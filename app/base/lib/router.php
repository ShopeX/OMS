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
 
class base_router implements base_interface_router{

    function __construct($app){
        $this->app = $app;
    }

    function gen_url($params=array(),$full=false){

        $url_array = array(
            $params['ctl']?$params['ctl']:'default',
            $params['act']?$params['act']:'index',
        );

        unset($params['ctl'],$params['act']);

        foreach($params as $k=>$v){
            $url_array[] = $k;
            $url_array[] = $v;
        }

        return $this->app->base_url($full).implode('/',$url_array);
    }

    function dispatch($query){
        $query_args = explode('/',$query);
        $controller = array_shift($query_args);
        $action = array_shift($query_args);
        if($controller == 'index.php'){
            $controller = '';
        }
        foreach($query_args as $i=>$v){
            if($i%2){
                $params[$k] = $v;
            }else{
                $k = $v;
            }
        }

        $controller = $controller?$controller:'default';
        kernel::request()->set_params($params);
        $action = $action?$action:'index';
        $controller = $this->app->controller($controller);

        $controller->$action();
    }

}
