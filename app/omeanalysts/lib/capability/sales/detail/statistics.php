<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class omeanalysts_capability_sales_detail_statistics
{
    private const AGGREGATION_BATCH_SIZE = 200;

    private const ORDERABLE_FIELDS = [
        'type_id', 'bn', 'name', 'goods_bn', 'goods_name', 'sales_material_bn', 'sales_material_name',
        'sale_price', 'sale_num', 'sale_amount', 'day_amount', 'day_num', 'reship_num', 'reship_ratio',
        'cost_amount', 'gross_sales', 'gross_sales_rate', 'cat_id',
    ];

    private const ALLOWED_INPUT_FIELDS = [
        'material_type', 'shop_id', 'shop_type', 'time_from', 'time_to', 'order_status',
        'page', 'page_size', 'order_by', 'order_type', 'include_detail', 'include_aggregations',
        'bn', 'goods_bn', 'sales_material_bn',
    ];

    private $modelResolver;

    private $shopNameResolver;

    public function __construct(?callable $modelResolver = null, ?callable $shopNameResolver = null)
    {
        $this->modelResolver = $modelResolver ?? function (string $materialType): object {
            return $this->resolveNativeModel($materialType);
        };
        $this->shopNameResolver = $shopNameResolver ?? function (array $shopIds): array {
            return $this->resolveShopNamesFromModel($shopIds);
        };
    }

    /**
     * 默认店铺名解析：批量读取 ome shop 表，把 shop_id 映射为店铺名。
     * 解析失败时返回空 map（非致命），调用方保留原始 shop_id 作为 label。
     */
    protected function resolveShopNamesFromModel(array $shopIds): array
    {
        $nameById = [];
        try {
            $shopModel = app::get('ome')->model('shop');
            foreach ($shopModel->getList('shop_id,name', ['shop_id' => $shopIds]) as $shop) {
                if (isset($shop['shop_id'], $shop['name']) && $shop['name'] !== '') {
                    $nameById[(string) $shop['shop_id']] = (string) $shop['name'];
                }
            }
        } catch (Throwable) {
            // 非致命：店铺名解析失败时保留原始 shop_id
        }

        return $nameById;
    }

    public function execute(array $input, array $allowedOrganizationIds = []): array
    {
        $request = $this->normalizeRequest($input, $allowedOrganizationIds);
        $materialType = $request['material_type'];
        $model = ($this->modelResolver)($materialType);
        $offset = ($request['page'] - 1) * $request['page_size'];
        $order = $request['order_by'] !== '' ? $request['order_by'] . ' ' . $request['order_type'] : null;

        $summaryData = $this->normalizeSummary($model->get_products($request));
        $rows = $this->normalizeRows($model->getList('*', $request, $offset, $request['page_size'], $order));
        $total = $this->normalizeTotal($model->count($request));
        $aggregations = $request['include_aggregations']
            ? $this->getAggregations($model, $request, $order, $materialType)
            : null;

        return [
            'detail' => [
                'list' => $request['include_detail'] ? $this->attachShopNames($rows) : [],
                'total' => $request['include_detail'] ? (int) $total : 0,
                'page' => $request['page'],
                'page_size' => $request['page_size'],
            ],
            'summary' => $this->summary($summaryData),
            'aggregations' => $request['include_aggregations'] ? $aggregations
                : [
                    'by_product' => $this->emptyAggregation('product'),
                    'by_shop' => $this->emptyAggregation('shop'),
                ],
        ];
    }

    private function normalizeRequest(array $input, array $allowedOrganizationIds): array
    {
        foreach (array_keys($input) as $field) {
            if (!in_array($field, self::ALLOWED_INPUT_FIELDS, true)) {
                throw new InvalidArgumentException($field . ' is not supported');
            }
        }

        if (array_key_exists('material_type', $input) && !is_string($input['material_type'])) {
            throw new InvalidArgumentException('material_type must be a string');
        }
        $materialType = $input['material_type'] ?? 'basic_material';
        $allowedTypes = ['basic_material', 'sales_material', 'sales_and_basic_material'];
        if (!in_array($materialType, $allowedTypes, true)) {
            throw new InvalidArgumentException('material_type is invalid');
        }

        $incompatible = match ($materialType) {
            'basic_material' => ['goods_bn', 'sales_material_bn'],
            'sales_material' => ['bn', 'sales_material_bn'],
            'sales_and_basic_material' => ['goods_bn'],
        };
        foreach ($incompatible as $field) {
            if (array_key_exists($field, $input) && $input[$field] !== '' && $input[$field] !== null && $input[$field] !== []) {
                throw new InvalidArgumentException($field . ' is only supported for ' . $this->materialTypeForField($field));
            }
        }
        foreach (match ($materialType) {
            'basic_material' => ['bn'],
            'sales_material' => ['goods_bn'],
            'sales_and_basic_material' => ['bn', 'sales_material_bn'],
        } as $field) {
            if (array_key_exists($field, $input) && !is_string($input[$field])) {
                throw new InvalidArgumentException($field . ' must be a string');
            }
        }

        $hasTimeFrom = array_key_exists('time_from', $input) && $input['time_from'] !== '' && $input['time_from'] !== null;
        $hasTimeTo = array_key_exists('time_to', $input) && $input['time_to'] !== '' && $input['time_to'] !== null;
        if (!$hasTimeFrom && !$hasTimeTo) {
            throw new InvalidArgumentException('time_from must be provided');
        }
        if ($hasTimeFrom !== $hasTimeTo) {
            $missingField = $hasTimeFrom ? 'time_to' : 'time_from';
            throw new InvalidArgumentException($missingField . ' must be provided when ' . ($hasTimeFrom ? 'time_from' : 'time_to') . ' is provided');
        }

        $dates = [];
        foreach (['time_from', 'time_to'] as $field) {
            $value = $input[$field];
            if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                throw new InvalidArgumentException($field . ' must be a valid date in YYYY-MM-DD format');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new InvalidArgumentException($field . ' must be a valid date in YYYY-MM-DD format');
            }
            $dates[$field] = $date;
        }
        if ($hasTimeFrom && $dates['time_from'] > $dates['time_to']) {
            throw new InvalidArgumentException('time_from must be on or before time_to');
        }
        if ($dates['time_from']->diff($dates['time_to'])->days > 31) {
            throw new InvalidArgumentException('date range must not exceed 31 days');
        }

        if (array_key_exists('page', $input) && (!is_int($input['page']) || $input['page'] < 1)) {
            throw new InvalidArgumentException('page must be an integer greater than or equal to 1');
        }
        if (array_key_exists('page_size', $input) && (!is_int($input['page_size']) || $input['page_size'] < 1 || $input['page_size'] > 200)) {
            throw new InvalidArgumentException('page_size must be an integer between 1 and 200');
        }
        foreach (['include_detail', 'include_aggregations'] as $flag) {
            if (array_key_exists($flag, $input) && !is_bool($input[$flag])) {
                throw new InvalidArgumentException($flag . ' must be a boolean');
            }
        }
        if (array_key_exists('order_by', $input) && !is_string($input['order_by'])) {
            throw new InvalidArgumentException('order_by must be a string');
        }
        $orderBy = (string) ($input['order_by'] ?? '');
        if ($orderBy !== '' && !in_array($orderBy, self::ORDERABLE_FIELDS, true)) {
            throw new InvalidArgumentException('order_by is not supported');
        }
        if (array_key_exists('order_type', $input) && !is_string($input['order_type'])) {
            throw new InvalidArgumentException('order_type must be asc or desc');
        }
        $orderType = strtolower((string) ($input['order_type'] ?? 'desc'));
        if (!in_array($orderType, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('order_type must be asc or desc');
        }

        $page = $input['page'] ?? 1;
        $pageSize = $input['page_size'] ?? 20;
        $request = $input;
        $request['material_type'] = $materialType;
        $request['page'] = $page;
        $request['page_size'] = $pageSize;
        $request['order_by'] = $orderBy;
        $request['order_type'] = $orderType;
        $request['include_detail'] = ($input['include_detail'] ?? true) !== false;
        $request['include_aggregations'] = ($input['include_aggregations'] ?? true) !== false;
        $request['time_from'] = $hasTimeFrom ? $input['time_from'] : '';
        $request['time_to'] = $hasTimeTo ? $input['time_to'] : '';
        $request['org_id'] = $this->normalizeOrganizationIds($allowedOrganizationIds);
        $request['_sale_amount_search'] = false;
        $request['_sale_num_search'] = false;

        return $request;
    }

    private function normalizeOrganizationIds(array $organizationIds): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn($organizationId): string => (string) $organizationId,
            $organizationIds
        ), static fn(string $organizationId): bool => $organizationId !== '')));
    }

    private static function nativeModelClass(string $materialType): string
    {
        return match ($materialType) {
            'sales_material' => 'omeanalysts_mdl_sales_goods',
            'sales_and_basic_material' => 'omeanalysts_mdl_sales_products',
            default => 'omeanalysts_mdl_ome_products',
        };
    }

    protected function resolveNativeModel(string $materialType): object
    {
        return kernel::single(self::nativeModelClass($materialType));
    }

    private function getAggregations(object $model, array $request, ?string $order, string $materialType): array
    {
        $groups = ['product' => [], 'shop' => []];
        $offset = 0;
        do {
            $chunk = $this->normalizeRows($model->getList('*', $request, $offset, self::AGGREGATION_BATCH_SIZE, $order));
            if ($chunk === []) {
                break;
            }
            $this->addAggregationBatch($groups, $chunk, $materialType);
            $offset += count($chunk);
        } while (count($chunk) === self::AGGREGATION_BATCH_SIZE);

        return [
            'by_product' => $this->formatAggregation($groups['product'], 'product'),
            'by_shop' => $materialType === 'sales_material'
                ? $this->emptyAggregation('shop')
                : $this->formatAggregation($this->applyShopNamesToAggregation($groups['shop']), 'shop'),
        ];
    }

    /**
     * 对按店铺聚合的分组，把仍是原始 shop_id 的 label 解析为店铺名。
     * 已带店铺名（label !== 自身 shop_id）的分组保持原样，避免不必要的解析。
     */
    private function applyShopNamesToAggregation(array $shopGroups): array
    {
        $unresolved = [];
        foreach ($shopGroups as $shopId => $group) {
            if (($group['label'] ?? '') === $shopId) {
                $unresolved[] = $shopId;
            }
        }
        if ($unresolved === []) {
            return $shopGroups;
        }

        $nameById = ($this->shopNameResolver)($unresolved);
        foreach ($unresolved as $shopId) {
            if (isset($nameById[$shopId]) && $nameById[$shopId] !== '') {
                $shopGroups[$shopId]['label'] = $nameById[$shopId];
            }
        }

        return $shopGroups;
    }

    /**
     * 为明细行补充 shop_name（当行内缺少 shop_name 且存在 shop_id 时）。
     */
    private function attachShopNames(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $unresolved = [];
        foreach ($rows as $row) {
            $shopId = (string) ($row['shop_id'] ?? '');
            if ($shopId !== '' && (string) ($row['shop_name'] ?? '') === '') {
                $unresolved[$shopId] = true;
            }
        }
        if ($unresolved === []) {
            return $rows;
        }

        $nameById = ($this->shopNameResolver)(array_keys($unresolved));
        if ($nameById === []) {
            return $rows;
        }

        foreach ($rows as $key => $row) {
            $shopId = (string) ($row['shop_id'] ?? '');
            if ($shopId !== '' && isset($nameById[$shopId]) && $nameById[$shopId] !== '') {
                $rows[$key]['shop_name'] = $nameById[$shopId];
            }
        }

        return $rows;
    }

    private function normalizeRows($rows): array
    {
        return is_array($rows) ? $rows : [];
    }

    private function normalizeSummary($summary): array
    {
        return is_array($summary) ? $summary : [];
    }

    private function normalizeTotal($total): int
    {
        if (is_int($total)) {
            return max(0, $total);
        }
        if (is_numeric($total)) {
            return max(0, (int) $total);
        }
        return 0;
    }

    private function materialTypeForField(string $field): string
    {
        return match ($field) {
            'bn' => 'sales_and_basic_material',
            'goods_bn' => 'sales_material',
            'sales_material_bn' => 'sales_and_basic_material',
        };
    }

    private function summary(array $data): array
    {
        return [
            ['key' => 'sale_num', 'label' => '销售量', 'value' => $data['salenums'] ?? 0],
            ['key' => 'sale_amount', 'label' => '销售额', 'value' => $data['sale_amount'] ?? 0],
            ['key' => 'day_amounts', 'label' => '日均销售额', 'value' => $data['day_amounts'] ?? 0],
            ['key' => 'day_nums', 'label' => '日均销售量', 'value' => $data['day_nums'] ?? 0],
            ['key' => 'gross_sales', 'label' => '销售毛利', 'value' => $data['gross_sales'] ?? 0],
            ['key' => 'gross_sales_rate', 'label' => '销售毛利率', 'value' => $data['gross_sales_rate'] ?? 0],
            ['key' => 'reship_nums', 'label' => '退货量', 'value' => $data['reship_nums'] ?? 0],
            ['key' => 'reship_amounts', 'label' => '退货总额', 'value' => $data['reship_amounts'] ?? 0],
        ];
    }

    private function addAggregationBatch(array &$groups, array $rows, string $materialType): void
    {
        foreach ($rows as $row) {
            foreach (['product', 'shop'] as $dimension) {
                if ($materialType === 'sales_material' && $dimension === 'shop') {
                    continue;
                }
                if ($dimension === 'shop') {
                    $key = (string) ($row['shop_id'] ?? '');
                    $label = (string) ($row['shop_name'] ?? $key);
                } else {
                    $key = match ($materialType) {
                        'sales_material' => (string) ($row['goods_bn'] ?? ''),
                        'sales_and_basic_material' => (string) ($row['sales_material_bn'] ?? $row['bn'] ?? ''),
                        default => (string) ($row['bn'] ?? ''),
                    };
                    $label = (string) ($row['name'] ?? $row['goods_name'] ?? $row['sales_material_name'] ?? $key);
                }
                if ($key === '') {
                    continue;
                }
                if (!isset($groups[$dimension][$key])) {
                    $groups[$dimension][$key] = ['label' => $label, 'value' => 0];
                }
                $groups[$dimension][$key]['value'] += (float) ($row['sale_amount'] ?? 0);
            }
        }
    }

    private function formatAggregation(array $groups, string $dimension): array
    {
        uasort($groups, static fn(array $left, array $right): int => $right['value'] <=> $left['value']);

        return [
            'dimension' => $dimension,
            'metric' => 'sale_amount',
            'suggestedChartType' => $dimension === 'product' ? 'bar' : 'pie',
            'labels' => array_column($groups, 'label'),
            'series' => [[
                'name' => '销售额',
                'data' => array_column($groups, 'value'),
            ]],
        ];
    }

    private function emptyAggregation(string $dimension): array
    {
        return [
            'dimension' => $dimension,
            'metric' => 'sale_amount',
            'suggestedChartType' => $dimension === 'product' ? 'bar' : 'pie',
            'labels' => [],
            'series' => [],
        ];
    }
}
