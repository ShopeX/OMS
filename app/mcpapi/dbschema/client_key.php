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

$db['client_key'] = array(
    'columns' => array(
        'client_key_id' => array('type' => 'number', 'required' => true, 'pkey' => true, 'extra' => 'auto_increment'),
        'client_id' => array('type' => 'varchar(128)', 'required' => true),
        'kid' => array('type' => 'varchar(128)', 'required' => true),
        'algorithm' => array('type' => 'varchar(32)', 'required' => true),
        'public_key' => array('type' => 'varchar(4096)', 'required' => true),
        'status' => array('type' => 'varchar(32)', 'required' => true),
        'created_at' => array('type' => 'time', 'required' => true),
        'activated_at' => array('type' => 'time', 'required' => false, 'default' => null),
        'revoked_at' => array('type' => 'time', 'required' => false, 'default' => null),
        'revoked_reason' => array('type' => 'varchar(255)', 'required' => false, 'default' => null),
    ),
    'index' => array(
        'ind_client_id' => array('columns' => array('client_id')),
        'uni_client_kid' => array('columns' => array('client_id', 'kid'), 'prefix' => 'UNIQUE'),
        'ind_active_key' => array('columns' => array('client_id', 'status', 'revoked_at')),
    ),
    'engine' => 'innodb',
    'comment' => 'MCPAPI client public key lifecycle registry',
);
