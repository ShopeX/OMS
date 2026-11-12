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

$db['aoxiang_skus'] = array(
    'columns' => array(
        'sid' => array(
            'pkey' => true,
            'type' => 'varchar(32)',
            'required' => true,
            'label' => 'SKUID',
            'order' => 1,
        ),
        'pid' => array(
            'type' => 'table:aoxiang_product@dchain',
            'label' => '映射ID',
            'width' => 120,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 2,
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
        'product_name' => array(
            'type' => 'varchar(200)',
            'label' => '店铺宝贝标题',
            'editable' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 13,
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
        'shop_sku_id' => array(
            'type' => 'varchar(50)',
            'required' => false,
            'label' => '店铺货品ID',
            'in_list' => true,
            'default_in_list' => false,
            'width' => 120,
            'order' => 22
        ),
        'mapping' => array (
            'type' => array(
                '0' => '未映射',
                '1' => '已映射',
            ),
            'label' => '关联状态',
            'default' => '0',
            'editable' => false,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 100,
            'order' => 18,
        ),
        'bind' => array(
            'type' => array(
                '0' => '普通类型',
                '1' => '组合类型',
            ),
            'label' => '是否捆绑',
            'default' => '0',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 50
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
        'mapping_status' => array(
            'type' => array(
                'none' => '未关联',
                'fail' => '关联失败',
                'succ' => '关联成功',
                'running' => '关联中',
                'invalid' => '无效的',
            ),
            'default' => 'none',
            'label' => '商货品关联状态',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 70,
            'filtertype' => 'normal',
            'filterdefault' => true,
        ),
        'mapping_time' => array (
            'type' => 'time',
            'editable' => false,
            'label' => '商货品关联时间',
            'in_list' => true,
            'default_in_list' => false,
            'order' => 75,
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
    ),
    'index' => array(
        'uni_shop_sku' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'product_bn',
                2 => 'shop_iid',
                3 => 'shop_sku_id',
            ),
            'prefix' => 'unique',
        ),
        'ind_mapping_status' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'mapping_status',
            ),
        ),
        'ind_shop_sku' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'product_bn',
            ),
        ),
        'ind_shop_iid' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'shop_iid',
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
        'ind_shop_productid' => array(
            'columns' => array(
                0 => 'shop_id',
                1 => 'product_id',
            ),
        ),
    ),
    'engine' => 'innodb',
    'version' => '$Rev: $',
    'comment' => '店铺SKU映射表',
);