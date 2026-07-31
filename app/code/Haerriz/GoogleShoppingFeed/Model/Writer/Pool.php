<?php
namespace Haerriz\GoogleShoppingFeed\Model\Writer;

use Haerriz\GoogleShoppingFeed\Api\WriterInterface;

class Pool
{
    private array $writers;

    public function __construct(
        GoogleXml $googleXml,
        Delimited $delimited,
        JsonLines $jsonLines,
        array $writers = []
    ) {
        $this->writers = array_merge([
            'xml' => $googleXml,
            'google_shopping_v1' => $googleXml,
            'microsoft_merchant_v1' => $googleXml,
            'csv' => $delimited,
            'tsv' => $delimited,
            'txt' => $delimited,
            'meta_catalog_v1' => $delimited,
            'instagram_catalog_v1' => $delimited,
            'snapchat_catalog_v1' => $delimited,
            'tiktok_catalog_v1' => $delimited,
            'pinterest_catalog_v1' => $delimited,
            'amazon_catalog_v1' => $delimited,
            'ebay_inventory_v1' => $delimited,
            'rakuten_catalog_v1' => $delimited,
            'jsonl' => $jsonLines,
            'json' => $jsonLines,
            'openai_commerce_v1' => $jsonLines,
        ], $writers);
    }

    public function get($format): WriterInterface
    {
        $key = strtolower(trim((string)$format));
        if (isset($this->writers[$key]) && $this->writers[$key] instanceof WriterInterface) {
            return $this->writers[$key];
        }

        if (str_contains($key, 'xml')) {
            return $this->writers['xml'];
        }
        if (str_contains($key, 'json')) {
            return $this->writers['jsonl'];
        }

        return $this->writers['csv'];
    }
}
