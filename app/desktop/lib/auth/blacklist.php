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

class desktop_auth_blacklist
{
    private $kvstoreKey = 'pam_token_blacklist';
    private $kvstore;

    public function __construct()
    {
        $this->kvstore = base_kvstore::instance($this->kvstoreKey);
    }

    public function add(string $jti, int $ttlRemaining): void
    {
        if ($ttlRemaining <= 0) {
            return;
        }
        $this->kvstore->store('blacklist_' . $jti, '1', $ttlRemaining);
    }

    public function isBlacklisted(string $jti): bool
    {
        $value = null;
        $this->kvstore->fetch('blacklist_' . $jti, $value);
        return $value !== null && $value === '1';
    }
}
