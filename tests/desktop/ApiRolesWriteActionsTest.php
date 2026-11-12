<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class ApiRolesWriteActionsTest extends TestCase
{
    public function testApiRolesControllerDeclaresCreateAndUpdateActionsWithPermissions(): void
    {
        if (!class_exists('desktop_controller', false)) {
            eval('class desktop_controller {}');
        }
        require_once dirname(__DIR__, 2) . '/app/desktop/controller/api/base.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/controller/api/roles.php';

        $reflection = new ReflectionClass('desktop_ctl_api_roles');

        $this->assertTrue($reflection->hasMethod('create'));
        $this->assertTrue($reflection->hasMethod('update'));

        $createPermission = $reflection->getMethod('create')->getAttributes(app\desktop\lib\annotation\RequirePermission::class)[0] ?? null;
        $updatePermission = $reflection->getMethod('update')->getAttributes(app\desktop\lib\annotation\RequirePermission::class)[0] ?? null;

        $this->assertNotNull($createPermission);
        $this->assertNotNull($updatePermission);
        $this->assertSame('user_roles_add', $createPermission->newInstance()->permission);
        $this->assertSame('user_roles_edit', $updatePermission->newInstance()->permission);
    }
}
