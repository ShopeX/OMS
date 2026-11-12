<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UserMcpapiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
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

    public function testPlannedUserRoutesHaveAnMcpapiControllerBeforeDispatchIsAdded(): void
    {
        $controllerFile = $this->root . '/app/mcpapi/controller/v1/user.php';

        $this->assertFileExists($controllerFile);
        $this->assertTrue(class_exists('mcpapi_ctl_v1_user'));

        foreach (['list', 'show'] as $action) {
            $this->assertContains($action, get_class_methods('mcpapi_ctl_v1_user'));
        }

        $this->assertContains('create', get_class_methods('mcpapi_ctl_v1_user'));
        $this->assertContains('update', get_class_methods('mcpapi_ctl_v1_user'));
    }

    public function testListAndShowUseFrozenRoutePermissions(): void
    {
        $list = new ReflectionMethod(mcpapi_ctl_v1_user::class, 'list');
        $show = new ReflectionMethod(mcpapi_ctl_v1_user::class, 'show');

        $listAttributes = $list->getAttributes(app\desktop\lib\annotation\RequirePermission::class);
        $showAttributes = $show->getAttributes(app\desktop\lib\annotation\RequirePermission::class);

        $this->assertCount(1, $listAttributes);
        $this->assertCount(1, $showAttributes);
        $this->assertSame('users', $listAttributes[0]->getArguments()[0]);
        $this->assertSame('operator_edit', $showAttributes[0]->getArguments()[0]);
    }

    public function testRouterRejectsListWithoutTheFrozenUsersPermission(): void
    {
        $router = new mcpapi_router($this->guard(['operator_edit']));
        $response = $router->dispatch(
            'POST',
            '/index.php/mcp-api/v1/user/list',
            $this->authorization(),
            'user-list-denied',
            [],
            'application/json'
        );

        $this->assertSame(403, $response->status());
        $this->assertSame(['error' => 'permission_denied'], $response->body());
    }

    public function testCreateAndUpdateDeclareTheFrozenWritePermissionsAndDispatch(): void
    {
        foreach ([
            'create' => 'operator_add',
            'update' => 'operator_edit',
        ] as $action => $permission) {
            $method = new ReflectionMethod(mcpapi_ctl_v1_user::class, $action);
            $attributes = $method->getAttributes(app\desktop\lib\annotation\RequirePermission::class);
            $this->assertCount(1, $attributes);
            $this->assertSame($permission, $attributes[0]->getArguments()[0]);

            $router = new mcpapi_router($this->guard([]));
            $response = $router->dispatch(
                'POST',
                '/index.php/mcp-api/v1/user/' . $action,
                $this->authorization(),
                'user-' . $action . '-denied',
                [],
                'application/json'
            );

            $this->assertSame(403, $response->status());
            $this->assertSame(['error' => 'permission_denied'], $response->body());
        }
    }

    private function guard(array $permissions): mcpapi_auth_guard
    {
        $record = new mcpapi_auth_client_record(
            'user-client',
            'user-tenant',
            'https://oms.example.test',
            7,
            [1],
            $permissions,
            $permissions,
            true,
            null,
            time() + 3600,
            '',
            '',
            '',
            '',
            null,
            7
        );
        return new mcpapi_auth_guard(
            static fn(string $token): array => [],
            null,
            null,
            static fn(string $clientId, string $clientSecret): ?mcpapi_auth_client_record => $clientId === 'user-client'
                && $clientSecret === 'user-secret' ? $record : null
        );
    }

    private function authorization(): string
    {
        return 'Basic ' . base64_encode('user-client:user-secret');
    }
}
