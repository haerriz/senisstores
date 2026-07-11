<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 *
 * Re-queue failed newsletter recipients for a slow cron resend.
 *
 * Usage:
 *   php tools/magento/newsletter-retry-failed.php --from-queue-id=1
 *   php tools/magento/newsletter-retry-failed.php --from-queue-id=1 --dry-run
 */

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Newsletter\Model\Queue;
use Magento\Newsletter\Model\QueueFactory;
use Magento\Newsletter\Model\Template;
use Magento\Newsletter\Model\TemplateFactory;

require __DIR__ . '/../../app/bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);
$fromQueueId = 1;

foreach ($argv as $arg) {
    if (strpos($arg, '--from-queue-id=') === 0) {
        $fromQueueId = (int) substr($arg, strlen('--from-queue-id='));
    }
}

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

/** @var State $appState */
$appState = $objectManager->get(State::class);
try {
    $appState->setAreaCode('adminhtml');
} catch (\Exception $e) {
    // Area already set.
}

$connection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
$storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
$storeId = (int) $storeManager->getStore()->getId();

/** @var QueueFactory $queueFactory */
$queueFactory = $objectManager->get(QueueFactory::class);
$oldQueue = $queueFactory->create()->load($fromQueueId);

if (!(int) $oldQueue->getId()) {
    fwrite(STDERR, "Source queue {$fromQueueId} not found.\n");
    exit(1);
}

$failedSubscriberIds = $connection->fetchCol(
    'SELECT DISTINCT subscriber_id FROM newsletter_problem WHERE queue_id = ?',
    [$fromQueueId]
);

if (!$failedSubscriberIds) {
    echo "No failed subscribers in newsletter_problem for queue {$fromQueueId}.\n";
    exit(0);
}

echo 'Failed subscribers to retry: ' . count($failedSubscriberIds) . "\n";

if ($dryRun) {
    exit(0);
}

$senderEmail = (string) $scopeConfig->getValue('trans_email/ident_general/email', 'store', $storeId);
$senderName = (string) $scopeConfig->getValue('trans_email/ident_general/name', 'store', $storeId);

/** @var TemplateFactory $templateFactory */
$templateFactory = $objectManager->get(TemplateFactory::class);
$sourceTemplate = $templateFactory->create()->load((int) $oldQueue->getTemplateId());

/** @var Template $template */
$template = $templateFactory->create();
$template->setTemplateCode('senis_retry_' . date('Ymd_His'));
$template->setTemplateSubject((string) $oldQueue->getNewsletterSubject());
$template->setTemplateSenderName($senderName);
$template->setTemplateSenderEmail($senderEmail);
$template->setTemplateText((string) $sourceTemplate->getTemplateText());
$template->setTemplateStyles((string) $sourceTemplate->getTemplateStyles());
$template->setTemplateType(Template::TYPE_HTML);
$template->save();

/** @var Queue $queue */
$queue = $queueFactory->create();
$queue->setTemplateId((int) $template->getId());
$queue->setNewsletterSubject((string) $oldQueue->getNewsletterSubject());
$queue->setNewsletterSenderName($senderName);
$queue->setNewsletterSenderEmail($senderEmail);
$queue->setNewsletterText($template->getTemplateText());
$queue->setNewsletterStyles((string) $template->getTemplateStyles());
$queue->setQueueStatus(Queue::STATUS_NEVER);
$queue->save();

$queue->addSubscribersToQueue(array_map('intval', $failedSubscriberIds));

$date = $objectManager->get(\Magento\Framework\Stdlib\DateTime\DateTime::class);
$queue->setQueueStartAt($date->gmtDate());
$queue->setQueueStatus(Queue::STATUS_SENDING);
$queue->save();

echo "Created retry queue ID: {$queue->getId()} with " . count($failedSubscriberIds) . " recipients.\n";
echo "Run: php tools/magento/newsletter-send-batch.php --queue-id={$queue->getId()}\n";
