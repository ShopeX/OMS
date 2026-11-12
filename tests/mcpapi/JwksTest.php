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

final class JwksTest extends TestCase
{
    private string $cwdBeforeTest;
    private string $privateKey;
    private string $publicKey;

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

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $privateKey = '';
        openssl_pkey_export($key, $privateKey);
        $this->privateKey = $privateKey;
        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        $this->publicKey = $details['key'];
    }

    protected function tearDown(): void
    {
        chdir($this->cwdBeforeTest);
        parent::tearDown();
    }

    public function testJwksSerializesOnlyActivePublicKeys(): void
    {
        $service = new mcpapi_auth_key_jwks(fn(): array => [
            $this->row('client-a', 'key-a', $this->publicKey, 'active'),
            $this->row('client-a', 'key-revoked', $this->publicKey, 'revoked', time() - 1),
        ]);

        $keys = $service->publicKeys();

        $this->assertCount(1, $keys);
        $this->assertSame('key-a', $keys[0]['kid']);
        $this->assertSame('RSA', $keys[0]['kty']);
        $this->assertSame('RS256', $keys[0]['alg']);
        $this->assertArrayNotHasKey('private_key', $keys[0]);
        $this->assertArrayNotHasKey('d', $keys[0]);
    }

    public function testPerClientVerifierUsesMatchingKidAndRejectsRevokedOrUnknownKeys(): void
    {
        $service = new mcpapi_auth_key_jwks(fn(string $clientId, string $kid): ?array => match ($clientId . ':' . $kid) {
            'client-a:key-a' => $this->row('client-a', 'key-a', $this->publicKey, 'active'),
            'client-a:key-revoked' => $this->row('client-a', 'key-revoked', $this->publicKey, 'revoked', time() - 1),
            default => null,
        });
        $verifier = new mcpapi_auth_token_verifier(null, 'oms-mcp-client', 'oms-mcp-gateway');
        $token = $this->jwt('client-a', 'key-a', 'RS256');

        $claims = $verifier->verifyClientWithJwks($token, $service, time());

        $this->assertSame('client-a', $claims['client_id']);
        $this->expectException(mcpapi_auth_exception::class);
        $verifier->verifyClientWithJwks($this->jwt('client-a', 'unknown', 'RS256'), $service, time());
    }

    public function testUnsupportedOrRevokedClientKeyFailsClosed(): void
    {
        $service = new mcpapi_auth_key_jwks(fn(string $clientId, string $kid): ?array => $this->row(
            $clientId,
            $kid,
            $this->publicKey,
            'revoked',
            time() - 1
        ));
        $verifier = new mcpapi_auth_token_verifier(null, 'oms-mcp-client', 'oms-mcp-gateway');

        $this->expectException(mcpapi_auth_exception::class);
        $verifier->verifyClientWithJwks($this->jwt('client-a', 'key-a', 'HS256'), $service, time());
    }

    public function testRevokedAsymmetricClientKeyFailsClosed(): void
    {
        $service = new mcpapi_auth_key_jwks(fn(string $clientId, string $kid): ?array => $this->row(
            $clientId,
            $kid,
            $this->publicKey,
            'revoked',
            time() - 1
        ));
        $verifier = new mcpapi_auth_token_verifier(null, 'oms-mcp-client', 'oms-mcp-gateway');

        $this->expectException(mcpapi_auth_exception::class);
        $verifier->verifyClientWithJwks($this->jwt('client-a', 'key-a', 'RS256'), $service, time());
    }

    public function testRouterDoesNotExposeJwks(): void
    {
        $jwks = new mcpapi_auth_key_jwks(function (?string $clientId = null, ?string $kid = null): ?array {
            $row = $this->row($clientId ?? 'client-a', $kid ?? 'key-a', $this->publicKey, 'active');
            return $clientId === null ? [$row] : ($clientId === 'client-a' && $kid === 'key-a' ? $row : null);
        });
        $guard = new mcpapi_auth_guard(
            static function (): array {
                throw new mcpapi_auth_exception();
            },
            null,
            $jwks
        );
        $router = new mcpapi_router($guard, null, null, new mcpapi_ctl_v1_jwks($jwks));

        $response = $router->dispatch('GET', '/mcp-api/v1/jwks', '', 'request-123');

        $this->assertSame(404, $response->status());
    }

    public function testRouterDoesNotExposeInternalExchange(): void
    {
        $jwks = new mcpapi_auth_key_jwks(function (?string $clientId = null, ?string $kid = null): ?array {
            $row = $this->row($clientId ?? 'client-a', $kid ?? 'key-a', $this->publicKey, 'active');
            return $clientId === null ? [$row] : ($clientId === 'client-a' && $kid === 'key-a' ? $row : null);
        });
        $registry = new class implements mcpapi_auth_client_registry {
            public function find(string $clientId): ?mcpapi_auth_client_record
            {
                return $clientId === 'client-a'
                    ? new mcpapi_auth_client_record('client-a', 'tenant-a', 'https://oms.example.internal', 42, [7], ['orders.read'], ['orders:read'], true, null, time() + 1000)
                    : null;
            }
        };
        $router = new mcpapi_router(
            new mcpapi_auth_guard(static function (): array {
                throw new mcpapi_auth_exception();
            }, null, $jwks),
            null,
            new mcpapi_ctl_v1_internal_exchange(new mcpapi_auth_exchange(
                $registry,
                new mcpapi_auth_token_issuer(static fn(): string => 'test-only-key', 'test-issuer', 60)
            ))
        );

        $response = $router->dispatch('POST', '/mcp-api/v1/internal/exchange', 'Bearer ' . $this->jwt('client-a', 'key-a', 'RS256'), 'request-123');
        $this->assertSame(404, $response->status());
    }

    private function row(string $clientId, string $kid, string $publicKey, string $status, ?int $revokedAt = null): array
    {
        return [
            'client_id' => $clientId,
            'kid' => $kid,
            'algorithm' => 'RS256',
            'public_key' => $publicKey,
            'status' => $status,
            'revoked_at' => $revokedAt,
        ];
    }

    private function jwt(string $clientId, string $kid, string $algorithm): string
    {
        $claims = [
            'iss' => 'oms-mcp-client',
            'aud' => 'oms-mcp-gateway',
            'iat' => time() - 1,
            'exp' => time() + 60,
            'jti' => 'assertion-123',
            'client_id' => $clientId,
            'sub' => 'gateway-subject',
        ];
        $encode = static fn(array $value): string => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $header = $encode(['typ' => 'JWT', 'alg' => $algorithm, 'kid' => $kid]);
        $payload = $encode($claims);
        $input = $header . '.' . $payload;
        $signature = '';
        openssl_sign($input, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        return $input . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
