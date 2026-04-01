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
 * 销售物料基础物料关联数据结构
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */

$db['sales_basic_material'] = array(
    'columns' => array(
        'sm_id'  => array(
            'type'     => 'int unsigned',
            'required' => true,
            'width'    => 110,
            'hidden'   => true,
            'editable' => false,
            'comment' => '销售物料ID,关联material_sales_material.sm_id'
        ),
        'bm_id'  => array(
            'type'     => 'int unsigned',
            'required' => true,
            'width'    => 110,
            'hidden'   => true,
            'editable' => false,
            'comment' => '基础物料ID,关联material_basic_material.bm_id'
        ),
        'number' => array(
            'type'     => 'number',
            'editable' => false,
            'label'    => '数量',
            'default'  => 1,
            'hidden'   => true,
            'comment'  => '基础物料数量'
        ),
        'rate'   => array(
            'type'     => 'decimal(5,2)',
            'editable' => false,
            'label'    => '促销类基础物料价格贡献占比',
            'default'  => 100,
            'hidden'   => true,
        ),
        'fixed_price' => array(
            'type' => 'money',
            'editable' => false,
            'label'    => '固定价',
            'hidden'   => true,
        ),
    ),
    'index'   => array(
        'ind_sm_bm' => array('columns' => array('sm_id', 'bm_id')),
        'ind_bm'    => array('columns' => array('bm_id')),
    ),
    'comment' => '销售物料与基础物料关联表, 用于存储两者关系, 支持一对多',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
