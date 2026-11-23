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


$db['goods'] = array(
    'columns' => array(
        'id' => array(
            'type' => 'mediumint(8)',
            'required' => true,
            'pkey' => true,
            'extra' => 'auto_increment',
            'label' => '经销商货品ID',
        ),
        'bs_id' => array(
            'type' => 'mediumint(8)',
            'required' => true,
            'label' => '经销商ID',
        ),
        'bm_id' => array(
            'type' => 'int unsigned',
            'required' => true,
            'label' => '货品ID',
        ),
        'cost' => array (
            'type' => 'money',
            'required' => true,
            'label' => '成本价',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
        ),
        'create_time' => array (
            'type' => 'time',
            'label' => '创建时间',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
        ),
        'modify_time' => array (
            'type' => 'time',
            'label' => '修改时间',
            'editable' => false,
            'in_list' => true,
            'default_in_list' => true,
        ),
    ),
    'index' => array (
        'ind_bs_bm_id' => array (
            'columns' => array (
                'bs_id',
                'bm_id',
            ),
            'prefix' => 'unique'
        ),
        'ind_bm_id' => array (
            'columns' => array (
                'bm_id',
            ),
        ),
    ),
    'comment' => '经销商货品',
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);