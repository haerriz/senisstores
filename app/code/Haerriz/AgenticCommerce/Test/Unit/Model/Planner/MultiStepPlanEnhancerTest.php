<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Planner;

use Haerriz\AgenticCommerce\Model\Config;
use Haerriz\AgenticCommerce\Model\Planner\MultiStepPlanEnhancer;
use PHPUnit\Framework\TestCase;

class MultiStepPlanEnhancerTest extends TestCase
{
    private function enhancer(): MultiStepPlanEnhancer
    {
        $config = $this->createMock(Config::class);
        $config->method('getDefaultReasoningMode')->willReturn('deep');
        $config->method('getMaxToolCalls')->willReturn(8);
        return new MultiStepPlanEnhancer($config);
    }

    public function testSearchThenCartAddCreatesDependentStep(): void
    {
        $plan = $this->enhancer()->enhance(
            'find cheapest manuals and then add the first one to my cart',
            ['identity' => ['store_id' => 1]],
            ['tools' => [['name' => 'search_products', 'arguments' => []]]]
        );
        self::assertSame(['search_products', 'add_recent_product_to_cart'], array_column($plan['tools'], 'name'));
    }

    public function testSearchThenInventoryCreatesDependentRead(): void
    {
        $plan = $this->enhancer()->enhance(
            'show black shoes then tell me how many of the first are left',
            ['identity' => ['store_id' => 1]],
            ['tools' => [['name' => 'search_products', 'arguments' => []]]]
        );
        self::assertSame(['search_products', 'get_inventory'], array_column($plan['tools'], 'name'));
    }

    public function testNegatedDependentMutationIsNotAdded(): void
    {
        $plan = $this->enhancer()->enhance(
            'find shoes and then do not add the first one to my cart',
            ['identity' => ['store_id' => 1]],
            ['tools' => [['name' => 'search_products', 'arguments' => []]]]
        );
        self::assertNotContains('add_recent_product_to_cart', array_column($plan['tools'], 'name'));
    }
}
