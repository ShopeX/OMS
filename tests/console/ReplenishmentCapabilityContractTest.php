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
final class ReplenishmentCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        chdir($this->root);
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', getcwd());
        }
        if (!defined('APP_DIR')) {
            define('APP_DIR', ROOT_DIR . '/app');
        }
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
    }

    public function testCapabilityFileExists(): void
    {
        $this->assertFileExists($this->root . '/app/console/lib/capability/replenishment.php');
    }

    public function testRecommendReturnsLowStockMaterialsWithSalesData(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $branchProductModel = new class {
            public $db;
            public function __construct()
            {
                $this->db = new class {
                    public function select(string $sql): array
                    {
                        if (str_contains($sql, 'sdb_ome_sales_items')) {
                            return [
                                ['bm_id' => 100, 'total_nums' => 70, 'day_count' => 14],
                            ];
                        }
                        return [
                            ['product_id' => 100, 'material_bn' => 'BM-001', 'store' => 10, 'store_freeze' => 5, 'safe_store' => 50, 'arrive_store' => 2],
                        ];
                    }
                };
            }
        };

        $branchModel = new class {
            public function dump($id, string $fields = '*'): array
            {
                return ['branch_id' => is_array($id) ? ($id['branch_id'] ?? 0) : $id, 'name' => '上海仓', 'branch_bn' => 'BR-SH', 'b_type' => 1];
            }
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        };

        $branchOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        };

        $capability = new console_capability_replenishment($branchProductModel, $branchModel, $branchOpsModel);

        $result = $capability->recommend([
            'branch_id' => 1,
            'time_from' => '2026-07-28',
            'time_to' => '2026-08-10',
        ]);

        $this->assertIsArray($result);
        $this->assertStringStartsWith('REPL-', $result['recommendation_id']);
        $this->assertArrayHasKey('generated_at', $result);
        $this->assertSame(1, $result['branch_id']);
        $this->assertSame('上海仓', $result['branch_name']);
        $this->assertSame(1, $result['total_low_stock']);
        $this->assertCount(1, $result['lines']);

        $line = $result['lines'][0];
        $this->assertSame(100, $line['product_id']);
        $this->assertSame('BM-001', $line['material_bn']);
        $this->assertSame(10, $line['store']);
        $this->assertSame(5, $line['store_freeze']);
        $this->assertSame(50, $line['safe_store']);
        $this->assertSame(5, $line['available']);
        $this->assertSame(5.0, $line['daily_sales']);
        $this->assertSame(1.0, $line['days_remaining']);
    }

    public function testMaterialWithoutSalesShowsDaysRemainingBasedOnTotalDays(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $branchProductModel = new class {
            public $db;
            public function __construct()
            {
                $this->db = new class {
                    public function select(string $sql): array
                    {
                        if (str_contains($sql, 'sdb_ome_sales_items')) {
                            return [];
                        }
                        return [
                            ['product_id' => 200, 'material_bn' => 'BM-002', 'store' => 5, 'store_freeze' => 0, 'safe_store' => 20, 'arrive_store' => 0],
                        ];
                    }
                };
            }
        };

        $branchModel = new class {
            public function dump($id, string $fields = '*'): array
            {
                return ['branch_id' => is_array($id) ? ($id['branch_id'] ?? 0) : $id, 'name' => 'Test', 'branch_bn' => 'BR-TEST', 'b_type' => 1];
            }
            public function getList(string $f, array $filter = [], int $o = 0, int $l = -1): array
            {
                return [];
            }
        };

        $branchOpsModel = new class {
            public function getList(string $f, array $filter = [], int $o = 0, int $l = -1): array
            {
                return [];
            }
        };

        $capability = new console_capability_replenishment($branchProductModel, $branchModel, $branchOpsModel);

        $result = $capability->recommend([
            'branch_id' => 1,
            'time_from' => '2026-07-28',
            'time_to' => '2026-08-10',
        ]);

        $this->assertCount(1, $result['lines']);
        $line = $result['lines'][0];
        $this->assertSame(0.0, $line['daily_sales']);
        $this->assertSame(14.0, $line['days_remaining']);
    }

    public function testStockAboveSafeStoreIsExcluded(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $branchProductModel = new class {
            public $db;
            public function __construct()
            {
                $this->db = new class {
                    public function select(string $sql): array
                    {
                        return [];
                    }
                };
            }
        };

        $branchModel = new class {
            public function dump($id, string $fields = '*'): array
            {
                return ['branch_id' => is_array($id) ? ($id['branch_id'] ?? 0) : $id, 'name' => 'Test', 'branch_bn' => 'BR-TEST', 'b_type' => 1];
            }
            public function getList(string $f, array $filter = [], int $o = 0, int $l = -1): array
            {
                return [];
            }
        };

        $branchOpsModel = new class {
            public function getList(string $f, array $filter = [], int $o = 0, int $l = -1): array
            {
                return [];
            }
        };

        $capability = new console_capability_replenishment($branchProductModel, $branchModel, $branchOpsModel);

        $result = $capability->recommend(['branch_id' => 1]);

        $this->assertSame(0, $result['total_low_stock']);
        $this->assertCount(0, $result['lines']);
    }

    public function testMaterialBnsFilterLimitsResults(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $branchProductModel = new class {
            public $db;
            public function __construct()
            {
                $this->db = new class {
                    public array $calls = [];
                    public function select(string $sql): array
                    {
                        $this->calls[] = $sql;
                        if (str_contains($sql, 'sdb_ome_branch_product')) {
                            return [
                                ['product_id' => 100, 'material_bn' => 'BM-001', 'store' => 10, 'store_freeze' => 5, 'safe_store' => 50, 'arrive_store' => 2],
                                ['product_id' => 200, 'material_bn' => 'BM-002', 'store' => 5, 'store_freeze' => 0, 'safe_store' => 20, 'arrive_store' => 0],
                            ];
                        }
                        return [
                            ['bm_id' => 100, 'total_nums' => 70, 'day_count' => 14],
                            ['bm_id' => 200, 'total_nums' => 30, 'day_count' => 14],
                        ];
                    }
                };
            }
        };

        $branchModel = new class {
            public function dump($id, string $fields = '*'): array
            {
                return ['branch_id' => is_array($id) ? ($id['branch_id'] ?? 0) : $id, 'name' => 'Test', 'branch_bn' => 'BR-TEST', 'b_type' => 1];
            }
            public function getList(string $f, array $filter = [], int $o = 0, int $l = -1): array
            {
                return [];
            }
        };

        $branchOpsModel = new class {
            public function getList(string $f, array $filter = [], int $o = 0, int $l = -1): array
            {
                return [];
            }
        };

        $capability = new console_capability_replenishment($branchProductModel, $branchModel, $branchOpsModel);

        $result = $capability->recommend([
            'branch_id' => 1,
            'material_bns' => ['BM-001', 'BM-002'],
        ]);

        $this->assertSame(2, $result['total_low_stock']);
    }

    public function testBranchReadsDisableModelPermissionCheckAfterRouteAuthorization(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $branchProductModel = new class {
            public $db;
            public function __construct()
            {
                $this->db = new class {
                    public function select(string $sql): array
                    {
                        return [];
                    }
                };
            }
        };

        $branchModel = new class {
            public array $dumpFilters = [];

            public function dump($filter, string $fields = '*'): array
            {
                $this->dumpFilters[] = $filter;
                return ['branch_id' => 1, 'name' => 'Test', 'branch_bn' => 'BR-TEST', 'b_type' => 1];
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        };

        $capability = new console_capability_replenishment($branchProductModel, $branchModel);
        $capability->recommend(['branch_id' => 1, 'time_from' => '2026-07-28', 'time_to' => '2026-08-10']);

        $this->assertCount(2, $branchModel->dumpFilters);
        foreach ($branchModel->dumpFilters as $filter) {
            $this->assertIsArray($filter);
            $this->assertSame(1, $filter['branch_id']);
            $this->assertSame('false', $filter['check_permission']);
        }
    }

    public function testInvalidBranchIdThrows(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $capability = new console_capability_replenishment(
            new class { public $db; public function __construct() { $this->db = new class { public function select($s): array { return []; } }; } },
            new class { public function dump($id, $f = '*'): array { return []; } public function getList(...$_): array { return []; } },
            new class { public function getList(...$_): array { return []; } }
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('请提供有效的仓库ID');

        $capability->recommend(['branch_id' => 0]);
    }

    public function testNonSuperBranchScopeFiltersUnauthorized(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $branchProductModel = new class {
            public $db;
            public function __construct()
            {
                $this->db = new class {
                    public function select($s): array { return []; }
                };
            }
        };

        $branchModel = new class {
            public function dump($id, string $fields = '*'): array
            {
                return ['branch_id' => $id, 'name' => 'Test', 'branch_bn' => 'BR-TEST'];
            }
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                if (($filter['type'] ?? '') === 'main' && ($filter['b_type'] ?? '') === 1) {
                    return array_map(static fn(int $id): array => ['branch_id' => $id], $filter['branch_id'] ?? []);
                }
                return [];
            }
        };

        $branchOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['branch_id' => 1]];
            }
        };

        $capability = new console_capability_replenishment($branchProductModel, $branchModel, $branchOpsModel);

        $nonSuperContext = new mcpapi_auth_context(
            'test-req', 'test-client', 'test-subj', 'test-tenant',
            'https://oms.example.com', 42, [1], ['console_purchase_po'],
            ['console_purchase_po'], time() + 3600, false
        );

        $result = $capability->recommend(['branch_id' => 2], $nonSuperContext);

        $this->assertSame(0, $result['total_low_stock']);
        $this->assertSame('', $result['branch_name']);
    }

    public function testSuperBypassesBranchScope(): void
    {
        require_once $this->root . '/app/console/lib/capability/replenishment.php';

        $branchProductModel = new class {
            public $db;
            public function __construct()
            {
                $this->db = new class {
                    public function select(string $sql): array
                    {
                        if (str_contains($sql, 'sdb_ome_branch_product')) {
                            return [
                                ['product_id' => 600, 'material_bn' => 'BM-SUPER', 'store' => 10, 'store_freeze' => 5, 'safe_store' => 50, 'arrive_store' => 2],
                            ];
                        }
                        return [];
                    }
                };
            }
        };

        $branchModel = new class {
            public function dump($id, string $fields = '*'): array
            {
                return ['branch_id' => is_array($id) ? ($id['branch_id'] ?? 0) : $id, 'name' => 'Super Branch', 'branch_bn' => 'BR-SUP', 'b_type' => 1];
            }
            public function getList(string $f, array $filter = [], int $o = 0, int $l = -1): array
            {
                return [];
            }
        };

        $branchOpsModel = new class {
            public function getList(string $f, array $filter = [], int $o = 0, int $l = -1): array
            {
                return [];
            }
        };

        $capability = new console_capability_replenishment($branchProductModel, $branchModel, $branchOpsModel);

        $superContext = new mcpapi_auth_context(
            'test-req', 'test-client', 'test-subj', 'test-tenant',
            'https://oms.example.com', 1, [], [], [], time() + 3600, true
        );

        $result = $capability->recommend(['branch_id' => 99], $superContext);

        $this->assertSame(1, $result['total_low_stock']);
        $this->assertSame('Super Branch', $result['branch_name']);
    }
}
