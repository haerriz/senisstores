<?php
namespace Haerriz\GoogleShoppingFeed\Model\Template;

use Haerriz\GoogleShoppingFeed\Api\FeedTemplateRegistryInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedTemplateInterface;
use Psr\Log\LoggerInterface;

class PresetRegistry implements FeedTemplateRegistryInterface
{
    private $logger;
    private ?array $presets = null;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * FIX 20: Implements FeedTemplateRegistryInterface::getTemplate()
     */
    public function getTemplate(string $code): FeedTemplateInterface
    {
        $presets = $this->getPresets();
        if (!isset($presets[$code])) {
            $this->logger->warning("PresetRegistry: Unknown template code [{$code}], using 'google' as default");
            $code = 'google';
        }
        return new Preset($code, $presets[$code]);
    }

    /**
     * FIX 20: Implements FeedTemplateRegistryInterface::getTemplates()
     */
    public function getTemplates(): array
    {
        $presets = $this->getPresets();
        $result  = [];
        foreach ($presets as $code => $data) {
            $result[$code] = new Preset($code, $data);
        }
        $this->logger->debug("PresetRegistry: getTemplates() returned " . count($result) . " templates");
        return $result;
    }

    public function getPresets(): array
    {
        if ($this->presets !== null) {
            return $this->presets;
        }

        $this->presets = [
            'google'    => $this->buildGoogle(),
            'meta'      => $this->buildMeta(),
            'instagram' => $this->buildInstagram(),
            'snapchat'  => $this->buildSnapchat(),
            'tiktok'    => $this->buildTikTok(),
            'pinterest' => $this->buildPinterest(),
            'microsoft' => $this->buildMicrosoft(),
            'amazon'    => $this->buildAmazon(),
            'ebay'      => $this->buildEbay(),
            'rakuten'   => $this->buildRakuten(),
            'openai'    => $this->buildOpenAi(),
        ];

        $this->logger->debug("PresetRegistry: Loaded " . count($this->presets) . " channel presets");
        return $this->presets;
    }

    private function buildGoogle(): array
    {
        return ['name'=>'Google Shopping','format'=>'xml','mapping'=>[
            ['google_attribute'=>'g:id',          'magento_attribute'=>'sku'],
            ['google_attribute'=>'g:title',        'magento_attribute'=>'name'],
            ['google_attribute'=>'g:description',  'magento_attribute'=>'description'],
            ['google_attribute'=>'g:link',         'magento_attribute'=>'product_url'],
            ['google_attribute'=>'g:image_link',   'magento_attribute'=>'image_url'],
            ['google_attribute'=>'g:price',        'magento_attribute'=>'price'],
            ['google_attribute'=>'g:availability', 'magento_attribute'=>'quantity_and_stock_status'],
            ['google_attribute'=>'g:condition',    'source_type'=>'static','static_value'=>'new'],
            ['google_attribute'=>'g:brand',        'magento_attribute'=>'manufacturer'],
            ['google_attribute'=>'g:google_product_category','magento_attribute'=>'google_product_category'],
        ]];
    }

    private function buildMeta(): array
    {
        return ['name'=>'Meta Facebook Catalog','format'=>'csv','mapping'=>[
            ['google_attribute'=>'id',          'magento_attribute'=>'sku'],
            ['google_attribute'=>'title',       'magento_attribute'=>'name'],
            ['google_attribute'=>'description', 'magento_attribute'=>'description'],
            ['google_attribute'=>'availability','magento_attribute'=>'quantity_and_stock_status'],
            ['google_attribute'=>'condition',   'source_type'=>'static','static_value'=>'new'],
            ['google_attribute'=>'price',       'magento_attribute'=>'price'],
            ['google_attribute'=>'link',        'magento_attribute'=>'product_url'],
            ['google_attribute'=>'image_link',  'magento_attribute'=>'image_url'],
            ['google_attribute'=>'brand',       'magento_attribute'=>'manufacturer'],
        ]];
    }

    private function buildInstagram(): array
    {
        $m = $this->buildMeta(); $m['name'] = 'Instagram Shopping Catalog'; return $m;
    }

    private function buildSnapchat(): array
    {
        return ['name'=>'Snapchat Commerce','format'=>'csv','mapping'=>[
            ['google_attribute'=>'id',          'magento_attribute'=>'sku'],
            ['google_attribute'=>'title',       'magento_attribute'=>'name'],
            ['google_attribute'=>'description', 'magento_attribute'=>'description'],
            ['google_attribute'=>'availability','magento_attribute'=>'quantity_and_stock_status'],
            ['google_attribute'=>'price',       'magento_attribute'=>'price'],
            ['google_attribute'=>'link',        'magento_attribute'=>'product_url'],
            ['google_attribute'=>'image_link',  'magento_attribute'=>'image_url'],
            ['google_attribute'=>'brand',       'magento_attribute'=>'manufacturer'],
        ]];
    }

    private function buildTikTok(): array
    {
        return ['name'=>'TikTok Commerce','format'=>'csv','mapping'=>[
            ['google_attribute'=>'sku_id',             'magento_attribute'=>'sku'],
            ['google_attribute'=>'title',              'magento_attribute'=>'name'],
            ['google_attribute'=>'description',        'magento_attribute'=>'description'],
            ['google_attribute'=>'available_for_sale', 'magento_attribute'=>'quantity_and_stock_status'],
            ['google_attribute'=>'price',              'magento_attribute'=>'price'],
            ['google_attribute'=>'url',                'magento_attribute'=>'product_url'],
            ['google_attribute'=>'image_url',          'magento_attribute'=>'image_url'],
            ['google_attribute'=>'brand',              'magento_attribute'=>'manufacturer'],
        ]];
    }

    private function buildPinterest(): array
    {
        return ['name'=>'Pinterest Catalog','format'=>'csv','mapping'=>[
            ['google_attribute'=>'id',          'magento_attribute'=>'sku'],
            ['google_attribute'=>'title',       'magento_attribute'=>'name'],
            ['google_attribute'=>'description', 'magento_attribute'=>'description'],
            ['google_attribute'=>'availability','magento_attribute'=>'quantity_and_stock_status'],
            ['google_attribute'=>'condition',   'source_type'=>'static','static_value'=>'new'],
            ['google_attribute'=>'price',       'magento_attribute'=>'price'],
            ['google_attribute'=>'link',        'magento_attribute'=>'product_url'],
            ['google_attribute'=>'image_link',  'magento_attribute'=>'image_url'],
            ['google_attribute'=>'google_product_category','magento_attribute'=>'google_product_category'],
        ]];
    }

    private function buildMicrosoft(): array
    {
        $m = $this->buildGoogle(); $m['name'] = 'Microsoft Bing Merchant'; return $m;
    }

    private function buildAmazon(): array
    {
        return ['name'=>'Amazon Seller Catalog','format'=>'csv','mapping'=>[
            ['google_attribute'=>'item_sku',        'magento_attribute'=>'sku'],
            ['google_attribute'=>'item_name',       'magento_attribute'=>'name'],
            ['google_attribute'=>'item_description','magento_attribute'=>'description'],
            ['google_attribute'=>'price',           'magento_attribute'=>'price'],
            ['google_attribute'=>'quantity',        'magento_attribute'=>'quantity'],
            ['google_attribute'=>'external_product_url','magento_attribute'=>'product_url'],
            ['google_attribute'=>'main_image_url',  'magento_attribute'=>'image_url'],
            ['google_attribute'=>'brand_name',      'magento_attribute'=>'manufacturer'],
        ]];
    }

    private function buildEbay(): array
    {
        return ['name'=>'eBay Inventory Feed','format'=>'csv','mapping'=>[
            ['google_attribute'=>'CustomLabel', 'magento_attribute'=>'sku'],
            ['google_attribute'=>'Title',       'magento_attribute'=>'name'],
            ['google_attribute'=>'Description', 'magento_attribute'=>'description'],
            ['google_attribute'=>'StartPrice',  'magento_attribute'=>'price'],
            ['google_attribute'=>'Quantity',    'magento_attribute'=>'quantity'],
            ['google_attribute'=>'PictureURL',  'magento_attribute'=>'image_url'],
        ]];
    }

    private function buildRakuten(): array
    {
        return ['name'=>'Rakuten Advertising','format'=>'csv','mapping'=>[
            ['google_attribute'=>'sku',         'magento_attribute'=>'sku'],
            ['google_attribute'=>'productname', 'magento_attribute'=>'name'],
            ['google_attribute'=>'description', 'magento_attribute'=>'description'],
            ['google_attribute'=>'saleprice',   'magento_attribute'=>'price'],
            ['google_attribute'=>'producturl',  'magento_attribute'=>'product_url'],
            ['google_attribute'=>'imageurl',    'magento_attribute'=>'image_url'],
            ['google_attribute'=>'brand',       'magento_attribute'=>'manufacturer'],
            ['google_attribute'=>'instock',     'magento_attribute'=>'quantity_and_stock_status'],
        ]];
    }

    private function buildOpenAi(): array
    {
        return ['name'=>'OpenAI Agentic Commerce','format'=>'jsonl','mapping'=>[
            ['google_attribute'=>'id',               'magento_attribute'=>'sku'],
            ['google_attribute'=>'name',             'magento_attribute'=>'name'],
            ['google_attribute'=>'description',      'magento_attribute'=>'description'],
            ['google_attribute'=>'short_description','magento_attribute'=>'short_description'],
            ['google_attribute'=>'price',            'magento_attribute'=>'price'],
            ['google_attribute'=>'currency',         'magento_attribute'=>'currency'],
            ['google_attribute'=>'availability',     'magento_attribute'=>'quantity_and_stock_status'],
            ['google_attribute'=>'url',              'magento_attribute'=>'product_url'],
            ['google_attribute'=>'image_url',        'magento_attribute'=>'image_url'],
            ['google_attribute'=>'brand',            'magento_attribute'=>'manufacturer'],
            ['google_attribute'=>'category',         'magento_attribute'=>'google_product_category'],
            ['google_attribute'=>'condition',        'source_type'=>'static','static_value'=>'new'],
        ]];
    }
}
