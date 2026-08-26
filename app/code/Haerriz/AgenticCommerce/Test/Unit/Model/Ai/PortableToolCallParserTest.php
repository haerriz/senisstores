<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Ai;

use Haerriz\AgenticCommerce\Model\Ai\PortableToolCallParser;
use PHPUnit\Framework\TestCase;

final class PortableToolCallParserTest extends TestCase
{
    public function testParsesSinglePortableCall(): void
    {
        $parser = new PortableToolCallParser();
        self::assertSame(
            [['name' => 'search_products', 'arguments' => ['phrase' => 'running shoes']]],
            $parser->parse(
                '{"tool":"search_products","arguments":{"phrase":"running shoes"}}',
                ['search_products'],
                4
            )
        );
    }

    public function testRejectsUnknownToolAndNonJsonProse(): void
    {
        $parser = new PortableToolCallParser();
        self::assertSame([], $parser->parse('{"tool":"run_sql","arguments":{}}', ['search_products'], 4));
        self::assertSame([], $parser->parse('```json {"tool":"search_products","arguments":{}} ```', ['search_products'], 4));
    }

    public function testRespectsAllowedToolsAndMaximumCalls(): void
    {
        $parser = new PortableToolCallParser();
        $content = '{"tools":['
            . '{"name":"search_products","arguments":{"phrase":"shoes"}},'
            . '{"name":"get_inventory","arguments":{"index":1}},'
            . '{"name":"get_product_price","arguments":{"index":1}}'
            . ']}';
        $calls = $parser->parse($content, ['search_products', 'get_inventory', 'get_product_price'], 2);
        self::assertCount(2, $calls);
        self::assertSame('search_products', $calls[0]['name']);
        self::assertSame('get_inventory', $calls[1]['name']);
    }
}
