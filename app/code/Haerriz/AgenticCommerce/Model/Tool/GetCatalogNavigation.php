<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class GetCatalogNavigation implements ToolInterface
{
    public function __construct(private CollectionFactory $categories, private StoreManagerInterface $stores) {}
    public function getName(): string { return 'get_catalog_navigation'; }
    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'List active top-level storefront catalog categories as safe navigation choices.',
            'parameters'=>['type'=>'object','properties'=>['limit'=>['type'=>'integer','minimum'=>1,'maximum'=>30]]],
        ]];
    }
    public function execute(array $arguments, array $context=[]): array
    {
        $store=$this->stores->getStore((int)($context['identity']['store_id']??0));
        $rootId=(int)$store->getRootCategoryId(); $limit=max(1,min(30,(int)($arguments['limit']??20)));
        $collection=$this->categories->create();
        $collection->setStoreId((int)$store->getId())->addAttributeToSelect(['name','url_key'])->addAttributeToFilter('is_active',1)->addFieldToFilter('parent_id',$rootId)->addAttributeToSort('position','ASC')->setPageSize($limit);
        $actions=[];
        foreach($collection as $category){ $actions[]=['type'=>'navigate','label'=>(string)$category->getName(),'url'=>(string)$category->getUrl(),'auto_navigate'=>false]; }
        return ['actions'=>$actions,'assistant_message'=>$actions?(string)__('These are the main shopping categories.'):(string)__('No active catalog categories are available.')];
    }
}
