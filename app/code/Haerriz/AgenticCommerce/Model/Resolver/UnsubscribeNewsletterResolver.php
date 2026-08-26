<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\Customer\NewsletterService;
use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\GraphQl\IdempotentExecutor;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class UnsubscribeNewsletterResolver implements ResolverInterface
{
    public function __construct(private NewsletterService $newsletter, private CustomerContext $customerContext, private IdempotentExecutor $idempotent) {}

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $input = (array)($args['input'] ?? []);
        $identity = $this->customerContext->identityForTool($context, null, 'unsubscribe_newsletter');
        return $this->idempotent->execute('unsubscribe_newsletter', $input, $identity, fn(): array => $this->newsletter->unsubscribe($identity));
    }
}
