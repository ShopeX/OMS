<?php
/**
 * 审批流节点业务逻辑类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_workflow_node extends ticket_abstract {
    /**
     * 验证数据
     *
     * @param array $params
     * @return array
     */
    public function checkParams(&$params)
    {
        $mdl = app::get('ticket')->model('workflow_node');
        $userMdl = app::get('desktop')->model('users');
        $templateMdl = app::get('ticket')->model('workflow_template');
        
        // check template_id
        if(empty($params['template_id'])){
            $error_msg = '所属模板未选择，请检查';
            return $this->error($error_msg);
        }
        
        // check node_name
        if(empty($params['node_name'])){
            $error_msg = '节点名称必须填写';
            return $this->error($error_msg);
        }
        
        // check node_type
        if(empty($params['node_type'])){
            $error_msg = '节点类型未选择，请检查';
            return $this->error($error_msg);
        }
        
        // check step_order
//        if(empty($params['step_order'])){
//            $error_msg = '节点顺序必须填写';
//            return $this->error($error_msg);
//        }
        
        if($params['node_type'] == 'approval'){
            $params['step_order'] = 2;
        }elseif($params['node_type'] == 'cc'){
            $params['step_order'] = 3;
        }elseif($params['node_type'] == 'end'){
            $params['step_order'] = 4;
        }else{
            $params['step_order'] = 1;
        }
        
        if(empty($params['assignee_type'])){
            $error_msg = '审批人类型未选择，请检查';
            return $this->error($error_msg);
        }
        
        if(empty($params['assignee_id'])){
            $error_msg = '审批人未选择，请检查';
            return $this->error($error_msg);
        }
        
        // 模板信息
        $templateInfo = $templateMdl->dump(array('id'=>$params['template_id'], 'is_enabled'=>'true'), '*');
        if(empty($templateInfo)){
            $error_msg = '选择的模板：'.$templateInfo['template_name'].'不存在或者状态异常';
            return $this->error($error_msg);
        }
        
        $params['template_bn'] = $templateInfo['template_bn'];
        $params['template_name'] = $templateInfo['template_name'];
        $params['scene_type'] = $templateInfo['scene_type'];
        
        // 操作员信息
        $userInfo = $userMdl->dump(array('user_id'=>$params['assignee_id'], 'status'=>1), '*');
        if(empty($userInfo)){
            $error_msg = '审批人不存在或者状态异常';
            return $this->error($error_msg);
        }
        
        $params['assignee_name'] = $userInfo['name'];
        
        // check exist
        if($params['id']){
            $rowInfo = $mdl->dump(array('id'=>$params['id']), '*');
            if(empty($rowInfo)){
                $error_msg = '编辑的记录不存在,请检查！';
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
        $mdl = app::get('ticket')->model('workflow_node');
        
        //data
        $saveData = [
            'id' => isset($data['id']) ? $data['id'] : null,
            'template_id' => $data['template_id'],
            'node_name' => $data['node_name'],
            'node_type' => $data['node_type'],
            'step_order' => $data['step_order'],
            'assignee_type' => $data['assignee_type'],
            'assignee_id' => $data['assignee_id'],
            'assignee_name' => $data['assignee_name'],
        ];
        
        //check
        if(empty($saveData['template_id'])){
            $error_msg = '提交的所属模板ID无效';
            return $this->error($error_msg);
        }
        
        if(empty($saveData['node_name'])){
            $error_msg = '提交的节点名称无效';
            return $this->error($error_msg);
        }
        
        if(empty($saveData['node_type'])){
            $error_msg = '提交的节点类型无效';
            return $this->error($error_msg);
        }
        
        if(empty($saveData['step_order'])){
            $error_msg = '提交的节点顺序无效';
            return $this->error($error_msg);
        }
        
        if(empty($saveData['assignee_id'])){
            $error_msg = '审批人无效';
            return $this->error($error_msg);
        }
        
        // 检测审批人是否已经被其他审批节点占用
        $filter = [
            'node_type' => $saveData['node_type'],
            'template_id' => $saveData['template_id'],
        ];
        
        if($saveData['id']){
            $filter['id|noequal'] = $saveData['id'];
        }
        
        $existNodeRow = $mdl->getList('id,node_name,node_type', $filter, 0, 1);
        if($existNodeRow){
            $error_msg = '审批节点['. $saveData['node_type'] .'] + 模板['.$data['template_name'].']已经存在，不允许重复添加';
            return $this->error($error_msg);
        }
        
        // 检测审批人是否已经被其他审批节点占用
        $filter = [
            'template_id' => $saveData['template_id'],
            'assignee_id' => $saveData['assignee_id'],
            'step_order|noequal' => $saveData['step_order']
        ];
        $existNodeRow = $mdl->getList('id,node_name,node_type', $filter, 0, 1);
        if($existNodeRow){
            $error_msg = '审批人['.$saveData['assignee_name'].']已经被['. $existNodeRow[0]['node_name'] .']占用，请检查';
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
    
    /**
     * 获取开始节点
     *
     * @param int $template_id
     * @return array|null
     */
    public function getStartNode($template_id=0)
    {
        $nodeMdl = app::get('ticket')->model('workflow_node');
        
        // filter
        $filter = ['node_type'=>'start'];
        if($template_id){
            $filter['template_id'] = $template_id;
        }
        
        // get current node
        $currentNode = $nodeMdl->dump($filter, '*');
        if(empty($currentNode)){
            return null;
        }
        
        return $currentNode;
    }
    
    /**
     * 获取当前节点
     *
     * @param int $current_node_id
     * @return array
     */
    public function getCurrentNode($current_node_id)
    {
        $nodeMdl = app::get('ticket')->model('workflow_node');
        
        // filter
        $filter = ['id'=>$current_node_id];
        
        // get current node
        $currentNode = $nodeMdl->dump($filter, '*');
        if(empty($currentNode)){
            return null;
        }
        
        return $currentNode;
    }
    
    /**
     * 获取操作员列表
     *
     * @return array
     */
    public function getOperatorList()
    {
        $userMdl = app::get('desktop')->model('users');
        
        $userList = $userMdl->getList('user_id,name', ['status'=>1], 0, -1);
        
        return $userList;
    }
}