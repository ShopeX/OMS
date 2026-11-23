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
class base_kvstore_mysql extends base_kvstore_abstract implements base_interface_kvstore_base
{

    function __construct($prefix) 
    {
        $this->prefix = $prefix;
    }//End Function

    public function store($key, $value, $ttl=0) 
    {
        $rows = app::get('base')->model('kvstore')->getList('id', array('prefix'=>$this->prefix, 'key'=>$key));
        $data = array('prefix'=>$this->prefix, 'key'=>$key, 'value'=>$value, 'dateline'=>time(), 'ttl'=>$ttl);
        if($rows[0]['id'] > 0){
            return app::get('base')->model('kvstore')->update($data, array('id'=>$rows[0]['id']));
        }else{
            return app::get('base')->model('kvstore')->insert($data);
        }
    }//End Function

    public function fetch($key, &$value, $timeout_version=null) 
    {
        $rows = kernel::database()->select(
            sprintf("SELECT * FROM `sdb_base_kvstore` WHERE `prefix` = %s AND `key` = %s", kernel::database()->quote($this->prefix), kernel::database()->quote($key)),
            true
        );
        if($rows[0]['id'] > 0 && $timeout_version < $rows[0]['dateline']){
            if($rows[0]['ttl'] > 0 && ($rows[0]['dateline']+$rows[0]['ttl']) < time()){
                return false;
            }
            $value = unserialize($rows[0]['value']);
            return true;
        }
        return false;
    }//End Function

    public function delete($key) 
    {
        return app::get('base')->model('kvstore')->delete(array('prefix'=>$this->prefix, 'key'=>$key));
    }//End Function

    public function recovery($record) 
    {
        return false;
    }//End Function
}//End Class
