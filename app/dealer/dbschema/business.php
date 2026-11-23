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


$db['business'] = array(
    'columns' => array(
        'bs_id'           => array(
            'type'     => 'mediumint(8)',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'label'    => '经销商ID',
        ),
        'bs_bn'           => array(
            'type'            => 'varchar(32)',
            'required'        => true,
            'label'           => '经销商编号',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'nequal',
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'name'            => array(
            'type'            => 'varchar(255)',
            'required'        => true,
            'label'           => '经销商名称',
            'editable'        => false,
            'is_title'        => true,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'deal_cost'       => array(
            'type'     => 'varchar(1000)',
            'required' => true,
            'label'    => '订单处理费公式',
            'editable' => false,
        ),
        'create_time'     => array(
            'type'            => 'time',
            'label'           => '创建时间',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        'modify_time'     => array(
            'type'            => 'time',
            'label'           => '修改时间',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => true,
        ),
        // 一件代发扩展字段
        'op_name'         => array(
            'type'            => 'varchar(32)',
            'label'           => '创建者',
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'betc_id'         => array(
            'type'            => 'varchar(255)',
            'label'           => '贸易公司ID集合，示例：1,2,3',
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'cos_id'          => array(
            'type'            => 'varchar(255)',
            'label'           => '组织架构ID集合',
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'contact_address' => array(
            'type'            => 'varchar(255)',
            'label'           => '联系人地址',
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'contact_mobile'  => array(
            'type'            => 'varchar(30)',
            'label'           => '联系人手机',
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'contact_name'    => array(
            'type'            => 'varchar(30)',
            'label'           => '联系人姓名',
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'customer_code'   => array(
            'type'            => 'varchar(32)',
            'label'           => '经销商客户编码',
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'salesoffice_code' => array(
            'type'            => 'varchar(32)',
            'label'           => '销售办公室编码',
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'division_code'   => array(
            'type'            => 'varchar(32)',
            'label'           => '产品组编码',
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'salesgroup_code' => array(
            'type'            => 'varchar(32)',
            'label'           => '销售组织',
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'status'          => array(
            'type'            => "enum('active', 'close')",
            'label'           => '状态',
            'default'         => 'active',
            'in_list'         => false,
            'default_in_list' => false,
            'editable'        => false,
            'order'           => 30,
        ),
        'at_time'         => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'default'         => 'CURRENT_TIMESTAMP',
            'width'           => 150,
            'in_list'         => false,
            'default_in_list' => false,
            'order'           => 40,
        ),
        'up_time'         => array(
            'type'            => 'TIMESTAMP',
            'label'           => '更新时间',
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'width'           => 150,
            'in_list'         => false,
            'default_in_list' => false,
            'order'           => 50,
        ),
    ),
    'index'   => array(
        'ind_bs_bn' => array(
            'columns' => array(
                0 => 'bs_bn',
            ),
            'prefix'  => 'unique',
        ),
    ),
    'comment' => '经销商',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
