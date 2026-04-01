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
 * 基础物料数据结构
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */

$db['basic_material']=array(
  'columns' =>
  array(
    'bm_id' =>
    array(
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'ID',
      'width' => 110,
      'hidden' => true,
      'editable' => false,
      'comment' => '自增主键ID'
    ),
    'material_bn' => array(
        'type' => 'varchar(200)',
        'label' => '基础物料编码',
        'width' => 230,
        'editable' => false,
        'in_list' => true,
        'default_in_list' => true,
        'required' => true,
        'searchtype' => 'nequal',
        'filtertype' => 'textarea',
        'filterdefault' => true,
    ),
    'material_name' =>
    array(
      'type' => 'varchar(200)',
      'required' => true,
      'label' => '基础物料名称',
      'is_title' => true,
      'default_in_list' => true,
      'width' => 260,
      'searchtype' => 'has',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
    ),
    'material_spu' =>
    array(
        'type' => 'varchar(200)',
        'label' => '基础物料款号',
        'width' => 120,
        'editable' => false,
        'in_list' => true,
        'searchtype' => 'nequal',
        'filtertype' => 'normal',
        'filterdefault' => true,
        'comment' => '基础物料SPU'
    ),
    'material_bn_crc32' =>
    array(
      'type' => 'bigint(13)',
      'label' => '基础物料编码整型索引值',
      'editable' => false,
      'required'        => true,
    ),
    'type' =>
    array(
      'type' => 'tinyint(1)',
      'label' => '物料属性',
      'width' => 100,
      'editable' => false,
      'default' => 1,
      'in_list' => true,
      'default_in_list' => true,
      'required' => true,
      'comment' => '物料属性,可选值:1(成品),2(半成品),3(普通),4(礼盒),5(虚拟)',
    ),
    'cat_id' =>
    array(
      'type' => 'table:basic_material_cat@material',
      'required' => false,
      'default' => 0,
      'label' => '分类',
      'width' => 160,
      'editable' => true,
      'filtertype' => 'yes',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'panel_id' => 'basic_material_finder_top',
      'comment' => '分类ID,关联material_basic_material_cat.cat_id'
    ),
    'cat_path' =>
    array(
      'type' => 'varchar(100)',
      'default' => '',
      'label' => '分类路径',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'comment' => '分类路径(从根至本结点的路径逗号分隔)',
    ),
    'serial_number' =>
    array(
      'type' => 'bool',
      'default' => 'false',
      'required' => true,
      'editable' => false,
      'label' => 'SN码',
      'width' => 130,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => false,
    ),
    'is_ctrl_store' => array(
        'type' => 'tinyint(1)',
        'editable' => false,
        'default' => 1,
        'label' => '是否管控库存',
        'in_list' => true,
        'default_in_list' => true,
        'comment' => '管控库存(1=是，2=否)',
    ),
    'visibled' =>
    array(
      'type' => 'tinyint(1)',
      'label' => '销售状态',
      'width' => 100,
      'in_list' => true,
      'default_in_list' => true,
      'editable' => false,
      'default' => 1,
      'required' => true,
      'comment' => '销售状态,可选值:0(否),1(是)'
    ),
    'create_time' => array(
      'type' => 'time',
      'label' => '创建时间',
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'time',
      'filterdefault' => true,
      'default' => 0,
    ),
    'tax_rate' =>
    array(
          'type' => 'tinyint(2)',
          'label' => '开票税率',
          'width' => 120,
          'in_list' => true,
          'default_in_list' => false,
    ),
    'tax_name' =>
    array(
          'type' => 'varchar(200)',
          'label' => '开票名称',
          'default' => '',
          'width' => 120,
          'searchtype' => 'head',
          'editable' => false,
          'filtertype' => 'yes',
          'filterdefault' => true,
          'in_list' => true,
          'default_in_list' => false,
    ),
    'tax_code' =>
    array(
          'type' => 'varchar(200)',
          'label' => '开票分类编码',
          'default' => '',
          'width' => 160,
          'searchtype' => 'head',
          'editable' => false,
          'filtertype' => 'yes',
          'filterdefault' => true,
          'in_list' => true,
          'default_in_list' => false,
    ),
    'disabled' =>
    array(
      'type' => 'bool',
      'default' => 'false',
      'required' => true,
      'editable' => false,
      'label' => '删除状态',
      'comment' => '删除状态,可选值:true(是), false(否)',
    ),
    'omnichannel' =>
    array(
      'type' => 'tinyint(1)',
      'label' => '是否全渠道',
      'in_list' => true,
      'editable' => false,
      'default' => 2,
      'comment' => '0(否),1(是)',
    ),
    'is_o2o_sales' =>
    array(
      'type' => 'tinyint(1)',
      'label' => '门店销售',
      'in_list' => true,
      'editable' => false,
      'default' => 0,
      'comment' => '0(否),1(是)',
    ),
    'last_modified' => array(
        'label' => '最后更新时间',
        'type' => 'last_modify',
        'width' => 130,
        'in_list' => true,
        'default_in_list' => true,
        'filtertype' => 'time',
        'filterdefault' => true,
    ),
    'source' => array(
        'type'     => 'varchar(50)',
        'required' => true,
        'label'    => '数据来源',
        'default'  => 'local',
        // 'default_in_list' => true,
        'in_list' => true,
        'comment' => '数据来源,可选值:local(本地),api(接口)',
    ),
    'color'              => array(
          'type'     => 'varchar(255)',
          'label'    => '颜色',
          'width'    => 100,
          'editable' => false,
          'in_list' => true,
          'default_in_list' => true,
    ),
    'size'               => array(
          'type'     => 'varchar(255)',
          'label'    => '尺码',
          'width'    => 100,
          'editable' => false,
          'in_list' => true,
          'default_in_list' => true,
    ),
    'bbu_id'              => array(
        // 'type'            => 'table:bbu@dealer',
        'type'            => 'int unsigned',
        'label'           => '所属公司业务组织',
        'comment'         => '品牌BUID',
        'width'           => '130',
        'editable'        => false,
        'in_list'         => false,
        'default_in_list' => false,
        'filtertype'      => 'normal',
        'filterdefault'   => true,
    ),
    'cos_id'              => array(
        'type'     => 'table:cos@organization',
        'label'    => '组织架构ID',
        'editable' => false,
    ),
  ),
  'index' =>
  array(
    'uni_material_bn' =>
    array(
      'columns' =>
      array(
        0 => 'material_bn',
      ),
      'prefix' => 'UNIQUE',
    ),
    'ind_material_spu' =>
    array(
        'columns' =>
        array(
            0 => 'material_spu',
        ),
    ),
    'ind_last_modified' => array(
        'columns' => array(
            0 => 'last_modified',
        ),
    ),
   'create_time' => array (
       'columns' => array (
          0 => 'create_time',
       ),
   ),
  ),
  'comment' => '基础物料表,用于存储SKU纬度的商品数据',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
