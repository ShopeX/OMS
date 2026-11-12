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
final class BranchCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/branch.php');
    }

    public function testCapabilityListsBranches(): void
    {
        require_once $this->root . '/app/ome/lib/capability/branch.php';

        $branchModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
            {
                return [
                    ['branch_id' => 1, 'branch_bn' => 'BR-A', 'name' => '主仓A', 'wms_id' => 16, 'type' => 'main'],
                    ['branch_id' => 2, 'branch_bn' => 'BR-B', 'name' => '主仓B', 'wms_id' => 16, 'type' => 'main'],
                ];
            }
        };

        $capability = new ome_capability_branch(
            $branchModel,
            new class {
                public function getList(string $fields, array $filter = []): array { return []; }
            },
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; }
            },
            new class {
                public function getWmsChannelList(): array { return []; }
                public function getBranchTypes(): array { return []; }
            },
            new class {
                public function getList(string $fields, array $filter = []): array { return []; }
            },
            []
        );
        $result = $capability->list();

        $this->assertSame(2, count($result['rows']));
        $this->assertSame('主仓A', $result['rows'][0]['name']);
        $this->assertSame('BR-B', $result['rows'][1]['branch_bn']);
    }

    public function testCapabilityReturnsBranchDetailWithSelectedCorpsAndShops(): void
    {
        require_once $this->root . '/app/ome/lib/capability/branch.php';

        $branchModel = new class {
            public function dump($filter, string $fields = '*'): ?array
            {
                return [
                    'branch_id' => 3,
                    'branch_bn' => 'BR-DETAIL',
                    'name' => '详情仓',
                    'cutoff_time' => '0930',
                    'latest_delivery_time' => '1830',
                ];
            }
        };
        $branchCorpModel = new class {
            public function getList(string $fields, array $filter = []): array
            {
                return [['corp_id' => 5], ['corp_id' => 9]];
            }
        };
        $shopModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [];
            }
        };
        $dlyCorpModel = new class {
            public function getList(string $fields, array $filter = []): array
            {
                return [['corp_id' => 5, 'name' => '物流A']];
            }
        };

        $capability = new ome_capability_branch(
            $branchModel,
            $branchCorpModel,
            $shopModel,
            new class {
                public function getWmsChannelList(): array { return []; }
                public function getBranchTypes(): array { return []; }
            },
            $dlyCorpModel,
            ['SHOP-A' => ['BR-DETAIL']]
        );
        $detail = $capability->detail(3);

        $this->assertSame(3, $detail['branch_id']);
        $this->assertSame([5, 9], $detail['selected_corp_ids']);
        $this->assertSame(['SHOP-A'], $detail['shop_bns']);
        $this->assertSame(9, $detail['cutoff_time_hour']);
        $this->assertSame(30, $detail['cutoff_time_minute']);
    }

    public function testCapabilityReturnsWmsCandidates(): void
    {
        require_once $this->root . '/app/ome/lib/capability/branch.php';

        $branchService = new class {
            public function getWmsChannelList(): array
            {
                return [
                    ['wms_id' => 16, 'wms_name' => 'WMS-A', 'node_id' => 'node-a'],
                    ['wms_id' => 17, 'wms_name' => 'WMS-B', 'node_id' => ''],
                ];
            }
        };

        $capability = new ome_capability_branch(
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array { return []; }
                public function dump($filter, string $fields = '*'): ?array { return null; }
            },
            new class {
                public function getList(string $fields, array $filter = []): array { return []; }
            },
            new class {
                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array { return []; }
            },
            $branchService,
            new class {
                public function getList(string $fields, array $filter = []): array { return []; }
            },
            []
        );
        $result = $capability->wmsList();

        $this->assertSame(1, count($result['list']));
        $this->assertSame('WMS-A', $result['list'][0]['wms_name']);
    }
}
