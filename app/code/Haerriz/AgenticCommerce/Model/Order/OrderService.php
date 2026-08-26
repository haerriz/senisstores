<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Order;

use Haerriz\AgenticCommerce\Model\Config;

use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class OrderService
{
    public function __construct(
        private CollectionFactory $orderCollectionFactory,
        private PriceCurrencyInterface $priceCurrency,
        private Config $config
    ) {
    }

    public function recent(array $identity, int $limit = 5): array
    {
        $this->assertEnabled((int)($identity['store_id'] ?? 0));
        $customerId = $this->customerId($identity);
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('store_id', (int)$identity['store_id'])
            ->setOrder('created_at', 'DESC')
            ->setPageSize(max(1, min($this->config->getMaxRecentOrders((int)$identity['store_id']), $limit)));
        $orders = [];
        foreach ($collection as $order) {
            $orders[] = $this->present($order, false);
        }
        return ['orders' => $orders, 'assistant_message' => $orders ? (string)__('Here are your most recent orders.') : (string)__('You do not have any orders in this store view yet.')];
    }

    public function byNumber(array $identity, string $incrementId): array
    {
        $this->assertEnabled((int)($identity['store_id'] ?? 0));
        $customerId = $this->customerId($identity);
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('store_id', (int)$identity['store_id'])
            ->addFieldToFilter('increment_id', trim($incrementId))
            ->setPageSize(1);
        $order = $collection->getFirstItem();
        if (!$order->getId()) {
            throw new LocalizedException(__('I could not find that order in your account.'));
        }
        $presented = $this->present($order, true);
        return ['orders' => [$presented], 'assistant_message' => (string)__('Order %1 is currently %2.', $presented['number'], $presented['status_label'])];
    }

    private function present($order, bool $withItems): array
    {
        $items = [];
        if ($withItems) {
            foreach ($order->getAllVisibleItems() as $item) {
                $items[] = ['sku' => (string)$item->getSku(), 'name' => (string)$item->getName(), 'qty' => (float)$item->getQtyOrdered()];
            }
        }
        $tracking = [];
        foreach ($order->getShipmentsCollection() as $shipment) {
            foreach ($shipment->getAllTracks() as $track) {
                $tracking[] = ['carrier' => (string)$track->getTitle(), 'number' => (string)$track->getTrackNumber()];
            }
        }
        return [
            'number' => (string)$order->getIncrementId(),
            'status' => (string)$order->getStatus(),
            'status_label' => (string)$order->getStatusLabel(),
            'created_at' => (string)$order->getCreatedAt(),
            'grand_total' => (float)$order->getGrandTotal(),
            'formatted_grand_total' => $this->priceCurrency->format((float)$order->getGrandTotal(), false),
            'items' => $items,
            'tracking' => $tracking,
        ];
    }

    private function assertEnabled(int $storeId): void
    {
        if (!$this->config->isFeatureEnabled('orders', $storeId)) {
            throw new LocalizedException(__('Order assistant capabilities are disabled.'));
        }
    }

    private function customerId(array $identity): int
    {
        $id = (int)($identity['customer_id'] ?? 0);
        if ($id <= 0) {
            throw new AuthorizationException(__('Please sign in to view order information.'));
        }
        return $id;
    }
}
