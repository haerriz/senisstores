<?php
declare(strict_types=1);namespace Haerriz\AgenticCommerce\Test\Unit\Model\Ai;
use Haerriz\AgenticCommerce\Model\Ai\{ProviderInterface,ProviderRegistry};use PHPUnit\Framework\TestCase;
class ProviderRegistryTest extends TestCase{public function testCustomProviderIsDiscoverable():void{$p=$this->createMock(ProviderInterface::class);$r=new ProviderRegistry(['custom_gateway'=>$p],['custom_gateway'=>'Custom Gateway']);self::assertSame($p,$r->get('custom_gateway'));self::assertSame('custom_gateway',$r->getOptions(false)[0]['value']);}}
