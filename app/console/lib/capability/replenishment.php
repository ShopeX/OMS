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

class console_capability_replenishment
{
    private object $branchProductModel;
    private object $branchModel;

    public function __construct(
        ?object $branchProductModel = null,
        ?object $branchModel = null
    ) {
        $this->branchProductModel = $branchProductModel ?? app::get('ome')->model('branch_product');
        $this->branchModel = $branchModel ?? app::get('ome')->model('branch');
    }

    public function recommend(array $request): array
    {
        $branchId = (int) ($request['branch_id'] ?? 0);
        $scoped = $branchId >= 1;

        $materialBns = $request['material_bns'] ?? null;
        if ($materialBns !== null) {
            if (!is_array($materialBns)) {
                throw new InvalidArgumentException('material_bns 必须是数组');
            }
            if (count($materialBns) > 100) {
                throw new InvalidArgumentException('material_bns 最多支持 100 个物料');
            }
            $materialBns = array_values(array_unique(array_map('strval', $materialBns)));
            if ($materialBns === []) {
                throw new InvalidArgumentException('material_bns 必须包含有效的物料编码');
            }
        }

        $timeFrom = (string) ($request['time_from'] ?? '');
        $timeTo = (string) ($request['time_to'] ?? '');
        if ($timeFrom === '' && $timeTo === '') {
            // 默认窗口含今天（time_to = 今天），往前推 13 天共 14 天，避免漏掉当天销售
            $timeTo = date('Y-m-d');
            $timeFrom = date('Y-m-d', strtotime('-13 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $timeFrom)) {
            throw new InvalidArgumentException('time_from 格式必须为 Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $timeTo)) {
            throw new InvalidArgumentException('time_to 格式必须为 Y-m-d');
        }
        $fromTs = strtotime($timeFrom);
        // 时间窗上界补到当天 23:59:59，确保 time_to 当天完整计入（与销售明细统计口径一致）
        $toTs = strtotime($timeTo . ' 23:59:59');
        if ($fromTs === false || $toTs === false) {
            throw new InvalidArgumentException('日期格式无效');
        }
        if ($fromTs > $toTs) {
            throw new InvalidArgumentException('time_from 不能晚于 time_to');
        }
        $effectiveDays = (int) (($toTs - $fromTs) / 86400) + 1;
        if ($effectiveDays > 31) {
            throw new InvalidArgumentException('最多支持 31 天历史范围');
        }

        $branchIds = $this->resolveBranchIds($scoped, $branchId);

        if ($scoped) {
            $branchTypeRow = $this->branchModel->dump(['branch_id' => $branchId, 'check_permission' => 'false'], 'b_type');
            if (is_array($branchTypeRow) && (int) ($branchTypeRow['b_type'] ?? 0) !== 1) {
                return $this->emptyResponse($branchId, '', $timeFrom, $timeTo, true);
            }
        }

        $branchRow = $scoped ? $this->branchModel->dump(['branch_id' => $branchId, 'check_permission' => 'false'], 'name,branch_bn') : [];
        $branchName = is_array($branchRow) ? ((string) ($branchRow['name'] ?? $branchRow['branch_bn'] ?? '')) : '';

        $lowStockRows = $this->fetchLowStock($branchIds, $materialBns);
        $rows = $scoped ? $lowStockRows : $this->aggregateStock($lowStockRows);
        if ($rows === []) {
            return $this->emptyResponse($branchId, $branchName, $timeFrom, $timeTo, $scoped);
        }

        $productIds = array_column($rows, 'product_id');
        $salesMap = $this->fetchBulkSales($productIds, $fromTs, $toTs, $branchIds);

        $lines = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $materialBn = (string) ($row['material_bn'] ?? '');
            $store = (int) ($row['store'] ?? 0);
            $storeFreeze = (int) ($row['store_freeze'] ?? 0);
            $safeStore = (int) ($row['safe_store'] ?? 0);
            $available = $store - $storeFreeze;
            $sales = $salesMap[$productId] ?? ['total_nums' => 0, 'day_count' => 0];
            $dailySales = $sales['day_count'] > 0 ? round($sales['total_nums'] / $sales['day_count'], 2) : 0.0;
            $daysRemaining = $dailySales > 0 ? round($available / $dailySales, 1) : ($available > 0 ? (float) $effectiveDays : 0.0);

            $lines[] = [
                'product_id' => $productId,
                'material_bn' => $materialBn,
                'store' => $store,
                'store_freeze' => $storeFreeze,
                'safe_store' => $safeStore,
                'available' => $available,
                'daily_sales' => $dailySales,
                'days_remaining' => $daysRemaining,
            ];
        }

        return [
            'recommendation_id' => 'REPL-' . bin2hex(random_bytes(8)),
            'generated_at' => date('c'),
            'branch_id' => $scoped ? $branchId : 0,
            'branch_name' => $branchName,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'total_low_stock' => count($lines),
            'scope' => $scoped ? 'scoped' : 'aggregated',
            'purchase_in_branches' => $this->fetchPurchaseInBranches(),
            'lines' => $lines,
        ];
    }

    private function resolveBranchIds(bool $scoped, int $branchId): array
    {
        if ($scoped) {
            return [$branchId];
        }
        $rows = $this->branchModel->getList('branch_id', ['type' => 'main', 'b_type' => 1, 'check_permission' => 'false'], 0, -1);
        return is_array($rows) ? array_map('intval', array_column($rows, 'branch_id')) : [];
    }

    private function fetchPurchaseInBranches(): array
    {
        $flow = app::get('ome')->model('branch_flow')->dump(['flow_type' => 'purchasein', 'check_permission' => 'false']);
        if (!is_array($flow) || empty($flow['content'])) {
            return [];
        }
        $content = json_decode($flow['content'], true);
        $ids = is_array($content) ? ($content['branch_id'] ?? []) : [];
        if (!is_array($ids) || $ids === []) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $branches = $this->branchModel->getList('branch_id,name,branch_bn', ['branch_id' => $ids, 'check_permission' => 'false'], 0, -1);
        if (!is_array($branches)) {
            return [];
        }
        return array_map(static fn(array $b): array => [
            'branch_id' => (int) $b['branch_id'],
            'name' => (string) ($b['name'] ?? ''),
            'branch_bn' => (string) ($b['branch_bn'] ?? ''),
        ], $branches);
    }

    private function fetchLowStock(array $branchIds, ?array $materialBns): array
    {
        if ($branchIds === []) {
            return [];
        }
        $ids = implode(',', array_map('intval', $branchIds));
        $sql = 'SELECT bp.product_id, a.material_bn, bp.branch_id, bp.store, bp.store_freeze, bp.safe_store, bp.arrive_store '
             . 'FROM sdb_ome_branch_product bp '
             . 'LEFT JOIN sdb_material_basic_material a ON bp.product_id = a.bm_id '
             . 'LEFT JOIN sdb_ome_branch b ON bp.branch_id = b.branch_id '
             . 'WHERE a.material_bn != \'\' AND b.b_type = 1 '
             . 'AND bp.branch_id IN (' . $ids . ') '
             . 'AND (bp.store - bp.store_freeze) < bp.safe_store AND bp.safe_store > 0';

        if ($materialBns !== null) {
            $escaped = array_map(function (string $bn): string {
                return '\'' . addslashes($bn) . '\'';
            }, $materialBns);
            $sql .= ' AND a.material_bn IN (' . implode(',', $escaped) . ')';
        }

        $rows = $this->branchProductModel->db->select($sql);

        return is_array($rows) ? $rows : [];
    }

    private function aggregateStock(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            if (!isset($map[$productId])) {
                $map[$productId] = [
                    'product_id' => $productId,
                    'material_bn' => (string) ($row['material_bn'] ?? ''),
                    'store' => 0,
                    'store_freeze' => 0,
                    'safe_store' => 0,
                ];
            }
            $map[$productId]['store'] += (int) ($row['store'] ?? 0);
            $map[$productId]['store_freeze'] += (int) ($row['store_freeze'] ?? 0);
            $map[$productId]['safe_store'] += (int) ($row['safe_store'] ?? 0);
        }
        return array_values($map);
    }

    private function fetchBulkSales(array $productIds, int $fromTs, int $toTs, array $branchIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $chunks = array_chunk($productIds, 500);
        $map = [];

        foreach ($chunks as $chunk) {
            $ids = implode(',', array_map('intval', $chunk));
            $branchFilter = $branchIds === [] ? '' : ' AND SI.branch_id IN (' . implode(',', array_map('intval', $branchIds)) . ')';
            $sql = 'SELECT SI.product_id AS bm_id, SUM(SI.nums) AS total_nums, '
                 . 'COUNT(DISTINCT DATE(FROM_UNIXTIME(S.paytime))) AS day_count '
                 . 'FROM sdb_ome_sales_items SI '
                 . 'LEFT JOIN sdb_ome_sales S ON SI.sale_id = S.sale_id '
                 . 'WHERE SI.product_id IN (' . $ids . ') '
                 . 'AND S.paytime >= ' . $fromTs . ' AND S.paytime <= ' . $toTs
                 . ' AND SI.nums > 0 '
                 . $branchFilter
                 . ' GROUP BY SI.product_id';

            $rows = $this->branchProductModel->db->select($sql);
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $map[(int) $row['bm_id']] = [
                        'total_nums' => (int) ($row['total_nums'] ?? 0),
                        'day_count' => (int) ($row['day_count'] ?? 0),
                    ];
                }
            }
        }

        return $map;
    }

    private function emptyResponse(int $branchId, string $branchName, string $timeFrom, string $timeTo, bool $scoped): array
    {
        return [
            'recommendation_id' => 'REPL-' . bin2hex(random_bytes(8)),
            'generated_at' => date('c'),
            'branch_id' => $scoped ? $branchId : 0,
            'branch_name' => $branchName,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'total_low_stock' => 0,
            'scope' => $scoped ? 'scoped' : 'aggregated',
            'purchase_in_branches' => $this->fetchPurchaseInBranches(),
            'lines' => [],
        ];
    }
}
