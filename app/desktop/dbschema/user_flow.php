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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
$db['user_flow']=array (
  'columns' => 
  array (
    'user_id' => array (
      'type' => 'table:users',
      'required' => true,
      'pkey' => true,
    ),
    'flow_id' => array (
      'type' => 'table:flow',
      'required' => true,
      'pkey' => true,
      'comment' => '信息id',
    ),
    'unread' => array (
      'type' => 'bool',
      'required' => true,
      'default'=>'true',
      'comment' => '是否已读',
    ),
    'note' => array (
      'type' => 'varchar(50)',
      'default'=>'',
      'comment' => '信息',
    ),
    'has_star' => array (
      'type' => 'bool',
      'required' => true,
      'default'=>'false',
      'comment'=> '是否标记',
    ),
    'keep_unread' => array (
      'type' => 'bool',
      'required' => true,
      'default'=>'false',
      'comment' => '保持未读',
    ),
  ),
  'comment' => '管理员和信息关联表',
  'version' => '$Rev$',
  'ignore_cache' => true,
);
