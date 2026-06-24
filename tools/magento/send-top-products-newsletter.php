<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 *
 * Subscribe all customers to the newsletter and send a top-products email.
 *
 * Usage (from Magento root):
 *   php tools/magento/send-top-products-newsletter.php --dry-run
 *   php tools/magento/send-top-products-newsletter.php --send
 */

declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Newsletter\Model\Queue;
use Magento\Newsletter\Model\QueueFactory;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory as SubscriberCollectionFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Newsletter\Model\Template;
use Magento\Newsletter\Model\TemplateFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

require __DIR__ . '/../../app/bootstrap.php';

$dryRun = in_array('--dry-run', $argv, true);
$send = in_array('--send', $argv, true);
$queueOnly = in_array('--queue-only', $argv, true);
$resumeQueueId = 0;
foreach ($argv as $arg) {
    if (strpos($arg, '--resume-queue-id=') === 0) {
        $resumeQueueId = (int) substr($arg, strlen('--resume-queue-id='));
    }
}

if (!$dryRun && !$send && $resumeQueueId <= 0) {
    fwrite(STDERR, "Pass --dry-run, --send, or --resume-queue-id=<id>\n");
    exit(1);
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

/** @var StoreManagerInterface $storeManager */
$storeManager = $objectManager->get(StoreManagerInterface::class);
$store = $storeManager->getStore();
$storeId = (int) $store->getId();

/** @var ScopeConfigInterface $scopeConfig */
$scopeConfig = $objectManager->get(ScopeConfigInterface::class);
$senderEmail = (string) $scopeConfig->getValue('trans_email/ident_general/email', ScopeInterface::SCOPE_STORE, $storeId);
$senderName = (string) $scopeConfig->getValue('trans_email/ident_general/name', ScopeInterface::SCOPE_STORE, $storeId);
$newsletterActive = $scopeConfig->isSetFlag('newsletter/general/active', ScopeInterface::SCOPE_STORE, $storeId)
    || $scopeConfig->getValue('newsletter/general/active', ScopeInterface::SCOPE_STORE, $storeId) === null;

if (!$newsletterActive) {
    fwrite(STDERR, "Newsletter is disabled in config. Enable it in Admin > Stores > Configuration > Customers > Newsletter.\n");
    exit(1);
}

/** @var CustomerCollectionFactory $customerCollectionFactory */
$customerCollectionFactory = $objectManager->get(CustomerCollectionFactory::class);
/** @var SubscriberFactory $subscriberFactory */
$subscriberFactory = $objectManager->get(SubscriberFactory::class);
/** @var SubscriberCollectionFactory $subscriberCollectionFactory */
$subscriberCollectionFactory = $objectManager->get(SubscriberCollectionFactory::class);
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
/** @var PriceHelper $priceHelper */
$priceHelper = $objectManager->get(PriceHelper::class);
$connection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();

echo "Store: {$store->getName()} (ID {$storeId})\n";
echo "Sender: {$senderName} <{$senderEmail}>\n";

if ($resumeQueueId > 0) {
    /** @var QueueFactory $queueFactory */
    $queueFactory = $objectManager->get(QueueFactory::class);
    $queue = $queueFactory->create()->load($resumeQueueId);
    if (!(int) $queue->getId()) {
        fwrite(STDERR, "Queue {$resumeQueueId} not found.\n");
        exit(1);
    }
    echo "Resuming queue ID: {$resumeQueueId}, status: {$queue->getQueueStatus()}\n";
    if ((int) $queue->getQueueStatus() === Queue::STATUS_NEVER) {
        /** @var \Magento\Framework\Stdlib\DateTime\DateTime $date */
        $date = $objectManager->get(\Magento\Framework\Stdlib\DateTime\DateTime::class);
        $queue->setQueueStartAt($date->gmtDate());
        $queue->setQueueStatus(Queue::STATUS_SENDING);
        $queue->save();
    }
    goto send_queue;
}

$customers = $customerCollectionFactory->create()
    ->addAttributeToSelect(['email', 'firstname', 'lastname', 'store_id'])
    ->addFieldToFilter('email', ['notnull' => true]);

$subscribed = 0;
$alreadySubscribed = 0;
$skipped = 0;

foreach ($customers as $customer) {
    $email = trim((string) $customer->getEmail());
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $skipped++;
        continue;
    }

    /** @var Subscriber $subscriber */
    $subscriber = $subscriberFactory->create();
    $subscriber->loadByEmail($email);

    if ((int) $subscriber->getId() > 0
        && (int) $subscriber->getStatus() === Subscriber::STATUS_SUBSCRIBED
    ) {
        $alreadySubscribed++;
        continue;
    }

    if ($dryRun) {
        echo "[dry-run] would subscribe: {$email}\n";
        $subscribed++;
        continue;
    }

    try {
        $subscriber->setStoreId((int) $customer->getStoreId());
        $subscriber->setCustomerId((int) $customer->getId());
        $subscriber->setStatus(Subscriber::STATUS_SUBSCRIBED);
        $subscriber->setEmail($email);
        if (!(int) $subscriber->getId()) {
            $subscriber->setSubscriberConfirmCode($subscriber->randomSequence());
        }
        $subscriber->save();
        $subscribed++;
        echo "Subscribed: {$email}\n";
    } catch (\Exception $e) {
        echo "Failed {$email}: {$e->getMessage()}\n";
        $skipped++;
    }
}

echo "Customers processed: " . $customers->getSize() . "\n";
echo "Newly subscribed: {$subscribed}\n";
echo "Already subscribed: {$alreadySubscribed}\n";
echo "Skipped: {$skipped}\n";

$productRows = $connection->fetchAll(
    "SELECT cpe.entity_id, cpe.sku,
            COALESCE(name.value, cpe.sku) AS name,
            SUM(COALESCE(soi.qty_ordered, 0)) AS qty_ordered
     FROM sales_order_item soi
     INNER JOIN catalog_product_entity cpe ON cpe.entity_id = soi.product_id
     LEFT JOIN catalog_product_entity_varchar name
       ON name.entity_id = cpe.entity_id
      AND name.attribute_id = (
          SELECT attribute_id FROM eav_attribute
          WHERE attribute_code = 'name' AND entity_type_id = 4 LIMIT 1
      )
      AND name.store_id IN (0, {$storeId})
     WHERE soi.parent_item_id IS NULL
     GROUP BY cpe.entity_id, cpe.sku, name.value
     ORDER BY qty_ordered DESC
     LIMIT 6"
);

if (!$productRows) {
    $productRows = $connection->fetchAll(
        "SELECT cpe.entity_id, cpe.sku, COALESCE(name.value, cpe.sku) AS name, 0 AS qty_ordered
         FROM catalog_product_entity cpe
         INNER JOIN catalog_product_entity_int status
           ON status.entity_id = cpe.entity_id
          AND status.attribute_id = (
              SELECT attribute_id FROM eav_attribute
              WHERE attribute_code = 'status' AND entity_type_id = 4 LIMIT 1
          )
          AND status.store_id = 0
          AND status.value = 1
         LEFT JOIN catalog_product_entity_varchar name
           ON name.entity_id = cpe.entity_id
          AND name.attribute_id = (
              SELECT attribute_id FROM eav_attribute
              WHERE attribute_code = 'name' AND entity_type_id = 4 LIMIT 1
          )
          AND name.store_id IN (0, {$storeId})
         ORDER BY cpe.entity_id DESC
         LIMIT 6"
    );
}

$productBlocks = '';
foreach ($productRows as $row) {
    try {
        $product = $productRepository->getById((int) $row['entity_id'], false, $storeId);
    } catch (NoSuchEntityException $e) {
        continue;
    }

    if ((int) $product->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE) {
        continue;
    }

    $name = htmlspecialchars((string) $product->getName(), ENT_QUOTES, 'UTF-8');
    $url = htmlspecialchars($product->getProductUrl(), ENT_QUOTES, 'UTF-8');
    $price = htmlspecialchars((string) $priceHelper->currency($product->getFinalPrice(), true, false), ENT_QUOTES, 'UTF-8');
    $image = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA)
        . 'catalog/product' . $product->getImage();

    $productBlocks .= <<<HTML
<tr>
  <td style="padding:12px;border-bottom:1px solid #eee;">
    <a href="{$url}" style="color:#1a1a1a;text-decoration:none;">
      <img src="{$image}" alt="{$name}" width="120" style="display:block;border:0;max-width:120px;height:auto;" />
    </a>
  </td>
  <td style="padding:12px;border-bottom:1px solid #eee;vertical-align:top;">
    <a href="{$url}" style="color:#1a1a1a;font-size:16px;font-weight:bold;text-decoration:none;">{$name}</a><br/>
    <span style="color:#c9a227;font-size:18px;font-weight:bold;">{$price}</span>
  </td>
</tr>

HTML;
}

$subject = "Top picks at Seni's Stores — tools, sheets, paint & more";
$preheader = "See what's selling fast at Seni's Stores in Theni.";

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">
  <span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;">{$preheader}</span>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
          <tr>
            <td style="background:#1a1a1a;color:#ffffff;padding:24px;text-align:center;">
              <h1 style="margin:0;font-size:24px;">Seni's Stores</h1>
              <p style="margin:8px 0 0;font-size:14px;color:#c9a227;">Hardware &amp; building materials in Theni</p>
            </td>
          </tr>
          <tr>
            <td style="padding:24px;color:#333333;font-size:15px;line-height:1.6;">
              <p style="margin:0 0 16px;">Hello,</p>
              <p style="margin:0 0 20px;">Here are our <strong>top-selling products</strong> this month — GC sheets, tools, paints, wire, and more. Visit us on Periyakulam Main Road or shop online.</p>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                {$productBlocks}
              </table>
              <p style="margin:24px 0 8px;text-align:center;">
                <a href="https://senisstores.com/" style="background:#c9a227;color:#1a1a1a;padding:12px 24px;text-decoration:none;font-weight:bold;border-radius:4px;display:inline-block;">Shop now at senisstores.com</a>
              </p>
              <p style="margin:20px 0 0;font-size:13px;color:#666;">
                Call <a href="tel:+919442650753" style="color:#1a1a1a;">+91 9442650753</a> for bulk orders and delivery in Theni &amp; nearby areas.
              </p>
            </td>
          </tr>
          <tr>
            <td style="background:#f0f0f0;padding:16px;text-align:center;font-size:12px;color:#888;">
              Seni's Stores · 1129A Periyakulam Main Road, Theni 625531<br/>
              <a href="{{var subscriber.getUnsubscriptionLink()}}" style="color:#888;">Unsubscribe</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

$subscriberCount = $subscriberCollectionFactory->create()
    ->addFieldToFilter('subscriber_status', Subscriber::STATUS_SUBSCRIBED)
    ->getSize();

echo "Active subscribers after subscribe step: {$subscriberCount}\n";
echo "Products in template: " . count($productRows) . "\n";
echo "Subject: {$subject}\n";

if ($dryRun) {
    echo "Dry run complete. Re-run with --send to create template, queue, and send.\n";
    exit(0);
}

send_queue:
if ($resumeQueueId <= 0) {
/** @var TemplateFactory $templateFactory */
$templateFactory = $objectManager->get(TemplateFactory::class);
$templateCode = 'senis_top_products_' . date('Ymd_His');

/** @var Template $template */
$template = $templateFactory->create();
$template->setTemplateCode($templateCode);
$template->setTemplateSubject($subject);
$template->setTemplateSenderName($senderName);
$template->setTemplateSenderEmail($senderEmail);
$template->setTemplateText($html);
$template->setTemplateStyles('');
$template->setTemplateType(Template::TYPE_HTML);
$template->save();

echo "Created newsletter template ID: {$template->getId()} ({$templateCode})\n";

/** @var QueueFactory $queueFactory */
$queueFactory = $objectManager->get(QueueFactory::class);

/** @var Queue $queue */
$queue = $queueFactory->create();
$queue->setTemplateId((int) $template->getId());
$queue->setNewsletterSubject($subject);
$queue->setNewsletterSenderName($senderName);
$queue->setNewsletterSenderEmail($senderEmail);
$queue->setNewsletterText($template->getTemplateText());
$queue->setNewsletterStyles((string) $template->getTemplateStyles());
$queue->setQueueStatus(Queue::STATUS_NEVER);
$queue->setStores([$storeId]);
$queue->save();

echo "Created newsletter queue ID: {$queue->getId()}\n";

/** @var \Magento\Framework\Stdlib\DateTime\DateTime $date */
$date = $objectManager->get(\Magento\Framework\Stdlib\DateTime\DateTime::class);
$queue->setQueueStartAt($date->gmtDate());
$queue->setQueueStatus(Queue::STATUS_SENDING);
$queue->save();
}

/** @var QueueFactory $queueFactory */
if (!isset($queueFactory)) {
    $queueFactory = $objectManager->get(QueueFactory::class);
}
echo "Queue started. Sending in batches...\n";

$queueId = (int) $queue->getId();
$sentBatches = 0;
while (true) {
    /** @var Queue $batchQueue */
    $batchQueue = $queueFactory->create()->load($queueId);
    if ((int) $batchQueue->getQueueStatus() !== Queue::STATUS_SENDING) {
        break;
    }
    $batchQueue->sendPerSubscriber(20);
    $sentBatches++;
    echo "Batch {$sentBatches}, queue status: {$batchQueue->getQueueStatus()}\n";
    if ($sentBatches > 500) {
        fwrite(STDERR, "Aborting after 500 send batches.\n");
        break;
    }
}

$queue->load($queueId);
echo "Final queue status: {$queue->getQueueStatus()}\n";
echo "Newsletter send complete.\n";
