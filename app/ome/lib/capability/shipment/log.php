<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_shipment_log
{
    private object $shipmentLogModel;

    public function __construct(?object $shipmentLogModel = null)
    {
        $this->shipmentLogModel = $shipmentLogModel ?? app::get('ome')->model('shipment_log');
    }

    public function list(array $request): array
    {
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['pageSize'] ?? $request['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;
        $filter = is_array($request['filters'] ?? null) ? $request['filters'] : [];
        $orderBy = $this->resolveOrderBy($request['orderBy'] ?? null);
        $orderType = strtoupper((string) ($request['orderType'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $orderClause = $orderBy === '' ? '' : $orderBy . ' ' . $orderType;

        $rows = $this->shipmentLogModel->getList('*', $filter, $offset, $pageSize, $orderClause);
        $total = method_exists($this->shipmentLogModel, 'count')
            ? (int) $this->shipmentLogModel->count($filter)
            : count($rows);

        return [
            'list' => array_map(fn(array $row): array => $this->normalizeRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public function detail(string $logId): ?array
    {
        $logId = trim($logId);
        if ($logId === '') {
            throw new InvalidArgumentException('缺少日志ID');
        }

        $row = $this->shipmentLogModel->dump($logId);
        if (!is_array($row) || $row === []) {
            return null;
        }

        return $this->normalizeRow($row);
    }

    private function normalizeRow(array $row): array
    {
        foreach (['shopId', 'ownerId'] as $field) {
            if (isset($row[$field]) && is_numeric($row[$field])) {
                $row[$field] = (int) $row[$field];
            }
        }
        return $row;
    }

    private function resolveOrderBy(mixed $orderBy): string
    {
        if (!is_string($orderBy) || trim($orderBy) === '') {
            return '';
        }

        $allowed = ['receiveTime', 'updateTime', 'status', 'shopId', 'orderBn', 'deliveryCode', 'deliveryCropName'];
        return in_array($orderBy, $allowed, true) ? $orderBy : '';
    }
}
