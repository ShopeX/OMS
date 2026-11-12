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

$db['aoxiang_logistics'] = array(
    'columns' => array(
        'lid' => array( 
            'pkey' => 'true',
            'type' => 'int unsigned',
            'extra' => 'auto_increment',
            'label' => '映射ID',
            'order' => 1,
        ),
        'erp_code' => array(
            'type' => 'varchar(30)',
            'label' => '商家物流配编码',
            'width' => 180,
            'editable' => false,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 3,
        ),
        'shop_id' => array(
            'type' => 'table:shop@ome',
            'label' => '来源店铺',
            'width' => 120,
            'editable' => false,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 20,
        ),
        'shop_type' => array(
            'type' => 'varchar(50)',
            'label' => '店铺类型',
            'width' => 90,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 22,
        ),
        'corp_id' => array (
            'type' => 'table:dly_corp@ome',
            'label' => '物流公司',
            'required' => false,
            'editable' => false,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 10,
        ),
        'logi_code' => array(
            'type' => 'varchar(32)',
            'label' => '物流公司编码',
            'editable' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => 'true',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 12,
        ),
        'create_time' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '创建时间',
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'time',
            'filterdefault' => true,
            'order' => 98,
        ),
        'last_modified' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '最后修改时间',
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'time',
            'filterdefault' => true,
            'order' => 99,
        ),
        'sync_status' => array (
            'type' => array(
                'none' => '未同步',
                'fail' => '同步失败',
                'succ' => '同步成功',
            ),
            'default' => 'none',
            'label' => '同步状态',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'width' => 120,
            'order' => 80,
        ),
        'sync_msg' => array (
            'type' => 'text',
            'label' => '同步失败原因',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 82,
        ),
        'fail_nums' => array (
            'type' => 'number',
            'default' => 0,
            'label' => '失败次数',
            'width' => 90,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 84,
        ),
    ),
    'index' => array(
        'ind_erp_code' => array (
            'columns' => array (
                0 => 'erp_code',
                1 => 'shop_id',
            ),
            'prefix' => 'unique',
        ),
        'ind_shop_sync' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'sync_status',
            ),
        ),
        'ind_logi_code' => array(
            'columns' => array(
                0 => 'logi_code',
            ),
        ),
        'ind_create_time' => array (
            'columns' => array (
                0 => 'create_time',
            ),
        ),
        'ind_last_modified' => array(
            'columns' => array(
                0 => 'last_modified',
            ),
        ),
    ),
    'engine' => 'innodb',
    'version' => '$Rev: $',
    'comment' => '物流公司映射表',
);