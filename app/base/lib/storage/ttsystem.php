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
 
class base_storage_ttsystem implements base_interface_storager{

    function __construct(){
        $this->memcache=new Memcache;
        $host_mirrors = preg_split('/[,\s]+/',constant('STORAGE_MEMCACHED'));
        if(is_array($host_mirrors) && isset($host_mirrors[0])){
            foreach($host_mirrors as $k =>$v){
                list($host,$port) = explode(":",$v);
                $this->memcache->addServer($host,$port);
            }
        }
    }

    function save($file,&$url,$type,$addons,$ext_name=""){
        $id = $this->_get_ident($file,$type,$addons,$url,$path,$ext_name);
        if($path && $this->memcache->set($path,file_get_contents($file))){
            return $id;
        }else{
            return false;
        }
    }

    function replace($file,$id){
        if($this->memcache->set($id,file_get_contents($file))){
            return $id;
        }else{
            return false;
        }
    }

    function _get_ident($file,$type,$addons,&$url,&$path,$ext_name){    
        $path = $this->_ident($id).$ext_name;
        $url = STORAGE_HOST.$path;
        return $path;
    }


    function remove($id){
        if($id){
            return $this->memcache->delete($id,10);
        }else{
            return true;
        }
    }

    function _ident($id){
        return '/'.md5(microtime().base_certificate::get()).$id;
    }

    function getFile($id,$type){
        if($type=='public'){
            $f_dir = DATA_DIR.'/public'; 
        }else{
            $f_dir = DATA_DIR.'/private'; 
        }
        $tmpfile = tempnam($f_dir);
        if($id && file_put_contents($tmpfile,$this->memcache->get($id))){
            return $tmpfile;
        }else{
            return true;
        }
    }
}
