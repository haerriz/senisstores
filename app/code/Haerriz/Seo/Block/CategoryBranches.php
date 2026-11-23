<?php

declare(strict_types=1);

namespace Haerriz\Seo\Block;

use Magento\Catalog\Model\Category;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

class CategoryBranches extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return Category[] */
    public function getBranches(): array
    {
        $category = $this->registry->registry('current_category');
        if (!$category instanceof Category) {
            return [];
        }

        $branches = [];
        foreach ($category->getChildrenCategories() as $child) {
            if ($child->getIsActive()
                && $child->getProductCollection()->addAttributeToFilter('status', 1)->getSize() > 0
            ) {
                $branches[] = $child;
            }
        }
        return $branches;
    }
}
