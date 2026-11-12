<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_reship
{
    private object $reshipModel;
    private object $operationOpsModel;
    private object $logModel;

    public function __construct(?object $reshipModel = null, ?object $operationOpsModel = null, ?object $logModel = null)
    {
        $this->reshipModel = $reshipModel ?? app::get('ome')->model('reship');
        $this->operationOpsModel = $operationOpsModel ?? app::get('ome')->model('operation_ops');
        $this->logModel = $logModel ?? app::get('ome')->model('operation_log');
    }

    public function list(array $request, ?mcpapi_auth_context $context = null): array
    {
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['pageSize'] ?? $request['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $filter = is_array($request['filters'] ?? null) ? $request['filters'] : [];
        $orderBy = $this->resolveOrderBy((string) ($request['orderBy'] ?? 'reship_id'));
        $orderType = strtoupper((string) ($request['orderType'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $filter = $this->applyOrganizationScope($filter, $context);

        $rows = $this->reshipModel->getList('*', $filter, $offset, $pageSize, $orderBy . ' ' . $orderType);
        $total = method_exists($this->reshipModel, 'count')
            ? (int) $this->reshipModel->count($filter)
            : count($rows);

        return [
            'list' => array_map(fn(array $row): array => $this->normalizeListRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public function detail(int $reshipId, ?mcpapi_auth_context $context = null): ?array
    {
        if ($reshipId < 1) {
            throw new InvalidArgumentException('缺少补寄/退货单ID');
        }

        $scope = $this->applyOrganizationScope([], $context);
        if (($scope['org_id'] ?? null) === 'false') {
            return null;
        }

        $row = $this->reshipModel->dump($reshipId, '*', ['reship_items' => ['*']]);
        if (!is_array($row) || $row === []) {
            return null;
        }
        if (isset($scope['org_id']) && is_array($scope['org_id']) && !in_array((int) ($row['org_id'] ?? 0), $scope['org_id'], true)) {
            return null;
        }

        $logs = method_exists($this->logModel, 'read_log')
            ? (array) $this->logModel->read_log(['obj_type' => 'reship@ome', 'obj_id' => $reshipId], 0, 20, 'log_id')
            : [];

        return [
            'basic' => $this->normalizeListRow($row),
            'items' => array_values((array) ($row['reship_items'] ?? [])),
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

    private function normalizeListRow(array $row): array
    {
        foreach (['reship_id', 'order_id', 'return_id', 'shop_id', 'branch_id', 'org_id'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = (int) $row[$field];
            }
        }
        return $row;
    }

    private function resolveOrderBy(string $orderBy): string
    {
        $allowed = ['reship_id', 'reship_bn', 'order_id', 'return_id', 't_begin', 't_end', 'is_check', 'status', 'shop_id', 'org_id'];
        return in_array($orderBy, $allowed, true) ? $orderBy : 'reship_id';
    }
}
