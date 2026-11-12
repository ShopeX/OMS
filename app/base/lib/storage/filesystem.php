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


class base_storage_filesystem implements base_interface_storager{
    function __construct(){
        switch($_POST['_f_type']){
            case "public":
                $this->f_dir = PUBLIC_DIR.'/files';
                $this->base_dir = 'public/files';
                break;
            case "private":
                $this->f_dir = DATA_DIR.'/private';
                $this->base_dir = 'data/private';
                $this->is_private = true;
                break;
            default:
                $this->f_dir = PUBLIC_DIR.'/images';
                $this->base_dir = 'public/images';
        }
    }

    function save($file,&$url,$type,$addons,$ext_name=""){
        // CNVD upload RCE: never force executable .php for private storage.
        // Keep caller-provided extension; default to non-executable .dat when empty.
        if($this->is_private){
            if($ext_name === '' || $ext_name === null){
                $ext_name = '.dat';
            }
        }
        $id = $this->_get_ident($file,$type,$addons,$url,$path,$ext_name);
        if($path && copy($file,$path)){
            @chmod($path,0644);
            return $id;
        }else{
            if(!is_writeable($path)){
                echo '目录：'.$this->f_dir.' 不可写.';
            }
            return false;
        }
    }

    function replace($file,$id){
        $path = $this->f_dir.$id;
        $dir = dirname($path);

        if(file_exists($path)){
            if(!unlink($path)){
                return false;
            }
        }elseif(!is_dir($dir)){
            if(!$this->mkdir_p($dir)){
                return false;
            }
        }
        if($path && rename($file,$path)){
            return $id;
        }else{
            return false;
        }
    }


    function _get_ident($file,$type,$addons,&$url,&$path,$ext_name=""){
        $ident = md5(rand(0,9999).microtime());
        $dir = '/'.$ident[0].$ident[1].'/'.$ident[2].$ident[3].'/'.$ident[4].$ident[5].'/'.substr($ident,6);
        $addons = implode('-',$addons);
        $s_file = basename($addons?$addons:$file);
        $uri = $dir.substr(md5(($addons?$addons:$file).microtime()),0,6);
        if($ext_name) {
            if(strrpos($uri,".")) $uri = substr($uri,0,strrpos($uri,".")).$ext_name;
            else $uri .= $ext_name;
        }
        $path = $this->f_dir.$uri;
        $url = $this->base_dir.$uri;
        

        if(defined('HOST_MIRRORS')){
            $host_mirrors = preg_split('/[,\s]+/',constant('HOST_MIRRORS'));
            if(is_array($host_mirrors) && isset($host_mirrors[0])){
                $host_mirrors = &$host_mirrors;
                $host_mirrors_count = count($host_mirrors)-1;
                $url = $host_mirrors[rand(0,$host_mirrors_count)].'/'.$url;            
            }
        }
        if(file_exists($path) && !unlink($path)){
            return false;
        }

        $dir = dirname($path);
        if(!is_dir($dir)){
            if(!$this->mkdir_p($dir)){
                return false;
            }
        }

        return $uri;
    }

    function remove($id){
        if($id && file_exists($this->f_dir.$id)){
            return @unlink($this->f_dir.$id);
         }else{
            return true;
        }
    }

    function getFile($id,$type){
        if($id && file_exists($this->f_dir.$id)){
            return $this->f_dir.$id;
         }else{
            return false;
        }
    }
    
    function mkdir_p($dir,$dirmode=0755){
        $path = explode('/',str_replace('\\','/',$dir));
        $depth = count($path);
        for($i=$depth;$i>0;$i--){
            if(file_exists(implode('/',array_slice($path,0,$i)))){
                break;
            }
        }
        for($i;$i<$depth;$i++){
            if($d= implode('/',array_slice($path,0,$i+1))){
                mkdir($d,$dirmode);
            }
        }
        return is_dir($dir);
    }


    /*function store($file,$ident,$size=''){
        $ident = $ident[0].$ident{1}.'/'.$ident{2}.$ident{3}.'/'.$ident{4}.$ident{5}.'/'.substr($ident,6);

        if($size){
            $ident = substr($ident,0,-10).$size.substr($ident,-4,4);
        }

        $filename = $this->f_dir.'/'.$ident;

        $dir = dirname($filename);
        if(!is_dir($dir)){
            utils::mkdir_p($dir);
        }
        if(file_exists($filename)){
            unlink($filename);
        }
        rename($file,$filename);
        return array($ident,'images/'.$ident);
    }

    function delete($ident){
        unlink($this->f_dir.'/'.$ident);
        foreach(array('L','M','S') as $size){
            unlink($this->f_dir.'/'.substr($ident,0,-10).$size.substr($ident,-4,4));
        }
    }

    function fetch($ident){
        return $this->f_dir.'/'.$ident;
    }
    */
}
