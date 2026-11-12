<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ShopMcpapiContractTest extends TestCase
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
        $this->assertFileExists($this->root . '/app/mcpapi/controller/v1/shop.php');
    }

    public function testControllerDelegatesListToCapability(): void
    {
        require_once $this->root . '/app/ome/lib/capability/shop.php';
        require_once $this->root . '/app/mcpapi/controller/v1/shop.php';

        $capability = new class extends ome_capability_shop {
            public $capturedOrganizationIds = 'unset';
            public function __construct() {}
            public function list(?array $organizationIds = null): array
            {
                $this->capturedOrganizationIds = $organizationIds;
                return ['rows' => [['shop_id' => 1, 'name' => '店铺A']]];
            }
        };

        $controller = new mcpapi_ctl_v1_shop($capability);
        $response = $controller->list($this->context(), []);

        $this->assertSame(200, $response->status());
        $this->assertSame('店铺A', $response->body()['rows'][0]['name']);
        $this->assertNull($capability->capturedOrganizationIds);
    }

    public function testControllerExposesReadOnlyAndFormConfigMethodsOnly(): void
    {
        $source = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/shop.php');

        $this->assertStringContainsString('public function list(', $source);
        $this->assertStringContainsString('public function show(', $source);
        $this->assertStringContainsString('public function addForm(', $source);
        $this->assertStringContainsString('public function editForm(', $source);
        $this->assertStringNotContainsString('public function create(', $source);
        $this->assertStringNotContainsString('public function update(', $source);
        $this->assertStringNotContainsString('public function delete(', $source);
    }

    public function testControllerShowAndEditFormForwardOrganizationPermissions(): void
    {
        $source = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/shop.php');

        $this->assertMatchesRegularExpression('/->detail\s*\(\s*\(int\)\s*\(\$request\[\'shop_id\'\]\s*\?\?\s*0\),\s*\$this->organizationPermissions\(\$context\)\s*\)/', $source);
        $this->assertMatchesRegularExpression('/->editForm\s*\(\s*\(int\)\s*\(\$request\[\'shop_id\'\]\s*\?\?\s*0\),\s*\$this->organizationPermissions\(\$context\)\s*\)/', $source);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-shop',
            'client-shop',
            'subject-shop',
            'tenant-shop',
            'https://oms.example.internal',
            1,
            [1],
            ['setting_shop'],
            ['setting_shop'],
            time() + 3600,
            true
        );
    }
}
