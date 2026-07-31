<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template\Rakuten;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;

class CatalogV1 implements FeedTemplateInterface
{
    public function getCode(): string { return 'rakuten_catalog_v1'; }
    public function getName(): string { return 'Rakuten Advertising Feed (CSV)'; }
    public function getDefaultMapping(): array {
        return [
            'id' => 'sku',
            'product_name' => 'name',
            'product_url' => 'product_url',
            'image_url' => 'image_url',
            'price' => 'price'
        ];
    }
}
