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
 
class base_misc_autotask{

    function trigger(){

        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit','1024M');
        $status = $this->status();
        $now = time();

        foreach(kernel::servicelist('autotask') as $k=>$o){
            foreach($this->type() as $name => $interval){
                if(isset($status[$k][$name]) && $now-$status[$k][$name]<$interval ){
                    continue;
                }
                kernel::log($k.'::'.$name);
                if(!method_exists($o,$name)) continue;
                $o->$name();
                $data = array( 'last'=>$now, 'task'=>$k, $name=>$now,);
                app::get('base')->model('task')->replace($data ,array('task'=>$k));
            }
        }

    }

    function type(){
        return array(
            'minute' => 60,
            'hour' => 3600,
            'day'=> 3600*24,
            'week' => 3600*24*7,
            'month'=> 3600*24*30,
        );
    }

    function status(){
        $status = array();
        foreach(app::get('base')->model('task')->getlist('*') as $row){
            $status[$row['task']] = $row;
        }
        return $status;
    }

}
