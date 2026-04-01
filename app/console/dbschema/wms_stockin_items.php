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

$db['wms_stockin_items'] = array(
    'columns' => array(
        'id'        => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'label'    => 'ID',
            'width'    => 110,
            'hidden'   => true,
            'editable' => false,
        ),
        'wsi_id'        => array(
            'type'            => 'table:wms_stockin@console',
            'label'           => '第三方入库单',
        ),
        'tid'        => array(
            'type'            => 'varchar(255)',
            'default'         => '',
            'label'           => '单号',
        ),
        'oid'        => array(
            'type'            => 'varchar(255)',
            'default'         => '',
            'label'           => '子单号',
        ),
        'product_bn'        => array(
            'type'            => 'varchar(255)',
            'default'         => '',
            'label'           => '货品编码',
        ),
        'normal_num'        => array(
            'type'            => 'number',
            'default'         => 0,
            'label'           => '良品数',
        ),
        'defective_num'        => array(
            'type'            => 'number',
            'default'         => 0,
            'label'           => '不良品数',
        ),
        'sn_list'        => array(
            'type'            => 'text',
            'label'           => '唯一码',
        ),
        'batch'        => array(
            'type'            => 'text',
            'label'           => '批次号',
        ),
        'wms_item_id'        => array(
            'type'            => 'varchar(255)',
            'default'         => '',
            'label'           => '第三方货品编码',
        ),
        'inventoryType'      => array(
            'type'            => 'varchar(25)', // ZP/CC
            'default'         => '',
            'label'           => '库存类型',
        ),
        'at_time'       => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default_in_list' => true,
            'in_list'         => true,
            'filtertype'      => 'time',
            'filterdefault'   => true,
            'default'         => 'CURRENT_TIMESTAMP',
            'order' => 100,
        ),
    ),
    'index'   => array(
        'idx_product_bn'     => array('columns' => array('product_bn')),
        'idx_wms_item_id'     => array('columns' => array('wms_item_id')),
    ),
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
    'comment' => '第三方入库单明细',
);
