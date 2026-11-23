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
 * @2016-12-23
 * @mdl_user_logs
 */
 
class desktop_mdl_user_logs extends dbeav_model{
    
    //1-添加用户
    public function addUser($user){
        unset($user['re_password']);

        $op_id = kernel::single('desktop_user')->get_id();
        $op_name = kernel::single('desktop_user')->get_name();

        $data = array(
            'obj_name'         => $user['name'],                                        # 被操作人姓名
            'obj_id'           => $user['user_id'],                                     # 被操作人ID
            'op_id'            => $op_id ? $op_id : 16777215,                          # 操作人ID
            'op_name'          => $op_name ? $op_name : 'system',                       # 操作人姓名
            'operation_time'   => time(),                                               # 操作时间
            'operation_type'   => 1,                                                    # 操作类型
            'operation_detail' => serialize($user),                         # 快照
            'ip'               => kernel::single('base_request')->get_remote_addr(),    # 远程ID
        );
        $this->insert($data);
    }
    
    //2-信息编辑
    public function userEdit($user){
        $op_id = kernel::single('desktop_user')->get_id();
        $op_name = kernel::single('desktop_user')->get_name();

        $data = array(
            'obj_name'         => $user['name'],                                        # 被操作人姓名
            'obj_id'           => $user['account_id'],                                     # 被操作人ID
            'op_id'            => $op_id ? $op_id : 16777215,                          # 操作人ID
            'op_name'          => $op_name ? $op_name : 'system',                       # 操作人姓名
            'operation_time'   => time(),                                               # 操作时间
            'operation_type'   => 2,                                                    # 操作类型
            'operation_detail' => serialize($user),
            'ip'               => kernel::single('base_request')->get_remote_addr(),    # 远程ID
        );
        $this->insert($data);
    }
    
    //3-删除用户
    public function deleteUser($users){
        $strFields = array('obj_name','obj_id','op_id','op_name','operation_time','operation_type','ip');
        $strValues = array();
        
        $op_id = kernel::single('desktop_user')->get_id();
        $op_name = kernel::single('desktop_user')->get_name();
        
        foreach ($users as $user) {
            $v = array(
                $this->db->quote($user['name']),
                $this->db->quote($user['user_id']),
                $this->db->quote($op_id ? $op_id : 16777215),
                $this->db->quote($op_name ? $op_name : 'system'),
                time(),
                3,
                $this->db->quote(kernel::single('base_request')->get_remote_addr()),

            );

            $strValues[] = implode(',',$v);
        }

        $sql = sprintf('INSERT INTO `%s`(`%s`) VALUES (%s)',$this->table_name(true),implode('`,`',$strFields),implode('),(',$strValues));

        $this->db->exec($sql);
    }
    
    //4-修改密码
    public function changePwd($user_id){
        $user = $this->app->model('users')->dump($user_id,'name,user_id');

        $op_id = kernel::single('desktop_user')->get_id();
        $op_name = kernel::single('desktop_user')->get_name();

        $data = array(
            'obj_name'         => $user['name'],                                        # 被操作人姓名
            'obj_id'           => $user['user_id'],                                     # 被操作人ID
            'op_id'            => $op_id ? $op_id : 16777215,                          # 操作人ID
            'op_name'          => $op_name ? $op_name : 'system',                       # 操作人姓名
            'operation_time'   => time(),                                               # 操作时间
            'operation_type'   => 4,                                                    # 操作类型
            'operation_detail' => serialize(array('pam_account'=>array('login_password'=>$_POST['pam_account']['login_password']))),
            'ip'               => kernel::single('base_request')->get_remote_addr(),    # 远程ID
        );
        $this->insert($data);
    }
}
