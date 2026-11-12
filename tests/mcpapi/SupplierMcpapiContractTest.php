<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SupplierMcpapiContractTest extends TestCase
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
        $this->assertFileExists($this->root . '/app/mcpapi/controller/v1/supplier.php');
    }

    public function testControllerDelegatesListToCapability(): void
    {
        require_once $this->root . '/app/purchase/lib/capability/supplier.php';
        require_once $this->root . '/app/mcpapi/controller/v1/supplier.php';

        $capability = new class extends purchase_capability_supplier {
            public function __construct() {}
            public function list(array $request): array
            {
                return ['list' => [['supplier_id' => 1, 'name' => '供应商A']], 'total' => 1, 'page' => 1, 'pageSize' => 20];
            }
        };

        $controller = new mcpapi_ctl_v1_supplier($capability);
        $response = $controller->list($this->context(), ['page' => 1, 'pageSize' => 20]);

        $this->assertSame(200, $response->status());
        $this->assertSame('供应商A', $response->body()['list'][0]['name']);
    }

    public function testControllerExposesReadOnlyMethodsOnly(): void
    {
        $source = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/supplier.php');

        $this->assertStringContainsString('public function list(', $source);
        $this->assertStringContainsString('public function show(', $source);
        $this->assertStringNotContainsString('public function create(', $source);
        $this->assertStringNotContainsString('public function update(', $source);
        $this->assertStringNotContainsString('public function delete(', $source);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-supplier',
            'client-supplier',
            'subject-supplier',
            'tenant-supplier',
            'https://oms.example.internal',
            1,
            [1],
            ['purchase_supplier'],
            ['purchase_supplier'],
            time() + 3600,
            true
        );
    }
}
