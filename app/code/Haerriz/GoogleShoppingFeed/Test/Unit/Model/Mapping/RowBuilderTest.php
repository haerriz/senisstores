<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Mapping;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Mapping\RowBuilder;

class RowBuilderTest extends TestCase
{
    public function testGetMappingsReturnsDefaultArray()
    {
        $valueResolver = $this->createMock(\Haerriz\GoogleShoppingFeed\Api\ProductValueResolverInterface::class);
        $modifierPipeline = $this->createMock(\Haerriz\GoogleShoppingFeed\Api\ModifierPipelineInterface::class);
        $configReader = $this->createMock(\Haerriz\GoogleShoppingFeed\Model\ProfileConfigReader::class);
        $presetRegistry = new \Haerriz\GoogleShoppingFeed\Model\Template\PresetRegistry();

        $rowBuilder = new RowBuilder($valueResolver, $modifierPipeline, $configReader, $presetRegistry);
        $profile = $this->createMock(\Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface::class);
        $profile->method('getAttributesMappingSerialized')->willReturn('');
        $profile->method('getFeedType')->willReturn('google_shopping_v1');

        $mappings = $rowBuilder->getMappings($profile);
        $this->assertNotEmpty($mappings);
        $this->assertEquals('g:id', $mappings[0]['google_attribute']);
    }
}
