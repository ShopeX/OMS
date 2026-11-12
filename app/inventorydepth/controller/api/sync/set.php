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
 * 库存同步设置（inventorydepth / sync_set）Vue JSON 契约
 *
 * legacy 页 `app/inventorydepth/view/sync/setting.html` 是**配置/设置表单页**（非 finder）：
 * 三组 radio —— 库存同步基准(销售物料/平台SKUID)、库存同步方式(全量/增量)、库存回写触发依据(可售库存/回写库存)。
 * 读写链路与 legacy 共用 `inventorydepth_capability_sync_set`（capability-backed，单一实现）。
 *
 * 路由：index.php?app=inventorydepth&ctl=api_sync_set&act=<act>
 */
class inventorydepth_ctl_api_sync_set extends desktop_ctl_api_base
{
    private inventorydepth_capability_sync_set $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = kernel::single('inventorydepth_capability_sync_set');
    }

    /**
     * 库存同步设置 - 读取
     * GET ?app=inventorydepth&ctl=api_sync_set&act=index
     */
    #[RequirePermission('inventorydepth_sync_set')]
    public function index(): void
    {
        $this->succ($this->capability->loadSettings());
    }

    /**
     * 库存同步设置 - 保存（独立 public act，不在 index() 里手搓 action 分发）
     * POST ?app=inventorydepth&ctl=api_sync_set&act=save
     *      {"set": {"stock.sync.set": "sales", "stock.sync.mode": "full", "stock.sync.trigger_basis": "actual_stock"}}
     */
    #[RequirePermission('inventorydepth_sync_set')]
    public function save(): void
    {
        $input = $this->getJsonBody();
        $set   = $input['set'] ?? [];

        if (empty($set) || !is_array($set)) {
            $this->fail('参数错误：缺少设置数据');
            return;
        }

        try {
            $this->capability->saveSettings($set);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());
            return;
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败');
            return;
        }

        // 前后端分离契约：直接返回 JSON（勿用 legacy begin()/end()，其 redirect 会让 SPA 跳去 legacy 页）
        $this->succ(['message' => '保存成功']);
    }
}
