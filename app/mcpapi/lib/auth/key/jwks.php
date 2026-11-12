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

final class mcpapi_auth_key_jwks
{
    private const ALGORITHMS = [
        'RS256' => OPENSSL_ALGO_SHA256,
        'RS384' => OPENSSL_ALGO_SHA384,
        'RS512' => OPENSSL_ALGO_SHA512,
        'ES256' => OPENSSL_ALGO_SHA256,
        'ES384' => OPENSSL_ALGO_SHA384,
        'ES512' => OPENSSL_ALGO_SHA512,
    ];

    private $rowLoader;
    private int $now;

    public function __construct(?callable $rowLoader = null, ?int $now = null)
    {
        $this->rowLoader = $rowLoader ?? static function (?string $clientId = null, ?string $kid = null): array {
            $filter = [];
            if ($clientId !== null) {
                $filter['client_id'] = $clientId;
            }
            if ($kid !== null) {
                $filter['kid'] = $kid;
            }
            return app::get('mcpapi')->model('client_key')->getList('*', $filter, 0, $clientId === null ? -1 : 2);
        };
        $this->now = $now ?? time();
    }

    public function publicKeys(?int $now = null): array
    {
        $keys = [];
        foreach ($this->rows() as $row) {
            if (!$this->isUsable($row, $now ?? $this->now)) {
                continue;
            }
            $jwk = $this->jwk($row);
            if ($jwk !== null) {
                $keys[] = $jwk;
            }
        }
        return $keys;
    }

    public function verifySignature(string $token, ?int $now = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new mcpapi_auth_exception();
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->decode($encodedHeader);
        $claims = $this->decode($encodedPayload);
        $algorithm = $header['alg'] ?? null;
        $clientId = $claims['client_id'] ?? null;
        $kid = $header['kid'] ?? null;
        if (!is_string($algorithm) || !isset(self::ALGORITHMS[$algorithm])
            || ($header['typ'] ?? null) !== 'JWT'
            || !is_string($clientId) || $clientId === ''
            || !is_string($kid) || $kid === '') {
            throw new mcpapi_auth_exception();
        }

        $row = $this->find($clientId, $kid);
        if ($row === null || !$this->isUsable($row, $now ?? $this->now)
            || ($row['algorithm'] ?? null) !== $algorithm) {
            throw new mcpapi_auth_exception();
        }
        $jwk = $this->jwk($row);
        $publicKey = $this->publicKey($row['public_key'] ?? null);
        $signature = $this->base64urlDecode($encodedSignature);
        if ($jwk === null || $publicKey === false || $signature === false) {
            throw new mcpapi_auth_exception();
        }
        if (str_starts_with($algorithm, 'ES')) {
            $signature = $this->ecdsaDerSignature($signature, $algorithm);
            if ($signature === false) {
                throw new mcpapi_auth_exception();
            }
        }
        $verified = openssl_verify($encodedHeader . '.' . $encodedPayload, $signature, $publicKey, self::ALGORITHMS[$algorithm]);
        if ($verified !== 1) {
            throw new mcpapi_auth_exception();
        }
        return $claims;
    }

    private function rows(): array
    {
        $rows = ($this->rowLoader)();
        return is_array($rows) ? $rows : [];
    }

    private function find(string $clientId, string $kid): ?array
    {
        $loader = $this->rowLoader;
        $rows = (new ReflectionFunction(Closure::fromCallable($loader)))->getNumberOfParameters() >= 2
            ? $loader($clientId, $kid)
            : $this->rows();
        if (is_array($rows) && isset($rows['client_id'])) {
            $rows = [$rows];
        }
        if (!is_array($rows)) {
            return null;
        }
        foreach ($rows as $row) {
            if (is_array($row) && ($row['client_id'] ?? null) === $clientId && ($row['kid'] ?? null) === $kid) {
                return $row;
            }
        }
        return null;
    }

    private function isUsable(array $row, int $now): bool
    {
        if (($row['status'] ?? null) !== 'active' || !is_string($row['client_id'] ?? null)
            || $row['client_id'] === '' || !is_string($row['kid'] ?? null) || $row['kid'] === ''
            || !is_string($row['algorithm'] ?? null) || !isset(self::ALGORITHMS[$row['algorithm']])) {
            return false;
        }
        $revokedAt = $row['revoked_at'] ?? null;
        return $revokedAt === null || $this->timestamp($revokedAt) > $now;
    }

    private function jwk(array $row): ?array
    {
        $publicKey = $this->publicKey($row['public_key'] ?? null);
        if ($publicKey === false) {
            return null;
        }
        $details = openssl_pkey_get_details($publicKey);
        if (!is_array($details) || !isset($details['key'])) {
            return null;
        }
        $algorithm = $row['algorithm'];
        if (isset($details['rsa']) && is_array($details['rsa'])) {
            if (!isset($details['rsa']['n'], $details['rsa']['e'])) {
                return null;
            }
            return [
                'kty' => 'RSA',
                'kid' => $row['kid'],
                'alg' => $algorithm,
                'use' => 'sig',
                'n' => $this->base64urlEncode($details['rsa']['n']),
                'e' => $this->base64urlEncode($details['rsa']['e']),
            ];
        }
        if (isset($details['ec']) && is_array($details['ec'])) {
            $curve = ['prime256v1' => 'P-256', 'secp384r1' => 'P-384', 'secp521r1' => 'P-521'][$details['ec']['curve_name'] ?? ''] ?? null;
            if ($curve === null || !isset($details['ec']['x'], $details['ec']['y'])) {
                return null;
            }
            return [
                'kty' => 'EC',
                'kid' => $row['kid'],
                'alg' => $algorithm,
                'use' => 'sig',
                'crv' => $curve,
                'x' => $this->base64urlEncode($details['ec']['x']),
                'y' => $this->base64urlEncode($details['ec']['y']),
            ];
        }
        return null;
    }

    private function publicKey($value)
    {
        if (!is_string($value) || $value === '') {
            return false;
        }
        return openssl_pkey_get_public($value);
    }

    private function decode(string $value): array
    {
        $decoded = $this->base64urlDecode($value);
        $json = $decoded === false ? null : json_decode($decoded, true);
        if (!is_array($json)) {
            throw new mcpapi_auth_exception();
        }
        return $json;
    }

    private function base64urlDecode(string $value)
    {
        return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }

    private function base64urlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function timestamp($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/', $value)) {
            return (int) $value;
        }
        $parsed = is_string($value) ? strtotime($value) : false;
        return $parsed === false ? null : $parsed;
    }

    private function ecdsaDerSignature(string $signature, string $algorithm)
    {
        $length = ['ES256' => 32, 'ES384' => 48, 'ES512' => 66][$algorithm];
        if (strlen($signature) !== $length * 2) {
            return false;
        }
        $r = ltrim(substr($signature, 0, $length), "\x00");
        $s = ltrim(substr($signature, $length), "\x00");
        $r = ($r === '' ? "\x00" : $r);
        $s = ($s === '' ? "\x00" : $s);
        if (ord($r[0]) & 0x80) {
            $r = "\x00" . $r;
        }
        if (ord($s[0]) & 0x80) {
            $s = "\x00" . $s;
        }
        $body = "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s;
        return "\x30" . chr(strlen($body)) . $body;
    }
}
