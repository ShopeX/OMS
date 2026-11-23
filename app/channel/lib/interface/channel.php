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

class channel_interface_channel{

    function __construct($app){
        $this->app = $app;
    }

    public function dump($filter,$field = '*',$subSdf = null){
        return $this->app->model('channel')->dump($filter, $field, $subSdf);
    }

    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null){
        return $this->app->model('channel')->getList($cols, $filter, $offset, $limit, $orderType);
    }

    public function save(&$data){
        if(!$data){
            return false;
        }
        return $this->app->model('channel')->save($data);
    }

    public function delete($filter){
        return $this->app->model('channel')->delete($filter);
    }
}