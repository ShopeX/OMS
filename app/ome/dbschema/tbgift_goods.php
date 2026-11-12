<?php
$db['tbgift_goods']=array (
  'columns' =>
  array (
    'goods_id' =>
    array (
      'type' => 'int unsigned',
      'required' => true,
      'pkey' => true,
      'extra' => 'auto_increment',
      'label' => 'ID',
      'width' => 110,
      'hidden' => true,
      'editable' => false,
    ),
    'gift_bn' =>
    array (
      'type' => 'varchar(200)',
      'required' => true,
      'label' => '赠品编码',
      'searchtype' => 'head',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefault' => true,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'name' =>
    array (
      'type' => 'varchar(200)',
      'required' => true,
      'label' => '赠品名称',
      'is_title' => true,
      'default_in_list' => true,
      'width' => 260,
      'searchtype' => 'head',
      'editable' => false,
      'filtertype' => 'normal',
      'filterdefalut' => true,
      'in_list' => true,
    ),
    'goods_type' =>
    array (
      'type' =>
      array (
        'normal' => '普通商品',
        'bind' => '捆绑商品',
      ),
      'default' => 'normal',
      'required' => true
    ),
    'status' =>
    array(
        'type' => 'tinyint(1)',
        'required' => true,
        'editable' => false,
        'default' => 2,
    ),
  ),
  'comment' => '淘宝赠品表',
  'index' =>
    array (
      'uni_bn' =>
        array (
    	  'columns' =>
            array (
              0 => 'gift_bn',
            ),
	      'prefix' => 'UNIQUE',
        ),
	  'ind_name' =>
        array (
    	  'columns' =>
            array (
              0 => 'name',
            ),
        ),
    ),
  'engine' => 'innodb',
  'version' => '$Rev:  $',
);
