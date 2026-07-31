<?php
namespace Haerriz\GoogleShoppingFeed\Model;

use Haerriz\GoogleShoppingFeed\Api\CredentialProviderInterface;
use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CredentialProvider implements CredentialProviderInterface
{
    private $encryptor;
    private $scopeConfig;

    public function __construct(
        EncryptorInterface $encryptor,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->encryptor = $encryptor;
        $this->scopeConfig = $scopeConfig;
    }

    public function getDecryptedPassword(FeedProfileInterface $profile): string
    {
        $encrypted = $profile->getDeliveryPassword();
        if (!$encrypted) {
            return '';
        }
        return $this->encryptor->decrypt($encrypted);
    }

    public function encrypt($secret)
    {
        if (empty($secret)) {
            return '';
        }
        return $this->encryptor->encrypt($secret);
    }

    public function decrypt($encryptedSecret)
    {
        if (empty($encryptedSecret)) {
            return '';
        }
        return $this->encryptor->decrypt($encryptedSecret);
    }

    public function getConfigSecret($path, $scopeType = \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $scopeCode = null)
    {
        $value = $this->scopeConfig->getValue($path, $scopeType, $scopeCode);
        if (empty($value)) {
            return '';
        }
        // Config secrets might not be encrypted in standard getValue if there's no backend model,
        // but if they are encrypted by the backend model, getValue might return the encrypted string
        // or the decrypted string based on Magento version/config. 
        // We will try decrypting it.
        return $this->encryptor->decrypt($value);
    }
}
