<?php
/**
 * 审批流实例业务逻辑类
 *
 * @author shopex开发团队
 * @version 2025.07.10
 */
class ticket_workflow_case extends ticket_abstract
{
    /**
     * 获取订单详细信息
     *
     * @param $order_id
     * @return array
     */
    public function getOrderDetails($caseInfo)
    {
        $orderMdl = app::get('ome')->model('orders');
        
        // order_id
        $order_id = $caseInfo['original_id'];
        $addGiftList = json_decode($caseInfo['items_context'], true);
        
        // check
        if(empty($addGiftList)){
            return $this->error('赠品信息不存在,请检查！');
        }
        
        // order info
        $orderInfo = $orderMdl->dump(['order_id'=>$order_id], '*', array('order_objects'=>array('*',array('order_items'=>array('*')))));
        if(empty($orderInfo)){
            return $this->error('订单信息不存在,请检查！');
        }
        
        if(empty($orderInfo['order_objects'])){
            return $this->error('订单明细列表不存在,请检查！');
        }
        
        $orderInfo['addGiftList'] = $addGiftList;
        
        return $this->succ('操作成功', $orderInfo);
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
        
        // check
        if(empty($caseInfo['items_context'])){
            $error_msg = '审批实例没有赠品信息';
            return $this->error($error_msg);
        }
        
        if(in_array($caseInfo['current_node_type'], ['end'])){
            $error_msg = '审批已结束,无法审批';
            return $this->error($error_msg);
        }
        
        // order info
        $result = $this->getOrderDetails($caseInfo);
        if($result['rsp'] != 'succ'){
            $error_msg = $result['error_msg'];
            return $this->error($error_msg);
        }
        
        $orderInfo = $result['data'];
        
        // check
        if(empty($orderInfo['addGiftList'])){
            $error_msg = '审批实例没有有效的赠品信息';
            return $this->error($error_msg);
        }
        
        // check case status
        if($caseInfo['status'] != 'pending' && $caseInfo['status'] != 'processing'){
            $error_msg = '审批实例状态不允许操作';
            return $this->error($error_msg);
        }
        
        // check current user is assignee
        $user = kernel::single('desktop_user');
        $current_user_id = $user->get_id();
        if($caseInfo['assignee_id'] != $current_user_id){
            $error_msg = '您不是当前审批人，无法操作';
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
     * @param string $error_msg
     * @return array
     */
    public function processAudit(&$data, &$error_msg=null)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        $recordMdl = app::get('ticket')->model('workflow_record');
        $nodeMdl = app::get('ticket')->model('workflow_node');
        $operLogObj = app::get('ome')->model('operation_log');
        
        // user info
        $user = kernel::single('desktop_user');
        $op_id = $user->get_id();
        $op_name = $user->get_name();
        
        // get case info
        $caseInfo = $caseMdl->dump($data['id']);
        if(empty($caseInfo)){
            $error_msg = '找不到审批的实例';
            return $this->error($error_msg);
        }
        
        // 审批流节点
        $nodeInfo = $nodeMdl->dump(['id'=>$caseInfo['current_node_id']], '*');
        
        // create record
        $recordData = [
            'case_id' => $data['id'],
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
            // 获取下一个节点
            $nextNode = $this->getNextNode($caseInfo['current_node_id']);
            
            // 下一个节点,是否为：结束
            if($nextNode['node_type'] == 'end' || empty($nextNode)){
                // 审批完成
                $updateData['status'] = 'approved';
                $updateData['current_node_id'] = $nextNode['id'];
                $updateData['current_node_type'] = $nextNode['node_type'];
                $updateData['end_time'] = time();
                
                // 没有下一个节点,赋值为空
                $nextNode = null;
            }else{
                // 还有下一个节点
                $updateData['status'] = 'processing';
                $updateData['current_node_id'] = $nextNode['id'];
                $updateData['current_node_type'] = $nextNode['node_type'];
                $updateData['assignee_id'] = $nextNode['assignee_id'];
                $updateData['assignee_name'] = $nextNode['assignee_name'];
            }
        }elseif($data['status'] == 'rejected'){
            // 拒绝
            $updateData['status'] = 'rejected';
            $updateData['end_time'] = time();
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
        if($nodeInfo['node_type'] == 'start' || $noteTypeList[$nodeInfo['node_type']] == '开始'){
            $log_msg = '审批开始，进入审批流程!';
        }else{
            $log_msg = '审批节点：'. $noteTypeList[$nodeInfo['node_type']] .'('. $statusList[$data['status']] .')，审批意见：'. $data['remark'];
        }
        
        $operLogObj->write_log('order_workflow_case@wms', $data['id'], $log_msg);
        
        // 如果审批流程结束，更新订单状态
        if($updateData['current_node_type'] == 'end' && $updateData['status'] == 'approved'){
            $result = $this->updateOrderAfterApproval($caseInfo);
            if($result['rsp'] != 'succ'){
                $error_msg = '审批结束，更新订单状态或赠品失败：'.$result['error_msg'];
                return $this->error($error_msg);
            }
            
            // create end record
            $recordData = [
                'case_id' => $data['id'],
                'node_id' => $updateData['current_node_id'], // 审批流节点ID
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
            $operLogObj->write_log('order_workflow_case@wms', $data['id'], $log_msg);
            
            // [大家电订单]商品被修改，订单需要重新预约时间
            kernel::single('ome_order_reservation')->againReservation($caseInfo['original_id'], 'goods_change');
        }
        
        return $this->succ('审批处理成功');
    }
    
    /**
     * 验证创建赠品审批参数
     *
     * @param array $params
     * @return array
     */
    public function checkCreateGiftApprovalParams(&$params)
    {
        $orderMdl = app::get('ome')->model('orders');
        $templateMdl = app::get('ticket')->model('workflow_template');
        
        // check order_id
        if(empty($params['order_id'])){
            $error_msg = '订单ID不能为空';
            return $this->error($error_msg);
        }
        
        // check order exists
        $orderInfo = $orderMdl->dump($params['order_id']);
        if(empty($orderInfo)){
            $error_msg = '订单不存在';
            return $this->error($error_msg);
        }
        
        // check gift items
        if(empty($params['gift_items'])){
            $error_msg = '赠品信息不能为空';
            return $this->error($error_msg);
        }
        
        // check template exists
        $templateList = $templateMdl->getList('*', ['scene_type'=>'add_gift', 'is_enabled'=>'true'], 0, 1);
        if(empty($templateList)){
            $error_msg = '没有可用的赠品审批模板';
            return $this->error($error_msg);
        }
        
        return $this->succ('创建审批参数验证成功');
    }
    
    /**
     * 创建赠品审批实例
     *
     * @param array $data
     * @param string $error_msg
     * @return array
     */
    public function createGiftApprovalCase(&$data, &$error_msg=null)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        $templateMdl = app::get('ticket')->model('workflow_template');
        $nodeMdl = app::get('ticket')->model('workflow_node');
        $orderMdl = app::get('ome')->model('orders');
        
        // user info
        $user = kernel::single('desktop_user');
        $op_id = $user->get_id();
        $op_name = $user->get_name();
        
        // get order info
        $orderInfo = $orderMdl->dump($data['order_id']);
        
        // get template
        $templateList = $templateMdl->getList('*', ['scene_type'=>'add_gift', 'is_enabled'=>'true'], 0, 1);
        $templateInfo = $templateList[0];
        
        // get first node
        $nodeList = $nodeMdl->getList('*', ['template_id'=>$templateInfo['id']], 0, 1, 'step_order ASC');
        if(empty($nodeList)){
            $error_msg = '审批流程模板没有配置节点';
            return $this->error($error_msg);
        }
        $firstNode = $nodeList[0];
        
        // generate case_bn
        $case_bn = $this->generateCaseBn();
        
        // create case
        $caseData = [
            'case_bn' => $case_bn,
            'title' => '订单'. $orderInfo['order_bn'] .'赠品审批',
            'original_id' => $data['order_id'],
            'original_bn' => $orderInfo['order_bn'],
            'bill_bn' => $orderInfo['order_bn'],
            'bill_type' => 'order',
            'scene_type' => 'add_gift',
            'items_context' => json_encode($data['gift_items']),
            'status' => 'pending',
            'template_id' => $templateInfo['id'],
            'submitter_id' => $op_id,
            'submitter_name' => $op_name,
            'current_node_id' => $firstNode['id'],
            'start_time' => time(),
            'assignee_type' => $firstNode['assignee_type'],
            'assignee_id' => $firstNode['assignee_id'],
            'assignee_name' => $firstNode['assignee_name'],
        ];
        
        $id = $caseMdl->insert($caseData);
        if(!$id){
            $error_msg = '创建审批实例失败';
            return $this->error($error_msg);
        }
        
        // update order status
        $this->updateOrderBeforeApproval($data['order_id']);
        
        return $this->succ('审批实例创建成功', ['id' => $id, 'case_bn' => $case_bn]);
    }
    
    /**
     * 获取下一个节点
     *
     * @param int $current_node_id
     * @return array|null
     */
    public function getNextNode($current_node_id)
    {
        $nodeMdl = app::get('ticket')->model('workflow_node');
        
        // get current node
        $currentNode = $nodeMdl->dump($current_node_id);
        if(empty($currentNode)){
            return null;
        }
        
        // get next node
        $nextNode = $nodeMdl->getList('*', [
            'template_id' => $currentNode['template_id'],
            'step_order|than' => $currentNode['step_order']
        ], 0, 1, 'step_order ASC');
        
        return empty($nextNode) ? null : $nextNode[0];
    }
    
    /**
     * 获取用户待审批列表
     *
     * @param int $user_id
     * @return array
     */
    public function getPendingCasesByUser($user_id)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        
        $pendingList = $caseMdl->getList('*', [
            'assignee_id' => $user_id,
            'status' => ['pending', 'processing']
        ], 0, -1, 'id DESC');
        
        return $this->succ('获取成功', $pendingList);
    }
    
    /**
     * 获取审批记录
     *
     * @param int $id
     * @return array
     */
    public function getApprovalRecords($id)
    {
        $recordMdl = app::get('ticket')->model('workflow_record');
        
        $records = $recordMdl->getList('*', ['id' => $id], 0, -1, 'id ASC');
        
        return $this->succ('获取成功', $records);
    }
    
    /**
     * 验证取消参数
     *
     * @param array $params
     * @return array
     */
    public function checkCancelParams(&$params)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        
        // check id
        if(empty($params['id'])){
            $error_msg = '审批实例ID不能为空';
            return $this->error($error_msg);
        }
        
        // check case exists
        $caseInfo = $caseMdl->dump($params['id']);
        if(empty($caseInfo)){
            $error_msg = '审批实例不存在';
            return $this->error($error_msg);
        }
        
        // check case status
        if($caseInfo['status'] != 'pending' && $caseInfo['status'] != 'processing'){
            $error_msg = '审批实例状态不允许取消';
            return $this->error($error_msg);
        }
        
        // check current user is submitter
        $user = kernel::single('desktop_user');
        $current_user_id = $user->get_id();
        
        if($caseInfo['submitter_id'] != $current_user_id){
            $error_msg = '只有提交人才能取消审批';
            return $this->error($error_msg);
        }
        
        return $this->succ('取消参数验证成功');
    }
    
    /**
     * 取消审批
     *
     * @param array $data
     * @param string $error_msg
     * @return array
     */
    public function cancelApproval(&$data, &$error_msg=null)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        $operLogObj = app::get('ome')->model('operation_log');
        
        // update case status
        $updateData = [
            'status' => 'cancelled',
            'end_time' => time()
        ];
        
        $rs = $caseMdl->update($updateData, array('id'=>$data['id']));
        if(is_bool($rs)) {
            $error_msg = '取消审批失败';
            return $this->error($error_msg);
        }
        
        // update order status
        $caseInfo = $caseMdl->dump($data['id']);
        $this->updateOrderAfterCancel($caseInfo['original_id']);
        
        // log
        $log_msg = '审批已取消';
        $operLogObj->write_log('workflow_cancel@ticket', $data['id'], $log_msg);
        
        return $this->succ('审批已取消');
    }
    
    /**
     * 验证重新提交参数
     *
     * @param array $params
     * @return array
     */
    public function checkResubmitParams(&$params)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        
        // check id
        if(empty($params['id'])){
            $error_msg = '审批实例ID不能为空';
            return $this->error($error_msg);
        }
        
        // check case exists
        $caseInfo = $caseMdl->dump($params['id']);
        if(empty($caseInfo)){
            $error_msg = '审批实例不存在';
            return $this->error($error_msg);
        }
        
        // check case status
        if($caseInfo['status'] != 'rejected' && $caseInfo['status'] != 'cancelled'){
            $error_msg = '审批实例状态不允许重新提交';
            return $this->error($error_msg);
        }
        
        return $this->succ('重新提交参数验证成功');
    }
    
    /**
     * 重新提交审批
     *
     * @param array $data
     * @param string $error_msg
     * @return array
     */
    public function resubmitApproval(&$data, &$error_msg=null)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        $nodeMdl = app::get('ticket')->model('workflow_node');
        $operLogObj = app::get('ome')->model('operation_log');
        
        // get case info
        $caseInfo = $caseMdl->dump($data['id']);
        
        // get first node
        $nodeList = $nodeMdl->getList('*', ['template_id'=>$caseInfo['template_id']], 0, 1, 'step_order ASC');
        if(empty($nodeList)){
            $error_msg = '审批流程模板没有配置节点';
            return $this->error($error_msg);
        }
        $firstNode = $nodeList[0];
        
        // update case status
        $updateData = [
            'status' => 'pending',
            'current_node_id' => $firstNode['id'],
            'start_time' => time(),
            'end_time' => null,
            'assignee_type' => $firstNode['assignee_type'],
            'assignee_id' => $firstNode['assignee_id'],
            'assignee_name' => $firstNode['assignee_name'],
        ];
        
        $rs = $caseMdl->update($updateData, array('id'=>$data['id']));
        if(is_bool($rs)) {
            $error_msg = '重新提交审批失败';
            return $this->error($error_msg);
        }
        
        // update order status
        $this->updateOrderBeforeApproval($caseInfo['original_id']);
        
        // log
        $log_msg = '审批已重新提交';
        $operLogObj->write_log('workflow_resubmit@ticket', $data['id'], $log_msg);
        
        return $this->succ('审批已重新提交');
    }
    
    /**
     * 导出审批记录
     *
     * @param array $data
     * @return array
     */
    public function exportApprovalRecords($data)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        $recordMdl = app::get('ticket')->model('workflow_record');
        
        // build filter
        $filter = [];
        if(!empty($data['status'])){
            $filter['status'] = $data['status'];
        }
        if(!empty($data['scene_type'])){
            $filter['scene_type'] = $data['scene_type'];
        }
        if(!empty($data['date_from'])){
            $filter['at_time|>='] = strtotime($data['date_from']);
        }
        if(!empty($data['date_to'])){
            $filter['at_time|<='] = strtotime($data['date_to']);
        }
        
        // get cases
        $caseList = $caseMdl->getList('*', $filter, 0, -1, 'id DESC');
        
        // build CSV
        $csv = "审批号,订单号,审批标题,场景类型,状态,提交人,提交时间,审批人,审批时间,审批意见\n";
        
        foreach($caseList as $case){
            // get records
            $records = $recordMdl->getList('*', ['id' => $case['id']], 0, -1, 'id DESC');
            
            $record = empty($records) ? [] : $records[0];
            
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $case['case_bn'],
                $case['original_bn'],
                $case['title'],
                $case['scene_type'],
                $case['status'],
                $case['submitter_name'],
                date('Y-m-d H:i:s', strtotime($case['at_time'])),
                empty($record) ? '' : $record['assignee_name'],
                empty($record) ? '' : date('Y-m-d H:i:s', $record['process_time']),
                empty($record) ? '' : $record['remark']
            );
        }
        
        return $this->succ('导出成功', $csv);
    }
    
    /**
     * 生成审批号
     *
     * @return string
     */
    private function generateCaseBn()
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        
        $prefix = 'WF' . date('Ymd');
        $maxBn = $caseMdl->getList('MAX(case_bn) as max_bn', array('case_bn|like' => $prefix . '%'));
        $maxNum = 0;
        
        if(isset($maxBn[0]['max_bn']) && $maxBn[0]['max_bn']){
            $maxNum = intval(substr($maxBn[0]['max_bn'], -4));
        }
        
        return $prefix . str_pad($maxNum + 1, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * 审批前更新订单状态
     *
     * @param int $order_id
     */
    private function updateOrderBeforeApproval($order_id)
    {
        $orderMdl = app::get('ome')->model('orders');
        
        // 设置订单为不可审核状态
        $orderMdl->update(['is_not_combine' => 1], ['order_id' => $order_id]);
    }
    
    /**
     * 审批通过后更新订单状态
     *
     * @param int $order_id
     */
    public function updateOrderAfterApproval($caseInfo)
    {
        $orderMdl = app::get('ome')->model('orders');
        $objectObj = app::get('ome')->model('order_objects');
        $itemObj = app::get('ome')->model('order_items');
        $salesMaterialMdl = app::get('material')->model('sales_material');
        $operLogObj = app::get('ome')->model('operation_log');
       
        $salesBasicMaterialLib = kernel::single('material_sales_material');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        // order_id
        $order_id = $caseInfo['original_id'];
        
        // order
        $result = $this->getOrderDetails($caseInfo);
        if($result['rsp'] != 'succ'){
            $error_msg = $result['error_msg'];
            return $this->error($error_msg);
        }
        
        $orderInfo = $result['data'];
        
        // update order
        $updateOrder = array('order_id'=>$order_id);
        $needUpdateFreezeItem = [];
        
        // addGiftList
        $addGiftList = $orderInfo['addGiftList'];
        if($addGiftList){
            // sales_material_bn
            $goodsBns = array_column($addGiftList, 'bn');
            
            $tempList = $salesMaterialMdl->getList('sm_id,sales_material_bn,sales_material_name,shop_id,sales_material_type', array('sales_material_bn'=>$goodsBns));
            
            $smList = array();
            foreach ($tempList as $key => $val)
            {
                $sm_id = $val['sm_id'];
                $sales_material_bn = $val['sales_material_bn'];
                
                //绑定的基础物料
                $bindInfo = $salesBasicMaterialLib->getBasicMBySalesMIds($sm_id);
                $bindInfo = $bindInfo[$sm_id];
                $bindInfo = $bindInfo ? array_column($bindInfo, null, 'bm_id') : null;
                if(empty($bindInfo)){
                    $error_msg = sprintf('替换商品[%s]没有绑定基础物料', $sales_material_bn);
                    return $this->error($error_msg);
                }
                
                $val['basicMaterial'] = $bindInfo;
                
                $smList[$sales_material_bn] = $val;
            }
            
            // 添加赠品到订单
            foreach ($addGiftList as $giftKey => $giftInfo)
            {
                $obj_type = $giftInfo['obj_type'];
                $goods_bn = $giftInfo['bn'];
                $obj_quantity = $giftInfo['quantity'];
                
                // 销售物料信息
                $salesMaterial = $smList[$goods_bn];
                
                //新增的商品绑定的基础物料
                $salesBasicMaterial = $salesMaterial['basicMaterial'];
                
                // sdf
                $new_order_object   = array(
                    'order_id'          => $order_id,
                    'obj_type'          => $obj_type,
                    'obj_alias'         => $obj_type,
                    'shop_goods_id'     => $obj_type == 'gift' ? '-1' : '0',
                    'bn'                => $goods_bn,
                    'name'              => $giftInfo['name'],
                    'goods_id'          => $salesMaterial['sm_id'],
                    'price'             => 0,
                    'quantity'          => $obj_quantity,
                    'amount'            => 0,
                    'pmt_price'         => 0,
                    'sale_price'        => 0,
                    'divide_order_fee'  => 0,
                    'part_mjz_discount' => 0,
                    'weight' => 0,
                );
                
                //订单item层明细
                $new_order_item = array();
                foreach ($salesBasicMaterial as $item)
                {
                    $item_type = ($obj_type == 'goods' ? 'product' : $obj_type);
                    
                    $new_order_item[] = array(
                        'order_id'          => $order_id,
                        'bn'                => $item['material_bn'],
                        'name'              => $item['material_name'],
                        'product_id'        => $item['bm_id'],
                        'price'             => 0,
                        'nums'              => intval($obj_quantity * $item['number']),
                        'original_unit_num' => $item['number'],
                        'amount'            => 0,
                        'pmt_price'         => 0,
                        'sale_price'        => 0,
                        'item_type'         => $item_type,
                        'divide_order_fee'  => 0,
                        'part_mjz_discount' => 0,
                        'weight' => 0,
                    );
                }
                
                // 新增商品的冻结
                foreach($new_order_item as $item)
                {
                    $item['operate_type'] = 'add';
                    $item['goods_id'] = $new_order_object['goods_id'];
                    
                    $needUpdateFreezeItem[] = $item;
                }
                
                $new_order_object['order_items'] = $new_order_item;
                
                $divideOrder = kernel::single('ome_order')->divide_objects_to_items(['order_objects'=>[$new_order_object]]);
                
                $updateOrder['order_objects'][]  = $divideOrder['order_objects'][0];
            }
        }
        
        //check
        if(empty($updateOrder['order_objects'])){
            $error_msg = sprintf('订单[%s]没有需要更新的object层数据', $orderInfo['order_bn']);
            return $this->error($error_msg);
        }
        
        // 更新订单明细
        foreach ($updateOrder['order_objects'] as $objKey => $objVal)
        {
            $order_items = $objVal['order_items'];
            if(empty($order_items)){
                $error_msg = sprintf('订单[%s]没有需要更新item层记录', $orderInfo['order_bn']);
                return $this->error($error_msg);
            }
            
            // unset
            unset($objVal['order_items']);
            
            // insert object
            $isSave = $objectObj->insert($objVal);
            if(!$isSave){
                $error_msg = sprintf('订单[%s]添加销售物料[%s]失败', $orderInfo['order_bn'], $objVal['bn']);
                return $this->error($error_msg);
            }
            
            // add items
            foreach ($order_items as $itemKey => $itemVal)
            {
                $itemVal['obj_id']= $objVal['obj_id'];
                
                // insert item
                $isSave = $itemObj->insert($itemVal);
                if(!$isSave){
                    $error_msg = sprintf('订单[%s]添加基础物料[%s]失败', $orderInfo['order_bn'], $itemVal['bn']);
                    return $this->error($error_msg);
                }
            }
        }
        
        // 增加冻结
        if($needUpdateFreezeItem) {
            uasort($needUpdateFreezeItem, [kernel::single('console_iostockorder'), 'cmp_productid']);
            
            $branchBatchList = [];
            foreach ($needUpdateFreezeItem as $item)
            {
                if ($item['operate_type'] == 'add') {
                    $freezeData = [];
                    $freezeData['bm_id'] = $item['product_id'];
                    $freezeData['sm_id'] = $item['goods_id'];
                    $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
                    $freezeData['bill_type'] = 0;
                    $freezeData['obj_id'] = $order_id;
                    $freezeData['shop_id'] = $orderInfo['shop_id'];
                    $freezeData['branch_id'] = 0;
                    $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
                    $freezeData['num'] = abs($item['nums']);
                    $freezeData['obj_bn'] = $orderInfo['order_bn'];
                    
                    $branchBatchList['+'][] = $freezeData;
                }
            }
            
            // 冻结
            if($branchBatchList['+']){
                $err = '';
                $basicMStockFreezeLib->freezeBatch($branchBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
            }
        }
        
        // [按位异或运算]去除[赠品审批]标识符
        $is_not_combine = $orderInfo['is_not_combine'] ^ ome_order_combine_const::__ORDER_GIFT_APPROVAL;
        
        // update
        $orderMdl->update(['is_not_combine'=>$is_not_combine], ['order_id'=>$orderInfo['order_id']]);
        
        // log
        $log_msg = '订单审批赠品成功!';
        $operLogObj->write_log('order_modify@ome', $order_id, $log_msg);
        
        return $this->succ($log_msg);
    }
    
    /**
     * 取消审批后更新订单状态
     *
     * @param int $order_id
     */
    private function updateOrderAfterCancel($order_id)
    {
        $orderMdl = app::get('ome')->model('orders');
        
        // 恢复订单可审核状态
        $orderMdl->update(['is_not_combine' => 0], ['order_id' => $order_id]);
    }
    
    /**
     * 获取指定单据是否正在审批中
     *
     * @param $original_bn
     * @param $bill_type
     * @return array
     */
    public function getOrderIsApproval($original_bn, $bill_type='order')
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        
        // 获取正在审批的单据
        $caceInfo = $caseMdl->dump(['original_bn'=>$original_bn, 'bill_type'=>$bill_type, 'status'=>['pending','processing']], 'id,case_bn,original_id,original_bn');
        
        return $caceInfo;
    }
    
}