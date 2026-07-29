<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\FeedProfile;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\FeedProfile\DataProvider;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile\CollectionFactory;
use Haerriz\GoogleShoppingFeed\Model\ResourceModel\FeedProfile\Collection;
use Haerriz\GoogleShoppingFeed\Model\FeedProfile;

class DataProviderTest extends TestCase
{
    public function testGetDataDeserializesMapping()
    {
        $collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $collectionMock = $this->createMock(Collection::class);
        
        $collectionFactoryMock->method('create')->willReturn($collectionMock);
        
        $modelMock = $this->createMock(FeedProfile::class);
        $modelMock->method('getId')->willReturn(1);
        $modelMock->method('getData')->willReturn([
            'profile_id' => 1,
            'attributes_mapping_serialized' => json_encode([['google_attribute' => 'g:id', 'magento_attribute' => 'sku']])
        ]);
        
        $collectionMock->method('getItems')->willReturn([$modelMock]);
        
        $dataProvider = new DataProvider('name', 'primary', 'request', $collectionFactoryMock);
        $data = $dataProvider->getData();
        
        $this->assertArrayHasKey(1, $data);
        $this->assertArrayHasKey('attributes_mapping', $data[1]);
        $this->assertEquals('g:id', $data[1]['attributes_mapping'][0]['google_attribute']);
    }
}
