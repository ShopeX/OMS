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
 
class base_mdl_apps extends base_db_model{

    function filter($filter){
        $addons = array();
        if(isset($filter['installed'])){
            $addons[] = $filter['installed']?'status!="uninstalled"':'status="uninstalled"';
            unset($filter['installed']);
        }
        
        if(isset($filter['normalview'])){ //普通用户浏览模式
            $hidden_apps = true;
            if($service = kernel::service('base_mdl_apps_hidden')){
                if(method_exists($service, 'is_hidden')){
                    $hidden_apps = $service->is_hidden($filter);
                }
            }
            if($hidden_apps === true){
                $depends_apps = array_keys($this->check_deploy_depends());
                $package = $this->fetch_deploy_package();
                $package_apps = array();
                foreach($package AS $package_app){
                    $package_apps[] = $package_app['id'];
                }
                $diff_apps = array_diff($depends_apps, $package_apps);
                if(count($diff_apps)){
                    $addons[] = "`app_id` NOT IN ('" . join("', '", $diff_apps) . "')";
                }//todo: 隐藏信赖app信息
            }//todo：判断是否需要隐藏app
        }
        unset($filter['normalview']);

        $addons = implode(' AND ',$addons);
        if($addons) $addons.=' AND ';
        return $addons.parent::filter($filter);
    }

    public function fetch_deploy_package() 
    {
        $deploy = kernel::single('base_xml')->xml2array(file_get_contents(ROOT_DIR.'/config/deploy.xml'),'base_deploy');
        return (is_array($deploy['package']['app'])) ? $deploy['package']['app'] : array();
    }//End Function

    public function check_deploy_depends() 
    {
        $depends_apps = array();
        $package = $this->fetch_deploy_package();
        foreach($package AS $package_app){
            $this->check_depends_install($package_app['id'], $depends_apps);
        }
        return $depends_apps;
    }//End Function

    public function check_depends_install($app_id, &$queue){
        $depends_app = app::get($app_id)->define('depends/app');
        foreach((array)$depends_app as $depend_app_id){
            $this->check_depends_install($depend_app_id['value'], $queue);
        }
        $queue[$app_id] = app::get($app_id)->define();
    }

}
