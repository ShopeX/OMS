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
            return 'hash:' . $accountType . ':' . $password;
        }
    }
}

#[RunTestsInSeparateProcesses]
final class DesktopUserMutationCapabilityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCreateRollsBackEveryWriteAndDoesNotSendPlaintextPasswordEmail(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/mutation.php';

        $database = new class {
            public int $begins = 0;
            public int $commits = 0;
            public int $rollbacks = 0;

            public function beginTransaction(): string
            {
                $this->begins++;
                return 'tx-1';
            }

            public function commit(string $transaction): void
            {
                $this->commits++;
            }

            public function rollBack(string $transaction): void
            {
                $this->rollbacks++;
            }
        };
        $model = new class {
            public array $saved = [];

            public function validate(array $payload, &$message): bool
            {
                return true;
            }

            public function save(array &$payload): bool
            {
                $payload['user_id'] = 42;
                $payload['pam_account']['account_id'] = 42;
                $this->saved[] = $payload;
                return true;
            }
        };
        $mailCalls = 0;
        $auditPayload = null;

        $capability = new desktop_capability_user_mutation(
            $model,
            $database,
            static function (array $payload): void {
            },
            static function (int $userId, array $orgIds): bool {
                return true;
            },
            static function (array $payload): void {
                throw new RuntimeException('relation write failed');
            },
            static function (array $payload): bool {
                return true;
            },
            static function (array $payload) use (&$auditPayload): void {
                $auditPayload = $payload;
            },
            static function (array $payload, string $mode): array {
                return [true, ''];
            }
        );

        try {
            $capability->create([
                'name' => '测试用户',
                'mobile' => '13800138000',
                'email' => 'user@example.test',
                'role' => [3],
                'org_id' => [7],
                'pam_account' => [
                    'login_name' => 'new-user',
                    'login_password' => 'Plaintext-Sentinel-123',
                ],
            ], $this->authority());
            $this->fail('Expected the relation failure to be raised');
        } catch (RuntimeException $exception) {
            $this->assertSame('用户写入失败', $exception->getMessage());
        }

        $this->assertSame(1, $database->begins);
        $this->assertSame(0, $database->commits);
        $this->assertSame(1, $database->rollbacks);
        $this->assertSame(0, $mailCalls);
        $this->assertNull($auditPayload);
        $this->assertStringNotContainsString('Plaintext-Sentinel-123', json_encode($auditPayload));
    }

    public function testUpdatePreservesOmittedAssignmentsAndAllowsExplicitEmptyScope(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/mutation.php';

        $model = new class {
            public array $edited = [];

            public function dump(int $userId): array
            {
                return [
                    'user_id' => $userId,
                    'name' => '原姓名',
                    'role' => [4],
                    'org_id' => [8],
                    'store_id' => [9],
                    'account' => ['login_name' => 'existing-user'],
                ];
            }

            public function editUser(array &$payload): bool
            {
                $this->edited = $payload;
                return true;
            }

            public function save_per(array $payload): void
            {
            }
        };
        $database = $this->database();
        $operationScopes = [];

        $capability = new desktop_capability_user_mutation(
            $model,
            $database,
            static function (array $payload): bool {
                return true;
            },
            static function (int $userId, array $orgIds): bool {
                return true;
            },
            static function (array $payload): bool {
                return true;
            },
            static function (array $payload) use (&$operationScopes): bool {
                $operationScopes[] = $payload['org_id'];
                return true;
            },
            static function (array $payload): void {
            },
            static function (array $payload, string $mode): array {
                return [true, ''];
            }
        );

        $result = $capability->update(12, ['org_id' => []], $this->authority());

        $this->assertSame('committed', $result['status']);
        $this->assertSame('原姓名', $model->edited['name']);
        $this->assertSame([4], $model->edited['role']);
        $this->assertSame([[]], $operationScopes);
        foreach (['role' => [5], 'org_id' => [10], 'store_org_conf' => ['orgIds' => [11]], 'dealer_shop_conf' => ['cosIds' => [12]], 'branch' => [13], 'confirm_group' => [14]] as $field => $value) {
            $selfCapability = new desktop_capability_user_mutation($model, $database, static function (array $payload): void {}, static function (int $userId, array $orgIds): bool { return true; }, static function (array $payload): bool { return true; }, static function (array $payload): bool { return true; }, static function (array $payload): void {}, static function (array $payload, string $mode): array { return [true, '']; });
            try {
                $selfCapability->update(12, [$field => $value], array_merge($this->authority(), ['subject_id' => 12]));
                $this->fail("Expected self {$field} update to be rejected");
            } catch (RuntimeException $exception) {
                $this->assertSame('不允许修改当前登录账号', $exception->getMessage());
            }
        }
    }

    public function testRejectsPrehashedPasswordAndProtectedUserMutation(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/mutation.php';

        $capability = new desktop_capability_user_mutation(new class {
        }, $this->database());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('密码必须是明文输入');
        $capability->create([
            'name' => '测试用户',
            'role' => [3],
            'pam_account' => [
                'login_name' => 'new-user',
                'login_password' => '$2y$10$already-hashed',
            ],
        ], $this->authority());
    }

    public function testRejectsProtectedUserAndReportsExternalFailuresAfterCommit(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/mutation.php';

        $model = new class {
            public function dump(int $userId): array
            {
                return [
                    'user_id' => $userId,
                    'super' => 1,
                    'name' => '超级管理员',
                    'role' => [1],
                    'account' => ['login_name' => 'super-user'],
                ];
            }
        };
        $capability = new desktop_capability_user_mutation($model, $this->database());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('不允许修改受保护用户');
        $capability->update(1, ['name' => '不能修改'], $this->authority());
    }

    public function testExternalAndAuditFailuresAreReportedWithoutSecretFields(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/mutation.php';

        $auditPayload = null;
        $syncPayload = null;
        $model = new class {
            public function save(array &$payload): bool
            {
                $payload['user_id'] = 42;
                $payload['pam_account']['account_id'] = 42;
                return true;
            }
        };
        $capability = new desktop_capability_user_mutation(
            $model,
            $this->database(),
            static function (array $payload): void {
            },
            static function (int $userId, array $orgIds): bool {
                return true;
            },
            static function (array $payload): bool {
                return true;
            },
            static function (array $payload): bool {
                return true;
            },
            static function (array $payload) use (&$auditPayload): void {
                $auditPayload = $payload;
                throw new RuntimeException('audit backend unavailable');
            },
            static function (array $payload, string $mode) use (&$syncPayload): array {
                $syncPayload = $payload;
                return [false, 'IDAAS unavailable: Plaintext-Sentinel-123'];
            }
        );

        $result = $capability->create([
            'name' => '测试用户',
            'role' => [3],
            'pam_account' => [
                'login_name' => 'new-user',
                'login_password' => 'Plaintext-Sentinel-123',
            ],
        ], $this->authority());

        $this->assertSame('committed', $result['status']);
        $this->assertSame('failed', $result['external_sync']);
        $this->assertSame('failed', $result['audit_log']);
        $this->assertSame('hash:desktop:Plaintext-Sentinel-123', $syncPayload['login_password']);
        $this->assertStringNotContainsString('Plaintext-Sentinel-123', json_encode($result));
        $this->assertStringNotContainsString('login_password', json_encode($auditPayload));
        $this->assertStringNotContainsString('Plaintext-Sentinel-123', json_encode($auditPayload));
    }

    public function testHookRunsAfterCommitAndFailureIsReportedWithoutRollback(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/mutation.php';

        $database = new class {
            public int $commits = 0;
            public int $rollbacks = 0;

            public function beginTransaction(): string
            {
                return 'tx';
            }

            public function commit(string $transaction): void
            {
                $this->commits++;
            }

            public function rollBack(string $transaction): void
            {
                $this->rollbacks++;
            }
        };
        $model = new class {
            public function save(array &$payload): bool
            {
                $payload['user_id'] = 42;
                $payload['pam_account']['account_id'] = 42;
                return true;
            }
        };
        $hookCalls = 0; $hookPayload = null;

        $capability = new desktop_capability_user_mutation(
            $model,
            $database,
            static function (array $payload) use (&$hookCalls, &$hookPayload): void { $hookCalls++; $hookPayload = $payload; throw new RuntimeException('external hook failed: Plaintext-Sentinel-123'); },
            static function (int $userId, array $orgIds): bool {
                return true;
            },
            static function (array $payload): bool {
                return true;
            },
            static function (array $payload): bool {
                return true;
            },
            static function (array $payload): void {
            },
            static function (array $payload, string $mode): array {
                return [true, ''];
            }
        );

        $result = $capability->create([
            'name' => '测试用户',
            'role' => [3],
            'pam_account' => [
                'login_name' => 'new-user',
                'login_password' => 'Plaintext-Sentinel-123',
            ],
        ], $this->authority());

        $this->assertSame(1, $hookCalls);
        $this->assertSame(1, $database->commits);
        $this->assertSame(0, $database->rollbacks);
        $this->assertSame('committed', $result['status']);
        $this->assertSame('failed', $result['hook_status']); $this->assertIsArray($hookPayload); $this->assertArrayNotHasKey('login_password', $hookPayload['pam_account']); $this->assertStringNotContainsString('hash:', json_encode($hookPayload));
        $this->assertStringNotContainsString('Plaintext-Sentinel-123', json_encode($result));
    }

    public function testCreateDoesNotDuplicatePermissionCheckFromRouterLayer(): void
    {
        require_once $this->root . '/app/desktop/lib/capability/user/mutation.php';

        $capability = new desktop_capability_user_mutation(new class {
        }, $this->database());

        // Permission checking is the router's responsibility via RequirePermission annotation.
        // Capability no longer rejects based on permissions; it proceeds to normalization.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('密码不能为空');
        $capability->create([], [
            'subject_id' => 99,
            'is_super' => false,
            'permissions' => [],
            'scope_org_ids' => [],
        ]);
    }

    private function authority(): array
    {
        return [
            'subject_id' => 99,
            'is_super' => true,
            'permissions' => ['operator_add', 'operator_edit'],
            'scope_org_ids' => [],
        ];
    }

    private function database(): object
    {
        return new class {
            public function beginTransaction(): string
            {
                return 'tx';
            }

            public function commit(string $transaction): void
            {
            }

            public function rollBack(string $transaction): void
            {
            }
        };
    }
}
