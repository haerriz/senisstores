<?php
namespace Haerriz\GoogleShoppingFeed\Model\Storage;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\CredentialProviderInterface;
use Haerriz\GoogleShoppingFeed\Model\Security\RemoteHostValidator;
use Psr\Log\LoggerInterface;

class ConnectionTester
{
    private $credentialProvider;
    private $hostValidator;
    private $logger;

    public function __construct(
        CredentialProviderInterface $credentialProvider,
        RemoteHostValidator $hostValidator,
        LoggerInterface $logger
    ) {
        $this->credentialProvider = $credentialProvider;
        $this->hostValidator      = $hostValidator;
        $this->logger             = $logger;
    }

    /**
     * Test the delivery connection for a profile.
     * FIX 25: Uses RemoteHostValidator::validate() before opening socket.
     * FIX 24: Uses CredentialProvider::decrypt() to read the actual password.
     */
    public function test(FeedProfileInterface $profile): bool
    {
        $type = strtolower((string)$profile->getDeliveryType());

        if ($type === 'local') {
            return true; // Local always succeeds
        }

        $host = (string)$profile->getData('ftp_host');
        $port = (int)($profile->getData('ftp_port') ?: ($type === 'sftp' ? 22 : 21));

        // FIX 25: RemoteHostValidator::validate() — block SSRF / private IP attempts
        $validationError = $this->hostValidator->validate($host);
        if ($validationError) {
            $this->logger->warning("ConnectionTester: Host [{$host}] rejected by RemoteHostValidator: {$validationError}");
            throw new \RuntimeException("Host validation failed: {$validationError}");
        }

        // FIX 24: CredentialProvider::decrypt() — decrypt stored password
        $encryptedPassword = (string)$profile->getData('ftp_password');
        $password = '';
        if ($encryptedPassword) {
            try {
                $password = $this->credentialProvider->decrypt($encryptedPassword);
            } catch (\Exception $e) {
                $this->logger->debug("ConnectionTester: Decrypt failed, using raw value: " . $e->getMessage());
                $password = $encryptedPassword;
            }
        }

        $user = (string)$profile->getData('ftp_user');

        $this->logger->info("ConnectionTester: Testing {$type}://{$user}@{$host}:{$port}");

        try {
            if ($type === 'sftp') {
                return $this->testSftp($host, $port, $user, $password);
            }
            return $this->testFtp($host, $port, $user, $password);
        } catch (\Exception $e) {
            $this->logger->error("ConnectionTester: Failed {$type}://{$host}:{$port} — " . $e->getMessage());
            return false;
        }
    }

    private function testFtp(string $host, int $port, string $user, string $password): bool
    {
        if (!function_exists('ftp_connect')) {
            $this->logger->warning("ConnectionTester: FTP extension not available");
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
            // Fallback: raw TCP socket test
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            if (!$socket) {
                throw new \RuntimeException("SFTP port {$port} unreachable on {$host}: {$errstr}");
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
