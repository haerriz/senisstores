<?php
namespace Haerriz\GoogleShoppingFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FeedType implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'google_shopping_v1', 'label' => __('Google Shopping (XML)')],
            ['value' => 'meta_catalog_v1', 'label' => __('Meta / Facebook Catalog (CSV)')],
            ['value' => 'instagram_catalog_v1', 'label' => __('Instagram Shopping (CSV)')],
            ['value' => 'snapchat_catalog_v1', 'label' => __('Snapchat Product Catalog (CSV)')],
            ['value' => 'tiktok_catalog_v1', 'label' => __('TikTok Commerce Catalog (CSV)')],
            ['value' => 'pinterest_catalog_v1', 'label' => __('Pinterest Catalog (CSV)')],
            ['value' => 'microsoft_merchant_v1', 'label' => __('Microsoft / Bing Shopping (XML)')],
            ['value' => 'amazon_catalog_v1', 'label' => __('Amazon Seller Catalog (CSV)')],
            ['value' => 'ebay_inventory_v1', 'label' => __('eBay Inventory Feed (CSV)')],
            ['value' => 'rakuten_catalog_v1', 'label' => __('Rakuten Advertising (CSV)')],
            ['value' => 'openai_commerce_v1', 'label' => __('OpenAI / ChatGPT Agentic Commerce (JSONL)')],
        ];
    }
}
