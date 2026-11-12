<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

final class mcpapi_ctl_v1_role
{
    private object $rolesModel;
    private object $hasRoleModel;
    private object $menusModel;

    public function __construct(
        ?object $rolesModel = null,
        ?object $hasRoleModel = null,
        ?object $menusModel = null
    ) {
        $this->rolesModel = $rolesModel ?? app::get('desktop')->model('roles');
        $this->hasRoleModel = $hasRoleModel ?? app::get('desktop')->model('hasrole');
        $this->menusModel = $menusModel ?? app::get('desktop')->model('menus');
    }

    /**
     * GET role permissions: decoded workground + data_authority + permission name labels.
     * POST /mcp-api/v1/role/show  { role_id: int }
     */
    #[RequirePermission('users')]
    public function show(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $roleId = (int) ($request['role_id'] ?? 0);
            if ($roleId < 1) {
                return mcpapi_http_response::fromJson(422, ['error' => 'VALIDATION_FAILED', 'message' => 'role_id is required']);
            }

            $role = $this->rolesModel->dump($roleId, 'role_id,role_name,workground,data_authority');
            if (!is_array($role) || $role === []) {
                return mcpapi_http_response::fromJson(404, ['error' => 'NOT_FOUND']);
            }

            $workground = $this->decodeSerialized($role['workground'] ?? null);
            $dataAuthority = $this->decodeSerialized($role['data_authority'] ?? null);

            $permissionLabels = $this->permissionLabels($workground);

            $userCount = $this->countUsersByRole($roleId);

            $data = [
                'role_id' => (int) $roleId,
                'role_name' => (string) ($role['role_name'] ?? ''),
                'permissions' => $workground,
                'permission_labels' => $permissionLabels,
                'data_authority' => $dataAuthority,
                'user_count' => $userCount,
            ];

            if (!empty($request['include_tree'])) {
                $rolesLib = new desktop_roles(app::get('desktop'));
                $tree = $rolesLib->get_roles($workground);
                $data['workgrounds'] = $tree['workgrounds'] ?? [];
                $data['widgets'] = $tree['widgets'] ?? [];
                $data['adminpanels'] = $tree['adminpanels'] ?? [];
                $data['others'] = $tree['others'] ?? [];
            }

            return mcpapi_http_response::fromJson(200, ['data' => $data]);
        } catch (Throwable) {
            return mcpapi_http_response::fromJson(500, ['error' => 'INTERNAL_ERROR']);
        }
    }

    /**
     * GET role list: all roles with decoded permission counts.
     * POST /mcp-api/v1/role/list
     */
    #[RequirePermission('users')]
    public function list(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $page = max(1, (int) ($request['page'] ?? 1));
            $pageSize = max(1, min(100, (int) ($request['page_size'] ?? 20)));
            $offset = ($page - 1) * $pageSize;

            $rows = $this->rolesModel->getList(
                'role_id,role_name,workground,data_authority',
                [],
                $offset,
                $pageSize,
                'role_id DESC'
            );

            $total = method_exists($this->rolesModel, 'count')
                ? (int) $this->rolesModel->count([])
                : count($rows);

            $items = array_map(function (array $row): array {
                $permissions = $this->decodeSerialized($row['workground'] ?? null);
                $dataAuthority = $this->decodeSerialized($row['data_authority'] ?? null);
                $roleId = (int) ($row['role_id'] ?? 0);
                return [
                    'role_id' => $roleId,
                    'role_name' => (string) ($row['role_name'] ?? ''),
                    'permission_count' => count($permissions),
                    'data_authority_count' => count($dataAuthority),
                    // Desktop parity: warehouse > o2o > order (default). Used by user-create assignment panels.
                    'assignment_kind' => $this->resolveAssignmentKind($permissions),
                ];
            }, $rows);

            return mcpapi_http_response::fromJson(200, [
                'data' => [
                    'items' => $items,
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total' => $total,
                ],
            ]);
        } catch (Throwable) {
            return mcpapi_http_response::fromJson(500, ['error' => 'INTERNAL_ERROR']);
        }
    }

    #[RequirePermission('user_roles_add')]
    public function create(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $result = (new desktop_capability_roles())->create($request);
            return mcpapi_http_response::fromJson(200, ['data' => $result]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'VALIDATION_FAILED', 'message' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            return mcpapi_http_response::fromJson(500, ['error' => 'INTERNAL_ERROR', 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return mcpapi_http_response::fromJson(500, ['error' => 'INTERNAL_ERROR']);
        }
    }

    #[RequirePermission('user_roles_edit')]
    public function update(mcpapi_auth_context $context, array $request): mcpapi_http_response
    {
        try {
            $roleId = (int) ($request['role_id'] ?? 0);
            if ($roleId < 1) {
                return mcpapi_http_response::fromJson(422, ['error' => 'VALIDATION_FAILED', 'message' => 'role_id is required']);
            }
            $result = (new desktop_capability_roles())->update($roleId, $request);
            return mcpapi_http_response::fromJson(200, ['data' => $result]);
        } catch (InvalidArgumentException $exception) {
            return mcpapi_http_response::fromJson(422, ['error' => 'VALIDATION_FAILED', 'message' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            return mcpapi_http_response::fromJson(500, ['error' => 'INTERNAL_ERROR', 'message' => $exception->getMessage()]);
        } catch (Throwable) {
            return mcpapi_http_response::fromJson(500, ['error' => 'INTERNAL_ERROR']);
        }
    }

    private function decodeSerialized(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }
        if (is_array($value)) {
            return array_values(array_filter($value, static fn($v) => is_string($v) && $v !== ''));
        }
        $decoded = @unserialize((string) $value, ['allowed_classes' => false]);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter($decoded, static fn($v) => is_string($v) && $v !== ''));
    }

    private function permissionLabels(array $permissions): array
    {
        if ($permissions === []) {
            return [];
        }

        $labels = [];

        $permRows = $this->menusModel->getList(
            'permission,menu_title',
            ['menu_type' => 'permission', 'permission|in' => $permissions]
        );
        foreach ($permRows as $row) {
            $labels[$row['permission']] = (string) ($row['menu_title'] ?? $row['permission']);
        }

        $missing = array_filter($permissions, static fn(string $p) => !isset($labels[$p]));
        if ($missing !== []) {
            $widgetRows = $this->menusModel->getList(
                'addon,menu_title',
                ['menu_type' => 'widgets', 'addon|in' => $missing]
            );
            foreach ($widgetRows as $row) {
                $labels[$row['addon']] = (string) ($row['menu_title'] ?? $row['addon']);
            }
        }

        $result = [];
        foreach ($permissions as $perm) {
            $result[] = [
                'permission' => $perm,
                'label' => $labels[$perm] ?? $perm,
            ];
        }
        return $result;
    }

    private function countUsersByRole(int $roleId): int
    {
        $rows = $this->hasRoleModel->getList('user_id', ['role_id' => $roleId], 0, -1);
        return is_array($rows) ? count($rows) : 0;
    }

    /**
     * Classify role for user assignment panels: warehouse | o2o | order.
     *
     * @param list<string> $permissions
     */
    private function resolveAssignmentKind(array $permissions): string
    {
        if ($permissions === []) {
            return 'order';
        }
        if ($this->permissionsHaveShowAddon($permissions, 'ome_roles:show_branch', static function (string $permission): bool {
            return $permission !== 'o2o_store_self' && $permission !== 'o2o_center';
        })) {
            return 'warehouse';
        }
        if ($this->permissionsHaveShowAddon($permissions, 'ome_roles:show_o2o_branch', static function (string $permission): bool {
            return str_starts_with($permission, 'wap_');
        })) {
            return 'o2o';
        }
        return 'order';
    }

    /**
     * @param list<string> $permissions
     * @param callable(string): bool $permissionFilter
     */
    private function permissionsHaveShowAddon(array $permissions, string $showAddon, callable $permissionFilter): bool
    {
        foreach ($permissions as $permission) {
            if (!$permissionFilter($permission)) {
                continue;
            }
            $sdf = $this->menusModel->dump(['menu_type' => 'permission', 'permission' => $permission]);
            if (!is_array($sdf) || $sdf === []) {
                continue;
            }
            $addon = @unserialize((string) ($sdf['addon'] ?? ''), ['allowed_classes' => false]);
            if (is_array($addon) && ($addon['show'] ?? '') === $showAddon) {
                return true;
            }
        }
        return false;
    }
}
