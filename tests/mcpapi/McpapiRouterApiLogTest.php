<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpapiRouterApiLogTest extends TestCase
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

    public function testDispatchWritesAndCompletesApiLogForMcpRoute(): void
    {
        $logger = new class implements mcpapi_api_log_writer_contract {
            public array $writes = [];

            public function write(array $context): string
            {
                $this->writes[] = $context;
                return 'log-1';
            }
        };

        $brandModel = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit, string $orderBy): array
            {
                return [];
            }

            public function count(array $filters): int
            {
                return 0;
            }
        };
        $router = new mcpapi_router($this->guard(['goods_brand']), $logger, new mcpapi_ctl_v1_brand(new ome_capability_brand($brandModel)));

        $response = $router->dispatch(
            'POST',
            '/mcp-api/v1/brand/list',
            $this->authorization(),
            'request-1',
            ['page' => 3],
            'application/json'
        );

        $this->assertSame(200, $response->status());
        $this->assertCount(1, $logger->writes);
        $this->assertSame('/mcp-api/v1/brand/list', $logger->writes[0]['path']);
        $this->assertSame('brand-client', $logger->writes[0]['client_id']);
        $this->assertSame('success', $logger->writes[0]['status']);
        $this->assertSame(200, $logger->writes[0]['response_status']);
        $this->assertSame('POST', $logger->writes[0]['method']);
    }

    public function testDispatchLogsUnauthorizedRequestAsFailure(): void
    {
        $logger = new class implements mcpapi_api_log_writer_contract {
            public array $writes = [];

            public function write(array $context): string
            {
                $this->writes[] = $context;
                return 'log-2';
            }
        };

        $router = new mcpapi_router($this->guard(['goods_brand']), $logger, new mcpapi_ctl_v1_brand(new ome_capability_brand($this->brandModel())));

        $response = $router->dispatch(
            'POST',
            '/mcp-api/v1/brand/list',
            'Basic ' . base64_encode('brand-client:wrong-secret'),
            'request-unauthorized',
            ['page' => 1, 'filters' => []],
            'application/json'
        );

        $this->assertSame(401, $response->status());
        $this->assertCount(1, $logger->writes);
        $this->assertSame('fail', $logger->writes[0]['status']);
        $this->assertSame(401, $logger->writes[0]['response_status']);
    }

    public function testDispatchLogsActualHttpMethodForGetRoute(): void
    {
        $logger = new class implements mcpapi_api_log_writer_contract {
            public array $writes = [];

            public function write(array $context): string
            {
                $this->writes[] = $context;
                return 'log-3';
            }
        };

        $router = new mcpapi_router($this->guard(['goods_brand']), $logger, new mcpapi_ctl_v1_brand(new ome_capability_brand($this->brandModel())));

        $response = $router->dispatch(
            'GET',
            '/mcp-api/v1/brand',
            $this->authorization(),
            'request-get',
            [],
            'application/json'
        );

        $this->assertSame(200, $response->status());
        $this->assertCount(1, $logger->writes);
        $this->assertSame('GET', $logger->writes[0]['method']);
    }

    public function testDispatchLogsAndRethrowsUnhandledExceptions(): void
    {
        $logger = new class implements mcpapi_api_log_writer_contract {
            public array $writes = [];

            public function write(array $context): string
            {
                $this->writes[] = $context;
                return 'log-4';
            }
        };

        $throwingCapability = new class extends ome_capability_brand {
            public function __construct()
            {
            }

            public function list(array $request): array
            {
                throw new RuntimeException('boom');
            }
        };
        $router = new mcpapi_router($this->guard(['goods_brand']), $logger, new mcpapi_ctl_v1_brand($throwingCapability));

        try {
            $router->dispatch(
                'POST',
                '/mcp-api/v1/brand/list',
                $this->authorization(),
                'request-throw',
                ['page' => 1],
                'application/json'
            );
            $this->fail('Expected RuntimeException to be rethrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertCount(1, $logger->writes);
        $this->assertSame('fail', $logger->writes[0]['status']);
        $this->assertSame(500, $logger->writes[0]['response_status']);
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
