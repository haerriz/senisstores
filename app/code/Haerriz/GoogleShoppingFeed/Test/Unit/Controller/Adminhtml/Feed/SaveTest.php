<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Controller\Adminhtml\Feed;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed\Save;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Message\ManagerInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedProfileFactory;
use Haerriz\GoogleShoppingFeed\Model\FeedProfile;

class SaveTest extends TestCase
{
    protected $controller;
    protected $contextMock;
    protected $requestMock;
    protected $redirectFactoryMock;
    protected $resultRedirectMock;
    protected $messageManagerMock;
    protected $repositoryMock;
    protected $factoryMock;
    protected $modelMock;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->requestMock = $this->createMock(RequestInterface::class);
        $this->redirectFactoryMock = $this->createMock(RedirectFactory::class);
        $this->resultRedirectMock = $this->createMock(Redirect::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->repositoryMock = $this->createMock(FeedProfileRepositoryInterface::class);
        $this->factoryMock = $this->createMock(FeedProfileFactory::class);
        $this->modelMock = $this->createMock(FeedProfile::class);

        $this->contextMock->method('getRequest')->willReturn($this->requestMock);
        $this->contextMock->method('getResultRedirectFactory')->willReturn($this->redirectFactoryMock);
        $this->contextMock->method('getMessageManager')->willReturn($this->messageManagerMock);

        $this->redirectFactoryMock->method('create')->willReturn($this->resultRedirectMock);
        $this->resultRedirectMock->method('setPath')->willReturnSelf();

        $this->controller = new Save(
            $this->contextMock,
            $this->repositoryMock,
            $this->factoryMock
        );
    }

    public function testExecuteWithInvalidFilename()
    {
        $postData = [
            'filename' => '../../etc/passwd.xml'
        ];

        $this->requestMock->method('getPostValue')->willReturn($postData);
        $this->factoryMock->method('create')->willReturn($this->modelMock);
        
        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->stringContains('Invalid filename extension.'));

        $this->controller->execute();
    }
}
