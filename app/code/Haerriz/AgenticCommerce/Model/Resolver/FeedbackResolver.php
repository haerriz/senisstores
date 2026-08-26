<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Model\Resolver;

use Haerriz\AgenticCommerce\Model\GraphQl\CustomerContext;
use Haerriz\AgenticCommerce\Model\Learning\FeedbackService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Exception\LocalizedException;

class FeedbackResolver implements ResolverInterface
{
    public function __construct(private FeedbackService $feedback,private CustomerContext $customerContext) {}
    public function resolve(Field $field,$context,ResolveInfo $info,array $value=null,array $args=null)
    {
        $input=(array)($args['input']??[]);
        try {
            return $this->feedback->submit((string)($input['conversation_id']??''),(string)($input['message']??''),(string)($input['tool_name']??''),(int)($input['rating']??0),$this->customerContext->identity($context,isset($input['client_id'])?(string)$input['client_id']:null),isset($input['comment'])?(string)$input['comment']:null);
        } catch (LocalizedException $e) { throw new GraphQlInputException(__($e->getMessage())); }
    }
}
