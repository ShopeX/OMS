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
$db['wmsdelivery']=array (
  'columns' =>
  array (
    'delivery_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'extra' => 'auto_increment',

    ),
    'idx_split' =>
    array(
        'type' => 'bigint',
        'required' => true,
        'label' => '订单内容',
        'comment' => '订单的大致内容',
        'editable' => false,
        'width' => 160,
        'in_list' => false,
        'default_in_list' => false,
        'default' => 0,
    ),
    'skuNum' =>
    array(
        'type' => 'number',
        'required' => true,
        'label' => '商品种类',
        'comment' => '商品种类数',
        'editable' => false,
        'in_list' => false,
        'default' => 0,
    ),
    'itemNum' =>
    array(
        'type' => 'number',
        'required' => true,
        'label' => '商品总数量',
        'comment' => '商品种类数',
        'editable' => false,
        'in_list' => false,
        'default' => 0,
    ),
    'bnsContent' =>
    array(
        'type' => 'text',
        'required' => true,
        'label' => '具体订单内容',
        'comment' => '具体订单内容',
        'editable' => false,
        'in_list' => false,
        'default' => '',
    ),
    'delivery_bn' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '发货单号',
      'comment' => '配送流水号',
      'editable' => false,
      'width' =>140,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
      'is_title' => true,
    ),
 
    'member_id' =>
    array (
      'type' => 'table:members@ome',
      'label' => '会员用户名',
      'comment' => '订货会员ID',
      'editable' => false,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'is_protect' =>
    array (
      'type' => 'bool',
      'default' => 'false',
      'required' => true,
      'label' => '是否保价',
      'comment' => '是否保价',
      'editable' => false,
      'in_list' => true,
    ),
	'cost_protect' =>
    array (
       'type' => 'money',
      'default' => '0',
      'label' => '保价费用',
      'required' => false,
      'editable' => false,
    ),
    'is_cod' =>
    array (
      'type' => 'bool',
      'default' => 'false',
      'required' => true,
      'label' => '是否货到付款',
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
    ),
    'logi_id' =>
    array (
      'type' => 'table:dly_corp@ome',
      'comment' => '物流公司ID',
      'editable' => false,
      'label' => '物流公司',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'logi_name' =>
    array (
      'type' => 'varchar(100)',
      'label' => '物流公司',
      'comment' => '物流公司名称',
      'editable' => false,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'logi_number' =>
    array (
      'type' => 'number',
      'required' => true,
      'default' => 1,
      'editable' => false,
      'label' => '包裹总数',
      'comment' => '物流包裹总数',
    ),
    'delivery_logi_number' =>
    array (
      'type' => 'number',
      'required' => true,
      'default' => 0,
      'editable' => false,
      'label' => '已发货包裹数',
      'comment' => '已发货物流包裹数',
    ),
    'ship_name' =>
    array (
      'type' => 'varchar(255)',
      'label' => '收货人',
      'comment' => '收货人姓名',
      'editable' => false,
      'searchtype' => 'tequal',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
      'sdfpath' => 'consignee/name',
    ),
    'ship_area' =>
    array (
      'type' => 'region',
      'label' => '收货地区',
      'comment' => '收货人地区',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' =>130,
      'in_list' => true,
      'default_in_list' => true,
      'sdfpath' => 'consignee/area',
    ),
    'ship_province' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'sdfpath' => 'consignee/province',
      'comment' => '省',
    ),
    'ship_city' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'sdfpath' => 'consignee/city',
      'comment' => '市',
    ),
    'ship_district' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'sdfpath' => 'consignee/district',
      'comment' => '区/县',
    ),
    'ship_addr' =>
    array (
      'type' => 'text',
      'label' => '收货地址',
      'comment' => '收货人地址',
      'editable' => false,
      'filtertype' => 'normal',
      'width' =>150,
      'in_list' => true,
      'sdfpath' => 'consignee/addr',
    ),
    'ship_zip' =>
    array (
      'type' => 'varchar(20)',
      'label' => '收货邮编',
      'comment' => '收货人邮编',
      'editable' => false,
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
      'sdfpath' => 'consignee/zip',
    ),
    'ship_tel' =>
    array (
      'type' => 'varchar(200)',
      'label' => '收货人电话',
      'comment' => '收货人电话',
      'editable' => false,
      'in_list' => true,
      'sdfpath' => 'consignee/telephone',
    ),
    'ship_mobile' =>
    array (
      'type' => 'varchar(200)',
      'label' => '收货人手机',
      'comment' => '收货人手机',
      'editable' => false,
      'in_list' => true,
      'sdfpath' => 'consignee/mobile',
    ),
    'ship_email' =>
    array (
      'type' => 'varchar(150)',
      'label' => '收货人Email',
      'comment' => '收货人Email',
      'editable' => false,
      'in_list' => true,
      'sdfpath' => 'consignee/email',
    ),
    'create_time' =>
    array (
      'type' => 'time',
      'label' => '单据创建时间',
      'comment' => '单据生成时间',
      'editable' => false,
      'filtertype' => 'time',
      'in_list' => true,
    ),
    'status' =>
    array (
      'type' => 'tinyint(1)',
      'default' => 0,
      'width' => 150,
      'required' => true,
      'comment' => '单据状态',
      'editable' => false,
      'label' => '单据状态',
    ),
    'print_status' =>
    array (
      'type' => 'tinyint(1)',
      'default' => 0,
      'width' => 150,
      'required' => true,
      'comment' => '打印状态',
      'editable' => false,
      'label' => '打印状态',
    ),
    'process_status' =>
    array (
      'type' => 'tinyint(1)',
      'default' => 0,
      'width' => 150,
      'required' => true,
      'comment' => '处理状态',
      'editable' => false,
      'label' => '处理状态',
    ),
    'memo' =>
    array (
      'type' => 'longtext',
      'label' => '备注',
      'comment' => '备注',
      'editable' => false,
      'in_list' => true,
    ),
    'disabled' =>
    array (
      'type' => 'bool',
      'default' => 'false',
      'comment' => '无效',
      'editable' => false,
      'label' => '无效',
      'in_list' => false,
    ),
    'branch_id' =>
    array (
      'type' => 'table:branch@ome',
      'editable' => false,
      'label' => '仓库',
      'width' => 110,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'panel_id' => 'delivery_finder_top',
    ),
    'last_modified' =>
    array (
      'label' => '最后更新时间',
      'type' => 'last_modify',
      'editable' => false,
      'in_list' => true,
    ),
    'delivery_time' =>
    array (
      'type' => 'time',
      'label' => '发货时间',
      'comment' => '发货时间',
      'editable' => false,
      'in_list' => true,
    ),
    'delivery_cost_expect' =>
    array (
      'type' => 'money',
      'default' => '0',
      'editable' => false,
      'comment' => '预计物流费用(包裹重量计算的费用)',
    ),
    'delivery_cost_actual' =>
    array (
      'type' => 'money',
      'editable' => false,
      'comment' => '实际物流费用(物流公司提供费用)',
    ),
    'bind_key' =>
    array (
      'type' => 'varchar(32)',
      'editable' => false,
      'comment' => '店铺仓库收货人会员md5',
    ),
    'type' =>
    array (
      'type' =>
      array (
        'normal' => '普通发货单',
        'reject' => '拒绝退货的发货单',
      ),
      'default' => 'normal',
      'editable' => false,
    ),
    'shop_id' =>
    array (
      'type' => 'table:shop@ome',
      'label' => '来源店铺',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'filtertype' => 'normal',
    ),
   'order_createtime' =>
    array (
      'type' => 'time',
      'label' => '订单创建时间',
      'width' => 130,
      'editable' => false,
      'filtertype' => 'time',
      'in_list' => false,
    ),
    'ship_time' =>
    array (
      'type' => 'varchar(50)',
      'editable' => false,
      'sdfpath' => 'consignee/r_time',
      'comment' => '要求发货时间',
    ),
    'op_id' =>
    array (
      'type' => 'table:account@pam',
      'editable' => false,
      'required' => true,
      'comment' => '操作员ID',
    ),
    'op_name' =>
    array (
      'type' => 'varchar(30)',
      'editable' => false,
      'comment' => '操作员',
    ),
    'outer_delivery_bn'=>
    array (
      'type' => 'varchar(32)',
      'default' => '',
      'editable' => false,
      'required' => true,
      'comment' => '外部关联发货单号',
    ),
    'shop_type' =>
    array (
      'type' => 'varchar(50)',
      'label' => '店铺类型',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
    ),
  ),
  'index' =>
  array (
    'ind_delivery_bn' =>
    array (
      'columns' =>
      array (
        0 => 'delivery_bn',
      ),
      'prefix' => 'unique',
    ),
    'ind_outer_delivery_bn' =>
    array (
      'columns' =>
      array (
        0 => 'outer_delivery_bn',
      ),
      'prefix' => 'unique',
    ),
    
    'ind_type' =>
    array(
        'columns' =>
        array(
            0 => 'type',
        ),
    ),
    'ind_bind_key' =>
    array(
        'columns' =>
        array(
            0 => 'bind_key',
        ),
    ),
    'ind_order_createtime' =>
    array(
        'columns' =>
        array(
            0 => 'order_createtime',
        ),
    ),
    'ind_delivery_time' =>
    array(
        'columns' =>
        array(
            0 => 'delivery_time',
        ),
    ),
  ),
  'comment' => '归档自有仓储发货单主表',
  'engine' => 'innodb',
  'version' => '$Rev: 41996 $',
);
