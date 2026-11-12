<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class console_capability_inventory_risk
{
    private object $dailyInventoryModel;
    private object $dailyInventoryItemModel;

    public function __construct(?object $dailyInventoryModel = null, ?object $dailyInventoryItemModel = null)
    {
        $this->dailyInventoryModel = $dailyInventoryModel ?? app::get('console')->model('dailyinventory');
        $this->dailyInventoryItemModel = $dailyInventoryItemModel ?? app::get('console')->model('dailyinventory_items');
    }

    public function review(array $request): array
    {
        $stockDate = trim((string) ($request['stock_date'] ?? ''));
        if ($stockDate === '') {
            throw new InvalidArgumentException('缺少库存日期');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $stockDate)) {
            throw new InvalidArgumentException('库存日期格式必须为 YYYY-MM-DD');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $stockDate);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('库存日期格式必须为 YYYY-MM-DD');
        }
        if (array_key_exists('branch_ids', $request)) {
            throw new InvalidArgumentException('branch_ids is not supported for inventory risk review');
        }

        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['page_size'] ?? 20)));

        $channelFilter = array_filter([
            'stock_date' => $stockDate,
            'channel_type' => $request['channel_type'] ?? null,
            'channel_bn' => $request['channel_bn'] ?? null,
        ], static fn($value) => $value !== null && $value !== '');

        $channels = $this->dailyInventoryModel->getList('*', $channelFilter, 0, -1, 'stock_date DESC,id DESC');
        $channelIds = array_values(array_filter(array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $channels)));
        $itemFilter = array_filter([
            'stock_date' => $stockDate,
            'dlyinv_id' => $channelIds === [] ? [0] : $channelIds,
            'warehouse_code' => $request['warehouse_code'] ?? null,
            'material_bn' => $request['material_bn'] ?? null,
            'diff_stock|noequal' => $this->isDiffOnly($request) ? '0' : null,
        ], static fn($value) => $value !== null && $value !== '');
        $items = $this->dailyInventoryItemModel->getList('*', $itemFilter, 0, -1, 'stock_date DESC,id DESC');
        $totalCount = count($items);
        $offset = ($page - 1) * $pageSize;
        $pagedItems = array_slice($items, $offset, $pageSize);
        $hasMore = ($offset + $pageSize) < $totalCount;
        $partial = $hasMore;
        $warnings = $partial ? ['result window is bounded'] : [];

        return [
            'query' => [
                'stock_date' => $stockDate,
                'channel_type' => $request['channel_type'] ?? null,
                'channel_bn' => $request['channel_bn'] ?? null,
                'warehouse_code' => $request['warehouse_code'] ?? null,
                'material_bn' => $request['material_bn'] ?? null,
                'diff_only' => $this->isDiffOnly($request),
            ],
            'summary' => [
                'channel_total' => count($channels),
                'diff_channel_total' => count(array_filter($channels, static fn(array $row): bool => (string) ($row['is_diff'] ?? '0') === '1')),
                'oms_stock_total' => array_sum(array_map(static fn(array $row): int => (int) ($row['oms_stock'] ?? 0), $channels)),
                'outer_stock_total' => array_sum(array_map(static fn(array $row): int => (int) ($row['outer_stock'] ?? 0), $channels)),
                'diff_stock_total' => array_sum(array_map(static fn(array $row): int => abs((int) ($row['diff_stock'] ?? 0)), $channels)),
            ],
            'items' => array_map(fn(array $row): array => $this->normalizeItem($row), $pagedItems),
            'total_count' => $totalCount,
            'returned_count' => count($pagedItems),
            'page' => $page,
            'page_size' => $pageSize,
            'has_more' => $hasMore,
            'partial' => $partial,
            'warnings' => $warnings,
        ];
    }

    private function isDiffOnly(array $request): bool
    {
        $value = $request['diff_only'] ?? true;
        return !in_array($value, [false, 'false', 0, '0'], true);
    }

    private function normalizeItem(array $row): array
    {
        foreach (['id', 'dlyinv_id', 'bm_id', 'oms_stock', 'outer_stock', 'diff_stock', 'diff_type'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = (int) $row[$field];
            }
        }

        return $row;
    }
}
