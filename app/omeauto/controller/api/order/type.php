<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use app\desktop\lib\annotation\RequirePermission;

class omeauto_ctl_api_order_type extends desktop_ctl_api_base
{
    protected static $objectName = 'omeauto_mdl_order_type';

    #[RequirePermission('order_type')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName' => 'omeauto_mdl_order_type',
            'finderAlias' => 'order_type_view_v1',
            'actions' => [
                ['label' => '新建', 'id' => 'add', 'type' => 'primary'],
            ],
            'base_filter' => ['group_type' => 'order'],
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => true,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'use_view_tab' => true,
        ]);
    }



    /**
     * Set status (enable/disable)
     */
    #[RequirePermission('order_type')]
    public function setStatus(): void
    {
        $tid = (int) ($_GET['p'][0] ?? 0);
        $status = $_GET['p'][1] ?? 'true';

        if ($tid <= 0) {
            $this->fail('tid is required');
            return;
        }

        if ($status == 'true') {
            $disabled = 'false';
        } else {
            $disabled = 'true';
        }

        kernel::database()->query("update sdb_omeauto_order_type set disabled='{$disabled}' where tid={$tid}");

        $this->succ(['message' => '状态已更新']);
    }

    /**
     * Save order type (create/update)
     */
    #[RequirePermission('order_type')]
    public function save(): void
    {
        $input = $this->getRequestInput();

        $sdf = [];
        $sdf['name'] = $input['name'] ?? '';
        $sdf['memo'] = $input['memo'] ?? '';
        $sdf['weight'] = $input['weight'] ? $input['weight'] : 0;
        $sdf['config'] = ($input['roles'] ?? '') !== '' ? explode('|||', $input['roles']) : [];
        $sdf['group_type'] = $input['group_type'] ? $input['group_type'] : 'order';
        $sdf['org_id'] = $input['org_id'] ?? null;

        $tid = isset($input['tid']) ? intval($input['tid']) : 0;
        if (!empty($tid) && $tid > 0) {
            $sdf['tid'] = $tid;
        }

        $orderTypeObj = $this->app->model('order_type');
        $orderTypeObj->save($sdf);

        $this->succ(['message' => '保存成功', 'tid' => $sdf['tid'] ?? $tid]);
    }

    /**
     * Get organizations for dropdown
     */
    #[RequirePermission('order_type')]
    public function getOrganizations(): void
    {
        $operationOrgObj = app::get('ome')->model('operation_organization');
        $orgs = $operationOrgObj->getList('*', [], 0, -1);
        $this->succ(['orgs' => $orgs]);
    }

    /**
     * Get autoconfirm rules for dropdown
     */
    #[RequirePermission('order_type')]
    public function getAutoconfirmRules(): void
    {
        $rows = $this->app->model('autoconfirm')->getList('oid,name,disabled');
        $this->succ(['rules' => $rows]);
    }

    /**
     * Get autodispatch rules for dropdown
     */
    #[RequirePermission('order_type')]
    public function getAutodispatchRules(): void
    {
        $rows = $this->app->model('autodispatch')->getList('oid,name,disabled');
        $this->succ(['rules' => $rows]);
    }

    /**
     * Get branches for dropdown
     */
    #[RequirePermission('order_type')]
    public function getBranches(): void
    {
        $branchModel = $this->app->model('branch');
        $rows = $branchModel->getList('branch_id,name,disabled', ['is_deliv_branch' => 'true']);

        // Add O2O store option
        $rows[] = [
            'branch_id' => -1,
            'name' => '参加O2O的门店',
            'disabled' => 'false'
        ];

        $this->succ(['branches' => $rows]);
    }

    /**
     * Delete order type
     */
    #[RequirePermission('order_type')]
    public function delete(): void
    {
        $input = $this->getRequestInput();
        $tids = $input['tid'] ?? [];

        if (empty($tids)) {
            $this->fail('tid is required');
            return;
        }

        $orderTypeObj = $this->app->model('order_type');

        if (is_array($tids)) {
            foreach ($tids as $tid) {
                $orderTypeObj->delete(['tid' => $tid]);
            }
        } else {
            $orderTypeObj->delete(['tid' => $tids]);
        }

        $this->succ(['message' => '删除成功']);
    }

    /**
     * Get order type detail (for Vue edit dialog)
     * Vue frontend calls act=detail&tid=<id>
     */
    #[RequirePermission('order_type')]
    public function detail(): void
    {
        $tid = isset($_GET['tid']) ? intval($_GET['tid']) : 0;

        if ($tid <= 0) {
            $this->fail('tid is required');
            return;
        }

        $orderTypeObj = $this->app->model('order_type');
        $row = $orderTypeObj->dump(['tid' => $tid], '*');

        if (empty($row)) {
            $this->fail('记录不存在');
            return;
        }

        $this->succ(['info' => $row]);
    }

    /**
     * Create role for order label rule
     * Wraps legacy omeauto_auto_type->createRole() which returns JSON
     */
    #[RequirePermission('order_type')]
    public function createRole(): void
    {
        $result = kernel::single('omeauto_auto_type')->createRole();
        echo $result;
        exit;
    }

    /**
     * Get condition UI schema (replaces legacy getUI which returns HTML)
     * vue_mode=1 → getUISchema() → JSON schema
     */
    #[RequirePermission('order_type')]
    public function getConditionUI(): void
    {
        $typeId = $_GET['type_id'] ?? '';
        $role   = $_GET['role'] ?? '';
        $orgId  = $_GET['org_id'] ?? '';

        if (!$typeId) {
            $this->fail('type_id is required');
            return;
        }

        // role 是 base64 JSON，直接透传给 getUI/getUISchema
        $params = [
            $role,        // val[0] = base64(role_json)
            $orgId,       // val[1] = org_id
            'vue_mode' => true,
        ];

        $result = kernel::single('omeauto_auto_type')->doMethod($typeId, 'getUI', $params);

        if (is_string($result) && strpos($result, '不存在') !== false) {
            $this->fail($result);
            return;
        }

        $this->succ($result);
    }
}