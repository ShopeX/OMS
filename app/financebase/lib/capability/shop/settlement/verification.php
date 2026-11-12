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
 * 核销规则设置（financebase / shop_settlement_verification）写能力层 —— 唯一认可的写实现。
 *
 * 链路：
 *   Vue `ctl=api_shop_settlement_verification&act=save`（JSON body）
 *     → financebase_ctl_api_shop_settlement_verification → 本 capability（事务归属自动判定）
 *   legacy `financebase_ctl_admin_shop_settlement_verification::saveVerificationError()`
 *     （app/financebase/controller/admin/shop/settlement/verification.php）
 *     → 本 capability（legacy 保持自带 begin()/end()，两条链路各自实现）
 *
 * 校验 / 归一化 / 持久化 / 事务全部收敛在此层；控制器只做「权限注解 + 入参解析 + 响应格式化」。
 * 行为逐字对齐 legacy saveVerificationError():66-98 / setVerificationError():45-63。
 */
class financebase_capability_shop_settlement_verification
{
    /** @var object */
    private $model;

    /** @var object */
    private $database;

    /** @var array<string,string>|null shop_id ⇒ 店铺名（快照用，懒加载一次） */
    private $shopNameCache = null;

    public function __construct($model = null, $db = null)
    {
        $this->model    = $model ?: app::get('financebase')->model('bill_verification_error');
        $this->database = $db ?: kernel::database();
    }

    /**
     * 表单回填数据 + 店铺下拉列表（legacy setVerificationError:45-63）。
     *
     * @return array{data:array,shop_list:list<array>}
     */
    public function formData(int $id): array
    {
        $shopInfo = ['shop_id' => '', 'money' => 0, 'id' => 0];

        if ($id > 0) {
            $row = $this->model->dump($id);
            if (!empty($row)) {
                $shopInfo = $row;
            }
        }

        // legacy:56-58 —— 无规则时给一条空规则行（前端据此渲染一行空规则）
        if (empty($shopInfo['rule'])) {
            $shopInfo['rule'] = [[]];
        }

        $shopList = financebase_func::getShopList(financebase_func::getShopType());

        return ['data' => $shopInfo, 'shop_list' => $shopList ?: []];
    }

    /**
     * 保存核销规则（legacy saveVerificationError:66-98）。
     *
     * @param array<string,mixed> $input 与 legacy $_POST 同形：id/money/shop_id/name/priority/is_verify/verify_mode/rule
     *
     * @return array{message:string,id:int}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息=legacy 文案）
     */
    public function saveVerificationError(array $input): array
    {
        $data = [];
        $data['id']          = (int) ($input['id'] ?? 0);
        $data['money']       = sprintf('%.2f', (float) ($input['money'] ?? 0));
        $data['shop_id']     = (string) ($input['shop_id'] ?? '');
        $data['name']        = trim((string) ($input['name'] ?? ''));
        $data['priority']    = trim((string) ($input['priority'] ?? ''));
        $data['is_verify']   = $input['is_verify'] ?? '0';
        $data['verify_mode'] = $input['verify_mode'] ?? '0';
        $data['rule']        = $input['rule'] ?? [];

        $isEdit = !empty($input['id']);
        if (!$data['id']) {
            $data['create_time'] = time();
        }

        // 「查看快照」需要改动前/后对照 ⇒ 必须在 save 之前取旧行；新增没有旧行（无差异）
        $before = $isEdit ? (array) $this->model->dump((int) $data['id']) : [];

        // 是否唯一（legacy:85-88）
        if ($this->model->isExist(['name' => $data['name'], 'shop_id' => $data['shop_id']], $data['id'])) {
            throw new \RuntimeException('误差类型已经存在');
        }

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            if (!$this->model->save($data)) {
                throw new \RuntimeException('保存失败');
            }
            $data['id'] = (int) $data['id'];
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }
        // 追加本页「操作日志」（legacy detail_oplog 读的就是行 memo：op_name/time/money）
        // ⚠️ 仅 Vue/api 写链路记日志；legacy 控制器保持原样（不承接、不改动）
        $this->appendOperationLog(
            (int) $data['id'],
            $isEdit ? '编辑' : '新增',
            (string) $data['name'],
            (string) $data['money'],
            $isEdit ? $this->buildSnapshot($before, $data) : []
        );

        if ($ownsTransaction) {
            $this->commit();
        }

        return ['message' => '保存成功', 'id' => $data['id']];
    }


    /**
     * 追加一条「操作日志」到行 memo（legacy detail_oplog 的展示结构：op_name/time/money）。
     *
     * - 操作者取法与 `ome_mdl_operation_log::_get_op_info()` 一致：`ome_func::getDesktopUser()`
     * - 追加在数组尾部；legacy 侧用 `array_reverse` 读 ⇒ 展示为「最新在前」
     * - 条目键与同族页一致：`operation`（行为）+ `memo`（操作内容）；legacy 模板只读 3 个键，多出的键无副作用
     */
    private function appendOperationLog(int $id, string $operation, string $name, string $money, array $snapshot = []): void
    {
        if ($id <= 0) {
            return;
        }

        $row = $this->model->getRow('memo', ['id' => $id]);
        $list = @unserialize((string) ($row['memo'] ?? ''));
        $list = is_array($list) ? $list : [];

        $opName = '';
        try {
            $opInfo = kernel::single('ome_func')->getDesktopUser();
            $opName = (string) ($opInfo['op_name'] ?? '');
        } catch (\Throwable $exception) {
            $opName = '';
        }

        $entry = [
            'op_name'   => $opName,
            'time'      => time(),
            'money'     => $money,            // legacy 读取方（op_logs.html）保留该字段
            'operation' => $operation,        // 行为（新增/编辑）
            'memo'      => sprintf('差异类型：%s', $name),  // 操作内容
        ];

        // 「查看快照」数据（同族页 diff 结构：{字段: {old_value, new_value}}）；无差异时不下发
        if (!empty($snapshot)) {
            $entry['snapshot'] = $snapshot;
        }

        $list[] = $entry;

        $this->model->update(['memo' => serialize($list)], ['id' => $id]);
    }

    /**
     * 计算「查看快照」差异（同族页口径：只列**发生变化**的字段）。
     *
     * 取值做展示化处理（是/否、核销方式文案、店铺名、核销规则条件文本），
     * 与 legacy 表单 error.html 的选项文案保持一致。
     *
     * @param array<string,mixed> $before save 前的旧行
     * @param array<string,mixed> $after  本次提交（已归一化）
     *
     * @return array<string,array{old_value:string,new_value:string}>
     */
    private function buildSnapshot(array $before, array $after): array
    {
        $fields = [
            'name'        => '名称',
            'shop_id'     => '所属店铺',
            'priority'    => '优先级',
            'is_verify'   => '是否核销',
            'verify_mode' => '核销方式',
            'rule'        => '核销规则条件',
        ];

        $diff = [];
        foreach ($fields as $field => $label) {
            $oldValue = $this->formatSnapshotValue($field, $before[$field] ?? '');
            $newValue = $this->formatSnapshotValue($field, $after[$field] ?? '');

            if ($oldValue !== $newValue) {
                $diff[$field] = ['old_value' => $oldValue, 'new_value' => $newValue];
            }
        }

        return $diff;
    }

    /**
     * 快照值的展示化（legacy error.html 选项文案：0/1 ⇒ 否/是、按订单总额核销/订单收入和退款分别核销）。
     */
    private function formatSnapshotValue(string $field, $value): string
    {
        switch ($field) {
            case 'is_verify':
                return (string) $value === '1' ? '是' : '否';
            case 'verify_mode':
                return (string) $value === '1' ? '订单收入和退款分别核销' : '按订单总额核销';
            case 'shop_id':
                return $this->shopName((string) $value);
            case 'rule':
                return $this->formatRuleText($value);
            default:
                return is_scalar($value) ? (string) $value : '';
        }
    }

    /**
     * 规则条件文本：`大于 5 且 小于等于 10`（算子文案取自 legacy error.html）。
     */
    private function formatRuleText($rule): string
    {
        if (!is_array($rule) || empty($rule)) {
            return '';
        }

        $operators = [
            'than'   => '大于',
            'lthan'  => '小于',
            'nequal' => '等于',
            'sthan'  => '小于等于',
            'bthan'  => '大于等于',
        ];

        $parts = [];
        foreach ($rule as $item) {
            if (!is_array($item)) {
                continue;
            }
            $operator = (string) ($item['operator'] ?? '');
            $operand  = (string) ($item['operand'] ?? '');
            if ($operator === '' && $operand === '') {
                continue;
            }
            $parts[] = sprintf('%s %s', $operators[$operator] ?? $operator, $operand);
        }

        return implode(' 且 ', $parts);
    }

    /**
     * shop_id ⇒ 店铺名（取法与 legacy 表单下拉一致）；查不到时回退原始 id。
     */
    private function shopName(string $shopId): string
    {
        if ($shopId === '') {
            return '';
        }

        if ($this->shopNameCache === null) {
            $this->shopNameCache = [];
            try {
                $shopList = financebase_func::getShopList(financebase_func::getShopType());
                foreach ((array) $shopList as $shop) {
                    if (isset($shop['shop_id'])) {
                        $this->shopNameCache[(string) $shop['shop_id']] = (string) ($shop['name'] ?? '');
                    }
                }
            } catch (\Throwable $exception) {
                $this->shopNameCache = [];
            }
        }

        return $this->shopNameCache[$shopId] ?? $shopId;
    }

    /**
     * 事务归属：按连接状态自动判定（已在事务中则由外层托管，如旧式 desktop_controller::begin()/end()）。
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
            $this->database->rollBack();
            throw new \RuntimeException('开启事务失败');
        }

        return true;
    }

    private function commit(): void
    {
        $this->database->commit();
    }

    private function rollBack(): void
    {
        $this->database->rollBack();
    }
}
