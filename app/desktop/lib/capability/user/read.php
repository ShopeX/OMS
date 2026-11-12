<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class desktop_capability_user_read
{
    private object $usersModel;
    private object $rolesModel;
    private object $hasRoleModel;
    private object $operationOpsModel;
    private ?object $operationOrgModel;
    private ?object $organizationOpsModel;
    private ?object $organizationModel;
    private ?object $cosOpsModel;
    private object $groupOpsModel;
    private object $branchOpsModel;

    public function __construct(
        ?object $usersModel = null,
        ?object $rolesModel = null,
        ?object $hasRoleModel = null,
        ?object $operationOpsModel = null,
        ?object $operationOrgModel = null,
        ?object $organizationModel = null,
        ?object $organizationOpsModel = null,
        ?object $cosOpsModel = null,
        ?object $groupOpsModel = null,
        ?object $branchOpsModel = null
    ) {
        $this->usersModel = $usersModel ?? app::get('desktop')->model('users');
        $this->rolesModel = $rolesModel ?? app::get('desktop')->model('roles');
        $this->hasRoleModel = $hasRoleModel ?? app::get('desktop')->model('hasrole');
        $this->operationOpsModel = $operationOpsModel ?? app::get('ome')->model('operation_ops');
        $this->operationOrgModel = $operationOrgModel ?? app::get('ome')->model('operation_organization');
        $this->organizationOpsModel = $organizationOpsModel;
        $this->organizationModel = $organizationModel;
        $this->cosOpsModel = $cosOpsModel;
        $this->groupOpsModel = $groupOpsModel ?? app::get('ome')->model('group_ops');
        $this->branchOpsModel = $branchOpsModel ?? app::get('ome')->model('branch_ops');

        if (app::get('organization')->is_installed()) {
            $this->organizationOpsModel ??= app::get('organization')->model('organization_ops');
            $this->organizationModel ??= app::get('organization')->model('organization');
            $this->cosOpsModel ??= app::get('organization')->model('cos_ops');
        }
    }

    public function list(array $request, bool $isSuper, array $allowedOrganizationIds): array
    {
        $this->validateListRequest($request);
        $page = max(1, (int) ($request['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($request['page_size'] ?? 20)));
        $filters = $this->normalizeFilters($request['filters'] ?? []);
        $visibleUserIds = $this->visibleUserIds($filters['org_id'] ?? null, $isSuper, $allowedOrganizationIds);

        if ($visibleUserIds === []) {
            return ['items' => [], 'page' => $page, 'page_size' => $pageSize, 'total' => 0];
        }

        $modelFilter = $filters;
        $modelFilter['user_id'] = $visibleUserIds;
        unset($modelFilter['org_id']);
        $offset = ($page - 1) * $pageSize;
        $rows = $this->usersModel->getList(
            'user_id,name,mobile,email,op_no,memo,status,mcp_enabled,super',
            $modelFilter,
            $offset,
            $pageSize,
            'user_id ' . (strtolower((string) ($request['order_type'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC')
        );
        $total = method_exists($this->usersModel, 'count') ? (int) $this->usersModel->count($modelFilter) : count($rows);

        return [
            'items' => array_map(fn(array $row): array => $this->normalizeListRow($row), $rows),
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ];
    }

    public function show(int $userId, bool $isSuper, array $allowedOrganizationIds): ?array
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('user_id 必须是正整数');
        }

        $row = $this->usersModel->dump($userId, '*', [':account@pam' => ['*']]);
        if (!is_array($row) || $row === [] || !$this->canAccessUser($userId, $isSuper, $allowedOrganizationIds)) {
            return null;
        }

        $roles = $this->roles($userId);
        $operationOrgIds = $this->operationOrganizationIds($userId);
        $storeOrgs = $this->storeOrganizations($userId);
        $dealerShopIds = $this->dealerShopIds($userId);
        $confirmGroupIds = $this->confirmGroupIds($userId);
        $branchIds = $this->branchIds($userId);
        $safe = array_merge($this->normalizeProfile($row), [
            'roles' => $roles,
            'role_ids' => array_column($roles, 'role_id'),
            'operation_orgs' => $this->operationOrganizations($operationOrgIds),
            'operation_org_ids' => $operationOrgIds,
            'store_orgs' => $storeOrgs,
            'store_org_ids' => array_column($storeOrgs, 'org_id'),
            'dealer_shops' => array_map(static fn(int $id): array => ['shop_id' => $id], $dealerShopIds),
            'dealer_shop_ids' => $dealerShopIds,
            'confirm_group' => $confirmGroupIds,
            'branch' => $branchIds,
        ]);
        $safe['state_fingerprint'] = hash('sha256', json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $safe;
    }

    private function confirmGroupIds(int $userId): array
    {
        $rows = $this->groupOpsModel->getList('group_id', ['op_id' => $userId], 0, -1);
        return array_values(array_unique(array_filter(array_map('intval', array_column((array) $rows, 'group_id')), static fn(int $id): bool => $id > 0)));
    }

    private function branchIds(int $userId): array
    {
        $rows = $this->branchOpsModel->getList('branch_id', ['op_id' => $userId], 0, -1);
        return array_values(array_unique(array_filter(array_map('intval', array_column((array) $rows, 'branch_id')), static fn(int $id): bool => $id > 0)));
    }

    private function normalizeFilters($filters): array
    {
        if (!is_array($filters)) {
            throw new InvalidArgumentException('filters 必须是对象');
        }
        $allowed = ['login_name', 'name', 'status', 'mcp_enabled', 'op_no', 'org_id'];
        foreach (array_keys($filters) as $key) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('不支持的筛选字段');
            }
        }
        if (isset($filters['org_id'])) {
            if (!is_array($filters['org_id'])) {
                throw new InvalidArgumentException('filters.org_id 必须是数组');
            }
            $filters['org_id'] = $this->positiveIds($filters['org_id']);
        }
        foreach (['status', 'mcp_enabled'] as $field) {
            if (isset($filters[$field]) && !in_array((string) $filters[$field], ['0', '1'], true)) {
                throw new InvalidArgumentException("filters.{$field} 必须是 0 或 1");
            }
        }
        return $filters;
    }

    private function validateListRequest(array $request): void
    {
        foreach (array_keys($request) as $key) {
            if (!in_array($key, ['page', 'page_size', 'filters', 'order_by', 'order_type'], true)) {
                throw new InvalidArgumentException('不支持的请求字段');
            }
        }
        if (isset($request['order_by']) && (string) $request['order_by'] !== 'user_id') {
            throw new InvalidArgumentException('不支持的排序字段');
        }
        if (isset($request['order_type']) && !in_array(strtolower((string) $request['order_type']), ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('order_type 必须是 asc 或 desc');
        }
    }

    private function visibleUserIds(?array $requestedOrganizationIds, bool $isSuper, array $allowedOrganizationIds): array
    {
        if (!$isSuper && $allowedOrganizationIds === []) {
            return [];
        }
        $organizationIds = $requestedOrganizationIds ?? $allowedOrganizationIds;
        if (!$isSuper) {
            $organizationIds = array_values(array_intersect($organizationIds, $allowedOrganizationIds));
        }
        if ($organizationIds === [] && (!$isSuper || $requestedOrganizationIds !== null)) {
            return [];
        }
        if ($isSuper && $requestedOrganizationIds === null) {
            $rows = $this->usersModel->getList('user_id', [], 0, -1, 'user_id DESC');
            return array_values(array_unique(array_map('intval', array_column($rows, 'user_id'))));
        }
        $rows = $this->operationOpsModel->getList('op_id', ['org_id|in' => $organizationIds], 0, -1);
        return array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'op_id')))));
    }

    private function canAccessUser(int $userId, bool $isSuper, array $allowedOrganizationIds): bool
    {
        if ($isSuper) {
            return true;
        }
        if ($allowedOrganizationIds === []) {
            return false;
        }
        $rows = $this->operationOpsModel->getList('org_id', ['op_id' => $userId], 0, -1);
        $organizationIds = array_map('intval', array_column($rows, 'org_id'));
        return (bool) array_intersect($organizationIds, array_map('intval', $allowedOrganizationIds));
    }

    private function normalizeListRow(array $row): array
    {
        $userId = (int) ($row['user_id'] ?? 0);
        $roles = $this->roles($userId);
        $operationOrgIds = $this->operationOrganizationIds($userId);
        return array_merge($this->normalizeProfile($row), [
            'role_ids' => array_column($roles, 'role_id'),
            'role_names' => array_column($roles, 'role_name'),
            'operation_org_ids' => $operationOrgIds,
            'store_org_ids' => array_column($this->storeOrganizations($userId), 'org_id'),
            'dealer_shop_ids' => $this->dealerShopIds($userId),
        ]);
    }

    private function normalizeProfile(array $row): array
    {
        return [
            'user_id' => (int) ($row['user_id'] ?? 0),
            'login_name' => (string) ($row['account']['login_name'] ?? $row['login_name'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'mobile' => (string) ($row['mobile'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'op_no' => (string) ($row['op_no'] ?? ''),
            'memo' => (string) ($row['memo'] ?? ''),
            'status' => (string) ($row['status'] ?? '0'),
            'mcp_enabled' => (string) ($row['mcp_enabled'] ?? '0'),
            'is_super' => (bool) ($row['super'] ?? false),
        ];
    }

    private function roles(int $userId): array
    {
        $relations = (array) $this->hasRoleModel->getList('role_id', ['user_id' => $userId], 0, -1);
        $roleIds = array_values(array_unique(array_filter(array_map('intval', array_column($relations, 'role_id')))));
        if ($roleIds === []) {
            return [];
        }
        $roles = (array) $this->rolesModel->getList('role_id,role_name', ['role_id|in' => $roleIds], 0, -1);
        return array_map(static fn(array $role): array => [
            'role_id' => (int) ($role['role_id'] ?? 0),
            'role_name' => (string) ($role['role_name'] ?? ''),
        ], $roles);
    }

    private function operationOrganizationIds(int $userId): array
    {
        $rows = (array) $this->operationOpsModel->getList('org_id', ['op_id' => $userId], 0, -1);
        return array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'org_id')))));
    }

    private function operationOrganizations(array $ids): array
    {
        if ($ids === [] || $this->operationOrgModel === null) {
            return [];
        }
        $rows = (array) $this->operationOrgModel->getList('org_id,name', ['org_id|in' => $ids], 0, -1);
        return array_map(static fn(array $row): array => [
            'org_id' => (int) ($row['org_id'] ?? 0),
            'name' => (string) ($row['name'] ?? $row['org_name'] ?? ''),
        ], $rows);
    }

    private function storeOrganizations(int $userId): array
    {
        if ($this->organizationOpsModel === null || !method_exists($this->organizationOpsModel, 'getUserOrganizations')) {
            return [];
        }
        $ids = $this->positiveIds((array) $this->organizationOpsModel->getUserOrganizations($userId));
        if ($ids === [] || $this->organizationModel === null) {
            return array_map(static fn(int $id): array => ['org_id' => $id, 'name' => ''], $ids);
        }
        $rows = (array) $this->organizationModel->getList('org_id,org_name', ['org_id|in' => $ids], 0, -1);
        return array_map(static fn(array $row): array => [
            'org_id' => (int) ($row['org_id'] ?? 0),
            'name' => (string) ($row['org_name'] ?? $row['name'] ?? ''),
        ], $rows);
    }

    private function dealerShopIds(int $userId): array
    {
        if ($this->cosOpsModel === null || !method_exists($this->cosOpsModel, 'db_dump')) {
            return [];
        }
        $row = (array) $this->cosOpsModel->db_dump(['op_id' => $userId], 'cos_ids');
        return $this->positiveIds(explode(',', (string) ($row['cos_ids'] ?? '')));
    }

    private function positiveIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    }
}
