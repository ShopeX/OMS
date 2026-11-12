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

final class SecurityRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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

    public function testSecretCreateResponseHasNoStoreHeader(): void
    {
        $controller = new mcpapi_ctl_client(new mcpapi_auth_client_manager(
            static fn(): array => [],
            static fn(): array => [],
            static function (): void {},
            static function (): void {},
            static function (): void {},
            static function (): void {},
            static fn(): string => 'Alice',
            1720000000,
            static function (): void {},
            static function (): void {},
            static function (): void {}
        ), static fn(): bool => true);
        desktop_auth_context::setUserId(7);

        $response = $controller->create(['name' => 'agent']);

        $this->assertSame('no-store', $response->headers()['Cache-Control']);
    }

    public function testOwnerEffectiveScopesAreLimitedToCurrentPermissions(): void
    {
        $owner = new mcpapi_auth_owner(
            static fn(int $userId): array => ['user_id' => $userId, 'status' => '1'],
            static fn(int $userId): array => [
                ['role_id' => 9, 'workground' => serialize(['orders.read']), 'data_authority' => serialize([])],
            ]
        );
        $record = new mcpapi_auth_client_record(
            'client-owner', 'tenant-1', 'https://oms.example.internal', 12, [3], ['stale.permission'],
            ['orders:read', 'admin:write'], true, null, time() + 300, '', '', '', '', null, 12
        );
        $registry = new class ($record) implements mcpapi_auth_client_registry {
            public function __construct(private mcpapi_auth_client_record $record) {}
            public function find(string $clientId): ?mcpapi_auth_client_record
            {
                return $clientId === $this->record->clientId() ? $this->record : null;
            }
        };

        $exchange = new mcpapi_auth_exchange(
            $registry,
            new mcpapi_auth_token_issuer(static fn(): string => 'owner-test-key'),
            $owner
        );
        $token = $exchange->issue(new mcpapi_gateway_identity('client-owner', 'external'), time());
        [, $payload] = explode('.', $token);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/') . str_repeat('=', (4 - strlen($payload) % 4) % 4)), true);

        $this->assertSame(['orders:read'], $claims['scope']);
    }

    public function testCreateRollsBackClientRowWhenPersistenceFails(): void
    {
        $events = [];
        $manager = new mcpapi_auth_client_manager(
            static fn(): array => [],
            static fn(): array => [],
            static function () use (&$events): void { $events[] = 'client'; throw new RuntimeException('client failed'); },
            static function (): void {},
            static function (): void {},
            static function (): void {},
            static fn(): string => 'Alice',
            1720000000,
            static function () use (&$events): void { $events[] = 'begin'; },
            static function () use (&$events): void { $events[] = 'commit'; },
            static function () use (&$events): void { $events[] = 'rollback'; }
        );

        $this->expectException(RuntimeException::class);
        try {
            $manager->createForOwner(7, 'agent');
        } finally {
            $this->assertSame(['begin', 'client', 'rollback'], $events);
        }
    }
}
