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

/*
 * @package base
 * @copyright Copyright (c) 2010, shopex. inc
 * @author edwin.lzh@gmail.com
 * @license 
 */
class base_cache_ecae extends base_cache_abstract implements base_interface_cache  
{

    function __construct() 
    {
        $this->check_vary_list();
    }//End Function

    public function fetch($key, &$result) 
    {
        $result = ecae_cache_read($key);
        return $result !== false;
    }//End Function

    public function store($key, $value, $ttl=0) 
    {
        return ecae_cache_write($key, $value);
    }//End Function

    public function status() 
    {
        $status = ecae_cache_stats();
        foreach($status AS $key=>$value){
            $return[$key.'=>缓存获取'] = $value['cmd_get'];
            $return[$key.'=>缓存存储'] = $value['cmd_set'];
            $return[$key.'=>可使用缓存'] = $value['limit_maxbytes']/1024/1024 ." MB";
        }
        return $return;
    }//End Function
}//End Class