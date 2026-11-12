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

final class mcpapi_auth_owner
{
    private $userLoader;
    private $roleLoader;

    public function __construct(?callable $userLoader = null, ?callable $roleLoader = null)
    {
        $this->userLoader = $userLoader ?? static function (int $userId): array {
            $user = app::get('desktop')->model('users')->dump($userId, 'user_id,status,super,mcp_enabled,token_version,account_type');
            return is_array($user) ? $user : [];
        };
        $this->roleLoader = $roleLoader ?? static function (int $userId): array {
            $hasRole = app::get('desktop')->model('hasrole');
            $roles = app::get('desktop')->model('roles');
            $rows = $hasRole->getList('role_id', ['user_id' => $userId]);
            if (!$rows) {
                return [];
            }

            $roleIds = array_values(array_filter(array_map(
                static fn (array $row): ?int => isset($row['role_id']) ? (int) $row['role_id'] : null,
                $rows
            )));
            return $roles->getList('role_id,workground,data_authority', ['role_id|in' => $roleIds]);
        };
    }

    public function resolve(int $ownerUserId): array
    {
        if ($ownerUserId < 1) {
            throw new mcpapi_auth_exception();
        }

        $user = ($this->userLoader)($ownerUserId);
        if (!is_array($user) || (int) ($user['user_id'] ?? 0) !== $ownerUserId) {
            throw new mcpapi_auth_exception();
        }
        if (!$this->isEnabled($user['status'] ?? null) || !$this->isMcpEnabled($user['mcp_enabled'] ?? null)) {
            throw new mcpapi_auth_exception('Forbidden', 403);
        }

        $roleIds = [];
        $permissions = [];
        foreach ((array) ($this->roleLoader)($ownerUserId) as $role) {
            if (!is_array($role) || !isset($role['role_id'])) {
                continue;
            }
            $roleIds[] = (int) $role['role_id'];
            $permissions = array_merge(
                $permissions,
                $this->decodePermissions($role['workground'] ?? null),
                $this->decodePermissions($role['data_authority'] ?? null)
            );
        }

        $roleIds = array_values(array_unique(array_filter($roleIds, static fn (int $roleId): bool => $roleId > 0)));
        $permissions = array_values(array_unique(array_filter($permissions, static fn ($permission): bool => is_string($permission) && $permission !== '')));

        return [
            'oms_subject_id' => $ownerUserId,
            'oms_role_ids' => $roleIds,
            'oms_permissions' => $permissions,
            'is_super' => $this->isSuper($user['super'] ?? null),
            'mcp_enabled' => true,
            'token_version' => $this->normalizeVersion($user['token_version'] ?? 1),
            'account_type' => ($user['account_type'] ?? 'human') === 'service' ? 'service' : 'human',
        ];
    }

    /**
     * 用户当前 token_version（轻量查询）：access/refresh 的 ver 终审依据。
     * 用户不存在或版本非法 → 拒绝（fail-closed）。
     */
    public function tokenVersion(int $ownerUserId): int
    {
        if ($ownerUserId < 1) {
            throw new mcpapi_auth_exception();
        }
        $user = ($this->userLoader)($ownerUserId);
        if (!is_array($user) || (int) ($user['user_id'] ?? 0) !== $ownerUserId) {
            throw new mcpapi_auth_exception();
        }
        return $this->normalizeVersion($user['token_version'] ?? 1);
    }

    private function normalizeVersion($version): int
    {
        $version = (int) $version;
        return $version >= 1 ? $version : 1;
    }

    public function limitScopes(array $scopes, array $permissions, bool $isSuper = false): array
    {
        if ($isSuper) {
            return array_values(array_filter($scopes, static fn ($scope): bool => is_string($scope) && $scope !== ''));
        }
        $allowed = array_fill_keys(array_map('strval', $permissions), true);
        return array_values(array_filter($scopes, static function ($scope) use ($allowed): bool {
            if (!is_string($scope) || $scope === '') {
                return false;
            }
            return isset($allowed[$scope]) || isset($allowed[str_replace(':', '.', $scope)]);
        }));
    }

    private function isEnabled($status): bool
    {
        return $status === true || $status === 1 || $status === '1' || $status === 'active' || $status === 'enabled';
    }

    private function isSuper($super): bool
    {
        return $super === true || $super === 1 || $super === '1';
    }

    private function isMcpEnabled($mcpEnabled): bool
    {
        if ($mcpEnabled === null || $mcpEnabled === '') {
            return true;
        }
        return $mcpEnabled === true || $mcpEnabled === 1 || $mcpEnabled === '1' || $mcpEnabled === 'true';
    }

    private function decodePermissions($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = @unserialize($value, ['allowed_classes' => false]);
        return is_array($decoded) ? $decoded : [];
    }
}
