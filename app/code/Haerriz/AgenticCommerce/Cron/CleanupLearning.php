<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Cron;

use Haerriz\AgenticCommerce\Model\Learning\AdaptiveLearningService;
use Magento\Store\Model\StoreManagerInterface;

class CleanupLearning
{
    public function __construct(private AdaptiveLearningService $learning, private StoreManagerInterface $stores) {}
    public function execute(): void
    {
        foreach ($this->stores->getStores() as $store) $this->learning->prune((int)$store->getId());
    }
}
