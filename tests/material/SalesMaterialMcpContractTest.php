<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SalesMaterialMcpContractTest extends TestCase
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
        $this->assertFileExists($this->root . '/app/mcpapi/controller/v1/material/sales.php');
    }

    public function testControllerDelegatesListToCapability(): void
    {
        require_once $this->root . '/app/material/lib/capability/sales/material.php';
        require_once $this->root . '/app/mcpapi/controller/v1/material/sales.php';

        $capability = new class extends material_capability_sales_material {
            public function __construct() {}
            public function list(array $request): array
            {
                return ['list' => [['sm_id' => 1, 'sales_material_name' => '销售物料A']], 'total' => 1, 'page' => 1, 'page_size' => 20];
            }
        };

        $controller = new mcpapi_ctl_v1_material_sales($capability);
        $response = $controller->list($this->context(), ['page' => 1, 'page_size' => 20]);

        $this->assertSame(200, $response->status());
        $this->assertSame('销售物料A', $response->body()['list'][0]['sales_material_name']);
    }

    public function testControllerExposesReadAndTemplateOnly(): void
    {
        $source = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/material/sales.php');

        $this->assertStringContainsString('public function list(', $source);
        $this->assertStringContainsString('public function show(', $source);
        $this->assertStringContainsString('public function importTemplate(', $source);
        $this->assertStringNotContainsString('public function create(', $source);
        $this->assertStringNotContainsString('public function update(', $source);
        $this->assertStringNotContainsString('public function delete(', $source);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-sales-material',
            'client-sales-material',
            'subject-sales-material',
            'tenant-sales-material',
            'https://oms.example.internal',
            1,
            [1],
            ['sales_material'],
            ['sales_material'],
            time() + 3600,
            true
        );
    }
}
