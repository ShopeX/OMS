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

$db['sales']=array (
  'columns' =>
  array (
    'sale_id' =>
    array (
      'type' => 'bigint unsigned',
      'required' => true,
      'pkey' => true,
    ),
    'sale_bn' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '销售单号',
      'is_title' => true,
      'default_in_list'=>true,
	    'in_list'=>true,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'has',
    ),  
    'order_id' =>
    array (
      'type' => 'table:orders@ome',
      'required' => true,
      'label' => '订单号',
      'default_in_list'=>true,
      'in_list'=>true,
    ),
    'iostock_bn' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '出入库单号',
      'default_in_list'=>false,
	    'in_list'=>false,
      #'filtertype' => 'normal',
      #'filterdefault' => true,
    ),
    'sale_time' =>
    array (
      'type' => 'time',
      'label' => '销售时间',
      'editable' => false,
      'width' => 130,
      'filtertype' => 'time',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'total_amount' =>
    array (
      'type' => 'money',
      'required' => true,
      'default' => 0,
      'label' => '商品金额',
      'default_in_list'=>true,
      'in_list'=>true,
    ), 
    'cost_freight' =>
    array (
      'type' => 'money',
      'required' => true,
      'default' => 0,
      'label' => '配送费用',
      'default_in_list'=>true,
      'in_list'=>true,
    ),      
    'sale_amount' =>
    array (
      'type' => 'money',
      'required' => true,
      'default' => 0,
      'label' => '销售金额',
      'comment' => '销售金额',
      'default_in_list'=>true,
	    'in_list'=>true,
    ),
    'payment' =>
    array (
      'type' => 'varchar(255)',
      'label' => '支付方式',
      'width' => 65,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      #'sdfpath' => 'payinfo/pay_name',
    ),
    'delivery_cost' =>
    array (
      'type' => 'money',
      'default' => 0,
      'label' => '预收物流费用',
      'default_in_list'=>true,
	    'in_list'=>true,
    ),
    'additional_costs' =>
    array (
      'type' => 'money',
      'default' => 0,
      'label' => '附加费',
      'default_in_list'=>true,
	    'in_list'=>true,
    ),
    'deposit' =>
    array (
      'type' => 'money',
      'default' => 0,
      'label' => '预存费',
      'default_in_list'=>false,
  	  'in_list'=>false,
    ),
    'discount' =>
    array (
      'type' => 'money',
      'default' => 0,
      'label' => '优惠金额',
      'comment' => '订单折扣金额',
      'default_in_list'=>true,
  	  'in_list'=>true,
    ),
    'operator' =>
    array (
      'type' => 'varchar(30)',
      'label' => '操作员',
  	  'in_list'=>false,
    ),
    'member_id' =>
    array (
      'type' => 'table:members@ome',
      'label' => '用户名称',
      'in_list'=>true,
    ),  
    'delivery_cost_actual' =>
    array (
      'type' => 'money',
      'editable' => false,
      'label' => '预估物流费用',
      'comment' => '预估物流费用',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'delivery_id' => 
    array (
      'type' => 'table:delivery@ome',
      'required' => true,
      'editable' => false,
      'comment' => '发货单号',
      'in_list' => false,
      'default_in_list' => false,
    ),
   'is_tax' =>
    array (
      'type' => 'bool',
      'default' => 'false',
      'required' => true,
      'editable' => false,
      'in_list' => true,
      'label' => '是否开发票',
      'width' => 80,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'shop_id' =>
    array (
      'type' => 'table:shop@ome',
      'label' => '店铺名称',
      'default_in_list'=>true,
  	  'in_list'=>true,
    ),
    'shopping_guide' =>
    array (
      'type' => 'text',
      'comment' => '导购',
      'label' => '导购',
      'width' => 130,
      'in_list'=>false,
    ),
    'cost' =>
    array (
      'type' => 'money',
      'default' => 0,
      'label' => '成本金额',
      'default_in_list'=>false,
      'in_list'=>false,
    ),    
    'memo' =>
    array (
      'type' => 'text',
      'comment' => '备注',
      'label' => '备注',
      'in_list'=>false,
      'default_in_list'=>false,
    ),
    'branch_id' =>
    array (
      'type' => 'table:branch@ome',
      'label' => '仓库名称',
      'default_in_list'=>true,
      'in_list'=>true,
      'filtertype' => 'normal',
      'filterdefault' => true,      
    ),
    'pay_status' =>
    array (
      'type' => array(
        0 => '未支付',
        1 => '已支付',
      ),
      'label' => '支付状态',
      'default_in_list'=>false,
      #'filtertype' => 'normal',
      #'filterdefault' => true,
      'in_list'=>false,
    ),
   'logi_id' =>
    array (
      'type' => 'table:dly_corp@ome',
      'comment' => '物流公司ID',
      'editable' => false,
      'label' => '物流公司',
      #'filtertype' => 'normal',
      #'filterdefault' => true,
    ),
    'logi_name' =>
    array (
      'type' => 'varchar(100)',
      'label' => '物流公司',
      'comment' => '物流公司名称',
      'editable' => false,
      'width' =>75,
      'in_list' => false,
      'default_in_list' => false,
    ),
    'logi_no' =>
    array (
      'type' => 'varchar(50)',
      'label' => '物流单号',
      'comment' => '物流单号',
      'editable' => false,
      'width' =>110,
      'in_list' => false,
      'default_in_list' => false,
    ),
    'order_check_id' =>
    array (
      'type' => 'table:account@pam',
      'label' => '订单审核人',
      'in_list'=>true,
    ),
    'order_check_time' =>
    array (
      'type' => 'time',
      'label' => '订单审核时间',
      'comment' => '订单审核时间(订单审核并生成发货单时间)',
      'editable' => false,
      'width' => 130,
      'filtertype' => 'time',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ), 
    'order_create_time' =>
    array (
      'type' => 'time',
      'label' => '下单时间',
      'editable' => false,
      'width' => 130,
      'filtertype' => 'time',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ), 
    'paytime' =>
    array (
      'type' => 'time',
      'label' => '付款时间',
      'editable' => false,
      'width' => 130,
      'filtertype' => 'time',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ), 
    'ship_time' =>
    array (
      'type' => 'time',
      'label' => '发货时间',
      'editable' => false,
      'width' => 130,
      'filtertype' => 'time',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
  ),
  'index' =>
  array (
    'ind_sale_bn' =>
    array (
        'columns' =>
        array (
          0 => 'sale_bn',
        ),
    ),
    'ind_iostock_bn' =>
    array (
        'columns' =>
        array (
          0 => 'iostock_bn',
        ),
    ),
    'ind_sale_time' =>
    array (
        'columns' =>
        array (
          0 => 'sale_time',
        ),
    ),
    'ind_order_check_time' =>
    array (
        'columns' =>
        array (
          0 => 'order_check_time',
        ),
    ),
    'ind_order_create_time' =>
    array (
        'columns' =>
        array (
          0 => 'order_create_time',
        ),
    ),
    'ind_paytime' =>
    array (
        'columns' =>
        array (
          0 => 'paytime',
        ),
    ),
    'ind_ship_time' =>
    array (
        'columns' =>
        array (
          0 => 'ship_time',
        ),
    ),
  ),
  'comment' => '销售单数据',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);