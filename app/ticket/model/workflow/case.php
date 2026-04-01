<?php
/**
 * 审批流实例模型类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_mdl_workflow_case extends dbeav_model {
    // 可根据需要扩展model方法
    
    /**
     * 审核状态列表
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
     * 业务类型列表
     *
     * @return array
     */
    public function getBillTypeList()
    {
        return array(
            'order' => ['bill_type'=>'order', 'bill_name'=>'订单'],
        );
    }
    
    /**
     * 获取审批场景类型列表
     *
     * @return array
     */
    public function getSceneTypes()
    {
        $templateMdl = app::get('ticket')->model('workflow_template');
        
        $typeList = $templateMdl->getSceneTypes();
        
        return $typeList;
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
     * 根据状态获取实例列表
     *
     * @param string $status
     * @return array
     */
    public function getCasesByStatus($status)
    {
        return $this->getList('*', array('status' => $status), 0, -1, 'id DESC');
    }
    
    /**
     * 根据审批人获取待审批实例
     *
     * @param int $assignee_id
     * @param string $assignee_type
     * @return array
     */
    public function getPendingCasesByAssignee($assignee_id, $assignee_type = 'user')
    {
        return $this->getList('*', array(
            'assignee_id' => $assignee_id,
            'assignee_type' => $assignee_type,
            'status' => 'pending'
        ), 0, -1, 'id DESC');
    }
    
    /**
     * 生成审批号
     *
     * @return string
     */
    public function generateCaseBn()
    {
        $prefix = 'WF' . date('Ymd');
        $maxBn = $this->getList('MAX(case_bn) as max_bn', array('case_bn|like' => $prefix . '%'));
        $maxNum = 0;
        
        if(isset($maxBn[0]['max_bn']) && $maxBn[0]['max_bn']){
            $maxNum = intval(substr($maxBn[0]['max_bn'], -4));
        }
        
        return $prefix . str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);
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
    
    /**
     * 获取业务类型
     *
     * @param $col
     * @return mixed|string
     */
    public function modifier_bill_type($col)
    {
        $typeList = $this->getBillTypeList();
        
        return isset($typeList[$col]) ? $typeList[$col]['bill_name'] : $col;
    }
} 