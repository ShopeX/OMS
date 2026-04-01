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
 * 销售物料数据结构
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */

$db['sales_material']=array(
  'columns' =>
  array(
    'sm_id' =>
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
    'shop_id' => array(
        'type' => 'varchar(32)',
        'required' => true,
        'label' => '所属店铺',
        'in_list' => true,
        'default_in_list' => true,
        'default' => '_ALL_',
    ),
    'sales_material_bn' =>
        array(
            'type' => 'varchar(200)',
            'label' => '销售物料编码',
            'width' => 230,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'required' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'textarea',
            'filterdefault' => true,
        ),
    'sales_material_name' =>
    array(
      'type' => 'varchar(200)',
      'required' => true,
      'label' => '销售物料名称',
      'is_title' => true,
      'default_in_list' => true,
      'width' => 260,
      'searchtype' => 'has',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
    ),
    'sales_material_bn_crc32' =>
    array(
      'type' => 'bigint(13)',
      'label' => '销售物料编码整型索引值',
      'editable' => false,
      'required' => true,
    ),
    'sales_material_type' =>
    array(
      'type' => 'tinyint(1)',
      'label' => '销售物料类型',
      'width' => 100,
      'editable' => false,
      'default' => 1,
      'in_list' => true,
      'default_in_list' => true,
      'required'        => true,
      'comment'=>'销售物料类型,可选值:1(普通),2(组合),3(赠品),5(多选一),6(礼盒),7(福袋组合)',
    ),
    'class_id' => array(
        'type' => 'table:customer_classify@material',
        'default' => 0,
        'editable' => false,
        'label' => '客户分类',
        'width' => 160,
        'in_list' => true,
        'default_in_list' => true,
    ),
    'is_bind' => array(
        'type' => 'tinyint(1)',
        'default' => 2,
        'label' => '是否绑定基础物料',
        'comment' => '是否绑定基础物料,可选值:1(是), 0(否)',
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
    'last_modify' => array(
          'type' => 'last_modify',
          'label' => '最后更新时间',
          'in_list' => true,
          'default_in_list' => true,
          'filtertype' => 'time',
          'filterdefault' => true,
          'order' => 11,
      ),
    'disabled' =>
    array(
      'type' => 'bool',
      'default' => 'false',
      'required' => true,
      'editable' => false,
      'label' => '删除状态',
      'comment' => 'true(是), false(否)',
    ),
    'tax_rate'                => array(
        'type'            => 'tinyint(2)',
        'label'           => '开票税率',
        'width'           => 120,
        'in_list'         => true,
        'default_in_list' => false,
    ),
    'tax_name'                => array(
        'type'            => 'varchar(200)',
        'label'           => '开票名称',
        'default'         => '',
        'width'           => 120,
        'searchtype'      => 'head',
        'editable'        => false,
        'filtertype'      => 'yes',
        'filterdefault'   => true,
        'in_list'         => true,
        'default_in_list' => false,
    ),
    'tax_code'                => array(
        'type'            => 'varchar(200)',
        'label'           => '开票分类编码',
        'default'         => '',
        'width'           => 160,
        'searchtype'      => 'head',
        'editable'        => false,
        'filtertype'      => 'yes',
        'filterdefault'   => true,
        'in_list'         => true,
        'default_in_list' => false,
    ),
    'org_id' =>
        array (
            'type' => 'table:operation_organization@ome',
            'label' => '运营组织',
            'width' => '100',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
        ),
    'visibled' =>
        array(
            'type' =>
                array (
                    0 => '停售',
                    1 => '在售',
                ),
            'label' => '销售状态',
            'width' => 100,
            'in_list' => true,
            'default_in_list' => true,
            'editable' => false,
            'default' => '1',
            'comment' => '销售状态,可选值:0(否),1(是)',
            'filtertype' => 'yes',
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
  ),
  'comment' => '销售物料表,用于匹配销售平台订单商品编码',
  'index' =>
  array(
    'uni_sales_material_bn' =>
    array(
      'columns' =>
      array(
        0 => 'sales_material_bn',
      ),
      'prefix' => 'UNIQUE',
    ),
    'ind_visibled' =>
        array (
            'columns' =>
                array (
                    0 => 'visibled',
                ),
        ),
      'last_modify' => array (
        'columns' => array (
                0 => 'last_modify',
            ),
      ),
      'create_time' => array (
          'columns' => array (
              0 => 'create_time',
          ),
      ),
  ),
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
