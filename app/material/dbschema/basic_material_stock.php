<?php
/**
 * 基础物料库存数据结构
 *
 * @author kamisama.xia@gmail.com 
 * @version 0.1
 */

$db['basic_material_stock']=array (
  'columns' =>
  array (
    'bm_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'width' => 150,
      'hidden' => true,
      'editable' => false,
      'pkey' => true,
    ),
    'store' =>
    array (
      'type' => 'int NOT NULL',
      'editable' => false,
      'comment' => '库存（各仓库 的库存总和）',
      'label' => '库存',
      'default' => 0,
      'width' => 150,
      'in_list' => true,
      'filtertype' => 'number',
      'filterdefault' => true,
      'default_in_list' => true,
    ),
    'store_freeze' =>
    array (
      'type' => 'int NOT NULL',
      'sdfpath' => 'freez',
      'label' => '冻结库存',
      'width' => 150,
      'hidden' => true,
      'filtertype' => 'number',
      'filterdefault' => true,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
      'default' => 0,
    ),
    'alert_store' =>
    array (
      'type' => 'number',
      'label' => '安全库存数',
      'default' => 0,
      'editable' => false,
      'in_list' => false,
      'default_in_list' => false,
    ),
    'last_modified' =>
    array (
      'type' => 'last_modify',
      'label' => '最后修改日期',
      'width' => 150,
      'editable' => false,
      'in_list' => true,
    ),
    'real_store_lastmodify' =>
    array (
      'type' => 'time',
      'editable' => false,
      'comment' => '实际库存最后更新时间',
    ),
    'max_store_lastmodify' =>
    array (
      'type' => 'time',
      'editable' => false,
      'comment' => '最大可下单库存最后更新时间',
    ),
    'inc_store_lastmodify' =>
    array (
      'type' => 'time',
      'editable' => false,
      'default' => 0,
      'comment' => '库存最后添加时间（仅库存增加时更新）',
      'in_list' => false,
      'default_in_list' => false,
    ),
  ),
  'index' => array (
      'ind_last_modified' => array (
          'columns' => array (
              0 => 'last_modified',
          ),
      ),
      'ind_store_lastmodify' => array (
          'columns' => array (
              0 => 'max_store_lastmodify',
          ),
      ),
      'ind_inc_store_lastmodify' => array (
          'columns' => array (
              0 => 'inc_store_lastmodify',
          ),
      ),
  ),
  'comment' => '基础物料总库存表',
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
