<?php
/**
 * 审批流记录业务逻辑类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_workflow_record extends ticket_abstract {
    /**
     * 验证数据
     *
     * @param array $params
     * @return array
     */
    public function checkParams(&$params)
    {
        $mdl = app::get('ticket')->model('workflow_record');
        
        // check case_id
        if(empty($params['case_id'])){
            $error_msg = '审批流案例ID必须填写';
            return $this->error($error_msg);
        }

        // check node_id
        if(empty($params['node_id'])){
            $error_msg = '节点ID必须填写';
            return $this->error($error_msg);
        }
        
        // check action
        if(empty($params['action'])){
            $error_msg = '操作类型必须填写';
            return $this->error($error_msg);
        }
        
        // check action values
        $validActions = array('approved', 'rejected', 'forward', 'cc');
        if(!in_array($params['action'], $validActions)){
            $error_msg = '操作类型必须是：approved(同意)、rejected(拒绝)、forward(转发)';
            return $this->error($error_msg);
        }
        
        // check status
        if(empty($params['status'])){
            $error_msg = '状态必须填写';
            return $this->error($error_msg);
        }
        
        // check status values
        $validStatuses = array('pending', 'approved', 'rejected');
        if(!in_array($params['status'], $validStatuses)){
            $error_msg = '状态必须是：pending(待审批)、approved(同意)、rejected(拒绝)';
            return $this->error($error_msg);
        }
        
        // check assignee_type if provided
        if(!empty($params['assignee_type'])){
            $validAssigneeTypes = array('user', 'role', 'dept');
            if(!in_array($params['assignee_type'], $validAssigneeTypes)){
                $error_msg = '审批人类型必须是：user(用户)、role(角色)、dept(部门)';
                return $this->error($error_msg);
            }
        }
        
        return $this->succ('数据验证成功');
    }

    /**
     * 保存数据
     *
     * @param array $data
     * @param string $error_msg
     * @return bool
     */
    public function saveData(&$data, &$error_msg=null)
    {
        $mdl = app::get('ticket')->model('workflow_record');
        
        //data
        $saveData = [
            'id' => $data['id'],
            'case_id' => $data['case_id'],
            'node_id' => $data['node_id'],
            'assignee_type' => $data['assignee_type'],
            'assignee_id' => $data['assignee_id'],
            'assignee_name' => $data['assignee_name'],
            'action' => $data['action'],
            'remark' => $data['remark'],
            'status' => $data['status'],
            'process_time' => $data['process_time'],
        ];
        
        //check
        if(empty($saveData['case_id'])){
            $error_msg = '提交的审批流案例ID无效';
            return $this->error($error_msg);
        }
        
        if(empty($saveData['node_id'])){
            $error_msg = '提交的节点ID无效';
            return $this->error($error_msg);
        }
        
        if(empty($saveData['action'])){
            $error_msg = '提交的操作类型无效';
            return $this->error($error_msg);
        }
        
        if(empty($saveData['status'])){
            $error_msg = '提交的状态无效';
            return $this->error($error_msg);
        }
        
        // insert or update
        if($saveData['id']){
            // unset
            unset($saveData['id']);

            //update
            $rs = $mdl->update($saveData, array('id'=>$data['id']));
            if(is_bool($rs)) {
                $error_msg = '更新数据失败或者数据无变化';
                return $this->error($error_msg);
            }
        }else{
            //insert
            $rs = $mdl->insert($saveData);
            if(!$rs){
                $error_msg = '插入数据失败';
                return $this->error($error_msg);
            }
        }
        
        return $this->succ('数据保存成功');
    }
}