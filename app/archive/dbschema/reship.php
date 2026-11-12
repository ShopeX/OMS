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

$db['reship']=array (
  'columns' =>
  array (
    'reship_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'extra' => 'auto_increment',
      'comment' => '退换货ID',
    ),
   
    'reship_bn' =>
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'label' => '退换货单号',
      'comment' => '退换货单号',
      'editable' => false,
      'searchtype' => 'has',
      'filterdefault' => true,
      'filtertype' => 'normal',
      'width' =>200,
      'in_list' => true,
      'default_in_list' => true,
      'is_title' => true,
    ),
    'order_id' =>
    array (
      'type' => 'int unsigned',
      'label' => '订单号',
      'comment' => '订单号',
      'editable' => false,
      'width' =>200,
      'in_list' => false,
      'default_in_list' => false,
    ),
    'money' =>
    array (
      'type' => 'money',
      'required' => true,
      'default' => 0,
      'label' => '物流费用',
      'comment' => '配送费用',
      'editable' => false,
      'filtertype' => 'number',
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
    ),
   
    'delivery' =>
    array (
      'type' => 'varchar(20)',
      'label' => '配送方式',
      'comment' => '配送方式(货到付款、EMS...)',
      'editable' => false,
      'filtertype' => 'normal',
      'width' =>110,
      'in_list' => true,
      'default_in_list' => true,
    ),
    
    
    'return_logi_name' =>
    array (
      'type' => 'varchar(100)',
      'label' => '退回物流公司',
      'comment' => '退回物流公司名称',
      'editable' => false,
      'filtertype' => 'normal',
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'return_logi_no' =>
    array (
      'type' => 'varchar(50)',
      'label' => '退回物流单号',
      'comment' => '退回物流单号',
      'editable' => false,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
      'width' =>130,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'ship_name' =>
    array (
      'type' => 'varchar(255)',
      'label' => '收货人',
      'comment' => '收货人姓名',
      'editable' => false,
      'filtertype' => 'normal',
      'width' =>180,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'ship_area' =>
    array (
      'type' => 'region',
      'label' => '收货地区',
      'comment' => '收货人地区',
      'editable' => false,
      'filtertype' => 'normal',
      'width' =>75,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'ship_addr' =>
    array (
      'type' => 'text',
      'label' => '收货地址',
      'comment' => '收货人地址',
      'editable' => false,
      'filtertype' => 'normal',
      'width' =>180,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'ship_zip' =>
    array (
      'type' => 'varchar(20)',
      'label' => '收货邮编',
      'comment' => '收货人邮编',
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
    ),
    'ship_tel' =>
    array (
      'type' => 'varchar(200)',
      'label' => '收货人电话',
      'comment' => '收货人电话',
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
      'width' =>180,
    ),
    'ship_mobile' =>
    array (
      'type' => 'varchar(200)',
      'label' => '收货人手机',
      'comment' => '收货人手机',
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
      'width' =>180,
    ),
   
    't_begin' =>
    array (
      'type' => 'time',
      'label' => '单据创建时间',
      'comment' => '单据生成时间',
      'editable' => false,
      'filtertype' => 'time',
      'in_list' => true,
    ),
    't_end' =>
    array (
      'type' => 'time',
      'comment' => '单据结束时间',
      'editable' => false,
      'label' => '单据结束时间',
     'filtertype' => 'time',
      'in_list' => true,
    ),
    'op_id' =>
    array (
      'type' => 'table:account@pam',
      'label' => '操作员',
      'comment' => '操作者',
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
    ),
    'status' =>
    array (
      'type' =>
      array (
        'succ' => '成功到达',
        'failed' => '发货失败',
        'cancel' => '已取消',
        'progress' => '运送中',
        'timeout' => '超时',
        'ready' => '准备发货',
        'stop' => '暂停',
        'back' => '打回',
      ),
      'default' => 'ready',
      'required' => true,
      'comment' => '状态',
      'editable' => false,
      'label' => '状态',
      'width' =>65,
      'in_list' => false,
      'default_in_list' => false,
    ),
    'is_check' =>
    array(
      'type' => array(
        0 => '未审核',
        1 => '审核成功',
        2 => '审核失败',
        3 => '收货成功',
        4 => '拒绝收货',
        5 => '拒绝',
        6 => '补差价',
        7 => '完成',
        8 => '质检通过',
        9 => '拒绝质检',
        10 => '质检异常',
        11 => '待确认',
        12=>'收货异常',
        13 => '质检中',
      ),
      'default'=>'0',
      'comment' => '当前状态',
      'editable' => false,
      'label' => '当前状态',
      'width' =>65,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'memo' =>
    array (
      'type' => 'longtext',
      'label' => '备注',
      'comment' => '备注',
      'editable' => false,
      'filtertype' => 'normal',
      'in_list' => true,
    ),
    'tmoney' =>
    array (
      'type' => 'money',
      'editable' => false,
      'label' => '退款的金额',
      'in_list' => true,
    ),
    'bmoney' =>
    array (
      'type' => 'money',
      'editable' => false,
      'label' => '折旧(其他费用)',
      'in_list' => true,
    ),
    'totalmoney' =>
    array (
      'type' => 'money',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'label' => '最后合计金额',
      'width' => 130,
    ),
    
    'return_id' =>
    array (
      'type' => 'int unsigned',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,

      'label' => '售后申请单号',
    ),
    'reason' =>
    array (
      'type' => 'longtext',
      'editable' => false,
	  'label' => '收货/质检原因',
    ),
    'return_type' =>
    array (
      'type' =>
      array (
        'return' => '退货',
        'change' => '换货',
		'refuse' => '拒收退货',
      ),
      'default' => 'return',
      'required' => true,
      'comment' => '退换货类型',
      'editable' => false,
      'label' => '退换货类型',
      'width' =>65,
      'in_list' => true,
      'default_in_list' => true,
      'filtertype' => true,
      'filterdefault' => true,
    ),
    'change_status' =>
    array(
      'type' => array(
        0 => '未生成',
        1 => '已生成',
        2 => '不生成',

      ),
      'default'=>'0',
      'comment' => '换货订单状态',
      'editable' => false,
      'label' => '换货订单状态',
      'width' =>65,
      'in_list' => true,

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
    
    'change_amount' => array(
      'type' => 'money',
      'label' => app::get('ome')->_('换出金额'),
      'in_list'=>true,
      'default' => 0,
    ),
    'cost_freight_money' => array(
        'type' => 'money',
        'label' => app::get('ome')->_('买家承担的邮费'),
        'in_list' => true,
        'default' => 0,
    ),
    
    'branch_id' =>
    array (
        'type' => 'table:branch@ome',
      'editable' => false,
        'in_list' => true,
      'label'=>'仓库',
      'comment'=>'仓库',

    ),
   
'source' =>
    array (
      'type' => 'varchar(50)',
      'default' => 'local',
      'editable' => false,
      'label'=>'来源',
      'in_list' => true,
    ),
     'outer_lastmodify' =>
    array (
        'label' => '收货时间',
        'type' => 'time',
        'width' => 130,
        'editable' => false,
        'filtertype' => 'time',
        'in_list' => true,
    ),
    'out_iso_bn' =>
    array (
      'type' => 'varchar(32)',
      'label' => '外部单号',
      'is_title' => true,
      'width' => 125,
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    
     'check_time' =>
    array (
      'type' => 'time',
      'comment' => '单据审核时间',
      'editable' => false,
      'label' => '单据审核时间',
      'in_list' => true,
      'filtertype' => 'time',
    ),
    'changebranch_id' =>
    array (
      'type' => 'number',
      'editable' => false,
      'label'=>'换货仓库ID',
      'comment'=>'换货仓库ID',
    ),
   
    'change_order_id' =>
    array (
    'type' => 'int unsigned',

    'default' => 0,
    'editable' => false,
    'label' => '换货订单ID',
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
  'comment' => '退换货单表',
  'index' =>
  array (
    
    'ind_reship_bn_shop' =>
    array (
        'columns' =>
        array (
          0 => 'reship_bn',
          1 => 'shop_id',
        ),
        'prefix' => 'unique',
    ),
    'ind_is_check' =>
    array (
        'columns' =>
        array (
          0 => 'is_check',
        ),
    ),
    'ind_return_type' =>
    array (
        'columns' =>
        array (
          0 => 'return_type',
        ),
    ),
    
    'ind_t_begin' =>
    array (
        'columns' =>
        array (
          0 => 't_begin',
        ),
    ),
    
    'ind_return_logi_no' =>
    array (
      'columns' =>
      array (
        0 => 'return_logi_no',
      ),
    ),
    'ind_change_order_id'=>array (
      'columns' =>
      array (
        0 => 'change_order_id',
      ),
    ),
  ),
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
