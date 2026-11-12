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
 * 客户分类（material / admin_customer_classify）Vue JSON 契约
 *
 * legacy 页 `material_ctl_admin_customer_classify`
 * （app/material/controller/admin/customer/classify.php）是 **Finder 列表页 Format 2**：
 *  - 工具栏 `actions`（view=0 分支）：只有「添加」（`act=add` dialog 600x300，title「新建客户分类」）
 *  - `use_buildin_recycle=true`（回收站删除）/ `use_buildin_filter=true`
 *  - `use_buildin_new_dialog=false` / `use_buildin_set_tag=false`
 *    / `use_buildin_export=false` / `use_buildin_import=false` ⇒ 无标签/无导出/无导入
 *  - `orderBy = class_id DESC`
 *  - 行内函数列（app/material/lib/finder/customer/classify.php）：`column_edit` → 「编辑」（legacy act=edit 弹窗）
 *  - `_views()`：单个「全部」Tab（count==0 时 unset ⇒ 无 Tab）
 *
 * 写链路（唯一认可链路）：Vue `ctl=api_customer_classify&act=save`（JSON body）
 *   → 本控制器 → material_capability_customer_classify（校验 / 归一化 / 持久化 / 事务的唯一实现）；
 * legacy `save()` 委托同一 capability（消除双写出口，legacy 事务由 begin()/end() 托管）。
 *
 * 路由：index.php?app=material&ctl=api_customer_classify&act=<index|editInfo|save>
 * Vue：/m-material/customer-classify/index
 */
class material_ctl_api_customer_classify extends desktop_ctl_api_base
{
    /** 页级权限点（菜单行 permission，app/material/desktop.xml） */
    private const PERMISSION = 'material_customer_classify';

    private const OBJECT_NAME = 'material_mdl_customer_classify';

    private material_capability_customer_classify $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new material_capability_customer_classify();
    }

    /**
     * 客户分类列表（Finder 契约：action=columns / filters / list / cols / tabs / delete 由框架处理）
     * GET ?app=material&ctl=api_customer_classify&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'            => self::OBJECT_NAME,
            'title'                 => app::get('base')->_('客户分类'),
            'actions'               => [
                [
                    'id'    => 'add',
                    'label' => '添加',
                    // 新建/编辑由 FE Vue 弹窗 → act=save（capability-backed），不再走 legacy dialog
                    'type'  => 'primary',
                ],
            ],
            'use_buildin_set_tag'   => false,
            'use_buildin_recycle'   => true,
            'use_buildin_filter'    => true,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
            'use_buildin_selectrow' => true,
            'orderBy'               => 'class_id desc',
            // Tab 由 _finderViews() 经 params.views 注入，count 由 tabs 处理器自动计算
            'views'                 => $this->_finderViews(),
        ]);
    }

    /**
     * 编辑回填（Vue 弹窗打开时取表单字段，取库中权威行）
     * GET ?app=material&ctl=api_customer_classify&act=editInfo&id=<class_id>
     */
    #[RequirePermission(self::PERMISSION)]
    public function editInfo(): void
    {
        $classId = (int) ($_GET['id'] ?? $_GET['class_id'] ?? $_GET['p'][0] ?? 0);

        try {
            $result = $this->capability->loadClassify($classId);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '读取失败', 422);
            return;
        }

        $this->succ($result);
    }

    /**
     * 新建 / 编辑保存（独立 public act）
     * POST ?app=material&ctl=api_customer_classify&act=save
     *      body JSON：{class_id?,class_bn,class_name,disabled}
     */
    #[RequirePermission(self::PERMISSION)]
    public function save(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        try {
            $result = $this->capability->saveClassify($input);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 422);
            return;
        }

        $this->succ([
            'success' => true,
            'message' => $result['message'] ?? '保存成功',
            'id'      => (int) ($result['id'] ?? 0),
        ]);
    }

    /**
     * Vue Finder tabs —— 与 legacy `_views()` 逐项一致：单个「全部」Tab，
     * 且 count==0 时 legacy `unset` 掉该 Tab（此处同步返回空数组 ⇒ 无 Tab）。
     *
     * @return list<array{id:string,label:string,filter:array}>
     */
    protected function _finderViews(): array
    {
        $model = app::get('material')->model('customer_classify');
        $count = (int) $model->count([]);

        if ($count === 0) {
            return [];
        }

        return [
            ['id' => '0', 'label' => app::get('base')->_('全部'), 'filter' => []],
        ];
    }
}
