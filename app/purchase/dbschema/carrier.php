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

$db['carrier']=array (
    'columns' => array (
            'cid' =>
            array (
                    'type' => 'number',
                    'required' => true,
                    'pkey' => true,
                    'extra' => 'auto_increment',
                    'editable' => false,
            ),
            'carrier_code' =>
            array (
                    'type' => 'varchar(80)',
                    'required' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'searchtype' => 'nequal',
                    'filterdefault' => true,
                    'filtertype' => 'yes',
                    'label' => '承运商编号',
                    'order' => 5,
            ),
            'carrier_name' =>
            array (
                    'type' => 'varchar(80)',
                    'required' => true,
                    'editable' => false,
                    'is_title' => true,
                    'searchtype' => 'has',
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'width' => 130,
                    'label' => '承运商名称',
                    'order' => 6,
            ),
            'shop_id' =>
            array (
                    'type' => 'table:shop@ome',
                    'label' => '来源店铺',
                    'editable' => false,
                    'required' => true,
                    'in_list' => true,
                    'default_in_list' => true,
                    'filtertype' => 'normal',
                    'filterdefault' => true,
                    'order' => 7,
            ),
            'tms_carrier_id' =>
            array (
                    'type' => 'varchar(64)',
                    'label' => 'tms端承运商ID',
                    'editable' => false,
                    'required' => false,
            ),
            'carrier_shortname' =>
            array (
                    'type' => 'varchar(80)',
                    'required' => false,
                    'editable' => false,
                    'in_list' => false,
                    'default_in_list' => false,
                    'width' => 130,
                    'label' => '承运商简称',
            ),
            'carrier_isvalid' =>
            array (
                    'type' => 'number',
                    'label' => '承运商状态',
                    'editable' => false,
                    'required' => false,
                    'in_list' => false,
                    'default_in_list' => false,
                    'default' => 0,
                    'order' => 89,
            ),
            'platform_status' => array (
                'type' => 'varchar(30)',
                'label' => '平台状态',
                'editable' => false,
                'required' => false,
                'in_list' => false,
                'default_in_list' => false,
                'order' => 50,
            ),
            'memo' => array (
                'type' => 'varchar(80)',
                'label' => '备注',
                'editable' => false,
                'required' => false,
                'in_list' => true,
                'default_in_list' => true,
                'width' => 300,
                'order' => 95,
            ),
            'disabled' => array(
                'type'          => 'bool',
                'label'         => '禁用状态',
                'default'       => 'false',
                'in_list'       => true,
                'default_in_list' => true,
                'order' => 90,
            ),
            'at_time' => array(
                'type'            => 'TIMESTAMP',
                'label'           => '创建时间',
                'default'         => 'CURRENT_TIMESTAMP',
                'in_list'         => true,
                'default_in_list' => true,
                'order'           => 1000,
            ),
            'up_time' => array(
                'type'            => 'TIMESTAMP',
                'label'           => '更新时间',
                'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'in_list'         => true,
                'default_in_list' => true,
                'order'           => 1010,
            ),
    ),
    'index' => array (
        'idx_shop_carrier_code'  => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'carrier_code',
            ),
            'prefix' => 'unique',
        ),
        'idx_disabled'  => array(
            'columns' => array(
                0 => 'disabled',
            ),
        ),
    ),
    'comment' => '承运商管理表',
    'engine' => 'innodb',
    'version' => '$Rev: $',
);