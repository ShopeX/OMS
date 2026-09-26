<?php

/**
 * 企微通知规则路由：按事件 + 门店匹配规则 Webhook，未命中走全局配置
 */
class monitor_workwx_router
{
    /**
     * 解析发送 Webhook
     *
     * @param string $eventType
     * @param array  $params
     * @return string|null 命中规则返回 Webhook，否则 null 表示走原全局路由
     */
    public function resolveWebhook($eventType, $params = array())
    {
        $eventType = trim((string) $eventType);
        if ($eventType === '') {
            return null;
        }
        if (!is_array($params)) {
            $params = array();
        }

        $rules = $this->getEnabledRulesByEvent($eventType);
        if (empty($rules)) {
            return null;
        }

        $storeId = $this->resolveStoreId($params);
        if (!$storeId) {
            return null;
        }

        $rule = $this->matchRuleByStore($rules, $storeId);
        if (empty($rule['workwx_webhook'])) {
            return null;
        }

        return trim($rule['workwx_webhook']);
    }

    /**
     * 启用的、包含指定事件的规则（权重高优先）
     */
    protected function getEnabledRulesByEvent($eventType)
    {
        $rows = app::get('monitor')->model('workwx_rule')->getList(
            'rule_id,rule_name,workwx_webhook,event_types,weight',
            array('disabled' => 'false'),
            0,
            5000,
            'weight desc,rule_id asc'
        );
        $rules = array();
        foreach ((array) $rows as $row) {
            $types = array_filter(array_map('trim', explode(',', $row['event_types'])));
            if (in_array($eventType, $types, true)) {
                $rules[] = $row;
            }
        }
        return $rules;
    }

    /**
     * 门店归属规则匹配
     */
    protected function matchRuleByStore(array $rules, $storeId)
    {
        $storeId = intval($storeId);
        if (!$storeId || empty($rules)) {
            return null;
        }
        $ruleIds = array_column($rules, 'rule_id');
        $storeRows = app::get('monitor')->model('workwx_rule_store')->getList(
            'rule_id',
            array('store_id' => $storeId, 'rule_id' => $ruleIds),
            0,
            1
        );
        if (empty($storeRows[0]['rule_id'])) {
            return null;
        }
        $matchedRuleId = $storeRows[0]['rule_id'];
        foreach ($rules as $rule) {
            if (intval($rule['rule_id']) === intval($matchedRuleId)) {
                return $rule;
            }
        }
        return null;
    }

    /**
     * 从通知参数解析门店 ID
     */
    protected function resolveStoreId(array $params)
    {
        if (!empty($params['store_id'])) {
            return intval($params['store_id']);
        }

        $branchId = 0;
        if (!empty($params['branch_id'])) {
            $branchId = intval($params['branch_id']);
        } elseif (!empty($params['delivery_bn'])) {
            $branchId = $this->getBranchIdByDeliveryBn($params['delivery_bn']);
        } elseif (!empty($params['delivery_id'])) {
            $branchId = $this->getBranchIdByDeliveryId($params['delivery_id']);
        }

        if ($branchId) {
            return $this->getStoreIdByBranchId($branchId);
        }

        return 0;
    }

    protected function getBranchIdByDeliveryBn($deliveryBn)
    {
        $deliveryBn = trim((string) $deliveryBn);
        if ($deliveryBn === '') {
            return 0;
        }
        $row = app::get('ome')->model('delivery')->dump(
            array('delivery_bn' => $deliveryBn),
            'branch_id'
        );
        return !empty($row['branch_id']) ? intval($row['branch_id']) : 0;
    }

    protected function getBranchIdByDeliveryId($deliveryId)
    {
        $deliveryId = intval($deliveryId);
        if (!$deliveryId) {
            return 0;
        }
        $row = app::get('ome')->model('delivery')->dump(
            array('delivery_id' => $deliveryId),
            'branch_id'
        );
        return !empty($row['branch_id']) ? intval($row['branch_id']) : 0;
    }

    /**
     * delivery.branch_id 关联 o2o.store.branch_id
     */
    protected function getStoreIdByBranchId($branchId)
    {
        $branchId = intval($branchId);
        if (!$branchId || !app::get('o2o')->is_installed()) {
            return 0;
        }
        $row = app::get('o2o')->model('store')->dump(
            array('branch_id' => $branchId, 'status' => 1),
            'store_id'
        );
        return !empty($row['store_id']) ? intval($row['store_id']) : 0;
    }
}
