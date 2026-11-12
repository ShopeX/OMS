<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_aftersales_unified
{
    private object $refundModel;
    private object $returnModel;
    private object $reshipModel;
    private object $operationOpsModel;
    private ?object $shopModel;
    private ?object $operationOrganizationModel;

    public function __construct(
        ?object $refundModel = null,
        ?object $returnModel = null,
        ?object $reshipModel = null,
        ?object $operationOpsModel = null,
        ?object $shopModel = null,
        ?object $operationOrganizationModel = null
    ) {
        $this->refundModel = $refundModel ?? app::get('ome')->model('refund_apply');
        $this->returnModel = $returnModel ?? app::get('ome')->model('return_product');
        $this->reshipModel = $reshipModel ?? app::get('ome')->model('reship');
        $this->operationOpsModel = $operationOpsModel ?? app::get('ome')->model('operation_ops');
        $this->shopModel = $shopModel;
        $this->operationOrganizationModel = $operationOrganizationModel;
    }

    public function list(array $request, mcpapi_auth_context $context): array
    {
        $query = trim((string) ($request['query'] ?? ''));
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['page_size'] ?? $request['pageSize'] ?? 20)));
        $scope = $this->permissionScopedFilters($context);
        $sourceWindowLimit = isset($request['source_window_limit'])
            ? max(1, (int) $request['source_window_limit'])
            : -1;
        $partial = false;
        $rows = [];

        foreach ([
            'refund' => $this->refundModel,
            'return' => $this->returnModel,
            'reship' => $this->reshipModel,
        ] as $kind => $model) {
            $sourceRows = (array) $model->getList('*', $scope, 0, $sourceWindowLimit);
            if ($sourceWindowLimit > 0 && count($sourceRows) >= $sourceWindowLimit) {
                $partial = true;
            }
            foreach ($sourceRows as $sourceRow) {
                if (!is_array($sourceRow) || !$this->isVisible($sourceRow, $scope)) {
                    continue;
                }
                if ($query !== '' && !$this->matchesQuery($sourceRow, $query)) {
                    continue;
                }
                $rows[] = $this->normalizeRow($kind, $sourceRow);
            }
        }

        $orgs = $this->orgMap($rows);
        foreach ($rows as &$row) {
            $orgId = (int) ($row['summary']['org_id'] ?? 0);
            if ($orgId > 0 && isset($orgs[$orgId])) {
                $row['summary']['org_name'] = (string) ($orgs[$orgId]['name'] ?? '');
            }
        }
        unset($row);

        $shops = $this->shopMap($rows);
        foreach ($rows as &$row) {
            $shopId = (int) ($row['summary']['shop_id'] ?? 0);
            if ($shopId > 0 && isset($shops[$shopId])) {
                $row['summary']['shop_name'] = (string) ($shops[$shopId]['name'] ?? '');
                $row['summary']['shop_bn'] = (string) ($shops[$shopId]['shop_bn'] ?? '');
            }
        }
        unset($row);

        $orderBy = (string) ($request['order_by'] ?? 'occur_time');
        $orderType = strtolower((string) ($request['order_type'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        usort($rows, function (array $left, array $right) use ($orderBy, $orderType): int {
            $leftValue = $this->sortValue($left, $orderBy);
            $rightValue = $this->sortValue($right, $orderBy);
            $comparison = $leftValue <=> $rightValue;
            if ($comparison === 0) {
                $comparison = ((int) $left['source_id']) <=> ((int) $right['source_id']);
            }
            return $orderType === 'asc' ? $comparison : -$comparison;
        });

        $totalCount = count($rows);
        $items = array_slice($rows, ($page - 1) * $pageSize, $pageSize);

        return [
            'query' => $query,
            'items' => $items,
            'returned_count' => count($items),
            'total_count' => $totalCount,
            'page' => $page,
            'page_size' => $pageSize,
            'has_more' => ($page * $pageSize) < $totalCount,
            'partial' => $partial,
            'warnings' => $partial ? ['one or more source windows were bounded'] : [],
        ];
    }

    private function matchesQuery(array $row, string $query): bool
    {
        foreach (['biz_bn', 'aftersale_bn', 'order_bn', 'refund_apply_bn', 'return_bn', 'reship_bn', 'return_apply_bn', 'platform_order_bn'] as $field) {
            if (isset($row[$field]) && (string) $row[$field] === $query) {
                return true;
            }
        }

        return false;
    }

    private function permissionScopedFilters(mcpapi_auth_context $context): array
    {
        if ($context->isSuper()) {
            return [];
        }

        $ops = $this->operationOpsModel->getList('org_id', ['op_id' => $context->omsSubjectId()], 0, -1);
        $organizationIds = array_values(array_unique(array_map('intval', array_column((array) $ops, 'org_id'))));
        return ['org_id' => $organizationIds === [] ? 'false' : $organizationIds];
    }

    private function isVisible(array $row, array $scope): bool
    {
        if (!isset($scope['org_id']) || $scope['org_id'] === 'false') {
            return !isset($scope['org_id']);
        }
        return in_array((int) ($row['org_id'] ?? 0), $scope['org_id'], true);
    }

    private function normalizeRow(string $kind, array $row): array
    {
        $sourceId = $row['source_id'] ?? match ($kind) {
            'refund' => $row['apply_id'] ?? $row['refund_apply_id'] ?? 0,
            'return' => $row['return_id'] ?? 0,
            'reship' => $row['reship_id'] ?? 0,
            default => 0,
        };
        $occurTime = $row['occur_time'] ?? $row['create_time'] ?? $row['add_time'] ?? $row['t_begin'] ?? null;
        $bizBn = $row['biz_bn'] ?? $row['refund_apply_bn'] ?? $row['return_bn'] ?? $row['reship_bn'] ?? '';

        return [
            'kind' => $kind,
            'source_id' => (int) $sourceId,
            'summary' => [
                'order_id' => (int) ($row['order_id'] ?? 0),
                'order_bn' => (string) ($row['order_bn'] ?? ''),
                'org_id' => (int) ($row['org_id'] ?? 0),
                'org_name' => '',
                'shop_id' => (int) ($row['shop_id'] ?? 0),
                'shop_name' => '',
                'shop_bn' => '',
                'status' => (string) ($row['status'] ?? ''),
                'status_text' => (string) ($row['status_text'] ?? $row['status'] ?? ''),
                'amount' => $row['amount'] ?? null,
                'occur_time' => $occurTime,
                'biz_bn' => (string) $bizBn,
            ],
        ];
    }

    private function orgMap(array $rows): array
    {
        $orgIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['summary']['org_id'] ?? 0),
            $rows
        ), static fn(int $orgId): bool => $orgId > 0)));

        if ($orgIds === []) {
            return [];
        }

        if ($this->operationOrganizationModel === null) {
            $this->operationOrganizationModel = app::get('ome')->model('operation_organization');
        }

        $orgs = $this->operationOrganizationModel->getList('org_id,name', ['org_id' => $orgIds], 0, -1);
        return array_column((array) $orgs, null, 'org_id');
    }

    private function shopMap(array $rows): array
    {
        $shopIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['summary']['shop_id'] ?? 0),
            $rows
        ), static fn(int $shopId): bool => $shopId > 0)));

        if ($shopIds === []) {
            return [];
        }

        if ($this->shopModel === null) {
            $this->shopModel = app::get('ome')->model('shop');
        }

        $shops = $this->shopModel->getList('shop_id,shop_bn,name', ['shop_id' => $shopIds], 0, -1);
        return array_column((array) $shops, null, 'shop_id');
    }

    private function sortValue(array $row, string $orderBy)
    {
        if ($orderBy === 'kind') {
            return $row['kind'];
        }
        if ($orderBy === 'biz_bn') {
            return $row['summary']['biz_bn'];
        }
        return $row['summary']['occur_time'] ?? 0;
    }
}
