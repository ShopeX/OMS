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
 * OAuth 授权码：签发（hash 落库）、单次消费（PKCE S256 校验）。
 */
final class mcpapi_auth_oauth_codes
{
    public const LIFETIME = 300; // 5 分钟（设计 V3.1：WorkBuddy 预期 10 分钟内换票）
    private const CODE_BYTES = 32;
    /** 过期行保留时长（重放检测/审计窗口），超过后由 purgeExpired 清理 */
    public const RETENTION = 86400;

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
            return app::get('mcpapi')->model('oauth_code')->getList('*', $filter, 0, 2);
        };
        $this->rowSaver = $rowSaver ?? static function (array $row): void {
            app::get('mcpapi')->model('oauth_code')->insert($row);
        };
        $this->rowUpdater = $rowUpdater ?? static function (array $data, array $filter): void {
            app::get('mcpapi')->model('oauth_code')->update($data, $filter);
        };
        $this->now = $now ?? time();
    }

    /**
     * 签发授权码。
     *
     * @param string[] $scopes
     * @return array{code:string, expires_at:int}
     */
    public function issue(string $clientId, int $userId, string $redirectUri, array $scopes, ?string $codeChallenge = null, ?string $challengeMethod = null): array
    {
        $code = bin2hex(random_bytes(self::CODE_BYTES));
        $expiresAt = $this->now + self::LIFETIME;
        ($this->rowSaver)([
            'code_hash' => hash('sha256', $code),
            'client_id' => $clientId,
            'user_id' => $userId,
            'redirect_uri' => $redirectUri,
            'scopes' => $scopes,
            'code_challenge' => $codeChallenge,
            'challenge_method' => $challengeMethod,
            'expires_at' => $expiresAt,
            'consumed_at' => null,
            'created_at' => $this->now,
        ]);
        return ['code' => $code, 'expires_at' => $expiresAt];
    }

    /**
     * 单次消费授权码：校验 client / redirect / 过期 / 未消费 / PKCE。
     *
     * @param string|null $codeVerifier PKCE verifier，公共客户端必填
     * @return array{user_id:int, scopes:string[], redirect_uri:string}
     * @throws mcpapi_auth_oauth_error code 无效时（invalid_grant）
     */
    public function consume(string $code, string $clientId, string $redirectUri, ?string $codeVerifier = null): array
    {
        $rows = ($this->rowLoader)(['code_hash' => hash('sha256', $code)]);
        $row = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
        if ($row === null) {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Authorization code not found');
        }

        if (($row['client_id'] ?? null) !== $clientId) {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Authorization code was issued to another client');
        }
        if ((string) ($row['redirect_uri'] ?? '') !== $redirectUri) {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'redirect_uri mismatch');
        }
        if ((int) ($row['expires_at'] ?? 0) <= $this->now) {
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Authorization code expired');
        }
        if (($row['consumed_at'] ?? null) !== null && (int) $row['consumed_at'] > 0) {
            // 重放：立即作废并拒绝（RFC 6749 §4.1.2 建议尽量吊销该 code 关联 token）
            throw new mcpapi_auth_oauth_error('invalid_grant', 'Authorization code already used');
        }

        $challenge = (string) ($row['code_challenge'] ?? '');
        if ($challenge !== '') {
            $verifier = $codeVerifier ?? '';
            if ($verifier === '') {
                throw new mcpapi_auth_oauth_error('invalid_grant', 'code_verifier required');
            }
            if (!preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier)) {
                throw new mcpapi_auth_oauth_error('invalid_grant', 'Malformed code_verifier');
            }
            $method = (string) ($row['challenge_method'] ?? 'S256');
            $computed = $method === 'S256'
                ? rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')
                : $verifier;
            if (!hash_equals($challenge, $computed)) {
                throw new mcpapi_auth_oauth_error('invalid_grant', 'code_verifier mismatch');
            }
        }

        ($this->rowUpdater)(['consumed_at' => $this->now], ['code_hash' => $row['code_hash']]);

        return [
            'user_id' => (int) $row['user_id'],
            'scopes' => is_array($row['scopes'] ?? null) ? $row['scopes'] : [],
            'redirect_uri' => (string) $row['redirect_uri'],
        ];
    }

    /**
     * 清理过期授权码（含已消费）：保留 RETENTION 窗口供重放审计，由定时任务/换票后懒清理调用。
     *
     * 注意：base_db_model::filter 仅支持等值过滤（比较符 key 会被丢弃），
     * 范围条件必须用原生 SQL，否则会退化成全表操作。
     */
    public function purgeExpired(): int
    {
        $db = kernel::database();
        $table = $db->prefix . 'mcpapi_oauth_code';
        $cutoff = (int) ($this->now - self::RETENTION);
        try {
            $rows = $db->select("SELECT `code_hash` FROM `{$table}` WHERE `expires_at` < {$cutoff}");
        } catch (Throwable $e) {
            return 0;
        }
        $count = 0;
        foreach ((array) $rows as $row) {
            $hash = is_array($row) ? ($row['code_hash'] ?? null) : null;
            if (!is_string($hash) || $hash === '' || !preg_match('/^[0-9a-f]{64}$/', $hash)) {
                continue;
            }
            try {
                if ($db->exec("DELETE FROM `{$table}` WHERE `code_hash` = '{$hash}'")) {
                    $count++;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
        return $count;
    }
}
