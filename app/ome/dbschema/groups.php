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

$db['groups']=array (
  'columns' => 
  array (
    'group_id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'name' => 
    array (
      'type' => 'varchar(100)',
      'required' => true,
      'is_title' => true,
      'label' => '名称',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'config' => 
    array (
      'type' => 'text',
      'editable' => false,
    ),
    'description' =>
    array (
      'type' => 'text',
      'editable' => false,
    ),
    'g_type' =>
    array (
      'type' => 'varchar(20)',
      'editable' => false,
      'required' => true,
      'default' => 'confirm',
      'label' => '所属版块',
    ),
    'org_id' =>
    array (
      'type' => 'table:operation_organization@ome',
      'label' => '运营组织',
      'editable' => false,
      'width' => 60,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'disabled' =>
    array (
      'type' => 'bool',
      'default' => 'false',
      'editable' => false,
      'required' => true,
    ),
  ),
  'comment' => '管理员组',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);