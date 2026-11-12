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

use app\desktop\lib\annotation\RequirePermission;

class desktop_ctl_queue extends desktop_controller{

    var $workground = 'desktop_ctl_system';

    #[RequirePermission('performance')]
    function index(){
        $params = array(
            'title'=>app::get('desktop')->_('队列列表'),
            'actions'=>array(
                array('label'=>app::get('desktop')->_('全部启动'),'submit'=>'index.php?app=desktop&ctl=queue&act=run'),
                array('label'=>app::get('desktop')->_('全部暂停'),'submit'=>'index.php?app=desktop&ctl=queue&act=pause'),
                ),
            );
        $this->finder('base_mdl_queue',$params);
    }

    #[RequirePermission('performance')]
    function run(){
        $this->begin('index.php?app=desktop&ctl=queue&act=index');
        $queue_model = app::get('base')->model('queue');
        foreach((array)$_POST['queue_id'] as $id){
            $item['queue_id'] = $id;
            $item['status'] = 'hibernate';
            $queue_model->save($item);        
        }
        $queue_model->flush();
        $this->end(true,app::get('desktop')->_('启动成功'));
    }
    
    #[RequirePermission('performance')]
    function pause(){
        $this->begin('index.php?app=desktop&ctl=queue&act=index');
        $queue_model = app::get('base')->model('queue');
        foreach((array)$_POST['queue_id'] as $id){
            $item['queue_id'] = $id;
            $item['status'] = 'paused';
            $queue_model->save($item);
        }
        $this->end(true,app::get('desktop')->_('暂停成功'));
    }


}
