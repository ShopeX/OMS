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
 
class base_command_queue extends base_shell_prototype{

    var $command_list = '列出所有队列任务';
    function command_list(){
        $rows = app::get('base')->model('queue')->getList('queue_id,queue_title,status,worker');        
        $this->output_table( $rows );
    }
    
    var $command_flush = '立即执行所有队列任务';
    function command_flush(){
        app::get('base')->model('queue')->flush();
    }
    
    var $command_exec = '执行指定的队列任务';
    function command_exec($queue_id){
        app::get('base')->model('queue')->runtask($queue_id);
    }
    
    var $command_clear = '清除所有队列任务';
    var $command_active = '激活某任务';
    var $command_disable = '暂停某任务';

}
