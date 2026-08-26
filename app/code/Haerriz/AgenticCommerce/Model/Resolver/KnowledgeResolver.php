<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\Knowledge\KnowledgeService;
use Magento\Framework\GraphQl\Config\Element\Field; use Magento\Framework\GraphQl\Query\ResolverInterface; use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
class KnowledgeResolver implements ResolverInterface
{
    public function __construct(private KnowledgeService $service, private CustomerContext $customerContext){}
    public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null):array { $this->customerContext->identityForTool($context, null, 'answer_store_question'); return $this->service->search((string)($args['query']??''),(int)($args['limit']??3)); }
}
