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
 * @Author: xueding@shopex.cn
 * @Datetime: 2022/4/18
 * @Describe: 外部erp商品映射关系
 */
$db['foreign_sku'] = array(
    'columns' => array(
        'id'               => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'editable' => false,
            'extra'    => 'auto_increment',
        ),
        'dchain_id'        => array(
            'type'     => 'varchar(32)',
            'required' => true,
            'label'    => '来源优仓',
            'editable' => false,
        ),
        'inner_sku'        => array(
            'type'            => 'varchar(50)',
            'required'        => true,
            'label'           => '货品编码',
            'comment'         => '内部sku',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'order'           => 25,

        ),
        'inner_product_id' => array(
            'type'     => 'int unsigned',
            'label'    => '货品ID',
            'width'    => 110,
            'editable' => false,
            'default'  => 0,
        ),
        'inner_type'       => array(
            'type'     => array(
                '0' => '普通商品',
                '1' => '捆绑商品'
            ),
            'default'  => '0',
            'label'    => '商品类型',
            'width'    => 110,
            'editable' => false,
            'in_list'  => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'outer_sku'        => array(
            'type'            => 'varchar(50)',
            'label'           => '外部商家编码',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'order'           => 40,
        ),
        'outer_sku_id'     => array(
            'type'            => 'varchar(50)',
            'label'           => '外部货品ID',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'order'           => 30,
        ),
        'outer_bar_code'   => array(
            'type'            => 'varchar(50)',
            'label'           => '外部商家条码',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'has',
            'order'           => 50,
        ),
        'goods_status'     => array(
            'type'            => 'bool',
            'default'         => 'false',
            'label'           => '商品状态',
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'sync_status'      => array(
            'type'            => array(
                0 => '未同步',
                1 => '同步失败',
                2 => '同步中',
                3 => '同步成功',
                4 => '同步后编辑',
            ),
            'default'         => '0',
            'label'           => '商品同步状态',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 60,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'mapping_status'   => array(
            'type'            => array(
                0 => '未关联',
                1 => '关联失败',
                2 => '关联成功',
            ),
            'default'         => '0',
            'label'           => '商货品关联状态',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 70,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'mapping_addon'    => array(
            'type'    => 'varchar(255)',
            'default' => '',
            'label'   => '商货品关联返回内容',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 90,
            'width'           => 250,
        ),
        'addon'            => array(
            'type'    => 'varchar(255)',
            'default' => '',
            'label'   => '商品同步结果',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 80,
            'width'           => 250,
        ),
        'outer_createtime' => array(
            'type'       => 'TIMESTAMP',
            'label'      => '货品创建时间',
            'default'    => 'CURRENT_TIMESTAMP',
            'in_list'    => true,
            'default_in_list' => true,
            'filtertype' => 'time',
            'order'           => 100,
        ),
        'outer_lastmodify' => array(
            'type'       => 'TIMESTAMP',
            'label'      => '货品更新时间',
            'default'    => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'in_list'    => true,
            'default_in_list' => true,
            'filtertype' => 'time',
            'order'           => 110,
        ),
        'shop_sku_id'      => array(
            'type'            => 'varchar(50)',
            'required'        => false,
            'label'           => '店铺货品ID',
            'order'           => 20,
            'in_list'         => true,
            'default_in_list' => true,
            'filterdefault'   => true,
            'filtertype'      => 'normal',
        ),
        'shop_product_id'  => array(
            'type'            => 'varchar(50)',
            'required'        => false,
            'label'           => '店铺商品ID',
            'order'           => 10,
            'in_list'         => true,
            'default_in_list' => true,
            'filterdefault'   => true,
            'filtertype'      => 'normal',
        ),
    ),
    'index'   => array(
        'ind_inner_sku'  => array('columns' => array('inner_sku')),
        'ind_dchain_id'        => array('columns' => array('dchain_id',)),
        'ind_sync_status'      => array('columns' => array('sync_status',)),
    ),
    'engine'  => 'innodb',
    'version' => '$Rev: $',
    'comment' => '外部erp商品映射关系',
);
