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

$db['iso_items']=array (
  'columns' =>
  array (
     'iso_items_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
    ),
    'iso_id' =>
    array (
      'type' => 'table:iso@taoguaniostockorder',
      'required' => true,
      'default' => 0,
      'editable' => false,
    ),
    'iso_bn' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '出入库单号',
      'is_title' => true,
      'default_in_list'=>true,
	  'in_list'=>true,
      'width' => 125,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
     'product_id' =>
    array (
      'type' => 'table:products@ome',
      'required' => true,
    ),
    'product_name' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '货品名称',
    ),
    'bn' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '货号',
    ),
    
    'partcode'=> array(
        'type' => 'varchar(200)',
        'label' => '备件条码',
        'in_list'         => true,
        'default_in_list' => true,
        'editable' => false,
    ),
    'unit' =>
    array (
      'type' => 'varchar(20)',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '单位',
    ),
    'price' =>
    array (
      'type' => 'money',
      'required' => true,
      'editable' => false,
    ),
    'nums' =>
    array (
      'type' => 'number',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '出入库数量',
    ),
    'normal_num' =>
    array (
      'type' => 'number',
      'label' => '良品数量',
	  'default' => 0,
    'in_list' => true,
    'default_in_list' => true,
    ),
   'defective_num' =>
    array (
      'type' => 'number',
      'label' => '不良品数量',
	  'default' => 0,
    'in_list' => true,
    'default_in_list' => true,
    ),

    'sn'           => array(
      'type'            => 'text',
      'editable'        => false,
      'in_list'         => true,
      'default_in_list' => true,
      'label'           => '唯一码',
    ),
    'sn_list'      => array(
      'type'            => 'text',
      'editable'        => false,
      'in_list'         => true,
      'default_in_list' => true,
      'label'           => '唯一码列表',
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
   
    'ind_bn' =>
    array (
        'columns' =>
        array (
          0 => 'bn',
        ),
    ),
    'ind_partcode' =>
    array (
        'columns' =>
        array (
          0 => 'partcode',
        ),
    ),
    
  ),
  'comment' => '出入库单信息明细表',
  'engine' => 'innodb',
  'version' => '$Rev:  51996',
);
