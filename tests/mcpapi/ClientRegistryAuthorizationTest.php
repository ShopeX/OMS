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
use PHPUnit\Framework\Attributes\DataProvider;

final class ClientRegistryAuthorizationTest extends TestCase
{
    private const NOW = 1720000000;

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

    public function testDatabaseRegistryResolvesImmutableAuthorizationBinding(): void
    {
        $registry = $this->registry([
            $this->row(),
        ]);

        $record = $registry->find('acme-prod-agent');

        $this->assertInstanceOf(mcpapi_auth_client_record::class, $record);
        $this->assertSame('acme-prod-agent', $record->clientId());
        $this->assertSame('acme', $record->tenantId());
        $this->assertSame('https://oms.acme.internal', $record->omsBaseUrl());
        $this->assertSame(42, $record->omsSubjectId());
        $this->assertSame([7, 9], $record->omsRoleIds());
        $this->assertSame(['orders.read'], $record->omsPermissions());
        $this->assertSame(['oms.read.organizations'], $record->scopes());
        $this->assertSame('system', $record->createdBy());
        $this->assertSame('system', $record->updatedBy());
        $this->assertSame('2024-07-01T00:00:00+00:00', $record->createdAt());
        $this->assertSame('2024-07-01T00:00:00+00:00', $record->updatedAt());
        $this->assertNull($record->revokedReason());
        $this->assertTrue($record->isUsableAt(self::NOW));
    }

    #[DataProvider('unusableRows')]
    public function testDatabaseRegistryRejectsUnusablePersistedRecords(array $row): void
    {
        $this->assertNull($this->registry([$row])->find('acme-prod-agent'));
    }

    public static function unusableRows(): array
    {
        $row = [
            'client_id' => 'acme-prod-agent',
            'tenant_id' => 'acme',
            'oms_base_url' => 'https://oms.acme.internal',
            'oms_subject_id' => 42,
            'oms_role_ids' => [7],
            'oms_permissions' => ['orders.read'],
            'scopes' => ['oms.read.organizations'],
            'enabled' => 1,
            'revoked_at' => null,
            'expires_at' => self::NOW + 3600,
        ];

        return [
            'unknown client' => [[]],
            'disabled' => [array_merge($row, ['enabled' => 0])],
            'revoked' => [array_merge($row, ['revoked_at' => self::NOW - 1])],
            'expired' => [array_merge($row, ['expires_at' => self::NOW])],
            'malformed endpoint' => [array_merge($row, ['oms_base_url' => 'not-an-endpoint'])],
            'invalid principal' => [array_merge($row, ['oms_subject_id' => 999])],
        ];
    }

    public function testDatabaseRegistryRejectsDuplicateClientIdRows(): void
    {
        $this->assertNull($this->registry([$this->row(), $this->row()])->find('acme-prod-agent'));
    }

    public function testClientSchemaContainsAuthorizationAndAuditFieldsButNoRawSecret(): void
    {
        $schema = file_get_contents('app/mcpapi/dbschema/client.php');

        $this->assertIsString($schema);
        foreach ([
            'client_id',
            'tenant_id',
            'oms_base_url',
            'oms_subject_id',
            'oms_role_ids',
            'oms_permissions',
            'scopes',
            'enabled',
            'revoked_at',
            'expires_at',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
            'revoked_reason',
        ] as $field) {
            $this->assertStringContainsString("'{$field}'", $schema);
        }
        $this->assertStringContainsString("'client_secret_hash'", $schema);
        $this->assertStringNotContainsString("'client_secret'", $schema);
        $this->assertStringNotContainsString('private_key', $schema);
        $this->assertStringNotContainsString('raw_secret', $schema);
    }

    public function testExchangeUsesOnlyRegistryAuthorizationBinding(): void
    {
        $issuer = new mcpapi_auth_token_issuer(static fn(): string => 'test-only-key', 'test-issuer', 60);
        $verifier = new mcpapi_auth_token_verifier(static fn(): string => 'test-only-key', 'test-issuer');
        $exchange = new mcpapi_auth_exchange($this->recordRegistry([
            new mcpapi_auth_client_record(
                'acme-prod-agent',
                'acme',
                'https://oms.acme.internal',
                42,
                [7, 9],
                ['orders.read'],
                ['oms.read.organizations'],
                true,
                null,
                self::NOW + 3600
            ),
        ]), $issuer);

        $claims = $verifier->verify(
            $exchange->issue(new mcpapi_gateway_identity('acme-prod-agent', 'external-subject'), self::NOW),
            self::NOW + 1
        );

        $this->assertSame('acme-prod-agent', $claims['client_id']);
        $this->assertSame('acme', $claims['tenant_id']);
        $this->assertSame('https://oms.acme.internal', $claims['oms_base_url']);
        $this->assertSame(42, $claims['oms_subject_id']);
        $this->assertSame([7, 9], $claims['oms_role_ids']);
        $this->assertSame(['orders.read'], $claims['oms_permissions']);
        $this->assertSame(['oms.read.organizations'], $claims['scope']);
        $this->assertSame('external-subject', $claims['external_subject']);
        $this->assertArrayNotHasKey('sub', $claims);
    }

    #[DataProvider('missingExchangeClaims')]
    public function testVerifierRejectsMissingRegistryAuthorizationClaim(string $missingClaim): void
    {
        $issuer = new mcpapi_auth_token_issuer(static fn(): string => 'test-only-key', 'test-issuer', 60);
        $claims = [
            'client_id' => 'client-123',
            'tenant_id' => 'tenant-123',
            'oms_base_url' => 'https://oms.example.internal',
            'oms_subject_id' => 42,
            'oms_role_ids' => [7],
            'oms_permissions' => ['orders.read'],
            'scope' => ['orders:read'],
            'external_subject' => 'external-subject',
        ];
        unset($claims[$missingClaim]);

        $this->expectException(mcpapi_auth_exception::class);
        (new mcpapi_auth_token_verifier(static fn(): string => 'test-only-key', 'test-issuer'))
            ->verify($issuer->issue($claims, self::NOW), self::NOW);
    }

    public static function missingExchangeClaims(): array
    {
        return array_map(static fn(string $claim): array => [$claim], [
            'client_id',
            'tenant_id',
            'oms_base_url',
             'oms_subject_id',
             'oms_role_ids',
             'oms_permissions',
             'scope',
        ]);
    }

    public function testGuardContextUsesRegistryAuthorizationAndKeepsExternalSubjectForAudit(): void
    {
        $issuer = new mcpapi_auth_token_issuer(static fn(): string => 'test-only-key', 'test-issuer', 60);
        $verifier = new mcpapi_auth_token_verifier(static fn(): string => 'test-only-key', 'test-issuer');
        $now = time();
        $exchange = new mcpapi_auth_exchange($this->recordRegistry([
            new mcpapi_auth_client_record(
                'client-123',
                'tenant-123',
                'https://oms.example.internal',
                42,
                [7, 9],
                ['orders.read'],
                ['orders:read'],
                true,
                null,
                $now + 3600
            ),
        ]), $issuer);
        $token = $exchange->issue(new mcpapi_gateway_identity('client-123', 'external-subject'), $now);

        $context = (new mcpapi_auth_guard(
            static fn(string $value): array => $verifier->verify($value, $now)
        ))->authenticate('Bearer ' . $token, 'request-123');

        $this->assertSame('tenant-123', $context->tenantId());
        $this->assertSame('https://oms.example.internal', $context->omsBaseUrl());
        $this->assertSame(42, $context->omsSubjectId());
        $this->assertSame([7, 9], $context->omsRoleIds());
        $this->assertSame(['orders.read'], $context->omsPermissions());
        $this->assertSame(['orders:read'], $context->scopes());
        $this->assertSame('external-subject', $context->externalSubject());
    }

    private function registry(array $rows): mcpapi_auth_client_database
    {
        return new mcpapi_auth_client_database(
            static fn(string $clientId): array => array_values(array_filter(
                $rows,
                static fn(array $row): bool => ($row['client_id'] ?? null) === $clientId
            )),
            static fn(int $subjectId): bool => $subjectId === 42,
            self::NOW
        );
    }

    private function recordRegistry(array $records): mcpapi_auth_client_registry
    {
        return new class($records) implements mcpapi_auth_client_registry {
            /** @var array<string, mcpapi_auth_client_record> */
            private array $records;

            /** @param list<mcpapi_auth_client_record> $records */
            public function __construct(array $records)
            {
                $this->records = [];
                foreach ($records as $record) {
                    $this->records[$record->clientId()] = $record;
                }
            }

            public function find(string $clientId): ?mcpapi_auth_client_record
            {
                return $this->records[$clientId] ?? null;
            }
        };
    }

    private function row(): array
    {
        return [
            'client_id' => 'acme-prod-agent',
            'tenant_id' => 'acme',
            'oms_base_url' => 'https://oms.acme.internal/',
            'oms_subject_id' => 42,
            'oms_role_ids' => [7, 9],
            'oms_permissions' => ['orders.read'],
            'scopes' => ['oms.read.organizations'],
            'enabled' => 1,
            'revoked_at' => null,
            'expires_at' => self::NOW + 3600,
            'created_at' => '2024-07-01T00:00:00+00:00',
            'updated_at' => '2024-07-01T00:00:00+00:00',
            'created_by' => 'system',
            'updated_by' => 'system',
            'revoked_reason' => null,
        ];
    }
}
