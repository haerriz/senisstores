<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template;

class PresetRegistry
{
    /**
     * Get all supported ready-made platform templates
     *
     * @return array
     */
    public function getPresets()
    {
        return [
            'google' => [
                'name' => 'Google Shopping',
                'format' => 'xml',
                'schema_version' => '1.0',
                'last_verified' => '2026-07-29',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'mapping' => [
                    ['google_attribute' => 'g:id', 'magento_attribute' => 'sku'],
                    ['google_attribute' => 'g:title', 'magento_attribute' => 'name'],
                    ['google_attribute' => 'g:price', 'magento_attribute' => 'price', 'modifier' => 'round_price'],
                    ['google_attribute' => 'g:availability', 'magento_attribute' => 'quantity']
                ],
                'frequency' => 'daily'
            ],
            'meta' => [
                'name' => 'Meta/Facebook/Instagram Catalog',
                'format' => 'csv',
                'schema_version' => '2.0',
                'last_verified' => '2026-07-29',
                'utm_source' => 'facebook',
                'utm_medium' => 'cpc',
                'mapping' => [
                    ['google_attribute' => 'id', 'magento_attribute' => 'sku'],
                    ['google_attribute' => 'title', 'magento_attribute' => 'name'],
                    ['google_attribute' => 'price', 'magento_attribute' => 'price'],
                    ['google_attribute' => 'availability', 'magento_attribute' => 'quantity']
                ],
                'frequency' => 'daily'
            ],
            'bing' => [
                'name' => 'Microsoft/Bing Shopping',
                'format' => 'xml',
                'schema_version' => '1.0',
                'last_verified' => '2026-07-29',
                'mapping' => [
                    ['google_attribute' => 'g:id', 'magento_attribute' => 'sku'],
                    ['google_attribute' => 'g:title', 'magento_attribute' => 'name'],
                    ['google_attribute' => 'g:price', 'magento_attribute' => 'price']
                ],
                'frequency' => 'daily'
            ],
            'tiktok' => [
                'name' => 'TikTok Catalog',
                'format' => 'csv',
                'schema_version' => '1.1',
                'last_verified' => '2026-07-29',
                'mapping' => [
                    ['google_attribute' => 'sku_id', 'magento_attribute' => 'sku'],
                    ['google_attribute' => 'title', 'magento_attribute' => 'name'],
                    ['google_attribute' => 'price', 'magento_attribute' => 'price']
                ],
                'frequency' => 'daily'
            ],
            'pinterest' => [
                'name' => 'Pinterest Catalog',
                'format' => 'csv',
                'schema_version' => '1.0',
                'last_verified' => '2026-07-29',
                'mapping' => [
                    ['google_attribute' => 'id', 'magento_attribute' => 'sku'],
                    ['google_attribute' => 'title', 'magento_attribute' => 'name'],
                    ['google_attribute' => 'price', 'magento_attribute' => 'price']
                ],
                'frequency' => 'daily'
            ],
            'amazon' => [
                'name' => 'Amazon XML Feed',
                'format' => 'xml',
                'schema_version' => '4.1',
                'last_verified' => '2026-07-29',
                'mapping' => [
                    ['google_attribute' => 'SKU', 'magento_attribute' => 'sku'],
                    ['google_attribute' => 'StandardProductID', 'magento_attribute' => 'sku']
                ],
                'frequency' => 'hourly'
            ],
            'ebay' => [
                'name' => 'eBay CSV Feed',
                'format' => 'csv',
                'schema_version' => '1.0',
                'last_verified' => '2026-07-29',
                'mapping' => [
                    ['google_attribute' => 'Action', 'magento_attribute' => 'sku'],
                    ['google_attribute' => 'ItemID', 'magento_attribute' => 'sku']
                ],
                'frequency' => 'daily'
            ],
            'rakuten' => [
                'name' => 'Rakuten Feed (Beta)',
                'format' => 'csv',
                'schema_version' => '1.0',
                'last_verified' => '2026-07-29',
                'mapping' => [
                    ['google_attribute' => 'id', 'magento_attribute' => 'sku'],
                    ['google_attribute' => 'title', 'magento_attribute' => 'name']
                ],
                'frequency' => 'daily'
            ],
            'openai' => [
                'name' => 'OpenAI Agentic Commerce',
                'format' => 'jsonl.gz',
                'schema_version' => '1.0',
                'last_verified' => '2026-07-29',
                'mapping' => [
                    ['google_attribute' => 'id', 'magento_attribute' => 'sku'],
                    ['google_attribute' => 'name', 'magento_attribute' => 'name'],
                    ['google_attribute' => 'price', 'magento_attribute' => 'price'],
                    ['google_attribute' => 'availability', 'magento_attribute' => 'quantity']
                ],
                'frequency' => 'daily'
            ]
        ];
    }
}
