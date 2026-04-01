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

$db['dly_corp']=array (
  'columns' =>
  array (
    'corp_id' =>
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'extra' => 'auto_increment',
    ),
    'branch_id' =>
    array (
      'type' => 'number',
      'required' => true,
      'default' => 0, 
      'editable' => false,
    ),
    'all_branch' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'true',
      'editable' => false,
    ),
    'corp_model' => array (
          'type' => array(
              'normal' => '普通快递',
              'instatnt' => '同城配送',
              'seller' => '商家配送',
              'heavy' => '大件配送',
              'pickup' => '自提',
          ),
          'editable' => false,
          'required' => true,
          'default' => 'normal',
          'label' => '物流模式',
          'in_list' => true,
          'default_in_list' => true,
    ),
    'type' => array (
      'type' => 'varchar(20)',
      'editable' => false,
      'label' => '物流公司编码',
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype'    => 'nequal',
    ),
    'name' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
      'label' => '物流公司名称',
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'is_title' => true,
    ),
    'disabled' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => false,
    ),
    'website' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
    ),
    'request_url' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
    ),
    'daily_process' =>
    array (
      'type' => 'number',
      'editable' => false,
      'default' => 100,
    ),
    'firstunit' =>
    array (
      'type' => 'number',
      'editable' => false,
      'default' => 0,
      'required' => true,
    ),
    'continueunit' =>
    array (
      'type' => 'number',
      'editable' => false,
      'default' => 0,
      'required' => true,
    ),
    'protect' =>
    array (
      'type' => 'bool',
      'editable' => false,
      'required' => true,
      'default' => 'false',
    ),
    'protect_rate' =>
    array (
      'type' => 'decimal(6,3)',
      'editable' => false,
    ),
    'minprice' =>
    array (
      'type' => 'money',
      'editable' => false,
    ),
    'setting' =>
    array (
      'type' =>
      array (
        0 => '指定地区费用',
        1 => '统一设置',
      ),
      'editable' => false,
      'required' => true,
      'default' => '1',
      'label' => '地区费用类型',
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'firstprice' =>
    array (
      'type' => 'money',
      'editable' => false,
    ),
    'continueprice' =>
    array (
      'type' => 'money',
      'editable' => false,
    ),
    'dt_expressions' =>
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'dt_useexp' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => false,
    ),
    'area_fee_conf' =>
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'is_cod' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => false,
    ),
    'shop_id' =>
    array (
      'type' => 'table:shop@ome',
      'editable' => false,
      'label' => '适用店铺',
      'width' => 130,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'channel_id' =>
    array (
      'type' => 'table:channel@logisticsmanager',
      'editable' => false,
      'comment' => '来源主键',
      'label' => '面单来源',
      'width' => 130,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'tmpl_type' =>
    array (
      'type' => array(
        'normal' => '普通面单',
        'electron' => '电子面单',
      ),
      'editable' => false,
      'required' => true,
      'default' => 'normal',
      'label' => '快递模板类型',
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'prt_tmpl_id' =>
    array (
      'type' => 'table:print_tmpl@wms',
      'default' => '0',
      'editable' => false,
    ),
    'weight' =>
    array (
      'type' => 'number',
      'edtiable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '权重',
    ),
    'd_type' =>
    array (
      'type' => 'tinyint(1)',
      'editable' => false,
      'label' => '线上/线下业务区分标记',
      'default' => 1
    ),
    'corp_type' =>
      array (
          'type' => 'number',
          'default' => 0,#0是本地，1是kuaj
          'editable' => false,
      ),
      
     'crossborder_souce' =>
      array (
          'type' => 'table:shop@ome',
          'editable' => false,
          'comment' => '来源',
          'label' => '来源',
          'width' => 150,
          'in_list' => true,
          'default_in_list' => true,
      ),
      'crossborder_res_id' =>
      array (
          'type' => 'varchar(200)',
          'comment' => '跨境配送资源id',
          'editable' => false,
      ),
      'crossborder_region_id' =>
      array (
          'type' => 'int unsigned',
          'comment' => '跨境发货地区域ID',
          'label' => '区域ID',
          'editable' => false,
          'in_list' => true,
          'default_in_list' => false,
      ),
      
        'contact_email' => array (
            'type'            => 'text',
            'label'         => '联系人邮箱',
            'editable'        => false,
            'in_list' => true,
            'default_in_list' => true,
        ),
        'at_time'       => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default'         => 'CURRENT_TIMESTAMP',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 1000,
        ),
        'up_time'       => array(
            'type'            => 'TIMESTAMP',
            'label'           => '更新时间',
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 1010,
        ),
  ),
    'index' =>
    array (
        'ind_d_type' =>
        array (
            'columns' =>
            array (
                0 => 'd_type',
            ),
        ),
    ),
  'comment' => '物流公司配送信息',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);