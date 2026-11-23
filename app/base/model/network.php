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
 

class base_mdl_network extends base_db_model{

    function invite($url,$message=null){
        $self_url = $this->system->base_url();
        $key = md5(rand(0,time()));
        $res = $this->call($url,'guest_ping',$key,$message);
        if($res){
            $data = array(
                    'node_name'=>$res->name,
                    'node_url'=>$res->url,
                    'link_status'=>'wait',
                    'sitekey'=>$key,
                    'node_detail'=>$res->desc,
                );
            $this->insert($data);
            return true;
        }else{
            return false;
        }
    }

    function call(){
        if(!$this->json_rpc){
            $this->json_rpc = $this->app->load('json_rpc');
        }
        $args = func_get_args();
        $url = array_shift($args);
        $func = array_shift($args);
        array_unshift($args,$this->system->base_url());
        return $this->json_rpc->call($url,$func,$args);
    }
  
}
