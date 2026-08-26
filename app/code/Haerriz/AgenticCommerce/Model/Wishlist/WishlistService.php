<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Wishlist;

use Haerriz\AgenticCommerce\Model\ProductPresenter;
use Haerriz\AgenticCommerce\Model\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Wishlist\Model\WishlistFactory;

class WishlistService
{
    public function __construct(
        private WishlistFactory $wishlistFactory,
        private ProductRepositoryInterface $productRepository,
        private ProductPresenter $presenter,
        private Config $config
    ) {
    }

    public function get(array $identity): array
    {
        $this->assertEnabled((int)($identity['store_id'] ?? 0));
        $wishlist = $this->wishlist($identity);
        $items = [];
        foreach ($wishlist->getItemCollection() as $item) {
            $product = $item->getProduct();
            if (!$product || !$product->getId()) {
                continue;
            }
            $presented = $this->presenter->present($product);
            $items[] = [
                'item_id' => (int)$item->getId(),
                'sku' => (string)$presented['sku'],
                'name' => (string)$presented['name'],
                'url' => (string)$presented['url'],
                'image' => $presented['image'] ?? null,
                'price' => (float)$presented['price'],
                'formatted_price' => (string)$presented['formatted_price'],
            ];
        }
        return ['items_count' => count($items), 'items' => $items];
    }

    public function add(array $identity, string $sku): array
    {
        $this->assertEnabled((int)($identity['store_id'] ?? 0));
        $wishlist = $this->wishlist($identity);
        $product = $this->productRepository->get($sku, false, (int)$identity['store_id'], true);
        $result = $wishlist->addNewItem($product);
        if (is_string($result)) {
            throw new LocalizedException(__($result));
        }
        $wishlist->save();
        return [
            'added' => true,
            'assistant_message' => (string)__('%1 was saved to your wishlist.', $product->getName()),
            'wishlist' => $this->get($identity),
        ];
    }

    public function removeByPosition(array $identity, int $index): array
    {
        $this->assertEnabled((int)($identity['store_id'] ?? 0));
        $wishlist = $this->wishlist($identity);
        $items = array_values(iterator_to_array($wishlist->getItemCollection()));
        $position = $index === 0 ? count($items) : $index;
        if ($position < 1 || !isset($items[$position - 1])) {
            throw new LocalizedException(__('I could not find that item in your wishlist.'));
        }
        $item = $items[$position - 1];
        $name = (string)$item->getProduct()->getName();
        $item->delete();
        $wishlist->save();
        return [
            'removed' => true,
            'assistant_message' => (string)__('%1 was removed from your wishlist.', $name),
            'wishlist' => $this->get($identity),
        ];
    }

    private function assertEnabled(int $storeId): void
    {
        if (!$this->config->isFeatureEnabled('wishlist', $storeId)) {
            throw new LocalizedException(__('Wishlist assistant capabilities are disabled.'));
        }
    }

    private function wishlist(array $identity)
    {
        $customerId = (int)($identity['customer_id'] ?? 0);
        if ($customerId <= 0) {
            throw new AuthorizationException(__('Please sign in to use your wishlist.'));
        }
        $wishlist = $this->wishlistFactory->create()->loadByCustomerId($customerId, true);
        if (!$wishlist->getId()) {
            throw new LocalizedException(__('The wishlist could not be loaded.'));
        }
        return $wishlist;
    }
}
