<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Observer\Returns;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Registry;
use WeSupply\Toolbox\Model\ReturnsRepository;

/**
 * Class RegisterRefund
 *
 * @package WeSupply\Toolbox\Observer\Returns
 */
class RegisterRefund implements ObserverInterface
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var ReturnsRepository
     */
    protected $wsReturnsRepository;

    /**
     * RegisterRefund constructor.
     *
     * @param Registry          $registry
     * @param ReturnsRepository $wsReturnsRepository
     */
    public function __construct(
        Registry $registry,
        ReturnsRepository $wsReturnsRepository
    ) {
        $this->registry = $registry;
        $this->wsReturnsRepository = $wsReturnsRepository;
    }

    /**
     * @param Observer $observer
     *
     * @return $this
     * @throws AlreadyExistsException
     * @throws \Exception
     */
    public function execute(Observer $observer)
    {
        $triggeredBy = $observer->getEvent()->getName();
        switch ($triggeredBy) {
            case 'sales_order_creditmemo_refund':
                $returnSplitId = $this->getReturnSplitId();
                $this->updateReturnBySplitId($returnSplitId);
                break;
            case 'sales_order_payment_refund':
                $this->registerPayment($observer);
                break;
            case 'adminhtml_sales_order_creditmemo_register_before':
                $input =  $observer->getEvent()->getInput();
                $this->registerReturnRequest($input);
                break;
        }

        return $this;
    }

    /**
     * @param $input
     *
     * @throws AlreadyExistsException
     */
    private function registerReturnRequest($input)
    {
        $this->registry->unregister('return_reference');
        $this->registry->unregister('return_split_id');
        $this->registry->unregister('refund_amount');

        if (!empty($input['return_split_id'])) {
            $this->registry->register('return_reference', $input['return_reference']);
            $this->registry->register('return_split_id', $input['return_split_id']);

            $return = $this->wsReturnsRepository->getByReturnSplitId($input['return_split_id']);

            if (!$return->getId()) {
                $this->wsReturnsRepository->registerNewReturn(
                    $input['return_reference'],
                    $input['request_log_id'],
                    $input['return_split_id']
                );
            }
        }
    }

    /**
     * @param $observer
     */
    private function registerPayment($observer)
    {
        $splitId = $this->getReturnSplitId();
        if (!empty($splitId)) {
            $payment =  $observer->getEvent()->getPayment();
            $this->registry->register('refund_amount', $payment->getAmountRefunded());
        }
    }

    /**
     * @param $splitId
     *
     * @throws \Exception
     */
    private function updateReturnBySplitId($splitId)
    {
        $refundAmount = $this->getRefundAmount();
        if (!empty($refundAmount)) {
            $this->wsReturnsRepository->updateReturn(
                $splitId,
                [
                    'status' => 'done',
                    'refunded' => true
                ]
            );

        }
    }

    /**
     * @return mixed|string
     */
    private function getReturnSplitId()
    {
        return $this->registry->registry('return_split_id') ?? '';
    }

    /**
     * @return mixed|string
     */
    private function getRefundAmount()
    {
        return $this->registry->registry('refund_amount') ?? '';
    }
}
