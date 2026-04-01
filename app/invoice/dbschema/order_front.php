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

$db['order_front'] = array(
    'columns' => array(
        'id'                => array(
            'type'            => 'int unsigned',
            'pkey'            => true,
            'extra'           => 'auto_increment',
            'required'        => true,
            'label'           => '编号',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => false,
            'width'           => 60,
            'hidden'          => true,
            'order'           => 10,
        ),
        'source_id'          => array(
            'type'            => 'int unsigned',
            'default'         => 0,
            'required'        => true,
            'label'           => '来源id',
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'source_bn'          => array(
            'type'            => 'varchar(255)',
            'required'        => true,
            'label'           => '业务单号',
            'searchtype'      => 'nequal',
            'filterdefault'   => true,
            'filtertype'      => 'textarea',
            'in_list'         => true,
            'default_in_list' => true,
            'width'           => 240,
            'order'           => 20,
        ),
        'source'           => array(
            'type'            => array(
                'b2c' => '电商订单',
                'b2b' => 'B2B订单',
            ),
            'default'         => 'b2c',
            'label'           => '来源',
            'in_list'         => true,
            'default_in_list' => false,
            'width'           => 30,
            'order'           => 30,
        ),
        'mode'              => array(
            'type'            => array(
                '1' => '电子发票',
                '2' => '纸质发票',
                '3' => '专用发票',
            ),
            'default'         => '1',
            'required'        => true,
            'label'           => '开票方式',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order'           => 40,
            'width'           => 70,
        ),
        'amount'            => array(
            'type'            => 'money',
            'default'         => '0',
            'required'        => true,
            'label'           => '开票金额',
            'filtertype'      => 'number',
            'filterdefault'   => true,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 50,
            'width'           => 80,
        ),
        'status'         => array(
            'type'            => array(
                'active' => '活动',
                'acitve' => '活动',
                'finish' => '已签收',
                'close' => '已作废',
            ),
            'default'         => 'active',
            'required'        => true,
            'label'           => '业务状态',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
            'order'           => 60,
            'width'           => 70,
        ),
        'op_edit'           =>  array(
            'type'  => [
                '0' => '未人工编辑',
                '1' => '已人工编辑'
            ],
            'default' => '0',
            'label' => '人工编辑',
            'order'           => 70,
        ),     
        'title'             => array(
            'type'            => 'varchar(150)',
            'label'           => '发票抬头',
            'in_list'         => true,
            'default_in_list' => true,
            'searchtype'      => 'head',
            'filtertype'      => 'normal',
            'filterdefault'   => false,
            'order'           => 80,
            'width'           => 200,
        ),
        'ship_area'         => array(
            'type'  => 'varchar(100)',
            'label' => '收票地区',
        ),
        'ship_addr'         => array(
            'type'            => 'text',
            'label'           => '收票地址',
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'ship_tel'          => array(
            'type'            => 'varchar(200)',
            'label'           => '收票电话',
            'in_list'         => true,
            'default_in_list' => false,
        ),

        'ship_email' => array(
            'type'     => 'varchar(150)',
            'label'    => '收票人Email',
            'comment'  => '收票人Email',
            'editable' => false,
            'in_list'  => true,
        ),
        'ship_name'          => array(
            'type'            => 'varchar(200)',
            'label'           => '收票联系人',
            'editable'        => false,
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'ship_tax'          => array(
            'type'            => 'varchar(255)',
            'label'           => '客户税号',
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'ship_bank'         => array(
            'type'            => 'varchar(255)',
            'label'           => '客户开户银行',
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'ship_bank_no'      => array(
            'type'            => 'varchar(255)',
            'label'           => '客户银行账号',
            'in_list'         => true,
            'default_in_list' => false,
        ),
        'ship_company_addr' => array(
            'type'    => 'varchar(255)',
            'label'   => '注册地址',
            'in_list' => true,
        ),
        'ship_company_tel' => array(
            'type'    => 'varchar(255)',
            'label'   => '注册电话',
            'in_list' => true,
        ),
        'shop_id'           => array(
            'type'            => 'table:shop@ome',
            'default'         => 0,
            'required'        => true,
            'editable'        => false,
            'label'           => '来源店铺',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'      => 'normal',
            'filterdefault'   => true,
        ),
        'shop_type'         => array(
            'type'     => 'varchar(50)',
            'label'    => '店铺类型',
            'width'    => 75,
            'editable' => false,
        ),
        'is_status'         => array(
            'type'            => array(
                0 => '需开票',
                1 => '已开票',
                2 => '需作废',
            ),
            'default'         => '0',
            'required'        => true,
            'label'           => '开票要求',
            'width'           => 70,
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 90,
        ),
        'is_write'         => array(
            'type'            => array(
                '0' => '未写入',
                '1' => '已写入',
            ),
            'default'         => '0',
            'label'           => '写入开票信息',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 100,
        ),
        'write_msg'         => array(
            'type'            => 'varchar(255)',
            'default'         => '',
            'label'           => '写入返回',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 110,
        ),
        'disabled' =>
        array (
          'type'    => 'bool',
          'default' => 'false',
          'label'   => '列表是否显示',  
          'editable'=> false,
        ),
        'cost_freight' =>
        array (
          'type'    => 'money',
          'default' => '0',
          'label'   => '运费',  
          'editable'=> false,
        ),
        'org_id' => array (
            'type' => 'table:operation_organization@ome',
            'label' => '运营组织',
            'editable' => false,
            'width' => 60,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
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
    'index' => array(
        'source_id_source'  => array('columns' => array('source_id','source'), 'prefix'=>'unique'),
        'source_bn'         => array('columns' => array('source_bn',),),
        'status'            => array('columns' => array('status',),),
        'disabled'          => array('columns' => array('disabled',),),
        'idx_at_time'       => array('columns' => array('at_time')),
        'idx_up_time'       => array('columns' => array('up_time')),
    ),
    'comment' => '预发票表',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
