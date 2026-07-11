<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 *
 * Rate-limited newsletter sender for Hostinger shared mail limits.
 * Run via cron every 10–15 minutes; sends a small batch per run.
 *
 * Usage (from Magento root):
 *   php tools/magento/newsletter-send-batch.php
 *   php tools/magento/newsletter-send-batch.php --queue-id=2 --batch-size=6
 *   php tools/magento/newsletter-send-batch.php --dry-run
 */

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Newsletter\Model\Queue;
use Magento\Newsletter\Model\QueueFactory;

require __DIR__ . '/../../app/bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);
$queueId = 0;
$batchSize = 6;
$delayMin = 5;
$delayMax = 12;

foreach ($argv as $arg) {
    if (strpos($arg, '--queue-id=') === 0) {
        $queueId = (int) substr($arg, strlen('--queue-id='));
    }
    if (strpos($arg, '--batch-size=') === 0) {
        $batchSize = max(1, min(15, (int) substr($arg, strlen('--batch-size='))));
    }
    if (strpos($arg, '--delay-min=') === 0) {
        $delayMin = max(1, (int) substr($arg, strlen('--delay-min=')));
    }
    if (strpos($arg, '--delay-max=') === 0) {
        $delayMax = max($delayMin, (int) substr($arg, strlen('--delay-max=')));
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

if ($queueId <= 0) {
    $queueId = (int) $connection->fetchOne(
        'SELECT queue_id FROM newsletter_queue WHERE queue_status = ? ORDER BY queue_id DESC LIMIT 1',
        [Queue::STATUS_SENDING]
    );
}

if ($queueId <= 0) {
    echo "No newsletter queue in SENDING status. Nothing to do.\n";
    exit(0);
}

/** @var QueueFactory $queueFactory */
$queueFactory = $objectManager->get(QueueFactory::class);

/** @var Queue $queue */
$queue = $queueFactory->create()->load($queueId);
if (!(int) $queue->getId()) {
    fwrite(STDERR, "Queue {$queueId} not found.\n");
    exit(1);
}

$pending = (int) $connection->fetchOne(
    'SELECT COUNT(*) FROM newsletter_queue_link WHERE queue_id = ? AND letter_sent_at IS NULL',
    [$queueId]
);

$sent = (int) $connection->fetchOne(
    'SELECT COUNT(*) FROM newsletter_queue_link WHERE queue_id = ? AND letter_sent_at IS NOT NULL',
    [$queueId]
);

$total = $sent + $pending;

echo date('c') . " Queue {$queueId}: {$sent}/{$total} sent, {$pending} pending\n";
echo "Batch plan: up to {$batchSize} emails, delay {$delayMin}-{$delayMax}s between each\n";

if ($pending === 0) {
    if ((int) $queue->getQueueStatus() === Queue::STATUS_SENDING) {
        $queue->setQueueStatus(Queue::STATUS_SENT);
        $queue->setQueueFinishAt($objectManager->get(\Magento\Framework\Stdlib\DateTime\DateTime::class)->gmtDate());
        $queue->save();
        echo "Queue marked SENT.\n";
    } else {
        echo "Queue already complete (status {$queue->getQueueStatus()}).\n";
    }
    exit(0);
}

if ($dryRun) {
    echo "Dry run: would send up to {$batchSize} emails.\n";
    exit(0);
}

$sentThisRun = 0;
for ($i = 0; $i < $batchSize; $i++) {
    /** @var Queue $batchQueue */
    $batchQueue = $queueFactory->create()->load($queueId);
    if ((int) $batchQueue->getQueueStatus() !== Queue::STATUS_SENDING) {
        break;
    }

    $stillPending = (int) $connection->fetchOne(
        'SELECT COUNT(*) FROM newsletter_queue_link WHERE queue_id = ? AND letter_sent_at IS NULL',
        [$queueId]
    );
    if ($stillPending === 0) {
        break;
    }

    try {
        $batchQueue->sendPerSubscriber(1);
        $sentThisRun++;
        echo "  sent email #" . ($sent + $sentThisRun) . "\n";
    } catch (\Throwable $e) {
        echo "  error: {$e->getMessage()}\n";
        break;
    }

    if ($i < $batchSize - 1 && $stillPending > 1) {
        $sleep = random_int($delayMin, $delayMax);
        echo "  sleep {$sleep}s\n";
        sleep($sleep);
    }
}

$queue->load($queueId);
$pendingAfter = (int) $connection->fetchOne(
    'SELECT COUNT(*) FROM newsletter_queue_link WHERE queue_id = ? AND letter_sent_at IS NULL',
    [$queueId]
);

if ($pendingAfter === 0 && (int) $queue->getQueueStatus() === Queue::STATUS_SENDING) {
    $queue->setQueueStatus(Queue::STATUS_SENT);
    $queue->setQueueFinishAt($objectManager->get(\Magento\Framework\Stdlib\DateTime\DateTime::class)->gmtDate());
    $queue->save();
    echo "Queue {$queueId} completed.\n";
} else {
    echo "Sent {$sentThisRun} this run; {$pendingAfter} still pending.\n";
}
