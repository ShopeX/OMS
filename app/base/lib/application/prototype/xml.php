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
class base_application_prototype_xml extends base_application_prototype_content{

    var $current;
    var $xml;
    var $xsd;
    var $path;
    static $__appinfo;

    public function init_iterator() {
        if(defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR.'/'.$this->target_app->app_id.'/'.$this->xml)){
            $ident = $this->target_app->app_id.'-'.$this->xml;
            if(!isset(self::$__appinfo[$ident])){
                $custom_file_content = file_get_contents(CUSTOM_CORE_DIR.'/'.$this->target_app->app_id.'/'.$this->xml);
                self::$__appinfo[$ident] = kernel::single('base_xml')->xml2array($custom_file_content,$this->xsd);
            }            
            eval('$array = &self::$__appinfo[$ident]["'.str_replace('/','"]["',$this->path).'"];');
        }elseif(file_exists($this->target_app->app_dir.'/'.$this->xml)){
            $ident = $this->target_app->app_id.'-'.$this->xml;
            if(!isset(self::$__appinfo[$ident])){
                $file_content = file_get_contents($this->target_app->app_dir.'/'.$this->xml);
                self::$__appinfo[$ident] = kernel::single('base_xml')->xml2array($file_content,$this->xsd);
            }
            
            eval('$array = &self::$__appinfo[$ident]["'.str_replace('/','"]["',$this->path).'"];');
        }else{
            $array = array();
        }
        return new ArrayIterator((array)$array);
    }
    
    function last_modified($app_id){
        if(defined('CUSTOM_CORE_DIR') && file_exists(CUSTOM_CORE_DIR.'/'.app::get($app_id)->app_id.'/'.$this->xml)){
            $file = CUSTOM_CORE_DIR.'/'.app::get($app_id)->app_id.'/'.$this->xml;
        }else{
            $file = app::get($app_id)->app_dir.'/'.$this->xml;
        }
        
        if(file_exists($file)){
            //return filemtime($file);
            //todo: md5
            return md5_file($file);
        }else{
            return false;
        }
    }

}
