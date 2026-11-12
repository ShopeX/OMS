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

$db['aoxiang_delivery'] = array(
    'columns' => array(
        'did' => array(
            'pkey' => 'true',
            'type' => 'int unsigned',
            'extra' => 'auto_increment',
            'label' => '映射ID',
            'order' => 1,
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
            'order' => 10,
        ),
        'shop_type' => array(
            'type' => 'varchar(50)',
            'label' => '店铺类型',
            'width' => 90,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 12,
        ),
        'delivery_id' => array (
            'type' => 'int unsigned',
            'label' => '发货单',
            'required' => false,
            'editable' => false,
            'in_list' => false,
            'default_in_list' => false,
            'order' => 2,
        ),
        'delivery_bn' => array(
            'type' => 'varchar(32)',
            'label' => '发货单号',
            'editable' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => 'true',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 3,
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
            'order' => 99,
        ),
        'sync_status' => array (
            'type' => array(
                'none' => '未同步',
                'fail' => '同步失败',
                'succ' => '同步成功',
                'cancel_fail' =>'取消失败',
                'cancel_succ' =>'取消成功',
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
        'ind_shop_sync' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'sync_status',
            ),
        ),
        'ind_shop_delivery' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'delivery_id',
            ),
        ),
        
        'ind_delivery_bn' => array(
            'columns' => array(
                0 => 'delivery_bn',
            ),
        ),
        'ind_create_time' => array (
            'columns' => array (
                0 => 'create_time',
            ),
        ),
    ),
    'engine' => 'innodb',
    'version' => '$Rev: $',
    'comment' => '发货单表',
);