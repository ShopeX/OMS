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
final class DeliveryCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/delivery.php');
    }

    public function testCapabilityListsDeliveriesWithOrganizationScope(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/delivery.php';

        $capturedFilter = null;
        $deliveryModel = new class($capturedFilter) {
            public $capturedFilter;

            public function __construct(& $capturedFilter)
            {
                $this->capturedFilter = & $capturedFilter;
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedFilter = $filter;
                return [
                    ['delivery_id' => 1, 'delivery_bn' => 'D-1', 'org_id' => 11],
                    ['delivery_id' => 2, 'delivery_bn' => 'D-2', 'org_id' => 22],
                ];
            }

            public function count(array $filter = []): int
            {
                return 2;
            }
        };
        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 11], ['org_id' => 22]];
            }
        };

        $capability = new ome_capability_delivery($deliveryModel, $operationOpsModel);
        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['invoice_delivery'], ['invoice_delivery'], time() + 3600, false);
        $result = $capability->list(['page' => 1, 'pageSize' => 20], $context);

        $this->assertSame([11, 22], $capturedFilter['org_id']);
        $this->assertSame(2, count($result['list']));
        $this->assertSame('D-1', $result['list'][0]['delivery_bn']);
    }

    public function testCapabilityReturnsDeliveryDetail(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/delivery.php';

        $deliveryModel = new class {
            public function dump(array $filter): ?array
            {
                return ((int) ($filter['delivery_id'] ?? 0) === 9)
                    ? ['delivery_id' => 9, 'delivery_bn' => 'D-9', 'org_id' => 11, 'logi_no' => 'LN-9', 'type' => 'normal', 'pause' => 'false', 'parent_id' => 0, 'disabled' => 'false', 'status' => 'ready']
                    : null;
            }
        };
        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 11]];
            }
        };

        $capability = new ome_capability_delivery($deliveryModel, $operationOpsModel);
        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['invoice_delivery'], ['invoice_delivery'], time() + 3600, false);
        $detail = $capability->detail(9, $context);

        $this->assertSame(9, $detail['basic']['delivery_id']);
        $this->assertSame('D-9', $detail['basic']['delivery_bn']);
        $this->assertSame('LN-9', $detail['basic']['logi_no']);
    }
}
