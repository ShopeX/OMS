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

$db['store']=array (
  'columns' =>
  array (
    'store_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'width' => 110,
      'hidden' => true,
      'editable' => false,
      'pkey' => true,
      'extra' => 'auto_increment',
    ),
    'store_name' =>
    array (
      'type' => 'varchar(255)',
      'label' => '门店名称',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'store_bn' =>
    array (
      'type' => 'varchar(20)',
      'label' => '门店编码',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'cat_id' =>
    array (
      'type' => 'int(10)',
      'label' => '门店类目',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'outer_store_id' =>
    array (
      'type' => 'int(10)',
      'label' => '淘宝门店ID',
      'editable' => false,
      'in_list' => true,
    ),
    'local_store_id' =>
    array (
      'type' => 'int(10)',
      'label' => '本地门店ID',
      'editable' => false,
    ),
    'store_type' =>
    array (
      'type' =>
      array (
        'normal' => '普通门店',
        'mall' => '商城',
        'mall_shop' => '店中店',
        'light_shop' => '淘小铺',
        'hospital' => '阿里健康(医院)',
        'department' => '阿里健康(医院科室)',
        'warehous' => '仓库',
      ),
      'required' => true,
      'default' => 'normal',
      'editable' => false,
      'label' => '门店类型',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'open_hours' =>
    array (
      'type' => 'varchar(20)',
      'label' => '营业时间',
      'editable' => false,
    ),
    'status' =>
    array (
      'type' =>
      array (
        'hold' => '暂停营业',
        'close' => '关店',
        'normal' => '正常',
      ),
      'required' => true,
      'default' => 'normal',
      'editable' => false,
      'label' => '门店状态',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'contacter' =>
    array (
      'type' => 'varchar(50)',
      'label' => '联系人',
      'width' => 60,
      'searchtype' => 'head',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'area' =>
    array (
      'type' => 'region',
      'label' => '地区',
      'width' => 170,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'address' =>
    array (
      'type' => 'varchar(100)',
      'label' => '详细地址',
      'width' => 180,
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'tel' =>
    array (
      'type' => 'varchar(30)',
      'label' => '固定电话',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'mobile' =>
    array (
      'label' => '手机',
      'hidden' => true,
      'type' => 'varchar(50)',
      'editable' => false,
      'width' => 85,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'fax' => 
    array (
      'type' => 'varchar(20)',
      'label' => '传真',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'zip' =>
    array (
      'label' => '邮编',
      'type' => 'varchar(20)',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
   'sync' =>
    array (
      'type' => 'tinyint(1)',
      'default' => '1',
      'label' => '同步状态',
      'in_list' => true,
      'default_in_list' => true,
    ),
  ),
  'comment' => '淘宝门店信息表',
  'index' =>
  array (
    'ind_outer_store_id' =>
    array (
      'columns' =>
      array (
        0 => 'outer_store_id',
      ),
      'prefix' => 'unique',
    ),
    'ind_local_store_id' =>
    array (
      'columns' =>
      array (
        0 => 'local_store_id',
      ),
      'prefix' => 'unique',
    ),
  ),
  'engine' => 'innodb',
);