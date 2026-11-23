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
 * 接口字段映射表
 * 
 * @copyright shopex.cn 2025.01.01
 * @author system
 */
$db['interfacefield_mapper'] = array(
    'columns' => array(
        'id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'label' => 'ID',
            'width' => 110,
            'hidden' => true,
            'editable' => false,
            'comment' => '自增主键',
        ),
        'channel_id' => array(
            'type' => 'varchar(32)',
            'required' => true,
            'default' => '',
            'label' => '渠道ID',
            'width' => 150,
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'comment' => '渠道ID',
        ),
        'channel_type' => array(
            'type' => 'varchar(32)',
            'required' => true,
            'default' => '',
            'label' => '渠道类型',
            'width' => 120,
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'comment' => '渠道类型',
        ),
        'scene' => array(
            'type' => 'varchar(32)',
            'default' => '',
            'label' => '场景',
            'width' => 120,
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'comment' => '场景',
        ),
        'content' => array(
            'type' => 'longtext',
            'default' => '',
            'label' => '字段内容',
            'width' => 300,
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'comment' => '字段内容',
        ),
        'at_time' => array(
            'type' => 'TIMESTAMP',
            'default' => 'CURRENT_TIMESTAMP',
            'label' => '创建时间',
            'width' => 130,
            'editable' => false,
            'in_list' => true,
            'order' => 330,
            'filtertype' => 'normal',
            'comment' => '创建时间',
        ),
        'up_time' => array(
            'type' => 'TIMESTAMP',
            'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'label' => '更新时间',
            'width' => 130,
            'editable' => false,
            'in_list' => true,
            'order' => 340,
            'filtertype' => 'normal',
            'comment' => '更新时间',
        ),
    ),
    'index' => array(
        'idx_channel' => array(
            'columns' => array('channel_id', 'channel_type'),
        ),
        'idx_scene' => array(
            'columns' => array('scene'),
        ),
        'ind_at_time' => array(
            'columns' => array('at_time'),
        ),
        'ind_up_time' => array(
            'columns' => array('up_time'),
        ),
    ),
    'engine' => 'innodb',
    'charset' => 'utf8mb4',
    'collate' => 'utf8mb4_general_ci',
    'comment' => '接口字段映射表',
); 