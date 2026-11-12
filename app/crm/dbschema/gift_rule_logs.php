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
 * 赠品规则记录
 * 
 * @author wangbiao@shopex.cn
 * @version v0.1
 */
$db['gift_rule_logs'] = array (
    'columns' => array (
        'sid' => array(
            'type' => 'int unsigned',
            'required' => true,
            'pkey' => true,
            'editable' => false,
            'extra' => 'auto_increment',
            'order' => 1,
        ),
        'rule_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'editable' => false,
            'label' => '规则ID',
            'default_in_list' => false,
            'in_list' => false,
            'order' => 10,
        ),
        'rule_bn' => array (
            'type' => 'varchar(32)',
            'required' => true,
            'editable' => false,
            'label' => '规则编号',
            'searchtype' => 'has',
            'filtertype' => 'normal',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 130,
            'order' => 11,
        ),
        'gift_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'editable' => false,
            'label' => '赠品ID',
            'default_in_list' => false,
            'in_list' => false,
            'order' => 20,
        ),
        'product_id' => array (
            'type' => 'int unsigned',
            'comment' => '赠品货品ID',
            'required' => true,
            'editable' => false,
            'default_in_list' => false,
            'in_list' => false,
            'order' => 21,
        ),
        'gift_bn' => array (
            'type' => 'varchar(80)',
            'required' => true,
            'label' => '赠品货号',
            'searchtype' => 'head',
            'editable' => false,
            'filtertype' => 'normal',
            'filterdefault' => true,
            'default_in_list' => true,
            'in_list' => true,
            'width' => 130,
            'order' => 22,
        ),
        'is_warning' => array(
            'type' => 'bool',
            'default' => 'false',
            'in_list' => true,
            'default_in_list' => true,
            'required' => false,
            'label' => '是否预警',
            'filtertype' => false,
            'searchtype' => false,
            'width' => 90,
            'order' => 30,
        ),
        'warning_num' => array(
            'type' => 'number',
            'in_list' => true,
            'default' => 0,
            'default_in_list' => true,
            'required' => false,
            'label' => '预警数量',
            'filtertype' => false,
            'searchtype' => false,
            'width' => 90,
            'order' => 31,
        ),
        'warning_mobile' => array(
            'type' => 'varchar(80)',
            'in_list' => true,
            'default' => '',
            'default_in_list' => true,
            'required' => false,
            'label' => '预警手机号',
            'width' => 120,
            'order' => 42,
        ),
        'send_num' => array(
            'type' => 'number',
            'in_list' => true,
            'default' => 0,
            'default_in_list' => true,
            'required' => false,
            'label' => '已赠送数量',
            'filtertype' => false,
            'searchtype' => false,
            'width' => 110,
            'order' => 40,
        ),
        'send_time' => array(
            'type' => 'time',
            'required' => false,
            'label' => '最后赠送日期',
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 130,
            'order' => 50,
        ),
        'create_time' => array(
            'type' => 'time',
            'required' => false,
            'label' => '创建时间',
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 130,
            'order' => 98,
        ),
        'update_time' => array(
            'type' => 'time',
            'required' => false,
            'label' => '最后修改日期',
            'filtertype' => 'time',
            'filterdefault' => true,
            'in_list' => true,
            'default_in_list' => true,
            'width' => 130,
            'order' => 99,
        ),
    ),
    'index' => array(
        'ind_rule_gift' => array(
            'columns' => array('rule_id', 'gift_id'),
            'prefix' => 'unique',
        ),
        
        'ind_gift_product' => array(
            'columns' => array('gift_id', 'product_id'),
        ),
    ),
    'engine' => 'innodb',
    'version' => '$Rev:  $'
);