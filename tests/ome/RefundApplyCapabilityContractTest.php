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
final class RefundApplyCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/refund/apply.php');
    }

    public function testCapabilityListsRefundApplicationsWithOrganizationScope(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/refund/apply.php';

        $capturedFilter = null;
        $refundModel = new class($capturedFilter) {
            public $capturedFilter;

            public function __construct(& $capturedFilter)
            {
                $this->capturedFilter = & $capturedFilter;
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedFilter = $filter;
                return [
                    ['apply_id' => 1, 'refund_apply_bn' => 'RA-1', 'org_id' => 11],
                    ['apply_id' => 2, 'refund_apply_bn' => 'RA-2', 'org_id' => 22],
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

        $capability = new ome_capability_refund_apply($refundModel, $operationOpsModel);
        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['aftersale_return_refund_apply'], ['aftersale_return_refund_apply'], time() + 3600, false);
        $result = $capability->list(['page' => 1, 'pageSize' => 20], $context);

        $this->assertSame([11, 22], $capturedFilter['org_id']);
        $this->assertSame(2, count($result['list']));
        $this->assertSame('RA-1', $result['list'][0]['refund_apply_bn']);
    }

    public function testCapabilityReturnsRefundApplicationDetail(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/refund/apply.php';

        $refundModel = new class {
            public function refund_apply_detail(int $id): ?array
            {
                return $id === 9 ? ['apply_id' => 9, 'refund_apply_bn' => 'RA-9', 'org_id' => 11, 'money' => '88.00', 'items' => [['bn' => 'SKU-1']]] : null;
            }
        };
        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 11]];
            }
        };

        $capability = new ome_capability_refund_apply($refundModel, $operationOpsModel);
        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['aftersale_return_refund_apply'], ['aftersale_return_refund_apply'], time() + 3600, false);
        $detail = $capability->detail(9, $context);

        $this->assertSame(9, $detail['basic']['apply_id']);
        $this->assertSame('RA-9', $detail['basic']['refund_apply_bn']);
        $this->assertSame('SKU-1', $detail['basic']['items'][0]['bn']);
    }
}
