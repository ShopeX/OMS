<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

class desktop_ctl_api_roles extends desktop_ctl_api_base
{
    protected static $objectName = 'desktop_mdl_roles';
    private desktop_capability_roles $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new desktop_capability_roles();
    }

    /**
     * GET - List/filters/columns (finder dispatch)
     * ?app=desktop&ctl=api_roles&act=index&action=list|columns|filters|detail|...
     */
    #[RequirePermission('users')]
    public function index(): void
    {
        $action = $_GET['action'] ?? 'list';
        $finderActions = static::$finderActions;

        // Only dispatch finder actions through vue context
        if (in_array($action, $finderActions, true)) {
            $actions = [];
            $is_user_roles_add = kernel::single('desktop_user')->has_permission('user_roles_add');
            if ($is_user_roles_add) {
                $actions[] = ['label' => app::get('desktop')->_('新建角色'), 'action' => 'add', 'type' => 'primary'];
            }
            $this->finderDispatch([
                'objectName' => 'desktop_mdl_roles',
                'finderAlias' => 'vue',
                'use_buildin_recycle' => true,
                'actions' => $actions,
            ]);
            return;
        }

        $this->fail('Invalid finder action: ' . $action);
    }

    /**
     * GET - Get permissions tree for add/edit forms
     * ?app=desktop&ctl=api_roles&act=getPermissions
     */
    #[RequirePermission('users')]
    public function getPermissions(): void
    {
        try {
            $input = $this->getRequestInput();
            $roleId = $input['role_id'] ?? null;

            $ownerPermissions = [];

            // If editing existing role, load its permissions
            if ($roleId) {
                $rolesModel = $this->app->model('roles');
                $role = $rolesModel->dump($roleId, 'workground');
                if ($role && !empty($role['workground'])) {
                    $ownerPermissions = unserialize($role['workground']);
                    if (!is_array($ownerPermissions)) {
                        $ownerPermissions = [];
                    }
                }
            }

            // Get full permission tree from service
            $rolesLib = new desktop_roles($this->app);
            $permissions = $rolesLib->get_roles($ownerPermissions);

            $this->succ([
                'workgrounds' => $permissions['workgrounds'] ?? [],
                'widgets' => $permissions['widgets'] ?? [],
                'adminpanels' => $permissions['adminpanels'] ?? [],
                'others' => $permissions['others'] ?? [],
            ]);
        } catch (Throwable $e) {
            error_log('[getPermissions] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->fail('加载权限失败: ' . $e->getMessage(), 500);
        }
    }

    #[RequirePermission('user_roles_add')]
    public function create(): void
    {
        try {
            $result = $this->capability->create($this->getRequestInput());
            $this->succ($result);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    #[RequirePermission('user_roles_edit')]
    public function update(): void
    {
        try {
            $input = $this->getRequestInput();
            $roleId = (int) ($input['role_id'] ?? $_GET['p'][0] ?? 0);
            $result = $this->capability->update($roleId, $input);
            $this->succ($result);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    /**
     * GET - Get role detail for edit dialog
     * Returns dbschema fields + full permissions tree (checked status) in ONE request
     * Called by Vue RoleEditDialog as: ?app=desktop&ctl=api_roles&act=detail&role_id=<id>
     *
     * NOTE: This is different from finderDispatch action=detail (row expand / detailTab).
     *       Use act=detail (controller action) for dialog data; action=detail (finderDispatch)
     *       for row expand and detailTab only.
     */
    #[RequirePermission('user_roles_edit')]
    public function detail(): void
    {
        $input = $this->getRequestInput();
        $roleId = $input['role_id'] ?? null;

        if (!$roleId) {
            $this->fail('role_id is required');
            return;
        }

        $rolesModel = $this->app->model('roles');
        $role = $rolesModel->dump($roleId);

        if (!$role) {
            $this->fail(app::get('desktop')->_('角色不存在'));
            return;
        }

        // Unserialize workground (array of permission strings)
        $ownerPermissions = [];
        if (!empty($role['workground'])) {
            $ownerPermissions = unserialize($role['workground']);
            if (!is_array($ownerPermissions)) {
                $ownerPermissions = [];
            }
        }

        // Unserialize data_authority
        $dataAuthority = [];
        if (!empty($role['data_authority'])) {
            $dataAuthority = unserialize($role['data_authority']);
            if (!is_array($dataAuthority)) {
                $dataAuthority = [];
            }
        }

        // Get full permissions tree with checked status
        $rolesLib = new desktop_roles($this->app);
        $permissions = $rolesLib->get_roles($ownerPermissions);

        $this->succ([
            'role' => [
                'role_id' => (int) $role['role_id'],
                'role_name' => $role['role_name'],
                'disabled' => $role['disabled'] ?? 'false',
            ],
            'workgrounds' => $permissions['workgrounds'] ?? [],
            'widgets' => $permissions['widgets'] ?? [],
            'adminpanels' => $permissions['adminpanels'] ?? [],
            'others' => $permissions['others'] ?? [],
            'data_authority' => $dataAuthority,
        ]);
    }

    /**
     * GET - Data authority options tree for role forms
     * ?app=desktop&ctl=api_roles&act=getDataAuthority&role_id=<optional>
     */
    #[RequirePermission('users')]
    public function getDataAuthority(): void
    {
        $input = $this->getRequestInput();
        $roleId = $input['role_id'] ?? null;

        $options = [
            ['id' => 'customer_sensitive_info', 'label' => app::get('desktop')->_('客户敏感信息')],
            ['id' => 'purchase_price', 'label' => app::get('desktop')->_('采购价')],
            ['id' => 'cost_price', 'label' => app::get('desktop')->_('成本价')],
            ['id' => 'original_price', 'label' => app::get('desktop')->_('原价')],
            ['id' => 'sale_price', 'label' => app::get('desktop')->_('销售价')],
        ];

        $checked = [];
        if ($roleId) {
            $rolesModel = $this->app->model('roles');
            $role = $rolesModel->dump($roleId, 'data_authority');
            if ($role && !empty($role['data_authority'])) {
                $dataAuthority = unserialize($role['data_authority']);
                if (is_array($dataAuthority)) {
                    $checked = array_values(array_filter($dataAuthority));
                }
            }
        }

        foreach ($options as &$opt) {
            $opt['checked'] = in_array($opt['id'], $checked, true);
        }
        unset($opt);

        $this->succ(['options' => $options]);
    }
}
