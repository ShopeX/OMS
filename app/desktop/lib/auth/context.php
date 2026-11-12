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
 * desktop_auth_context — 本次请求内存态认证上下文
 *
 * 设计原则：
 * - 仅保存本次 request 的用户身份信息，不写 SESSION
 * - 请求结束时自动清空（PHP 请求结束自然回收）
 * - 所有需要读取当前登录用户的地方都应从 context 读取
 *
 * 使用方式：
 *   desktop_auth_context::setUserId($userId);
 *   desktop_auth_context::userId();          // int
 *   desktop_auth_context::isAuthenticated();   // bool
 *   desktop_auth_context::clear();
 */
class desktop_auth_context
{
    /** @var array ['user_id' => int, 'login_name' => string, 'profile' => array] */
    private static $store = [];

    public static function setUserId(int $userId): void
    {
        if ($userId > 0) {
            self::$store['user_id'] = $userId;
        }
    }

    public static function userId(): int
    {
        return (int)(self::$store['user_id'] ?? 0);
    }

    public static function isAuthenticated(): bool
    {
        return self::userId() > 0;
    }

    public static function setLoginName(string $loginName): void
    {
        self::$store['login_name'] = $loginName;
    }

    public static function loginName(): string
    {
        return self::$store['login_name'] ?? '';
    }

    /**
     * @param array $profile  用户档案，键名: login_name, name, is_super, status, mobile
     */
    public static function setProfile(array $profile): void
    {
        self::$store['profile'] = $profile;
    }

    public static function profile(): array
    {
        return self::$store['profile'] ?? [];
    }

    public static function isSuper(): bool
    {
        return (bool)(self::$store['profile']['is_super'] ?? false);
    }

    public static function clear(): void
    {
        self::$store = [];
    }
}
