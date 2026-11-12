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

final class mcpapi_auth_token_verifier
{
    private $keySource;
    private string $issuer;
    private array $audiences;

    public function __construct(
        ?callable $keySource = null,
        string $issuer = 'mcpapi',
        $audience = 'mcp-api',
        string $keyEnvironment = 'MCPAPI_TRANSITIONAL_HS256_KEY'
    )
    {
        $this->keySource = $keySource ?? static function () use ($keyEnvironment): string {
            $key = getenv($keyEnvironment);
            if (!is_string($key) || $key === '') {
                throw new mcpapi_auth_exception($keyEnvironment . ' is not configured', 500);
            }
            return $key;
        };
        $this->issuer = $issuer;
        $this->audiences = is_array($audience) ? array_values($audience) : [$audience];
    }

    public function verify(string $token, ?int $now = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new mcpapi_auth_exception();
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decode($encodedHeader);
        $claims = $this->decode($encodedPayload);
        if (($header['alg'] ?? null) !== 'HS256' || ($header['typ'] ?? null) !== 'JWT') {
            throw new mcpapi_auth_exception();
        }
        $expected = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, ($this->keySource)(), true);
        $actual = base64_decode(strtr($encodedSignature, '-_', '+/') . str_repeat('=', (4 - strlen($encodedSignature) % 4) % 4), true);
        if (!is_string($actual) || !hash_equals($expected, $actual)) {
            throw new mcpapi_auth_exception();
        }
        if (!is_string($claims['aud'] ?? null) || !in_array($claims['aud'], $this->audiences, true)
            || $claims['aud'] !== 'mcp-api' || ($claims['iss'] ?? null) !== $this->issuer) {
            throw new mcpapi_auth_exception();
        }
        if (!isset($claims['exp']) || !is_int($claims['exp']) || $claims['exp'] <= ($now ?? time())) {
            throw new mcpapi_auth_exception();
        }
        foreach (['client_id', 'jti'] as $name) {
            if (!is_string($claims[$name] ?? null) || $claims[$name] === '') {
                throw new mcpapi_auth_exception();
            }
        }
        if ($claims['aud'] === 'mcp-api') {
            if (!is_string($claims['tenant_id'] ?? null) || $claims['tenant_id'] === ''
                || !is_string($claims['oms_base_url'] ?? null) || $claims['oms_base_url'] === ''
                 || !is_int($claims['oms_subject_id'] ?? null) || $claims['oms_subject_id'] < 1
                 || !is_array($claims['oms_role_ids'] ?? null)
                 || !$this->validStrings($claims['oms_permissions'] ?? null)
                 || !is_array($claims['scope'] ?? null)
                || (isset($claims['external_subject'])
                    && (!is_string($claims['external_subject']) || $claims['external_subject'] === ''))) {
                throw new mcpapi_auth_exception();
            }
        }
        return $claims;
    }

    private function validStrings($value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                return false;
            }
        }
        return true;
    }

    public function verifyClient(string $token, ?int $now = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new mcpapi_auth_exception();
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decode($encodedHeader);
        $claims = $this->decode($encodedPayload);
        if (($header['alg'] ?? null) !== 'HS256' || ($header['typ'] ?? null) !== 'JWT') {
            throw new mcpapi_auth_exception();
        }
        $expected = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, ($this->keySource)(), true);
        $actual = base64_decode(strtr($encodedSignature, '-_', '+/') . str_repeat('=', (4 - strlen($encodedSignature) % 4) % 4), true);
        if (!is_string($actual) || !hash_equals($expected, $actual)) {
            throw new mcpapi_auth_exception();
        }
        return $this->validateClientClaims($claims, $now ?? time());
    }

    public function verifyClientWithJwks(string $token, mcpapi_auth_key_jwks $jwks, ?int $now = null): array
    {
        $claims = $jwks->verifySignature($token, $now);
        return $this->validateClientClaims($claims, $now ?? time());
    }

    private function validateClientClaims(array $claims, int $currentTime): array
    {
        if (($claims['iss'] ?? null) !== $this->issuer
            || ($claims['aud'] ?? null) !== ($this->audiences[0] ?? null)
            || !is_int($claims['iat'] ?? null)
            || !is_int($claims['exp'] ?? null)
            || $claims['iat'] > $currentTime
            || $claims['exp'] <= $currentTime) {
            throw new mcpapi_auth_exception();
        }
        foreach (['jti', 'client_id', 'sub'] as $name) {
            if (!is_string($claims[$name] ?? null) || $claims[$name] === '') {
                throw new mcpapi_auth_exception();
            }
        }
        return $claims;
    }

    private function decode(string $value): array
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if (!is_string($decoded)) {
            throw new mcpapi_auth_exception();
        }
        $json = json_decode($decoded, true);
        if (!is_array($json)) {
            throw new mcpapi_auth_exception();
        }
        return $json;
    }
}
