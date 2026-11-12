<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class material_capability_cat
{
    private object $model;
    private object $database;

    public function __construct(?object $model = null, ?object $database = null)
    {
        $this->model = $model ?? app::get('material')->model('basic_material_cat');
        $this->database = $database ?? kernel::database();
    }

    public function tree(): array
    {
        return $this->normalizeTree($this->model->getTree());
    }

    public function detail(int $catId): ?array
    {
        if ($catId < 1) {
            throw new InvalidArgumentException('分类ID不能为空');
        }

        $row = $this->model->dump(['cat_id' => $catId], '*');
        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    public function create(array $request): array
    {
        return $this->persist($request, null);
    }

    public function update(int $catId, array $request): array
    {
        if ($catId < 1) {
            throw new InvalidArgumentException('分类ID不能为空');
        }

        $existing = $this->model->dump((int) $catId);
        if (!$existing || empty($existing['cat_id'])) {
            throw new OutOfBoundsException('分类不存在');
        }

        return $this->persist($request, $existing);
    }

    public function delete(int $catId): bool
    {
        if ($catId < 1) {
            throw new InvalidArgumentException('分类ID不能为空');
        }

        $existing = $this->model->dump($catId);
        if (!$existing) {
            throw new OutOfBoundsException('分类不存在');
        }

        $message = '';
        $transaction = $this->database->beginTransaction();
        try {
            if (!$this->model->toRemove($catId, $message)) {
                throw new InvalidArgumentException($message !== '' ? $message : '删除失败');
            }

            $this->database->commit($transaction);
            return true;
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }

    public function sort(array $orders): int
    {
        if ($orders === []) {
            throw new InvalidArgumentException('没有要更新的排序数据');
        }

        $transaction = $this->database->beginTransaction();
        try {
            $count = 0;
            foreach ($orders as $catId => $order) {
                $catId = (int) $catId;
                if ($catId <= 0) {
                    continue;
                }
                $updated = $this->model->update(['p_order' => $order === '' ? null : (int) $order], ['cat_id' => $catId]);
                if ($updated === false) {
                    throw new RuntimeException('分类排序更新失败');
                }
                $count++;
            }

            if (method_exists($this->model, 'cat2json')) {
                $this->model->cat2json();
            }

            $this->database->commit($transaction);
            return $count;
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }

    private function persist(array $request, ?array $existing): array
    {
        $catData = $request['cat'] ?? $request;
        if (!is_array($catData)) {
            throw new InvalidArgumentException('分类数据不能为空');
        }
        if (empty($catData['cat_name'])) {
            throw new InvalidArgumentException('分类名称不能为空');
        }

        // 上级分类：legacy `admin/material/category/info.html` 编辑态同样渲染可选的下拉
        // （`input type=select name="cat[parent_id]"`），即父分类**可改**；此处按提交值优先、
        // 缺省回退到现有值，保持与 legacy 一致（原实现无条件取 existing，会静默丢弃父分类改动）。
        $parentId = (int) ($catData['parent_id'] ?? ($existing['parent_id'] ?? 0));

        $duplicateFilter = [
            'cat_name' => $catData['cat_name'],
            'parent_id' => $parentId,
        ];
        $catId = (int) ($catData['cat_id'] ?? ($existing['cat_id'] ?? 0));
        if ($catId > 0) {
            $duplicateFilter['cat_id!'] = $catId;
        }
        $duplicate = $this->model->dump($duplicateFilter);
        if ($duplicate && !empty($duplicate['cat_id'])) {
            throw new InvalidArgumentException('分类名称不能重复');
        }

        $minPrice = (int) ($catData['min_price'] ?? 0);
        $maxPrice = (int) ($catData['max_price'] ?? 0);
        if ($minPrice < 0 || ($maxPrice > 0 && $maxPrice < $minPrice)) {
            throw new InvalidArgumentException('最高价必须大于最低价，且不能为负数');
        }

        $saveData = [
            'cat_name' => utils::safe_input((string) $catData['cat_name']),
            'cat_code' => utils::safe_input((string) ($catData['cat_code'] ?? '')),
            'parent_id' => $parentId,
            'p_order' => (int) ($catData['p_order'] ?? 0),
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'create_time' => time(),
            'last_modify' => time(),
        ];

        if ($catId > 0) {
            $saveData['cat_id'] = $catId;
        }

        $transaction = $this->database->beginTransaction();
        try {
            if (!$this->model->save($saveData)) {
                throw new RuntimeException('保存失败');
            }

            $this->database->commit($transaction);
            return ['cat_id' => (int) ($saveData['cat_id'] ?? 0), 'success' => true];
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }

    private function normalizeTree(array $tree): array
    {
        usort($tree, static function (array $left, array $right): int {
            if ((int) ($left['pid'] ?? 0) === (int) ($right['pid'] ?? 0)) {
                return (int) ($left['p_order'] ?? 0) <=> (int) ($right['p_order'] ?? 0);
            }
            return (int) ($left['pid'] ?? 0) <=> (int) ($right['pid'] ?? 0);
        });

        $steps = [];
        $rows = [];
        foreach ($tree as $item) {
            $parentId = (int) ($item['pid'] ?? 0);
            $step = $parentId === 0 || !isset($steps[$parentId]) ? 1 : $steps[$parentId] + 1;
            $catId = (int) ($item['id'] ?? 0);
            if ($catId < 1) {
                continue;
            }
            $steps[$catId] = $step;
            $rows[] = [
                'cat_id' => $catId,
                'parent_id' => $parentId,
                'cat_name' => (string) ($item['text'] ?? ''),
                'cat_code' => (string) ($item['code'] ?? ''),
                'p_order' => (int) ($item['p_order'] ?? 0),
                'cat_path' => (string) ($item['cat_path'] ?? ''),
                'is_leaf' => (bool) ($item['is_leaf'] ?? false),
                'cls' => !((bool) ($item['is_leaf'] ?? false)),
                'child_count' => (int) ($item['child_count'] ?? 0),
                'step' => $step,
            ];
        }

        return $rows;
    }

    private function normalizeRow(array $row): array
    {
        return [
            'cat_id' => (int) ($row['cat_id'] ?? 0),
            'parent_id' => (int) ($row['parent_id'] ?? 0),
            'cat_name' => (string) ($row['cat_name'] ?? ''),
            'cat_code' => (string) ($row['cat_code'] ?? ''),
            'p_order' => isset($row['p_order']) ? (int) $row['p_order'] : 0,
            'cat_path' => (string) ($row['cat_path'] ?? ''),
            'child_count' => (int) ($row['child_count'] ?? 0),
        ];
    }
}
