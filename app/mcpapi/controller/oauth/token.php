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
 * OAuth token 端点（POST /mcp-api/oauth/token）。
 *
 * grant_type=authorization_code — 授权码 + PKCE 换 access/refresh token
 * grant_type=refresh_token     — refresh token 轮换
 *
 * 客户端认证：公共客户端（token_endpoint_auth=none）凭 client_id + PKCE；
 * 机密客户端凭 Basic 或 POST client_secret（password_verify 比对）。
 */
final class mcpapi_ctl_oauth_token
{
    private $clientFinder;
    private mcpapi_auth_oauth_codes $codes;
    private mcpapi_auth_oauth_refreshtokens $refreshTokens;
    private mcpapi_auth_oauth_tokens $tokens;
    private mcpapi_auth_oauth_consents $consents;
    private mcpapi_auth_oauth_registration $registration;
    private $pendingClientFinder;
    /** @var callable|null 重放安全事件钩子（注入测试时可关闭） */
    private $onReplay;
    private ?mcpapi_auth_owner $owner = null;

    public function __construct(
        ?callable $clientFinder = null,
        ?mcpapi_auth_oauth_codes $codes = null,
        ?mcpapi_auth_oauth_refreshtokens $refreshTokens = null,
        ?mcpapi_auth_oauth_tokens $tokens = null,
        ?mcpapi_auth_oauth_consents $consents = null,
        ?mcpapi_auth_oauth_registration $registration = null,
        ?callable $onReplay = null,
        ?callable $pendingClientFinder = null
    ) {
        $this->clientFinder = $clientFinder ?? static function (string $clientId): ?array {
            $rows = app::get('mcpapi')->model('client')->getList('*', ['client_id' => $clientId], 0, 1);
            $row = $rows[0] ?? null;
            if (!is_array($row)) {
                return null;
            }
            $enabled = $row['enabled'] ?? false;
            $revokedAt = $row['revoked_at'] ?? null;
            $expiresAt = $row['expires_at'] ?? null;
            // dbeav enum 列返回字符串（'true'/'false'），宽松匹配布尔与字符串口径
            $active = in_array($enabled, [true, 1, '1', 'true'], true)
                && ($revokedAt === null || (int) $revokedAt === 0)
                && ($expiresAt === null || (int) $expiresAt > time());
            return $active ? $row : null;
        };
        $this->codes = $codes ?? new mcpapi_auth_oauth_codes();
        $this->refreshTokens = $refreshTokens ?? new mcpapi_auth_oauth_refreshtokens(null, null, null, null, null, $onReplay ?? $this->defaultReplayHook());
        $this->tokens = $tokens ?? new mcpapi_auth_oauth_tokens();
        $this->consents = $consents ?? new mcpapi_auth_oauth_consents();
        $this->registration = $registration ?? new mcpapi_auth_oauth_registration();
        $this->onReplay = $onReplay;
        $this->pendingClientFinder = $pendingClientFinder ?? self::defaultPendingFinder();
    }

    private static function defaultPendingFinder(): callable
    {
        return static function (string $clientId): ?array {
            if ($clientId === '') {
                return null;
            }
            try {
                $rows = app::get('mcpapi')->model('client')->getList('*', ['client_id' => $clientId], 0, 1);
                $row = is_array($rows[0] ?? null) ? $rows[0] : null;
                if ($row === null) {
                    return null;
                }
                $enabled = in_array($row['enabled'] ?? null, [true, 1, '1', 'true'], true);
                $revoked = (int) ($row['revoked_at'] ?? 0) > 0;
                return (!$enabled && !$revoked) ? $row : null;
            } catch (Throwable $e) {
                return null;
            }
        };
    }

    public function create(array $request = []): mcpapi_http_response
    {
        try {
            $body = $this->process($request);
        } catch (mcpapi_auth_oauth_error $exception) {
            return $exception->toResponse();
        }

        // 换票成功后的治理动作：活跃客户端续期 + 过期数据懒清理（失败不影响响应；测试环境跳过）
        if (!defined('TESTSUITE')) {
            if (is_array($this->authenticatedClient)) {
                $this->registration->markActive($this->authenticatedClient);
            }
            try {
                $this->codes->purgeExpired();
                $this->refreshTokens->purgeExpired();
            } catch (Throwable $e) {
                // 清理失败不阻断换票
            }
        }

        return mcpapi_http_response::fromJson(200, $body, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    /** @var array|null 本次请求通过认证的 client 行 */
    private ?array $authenticatedClient = null;

    private function process(array $request): array
    {
        $request = $this->mergedRequest($request);
        $grantType = (string) ($request['grant_type'] ?? '');
        if ($grantType !== 'authorization_code' && $grantType !== 'refresh_token' && $grantType !== 'client_credentials') {
            throw new mcpapi_auth_oauth_error('unsupported_grant_type');
        }

        [$clientId, $clientSecret] = $this->authenticateClient($request);
        $client = $this->loadClient($clientId);
        $this->assertClientAuth($client, $clientSecret);
        $this->authenticatedClient = $client;

        if ($grantType === 'client_credentials') {
            return $this->grantClientCredentials($clientId);
        }
        if ($grantType === 'authorization_code') {
            return $this->grantAuthorizationCode($request, $clientId);
        }
        return $this->grantRefreshToken($request, $clientId);
    }

    /**
     * 客户端凭证分支（Agent 型机密客户端专用）：
     * ① 客户端认证（已由 assertClientAuth 完成）② 分支守卫（kind=agent）
     * ③ 服务账号校验（account_type=service、状态正常、MCP 接入开启）④ 签发（无 refresh_token）。
     * 令牌声明与操作员型同构（sub=服务账号），MCP 服务端零改动。
     */
    private function grantClientCredentials(string $clientId): array
    {
        $client = $this->authenticatedClient ?? [];
        if (($client['client_kind'] ?? 'operator') !== 'agent') {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Client kind mismatch for client_credentials grant');
        }

        $ownerUserId = (int) ($client['owner_user_id'] ?? 0);
        if ($ownerUserId < 1) {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Agent client has no bound service account');
        }

        // 服务账号校验：存在、account_type=service、状态正常、MCP 接入开启
        $this->owner ??= new mcpapi_auth_owner();
        $ownerAuth = $this->owner->resolve($ownerUserId);
        if (($ownerAuth['account_type'] ?? 'human') !== 'service') {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Bound account is not a service account');
        }

        // scope：注册时净化落库的域
        $scopes = is_array($client['scopes'] ?? null)
            ? array_values(array_filter($client['scopes'], 'is_string'))
            : [];

        return $this->tokens->issueAccessToken($clientId, $ownerUserId, $scopes);
    }

    private function grantAuthorizationCode(array $request, string $clientId): array
    {
        $code = (string) ($request['code'] ?? '');
        $redirectUri = (string) ($request['redirect_uri'] ?? '');
        $codeVerifier = isset($request['code_verifier']) ? (string) $request['code_verifier'] : null;
        if ($code === '' || $redirectUri === '') {
            throw new mcpapi_auth_oauth_error('invalid_request');
        }

        $granted = $this->codes->consume($code, $clientId, $redirectUri, $codeVerifier);

        // 同意记录兜底校验（授权码签发时必然已 grant；此处防同意被撤销后 code 仍在有效期窗口内）
        if (!$this->consents->hasValidConsent($granted['user_id'], $clientId, $granted['scopes'])) {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Consent has been revoked');
        }

        return $this->tokens->issueTokenPair($clientId, $granted['user_id'], $granted['scopes'], $this->refreshTokens);
    }

    private function grantRefreshToken(array $request, string $clientId): array
    {
        $refreshToken = (string) ($request['refresh_token'] ?? '');
        if ($refreshToken === '') {
            throw new mcpapi_auth_oauth_error('invalid_request');
        }

        $rotated = $this->refreshTokens->rotate($refreshToken, $clientId);

        if (!$this->consents->hasValidConsent($rotated['user_id'], $clientId, $rotated['scopes'])) {
            // 同意已撤销：refresh 链一并作废
            $this->refreshTokens->revokeAll($rotated['user_id'], $clientId);
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Consent has been revoked');
        }

        $access = $this->tokens->issueAccessToken($clientId, $rotated['user_id'], $rotated['scopes']);
        return array_merge($access, [
            'refresh_token' => $rotated['refresh_token'],
            'refresh_expires_in' => mcpapi_auth_oauth_refreshtokens::LIFETIME,
        ]);
    }

    /**
     * 客户端认证：Authorization Basic 优先，POST client_secret 兜底。
     *
     * @return array{0:string,1:string} [client_id, client_secret（可能为空）]
     */
    private function authenticateClient(array $request): array
    {
        $authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^Basic\s+(.+)$/i', $authorization, $matches)) {
            $decoded = base64_decode($matches[1], true);
            if (is_string($decoded) && str_contains($decoded, ':')) {
                [$clientId, $clientSecret] = explode(':', $decoded, 2);
                return [$clientId, $clientSecret];
            }
        }
        $clientId = (string) ($request['client_id'] ?? '');
        $clientSecret = (string) ($request['client_secret'] ?? '');
        return [$clientId, $clientSecret];
    }

    private function loadClient(string $clientId): array
    {
        if ($clientId === '') {
            throw new mcpapi_auth_oauth_error('invalid_client', 'client authentication failed', 401);
        }
        $client = ($this->clientFinder)($clientId);
        if (!is_array($client)) {
            if (is_array(($this->pendingClientFinder)($clientId))) {
                throw new mcpapi_auth_oauth_error('access_denied', 'Client is pending administrator approval', 403);
            }
            throw new mcpapi_auth_oauth_error('invalid_client', 'client authentication failed', 401);
        }
        return $client;
    }

    private function assertClientAuth(array $client, string $clientSecret): void
    {
        $authMethod = (string) ($client['token_endpoint_auth'] ?? 'client_secret_basic');
        if ($authMethod === 'none') {
            // 公共客户端：不允许携带 secret（携带了反而拒绝，防降级攻击）
            if ($clientSecret !== '') {
                throw new mcpapi_auth_oauth_error('invalid_client', 'Public client must not authenticate with a secret', 401);
            }
            return;
        }

        $hash = $client['client_secret_hash'] ?? null;
        if (!is_string($hash) || $hash === '' || $clientSecret === '' || !password_verify($clientSecret, $hash)) {
            throw new mcpapi_auth_oauth_error('invalid_client', 'client authentication failed', 401);
        }
    }

    /**
     * refresh 重放安全事件：提升该用户 token_version（连带吊销桌面端存量 token）并记录安全日志。
     */
    private function defaultReplayHook(): callable
    {
        return function (int $userId, string $clientId): void {
            try {
                $users = app::get('desktop')->model('users');
                $user = $users->dump($userId, 'token_version');
                $users->update(
                    ['token_version' => (int) ($user['token_version'] ?? 1) + 1],
                    ['user_id' => $userId]
                );
            } catch (Throwable $e) {
                // token_version 提升失败不阻断 invalid_grant 响应
            }
            error_log(sprintf(
                'MCPAPI_SECURITY refresh_token_replay user=%d client=%s time=%d',
                $userId,
                $clientId,
                time()
            ));
        };
    }

    /**
     * token 端点按 RFC 6749 §4.1.3 也接受 application/x-www-form-urlencoded。
     */
    private function mergedRequest(array $request): array
    {
        if ($request === [] && class_exists('desktop_auth_request')) {
            $request = desktop_auth_request::jsonBody();
        }
        if (is_array($_POST ?? null) && $_POST !== []) {
            foreach ($_POST as $key => $value) {
                if (is_string($value)) {
                    $request[$key] = $value;
                }
            }
        }
        return $request;
    }
}
