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

$db['branch_product_extend'] = array (
    'columns' => array (
        'eid' => array (
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'editable' => false,
        ),
        'branch_id' => array (
            'type' => 'int unsigned',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'label' => '仓库ID',
            'in_list' => false,
            'default_in_list' => false,
        ),
        'product_id' => array (
            'type' => 'int unsigned',
            'required' => true,
            'default' => 0,
            'editable' => false,
            'label' => '基础物料ID',
            'in_list' => false,
            'default_in_list' => false,
        ),
        'store_sell_type' => array (
            'type' => array (
                'normal' => '无',
                'cash' => '现货模式',
                'presell' => '全款预售模式',
            ),
            'default' => 'normal',
            'width' => 120,
            'editable' => false,
            'label' => '库存销售模式',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 90,
        ),
        'sell_delay' => array (
            'type' => array (
                'normal' => '无',
                '0' => '当天发货',
                '1' => '24小时发货',
                '2' => '2天',
                '3' => '3天',
                '5' => '5天',
                '7' => '7天',
                '10' => '10天',
                '15' => '15天',
            ),
            'default' => 'normal',
            'width' => 120,
            'editable' => false,
            'label' => '发货时效',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 92,
        ),
        'sell_end_time' => array (
            'type' => 'time',
            'width' => 150,
            'editable' => false,
            'label' => '全款预售截止时间',
            'in_list' => false,
            'default_in_list' => false,
            'order' => 95,
        ),
        'sync_status' => array(
            'type' => array(
                'none' => '未回写',
                'fail' => '回写失败',
                'succ' => '回写成功',
            ),
            'default' => 'none',
            'label' => '回写状态',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
        ),
        'last_modify' => array (
            'label' => '最后更新时间',
            'type' => 'time',
            'default' => 0,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
        ),
    ),
    'index' => array (
        'ind_branch_id' => array (
            'columns' => array (
                0 => 'branch_id',
                1 => 'product_id',
            ),
            'prefix' => 'unique',
        ),
        'ind_sell_type' => array (
            'columns' => array (
                0 => 'store_sell_type',
            ),
        ),
        'ind_sync_status' => array (
            'columns' => array (
                0 => 'sync_status',
            ),
        ),
    ),
    'comment' => '库存销售模式表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);