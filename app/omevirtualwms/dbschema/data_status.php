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
/*

*/

$db['data_status'] = array(

    'columns' => array(
        'id' => array(
            'type' => 'mediumint unsigned',
            'required' => true,
            'extra' => 'auto_increment',
            'label' => 'ID',
            'pkey' => true,
            'editable' => false,
        ),
        'bn' => array(
            'type' => 'varchar(100)',
            'required' => true,
            'label' => '单号',
            'editable' => false,
        ),
        'type' => array(
            'type' => 'varchar(255)',
            'required' => true,
            'label' => '类型',
            'editable' => false,
        ),
        'status' => array(
            'type' => 'varchar(100)',
            'required' => true,
            'label' => '单据状态',
            'editable' => false,
        ),
        'op_id' => array(
            'type' => 'table:account@pam',
            'label' => '操作员ID',
            'editable' => false,
            'in_list' => false,
            'default_in_list' => false,
        ),
        'op_name' => array (
            'type' => 'varchar(30)',
            'label' => '操作员名称',
            'editable' => false,
            'in_list' => false,
            'default_in_list' => false,
        ),
        'create_time' => array(
            'type' => 'time',
            'required' => true,
            'label' => '创建时间',
            'editable' => false,
        )
    ),
    'index' => array(
        'idx_bn' => array(
            'columns' => array('bn','type'),
        ),
        
        'idx_type' => array(
            'columns' => array('type')
        ),
        'idx_create_time' => array(
            'columns' => array('create_time')
        )
    ),
    'comment' => '数据状态',
    'engine' => 'Innodb',
    'version' => '$Rev: $'

);
