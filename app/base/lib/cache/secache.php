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
class base_cache_secache extends base_cache_secache_model implements base_interface_cache
{

    function __construct() 
    {
        $workat = DATA_DIR . '/cache';
        if(!is_dir($workat))    utils::mkdir_p($workat);        
        $this->workat($workat . '/secache');
        $this->check_vary_list();
    }//End Function

    public function status(&$curBytes,&$totalBytes) 
    {
        $data = parent::status($curBytes, $totalBytes);
        foreach($data AS $val){
            $status[$val['name']] = $val['value'];
        }
        //$status[app::get('base')->_('已使用缓存')] = $cur;
        $status['可使用缓存'] = $totalBytes;
        return $status;
    }//End Function
    
}//End Class
