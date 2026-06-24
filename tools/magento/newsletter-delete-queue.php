<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 */
require __DIR__ . '/../../app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');
$c = $om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$queueId = (int) ($argv[1] ?? 0);
if ($queueId <= 0) {
    fwrite(STDERR, "Usage: php newsletter-delete-queue.php <queue_id>\n");
    exit(1);
}
$c->delete('newsletter_queue_link', ['queue_id = ?' => $queueId]);
$c->delete('newsletter_problem', ['queue_id = ?' => $queueId]);
$c->delete('newsletter_queue_store_link', ['queue_id = ?' => $queueId]);
$c->delete('newsletter_queue', ['queue_id = ?' => $queueId]);
echo "Deleted queue {$queueId}\n";
