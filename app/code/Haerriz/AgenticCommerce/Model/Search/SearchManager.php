<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Search;

use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Inventory\InventoryService;
use Psr\Log\LoggerInterface;

/**
 * DI-registry-backed search orchestration. Any enterprise module can register a search adapter
 * (Adobe Live Search, ElasticSuite, Algolia, custom vector/semantic service) without forking core.
 * Native Magento remains the correctness fallback.
 */
class SearchManager implements SearchAdapterInterface
{
    public function __construct(
        private Config $config,
        private SearchAdapterRegistry $registry,
        private InventoryService $inventory,
        private LoggerInterface $logger
    ) {}

    public function search(array $arguments): array
    {
        $requested=$this->config->getSearchProvider();
        $provider=$this->registry->get($requested)??$this->registry->get('native');
        if(!$provider instanceof SearchAdapterInterface)throw new \RuntimeException('No Agentic Commerce search adapter is registered.');

        // Inventory-only filters stay on native Magento unless an extension explicitly owns native semantics.
        if($requested!=='native'&&$this->containsInventoryFilter((array)($arguments['filters']??[]))){
            $native=$this->registry->get('native');
            if($native instanceof SearchAdapterInterface){$result=$native->search($arguments);$result['search_provider']='native_inventory_filter';return $result;}
        }
        try{return $this->enrichInventory($provider->search($arguments));}
        catch(\Throwable $e){
            if($requested==='native')throw $e;
            $this->logger->warning('Configured Agentic Commerce search adapter failed; falling back to native Magento search.',['provider'=>$requested,'exception'=>$e]);
            $native=$this->registry->get('native');if(!$native instanceof SearchAdapterInterface)throw $e;
            $result=$native->search($arguments);$result['search_provider']='native_fallback';return $result;
        }
    }

    private function containsInventoryFilter(array $filters):bool{foreach($filters as $filter)if(is_array($filter)&&(string)($filter['attribute']??'')==='stock_status')return true;return false;}
    private function enrichInventory(array $result):array
    {
        if(!$this->config->isInventoryOnCardsEnabled())return$result;$products=is_array($result['products']??null)?$result['products']:[];
        foreach($products as $index=>$product){if(!is_array($product)||!empty($product['inventory'])||empty($product['sku']))continue;try{$availability=$this->inventory->get((string)$product['sku']);$products[$index]['inventory']=$availability;$products[$index]['is_salable']=(bool)$availability['is_salable'];}catch(\Throwable $e){$this->logger->debug('Could not enrich agentic search result with inventory.',['sku'=>(string)$product['sku'],'exception'=>$e]);}}
        $result['products']=$products;return$result;
    }
}
