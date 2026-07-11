<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Controller\Webhook;

use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Model\Rule;
use WeSupply\Toolbox\Logger\Logger as Logger;
use WeSupply\Toolbox\Model\Webhook;


/**
 * Class CouponCode
 *
 * @package WeSupply\Toolbox\Controller\Webhook
 */
class GenerateInstantCredit extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var Webhook
     */
    private $webhook;

    /**
     * @var Rule
     */
    private $salesRule;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customer;

    /**
     * @var CustomerInterface
     */
    private $customerData;

    /**
     * @var array
     */
    private $couponParams;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Context                     $context
     * @param JsonFactory                 $jsonFactory
     * @param JsonSerializer              $jsonSerializer
     * @param CustomerRepositoryInterface $customer
     * @param Webhook                     $webhook
     * @param Rule                        $salesRule
     * @param Logger                      $logger
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        JsonSerializer $jsonSerializer,
        CustomerRepositoryInterface $customer,
        Webhook $webhook,
        Rule $salesRule,
        Logger $logger
    ) {
        $this->resultJsonFactory = $jsonFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->customer = $customer;
        $this->salesRule = $salesRule;
        $this->webhook = $webhook;

        $this->logger = $logger;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $this->params = $this->getRequest()->getParams();

        if (!$this->requestIsAllowed()) {
            $error = $this->webhook->getError();
            $this->logger->error($error['status-message']);

            return $resultJson->setData($error);
        }

        try {
            $this->loadCustomerData();
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('%s :: %s', get_class($this), $e->getMessage())
            );

            return $resultJson->setData(
                [
                    'success' => false,
                    'status-title' => 'Instant Credit Failed',
                    'status-message' => sprintf('Magento response: %s', $e->getMessage())
                ]
            );
        }

        try {
            $this->prepareCouponParams();
            $this->createSalesRule();
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('%s :: %s', get_class($this), $e->getMessage())
            );

            return $resultJson->setData(
                [
                    'success' => false,
                    'status-title' => 'Instant Credit Failed',
                    'status-message' => sprintf('Magento response: %s', $e->getMessage())
                ]
            );
        }

        $updateResp = [
            'success' => true,
            'status-title' => $this->salesRule->getCouponCode(),
            'status-message' => 'Instant Credit successfully created.'
        ];

        return $resultJson->setData($updateResp);
    }

    /**
     * @return bool
     */
    private function requestIsAllowed(): bool
    {
        if (
            !$this->webhook->canProceedsRequest() ||
            !$this->webhook->validateParams('coupon_code', $this->params)
        ) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function loadCustomerData()
    {
        $this->customerData =
            $this->customer->getById($this->params['customer_id']);
    }

    /**
     * @return Rule
     * @throws Exception
     */
    private function createSalesRule()
    {
        return $this->salesRule
            ->setName($this->couponParams['name'])
            ->setDescription($this->couponParams['description'])
            ->setWebsiteIds($this->couponParams['website_ids'])
            ->setFromDate($this->couponParams['from_date'])
            ->setToDate($this->couponParams['to_date'])
            ->setUsesPerCustomer($this->couponParams['use_per_customer'])
            ->setDiscountAmount($this->couponParams['discount_amount'])
            ->setSimpleAction($this->couponParams['discount_type'])
            ->setApplyToShipping($this->couponParams['free_shipping_flag'])
            ->setTimesUsed($this->couponParams['times_used'])
            ->setCouponCode($this->couponParams['coupon_code'])
            ->setCustomerGroupIds($this->couponParams['customer_groups'])
            ->setCouponType($this->couponParams['coupon_type'])
            ->setUsesPerCoupon($this->couponParams['times_used'])
            ->setIsActive(1)
            ->setDiscountQty(1)
            ->save();
    }

    /**
     * Prepare coupon params
     */
    private function prepareCouponParams()
    {
        $this->couponParams = [
            'name' => 'WeSupply Instant Credit - ' . $this->customerData->getEmail(),
            'description' => sprintf(
                'Instant Credit triggered by WeSupply Return Request for customer: %s, in amount of: %s',
                $this->customerData->getEmail(),
                number_format(floatval($this->params['total_credit']), 2)
            ),
            'website_ids' => $this->customerData->getWebsiteId(),
            'customer_groups' => $this->customerData->getGroupId(),
            'from_date' => date('Y-m-d'),
            'to_date' => date('Y-m-d', strtotime('+1 year')),
            'use_per_customer' => 1,
            'discount_amount' => floatval($this->params['total_credit']),
            'discount_type' => RuleInterface::DISCOUNT_ACTION_FIXED_AMOUNT_FOR_CART,
            'free_shipping_flag' => RuleInterface::FREE_SHIPPING_NONE,
            'times_used' => 1,
            'coupon_code' => 'WS-' . $this->generateRandomCode(),
            'coupon_type' => 2
        ];
    }

    /**
     * @return string
     */
    private function generateRandomCode()
    {
        $allowedCharacters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        return substr(str_shuffle($allowedCharacters), 0, 10);
    }
}
