<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Block;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Block\Adminhtml\System\Config\SystemInfo;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\State;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;

class SystemInfoTest extends TestCase
{
    public function testGetElementHtmlReturnsFormattedInfo()
    {
        $context = $this->createMock(Context::class);
        $appState = $this->createMock(State::class);
        $directoryList = $this->createMock(DirectoryList::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $connection = $this->createMock(AdapterInterface::class);
        $element = $this->createMock(AbstractElement::class);

        $appState->method('getMode')->willReturn('production');
        $directoryList->method('getRoot')->willReturn('/var/www/html');
        $resourceConnection->method('getConnection')->willReturn($connection);
        $connection->method('fetchOne')->willReturn('2026-07-30 13:13:43');

        $systemInfo = new SystemInfo($context, $appState, $directoryList, $resourceConnection);

        $reflection = new \ReflectionMethod(SystemInfo::class, '_getElementHtml');
        $reflection->setAccessible(true);
        $html = $reflection->invoke($systemInfo, $element);

        $this->assertStringContainsString('System Information', $html);
        $this->assertStringContainsString('Production', $html);
        $this->assertStringContainsString('/var/www/html', $html);
    }
}
