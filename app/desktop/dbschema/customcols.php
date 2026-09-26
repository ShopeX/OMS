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

$db['customcols']=array (
  'columns' => 
  array (
    'id' => 
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
      'label' => 'ID',
    ),
    'at_time'       => array(
        'type'            => 'TIMESTAMP',
        'label'           => '创建时间',
        'default_in_list' => true,
        'in_list'         => true,
        'filtertype'      => 'time',
        'filterdefault'   => true,
        'default'         => 'CURRENT_TIMESTAMP',
        'order' => 100,
    ),
    'up_time'       => array(
        'type'            => 'TIMESTAMP',
        'label'           => '更新时间',
        'default_in_list' => true,
        'in_list'         => true,
        'filtertype'      => 'time',
        'filterdefault'   => true,
        'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'order' => 110,
    ),
    'tbl_name' => 
    array (
      'type' => 'varchar(50)',
      'required' => true,
      'label' => '表名',
    ),
    'p_order' => 
    array (
      'type' => 'number',
      'label' => '排序',
      'default' => 0,
    ),
    'disabled' => 
    array (
      'type' => 'bool',
      'label' => '无效状态',
      'default' => 'false',
    ),
    'col_name' => 
    array (
      'type' => 'varchar(50)',
      'label' => '字段名称',
      'required' => true,
    ),
    'col_key' => 
    array (
      'type' => 'varchar(50)',
      'label' => '字段键',
      'required' => true,
    ),
    'col_type' => array(
      'type' => 'varchar(20)',
      'label' => '列类型',
      'default' => 'input',
    ),
    'memo' => 
    array (
      'type' => 'varchar(50)',
      'label' => '字段描述',
    ),
    'filter_data' => array(
      'type' => 'text',
      'label' => '查询条件',
      'in_list' => false,
      'default_in_list' => false,
    ),
  ),
  'index'   => array(
    'idx_at_time'       => array('columns' => array('at_time')),
    'idx_up_time'       => array('columns' => array('up_time')),
    'idx_col_key'   => array('columns' => array('col_key','tbl_name'), 'prefix' => 'UNIQUE'),
),
'engine'  => 'innodb',
'version' => '$Rev:  $',
  'comment' => '自定义字段',	
);
