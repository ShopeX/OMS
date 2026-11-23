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


$db['difference'] = array(
    'columns' => array(
        'id'     => array(
            'type'      => 'mediumint(8)',
            'label'     => 'ID',
            'comment'   => 'ID',
            'required'  => true,
            'pkey'      => true,
            'extra'     => 'auto_increment',
        ),
        'diff_bn'    => array(
            'type'              => 'varchar(32)',
            'label'             => '单号',
            'required'          => true,
            'in_list'           => true,
            'default_in_list'   => true,
            'searchtype'        => 'nequal',
            'filtertype'        => 'normal',
            'filterdefault'     => true,
            'order'             => 10,
        ),
        'task_id'     => array(
            'type'              => 'int unsigned',
            'label'             => '任务ID',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 20,
        ),
        'task_bn'    => array(
            'type'              => 'varchar(50)',
            'label'             => '任务单号',
            'in_list'           => true,
            'default_in_list'   => true,
            'searchtype'        => 'nequal',
            'filtertype'        => 'normal',
            'filterdefault'     => true,
            'order'             => 30,
        ),
        'oms_stores' => array(
            'type'              => 'mediumint',
            'label'             => '系统库存',
            'comment'           => '系统库存',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 60,
        ),
        'wms_stores' => array(
            'type'              => 'mediumint',
            'label'             => '盘点库存',
            'comment'           => '盘点库存',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 70,
        ),
        'diff_stores' => array(
            'type'              => 'mediumint',
            'label'             => '库存差异',
            'comment'           => '库存差异',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 80,
        ),
        'status'   => array(
            'type'              => [
                '1'=>'已确认','2'=>'待财务确认','3'=>'取消','4'=>'待审核'
            ],
            'label'             => '单据状态',
            'default'           => '2',
            'in_list'           => true,
            'default_in_list'   => true,
            'searchtype'        => 'nequal',
            'filtertype'        => 'normal',
            'filterdefault'     => true,
            'order'             => 90,
        ),
        'in_status'   => array(
            'type'              => [
                '0'=>'','1'=>'生成失败'
            ],
            'label'             => '入库单',
            'default'           => '0',
            'in_list'           => false,
            'default_in_list'   => false,
            'order'             => 90,
        ),
        'out_status'   => array(
            'type'              => [
                '0'=>'','1'=>'生成失败'
            ],
            'label'             => '出库单',
            'default'           => '0',
            'in_list'           => false,
            'default_in_list'   => false,
            'order'             => 90,
        ),
        'branch_id' => array (
            'type' => 'table:branch@ome',
            'in_list' => true,
            'default_in_list' => true,
            'label' => '仓库',
            'order' => 95,
        ),
        'negative_branch_id' => array (
            'type' => 'text',
            'in_list' => false,
            'default_in_list' => false,
            'label' => '盘亏仓库',
        ),
        'physics_id'=>array(
            'type'            => 'table:store@o2o',
            'label'           => '门店编码',
            'in_list'         => true,
            'default_in_list' => true,
        ),

        'operate_type' => array(
            'type' => array(
                'branch' => '仓库盘点',
                'store' => '门店盘点',
            ),
            'default'           => 'branch',
            'label'             => '类型',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 100,
        ),
        'adjust_oper'    => array(
            'type'              => 'varchar(32)',
            'label'             => '调整操作人',
            'comment'           => '调整操作人',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 120,
        ),
        'adjust_time' => array(
            'type'              => 'time',
            'label'             => '调整时间',
            'comment'           => '调整时间',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 130,
        ),
        'confirm_oper'    => array(
            'type'              => 'varchar(32)',
            'label'             => '确认操作人',
            'comment'           => '确认操作人',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 140,
        ),
        'confirm_time'    => array(
            'type'              => 'time',
            'label'             => '确认时间',
            'comment'           => '确认时间',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 150,
        ),
        'total_amount'=> array(
            'type'     => 'money',
            'default'  => '0',
            'in_list'           => true,
            'default_in_list'   => true,
            'label' => '盈亏总金额',
        ),

        'memo'    => array(
            'type'              => 'text',
            'label'             => '备注',
            'in_list'           => true,
            'default_in_list'   => true,
            'order'             => 160,
        ),
        'at_time'       => array(
            'type'    => 'TIMESTAMP',
            'label'   => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
            'width'   => 120,
            'in_list' => false,
            'order'   => 11,
        ),
        'up_time'       => array(
            'type'    => 'TIMESTAMP',
            'label'   => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width'   => 120,
            'in_list' => false,
            'order'   => 11,
        ),
    ),
    'index'   => array(
        'ind_diff_bn' => array(
            'columns' => array(
                0 => 'diff_bn',
            ),
            'prefix'  => 'unique',
        ),
        'ind_task_bn' => array(
            'columns' => array(
                0 => 'task_bn',
            ),
        ),
        'ind_status' => array(
            'columns' => array(
                0 => 'status',
            ),
        ),
        'ind_operate_type' => array(
            'columns' => array(
                0 => 'operate_type',
            ),
        ),
        'ind_adjust_oper' => array(
            'columns' => array(
                0 => 'adjust_oper',
            ),
        ),
        'ind_confirm_oper' => array(
            'columns' => array(
                0 => 'confirm_oper',
            ),
        ),
    ),
    'comment' => '盘点差异单',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
