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
final class ApiUsersPasswordActionTest extends TestCase
{
    public function testApiUsersControllerDeclaresChangePasswordWithOperatorEditPermission(): void
    {
        if (!class_exists('desktop_controller', false)) {
            eval('class desktop_controller {}');
        }
        require_once dirname(__DIR__, 2) . '/app/desktop/controller/api/base.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/auth/password/security.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/capability/user/password.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/controller/api/users.php';

        $reflection = new ReflectionClass('desktop_ctl_api_users');
        $method = $reflection->getMethod('changePassword');
        $attribute = $method->getAttributes(app\desktop\lib\annotation\RequirePermission::class)[0]->newInstance();

        $this->assertSame('operator_edit', $attribute->permission);
        $this->assertSame([], $attribute->all);
        $this->assertTrue($reflection->hasProperty('passwordCapability'));
    }

    public function testChangePasswordActionReadsNormalizedRequestInputInsteadOfRawPost(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/desktop/controller/api/users.php');
        $start = strpos($source, 'public function changePassword(): void');
        $end = strpos($source, 'public function unLock(): void', $start);
        $method = substr($source, $start, $end - $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $this->assertStringContainsString('$this->getRequestInput()', $method);
        $this->assertStringNotContainsString('$_POST', $method);
    }

    public function testApiBaseJsonParserAllowsCharsetQualifiedContentType(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/desktop/controller/api/base.php');

        $normalized = preg_replace('/\s+/', ' ', $source);
        $this->assertIsString($normalized);
        $expectedExpression = 'strpos(' . '$contentType' . ", 'application/json') !== false";
        $this->assertStringContainsString($expectedExpression, $normalized);
    }
}
