<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Api;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Api\MerchantClientV1;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Psr\Log\LoggerInterface;

class MerchantClientV1Test extends TestCase
{
    protected $clientV1;
    protected $scopeConfigMock;
    protected $encryptorMock;
    protected $loggerMock;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->encryptorMock = $this->createMock(EncryptorInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->clientV1 = new MerchantClientV1(
            $this->scopeConfigMock,
            $this->encryptorMock,
            $this->loggerMock
        );
    }

    public function testGetMerchantId()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(MerchantClientV1::XML_PATH_MERCHANT_ID)
            ->willReturn('123456');

        $this->assertEquals('123456', $this->clientV1->getMerchantId());
    }

    public function testGetClientThrowsExceptionWhenNoCredentials()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(MerchantClientV1::XML_PATH_SERVICE_ACCOUNT_JSON)
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->clientV1->getProductsClient();
    }
}
