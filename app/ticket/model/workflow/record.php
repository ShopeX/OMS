<?php
/**
 * 审批流记录模型类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_mdl_workflow_record extends dbeav_model {
    // 可根据需要扩展model方法
    
    /**
     * 获取操作类型列表
     *
     * @return array
     */
    public function getActionList()
    {
        return array(
            'approved' => '同意',
            'rejected' => '拒绝',
            'forward' => '转发',
        );
    }
    
    /**
     * 获取状态列表
     *
     * @return array
     */
    public function getStatusList()
    {
        return array(
            'pending' => '待审批',
            'processing' => '审批中',
            'approved' => '同意',
            'rejected' => '拒绝',
            'cancelled' => '取消',
        );
    }
    
    /**
     * 获取审批人类型列表
     *
     * @return array
     */
    public function getAssigneeTypes()
    {
        $nodeMdl = app::get('ticket')->model('workflow_node');
        
        $typeList = $nodeMdl->getAssigneeTypes();
        
        return $typeList;
    }
    
    /**
     * 根据案例ID获取记录列表
     *
     * @param int $case_id
     * @return array
     */
    public function getRecordsByCaseId($case_id)
    {
        return $this->getList('*', array('case_id' => $case_id), 0, -1, 'id DESC');
    }
    
    /**
     * 根据节点ID获取记录列表
     *
     * @param int $node_id
     * @return array
     */
    public function getRecordsByNodeId($node_id)
    {
        return $this->getList('*', array('node_id' => $node_id), 0, -1, 'id DESC');
    }
    
    /**
     * 根据审批人获取记录列表
     *
     * @param int $assignee_id
     * @param string $assignee_type
     * @return array
     */
    public function getRecordsByAssignee($assignee_id, $assignee_type = 'user')
    {
        return $this->getList('*', array(
            'assignee_id' => $assignee_id,
            'assignee_type' => $assignee_type
        ), 0, -1, 'id DESC');
    }
    
    /**
     * 根据操作类型获取记录列表
     *
     * @param string $action
     * @return array
     */
    public function getRecordsByAction($action)
    {
        return $this->getList('*', array('action' => $action), 0, -1, 'id DESC');
    }
    
    /**
     * 根据状态获取记录列表
     *
     * @param string $status
     * @return array
     */
    public function getRecordsByStatus($status)
    {
        return $this->getList('*', array('status' => $status), 0, -1, 'id DESC');
    }
    
    /**
     * 获取案例的审批历史
     *
     * @param int $case_id
     * @return array
     */
    public function getCaseApprovalHistory($case_id)
    {
        return $this->getList('*', array('case_id' => $case_id), 0, -1, 'at_time ASC');
    }
    
    /**
     * 获取节点的审批记录
     *
     * @param int $node_id
     * @return array
     */
    public function getNodeApprovalRecords($node_id)
    {
        return $this->getList('*', array('node_id' => $node_id), 0, -1, 'at_time ASC');
    }
    
    /**
     * 创建审批记录
     *
     * @param array $data
     * @return int|bool
     */
    public function createApprovalRecord($data)
    {
        $recordData = array(
            'case_id' => $data['case_id'],
            'node_id' => $data['node_id'],
            'assignee_type' => $data['assignee_type'],
            'assignee_id' => $data['assignee_id'],
            'assignee_name' => $data['assignee_name'],
            'action' => $data['action'],
            'remark' => $data['remark'],
            'status' => $data['status'],
            'process_time' => time(),
        );
        
        return $this->insert($recordData);
    }
    
    /**
     * 获取审核状态
     *
     * @param $col
     * @return mixed|string
     */
    public function modifier_status($col)
    {
        $StatusList = $this->getStatusList();
        
        return isset($StatusList[$col]) ? $StatusList[$col] : $col;
    }
} 