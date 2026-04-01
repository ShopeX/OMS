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

$db['farm']=array(
    'columns'=>array(
        'farm_id'=>array(
            'type' => 'number',
              'required' => true,
              'pkey' => true,
              'extra' => 'auto_increment',
              'editable' => false,
        ),
        'farm_name'=>array(
            'type'=>'varchar(250)',
            'label'=>'规则名称',
            'editable'=>false,
            'is_title'=>true,
            'in_list'=>true,
            'required' => true,
            'default_in_list'=>true,
            'order' => 10,
        ),
        'shop_ids'=>array(
            'type'=>'text',
            'label' => '商城名称',
            'comment' => '商店的shop_id集',
            'width' => 125,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 12,
        ),
        'user_name'=>array(
            'type'=>'text',
            'label' => '帐号',
            'width' => 200,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 14,
        ),
        'ship_mobile'=>array(
            'type'=>'text',
            'label' => '收货人手机',
            'width' => 200,
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 16,
        ),
        'product_bn_match'=>array(
            'type'=>array(
                '0'=>'全部匹配',
                '1'=>'部分匹配'
            ),
            'label'=>'商品货号匹配',
            'editable'=>false,
            'in_list'=>false,
            'default_in_list'=>false
        ),
        'product_bn'=>array(
            'type'=>'text',
            'label'=>'商品货号',
            'width'=>200,
            'editable'=>false,
            'in_list'=>true,
            'default_in_list'=>true,
            'order' => 20,
        ),
        'condition'=>array(
            'type'=>array(
                // '' => '',
                'eq'=>'等于',
                'gt'=>'大于',
                'lt'=>'小于',
            ),
            'label'=>'条件',
            'editable'=>false,
            'in_list'=>true,
            'default_in_list'=>true,
            'order' => 22,
        ),
        'money'=>array(
            'type'=>'money',
            'label'=>'金额',
            'editable'=>false,
            'in_list'=>true,
            'required' => true,
            'default' => 0,
            'default_in_list'=>true,
            'order' => 24,
        ),
        'custom_mark'=>array(
            'type'=>'text',
            'label'=>'客户备注',
            'width'=>200,
            'editable'=>false,
            'in_list'=>true,
            'default_in_list'=>true,
            'order' => 30,
        ),
        'mark_text'=>array(
            'type'=>'text',
            'label'=>'商家备注',
            'width'=>200,
            'editable'=>false,
            'in_list'=>true,
            'default_in_list'=>true,
            'order' => 32,
        ),
        'mark_type'=>array(
            'type'=>'char(3)',
            'label'=>'淘宝旗标',
            'width'=>200,
            'editable'=>false,
            'default'=> '',
            'in_list'=>true,
            'default_in_list'=>true,
            'order' => 40,
        ),
        'ship_addr'=>array(
            'type'=>'varchar(250)',
            'label'=>'详细地址',
            'width'=>300,
            'in_list'=>true,
            'default_in_list'=>true,
            'order' => 50,
        ),
        'status'=>array(
            'type'=>array(
                '0'=>'关闭',
                '1'=>'开启',
                ),
            'label'=>'状态',
            'editable'=>false,
            'required' => true,
            'default'=>'1',
            'in_list'=>true,
            'default_in_list' => true,
            'order' => 19,
        ),
        'op_id' =>
            array(
                'type' => 'table:account@pam',
                'editable' => false,
                'required' => true,
            ),
        'op_name' =>
            array(
                'type' => 'varchar(30)',
                'editable' => false,
            ),
        'createtime' => array (
            'type' => 'time',
            'label' => '创建时间',
            'width' => 130,
            'editable' => false,
            'filtertype' => 'time',
            //'filterdefault' => true,
            'in_list' => true,
            'order' => 98,
        ),
        'uptime' =>array (
            'type' => 'time',
            'label' => '最后更新时间',
            'width' => 130,
            'editable' => false,
            //'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'order' => 99,
        ),
    ),
    'comment' => '刷单条件表',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);
?>