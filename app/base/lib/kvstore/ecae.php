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
 * @copyright Copyright (c) 2011, shopex. inc
 * @author edwin.lzh@gmail.com
 * @license 
 */
class base_kvstore_ecae extends base_kvstore_abstract implements base_interface_kvstore_base 
{
    function __construct($prefix) 
    {
        $this->prefix = $prefix;
    }//End Function

    public function create_key($key) 
    {
        if(isset($key[201])){
            return parent::create_key($key);
        }
        return sprintf("%s/%s/%s", base_kvstore::kvprefix(), $this->prefix, $key);
    }//End Function

    public function store($key, $value, $ttl=0) 
    {
        $data['key'] = $key;
        $data['prefix'] = $this->prefix;
        $data['value'] = $value;
        $data['ttl'] = $ttl;
        $data['dateline'] = time();
        return ecae_kvstore_write($this->create_key($key), $data);
    }//End Function

    public function fetch($key, &$value, $timeout_version=null) 
    {
        if(ecae_kvstore_read($this->create_key($key), $data)){
            if($timeout_version < $data['dateline']){
                if($data['ttl'] > 0 && ($data['dateline'] + $data['ttl']) < time()){
                    return false;
                }
                $value = $data['value'];
                return true;
            }
        }
        return false;
    }//End Function

    public function delete($key) 
    {
        return ecae_kvstore_delete($this->create_key($key));
    }//End Function

    public function recovery($record) 
    {
        $key = $record['key'];
        $data['key'] = $key;
        $data['prefix'] = $this->prefix;
        $data['value'] = $record['value'];
        $data['ttl'] = $record['ttl'];
        $data['dateline'] = $record['dateline'];
        return ecae_kvstore_write($this->create_key($key), $data);
    }//End Function

}//End Class