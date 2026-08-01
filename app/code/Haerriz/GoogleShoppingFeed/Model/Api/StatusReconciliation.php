<?php
namespace Haerriz\GoogleShoppingFeed\Model\Api;

use Haerriz\GoogleShoppingFeed\Model\Config;
use Haerriz\GoogleShoppingFeed\Model\FeedRemoteStateRepository;
use Psr\Log\LoggerInterface;

class StatusReconciliation
{
    private MerchantClientV1 $merchantClient;
    private Config $config;
    private FeedRemoteStateRepository $remoteStateRepo;
    private LoggerInterface $logger;

    public function __construct(
        MerchantClientV1 $merchantClient,
        Config $config,
        FeedRemoteStateRepository $remoteStateRepo,
        LoggerInterface $logger
    ) {
        $this->merchantClient = $merchantClient;
        $this->config = $config;
        $this->remoteStateRepo = $remoteStateRepo;
        $this->logger = $logger;
    }

    public function reconcile(int $storeId = 0): array
    {
        $merchantId = trim((string)$this->config->getMerchantId($storeId));
        if ($merchantId === '') {
            return [
                'reconciled' => false,
                'reason' => 'no_merchant_id',
                'message' => 'Google Merchant Account ID is not configured.',
            ];
        }

        $reconciled = 0;
        $statuses = [];

        try {
            $remoteProducts = $this->merchantClient->listProducts($merchantId);

            foreach ($remoteProducts as $remoteProduct) {
                if (!is_array($remoteProduct)) {
                    continue;
                }
                $sku = (string)($remoteProduct['offerId'] ?? $remoteProduct['offer_id'] ?? '');
                if ($sku === '') {
                    continue;
                }

                $status = $this->normalizeStatus($remoteProduct);
                $issues = $remoteProduct['itemLevelIssues'] ?? $remoteProduct['item_level_issues'] ?? [];
                if (!is_array($issues)) {
                    $issues = [];
                }

                try {
                    $state = $this->remoteStateRepo->getByOfferIdAndProfile($sku, null);
                    $state->setProfileId(null);
                    $state->setRemoteStatus($status);
                    $state->setIssues(json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $state->setSyncedAt(date('Y-m-d H:i:s'));
                    $this->remoteStateRepo->save($state);
                    $reconciled++;
                } catch (\Throwable $innerEx) {
                    $this->logger->debug(
                        "StatusReconciliation: SKU [{$sku}] state update failed: " . $innerEx->getMessage()
                    );
                }

                $statuses[$sku] = $status;
            }
        } catch (\Throwable $e) {
            $this->logger->error('StatusReconciliation::reconcile failed: ' . $e->getMessage());
            return [
                'reconciled' => false,
                'error' => $this->formatAdminError($e),
                'reason' => $this->classifyReason($e),
            ];
        }

        return [
            'reconciled' => true,
            'count' => $reconciled,
            'statuses' => $statuses,
            'message' => __('Updated %1 merchant offer statuses.', $reconciled)->render(),
        ];
    }

    /**
     * @param array<string, mixed> $remoteProduct
     */
    private function normalizeStatus(array $remoteProduct): string
    {
        $issues = $remoteProduct['itemLevelIssues'] ?? $remoteProduct['item_level_issues'] ?? [];
        $destinationStatuses = $remoteProduct['destinationStatuses'] ?? null;
        $destinationStatus = '';
        if (is_array($destinationStatuses) && isset($destinationStatuses[0]) && is_array($destinationStatuses[0])) {
            $destinationStatus = (string)($destinationStatuses[0]['status'] ?? '');
        }

        $raw = strtolower((string)(
            $remoteProduct['status']
            ?? $remoteProduct['approvalStatus']
            ?? $destinationStatus
            ?? ''
        ));

        if (str_contains($raw, 'disapproved') || str_contains($raw, 'rejected')) {
            return 'disapproved';
        }
        if (str_contains($raw, 'approved') || str_contains($raw, 'eligible') || str_contains($raw, 'serving')) {
            return 'approved';
        }
        if (str_contains($raw, 'pending') || str_contains($raw, 'review')) {
            return 'pending';
        }
        if (is_array($issues) && count($issues) > 0) {
            return 'disapproved';
        }

        return 'pending';
    }

    private function classifyReason(\Throwable $e): string
    {
        $msg = strtolower($e->getMessage());
        if (str_contains($msg, 'array_key_exists') || str_contains($msg, 'service account json')) {
            return 'invalid_credentials';
        }
        if (str_contains($msg, 'permission') || str_contains($msg, 'unauthorized') || str_contains($msg, 'does not have access')) {
            return 'permission_denied';
        }
        if (str_contains($msg, 'not configured')) {
            return 'missing_credentials';
        }
        return 'api_error';
    }

    private function formatAdminError(\Throwable $e): string
    {
        $msg = trim($e->getMessage());

        if (str_contains($msg, 'array_key_exists')) {
            return 'Service account JSON is missing or invalid (Magento could not parse client_email/private_key). '
                . 'Re-paste the full JSON key in Stores > Configuration > Google Merchant API and Save.';
        }

        $decoded = json_decode($msg, true);
        if (is_array($decoded)) {
            $apiMessage = (string)($decoded['message'] ?? '');
            $reason = (string)($decoded['reason'] ?? ($decoded['errorInfoMetadata']['REASON'] ?? ''));
            if ($apiMessage !== '') {
                if (str_contains(strtolower($apiMessage), 'does not have access')
                    || str_contains(strtolower($reason), 'permission')
                    || str_contains(strtolower($reason), 'unauthorized')
                ) {
                    return $apiMessage . ' Add the service account email as a user in Google Merchant Center '
                        . '(Settings → Users & access), then try Reconcile again.';
                }
                return $apiMessage;
            }
        }

        if (str_contains(strtolower($msg), 'does not have access')
            || str_contains(strtolower($msg), 'permission_denied')
            || str_contains(strtolower($msg), 'unauthorized')
        ) {
            return 'The service account is not authorized for this Merchant Center account. '
                . 'In Merchant Center → Settings → Users & access, add the JSON client_email '
                . 'with Standard/Admin access, then retry.';
        }

        return $msg;
    }
}
