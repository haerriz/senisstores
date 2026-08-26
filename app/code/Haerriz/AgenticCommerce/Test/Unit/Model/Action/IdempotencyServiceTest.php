<?php

declare(strict_types=1);

namespace Haerriz\AgenticCommerce\Test\Unit\Model\Action;

use Haerriz\AgenticCommerce\Model\Action\IdempotencyService;
use Haerriz\AgenticCommerce\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class IdempotencyServiceTest extends TestCase
{
    public function testFingerprintIsStableAcrossAssociativeKeyOrder(): void
    {
        $service = new IdempotencyService(
            $this->createMock(ResourceConnection::class),
            $this->createMock(Config::class)
        );
        self::assertSame(
            $service->fingerprint(['tool'=>'add','payload'=>['sku'=>'A','qty'=>1]]),
            $service->fingerprint(['payload'=>['qty'=>1,'sku'=>'A'],'tool'=>'add'])
        );
    }

    public function testAcquireStoresOnlyHashedScopeAndIdempotencyKey(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturn(false);
        $connection->expects(self::once())->method('insert')->with(
            'haerriz_agentic_idempotency',
            self::callback(static function (array $row): bool {
                return ($row['scope_hash'] ?? '') === hash('sha256', 'customer:7|cart')
                    && ($row['idempotency_key_hash'] ?? '') === hash('sha256', 'retry-key')
                    && !in_array('retry-key', $row, true)
                    && !in_array('customer:7|cart', $row, true)
                    && ($row['status'] ?? '') === 'processing';
            })
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->with('haerriz_agentic_idempotency')->willReturn('haerriz_agentic_idempotency');
        $config = $this->createMock(Config::class);
        $config->method('getIdempotencyTtl')->willReturn(600);
        $service = new IdempotencyService($resource, $config);

        self::assertNull($service->acquire(
            'retry-key',
            'customer:7|cart',
            'add_product_to_cart',
            $service->fingerprint(['sku'=>'ABC','qty'=>1]),
            1
        ));
    }

    public function testAcquireReplaysCompletedResultForSameFingerprint(): void
    {
        $requestHash = hash('sha256', 'request');
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchRow')->willReturn([
            'idempotency_id'=>1,
            'request_hash'=>$requestHash,
            'status'=>'completed',
            'response_json'=>'{"ok":true}',
            'expires_at'=>gmdate('Y-m-d H:i:s', time()+300),
        ]);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn('haerriz_agentic_idempotency');
        $service = new IdempotencyService($resource, $this->createMock(Config::class));

        self::assertSame(['ok'=>true], $service->acquire('same','scope','tool',$requestHash,1));
    }
}
