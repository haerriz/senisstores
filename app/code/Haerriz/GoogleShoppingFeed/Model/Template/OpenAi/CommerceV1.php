<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template\OpenAi;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;

class CommerceV1 implements FeedTemplateInterface
{
    public function getCode(): string { return 'openai_commerce_v1'; }
    public function getName(): string { return 'OpenAI / ChatGPT Agentic Commerce (JSONL)'; }
    public function getDefaultMapping(): array {
        return [
            'id' => 'sku',
            'name' => 'name',
            'description' => 'description',
            'url' => 'product_url',
            'image' => 'image_url',
            'price' => 'price'
        ];
    }
}
