<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Cron;

use Magento\Framework\App\ResourceConnection;

/** Removes expired durable idempotency rows. */
class CleanupIdempotency
{
    public function __construct(private ResourceConnection $resource) {}

    public function execute(): void
    {
        $this->resource->getConnection()->delete(
            $this->resource->getTableName('haerriz_agentic_idempotency'),
            ['expires_at < ?' => date('Y-m-d H:i:s')]
        );
    }
}
