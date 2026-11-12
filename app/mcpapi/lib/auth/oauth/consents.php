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
 * OAuth 同意记录：user + client 维度保存已授权 scope，支持撤销。
 */
final class mcpapi_auth_oauth_consents
{
    private $rowLoader;
    private $rowSaver;
    private $rowUpdater;
    private int $now;

    public function __construct(
        ?callable $rowLoader = null,
        ?callable $rowSaver = null,
        ?callable $rowUpdater = null,
        ?int $now = null
    ) {
        $this->rowLoader = $rowLoader ?? static function (array $filter): array {
            return app::get('mcpapi')->model('oauth_consent')->getList('*', $filter, 0, 2);
        };
        $this->rowSaver = $rowSaver ?? static function (array $row): void {
            app::get('mcpapi')->model('oauth_consent')->insert($row);
        };
        $this->rowUpdater = $rowUpdater ?? static function (array $data, array $filter): void {
            app::get('mcpapi')->model('oauth_consent')->update($data, $filter);
        };
        $this->now = $now ?? time();
    }

    /**
     * 是否存在未撤销的同意记录，且覆盖了请求的全部 scope。
     *
     * @param string[] $scopes
     */
    public function hasValidConsent(int $userId, string $clientId, array $scopes): bool
    {
        $row = $this->find($userId, $clientId);
        if ($row === null) {
            return false;
        }
        $granted = is_array($row['scopes'] ?? null) ? $row['scopes'] : [];
        $grantedMap = array_fill_keys(array_map('strval', $granted), true);
        foreach ($scopes as $scope) {
            if (!isset($grantedMap[$scope])) {
                return false;
            }
        }
        return true;
    }

    /**
     * 保存或更新同意记录（并集合并）。
     *
     * @param string[] $scopes
     */
    public function grant(int $userId, string $clientId, array $scopes): void
    {
        $row = $this->find($userId, $clientId);
        if ($row === null) {
            ($this->rowSaver)([
                'user_id' => $userId,
                'client_id' => $clientId,
                'scopes' => array_values($scopes),
                'granted_at' => $this->now,
                'revoked_at' => null,
                'last_refreshed_at' => $this->now,
            ]);
            return;
        }

        $granted = is_array($row['scopes'] ?? null) ? $row['scopes'] : [];
        ($this->rowUpdater)(
            [
                'scopes' => array_values(array_unique(array_merge($granted, $scopes))),
                'granted_at' => $this->now,
                'revoked_at' => null,
                'last_refreshed_at' => $this->now,
            ],
            ['user_id' => $userId, 'client_id' => $clientId]
        );
    }

    /**
     * 撤销用户对某 client 的同意（同时使该 client 的 refresh token 失效由调用方负责）。
     */
    public function revoke(int $userId, string $clientId): void
    {
        if ($this->find($userId, $clientId) !== null) {
            ($this->rowUpdater)(
                ['revoked_at' => $this->now],
                ['user_id' => $userId, 'client_id' => $clientId]
            );
        }
    }

    /**
     * @return array{user_id:int, scopes:string[]}|null
     */
    public function findValid(int $userId, string $clientId): ?array
    {
        $row = $this->find($userId, $clientId);
        if ($row === null) {
            return null;
        }
        return [
            'user_id' => (int) $row['user_id'],
            'scopes' => is_array($row['scopes'] ?? null) ? $row['scopes'] : [],
        ];
    }

    private function find(int $userId, string $clientId): ?array
    {
        if ($userId < 1 || $clientId === '') {
            return null;
        }
        $rows = ($this->rowLoader)(['user_id' => $userId, 'client_id' => $clientId]);
        $row = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
        if ($row === null) {
            return null;
        }
        // 撤销后视为无记录（重授权走 grant 覆盖 revoked_at）
        return ($row['revoked_at'] ?? null) !== null && (int) $row['revoked_at'] > 0 ? null : $row;
    }
}
