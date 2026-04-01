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


class desktop_router implements base_interface_router{

    function __construct($app){
        $this->app = $app;
    }

    function gen_url($params=array(),$full=false){
        $params = utils::http_build_query($params);
        if($params){
            return $this->app->base_url($full).'index.php?'.$params;
        }else{
            return $this->app->base_url($full);
        }
    }

    function dispatch($query){
        $_GET['ctl'] = $_GET['ctl']?$_GET['ctl']:'default';
        $_GET['act'] = $_GET['act']?$_GET['act']:'index';
        $_GET['app'] = $_GET['app']?$_GET['app']:'desktop';
        $query_args = $_GET['p'];

        $controller = app::get($_GET['app'])->controller($_GET['ctl']);
        $arrMethods = get_class_methods($controller);
        if (in_array($_GET['act'], $arrMethods))
            call_user_func_array(array(&$controller,$_GET['act']),(array)$query_args);
        else{
            header("HTTP/1.1 404 Not Found");
            echo '<h1>404 - Page Not Found</h1>';
            echo '<p>请求的页面不存在</p>';
            exit;
        }
    }
    
    /**
     * 生成菜单唯一ID finder_vid
     * @Author: xueding
     * @Vsersion: 2022/9/8 上午10:38
     * @param $path
     * @return false|string
     */
    public static function getFinderVid($path)
    {
        // if (stripos($path,'?')) {
        //     $path = mb_substr($path,stripos($path,'?')+1);
        // }

        $path = stripos($path,'?') ? parse_url($path, PHP_URL_QUERY) : $path;

        parse_str($path, $pathArr);
        
        // if (isset($pathArr['view'])) {
        //     unset($pathArr['view']);
        // }

        unset($pathArr['finder_vid'],$pathArr['finder_id'],$pathArr['_finder'],$pathArr['view']);

        
        ksort($pathArr);
        
        $newUrl = http_build_query($pathArr);

        return substr(md5($newUrl), 5, 6);
    }

}
