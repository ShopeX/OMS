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

class desktop_auth_profile
{
    /**
     * Mask person-identifying text for browser API responses (aligned with Vue NavigationBar).
     */
    public static function maskDisplayName(string $name): string
    {
        if ($name === '') {
            return '管理员';
        }

        if (function_exists('mb_strlen')) {
            $chars = [];
            $len = mb_strlen($name, 'UTF-8');
            for ($i = 0; $i < $len; $i++) {
                $chars[] = mb_substr($name, $i, 1, 'UTF-8');
            }
        } else {
            $chars = str_split($name);
        }

        $count = count($chars);
        if ($count <= 2) {
            return $chars[0] . '*';
        }
        if ($count <= 4) {
            return $chars[0] . '*' . $chars[$count - 1];
        }

        return implode('', array_slice($chars, 0, 2))
            . '***'
            . implode('', array_slice($chars, -2));
    }

    /**
     * Strip PII from internal profile before returning to Vue (login/me/refresh/2FA/OIDC).
     *
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    public static function forBrowserClient(array $profile): array
    {
        $displaySource = trim((string)($profile['name'] ?? ''));
        if ($displaySource === '') {
            $displaySource = trim((string)($profile['login_name'] ?? ''));
        }

        $public = [
            'id' => (int)($profile['id'] ?? 0),
            'display_name' => self::maskDisplayName($displaySource),
            'is_super' => (bool)($profile['is_super'] ?? false),
            'mcp_enabled' => $profile['mcp_enabled'] ?? true,
            'roles' => is_array($profile['roles'] ?? null) ? $profile['roles'] : [],
        ];

        if (array_key_exists('need_change_password', $profile)) {
            $public['need_change_password'] = $profile['need_change_password'];
        }
        if (isset($profile['login_time'])) {
            $public['login_time'] = (int)$profile['login_time'];
        }

        return $public;
    }

    /**
     * Browser login / refresh / 2FA / OIDC user profile (internal; sanitize via forBrowserClient for API).
     */
    public static function buildBrowser(int $userId, string $loginName): array
    {
        $userModel = app::get('desktop')->model('users');
        $user = $userModel->dump($userId, '*', [':account@pam' => ['login_name']]);
        $desktopUser = kernel::single('desktop_user');
        $desktopUser->setVirtualLogin($userId);
        $accountLoginName = (string)($user['account']['login_name'] ?? '');
        $resolvedLoginName = $accountLoginName !== '' ? $accountLoginName : $loginName;

        return [
            'id' => $userId,
            'login_name' => $resolvedLoginName,
            'name' => $user['name'] ?? $resolvedLoginName,
            'is_super' => (bool)($user['super'] ?? false),
            'mcp_enabled' => $user['mcp_enabled'] ?? true,
            'need_change_password' => self::needChangePasswordHint($userId),
            'roles' => [],
        ];
    }

    /**
     * Minimal MCP profile — login_name + is_super only.
     */
    public static function buildMcp(int $userId, string $loginName): array
    {
        $userModel = app::get('desktop')->model('users');
        $user = $userModel->dump($userId, 'user_id,super', [':account@pam' => ['login_name']]);

        return [
            'login_name' => $loginName,
            'is_super' => (bool)($user['super'] ?? false),
        ];
    }

    public static function needChangePasswordHint(int $userId): ?string
    {
        $desktopUser = kernel::single('desktop_user');
        $errmsg = '';
        if (!$desktopUser->checkPassword('', $errmsg)) {
            return $errmsg ?: null;
        }
        if ($desktopUser->isForceResetPwd($userId)) {
            return '管理员强制您改密码';
        }

        return null;
    }
}
