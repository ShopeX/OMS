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

$db['organization']=array (
    'columns' => array (
        'org_id' => array(
            'type' => 'number',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
            'order' => 1,
        ),
        'org_no' => array(
            'type' => 'varchar(15)',
            'label' => '组织编码',
            'width' => 120,
            'in_list' => true,
            'default_in_list' => true,
            'searchtype' => 'has',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'order' => 2,
        ),
        'org_name' => array(
            'type' => 'varchar(50)',
            'label' => '组织名称',
            'width' => 260,
            'in_list' => true,
            'default_in_list' => true,
            'searchtype' => 'has',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'order' => 3,
        ),
        'org_type' => array(
            'type' => 'tinyint(1)',
            'label' => '组织类型',
            'default' => 1,
            'in_list' => true,
            'order' => 4,
        ),
        'org_level_num' => array(
            'type' => 'int(2)',
            'label' => '组织层级',
            'width' => 80,
            'default' => 1,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 5,
        ),
        'parent_id' => array(
            'type' => 'number',
            'label' => '上级组织id',
            'width' => 100,
            'in_list' => true,
            'order' => 6,
            'default' => 0,
        ),
        'parent_no' => array(
            'type' => 'varchar(15)',
            'label' => '上级组织编码',
            'width' => 120,
            'in_list' => true,
            'default_in_list' => true,
            'searchtype' => 'has',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'order' => 7,
        ),
        'child_nos' => array(
            'type' => 'text',
            'label' => '下级组织编码',
            'width' => 320,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 8,
        ),
        'child_names' => array(
            'type' => 'text',
            'label' => '下级组织名称',
            'width' => 320,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 9,
        ),
        'area' =>
        array (
            'type' => 'region',
            'label' => '地区',
            'width' => 170,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 3,
        ),
        'status' => array(
            'type' => 'tinyint(1)',
            'label' => '状态',
            'width' => 120,
            'default' => 2,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 10,
        ),
        'create_time' => array(
            'type' => 'time',
            'label' => '新建时间',
            'width' => 120,
            'in_list' => true,
            'order' => 11,
        ),
        'last_modify' => array(
            'type' => 'last_modify',
            'label' => '最后更新时间',
            'width' => 120,
            'in_list' => true,
            'order' => 11,
        ),
        'del_mark' => array(
            'type' => 'tinyint(1)',
            'label' => '删除标识',
            'default' => 0,
            'order' => 12,
        ),
        'del_time' => array(
            'type' => 'time',
            'label' => '删除时间',
            'width' => 120,
            'in_list' => true,
            'order' => 13,
        ),
        'recently_enabled_time' => array(
            'type' => 'time',
            'label' => '最近启用时间',
            'width' => 120,
            'in_list' => true,
            'order' => 14,
        ),
        'recently_stopped_time' => array(
            'type' => 'time',
            'label' => '最近停用时间',
            'width' => 120,
            'in_list' => true,
            'order' => 15,
        ),
        'first_enable_time' => array(
            'type' => 'time',
            'label' => '首次启用时间',
            'width' => 120,
            'in_list' => true,
            'order' => 16,
        ),
        'org_parents_structure' => array(
            'type' => 'varchar(255)',
            'label' => '组织架构结构',
            'order' => 17,
        ),
        'haschild' => array(
            'type' => 'tinyint(1)',
            'default' => 0,
            'label' => '是否存在下级',
            'order' => 18,
        ),
    ),
    
    'index' => array (
        'ind_org_no' => 
        array (
            'columns' =>
                array (
                    0 => 'org_no',
                ),
            'prefix' => 'unique',
        ),
        'ind_org_type' =>
        array (
            'columns' =>
            array (
                    0 => 'org_type',
            ),
        ),
        'ind_org_level_num' =>
        array (
            'columns' =>
            array (
                    0 => 'org_level_num',
            ),
        ),
        'ind_parent_id' =>
        array (
            'columns' =>
            array (
                    0 => 'parent_id',
            ),
        ),
        'ind_parent_no' =>
        array (
            'columns' =>
            array (
                    0 => 'parent_no',
            ),
        ),
        'ind_status' =>
        array (
            'columns' =>
            array (
                    0 => 'status',
            ),
        ),
        'ind_del_mark' =>
        array (
            'columns' =>
            array (
                    0 => 'del_mark',
            ),
        ),
        'ind_haschild' =>
        array (
            'columns' =>
            array (
                    0 => 'haschild',
            ),
        ),
    ),
    'comment' => '组织层级表',
    'engine' => 'innodb',
    'version' => '$Rev: $',
);