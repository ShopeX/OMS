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
 * OAuth Refresh Token：签发、轮换（rotation）、重放检测（已轮换 token 再用 = 吊销整链）。
 */
final class mcpapi_auth_oauth_refreshtokens
{
    public const LIFETIME = 2592000; // 30 天
    private const TOKEN_BYTES = 32;
    /** 过期/轮换/吊销行保留时长（重放检测与审计），超过后由 purgeExpired 清理 */
    public const RETENTION = 604800; // 7 天

    private $rowLoader;
    private $rowSaver;
    private $rowUpdater;
    private int $now;
    /** @var callable(int $userId): int 用户当前 token_version */
    private $tokenVersionProvider;
    /** @var callable|null 重放安全事件钩子（int $userId, string $clientId） */
    private $onReplay;

    public function __construct(
        ?callable $rowLoader = null,
        ?callable $rowSaver = null,
        ?callable $rowUpdater = null,
        ?int $now = null,
        ?callable $tokenVersionProvider = null,
        ?callable $onReplay = null
    ) {
        $this->rowLoader = $rowLoader ?? static function (array $filter): array {
            return app::get('mcpapi')->model('oauth_refresh_token')->getList('*', $filter, 0, 2);
        };
        $this->rowSaver = $rowSaver ?? static function (array $row): void {
            app::get('mcpapi')->model('oauth_refresh_token')->insert($row);
        };
        $this->rowUpdater = $rowUpdater ?? static function (array $data, array $filter): void {
            app::get('mcpapi')->model('oauth_refresh_token')->update($data, $filter);
        };
        $this->now = $now ?? time();
        $this->tokenVersionProvider = $tokenVersionProvider ?? static function (int $userId): int {
            $user = app::get('desktop')->model('users')->dump($userId, 'token_version');
            $version = is_array($user) ? (int) ($user['token_version'] ?? 1) : 1;
            return $version >= 1 ? $version : 1;
        };
        $this->onReplay = $onReplay;
    }

    /**
     * 签发 refresh token。
     *
     * @param string[] $scopes
     * @return array{refresh_token:string, expires_at:int}
     */
    public function issue(int $userId, string $clientId, array $scopes): array
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $expiresAt = $this->now + self::LIFETIME;
        ($this->rowSaver)([
            'token_hash' => hash('sha256', $token),
            'user_id' => $userId,
            'client_id' => $clientId,
            'scopes' => $scopes,
            'status' => 'active',
            'token_version' => ($this->tokenVersionProvider)($userId),
            'expires_at' => $expiresAt,
            'rotated_at' => null,
            'replaced_by_token_hash' => null,
            'created_at' => $this->now,
        ]);
        return ['refresh_token' => $token, 'expires_at' => $expiresAt];
    }

    /**
     * 消费 refresh token（轮换）：返回新 token 与用户身份。
     *
     * 重放检测：状态为 rotated 的 token 再次出现 → 吊销其替换链上的全部 token，
     * 抛 invalid_grant（RFC 6749 §10.4 / OAuth 2.1 强制 rotation）。
     *
     * @return array{user_id:int, scopes:string[], refresh_token:string, expires_at:int}
     * @throws mcpapi_auth_oauth_error
     */
    public function rotate(string $token, string $clientId): array
    {
        $rows = ($this->rowLoader)(['token_hash' => hash('sha256', $token)]);
        $row = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
        if ($row === null) {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Refresh token not found');
        }

        if (($row['client_id'] ?? null) !== $clientId) {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Refresh token was issued to another client');
        }

        $status = (string) ($row['status'] ?? '');
        if ($status === 'rotated') {
            // 重放：吊销替换链 + 该用户该应用全部 refresh + 提升 token_version（安全事件）
            $userId = (int) ($row['user_id'] ?? 0);
            $this->revokeChain($row);
            $this->revokeAll($userId, $clientId);
            if ($this->onReplay !== null) {
                ($this->onReplay)($userId, $clientId);
            }
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Refresh token replay detected, authorization chain revoked');
        }
        if ($status === 'revoked') {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Refresh token revoked');
        }
        if ((int) ($row['expires_at'] ?? 0) <= $this->now) {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Refresh token expired');
        }

        $userId = (int) ($row['user_id'] ?? 0);

        // token_version 终审：签发后用户改密/被吊销会话（版本提升）→ refresh 全部失效
        if (($this->tokenVersionProvider)($userId) > (int) ($row['token_version'] ?? 1)) {
            $this->revokeAll($userId, $clientId);
            throw new mcpapi_auth_oauth_error('invalid_grant', 'User session has been revoked');
        }

        $scopes = is_array($row['scopes'] ?? null) ? $row['scopes'] : [];
        $replacement = $this->issue($userId, $clientId, $scopes);

        ($this->rowUpdater)(
            [
                'status' => 'rotated',
                'rotated_at' => $this->now,
                'replaced_by_token_hash' => hash('sha256', $replacement['refresh_token']),
            ],
            ['token_hash' => $row['token_hash']]
        );

        return [
            'user_id' => $userId,
            'scopes' => $scopes,
            'refresh_token' => $replacement['refresh_token'],
            'expires_at' => $replacement['expires_at'],
        ];
    }

    /**
     * 吊销用户在某 client 下的全部 refresh token（用户撤销同意 / client 被吊销时调用）。
     */
    public function revokeAll(int $userId, string $clientId): void
    {
        ($this->rowUpdater)(
            ['status' => 'revoked'],
            ['user_id' => $userId, 'client_id' => $clientId, 'status' => 'active']
        );
    }

    /**
     * 清理过期 refresh token（任意状态）：保留 RETENTION 窗口供重放检测与审计。
     *
     * 注意：base_db_model::filter 仅支持等值过滤，范围条件必须用原生 SQL。
     */
    public function purgeExpired(): int
    {
        $db = kernel::database();
        $table = $db->prefix . 'mcpapi_oauth_refresh_token';
        $cutoff = (int) ($this->now - self::RETENTION);
        try {
            $rows = $db->select("SELECT `token_hash` FROM `{$table}` WHERE `expires_at` < {$cutoff}");
        } catch (Throwable $e) {
            return 0;
        }
        $count = 0;
        foreach ((array) $rows as $row) {
            $hash = is_array($row) ? ($row['token_hash'] ?? null) : null;
            if (!is_string($hash) || $hash === '' || !preg_match('/^[0-9a-f]{64}$/', $hash)) {
                continue;
            }
            try {
                if ($db->exec("DELETE FROM `{$table}` WHERE `token_hash` = '{$hash}'")) {
                    $count++;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
        return $count;
    }

    /**
     * 吊销整条替换链：从重放点沿 replaced_by 递归作废。
     */
    private function revokeChain(array $row): void
    {
        $seen = [];
        $current = $row;
        while ($current !== null && !isset($seen[$current['token_hash'] ?? ''])) {
            $seen[$current['token_hash'] ?? ''] = true;
            ($this->rowUpdater)(
                ['status' => 'revoked'],
                ['token_hash' => $current['token_hash']]
            );
            $nextHash = (string) ($current['replaced_by_token_hash'] ?? '');
            $current = null;
            if ($nextHash !== '') {
                $rows = ($this->rowLoader)(['token_hash' => $nextHash]);
                if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
                    $current = $rows[0];
                }
            }
        }
    }
}
