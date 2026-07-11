<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Cron\Order;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Magento\Shipping\Model\ResourceModel\Order\Track\Collection as ShipmentTrackCollection;
use WeSupply\Toolbox\Api\OrderRepositoryInterface;
use WeSupply\Toolbox\Cron\CronBase;
use WeSupply\Toolbox\Helper\WeSupplyMappings;
use WeSupply\Toolbox\Logger\Logger;

/**
 * Class OrderUpdatesCheck
 *
 * @package WeSupply\Toolbox\Cron
 */
class UpdatesCheck extends CronBase
{
    /**
     * @var string
     */
    private const DATETIME_OFFSET = '-10 minutes';

    /**
     * @var OrderCollection
     */
    protected $orderCollection;

    /**
     * @var InvoiceCollection
     */
    protected $invoiceCollection;

    /**
     * @var ShipmentTrackCollection
     */
    protected $shipmentTrackCollection;

    /**
     * @var string
     */
    private $startDateTime;

    /**
     * @var array
     */
    private $awaiting;

    /**
     * OrderUpdatesCheck constructor.
     *
     * @param Context                  $context
     * @param DateTime                 $dateTime
     * @param OrderRepositoryInterface $wsOrderRepository
     * @param OrderCollection          $orderCollection
     * @param InvoiceCollection        $invoiceCollection
     * @param ShipmentTrackCollection  $shipmentTrackCollection
     * @param WeSupplyMappings         $weSupplyMappings
     * @param Json                     $json
     * @param Logger                   $logger
     */
    public function __construct (
        Context $context,
        DateTime $dateTime,
        OrderRepositoryInterface $wsOrderRepository,
        OrderCollection $orderCollection,
        InvoiceCollection $invoiceCollection,
        ShipmentTrackCollection $shipmentTrackCollection,
        WeSupplyMappings $weSupplyMappings,
        Json $json,
        Logger $logger
    ) {
        $this->awaiting = [];
        $this->orderCollection = $orderCollection;
        $this->invoiceCollection = $invoiceCollection;
        $this->shipmentTrackCollection = $shipmentTrackCollection;

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
     */
    public function execute()
    {
        if (!$this->isWesupplyEnabled()) {
            return $this;
        }

        $this->logger->info('Cron Job ws_updates_check start.');

        $currDateTime = $this->getCurrentTimestamp();
        $this->startDateTime = $this->formatDateTime(strtotime(self::DATETIME_OFFSET, $currDateTime));

        $this->compareOrder();
        $this->compareInvoice();
        $this->compareShipmentTrack();

        if (!empty($this->awaiting)) {
            $this->logger->info(
                'Cron Job ws_updates_check successfully finished for ', array_unique($this->awaiting)
            );
        }

        return $this;
    }

    /**
     * Compare mage orders against ws orders
     */
    private function compareOrder()
    {
        $orders = $this->getOrdersByDate();
        $updated = $orders->getItems();

        foreach ($updated as $orderId => $order) {

            $wsOrder = $this->loadWsOrder($orderId);
            if (!$wsOrder->getId()) {
                $this->triggerOrderUpdate($orderId);
                continue;
            }

            $this->compareUpdatedAt($order, $wsOrder);
        }
    }

    /**
     * Compare order invoices against ws orders
     */
    private function compareInvoice()
    {
        $invoices = $this->getInvoicesByDate();
        $updated = $invoices->getItems();

        foreach ($updated as $invoice) {

            $wsOrder = $this->loadWsOrder($invoice->getOrderId());
            if (!$wsOrder->getId()) {
                $this->triggerOrderUpdate($invoice->getOrderId());
                continue;
            }

            $this->compareUpdatedAt($invoice, $wsOrder);
        }
    }

    /**
     * Compare tracking against ws orders
     */
    private function compareShipmentTrack()
    {
        $shipments = $this->getShipmentTracksByDate();
        $updated = $shipments->getItems();

        foreach ($updated as $tracking) {

            $wsOrder = $this->loadWsOrder($tracking->getOrderId());
            if (!$wsOrder->getId()) {
                $this->triggerOrderUpdate($tracking->getOrderId());
                continue;
            }

            $this->compareUpdatedAt($tracking, $wsOrder);
        }
    }

    /**
     * @return OrderCollection
     */
    private function getOrdersByDate()
    {
        return $this->orderCollection
            ->addAttributeToSelect('entity_id')
            ->addAttributeToSelect('updated_at')
            ->addFieldToFilter('updated_at', ['gteq' => $this->startDateTime])
            ->setOrder('updated_at','asc');
    }

    /**
     * @return InvoiceCollection
     */
    private function getInvoicesByDate()
    {
        return $this->invoiceCollection
            ->addAttributeToSelect('order_id')
            ->addAttributeToSelect('updated_at')
            ->addFieldToFilter('updated_at', ['gteq' => $this->startDateTime])
            ->setOrder('updated_at','asc');
    }

    /**
     * @return ShipmentTrackCollection
     */
    private function getShipmentTracksByDate()
    {
        return $this->shipmentTrackCollection
            ->addAttributeToSelect('order_id')
            ->addAttributeToSelect('updated_at')
            ->addFieldToFilter('updated_at', ['gteq' => $this->startDateTime])
            ->setOrder('updated_at','asc');
    }

    /**
     * @param $orderId
     *
     * @return mixed
     */
    private function loadWsOrder($orderId)
    {
        return $this->wsOrderRepository->getByOrderId($orderId);
    }

    /**
     * @param $resource
     * @param $wsOrder
     */
    private function compareUpdatedAt($resource, $wsOrder)
    {
        if (
            $this->dateTime->timestamp($resource->getUpdatedAt()) >
            $this->dateTime->timestamp($wsOrder->getUpdatedAt())
        ) {
            // set update_awaiting flag
            array_push($this->awaiting, $wsOrder->getOrderId());
            $wsOrder->setAwaitingUpdate(TRUE)->save();
        }
    }

    /**
     * @param $orderId
     */
    private function triggerOrderUpdate($orderId)
    {
        $this->wsOrderRepository->triggerOrderUpdate($orderId);
        $this->logger->info(
            'Cron Job ws_updates_check added new order to wesupply orders with id: ', [$orderId]
        );
    }
}
