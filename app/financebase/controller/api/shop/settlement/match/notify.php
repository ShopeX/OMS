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
 * 店铺收支明细匹配失败通知（financebase / admin_shop_settlement_match_notify）Vue JSON 契约
 *
 * legacy 页 `app/financebase/view/admin/shop/settlement/match/notify.html` 是**配置/设置表单页**（非 finder）：
 * 启用通知（是/否）+ 发送时间（HH:MM 整点）+ 只读运行状态看板 + 预警配置状态 + 配置指引。
 * 读写链路与 legacy 共用 `financebase_capability_shop_settlement_match_notify`（capability-backed，单一实现）。
 *
 * 路由：index.php?app=financebase&ctl=api_shop_settlement_match_notify&act=<act>
 */
class financebase_ctl_api_shop_settlement_match_notify extends desktop_ctl_api_base
{
    private financebase_capability_shop_settlement_match_notify $capability;

    public function __construct($app)
    {
        parent::__construct($app);
        $this->capability = kernel::single('financebase_capability_shop_settlement_match_notify');
    }

    /**
     * 匹配失败通知配置 - 读取
     * GET ?app=financebase&ctl=api_shop_settlement_match_notify&act=index
     */
    #[RequirePermission('shop_settlement_match_notify')]
    public function index(): void
    {
        $this->succ($this->capability->loadPageData());
    }

    /**
     * 匹配失败通知配置 - 保存（独立 public act，不在 index() 里手搓 action 分发）
     * POST ?app=financebase&ctl=api_shop_settlement_match_notify&act=save
     *      {"enabled": "true"|"false", "cron_time": "HH:MM"}
     */
    #[RequirePermission('shop_settlement_match_notify')]
    public function save(): void
    {
        $input = $this->getJsonBody();

        if (!is_array($input) || $input === array()) {
            $this->fail('参数错误：缺少设置数据');
            return;
        }

        try {
            $message = $this->capability->saveConfig($input);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());
            return;
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage() !== '' ? $exception->getMessage() : '保存失败');
            return;
        }

        // 前后端分离契约：直接返回 JSON（勿用 legacy begin()/end()，其 redirect 会让 SPA 跳去 legacy 页）
        $this->succ(array('message' => $message));
    }
}
