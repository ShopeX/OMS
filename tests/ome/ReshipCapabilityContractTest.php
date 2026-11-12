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
final class ReshipCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/reship.php');
    }

    public function testCapabilityListsReshipsWithOrganizationScope(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/reship.php';

        $capturedFilter = null;
        $reshipModel = new class($capturedFilter) {
            public $capturedFilter;

            public function __construct(& $capturedFilter)
            {
                $this->capturedFilter = & $capturedFilter;
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedFilter = $filter;
                return [
                    ['reship_id' => 1, 'reship_bn' => 'RS-1', 'org_id' => 11],
                    ['reship_id' => 2, 'reship_bn' => 'RS-2', 'org_id' => 22],
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

        $capability = new ome_capability_reship($reshipModel, $operationOpsModel, new class {
            public function read_log(array $filter, int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [];
            }
        });
        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['ome.action.return_reshipping.view'], ['ome.action.return_reshipping.view'], time() + 3600, false);
        $result = $capability->list(['page' => 1, 'pageSize' => 20], $context);

        $this->assertSame([11, 22], $capturedFilter['org_id']);
        $this->assertSame(2, count($result['list']));
        $this->assertSame('RS-1', $result['list'][0]['reship_bn']);
    }

    public function testCapabilityReturnsReshipDetail(): void
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/reship.php';

        $reshipModel = new class {
            public function dump($id, string $fields = '*', array $subs = []): ?array
            {
                return $id === 9 ? ['reship_id' => 9, 'reship_bn' => 'RS-9', 'org_id' => 11, 'reship_items' => [['bn' => 'SKU-1']]] : null;
            }
        };
        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 11]];
            }
        };
        $logModel = new class {
            public function read_log(array $filter, int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [['operation' => 'create', 'log_id' => 1, 'operate_time' => 1700000000, 'op_name' => 'admin', 'memo' => 'memo', 'ip' => '127.0.0.1']];
            }
        };

        $capability = new ome_capability_reship($reshipModel, $operationOpsModel, $logModel);
        $context = new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 9, [1], ['ome.action.return_reshipping.view'], ['ome.action.return_reshipping.view'], time() + 3600, false);
        $detail = $capability->detail(9, $context);

        $this->assertSame(9, $detail['basic']['reship_id']);
        $this->assertSame('RS-9', $detail['basic']['reship_bn']);
        $this->assertSame('SKU-1', $detail['items'][0]['bn']);
        $this->assertSame('create', $detail['oplog'][0]['operation']);
    }
}
