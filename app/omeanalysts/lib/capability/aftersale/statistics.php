<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class omeanalysts_capability_aftersale_statistics
{
    private const MAX_DATE_RANGE_DAYS = 31;
    private const MAX_REQUESTED_SHOP_IDS = 200;

    private const ALLOWED_FIELDS = [
        'time_from',
        'time_to',
        'shop_id',
        'shop_type',
        'brand_id',
        'goods_type_id',
        'return_type_id',
        'problem_id',
        'page',
        'page_size',
    ];

    private object $model;
    private $visibleShopResolver;

    public function __construct(?object $model = null, ?callable $visibleShopResolver = null)
    {
        $this->model = $model ?? app::get('omeanalysts')->model('ome_aftersale');
        $this->visibleShopResolver = $visibleShopResolver ?? function (array $organizationIds): array {
            if ($organizationIds === []) {
                return [];
            }

            $filter = ['org_id' => $organizationIds];
            $shops = app::get('ome')->model('shop')->getList('shop_id', $filter, 0, -1);

            return array_values(array_unique(array_map('strval', array_column((array) $shops, 'shop_id'))));
        };
    }

    public function execute(array $input, array $allowedOrganizationIds = [], bool $isSuper = false): array
    {
        $request = $this->normalizeRequest($input, $allowedOrganizationIds);
        $visibleShopIds = $this->resolveVisibleShopIds($request['org_id'], $isSuper);
        $request['shop_id'] = $this->intersectRequestedShopIds($request['shop_id'] ?? null, $visibleShopIds);

        if ($request['shop_id'] === []) {
            return $this->emptyStatistics($request['page'], $request['page_size']);
        }

        $data = $this->model->get_count($request);
        $data = is_array($data) ? $data : [];
        $offset = ($request['page'] - 1) * $request['page_size'];
        $rows = $this->model->getList('*', $request, $offset, $request['page_size']);
        $total = $this->model->count($request);

        return [
            'statistics' => [
                'total_nums' => (int) ($data['total_nums'] ?? 0),
                'total_apply_money' => number_format((float) ($data['total_apply_money'] ?? 0), 2, '.', ''),
                'total_refund_money' => number_format((float) ($data['total_refund_money'] ?? 0), 2, '.', ''),
            ],
            'detail' => [
                'list' => is_array($rows) ? $rows : [],
                'total' => (int) $total,
                'page' => $request['page'],
                'page_size' => $request['page_size'],
            ],
        ];
    }

    private function normalizeRequest(array $input, array $allowedOrganizationIds): array
    {
        foreach (array_keys($input) as $field) {
            if (!in_array($field, self::ALLOWED_FIELDS, true)) {
                throw new InvalidArgumentException($field . ' is not supported');
            }
        }

        foreach (['time_from', 'time_to'] as $field) {
            if (!isset($input[$field]) || !is_string($input[$field]) || !$this->isDate($input[$field])) {
                throw new InvalidArgumentException($field . ' must be a valid date in YYYY-MM-DD format');
            }
        }
        if ($input['time_from'] > $input['time_to']) {
            throw new InvalidArgumentException('time_from must be on or before time_to');
        }
        $timeFrom = DateTimeImmutable::createFromFormat('!Y-m-d', $input['time_from']);
        $timeTo = DateTimeImmutable::createFromFormat('!Y-m-d', $input['time_to']);
        if ($timeFrom->diff($timeTo)->days > self::MAX_DATE_RANGE_DAYS) {
            throw new InvalidArgumentException('date range must not exceed ' . self::MAX_DATE_RANGE_DAYS . ' days');
        }

        if (array_key_exists('page', $input) && (!is_int($input['page']) || $input['page'] < 1)) {
            throw new InvalidArgumentException('page must be an integer greater than or equal to 1');
        }
        if (array_key_exists('page_size', $input) && (!is_int($input['page_size']) || $input['page_size'] < 1 || $input['page_size'] > 200)) {
            throw new InvalidArgumentException('page_size must be an integer between 1 and 200');
        }

        if (array_key_exists('shop_id', $input) && !is_string($input['shop_id']) && !is_int($input['shop_id']) && !is_array($input['shop_id'])) {
            throw new InvalidArgumentException('shop_id must be a string, integer, or array');
        }
        if (array_key_exists('shop_id', $input)) {
            $input['shop_id'] = $this->normalizeShopIds($input['shop_id']);
        }
        if (array_key_exists('shop_type', $input) && !is_string($input['shop_type']) && !is_int($input['shop_type'])) {
            throw new InvalidArgumentException('shop_type must be a string or integer');
        }
        foreach (['brand_id', 'goods_type_id', 'return_type_id', 'problem_id'] as $field) {
            if (array_key_exists($field, $input) && !$this->isNonNegativeIntegerLike($input[$field])) {
                throw new InvalidArgumentException($field . ' must be an integer');
            }
        }

        $request = $input;
        $request['page'] = $input['page'] ?? 1;
        $request['page_size'] = $input['page_size'] ?? 20;
        $request['org_id'] = $this->normalizeOrganizationIds($allowedOrganizationIds);

        return $request;
    }

    private function normalizeOrganizationIds(array $organizationIds): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn($organizationId): string => (string) $organizationId,
            $organizationIds
        ), static fn(string $organizationId): bool => $organizationId !== '')));
    }

    private function resolveVisibleShopIds(array $organizationIds, bool $isSuper): array
    {
        if ($isSuper) {
            $shops = app::get('ome')->model('shop')->getList('shop_id', [], 0, -1);

            return array_values(array_unique(array_map('strval', array_column((array) $shops, 'shop_id'))));
        }

        if ($organizationIds === []) {
            return [];
        }

        return ($this->visibleShopResolver)($organizationIds);
    }

    private function intersectRequestedShopIds($requestedShopIds, array $visibleShopIds): array
    {
        $visibleShopIds = array_values(array_unique(array_map('strval', $visibleShopIds)));
        if ($requestedShopIds === null) {
            return $visibleShopIds;
        }

        $requestedShopIds = is_array($requestedShopIds) ? $requestedShopIds : [$requestedShopIds];
        return array_values(array_intersect(array_map('strval', $requestedShopIds), $visibleShopIds));
    }

    private function normalizeShopIds($shopIds): array
    {
        if (is_array($shopIds) && count($shopIds) > self::MAX_REQUESTED_SHOP_IDS) {
            throw new InvalidArgumentException('shop_id must not contain more than ' . self::MAX_REQUESTED_SHOP_IDS . ' IDs');
        }

        $normalized = [];
        foreach (is_array($shopIds) ? $shopIds : [$shopIds] as $shopId) {
            if (!is_string($shopId) && !is_int($shopId)) {
                throw new InvalidArgumentException('shop_id must contain only string or integer IDs');
            }
            if (is_int($shopId) && $shopId < 0) {
                throw new InvalidArgumentException('shop_id must contain only non-negative IDs');
            }
            if (is_string($shopId) && ($shopId === '' || (is_numeric($shopId) && !ctype_digit($shopId)))) {
                throw new InvalidArgumentException('shop_id must contain non-negative integer-like or non-numeric IDs');
            }
            $normalized[] = (string) $shopId;
        }

        return array_values(array_unique($normalized));
    }

    private function isNonNegativeIntegerLike($value): bool
    {
        return (is_int($value) && $value >= 0) || (is_string($value) && ctype_digit($value));
    }

    private function isDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    private function emptyStatistics(int $page, int $pageSize): array
    {
        return [
            'statistics' => [
                'total_nums' => 0,
                'total_apply_money' => '0.00',
                'total_refund_money' => '0.00',
            ],
            'detail' => [
                'list' => [],
                'total' => 0,
                'page' => $page,
                'page_size' => $pageSize,
            ],
        ];
    }
}
