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

/**
 * 失败重试共享写能力（erpapi/api_fail）
 *
 * 等价 legacy `erpapi_ctl_admin_api_fail::retry_view()` + `::retry_do()` 的实现，
 * 由 api 控制器（api_api_fail）复用；legacy 控制器冻结不动。
 *
 * 口径：
 * - 预检筛选与 legacy 完全一致：非 _ALL_ ⇒ 按 id 集合；_ALL_ ⇒ 按面板筛选字段；一律附加 status|noequal=succ。
 * - 上限 RETRY_LIMIT（legacy 硬编码 500；按业务「一次 100/1000 条」的规模放宽到 1000），超出直接拒绝，不截断。
 * - 单条重试：取到行后 status=running 先归位 fail（legacy 注释「设置为失败状态，让手工可以重试」），
 *   再交 `erpapi_autotask_task_retryapi::process()` —— 该方法自身会置 running 并可能触发 WMS 事件（真实外呼）。
 * - 不加事务：legacy 无事务，且 process() 内部含事件触发，包事务会改变语义。
 * - 分块重试（retryChunk）：片源是预检返回的 id 快照（与 legacy retry_view 的 $postIds 同口径），按片顺序处理、
 *   每条最多尝试一次。不复用「按筛选取剩余集前 N 条」的服务端分页口径：重试失败行仍留在
 *   status|noequal=succ 集合里，按剩余集取前 N 条会把永久失败行反复重试成死循环。
 */
class erpapi_capability_api_fail
{
    /** 单次批量重试上限（legacy retry_view 硬编码 500；按业务「一次 100/1000 条」放宽到 1000） */
    public const RETRY_LIMIT = 1000;

    /** 分块重试：单请求默认处理条数（每条都会真实外呼，取小值让进度条及时刷新） */
    public const RETRY_CHUNK_SIZE = 20;

    /** 分块重试：单请求墙钟预算（秒）；超出即让出，由前端携带 nextOffset 续跑 */
    public const RETRY_CHUNK_BUDGET = 20.0;

    /** _ALL_ 模式下允许透传的筛选字段（等价 legacy 直接使用 $_POST 的字段集合） */
    private const ALL_FILTER_FIELDS = ['obj_type', 'obj_bn', 'method', 'msg_id', 'status', 'err_code'];

    /**
     * 批量重试预检：解析待执行 id 列表，命中数超上限或空选时报错。
     *
     * @param array $input  {ids?: int[]|string[], isSelectedAll?: string, obj_type?: string, ...}
     * @param int   $count  出参：命中记录数
     * @param string $err   出参：拒绝原因（空串表示通过）
     *
     * @return int[] 待执行 id 列表（拒绝时为空数组）
     */
    public function collectRetryIds(array $input, int &$count, string &$err): array
    {
        $count = 0;
        $err = '';

        $isAll = (string) ($input['isSelectedAll'] ?? '') === '_ALL_';
        $ids = $this->normalizeIds($input['ids'] ?? []);

        if (!$isAll && !$ids) {
            $err = '没有选择执行的记录!';

            return [];
        }

        $filter = [];
        if (!$isAll) {
            $filter['id'] = $ids;
        } else {
            foreach (self::ALL_FILTER_FIELDS as $field) {
                $value = $input[$field] ?? '';
                if (is_scalar($value) && (string) $value !== '') {
                    $filter[$field] = (string) $value;
                }
            }
        }

        // legacy: $filter['status|noequal'] = 'succ'
        $filter['status|noequal'] = 'succ';

        $model = app::get('erpapi')->model('api_fail');
        $count = (int) $model->count($filter);

        if ($count > self::RETRY_LIMIT) {
            $err = '每次最多可执行' . self::RETRY_LIMIT . '条记录!';

            return [];
        }

        $rows = $model->getList('id', $filter);
        $result = [];
        foreach ((array) $rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $result[] = $id;
            }
        }

        $count = count($result);

        return $result;
    }

    /**
     * 单条重试（等价 legacy retry_do）。
     *
     * @param int    $id  失败记录 id
     * @param string $err 出参：失败原因（成功时置空串）
     *
     * @return array|null {succ: bool, id: int, obj_bn: string}；入参非法时返回 null
     */
    public function retryOne(int $id, string &$err): ?array
    {
        $err = '';

        if ($id <= 0) {
            $err = '没有相关记录';

            return null;
        }

        $model = app::get('erpapi')->model('api_fail');
        // legacy: filter = id + status|noequal=succ，getList('*', $filter, 0, 1)
        $rows = $model->getList('*', ['id' => $id, 'status|noequal' => 'succ'], 0, 1);
        $row = $rows[0] ?? null;

        if (empty($row)) {
            $err = '没有相关记录';

            return null;
        }

        // legacy：running 状态先归位 fail，让手工可以重试
        if ((string) ($row['status'] ?? '') === 'running') {
            $model->update(['status' => 'fail'], ['id' => $row['id']]);
        }

        $processError = '';
        $succ = (bool) kernel::single('erpapi_autotask_task_retryapi')->process($row, $processError);

        if (!$succ) {
            $err = $processError !== '' ? $processError : '重试失败';

            return ['succ' => false, 'id' => (int) $row['id'], 'obj_bn' => (string) ($row['obj_bn'] ?? '')];
        }

        return ['succ' => true, 'id' => (int) $row['id'], 'obj_bn' => (string) ($row['obj_bn'] ?? '')];
    }

    /**
     * 分块批量重试：处理 id 快照的一段，由前端循环驱动并渲染进度条（FinderBatchProgress）。
     *
     * 与 legacy 的关系：legacy retry_view 对同一份 id 快照逐条 ajax，本方法把快照切片、每片一个请求顺序执行，
     * 语义等价（每条最多尝试一次），请求数从 N 降到 N/RETRY_CHUNK_SIZE；单片超出墙钟预算即返回 nextOffset 让前端续跑。
     *
     * @param array  $ids       待重试 id 快照（预检返回的完整列表）
     * @param int    $offset    本片起始下标（前端负责切片/续跑，服务端只做边界保护）
     * @param int    $chunkSize 期望单片条数（0 = 默认；上限 RETRY_CHUNK_SIZE）
     * @param string $err       出参：拒绝原因（空串表示通过）
     *
     * @return array{processed: int, succ: int, fail: int, nextOffset: int, total: int, done: bool, details: array}
     */
    public function retryChunk(array $ids, int $offset, int $chunkSize, string &$err): array
    {
        $err = '';
        $ids = $this->normalizeIds($ids);
        $total = count($ids);

        if ($total === 0) {
            $err = '没有选择执行的记录!';

            return $this->emptyChunkResult();
        }

        if ($total > self::RETRY_LIMIT) {
            $err = '每次最多可执行' . self::RETRY_LIMIT . '条记录!';

            return $this->emptyChunkResult();
        }

        $offset = max(0, min($offset, $total));
        $size = $chunkSize > 0 ? min($chunkSize, self::RETRY_CHUNK_SIZE) : self::RETRY_CHUNK_SIZE;
        $startedAt = microtime(true);

        $processed = 0;
        $succ = 0;
        $fail = 0;
        $details = [];
        $cursor = $offset;

        while ($cursor < $total && $processed < $size) {
            $oneErr = '';
            $row = $this->retryOne((int) $ids[$cursor], $oneErr);
            $ok = is_array($row) && !empty($row['succ']);

            if ($ok) {
                $succ++;
            } else {
                $fail++;
            }

            $details[] = [
                'id' => (int) $ids[$cursor],
                'succ' => $ok,
                'message' => $ok ? '' : ($oneErr !== '' ? $oneErr : '重试失败'),
            ];

            $cursor++;
            $processed++;

            // 墙钟预算：让出请求，前端带 nextOffset 续跑（不丢行、不重复）
            if ($processed < $size && (microtime(true) - $startedAt) >= self::RETRY_CHUNK_BUDGET) {
                break;
            }
        }

        return [
            'processed' => $processed,
            'succ' => $succ,
            'fail' => $fail,
            'nextOffset' => $cursor,
            'total' => $total,
            'done' => $cursor >= $total,
            'details' => $details,
        ];
    }

    /**
     * 分块重试的空结果骨架（拒绝/空选时返回，字段齐全便于前端统一处理）。
     *
     * @return array{processed: int, succ: int, fail: int, nextOffset: int, total: int, done: bool, details: array}
     */
    private function emptyChunkResult(): array
    {
        return [
            'processed' => 0,
            'succ' => 0,
            'fail' => 0,
            'nextOffset' => 0,
            'total' => 0,
            'done' => true,
            'details' => [],
        ];
    }

    /**
     * @param mixed $ids
     *
     * @return int[]
     */
    private function normalizeIds($ids): array
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $result = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $result[] = $id;
            }
        }

        return array_values(array_unique($result));
    }
}
