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

$db['order_front_items'] = array(
    'columns' => array(
        'id'   => array(
            'type'     => 'int unsigned',
            'extra'    => 'auto_increment',
            'pkey'     => true,
            'editable' => false,
            'label'    => '自增ID',
        ),
        'of_id'  => array(
            'type'          => 'table:order_front',
            'label'         => '主表ID',
        ),
        'source_item_id'          => array(
            'type'            => 'int unsigned',
            'default'         => 0,
            'required'        => true,
            'label'           => '来源明细id',
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'bm_id'                => array(
            'type'     => 'int unsigned',
            'editable' => false,
            'label'           => '物料主键',
        ),
        'bn'                => array(
            'type'     => 'varchar(40)',
            'editable' => false,
            'label'           => '物料编码',
            'is_title' => true,
        ),
        'item_type'         => array(
            'type'     => [
                'basic' => '基础物料',
                'sales' => '销售物料',
                'ship'  => '运费',
            ],
            'label'           => '行类型',
            'editable' => false,
        ),
        'name'              => array(
            'type'     => 'varchar(200)',
            'label'           => '物料名称',
            'editable' => false,
        ),
        'divide'             => array(
            'type'     => 'money',
            'default'  => '0',
            'label'           => '实付小计',
            'required' => true,
            'editable' => false,
        ),
        'amount'            => array(
            'type'     => 'money',
            'default'  => '0',
            'label'           => '开票金额小计',
            'required' => true,
            'editable' => false,
        ),
        'quantity'          => array(
            'type'     => 'number',
            'default'  => 1,
            'label'           => '购买数量',
            'required' => true,
            'editable' => false,
        ),
        'reship_num'          => array(
            'type'     => 'number',
            'default'  => 0,
            'label'           => '退货数量',
            'required' => true,
            'editable' => false,
        ),
        'is_delete'            => array(
            'type'     => 'bool',
            'default'  => 'false',
            'label'           => '是否删除',
            'editable' => false,
        ),
        'at_time'       => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default'         => 'CURRENT_TIMESTAMP',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 1000,
        ),
        'up_time'       => array(
            'type'            => 'TIMESTAMP',
            'label'           => '更新时间',
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 1010,
        ),
    ),
    'index'   => array(
        'idx_at_time'       => array('columns' => array('at_time')),
        'idx_up_time'       => array('columns' => array('up_time')),
        'source_item_id'    => array('columns' => array('source_item_id')),
    ),
    'engine'  => 'innodb',
    'commit'  => '预发票表明细表',
    'version' => 'Rev: 41996 $',
);