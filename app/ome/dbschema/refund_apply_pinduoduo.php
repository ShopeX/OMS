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

$db['refund_apply_pinduoduo']=array (
    'columns' =>
        array (
            'apply_id' => array (
                'type' => 'int unsigned',
                'required' => true,
                'pkey' => true,
                'editable' => false,
            ),
            'refund_apply_bn' => array (
                'type' => 'varchar(32)',
                'required' => true,
                'default' => '',
                'label' => '退款申请单号',
                'width' => 140,
                'editable' => false,
                'in_list' => true,
                'is_title' => true,
            ),
            'shop_id' => array (
                'type' => 'table:shop@ome',
                'label' => '来源店铺',
                'width' => 75,
                'editable' => false,
                'in_list' => true,
                'filtertype' => 'normal',
                'filterdefault' => true,
            ),
            'refuse_memo' => array(
                'type' => 'longtext',
                'label' => '拒绝退款原因留言',
            ),
            'oid' => array (
                'type' => 'varchar(50)',
                'default' => 0,
                'editable' => false,
                'label' => '子订单号',
            ),
            'cs_status' => array(
                'type' => 'varchar(50)',
                'default'=>'1',
                'comment' => '客服介入状态',
                'editable' => false,
                'label' => '客服介入状态',
                'width' =>65,
            ),
            'operation_constraint'=>array(
                'type' => array(
                    'cannot_refuse'=>'不允许操作',
                    'refund_onweb' => '需要到网页版操作',
                ),
                'default'=>'cannot_refuse',
                'editable' => false,
                'label' => '退款约束',
            ),
            'buyer_nick' => array(
                'type'=>'varchar(50)',
                'label'=>'买家昵称',
            ),
            'seller_nick' => array(
                'type'=>'varchar(50)',
                'label'=>'卖家昵称',
            ),
            'has_good_return' => array(
                'type' => 'varchar(32)',
                'label'=>'买家是否需要退货',
            ),
            'payment_id' => array(
                'type'=>'varchar(100)',
                'label'=>'支付交易号',
            ),
            'refund_fee' => array(
                'type'=>'money',
                'label'=>'需退金额',
            ),
        ),
    'index' =>
        array (
            'ind_refund_apply_bn_shop' =>
                array (
                    'columns' =>
                        array (
                            0 => 'refund_apply_bn',
                            1 => 'shop_id',
                            2=>'apply_id',
                        ),
                    'prefix' => 'unique',
                ),
            
        ),
    'comment' => '退款申请拼多多附加信息表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);
