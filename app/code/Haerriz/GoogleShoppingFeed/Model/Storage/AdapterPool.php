<?php
namespace Haerriz\GoogleShoppingFeed\Model\Storage;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Psr\Log\LoggerInterface;

class AdapterPool
{
    private const MAX_ATTEMPTS = 3;

    private $local;
    private $ftp;
    private $sftp;
    private LoggerInterface $logger;

    public function __construct(
        Local $local,
        Ftp $ftp,
        Sftp $sftp,
        LoggerInterface $logger
    ) {
        $this->local = $local;
        $this->ftp = $ftp;
        $this->sftp = $sftp;
        $this->logger = $logger;
    }

    /**
     * Resolve the correct delivery adapter for a profile's delivery type.
     */
    public function get(string $deliveryType): AdapterInterface
    {
        switch (strtolower(trim($deliveryType))) {
            case 'ftp':
                return $this->ftp;
            case 'sftp':
                return $this->sftp;
            case 'local':
            default:
                return $this->local;
        }
    }

    /**
     * Deliver a generated feed file for a given profile.
     */
    public function deliver(FeedProfileInterface $profile, string $localFilePath): bool
    {
        $deliveryType = strtolower(trim((string)$profile->getDeliveryType()));
        $adapter = $this->get($deliveryType);
        $attempts = in_array($deliveryType, ['ftp', 'sftp'], true) ? self::MAX_ATTEMPTS : 1;
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                if ((bool)$adapter->upload($profile, $localFilePath)) {
                    return true;
                }
                throw new \RuntimeException('Adapter returned an unsuccessful upload result.');
            } catch (\Throwable $exception) {
                $lastException = $exception;
                if ($attempt >= $attempts) {
                    break;
                }
                $this->logger->warning(sprintf(
                    'Delivery attempt %d/%d failed for profile #%d via %s: %s',
                    $attempt,
                    $attempts,
                    (int)$profile->getId(),
                    $deliveryType ?: 'local',
                    $exception->getMessage()
                ));
                usleep(250000 * $attempt);
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Delivery failed after %d attempt(s) via %s: %s',
                $attempts,
                $deliveryType ?: 'local',
                $lastException ? $lastException->getMessage() : 'unknown error'
            ),
            0,
            $lastException
        );
    }
}
