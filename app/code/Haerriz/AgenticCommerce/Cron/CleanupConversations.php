<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Cron;

use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Conversation\ConversationRepository;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CleanupConversations
{
    public function __construct(
        private ConversationRepository $conversations,
        private Config $config,
        private StoreManagerInterface $storeManager,
        private LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            $totals = ['guest_deleted' => 0, 'customer_deleted' => 0, 'stores' => 0];
            foreach ($this->storeManager->getStores(false) as $store) {
                $storeId = (int)$store->getId();
                if ($storeId <= 0) {
                    continue;
                }
                $guestDays = $this->config->getGuestRetentionDays($storeId);
                $customerDays = $this->config->getCustomerRetentionDays($storeId);
                $guestCutoff = gmdate('Y-m-d H:i:s', time() - ($guestDays * 86400));
                $customerCutoff = gmdate('Y-m-d H:i:s', time() - ($customerDays * 86400));
                $guestDeleted = $this->conversations->deleteOlderThan($guestCutoff, true, $storeId);
                $customerDeleted = $this->conversations->deleteOlderThan($customerCutoff, false, $storeId);
                $totals['guest_deleted'] += $guestDeleted;
                $totals['customer_deleted'] += $customerDeleted;
                $totals['stores']++;
                $this->logger->info('Haerriz Agentic Commerce store retention cleanup completed.', [
                    'store_id' => $storeId,
                    'guest_deleted' => $guestDeleted,
                    'customer_deleted' => $customerDeleted,
                    'guest_days' => $guestDays,
                    'customer_days' => $customerDays,
                ]);
            }
            $this->logger->info('Haerriz Agentic Commerce retention cleanup completed.', $totals);
        } catch (\Throwable $e) {
            $this->logger->error('Haerriz Agentic Commerce retention cleanup failed.', ['exception' => $e]);
        }
    }
}
