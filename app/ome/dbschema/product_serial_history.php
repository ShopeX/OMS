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

$db['product_serial_history']=array (
  'columns' => 
  array (
    'history_id' => 
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
      'label' => '仓库名称',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'bn' =>
    array (
      'type' => 'varchar(30)',
      'label' => '基础物料编码',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'nequal',
    ),
    'product_name' =>
    array (
      'type' => 'varchar(200)',
      'label' => '基础物料名称',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'act_type' => 
    array (
      'type' => 'tinyint',
      'label' => '操作类型',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'act_time' => 
    array (
      'type' => 'time',
      'label' => '操作时间',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'act_owner' => 
    array (
      'type' => 'table:account@pam',
      'label' => '操作人',
      'required' => true,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'bill_type' => 
    array (
      'type' => 'tinyint',
      'label' => '单据类型',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'bill_id' => 
    array (
      'type' => 'int unsigned',#暂只有导入使用
      'comment' => '单据id',
      'label' => '单据id',
      'default' => 0,
      'editable' => false,
    ),
    'bill_no' =>
    array (
      'type' => 'varchar(30)',
      'label' => '单据编号',
      'required' => true,
      'default' => '',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'yes',
      'filterdefault' => true,
    ),
    'serial_number' => 
    array (
      'type' => 'varchar(64)',
      'required' => true,
      'editable' => false,
      'label' => '唯一码',
      'is_title' => true,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'nequal',
    ),
    'imei_number' => 
    array (
      'type' => 'varchar(512)',
      'default' => '',
      'editable' => false,
      'label' => 'IMEI码',  // 多个用“,”隔开
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'nequal',
    ),
    'sync'               => array(
        'type'          => array(
            'none' => '未回写',
            'run'  => '运行中',
            'fail' => '回写失败',
            'succ' => '回写成功',
        ),
        'default'       => 'none',
        'label'         => '回写状态',
        'editable'      => false,
        'in_list' => true,
        'default_in_list' => true,
        'filtertype'    => 'yes',
        'filterdefault' => true,
    ),
  ),
  'index' => 
  array (
    'ind_bill_id' => 
    array (
        'columns' =>
        array (
          0 => 'bill_id',
        ),
    ),
    'ind_bill_no' => 
    array (
        'columns' =>
        array (
          0 => 'bill_no',
        ),
    ),
    'ind_sync' => 
    array (
        'columns' =>
        array (
          0 => 'sync',
        ),
    ),
    'idx_c_serial_number' => 
    array (
        'columns' =>
        array (
          0 => 'serial_number',
        ),
    ),
  ),
  'comment' => '唯一码历史记录表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);