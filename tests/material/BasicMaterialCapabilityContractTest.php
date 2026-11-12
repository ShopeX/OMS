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
final class BasicMaterialCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/material/lib/capability/basic/material.php');
    }

    public function testCapabilityListsBasicMaterials(): void
    {
        require_once $this->root . '/app/material/lib/capability/basic/material.php';

        $basicMaterialModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [
                    ['bm_id' => 1, 'material_bn' => 'BM-1', 'material_name' => '物料一'],
                    ['bm_id' => 2, 'material_bn' => 'BM-2', 'material_name' => '物料二'],
                ];
            }

            public function count(array $filter = []): int
            {
                return 2;
            }
        };

        $capability = new material_capability_basic_material(
            $basicMaterialModel,
            new class { public function dump($bmId) { return []; } },
            new class { public function dump($bmId) { return []; } },
            new class { public function dump($bmId) { return []; } },
            new class { public function dump(array $filter, string $fields = '*') { return []; } },
            new class { public function dump($bmId, string $fields = '*') { return []; } },
            new class { public function getList(string $fields, array $filter = []): array { return []; } },
            new class { public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; } },
            new class { public function getList(string $fields, array $filter = []): array { return []; } }
        );

        $result = $capability->list(['page' => 1, 'pageSize' => 20]);

        $this->assertSame(2, count($result['list']));
        $this->assertSame('物料一', $result['list'][0]['material_name']);
    }

    public function testCapabilityReturnsBasicMaterialDetailAndTemplate(): void
    {
        if (!class_exists('material_codebase', false)) {
            eval('class material_codebase { public static function getBarcodeType(): string { return "barcode"; } }');
        }
        require_once $this->root . '/app/material/lib/capability/basic/material.php';

        $basicMaterialModel = new class {
            public function dump($bmId): ?array
            {
                return $bmId === 9 ? ['bm_id' => 9, 'type' => '1', 'material_name' => '详情物料', 'material_bn' => 'BM-9', 'cat_id' => 6] : null;
            }

            public function templateColumn(): array
            {
                return ['*:基础物料名称' => 'material_name', '*:基础物料编码' => 'material_bn'];
            }
        };

        $capability = new material_capability_basic_material(
            $basicMaterialModel,
            new class { public function dump($bmId): array { return ['brand_id' => 3, 'cat_id' => 5, 'specifications' => '大号']; } },
            new class { public function dump($bmId): array { return ['use_expire' => 1, 'warn_day' => 10]; } },
            new class { public function dump($bmId): array { return ['openscan' => '3', 'fromposition' => '1', 'toposition' => '8']; } },
            new class { public function dump(array $filter, string $fields = '*'): array { return ['code' => 'BAR-9']; } },
            new class { public function dump($bmId, string $fields = '*'): array { return ['cat_id' => 6, 'cat_path' => '1,6', 'min_price' => '10']; } },
            new class { public function getList(string $fields, array $filter = []): array { return [['props_col' => 'season', 'props_value' => '夏季']]; } },
            new class { public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return [['feature_group_id' => 11]]; } },
            new class { public function getList(string $fields, array $filter = []): array { return [['bm_id' => 10, 'material_name' => '子物料', 'material_bn' => 'SUB-1', 'material_num' => '2']]; } }
        );

        $detail = $capability->detail(9);
        $template = $capability->importTemplate();

        $this->assertSame(9, $detail['bm_id']);
        $this->assertSame('详情物料', $detail['material_name']);
        $this->assertSame('BAR-9', $detail['material_code']);
        $this->assertSame('夏季', $detail['props']['season']);
        $this->assertSame('*:基础物料名称,*:基础物料编码', $template['csv_header']);
    }
}
