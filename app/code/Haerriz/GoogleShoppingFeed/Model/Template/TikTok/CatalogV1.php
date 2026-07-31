<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template\TikTok;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;

class CatalogV1 implements FeedTemplateInterface
{
    public function getCode(): string { return 'tiktok_catalog_v1'; }
    public function getName(): string { return 'TikTok Commerce Catalog (CSV)'; }
    public function getDefaultMapping(): array {
        return [
            'sku_id' => 'sku',
            'title' => 'name',
            'description' => 'description',
            'availability' => 'in stock',
            'condition' => 'new',
            'price' => 'price',
            'landing_url' => 'product_url',
            'image_link' => 'image_url'
        ];
    }
}
