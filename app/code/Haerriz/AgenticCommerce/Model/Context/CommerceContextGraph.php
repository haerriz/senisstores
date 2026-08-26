<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Context;

use Haerriz\AgenticCommerce\Api\CommerceContextProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds a bounded, privacy-safe graph of current storefront state for planning.
 * It contains relationships and positions, not customer IDs, cart tokens or payment secrets.
 */
class CommerceContextGraph
{
    /** @param array<string,CommerceContextProviderInterface> $providers */
    public function __construct(
        private LoggerInterface $logger,
        private array $providers = []
    ) {}

    public function build(array $context): array
    {
        $recent=[];
        foreach (array_slice((array)($context['recent_products']??[]),0,12) as $i=>$product) {
            $recent[]=['position'=>$i+1,'sku'=>(string)($product['sku']??''),'name'=>(string)($product['name']??'')];
        }
        $cart=[];
        foreach (array_slice((array)($context['cart_items']??[]),0,12) as $i=>$item) {
            $cart[]=['position'=>$i+1,'sku'=>(string)($item['sku']??''),'name'=>(string)($item['name']??''),'qty'=>(float)($item['qty']??$item['qty_ordered']??0)];
        }
        $identity=(array)($context['identity']??[]);
        $extensions=[];
        foreach (array_slice($this->providers, 0, 12, true) as $code=>$provider) {
            if (!$provider instanceof CommerceContextProviderInterface) continue;
            try {
                $safe=$this->sanitize((array)$provider->getContext($context),0);
                if ($safe!==[]) $extensions[mb_substr((string)$code,0,64)]=$safe;
            } catch (\Throwable $e) {
                // Extension context is optional; a third-party provider must not break commerce.
                $this->logger->warning('Agentic Commerce context provider failed.', [
                    'provider'=>(string)$code,
                    'exception'=>$e::class,
                ]);
            }
        }
        return [
            'search'=>[
                'phrase'=>mb_substr((string)($context['query_phrase']??''),0,180),
                'filters'=>array_slice(array_values((array)($context['filters']??[])),0,30),
            ],
            'recent_products'=>$recent,
            'cart_items'=>$cart,
            'shopper'=>['signed_in'=>(bool)($identity['is_customer']??false)],
            'has_pending_confirmation'=>!empty($context['confirmation']),
            'extensions'=>$extensions,
        ];
    }

    private function sanitize(array $value, int $depth): array
    {
        if ($depth>=3) return [];
        $out=[];$count=0;
        foreach ($value as $key=>$item) {
            if (++$count>30) break;
            $key=mb_substr((string)$key,0,64);
            if ($this->isSensitiveKey($key)) continue;
            if (is_array($item)) {
                $nested=$this->sanitize($item,$depth+1);
                if ($nested!==[]) $out[$key]=$nested;
                continue;
            }
            if (is_bool($item)||is_int($item)||is_float($item)) {$out[$key]=$item;continue;}
            if (is_string($item)) {$out[$key]=mb_substr(strip_tags($item),0,300);}
        }
        return $out;
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool)preg_match('/(?:password|passwd|secret|token|authorization|cookie|session|client_id|customer_id|quote_id|cart_id|address|email|phone|telephone|card|cvv|cvc|pan|source_code|stock_id)/i',$key);
    }
}
