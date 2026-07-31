<?php
namespace Haerriz\GoogleShoppingFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Channel implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'google_shopping_v1', 'label' => __('Google Shopping (v1.0 Official XML)')],
            ['value' => 'meta_catalog_v1', 'label' => __('Meta Facebook / Instagram Catalog (v2.0 Official CSV)')],
            ['value' => 'snapchat_catalog_v1', 'label' => __('Snapchat Catalog (v1.0 Official CSV)')],
            ['value' => 'tiktok_catalog_v1', 'label' => __('TikTok Commerce Catalog (v1.1 Official CSV)')],
            ['value' => 'pinterest_catalog_v1', 'label' => __('Pinterest Catalog (v1.0 Official CSV)')],
            ['value' => 'microsoft_merchant_v1', 'label' => __('Microsoft Bing Merchant (v1.0 Official XML)')],
            ['value' => 'amazon_catalog_v1', 'label' => __('Amazon Seller Catalog (v4.1 Official CSV)')],
            ['value' => 'ebay_inventory_v1', 'label' => __('eBay Inventory Feed (v1.0 Official CSV)')],
            ['value' => 'rakuten_catalog_v1', 'label' => __('Rakuten Advertising Catalog (v1.0 Official CSV)')],
            ['value' => 'openai_commerce_v1', 'label' => __('OpenAI Agentic Commerce (v1.0 Agent JSONL)')],
        ];
    }
}
