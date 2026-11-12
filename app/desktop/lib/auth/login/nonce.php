<?php

/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * SCRUM-7779 登录防重放（修法 2：前端生成 nonce，后端只记「已用」）
 *
 * 为什么不用「meta 签发 nonce」：那是免登录接口，每次请求都要写一个 KV key，
 * 可被匿名刷请求放大（KV 无 maxmemory 时会把 Redis 撑爆 ⇒ 全会话一起没）。
 * 现在写入量 = 真实登录提交次数，且解密失败时零写入。
 *
 * - claim()：原子占位（Redis INCR，首次返回 1），重放返回 ≥2 ⇒ 拒；
 * - freshTimestamp()：密文内时间戳新鲜度校验 ⇒ 录走的密文过期即失效；
 * - CACHE_TTL 必须大于 TS_MAX_AGE，否则「缓存过期 + 时间戳仍新鲜」会出现重放窗口。
 */
class desktop_auth_login_nonce
{
    /** 已用 nonce 的缓存前缀 */
    private const PREFIX = 'login_nonce_used:';

    /** 密文最大年龄（秒）：容忍客户端时钟偏慢 */
    public const TS_MAX_AGE = 900;

    /** 允许客户端时钟超前的秒数 */
    public const TS_MAX_SKEW = 300;

    /** 已用标记的存活时间（秒）：必须 > TS_MAX_AGE */
    public const CACHE_TTL = 1200;

    /**
     * 一次性占位：返回 true 表示「本 nonce 首次使用」，false 表示重复使用（拒）
     */
    public static function claim(string $nonce): bool
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $nonce)) {
            return false;
        }
        $kv = self::kv();
        $key = self::PREFIX . $nonce;
        $controller = $kv->get_controller();

        // 只有 redis / memcache / memcached 实现了 base_interface_kvstore_extension（原子 INCR）。
        // 必须判能力再调：直接调控制器方法会在 filesystem/mysql/dba/mongo 等后端上
        // 触发 "Call to undefined method base_kvstore_filesystem::increment()" 致命错误。
        if ($controller instanceof base_interface_kvstore_extension) {
            $hits = (int) $controller->increment($key, 1);
            if ($hits !== 1) {
                return false;
            }
            // 首次：补上 TTL（INCR 建的 key 没有过期时间）。setcr 不属于 extension 接口，故兜底判方法
            if (method_exists($controller, 'setcr')) {
                $controller->setcr($key, 1, self::CACHE_TTL);
            } else {
                $kv->store($key, 1, self::CACHE_TTL);
            }

            return true;
        }

        // 无原子自增的后端（filesystem/mysql/dba/mongo…，如本地未接 Redis 的环境）：
        // 降级为「读-写」占位——非原子，但第二次提交同样会被拒；
        // 绝不能因为 KV 后端不支持 INCR 就让登录直接 500。
        $used = null;
        if ($kv->fetch($key, $used)) {
            return false;
        }
        $kv->store($key, 1, self::CACHE_TTL);

        return true;
    }

    /**
     * 密文内时间戳（毫秒）是否新鲜
     */
    public static function freshTimestamp(int $tsMs): bool
    {
        if ($tsMs <= 0) {
            return false;
        }
        $nowMs = (int) round(microtime(true) * 1000);
        if ($tsMs > $nowMs + self::TS_MAX_SKEW * 1000) {
            return false;
        }

        return $nowMs - $tsMs <= self::TS_MAX_AGE * 1000;
    }

    private static function kv()
    {
        return base_kvstore::instance('default');
    }
}