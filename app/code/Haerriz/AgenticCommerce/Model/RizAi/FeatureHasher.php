<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\RizAi;

/**
 * Dependency-free feature encoder used by the bundled RizAI neural intent network.
 *
 * Training and PHP inference intentionally share the same CRC32 hashing contract so Magento does
 * not need Python, Torch, ONNX Runtime or a model server to execute this classifier.
 */
final class FeatureHasher
{
    /** @return array<int,float> sparse L2-normalized vector keyed by feature index */
    public function encode(string $message, int $dimension): array
    {
        if ($dimension < 32) {
            return [];
        }
        $text = $this->normalize($message);
        if ($text === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $counts = [];
        foreach ($tokens as $token) {
            $this->increment($counts, $this->index('w:' . $token, $dimension));
        }
        for ($i = 0, $n = count($tokens) - 1; $i < $n; $i++) {
            $this->increment($counts, $this->index('b:' . $tokens[$i] . ' ' . $tokens[$i + 1], $dimension));
        }

        $compact = ' ' . $text . ' ';
        $length = mb_strlen($compact, 'UTF-8');
        foreach ([3, 4] as $gram) {
            if ($length < $gram) {
                continue;
            }
            for ($i = 0; $i <= $length - $gram; $i++) {
                $feature = 'c' . $gram . ':' . mb_substr($compact, $i, $gram, 'UTF-8');
                $this->increment($counts, $this->index($feature, $dimension));
            }
        }

        $sumSquares = 0.0;
        foreach ($counts as $value) {
            $sumSquares += $value * $value;
        }
        if ($sumSquares <= 0.0) {
            return [];
        }
        $norm = sqrt($sumSquares);
        foreach ($counts as $index => $value) {
            $counts[$index] = $value / $norm;
        }
        ksort($counts, SORT_NUMERIC);
        return $counts;
    }

    public function normalize(string $message): string
    {
        $text = mb_strtolower(trim($message), 'UTF-8');
        // Match the offline model's English/Unicode alphanumeric normalization without requiring ext-intl.
        $text = preg_replace('/[^\p{L}\p{N}_\s]+/u', ' ', $text) ?? '';
        $text = str_replace('_', ' ', $text);
        return trim((string)(preg_replace('/\s+/u', ' ', $text) ?? ''));
    }

    /** @param array<int,float> $counts */
    private function increment(array &$counts, int $index): void
    {
        $counts[$index] = ($counts[$index] ?? 0.0) + 1.0;
    }

    private function index(string $feature, int $dimension): int
    {
        // sprintf('%u') normalizes crc32's signed/unsigned platform representation.
        $unsigned = (int)sprintf('%u', crc32($feature));
        return $unsigned % $dimension;
    }
}
