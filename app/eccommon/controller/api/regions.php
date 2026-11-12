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
 * 地区管理（eccommon/regions）Vue JSON 契约
 *
 * legacy 页 `eccommon_ctl_regions::index()`
 * （app/eccommon/controller/regions.php:19-35）是**自定义页（非 Finder）**：
 *  - 树形懒加载（`eccommon_regions_operation::getRegionById()` 按 parent 分次取数）；
 *  - 无分页 / 无筛选面板 / 无工具栏 actions / 无导出导入（index() 未声明 `use_buildin_export|import`）；
 *  - 行内动作由模板渲染（app/eccommon/view/regions/area_treeList.html）：
 *    地区名称（点击=编辑弹窗）/ 排序（可编辑输入 `p_order[region_id]`）/ 添加子地区 / 编辑 / 删除，
 *    表格底部「保存排序」按钮 → form post `act=updateOrderNum`；
 *  - 写动作（legacy 均以 `$this->begin()/end()` 托管事务）：
 *    `addDlArea`（:85）/ `saveDlArea`（:101）/ `toRemoveArea`（:132，含「已有订单使用」守卫）/
 *    `updateOrderNum`（:165）。
 *
 * 写链路（唯一认可链路）：Vue `act=<写动作>` → 本控制器 → `eccommon_capability_regions`
 * → `eccommon_regions_operation` / `eccommon_mdl_regions`；legacy `eccommon_ctl_regions`
 * 的同名写动作委托同一 capability（消除双写出口）。
 *
 * 路由：index.php?app=eccommon&ctl=api_regions&act=<getRegionById|getAllRegions|addDlArea|saveDlArea|toRemoveArea|updateOrderNum>
 */
class eccommon_ctl_api_regions extends desktop_ctl_api_base
{
    /** 页面菜单权限点（legacy 写 act 未单独设权限 ⇒ 取菜单行 permission，app/eccommon/desktop.xml:9） */
    private const PERMISSION = 'regions';

    private eccommon_capability_regions $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = new eccommon_capability_regions();
    }

    /**
     * 读：指定父级下的地区（parent_id 为空 = 顶层 region_grade=1）
     * GET ?app=eccommon&ctl=api_regions&act=getRegionById&parent_id=1
     */
    #[RequirePermission(self::PERMISSION)]
    public function getRegionById(): void
    {
        try {
            $parentId = $_GET['parent_id'] ?? $_POST['parent_id'] ?? '';
            $rows     = kernel::single('eccommon_regions_operation')->getRegionById($parentId);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '查询地区失败', 500);
            return;
        }

        $this->succ(['list' => is_array($rows) ? array_values($rows) : []]);
    }

    /**
     * 读：全部地区（扁平列表，含 child_count）
     * GET ?app=eccommon&ctl=api_regions&act=getAllRegions
     */
    #[RequirePermission(self::PERMISSION)]
    public function getAllRegions(): void
    {
        try {
            $rows = kernel::single('eccommon_regions_operation')->getAllRegions();
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '查询地区失败', 500);
            return;
        }

        $this->succ(['list' => is_array($rows) ? $rows : []]);
    }

    /**
     * 写：新建地区（独立 public act；legacy `act=addDlArea` regions.php:85-99）
     * POST ?app=eccommon&ctl=api_regions&act=addDlArea  {"p_region_id":1,"local_name":"新建区","ordernum":5}
     */
    #[RequirePermission(self::PERMISSION)]
    public function addDlArea(): void
    {
        $this->handleWrite(fn(array $input): array => $this->capability->addArea($input));
    }

    /**
     * 写：修改地区（独立 public act；legacy `act=saveDlArea` regions.php:101-112）
     * POST ?app=eccommon&ctl=api_regions&act=saveDlArea  {"region_id":1,"p_region_id":0,"local_name":"改名","ordernum":5}
     */
    #[RequirePermission(self::PERMISSION)]
    public function saveDlArea(): void
    {
        $this->handleWrite(fn(array $input): array => $this->capability->saveArea($input));
    }

    /**
     * 写：删除地区（独立 public act；legacy `act=toRemoveArea` regions.php:132-160）
     * POST ?app=eccommon&ctl=api_regions&act=toRemoveArea  {"region_id":123}
     */
    #[RequirePermission(self::PERMISSION)]
    public function toRemoveArea(): void
    {
        $this->handleWrite(function (array $input): array {
            $regionId = $input['region_id'] ?? $input['p'][0] ?? 0;

            return $this->capability->removeArea($regionId);
        });
    }

    /**
     * 写：批量保存排序（独立 public act；legacy `act=updateOrderNum` regions.php:165-193）
     * POST ?app=eccommon&ctl=api_regions&act=updateOrderNum  {"p_order":{"123":10,"124":20}}
     */
    #[RequirePermission(self::PERMISSION)]
    public function updateOrderNum(): void
    {
        $this->handleWrite(function (array $input): array {
            $pOrder = $input['p_order'] ?? [];

            return $this->capability->updateOrderNum(is_array($pOrder) ? $pOrder : []);
        });
    }

    /**
     * 写动作统一入口：解析入参 → capability（ownTransaction=true）→ JSON 响应。
     * 成功 `succ({message})`；业务校验失败 `fail(msg, 422)`；异常 `fail(msg, 500)`。
     *
     * ⚠️ 不得沿用 legacy `begin()/end()`（其响应带 redirect/splash，会让 SPA 跳走）。
     *
     * @param callable(array<string,mixed>):array{ok:bool,msg:string} $handler
     */
    private function handleWrite(callable $handler): void
    {
        $input = $this->getRequestInput();

        try {
            $result = $handler($input);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage(), 422);
            return;
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '操作失败', 500);
            return;
        }

        if (empty($result['ok'])) {
            $this->fail((string) ($result['msg'] ?? '操作失败'), 422);
            return;
        }

        $this->succ(['message' => (string) ($result['msg'] ?? '操作成功')]);
    }
}
