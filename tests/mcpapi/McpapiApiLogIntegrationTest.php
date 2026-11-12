<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpapiApiLogIntegrationTest extends TestCase
{
    private string $cwdBeforeTest;
    /** @var string[] */
    private array $createdLogIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cwdBeforeTest = getcwd();
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

    protected function tearDown(): void
    {
        if ($this->createdLogIds !== []) {
            app::get('ome')->model('api_log')->delete(['log_id' => $this->createdLogIds]);
        }
        chdir($this->cwdBeforeTest);
        parent::tearDown();
    }

    public function testRouterDispatchPersistsReadableApiLogRow(): void
    {
        $requestId = 'router-integration-' . uniqid();
        $router = new mcpapi_router(
            $this->guard(['goods_brand']),
            new mcpapi_api_log_writer(),
            new mcpapi_ctl_v1_brand(new ome_capability_brand($this->brandModel()))
        );

        $response = $router->dispatch(
            'POST',
            '/mcp-api/v1/brand/list',
            $this->authorization(),
            $requestId,
            ['page' => 1, 'filters' => []],
            'application/json'
        );

        $this->assertSame(200, $response->status());

        $rows = app::get('ome')->model('api_log')->getList(
            'log_id,task_name,status,api_type,msg_id,url,worker,spendtime,params,response',
            ['msg_id' => $requestId],
            0,
            1,
            'createtime DESC'
        );

        $this->assertCount(1, $rows);
        $this->createdLogIds[] = (string) $rows[0]['log_id'];
        $this->assertSame('MCPAPI /mcp-api/v1/brand/list', $rows[0]['task_name']);
        $this->assertSame('success', $rows[0]['status']);
        $this->assertSame('response', $rows[0]['api_type']);
        $this->assertSame($requestId, $rows[0]['msg_id']);
        $this->assertSame('/mcp-api/v1/brand/list', $rows[0]['url']);
        $this->assertSame('mcpapi_router:dispatch', $rows[0]['worker']);
        $this->assertStringContainsString('POST', (string) $rows[0]['params']);
        $this->assertStringContainsString('list', (string) $rows[0]['response']);
    }

    public function testDefaultRouterFallbackPersistsReadableApiLogRow(): void
    {
        $requestId = 'router-default-' . uniqid();
        $router = new mcpapi_router(
            $this->guard(['goods_brand']),
            new mcpapi_ctl_v1_brand(new ome_capability_brand($this->brandModel()))
        );

        $response = $router->dispatch(
            'POST',
            '/mcp-api/v1/brand/list',
            $this->authorization(),
            $requestId,
            ['page' => 1, 'filters' => []],
            'application/json'
        );

        $this->assertSame(200, $response->status());

        $rows = app::get('ome')->model('api_log')->getList(
            'log_id,task_name,status,api_type,msg_id,url,worker',
            ['msg_id' => $requestId],
            0,
            1,
            'createtime DESC'
        );

        $this->assertCount(1, $rows);
        $this->createdLogIds[] = (string) $rows[0]['log_id'];
        $this->assertSame('MCPAPI /mcp-api/v1/brand/list', $rows[0]['task_name']);
        $this->assertSame('success', $rows[0]['status']);
        $this->assertSame('response', $rows[0]['api_type']);
        $this->assertSame($requestId, $rows[0]['msg_id']);
        $this->assertSame('/mcp-api/v1/brand/list', $rows[0]['url']);
        $this->assertSame('mcpapi_router:dispatch', $rows[0]['worker']);
    }

    private function brandModel(): object
    {
        return new class {
            public function getList(string $columns, array $filters, int $offset, int $limit, string $orderBy): array
            {
                return [];
            }

            public function count(array $filters): int
            {
                return 0;
            }
        };
    }

    private function guard(array $permissions): mcpapi_auth_guard
    {
        $record = new mcpapi_auth_client_record('brand-client', 'brand-tenant', 'https://oms.example.test', 7, [1], $permissions, $permissions, true, null, time() + 3600, '', '', '', '', null, 7);
        return new mcpapi_auth_guard(
            static fn(string $token): array => [],
            null,
            null,
            static fn(string $clientId, string $clientSecret): ?mcpapi_auth_client_record => $clientId === 'brand-client' && $clientSecret === 'brand-secret' ? $record : null
        );
    }

    private function authorization(): string
    {
        return 'Basic ' . base64_encode('brand-client:brand-secret');
    }
}
