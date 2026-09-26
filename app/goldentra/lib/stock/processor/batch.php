<?php

/**
 * 全量批次 SQL 批量比对（百万级）
 */
class goldentra_stock_processor_batch
{
    public function processFullBatch($sync_batch_no)
    {
        $mcLib = kernel::single('goldentra_stock_machinecenter');
        if (!$mcLib->isInstalled()) {
            throw new Exception('机器中心未安装，无法比对库存');
        }

        @ini_set('memory_limit', '512M');
        set_time_limit(0);

        $db = kernel::database();
        $batch_esc = kernel::single('goldentra_db')->escape($sync_batch_no, $db);
        $now = time();

        $row = $db->selectrow(
            "SELECT snapshot_date, COUNT(*) AS cnt FROM sdb_goldentra_stock
            WHERE sync_batch_no='{$batch_esc}' AND stock_type='full'"
        );
        if (empty($row['cnt'])) {
            return array(
                'sync_batch_no' => $sync_batch_no,
                'processed' => 0,
                'message' => '批次不存在或非全量批次',
                'succ' => 0,
                'fail' => 0,
                'skip' => 0,
            );
        }
        $snapshot_date = $row['snapshot_date'];
        $snap_esc = kernel::single('goldentra_db')->escape($snapshot_date, $db);

        $db->exec("UPDATE sdb_goldentra_stock SET process_status='running', up_time={$now}
            WHERE sync_batch_no='{$batch_esc}' AND process_status IN ('pending','fail')");

        $this->buildLatestSnapshotTemp($db, $batch_esc);
        $this->skipExpiredByTemp($db, $batch_esc, $now);
        $this->failUnmatchedMaterial($db, $batch_esc, $now);
        $this->failUnknownBranch($db, $batch_esc, $now);
        $this->bulkCompare($db, $batch_esc, $now);
        $version_time = kernel::single('goldentra_stock_machinecenter')->todayVersionTime();
        kernel::single('goldentra_stock_machinecenter')->touchVersionTimeBatch($db, $batch_esc, $version_time);
        $this->zeroOutStaleByVersion($db, $batch_esc, $snap_esc, $snapshot_date, $version_time, $now);
        // 电商仓：差异只进差异表，不出调整单
        $this->saveEcWarehouseDiffs($db, $batch_esc, $snapshot_date, $now);
        $this->createDiffAdjustments($db, $batch_esc, $now);
        $this->markDiffAdjustments($db, $batch_esc, $now);

        $stats = $db->selectrow(
            "SELECT
                SUM(process_status='succ') AS succ,
                SUM(process_status='fail') AS fail,
                SUM(process_status='skip') AS skip
            FROM sdb_goldentra_stock WHERE sync_batch_no='{$batch_esc}'"
        );

        return array(
            'sync_batch_no' => $sync_batch_no,
            'snapshot_date' => $snapshot_date,
            'succ' => (int) $stats['succ'],
            'fail' => (int) $stats['fail'],
            'skip' => (int) $stats['skip'],
        );
    }

    public function findPendingFullBatch()
    {
        $db = kernel::database();
        $row = $db->selectrow(
            "SELECT sync_batch_no FROM sdb_goldentra_stock
            WHERE stock_type='full' AND process_status IN ('pending','running')
            ORDER BY stock_id ASC"
        );
        return !empty($row['sync_batch_no']) ? $row['sync_batch_no'] : '';
    }

    public function hasFullBatch($sync_batch_no)
    {
        $sync_batch_no = trim((string) $sync_batch_no);
        if ($sync_batch_no === '') {
            return false;
        }
        $db = kernel::database();
        $batch_esc = kernel::single('goldentra_db')->escape($sync_batch_no, $db);
        $row = $db->selectrow(
            "SELECT stock_id FROM sdb_goldentra_stock
            WHERE sync_batch_no='{$batch_esc}' AND stock_type='full'"
        );
        return !empty($row['stock_id']);
    }

    private function buildLatestSnapshotTemp($db, $batch_esc)
    {
        $status_in = "'" . implode("','", goldentra_stock::$processed_status) . "'";
        $db->exec('DROP TEMPORARY TABLE IF EXISTS tmp_goldentra_stock_latest');
        $db->exec(
            "CREATE TEMPORARY TABLE tmp_goldentra_stock_latest (
                plant VARCHAR(30) NOT NULL DEFAULT '',
                branch_bn VARCHAR(32) NOT NULL,
                unique_code VARCHAR(30) NOT NULL,
                latest VARCHAR(19) NOT NULL,
                PRIMARY KEY (plant, branch_bn, unique_code)
            )"
        );
        $db->exec(
            "INSERT INTO tmp_goldentra_stock_latest (plant, branch_bn, unique_code, latest)
            SELECT cur.plant, cur.branch_bn, cur.unique_code, MAX(hist.snapshot_date) AS latest
            FROM sdb_goldentra_stock cur
            INNER JOIN sdb_goldentra_stock hist
                ON hist.plant = cur.plant
                AND hist.branch_bn = cur.branch_bn
                AND hist.unique_code = cur.unique_code
                AND hist.process_status IN ({$status_in})
            WHERE cur.sync_batch_no='{$batch_esc}'
            GROUP BY cur.plant, cur.branch_bn, cur.unique_code"
        );
    }

    private function skipExpiredByTemp($db, $batch_esc, $now)
    {
        $db->exec(
            "UPDATE sdb_goldentra_stock s
            INNER JOIN tmp_goldentra_stock_latest t
                ON t.plant = s.plant AND t.branch_bn = s.branch_bn AND t.unique_code = s.unique_code
            SET s.process_status='skip',
                s.process_msg=CONCAT('快照过期 ', s.snapshot_date, ' < ', t.latest),
                s.process_time={$now}, s.up_time={$now}
            WHERE s.sync_batch_no='{$batch_esc}' AND s.process_status='running'
            AND s.snapshot_date < t.latest"
        );
    }

    private function failUnmatchedMaterial($db, $batch_esc, $now)
    {
        $db->exec(
            "UPDATE sdb_goldentra_stock s
            LEFT JOIN sdb_goldentra_material m
                ON m.unique_code = s.unique_code AND m.match_status='1' AND m.bn <> ''
            SET s.process_status='fail',
                s.process_msg=CONCAT('主档未匹配:', s.unique_code),
                s.process_time={$now}, s.up_time={$now}
            WHERE s.sync_batch_no='{$batch_esc}' AND s.process_status='running'
            AND m.material_id IS NULL"
        );
    }

    /**
     * 仓库匹配：优先 plant，其次 plant_库位，无 plant 时回退库位
     */
    private function branchJoinSql($stockAlias = 's')
    {
        return "
            LEFT JOIN sdb_ome_branch b_plant
                ON {$stockAlias}.plant <> '' AND b_plant.branch_bn = {$stockAlias}.plant
            LEFT JOIN sdb_ome_branch b_combo
                ON {$stockAlias}.plant <> '' AND b_plant.branch_id IS NULL
                AND b_combo.branch_bn = CONCAT({$stockAlias}.plant, '_', {$stockAlias}.branch_bn)
            LEFT JOIN sdb_ome_branch b_loc
                ON {$stockAlias}.plant = '' AND b_loc.branch_bn = {$stockAlias}.branch_bn
        ";
    }

    private function branchIdExpr()
    {
        return 'COALESCE(b_plant.branch_id, b_combo.branch_id, b_loc.branch_id)';
    }

    private function branchBnExpr()
    {
        return 'COALESCE(b_plant.branch_bn, b_combo.branch_bn, b_loc.branch_bn)';
    }

    private function failUnknownBranch($db, $batch_esc, $now)
    {
        $join = $this->branchJoinSql('s');
        $branchId = $this->branchIdExpr();
        $db->exec(
            "UPDATE sdb_goldentra_stock s
            {$join}
            SET s.process_status='fail',
                s.process_msg=IF(
                    s.plant <> '',
                    CONCAT('仓库不存在: plant=', s.plant, ' 或 ', s.plant, '_', s.branch_bn),
                    CONCAT('仓库不存在: ', s.branch_bn)
                ),
                s.process_time={$now}, s.up_time={$now}
            WHERE s.sync_batch_no='{$batch_esc}' AND s.process_status='running'
            AND {$branchId} IS NULL"
        );
    }

    private function bulkCompare($db, $batch_esc, $now)
    {
        $mcLib = kernel::single('goldentra_stock_machinecenter');
        $in_stock = $mcLib->sqlInStockStatuses();
        $out_stock = goldentra_stock_machinecenter::OUT_STOCK_STATUS;
        $join = $this->branchJoinSql('s');
        $branchId = $this->branchIdExpr();
        $db->exec(
            "UPDATE sdb_goldentra_stock s
            {$join}
            INNER JOIN sdb_goldentra_material m
                ON m.unique_code = s.unique_code AND m.match_status='1' AND m.bn <> ''
            LEFT JOIN sdb_machinecenter_material_sn mc
                ON mc.branch_id = {$branchId} AND mc.sn = s.unique_code
            SET
                s.oms_qty = IF(mc.status IN ({$in_stock}), 1, 0),
                s.diff_qty = s.qty - IF(mc.status IN ({$in_stock}), 1, 0),
                s.process_status = CASE
                    WHEN s.qty - IF(mc.status IN ({$in_stock}), 1, 0) = 0 THEN 'succ'
                    WHEN mc.status = '{$out_stock}' AND s.qty - IF(mc.status IN ({$in_stock}), 1, 0) < 0 THEN 'fail'
                    ELSE 'succ'
                END,
                s.process_msg = CASE
                    WHEN s.qty - IF(mc.status IN ({$in_stock}), 1, 0) = 0 THEN '库存一致'
                    WHEN mc.status = '{$out_stock}' AND s.qty - IF(mc.status IN ({$in_stock}), 1, 0) < 0 THEN '唯一码已出库，禁止调整'
                    ELSE s.process_msg
                END,
                s.process_time = {$now},
                s.up_time = {$now}
            WHERE s.sync_batch_no='{$batch_esc}' AND s.process_status='running'
            AND {$branchId} IS NOT NULL"
        );
    }

    private function markDiffAdjustments($db, $batch_esc, $now)
    {
        $db->exec(
            "UPDATE sdb_goldentra_stock
            SET process_msg = CONCAT(
                '待对接机器中心调整 diff=', diff_qty,
                ' plant=', plant, ' 库位=', branch_bn, ' 码=', unique_code
            ),
            up_time = {$now}
            WHERE sync_batch_no='{$batch_esc}'
            AND process_status='succ' AND diff_qty <> 0 AND process_msg = ''"
        );
    }

    /**
     * 电商仓全量差异 → stock_diff；同步行标 skip，避免后续出调整单
     */
    private function saveEcWarehouseDiffs($db, $batch_esc, $snapshot_date, $now)
    {
        $ecIdsSql = kernel::single('goldentra_ecwarehouse')->ecBranchIdsSqlIn();
        if ($ecIdsSql === '') {
            return;
        }

        $diffLib = kernel::single('goldentra_stock_diff');
        $diffLib->deleteByBatch($batch_esc);

        $join = $this->branchJoinSql('s');
        $branchId = $this->branchIdExpr();
        $branchBn = $this->branchBnExpr();
        $rows = $db->select(
            "SELECT s.stock_id, s.sync_batch_no, s.snapshot_date, s.plant, s.branch_bn AS location_bn,
                s.unique_code, s.mold_code, s.qty AS sap_qty, s.oms_qty, s.diff_qty,
                {$branchId} AS branch_id, {$branchBn} AS branch_bn,
                COALESCE(b_plant.name, b_combo.name, b_loc.name) AS branch_name,
                m.bn
            FROM sdb_goldentra_stock s
            {$join}
            LEFT JOIN sdb_goldentra_material m ON m.unique_code = s.unique_code
            WHERE s.sync_batch_no='{$batch_esc}'
            AND s.process_status='succ' AND s.diff_qty <> 0
            AND {$branchId} IS NOT NULL
            AND {$branchId} IN ({$ecIdsSql})"
        );
        if (empty($rows)) {
            return;
        }

        $inserts = array();
        $stockIds = array();
        foreach ($rows as $row) {
            $stockIds[] = (int) $row['stock_id'];
            $inserts[] = array(
                'sync_batch_no' => $row['sync_batch_no'],
                'snapshot_date' => $row['snapshot_date'] !== '' ? $row['snapshot_date'] : $snapshot_date,
                'branch_id' => (int) $row['branch_id'],
                'branch_bn' => (string) $row['branch_bn'],
                'branch_name' => isset($row['branch_name']) ? (string) $row['branch_name'] : '',
                'plant' => isset($row['plant']) ? (string) $row['plant'] : '',
                'location_bn' => isset($row['location_bn']) ? (string) $row['location_bn'] : '',
                'unique_code' => (string) $row['unique_code'],
                'bn' => isset($row['bn']) ? (string) $row['bn'] : '',
                'mold_code' => isset($row['mold_code']) ? (string) $row['mold_code'] : '',
                'sap_qty' => $row['sap_qty'],
                'oms_qty' => $row['oms_qty'],
                'diff_qty' => $row['diff_qty'],
                'diff_type' => $diffLib->classifyDiffType($row['sap_qty'], $row['oms_qty']),
                'stock_id' => (int) $row['stock_id'],
                'at_time' => $now,
            );
        }
        $diffLib->insertRows($inserts);

        $msg = kernel::single('goldentra_db')->escape(goldentra_stock_diff::MSG_EC_FULL_DIFF, $db);
        $idList = implode(',', array_map('intval', $stockIds));
        if ($idList !== '') {
            $db->exec(
                "UPDATE sdb_goldentra_stock
                SET process_status='skip', process_msg='{$msg}', process_time={$now}, up_time={$now}
                WHERE stock_id IN ({$idList})"
            );
        }
    }

    private function createDiffAdjustments($db, $batch_esc, $now)
    {
        $join = $this->branchJoinSql('s');
        $branchId = $this->branchIdExpr();
        $branchBn = $this->branchBnExpr();
        $ecIdsSql = kernel::single('goldentra_ecwarehouse')->ecBranchIdsSqlIn();
        $ecExclude = $ecIdsSql !== ''
            ? " AND {$branchId} NOT IN ({$ecIdsSql})"
            : '';
        $rows = $db->select(
            "SELECT s.stock_id, {$branchBn} AS branch_bn, {$branchId} AS branch_id,
                s.plant, s.branch_bn AS location_bn, s.unique_code, s.diff_qty,
                s.snapshot_date, s.sync_batch_no, m.bn
            FROM sdb_goldentra_stock s
            {$join}
            INNER JOIN sdb_goldentra_material m
                ON m.unique_code = s.unique_code AND m.match_status='1' AND m.bn <> ''
            WHERE s.sync_batch_no='{$batch_esc}'
            AND s.process_status='succ' AND s.diff_qty <> 0 AND s.process_msg = ''
            AND {$branchId} IS NOT NULL
            {$ecExclude}"
        );
        if (empty($rows)) {
            return;
        }

        $stockLib = kernel::single('goldentra_stock');
        $adjustment = kernel::single('goldentra_stock_adjustment');
        try {
            $results = $adjustment->createFromDiffBatch($rows);
        } catch (Exception $e) {
            // 未预期异常：按行记同一系统错误，避免静默丢结果
            foreach ($rows as $row) {
                $stockLib->markFail(
                    $row['stock_id'],
                    '库存调整异常: ' . $e->getMessage(),
                    null,
                    $row['diff_qty']
                );
            }
            return;
        }

        $msgByStockId = $adjustment->buildStockMessageMap($results);
        $failByStockId = $adjustment->buildStockFailMap($results);
        foreach ($rows as $row) {
            $stock_id = (int) $row['stock_id'];
            if (isset($failByStockId[$stock_id])) {
                $stockLib->markFail($stock_id, $failByStockId[$stock_id], null, $row['diff_qty']);
                continue;
            }
            $msg = isset($msgByStockId[$stock_id])
                ? $msgByStockId[$stock_id]
                : '已生成批次调整单';
            $msg_esc = kernel::single('goldentra_db')->escape($msg, $db);
            $db->exec(
                "UPDATE sdb_goldentra_stock
                SET process_msg='{$msg_esc}', up_time={$now}
                WHERE stock_id={$stock_id}"
            );
        }
    }

    /**
     * 全量反向：本批次未打 version_time 的在库 SN → diff=-1
     */
    private function zeroOutStaleByVersion($db, $batch_esc, $snap_esc, $snapshot_date, $version_time, $now)
    {
        $pairs = $db->select(
            "SELECT DISTINCT plant, branch_bn FROM sdb_goldentra_stock
            WHERE sync_batch_no='{$batch_esc}' AND stock_type='full'"
        );
        if (empty($pairs)) {
            return;
        }

        $stockLib = kernel::single('goldentra_stock');
        $oms_branch_bns = array();
        foreach ($pairs as $pair) {
            $branch = $stockLib->resolveBranch(
                isset($pair['plant']) ? $pair['plant'] : '',
                $pair['branch_bn']
            );
            if (!empty($branch['branch_bn'])) {
                $oms_branch_bns[$branch['branch_bn']] = $branch['branch_bn'];
            }
        }
        $oms_branch_bns = array_values($oms_branch_bns);
        if (empty($oms_branch_bns)) {
            return;
        }

        $mcLib = kernel::single('goldentra_stock_machinecenter');
        $staleRows = $mcLib->findStaleInStockByBranches($db, $oms_branch_bns, $version_time);
        if (empty($staleRows)) {
            return;
        }

        foreach ($staleRows as $row) {
            $branch_bn = kernel::single('goldentra_db')->escape($row['branch_bn'], $db);
            $unique_code = kernel::single('goldentra_db')->escape($row['unique_code'], $db);
            if (!$this->isLatestFullSnapshotForOmsBranch($db, $row['branch_bn'], $snapshot_date, $pairs, $stockLib)) {
                continue;
            }
            $exist = $db->selectrow(
                "SELECT stock_id FROM sdb_goldentra_stock
                WHERE sync_batch_no='{$batch_esc}' AND unique_code='{$unique_code}'
                AND snapshot_date='{$snap_esc}'
                AND (
                    plant='{$branch_bn}'
                    OR (plant <> '' AND CONCAT(plant, '_', branch_bn)='{$branch_bn}')
                    OR (plant='' AND branch_bn='{$branch_bn}')
                )"
            );
            if (!empty($exist['stock_id'])) {
                continue;
            }
            $mold_code = '';
            $mat = $db->selectrow(
                "SELECT mold_code FROM sdb_goldentra_material WHERE unique_code='{$unique_code}'"
            );
            if (!empty($mat['mold_code'])) {
                $mold_code = kernel::single('goldentra_db')->escape($mat['mold_code'], $db);
            }
            $db->exec(
                "INSERT INTO sdb_goldentra_stock (
                    sync_batch_no, stock_type, snapshot_date, plant, branch_bn, unique_code, mold_code,
                    qty, raw_qty, raw_unit, oms_qty, diff_qty, process_status, process_msg, process_time, at_time, up_time
                ) VALUES (
                    '{$batch_esc}', 'full', '{$snap_esc}', '{$branch_bn}', '', '{$unique_code}', '{$mold_code}',
                    0, 0, '', 1, -1, 'succ', '全量比对未打标需清零', {$now}, {$now}, {$now}
                )"
            );
        }
    }

    private function isLatestFullSnapshotForOmsBranch($db, $oms_branch_bn, $snapshot_date, array $pairs, $stockLib)
    {
        $related = false;
        foreach ($pairs as $pair) {
            $branch = $stockLib->resolveBranch(
                isset($pair['plant']) ? $pair['plant'] : '',
                $pair['branch_bn']
            );
            if (!empty($branch['branch_bn']) && $branch['branch_bn'] === $oms_branch_bn) {
                $related = true;
                break;
            }
        }
        if (!$related) {
            return false;
        }

        $oms_esc = kernel::single('goldentra_db')->escape($oms_branch_bn, $db);
        $row = $db->selectrow(
            "SELECT MAX(snapshot_date) AS latest FROM sdb_goldentra_stock
            WHERE stock_type='full' AND process_status='succ'
            AND (
                plant='{$oms_esc}'
                OR (plant <> '' AND CONCAT(plant, '_', branch_bn)='{$oms_esc}')
                OR (plant='' AND branch_bn='{$oms_esc}')
            )"
        );
        if (empty($row['latest'])) {
            return true;
        }
        return $snapshot_date >= $row['latest'];
    }
}
