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

if (!class_exists('pam_account', false)) {
    class pam_account
    {
        public static function get_account_type($appId)
        {
            return 'desktop';
        }
    }
}

if (!class_exists('pam_encrypt', false)) {
    class pam_encrypt
    {
        public static function get_encrypted_password($password, $accountType)
        {
            return sprintf('enc:%s:%s', $accountType, $password);
        }
    }
}

if (!class_exists('desktop_auth_password_security', false)) {
    require_once __DIR__ . '/../../app/desktop/lib/auth/password/security.php';
}

#[RunTestsInSeparateProcesses]
final class DesktopUserPasswordCapabilityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadPath(): void
    {
        $this->assertFileExists($this->root . '/app/desktop/lib/capability/user/password.php');
    }

    public function testSelfChangeRejectsSameOldAndNewPassword(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/password.php';

        $capability = new desktop_capability_user_password(
            new class {
                public function dump($userId, $fields = '*', $related = [])
                {
                    return ['name' => '测试用户', 'account' => ['login_name' => 'admin']];
                }
            },
            new class {
                public function checkRepeatPassWord($userId, $password, &$errorMsg)
                {
                    return true;
                }
                public function checkUserPassWordLength($userId, $password, &$errorMsg)
                {
                    return true;
                }
                public function validPassWord($password, &$errorMsg, $name = '')
                {
                    return true;
                }
                public function checkPassword($password, &$errorMsg)
                {
                    return true;
                }
                public function checkUpdatePwd($userId, $force)
                {
                }
            },
            new class {
                public function getList($fields, $filter)
                {
                    return [['account_id' => 9]];
                }
            },
            static function (array $saveData) {
                return true;
            },
            static function (int $userId): void {
            },
            static function (int $userId): void {
            },
            static function (array $payload, string $mode): array {
                return [true, ''];
            },
            'desktop'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('新密码和旧密码需要不一致');
        $capability->change([
            'old_login_password' => 'Password123456',
            'new_login_password' => 'Password123456',
            'confirm_password' => 'Password123456',
        ], 9);
    }

    public function testAdminChangePersistsAndRevokesOnSuccess(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/password.php';

        $savedPayloads = [];
        $revoked = [];
        $logged = [];
        $synced = [];

        $capability = new desktop_capability_user_password(
            new class {
                public function dump($userId, $fields = '*', $related = [])
                {
                    return ['name' => '测试用户', 'account' => ['login_name' => 'admin']];
                }
                public function getList($fields, $filter)
                {
                    return [['user_id' => 1]];
                }
            },
            new class {
                public function checkRepeatPassWord($userId, $password, &$errorMsg)
                {
                    return true;
                }
                public function checkUserPassWordLength($userId, $password, &$errorMsg)
                {
                    return true;
                }
                public function validPassWord($password, &$errorMsg, $name = '')
                {
                    return true;
                }
                public function checkPassword($password, &$errorMsg)
                {
                    return true;
                }
                public function checkUpdatePwd($userId, $force)
                {
                }
            },
            new class {
                public function getList($fields, $filter)
                {
                    return [['account_id' => 1]];
                }
            },
            static function (array $saveData) use (&$savedPayloads) {
                $savedPayloads[] = $saveData;
                return true;
            },
            static function (int $userId) use (&$revoked): void {
                $revoked[] = $userId;
            },
            static function (int $userId) use (&$logged): void {
                $logged[] = $userId;
            },
            static function (array $payload, string $mode) use (&$synced): array {
                $synced[] = [$payload, $mode];
                return [true, ''];
            },
            'desktop'
        );

        $result = $capability->change([
            'user_id' => 9,
            'old_login_password' => 'AdminPassword123',
            'new_login_password' => 'NewPassword123456',
            'confirm_password' => 'NewPassword123456',
        ], 1);

        $this->assertSame(['message' => '密码修改成功'], $result);
        $this->assertNotEmpty($savedPayloads);
        $this->assertSame([9], $revoked);
        $this->assertSame([9], $logged);
        $this->assertSame('password', $synced[0][1]);
        $this->assertSame(9, $savedPayloads[0]['user_id']);
        $this->assertArrayHasKey('password_changed_at', $savedPayloads[0]['pam_account']);
    }

    public function testSelfChangeWithoutUserIdTargetsCurrentUser(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/password.php';

        $savedPayloads = [];
        $revoked = [];
        $logged = [];
        $synced = [];

        $capability = new desktop_capability_user_password(
            new class {
                public function dump($userId, $fields = '*', $related = [])
                {
                    return ['name' => '当前用户', 'account' => ['login_name' => 'self-user']];
                }
            },
            new class {
                public function checkRepeatPassWord($userId, $password, &$errorMsg)
                {
                    return true;
                }
                public function checkUserPassWordLength($userId, $password, &$errorMsg)
                {
                    return true;
                }
                public function validPassWord($password, &$errorMsg, $name = '')
                {
                    return true;
                }
                public function checkPassword($password, &$errorMsg)
                {
                    return true;
                }
                public function checkUpdatePwd($userId, $force)
                {
                }
            },
            new class {
                public array $filters = [];
                public function getList($fields, $filter)
                {
                    $this->filters[] = $filter;
                    return [['account_id' => 5]];
                }
            },
            static function (array $saveData) use (&$savedPayloads) {
                $savedPayloads[] = $saveData;
                return true;
            },
            static function (int $userId) use (&$revoked): void {
                $revoked[] = $userId;
            },
            static function (int $userId) use (&$logged): void {
                $logged[] = $userId;
            },
            static function (array $payload, string $mode) use (&$synced): array {
                $synced[] = [$payload, $mode];
                return [true, ''];
            },
            'desktop'
        );

        $result = $capability->change([
            'old_login_password' => 'OldPassword123',
            'new_login_password' => 'NewPassword123456',
            'confirm_password' => 'NewPassword123456',
        ], 5);

        $this->assertSame(['message' => '密码修改成功'], $result);
        $this->assertSame(5, $savedPayloads[0]['user_id']);
        $this->assertSame([5], $revoked);
        $this->assertSame([5], $logged);
        $this->assertSame(5, $synced[0][0]['account_id']);
    }

    public function testSelfChangeWithCurrentUserIdDoesNotEscalateToAdminMode(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/password.php';

        $savedPayloads = [];
        $revoked = [];
        $logged = [];
        $synced = [];

        $pamAccountModel = new class {
            public array $filters = [];
            public function getList($fields, $filter)
            {
                $this->filters[] = $filter;
                return [['account_id' => 7]];
            }
        };

        $capability = new desktop_capability_user_password(
            new class {
                public function dump($userId, $fields = '*', $related = [])
                {
                    return ['name' => '当前用户', 'account' => ['login_name' => 'self-user']];
                }
            },
            new class {
                public function checkRepeatPassWord($userId, $password, &$errorMsg)
                {
                    return true;
                }
                public function checkUserPassWordLength($userId, $password, &$errorMsg)
                {
                    return true;
                }
                public function validPassWord($password, &$errorMsg, $name = '')
                {
                    return true;
                }
                public function checkPassword($password, &$errorMsg)
                {
                    return true;
                }
                public function checkUpdatePwd($userId, $force)
                {
                }
            },
            $pamAccountModel,
            static function (array $saveData) use (&$savedPayloads) {
                $savedPayloads[] = $saveData;
                return true;
            },
            static function (int $userId) use (&$revoked): void {
                $revoked[] = $userId;
            },
            static function (int $userId) use (&$logged): void {
                $logged[] = $userId;
            },
            static function (array $payload, string $mode) use (&$synced): array {
                $synced[] = [$payload, $mode];
                return [true, ''];
            },
            'desktop'
        );

        $result = $capability->change([
            'user_id' => 7,
            'old_login_password' => 'OldPassword123',
            'new_login_password' => 'NewPassword123456',
            'confirm_password' => 'NewPassword123456',
        ], 7);

        $this->assertSame(['message' => '密码修改成功'], $result);
        $this->assertSame(7, $savedPayloads[0]['user_id']);
        $this->assertSame([7], $revoked);
        $this->assertSame([7], $logged);
        $this->assertCount(1, $pamAccountModel->filters);
        $this->assertSame(7, $pamAccountModel->filters[0]['account_id']);
        $this->assertArrayNotHasKey('super', $pamAccountModel->filters[0]);
    }
}
