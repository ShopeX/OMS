<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class RequirePermissionAnnotationTest extends TestCase
{
    public function testLegacyScalarPermissionRemainsAvailable(): void
    {
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';

        $attribute = new app\desktop\lib\annotation\RequirePermission('operator_edit');

        $this->assertSame('operator_edit', $attribute->permission);
        $this->assertSame([], $attribute->all);
    }

    public function testAllPermissionsFormStoresExplicitAndList(): void
    {
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';

        $attribute = new app\desktop\lib\annotation\RequirePermission(all: ['a', 'b']);

        $this->assertNull($attribute->permission);
        $this->assertSame(['a', 'b'], $attribute->all);
    }

    public function testMixedScalarAndAllUsageIsRejected(): void
    {
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';

        $this->expectException(InvalidArgumentException::class);
        new app\desktop\lib\annotation\RequirePermission('a', ['b']);
    }

    public function testEmptyScalarPermissionIsRejected(): void
    {
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';

        $this->expectException(InvalidArgumentException::class);
        new app\desktop\lib\annotation\RequirePermission('');
    }

    public function testEmptyScalarWithAllPermissionsIsRejected(): void
    {
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';

        $this->expectException(InvalidArgumentException::class);
        new app\desktop\lib\annotation\RequirePermission('', ['b']);
    }

    public function testNoPermissionArgumentsAreRejected(): void
    {
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';

        $this->expectException(InvalidArgumentException::class);
        new app\desktop\lib\annotation\RequirePermission();
    }

    public function testEmptyAllEntryIsRejected(): void
    {
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';

        $this->expectException(InvalidArgumentException::class);
        new app\desktop\lib\annotation\RequirePermission(all: ['']);
    }

    public function testNonStringAllEntryIsRejected(): void
    {
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';

        $this->expectException(InvalidArgumentException::class);
        new app\desktop\lib\annotation\RequirePermission(all: ['a', 1]);
    }

    public function testDesktopAllPermissionsRequireEveryEntry(): void
    {
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/user.php';

        $previousGet = $_GET;
        $_GET = [
            'app' => 'desktop',
            'ctl' => 'permission_probe',
            'act' => 'handle',
            '_finder' => [],
            'finder_id' => '',
            'find_id' => '',
            'goto' => '',
            'workground' => '',
        ];

        try {
            $user = new class extends desktop_user {
                public array $authorizedPermissions = [];

                public function __construct() {}

                public function has_permission($permission_id)
                {
                    return in_array($permission_id, $this->authorizedPermissions, true);
                }
            };

            $user->authorizedPermissions = ['perm.a'];
            $this->assertFalse($user->chkground('test_workground'));

            $user->authorizedPermissions = ['perm.a', 'perm.b'];
            $this->assertTrue($user->chkground('test_workground'));
        } finally {
            $_GET = $previousGet;
        }
    }

    public function testMalformedAnnotationDeniesInsteadOfFallingBackToLegacyAuthorization(): void
    {
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/annotation/RequirePermission.php';
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/user.php';

        $previousGet = $_GET;
        $_GET = [
            'app' => 'desktop',
            'ctl' => 'malformed_permission_probe',
            'act' => 'handle',
            '_finder' => [],
            'finder_id' => '',
            'find_id' => '',
            'goto' => '',
            'workground' => '',
        ];

        try {
            $user = new class extends desktop_user {
                public function __construct() {}
            };

            $this->assertFalse($user->chkground('test_workground'));
        } finally {
            $_GET = $previousGet;
        }
    }

    /**
     * SCRUM-7701 回归：不预加载注解属性类（模拟 web 运行时 omsAutoloader 加载不到命名空间类），
     * chkground 注解路径仍须正确解析权限并放行，而不是 newInstance 抛错被吞成 false 导致子账号 403。
     */
    public function testAnnotationClassIsLazyLoadedWithoutExplicitPreload(): void
    {
        require_once dirname(__DIR__, 2) . '/app/desktop/lib/user.php';

        $previousGet = $_GET;
        $_GET = [
            'app' => 'desktop',
            'ctl' => 'permission_probe',
            'act' => 'handle',
            '_finder' => [],
            'finder_id' => '',
            'find_id' => '',
            'goto' => '',
            'workground' => '',
        ];

        try {
            $user = new class extends desktop_user {
                public array $authorizedPermissions = [];

                public function __construct() {}

                public function has_permission($permission_id)
                {
                    return in_array($permission_id, $this->authorizedPermissions, true);
                }
            };

            $user->authorizedPermissions = ['perm.a', 'perm.b'];
            $this->assertTrue($user->chkground('test_workground'));
        } finally {
            $_GET = $previousGet;
        }
    }
}

final class desktop_ctl_permission_probe
{
    #[\app\desktop\lib\annotation\RequirePermission(all: ['perm.a', 'perm.b'])]
    public function handle(): void {}
}

final class desktop_ctl_malformed_permission_probe
{
    #[\app\desktop\lib\annotation\RequirePermission]
    public function handle(): void {}
}
