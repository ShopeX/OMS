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

final class mcpapi_auth_token_issuer
{
    private $keySource;
    private string $issuer;
    private int $lifetime;

    public function __construct(?callable $keySource = null, string $issuer = 'mcpapi', int $lifetime = 60)
    {
        $this->keySource = $keySource ?? static function (): string {
            $key = getenv('MCPAPI_TRANSITIONAL_HS256_KEY');
            if (!is_string($key) || $key === '') {
                throw new mcpapi_auth_exception('MCPAPI transitional signing key is not configured', 500);
            }
            return $key;
        };
        $this->issuer = $issuer;
        $this->lifetime = max(1, min($lifetime, 300));
    }

    public function issue(array $claims, ?int $now = null): string
    {
        $iat = $now ?? time();
        $payload = array_merge($claims, [
            'aud' => 'mcp-api',
            'iss' => $this->issuer,
            'iat' => $iat,
            'exp' => $iat + $this->lifetime,
            'jti' => bin2hex(random_bytes(16)),
        ]);
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $encodedHeader = $this->encode($header);
        $encodedPayload = $this->encode($payload);
        $unsigned = $encodedHeader . '.' . $encodedPayload;
        $signature = hash_hmac('sha256', $unsigned, ($this->keySource)(), true);
        return $unsigned . '.' . $this->base64UrlEncode($signature);
    }

    private function encode(array $value): string
    {
        return $this->base64UrlEncode(json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
