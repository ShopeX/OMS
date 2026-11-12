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
$db['inventory_apply']=array (
  'columns' =>
  array (
    'inventory_apply_id' =>
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
    ),
    'inventory_apply_bn' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '盘点申请单号',
      'width' => 140,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'nequal',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'out_id' =>
    array (
      'type' => 'varchar(50)',
      'in_list' => true,
      'default_in_list' => true,
      'label' => '盘点仓库',
    ),
    'branch_id' =>
    array (
      'type' => 'table:branch@ome',
      'in_list' => false,
      'default_in_list' => false,
      'label' => '盘盈仓库',
    ),
    'negative_branch_id' =>
    array (
      'type' => 'text',
      'in_list' => false,
      'default_in_list' => false,
      'label' => '良品仓库',
    ),
    'negative_cc_branch_id' =>
    array (
      'type' => 'text',
      'in_list' => false,
      'default_in_list' => false,
      'label' => '残品仓库',
    ),
    'wms_id' =>
    array (
      'type' => 'varchar(50)',
      'required' => true,
      'label' => 'WMS编号',
    ),
    'status' =>
    array(
      'type' =>
      array (
        'unconfirmed' => '未确认',
        'confirming' => '确认中',
        'confirmed' => '已确认',
        'closed' => '已关闭',
      ),
      'default' => 'unconfirmed',
      'required' => true,
      'label' => '状态',
      'width' => 100,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'sku_hang' =>
    array (
      'type' => 'int',
      'label' => '物料行数',
      'width' => 120,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'sku_total' =>
    array (
      'type' => 'int',
      'label' => '物料总数',
      'width' => 120,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'inventory_date' =>
    array (
      'type' => 'time',
      'label' => '盘点日期',
      'width' => 150,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'process_date' =>
    array (
      'type' => 'time',
      'label' => '处理时间',
      'width' => 150,
      'in_list' => true,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'memo' =>
    array (
      'type' => 'text',
      'label' => '备注',
      'width' => 150,
      'in_list' => true,
    ),
    'at_time'       => array(
        'type'    => 'TIMESTAMP',
        'label'   => '创建时间',
        'default' => 'CURRENT_TIMESTAMP',
        'width'   => 120,
        'in_list' => false,
        'order'   => 11,
    ),
    'up_time'       => array(
        'type'    => 'TIMESTAMP',
        'label'   => '更新时间',
        'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'width'   => 120,
        'in_list' => false,
        'order'   => 11,
    ),
  ),
  'index'   => array(
      'ind_inventory_apply_bn' => array(
          'columns' => array(
              0 => 'inventory_apply_bn',
          ),
          'prefix'  => 'unique',
      ),
  ),
  'comment' => '盘点申请表',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
