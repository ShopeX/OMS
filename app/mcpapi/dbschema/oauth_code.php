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

// OAuth 授权码：单次消费，明文 code 只在签发响应中出现，库中存 SHA-256 哈希
$db['oauth_code'] = array(
    'columns' => array(
        'code_hash' => array('type' => 'varchar(64)', 'required' => true, 'pkey' => true),
        'client_id' => array('type' => 'varchar(128)', 'required' => true),
        'user_id' => array('type' => 'number', 'required' => true),
        'redirect_uri' => array('type' => 'varchar(1024)', 'required' => true),
        'scopes' => array('type' => 'serialize', 'required' => true),
        'code_challenge' => array('type' => 'varchar(128)', 'required' => false, 'default' => null),
        'challenge_method' => array('type' => 'varchar(16)', 'required' => false, 'default' => null),
        'expires_at' => array('type' => 'int unsigned', 'required' => true),
        'consumed_at' => array('type' => 'int unsigned', 'required' => false, 'default' => null),
        'created_at' => array('type' => 'int unsigned', 'required' => true),
    ),
    'index' => array(
        'ind_client_id' => array('columns' => array('client_id')),
        'ind_user_id' => array('columns' => array('user_id')),
        'ind_expires_at' => array('columns' => array('expires_at')),
    ),
    'engine' => 'innodb',
    'comment' => 'OAuth authorization codes (hash only, single use)',
);
