<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InventoryRiskCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/console/lib/capability/inventory/risk.php');
    }

    public function testCapabilityBuildsDailyDifferenceReviewSummaryAndItems(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/risk.php';

        $dailyInventoryModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                if (($filter['stock_date'] ?? null) !== '2026-07-24') {
                    return [];
                }

                return [
                    ['id' => 1, 'channel_type' => 'wms', 'channel_bn' => 'WMS-A', 'oms_stock' => 100, 'outer_stock' => 90, 'diff_stock' => 10, 'is_diff' => '1'],
                    ['id' => 2, 'channel_type' => 'store', 'channel_bn' => 'STORE-B', 'oms_stock' => 50, 'outer_stock' => 50, 'diff_stock' => 0, 'is_diff' => '0'],
                ];
            }
        };

        $dailyInventoryItemModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                if (($filter['stock_date'] ?? null) !== '2026-07-24') {
                    return [];
                }
                if (($filter['material_bn'] ?? null) === 'BM-404') {
                    return [];
                }

                return [
                    ['dlyinv_id' => 1, 'warehouse_code' => 'W-A', 'material_bn' => 'BM-1', 'oms_stock' => 70, 'outer_stock' => 60, 'diff_stock' => 10, 'diff_type' => 1],
                    ['dlyinv_id' => 2, 'warehouse_code' => 'S-B', 'material_bn' => 'BM-2', 'oms_stock' => 50, 'outer_stock' => 50, 'diff_stock' => 0, 'diff_type' => 1],
                ];
            }
        };

        $capability = new console_capability_inventory_risk($dailyInventoryModel, $dailyInventoryItemModel);
        $result = $capability->review(['stock_date' => '2026-07-24']);

        $this->assertSame('2026-07-24', $result['query']['stock_date']);
        $this->assertSame(2, $result['summary']['channel_total']);
        $this->assertSame(1, $result['summary']['diff_channel_total']);
        $this->assertSame(150, $result['summary']['oms_stock_total']);
        $this->assertSame(140, $result['summary']['outer_stock_total']);
        $this->assertSame(10, $result['summary']['diff_stock_total']);
        $this->assertCount(2, $result['items']);
        $this->assertSame('BM-1', $result['items'][0]['material_bn']);
    }

    public function testCapabilityRejectsMissingStockDate(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/risk.php';

        $capability = new console_capability_inventory_risk(new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [];
            }
        }, new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [];
            }
        });

        $this->expectException(InvalidArgumentException::class);
        $capability->review([]);
    }

    public function testCapabilityRejectsMalformedStockDate(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/risk.php';

        $capability = new console_capability_inventory_risk(new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [];
            }
        }, new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [];
            }
        });

        $this->expectException(InvalidArgumentException::class);
        $capability->review(['stock_date' => '2026/07/24']);
    }

    public function testCapabilityUsesAbsoluteDiffTotalsAndPropagatesChannelFiltersToItems(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/risk.php';

        $capturedChannelFilter = null;
        $capturedItemFilter = null;

        $dailyInventoryModel = new class($capturedChannelFilter) {
            public $capturedChannelFilter;
            public function __construct(& $capturedChannelFilter) { $this->capturedChannelFilter = & $capturedChannelFilter; }
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedChannelFilter = $filter;
                return [
                    ['id' => 1, 'channel_type' => 'wms', 'channel_bn' => 'WMS-A', 'oms_stock' => 100, 'outer_stock' => 90, 'diff_stock' => 10, 'is_diff' => '1'],
                    ['id' => 2, 'channel_type' => 'wms', 'channel_bn' => 'WMS-A', 'oms_stock' => 80, 'outer_stock' => 95, 'diff_stock' => -15, 'is_diff' => '1'],
                ];
            }
        };

        $dailyInventoryItemModel = new class($capturedItemFilter) {
            public $capturedItemFilter;
            public function __construct(& $capturedItemFilter) { $this->capturedItemFilter = & $capturedItemFilter; }
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedItemFilter = $filter;
                return [
                    ['dlyinv_id' => 1, 'channel_bn' => 'WMS-A', 'warehouse_code' => 'W-A', 'material_bn' => 'BM-1', 'oms_stock' => 70, 'outer_stock' => 60, 'diff_stock' => 10, 'diff_type' => 1],
                    ['dlyinv_id' => 2, 'channel_bn' => 'WMS-A', 'warehouse_code' => 'W-B', 'material_bn' => 'BM-2', 'oms_stock' => 80, 'outer_stock' => 95, 'diff_stock' => -15, 'diff_type' => 1],
                ];
            }
        };

        $capability = new console_capability_inventory_risk($dailyInventoryModel, $dailyInventoryItemModel);
        $result = $capability->review(['stock_date' => '2026-07-24', 'channel_type' => 'wms', 'channel_bn' => 'WMS-A']);

        $this->assertSame('wms', $capturedChannelFilter['channel_type']);
        $this->assertSame('WMS-A', $capturedChannelFilter['channel_bn']);
        $this->assertSame([1, 2], $capturedItemFilter['dlyinv_id']);
        $this->assertSame(25, $result['summary']['diff_stock_total']);
    }

    public function testCapabilityHonorsDiffOnlyFalseAndRejectsInvalidCalendarDate(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/risk.php';

        $capturedItemFilter = null;
        $capability = new console_capability_inventory_risk(new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [['id' => 1, 'channel_type' => 'wms', 'channel_bn' => 'WMS-A', 'oms_stock' => 10, 'outer_stock' => 10, 'diff_stock' => 0, 'is_diff' => '0']];
            }
        }, new class($capturedItemFilter) {
            public $capturedItemFilter;
            public function __construct(& $capturedItemFilter) { $this->capturedItemFilter = & $capturedItemFilter; }
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                $this->capturedItemFilter = $filter;
                return [];
            }
        });

        $result = $capability->review(['stock_date' => '2026-07-24', 'diff_only' => 'false']);
        $this->assertFalse($result['query']['diff_only']);
        $this->assertArrayNotHasKey('diff_stock|noequal', $capturedItemFilter);

        $this->expectException(InvalidArgumentException::class);
        $capability->review(['stock_date' => '2026-02-31']);
    }

    public function testCapabilityRejectsBranchScopeUntilPersistedMappingExists(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/risk.php';

        $capability = new console_capability_inventory_risk(new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [];
            }
        }, new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [];
            }
        });

        $this->expectException(InvalidArgumentException::class);
        $capability->review(['stock_date' => '2026-07-24', 'branch_ids' => [11, 12]]);
    }

    public function testCapabilityReturnsBoundedPageEnvelope(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/risk.php';

        $dailyInventoryModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [
                    ['id' => 1, 'channel_type' => 'wms', 'channel_bn' => 'WMS-A', 'oms_stock' => 100, 'outer_stock' => 90, 'diff_stock' => 10, 'is_diff' => '1'],
                    ['id' => 2, 'channel_type' => 'store', 'channel_bn' => 'STORE-B', 'oms_stock' => 50, 'outer_stock' => 40, 'diff_stock' => 10, 'is_diff' => '1'],
                ];
            }
        };

        $dailyInventoryItemModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [
                    ['dlyinv_id' => 1, 'warehouse_code' => 'W-A', 'material_bn' => 'BM-1', 'oms_stock' => 70, 'outer_stock' => 60, 'diff_stock' => 10, 'diff_type' => 1],
                    ['dlyinv_id' => 2, 'warehouse_code' => 'S-B', 'material_bn' => 'BM-2', 'oms_stock' => 50, 'outer_stock' => 40, 'diff_stock' => 10, 'diff_type' => 1],
                ];
            }
        };

        $capability = new console_capability_inventory_risk($dailyInventoryModel, $dailyInventoryItemModel);
        $result = $capability->review(['stock_date' => '2026-07-24', 'page' => 2, 'page_size' => 1]);

        $this->assertSame(2, $result['total_count']);
        $this->assertSame(1, $result['returned_count']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(1, $result['page_size']);
        $this->assertFalse($result['has_more']);
    }

    public function testCapabilityMarksPartialWhenResultPageIsBounded(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory/risk.php';

        $capability = new console_capability_inventory_risk(new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [
                    ['id' => 1, 'channel_type' => 'wms', 'channel_bn' => 'WMS-A', 'oms_stock' => 100, 'outer_stock' => 90, 'diff_stock' => 10, 'is_diff' => '1'],
                    ['id' => 2, 'channel_type' => 'wms', 'channel_bn' => 'WMS-B', 'oms_stock' => 90, 'outer_stock' => 80, 'diff_stock' => 10, 'is_diff' => '1'],
                ];
            }
        }, new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [
                    ['dlyinv_id' => 1, 'warehouse_code' => 'W-A', 'material_bn' => 'BM-1', 'oms_stock' => 70, 'outer_stock' => 60, 'diff_stock' => 10, 'diff_type' => 1],
                    ['dlyinv_id' => 2, 'warehouse_code' => 'W-B', 'material_bn' => 'BM-2', 'oms_stock' => 80, 'outer_stock' => 70, 'diff_stock' => 10, 'diff_type' => 1],
                ];
            }
        });

        $result = $capability->review(['stock_date' => '2026-07-24', 'page' => 1, 'page_size' => 1]);

        $this->assertTrue($result['partial']);
        $this->assertContains('result window is bounded', $result['warnings']);
    }
}
