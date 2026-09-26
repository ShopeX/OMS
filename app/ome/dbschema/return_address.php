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

$db['return_address']=array (
  'columns' => 
  array (
   'address_id'=>array(
   'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
   ),
   'shop_id' =>
    array (
      'type' => 'table:shop@ome',
      'editable' => false,
      'label' => '店铺',
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault'   => true,
    ),
    'shop_type' =>
    array (
      'type' => 'varchar(50)',
      'label' => '店铺类型',
      'width' => 90,
      'editable' => false,
      'in_list' => true,
      'filtertype' => 'normal',
      'filterdefault'   => true,
      'filterdefault' => true,
      'default_in_list' => true,
    ),
    'contact_id' => 
    array (
      'type' => 'varchar(35)',
      'editable' => false,
      'label' => '地址库ID',
      'filtertype' => 'normal',
      'filterdefault'   => true,
      'default_in_list' => true,
      'in_list' => true,
    ),
    'contact_name' =>
    array (
      'type' => 'varchar(50)',
      'label'=>'联系人姓名',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'province' => 
    array (
      'type' => 'varchar(20)',
      'editable' => false,
      'label' => '省',
       'in_list' => true,
      'default_in_list' => true,
    ),
    'city' =>
    array (
      'type' => 'varchar(20)',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '市',
      'default_in_list' => true,
    ),
    'country' => 
    array (
      'type' => 'varchar(20)',
      'label'=>'区',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'street' => array (
        'type' => 'varchar(30)',
        'label' => '街道',
        'editable' => false,
        'in_list' => true,
        'default_in_list' => true,
    ),
    'addr' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'label' => '详细街道地址',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'zip_code' =>
    array (
      'type' => 'varchar(10)',
      'editable' => false,
      'label' => '地区邮政编码',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'phone' =>
    array (
      'type'=>'varchar(18)',
      'label'=>'电话号码',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'mobile_phone' => 
    array (
      'type'=>'varchar(15)',
      'label'=>'手机号码',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'seller_company' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'label' => '公司名称',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'memo' =>
    array (
      'type' => 'longtext',
      'editable' => false,
      'label' => '备注',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'area_id'=>array(
        'type'=>'number',
        'label'=>'区域ID',
        'in_list' => true,
        'default_in_list' => true,
    ),
    'get_def' =>
    array (
      'type' => 'bool',
      'label' => '是否默认取货地址',
      'comment' => '是否默认取货地址',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'default' => 'false',
    ),
    'cancel_def' =>
    array(
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,      
      'comment'=>'是否默认退货地址',
      'label'=>'是否默认退货地址',      
    ),
    'platform_create_time' => array (
        'type' => 'time',
        'label' => '平台创建时间',
        'editable' => false,
        'in_list' => true,
        'default_in_list' => true,
        'order' => 97,
    ),
    'platform_update_time' => array (
        'type' => 'time',
        'label' => '平台更新时间',
        'editable' => false,
        'in_list' => true,
        'default_in_list' => true,
        'order' => 98,
    ),
    'modify_date' => array(
        'type'=>'time',
        'label'=>'修改日期时间',
        'in_list' => true,
        'default_in_list' => true,
        'order' => 99,
    ),
    'address_type' => array (
        'type' => array (
            '0' => '备件库退货地址',
            '1' => '自主售后退货地址',
            '2' => '全部类型',
        ),
        'label' => '退货地址类型',
        'comment' =>'京东退货地址类型',
        'default' => '1',
        'in_list' => true,
        'default_in_list' => true,
        'filterdefault' => true,
        'filtertype' => 'normal',
    ),
    'reship_id' => array (
      'type' => 'int unsigned',
      'editable' => false,
      'label' => '退货单ID',
      'default' => 0,
    ),
    'wms_type' => array (
        'type' => 'varchar(30)',
        'editable' => false,
        'label' => 'WMS仓储类型',
        'filtertype' => 'normal',
        'filterdefault' => true,
    ),
    'service_bn' => array (
        'type' => 'varchar(50)',
        'label' => '服务单号',
        'editable' => false,
    ),
    'add_type' => array (
        'type' => array (
            'shop' => '店铺平台',
            'wms' => 'WMS仓储',
            'manual' => '手动创建',
        ),
        'label' => '来源类型',
        'comment' =>'来源类型',
        'default' => 'shop',
        'filtertype' => 'normal',
        'filterdefault' => true,
    ),
   'area'          => array(
       'type' => 'region',
       'label' => '收货地区',
       'width' => 170,
       'editable' => false,
   ),
    'md5_address' => array (
        'type' => 'varchar(32)',
        'label' => 'md5退货地址',
        'editable' => false,
        'in_list' => false,
        'default_in_list' => false,
        'order' => 99,
    ),
    'branch_bn' =>
    array (
      'type' => 'varchar(32)',
      'in_list' => true,
      'default_in_list' => true,
      'label' => '发货仓库编号',
      'searchtype' => 'nequal',
      'filterdefault' => true,
      'filtertype'  => 'textarea',
        'order'=>3
    ),
    'branch_name' => 
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'is_title' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'width' => 130,
      'label' => '发胡仓库名称',
        'order'=>2
    ),
  ),
  'index' => array (
      'in_reship_id' => array (
            'columns' => array (
                    0 => 'reship_id',
            ),
      ),
      
      'in_shop_address' => array (
          'columns' => array (
              0 => 'shop_id',
              1 => 'md5_address',
          ),
      ),
  ),
  'engine' => 'innodb',
  'version' => '$Rev:  $',
  'comment'=>'地址表',
);
