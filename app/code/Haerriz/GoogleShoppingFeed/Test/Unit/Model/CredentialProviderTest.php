<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model;

use Haerriz\GoogleShoppingFeed\Model\CredentialProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\TestCase;

class CredentialProviderTest extends TestCase
{
    public function testConfigSecretIsDecryptedOnlyWhenRequested()
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('vendor/module/secret', 'default', null)
            ->willReturn('encrypted-value');
        $encryptor->expects($this->once())
            ->method('decrypt')
            ->with('encrypted-value')
            ->willReturn('plain-value');

        $provider = new CredentialProvider($encryptor, $scopeConfig);

        $this->assertSame('plain-value', $provider->getConfigSecret('vendor/module/secret'));
    }

    public function testEmptySecretDoesNotCallDecrypt()
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $encryptor->expects($this->never())->method('decrypt');

        $provider = new CredentialProvider($encryptor, $scopeConfig);

        $this->assertSame('', $provider->decrypt(null));
    }
}
