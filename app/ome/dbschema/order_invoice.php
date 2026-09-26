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

$db['order_invoice']=array (
    'columns' =>
        array (
            'id' => array(
                'type'=>'int unsigned',
                'require' => true,
                'pkey' => true,
                'editable' => false,
                'extra' => 'auto_increment',
            ),
            'order_id' =>
                array (
                    'type' => 'int unsigned',
                    'required' => true,
                    'editable' => false,
                ),
            'tax_title' =>
                array (
                    'type' => 'varchar(255)',
                    'commit' => '发票抬头',
                    'editable' => false,
                ),
            'tax_no' =>
                array (
                    'type' => 'varchar(255)',
                    'commit' => '发票号',
                    'editable' => false,
                ),
            'register_no' =>
                array (
                    'type' => 'varchar(255)',
                    'commit' => '税务号',
                    'editable' => false
                ),
            'invoice_kind' =>
                array(
                    'type' => array(
                        0=>'纸质发票',
                        1=>'电子普票',
                        2=>'电子专票',
                    ),
                    'default' => '0',
                ),
            'title_type' =>
                array (
                    'type' => array(
                        0=>'个人',
                        1=>'企业',
                    ),
                    'default' => '0',
                ),
            'create_time' =>
                array (
                    'type' => 'time',
                    'editable' => 'false',
                    'commit' => '创建时间'
                )
        ),
    'index' =>
        array (
            'ind_order_id' =>
                array (
                    'columns' =>
                        array (
                            0 => 'order_id',
                        ),
                    'prefix' => 'unique',
                ),
            'ind_create_time' =>
                array (
                    'columns' =>
                        array(
                            0 => 'create_time'
                        )
                )
        ),
    'comment' => '订单发票信息表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);