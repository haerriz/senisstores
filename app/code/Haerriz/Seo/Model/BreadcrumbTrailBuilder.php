<?php

declare(strict_types=1);

namespace Haerriz\Seo\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;

class BreadcrumbTrailBuilder
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return array<int, array{name:string,url:string,is_current:bool}>
     */
    public function forCategory(Category $category): array
    {
        return $this->build($category, null);
    }

    /**
     * @return array<int, array{name:string,url:string,is_current:bool}>
     */
    public function forProduct(Product $product): array
    {
        return $this->build($this->selectPrimaryCategory($product), $product);
    }

    private function selectPrimaryCategory(Product $product): ?Category
    {
        $collection = $product->getCategoryCollection()
            ->addAttributeToSelect(['name', 'url_key', 'url_path', 'is_active', 'include_in_menu'])
            ->addAttributeToFilter('is_active', 1)
            ->setOrder('level', 'DESC')
            ->setOrder('position', 'ASC');

        foreach ($collection as $category) {
            if ((int) $category->getId() === (int) $this->storeManager->getStore()->getRootCategoryId()) {
                continue;
            }
            return $category;
        }

        return null;
    }

    /**
     * @return array<int, array{name:string,url:string,is_current:bool}>
     */
    private function build(?Category $category, ?Product $product): array
    {
        $store = $this->storeManager->getStore();
        $trail = [[
            'name' => 'Home',
            'url' => $store->getBaseUrl(),
            'is_current' => false,
        ]];

        if ($category) {
            $rootCategoryId = (int) $store->getRootCategoryId();
            foreach (array_map('intval', explode('/', (string) $category->getPath())) as $categoryId) {
                if ($categoryId <= 1 || $categoryId === $rootCategoryId) {
                    continue;
                }
                try {
                    $pathCategory = $this->categoryRepository->get($categoryId, (int) $store->getId());
                } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                    continue;
                }
                if (!$pathCategory->getIsActive()) {
                    continue;
                }
                $trail[] = [
                    'name' => (string) $pathCategory->getName(),
                    'url' => (string) $pathCategory->getUrl(),
                    'is_current' => $product === null && $categoryId === (int) $category->getId(),
                ];
            }
        }

        if ($product) {
            $trail[] = [
                'name' => (string) $product->getName(),
                'url' => (string) $product->getProductUrl(),
                'is_current' => true,
            ];
        }

        return $trail;
    }
}
