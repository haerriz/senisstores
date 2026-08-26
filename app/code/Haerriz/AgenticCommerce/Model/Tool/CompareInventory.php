<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Tool;

use Haerriz\AgenticCommerce\Model\Inventory\InventoryService;
use Magento\Framework\Exception\LocalizedException;

class CompareInventory implements ToolInterface
{
    public function __construct(private InventoryService $inventory) {}
    public function getName(): string { return 'compare_inventory'; }
    public function getDefinition(): array
    {
        return ['type'=>'function','function'=>[
            'name'=>$this->getName(),
            'description'=>'Compare availability for multiple products from the server-owned recent result set. Useful for “which are in stock?”, “compare stock of the first two”, or “which has more remaining?”.',
            'parameters'=>['type'=>'object','properties'=>[
                'indexes'=>['type'=>'array','items'=>['type'=>'integer','minimum'=>1,'maximum'=>24],'minItems'=>2,'maxItems'=>12],
                'requested_qty'=>['type'=>'number','minimum'=>0.0001,'maximum'=>10000],
            ],'required'=>['indexes']],
        ]];
    }
    public function execute(array $arguments, array $context = []): array
    {
        $recent=(array)($context['recent_products']??[]);
        $indexes=array_values(array_unique(array_map('intval',(array)($arguments['indexes']??[]))));
        if(count($indexes)<2) throw new LocalizedException(__('Choose at least two previously shown products to compare stock.'));
        $skus=[]; $labels=[];
        foreach(array_slice($indexes,0,12) as $index){
            if($index<1 || empty($recent[$index-1]['sku'])) continue;
            $skus[]=(string)$recent[$index-1]['sku'];
            $labels[(string)$recent[$index-1]['sku']]=(string)($recent[$index-1]['name']??$recent[$index-1]['sku']);
        }
        if(count($skus)<2) throw new LocalizedException(__('Those product references are no longer available in this conversation.'));
        $items=$this->inventory->getMany($skus,(int)($context['identity']['store_id']??0),max(0.0001,(float)($arguments['requested_qty']??1)),12);
        $parts=[];
        foreach($items as &$item){
            $item['name']=$labels[(string)$item['sku']]??(string)$item['sku'];
            $parts[]=$item['name'].': '.(string)$item['message'];
        }
        unset($item);
        return ['inventories'=>$items,'assistant_message'=>implode(' ', $parts)];
    }
}
