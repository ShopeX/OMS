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

class desktop_auth_session_bridge
{
    public static function hydrateFromUserId(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $accountType = pam_account::get_account_type('desktop');
        kernel::single('base_session')->start();
        $_SESSION['account'][$accountType] = $userId;
        $_SESSION['account']['desktop'] = $userId;
        $_SESSION['type'] = $accountType;
        $_SESSION['login_time'] = time();
        kernel::single('desktop_user')->setVirtualLogin($userId);
    }

    public static function clearAuthCookies(): void
    {
        $cookies = ['jwt', 'oms-token-key', 'wap-token-key', 'wap-refresh-token-key', 'oms-refresh-token-key', 'pam_passport_basic_uname', 's'];
        $host = parse_url(kernel::base_url(1), PHP_URL_HOST);
        $expire = time() - 3600;
        $isHttps = parse_url(kernel::base_url(1), PHP_URL_SCHEME) === 'https';
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1', ''], true)
            || preg_match('/\\.local$/i', $host ?? '');
        $secure = $isHttps || !$isLocal;
        $domains = $host ? ['', $host, '.' . $host] : [''];
        foreach ($cookies as $name) {
            foreach ($domains as $domain) {
                setcookie($name, '', [
                    'expires' => $expire,
                    'path' => '/',
                    'domain' => $domain,
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        }
    }

    public static function clear(): void
    {
        self::clearAuthCookies();
        kernel::single('base_session')->destory();
    }
}
