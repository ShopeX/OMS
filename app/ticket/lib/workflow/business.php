<?php
/**
 * 审批流相关业务数据Lib方法类
 *
 * @author wangbiao@shopex.cn
 * @version 2026.04.14
 */
class ticket_workflow_business extends ticket_abstract
{
    const BPM_STATUS_NONE = 'none';
    const BPM_STATUS_PENDING = 'pending';
    const BPM_STATUS_APPROVED = 'approved';
    const BPM_STATUS_REJECTED = 'rejected';
    
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
     * 根据店铺ID获取审批人信息
     *
     * @param $shop_id
     * @param array $nodeInfo
     * @param string|null $error_msg
     * @return array|false
     */
    public function getShopByApprover($shop_id, $nodeInfo, &$error_msg = null)
    {
        if (empty($shop_id)) {
            $error_msg = '订单未关联店铺，无法匹配补发审批人';
            return false;
        }

        if (!$this->shouldResolveNodeAssignee($nodeInfo)) {
            return array(
                'assignee_type' => $nodeInfo['assignee_type'] ?? 'bpm',
                'assignee_id' => $nodeInfo['assignee_id'] ?? 0,
                'assignee_name' => $nodeInfo['assignee_name'] ?? '',
                'bpm_submitter' => '',
                'bpm_approver' => '',
            );
        }
        
        // 根据店铺+审批节点，获取审批人信息
        $result = $this->getApproverInfo($shop_id, $nodeInfo['id']);
        if ($result['rsp'] != 'succ') {
            $error_msg = $result['error_msg'];
            return false;
        }
        
        return array(
            'assignee_type' => 'user',
            'assignee_id' => $result['data']['approver_id'],
            'assignee_name' => $result['data']['approver_name'],
            'bpm_submitter' => $result['data']['bpm_submitter'], // BPM提交人
            'bpm_approver' => $result['data']['bpm_approve_user'], // BPM审批人
        );
    }

    /**
     * OMS 审批节点是否需按店铺解析审批人
     *
     * @param array $nodeInfo
     * @return bool
     */
    public function shouldResolveNodeAssignee(array $nodeInfo)
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
     * 下一节点是否为 BPM 审批节点
     *
     * @param array $nextNode
     * @return bool
     */
    public function caseNeedsBpmApproval($nextNode)
    {
        if (empty($nextNode) || ($nextNode['node_type'] ?? '') != 'approval') {
            return false;
        }
        
        $is_bpm_node = false;
        
        // 审批人姓名是否：BPM
        $nextNode['assignee_name'] = trim($nextNode['assignee_name']);
        if($nextNode['assignee_name']){
            if(strtoupper($nextNode['assignee_name']) === 'BPM'){
                $is_bpm_node = true;
            }
        }
        
        // 节点名称是否包含：BPM
        $nextNode['node_name'] = trim($nextNode['node_name']);
        if($nextNode['node_name']){
            if(strpos($nextNode['node_name'], 'BPM') !== false){
                $is_bpm_node = true;
            }
        }
        
        return $is_bpm_node;
    }

    /**
     * 统计已通过 OMS 审批节点次数
     *
     * @param int $case_id
     * @return int
     */
    public function countApprovedApprovalRecords($case_id)
    {
        $recordMdl = app::get('ticket')->model('workflow_record');

        return intval($recordMdl->count(array(
            'case_id' => $case_id,
            'action' => 'approval',
            'status' => 'approved',
        )));
    }

    /**
     * 获取审批实例金额快照
     *
     * @param array $caseInfo
     * @return float
     */
    public function getCaseOrderApprovalAmount($caseInfo)
    {
        if (isset($caseInfo['order_retail_amount']) && $caseInfo['order_retail_amount'] !== '' && $caseInfo['order_retail_amount'] !== null) {
            return (float) $caseInfo['order_retail_amount'];
        }

        return $this->getOrderApprovalAmount($caseInfo['original_id']);
    }

    /**
     * 给订单添加审批锁定状态
     *
     * @param int $order_id
     * @param string $sceneType
     * @return bool
     */
    public function markOrderBeforeApproval($order_id, $sceneType = 'approve_orders')
    {
        return $this->addOrderApprovalFlag($order_id, $sceneType);
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

        return (bool) $orderMdl->update(array('is_not_combine' => $nextFlag), array('order_id' => $orderInfo['order_id']));
    }

    /**
     * 根据当前审批实例，获取下一个节点
     *
     * @param array $caseInfo
     * @return array|null
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
     * 根据条件节点获取目标节点信息
     *
     * @param array $conditionNode
     * @param array $caseInfo
     * @param array $nodeList
     * @return array|null
     */
    public function getConditionNodeTargetInfo(array $conditionNode, array $caseInfo, array $nodeList = array())
    {
        $nodeLib = kernel::single('ticket_workflow_node');

        $rules = $nodeLib->decodeJsonField($conditionNode['condition_rules'] ?? null);
        $ruleList = array();
        if (is_array($rules) && isset($rules['true']) && is_array($rules['true'])) {
            $ruleList = $rules['true'];
        }

        $context = $this->buildConditionContext($caseInfo);

        $isMatched = !empty($ruleList);
        foreach ($ruleList as $rule) {
            if (!is_array($rule) || !$this->evaluateSingleConditionRule($rule, $context)) {
                $isMatched = false;
                break;
            }
        }

        $targetAlias = $isMatched
            ? ($conditionNode['condition_true_next_alias'] ?? '')
            : ($conditionNode['condition_false_next_alias'] ?? '');

        return $this->findNodeByAlias($conditionNode['template_id'], $targetAlias, $nodeList);
    }

    private function getNodeById($node_id)
    {
        $nodeMdl = app::get('ticket')->model('workflow_node');

        return $nodeMdl->dump(array('id' => $node_id), '*');
    }

    private function getTemplateNodes($template_id)
    {
        $nodeMdl = app::get('ticket')->model('workflow_node');

        return $nodeMdl->getList('*', array('template_id' => $template_id), 0, -1, 'step_order ASC,id ASC');
    }

    private function findNodeByAlias($template_id, $nodeAlias, array $nodeList = array())
    {
        $nodeAlias = trim((string) $nodeAlias);
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

    private function getLinearNextNodeByNode(array $currentNode, array $nodeList)
    {
        $matchedCurrent = false;
        foreach ($nodeList as $node) {
            if ($matchedCurrent) {
                return $node;
            }
            if ((int) $node['id'] === (int) $currentNode['id']) {
                $matchedCurrent = true;
            }
        }

        return null;
    }

    private function resolveNonConditionTargetNode(array $currentNode, array $nodeList)
    {
        $targetAlias = trim((string) ($currentNode['condition_true_next_alias'] ?? ''));
        if ($targetAlias !== '') {
            $targetNode = $this->findNodeByAlias($currentNode['template_id'], $targetAlias, $nodeList);
            if (!empty($targetNode)) {
                return $targetNode;
            }
        }

        return $this->getLinearNextNodeByNode($currentNode, $nodeList);
    }

    private function buildConditionContext($caseInfo)
    {
        return array(
            'order_amount' => $this->getCaseOrderApprovalAmount($caseInfo),
        );
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

        $actual = is_numeric($actual) ? (float) $actual : $actual;
        $expected = is_numeric($expected) ? (float) $expected : $expected;

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
        
        $filter = array('order_id' => $order_id);
        $orderInfo = $orderMdl->dump($filter, '*', array('order_objects' => array('*', array('order_items' => array('*')))));
        if (empty($orderInfo['order_objects']) || !is_array($orderInfo['order_objects'])) {
            return 0;
        }
        
        $quantityMap = array();
        foreach ($orderInfo['order_objects'] as $object)
        {
            if (empty($object['order_items']) || !is_array($object['order_items'])) {
                continue;
            }
            
            foreach ($object['order_items'] as $item)
            {
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
     * 是否等待BPM回调结果
     *
     * @param $caseInfo
     * @return bool
     */
    public function isBpmPending($caseInfo)
    {
        return !empty($caseInfo['bpm_status']) && $caseInfo['bpm_status'] == self::BPM_STATUS_PENDING;
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
        $orderItems = [];
        if ($orderInfo['order_objects'] && is_array($orderInfo['order_objects'])) {
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
                    
                    // items
                    $orderItems[] = array(
                        'name' => $item['name'] ?? ($object['name'] ?? ''),
                        'bn' => $item['bn'] ?? '',
                        'spec_info' => $item['spec_info'] ?? ($object['spec_info'] ?? ''),
                        'quantity' => $quantity,
                        'price' => $price,
                        'amount' => $amount,
                    );
                }
            }
        }
        
        $orderInfo['approvalItemList'] = $orderItems;
        
        $orderInfo['addGiftList'] = array();
        
        return $this->succ('操作成功', $orderInfo);
    }
    
    /**
     * 根据店铺+审批节点，获取审批人信息
     *
     * @param string $shop_id
     * @param int $node_id
     * @return array
     */
    public function getApproverInfo($shop_id, $node_id)
    {
        // 注册Service，请求BPM接口方法
        if ($service = kernel::servicelist('nipponpaint.service.bufa.approval.assignee')) {
            foreach($service as $object)
            {
                if(method_exists($object, 'resolve')){
                    return $object->resolve($shop_id, $node_id);
                }
            }
        }
        
        return $this->error('未找到审批人解析服务');
    }
}