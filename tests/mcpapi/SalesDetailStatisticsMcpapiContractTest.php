<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SalesDetailStatisticsMcpapiContractTest extends TestCase
{
    private string $cwdBeforeTest;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cwdBeforeTest = getcwd();
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
        kernel::register_autoload();
    }

    protected function tearDown(): void
    {
        chdir($this->cwdBeforeTest);
        parent::tearDown();
    }

    public function testSalesDetailStatisticsReadOnlyContract(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $source = $this->sourceFixture();

        $capability = new omeanalysts_capability_sales_detail_statistics(
            static fn(string $materialType): object => $source
        );
        $result = $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
            'page' => 1,
            'page_size' => 20,
        ]);

        $this->assertSame(['detail', 'summary', 'aggregations'], array_keys($result));
        $this->assertSame('SKU-1', $result['detail']['list'][0]['bn']);
        $this->assertSame(1, $result['detail']['total']);
        $this->assertSame(['销售量', '销售额', '日均销售额', '日均销售量', '销售毛利', '销售毛利率', '退货量', '退货总额'], array_column($result['summary'], 'label'));
        $this->assertSame(['sale_num', 'sale_amount', 'day_amounts', 'day_nums', 'gross_sales', 'gross_sales_rate', 'reship_nums', 'reship_amounts'], array_column($result['summary'], 'key'));
        $this->assertSame([3, 12.5, 4.5, 1.5, 5.5, 0.44, 2, 1.5], array_column($result['summary'], 'value'));
        $this->assertArrayHasKey('by_product', $result['aggregations']);
        $this->assertArrayHasKey('by_shop', $result['aggregations']);
        $this->assertSame(['商品一'], $result['aggregations']['by_product']['labels']);
        $this->assertSame(['店铺一'], $result['aggregations']['by_shop']['labels']);
        $this->assertArrayNotHasKey('by_date', $result['aggregations']);
        $this->assertArrayNotHasKey('filters', $result);
        $this->assertArrayNotHasKey('columns', $result);
    }

    public function testSalesDetailStatisticsRequiresBothDates(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('time_from must be provided');
        $capability->execute(['material_type' => 'basic_material']);
    }

    public function testSalesDetailStatisticsRejectsIncompatiblePerModelFilters(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            static fn(string $materialType): object => new stdClass()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('goods_bn is only supported for sales_material');
        $capability->execute([
            'material_type' => 'basic_material',
            'goods_bn' => 'SKU-1',
        ]);
    }

    public function testSalesDetailStatisticsReturnsEmptyAggregationObjectsWhenDisabled(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );
        $result = $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
            'include_aggregations' => false,
        ]);

        foreach (['by_product', 'by_shop'] as $key) {
            $this->assertIsArray($result['aggregations'][$key]);
            $this->assertSame([], $result['aggregations'][$key]['labels']);
            $this->assertSame([], $result['aggregations'][$key]['series']);
        }
    }

    public function testSalesDetailStatisticsAggregatesFullResultAndPreservesSalesMaterialShopGap(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $rows = [
            ['goods_bn' => 'SALE-1', 'goods_name' => '销售物料一', 'shop_id' => 'shop-1', 'sale_amount' => 10],
            ['goods_bn' => 'SALE-2', 'goods_name' => '销售物料二', 'shop_id' => 'shop-2', 'sale_amount' => 20],
        ];
        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture($rows)
        );
        $result = $capability->execute([
            'material_type' => 'sales_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
            'page' => 1,
            'page_size' => 1,
        ]);

        $this->assertCount(1, $result['detail']['list']);
        $this->assertSame(['销售物料二', '销售物料一'], $result['aggregations']['by_product']['labels']);
        $this->assertSame([20.0, 10.0], $result['aggregations']['by_product']['series'][0]['data']);
        $this->assertSame([], $result['aggregations']['by_shop']['labels']);
        $this->assertSame([], $result['aggregations']['by_shop']['series']);
    }

    public function testSalesDetailStatisticsPreservesSalesAndBasicNativeGroupingAndSortsAggregations(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $rows = [
            [
                'shop_id' => 'shop-1',
                'shop_name' => '店铺一',
                'sales_material_bn' => 'SM-1',
                'product_id' => 101,
                'bn' => 'SKU-1',
                'name' => '基础物料一',
                'sale_amount' => 8,
            ],
            [
                'shop_id' => 'shop-2',
                'shop_name' => '店铺二',
                'sales_material_bn' => 'SM-2',
                'product_id' => 202,
                'bn' => 'SKU-2',
                'name' => '基础物料二',
                'sale_amount' => 25,
            ],
        ];
        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture($rows)
        );
        $result = $capability->execute([
            'material_type' => 'sales_and_basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
            'page' => 1,
            'page_size' => 20,
        ]);

        $this->assertSame(['SM-1', 'SM-2'], array_column($result['detail']['list'], 'sales_material_bn'));
        $this->assertSame([25.0, 8.0], $result['aggregations']['by_product']['series'][0]['data']);
        $this->assertSame(['基础物料二', '基础物料一'], $result['aggregations']['by_product']['labels']);
        $this->assertSame([25.0, 8.0], $result['aggregations']['by_shop']['series'][0]['data']);
        $this->assertSame(['店铺二', '店铺一'], $result['aggregations']['by_shop']['labels']);
    }

    public function testSalesDetailStatisticsResolvesShopNamesFromRawShopIds(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $rows = [
            ['bn' => 'SKU-1', 'name' => '商品一', 'shop_id' => '9168a9da00000000000000000000c117', 'sale_amount' => 30],
            ['bn' => 'SKU-2', 'name' => '商品二', 'shop_id' => 'aabbccdd000000000000000000000001', 'sale_amount' => 10],
        ];
        $shopNames = [
            '9168a9da00000000000000000000c117' => '视频号小店',
            'aabbccdd000000000000000000000001' => '天猫旗舰店',
        ];
        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture($rows),
            static fn(array $shopIds): array => array_intersect_key($shopNames, array_flip($shopIds)),
        );
        $result = $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
            'page' => 1,
            'page_size' => 20,
        ]);

        $this->assertSame(['视频号小店', '天猫旗舰店'], $result['aggregations']['by_shop']['labels']);
        $this->assertSame('视频号小店', $result['detail']['list'][0]['shop_name']);
        $this->assertSame('天猫旗舰店', $result['detail']['list'][1]['shop_name']);
    }

    public function testSalesDetailStatisticsRejectsUnsafeOrderByAndMalformedPagingFlags(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('order_by is not supported');
        $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
            'order_by' => 'sale_amount; DROP TABLE sales',
        ]);
    }

    public function testSalesDetailStatisticsRejectsMalformedPageAndFlagTypes(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('page must be an integer');
        $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
            'page' => '1',
        ]);
    }

    public function testSalesDetailStatisticsRejectsMalformedFlagTypes(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('include_detail must be a boolean');
        $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
            'include_detail' => 1,
        ]);
    }

    public function testSalesDetailStatisticsRejectsUnsupportedTopLevelFields(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );

        foreach (['filters', 'columns', 'by_date'] as $field) {
            try {
                $capability->execute(['material_type' => 'basic_material', $field => []]);
                $this->fail($field . ' should be rejected');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame($field . ' is not supported', $exception->getMessage());
            }
        }
    }

    public function testSalesDetailStatisticsRejectsRequestOrgId(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('org_id is not supported');
        $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
            'org_id' => ['org-requested'],
        ]);
    }

    public function testSalesDetailStatisticsValidatesModelSpecificFieldTypes(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );
        foreach ([
            ['material_type' => 'basic_material', 'bn' => ['SKU-1'], 'field' => 'bn'],
            ['material_type' => 'sales_material', 'goods_bn' => 123, 'field' => 'goods_bn'],
            ['material_type' => 'sales_and_basic_material', 'sales_material_bn' => ['SM-1'], 'field' => 'sales_material_bn'],
        ] as $case) {
            $field = $case['field'];
            unset($case['field']);
            try {
                $capability->execute($case);
                $this->fail($field . ' should require a string');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame($field . ' must be a string', $exception->getMessage());
            }
        }
    }

    public function testSalesDetailStatisticsExecutesDefaultResolverPathForEachMaterialType(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $sources = [
            'basic_material' => $this->sourceFixture(),
            'sales_material' => $this->sourceFixture([['goods_bn' => 'GM-1', 'sale_amount' => 1]]),
            'sales_and_basic_material' => $this->sourceFixture([['sales_material_bn' => 'SM-1', 'product_id' => 1, 'shop_id' => 'shop-1', 'sale_amount' => 1]]),
        ];
        $capability = new class($sources) extends omeanalysts_capability_sales_detail_statistics {
            public function __construct(private array $sources)
            {
                parent::__construct();
            }

            protected function resolveNativeModel(string $materialType): object
            {
                return $this->sources[$materialType];
            }
        };

        foreach (array_keys($sources) as $materialType) {
            $result = $capability->execute([
                'material_type' => $materialType,
                'time_from' => '2026-07-01',
                'time_to' => '2026-07-21',
            ]);
            $this->assertArrayHasKey('detail', $result);
        }
    }

    public function testSalesDetailStatisticsReadsLargeAggregationInAdvancingBatches(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $rows = [];
        for ($index = 1; $index <= 205; $index++) {
            $rows[] = [
                'bn' => 'SKU-' . $index,
                'name' => '商品' . $index,
                'shop_id' => 'shop-1',
                'shop_name' => '店铺一',
                'sale_amount' => $index,
            ];
        }
        $source = $this->sourceFixture($rows);
        $capability = new omeanalysts_capability_sales_detail_statistics(
            static fn(string $materialType): object => $source
        );
        $result = $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
        ]);

        $aggregationCalls = array_values(array_filter($source->listCalls, static fn(array $call): bool => $call['limit'] === 200));
        $this->assertSame([0, 200], array_column($aggregationCalls, 'offset'));
        $this->assertCount(205, $result['aggregations']['by_product']['labels']);
    }

    public function testSalesDetailStatisticsUsesBoundedAggregationCallsAndNormalizesModelResponses(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $source = $this->sourceFixture([], false, 'not-a-count');
        $capability = new omeanalysts_capability_sales_detail_statistics(
            static fn(string $materialType): object => $source
        );
        $result = $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
            'page' => 2,
            'page_size' => 5,
            'include_detail' => true,
            'include_aggregations' => true,
        ]);

        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 0], array_column($result['summary'], 'value'));
        $this->assertSame(0, $result['detail']['total']);
        $this->assertNotEmpty($source->listCalls);
        foreach ($source->listCalls as $call) {
            $this->assertGreaterThanOrEqual(0, $call['limit']);
            $this->assertLessThanOrEqual(200, $call['limit']);
        }
    }

    public function testSalesDetailStatisticsDefaultResolverSelectsEachNativeModel(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics();
        $method = new ReflectionMethod($capability, 'nativeModelClass');
        $method->setAccessible(true);

        $this->assertSame('omeanalysts_mdl_ome_products', $method->invoke($capability, 'basic_material'));
        $this->assertSame('omeanalysts_mdl_sales_goods', $method->invoke($capability, 'sales_material'));
        $this->assertSame('omeanalysts_mdl_sales_products', $method->invoke($capability, 'sales_and_basic_material'));
    }

    public function testSalesDetailStatisticsPassesNormalizedRequestToModelFixture(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $source = $this->sourceFixture();
        $capability = new omeanalysts_capability_sales_detail_statistics(
            static fn(string $materialType): object => $source
        );
        $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
            'page' => 2,
            'page_size' => 5,
            'include_detail' => false,
            'include_aggregations' => false,
            'order_by' => 'sale_amount',
            'order_type' => 'asc',
        ]);

        $this->assertSame(2, $source->lastRequest['page']);
        $this->assertSame(5, $source->lastRequest['page_size']);
        $this->assertFalse($source->lastRequest['include_detail']);
        $this->assertFalse($source->lastRequest['include_aggregations']);
        $this->assertSame('sale_amount', $source->lastRequest['order_by']);
        $this->assertSame('asc', $source->lastRequest['order_type']);
    }

    public function testSalesDetailStatisticsInjectsAllowedOrganizationsIntoNormalizedRequest(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $source = $this->sourceFixture();
        $capability = new omeanalysts_capability_sales_detail_statistics(
            static fn(string $materialType): object => $source
        );

        $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-21',
        ], ['org-allowed-1', 'org-allowed-2']);

        $this->assertSame(['org-allowed-1', 'org-allowed-2'], $source->lastRequest['org_id']);
    }

    public function testSalesDetailStatisticsRejectsInvalidDates(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('time_from must be a valid date in YYYY-MM-DD format');
        $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-02-30',
            'time_to' => '2026-03-01',
        ]);
    }

    public function testSalesDetailStatisticsRejectsReversedDates(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('time_from must be on or before time_to');
        $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-21',
            'time_to' => '2026-07-01',
        ]);
    }

    public function testSalesDetailStatisticsRejectsPartialDateRange(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('time_to must be provided when time_from is provided');
        $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
        ]);
    }

    public function testSalesDetailStatisticsRejectsMissingEndDate(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('time_from must be provided when time_to is provided');
        $capability->execute([
            'material_type' => 'basic_material',
            'time_to' => '2026-07-01',
        ]);
    }

    public function testSalesDetailStatisticsAllowsExactly31Days(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );
        $result = $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-08-01',
        ]);

        $this->assertSame(1, $result['detail']['total']);
    }

    public function testSalesDetailStatisticsRejectsDateRangeLongerThan31Days(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';

        $capability = new omeanalysts_capability_sales_detail_statistics(
            fn(string $materialType): object => $this->sourceFixture()
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('date range must not exceed 31 days');
        $capability->execute([
            'material_type' => 'basic_material',
            'time_from' => '2026-07-01',
            'time_to' => '2026-08-02',
        ]);
    }

    public function testSalesDetailStatisticsControllerIsReadOnlyAndDelegates(): void
    {
        require_once $this->root . '/app/omeanalysts/lib/capability/sales/detail/statistics.php';
        require_once $this->root . '/app/mcpapi/controller/v1/sales/detail/statistics.php';

        if (!class_exists('desktop_auth_context', false)) {
            class_alias(SalesDetailStatisticsContractDesktopAuthContextStub::class, 'desktop_auth_context');
        }
        SalesDetailStatisticsContractDesktopAuthContextStub::reset();

        $capability = new class extends omeanalysts_capability_sales_detail_statistics {
            public array $lastRequest = [];
            public array $lastAllowedOrganizations = [];

            public function __construct() {}

            public function execute(array $request, array $allowedOrganizations = []): array
            {
                $this->lastRequest = $request;
                $this->lastAllowedOrganizations = $allowedOrganizations;

                return [
                    'detail' => ['list' => [], 'total' => 0, 'page' => 1, 'page_size' => 20],
                    'summary' => [],
                    'aggregations' => ['by_product' => [], 'by_shop' => []],
                ];
            }
        };

        $desktopUser = new class(['org-allowed-1', 'org-allowed-2']) extends desktop_user {
            public array $virtualLoginCalls = [];

            public function __construct(private array $organizations) {}

            public function is_super()
            {
                return false;
            }

            public function setVirtualLogin($userId)
            {
                $this->virtualLoginCalls[] = $userId;
            }

            public function get_organization_permission()
            {
                return $this->organizations;
            }
        };

        $controller = new mcpapi_ctl_v1_sales_detail_statistics($capability, $desktopUser);
        $response = $controller->index($this->context(false, 99), ['material_type' => 'basic_material']);

        $this->assertSame(200, $response->status());
        $this->assertSame(['detail', 'summary', 'aggregations'], array_keys($response->body()));
        $this->assertSame(['org-allowed-1', 'org-allowed-2'], $capability->lastAllowedOrganizations);
        $this->assertSame([99], $desktopUser->virtualLoginCalls);
        $this->assertSame([99], SalesDetailStatisticsContractDesktopAuthContextStub::$userIds);

        $response = $controller->index($this->context(true, 99), [
            'material_type' => 'basic_material',
        ]);

        $this->assertSame(200, $response->status());
        $this->assertSame([], $capability->lastAllowedOrganizations);

        $response = $controller->index($this->context(false, 99), [
            'material_type' => 'basic_material',
            'org_id' => ['org-allowed-2', 'org-denied'],
        ]);

        $methods = array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(mcpapi_ctl_v1_sales_detail_statistics::class))->getMethods(ReflectionMethod::IS_PUBLIC)
        );
        sort($methods);
        $this->assertSame(['__construct', 'index'], $methods);
    }

    private function context(bool $isSuper = true, int $omsSubjectId = 1): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-sales-detail-statistics',
            'client-sales-detail-statistics',
            'subject-sales-detail-statistics',
            'tenant-sales-detail-statistics',
            'https://oms.example.internal',
            $omsSubjectId,
            [1],
            ['analysis_products'],
            ['analysis_products'],
            time() + 3600,
            $isSuper
        );
    }

    private function sourceFixture(?array $rows = null, mixed $summary = null, mixed $count = null): object
    {
        $rows = $rows ?? [[
            'bn' => 'SKU-1',
            'name' => '商品一',
            'shop_id' => 'shop-1',
            'shop_name' => '店铺一',
            'sale_num' => 3,
            'sale_amount' => 12.5,
        ]];

        return new class($rows, $summary, $count) {
            public array $listCalls = [];
            public array $lastRequest = [];

            public function __construct(private array $rows, private mixed $summary, private mixed $count) {}

            public function get_products(array $request): mixed
            {
                $this->lastRequest = $request;
                if ($this->summary !== null) {
                    return $this->summary;
                }
                return [
                    'salenums' => 3,
                    'sale_amount' => 12.5,
                    'day_amounts' => 4.5,
                    'day_nums' => 1.5,
                    'gross_sales' => 5.5,
                    'gross_sales_rate' => 0.44,
                    'reship_nums' => 2,
                    'reship_amounts' => 1.5,
                ];
            }

            public function getList(string $columns, array $request, int $offset, int $limit, ?string $order): array
            {
                $this->lastRequest = $request;
                $this->listCalls[] = ['offset' => $offset, 'limit' => $limit, 'order' => $order];
                return array_slice($this->rows, $offset, $limit);
            }

            public function count(array $request): mixed
            {
                $this->lastRequest = $request;
                return $this->count ?? count($this->rows);
            }
        };
    }
}

if (!class_exists('SalesDetailStatisticsContractDesktopAuthContextStub', false)) {
    final class SalesDetailStatisticsContractDesktopAuthContextStub
    {
        public static array $userIds = [];

        public static function setUserId(int $userId): void
        {
            self::$userIds[] = $userId;
        }

        public static function reset(): void
        {
            self::$userIds = [];
        }
    }
}
