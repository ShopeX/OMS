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

#[RunTestsInSeparateProcesses]
final class DesktopUserProvisioningCapabilityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCapabilityFileExistsAtAutoloadPath(): void
    {
        $this->assertFileExists($this->root . '/app/desktop/lib/capability/user/provisioning.php');
    }

    public function testProvisionRejectsMissingRoleSelection(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/provisioning.php';

        $capability = new desktop_capability_user_provisioning(
            new class {
                public function validate(array $data, &$msg)
                {
                    return true;
                }
                public function save(array &$data)
                {
                    return true;
                }
            }
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('请至少选择一个工作组');
        $capability->create([
            'pam_account' => ['login_name' => 'demo', 'login_password' => 'Password123456'],
            'name' => '测试用户',
            'super' => 0,
            'role' => []
        ]);
    }

    public function testProvisionSavesUserAndTriggersSideEffects(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/provisioning.php';

        $savedPayloads = [];
        $hookCalls = [];
        $orgCalls = [];
        $groundCalls = [];
        $dealerCalls = [];
        $mailCalls = [];
        $logCalls = [];
        $syncCalls = [];

        $usersModel = new class($savedPayloads) {
            public array $savedPayloads = [];
            public function validate(array $data, &$msg)
            {
                return true;
            }
            public function save(array &$data)
            {
                $data['user_id'] = 9;
                $data['pam_account']['account_id'] = 9;
                $this->savedPayloads[] = $data;
                return true;
            }
        };

        $capability = new desktop_capability_user_provisioning(
            $usersModel,
            static function (array $payload) use (&$hookCalls): void { $hookCalls[] = $payload; },
            static function (int $userId, array $orgIds) use (&$orgCalls): bool { $orgCalls[] = [$userId, $orgIds]; return true; },
            static function (array $payload) use (&$groundCalls): void { $groundCalls[] = $payload; },
            static function (array $payload) use (&$dealerCalls): bool { $dealerCalls[] = $payload; return true; },
            static function (string $to, string $subject, string $body) use (&$mailCalls): void { $mailCalls[] = [$to, $subject, $body]; },
            static function (array $payload) use (&$logCalls): void { $logCalls[] = $payload; },
            static function (array $payload, string $mode) use (&$syncCalls): array { $syncCalls[] = [$payload, $mode]; return [true, '']; }
        );

        $result = $capability->create([
            'op_no' => 'ab12',
            'name' => '测试用户',
            'mobile' => '13800138000',
            'email' => 'a@example.com',
            'status' => '1',
            'mcp_enabled' => '1',
            'super' => 0,
            'role' => [1],
            'pam_account' => ['login_name' => 'demo', 'login_password' => 'Password123456'],
            'store_org_conf' => ['orgIds' => ['5,6']],
            'dealer_shop_conf' => ['cosIds' => ['9,10']]
        ]);

        $this->assertSame(['success' => true, 'user_id' => 9], $result);
        $this->assertSame('AB12', $usersModel->savedPayloads[0]['op_no']);
        $this->assertNotEmpty($hookCalls);
        $this->assertSame([[9, ['5', '6']]], $orgCalls);
        $this->assertNotEmpty($groundCalls);
        $this->assertNotEmpty($dealerCalls);
        $this->assertNotEmpty($mailCalls);
        $this->assertNotEmpty($logCalls);
        $this->assertSame('add', $syncCalls[0][1]);
    }
}
