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

$db['basic_material_cat']=array (
  'columns' => 
    array (
        'cat_id' => 
        array (
          'type' => 'number',
          'required' => true,
          'pkey' => true,
          'extra' => 'auto_increment',
          'label' => '分类ID',
          'width' => 150,
          'editable' => false,
          'in_list' => true,
          'default_in_list' => true,
        ),
        'parent_id' => 
        array (
          'type' => 'number',
          'label' => '分类ID',
          'width' => 150,
          'editable' => false,
          'in_list' => true,
          'parent_id'=>true,
          'default' => '0',
        ),
        'cat_path' => 
        array (
          'type' => 'varchar(100)',
          'default' => ',',
          'label' => '分类路径(从根至本结点的路径,逗号分隔,首部有逗号)',
          'width' => 150,
          'editable' => false,
          'in_list' => true,
        ),
        'is_leaf' => 
        array (
          'type' => 'bool',
          'required' => true,
          'default' => 'false',
          'label' => '是否叶子结点（true：是；false：否）',
          'width' => 150,
          'editable' => false,
          'in_list' => true,
        ),
        'type_id' => 
        array (
          'type' => 'mediumint',
          'label' => '物料类型',
          'width' => 150,
          'editable' => false,
          'in_list' => true,
        ),
        'cat_name' => 
        array (
          'type' => 'varchar(100)',
          'required' => true,
          'is_title' => true,
          'default' => '',
          'label' => '分类名称',
          'width' => 150,
          'editable' => false,
          'in_list' => true,
        ),
        'cat_code' => 
        array (
          'type' => 'varchar(100)',
          'required' => false,
          // 'is_title' => true,
          'default' => '',
          'label' => '分类编码',
          'width' => 150,
          'editable' => false,
          'in_list' => true,
        ),
        'disabled' => 
        array (
          'type' => 'bool',
          'default' => 'false',
          'required' => true,
          'label' => '是否屏蔽（true：是；false：否）',
          'width' => 150,
          'editable' => false,
          'in_list' => true,
        ),
        'p_order' => 
        array (
          'type' => 'number',
          'label' => '排序',
          'width' => 150,
          'editable' => false,
          'in_list' => true,
        ),
        'goods_count' => 
        array (
          'type' => 'number',
          'label' => '商品数',
          'width' => 150,
          'editable' => false,
          'in_list' => true,
        ),
        'tabs' => 
        array (
          'type' => 'longtext',
          'editable' => false,
        ),
        'finder' => 
        array (
          'type' => 'longtext',
          'label' => '渐进式筛选容器',
          'width' => 150,
          'editable' => false,
          'in_list' => true,
        ),
        'addon' => 
        array (
          'type' => 'longtext',
          'editable' => false,
        ),
        'child_count' => 
        array (
          'type' => 'number',
          'default' => 0,
          'required' => false,
          'editable' => false,
        ),
        'min_price' => 
        array (
          'type' => 'int unsigned',
          'default' => 0,
          'required' => false,
          'editable' => false,
          'label' => '最低售价',
        ),
        'max_price' => 
        array (
          'type' => 'int unsigned',
          'default' => 0,
          'required' => false,
          'editable' => false,
          'label' => '最高售价',
        ),
        'tb_cid' => 
        array (
            'type' => 'varchar(100)',
            'default' => '',
            'label' => '淘宝分类ID',
            'width' => 150,
            'editable' => false,  
            'required' => false,
            'in_list' => true,
        ),
        'tb_cat_path' => 
        array (
            'type' => 'varchar(200)',
            'default' => '',
            'label' => '淘宝分类ID路径',
            'required' => false,
        ),
        'tb_cat_name' => 
        array (
            'type' => 'varchar(200)',
            'default' => '',
            'label' => '淘宝分类名称',
            'width' => 150,
            'editable' => false,
            'required' => false,
            'in_list' => true,
        ),
        'create_time' => array(
            'type' => 'time',
            'label' => '新建时间',
            'width' => 150,
            'in_list' => true,
            'order' => 11,
        ),
        'last_modify' => array(
            'type' => 'last_modify',
            'label' => '最后更新时间',
            'width' => 150,
            'in_list' => true,
            'order' => 11,
        ),
    ),
    'comment' => '基础物料分类',
    'index' => 
    array (
        'ind_cat_path' => 
        array (
            'columns' => 
            array (
                0 => 'cat_path',
            ),
        ),
        'ind_tb_cid' => 
        array (
            'columns' => 
            array (
                0 => 'tb_cid',
            ),
        ),
        'ind_disabled' => 
        array (
            'columns' => 
            array (
                0 => 'disabled',
            ),
        ),
        'ind_cat_name' => 
        array (
            'columns' => 
            array (
                0 => 'cat_name', 1 => 'parent_id',
            ),
            'prefix' => 'unique',
        ),
    ),
    'engine' => 'innodb',
    'version' => '$Rev: 41329 $',
);
