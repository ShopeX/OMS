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
final class SupplierCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/purchase/lib/capability/supplier.php');
    }

    public function testCapabilityListsSuppliers(): void
    {
        require_once $this->root . '/app/purchase/lib/capability/supplier.php';

        $model = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [
                    ['supplier_id' => 1, 'bn' => 'SUP-A', 'name' => '供应商A', 'arrive_days' => '3'],
                    ['supplier_id' => 2, 'bn' => 'SUP-B', 'name' => '供应商B', 'arrive_days' => '5'],
                ];
            }

            public function count(array $filter = []): int
            {
                return 2;
            }
        };

        $capability = new purchase_capability_supplier($model);
        $result = $capability->list(['page' => 1, 'pageSize' => 20]);

        $this->assertSame(2, count($result['list']));
        $this->assertSame('供应商A', $result['list'][0]['name']);
        $this->assertSame(3, $result['list'][0]['arrive_days']);
    }

    public function testCapabilityReturnsSupplierDetail(): void
    {
        require_once $this->root . '/app/purchase/lib/capability/supplier.php';

        $model = new class {
            public function dump(array $filter, string $fields = '*'): ?array
            {
                return $filter['supplier_id'] === 9
                    ? ['supplier_id' => 9, 'bn' => 'SUP-9', 'name' => '详情供应商', 'contacter' => serialize([['name' => '张三']])]
                    : null;
            }
        };

        $capability = new purchase_capability_supplier($model);
        $detail = $capability->detail(9);

        $this->assertSame(9, $detail['supplier_id']);
        $this->assertSame('详情供应商', $detail['name']);
        $this->assertSame('张三', $detail['contacter'][0]['name']);
    }
}
