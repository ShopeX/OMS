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
 
/**
 * dbeav_metavalue
 * Metaֵ
 *
 * @version $Id$
 * @copyright 2003-2007 ShopEx
 * @author Ken <ken@shopex.cn>
 * @license Commercial
 */
class dbeav_metavalue {    
    
    function __construct(){
        $this->db =  kernel::database();  
        $this->table = "sdb_".str_replace('mdl_','',get_class($this));
    }
    
    function insert($data){
        if($this->table == "sdb_dbeav_meta_value_int" && $data['value'] ==="") return;// $data['value'] =0;
        if($this->table == "sdb_dbeav_meta_value_decimal" && $data['value'] ==="") return ;// $data['value'] ='0.0000';
        $rs = $this->db->exec('select * from '.$this->table.' where 0=1');
        $sql = base_db_tools::getInsertSQL($rs,$data);
        $this->db->exec($sql);            
    }
    
        /**
     * select 
     * עidֵmetaֵе
     * @param int $mr_id 
     * @param array $pk 
     * @access public
     * @return array
     */    
    function select($mr_id,$pk){
        $sql = "
        SELECT r.tbl_name,r.col_name,v.pk,v.value 
        FROM ".$this->table." v 
        LEFT JOIN sdb_dbeav_meta_register r 
        ON v.mr_id=r.mr_id  
        WHERE v.mr_id='".$mr_id."' 
        AND v.pk in (".implode(',',$pk).")
        ";
        $rows = $this->db->select($sql);
        foreach($rows as $row){
            $ret[$row['pk']] = array($row['col_name']=>$row['value']); 
        }
        return $ret;
    }
    
    function delete($pk,$mr_id = null){
        //清除数据 数据为空时追加
        if( !$pk ) return false;
        
        $sql = "
        DELETE 
        FROM ".$this->table." 
        WHERE pk 
        IN (".implode(',',(array)$pk).")
        ".( $mr_id?' AND mr_id IN ('.implode(',',(array)$mr_id).') ':'' );
        $this->db->exec($sql);    
    }
    
    function update($value,$pk,$mr_id){
        if($this->table == "sdb_dbeav_meta_value_int" && $value ==="")$value=null;// $value =0;
        if($this->table == "sdb_dbeav_meta_value_decimal" && $value ==="")$value=null;// $value ='0.0000';
        if( isset( $value ) ){
            $pk_id = $pk[0];
            $aSql = "SELECT * FROM ".$this->table." WHERE pk = ".$pk_id ." AND mr_id = ".$mr_id;
            $result = $this->db->select($aSql);
            if($result){
                $sql = "
                UPDATE ".$this->table."
                SET value='".$value."'
                WHERE pk
                IN (".implode(',',$pk).") AND mr_id = ".$mr_id;
            }else{
                $sql = "INSERT INTO ".$this->table."(mr_id,pk,value) VALUES('$mr_id','$pk_id','$value')";
            }
            $this->db->exec($sql);
       }else{
            $this->delete( $pk,$mr_id );
        }
    }
    
    function get_pk($value){
        $sql = "
        SELECT pk
        FROM ".$this->table."
        WHERE value='".$value."'
        ";
        $rows = $this->db->select($sql);
        foreach($rows as $row){
            $ret[] = $row['pk'];
        }
        return $ret;
    }
    


}
