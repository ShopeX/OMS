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

$db['return_process']=array (
  'columns' => 
  array (
    'por_id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'editable' => false,
      'extra' => 'auto_increment',
    ),
    'reship_id' =>
    array (
      'type' => 'table:reship@ome',
      'editable' => false,
    ),
    'order_id' =>
    array (
      'type' => 'table:orders@ome',
      'editable' => false,
    ),
    'return_id' =>
    array (
      'type' => 'table:return_product@ome',
      'editable' => false,
    ),
    'member_id' =>
    array (
      'type' => 'table:members@ome',
      'editable' => false,
    ),
    'title' =>
    array (
      'type' => 'varchar(200)',
      'editable' => false,
       'label' => '售后服务标题',
         'in_list' => true,
       'default_in_list' => true,
    ),
    'content' =>
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'add_time' =>
    array (
      'type' => 'time',
      'editable' => false,
       'label' => '售后处理时间',
         'in_list' => true,
       'default_in_list' => true,
    ),
    'shop_id' =>
    array (
      'type' => 'table:shop@ome',
      'editable' => false,
    ),
    'last_modified' => 
    array (
      'type' => 'last_modify',
      'editable' => false,
    ),
    'memo' =>
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'branch_id' =>
    array (
      'type' => 'table:branch@ome',
      'editable' => false,
         'in_list' => true,
      'default_in_list' => true,
      'label' => '仓库',
    ),
    'attachment' =>
    array (
      'type' => 'varchar(255)',
      'editable' => false,
    ),
    'comment' =>
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'process_data' =>
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'recieved' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
    ),
    'verify' =>
    array (
      'type' => 'bool',
      'required' => true,
      'default' => 'false',
    ),
    'wms_type' => array (
        'type' => 'varchar(30)',
        'editable' => false,
        'label' => 'WMS仓储类型',
    ),
    'service_bn' => array (
        'type' => 'varchar(50)',
        'label' => '服务单号',
        'editable' => false,
    ),
    'service_type' => array (
        'type' => 'varchar(20)',
        'editable' => false,
        'label' => '售后服务类型',
    ),
    'afsResultType' => array (
        'type' => 'varchar(30)',
        'editable' => false,
        'label' => '平台处理结果',
    ),
    'step_type' => array (
        'type' => 'varchar(30)',
        'editable' => false,
        'label' => '平台处理环节',
    ),
    'service_status' => array (
        'type' => 'varchar(20)',
        'editable' => false,
        'label' => '服务单状态',
    ),
    'package_bn' => array (
        'type' => 'varchar(50)',
        'editable' => false,
        'label' => '发货包裹号',
    ),
    'wms_order_code' => array (
        'type' => 'varchar(50)',
        'editable' => false,
        'label' => '售后申请单号',
    ),
    'logi_code' => array(
        'type' => 'varchar(20)',
        'label' => '退回物流公司编码',
        'width' => 130,
        'in_list' => true,
        'default_in_list' => true,
    ),
    'logi_no' => array(
        'type' => 'varchar(50)',
        'label' => '退回物流单号',
        'width' => 130,
        'in_list' => true,
        'default_in_list' => true,
    ),
    'new_package_bn' => array (
        'type' => 'varchar(50)',
        'editable' => false,
        'label' => '换新京东订单号',
    ),
    'wms_refund_id' => array(
        'type' => 'varchar(30)',
        'editable' => false,
        'label' => '退款唯一标识',
        'in_list' => true,
        'default_in_list' => true,
    ),
    'wms_refund_fee' => array(
        'type' => 'money',
        'editable' => false,
        'label' => 'WMS退款金额',
        'in_list' => true,
        'default_in_list' => true,
    ),
    'wms_refund_time' => array (
        'type' => 'time',
        'editable' => false,
        'label' => 'WMS退款时间',
        'in_list' => true,
        'default_in_list' => true,
    ),
    'remark' => array(
        'type' => 'text',
        'label' => '备注',
        'comment' => '备注',
        'editable' => false,
        'filtertype' => 'normal',
        'in_list' => false,
        'default_in_list' => false,
    ),
  ),
  'index' => 
  array (
    
    'in_service_bn' => array (
        'columns' => array (
            0 => 'service_bn',
        ),
    ),
    'in_re_service' => array (
        'columns' => array (
            0 => 'reship_id',
            1 => 'service_bn',
        ),
    ),
  ),
  'comment' => '收货服务中间表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
