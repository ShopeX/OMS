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
 * 失败订单进入快照（SCRUM-7468）
 * 按 entered_at 做定时批量邮件水位统计，禁止扫当前 is_fail。
 */
$db['order_fail_snapshot'] = array(
    'columns' => array(
        'id' => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'label'    => 'ID',
            'editable' => false,
        ),
        'order_id' => array(
            'type'     => 'int unsigned',
            'required' => true,
            'default'  => 0,
            'label'    => '订单ID',
            'editable' => false,
        ),
        'order_bn' => array(
            'type'     => 'varchar(32)',
            'required' => true,
            'default'  => '',
            'label'    => '订单号',
            'editable' => false,
        ),
        'shop_id' => array(
            'type'     => 'varchar(32)',
            'default'  => '',
            'label'    => '店铺ID',
            'editable' => false,
        ),
        'shop_name' => array(
            'type'     => 'varchar(255)',
            'default'  => '',
            'label'    => '来源店铺',
            'editable' => false,
        ),
        'org_id' => array(
            'type'     => 'varchar(32)',
            'default'  => '0',
            'label'    => '运营组织',
            'editable' => false,
        ),
        'total_amount' => array(
            'type'     => 'money',
            'default'  => '0',
            'label'    => '订单总额',
            'editable' => false,
        ),
        'pay_status' => array(
            'type'     => 'varchar(8)',
            'default'  => '0',
            'label'    => '付款状态',
            'editable' => false,
        ),
        'order_createtime' => array(
            'type'     => 'time',
            'default'  => 0,
            'label'    => '下单时间',
            'editable' => false,
        ),
        'paytime' => array(
            'type'     => 'time',
            'default'  => 0,
            'label'    => '付款时间',
            'editable' => false,
        ),
        'fail_reason' => array(
            'type'     => 'text',
            'label'    => '失败原因',
            'editable' => false,
        ),
        'entered_at' => array(
            'type'     => 'time',
            'required' => true,
            'default'  => 0,
            'label'    => '进入失败时间',
            'editable' => false,
        ),
        'at_time' => array(
            'type'     => 'TIMESTAMP',
            'label'    => '创建时间',
            'default'  => 'CURRENT_TIMESTAMP',
            'editable' => false,
        ),
    ),
    'index' => array(
        'uni_order_id' => array(
            'columns' => array('order_id'),
            'prefix'  => 'UNIQUE',
        ),
        'ind_entered_org' => array(
            'columns' => array('entered_at', 'org_id'),
        ),
        'ind_order_bn' => array(
            'columns' => array('order_bn'),
        ),
        'ind_at_time' => array(
            'columns' => array('at_time'),
        ),
    ),
    'comment' => '失败订单进入快照',
    'engine'  => 'innodb',
    'version' => '$Rev$',
);
