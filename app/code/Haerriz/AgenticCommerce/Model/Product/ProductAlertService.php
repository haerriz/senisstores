<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\ProductAlert\Model\PriceFactory;
use Magento\ProductAlert\Model\StockFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProductAlertService
{
    private const XML_PATH_ALLOW_PRICE = 'catalog/productalert/allow_price';
    private const XML_PATH_ALLOW_STOCK = 'catalog/productalert/allow_stock';

    public function __construct(
        private ProductRepositoryInterface $products,
        private PriceFactory $priceAlerts,
        private StockFactory $stockAlerts,
        private StoreManagerInterface $stores,
        private ScopeConfigInterface $scopeConfig
    ) {}

    public function subscribe(array $identity, string $sku, string $type): array
    {
        $customerId = (int)($identity['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new AuthorizationException(__('Please sign in to create product alerts.'));
        }

        $store = $this->stores->getStore((int)$identity['store_id']);
        $type = mb_strtolower(trim($type));
        $configPath = match ($type) {
            'price' => self::XML_PATH_ALLOW_PRICE,
            'stock' => self::XML_PATH_ALLOW_STOCK,
            default => '',
        };
        if ($configPath === '') {
            throw new LocalizedException(__('Unknown product alert type.'));
        }
        if (!$this->scopeConfig->isSetFlag($configPath, ScopeInterface::SCOPE_STORE, (int)$store->getId())) {
            throw new LocalizedException(__('%1 alerts are disabled for this store view.', ucfirst($type)));
        }

        $product = $this->products->get($sku, false, (int)$store->getId(), true);
        $model = $type === 'price' ? $this->priceAlerts->create() : $this->stockAlerts->create();
        $model->setCustomerId($customerId)
            ->setProductId((int)$product->getId())
            ->setWebsiteId((int)$store->getWebsiteId())
            ->save();

        return [
            'subscribed' => true,
            'type' => $type,
            'sku' => $sku,
            'assistant_message' => (string)__('Your %1 alert for %2 is active.', $type, $product->getName()),
        ];
    }
}
