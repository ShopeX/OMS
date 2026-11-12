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
 * 基础物料保质期明细流水单据
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

$db['basic_material_storage_life_bills']=array (
  'columns' =>
  array (
    'bmslb_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'ID',
      'width' => 150,
      'hidden' => true,
      'editable' => false,
    ),
    'bmsl_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'width' => 150,
      'hidden' => true,
      'editable' => false,
    ),    
    'bm_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'width' => 150,
      'hidden' => true,
      'editable' => false,
    ),
    'material_bn' =>
    array (
	  'type' => 'varchar(200)',
      'label' => '物料编码',
      'width' => 150,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'required'        => true,
    ),
    'material_bn_crc32' =>
    array (
      'type' => 'bigint(13)',
      'label' => '货号查询索引值',
      'editable' => false,
      'required'        => true,
    ),
    'expire_bn' =>
    array (
      'type' => 'varchar(200)',
      'label' => '物料保质期编码',
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'required'        => true,
    ),
    'nums'=>
    array(
      'type'=>'mediumint(8)',
      'label' => '数量',
      'editable' => false,
      'default'         => 0,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'branch_id' =>
    array (
      'type' => 'number',
      'label' => '仓库ID',
      'required' => true,
      'editable' => false,
    ),
    'bill_id' => array(
        'type' => 'int unsigned',
        'label' => '关联单据ID',
        'hidden' => true,
        'default' => 0,
        'editable' => false,
        'required' => true,
    ),
    'bill_bn' => array(
        'type' => 'varchar(50)',
        'label' => '关联单据单号',
        'hidden' => true,
        'editable' => false,
        'required' => true,
    ),
    'bill_type' => array(
        'type' => 'number',
        'label' => '关联单据类型',
        'hidden' => true,
        'editable' => false,
        'required' => true,
    ),
    'bill_io_type' => array(
        'type' => 'number',
        'label' => '单据出入类型',
        'hidden' => true,
        'editable' => false,
        'required' => true,
    ),
  ),
  'comment' => '基础物料保质期明细流水单据',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
