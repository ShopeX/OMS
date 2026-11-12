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


$db['roles']=array (
  'columns' =>
  array (
    'role_id' =>
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'width' => 100,
      'label' => app::get('desktop')->_('角色ID'),
      'hidden' => false,
      'editable' => false,
      'extra' => 'auto_increment',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'role_name' =>
    array (
      'type' => 'varchar(100)',
      'required' => true,
      'label' => app::get('desktop')->_('角色名称'),
      'width' => 200,
      'in_list' => true,
      'is_title' => true,
      'searchtype'      => 'has',
      'default_in_list' => true,
      'filtertype'     => 'normal',
    ),
    'workground' =>
    array (
      'label' => app::get('desktop')->_('权限'),
      'type' => 'text',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'hidden' => false,
    ),
    'data_authority' =>
    array (
      'label' => app::get('desktop')->_('数据权限'),
      'type' => 'text',
      'editable' => false,
      'in_list' => false,
      'hidden' => true,
    ),
  ),
  'comment' => '管理员角色表',
  'version' => '$Rev: 40654 $',
);
