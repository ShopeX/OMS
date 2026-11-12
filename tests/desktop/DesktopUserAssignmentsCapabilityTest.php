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
final class DesktopUserAssignmentsCapabilityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadPath(): void
    {
        $this->assertFileExists($this->root . '/app/desktop/lib/capability/user/assignments.php');
    }

    public function testUpdateAssignmentsPersistsRolesAndProjectionWrites(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/assignments.php';

        $usersModel = new class {
            public array $editPayloads = [];
            public array $savePerPayloads = [];

            public function editUser(array $data): bool
            {
                $this->editPayloads[] = $data;
                return true;
            }

            public function save_per(array $data): void
            {
                $this->savePerPayloads[] = $data;
            }
        };

        $groundSaverCalls = [];
        $capability = new desktop_capability_user_assignments(
            $usersModel,
            static function (array $payload) use (&$groundSaverCalls): void {
                $groundSaverCalls[] = $payload;
            }
        );

        $result = $capability->update(9, [
            'role' => [1, 2],
            'confirm_group' => [11],
            'branch' => [21],
            'store_id' => [31]
        ]);

        $this->assertSame(['success' => true], $result);
        $this->assertSame(9, $usersModel->editPayloads[0]['account_id']);
        $this->assertSame(9, $usersModel->editPayloads[0]['user_id']);
        $this->assertSame([['role_id' => 1], ['role_id' => 2]], $usersModel->editPayloads[0]['roles']);
        $this->assertSame([1, 2], $usersModel->savePerPayloads[0]['role']);
        $this->assertCount(1, $groundSaverCalls);
    }

    public function testUpdateAssignmentsRejectsEmptyRoles(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/assignments.php';

        $capability = new desktop_capability_user_assignments(new class {
            public function editUser(array $data): bool
            {
                return true;
            }

            public function save_per(array $data): void
            {
            }
        }, static function (array $payload): void {
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('请至少选择一个工作组！');
        $capability->update(9, ['role' => []]);
    }

    public function testUpdateAssignmentsPreservesExistingLoginNameAndProfileFields(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/assignments.php';

        $usersModel = new class {
            public array $editPayloads = [];

            public function dump($userId, $fields = '*', $related = [])
            {
                return [
                    'op_no' => 'TEST001',
                    'name' => '测试用户',
                    'mobile' => '13800000001',
                    'email' => 'test@test.com',
                    'memo' => '',
                    'status' => '1',
                    'mcp_enabled' => '0',
                    'account' => ['login_name' => 'test_create_1780844120']
                ];
            }

            public function editUser(array $data): bool
            {
                $this->editPayloads[] = $data;
                return true;
            }

            public function save_per(array $data): void
            {
            }
        };

        $capability = new desktop_capability_user_assignments($usersModel, static function (array $payload): void {
        });
        $capability->update(9, ['role' => [1]]);

        $payload = $usersModel->editPayloads[0];
        $this->assertSame('test_create_1780844120', $payload['pam_account']['login_name']);
        $this->assertSame('测试用户', $payload['name']);
        $this->assertSame('13800000001', $payload['mobile']);
    }
}
