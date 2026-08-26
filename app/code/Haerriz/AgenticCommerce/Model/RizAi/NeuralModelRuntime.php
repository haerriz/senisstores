<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\RizAi;

use Psr\Log\LoggerInterface;

/**
 * Pure-PHP inference runtime for the bundled RizAI feed-forward neural intent model.
 *
 * This is real learned-weight inference. It is deliberately small and bounded: the network predicts
 * commerce intent candidates; ToolPolicy and Magento services remain the authority for actions/data.
 */
final class NeuralModelRuntime
{
    private const MODEL_FILE = '/RizAi/Model/rizai-commerce-intent-v1.json';
    private const CHECKSUM_FILE = '/RizAi/Model/rizai-commerce-intent-v1.sha256';

    /** @var array<string,mixed>|null */
    private ?array $model = null;
    private bool $loadAttempted = false;
    private bool $checksumVerified = false;

    public function __construct(
        private FeatureHasher $featureHasher,
        private LoggerInterface $logger
    ) {}

    /**
     * @return array{available:bool,model_id:string,model_type:string,intent:string,confidence:float,margin:float,top:array<int,array{intent:string,confidence:float}>}
     */
    public function predict(string $message): array
    {
        $model = $this->load();
        if ($model === null) {
            return $this->emptyPrediction();
        }
        $architecture = (array)($model['architecture'] ?? []);
        $dimension = (int)($architecture['input_dim'] ?? 0);
        $hiddenDim = (int)($architecture['hidden_dim'] ?? 0);
        $labels = array_values(array_map('strval', (array)($model['labels'] ?? [])));
        $weights = (array)($model['weights'] ?? []);
        $w1 = (array)($weights['w1'] ?? []);
        $b1 = (array)($weights['b1'] ?? []);
        $w2 = (array)($weights['w2'] ?? []);
        $b2 = (array)($weights['b2'] ?? []);
        if ($dimension <= 0 || $hiddenDim <= 0 || $labels === [] || count($w1) !== $hiddenDim || count($b1) !== $hiddenDim
            || count($w2) !== count($labels) || count($b2) !== count($labels)) {
            return $this->emptyPrediction();
        }

        $sparse = $this->featureHasher->encode($message, $dimension);
        if ($sparse === []) {
            return $this->emptyPrediction();
        }

        $hidden = array_fill(0, $hiddenDim, 0.0);
        for ($j = 0; $j < $hiddenDim; $j++) {
            $sum = (float)($b1[$j] ?? 0.0);
            $row = (array)($w1[$j] ?? []);
            foreach ($sparse as $index => $value) {
                $sum += $value * (float)($row[$index] ?? 0.0);
            }
            $hidden[$j] = $sum > 0.0 ? $sum : 0.0; // ReLU
        }

        $logits = [];
        foreach ($labels as $k => $label) {
            $sum = (float)($b2[$k] ?? 0.0);
            $row = (array)($w2[$k] ?? []);
            for ($j = 0; $j < $hiddenDim; $j++) {
                $sum += $hidden[$j] * (float)($row[$j] ?? 0.0);
            }
            $logits[$label] = $sum;
        }
        $max = max($logits);
        $denominator = 0.0;
        $probabilities = [];
        foreach ($logits as $label => $logit) {
            $value = exp(max(-60.0, min(60.0, $logit - $max)));
            $probabilities[$label] = $value;
            $denominator += $value;
        }
        if ($denominator <= 0.0) {
            return $this->emptyPrediction();
        }
        foreach ($probabilities as $label => $value) {
            $probabilities[$label] = $value / $denominator;
        }
        arsort($probabilities, SORT_NUMERIC);
        $top = [];
        foreach (array_slice($probabilities, 0, 3, true) as $label => $confidence) {
            $top[] = ['intent' => (string)$label, 'confidence' => round((float)$confidence, 6)];
        }
        $first = $top[0] ?? ['intent' => '', 'confidence' => 0.0];
        $second = $top[1]['confidence'] ?? 0.0;
        return [
            'available' => true,
            'model_id' => (string)($model['model_id'] ?? 'rizai-commerce-intent-v1'),
            'model_type' => (string)($model['model_type'] ?? 'feed_forward_neural_network'),
            'intent' => (string)$first['intent'],
            'confidence' => (float)$first['confidence'],
            'margin' => round(max(0.0, (float)$first['confidence'] - (float)$second), 6),
            'top' => $top,
        ];
    }

    /** @return array<string,mixed> */
    public function metadata(): array
    {
        $model = $this->load();
        if ($model === null) {
            return ['available' => false];
        }
        return [
            'available' => true,
            'model_id' => (string)($model['model_id'] ?? ''),
            'model_family' => (string)($model['model_family'] ?? ''),
            'model_type' => (string)($model['model_type'] ?? ''),
            'architecture' => (array)($model['architecture'] ?? []),
            'training' => (array)($model['training'] ?? []),
            'checksum_verified' => $this->checksumVerified,
        ];
    }

    /** @return array<string,mixed>|null */
    private function load(): ?array
    {
        if ($this->loadAttempted) {
            return $this->model;
        }
        $this->loadAttempted = true;
        $path = dirname(__DIR__, 2) . self::MODEL_FILE;
        try {
            $raw = @file_get_contents($path);
            if (!is_string($raw) || $raw === '') {
                $this->logger->warning('RizAI neural model file could not be read.', ['path' => $path]);
                return null;
            }
            $checksumPath = dirname(__DIR__, 2) . self::CHECKSUM_FILE;
            $checksumRaw = @file_get_contents($checksumPath);
            $expectedChecksum = is_string($checksumRaw) ? strtolower(trim((string)preg_split('/\s+/', trim($checksumRaw))[0])) : '';
            $actualChecksum = hash('sha256', $raw);
            if ($expectedChecksum === '' || !preg_match('/^[a-f0-9]{64}$/', $expectedChecksum)
                || !hash_equals($expectedChecksum, $actualChecksum)) {
                $this->logger->warning('RizAI neural model checksum validation failed.', ['path' => $path]);
                return null;
            }
            $this->checksumVerified = true;
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || (int)($decoded['schema_version'] ?? 0) !== 1) {
                $this->logger->warning('RizAI neural model has an unsupported schema.', ['path' => $path]);
                return null;
            }
            $this->model = $decoded;
            return $this->model;
        } catch (\Throwable $e) {
            $this->logger->warning('RizAI neural model failed to load.', ['exception_class' => $e::class]);
            return null;
        }
    }

    /** @return array{available:bool,model_id:string,model_type:string,intent:string,confidence:float,margin:float,top:array} */
    private function emptyPrediction(): array
    {
        return ['available' => false, 'model_id' => '', 'model_type' => '', 'intent' => '', 'confidence' => 0.0, 'margin' => 0.0, 'top' => []];
    }
}
