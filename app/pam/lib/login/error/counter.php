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

class pam_login_error_counter {

    /** 连续登录失败后显示图形验证码的阈值 */
    public const VCODE_ERROR_THRESHOLD = 5;

    private $kvstoreKey = 'pam_login_error_counter';
    private $ttl = 600;

    /**
     * Get KVStore instance
     *
     * @return base_kvstore
     */
    private function getKvStore() {
        return base_kvstore::instance($this->kvstoreKey);
    }

    /**
     * Get the storage key for an appid
     *
     * @param string $appid
     * @return string
     */
    private function getKey($appid) {
        return 'error_count_' . $appid;
    }

    /**
     * Increment error count for appid
     *
     * @param string $appid
     * @param int $ttl TTL in seconds (default 600)
     * @return int New count after increment
     */
    public function increment($appid, $ttl = 600) {
        $kvstore = $this->getKvStore();
        $key = $this->getKey($appid);

        $count = 0;
        $kvstore->fetch($key, $count);

        $count = (int)$count + 1;
        $kvstore->store($key, $count, $ttl > 0 ? $ttl : $this->ttl);

        return $count;
    }

    /**
     * Get current error count for appid
     *
     * @param string $appid
     * @return int
     */
    public function get($appid) {
        $kvstore = $this->getKvStore();
        $key = $this->getKey($appid);

        $count = null;
        $kvstore->fetch($key, $count);

        return (int)$count;
    }

    /**
     * Reset (clear) error count for appid
     *
     * @param string $appid
     * @return void
     */
    public function reset($appid) {
        $kvstore = $this->getKvStore();
        $key = $this->getKey($appid);

        $kvstore->store($key, 0, 1);
    }

    /**
     * Check if should show vcode after repeated login failures.
     *
     * @param string $appid
     * @return bool
     */
    public function shouldShowVcode($appid) {
        return $this->get($appid) >= self::VCODE_ERROR_THRESHOLD;
    }
}