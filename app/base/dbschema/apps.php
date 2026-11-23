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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
$db['apps']=array (
    
  'columns' => 
  array (
    'app_id' => 
    array (
      'type' => 'varchar(32)',
      'required' => true,
      'default' => '',
      'pkey' => true,
      'width' => 100,
      'label' => app::get('base')->_('程序目录'),
      'hidden' => 1,
      'editable' => false,
      'in_list' => true,
      'default_in_list' => false,
    ),
    'app_name' => array ('type' => 'varchar(50)','width' => 150,'label' => app::get('base')->_('应用程序'),'is_title'=>1, 'in_list' => true,'default_in_list' => 1 ),
    'debug_mode' => array ('type' => 'bool','default' => 'false','width' => 100,'label' => app::get('base')->_('调试模式'), 'in_list' => true,'default_in_list' => false ),
    'app_config' => array ('type' => 'text'),
    'status' =>  array (
      'label' => app::get('base')->_('状态'),
      'width' => 100,
      'default' => 'uninstalled',
      'type' => 
      array (
        'installed' => app::get('base')->_('已安装, 未启动'),
        'resolved' => app::get('base')->_('已配置'),
        'starting' => app::get('base')->_('正在启动'),
        'active' => app::get('base')->_('运行中'),
        'stopping' => app::get('base')->_('正在关闭'),
        'uninstalled' => app::get('base')->_('尚未安装'),
        'broken' => app::get('base')->_('已损坏'),
        'paused' => app::get('base')->_('已暂停'),
      ),
      'in_list' => true,
      'default_in_list' => true,
    ),
    'webpath'=>array('type'=>'varchar(20)'),
    'description'=>array('type'=>'varchar(255)','width' => 300,'label' => app::get('base')->_('说明'),'in_list' => true,'default_in_list' => 1),
    'local_ver'=>array('type'=>'varchar(20)','width' => 100,'label' => app::get('base')->_('当前版本'),'in_list' => true,'default_in_list' => 1),
    'remote_ver'=>array('type'=>'varchar(20)','width' => 100,'label' => app::get('base')->_('最新版本'),'in_list' => true,'default_in_list' => false),
    'author_name'=>array('type'=>'varchar(100)'),
    'author_url'=>array('type'=>'varchar(100)'),
    'author_email'=>array('type'=>'varchar(100)'),
    'dbver'=>array('type'=>'varchar(32)'),
    'remote_config'=>array('type'=>'serialize')
  ),
    'comment' => '系统应用表',
    'version' => '$Rev: 44008 $',
    'unbackup' => true,
);
