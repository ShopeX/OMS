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

/**
 * 客户分类数据结构
 *
 * @author wangbiao@shopex.cn
 * @version 2025.06.11
 */

$db['customer_classify'] = array(
    'columns' => array(
        'class_id' => array(
            'pkey' => 'true',
            'type' => 'int unsigned',
            'extra' => 'auto_increment',
            'label' => '主键ID',
            'required' => true,
            'order' => 1,
        ),
        'class_bn' => array(
            'type' => 'varchar(32)',
            'label' => '客户分类编码',
            'editable' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'textarea',
            'filterdefault' => 'true',
            'in_list' => true,
            'default_in_list' => true,
            'width' => 180,
            'order' => 10,
        ),
        'class_name' => array(
            'type' => 'varchar(50)',
            'is_title' => true,
            'label' => '客户分类名称',
            'width' => 260,
            'searchtype' => 'has',
            'editable' => false,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 12,
        ),
        'disabled' => array (
            'type' => 'bool',
            'default' => 'false',
            'label' => '是否屏蔽',
            'width' => 150,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 90,
        ),
        'at_time' => array(
            'type' => 'TIMESTAMP',
            'label' => '创建时间',
            'default' => 'CURRENT_TIMESTAMP',
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' =>  true,
            'default_in_list' => true,
            'width' => 180,
            'order' => 98,
        ),
        'up_time' => array(
            'type' => 'TIMESTAMP',
            'label' => '更新时间',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'in_list' => true,
            'default_in_list' => true,
            'width' => 180,
            'order' => 99,
        ),
    ),
    'index' => array(
        'uni_class_bn' => array(
            'columns' => array(
                0 => 'class_bn',
            ),
            'prefix' => 'unique',
        ),
        'ind_class_name' => array(
            'columns' => array(
                0 => 'class_name',
            ),
        ),
        'ind_disabled' => array(
            'columns' => array(
                0 => 'disabled',
            ),
        ),
        'ind_at_time' => array(
            'columns' => array(
                0 => 'at_time',
            ),
        ),
    ),
    'engine' => 'innodb',
    'version' => '$Rev: $',
    'comment' => '销售客户分类表',
);
