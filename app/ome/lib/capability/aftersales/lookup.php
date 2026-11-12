<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_aftersales_lookup
{
    private object $aftersaleModel;
    private object $operationOpsModel;

    public function __construct(?object $aftersaleModel = null, ?object $operationOpsModel = null)
    {
        $this->aftersaleModel = $aftersaleModel ?? app::get('sales')->model('aftersale');
        $this->operationOpsModel = $operationOpsModel ?? app::get('ome')->model('operation_ops');
    }

    public function lookup(string $query, ?mcpapi_auth_context $context = null): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new InvalidArgumentException('缺少查询条件');
        }

        $organizationScope = $this->organizationScope($context);
        if ($organizationScope === 'false') {
            return ['query' => $query, 'matches' => [], 'count' => 0, 'partial' => false, 'warnings' => []];
        }

        $filters = [
            ['order_bn' => $query],
            ['aftersale_bn|head' => $query],
            ['return_bn' => $query],
            ['reship_bn' => $query],
            ['return_apply_bn' => $query],
            ['platform_order_bn' => $query],
        ];

        $matches = [];
        $partial = false;
        $warnings = [];
        foreach ($filters as $filter) {
            if (is_array($organizationScope)) {
                $filter['org_id'] = $organizationScope;
            }
            $rows = $this->aftersaleModel->getList('*', $filter, 0, 50, 'aftersale_time DESC');
            if (count((array) $rows) === 50) {
                $partial = true;
            }
            foreach ((array) $rows as $row) {
                $aftersaleId = (int) ($row['aftersale_id'] ?? 0);
                if ($aftersaleId < 1 || isset($matches[$aftersaleId]) || !$this->matchesQuery($row, $query)) {
                    continue;
                }
                $matches[$aftersaleId] = $this->normalizeRow($row);
            }
        }

        return [
            'query' => $query,
            'matches' => array_values($matches),
            'count' => count($matches),
            'partial' => $partial,
            'warnings' => $partial ? ['lookup results are based on a bounded source window'] : [],
        ];
    }

    private function organizationScope(?mcpapi_auth_context $context)
    {
        if ($context === null || $context->isSuper()) {
            return null;
        }

        $ops = $this->operationOpsModel->getList('org_id', ['op_id' => $context->omsSubjectId()], 0, -1);
        $organizationIds = array_values(array_unique(array_map('intval', array_column($ops, 'org_id'))));

        return $organizationIds === [] ? 'false' : $organizationIds;
    }

    private function normalizeRow(array $row): array
    {
        $returnType = (string) ($row['return_type'] ?? '');
        $kind = match ($returnType) {
            'refund' => 'refund',
            'return' => 'return',
            'refunded' => 'refund',
            'refuse' => 'refuse',
            'change' => 'reship',
            default => $returnType !== '' ? $returnType : 'unknown',
        };

        return [
            'kind' => $kind,
            'id' => (int) ($row['aftersale_id'] ?? 0),
            'source' => 'sales.aftersale',
            'summary' => [
                'aftersale_bn' => (string) ($row['aftersale_bn'] ?? ''),
                'order_id' => (int) ($row['order_id'] ?? 0),
                'order_bn' => (string) ($row['order_bn'] ?? ''),
                'shop_id' => (int) ($row['shop_id'] ?? 0),
                'org_id' => (int) ($row['org_id'] ?? 0),
                'return_type' => $returnType,
                'refund_apply_money' => $row['refund_apply_money'] ?? null,
                'refundmoney' => $row['refundmoney'] ?? null,
                'real_refund_amount' => $row['real_refund_amount'] ?? null,
                'aftersale_time' => $row['aftersale_time'] ?? null,
            ],
        ];
    }

    private function matchesQuery(array $row, string $query): bool
    {
        foreach (['aftersale_bn', 'order_bn', 'return_bn', 'reship_bn', 'return_apply_bn', 'platform_order_bn'] as $field) {
            if (isset($row[$field]) && (string) $row[$field] === $query) {
                return true;
            }
        }

        return false;
    }
}
