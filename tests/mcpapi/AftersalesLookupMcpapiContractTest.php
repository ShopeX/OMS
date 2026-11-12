<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;
use PHPUnit\Framework\TestCase;

final class AftersalesLookupMcpapiContractTest extends TestCase
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

    public function testControllerFileExistsAtExpectedPath(): void
    {
        $this->assertFileExists($this->root . '/app/mcpapi/controller/v1/aftersales.php');
    }

    public function testListRequiresAftersalesReadPermissionBoundary(): void
    {
        require_once $this->root . '/app/desktop/lib/annotation/RequirePermission.php';
        require_once $this->root . '/app/ome/lib/capability/aftersales/unified.php';
        require_once $this->root . '/app/mcpapi/controller/v1/aftersales.php';

        $reflection = new ReflectionMethod(mcpapi_ctl_v1_aftersales::class, 'list');
        $attributes = $reflection->getAttributes(RequirePermission::class);
        $this->assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();

        $this->assertSame([
            'aftersale_return_refund_apply',
            'aftersale_return_apply',
            'ome.action.return_reshipping.view',
        ], $attribute->all);

        $capability = new class extends ome_capability_aftersales_unified {
            public function __construct() {}

            public function list(array $request, mcpapi_auth_context $context): array
            {
                return ['items' => []];
            }
        };
        $controller = new mcpapi_ctl_v1_aftersales($capability);

        $denied = (new mcpapi_router($this->guard([
            'aftersale_return_refund_apply',
            'aftersale_return_apply',
        ]), $controller))->dispatch(
            'POST',
            '/mcp-api/v1/aftersales/list',
            $this->authorization(),
            'aftersales-denied',
            ['query' => 'refund'],
            'application/json'
        );
        $allowed = (new mcpapi_router($this->guard([
            'aftersale_return_refund_apply',
            'aftersale_return_apply',
            'ome.action.return_reshipping.view',
        ]), $controller))->dispatch(
            'POST',
            '/mcp-api/v1/aftersales/list',
            $this->authorization(),
            'aftersales-allowed',
            ['query' => 'refund'],
            'application/json'
        );

        $this->assertSame(403, $denied->status());
        $this->assertSame(['error' => 'permission_denied'], $denied->body());
        $this->assertSame(200, $allowed->status());
        $this->assertSame(['items' => []], $allowed->body());
    }

    public function testListReturnsValidationFailedForBadPagingInputAndEmptyQuery(): void
    {
        require_once $this->root . '/app/ome/lib/capability/aftersales/unified.php';
        require_once $this->root . '/app/mcpapi/controller/v1/aftersales.php';

        $capability = new class extends ome_capability_aftersales_unified {
            public function __construct() {}
            public function list(array $request, mcpapi_auth_context $context): array
            {
                return [];
            }
        };

        $controller = new mcpapi_ctl_v1_aftersales($capability);

        $emptyQuery = $controller->list($this->context(), ['query' => '']);
        $badPaging = $controller->list($this->context(), ['query' => 'refund', 'page' => 0]);

        $this->assertSame(422, $emptyQuery->status());
        $this->assertSame('validation_failed', $emptyQuery->body()['error']);
        $this->assertSame(422, $badPaging->status());
        $this->assertSame('validation_failed', $badPaging->body()['error']);
    }

    public function testListReturnsNormalizedUnionPayload(): void
    {
        require_once $this->root . '/app/ome/lib/capability/aftersales/unified.php';
        require_once $this->root . '/app/mcpapi/controller/v1/aftersales.php';

        $payload = [
            'query' => 'SO-1',
            'items' => [[
                'kind' => 'refund',
                'source_id' => 1,
                'summary' => [
                    'biz_bn' => 'SO-1',
                    'order_bn' => 'ORDER-1',
                    'org_id' => 11,
                    'org_name' => '总部',
                    'shop_id' => 11,
                    'shop_name' => '店铺A',
                    'shop_bn' => 'SHOP-A',
                    'status' => 'refund_pending',
                    'status_text' => '退款处理中',
                    'amount' => 88.5,
                    'occur_time' => '2026-07-26 11:00:00',
                ],
            ], [
                'kind' => 'return',
                'source_id' => 2,
                'summary' => [
                    'biz_bn' => 'RT-2',
                    'order_bn' => 'ORDER-2',
                    'org_id' => 12,
                    'org_name' => '华东组织',
                    'shop_id' => 12,
                    'shop_name' => '店铺B',
                    'shop_bn' => 'SHOP-B',
                    'status' => 'return_waiting_inbound',
                    'status_text' => '等待退货入库',
                    'amount' => 18.5,
                    'occur_time' => '2026-07-26 10:00:00',
                ],
            ], [
                'kind' => 'reship',
                'source_id' => 3,
                'summary' => [
                    'biz_bn' => 'RS-3',
                    'order_bn' => 'ORDER-3',
                    'org_id' => 0,
                    'org_name' => '',
                    'shop_id' => 0,
                    'shop_name' => '',
                    'shop_bn' => '',
                    'status' => 'reship_sent',
                    'status_text' => '补寄已发出',
                    'amount' => 0,
                    'occur_time' => '2026-07-26 09:00:00',
                ],
            ]],
            'returned_count' => 3,
            'total_count' => 3,
            'page' => 1,
            'page_size' => 20,
            'has_more' => false,
            'partial' => false,
            'warnings' => [],
        ];
        $capability = new class($payload) extends ome_capability_aftersales_unified {
            private array $payload;

            public function __construct(array $payload)
            {
                $this->payload = $payload;
            }

            public function list(array $request, mcpapi_auth_context $context): array
            {
                return $this->payload;
            }
        };

        $controller = new mcpapi_ctl_v1_aftersales($capability);
        $response = $controller->list($this->context(), ['query' => 'SO-1']);

        $this->assertSame(200, $response->status());
        $this->assertSame($payload, $response->body());
        $this->assertSame('退款处理中', $response->body()['items'][0]['summary']['status_text']);
        $this->assertSame('refund_pending', $response->body()['items'][0]['summary']['status']);
        $this->assertSame('等待退货入库', $response->body()['items'][1]['summary']['status_text']);
        $this->assertSame('return_waiting_inbound', $response->body()['items'][1]['summary']['status']);
        $this->assertSame('补寄已发出', $response->body()['items'][2]['summary']['status_text']);
        $this->assertSame('reship_sent', $response->body()['items'][2]['summary']['status']);
        $this->assertSame('ORDER-1', $response->body()['items'][0]['summary']['order_bn']);
        $this->assertSame('总部', $response->body()['items'][0]['summary']['org_name']);
        $this->assertSame('华东组织', $response->body()['items'][1]['summary']['org_name']);
        $this->assertSame('', $response->body()['items'][2]['summary']['org_name']);
        $this->assertSame('店铺A', $response->body()['items'][0]['summary']['shop_name']);
        $this->assertSame('SHOP-A', $response->body()['items'][0]['summary']['shop_bn']);
        $this->assertSame('店铺B', $response->body()['items'][1]['summary']['shop_name']);
        $this->assertSame('SHOP-B', $response->body()['items'][1]['summary']['shop_bn']);
        $this->assertSame('', $response->body()['items'][2]['summary']['shop_name']);
        $this->assertSame('', $response->body()['items'][2]['summary']['shop_bn']);
        $this->assertSame(88.5, $response->body()['items'][0]['summary']['amount']);
        $this->assertSame('2026-07-26 11:00:00', $response->body()['items'][0]['summary']['occur_time']);
    }

    public function testControllerExposesReadOnlyListActionOnly(): void
    {
        $source = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/aftersales.php');

        $this->assertStringContainsString('public function list(', $source);
        $this->assertStringNotContainsString('public function create(', $source);
        $this->assertStringNotContainsString('public function update(', $source);
        $this->assertStringNotContainsString('public function delete(', $source);
    }

    private function context(array $permissions = [
        'aftersale_return_refund_apply',
        'aftersale_return_apply',
        'ome.action.return_reshipping.view',
    ]): mcpapi_auth_context
    {
        return new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 1, [1], $permissions, $permissions, time() + 3600, false);
    }

    private function guard(array $permissions): mcpapi_auth_guard
    {
        $record = new mcpapi_auth_client_record('aftersales-client', 'tenant', 'https://oms.example.internal', 1, [1], $permissions, $permissions, true, null, time() + 3600, '', '', '', '', null, 1);

        return new mcpapi_auth_guard(
            static fn(string $token): array => [],
            null,
            null,
            static fn(string $clientId, string $clientSecret): ?mcpapi_auth_client_record
                => $clientId === 'aftersales-client' && $clientSecret === 'secret' ? $record : null
        );
    }

    private function authorization(): string
    {
        return 'Basic ' . base64_encode('aftersales-client:secret');
    }
}
