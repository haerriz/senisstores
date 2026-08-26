<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\GraphQl\IdempotentExecutor;
use Haerriz\AgenticCommerce\Model\Product\ReviewService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class SubmitProductReviewResolver implements ResolverInterface
{
    public function __construct(private ReviewService $reviews, private CustomerContext $customerContext, private IdempotentExecutor $idempotent) {}

    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null): array
    {
        $input = (array)($args['input'] ?? []);
        $identity = $this->customerContext->identityForTool($context, $input['client_id'] ?? null, 'submit_product_review');
        return $this->idempotent->execute('submit_product_review', $input, $identity, fn(): array =>
            $this->reviews->submit($identity, (string)($input['sku'] ?? ''), (string)($input['title'] ?? ''), (string)($input['detail'] ?? ''), (string)($input['nickname'] ?? ''))
        );
    }
}
