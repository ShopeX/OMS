<?php

use app\desktop\lib\annotation\RequirePermission;

class desktop_ctl_api_users extends desktop_ctl_api_base
{
    protected static $objectName = 'desktop_mdl_users';
    private desktop_capability_user_assignments $assignmentsCapability;
    private desktop_capability_user_orgscope $orgScopeCapability;
    private desktop_capability_user_provisioning $provisioningCapability;
    private desktop_capability_user_profile $profileCapability;
    private desktop_capability_user_password $passwordCapability;
    private desktop_capability_user_locking $lockingCapability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->assignmentsCapability = new desktop_capability_user_assignments();
        $this->orgScopeCapability = new desktop_capability_user_orgscope();
        $this->provisioningCapability = new desktop_capability_user_provisioning();
        $this->profileCapability = new desktop_capability_user_profile();
        $this->passwordCapability = new desktop_capability_user_password();
        $this->lockingCapability = new desktop_capability_user_locking();
    }

    #[RequirePermission('users')]
    public function index(): void
    {
        $action = $_GET['action'] ?? 'list';

        $finderActions = static::$finderActions;
        if (in_array($action, $finderActions, true)) {
            $baseFilter = [];
            // account_type 筛选：service=服务账号下拉 / human=执行人选择等场景
            $accountType = (string) ($_GET['account_type'] ?? '');
            if (in_array($accountType, ['human', 'service'], true)) {
                $baseFilter['account_type'] = $accountType;
            }
            $result = $this->finderDispatch([
                'objectName' => 'desktop_mdl_users',
                'finderAlias' => 'vue',
                'base_filter' => $baseFilter,
                'actions'    => [
                    [
                        'label'  => app::get('desktop')->_('新建操作员'),
                        'id'     => 'addnew',
                        'type'   => 'primary',
                    ],
                ],
                'use_buildin_export'  => true,
                'use_buildin_import'  => true,
                'use_import_template' => true,
                'use_buildin_recycle' => true,
            ]);
            echo json_encode($result);
            return;
        }

        $this->fail('Invalid action: ' . $action);
    }

    /**
     * 获取用户详情（供 Vue 编辑/复制表单）
     * GET ?app=desktop&ctl=api_users&act=detail&user_id={id}
     */
    #[RequirePermission('operator_edit')]
    public function detail(): void
    {
        $userId = (int)($this->getRequestInput()['user_id'] ?? $_GET['user_id'] ?? 0);
        if (!$userId) {
            $this->fail('用户ID不能为空');
            return;
        }

        $payload = $this->_buildUserDetailPayload($userId);
        if ($payload === null) {
            $this->fail('用户不存在');
            return;
        }

        $this->succ($payload);
    }

    /**
     * @deprecated 请使用 detail()，保留兼容旧调用
     */
    #[RequirePermission('operator_edit')]
    public function getUserDetail(): void
    {
        $this->detail();
    }

    #[RequirePermission('users')]
    public function getRoles(): void
    {
        $rolesModel = $this->app->model('roles');
        $roles = $rolesModel->getList('role_id,role_name', [], 0, -1);

        $this->succ($roles);
    }

    #[RequirePermission('users')]
    public function getOrgs(): void
    {
        $operationOrgObj = app::get('ome')->model('operation_organization');
        $orgs = $operationOrgObj->getList('*', [], 0, -1);

        $this->succ($orgs);
    }

    /**
     * 动态加载角色子权限面板
     * GET ?app=desktop&ctl=api_users&act=detailGround&role_id=1&role_type=branch&user_id=1&checkedIds=1,2,3
     */
    #[RequirePermission('users')]
    public function detailGround(): void
    {
        $roleId   = (int)($_GET['role_id'] ?? 0);
        $roleType = $_GET['role_type'] ?? 'order'; // order|branch|o2o|dealer
        $userId   = (int)($_GET['user_id'] ?? 0);

        $list = match ($roleType) {
            'branch' => kernel::single('ome_roles')->show_branch_json($userId),
            'o2o'    => kernel::single('ome_roles')->show_o2o_branch_json($userId),
            'dealer' => kernel::single('ome_roles')->show_branch_json($userId), // dealer 用 branch 同套
            default  => kernel::single('ome_roles')->show_group_json($userId),  // order
        };

        $this->succ(['list' => $list]);
    }

    /**
     * 获取门店组织树
     * GET ?app=desktop&ctl=api_users&act=getStoreOrgTree
     */
    #[RequirePermission('users')]
    public function getStoreOrgTree(): void
    {
        if (!app::get('organization')->is_installed()) {
            $this->succ([]);
            return;
        }
        $orgMdl = app::get('organization')->model('organization');
        $allOrgs = $orgMdl->getList('org_id,org_name,parent_id', [], 0, -1);
        $this->succ($allOrgs);
    }

    /**
     * 获取经销商店铺树
     * GET ?app=desktop&ctl=api_users&act=getDealerShopTree
     */
    #[RequirePermission('users')]
    public function getDealerShopTree(): void
    {
        if (!app::get('organization')->is_installed()) {
            $this->succ([]);
            return;
        }
        $cosMdl = app::get('organization')->model('cos');
        $allCos = $cosMdl->getList('cos_id,cos_name,parent_id', [], 0, -1);
        $this->succ($allCos);
    }

    #[RequirePermission('operator_edit')]
    public function updateProfile(): void
    {
        try {
            $input = $this->getRequestInput();
            $userId = (int) ($input['account_id'] ?? $input['user_id'] ?? 0);
            $result = $this->profileCapability->update($userId, $input);
            $this->succ($result);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 200);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    #[RequirePermission('operator_edit')]
    public function updateAssignments(): void
    {
        try {
            $input = $this->getRequestInput();
            $userId = (int) ($input['account_id'] ?? $input['user_id'] ?? 0);
            $result = $this->assignmentsCapability->update($userId, $input);
            $this->succ($result);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 200);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    #[RequirePermission('operator_edit')]
    public function updateOrgScope(): void
    {
        try {
            $input = $this->getRequestInput();
            $userId = (int) ($input['account_id'] ?? $input['user_id'] ?? 0);
            $result = $this->orgScopeCapability->update($userId, $input);
            $this->succ($result);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 200);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    #[RequirePermission('operator_add')]
    public function createUser(): void
    {
        try {
            $result = $this->provisioningCapability->create($this->getRequestInput());
            $this->succ($result);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 200);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    /**
     * 构建用户详情 payload（编辑/复制表单共用）
     */
    private function _buildUserDetailPayload(int $userId): ?array
    {
        $users = $this->app->model('users');
        $sdf = $users->dump($userId, '*', [
            ':account@pam' => ['*'],
        ]);

        if (empty($sdf)) {
            return null;
        }

        $roles = $this->app->model('roles');
        $workgroups = $roles->getList('*');
        $hasrole = $this->app->model('hasrole');
        $workgroup_branch = [];
        $workgroup_o2o_branch = [];
        $workgroup_order = [];
        $workgroup_dealer = [];

        foreach ($workgroups as $group) {
            $rolesData = $hasrole->getList('*', ['user_id' => $userId, 'role_id' => $group['role_id']]);
            $group['checked'] = $rolesData ? 'true' : 'false';

            if ($this->_getShowBranch($group['role_id'])) {
                $workgroup_branch[] = $group;
            } elseif ($this->_getO2oBranch($group['role_id'])) {
                $workgroup_o2o_branch[] = $group;
            } else {
                $workgroup_order[] = $group;
            }
            if ($this->_getDealerWorkgroup($group['role_id'])) {
                $workgroup_dealer[] = $group;
            }
        }

        $oper_per = app::get('ome')->model('operation_ops')
            ->getList('org_id', ['op_id' => $userId], 0, -1);
        $org_id = array_column($oper_per, 'org_id');

        $storeOrgList = ['orgCollectName' => [], 'orgIds' => []];
        if (app::get('organization')->is_installed()) {
            $orgOpsModel = app::get('organization')->model('organization_ops');
            $userOrgs = $orgOpsModel->getUserOrganizations($userId);
            if (!empty($userOrgs)) {
                $orgInfo = app::get('organization')->model('organization')
                    ->getList('org_id,org_name', ['org_id|in' => $userOrgs]);
                foreach ($orgInfo as $org) {
                    $storeOrgList['orgCollectName'][] = $org['org_name'];
                    $storeOrgList['orgIds'][] = $org['org_id'];
                }
            }
        }

        $dealer_shop = ['cosCollectName' => [], 'cosIds' => []];
        if (app::get('organization')->is_installed()) {
            $cosOpsInfo = app::get('organization')->model('cos_ops')->db_dump(['op_id' => $userId]);
            if (!empty($cosOpsInfo['cos_ids'])) {
                $cosList = app::get('organization')->model('cos')
                    ->getList('*', ['cos_id|in' => explode(',', $cosOpsInfo['cos_ids'])]);
                foreach ($cosList as $v) {
                    $dealer_shop['cosCollectName'][] = $v['cos_name'];
                    $dealer_shop['cosIds'][] = $v['cos_id'];
                }
            }
        }

        return [
            'user_id'              => $sdf['user_id'],
            'account_id'           => $sdf['account_id'] ?? $sdf['user_id'],
            'op_no'                => $sdf['op_no'] ?? '',
            'name'                 => $sdf['name'] ?? '',
            'mobile'               => $sdf['mobile'] ?? '',
            'email'                => $sdf['email'] ?? '',
            'memo'                 => $sdf['memo'] ?? '',
            'status'               => (string)($sdf['status'] ?? '1'),
            'mcp_enabled'          => (string)($sdf['mcp_enabled'] ?? '1'),
            'super'                => (string)($sdf['super'] ?? '0'),
            'login_name'           => $sdf['account']['login_name'] ?? '',
            'workgroup_branch'     => $workgroup_branch,
            'workgroup_order'      => $workgroup_order,
            'workgroup_o2o_branch' => $workgroup_o2o_branch,
            'workgroup_dealer'     => $workgroup_dealer,
            'store_org'            => $storeOrgList,
            'dealer_shop'          => $dealer_shop,
            'org_id'               => $org_id,
        ];
    }

    private function _getShowBranch(int $roleId): bool
    {
        $rolesMdl = app::get('desktop')->model('roles');
        $menusMdl = app::get('desktop')->model('menus');
        $result = $rolesMdl->dump($roleId);
        $data = unserialize($result['workground'] ?? '');
        $aPermission = [];
        foreach ((array) $data as $row) {
            $aPermission[] = $row;
        }
        $aPermission = array_unique($aPermission);
        if (!$aPermission) {
            return false;
        }
        foreach ((array) $aPermission as $val) {
            if ($val == 'o2o_store_self' || $val == 'o2o_center') {
                return false;
            }
            $sdf = $menusMdl->dump(['menu_type' => 'permission', 'permission' => $val]);
            $addon = unserialize($sdf['addon'] ?? '');
            if (($addon['show'] ?? '') === 'ome_roles:show_branch') {
                return true;
            }
        }
        return false;
    }

    private function _getO2oBranch(int $roleId): bool
    {
        $rolesMdl = app::get('desktop')->model('roles');
        $menusMdl = app::get('desktop')->model('menus');
        $result = $rolesMdl->dump($roleId);
        $data = unserialize($result['workground'] ?? '');
        $aPermission = [];
        foreach ((array) $data as $row) {
            $aPermission[] = $row;
        }
        $aPermission = array_unique($aPermission);
        if (!$aPermission) {
            return false;
        }
        foreach ((array) $aPermission as $val) {
            if (strpos($val, 'wap_') === false) {
                continue;
            }
            $sdf = $menusMdl->dump(['menu_type' => 'permission', 'permission' => $val]);
            $addon = unserialize($sdf['addon'] ?? '');
            if (($addon['show'] ?? '') === 'ome_roles:show_o2o_branch') {
                return true;
            }
        }
        return false;
    }

    private function _getDealerWorkgroup(int $roleId): bool
    {
        $rolesMdl = app::get('desktop')->model('roles');
        $menusMdl = app::get('desktop')->model('menus');
        $result = $rolesMdl->dump($roleId);
        $data = unserialize($result['workground'] ?? '');
        $aPermission = [];
        foreach ((array) $data as $row) {
            $aPermission[] = $row;
        }
        $aPermission = array_unique($aPermission);
        if (!$aPermission) {
            return false;
        }
        foreach ((array) $aPermission as $val) {
            if (strpos($val, 'dealer_') === false) {
                continue;
            }
            $sdf = $menusMdl->dump(['menu_type' => 'permission', 'permission' => $val]);
            $addon = unserialize($sdf['addon'] ?? '');
            if (($addon['show'] ?? '') === 'ome_roles:show_dealer_workgroup') {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取角色分类元数据（供 Vue 新建/编辑表单下拉选角色）
     */
    #[RequirePermission('users')]
    public function getMeta(): void
    {
        $roles = app::get('desktop')->model('roles');
        $workgroups = $roles->getList('*');

        $workgroup_branch = [];
        $workgroup_o2o_branch = [];
        $workgroup_order = [];
        $workgroup_dealer = [];

        foreach ($workgroups as $workgroup) {
            if ($this->_getShowBranch($workgroup['role_id'])) {
                $workgroup_branch[] = $workgroup;
            } elseif ($this->_getO2oBranch($workgroup['role_id'])) {
                $workgroup_o2o_branch[] = $workgroup;
            } else {
                $workgroup_order[] = $workgroup;
            }
            if ($this->_getDealerWorkgroup($workgroup['role_id'])) {
                $workgroup_dealer[] = $workgroup;
            }
        }

        $this->succ([
            'workgroup_branch'    => $workgroup_branch,
            'workgroup_order'    => $workgroup_order,
            'workgroup_o2o_branch'=> $workgroup_o2o_branch,
            'workgroup_dealer'   => $workgroup_dealer,
            'workgroup'          => $workgroups,
        ]);
    }

    /**
     * 修改用户密码
     * - 不传 user_id 或 user_id 等于当前用户：自改模式（验证自己的旧密码）
     * - 传 user_id 且不等于当前用户：管理员模式（验证超管密码）
     * POST ?app=desktop&ctl=api_users&act=changePassword
     */
    #[RequirePermission('operator_edit')]
    public function changePassword(): void
    {
        try {
            $result = $this->passwordCapability->change($this->getRequestInput(), desktop_auth_user::currentUserId());
            $this->succ($result);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 200);
        } catch (RuntimeException $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    /**
     * 解锁用户
     */
    #[RequirePermission('operator_edit')]
    public function unLock(): void
    {
        try {
            $input = $this->getRequestInput();
            $userId = (int)($input['user_id'] ?? $_GET['user_id'] ?? 0);
            $this->lockingCapability->unlock($userId);
            $this->succ(['message' => '解锁成功']);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    /**
     * 导出操作员CSV模板
     */
    #[RequirePermission('operator_add')]
    public function exportTemplate(): void
    {
        header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment; filename=" . urlencode('操作员导入模板' . date('Ymd') . '.csv'));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        header('Pragma: public');
        echo "\xEF\xBB\xBF"; // BOM for Excel UTF-8
        $model = $this->app->model('users');
        $title = $model->getTemplateColumn(false);
        echo '"' . implode('","', $title) . '"';
    }
}
