<?php
declare(strict_types=1);namespace Haerriz\AgenticCommerce\Test\Unit\Model\Search;
use Haerriz\AgenticCommerce\Model\Search\{SearchAdapterInterface,SearchAdapterRegistry};use PHPUnit\Framework\TestCase;
class SearchAdapterRegistryTest extends TestCase{public function testCustomAdapterIsDiscoverable():void{$a=$this->createMock(SearchAdapterInterface::class);$r=new SearchAdapterRegistry(['semantic'=>$a],['semantic'=>'Semantic']);self::assertTrue($r->has('semantic'));self::assertSame('semantic',$r->getOptions()[0]['value']);}}
