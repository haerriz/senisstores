<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Template;

use PHPUnit\Framework\TestCase;
use Haerriz\GoogleShoppingFeed\Model\Template\PresetRegistry;

class PresetRegistryTest extends TestCase
{
    protected $registry;

    protected function setUp(): void
    {
        $this->registry = new PresetRegistry();
    }

    public function testGetPresetsContainsGoogleShopping()
    {
        $presets = $this->registry->getPresets();
        $this->assertArrayHasKey('google', $presets);
        $this->assertEquals('Google Shopping', $presets['google']['name']);
        $this->assertEquals('xml', $presets['google']['format']);
    }

    public function testGetPresetsContainsOpenAiAgenticCommerce()
    {
        $presets = $this->registry->getPresets();
        $this->assertArrayHasKey('openai', $presets);
        $this->assertEquals('OpenAI Agentic Commerce', $presets['openai']['name']);
        $this->assertEquals('jsonl.gz', $presets['openai']['format']);
    }
}
