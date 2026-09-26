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

$db['pick_stockout_bills']=array (
    'columns' => array (
        'stockout_id' =>
            array (
              'type' => 'number',
              'required' => true,
              'pkey' => true,
              'extra' => 'auto_increment',
              'editable' => false,
              'order' => 1,
        ),
        'stockout_no' =>
            array (
                    'type' => 'varchar(32)',
                    'required' => true,
                    'label' => '出库单号',
                    'width' => 140,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'searchtype' => 'nequal',
                    'filterdefault' => true,
                    'filtertype' => 'yes',
                    'order' => 2,
            ),
        'branch_id' =>
            array (
                    'type' => 'table:branch@ome',
                    'label' => '出库仓',
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 20,
                    'filtertype' => true,
                    'filterdefault' => true,
            ),
        'pick_num' =>
            array (
                    'type' => 'number',
                    'label' => '拣货数量',
                    'editable' => false,
                    'width' => 90,
                    'in_list' => true,
                    'default_in_list' => true,
                    'required' => true,
                    'default' => 0,
                    'order' => 10,
            ),
        'branch_out_num' =>
            array (
                    'type' => 'number',
                    'label' => '仓库出库数量',
                    'editable' => false,
                    'width' => 90,
                    'in_list' => true,
                    'default_in_list' => true,
                    'required' => true,
                    'default' => 0,
                    'order' => 10,
            ),
        'status' =>
            array (
                    'type' => 'tinyint(1)',
                    'label' => '单据状态',
                    'width' => 130,
                    'editable' => false,
                    'default' => 1,
                    'order' => 11,
            ),
        'confirm_status' =>
            array (
                    'type' => 'tinyint(1)',
                    'label' => '审核状态',
                    'width' => 130,
                    'editable' => false,
                    'default' => 1,
                    'order' => 12,
            ),
        'o_status' =>
            array (
                    'type' => 'tinyint(1)',
                    'label' => '出库状态',
                    'width' => 100,
                    'editable' => false,
                    'default' => 1,
                    'order' => 13,
            ),
        'carrier_code' =>
            array (
                    'type' => 'varchar(32)',
                    'label' => '承运商',
                    'width' => 120,
                    'editable' => false,
                    'order' => 20,
            ),
        'delivery_no' =>
            array (
                    'type' => 'varchar(32)',
                    'label' => '运单号',
                    'width' => 120,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 20,
            ),
        'storage_no' =>
            array (
                    'type' => 'varchar(32)',
                    'label' => '入库单号',
                    'width' => 120,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 22,
            ),
        'delivery_time' =>
            array (
                    'type' => 'varchar(32)',
                    'label' => '送货批次时间',
                    'width' => 130,
                    'editable' => false,
                    'in_list' => true,
                    'order' => 30,
            ),
        'arrival_time' =>
            array (
                    'type' => 'varchar(32)',
                    'label' => '要求到货时间',
                    'width' => 130,
                    'editable' => false,
                    'in_list' => true,
                    'order' => 30,
            ),
        'is_air_embargo' =>
            array (
                    'type' => array(
                        '0' => '不禁运',
                        '1' => '禁运',
                    ),
                    'label' => '是否航空禁运',
                    'default' => '0',
                    'width' => 100,
                    'editable' => false,
                    'in_list' => true,
                    'order' => 30,
            ),
        'dly_mode' =>
            array (
                    'type' => 'tinyint(1)',
                    'label' => '配送方式',
                    'width' => 90,
                    'editable' => false,
                    'default' => 0,
                    'order' => 14,
            ),
        'create_time' =>
            array (
                    'type' => 'time',
                    'label' => '创建时间',
                    'default' => 0,
                    'width' => 130,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'time',
                    'filterdefault' => true,
                    'order' => 99,
            ),
        'last_modified' =>
            array (
                    'type' => 'time',
                    'label' => '最后更新时间',
                    'default' => 0,
                    'in_list' => true,
                    'width' => 130,
                    'editable' => false,
                    'order' => 99,
            ),
            'ship_time' =>
            array (
                    'type' => 'time',
                    'label' => '发货时间',
                    'width' => 100,
                    'editable' => false,
                    'in_list' => true,
                    'order' => 30,
        ),
        'check_time' => array (
            'type' => 'time',
            'comment' => '单据审核时间',
            'editable' => false,
            'label' => '单据审核时间',
            'in_list' => true,
            'default_in_list'=>true,
        ),
        'complete_time' => array (
            'type' => 'time',
            'comment' => '出库完成时间',
            'label' => '出库完成时间',
            'in_list' => true,
            'default_in_list'=>true,
        ),
        'receive_status' =>
            array (
                    'type' => 'bigint(20)',
                    'label' => '接收状态',
                    'editable' => false,
                    'default' => 0,
            ),
        'rsp_code' =>
            array (
                    'type' => 'tinyint(1)',
                    'label' => '唯品会Api接口失败状态',
                    'width' => 90,
                    'editable' => false,
                    'default' => 0,
            ),
    ),
    'index' => array (
        'ind_stockout_no' =>
            array (
            'columns' =>
                  array (
                    0 => 'stockout_no',
                  ),
        ),
    ),
    'comment' => '出库单',
    'engine' => 'innodb',
    'version' => '$Rev: $',
);
