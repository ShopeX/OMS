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

$db['product_serial']=array (
  'columns' => 
  array (
    'serial_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'branch_id' => 
    array (
      'type' => 'table:branch@ome',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'label' => '仓库',
      'width' => 110,
      'in_list' => true,
      'default_in_list' => false,
    ),
    'product_id' => 
    array (
      'type' => 'table:products@ome',
      'required' => true,
      'default' => 0,
      'editable' => false,
    ),
    'bn' => 
    array (
      'type' => 'varchar(30)',
      'required' => true,
      'default' => '',
      'editable' => false,
      'label' => '基础物料编码',
      'width' => 120,
      'in_list' => true,
      'default_in_list' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'nequal',
    ),
    'serial_number' => 
    array (
      'type' => 'varchar(30)',
      'required' => true,
      'default' => '',
      'editable' => false,
      'label' => '唯一码',
      'width' => 120,
      'is_title' => true,
       'in_list' => true,
      'default_in_list' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'status' =>
    array (
      'type' => array (
        '0' => '已入库',
        '1' => '已出库',
        '2' => '已作废', 
        '3' => '已退入',
        '4' => '已预占',
       ),
      'default' => '0',
      'required' => true,
      'label' => '状态',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'create_time' =>
    array (
        'type' => 'time',
        'label' => '创建时间',
        'editable' => false,
        'in_list' => true,
        'default_in_list' => true,
        'filtertype' => 'time',
        'filterdefault' => true,
    ),
    'update_time' =>
    array (
        'type' => 'time',
        'label' => '更新时间',
        'editable' => false,
        'in_list' => true,
        'default_in_list' => true,
        'filtertype' => 'time',
        'filterdefault' => true,
    ),
  ),
  'index' =>
  array (
    'idx_c_serial_number' =>
    array (
      'columns' =>
      array (
        0 => 'serial_number',
      ),
    ),
    'idx_c_status' =>
    array (
      'columns' =>
      array (
        0 => 'status',
      ),
    ),
  ),
  'comment' => '仓库货品唯一码表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);