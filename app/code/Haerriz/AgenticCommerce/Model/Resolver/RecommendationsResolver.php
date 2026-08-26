<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\Recommendation\RecommendationService; use Magento\Store\Model\StoreManagerInterface; use Magento\Framework\GraphQl\Config\Element\Field; use Magento\Framework\GraphQl\Exception\GraphQlInputException; use Magento\Framework\GraphQl\Query\ResolverInterface; use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
class RecommendationsResolver implements ResolverInterface
{
    public function __construct(private RecommendationService $service, private StoreManagerInterface $storeManager, private CustomerContext $customerContext){}
    public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null):array {
        $this->customerContext->identityForTool($context, null, 'get_recommendations');
        $sku=trim((string)($args['sku']??'')); if($sku==='') throw new GraphQlInputException(__('SKU is required.'));
        try { $items=$this->service->forSku($sku,(string)($args['type']??'related'),(int)($args['limit']??6),(int)$this->storeManager->getStore()->getId()); return ['total_count'=>count($items),'items'=>$items]; }
        catch(\Magento\Framework\Exception\LocalizedException $e){ throw new GraphQlInputException(__($e->getMessage())); }
    }
}
