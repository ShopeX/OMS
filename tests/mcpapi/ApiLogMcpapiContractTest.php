<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ApiLogMcpapiContractTest extends TestCase
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
        $this->assertFileExists($this->root . '/app/mcpapi/controller/v1/api/log.php');
    }

    public function testControllerDelegatesListToCapability(): void
    {
        require_once $this->root . '/app/ome/lib/capability/api/log.php';
        require_once $this->root . '/app/mcpapi/controller/v1/api/log.php';

        $capability = new class extends ome_capability_api_log {
            public function __construct() {}
            public function list(array $request): array
            {
                return ['list' => [['log_id' => 'L-1']], 'total' => 1, 'page' => 1, 'pageSize' => 20];
            }
        };

        $controller = new mcpapi_ctl_v1_api_log($capability);
        $response = $controller->list($this->context(), ['page' => 1, 'pageSize' => 20]);

        $this->assertSame(200, $response->status());
        $this->assertSame('L-1', $response->body()['list'][0]['log_id']);
    }

    public function testControllerExposesReadOnlyMethodsOnly(): void
    {
        $source = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/api/log.php');

        $this->assertStringContainsString('public function list(', $source);
        $this->assertStringContainsString('public function show(', $source);
        $this->assertStringNotContainsString('public function create(', $source);
        $this->assertStringNotContainsString('public function update(', $source);
        $this->assertStringNotContainsString('public function delete(', $source);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context('req', 'client', 'subject', 'tenant', 'https://oms.example.internal', 1, [1], ['setting_api_log'], ['setting_api_log'], time() + 3600, true);
    }
}
