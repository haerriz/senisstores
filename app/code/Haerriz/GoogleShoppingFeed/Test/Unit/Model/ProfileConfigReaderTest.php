<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Model\FeedProfile;
use Haerriz\GoogleShoppingFeed\Model\ProfileConfigReader;
use PHPUnit\Framework\TestCase;

class ProfileConfigReaderTest extends TestCase
{
    public function testReadsAndNormalizesConcreteProfileValues()
    {
        $profile = $this->getMockBuilder(FeedProfile::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $profile->setData('enabled_flag', '1');
        $profile->setData('category_ids', '3, 5,3,invalid');

        $reader = new ProfileConfigReader();

        $this->assertTrue($reader->getBoolean($profile, 'enabled_flag'));
        $this->assertSame([3, 5], $reader->getIntList($profile, 'category_ids'));
    }

    public function testReturnsDefaultsForNonConcreteProfileImplementation()
    {
        $profile = $this->createMock(FeedProfileInterface::class);
        $reader = new ProfileConfigReader();

        $this->assertSame('fallback', $reader->get($profile, 'unknown', 'fallback'));
        $this->assertFalse($reader->getBoolean($profile, 'unknown'));
        $this->assertSame([], $reader->getIntList($profile, 'unknown'));
    }
}
