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

class base_mdl_queue extends base_db_model{

    var $limit = 100; //最大任务并发
    var $task_timeout = 300; //单次任务超时

    function flush(){
        //屏蔽原来的入口
        return true;
        $base_url = kernel::base_url();
        foreach($this->db->select('select queue_id from sdb_base_queue limit '.$this->limit) as $r){
            $this->runtask($r['queue_id']);
        }
    }

    function runtask($task_id){
        $http = new base_httpclient;
        $_POST['task_id'] = $task_id;
        $url =  kernel::openapi_url('openapi.queue','worker',array('task_id'=>$task_id));
        kernel::log('Spawn [Task-'.$task_id.'] '.$url);

        //99%概率不会有问题
        $http->hostaddr = $_SERVER["SERVER_NAME"]?$_SERVER["SERVER_NAME"]:'127.0.0.1';
        $http->hostport = $_SERVER["SERVER_PORT"]?$_SERVER["SERVER_PORT"]:'80';
        $http->timeout = 2;
        kernel::log($http->post($url,$_POST));
    }

    /**
     *  重写伪队列数据保存的方法，任务走taskmgr机制
     *
     * @param array $data 传入参数
     * @param array $mustUpdate 是否必须更新，框架机制没人用过，是不是坑请仙人自己尝试
     * @return boolean true/false
     */
    function save(&$data,$mustUpdate = null){
        $push_data = $data;

        $res = parent::save($data,$mustUpdate);

        $push_params = array(
            'data' => array(
                'queue_id' => $data['queue_id'],
                'task_type' => 'queue'
            ),
            'url' => kernel::openapi_url('openapi.autotask','service')
        );

        $push_data['params'] = serialize($push_data['params']);
        $push_params['data'] = array_merge($push_params['data'], $push_data);

        kernel::single('taskmgr_interface_connecter')->push($push_params);

        return $res;
    }

}
