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

$db['order_split'] = array(
    'columns' =>
        array(
            'sid' =>
                array(
                    'type' => 'number',
                    'required' => true,
                    'pkey' => true,
                    'extra' => 'auto_increment',
                    'editable' => false,
                ),
            'name' =>
                array(
                    'type' => 'varchar(200)',
                    'required' => true,
                    'editable' => false,
                    'is_title' => true,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'width' => 130,
                    'label' => '规则名称',
                    'order' => 10
                ),
            'describe' =>
                array(
                    'type' => 'text',
                    'default' => '',
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => false,
                    'width' => 230,
                    'label' => '简述',
                    'order' => 20
                ),
            'split_type' =>
                array(
                    'type' => 'varchar(20)',
                    'default' => 0,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'width' => 200,
                    'label' => '拆单类型',
                    'order' => 30
                ),
            'split_config' =>
                array(
                    'type' => 'serialize',
                    'default' => '',
                    'editable' => false,
                    'in_list' => false,
                    'default_in_list' => false,
                    'width' => 200,
                    'label' => '拆单配置',
                ),
            'createtime' =>
                array(
                    'type' => 'time',
                    'label' => '创建时间',
                    'width' => 130,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => false,
                    'order' => 40
                ),
            'last_modified' =>
                array(
                    'label' => '最后修改时间',
                    'type' => 'last_modify',
                    'width' => 130,
                    'editable' => false,
                    'in_list' => true,
                    'default_in_list' => true,
                    'order' => 50
                ),
        ),
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);