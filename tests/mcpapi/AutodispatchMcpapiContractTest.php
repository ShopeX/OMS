<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

#[PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class AutodispatchMcpapiContractTest extends TestCase
{
    private string $cwdBeforeTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cwdBeforeTest = getcwd();
        $root = dirname(__DIR__, 2);
        chdir($root);
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

    public function testControllerDelegatesCreateAndStatusToCapability(): void
    {
        $capability = new class extends omeauto_capability_autodispatch {
            public array $calls = [];
            public function __construct() {}
            public function create(array $request, ?array $allowedOrganizationIds): array { $this->calls[] = ['create', $request]; return ['oid' => 12]; }
            public function setStatus(int $oid, string $status, ?array $allowedOrganizationIds): array { $this->calls[] = ['setStatus', $oid, $status]; return ['affected_rule_oids' => [$oid], 'affected_order_type_tids' => []]; }
            public function list(?array $allowedOrganizationIds): array { return []; }
            public function detail(int $oid, ?array $allowedOrganizationIds): ?array { return null; }
            public function orderTypes(?array $allowedOrganizationIds): array { return []; }
            public function orderTypeDetail(int $tid, ?array $allowedOrganizationIds): ?array { return null; }
            public function update(int $oid, array $request, ?array $allowedOrganizationIds): array { return []; }
            public function setDefault(int $oid, ?array $allowedOrganizationIds): array { return []; }
            public function removeDefault(int $oid, ?array $allowedOrganizationIds): array { return []; }
        };

        $controller = new mcpapi_ctl_v1_autodispatch($capability);
        $create = $controller->create($this->context(), ['org_id' => 5]);
        $status = $controller->setStatus($this->context(), ['oid' => 9, 'status' => 'true']);

        $this->assertSame(200, $create->status());
        $this->assertSame(200, $status->status());
        $this->assertSame(['create', ['org_id' => 5]], $capability->calls[0]);
        $this->assertSame(['setStatus', 9, 'true'], $capability->calls[1]);
    }

    public function testControllerMapsValidationExceptionTo422(): void
    {
        $capability = new class extends omeauto_capability_autodispatch {
            public function __construct() {}
            public function create(array $request, ?array $allowedOrganizationIds): array { throw new InvalidArgumentException('没有该组织的数据权限'); }
            public function list(?array $allowedOrganizationIds): array { return []; }
            public function detail(int $oid, ?array $allowedOrganizationIds): ?array { return null; }
            public function orderTypes(?array $allowedOrganizationIds): array { return []; }
            public function orderTypeDetail(int $tid, ?array $allowedOrganizationIds): ?array { return null; }
            public function update(int $oid, array $request, ?array $allowedOrganizationIds): array { return []; }
            public function setStatus(int $oid, string $status, ?array $allowedOrganizationIds): array { return []; }
            public function setDefault(int $oid, ?array $allowedOrganizationIds): array { return []; }
            public function removeDefault(int $oid, ?array $allowedOrganizationIds): array { return []; }
        };

        $controller = new mcpapi_ctl_v1_autodispatch($capability);
        $response = $controller->create($this->context(), []);

        $this->assertSame(422, $response->status());
        $this->assertSame('validation_failed', $response->body()['error']);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-autodispatch',
            'client-autodispatch',
            'subject-autodispatch',
            'tenant-autodispatch',
            'https://oms.example.internal',
            1,
            [1],
            ['auto_dispatch'],
            ['auto_dispatch'],
            time() + 3600,
            true
        );
    }
}
