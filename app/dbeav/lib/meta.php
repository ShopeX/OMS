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
 * dbeav_meta
 * metaֵ
 *
 * @uses modelFactory
 * @package
 * @version $Id$
 * @copyright 2003-2007 ShopEx
 * @author Ever <ever@shopex.cn>
 * @license Commercial
 */
class dbeav_meta {    
    
    function __construct($table,$column,$has_pk=true){
        $sql = "SELECT * FROM sdb_dbeav_meta_register WHERE tbl_name='".$table."' AND col_name='".$column."'";
        $rows =  kernel::database()->select($sql);
        $rows[0]['col_desc'] = unserialize($rows[0]['col_desc']);
        $this->table = $table;
        $this->column = $column;
        $this->mr_id = $rows[0]['mr_id'];
        $this->mr_info = $rows[0];
        $this->value_type = "meta_value_".$rows[0]['col_type'];
        $this->value =  app::get('dbeav')->model($this->value_type);
        $this->pk_name = $rows[0]['pk_name'];
        $this->has_pk = $has_pk;       
    }
    
    function insert($data){
        $data['mr_id'] = $this->mr_id;
        return $this->value->insert($data);
    }
    
    function select(&$data){
        foreach($data as $row){
            $pk[] = $row[$this->pk_name];
        }
        $metarows = $this->value->select($this->mr_id,$pk);
        #кϲ
        foreach($data as $dkey=>$drow){
            $pk_id = $drow[$this->pk_name];
            if(!$metarows[$pk_id]){
                $metarows[$pk_id] = array($this->column=>NULL);
            }else{
                switch(strtolower($this->mr_info['col_desc']['type']))
                {
                    case 'serialize':
                        if(($meta_value_tmp=unserialize($metarows[$pk_id][$this->column])) !== false){
                            $metarows[$pk_id][$this->column] = $meta_value_tmp;
                        }
                        break;
                    default:   
                }//End Switch
            }
            $drow = array_merge($drow,$metarows[$pk_id]);
            #ҪеĲѯҪȥеֵ
            if(!$this->has_pk){
                unset($drow[$this->pk_name]);
            }
            $data[$dkey] = $drow;
        }      
         return true;
    }
    
    function update($value,$pk){
        $this->value->update($value,$pk,$this->mr_id);
    }

    function delete($pk){
        $this->value->delete($pk);
    }
   
    function filter($filter){
        $value = $filter[$this->column];
        $pk = $this->value->get_pk($value);
        if(!is_array($pk)) return " AND 0 ";
        $ret = " AND ".$this->pk_name." IN (".implode(',',$pk).")";
        return $ret;
    }
    
    static function get_meta_column($tbl_name){
        $sql = "select col_name,col_desc from sdb_dbeav_meta_register where tbl_name='{$tbl_name}'";
        $rows = kernel::database()->select($sql);
        foreach($rows as $row){
            $meta['metaColumn'][] = $row['col_name'];
            $meta['columns'][$row['col_name']] = unserialize($row['col_desc']);
        }
        
        return $meta;
    }
    


}
