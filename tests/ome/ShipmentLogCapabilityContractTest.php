<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class ShipmentLogCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/shipment/log.php');
    }

    public function testCapabilityListsShipmentLogs(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shipment/log.php';

        $capturedOrder = null;
        $model = new class($capturedOrder) {
            public $capturedOrder;

            public function __construct(& $capturedOrder)
            {
                $this->capturedOrder = & $capturedOrder;
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedOrder = $orderBy;
                return [
                    ['log_id' => 'S-1', 'shopId' => '12', 'orderBn' => 'O-1', 'deliveryCode' => 'D-1'],
                ];
            }

            public function count(array $filter = []): int
            {
                return 1;
            }
        };

        $capability = new ome_capability_shipment_log($model);
        $result = $capability->list(['page' => 1, 'pageSize' => 20]);

        $this->assertSame('', $capturedOrder);
        $this->assertSame('S-1', $result['list'][0]['log_id']);
        $this->assertSame(12, $result['list'][0]['shopId']);
        $this->assertSame(1, $result['total']);
    }

    public function testCapabilityReturnsShipmentLogDetail(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shipment/log.php';

        $model = new class {
            public function dump(string $logId): ?array
            {
                return $logId === 'S-9' ? ['log_id' => 'S-9', 'shopId' => '10', 'deliveryCode' => 'SF123'] : null;
            }
        };

        $capability = new ome_capability_shipment_log($model);
        $detail = $capability->detail('S-9');

        $this->assertSame('S-9', $detail['log_id']);
        $this->assertSame(10, $detail['shopId']);
        $this->assertSame('SF123', $detail['deliveryCode']);
    }
}
