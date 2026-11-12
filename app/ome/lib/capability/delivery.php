<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_delivery
{
    private object $deliveryModel;
    private object $operationOpsModel;

    public function __construct(?object $deliveryModel = null, ?object $operationOpsModel = null)
    {
        $this->deliveryModel = $deliveryModel ?? app::get('ome')->model('delivery');
        $this->operationOpsModel = $operationOpsModel ?? app::get('ome')->model('operation_ops');
    }

    public function list(array $request, ?mcpapi_auth_context $context = null): array
    {
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['pageSize'] ?? $request['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $filter = is_array($request['filters'] ?? null) ? $request['filters'] : [];
        $filter = array_merge($filter, $this->baseFilter());
        $orderBy = $this->resolveOrderBy((string) ($request['orderBy'] ?? 'delivery_id'));
        $orderType = strtoupper((string) ($request['orderType'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $filter = $this->applyOrganizationScope($filter, $context);

        $rows = $this->deliveryModel->getList('*', $filter, $offset, $pageSize, $orderBy . ' ' . $orderType);
        $total = method_exists($this->deliveryModel, 'count')
            ? (int) $this->deliveryModel->count($filter)
            : count($rows);

        return [
            'list' => array_map(fn(array $row): array => $this->normalizeListRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public function detail(int $deliveryId, ?mcpapi_auth_context $context = null): ?array
    {
        if ($deliveryId < 1) {
            throw new InvalidArgumentException('缺少发货单ID');
        }

        $scope = $this->applyOrganizationScope([], $context);
        if (($scope['org_id'] ?? null) === 'false') {
            return null;
        }

        $row = $this->deliveryModel->dump(['delivery_id' => $deliveryId]);
        if (!is_array($row) || $row === []) {
            return null;
        }
        if (($row['type'] ?? null) !== 'normal' || ($row['pause'] ?? null) === 'true' || ($row['parent_id'] ?? 0) != 0 || ($row['disabled'] ?? null) === 'true') {
            return null;
        }
        if (isset($row['status']) && !in_array((string) $row['status'], ['ready', 'progress', 'succ'], true)) {
            return null;
        }
        if (isset($scope['org_id']) && is_array($scope['org_id']) && !in_array((int) ($row['org_id'] ?? 0), $scope['org_id'], true)) {
            return null;
        }

        return ['basic' => $this->normalizeListRow($row)];
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
        foreach (['delivery_id', 'logi_id', 'branch_id', 'shop_id', 'org_id'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = (int) $row[$field];
            }
        }
        return $row;
    }

    private function baseFilter(): array
    {
        return [
            'type' => 'normal',
            'pause' => 'false',
            'parent_id' => 0,
            'disabled' => 'false',
            'status' => ['ready', 'progress', 'succ'],
        ];
    }

    private function resolveOrderBy(string $orderBy): string
    {
        $allowed = ['delivery_id', 'delivery_bn', 'create_time', 'delivery_time', 'status', 'branch_id', 'logi_id', 'shop_id'];
        return in_array($orderBy, $allowed, true) ? $orderBy : 'delivery_id';
    }
}
