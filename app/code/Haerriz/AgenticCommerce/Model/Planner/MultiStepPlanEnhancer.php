<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Planner;

use Haerriz\AgenticCommerce\Model\Config;

/**
 * Adds bounded dependent steps to an already-safe first-step plan.
 *
 * The enhancer never invents a mutation. Any appended write action must be explicitly requested in
 * the current shopper message. Later tools may reference results produced earlier in the same turn
 * through AgentService's server-owned context updates.
 */
class MultiStepPlanEnhancer
{
    public function __construct(private Config $config) {}

    public function enhance(string $message, array $context, array $plan): array
    {
        $storeId = (int)($context['identity']['store_id'] ?? 0);
        if ($this->config->getDefaultReasoningMode($storeId) === 'fast') {
            return $plan;
        }
        $tools = (array)($plan['tools'] ?? []);
        if ($tools === []) {
            return $plan;
        }
        $first = (string)($tools[0]['name'] ?? '');
        $lower = mb_strtolower($message);
        if ($this->isNegatedMutation($lower)) {
            return $plan;
        }

        if ($first === 'search_products') {
            if ($this->asksFollowupStock($lower)) {
                $tools[] = ['name' => 'get_inventory', 'arguments' => ['index' => 1, 'query' => $message]];
            }
            if ($this->asksFollowupCartAdd($lower)) {
                $tools[] = ['name' => 'add_recent_product_to_cart', 'arguments' => ['index' => 1, 'quantity' => 1.0]];
            }
            if (preg_match('/\b(?:then|and(?:\s+then)?)\s+compare\s+(?:the\s+)?first\s+two\b/u', $lower)) {
                $tools[] = ['name' => 'compare_recent_products', 'arguments' => ['indexes' => [1, 2], 'focus' => [], 'goal' => '']];
            }
            if (preg_match('/\b(?:then|and(?:\s+then)?)\s+(?:tell me|show|check)\s+(?:the\s+)?(?:price|pricing|cost)\s+(?:of\s+)?(?:the\s+)?first\b/u', $lower)) {
                $tools[] = ['name' => 'get_product_price', 'arguments' => ['index' => 1]];
            }
            if (preg_match('/\b(?:then|and(?:\s+then)?)\s+(?:describe|summari[sz]e|show details (?:for|of)|tell me about)\s+(?:the\s+)?first\b/u', $lower)) {
                $tools[] = ['name' => 'get_product_content', 'arguments' => ['index' => 1]];
            }
        }

        // Combine authoritative commerce reads when the user explicitly asks for both in one turn.
        if ($first === 'get_inventory' && preg_match('/\b(?:price|pricing|cost|how much)\b/u', $lower)) {
            $tools[] = ['name' => 'get_product_price', 'arguments' => $this->sameProductReference($tools[0]['arguments'] ?? [])];
        }
        if ($first === 'get_product_price' && preg_match('/\b(?:stock|availability|available|left|remaining)\b/u', $lower)) {
            $tools[] = ['name' => 'get_inventory', 'arguments' => $this->sameProductReference($tools[0]['arguments'] ?? []) + ['query' => $message]];
        }
        if ($first === 'get_product_content') {
            if (preg_match('/\b(?:stock|availability|available|left|remaining)\b/u', $lower)) {
                $tools[] = ['name' => 'get_inventory', 'arguments' => $this->sameProductReference($tools[0]['arguments'] ?? []) + ['query' => $message]];
            }
            if (preg_match('/\b(?:price|pricing|cost|how much)\b/u', $lower)) {
                $tools[] = ['name' => 'get_product_price', 'arguments' => $this->sameProductReference($tools[0]['arguments'] ?? [])];
            }
            if (preg_match('/\b(?:similar|alternative|alternatives|related)\b/u', $lower)) {
                $tools[] = ['name' => 'get_recommendations', 'arguments' => $this->sameProductReference($tools[0]['arguments'] ?? []) + ['type' => 'related', 'limit' => 6]];
            }
        }

        if ($first === 'get_checkout_state') {
            if (preg_match('/\b(?:shipping|delivery)\s+(?:method|methods|option|options)\b/u', $lower)) {
                $tools[] = ['name' => 'get_shipping_methods', 'arguments' => []];
            }
            if (preg_match('/\bpayment\s+(?:method|methods|option|options)\b/u', $lower)) {
                $tools[] = ['name' => 'get_payment_methods', 'arguments' => []];
            }
        }
        if ($first === 'get_cart' && preg_match('/\bcheckout\s+(?:state|status|ready|readiness)\b/u', $lower)) {
            $tools[] = ['name' => 'get_checkout_state', 'arguments' => []];
        }
        if ($first === 'compare_recent_products' && $this->asksFollowupCartAdd($lower)) {
            $tools[] = ['name' => 'add_recent_product_to_cart', 'arguments' => ['index' => 1, 'quantity' => 1.0]];
        }

        $plan['tools'] = $this->deduplicate(array_slice($tools, 0, $this->config->getMaxToolCalls($storeId)));
        return $plan;
    }

    private function asksFollowupStock(string $lower): bool
    {
        return (bool)preg_match('/\b(?:then|and(?:\s+then)?)\s+(?:(?:tell me|check|show)\s+)?(?:the\s+)?(?:stock|availability|how many[^.?!]*left|how many[^.?!]*remaining)\b/u', $lower);
    }

    private function asksFollowupCartAdd(string $lower): bool
    {
        return (bool)preg_match('/\b(?:then|and(?:\s+then)?)\s+(?:add|put)\s+(?:the\s+)?(?:first|cheapest|top)\s+(?:one|product|item)?(?:\s*(?:to|in)\s+(?:my\s+)?(?:cart|basket))?\b/u', $lower);
    }

    private function isNegatedMutation(string $lower): bool
    {
        return (bool)preg_match('/\b(?:do not|don[\'’]?t|never|without)\b[^.?!]{0,80}\b(?:add|put|remove|delete|clear|apply|place|order|subscribe|update|set)\b/u', $lower);
    }

    private function sameProductReference(array $arguments): array
    {
        foreach (['sku', 'index', 'query'] as $key) {
            if (array_key_exists($key, $arguments) && $arguments[$key] !== '' && $arguments[$key] !== null) {
                return [$key => $arguments[$key]];
            }
        }
        return ['index' => 1];
    }

    private function deduplicate(array $tools): array
    {
        $seen = [];
        $out = [];
        foreach ($tools as $tool) {
            $key = (string)($tool['name'] ?? '') . ':' . json_encode($tool['arguments'] ?? []);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $tool;
        }
        return $out;
    }
}
