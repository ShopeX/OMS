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

/**
 * 批量操作设置 Vue 控制器（SCRUM-7771）
 *
 * 菜单：menu_id=8299 `app=ome&ctl=admin_return_batch&act=index`（permission=aftersale_return_batch）；
 * desktop_user 对含 `admin_` 的 menu_path 做 str_replace('admin_','api_')（app/desktop/lib/user.php:397-405）
 * ⇒ 运行时 ctl 派生为 api_return_batch，类名 ome_ctl_api_return_batch 经 `_`→`/` 反解到本文件。
 *
 * 分派纪律（lookups/php-api-dispatch.md）：
 * - Finder 查询/元数据 → index() + finderDispatch（act=index&action=columns|filters|list|cols）
 * - 独立 JSON 方法 → 直接 act=方法名（shops / getReturnBatch）
 * - 写动作 set() 不塞进 index()，且写链路走 ome_capability_return_batch（capability-backed）
 *
 * parity 基准 = legacy `app/ome/controller/admin/return/batch.php`：
 * - 工具栏只有「添加设置」（dialog 600x400，:10-16）
 * - use_buildin_recycle=false（:8）；导出未开启 ⇒ 与 finder 默认一致（builder/view.php:21 默认 false）⇒ 本页无导出
 * - 行操作「编辑」来自 legacy finder lib `app/ome/lib/finder/return/batch.php::column_edit`
 *   （框架自动合并为 `_actions` 列，前端只渲染「编辑」，legacy 无删除）
 * - 快捷筛选：legacy 模型无 searchOptions()（ome 无 return_batch 模型文件）⇒ 本页**不新增**快捷筛选
 */

use app\desktop\lib\annotation\RequirePermission;

class ome_ctl_api_return_batch extends desktop_ctl_api_base
{
    /** 菜单 permission（sdb_desktop_menus.menu_id=8299 的 permission 列） */
    private const PERMISSION = 'aftersale_return_batch';

    /** Finder 查询对象（跨入口统一） */
    protected static $objectName = 'ome_mdl_return_batch';

    /**
     * 列表入口（act=index）：分发 list / columns / filters / cols
     *
     * @return void
     */
    #[RequirePermission(self::PERMISSION)]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'             => static::$objectName,
            'finderAlias'            => 'vue',
            'title'                  => '批量设置',   // 逐字对齐 legacy admin/return/batch.php:9
            'use_buildin_filter'     => true,          // 必须 true，否则筛选定义为空（context.php:515-517）
            'use_buildin_recycle'    => false,         // legacy :8
            'use_buildin_export'     => false,         // legacy 无导出（finder/builder/view.php:21 默认 false）
            'use_buildin_import'     => false,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            // legacy :10-16 的工具栏动作；框架下发后前端按 action=add 开本页弹窗
            'actions'                => [
                [
                    'id'     => 'add-batch',
                    'label'  => '添加设置',
                    'action' => 'add',
                    'target' => ['type' => 'vue-dialog'],
                    'type'   => 'primary',
                ],
            ],
        ]);
    }

    /**
     * 店铺下拉：仅淘宝店铺且 node_id 非空（legacy set() 的 $shop_list 口径，:31-39）
     *
     * @return void
     */
    #[RequirePermission(self::PERMISSION)]
    public function shops(): void
    {
        $shopModel = $this->app->model('shop');
        $shopList = $shopModel->getList('shop_id,name', ['shop_type' => 'taobao', 'node_id|noequal' => '']);

        $this->succ($shopList ?: []);
    }

    /**
     * 编辑弹窗回填：取库中原始字段（列表行 shop_id 已被解析成店铺名，不能回填）
     *
     * @return void
     */
    #[RequirePermission(self::PERMISSION)]
    public function getReturnBatch(): void
    {
        $batchId = (int) ($_GET['batch_id'] ?? 0);

        try {
            $row = $this->returnBatchCapability()->detail($batchId);
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
     * 保存批量设置（multipart/form-data）
     *
     * 传输口径：本动作含图片上传，按 skill「文件上传路径不强制 JSON」保留 multipart，
     * 图片仍落 legacy 的 base_storager(file) ⇒ picurl/imgext 与 legacy 同存储同语义。
     * 校验、上传、事务、持久化全在 capability 内，本方法只做入参解析与响应格式化。
     *
     * @return void
     */
    #[RequirePermission(self::PERMISSION)]
    public function set(): void
    {
        $input = array_merge($_GET, $_POST);
        $uploadedImage = isset($_FILES['picurl']) && is_array($_FILES['picurl']) ? $_FILES['picurl'] : null;

        try {
            $result = $this->returnBatchCapability()->save($input, $uploadedImage);
        } catch (InvalidArgumentException $exception) {
            // 校验文案与 legacy 的 end(false, msg) 逐字一致
            $this->fail($exception->getMessage(), 422);
            return;
        } catch (Throwable $exception) {
            $this->fail('保存失败');
            return;
        }

        $this->succ($result);
    }

    /**
     * 取批量设置写能力
     *
     * @return ome_capability_return_batch
     */
    private function returnBatchCapability(): ome_capability_return_batch
    {
        return new ome_capability_return_batch();
    }
}
