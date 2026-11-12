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
 * 销售物料扩展数据结构
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

$db['sales_material_ext']=array (
  'columns' =>
  array (
    'sm_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'width' => 150,
      'hidden' => true,
      'editable' => false,
      'pkey' => true,
      'comment' => '销售物料ID,关联material_sales_material.sm_id'
    ),
    'cost' =>
    array (
      'type' => 'money',
      'default' => '0.000',
      'label' => '成本价',
      'width' => 150,
      'comment'=>'销售物料固定成本价',
    ),
    'lowest_price' =>
    array (
      'type' => 'money',
      'default' => '0.000',
      'label' => '最低售价',
      'width' => 150,
      'comment'=>'销售物料最低售价',
    ),
    'retail_price' =>
    array (
      'type' => 'money',
      'default' => '0.000',
      'label' => '零售价',
      'width' => 150,
      'comment'=>'销售物料零售价',
    ),
    'weight' =>
    array (
      'type' => 'decimal(20,3)',
      'label' => '重量',
      'default' => '0.000',
      'width' => 150,
      'editable' => false,
      'comment' => '销售物料重量,单位:g',
    ),
    'unit' =>
    array (
      'type' => 'varchar(20)',
      'label' => '包装单位',
      'width' => 150,
      'editable' => false,
    ),
    'brand_id' =>
    array(
        'type' => 'table:brand@ome',
        'label' => '品牌id',
        'width' => 150,
        'editable' => false,
        'comment' => '品牌ID,关联ome_brand.brand_id',
    ),
    'cat_id' => array (
        'type' => 'table:basic_material_cat@material',
        'required' => false,
        'default' => 0,
        'label' => '分类',
        'width' => 150,
        'editable' => true,
        'filtertype' => 'normal',
        'filterdefault' => true,
        'in_list' => true,
        'default_in_list' => true,
        'comment' => '分类ID,关联material_basic_material_cat.cat_id'
    ),
    'cat_path' => array (
        'type' => 'varchar(100)',
        'default' => '',
        'label' => '分类路径',
        'width' => 150,
        'editable' => false,
        'in_list' => true,
        'comment' => '分类路径(从根至本结点的路径,逗号分隔)',
    ),
  ),
  'comment' => '销售物料扩展表,用于存储销售物料的额外属性信息',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
