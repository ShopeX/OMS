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
 * 赠品管理（crm/admin_gift）Vue JSON 契约
 *
 * legacy 页 `crm_ctl_admin_gift::index()` 是 **Finder 列表页 Format 2**：
 * 工具栏「新增（弹窗）/ 删除」+ 行操作「设置赠品」（`crm_finder_gift::column_edit`）。
 * 写链路与 legacy 共用 `crm_capability_gift`（capability-backed，单一实现）。
 *
 * 路由：index.php?app=crm&ctl=api_gift&act=<act>
 */
class crm_ctl_api_gift extends desktop_ctl_api_base
{
    /** 页面菜单权限点（legacy 该页未给写动作单独设权限 ⇒ 取菜单行 permission） */
    private const PERMISSION = 'crm_index';

    private crm_capability_gift $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new crm_capability_gift();
    }

    /**
     * 赠品列表（Finder 契约：action=columns / filters / list 由框架处理）
     * GET ?app=crm&ctl=api_gift&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'             => 'crm_mdl_gift',
            'title'                  => '赠品管理',
            // legacy `crm/controller/admin/gift.php::index()` 未设 use_buildin_filter（框架 builder/view.php:24 默认 false）
            // ⇒ 列表无「高级筛选」面板；快捷筛选独立于该开关（由 dbschema filtertype 下发，2026-09-30 对齐 legacy）
            'use_buildin_filter'     => false,
            'use_buildin_recycle'    => false,  // legacy :24 硬删除，工具栏「删除」由 actions 显式声明
            'use_buildin_export'     => false,  // legacy 本页无导出
            'use_buildin_import'     => false,  // legacy 本页无导入
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'orderBy'                => 'gift_id DESC',  // legacy :25
            'actions'                => [
                [
                    'id'     => 'add-gift',
                    'label'  => '新增',
                    'action' => 'add',
                    'target' => ['type' => 'vue-dialog'],
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
     * 编辑弹窗回填：取库中原始行（列表行可能已被加工，不能直接回填）
     * GET ?app=crm&ctl=api_gift&act=editInfo&id=<gift_id>
     */
    #[RequirePermission(self::PERMISSION)]
    public function editInfo(): void
    {
        try {
            $row = $this->capability->loadGift((int) ($_GET['id'] ?? 0));
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
            return;
        } catch (RuntimeException $exception) {
            $this->fail($exception->getMessage(), 404);
            return;
        }

        $this->succ($row);
    }

    /**
     * 弹窗「选择礼品物料」（Finder 契约：action=columns / filters / list 由框架处理）
     * GET ?app=crm&ctl=api_gift&act=materialPicker&action=columns
     *
     * 替代 legacy add.html 的 object 选择器（`sales_material@material` + filter sales_material_type=3）：
     *  - 只可选赠品类销售物料 ⇒ 固定范围随列表下发（弹窗不分栏，不请求 action=tabs）
     *  - 高级筛选字段来自 material_finder_extend_filter_sales_material
     *  - 列配置 / 分页 / 行多选由框架 vue finder 处理（与代发销售商品「关联基础商品」同一模式）
     */
    #[RequirePermission(self::PERMISSION)]
    public function materialPicker(): void
    {
        $this->finderDispatch([
            'objectName'            => 'material_mdl_sales_material',
            'title'                 => '礼品物料列表',
            'base_filter'           => self::buildGiftMaterialBaseFilter(),
            'finderCols'            => 'sales_material_bn,sales_material_name,column_brand,column_unit,column_retail_price',
            'use_buildin_set_tag'   => false,
            'use_buildin_recycle'   => false,
            'use_buildin_filter'    => true,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
            'use_buildin_selectrow' => true,
        ]);
    }

    /**
     * 赠品可选物料范围：
     *  - `sales_material_type=3` 对齐 legacy `crm/view/admin/gift/add.html` 的
     *    filter=array("sales_material_type"=>"3")（只可选赠品类销售物料）
     *  - `visibled=1` 只可选「在售」：停售货品（visibled=0）不得出现在选择弹窗中，
     *    与 legacy「只可选可售物料」口径一致（用户口径：停售的品不供用户选择）
     *
     * @return array<string, mixed>
     */
    private static function buildGiftMaterialBaseFilter(): array
    {
        return ['sales_material_type' => 3, 'visibled' => 1];
    }

    /**
     * 新增赠品（独立 public act，不在 index() 里手搓 action 分发）
     * POST ?app=crm&ctl=api_gift&act=saveGift  {"sm_id": [...]}
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveGift(): void
    {
        $input = $this->getJsonBody();
        $smIds = $input['sm_id'] ?? [];

        try {
            $count = $this->capability->addGifts(is_array($smIds) ? $smIds : []);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
            return;
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '操作失败', 500);
            return;
        }

        $this->succ(['message' => '操作成功', 'count' => $count]);
    }

    /**
     * 编辑保存（独立 public act）
     * POST ?app=crm&ctl=api_gift&act=saveEdit  {id, giftset, gift_num, is_yujing, yj_num, yj_mobile, is_del, product_id}
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveEdit(): void
    {
        $input = $this->getJsonBody();

        try {
            $this->capability->updateGift($input);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
            return;
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败', 500);
            return;
        }

        // 前后端分离契约：直接返回 JSON（勿用 legacy begin()/end()，其 redirect 会让 SPA 跳去 legacy 页）
        $this->succ(['message' => '保存成功']);
    }

    /**
     * 删除赠品（独立 public act，硬删除）
     * POST ?app=crm&ctl=api_gift&act=delGift  {"ids": [...], "selection_mode": "_ALL_", "filters": {...}}
     */
    #[RequirePermission(self::PERMISSION)]
    public function delGift(): void
    {
        $input = $this->getJsonBody();

        try {
            $count = $this->capability->deleteGifts(
                is_array($input['ids'] ?? null) ? $input['ids'] : [],
                is_array($input['filters'] ?? null) ? $input['filters'] : [],
                (string) ($input['selection_mode'] ?? '')
            );
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
            return;
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '删除失败', 500);
            return;
        }

        $this->succ(['message' => '删除成功', 'count' => $count]);
    }
}
