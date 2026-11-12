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

/**
 * AS 签名密钥管理：RS256 密钥对生成、JWKS 发布、token 验签。
 *
 * 密钥落 as_key 表（kid 主键）。轮换 = 插入新 active kid，旧 kid 标记 retired；
 * 验签仍接受 retired kid（宽限期），签发只用 active。
 */
final class mcpapi_auth_oauth_askeys
{
    private const KEY_BITS = 2048;
    private const ALGORITHM = 'RS256';

    private $rowLoader;
    private $rowSaver;
    private int $now;

    public function __construct(?callable $rowLoader = null, ?callable $rowSaver = null, ?int $now = null)
    {
        $this->rowLoader = $rowLoader ?? static function (array $filter): array {
            return app::get('mcpapi')->model('as_key')->getList('*', $filter, 0, -1);
        };
        $this->rowSaver = $rowSaver ?? static function (array $row): void {
            app::get('mcpapi')->model('as_key')->insert($row);
        };
        $this->now = $now ?? time();
    }

    /**
     * 确保 AS 至少持有一把 active RS256 密钥；无则现场生成。
     *
     * @return array{kid:string, private_key:string, public_key:string} active 密钥行
     */
    public function ensureActiveKey(): array
    {
        $active = $this->activeRows();
        if ($active !== []) {
            return $active[0];
        }

        $kid = 'as_' . bin2hex(random_bytes(16));
        $config = [
            'private_key_bits' => self::KEY_BITS,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $resource = openssl_pkey_new($config);
        if ($resource === false) {
            throw new RuntimeException('Unable to generate AS signing key', 500);
        }
        $exported = openssl_pkey_export($resource, $privateKeyPem);
        if ($exported === false) {
            throw new RuntimeException('Unable to export AS signing key', 500);
        }
        $details = openssl_pkey_get_details($resource);
        if (!is_array($details) || !isset($details['key'])) {
            throw new RuntimeException('Unable to derive AS public key', 500);
        }

        $row = [
            'kid' => $kid,
            'algorithm' => self::ALGORITHM,
            'public_key' => $details['key'],
            'private_key' => $privateKeyPem,
            'status' => 'active',
            'created_at' => $this->now,
            'retired_at' => null,
        ];
        ($this->rowSaver)($row);
        return $row;
    }

    /**
     * JWKS 响应体：所有未退役密钥的公钥（active + retired 均发布，服务验签宽限）。
     * 首次访问惰性生成 active 密钥。
     */
    public function jwks(): array
    {
        $this->ensureActiveKey();
        $keys = [];
        foreach ($this->rows() as $row) {
            if (!$this->isUsable($row)) {
                continue;
            }
            $jwk = $this->rsaJwk($row);
            if ($jwk !== null) {
                $keys[] = $jwk;
            }
        }
        return ['keys' => $keys];
    }

    /**
     * RS256 签发 JWT。
     */
    public function sign(array $claims, array $header = []): string
    {
        $key = $this->ensureActiveKey();
        $privateKey = openssl_pkey_get_private($key['private_key']);
        if ($privateKey === false) {
            throw new RuntimeException('Invalid AS signing key', 500);
        }

        $header = array_merge(['typ' => 'JWT', 'alg' => self::ALGORITHM, 'kid' => $key['kid']], $header);
        $encodedHeader = $this->base64UrlEncode((string) json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode((string) json_encode($claims, JSON_THROW_ON_ERROR));
        $unsigned = $encodedHeader . '.' . $encodedPayload;

        $signed = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if ($signed === false) {
            throw new RuntimeException('Unable to sign token', 500);
        }

        return $unsigned . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * 验签 + 结构校验，返回 claims；任何失败抛 mcpapi_auth_exception。
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new mcpapi_auth_exception();
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeJson($encodedHeader);
        $claims = $this->decodeJson($encodedPayload);
        $algorithm = $header['alg'] ?? null;
        $kid = $header['kid'] ?? null;
        if ($algorithm !== self::ALGORITHM || ($header['typ'] ?? null) !== 'JWT'
            || !is_string($kid) || $kid === '') {
            throw new mcpapi_auth_exception();
        }

        $row = null;
        foreach ($this->rows() as $candidate) {
            if (($candidate['kid'] ?? null) === $kid) {
                $row = $candidate;
                break;
            }
        }
        if ($row === null || !$this->isUsable($row)) {
            throw new mcpapi_auth_exception();
        }

        $signature = $this->base64UrlDecode($encodedSignature);
        $publicKey = openssl_pkey_get_public($row['public_key']);
        if ($signature === false || $publicKey === false) {
            throw new mcpapi_auth_exception();
        }

        $verified = openssl_verify($encodedHeader . '.' . $encodedPayload, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new mcpapi_auth_exception();
        }

        return $claims;
    }

    /**
     * @return string[]
     */
    public function activeKids(): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) $row['kid'],
            $this->activeRows()
        ));
    }

    private function activeRows(): array
    {
        return array_values(array_filter(
            $this->rows(),
            static fn (array $row): bool => ($row['status'] ?? null) === 'active'
        ));
    }

    private function rows(): array
    {
        $rows = ($this->rowLoader)(['status|in' => ['active', 'retired']]);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private function isUsable(array $row): bool
    {
        return in_array($row['status'] ?? null, ['active', 'retired'], true)
            && is_string($row['kid'] ?? null) && $row['kid'] !== ''
            && is_string($row['public_key'] ?? null) && $row['public_key'] !== '';
    }

    private function rsaJwk(array $row): ?array
    {
        $publicKey = openssl_pkey_get_public($row['public_key']);
        if ($publicKey === false) {
            return null;
        }
        $details = openssl_pkey_get_details($publicKey);
        if (!is_array($details) || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            return null;
        }
        return [
            'kty' => 'RSA',
            'kid' => $row['kid'],
            'alg' => self::ALGORITHM,
            'use' => 'sig',
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ];
    }

    private function decodeJson(string $value): array
    {
        $decoded = $this->base64UrlDecode($value);
        $json = $decoded === false ? null : json_decode($decoded, true);
        if (!is_array($json)) {
            throw new mcpapi_auth_exception();
        }
        return $json;
    }

    private function base64UrlDecode(string $value)
    {
        return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
