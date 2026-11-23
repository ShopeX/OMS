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

$db['wms_reship'] = array(
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
        'reship_id' => array(
            'type' => 'table:reship@ome',
            'label' => 'OMS退货单ID'
        ),
        'reship_bn'        => array(
            'type'            => 'varchar(255)',
            'label'           => '退货单号',
            'default_in_list' => true,
            'searchtype'      => 'nequal',
            'in_list'         => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order' => 10,
            'width'           => 200,
        ),
        'reship_status'        => array(
            'type'            => [
                '1' => '未匹配',
                '2' => '已匹配未处理完成',
                '3' => '处理完成',
            ],
            'label'           => '退货单状态',
            'default'         => '1',
            'default_in_list' => true,
            'in_list'         => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order' => 15,
            'width'           => 200,
        ),
        'return_order_id'        => array(
            'type'            => 'varchar(255)',
            'label'           => '仓库系统订单编码',
            'default_in_list' => true,
            'searchtype'      => 'nequal',
            'in_list'         => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order' => 20,
            'width'           => 200,
        ),
        'wms_status'        => array(
            'type'            => 'varchar(255)',
            'label'           => '第三方状态',
            'default'         => '',
            'width'           => 200,
        ),
        'wms_node_id'        => array(
            'type'            => 'varchar(255)',
            'label'           => '第三方仓储绑定ID',
            'default'         => '',
            'in_list' => true,
            'default_in_list' => true,
            'order' => 30,
            'width'           => 200,
        ),
        'logistics'        => array(
            'type'            => 'varchar(255)',
            'label'           => '物流编码',
            'default'         => '',
            'default_in_list' => true,
            'order' => 35,
            'width'           => 200,
        ),
        'logi_no'        => array(
            'type'            => 'varchar(255)',
            'label'           => '物流单号',
            'default_in_list' => true,
            'searchtype'      => 'nequal',
            'in_list'         => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order' => 40,
            'width'           => 200,
        ),
        'remark'        => array(
            'type'            => 'text',
            'label'           => '备注',
            'default'         => '',
            'default_in_list' => true,
            'order' => 45,
            'width'           => 200,
        ),
        'extend_props'        => array(
            'type'            => 'text',
            'label'           => '扩展属性',
            'order' => 35,
            'width'           => 200,
        ),
        'order_type'        => array(
            'type'            => 'varchar(25)',
            'default'         => 'THRK',
            'label'           => '单据类别',
            'comment'         => 'THRK=退货入库;HHRK=换货入库;WTJRK=无头件入库',
            'default_in_list' => true,
            'in_list'         => true,
            'order' => 50,
            'width'           => 200,
        ),
        'warehouse'        => array(
            'type'            => 'varchar(255)',
            'label'           => '仓库编码',
            'default_in_list' => true,
            'in_list'         => true,
            'order' => 50,
            'width'           => 200,
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
        'up_time'       => array(
            'type'            => 'TIMESTAMP',
            'label'           => '更新时间',
            'default_in_list' => true,
            'in_list'         => true,
            'filtertype'      => 'time',
            'filterdefault'   => true,
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'order' => 110,
        ),
    ),
    'index'   => array(
        'idx_reship_bn_return_order_id_wms_node_id'   => array('columns' => array('reship_bn','return_order_id','wms_node_id'), 'prefix' => 'UNIQUE'),
        'idx_return_order_id'     => array('columns' => array('return_order_id')),
        'idx_logi_no'     => array('columns' => array('logi_no')),
        'idx_at_time'     => array('columns' => array('at_time')),
        'idx_up_time'     => array('columns' => array('up_time')),
        'idx_reship_status_time' => array(
            'columns' => array(
                0 => 'reship_status',
                1 => 'at_time',
                2 => 'wms_status',
            ),
        ),
    ),
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
    'comment' => '第三方退货单',
);
