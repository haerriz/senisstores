<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model;

use Haerriz\AgenticCommerce\Model\AttributeMetadataService;
use Haerriz\AgenticCommerce\Model\FilterNormalizer;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class FilterNormalizerTest extends TestCase
{
    public function testNormalizesOptionLabelToValue(): void
    {
        $metadata = $this->createMock(AttributeMetadataService::class);
        $metadata->method('getByCode')->with('color', 1)->willReturn([
            'label' => 'Color', 'frontend_input' => 'select', 'is_filterable' => true,
            'is_filterable_in_search' => true, 'is_searchable' => true,
        ]);
        $metadata->method('resolveOption')->with('color', 'Black', 1)->willReturn('49');
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $subject = new FilterNormalizer($metadata, $stores);
        $result = $subject->normalize([['attribute' => 'color', 'condition' => 'eq', 'values' => ['Black']]]);
        self::assertSame('49', $result[0]['values'][0]);
    }

    public function testRejectsUnknownCustomAttribute(): void
    {
        $metadata = $this->createMock(AttributeMetadataService::class);
        $metadata->method('getByCode')->willReturn(null);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $subject = new FilterNormalizer($metadata, $stores);
        self::assertSame([], $subject->normalize([['attribute' => 'evil_sql', 'values' => ['x']]]));
    }
}
