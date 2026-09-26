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

$db['shop']=array (
  'columns' =>
  array (
    'shop_id' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'pkey' => true,
      'editable' => false,
    ),
    'shop_bn' =>
    array (
      'type' => 'varchar(20)',
      'required' => true,
      'label' => '店铺编码',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'name' =>
    array (
      'type' => 'varchar(255)',
      'required' => true,
      'label' => '店铺名称',
      'editable' => false,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'is_title' => true,
      'width' => '240',
    ),
    'shop_type' =>
    array (
      'type' => 'varchar(50)',
      'required' => false,
      'label' => '店铺类型',
      'in_list' => true,
      'default_in_list' => true,
      'width' => '70'
    ),
    'config' =>
    array (
      'type' => 'text',
      'editable' => false,
    ),
    'crop_config' =>
    array (
      'type' => 'serialize',
      'editable' => false,
    ),
    'last_download_time' =>
    array (
      'type' => 'time',
      'editable' => false,
      'label' => '上次下载订单时间(终端)',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'last_upload_time' =>
    array (
      'type' => 'time',
      'editable' => false,
      'label' => '上次上传订单时间(ome)',
      'in_list' => false,
      'default_in_list' => true,
    ),
    'active' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'in_list' => false,
      'default_in_list' => true,
      'editable' => false,
      'label' => '激活',
    ),
    'disabled' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => false,
    ),
    'last_store_sync_time' =>
    array (
      'type' => 'time',
      'editable' => false,
      'label' => '上次库存同步时间',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'area' =>
    array (
      'type' => 'varchar(255)',
      'editable' => false,
    ),
    'zip' =>
    array (
      'type' => 'varchar(20)',
      'editable' => false,
    ),
    'addr' =>
    array (
      'type' => 'varchar(255)',
      'editable' => false,
    ),
    'default_sender' =>
    array (
      'type' => 'varchar(255)',
      'editable' => false,
    ),
    'mobile' =>
    array (
      'type' => 'varchar(30)',
      'editable' => false,
    ),
    'tel' =>
    array (
      'type' => 'varchar(30)',
      'editable' => false,
    ),
    'filter_bn' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'editable' => false,
    ),
    'bn_regular' =>
    array (
      'type' => 'varchar(255)',
      'editable' => false,
    ),
    'express_remark' =>
    array (
      'type' => 'text',
      'editable' => false,
    ),
    'delivery_template' =>
    array (
      'type' => 'varchar(255)',
      'editable' => false,
    ),
    'order_bland_template' =>
    array (
      'type' => 'varchar(255)',
      'editable' => false,
    ),
    'node_id' =>
    array (
      'type' => 'varchar(32)',
      'editable' => false,
      'label' => '节点',
      'in_list' => true,
    ),
    'node_type' =>
    array (
      'type' => 'varchar(32)',
      'editable' => false,
    ),
    'api_version' =>
    array (
      'type' => 'char(6)',
      'editable' => false,
    ),
    'addon' =>
    array (
      'type' => 'serialize',
      'editable' => false,
    ),
    'sw_code' =>
    array (
      'type' => 'varchar(32)',
      'comment' => '售达方编码',
      'required' => false,
    ),
    'alipay_authorize' =>
    array (
      'type' => array(
         'true' => '已授权',
         'false' => '未授权'
      ),
      'default' => 'false',
      'editable' => false,
    ),
    'business_type' =>
    array(
      'type' => array(
         'zx' => '直销',
         'fx' => '分销',
         'dx' => '代销',
         'maochao' => '猫超国际',
         'jdlvmi' => '京东云物流',
      ),
      'label' => '订单类型',
      'default' => 'zx',
      'in_list' => true,
      'default_in_list' => true,
      'editable' => false,
    ),
    'business_category' =>
    array(
      'type' => 'varchar(15)',
      'label' => '业务分类',
      'default' => 'B2C',
      'in_list' => true,
      'default_in_list' => true,
      'editable' => false,
      'comment' => '业务分,可选值: B2C、B2B',
    ),
    'tbbusiness_type' =>
    array(
      'type' => 'char(6)',
      'label' => '淘宝店铺类型',
      'default' => 'other',    
      'in_list' => true,
      'default_in_list' => true,      
      'editable' => false,
    ),
    's_type' =>
    array (
      'type' => 'tinyint(1)',
      'editable' => false,
      'label' => '线上/线下业务区分标记',
      'default' => 1
    ),
    's_status' =>
    array (
      'type' => 'tinyint(1)',
      'editable' => false,
      'label' => '店铺是否启用',
      'default' => 2
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
      'filterdefault' => true,
    ),
    'delivery_mode' => array (
      'type' => [
        'self' => '自发',
        'jingxiao' => '经销',
        'shopyjdf' => '分销一件代发',
      ],
      'label' => '发货模式',
      'width' => '100',
      'default' => 'self',
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'aoxiang_signed' => array (
      'type' => array(
         '0' => '未签约',
         '1' => '已签约',
         '2' => '取消签约',
      ),
      'editable' => false,
      'label' => '翱象签约状态',
      'default' => '0',
      'in_list' => false,
      'default_in_list' => false,
    ),
    'aoxiang_signed_time' => array (
      'type' => 'time',
      'editable' => false,
      'label' => '翱象签约时间business_category',
      'in_list' => true,
      'default_in_list' => true,
    ),
    'matrix_url' => array (
      'type' => 'varchar(255)',
      'label' => '矩阵请求地址',
      'comment' => '可单独为某个店铺指定矩阵请求地址',
      'in_list' => true,
    ),
    'source'        => array(
      'type'     => 'varchar(30)',
      'editable' => false,
      'label'    => '来源',
      'width'    => 100,
      'in_list'  => true,
      'default'  => 'local',
      'comment'  => 'local:本地,openapi:openapi',
    ),
    'shop_type_alias' =>
        array (
            'type' => 'varchar(50)',
            'required' => false,
            'label' => '店铺类型别名',
            'in_list' => true,
            'default_in_list' => true,
            'width' => '70'
    ),
    'bs_id' => array(
          'type' => 'int(10)',
          'default' => 0,
          'editable' => false,
          'label' => '经销商ID',
          'in_list' => false,
          'default_in_list' => false,
    ),
    'cos_id' => array(
          'type' => 'varchar(255)',
          'default' => 0,
          'editable' => false,
          'label' => '组织架构ID',
          'in_list' => false,
          'default_in_list' => false,
    ),
    'create_time' => array (
          'type' => 'time',
          'default' => 0,
          'editable' => false,
          'label' => '创建时间',
          'in_list' => true,
          'default_in_list' => true,
    ),
    'is_common_shop' => array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
      'label' => '是否常用店铺',
      'in_list' => true,
      'default_in_list' => true,
    ),
  ),
  'index' =>
  array (
    'ind_shop_bn' =>
    array (
        'columns' =>
        array (
          0 => 'shop_bn',
        ),
        'prefix' => 'unique',
    ),
    'ind_node_id' =>
    array (
        'columns' =>
        array (
          0 => 'node_id',
        ),
    ),
    'ind_s_type' =>
    array (
        'columns' =>
        array (
            0 => 's_type',
        ),
    ),
    'ind_cos_id' => array (
        'columns' => array (
            0 => 'cos_id',
        ),
    ),
  ),
  'comment' => '店铺表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
