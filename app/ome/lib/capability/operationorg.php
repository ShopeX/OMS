<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

class ome_capability_operationorg
{
    private object $model;
    private object $roles;
    private object $database;

    public function __construct(?object $model = null, ?object $roles = null, ?object $database = null)
    {
        $this->model = $model ?? app::get('ome')->model('operation_organization');
        $this->roles = $roles ?? kernel::single('desktop_roles');
        $this->database = $database ?? kernel::database();
    }

    public function list(): array
    {
        return [
            'list' => array_map(fn(array $row): array => $this->normalize($row), $this->model->getList('*')),
        ];
    }

    public function detail(int $orgId): ?array
    {
        if ($orgId < 1) {
            throw new InvalidArgumentException('组织ID无效');
        }

        $row = $this->model->dump(['org_id' => $orgId], '*');
        return is_array($row) ? $this->normalize($row) : null;
    }

    public function create(array $request): array
    {
        $code = trim((string) ($request['code'] ?? ''));
        $name = trim((string) ($request['name'] ?? ''));
        $parentId = (int) ($request['parent_id'] ?? 0);

        if ($code === '') {
            throw new InvalidArgumentException('组织编码不能为空');
        }
        if ($name === '') {
            throw new InvalidArgumentException('组织名称不能为空');
        }

        $exists = $this->model->dump(['code' => addslashes($code)], '*');
        if (!empty($exists)) {
            throw new InvalidArgumentException('编码已存在!不可以重复添加');
        }

        $data = [
            'code' => $code,
            'name' => $name,
            'parent_id' => $parentId,
        ];

        $transaction = $this->database->beginTransaction();
        try {
            $saved = $this->model->save($data);
            if (!$saved) {
                throw new RuntimeException('运营组织保存失败');
            }

            $this->roles->syncPermissionQueue();
            $this->database->commit($transaction);
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }

        return [
            'success' => true,
            'code' => $code,
            'name' => $name,
            'parent_id' => $parentId,
            'org_id' => (int) ($data['org_id'] ?? 0),
        ];
    }

    private function normalize(array $row): array
    {
        return [
            'org_id' => (int) ($row['org_id'] ?? 0),
            'org_name' => (string) ($row['org_name'] ?? $row['name'] ?? ''),
            'org_code' => (string) ($row['org_code'] ?? $row['code'] ?? ''),
            'parent_id' => (int) ($row['parent_id'] ?? 0),
        ];
    }
}
