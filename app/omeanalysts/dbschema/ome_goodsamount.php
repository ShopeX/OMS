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

$db['ome_goodsamount']=array (
  'columns' => 
  array (
    'id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'ID',
      'width' => 110,
      'hidden' => true,
      'editable' => false,
	  'filtertype' => 'normal',
    ),
    'goods_bn' => 
    array (
      'type' => 'varchar(200)',
      'label' => '商品货号',
      'width' => 120,
      'searchtype' => 'head',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
	  'filtertype' => 'normal',
    ),
    'name' => 
    array (
      'type' => 'varchar(200)',
      'required' => true,
      'default' => '',
      'label' => '商品名称',
      'is_title' => true,
      'default_in_list' => true,
      'width' => 260,
      'searchtype' => 'has',
      'editable' => false,
      'filtercustom' => 
      array (
        'has' => '包含',
        'tequal' => '等于',
        'head' => '开头等于',
        'foot' => '结尾等于',
      ),
      'in_list' => true,
    ),
    
    'brand_id' => 
    array (
      'type' => 'table:brand@ome',
      'sdfpath' => 'brand/brand_id',
      'label' => '品牌',
      'width' => 75,
      'editable' => false,
      'hidden' => true,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
	'sales_num' =>
    array (
      'type' => 'number',
      'editable' => false,
	  'label' => '销量',
	  'filtertype' => 'normal',
	  'filterdefault' => true,
      'in_list' => true,
	  'default' => 0,
    ),
	'store' =>
    array (
      'type' => 'number',
      'editable' => false,
	  'label' => '当天库存',
      'in_list' => true,
	  'default' => 0,
    ),
	'purchase_num' =>
    array (
      'type' => 'number',
      'editable' => false,
	  'label' => '采购入库数量',
      'in_list' => true,
	  'default' => 0,
    ),
	'allocation_num' =>
    array (
      'type' => 'number',
      'editable' => false,
	  'label' => '调拨入库数量',
      'in_list' => true,
      'default' => 0,
    ),
	'shop_id' => 
    array (
	  'type' => 'table:shop@ome',
      'sdfpath' => 'shop/shop_id',
      'required' => false,
      'editable' => false,
	  'label' => '所属店铺',
    ),
	'spec_info' => 
    array (
      'type' => 'varchar(250)',
      'label' => '规格',
      'width' => 110,
      'filtertype' => 'normal',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
	'createtime' =>
    array (
      'type' => 'time',
      'label' => '所属时间',
      'width' => 130,
      'editable' => false,
      'in_list' => true,
	  'default_in_list' => true,
    ),
  ),
  'comment' => '商品表',
  'index' => 
  array (
    'ind_goods_bn' => 
    array (
      'columns' => 
      array (
        0 => 'goods_bn',
      ),
    ),
  ),
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
