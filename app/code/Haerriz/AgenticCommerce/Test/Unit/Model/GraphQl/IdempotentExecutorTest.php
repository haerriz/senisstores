<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\GraphQl;

use Haerriz\AgenticCommerce\Model\Action\IdempotencyService;
use Haerriz\AgenticCommerce\Model\Agent\ToolPolicy;
use Haerriz\AgenticCommerce\Model\GraphQl\IdempotentExecutor;
use Haerriz\AgenticCommerce\Model\Observability\TelemetryEmitter;
use PHPUnit\Framework\TestCase;

class IdempotentExecutorTest extends TestCase
{
    public function testPolicyIsReassertedEvenWithoutRetryKey(): void
    {
        $policy = $this->createMock(ToolPolicy::class);
        $policy->expects(self::once())->method('assertAllowed')->with('update_cart_item', ['store_id'=>1,'customer_id'=>7]);
        $telemetry = $this->createMock(TelemetryEmitter::class);
        $telemetry->method('traceId')->willReturn('trace');
        $subject = new IdempotentExecutor($this->createMock(IdempotencyService::class), $policy, $telemetry);
        $calls = 0;
        $result = $subject->execute('update_cart_item', [], ['store_id'=>1,'customer_id'=>7], static function () use (&$calls): array {
            $calls++;
            return ['updated'=>true];
        });
        self::assertSame(1, $calls);
        self::assertTrue($result['updated']);
    }

    public function testCompletedIdempotentMutationIsReplayedWithoutExecutingOperation(): void
    {
        $policy = $this->createMock(ToolPolicy::class);
        $policy->method('isIdempotent')->with('add_product_to_cart')->willReturn(true);
        $idempotency = $this->createMock(IdempotencyService::class);
        $idempotency->method('fingerprint')->willReturn(str_repeat('a',64));
        $idempotency->method('acquire')->willReturn(['added'=>true]);
        $telemetry = $this->createMock(TelemetryEmitter::class);
        $telemetry->method('traceId')->willReturn('trace');
        $subject = new IdempotentExecutor($idempotency, $policy, $telemetry);
        $result = $subject->execute(
            'add_product_to_cart',
            ['idempotency_key'=>'retry-1','sku'=>'ABC'],
            ['store_id'=>1,'customer_id'=>7],
            static function (): array { throw new \RuntimeException('must not execute'); }
        );
        self::assertTrue($result['added']);
        self::assertTrue($result['idempotent_replay']);
    }
}
