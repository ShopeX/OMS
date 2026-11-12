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
 * 会计科目设置（financebase / shop_settlement_account_chart）写能力层 —— 唯一认可的写实现。
 *
 * 链路：
 *   Vue `ctl=api_shop_settlement_account_chart&act=saveAccount|disableAccount|restoreAccount`
 *     → financebase_ctl_api_shop_settlement_account_chart → 本 capability（事务归属自动判定）
 *   legacy `financebase_ctl_admin_shop_settlement_account_chart::saveAccount()/disableAccount()/restoreAccount()`
 *     （app/financebase/controller/admin/shop/settlement/account/chart.php）
 *     → 本 capability（legacy 保持原样，两条链路各自实现）
 *
 * 校验 / 归一化 / 持久化 / 事务全部收敛在此层；控制器只做「权限注解 + 入参解析 + 响应格式化」。
 * 行为逐字对齐 legacy saveAccount:159-294 / disableAccount:297-369 / restoreAccount:372-412 /
 * show_history:468-511 / _recordLog:422-463 / _compareAccountData:516-556。
 */
class financebase_capability_shop_settlement_account_chart
{

    /** 四个应用场景（每种只能有一条） */
    private const CATEGORY_KEYS = [
        'platform_amount',
        'actually_amount',
        'refundplatform_amount',
        'refundactually_amount',
    ];

    private const CATEGORY_NAMES = [
        'platform_amount'       => '平台承担',
        'actually_amount'       => '客户实付',
        'refundplatform_amount' => '平台补贴退',
        'refundactually_amount' => '客户应退',
    ];

    /** @var object */
    private $accountModel;

    /** @var object */
    private $logModel;

    /** @var object */
    private $snapshotModel;

    /** @var object */
    private $database;

    public function __construct($accountModel = null, $logModel = null, $snapshotModel = null, $db = null)
    {
        $this->accountModel  = $accountModel ?: app::get('financebase')->model('account_chart');
        $this->logModel      = $logModel ?: app::get('ome')->model('operation_log');
        $this->snapshotModel = $snapshotModel ?: app::get('ome')->model('operation_log_snapshoot');
        $this->database      = $db ?: kernel::database();
    }

    /**
     * 表单回填数据 + 已占用的应用场景（legacy setAccount:120-154）。
     *
     * @return array{data:array,existing_categories:list<string>}
     */
    public function formData(int $id): array
    {
        $existingCategories = [];

        if ($id > 0) {
            $accountInfo = $this->accountModel->db_dump(['id' => $id]);
            if (empty($accountInfo)) {
                $accountInfo = $this->emptyForm();
            }
            $platformExists        = $this->accountModel->count(['account_category' => 'platform_amount', 'id|noequal' => $id]);
            $actuallyExists        = $this->accountModel->count(['account_category' => 'actually_amount', 'id|noequal' => $id]);
            $refundPlatformExists  = $this->accountModel->count(['account_category' => 'refundplatform_amount', 'id|noequal' => $id]);
            $refundActuallyExists  = $this->accountModel->count(['account_category' => 'refundactually_amount', 'id|noequal' => $id]);
        } else {
            $accountInfo = $this->emptyForm();
            $platformExists        = $this->accountModel->count(['account_category' => 'platform_amount']);
            $actuallyExists        = $this->accountModel->count(['account_category' => 'actually_amount']);
            $refundPlatformExists  = $this->accountModel->count(['account_category' => 'refundplatform_amount']);
            $refundActuallyExists  = $this->accountModel->count(['account_category' => 'refundactually_amount']);
        }

        if ($platformExists > 0) {
            $existingCategories[] = 'platform_amount';
        }
        if ($actuallyExists > 0) {
            $existingCategories[] = 'actually_amount';
        }
        if ($refundPlatformExists > 0) {
            $existingCategories[] = 'refundplatform_amount';
        }
        if ($refundActuallyExists > 0) {
            $existingCategories[] = 'refundactually_amount';
        }

        return ['data' => $accountInfo, 'existing_categories' => $existingCategories];
    }

    /**
     * 保存会计科目（legacy saveAccount:159-294）。
     *
     * @param array<string,mixed> $input 与 legacy $_POST 同形：id/account/customer/description/
     *                                   postingkey/costcenter/reason_code/tax_rate/tax_account/account_category
     *
     * @return array{message:string,id:int}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息=legacy 文案）
     */
    public function saveAccount(array $input): array
    {
        $isEdit = !empty($input['id']);
        if ($isEdit) {
        } else {
        }

        $data = [];
        $data['id'] = (int) ($input['id'] ?? 0);

        // 获取原始数据用于日志记录（仅编辑时需要）
        $existing = null;
        if ($isEdit) {
            $existing = $this->accountModel->dump(['id' => $data['id']]);
        }

        // 会计科目编码：新增时自动生成；编辑时若原记录无编码也生成，已有编码不改
        $needGenerateCode = !$isEdit || empty($existing['account_code']);
        if ($needGenerateCode) {
            $data['account_code'] = $this->generateAccountCode();
        }

        $data['account']     = htmlspecialchars(trim((string) ($input['account'] ?? '')));
        $data['description'] = htmlspecialchars(trim((string) ($input['description'] ?? '')));
        $data['postingkey']  = htmlspecialchars(trim((string) ($input['postingkey'] ?? '')));
        $data['customer']    = htmlspecialchars(trim((string) ($input['customer'] ?? '')));
        $data['costcenter']  = htmlspecialchars(trim((string) ($input['costcenter'] ?? '')));

        // 会计科目和客户至少填写一项
        if (empty($data['account']) && empty($data['customer'])) {
            throw new \RuntimeException('会计科目 和 客户 至少填写一项');
        }

        // 仅当 tax_rate !== '' 才写入（legacy:216-218）
        if (($input['tax_rate'] ?? null) !== '') {
            $data['tax_rate'] = $input['tax_rate'] ?? null;
        }
        $data['tax_account']      = htmlspecialchars(trim((string) ($input['tax_account'] ?? '')));
        $data['account_category'] = trim((string) ($input['account_category'] ?? ''));
        $data['reason_code']      = trim((string) ($input['reason_code'] ?? ''));

        // 税率与税率会计科目的关联关系
        if (!empty($data['tax_rate']) && $data['tax_rate'] > 0 && empty($data['tax_account'])) {
            throw new \RuntimeException('如果有税率，税率会计科目为必填项');
        }

        // 编辑态：设置了应用场景时，校验该科目未被收支分类 / 差异类型占用
        if ($isEdit && !empty($data['account_category']) && in_array($data['account_category'], self::CATEGORY_KEYS, true)) {
            $this->assertNotUsedByRulesOrGap($data['account'], $data['id']);
        }

        // 应用场景唯一性（同场景只能 1 条，编辑时排除自身）
        if (!empty($data['account_category']) && in_array($data['account_category'], self::CATEGORY_KEYS, true)) {
            $filter = ['account_category' => $data['account_category']];
            if ($data['id']) {
                $filter['id|noequal'] = $data['id'];
            }
            if ($this->accountModel->count($filter) > 0) {
                throw new \RuntimeException(self::CATEGORY_NAMES[$data['account_category']] . '只能有一条数据');
            }
        }

        $action = $isEdit ? 'edit' : 'add';

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            if (!$this->accountModel->save($data)) {
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
     * 批量置为无效（legacy disableAccount:297-369）。
     *
     * @param array $ids id 列表，或包含 ids/id 与 isSelectedAll 的输入数组
     *
     * @return array{message:string}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息=legacy 文案）
     */
    public function disableAccount(array $ids): array
    {
        $isSelectedAll = (string) ($ids['isSelectedAll'] ?? '');
        $idList = $this->normalizeIdList($ids);


        if ($isSelectedAll === '_ALL_') {
            throw new \RuntimeException('不支持全选置为无效');
        }
        if (empty($idList)) {
            throw new \RuntimeException('请选择要置为无效的会计科目');
        }

        $rulesModel = app::get('financebase')->model('bill_category_rules');
        $gapModel   = app::get('financebase')->model('gap');

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            foreach ($idList as $accountId) {
                if (!$accountId) {
                    continue;
                }

                $accountInfo = $this->accountModel->dump(['id' => $accountId], 'account');
                if (!$accountInfo) {
                    throw new \RuntimeException('会计科目不存在');
                }
                $accountName = $accountInfo['account'];

                $rulesUsed = $rulesModel->getList('bill_category', ['account_id_plus' => $accountId]);
                if (empty($rulesUsed)) {
                    $rulesUsed = $rulesModel->getList('bill_category', ['account_id_minus' => $accountId]);
                }
                if (!empty($rulesUsed)) {
                    $usedCategories = array_column($rulesUsed, 'bill_category');
                    throw new \RuntimeException("会计科目【{$accountName}】已被收支分类【" . implode('、', $usedCategories) . "】使用，无法删除");
                }

                $gapUsed = $gapModel->getList('gap_name', ['account_id_plus' => $accountId]);
                if (empty($gapUsed)) {
                    $gapUsed = $gapModel->getList('gap_name', ['account_id_minus' => $accountId]);
                }
                if (!empty($gapUsed)) {
                    $usedGaps = array_column($gapUsed, 'gap_name');
                    throw new \RuntimeException("会计科目【{$accountName}】已被差异类型【" . implode('、', $usedGaps) . "】使用，无法置为无效");
                }

                // 软删除：disabled=true
                if (!$this->accountModel->update(['disabled' => 'true'], ['id' => $accountId])) {
                    throw new \RuntimeException("置为无效会计科目【{$accountName}】失败");
                }

                $this->recordLog('delete', (int) $accountId);
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
     * 批量恢复（legacy restoreAccount:372-412）。
     *
     * @param array $ids id 列表，或包含 ids/id 与 isSelectedAll 的输入数组
     *
     * @return array{message:string}
     *
     * @throws \RuntimeException 校验失败或写入失败（消息=legacy 文案）
     */
    public function restoreAccount(array $ids): array
    {
        $isSelectedAll = (string) ($ids['isSelectedAll'] ?? '');
        $idList = $this->normalizeIdList($ids);


        if ($isSelectedAll === '_ALL_') {
            throw new \RuntimeException('不支持全选恢复');
        }
        if (empty($idList)) {
            throw new \RuntimeException('请选择要恢复的会计科目');
        }

        $ownsTransaction = $this->beginTransactionIfOwned();
        try {
            foreach ($idList as $accountId) {
                if (!$accountId) {
                    continue;
                }

                $accountInfo = $this->accountModel->dump(['id' => $accountId], 'account');
                if (!$accountInfo) {
                    throw new \RuntimeException('会计科目不存在');
                }
                $accountName = $accountInfo['account'];

                // 恢复：disabled=false
                if (!$this->accountModel->update(['disabled' => 'false'], ['id' => $accountId])) {
                    throw new \RuntimeException("恢复会计科目【{$accountName}】失败");
                }

                $this->recordLog('restore', (int) $accountId);
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
     * 查看历史快照（legacy show_history:468-511）。
     *
     * @return array{data:array,diff_data:array,existing_categories:list<string>}
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

        // 已占用的应用场景（用于显示当前状态）
        $existingCategories = [];
        $platformExists       = $this->accountModel->count(['account_category' => 'platform_amount', 'id|noequal' => $row['id']]);
        $actuallyExists       = $this->accountModel->count(['account_category' => 'actually_amount', 'id|noequal' => $row['id']]);
        $refundPlatformExists = $this->accountModel->count(['account_category' => 'refundplatform_amount', 'id|noequal' => $row['id']]);
        $refundActuallyExists = $this->accountModel->count(['account_category' => 'refundactually_amount', 'id|noequal' => $row['id']]);

        if ($platformExists > 0) {
            $existingCategories[] = 'platform_amount';
        }
        if ($actuallyExists > 0) {
            $existingCategories[] = 'actually_amount';
        }
        if ($refundPlatformExists > 0) {
            $existingCategories[] = 'refundplatform_amount';
        }
        if ($refundActuallyExists > 0) {
            $existingCategories[] = 'refundactually_amount';
        }

        return [
            'data'                => $row,
            'diff_data'           => $diffData,
            'existing_categories' => $existingCategories,
        ];
    }

    /**
     * 生成会计科目编码（legacy:186-204）。
     * 取 MAX(account_code) 数字部分 +1，格式 KJKM%03d（首条 KJKM001）。
     *
     * ⚠️ **有意偏离 legacy**：legacy 原算法只取 `disabled='false'` 的 MAX，但 `account_code` 的唯一索引覆盖全部行
     * ⇒ 只要「当前最高编码」那行被置为无效，新增就会算出同一个编码、撞唯一索引，接口只回一句「保存失败」（实测复现）。
     * 迁移口径「不复刻缺陷」⇒ 这里对**全部行**取 MAX（含已置无效行），既避免碰撞也不改变正常路径的编码序列。
     */
    private function generateAccountCode(): string
    {
        $sql = "SELECT account_code FROM sdb_financebase_account_chart ORDER BY account_code DESC";
        $lastAccount = $this->database->selectrow($sql);

        if ($lastAccount && !empty($lastAccount['account_code'])) {
            $lastNum = (int) substr((string) $lastAccount['account_code'], 4);

            return 'KJKM' . str_pad((string) ($lastNum + 1), 3, '0', STR_PAD_LEFT);
        }

        return 'KJKM001';
    }

    /**
     * 编辑态：科目被收支分类或差异类型使用时禁止设置应用场景（legacy:227-260）。
     */
    private function assertNotUsedByRulesOrGap(string $account, int $accountId): void
    {
        $rulesModel = app::get('financebase')->model('bill_category_rules');
        $rulesUsed = $rulesModel->getList('bill_category', ['account_id_plus' => $accountId]);
        if (empty($rulesUsed)) {
            $rulesUsed = $rulesModel->getList('bill_category', ['account_id_minus' => $accountId]);
        }
        if (!empty($rulesUsed)) {
            $usedCategories = array_column($rulesUsed, 'bill_category');
            throw new \RuntimeException("会计科目【{$account}】已被收支分类【" . implode('、', $usedCategories) . "】使用，不得设置应用场景");
        }

        $gapModel = app::get('financebase')->model('gap');
        $gapUsed = $gapModel->getList('gap_name', ['account_id_plus' => $accountId]);
        if (empty($gapUsed)) {
            $gapUsed = $gapModel->getList('gap_name', ['account_id_minus' => $accountId]);
        }
        if (!empty($gapUsed)) {
            $usedGaps = array_column($gapUsed, 'gap_name');
            throw new \RuntimeException("会计科目【{$account}】已被差异类型【" . implode('、', $usedGaps) . "】使用，不得设置应用场景");
        }
    }

    /**
     * 记录操作日志，编辑成功时额外写快照（legacy:422-463）。
     */
    private function recordLog(string $action, int $accountId, ?array $data = null, ?array $existing = null): void
    {
        switch ($action) {
            case 'add':
                $logKey = 'account_chart_add@financebase';
                $memo = '新增会计科目';
                break;
            case 'edit':
                $logKey = 'account_chart_edit@financebase';
                $memo = '编辑会计科目';
                break;
            case 'delete':
                $logKey = 'account_chart_delete@financebase';
                $memo = '置为无效会计科目';
                break;
            case 'restore':
                $logKey = 'account_chart_restore@financebase';
                $memo = '恢复会计科目';
                break;
            default:
                return;
        }

        $logId = $this->logModel->write_log($logKey, $accountId, $memo);

        if ($logId && $action === 'edit' && $existing && $data) {
            // ⚠️ dbeav_model::insert(&$data) 形参按引用传递 ⇒ 不能传数组字面量（PHP8 Fatal）
            $snapshot = [
                'log_id'    => $logId,
                'snapshoot' => json_encode($existing, JSON_UNESCAPED_UNICODE),
                'updated'   => json_encode($data, JSON_UNESCAPED_UNICODE),
            ];
            $this->snapshotModel->insert($snapshot);
        }
    }

    /**
     * 对比会计科目数据差异（legacy:516-556）。
     */
    private function compareData(array $oldData, array $newData): array
    {
        $diffData = [];
        $compareFields = ['account', 'description', 'postingkey', 'customer', 'costcenter', 'tax_rate', 'tax_account', 'account_category', 'reason_code'];

        foreach ($compareFields as $field) {
            $oldValue = isset($oldData[$field]) ? $oldData[$field] : '';
            $newValue = isset($newData[$field]) ? $newData[$field] : '';

            if ($field === 'account_category') {
                $oldValue = isset(self::CATEGORY_NAMES[$oldValue]) ? self::CATEGORY_NAMES[$oldValue] : ($oldValue ?: '未设置');
                $newValue = isset(self::CATEGORY_NAMES[$newValue]) ? self::CATEGORY_NAMES[$newValue] : ($newValue ?: '未设置');
            }

            if ($field === 'tax_rate') {
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
     * @return array<string,string|int>
     */
    private function emptyForm(): array
    {
        return [
            'id'               => 0,
            'account'          => '',
            'customer'         => '',
            'description'      => '',
            'postingkey'       => '',
            'costcenter'       => '',
            'reason_code'      => '',
            'tax_rate'         => '',
            'tax_account'      => '',
            'account_category' => '',
        ];
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
