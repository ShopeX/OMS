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

$db['member_address']=array (
  'columns' => 
  array (
    'address_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'ID',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
    ),
    'member_id' => 
    array (
      'type' => 'table:members@ome',
      'required' => true,
      'width' => 110,
      'editable' => false,
      'in_list' => true,
    ),
    'ship_name' =>
    array (
      'type' => 'varchar(255)',
      'label' => '收货人',
      'width' => 60,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => false,
    ),
    'ship_area' =>
    array (
      'type' => 'region',
      'label' => '收货地区',
      'width' => 170,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
    ),
    'ship_addr' =>
    array (
      'type' => 'text',
      'label' => '收货地址',
      'width' => 180,
      'editable' => false,
      'filtertype' => 'normal',
   
      'in_list' => true,
    ),
    'ship_mobile' =>
    array (
      'label' => '收货人手机',
    
      'type' => 'varchar(200)',
      'editable' => false,
      'width' => 85,
    
      'in_list' => true,
    ),
    'ship_tel' =>
    array (
      'type' => 'varchar(200)',
      'label' => '收货人电话',
      'width' => 75,
      'editable' => false,
     
      'in_list' => true,
    ),
    
    'ship_zip' =>
    array (
      'label' => '收货邮编',
      'type' => 'varchar(20)',
      'editable' => false,
    
      'in_list' => true,
    ),
    'ship_email' =>
    array (
      'label' => '收货邮箱',
      'type' => 'varchar(150)',
      'editable' => false,
     
    ),
    'is_default' =>
    array (
      'type' => 'tinyint(1)',
      'required' => true,
      'default' => 0,
      'editable' => false,
    ),
    'address_hash' =>
    array (
      'type' => 'bigint(13)',
      'label' => '合并识别号',
      'editable' => false,
    ),
    
  ),
  'index' =>
  array (
    
    'ind_address'=>
    array (
      'columns' =>
      array (
        0 => 'member_id',
        1 => 'address_hash',
      ),
      'prefix' => 'unique',
    ),
  ),
  'comment' => '会员地址库',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
