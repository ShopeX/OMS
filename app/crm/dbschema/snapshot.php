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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

// 数据快照
$db['snapshot']=array (
    'columns' => 
    array (
        'id' =>
        array(
            'type' => 'bigint unsigned',
            'required' => true,
            'pkey' => true,
            'editable' => false,
            'extra' => 'auto_increment',
        ),
        'type' => 
        array(
            'type' => array(
                1 => '赠品规则应用',
                2 => '分组规则',
                3 => '赠品规则',
                9 => '其它',
            ),
            'label'=>'类型',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'task_id' => 
        array(
            'type' => 'int unsigned',
            'label'=>'业务ID',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'title' => 
        array(
            'type' => 'varchar(100)',
            'label'=>'标题',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'content' => 
        array(
            'type' => 'longtext',
            'label'=>'内容',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'op_user' => 
        array(
            'type' => 'varchar(50)',
            'label'=>'操作人',
            'in_list' => true,
            'default_in_list' => true,
        ),
        'create_time' => 
        array(
            'type' => 'datetime',
            'label'=>'操作时间',
            'in_list' => true,
            'default_in_list' => true,
        ),
    ),
    'index' =>
    array (
        'ind_task_id' =>
        array (
            'columns' =>
            array (
                'task_id',
            ),
        ),
    ),
    'engine' => 'innodb',
    'version' => '$Rev:  $',
);