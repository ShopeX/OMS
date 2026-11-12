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
final class AutoconfirmMcpapiContractTest extends TestCase
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
        $capability = new class extends omeauto_capability_autoconfirm {
            public array $calls = [];
            public function __construct() {}
            public function create(array $request, ?array $allowedOrganizationIds): array { $this->calls[] = ['create', $request]; return ['oid' => 9]; }
            public function setStatus(int $oid, string $status, ?array $allowedOrganizationIds): array { $this->calls[] = ['setStatus', $oid, $status]; return ['affected_rule_oids' => [$oid], 'affected_order_type_tids' => []]; }
            public function list(?array $allowedOrganizationIds): array { return []; }
            public function detail(int $oid, ?array $allowedOrganizationIds): ?array { return null; }
            public function orderTypes(?array $allowedOrganizationIds): array { return []; }
            public function update(int $oid, array $request, ?array $allowedOrganizationIds): array { return []; }
            public function setDefault(int $oid, ?array $allowedOrganizationIds): array { return []; }
            public function removeDefault(int $oid, ?array $allowedOrganizationIds): array { return []; }
        };

        $controller = new mcpapi_ctl_v1_autoconfirm($capability);
        $create = $controller->create($this->context(), ['name' => 'rule']);
        $status = $controller->setStatus($this->context(), ['oid' => 9, 'status' => 'true']);

        $this->assertSame(200, $create->status());
        $this->assertSame(200, $status->status());
        $this->assertSame(['create', ['name' => 'rule']], $capability->calls[0]);
        $this->assertSame(['setStatus', 9, 'true'], $capability->calls[1]);
    }

    public function testControllerMapsValidationExceptionTo422(): void
    {
        $capability = new class extends omeauto_capability_autoconfirm {
            public function __construct() {}
            public function create(array $request, ?array $allowedOrganizationIds): array { throw new InvalidArgumentException('请设置生效时间范围'); }
            public function list(?array $allowedOrganizationIds): array { return []; }
            public function detail(int $oid, ?array $allowedOrganizationIds): ?array { return null; }
            public function orderTypes(?array $allowedOrganizationIds): array { return []; }
            public function update(int $oid, array $request, ?array $allowedOrganizationIds): array { return []; }
            public function setStatus(int $oid, string $status, ?array $allowedOrganizationIds): array { return []; }
            public function setDefault(int $oid, ?array $allowedOrganizationIds): array { return []; }
            public function removeDefault(int $oid, ?array $allowedOrganizationIds): array { return []; }
        };

        $controller = new mcpapi_ctl_v1_autoconfirm($capability);
        $response = $controller->create($this->context(), []);

        $this->assertSame(422, $response->status());
        $this->assertSame('validation_failed', $response->body()['error']);
    }

    private function context(): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-autoconfirm',
            'client-autoconfirm',
            'subject-autoconfirm',
            'tenant-autoconfirm',
            'https://oms.example.internal',
            1,
            [1],
            ['auto_confirm'],
            ['auto_confirm'],
            time() + 3600,
            true
        );
    }
}
