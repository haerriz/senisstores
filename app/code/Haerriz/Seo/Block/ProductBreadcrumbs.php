<?php

declare(strict_types=1);

namespace Haerriz\Seo\Block;

use Haerriz\Seo\Model\BreadcrumbTrailBuilder;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

class ProductBreadcrumbs extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly BreadcrumbTrailBuilder $trailBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<int, array{name:string,url:string,is_current:bool}> */
    public function getTrail(): array
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product ? $this->trailBuilder->forProduct($product) : [];
    }
}
