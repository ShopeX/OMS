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

$db['replenish_task'] = array(
    'columns' => array(
        'task_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'label' => 'ID',
            'width' => 110,
            'hidden'   => true,
            'editable' => false,
        ),
        'task_bn' => array(
            'type' => 'varchar(30)',
            'label' => '补货任务号',
            'required' => true,
            'default_in_list' => true,
            'in_list' => true,
            'searchtype' => 'head',
            'width' => 150,
            'order' => 10,
        ),
        'task_name' => array(
            'type' => 'varchar(50)',
            'label' => '补货任务名称',
            'required' => true,
            'default_in_list' => true,
            'in_list' => true,
            'searchtype' => 'head',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'order' => 20,
        ),
        'store_type' => array(
            'label' => '门店类型',
            'type' => array (
                'normal' => '正价店铺',
                'discount' => '奥莱店铺',
            ),
            'default' => 'normal',
            'required' => true,
            'label' => '门店类型',
            'default_in_list' => true,
            'in_list' => true,
            'order' => 30,
        ),
        'store_ids' => array (
            'type' => 'text',
            'required' => false,
            'editable' => false,
            'label' => '适用多门店',
        ),
        'start_time' => array(
            'type' => 'time',
            'label' => '销售开始日期',
            'in_list' => true,
            'default' => 0,
            'default_in_list' => true,
            'in_list' => true,
            'order' => 40,
        ),
        'end_time' => array(
            'type' => 'time',
            'label' => '销售结束日期',
            'in_list' => true,
            'default' => 0,
            'default_in_list' => true,
            'in_list' => true,
            'order' => 41,
        ),
        'multipl_num' => array(
            'type' => 'decimal(3,2)',
            'label' => '销售补货系数',
            'default' => 0,
            'required' => true,
            'editable' => false,
            'default_in_list' => true,
            'in_list' => true,
            'order' => 25,
        ),
        'bm_ids' => array (
            'type' => 'text',
            'required' => false,
            'editable' => false,
            'label' => '适用多商品',
        ),
        'out_branch_id' => array (
            'type' => 'table:branch@ome',
            'editable' => false,
            'label' => '调出仓库',
            'width' => 110,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 39,
        ),
        'task_status' => array(
            'type' => array(
                '0' => '待确认',
                '1' => '已确认',
                '2' => '执行中',
                '3' => '已生成补货建议',
                '4' => '待生成补货单',
                '5' => '已生成补货单',
                '6' => '补货完成',
                '7' => '执行失败',
            ),
            'default' => '0',
            'label' => '单据状态',
            'width' => 110,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'has',
            'filterdefault' => true,
            'order' => 22,
        ),
        'task_msg' => array (
            'type' => 'varchar(200)',
            'label' => '失败原因',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 90,
        ),
        'create_time' => array(
            'type' => 'time',
            'label' => '创建时间',
            'in_list' => true,
            'default' => 0,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'time',
            'filterdefault' => true,
            'order' => 98,
        ),
        'last_modified' => array(
            'label' => '最后更新时间',
            'type' => 'last_modify',
            'width' => 130,
            'editable' => false,
            'in_list'  => true,
            'default_in_list' => true,
            'order' => 99,
        ),
    ),
    'index' => array(
        'idx_task_bn' => array(
            'columns' => array('task_bn'),
            'prefix' => 'unique',
        ),
        'idx_task_name' => array(
            'columns' => array('task_name'),
        ),
        'idx_task_status' => array(
            'columns' => array('task_status'),
        ),
        'idx_create_time' => array(
            'columns' => array('create_time'),
        ),
    ),
    'engine'  => 'innodb',
    'version' => '$Rev: $',
    'comment' => '补货任务表',
);
