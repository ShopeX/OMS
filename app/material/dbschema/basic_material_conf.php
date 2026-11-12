<?php
/**
 * 基础物料配置信息数据结构
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

$db['basic_material_conf']=array (
  'columns' =>
  array (
    'bm_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      // 'extra' => 'auto_increment',
      'label' => 'ID',
      'width' => 150,
      'hidden' => true,
      'editable' => false,
    ),
    'use_expire' =>
    array (
      'type' => 'tinyint(1)',
      'label' => '保质期开关',
      'editable' => false,
      'default'         => 2,
    ),
    'warn_day' =>
    array (
      'type' => 'number',
      'label' => '预警天数',
      'editable' => false,
      'default'         => 0,
    ),
    'quit_day' =>
    array (
      'type' => 'number',
      'label' => '自动退出库存天数',
      'editable' => false,
      'default'         => 0,
    ),
    'use_expire_wms' => 
    array (
      'type' => 'tinyint(1)',
      'label' => '保质期开关',
      'editable' => false,
      'default' => 2,
    ),
    'shelf_life' => 
    array (
      'type' => 'number',
      'label' => '保质期(小时)',
      'editable' => false,
      'default' => 0,
    ),
    'reject_life_cycle' => 
    array (
      'type' => 'number',
      'label' => '禁收天数',
      'editable' => false,
      'default' => 0,
    ),
    'lockup_life_cycle' => 
    array (
      'type' => 'number',
      'label' => '禁售天数',
      'editable' => false,
      'default' => 0,
    ),
    'advent_life_cycle' => 
    array (
      'type' => 'number',
      'label' => '临期预警天数',
      'editable' => false,
      'default' => 0,
    ),
    'create_time' => array(
        'type' => 'time',
        'label' => '创建时间',
        'in_list' => true,
        'default' => 0,
    ),
  ),
  'comment' => '基础物料配置表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
