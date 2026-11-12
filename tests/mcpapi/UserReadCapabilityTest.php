<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UserReadCapabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        chdir(dirname(__DIR__, 2));
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

    public function testListNormalizesPaginationAndRedactsAuthenticationFields(): void
    {
        $users = new class {
            public function getList(string $columns, array $filter, int $offset, int $limit, string $order): array
            {
                TestCase::assertStringNotContainsString('password', strtolower($columns));
                TestCase::assertSame(['user_id' => [7, 8]], $filter);
                TestCase::assertSame(0, $offset);
                TestCase::assertSame(100, $limit);
                TestCase::assertSame('user_id DESC', $order);

                return [[
                    'user_id' => 7,
                    'name' => 'Visible',
                    'mobile' => '13800000000',
                    'email' => 'visible@example.test',
                    'op_no' => 'OP7',
                    'status' => 1,
                    'mcp_enabled' => 1,
                    'super' => 0,
                    'password' => 'must-not-leak',
                    'session_id' => 'must-not-leak',
                    'token_version' => 9,
                ]];
            }

            public function count(array $filter): int
            {
                TestCase::assertSame(['user_id' => [7, 8]], $filter);
                return 2;
            }
        };
        $operationOps = new class {
            public function getList(string $columns, array $filter, int $offset, int $limit): array
            {
                if (isset($filter['org_id|in'])) {
                    TestCase::assertSame('op_id', $columns);
                    TestCase::assertSame(['org_id|in' => [10]], $filter);
                    return [['op_id' => 7], ['op_id' => 8]];
                }
                return [['org_id' => 10]];
            }
        };
        $roles = new class {
            public function getList(string $columns, array $filter): array
            {
                return [['role_id' => 3, 'role_name' => 'Operator']];
            }
        };
        $hasRole = new class {
            public function getList(string $columns, array $filter): array
            {
                return [['role_id' => 3]];
            }
        };

        $capability = new desktop_capability_user_read($users, $roles, $hasRole, $operationOps);
        $result = $capability->list(['page' => 0, 'page_size' => 999], false, [10]);

        self::assertSame(1, $result['page']);
        self::assertSame(100, $result['page_size']);
        self::assertSame(2, $result['total']);
        self::assertSame([7], $result['items'][0]['user_id'] ? [$result['items'][0]['user_id']] : []);
        self::assertSame([3], $result['items'][0]['role_ids']);
        self::assertArrayNotHasKey('password', $result['items'][0]);
        self::assertArrayNotHasKey('session_id', $result['items'][0]);
        self::assertArrayNotHasKey('token_version', $result['items'][0]);
    }

    public function testShowReturnsSafeRoleAndOrganizationSummaries(): void
    {
        $users = new class {
            public function dump(int $userId, string $columns, array $joins): array
            {
                TestCase::assertSame(7, $userId);
                return [
                    'user_id' => 7,
                    'name' => 'Visible',
                    'mobile' => '13800000000',
                    'email' => 'visible@example.test',
                    'op_no' => 'OP7',
                    'memo' => 'memo',
                    'status' => '1',
                    'mcp_enabled' => '1',
                    'super' => '0',
                    'account' => [
                        'login_name' => 'visible',
                        'login_password' => 'hash-must-not-leak',
                        'session_id' => 'session-must-not-leak',
                    ],
                ];
            }
        };
        $roles = new class {
            public function getList(string $columns, array $filter): array
            {
                return [['role_id' => 3, 'role_name' => 'Operator', 'workground' => 'secret-permissions']];
            }
        };
        $hasRole = new class {
            public function getList(string $columns, array $filter): array
            {
                return [['role_id' => 3]];
            }
        };
        $operationOps = new class {
            public function getList(string $columns, array $filter, int $offset = 0, int $limit = -1): array
            {
                if (($filter['op_id'] ?? null) === 7) {
                    return [['org_id' => 10]];
                }
                return [['op_id' => 7]];
            }
        };
        $orgs = new class {
            public function getList(string $columns, array $filter): array
            {
                return [['org_id' => 10, 'name' => 'Operations']];
            }
        };

        $capability = new desktop_capability_user_read($users, $roles, $hasRole, $operationOps, $orgs);
        $result = $capability->show(7, false, [10]);

        self::assertSame(7, $result['user_id']);
        self::assertSame([['role_id' => 3, 'role_name' => 'Operator']], $result['roles']);
        self::assertSame([['org_id' => 10, 'name' => 'Operations']], $result['operation_orgs']);
        self::assertArrayNotHasKey('account', $result);
        self::assertArrayNotHasKey('login_password', $result);
        self::assertStringNotContainsString('secret-permissions', json_encode($result));
        self::assertStringNotContainsString('session-must-not-leak', json_encode($result));
    }

    public function testShowReturnsConfirmationGroupAndBranchAssignments(): void
    {
        $users = new class {
            public function dump(int $userId, string $columns, array $joins): array
            {
                return [
                    'user_id' => $userId,
                    'name' => 'Assigned',
                    'status' => '1',
                    'mcp_enabled' => '1',
                    'super' => '0',
                    'account' => ['login_name' => 'assigned'],
                ];
            }
        };
        $roles = new class {
            public function getList(string $columns, array $filter): array
            {
                return [];
            }
        };
        $hasRole = new class {
            public function getList(string $columns, array $filter): array
            {
                return [];
            }
        };
        $operationOps = new class {
            public function getList(string $columns, array $filter, int $offset = 0, int $limit = -1): array
            {
                if (($filter['op_id'] ?? null) === 7) return [['org_id' => 10]];
                return [];
            }
        };
        $orgs = new class {
            public function getList(string $columns, array $filter): array
            {
                return [];
            }
        };
        $groupOps = new class {
            public function getList(string $columns, array $filter, int $offset = 0, int $limit = -1): array
            {
                return [['group_id' => 1]];
            }
        };
        $branchOps = new class {
            public function getList(string $columns, array $filter, int $offset = 0, int $limit = -1): array
            {
                return [['branch_id' => 1]];
            }
        };

        $capability = new desktop_capability_user_read($users, $roles, $hasRole, $operationOps, $orgs, null, null, null, $groupOps, $branchOps);
        $result = $capability->show(7, false, [10]);

        self::assertSame([1], $result['confirm_group']);
        self::assertSame([1], $result['branch']);
    }

    public function testShowHidesOutOfScopeAndMissingUsersIdentically(): void
    {
        $users = new class {
            public function dump(int $userId, string $columns, array $joins): array
            {
                return ['user_id' => $userId, 'name' => 'Outside', 'super' => 0];
            }
        };
        $operationOps = new class {
            public function getList(string $columns, array $filter, int $offset = 0, int $limit = -1): array
            {
                return [['org_id' => 99]];
            }
        };

        $capability = new desktop_capability_user_read($users, new stdClass(), new stdClass(), $operationOps);

        self::assertNull($capability->show(7, false, [10]));
        self::assertNull($capability->show(999, false, []));
    }

    public function testSuperListCanSeeTenantUsersWithoutSubjectOrganizationRows(): void
    {
        $users = new class {
            public int $calls = 0;

            public function getList(string $columns, array $filter, int $offset, int $limit, string $order): array
            {
                $this->calls++;
                if ($this->calls === 1) {
                    return [['user_id' => 7], ['user_id' => 8]];
                }
                return [];
            }

            public function count(array $filter): int
            {
                return 2;
            }
        };
        $capability = new desktop_capability_user_read(
            $users,
            new stdClass(),
            new stdClass(),
            new stdClass()
        );

        $result = $capability->list([], true, []);

        self::assertSame(2, $result['total']);
        self::assertSame(2, $users->calls);
    }

    public function testControllerListWorksDirectlyFromAuthContextWithoutDesktopUserGlobalState(): void
    {
        $users = new class {
            public function getList(string $columns, array $filter, int $offset, int $limit, string $order): array
            {
                return [];
            }

            public function count(array $filter): int
            {
                return 0;
            }
        };
        $capability = new desktop_capability_user_read(
            $users,
            new stdClass(),
            new stdClass(),
            new class {
                public function getList(string $columns, array $filter, int $offset = 0, int $limit = -1): array
                {
                    return [['op_id' => 7]];
                }
            }
        );
        $controller = new mcpapi_ctl_v1_user($capability);
        $context = new mcpapi_auth_context(
            'user-read-request',
            'user-client',
            'user-subject',
            'user-tenant',
            'https://oms.example.test',
            7,
            [],
            ['users'],
            ['users'],
            time() + 3600
        );

        $response = $controller->list($context, []);

        self::assertSame(200, $response->status());
        self::assertSame([], $response->body()['data']['items']);
    }
}
