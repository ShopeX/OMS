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

$db['sales_material_fukubukuro'] = array(
    'columns' => array(
        'fd_id' => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'label'    => '主键ID',
            'hidden'   => true,
            'editable' => false,
        ),
        'sm_id' => array(
            'type'     => 'int unsigned',
            'required' => true,
            'width' => 150,
            'hidden'   => true,
            'editable' => false,
            'comment'  => '销售物料ID,'
        ),
        'combine_id' => array(
            'type'     => 'int unsigned',
            'required' => true,
            'width' => 150,
            'hidden'   => true,
            'editable' => false,
            'comment'  => '福袋组合ID'
        ),
        'number' => array(
            'type'     => 'int unsigned',
            'editable' => false,
            'label'    => '数量',
            'default'  => 1,
            'hidden'   => true,
            'comment'  => '福袋组合数量'
        ),
        'rate_price' => array (
            'type' => 'money',
            'default' => '0.000',
            'label' => '组合贡献价',
            'comment' => '组合贡献价',
            'in_list' => true,
            'default_in_list' => true,
            'width' => 150,
            'order' => 80,
        ),
        'rate' => array(
            'type'     => 'decimal(5,2)',
            'editable' => false,
            'label'    => '销售价贡献占比',
            'default'  => 100,
            'hidden'   => true,
        ),
    ),
    'index' => array(
        'ind_combine_bm_id' => array(
            'columns' => array(
                0 => 'sm_id',
                1 => 'combine_id',
            ),
            'prefix' => 'unique',
        ),
        'ind_combine_id' => array(
            'columns' => array(
                0 => 'combine_id',
            ),
        ),
    ),
    'comment' => '销售物料与福袋组合关联表, 用于存储两者关系, 支持一对多',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
