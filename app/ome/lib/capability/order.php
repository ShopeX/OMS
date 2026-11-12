<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_order
{
    private object $ordersModel;
    private object $operationOpsModel;
    private object $logModel;
    private ?object $orderFunc;
    private ?object $orderExtendModel;
    private ?object $paymentsModel;
    private ?object $refundsModel;
    private ?object $refundApplyModel;
    private ?object $orderPmtModel;
    private ?object $orderCouponModel;
    private ?object $billLabelModel;
    private ?object $refundBoolTypeLib;
    private ?object $orderBoolTypeLib;
    private ?object $billLabelShsmLib;
    private ?object $abnormalModel;
    private ?object $preprocessConstLib;

    public function __construct(
        ?object $ordersModel = null,
        ?object $operationOpsModel = null,
        ?object $logModel = null,
        ?object $orderFunc = null,
        ?object $orderExtendModel = null,
        ?object $paymentsModel = null,
        ?object $refundsModel = null,
        ?object $refundApplyModel = null,
        ?object $orderPmtModel = null,
        ?object $orderCouponModel = null,
        ?object $billLabelModel = null,
        ?object $refundBoolTypeLib = null,
        ?object $orderBoolTypeLib = null,
        ?object $billLabelShsmLib = null,
        ?object $abnormalModel = null,
        ?object $preprocessConstLib = null
    )
    {
        $this->ordersModel = $ordersModel ?? app::get('ome')->model('orders');
        $this->operationOpsModel = $operationOpsModel ?? app::get('ome')->model('operation_ops');
        $this->logModel = $logModel ?? app::get('ome')->model('operation_log');
        $this->orderFunc = $orderFunc ?? ((class_exists('kernel', false) && method_exists('kernel', 'single')) ? kernel::single('ome_order_func') : null);
        $this->orderExtendModel = $orderExtendModel;
        $this->paymentsModel = $paymentsModel;
        $this->refundsModel = $refundsModel;
        $this->refundApplyModel = $refundApplyModel;
        $this->orderPmtModel = $orderPmtModel;
        $this->orderCouponModel = $orderCouponModel;
        $this->billLabelModel = $billLabelModel;
        $this->refundBoolTypeLib = $refundBoolTypeLib ?? ((class_exists('kernel', false) && method_exists('kernel', 'single')) ? kernel::single('ome_refund_bool_type') : null);
        $this->orderBoolTypeLib = $orderBoolTypeLib ?? ((class_exists('kernel', false) && method_exists('kernel', 'single')) ? kernel::single('ome_order_bool_type') : null);
        $this->billLabelShsmLib = $billLabelShsmLib ?? ((class_exists('kernel', false) && method_exists('kernel', 'single')) ? kernel::single('ome_bill_label_shsm') : null);
        $this->abnormalModel = $abnormalModel;
        $this->preprocessConstLib = $preprocessConstLib ?? ((class_exists('ome_preprocess_const', false)) ? new ome_preprocess_const() : null);
    }

    public function list(array $request, ?mcpapi_auth_context $context = null): array
    {
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['pageSize'] ?? $request['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $filter = is_array($request['filters'] ?? null) ? $request['filters'] : [];
        $orderBy = $this->resolveOrderBy((string) ($request['orderBy'] ?? 'order_id'));
        $orderType = strtoupper((string) ($request['orderType'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $filter = $this->applySceneFilter($filter, $context);
        $filter = $this->applyOrganizationScope($filter, $context);

        $rows = $this->ordersModel->getList('*', $filter, $offset, $pageSize, $orderBy . ' ' . $orderType);
        $total = method_exists($this->ordersModel, 'count')
            ? (int) $this->ordersModel->count($filter)
            : count($rows);

        return [
            'list' => array_map(fn(array $row): array => $this->normalizeListRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public function detail(int $orderId, ?mcpapi_auth_context $context = null): ?array
    {
        if ($orderId < 1) {
            throw new InvalidArgumentException('缺少订单ID');
        }

        $row = $this->ordersModel->dump($orderId, '*', ['order_objects' => ['*', ['order_items' => ['*']]]]);
        if (!is_array($row) || $row === []) {
            return null;
        }
        if (!$this->canAccessOrderRow($row, $context)) {
            return null;
        }

        $logs = method_exists($this->logModel, 'read_log') ? (array) $this->logModel->read_log(['obj_id' => $orderId, 'obj_type' => 'orders@ome'], 0, -1) : [];

        $billLabels = $this->loadBillLabels($orderId);

        return [
            'basic' => $this->normalizeDetailRow($row),
            'order_objects' => $this->normalizeOrderObjects((array) ($row['order_objects'] ?? [])),
            'order_extend' => $this->loadOrderExtend($orderId),
            'payments' => $this->loadPayments($orderId),
            'refunds' => $this->loadRefunds($orderId),
            'refund_apply' => $this->loadRefundApply($orderId),
            'pmts' => $this->loadPmts($orderId),
            'coupon' => $this->loadCoupon($orderId),
            'bill_labels' => $billLabels,
            'order_identifiers' => $this->loadOrderIdentifiers($row, $orderId, $billLabels),
            'abnormal_identifiers' => $this->loadAbnormalIdentifiers($row, $orderId),
            'oplog' => array_map(static function (array $log): array {
                return [
                    'log_id' => $log['log_id'] ?? '',
                    'operate_time' => isset($log['operate_time']) ? date('Y-m-d H:i:s', (int) $log['operate_time']) : '',
                    'op_name' => $log['op_name'] ?? '',
                    'operation' => $log['operation'] ?? '',
                    'memo' => $log['memo'] ?? '',
                    'ip' => $log['ip'] ?? '',
                ];
            }, $logs),
        ];
    }

    private function loadOrderExtend(int $orderId): array
    {
        $model = $this->orderExtendModel ?? app::get('ome')->model('order_extend');
        if (method_exists($model, 'dump')) {
            $row = $model->dump(['order_id' => $orderId]);
            return is_array($row) ? $row : [];
        }

        return [];
    }

    private function loadPayments(int $orderId): array
    {
        $model = $this->paymentsModel ?? app::get('ome')->model('payments');
        if (!method_exists($model, 'getList')) {
            return [];
        }

        $rows = (array) $model->getList('payment_id,payment_bn,t_begin,download_time,money,paymethod', ['order_id' => $orderId]);
        foreach ($rows as $index => $row) {
            if (isset($row['t_begin'])) {
                $rows[$index]['t_begin'] = date('Y-m-d H:i:s', (int) $row['t_begin']);
            }
            if (!empty($row['download_time'])) {
                $rows[$index]['download_time'] = date('Y-m-d H:i:s', (int) $row['download_time']);
            }
        }

        return $rows;
    }

    private function loadRefunds(int $orderId): array
    {
        $model = $this->refundsModel ?? app::get('ome')->model('refunds');
        if (!method_exists($model, 'getList')) {
            return [];
        }

        return (array) $model->getList('refund_bn,t_ready,download_time,money,paymethod,payment', ['order_id' => $orderId]);
    }

    private function loadRefundApply(int $orderId): array
    {
        $model = $this->refundApplyModel ?? app::get('ome')->model('refund_apply');
        if (!method_exists($model, 'getList')) {
            return [];
        }

        $rows = (array) $model->getList('create_time,status,money,refund_apply_bn,refunded,bool_type', ['order_id' => $orderId]);
        foreach ($rows as $index => $row) {
            $prefix = '';
            $boolType = (int) ($row['bool_type'] ?? 0);
            if ($boolType > 0 && $this->refundBoolTypeLib !== null && method_exists($this->refundBoolTypeLib, 'isPriceProtectRefund') && $this->refundBoolTypeLib->isPriceProtectRefund($boolType)) {
                $prefix = '【价保】';
            }
            $status = (string) ($row['status'] ?? '');
            if (class_exists('ome_refund_func', false) && method_exists('ome_refund_func', 'refund_apply_status_name')) {
                $status = ome_refund_func::refund_apply_status_name($status);
            }
            $rows[$index]['status_text'] = $prefix . $status;
        }

        return $rows;
    }

    private function loadPmts(int $orderId): array
    {
        $model = $this->orderPmtModel ?? app::get('ome')->model('order_pmt');
        if (!method_exists($model, 'getList')) {
            return [];
        }

        return (array) $model->getList('*', ['order_id' => $orderId]);
    }

    private function loadCoupon(int $orderId): array
    {
        $model = $this->orderCouponModel ?? app::get('ome')->model('order_coupon');
        if (!method_exists($model, 'getList')) {
            return [];
        }

        $couponRows = (array) $model->getList('type,type_name as pmt_describe,total_amount as pmt_amount, oid,material_bn,amount', ['order_id' => $orderId]);
        $title = ['oid' => '子单号', 'material_bn' => '物料编号'];
        $pmts = [];

        foreach ($couponRows as $row) {
            $index = (string) ($row['oid'] ?? '') . (string) ($row['material_bn'] ?? '');
            $pmtIndex = (string) ($row['pmt_describe'] ?? '') . (!empty($row['type']) ? '(' . $row['type'] . ')' : '');
            $pmts[$index]['oid'] = $row['oid'] ?? '';
            $pmts[$index]['material_bn'] = $row['material_bn'] ?? '';
            $pmts[$index][$pmtIndex] = $row['pmt_amount'] ?? $row['amount'] ?? '';
            $title[$pmtIndex] = $pmtIndex;
        }

        return ['title' => $title, 'pmts' => array_values($pmts)];
    }

    private function loadBillLabels(int $orderId): array
    {
        $model = $this->billLabelModel ?? app::get('ome')->model('bill_label');
        if (method_exists($model, 'getBIllLabelList')) {
            return (array) $model->getBIllLabelList([(string) $orderId], 'order');
        }

        return [];
    }

    private function loadOrderIdentifiers(array $row, int $orderId, array $billLabels): array
    {
        $orderBoolType = (int) ($row['order_bool_type'] ?? 0);
        $markType = (string) ($row['mark_type'] ?? '');
        $labelCodes = [];
        $labelNames = [];
        foreach ($billLabels as $billLabel) {
            $code = (string) ($billLabel['label_code'] ?? '');
            $name = (string) ($billLabel['label_name'] ?? '');
            if ($code !== '') {
                $labelCodes[] = $code;
            }
            if ($name !== '') {
                $labelNames[] = $name;
            }
        }

        return [
            'order_bool_type' => $orderBoolType,
            'order_bool_type_text' => $this->describeOrderBoolType($orderBoolType, (string) ($row['shop_type'] ?? '')),
            'mark_type' => $markType,
            'mark_type_icon' => $this->resolveMarkTypeIcon($markType),
            'bill_label_codes' => array_values(array_unique($labelCodes)),
            'bill_label_names' => array_values(array_unique($labelNames)),
            'is_cpup' => $this->evaluateOrderBoolTypeFlag('isCPUP', $orderBoolType),
            'is_jitx' => $this->evaluateOrderBoolTypeFlag('isJITX', $orderBoolType),
            'is_aoxiang' => $this->evaluateOrderBoolTypeFlag('isAoxiang', $orderBoolType),
            'is_jdlvmi' => $this->evaluateOrderBoolTypeFlag('isJDLVMI', $orderBoolType),
            'is_dw_brand' => $this->evaluateOrderBoolTypeFlag('isDWBrand', $orderBoolType),
            'is_dw_jisu' => $this->evaluateOrderBoolTypeFlag('isDWJISU', $orderBoolType),
            'is_o2opick' => $this->evaluateOrderBoolTypeFlag('isO2opick', $orderBoolType),
            'is_booking_delivery' => $this->evaluateOrderBoolTypeFlag('isBookingDelivery', $orderBoolType),
            'is_shsm' => $this->evaluateBillLabelShsm($orderId),
        ];
    }

    private function loadAbnormalIdentifiers(array $row, int $orderId): array
    {
        return [
            'abnormal' => $row['abnormal'] ?? null,
            'abnormal_status' => (int) ($row['abnormal_status'] ?? 0),
            'abnormal_status_display' => $this->describeAbnormalStatus((int) ($row['abnormal_status'] ?? 0)),
            'abnormal_latest' => $this->loadLatestAbnormal($orderId),
        ];
    }

    private function loadLatestAbnormal(int $orderId): ?array
    {
        $model = $this->abnormalModel ?? app::get('ome')->model('abnormal');
        if (!method_exists($model, 'getList')) {
            return null;
        }

        $rows = (array) $model->getList('*', ['order_id' => $orderId], 0, -1, 'abnormal_id desc');
        if ($rows === []) {
            return null;
        }

        $latest = $rows[0];
        if (isset($latest['abnormal_memo']) && is_string($latest['abnormal_memo'])) {
            $latest['abnormal_memo'] = @unserialize($latest['abnormal_memo']) ?: $latest['abnormal_memo'];
        }
        return $latest;
    }

    private function describeOrderBoolType(int $orderBoolType, string $shopType): string
    {
        if ($orderBoolType === 0 || $this->orderBoolTypeLib === null || !method_exists($this->orderBoolTypeLib, 'getBoolTypeDescribe')) {
            return '';
        }

        return (string) $this->orderBoolTypeLib->getBoolTypeDescribe($orderBoolType, $shopType);
    }

    private function resolveMarkTypeIcon(string $markType): string
    {
        if ($markType === '' || !class_exists('ome_order_func', false) || !method_exists('ome_order_func', 'order_mark_type')) {
            return '';
        }

        return (string) ome_order_func::order_mark_type($markType);
    }

    private function evaluateOrderBoolTypeFlag(string $method, int $orderBoolType): bool
    {
        return $this->orderBoolTypeLib !== null && method_exists($this->orderBoolTypeLib, $method)
            ? (bool) $this->orderBoolTypeLib->{$method}($orderBoolType)
            : false;
    }

    private function evaluateBillLabelShsm(int $orderId): bool
    {
        return $this->billLabelShsmLib !== null && method_exists($this->billLabelShsmLib, 'isTinyPieces')
            ? (bool) $this->billLabelShsmLib->isTinyPieces($orderId)
            : false;
    }

    private function describeAbnormalStatus(int $abnormalStatus): string
    {
        if ($abnormalStatus === 0 || $this->preprocessConstLib === null || !method_exists($this->preprocessConstLib, 'getBoolTypeIdentifier')) {
            return '';
        }

        return (string) $this->preprocessConstLib->getBoolTypeIdentifier($abnormalStatus);
    }

    /**
     * Expand explicit scene presets into model filters.
     *
     * - my_pending: current operator pending queue (op_id + assigned + process_status)
     * - pending: org-level pending tab (group/待处理), no op_id
     * - absent/empty: plain query, only org scope later
     */
    private function applySceneFilter(array $filter, ?mcpapi_auth_context $context): array
    {
        if (!array_key_exists('scene', $filter)) {
            return $filter;
        }

        $scene = $filter['scene'];
        unset($filter['scene']);

        if ($scene === null || $scene === '') {
            return $filter;
        }

        if (!is_string($scene)) {
            throw new InvalidArgumentException('filters.scene 必须是字符串');
        }

        $scene = trim($scene);
        $pendingProcessStatus = ['unconfirmed', 'confirmed', 'splitting'];

        if ($scene === 'my_pending') {
            $filter['assigned'] = 'assigned';
            $filter['abnormal'] = 'false';
            $filter['is_fail'] = 'false';
            $filter['status'] = 'active';
            $filter['process_status'] = $pendingProcessStatus;
            unset($filter['op_id'], $filter['order_confirm_filter']);

            if ($context === null) {
                throw new InvalidArgumentException('filters.scene=my_pending 需要登录上下文');
            }

            if (!$context->isSuper()) {
                $opId = $context->omsSubjectId();
                if ($opId < 1) {
                    throw new InvalidArgumentException('filters.scene=my_pending 缺少有效操作员');
                }
                $filter['op_id'] = $opId;
            }

            return $filter;
        }

        if ($scene === 'pending') {
            $filter['abnormal'] = 'false';
            $filter['status'] = 'active';
            $filter['process_status'] = $pendingProcessStatus;
            $filter['order_confirm_filter'] = 'group_id > 0';
            unset($filter['op_id'], $filter['assigned']);

            return $filter;
        }

        throw new InvalidArgumentException('filters.scene 仅支持 my_pending、pending');
    }

    private function applyOrganizationScope(array $filter, ?mcpapi_auth_context $context): array
    {
        if ($context === null || $context->isSuper()) {
            return $filter;
        }

        $ops = $this->operationOpsModel->getList('org_id', ['op_id' => $context->omsSubjectId()], 0, -1);
        $organizationIds = array_values(array_unique(array_map('intval', array_column($ops, 'org_id'))));
        if ($organizationIds === []) {
            $filter['org_id'] = 'false';
            return $filter;
        }

        if (isset($filter['org_id'])) {
            $current = $filter['org_id'];
            if (is_array($current)) {
                $filtered = array_values(array_intersect(array_map('intval', $current), $organizationIds));
                $filter['org_id'] = $filtered === [] ? 'false' : $filtered;
            } else {
                $filter['org_id'] = in_array((int) $current, $organizationIds, true) ? (int) $current : 'false';
            }
            return $filter;
        }

        $filter['org_id'] = $organizationIds;
        return $filter;
    }

    private function canAccessOrderRow(array $row, ?mcpapi_auth_context $context): bool
    {
        $scopedFilter = $this->applyOrganizationScope(['org_id' => (int) ($row['org_id'] ?? 0)], $context);

        return ($scopedFilter['org_id'] ?? null) !== 'false';
    }

    private function normalizeListRow(array $row): array
    {
        foreach (['order_id', 'shop_id', 'org_id'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = (int) $row[$field];
            }
        }
        return $row;
    }

    private function normalizeDetailRow(array $row): array
    {
        return [
            'order_id' => (int) ($row['order_id'] ?? 0),
            'order_bn' => (string) ($row['order_bn'] ?? ''),
            'shop_id' => (int) ($row['shop_id'] ?? 0),
            'org_id' => (int) ($row['org_id'] ?? 0),
            'member_id' => (int) ($row['member_id'] ?? 0),
            'pay_status' => (string) ($row['pay_status'] ?? ''),
            'ship_status' => (string) ($row['ship_status'] ?? ''),
            'process_status' => (string) ($row['process_status'] ?? ''),
            'total_amount' => $row['total_amount'] ?? null,
            'payed' => $row['payed'] ?? null,
            'shipping' => $row['shipping'] ?? [],
            'payment' => $row['payment'] ?? [],
            'createtime' => $row['createtime'] ?? null,
            'mark_text' => $row['mark_text'] ?? '',
            'custom_mark' => $row['custom_mark'] ?? '',
            'memo' => is_string($row['memo'] ?? null) ? (@unserialize($row['memo']) ?: $row['memo']) : ($row['memo'] ?? []),
        ];
    }

    private function normalizeOrderObjects(array $orderObjects): array
    {
        return array_map(static function (array $object): array {
            $object['obj_id'] = (int) ($object['obj_id'] ?? 0);
            $object['order_items'] = array_map(static function (array $item): array {
                if (isset($item['item_id'])) {
                    $item['item_id'] = (int) $item['item_id'];
                }
                if (isset($item['product_id'])) {
                    $item['product_id'] = (int) $item['product_id'];
                }
                return $item;
            }, (array) ($object['order_items'] ?? []));
            return $object;
        }, $orderObjects);
    }

    private function resolveOrderBy(string $orderBy): string
    {
        $allowed = ['order_id', 'order_bn', 'createtime', 'paytime', 'total_amount', 'shop_id', 'org_id'];
        return in_array($orderBy, $allowed, true) ? $orderBy : 'order_id';
    }
}
