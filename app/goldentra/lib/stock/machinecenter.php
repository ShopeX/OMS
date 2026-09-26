<?php

/**
 * 库存同步与机器中心 SN（sdb_machinecenter_material_sn）比对
 */
class goldentra_stock_machinecenter
{
    /** 在库（与 WAP 发货扫码一致：status=1） */
    const IN_STOCK_STATUS = array('1');

    /** 已出库，禁止按 SAP 快照调增 */
    const OUT_STOCK_STATUS = '0';

    public function isInstalled()
    {
        return app::get('machinecenter')->is_installed();
    }

    public function requireInstalled()
    {
        if (!$this->isInstalled()) {
            throw new Exception('机器中心未安装，无法比对库存');
        }
    }

    /**
     * @param int|string $branch_id
     * @param string $unique_code SN
     * @return array|false
     */
    public function loadSn($branch_id, $unique_code)
    {
        return app::get('machinecenter')->model('material_sn')->dump(
            array(
                'branch_id' => $branch_id,
                'sn' => $unique_code,
            ),
            'id,sn,status,material_bn,branch_id,branch_bn'
        );
    }

    public function calcOmsQty($sn)
    {
        if (empty($sn)) {
            return 0;
        }
        if (in_array((string) $sn['status'], self::IN_STOCK_STATUS, true)) {
            return 1;
        }
        return 0;
    }

    public function isSoldOut($sn)
    {
        return !empty($sn) && (string) $sn['status'] === self::OUT_STOCK_STATUS;
    }

    public function sqlInStockStatuses()
    {
        return "'" . implode("','", self::IN_STOCK_STATUS) . "'";
    }

    /** 当日0点 Unix 时间戳 */
    public function todayVersionTime()
    {
        return strtotime(date('Y-m-d 00:00:00'));
    }

    public function touchVersionTime($branch_id, $sn, $version_time = null)
    {
        if (!$this->isInstalled()) {
            return false;
        }
        if ($version_time === null) {
            $version_time = $this->todayVersionTime();
        }
        return app::get('machinecenter')->model('material_sn')->update(
            array('version_time' => (int) $version_time, 'up_time' => date('Y-m-d H:i:s')),
            array('branch_id' => $branch_id, 'sn' => $sn)
        );
    }

    public function touchVersionTimeBatch($db, $batch_esc, $version_time)
    {
        $in_stock = $this->sqlInStockStatuses();
        // 仓库匹配：优先 plant，其次 plant_库位，无 plant 时回退库位
        $db->exec(
            "UPDATE sdb_machinecenter_material_sn mc
            INNER JOIN sdb_ome_branch b ON b.branch_id = mc.branch_id
            INNER JOIN sdb_goldentra_stock s ON s.unique_code = mc.sn
            LEFT JOIN sdb_ome_branch b_plant
                ON s.plant <> '' AND b_plant.branch_bn = s.plant
            LEFT JOIN sdb_ome_branch b_combo
                ON s.plant <> '' AND b_plant.branch_id IS NULL
                AND b_combo.branch_bn = CONCAT(s.plant, '_', s.branch_bn)
            LEFT JOIN sdb_ome_branch b_loc
                ON s.plant = '' AND b_loc.branch_bn = s.branch_bn
            SET mc.version_time = {$version_time}, mc.up_time = NOW()
            WHERE s.sync_batch_no = '{$batch_esc}'
            AND s.qty > 0
            AND s.process_status IN ('succ','running')
            AND b.branch_id = COALESCE(b_plant.branch_id, b_combo.branch_id, b_loc.branch_id)"
        );
    }

    /**
     * 全量同步：本批次未打标且在库 SN → 需清零
     */
    public function findStaleInStockByBranches($db, array $branch_bns, $version_time)
    {
        if (empty($branch_bns)) {
            return array();
        }
        $in_stock = $this->sqlInStockStatuses();
        $branch_in = array();
        foreach ($branch_bns as $bn) {
            $branch_in[] = "'" . kernel::single('goldentra_db')->escape($bn, $db) . "'";
        }
        $branch_sql = implode(',', $branch_in);
        return $db->select(
            "SELECT mc.sn AS unique_code, b.branch_bn, b.branch_id, m.bn
            FROM sdb_machinecenter_material_sn mc
            INNER JOIN sdb_ome_branch b ON b.branch_id = mc.branch_id
            INNER JOIN sdb_goldentra_material m
                ON m.unique_code = mc.sn AND m.match_status='1' AND m.bn <> ''
            WHERE b.branch_bn IN ({$branch_sql})
            AND mc.status IN ({$in_stock})
            AND (mc.version_time IS NULL OR mc.version_time < {$version_time})"
        );
    }
}
