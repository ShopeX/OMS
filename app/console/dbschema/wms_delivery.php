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

$db['wms_delivery'] = array(
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
        'delivery_id' => array(
            'type' => 'table:delivery@ome',
            'label' => 'OMS发货单ID'
        ),
        'delivery_bn'        => array(
            'type'            => 'varchar(255)',
            'label'           => '发货单号',
            'default_in_list' => true,
            'searchtype'      => 'nequal',
            'in_list'         => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order' => 10,
            'width'           => 200,
        ),
        'delivery_status'        => array(
            'type'            => [
                '1' => '未匹配',
                '2' => '已匹配未处理完成',
                '3' => '处理完成',
            ],
            'label'           => '发货单状态',
            'default'         => '1',
            'default_in_list' => true,
            'in_list'         => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order' => 15,
            'width'           => 200,
        ),
        'out_delivery_bn'        => array(
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
        'weight'        => array(
            'type'            => 'varchar(255)',
            'label'           => '重量',
            'default_in_list' => true,
            'in_list'         => true,
            'order'           => 41,
            'width'           => 200,
        ),
        'volume'        => array(
            'type'            => 'varchar(255)',
            'label'           => '体积',
            'default_in_list' => true,
            'in_list'         => true,
            'order'           => 41,
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
        'warehouse'        => array(
            'type'            => 'varchar(255)',
            'label'           => '仓库编码',
            'default_in_list' => true,
            'in_list'         => true,
            'order' => 50,
            'width'           => 200,
        ),
        'oid'        => array(
            'type'            => 'varchar(255)',
            'label'           => '子单单号',
            'default_in_list' => true,
            'in_list'         => true,
            'order' => 60,
            'width'           => 200,
        ),
        'operate_time'        => array(
            'type'            => 'varchar(255)',
            'label'           => '操作时间',
            'default_in_list' => true,
            'in_list'         => true,
            'order' => 70,
            'width'           => 200,
        ),
        'other_list_0'        => array(
            'type'            => 'text',
            'label'           => '其他列表',
            'default_in_list' => true,
            'in_list'         => true,
            'order' => 80,
            'width'           => 200,
        ),
        'packages'        => array(
            'type'            => 'text',
            'label'           => '包裹信息',
            'default_in_list' => true,
            'in_list'         => true,
            'order' => 90,
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
        'idx_delivery_bn_out_delivery_bn_wms_node_id'   => array('columns' => array('delivery_bn','out_delivery_bn','wms_node_id'), 'prefix' => 'UNIQUE'),
        'idx_out_delivery_bn'     => array('columns' => array('out_delivery_bn')),
        'idx_logi_no'     => array('columns' => array('logi_no')),
        'idx_at_time'     => array('columns' => array('at_time')),
        'idx_up_time'     => array('columns' => array('up_time')),
        'idx_delivery_status_time' => array(
            'columns' => array(
                0 => 'delivery_status',
                1 => 'at_time',
                2 => 'wms_status',
            ),
        ),
    ),
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
    'comment' => '第三方发货单',
);
