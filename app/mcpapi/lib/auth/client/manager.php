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

class mcpapi_auth_client_manager
{
    private $clientLoader;
    private $keyLoader;
    private $clientSaver;
    private $clientUpdater;
    private $keySaver;
    private $keyUpdater;
    private $ownerNameLoader;
    private $transactionBegin;
    private $transactionCommit;
    private $transactionRollback;
    private $ownerLock;
    private int $now;

    public function __construct(
        ?callable $clientLoader = null,
        ?callable $keyLoader = null,
        ?callable $clientSaver = null,
        ?callable $clientUpdater = null,
        ?callable $keySaver = null,
        ?callable $keyUpdater = null,
        ?callable $ownerNameLoader = null,
        ?int $now = null,
        ?callable $transactionBegin = null,
        ?callable $transactionCommit = null,
        ?callable $transactionRollback = null,
        ?callable $ownerLock = null
    ) {
        $this->clientLoader = $clientLoader ?? static function (int $ownerId): array {
            return app::get('mcpapi')->model('client')->getList('*', ['owner_user_id' => $ownerId], 0, -1);
        };
        $this->keyLoader = $keyLoader ?? static function (string $clientId): array {
            return app::get('mcpapi')->model('client_key')->getList('*', ['client_id' => $clientId], 0, -1);
        };
        $this->clientSaver = $clientSaver ?? static function (array $row): void {
            app::get('mcpapi')->model('client')->save($row);
        };
        $this->clientUpdater = $clientUpdater ?? static function (array $data, array $filter): void {
            app::get('mcpapi')->model('client')->update($data, $filter);
        };
        $this->keySaver = $keySaver ?? static function (array $row): void {
            app::get('mcpapi')->model('client_key')->save($row);
        };
        $this->keyUpdater = $keyUpdater ?? static function (array $data, array $filter): void {
            app::get('mcpapi')->model('client_key')->update($data, $filter);
        };
        $this->ownerNameLoader = $ownerNameLoader ?? static function (int $ownerId): string {
            $row = app::get('desktop')->model('users')->dump($ownerId, 'name');
            return is_array($row) ? (string) ($row['name'] ?? '') : '';
        };
        $this->now = $now ?? time();
        $this->transactionBegin = $transactionBegin ?? static function (): void {
            kernel::database()->beginTransaction();
        };
        $this->transactionCommit = $transactionCommit ?? static function (): void {
            kernel::database()->commit();
        };
        $this->transactionRollback = $transactionRollback ?? static function (): void {
            kernel::database()->rollBack();
        };
        $this->ownerLock = $ownerLock ?? static function (int $ownerId, callable $operation): array {
            $lockName = 'mcpapi_client_owner_' . $ownerId;
            $database = kernel::database();
            $lock = $database->selectrow("SELECT GET_LOCK('{$lockName}', 10) AS lock_status");
            if ((int) ($lock['lock_status'] ?? 0) !== 1) {
                throw new RuntimeException('Unable to lock client owner', 503);
            }

            try {
                return $operation();
            } finally {
                $database->exec("SELECT RELEASE_LOCK('{$lockName}')");
            }
        };
    }

    public function listForOwner(int $ownerUserId): array
    {
        $this->assertOwner($ownerUserId);
        $rows = ($this->clientLoader)($ownerUserId);
        $row = $this->currentOwnerRow($rows, $ownerUserId);
        if ($row === null) {
            return [];
        }

        return [$this->view($row, $ownerUserId)];
    }

    public function createForOwner(int $ownerUserId, string $name, ?int $expiresAt = null): array
    {
        $this->assertOwner($ownerUserId);
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Client name is required', 422);
        }
        $expiresAt = $this->validateExpiration($expiresAt);

        $clientId = 'mcp_' . bin2hex(random_bytes(16));
        $clientSecret = $this->generateSecret();
        $timestamp = $this->now;
        $row = [
            'client_id' => $clientId,
            'client_secret_hash' => password_hash($clientSecret, PASSWORD_DEFAULT),
            'owner_user_id' => $ownerUserId,
            'name' => $name,
            'tenant_id' => 'desktop-user-' . $ownerUserId,
            'oms_base_url' => $this->baseUrl(),
            'oms_subject_id' => $ownerUserId,
            'oms_role_ids' => [],
            'oms_permissions' => [],
            'scopes' => [],
            // approval 模式：操作员申请 → enabled=false 待管理员审批；open 模式直接生效
            'enabled' => (defined('TESTSUITE') ? 'open' : mcpapi_auth_oauth_registration::currentMode()) === 'approval' ? 'false' : 'true',
            'revoked_at' => null,
            'expires_at' => $expiresAt,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'created_by' => (string) $ownerUserId,
            'updated_by' => (string) $ownerUserId,
            'revoked_reason' => null,
            'last_used_at' => null,
        ];
        return ($this->ownerLock)($ownerUserId, function () use ($row, $clientSecret, $ownerUserId): array {
            return $this->transaction(function () use ($row, $clientSecret, $ownerUserId): array {
                if (($this->clientLoader)($ownerUserId)) {
                    throw new InvalidArgumentException('Client already exists for this owner', 409);
                }
                ($this->clientSaver)($row);
                return array_merge($this->view($row, $ownerUserId, null, false), ['client_secret' => $clientSecret]);
            });
        });
    }

    public function rotateForOwner(int $ownerUserId, string $clientId): array
    {
        $row = $this->ownedClient($ownerUserId, $clientId);
        $clientId = (string) ($row['client_id'] ?? $clientId);

        return $this->transaction(function () use ($row, $ownerUserId, $clientId): array {
            $replacementClientId = 'mcp_' . bin2hex(random_bytes(16));
            $clientSecret = $this->generateSecret();
            ($this->clientUpdater)(
            [
                'client_id' => $replacementClientId,
                'client_secret_hash' => password_hash($clientSecret, PASSWORD_DEFAULT),
                'enabled' => true,
                'revoked_at' => null,
                'revoked_reason' => null,
                'expires_at' => $row['expires_at'] ?? null,
                'updated_at' => $this->now,
                'updated_by' => (string) $ownerUserId,
            ],
                ['client_id' => $clientId, 'owner_user_id' => $ownerUserId]
            );
            $replacementRow = array_merge($row, [
                'client_id' => $replacementClientId,
                'enabled' => true,
                'revoked_at' => null,
                'revoked_reason' => null,
                'expires_at' => $row['expires_at'] ?? null,
                'updated_at' => $this->now,
                'updated_by' => (string) $ownerUserId,
            ]);
            return array_merge($this->view($replacementRow, $ownerUserId, null, false), ['client_secret' => $clientSecret]);
        });
    }

    public function revokeForOwner(int $ownerUserId, string $clientId, string $reason = ''): array
    {
        $row = $this->ownedClient($ownerUserId, $clientId);
        $clientId = (string) ($row['client_id'] ?? $clientId);
        if ($this->isActive($row)) {
            return $this->transaction(function () use ($row, $ownerUserId, $clientId, $reason): array {
            $revokedAt = $this->now;
            ($this->clientUpdater)(
                [
                    'enabled' => false,
                    'revoked_at' => $revokedAt,
                    'revoked_reason' => trim($reason) ?: 'revoked',
                    'updated_at' => $revokedAt,
                    'updated_by' => (string) $ownerUserId,
                ],
                ['client_id' => $clientId, 'owner_user_id' => $ownerUserId]
            );
            $row = array_merge($row, ['enabled' => false, 'revoked_at' => $revokedAt]);
            return $this->view($row, $ownerUserId);
            });
        }

        return $this->view($row, $ownerUserId);
    }

    /**
     * AI Assistant iframe：返回当前 owner 的 active client（含明文 client_id，不含 Secret）。
     *
     * @return array{X-Shopex-OMS-Base-Url:string,X-Shopex-OMS-Client-Id:string}|null
     */
    public function assistantDownstreamAuthForOwner(int $ownerUserId): ?array
    {
        $this->assertOwner($ownerUserId);
        $rows = ($this->clientLoader)($ownerUserId);
        $row = $this->currentOwnerRow($rows, $ownerUserId);
        if ($row === null || !$this->isActive($row)) {
            return null;
        }

        $clientId = (string) ($row['client_id'] ?? '');
        if ($clientId === '') {
            return null;
        }

        return [
            'X-Shopex-OMS-Base-Url' => $this->normalizeAssistantBaseUrl($this->baseUrl()),
            'X-Shopex-OMS-Client-Id' => $clientId,
        ];
    }

    /**
     * 按 client_id 查找仍可用的 MCP client 行（enabled / 未吊销 / 未过期）。
     */
    public function findActiveClientById(string $clientId): ?array
    {
        if ($clientId === '') {
            return null;
        }
        $rows = app::get('mcpapi')->model('client')->getList('*', ['client_id' => $clientId], 0, 1);
        $row = $rows[0] ?? null;
        if (!is_array($row) || !$this->isActive($row)) {
            return null;
        }
        // 最近使用时间：节流更新（60 秒内不重复写），供管理端「最近使用」展示
        $lastUsed = (int) ($row['last_used_at'] ?? 0);
        if (time() - $lastUsed > 60) {
            try {
                ($this->clientUpdater)(['last_used_at' => time()], ['client_id' => $clientId]);
                $row['last_used_at'] = time();
            } catch (Throwable $e) {
                // 统计字段更新失败不影响鉴权
            }
        }
        return $row;
    }

    /**
     * 按客户端 ID 取原始行（不限状态），供待审批识别使用
     */
    public function findClientById(string $clientId): ?array
    {
        if ($clientId === '') {
            return null;
        }
        $rows = app::get('mcpapi')->model('client')->getList('*', ['client_id' => $clientId], 0, 1);
        return is_array($rows[0] ?? null) ? $rows[0] : null;
    }

    /**
     * 管理端静态注册机密客户端（性质分流，对齐技术设计 §5.1）。
     *
     * - operator：redirect_uris 必填，owner_user_id=0（不绑定，令牌身份来自各操作员授权）
     * - agent：redirect_uris 恒为空，owner_user_id=绑定的服务账号（必绑）
     *   可选 $newServiceAccount：顺带创建服务账号（desktop users + pam account，同一事务）
     *
     * @return array{client: array, client_secret: string, service_account?: array}
     */
    public function registerConfidential(
        string $kind,
        string $name,
        array $redirectUris,
        array $scopes,
        string $operator,
        ?int $serviceUserId = null,
        ?array $newServiceAccount = null,
        ?int $expiresAt = null
    ): array {
        if ($kind !== 'operator' && $kind !== 'agent') {
            throw new InvalidArgumentException('Invalid client kind', 422);
        }
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Client name is required', 422);
        }
        // 复用动态注册服务的回调地址校验（https/loopback/私有 scheme）
        $registration = new mcpapi_auth_oauth_registration();
        $uris = $registration->sanitizeRedirectUris($redirectUris);

        $db = kernel::database();
        $serviceCreated = null;
        $serviceUserId = $serviceUserId;

        if ($kind === 'agent') {
            if (empty($uris)) {
                // Agent 型无浏览器环节：回调恒为空
                $uris = [];
            }
            if ($serviceUserId === null && is_array($newServiceAccount) && ($newServiceAccount['name'] ?? '') !== '') {
                // 顺带创建服务账号（同一事务）
                $db->beginTransaction();
                try {
                    $serviceUserId = $this->createServiceAccount(
                        (string) $newServiceAccount['name'],
                        (array) ($newServiceAccount['role_ids'] ?? [])
                    );
                    $serviceCreated = [
                        'user_id' => $serviceUserId,
                        'login_name' => (string) $newServiceAccount['name'],
                    ];
                    $db->commit();
                } catch (Throwable $e) {
                    $db->rollBack();
                    throw new mcpapi_auth_exception('服务账号创建失败：' . $e->getMessage(), 500);
                }
            }
            if ($serviceUserId === null || $serviceUserId < 1) {
                throw new InvalidArgumentException('Agent client requires a bound service account', 422);
            }
            // 绑定校验：account_type=service 且状态正常
            $svc = app::get('desktop')->model('users')->dump($serviceUserId, 'account_type,status,mcp_enabled');
            if (!is_array($svc) || ($svc['account_type'] ?? 'human') !== 'service') {
                throw new InvalidArgumentException('绑定的账号不是服务账号', 422);
            }
            if (!in_array($svc['status'] ?? '', [1, '1', 'true'], true)) {
                throw new InvalidArgumentException('服务账号状态异常', 422);
            }
        } elseif ($kind === 'operator' && empty($uris)) {
            throw new InvalidArgumentException('操作员型客户端必须登记回调地址', 422);
        }

        // scope 净化（复用授权域白名单）
        $scopeSvc = new mcpapi_auth_oauth_scopes();
        $cleanScopes = $scopeSvc->sanitize($scopes);
        if (empty($cleanScopes)) {
            $cleanScopes = $scopeSvc->defaults();
        }

        $clientId = 'mcp_' . bin2hex(random_bytes(16));
        $clientSecret = $this->generateSecret();
        $now = time();

        $row = [
            'client_id' => $clientId,
            'client_secret_hash' => password_hash($clientSecret, PASSWORD_DEFAULT),
            'owner_user_id' => $kind === 'agent' ? $serviceUserId : 0,
            'name' => $name,
            'tenant_id' => 'default',
            'oms_base_url' => $this->baseUrl(),
            'oms_subject_id' => $kind === 'agent' ? $serviceUserId : 0,
            'oms_role_ids' => [],
            'oms_permissions' => [],
            'scopes' => $cleanScopes,
            'client_type' => 'confidential',
            'client_kind' => $kind,
            'token_endpoint_auth' => 'client_secret_basic',
            'registration_origin' => 'static',
            'redirect_uris' => $uris,
            'enabled' => 'true',
            'revoked_at' => null,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => 'register:' . $operator,
            'updated_by' => 'register:' . $operator,
            'revoked_reason' => null,
            'last_used_at' => null,
        ];
        ($this->clientSaver)($row);

        $result = [
            'client' => $row,
            'client_secret' => $clientSecret, // 明文仅此一次
        ];
        if ($serviceCreated !== null) {
            $result['service_account'] = $serviceCreated;
        }
        return $result;
    }

    /**
     * 创建服务账号（desktop users + pam account，account_type=service）。
     * 调用方负责事务包裹。
     */
    /**
     * 创建服务账号（原生 SQL，避免 dbeav has_parent 的 required 校验干扰）。
     * users 行 + pam_account 行 + 角色绑定。调用方负责事务包裹。
     */
    private function createServiceAccount(string $name, array $roleIds): int
    {
        $db = kernel::database();
        $now = time();
        $loginName = trim($name);
        $randomPass = pam_encrypt::get_encrypted_password(bin2hex(random_bytes(16)), 'shopadmin');

        // users 行（Shopex 主键非 auto_increment：显式分配 user_id，且需与 pam_account.account_id 共用 ID 空间）
        $mx = $db->selectrow("SELECT GREATEST((SELECT IFNULL(MAX(user_id),0) FROM `{$db->prefix}desktop_users`), (SELECT IFNULL(MAX(account_id),0) FROM `{$db->prefix}pam_account`)) AS mx");
        $userId = (int) ($mx['mx'] ?? 0) + 1;
        $db->exec("INSERT INTO `{$db->prefix}desktop_users` (`user_id`, `name`, `status`, `super`, `mcp_enabled`, `account_type`, `disabled`, `token_version`, `create_time`, `last_modify`) VALUES ({$userId}, '{$loginName}', '1', '0', '1', 'service', 'false', 1, {$now}, {$now})");

        if ($userId < 1) {
            throw new RuntimeException('服务账号 users 行写入失败', 500);
        }

        // pam_account 行（登录凭证，密码随机——服务账号禁登录控制台）
        $db->exec("INSERT INTO `{$db->prefix}pam_account` (`account_type`, `account_id`, `login_name`, `login_password`, `disabled`, `createtime`) VALUES ('shopadmin', {$userId}, '{$loginName}', '{$randomPass}', 'false', {$now})");

        // 角色绑定
        foreach ((array) $roleIds as $roleId) {
            $roleId = (int) $roleId;
            if ($roleId > 0) {
                $db->exec("INSERT INTO `{$db->prefix}desktop_hasrole` (`user_id`, `role_id`) VALUES ({$userId}, {$roleId})");
            }
        }

        return $userId;
    }

    /**
     * 全部客户端列表（管理员视角，含动态注册的外部应用 owner_user_id=0）
     */
    public function listAllClients(): array
    {
        $rows = app::get('mcpapi')->model('client')->getList('*', [], 0, -1);
        $rows = array_values(array_filter((array) $rows, 'is_array'));

        // 归属账号：owner 创建的取 owner_user_id；动态注册的取授权过它的账号（consent）
        $ownerIds = [];
        $clientIds = [];
        foreach ($rows as $row) {
            $clientIds[] = (string) $row['client_id'];
            if ((int) ($row['owner_user_id'] ?? 0) > 0) {
                $ownerIds[] = (int) $row['owner_user_id'];
            }
        }
        $accountNames = [];
        $userIds = $ownerIds;
        if ($clientIds !== []) {
            try {
                $consents = app::get('mcpapi')->model('oauth_consent')
                    ->getList('client_id,user_id', ['client_id|in' => $clientIds], 0, -1);
                foreach ((array) $consents as $c) {
                    if (!is_array($c) || (int) ($c['user_id'] ?? 0) < 1) {
                        continue;
                    }
                    $userIds[] = (int) $c['user_id'];
                    $clientAccounts[(string) $c['client_id']][] = (int) $c['user_id'];
                }
            } catch (Throwable $e) {
                $clientAccounts = [];
            }
        }
        if ($userIds !== []) {
            $userIds = array_values(array_unique($userIds));
            try {
                $accounts = app::get('pam')->model('account')
                    ->getList('account_id,login_name', ['account_id|in' => $userIds], 0, -1);
                foreach ((array) $accounts as $a) {
                    if (is_array($a) && (int) ($a['account_id'] ?? 0) > 0) {
                        $accountNames[(int) $a['account_id']] = (string) ($a['login_name'] ?? '');
                    }
                }
            } catch (Throwable $e) {
                $accountNames = [];
            }
        }

        $list = [];
        foreach ($rows as $row) {
            $row['status'] = $this->status($row);
            $row['origin_label'] = ($row['registration_origin'] ?? '') === 'dynamic' ? '动态注册' : '操作员登记';
            $ownerId = (int) ($row['owner_user_id'] ?? 0);
            if ($ownerId > 0) {
                $row['account_label'] = $accountNames[$ownerId] ?? ('user#' . $ownerId);
            } else {
                $labels = [];
                foreach ($clientAccounts[(string) $row['client_id']] ?? [] as $uid) {
                    $labels[] = $accountNames[$uid] ?? ('user#' . $uid);
                }
                if ($labels === [] && (string) ($row['applied_by'] ?? '') !== '') {
                    // 待审批且尚未授权：显示申请人（authorize 尝试时记录）
                    $labels[] = (string) $row['applied_by'];
                }
                $row['account_label'] = $labels !== [] ? implode('、', array_unique($labels)) : '—';
            }
            $list[] = $row;
        }
        $rank = ['active' => 0, 'pending' => 1, 'expired' => 2, 'revoked' => 3];
        usort($list, static function (array $a, array $b) use ($rank): int {
            $ra = $rank[$a['status']] ?? 9;
            $rb = $rank[$b['status']] ?? 9;
            if ($ra !== $rb) {
                return $ra - $rb;
            }
            return (int) ($b['created_at'] ?? 0) <=> (int) ($a['created_at'] ?? 0);
        });
        return $list;
    }

    /**
     * 待审批客户端列表（动态注册、enabled=false 且未吊销）
     */
    public function listPendingClients(): array
    {
        $rows = app::get('mcpapi')->model('client')->getList('*', [], 0, -1);
        $pending = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $enabled = in_array($row['enabled'] ?? null, [true, 1, '1', 'true'], true);
            $revoked = (int) ($row['revoked_at'] ?? 0) > 0;
            if (!$enabled && !$revoked) {
                $pending[] = $row;
            }
        }
        return $pending;
    }

    /**
     * 拒绝待审批客户端（吊销语义，记录审批操作员）。
     */
    public function rejectPendingClient(string $clientId, string $operator, string $reason = ''): array
    {
        $row = $this->findClientById($clientId);
        if ($row === null) {
            throw new mcpapi_auth_exception('Client not found', 404);
        }
        $enabled = in_array($row['enabled'] ?? null, [true, 1, '1', 'true'], true);
        $revoked = (int) ($row['revoked_at'] ?? 0) > 0;
        if ($enabled || $revoked) {
            throw new mcpapi_auth_exception('Client is not pending approval', 409);
        }
        // 拒绝 = 直接作废（删除记录），不保留已撤销尸体数据
        app::get('mcpapi')->model('client')->delete(['client_id' => $clientId]);
        error_log(sprintf(
            'MCPAPI_SECURITY dynamic_registration_rejected client=%s operator=%s reason=%s time=%d',
            $clientId,
            $operator,
            $reason,
            time()
        ));
        return [];
    }

    /**
     * 管理员吊销任意客户端（含动态注册的外部应用 owner_user_id=0）
     */
    public function revokeById(string $clientId, string $operator, string $reason = ''): array
    {
        $row = $this->findClientById($clientId);
        if ($row === null) {
            throw new mcpapi_auth_exception('Client not found', 404);
        }
        if ((int) ($row['revoked_at'] ?? 0) > 0) {
            throw new mcpapi_auth_exception('Client already revoked', 409);
        }
        ($this->clientUpdater)(
            [
                'enabled' => 'false',
                'revoked_at' => time(),
                'revoked_reason' => $reason !== '' ? $reason : ('revoked:' . $operator),
                'updated_at' => time(),
                'updated_by' => 'revoke:' . $operator,
            ],
            ['client_id' => $clientId]
        );
        return $this->findClientById($clientId) ?? [];
    }

    /**
     * 审批激活待审批的动态注册客户端（enabled=false 且未吊销 → enabled=true）。
     * 审批操作员身份写入 updated_by，可追溯。
     */
    public function approvePendingClient(string $clientId, string $operator): array
    {
        $row = $this->findClientById($clientId);
        if ($row === null) {
            throw new mcpapi_auth_exception('Client not found', 404);
        }
        $enabled = in_array($row['enabled'] ?? null, [true, 1, '1', 'true'], true);
        $revoked = (int) ($row['revoked_at'] ?? 0) > 0;
        if ($enabled || $revoked) {
            throw new mcpapi_auth_exception('Client is not pending approval', 409);
        }
        $now = time();
        ($this->clientUpdater)(
            [
                'enabled' => 'true',
                'updated_at' => $now,
                'updated_by' => 'approve:' . $operator,
            ],
            ['client_id' => $clientId]
        );
        return $this->findClientById($clientId) ?? [];
    }

    public function updateExpirationForOwner(int $ownerUserId, string $clientId, ?int $expiresAt): array
    {
        $row = $this->ownedClient($ownerUserId, $clientId);
        if (($row['revoked_at'] ?? null) !== null || !(bool) ($row['enabled'] ?? false)) {
            throw new InvalidArgumentException('Revoked clients cannot be restored', 409);
        }
        $expiresAt = $this->validateExpiration($expiresAt);
        $clientId = (string) ($row['client_id'] ?? $clientId);

        return $this->transaction(function () use ($row, $ownerUserId, $clientId, $expiresAt): array {
            ($this->clientUpdater)(
                [
                    'expires_at' => $expiresAt,
                    'updated_at' => $this->now,
                    'updated_by' => (string) $ownerUserId,
                ],
                ['client_id' => $clientId, 'owner_user_id' => $ownerUserId]
            );
            $updatedRow = array_merge($row, [
                'expires_at' => $expiresAt,
                'updated_at' => $this->now,
                'updated_by' => (string) $ownerUserId,
            ]);
            return $this->view($updatedRow, $ownerUserId);
        });
    }

    private function transaction(callable $operation): array
    {
        ($this->transactionBegin)();
        try {
            $result = $operation();
            ($this->transactionCommit)();
            return $result;
        } catch (Throwable $exception) {
            ($this->transactionRollback)();
            throw $exception;
        }
    }

    private function ownedClient(int $ownerUserId, string $clientId): array
    {
        $this->assertOwner($ownerUserId);
        $rows = array_values(array_filter((array) ($this->clientLoader)($ownerUserId), 'is_array'));
        if ($clientId === '') {
            $currentRow = $this->currentOwnerRow($rows, $ownerUserId);
            $clientId = (string) ($currentRow['client_id'] ?? '');
        }

        foreach ($rows as $row) {
            if (is_array($row) && ($row['client_id'] ?? null) === $clientId
                && (int) ($row['owner_user_id'] ?? 0) === $ownerUserId) {
                return $row;
            }
        }
        throw new mcpapi_auth_exception('Client not found', 404);
    }

    private function currentOwnerRow($rows, int $ownerUserId): ?array
    {
        if (!is_array($rows)) {
            return null;
        }

        $ownerRows = array_values(array_filter(
            $rows,
            static fn($row): bool => is_array($row) && (int) ($row['owner_user_id'] ?? 0) === $ownerUserId
        ));
        usort($ownerRows, static function (array $left, array $right): int {
            $leftUpdatedAt = (int) ($left['updated_at'] ?? $left['created_at'] ?? 0);
            $rightUpdatedAt = (int) ($right['updated_at'] ?? $right['created_at'] ?? 0);
            if ($leftUpdatedAt !== $rightUpdatedAt) {
                return $rightUpdatedAt <=> $leftUpdatedAt;
            }

            return strcmp((string) ($right['client_id'] ?? ''), (string) ($left['client_id'] ?? ''));
        });

        return $ownerRows[0] ?? null;
    }

    private function view(array $row, int $ownerUserId, ?string $kid = null, bool $maskClientId = true): array
    {
        $clientId = (string) ($row['client_id'] ?? '');

        return [
            'client_id' => $maskClientId ? $this->maskClientId($clientId) : $clientId,
            'name' => (string) ($row['name'] ?? ''),
            'status' => $this->status($row),
            'owner_name' => (string) ($this->ownerNameLoader)($ownerUserId),
            'created_at' => $row['created_at'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
            'last_used_at' => $row['last_used_at'] ?? null,
        ];
    }

    private function maskClientId(string $clientId): string
    {
        if (strlen($clientId) <= 8) {
            return str_repeat('*', strlen($clientId));
        }

        return substr($clientId, 0, 4) . str_repeat('*', strlen($clientId) - 8) . substr($clientId, -4);
    }

    private function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function validateExpiration(?int $expiresAt): ?int
    {
        if ($expiresAt === null) {
            return null;
        }
        if ($expiresAt <= $this->now) {
            throw new InvalidArgumentException('Expiration must be in the future', 422);
        }
        return $expiresAt;
    }

    private function status(array $row): string
    {
        $revoked = ($row['revoked_at'] ?? null) !== null;
        if (!(bool) ($row['enabled'] ?? false)) {
            return $revoked ? 'revoked' : 'pending';
        }
        if ($revoked) {
            return 'revoked';
        }
        $expiresAt = $row['expires_at'] ?? null;
        return $expiresAt !== null && (int) $expiresAt <= $this->now ? 'expired' : 'active';
    }

    private function isActive(array $row): bool
    {
        return $this->status($row) === 'active';
    }

    private function assertOwner(int $ownerUserId): void
    {
        if ($ownerUserId < 1) {
            throw new mcpapi_auth_exception();
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) kernel::base_url(1), '/');
    }

    private function normalizeAssistantBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl !== '' && !preg_match('#/index\.php$#i', $baseUrl)) {
            $baseUrl .= '/index.php';
        }
        return $baseUrl;
    }
}
