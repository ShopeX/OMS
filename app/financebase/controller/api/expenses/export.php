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
 * 结果导出日志（financebase / shop_settlement_expenses_export）Vue JSON 契约
 *
 * legacy 页 `financebase_ctl_admin_expenses_export`
 * （app/financebase/controller/admin/expenses/export.php）是**纯只读** Finder 列表页：
 *  - `index()` 仅 `$this->finder('financebase_mdl_expenses_export_log', $params)`
 *  - params：title='导出日志' / `use_buildin_set_tag=false` / `use_buildin_filter=true` /
 *    **`use_buildin_export=false` / `use_buildin_import=false` / `use_buildin_recycle=false`** /
 *    `actions=[]` / `orderBy='id desc'`
 *  ⇒ 无 Tab（无 `_views()`）、无工具栏按钮（actions 为空）、无导出/导入/回收站、**无任何写动作**。
 *  - finder 函数列（app/financebase/lib/finder/expenses/export/log.php，`addon_cols='export_type,filter'`）：
 *    `column_trade`(账单日期) / `column_split`(拆分日期) / `column_category`(费用项) / `column_material`(基础物料编码)；
 *    其余列由 dbschema `in_list` 决定，time/enum 由框架 list 处理器服务端格式化。
 *
 * 路由：index.php?app=financebase&ctl=api_expenses_export&act=index
 * Vue：/m-financebase/expenses-export/index
 */
class financebase_ctl_api_expenses_export extends desktop_ctl_api_base
{
    /** 页级权限点（菜单行 permission，app/financebase/desktop.xml:73） */
    private const PERMISSION = 'shop_settlement_expenses_export';

    private const OBJECT_NAME = 'financebase_mdl_expenses_export_log';

    /**
     * 结果导出日志列表（Finder 契约：action=columns / filters / list / cols 由框架处理）
     * GET ?app=financebase&ctl=api_expenses_export&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'            => self::OBJECT_NAME,
            'title'                 => app::get('base')->_('导出日志'),
            'actions'               => [],
            'use_buildin_set_tag'   => false,
            'use_buildin_recycle'   => false,
            'use_buildin_filter'    => true,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
            'use_buildin_selectrow' => true,
            'orderBy'               => 'id desc',
            'base_filter'           => [],
        ]);
    }
}
