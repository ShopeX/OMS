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

$db['po'] = array(
    'columns' =>
        array(
            'po_id'       =>
                array(
                    'type'     => 'number',
                    'required' => true,
                    'pkey'     => true,
                    'extra'    => 'auto_increment',
                    'editable' => false,
                ),
            'name'        =>
                array(
                    'type'            => 'varchar(200)',
                    'label'           => '采购单名称',
                    'width'           => 160,
                    'editable'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                    'searchtype'      => 'has',
                    'filtertype' => 'normal',
                    'filterdefault'   => true,
                ),
            'po_bn'       =>
                array(
                    'type'            => 'varchar(32)',
                    'required'        => true,
                    'label'           => '采购单编号',
                    'width'           => 140,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => false,
                    'is_title'        => true,
                    'searchtype'      => 'has',
                    'filtertype' => 'normal',
                    'filterdefault'   => true,
                ),
            'supplier_id' =>
                array(
                    'type'            => 'table:supplier',
                    'required'        => true,
                    'label'           => '供应商',
                    'width'           => 110,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                    //'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault'   => true,
                ),

            'purchase_time' =>
                array(
                    'type'            => 'time',
                    'label'           => '采购日期',
                    'width'           => 75,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'has',
                    'filtertype' => 'normal',
                    'filterdefault'   => false,
                ),
            'amount'        =>
                array(
                    'type'            => 'money',
                    'label'           => '金额总计',
                    'width'           => 75,
                    'editable'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
            'operator'      =>
                array(
                    'type'            => 'varchar(50)',
                    'label'           => '采购员',
                    'width'           => 75,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'has',
                    'filtertype' => 'normal',
                    'filterdefault'   => true,
                ),
            'po_type'       =>
                array(
                    'type'            =>
                        array(
                            'cash'   => '现购',
                            'credit' => '赊购',
                        ),
                    'label'           => '付款单 / 赊购单',
                    'width'           => 100,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'has',
                    'filtertype' => 'normal',
                    'filterdefault'   => false,
                ),

            'branch_id'       =>
                array(
                    'type'            => 'table:branch@ome',
                    'label'           => '仓库',
                    'width'           => 75,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => false,
                    'searchtype'      => 'has',
                    'filtertype' => 'normal',
                    'filterdefault'   => false,
                ),
            'arrive_time'     =>
                array(
                    'type'          => 'time',
                    'label'         => '预计到货',
                    'width'         => 75,
                    'editable'      => false,
                    'in_list'       => true,
                    'searchtype'    => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => false,
                ),
            'deposit'         =>
                array(
                    'type'     => 'money',
                    'label'    => '预付款原款',
                    'width'    => 110,
                    'editable' => false,
                    'in_list'  => true,
                ),
            'deposit_balance' =>
                array(
                    'type'     => 'money',
                    'label'    => '预付款金额',
                    'width'    => 110,
                    'editable' => false,
                ),

            'product_cost'     =>
                array(
                    'type'            => 'money',
                    'label'           => '商品总额',
                    'width'           => 75,
                    'default'         => 0,
                    'editable'        => false,
                    'in_list'         => false,
                    'default_in_list' => false,
                ),
            'delivery_cost'    =>
                array(
                    'type'            => 'money',
                    'label'           => '物流费用',
                    'width'           => 75,
                    'default'         => 0,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                ),
            'memo'             =>
                array(
                    'type'     => 'longtext',
                    'editable' => false,
                ),
            'eo_status'        =>
                array(
                    'type'            =>
                        array(
                            0 => 'N/A',
                            1 => '待入库',
                            2 => '部分入库',
                            3 => '已入库',
                            4 => '未入库',
                        ),
                    'default'         => 1,
                    'label'           => '入库状态',
                    'width'           => 60,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault'   => false,
                ),
            'check_status'     =>
                array(
                    'type'            =>
                        array(
                            1 => '未审',
                            2 => '已审',

                        ),
                    'default'         => 1,
                    'label'           => '审核状态',
                    'width'           => 60,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'filterdefault'   => true,
                ),
            'defective_status' => array(
                'type'    =>
                    array(
                        0 => '无需确认',
                        1 => '未确认',
                        2 => '已确认',
                    ),
                'default' => '0',
                'label'   => '残损确认',
            ),
            'check_operator'   =>
                array(
                    'type'            => 'varchar(50)',
                    'label'           => '审核人',
                    'width'           => 75,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'has',
                    'filtertype' => 'normal',
                    'filterdefault'   => true,
                ),
            'op_name'          =>
                array(
                    'type'            => 'varchar(50)',
                    'label'           => '采购单创建人',
                    'default'         => '',
                    'width'           => 90,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'has',
                    'filtertype' => 'normal',
                    'filterdefault'   => true,
                ),
            'check_time'       =>
                array(
                    'type'            => 'time',
                    'label'           => '审核时间',
                    'width'           => 75,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                    'searchtype'      => 'has',
                    'filtertype' => 'normal',
                    'filterdefault'   => false,
                ),
            'statement'        =>
                array(
                    'type'            =>
                        array(
                            1 => '未结算',
                            2 => '部分结算',
                            3 => '已结算',
                        ),
                    'default'         => 1,
                    'label'           => '结算状态',
                    'width'           => 60,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                ),
            'po_status'        =>
                array(
                    'type'            =>
                        array(
                            1 => '已新建',
                            2 => '采购终止',
                            3 => '采购退货',
                            4 => '采购完成',
                        ),
                    'default'         => 1,
                    'label'           => '采购状态',
                    'width'           => 60,
                    'editable'        => false,
                    'in_list'         => true,
                    'default_in_list' => true,
                ),
            'emergency'        =>
                array(
                    'type'     => 'bool',
                    'default'  => 'false',
                    'label'    => '是否紧急',
                    'width'    => 60,
                    'editable' => false,
                    'in_list'  => true,
                ),
            'out_iso_bn'       =>
                array(
                    'type'  => 'varchar(32)',
                    'label' => '外部采购单编号',
                    'width' => 140,
                ),
            'accos_po_id'      => [
                'type'  => 'number',
                'label' => '关联采购单id'
            ],
            'accos_po_bn'      => [
                'type'  => 'varchar(32)',
                'label' => '关联采购单号'
            ],
            'po_species'      => [
                'type'  => [
                    1 => '正常采购单',
                    2 => '采购补货单'
                ],
                'default' => 1,
                'label' => '采购单种类',
                'filtertype' => 'normal',
                'filterdefault'   => false,
            ],
            'receive_status'   =>
                array(
                    'type'     => 'bigint(20)',
                    'label'    => '接收状态',
                    'editable' => false,
                    'default'  => '0',
                ),
            'last_modify'      =>
                array(
                    'type'            => 'last_modify',
                    'label'           => '最后更新时间',
                    'editable'        => false,
                    'filtertype' => 'normal',
                    'filterdefault'   => false,
                    'in_list'         => true,
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
    'index'   =>
        array(
            'ind_po_bn'       =>
                array(
                    'columns' =>
                        array(
                            0 => 'po_bn',
                        ),
                    'prefix'  => 'unique',
                ),
            'ind_statement'   =>
                array(
                    'columns' =>
                        array(
                            0 => 'statement',
                        ),
                ),
            'ind_po_status'   =>
                array(
                    'columns' =>
                        array(
                            0 => 'po_status',
                        ),
                ),
            'ind_eo_status'   =>
                array(
                    'columns' =>
                        array(
                            0 => 'eo_status',
                        ),
                ),
            'ind_po_species' =>
                array(
                    'columns' =>
                        array(
                            0 => 'po_species',
                        ),
                ),
            'ind_last_modify' =>
                array(
                    'columns' =>
                        array(
                            0 => 'last_modify',
                        ),
                ),

        ),
    'comment' => '采购单',
    'engine'  => 'innodb',
    'version' => '$Rev: 44513 $',
);
