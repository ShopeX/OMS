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

$db['appropriation'] = array(
    'columns' => array(
        'appropriation_id' => array(
            'type'     => 'number',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'label'    => 'ID',
            'width'    => 110,
            'editable' => false,
        ),
        'appropriation_no' => array(
            'type'            => 'varchar(45)',
            'label'           => '调拨单号',
            'width'           => 160,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'create_time'      => array(
            'type'            => 'time',
            'label'           => '生成日期',
            'width'           => 160,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
    'confirm_time'     => array(
            'type'            => 'time',
            'comment'         => '审核时间',
            'filtertype'      => 'time',
            'filterdefault'   => true,
            'default_in_list' => true,
            'in_list'         => true,
            'label'           => '审核时间',
        ),
        'operator_name'    => array(
            'type'            => 'varchar(50)',
            'label'           => '经办人',
            'width'           => 110,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'type'             => array(
            'type'            => array(
                0 => '调拔单',
                1 => '理货单',
            ),
            'default'         => '0',
            'required'        => true,
            'label'           => '类型',
            'width'           => 75,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => false,
        ),
        
        'bill_type'        => array(
            'type'            => array(
                'transfer'        => '调拔',
                'replenishment'   => '补货',
                'returndefective' => '残次品退仓',
                'returnnormal'    => '正品退仓',
                'flattransfer'    => '平调',
                'o2otransfer'     => '门店调拔',
                'o2oprepayed'     => '门店预订单',  
            ),
            'default'         => 'transfer',
            'label'           => '业务类型',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'process_status'   => array(
            'type'            => array(
                '0' => '新建',
                '1' => '待审核',
                '2' => '已审核',
                '3' => '待发货',
                '4' => '待收货',
                '5' => '完成',
                '6' => '取消',
                '7' => '拒绝',
            ),

            'default'         => '1',
            'label'           => '处理状态',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'memo'             => array(
            'type'     => 'longtext',
            'editable' => false,
            'label'           => '备注',
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'corp_id'          => array(
            'type'     => 'number',
            'comment'  => '物流公司ID',
            'editable' => false,
            'label'    => '物流公司',
        ),
        'from_branch_id'   => array(
            'type'            => 'table:branch@ome',
            'in_list'         => true,
            'default_in_list' => true,
            'label'           => '调出仓库',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'editable'        => false,
        ),
        'to_branch_id'     => array(
            'type'            => 'table:branch@ome',
            'label'           => '调入仓库',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'required'        => true,
        ),
        'from_physics_id'=>array(
            'type'            => 'table:store@o2o',
            'label'           => '调出方门店',
            'in_list'         => true,
            'default_in_list' => true,
            'default' => 0,
        ),
        'to_physics_id'=>array(
            'type'            => 'table:store@o2o',
            'label'           => '调入方门店',
            'in_list'         => true,
            'default_in_list' => true,
            'default' => 0,
        ),
        'delivery_time'    => array(
            'type'          => 'time',
            'label'         => '发货时间',
            'comment'       => '发货时间',
            'editable'      => false,
            'filtertype'    => 'time',
            'filterdefault' => true,
            'in_list'       => true,
        ),
        'original_bn'      => array(
            'type'            => 'varchar(255)',
            'label'           => '原始单据号',
            'default_in_list' => true,
            'in_list'         => true,
            'searchtype'      => 'nequal',
        ),
        'original_id'      => array(
            'type'    => 'int unsigned',
            'comment' => '原始单据id',
        ),
        'business_bn'      => array(
            'type'            => 'varchar(255)',
            'label'           => '业务单据号',
            'default_in_list' => true,
            'in_list'         => true,
     
            'searchtype'      => 'nequal',
        ),
        'logi_code'          => array(
            'type'            => 'varchar(25)',
            'label'           => '物流编码',
            'editable'        => false,
            'width'           => 110,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'logi_no'          => array(
            'type'            => 'varchar(50)',
            'label'           => '物流单号',
            'editable'        => false,
            'width'           => 110,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'source_from' => array(
            'type'    => array(
                'pc'  => 'PC发起',
                'pad' => 'PAD发起',
                'pos' => 'POS',  
            ),
            'default' => 'pad',
            'label'   => '订单创建端',
        ),
        'extrabranch_bn'   => array(
            'type'    => 'varchar(30)',
            'label'   => '转储入库仓编码',
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
       
        'extra_ship_mobile'   => array(
            'type'     => 'varchar(200)',
            'label'    => '外部联系人手机',
            'editable' => false,
            'in_list'  => true,
        ),
        'at_time'               => array(
            'type'    => 'TIMESTAMP',
            'label'   => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
            'width'   => 120,
            'in_list' => false,
        ),
        'up_time'               => array(
            'type'    => 'TIMESTAMP',
            'label'   => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width'   => 120,
            'in_list' => false,
        ),
    ),
    'index'   => array(
        'ind_appropriation_no' => array(
            'columns' => array(
                0 => 'appropriation_no',
            ),
        ),
        'ind_up_time' => array(
            'columns' => array(
                0 => 'up_time',
            ),
        ),
    ),
    'comment' => '库存调整单（调拨单）',
    'engine'  => 'innodb',
    'version' => '$Rev: 44513 $',
);
