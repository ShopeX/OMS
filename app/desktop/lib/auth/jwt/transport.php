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
 * JWT Transport Layer - Cookie / Unauthorized Response
 *
 * Centralises Cookie token delivery, Cookie write/clear,
 * and 401 payload construction so the controller stays thin.
 *
 * Design contract:
 *   - Browser success  : write HttpOnly access/refresh Cookie; body has no token
 *   - WAP success      : write wap-token-key / wap-refresh-token-key HttpOnly Cookies
 *   - Unauthorized     : clean payload + optional cookie clearing
 */

class desktop_auth_jwt_transport
{
    /* --------------------------------------------------------------------
     * Cookie name constants (must match client.php / legacy code)
     * -------------------------------------------------------------------- */

    public const DESKTOP_ACCESS_COOKIE  = 'oms-token-key';
    public const DESKTOP_REFRESH_COOKIE = 'oms-refresh-token-key';
    public const WAP_ACCESS_COOKIE      = 'wap-token-key';
    public const WAP_REFRESH_COOKIE     = 'wap-refresh-token-key';
    public const CSRF_COOKIE            = 'oms-csrf-token';

    /** @var string[] Legacy desktop auth cookies that pre-date oms-token-key */
    public const LEGACY_DESKTOP_AUTH_COOKIES = [
        'jwt',
    ];

    /** @var string[] Desktop auth cookies cleared during browser logout and migration cleanup */
    public const DESKTOP_AUTH_COOKIES = [
        'jwt',
        self::DESKTOP_ACCESS_COOKIE,
        self::DESKTOP_REFRESH_COOKIE,
        self::CSRF_COOKIE,
        'pam_passport_basic_uname',
        's',
    ];

    /** @var string[] All cookie names cleared on logout */
    private const ALL_AUTH_COOKIES = [
        'jwt',
        self::DESKTOP_ACCESS_COOKIE,
        self::DESKTOP_REFRESH_COOKIE,
        self::WAP_ACCESS_COOKIE,
        self::WAP_REFRESH_COOKIE,
        self::CSRF_COOKIE,
        'pam_passport_basic_uname',
        's',
    ];

    /* --------------------------------------------------------------------
     * Internal state
     * -------------------------------------------------------------------- */

    /**
     * Cache for the resolved base URL keyed by scheme+host.
     * Multiple hosts (e.g. dev HMR vs prod) do not collide and tests
     * can flip HTTPS without leaking prior state.
     *
     * @var array<string,string>
     */
    private static array $baseUrlCache = [];

    /* --------------------------------------------------------------------
     * Payload builders (no side-effects)
     * -------------------------------------------------------------------- */

    /**
     * Browser login / refresh success body.
     *
     * Returns the JSON body for a successful browser login.
     * No token is included — the token lives in the HttpOnly Cookie only.
     *
     * @param desktop_auth_jwt_result $result
     * @return array JSON-serialisable body
     */
    public static function browserLoginPayload(desktop_auth_jwt_result $result): array
    {
        $payload = [
            'authenticated' => $result->isAuthenticated(),
            'status'        => $result->statusCode(),
            'reason'        => $result->reason(),
            'message'       => $result->message(),
        ];

        // Forward any non-token extra data (e.g. need_change_password, roles)
        $data = $result->data();
        if (!empty($data)) {
            // Strip any token keys that might have been accidentally included
            unset($data['token'], $data['refresh_token'], $data['access_token']);
            unset($data['login_name'], $data['name']);
            if (isset($data['user']) && is_array($data['user'])) {
                $data['user'] = desktop_auth_profile::forBrowserClient($data['user']);
            }
            // Flatten inner data into the top-level payload so the
            // client sees payload.user / payload.expires_in directly.
            // The `succ()` wrapper adds another {rsp:'succ', data:...} layer,
            // so we MUST NOT nest the inner data under another 'data' key.
            $payload = array_merge($payload, $data);
        }

        return $payload;
    }

    /**
     * Standard fail JSON body for Vue / legacy AJAX clients.
     *
     * Uses top-level code + reason so frontend service.ts 401 interceptor can read them.
     *
     * @param string $message
     * @param int    $code
     * @param string $reason
     * @param array  $extra
     * @return array
     */
    public static function failPayload(string $message, int $code = 400, string $reason = '', array $extra = []): array
    {
        unset($extra['location'], $extra['message'], $extra['code'], $extra['reason']);

        $payload = [
            'rsp'     => 'fail',
            'message' => $message,
            'code'    => $code,
        ];
        if ($reason !== '') {
            $payload['reason'] = $reason;
        }

        return array_merge($payload, $extra);
    }

    /**
     * 401 JSON body.
     *
     * @param string $message
     * @param string $reason
     * @param array  $extra
     * @return array
     */
    public static function unauthorizedPayload(string $message, string $reason = 'unauthorized', array $extra = []): array
    {
        $reason = (string) ($extra['reason'] ?? $reason);
        unset($extra['reason']);

        return self::failPayload($message, 401, $reason, $extra);
    }

    /**
     * 401 JSON body from JWT auth result.
     *
     * @param desktop_auth_jwt_result $result
     * @return array
     */
    public static function unauthorizedFromResult(desktop_auth_jwt_result $result): array
    {
        return self::unauthorizedPayload(
            $result->message() ?: '登录状态已过期，请重新登录',
            $result->reason() ?: 'session_expired'
        );
    }

    /**
     * @param int   $httpCode
     * @param array $payload
     * @return void
     */
    public static function respondJson(int $httpCode, array $payload): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @param string $message
     * @param array  $extra
     * @return void
     */
    public static function respondUnauthorized(string $message, array $extra = []): void
    {
        $reason = (string) ($extra['reason'] ?? 'unauthorized');
        self::respondJson(401, self::unauthorizedPayload($message, $reason, $extra));
    }

    /**
     * @param desktop_auth_jwt_result $result
     * @param bool                    $clearBrowserCookies
     * @return void
     */
    public static function respondUnauthorizedFromResult(
        desktop_auth_jwt_result $result,
        bool $clearBrowserCookies = false
    ): void {
        if ($clearBrowserCookies && $result->shouldClearCookies()) {
            self::clearBrowserAuthCookies();
        }
        self::respondJson(401, self::unauthorizedFromResult($result));
    }

    /**
     * @param string $reason  'csrf_failed' | 'origin_rejected'
     * @return void
     */
    public static function respondCsrfFailed(string $reason = 'csrf_failed'): void
    {
        self::respondJson(403, self::failPayload('CSRF 校验失败，请刷新页面后重试', 403, $reason));
    }

    /* --------------------------------------------------------------------
     * Cookie writers (side-effect: set HttpOnly cookies via setcookie())
     * -------------------------------------------------------------------- */

    /**
     * Write browser (desktop) HttpOnly auth cookies.
     *
     * @param array $tokens  desktop_auth_jwt::issueTokens() output
     * @return void
     */
    public static function setBrowserAuthCookies(array $tokens): void
    {
        // Always expire the legacy `jwt` access cookie before issuing the canonical pair,
        // so a stale cookie from older sessions cannot shadow `oms-token-key`.
        self::clearLegacyDesktopAuthCookies();

        $access  = $tokens['access']  ?? [];
        $refresh = $tokens['refresh'] ?? [];

        $accessToken  = $access['token']       ?? '';
        $refreshToken = $refresh['token']      ?? '';
        $accessExp    = (int)($access['expires_at'] ?? 0);
        $refreshExp   = (int)($refresh['expires_at'] ?? 0);

        self::setCookie(self::DESKTOP_ACCESS_COOKIE, $accessToken, $accessExp, self::cookieOptions($accessExp));
        self::setCookie(self::DESKTOP_REFRESH_COOKIE, $refreshToken, $refreshExp, self::cookieOptions($refreshExp));
    }

    /**
     * Write the non-HttpOnly CSRF cookie on browser login/refresh success.
     *
     * The token is readable by JS (for X-CSRF-Token double-submit) but NOT
     * the HttpOnly JWT cookies.  This method is idempotent — calling it multiple
     * times on the same request is safe (same token, same options).
     *
     * @param string      $csrfToken  raw token value from desktop_csrf::set_token()
     * @param int|null    $expiresAt  Unix expiry; defaults to JWT refresh TTL
     * @return void
     */
    public static function setCsrfCookie(string $csrfToken, ?int $expiresAt = null): void
    {
        if ($csrfToken === '') {
            return;
        }
        // CSRF cookie: NOT HttpOnly, SameSite=Lax; expiry decoupled from access token TTL —
        // bind to refresh TTL so a 1h access expiry does not invalidate CSRF mid-session.
        $exp = $expiresAt ?? (time() + desktop_auth_jwt::configuredRefreshTtl());
        $opts = self::cookieOptions($exp);
        $opts['httponly'] = false;
        self::setCookie(self::CSRF_COOKIE, $csrfToken, $opts['expires'], $opts);
    }

    /**
     * Write WAP HttpOnly auth cookies.
     *
     * Uses wap-token-key / wap-refresh-token-key so WAP and PC desktop
     * do not share cookies and do not interfere with each other.
     *
     * @param array $tokens  desktop_auth_jwt::issueTokens() output
     * @return void
     */
    public static function setWapAuthCookies(array $tokens): void
    {
        $access  = $tokens['access']  ?? [];
        $refresh = $tokens['refresh'] ?? [];

        $accessToken  = $access['token']       ?? '';
        $refreshToken = $refresh['token']      ?? '';
        $accessExp    = (int)($access['expires_at'] ?? 0);
        $refreshExp   = (int)($refresh['expires_at'] ?? 0);

        self::setCookie(self::WAP_ACCESS_COOKIE, $accessToken, $accessExp, self::cookieOptions($accessExp));
        self::setCookie(self::WAP_REFRESH_COOKIE, $refreshToken, $refreshExp, self::cookieOptions($refreshExp));
    }

    /* --------------------------------------------------------------------
     * Cookie clearers (side-effect: expire cookies via setcookie())
     * -------------------------------------------------------------------- */

    /**
     * Clear all browser (desktop) auth cookies.
     *
     * Clears oms-token-key + oms-refresh-token-key across all applicable
     * domain variants to ensure complete cleanup regardless of which
     * domain variant was used at set time.
     *
     * @return void
     */
    public static function clearBrowserAuthCookies(): void
    {
        self::clearCookies([
            self::DESKTOP_ACCESS_COOKIE,
            self::DESKTOP_REFRESH_COOKIE,
        ]);
    }

    /**
     * Clear the legacy desktop access cookie (`jwt`) before login/refresh writes
     * the canonical `oms-token-key` so old cookies cannot shadow the new ones.
     */
    public static function clearLegacyDesktopAuthCookies(): void
    {
        self::clearCookies(self::LEGACY_DESKTOP_AUTH_COOKIES);
    }

    /**
     * Clear the complete desktop auth-cookie set on logout. Includes the legacy
     * `jwt` cookie, both `oms-*` cookies, CSRF, and legacy session markers.
     */
    public static function clearDesktopAuthCookies(): void
    {
        self::clearCookies(self::DESKTOP_AUTH_COOKIES);
    }

    /**
     * Clear all WAP auth cookies.
     *
     * @return void
     */
    public static function clearWapAuthCookies(): void
    {
        self::clearCookies([
            self::WAP_ACCESS_COOKIE,
            self::WAP_REFRESH_COOKIE,
        ]);
    }

    /**
     * Clear all auth cookies (browser + WAP + legacy).
     *
     * @return void
     */
    public static function clearAllAuthCookies(): void
    {
        self::clearCookies(self::ALL_AUTH_COOKIES);
    }

    /* --------------------------------------------------------------------
     * Cookie options resolver
     * -------------------------------------------------------------------- */

    /**
     * Build a setcookie() options array for a given expiry timestamp.
     *
     * Secure policy:
     *   1. env JWT_COOKIE_SECURE explicit true  → true
     *   2. env JWT_COOKIE_SECURE explicit false → false
     *   3. base_url scheme is https            → true
     *   4. host is localhost/127.0.0.1/*.local + http → false
     *   5. default                                                     → true
     *
     * Domain strategy:
     *   1. env JWT_COOKIE_DOMAIN explicit non-empty → use that value
     *   2. env JWT_COOKIE_DOMAIN === '' (empty string) → host-only (empty string)
     *   3. otherwise → derive from kernel::base_url(1) host
     *   4. local dev hosts → host-only allowed
     *
     * SameSite: always 'Lax' (safe default for auth cookies)
     * HttpOnly: always true (tokens must not be readable from JS)
     * Path: always '/'
     *
     * @param int         $expiresAt
     * @param string|null $domainOverride  pass to force a specific domain
     * @return array  keys: expires, path, domain, secure, httponly, samesite
     */
    public static function cookieOptions(int $expiresAt, ?string $domainOverride = null): array
    {
        $baseUrl = self::resolveBaseUrlForCurrentRequest();
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);

        // ----- Secure -----
        $secure = self::resolveSecureFlag($scheme, $host);

        // ----- Domain -----
        $domain = $domainOverride ?? self::resolveCookieDomain($host);

        return [
            'expires'  => $expiresAt,
            'path'     => '/',
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /* --------------------------------------------------------------------
     * Internal helpers
     * -------------------------------------------------------------------- */

    /**
     * Emit a single setcookie() call with explicit options.
     *
     * @param string $name
     * @param string $value
     * @param int    $expiresAt
     * @param array  $opts   from cookieOptions()
     * @return void
     */
    private static function setCookie(string $name, string $value, int $expiresAt, array $opts): void
    {
        if ($name === '' || $value === '') {
            return;
        }

        setcookie($name, $value, [
            'expires'  => $opts['expires'],
            'path'     => $opts['path'],
            'domain'   => $opts['domain'],
            'secure'   => $opts['secure'],
            'httponly' => $opts['httponly'],
            'samesite' => $opts['samesite'],
        ]);
    }

    /**
     * Clear one or more cookies by writing an empty value with past expiry,
     * across all domain variants that might have been used at set time.
     *
     * Symmetry requirement: clear must use EXACT same path/secure/httponly/samesite
     * as the original set, and must cover host-only, dot-prefix, and configured domain.
     *
     * @param string[] $names
     * @return void
     */
    private static function clearCookies(array $names): void
    {
        $baseUrl = self::resolveBaseUrlForCurrentRequest();
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
        $secure = self::resolveSecureFlag($scheme, $host);

        $configuredDomain = self::getConfiguredDomain($host);
        $domainCandidates = [
            '',                                          // host-only
            $host,                                       // current host (no dot)
            '.' . $host,                                 // dot-prefix (legacy)
            $configuredDomain,                           // explicit domain
        ];
        if ($configuredDomain !== '' && $configuredDomain !== $host) {
            $domainCandidates[] = '.' . $configuredDomain;
        }
        $domains = array_values(array_unique($domainCandidates, SORT_REGULAR));

        $past = time() - 3600;

        foreach ($names as $name) {
            foreach ($domains as $domain) {
                setcookie($name, '', [
                    'expires'  => $past,
                    'path'     => '/',
                    'domain'   => $domain,
                    'secure'   => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
    }

    /**
     * Resolve the Secure flag according to strategy.
     *
     * @param string|null $scheme  from base_url
     * @param string|null $host
     * @return bool
     */
    private static function resolveSecureFlag(?string $scheme, ?string $host): bool
    {
        $env = getenv('JWT_COOKIE_SECURE');
        if ($env !== false && $env !== '') {
            return filter_var($env, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }

        if ($scheme === 'https') {
            return true;
        }

        // Dev-friendly: allow insecure cookies on known local dev hosts
        if ($host && self::isLocalHost($host)) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the Cookie domain.
     *
     * @param string|null $host  derived from base_url
     * @return string  empty string means host-only cookie
     */
    private static function resolveCookieDomain(?string $host): string
    {
        $env = getenv('JWT_COOKIE_DOMAIN');
        if ($env !== false) {
            // Empty string → host-only
            // Any non-empty value → use as-is
            return $env;
        }

        // Dev proxy mode: allow host-only for local dev hosts without
        // forcing an explicit Cookie Domain when the host is already local.
        if ($host && self::isLocalHost($host)) {
            // host-only for *.local / localhost / 127.0.0.1
            return '';
        }

        return $host ?? '';
    }

    /**
     * Get the configured cookie domain (from env only; no default fallback).
     *
     * @param string|null $derivedHost
     * @return string
     */
    private static function getConfiguredDomain(?string $derivedHost): string
    {
        $env = getenv('JWT_COOKIE_DOMAIN');
        if ($env !== false) {
            return $env;
        }
        return $derivedHost ?? '';
    }

    /**
     * Check whether the host is a known local-development host.
     *
     * @param string|null $host
     * @return bool
     */
    private static function isLocalHost(?string $host): bool
    {
        if ($host === null || $host === '') {
            return true;
        }

        // 本地开发域名一律走 host-only cookie（oms.local / oms-develop.local / oms-frontend.local 均命中 '.local'）
        $localPrefixes = ['localhost', '127.0.0.1', '::1'];
        foreach ($localPrefixes as $prefix) {
            if ($host === $prefix || str_ends_with($host, '.local')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the base URL for the current request, falling back to
     * scheme + host derived from $_SERVER. Results are cached per
     * scheme+host so flipping HTTPS does not leak prior state.
     *
     * @return string
     */
    private static function resolveBaseUrlForCurrentRequest(): string
    {
        $scheme = self::detectRequestScheme();
        $host = self::detectRequestHost();
        $cacheKey = $scheme . '://' . $host;

        if (isset(self::$baseUrlCache[$cacheKey])) {
            return self::$baseUrlCache[$cacheKey];
        }

        if (class_exists('kernel') && method_exists('kernel', 'base_url')) {
            self::$baseUrlCache[$cacheKey] = (string)kernel::base_url(1);
            return self::$baseUrlCache[$cacheKey];
        }

        self::$baseUrlCache[$cacheKey] = $scheme . '://' . $host;
        return self::$baseUrlCache[$cacheKey];
    }

    private static function detectRequestScheme(): string
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower($https) !== 'off') {
            return 'https';
        }
        return 'http';
    }

    private static function detectRequestHost(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '') {
            return $host;
        }
        $name = $_SERVER['SERVER_NAME'] ?? '';
        if ($name !== '') {
            return $name;
        }
        return 'localhost';
    }
}
