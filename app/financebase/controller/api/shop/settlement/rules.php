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
 * 收支分类设置（financebase / shop_settlement_rules）Vue JSON 契约
 *
 * legacy 页 `financebase_ctl_admin_shop_settlement_rules`
 * （app/financebase/controller/admin/shop/settlement/rules.php）是 Finder 列表页 Format 1：
 *  - 视图 Tab：`_views()` 2 项 —— 有效(disabled=false) / 无效(disabled=true)，各带 count() addon
 *  - 工具栏：新增（..._add）/ 置为无效（..._delete，仅 view=0）/ 置为有效（..._restore，仅 view=1）
 *  - 行操作：`column_edit`「编辑」（..._edit）；行 detail：`detail_show_log`「操作记录」
 *  - 函数列：`column_account_id_plus` / `column_account_id_minus`（会计科目显示 `[account-postingkey]description`）
 *            `column_platform`（平台规则设置，结构化平台链接：blue=已设置/red=未设置）
 *  - `use_buildin_set_tag/recycle/filter/export/import=false`；`use_buildin_selectrow` 默认 true；`orderBy=ordernum`
 *
 * 写链路（Vue 侧唯一认可链路）：Vue `act=saveCategory|saveRule|disableRules|restoreRules`（JSON body）
 *   → 本控制器 → financebase_capability_shop_settlement_rules。
 * legacy 侧：**整份保持原样不动**（四个写 act 仍为自身内联实现，各带细粒度权限门）；
 * 两条链路各自独立实现，口径差异记录在迁移文档「Legacy controllers stay frozen」。
 *
 * 路由：index.php?app=financebase&ctl=api_shop_settlement_rules&act=<index|setCategory|saveCategory|disableRules|restoreRules|show_history>
 * Vue：/m-financebase/shop-settlement-rules/index
 *
 * 平台规则设置编辑页已迁 Vue：读 `act=setRule` / 写 `act=saveRule` + `PlatformRuleDialog.vue`
 * （由列表 `column_platform` 的平台链接打开，不再新窗口跳 legacy 页）；legacy 同名 act 保持原样不动。
 */
class financebase_ctl_api_shop_settlement_rules extends desktop_ctl_api_base
{
    /** 页级权限点（菜单行 permission，app/financebase/desktop.xml:37） */
    private const PERMISSION  = 'shop_settlement_rules';
    private const PERM_ADD    = 'shop_settlement_rules_add';
    private const PERM_EDIT   = 'shop_settlement_rules_edit';
    private const PERM_DELETE = 'shop_settlement_rules_delete';
    private const PERM_RESTORE = 'shop_settlement_rules_restore';

    private const OBJECT_NAME = 'financebase_mdl_bill_category_rules';

    private financebase_capability_shop_settlement_rules $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new financebase_capability_shop_settlement_rules();
    }

    /**
     * 收支分类列表（Finder 契约：action=columns / filters / list / tabs / cols / detail 由框架处理）
     * GET ?app=financebase&ctl=api_shop_settlement_rules&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'            => self::OBJECT_NAME,
            'title'                 => app::get('base')->_('收支分类设置'),
            'actions'               => $this->buildActions(),
            'views'                 => self::buildViews(),
            'use_buildin_set_tag'   => false,
            'use_buildin_recycle'   => false,
            'use_buildin_filter'    => false,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
            'use_buildin_selectrow' => true,
            'orderBy'               => 'ordernum',
            // disabled 不钉死在 base_filter：默认「有效」由前端默认选中第一个 Tab 并把其 filter 并入列表请求
            'base_filter'           => [],
        ]);
    }

    /**
     * 读：表单回填数据 + 可选会计科目（供 Vue 弹窗）
     * GET ?app=financebase&ctl=api_shop_settlement_rules&act=setCategory&id=0
     */
    #[RequirePermission(self::PERMISSION)]
    public function setCategory(): void
    {
        $ruleId = (int) ($_GET['id'] ?? $_GET['p'][0] ?? 0);
        $this->succ($this->capability->formData($ruleId));
    }

    /**
     * 读：平台规则设置表单（legacy `act=setRule` 的 JSON 化；参数支持 rule_id/platform_type 或 p[0]/p[1]）
     * GET ?app=financebase&ctl=api_shop_settlement_rules&act=setRule&rule_id=25&platform_type=alipay
     */
    #[RequirePermission(self::PERMISSION)]
    public function setRule(): void
    {
        $ruleId   = (int) ($_GET['rule_id'] ?? $_GET['p'][0] ?? 0);
        $platform = (string) ($_GET['platform_type'] ?? $_GET['p'][1] ?? '');

        try {
            $this->succ($this->capability->ruleForm($ruleId, $platform));
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '读取失败', 422);
        }
    }

    /**
     * 写：保存某平台规则（legacy `act=saveRule` 的 JSON 化；细粒度权限由本层注解 PERM_EDIT 判定）
     * POST ?app=financebase&ctl=api_shop_settlement_rules&act=saveRule
     *      body JSON：{rule_id, platform_type, business_type?, rule_rows:[[{rule_type,rule_op,rule_filter,rule_value},...],...]}
     */
    #[RequirePermission(self::PERM_EDIT)]
    public function saveRule(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        try {
            $result = $this->capability->saveRule($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 422);
            return;
        }

        $this->succ($result);
    }

    /**
     * 写：新增 / 编辑收支分类（独立 public act）
     * POST ?app=financebase&ctl=api_shop_settlement_rules&act=saveCategory
     *      body JSON：{rule_id,bill_category,ordernum,account_id_plus,account_id_minus,need_ar_verify}
     *
     * 细粒度权限（add/edit）由 capability 按 rule_id 判定（逐字对齐 legacy），失败抛异常 → 422。
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveCategory(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        // add / edit 是同一个 act：哪个权限由入参决定 ⇒ 注解表达不了，在控制器层按 rule_id 判（legacy setCategory 同口径）
        $isEdit = !empty($input['rule_id']);
        if (!$this->requireUserPermission(
            $isEdit ? self::PERM_EDIT : self::PERM_ADD,
            $isEdit ? '您没有编辑收支分类的权限' : '您没有新增收支分类的权限'
        )) {
            return;
        }

        try {
            $result = $this->capability->saveCategory($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 422);
            return;
        }

        $this->succ(['message' => $result['message'] ?? '保存成功', 'id' => $result['id'] ?? 0]);
    }

    /**
     * 写：批量置为无效（独立 public act；确认弹窗后调用）
     * POST ?app=financebase&ctl=api_shop_settlement_rules&act=disableRules
     *      body JSON：{"ids":[...]}（兼容 id / id[]；全选标记 isSelectedAll='_ALL_' ⇒ 拒绝）
     */
    #[RequirePermission(self::PERM_DELETE)]
    public function disableRules(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        try {
            $result = $this->capability->disableRules($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '置为无效失败', 422);
            return;
        }

        $this->succ(['message' => $result['message'] ?? '置为无效成功']);
    }

    /**
     * 写：批量恢复（独立 public act）
     * POST ?app=financebase&ctl=api_shop_settlement_rules&act=restoreRules
     *      body JSON：{"ids":[...]}（兼容 id / id[]；全选标记 isSelectedAll='_ALL_' ⇒ 拒绝）
     */
    #[RequirePermission(self::PERM_RESTORE)]
    public function restoreRules(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        try {
            $result = $this->capability->restoreRules($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '置为有效失败', 422);
            return;
        }

        $this->succ(['message' => $result['message'] ?? '置为有效成功']);
    }

    /**
     * 读：查看历史快照
     * GET ?app=financebase&ctl=api_shop_settlement_rules&act=show_history&log_id=1
     *
     * 返回 ['data'=>row,'diff_data'=>[...]]；无快照 ⇒ 422「快照数据不存在」。
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
     * 视图 Tab（legacy `_views()` 逐项对齐；addon 计数由框架 tabs 处理器按同一 filter 计算）。
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
     * 工具栏动作（legacy index 逐字对齐：权限 + view 门控）。
     *
     * @return list<array{id:string,label:string,action:string,target:array{type:string},type:string}>
     */
    /** 控制器层细粒度权限判定（capability 只做业务，不判权限）；不足时直接回 403 并返回 false */
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