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

$db['aoxiang_product'] = array(
    'columns' => array(
        'pid' => array(
            'pkey' => true,
            'type' => 'varchar(32)',
            'required' => true,
            'label' => 'ID',
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
            'order' => 2,
        ),
        'shop_bn' => array (
            'type' => 'varchar(20)',
            'label' => '店铺编码',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 3,
        ),
        'product_id' => array (
            'type' => 'int unsigned',
            'label' => '商品ID',
            'editable' => false,
            'in_list' => false,
            'default_in_list' => false,
            'order' => 10,
        ),
        'product_bn' => array(
            'type' => 'varchar(50)',
            'label' => '商品编码',
            'editable' => true,
            'searchtype' => 'nequal',
            'filtertype' => 'normal',
            'filterdefault' => 'true',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 12,
        ),
        'barcode' => array(
            'type' => 'varchar(50)',
            'label' => '条形码',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 14,
        ),
        'product_name' => array(
            'type' => 'varchar(200)',
            'label' => '店铺宝贝标题',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => false,
            'order' => 92,
        ),
        'product_type' => array (
            'type' => array(
                'normal' => '普通',
                'combine' => '组合',
            ),
            'label' => '商品类型',
            'default' => 'normal',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 100,
            'order' => 50,
        ),
        'shop_iid' => array(
            'type' => 'varchar(50)',
            'required' => false,
            'label' => '店铺商品ID',
            'in_list' => true,
            'default_in_list' => false,
            'width' => 120,
            'order' => 20,
        ),
        'product_mapping' => array (
            'type' => array(
                '0' => '未映射',
                '1' => '已映射',
                '2' => '映射失败',
            ),
            'label' => 'OMS商品映射',
            'default' => '0',
            'editable' => false,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 100,
            'order' => 52,
        ),
        'approve_status' => array(
            'type' => array(
                'onsale' => '上架',
                'instock' => '下架',
            ),
            'default' => 'onsale',
            'label' => '商品在架状态',
            'filterdefault' => true,
            'in_list'  => true,
            'default_in_list' => true,
            'filtertype' => true,
            'order' => 55
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
                'running' => '同步中',
                'lack' => '组合无子货品',
            ),
            'default' => 'none',
            'label' => '同步状态',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'width' => 120,
            'order' => 30,
        ),
        'sync_msg' => array (
            'type' => 'text',
            'label' => '同步失败原因',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 32,
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
        'mapping_status' => array(
            'type' => array(
                'none' => '未关联',
                'fail' => '关联失败',
                'succ' => '关联成功',
                'running' => '关联中',
                'splitting' => '部分关联',
                'invalid' => '无效的',
            ),
            'default' => 'none',
            'label' => '商货品关联状态',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 35,
            'filtertype' => 'normal',
            'filterdefault' => true,
        ),
        'mapping_time' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '商货品关联时间',
            'in_list' => true,
            'default_in_list' => false,
            'order' => 38,
        ),
        'delete_status' => array (
            'type' => array(
                'none' => '默认',
                'fail' => '删除失败',
                'succ' => '删除成功',
                'running' => '删除中',
            ),
            'default' => 'none',
            'label' => '删除状态',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'width' => 120,
            'order' => 85,
        ),
        'sync_stock_time' => array(
            'type' => 'time',
            'default' => 0,
            'label' => '回写库存时间',
            'in_list' => true,
            'default_in_list' => false,
            'order'=>110,
        ),
    ),
    'index' => array(
        'uni_shop_product' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'product_type',
                2 => 'product_bn',
            ),
            'prefix' => 'unique',
        ),
        'ind_shop_product_bn' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'product_bn',
            ),
        ),
        'ind_shop_sync' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'sync_status',
            ),
        ),
        'ind_shop_sync_type' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'sync_status',
                2 => 'product_type',
            ),
        ),
        'ind_shop_mapping' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'sync_status',
                2 => 'mapping_status',
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
        'ind_sync_stocks' => array(
            'columns' => array(
                0 => 'shop_id',
                2 => 'sync_stock_time',
            ),
        ),
        'ind_shop_productid' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'product_id',
            ),
        ),
    ),
    'engine' => 'innodb',
    'version' => '$Rev: $',
    'comment' => '店铺商品映射表',
);