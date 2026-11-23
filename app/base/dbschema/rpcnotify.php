<?php
/**
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
$db['rpcnotify'] = array (
  'columns' => array (
    'id' => 
    array (
      'type' => 'bigint unsigned',
      'extra' => 'auto_increment',
      'pkey' => true,
      'label' => 'ID',
      'required' => true,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
      'width' => 90,
    ),
    'callback' => 
    array (
      'type' => 'varchar(200)',
      'label'=>'API',
      'required' => true,
      'in_list'=>true,
      'width'=>100,
    ),
    'rsp' =>
    array(
      'type' => array(
            'succ' => app::get('base')->_('成功'),
            'fail' => app::get('base')->_('失败'),
      ),
      'label' => app::get('base')->_('状态'),
      'required' => true,
      'width' => 100,
      'in_list' =>true,
      'default_in_list' => true,
    ),
    'msg'=>array(
      'type' => 'text',
      'required' => true,
      'width' => 320,
      'label' => app::get('base')->_('信息'),
      'filtertype' => 'normal',
      'filterdefault' => true,
      'searchtype' => 'has',
      'in_list' =>false,
      'default_in_list' => false,
    ),
    'notifytime' => 
    array (
      'type' => 'time',
      'label' => app::get('base')->_('通知时间'),
      'required' => true,
      'filtertype'    => 'time',
      'filterdefault' => true,
      'width' => 160,
      'in_list'=> true,
      'default_in_list'=> true,
    ),
    'status' =>
    array(
      'type' => array(
            'true' => app::get('base')->_('已读'),
            'false' => app::get('base')->_('未读'),
      ),
      'label' => app::get('base')->_('状态'),
      'default' => 'false',
      'required' => true,
      'width' => 100,
      'in_list' =>true,
      'default_in_list' => true,
    ),
    'read_user' =>
    array(
      'type' => 'varchar(255)',
      'label' => app::get('base')->_('已读人'),
      'width' => 100,
      'in_list' =>true,
      'default_in_list' => true,
    ),
    'event_type' =>
    array(
      'type' => 'varchar(50)',
      'label' => app::get('base')->_('模板类型'),
      'width' => 260,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => true,
    ),
    'msg_type' => array(
        'type' => 'varchar(30)',
        'label' => '消息类型',
        'default' => 'system',
        'width' => 120,
        'editable' => false,
        'in_list' => true,
        'default_in_list' => true,
    ),
    'shop_type' => array (
        'type' => 'varchar(50)',
        'required' => false,
        'label' => '店铺类型',
        'in_list' => true,
        'default_in_list' => false,
        'width' => 110,
    ),
    'shop_bn' => array (
        'type' => 'varchar(20)',
        'required' => false,
        'label' => '店铺编码',
        'filtertype' => 'normal',
        'filterdefault' => true,
        'in_list' => true,
        'default_in_list' => false,
        'width' => 110,
    ),
    'org_id' => array(
        'type'            => 'number',
        'label'           => '运营组织',
        'default'         => 0,
        'editable'        => false,
        'in_list'         => true,
        'default_in_list' => false,
        'width'           => 160,
    ),
  ),
  'index' => array (
    'ind_status' => 
    array (
      'columns' => 
      array (
        0 => 'status',
      ),
    ),
    'ind_notifytime' => 
    array (
      'columns' => 
      array (
        0 => 'notifytime',
      ),
    ),
    'ind_event_type' =>
    array (
      'columns' =>
      array (
        0 => 'event_type',
      ),
    ),
    'ind_msg_shop_type' => array (
        'columns' => array (
            0 => 'msg_type',
            1 => 'shop_type',
        ),
    ),
    'ind_org_id' => array (
        'columns' => array (
            0 => 'org_id',
        ),
    ),
  ),
    'comment' => '系统通知表',
    'engine' => 'innodb',
    'version' => '$Rev: 40912 $',
    'ignore_cache' => true,
);

