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
 
$db['flow']=array (
  'columns' => 
  array (
    'flow_id' => 
    array (
      'label' => app::get('desktop')->_('序号'),
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'flow_from' => 
    array (
      'label' => app::get('desktop')->_('发送者'),
      'type' => 
      array (
        'user' => app::get('desktop')->_('管理员'),
        'system' => app::get('desktop')->_('系统'),
        'internet' => app::get('desktop')->_('站外'),
        'user' => app::get('desktop')->_('用户'),
      ),
      'default' => 'system',
      'required' => true,
      'in_list' => true,
    ),
    'from_id' => 
    array (
      'type' => 'number',
      'default' => 0,
      'editable' => false,
      'comment' => '发送者id',
    ),
    'subject' => 
    array (
      'label' => app::get('desktop')->_('消息标题'),
      'type' => 'varchar(50)',
      'required' => true,
      'default' => '',
      'editable' => false,
      'in_list' => true,
      'is_title' => true,
    ),
    'flow_desc' => 
    array (
      'label' => app::get('desktop')->_('消息描述'),
      'type' => 'varchar(100)',
      'required' => true,
      'default' => '',
      'editable' => false,
      'in_list' => true,
    ),
    'body' => 
    array (
      'label' => app::get('desktop')->_('内容本体'),
      'type' => 'text',
      'required' => true,
      'default' => '',
      'editable' => false,
      'in_list' => true,
    ),
    'flow_ip' => 
    array (
      'type' => 'varchar(20)',
      'default' => '',
      'required' => true,
      'editable' => false,
      'comment' => 'ip',
    ),
    'send_mode'=>array(
        'type'=>array(
            'direct'=>app::get('desktop')->_('直送'),
            'broadcast'=>app::get('desktop')->_('广播'),
            'fetch'=>app::get('desktop')->_('收取'),
        ),
        'default' => 'direct',
        'required' => true,
    ),
    'flow_type' => 
    array (
      'type' => 'varchar(32)',
      'default' => 'default',
      'required' => true,
      'editable' => false,
      'comment' => '消息类型',
    ),
    'send_time'=>array(
        'type'=>'time',
        'required' => true,
    	'comment' => '发送时间',
    )
  ),
  'comment' => app::get('desktop')->_('信息表'),
  'version' => '$Rev$',
);
