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
final class ApiUsersAssignmentsActionTest extends TestCase
{
    public function testApiUsersControllerDeclaresUpdateAssignmentsWithOperatorEditPermission(): void
    {
        if (!class_exists('desktop_controller', false)) {
            eval('class desktop_controller {}');
        }
        require_once dirname(__DIR__, 2) . '/app/desktop/controller/api/base.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/capability/user/assignments.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/controller/api/users.php';

        $reflection = new ReflectionClass('desktop_ctl_api_users');
        $method = $reflection->getMethod('updateAssignments');
        $permission = $method->getAttributes(app\desktop\lib\annotation\RequirePermission::class)[0]->newInstance()->permission;

        $this->assertSame('operator_edit', $permission);
        $this->assertTrue($reflection->hasProperty('assignmentsCapability'));
    }
}
