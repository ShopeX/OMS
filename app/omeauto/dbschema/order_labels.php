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

$db['order_labels'] = array(
    'columns' => array(
        'label_id' => array(
            'type' => 'number',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
            'order' => 1,
        ),
        'label_name' => array(
            'type' => 'varchar(30)',
            'required' => true,
            'editable' => false,
            'searchtype' => 'has',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 260,
            'label' => '标签名称',
            'order' => 12,
        ),
        'label_code' => array(
            'type' => 'varchar(30)',
            'required' => true,
            'editable' => false,
            'searchtype' => 'has',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 260,
            'label' => '标签代码',
            'order' => 10,
        ),
        'label_color' => array(
            'type' => 'varchar(30)',
            'required' => true,
            'editable' => false,
            'searchtype' => 'has',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => false,
            'default_in_list' => false,
            'width' => 120,
            'label' => '标签颜色',
            'order' => 15,
        ),
        'source' => array(
            'type' => 'varchar(30)',
            'searchtype' => 'has',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 130,
            'label' => '标签来源',
            'order' => 20,
        ),
        'create_time' => array(
            'type' => 'time',
            'label'  => '创建时间',
            'width' => 130,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 98,
        ),
        'last_modified' => array(
            'type' => 'time',
            'label' => '最后更新时间',
            'width' => 130,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 99,
        ),
    ),
    'index' => array(
        'in_label_code' => array(
            'columns'=> array('label_code')
        ),
        'in_label_name' => array(
            'columns'=> array('label_name')
        ),
    ),
    'comment' => '标签表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);