<?php

declare(strict_types=1);

namespace Haerriz\Seo\Block;

use Haerriz\Seo\Model\BreadcrumbTrailBuilder;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

class BreadcrumbSchema extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly BreadcrumbTrailBuilder $trailBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSchema(): array
    {
        $product = $this->registry->registry('current_product');
        $category = $this->registry->registry('current_category');

        if ($product instanceof Product) {
            $trail = $this->trailBuilder->forProduct($product);
        } elseif ($category instanceof Category) {
            $trail = $this->trailBuilder->forCategory($category);
        } else {
            return [];
        }

        if (count($trail) < 2) {
            return [];
        }

        $items = [];
        foreach ($trail as $position => $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $crumb['name'],
            ];
            if (!$crumb['is_current']) {
                $item['item'] = $crumb['url'];
            }
            $items[] = $item;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}
