<?php

declare(strict_types=1);
namespace Haerriz\AgenticCommerce\Model\Product;

use Haerriz\AgenticCommerce\Api\ProductIntelligenceInterface;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/** REST facade over the same product-intelligence services used by GraphQL and chat. */
class ProductIntelligenceApi implements ProductIntelligenceInterface
{
    public function __construct(
        private ProductContentService $content,
        private ProductComparisonService $comparison,
        private ProductQuestionService $questions,
        private StoreManagerInterface $stores,
        private UserContextInterface $userContext,
        private CustomerRepositoryInterface $customers
    ) {}

    public function get(string $sku): array
    {
        return $this->content->get($sku,(int)$this->stores->getStore()->getId(),$this->customerGroupId());
    }

    public function compare(array $skus, ?string $focus = null, ?string $goal = null): array
    {
        $focusValues=$focus!==null?array_values(array_filter(array_map('trim',explode(',',$focus)))):[];
        return $this->comparison->compare($skus,(int)$this->stores->getStore()->getId(),$focusValues,$this->customerGroupId(),mb_substr(trim((string)$goal),0,500));
    }

    public function question(string $sku, string $question): array
    {
        return $this->questions->answer($sku,$question,(int)$this->stores->getStore()->getId(),$this->customerGroupId());
    }

    private function customerGroupId(): ?int
    {
        try {
            if ($this->userContext->getUserType() !== UserContextInterface::USER_TYPE_CUSTOMER) return null;
            $id=(int)$this->userContext->getUserId();
            return $id>0?(int)$this->customers->getById($id)->getGroupId():null;
        } catch (\Throwable) {
            return null;
        }
    }
}
