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
 * dbeav_meta_register
 * metaע
 *
 * @uses modelFactory
 * @package
 * @version $Id$
 * @copyright 2003-2007 ShopEx
 * @author Ken <ken@shopex.cn>
 * @license Commercial
 */
class dbeav_mdl_meta_register extends base_db_model{
        
    function register($table,$id_column,$column){
        list($col_name,$col_desc) = each($column);
        $this->table = $table;
        $this->col_name = $col_name;    
        $this->col_desc = $col_desc;
        if($this->is_valid()){
            $entry = array(
                'tbl_name'=>$table,
                'pk_name'=>$id_column,
                'col_name'=>$col_name,
                'col_type'=>$this->get_col_type(),
                'col_desc'=>$col_desc,
                    );
            $this->insert($entry);
            return true;
        }else{
            return false;
        }
    }


    function drop( $table, $col ){
        $mrId = kernel::database()->selectrow('SELECT mr_id FROM sdb_dbeav_meta_register WHERE tbl_name = "'.$table.'" AND col_name = "'.$col.'"');
        if( !$mrId )
            return true;
        $mrId = $mrId['mr_id'];
        $this->delete( array('mr_id'=>$mrId) );
        return kernel::database()->exec('DELETE FROM sdb_dbeav_meta_register WHERE mr_id = '.$mrId);
    }
 
    private function is_valid(){
        if(!is_string($this->col_name) && !is_array($this->col_desc)){
            return false;   
        }
        $sql = "select count(*) as c from sdb_dbeav_meta_register where tbl_name='".$this->table."' and col_name='".$this->col_name."'" ;
        $ret = $this->db->select($sql);
        if(intval($ret[0]['c']) > 0){
            return false;
        }
        return true;
    }
    
    private function get_col_type(){
        $col_type = $this->col_desc['type'];
        if(in_array($col_type,array('time','cdate','last_modify','number')) || strstr($col_type,'int') ){
            return  'int';
        }
        if( in_array($col_type,array('money')) || strstr($col_type,'decimal') ){                
            return 'decimal';
        }
        if( strstr($col_type,'datetime') ){                
            return 'datetime';
        }
        if( strstr($col_type,'denum') ){                
            return 'denum';
        }
        if( strstr($col_type,'text') ){                
            return  'text';
        }
        if( in_array($col_type,array('serialize')) ){
            return 'longtext';
        }
        
        return  'varchar';
    }
    
    static function get_meta_column($tbl_name){
        $sql = "select col_name,col_desc from sdb_meta_refs where tbl_name='{$tbl_name}'";
        $rows = $this->db->select($sql);
        foreach($rows as $row){
            $meta['metaColumn'][] = $row['col_name'];
            $meta['columns'][$row['col_name']] = ($row['col_desc']);
        }
        return $meta;
    }

}
