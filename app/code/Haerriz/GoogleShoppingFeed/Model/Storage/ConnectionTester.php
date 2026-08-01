<?php
namespace Haerriz\GoogleShoppingFeed\Model\Storage;

use Haerriz\GoogleShoppingFeed\Api\CredentialProviderInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Model\Security\RemoteHostValidator;
use Psr\Log\LoggerInterface;

class ConnectionTester
{
    private CredentialProviderInterface $credentialProvider;
    private RemoteHostValidator $hostValidator;
    private LoggerInterface $logger;

    public function __construct(
        CredentialProviderInterface $credentialProvider,
        RemoteHostValidator $hostValidator,
        LoggerInterface $logger
    ) {
        $this->credentialProvider = $credentialProvider;
        $this->hostValidator = $hostValidator;
        $this->logger = $logger;
    }

    public function test(FeedProfileInterface $profile): bool
    {
        $type = strtolower((string)$profile->getDeliveryType());

        if ($type === 'local' || $type === '') {
            return true;
        }

        $host = (string)($profile->getDeliveryHost() ?: $profile->getData('ftp_host'));
        $port = (int)($profile->getDeliveryPort()
            ?: $profile->getData('ftp_port')
            ?: ($type === 'sftp' ? 22 : 21));

        $validationError = $this->hostValidator->validate($host);
        if ($validationError) {
            $this->logger->warning("ConnectionTester: Host [{$host}] rejected: {$validationError}");
            throw new \RuntimeException("Host validation failed: {$validationError}");
        }

        $encryptedPassword = (string)($profile->getDeliveryPassword() ?: $profile->getData('ftp_password'));
        $password = $encryptedPassword ? $this->credentialProvider->decrypt($encryptedPassword) : '';
        $user = (string)($profile->getDeliveryUsername() ?: $profile->getData('ftp_user'));

        $this->logger->info("ConnectionTester: Testing {$type}://{$user}@{$host}:{$port}");

        try {
            if ($type === 'sftp') {
                return $this->testSftp($host, $port, $user, $password);
            }
            return $this->testFtp($host, $port, $user, $password);
        } catch (\Exception $e) {
            $this->logger->error("ConnectionTester: Failed {$type}://{$host}:{$port} - " . $e->getMessage());
            return false;
        }
    }

    private function testFtp(string $host, int $port, string $user, string $password): bool
    {
        if (!function_exists('ftp_connect')) {
            $this->logger->warning('ConnectionTester: FTP extension not available');
            return false;
        }

        $conn = @ftp_connect($host, $port, 10);
        if (!$conn) {
            throw new \RuntimeException("FTP connect failed to {$host}:{$port}");
        }

        $login = @ftp_login($conn, $user, $password);
        @ftp_close($conn);
        if (!$login) {
            throw new \RuntimeException("FTP login failed for user {$user}");
        }

        $this->logger->info("ConnectionTester: FTP test SUCCESS {$host}:{$port}");
        return true;
    }

    private function testSftp(string $host, int $port, string $user, string $password): bool
    {
        if (!function_exists('ssh2_connect')) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            if (!$socket) {
                throw new \RuntimeException("SFTP port {$port} unreachable on {$host}");
            }
            fclose($socket);
            $this->logger->info("ConnectionTester: SFTP socket test SUCCESS {$host}:{$port} (no ssh2 ext)");
            return true;
        }

        $conn = @ssh2_connect($host, $port);
        if (!$conn) {
            throw new \RuntimeException("SSH2 connect failed to {$host}:{$port}");
        }

        $auth = @ssh2_auth_password($conn, $user, $password);
        if (!$auth) {
            throw new \RuntimeException("SFTP auth failed for user {$user}");
        }

        $this->logger->info("ConnectionTester: SFTP test SUCCESS {$host}:{$port}");
        return true;
    }
}
