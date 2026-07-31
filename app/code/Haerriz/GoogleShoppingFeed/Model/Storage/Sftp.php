<?php
namespace Haerriz\GoogleShoppingFeed\Model\Storage;

use Haerriz\GoogleShoppingFeed\Api\CredentialProviderInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP as SftpClient;

class Sftp implements AdapterInterface
{
    private $filesystem;
    private $credentialProvider;

    public function __construct(Filesystem $filesystem, CredentialProviderInterface $credentialProvider)
    {
        $this->filesystem = $filesystem;
        $this->credentialProvider = $credentialProvider;
    }

    public function upload(FeedProfileInterface $profile, $localFilePath)
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        if (!$directory->isReadable($localFilePath)) {
            throw new LocalizedException(__('The local feed artifact is unavailable.'));
        }
        $client = null;
        try {
            $client = $this->connect($profile);
            $remoteDirectory = (string)$profile->getDeliveryPath();
            if ($remoteDirectory !== '' && !$client->chdir($remoteDirectory)) {
                throw new \RuntimeException('Unable to enter the configured remote directory.');
            }
            $filename = basename((string)($profile->getData('remote_filename') ?: $localFilePath));
            $temporary = '.' . $filename . '.' . bin2hex(random_bytes(8)) . '.tmp';
            $source = $directory->getAbsolutePath($localFilePath);
            if (!$client->put($temporary, $source, SftpClient::SOURCE_LOCAL_FILE)
                || !$client->rename($temporary, $filename)
            ) {
                $client->delete($temporary);
                throw new \RuntimeException('Atomic SFTP publication failed.');
            }
            return true;
        } catch (\Throwable $exception) {
            throw new LocalizedException(__('SFTP upload failed. Verify the secure connection settings.'), $exception);
        } finally {
            if ($client) {
                $client->disconnect();
            }
        }
    }

    public function testConnection(FeedProfileInterface $profile)
    {
        $client = $this->connect($profile);
        try {
            $path = (string)$profile->getDeliveryPath();
            return $path === '' || $client->chdir($path);
        } finally {
            $client->disconnect();
        }
    }

    private function connect(FeedProfileInterface $profile)
    {
        $client = new SftpClient(
            (string)$profile->getDeliveryHost(),
            (int)($profile->getDeliveryPort() ?: 22),
            max(1, (int)$profile->getData('delivery_timeout'))
        );
        $hostKey = $client->getServerPublicHostKey();
        if (!$hostKey) {
            throw new \RuntimeException('The SFTP host did not provide a verifiable public key.');
        }
        $this->verifyFingerprint($hostKey, (string)$profile->getData('sftp_fingerprint'));

        $credential = $this->credentialProvider->decrypt($profile->getDeliveryPassword());
        $privateKey = $this->credentialProvider->decrypt($profile->getData('delivery_private_key'));
        if ($privateKey !== '') {
            $passphrase = $this->credentialProvider->decrypt($profile->getData('delivery_key_passphrase'));
            $credential = PublicKeyLoader::load($privateKey, $passphrase ?: false);
        }
        if (!$client->login((string)$profile->getDeliveryUsername(), $credential)) {
            throw new \RuntimeException('SFTP authentication failed.');
        }
        return $client;
    }

    private function verifyFingerprint($openSshKey, $configured)
    {
        if (trim($configured) === '') {
            throw new \InvalidArgumentException('An SFTP host fingerprint is required.');
        }
        $parts = explode(' ', trim($openSshKey), 2);
        $blob = isset($parts[1]) ? base64_decode($parts[1], true) : false;
        if ($blob === false) {
            throw new \RuntimeException('Unable to decode the SFTP host key.');
        }
        $sha256 = 'SHA256:' . rtrim(base64_encode(hash('sha256', $blob, true)), '=');
        $md5 = 'MD5:' . implode(':', str_split(md5($blob), 2));
        if (!hash_equals($sha256, trim($configured)) && !hash_equals($md5, trim($configured))) {
            throw new \RuntimeException('SFTP host fingerprint mismatch.');
        }
    }
}
