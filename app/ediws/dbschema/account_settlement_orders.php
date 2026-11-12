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

$db['account_settlement_orders'] = array(
    'columns' => array(
        'oid' => array(
            'pkey' => 'true',
            'type' => 'int unsigned',
            'extra' => 'auto_increment',
            'label' => '序号',
            'order' => 1,
        ),
        'shop_id' => array(
            'type' => 'table:shop@ome',
            'label' => '店铺',
            'in_list'         => true,
            'default_in_list' => true,
            'editable' => false,
        ),
        'sole_bn' => array(
            'type' => 'varchar(80)',
            'required' => true,
            'editable' => false,
            'label' => '唯一编码',
            'in_list' => false,
            'default_in_list' => false,
            'order' => 3,
        ),
        'orderNo' => array(
            'type' => 'varchar(32)',
            'label' => '订单号',
            'editable' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => 'true',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 6,
        ),
        'expenseId' => array(
            'type' => 'varchar(32)',
            'label' => '应付账ID(唯一)',
            'editable' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'searchtype' => 'nequal',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 8,
        ),
        'shqid' => array(
            'type' => 'varchar(32)',
            'label' => '结算单号',
            'editable' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'searchtype' => 'nequal',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 15,
        ),
        
        'rootExpenseType' => array(
            'type' => 'char(32)',
            'label' => '应付帐单据类型',
            'editable' => true,
            'in_list' => false,
            'default_in_list' => false,
            'order' => 15,
        ),
        'expenseTypeName' => array(
            'type' => 'char(32)',
            'label' => '应付类型名称',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 15,
        ),
        'sku' => array(
            'type' => 'varchar(32)',
            'label' => 'SKU编码',
            'editable' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 20,
        ),
        'material_bn' =>
        array (
          'type' => 'varchar(30)',
          'label' => '货号',
          'width' => 150,
         
          'editable' => false,
        
        ),
        'bm_id' =>
        array (
            'type' => 'int unsigned',
            'label' => '商品ID',
            'editable' => false,
            'in_list' => false,
            'default_in_list' => false,
            'comment' => '基础物料ID,关联material_basic_material.bm_id'
        ),
        'quantity' => array(
            'type' => 'decimal(20,1)',
            'label' => '数量',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 22,
        ),
        'goodsName' => array(
            'type' => 'varchar(150)',
            'label' => 'SKU名称',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 23,
        ),
        'bills_amount' => array(
            'type' => 'decimal(20,3)',
            'label' => '单据金额',
            'default' => 0.000,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 30,
        ),
        'rebate_amount' => array(
            'type' => 'decimal(20,3)',
            'label' => '返利金额',
            'default' => 0.000,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 32,
        ),
        'settle_amount' => array(
            'type' => 'decimal(20,3)',
            'label' => '应结金额',
            'default' => 0.000,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 34,
        ),
        'tax_point' => array(
            'type' => 'decimal(20,3)',
            'label' => '点位',
            'default' => 0.000,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 36,
        ),
        'vendorCode' => array(
            'type' => 'varchar(32)',
            'label' => '供应商简码',
            'editable' => true,
            'filtertype' => 'normal',
            'filterdefault' => 'true',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 60,
        ),
        'vendorName' => array(
            'type' => 'varchar(80)',
            'label' => '供应商名称',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 62,
        ),
        'complete_time' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '订单完成时间',
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 80,
        ),
        'business_time' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '业务时间',
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 82,
        ),
        'ouName' => array(
            'type' => 'varchar(32)',
            'label' => '合同主体',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 84,
        ),
        'is_factory' => array(
            'type' => 'varchar(50)',
            'label' => '是否厂直',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 85,
        ),
        'departmentName' => array(
            'type' => 'varchar(80)',
            'label' => '部门',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 130,
            'order' => 86,
        ),
        'teamName' => array(
            'type' => 'varchar(32)',
            'label' => '组别',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 87,
        ),
        'spare_barcode' => array(
            'type' => 'varchar(32)',
            'label' => '备件库条码',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 88,
        ),
        'kefu_order' => array(
            'type' => 'varchar(32)',
            'label' => '客户订单号',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 89,
        ),
        'invoiceMode' => array(
            'type' => 'varchar(32)',
            'label' => '开票方向',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 89,
        ),
        'xniName' => array(
            'type' => 'varchar(32)',
            'label' => '采购类型',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 90,
        ),
        
        'sync_status' => array(
            'type' => array(
                '0' => '未同步',
                '1' => '同步成功',
                '2' => '同步失败',
            ),
            'label' => '同步状态',
            'editable' => true,
            'default' => '0',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 110,
            'order' => 15,
        ),
        'error_msg' => array (
            'type' => 'text',
            'label' => '失败原因',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 380,
            'order' => 90,
        ),
        'create_time' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '创建时间',
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 98,
        ),
        'last_modified' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '最后修改时间',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 99,
        ),
    ),
    'index' => array(
        'uni_sole_bn' => array(
            'columns' => array(
                0 => 'sole_bn',
            ),
            'prefix' => 'unique',
        ),
        'ind_orderNo' => array(
            'columns' => array(
                0 => 'orderNo',
            ),
        ),
        'ind_shqid' => array(
            'columns' => array(
                0 => 'shqid',
            ),
        ),
        'ind_expenseId' => array(
            'columns' => array(
                0 => 'expenseId',
            ),
        ),
        
        'ind_sku' => array(
            'columns' => array(
                0 => 'sku',
            ),
        ),

        'ind_complete_time' => array(
            'columns' => array(
                0 => 'complete_time',
            ),
        ),
        'ind_create_time' => array(
            'columns' => array(
                0 => 'create_time',
            ),

        ),
        'ind_sync_status' => array(
            'columns' => array(
                0 => 'sync_status',
            ),

        ),
       

    ),
    'engine' => 'innodb',
    'version' => '$Rev: $',
    'comment' => '结算订单详细表',
);