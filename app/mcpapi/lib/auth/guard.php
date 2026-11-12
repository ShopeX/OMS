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

final class mcpapi_auth_guard
{
    private $verifier;
    private $clientVerifier;
    private $basicVerifier;
    private $browserJwtVerifier;
    private $bearerVerifier;
    private $activeClientFinder;
    private mcpapi_auth_key_jwks $clientJwks;
    private mcpapi_auth_owner $owner;
    private bool $refreshOwnerAuthorization;
    /** @var string|null null = 惰性推导默认 issuer；'' = 关闭校验（测试） */
    private ?string $expectedIssuer;
    private mcpapi_auth_oauth_scopes $oauthScopes;

    public function __construct(
        callable $verifier,
        ?callable $clientVerifier = null,
        ?mcpapi_auth_key_jwks $clientJwks = null,
        ?callable $basicVerifier = null,
        ?mcpapi_auth_owner $owner = null,
        ?callable $browserJwtVerifier = null,
        ?callable $activeClientFinder = null,
        ?callable $bearerVerifier = null,
        ?string $expectedIssuer = null,
        ?mcpapi_auth_oauth_scopes $oauthScopes = null
    )
    {
        $this->verifier = Closure::fromCallable($verifier);
        $this->clientJwks = $clientJwks ?? new mcpapi_auth_key_jwks();
        $this->clientVerifier = $clientVerifier === null
            ? function (string $token): array {
                return (new mcpapi_auth_token_verifier(
                    null,
                    'oms-mcp-client',
                    'oms-mcp-gateway',
                    'OMS_MCP_CLIENT_JWT_KEY'
                ))->verifyClientWithJwks($token, $this->clientJwks);
            }
            : Closure::fromCallable($clientVerifier);
        $this->refreshOwnerAuthorization = $basicVerifier === null || $owner !== null;
        $this->basicVerifier = $basicVerifier === null
            ? static function (string $clientId, string $clientSecret): ?mcpapi_auth_client_record {
                return (new mcpapi_auth_client_database())->verify($clientId, $clientSecret);
            }
            : Closure::fromCallable($basicVerifier);
        $this->owner = $owner ?? new mcpapi_auth_owner();
        $this->browserJwtVerifier = $browserJwtVerifier === null
            ? static function (string $token): array {
                return desktop_auth_jwt::decodeWithValidation($token, true);
            }
            : Closure::fromCallable($browserJwtVerifier);
        $this->activeClientFinder = $activeClientFinder === null
            ? static function (string $clientId): ?array {
                return (new mcpapi_auth_client_manager())->findActiveClientById($clientId);
            }
            : Closure::fromCallable($activeClientFinder);
        $this->bearerVerifier = $bearerVerifier === null
            ? static function (string $token): array {
                return (new mcpapi_auth_oauth_askeys())->verify($token);
            }
            : Closure::fromCallable($bearerVerifier);
        $this->expectedIssuer = $expectedIssuer;
        $this->oauthScopes = $oauthScopes ?? new mcpapi_auth_oauth_scopes();
    }

    public function authenticate(string $authorization, string $requestId): mcpapi_auth_context
    {
        $claims = $this->verifiedClaims($authorization);

        if (!$this->hasMcpAudience($claims['aud'] ?? null)) {
            throw new mcpapi_auth_exception();
        }

        if (!isset($claims['exp']) || !is_int($claims['exp'])) {
            throw new mcpapi_auth_exception();
        }

        $expiresAt = $claims['exp'];
        if ($expiresAt <= time()) {
            throw new mcpapi_auth_exception();
        }

        $clientId = $this->requiredClaim($claims, 'client_id');
        $externalSubject = $this->requiredClaim($claims, 'external_subject');
        $tenantId = $this->requiredClaim($claims, 'tenant_id');
        $omsBaseUrl = $this->requiredClaim($claims, 'oms_base_url');
        $omsSubjectId = $this->requiredIntegerClaim($claims, 'oms_subject_id');
        $omsRoleIds = $claims['oms_role_ids'] ?? null;
        if (!is_array($omsRoleIds)) {
            throw new mcpapi_auth_exception();
        }
        $omsPermissions = $claims['oms_permissions'] ?? null;
        if (!is_array($omsPermissions)) {
            throw new mcpapi_auth_exception();
        }
        $scopes = $claims['scope'] ?? [];
        if (is_string($scopes)) {
            $scopes = preg_split('/\s+/', trim($scopes), -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($scopes)) {
            throw new mcpapi_auth_exception();
        }
        $isSuper = $claims['is_super'] ?? false;
        if (!is_bool($isSuper)) {
            throw new mcpapi_auth_exception();
        }

        return new mcpapi_auth_context(
            $requestId,
            $clientId,
            $externalSubject,
            $tenantId,
            $omsBaseUrl,
            $omsSubjectId,
            $omsRoleIds,
            $omsPermissions,
            $scopes,
            $expiresAt,
            $isSuper
        );
    }

    public function authenticateInternal(string $authorization, string $requestId): mcpapi_gateway_identity
    {
        unset($requestId);
        $claims = $this->verifiedClaims($authorization, $this->clientVerifier);
        if (!$this->hasClientAudience($claims['aud'] ?? null)) {
            throw new mcpapi_auth_exception();
        }

        if (!isset($claims['exp']) || !is_int($claims['exp']) || $claims['exp'] <= time()) {
            throw new mcpapi_auth_exception();
        }

        return new mcpapi_gateway_identity(
            $this->requiredClaim($claims, 'client_id'),
            $this->requiredClaim($claims, 'sub')
        );
    }

    public function authenticateBasic(string $authorization, string $requestId): mcpapi_auth_context
    {
        if (!preg_match('/^Basic\s+(.+)$/i', $authorization, $matches)) {
            throw new mcpapi_auth_exception();
        }
        $decoded = base64_decode($matches[1], true);
        if (!is_string($decoded) || !str_contains($decoded, ':')) {
            throw new mcpapi_auth_exception();
        }
        [$clientId, $clientSecret] = explode(':', $decoded, 2);
        $record = ($this->basicVerifier)($clientId, $clientSecret);
        if (!$record instanceof mcpapi_auth_client_record) {
            throw new mcpapi_auth_exception();
        }

        $ownerUserId = $record->ownerUserId();
        if ($ownerUserId < 1 || !$this->refreshOwnerAuthorization) {
            return new mcpapi_auth_context(
                $requestId,
                $record->clientId(),
                $record->clientId(),
                $record->tenantId(),
                $record->omsBaseUrl(),
                $record->omsSubjectId(),
                $record->omsRoleIds(),
                $record->omsPermissions(),
                $record->scopes(),
                $record->expiresAt(),
                false
            );
        }

        $authorization = $this->owner->resolve($ownerUserId);
        $permissions = $authorization['oms_permissions'];
        $scopes = $this->owner->limitScopes($record->scopes(), $permissions, $authorization['is_super']);

        return new mcpapi_auth_context(
            $requestId,
            $record->clientId(),
            $record->clientId(),
            $record->tenantId(),
            $record->omsBaseUrl(),
            $authorization['oms_subject_id'],
            $authorization['oms_role_ids'],
            $permissions,
            $scopes,
            $record->expiresAt(),
            $authorization['is_super']
        );
    }

    /**
     * OAuth Bearer 认证（Client-Mode=bearer）：验 AS 签发的 access token，
     * 校验 client 活性 + token_version 终审后实时收敛 owner 权限。
     */
    public function authenticateBearer(string $authorization, string $requestId): mcpapi_auth_context
    {
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            throw new mcpapi_auth_exception();
        }

        $claims = ($this->bearerVerifier)($matches[1]);

        if (($claims['aud'] ?? null) !== 'mcp-api' || ($claims['type'] ?? null) !== 'access') {
            throw new mcpapi_auth_exception();
        }
        // iss 必须与本实例 AS 元数据一致（跨 AS 重放防护）
        if (($claims['iss'] ?? null) !== $this->resolveExpectedIssuer()) {
            throw new mcpapi_auth_exception();
        }
        $expiresAt = $claims['exp'] ?? null;
        if (!is_int($expiresAt) || $expiresAt <= time()) {
            throw new mcpapi_auth_exception();
        }
        $ver = $claims['ver'] ?? null;
        if (!is_int($ver) || $ver < 1) {
            throw new mcpapi_auth_exception();
        }
        $clientId = $claims['client_id'] ?? null;
        if (!is_string($clientId) || $clientId === '') {
            throw new mcpapi_auth_exception();
        }
        $userId = $claims['sub'] ?? null;
        if (!is_string($userId) || preg_match('/^[1-9][0-9]*$/', $userId) !== 1) {
            throw new mcpapi_auth_exception();
        }
        $userId = (int) $userId;
        $scopes = $claims['scope'] ?? null;
        if (is_string($scopes)) {
            $scopes = preg_split('/\s+/', trim($scopes), -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($scopes)) {
            throw new mcpapi_auth_exception();
        }

        $row = ($this->activeClientFinder)($clientId);
        if (!is_array($row)) {
            throw new mcpapi_auth_exception();
        }

        $tenantId = (string) ($row['tenant_id'] ?? '');
        $omsBaseUrl = (string) ($row['oms_base_url'] ?? '');
        if ($tenantId === '' || $omsBaseUrl === '') {
            throw new mcpapi_auth_exception();
        }

        // scope ≤ 应用能力上限（存量空 scopes 客户端视为不限）；超管跳过收敛
        $clientScopes = is_array($row['scopes'] ?? null)
            ? array_values(array_filter($row['scopes'], 'is_string'))
            : [];

        // token_version 终审：低于用户当前版本 = 改密/吊销会话，立即失效
        if ($this->owner->tokenVersion($userId) > $ver) {
            throw new mcpapi_auth_exception('Forbidden', 403);
        }

        if (!$this->refreshOwnerAuthorization) {
            $contextScopes = $clientScopes === []
                ? $scopes
                : array_values(array_intersect($scopes, $clientScopes));
            return new mcpapi_auth_context(
                $requestId,
                $clientId,
                (string) $userId, // 审计 subject = token sub（用户维度）
                $tenantId,
                $omsBaseUrl,
                $userId,
                [],
                [],
                $contextScopes,
                $expiresAt,
                false
            );
        }

        $ownerAuth = $this->owner->resolve($userId);
        if ($ownerAuth['is_super']) {
            // 超管：scope 与权限均不收敛（权限即全部）
            $contextScopes = $scopes;
            $permissions = $ownerAuth['oms_permissions'];
        } else {
            $contextScopes = $clientScopes === []
                ? $scopes
                : array_values(array_intersect($scopes, $clientScopes));
            // 跟着角色走：token 权限面 = 实时角色权限 ∩ 已授权 scope 展开的权限点
            $permissions = $this->oauthScopes->convergePermissions($contextScopes, $ownerAuth['oms_permissions']);
        }

        return new mcpapi_auth_context(
            $requestId,
            $clientId,
            (string) $userId, // 审计 subject = token sub（用户维度）
            $tenantId,
            $omsBaseUrl,
            $ownerAuth['oms_subject_id'],
            $ownerAuth['oms_role_ids'],
            $permissions,
            $contextScopes,
            $expiresAt,
            $ownerAuth['is_super']
        );
    }

    /**
     * 与 mcpapi_auth_oauth_tokens::defaultIssuer() 保持同一推导口径。
     */
    private function resolveExpectedIssuer(): string
    {
        return $this->expectedIssuer
            ?? rtrim((string) kernel::base_url(1), '/') . '/index.php/mcp-api/oauth';
    }

    /**
     * OMS 内嵌 AI Assistant（Client-Mode=jwt）：先验 client_id，再验 Bearer JWT，并绑定 owner。
     */
    public function authenticateBrowserJwt(string $clientId, string $authorization, string $requestId): mcpapi_auth_context
    {
        $clientId = trim($clientId);
        if ($clientId === '' || !preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            throw new mcpapi_auth_exception();
        }

        $row = ($this->activeClientFinder)($clientId);
        if (!is_array($row)) {
            throw new mcpapi_auth_exception();
        }

        try {
            $validated = ($this->browserJwtVerifier)($matches[1]);
        } catch (Throwable) {
            throw new mcpapi_auth_exception();
        }
        if (!is_array($validated)) {
            throw new mcpapi_auth_exception();
        }

        $userId = (int) ($validated['user_id'] ?? 0);
        $type = (string) ($validated['type'] ?? '');
        if ($userId < 1 || !in_array($type, ['access', 'ai_assistant'], true)) {
            throw new mcpapi_auth_exception();
        }

        $ownerUserId = (int) ($row['owner_user_id'] ?? 0);
        if ($ownerUserId < 1 || $userId !== $ownerUserId) {
            throw new mcpapi_auth_exception();
        }

        $tenantId = (string) ($row['tenant_id'] ?? '');
        $omsBaseUrl = (string) ($row['oms_base_url'] ?? '');
        $scopes = is_array($row['scopes'] ?? null) ? $row['scopes'] : [];
        $expiresAt = isset($row['expires_at']) && $row['expires_at'] !== null && $row['expires_at'] !== ''
            ? (int) $row['expires_at']
            : (int) ($validated['exp'] ?? (time() + 300));
        if ($tenantId === '' || $omsBaseUrl === '') {
            throw new mcpapi_auth_exception();
        }

        if (!$this->refreshOwnerAuthorization) {
            return new mcpapi_auth_context(
                $requestId,
                $clientId,
                $clientId,
                $tenantId,
                $omsBaseUrl,
                (int) ($row['oms_subject_id'] ?? $ownerUserId),
                is_array($row['oms_role_ids'] ?? null) ? $row['oms_role_ids'] : [],
                is_array($row['oms_permissions'] ?? null) ? $row['oms_permissions'] : [],
                $scopes,
                $expiresAt,
                false
            );
        }

        $ownerAuth = $this->owner->resolve($ownerUserId);
        $permissions = $ownerAuth['oms_permissions'];
        $limitedScopes = $this->owner->limitScopes($scopes, $permissions, $ownerAuth['is_super']);

        return new mcpapi_auth_context(
            $requestId,
            $clientId,
            $clientId,
            $tenantId,
            $omsBaseUrl,
            $ownerAuth['oms_subject_id'],
            $ownerAuth['oms_role_ids'],
            $permissions,
            $limitedScopes,
            $expiresAt,
            $ownerAuth['is_super']
        );
    }

    private function verifiedClaims(string $authorization, ?callable $verifier = null): array
    {
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            throw new mcpapi_auth_exception();
        }

        try {
            $claims = ($verifier ?? $this->verifier)($matches[1]);
        } catch (Throwable) {
            throw new mcpapi_auth_exception();
        }

        if (!is_array($claims)) {
            throw new mcpapi_auth_exception();
        }

        return $claims;
    }

    private function requiredClaim(array $claims, string $name): string
    {
        $value = $claims[$name] ?? null;
        if (!is_string($value) || $value === '') {
            throw new mcpapi_auth_exception();
        }
        return $value;
    }

    private function requiredIntegerClaim(array $claims, string $name): int
    {
        $value = $claims[$name] ?? null;
        if (!is_int($value) || $value < 1) {
            throw new mcpapi_auth_exception();
        }
        return $value;
    }

    private function hasMcpAudience($audience): bool
    {
        if (is_string($audience)) {
            return $audience === 'mcp-api';
        }
        return is_array($audience) && in_array('mcp-api', $audience, true) && !in_array('browser', $audience, true);
    }

    private function hasClientAudience($audience): bool
    {
        return $audience === 'oms-mcp-gateway';
    }
}
