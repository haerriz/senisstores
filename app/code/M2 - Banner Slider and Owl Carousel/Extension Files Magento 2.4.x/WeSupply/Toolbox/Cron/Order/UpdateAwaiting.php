<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Cron\Order;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use WeSupply\Toolbox\Api\OrderRepositoryInterface;
use WeSupply\Toolbox\Cron\CronBase;
use WeSupply\Toolbox\Helper\WeSupplyMappings;
use WeSupply\Toolbox\Logger\Logger;
use WeSupply\Toolbox\Model\ResourceModel\Order\Collection as WsOrderCollection;

/**
 * Class UpdateAwaiting
 *
 * @package WeSupply\Toolbox\Cron\Order
 */
class UpdateAwaiting extends CronBase
{
    /**
     * @var int
     */
    private const ORDERS_LIMIT = 500;

    /**
     * @var WsOrderCollection
     */
    protected $wsOrderCollection;

    /**
     * UpdateAwaiting constructor.
     *
     * @param Context                  $context
     * @param DateTime                 $dateTime
     * @param OrderRepositoryInterface $wsOrderRepository
     * @param WeSupplyMappings         $weSupplyMappings
     * @param Json                     $json
     * @param Logger                   $logger
     * @param WsOrderCollection        $wsOrderCollection
     */
    public function __construct (
        Context $context,
        DateTime $dateTime,
        OrderRepositoryInterface $wsOrderRepository,
        WeSupplyMappings $weSupplyMappings,
        Json $json,
        Logger $logger,
        WsOrderCollection $wsOrderCollection
    ) {
        $this->wsOrderCollection = $wsOrderCollection;

        parent::__construct(
            $context,
            $dateTime,
            $wsOrderRepository,
            $weSupplyMappings,
            $json,
            $logger
        );
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function execute()
    {
        if (!$this->isWesupplyEnabled()) {
            return $this;
        }

        try {
            $wsOrders = $this->wsOrderCollection
                ->addFieldToFilter('awaiting_update', ['eq' => true])
                ->setOrder('id', 'asc')
                ->setPageSize(self::ORDERS_LIMIT)
                ->setCurPage(1);
        } catch (Exception $ex) {
            $this->logger->error('UpdateAwaiting error :: ' . $ex->getMessage());
        }

        $updated = [];

        if (!empty($wsOrders)) {
            foreach ($wsOrders as $wsOrder) {
                array_push($updated, $wsOrder->getOrderId());
                $wsOrder->setAwaitingUpdate(FALSE)->save();
                $this->wsOrderRepository->setOrder($wsOrder);
                $this->wsOrderRepository->triggerOrderUpdate();
            }

            $this->logger->info(
                'Cron Job ws_update_awaiting successfully finished for ', $updated
            );

            return $this;
        }

        return $this;
    }
}
