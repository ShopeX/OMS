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
 * 基础物料预占流水表
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

$db['basic_material_stock_freeze']=array (
  'columns' =>
  array (
    'bmsf_id' => array(
        'type'     => 'int unsigned',
        'required' => true,
        'pkey'     => true,
        'extra'    => 'auto_increment',
        'editable' => false,
    ),
    'bmsq_id' =>
    array (
        'type' => 'int',
        'comment' => '配额ID',
        'label' => '配额ID',
        'editable' => false,
        'in_list' => false,
        'default_in_list' => false,
    ),
    'bm_id' =>
    array (
        'type' => 'int unsigned',
        'comment' => '基础物料ID',
        'label' => '基础物料ID',
        'editable' => false,
        'in_list' => false,
        'default_in_list' => false,
    ),
    'sm_id' =>
    array (
        'type' => 'int unsigned',
        'comment' => '销售物料ID',
        'label' => '销售物料ID',
        'editable' => false,
        'in_list' => false,
        'default_in_list' => false,
    ),
    'obj_id' => array(
        'type' => 'int unsigned',
        'comment' => '对象ID',
        'label' => '对象ID',
        'default' => 0,
        'editable' => false,
        'in_list' => false,
        'default_in_list' => false,
    ),
    'obj_bn' => array(
        'type' => 'varchar(255)',
        'label' => '对象编号',
        'default' => '',
        'editable' => false,
        'in_list' => false,
        'default_in_list' => false,
    ),
    'obj_type' => array(
        'type' => 'tinyint(1)',
        'comment' => '对象类型',
        'label' => '对象类型',
        'editable' => false,
        'in_list' => false,
        'default_in_list' => false,
    ),
    'bill_type' => array(
        'type' => 'tinyint(1)',
        'comment' => '业务类型',
        'editable' => false,
        'default' => 0,
    ),
    'sub_bill_type' => array(
        'type' => 'varchar(255)',
        'label' => '业务子类型',
        'editable' => false,
        'default' => '',
        'in_list' => true,
        'default_in_list' => true,
    ),
    'shop_id' => array(
        'type' => 'varchar(32)',
        'comment' => '店铺ID',
        'label' => '店铺ID',
        'editable' => false,
        'in_list' => false,
        'default_in_list' => false,
    ),
    'branch_id' => array(
        'type' => 'number',
        'comment' => '仓库ID',
        'label' => '仓库ID',
        'default' => 0,
        'editable' => false,
        'in_list' => false,
        'default_in_list' => false,
    ),
    'num' => array(
        'type' => 'number',
        'comment' => '冻结数',
        'label' => '冻结数',
        'default' => 0,
        'editable' => false,
        'in_list' => true,
        'default_in_list' => true,
        'width' => 150,
        'order' => 90,
    ),
    'create_time' => array(
            'type' => 'time',
            'label' => '创建时间',
            'width' => 150,
            'in_list' => true,
            'order' => 98,
    ),
    'last_modified' => array (
            'label' => '最后更新时间',
            'type' => 'last_modify',
            'width' => 150,
            'in_list' => true,
            'order' => 99,
    ),
    'store_code' => array(
        'type' => 'varchar(64)',
        'default' => '',
        'editable' => false,
        'label' => '预选仓库编码',
    ),
    'original_item_id' => array(
        'type' => 'int unsigned',
        'label' => '原单明细行ID',
        'default' => 0,
        'editable' => false,
        'in_list' => false,
        'default_in_list' => false,
    ),
    'source' => array(
        'type'            => 'varchar(255)',
        'label'           => '调用来源方法',
        'default'         => '',
        'default_in_list' => false,
        'in_list'         => true,
        'order'           => 100,
    ),
    'at_time'           => [
        'type'    => 'TIMESTAMP',
        'label'   => '创建时间',
        'default' => 'CURRENT_TIMESTAMP',
        'width' => 150,
    ],
    'up_time'           => [
        'type'    => 'TIMESTAMP',
        'label'   => '更新时间',
        'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        'width' => 150,
    ],
  ),
  'index' => array (
    'ind_obj_type_id' => array (
        'columns' => array (
            0 => 'obj_id',
            1 => 'obj_type',
        ),
    ),
    'ind_bm_obj_shop' => array(
        'columns' => array(
            0 => 'bm_id',
            1 => 'obj_type',
            2 => 'shop_id',
        ),
    ),
    'ind_obj_bn' => array(
        'columns' => array(
            0 => 'obj_bn',
        ),
    ),
    'ind_objtype_smid' => array(
        'columns' => array(
            'obj_type',
            'sm_id',
        ),
    ),
    'idx_bmid_num' => [
        'columns'   =>  [
            'bm_id',
            'num',
        ],
    ],
    'ind_branch_obj_bill_bm' => array(
        'columns' => array(
            0 => 'branch_id',
            1 => 'obj_type',
            2 => 'bill_type',
            3 => 'bm_id',
        ),
    ),
    'ind_objid_objtype_bmid_bill_bmsq' => array(
        'columns' => array(
            0 => 'obj_id',
            1 => 'obj_type',
            2 => 'bm_id',
            3 => 'bill_type',
            4 => 'bmsq_id',
        ),
    ),
    'ind_attime' => array(
        'columns' => array(
            0 => 'at_time',
        ),
    ),
    'ind_uptime' => array(
        'columns' => array(
            0 => 'up_time',
        ),
    ),
    'ind_num' => array(
        'columns' => array(
            0 => 'num',
        ),
    ),
  ),
  'comment' => '基础物料预占流水表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
