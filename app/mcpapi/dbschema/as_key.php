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

// AS 签名密钥（RS256）：同一 AS 可持有多个 kid 实现平滑轮换；私钥仅落库，从不外发
$db['as_key'] = array(
    'columns' => array(
        'kid' => array('type' => 'varchar(128)', 'required' => true, 'pkey' => true),
        'algorithm' => array('type' => 'varchar(32)', 'required' => true),
        'public_key' => array('type' => 'text', 'required' => true),
        'private_key' => array('type' => 'text', 'required' => true),
        'status' => array('type' => array('active' => '使用中', 'retired' => '已退役'), 'default' => 'active', 'required' => true),
        'created_at' => array('type' => 'int unsigned', 'required' => true),
        'retired_at' => array('type' => 'int unsigned', 'required' => false, 'default' => null),
    ),
    'index' => array(
        'ind_status' => array('columns' => array('status')),
    ),
    'engine' => 'innodb',
    'comment' => 'OAuth authorization server signing keys (RS256)',
);
