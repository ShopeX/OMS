<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_return_product
{
    private object $returnProductModel;
    private object $operationOpsModel;

    public function __construct(?object $returnProductModel = null, ?object $operationOpsModel = null)
    {
        $this->returnProductModel = $returnProductModel ?? app::get('ome')->model('return_product');
        $this->operationOpsModel = $operationOpsModel ?? app::get('ome')->model('operation_ops');
    }

    public function list(array $request, ?mcpapi_auth_context $context = null): array
    {
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['pageSize'] ?? $request['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $filter = is_array($request['filters'] ?? null) ? $request['filters'] : [];
        $orderBy = $this->resolveOrderBy((string) ($request['orderBy'] ?? 'add_time'));
        $orderType = strtoupper((string) ($request['orderType'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $filter = $this->applyOrganizationScope($filter, $context);

        $rows = $this->returnProductModel->getList('*', $filter, $offset, $pageSize, $orderBy . ' ' . $orderType);
        $total = method_exists($this->returnProductModel, 'count')
            ? (int) $this->returnProductModel->count($filter)
            : count($rows);

        return [
            'list' => array_map(fn(array $row): array => $this->normalizeListRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public function detail(int $returnId, ?mcpapi_auth_context $context = null): ?array
    {
        if ($returnId < 1) {
            throw new InvalidArgumentException('缺少售后申请单ID');
        }

        $scope = $this->applyOrganizationScope([], $context);
        if (($scope['org_id'] ?? null) === 'false') {
            return null;
        }

        $row = $this->returnProductModel->product_detail($returnId);
        if (!is_array($row) || $row === []) {
            return null;
        }
        if (isset($scope['org_id']) && is_array($scope['org_id']) && !in_array((int) ($row['org_id'] ?? 0), $scope['org_id'], true)) {
            return null;
        }

        return ['basic' => $this->normalizeDetailRow($row)];
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
        foreach (['return_id', 'order_id', 'delivery_id', 'member_id', 'org_id', 'shop_id'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = (int) $row[$field];
            }
        }
        return $row;
    }

    private function normalizeDetailRow(array $row): array
    {
        $row = $this->normalizeListRow($row);
        if (isset($row['items']) && is_array($row['items'])) {
            $row['items'] = array_values($row['items']);
        }
        if (isset($row['check_data']) && is_array($row['check_data'])) {
            $row['check_data'] = array_values($row['check_data']);
        }
        if (isset($row['log']) && is_array($row['log'])) {
            $row['log'] = array_values($row['log']);
        }
        return $row;
    }

    private function resolveOrderBy(string $orderBy): string
    {
        $allowed = ['return_id', 'return_bn', 'order_id', 'status', 'add_time', 'last_modified', 'shop_id', 'org_id'];
        return in_array($orderBy, $allowed, true) ? $orderBy : 'add_time';
    }
}
