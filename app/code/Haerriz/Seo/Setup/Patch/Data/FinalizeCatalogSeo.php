<?php
declare(strict_types=1);

namespace Haerriz\Seo\Setup\Patch\Data;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class FinalizeCatalogSeo implements DataPatchInterface
{
    public function __construct(
        private readonly CategoryFactory $categoryFactory,
        private readonly CollectionFactory $productCollectionFactory
    ) {}

    public function apply(): self
    {
        foreach (range(3, 35) as $id) {
            $category = $this->categoryFactory->create()->setStoreId(0)->load($id);
            if ($category->getId() && $category->getProductCollection()->addAttributeToFilter('status', 1)->getSize() === 0) {
                $category->setIncludeInMenu(false)->getResource()->saveAttribute($category, 'include_in_menu');
            }
        }

        $product = $this->productCollectionFactory->create()
            ->addAttributeToSelect(['sku','name'])
            ->addAttributeToFilter('sku', 'Garden scissors')
            ->getFirstItem();
        if ($product->getId()) {
            $description = 'General-purpose garden scissors with a PVC handle for trimming and routine plant care. Clean and dry the blades after use, store safely and keep the cutting edge away from children.';
            $product->setMetaTitle('Garden Scissors for Pruning | Seni\'s Stores')
                ->setMetaDescription('Buy garden scissors with a PVC handle for routine trimming and plant care. Order online across India from Seni\'s Stores, Theni.')
                ->setShortDescription($description)
                ->setDescription('<p>' . $description . '</p><p>Order online for eligible delivery across India or contact the Theni store for availability.</p>');
            foreach (['meta_title','meta_description','short_description','description'] as $attribute) {
                $product->getResource()->saveAttribute($product, $attribute);
            }
        }
        return $this;
    }

    public static function getDependencies(): array { return [BuildContentArchitecture::class]; }
    public function getAliases(): array { return []; }
}
