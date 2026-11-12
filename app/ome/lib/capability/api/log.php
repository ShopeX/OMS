<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_api_log
{
    private object $apiLogModel;
    private ?object $apiFunc;

    public function __construct(?object $apiLogModel = null, ?object $apiFunc = null)
    {
        $this->apiLogModel = $apiLogModel ?? app::get('ome')->model('api_log');
        $this->apiFunc = $apiFunc ?? ((class_exists('kernel', false) && method_exists('kernel', 'single')) ? kernel::single('ome_api_func') : null);
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

        $rows = $this->apiLogModel->getList('*', $filter, $offset, $pageSize, $orderClause);
        $total = method_exists($this->apiLogModel, 'count')
            ? (int) $this->apiLogModel->count($filter)
            : count($rows);

        return [
            'list' => array_map(fn(array $row): array => $this->normalizeListRow($row), $rows),
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

        $row = $this->apiLogModel->dump($logId);
        if (!is_array($row) || $row === []) {
            return null;
        }

        if ($this->apiFunc !== null && method_exists($this->apiFunc, 'processApiLogData')) {
            $processed = $this->apiFunc->processApiLogData($row);
            if (is_array($processed)) {
                $row = $processed;
            }
        }

        return $this->normalizeDetailRow($row);
    }

    private function normalizeListRow(array $row): array
    {
        if (isset($row['retry'])) {
            $row['retry'] = (int) $row['retry'];
        }
        if (isset($row['spendtime'])) {
            $row['spendtime'] = (float) $row['spendtime'];
        }
        return $row;
    }

    private function normalizeDetailRow(array $row): array
    {
        $normalized = $row;
        if (isset($normalized['retry'])) {
            $normalized['retry'] = (int) $normalized['retry'];
        }
        if (isset($normalized['spendtime'])) {
            $normalized['spendtime'] = (float) $normalized['spendtime'];
        }
        return $normalized;
    }

    private function resolveOrderBy(mixed $orderBy): string
    {
        if (!is_string($orderBy) || trim($orderBy) === '') {
            return '';
        }

        $allowed = ['createtime', 'last_modified', 'status', 'original_bn', 'task_name', 'worker', 'msg_id', 'spendtime'];
        return in_array($orderBy, $allowed, true) ? $orderBy : '';
    }
}
