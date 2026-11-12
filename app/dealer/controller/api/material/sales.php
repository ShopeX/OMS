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
 * 代发销售商品（dealer/admin_material_sales）Vue JSON 契约
 *
 * legacy 页 `dealer_ctl_admin_material_sales::index()`
 * （app/dealer/controller/admin/material/sales.php:62-96）是 **Finder 列表页**：
 *  - `actions`（:84-88）：仅「新建销售商品」一个工具栏按钮；
 *    **新建 / 编辑已 Vue 化**：FE 自建 FinderFormDialog 表单 → `act=save`（capability-backed，
 *    dealer_capability_material_sales），不再走 legacy iframe 弹窗；
 *    legacy `act=add/edit/toAdd/toEdit` 保留但**仅作传输适配**（委托同一 capability）
 *  - `use_buildin_set_tag = false`（:77）/ `use_buildin_recycle = false`（:78）
 *    / `use_buildin_export = false`（:80）/ `use_buildin_import = false`（:81）
 *    / `use_buildin_importxls = false`（:82）
 *  - `use_buildin_filter`（:79）在 legacy 中被注释掉 ⇒ 无筛选面板，按 false 对齐
 *    （legacy finder builder 默认值即 false，见 app/desktop/lib/finder/builder/view.php:24）
 *  - `base_filter`（:64-71）：按 organization_cos 组织权限过滤 cos_id
 *  - 视图 Tab：`_views()`（:20-54）4 项 —— 全部 / 普通(1) / 组合(2) / 赠品(3)
 *  - 行内函数列 / 行内 detail tab（app/dealer/lib/finder/material/sales.php）
 *    已补 `vue_mode` 结构化分支，由基类 `action=detail` 按 `finderview` 分发
 *
 * 路由：index.php?app=dealer&ctl=api_material_sales&act=index
 */
class dealer_ctl_api_material_sales extends desktop_ctl_api_base
{
    /** 页面菜单权限点（取 desktop.xml:49 菜单行 permission） */
    private const PERMISSION = 'dealer_sales_material_sku';

    /** 共享写能力：Vue JSON 与 legacy 表单两条入口共用同一份校验/落库逻辑 */
    private dealer_capability_material_sales $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new dealer_capability_material_sales();
    }

    /**
     * 代发销售商品列表（Finder 契约：action=columns / filters / list / tabs / detail / cols 由框架处理）
     * GET ?app=dealer&ctl=api_material_sales&act=index&action=columns
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'            => 'dealer_mdl_sales_material',
            'title'                 => app::get('dealer')->_('代发销售商品'),
            'actions'               => [
                [
                    'id'    => 'add',
                    'label' => '新建销售商品',
                    // 新建/编辑由 FE Vue 弹窗 → act=save（capability-backed），不再走 legacy iframe
                    'type'  => 'primary',
                ],
            ],
            'base_filter'           => self::buildBaseFilter(),
            'use_buildin_recycle'   => false,     // legacy :78
            'use_buildin_set_tag'   => false,     // legacy :77
            'use_buildin_export'    => false,     // legacy :80
            'use_buildin_import'    => false,     // legacy :81
            'use_buildin_selectrow' => true,      // legacy 未显式关闭 ⇒ 行首多选列（finder 默认 true）
            'use_buildin_filter'    => false,     // legacy :79 被注释掉 ⇒ 无筛选面板
            'views'                 => self::buildViews(),
        ]);
    }

    /**
     * 编辑回填（Vue 弹窗打开时取主表字段 + 已关联基础商品）
     * GET ?app=dealer&ctl=api_material_sales&act=editInfo&id=<sm_id>
     */
    #[RequirePermission(self::PERMISSION)]
    public function editInfo(): void
    {
        try {
            $input = $this->getRequestInput();
            $smId  = (int) ($input['id'] ?? $input['sm_id'] ?? $_GET['p'][0] ?? 0);
            $this->succ($this->capability->loadMaterial($smId));
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    /**
     * 新建 / 编辑保存（Vue 弹窗 → capability，legacy toAdd/toEdit 同一份实现）
     * POST ?app=dealer&ctl=api_material_sales&act=save
     * JSON body: {sm_id?, sales_material_bn, sales_material_name, sales_material_type, shop_id,
     *             bm_id(类型1) | at{bm_id:number}(类型2/3), pr{bm_id:rate}(类型2)}
     */
    #[RequirePermission(self::PERMISSION)]
    public function save(): void
    {
        try {
            $this->succ($this->capability->saveMaterial($this->getRequestInput()));
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    /**
     * 弹窗「所属经销店铺」下拉（等价 legacy add()/edit() 的 delivery_mode=shopyjdf + cos 过滤）
     * GET ?app=dealer&ctl=api_material_sales&act=shops
     */
    #[RequirePermission(self::PERMISSION)]
    public function shops(): void
    {
        try {
            $cosIds = self::cosIdsForOptions();
            if ($cosIds === null) {
                $this->succ(['shops' => []]);

                return;
            }
            $keyword = trim((string) ($_GET['keyword'] ?? ''));
            $this->succ(['shops' => $this->capability->loadShops($keyword, $cosIds)]);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage(), 500);
        }
    }

    /**
     * 弹窗「选择基础物料」（Finder 契约）
     * GET ?app=dealer&ctl=api_material_sales&act=basicMaterialPicker&action=columns|filters|list
     *
     * 替代 legacy「关联基础商品」输入框弹窗链路（app/material/lib/view/input.php type=material_object
     * → material_ctl_admin_material_basic::findMaterial）：
     *  - 只能选可售物料：前端固定下发 visibled=1 基础范围（不提供分栏，也不下发 action=tabs）
     *  - 高级筛选字段来自 material_finder_extend_filter_basic_material（物料类型/物料属性/编码模糊/物料标记）
     *  - 列配置 / 分页 / 行多选由框架 vue finder 处理
     */
    #[RequirePermission(self::PERMISSION)]
    public function basicMaterialPicker(): void
    {
        $this->finderDispatch([
            'objectName'            => 'material_mdl_basic_material',
            'title'                 => app::get('base')->_('基础物料列表'),
            'base_filter'           => self::buildBasicMaterialBaseFilter(),
            'finderCols'            => 'material_bn,material_name,column_goods_type,type,visibled,omnichannel,column_barcode',
            'use_buildin_set_tag'   => false,
            'use_buildin_recycle'   => false,
            'use_buildin_filter'    => true,
            'use_buildin_export'    => false,
            'use_buildin_import'    => false,
            'use_buildin_selectrow' => true,
        ]);
    }

    /**
     * 组织 cos 白名单（与 buildBaseFilter 同一判据）
     *
     * @return list<int>|null null = 当前账号无组织权限 ⇒ 弹窗选项应为空
     */
    private static function cosIdsForOptions(): ?array
    {
        $cosList = kernel::single('organization_cos')->getCosList();
        if (!$cosList[0]) {
            return null;
        }
        if ($cosList[1] === '_ALL_') {
            return [];
        }

        return array_column($cosList[1], 'cos_id');
    }

    /**
     * legacy `index()`（app/dealer/controller/admin/material/sales.php:64-71）逐字对齐
     *
     * @return array<string, mixed>
     */
    private static function buildBaseFilter(): array
    {
        // 根据权限，只展示当前账号有权限的商品
        $cosList = kernel::single('organization_cos')->getCosList();
        if (!$cosList[0]) {
            return ['sm_id' => 0];
        }
        if ($cosList[0] && $cosList[1] != '_ALL_') {
            return ['cos_id|in' => array_column($cosList[1], 'cos_id')];
        }
        return [];
    }

    /**
     * 视图 Tab（legacy `_views()` app/dealer/controller/admin/material/sales.php:40-53 逐项对齐；
     * 每项 addon 计数由框架 tabs 处理器按同一 filter 计算）
     *
     * @return list<array{id:string,label:string,filter:array<string,mixed>}>
     */
    private static function buildViews(): array
    {
        $baseFilter = self::buildBaseFilter();

        return [
            ['id' => '0', 'label' => app::get('base')->_('全部'), 'filter' => $baseFilter],
            ['id' => '1', 'label' => app::get('base')->_('普通'), 'filter' => array_merge($baseFilter, ['sales_material_type' => 1])],
            ['id' => '2', 'label' => app::get('base')->_('组合'), 'filter' => array_merge($baseFilter, ['sales_material_type' => 2])],
            ['id' => '3', 'label' => app::get('base')->_('赠品'), 'filter' => array_merge($baseFilter, ['sales_material_type' => 3])],
        ];
    }

    /**
     * 弹窗基础物料 cos 白名单
     *
     * legacy 对齐 app/dealer/controller/admin/material/sales.php:107-124 的 $bm_filter：
     * 无组织权限 ⇒ 空结果；非 _ALL_ ⇒ cos_id in (bbu cos ids)，bbu 为空回退 cos_id=0；_ALL_ ⇒ 不限制。
     *
     * @return array<string, mixed>
     */
    private static function buildBasicMaterialBaseFilter(): array
    {
        $cosList    = kernel::single('organization_cos')->getCosList();
        $bbuCosList = kernel::single('organization_cos')->getBbuFromCosId();

        if (!$cosList[0]) {
            return ['bm_id' => 0];
        }
        if ($cosList[1] != '_ALL_') {
            $bbuCosIds = is_array($bbuCosList[1]) ? array_column($bbuCosList[1], 'cos_id') : [];

            return ['cos_id|in' => $bbuCosIds ?: [0]];
        }

        return [];
    }

}
