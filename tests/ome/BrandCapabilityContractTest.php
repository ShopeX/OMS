<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BrandCapabilityBrandModelFake
{
    public array $lastListFilter = [];
    public array $saved = [];
    public int $nextId = 9;
    public array $rows = [
        [
            'brand_id' => 3,
            'brand_code' => 'BR-3',
            'brand_name' => '品牌三',
            'brand_keywords' => '三',
            'brand_url' => 'https://example.test/3',
            'brand_desc' => 'desc',
            'brand_logo' => 'logo',
            'disabled' => 'false',
            'ordernum' => '2',
            'org_id' => 99,
        ],
    ];

    public function getList(string $columns, array $filter, int $offset, int $limit, string $orderBy): array
    {
        $this->lastListFilter = $filter;
        return array_slice($this->rows, $offset, $limit);
    }

    public function count(array $filter): int
    {
        return count($this->rows);
    }

    public function dump(array $filter, string $columns = '*'): ?array
    {
        foreach ($this->rows as $row) {
            if ((int) $row['brand_id'] === (int) ($filter['brand_id'] ?? 0)) {
                return $row;
            }
        }
        return null;
    }

    public function db_dump(array $filter): ?array
    {
        foreach ($this->rows as $row) {
            if (($filter['brand_code'] ?? null) === ($row['brand_code'] ?? null)
                && (int) ($filter['brand_id|noequal'] ?? 0) !== (int) $row['brand_id']) {
                return $row;
            }
        }
        return null;
    }

    public function save(array &$data): bool
    {
        $data['brand_id'] = $data['brand_id'] ?? $this->nextId;
        $this->saved = $data;
        return true;
    }

    public function delete(array $filter): bool
    {
        return true;
    }
}

final class BrandCapabilityGoodsModelFake
{
    public array $rows = [];

    public function getList(string $columns, array $filter, int $offset = 0, int $limit = -1): array
    {
        return $this->rows;
    }
}

final class BrandCapabilityContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        chdir(__DIR__ . '/../..');
        if (!defined('ROOT_DIR')) {
            define('ROOT_DIR', getcwd());
        }
        if (!defined('APP_DIR')) {
            define('APP_DIR', ROOT_DIR . '/app');
        }
        include_once 'config/config.php';
        include_once 'app/base/defined.php';
        include_once 'app/base/kernel.php';
        kernel::register_autoload();
    }

    public function testListIsGlobalAndNormalizesFinderRows(): void
    {
        $brands = new BrandCapabilityBrandModelFake();
        $capability = new ome_capability_brand($brands, new BrandCapabilityGoodsModelFake());

        $result = $capability->list(['page' => 1, 'page_size' => 10, 'orderBy' => 'ordernum', 'orderType' => 'desc', 'filters' => ['brand_name' => '品牌']]);

        $this->assertSame(['brand_name' => '品牌'], $brands->lastListFilter);
        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['pageSize']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(3, $result['list'][0]['brand_id']);
        $this->assertSame(false, $result['list'][0]['disabled']);
        $this->assertSame(2, $result['list'][0]['ordernum']);
        $this->assertArrayNotHasKey('org_id', $result['list'][0]);
    }

    public function testDetailUsesGlobalBrandMapping(): void
    {
        $capability = new ome_capability_brand(new BrandCapabilityBrandModelFake(), new BrandCapabilityGoodsModelFake());

        $result = $capability->detail(3);

        $this->assertSame(3, $result['brand_id']);
        $this->assertSame(false, $result['disabled']);
        $this->assertArrayNotHasKey('org_id', $result);
    }

    public function testCreateAndUpdateEnforceBusinessValidationAndCodeUniqueness(): void
    {
        $brands = new BrandCapabilityBrandModelFake();
        $brands->rows[] = [
            'brand_id' => 4,
            'brand_code' => 'DUP',
            'brand_name' => '另一个品牌',
        ];
        $capability = new ome_capability_brand($brands, new BrandCapabilityGoodsModelFake());

        $created = $capability->create(['brand_code' => ' NEW ', 'brand_name' => ' 新品牌 ', 'brand_keywords' => '', 'brand_url' => '']);
        $this->assertSame(9, $created['brand_id']);
        $this->assertSame('NEW', $brands->saved['brand_code']);
        $this->assertSame('新品牌', $brands->saved['brand_name']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('品牌编码已存在');
        $capability->update(3, ['brand_code' => 'DUP']);
    }

    public function testCreateStartsAndCommitsTransaction(): void
    {
        $brands = new BrandCapabilityBrandModelFake();
        $goods = new BrandCapabilityGoodsModelFake();
        $database = new class {
            public int $begins = 0;
            public int $commits = 0;
            public int $rollbacks = 0;

            public function beginTransaction(): string
            {
                $this->begins++;
                return 'tx';
            }

            public function commit($tx = null): void
            {
                $this->commits++;
            }

            public function rollBack($tx = null): void
            {
                $this->rollbacks++;
            }
        };

        $capability = new ome_capability_brand($brands, $goods, $database);
        $created = $capability->create(['brand_code' => 'NEW2', 'brand_name' => '新品牌2', 'brand_keywords' => '', 'brand_url' => '']);

        $this->assertSame(9, $created['brand_id']);
        $this->assertSame(1, $database->begins);
        $this->assertSame(1, $database->commits);
        $this->assertSame(0, $database->rollbacks);
    }

    public function testCreateRollsBackWhenSaveReturnsFalse(): void
    {
        $brands = new class {
            public array $rows = [];

            public function dump(array $filter, string $columns = '*'): ?array
            {
                return null;
            }

            public function db_dump(array $filter): ?array
            {
                return null;
            }

            public function save(array &$data): bool
            {
                return false;
            }
        };
        $database = new class {
            public int $begins = 0;
            public int $commits = 0;
            public int $rollbacks = 0;

            public function beginTransaction(): string
            {
                $this->begins++;
                return 'tx';
            }

            public function commit($tx = null): void
            {
                $this->commits++;
            }

            public function rollBack($tx = null): void
            {
                $this->rollbacks++;
            }
        };

        $capability = new ome_capability_brand($brands, new BrandCapabilityGoodsModelFake(), $database);

        try {
            $capability->create(['brand_code' => 'NEW3', 'brand_name' => '新品牌3', 'brand_keywords' => '', 'brand_url' => '']);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('品牌保存失败', $exception->getMessage());
        }

        $this->assertSame(1, $database->begins);
        $this->assertSame(0, $database->commits);
        $this->assertSame(1, $database->rollbacks);
    }

    public function testDeleteRefusesReferencedBrand(): void
    {
        $goods = new BrandCapabilityGoodsModelFake();
        $goods->rows = [['goods_id' => 100]];
        $capability = new ome_capability_brand(new BrandCapabilityBrandModelFake(), $goods);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('品牌已经被使用');
        $capability->delete(3);
    }
}
