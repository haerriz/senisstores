<?php
declare(strict_types=1);
namespace Haerriz\Seo\Block;

use Magento\Catalog\Model\Category;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

class CategorySchema extends Template
{
    public function __construct(Template\Context $context, private readonly Registry $registry, array $data = []) { parent::__construct($context, $data); }
    public function getSchema(): array
    {
        $category = $this->registry->registry('current_category');
        if (!$category instanceof Category) { return []; }
        $items=[]; $position=1;
        foreach ($category->getProductCollection()->addAttributeToSelect(['name','url_key'])->addAttributeToFilter('status',1)->setPageSize(20) as $product) {
            $items[]=['@type'=>'ListItem','position'=>$position++,'url'=>$product->getProductUrl(),'name'=>(string)$product->getName()];
        }
        if (!$items) { return []; }
        return ['@context'=>'https://schema.org','@type'=>'ItemList','name'=>(string)$category->getName(),'url'=>(string)$category->getUrl(),'numberOfItems'=>count($items),'itemListElement'=>$items];
    }
}
