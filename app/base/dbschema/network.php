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
 
$db['network']=array (
  'columns' => 
  array (
    'node_id' => array (
      'type' => 'number',
      'label' => 'id',
      'required' => true,
      'width' => 100,
      'in_list' => true,
      'default_in_list' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
    ),
    'node_name' => 
    array (
      'type' => 'varchar(255)',
      'label' => app::get('base')->_('名称'),
      'required' => true,
      'width' => 150,
      'in_list' => true,
      'default_in_list' => true,
      'is_title' => true,
    ),
    'node_url' => 
    array (
      'type' => 'varchar(100)',
      'label' => app::get('base')->_('网址'),
      'width' => 150,
      'required' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'node_api' => 
    array (
      'type' => 'varchar(100)',
      'label' => app::get('base')->_('api地址'),
      'width' => 150,
      'required' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'link_status' => 
    array (
      'type' => 
      array (
        'active' => app::get('base')->_('正常'),
        'group' => app::get('base')->_('维护'),
        'wait' => app::get('base')->_('等待对方确认...'),
      ),
      'default' => 'wait',
      'width' => 100,
      'label' => app::get('base')->_('关联类型'),
      'required' => true,
      'in_list' => true,
    ),
    'node_detail' => 
    array (
      'type' => 'varchar(255)',
      'label' => app::get('base')->_('说明'),
      'width' => 300,
    ),
    'token' => 
    array (
      'type' => 'varchar(32)',
      'label' => app::get('base')->_('验证玛'),
    ),
  ),
  'comment' => '网络互联表',
  'version' => '$Rev: 41137 $',
  'ignore_cache' => true,
);