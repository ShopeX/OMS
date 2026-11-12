<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class desktop_capability_user_mutation
{
    private object $usersModel;
    private object $database;
    private $runHooks;
    private $saveStoreOrganizations;
    private $saveAssignments;
    private $saveOperationOrganizations;
    private $saveDealerScope;
    private $writeAudit;
    private $syncAccount;

    public function __construct(
        ?object $usersModel = null,
        ?object $database = null,
        ?callable $runHooks = null,
        ?callable $saveStoreOrganizations = null,
        ?callable $saveAssignments = null,
        ?callable $saveOperationOrganizations = null,
        ?callable $writeAudit = null,
        ?callable $syncAccount = null
    ) {
        $this->usersModel = $usersModel ?? app::get('desktop')->model('users');
        $this->database = $database ?? kernel::database();
        $this->runHooks = $runHooks ?? static function (array $payload): void {
            foreach (kernel::servicelist('desktop_useradd') as $service) {
                if ($service instanceof desktop_interface_useradd) {
                    $service->useradd($payload);
                }
            }
        };
        $this->saveStoreOrganizations = $saveStoreOrganizations ?? static function (int $userId, array $orgIds): bool {
            if (!app::get('organization')->is_installed()) {
                return true;
            }
            return (bool) app::get('organization')->model('organization_ops')->saveUserOrganizations($userId, $orgIds);
        };
        $this->saveAssignments = $saveAssignments ?? static function (array $payload): bool {
            $model = app::get('desktop')->model('users');
            if (method_exists($model, 'save_per')) {
                $model->save_per($payload);
            }
            return true;
        };
        $this->saveOperationOrganizations = $saveOperationOrganizations ?? static function (array $payload): bool {
            $model = app::get('ome')->model('operation_ops');
            $model->delete(['op_id' => (int) $payload['user_id']]);
            foreach ($payload['org_id'] as $orgId) {
                $row = ['op_id' => (int) $payload['user_id'], 'org_id' => (int) $orgId];
                $model->save($row);
            }
            return true;
        };
        $this->saveDealerScope = $saveDealerScope ?? static function (array $payload): bool {
            return (bool) kernel::single('organization_cos')->save_operation_permission($payload);
        };
        $this->writeAudit = $writeAudit ?? static function (array $payload): void {
            app::get('desktop')->model('user_logs')->addUser($payload);
        };
        $this->syncAccount = $syncAccount ?? static function (array $payload, string $mode): array {
            return kernel::single('desktop_user_auth')->sync_account($payload, $mode);
        };
    }

    public function create(array $request, array $authority): array
    {
        $payload = $this->normalizeCreate($request);
        $this->assertCreateAuthority($payload, $authority);

        $transaction = $this->database->beginTransaction();
        try {
            $this->saveUser($payload, true);
            $this->saveRelations($payload);
            $this->database->commit($transaction);
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw new RuntimeException('用户写入失败', 0, $exception);
        }

        return $this->completeExternalEffects($payload, 'add');
    }

    public function update(int $userId, array $request, array $authority): array
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('用户ID不能为空');
        }
        $current = $this->currentUser($userId);
        $this->assertUpdateAuthority($userId, $current, $request, $authority);
        $payload = $this->normalizeUpdate($userId, $current, $request);

        $transaction = $this->database->beginTransaction();
        try {
            $this->saveUser($payload, false);
            $this->saveRelations($payload);
            $this->database->commit($transaction);
        } catch (Throwable $exception) {
            $this->database->rollBack($transaction);
            throw new RuntimeException('用户写入失败', 0, $exception);
        }

        return $this->completeExternalEffects($payload, 'update');
    }

    private function normalizeCreate(array $request): array
    {
        $password = (string) ($request['pam_account']['login_password'] ?? '');
        $this->validatePlaintextPassword($password);
        $roles = $this->normalizeIds($request['role'] ?? []);
        if ($roles === []) {
            throw new InvalidArgumentException('请至少选择一个工作组');
        }

        $payload = $request;
        $payload['super'] = 0;
        $payload['status'] = array_key_exists('status', $request) ? (string) $request['status'] : '1';
        $payload['mcp_enabled'] = array_key_exists('mcp_enabled', $request) ? (string) $request['mcp_enabled'] : '1';
        $payload['name'] = trim((string) ($request['name'] ?? ''));
        $payload['mobile'] = trim((string) ($request['mobile'] ?? ''));
        $payload['email'] = trim((string) ($request['email'] ?? ''));
        $payload['op_no'] = strtoupper(trim((string) ($request['op_no'] ?? '')));
        $payload['role'] = $roles;
        $payload['roles'] = array_map(static fn(int $roleId): array => ['role_id' => $roleId], $roles);
        $payload['pam_account'] = [
            'login_name' => trim((string) ($request['pam_account']['login_name'] ?? '')),
            'login_password' => pam_encrypt::get_encrypted_password($password, pam_account::get_account_type('desktop')),
            'account_type' => pam_account::get_account_type('desktop'),
        ];
        $this->validateProfile($payload);
        return $payload;
    }

    private function normalizeUpdate(int $userId, array $current, array $request): array
    {
        $currentRoles = array_key_exists('role', $current)
            ? $this->normalizeIds($current['role'])
            : $this->normalizeIds(array_column(
                (array) app::get('desktop')->model('hasrole')->getList('role_id', ['user_id' => $userId], 0, -1),
                'role_id'
            ));
        $roles = array_key_exists('role', $request) ? $this->normalizeIds($request['role']) : $currentRoles;
        if ($roles === []) {
            throw new InvalidArgumentException('请至少选择一个工作组');
        }
        $payload = [
            'user_id' => $userId,
            'account_id' => $userId,
            'name' => array_key_exists('name', $request) ? trim((string) $request['name']) : (string) ($current['name'] ?? ''),
            'mobile' => array_key_exists('mobile', $request) ? trim((string) $request['mobile']) : (string) ($current['mobile'] ?? ''),
            'email' => array_key_exists('email', $request) ? trim((string) $request['email']) : (string) ($current['email'] ?? ''),
            'memo' => array_key_exists('memo', $request) ? (string) $request['memo'] : (string) ($current['memo'] ?? ''),
            'op_no' => array_key_exists('op_no', $request) ? strtoupper(trim((string) $request['op_no'])) : (string) ($current['op_no'] ?? ''),
            'status' => array_key_exists('status', $request) ? (string) $request['status'] : (string) ($current['status'] ?? '1'),
            'mcp_enabled' => array_key_exists('mcp_enabled', $request) ? (string) $request['mcp_enabled'] : (string) ($current['mcp_enabled'] ?? '1'),
            'role' => $roles,
            'roles' => array_map(static fn(int $roleId): array => ['role_id' => $roleId], $roles),
            'pam_account' => ['account_id' => $userId, 'login_name' => (string) ($current['account']['login_name'] ?? '')],
        ];
        foreach (['org_id', 'store_id', 'branch', 'confirm_group'] as $field) {
            if (array_key_exists($field, $request)) {
                $payload[$field] = $this->normalizeIds($request[$field]);
            } elseif (array_key_exists($field, $current)) {
                $payload[$field] = $this->normalizeIds($current[$field]);
            }
        }
        if (array_key_exists('store_org_conf', $request)) {
            $payload['store_org_conf'] = ['orgIds' => $this->normalizeIds($request['store_org_conf']['orgIds'] ?? [])];
        }
        if (array_key_exists('dealer_shop_conf', $request)) {
            $payload['dealer_shop_conf'] = ['cosIds' => $this->normalizeIds($request['dealer_shop_conf']['cosIds'] ?? [])];
        }
        $this->validateProfile($payload);
        return $payload;
    }

    private function saveUser(array &$payload, bool $create): void
    {
        if ($create) {
            $this->usersModel->save($payload);
        } else {
            $this->usersModel->editUser($payload);
        }
    }

    private function saveRelations(array &$payload): void
    {
        if (!(($this->saveAssignments)($payload))) {
            throw new RuntimeException('角色权限保存失败');
        }
        if (array_key_exists('store_org_conf', $payload)) {
            $orgIds = $this->normalizeIds($payload['store_org_conf']['orgIds'] ?? []);
            if (!(($this->saveStoreOrganizations)((int) $payload['user_id'], $orgIds))) {
                throw new RuntimeException('门店组织权限保存失败');
            }
        }
        if (array_key_exists('org_id', $payload)) {
            if (!(($this->saveOperationOrganizations)(['user_id' => (int) $payload['user_id'], 'org_id' => $payload['org_id']]))) {
                throw new RuntimeException('运营组织权限保存失败');
            }
        }
        if (array_key_exists('dealer_shop_conf', $payload)) {
            if (!(($this->saveDealerScope)(['user_id' => (int) $payload['user_id'], 'dealer_shop_conf' => $payload['dealer_shop_conf']]))) {
                throw new RuntimeException('经销商权限保存失败');
            }
        }
    }

    private function completeExternalEffects(array $payload, string $mode): array
    {
        $hookStatus = 'not_requested';
        if ($mode === 'add') {
            try {
                // Hooks are extension points with no database-only contract; run after commit.
                ($this->runHooks)($this->redactSensitive($payload));
                $hookStatus = 'success';
            } catch (Throwable) {
                $hookStatus = 'failed';
            }
        }

        $syncStatus = 'not_requested';
        $syncMessage = '';
        try {
            // IDAAS add requires the derived credential; keep this handoff local and never return it.
            $syncPayload = [
                'account_id' => (int) ($payload['pam_account']['account_id'] ?? $payload['user_id'] ?? 0),
                'login_name' => (string) ($payload['pam_account']['login_name'] ?? ''),
            ];
            if ($mode === 'add') {
                $syncPayload['login_password'] = (string) ($payload['pam_account']['login_password'] ?? '');
            }
            $syncResult = ($this->syncAccount)($syncPayload, $mode);
            unset($syncPayload['login_password']);
            $synced = is_array($syncResult) && ($syncResult[0] ?? false) !== false;
            $syncMessage = is_array($syncResult) ? (string) ($syncResult[1] ?? '') : '';
            $syncStatus = $synced === false ? 'failed' : 'success';
            if ($syncStatus === 'failed') {
                $syncMessage = '外部账号同步失败';
            } else {
                $syncMessage = '';
            }
        } catch (Throwable) {
            $syncStatus = 'failed';
            $syncMessage = '外部账号同步失败';
        }

        $auditStatus = 'success';
        try {
            ($this->writeAudit)($this->safeAuditPayload($payload, $mode));
        } catch (Throwable) {
            $auditStatus = 'failed';
        }

        $result = [
            'status' => 'committed',
            'user_id' => (int) ($payload['user_id'] ?? $payload['pam_account']['account_id'] ?? 0),
            'hook_status' => $hookStatus,
            'external_sync' => $syncStatus,
            'audit_log' => $auditStatus,
        ];
        if ($syncMessage !== '') {
            $result['external_message'] = $syncMessage;
        }
        return $result;
    }

    private function currentUser(int $userId): array
    {
        $current = method_exists($this->usersModel, 'dump')
            ? (array) $this->usersModel->dump($userId, '*', [':account@pam' => ['*']])
            : [];
        if (!is_array($current) || $current === []) {
            throw new InvalidArgumentException('用户不存在');
        }
        return $current;
    }

    private function assertCreateAuthority(array $payload, array $authority): void
    {
        if ((int) ($payload['super'] ?? 0) === 1) {
            throw new RuntimeException('不允许创建超级管理员');
        }
        $this->assertScope($payload['org_id'] ?? [], $authority);
    }

    private function assertUpdateAuthority(int $userId, array $current, array $request, array $authority): void
    {
        if ((int) ($current['super'] ?? 0) === 1 || $userId === 1) {
            throw new RuntimeException('不允许修改受保护用户');
        }
        if ($userId === (int) ($authority['subject_id'] ?? 0)) {
            throw new RuntimeException('不允许修改当前登录账号');
        }
        $this->assertScope($request['org_id'] ?? ($current['org_id'] ?? []), $authority);
    }

    private function assertScope(array $orgIds, array $authority): void
    {
        if (($authority['is_super'] ?? false) === true) {
            return;
        }
        $allowed = $this->normalizeIds($authority['scope_org_ids'] ?? []);
        if (array_diff($this->normalizeIds($orgIds), $allowed) !== []) {
            throw new RuntimeException('超出数据权限范围');
        }
    }

    private function validateProfile(array $payload): void
    {
        if ((string) ($payload['pam_account']['login_name'] ?? '') === '' || (string) ($payload['name'] ?? '') === '') {
            throw new InvalidArgumentException('必填项不能为空');
        }
        $mobile = (string) ($payload['mobile'] ?? '');
        if ($mobile !== '' && (strlen($mobile) !== 11 || !ctype_digit($mobile))) {
            throw new InvalidArgumentException('手机号码格式不正确');
        }
    }

    private function validatePlaintextPassword(string $password): void
    {
        if ($password === '') {
            throw new InvalidArgumentException('密码不能为空');
        }
        if (preg_match('/^(\$2[aby]\$|\$argon2|enc:|hash:)/i', $password)) {
            throw new InvalidArgumentException('密码必须是明文输入');
        }
    }

    private function normalizeIds($values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $values), static fn(int $value): bool => $value > 0)));
    }

    private function safeAuditPayload(array $payload, string $mode): array
    {
        $safe = $this->redactSensitive($payload);
        $safe['operation'] = $mode;
        return $safe;
    }

    private function redactSensitive(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/(?:password|token|secret|authorization|session|credential|private[_-]?key)/i', $key)) {
            return null;
        }
        if (!is_array($value)) {
            return $value;
        }
        $result = [];
        foreach ($value as $childKey => $childValue) {
            $childKey = (string) $childKey;
            if (preg_match('/(?:password|token|secret|authorization|session|credential|private[_-]?key)/i', $childKey)) {
                continue;
            }
            $result[$childKey] = $this->redactSensitive($childValue, $childKey);
        }
        return $result;
    }
}
