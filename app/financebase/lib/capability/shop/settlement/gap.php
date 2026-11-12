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
 * 差异类型设置（financebase / shop_settlement_gap）写能力层 —— 唯一认可的写实现。
 *
 * 链路：
 *   Vue `ctl=api_shop_settlement_gap&act=saveGap|disableGap|restoreGap`
 *     → financebase_ctl_api_shop_settlement_gap → 本 capability（事务归属自动判定）
 *   legacy `financebase_ctl_admin_shop_settlement_gap::saveGap()/disableGap()/restoreGap()`
 *     （app/financebase/controller/admin/shop/settlement/gap.php）
 *     → 本 capability（legacy 保持原样，两条链路各自实现）
 *
 * 校验 / 归一化 / 持久化 / 事务全部收敛在此层；控制器只做「权限注解 + 入参解析 + 响应格式化」。
 * 行为逐字对齐 legacy saveGap:130-186 / disableGap:189-230 / restoreGap:233-274 /
 * show_history:352-... / _recordLog:284-... / _formatAccountDisplay:... / _compareGapData:...。
 */
class financebase_capability_shop_settlement_gap
{

    /** @var object */
    private $gapModel;

    /** @var object */
    private $logModel;

    /** @var object */
    private $snapshotModel;

    /** @var object */
    private $database;

    public function __construct($gapModel = null, $logModel = null, $snapshotModel = null, $db = null)
    {
        $this->gapModel      = $gapModel ?: app::get('financebase')->model('gap');
        $this->logModel      = $logModel ?: app::get('ome')->model('operation_log');
        $this->snapshotModel = $snapshotModel ?: app::get('ome')->model('operation_log_snapshoot');
        $this->database      = $db ?: kernel::database();
    }

    /**
     * 表单回填数据 + 可选会计科目（legacy setGap:104-154）。
     *
     * @return array{data:array,account_list:list<array>}
     */
    public function formData(int $id): array
    {
        $gapInfo = ['id' => $id];

        if ($id > 0) {
            $row = $this->gapModel->db_dump(['id' => $id]);
            if (!empty($row)) {
                $gapInfo = $row;
            }
        }

        // 只获取有效且排除内置特殊场景科目（legacy:139-141）
        $accountModel = app::get('financebase')->model('account_chart');
        $accountList = $accountModel->getList(
            'id,account,description,postingkey',
            ['disabled' => 'false', 'account_category' => '']
        );

        return ['data' => $gapInfo, 'account_list' => $accountList ?: []];
    }

    /**
     * 保存差异类型（legacy saveGap:130-186）。
     *
     * @param array<string,mixed> $input 与 legacy $_POST 同形：id/gap_name/account_id_plus/account_id_minus
     *
     * @return array{message:string,id:int}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息=legacy 文案）
     */
    public function saveGap(array $input): array
    {
        $isEdit = !empty($input['id']);
        if ($isEdit) {
        } else {
        }

        $data = [];
        $data['id'] = (int) ($input['id'] ?? 0);

        // legacy: htmlspecialchars($_POST['gap_name'])（不做 trim，逐字对齐）
        $data['gap_name']        = htmlspecialchars((string) ($input['gap_name'] ?? ''));
        $data['account_id_plus'] = (int) ($input['account_id_plus'] ?? 0);
        $data['account_id_minus'] = (int) ($input['account_id_minus'] ?? 0);

        // 差异类型唯一（legacy:151-154）
        if ($this->gapModel->isExist(['gap_name' => $data['gap_name']], $data['id'])) {
            throw new \RuntimeException('差异类型有重名');
        }

        // 编辑时取原始数据用于快照（legacy:157-160）
        $existing = null;
        if ($isEdit) {
            $existing = $this->gapModel->dump(['id' => $data['id']]);
        }

        $action = $isEdit ? 'edit' : 'add';

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            if (!$this->gapModel->save($data)) {
                throw new \RuntimeException('保存失败');
            }
            $data['id'] = (int) $data['id'];
            // 操作日志 + 编辑快照
            $this->recordLog($action, $data['id'], $data, $existing);
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }
        if ($ownsTransaction) {
            $this->commit();
        }

        return ['message' => '保存成功', 'id' => $data['id']];
    }

    /**
     * 批量置为无效（legacy disableGap:189-230）。
     *
     * @param array $ids id 列表，或包含 ids/id 与 isSelectedAll 的输入数组
     *
     * @return array{message:string}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息=legacy 文案）
     */
    public function disableGap(array $ids): array
    {
        $isSelectedAll = (string) ($ids['isSelectedAll'] ?? '');
        $idList = $this->normalizeIdList($ids);


        if ($isSelectedAll === '_ALL_') {
            throw new \RuntimeException('不支持全选置为无效');
        }
        if (empty($idList)) {
            throw new \RuntimeException('请选择要置为无效的差异类型');
        }

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            foreach ($idList as $gapId) {
                if (!$gapId) {
                    continue;
                }

                $gapInfo = $this->gapModel->dump(['id' => $gapId], 'gap_name');
                if (!$gapInfo) {
                    throw new \RuntimeException('差异类型不存在');
                }
                $gapName = $gapInfo['gap_name'];

                // 软删除：status=0
                if (!$this->gapModel->update(['status' => '0'], ['id' => $gapId])) {
                    throw new \RuntimeException("置为无效差异类型【{$gapName}】失败");
                }

                $this->recordLog('delete', (int) $gapId);
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
     * 批量恢复（legacy restoreGap:233-274）。
     *
     * @param array $ids id 列表，或包含 ids/id 与 isSelectedAll 的输入数组
     *
     * @return array{message:string}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息=legacy 文案）
     */
    public function restoreGap(array $ids): array
    {
        $isSelectedAll = (string) ($ids['isSelectedAll'] ?? '');
        $idList = $this->normalizeIdList($ids);


        if ($isSelectedAll === '_ALL_') {
            throw new \RuntimeException('不支持全选恢复');
        }
        if (empty($idList)) {
            throw new \RuntimeException('请选择要恢复的差异类型');
        }

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            foreach ($idList as $gapId) {
                if (!$gapId) {
                    continue;
                }

                $gapInfo = $this->gapModel->dump(['id' => $gapId], 'gap_name');
                if (!$gapInfo) {
                    throw new \RuntimeException('差异类型不存在');
                }
                $gapName = $gapInfo['gap_name'];

                // 恢复：status=1
                if (!$this->gapModel->update(['status' => '1'], ['id' => $gapId])) {
                    throw new \RuntimeException("恢复差异类型【{$gapName}】失败");
                }

                $this->recordLog('restore', (int) $gapId);
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
     * 查看历史快照（legacy show_history）。
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
     * 记录操作日志，编辑成功时额外写快照（legacy _recordLog:284-...）。
     */
    private function recordLog(string $action, int $gapId, ?array $data = null, ?array $existing = null): void
    {
        switch ($action) {
            case 'add':
                $logKey = 'gap_add@financebase';
                $memo = '新增差异类型';
                break;
            case 'edit':
                $logKey = 'gap_edit@financebase';
                $memo = '编辑差异类型';
                break;
            case 'delete':
                $logKey = 'gap_delete@financebase';
                $memo = '置为无效差异类型';
                break;
            case 'restore':
                $logKey = 'gap_restore@financebase';
                $memo = '恢复差异类型';
                break;
            default:
                return;
        }

        $logId = $this->logModel->write_log($logKey, $gapId, $memo);

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
     * 格式化会计科目显示格式（legacy _formatAccountDisplay）。
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
     * 对比差异类型数据差异（legacy _compareGapData）。
     */
    private function compareData(array $oldData, array $newData): array
    {
        $diffData = [];
        $compareFields = ['gap_name', 'account_id_plus', 'account_id_minus'];

        foreach ($compareFields as $field) {
            $oldValue = isset($oldData[$field]) ? $oldData[$field] : '';
            $newValue = isset($newData[$field]) ? $newData[$field] : '';

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
