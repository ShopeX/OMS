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
final class MaterialTypeCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/goods/type.php');
    }

    public function testCapabilityListsMaterialTypes(): void
    {
        require_once $this->root . '/app/ome/lib/capability/goods/type.php';

        $model = new class {
            public function list(array $request): array
            {
                return [
                    'list' => [
                        ['type_id' => 1, 'name' => '类型A', 'alias' => 'A'],
                        ['type_id' => 2, 'name' => '类型B', 'alias' => 'B'],
                    ],
                    'total' => 2,
                    'page' => 1,
                    'pageSize' => 20,
                ];
            }
        };

        $capability = new ome_capability_goods_type($model);
        $result = $capability->list(['page' => 1, 'pageSize' => 20]);

        $this->assertSame(2, count($result['list']));
        $this->assertSame('类型A', $result['list'][0]['name']);
    }

    public function testCapabilityReturnsMaterialTypeDetail(): void
    {
        require_once $this->root . '/app/ome/lib/capability/goods/type.php';

        $model = new class {
            public function detail(int $typeId): ?array
            {
                return $typeId === 3 ? ['type_id' => 3, 'name' => '详情类型', 'setting' => []] : null;
            }
        };

        $capability = new ome_capability_goods_type($model);
        $detail = $capability->detail(3);

        $this->assertSame(3, $detail['type_id']);
        $this->assertSame('详情类型', $detail['name']);
    }
}
