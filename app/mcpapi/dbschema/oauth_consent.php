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

// OAuth 同意记录：user_id + client_id 联合唯一，授权码签发前置条件
$db['oauth_consent'] = array(
    'columns' => array(
        'consent_id' => array('type' => 'number', 'required' => true, 'pkey' => true, 'extra' => 'auto_increment'),
        'user_id' => array('type' => 'number', 'required' => true),
        'client_id' => array('type' => 'varchar(128)', 'required' => true),
        'scopes' => array('type' => 'serialize', 'required' => true),
        'granted_at' => array('type' => 'int unsigned', 'required' => true),
        'revoked_at' => array('type' => 'int unsigned', 'required' => false, 'default' => null),
        'last_refreshed_at' => array('type' => 'int unsigned', 'required' => false, 'default' => null),
    ),
    'index' => array(
        'uni_user_client' => array('columns' => array('user_id', 'client_id'), 'prefix' => 'UNIQUE'),
        'ind_client_id' => array('columns' => array('client_id')),
    ),
    'engine' => 'innodb',
    'comment' => 'OAuth user consent registry',
);
