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
 
class base_storage_ttprosystem implements base_interface_storager{

    function base_storage_ttprosystem(){
        $this->memcache=new Memcache;
        $host_mirrors = preg_split('/[,\s]+/',constant('STORAGE_MEMCACHED'));
        if(is_array($host_mirrors) && isset($host_mirrors[0])){
            foreach($host_mirrors as $k =>$v){
                list($host,$port) = explode(":",$v);
                $this->memcache->addServer($host,$port);
            }
        }
        if(defined('HOST_MIRRORS')){
            $host_mirrors = preg_split('/[,\s]+/',constant('HOST_MIRRORS'));
            if(is_array($host_mirrors) && isset($host_mirrors[0])){
                $this->host_mirrors = &$host_mirrors;
                $this->host_mirrors_count = count($host_mirrors)-1;
            }
        }
    }

    function save($file,&$url,$type,$addons,$ext_name=""){
        if($type=='public'){
            $base_dir = '/public/files'; 
        }elseif($type=='private'){
            $base_dir = '/data/private'; 
        }else{
            $base_dir = '/public/images';
        }
        $this->_get_ident($type,$url,$ident,$ext_name,$base_dir);
        $mkey = $base_dir.$ident;
        if($ident && $this->memcache->set($mkey,file_get_contents($file),0,0)){
            return $ident;
        }else{
            return false;
        }
    }

    function replace($file,$id){
        if($this->memcache->set($id,file_get_contents($file),0,0)){
            return $id;
        }else{
            return false;
        }
    }

    function _get_ident($type,&$url,&$ident,$ext_name,$base_dir){    
        $ident = $this->_ident().$ext_name;
        if($this->host_mirrors){
            $url = $this->host_mirrors[rand(0,$this->host_mirrors_count)].$base_dir.$ident;
        }
        return $ident;
    }


    function remove($id){
        if($id){
            return $this->memcache->delete($id,10);
        }else{
            return true;
        }
    }

    function _ident(){
        $id = md5(microtime().base_certificate::get());
        $id = '/'.substr($id,0,2).'/'.substr($id,2,2).'/'.$id;
        return $id;
    }

    function getFile($id,$type){
        $tmpfile = tempnam('/tmp','ttprosystem');
        if($id && file_put_contents($tmpfile,$this->memcache->get($id))){
            return $tmpfile;
        }else{
            return true;
        }
    }
}
