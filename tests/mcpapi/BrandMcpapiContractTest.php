<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BrandMcpapiContractTest extends TestCase
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

    public function testBrandRouteUsesSlashPathAutoloadAndPermission(): void
    {
        $this->assertTrue(class_exists('mcpapi_ctl_v1_brand'));
        $this->assertFileExists(ROOT_DIR . '/app/mcpapi/controller/v1/brand.php');

        $router = new mcpapi_router($this->guard(['other_permission']));
        $response = $router->dispatch('GET', '/mcp-api/v1/brand', $this->authorization(), 'brand-denied');

        $this->assertSame(403, $response->status());
        $this->assertSame(['error' => 'permission_denied'], $response->body());
    }

    public function testBrandListAcceptsTheRegisteredExplicitPostRoute(): void
    {
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
        $controller = new mcpapi_ctl_v1_brand(new ome_capability_brand($brandModel));
        $router = new mcpapi_router($this->guard(['goods_brand']), $controller);

        $response = $router->dispatch(
            'POST',
            '/mcp-api/v1/brand/list',
            $this->authorization(),
            'brand-list',
            ['page' => 1, 'page_size' => 20, 'filters' => []],
            'application/json'
        );

        $this->assertSame(200, $response->status());
        $this->assertSame([], $response->body()['list']);
    }

    public function testBrandRouteReturnsJsonBusinessEnvelopeWithGlobalReadAction(): void
    {
        $capability = new class extends ome_capability_brand {
            public function detail(int $brandId): array
            {
                return [
                    'brand_id' => $brandId,
                    'brand_code' => 'BR-3',
                    'brand_name' => '品牌三',
                    'brand_keywords' => '',
                    'brand_url' => '',
                    'brand_desc' => '',
                    'brand_logo' => '',
                    'disabled' => false,
                    'ordernum' => 0,
                ];
            }
        };
        $controller = new mcpapi_ctl_v1_brand($capability);
        $response = $controller->show($this->context(), ['brand_id' => 3]);

        $this->assertSame(200, $response->status());
        $this->assertArrayHasKey('info', $response->body());
    }

    public function testBrandControllerExposesExplicitCapabilityMethods(): void
    {
        $methods = get_class_methods(mcpapi_ctl_v1_brand::class);

        $this->assertContains('list', $methods);
        $this->assertContains('show', $methods);
        $this->assertContains('create', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('delete', $methods);
    }

    public function testBrandUpdateRouteDoesNotUseCreateActionMultiplexing(): void
    {
        $router = new mcpapi_router($this->guard(['goods_brand']));
        $response = $router->dispatch(
            'POST',
            '/mcp-api/v1/brand/update',
            $this->authorization(),
            'brand-update-validation',
            [],
            'application/json'
        );

        $this->assertSame(422, $response->status());
        $this->assertSame('validation_failed', $response->body()['error']);
    }

    public function testBrandCapabilityKeepsGlobalScopeAndBlocksReferencedDelete(): void
    {
        $brandModel = new class {
            public array $lastFilters = [];

            public function getList(string $columns, array $filters, int $offset, int $limit, string $orderBy): array
            {
                $this->lastFilters = $filters;
                return [[
                    'brand_id' => 4,
                    'brand_code' => 'BR-4',
                    'brand_name' => '品牌四',
                    'brand_keywords' => '',
                    'brand_url' => '',
                    'brand_desc' => '',
                    'brand_logo' => '',
                    'disabled' => 'false',
                    'ordernum' => '2',
                ]];
            }

            public function count(array $filters): int
            {
                return 1;
            }

            public function dump(array $filters, string $columns): array
            {
                return ['brand_id' => 4, 'brand_name' => '品牌四'];
            }
        };
        $goodsModel = new class {
            public function getList(string $columns, array $filters, int $offset, int $limit): array
            {
                return [['goods_id' => 8]];
            }
        };

        $capability = new ome_capability_brand($brandModel, $goodsModel);
        $result = $capability->list(['filters' => ['brand_name' => '品牌', 'org_id' => 9]]);

        $this->assertArrayNotHasKey('org_id', $brandModel->lastFilters);
        $this->assertSame(4, $result['list'][0]['brand_id']);
        $this->expectException(InvalidArgumentException::class);
        $capability->delete(4);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context('brand-request', 'brand-client', 'brand-subject', 'brand-tenant', 'https://oms.example.test', 7, [1], ['goods_brand'], ['goods_brand'], time() + 3600);
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
