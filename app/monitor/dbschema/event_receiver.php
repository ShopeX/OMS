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
 * @Author: xueding@shopex.cn
 * @Vsersion: 2022/10/14
 * @Describe: 模板接收用户信息
 */

$db['event_receiver'] = array(
    'columns' => array(
        'id'          => array(
            'type'     => 'int unsigned',
            'required' => true,
            'pkey'     => true,
            'extra'    => 'auto_increment',
            'label'    => 'ID',
            'width'    => 110,
            'hidden'   => true,
            'editable' => false,
        ),
        'event_type' => array(
            'type'            => 'text',
//            'required'        => true,
            'label'           => '触发事件',
            'comment'         => '触发事件',
            'editable'        => false,
            'in_list'         => false,
            'default_in_list' => false,
            'order'           => 10,
        ),
        'receiver'    => array(
            'type'            => 'varchar(50)',
            'label'           => '收件人',
            'comment'         => '收件人',
            'editable'        => false,
            'searchtype'      => 'nequal',
            'in_list'         => true,
            'default_in_list' => true,
            'filtertype'    => 'normal',
            'filterdefault' => true,
            'order'           => 20,
        ),
        'send_type'     => array(
            'type'            => array(
                'sms'   => '短信',
                'email' => '邮箱',
            ),
            'default'         => 'email',
            'label'           => '发送类型',
            'comment'         => '发送类型',
            'editable'        => false,
            'filtertype' => 'normal',
            'filterdefault'   => true,
        ),
        'org_id'             => array(
            'type'            => 'varchar(200)',
            'label'           => '运营组织',
            'editable'        => false,
            'width'           => 150,
            'filtertype'      => 'normal',
            'filterdefault'   => false,
            'in_list'         => false,
            'default_in_list' => false,
        ),
        'at_time'     => array(
            'type'            => 'TIMESTAMP',
            'label'           => '创建时间',
            'comment'         => '创建时间',
            'default'         => 'CURRENT_TIMESTAMP',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 30,
        ),
        'up_time'     => array(
            'type'            => 'TIMESTAMP',
            'label'           => '修改时间',
            'comment'         => '修改时间',
            'default'         => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'in_list'         => true,
            'default_in_list' => true,
            'order'           => 40,
        ),
    
    ),
    'index'   => array(
        'ind_receiver'     => array('columns' => array('receiver',) , 'prefix' => 'unique'),
    ),
    'comment' => '预警配置',
    'engine'  => 'innodb',
    'version' => '$Rev:  $',
);
