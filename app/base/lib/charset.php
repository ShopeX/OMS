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
 
class base_charset{
    
    private $_instance = null;

    function __construct() 
    {
        $obj = kernel::service('base_charset');
        if($obj instanceof base_charset_interface){ 
            $this->set_instance($obj);
        }
    }//End Function

    public function set_instance(&$obj) 
    {
        $this->_instance = $obj;
    }//End Function
    
    public function get_instance() 
    {
        return $this->_instance;
    }//End Function

    public function local2utf($strFrom,$charset='zh') 
    {
        return $this->_instance->local2utf($strFrom, $charset);
    }//End Function

    public function utf2local($strFrom,$charset='zh') 
    {
        return $this->_instance->utf2local($strFrom, $charset);
    }//End Function

    public function u2utf8($str) 
    {
        return $this->_instance->u2utf8($str);
    }//End Function

    public function utf82u($str) 
    {
        return $this->_instance->utf82u($str);
    }//End Function
	
	public function replace_utf8bom( $str )  
	{
		return $this->_instance->replace_utf8bom($str);
	}
	
	public function is_utf8( $str )
	{
		return $this->_instance->is_utf8($str);
	}
}
