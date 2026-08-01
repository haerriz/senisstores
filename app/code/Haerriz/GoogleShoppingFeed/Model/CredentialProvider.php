<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\CredentialProviderInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class CredentialProvider implements CredentialProviderInterface
{
    private EncryptorInterface $encryptor;
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        EncryptorInterface $encryptor,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->encryptor = $encryptor;
        $this->scopeConfig = $scopeConfig;
    }

    public function encrypt($secret)
    {
        if ($secret === null || $secret === '') {
            return '';
        }
        return $this->encryptor->encrypt((string)$secret);
    }

    public function decrypt($encryptedSecret)
    {
        if ($encryptedSecret === null || $encryptedSecret === '') {
            return '';
        }
        return $this->encryptor->decrypt((string)$encryptedSecret);
    }

    public function getConfigSecret($path, $scopeType = 'default', $scopeCode = null)
    {
        $encrypted = $this->scopeConfig->getValue($path, $scopeType, $scopeCode);
        if ($encrypted === null || $encrypted === '') {
            return '';
        }
        return $this->encryptor->decrypt((string)$encrypted);
    }

    public function getDecryptedPassword(FeedProfileInterface $profile): string
    {
        return $this->decrypt($profile->getDeliveryPassword());
    }
}
