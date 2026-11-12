<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class desktop_capability_user_assignments
{
    private object $usersModel;
    private $groundSaver;

    public function __construct(?object $usersModel = null, ?callable $groundSaver = null)
    {
        $this->usersModel = $usersModel ?? app::get('desktop')->model('users');
        $this->groundSaver = $groundSaver ?? static function (array $payload): void {
        };
    }

    public function update(int $userId, array $request): array
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('编辑失败,参数丢失！');
        }

        $roles = array_values(array_filter(array_map('intval', (array) ($request['role'] ?? []))));
        if ($roles === []) {
            throw new InvalidArgumentException('请至少选择一个工作组！');
        }

        $current = method_exists($this->usersModel, 'dump')
            ? $this->usersModel->dump($userId, '*', [':account@pam' => ['*']])
            : [];
        $currentLoginName = '';
        if (is_array($current) && isset($current['account']) && is_array($current['account'])) {
            $currentLoginName = (string) ($current['account']['login_name'] ?? '');
        }

        $payload = [
            'account_id' => $userId,
            'user_id' => $userId,
            'op_no' => (string) ($current['op_no'] ?? ''),
            'name' => (string) ($current['name'] ?? ''),
            'mobile' => (string) ($current['mobile'] ?? ''),
            'email' => (string) ($current['email'] ?? ''),
            'memo' => (string) ($current['memo'] ?? ''),
            'status' => (string) ($current['status'] ?? '1'),
            'mcp_enabled' => (string) ($current['mcp_enabled'] ?? '1'),
            'role' => $roles,
            'roles' => array_map(static fn (int $roleId): array => ['role_id' => $roleId], $roles),
            'confirm_group' => array_values(array_filter(array_map('intval', (array) ($request['confirm_group'] ?? [])))),
            'branch' => array_values(array_filter(array_map('intval', (array) ($request['branch'] ?? [])))),
            'store_id' => array_values(array_filter(array_map('intval', (array) ($request['store_id'] ?? [])))),
            'pam_account' => [
                'account_id' => $userId,
                'login_name' => $currentLoginName,
            ]
        ];

        if (!$this->usersModel->editUser($payload)) {
            throw new RuntimeException('编辑失败');
        }

        $this->usersModel->save_per($payload);
        ($this->groundSaver)($payload);

        return ['success' => true];
    }
}
