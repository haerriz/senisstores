<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Rule;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\CatalogRule\Model\Rule\Condition\CombineFactory as ConditionCombineFactory;
use Magento\CatalogRule\Model\Rule\Condition\Combine;
use Magento\Rule\Model\Action\CollectionFactory as ActionCollectionFactory;
use Magento\Rule\Model\Action\Collection as ActionCollection;

class RuleTest extends TestCase
{
    protected $contextMock;
    protected $registryMock;
    protected $formFactoryMock;
    protected $timezoneMock;
    protected $combineFactoryMock;
    protected $actionFactoryMock;
    protected $rule;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->registryMock = $this->createMock(Registry::class);
        $this->formFactoryMock = $this->createMock(FormFactory::class);
        $this->timezoneMock = $this->createMock(TimezoneInterface::class);
        $this->combineFactoryMock = $this->createMock(ConditionCombineFactory::class);
        $this->actionFactoryMock = $this->createMock(ActionCollectionFactory::class);

        $this->rule = new Rule(
            $this->contextMock,
            $this->registryMock,
            $this->formFactoryMock,
            $this->timezoneMock,
            $this->combineFactoryMock,
            $this->actionFactoryMock
        );
    }

    public function testGetConditionsInstance()
    {
        $combineMock = $this->createMock(Combine::class);
        $this->combineFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($combineMock);

        $this->assertSame($combineMock, $this->rule->getConditionsInstance());
    }

    public function testGetActionsInstance()
    {
        $actionMock = $this->createMock(ActionCollection::class);
        $this->actionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($actionMock);

        $this->assertSame($actionMock, $this->rule->getActionsInstance());
    }
}
