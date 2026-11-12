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
$db['zero_sale']=array (
  'columns' => 
  array (
    'bpsd_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'label' => 'ID',
    ),
    'branch_id' => 
    array (
	  'type' => 'table:branch@ome',
      'required' => false,
      'editable' => false,
	  'label' => '线上仓库',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'order' => 1,
      'width' => 130,
    ),
    'product_id' => 
    array (
	  'type' => 'table:products@ome',
      'required' => false,
      'editable' => false,
	  'label' => '货品ID',
    ),
	'bn' =>
    array (
      'type' => 'varchar(60)',
      'editable' => false,
	  'label' => '货号',
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'order' => 2,
      'width' => 100,
    ),
	'name' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
	  'label' => '商品名称(规格)',
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
	  'default' => 0,
      'order' => 4,
      'width' => 200,
    ),
    'type_id' =>
    array (
      'type' => 'table:goods_type@ome',
      'editable' => false,
	  'label' => '商品类目',
      'in_list' => true,
      'default_in_list' => true,
	  'default' => 0,
      'order' => 3,
      'width' => 120,
    ),
    'brand_id' =>  
    array (
      'type' => 'table:brand@ome',
      'label' => '品牌',
      'width' => 90,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'order' => 6,
    ),
	'months' =>
    array (
      'type' => 'varchar(8)',
      'label' => '统计时间',
      'width' => 130,
      'editable' => false,
      'order' => 7,
    ),
    'days' =>
    array (
      'type' => 'varchar(32)',
      'default' => '0000000000000000000000000000000',
      'label' => '记录每天销售情况,用32位2进制存储',
      'editable' => false,
    ),
  ),
  'comment' => '零销售产品分析',
  'index' => 
  array (
    
    
    'ind_bn' => 
    array (
      'columns' => 
      array (
        0 => 'bn',
      ),
    ),
    'ind_month_day' => 
    array (
      'columns' => 
      array (
        0 => 'months',
        1 => 'days',
      ),
    ),
  ),
  'engine' => 'innodb',
  'version' => '$Rev: 44513 $',
);
