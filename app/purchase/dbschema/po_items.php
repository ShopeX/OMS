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

$db['po_items']=array (
  'columns' =>
  array (
    'item_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),

    'po_id' =>
    array (
      'type' => 'table:po',
      'required' => true,
      'editable' => false,
    ),
    'product_id' =>
    array (
      'type' => 'table:products@ome',
      'required' => true,
      'editable' => false,
    ),
    'num' =>
    array (
      'type' => 'number',
      'required' => true,
      'editable' => false,
    ),
    'in_num' =>
    array (
      'type' => 'number',
      'default' => 0,
      'required' => true,
      'editable' => false,
    ),
    'out_num' =>
    array (
      'type' => 'number',
      'default' => 0,
      'required' => true,
      'editable' => false,
    ),
    'price' =>
    array (
      'type' => 'money',
      'required' => true,
      'editable' => false,
    ),
    'purchasing_price' =>
        array (
            'type' => 'money',
            'default' => '0.000',
            'label' => '采购进价',
        ),
    'status' =>
    array (
      'type' =>
      array (
        1 => '待入库',
        2 => '部分入库',
        3 => '完成入库',
      ),
      'label' => '采购详情状态',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
    ),
    'barcode' =>
    array (
      'type' => 'varchar(32)',
      'label' => '条形码',
      'width' => 110,
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
    ),
    'bn' =>
    array (
      'type' => 'varchar(30)',
      'label' => '货号',
      'width' => 110,
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
    ),
    'name' =>
    array (
      'type' => 'varchar(200)',
      'label' => '货品名称',
      'width' => 110,
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
    ),
    'spec_info' =>
    array (
      'type' => 'longtext',
      'label' => '货品描述',
      'width' => 110,
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
    ),
    'memo' =>
    array (
      'type' => 'text',
      'editable' => false,
    ),
     'defective_num' =>
    array (
      'type' => 'number',
      'editable' => false,
      'required' => true,
      'default' => 0,
      'label' => '不良品',
    ),
    'sn'           => array(
          'type'            => 'text',
          'editable'        => false,
          'in_list'         => true,
          'default_in_list' => true,
          'label'           => '唯一码',
    ),
  ),
  'index' =>
  array (
    'ind_status' =>
    array (
      'columns' =>
      array (
        0 => 'status',
      ),
    ),
  ),
  'comment' => '采购单明细',
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
