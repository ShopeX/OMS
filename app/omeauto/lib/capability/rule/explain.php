<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class omeauto_capability_rule_explain
{
    private object $orderModel;
    private object $objectModel;
    private object $itemModel;
    private object $labelModel;
    private object $extendModel;
    private object $operationLogModel;
    private object $orderTypeModel;
    private object $autoconfirmModel;
    private object $autodispatchModel;
    private object $autobranchModel;
    private object $branchModel;
    private object $database;
    private ?object $orderBoolType;

    public function __construct(
        ?object $orderModel = null,
        ?object $objectModel = null,
        ?object $itemModel = null,
        ?object $labelModel = null,
        ?object $extendModel = null,
        ?object $operationLogModel = null,
        ?object $orderTypeModel = null,
        ?object $autoconfirmModel = null,
        ?object $autodispatchModel = null,
        ?object $autobranchModel = null,
        ?object $branchModel = null,
        ?object $database = null,
        ?object $orderBoolType = null
    ) {
        $this->orderModel = $orderModel ?? app::get('ome')->model('orders');
        $this->objectModel = $objectModel ?? app::get('ome')->model('order_objects');
        $this->itemModel = $itemModel ?? app::get('ome')->model('order_items');
        $this->labelModel = $labelModel ?? app::get('ome')->model('bill_label');
        $this->extendModel = $extendModel ?? app::get('ome')->model('order_extend');
        $this->operationLogModel = $operationLogModel ?? app::get('ome')->model('operation_log');
        $this->orderTypeModel = $orderTypeModel ?? app::get('omeauto')->model('order_type');
        $this->autoconfirmModel = $autoconfirmModel ?? app::get('omeauto')->model('autoconfirm');
        $this->autodispatchModel = $autodispatchModel ?? app::get('omeauto')->model('autodispatch');
        $this->autobranchModel = $autobranchModel ?? app::get('omeauto')->model('autobranch');
        $this->branchModel = $branchModel ?? app::get('ome')->model('branch');
        $this->database = $database ?? kernel::database();
        $this->orderBoolType = $orderBoolType ?? (class_exists('ome_order_bool_type', false) ? new ome_order_bool_type() : null);
    }

    public function explain(array $request, ?array $allowedOrganizationIds): array
    {
        $order = $this->resolveOrder($request, $allowedOrganizationIds);
        if ($order === null) {
            throw new OutOfBoundsException('订单不存在或没有该订单的数据权限');
        }

        $orderId = (int) $order['order_id'];
        $item = $this->buildOrderItem($orderId, $allowedOrganizationIds);

        $operationLogs = $this->loadOperationRows($orderId);
        $actual = $this->parseActual($operationLogs, $order, $item);

        $expectedRef = $this->resolveExpected($request, $order, $item, $allowedOrganizationIds);
        $reasons = [];
        $conditionChecks = [];

        foreach ($expectedRef['candidates'] as $candidate) {
            if ($candidate['kind'] === 'audit') {
                $explained = $this->explainAudit($candidate['audit_oid'], $candidate['order_type_tid'], $order, $item, $actual, $allowedOrganizationIds);
                $reasons = array_merge($reasons, $explained['reasons']);
                $conditionChecks = array_merge($conditionChecks, $explained['condition_checks']);
            } elseif ($candidate['kind'] === 'dispatch') {
                $explained = $this->explainDispatch($candidate['dispatch_oid'], $candidate['order_type_tid'], $order, $item, $actual, $allowedOrganizationIds);
                $reasons = array_merge($reasons, $explained['reasons']);
                $conditionChecks = array_merge($conditionChecks, $explained['condition_checks']);
            } elseif ($candidate['kind'] === 'warehouse') {
                $explained = $this->explainWarehouse($candidate['warehouse_tid'], $order, $item, $actual, $allowedOrganizationIds);
                $reasons = array_merge($reasons, $explained['reasons']);
                $conditionChecks = array_merge($conditionChecks, $explained['condition_checks']);
            }
        }

        if ($reasons === [] && $expectedRef['candidates'] !== []) {
            $reasons[] = [
                'stage' => 'rule',
                'code' => 'EXPECTED_RULE_MATCHED',
                'message' => '按当前配置，期望规则与该订单的条件匹配；实际处理仍可能受合并批次、执行时间点或同规则多候选影响。',
                'suggestion' => '如实际结果与预期不同，请核对订单处理时刻的规则状态，而不是只看当前配置。',
            ];
        }

        return [
            'order' => [
                'order_id' => $orderId,
                'order_bn' => (string) ($order['order_bn'] ?? ''),
                'org_id' => (int) ($order['org_id'] ?? 0),
                'shop_id' => (int) ($order['shop_id'] ?? 0),
                'order_type' => (string) ($order['order_type'] ?? ''),
                'total_amount' => (float) ($order['total_amount'] ?? 0),
                'process_status' => (string) ($order['process_status'] ?? ''),
            ],
            'actual' => $actual,
            'expected' => $expectedRef['summary'],
            'reasons' => $reasons,
            'condition_checks' => $conditionChecks,
            'limitations' => [
                '当前解释基于订单当前数据和规则当前状态；若规则在订单处理后被修改，需按处理时刻配置核对。',
                '自动审单可能在合单批次中执行，单订单条件核对不能完全代表真实批次结果。',
                'operation log 记录了审单规则和仓库规则，分派规则 ID 未直接落日志，分派通过确认组/确认员和当前候选规则推断。',
            ],
        ];
    }

    private function resolveOrder(array $request, ?array $allowedOrganizationIds): ?array
    {
        $orderId = (int) ($request['order_id'] ?? 0);
        $orderBn = trim((string) ($request['order_bn'] ?? ''));
        if ($orderId < 1 && $orderBn === '') {
            throw new InvalidArgumentException('order_id 或 order_bn 至少需要一个');
        }

        $filter = $orderId > 0 ? ['order_id' => $orderId] : ['order_bn' => $orderBn];
        $rows = $this->orderModel->getList('*', $filter, 0, 2) ?: [];
        if ($rows === []) {
            return null;
        }
        $order = $rows[0];
        if (!$this->canAccessOrder($order, $allowedOrganizationIds)) {
            return null;
        }
        return $order;
    }

    private function canAccessOrder(array $order, ?array $allowedOrganizationIds): bool
    {
        if ($allowedOrganizationIds === null) {
            return true;
        }
        return in_array((string) ($order['org_id'] ?? ''), array_map('strval', $allowedOrganizationIds), true);
    }

    private function resolveExpected(array $request, array $order, object $item, ?array $allowedOrganizationIds): array
    {
        $candidates = [];
        $summary = [];
        $expectedOrderTypeTid = (int) ($request['expected_order_type_tid'] ?? 0);
        $expectedAuditOid = (int) ($request['expected_audit_rule_oid'] ?? 0);
        $expectedDispatchOid = (int) ($request['expected_dispatch_rule_oid'] ?? 0);
        $expectedWarehouseTid = (int) ($request['expected_warehouse_rule_tid'] ?? 0);
        $expectedName = trim((string) ($request['expected_rule_name'] ?? ''));

        if ($expectedOrderTypeTid > 0) {
            $row = $this->orderTypeModel->dump(['tid' => $expectedOrderTypeTid], '*');
            if (is_array($row)) {
                $kind = (string) ($row['group_type'] ?? 'order');
                if ($kind === 'branch') {
                    $candidates[] = ['kind' => 'warehouse', 'warehouse_tid' => $expectedOrderTypeTid];
                    $summary[] = ['kind' => 'warehouse_rule', 'id' => $expectedOrderTypeTid, 'name' => (string) ($row['name'] ?? '')];
                } elseif ($kind === 'order') {
                    $candidates[] = ['kind' => 'audit', 'audit_oid' => (int) ($row['oid'] ?? 0), 'order_type_tid' => $expectedOrderTypeTid];
                    $summary[] = ['kind' => 'order_type_rule', 'id' => $expectedOrderTypeTid, 'name' => (string) ($row['name'] ?? ''), 'linked_audit_rule_oid' => (int) ($row['oid'] ?? 0), 'linked_dispatch_rule_oid' => (int) ($row['did'] ?? 0)];
                }
            } else {
                $summary[] = ['kind' => 'order_type_rule', 'id' => $expectedOrderTypeTid, 'not_found' => true];
            }
        }
        if ($expectedAuditOid > 0) {
            $candidates[] = ['kind' => 'audit', 'audit_oid' => $expectedAuditOid, 'order_type_tid' => 0];
            $summary[] = ['kind' => 'audit_rule', 'id' => $expectedAuditOid];
        }
        if ($expectedDispatchOid > 0) {
            $candidates[] = ['kind' => 'dispatch', 'dispatch_oid' => $expectedDispatchOid, 'order_type_tid' => 0];
            $summary[] = ['kind' => 'dispatch_rule', 'id' => $expectedDispatchOid];
        }
        if ($expectedWarehouseTid > 0) {
            $candidates[] = ['kind' => 'warehouse', 'warehouse_tid' => $expectedWarehouseTid];
            $summary[] = ['kind' => 'warehouse_rule', 'id' => $expectedWarehouseTid];
        }
        if ($expectedName !== '' && $candidates === []) {
            $byName = $this->findRuleByName($expectedName, $order, $item);
            foreach ($byName as $entry) {
                $candidates[] = $entry['candidate'];
                $summary[] = $entry['summary'];
            }
        }
        if ($candidates === []) {
            $summary[] = ['kind' => 'no_expected_rule', 'message' => '未提供或未识别到期望规则'];
        }

        return ['candidates' => $candidates, 'summary' => $summary];
    }

    private function findRuleByName(string $name, array $order, object $item): array
    {
        $results = [];
        foreach (['audit', 'dispatch', 'warehouse'] as $kind) {
            $rows = [];
            if ($kind === 'audit') {
                $rows = $this->autoconfirmModel->getList('*', ['name' => $name], 0, 10) ?: [];
            } elseif ($kind === 'dispatch') {
                $rows = $this->autodispatchModel->getList('*', ['name' => $name], 0, 10) ?: [];
            } else {
                $rows = $this->orderTypeModel->getList('*', ['name' => $name, 'group_type' => 'branch'], 0, 10) ?: [];
            }
            foreach ($rows as $row) {
                if ($kind === 'audit') {
                    $results[] = ['candidate' => ['kind' => 'audit', 'audit_oid' => (int) ($row['oid'] ?? 0), 'order_type_tid' => 0], 'summary' => ['kind' => 'audit_rule', 'id' => (int) ($row['oid'] ?? 0), 'name' => (string) ($row['name'] ?? '')]];
                } elseif ($kind === 'dispatch') {
                    $results[] = ['candidate' => ['kind' => 'dispatch', 'dispatch_oid' => (int) ($row['oid'] ?? 0), 'order_type_tid' => 0], 'summary' => ['kind' => 'dispatch_rule', 'id' => (int) ($row['oid'] ?? 0), 'name' => (string) ($row['name'] ?? '')]];
                } else {
                    $results[] = ['candidate' => ['kind' => 'warehouse', 'warehouse_tid' => (int) ($row['tid'] ?? 0)], 'summary' => ['kind' => 'warehouse_rule', 'id' => (int) ($row['tid'] ?? 0), 'name' => (string) ($row['name'] ?? '')]];
                }
            }
        }
        return $results;
    }

    private function explainAudit(int $expectedOid, int $expectedOrderTypeTid, array $order, object $item, array $actual, ?array $allowedOrganizationIds): array
    {
        $rule = $expectedOid > 0 ? $this->autoconfirmModel->dump(['oid' => $expectedOid], '*') : null;
        if (!is_array($rule) || ($expectedOid > 0 && $rule === [])) {
            return ['reasons' => [['stage' => 'audit', 'code' => 'RULE_NOT_FOUND', 'message' => '期望的自动审单规则不存在', 'suggestion' => '核对自动审单规则是否仍存在']], 'condition_checks' => []];
        }

        $reasons = [];
        $conditions = [];
        if ($expectedOrderTypeTid > 0) {
            $groupRow = $this->orderTypeModel->dump(['tid' => $expectedOrderTypeTid], '*');
            if (!is_array($groupRow)) {
                $reasons[] = ['stage' => 'audit', 'code' => 'ORDER_TYPE_NOT_FOUND', 'message' => '期望的订单分组不存在', 'suggestion' => '核对订单分组是否仍存在'];
                return ['reasons' => $reasons, 'condition_checks' => []];
            }
            if ($expectedOid > 0 && (int) ($groupRow['oid'] ?? 0) !== $expectedOid) {
                $reasons[] = ['stage' => 'audit', 'code' => 'ORDER_TYPE_NOT_LINKED', 'message' => "订单分组未关联期望自动审单规则：当前关联 oid=" . (int) ($groupRow['oid'] ?? 0), 'suggestion' => '在自动审单规则中把该订单分组加入 autoOrders'];
            }
            if ((string) ($groupRow['disabled'] ?? 'false') === 'true') {
                $reasons[] = ['stage' => 'audit', 'code' => 'ORDER_TYPE_DISABLED', 'message' => '期望订单分组处于停用状态，运行时不会加载', 'suggestion' => '启用该订单分组'];
            }
            $conditions = $this->checkConditions($groupRow, $item);
            $conditionReasons = array_values(array_filter(array_map(static fn(array $row): ?array => $row['passed'] ? null : [
                'stage' => 'audit',
                'code' => 'CONDITION_FAILED',
                'message' => '条件不满足：' . ($row['caption'] ?? $row['role']),
                'suggestion' => $row['hint'] ?? '调整该订单分组条件',
            ], $conditions)));
            $reasons = array_merge($reasons, $conditionReasons);
        }
        if (is_array($rule)) {
            if ((string) ($rule['disabled'] ?? 'false') === 'true') {
                $reasons[] = ['stage' => 'audit', 'code' => 'AUDIT_RULE_DISABLED', 'message' => '自动审单规则处于停用状态，关联订单分组不会进入自动审单候选', 'suggestion' => '启用自动审单规则'];
            }
            $active = $this->checkAuditWindow($rule);
            if ($active !== null && $active['active'] === false) {
                $reasons[] = ['stage' => 'audit', 'code' => 'OUTSIDE_TIME_WINDOW', 'message' => '当前服务端时间不在自动审单规则生效时间或处于排除时间范围', 'suggestion' => '调整自动审单规则的生效/排除时间'];
            }
            $rulesOrder = $this->auditCandidateOrder($order, $item);
            $actualOid = (int) (($actual['audit_rule']['oid'] ?? 0));
            if ($rulesOrder['selected_oid'] !== null && $expectedOid > 0 && $rulesOrder['selected_oid'] !== $expectedOid) {
                $earlier = array_values(array_filter($rulesOrder['ordered'], static fn(array $row): bool => in_array($expectedOid, [(int) ($row['oid'] ?? 0)], true)));
                if ($earlier !== []) {
                    $reasons[] = ['stage' => 'audit', 'code' => 'LOWER_PRIORITY', 'message' => "当前自动审单候选按 weight DESC, tid ASC 排序后先命中其他规则，实际应为 oid=" . $rulesOrder['selected_oid'], 'suggestion' => '提高期望规则优先级或调整其他规则的条件'];
                }
            }
            if ($actualOid > 0 && $expectedOid > 0 && $actualOid !== $expectedOid && $rulesOrder['selected_oid'] === $expectedOid) {
                $reasons[] = ['stage' => 'audit', 'code' => 'LOG_RULE_DIFFERS_FROM_CURRENT', 'message' => '订单处理日志显示走的是其他审单规则，当前配置计算会命中期望规则，可能规则在订单处理后被修改', 'suggestion' => '按订单处理时刻配置核对'];
            }
            if ($actualOid === 0 && $expectedOid > 0 && $rulesOrder['selected_oid'] === null) {
                $reasons[] = ['stage' => 'audit', 'code' => 'NO_MATCH_FALLBACK', 'message' => '订单未命中任何关联自动审单分组，已进入默认规则或系统默认', 'suggestion' => '检查订单分组条件与启停状态'];
            }
        }

        return ['reasons' => $reasons, 'condition_checks' => $conditions];
    }

    private function checkAuditWindow(array $rule): ?array
    {
        $config = $this->decodeConfig($rule['config'] ?? null);
        $now = time();
        $start = (int) ($config['confirmStartTime'] ?? 0);
        $end = (int) ($config['confirmEndTime'] ?? 0);
        $excludeStart = (int) ($config['excludeStartTime'] ?? 0);
        $excludeEnd = (int) ($config['excludeEndTime'] ?? 0);
        if ($start > 0 && $end > 0 && ($now < $start || $now > $end)) {
            return ['active' => false, 'window' => 'outside'];
        }
        if ($excludeStart > 0 && $excludeEnd > 0 && $excludeStart < $now && $excludeEnd > $now) {
            return ['active' => false, 'window' => 'excluded'];
        }
        return ['active' => true, 'window' => 'inside'];
    }

    private function explainDispatch(int $expectedOid, int $expectedOrderTypeTid, array $order, object $item, array $actual, ?array $allowedOrganizationIds): array
    {
        $rule = $expectedOid > 0 ? $this->autodispatchModel->dump(['oid' => $expectedOid], '*') : null;
        $reasons = [];
        $conditions = [];
        if (!is_array($rule)) {
            $reasons[] = ['stage' => 'dispatch', 'code' => 'RULE_NOT_FOUND', 'message' => '期望的自动分派规则不存在', 'suggestion' => '核对自动分派规则是否仍存在'];
            return ['reasons' => $reasons, 'condition_checks' => []];
        }
        if ((string) ($rule['disabled'] ?? 'false') === 'true') {
            $reasons[] = ['stage' => 'dispatch', 'code' => 'DISPATCH_RULE_DISABLED', 'message' => '自动分派规则处于停用状态', 'suggestion' => '启用自动分派规则'];
        }
        if ($expectedOrderTypeTid > 0) {
            $groupRow = $this->orderTypeModel->dump(['tid' => $expectedOrderTypeTid], '*');
            if (!is_array($groupRow)) {
                $reasons[] = ['stage' => 'dispatch', 'code' => 'ORDER_TYPE_NOT_FOUND', 'message' => '期望订单分组不存在', 'suggestion' => '核对订单分组是否仍存在'];
                return ['reasons' => $reasons, 'condition_checks' => []];
            }
            if ((int) ($groupRow['did'] ?? 0) !== $expectedOid) {
                $reasons[] = ['stage' => 'dispatch', 'code' => 'ORDER_TYPE_NOT_LINKED', 'message' => '订单分组未关联期望自动分派规则', 'suggestion' => '在自动分派规则中把该订单分组加入 autoOrders'];
            }
            if ((string) ($groupRow['disabled'] ?? 'false') === 'true') {
                $reasons[] = ['stage' => 'dispatch', 'code' => 'ORDER_TYPE_DISABLED', 'message' => '期望订单分组停用', 'suggestion' => '启用订单分组'];
            }
            $conditions = $this->checkConditions($groupRow, $item);
            $reasons = array_merge($reasons, array_values(array_filter(array_map(static fn(array $row): ?array => $row['passed'] ? null : [
                'stage' => 'dispatch',
                'code' => 'CONDITION_FAILED',
                'message' => '条件不满足：' . ($row['caption'] ?? $row['role']),
                'suggestion' => $row['hint'] ?? '调整该订单分组条件',
            ], $conditions))));
        }
        $current = $this->dispatchCandidateOrder($order, $item);
        if ($current['selected_dispatch_oid'] !== null && $expectedOid > 0 && $current['selected_dispatch_oid'] !== $expectedOid) {
            $reasons[] = ['stage' => 'dispatch', 'code' => 'LOWER_PRIORITY', 'message' => "自动分派候选命中其他规则 oid=" . $current['selected_dispatch_oid'] . '，而非期望规则', 'suggestion' => '提高期望规则关联订单分组权重或调整条件'];
        }
        if ($current['selected_dispatch_oid'] === null && $expectedOid > 0) {
            $reasons[] = ['stage' => 'dispatch', 'code' => 'NO_MATCH_FALLBACK', 'message' => '订单未命中任何自动分派分组，进入默认分派或保持未自动分派', 'suggestion' => '检查订单分组条件和启停状态'];
        }
        return ['reasons' => $reasons, 'condition_checks' => $conditions];
    }

    private function explainWarehouse(int $expectedTid, array $order, object $item, array $actual, ?array $allowedOrganizationIds): array
    {
        $row = $this->orderTypeModel->dump(['tid' => $expectedTid], '*');
        if (!is_array($row) || (string) ($row['group_type'] ?? '') !== 'branch') {
            return ['reasons' => [['stage' => 'warehouse', 'code' => 'RULE_NOT_FOUND', 'message' => '期望仓库规则不存在或不是仓库规则', 'suggestion' => '核对仓库规则 ID']], 'condition_checks' => []];
        }
        $reasons = [];
        $conditions = $this->checkConditions($row, $item);
        if ((string) ($row['disabled'] ?? 'false') === 'true') {
            $reasons[] = ['stage' => 'warehouse', 'code' => 'WAREHOUSE_RULE_DISABLED', 'message' => '仓库规则停用', 'suggestion' => '启用该仓库规则'];
        }
        $expectedMatches = $this->allConditionsPass($conditions);
        $reasons = array_merge($reasons, array_values(array_filter(array_map(static fn(array $condition): ?array => $condition['passed'] ? null : [
            'stage' => 'warehouse',
            'code' => 'CONDITION_FAILED',
            'message' => '条件不满足：' . ($condition['caption'] ?? $condition['role']),
            'suggestion' => $condition['hint'] ?? '调整该仓库规则命中条件',
        ], $conditions))));
        $actualTid = (int) (($actual['warehouse_rule']['tid'] ?? 0));
        if ($expectedMatches && $actualTid > 0 && $actualTid !== $expectedTid) {
            $matched = $this->matchingWarehouseRules($order, $item, $expectedTid);
            $reasons[] = [
                'stage' => 'warehouse',
                'code' => 'MULTI_WAREHOUSE_RULE_SELECTION',
                'message' => '期望仓库规则条件匹配，但自动仓库阶段会收集所有匹配仓库规则/仓库候选，再结合库存、默认仓和 router/cost 策略选择，因此实际选中了其他仓库规则',
                'suggestion' => '查看仓库规则权重、绑定仓、默认仓和库存；若希望只走期望规则，需要让其他仓库规则不匹配或提高其绑定优先级',
            ];
            $matchedIds = array_values(array_unique(array_map(static fn(array $matchedRow): int => (int) ($matchedRow['tid'] ?? 0), $matched['matched'])));
            $reasons[0]['matching_warehouse_rules'] = $matchedIds;
        } elseif ($expectedMatches && $actualTid === 0) {
            $reasons[] = ['stage' => 'warehouse', 'code' => 'WAREHOUSE_LOG_EMPTY', 'message' => '日志中没有可识别的实际仓库规则，可能订单未走到自动仓库选择', 'suggestion' => '结合订单处理状态查看是否自动仓库未执行'];
        } elseif (!$expectedMatches) {
            $reasons[] = ['stage' => 'warehouse', 'code' => 'CONDITION_NOT_MATCHED', 'message' => '期望仓库规则条件未匹配该订单', 'suggestion' => '按条件检查结果调整规则'];
        }
        return ['reasons' => $reasons, 'condition_checks' => $conditions];
    }

    private function allConditionsPass(array $conditions): bool
    {
        if ($conditions === []) {
            return true;
        }
        foreach ($conditions as $condition) {
            if (empty($condition['passed'])) {
                return false;
            }
        }
        return true;
    }

    private function matchingWarehouseRules(array $order, object $item, int $expectedTid): array
    {
        $rows = $this->warehouseRows();
        $matched = [];
        foreach ($rows as $row) {
            $checks = $this->checkConditions($row, $item);
            if ($this->allConditionsPass($checks)) {
                $matched[] = $row;
            }
        }
        return ['matched' => $matched, 'conditions' => array_map(static fn(array $row): array => ['tid' => (int) ($row['tid'] ?? 0), 'name' => (string) ($row['name'] ?? '')], $matched)];
    }

    private function parseActual(array $logs, array $order, object $item): array
    {
        $audit = ['oid' => 0, 'name' => '', 'source_log_id' => null];
        $warehouse = ['tid' => 0, 'name' => '', 'source_log_id' => null];
        $dispatch = ['group_id' => 0, 'op_id' => 0, 'group_name' => '', 'operator_name' => ''];

        foreach ($logs as $row) {
            $memo = (string) ($row['memo'] ?? '');
            if ($memo === '') {
                continue;
            }
            if (preg_match('/审单规则:(.*?)(?:<span.*?<\/span>)?\((\d+)\),仓库规则:/u', $memo, $matches)) {
                $audit = ['oid' => (int) $matches[2], 'name' => trim(strip_tags($matches[1])), 'source_log_id' => (int) ($row['log_id'] ?? 0)];
            }
            if (preg_match('/仓库规则:(.*?)\((-?\d+|-)\)/u', $memo, $matches)) {
                $warehouseTid = $matches[2] === '-' ? 0 : (int) $matches[2];
                $warehouse = ['tid' => $warehouseTid, 'name' => trim(strip_tags($matches[1])), 'source_log_id' => (int) ($row['log_id'] ?? 0)];
            }
            if (preg_match('/自动分派给确认组:(.*?),确认人:(.*?)(?:<|$)/u', $memo, $matches)) {
                $dispatch['group_name'] = trim($matches[1]);
                $dispatch['operator_name'] = trim($matches[2]);
            }
        }

        if ($dispatch['group_name'] === '' && isset($order['group_id'])) {
            $dispatch['group_id'] = (int) $order['group_id'];
        }
        if (isset($order['op_id'])) {
            $dispatch['op_id'] = (int) $order['op_id'];
        }

        if ($audit['oid'] === 0 && $warehouse['tid'] === 0) {
            $auditReplay = $this->auditCandidateOrder($order, $item);
            $audit['replayed_oid'] = $auditReplay['selected_oid'];
            $audit['replayed_default'] = $auditReplay['selected_default'];
        }
        $dispatchReplay = $this->dispatchCandidateOrder($order, $item);
        $dispatch['replayed_dispatch_oid'] = $dispatchReplay['selected_dispatch_oid'];
        $dispatch['replayed_default'] = $dispatchReplay['selected_default'];

        return ['audit_rule' => $audit, 'warehouse_rule' => $warehouse, 'dispatch' => $dispatch];
    }

    private function loadOperationRows(int $orderId): array
    {
        $confirmRows = $this->operationLogModel->getList('*', ['obj_id' => (string) $orderId, 'operation' => 'order_confirm@ome'], 0, 100, 'operate_time DESC, log_id DESC') ?: [];
        $dispatchRows = $this->operationLogModel->getList('*', ['obj_id' => (string) $orderId, 'operation' => 'order_dispatch@ome'], 0, 100, 'operate_time DESC, log_id DESC') ?: [];
        return array_merge($confirmRows, $dispatchRows);
    }

    private function buildOrderItem(int $orderId, ?array $allowedOrganizationIds): object
    {
        $rows = $this->orderModel->getList('*', ['order_id' => $orderId], 0, 1) ?: [];
        $order = $rows[0] ?? [];
        if ($order === []) {
            throw new OutOfBoundsException('订单不存在或没有该订单的数据权限');
        }

        $objects = $this->objectModel->getList('*', ['order_id' => $orderId, 'is_sh_ship' => 'false'], 0, -1) ?: [];
        $objectIds = array_map(static fn(array $row): int => (int) ($row['obj_id'] ?? 0), $objects);
        $items = $objectIds === [] ? [] : ($this->itemModel->getList('*', ['order_id' => $orderId, 'obj_id' => $objectIds, 'delete' => 'false'], 0, -1) ?: []);

        foreach ($objects as $object) {
            $objId = (int) ($object['obj_id'] ?? 0);
            $object['addon'] = @json_decode((string) ($object['addon'] ?? ''), true);
            $object['addon'] = is_array($object['addon']) ? $object['addon'] : [];
            foreach ($items as $item) {
                if ((int) ($item['obj_id'] ?? 0) === $objId) {
                    $object['items'][(int) ($item['item_id'] ?? 0)] = $item;
                }
            }
            $order['objects'][$objId] = $object;
        }

        $labels = $this->labelModel->getList('bill_id,label_id', ['bill_type' => 'order', 'bill_id' => $orderId], 0, -1) ?: [];
        foreach ($labels as $label) {
            $order['labels'][] = ['label_id' => (string) ($label['label_id'] ?? '')];
        }

        $extend = $this->extendModel->dump(['order_id' => $orderId], '*');
        if (is_array($extend)) {
            $order['extend_field'] = @json_decode((string) ($extend['extend_field'] ?? ''), true);
            $order['cpup_service'] = $extend['cpup_service'] ?? '';
        }

        return new omeauto_auto_group_item([$order]);
    }

    private function auditCandidateOrder(array $order, object $item): array
    {
        $rows = $this->database->select(
            "SELECT ot.tid,ot.oid,ot.did,ot.name,ot.weight,ot.config,ot.disabled,at.disabled AS audit_disabled,at.defaulted,at.config AS audit_config
             FROM sdb_omeauto_order_type AS ot
             LEFT JOIN sdb_omeauto_autoconfirm AS at ON ot.oid=at.oid
             WHERE ot.oid>0
             ORDER BY ot.weight DESC, ot.tid ASC"
        ) ?: [];

        $selectedOid = null;
        $selectedDefault = false;
        foreach ($rows as $row) {
            if ((string) ($row['disabled'] ?? 'false') === 'true' || (string) ($row['audit_disabled'] ?? 'true') === 'true') {
                continue;
            }
            if ($this->checkAuditWindow(['config' => $row['audit_config'] ?? null])['active'] === false) {
                continue;
            }
            $checks = $this->checkConditions($row, $item);
            if ($this->allConditionsPass($checks)) {
                $selectedOid = (int) ($row['oid'] ?? 0);
                break;
            }
        }

        if ($selectedOid === null) {
            $defaultRow = $this->autoconfirmModel->dump(['defaulted' => 'true', 'disabled' => 'false'], 'oid,config');
            if (is_array($defaultRow) && (int) ($defaultRow['oid'] ?? 0) > 0 && $this->checkAuditWindow($defaultRow)['active']) {
                $selectedOid = (int) $defaultRow['oid'];
                $selectedDefault = true;
            }
        }
        return ['ordered' => $rows, 'selected_oid' => $selectedOid, 'selected_default' => $selectedDefault];
    }

    private function dispatchCandidateOrder(array $order, object $item): array
    {
        $rows = $this->database->select(
            "SELECT ot.tid,ot.did,ot.name,ot.weight,ot.config,ot.disabled,dt.disabled AS dispatch_disabled,dt.defaulted,dt.op_id,dt.group_id
             FROM sdb_omeauto_order_type AS ot
             INNER JOIN sdb_omeauto_autodispatch AS dt ON ot.did=dt.oid
             WHERE ot.did>0
             ORDER BY ot.weight DESC, ot.tid ASC"
        ) ?: [];

        $selectedDispatchOid = null;
        $selectedDefault = false;
        foreach ($rows as $row) {
            if ((string) ($row['disabled'] ?? 'false') === 'true' || (string) ($row['dispatch_disabled'] ?? 'true') === 'true') {
                continue;
            }
            $checks = $this->checkConditions($row, $item);
            if ($this->allConditionsPass($checks)) {
                $selectedDispatchOid = (int) ($row['did'] ?? 0);
                break;
            }
        }

        if ($selectedDispatchOid === null) {
            $defaultRow = $this->autodispatchModel->dump(['defaulted' => 'true', 'disabled' => 'false'], 'oid,config');
            if (is_array($defaultRow) && (int) ($defaultRow['oid'] ?? 0) > 0) {
                $selectedDispatchOid = (int) $defaultRow['oid'];
                $selectedDefault = true;
            }
        }

        return ['ordered' => $rows, 'selected_dispatch_oid' => $selectedDispatchOid, 'selected_default' => $selectedDefault];
    }

    private function warehouseRows(): array
    {
        return $this->orderTypeModel->getList('*', ['group_type' => 'branch'], 0, -1, 'weight DESC, tid ASC') ?: [];
    }

    private function checkConditions(array $row, object $item): array
    {
        $config = $this->decodeConfig($row['config'] ?? null);
        if ($config === []) {
            return [];
        }
        $checks = [];
        foreach ($config as $roleJson) {
            $role = is_array($roleJson) ? $roleJson : (json_decode((string) $roleJson, true) ?: []);
            $roleName = (string) ($role['role'] ?? '');
            $content = $role['content'] ?? null;
            if ($roleName === '' || $content === null) {
                $checks[] = ['role' => $roleName, 'passed' => false, 'caption' => '配置格式无效', 'hint' => '规则配置不完整'];
                continue;
            }
            $passed = $this->runCondition($roleName, $content, $item);
            $checks[] = [
                'role' => $roleName,
                'passed' => $passed,
                'caption' => (string) ($role['caption'] ?? ''),
                'hint' => $passed ? '' : $this->conditionHint($roleName, $content, $item),
            ];
        }
        return $checks;
    }

    private function runCondition(string $role, mixed $content, object $item): bool
    {
        $class = 'omeauto_auto_type_' . strtolower($role);
        if (!class_exists($class, true)) {
            return false;
        }
        $filter = new $class();
        if (!method_exists($filter, 'setRole') || !method_exists($filter, 'vaild')) {
            return false;
        }
        try {
            $filter->setRole($content);
            return (bool) $filter->vaild($item);
        } catch (\Throwable) {
            return false;
        }
    }

    private function conditionHint(string $role, mixed $content, object $item): string
    {
        $hints = [
            'address' => '收货地区与订单 ship_area 的省市或末级地区不一致，或订单收货地区字段不完整',
            'shop' => '订单来源店铺不在该规则店铺列表中',
            'platform' => '订单平台值或好食期特殊平台加密数据不匹配',
            'shopmode' => 'shop_yjdf 在运行时不会命中；shop_self 通常只表示非空类型',
            'money' => '订单金额可能按合单后的总额计算',
            'weight' => '订单重量按系统计算口径，不一定等于页面商品重量',
            'itemnum' => '订单商品件数按明细数量合计',
            'skunum' => '订单商品种类数按明细行数统计，不是去重 SKU 数',
            'sku' => 'SKU 时间条件按订单创建时间，且“仅有”要求整单明细都属于配置 SKU',
            'memo' => '备注按买家/客服不同字段并支持正则匹配，关键词可能被当作正则',
            'materialcat' => '物料分类按精确 cat_id 匹配，父分类不会自动包含子分类',
            'orderlabel' => '订单未携带配置的订单标记，或标签未在订单数据中加载',
            'customer' => '订单销售物料分类与配置客户分类不一致',
            'materialtype' => '订单明细中没有配置的基础物料类型',
            'orderhost' => '达人/直播间字段不匹配',
            'ordertype' => '订单类型不在规则允许列表中',
            'cod' => '订单货到付款标识不匹配',
        ];
        return $hints[$role] ?? '条件不匹配';
    }

    private function decodeConfig(mixed $config): array
    {
        if (is_array($config)) {
            return $config;
        }
        if (is_string($config) && $config !== '') {
            $decoded = @unserialize($config, ['allowed_classes' => false]);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
