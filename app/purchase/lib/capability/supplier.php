<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class purchase_capability_supplier
{
    private object $supplierModel;

    public function __construct(?object $supplierModel = null)
    {
        $this->supplierModel = $supplierModel ?? app::get('purchase')->model('supplier');
    }

    public function list(array $request): array
    {
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['pageSize'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $filter = is_array($request['filters'] ?? null) ? $request['filters'] : [];

        $rows = $this->supplierModel->getList('*', $filter, $offset, $pageSize, 'supplier_id desc');
        $total = method_exists($this->supplierModel, 'count')
            ? (int) $this->supplierModel->count($filter)
            : count($rows);

        return [
            'list' => array_map(fn(array $row): array => $this->normalizeRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public function detail(int $supplierId): ?array
    {
        if ($supplierId < 1) {
            throw new InvalidArgumentException('缺少供应商ID');
        }

        $row = $this->supplierModel->dump(['supplier_id' => $supplierId], '*');
        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    private function normalizeRow(array $row): array
    {
        if (!empty($row['contacter']) && is_string($row['contacter'])) {
            $contacters = @unserialize($row['contacter']);
            $row['contacter'] = is_array($contacters) ? $contacters : $row['contacter'];
        }

        $row['supplier_id'] = (int) ($row['supplier_id'] ?? 0);
        $row['arrive_days'] = $row['arrive_days'] === null || $row['arrive_days'] === '' ? null : (int) $row['arrive_days'];

        return $row;
    }
}
