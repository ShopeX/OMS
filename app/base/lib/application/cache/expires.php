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
 

class base_application_cache_expires extends base_application_prototype_filepath  
{
    var $path = 'dbschema';

    public function install() 
    {
        $dbschema = $this->getPathname();
        if(is_file($dbschema)){
            require($dbschema);
            foreach($db AS $key=>$val){
                if($val['ignore_cache'] !== true){
                    $data['type'] = 'DB';
                    $data['app'] = $this->target_app->app_id;
                    $data['name'] = strtoupper($this->target_app->app_id . "_" . $key);
                    $data['expire'] = time();
                    kernel::log('Installing Cache_Expires DB:'. $data['name']);
                    app::get('base')->model('cache_expires')->replace($data,
                        array('type'=>$data['type'],'app'=>$data['app'],'name'=>$data['name'])
                        );
                }
                break;
            }
            kernel::log('UPDATE CACHE EXPIRES KV DATA');
            cachemgr::store_vary_list(cachemgr::fetch_vary_list(true)); //更新kv
        }
    }//End Function

    function clear_by_app($app_id){
        if(!$app_id){
            return false;
        }
        app::get('base')->model('cache_expires')->delete(array(
            'app'=>$app_id));
    }

}//End Class
