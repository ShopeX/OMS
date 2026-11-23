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
 
$datatypes = array(
    'money'=>array(
        'sql'=>'decimal(20,3)',
        'searchparams'=>array('than'=>app::get('base')->_('大于'),'lthan'=>app::get('base')->_('小于'),'nequal'=>app::get('base')->_('等于'),'sthan'=>app::get('base')->_('小于等于'),'bthan'=>app::get('base')->_('大于等于'),'between'=>app::get('base')->_('介于')),
        'match'=>'[0-9]{1,18}(\.[0-9]{1,3}|)',
    ),
    'email'=>array(
        'sql'=>'varchar(255)',
        'searchparams'=>array('has'=>app::get('base')->_('包含'),'tequal'=>app::get('base')->_('等于'),'head'=>app::get('base')->_('开头等于'),'foot'=>app::get('base')->_('结尾等于'),'nohas'=>app::get('base')->_('不包含')),
    ),
    'bn'=>array(
        'sql'=>'varchar(255)',
        'searchparams'=>array('has'=>app::get('base')->_('包含'),'tequal'=>app::get('base')->_('等于'),'nohas'=>app::get('base')->_('不包含')),
    ),
    'html'=>array(
        'sql'=>'text',
    ),
    'bool'=>array(
        'sql'=>'enum(\'true\',\'false\')',
        'searchparams'=>array('has'=>app::get('base')->_('包含'),'nohas'=>app::get('base')->_('不包含')),
    ),
    'time'=>array(
        'sql'=>'integer(10) unsigned',
        'searchparams'=>array('than'=>app::get('base')->_('晚于'),'lthan'=>app::get('base')->_('早于'),'nequal'=>app::get('base')->_('是'),'between'=>app::get('base')->_('介于')),
    ),
    'cdate'=>array(
        'sql'=>'integer(10) unsigned',
    ),
    'intbool'=>array(
        'sql'=>'enum(\'0\',\'1\')',
    ),
    'region'=>array(
        'sql'=>'varchar(255)',
    ),
    'password'=>array(
        'sql'=>'varchar(32)',
    ),
    'tinybool'=>array(
        'sql'=>'enum(\'Y\',\'N\')',
    ),
    'number'=>array(
        'sql'=>'mediumint unsigned',
        'searchparams'=>array('than'=>app::get('base')->_('大于'),'lthan'=>app::get('base')->_('小于'),'nequal'=>app::get('base')->_('等于'),'sthan'=>app::get('base')->_('小于等于'),'bthan'=>app::get('base')->_('大于等于'),'between'=>app::get('base')->_('介于')),
    ),
    'float'=>array(
        'sql'=>'float',
        'searchparams'=>array('than'=>app::get('base')->_('大于'),'lthan'=>app::get('base')->_('小于'),'nequal'=>app::get('base')->_('等于'),'sthan'=>app::get('base')->_('小于等于'),'bthan'=>app::get('base')->_('大于等于'),'between'=>app::get('base')->_('介于')),
    ),
    'gender'=>array(
        'sql'=>'enum(\'male\',\'female\')',
    ),
    'ipaddr'=>array(
        'sql'=>'varchar(20)',
    ),
    'serialize'=>array(
        'sql'=>'longtext',
    ),
    'last_modify'=>array(
        'sql'=>'integer(10) unsigned',
        'searchparams'=>array('than'=>app::get('base')->_('大于'),'lthan'=>app::get('base')->_('小于'),'nequal'=>app::get('base')->_('等于')),
    ),
    'TIMESTAMP'=>array(
        'searchparams'=>array('than'=>app::get('base')->_('晚于'),'lthan'=>app::get('base')->_('早于'),'nequal'=>app::get('base')->_('是'),'between'=>app::get('base')->_('介于')),
    ),
    'memo'=>array(
        'sql'=>'longtext',
        'searchparams'=>array('has'=>app::get('base')->_('存在'),'nohas'=>app::get('base')->_('不存在')),
    ),
);
