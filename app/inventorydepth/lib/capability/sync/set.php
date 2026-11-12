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

/**
 * 库存同步设置（inventorydepth / sync_set）读写 capability
 *
 * 单一实现：legacy 控制器 `inventorydepth_ctl_sync_set` 与 Vue JSON api
 * `inventorydepth_ctl_api_sync_set` 共用本类，避免两处漂移。
 *
 * 配置键（值语义对齐 legacy 模板 `app/inventorydepth/view/sync/setting.html`）：
 *  - stock.sync.set           ← set[stock.sync.set]：sales(销售物料，默认) | skuid(平台SKUID)
 *  - stock.sync.mode          ← set[stock.sync.mode]：full(全量库存，默认) | increment(增量库存)
 *  - stock.sync.trigger_basis ← set[stock.sync.trigger_basis]：actual_stock(可售库存，默认) | store(回写库存)
 *
 * 读取面（含两个提示文案所需的平台清单）沿用既有业务助手 `inventorydepth_sync_set`，
 * 其中 `getStockTriggerBasis()` 负责把未初始化站点归一为 actual_stock（legacy 原语义）。
 *
 * 写面语义与 legacy index() 的 POST 分支一致：逐键 getConf 比对、值未变不写。
 * conf 走 kvstore 无事务接口 ⇒ 以「先快照、异常整组回滚」保护，等价于单组写的事务边界。
 */
class inventorydepth_capability_sync_set
{
    /**
     * 允许写入的配置键与值域（对齐 legacy 模板三组 radio 的 value）
     *
     * @var array<string, string[]>
     */
    private const WRITABLE = [
        'stock.sync.set' => ['sales', 'skuid'],
        'stock.sync.mode' => ['full', 'increment'],
        'stock.sync.trigger_basis' => ['actual_stock', 'store'],
    ];

    private object $app;

    public function __construct(?object $app = null)
    {
        $this->app = $app ?? app::get('inventorydepth');
    }

    /**
     * 读取设置（legacy index 与 api index 共用）
     *
     * @return array{setData: array<string, mixed>, support_name: string, mode_support_name: string}
     */
    public function loadSettings(): array
    {
        $helper = kernel::single('inventorydepth_sync_set');

        return [
            'setData' => [
                'stock_sync_set' => $this->app->getConf('stock.sync.set'),
                'stock_sync_mode' => $this->app->getConf('stock.sync.mode'),
                'stock_sync_trigger_basis' => $helper->getStockTriggerBasis(),
            ],
            'support_name' => $helper->getSupportName(),
            'mode_support_name' => $helper->getModeSupportName(),
        ];
    }

    /**
     * 保存设置（legacy index 的 POST 分支与 api save 共用）
     *
     * @param array<string, mixed> $set 键同 legacy $_POST['set']
     *
     * @throws InvalidArgumentException 值域非法或未提供任何可写项
     */
    public function saveSettings(array $set): void
    {
        $targets = [];
        foreach (self::WRITABLE as $key => $allowed) {
            if (!array_key_exists($key, $set)) {
                continue;
            }

            $value = (string) $set[$key];
            if (!in_array($value, $allowed, true)) {
                throw new InvalidArgumentException('库存同步设置参数无效：' . $key);
            }

            $targets[$key] = $value;
        }

        if ($targets === []) {
            throw new InvalidArgumentException('参数错误：缺少设置数据');
        }

        // 快照（conf 无事务能力 ⇒ 异常时整组回滚，避免半保存）
        $snapshot = [];
        foreach ($targets as $key => $value) {
            $snapshot[$key] = $this->app->getConf($key);
        }

        try {
            foreach ($targets as $key => $value) {
                // 对齐 legacy：与当前值相同则不写
                if ($snapshot[$key] != $value) {
                    $this->app->setConf($key, $value);
                }
            }
        } catch (Throwable $exception) {
            foreach ($snapshot as $key => $old) {
                $this->app->setConf($key, $old);
            }

            throw $exception;
        }
    }
}
