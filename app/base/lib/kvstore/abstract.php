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
abstract class base_kvstore_abstract 
{
    
    /*
     * 生成经过处理的唯一key
     * @var string $key
     * @access public
     * @return string
     */
    public function create_key($key) 
    {
        return md5(base_kvstore::kvprefix() . $this->prefix . $key);
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

        return '1';
    }
}//End Class