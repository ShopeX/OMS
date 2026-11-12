<?php
/**
 * 组合基础物料明细数据结构
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

$db['basic_material_combination_items']=array (
  'columns' =>
  array (
    'pbm_id' =>
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
    'material_name' =>
    array (
      'type' => 'varchar(200)',
      'required' => true,
      'label' => '物料名称',
      'is_title' => true,
      'default_in_list' => true,
      'width' => 260,
      'searchtype' => 'nequal',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
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
    'material_num'=>
    array(
      'type'=>'mediumint(8)',
      'default' => 1,
    ),
  ),
  'index' =>
  array(
    'ind_pbm_id' =>
    array(
        'columns' =>
        array(
            0 => 'pbm_id',
        ),
    ),
  ),
  'comment' => '基础物料组合类扩展表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
