<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Ai;

use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

/** Google Gemini generateContent provider with native function calling. */
class GeminiProvider implements ProviderInterface, ResponseProviderInterface
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
        $key=$this->config->getGeminiApiKey(); $model=$this->config->getGeminiModel();
        if ($key==='' || $model==='') return null;
        $declarations=[];
        foreach ($toolDefinitions as $definition) {
            $f=(array)($definition['function']??[]); if (($f['name']??'')==='') continue;
            $declarations[]=['name'=>(string)$f['name'],'description'=>(string)($f['description']??''),'parameters'=>(array)($f['parameters']??['type'=>'object','properties'=>[]])];
        }
        $payload=[
            'systemInstruction'=>['parts'=>[['text'=>$this->prompt->system()]]],
            'contents'=>[['role'=>'user','parts'=>[['text'=>json_encode(['message'=>$message,'context'=>$this->prompt->safeContext($context)],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]]]],
            'tools'=>[['functionDeclarations'=>$declarations]],
            'toolConfig'=>['functionCallingConfig'=>['mode'=>'AUTO']],
            'generationConfig'=>['maxOutputTokens'=>$this->config->getAiMaxTokens()],
        ];
        $this->applyThinkingConfig($payload, $model);
        try {
            $data=$this->post($model,$key,$payload); if (!is_array($data)) return null;
            $parts=(array)($data['candidates'][0]['content']['parts']??[]); $calls=[]; $assistant='';
            foreach ($parts as $part) {
                if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                    $fc=$part['functionCall']; if (($fc['name']??'')!=='') $calls[]=['name'=>(string)$fc['name'],'arguments'=>(array)($fc['args']??[])];
                } elseif (isset($part['text'])) $assistant.=(string)$part['text'];
            }
            if ($calls===[]) return null;
            return ['assistant_message'=>trim($assistant),'tools'=>array_slice($calls,0,$this->config->getMaxToolCalls())];
        } catch (\Throwable $e) {
            $this->logger->warning('Gemini planner failed; deterministic fallback will be used.', ['exception_class'=>$e::class]);
            return null;
        }
    }

    public function synthesize(string $message, array $facts, array $context = []): ?string
    {
        try {
            $payload=[
                'systemInstruction'=>['parts'=>[['text'=>$this->prompt->system().' Write a concise shopper-facing answer using only supplied authoritative facts.']]],
                'contents'=>[['role'=>'user','parts'=>[['text'=>json_encode(['message'=>$message,'facts'=>$facts],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]]]],
                'generationConfig'=>['maxOutputTokens'=>$this->config->getAiMaxTokens()],
            ];
            $this->applyThinkingConfig($payload, $this->config->getGeminiModel());
            $data=$this->post($this->config->getGeminiModel(),$this->config->getGeminiApiKey(),$payload);
            $text=''; foreach ((array)($data['candidates'][0]['content']['parts']??[]) as $part) if (isset($part['text'])) $text.=(string)$part['text'];
            return trim($text)!==''?trim($text):null;
        } catch (\Throwable $e) {
            $this->logger->warning('Gemini response synthesis failed.', ['exception_class'=>$e::class]); return null;
        }
    }

    private function applyThinkingConfig(array &$payload, string $model): void
    {
        $level = $this->config->getAiReasoningEffort();
        if (!in_array($level, ['minimal', 'low', 'medium', 'high'], true)) {
            return;
        }
        // thinkingLevel is the Gemini 3.x control. Gemini 2.5 uses thinkingBudget instead;
        // omit an override there so model defaults remain valid across 2.5 variants.
        if (preg_match('/^gemini-3(?:[._-]|$)/i', $model)) {
            $payload['generationConfig']['thinkingConfig'] = ['thinkingLevel' => $level];
        }
    }

    private function post(string $model,string $key,array $payload): ?array
    {
        if ($model===''||$key==='') return null;
        $base=rtrim($this->config->getGeminiEndpoint(),'/');
        $this->endpointPolicy->assertAllowed($base, $this->config->isInsecureAiEndpointAllowed(), $this->config->isPrivateAiEndpointAllowed(), $this->config->getAiEndpointHostAllowlist());
        $url=$base.'/'.rawurlencode($model).':generateContent';
        $this->curl->setTimeout($this->config->getAiTimeout());
        // Keep API credentials out of query strings so reverse-proxy/access logs do not capture them.
        $this->curl->setHeaders(['Content-Type'=>'application/json','x-goog-api-key'=>$key]);
        $this->curl->post($url,(string)json_encode($payload));
        if ($this->curl->getStatus()<200||$this->curl->getStatus()>=300) throw new \RuntimeException('HTTP '.$this->curl->getStatus());
        $data=json_decode($this->curl->getBody(),true); return is_array($data)?$data:null;
    }
}
