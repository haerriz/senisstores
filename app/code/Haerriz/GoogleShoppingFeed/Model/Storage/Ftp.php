<?php
namespace Haerriz\GoogleShoppingFeed\Model\Storage;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\CredentialProviderInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\Ftp as FtpIo;
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
     * @var CredentialProviderInterface
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
        CredentialProviderInterface $credentialProvider
    ) {
        $this->filesystem = $filesystem;
        $this->ftpIo = $ftpIo;
        $this->encryptor = $credentialProvider;
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
                'passive' => (bool)$profile->getData('ftp_passive'),
                'timeout' => max(1, (int)$profile->getData('delivery_timeout'))
            ];

            if (!$this->ftpIo->open($config)) {
                throw new LocalizedException(__('Failed to open FTP connection to %1', $profile->getDeliveryHost()));
            }

            $remoteDir = $profile->getDeliveryPath();
            if ($remoteDir) {
                $this->ftpIo->cd($remoteDir);
            }

            $filename = basename((string)($profile->getData('remote_filename') ?: $localFilePath));
            $temporary = '.' . $filename . '.' . bin2hex(random_bytes(8)) . '.tmp';
            $result = $this->ftpIo->write($temporary, $absoluteLocalPath);
            if ($result) {
                $result = $this->ftpIo->mv($temporary, $filename);
            }

            $this->ftpIo->close();

            if (!$result) {
                throw new LocalizedException(__('FTP transfer failed for %1', $filename));
            }

            return true;
        } catch (\Exception $e) {
            throw new LocalizedException(__('FTP upload failed. Verify the connection settings.'), $e);
        }
    }

    public function testConnection(FeedProfileInterface $profile)
    {
        $password = $this->encryptor->decrypt($profile->getDeliveryPassword());
        $opened = false;
        try {
            $this->ftpIo->open([
                'host' => $profile->getDeliveryHost(),
                'port' => $profile->getDeliveryPort() ?: 21,
                'user' => $profile->getDeliveryUsername(),
                'password' => $password,
                'passive' => (bool)$profile->getData('ftp_passive'),
                'timeout' => max(1, (int)$profile->getData('delivery_timeout')),
            ]);
            $opened = true;
            $path = (string)$profile->getDeliveryPath();
            return $path === '' || (bool)$this->ftpIo->cd($path);
        } finally {
            if ($opened) {
                $this->ftpIo->close();
            }
        }
    }
}
