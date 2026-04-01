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


$db['rpcnotify']=array (
    'columns' => 
    array (
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
      'width'=>40,
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
      'width'=>200,
      'label' => app::get('base')->_('信息'),
      'in_list' =>false,
      'default_in_list' => false,
    ),
    'notifytime' => 
    array (
      'type' => 'time',
      'label' => app::get('base')->_('通知时间'),
      'required' => true,
      'width' => 140,
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
    ),
  'index' => 
  array (
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
  ),
    'comment' => '系统通知表',
    'engine' => 'innodb',
    'version' => '$Rev: 40912 $',
    'ignore_cache' => true,
);

