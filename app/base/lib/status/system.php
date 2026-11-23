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
 

class base_status_system extends base_status_abstract{
    
    function get_cache_status(){
        $ret = array(
            'cache.engine'=>CACHE_STORAGE,
            );
            
        if(method_exists(CACHE_STORAGE,'status')){
            foreach(kernel::single(CACHE_STORAGE) as $k=>$v){
                $ret['cache.'.$k] = $v;
            }
        }
        return $ret;
    }
    
    function get_kvstore_status(){
        $ret = array(
            'kvstore.engine'=>KVSTORE_STORAGE,
            );
            
        if(method_exists(KVSTORE_STORAGE,'status')){
            foreach(kernel::single(KVSTORE_STORAGE) as $k=>$v){
                $ret['kvstore.'.$k] = $v;
            }
        }
        return $ret;
    }
    
    function get_mysql_status(){
        $aResult = array(
            'mysql.server_host'=>DB_HOST,
            'mysql.server_dbname'=>DB_NAME,
            'mysql.server_user'=>DB_USER,
        );
        foreach(kernel::database()->select("show status") as $row) 
        {
            $aResult['mysql.'.strtolower($row["Variable_name"])] = $row["Value"];
        }
        return $aResult;
    }

}