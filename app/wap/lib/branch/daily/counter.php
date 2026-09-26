<?php
/**
 * 门店仓按自然日派单量（WAP 发货单下发计数，撤销/拒单回滚）
 *
 * 缓存读写与 wap_statistic_{branch_id} 一致，统一走 cachecore::fetch / cachecore::store
 */
class wap_branch_daily_counter
{
    const TTL = 7776000; // 90 天

    /**
     * 发货单下发到门店 +1
     *
     * @param int    $branchId
     * @param string $outerDeliveryBn
     * @param int    $createTime 发货单创建时间戳，默认当前时间
     * @return int|false 当日最新计数
     */
    public function incrDispatch($branchId, $outerDeliveryBn, $createTime = 0)
    {
        $branchId = intval($branchId);
        $outerDeliveryBn = trim((string) $outerDeliveryBn);
        if ($branchId <= 0 || $outerDeliveryBn === '') {
            return false;
        }

        if ($this->fetchDispatchMark($outerDeliveryBn)) {
            return $this->getDispatchCount($branchId, $this->formatDate($createTime));
        }

        $statDate = $this->formatDate($createTime);
        $count    = $this->changeCounter($branchId, $statDate, 1);
        $this->saveDispatchMark($outerDeliveryBn, $branchId, $statDate);

        return $count;
    }

    /**
     * 发货单撤销/拒单成功 -1（按原计入日期回滚）
     *
     * @param string $outerDeliveryBn
     * @return int|false
     */
    public function decrDispatchByOuterBn($outerDeliveryBn)
    {
        $outerDeliveryBn = trim((string) $outerDeliveryBn);
        if ($outerDeliveryBn === '') {
            return false;
        }

        $mark = $this->fetchDispatchMark($outerDeliveryBn);
        if (empty($mark['branch_id']) || empty($mark['stat_date'])) {
            return false;
        }

        $count = $this->changeCounter(intval($mark['branch_id']), $mark['stat_date'], -1);
        $this->deleteDispatchMark($outerDeliveryBn);

        return $count;
    }

    /**
     * 读取门店某日派单量（KV 未初始化时一次性从 DB 回灌，之后只读缓存）
     */
    public function getDispatchCount($branchId, $date = null)
    {
        $branchId = intval($branchId);
        if ($branchId <= 0) {
            return 0;
        }

        $statDate = $date ?: date('Ymd');
        $cached   = cachecore::fetch($this->buildCountCacheKey($branchId, $statDate));

        // 缓存已存在（含合法 0）：直接返回，不打 DB
        if ($cached !== false && $cached !== null) {
            return max(0, intval($cached));
        }

        // KV 未初始化：一次性回灌（兼容功能上线前历史单）
        return $this->syncDispatchCountFromDb($branchId, $statDate);
    }

    /**
     * 批量读取门店当日派单量 branch_id => count
     *
     * @param array  $branchIds
     * @param string $date Ymd
     * @return array
     */
    public function getDispatchCounts(array $branchIds, $date = null)
    {
        $date    = $date ?: date('Ymd');
        $result  = array();
        foreach ($branchIds as $branchId) {
            $branchId = intval($branchId);
            if ($branchId <= 0) {
                continue;
            }
            $result[$branchId] = $this->getDispatchCount($branchId, $date);
        }

        return $result;
    }

    /**
     * 自动派单：在候选门店中取当日派单量最低者；相同则随机
     *
     * @param array $branchIds 已通过覆盖区域、库存等过滤的门店仓 ID
     * @return int 选中的 branch_id，无候选返回 0
     */
    public function pickBranchByLowestDispatch(array $branchIds, $date = null)
    {
        $branchIds = array_values(array_unique(array_filter(array_map('intval', $branchIds))));
        if (empty($branchIds)) {
            return 0;
        }
        if (count($branchIds) === 1) {
            return $branchIds[0];
        }

        $counts      = $this->getDispatchCounts($branchIds, $date);
        $minCount    = min($counts);
        $candidates  = array();
        foreach ($counts as $branchId => $count) {
            if ($count === $minCount) {
                $candidates[] = $branchId;
            }
        }

        if (empty($candidates)) {
            return $branchIds[0];
        }

        return $candidates[array_rand($candidates)];
    }

    /**
     * 读取门店日派单量缓存
     */
    public function fetchDispatchCount($branchId, $statDate = null)
    {
        $data = cachecore::fetch($this->buildCountCacheKey($branchId, $statDate ?: date('Ymd')));

        return ($data === false || $data === null) ? 0 : intval($data);
    }

    /**
     * 保存门店日派单量缓存
     */
    public function saveDispatchCount($branchId, $statDate, $count)
    {
        return cachecore::store(
            $this->buildCountCacheKey($branchId, $statDate),
            max(0, intval($count)),
            self::TTL
        );
    }

    /**
     * 读取发货单派单计入标记（幂等 / 回滚定位）
     */
    public function fetchDispatchMark($outerDeliveryBn)
    {
        $data = cachecore::fetch($this->buildMarkCacheKey($outerDeliveryBn));

        return is_array($data) ? $data : array();
    }

    /**
     * 保存发货单派单计入标记
     */
    public function saveDispatchMark($outerDeliveryBn, $branchId, $statDate)
    {
        return cachecore::store(
            $this->buildMarkCacheKey($outerDeliveryBn),
            array(
                'branch_id' => intval($branchId),
                'stat_date' => (string) $statDate,
            ),
            self::TTL
        );
    }

    /**
     * 删除发货单派单计入标记
     */
    public function deleteDispatchMark($outerDeliveryBn)
    {
        return cachecore::delete($this->buildMarkCacheKey($outerDeliveryBn));
    }

    protected function changeCounter($branchId, $statDate, $delta)
    {
        $count = $this->fetchDispatchCount($branchId, $statDate);
        $count = max(0, intval($count) + intval($delta));
        $this->saveDispatchCount($branchId, $statDate, $count);

        return $count;
    }

    protected function buildCountCacheKey($branchId, $statDate)
    {
        return 'wap_branch_dispatch_' . intval($branchId) . '_' . $statDate;
    }

    protected function buildMarkCacheKey($outerDeliveryBn)
    {
        return 'wap_branch_dispatch_mark_' . trim((string) $outerDeliveryBn);
    }

    protected function formatDate($timestamp = 0)
    {
        $timestamp = intval($timestamp) > 0 ? intval($timestamp) : time();

        return date('Ymd', $timestamp);
    }

    /**
     * 以 DB 为准回灌 KV 计数及派单标记（每门店每日最多执行一次）
     */
    public function syncDispatchCountFromDb($branchId, $statDate = null)
    {
        $branchId = intval($branchId);
        if ($branchId <= 0) {
            return 0;
        }

        $statDate = $statDate ?: date('Ymd');
        $range    = $this->buildDayRange($statDate);
        if (!$range) {
            return 0;
        }

        $excludeSql = kernel::single('wap_delivery')->getExcludePickupSql();
        $sql        = 'SELECT outer_delivery_bn, status FROM sdb_wap_delivery'
            . ' WHERE branch_id=' . $branchId
            . ' AND create_time>=' . intval($range['start'])
            . ' AND create_time<' . intval($range['end'])
            . " AND status != '1'"
            . " AND outer_delivery_bn != ''"
            . $excludeSql;

        $list  = kernel::database()->select($sql);
        $count = is_array($list) ? count($list) : 0;

        foreach ((array) $list as $row) {
            $outerBn = trim((string) ($row['outer_delivery_bn'] ?? ''));
            if ($outerBn === '') {
                continue;
            }
            if (!$this->fetchDispatchMark($outerBn)) {
                $this->saveDispatchMark($outerBn, $branchId, $statDate);
            }
        }

        $this->saveDispatchCount($branchId, $statDate, $count);

        return $count;
    }

    protected function buildDayRange($statDate)
    {
        $statDate = (string) $statDate;
        if (!preg_match('/^\d{8}$/', $statDate)) {
            return null;
        }

        $start = strtotime(
            substr($statDate, 0, 4) . '-'
            . substr($statDate, 4, 2) . '-'
            . substr($statDate, 6, 2) . ' 00:00:00'
        );
        if (!$start) {
            return null;
        }

        return array('start' => $start, 'end' => $start + 86400);
    }
}
