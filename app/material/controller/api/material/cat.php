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
 * 基础物料分类（material / admin_material_cat）Vue JSON 契约
 *
 * legacy 页 `material_ctl_admin_material_cat`（app/material/controller/admin/material/cat.php）
 * 是**自定义分类树页（Format 3，非 finder）**：`index()` 渲染 `admin/material/category/map.html`
 *  - 工具栏：添加分类（`act=addnew` dialog）/ 编辑排序 / 保存排序（本表单 `act=update`）/ 展开分类 / 收起分类（`tree_number<=1000`）
 *  - 列表头：分类名称 | 分类名称EN | 类型 | 排序 | 操作
 *  - 行操作：增加子类（`act=addnew&p[0]=id`）/ 编辑（`act=edit&p[0]=id`）/
 *            删除（`act=toRemove&p[0]=id`，仅 `pid>0`）/
 *            查看基础物料（跳 `admin_material_basic&act=index&filter[cat_id]=id`，仅 `pid>0 && child_count==0`）
 *  - 新增/编辑表单（`admin/material/category/info.html`）：分类名称* / 分类名称EN / 上级分类 / 排序 / 最低售价 / 最高售价
 *  - 无 Tab（无 `_views()`）、无高级筛选、无勾选列、无分页（整树一次下发）、无导出/导入
 *
 * 写链路（唯一认可链路）：Vue `ctl=api_material_cat&act=save|update|toRemove`（JSON body / `p[0]`）
 *   → 本控制器 → material_capability_cat（校验 / 归一化 / 持久化 / 事务的唯一实现，
 *     同一 capability 亦服务 `app/mcpapi/controller/v1/material/cat.php`）。
 *
 * 路由：index.php?app=material&ctl=api_material_cat&act=<getTree|save|toRemove|update>
 * Vue：/m-material/material-cat/index
 */
class material_ctl_api_material_cat extends desktop_ctl_api_base
{
    /** 页级权限点（菜单行 permission，app/material/desktop.xml:73） */
    private const PERMISSION = 'material_cat';

    private material_capability_cat $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new material_capability_cat();
    }

    /**
     * 读：整棵分类树（legacy index() 的 `$objCat->get_cat_list()` 同一份数据）。
     *
     * 额外合并 min_price / max_price / type_id，对齐 legacy `_info()` 在编辑态 dump 出的原始行
     * （Vue 编辑弹窗据此回填，避免拿列表行当回填源）。
     *
     * GET ?app=material&ctl=api_material_cat&act=getTree
     */
    #[RequirePermission(self::PERMISSION)]
    public function getTree(): void
    {
        $model = app::get('material')->model('basic_material_cat');

        $rows = $model->get_cat_list();
        if (!is_array($rows)) {
            $rows = [];
        }

        $extra = $model->getList('cat_id,min_price,max_price,type_id', [], 0, -1);
        $extraMap = [];
        foreach ((array) $extra as $extraRow) {
            $extraMap[(int) $extraRow['cat_id']] = $extraRow;
        }

        foreach ($rows as &$row) {
            $catId = (int) ($row['cat_id'] ?? 0);
            $row['cat_id'] = $catId;
            $row['pid'] = (int) ($row['pid'] ?? 0);
            $row['p_order'] = (int) ($row['p_order'] ?? 0);
            $row['child_count'] = (int) ($row['child_count'] ?? 0);
            $row['cls'] = (($row['cls'] ?? 'false') === 'true') ? 'true' : 'false';
            $row['type_name'] = $row['type_name'] ?? null;

            if (isset($extraMap[$catId])) {
                $row['min_price'] = (int) $extraMap[$catId]['min_price'];
                $row['max_price'] = (int) $extraMap[$catId]['max_price'];
                $row['type_id'] = $extraMap[$catId]['type_id'] === null ? null : (int) $extraMap[$catId]['type_id'];
            }
        }
        unset($row);

        $this->succ(['tree' => array_values($rows)]);
    }

    /**
     * 写：新增 / 编辑分类（独立 public act；legacy `save()` 读 `$_POST['cat']`）。
     *
     * POST ?app=material&ctl=api_material_cat&act=save
     *      body JSON：{cat:{cat_id?,cat_name,cat_code?,parent_id,p_order?,min_price?,max_price?}}
     */
    #[RequirePermission(self::PERMISSION)]
    public function save(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        $catId = (int) ($input['cat']['cat_id'] ?? $input['cat_id'] ?? 0);

        try {
            if ($catId > 0) {
                $result = $this->capability->update($catId, $input);
            } else {
                $result = $this->capability->create($input);
            }
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 422);
            return;
        }

        $this->succ([
            'success' => true,
            'message' => '保存成功',
            'id'      => (int) ($result['cat_id'] ?? 0),
        ]);
    }

    /**
     * 写：删除分类（独立 public act；legacy `toRemove($nCatId)`，参数经 `p[0]` 传入）。
     *
     * POST ?app=material&ctl=api_material_cat&act=toRemove&p[0]=<cat_id>
     */
    #[RequirePermission(self::PERMISSION)]
    public function toRemove($catId = 0): void
    {
        $catId = (int) ($catId ?: ($_GET['cat_id'] ?? 0));

        try {
            $this->capability->delete($catId);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '删除失败', 422);
            return;
        }

        $this->succ(['success' => true, 'message' => '已删除']);
    }

    /**
     * 写：批量保存排序（独立 public act；legacy `update()` 循环 `$_POST['p_order']`）。
     *
     * POST ?app=material&ctl=api_material_cat&act=update
     *      body JSON：{cat_id: p_order, ...}（也兼容 {"p_order": {...}}）
     */
    #[RequirePermission(self::PERMISSION)]
    public function update(): void
    {
        $input = $this->getJsonBody();
        if (empty($input)) {
            $input = $this->getRequestInput();
        }

        $orders = $input['p_order'] ?? $input;
        if (!is_array($orders)) {
            $this->fail('没有要更新的排序数据', 422);
            return;
        }
        unset($orders['cat_id'], $orders['cat']);

        try {
            $updated = $this->capability->sort($orders);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '操作失败', 422);
            return;
        }

        $this->succ(['success' => true, 'message' => '操作成功', 'updated' => $updated]);
    }
}
