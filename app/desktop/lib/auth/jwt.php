<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 
 * 2.0 (the "License");
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

/* ========================================================================
 * desktop_auth_jwt — JWT 认证核心（签发 / 解码 / 黑名单 / Token 提取 / Session 桥接）
 * ======================================================================== */

class desktop_auth_jwt
{
    /* --------------------------------------------------------------------
     * Token 提取
     * -------------------------------------------------------------------- */

    public static function extractToken(): ?string
    {
        // Legacy compatibility accessor: callers must now use the transport-specific helpers
        // (extractBrowserAccessToken / extractHeaderAccessToken / extractWapAccessToken).
        $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
        if ($token) {
            return $token;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$authHeader && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if ($authHeader && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        if (!empty($_COOKIE['jwt'])) {
            return $_COOKIE['jwt'];
        }

        if (!empty($_COOKIE['oms-token-key'])) {
            return $_COOKIE['oms-token-key'];
        }

        return null;
    }

    /**
     * Browser-only access token extractor.
     *
     * Returns the HttpOnly `oms-token-key` cookie value or null. Browser requests
     * never use header tokens or the legacy `jwt` cookie.
     */
    public static function extractBrowserAccessToken(): ?string
    {
        $token = $_COOKIE['oms-token-key'] ?? '';
        return $token !== '' ? (string) $token : null;
    }

    /**
     * Browser-only refresh token extractor. Request bodies are never accepted.
     */
    public static function extractBrowserRefreshToken(): ?string
    {
        $token = $_COOKIE['oms-refresh-token-key'] ?? '';
        return $token !== '' ? (string)$token : null;
    }

    /**
     * Header/body access token extractor for MCP/API clients.
     *
     * Reads X-Auth-Token then Authorization Bearer. Browser cookies are not used.
     */
    public static function extractHeaderAccessToken(): ?string
    {
        $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
        if ($token !== '') {
            return $token;
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$authHeader && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if ($authHeader && preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * WAP access token extractor. Reads only `wap-token-key`.
     */
    public static function extractWapAccessToken(): ?string
    {
        $token = $_COOKIE['wap-token-key'] ?? '';
        return $token !== '' ? (string) $token : null;
    }

    /**
     * WAP refresh token extractor. Reads only `wap-refresh-token-key`.
     */
    public static function extractWapRefreshToken(): ?string
    {
        $token = $_COOKIE['wap-refresh-token-key'] ?? '';
        return $token !== '' ? (string) $token : null;
    }

    public static function extractRefreshToken(?array $jsonBody = null): ?string
    {
        if ($jsonBody !== null && isset($jsonBody['refresh_token']) && $jsonBody['refresh_token'] !== '') {
            return (string)$jsonBody['refresh_token'];
        }

        if (!empty($_COOKIE['oms-refresh-token-key'])) {
            return (string)$_COOKIE['oms-refresh-token-key'];
        }

        return null;
    }

    public static function claims(bool $verifyExpire = true): array
    {
        $token = self::extractAccessTokenForClaims();
        if ($token) {
            try {
                return self::decodeWithValidation($token, $verifyExpire);
            } catch (desktop_auth_exception $e) {
                // Fall through to context-derived claims when token decode fails
            }
        }

        $contextUserId = desktop_auth_context::userId();
        if ($contextUserId <= 0) {
            return [];
        }

        $profile = desktop_auth_context::profile();
        return [
            'user_id' => $contextUserId,
            'login_name' => desktop_auth_context::loginName() !== ''
                ? desktop_auth_context::loginName()
                : (string)($profile['login_name'] ?? ''),
            'type' => 'access',
            'super' => (bool)($profile['is_super'] ?? desktop_auth_context::isSuper()),
            'need_change_password' => $profile['need_change_password'] ?? null,
            'roles' => is_array($profile['roles'] ?? null) ? $profile['roles'] : [],
            'login_time' => (int)($profile['login_time'] ?? 0),
            'authority' => [],
            'exp' => 0,
            'iat' => 0,
        ];
    }

    /**
     * Prefer browser cookie, then header, then WAP cookie (matches multi-client guard sources).
     */
    private static function extractAccessTokenForClaims(): ?string
    {
        return self::extractBrowserAccessToken()
            ?? self::extractHeaderAccessToken()
            ?? self::extractWapAccessToken();
    }

    public static function currentUserId(): int
    {
        $contextUserId = desktop_auth_context::userId();
        if ($contextUserId > 0) {
            return $contextUserId;
        }

        $claims = self::claims(true);
        if (isset($claims['user_id'])) {
            return (int)$claims['user_id'];
        }
        if (isset($claims['sub'])) {
            return (int)$claims['sub'];
        }
        return 0;
    }

    public static function clearAuthToken(): void
    {
        desktop_auth_session_bridge::clearAuthCookies();
    }

    /* --------------------------------------------------------------------
     * JWT 核心引擎
     * -------------------------------------------------------------------- */

    public const DEFAULT_TTL = 300;          // 兜底值，实际优先读 JWT_TTL 常量
    public const DEFAULT_REFRESH_TTL = 86400; // 兜底值，实际优先读 JWT_REFRESH_TTL 常量

    public const CLAIM_ROLES = 'roles';
    public const CLAIM_LOGIN_TIME = 'login_time';
    public const CLAIM_NEED_CHANGE_PASSWORD = 'need_change_password';
    public const CLAIM_AUTHORITY = 'authority';
    public const CLAIM_SUPER = 'super';
    /**
     * SCRUM-7917 Plan B: session-family id shared by all tokens of one browser
     * login (refresh rotation inherits it). NOTE: unrelated to the transient
     * 2FA request id also called 'fid' in verifySecondFactor flows — that one
     * only lives in transient cleanup payloads, never in JWT claims.
     */
    public const CLAIM_FID = 'fid';

    /** 运行时读取 JWT_TTL / JWT_REFRESH_TTL 常量，支持 env > config.php 覆盖 */
    public static function ttl(string $type = 'access'): int
    {
        static $cache = [];
        if (!isset($cache[$type])) {
            // env 最高优先级
            $envKey = $type === 'refresh' ? 'JWT_REFRESH_TTL' : 'JWT_TTL';
            $envVal = getenv($envKey);
            if ($envVal !== false && $envVal !== '') {
                $cache[$type] = (int)$envVal;
            } else {
                $const = $type === 'refresh' ? 'JWT_REFRESH_TTL' : 'JWT_TTL';
                $cache[$type] = defined($const) ? (int)constant($const) : null;
            }
        }
        return $cache[$type] ?? ($type === 'refresh' ? self::DEFAULT_REFRESH_TTL : self::DEFAULT_TTL);
    }

    /**
     * Preserve login_time across refresh rotations (audit / future absolute session cap).
     *
     * @param array $refreshClaims decoded refresh JWT payload
     * @return int Unix timestamp of original login
     */
    public static function preservedLoginTimeFromClaims(array $refreshClaims): int
    {
        $loginTime = (int)($refreshClaims[self::CLAIM_LOGIN_TIME] ?? 0);
        if ($loginTime > 0) {
            return $loginTime;
        }
        $iat = (int)($refreshClaims['iat'] ?? 0);
        return $iat > 0 ? $iat : time();
    }

    public static function resolveSecret(): string
    {
        return self::secret();
    }

    protected static function secret(): string
    {
        static $secret;
        if ($secret !== null) {
            return $secret;
        }

        $secret = getenv('JWT_SECRET');
        if (!$secret && defined('JWT_SECRET')) {
            $secret = constant('JWT_SECRET');
        }
        if (!$secret) {
            $secret = app::get('desktop')->getConf('desktop.jwt.secret');
        }
        if (!$secret) {
            $secret = base_certificate::token() ?: '';
        }

        if (!$secret) {
            throw new desktop_auth_exception(
                'JWT secret not configured. Set JWT_SECRET env or desktop.jwt.secret conf.'
            );
        }

        return $secret;
    }

    /**
     * Mint a fresh session-family id (SCRUM-7917 Plan B).
     * One id per browser login; refresh rotation inherits it.
     */
    public static function newFamilyId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Revoke every token of a session family (manual logout of one browser).
     * Does NOT touch token_version — other concurrent sessions of the same
     * account keep working.
     */
    public static function revokeFamily(string $familyId, ?int $familyExpiresAt = null): void
    {
        if ($familyId === '') {
            return;
        }
        kernel::single('desktop_auth_family_revoker')->revoke(
            $familyId,
            desktop_auth_family_revoker::markerTtl($familyExpiresAt)
        );
    }

    /**
     * True when the token's session family has been revoked.
     * Legacy tokens without fid are never family-revoked (backward compatible).
     */
    public static function isFamilyRevoked(?string $familyId): bool
    {
        return kernel::single('desktop_auth_family_revoker')->isRevoked($familyId);
    }

    public static function blacklistJti(?string $jti, int $exp): void
    {
        if ($jti === null || $jti === '') {
            return;
        }
        $ttl = $exp - time();
        if ($ttl <= 0) {
            return;
        }
        kernel::single('desktop_auth_blacklist')->add($jti, $ttl);
    }

    public static function blacklistPayload(array $payload): void
    {
        self::blacklistJti($payload['jti'] ?? null, (int)($payload['exp'] ?? 0));
    }

    protected static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    protected static function sign(string $data): string
    {
        return self::base64UrlEncode(hash_hmac('sha256', $data, self::secret(), true));
    }

    protected static function issue(array $claims, int $ttl, ?int $expiresAt = null): array
    {
        $now = time();
        $payload = array_merge([
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiresAt ?? $now + $ttl,
            'jti' => bin2hex(random_bytes(8)),
            'ver' => $claims['ver'] ?? 1,
        ], $claims);

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $encodedHeader = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $encodedPayload = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = self::sign($encodedHeader . '.' . $encodedPayload);

        return [
            'token' => $encodedHeader . '.' . $encodedPayload . '.' . $signature,
            'expires_at' => $payload['exp'],
            'expires_in' => $payload['exp'] - $payload['iat'],
            'payload' => $payload,
        ];
    }

    public static function issueAccessToken(
        int $userId,
        string $loginName,
        array $claims = [],
        ?int $ttl = null
    ): array {
        $ver = self::fetchTokenVersion($userId);

        $baseClaims = array_merge($claims, [
            'sub' => $userId,
            'uname' => $loginName,
            'type' => 'access',
            'ver'  => $ver,
        ]);

        if (!isset($baseClaims[self::CLAIM_SUPER])) {
            $baseClaims[self::CLAIM_SUPER] = false;
        }
        if (!isset($baseClaims[self::CLAIM_LOGIN_TIME])) {
            $baseClaims[self::CLAIM_LOGIN_TIME] = time();
        }
        if (!isset($baseClaims[self::CLAIM_NEED_CHANGE_PASSWORD])) {
            $baseClaims[self::CLAIM_NEED_CHANGE_PASSWORD] = null;
        }
        if (!isset($baseClaims[self::CLAIM_ROLES])) {
            $baseClaims[self::CLAIM_ROLES] = [];
        }
        if (!isset($baseClaims[self::CLAIM_FID])) {
            $baseClaims[self::CLAIM_FID] = self::newFamilyId();
        }

        return self::issue($baseClaims, $ttl ?? self::ttl('access'));
    }

    /**
     * AI Assistant embed 下游短时 JWT（响应体可读；与 access 同 secret / token_version）。
     */
    public static function issueAiAssistantToken(
        int $userId,
        string $loginName,
        ?int $ttl = null
    ): array {
        $ver = self::fetchTokenVersion($userId);
        $resolvedTtl = $ttl ?? min(self::ttl('access'), 3600);

        return self::issue([
            'sub' => $userId,
            'uname' => $loginName,
            'type' => 'ai_assistant',
            'aud' => 'oms-ai-assistant',
            'ver' => $ver,
            self::CLAIM_LOGIN_TIME => time(),
        ], $resolvedTtl);
    }

    public static function issueRefreshToken(
        int $userId,
        string $loginName,
        array $claims = [],
        ?int $ttl = null,
        ?int $expiresAt = null
    ): array {
        $ver = self::fetchTokenVersion($userId);

        $baseClaims = array_merge($claims, [
            'sub' => $userId,
            'uname' => $loginName,
            'type' => 'refresh',
            'ver'  => $ver,
            self::CLAIM_LOGIN_TIME => $claims[self::CLAIM_LOGIN_TIME] ?? time(),
        ]);
        if (!isset($baseClaims[self::CLAIM_FID]) || $baseClaims[self::CLAIM_FID] === '') {
            $baseClaims[self::CLAIM_FID] = self::newFamilyId();
        }

        return self::issue($baseClaims, $ttl ?? self::ttl('refresh'), $expiresAt);
    }

    public static function decode(string $token, bool $verifyExpire = true): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new desktop_auth_exception('Invalid token structure');
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;
        $expected = self::sign($encodedHeader . '.' . $encodedPayload);
        if (!hash_equals($expected, $signature)) {
            throw new desktop_auth_exception('Invalid token signature');
        }

        $payload = json_decode(self::base64UrlDecode($encodedPayload), true) ?: [];
        if ($verifyExpire && isset($payload['exp']) && $payload['exp'] < time()) {
            throw new desktop_auth_exception('Token has expired');
        }

        $jti = $payload['jti'] ?? null;
        if ($jti) {
            $blacklist = kernel::single('desktop_auth_blacklist');
            if ($blacklist->isBlacklisted($jti)) {
                throw new desktop_auth_exception('Token has been revoked');
            }
        }

        return $payload;
    }

    public static function decodeWithValidation(string $token, bool $verifyExpire = true): array
    {
        $payload = self::decode($token, $verifyExpire);

        $userId = self::extractInt($payload, 'sub');
        if ($userId <= 0) {
            throw new desktop_auth_exception('Invalid user ID in token');
        }

        $iat = self::extractInt($payload, 'iat', 0);
        if ($iat > 0) {
            $accountModel = app::get('pam')->model('account');
            $row = $accountModel->getList('password_changed_at', ['account_id' => $userId]);
            $changedAt = (int)($row[0]['password_changed_at'] ?? 0);
            if ($changedAt > 0 && $iat < $changedAt) {
                throw new desktop_auth_exception('Password has changed, token revoked');
            }
        }

        // token_version 验证: token.ver < DB.token_version 则拒绝
        // 无条件生效：改密 / refresh 重用 / 互踢 必须使旧 token 立即失效（不受 desktop.account.equal.restrict 控制）
        // SCRUM-7917 Plan B 后手动登出不再 bump token_version，改走会话族吊销（下一段）。
        $tokenVer = (int)($payload['ver'] ?? 1);
        $dbVer = self::fetchTokenVersion($userId);
        if ($tokenVer < $dbVer) {
            throw new desktop_auth_exception('Token has been revoked');
        }

        // 会话族吊销验证（SCRUM-7917 Plan B）：手动登出只杀本会话族。
        // 无 fid 的旧 token（上线前签发）不做族校验，向后兼容、平滑灰度。
        if (self::isFamilyRevoked(isset($payload[self::CLAIM_FID]) ? (string)$payload[self::CLAIM_FID] : null)) {
            throw new desktop_auth_exception('Token has been revoked');
        }

        $loginName = self::extractString($payload, 'uname');
        $type = self::extractString($payload, 'type');

        return [
            'user_id' => $userId,
            'login_name' => $loginName,
            'type' => $type,
            'super' => self::extractBool($payload, self::CLAIM_SUPER, false),
            'need_change_password' => self::extractNullableString($payload, self::CLAIM_NEED_CHANGE_PASSWORD),
            'roles' => self::extractArray($payload, self::CLAIM_ROLES, []),
            'login_time' => self::extractInt($payload, self::CLAIM_LOGIN_TIME, 0),
            'authority' => self::extractArray($payload, self::CLAIM_AUTHORITY, []),
            'exp' => self::extractInt($payload, 'exp', 0),
            'iat' => self::extractInt($payload, 'iat', 0),
        ];
    }

    public static function decodeRefresh(string $token): array
    {
        $payload = self::decode($token, true);
        if (($payload['type'] ?? '') !== 'refresh') {
            throw new desktop_auth_exception('Invalid refresh token');
        }

        // 会话族吊销验证（SCRUM-7917 Plan B）：手动登出吊销本族后，refresh 不得继续轮换。
        if (self::isFamilyRevoked(isset($payload[self::CLAIM_FID]) ? (string)$payload[self::CLAIM_FID] : null)) {
            throw new desktop_auth_exception('Token has been revoked');
        }

        // 必须最先校验 userId：sub<=0 的 token 不得绕过后续所有安全校验
        $userId = (int)($payload['sub'] ?? 0);
        if ($userId <= 0) {
            throw new desktop_auth_exception('Invalid refresh token');
        }

        $iat = $payload['iat'] ?? 0;
        if ($iat > 0) {
            $accountModel = app::get('pam')->model('account');
            $row = $accountModel->getList('password_changed_at', ['account_id' => $userId]);
            $changedAt = (int)($row[0]['password_changed_at'] ?? 0);
            if ($changedAt > 0 && $iat < $changedAt) {
                throw new desktop_auth_exception('Password has changed, refresh token revoked');
            }
        }

        // token_version 验证: token.ver < DB.token_version 则拒绝
        // 无条件生效：logout / 改密 必须使旧 refresh token 立即失效（不受 desktop.account.equal.restrict 控制）
        $tokenVer = (int)($payload['ver'] ?? 1);
        $dbVer = self::fetchTokenVersion($userId);
        if ($tokenVer < $dbVer) {
            throw new desktop_auth_exception('Token version mismatch');
        }

        return $payload;
    }

    /**
     * Consume a refresh token for rotation: validate, then blacklist jti (one-time use).
     *
     * 宽限期幂等（rotation leeway）：竞态页签并发 refresh 时，输家对"刚被消费过"
     * 的 jti 重放不再触发盗用吊销，而是按同一次轮换幂等放行——前提是签名有效、
     * jti 确实在黑名单（即真的消费过）且在 JWT_REFRESH_GRACE_SECONDS 窗口内。
     * 窗口外重放或从未消费过的 jti 仍走 revokeSessionOnRefreshReuse 灭全家。
     *
     * @return array<string, mixed> raw refresh JWT payload
     * @throws desktop_auth_exception
     */
    public static function consumeRefreshToken(string $token): array
    {
        try {
            $payload = self::decodeRefresh($token);
        } catch (desktop_auth_exception $e) {
            $graceClaims = self::graceReplayClaims($token, $e);
            if ($graceClaims !== null) {
                return $graceClaims;
            }

            throw $e;
        }

        // 先记宽限标记、后写黑名单：若顺序颠倒，并发重放可能落在两次写之间，
        // 看到"已入黑名单但宽限标记未落"，被误判盗用灭全家（微秒级竞态窗口）。
        // 先记后封把该窗口彻底闭合；标记写失败仅意味着宽限缺失（fail-closed 维持盗用响应）。
        self::recordRefreshGrace($token, $payload);
        self::blacklistPayload($payload);

        return $payload;
    }

    /**
     * Within the grace window, a replay of a just-consumed refresh jti is the
     * same logical rotation: return the recorded claims instead of failing.
     *
     * Security gates (all must pass):
     *  - failure is a 'revoked' failure (i.e. blacklisted jti / family /
     *    version rejection paths stay protected as before for foreign errors);
     *  - signature verified via peekSignedPayload;
     *  - jti present and actually blacklisted (never consumed => no grace);
     *  - token_version / password-change rejections only get grace when the
     *    recorded claims still satisfy the current DB state (re-checked below
     *    by the caller through decodeRefresh of the recorded payload — here we
     *    re-verify ver/password cheaply against the replayed payload itself);
     *  - family revocation (manual logout) always wins over grace.
     */
    private static function graceReplayClaims(string $token, desktop_auth_exception $e): ?array
    {
        if (!str_contains($e->getMessage(), 'revoked')) {
            return null;
        }

        $payload = self::peekSignedPayload($token);
        if ($payload === null || ($payload['type'] ?? '') !== 'refresh') {
            return null;
        }

        // Manual logout revokes the whole family: grace must not resurrect it.
        if (self::isFamilyRevoked(isset($payload[self::CLAIM_FID]) ? (string)$payload[self::CLAIM_FID] : null)) {
            return null;
        }

        $jti = (string)($payload['jti'] ?? '');
        if ($jti === '' || !kernel::single('desktop_auth_blacklist')->isBlacklisted($jti)) {
            return null;
        }

        $graceClaims = kernel::single('desktop_auth_refresh_grace')->claimsFor($jti);
        if ($graceClaims === null) {
            return null;
        }

        // The recorded claims must describe the same identity as the replayed
        // token (defence against jti collision/mismatched storage).
        if ((int)($graceClaims['sub'] ?? 0) !== (int)($payload['sub'] ?? -1)) {
            return null;
        }

        // 同族校验：血缘记录的 fid 必须与重放 token 的 fid 一致，
        // 防御存储串号/键碰撞场景下的跨族身份混用。
        $replayFid = isset($payload[self::CLAIM_FID]) ? (string)$payload[self::CLAIM_FID] : '';
        $graceFid = isset($graceClaims[self::CLAIM_FID]) ? (string)$graceClaims[self::CLAIM_FID] : '';
        if ($replayFid === '' || $graceFid === '' || !hash_equals($graceFid, $replayFid)) {
            return null;
        }

        // ver must not have moved past what the recorded rotation carried;
        // a bumped token_version (改密/互踢) invalidates the grace replay.
        $dbVer = self::fetchTokenVersion((int)($payload['sub'] ?? 0));
        if ((int)($graceClaims['ver'] ?? 1) < $dbVer) {
            return null;
        }

        return $graceClaims;
    }

    /**
     * After a successful rotation, remember the old jti -> consumed claims for
     * the grace window so losing-race replays resolve idempotently.
     */
    private static function recordRefreshGrace(string $token, array $consumedPayload): void
    {
        $jti = (string)($consumedPayload['jti'] ?? '');
        if ($jti === '') {
            return;
        }

        kernel::single('desktop_auth_refresh_grace')->record($jti, $consumedPayload);
    }

    /**
     * Reuse of a rotated refresh token → revoke all sessions (token_version bump).
     */
    public static function revokeSessionOnRefreshReuse(string $token, desktop_auth_exception $exception): void
    {
        if (!str_contains($exception->getMessage(), 'revoked')) {
            return;
        }

        $payload = self::peekSignedPayload($token);
        if ($payload === null || ($payload['type'] ?? '') !== 'refresh') {
            return;
        }

        $jti = (string)($payload['jti'] ?? '');
        if ($jti === '' || !kernel::single('desktop_auth_blacklist')->isBlacklisted($jti)) {
            return;
        }

        // 宽限期内重放 = 同一次轮换的竞态页签，不是盗用：不吊销。
        if (kernel::single('desktop_auth_refresh_grace')->claimsFor($jti) !== null) {
            return;
        }

        $userId = (int)($payload['sub'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        try {
            self::incrementTokenVersion($userId);
        } catch (desktop_auth_exception) {
            // Best-effort theft response; caller still receives original refresh failure.
        }
    }

    /**
     * Read JWT payload after signature verification (skips expiry + blacklist checks).
     *
     * @return array<string, mixed>|null
     */
    public static function peekSignedPayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;
        $expected = self::sign($encodedHeader . '.' . $encodedPayload);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($encodedPayload), true);

        return is_array($payload) ? $payload : null;
    }

    private static function extractInt(array $payload, string $key, int $default = 0): int
    {
        $value = $payload[$key] ?? null;
        return is_numeric($value) ? (int)$value : $default;
    }

    private static function extractString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        return is_string($value) ? $value : '';
    }

    private static function extractBool(array $payload, string $key, bool $default = false): bool
    {
        $value = $payload[$key] ?? null;
        return is_bool($value) ? $value : $default;
    }

    private static function extractNullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    private static function extractArray(array $payload, string $key, array $default = []): array
    {
        $value = $payload[$key] ?? null;
        return is_array($value) ? $value : $default;
    }

    /**
     * Fetch the current token_version for a user.
     *
     * DB failures are fail-closed: any exception results in an authentication
     * failure rather than returning a permissive default. This prevents a
     * temporarily unavailable DB from silently allowing revoked tokens.
     *
     * @param int $userId
     * @return int
     * @throws desktop_auth_exception on DB read failure
     */
    private static function fetchTokenVersion(int $userId): int
    {
        $userModel = app::get('desktop')->model('users');
        $row = $userModel->getList('token_version', ['user_id' => $userId]);
        if (empty($row)) {
            throw new desktop_auth_exception('token_version lookup returned no rows for user');
        }
        return (int)($row[0]['token_version'] ?? 1);
    }

    public static function incrementTokenVersion(int $userId): void
    {
        $userModel = app::get('desktop')->model('users');

        // Read current value first (with direct SQL to bypass any ORM caching)
        $uid = (int) $userId;
        $rows = $userModel->db->select(
            'SELECT token_version FROM sdb_desktop_users WHERE user_id=' . $uid . ' LIMIT 1'
        );
        $currentVer = (int)($rows[0]['token_version'] ?? 0);
        $newVer = $currentVer + 1;

        // Write using raw SQL (same pattern as other desktop models: flow.php, roles.php)
        // ORM update() silently drops fields not in schema; raw SQL guarantees the write.
        $sql = 'UPDATE sdb_desktop_users SET token_version=' . (int)$newVer . ' WHERE user_id=' . $uid;
        $userModel->db->exec($sql);

        // Verify the write actually landed in the database
        $verifyRows = $userModel->db->select(
            'SELECT token_version FROM sdb_desktop_users WHERE user_id=' . $uid . ' LIMIT 1'
        );
        $dbVer = (int)($verifyRows[0]['token_version'] ?? 0);
        if ($dbVer !== $newVer) {
            throw new desktop_auth_exception(
                "token_version write mismatch: expected {$newVer}, got {$dbVer} in DB"
            );
        }
    }

    /* --------------------------------------------------------------------
     * token_version 配置
     * -------------------------------------------------------------------- */

    /**
     * 是否启用同账号互踢（token_version 机制）
     * 对应配置项: desktop.account.equal.restrict
     * 默认开启（非 'false' 即视为开启）
     */
    public static function isSingleSessionEnforced(): bool
    {
        $val = app::get('ome')->getConf('desktop.account.equal.restrict');
        return $val !== 'false';
    }

    /* --------------------------------------------------------------------
     * Legacy $_POST 注入 & 会话完成
     * -------------------------------------------------------------------- */

    public static function seedLegacyPost(
        string $username,
        string $password,
        string $terminal,
        bool $remember,
        string $needMobile,
        string $mobileCode,
        string $verifyCode
    ): void {
        $payload = [
            'login_name' => $username,
            'login_password' => $password,
            'terminal' => $terminal,
            'remember' => $remember ? 'true' : '',
            'mobileverifycode' => $mobileCode,
            'vcode' => $verifyCode,
        ];
        if ($needMobile !== '') {
            $payload['needmobileverifycode'] = $needMobile;
        }

        $key = date('Y-m-d', time()) . 'eDu189';
        $iv = substr(OPENSSL_CPIHER_IV, 0, 16); // 对齐前端加密用的 16 字节 IV
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $jsonLen = strlen($json);
        $blockSize = 16;
        $padLen = $blockSize - ($jsonLen % $blockSize);
        $padded = $json . str_repeat(chr($padLen), $padLen); // PKCS7
        $_POST['login_cipher'] = openssl_encrypt($padded, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
    }

    private static function pkcs7Pad(string $data, int $blockSize): string
    {
        $pad = $blockSize - (strlen($data) % $blockSize);
        return $data . str_repeat(chr($pad), $pad);
    }

    public static function configuredIdleTimeoutMinutes(): int
    {
        return defined('IDLE_TIMEOUT_MINUTES') ? (int)constant('IDLE_TIMEOUT_MINUTES') : 60;
    }

    public static function configuredAccessTtl(): int
    {
        return self::ttl('access');
    }

    public static function configuredRefreshTtl(): int
    {
        return self::ttl('refresh');
    }

    /**
     * Issue access + refresh token pair.
     *
     * @param int|null $preserveRefreshExpiresAt When set (refresh flow), new refresh keeps this
     *                                           absolute exp instead of sliding JWT_REFRESH_TTL.
     */
    public static function issueTokens(
        int $userId,
        string $loginName,
        array $profile,
        ?int $preserveRefreshExpiresAt = null
    ): array {
        $extraClaims = [];
        if (!empty($profile['is_super'])) {
            $extraClaims[self::CLAIM_SUPER] = true;
        }
        if (!empty($profile['roles'])) {
            $extraClaims[self::CLAIM_ROLES] = $profile['roles'];
        }
        if (!empty($profile['need_change_password'])) {
            $extraClaims[self::CLAIM_NEED_CHANGE_PASSWORD] = $profile['need_change_password'];
        }
        $presetLoginTime = (int)($profile['login_time'] ?? 0);
        $extraClaims[self::CLAIM_LOGIN_TIME] = $presetLoginTime > 0 ? $presetLoginTime : time();

        // SCRUM-7917 Plan B: one login = one session family. Rotation passes the
        // existing fid via profile; fresh logins mint one here and share it
        // between access AND refresh (each issue*() fallback would otherwise
        // mint two different families).
        $presetFamilyId = (string)($profile['fid'] ?? '');
        $extraClaims[self::CLAIM_FID] = $presetFamilyId !== '' ? $presetFamilyId : self::newFamilyId();

        $access = self::issueAccessToken($userId, $loginName, $extraClaims);

        $refreshTtl = null;
        if ($preserveRefreshExpiresAt !== null) {
            $refreshTtl = self::remainingTtlUntil($preserveRefreshExpiresAt);
        }
        $refresh = self::issueRefreshToken(
            $userId,
            $loginName,
            $extraClaims,
            $refreshTtl,
            $preserveRefreshExpiresAt
        );

        return [
            'access' => $access,
            'refresh' => $refresh,
        ];
    }

    /**
     * Remaining seconds until an absolute Unix expiry (for non-sliding refresh rotation).
     *
     * @throws desktop_auth_exception when already expired
     */
    public static function remainingTtlUntil(int $expiresAt): int
    {
        $remaining = $expiresAt - time();
        if ($remaining <= 0) {
            throw new desktop_auth_exception('Refresh token has expired');
        }

        return $remaining;
    }

    public static function setJwtCookie(string $token, int $expiresAt): void
    {
        $host = parse_url(kernel::base_url(1), PHP_URL_HOST);
        setcookie('oms-token-key', $token, [
            'expires' => $expiresAt,
            'path' => '/',
            'domain' => $host ?: '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Set HttpOnly JWT cookie for WAP.
     *
     * Uses 'wap-token-key' to distinguish from desktop 'oms-token-key'.
     * domain/secure are environment-aware: local HTTP dev environments get
     * secure=false so the cookie is sent back; production always uses secure=true.
     *
     * @param string $token  JWT string
     * @param int    $expiresAt  Unix timestamp
     */
    public static function setWapJwtCookie(string $token, int $expiresAt): void
    {
        self::setWapAuthCookie('wap-token-key', $token, $expiresAt);
    }

    public static function setWapRefreshCookie(string $token, int $expiresAt): void
    {
        self::setWapAuthCookie('wap-refresh-token-key', $token, $expiresAt);
    }

    private static function setWapAuthCookie(string $name, string $token, int $expiresAt): void
    {
        $host = parse_url(kernel::base_url(1), PHP_URL_HOST);
        $isHttps = parse_url(kernel::base_url(1), PHP_URL_SCHEME) === 'https';
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1', ''], true)
            || preg_match('/\\.local$/i', $host ?? '');

        // Local HTTP: be permissive so WAP login works in dev; otherwise insist on HTTPS
        $secure = $isHttps || !$isLocal;

        setcookie($name, $token, [
            'expires' => $expiresAt,
            'path' => '/',
            'domain' => $host ?: '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearAuthCookies(): void
    {
        desktop_auth_session_bridge::clearAuthCookies();
    }

    /**
     * @return array{message:string,data:array,status:int}|null 需要二次认证时返回挑战数据，否则 null
     */
    public static function finalizeSession(
        int $userId,
        string $password,
        pam_auth $auth,
        string $username,
        string $accountType,
        string $idaasCaptchaCode = '',
        string $idaasCaptchaValue = ''
    ): ?array {
        if (desktop_auth_idaas::isEnabled()) {
            $challenge = desktop_auth_idaas::authenticate(
                $userId,
                $username,
                $password,
                $accountType,
                $idaasCaptchaCode,
                $idaasCaptchaValue
            );
            if ($challenge !== null) {
                return $challenge;
            }
        } else {
            // 本地风控 2FA 已延期（可能未购 SMS），风控结果不转为阻塞式短信挑战
            // $risk = self::detectRisk($userId, $password, $username, $accountType);
        }

        self::fireLoginHooks($userId, $username, $accountType);

        return null;
    }

    /**
     * 触发登录后钩子：pam_login_listener / pam_log / login_log
     * 在 issueTokens 之前调用，与 pam_callback::login() 行为对齐
     */
    protected static function fireLoginHooks(int $userId, string $username, string $accountType): void
    {
        $params = [
            'uname' => $username,
            'member_id' => $userId,
            'type' => $accountType,
        ];

        // 1. pam_login_listener 事件通知
        foreach (kernel::servicelist('pam_login_listener') as $service) {
            if (method_exists($service, 'listener_login')) {
                $service->listener_login($params);
            }
        }

        // 2. pam_log 登录日志（desktop_mdl_login -> sdb_pam_log）
        $authData = ['log_data' => 'JWT login'];
        try {
            $logData = [
                'event_type' => $accountType,
                'event_data' => base_request::get_remote_addr() . ':' . ($authData['log_data'] ?? ''),
                'event_time' => time(),
            ];
            app::get('desktop')->model('login')->insert($logData);
        } catch (Exception $e) {
            // 日志写入失败不影响登录流程
        }

        // 3. 御城河 login_log（DEV_ENV 下会跳过）
        kernel::single('base_hchsafe')->login_log($params);
    }

    protected static function detectRisk(int $userId, string $password, string $username, string $accountType): ?array
    {
        if (defined('DEV_ENV') && DEV_ENV) {
            return null;
        }

        $params = [
            'uname' => $username,
            'password_string' => pam_encrypt::get_encrypted_password($password, $accountType),
            'member_id' => $userId,
        ];

        $msg = '';
        $extra = null;
        $risk = kernel::single('base_hchsafe')->compute_risk($params, $msg, $extra);

        return $risk ? null : ['msg' => $msg, 'extra' => $extra];
    }

    /**
     * 御城河本地风控短信二次认证（非 IDAAS 路径）。
     *
     * @return array{message:string,data:array,status:int}|null
     */
    protected static function buildLocalRiskSmsChallenge(
        int $userId,
        string $username,
        string $accountType,
        string $message
    ): ?array {
        $user = app::get('desktop')->model('users')->dump($userId, 'mobile');
        $mobile = $user['mobile'] ?? null;
        if (!$mobile || strlen($mobile) < 11) {
            $text = $message !== '' ? $message : '账号存在安全风险，需要进行手机验证';

            return ['message' => $text, 'data' => [], 'status' => 403];
        }

        desktop_auth_second_factor::assertSmsAllowed();

        $fid = bin2hex(random_bytes(16));
        $result = kernel::single('erpapi_router_request')->set('account', 'account')->account_sendLoginVerifyCode([
            'login_name' => $username,
            'mobile' => $mobile,
            'fid' => $fid,
        ]);

        if (($result['rsp'] ?? '') !== 'succ') {
            $failMsg = trim((string)($result['msg'] ?? ($result['err_msg'] ?? '短信发送失败')));

            return [
                'message' => $failMsg !== '' ? $failMsg : '短信发送失败',
                'data' => [],
                'status' => 502,
            ];
        }

        desktop_auth_second_factor::markSmsSent();
        desktop_auth_second_factor::savePending($fid, $userId, $accountType, $username);

        $text = $message !== '' ? $message : '账号存在安全风险，需要进行手机验证';

        return [
            'message' => $text,
            'data' => [
                'need_mobile_code' => true,
                'fid' => $fid,
                'username' => $username,
                'mobile_mask' => substr($mobile, 0, 3) . '****' . substr($mobile, -4),
                'sms_sent' => true,
            ],
            'status' => 403,
        ];
    }
}
