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

$db['products']=array (
  'columns' =>
  array (
    'product_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => '货品ID',
      'width' => 110,
      'editable' => false,
    ),
    'goods_id' =>
    array (
      'type' => 'table:goods@ome',
      'default' => 0,
      'required' => true,
      'label' => '商品ID',
      'width' => 110,
      'editable' => false,
    ),
    'title' =>
    array (
      'type' => 'varchar(255)',
      'sdfpath' => 'title',
      'label' => '',
      'width' => 110,
      'editable' => false,
    ),
    'bn' =>
    array (
      'type' => 'varchar(30)',
      'label' => '基础物料编码',
      'width' => 150,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'name' =>
    array (
      'type' => 'varchar(200)',
      'required' => true,
      'default' => '',
      'label' => '基础物料名称',
      'width' => 190,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'spec_info' =>
    array (
      'type' => 'longtext',
      'label' => '规格',
      'width' => 110,
      'filtertype' => 'normal',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'store' =>
    array (
      'type' => 'number',
      'editable' => false,
      'comment' => '库存（各仓库 的库存总和）',
      'label' => '库存量',
      'default' => 0,
      'width' => 65,
      'in_list' => true,
      'filtertype' => 'number',
      'filterdefault' => true,
      'default_in_list' => true,
      'label' => '库存',
    ),
    'store_freeze' =>
    array (
      'type' => 'number',
      'sdfpath' => 'freez',
      'label' => '冻结库存',
      'width' => 65,
      'hidden' => true,
      'filtertype' => 'number',
      'filterdefault' => true,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
      'default' => 0,
    ),
    'price' =>
    array (
      'type' => 'money',
      'sdfpath' => 'price/price/price',
      'default' => '0',

      'label' => '销售价格',
      'width' => 75,
    ),
    'cost' =>
    array (
      'type' => 'money',
      'sdfpath' => 'price/cost/price',
      'default' => '0',
      'label' => '成本价',
      'width' => 110,
      'editable' => false,
      'filtertype' => 'normal',         
      //'in_list' => true,   
      //'default_in_list' => true, 
    ),
    'mktprice' =>
    array (
      'type' => 'money',
      'sdfpath' => 'price/mktprice/price',
      'label' => '市场价',
      'width' => 75,
    ),
    'weight' =>
    array (
      'type' => 'decimal(20,3)',
      'label' => '重量',
      'width' => 110,
      'filtertype' => 'number',
      'filterdefault' => true,
      'editable' => false,
      'in_list' => true,
    ),
    'barcode' =>
    array (
      'type' => 'varchar(32)',
      'label' => '条形码',
      'width' => 110,
      'editable' => false,
      'filtertype' => 'number',
      'filterdefault' => true,
      'searchtype' => 'has',
      'in_list' => true,
	  'default_in_list' => true,
    ),
    'unit' =>
    array (
      'type' => 'varchar(20)',
      'label' => '单位',
      'width' => 110,
      'filtertype' => 'normal',
      'editable' => false,
      'in_list' => true,
    ),
    'spec_desc' =>
    array (
      'type' => 'serialize',
      'label' => '规格值,序列化',
      'width' => 110,
      'editable' => false,
    ),
    'uptime' =>
    array (
      'type' => 'time',
      'label' => '录入日期',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
    ),
    'last_modified' =>
    array (
      'type' => 'last_modify',
      'label' => '最后修改日期',
      'width' => 90,
      'editable' => false,
      'in_list' => true,
    ),
    'disabled' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => false,
    ),
    'marketable' =>
    array (
      'type' => 'bool',
      'sdfpath' => 'status',
      'default' => 'true',
      'required' => true,
      'label' => '上架',
      'width' => 30,
      'editable' => false
    ),
    'sku_property' =>
    array (
      'type' => 'text',
      'editable' => false,
      //'width' => 70,
      //'label' => '规格属性',
      //'in_list' => true,
      //'default_in_list' => true,
    ),
    'alert_store' =>
    array (
      'type' => 'number',
      'editable' => false,
      'label' => '安全库存数',
    ),
    'limit_day' =>
    array (
      'type' => 'number',
      'editable' => false,
    ),
    'real_store_lastmodify' =>
    array (
      'type' => 'time',
      'editable' => false,
      'comment' => '实际库存最后更新时间',
    ),
    'max_store_lastmodify' =>
    array (
      'type' => 'time',
      'editable' => false,
      'comment' => '最大可下单库存最后更新时间',
    ),
    'taobao_sku_id' =>
    array (
      'type' => 'bigint(20)',
      'editable' => false,
      'label' => '淘宝SKU ID',
    ),
    'picurl' =>
    array (
      'type' => 'varchar(255)',
      'editable' => false,
      'label' => '商品预览图片',
    ),

    'visibility' => array(
      'type' => array(
        //'0' => app::get('ome')->_('全部'),
        'false' => app::get('ome')->_('隐藏'),
        'true' => app::get('ome')->_('显示'),
      ),
      'label'    => app::get('ome')->_('可视状态'),
      'default'  => 'true',
      'required' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),

    'type' =>
    array (
      'type' =>
      array (
        'normal' => '基础货品',
        'combination' => '组合商品',
        'pkg' => '捆绑商品',
      ),
      'default' => 'normal',
      'width' => 100,
      'label' => '基础物料类型',
      'in_list' => true,
      'default_in_list' => true,
      'order' => '2',
    ),
    'brand_id' => 
    array (
      'type' => 'table:brand@ome',
      'label' => '商品品牌',
      'default' => '0',
      'width' => 75,
      'editable' => false,
      'hidden' => true,
   
      'filtertype' => 'yes',
      'filterdefault' => true,
    ),
  ),
  'index' =>
  array (
    'ind_bn' =>
    array (
        'columns' =>
        array (
          0 => 'bn',
        ),
    ),
    'ind_name' =>
    array (
        'columns' =>
        array (
          0 => 'name',
        ),
    ),
    'ind_barcode' =>
    array (
        'columns' =>
        array (
          0 => 'barcode',
        ),
    ),
    'ind_store' =>
    array (
        'columns' =>
        array (
          0 => 'store',
        ),
    ),
    'ind_real_store_lastmodify' =>
    array (
        'columns' =>
        array (
          0 => 'real_store_lastmodify',
        ),
    ),
    'ind_max_store_lastmodify' =>
    array (
        'columns' =>
        array (
          0 => 'max_store_lastmodify',
        ),
    ),
  ),
  'comment' => '货品表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);