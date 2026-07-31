<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template\Ebay;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;

class InventoryV1 implements FeedTemplateInterface
{
    public function getCode(): string { return 'ebay_inventory_v1'; }
    public function getName(): string { return 'eBay Inventory Feed (CSV)'; }
    public function getDefaultMapping(): array {
        return [
            'SKU' => 'sku',
            'Title' => 'name',
            'Description' => 'description',
            'Price' => 'price',
            'Quantity' => 'qty'
        ];
    }
}
