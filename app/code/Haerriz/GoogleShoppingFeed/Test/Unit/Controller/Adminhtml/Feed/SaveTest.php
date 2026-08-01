<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Controller\Adminhtml\Feed;

use Haerriz\GoogleShoppingFeed\Api\CredentialProviderInterface;
use Haerriz\GoogleShoppingFeed\Api\FeedProfileRepositoryInterface;
use Haerriz\GoogleShoppingFeed\Controller\Adminhtml\Feed\Save;
use Haerriz\GoogleShoppingFeed\Model\FeedProfile;
use Haerriz\GoogleShoppingFeed\Model\ProfileValidator;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    private $controller;
    private $requestMock;
    private $resultRedirectMock;
    private $messageManagerMock;
    private $repositoryMock;
    private $validatorMock;
    private $credentialProviderMock;
    private $modelMock;

    protected function setUp(): void
    {
        $contextMock = $this->createMock(Context::class);
        $this->requestMock = $this->createMock(Http::class);
        $redirectFactoryMock = $this->createMock(RedirectFactory::class);
        $this->resultRedirectMock = $this->createMock(Redirect::class);
        $this->messageManagerMock = $this->createMock(ManagerInterface::class);
        $this->repositoryMock = $this->createMock(FeedProfileRepositoryInterface::class);
        $this->validatorMock = $this->createMock(ProfileValidator::class);
        $this->credentialProviderMock = $this->createMock(CredentialProviderInterface::class);
        $this->modelMock = $this->createMock(FeedProfile::class);

        $contextMock->method('getRequest')->willReturn($this->requestMock);
        $contextMock->method('getResultRedirectFactory')->willReturn($redirectFactoryMock);
        $contextMock->method('getMessageManager')->willReturn($this->messageManagerMock);
        $redirectFactoryMock->method('create')->willReturn($this->resultRedirectMock);
        $this->resultRedirectMock->method('setPath')->willReturnSelf();
        $this->resultRedirectMock->method('setUrl')->willReturnSelf();

        $this->controller = new Save(
            $contextMock,
            $this->repositoryMock,
            $this->validatorMock,
            $this->credentialProviderMock
        );
    }

    public function testExecuteWithInvalidFilename()
    {
        $postData = [
            'name' => 'Test',
            'feed_type' => 'google_shopping',
            'filename' => '../../etc/passwd.xml',
        ];

        $this->requestMock->method('getPostValue')->willReturn($postData);
        $this->repositoryMock->method('create')->willReturn($this->modelMock);
        $this->modelMock->method('setData')->willReturnSelf();
        $this->modelMock->method('getName')->willReturn('Test');
        $this->validatorMock->method('validate')->willReturn(['Filename must end in .xml, .csv, .jsonl, .txt, or .tsv.']);

        $this->messageManagerMock->expects($this->once())
            ->method('addErrorMessage')
            ->with($this->stringContains('Filename must end'));

        $this->repositoryMock->expects($this->never())->method('save');

        $this->controller->execute();
    }

    public function testExecuteEncryptsDeliveryPassword()
    {
        $postData = [
            'name' => 'Secure Feed',
            'feed_type' => 'google_shopping',
            'filename' => 'google.xml',
            'delivery_password' => 'super-secret',
        ];

        $this->requestMock->method('getPostValue')->willReturn($postData);
        $this->repositoryMock->method('create')->willReturn($this->modelMock);
        $this->modelMock->method('setData')->willReturnSelf();
        $this->modelMock->method('setDeliveryPassword')->willReturnSelf();
        $this->modelMock->method('getName')->willReturn('Secure Feed');
        $this->modelMock->method('getId')->willReturn(11);
        $this->validatorMock->method('validate')->willReturn([]);
        $this->credentialProviderMock->expects($this->once())
            ->method('encrypt')
            ->with('super-secret')
            ->willReturn('encrypted-secret');
        $this->modelMock->expects($this->once())
            ->method('setDeliveryPassword')
            ->with('encrypted-secret');
        $this->repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->modelMock)
            ->willReturn($this->modelMock);
        $this->messageManagerMock->expects($this->once())->method('addSuccessMessage');

        $this->controller->execute();
    }
}
