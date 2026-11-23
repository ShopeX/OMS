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
 

class desktop_mdl_tag extends dbeav_model{
	
    function __construct($app){
        parent::__construct($app);
        //使用meta系统进行存储
        $this->use_meta();
    }
    
    var $defaultOrder = array('tag_id',' DESC');

    function save( &$item, $mustUpdate = null ){
        $list = parent::getList('*',array('tag_name'=>$item['tag_name'],'tag_type'=>$item['tag_type'],'app_id'=>$item['app_id']));
        if($list && count($list)>0){
            $item['tag_id'] = $list[0]['tag_id'];
        } 
	    if($item['tag_bgcolor'])
      		$item['tag_fgcolor'] = '#'.$this->fgcolor(substr($item['tag_bgcolor'],1));
        parent::save($item, $mustUpdate);
    }
    function check_tag($item){
        return parent::count(array('tag_name'=>$item['tag_name'],'tag_type'=>$item['tag_type'],'app_id'=>$item['app_id']));
    }
    function fgcolor($rgb){ 
        return (hexdec($rgb[0].$rgb[1])*14+hexdec($rgb[2].$rgb[3])*90+hexdec($rgb[4].$rgb[5])*14)>(30090/2)?'000000':'ffffff';
    }
    function modifier_tag_bgcolor($value){
        return "<span style=\"background-color: $value;\" class=\"tag-label\">&nbsp;&nbsp;&nbsp;&nbsp;</span>";
    }

    function modifier_tag_fgcolor($value){
        return "<span style=\"background-color: $value;\" class=\"tag-label\">&nbsp;&nbsp;&nbsp;&nbsp;</span>";
    }
    function &tagList($type,$count=false,$joinTable=null,$obj_id=null,$data=array(),$where=false){
            
        if($count){
            if($joinTable && $obj_id){
                if(!$where){
                    $sql = "select t.tag_id,t.tag_name,t.tag_type,count(o.{$obj_id}) as rel_count,$obj_id as ss,t.is_system
                     FROM sdb_base_tag t
                     LEFT JOIN sdb_base_tag_rel r ON r.tag_id=t.tag_id
                     LEFT JOIN {$joinTable} o ON r.rel_id=o.{$obj_id} and o.disabled!='true'
                     where tag_type='$type' group by t.tag_id";
                }else{
                    $sql = "select $obj_id as trel_id
                     FROM sdb_base_tag_rel r
                     LEFT JOIN {$joinTable} o ON r.rel_id=o.{$obj_id} and o.disabled!='true'
                     where r.tag_id = {$where}";
                }
            }else{
                $sql = "select t.tag_id,t.tag_name,t.tag_type,count(r.rel_id) as rel_count,t.is_system FROM sdb_base_tag t LEFT JOIN sdb_base_tag_rel r ON r.tag_id=t.tag_id where tag_type='$type' group by t.tag_id";
            }
        }else{
            $sql = "select * FROM sdb_base_tag where tag_type='$type'";
        }
        $aRet = $this->db->select($sql);
        if($where){
            return      $aRet;
        }
        if($data){
            $tagList=$this->getSelctedTagList($type,$data);
        }
        foreach($aRet as $key=>$value){
            if($tagList[$aRet[$key]['tag_id']]){
                $aRet[$key]['status']=$tagList[$aRet[$key]['tag_id']];
            }else{
                $aRet[$key]['status']='none';
            }
        }
        return $aRet;
    }
}
