<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template\Google;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;

class ShoppingV1 implements FeedTemplateInterface
{
    public function getCode(): string { return 'google_shopping_v1'; }
    public function getName(): string { return 'Google Shopping v1 XML'; }
    public function getDefaultMapping(): array
    {
        return [
            'id' => 'sku',
            'title' => 'name',
            'description' => 'description',
            'link' => 'product_url',
            'image_link' => 'image_url',
            'price' => 'price'
        ];
    }
}
