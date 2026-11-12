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
final class InventoryCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/console/lib/capability/inventory.php');
    }

    public function testCapabilityListsInventoryRows(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory.php';

        $stockSource = new class {
            public function list(array $request): array
            {
                return [
                    'list' => [
                        ['branch_id' => 1, 'bm_id' => 2001, 'store' => 100],
                        ['branch_id' => 2, 'bm_id' => 2002, 'store' => 50],
                    ],
                    'total' => 2,
                    'page' => 1,
                    'pageSize' => 20,
                ];
            }
        };
        $branchProductSource = new class {
            public function getRow(int $branchId, int $bmId): ?array
            {
                return null;
            }

            public function diff(array $pairs): array
            {
                return [];
            }
        };

        $capability = new console_capability_inventory($stockSource, $branchProductSource);
        $result = $capability->list(['page' => 1, 'pageSize' => 20]);

        $this->assertSame(2, count($result['list']));
        $this->assertSame(100, $result['list'][0]['store']);
    }

    public function testCapabilityGetsSingleWarehouseMaterialRow(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory.php';

        $stockSource = new class {
            public function list(array $request): array
            {
                return ['list' => [], 'total' => 0, 'page' => 1, 'pageSize' => 20];
            }
        };
        $branchProductSource = new class {
            public function getRow(int $branchId, int $bmId): ?array
            {
                return ['branch_id' => $branchId, 'bm_id' => $bmId, 'store' => 11];
            }

            public function diff(array $pairs): array
            {
                return [];
            }
        };

        $capability = new console_capability_inventory($stockSource, $branchProductSource);
        $result = $capability->getByMaterialWarehouse(1, 2001);

        $this->assertSame(1, $result['branch_id']);
        $this->assertSame(2001, $result['bm_id']);
    }

    public function testCapabilityDiffChecksMultiplePairs(): void
    {
        require_once $this->root . '/app/console/lib/capability/inventory.php';

        $stockSource = new class {
            public function list(array $request): array
            {
                return ['list' => [], 'total' => 0, 'page' => 1, 'pageSize' => 20];
            }
        };
        $branchProductSource = new class {
            public function getRow(int $branchId, int $bmId): ?array
            {
                return null;
            }

            public function diff(array $pairs): array
            {
                return [
                    'pairs' => [
                        ['branch_id' => 1, 'bm_id' => 2001, 'label' => 'A', 'status' => 'resolved'],
                        ['branch_id' => 2, 'bm_id' => 2002, 'label' => 'B', 'status' => 'missing'],
                    ],
                ];
            }
        };

        $capability = new console_capability_inventory($stockSource, $branchProductSource);
        $result = $capability->diffCheck([
            ['branch_id' => 1, 'bm_id' => 2001, 'label' => 'A'],
            ['branch_id' => 2, 'bm_id' => 2002, 'label' => 'B'],
        ]);

        $this->assertSame('resolved', $result['pairs'][0]['status']);
        $this->assertSame('B', $result['pairs'][1]['label']);
    }

    public function testDefaultInventorySourcesExposeExpectedMethods(): void
    {
        $stockSource = (string) file_get_contents($this->root . '/app/console/controller/api/stock.php');
        $branchProductSource = (string) file_get_contents($this->root . '/app/console/controller/api/branch/product.php');

        $this->assertStringContainsString('function list(', $stockSource);
        $this->assertStringContainsString('function getRow(', $branchProductSource);
        $this->assertStringContainsString('function diff(', $branchProductSource);
    }
}
