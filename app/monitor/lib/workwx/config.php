<?php

/**
 * 企微通知规则配置
 */
class monitor_workwx_config
{
    /**
     * 企微通知规则可选事件：仅已审核且启用的「发送类型=企微」预警模板
     */
    public function getWorkwxEventTypes()
    {
        $eventTypeMap = kernel::single('monitor_event_template')->getEventType();
        $rows         = app::get('monitor')->model('event_template')->getList(
            'event_type,template_name',
            array(
                'send_type' => 'workwx',
                'status'    => '1',
                'disabled'  => 'false',
            ),
            0,
            5000,
            'template_id asc'
        );
        $types = array();
        foreach ((array) $rows as $row) {
            $eventType = trim($row['event_type']);
            if ($eventType === '' || isset($types[$eventType])) {
                continue;
            }
            if (isset($eventTypeMap[$eventType])) {
                $types[$eventType] = $eventTypeMap[$eventType];
            } else {
                $types[$eventType] = $row['template_name'] ?: $eventType;
            }
        }
        return $types;
    }

    /**
     * 规则可选物流类型
     */
    public function getLogiTypeOptions()
    {
        return array(
            ''           => '不限',
            'o2o_pickup' => '门店自提',
            'o2o_ship'   => '门店配送',
        );
    }

    /**
     * 门店下拉/多选数据
     */
    public function getStoreOptions()
    {
        if (!app::get('o2o')->is_installed()) {
            return array();
        }
        $rows = app::get('o2o')->model('store')->getList(
            'store_id,store_bn,name,branch_id,status',
            array('status' => 1),
            0,
            5000,
            'store_bn asc'
        );
        $list = array();
        foreach ((array) $rows as $row) {
            $list[] = array(
                'store_id'   => $row['store_id'],
                'store_bn'   => $row['store_bn'],
                'store_name' => $row['name'],
                'branch_id'  => $row['branch_id'],
                'label'      => $row['store_bn'] . ' / ' . $row['name'],
            );
        }
        return $list;
    }

    /**
     * 保存通知规则（含 Webhook、门店、规则条件）
     */
    public function saveRule(array $data, &$errMsg = '')
    {
        $ruleMdl    = app::get('monitor')->model('workwx_rule');
        $storeMdl   = app::get('monitor')->model('workwx_rule_store');
        $ruleName   = trim($data['rule_name']);
        $webhook    = trim($data['workwx_webhook']);
        $ruleId     = intval($data['rule_id']);
        $eventTypes = array_filter((array) $data['event_type']);
        $roles      = $this->parseRuleRoles($data);

        if ($ruleName === '') {
            $errMsg = '请填写规则名称';
            return false;
        }
        if ($webhook === '') {
            $errMsg = '请填写企微Webhook地址';
            return false;
        }
        if (empty($eventTypes)) {
            $errMsg = '请至少选择一个触发事件';
            return false;
        }

        $storeIds = $this->extractStoreIdsFromRoles($roles);
        if (empty($storeIds)) {
            $errMsg = '请在通知条件中至少添加一条门店规则';
            return false;
        }

        if (!$this->validateRuleStoreUnique($ruleId, $storeIds, $errMsg)) {
            return false;
        }

        $orgIds = '';
        if (!empty($data['org_id']) && is_array($data['org_id'])) {
            $orgIds = implode(',', array_filter($data['org_id']));
        } elseif (!empty($data['org_id'])) {
            $orgIds = trim($data['org_id']);
        }

        $config = array(
            'roles' => $roles,
        );

        $saveData = array(
            'rule_name'      => $ruleName,
            'workwx_webhook' => $webhook,
            'org_id'         => $orgIds,
            'event_types'    => implode(',', $eventTypes),
            'weight'         => intval($data['weight']),
            'config'         => $config,
            'memo'           => trim($data['memo']),
            'disabled'       => ($data['disabled'] === 'true') ? 'true' : 'false',
        );
        if ($ruleId) {
            $saveData['rule_id'] = $ruleId;
        }

        $transaction = kernel::database()->beginTransaction();
        try {
            if (!$ruleMdl->save($saveData)) {
                throw new Exception('保存规则失败');
            }
            $newRuleId = $saveData['rule_id'];
            $storeMdl->delete(array('rule_id' => $newRuleId));

            $o2oStoreMdl = app::get('o2o')->model('store');
            foreach ($storeIds as $storeId) {
                $storeRow = $o2oStoreMdl->dump($storeId, 'store_id,store_bn,name');
                if (empty($storeRow)) {
                    throw new Exception('门店不存在：' . $storeId);
                }
                $storeSaveData = array(
                    'rule_id'    => $newRuleId,
                    'store_id'   => $storeId,
                    'store_bn'   => $storeRow['store_bn'],
                    'store_name' => $storeRow['name'],
                );
                if (!$storeMdl->insert($storeSaveData)) {
                    throw new Exception('保存门店关联失败：' . $storeId);
                }
            }
            kernel::database()->commit($transaction);
            return $newRuleId;
        } catch (Exception $e) {
            kernel::database()->rollBack();
            $errMsg = $e->getMessage();
            return false;
        }
    }

    /**
     * 解析规则条件 JSON 列表
     */
    public function parseRuleRoles(array $data)
    {
        $roles = array();
        if (!empty($data['roles'])) {
            foreach (explode('|||', $data['roles']) as $row) {
                $row = trim($row);
                if ($row === '') {
                    continue;
                }
                $decoded = json_decode($row, true);
                if (is_array($decoded) && !empty($decoded['role'])) {
                    $roles[] = $decoded;
                }
            }
        }
        return $roles;
    }

    /**
     * 从规则条件中提取门店 ID（去重）
     */
    public function extractStoreIdsFromRoles(array $roles)
    {
        $storeIds = array();
        foreach ($roles as $role) {
            if ($role['role'] !== 'store' || empty($role['content'])) {
                continue;
            }
            foreach ((array) $role['content'] as $storeId) {
                $storeId = intval($storeId);
                if ($storeId) {
                    $storeIds[$storeId] = $storeId;
                }
            }
        }
        return array_values($storeIds);
    }

    /**
     * 读取规则已选门店
     */
    public function getRuleStoreIds($ruleId)
    {
        $rows = app::get('monitor')->model('workwx_rule_store')->getList(
            'store_id',
            array('rule_id' => intval($ruleId)),
            0,
            5000
        );
        return array_column((array) $rows, 'store_id');
    }

    /**
     * 编辑页规则条件列表
     */
    public function getRuleRoleItems($ruleId)
    {
        $items = array();
        $rule  = app::get('monitor')->model('workwx_rule')->dump(intval($ruleId), 'config');
        $roles = array();
        if (!empty($rule['config']['roles']) && is_array($rule['config']['roles'])) {
            $roles = $rule['config']['roles'];
        } elseif ($ruleId) {
            $storeIds = $this->getRuleStoreIds($ruleId);
            if ($storeIds) {
                $storeType = kernel::single('monitor_workwx_rule_type_store');
                $roles[]   = json_decode($storeType->roleToString(array('store_id' => $storeIds)), true);
            }
        }
        $uid = 1000;
        foreach ($roles as $role) {
            $items[] = array(
                'uid'  => 'm_item_' . ($uid++),
                'json' => json_encode($role),
                'attr' => $role,
            );
        }
        return $items;
    }

    /**
     * 门店唯一归属校验（按规则）
     */
    public function validateRuleStoreUnique($ruleId, array $storeIds, &$errMsg = '')
    {
        $storeIds = array_filter(array_map('intval', $storeIds));
        if (empty($storeIds)) {
            return true;
        }
        $storeMdl = app::get('monitor')->model('workwx_rule_store');
        foreach ($storeIds as $storeId) {
            $filter = array('store_id' => $storeId);
            if ($ruleId) {
                $filter['rule_id|noequal'] = intval($ruleId);
            }
            $exists = $storeMdl->dump($filter, 'rule_id,store_name,store_bn');
            if ($exists) {
                $ruleRow = app::get('monitor')->model('workwx_rule')->dump($exists['rule_id'], 'rule_name');
                $errMsg = sprintf(
                    '门店 %s/%s 已属于通知规则「%s」，一个门店只能归属一条规则',
                    $exists['store_bn'],
                    $exists['store_name'],
                    $ruleRow['rule_name']
                );
                return false;
            }
        }
        return true;
    }

    /**
     * 已被其他规则占用的门店（用于选择器 disabled 展示）
     */
    public function getOccupiedStoreMap($excludeRuleId = 0)
    {
        $filter = array();
        if ($excludeRuleId) {
            $filter['rule_id|noequal'] = intval($excludeRuleId);
        }
        $rows = app::get('monitor')->model('workwx_rule_store')->getList(
            'store_id,store_bn,store_name,rule_id',
            $filter,
            0,
            5000
        );
        $map = array();
        foreach ((array) $rows as $row) {
            $storeId = intval($row['store_id']);
            if (!$storeId || isset($map[$storeId])) {
                continue;
            }
            $ruleRow = app::get('monitor')->model('workwx_rule')->dump($row['rule_id'], 'rule_name');
            $map[$storeId] = array(
                'rule_id'   => intval($row['rule_id']),
                'rule_name' => $ruleRow['rule_name'] ?: ('规则#' . $row['rule_id']),
                'store_bn'  => $row['store_bn'],
                'store_name'=> $row['store_name'],
            );
        }
        return $map;
    }

    /**
     * webhook 脱敏展示
     */
    public function maskWebhook($webhook)
    {
        $webhook = (string) $webhook;
        if (strlen($webhook) <= 20) {
            return $webhook;
        }
        return substr($webhook, 0, 30) . '...' . substr($webhook, -8);
    }
}
