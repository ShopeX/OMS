<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * CSRF Protection — Double-Submit Cookie Pattern
 *
 * Cookie name: oms-csrf-token (matches desktop_auth_jwt_transport::CSRF_COOKIE)
 * Storage key : _oms_csrf_key_ (server-side token store, single-use per rotation)
 *
 * Design:
 *   - Token is generated fresh on login / every successful verification (rotation).
 *   - Cookie is non-HttpOnly so JS can read it for X-CSRF-Token header.
 *   - JWT tokens remain HttpOnly and are NOT exposed to JS.
 *   - verify() performs double-submit against the server-side registry: the header must be
 *     a live token in the rolling window (TOKEN_MAX). It deliberately does NOT require
 *     header === cookie, because concurrent writes and JWT refresh rotate the cookie
 *     mid-flight and strict equality would 403 those in-flight requests (csrf_failed).
 *   - Token list uses cachecore when enabled; falls back to PHP session in local dev.
 */
class desktop_csrf
{
    /** Cookie name — must match desktop_auth_jwt_transport::CSRF_COOKIE */
    private const COOKIE_NAME = 'oms-csrf-token';

    /** Server-side token registry key */
    private const TOKEN_STORE_KEY = '_oms_csrf_key_';

    /** Maximum tokens to keep in rotation window */
    private const TOKEN_MAX = 10;

    /** Session fallback when cachecore is disabled (common in local dev) */
    private const SESSION_TOKEN_KEY = '_oms_csrf_key_';

    /**
     * __construct
     * @param mixed $app app
     * @return mixed 返回值
     */
    public function __construct($app)
    {
    }

    /**
     * @return array<int,string>
     */
    private function loadTokenList(): array
    {
        if (cachecore::enable()) {
            $tokens = cachecore::fetch(self::TOKEN_STORE_KEY);
            return is_array($tokens) ? $tokens : [];
        }

        return is_array($_SESSION[self::SESSION_TOKEN_KEY] ?? null)
            ? $_SESSION[self::SESSION_TOKEN_KEY]
            : [];
    }

    /**
     * @param array<int,string> $tokens
     * @return void
     */
    private function saveTokenList(array $tokens): void
    {
        if (cachecore::enable()) {
            cachecore::store(self::TOKEN_STORE_KEY, $tokens, 3600);
            return;
        }

        $_SESSION[self::SESSION_TOKEN_KEY] = $tokens;
    }

    /**
     * @param array<int,string> $tokens
     * @param string            $token
     * @return array<int,string>
     */
    private function pushToken(array $tokens, string $token): array
    {
        array_unshift($tokens, $token);
        while (count($tokens) > self::TOKEN_MAX) {
            array_pop($tokens);
        }

        return $tokens;
    }

    /**
     * Generate a new CSRF token, store it server-side, and set the cookie.
     *
     * Called on login success and on every successful verify (token rotation).
     *
     * @param int|null $expiresAt Unix expiry for CSRF cookie; defaults to JWT refresh TTL
     * @return string  the raw token value
     */
    public function set_token(?int $expiresAt = null): string
    {
        $token = bin2hex(random_bytes(32));
        $this->saveTokenList($this->pushToken($this->loadTokenList(), $token));
        desktop_auth_jwt_transport::setCsrfCookie($token, $expiresAt);

        return $token;
    }

    /**
     * Read the current CSRF token from cookie.
     *
     * @return string
     */
    public function get_token(): string
    {
        return $_COOKIE[self::COOKIE_NAME] ?? '';
    }

    /**
     * Strict double-submit verify: header value MUST match cookie value,
     * and cookie value MUST be in the server-side valid token list.
     *
     * On success: rotates token (issues new one, invalidates old).
     * On failure: does NOT rotate — caller decides next action.
     *
     * @param string|null $headerToken  defaults to HTTP_X_CSRF_TOKEN
     * @param string|null $cookieToken  defaults to COOKIE_NAME cookie
     * @return bool  true if valid, false if invalid
     */
    public function verify(?string $headerToken = null, ?string $cookieToken = null): bool
    {
        $headerToken = $headerToken ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $cookieToken = $cookieToken ?? ($_COOKIE[self::COOKIE_NAME] ?? '');

        if ($headerToken === '' || $cookieToken === '') {
            return false;
        }

        $arrTokenKey = $this->loadTokenList();
        // Double-submit: header 必须命中服务端滚动注册表（TOKEN_MAX 窗口内的 token 都有效）。
        // 不再要求 header === cookie —— 并发写请求与 JWT refresh 都会轮换 cookie，
        // 严格相等会把「读了 token 之后 cookie 被轮换」的在飞请求打成 403 csrf_failed。
        if (!in_array($headerToken, $arrTokenKey, true)) {
            // 注册表未命中（如 cache 失效）但 header/cookie 自洽 → 保留原补种逻辑，免整站重登
            if ($headerToken !== $cookieToken) {
                return false;
            }
            $this->saveTokenList($this->pushToken($arrTokenKey, $cookieToken));
        }

        $this->set_token();
        return true;
    }
}
