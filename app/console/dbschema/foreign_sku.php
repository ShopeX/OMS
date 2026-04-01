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
$db['foreign_sku']=array (
  'columns' => 
  array (
    'fsid' =>
    array (
        'type' => 'int unsigned',
        'required' => true,
        'pkey' => true,
        'extra' => 'auto_increment',
    ),
    'inner_sku' => 
    array (
      'type' => 'varchar(255)',
      'required' => true,
      'label' => '基础物料编码',
      'comment'=>'内部sku',
      'in_list' => true,
      'default_in_list' => true,
      'editable' => false,
      'searchtype' => 'nequal',
      'filtertype' => 'textarea',
      'filterdefault' => true,
      'order' => 10,
    ),
    'inner_product_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'label' => '货品ID',
      'width' => 110,
      'editable' => false,
      'order' => 11,
    ),
    'wms_id' => 
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '来源WMS',
      'editable' => false,
      'order' => 20,
    ),
    'outer_sku' => 
    array (
      'type' => 'varchar(50)',
      'label' => 'wms物料编码',
      'in_list' => true,
      'default_in_list' => true,
      'order' => 21,
    ),
    'oms_sku' => 
    array (
      'type' => 'varchar(50)',
      'label' => '外部oms物料编码',
      'in_list' => true,
      'default_in_list' => true,
      'order' => 30,
    ),
    'price' => 
    array (
      'type' => 'money',
      'label' => '单价',
      'in_list' => true,
      'default_in_list' => true,
      'order' => 31,
    ),
    'new_tag' =>
    array (
     'type' =>
      array (
        0 => '新品',
        1 => '非新品',
      ),
      'label' => '新品标识',
      'default' => '0',
      'required' => true,
      'in_list' => true,
      'default_in_list' => false,
      'order' => 40,
    ),
    'inner_type' => array (
      'type'     => array(
        '0' => '普通商品',
        '1' => '捆绑商品',
        '2'=>'礼盒',
      ),
      'required' => true,
      'default' => '0',
      'label'    => '商品类型',
      'searchtype' => 'has',
      'width'    => 110,
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => false,
      'order' => 50,
    ),
    'sync_status' =>
    array (
     'type' =>
      array (
        0 => '未同步',
        1 => '同步失败',
        2 => '同步中',
        3 => '同步成功',
        4 => '同步后编辑',
      ),
      'default' => '0',
      'required' => true,
      'label' => '同步状态',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'order' => 90,
    ),
    'sync_combination' =>
    array (
     'type' =>
      array (
        0 => '未同步',
        1 => '同步失败',
        2 => '无需同步',
        3 => '同步成功',
      ),
      'default' => '0',
      'required' => true,
      'label' => '组合同步状态',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'order' => 90,
    ),
  ),
  'index' =>
      array (
        'ind_product_wms_out' =>
        array (
            'columns' =>
            array (
              0 => 'inner_sku',
              1 => 'wms_id',
            ),
            'prefix' => 'unique',
        ),
        'ind_inner_product_id' =>
        array (
            'columns' =>
            array (
              0 => 'inner_product_id',
            ),
        ),
        'ind_wms_id' =>
        array (
            'columns' =>
            array (
              0 => 'wms_id',
            ),
        ),
        'ind_sync_status' =>
        array (
            'columns' =>
            array (
              0 => 'sync_status',
            ),
        ),
        'ind_sync_combination' =>
        array (
            'columns' =>
            array (
              0 => 'sync_combination',
            ),
        ),
        'ind_inner_type' =>
        array (
            'columns' =>
            array (
              0 => 'inner_type',
            ),
        ),
      ),
  'comment' => '外部sku关联表',
  'engine' => 'innodb',
  'version' => '$Rev: 40654 $',
);
