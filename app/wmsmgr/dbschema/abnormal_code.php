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

$db['abnormal_code'] = array (
    'columns' => array (
        'abnormal_id' => array (
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'editable' => false,
            'extra' => 'auto_increment',
            'order' => 1,
        ),
        'abnormal_code' => array (
            'type' => 'varchar(32)',
            'required' => true,
            'label' => '错误码',
            'editable' => false,
            'width' => 130,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 10,
        ),
        'abnormal_name' => array (
            'type' => 'varchar(50)',
            'required' => true,
            'label' => '错误标题',
            'editable' => false,
            'width' => 150,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 11,
        ),
        'abnormal_type' => array (
            'type' => array (
                'delivery' => '发货单',
                'return' => '售后单',
            ),
            'default' => 'delivery',
            'width' => 120,
            'required' => true,
            'editable' => false,
            'label' => '单据类型',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 20,
        ),
        'disabled' => array (
            'type' => 'bool',
            'default' => 'false',
            'editable' => false,
            'label' => '是否禁用',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 50,
        ),
        'create_time' => array (
            'type' => 'time',
            'label' => '创建时间',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 98,
        ),
        'last_modified' => array (
            'label' => '最后更新时间',
            'type' => 'last_modify',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 99,
        ),
    ),
    'index' => array (
        'ind_type_code' => array (
            'columns' => array (
                0 => 'abnormal_code',
                1 => 'abnormal_type',
            ),
        ),
    ),
    'comment' => 'WMS异常错误码',
    'engine' => 'innodb',
    'version' => '$Rev: 1001',
);
