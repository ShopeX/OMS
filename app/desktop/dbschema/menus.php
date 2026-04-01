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
$db['menus']=array (
  'columns' => 
  array (
    'menu_id'=>array(
      'type' => 'number',
      'pkey' => true,
      'extra' => 'auto_increment',
    ),
    'menu_type' => 
    array (
      'type' => 'varchar(80)',
      'required' => true,
      'width' => 100,
      'in_list' => true,
      'default_in_list' => true,
      'comment' => '菜单类型',
    ),
    'app_id' => 
    array (
      'type' => 'table:apps@base',
      'required' => true,
      'width' => 100,
      'in_list' => true,
      'default_in_list' => true,
      'comment' => '所属app(应用)ID',
    ),
    'workground'=>array(
        'type'=>'varchar(200)',
    	'comment'=>'顶级菜单',
    ),
     'menu_group'=>array(
        'type'=>'varchar(200)',
     	'comment'=>'菜单组',
    ),
    'menu_title'=>array(
        'type'=>'varchar(100)',
        'is_title'=>true,
    	'comment'=>'菜单标题',
    ),
    'menu_path'=>array(
        'type'=>'varchar(255)',
    	'comment'=>'菜单对应执行的url路径',
    ),
    'disabled'=>array(
        'type'=>'bool',
        'default'=>'false',
    	'comment'=>'是否有效',
    ),
     'display'=>array(
        'type'=>"enum('true', 'false')",
        'default'=>'false',
     	'comment'=>'是否显示',
    ),
    'permission'=>array(
        'type'=>'varchar(80)',
    	'comment'=>'权限',
    ),
    'addon'=>array(
        'type'=>'text',
    	'comment'=>'额外信息',
    ),
    'target'=>array(
        'type'=>'varchar(255)',
        'default'=>'',
    	'comment'=>'跳转'
    ),
    'menu_order'=>array(
        'type' => 'int unsigned',
        'default'=>'0',
    	'comment'=>'排序'
    ),
    'icon'=>array(
        'type'=>'varchar(50)',
        'comment'=>'图标',
    ),
    'en'=>array(
        'type'=>'varchar(100)',
        'comment'=>'英文名称',
    ),
  ),
  'index' => 
  array (
    'ind_menu_type' => 
    array (
      'columns' => 
      array (
        0 => 'menu_type',
      ),
    ),
    'ind_menu_path' => 
    array (
      'columns' => 
      array (
        0 => 'menu_path',
      ),
    ),
    'ind_menu_order' => 
    array (
      'columns' => 
      array (
        0 => 'menu_order',
      ),
    ),
  ),
  'comment' => '后台菜单表',
  'version' => '$Rev: 44008 $',
  'unbackup' => true,
);
