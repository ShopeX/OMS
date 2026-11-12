<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AftersaleStatisticsMcpapiContractTest extends TestCase
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

    public function testAftersalesDetailStatisticsReturnsKpiAndPaginatedDetailShape(): void
    {
        $response = $this->callJsonRoute([
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-31',
            'shop_id' => ['shop-out-of-scope'],
        ]);
        $this->assertSame(200, $response->status());

        $result = $response->body();

        $this->assertSame(['statistics', 'detail'], array_keys($result));
        $this->assertArrayHasKey('statistics', $result);
        $this->assertSame(
            ['total_nums', 'total_apply_money', 'total_refund_money'],
            array_keys($result['statistics'])
        );
        $this->assertArrayHasKey('total_nums', $result['statistics']);
        $this->assertArrayHasKey('total_apply_money', $result['statistics']);
        $this->assertArrayHasKey('total_refund_money', $result['statistics']);
        $this->assertIsInt($result['statistics']['total_nums']);
        $this->assertIsString($result['statistics']['total_apply_money']);
        $this->assertIsString($result['statistics']['total_refund_money']);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $result['statistics']['total_apply_money']);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $result['statistics']['total_refund_money']);
        $this->assertArrayHasKey('detail', $result);
        $this->assertSame(['list', 'total', 'page', 'page_size'], array_keys($result['detail']));
        $this->assertIsArray($result['detail']['list']);
        $this->assertIsInt($result['detail']['total']);
        $this->assertSame(1, $result['detail']['page']);
        $this->assertSame(20, $result['detail']['page_size']);
        $this->assertSame([], $result['detail']['list']);
        $this->assertSame(0, $result['detail']['total']);
    }

    public function testAftersalesDetailStatisticsPreservesDeterministicDetailRowContract(): void
    {
        $model = new class {
            public array $requests = [];

            public function get_count(array $request): array
            {
                $this->requests['summary'] = $request;

                return [
                    'total_nums' => 1,
                    'total_apply_money' => '12.00',
                    'total_refund_money' => '8.00',
                ];
            }

            public function getList(string $columns, array $request, int $offset, int $limit): array
            {
                $this->requests['detail'] = [$columns, $request, $offset, $limit];

                return [[
                    'shop_id' => 'shop-1',
                    'order_id' => 'order-1',
                    'product_name' => 'Product 1',
                    'apply_money' => '12.00',
                    'refundmoney' => '8.00',
                    'aftersale_time' => '2026-07-02 12:00:00',
                ]];
            }

            public function count(array $request): int
            {
                $this->requests['count'] = $request;

                return 1;
            }
        };
        $capability = new omeanalysts_capability_aftersale_statistics(
            $model,
            static fn(array $organizationIds): array => ['shop-1']
        );

        $result = $capability->execute([
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-31',
            'shop_id' => ['shop-1'],
            'page' => 2,
            'page_size' => 1,
        ], [42]);

        $this->assertSame(['statistics', 'detail'], array_keys($result));
        $this->assertSame(['shop_id', 'order_id', 'product_name', 'apply_money', 'refundmoney', 'aftersale_time'], array_keys($result['detail']['list'][0]));
        $this->assertSame(1, $result['detail']['total']);
        $this->assertSame(2, $result['detail']['page']);
        $this->assertSame(1, $result['detail']['page_size']);
        $this->assertSame(1, $model->requests['detail'][2]);
        $this->assertSame(1, $model->requests['detail'][3]);
    }

    public function testAftersalesDetailStatisticsRejectsOrgIdFromCaller(): void
    {
        $response = $this->callJsonRoute([
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-31',
            'org_id' => [999],
        ]);

        $result = $response->body();
        $this->assertSame('fail', $result['rsp']);
    }

    public function testAftersalesDetailStatisticsReturnsEmptyForUnauthorizedShopIntersection(): void
    {
        $response = $this->callJsonRoute([
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-31',
            'shop_id' => ['shop-out-of-scope'],
        ]);
        $this->assertSame(200, $response->status());

        $result = $response->body();

        $this->assertSame(['statistics', 'detail'], array_keys($result));
        $this->assertSame(
            ['total_nums', 'total_apply_money', 'total_refund_money'],
            array_keys($result['statistics'])
        );
        $this->assertSame(0, $result['statistics']['total_nums']);
        $this->assertSame('0.00', $result['statistics']['total_apply_money']);
        $this->assertSame('0.00', $result['statistics']['total_refund_money']);
        $this->assertSame([], $result['detail']['list']);
        $this->assertSame(0, $result['detail']['total']);
        $this->assertSame(1, $result['detail']['page']);
        $this->assertSame(20, $result['detail']['page_size']);
    }

    public function testAftersalesDetailStatisticsRejectsUnknownFields(): void
    {
        $response = $this->callJsonRoute([
            'time_from' => '2026-07-01',
            'time_to' => '2026-07-31',
            'unexpected' => 'value',
        ]);

        $result = $response->body();
        $this->assertSame('fail', $result['rsp']);
    }

    private function callJsonRoute(array $payload): mcpapi_http_response
    {
        $response = (new mcpapi_router($this->permissionGuard()))->dispatch(
            'POST',
            '/mcp-api/v1/aftersale/statistics/index',
            'Basic ' . base64_encode('aftersale-statistics-client:secret'),
            'request-aftersale-statistics',
            $payload,
            'application/json'
        );

        $this->assertNotSame(404, $response->status(), 'Dedicated aftersale statistics MCPAPI route is not registered yet.');

        return $response;
    }

    private function permissionGuard(): mcpapi_auth_guard
    {
        $record = new mcpapi_auth_client_record(
            'aftersale-statistics-client',
            'tenant-aftersale-statistics',
            'https://oms.example.internal',
            42,
            [7],
            ['analysis_aftersale'],
            ['analysis_aftersale'],
            true,
            null,
            time() + 3600,
            '',
            '',
            '',
            '',
            null,
            42
        );

        return new mcpapi_auth_guard(
            static fn(string $token): array => [],
            null,
            null,
            static fn(string $clientId, string $clientSecret): ?mcpapi_auth_client_record
                => $clientId === 'aftersale-statistics-client' && $clientSecret === 'secret' ? $record : null
        );
    }
}
