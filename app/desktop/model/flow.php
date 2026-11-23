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
 
class desktop_mdl_flow extends dbeav_model{

    var $pageLimit = 20;

    function fetch_role_flow(&$user){
        $user->get_conf('last_fetch_id',$last_fetch_id);
        $last_fetch_id = intval($last_fetch_id);
        $rs = $this->db->exec('insert into sdb_desktop_user_flow (flow_id,user_id) select flow_id,'
                        .$user->get_id().' from sdb_desktop_role_flow where flow_id>'
                        .$last_fetch_id.' and role_id in ('.implode(',',$user->has_roles()).') limit '.$this->pageLimit);
        $affect_row = $this->db->affect_row();
        if($affect_row){
            $row = $this->db->selectrow('select max(flow_id) as flow_id from sdb_desktop_flow');
            user::set_conf('last_fetch_id',$row['flow_id']);
        }
        return $affect_row;
    }

    function list_flow(&$user,$type='unread',$cursor_flow_id=null){

        if($type=='unread'){
            $this->fetch_role_flow($user);
        }

        $where = array(
                'unread'=>'o.unread="true"',
                'read'=>'o.unread="false"',
                'starred'=>'o.has_star="true"',
            );

        $s='SELECT m.flow_id,m.flow_type,m.send_time,m.subject,m.flow_desc,o.unread,o.note,o.has_star FROM sdb_desktop_user_flow o
                                left join sdb_desktop_flow m on o.flow_id=m.flow_id
                                where o.user_id='.$user->get_id().' and '.$where[$type].' order by flow_id desc limit '.$this->pageLimit;

        return $this->db->select($s);

    }

    function write_role($roles,$flow,$fetch_once=false){

        if(!is_array($roles)) $roles = array($roles);

        $role_flow = $this->app->model('role_flow');
        $flow['send_time'] = time();
        $flow['send_mode'] = $fetch_once?'fetch':'broadcast';
        if($this->insert($flow)){
            $flow_id = $this->db->lastinsertid();
            foreach($roles as $role_id){
                $data = array('role_id'=>$role_id,'flow_id'=>$flow_id);
                $role_flow->insert($data);
            }
            return true;
        }else{
            trigger_error($this->db->errorinfo(),E_USER_ERROR);
            return false;
        }
    }

    function write_op($user_id,$flow){
        $op_flow = $this->app->model('op_flow');
        $flow['send_time'] = time();
        $flow['direct'] = 'broadcast';
        if($this->insert($flow)){
            $flow_id = $this->db->lastinsertid();
            $data = array(
                'flow_id'=>$flow_id,
                'user_id'=>$user_id,
                );
            return $op_flow->insert($data);
        }else{
            trigger_error($this->db->errorinfo(),E_USER_ERROR);
            return false;
        }
    }

    function mark_read($flow_id){

        if(!is_array($flow_id))$flow_id = array($flow_id);

        $this->db->exec('delete r.* from sdb_desktop_role_flow r
                            left join sdb_desktop_flow m on m.flow_id = r.flow_id
                            where r.flow_id in ('.implode(',',$flow_id).') and r.role_id in ('
                            .implode(',',$this->system->op_has_roles).') and m.send_mode="fetch"');

        $this->db->exec('update sdb_desktop_user_flow set unread="false" where user_id='
        .$this->system->user_id.' and flow_id in('.implode(',',$flow_id).')');
    }
    
}
