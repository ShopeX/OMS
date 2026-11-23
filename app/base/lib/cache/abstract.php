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
abstract class base_cache_abstract 
{

    /*
     * @var string $_vary_list
     * @access protected
     */
    protected $_vary_list;

    /*
     * 检查是否取得影响缓存的相关数据，如果无则获取一次
     * @access public
     * @return void
     */
    protected function check_vary_list() 
    {
        if(!isset($this->_vary_list)){
            $this->_vary_list = cachemgr::fetch_vary_list();
        }
    }//End Function

    /*
     * 取得数据
     * @var string $type
     * @var string $key
     * @access public
     * @return mixed
     */
    public function get_modified($type, $key) 
    {
        return $this->_vary_list[strtoupper($type)][strtoupper($key)];
    }//End Function

    /*
     * 设置数据
     * @var string $type
     * @var string $key
     * @var int $time
     * @access public
     * @return boolean
     */
    public function set_modified($type, $key, $time=0) 
    {
        $now = ($time>0) ? $time : time();
        if(is_array($key)){
            foreach($key as $k){
                $this->_vary_list[strtoupper($type)][strtoupper($k)] = $now;
            }
        }else{
            $this->_vary_list[strtoupper($type)][strtoupper($key)] = $now;
        }
        return true;
    }//End Function

    /**
     * 是否支持同步的自增单号处理
     */
    public function supportUUID() {

        return false;
    }

    /**
     * 返回类型值
     */
    public function getUUIDFix() {

        return '2';
    }
}//End Class