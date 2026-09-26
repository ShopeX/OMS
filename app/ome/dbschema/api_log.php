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

$db['api_log'] = array(
    'columns' => array(
        'log_id'          => array(
            'type'            => 'varchar(32)',
            'required'        => true,
            'pkey'            => true,
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => true,
            'label'           => '日志编号',
            'width'           => 100,
            'panel_id'        => 'api_log_finder_top',
        ),
        'createtime'      => array(
            'type'            => 'time',
            'label'           => '发起同步时间',
            'width'           => 160,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype' => 'time',
            'filterdefault' => true,
            'pkey'            => true,
        ),
        'original_bn'     => array(
            'type'            => 'varchar(50)',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'searchtype'      => 'nequal',
            'label'           => '单据号',
            'width'           => 230,
            'order'           => '3',
            'panel_id'        => 'api_log_finder_top',
        ),
        'task_name'       => array(
            'type'            => 'varchar(255)',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'searchtype'      => 'has',
            'label'           => '任务名称',
            'width'           => 450,
            'panel_id'        => 'api_log_finder_top',
        ),
        'status'          => array(
            'type'            => array(
                'running' => '运行中',
                'success' => '成功',
                'fail'    => '失败',
                'sending' => '发起中',
            ),
            'default'         => 'sending',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'editable'        => false,
            'filtertype'      => 'yes',
            'filterdefault'   => true,
            'label'           => '状态',
            'width'           => 90,
            'panel_id'        => 'api_log_finder_top',
        ),
        'worker'          => array(
            'type'     => 'varchar(200)',
            'editable' => false,
            'label'    => 'api方法名',
            'in_list'  => true,
            'default_in_list' => true,
            'width'    => 210,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => true,
        ),
        'original_params' => array(
            'type'     => 'longtext',
            'editable' => false,
            'label'    => '原始参数',
        ),
        'params'          => array(
            'type'          => 'longtext',
            'editable'      => false,
            'label'         => '接口参数',
            'filtertype'    => 'yes',
            'filterdefault' => true,
            'searchtype'    => 'has',
            'panel_id'      => 'api_log_finder_top',
        ),
        'transfer'        => array(
            'type'          => 'longtext',
            'editable'      => false,
            'label'         => '转换参数',
            'filtertype'    => 'yes',
            'filterdefault' => true,
        ),
        'response'        => array(
            'type'          => 'longtext',
            'editable'      => false,
            'label'         => '响应参数',
            'filtertype'    => 'yes',
            'filterdefault' => true,

        ),
        'sync'            => array(
            'type'     => array(
                'true'  => '同步',
                'false' => '异步',
            ),
            'editable' => false,
            'label'    => '同异步类型',
        ),
        'msg'             => array(
            'type'     => 'text',
            'editable' => false,
            'comment'  => '信息',
            'in_list'  => true,
            'default_in_list' => true,
            'label' => '信息',
            'width' => 320,
        ),
        'log_type'        => array(
            'type'     => 'varchar(32)',
            'editable' => false,
            'label'    => '日志类型',
        ),
        'api_type'        => array(
            'type'            => array(
                'response' => '响应',
                'request'  => '请求',
            ),
            'editable'        => false,
            'default'         => 'request',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'yes',
            'filterdefault'   => true,
            'label'           => '同步类型',
            'width'           => 90,
        ),
        'error_lv'        => array(
            'type'          => 'varchar(32)',
            'editable'      => false,
            'default'       => 'normal',
            'label'         => '错误级别',
            'filtertype'    => 'yes',
            'filterdefault' => true,
        ),
        'marking_value'   => array(
            'type'     => 'varchar(80)',
            'edtiable' => false,
            'comment'  => '标识值',
        ),
        'marking_type'    => array(
            'type'     => 'varchar(32)',
            'edtiable' => false,
            'comment'  => '标识类型',
        ),
        'memo'            => array(
            'type'     => 'text',
            'edtiable' => false,
            'comment'  => '备注',
        ),
        'msg_id'          => array(
            'type'            => 'varchar(60)',
            'filtertype'      => 'yes',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'searchtype'      => 'nequal',
            'label'           => 'msg_id',
            'width'           => 250,
            'edtiable'        => false,
            'panel_id'        => 'api_log_finder_top',
            'comment'         => '中心信息id',
        ),
        'retry'           => array(
            'type'            => 'number',
            'default'         => 0,
            'width'           => 60,
            'edtiable'        => false,
            // 'in_list'         => true,
            'label'           => '重试次数',
            // 'default_in_list' => true,
        ),
        'addon'           => array(
            'type'     => 'longtext',
            'editable' => false,
            'label'    => '附加参数',
        ),
        'unique'          => array(
            'type'     => 'varchar(32)',
            'editable' => false,
            'label'    => '日志唯一性',
        ),
        'last_modified'   => array(
            'label'           => '最后重试时间',
            'type'            => 'last_modify',
            'width'           => 160,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'spendtime'      => array(
            'type'            => 'float',
            'label'           => '耗时',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'width'           => 90,
        ),
        'url' =>
            array (
                'type' => 'varchar(255)',
                'label' => '请求地址',
                'width' => 150,
            ),
    ),
    'index'   => array(
        'ind_status'      => array(
            'columns' => array(
                0 => 'status',
            ),
        ),
        'ind_createtime'  => array(
            'columns' => array(
                0 => 'createtime',
            ),
        ),
        'ind_unique'      => array(
            'columns' => array(
                0 => 'unique',
            ),
        ),
        'ind_api_type'    => array(
            'columns' => array(
                0 => 'api_type',
            ),
        ),
        'ind_original_bn' => array(
            'columns' => array(
                0 => 'original_bn',
            ),
        ),
        'ind_msg_id'      => array(
            'columns' => array(
                0 => 'msg_id',
            ),
        ),
        'ind_task_name'   => array(
            'columns' => array(
                0 => 'task_name',
            ),
        ),
        'ind_spendtime'    => array(
            'columns' => array(
                0 => 'spendtime',
            ),
        ),
        'ind_worker'    => array(
            'columns' => array(
                0 => 'worker',
            ),
        ),
    ),
    // 'partition' => [
    //     'type' => 'RANGE',
    //     'columns' => 'createtime',
    //     'partitions' => [
    //         'p'.date('Y_m_d') => strtotime('+1 day',strtotime(date('Y-m-d'))), // 当天
    //         'p'.date('Y_m_d',strtotime('+1 day'))  => strtotime('+2 day',strtotime(date('Y-m-d'))), // 明天
    //         'p'.date('Y_m_d',strtotime('+2 day'))  => strtotime('+3 day',strtotime(date('Y-m-d'))), // 后天
    //         'p_future' => 'MAXVALUE', // 兜底分区
    //     ],
    // ],
    'comment' => 'api日志',
    'engine'  => 'innodb',
    'version' => '$Rev: 44513 $',
    'charset' => 'utf8mb4',
);