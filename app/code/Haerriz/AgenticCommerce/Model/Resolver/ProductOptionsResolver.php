<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Haerriz\AgenticCommerce\Model\Product\ProductOptionService;
class ProductOptionsResolver implements ResolverInterface { public function __construct(private ProductOptionService $options, private CustomerContext $customerContext) {} public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null) { $this->customerContext->identityForTool($context, null, 'get_product_options'); return $this->options->describe((string)($args['sku']??''),(int)$context->getExtensionAttributes()->getStore()->getId()); } }
