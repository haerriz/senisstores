<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template\Pinterest;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;

class CatalogV1 implements FeedTemplateInterface
{
    public function getCode(): string { return 'pinterest_catalog_v1'; }
    public function getName(): string { return 'Pinterest Product Catalog (CSV)'; }
    public function getDefaultMapping(): array {
        return [
            'id' => 'sku',
            'title' => 'name',
            'description' => 'description',
            'link' => 'product_url',
            'image_link' => 'image_url',
            'price' => 'price',
            'availability' => 'in stock'
        ];
    }
}
