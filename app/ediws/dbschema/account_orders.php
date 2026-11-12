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

$db['account_orders'] = array(
    'columns' => array(
        'ord_id' => array(
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
        
        'kDanHao' => array(
            'type' => 'varchar(32)',
            'label' => '出管单号',
            'editable' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => 'true',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 5,
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
            'order' => 5,
        ),
        'purchaseOrderNo' => array(
            'type' => 'varchar(32)',
            'label' => '采购单号',
            'filtertype' => 'normal',
            'filterdefault' => 'true',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 30,
        ),
        'sku' => array(
            'type' => 'varchar(32)',
            'label' => '商品编码',
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
          'in_list' => true,
        'default_in_list' => true,  
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
        'barcode' => array(
            'type' => 'varchar(255)',
            'label' => '商品条形码',
            'in_list'         => true,
            'default_in_list' => true,
            'editable' => false,
        ),
        
        'product_name' => array(
            'type' => 'varchar(255)',
            'label' => '商品名称',
            'in_list'         => true,
            'default_in_list' => true,
            'editable' => false,
        ),
        'goodsName' => array(
            'type' => 'varchar(80)',
            'label' => '商品名称',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 21,
        ),
        'expenseCode' => array(
            'type' => 'varchar(32)',
            'label' => '费用项编码',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'searchtype' => 'nequal',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 52,
        ),
        'quantity' => array(
            'type' => 'mediumint',
            'label' => '销售数量',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 22,
        ),
        'price' => array(
            'type' => 'decimal(20,3)',
            'label' => '单价',
            'default' => 0.000,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 23,
        ),
        'amount' => array(
            'type' => 'decimal(20,3)',
            'label' => '销售金额',
            'default' => 0.000,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 46,
        ),
        'storeOutDate' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '出入库时间',
            'filtertype' => 'time',
             'default' => 0,
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 98,
        ),
        
        'supplierId' => array(
            'type' => 'varchar(32)',
            'label' => '供应商编码',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 60,
        ),

         'xniType' => array (
            'type' => 'number',
            'label' => '采购类型',
            'editable' => true,
            'default' => 0,
            'width' => 90,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 32,
        ),

        'purchaseDate' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '采购日期',
            'default' => 0,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 98,
        ), 

        'refType' => array(
            'type' => 'varchar(32)',
            'label' => '单据类型',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 70,
        ),

        'orderCompleteDate' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '订单完成时间',
            'default' => 0,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 98,
        ), 

        'salesPrice'=>array (
            'type' => 'decimal(20,3)',
            'editable' => false,
            'label' => '流水倒扣基础单价',
          
            'in_list' => true,
            'default_in_list' => true,
            'order' => 98,
        ), 

        'salesAmount'=>array (
            'type' => 'decimal(20,3)',
            'editable' => false,
            'label' => '流水倒扣基础金额',
          
            'in_list' => true,
            'default_in_list' => true,
            'order' => 98,
        ), 

       'rebateAmount' => array(
            'type' => 'decimal(20,3)',
            'label' => '扣点金额',
            'default' => 0.000,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 51,
        ),

       'rebateRate' => array(
            'type' => 'decimal(20,3)',
            'label' => '扣点比例',
            'default' => 0.000,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 50,
        ),

       'discountAmount' => array(
            'type' => 'decimal(20,3)',
            'label' => '折扣项金额',
            'default' => 0.000,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 52,
        ),

       'orderTime' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '下单时间',
            'default' => 0,
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 98,
        ), 

       'settleAmount' => array(
            'type' => 'decimal(20,3)',
            'label' => '结算金额',
            'default' => 0.000,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 52,
        ),

       'flowFlag' => array (
            'type' => 'tinyint(2)',
            'label' => '是否流水倒扣',
            'editable' => true,
            'default' => 0,
            'width' => 90,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 32,
        ),
        'bizType' => array(
            'type' => 'varchar(80)',
            'label' => '账套',
            'editable' => true,
           
            'in_list' => true,
            'default_in_list' => true,
            'order' => 23,
        ),
        'lastId'=>array(

            'type' => 'varchar(100)',

            'label' => 'lastId',
            'editable' => true,
           
            'in_list' => true,
            'default_in_list' => true,

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
            'order' => 30,
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
        
        'ind_orderNo' => array(
            'columns' => array(
                0 => 'orderNo',
            ),
        ),
        
        'ind_purchaseOrderNo' => array(
            'columns' => array(
                0 => 'purchaseOrderNo',
            ),
        ),
        'ind_kDanHao'=>array(

            'columns' => array(
                0 => 'kDanHao',
            ),
        ),
        'ind_sku' => array(
            'columns' => array(
                0 => 'sku',
            ),
        ),
        'ind_sync_status' => array(
            'columns' => array(
                0 => 'sync_status',
                1=>'refType',
            ),
        ),
        
        'ind_create_time' => array(
            'columns' => array(
                0 => 'create_time',
            ),
        ),
        'ind_refType' => array(
            'columns' => array(
                0 => 'refType',
            ),
        ),
        'idx_lastId' => array(
            'columns' => array(
                0 => 'lastId',
            ),
        ),
    ),
    'engine' => 'innodb',
    'version' => '$Rev: $',
    'comment' => '实销实结明细表',
);
