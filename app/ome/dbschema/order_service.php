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

$db['order_service']=array (
  'columns' => 
  array (
    'id' => 
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'editable' => false,
    ),
    'order_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'editable' => false,
        'label' => '订单号',
    ),

    'item_oid' =>
      array (
          'type' => 'varchar(50)',
          'default' => 0,
          'editable' => false,
          'label' => '服务所属的交易订单号',
      ),

      'tmser_spu_code' =>
        array (
            'type' => 'varchar(50)',
            'default' => 0,
            'editable' => false,
            'label' => '支持家装类物流的类型',
        ),
      'sale_price' =>
          array (
              'type' => 'money',
              'editable' => false,
              'label'=>'销售价',
          ),
    'num' =>
        array (
          'type' => 'longtext',
          'edtiable' => false,
            'label'=>'购买数量',
        ),
    'total_fee' =>
        array (
            'type' => 'money',
          'editable' => false,
            'label'=>'服务子订单总费用',
        ),
      'type' =>
          array (
              'type' => 'varchar(15)',
              'editable' => false,
              'default' => 'service',
              'label'=>'服务',
          ),
    'type_alias' =>
      array (
          'type' => 'varchar(50)',
          'default' => 0,
          'editable' => false,
          'label'=>'服务别名',
      ),
    'title' =>
      array (
          'type' => 'varchar(50)',
          'default' => 0,
          'editable' => false,
            'label'=>'商品名称',
      ),
    'service_id' =>
      array (
          'type' => 'varchar(50)',
          'default' => 0,
          'editable' => false,
          'label' => '服务数字id',
      ),
      'refund_id' =>
          array (
              'type' => 'varchar(50)',
              'default' => 0,
              'editable' => false,
              'label' => '最近退款的id',
          ),
  ),
'index' =>
    array (
        'ind_order_id' =>
            array (
                'columns' =>
                    array (
                        0 => 'order_id',
                    ),
            ),

    ),
  'comment' => '服务订单表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);