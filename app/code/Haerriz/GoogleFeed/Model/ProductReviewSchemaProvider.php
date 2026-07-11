<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\GoogleFeed\Model;

use Magento\Catalog\Model\Product;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewSummaryFactory;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class ProductReviewSchemaProvider
{
    /**
     * @var ReviewSummaryFactory
     */
    private $reviewSummaryFactory;

    /**
     * @var ReviewCollectionFactory
     */
    private $reviewCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param ReviewSummaryFactory $reviewSummaryFactory
     * @param ReviewCollectionFactory $reviewCollectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ReviewSummaryFactory $reviewSummaryFactory,
        ReviewCollectionFactory $reviewCollectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->reviewSummaryFactory = $reviewSummaryFactory;
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @param Product $product
     * @return array<string, mixed>
     */
    public function getSchema(Product $product)
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $this->reviewSummaryFactory->create()->appendSummaryDataToObject($product, $storeId);

        
        
        $ratingPercent = (float) $product->getRatingSummary();
        $reviewCount = (int) $product->getReviewsCount();


        if ($reviewCount === 0) {
            return [];
        }

        
        $ratingValue = round($ratingPercent / 20, 1);

        if ($ratingValue <= 0) {
            return [];
        }

        $schema = [
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $ratingValue,
                'bestRating' => '5',
                'worstRating' => '1',
                'reviewCount' => (string) $reviewCount,
            ],
        ];

        $reviews = $this->getReviewItems($product, $storeId);
        if ($reviews !== []) {
            $schema['review'] = $reviews;
        }

        return $schema;
    }

    /**
     * @param Product $product
     * @param int $storeId
     * @return array<int, array<string, mixed>>
     */
    private function getReviewItems(Product $product, $storeId)
    {
        $collection = $this->reviewCollectionFactory->create();
        $collection->addStoreFilter($storeId)
            ->addStatusFilter(Review::STATUS_APPROVED)
            ->addEntityFilter('product', (int) $product->getId())
            ->addRateVotes()
            ->setDateOrder()
            ->setPageSize(5);

        $items = [];
        foreach ($collection as $review) {
            $rating = $this->resolveReviewRating($review);
            if ($rating === null) {
                continue;
            }

            $items[] = [
                '@type' => 'Review',
                'author' => [
                    '@type' => 'Person',
                    'name' => (string) $review->getNickname(),
                ],
                'datePublished' => substr((string) $review->getCreatedAt(), 0, 10),
                'reviewBody' => trim(strip_tags((string) $review->getDetail())),
                'name' => (string) $review->getTitle(),
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => (string) $rating,
                    'bestRating' => '5',
                    'worstRating' => '1',
                ],
            ];
        }

        return $items;
    }

    /**
     * @param Review $review
     * @return float|null
     */
    private function resolveReviewRating(Review $review)
    {
        foreach ($review->getRatingVotes() as $vote) {
            $percent = (float) $vote->getPercent();
            if ($percent > 0) {
                return round($percent / 20, 1);
            }
        }

        return null;
    }
}
