<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InventoryDiffMcpapiContractTest extends TestCase
{
    private string $cwdBeforeTest;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cwdBeforeTest = getcwd();
        $this->root = dirname(__DIR__, 2);
        chdir($this->root);
        if (!defined('ROOT_DIR')) define('ROOT_DIR', getcwd());
        if (!defined('APP_DIR')) define('APP_DIR', ROOT_DIR . '/app');
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
        kernel::register_autoload();
    }

    protected function tearDown(): void
    {
        chdir($this->cwdBeforeTest);
        parent::tearDown();
    }

    public function testOverviewAndDetailControllerContracts(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/diff.php';
        require_once $this->root . '/app/mcpapi/controller/v1/inventory/diff.php';
        $daily = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit, string $orderBy = ''): array { return [['id' => 7]]; }
            public function count(array $filters): int { return 1; }
            public function dump(int $id, string $columns): array { return ['id' => $id, 'channel_id' => 1]; }
        };
        $items = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit): array { return []; }
        };
        $branches = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit): array { return []; }
        };
        $capability = new console_capability_inventory_diff($daily, $items, $branches, $branches);
        $controller = new mcpapi_ctl_v1_inventory_diff($capability);

        $overview = $controller->overview($this->context(), ['stock_date' => '2026-08-11']);
        $detail = $controller->detail($this->context(), ['dlyinv_id' => 7]);

        self::assertSame(200, $overview->status());
        self::assertSame(1, $overview->body()['total']);
        self::assertSame(200, $detail->status());
        self::assertSame(7, $detail->body()['row']['dlyinv']['id']);
    }

    public function testDetailResolvesAllWmsBranchesForWarehouseCode(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/diff.php';
        require_once $this->root . '/app/mcpapi/controller/v1/inventory/diff.php';
        $daily = new class {
            public function dump(int $id, string $columns): array
            {
                return ['id' => $id, 'channel_id' => 9, 'channel_type' => 'wms', 'stock_date' => '2026-08-11'];
            }
        };
        $items = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit): array
            {
                return [[
                    'warehouse_code' => 'WMS-BR-1',
                    'material_bn' => 'BN-1',
                    'diff_stock' => 1,
                ]];
            }
        };
        $branches = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit): array
            {
                if (($filters['wms_id'] ?? null) === 9) {
                    return [
                        ['branch_id' => 101, 'branch_bn' => 'OMS-BR-1', 'wms_id' => 9],
                        ['branch_id' => 102, 'branch_bn' => 'OMS-BR-2', 'wms_id' => 9],
                    ];
                }
                return [];
            }
        };
        $relations = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit): array
            {
                return [
                    ['sys_branch_bn' => 'OMS-BR-1', 'wms_branch_bn' => 'WMS-BR-1'],
                    ['sys_branch_bn' => 'OMS-BR-2', 'wms_branch_bn' => 'WMS-BR-1'],
                ];
            }
        };
        $capability = new console_capability_inventory_diff($daily, $items, $branches, $branches, $relations);
        $response = (new mcpapi_ctl_v1_inventory_diff($capability))->detail($this->context(), ['dlyinv_id' => 7]);

        self::assertSame(200, $response->status());
        self::assertSame([101, 102], $response->body()['row']['items'][0]['branch_ids']);
        self::assertSame('wms_branch_bn', $response->body()['row']['items'][0]['warehouse_mapping']);
    }

    public function testDetailMarksUnresolvedWmsWarehouseWithoutExpandingScope(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/diff.php';
        require_once $this->root . '/app/mcpapi/controller/v1/inventory/diff.php';
        $daily = new class {
            public function dump(int $id, string $columns): array
            {
                return ['id' => $id, 'channel_id' => 9, 'channel_type' => 'wms'];
            }
        };
        $items = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit): array
            {
                return [['warehouse_code' => 'UNKNOWN', 'material_bn' => 'BN-1', 'diff_stock' => 1]];
            }
        };
        $branches = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit): array
            {
                return [['branch_id' => 101, 'branch_bn' => 'OMS-BR-1', 'wms_id' => 9]];
            }
        };
        $relations = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit): array { return []; }
        };
        $capability = new console_capability_inventory_diff($daily, $items, $branches, $branches, $relations);
        $response = (new mcpapi_ctl_v1_inventory_diff($capability))->detail($this->context(), ['dlyinv_id' => 7]);

        self::assertSame(200, $response->status());
        self::assertSame([], $response->body()['row']['items'][0]['branch_ids']);
        self::assertSame('unresolved', $response->body()['row']['items'][0]['warehouse_mapping']);
    }

    public function testMovementListReturnsTypedSignedRowsAndFiltersByBnAndEpochRange(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/movement.php';
        require_once $this->root . '/app/mcpapi/controller/v1/inventory/movement.php';
        $iostock = new class {
            public array $listCall = [];
            public array $_iostock_types = ['70' => ['io' => 1], '7' => ['io' => 0]];
            public function getList(string $columns, array $filters, int $offset, int $limit, string $orderBy = ''): array
            {
                $this->listCall = [$columns, $filters, $offset, $limit, $orderBy];
                return [
                    ['iostock_id' => 1, 'iostock_bn' => 'IN-1', 'original_bn' => 'PO-1', 'branch_id' => 12, 'bn' => 'BM-1', 'nums' => 3.5, 'type_id' => 70, 'create_time' => 1700000100],
                    ['iostock_id' => 2, 'iostock_bn' => 'OUT-1', 'original_bn' => 'SO-1', 'branch_id' => 12, 'bn' => 'BM-1', 'nums' => 2, 'type_id' => 7, 'create_time' => 1700000200],
                ];
            }
            public function count(array $filters): int { return 2; }
        };
        $branch = new class {
            public function dump(int $id, string $columns): array { return ['branch_id' => $id, 'branch_bn' => 'BR-12']; }
        };
        $scope = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit): array { return []; }
        };
        $controller = new mcpapi_ctl_v1_inventory_movement(new console_capability_inventory_movement($iostock, $branch, $scope));
        $response = $controller->list($this->context(), ['branch_id' => 12, 'bn' => 'BM-1', 'time_from' => 1700000000, 'time_to' => 1700003600]);

        self::assertSame(200, $response->status());
        self::assertSame(['*', ['branch_id' => 12, 'create_time|between' => [1700000000, 1700003600], 'bn' => 'BM-1'], 0, 100, 'create_time asc, iostock_id asc'], $iostock->listCall);
        self::assertSame(3.5, $response->body()['list'][0]['signed_quantity']);
        self::assertSame(-2, $response->body()['list'][1]['signed_quantity']);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context('request-inventory', 'client-inventory', 'subject-inventory', 'tenant-inventory', 'https://oms.example.internal', 1, [1], ['inventory'], ['inventory'], time() + 3600, true);
    }
}
