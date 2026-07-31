<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\FeedProfile;

class FeedProfileTest extends TestCase
{
    /**
     * @var FeedProfile
     */
    protected $model;

    protected function setUp(): void
    {
        $this->model = $this->getMockBuilder(FeedProfile::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    public function testGetSetId()
    {
        $this->model->setId(1);
        $this->assertEquals(1, $this->model->getId());
    }

    public function testGetSetName()
    {
        $name = 'Google XML Feed';
        $this->model->setName($name);
        $this->assertEquals($name, $this->model->getName());
    }

    public function testGetSetStatus()
    {
        $this->model->setStatus(1);
        $this->assertEquals(1, $this->model->getStatus());
    }

    public function testGetSetFilename()
    {
        $filename = 'google_shopping.xml';
        $this->model->setFilename($filename);
        $this->assertEquals($filename, $this->model->getFilename());
    }

    public function testGetSetFeedType()
    {
        $type = 'xml';
        $this->model->setFeedType($type);
        $this->assertEquals($type, $this->model->getFeedType());
    }
}
