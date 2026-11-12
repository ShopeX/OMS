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

/**
 * Safe authentication observability helpers.
 *
 * Authentication log context must never contain tokens, raw cookie headers,
 * JWT payloads, or any other credential-bearing values. This helper builds
 * a sanitised context array from arbitrary input by:
 *
 *   - keeping only the keys in the allowlist
 *   - recursively scrubbing nested arrays
 *   - stripping strings that match a JWT shape
 *   - recursing into map values but truncating strings longer than a safe cap
 */
class desktop_auth_observability
{
    public const ALLOWED_KEYS = [
        'event',
        'request_id',
        'client_mode',
        'endpoint',
        'user_id',
        'reason',
        'token_type',
        'token_version',
        'db_token_version',
        'cookie_names_present',
    ];

    private const MAX_STRING_LENGTH = 200;

    /**
     * Build a sanitised observability context.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public static function context(array $raw): array
    {
        $context = [];
        foreach (self::ALLOWED_KEYS as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            $value = $raw[$key];
            $cleaned = self::cleanValue($value);
            if ($cleaned === '__REDACTED__' || $cleaned === null) {
                continue;
            }
            $context[$key] = $cleaned;
        }
        return $context;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function cleanValue($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $cleaned = self::cleanValue($v);
                if ($cleaned === '__REDACTED__' || $cleaned === null) {
                    continue;
                }
                $out[$k] = $cleaned;
            }
            return $out;
        }

        if (!is_string($value)) {
            return $value;
        }

        if (self::looksLikeJwt($value)) {
            return null;
        }

        if (self::looksLikeCookieHeader($value)) {
            return null;
        }

        if (mb_strlen($value) > self::MAX_STRING_LENGTH) {
            return mb_substr($value, 0, self::MAX_STRING_LENGTH) . '…';
        }

        return $value;
    }

    private static function looksLikeJwt(string $value): bool
    {
        $trimmed = trim($value);
        $segments = explode('.', $trimmed);
        if (count($segments) !== 3) {
            return false;
        }
        foreach ($segments as $segment) {
            if ($segment === '' || !preg_match('~^[A-Za-z0-9_\-=+]+$~', $segment)) {
                return false;
            }
        }
        return true;
    }

    private static function looksLikeCookieHeader(string $value): bool
    {
        return str_contains($value, 'oms-token-key=')
            || str_contains($value, 'oms-refresh-token-key=')
            || str_contains($value, 'oms-csrf-token=')
            || str_contains($value, 'wap-token-key=')
            || str_contains($value, 'jwt=');
    }
}
