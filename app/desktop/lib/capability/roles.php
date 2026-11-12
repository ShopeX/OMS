<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class desktop_capability_roles
{
    private object $model;
    private object $database;

    public function __construct(?object $model = null, ?object $database = null)
    {
        $this->model = $model ?? app::get('desktop')->model('roles');
        $this->database = $database ?? kernel::database();
    }

    public function create(array $request): array
    {
        $payload = $this->normalizeRequest($request);
        $this->validateRequest($payload, null);

        $transaction = $this->database->beginTransaction();
        try {
            if (!$this->model->save($payload)) {
                throw new RuntimeException('保存失败');
            }

            $this->database->commit($transaction);
            return ['success' => true];
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }

    public function update(int $roleId, array $request): array
    {
        $payload = $this->normalizeRequest($request, $roleId);
        $this->validateRequest($payload, $roleId);

        $transaction = $this->database->beginTransaction();
        try {
            if (!$this->model->save($payload)) {
                throw new RuntimeException('保存失败');
            }

            $this->database->commit($transaction);
            return ['success' => true];
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw $exception;
        }
    }

    private function normalizeRequest(array $request, ?int $roleId = null): array
    {
        $payload = $request;
        $payload['role_name'] = trim((string) ($request['role_name'] ?? ''));
        $payload['workground'] = array_values(array_filter((array) ($request['workground'] ?? []), static function ($value): bool {
            return is_string($value) && $value !== '';
        }));
        $payload['data_authority'] = array_values(array_filter((array) ($request['data_authority'] ?? []), static function ($value): bool {
            return is_string($value) && $value !== '';
        }));

        if ($roleId !== null) {
            $payload['role_id'] = $roleId;
        } elseif (isset($request['role_id'])) {
            $payload['role_id'] = (int) $request['role_id'];
        }

        return $payload;
    }

    private function validateRequest(array $payload, ?int $roleId): void
    {
        if ($payload['role_name'] === '') {
            throw new InvalidArgumentException('工作组名称不能为空');
        }

        if ($payload['workground'] === []) {
            throw new InvalidArgumentException('请至少选择一个权限');
        }

        $duplicateRoleId = $this->model->check_gname($payload['role_name']);
        if ($duplicateRoleId !== false && (int) $duplicateRoleId !== (int) ($roleId ?? 0)) {
            throw new InvalidArgumentException('该工作组名称已存在');
        }
    }
}
