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

$db['iso'] = array(
    'columns' => array(
        'iso_id'           => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'editable' => false,
            'extra'    => 'auto_increment',
        ),
        'confirm'          => array(
            'type'     => 'tinybool',
            'default'  => 'N',
            'required' => true,
            'label'    => '确认状态',
            'width'    => 75,
            'hidden'   => true,
            'editable' => false,
        ),
        'defective_status' => array(
            'type'          => array(
                0 => '无需确认',
                1 => '未确认',
                2 => '已确认',
            ),
            'filtertype'    => 'has',
            'filterdefault' => true,
            'default'       => '0',
            'label'         => '残损确认状态',
        ),
        'name'             => array(
            'type'            => 'varchar(200)',
            'label'           => '出入单名称',
            'width'           => 160,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'filtertype' => 'normal',
            'filterdefault'   => true,
        ),
        'iso_bn'           => array(
            'type'            => 'varchar(32)',
            'required'        => true,
            'label'           => '出入库单号',
            'is_title'        => true,
            'default_in_list' => true,
            'in_list'         => true,
            'width'           => 125,
            'searchtype'      => 'nequal',
            'filtertype'      => 'textarea',
            'filterdefault'   => true,
        ),
        'out_iso_bn'       => array(
            'type'          => 'varchar(32)',
            'label'         => '外部出入库单号',
            'is_title'      => true,
            'width'         => 125,
            'default_in_list' => true,
            'in_list'         => true,
            'filtertype'    => 'normal',
            'filterdefault' => true,
        ),
        'type_id'          => array(
            'type'            => 'table:iostock_type@ome',
            'required'        => true,
            'default_in_list' => true,
            'in_list'         => true,
            'comment'         => '出入库类型id',
            'label'           => '出入库类型',
            'filtertype'      => 'has',
            'filterdefault'   => true,
        ),
        'branch_id'        => array(
            'type'            => 'table:branch@ome',
            'required'        => true,
            'label'           => '仓库名称',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'default_in_list' => true,
            'in_list'         => true,
        ),
        'original_bn'      => array(
            'type'            => 'varchar(255)',
            'label'           => '关联单号',
            'searchtype'      => 'nequal',
            'default_in_list' => true,
            'in_list'         => true,
        ),
        'original_id'      => array(
            'type'    => 'int unsigned',
            'comment' => '原始单据id',
        ),
        'supplier_id'      => array(
            'type'    => 'number',
            'comment' => '供应商id',
        ),
        'supplier_name'    => array(
            'type'    => 'varchar(32)',
            'label'   => '供应商名称',
            'comment' => '供应商名称',
        ),
        'product_cost'     => array(
            'type'     => 'money',
            'label'    => '商品总额',
            'width'    => 75,
            'default'  => 0,
            'editable' => false,
            'in_list'  => false,
        ),
        'iso_price'        => array(
            'type'     => 'money',
            'label'    => '出入库费用',
            'required' => true,
            'default'  => 0,
            'in_list'  => false,
        ),
        'cost_tax'         => array(
            'type'    => 'money',
            'comment' => '税率',
        ),
        'oper'             => array(
            'type'    => 'varchar(30)',
            'comment' => '经手人',
            'in_list' => true,
            'label'   => '经手人',
        ),
        'create_time'      => array(
            'type'            => 'time',
            'comment'         => '出入库时间',
            'filtertype'      => 'time',
            'filterdefault'   => true,
            'default_in_list' => true,
            'in_list'         => true,
            'label'           => '创建时间',
        ),
        'operator'         => array(
            'type'            => 'varchar(30)',
            'comment'         => '操作人员',
            'default_in_list' => true,
            'in_list'         => true,
            'label'           => '操作人员',
        ),
        'settle_method'    => array(
            'type'    => 'varchar(32)',
            'comment' => '结算方式',
            'label'   => '结算方式',
        ),
        'settle_status'    => array(
            'type'  => array(
                '0' => '未结算',
                '1' => '已结算',
                '2' => '部分结算',
            ),
            'label' => '结算状态',
        ),
        'settle_operator'  => array(
            'type'    => 'varchar(30)',
            'comment' => '结算人',
            'label'   => '结算人',
        ),
        'settle_time'      => array(
            'type'    => 'time',
            'comment' => '结算时间',
            'label'   => '结算时间',
        ),
        'complete_time'    => array(
            'type'    => 'time',
            'comment' => '出入库完成时间',
            'label'   => '出入库完成时间',
            'filtertype'      => 'time',
            'filterdefault'   => true,
            'default_in_list' => true,
            'in_list'         => true,
        ),
        'check_time' => array (
            'type' => 'time',
            'comment' => '单据审核时间',
            'editable' => false,
            'label' => '单据审核时间',
            'in_list' => true,
            'default_in_list'=>true,
        ),
        'settle_num'       => array(
            'type'    => 'number',
            'comment' => '结算数量',
            'label'   => '结算数量',
        ),
        'settlement_bn'    => array(
            'type'    => 'varchar(32)',
            'comment' => '结算单号',
            'label'   => '结算单号',
        ),
        'settlement_money' => array(
            'type'    => 'money',
            'comment' => '结算金额',
            'label'   => '结算金额',
        ),
        'memo'             => array(
            'type'    => 'text',
            'comment' => '备注',
            'label'   => '备注',
            'in_list' => true,
        ),
        'emergency'        => array(
            'type'     => 'bool',
            'default'  => 'false',
            'label'    => '是否紧急',
            'width'    => 60,
            'editable' => false,
            'in_list'  => true,
        ),
        'iso_status'       => array(
            'type'          => array(
                1 => '未出/入库',
                2 => '部分出/入库',
                3 => '全部出/入库',
                4 => '取消',
            ),
            'default'       => 1,
            'label'         => '出入库状态',
            'width'         => 60,
            'editable'      => false,
            'filtertype'    => 'has',
            'filterdefault' => true,
        ),
        'check_status'     => array(
            'type'            => array(
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
        'extrabranch_id'   => array(
            'type'    => 'number',
            'label'   => '外部仓库名称',
            'default' => 0,

        ),
        'extrabranch_bn' =>
            array (
                'type' => 'varchar(32)',
                'label' => '外部仓库编码',
                'default' => '',
            ),
        'extra_ship_name'     => array(
            'type'     => 'varchar(255)',
            'label'    => '外部联系人姓名',
            'editable' => false,
            'in_list'  => true,
        ),
        'extra_ship_area'     => array(
            'type'     => 'region',
            'label'    => '外部联系人地区',
            'editable' => false,
            'in_list'  => true,
        ),
        'extra_ship_addr'     => array(
            'type'     => 'text',
            'label'    => '外部联系人地址',
            'editable' => false,
            'in_list'  => true,
        ),
        'extra_ship_zip'      => array(
            'type'     => 'varchar(20)',
            'label'    => '外部联系人邮编',
            'editable' => false,
            'in_list'  => true,
        ),
        'extra_ship_tel'      => array(
            'type'     => 'varchar(200)',
            'label'    => '外部联系人电话',
            'editable' => false,
            'in_list'  => true,
        ),
        'extra_ship_mobile'   => array(
            'type'     => 'varchar(200)',
            'label'    => '外部联系人手机',
            'editable' => false,
            'in_list'  => true,
        ),
        'extra_ship_email'    => array(
            'type'     => 'varchar(150)',
            'label'    => '外部联系人Email',
            'editable' => false,
            'in_list'  => true,
        ),
        'cost_type'    => array(
            'type'     => 'varchar(30)',
            'label'    => '费用类型',
            'editable' => false,
            'in_list'  => true,
        ),
        'cost_department'    => array(
            'type'     => 'varchar(50)',
            'label'    => '领用部门',
            'editable' => false,
            'in_list'  => true,
        ),
        'corp_id'          => array(
            'type'     => 'number',
            'comment'  => '物流公司ID',
            'editable' => false,
            'label'    => '物流公司',

        ),
        'appropriation_no' => array(
            'type'            => 'varchar(20)',
            'label'           => '调拨单号',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'width'           => 130,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'receive_status'   => array(
            'type'     => 'bigint(20)',
            'label'    => '接收状态',
            'editable' => false,
            'default'  => '0',
        ),
        // 'process_status'   => array(
        //     'type'    => array(
        //         'ACCEPT'        => '仓库接单',
        //         'PARTFULFILLED' => '部分收货完成',
        //         'FULFILLED'     => '收货完成',
        //         'PRINT'         => '打印',
        //         'PICK'          => '捡货',
        //         'PACKAGE'       => '打包',
        //         'WEIGH'         => '称重',
        //         'READY'         => '待提货',
        //         'DELIVERED'     => '已发货',
        //     ),
        //     'label'   => '单据状态',
        //     'in_list'         => true,
        //     'default_in_list' => true,
        //     'default' => 'ACCEPT',
        // ),
        'logi_no'          => array(
            'type'            => 'varchar(50)',
            'label'           => '物流单号',
            'editable'        => false,
            'width'           => 110,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'logi_code'          => array(
            'type'            => 'varchar(50)',
            'label'           => '物流公司',
            'editable'        => false,
            'width'           => 110,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'arrival_no'       => array (
            'type'            => 'varchar(32)',
            'label'           => '到货单号',
            'width'           => 140,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => false,
            'searchtype'      => 'has',
            'filtertype' => 'normal',
            'filterdefault'   => true,
        ),
        'at_time'          => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default'         => 'CURRENT_TIMESTAMP',
        ),
        'up_time'          => array(
            'type'            => 'TIMESTAMP',
            'label'           => '更新时间',
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ),
        'bill_type'          => array(
            'type'            => 'varchar(50)',
            'label'           => '业务类型',
            'comment'         => '业务类型',
            'default'         => 'other',  
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'business_bn'         => array(
            'type'            => 'varchar(255)',
            'label'           => '业务单号',
            'default_in_list' => true,
            'in_list'         => true,
            'searchtype'      => 'nequal',
        ),
        'diff_status'      => array(
            'type'     => array(
                '0' => '无差异',
                '1' => '差异待处理',
                '2' => '差异已处理',
            ),
            'label'    => '差异状态',
            'editable' => false,
            'default'  => '0',
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
        'source'             => array(
            'type'     => 'varchar(50)',
            'default'  => 'local',
            'editable' => false,
            'label'    => '来源',
            'in_list'  => false,
            'default_in_list' => false,
        ),
    ),
    'index'   => array(
        'ind_iso_bn'           => array(
            'columns' => array(
                0 => 'iso_bn',
            ),
         'prefix' => 'unique'
        ),
        'ind_original_bn'      => array(
            'columns' => array(
                0 => 'original_bn',
            ),
        ),
        'ind_original_id'      => array(
            'columns' => array(
                0 => 'original_id',
            ),
        ),
        'ind_supplier_id'      => array(
            'columns' => array(
                0 => 'supplier_id',
            ),
        ),
        'ind_create_time'      => array(
            'columns' => array(
                0 => 'create_time',
            ),
        ),
        'ind_appropriation_no' => array(
            'columns' => array(
                0 => 'appropriation_no',
            ),
        ),
        'ind_arrival_no' => array(
            'columns' => array(
                0 => 'arrival_no',
            ),
        ),
        'ind_diff_status'      => array(
            'columns' => array(
                0 => 'diff_status',
            ),
        ),
        'ind_at_time' => array('columns' => array('at_time')),
        'ind_up_time' => array('columns' => array('up_time')),
        'ind_complete_time' => array('columns' => array('complete_time')),
    ),
    'comment' => '出入库单信息表',
    'engine'  => 'innodb',
    'version' => '$Rev:  51996',
);
