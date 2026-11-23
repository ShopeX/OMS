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
 

class base_storager{

    function __construct($prefix = ''){
        $this->base_url = kernel::base_url('full').'/';
        if(!defined('FILE_STORAGER'))define('FILE_STORAGER','filesystem');
        $this->class_name = 'base_storage_'.FILE_STORAGER;
        $this->worker = new $this->class_name($prefix);
        if(defined('HOST_MIRRORS')){
            $host_mirrors = preg_split('/[,\s]+/',constant('HOST_MIRRORS'));
            if(is_array($host_mirrors) && isset($host_mirrors[0])){
                $this->host_mirrors = &$host_mirrors;
                $this->host_mirrors_count = count($host_mirrors)-1;
            }
        }
    }

    function &parse($ident){
        $ret = array();
        if(!$ident){
            return false;
        }elseif(list($ret['url'],$ret['id'],$ret['storager']) = explode('|',$ident)){
            return $ret;
        }else{
            $ret['url'] = &$ident;
            return $ret;
        }
    }

    function save($file,$type=null,$addons=''){
        if($addons){
            if(!is_array($addons)){
                $addons = array($addons);
            }
        }else{
            $addons = array();
        }

        if($id = $this->worker->save($file,$url,$type,$addons)){
            return $url.'|'.$id.'|'.$this->class_name;
        }else{
            return false;
        }
    }

    /*function __check_upload($file){
        switch($file['error']){
            case 1:
            return __('洗募小朔目占小');
            break;

            case 2:
            return __('洗募小');
            break;

            case 3:
            return __('募霾糠直洗');
            break;

            case 4:
            $msg=__('没业要洗募');
            break;

            case 5:
            return __('时募卸失');
            break;

            case 6:
            return __('募写氲绞蹦夹?);
            break;
        }
        return false;
    }*/


    function save_upload($file,$type='image',$addons='',&$msg,$ext_name=""){
       if(method_exists($this->worker,'save')){
            if($type=="file"){
                $ext_name = substr($file['name'],strrpos($file['name'],"."));
                $file = $file['tmp_name'];
            }
            $addons = array($file);
            if($id = $this->worker->save($file,$url,$type,$addons,$ext_name)){
                $ident_data = $url.'|'.$id.'|'.substr($this->class_name,strrpos($this->class_name,"_")+1);
                if($type=="file"){
                    $file_obj = app::get("base")->model("files");
                    $s_d = array("file_path"=>$ident_data,'file_type'=>$_POST['_f_type']);
                    $file_obj->save($s_d);
                    return $s_d['file_id'];
                }
                return $ident_data;
            }else{
                return false;
            }
       }
    }

    function replace($file,$ident,$type='image',$addons=''){
        if(method_exists($this->worker,'replace') && $ident){
            $data = $this->parse($ident);
            if($this->worker->replace($file,$data['id'])){
                return $ident;
            }else{
                return false;
            }
        }else{
            if($ident){
                $this->remove($ident);
            }
            return $this->save($file,$type,$addons);
        }
    }

    function remove($ident,$type='image'){
        $data = $this->parse($ident);
        if($type=="file"){
            $file_obj = app::get("base")->model("files");
            $s_d = array("file_path"=>$ident);
            $file_obj->delete($s_d);
        }
        return $this->worker->remove($data['id']);
    }

    function getFile($id){
        $file_obj = app::get("base")->model("files");
        $t_d = $file_obj->dump(array('file_id'=>$id));
        $ident = $t_d['file_path'];
        if($data = $this->parse($ident)){
            return $this->worker->getFile($data['id'],$t_d['file_type']);
        }else{
            return false;
        }
    }

    function getUrl($id){
        $file_obj = app::get("base")->model("files");
        $t_d = $file_obj->dump(array('file_id'=>$id));
        $ident = $t_d['file_path'];
        if($ident){
            $libs = array('http://'=>1,'https:/'=>1);
            $data = $this->parse($ident);
            if($this->host_mirrors){
                return $this->host_mirrors[rand(0,$this->host_mirrors_count)].'/'.$data['id'];
            }
            if(isset($libs[strtolower(substr($data['url'],0,7))])){
                return $data['url'];
            }else{
                return $this->base_url.$data['url'];
            }
            
        }else{
            return false;
        }
    }

    static private $registed = false;

    static function modifier($image_id,$size=''){
        if(isset($image_id[31]) && !isset($image_id[32])){
            return '%IMG_'.$image_id.'_S_'.$size.'_IMG%';
        }else{
            return $image_id;
        }
    }
    static function image_path($image_id,$size=''){
        $tmp = self::modifier($image_id,$size);
        return self::image_storage($tmp);
    }
    static function image_storage($content){
        $blocks = preg_split('/%IMG_([0-9a-f]{32})_S_([a-z0-9\:]*)_IMG%/'
                    ,$content,-1,PREG_SPLIT_DELIM_CAPTURE);

        $c = count($blocks);

        $imglib = array();
        $img = array();

        for($i=0;$i<$c;$i++){
            switch($i%3){
                case 1:
                   $image_id = $blocks[$i];
                   $img[$image_id][$i] = array($blocks[$i+1]);
                   $blocks[$i] = &$img[$image_id][$i][0];
                    break;
               case 2:
                   $img[$image_id][$i-1][1] = $blocks[$i];
                   $blocks[$i] = '';
                   break;
            }
        }

        if(defined('HOST_MIRRORS')){
            $host_mirrors = preg_split('/[,\s]+/',constant('HOST_MIRRORS'));
            if(is_array($host_mirrors) && isset($host_mirrors[0])){
                $host_mirrors_count = count($host_mirrors)-1;
            }
            $url = $host_mirrors[rand(0,$host_mirrors_count)].'/'.$url;
        }

        if($img){
            $db = &kernel::database();
            foreach($db->select($s='select image_id,url,s_url,m_url,l_url,last_modified,width,height from sdb_image_image where image_id in(\''.
                    implode("','",array_keys($img)).'\')') as $r){
                $imglib[$r['image_id']] = $r;
            }

            foreach($img as $image_id => $sizes){
                foreach($sizes as $i=>$item){
                    switch($item[0][0]){
                        case 's':
                            $url = $imglib[$image_id]['s_url']?$imglib[$image_id]['s_url']:$imglib[$image_id]['url'];
                            break;
                        case 'm':
                            $url = $imglib[$image_id]['m_url']?$imglib[$image_id]['m_url']:$imglib[$image_id]['url'];
                            break;
                        case 'l':
                            $url = $imglib[$image_id]['l_url']?$imglib[$image_id]['l_url']:$imglib[$image_id]['url'];
                            break;
                        default:
                            $url = $imglib[$image_id]['url'];
                            break;
                    }
                    if(!strpos($url,'://')){
                        if(is_array($host_mirrors)){
                            $url = $host_mirrors[rand(0,$host_mirrors_count)].'/'.$url;
                        }else{
                        	$image_url = defined('IMG_URL') ? IMG_URL : kernel::base_url(1);
                            $url = $image_url.'/'.$url;
                        }
                    }
                    $code = ($r['width']>$r['height'])?'w':'h';
                    $img[$image_id][$i][0] = $url;
                }
            }
        }

        return implode('',$blocks);
    }

}

