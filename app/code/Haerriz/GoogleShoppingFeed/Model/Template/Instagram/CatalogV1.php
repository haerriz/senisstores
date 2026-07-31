<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template\Instagram;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;

class CatalogV1 implements FeedTemplateInterface
{
    public function getCode(): string { return 'instagram_catalog_v1'; }
    public function getName(): string { return 'Instagram Shopping Catalog (CSV)'; }
    public function getDefaultMapping(): array {
        return [
            'id' => 'sku',
            'title' => 'name',
            'description' => 'description',
            'availability' => 'in stock',
            'condition' => 'new',
            'price' => 'price',
            'link' => 'product_url',
            'image_link' => 'image_url',
            'brand' => 'brand'
        ];
    }
}
