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


$db['log'] = array(
    'columns'=>array(
        'event_id'=>array('type'=>'number','pkey'=>true,'extra' => 'auto_increment','in_list'=>true,'default_in_list'=>true),
        'event_time'=>array('type'=>'varchar(50)','comment'=>'时间','in_list'=>true,'default_in_list'=>true,'label'=>'登录时间'),
        'event_data'=>array('type'=>'varchar(500)','comment'=>'数据','in_list'=>true,'default_in_list'=>true,'label'=>'登录信息'),
        'event_type'=>array('type'=>'text','comment'=>'类型','in_list'=>true,'default_in_list'=>true,'label'=>'登录类型'),
    ),
    'comment'=>'授权日志',
);
