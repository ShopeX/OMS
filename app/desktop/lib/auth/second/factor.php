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

/**
 * 二次认证状态：pending 上下文、短信节流、验证码错误次数（共用 kvstore）。
 */
class desktop_auth_second_factor
{
    public const KVSTORE_KEY = 'jwt_sms_throttle';

    public const PENDING_TTL = 600;

    private const PENDING_PREFIX = 'pending_';

    private const SMS_MAX_PER_WINDOW = 3;

    private const SMS_WINDOW_SECONDS = 3600;

    private const FA_MAX_ATTEMPTS = 3;

    private const FA_ATTEMPT_WINDOW = 300;

    /**
     * @return array{user_id:int,account_type:string,login_name:string,idaas_username?:string,created_at?:int}|null
     */
    public static function getPending(string $fid): ?array
    {
        if ($fid === '') {
            return null;
        }

        $value = null;
        base_kvstore::instance(self::KVSTORE_KEY)->fetch(self::PENDING_PREFIX . $fid, $value);
        if (!$value) {
            return null;
        }

        $payload = json_decode((string)$value, true);
        if (!is_array($payload) || (int)($payload['user_id'] ?? 0) <= 0) {
            return null;
        }

        return $payload;
    }

    public static function savePending(
        string $fid,
        int $userId,
        string $accountType,
        string $loginName,
        string $idaasUsername = ''
    ): void {
        if ($fid === '' || $userId <= 0) {
            return;
        }

        $payload = [
            'user_id' => $userId,
            'account_type' => $accountType,
            'login_name' => $loginName,
            'idaas_username' => $idaasUsername,
            'created_at' => time(),
        ];

        base_kvstore::instance(self::KVSTORE_KEY)->store(
            self::PENDING_PREFIX . $fid,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            self::PENDING_TTL
        );
    }

    public static function clearPending(string $fid): void
    {
        if ($fid === '') {
            return;
        }

        base_kvstore::instance(self::KVSTORE_KEY)->store(self::PENDING_PREFIX . $fid, '', 1);
    }

    public static function assertSmsAllowed(): void
    {
        // 短信发送频率限制暂不启用
        return;
    }

    public static function markSmsSent(): void
    {
        $kvstore = base_kvstore::instance(self::KVSTORE_KEY);
        $key = 'throttle_' . md5(base_request::get_remote_addr());

        $value = null;
        $kvstore->fetch($key, $value);

        $data = json_decode($value ?: '', true) ?: ['count' => 0, 'window_start' => time()];
        $data['count'] = ($data['count'] ?? 0) + 1;
        $data['window_start'] = $data['window_start'] ?: time();

        $ttl = max(self::SMS_WINDOW_SECONDS - (time() - $data['window_start']), 60);
        $kvstore->store($key, json_encode($data), (int)$ttl);
    }

    public static function assertVerifyAllowed(string $fid): void
    {
        // 验证码错误次数限制暂不启用
        return;
    }

    public static function recordVerifyFailure(string $fid): void
    {
        $kvstore = base_kvstore::instance(self::KVSTORE_KEY);
        $key = '2fa_' . $fid;

        $value = null;
        $kvstore->fetch($key, $value);

        $kvstore->store($key, (string)((int)($value ?? 0) + 1), self::FA_ATTEMPT_WINDOW);
    }

    public static function clearVerifyFailures(string $fid): void
    {
        base_kvstore::instance(self::KVSTORE_KEY)->store('2fa_' . $fid, '', 1);
    }

    public static function clearSmsThrottle(): void
    {
        $key = 'throttle_' . md5(base_request::get_remote_addr());
        base_kvstore::instance(self::KVSTORE_KEY)->store($key, '', 1);
    }
}
