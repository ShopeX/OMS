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
final class AftersalesUnifiedCapabilityContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/ome/lib/capability/aftersales/unified.php');
    }

    public function testRequiresUnifiedVisibleSourcesForNonSuperUser(): void
    {
        $sources = [
            'refund' => [
                ['source_id' => 101, 'org_id' => 11, 'occur_time' => 300, 'biz_bn' => 'RF-101'],
                ['source_id' => 102, 'org_id' => 22, 'occur_time' => 200, 'biz_bn' => 'RF-102'],
            ],
            'return' => [
                ['source_id' => 201, 'org_id' => 11, 'occur_time' => 250, 'biz_bn' => 'RT-201'],
            ],
            'reship' => [
                ['source_id' => 301, 'org_id' => 22, 'occur_time' => 150, 'biz_bn' => 'RS-301'],
            ],
        ];
        $calls = [];
        $capability = $this->newCapability($sources, $calls);
        $context = $this->context(
            ['aftersale_return_refund_apply', 'aftersale_return_apply', 'ome.action.return_reshipping.view'],
            false,
            [11]
        );

        $result = $capability->list(['query' => ''], $context);

        $this->assertSame(['refund', 'return', 'reship'], array_keys($calls));
        foreach ($calls as $filters) {
            $this->assertSame([11], $filters['org_id']);
        }
        $this->assertSame(['refund', 'return'], array_column($result['items'], 'kind'));
        $this->assertSame([101, 201], array_column($result['items'], 'source_id'));
    }

    public function testReturnsExactTotalCountAcrossRefundReturnReshipUnion(): void
    {
        $capability = $this->newCapability([
            'refund' => [['source_id' => 101, 'org_id' => 11, 'occur_time' => 100, 'biz_bn' => 'RF-101']],
            'return' => [
                ['source_id' => 201, 'org_id' => 11, 'occur_time' => 90, 'biz_bn' => 'RT-201'],
                ['source_id' => 202, 'org_id' => 11, 'occur_time' => 80, 'biz_bn' => 'RT-202'],
            ],
            'reship' => [['source_id' => 301, 'org_id' => 11, 'occur_time' => 70, 'biz_bn' => 'RS-301']],
        ]);

        $result = $capability->list(['query' => ''], $this->context());

        $this->assertSame(4, $result['total_count']);
        $this->assertSame(4, $result['returned_count']);
    }

    public function testFiltersRowsByExactBusinessOrOrderNumberAcrossSources(): void
    {
        $capability = $this->newCapability([
            'refund' => [[
                'source_id' => 101,
                'org_id' => 11,
                'refund_apply_bn' => 'RF-101',
                'order_bn' => 'SO-101',
                'occur_time' => 300,
            ]],
            'return' => [[
                'source_id' => 201,
                'org_id' => 11,
                'return_bn' => 'RT-201',
                'order_bn' => 'SO-201',
                'occur_time' => 200,
            ]],
            'reship' => [[
                'source_id' => 301,
                'org_id' => 11,
                'reship_bn' => 'RS-301',
                'order_bn' => 'SO-301',
                'occur_time' => 100,
            ]],
        ]);

        $result = $capability->list(['query' => 'RT-201'], $this->context());

        $this->assertSame(1, $result['total_count']);
        $this->assertSame([201], array_column($result['items'], 'source_id'));
        $this->assertSame('RT-201', $result['items'][0]['summary']['biz_bn']);
    }

    public function testExcludesOrderNumberPrefixFalsePositives(): void
    {
        $capability = $this->newCapability([
            'refund' => [
                ['source_id' => 101, 'org_id' => 11, 'order_bn' => 'SO-REFUNDED', 'occur_time' => 200],
                ['source_id' => 102, 'org_id' => 11, 'order_bn' => 'SO-REFUNDED-EXTRA', 'occur_time' => 100],
            ],
            'return' => [],
            'reship' => [],
        ]);

        $result = $capability->list(['query' => 'SO-REFUNDED'], $this->context());

        $this->assertSame(1, $result['total_count']);
        $this->assertSame([101], array_column($result['items'], 'source_id'));
        $this->assertSame('SO-REFUNDED', $result['items'][0]['summary']['order_bn']);
    }

    public function testAppliesGlobalSortAcrossUnionRows(): void
    {
        $capability = $this->newCapability([
            'refund' => [['source_id' => 101, 'org_id' => 11, 'occur_time' => 100, 'biz_bn' => 'RF-101']],
            'return' => [['source_id' => 201, 'org_id' => 11, 'occur_time' => 300, 'biz_bn' => 'RT-201']],
            'reship' => [['source_id' => 301, 'org_id' => 11, 'occur_time' => 200, 'biz_bn' => 'RS-301']],
        ]);

        $result = $capability->list(
            ['query' => '', 'order_by' => 'occur_time', 'order_type' => 'desc'],
            $this->context()
        );

        $this->assertSame(['return', 'reship', 'refund'], array_column($result['items'], 'kind'));
        $this->assertSame([300, 200, 100], array_column(array_column($result['items'], 'summary'), 'occur_time'));
    }

    public function testIncludesBusinessFacingSummaryFields(): void
    {
        $capability = $this->newCapability([
            'refund' => [[
                'source_id' => 101,
                'org_id' => 11,
                'status' => 'refund',
                'status_text' => '已退款',
                'biz_bn' => 'RF-101',
                'order_bn' => 'SO-1001',
                'amount' => 199.5,
                'occur_time' => '2026-07-26 10:00:00',
            ]],
            'return' => [],
            'reship' => [],
        ]);

        $result = $capability->list(['query' => ''], $this->context());

        $this->assertSame('refund', $result['items'][0]['kind']);
        $this->assertSame('RF-101', $result['items'][0]['summary']['biz_bn']);
        $this->assertSame('SO-1001', $result['items'][0]['summary']['order_bn']);
        $this->assertSame('refund', $result['items'][0]['summary']['status']);
        $this->assertSame('已退款', $result['items'][0]['summary']['status_text']);
        $this->assertSame(199.5, $result['items'][0]['summary']['amount']);
        $this->assertSame('2026-07-26 10:00:00', $result['items'][0]['summary']['occur_time']);
    }

    public function testEnrichesVisibleRowsFromOneUniqueBatchShopLookup(): void
    {
        $shopFilter = [];
        $calls = null;
        $shopRows = [
            ['shop_id' => 11, 'name' => '店铺A', 'shop_bn' => 'SHOP-A'],
            ['shop_id' => 12, 'name' => '店铺B', 'shop_bn' => 'SHOP-B'],
        ];
        $capability = $this->newCapability([
            'refund' => [
                ['source_id' => 101, 'org_id' => 11, 'shop_id' => 11, 'occur_time' => 300],
                ['source_id' => 102, 'org_id' => 11, 'shop_id' => 12, 'occur_time' => 200],
                ['source_id' => 103, 'org_id' => 11, 'shop_id' => 11, 'occur_time' => 100],
            ],
            'return' => [
                ['source_id' => 201, 'org_id' => 22, 'shop_id' => 99, 'occur_time' => 400],
            ],
            'reship' => [],
        ], $calls, $shopRows, $shopFilter);

        $result = $capability->list(['query' => ''], $this->context());

        $this->assertSame([11, 12], $shopFilter['shop_id']);
        $this->assertSame('店铺A', $result['items'][0]['summary']['shop_name']);
        $this->assertSame('SHOP-A', $result['items'][0]['summary']['shop_bn']);
        $this->assertSame('店铺B', $result['items'][1]['summary']['shop_name']);
        $this->assertSame('SHOP-B', $result['items'][1]['summary']['shop_bn']);
        $this->assertSame('店铺A', $result['items'][2]['summary']['shop_name']);
        $this->assertSame('SHOP-A', $result['items'][2]['summary']['shop_bn']);
    }

    public function testLeavesMissingAndUnmatchedShopFieldsEmpty(): void
    {
        $shopFilter = [];
        $calls = null;
        $capability = $this->newCapability([
            'refund' => [
                ['source_id' => 101, 'org_id' => 11, 'shop_id' => 0, 'occur_time' => 200],
                ['source_id' => 102, 'org_id' => 11, 'shop_id' => 999, 'occur_time' => 100],
            ],
            'return' => [],
            'reship' => [],
        ], $calls, [], $shopFilter);

        $result = $capability->list(['query' => ''], $this->context());

        $this->assertSame([999], $shopFilter['shop_id']);
        foreach ($result['items'] as $item) {
            $this->assertSame('', $item['summary']['shop_name']);
            $this->assertSame('', $item['summary']['shop_bn']);
        }
    }

    public function testEnrichesVisibleRowsFromOneUniqueBatchOrgLookup(): void
    {
        $orgFilter = [];
        $shopFilter = null;
        $calls = null;
        $orgRows = [
            ['org_id' => 11, 'name' => '总部'],
            ['org_id' => 12, 'name' => '华东组织'],
        ];
        $capability = $this->newCapability([
            'refund' => [
                ['source_id' => 101, 'org_id' => 11, 'occur_time' => 300],
                ['source_id' => 102, 'org_id' => 12, 'occur_time' => 200],
                ['source_id' => 103, 'org_id' => 11, 'occur_time' => 100],
            ],
            'return' => [],
            'reship' => [],
        ], $calls, [], $shopFilter, $orgRows, $orgFilter);

        $result = $capability->list(['query' => ''], $this->context(superUser: true));

        $this->assertSame([11, 12], $orgFilter['org_id']);
        $this->assertSame('总部', $result['items'][0]['summary']['org_name']);
        $this->assertSame('华东组织', $result['items'][1]['summary']['org_name']);
        $this->assertSame('总部', $result['items'][2]['summary']['org_name']);
    }

    public function testLeavesMissingAndUnmatchedOrgFieldsEmpty(): void
    {
        $orgFilter = [];
        $shopFilter = null;
        $calls = null;
        $capability = $this->newCapability([
            'refund' => [
                ['source_id' => 101, 'org_id' => 0, 'occur_time' => 200],
                ['source_id' => 102, 'org_id' => 999, 'occur_time' => 100],
            ],
            'return' => [],
            'reship' => [],
        ], $calls, [], $shopFilter, [], $orgFilter);

        $result = $capability->list(['query' => ''], $this->context(superUser: true));

        $this->assertSame([999], $orgFilter['org_id']);
        foreach ($result['items'] as $item) {
            $this->assertSame('', $item['summary']['org_name']);
        }
    }

    public function testPreservesDomainSpecificStatusTextWithoutCollapsingRawStatuses(): void
    {
        $capability = $this->newCapability([
            'refund' => [['source_id' => 101, 'org_id' => 11, 'status' => 'refund', 'status_text' => '退款完成', 'occur_time' => 300]],
            'return' => [['source_id' => 201, 'org_id' => 11, 'status' => 'return', 'status_text' => '退货入库', 'occur_time' => 200]],
            'reship' => [['source_id' => 301, 'org_id' => 11, 'status' => 'reship', 'status_text' => '补发待发货', 'occur_time' => 100]],
        ]);

        $result = $capability->list(['query' => ''], $this->context());
        $summaries = array_column($result['items'], 'summary');

        $this->assertSame(['refund', 'return', 'reship'], array_column($summaries, 'status'));
        $this->assertSame(['退款完成', '退货入库', '补发待发货'], array_column($summaries, 'status_text'));
        $this->assertCount(3, array_unique(array_column($summaries, 'status_text')));
    }

    public function testPaginatesAfterUnionNotBefore(): void
    {
        $capability = $this->newCapability([
            'refund' => [['source_id' => 101, 'org_id' => 11, 'occur_time' => 400, 'biz_bn' => 'RF-101']],
            'return' => [['source_id' => 201, 'org_id' => 11, 'occur_time' => 300, 'biz_bn' => 'RT-201']],
            'reship' => [['source_id' => 301, 'org_id' => 11, 'occur_time' => 200, 'biz_bn' => 'RS-301']],
        ]);

        $result = $capability->list(
            ['query' => '', 'page' => 2, 'page_size' => 1, 'order_by' => 'occur_time', 'order_type' => 'desc'],
            $this->context()
        );

        $this->assertSame(3, $result['total_count']);
        $this->assertSame(1, $result['returned_count']);
        $this->assertSame('return', $result['items'][0]['kind']);
        $this->assertTrue($result['has_more']);
    }

    public function testReturnsPartialWarningOnlyWhenSourceWindowIsActuallyBounded(): void
    {
        $unbounded = $this->newCapability([
            'refund' => [['source_id' => 101, 'org_id' => 11, 'occur_time' => 100, 'biz_bn' => 'RF-101']],
            'return' => [],
            'reship' => [],
        ]);
        $complete = $unbounded->list(['query' => '', 'source_window_limit' => 10], $this->context());

        $bounded = $this->newCapability([
            'refund' => [
                ['source_id' => 101, 'org_id' => 11, 'occur_time' => 100, 'biz_bn' => 'RF-101'],
                ['source_id' => 102, 'org_id' => 11, 'occur_time' => 90, 'biz_bn' => 'RF-102'],
            ],
            'return' => [],
            'reship' => [],
        ]);
        $partial = $bounded->list(['query' => '', 'source_window_limit' => 2], $this->context());

        $this->assertFalse($complete['partial']);
        $this->assertSame([], $complete['warnings']);
        $this->assertTrue($partial['partial']);
        $this->assertSame(['one or more source windows were bounded'], $partial['warnings']);
    }

    private function newCapability(
        array $sources,
        ?array &$calls = null,
        array $shopRows = [],
        ?array &$shopFilter = null,
        array $orgRows = [],
        ?array &$orgFilter = null
    ): object
    {
        require_once $this->root . '/app/mcpapi/lib/auth/context.php';
        require_once $this->root . '/app/ome/lib/capability/aftersales/unified.php';

        $models = [];
        foreach (['refund', 'return', 'reship'] as $kind) {
            $models[$kind] = new class($sources[$kind], $calls, $kind) {
                private array $rows;
                private $calls;
                private string $kind;

                public function __construct(array $rows, ?array &$calls, string $kind)
                {
                    $this->rows = $rows;
                    $this->calls = &$calls;
                    $this->kind = $kind;
                }

                public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1, string $orderBy = ''): array
                {
                    if ($this->calls !== null) {
                        $this->calls[$this->kind] = $filter;
                    }
                    return $this->rows;
                }
            };
        }

        $operationOpsModel = new class {
            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 11]];
            }
        };

        $operationOrganizationModel = new class($orgRows, $orgFilter) {
            private array $rows;
            private $filter;

            public function __construct(array $rows, ?array &$filter)
            {
                $this->rows = $rows;
                $this->filter =& $filter;
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                if ($this->filter !== null) {
                    $this->filter = $filter;
                }
                return $this->rows;
            }
        };

        $shopModel = new class($shopRows, $shopFilter) {
            private array $rows;
            private $filter;

            public function __construct(array $rows, ?array &$filter)
            {
                $this->rows = $rows;
                $this->filter =& $filter;
            }

            public function getList(string $fields, array $filter = [], int $offset = 0, int $limit = -1): array
            {
                if ($this->filter !== null) {
                    $this->filter = $filter;
                }
                return $this->rows;
            }
        };

        $arguments = [
            $models['refund'],
            $models['return'],
            $models['reship'],
            $operationOpsModel,
            $shopModel,
            $operationOrganizationModel,
        ];

        return new ome_capability_aftersales_unified(...$arguments);
    }

    private function context(array $permissions = [
        'aftersale_return_refund_apply',
        'aftersale_return_apply',
        'ome.action.return_reshipping.view',
    ], bool $superUser = false, array $orgIds = [11]): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'req',
            'client',
            'subject',
            'tenant',
            'https://oms.example.internal',
            9,
            [1],
            $permissions,
            $permissions,
            time() + 3600,
            $superUser
        );
    }
}
