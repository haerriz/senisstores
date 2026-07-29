<?php
namespace Haerriz\GoogleShoppingFeed\Model\Storage;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\Sftp as SftpIo;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;

class Sftp implements AdapterInterface
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var SftpIo
     */
    protected $sftpIo;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @param Filesystem $filesystem
     * @param SftpIo $sftpIo
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Filesystem $filesystem,
        SftpIo $sftpIo,
        EncryptorInterface $encryptor
    ) {
        $this->filesystem = $filesystem;
        $this->sftpIo = $sftpIo;
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
                'port' => $profile->getDeliveryPort() ?: 22,
                'username' => $profile->getDeliveryUsername(),
                'password' => $decryptedPassword,
                'timeout' => 20
            ];

            $this->sftpIo->open($config);

            $remoteDir = $profile->getDeliveryPath();
            if ($remoteDir) {
                $this->sftpIo->cd($remoteDir);
            }

            $filename = basename($localFilePath);
            // Magento SFTP Io write takes ($filename, $source, $dest = null) but wait,
            // SftpIo has write($filename, $source) or similar.
            $result = $this->sftpIo->write($filename, $absoluteLocalPath);

            $this->sftpIo->close();

            if (!$result) {
                throw new LocalizedException(__('SFTP transfer failed for %1', $filename));
            }

            return true;
        } catch (\Exception $e) {
            throw new LocalizedException(__('SFTP Upload Error: %1', $e->getMessage()), $e);
        }
    }
}
