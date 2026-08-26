<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use Magento\Store\Model\ScopeInterface;

class ReviewService
{
    private const XML_PATH_ALLOW_GUEST = 'catalog/review/allow_guest';

    public function __construct(
        private ProductRepositoryInterface $products,
        private CollectionFactory $collections,
        private ReviewFactory $reviews,
        private ScopeConfigInterface $scopeConfig
    ) {}

    public function list(string $sku, int $storeId, int $limit = 5): array
    {
        $product = $this->products->get($sku, false, $storeId, true);
        $collection = $this->collections->create();
        $collection->addStoreFilter($storeId)
            ->addEntityFilter('product', (int)$product->getId())
            ->addStatusFilter(Review::STATUS_APPROVED)
            ->setDateOrder()
            ->setPageSize(max(1, min(20, $limit)))
            ->setCurPage(1);
        $items = [];
        foreach ($collection as $review) {
            $items[] = [
                'id' => (int)$review->getReviewId(),
                'title' => (string)$review->getTitle(),
                'detail' => (string)$review->getDetail(),
                'nickname' => (string)$review->getNickname(),
                'created_at' => (string)$review->getCreatedAt(),
            ];
        }
        return ['sku' => $sku, 'total_count' => (int)$collection->getSize(), 'items' => $items];
    }

    public function submit(array $identity, string $sku, string $title, string $detail, string $nickname = ''): array
    {
        $storeId = (int)$identity['store_id'];
        $customerId = (int)($identity['customer_id'] ?? 0);
        if ($customerId <= 0 && !$this->scopeConfig->isSetFlag(self::XML_PATH_ALLOW_GUEST, ScopeInterface::SCOPE_STORE, $storeId)) {
            throw new AuthorizationException(__('Please sign in to submit a product review.'));
        }
        if (trim($title) === '' || trim($detail) === '') {
            throw new LocalizedException(__('Review title and review details are required.'));
        }

        $product = $this->products->get($sku, false, $storeId, true);
        $review = $this->reviews->create();
        $nickname = trim($nickname);
        if ($nickname === '') {
            $nickname = $customerId > 0 ? 'Customer' : 'Guest';
        }
        $review->setEntityPkValue((int)$product->getId())
            ->setStatusId(Review::STATUS_PENDING)
            ->setTitle(mb_substr(trim($title), 0, 255))
            ->setDetail(mb_substr(trim($detail), 0, 5000))
            ->setEntityId($review->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE))
            ->setStoreId($storeId)
            ->setStores([$storeId])
            ->setCustomerId($customerId ?: null)
            ->setNickname(mb_substr($nickname, 0, 64));
        $valid = $review->validate();
        if ($valid !== true) {
            throw new LocalizedException(__(implode(' ', (array)$valid)));
        }
        $review->save();
        $review->aggregate();
        return [
            'submitted' => true,
            'status' => 'pending',
            'assistant_message' => (string)__('Your review for %1 was submitted for moderation.', $product->getName()),
        ];
    }
}
