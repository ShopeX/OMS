<?php
/**
 * 零元补发单审批流业务流程处理Lib方法类
 *
 * @author wangbiao@shopex.cn
 * @version 2026.04.14
 */
class ticket_workflow_case_zero extends ticket_abstract
{
    const BPM_STATUS_NONE = 'none';
    const BPM_STATUS_PENDING = 'pending';
    const BPM_STATUS_APPROVED = 'approved';
    const BPM_STATUS_REJECTED = 'rejected';
    
    /**
     * 申请创建审批流实例
     *
     * @param $order_id
     * @return true
     * @throws Exception
     */
    public function applyApproval($order_id)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        
        // check
        if(empty($order_id)){
            $error_msg = '无效的订单ID';
            return $this->error($error_msg);
        }
        
        // 获取待审核的审批信息
        $orderFlowInfo = $caseMdl->getList('*', ['original_id'=>$order_id, 'status'=>['pending','processing']], 0, 1);
        if($orderFlowInfo){
            $error_msg = '已经存在待审核的订单';
            return $this->error($error_msg);
        }
        
        // 检查审批参数
        $params = array(
            'order_id' => $order_id,
        );
        $checkResult = $this->checkCreateApprovalParams($params);
        if($checkResult['rsp'] != 'succ'){
            $error_msg = '检查审批参数：' . $checkResult['error_msg'];
            return $this->error($error_msg);
        }
        
        // 创建审批实例
        $result = $this->createApprovalCase($params);
        if($result['rsp'] != 'succ'){
            $error_msg = $result['error_msg'];
            
            return $this->error($error_msg);
        }
        
        return $this->succ('创建审批实例成功', $result['data']);
    }
    
    /**
     * 创建零元补发单审批实例
     *
     * @param array $data
     * @return array
     */
    public function createApprovalCase($data)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        $templateMdl = app::get('ticket')->model('workflow_template');
        $orderMdl = app::get('ome')->model('orders');
        $operLogMdl = app::get('ome')->model('operation_log');
        
        $businessLib = kernel::single('ticket_workflow_business');
        
        $error_msg = '';
        $order_id = $data['order_id'];
        
        // 订单信息
        $orderInfo = $orderMdl->dump($order_id);
        if (empty($orderInfo)) {
            $error_msg = '订单不存在';
            return $this->error($error_msg);
        }
        
        // 审批模板信息
        $templateList = $templateMdl->getList('*', array('scene_type'=>'approve_orders', 'is_enabled'=>'true'), 0, 1);
        $templateInfo = $templateList[0];
        
        // 获取生成的审批实例编号
        $case_bn = $businessLib->createCaseBn();
        
        // shop_id
        $shop_id = $orderInfo['shop_id'];
        
        // 获取订单审批金额
        $orderRetailAmount = $businessLib->getOrderApprovalAmount($order_id);
        
        // 创建审批实例之前，获取第一个需要审批的节点
        $seedCaseInfo = array(
            'original_id' => $order_id,
            'scene_type' => 'approve_orders',
            'order_retail_amount' => $orderRetailAmount,
        );
        $firstNode = $businessLib->resolveApprovalInitialNode($templateInfo, $seedCaseInfo, $error_msg);
        if (empty($firstNode)) {
            if ($error_msg === null) {
                $error_msg = '审批流程模板没有配置可用审批节点';
            }
            return $this->error($error_msg);
        }
        
        $caseData = array(
            'case_bn' => $case_bn,
            'title' => '订单' . $orderInfo['order_bn'] . '赠品补发单审批',
            'original_id' => $order_id,
            'original_bn' => $orderInfo['order_bn'],
            'bill_bn' => $orderInfo['order_bn'],
            'shop_id' => $shop_id,
            'bill_type' => 'order',
            'scene_type' => 'approve_orders',
            'reason' => isset($orderInfo['bufa_reason']) ? trim($orderInfo['bufa_reason']) : '',
            'status' => 'pending',
            'template_id' => $templateInfo['id'],
            'current_node_id' => $firstNode['id'],
            'current_node_type' => $firstNode['node_type'],
            'start_time' => time(),
            'order_retail_amount' => $orderRetailAmount,
            'bpm_status' => self::BPM_STATUS_NONE,
            'abnormal_status' => 'false',
            'abnormal_msg' => '',
            'assignee_type' => $firstNode['assignee_type'] ?? '',
            'assignee_id' => $firstNode['assignee_id'] ?? 0,
            'assignee_name' => $firstNode['assignee_name'] ?? '',
        );
        
        // 获取店铺审批人信息
        $approverInfo = $businessLib->getShopByApprover($shop_id, $firstNode, $error_msg);
        if ($approverInfo) {
            $caseData['assignee_type'] = $approverInfo['assignee_type'];
            $caseData['assignee_id'] = $approverInfo['assignee_id'];
            $caseData['assignee_name'] = $approverInfo['assignee_name'];
            $caseData['bpm_submitter'] = $approverInfo['bpm_submitter'];
            $caseData['bpm_approver'] = $approverInfo['bpm_approver'];
        } else {
            $caseData['abnormal_status'] = 'true';
            $caseData['abnormal_msg'] = $error_msg;
            $caseData['assignee_type'] = '';
            $caseData['assignee_id'] = 0;
            $caseData['assignee_name'] = '';
        }
        
        // insert
        $id = $caseMdl->insert($caseData);
        if (!$id) {
            $error_msg = '创建审批实例失败';
            return $this->error($error_msg);
        }
        
        // log
        $log_msg = '创建审批实例';
        if ($caseData['abnormal_status'] == 'true') {
            $log_msg .= '，但存在异常：' . $caseData['abnormal_msg'];
        }
        $operLogMdl->write_log('order_workflow_case_bufa@wms', $id, $log_msg);
        
        // 给订单添加审批锁定状态
        if (!$businessLib->markOrderBeforeApproval($order_id)) {
            $error_msg = '给订单添加审批锁定状态失败';
            
            // log
            $log_msg = '审批实例创建成功，但给订单添加审批锁定状态失败!';
            $operLogMdl->write_log('order_modify@ome', $order_id, $log_msg);
            
            return $this->error($error_msg);
        }
        
        return $this->succ('审批实例创建成功', array(
            'id' => $id,
            'case_bn' => $case_bn,
            'abnormal_status' => ($caseData['abnormal_status'] == 'true' ? 1 : 0),
            'abnormal_msg' => $caseData['abnormal_msg'],
            'order_retail_amount' => $orderRetailAmount,
        ));
    }
    
    /**
     * 检查零元补发单审批参数
     *
     * @param array $params
     * @return array
     */
    public function checkCreateApprovalParams($params)
    {
        $orderMdl = app::get('ome')->model('orders');
        $templateMdl = app::get('ticket')->model('workflow_template');
        
        $businessLib = kernel::single('ticket_workflow_business');
        
        $error_msg = '';
        
        // check
        if (empty($params['order_id'])) {
            $error_msg = '订单ID不能为空';
            return $this->error($error_msg);
        }
        
        $orderInfo = $orderMdl->dump($params['order_id']);
        if (empty($orderInfo)) {
            $error_msg = '订单不存在';
            return $this->error($error_msg);
        }
        
        $templateList = $templateMdl->getList('*', array('scene_type' => 'approve_orders', 'is_enabled' => 'true'), 0, 1);
        if (empty($templateList)) {
            $error_msg = '没有可用的补发单审批模板';
            return $this->error($error_msg);
        }
        
        $templateInfo = $templateList[0];
        $orderRetailAmount = $businessLib->getOrderApprovalAmount($params['order_id']);
        $seedCaseInfo = array(
            'original_id' => $params['order_id'],
            'scene_type' => 'approve_orders',
            'order_retail_amount' => $orderRetailAmount,
        );
        $firstNode = $businessLib->resolveApprovalInitialNode($templateInfo, $seedCaseInfo, $error_msg);
        if (empty($firstNode)) {
            if ($error_msg === null) {
                $error_msg = '审批流程模板没有配置可用审批节点';
            }
            return $this->error($error_msg);
        }
        
        return $this->succ('创建审批参数验证成功');
    }
    
    /**
     * 验证审批参数
     *
     * @param array $params
     * @return array
     */
    public function checkAuditParams(&$params)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        
        $businessLib = kernel::single('ticket_workflow_business');
        
        // check
        if(empty($params['id'])){
            $error_msg = '审批实例无效,请刷新页面重试';
            return $this->error($error_msg);
        }
        
        if(!in_array($params['status'], ['approved', 'rejected'])){
            $error_msg = '审批状态必须选择approved(同意)、rejected(拒绝)';
            return $this->error($error_msg);
        }
        
        // info
        $caseInfo = $caseMdl->dump($params['id']);
        if(empty($caseInfo)){
            $error_msg = '审批实例不存在';
            return $this->error($error_msg);
        }
        
        if(in_array($caseInfo['current_node_type'], ['end'])){
            $error_msg = '审批已结束,无法审批';
            return $this->error($error_msg);
        }
        
        if($businessLib->isBpmPending($caseInfo)){
            $error_msg = '该审批单正在等待BPM三级审批结果，暂不允许OMS继续审批';
            return $this->error($error_msg);
        }
        
        if (!empty($caseInfo['abnormal_status']) && $caseInfo['abnormal_status'] != 'false') {
            $error_msg = empty($caseInfo['abnormal_msg']) ? '审批实例存在异常，请联系管理员处理' : $caseInfo['abnormal_msg'];
            return $this->error($error_msg);
        }
        
        // order info
        $result = $businessLib->getOrderDetails($caseInfo);
        if($result['rsp'] != 'succ'){
            $error_msg = $result['error_msg'];
            return $this->error($error_msg);
        }
        
        $orderInfo = $result['data'];
        
        // check case status
        if($caseInfo['status'] != 'pending' && $caseInfo['status'] != 'processing'){
            $error_msg = '审批实例状态不允许操作';
            return $this->error($error_msg);
        }
        
        // check current user is assignee
        $user = kernel::single('desktop_user');
        $current_user_id = $user->get_id();
        if($caseInfo['assignee_id'] != $current_user_id){
            $error_msg = '您不是当前审批人，无权限进行审批!';
            return $this->error($error_msg);
        }
        
        // check remark
        if(empty($params['remark'])){
            $error_msg = '请填写您的审批意见';
            return $this->error($error_msg);
        }
        
        // merge
        $params['order_id'] = $orderInfo['order_id'];
        $params['order_bn'] = $orderInfo['order_bn'];
        $params['remark'] = $this->charFilter(htmlspecialchars($params['remark']));
        
        return $this->succ('审批参数验证成功');
    }
    
    /**
     * 处理审批
     *
     * @param array $data
     * @return array
     */
    public function processAudit($data)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        $recordMdl = app::get('ticket')->model('workflow_record');
        $nodeMdl = app::get('ticket')->model('workflow_node');
        $orderMdl = app::get('ome')->model('orders');
        $operLogMdl = app::get('ome')->model('operation_log');
        
        $businessLib = kernel::single('ticket_workflow_business');
        
        $error_msg = '';
        
        // user info
        $user = kernel::single('desktop_user');
        $op_id = $user->get_id();
        $op_name = $user->get_name();
        
        // get case info
        $caseInfo = $caseMdl->dump($data['id'], '*');
        if(empty($caseInfo)){
            $error_msg = '找不到审批的实例';
            return $this->error($error_msg);
        }
        
        $orderInfo = $orderMdl->dump(array('order_id' => $caseInfo['original_id']), 'order_id,order_bn,shop_id');
        if (empty($orderInfo)) {
            $error_msg = '订单不存在，无法继续审批';
            return $this->error($error_msg);
        }
        
        // 审批流节点
        $nodeInfo = $nodeMdl->dump(['id'=>$caseInfo['current_node_id']], '*');
        if(empty($nodeInfo)){
            $error_msg = '找不到审批的节点';
            return $this->error($error_msg);
        }
        
        // order_id
        $order_id = $orderInfo['order_id'];
        
        // shop_id
        $shop_id = ($caseInfo['shop_id'] ? $caseInfo['shop_id'] : $orderInfo['shop_id']);
        
        // 获取店铺审批人信息
        $approverInfo = $businessLib->getShopByApprover($shop_id, $nodeInfo, $error_msg);
        if(!$approverInfo){
            $error_msg = '获取BPM审批人失败：' . $error_msg;
            return $this->error($error_msg);
        }
        
        $bpm_submitter = $approverInfo['bpm_submitter'];
        $bpm_approver = $approverInfo['bpm_approver'];
        
        // update
        $updateData = [];
        if($bpm_submitter && $bpm_submitter != $caseInfo['bpm_submitter']){
            $updateData['bpm_submitter'] = $bpm_submitter;
        }
        
        if($bpm_approver && $bpm_approver != $caseInfo['bpm_approver']){
            $updateData['bpm_approver'] = $bpm_approver;
        }
        
        // 更新提交人信息
        if($updateData){
            $caseMdl->update($updateData, array('id'=>$caseInfo['id']));
            
            // reset
            if($bpm_submitter){
                $caseInfo['bpm_submitter'] = $bpm_submitter;
            }
            
            if($bpm_approver){
                $caseInfo['bpm_approver'] = $bpm_approver;
            }
        }
        
        // 当前节点是否为 BPM 审批节点
        $is_bpm_node = $businessLib->caseNeedsBpmApproval($nodeInfo);
        if($is_bpm_node){
            // check
            if(empty($caseInfo['bpm_submitter']) || empty($caseInfo['bpm_approver'])){
                $error_msg = '提交人信息不存在，请先进行配置!';
                return $this->error($error_msg);
            }
        }
        
        // create record
        $recordData = [
            'case_id' => $data['id'],
            'case_bn' => $caseInfo['case_bn'],
            'node_id' => $caseInfo['current_node_id'], // 审批流节点ID
            'original_bn' => $caseInfo['original_bn'], // 来源单号(订单号)
            'assignee_type' => 'user',
            'assignee_id' => $op_id,
            'assignee_name' => $op_name,
            'action' => $nodeInfo['node_type'], // 审批流节点类型
            'status' => $data['status'], // 审核状态
            'remark' => $data['remark'],
            'process_time' => time(),
        ];
        $record_id = $recordMdl->insert($recordData);
        if(!$record_id){
            $error_msg = '审批记录保存失败';
            return $this->error($error_msg);
        }
        
        // update case status
        $updateData = [];
        if($data['status'] == 'approved'){
            $nextNode = $businessLib->getAuditNextNodeByCaseInfo($caseInfo);

            if ($this->shouldStartBpmApproval($caseInfo, $nextNode, $businessLib)) {
                $bpmResult = $this->startBpmApproval($caseInfo, $nextNode, $businessLib);
                if ($bpmResult['rsp'] != 'succ') {
                    $error_msg = $bpmResult['error_msg'];
                    return $this->error($error_msg);
                }
                
                if (isset($bpmResult['data']['instance_no'])) {
                    $updateData['bpm_instance_no'] = $bpmResult['data']['instance_no'];
                }
                
                $updateData['need_bpm_approval'] = 'true';
                $updateData['status'] = 'processing';
                $updateData['current_node_id'] = $nextNode['id'];
                $updateData['current_node_type'] = $nextNode['node_type'];
                $updateData['assignee_type'] = 'bpm';
                $updateData['assignee_id'] = 0;
                $updateData['assignee_name'] = $nextNode['assignee_name'];
                $updateData['bpm_status'] = self::BPM_STATUS_PENDING;
                
                if (empty($caseInfo['bpm_submitter'])) {
                    $updateData['bpm_submitter'] = $op_id;
                }
                if (empty($caseInfo['bpm_approver'])) {
                    $updateData['bpm_approver'] = $op_name;
                }
            } elseif (empty($nextNode) || $nextNode['node_type'] == 'end') {
                $updateData['status'] = 'approved';
                $updateData['current_node_id'] = empty($nextNode) ? 0 : $nextNode['id'];
                $updateData['current_node_type'] = empty($nextNode) ? 'end' : $nextNode['node_type'];
                $updateData['end_time'] = time();
                $updateData['bpm_status'] = $updateData['bpm_status'] ?? self::BPM_STATUS_APPROVED;
            } else {
                $nextApproverInfo = $businessLib->getShopByApprover($shop_id, $nextNode, $error_msg);
                if (!$nextApproverInfo) {
                    $error_msg = $error_msg ?: '获取下一节点审批人失败';
                    return $this->error($error_msg);
                }

                $updateData['status'] = 'processing';
                $updateData['current_node_id'] = $nextNode['id'];
                $updateData['current_node_type'] = $nextNode['node_type'];
                $updateData['assignee_type'] = $nextApproverInfo['assignee_type'];
                $updateData['assignee_id'] = $nextApproverInfo['assignee_id'];
                $updateData['assignee_name'] = $nextApproverInfo['assignee_name'];

                if (!empty($nextApproverInfo['bpm_submitter'])) {
                    $updateData['bpm_submitter'] = $nextApproverInfo['bpm_submitter'];
                }
                if (!empty($nextApproverInfo['bpm_approver'])) {
                    $updateData['bpm_approver'] = $nextApproverInfo['bpm_approver'];
                }
            }
        }elseif($data['status'] == 'rejected'){
            // 拒绝
            $updateData['status'] = 'rejected';
            $updateData['end_time'] = time();
            $updateData['bpm_status'] = self::BPM_STATUS_REJECTED;
        }elseif($data['status'] == 'return'){
            // 退回
            $updateData['status'] = 'cancelled';
            $updateData['end_time'] = time();
        }
        
        $rs = $caseMdl->update($updateData, array('id'=>$data['id']));
        if(is_bool($rs)) {
            $error_msg = '更新审批实例状态失败';
            return $this->error($error_msg);
        }
        
        // status
        $statusList = $caseMdl->getStatusList();
        $noteTypeList = $nodeMdl->getNodeTypes();
        
        // log
        if($nodeInfo['node_type'] == 'start' || (($noteTypeList[$nodeInfo['node_type']] ?? '') == '开始')){
            $log_msg = '审批开始，进入审批流程!';
        }elseif($businessLib->isBpmPending($updateData)){
            $log_msg = 'OMS二级审批完成；自动发起BPM三级审批，等待回调结果';
        }else{
            $log_msg = '审批节点：'. ($noteTypeList[$nodeInfo['node_type']] ?? $nodeInfo['node_type']) .'('. ($statusList[$data['status']] ?? $data['status']) .')，审批意见：'. $data['remark'];
        }
        
        // log
        $operLogMdl->write_log('order_workflow_case_bufa@wms', $data['id'], $log_msg);
        
        // [拒绝时]更新订单状态
        if(in_array($updateData['status'], array('rejected', 'cancelled', 'source_cancelled'), true)) {
            $isResult = $businessLib->removeOrderApprovalFlag($order_id, $caseInfo['scene_type']);
            if($isResult){
                $orderMdl = app::get('ome')->model('orders');
                $orderInfo = $orderMdl->db_dump(['order_id'=>$caseInfo['original_id']], 'order_id,order_bn,is_not_combine');
                
                // 不允许审核
                // [按位或运算符]添加禁止审核订单
                $is_not_combine = $orderInfo['is_not_combine'] | ome_order_combine_const::__PROHIBIT_CONFIRM_ORDER;
                
                // update
                if($is_not_combine != $orderInfo['is_not_combine']){
                    $orderMdl->update(['is_not_combine'=>$is_not_combine], ['order_id'=>$caseInfo['original_id']]);
                    
                    // log
                    $log_msg = '补发单审批被拒绝，标记：禁止审核订单；';
                    $operLogMdl->write_log('order_modify@ome', $caseInfo['original_id'], $log_msg);
                }
            }
        }
        
        // 如果审批流程结束，更新订单状态
        if(($updateData['current_node_type'] ?? '') == 'end' && $updateData['status'] == 'approved') {
            // 审批完成，恢复订单可审核状态，并且延迟5分钟自动重新路由审核订单
            $result = $businessLib->applySceneApprovalCompletion($caseInfo);
            if($result['rsp'] != 'succ'){
                $error_msg = '审批结束，更新业务状态失败：'.$result['error_msg'];
                return $this->error($error_msg);
            }
            
            // create end record
            $recordData = [
                'case_id' => $data['id'],
                'case_bn' => $caseInfo['case_bn'],
                'node_id' => $updateData['current_node_id'] ?: $caseInfo['current_node_id'], // 审批流节点ID
                'action' => $updateData['current_node_type'], // 审批流节点类型
                'original_bn' => $caseInfo['original_bn'], // 来源单号(订单号)
                'assignee_type' => 'user',
                'assignee_id' => $op_id,
                'assignee_name' => $op_name,
                'status' => $data['status'], // 审核状态
                // 'remark' => '审批结束',
                'process_time' => time(),
            ];
            $recordMdl->insert($recordData);
            
            // log
            $log_msg = '审批结束，完成审批流程!';
            $operLogMdl->write_log('order_workflow_case_bufa@wms', $data['id'], $log_msg);
        }
        
        return $this->succ('审批处理成功');
    }
    
    /**
     * 打开补发审批页前是否需要重新解析店铺审批人
     *
     * @param array $caseInfo
     * @return bool
     */
    public function shouldRefreshBufaAssigneeOnOpen(array $caseInfo)
    {
        if (($caseInfo['scene_type'] ?? '') !== 'approve_orders') {
            return false;
        }

        if (!empty($caseInfo['abnormal_status']) && $caseInfo['abnormal_status'] !== 'false') {
            return true;
        }

        if (empty($caseInfo['current_node_id'])) {
            return false;
        }

        $nodeMdl = app::get('ticket')->model('workflow_node');
        $businessLib = kernel::single('ticket_workflow_business');
        $nodeInfo = $nodeMdl->dump(array('id' => $caseInfo['current_node_id']), '*');
        if (empty($nodeInfo)) {
            return false;
        }

        if (!$businessLib->shouldResolveNodeAssignee($nodeInfo)) {
            return false;
        }

        $assigneeId = isset($caseInfo['assignee_id']) ? intval($caseInfo['assignee_id']) : 0;

        return $assigneeId <= 0;
    }

    /**
     * 刷新补发审批人
     *
     * @param array $caseInfo
     * @return array
     */
    public function refreshApprovalCaseAssignee(&$caseInfo)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        $nodeMdl = app::get('ticket')->model('workflow_node');
        $orderMdl = app::get('ome')->model('orders');
        $businessLib = kernel::single('ticket_workflow_business');

        if (empty($caseInfo['id'])) {
            return $this->error('审批实例不存在');
        }

        $orderInfo = $orderMdl->dump(array('order_id' => $caseInfo['original_id']), 'order_id,order_bn,shop_id');
        if (empty($orderInfo)) {
            $error_msg = '订单不存在，无法继续审批';
            $this->markCaseAbnormal($caseInfo['id'], $error_msg);
            return $this->error($error_msg);
        }

        $nodeInfo = $nodeMdl->dump(array('id' => $caseInfo['current_node_id']), '*');
        if (empty($nodeInfo)) {
            $error_msg = '审批节点不存在，无法继续审批';
            $this->markCaseAbnormal($caseInfo['id'], $error_msg);
            return $this->error($error_msg);
        }

        $shop_id = ($caseInfo['shop_id'] ? $caseInfo['shop_id'] : $orderInfo['shop_id']);
        $error_msg = '';
        $approverInfo = $businessLib->getShopByApprover($shop_id, $nodeInfo, $error_msg);
        if (!$approverInfo) {
            $this->markCaseAbnormal($caseInfo['id'], $error_msg);
            return $this->error($error_msg);
        }

        $updateData = array(
            'abnormal_status' => 'false',
            'abnormal_msg' => '',
            'assignee_type' => $approverInfo['assignee_type'],
            'assignee_id' => $approverInfo['assignee_id'],
            'assignee_name' => $approverInfo['assignee_name'],
            'bpm_submitter' => $approverInfo['bpm_submitter'],
            'bpm_approver' => $approverInfo['bpm_approver'],
        );
        $rs = $caseMdl->update($updateData, array('id' => $caseInfo['id']));
        if (is_bool($rs)) {
            return $this->error('更新审批实例失败');
        }

        $caseInfo = array_merge($caseInfo, $updateData);

        return $this->succ('刷新审批人成功', $caseInfo);
    }

    protected function markCaseAbnormal($case_id, $abnormal_msg)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');

        return $caseMdl->update(array(
            'abnormal_status' => 'true',
            'abnormal_msg' => $abnormal_msg,
        ), array('id' => $case_id));
    }

    /**
     * 是否应自动发起 BPM 审批
     *
     * @param array $caseInfo
     * @param array|null $nextNode
     * @param ticket_workflow_business $businessLib
     * @return bool
     */
    private function shouldStartBpmApproval($caseInfo, $nextNode, $businessLib)
    {
        if ($caseInfo['scene_type'] != 'approve_orders') {
            return false;
        }
        
        // 下一节点是否为 BPM 审批节点
        if (!$businessLib->caseNeedsBpmApproval($nextNode)) {
            return false;
        }
        
        // 统计已通过 OMS 审批节点次数
        return $businessLib->countApprovedApprovalRecords($caseInfo['id']) >= 2;
    }

    /**
     * 开始请求BPM审批
     *
     * @param array $caseInfo
     * @param array $bpmNode
     * @param ticket_workflow_business|null $businessLib
     * @return array
     */
    public function startBpmApproval($caseInfo, $bpmNode, $businessLib = null)
    {
        if ($businessLib === null) {
            $businessLib = kernel::single('ticket_workflow_business');
        }

        $nodeMdl = app::get('ticket')->model('workflow_node');
        
        $orderResult = $businessLib->getOrderDetails($caseInfo);
        if ($orderResult['rsp'] != 'succ') {
            $error_msg = '获取订单详情失败：' . $orderResult['error_msg'];
            return $this->error($error_msg);
        }
        
        $orderInfo = $orderResult['data'];
        if (empty($orderInfo)) {
            $error_msg = '获取审批的订单信息失败!';
            return $this->error($error_msg);
        }
        
        $bpmNode['id'] = intval($bpmNode['id']);
        $nodeInfo = $nodeMdl->dump(array('id'=>$bpmNode['id']), '*');
        if (empty($nodeInfo)) {
            $error_msg = '请求BPM失败：未找到审批的节点';
            return $this->error($error_msg);
        }
        
        $shop_id = ($caseInfo['shop_id'] ? $caseInfo['shop_id'] : $orderInfo['shop_id']);
        $error_msg = '';
        $approvalMappingInfo = $businessLib->getShopByApprover($shop_id, $nodeInfo, $error_msg);
        if (!$approvalMappingInfo) {
            $error_msg = '获取BPM审批人失败：' . $error_msg;
            return $this->error($error_msg);
        }
        
        $bpmNode['assignee_id'] = $approvalMappingInfo['bpm_submitter'];
        $bpmNode['assignee_name'] = $approvalMappingInfo['bpm_approver'];
        
        // check
        if (empty($bpmNode['assignee_id'])) {
            $error_msg = 'BPM三级审批节点未配置审批人';
            return $this->error($error_msg);
        }

        $params = array(
            'case_info' => $caseInfo,
            'node_info' => $nodeInfo,
            'order_details' => $orderInfo,
            'bpmNode' => $bpmNode,
        );

        $requestResult = $this->error('未发起BPM接口请求');

        foreach (kernel::servicelist('nipponpaint.service.bpm.ticket') as $object) {
            if (method_exists($object, 'requestBpmApproval')) {
                $requestResult = $object->requestBpmApproval($params);
            }
        }

        return $requestResult;
    }
}