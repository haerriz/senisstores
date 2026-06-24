<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */

namespace Haerriz\AbandonedCart\Cron;

use Haerriz\AbandonedCart\Model\Config;
use Haerriz\AbandonedCart\Model\EmailSender;
use Haerriz\AbandonedCart\Model\QuoteFinder;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class SendReminders
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var QuoteFinder
     */
    private $quoteFinder;

    /**
     * @var EmailSender
     */
    private $emailSender;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Config $config
     * @param QuoteFinder $quoteFinder
     * @param EmailSender $emailSender
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        QuoteFinder $quoteFinder,
        EmailSender $emailSender,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->quoteFinder = $quoteFinder;
        $this->emailSender = $emailSender;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $batchSize = $this->config->getBatchSize();
        $delayMin = $this->config->getDelayMinSeconds();
        $delayMax = $this->config->getDelayMaxSeconds();
        $sentCount = 0;

        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->getIsActive() || !$this->config->isEnabled((int) $store->getId())) {
                continue;
            }

            $candidates = $this->quoteFinder->findAbandoned((int) $store->getId(), $batchSize - $sentCount);
            foreach ($candidates as $candidate) {
                if ($sentCount >= $batchSize) {
                    break 2;
                }

                if ($this->emailSender->send($candidate)) {
                    $sentCount++;
                    $this->logger->info(
                        'Abandoned cart email sent',
                        ['quote_id' => $candidate['quote_id'], 'email' => $candidate['email']]
                    );

                    if ($sentCount < $batchSize && $delayMax > 0) {
                        $sleep = $delayMin === $delayMax ? $delayMin : random_int($delayMin, $delayMax);
                        if ($sleep > 0) {
                            sleep($sleep);
                        }
                    }
                }
            }
        }
    }
}
