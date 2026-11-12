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
 * 费用项设置（financebase / shop_settlement_feetype）Vue JSON 契约
 *
 * legacy 页 `financebase_ctl_admin_shop_settlement_fee_type`
 * （app/financebase/controller/admin/shop/settlement/fee/type.php）是 Finder 列表页 Format 2：
 *  - 工具栏：新增（`act=add` dialog 600x300，title「新增收支分类」）
 *  - 行操作：`column_edit` label「设置」—— **仅 taobao + business_type=fx 的店铺行**渲染
 *            「设置白名单」（legacy `target="_blank"` 新窗口 → `act=setWhiteList`）
 *  - 无 Tab（legacy `use_bulidin_view` 拼写错，实际不起作用）
 *  - `use_buildin_recycle=true`（回收站删除）/ `use_buildin_filter=true`
 *  - `use_buildin_set_tag=false` / `use_buildin_export=false` / `use_buildin_import=false`
 *  - 函数列：仅 `column_edit`（合成 `_actions` 列，label「设置」，width 80）
 *
 * 写链路（唯一认可链路）：Vue `act=save|saveWhiteList`（JSON body）
 *   → 本控制器 → financebase_capability_shop_settlement_fee_type；
 * legacy `save()/saveWhiteList()` 委托同一 capability（消除双写出口）。
 *
 * 路由：index.php?app=financebase&ctl=api_shop_settlement_fee_type&act=<index|setFeeType|save|setWhiteList|saveWhiteList>
 * Vue：/m-financebase/shop-settlement-fee-type/index
 *
 * 已知范围（未迁移，登记于交付说明）：legacy 控制器存在 `edit($fee_type_id)` 动作，但 Finder 列表
 * 未提供任何「编辑」入口（`column_edit` 只产出「设置白名单」），且 legacy `save()` **只做 insert**
 * （无 fee_type_id 分支）⇒ 本轮不 Vue 化编辑（新增 + 白名单两条写链路即 legacy 全部可达写面）。
 */
class financebase_ctl_api_shop_settlement_fee_type extends desktop_ctl_api_base
{
    /** 页级权限点（菜单行 permission，app/financebase/desktop.xml:42） */
    private const PERMISSION = 'shop_settlement_feetype';

    private const OBJECT_NAME = 'financebase_mdl_bill_fee_type';

    private financebase_capability_shop_settlement_fee_type $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new financebase_capability_shop_settlement_fee_type();
    }

    /**
     * 费用项列表（Finder 契约：action=columns / filters / list / cols / delete 由框架处理）
     * GET ?app=financebase&ctl=api_shop_settlement_fee_type&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'            => self::OBJECT_NAME,
            'title'                 => app::get('base')->_('费用项设置'),
            'actions'               => $this->buildActions(),
            'use_buildin_set_tag'   => false,
            'use_buildin_recycle'   => true,
            'use_buildin_filter'    => true,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
            'use_buildin_selectrow' => true,
            // legacy 无 `_views()` ⇒ 无 Tab；无 base_filter
            'base_filter'           => [],
        ]);
    }

    /**
     * 读：新增表单静态数据（店铺候选 / 收支分类候选 / 资金流向选项）
     * GET ?app=financebase&ctl=api_shop_settlement_fee_type&act=setFeeType
     */
    #[RequirePermission(self::PERMISSION)]
    public function setFeeType(): void
    {
        $this->succ($this->capability->formData());
    }

    /**
     * 写：新增费用项（独立 public act）
     * POST ?app=financebase&ctl=api_shop_settlement_fee_type&act=save
     *      body JSON：{shop_id,bill_type,rule_id}
     */
    #[RequirePermission(self::PERMISSION)]
    public function save(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        try {
            $result = $this->capability->save($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 422);
            return;
        }

        $this->succ(['message' => $result['message'] ?? '保存成功', 'id' => $result['id'] ?? 0]);
    }

    /**
     * 读：白名单回填（legacy setWhiteList）
     * GET ?app=financebase&ctl=api_shop_settlement_fee_type&act=setWhiteList&fee_type_id=1
     */
    #[RequirePermission(self::PERMISSION)]
    public function setWhiteList(): void
    {
        $feeTypeId = (int) ($_GET['fee_type_id'] ?? $_GET['id'] ?? $_GET['p'][0] ?? 0);
        $this->succ($this->capability->whiteListData($feeTypeId));
    }

    /**
     * 写：保存白名单（独立 public act）
     * POST ?app=financebase&ctl=api_shop_settlement_fee_type&act=saveWhiteList
     *      body JSON：{fee_type_id,whitelist}
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveWhiteList(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        try {
            $result = $this->capability->saveWhiteList($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 422);
            return;
        }

        $this->succ(['message' => $result['message'] ?? '保存成功']);
    }

    /**
     * 工具栏动作（legacy index `$actions` 逐字对齐：只有「新增」）。
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
