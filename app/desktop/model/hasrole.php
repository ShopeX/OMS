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
 
class desktop_mdl_hasrole extends dbeav_model{

    function update($data,$filter=array(),$mustUpdate = null){
        $c = parent::update($data,$filter);

        if($filter['role_id']){
            $role_id = array();
            foreach($this->getList('role_id',$filter) as $r){
                $role_id[] = $r['role_id'];
            }
        }else{
            $role_id = $filter['role_id'];
        }

        if(count($role_id)==1){
            $rows = $this->db->select('select action_id from sdb_base_lnk_acts where role_id in ('.implode(',',$role_id).')');
            $in_db = array();
            foreach($rows as $r){
                $in_db[] = $r['action_id'];
            }
            $data['actions'] = $data['actions']?$data['actions']:array();
            $to_add = array_diff($data['actions'],$in_db);
            $to_del = array_diff($in_db,$data['actions']);

            if(count($to_add)>0){
                $sql = 'INSERT INTO `sdb_base_lnk_acts` (`role_id`,`action_id`) VALUES ';
                foreach($to_add as $action_id){
                    $actions[] = "({$role_id[0]},$action_id)";
                }
                $sql .= implode($actions,',').';';
                $a = $this->db->exec($sql);
            }

            if(count($to_del)>0){
                $this->db->exec('delete from sdb_base_lnk_acts where action_id in ('.implode(',',$to_del).') and role_id='.intval($role_id[0]));
            }
        }else{

        }

        return $c;
    }

    function insert(&$data){
        $role_id = parent::insert($data);
        if($role_id && is_array($data['actions'])){
            $sql = 'INSERT INTO `sdb_base_lnk_acts` (`role_id`,`action_id`) VALUES ';
            foreach($data['actions'] as $action_id){
                $actions[] = "($role_id,$action_id)";
            }
            $sql .= implode($actions,',').';';
            $a = $this->db->exec($sql);
        }
        return $role_id;
    }

}
?>
