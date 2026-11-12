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

$db['delivery'] = array(
    'columns' =>
        array(
            'delivery_id' =>
                array(
                    'type' => 'int unsigned',
                    'required' => true,
                    'pkey' => true,
                    'editable' => false,
                    'extra' => 'auto_increment',

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
                    'label' => '订单内容',
                    'comment' => '订单内容',
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'default' => '',
                ),
            'delivery_bn' =>
                array(
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '发货单号',
                    'comment' => '配送流水号',
                    'editable' => false,
                    'width' => 140,
                    'searchtype' => 'nequal',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'is_title' => true,
                ),
            'member_id' =>
                array(
                    'type' => 'table:members@ome',
                    'label' => '会员用户名',
                    'comment' => '订货会员ID',
                    'editable' => false,
                    'width' => 75,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
            'is_cod' =>
                array(
                    'type' => 'bool',
                    'default' => 'false',
                    'required' => true,
                    'label' => '是否货到付款',
                    'editable' => false,
                ),
            'delivery' =>
                array(
                    'type' => 'varchar(20)',
                    'label' => '配送方式',
                    'comment' => '配送方式(货到付款、EMS...)',
                    'editable' => false,
                    'in_list' => true,
                    'width' => 65,
                    'default_in_list' => true,
                    'is_title' => true,
                ),
            'logi_id' =>
                array(
                    'type' => 'table:dly_corp@ome',
                    'comment' => '物流公司ID',
                    'editable' => false,
                    'label' => '物流公司',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'default_in_list' => true,
                    'in_list' => true,
                ),
            'logi_no' =>
                array(
                    'type' => 'varchar(50)',
                    'label' => '物流单号',
                    'comment' => '物流单号',
                    'editable' => false,
                    'width' => 130,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'searchtype' => 'nequal',
                ),
            'ship_name' =>
                array(
                    'type' => 'varchar(255)',
                    'label' => '收货人',
                    'comment' => '收货人姓名',
                    'editable' => false,
                    'searchtype' => 'tequal',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'width' => 75,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
            'ship_area' =>
                array(
                    'type' => 'region',
                    'label' => '收货地区',
                    'comment' => '收货人地区',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'width' => 130,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
            'ship_province' =>
                array(
                    'type' => 'varchar(50)',
                    'editable' => false,
                ),
            'ship_city' =>
                array(
                    'type' => 'varchar(50)',
                    'editable' => false,
                ),
            'ship_district' =>
                array(
                    'type' => 'varchar(50)',
                    'editable' => false,
                ),
            'ship_addr' =>
                array(
                    'type' => 'text',
                    'label' => '收货地址',
                    'comment' => '收货人地址',
                    'editable' => false,
                    'filtertype' => 'normal',
                    'width' => 150,
                    'in_list' => true,
                ),
            'ship_zip' =>
                array(
                    'type' => 'varchar(20)',
                    'label' => '收货邮编',
                    'comment' => '收货人邮编',
                    'editable' => false,
                    'width' => 75,
                    'in_list' => true,
                    'default_in_list' => true,
                ),
            'ship_tel' =>
                array(
                    'type' => 'varchar(200)',
                    'label' => '收货人电话',
                    'comment' => '收货人电话',
                    'editable' => false,
                    'in_list' => true,
                ),
            'ship_mobile' =>
                array(
                    'type' => 'varchar(200)',
                    'label' => '收货人手机',
                    'comment' => '收货人手机',
                    'editable' => false,
                    'in_list' => true,
                ),
            'ship_email' =>
                array(
                    'type' => 'varchar(150)',
                    'label' => '收货人Email',
                    'comment' => '收货人Email',
                    'editable' => false,
                    'in_list' => true,
                ),
            'ship_time' =>
                array(
                    'type' => 'varchar(50)',
                    'editable' => false,
                ),
            'create_time' =>
                array(
                    'type' => 'time',
                    'label' => '单据创建时间',
                    'comment' => '单据生成时间',
                    'editable' => false,
                    'filtertype' => 'time',
                    'in_list' => true,
                ),
            'status' =>
                array(
                    'type' =>
                        array(
                            'succ' => '已发货',
                            'failed' => '发货失败',
                            'cancel' => '已取消',
                            'progress' => '等待配货',
                            'timeout' => '超时',
                            'ready' => '等待配货',
                            'stop' => '暂停',
                            'back' => '打回',
                            'return_back'=>'退回',
                        ),
                    'default' => 'ready',
                    'width' => 150,
                    'required' => true,
                    'comment' => '状态',
                    'editable' => false,
                    'label' => '发货状态',
                ),
            'memo' =>
                array(
                    'type' => 'longtext',
                    'label' => '备注',
                    'comment' => '备注',
                    'editable' => false,
                    'in_list' => true,
                ),
            'disabled' =>
                array(
                    'type' => 'bool',
                    'default' => 'false',
                    'comment' => '无效',
                    'editable' => false,
                    'label' => '无效',
                    'in_list' => true,
                ),
            'expre_status' =>
                array(
                    'type' => 'bool',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'required' => true,
                    'width' => 75,
                    'editable' => false,
                    'default' => 'false',
                    'comment' => '快递单是否打印',
                    'label' => '快递单打印',
                ),
            'net_weight' =>
                array(
                    'type' => 'money',
                    'editable' => false,
                    'comment' => '商品重量',
                ),
            'weight' =>
                array(
                    'type' => 'money',
                    'editable' => false,
                    'comment' => '包裹重量',
                ),
            'last_modified' =>
                array(
                    'label' => '最后更新时间',
                    'type' => 'last_modify',
                    'editable' => false,
                    'in_list' => true,
                ),
            'delivery_time' =>
                array(
                    'type' => 'time',
                    'label' => '发货时间',
                    'comment' => '发货时间',
                    'editable' => false,
                    'in_list' => true,
                    'filtertype' => 'time',
                ),
            'delivery_cost_expect' =>
                array(
                    'type' => 'money',
                    'default' => '0',
                    'editable' => false,
                    'comment' => '预计物流费用(包裹重量计算的费用)',
                ),
            'delivery_cost_actual' =>
                array(
                    'type' => 'money',
                    'editable' => false,
                    'comment' => '实际物流费用(物流公司提供费用)',
                ),
            'shop_id' =>
                array(
                    'type' => 'table:shop@ome',
                    'label' => '来源店铺',
                    'width' => 75,
                    'editable' => false,
                    'in_list' => true,
                    'filtertype' => 'normal',
                ),
            'order_createtime' =>
                array(
                    'type' => 'time',
                    'label' => '订单创建时间',
                    'width' => 130,
                    'editable' => false,
                    'filtertype' => 'time',
                    'in_list' => false,
                ),
            'op_id' =>
                array(
                    'type' => 'table:account@pam',
                    'editable' => false,
                    'required' => true,
                ),
            'op_name' =>
                array(
                    'type' => 'varchar(30)',
                    'editable' => false,
                ),
            'is_sync' => array(
                'type' => 'bool',
                'editable' => false,
                'default' => 'false',
                'comment' => '是否回传',
            ),
            'shop_type' =>
            array (
              'type' => 'varchar(50)',
              'label' => '店铺类型',
              'width' => 75,
              'editable' => false,
            ),
        ),
    'index' =>
        array(
            'ind_delivery_bn' =>
                array(
                    'columns' =>
                        array(
                            0 => 'delivery_bn',
                        ),
                    'prefix' => 'unique',
                ),
            'ind_logi_no' =>
                array(
                    'columns' =>
                        array(
                            0 => 'logi_no',
                        ),
                    'prefix' => 'unique',
                ),
            // 'ind_logi_id' =>
            //     array(
            //         'columns' =>
            //             array(
            //                 0 => 'logi_id',
            //             ),
            //     ),
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
            'ind_status' =>
                array(
                    'columns' =>
                        array(
                            0 => 'status',
                        ),
                )
        ),
    'engine' => 'innodb',
    'version' => '$Rev: 41996 $',
);