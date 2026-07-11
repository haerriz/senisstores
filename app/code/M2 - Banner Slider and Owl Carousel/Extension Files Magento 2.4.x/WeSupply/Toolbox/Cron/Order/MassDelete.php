<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace WeSupply\Toolbox\Cron\Order;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use WeSupply\Toolbox\Api\OrderRepositoryInterface;
use WeSupply\Toolbox\Cron\CronBase;
use WeSupply\Toolbox\Helper\WeSupplyMappings;
use WeSupply\Toolbox\Logger\Logger;

/**
 * Class OrdersDelete
 *
 * @package WeSupply\Toolbox\Cron
 */
class MassDelete extends CronBase
{
    /**
     * @var string
     */
    private const WS_TABLE_NAME = 'wesupply_orders';

    /**
     * @var string
     */
    private const DATETIME_OFFSET = '-1 month';

    /**
     * @var int
     */
    private const ORDERS_LIMIT = 5000;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * OrdersDelete constructor.
     *
     * @param Context                  $context
     * @param OrderRepositoryInterface $wsOrderRepository
     * @param ResourceConnection       $resourceConnection
     * @param WeSupplyMappings         $weSupplyMappings
     * @param DateTime                 $dateTime
     * @param Json                     $json
     * @param Logger                   $logger
     */
    public function __construct
    (
        Context $context,
        OrderRepositoryInterface $wsOrderRepository,
        ResourceConnection $resourceConnection,
        WeSupplyMappings $weSupplyMappings,
        DateTime $dateTime,
        Json $json,
        Logger $logger
    )
    {
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
     */
    public function execute()
    {
        if (!$this->isWesupplyEnabled()) {
            return $this;
        }

        $endDate = $this->formatDateTime(
            strtotime(self::DATETIME_OFFSET, $this->getCurrentTimestamp())
        );

        $connection  = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName(self::WS_TABLE_NAME);

        $query = "DELETE FROM " . $tableName .
            " WHERE " . $connection->quoteInto('updated_at < ?', $endDate) .
            " ORDER BY 'id' ASC " .
            " LIMIT " . self::ORDERS_LIMIT;

        $this->logger->info("Cron Job ws_mass_delete started.");
        try {
            $connection->query($query);
            $this->logger->info("Cron Job ws_mass_delete finalized.");
        } catch (Exception $ex) {
            $this->logger->error('OrdersDelete error :: ' . $ex->getMessage());
        }

        return $this;
    }

}
