<?php
/**
 * @author Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license https://magebit.com/code-license
 *
 * Send one test abandoned-cart email (does not log quote as emailed).
 *
 * Usage:
 *   php tools/magento/send-test-abandoned-cart.php --email=you@example.com
 *   php tools/magento/send-test-abandoned-cart.php --email=you@example.com --quote-id=123
 */

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../../app/bootstrap.php';

$options = getopt('', ['email:', 'quote-id::', 'name::']);
$email = isset($options['email']) ? trim((string) $options['email']) : '';
$quoteId = isset($options['quote-id']) ? (int) $options['quote-id'] : 0;
$name = isset($options['name']) ? trim((string) $options['name']) : 'Haerriz';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php send-test-abandoned-cart.php --email=valid@email.com [--quote-id=ID] [--name=Name]\n");
    exit(1);
}

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);

try {
    $state->setAreaCode('frontend');
} catch (\Exception $e) {
    // Area already set.
}

$connection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$emailSender = $objectManager->get(\Haerriz\AbandonedCart\Model\EmailSender::class);
$cartRepository = $objectManager->get(\Magento\Quote\Api\CartRepositoryInterface::class);

if ($quoteId <= 0) {
    $quoteId = (int) $connection->fetchOne(
        'SELECT entity_id FROM quote WHERE is_active = 1 AND items_count > 0 ORDER BY updated_at DESC LIMIT 1'
    );
}

if ($quoteId <= 0) {
    fwrite(STDERR, "No active quote with items found. Add products to a cart first.\n");
    exit(1);
}

try {
    $quote = $cartRepository->get($quoteId);
} catch (\Exception $e) {
    fwrite(STDERR, 'Quote not found: ' . $quoteId . "\n");
    exit(1);
}

$candidate = [
    'quote_id' => $quoteId,
    'store_id' => (int) $quote->getStoreId(),
    'email' => $email,
    'firstname' => $name,
    'lastname' => '',
    'items_count' => (int) $quote->getItemsCount(),
];

echo "Sending test abandoned cart email...\n";
echo 'Quote ID: ' . $quoteId . "\n";
echo 'Items: ' . $candidate['items_count'] . "\n";
echo 'To: ' . $email . "\n";

if ($emailSender->send($candidate, false)) {
    echo "SUCCESS: Test email sent to {$email}\n";
    exit(0);
}

fwrite(STDERR, "FAILED: Could not send test email. Check var/log/system.log and SMTP status.\n");
exit(1);
