<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Ai;

use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

/** Native OpenAI Responses API provider with function/tool calling and optional reasoning effort. */
class OpenAiResponsesProvider implements ProviderInterface, ResponseProviderInterface
{
    public function __construct(
        private Config $config,
        private Curl $curl,
        private ProviderPrompt $prompt,
        private EndpointPolicy $endpointPolicy,
        private LoggerInterface $logger
    ) {}

    public function plan(string $message, array $context, array $toolDefinitions): ?array
    {
        $endpoint = $this->config->getOpenAiEndpoint();
        $model = $this->config->getOpenAiModel();
        $key = $this->config->getOpenAiApiKey();
        if ($endpoint === '' || $model === '' || $key === '') return null;

        $tools = [];
        foreach ($toolDefinitions as $definition) {
            $function = (array)($definition['function'] ?? []);
            if (($function['name'] ?? '') === '') continue;
            $tools[] = [
                'type' => 'function',
                'name' => (string)$function['name'],
                'description' => (string)($function['description'] ?? ''),
                'parameters' => $this->normalizeToolSchema(
                    (array)($function['parameters'] ?? ['type' => 'object', 'properties' => []])
                ),
            ];
        }
        $payload = [
            'model' => $model,
            'instructions' => $this->prompt->system(),
            'input' => json_encode(['message'=>$message,'context'=>$this->prompt->safeContext($context)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'tools' => $tools,
            'tool_choice' => 'auto',
            'max_output_tokens' => $this->config->getAiMaxTokens(),
        ];
        $effort = $this->config->getAiReasoningEffort();
        if ($effort !== 'auto' && $effort !== 'none') $payload['reasoning'] = ['effort'=>$effort];

        try {
            $data = $this->post($endpoint, $key, $payload);
            if (!is_array($data)) return null;
            $calls = [];
            $assistant = '';
            foreach ((array)($data['output'] ?? []) as $item) {
                if (($item['type'] ?? '') === 'function_call') {
                    $args = json_decode((string)($item['arguments'] ?? '{}'), true);
                    if (($item['name'] ?? '') !== '' && is_array($args)) {
                        $calls[] = ['name'=>(string)$item['name'],'arguments'=>$args];
                    }
                }
                if (($item['type'] ?? '') === 'message') {
                    foreach ((array)($item['content'] ?? []) as $content) {
                        if (($content['type'] ?? '') === 'output_text') $assistant .= (string)($content['text'] ?? '');
                    }
                }
            }
            if ($calls === []) return null;
            return ['assistant_message'=>trim($assistant),'tools'=>array_slice($calls,0,$this->config->getMaxToolCalls())];
        } catch (\Throwable $e) {
            $this->logger->warning('OpenAI Responses planner failed; deterministic fallback will be used.', [
                'exception_class' => $e::class,
                'provider_error' => mb_substr($e->getMessage(), 0, 500),
            ]);
            return null;
        }
    }

    public function synthesize(string $message, array $facts, array $context = []): ?string
    {
        try {
            $data = $this->post($this->config->getOpenAiEndpoint(), $this->config->getOpenAiApiKey(), [
                'model'=>$this->config->getOpenAiModel(),
                'instructions'=>$this->prompt->system() . ' Write a concise shopper-facing answer using only the supplied authoritative facts. If a fact is absent, say it is not stated.',
                'input'=>json_encode(['message'=>$message,'facts'=>$facts], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
                'max_output_tokens'=>$this->config->getAiMaxTokens(),
            ]);
            $text='';
            foreach ((array)($data['output'] ?? []) as $item) if (($item['type'] ?? '')==='message') foreach ((array)($item['content'] ?? []) as $content) if (($content['type'] ?? '')==='output_text') $text.=(string)($content['text']??'');
            return trim($text) !== '' ? trim($text) : null;
        } catch (\Throwable $e) {
            $this->logger->warning('OpenAI response synthesis failed.', ['exception_class'=>$e::class]);
            return null;
        }
    }

    private function post(string $endpoint, string $key, array $payload): ?array
    {
        if ($endpoint==='' || $key==='') return null;
        $this->endpointPolicy->assertAllowed($endpoint, $this->config->isInsecureAiEndpointAllowed(), $this->config->isPrivateAiEndpointAllowed(), $this->config->getAiEndpointHostAllowlist());
        $this->curl->setTimeout($this->config->getAiTimeout());
        $this->curl->setHeaders(['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json']);
        $this->curl->post($endpoint, (string)json_encode($payload));
        if ($this->curl->getStatus()<200 || $this->curl->getStatus()>=300) {
            $errorData = json_decode($this->curl->getBody(), true);
            $errorMessage = is_array($errorData) ? trim((string)($errorData['error']['message'] ?? '')) : '';
            throw new \RuntimeException(
                'HTTP ' . $this->curl->getStatus() . ($errorMessage !== '' ? ': ' . mb_substr($errorMessage, 0, 400) : '')
            );
        }
        $data=json_decode($this->curl->getBody(),true);
        return is_array($data)?$data:null;
    }

    private function normalizeToolSchema(array $schema): array
    {
        if (($schema['type'] ?? '') === 'object') {
            $properties = $schema['properties'] ?? [];
            if ($properties instanceof \stdClass) {
                $properties = (array)$properties;
            }
            if (is_array($properties)) {
                foreach ($properties as $name => $property) {
                    if (is_array($property)) {
                        $properties[$name] = $this->normalizeToolSchema($property);
                    }
                }
                $schema['properties'] = $properties === [] ? new \stdClass() : $properties;
            }
        }
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->normalizeToolSchema($schema['items']);
        }
        return $schema;
    }
}
