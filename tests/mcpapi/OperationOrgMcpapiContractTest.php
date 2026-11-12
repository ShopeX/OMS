<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OperationOrgMcpapiContractTest extends TestCase
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

    public function testControllerFileExistsAtAutoloadCorrectPath(): void
    {
        $this->assertFileExists($this->root . '/app/mcpapi/controller/v1/operation/org.php');
    }

    public function testControllerDelegatesListToCapability(): void
    {
        require_once $this->root . '/app/ome/lib/capability/operationorg.php';
        require_once $this->root . '/app/mcpapi/controller/v1/operation/org.php';

        $capability = new class extends ome_capability_operationorg {
            public function __construct() {}
            public function list(): array
            {
                return ['list' => [['org_id' => 1, 'org_name' => '总部']]];
            }
        };

        $controller = new mcpapi_ctl_v1_operation_org($capability);
        $response = $controller->list($this->context(), []);

        $this->assertSame(200, $response->status());
        $this->assertSame('总部', $response->body()['list'][0]['org_name']);
    }

    public function testReadOnlyControllerDoesNotExposeWriteMethods(): void
    {
        $source = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/operation/org.php');

        $this->assertStringContainsString('public function list(', $source);
        $this->assertStringContainsString('public function show(', $source);
        $this->assertStringNotContainsString('public function update(', $source);
        $this->assertStringNotContainsString('public function delete(', $source);
    }

    public function testControllerDelegatesCreateToCapability(): void
    {
        require_once $this->root . '/app/ome/lib/capability/operationorg.php';
        require_once $this->root . '/app/mcpapi/controller/v1/operation/org.php';

        $capability = new class extends ome_capability_operationorg {
            public array $calls = [];
            public function __construct() {}
            public function create(array $request): array
            {
                $this->calls[] = $request;
                return ['success' => true, 'org_id' => 9];
            }
        };

        $controller = new mcpapi_ctl_v1_operation_org($capability);
        $response = $controller->create($this->context(), ['code' => 'ORG_NEW', 'name' => '新组织']);

        $this->assertSame(200, $response->status());
        $this->assertSame(['code' => 'ORG_NEW', 'name' => '新组织'], $capability->calls[0]);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-org',
            'client-org',
            'subject-org',
            'tenant-org',
            'https://oms.example.internal',
            1,
            [1],
            ['operation_org'],
            ['operation_org'],
            time() + 3600,
            true
        );
    }
}
