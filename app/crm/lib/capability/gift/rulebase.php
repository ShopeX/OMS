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
 * 赠品规则（crm / admin_gift_rulebase）删除 capability
 *
 * 写链路唯一实现：Vue 侧（`crm_ctl_api_gift_rulebase::delRuleBase()`）与 legacy 侧
 * （`crm_ctl_admin_gift_rulebase::delRuleBase()`）共用本类，避免「前后端各写一份」的双写出口。
 *
 * 语义严格对齐 legacy `app/crm/controller/admin/gift/rulebase.php::delRuleBase()`：
 *  - 选择口径：`selection_mode != '_ALL_'` 且带 ids ⇒ 只处理这些；否则（`_ALL_` 或未给 ids）⇒ 全量（legacy 原语义）
 *  - 守护：该规则被「进行中」的赠品规则应用（`crm_mdl_gift_rule` status=1 / disable=false / 当前时间在有效期）
 *    引用时拒绝删除，文案与 legacy 一致：「规则在活动中，不能删除」
 *  - 落地：软删 `disabled='true'`（legacy `use_buildin_recycle=false`，不走回收站）
 *
 * 事务：本类按框架连接状态自动判定事务归属 —— 无外层事务时自己开合；
 * legacy 入口由 `desktop_controller::begin()` / `end()` 开合事务，本类自动识别后交给外层托管。
 */
final class crm_capability_gift_rulebase
{
    private object $ruleBaseModel;
    private object $ruleModel;
    private object $database;

    public function __construct(
        ?object $ruleBaseModel = null,
        ?object $ruleModel = null,
        ?object $database = null
    ) {
        // kernel::single('crm_capability_gift_rulebase') 会按类名首段把 app::get('crm') 注入首参
        // （app/base/kernel.php::single），此处必须识别丢弃，否则 app 对象会被当 model 用
        if ($ruleBaseModel instanceof app) {
            $ruleBaseModel = null;
        }
        $this->ruleBaseModel = $ruleBaseModel ?? app::get('crm')->model('gift_rule_base');
        $this->ruleModel = $ruleModel ?? app::get('crm')->model('gift_rule');
        $this->database = $database ?? kernel::database();
    }

    /**
     * 删除赠品规则（软删 `disabled='true'`）。
     *
     * @param int[]    $ids            勾选的规则 id（selection_mode != '_ALL_' 时使用）
     * @param string   $selectionMode  '_ALL_' 表示「全选符合条件」
     *
     * @throws InvalidArgumentException 规则在活动中（文案与 legacy 逐字一致）
     * @return int 实际软删的规则条数
     */
    public function deleteRuleBases(array $ids, string $selectionMode = ''): int
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($v): int => (int) $v, $ids),
            static fn (int $v): bool => $v > 0
        )));

        // legacy 原语义：isSelectedAll != '_ALL_' 且给了 ids ⇒ 只删这些；否则（_ALL_ / 未给 ids）⇒ 全量
        $isSelectAll = $selectionMode === '_ALL_';
        if (!$isSelectAll && !empty($ids)) {
            $baseFilter = ['id' => $ids];
        } else {
            $baseFilter = [];
        }

        $rows = $this->ruleBaseModel->getList('id', $baseFilter);
        $ruleIds = array_map('current', (array) $rows);

        // legacy :281-297 —— 规则被进行中的赠品规则应用时不可删
        $ruleRows = $this->ruleModel->getList('*', [
            'status'      => '1',
            'disable'     => 'false',
            'filter_sql'  => '(' . time() . ' BETWEEN start_time AND end_time)',
        ]);
        foreach ((array) $ruleRows as $rule) {
            $filterArr = json_decode((string) ($rule['filter_arr'] ?? ''), true);
            if (!empty($filterArr['add_or_divide'])) {
                foreach ((array) ($filterArr['id'] ?? []) as $val) {
                    if (in_array($val, $ruleIds)) {
                        throw new InvalidArgumentException('规则在活动中，不能删除');
                    }
                }
            }
        }

        if (empty($ruleIds)) {
            return 0;
        }

        // 事务归属：按连接状态自动判定（已在事务中则由外层托管，如 legacy begin()）。
        $ownsTransaction = $this->beginTransactionIfOwned();

        try {
            $affected = $this->ruleBaseModel->update(['disabled' => 'true'], ['id|in' => $ruleIds]);

            if ($ownsTransaction) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction) {
                $this->database->rollBack();
            }
            throw $exception;
        }

        return (int) $affected;
    }

    /**
     * 新增/编辑赠品规则：legacy `crm/controller/admin/gift/rulebase.php::save()` 语义 1:1 复刻。
     * 校验与日志复用 legacy `crm_gift` lib；主写用 `crm_gift_rule_base` 模型。
     *
     * @param array $input 与 legacy $_POST 同形（filter_arr / gift_id / gift_num / title / id ...）
     *
     * @throws InvalidArgumentException 文案与 legacy 逐字一致
     */
    public function saveRuleBase(array $input): int
    {
        $data = $this->normalizeRuleBaseInput($input);

        // 事务归属：按连接状态自动判定（已在事务中则由外层托管，如 legacy begin()）。
        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            $id = $this->persistRuleBase($data);
            if ($ownsTransaction) {
                $this->database->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction) {
                $this->database->rollback();
            }
            throw $exception;
        }

        return (int) $id;
    }


    /**
     * 归一化 + 校验：legacy `admin/gift/rulebase.php::save()` :5-78 逐条对齐（错误文案逐字一致）。
     *
     * @throws InvalidArgumentException
     */
    private function normalizeRuleBaseInput(array $input): array
    {
        $data = $input;
        $filter = (array) ($data['filter_arr'] ?? []);
        $buy = (array) ($filter['buy_goods'] ?? []);

        // legacy :5-7 不限量 ⇒ 清除限购条件
        if (empty($buy['limit_type'])) {
            unset($buy['limit_time_day'], $buy['limit_orders']);
        }

        // legacy :10-19 订单达人
        $host = (array) ($filter['is_host'] ?? []);
        if (($host['type'] ?? '') === 'assign') {
            if (trim((string) ($host['author'] ?? '')) === '') {
                throw new InvalidArgumentException('指定达人订单，达人ID为必填');
            }
            $host['author'] = str_replace('，', ',', trim((string) $host['author']));
            $host['room'] = str_replace('，', ',', trim((string) ($host['room'] ?? '')));
        } else {
            unset($host['author'], $host['room']);
        }
        $filter['is_host'] = $host;

        // legacy :24-31 组合数据
        $data['filter_arr'] = $filter;
        $data['gift_ids'] = (array) ($data['gift_id'] ?? []);
        $data['gift_num'] = (array) ($data['gift_num'] ?? []);
        $data['modified_time'] = time();
        $buy['bm_id'] = null;
        $buy['sm_id'] = null;
        $buy['goods_bn'] = null;
        $data['filter_arr']['buy_goods'] = $buy;

        if (!empty($buy['type'])) {
            $data = $this->resolveBuyGoods($buy, $data);
        }

        $data['filter_arr'] = json_encode($data['filter_arr']);
        if (empty($data['id'])) {
            $data['create_time'] = time();
        }

        // legacy :71-78 清理赠品数量（只保留被勾选的赠品）+ 序列化
        foreach ($data['gift_num'] as $giftId => $num) {
            if (!in_array($giftId, (array) $data['gift_ids'])) {
                unset($data['gift_num'][$giftId]);
            }
        }
        $data['gift_list'] = serialize($data['gift_num']);

        return $data;
    }


    /**
     * 批量取「id ⇒ (原始 id, 大写编码)」映射，替代 legacy 的逐条查询（N+1）。
     *
     * 注意 base_db_tools::filter2sql() 对数组过滤生成的是 OR 链、且值直接拼进 SQL：
     *  - 数组为空时该条件会被整体跳过（等于查全表）⇒ 必须跳过空块；
     *  - 值未转义 ⇒ 只允许传入整型 id。
     *
     * @param  \base_db_model $model   sales_material / basic_material 模型
     * @param  string         $idField 主键字段名（sm_id / bm_id）
     * @param  string         $bnField 编码字段名（sales_material_bn / material_bn）
     * @param  array          $ids     待查询 id 列表（按原顺序返回映射，不改变调用方顺序）
     * @return array                   键 = 整型 id 的字符串形式，值 = ['id' => 原始 id, 'bn' => 大写去空编码]
     */
    private function mapIdsToBn($model, $idField, $bnField, array $ids)
    {
        $map = array();
        $keys = array();
        foreach ($ids as $id) {
            $keys[] = (int) $id;
        }
        $keys = array_values(array_unique($keys));

        foreach (array_chunk($keys, 1000) as $chunk) {
            if (empty($chunk)) {
                continue;
            }
            $rows = $model->getList($idField . ',' . $bnField, array($idField => $chunk));
            foreach ((array) $rows as $row) {
                $map[(string) (int) $row[$idField]] = array(
                    'id' => $row[$idField],
                    'bn' => trim(strtoupper((string) $row[$bnField])),
                );
            }
        }

        return $map;
    }

    /**
     * 按物料编码批量取名称（编辑回填展示用；goods_bn 与 goods_name 同序，缺名回 ""）。
     * ORM 数组过滤是 OR 链且值不转义 ⇒ 只放行编码形态的值，异常值不回填（前端回退显示编码）。
     */
    private function mapBnsToNames(string $kind, array $bns)
    {
        $model = $kind === 'bm'
            ? app::get('material')->model('basic_material')
            : app::get('material')->model('sales_material');
        $bnField = $kind === 'bm' ? 'material_bn' : 'sales_material_bn';
        $nameField = $kind === 'bm' ? 'material_name' : 'sales_material_name';

        $keys = array();
        foreach ($bns as $bn) {
            $key = trim(strtoupper((string) $bn));
            if ($key !== '' && preg_match('/^[A-Za-z0-9_\-.]+$/', $key)) {
                $keys[$key] = true;
            }
        }
        $keys = array_keys($keys);

        $map = array();
        foreach (array_chunk($keys, 1000) as $chunk) {
            if (empty($chunk)) {
                continue;
            }
            $rows = $model->getList($bnField . ',' . $nameField, array($bnField => $chunk));
            foreach ((array) $rows as $row) {
                $map[trim(strtoupper((string) $row[$bnField]))] = (string) $row[$nameField];
            }
        }

        return $map;
    }

    /**
     * legacy :32-66 指定商品：type 1=销售物料 / 2=基础物料 ⇒ 回填 id 与 goods_bn。
     *
     * @throws InvalidArgumentException 文案与 legacy 逐字一致
     */
    private function resolveBuyGoods(array $buy, array $data): array
    {
        if ((string) $buy['type'] === '1') {
            $selectSmId = (array) ($data['sm_id'] ?? []);
            if (empty($selectSmId)) {
                throw new InvalidArgumentException('已选择指定销售物料，请至少选择一个销售物料');
            }
            $model = app::get('material')->model('sales_material');
            $bnMap = $this->mapIdsToBn($model, 'sm_id', 'sales_material_bn', $selectSmId);
            foreach ($selectSmId as $smId) {
                $hit = isset($bnMap[(string) (int) $smId]) ? $bnMap[(string) (int) $smId] : null;
                $buy['sm_id'][] = $hit ? $hit['id'] : null;
                $buy['goods_bn'][] = $hit ? $hit['bn'] : '';
            }
            unset($data['sm_id']);
        } elseif ((string) $buy['type'] === '2') {
            $selectBmId = (array) ($data['bm_id'] ?? []);
            if (empty($selectBmId)) {
                throw new InvalidArgumentException('已选择指定基础物料，请至少选择一个基础物料');
            }
            $model = app::get('material')->model('basic_material');
            $bnMap = $this->mapIdsToBn($model, 'bm_id', 'material_bn', $selectBmId);
            foreach ($selectBmId as $bmId) {
                $hit = isset($bnMap[(string) (int) $bmId]) ? $bnMap[(string) (int) $bmId] : null;
                $buy['bm_id'][] = $hit ? $hit['id'] : null;
                $buy['goods_bn'][] = $hit ? $hit['bn'] : '';
            }
            unset($data['bm_id']);
        } else {
            throw new InvalidArgumentException('未知的类型');
        }

        if (empty($buy['calculate_type'])) {
            throw new InvalidArgumentException('已选择指定商品类型，请选择计算方式');
        }

        $data['filter_arr']['buy_goods'] = $buy;

        return $data;
    }

    /**
     * legacy :79-103 主写 + 快照 + 日志（日志复用 legacy `crm_gift` lib）。
     *
     * @throws RuntimeException 落库失败（文案与 legacy 逐字一致）
     */
    private function persistRuleBase(array $data): int
    {
        if (!$this->ruleBaseModel->db_save($data)) {
            throw new RuntimeException('添加失败,已经使用过该编码了');
        }

        if (!empty($data['id'])) {
            $sdf = $this->ruleBaseModel->dump($data['id'], '*');
            $sdf['goodsInfo'] = app::get('crm')->model('gift')->getList('*', ['gift_id' => $data['gift_ids']]);
            $snapshot = [
                'title' => '赠品规则发生变动',
                'content' => json_encode($sdf),
                'type' => 3,
                'task_id' => $data['id'],
                'op_user' => kernel::single('desktop_user')->get_name(),
                'create_time' => date('Y-m-d H:i:s'),
            ];
            app::get('crm')->model('snapshot')->save($snapshot);
        }

        $errorMsg = '';
        kernel::single('crm_gift')->save_gift_rule_logs($data, $errorMsg);

        // db_save 引用传参 ⇒ 新增时框架已把自增主键回填到 $data['id']
        return (int) ($data['id'] ?? 0);
    }


    /**
     * 编辑/复制回填：取规则详情（filter_arr 解码 + 赠品映射 + 赠品明细），供 Vue 表单回填。
     *
     * @throws InvalidArgumentException 规则不存在
     */
    public function getRuleBaseForEdit($id = 0, string $ruleBn = ''): array
    {
        $row = [];
        $id = (int) $id;
        if ($id > 0) {
            $row = $this->ruleBaseModel->dump($id, '*');
        }
        // 列表行只下发「已声明列」（无 id 列）⇒ 允许按唯一编码 rule_bn 兜底解析
        if (empty($row) && $ruleBn !== '') {
            $rows = $this->ruleBaseModel->getList('*', ['rule_bn' => $ruleBn]);
            $row = $rows ? $rows[0] : [];
        }
        if (empty($row)) {
            throw new InvalidArgumentException('规则不存在');
        }

        $row['filter_arr'] = json_decode((string) ($row['filter_arr'] ?? ''), true) ?: [];

        // 已选商品名称回填：legacy 只存 goods_bn（无名称）⇒ 编辑态侧栏只有编码。
        // 按编码一次批量取名称（与 goods_bn 同序），前端回填展示；取不到就是空串（前端回退显示编码）。
        $buyGoods = isset($row['filter_arr']['buy_goods']) && is_array($row['filter_arr']['buy_goods'])
            ? $row['filter_arr']['buy_goods'] : [];
        $buyType = isset($buyGoods['type']) ? (string) $buyGoods['type'] : '';
        $buyBns = isset($buyGoods['goods_bn']) && is_array($buyGoods['goods_bn']) ? $buyGoods['goods_bn'] : [];
        if (($buyType === '1' || $buyType === '2') && $buyBns) {
            $nameMap = $this->mapBnsToNames($buyType === '2' ? 'bm' : 'sm', $buyBns);
            $names = [];
            foreach ($buyBns as $bn) {
                $key = trim(strtoupper((string) $bn));
                $names[] = ($key !== '' && isset($nameMap[$key])) ? $nameMap[$key] : '';
            }
            $row['filter_arr']['buy_goods']['goods_name'] = $names;
        }

        // legacy 以序列化数组存 gift_list（`gift_id => 赠送数量`），表内并无 gift_ids 列
        // （legacy save() 里 `$data['gift_ids']` 会被 dbschema 过滤掉），故「已选赠品 + 顺序」的唯一权威源是 gift_list 的键序。
        $giftNum = @unserialize((string) ($row['gift_list'] ?? ''));
        $giftNum = is_array($giftNum) ? $giftNum : [];
        $row['gift_num'] = $giftNum;
        $row['gift_ids'] = array_map('intval', array_keys($giftNum));

        // 赠品明细（回填展示用，严格按 gift_list 顺序，避免 getList 的自然序打乱「移动」结果）
        $giftRows = $row['gift_ids']
            ? app::get('crm')->model('gift')->getList('*', ['gift_id' => $row['gift_ids']])
            : [];
        $giftById = [];
        foreach ($giftRows as $giftRow) {
            $giftById[(int) $giftRow['gift_id']] = $giftRow;
        }
        $row['gift_detail'] = [];
        foreach ($row['gift_ids'] as $giftId) {
            if (isset($giftById[$giftId])) {
                $row['gift_detail'][] = $giftById[$giftId];
            }
        }

        return $row;
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
