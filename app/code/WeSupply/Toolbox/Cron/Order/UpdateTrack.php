<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Cron\Order;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use WeSupply\Toolbox\Api\OrderRepositoryInterface;
use WeSupply\Toolbox\Cron\CronBase;
use WeSupply\Toolbox\Helper\WeSupplyMappings;
use WeSupply\Toolbox\Logger\Logger;

/**
 * Class OrderUpdatesCheck
 *
 * @package WeSupply\Toolbox\Cron
 */
class UpdateTrack extends CronBase
{
    /**
     * @var int
     */
    private const TRACK_LIMIT = 500;

    /**
     * @var string
     */
    private const SHIPMENT_TRACK_TABLE_NAME = 'sales_shipment_track';

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * UpdateAwaiting constructor.
     *
     * @param Context                  $context
     * @param DateTime                 $dateTime
     * @param OrderRepositoryInterface $wsOrderRepository
     * @param WeSupplyMappings         $weSupplyMappings
     * @param Json                     $json
     * @param Logger                   $logger
     * @param ResourceConnection       $resourceConnection
     */
    public function __construct (
        Context $context,
        DateTime $dateTime,
        OrderRepositoryInterface $wsOrderRepository,
        WeSupplyMappings $weSupplyMappings,
        Json $json,
        Logger $logger,
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;

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
        $isCronEnabled = $this->_scopeConfig->getValue('wesupply_api/advanced_settings/wesupply_cron_settings/cron_update_shipment_track');
        if (!$isCronEnabled) {
            return $this;
        }
        $ordersUpdated = [];
        $connection  = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName(self::SHIPMENT_TRACK_TABLE_NAME);

        $sql = "SELECT entity_id, order_id FROM " . $tableName . " WHERE
            `wesupply_order_update` != 1
            ORDER BY entity_id DESC
            LIMIT " . self::TRACK_LIMIT;

        $this->logger->info("Cron Job ws_update_track started.");

        $result = $connection->fetchAll($sql);
        foreach ($result as $trackInfo) {
            $orderId = $trackInfo['order_id'];
            $shipmentTrackId = $trackInfo['entity_id'];
            if (!in_array($orderId, $ordersUpdated)) {
                try {
                    $this->wsOrderRepository->triggerOrderUpdate($orderId);
                    $ordersUpdated[] = $orderId;
                    $this->logger->info('UpdateTrack success for order_id :: ' . $orderId);
                    $this->_updateTrackFlag($shipmentTrackId, $tableName, $connection);
                    $updateQuery = "UPDATE `" . $tableName . "` SET `wesupply_order_update`= 1 WHERE entity_id = $shipmentTrackId ";
                    $connection->query($updateQuery);
                } catch (\Exception $ex) {
                    $this->logger->error('UpdateTrack error for order_id :: ' . $orderId . ' =>  ' .  $ex->getMessage());
                }
            } else {
                $this->_updateTrackFlag($shipmentTrackId, $tableName, $connection);
            }

        }

        $this->logger->info("Cron Job ws_update_track finished.");

        return $this;
    }

    /**
     * @param integer $shipmentTrackId
     * @param string $tableName
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     */
    protected function _updateTrackFlag($shipmentTrackId, $tableName, $connection) {
        $updateQuery = "UPDATE `" . $tableName . "` SET `wesupply_order_update`= 1 WHERE entity_id = $shipmentTrackId ";
        $connection->query($updateQuery);
    }
}
