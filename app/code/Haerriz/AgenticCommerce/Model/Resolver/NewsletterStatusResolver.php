<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Resolver;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Haerriz\AgenticCommerce\Model\Customer\NewsletterService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
class NewsletterStatusResolver implements ResolverInterface { public function __construct(private NewsletterService $newsletter, private CustomerContext $customerContext) {} public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null) { return $this->newsletter->status($this->customerContext->identityForTool($context, null, 'get_newsletter_status')); } }
