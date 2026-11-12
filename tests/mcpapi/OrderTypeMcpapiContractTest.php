<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OrderTypeMcpapiContractTest extends TestCase
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

    public function testControllerDelegatesCreateToCapability(): void
    {
        require_once $this->root . '/app/omeauto/lib/capability/order/type.php';
        require_once $this->root . '/app/mcpapi/controller/v1/order/type.php';

        $capability = new class extends omeauto_capability_order_type {
            public array $calls = [];
            public function __construct() {}
            public function create(array $request, ?array $allowedOrganizationIds): array
            {
                $this->calls[] = ['create', $request, $allowedOrganizationIds];
                return ['tid' => 31, 'row' => ['tid' => 31, 'name' => $request['name']]];
            }
            public function update(int $tid, array $request, ?array $allowedOrganizationIds): array
            {
                $this->calls[] = ['update', $tid, $request, $allowedOrganizationIds];
                return ['tid' => $tid, 'row' => ['tid' => $tid, 'name' => $request['name']]];
            }
            public function list(): array { return []; }
            public function detail(int $tid): ?array { return null; }
            public function organizations(): array { return []; }
            public function branches(): array { return []; }
            public function autoconfirmRules(): array { return []; }
            public function autodispatchRules(): array { return []; }
            public function conditionOptions(string $role, int $orgId = 0): array { return []; }
        };

        $controller = new mcpapi_ctl_v1_order_type($capability);
        $response = $controller->create($this->context(true), [
            'name' => '上海订单',
            'org_id' => 1,
            'conditions' => [['role' => 'address', 'content' => ['21']]],
        ]);
        $updated = $controller->update($this->context(true), [
            'tid' => 31,
            'name' => '上海订单2',
            'conditions' => [['role' => 'address', 'content' => ['21']]],
        ]);

        $this->assertSame(200, $response->status());
        $this->assertSame(200, $updated->status());
        $this->assertSame(['create', ['name' => '上海订单', 'org_id' => 1, 'conditions' => [['role' => 'address', 'content' => ['21']]]], null], $capability->calls[0]);
        $this->assertSame(31, $capability->calls[1][1]);
    }

    public function testControllerMapsValidationExceptionTo422(): void
    {
        require_once $this->root . '/app/omeauto/lib/capability/order/type.php';
        require_once $this->root . '/app/mcpapi/controller/v1/order/type.php';

        $capability = new class extends omeauto_capability_order_type {
            public function __construct() {}
            public function create(array $request, ?array $allowedOrganizationIds): array
            {
                throw new InvalidArgumentException('分组规则名称不能为空');
            }
            public function list(): array { return []; }
            public function detail(int $tid): ?array { return null; }
            public function organizations(): array { return []; }
            public function branches(): array { return []; }
            public function autoconfirmRules(): array { return []; }
            public function autodispatchRules(): array { return []; }
            public function conditionOptions(string $role, int $orgId = 0): array { return []; }
        };

        $controller = new mcpapi_ctl_v1_order_type($capability);
        $response = $controller->create($this->context(true), []);

        $this->assertSame(422, $response->status());
        $this->assertSame('validation_failed', $response->body()['error']);
        $this->assertSame('分组规则名称不能为空', $response->body()['message']);
    }

    public function testNonSuperOrganizationScopeIsDerivedWithoutDesktopUserState(): void
    {
        $source = (string) file_get_contents($this->root . '/app/mcpapi/controller/v1/order/type.php');

        $this->assertStringContainsString('operation_ops', $source);
        $this->assertStringNotContainsString('desktop_auth_context', $source);
        $this->assertStringNotContainsString('setVirtualLogin', $source);
    }

    public function testControllerDelegatesConditionOptions(): void
    {
        require_once $this->root . '/app/omeauto/lib/capability/order/type.php';
        require_once $this->root . '/app/mcpapi/controller/v1/order/type.php';

        $capability = new class extends omeauto_capability_order_type {
            public array $calls = [];
            public function __construct() {}
            public function conditionOptions(string $role, int $orgId = 0): array
            {
                $this->calls[] = [$role, $orgId];
                return [['value' => 'normal', 'label' => '正常订单']];
            }
            public function list(): array { return []; }
            public function detail(int $tid): ?array { return null; }
            public function organizations(): array { return []; }
            public function branches(): array { return []; }
            public function autoconfirmRules(): array { return []; }
            public function autodispatchRules(): array { return []; }
        };

        $controller = new mcpapi_ctl_v1_order_type($capability);
        $response = $controller->options($this->context(true), ['role' => 'ordertype', 'org_id' => 2]);

        $this->assertSame(200, $response->status());
        $this->assertSame(['ordertype', 2], $capability->calls[0]);
        $this->assertSame('normal', $response->body()['options'][0]['value']);
    }

    private function context(bool $isSuper): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'request-order-type',
            'client-order-type',
            'subject-order-type',
            'tenant-order-type',
            'https://oms.example.internal',
            1,
            [1],
            ['order_type'],
            ['order_type'],
            time() + 3600,
            $isSuper
        );
    }
}
