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

if (!class_exists('app', false)) {
    class app
    {
        public static $desktop;

        public static function get($name)
        {
            return self::$desktop;
        }
    }
}

#[RunTestsInSeparateProcesses]
final class DesktopUserProfileCapabilityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadPath(): void
    {
        $this->assertFileExists($this->root . '/app/desktop/lib/capability/user/profile.php');
    }

    public function testUpdateProfileNormalizesAndPersistsBaseFields(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/profile.php';

        $model = new class {
            public array $saved = [];

            public function editUser(array $data): bool
            {
                $this->saved = $data;
                return true;
            }
        };

        $capability = new desktop_capability_user_profile($model);
        $result = $capability->update(9, [
            'op_no' => ' ab12 ',
            'name' => ' 张三 ',
            'mobile' => '13800138000',
            'email' => 'a@example.com',
            'memo' => '备注',
            'status' => '1',
            'mcp_enabled' => '0',
        ]);

        $this->assertSame(['success' => true], $result);
        $this->assertSame('AB12', $model->saved['op_no']);
        $this->assertSame('张三', $model->saved['name']);
        $this->assertSame('13800138000', $model->saved['mobile']);
        $this->assertSame('0', $model->saved['mcp_enabled']);
        $this->assertSame(9, $model->saved['account_id']);
    }

    public function testUpdateProfileRejectsEmptyName(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/profile.php';

        $capability = new desktop_capability_user_profile(new class {
            public function editUser(array $data): bool
            {
                return true;
            }
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('请输入姓名');
        $capability->update(9, ['name' => '   ']);
    }

    public function testUpdateProfileRejectsInvalidMobileFormat(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/profile.php';

        $capability = new desktop_capability_user_profile(new class {
            public function editUser(array $data): bool
            {
                return true;
            }
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('手机号码格式不正确！');
        $capability->update(9, [
            'name' => '张三',
            'mobile' => '12345',
        ]);
    }

    public function testUpdateProfilePreservesExistingLoginName(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/profile.php';

        $model = new class {
            public array $saved = [];

            public function dump($userId, $fields = '*', $related = [])
            {
                return [
                    'account' => ['login_name' => 'test_create_1780844120']
                ];
            }

            public function editUser(array $data): bool
            {
                $this->saved = $data;
                return true;
            }
        };

        $capability = new desktop_capability_user_profile($model);
        $capability->update(9, [
            'name' => '张三',
            'mobile' => '13800138000'
        ]);

        $this->assertSame('test_create_1780844120', $model->saved['pam_account']['login_name']);
        $this->assertSame(9, $model->saved['pam_account']['account_id']);
    }
}
