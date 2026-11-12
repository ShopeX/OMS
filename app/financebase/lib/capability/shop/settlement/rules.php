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
 * 收支分类设置（financebase / shop_settlement_rules）写能力层 —— **Vue 写链路的**唯一实现。
 *
 * 链路：
 *   Vue `ctl=api_shop_settlement_rules&act=saveCategory|setRule|saveRule|disableRules|restoreRules`
 *     → financebase_ctl_api_shop_settlement_rules → 本 capability（事务归属自动判定）
 *   legacy 页 `financebase_ctl_admin_shop_settlement_rules` **整份保持原样**：其写 act 仍为自身内联实现
 *     （自带细粒度权限门），不委托本层 —— legacy 控制器冻结，两条链路各自实现（差异记录于迁移文档）。
 *
 * 校验 / 归一化 / 持久化 / 事务全部收敛在此层；控制器只做「权限注解/权限判定 + 入参解析 + 响应格式化」。
 * 行为逐字对齐 legacy saveCategory:144-204 / disableRules:469-509 / restoreRules:512-552 /
 * show_history:402-428 / _recordLog:317-363 / _formatAccountDisplay:371-397 / _compareRuleData:433-466。
 */
class financebase_capability_shop_settlement_rules
{
    /** @var object */
    private $rulesModel;

    /** @var object */
    private $logModel;

    /** @var object */
    private $snapshotModel;

    /** @var object */
    private $database;

    public function __construct($rulesModel = null, $logModel = null, $snapshotModel = null, $db = null)
    {
        $this->rulesModel    = $rulesModel ?: app::get('financebase')->model('bill_category_rules');
        $this->logModel      = $logModel ?: app::get('ome')->model('operation_log');
        $this->snapshotModel = $snapshotModel ?: app::get('ome')->model('operation_log_snapshoot');
        $this->database      = $db ?: kernel::database();
    }

    /**
     * 表单回填数据 + 可选会计科目（legacy setCategory:109-141）。
     *
     * @return array{data:array,account_list:list<array>}
     */
    public function formData(int $ruleId): array
    {
        $ruleInfo = ['rule_id' => $ruleId];

        if ($ruleId > 0) {
            $row = $this->rulesModel->getRow(
                'rule_id,bill_category,ordernum,account_id_plus,account_id_minus,need_ar_verify',
                ['rule_id' => $ruleId]
            );
            if (!empty($row)) {
                $ruleInfo = $row;
            }
        } else {
            // 新增默认值：优先级取当前最大值 + 1；默认需要核对 AR（legacy:130-132）
            $row = $this->rulesModel->getRow('max(ordernum) as max', []);
            $ruleInfo['ordernum'] = $row ? (int) $row['max'] + 1 : 1;
            $ruleInfo['need_ar_verify'] = 1;
        }

        // 只获取有效且排除内置特殊场景科目（legacy:137）
        $accountModel = app::get('financebase')->model('account_chart');
        $accountList = $accountModel->getList(
            'id,account,description,postingkey',
            ['disabled' => 'false', 'account_category' => '']
        );

        return ['data' => $ruleInfo, 'account_list' => $accountList ?: []];
    }

    /**
     * 保存具体类别名称（legacy saveCategory:144-204）。
     *
     * @param array<string,mixed> $input 与 legacy $_POST 同形：rule_id/bill_category/ordernum/account_id_plus/account_id_minus/need_ar_verify
     *
     * @return array{message:string,id:int}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息=legacy 文案）
     */
    public function saveCategory(array $input): array
    {
        $data = [];
        $data['rule_id'] = (int) ($input['rule_id'] ?? 0);
        if (!$data['rule_id']) {
            $data['create_time'] = time();
        }

        // legacy: htmlspecialchars($_POST['bill_category'])（不做 trim，逐字对齐）
        $data['ordernum']         = (int) ($input['ordernum'] ?? 0);
        $data['bill_category']    = htmlspecialchars((string) ($input['bill_category'] ?? ''));
        $data['account_id_plus']  = (int) ($input['account_id_plus'] ?? 0);
        $data['account_id_minus'] = (int) ($input['account_id_minus'] ?? 0);
        $data['need_ar_verify']   = (int) ($input['need_ar_verify'] ?? 0);

        // 具体类别唯一（legacy:176-179）
        if ($this->rulesModel->isExist(['bill_category' => $data['bill_category']], $data['rule_id'])) {
            throw new \RuntimeException('具体类别有重名');
        }

        // 优先级唯一（legacy:182-185）
        if ($this->rulesModel->isExist(['ordernum' => $data['ordernum']], $data['rule_id'])) {
            throw new \RuntimeException('优先级已存在');
        }

        // 编辑时取原始数据用于快照（legacy:188-191）
        $existing = null;
        if ($data['rule_id']) {
            $existing = $this->rulesModel->dump(['rule_id' => $data['rule_id']]);
        }

        $action = $data['rule_id'] ? 'edit' : 'add';

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            if (!$this->rulesModel->save($data)) {
                throw new \RuntimeException('保存失败');
            }
            // dbeav_model::save(&$data) 按引用回填自增主键 ⇒ 新增后 rule_id 有效
            $data['rule_id'] = (int) $data['rule_id'];
            $this->recordLog($action, $data['rule_id'], $data, $existing);
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }
        if ($ownsTransaction) {
            $this->commit();
        }

        return ['message' => '保存成功', 'id' => $data['rule_id']];
    }

    /**
     * 批量置为无效（legacy disableRules:469-509）。
     *
     * @param array $ids rule_id 列表，或包含 ids/id 与 isSelectedAll 的输入数组
     *
     * @return array{message:string}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息=legacy 文案）
     */
    /**
     * 读：某平台规则设置表单（legacy `admin/.../rules.php::setRule` + view `admin/bill/rules.html` 的 JSON 化）
     *
     * @return array{rule_id:int,platform_type:string,platform_label:string,title:string,business_type:string,rule_rows:array}
     */
    public function ruleForm(int $ruleId, string $platform): array
    {
        $platforms = $this->platformMap();
        if (!isset($platforms[$platform])) {
            throw new \RuntimeException('平台类型不存在');
        }

        $row = $this->rulesModel->getRow('rule_id,business_type,rule_content', ['rule_id' => $ruleId]);
        if (!$row) {
            throw new \RuntimeException('收支分类不存在');
        }

        $content = json_decode((string) ($row['rule_content'] ?? ''), true);
        if (!is_array($content)) {
            $content = [];
        }
        $rows = isset($content[$platform]) && is_array($content[$platform]) ? $content[$platform] : [];

        $normalized = [];
        foreach ($rows as $rowItems) {
            $line = [];
            foreach ((array) $rowItems as $item) {
                $line[] = [
                    'rule_type'   => (string) ($item['rule_type'] ?? 'trade_type'),
                    'rule_op'     => (string) ($item['rule_op'] ?? 'and'),
                    'rule_filter' => (string) ($item['rule_filter'] ?? 'contain'),
                    'rule_value'  => (string) ($item['rule_value'] ?? ''),
                ];
            }
            $normalized[] = $line;
        }

        return [
            'rule_id'        => (int) $row['rule_id'],
            'platform_type'  => $platform,
            'platform_label' => $platforms[$platform],
            'title'          => $platforms[$platform] . '规则设置',
            'business_type'  => (string) ($row['business_type'] ?? ''),
            'rule_rows'      => $normalized,
        ];
    }

    /**
     * 写：保存某平台规则（legacy `saveRule` 的等价实现；唯一写实现）
     *
     * legacy 形态：`rule_content[platform] = [ [ {rule_type,rule_value,rule_op,rule_filter}, ... ], ... ]`
     * （外层 = 规则行，内层 = 该行条件）；legacy 允许保存空规则（清空该平台规则）。
     *
     * ⚠️ legacy 的 saveRule 会把表单里的 business_type 直接写回，而该字段只在 alipay 表单里出现
     * ⇒ 非 alipay 平台保存会把 business_type 清空（legacy 缺陷）。迁移动口径「功能不丢失」⇒ 非 alipay 保留原值。
     */
    public function saveRule(array $input): array
    {
        $ruleId   = (int) ($input['rule_id'] ?? 0);
        $platform = (string) ($input['platform_type'] ?? '');
        if (!$ruleId) {
            throw new \RuntimeException('参数错误');
        }
        $platforms = $this->platformMap();
        if (!isset($platforms[$platform])) {
            throw new \RuntimeException('平台类型不存在');
        }

        $ruleContent = [];
        foreach ((array) ($input['rule_rows'] ?? []) as $rowItems) {
            $line = [];
            foreach ((array) $rowItems as $item) {
                $line[] = [
                    'rule_type'   => (string) ($item['rule_type'] ?? 'trade_type'),
                    'rule_value'  => (string) ($item['rule_value'] ?? ''),
                    'rule_op'     => (string) ($item['rule_op'] ?? 'and'),
                    'rule_filter' => (string) ($item['rule_filter'] ?? 'contain'),
                ];
            }
            $ruleContent[] = $line;
        }

        $rule = $this->rulesModel->getRow('rule_id,rule_content,business_type', ['rule_id' => $ruleId]);
        if (!$rule) {
            throw new \RuntimeException('收支分类不存在');
        }

        $content = json_decode((string) ($rule['rule_content'] ?? ''), true);
        if (!is_array($content)) {
            $content = [];
        }
        $content[$platform] = $ruleContent;

        $data = [
            'rule_id'      => $ruleId,
            'rule_content' => json_encode($content, JSON_UNESCAPED_UNICODE),
            'business_type' => $platform === 'alipay'
                ? (string) ($input['business_type'] ?? '')
                : (string) ($rule['business_type'] ?? ''),
        ];

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            if (!$this->rulesModel->save($data)) {
                throw new \RuntimeException('保存失败');
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }
        if ($ownsTransaction) {
            $this->commit();
        }

        // legacy saveRule 成功后会重编译规则缓存（事务外调用，避免用未提交数据重编译）
        kernel::single('financebase_data_bill_category_rules')->setRules();

        return ['message' => '保存成功', 'rule_id' => $ruleId, 'platform_type' => $platform];
    }

    /** 平台字典（legacy `financebase_func::getShopPlatform()`，不含 $column_platform=true 的子集过滤） */
    private function platformMap(): array
    {
        $platforms = kernel::single('financebase_func')->getShopPlatform();
        return is_array($platforms) ? $platforms : [];
    }

    public function disableRules(array $ids): array
    {
        $isSelectedAll = (string) ($ids['isSelectedAll'] ?? '');
        $idList = $this->normalizeIdList($ids);

        if ($isSelectedAll === '_ALL_') {
            throw new \RuntimeException('不支持全选置为无效');
        }
        if (empty($idList)) {
            throw new \RuntimeException('请选择要置为无效的收支分类');
        }

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            foreach ($idList as $ruleId) {
                if (!$ruleId) {
                    continue;
                }

                $ruleInfo = $this->rulesModel->dump(['rule_id' => $ruleId], 'bill_category');
                if (!$ruleInfo) {
                    throw new \RuntimeException('收支分类不存在');
                }
                $ruleName = $ruleInfo['bill_category'];

                // 软删除：disabled=true
                if (!$this->rulesModel->update(['disabled' => 'true'], ['rule_id' => $ruleId])) {
                    throw new \RuntimeException("置为无效收支分类【{$ruleName}】失败");
                }

                $this->recordLog('delete', (int) $ruleId);
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }
        if ($ownsTransaction) {
            $this->commit();
        }

        return ['message' => '置为无效成功'];
    }

    /**
     * 批量恢复（legacy restoreRules:512-552）。
     *
     * @param array $ids rule_id 列表，或包含 ids/id 与 isSelectedAll 的输入数组
     *
     * @return array{message:string}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息=legacy 文案）
     */
    public function restoreRules(array $ids): array
    {
        $isSelectedAll = (string) ($ids['isSelectedAll'] ?? '');
        $idList = $this->normalizeIdList($ids);

        if ($isSelectedAll === '_ALL_') {
            throw new \RuntimeException('不支持全选恢复');
        }
        if (empty($idList)) {
            throw new \RuntimeException('请选择要恢复的收支分类');
        }

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            foreach ($idList as $ruleId) {
                if (!$ruleId) {
                    continue;
                }

                $ruleInfo = $this->rulesModel->dump(['rule_id' => $ruleId], 'bill_category');
                if (!$ruleInfo) {
                    throw new \RuntimeException('收支分类不存在');
                }
                $ruleName = $ruleInfo['bill_category'];

                // 恢复：disabled=false
                if (!$this->rulesModel->update(['disabled' => 'false'], ['rule_id' => $ruleId])) {
                    throw new \RuntimeException("恢复收支分类【{$ruleName}】失败");
                }

                $this->recordLog('restore', (int) $ruleId);
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }
        if ($ownsTransaction) {
            $this->commit();
        }

        return ['message' => '置为有效成功'];
    }

    /**
     * 查看历史快照（legacy show_history:402-428）。
     *
     * @return array{data:array,diff_data:array}
     *
     * @throws \RuntimeException 快照数据不存在
     */
    public function history(int $logId): array
    {
        $log = $this->snapshotModel->db_dump(['log_id' => $logId]);
        $snapshoot = is_array($log) ? (string) ($log['snapshoot'] ?? '') : '';
        $row = json_decode($snapshoot, true);

        if (!$row) {
            throw new \RuntimeException('快照数据不存在');
        }

        $diffData = [];
        if (!empty($log['updated'])) {
            $updatedData = json_decode((string) $log['updated'], true);
            $diffData = $this->compareData($row, (array) $updatedData);
        }

        return ['data' => $row, 'diff_data' => $diffData];
    }

    /**
     * 记录操作日志，编辑成功时额外写快照（legacy _recordLog:317-363）。
     */
    private function recordLog(string $action, int $ruleId, ?array $data = null, ?array $existing = null): void
    {
        switch ($action) {
            case 'add':
                $logKey = 'bill_category_rules_add@financebase';
                $memo = '新增收支分类';
                break;
            case 'edit':
                $logKey = 'bill_category_rules_edit@financebase';
                $memo = '编辑收支分类';
                break;
            case 'delete':
                $logKey = 'bill_category_rules_delete@financebase';
                $memo = '置为无效收支分类';
                break;
            case 'restore':
                $logKey = 'bill_category_rules_restore@financebase';
                $memo = '恢复收支分类';
                break;
            default:
                return;
        }

        $logId = $this->logModel->write_log($logKey, $ruleId, $memo);

        if ($logId && $action === 'edit' && $existing && $data) {
            // 会计科目 id 转显示格式后再入快照（legacy _formatAccountDisplay）
            $snapshotData = $this->formatAccountDisplay($existing);
            $updatedData = $this->formatAccountDisplay($data);

            // ⚠️ dbeav_model::insert(&$data) 形参按引用传递 ⇒ 不能传数组字面量（PHP8 Fatal）
            $snapshot = [
                'log_id'    => $logId,
                'snapshoot' => json_encode($snapshotData, JSON_UNESCAPED_UNICODE),
                'updated'   => json_encode($updatedData, JSON_UNESCAPED_UNICODE),
            ];
            $this->snapshotModel->insert($snapshot);
        }
    }

    /**
     * 格式化会计科目显示格式（legacy _formatAccountDisplay:371-397）。
     */
    private function formatAccountDisplay(array $data): array
    {
        if (empty($data['account_id_plus']) && empty($data['account_id_minus'])) {
            return $data;
        }

        $accountModel = app::get('financebase')->model('account_chart');
        $accountList = $accountModel->getList('id,account,description,postingkey', ['disabled' => 'false']);

        $accountMap = [];
        foreach ($accountList as $account) {
            $accountMap[$account['id']] = '[' . $account['account'] . '-' . $account['postingkey'] . ']' . $account['description'];
        }

        if (!empty($data['account_id_plus']) && isset($accountMap[$data['account_id_plus']])) {
            $data['account_id_plus'] = $accountMap[$data['account_id_plus']];
        }
        if (!empty($data['account_id_minus']) && isset($accountMap[$data['account_id_minus']])) {
            $data['account_id_minus'] = $accountMap[$data['account_id_minus']];
        }

        return $data;
    }

    /**
     * 对比规则数据差异（legacy _compareRuleData:433-466）。
     */
    private function compareData(array $oldData, array $newData): array
    {
        $diffData = [];
        $compareFields = ['bill_category', 'ordernum', 'account_id_plus', 'account_id_minus', 'need_ar_verify'];

        foreach ($compareFields as $field) {
            $oldValue = isset($oldData[$field]) ? $oldData[$field] : '';
            $newValue = isset($newData[$field]) ? $newData[$field] : '';

            // 特殊处理 AR 核对状态字段（legacy:445-448）
            if ($field === 'need_ar_verify') {
                $oldValue = $oldValue == 1 ? '需核对AR' : '无需核对AR';
                $newValue = $newValue == 1 ? '需核对AR' : '无需核对AR';
            }

            // 特殊处理会计科目字段（legacy:451-454）
            if (in_array($field, ['account_id_plus', 'account_id_minus'], true)) {
                $oldValue = $oldValue ?: '未设置';
                $newValue = $newValue ?: '未设置';
            }

            if ($oldValue != $newValue) {
                $diffData[$field] = [
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ];
            }
        }

        return $diffData;
    }

    /**
     * 归一化批量 id 入参：支持 id 列表，或 {ids|id,isSelectedAll} 输入数组。
     *
     * @return list<mixed>
     */
    private function normalizeIdList(array $input): array
    {
        if (array_key_exists('ids', $input)) {
            $list = $input['ids'];
        } elseif (array_key_exists('id', $input)) {
            $list = $input['id'];
        } else {
            $list = $input;
        }

        if (!is_array($list)) {
            $list = [$list];
        }

        return array_values($list);
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
            // 无活动事务时 'rollback' 为 no-op，仅用于复位 connections::_in_transaction
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