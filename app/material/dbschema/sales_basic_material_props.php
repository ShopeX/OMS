<?php
/**
 * 销售物料与基础物料关联配置信息数据结构
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */

$db['sales_basic_material_props'] = array(
  'columns' => array(
    'id' => array(
      'type'     => 'int unsigned',
      'required' => true,
      'pkey'     => true,
      'extra'    => 'auto_increment',
      'label'    => 'ID',
      'width'    => 110,
      'hidden'   => true,
      'editable' => false,
      'comment'  => '自增主键',
    ),
    'sm_id' => array(
      'type'     => 'int unsigned',
      'required' => true,
      'width'    => 110,
      'hidden'   => true,
      'editable' => false,
      'comment'  => '销售物料ID,关联material_sales_material.sm_id',
    ),
    'bm_id' => array(
      'type'     => 'int unsigned',
      'required' => true,
      'width'    => 110,
      'hidden'   => true,
      'editable' => false,
      'comment'  => '基础物料ID,关联material_basic_material.bm_id',
    ),
   
    'props_col' => array(
      'type'     => 'varchar(50)',
      'editable' => false,
      'label'    => '键名',
      'comment'  => '键名，如调色号: props_col=custom_color_code',
    ),
    'props_value' => array(
      'type'     => 'varchar(200)',
      'editable' => false,
      'label'    => '键值',
      'comment'  => '键值',
    ),
    'at_time' => array(
      'type'    => 'TIMESTAMP',
      'label'   => '创建时间',
      'default' => 'CURRENT_TIMESTAMP',
      'width'   => 130,
      'in_list' => true,
      'order'   => 100,
    ),
    'up_time' => array(
      'type'    => 'TIMESTAMP',
      'label'   => '更新时间',
      'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
      'width'   => 130,
      'in_list' => true,
      'order'   => 110,
    ),
  ),
  'index' => array(
    'ind_sm_id'     => array('columns' => array('sm_id', 'bm_id')),
    'ind_bm_id'     => array('columns' => array('bm_id')),
    'ind_props_col' => array('columns' => array('props_col')),
  ),
  'comment' => '销售物料与基础物料关联配置表，支持键值对扩展（如调色号等），组合商品/普通商品/成品均需',
  'engine'  => 'innodb',
  'version' => '$Rev:  $',
);
