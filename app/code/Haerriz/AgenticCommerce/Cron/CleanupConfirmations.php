<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Cron;

use Magento\Framework\App\ResourceConnection;

class CleanupConfirmations
{
    public function __construct(private ResourceConnection $resource) {}
    public function execute(): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('haerriz_agentic_confirmation');
        $cutoff = date('Y-m-d H:i:s', time() - 86400);
        $connection->delete($table, ['expires_at < ?' => $cutoff]);
        $connection->delete($table, ['used_at IS NOT NULL AND used_at < ?' => $cutoff]);
    }
}
