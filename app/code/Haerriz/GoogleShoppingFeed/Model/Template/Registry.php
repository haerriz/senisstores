<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateRegistryInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;

class Registry implements FeedTemplateRegistryInterface
{
    private array $templates = [];

    public function __construct()
    {
        $this->templates['google_shopping_v1'] = new Google\ShoppingV1();
        $this->templates['meta_catalog_v1'] = new Meta\CatalogV1();
        $this->templates['instagram_catalog_v1'] = new Instagram\CatalogV1();
        $this->templates['snapchat_catalog_v1'] = new Snapchat\CatalogV1();
        $this->templates['tiktok_catalog_v1'] = new TikTok\CatalogV1();
        $this->templates['pinterest_catalog_v1'] = new Pinterest\CatalogV1();
        $this->templates['microsoft_merchant_v1'] = new Microsoft\MerchantV1();
        $this->templates['amazon_catalog_v1'] = new Amazon\CatalogV1();
        $this->templates['ebay_inventory_v1'] = new Ebay\InventoryV1();
        $this->templates['rakuten_catalog_v1'] = new Rakuten\CatalogV1();
        $this->templates['openai_commerce_v1'] = new OpenAi\CommerceV1();
    }

    public function getTemplate(string $code): FeedTemplateInterface
    {
        return $this->templates[$code] ?? $this->templates['google_shopping_v1'];
    }

    public function getTemplates(): array
    {
        return $this->templates;
    }
}
