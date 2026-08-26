<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Ai;

use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;
use Haerriz\AgenticCommerce\Model\Config;

class ResponseSynthesisService
{
    public function __construct(
        private Config $config,
        private ResponseProviderInterface $provider,
        private ExternalFactPolicy $factPolicy,
        private ToolPolicy $toolPolicy
    ) {}

    /** Mutations/transactions always keep the authoritative Magento tool wording. */
    public function synthesize(string $message,array $response,string $fallback,array $toolNames,array $identity=[]): string
    {
        $storeId=(int)($identity['store_id']??0);
        if (!$this->config->isAiResponseSynthesisEnabled($storeId) || $this->config->getAiProvider()==='deterministic') return $fallback;
        foreach ($toolNames as $tool) if ($this->toolPolicy->mutatesState((string)$tool)) return $fallback;
        $authoritativeCommerceReads = [
            'get_cart', 'get_wishlist', 'get_recent_orders', 'get_order',
            'get_checkout_state', 'get_shipping_methods', 'get_payment_methods',
            'get_store_information', 'answer_store_question', 'search_pages',
        ];
        foreach ($toolNames as $tool) {
            if (in_array((string)$tool, $authoritativeCommerceReads, true)) {
                return $fallback;
            }
        }
        $facts=$this->factPolicy->facts($response,$storeId); if ($facts===[]) return $fallback;
        $requiredFacts = [
            'get_inventory' => 'inventory',
            'compare_inventory' => 'inventories',
        ];
        foreach ($toolNames as $tool) {
            $required = $requiredFacts[(string)$tool] ?? null;
            // Never let an unrelated permitted fact rewrite an authoritative Magento result.
            // For example, catalog_only may expose total_count but intentionally omit cart data.
            if ($required !== null && !array_key_exists($required, $facts)) {
                return $fallback;
            }
        }
        $text=$this->provider->synthesize($message,$facts,['identity'=>['is_customer'=>(bool)($identity['is_customer']??false)]]);
        return is_string($text)&&trim($text)!==''?trim($text):$fallback;
    }
}
