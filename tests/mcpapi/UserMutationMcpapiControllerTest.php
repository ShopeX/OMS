<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UserMutationMcpapiControllerTest extends TestCase
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

    public function testCreateMapsMcpFieldsToCapabilityAndBuildsServerAuthority(): void
    {
        $mutation = $this->mutation();
        $desktopUser = $this->desktopUser([10]);
        $controller = new mcpapi_ctl_v1_user(
            $this->read(),
            $desktopUser,
            $mutation,
            static fn(string $login): bool => false
        );

        $response = $controller->create($this->context(['operator_add'], true), [
            'login_name' => 'new-user',
            'password' => 'Password-Sentinel-123',
            'name' => 'New User',
            'role_ids' => [3],
            'org_id' => [10],
        ]);

        self::assertSame(200, $response->status());
        self::assertSame(1, $mutation->createCalls);
        self::assertSame('new-user', $mutation->createRequest['pam_account']['login_name']);
        self::assertSame('Password-Sentinel-123', $mutation->createRequest['pam_account']['login_password']);
        self::assertSame([3], $mutation->createRequest['role']);
        self::assertArrayNotHasKey('role_ids', $mutation->createRequest);
        self::assertArrayNotHasKey('owner_id', $mutation->createRequest);
        self::assertArrayNotHasKey('tenant_id', $mutation->createRequest);
        self::assertSame(7, $mutation->authority['subject_id']);
        self::assertTrue($mutation->authority['is_super']);
        self::assertStringNotContainsString('Password-Sentinel-123', $response->json());
    }

    public function testLegacyRoleFieldIsRejectedAsAnUnknownPublicField(): void
    {
        $mutation = $this->mutation();
        $controller = new mcpapi_ctl_v1_user(
            $this->read(),
            $this->desktopUser([10]),
            $mutation,
            static fn(string $login): bool => false
        );

        $response = $controller->create($this->context(['operator_add'], false), [
            'login_name' => 'legacy-role-user',
            'password' => 'Password-Sentinel-123',
            'name' => 'Legacy Role',
            'role' => [3],
            'org_id' => [10],
        ]);

        self::assertSame(422, $response->status());
        self::assertSame('VALIDATION_FAILED', $response->body()['error']);
        self::assertSame(0, $mutation->createCalls);
        self::assertStringNotContainsString('Password-Sentinel-123', $response->json());
    }

    public function testUpdateRejectsStaleFingerprintBeforeCapabilityCall(): void
    {
        $mutation = $this->mutation();
        $read = $this->read();
        $read->visibleUser = [
            'user_id' => 42,
            'name' => 'Current',
            'state_fingerprint' => 'fingerprint-current',
        ];
        $controller = new mcpapi_ctl_v1_user(
            $read,
            $this->desktopUser([10]),
            $mutation,
            null,
            static fn(int $userId, bool $isSuper, array $scope): ?bool => true
        );

        $response = $controller->update($this->context(['operator_edit'], false), [
            'user_id' => 42,
            'expected_state_fingerprint' => 'fingerprint-old',
            'name' => 'Changed',
        ]);

        self::assertSame(409, $response->status());
        self::assertSame('STALE_STATE', $response->body()['error']);
        self::assertSame(0, $mutation->updateCalls);
    }

    public function testUpdateMapsCanonicalRoleIdsToTheCapabilityRoleField(): void
    {
        $mutation = $this->mutation();
        $read = $this->read();
        $read->visibleUser = [
            'user_id' => 42,
            'state_fingerprint' => 'fingerprint-current',
        ];
        $controller = new mcpapi_ctl_v1_user(
            $read,
            $this->desktopUser([10]),
            $mutation,
            null,
            static fn(int $userId, bool $isSuper, array $scope): ?bool => true
        );

        $response = $controller->update($this->context(['operator_edit'], false), [
            'user_id' => 42,
            'expected_state_fingerprint' => 'fingerprint-current',
            'role_ids' => [5],
            'name' => 'Updated',
        ]);

        self::assertSame(200, $response->status());
        self::assertSame(1, $mutation->updateCalls);
        self::assertSame([5], $mutation->updateRequest['request']['role']);
        self::assertArrayNotHasKey('role_ids', $mutation->updateRequest['request']);
    }

    public function testUpdateRejectsLegacyRoleFieldAsAnUnknownPublicField(): void
    {
        $mutation = $this->mutation();
        $read = $this->read();
        $read->visibleUser = ['user_id' => 42, 'state_fingerprint' => 'fingerprint-current'];
        $controller = new mcpapi_ctl_v1_user(
            $read,
            $this->desktopUser([10]),
            $mutation,
            null,
            static fn(int $userId, bool $isSuper, array $scope): ?bool => true
        );

        $response = $controller->update($this->context(['operator_edit'], false), [
            'user_id' => 42,
            'expected_state_fingerprint' => 'fingerprint-current',
            'role' => [5],
        ]);

        self::assertSame(422, $response->status());
        self::assertSame('VALIDATION_FAILED', $response->body()['error']);
        self::assertSame(0, $mutation->updateCalls);
    }

    public function testOutOfScopeCreateIsForbiddenWithoutCapabilityCall(): void
    {
        $mutation = $this->mutation();
        $controller = new mcpapi_ctl_v1_user(
            $this->read(),
            $this->desktopUser([10]),
            $mutation
        );

        $response = $controller->create($this->context(['operator_add'], false), [
            'login_name' => 'outside-user',
            'password' => 'Password-Sentinel-123',
            'name' => 'Outside',
            'role_ids' => [3],
            'org_id' => [99],
        ]);

        self::assertSame(403, $response->status());
        self::assertSame('FORBIDDEN', $response->body()['error']);
        self::assertSame(0, $mutation->createCalls);
    }

    public function testOutOfScopeUpdateIsForbiddenBeforeFingerprintOrMutation(): void
    {
        $mutation = $this->mutation();
        $controller = new mcpapi_ctl_v1_user(
            $this->read(),
            $this->desktopUser([10]),
            $mutation,
            null,
            static fn(int $userId, bool $isSuper, array $scope): ?bool => false
        );

        $response = $controller->update($this->context(['operator_edit'], false), [
            'user_id' => 99,
            'expected_state_fingerprint' => 'fingerprint',
            'name' => 'Outside',
        ]);

        self::assertSame(403, $response->status());
        self::assertSame('FORBIDDEN', $response->body()['error']);
        self::assertSame(0, $mutation->updateCalls);
    }

    public function testDuplicateLoginAndCapabilityOutcomesUseStableSafeMappings(): void
    {
        $duplicateController = new mcpapi_ctl_v1_user(
            $this->read(),
            $this->desktopUser([10]),
            $this->mutation(),
            static fn(string $login): bool => true
        );
        $duplicate = $duplicateController->create($this->context(['operator_add'], true), [
            'login_name' => 'existing-user',
            'password' => 'Password-Sentinel-123',
            'name' => 'Existing',
            'role_ids' => [3],
            'org_id' => [10],
        ]);
        self::assertSame(409, $duplicate->status());
        self::assertSame('DUPLICATE_LOGIN', $duplicate->body()['error']);
        self::assertStringNotContainsString('Password-Sentinel-123', $duplicate->json());

        foreach ([
            ['status' => 'partial', 'http' => 409, 'error' => 'PARTIAL_WRITE'],
            ['status' => 'unknown', 'http' => 503, 'error' => 'WRITE_AMBIGUOUS'],
        ] as $outcome) {
            $mutation = $this->mutation();
            $mutation->result = ['status' => $outcome['status'], 'user_id' => 42];
            $controller = new mcpapi_ctl_v1_user(
                $this->read(),
                $this->desktopUser([10]),
                $mutation,
                static fn(string $login): bool => false
            );
            $response = $controller->create($this->context(['operator_add'], true), [
                'login_name' => 'user-' . $outcome['status'],
                'password' => 'Password-Sentinel-123',
                'name' => 'User',
                'role_ids' => [3],
                'org_id' => [10],
            ]);
            self::assertSame($outcome['http'], $response->status());
            self::assertSame($outcome['error'], $response->body()['error']);
        }
    }

    private function read(): object
    {
        return new class {
            public array $visibleUser = [];

            public function show(int $userId, bool $isSuper, array $allowedOrganizationIds): ?array
            {
                return $this->visibleUser === [] ? null : $this->visibleUser;
            }
        };
    }

    private function mutation(): object
    {
        return new class {
            public int $createCalls = 0;
            public int $updateCalls = 0;
            public array $createRequest = [];
            public array $updateRequest = [];
            public array $authority = [];
            public array $result = ['status' => 'committed', 'user_id' => 42];

            public function create(array $request, array $authority): array
            {
                $this->createCalls++;
                $this->createRequest = $request;
                $this->authority = $authority;
                return $this->result;
            }

            public function update(int $userId, array $request, array $authority): array
            {
                $this->updateCalls++;
                $this->updateRequest = ['user_id' => $userId, 'request' => $request];
                $this->authority = $authority;
                return $this->result;
            }
        };
    }

    private function context(array $permissions, bool $isSuper): mcpapi_auth_context
    {
        return new mcpapi_auth_context(
            'user-mutation-request',
            'user-client',
            'user-subject',
            'user-tenant',
            'https://oms.example.test',
            7,
            [3],
            $permissions,
            ['operation_ops'],
            time() + 3600,
            $isSuper
        );
    }

    private function desktopUser(array $scope): object
    {
        return new class($scope) {
            public int $virtualUserId = 0;
            private array $scope;

            public function __construct(array $scope)
            {
                $this->scope = $scope;
            }

            public function setVirtualLogin(int $userId): void
            {
                $this->virtualUserId = $userId;
            }

            public function get_organization_permission(): array
            {
                return $this->scope;
            }
        };
    }
}
