<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template\Amazon;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;

class CatalogV1 implements FeedTemplateInterface
{
    public function getCode(): string { return 'amazon_catalog_v1'; }
    public function getName(): string { return 'Amazon Seller Catalog (CSV)'; }
    public function getDefaultMapping(): array {
        return [
            'sku' => 'sku',
            'product_name' => 'name',
            'description' => 'description',
            'price' => 'price',
            'quantity' => 'qty'
        ];
    }
}
