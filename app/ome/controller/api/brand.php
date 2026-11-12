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
 * 基础物料品牌 Vue JSON 契约。
 *
 * 读链路复用 Finder 的列表、列、筛选与详情分片；
 * 写链路复用 ome_capability_brand，统一承载品牌新增、更新和删除。
 *
 * 路由：index.php?app=ome&ctl=api_brand&act=<act>
 */
class ome_ctl_api_brand extends desktop_ctl_api_base
{
    protected static $objectName = 'ome_mdl_brand';

    /**
     * 基础物料品牌 - Finder 读链路。
     *
     * GET ?app=ome&ctl=api_brand&act=index&action=<finder_action>
     *
     * @return void
     */
    #[RequirePermission('goods_brand')]
    public function index(): void
    {
        $this->finderDispatch([
            'objectName'             => 'ome_mdl_brand',
            'finderAlias'            => 'default',
            'title'                  => '基础物料品牌',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => true,
            'use_buildin_export'     => true,
            'use_buildin_import'     => true,
            'use_buildin_filter'     => true,
            'orderBy'                => 'ordernum',
            'orderType'              => 'desc',
            // parity: legacy index() finder actions 声明「添加」按钮(admin/brand.php:8-9,
            // 弹窗形式新建物料品牌);Vue 侧由 toolbar action=add 打开新增弹窗。
            'actions'                => [
                [
                    'id'     => 'add-brand',
                    'label'  => '添加',
                    'action' => 'add',
                    'target' => ['type' => 'vue-dialog'],
                    'type'   => 'primary',
                ],
            ],
        ]);
    }

    /**
     * 基础物料品牌 - 新增或更新。
     *
     * POST ?app=ome&ctl=api_brand&act=save
     * Body: {brand_id?: int, brand_code: string, brand_name: string, ...}
     *
     * @return void
     */
    #[RequirePermission('goods_brand')]
    public function save(): void
    {
        $input = $this->getJsonBody();
        $brandId = (int) ($input['brand_id'] ?? 0);
        $capability = new ome_capability_brand();

        try {
            $brand = $brandId > 0
                ? $capability->update($brandId, $input)
                : $capability->create($input);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());
            return;
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败');
            return;
        }

        $this->succ([
            'message' => '保存成功',
            'brand'   => $brand,
        ]);
    }

    /**
     * 基础物料品牌 - 删除。
     *
     * 双入口语义：本独立 act 供 Vue 写链路调用；Finder 的 delete 分片仍由
     * index() 中的 finderDispatch() 处理，两者复用同一 capability 数据层。
     *
     * POST ?app=ome&ctl=api_brand&act=delete
     * Body: {brand_id: int} 或 {ids: int[]}
     *
     * @return void
     */
    #[RequirePermission('goods_brand')]
    public function delete(): void
    {
        $input = $this->getJsonBody();
        $brandId = (int) ($input['brand_id'] ?? 0);
        $brandIds = $brandId > 0
            ? [$brandId]
            : (isset($input['ids']) && is_array($input['ids']) ? $input['ids'] : []);

        if ($brandIds === []) {
            $this->fail('参数错误：缺少品牌ID');
            return;
        }

        $capability = new ome_capability_brand();

        try {
            foreach ($brandIds as $id) {
                $capability->delete((int) $id);
            }
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());
            return;
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '删除失败');
            return;
        }

        $this->succ(['message' => '删除成功']);
    }
}
