<?php
namespace Haerriz\GoogleShoppingFeed\Model\Storage;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;

class AdapterPool
{
    private $local;
    private $ftp;
    private $sftp;

    public function __construct(
        Local $local,
        Ftp $ftp,
        Sftp $sftp
    ) {
        $this->local = $local;
        $this->ftp = $ftp;
        $this->sftp = $sftp;
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
        $adapter = $this->get((string)$profile->getDeliveryType());
        return $adapter->deliver($localFilePath, $profile);
    }
}
