<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PurchaseOrderMcpapiContractTest extends TestCase
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
        $this->assertFileExists($this->root . '/app/mcpapi/controller/v1/purchase/order.php');
    }

    public function testControllerDelegatesListToCapability(): void
    {
        require_once $this->root . '/app/console/lib/capability/purchase.php';
        require_once $this->root . '/app/mcpapi/controller/v1/purchase/order.php';

        $capability = new class extends console_capability_purchase {
            public function __construct() {}
            public function list(array $request, ?mcpapi_auth_context $context = null): array
            {
                unset($context);
                return ['list' => [['po_id' => 1, 'name' => '采购单A']], 'total' => 1, 'page' => 1, 'pageSize' => 20];
            }
        };

        $controller = new mcpapi_ctl_v1_purchase_order($capability);
        $response = $controller->list($this->context(), ['page' => 1]);

        $this->assertSame(200, $response->status());
        $this->assertSame('采购单A', $response->body()['list'][0]['name']);
    }

    public function testControllerExposesReadMethodsAndApprovedImport(): void
    {
        $source = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/purchase/order.php');

        $this->assertStringContainsString('public function list(', $source);
        $this->assertStringContainsString('public function show(', $source);
        $this->assertStringContainsString('public function importTemplate(', $source);
        $this->assertStringNotContainsString('public function create(', $source);
        $this->assertStringNotContainsString('public function update(', $source);
        $this->assertStringContainsString('public function import(', $source);
        $this->assertStringContainsString('desktop_finder_vue_import', (string) file_get_contents($this->root . '/app/console/lib/capability/purchase.php'));
        $this->assertStringContainsString("'import'", (string) file_get_contents($this->root . '/app/mcpapi/lib/router.php'));
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-purchase-order',
            'client-purchase-order',
            'subject-purchase-order',
            'tenant-purchase-order',
            'https://oms.example.internal',
            1,
            [1],
            ['console_purchase_po'],
            ['console_purchase_po'],
            time() + 3600,
            true
        );
    }
}
