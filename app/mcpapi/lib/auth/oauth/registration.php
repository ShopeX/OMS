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
 * RFC 7591 动态客户端注册（仅公共客户端，token_endpoint_auth_method=none + PKCE 强制）。
 *
 * 防滥用治理（技术设计文档 V3.1）：
 * - 容量上限：单租户 dynamic client ≤ 1000，触顶时优先回收无活跃授权的过期客户端
 * - TTL：30 天，存在活跃授权（换票/续期）时自动续期
 * - scope 收敛：注册请求 scope 只保留白名单内
 * - 限流：注册端点按 IP 固定窗口限流（cachecore 可用时）
 * - redirect_uris 必填且必须 https 或 loopback / 私有 scheme
 */
final class mcpapi_auth_oauth_registration
{
    public const MAX_DYNAMIC_CLIENTS = 1000;
    public const INACTIVE_TTL = 2592000; // 30 天
    public const RATE_LIMIT_PER_HOUR = 20;

    /** 动态注册治理模式：open=注册即生效 / approval=注册后待管理员审批 / closed=关闭动态注册 */
    public const MODE_CONF_KEY = 'oauth.dynamic_registration.mode';
    public const MODES = ['open', 'approval', 'closed'];

    public static function currentMode(): string
    {
        // 测试环境固定 open：单测不依赖真实环境 conf（approval/closed 用例经构造参数注入）
        if (defined('TESTSUITE')) {
            return 'open';
        }
        try {
            $mode = (string) app::get('mcpapi')->getConf(self::MODE_CONF_KEY);
        } catch (Throwable $e) {
            return 'open';
        }
        return in_array($mode, self::MODES, true) ? $mode : 'open';
    }

    public static function setMode(string $mode): void
    {
        if (!in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('Invalid dynamic registration mode', 422);
        }
        app::get('mcpapi')->setConf(self::MODE_CONF_KEY, $mode);
    }

    private $clientSaver;
    private $clientCounter;
    private $rateLimiter;
    private mcpapi_auth_oauth_scopes $scopes;
    private string $tenantId;
    private string $omsBaseUrl;
    private int $now;

    public function __construct(
        ?callable $clientSaver = null,
        ?callable $clientCounter = null,
        ?mcpapi_auth_oauth_scopes $scopes = null,
        ?string $tenantId = null,
        ?string $omsBaseUrl = null,
        ?int $now = null,
        ?callable $rateLimiter = null,
        ?string $mode = null
    ) {
        $this->clientSaver = $clientSaver ?? static function (array $row): void {
            app::get('mcpapi')->model('client')->insert($row);
        };
        $this->clientCounter = $clientCounter ?? static function (string $tenantId): int {
            return (int) app::get('mcpapi')->model('client')->count([
                'tenant_id' => $tenantId,
                'registration_origin' => 'dynamic',
            ]);
        };
        $this->scopes = $scopes ?? new mcpapi_auth_oauth_scopes();
        $this->tenantId = $tenantId ?? 'default';
        $this->omsBaseUrl = $omsBaseUrl ?? rtrim((string) kernel::base_url(1), '/');
        $this->now = $now ?? time();
        $this->rateLimiter = $rateLimiter ?? static function (string $clientIp): bool {
            return self::defaultRateLimiter($clientIp);
        };
        $this->mode = $mode;
    }

    private ?string $mode;

    /**
     * 默认限流器：按 IP 固定窗口（1 小时 RATE_LIMIT_PER_HOUR 次），cachecore 不可用时 fail-open。
     */
    private static function defaultRateLimiter(string $clientIp): bool
    {
        try {
            if ($clientIp === '' || !class_exists('cachecore') || !cachecore::enable()) {
                return true;
            }
            $key = 'mcpapi_oauth_register_' . md5($clientIp);
            $count = cachecore::fetch($key);
            $count = is_numeric($count) ? (int) $count : 0;
            if ($count >= self::RATE_LIMIT_PER_HOUR) {
                return false;
            }
            cachecore::store($key, $count + 1, 3600);
            return true;
        } catch (Throwable $e) {
            return true;
        }
    }

    /**
     * 注册公共客户端，返回 RFC 7591 响应体。
     *
     * @param string[] $redirectUris
     * @param string[]|null $requestedScopes
     * @return array{client_id:string, client_id_issued_at:int, redirect_uris:string[], token_endpoint_auth_method:string, grant_types:string[], scope:string, client_name?:string}
     * @throws mcpapi_auth_oauth_error
     */
    public function register(string $clientName, array $redirectUris, ?array $requestedScopes = null, ?string $clientIp = null): array
    {
        $uris = $this->sanitizeRedirectUris($redirectUris);
        if ($uris === []) {
            throw new mcpapi_auth_oauth_error('invalid_redirect_uri', 'At least one valid https or loopback redirect_uri is required');
        }

        // 注册端点按 IP 限流
        if ($clientIp !== null && $clientIp !== '' && !($this->rateLimiter)($clientIp)) {
            throw new mcpapi_auth_oauth_error('invalid_client_metadata', 'Too many registration attempts, please retry later', 429);
        }

        // 治理模式：closed 直接拒绝；approval 注册后 enabled=false 待管理员审批
        $mode = $this->mode ?? self::currentMode();
        if ($mode === 'closed') {
            throw new mcpapi_auth_oauth_error('access_denied', 'Dynamic client registration is disabled; contact the administrator for static registration (client_id/client_secret)', 403);
        }
        $pending = ($mode === 'approval');

        $count = ($this->clientCounter)($this->tenantId);
        if ($count >= self::MAX_DYNAMIC_CLIENTS) {
            throw new mcpapi_auth_oauth_error('invalid_client_metadata', 'Dynamic registration capacity reached for this tenant', 403);
        }

        $scopes = $requestedScopes === null
            ? $this->scopes->defaults()
            : $this->scopes->sanitize($requestedScopes);
        if ($scopes === []) {
            $scopes = $this->scopes->defaults();
        }

        $clientId = 'mcp_' . bin2hex(random_bytes(16));
        $row = [
            'client_id' => $clientId,
            'client_secret_hash' => null,
            'owner_user_id' => 0,
            'name' => trim($clientName) !== '' ? trim($clientName) : ('MCP client ' . substr($clientId, -6)),
            'tenant_id' => $this->tenantId,
            'oms_base_url' => $this->omsBaseUrl,
            'oms_subject_id' => 0,
            'oms_role_ids' => [],
            'oms_permissions' => [],
            'scopes' => $scopes,
            'client_type' => 'public',
            'redirect_uris' => $uris,
            'registration_origin' => 'dynamic',
            'token_endpoint_auth' => 'none',
            'registered_at' => $this->now,
            'last_seen_at' => $this->now,
            'enabled' => $pending ? 'false' : 'true',
            'revoked_at' => null,
            'expires_at' => $this->now + self::INACTIVE_TTL,
            'created_at' => $this->now,
            'updated_at' => $this->now,
            'created_by' => $pending ? 'oauth-register-pending' : 'oauth-register',
            'updated_by' => $pending ? 'oauth-register-pending' : 'oauth-register',
            'revoked_reason' => null,
            'last_used_at' => null,
        ];
        ($this->clientSaver)($row);

        $response = [
            'client_id' => $clientId,
            'client_id_issued_at' => $this->now,
            'redirect_uris' => $uris,
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'scope' => implode(' ', $scopes),
        ];
        if (trim($clientName) !== '') {
            $response['client_name'] = trim($clientName);
        }
        if ($pending) {
            $response['x_pending_approval'] = true;
            error_log(sprintf(
                'MCPAPI_SECURITY dynamic_registration_pending client=%s name=%s ip=%s time=%d',
                $clientId,
                $response['client_name'] ?? '',
                $clientIp ?? '-',
                $this->now
            ));
        }
        return $response;
    }

    /**
     * 活跃治理：换票成功后刷新 last_seen_at；动态客户端随活跃授权续期 TTL。
     *
     * @param array $clientRow client 表行（含 client_id / registration_origin）
     */
    public function markActive(array $clientRow): void
    {
        $clientId = (string) ($clientRow['client_id'] ?? '');
        if ($clientId === '') {
            return;
        }
        $updates = [
            'last_seen_at' => $this->now,
            'updated_at' => $this->now,
            'updated_by' => 'oauth-token',
        ];
        if (($clientRow['registration_origin'] ?? '') === 'dynamic') {
            // 活跃授权存在 → TTL 续期（设计：仅回收无活跃授权的过期客户端）
            $updates['expires_at'] = $this->now + self::INACTIVE_TTL;
        }
        try {
            app::get('mcpapi')->model('client')->update($updates, ['client_id' => $clientId]);
        } catch (Throwable $e) {
            // 治理字段更新失败不影响换票主链路
        }
    }

    /**
     * 回收无活跃授权的过期动态客户端（容量触顶优先回收，也可定时调用）。
     *
     * 注意：base_db_model::filter 仅支持等值过滤，范围条件必须用原生 SQL。
     *
     * @return int 回收数量
     */
    public function purgeExpired(): int
    {
        $db = kernel::database();
        $clientTable = $db->prefix . 'mcpapi_client';
        $refreshTable = $db->prefix . 'mcpapi_oauth_refresh_token';
        $now = (int) $this->now;
        try {
            $rows = $db->select(
                "SELECT `client_id` FROM `{$clientTable}` "
                . "WHERE `registration_origin` = 'dynamic' AND `expires_at` > 0 AND `expires_at` < {$now}"
            );
        } catch (Throwable $e) {
            return 0;
        }
        $count = 0;
        foreach ((array) $rows as $row) {
            $clientId = is_array($row) ? (string) ($row['client_id'] ?? '') : '';
            if ($clientId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $clientId)) {
                continue;
            }
            try {
                // 有活跃授权（未过期 active refresh）的客户端续期而非回收
                $active = $db->select(
                    "SELECT `token_hash` FROM `{$refreshTable}` "
                    . "WHERE `client_id` = '{$clientId}' AND `status` = 'active' AND `expires_at` > {$now} LIMIT 1"
                );
                if ((array) $active !== []) {
                    $renew = $now + self::INACTIVE_TTL;
                    $db->exec("UPDATE `{$clientTable}` SET `expires_at` = {$renew} WHERE `client_id` = '{$clientId}'");
                    continue;
                }
                if ($db->exec("DELETE FROM `{$clientTable}` WHERE `client_id` = '{$clientId}'")) {
                    $count++;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
        return $count;
    }

    /**
     * redirect_uri 校验：https、loopback (127.0.0.1/::1)、私有 scheme（非 http/https）三种形态放行。
     *
     * @param string[] $redirectUris
     * @return string[]
     */
    public function sanitizeRedirectUris(array $redirectUris): array
    {
        $valid = [];
        foreach ($redirectUris as $uri) {
            if (!is_string($uri) || $uri === '' || strlen($uri) > 1024) {
                continue;
            }
            $parts = parse_url($uri);
            if ($parts === false || !isset($parts['scheme']) || isset($parts['fragment'])) {
                continue;
            }
            $scheme = strtolower($parts['scheme']);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $allowed = false;
            if ($scheme === 'https') {
                $allowed = true;
            } elseif ($scheme === 'http' && in_array($host, ['127.0.0.1', '[::1]', '::1', 'localhost'], true)) {
                $allowed = true;
            } elseif ($scheme !== 'http') {
                // 私有 scheme（如 workbuddy://）—— WorkBuddy 连接器回调形态
                $allowed = preg_match('/^[a-z][a-z0-9+.-]*$/i', $scheme) === 1;
            }
            if ($allowed) {
                $valid[] = $uri;
            }
        }
        return array_values(array_unique($valid));
    }
}
