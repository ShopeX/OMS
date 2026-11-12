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

declare(strict_types=1);

use app\desktop\lib\annotation\RequirePermission;

/**
 * 核销规则设置（financebase / shop_settlement_verification）Vue JSON 契约
 *
 * legacy 页 `financebase_ctl_admin_shop_settlement_verification`
 * （app/financebase/controller/admin/shop/settlement/verification.php）是 Finder 列表页 Format 2：
 *  - 工具栏：新增（dialog → act=setVerificationError&p[0]=0）
 *  - `use_buildin_recycle=true`（框架回收站批量删除）；`use_buildin_set_tag=false`
 *  - `use_buildin_filter=false`；`use_buildin_export/import=false`；无 Tab（无 `_views()`）
 *  - 行操作：`column_edit`「操作」= 编辑（dialog → act=setVerificationError&p[0]=<id>）
 *  - 行 detail：`detail_oplog`「操作日志」（读 memo 序列化数组，逆序）
 *
 * 写链路（唯一认可链路）：Vue `act=save`（JSON body）
 *   → 本控制器 → financebase_capability_shop_settlement_verification；
 * legacy `saveVerificationError()` 委托同一 capability（消除双写出口）。
 *
 * 路由：index.php?app=financebase&ctl=api_shop_settlement_verification&act=<error|editInfo|save>
 * Vue：/m-financebase/bill-verification-error/index
 */
class financebase_ctl_api_shop_settlement_verification extends desktop_ctl_api_base
{
    /** 页级权限点（菜单行 permission，app/financebase/desktop.xml:53） */
    private const PERMISSION = 'shop_settlement_verification';

    private const OBJECT_NAME = 'financebase_mdl_bill_verification_error';

    private financebase_capability_shop_settlement_verification $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new financebase_capability_shop_settlement_verification();
    }

    /**
     * 核销规则列表（Finder 契约：action=columns / filters / list / cols / detail 由框架处理）
     * GET ?app=financebase&ctl=api_shop_settlement_verification&act=error&action=columns
     * （act=error 逐字对齐菜单行 app=financebase&ctl=admin_shop_settlement_verification&act=error）
     */
    #[RequirePermission(self::PERMISSION)]
    public function error(): void
    {
        $this->finderDispatch([
            'objectName'            => self::OBJECT_NAME,
            'title'                 => app::get('base')->_('核销规则设置'),
            'actions'               => $this->buildActions(),
            'use_buildin_set_tag'   => false,
            'use_buildin_recycle'   => true,
            'use_buildin_filter'    => false,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
        ]);
    }

    /**
     * 读：表单回填数据 + 店铺下拉（供 Vue 弹窗）
     * GET ?app=financebase&ctl=api_shop_settlement_verification&act=editInfo&id=0
     */
    #[RequirePermission(self::PERMISSION)]
    public function editInfo(): void
    {
        $id = (int) ($_GET['id'] ?? $_GET['p'][0] ?? 0);
        $this->succ($this->capability->formData($id));
    }

    /**
     * 写：新增 / 编辑核销规则（独立 public act）
     * POST ?app=financebase&ctl=api_shop_settlement_verification&act=save
     *      body JSON：{id,money,shop_id,name,priority,is_verify,verify_mode,rule}
     */
    #[RequirePermission(self::PERMISSION)]
    public function save(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        try {
            $result = $this->capability->saveVerificationError($input, true);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 422);
            return;
        }

        $this->succ(['message' => $result['message'] ?? '保存成功', 'id' => $result['id'] ?? 0]);
    }

    /**
     * 工具栏动作（legacy index 逐字对齐：仅「新增」）。
     *
     * @return list<array{id:string,label:string,action:string,target:array{type:string},type:string}>
     */
    private function buildActions(): array
    {
        return [
            [
                'id'     => 'add',
                'label'  => '新增',
                'action' => 'add',
                'target' => ['type' => 'vue-dialog'],
                'type'   => 'primary',
            ],
        ];
    }
}
