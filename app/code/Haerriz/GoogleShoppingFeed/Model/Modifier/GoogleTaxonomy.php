<?php
namespace Haerriz\GoogleShoppingFeed\Model\Modifier;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\CategoryRepositoryInterface;

class GoogleTaxonomy implements ModifierInterface
{
    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @param CategoryRepositoryInterface $categoryRepository
     */
    public function __construct(CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * @inheritdoc
     */
    public function modify($value, Product $product, $argument = null)
    {
        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return '';
        }

        // Get the deepest category
        $categoryId = end($categoryIds);
        try {
            $category = $this->categoryRepository->get($categoryId, $product->getStoreId());
            $googleCategory = $category->getData('google_product_category');
            if ($googleCategory) {
                return $googleCategory;
            }
        } catch (\Exception $e) {
            // Category not found
        }
        return '';
    }
}
