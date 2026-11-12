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

final class ClientManagerTest extends TestCase
{
    private string $cwdBeforeTest;
    private array $clients = [];
    private array $keys = [];

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

    public function testOwnerCanCreateListRotateAndRevokeWithoutPersistingClientSecret(): void
    {
        $manager = $this->manager();

        $created = $manager->createForOwner(7, 'Desktop agent');
        $this->assertArrayHasKey('client_secret', $created);
        $this->assertTrue(password_verify($created['client_secret'], $this->clients[$created['client_id']]['client_secret_hash']));
        $this->assertArrayNotHasKey('client_secret', $this->clients[$created['client_id']]);

        $listed = $manager->listForOwner(7);
        $this->assertCount(1, $listed);
        $this->assertSame(substr($created['client_id'], 0, 4), substr($listed[0]['client_id'], 0, 4));
        $this->assertStringContainsString('*', $listed[0]['client_id']);
        $this->assertSame(substr($created['client_id'], -4), substr($listed[0]['client_id'], -4));
        $this->assertSame('Alice', $listed[0]['owner_name']);
        $this->assertArrayHasKey('last_used_at', $listed[0]);
        foreach (['client_secret', 'client_secret_hash', 'oms_role_ids', 'oms_permissions', 'scopes', 'tenant_id', 'oms_base_url'] as $field) {
            $this->assertArrayNotHasKey($field, $listed[0]);
        }

        $rotated = $manager->rotateForOwner(7, $created['client_id']);
        $this->assertNotSame($created['client_id'], $rotated['client_id']);
        $this->assertNotSame($created['client_secret'], $rotated['client_secret']);
        $this->assertTrue(password_verify($rotated['client_secret'], $this->clients[$rotated['client_id']]['client_secret_hash']));

        try {
            $manager->rotateForOwner(7, $created['client_id']);
            $this->fail('The old client identity remained usable after replacement');
        } catch (mcpapi_auth_exception $exception) {
            $this->assertSame(404, $exception->getCode());
        }

        $revoked = $manager->revokeForOwner(7, $rotated['client_id'], 'no longer needed');
        $this->assertSame('revoked', $revoked['status']);
        $this->assertSame($revoked, $manager->revokeForOwner(7, $rotated['client_id'], 'duplicate request'));
        $this->assertSame('revoked', $manager->listForOwner(7)[0]['status']);
    }

    public function testDuplicateOwnerIsRejectedAndMissingNameIsRejected(): void
    {
        $manager = $this->manager();

        $first = $manager->createForOwner(7, 'same name');
        $this->assertCount(1, $this->clients);

        try {
            $manager->createForOwner(7, 'same name');
            $this->fail('The owner created more than one client');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(409, $exception->getCode());
        }
        $this->assertArrayHasKey($first['client_id'], $this->clients);
        $this->assertCount(1, $this->clients);

        $this->expectException(InvalidArgumentException::class);
        $manager->createForOwner(7, '   ');
    }

    public function testClientSecretIsReturnedOnceAndStoredOnlyAsHash(): void
    {
        $manager = $this->manager();

        $created = $manager->createForOwner(7, 'secret client');

        $this->assertArrayHasKey('client_secret', $created);
        $this->assertNotSame('', $created['client_secret']);
        $this->assertArrayHasKey('client_secret_hash', $this->clients[$created['client_id']]);
        $this->assertTrue(password_verify($created['client_secret'], $this->clients[$created['client_id']]['client_secret_hash']));
        $this->assertArrayNotHasKey('client_secret', $this->clients[$created['client_id']]);
        $this->assertArrayNotHasKey('private_key', $created);
    }

    public function testAnotherOwnerCannotReadRotateOrRevokeClient(): void
    {
        $manager = $this->manager();
        $created = $manager->createForOwner(7, 'private client');

        $this->assertSame([], $manager->listForOwner(8));
        foreach ([
            static fn() => $manager->rotateForOwner(8, $created['client_id']),
            static fn() => $manager->revokeForOwner(8, $created['client_id'], 'attack'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Cross-owner lifecycle operation unexpectedly succeeded');
            } catch (mcpapi_auth_exception $exception) {
                $this->assertSame(404, $exception->getCode());
            }
        }
    }

    public function testRevokedOwnerClientCanBeReplacedWithoutExposingItsIdentifier(): void
    {
        $manager = $this->manager();
        $created = $manager->createForOwner(7, 'reprovisionable client');
        $manager->revokeForOwner(7, $created['client_id'], 'temporary disable');

        $replacement = $manager->rotateForOwner(7, '');

        $this->assertNotSame($created['client_id'], $replacement['client_id']);
        $this->assertSame('active', $replacement['status']);
        $this->assertNotSame('', $replacement['client_secret']);
        $this->assertSame('active', $manager->listForOwner(7)[0]['status']);
    }

    public function testCreateSupportsPermanentAndExplicitExpiration(): void
    {
        $manager = $this->manager();

        $permanent = $manager->createForOwner(7, 'permanent client');
        $this->assertNull($this->clients[$permanent['client_id']]['expires_at']);
        $this->assertSame('active', $manager->listForOwner(7)[0]['status']);

        $this->clients = [];
        $expiring = $manager->createForOwner(7, 'expiring client', 1720003600);
        $this->assertSame(1720003600, $this->clients[$expiring['client_id']]['expires_at']);
    }

    public function testExpirationCanBeUpdatedButRevocationCannotBeUndone(): void
    {
        $manager = $this->manager();
        $created = $manager->createForOwner(7, 'expiration client', 1720003600);
        $this->clients[$created['client_id']]['expires_at'] = 1719999900;

        $updated = $manager->updateExpirationForOwner(7, '', 1720003600);
        $this->assertSame(1720003600, $this->clients[$created['client_id']]['expires_at']);
        $this->assertSame('active', $updated['status']);

        $permanent = $manager->updateExpirationForOwner(7, '', null);
        $this->assertNull($this->clients[$created['client_id']]['expires_at']);
        $this->assertSame('active', $permanent['status']);

        $manager->revokeForOwner(7, $created['client_id'], 'test revoke');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(409);
        $manager->updateExpirationForOwner(7, '', 1720003600);
    }

    public function testCurrentOwnerClientSelectionIgnoresOlderHistoricalRows(): void
    {
        $manager = $this->manager();
        $created = $manager->createForOwner(7, 'current client');
        $currentRow = $this->clients[$created['client_id']];
        $historicalRow = array_merge($currentRow, [
            'client_id' => 'mcp_historical_client',
            'enabled' => false,
            'revoked_at' => 100,
            'created_at' => 100,
            'updated_at' => 100,
        ]);
        $this->clients[$historicalRow['client_id']] = $historicalRow;

        $listed = $manager->listForOwner(7);
        $this->assertCount(1, $listed);
        $this->assertSame(substr($created['client_id'], -4), substr($listed[0]['client_id'], -4));

        $rotated = $manager->rotateForOwner(7, '');
        $this->assertSame('active', $rotated['status']);
        $this->assertNotSame($historicalRow['client_id'], $rotated['client_id']);
    }

    public function testRouterUsesDesktopOwnerAndPermissionPerAction(): void
    {
        desktop_auth_context::setUserId(7);
        $controller = new mcpapi_ctl_client(
            $this->manager(),
            static fn(string $permission): bool => $permission !== 'mcpapi.client.revoke',
            new mcpapi_auth_owner(
                static fn(int $userId): array => ['user_id' => $userId, 'status' => '1', 'mcp_enabled' => '1'],
                static fn(): array => []
            )
        );
        $router = new mcpapi_router(
            new mcpapi_auth_guard(static fn(): array => throw new mcpapi_auth_exception()),
            null,
            null,
            null,
            $controller
        );

        $created = $router->dispatch(
            'POST',
            '/mcp-api/client',
            '',
            'request-123',
            ['name' => 'router client'],
            'application/json'
        );
        $this->assertSame(201, $created->status());
        $this->assertArrayHasKey('client_secret', $created->body());

        $list = $router->dispatch('GET', '/mcp-api/client', '', 'request-124');
        $this->assertSame(200, $list->status());
        $this->assertCount(1, $list->body()['clients']);
        $this->assertArrayNotHasKey('client_secret', $list->body()['clients'][0]);

        $revoke = $router->dispatch(
            'POST',
            '/mcp-api/client/revoke',
            '',
            'request-125',
            ['reason' => 'test'],
            'application/json'
        );
        $this->assertSame(403, $revoke->status());
    }

    public function testRouterDispatchesOwnerScopedActionsToDedicatedControllerMethods(): void
    {
        desktop_auth_context::setUserId(7);
        $router = new mcpapi_router(
            new mcpapi_auth_guard(static fn(): array => throw new mcpapi_auth_exception()),
            null,
            null,
            null,
            new mcpapi_ctl_client(
                $this->manager(),
                static fn(): bool => true,
                new mcpapi_auth_owner(
                    static fn(int $userId): array => ['user_id' => $userId, 'status' => '1', 'mcp_enabled' => '1'],
                    static fn(): array => []
                )
            )
        );

        $created = $router->dispatch(
            'POST',
            '/mcp-api/client',
            '',
            'request-create',
            ['name' => 'dedicated action client'],
            'application/json'
        );
        $this->assertSame(201, $created->status());

        $expiration = $router->dispatch(
            'POST',
            '/mcp-api/client/expiration',
            '',
            'request-expiration',
            ['expires_at' => 1720003600],
            'application/json'
        );
        $this->assertSame(200, $expiration->status());
        $this->assertSame(1720003600, $expiration->body()['client']['expires_at']);

        $rotated = $router->dispatch(
            'POST',
            '/mcp-api/client/rotate',
            '',
            'request-rotate',
            [],
            'application/json'
        );
        $this->assertSame(200, $rotated->status());
        $this->assertNotSame($created->body()['client']['client_id'], $rotated->body()['client']['client_id']);

        $revoked = $router->dispatch(
            'POST',
            '/mcp-api/client/revoke',
            '',
            'request-revoke',
            ['reason' => 'test'],
            'application/json'
        );
        $this->assertSame(200, $revoked->status());
        $this->assertSame('revoked', $revoked->body()['client']['status']);
    }

    public function testRouterRejectsClientLifecycleWhenOwnerMcpIsDisabled(): void
    {
        desktop_auth_context::setUserId(7);
        $manager = $this->manager();
        $router = new mcpapi_router(
            new mcpapi_auth_guard(static fn(): array => throw new mcpapi_auth_exception()),
            null,
            null,
            null,
            new mcpapi_ctl_client(
                $manager,
                static fn(): bool => true,
                new mcpapi_auth_owner(
                    static fn(int $userId): array => ['user_id' => $userId, 'status' => '1', 'mcp_enabled' => '0'],
                    static fn(): array => []
                )
            )
        );

        $created = $router->dispatch(
            'POST',
            '/mcp-api/client',
            '',
            'request-create-disabled',
            ['name' => 'disabled owner client'],
            'application/json'
        );
        $this->assertSame(403, $created->status());

        $existing = $manager->createForOwner(7, 'existing lifecycle client');

        $rotated = $router->dispatch(
            'POST',
            '/mcp-api/client/rotate',
            '',
            'request-rotate-disabled',
            [],
            'application/json'
        );
        $this->assertSame(403, $rotated->status());
        $this->assertArrayHasKey($existing['client_id'], $this->clients);
    }

    private function manager(): mcpapi_auth_client_manager
    {
        return new mcpapi_auth_client_manager(
            fn(int $ownerId): array => array_values(array_filter(
                $this->clients,
                static fn(array $row): bool => (int) $row['owner_user_id'] === $ownerId
            )),
            fn(string $clientId): array => isset($this->keys[$clientId]) ? [$this->keys[$clientId]] : [],
            function (array $row): void {
                $this->clients[$row['client_id']] = $row;
            },
            function (array $data, array $filter): void {
                foreach ($this->clients as $clientId => $row) {
                    if ($this->matchesRow($row, $filter)) {
                        $updated = array_merge($row, $data);
                        if (($updated['client_id'] ?? $clientId) !== $clientId) {
                            unset($this->clients[$clientId]);
                        }
                        $this->clients[$updated['client_id'] ?? $clientId] = $updated;
                    }
                }
            },
            function (array $row): void {
                $this->keys[$row['client_id']] = $row;
            },
            function (array $data, array $filter): void {
                foreach ($this->keys as $clientId => $row) {
                    if ($this->matchesRow($row, $filter)) {
                        $this->keys[$clientId] = array_merge($row, $data);
                    }
                }
            },
            static fn(int $ownerId): string => $ownerId === 7 ? 'Alice' : 'Bob',
            1720000000,
            static function (): void {
            },
            static function (): void {
            },
            static function (): void {
            },
            static fn(int $ownerId, callable $operation): array => $operation()
        );
    }

    private function matchesRow(array $row, array $filter): bool
    {
        foreach ($filter as $key => $value) {
            if (($row[$key] ?? null) !== $value) {
                return false;
            }
        }
        return true;
    }
}
