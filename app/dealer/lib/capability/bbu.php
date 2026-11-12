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
 * 公司业务组织（dealer/admin_bbu）共享写能力
 *
 * 单一写实现，两条入口共用同一份逻辑：
 *  - Vue JSON 入口：dealer_ctl_api_bbu::save()（act=save，JSON body）
 *  - legacy 入口：dealer_ctl_admin_bbu::save()（表单 AJAX）
 *
 * 校验 / 落库 / 企业组织表(cos) / 操作日志 + 编辑快照 与 legacy 原实现等价
 * （app/dealer/controller/admin/bbu.php:44-111）。
 */
final class dealer_capability_bbu
{
    private object $bbuModel;

    private object $database;

    public function __construct(?object $bbuModel = null, ?object $database = null)
    {
        $this->bbuModel = $bbuModel ?? app::get('dealer')->model('bbu');
        $this->database = $database ?? kernel::database();
    }

    /**
     * 编辑回填（legacy act=edit 表单字段）
     */
    public function loadBbu(int $bbuId): array
    {
        if ($bbuId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        $row = $this->bbuModel->dump(['bbu_id' => $bbuId], '*');
        if (empty($row)) {
            throw new InvalidArgumentException('公司业务组织不存在');
        }

        return [
            'bbu_id'   => (int) $row['bbu_id'],
            'bbu_code' => (string) $row['bbu_code'],
            'bbu_name' => (string) $row['bbu_name'],
        ];
    }

    /**
     * 快照回显（legacy act=show_history）
     */
    public function loadSnapshot(int $logId): array
    {
        if ($logId <= 0) {
            throw new InvalidArgumentException('参数错误');
        }

        $snapshootModel = app::get('ome')->model('operation_log_snapshoot');
        $log            = $snapshootModel->db_dump(['log_id' => $logId]);
        $row            = is_array($log) ? json_decode((string) ($log['snapshoot'] ?? ''), true) : null;
        if (!is_array($row)) {
            throw new InvalidArgumentException('快照不存在');
        }

        return [
            'bbu_id'   => (int) ($row['bbu_id'] ?? 0),
            'bbu_code' => (string) ($row['bbu_code'] ?? ''),
            'bbu_name' => (string) ($row['bbu_name'] ?? ''),
            'status'   => (string) ($row['status'] ?? ''),
        ];
    }

    /**
     * 新建 / 编辑保存
     *
     * @param array $input          bbu_id(可空) / bbu_code / bbu_name
     * @param bool  $ownTransaction legacy 入口事务由 desktop_controller::begin() 托管，传 false
     *
     * @return array{bbu_id:int,message:string}
     */
    public function saveBbu(array $input, bool $ownTransaction = true): array
    {
        $bbuId   = (int) ($input['bbu_id'] ?? 0);
        $bbuCode = trim((string) ($input['bbu_code'] ?? ''));
        $bbuName = trim((string) ($input['bbu_name'] ?? ''));

        if ($bbuName === '') {
            throw new InvalidArgumentException('销售团队名称必填');
        }
        if ($bbuCode === '') {
            throw new InvalidArgumentException('销售团队编码必填');
        }

        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $opName = (string) ($opInfo['op_name'] ?? '');
        $data   = [
            'bbu_name' => $bbuName,
            'bbu_code' => $bbuCode,
            'status'   => 'active',
            'op_name'  => $opName,
        ];

        // 编码唯一性：等价 legacy 判据（count>1，或命中行的 bbu_id 不等于当前 bbu_id）
        $bbuList  = $this->bbuModel->getList('*', ['bbu_code' => $bbuCode]);
        $conflict = count($bbuList) > 1;
        foreach ($bbuList as $existRow) {
            if ((int) $existRow['bbu_id'] !== $bbuId) {
                $conflict = true;
                break;
            }
        }
        if ($conflict) {
            throw new InvalidArgumentException('销售团队编码已被使用');
        }

        $transaction = $ownTransaction ? $this->database->beginTransaction() : null;
        try {
            $snapshoot = [];
            if ($bbuId > 0) {
                unset($data['bbu_code']); // legacy：编码添加后不可编辑
                $this->bbuModel->update($data, ['bbu_id' => $bbuId]);
                $data['bbu_id']   = $bbuId;
                $data['bbu_code'] = (string) $bbuList[0]['bbu_code'];
                $logInfo          = '更新销售团队。';
                $operation        = 'dealer_bbu_edit@dealer';
                $snapshoot        = $bbuList[0];
            } else {
                $this->bbuModel->insert($data);
                $bbuId = (int) ($data['bbu_id'] ?? 0);
                if ($bbuId <= 0) {
                    $created = $this->bbuModel->getList('bbu_id', ['bbu_code' => $bbuCode], 0, 1);
                    $bbuId   = (int) ($created[0]['bbu_id'] ?? 0);
                }
                $logInfo   = '添加销售团队。';
                $operation = 'dealer_bbu_add@dealer';
            }

            // 企业组织表(cos)：创建/更新后回写 cos_id
            $cosId = kernel::single('organization_cos')->saveCos([
                'cos_type'  => 'bbu',
                'cos_code'  => $data['bbu_code'],
                'cos_name'  => $data['bbu_name'],
                'op_name'   => $data['op_name'],
                'parent_id' => 1,
                'is_leaf'   => '1',
            ]);
            $cosId && $this->bbuModel->update(['cos_id' => $cosId], ['bbu_id' => $bbuId]);

            // 操作日志 + 编辑快照
            $logModel = app::get('ome')->model('operation_log');
            $logId    = $logModel->write_log($operation, $bbuId, $logInfo);
            if ($logId && $snapshoot) {
                $shootMdl  = app::get('ome')->model('operation_log_snapshoot');
                $shootData = [
                    'log_id'    => $logId,
                    'snapshoot' => json_encode($snapshoot, JSON_UNESCAPED_UNICODE),
                ];
                $shootMdl->insert($shootData);
            }

            if ($ownTransaction) {
                $this->database->commit($transaction);
            }
        } catch (Throwable $exception) {
            if ($ownTransaction) {
                $this->database->rollBack($transaction);
            }
            throw $exception;
        }

        return ['bbu_id' => $bbuId, 'message' => '操作成功'];
    }
}
