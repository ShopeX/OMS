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
 * 赠品规则（crm/admin_gift_rulebase）Vue JSON 契约
 *
 * legacy 页 `crm_ctl_admin_gift_rulebase::index()` 是 **Finder 列表页 Format 2**：
 * 工具栏「添加赠品规则（legacy 弹窗）/ 删除」；`crm_mdl_gift_rule_base` 无 finder 对象
 * （`app/crm/lib/finder/` 下无 rule_base）⇒ **无行操作列**；`use_view_tab=false` ⇒ 无 Tab；
 * `use_buildin_export=false` ⇒ 无导出；`use_buildin_recycle=false` ⇒ 无回收站。
 *
 * 「添加赠品规则」保持 legacy 表单页（`act=addRuleBase` 渲染 `admin/gift/rule_edit_base.html`），
 * 由前端以弹窗方式打开 —— 不解析 legacy HTML，仅作 iframe/新窗口入口，业务行为与 legacy 一致。
 * 删除走 capability-backed 写链路（`crm_capability_gift_rulebase`，legacy 侧共用同一实现）。
 *
 * 路由：index.php?app=crm&ctl=api_gift_rulebase&act=<act>
 */
class crm_ctl_api_gift_rulebase extends desktop_ctl_api_base
{
    /** 页面菜单权限点（legacy 该页未给写动作单独设权限 ⇒ 取菜单行 permission） */
    private const PERMISSION = 'crm_gift_rule_base';

    private crm_capability_gift_rulebase $capability;

    private const SEARCH_LIMIT = 20;

    /**
     * 表单选料搜索：指定销售物料 / 指定基础物料 / 赠品（禁裸 id 手填）
     * GET ?app=crm&ctl=api_gift_rulebase&act=searchSm|searchBm|searchGift&keyword=kw
     * 口径：关键词必填 + 前缀匹配（`|head`，与框架同一套 filter）+ 硬 LIMIT（禁预拉全量）
     */
    #[RequirePermission(self::PERMISSION)]
    public function searchSm(): void
    {
        $this->succ($this->searchTargetList('sm'));
    }

    #[RequirePermission(self::PERMISSION)]
    public function searchBm(): void
    {
        $this->succ($this->searchTargetList('bm'));
    }

    #[RequirePermission(self::PERMISSION)]
    public function searchGift(): void
    {
        $this->succ($this->searchTargetList('gift'));
    }

    /**
     * 选料搜索共用实现：销售物料沿用 legacy `rule_edit_2.html` 的范围 filter
     * `sales_material_type in (1,2,6)`；先按编码前缀，无命中再按名称前缀。
     *
     * @return list<array{id: int, bn: string, name: string, price: string}>
     */
    private function searchTargetList(string $kind): array
    {
        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        if ($keyword === '') {
            return []; // 禁预拉：关键词必填，空词不返回任何行
        }

        $map = [
            'sm'   => ['material', 'sales_material', 'sm_id,sales_material_bn,sales_material_name', ['sales_material_type' => [1, 2, 6]], 'sm_id', 'sales_material_bn', 'sales_material_name'],
            'bm'   => ['material', 'basic_material', 'bm_id,material_bn,material_name', [], 'bm_id', 'material_bn', 'material_name'],
            'gift' => ['crm', 'gift', 'gift_id,gift_bn,gift_name,gift_price', [], 'gift_id', 'gift_bn', 'gift_name'],
        ];
        [$appName, $modelName, $cols, $baseFilter, $idField, $bnField, $nameField] = $map[$kind];
        $model = app::get($appName)->model($modelName);

        $rows = $model->getList($cols, $baseFilter + [$bnField . '|head' => $keyword], 0, self::SEARCH_LIMIT);
        if (empty($rows)) {
            $rows = $model->getList($cols, $baseFilter + [$nameField . '|head' => $keyword], 0, self::SEARCH_LIMIT);
        }

        $lists = [];
        foreach ((array) $rows as $row) {
            $lists[] = [
                'id'    => (int) $row[$idField],
                'bn'    => (string) $row[$bnField],
                'name'  => (string) $row[$nameField],
                'price' => isset($row['gift_price']) ? (string) $row['gift_price'] : '',
            ];
        }

        return $lists;
    }

    /**
     * 表单选料弹窗（Finder 契约：action=columns / filters / list 由框架处理）
     * GET ?app=crm&ctl=api_gift_rulebase&act=picker&kind=sm|bm|gift&action=columns
     *
     * 与 searchSm/searchBm/searchGift **同一「范围」口径**（销售物料仍是 legacy rule_edit_2.html 的
     * sales_material_type in (1,2,6)），但走框架 finder：支持分页 / 高级筛选 / 列配置 / 行多选，
     * 解决 searchXxx 只能「关键词前缀 + 硬 LIMIT 20」、无法翻页浏览的问题。
     *
     * 三源共用一套实现，仅在 $scopes 表里分别声明（新增选料源只改这张表）。
     */
    #[RequirePermission(self::PERMISSION)]
    public function picker(): void
    {
        $kind = (string) ($_GET['kind'] ?? 'sm');
        $scopes = self::pickerScopes();
        if (!isset($scopes[$kind])) {
            $this->fail('不支持的选料类型', 422);
            return;
        }
        $scope = $scopes[$kind];

        $this->finderDispatch([
            'objectName'            => $scope['objectName'],
            'title'                 => $scope['title'],
            'base_filter'           => $scope['baseFilter'],
            'finderCols'            => $scope['finderCols'],
            'use_buildin_set_tag'   => false,
            'use_buildin_recycle'   => false,
            'use_buildin_filter'    => true,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
            'use_buildin_selectrow' => true,
            'orderBy'               => $scope['orderBy'],
        ]);
    }

    /**
     * 选料弹窗三源声明（与 searchTargetList 的 $map 同源同口径）。
     *
     * @return array<string, array<string, mixed>>
     */
    private static function pickerScopes(): array
    {
        return [
            'sm' => [
                'objectName'  => 'material_mdl_sales_material',
                'title'       => '选择销售物料',
                'baseFilter'  => ['sales_material_type' => [1, 2, 6]],  // legacy rule_edit_2.html 范围
                'finderCols'  => 'sales_material_bn,sales_material_name,column_specifications,column_unit,column_retail_price',
                'orderBy'     => 'sm_id DESC',
            ],
            'bm' => [
                'objectName'  => 'material_mdl_basic_material',
                'title'       => '选择基础物料',
                'baseFilter'  => [],
                'finderCols'  => 'material_bn,material_name,column_specifications,column_unit,column_retail_price',
                'orderBy'     => 'bm_id DESC',
            ],
            'gift' => [
                'objectName'  => 'crm_mdl_gift',
                'title'       => '选择赠品',
                // legacy `ajax_get_gifts()` 同口径：`$filter['is_del'] = '0'`（只取开启状态的货品）
                'baseFilter'  => ['is_del' => '0'],
                'finderCols'  => 'gift_bn,gift_name,gift_price',
                'orderBy'     => 'gift_id DESC',
            ],
        ];
    }

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new crm_capability_gift_rulebase();
    }

    /**
     * 赠品规则列表（Finder 契约：action=columns / filters / list 由框架处理）
     * GET ?app=crm&ctl=api_gift_rulebase&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        // 平台坑（本页触发）：finder 对象 `crm_finder_gift_rule_base` 的 `$column_edit_order = COLUMN_IN_HEAD`
        // 引用了 legacy finder builder 定义的常量（app/desktop/lib/finder/builder/prototype.php），
        // 而 Vue/API 路径不加载该文件 ⇒ 实例化 finder 报 "Undefined constant COLUMN_IN_HEAD"，
        // 被 desktop_finder_vue_context::discoverServices() 的 try/catch 吞掉 ⇒ 函数列整体不下发
        // （表现为列表丢失行操作「编辑/复制」与「是否限量」列，而 legacy 有）。此处按 legacy 同值补齐。
        if (!defined('COLUMN_IN_HEAD')) {
            define('COLUMN_IN_HEAD', 'HEAD');
        }

        $this->finderDispatch([
            'objectName'             => 'crm_mdl_gift_rule_base',
            'title'                  => '赠品规则',
            'use_buildin_filter'     => true,
            'use_buildin_recycle'    => false,  // legacy :19 软删（disabled='true'），工具栏「删除」由 actions 显式声明
            'use_buildin_export'     => false,  // legacy :21 本页无导出
            'use_buildin_import'     => false,  // legacy 本页无导入
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_view_tab'           => false,  // legacy :22 无视图 Tab
            'orderBy'                => 'id DESC',  // legacy :18
            'actions'                => [
                [
                    'id'     => 'add-rulebase',
                    'label'  => '添加赠品规则',
                    'href'   => 'index.php?app=crm&ctl=admin_gift_rulebase&act=addRuleBase',
                    'target' => ['type' => 'dialog', 'width' => '100%', 'height' => '100%'],
                    'type'   => 'primary',
                ],
                [
                    'id'      => 'delete',
                    'label'   => '删除',
                    'action'  => 'delete',
                    'confirm' => '确定删除选中项？',
                    'type'    => 'danger',
                ],
            ],
        ]);
    }

    /**
     * 删除赠品规则（独立 public act，不在 index() 里手搓 action 分发；软删 disabled='true'）
     * POST ?app=crm&ctl=api_gift_rulebase&act=delRuleBase  {"ids": [...], "selection_mode": "_ALL_"}
     */
    #[RequirePermission(self::PERMISSION)]
    public function delRuleBase(): void
    {
        $input = $this->getJsonBody();

        try {
            $count = $this->capability->deleteRuleBases(
                is_array($input['ids'] ?? null) ? $input['ids'] : [],
                (string) ($input['selection_mode'] ?? '')
            );
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
            return;
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '删除失败', 500);
            return;
        }

        // 前后端分离契约：直接返回 JSON（勿用 legacy begin()/end()，其 redirect 会让 SPA 跳去 legacy 页）
        $this->succ(['message' => '删除成功', 'count' => $count]);
    }

    /**
     * 新增赠品规则（legacy `admin_gift_rulebase::save()` 无 id 分支）。
     * POST ?app=crm&ctl=api_gift_rulebase&act=addRuleBase（body: JSON，与 legacy $_POST 同形）
     */
    #[RequirePermission(self::PERMISSION)]
    public function addRuleBase(): void
    {
        $this->saveRuleBaseRequest();
    }

    /**
     * 编辑赠品规则（legacy 同一个 save()，body 带 id）。
     * 复制：legacy `copy_rulebase()` = `editRuleBase()` + `type=copy` ⇒ 前端复用本 action，
     * 「无 id ⇒ 新增」的语义天然覆盖，无需单独 action。
     */
    #[RequirePermission(self::PERMISSION)]
    public function editRuleBase(): void
    {
        $this->saveRuleBaseRequest();
    }

    /** 写链路公共出口：JSON body 交 capability（事务在 capability 层，控制器零直写） */
    private function saveRuleBaseRequest(): void
    {
        $input = $this->getJsonBody();

        try {
            $id = $this->capability->saveRuleBase(is_array($input) ? $input : []);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
            return;
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 500);
            return;
        }

        $this->succ(['message' => '添加成功', 'id' => $id]);
    }


    /**
     * 编辑/复制回填：GET ?app=crm&ctl=api_gift_rulebase&act=editInfo&id=N
     */
    #[RequirePermission(self::PERMISSION)]
    public function editInfo(): void
    {
        $id = $_GET['id'] ?? 0;
        $ruleBn = (string) ($_GET['rule_bn'] ?? '');

        try {
            $row = $this->capability->getRuleBaseForEdit($id, $ruleBn);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
            return;
        } catch (Throwable $exception) {
            $this->fail('读取失败', 500);
            return;
        }

        $this->succ($row);
    }

}
