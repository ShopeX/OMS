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
 * 赠品管理（crm / admin_gift）读写 capability
 *
 * 写链路唯一实现：Vue 侧（`crm_ctl_api_gift`）与 legacy 侧（`crm_ctl_admin_gift`）共用本类，
 * 避免「前后端各写一份」的双写出口。
 *
 * 语义严格对齐 legacy `app/crm/controller/admin/gift.php`：
 *  - `saveGift()`  → 按 sm_id（礼品物料 sales_material_type=3）建/更新赠品行（复用已有 gift_id）
 *  - `edit(POST)`  → 更新赠品设置（数量设置 / 预警 / 状态），含 legacy 的三条校验
 *  - `delGift()`   → 硬删除（非回收站；legacy `use_buildin_recycle=false`）
 *
 * 事务：本类按框架连接状态自动判定事务归属 —— 无外层事务时自己开合；
 * legacy 入口由 `desktop_controller::begin()` / `end()` 开合事务，本类自动识别后交给外层托管。
 */
final class crm_capability_gift
{
    /** 多值 INSERT 每批条数：数千条时把「N 次单条 INSERT」压成「N/200 次」 */
    private const INSERT_BATCH = 200;

    private object $giftModel;
    private object $salesMaterialModel;
    private object $database;

    public function __construct(
        ?object $giftModel = null,
        ?object $salesMaterialModel = null,
        ?object $database = null
    ) {
        $this->giftModel = $giftModel ?? app::get('crm')->model('gift');
        $this->salesMaterialModel = $salesMaterialModel ?? app::get('material')->model('sales_material');
        $this->database = $database ?? kernel::database();
    }

    /**
     * 编辑弹窗回填：取库中原始行（列表行可能已被 framework 解析/加工，不能直接回填）。
     *
     * @throws InvalidArgumentException id 非法（文案与 legacy 一致）
     * @throws RuntimeException         记录不存在
     */
    public function loadGift(int $giftId): array
    {
        if ($giftId <= 0) {
            throw new InvalidArgumentException('gift_id error.');
        }

        $row = $this->giftModel->dump($giftId);
        if (empty($row)) {
            throw new RuntimeException('赠品不存在');
        }

        return (array) $row;
    }

    /**
     * 新增赠品（legacy `saveGift()` 语义）：按 sm_id 建/更新赠品行。
     *
     * @param string[] $smIds          礼品物料 sm_id 列表
     *
     * @throws InvalidArgumentException 缺少物料 / 物料不存在（文案与 legacy 一致）
     * @return int 落库条数
     */
    public function addGifts(array $smIds): int
    {
        $smIds = array_values(array_unique(array_filter(
            array_map(static fn ($v): string => (string) $v, $smIds),
            static fn (string $v): bool => $v !== ''
        )));

        if (empty($smIds)) {
            throw new InvalidArgumentException('缺少物料');
        }

        // 已有赠品：product_id => gift_id（legacy 复用 gift_id，避免重复插入）
        $giftMap = [];
        $existing = $this->giftModel->getList('gift_id,product_id', ['product_id|in' => $smIds]);
        foreach ((array) $existing as $row) {
            $giftMap[(string) $row['product_id']] = $row['gift_id'];
        }

        $materials = $this->salesMaterialModel->getList(
            'sm_id,sales_material_bn,sales_material_name',
            ['sm_id|in' => $smIds, 'sales_material_type' => '3']
        );
        if (empty($materials)) {
            throw new InvalidArgumentException('缺少物料');
        }

        // 事务归属：按连接状态自动判定（已在事务中则由外层托管，如 legacy begin()）。
        $ownsTransaction = $this->beginTransactionIfOwned();

        try {
            // 大批量（数千条）时逐条 save() 太慢：框架 insert() 每条都要先 `select ... where 0=1`
            // 取一次表结构、再发一条 INSERT ⇒ N 条 = 2N 次往返。
            // 拆两路：① 已有赠品要复用 gift_id，只能逐条 save()（量少）；
            // ② 新赠品走多值 INSERT，每批 self::INSERT_BATCH 条一条 SQL。
            $insertValues = [];
            foreach ((array) $materials as $material) {
                $smId = (string) $material['sm_id'];
                $bn   = (string) $material['sales_material_bn'];
                $name = (string) $material['sales_material_name'];

                if (isset($giftMap[$smId])) {
                    // ⚠️ crm_mdl_gift::save(&$data) 是引用参数：必须传变量，
                    // 传数组字面量会 fatal（"cannot be passed by reference"）。
                    $giftRow = [
                        'gift_id'    => $giftMap[$smId],
                        'product_id' => $smId,
                        'gift_bn'    => $bn,
                        'gift_name'  => $name,
                    ];
                    $this->giftModel->save($giftRow);
                    continue;
                }

                $insertValues[] = '('
                    . $this->database->quote($smId) . ','
                    . $this->database->quote($bn) . ','
                    . $this->database->quote($name) . ')';
            }

            foreach (array_chunk($insertValues, self::INSERT_BATCH) as $batch) {
                $sql = 'INSERT INTO `' . $this->giftModel->table_name(true)
                    . '` (`product_id`,`gift_bn`,`gift_name`) VALUES ' . implode(',', $batch);
                $this->database->exec($sql);
            }

            if ($ownsTransaction) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return count((array) $materials);
    }

    /**
     * 编辑保存（legacy `edit()` POST 分支语义）：校验 + 更新。
     *
     * @param array $data           表单数据（键同 legacy `$_POST`：id, giftset, gift_num,
     *                              is_yujing, yj_num, yj_mobile, is_del, product_id）
     *
     * @throws InvalidArgumentException 校验不通过（文案与 legacy 逐字一致）
     */
    public function updateGift(array $data): void
    {
        $giftId = (int) ($data['gift_id'] ?? $data['id'] ?? 0);
        if ($giftId <= 0) {
            throw new InvalidArgumentException('gift_id error.');
        }

        $giftset = (string) ($data['giftset'] ?? '0');
        $giftNum = (int) ($data['gift_num'] ?? 0);

        // legacy :159 新增设置数量不可大于当前可售库存
        if ($giftset === '0' && $giftNum > 0) {
            $store = $this->salesMaterialStock((string) ($data['product_id'] ?? ''));
            if ($giftNum > $store) {
                throw new InvalidArgumentException('设置赠品数量不可以大于当前可用库存数!');
            }
        }

        // legacy :162 不限数量 ⇒ 数量清零、关闭预警
        if ($giftset === '1') {
            $data['gift_num']  = 0;
            $data['is_yujing'] = 'false';
        }

        // legacy :166-179 预警校验
        if ((string) ($data['is_yujing'] ?? '') === 'true') {
            if (empty($data['yj_num'])) {
                throw new InvalidArgumentException('预警数量必填');
            }
            if (empty($data['yj_mobile'])) {
                throw new InvalidArgumentException('预警手机号必填');
            }
            foreach (explode('#', (string) $data['yj_mobile']) as $mobile) {
                if (!preg_match('/^[0-9]{11}$/', $mobile)) {
                    throw new InvalidArgumentException('预警手机号必须为11位数字');
                }
            }
        }

        $save = [
            'gift_id'     => $giftId,
            'product_id'  => (string) ($data['product_id'] ?? ''),
            'giftset'     => $giftset,
            'gift_num'    => (int) ($data['gift_num'] ?? 0),
            'is_yujing'   => (string) ($data['is_yujing'] ?? 'false'),
            'yj_num'      => (int) ($data['yj_num'] ?? 0),
            'yj_mobile'   => (string) ($data['yj_mobile'] ?? ''),
            'is_del'      => (string) ($data['is_del'] ?? '0'),
            'update_time' => time(),
        ];

        // 事务归属：按连接状态自动判定（已在事务中则由外层托管，如 legacy begin()）。
        $ownsTransaction = $this->beginTransactionIfOwned();

        try {
            $this->giftModel->save($save);

            if ($ownsTransaction) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction) {
                $this->database->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * 删除赠品（legacy `delGift()` 语义，硬删除）。
     *
     * @param int[]  $ids           勾选的 gift_id（selection_mode != _ALL_ 时使用）
     * @param array  $filters       列表筛选条件（selection_mode == _ALL_ 时按条件删全部）
     * @param string $selectionMode '_ALL_' 表示「全选符合条件」
     *
     * @throws InvalidArgumentException 未选择（文案与 legacy 一致）
     * @return int 删除条数
     */
    public function deleteGifts(array $ids, array $filters, string $selectionMode = ''): int
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($v): int => (int) $v, $ids),
            static fn (int $v): bool => $v > 0
        )));

        $isSelectAll = $selectionMode === '_ALL_';

        if ($isSelectAll) {
            // 与列表同语义（列表 vue/list.php 置 filter_use_like=true）⇒ 删除侧必须同置，
            // 否则「同一份 filters」列表模糊命中、删除精确命中，_ALL_ 会静默漏删。
            $this->giftModel->filter_use_like = true;
            $rows = $this->giftModel->getList('gift_id', $filters ?: []);
            $ids = [];
            foreach ((array) $rows as $row) {
                $ids[] = (int) $row['gift_id'];
            }
        }

        if (empty($ids)) {
            throw new InvalidArgumentException('请选择赠品!');
        }

        // 事务归属：按连接状态自动判定（已在事务中则由外层托管，如 legacy begin()）。
        $ownsTransaction = $this->beginTransactionIfOwned();

        try {
            $deleted = $this->giftModel->delete(['gift_id|in' => $ids]);

            if ($ownsTransaction) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return (int) $deleted;
    }

    /**
     * 可售库存（legacy `material_sales_material_stock::getSalesMStockById`）。
     * 助手不可用时返回 0（legacy 同环境必然可用；此处不阻断保存，避免误拒）。
     */
    private function salesMaterialStock(string $productId): int
    {
        try {
            $stockLib = kernel::single('material_sales_material_stock');
            if (method_exists($stockLib, 'getSalesMStockById')) {
                return (int) $stockLib->getSalesMStockById($productId);
            }
        } catch (Throwable $exception) {
            // fallthrough
        }

        return 0;
    }

    /**
     * 事务归属判定 + （必要时）开启事务。
     *
     * 归属按框架连接状态自动判定，无需调用方传参：调用方已在事务中
     * （如 legacy 控制器 `begin()` 已开事务）则由外层托管，本方法不碰事务；
     * 否则本方法自己开合（同 app/desktop/lib/application/menu.php:269 做法）。
     *
     * `connections::beginTransaction()` 不抛异常：已在事务中时直接 `return false`，
     * 故必须检查返回值；失败即复位 `_in_transaction` 标志再抛错。
     *
     * @return bool 本方法是否拥有事务（true ⇒ 调用方负责 commit/rollBack）
     */
    private function beginTransactionIfOwned(): bool
    {
        if ($this->database->isInTransaction()) {
            return false;
        }

        if (false === $this->database->beginTransaction()) {
            // 无活动事务时 'rollback' 为 no-op，仅用于复位 connections::_in_transaction
            $this->database->rollBack();
            throw new RuntimeException('开启事务失败');
        }

        return true;
    }


}
