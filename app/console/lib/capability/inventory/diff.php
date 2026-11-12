<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class console_capability_inventory_diff
{
    private object $dailyInvModel;
    private object $dailyInvItemModel;
    private object $branchModel;
    private object $branchOpsModel;
    private object $branchRelationModel;

    public function __construct(?object $dailyInvModel = null, ?object $dailyInvItemModel = null, ?object $branchModel = null, ?object $branchOpsModel = null, ?object $branchRelationModel = null)
    {
        $this->dailyInvModel = $dailyInvModel ?? app::get('console')->model('dailyinventory');
        $this->dailyInvItemModel = $dailyInvItemModel ?? app::get('console')->model('dailyinventory_items');
        $this->branchModel = $branchModel ?? app::get('ome')->model('branch');
        $this->branchOpsModel = $branchOpsModel ?? app::get('ome')->model('branch_ops');
        $this->branchRelationModel = $branchRelationModel ?? app::get('wmsmgr')->model('branch_relation');
    }

    public function overview(array $request, ?mcpapi_auth_context $context = null): array
    {
        $stockDate = trim((string) ($request['stock_date'] ?? ''));
        $this->validateStockDate($stockDate);
        $channelType = (string) ($request['channel_type'] ?? 'wms');
        if (!in_array($channelType, ['wms', 'store'], true)) {
            throw new InvalidArgumentException('channel_type 仅支持 wms|store');
        }
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['pageSize'] ?? $request['page_size'] ?? 20)));
        $filter = $this->applyDailyInvScope(['stock_date' => $stockDate, 'channel_type' => $channelType, 'is_diff' => '1'], $context);
        $rows = $this->dailyInvModel->getList('*', $filter, ($page - 1) * $pageSize, $pageSize, 'id desc');
        $total = method_exists($this->dailyInvModel, 'count') ? (int) $this->dailyInvModel->count($filter) : count($rows);

        return [
            'list' => array_map(static fn(array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'dailyinventory_bn' => (string) ($row['dailyinventory_bn'] ?? ''),
                'channel_type' => (string) ($row['channel_type'] ?? ''),
                'stock_date' => $row['stock_date'] ?? null,
                'oms_stock' => (int) ($row['oms_stock'] ?? 0),
                'outer_stock' => (int) ($row['outer_stock'] ?? 0),
                'diff_stock' => (int) ($row['diff_stock'] ?? 0),
                'is_diff' => (int) ($row['is_diff'] ?? 0),
            ], $rows),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public function detail(array $request, ?mcpapi_auth_context $context = null): ?array
    {
        $dlyinvId = (int) ($request['dlyinv_id'] ?? 0);
        if ($dlyinvId < 1) {
            throw new InvalidArgumentException('dlyinv_id 必须为正整数');
        }
        $dlyinv = $this->dailyInvModel->dump($dlyinvId, '*');
        if (!is_array($dlyinv) || $dlyinv === [] || !$this->isChannelAccessible($dlyinv['channel_id'] ?? 0, $context)) {
            return null;
        }
        $rows = $this->dailyInvItemModel->getList('*', ['dlyinv_id' => $dlyinvId, 'diff_stock|noequal' => '0'], 0, -1);
        $diffTotal = 0;
        $items = array_map(function (array $row) use (&$diffTotal, $dlyinv): array {
            $diff = (int) ($row['diff_stock'] ?? 0);
            $diffTotal += abs($diff);
            $warehouseCode = trim((string) ($row['warehouse_code'] ?? ''));
            $mapping = $this->resolveWarehouseBranches(
                (string) ($dlyinv['channel_type'] ?? ''),
                (int) ($dlyinv['channel_id'] ?? 0),
                $warehouseCode
            );
            return [
                'stock_date' => $row['stock_date'] ?? null,
                'warehouse_code' => $warehouseCode,
                'branch_ids' => $mapping['branch_ids'],
                'warehouse_mapping' => $mapping['mapping'],
                'material_bn' => (string) ($row['material_bn'] ?? ''),
                'storage_code' => (string) ($row['storage_code'] ?? ''),
                'bm_id' => (int) ($row['bm_id'] ?? 0),
                'oms_stock' => (int) ($row['oms_stock'] ?? 0),
                'outer_stock' => (int) ($row['outer_stock'] ?? 0),
                'diff_stock' => $diff,
                'diff_type' => (int) ($row['diff_type'] ?? 1),
            ];
        }, $rows);

        return [
            'dlyinv' => [
                'id' => (int) ($dlyinv['id'] ?? 0),
                'dailyinventory_bn' => (string) ($dlyinv['dailyinventory_bn'] ?? ''),
                'channel_type' => (string) ($dlyinv['channel_type'] ?? ''),
                'stock_date' => $dlyinv['stock_date'] ?? null,
                'oms_stock' => (int) ($dlyinv['oms_stock'] ?? 0),
                'outer_stock' => (int) ($dlyinv['outer_stock'] ?? 0),
                'diff_stock' => (int) ($dlyinv['diff_stock'] ?? 0),
            ],
            'items' => $items,
            'diff_total' => $diffTotal,
            'diff_count' => count($items),
        ];
    }

    /**
     * Resolve the OMS branches that contributed to the warehouse_code stored by the snapshot job.
     * WMS snapshots prefer branch_relation.wms_branch_bn and fall back to branch.branch_bn;
     * one WMS code may therefore resolve to multiple OMS branches.
     *
     * @return array{branch_ids: array<int>, mapping: string}
     */
    private function resolveWarehouseBranches(string $channelType, int $channelId, string $warehouseCode): array
    {
        if ($warehouseCode === '' || $channelId < 1) {
            return ['branch_ids' => [], 'mapping' => 'unresolved'];
        }

        if ($channelType === 'store') {
            $branches = $this->branchModel->getList('branch_id,branch_bn', ['branch_bn' => $warehouseCode], 0, -1);
            $branchIds = array_values(array_unique(array_map('intval', array_column($branches, 'branch_id'))));
            return ['branch_ids' => $branchIds, 'mapping' => $branchIds === [] ? 'unresolved' : 'branch_bn'];
        }

        if ($channelType !== 'wms') {
            return ['branch_ids' => [], 'mapping' => 'unsupported_channel'];
        }

        $branches = $this->branchModel->getList('branch_id,branch_bn,wms_id', ['wms_id' => $channelId], 0, -1);
        if ($branches === []) {
            return ['branch_ids' => [], 'mapping' => 'unresolved'];
        }

        $relations = $this->branchRelationModel->getList('sys_branch_bn,wms_branch_bn', ['wms_id' => $channelId], 0, -1);
        $relationByBranch = [];
        foreach ($relations as $relation) {
            $sysBranchBn = trim((string) ($relation['sys_branch_bn'] ?? ''));
            if ($sysBranchBn !== '') {
                $relationByBranch[$sysBranchBn] = trim((string) ($relation['wms_branch_bn'] ?? ''));
            }
        }

        $branchIds = [];
        $mappingTypes = [];
        foreach ($branches as $branch) {
            $branchId = (int) ($branch['branch_id'] ?? 0);
            $branchBn = trim((string) ($branch['branch_bn'] ?? ''));
            if ($branchId < 1) {
                continue;
            }
            $mappedCode = $relationByBranch[$branchBn] ?? '';
            if (($mappedCode !== '' && $mappedCode === $warehouseCode)
                || ($mappedCode === '' && $branchBn === $warehouseCode)) {
                $branchIds[] = $branchId;
                $mappingTypes[] = $mappedCode !== '' ? 'wms_branch_bn' : 'branch_bn_fallback';
            }
        }

        $mappingTypes = array_values(array_unique($mappingTypes));
        return [
            'branch_ids' => array_values(array_unique($branchIds)),
            'mapping' => $branchIds === [] ? 'unresolved' : (count($mappingTypes) === 1 ? $mappingTypes[0] : 'mixed'),
        ];
    }

    private function validateStockDate(string $stockDate): void
    {
        if ($stockDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $stockDate)) {
            throw new InvalidArgumentException('stock_date 必须为 YYYY-MM-DD 格式');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $stockDate, new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $stockDate) {
            throw new InvalidArgumentException('stock_date 必须为 YYYY-MM-DD 格式');
        }
    }

    private function applyDailyInvScope(array $filter, ?mcpapi_auth_context $context): array
    {
        if ($context === null || $context->isSuper()) return $filter;
        $wmsIds = $this->resolveUserWmsIds($context);
        $filter['channel_id'] = $wmsIds === [] ? 'false' : $wmsIds;
        return $filter;
    }

    private function isChannelAccessible($channelId, ?mcpapi_auth_context $context): bool
    {
        if ($context === null || $context->isSuper()) return true;
        return in_array((int) $channelId, array_map('intval', $this->resolveUserWmsIds($context)), true);
    }

    private function resolveUserWmsIds(mcpapi_auth_context $context): array
    {
        $branchOps = $this->branchOpsModel->getList('branch_id', ['op_id' => $context->omsSubjectId()], 0, -1);
        $branchIds = array_values(array_unique(array_map('intval', array_column($branchOps, 'branch_id'))));
        if ($branchIds === []) return [];
        $branches = $this->branchModel->getList('wms_id', ['type' => 'main', 'b_type' => 1, 'branch_id' => $branchIds], 0, -1);
        return array_values(array_unique(array_map('intval', array_filter(array_column($branches, 'wms_id')))));
    }
}
