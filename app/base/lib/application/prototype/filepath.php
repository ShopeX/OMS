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
 
class base_application_prototype_filepath extends base_application_prototype_content{

    var $current;
    var $path;
    private $_mtime = 0;
    protected $_hadFileName = array();

    function init_iterator(){
        $this->_hadFileName = array();
        if(is_dir($this->target_app->app_dir.'/'.$this->path)){
            $appendIterator = new AppendIterator();
            if(defined('CUSTOM_CORE_DIR') && is_dir(CUSTOM_CORE_DIR.'/'.$this->target_app->app_id.'/'.$this->path)){
                 $this->_mtime = filemtime(CUSTOM_CORE_DIR.'/'.$this->target_app->app_id.'/'.$this->path);
                 $cdi = new DirectoryIterator(CUSTOM_CORE_DIR.'/'.$this->target_app->app_id.'/'.$this->path);
                 $appendIterator->append($cdi);
            }else{
                 $this->_mtime = filemtime($this->target_app->app_dir.'/'.$this->path);
            }
            $di = new DirectoryIterator($this->target_app->app_dir.'/'.$this->path);
            $appendIterator->append($di);
            return $appendIterator;
        }else{
            return new ArrayIterator(array());
        }
    }

    public function getPathname(){
        return $this->iterator()->getPathname();
    }

    public function current() {
        $this->key = $this->iterator()->getFilename();
        return $this;
    }

    function prototype_filter(){
        $filename = $this->iterator()->getFilename();
        if(in_array($filename, $this->_hadFileName)) {
            return false;
        }
        if($filename[0]=='.'){
            return false;
        }else{
            $this->_hadFileName[] = $filename;
            return $this->filter();
        }
    }
    
    function last_modified($app_id){
        $info_arr = array();
        foreach($this->detect($app_id) as $item){
            //$modified = max($modified,filemtime($this->getPathname()));
            //todo: md5
            $filename = $this->getPathname();
            if(is_dir($filename)){
                foreach(utils::tree($filename) AS $k=>$v){
					if (is_dir($v)) continue;
                    $info_arr[$v] = md5_file($v);
                }
            }else{
                $info_arr[$filename] = md5_file($filename);
            }
        }
        ksort($info_arr);
        return md5(serialize($info_arr));
    }



}
