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

$db['returned_purchase']=array (
  'columns' =>
  array (
    'rp_id' =>
    array (
      'type' => 'number',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),

    'rp_bn' =>
    array (
      'type' => 'varchar(32)',
      'label' => '退货单编号',
      'width' => 150,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
      'is_title' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),

    'name' =>
    array (
      'type' => 'varchar(200)',
      'label' => '退货单名称',
      'width' => 160,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'supplier_id' =>
    array (
      'type' => 'table:supplier',
      'label' => '供应商',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'operator' =>
    array (
      'type' => 'varchar(50)',
      'label' => '经办人',
      'width' => 75,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'returned_time' =>
    array (
      'type' => 'time',
      'label' => '退货日期',
      'width' => 80,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => true,
    ),
    'purchase_time' =>
    array (
      'type' => 'time',
      'label' => '采购日期',
      'width' => 140,
      'editable' => false,
      'in_list' => false,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => false,
    ),
    'branch_id' =>
    array (
      'type' => 'table:branch@ome',
      'label' => '仓库',
      'width' => 110,
      'editable' => false,
      'in_list' => true,
    ),
    /*'arrive_time' =>
    array (
      'type' => 'time',
      'label' => '到货日期',
      'width' => 80,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'yes',
      'filterdefault' => false,
    ),*/
    'amount' =>
    array (
      'type' => 'money',
      'label' => '金额总计',
      'width' => 110,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
    ),
    'product_cost' =>
    array (
      'type' => 'money',
      'label' => '商品总额',
      'width' => 75,
      'default' => 0,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
    ),
    'delivery_cost' =>
    array (
      'type' => 'money',
      'label' => '物流费用',
      'width' => 75,
      'default' => 0,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'logi_no' =>
    array (
      'type' => 'varchar(50)',
      'label' => '物流单号',
      'width' => 80,
      'editable' => false,
      'in_list' => true,
      'searchtype' => 'has',
      'filtertype' => 'normal',
    ),
    'memo' =>
    array (
      'type' => 'longtext',
      'editable' => false,
    ),
    'po_type' =>
    array (
      'type' =>
      array (
        'cash' => '现款',
        'credit' => '赊账',
      ),
      'required' => true,
      'label' => '采购方式',
      'width' => 70,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
      'searchtype' => 'has',
      'filtertype' => 'normal',
      'filterdefault' => false,
    ),
    'rp_type' =>
    array (
      'type' =>
      array (
        'po' => '入库取消单',
        'eo' => '采购退货单',
        'iso'=> '直接出入库',
      ),
      'label' => '退货单类型',
      'width' => 80,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'object_id' =>
    array (
      'type' => 'number',
      'label' => '采购入库单ID',
      'width' => 130,
      'editable' => false,
    ),
    'emergency' =>
    array (
      'type' => 'bool',
      'default' => 'false',
      'label' => '是否特别退货',
      'width' => 60,
      'editable' => false,
      'in_list' => true,
    ),
    'return_status' =>
    array (
      'type' =>
      array (
        1 => '已新建',
        2 => '退货完成',
        3 => '出库拒绝',
        4=>'部分退货',
        5=>'取消出库',
        ),
      'default' => 1,
      'label' => '退货状态',
      'width' => 60,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'check_status' =>
    array (
      'type' =>
      array (
        1 => '未审',
        2 => '已审',
      ),
      'default' => 1,
      'label' => '审核状态',
      'width' => 60,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'filterdefault' => true,
    ),
    'out_iso_bn' =>
    array (
      'type' => 'varchar(32)',
      'label' => '外部单编号',
      'width' => 140,
     
     
    ),
     'corp_id' =>
    array (
      'type' => 'number',
      'comment' => '物流公司ID',
      'editable' => false,
      'label' => '物流公司',
      
    ),
    'last_modify' =>
    array (
      'type' => 'last_modify',
      'label' => '最后更新时间',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'po_bn' =>
        array (
            'type' => 'varchar(32)',
            'label' => '采购订单',
            'in_list' => true,
            'default_in_list' => true,
        ),
    'sync_status' => array(
        'type'            => array(
            '1' => '未同步',
            '2' => '推送失败',
            '3' => '推送成功',
        ),
        'default'         => '1',
        'width'           => 75,
        'required'        => true,
        'label'           => 'WMS同步状态',
        'filtertype' => 'normal',
        'filterdefault'   => true,
        'in_list'         => true,
        'default_in_list' => false,
    ),
    'sync_msg'    => array(
        'type'            => 'text',
        'label'           => 'WMS返回原因',
        'default_in_list' => true,
        'in_list'         => true,
    ),
  ),
  'index' =>
  array (
    'ind_po_type' =>
    array (
      'columns' =>
      array (
        0 => 'po_type',
      ),
    ),
    'ind_rp_type' =>
    array (
      'columns' =>
      array (
        0 => 'rp_type',
      ),
    ),
    'ind_object_id' =>
    array (
      'columns' =>
      array (
        0 => 'object_id',
      ),
    ),'ind_po_bn' =>
    array (
      'columns' =>
      array (
        0 => 'po_bn',
      ),
    ),
    'ind_last_modify' =>
    array (
      'columns' =>
      array (
        0 => 'last_modify',
      ),
    ),
  ),
  'comment' => '退货单',
  'engine' => 'innodb',
  'version' => '$Rev: 51996',
);
