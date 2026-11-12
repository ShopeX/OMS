<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_brand
{
    private object $brandModel;
    private object $goodsModel;
    private object $database;

    public function __construct(?object $brandModel = null, ?object $goodsModel = null, ?object $database = null)
    {
        $this->brandModel = $brandModel ?? app::get('ome')->model('brand');
        $this->goodsModel = $goodsModel ?? app::get('ome')->model('goods');
        $this->database = $database ?? kernel::database();
    }

    public function list(array $request): array
    {
        $page = $this->positiveInt($request['page'] ?? 1, 'page');
        $pageSize = $this->positiveInt($request['page_size'] ?? 20, 'page_size');
        $pageSize = min($pageSize, 100);
        $filters = isset($request['filters']) && is_array($request['filters']) ? $request['filters'] : [];
        unset($filters['org_id']);
        // 排序白名单对齐 legacy dbschema in_list 列(brand finder 表头均可点排序)
        $allowedOrderFields = ['brand_id', 'brand_code', 'brand_name', 'brand_keywords', 'brand_url', 'disabled', 'ordernum'];
        $requestedOrderBy = $request['orderBy'] ?? 'ordernum';
        $orderBy = is_string($requestedOrderBy) && in_array($requestedOrderBy, $allowedOrderFields, true)
            ? $requestedOrderBy : 'ordernum';
        $orderType = ($request['orderType'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $rows = $this->brandModel->getList('*', $filters, ($page - 1) * $pageSize, $pageSize, $orderBy . ' ' . $orderType);
        return [
            'list' => array_map(fn(array $row): array => $this->normalize($row), $rows),
            'total' => (int) $this->brandModel->count($filters),
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    public function detail(int $brandId): ?array
    {
        if ($brandId < 1) {
            throw new InvalidArgumentException('品牌ID必须为正整数');
        }
        $row = $this->brandModel->dump(['brand_id' => $brandId], '*');
        return $row ? $this->normalize($row) : null;
    }

    public function create(array $request): array
    {
        $data = $this->validatePayload($request, false);
        if ($this->brandModel->dump(['brand_name' => $data['brand_name']], '*')) {
            throw new InvalidArgumentException('品牌已存在');
        }
        if ($this->brandModel->db_dump(['brand_code' => $data['brand_code']])) {
            throw new InvalidArgumentException('品牌编码已存在');
        }
        $transaction = $this->database->beginTransaction();
        try {
            if ($this->brandModel->save($data) === false) {
                throw new RuntimeException('品牌保存失败');
            }
            $this->database->commit($transaction);
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
        return $this->normalize($data);
    }

    public function update(int $brandId, array $request): array
    {
        if ($brandId < 1) {
            throw new InvalidArgumentException('品牌ID必须为正整数');
        }
        $current = $this->brandModel->dump(['brand_id' => $brandId], '*');
        if (!$current) {
            throw new OutOfBoundsException('品牌不存在');
        }
        $data = $this->validatePayload(array_merge($current, $request), true);
        if ($this->brandModel->db_dump(['brand_code' => $data['brand_code'], 'brand_id|noequal' => $brandId])) {
            throw new InvalidArgumentException('品牌编码已存在');
        }
        $data['brand_id'] = $brandId;
        $transaction = $this->database->beginTransaction();
        try {
            if ($this->brandModel->save($data) === false) {
                throw new RuntimeException('品牌保存失败');
            }
            $this->database->commit($transaction);
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
        return $this->normalize($data);
    }

    public function delete(int $brandId): bool
    {
        if ($brandId < 1) {
            throw new InvalidArgumentException('品牌ID必须为正整数');
        }
        if (!$this->brandModel->dump(['brand_id' => $brandId], '*')) {
            throw new OutOfBoundsException('品牌不存在');
        }
        if ($this->goodsModel->getList('goods_id', ['brand_id' => $brandId], 0, 1)) {
            throw new InvalidArgumentException('该品牌已经被使用，无法删除');
        }
        $transaction = $this->database->beginTransaction();
        try {
            $deleted = (bool) $this->brandModel->delete(['brand_id' => $brandId]);
            if (!$deleted) {
                throw new RuntimeException('品牌删除失败');
            }
            $this->database->commit($transaction);
            return $deleted;
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }

    private function validatePayload(array $request, bool $isUpdate): array
    {
        $fields = ['brand_code', 'brand_name', 'brand_keywords', 'brand_url'];
        $data = [];
        foreach ($fields as $field) {
            if ($isUpdate && !array_key_exists($field, $request)) {
                continue;
            }
            $value = $request[$field] ?? '';
            if (!is_string($value)) {
                throw new InvalidArgumentException($field . '必须为字符串');
            }
            $data[$field] = trim($value);
        }
        if (!$isUpdate || array_key_exists('brand_code', $data)) {
            $this->validateText($data['brand_code'] ?? '', 'brand_code', 50, false);
        }
        if (!$isUpdate || array_key_exists('brand_name', $data)) {
            $this->validateText($data['brand_name'] ?? '', 'brand_name', 50, true);
        }
        if (array_key_exists('brand_keywords', $data)) {
            $this->validateText($data['brand_keywords'], 'brand_keywords', 200, false);
        }
        if (array_key_exists('brand_url', $data)) {
            $this->validateText($data['brand_url'], 'brand_url', 255, false);
        }
        return $data;
    }

    private function validateText(string $value, string $field, int $maxLength, bool $required): void
    {
        if (($required && $value === '') || strlen($value) > $maxLength) {
            throw new InvalidArgumentException($field . '格式不正确');
        }
    }

    private function normalize(array $row): array
    {
        $fields = ['brand_id', 'brand_code', 'brand_name', 'brand_keywords', 'brand_url', 'brand_desc', 'brand_logo', 'disabled', 'ordernum'];
        $normalized = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $row)) {
                $normalized[$field] = $row[$field];
            }
        }
        $normalized['brand_id'] = (int) ($normalized['brand_id'] ?? 0);
        $normalized['brand_code'] = (string) ($normalized['brand_code'] ?? '');
        $normalized['brand_name'] = (string) ($normalized['brand_name'] ?? '');
        $normalized['brand_keywords'] = (string) ($normalized['brand_keywords'] ?? '');
        $normalized['brand_url'] = (string) ($normalized['brand_url'] ?? '');
        $normalized['brand_desc'] = (string) ($normalized['brand_desc'] ?? '');
        $normalized['brand_logo'] = (string) ($normalized['brand_logo'] ?? '');
        $normalized['disabled'] = in_array($normalized['disabled'] ?? false, [true, 1, '1', 'true'], true);
        $normalized['ordernum'] = (int) ($normalized['ordernum'] ?? 0);
        return $normalized;
    }

    private function positiveInt(mixed $value, string $field): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException($field . '必须为正整数');
        }
        $result = (int) $value;
        if ($result < 1) {
            throw new InvalidArgumentException($field . '必须为正整数');
        }
        return $result;
    }
}
