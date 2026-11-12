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

// OAuth Refresh Token：轮换检测，明文 token 只出现一次，库中存 SHA-256 哈希
$db['oauth_refresh_token'] = array(
    'columns' => array(
        'token_hash' => array('type' => 'varchar(64)', 'required' => true, 'pkey' => true),
        'user_id' => array('type' => 'number', 'required' => true),
        'client_id' => array('type' => 'varchar(128)', 'required' => true),
        'scopes' => array('type' => 'serialize', 'required' => true),
        'status' => array('type' => array('active' => '有效', 'rotated' => '已轮换', 'revoked' => '已吊销'), 'default' => 'active', 'required' => true),
        'token_version' => array('type' => 'number', 'required' => true, 'default' => 1),
        'expires_at' => array('type' => 'int unsigned', 'required' => true),
        'rotated_at' => array('type' => 'int unsigned', 'required' => false, 'default' => null),
        'replaced_by_token_hash' => array('type' => 'varchar(64)', 'required' => false, 'default' => null),
        'created_at' => array('type' => 'int unsigned', 'required' => true),
    ),
    'index' => array(
        'ind_user_id' => array('columns' => array('user_id')),
        'ind_client_id' => array('columns' => array('client_id')),
        'ind_status' => array('columns' => array('status', 'expires_at')),
    ),
    'engine' => 'innodb',
    'comment' => 'OAuth refresh tokens (hash only, rotation tracked)',
);
