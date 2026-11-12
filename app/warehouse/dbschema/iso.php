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

$db['iso']=array (
  'columns' =>
  array (
   'iso_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'extra' => 'auto_increment',
      
    ),
   'confirm' =>
    array (
      'type' => 'tinybool',
      'default' => 'N',
      'required' => true,
      'label' => '确认状态',
      'width' => 75,
      'hidden' => true,
      'editable' => false,
    ),
    'defective_status'=>array (
      'type' =>
      array (
          0 => '无需确认',
          1 => '未确认',
          2 => '已确认',
      ),
      'filtertype' => 'has',
      'filterdefault' => true,
      'default' => '0',
      'label' => '残损确认状态',
    ),
    'name' =>
    array (
      'type' => 'varchar(200)',
      'label' => '名称',
      'width' => 160,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'iso_bn' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '单号',
      'is_title' => true,
      'default_in_list'=>true,
      'searchtype' => 'has',
	  'in_list'=>true,
      'width' => 125,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'out_iso_bn' =>
    array (
      'type' => 'varchar(32)',
      'label' => '外部单号',
      'is_title' => true,
      'width' => 125,
      'in_list'=>true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'original_iso_bn' =>
    array (
      'type' => 'varchar(32)',
      'label' => '原始外部单号',
      'is_title' => true,
      'width' => 80,
      'in_list'=>true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'type_id' =>
    array (
      'type' => 'table:iostock_type@ome',
      'required' => true,
      'default_in_list'=>true,
	  'in_list'=>true,
      'comment' => '入库类型id',
      'label' => '入库类型',
      'filtertype' => 'has',
      'filterdefault' => true,
    ),
    'branch_id' =>
    array (
      'type' => 'table:branch@ome',
      'required' => true,
      'label' => '仓库ID',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'default_in_list'=>true,
	  'in_list'=>true,
    ),
    'supplier_id' =>
    array (
      'type' => 'number',
      'comment' => '供应商id',
    ),
    'supplier_name' =>
    array (
      'type' => 'varchar(32)',
      'label' => '供应商名称',
      'comment' => '供应商名称',
    ),
    'product_cost' =>
    array (
      'type' => 'money',
      'label' => '商品总额',
      'width' => 75,
      'default' => 0,
      'editable' => false,
      'in_list' => true,
    ),
    'iso_price' =>
    array (
      'type' => 'money',
      'label' => '入库费用',
      'required' => true,
      'default' => 0,
      'in_list'=>true,
    ),
    'cost_tax' =>
    array (
      'type' => 'money',
      'comment' => '税率',
    ),
    'oper' =>
    array (
      'type' => 'varchar(30)',
      'comment' => '经手人',
      'in_list'=>true,
      'label' => '经手人',
    ),
    'create_time' =>
    array (
      'type' => 'time',
      'comment' => '创建时间',
      'filtertype' => 'time',
      'filterdefault' => true,
      'default_in_list'=>true,
	  'in_list'=>true,
      'label' => '创建时间',
    ),
    'operator' =>
    array (
      'type' => 'varchar(30)',
      'comment' => '操作人员',
      'default_in_list'=>true,
	  'in_list'=>true,
      'label' => '操作人员',
    ),
    'complete_time' =>
    array (
        'type' => 'time',
        'comment' => '调拨出入库完成时间',
        'label' => '出入库完成时间',
    ),
    'memo' =>
    array (
        'type' => 'text',
        'comment' => '备注',
        'label'=>'备注',
        'in_list'=>true,
    ),
    'emergency' =>
    array (
        'type' => 'bool',
        'default' => 'false',
        'label' => '是否紧急',
        'width' => 60,
        'editable' => false,
        'in_list' => true,
    ),
    'iso_status' =>
    array (
      'type' =>
      array (
        1 => '未入库',
        2 => '部分入库',
        3 => '全部入库',
        4 => '取消',
      ),
      'default' => 1,
      'label' => '入库状态',
      'width' => 60,
      'editable' => false,
      'filtertype' => 'has',
	  'filterdefault' => true,
      'default_in_list'=>true,
      'in_list' => true,
    ),
    'check_status' =>
    array (
      'type' =>
      array (
        1 => '未审',
        2 => '已审',
      ),
      'default' => 1,
      'label' => '审核状态',
      'width' => 60,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filterdefault' => true,
    ),
    'extrabranch_id' =>
    array (
      'type' => 'number',
      'label' => '外部仓库名称',
     'default' => 0,
    ),
  ),
  'index' =>
  array (
    'ind_iso_bn' =>
    array (
        'columns' =>
        array (
          0 => 'iso_bn',
        ),
    ),
    'ind_supplier_id' =>
    array (
        'columns' =>
        array (
          0 => 'supplier_id',
        ),
    ),
    'ind_create_time' =>
    array (
        'columns' =>
        array (
          0 => 'create_time',
        ),
    ),
  ),
  'comment' => '转仓单信息表',
  'engine' => 'innodb',
  'version' => '$Rev:  51996',
);