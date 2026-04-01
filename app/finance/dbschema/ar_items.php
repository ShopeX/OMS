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

$db['ar_items'] = array(
    'columns' => array(
        'item_id' => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'editable' => false,
        ),
        'ar_id'   => array(
            'type'     => 'table:ar@finance',
            'required' => true,
            'editable' => false,
        ),
        'bn'      => array(
            'type'     => 'varchar(255)',
            'editable' => false,
            'is_title' => true,
            'comment'  => '销售物料编码',
        ),
        'name'    => array(
            'type'     => 'varchar(200)',
            'editable' => false,
            'comment'  => '销售物料名称',
        ),
        'num'     => array(
            'type'     => 'number',
            'default'  => 1,
            'required' => true,
            'editable' => false,
            'comment'  => '数量',
        ),
        'money'   => array(
            'type'     => 'money',
            'default'  => 0,
            'required' => true,
            'editable' => false,
            'comment'  => '金额',
        ),
        'actually_money'       => array(
            'type'            => 'money',
            //'required'        => true,
            'label'           => '客户实付',
            'width'           => 65,
            'default'         => 0,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 13,
        ),
        'platform_amount' => array(
            'type'    => 'money',
            'default' => '0',
            'label'   => '平台承担金额',
            'width'           => 65,
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
            'comment' => '平台承担金额（不包含支付优惠）',
        ),
    ),
    'index' => array (
      'ind_bn' => array ('columns' => array ('bn')),
    ),
    'comment' => '销售应收单明细',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
