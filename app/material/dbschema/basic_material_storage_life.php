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

/**
 * 基础物料保质期明细数据结构
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

$db['basic_material_storage_life']=array (
  'columns' =>
  array (
    'bmsl_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'ID',
      'width' => 150,
      'hidden' => true,
      'editable' => false,
      'order'=>5,
    ),
    'bm_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'width' => 150,
      'hidden' => true,
      'editable' => false,
      'order'=>5,
    ),
    'material_bn' =>
    array (
      'type' => 'varchar(200)',
      'label' => '物料编码',
      'width' => 150,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'required'        => true,
      'order'=>10,
      'searchtype' => 'nequal',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'material_bn_crc32' =>
    array (
      'type' => 'bigint(13)',
      'label' => '货号查询索引值',
      'editable' => false,
      'required'        => true,
      'order'=>15,
    ),
    'expire_bn' =>
    array (
      'type' => 'varchar(200)',
      'label' => '物料保质期编码',
      'editable' => false,
      'is_title' => true,
      'in_list' => true,
      'default_in_list' => true,
      'required'        => true,
      'order'=>20,
      'searchtype' => 'nequal',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'production_date' =>
    array (
      'type' => 'time',
      'label' => '生产日期',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'required'        => true,
      'order'=>25,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'guarantee_period' =>
    array (
      'type' => 'number',
      'label' => '保质期时长',
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
      'required'        => true,
      'width' => 150,
      'order'=>30,
    ),
    'date_type' =>
    array (
      'type' => 'tinyint(1)',
      'label' => '时长类型',
      'editable' => false,
      'default' => 1,
      'required'        => true,
      'in_list' => false,
      'default_in_list' => false,
      'width' => 150,
      'order'=>31,
    ),
    'expiring_date' =>
    array (
      'type' => 'time',
      'label' => '过期日期',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'required'        => true,
      'order'=>40,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'warn_day' =>
    array (
      'type' => 'number',
      'label' => '预警天数',
      'editable' => false,
      'default'         => 0,
      'order'=>45,
    ),
    'warn_date' =>
    array (
      'type' => 'time',
      'label' => '预警日期',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'required'        => true,
      'order'=>98,
    ),
    'quit_day' =>
    array (
      'type' => 'number',
      'label' => '自动退出库存天数',
      'editable' => false,
      'default'         => 0,
      'order'=>55,
    ),
    'quit_date' =>
    array (
      'type' => 'time',
      'label' => '自动退出库存日期',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'required'        => true,
      'order'=>99,
      'filtertype' => 'time',
      'filterdefault' => true,
    ),
    'in_num'=>
    array(
      'type'=>'number',
      'label' => '入库数量',
      'editable' => false,
      'default'         => 0,
//       'in_list' => true,
//       'default_in_list' => true,
      'width' => 150,
      'order'=>65,
      'filtertype'=>true,
      'filterdefault'=>true,
    ),
    'out_num'=>
    array(
      'type'=>'number',
      'label' => '出库数量',
      'editable' => false,
      'default'         => 0,
//       'in_list' => true,
//       'default_in_list' => true,
      'width' => 150,
      'order'=>70,
      'filtertype'=>true,
      'filterdefault'=>true,
    ),
    'balance_num'=>
    array(
      'type'=>'number',
      'label' => '剩余数量',
      'editable' => false,
      'default'         => 0,
//       'in_list' => true,
//       'default_in_list' => true,
      'width' => 150,
      'order'=>75,
      'filtertype'=>true,
      'filterdefault'=>true,
    ),
    'freeze_num'=>
    array(
      'type'=>'mediumint(8) unsigned',
      'label' => '预占数量',
      'editable' => false,
      'default'         => 0,
//       'in_list' => true,
//       'default_in_list' => true,
      'width' => 150,
      'order'=>80,
    ),
    'branch_id' =>
    array (
      'type' => 'table:branch@ome',
      'label' => '仓库',
      'required' => true,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'order'=>85,
      'filtertype'=>'normal',
      'filterdefault'=>true,
    ),
    'is_change' =>
    array (
      'type' => 'tinyint(1)',
      'label' => '是否改期',
      'editable' => false,
      'default'         => 2,
      'required'        => true,
      'order'=>90,
    ),
    'status' =>
    array (
      'type' => 'tinyint(1)',
      'label' => '状态',
      'editable' => false,
      'default'  => 1,
      'required' => true,
    ),
  ),
  'index' =>
  array (
    'ind_expire_bn' =>
    array (
        'columns' =>
        array (
          0 => 'expire_bn',
          1 => 'branch_id',
          2 => 'bm_id',
        ),
        'prefix' => 'unique',
    ),
    'ind_bm_id' =>
    array (
        'columns' =>
        array (
          0 => 'bm_id',
        ),
    ),
  ),
  'comment' => '基础物料保质期明细数据',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
