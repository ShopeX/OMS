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
 

/*
 * @package base
 * @copyright Copyright (c) 2010, shopex. inc
 * @author edwin.lzh@gmail.com
 * @license 
 */
class base_kvstore_tair extends base_kvstore_abstract implements base_interface_kvstore_base 
{
    private $rs = null;

    function __construct($prefix) 
    {
        $this->rs = tair_init(TAIR_MASTER,TAIR_SLAVE,TAIR_GROUP);
        $this->prefix = $prefix;
    }//End Function

    public function fetch($key, &$value, $timeout_version=null) 
    {
        $store = tair_get($this->rs,$this->create_key($key));
        $store = unserialize($store);
        if($store !== false && $timeout_version < $store['dateline']){
            if($store['ttl'] > 0 && ($store['dateline']+$store['ttl']) < time()){
                return false;
            }
            $value = $store['value'];
            return true;
        }
        return false;
    }//End Function

    public function store($key, $value, $ttl=0) 
    {
        $store['value'] = $value;
        $store['dateline'] = time();
        $store['ttl'] = $ttl;
        $rst = tair_put($this->rs, $this->create_key($key), serialize($store));
        if(!$rst){
            $msg = tair_last_msg();
            trigger_error($msg[1],E_USER_ERROR);    
        }
        return $rst;
    }//End Function

    public function delete($key) 
    {
        return tair_remove($this->rs, $this->create_key($key));
    }//End Function

    public function recovery($record) 
    {
        $key = $record['key'];
        $store['value'] = $record['value'];
        $store['dateline'] = $record['dateline'];
        $store['ttl'] = $record['ttl'];
        $rst = tair_put($this->rs, $this->create_key($key), serialize($store));
        if(!$rst){
            $msg = tair_last_msg();
            trigger_error($msg[1],E_USER_ERROR);    
        }
        return $rst;
    }//End Function

}//End Class
