<?php
/**
 * 零元补发单审批Lib方法类
 *
 * @author wangbiao@shopex.cn
 * @version 2026.04.14
 */
class ticket_workflow_case_bufa extends ticket_abstract
{
    const BPM_STATUS_NONE = 'none';
    const BPM_STATUS_PENDING = 'pending';
    const BPM_STATUS_APPROVED = 'approved';
    const BPM_STATUS_REJECTED = 'rejected';
    
    /**
     * 检查零元补发单审批参数
     *
     * @param array $params
     * @return array
     */
    public function checkCreateApprovalParams(&$params)
    {
        $orderMdl = app::get('ome')->model('orders');
        $templateMdl = app::get('ticket')->model('workflow_template');

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
        $orderRetailAmount = $this->getOrderApprovalAmount($params['order_id']);
        $seedCaseInfo = array(
            'original_id' => $params['order_id'],
            'scene_type' => 'approve_orders',
            'order_retail_amount' => $orderRetailAmount,
        );
        $firstNode = $this->resolveApprovalInitialNode($templateInfo, $seedCaseInfo, $error_msg);
        if (empty($firstNode)) {
            if ($error_msg === null) {
                $error_msg = '审批流程模板没有配置可用审批节点';
            }
            return $this->error($error_msg);
        }

        return $this->succ('创建审批参数验证成功');
    }

    /**
     * 创建零元补发单审批实例
     *
     * @param array $data
     * @return array
     */
    public function createApprovalCase(&$data)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        $templateMdl = app::get('ticket')->model('workflow_template');
        $orderMdl = app::get('ome')->model('orders');
        $operLogMdl = app::get('ome')->model('operation_log');
        
        $user = kernel::single('desktop_user');
        $op_id = $user->get_id();
        $op_name = $user->get_name();
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
        $case_bn = $this->createCaseBn();
        
        // 获取订单审批金额
        $orderRetailAmount = $this->getOrderApprovalAmount($order_id);
        
        // 创建审批实例之前，获取第一个需要审批的节点
        $seedCaseInfo = array(
            'original_id' => $order_id,
            'scene_type' => 'approve_orders',
            'order_retail_amount' => $orderRetailAmount,
        );
        $firstNode = $this->resolveApprovalInitialNode($templateInfo, $seedCaseInfo, $error_msg);
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
            'bill_type' => 'order',
            'scene_type' => 'approve_orders',
            'reason' => isset($orderInfo['bufa_reason']) ? trim($orderInfo['bufa_reason']) : '',
            'status' => 'pending',
            'template_id' => $templateInfo['id'],
            //'bpm_submitter' => $op_id, // 第三方提交人ID（请求BPM接口时使用）
            //'bpm_approver' => $op_name, // 第三方提交人姓名（请求BPM接口时使用）
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

        $assigneeResult = $this->resolveBufaOrderAssignee($orderInfo, $firstNode);
        if ($assigneeResult['rsp'] == 'succ') {
            $resolvedAssignee = $assigneeResult['data'];
            $caseData['assignee_type'] = $resolvedAssignee['assignee_type'];
            $caseData['assignee_id'] = $resolvedAssignee['assignee_id'];
            $caseData['assignee_name'] = $resolvedAssignee['assignee_name'];
        } else {
            $caseData['abnormal_status'] = 'true';
            $caseData['abnormal_msg'] = $assigneeResult['error_msg'];
            $caseData['assignee_type'] = '';
            $caseData['assignee_id'] = 0;
            $caseData['assignee_name'] = '';
        }
        
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
        if (!$this->markOrderBeforeApproval($order_id)) {
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
     * 根据订单店铺和审批节点匹配补发审批人。
     *
     * @param array $orderInfo
     * @param array $nodeInfo
     * @return array
     */
    protected function resolveBufaOrderAssignee($orderInfo, array $nodeInfo)
    {
        if (empty($orderInfo['shop_id'])) {
            return $this->error('订单未关联店铺，无法匹配补发审批人');
        }

        if (!$this->shouldResolveNodeAssignee($nodeInfo)) {
            return $this->succ('当前节点沿用模板审批人', array(
                'assignee_type' => $nodeInfo['assignee_type'] ?? 'user',
                'assignee_id' => $nodeInfo['assignee_id'] ?? 0,
                'assignee_name' => $nodeInfo['assignee_name'] ?? '',
            ));
        }
        
        // 根据店铺+审批节点，获取审批人信息
        $businessLib = kernel::single('ticket_workflow_business');
        $result = $businessLib->getApproverInfo($orderInfo['shop_id'], $nodeInfo['id']);
        if ($result['rsp'] != 'succ') {
            return $result;
        }
        
        return $this->succ('匹配补发审批人成功', array(
            'assignee_type' => 'user',
            'assignee_id' => $result['data']['approver_id'],
            'assignee_name' => $result['data']['approver_name'],
            'bpm_submitter' => $result['data']['bpm_submitter'],
            'bpm_approver' => $result['data']['bpm_approve_user'],
        ));
    }

    protected function shouldResolveNodeAssignee(array $nodeInfo)
    {
        if (empty($nodeInfo['id'])) {
            return false;
        }

        if (($nodeInfo['node_type'] ?? '') != 'approval') {
            return false;
        }

        if (strtoupper(trim((string) ($nodeInfo['assignee_name'] ?? ''))) === 'BPM') {
            return false;
        }

        return true;
    }

    /**
     * 打开补发审批页前是否需要重新解析店铺审批人（读 bufa_approval_mapping 等）。
     * 异常实例，或当前节点需按店铺解析但尚未分配审批人时，返回 true。
     *
     * @param array $caseInfo workflow_case 行
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
        $nodeInfo = $nodeMdl->dump(array('id' => $caseInfo['current_node_id']), '*');
        if (empty($nodeInfo)) {
            return false;
        }

        if (!$this->shouldResolveNodeAssignee($nodeInfo)) {
            return false;
        }

        $assigneeId = isset($caseInfo['assignee_id']) ? intval($caseInfo['assignee_id']) : 0;

        return $assigneeId <= 0;
    }
    
    /**
     *  刷新补发审批人，如果没有则实时读取店铺审批人配置，并更新审批人
     *
     * @param $caseInfo
     * @return array
     */
    public function refreshApprovalCaseAssignee(&$caseInfo)
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        $nodeMdl = app::get('ticket')->model('workflow_node');
        $orderMdl = app::get('ome')->model('orders');

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
        
        // 获取店铺审批人信息
        $assigneeResult = $this->resolveBufaOrderAssignee($orderInfo, $nodeInfo);
        if ($assigneeResult['rsp'] != 'succ') {
            $this->markCaseAbnormal($caseInfo['id'], $assigneeResult['error_msg']);
            return $this->error($assigneeResult['error_msg']);
        }
        
        // 审批人信息
        $resolvedAssignee = $assigneeResult['data'];
        
        // update
        $updateData = array(
            'abnormal_status' => 'false',
            'abnormal_msg' => '',
            'assignee_type' => $resolvedAssignee['assignee_type'],
            'assignee_id' => $resolvedAssignee['assignee_id'],
            'assignee_name' => $resolvedAssignee['assignee_name'],
            'bpm_submitter' => $resolvedAssignee['bpm_submitter'],
            'bpm_approver' => $resolvedAssignee['bpm_approver'],
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
     * 获取零元补发单审批金额(基础物料零售价 * 订单明细购买数量)
     *
     * @param int $order_id
     * @return float
     */
    public function getOrderApprovalAmount($order_id)
    {
        $order_id = intval($order_id);
        if ($order_id <= 0) {
            return 0;
        }

        $orderMdl = app::get('ome')->model('orders');
        $orderInfo = $orderMdl->dump(
            array('order_id' => $order_id),
            '*',
            array('order_objects' => array('*', array('order_items' => array('*'))))
        );
        if (empty($orderInfo['order_objects']) || !is_array($orderInfo['order_objects'])) {
            return 0;
        }

        $quantityMap = array();
        foreach ($orderInfo['order_objects'] as $object) {
            if (empty($object['order_items']) || !is_array($object['order_items'])) {
                continue;
            }

            foreach ($object['order_items'] as $item) {
                $productId = isset($item['product_id']) ? intval($item['product_id']) : 0;
                if ($productId <= 0) {
                    continue;
                }

                $quantity = isset($item['nums']) ? $item['nums'] : ($item['quantity'] ?? 0);
                if (!isset($quantityMap[$productId])) {
                    $quantityMap[$productId] = 0;
                }
                $quantityMap[$productId] += (float)$quantity;
            }
        }

        if (empty($quantityMap)) {
            return 0;
        }
        
        // 获取基础物料零售价
        $basicMaterialExtMdl = app::get('material')->model('basic_material_ext');
        $basicMaterialList = $basicMaterialExtMdl->getList('bm_id,retail_price', array('bm_id' => array_keys($quantityMap)));
        $retailPriceMap = array();
        foreach ($basicMaterialList as $basicMaterial) {
            $retailPriceMap[$basicMaterial['bm_id']] = isset($basicMaterial['retail_price']) ? $basicMaterial['retail_price'] : 0;
        }
        
        // 计算金额
        $totalAmount = 0;
        foreach ($quantityMap as $productId => $quantity) {
            $retailPrice = isset($retailPriceMap[$productId]) ? $retailPriceMap[$productId] : 0;
            $totalAmount += (float)$retailPrice * (float)$quantity;
        }
        
        return round($totalAmount, 3);
    }
    
    /**
     * 生成审批实例编号
     *
     * @return string
     */
    public function createCaseBn()
    {
        $caseMdl = app::get('ticket')->model('workflow_case');
        
        // 审批号
        $case_bn = uniqid('WF'. date('Y'));
        
        // 检查单据号是否存在
        $caseInfo = $caseMdl->dump(array('case_bn'=>$case_bn), 'id');
        if($caseInfo){
            $case_bn = uniqid('WF');
        }
        
        return $case_bn;
    }
    
    /**
     * 创建审批实例之前，获取第一个需要审批的节点
     *
     * @param $templateInfo
     * @param $caseInfo
     * @param $error_msg
     * @return array|mixed|null
     */
    public function resolveApprovalInitialNode($templateInfo, $caseInfo, &$error_msg = null)
    {
        $nodeMdl = app::get('ticket')->model('workflow_node');
        $nodeLib = kernel::single('ticket_workflow_node');
        
        // 获取流程开始节点：start
        $startNode = $nodeLib->getStartNode($templateInfo['id']);
        if(empty($startNode)){
            $error_msg = '审批流程模板没有配置开始节点';
            return null;
        }
        
        if(empty($startNode['condition_true_next_alias'])){
            $error_msg = '开始节点没有配置下一个节点';
            return null;
        }
        
        $nextAliasList = $nodeMdl->getList('*', array('node_alias'=>$startNode['condition_true_next_alias']), 0, -1, 'step_order ASC,id ASC');
        if(empty($nextAliasList)){
            $error_msg = '通过开始节点，没有获取到下一个节点';
            return null;
        }
        $nextNodeInfo = $nextAliasList[0];
        
        // 如果节点是条件节点，则需要判断是否符合条件
        if($nextNodeInfo['node_type'] == 'condition'){
            // 根据条件节点获取目标节点信息：需要审批的节点
            $firstNode = $this->getConditionNodeTargetInfo($nextNodeInfo, $caseInfo);
        }else{
            // 直接取此节点
            $firstNode = $nextNodeInfo;
        }
        
        return $firstNode;
    }
    
    /**
     * 给订单添加审批锁定状态（添加禁止审批标记）
     *
     * @param $order_id
     * @param $sceneType
     * @return bool
     */
    public function markOrderBeforeApproval($order_id, $sceneType = 'approve_orders')
    {
        return $this->addOrderApprovalFlag($order_id, $sceneType);
    }

    public function getCaseOrderApprovalAmount($caseInfo)
    {
        if (isset($caseInfo['order_retail_amount']) && $caseInfo['order_retail_amount'] !== '' && $caseInfo['order_retail_amount'] !== null) {
            return (float)$caseInfo['order_retail_amount'];
        }

        return $this->getOrderApprovalAmount($caseInfo['original_id']);
    }

    private function getSceneCombineFlag($sceneType)
    {
        switch ($sceneType) {
            case 'approve_orders':
                return ome_order_combine_const::__BUFA_ORDER;
            case 'add_gift':
                return ome_order_combine_const::__ORDER_GIFT_APPROVAL;
            default:
                return 0;
        }
    }

    private function addOrderApprovalFlag($order_id, $sceneType)
    {
        $flag = $this->getSceneCombineFlag($sceneType);
        if (!$flag) {
            return true;
        }

        $orderMdl = app::get('ome')->model('orders');
        $orderInfo = $orderMdl->dump($order_id, 'order_id,is_not_combine');
        if (empty($orderInfo)) {
            return false;
        }

        $currentFlag = intval($orderInfo['is_not_combine']);
        if (($currentFlag & $flag) == $flag) {
            return true;
        }

        $nextFlag = $currentFlag > 0 ? ($currentFlag | $flag) : $flag;
        return (bool)$orderMdl->update(array('is_not_combine' => $nextFlag), array('order_id' => $orderInfo['order_id']));
    }

    private function getTemplateNodes($template_id)
    {
        $nodeMdl = app::get('ticket')->model('workflow_node');
        return $nodeMdl->getList('*', array('template_id' => $template_id), 0, -1, 'step_order ASC,id ASC');
    }
    
    /**
     * 获取下一个审批节点
     *
     * @param array $currentNode
     * @param array $nodeList
     * @return mixed|null
     */
    private function getLinearNextNodeByNode(array $currentNode, array $nodeList)
    {
        $matchedCurrent = false;
        foreach ($nodeList as $node) {
            if ($matchedCurrent) {
                return $node;
            }
            if ((int)$node['id'] === (int)$currentNode['id']) {
                $matchedCurrent = true;
            }
        }

        return null;
    }

    private function findNodeByAlias($template_id, $nodeAlias, array $nodeList = array())
    {
        $nodeAlias = trim((string)$nodeAlias);
        if ($nodeAlias === '') {
            return null;
        }

        if (empty($nodeList)) {
            $nodeList = $this->getTemplateNodes($template_id);
        }

        foreach ($nodeList as $node) {
            if (($node['node_alias'] ?? '') === $nodeAlias) {
                return $node;
            }
        }

        return null;
    }

    private function evaluateSingleConditionRule(array $rule, array $context)
    {
        $field = $rule['field'] ?? ($rule['field_name'] ?? '');
        $operator = $rule['operator'] ?? ($rule['op'] ?? '=');
        $expected = $rule['value'] ?? ($rule['target_value'] ?? null);
        $actual = $context[$field] ?? null;

        if ($field === '' || $actual === null) {
            return false;
        }

        $actual = is_numeric($actual) ? (float)$actual : $actual;
        $expected = is_numeric($expected) ? (float)$expected : $expected;

        switch ($operator) {
            case '>':
                return $actual > $expected;
            case '>=':
                return $actual >= $expected;
            case '<':
                return $actual < $expected;
            case '<=':
                return $actual <= $expected;
            case '!=':
            case '<>':
                return $actual != $expected;
            case '=':
            case '==':
            default:
                return $actual == $expected;
        }
    }

    private function buildConditionContext($caseInfo)
    {
        return array(
            'order_amount' => $this->getCaseOrderApprovalAmount($caseInfo),
        );
    }
    
    /**
     * 根据条件节点获取目标节点信息：需要审批的节点
     *
     * @param array $conditionNode
     * @param array $caseInfo
     * @param array $nodeList
     * @return mixed|null
     */
    private function getConditionNodeTargetInfo(array $conditionNode, array $caseInfo, array $nodeList = array())
    {
        $nodeLib = kernel::single('ticket_workflow_node');
        
        // json_decode规则字符串
        $rules = $nodeLib->decodeJsonField($conditionNode['condition_rules'] ?? null);
        $ruleList = array();
        if (is_array($rules) && isset($rules['true']) && is_array($rules['true'])) {
            $ruleList = $rules['true'];
        }
        
        // 获取审批金额
        $context = $this->buildConditionContext($caseInfo);
        
        // 根据规则匹配
        $isMatched = !empty($ruleList);
        foreach ($ruleList as $rule) {
            if (!is_array($rule) || !$this->evaluateSingleConditionRule($rule, $context)) {
                $isMatched = false;
                break;
            }
        }
        
        // True分支目标别名：condition_true_next_alias，False分支目标别名：condition_false_next_alias
        $targetAlias = $isMatched
            ? ($conditionNode['condition_true_next_alias'] ?? '')
            : ($conditionNode['condition_false_next_alias'] ?? '');
        
        // 获取目标节点信息
        $nodeInfo = $this->findNodeByAlias($conditionNode['template_id'], $targetAlias, $nodeList);
        
        return $nodeInfo;
    }

    private function resolveNonConditionTargetNode(array $currentNode, array $nodeList)
    {
        $targetAlias = trim((string)($currentNode['condition_true_next_alias'] ?? ''));
        if ($targetAlias !== '') {
            $targetNode = $this->findNodeByAlias($currentNode['template_id'], $targetAlias, $nodeList);
            if (!empty($targetNode)) {
                return $targetNode;
            }
        }

        return $this->getLinearNextNodeByNode($currentNode, $nodeList);
    }
    
    /**
     * 根据当前审批实例，获取下一个节点
     *
     * @param array $caseInfo
     * @return mixed|null
     */
    public function getAuditNextNodeByCaseInfo(array $caseInfo)
    {
        if (empty($caseInfo['current_node_id'])) {
            return null;
        }

        $currentNode = $this->getNodeById($caseInfo['current_node_id']);
        if (empty($currentNode)) {
            return null;
        }

        $nodeList = $this->getTemplateNodes($currentNode['template_id']);
        if (empty($nodeList)) {
            return null;
        }

        if ($currentNode['node_type'] == 'condition') {
            return $this->getConditionNodeTargetInfo($currentNode, $caseInfo, $nodeList);
        }

        $nextNode = $this->resolveNonConditionTargetNode($currentNode, $nodeList);
        if (empty($nextNode)) {
            return null;
        }

        if ($nextNode['node_type'] == 'condition') {
            return $this->getConditionNodeTargetInfo($nextNode, $caseInfo, $nodeList);
        }

        return $nextNode;
    }
    
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
        
        // order info
        $orderInfo = $orderMdl->dump(['order_id'=>$order_id], '*', array('order_objects'=>array('*',array('order_items'=>array('*')))));
        if(empty($orderInfo)){
            return $this->error('订单信息不存在,请检查！');
        }
        
        if(empty($orderInfo['order_objects'])){
            return $this->error('订单明细列表不存在,请检查！');
        }
        
        // 获取审批金额
        $orderInfo['order_retail_amount'] = $this->getOrderApprovalAmount($order_id);
        
        // 获取审批的明细
        $orderInfo['approvalItemList'] = $this->flattenOrderItems($orderInfo);
        
        $orderInfo['addGiftList'] = array();
        
        return $this->succ('操作成功', $orderInfo);
    }
    
    private function flattenOrderItems($orderInfo)
    {
        $items = array();
        if (empty($orderInfo['order_objects']) || !is_array($orderInfo['order_objects'])) {
            return $items;
        }
        
        foreach ($orderInfo['order_objects'] as $object)
        {
            if (empty($object['order_items']) || !is_array($object['order_items'])) {
                continue;
            }
            
            foreach ($object['order_items'] as $item)
            {
                $quantity = isset($item['nums']) ? $item['nums'] : ($item['quantity'] ?? 0);
                $price = isset($item['price']) ? $item['price'] : 0;
                $amount = isset($item['amount']) ? $item['amount'] : round((float)$price * (float)$quantity, 3);
                $items[] = array(
                    'name' => $item['name'] ?? ($object['name'] ?? ''),
                    'bn' => $item['bn'] ?? '',
                    'spec_info' => $item['spec_info'] ?? ($object['spec_info'] ?? ''),
                    'quantity' => $quantity,
                    'price' => $price,
                    'amount' => $amount,
                );
            }
        }
        
        return $items;
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
        
        if(in_array($caseInfo['current_node_type'], ['end'])){
            $error_msg = '审批已结束,无法审批';
            return $this->error($error_msg);
        }
        
        if($this->isBpmPending($caseInfo)){
            $error_msg = '该审批单正在等待BPM三级审批结果，暂不允许OMS继续审批';
            return $this->error($error_msg);
        }

        if (!empty($caseInfo['abnormal_status']) && $caseInfo['abnormal_status'] != 'false') {
            $error_msg = empty($caseInfo['abnormal_msg']) ? '审批实例存在异常，请联系管理员处理' : $caseInfo['abnormal_msg'];
            return $this->error($error_msg);
        }
        
        // order info
        $result = $this->getOrderDetails($caseInfo);
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
            $error_msg = '您不是当前审批人，没有权限进行审批!';
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
    
    public function isBpmPending($caseInfo)
    {
        return !empty($caseInfo['bpm_status']) && $caseInfo['bpm_status'] == self::BPM_STATUS_PENDING;
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
        
        // 获取提交人信息（实时获取，防止映射关系被修改）
        $businessLib = kernel::single('ticket_workflow_business');
        $getAssigneeResult = $businessLib->getApproverInfo($orderInfo['shop_id'], $nodeInfo['id']);
        if($getAssigneeResult['rsp'] != 'succ'){
            $error_msg = '获取提交人信息失败：' . $getAssigneeResult['error_msg'];
            return $this->error($error_msg);
        }
        
        $bpm_submitter = $getAssigneeResult['data']['bpm_submitter'];
        $bpm_approver = $getAssigneeResult['data']['bpm_approver'];
        
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
        }
        
        // reset
        $caseInfo['bpm_submitter'] = $bpm_submitter;
        $caseInfo['bpm_approver'] = ($bpm_approver ? $bpm_approver : $op_name);
        
        // check
        if(empty($caseInfo['bpm_submitter']) || empty($caseInfo['bpm_approver'])){
            $error_msg = '提交人信息不存在，请先进行配置!';
            return $this->error($error_msg);
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
            // 根据当前审批实例，获取下一个节点
            $nextNode = $this->getAuditNextNodeByCaseInfo($caseInfo);
            
            // 开始审批
            if ($this->shouldStartBpmApproval($caseInfo, $nextNode)) {
                // 开始请求BPM审批
                $bpmResult = $this->startBpmApproval($caseInfo, $nextNode);
                if ($bpmResult['rsp'] != 'succ') {
                    $error_msg = $bpmResult['error_msg'];
                    return $this->error($error_msg);
                }else{
                    // BPM实例号
                    if(isset($bpmResult['data']['instance_no'])){
                        $updateData['bpm_instance_no'] = $bpmResult['data']['instance_no'];
                    }
                }
                
                // 是否需要BPM三级审批
                $updateData['need_bpm_approval'] = 'true';
                
                // update
                $updateData['status'] = 'processing';
                $updateData['current_node_id'] = $nextNode['id'];
                $updateData['current_node_type'] = $nextNode['node_type'];
                $updateData['assignee_type'] = 'bpm';
                $updateData['assignee_id'] = 0;
                $updateData['assignee_name'] = $nextNode['assignee_name'];
                $updateData['bpm_status'] = self::BPM_STATUS_PENDING;
                
                // 提交人信息
                if(empty($caseInfo['bpm_submitter'])){
                    $updateData['bpm_submitter'] = $op_id;
                }
                
                if(empty($caseInfo['bpm_approver'])){
                    $updateData['bpm_approver'] = $op_name;
                }
            } elseif(empty($nextNode) || $nextNode['node_type'] == 'end'){
                // 审批完成
                $updateData['status'] = 'approved';
                $updateData['current_node_id'] = empty($nextNode) ? 0 : $nextNode['id'];
                $updateData['current_node_type'] = empty($nextNode) ? 'end' : $nextNode['node_type'];
                $updateData['end_time'] = time();
                $updateData['bpm_status'] = $updateData['bpm_status'] ?? self::BPM_STATUS_APPROVED;
            }else{
                $assigneeResult = $this->resolveBufaOrderAssignee($orderInfo, $nextNode);
                if ($assigneeResult['rsp'] != 'succ') {
                    $error_msg = $assigneeResult['error_msg'];
                    return $this->error($error_msg);
                }
                $resolvedAssignee = $assigneeResult['data'];

                // 还有下一个节点
                $updateData['status'] = 'processing';
                $updateData['current_node_id'] = $nextNode['id'];
                $updateData['current_node_type'] = $nextNode['node_type'];
                $updateData['assignee_type'] = $resolvedAssignee['assignee_type'];
                $updateData['assignee_id'] = $resolvedAssignee['assignee_id'];
                $updateData['assignee_name'] = $resolvedAssignee['assignee_name'];
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
        }elseif($this->isBpmPending($updateData)){
            $log_msg = 'OMS二级审批完成；自动发起BPM三级审批，等待回调结果';
        }else{
            $log_msg = '审批节点：'. ($noteTypeList[$nodeInfo['node_type']] ?? $nodeInfo['node_type']) .'('. ($statusList[$data['status']] ?? $data['status']) .')，审批意见：'. $data['remark'];
        }
        
        // log
        $operLogMdl->write_log('order_workflow_case_bufa@wms', $data['id'], $log_msg);
        
        // [拒绝时]更新订单状态
        if(in_array($updateData['status'], array('rejected', 'cancelled', 'source_cancelled'), true)) {
            $isResult = $this->updateOrderAfterCancel($caseInfo['original_id'], $caseInfo['scene_type']);
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
            $result = $this->applySceneApprovalCompletion($caseInfo);
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
    
    private function getNodeById($node_id)
    {
        $nodeMdl = app::get('ticket')->model('workflow_node');
        return $nodeMdl->dump(array('id' => $node_id), '*');
    }
    
    private function shouldStartBpmApproval($caseInfo, $nextNode)
    {
        if ($caseInfo['scene_type'] != 'approve_orders') {
            return false;
        }
        
        if (empty($nextNode) || $nextNode['node_type'] != 'approval') {
            return false;
        }
        
        // 是否请求BPM接口进行审批
        if (!$this->caseNeedsBpmApproval($caseInfo, $nextNode)) {
            return false;
        }
        
        return $this->countApprovedApprovalRecords($caseInfo['id']) >= 2;
    }
    
    /**
     * 是否请求BPM接口进行审批
     *
     * @param $caseInfo
     * @param $nextNode
     * @return bool
     */
    private function caseNeedsBpmApproval($caseInfo, $nextNode)
    {
        if(strtoupper($nextNode['assignee_name']) == 'BPM'){
            return true;
        }
        
        return false;
    }
    
    private function countApprovedApprovalRecords($case_id)
    {
        $recordMdl = app::get('ticket')->model('workflow_record');
        return intval($recordMdl->count(array(
            'case_id' => $case_id,
            'action' => 'approval',
            'status' => 'approved',
        )));
    }
    
    /**
     * 开始请求BPM审批
     *
     * @param $caseInfo
     * @param $bpmNode
     * @return array
     */
    public function startBpmApproval($caseInfo, $bpmNode)
    {
        $nodeMdl = app::get('ticket')->model('workflow_node');
        
        // 获取订单详情
        $orderResult = $this->getOrderDetails($caseInfo);
        if ($orderResult['rsp'] != 'succ') {
            $error_msg = '获取订单详情失败：' . $orderResult['error_msg'];
            return $this->error($error_msg);
        }
        
        // check
        $orderInfo = $orderResult['data'];
        if(empty($orderInfo)){
            $error_msg = '获取审批的订单信息失败!';
            return $this->error($error_msg);
        }
        
        // 审批流节点
        $nodeInfo = $nodeMdl->dump(['id'=>$caseInfo['current_node_id']], '*');
        if(empty($nodeInfo)){
            $error_msg = '请求BPM失败：未找到审批的节点';
            return $this->error($error_msg);
        }
        
        // 获取提交人信息（根据店铺和审批节点解析审批人）
        $businessLib = kernel::single('ticket_workflow_business');
        $getAssigneeResult = $businessLib->getApproverInfo($orderInfo['shop_id'], $nodeInfo['id']);
        if($getAssigneeResult['rsp'] != 'succ'){
            $error_msg = '获取BPM审批人失败：' . $getAssigneeResult['error_msg'];
            return $this->error($error_msg);
        }
        
        // check
        $approvalMappingInfo = $getAssigneeResult['data'];
        if(empty($approvalMappingInfo)){
            $error_msg = 'BPM审批人未配置，请检查!';
            return $this->error($error_msg);
        }
        
        // 获取BPM审批人
        $bpmNode['assignee_id'] = $approvalMappingInfo['bpm_submitter'];
        $bpmNode['assignee_name'] = $approvalMappingInfo['bpm_approver'];
        
        // check
        if (empty($bpmNode['assignee_id'])) {
            $error_msg = 'BPM三级审批节点未配置审批人';
            return $this->error($error_msg);
        }
        
        // 请求参数
        $params = [
            'case_info' => $caseInfo, //审批流程信息
            'node_info' => $nodeInfo, // 审批流节点
            'order_details' => $orderInfo, //订单详细信息
            'bpmNode' => $bpmNode, //BPM节点信息
        ];
        
        // Set Result
        $requestResult = $this->error('未发起BRPM接口请求');
        
        // 注册Service，请求BPM接口方法
        foreach(kernel::servicelist('nipponpaint.service.bpm.ticket') as $object)
        {
            if(method_exists($object, 'requestBpmApproval')){
                $requestResult = $object->requestBpmApproval($params);
            }
        }
        
        return $requestResult;
    }
    
    /**
     * 取消审批后更新订单状态
     *
     * @param int $order_id
     */
    private function updateOrderAfterCancel($order_id, $sceneType = 'approve_orders')
    {
        return $this->removeOrderApprovalFlag($order_id, $sceneType);
    }
    
    public function applySceneApprovalCompletion($caseInfo)
    {
        switch ($caseInfo['scene_type']) {
            case 'approve_orders':
                return $this->completeApproveOrdersApproval($caseInfo);
            default:
                return $this->succ('无需额外处理');
        }
    }
    
    /**
     * 审批完成，恢复订单可审核状态，并且延迟5分钟自动重新路由审核订单
     *
     * @param $caseInfo
     * @param $error_msg
     * @return array
     */
    private function completeApproveOrdersApproval($caseInfo)
    {
        // 恢复订单可审核状态（去除禁止审批标记）
        $released = $this->removeOrderApprovalFlag($caseInfo['original_id'], $caseInfo['scene_type']);
        if (!$released) {
            $error_msg = '恢复订单审核状态失败';
            return $this->error($error_msg);
        }
        
        $orderMdl = app::get('ome')->model('orders');
        $orderInfo = $orderMdl->dump($caseInfo['original_id']);
        if (empty($orderInfo)) {
            $error_msg = '订单不存在，无法进入自动审单';
            return $this->error($error_msg);
        }
        
        // 延迟5分钟自动重新路由审核订单
        $autoSdf = array('op_type'=>'timing_confirm', 'timing_time'=>strtotime('5 minutes'), 'memo'=>'零元订单补发单审批完成');
        
        //@todo：当系统设置里未开启[是否开启系统自动审核]时，未调用延迟自动审核;
        $autoSdf['cnAuto'] = 'true';
        
        // 调用自动延迟审单
        $combineResult = kernel::single('ome_order')->auto_order_combine($caseInfo['original_id'], $autoSdf);
        if ($combineResult === false) {
            $error_msg = '自动审单调用失败';
            return $this->error($error_msg);
        }
        
        return $this->succ('补发单审批完成并已进入自动审单');
    }
    
    /**
     * 恢复订单可审核状态（去除禁止审批标记）
     *
     * @param $order_id
     * @param $sceneType
     * @return bool
     */
    public function removeOrderApprovalFlag($order_id, $sceneType)
    {
        $flag = $this->getSceneCombineFlag($sceneType);
        if (!$flag) {
            return true;
        }
        
        $orderMdl = app::get('ome')->model('orders');
        $orderInfo = $orderMdl->dump($order_id, 'order_id,is_not_combine');
        if (empty($orderInfo)) {
            return false;
        }
        
        $currentFlag = intval($orderInfo['is_not_combine']);
        if (($currentFlag & $flag) != $flag) {
            return true;
        }
        
        $nextFlag = $currentFlag ^ $flag;
        
        return (bool)$orderMdl->update(array('is_not_combine' => $nextFlag), array('order_id' => $orderInfo['order_id']));
    }
}