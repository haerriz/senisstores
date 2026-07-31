<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template\Microsoft;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;

class MerchantV1 implements FeedTemplateInterface
{
    public function getCode(): string { return 'microsoft_merchant_v1'; }
    public function getName(): string { return 'Microsoft / Bing Shopping (XML)'; }
    public function getDefaultMapping(): array {
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
