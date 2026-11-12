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
 * 公司业务组织（dealer/admin_bbu）Vue JSON 契约
 *
 * legacy 页 `dealer_ctl_admin_bbu::index()`（app/dealer/controller/admin/bbu.php:6-27）
 * 是 **Finder 列表页 Format 2**：
 *  - 工具栏 `actions`（:8-14）只有「添加公司业务组织」
 *    → href legacy `act=create`，`target=dialog::{width:500,height:200,title:'添加公司业务组织'}`
 *  - `use_buildin_set_tag = false`（:17）/ `use_buildin_filter = false`（:18）
 *    / `use_buildin_export = false`（:19）/ `use_buildin_import = false`（:20）
 *    / `use_buildin_recycle = false`（:21）⇒ 无标签 / 无筛选面板 / 无导出 / 无导入 / 无删除
 *  - 无 `_views()` ⇒ 无视图 Tab（`use_view_tab` 未开启）
 *  - 行内函数列（app/dealer/lib/finder/bbu.php）：`column_edit` → 「编辑」（legacy act=edit 弹窗）
 *    / `column_status` → 状态（enum active→活跃 / close→关闭）
 *  - 行内 detail tab（app/dealer/lib/finder/bbu.php::detail_show_log）→ 「操作记录」
 *    （已补 `vue_mode` 结构化分支，由基类 `action=detail` 按 `finderview` 分发）
 *
 * 写链路（新建 / 编辑）本轮不迁：FE 以 legacy iframe 弹窗打开 `act=create` / `act=edit`
 * （同族先例 #79 channel / #91 material-sales），故本控制器**只读**、无写 act。
 *
 * 路由：index.php?app=dealer&ctl=api_bbu&act=index
 */
class dealer_ctl_api_bbu extends desktop_ctl_api_base
{
    /** 页面菜单权限点（取 desktop.xml:37 菜单行 permission） */
    private const PERMISSION = 'dealer_bbu';

    /** 写链路共享能力（与 legacy act=save 同一份实现） */
    private dealer_capability_bbu $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new dealer_capability_bbu();
    }

    /**
     * 公司业务组织列表（Finder 契约：action=columns / filters / list / detail / cols 由框架处理）
     * GET ?app=dealer&ctl=api_bbu&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'            => 'dealer_mdl_bbu',
            'title'                 => app::get('dealer')->_('公司业务组织'),
            'actions'               => [
                [
                    'id'    => 'add',
                    'label' => '添加公司业务组织',
                    // 新建/编辑由 FE Vue 弹窗 → act=save（capability-backed），不再走 legacy dialog
                    'type'  => 'primary',
                ],
            ],
            'use_buildin_set_tag'   => false,     // legacy :17
            'use_buildin_filter'    => false,     // legacy :18
            'use_buildin_export'    => false,     // legacy :19
            'use_buildin_import'    => false,     // legacy :20
            'use_buildin_recycle'   => false,     // legacy :21
            'use_buildin_selectrow' => true,      // legacy 未显式关闭 ⇒ 行首多选列（finder 默认 true）
        ]);
    }

    /**
     * 编辑回填（Vue 弹窗打开时取表单字段）
     * GET ?app=dealer&ctl=api_bbu&act=editInfo&id=<bbu_id>
     */
    #[RequirePermission(self::PERMISSION)]
    public function editInfo(): void
    {
        try {
            $input = $this->getRequestInput();
            $bbuId = (int) ($input['id'] ?? $input['bbu_id'] ?? $_GET['p'][0] ?? 0);
            $this->succ($this->capability->loadBbu($bbuId));
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    /**
     * 新建 / 编辑保存（Vue 弹窗 → capability）
     * POST ?app=dealer&ctl=api_bbu&act=save   JSON body: {bbu_id?, bbu_code, bbu_name}
     */
    #[RequirePermission(self::PERMISSION)]
    public function save(): void
    {
        try {
            $this->succ($this->capability->saveBbu($this->getRequestInput()));
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    /**
     * 操作日志快照回显（Vue 弹窗，legacy act=show_history 等价）
     * GET ?app=dealer&ctl=api_bbu&act=snapshot&log_id=<log_id>
     */
    #[RequirePermission(self::PERMISSION)]
    public function snapshot(): void
    {
        try {
            $input = $this->getRequestInput();
            $logId = (int) ($input['log_id'] ?? $_GET['p'][0] ?? 0);
            $this->succ($this->capability->loadSnapshot($logId));
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }
}
