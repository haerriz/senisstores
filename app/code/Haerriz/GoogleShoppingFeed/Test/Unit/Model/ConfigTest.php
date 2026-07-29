<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigTest extends TestCase
{
    protected $scopeConfigMock;
    protected $encryptorMock;
    protected $config;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->encryptorMock = $this->createMock(EncryptorInterface::class);

        $this->config = new Config(
            $this->scopeConfigMock,
            $this->encryptorMock
        );
    }

    public function testIsEnabled()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled());
    }

    public function testGetMerchantId()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_MERCHANT_ID, ScopeInterface::SCOPE_STORE, null)
            ->willReturn('123456789');

        $this->assertEquals('123456789', $this->config->getMerchantId());
    }

    public function testGetServiceAccountJson()
    {
        $encryptedVal = 'encrypted_string';
        $decryptedVal = '{"type": "service_account"}';

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(Config::XML_PATH_SERVICE_ACCOUNT_JSON, ScopeInterface::SCOPE_STORE, null)
            ->willReturn($encryptedVal);

        $this->encryptorMock->expects($this->once())
            ->method('decrypt')
            ->with($encryptedVal)
            ->willReturn($decryptedVal);

        $this->assertEquals($decryptedVal, $this->config->getServiceAccountJson());
    }

    public function testIsDebugLoggingEnabled()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(Config::XML_PATH_DEBUG_LOGGING, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->config->isDebugLoggingEnabled());
    }
}
