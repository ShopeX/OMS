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
final class LogisticsCorpCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/dly/corp.php');
    }

    public function testCapabilityListsEnabledCorps(): void
    {
        require_once $this->root . '/app/ome/lib/capability/dly/corp.php';

        $model = new class {
            public function getList(string $fields, array $filter = []): array
            {
                return [
                    ['corp_id' => 5, 'name' => '物流A'],
                    ['corp_id' => 9, 'name' => '物流B'],
                ];
            }
        };

        $capability = new ome_capability_dly_corp($model);
        $result = $capability->list();

        $this->assertSame(2, count($result['list']));
        $this->assertSame('物流A', $result['list'][0]['name']);
    }

    public function testCapabilityReturnsCorpDetail(): void
    {
        require_once $this->root . '/app/ome/lib/capability/dly/corp.php';

        $model = new class {
            public function dump($corpId): ?array
            {
                return $corpId === 5 ? ['corp_id' => 5, 'name' => '物流A', 'type' => 'express'] : null;
            }
        };

        $capability = new ome_capability_dly_corp($model);
        $detail = $capability->detail(5);

        $this->assertSame(5, $detail['detail']['corp_id']);
        $this->assertSame('物流A', $detail['detail']['name']);
    }
}
