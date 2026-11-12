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

use PHPUnit\Framework\TestCase;

final class ClientOwnerAuthorizationTest extends TestCase
{
    private string $cwdBeforeTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cwdBeforeTest = getcwd();
        chdir(__DIR__ . '/../..');
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

    protected function tearDown(): void
    {
        chdir($this->cwdBeforeTest);
        parent::tearDown();
    }

    public function testClientIsBoundToDesktopOwnerAndClientIdIsImmutable(): void
    {
        $schema = $this->loadSchema('client');

        $this->assertSame(['client_id'], $schema['index']['uni_client_id']['columns']);
        $this->assertTrue($schema['columns']['client_id']['pkey']);
        $this->assertTrue($schema['columns']['owner_user_id']['required']);
        $this->assertSame('varchar(255)', $schema['columns']['name']['type']);
        $this->assertArrayHasKey('last_used_at', $schema['columns']);
        $this->assertArrayNotHasKey('client_secret', $schema['columns']);
        $this->assertArrayNotHasKey('client_secret_enc', $schema['columns']);
        $this->assertArrayNotHasKey('private_key', $schema['columns']);
    }

    public function testDesktopUserSchemaContainsPerSubaccountMcpToggle(): void
    {
        $schema = file_get_contents('app/desktop/dbschema/users.php');

        $this->assertIsString($schema);
        $this->assertStringContainsString("'mcp_enabled'", $schema);
    }

    public function testLegacyAuthorizationSnapshotsRemainMigrationOnly(): void
    {
        $schema = $this->loadSchema('client');

        foreach (['oms_subject_id', 'oms_role_ids', 'oms_permissions'] as $field) {
            $this->assertArrayHasKey($field, $schema['columns']);
            $this->assertTrue(mcpapi_mdl_client::isLegacyAuthorizationSnapshot($field));
        }
        $this->assertFalse(mcpapi_mdl_client::isLegacyAuthorizationSnapshot('owner_user_id'));
    }

    public function testClientKeyStoresPublicLifecycleMetadataOnly(): void
    {
        $schema = $this->loadSchema('client_key');

        foreach (['client_id', 'kid', 'algorithm', 'public_key', 'status', 'created_at', 'activated_at', 'revoked_at', 'revoked_reason'] as $field) {
            $this->assertArrayHasKey($field, $schema['columns']);
        }
        $this->assertSame(['client_id', 'kid'], $schema['index']['uni_client_kid']['columns']);
        $this->assertSame(['client_id'], $schema['index']['ind_client_id']['columns']);
        $this->assertArrayNotHasKey('private_key', $schema['columns']);
        $this->assertArrayNotHasKey('private_key', get_class_vars(mcpapi_mdl_client_key::class));
    }

    public function testClientModelsAreAvailableThroughOmsAutoload(): void
    {
        $this->assertTrue(class_exists(mcpapi_mdl_client::class));
        $this->assertTrue(class_exists(mcpapi_mdl_client_key::class));
        $this->assertStringContainsString('extends dbeav_model', file_get_contents('app/mcpapi/model/client.php'));
        $this->assertStringContainsString('extends dbeav_model', file_get_contents('app/mcpapi/model/client_key.php'));
    }

    public function testOwnerResolverReturnsCurrentRolesAndPermissions(): void
    {
        $resolver = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '1', 'mcp_enabled' => '1'],
            static fn (int $userId): array => [
                ['role_id' => 7, 'workground' => serialize(['mcpapi.client.list']), 'data_authority' => serialize(['organization.view'])],
            ]
        );

        $authorization = $resolver->resolve(12);

        $this->assertSame(12, $authorization['oms_subject_id']);
        $this->assertSame([7], $authorization['oms_role_ids']);
        $this->assertSame(['mcpapi.client.list', 'organization.view'], $authorization['oms_permissions']);
        $this->assertFalse($authorization['is_super']);
    }

    public function testOwnerResolverReturnsLiveSuperFlagWithoutRoles(): void
    {
        $resolver = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '1', 'super' => '1', 'mcp_enabled' => '1'],
            static fn (int $userId): array => []
        );

        $authorization = $resolver->resolve(12);

        $this->assertTrue($authorization['is_super']);
        $this->assertSame([], $authorization['oms_role_ids']);
        $this->assertSame([], $authorization['oms_permissions']);
    }

    public function testOwnerResolverRejectsOwnerWithoutMcpEnabled(): void
    {
        $resolver = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '1', 'mcp_enabled' => '0'],
            static fn (int $userId): array => []
        );

        $this->expectException(mcpapi_auth_exception::class);
        $resolver->resolve(12);
    }

    public function testExchangeUsesCurrentOwnerAuthorizationInsteadOfSnapshot(): void
    {
        $record = new mcpapi_auth_client_record(
            'client-owner',
            'tenant-1',
            'https://oms.example.internal',
            12,
            [3],
            ['stale.permission'],
            ['mcp:read'],
            true,
            null,
            time() + 300,
            '',
            '',
            '',
            '',
            null,
            12
        );
        $registry = new class ($record) implements mcpapi_auth_client_registry {
            private mcpapi_auth_client_record $record;

            public function __construct(mcpapi_auth_client_record $record)
            {
                $this->record = $record;
            }

            public function find(string $clientId): ?mcpapi_auth_client_record
            {
                return $clientId === $this->record->clientId() ? $this->record : null;
            }
        };
        $owner = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '1', 'mcp_enabled' => '1'],
            static fn (int $userId): array => [
                ['role_id' => 9, 'workground' => serialize(['current.permission']), 'data_authority' => serialize([])],
            ]
        );
        $issuer = new mcpapi_auth_token_issuer(static fn (): string => 'owner-test-key');
        $verifier = new mcpapi_auth_token_verifier(static fn (): string => 'owner-test-key');
        $exchange = new mcpapi_auth_exchange($registry, $issuer, $owner);

        $claims = $verifier->verify($exchange->issue(new mcpapi_gateway_identity('client-owner', 'external'), time()), time() + 1);

        $this->assertSame(12, $claims['oms_subject_id']);
        $this->assertSame([9], $claims['oms_role_ids']);
        $this->assertSame(['current.permission'], $claims['oms_permissions']);
    }

    public function testBasicAuthUsesCurrentOwnerAuthorizationInsteadOfSnapshot(): void
    {
        $record = new mcpapi_auth_client_record(
            'client-basic-owner',
            'tenant-1',
            'https://oms.example.internal',
            12,
            [3],
            ['stale.permission'],
            ['current.permission'],
            true,
            null,
            time() + 300,
            '',
            '',
            '',
            '',
            null,
            12
        );
        $owner = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '1', 'mcp_enabled' => '1'],
            static fn (int $userId): array => [
                ['role_id' => 9, 'workground' => serialize(['current.permission']), 'data_authority' => serialize([])],
            ]
        );
        $guard = new mcpapi_auth_guard(
            static fn (): array => [],
            null,
            null,
            static fn (string $clientId, string $clientSecret): ?mcpapi_auth_client_record
                => $clientId === 'client-basic-owner' && $clientSecret === 'secret-basic-owner' ? $record : null,
            $owner
        );

        $context = $guard->authenticateBasic(
            'Basic ' . base64_encode('client-basic-owner:secret-basic-owner'),
            'request-basic-owner'
        );

        $this->assertSame(12, $context->omsSubjectId());
        $this->assertSame([9], $context->omsRoleIds());
        $this->assertSame(['current.permission'], $context->omsPermissions());
        $this->assertFalse($context->isSuper());
    }

    public function testBasicAuthRejectsDisabledOwner(): void
    {
        $record = new mcpapi_auth_client_record(
            'client-disabled-owner',
            'tenant-1',
            'https://oms.example.internal',
            12,
            [3],
            ['stale.permission'],
            ['current.permission'],
            true,
            null,
            time() + 300,
            '',
            '',
            '',
            '',
            null,
            12
        );
        $owner = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '0', 'super' => '1', 'mcp_enabled' => '1'],
            static fn (int $userId): array => []
        );
        $guard = new mcpapi_auth_guard(
            static fn (): array => [],
            null,
            null,
            static fn (): ?mcpapi_auth_client_record => $record,
            $owner
        );

        $this->expectException(mcpapi_auth_exception::class);
        $guard->authenticateBasic(
            'Basic ' . base64_encode('client-disabled-owner:secret'),
            'request-disabled-owner'
        );
    }

    public function testBasicAuthAllowsLiveSuperOwnerWithoutRolesAndPreservesConfiguredScopes(): void
    {
        $record = new mcpapi_auth_client_record(
            'client-basic-super',
            'tenant-1',
            'https://oms.example.internal',
            12,
            [3],
            ['stale.permission'],
            ['goods:brand:list', 'orders:read'],
            true,
            null,
            time() + 300,
            '',
            '',
            '',
            '',
            null,
            12
        );
        $owner = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '1', 'super' => '1', 'mcp_enabled' => '1'],
            static fn (int $userId): array => []
        );
        $guard = new mcpapi_auth_guard(
            static fn (): array => [],
            null,
            null,
            static fn (): ?mcpapi_auth_client_record => $record,
            $owner
        );

        $context = $guard->authenticateBasic(
            'Basic ' . base64_encode('client-basic-super:secret'),
            'request-basic-super'
        );

        $this->assertTrue($context->isSuper());
        $this->assertTrue($context->hasPermission('goods_brand'));
        $this->assertSame(['goods:brand:list', 'orders:read'], $context->scopes());
    }

    public function testExchangedBearerPreservesLiveSuperFlagAndConfiguredScopes(): void
    {
        $record = new mcpapi_auth_client_record(
            'client-exchange-super',
            'tenant-1',
            'https://oms.example.internal',
            12,
            [],
            [],
            ['goods:brand:list', 'orders:read'],
            true,
            null,
            time() + 300,
            '',
            '',
            '',
            '',
            null,
            12
        );
        $registry = new class ($record) implements mcpapi_auth_client_registry {
            private mcpapi_auth_client_record $record;

            public function __construct(mcpapi_auth_client_record $record)
            {
                $this->record = $record;
            }

            public function find(string $clientId): ?mcpapi_auth_client_record
            {
                return $clientId === $this->record->clientId() ? $this->record : null;
            }
        };
        $owner = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '1', 'super' => '1', 'mcp_enabled' => '1'],
            static fn (int $userId): array => []
        );
        $issuer = new mcpapi_auth_token_issuer(static fn (): string => 'super-test-key');
        $verifier = new mcpapi_auth_token_verifier(static fn (): string => 'super-test-key');
        $exchange = new mcpapi_auth_exchange($registry, $issuer, $owner);
        $token = $exchange->issue(new mcpapi_gateway_identity('client-exchange-super', 'external'), time());
        $claims = $verifier->verify($token);
        $context = (new mcpapi_auth_guard(static fn (string $value): array => $verifier->verify($value)))
            ->authenticate('Bearer ' . $token, 'request-exchange-super');

        $this->assertTrue($claims['is_super']);
        $this->assertTrue($context->isSuper());
        $this->assertTrue($context->hasPermission('goods_brand'));
        $this->assertSame(['goods:brand:list', 'orders:read'], $context->scopes());
    }

    public function testBrowserJwtAuthValidatesClientThenOwnerBinding(): void
    {
        $owner = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '1', 'mcp_enabled' => '1'],
            static fn (int $userId): array => [
                ['role_id' => 9, 'workground' => serialize(['current.permission']), 'data_authority' => serialize([])],
            ]
        );
        $guard = new mcpapi_auth_guard(
            static fn (): array => [],
            null,
            null,
            static fn (): ?mcpapi_auth_client_record => null,
            $owner,
            static fn (string $token): array => [
                'user_id' => 12,
                'login_name' => 'owner',
                'type' => 'ai_assistant',
                'exp' => time() + 300,
            ],
            static fn (string $clientId): ?array => $clientId === 'mcp_browser_ok' ? [
                'client_id' => 'mcp_browser_ok',
                'owner_user_id' => 12,
                'tenant_id' => 'desktop-user-12',
                'oms_base_url' => 'https://oms.example.internal',
                'oms_subject_id' => 12,
                'oms_role_ids' => [3],
                'oms_permissions' => ['stale.permission'],
                'scopes' => ['current.permission'],
                'enabled' => true,
                'revoked_at' => null,
                'expires_at' => time() + 3600,
            ] : null
        );

        $context = $guard->authenticateBrowserJwt(
            'mcp_browser_ok',
            'Bearer fake-ai-jwt',
            'request-browser-jwt'
        );

        $this->assertSame(12, $context->omsSubjectId());
        $this->assertSame([9], $context->omsRoleIds());
        $this->assertSame(['current.permission'], $context->omsPermissions());
        $this->assertSame('mcp_browser_ok', $context->clientId());
    }

    public function testBrowserJwtAuthRejectsOwnerMismatch(): void
    {
        $owner = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '1', 'mcp_enabled' => '1'],
            static fn (int $userId): array => []
        );
        $guard = new mcpapi_auth_guard(
            static fn (): array => [],
            null,
            null,
            static fn (): ?mcpapi_auth_client_record => null,
            $owner,
            static fn (string $token): array => [
                'user_id' => 99,
                'login_name' => 'other',
                'type' => 'ai_assistant',
                'exp' => time() + 300,
            ],
            static fn (string $clientId): ?array => [
                'client_id' => $clientId,
                'owner_user_id' => 12,
                'tenant_id' => 'desktop-user-12',
                'oms_base_url' => 'https://oms.example.internal',
                'scopes' => [],
                'expires_at' => time() + 3600,
            ]
        );

        $this->expectException(mcpapi_auth_exception::class);
        $guard->authenticateBrowserJwt('mcp_mismatch', 'Bearer fake-ai-jwt', 'request-mismatch');
    }

    public function testBrowserJwtAuthRejectsInactiveClient(): void
    {
        $owner = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '1', 'mcp_enabled' => '1'],
            static fn (int $userId): array => []
        );
        $guard = new mcpapi_auth_guard(
            static fn (): array => [],
            null,
            null,
            static fn (): ?mcpapi_auth_client_record => null,
            $owner,
            static fn (string $token): array => [
                'user_id' => 12,
                'login_name' => 'owner',
                'type' => 'ai_assistant',
                'exp' => time() + 300,
            ],
            static fn (string $clientId): ?array => null
        );

        $this->expectException(mcpapi_auth_exception::class);
        $guard->authenticateBrowserJwt('mcp_revoked', 'Bearer fake-ai-jwt', 'request-revoked');
    }

    public function testRouterPrefersBrowserJwtWhenClientIdAndBearerPresent(): void
    {
        $owner = new mcpapi_auth_owner(
            static fn (int $userId): array => ['user_id' => $userId, 'status' => '1', 'mcp_enabled' => '1'],
            static fn (int $userId): array => [
                ['role_id' => 1, 'workground' => serialize([]), 'data_authority' => serialize([])],
            ]
        );
        $guard = new mcpapi_auth_guard(
            static fn (): array => [],
            null,
            null,
            static function (): ?mcpapi_auth_client_record {
                throw new RuntimeException('Basic path should not run');
            },
            $owner,
            static fn (string $token): array => [
                'user_id' => 12,
                'login_name' => 'owner',
                'type' => 'ai_assistant',
                'exp' => time() + 300,
            ],
            static fn (string $clientId): ?array => [
                'client_id' => $clientId,
                'owner_user_id' => 12,
                'tenant_id' => 'desktop-user-12',
                'oms_base_url' => 'https://oms.example.internal',
                'scopes' => [],
                'expires_at' => time() + 3600,
            ]
        );
        $router = new mcpapi_router($guard, new mcpapi_ctl_v1_health());

        $response = $router->dispatch(
            'GET',
            '/mcp-api/v1/health',
            '',
            'request-dual-channel',
            [],
            'application/json',
            [
                'X-Shopex-OMS-Client-Id' => 'mcp_router_ok',
                'X-Shopex-OMS-Client-Secret' => 'fake-ai-jwt',
                'X-Shopex-OMS-Client-Mode' => 'jwt',
            ]
        );

        $this->assertSame(200, $response->status());

        $bearerResponse = $router->dispatch(
            'GET',
            '/mcp-api/v1/health',
            'Bearer fake-ai-jwt',
            'request-dual-channel-bearer',
            [],
            'application/json',
            [
                'X-Shopex-OMS-Client-Id' => 'mcp_router_ok',
                'X-Shopex-OMS-Client-Mode' => 'jwt',
            ]
        );

        $this->assertSame(200, $bearerResponse->status());
    }

    private function loadSchema(string $name): array
    {
        $db = [];
        include "app/mcpapi/dbschema/{$name}.php";

        $this->assertArrayHasKey($name, $db);
        return $db[$name];
    }
}
