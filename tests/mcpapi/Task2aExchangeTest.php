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

final class Task2aExchangeTest extends TestCase
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

    public function testActiveRegistryRecordDeterminesTenantAndScopes(): void
    {
        $registry = $this->registry([
            $this->record('client-123', 'tenant-from-registry', ['orders:read'], true, null, 2000),
        ]);
        $exchange = $this->exchange($registry);

        $token = $exchange->issue(new mcpapi_gateway_identity('client-123', 'gateway-subject'), 1000);
        $claims = $this->claimsFromToken($token);

        $this->assertSame('tenant-from-registry', $claims['tenant_id']);
        $this->assertSame(['orders.read'], $claims['oms_permissions']);
        $this->assertSame(['orders:read'], $claims['scope']);
    }

    #[DataProvider('unusableRegistryRecords')]
    public function testDisabledRevokedExpiredOrMismatchedRegistryRecordsAreRejected(
        string $recordClientId,
        string $tenantId,
        array $scopes,
        bool $enabled,
        ?int $revokedAt,
        int $expiresAt,
        string $gatewayClientId
    ): void
    {
        $this->expectException(mcpapi_auth_exception::class);
        $record = $this->record($recordClientId, $tenantId, $scopes, $enabled, $revokedAt, $expiresAt);
        $this->exchange($this->registry([$record]))
            ->issue(new mcpapi_gateway_identity($gatewayClientId, 'gateway-subject'), 1000);
    }

    public static function unusableRegistryRecords(): array
    {
        return [
            'disabled' => ['client-123', 'tenant-123', ['a'], false, null, 2000, 'client-123'],
            'revoked' => ['client-123', 'tenant-123', ['a'], true, 900, 2000, 'client-123'],
            'expired' => ['client-123', 'tenant-123', ['a'], true, null, 1000, 'client-123'],
            'mismatched' => ['registered-client', 'tenant-123', ['a'], true, null, 2000, 'gateway-client'],
        ];
    }

    public function testExchangeRejectsTenantScopeAndBearerStyleRequestInputs(): void
    {
        $exchange = $this->exchange($this->registry([
            $this->record('client-123', 'tenant-123', ['orders:read'], true, null, 2000),
        ]));
        $controller = new mcpapi_ctl_v1_internal_exchange($exchange);

        $this->expectException(mcpapi_auth_exception::class);
        $controller->handle(new mcpapi_gateway_identity('client-123', 'gateway-subject'), [
            'tenant_id' => 'attacker-tenant',
            'scope' => ['admin'],
            'authorization' => 'Bearer external-token',
        ]);
    }

    public function testIssuedTokenHasTransitionalClaimsAndGuardVerifiesIt(): void
    {
        $issuer = new mcpapi_auth_token_issuer(static fn(): string => 'test-only-key', 'test-issuer', 60);
        $verifier = new mcpapi_auth_token_verifier(static fn(): string => 'test-only-key', 'test-issuer');
        $exchange = new mcpapi_auth_exchange(
            $this->registry([
                $this->record('client-123', 'tenant-123', ['orders:read', 'orders:write'], true, null, time() + 1000),
            ]),
            $issuer
        );

        $now = time();
        $token = $exchange->issue(new mcpapi_gateway_identity('client-123', 'gateway-subject'), $now);
        $claims = $verifier->verify($token, $now);
        $context = (new mcpapi_auth_guard(static fn(string $value): array => $verifier->verify($value, $now)))
            ->authenticate('Bearer ' . $token, 'request-123');

        $this->assertSame('mcp-api', $claims['aud']);
        $this->assertSame('test-issuer', $claims['iss']);
        $this->assertSame('gateway-subject', $claims['external_subject']);
        $this->assertSame('client-123', $claims['client_id']);
        $this->assertSame('tenant-123', $claims['tenant_id']);
        $this->assertSame('https://oms.example.internal', $claims['oms_base_url']);
        $this->assertSame(42, $claims['oms_subject_id']);
        $this->assertSame([7, 9], $claims['oms_role_ids']);
        $this->assertSame(['orders.read'], $claims['oms_permissions']);
        $this->assertSame(['orders:read', 'orders:write'], $claims['scope']);
        $this->assertIsInt($claims['iat']);
        $this->assertIsInt($claims['exp']);
        $this->assertSame(60, $claims['exp'] - $claims['iat']);
        $this->assertIsString($claims['jti']);
        $this->assertNotSame('', $claims['jti']);
        $this->assertSame('tenant-123', $context->tenantId());
        $this->assertSame(['orders.read'], $context->omsPermissions());
        $this->assertSame(['orders:read', 'orders:write'], $context->scopes());
    }

    public function testAccessTokenJtiIsStableForRepeatedBearerVerification(): void
    {
        $issuer = new mcpapi_auth_token_issuer(static fn(): string => 'test-only-key', 'test-issuer', 60);
        $verifier = new mcpapi_auth_token_verifier(static fn(): string => 'test-only-key', 'test-issuer');
        $token = $issuer->issue([
            'external_subject' => 'external-subject',
            'client_id' => 'client-123',
            'tenant_id' => 'tenant-123',
            'oms_base_url' => 'https://oms.example.internal',
            'oms_subject_id' => 42,
            'oms_role_ids' => [7],
            'oms_permissions' => ['orders.read'],
            'scope' => ['orders:read'],
        ], 1000);

        $first = $verifier->verify($token, 1000);
        $second = $verifier->verify($token, 1000);

        $this->assertSame($first['jti'], $second['jti']);
    }

    public function testOriginalClientJwtIsVerifiedWithItsOwnIssuerAudienceAndKey(): void
    {
        $now = 1000;
        $token = $this->jwt([
            'iss' => 'oms-mcp-client',
            'aud' => 'oms-mcp-gateway',
            'iat' => $now,
            'exp' => $now + 60,
            'jti' => 'assertion-123',
            'client_id' => 'client-123',
            'sub' => 'gateway-subject',
        ], 'client-jwt-key');

        $verifier = new mcpapi_auth_token_verifier(
            static fn(): string => 'client-jwt-key',
            'oms-mcp-client',
            'oms-mcp-gateway',
            'OMS_MCP_CLIENT_JWT_KEY'
        );

        $claims = $verifier->verifyClient($token, $now);

        $this->assertSame('client-123', $claims['client_id']);
        $this->assertSame('gateway-subject', $claims['sub']);
    }

    public function testTransitionalInternalTokenIsRejectedByClientVerifier(): void
    {
        $token = $this->jwt([
            'iss' => 'mcpapi',
            'aud' => 'mcp-api-internal',
            'iat' => 1000,
            'exp' => 1060,
            'jti' => 'internal-123',
            'client_id' => 'client-123',
            'sub' => 'gateway-subject',
        ], 'transitional-key');
        $verifier = new mcpapi_auth_token_verifier(
            static fn(): string => 'client-jwt-key',
            'oms-mcp-client',
            'oms-mcp-gateway',
            'OMS_MCP_CLIENT_JWT_KEY'
        );

        $this->expectException(mcpapi_auth_exception::class);
        $verifier->verifyClient($token, 1000);
    }

    public function testDocumentationStatesAccessTokenJtiIsNotConsumedForReplayPrevention(): void
    {
        $documentation = file_get_contents('app/mcpapi/README.md');

        $this->assertIsString($documentation);
        $this->assertStringContainsString('`jti` is audit/revocation correlation only', $documentation);
        $this->assertStringContainsString('not consumed per request', $documentation);
        $this->assertStringContainsString('mTLS/DPoP', $documentation);
        $this->assertStringContainsString('assertion-jti replay protection', $documentation);
    }

    private function exchange($registry): mcpapi_auth_exchange
    {
        return new mcpapi_auth_exchange(
            $registry,
            new mcpapi_auth_token_issuer(static fn(): string => 'test-only-key', 'test-issuer', 60)
        );
    }

    private function record(
        string $clientId,
        string $tenantId,
        array $scopes,
        bool $enabled,
        ?int $revokedAt,
        int $expiresAt
    ): mcpapi_auth_client_record {
        return new mcpapi_auth_client_record(
            $clientId,
            $tenantId,
            'https://oms.example.internal',
            42,
            [7, 9],
            ['orders.read'],
            $scopes,
            $enabled,
            $revokedAt,
            $expiresAt
        );
    }

    private function claimsFromToken(string $token): array
    {
        [, $payload] = explode('.', $token);
        $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/') . str_repeat('=', (4 - strlen($payload) % 4) % 4)), true);
        $this->assertIsArray($decoded);
        return $decoded;
    }

    private function jwt(array $claims, string $key): string
    {
        $encode = static fn(array $value): string => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $header = $encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = $encode($claims);
        return $header . '.' . $payload . '.' . rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $payload, $key, true)), '+/', '-_'), '=');
    }

    private function registry(array $records)
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
}
