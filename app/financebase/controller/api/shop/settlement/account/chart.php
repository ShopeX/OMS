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
 * 会计科目设置（financebase / shop_settlement_account_chart）Vue JSON 契约
 *
 * legacy 页 `financebase_ctl_admin_shop_settlement_account_chart`
 * （app/financebase/controller/admin/shop/settlement/account/chart.php）是 Finder 列表页 Format 1：
 *  - 视图 Tab：`_views()` 2 项 —— 有效(disabled=false) / 无效(disabled=true)，各带 count() addon
 *  - 工具栏：新增（..._add）/ 置为无效（..._delete，仅 view=0）/ 置为有效（..._restore，仅 view=1）
 *  - 行操作：`column_edit`「编辑」（..._edit）；行 detail：`detail_show_log`「操作记录」
 *  - `use_buildin_set_tag/recycle/filter/export/import/selectrow=false`；`orderBy=id`
 *
 * 写链路（唯一认可链路）：Vue `act=saveAccount|disableAccount|restoreAccount`（JSON body）
 *   → 本控制器 → financebase_capability_shop_settlement_account_chart；
 * legacy 3 个写方法委托同一 capability（消除双写出口）。
 *
 * 路由：index.php?app=financebase&ctl=api_shop_settlement_account_chart&act=<index|setAccount|saveAccount|disableAccount|restoreAccount|show_history>
 * Vue：/m-financebase/shop-settlement-account-chart/index
 */
class financebase_ctl_api_shop_settlement_account_chart extends desktop_ctl_api_base
{
    /** 页级权限点（菜单行 permission，app/financebase/desktop.xml） */
    private const PERMISSION  = 'shop_settlement_account_chart';
    private const PERM_ADD    = 'shop_settlement_account_chart_add';
    private const PERM_EDIT   = 'shop_settlement_account_chart_edit';
    private const PERM_DELETE = 'shop_settlement_account_chart_delete';
    private const PERM_RESTORE = 'shop_settlement_account_chart_restore';

    private const OBJECT_NAME = 'financebase_mdl_account_chart';

    private financebase_capability_shop_settlement_account_chart $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new financebase_capability_shop_settlement_account_chart();
    }

    /**
     * 会计科目列表（Finder 契约：action=columns / filters / list / tabs / cols / detail 由框架处理）
     * GET ?app=financebase&ctl=api_shop_settlement_account_chart&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'            => self::OBJECT_NAME,
            'title'                 => app::get('base')->_('会计科目设置'),
            'actions'               => $this->buildActions(),
            'views'                 => self::buildViews(),
            'use_buildin_set_tag'   => false,
            'use_buildin_recycle'   => false,
            'use_buildin_filter'    => false,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
            'use_buildin_selectrow' => true,
            'orderBy'               => 'id',
            // disabled 不钉死在 base_filter：默认「有效」由前端默认选中第一个 Tab 并把其 filter 并入列表请求
            'base_filter'           => [],
        ]);
    }

    /**
     * 读：表单回填数据 + 已占用的应用场景（供 Vue 弹窗）
     * GET ?app=financebase&ctl=api_shop_settlement_account_chart&act=setAccount&id=0
     */
    #[RequirePermission(self::PERMISSION)]
    public function setAccount(): void
    {
        $id = (int) ($_GET['id'] ?? $_GET['p'][0] ?? 0);
        $this->succ($this->capability->formData($id));
    }

    /**
     * 写：新增 / 编辑会计科目（独立 public act）
     * POST ?app=financebase&ctl=api_shop_settlement_account_chart&act=saveAccount
     *      body JSON：{id,account,customer,description,postingkey,costcenter,reason_code,tax_rate,tax_account,account_category}
     *
     * 细粒度权限（add/edit）由**本层**按 id 分派（注解只能表达一个权限），口径对齐 legacy setAccount。
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveAccount(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        // add / edit 共用一个 act：哪个权限由入参决定 ⇒ 注解表达不了，在控制器层按 id 判（口径同 legacy setAccount）
        $isEdit = !empty($input['id']);
        if (!$this->requireUserPermission(
            $isEdit ? self::PERM_EDIT : self::PERM_ADD,
            $isEdit ? '您没有编辑会计科目的权限' : '您没有新增会计科目的权限'
        )) {
            return;
        }

        try {
            $result = $this->capability->saveAccount($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 422);
            return;
        }

        $this->succ(['message' => $result['message'] ?? '保存成功']);
    }

    /**
     * 写：批量置为无效（独立 public act；确认弹窗后调用）
     * POST ?app=financebase&ctl=api_shop_settlement_account_chart&act=disableAccount
     *      body JSON：{"ids":[...]}（兼容 id / id[]；全选标记 isSelectedAll='_ALL_' ⇒ 拒绝）
     */
    #[RequirePermission(self::PERM_DELETE)]
    public function disableAccount(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        try {
            $result = $this->capability->disableAccount($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '置为无效失败', 422);
            return;
        }

        $this->succ(['message' => $result['message'] ?? '置为无效成功']);
    }

    /**
     * 写：批量恢复（独立 public act）
     * POST ?app=financebase&ctl=api_shop_settlement_account_chart&act=restoreAccount
     *      body JSON：{"ids":[...]}（兼容 id / id[]；全选标记 isSelectedAll='_ALL_' ⇒ 拒绝）
     */
    #[RequirePermission(self::PERM_RESTORE)]
    public function restoreAccount(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        try {
            $result = $this->capability->restoreAccount($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '置为有效失败', 422);
            return;
        }

        $this->succ(['message' => $result['message'] ?? '置为有效成功']);
    }

    /**
     * 读：查看历史快照
     * GET ?app=financebase&ctl=api_shop_settlement_account_chart&act=show_history&log_id=1
     *
     * 返回 ['data'=>row,'diff_data'=>[...],'existing_categories'=>[...]]；无快照 ⇒ 422「快照数据不存在」。
     */
    #[RequirePermission(self::PERMISSION)]
    public function show_history(): void
    {
        $logId = (int) ($_GET['log_id'] ?? $_GET['p'][0] ?? 0);

        try {
            $result = $this->capability->history($logId);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '快照数据不存在', 422);
            return;
        }

        $this->succ($result);
    }

    /**
     * 视图 Tab（legacy `_views()` :27-39 逐项对齐；addon 计数由框架 tabs 处理器按同一 filter 计算）。
     *
     * @return list<array{id:string,label:string,filter:array<string,string>}>
     */
    private static function buildViews(): array
    {
        return [
            ['id' => '0', 'label' => app::get('base')->_('有效'), 'filter' => ['disabled' => 'false']],
            ['id' => '1', 'label' => app::get('base')->_('无效'), 'filter' => ['disabled' => 'true']],
        ];
    }

    /**
     * 工具栏动作（legacy index :58-87 逐字对齐：权限 + view 门控）。
     *
     * @return list<array{id:string,label:string,action:string,target:array{type:string},type:string}>
     */
    /** 控制器层细粒度权限判定（capability 只做业务）；不足时回 403 并返回 false */
    private function requireUserPermission(string $permission, string $message): bool
    {
        if (!kernel::single('desktop_user')->has_permission($permission)) {
            $this->fail($message, 403);
            return false;
        }
        return true;
    }

    private function buildActions(): array
    {
        $view = isset($_GET['view']) ? (string) $_GET['view'] : '0';

        $actions = [];

        if (kernel::single('desktop_user')->has_permission(self::PERM_ADD)) {
            $actions[] = [
                'id'     => 'add',
                'label'  => '新增',
                'action' => 'add',
                'target' => ['type' => 'vue-dialog'],
                'type'   => 'primary',
            ];
        }

        if (in_array($view, ['0', ''], true) && kernel::single('desktop_user')->has_permission(self::PERM_DELETE)) {
            $actions[] = [
                'id'     => 'disable',
                'label'  => '置为无效',
                'action' => 'disable',
                'target' => ['type' => 'vue-dialog'],
                'type'   => 'default',
            ];
        }

        if ($view === '1' && kernel::single('desktop_user')->has_permission(self::PERM_RESTORE)) {
            $actions[] = [
                'id'     => 'restore',
                'label'  => '置为有效',
                'action' => 'restore',
                'target' => ['type' => 'vue-dialog'],
                'type'   => 'default',
            ];
        }

        return $actions;
    }
}
