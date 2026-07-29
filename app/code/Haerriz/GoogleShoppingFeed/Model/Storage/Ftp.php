<?php
namespace Haerriz\GoogleShoppingFeed\Model\Storage;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\Ftp as FtpIo;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;

class Ftp implements AdapterInterface
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var FtpIo
     */
    protected $ftpIo;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @param Filesystem $filesystem
     * @param FtpIo $ftpIo
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Filesystem $filesystem,
        FtpIo $ftpIo,
        EncryptorInterface $encryptor
    ) {
        $this->filesystem = $filesystem;
        $this->ftpIo = $ftpIo;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritdoc
     */
    public function upload(FeedProfileInterface $profile, $localFilePath)
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $absoluteLocalPath = $directory->getAbsolutePath($localFilePath);

        if (!$directory->isReadable($localFilePath)) {
            throw new LocalizedException(__('Local feed file is missing or not readable at: %1', $localFilePath));
        }

        try {
            $password = $profile->getDeliveryPassword();
            $decryptedPassword = $password ? $this->encryptor->decrypt($password) : '';

            $config = [
                'host' => $profile->getDeliveryHost(),
                'port' => $profile->getDeliveryPort() ?: 21,
                'user' => $profile->getDeliveryUsername(),
                'password' => $decryptedPassword,
                'pasv' => true
            ];

            if (!$this->ftpIo->open($config)) {
                throw new LocalizedException(__('Failed to open FTP connection to %1', $profile->getDeliveryHost()));
            }

            $remoteDir = $profile->getDeliveryPath();
            if ($remoteDir) {
                $this->ftpIo->cd($remoteDir);
            }

            $filename = basename($localFilePath);
            $result = $this->ftpIo->write($filename, $absoluteLocalPath);

            $this->ftpIo->close();

            if (!$result) {
                throw new LocalizedException(__('FTP transfer failed for %1', $filename));
            }

            return true;
        } catch (\Exception $e) {
            throw new LocalizedException(__('FTP Upload Error: %1', $e->getMessage()), $e);
        }
    }
}
