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
 * Access Token 签发：RS256 JWT，claims 与技术设计文档 V3.1 一致。
 *
 * 权限不进 token：token 只带 scope；每次 API 调用由 guard 实时收敛 owner 权限
 * （权限点变化即时生效，token_version 提升时通过 ver 校验吊销存量 token）。
 */
final class mcpapi_auth_oauth_tokens
{
    public const ACCESS_LIFETIME = 1800; // 30 分钟（WorkBuddy 自动续期）
    public const TOKEN_VERSION = 1;

    private mcpapi_auth_oauth_askeys $keyService;
    private mcpapi_auth_owner $owner;
    private string $issuer;
    private int $accessLifetime;
    /** @var callable(int $userId): int 用户当前 token_version（改密/吊销会话提升即失效） */
    private $tokenVersionProvider;

    public function __construct(
        ?mcpapi_auth_oauth_askeys $keyService = null,
        ?mcpapi_auth_owner $owner = null,
        ?string $issuer = null,
        ?int $accessLifetime = null,
        ?callable $tokenVersionProvider = null
    ) {
        $this->keyService = $keyService ?? new mcpapi_auth_oauth_askeys();
        $this->owner = $owner ?? new mcpapi_auth_owner();
        $this->issuer = $issuer ?? $this->defaultIssuer();
        $this->accessLifetime = $accessLifetime ?? self::ACCESS_LIFETIME;
        $this->tokenVersionProvider = $tokenVersionProvider ?? static function (int $userId): int {
            $user = app::get('desktop')->model('users')->dump($userId, 'token_version');
            $version = is_array($user) ? (int) ($user['token_version'] ?? 1) : 1;
            return $version >= 1 ? $version : 1;
        };
    }

    /**
     * 为用户 + client 签发 access token（authorization_code / refresh_token 共用）。
     *
     * @param string[] $scopes
     * @return array{access_token:string, expires_in:int, token_type:string, scope:string}
     */
    public function issueAccessToken(string $clientId, int $userId, array $scopes, ?int $now = null): array
    {
        $now = $now ?? time();
        $expiresAt = $now + $this->accessLifetime;
        $clientScopes = $scopes;
        // ver = 用户当前 token_version：改密/吊销会话提升版本后，存量 access 自然失效
        $tokenVersion = ($this->tokenVersionProvider)($userId);

        $claims = [
            'iss' => $this->issuer,
            'sub' => (string) $userId,
            'aud' => 'mcp-api',
            'client_id' => $clientId,
            'scope' => implode(' ', $clientScopes),
            'ver' => $tokenVersion >= 1 ? $tokenVersion : self::TOKEN_VERSION,
            'iat' => $now,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
            'type' => 'access',
        ];

        $token = $this->keyService->sign($claims);
        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessLifetime,
            'scope' => implode(' ', $clientScopes),
        ];
    }

    /**
     * 完整 token 响应（access + refresh）。
     *
     * @param string[] $scopes
     */
    public function issueTokenPair(
        string $clientId,
        int $userId,
        array $scopes,
        ?mcpapi_auth_oauth_refreshtokens $refreshService = null,
        ?int $now = null
    ): array {
        $access = $this->issueAccessToken($clientId, $userId, $scopes, $now);
        $refreshService = $refreshService ?? new mcpapi_auth_oauth_refreshtokens();
        $refresh = $refreshService->issue($userId, $clientId, $scopes);
        return array_merge($access, [
            'refresh_token' => $refresh['refresh_token'],
            'refresh_expires_in' => mcpapi_auth_oauth_refreshtokens::LIFETIME,
        ]);
    }

    public function issuer(): string
    {
        return $this->issuer;
    }

    private function defaultIssuer(): string
    {
        return rtrim((string) kernel::base_url(1), '/') . '/index.php/mcp-api/oauth';
    }
}
