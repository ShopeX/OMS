<?php

/**
 * SAP 库存快照落库（比对机器中心 SN，不改 material_sn）
 */
class goldentra_stock
{
    const SNAPSHOT_LEN = 19;

    public static $processed_status = array('succ', 'fail', 'skip');

    /** @var array unique_code => mold_code */
    private $moldCodeCache = array();

    /**
     * 按唯一码批量预加载模号（全量导入等场景）
     *
     * @param string[] $unique_codes
     */
    public function preloadMoldCodes(array $unique_codes)
    {
        $need = array();
        foreach ($unique_codes as $code) {
            $code = trim((string) $code);
            if ($code === '' || array_key_exists($code, $this->moldCodeCache)) {
                continue;
            }
            $need[$code] = true;
        }
        if (!$need) {
            return;
        }
        $codes = array_keys($need);
        foreach ($codes as $code) {
            $this->moldCodeCache[$code] = '';
        }
        $rows = app::get('goldentra')->model('material')->getList(
            'unique_code,mold_code',
            array('unique_code' => $codes)
        );
        foreach ((array) $rows as $row) {
            $uc = trim((string) $row['unique_code']);
            if ($uc === '') {
                continue;
            }
            $this->moldCodeCache[$uc] = trim((string) $row['mold_code']);
        }
    }

    /**
     * 回填行数据中的模号
     *
     * @param array $rows
     */
    public function fillMoldCodes(array &$rows)
    {
        if (!$rows) {
            return;
        }
        $codes = array();
        foreach ($rows as $row) {
            if (!empty($row['unique_code'])) {
                $codes[] = $row['unique_code'];
            }
        }
        $this->preloadMoldCodes($codes);
        foreach ($rows as &$row) {
            if (empty($row['mold_code']) && !empty($row['unique_code'])) {
                $row['mold_code'] = $this->lookupMoldCode($row['unique_code']);
            }
        }
        unset($row);
    }

    public function lookupMoldCode($unique_code, $fetchMissing = true)
    {
        $unique_code = trim((string) $unique_code);
        if ($unique_code === '') {
            return '';
        }
        if (!array_key_exists($unique_code, $this->moldCodeCache)) {
            if (!$fetchMissing) {
                return '';
            }
            $this->preloadMoldCodes(array($unique_code));
        }
        return isset($this->moldCodeCache[$unique_code]) ? $this->moldCodeCache[$unique_code] : '';
    }

    /**
     * YYYY-MM-DD 或 YYYY-MM-DD HH:MM:SS → 19 位版本字符串
     */
    public function normalizeSnapshotDate($date)
    {
        $date = trim($date);
        if ($date === '') {
            throw new Exception('snapshot_time 不能为空');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date . ' 00:00:00';
        }
        if (strlen($date) === self::SNAPSHOT_LEN) {
            return $date;
        }
        throw new Exception('snapshot_time 须为 YYYY-MM-DD 或 YYYY-MM-DD HH:MM:SS');
    }

    public function normalizeStockType($type)
    {
        $type = strtolower(trim($type));
        if (!in_array($type, goldentra_stock_fields::$stock_types, true)) {
            throw new Exception('TYPE 须为 increment 或 full');
        }
        return $type;
    }

    /**
     * 解析 CSV 一行（5 列，无表头，分号分隔）
     */
    public function parseCsvColumns(array $cols, $sync_batch_no, $now, $require_type = null)
    {
        if (count($cols) < 5) {
            throw new Exception('CSV 列数不足，须为 branch_bn;unique_code;qty;type;snapshot_time');
        }

        $line = array(
            'branch_bn' => trim($cols[0]),
            'unique_code' => trim($cols[1]),
            'qty' => trim($cols[2]),
            'type' => trim($cols[3]),
            'snapshot_time' => trim($cols[4]),
            'plant' => isset($cols[5]) ? trim($cols[5]) : '',
        );

        return $this->buildRowFromLine($line, $sync_batch_no, $now, $require_type, false);
    }

    /**
     * 全量 FTP CSV 行解析：兼容 SAP POS 逗号 6 列与 legacy 分号 5 列。
     *
     * SAP POS 示例：1000,5001,6X00002003,11,件,2026-06-3022:36:32
     * Legacy 示例：9199000;1234567890123;1;full;2026-06-02
     */
    public function parseFullImportLine($line, $sync_batch_no, $now)
    {
        $line = trim($line);
        if ($line === '') {
            throw new Exception('空行');
        }

        $delimiter = strpos($line, ';') !== false ? ';' : ',';
        $cols = str_getcsv($line, $delimiter);
        if (!is_array($cols)) {
            throw new Exception('无法解析 CSV 行');
        }
        $cols = array_map('trim', $cols);

        if ($delimiter === ',' && count($cols) >= 6) {
            $mapped = array(
                'branch_bn' => $cols[0],
                'plant' => $cols[1],
                'unique_code' => $cols[2],
                'qty' => $cols[3],
                'unit' => $cols[4],
                'type' => 'full',
                'snapshot_time' => $this->normalizeSapSnapshotTime($cols[5]),
            );
            return $this->buildRowFromLine($mapped, $sync_batch_no, $now, 'full', false);
        }

        return $this->parseCsvColumns($cols, $sync_batch_no, $now, 'full');
    }

    /**
     * SAP 导出时间常无空格：2026-06-3022:36:32
     */
    public function normalizeSapSnapshotTime($date)
    {
        $date = trim($date);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})(\d{2}:\d{2}:\d{2})$/', $date, $m)) {
            return $m[1] . ' ' . $m[2];
        }
        return $date;
    }

    public function buildRowFromLine(array $line, $sync_batch_no, $now, $require_type = null, $require_plant = true)
    {
        $line = goldentra_stock_fields::normalizeLine($line);
        $required = goldentra_stock_fields::$line_required;
        if (!$require_plant) {
            $required = array_values(array_diff($required, array('plant')));
        }
        $missing = array();
        foreach ($required as $field) {
            if ($line[$field] === '' || $line[$field] === null) {
                $missing[] = $field;
            }
        }
        if ($missing) {
            throw new Exception(goldentra_api_normalize::formatMissingFieldsMessage(
                $missing,
                goldentra_stock_fields::$descriptions,
                '明细缺少'
            ));
        }

        $stock_type = $this->normalizeStockType($line['type']);
        if ($require_type !== null && $stock_type !== $require_type) {
            throw new Exception(sprintf('type 须为 %s，当前 %s', $require_type, $stock_type));
        }

        if (!is_numeric($line['qty'])) {
            throw new Exception('qty 须为数字');
        }

        $raw_qty = $this->normalizeRawQty($line['qty']);
        $raw_unit = $line['unit'];
        $qty = $this->normalizeQty($line['unique_code'], $raw_qty, $raw_unit);

        $extRaw = array_merge($line, array(
            'plant' => $line['plant'],
            'unit' => $line['unit'],
        ));
        foreach (goldentra_stock_fields::$ext_fields as $extKey) {
            $val = goldentra_api_normalize::pick($line, $extKey);
            if ($val !== null && $val !== '') {
                $extRaw[$extKey] = $val;
            }
        }

        return array(
            'sync_batch_no' => $sync_batch_no,
            'stock_type' => $stock_type,
            'snapshot_date' => $this->normalizeSnapshotDate($line['snapshot_time']),
            'plant' => $line['plant'],
            'branch_bn' => $line['branch_bn'],
            'unique_code' => $line['unique_code'],
            'mold_code' => $this->lookupMoldCode($line['unique_code'], false),
            'qty' => $qty,
            'raw_qty' => $raw_qty,
            'raw_unit' => $raw_unit,
            'oms_qty' => 0,
            'diff_qty' => 0,
            'ext_field' => goldentra_stock_fields::buildExtField($extRaw),
            'process_status' => 'pending',
            'process_msg' => '',
            'process_time' => 0,
            'at_time' => $now,
            'up_time' => $now,
        );
    }

    /**
     * 按 SAP 工厂/库位解析 OMS 仓库
     * 优先 plant；找不到再用 plant_库位；无 plant 时回退按库位匹配（兼容旧数据）
     *
     * @return array|null branch_id, branch_bn
     */
    public function resolveBranch($plant, $location_bn)
    {
        $plant = trim((string) $plant);
        $location_bn = trim((string) $location_bn);
        $branchMdl = app::get('ome')->model('branch');

        if ($plant !== '') {
            $branch = $branchMdl->dump(array('branch_bn' => $plant), 'branch_id,branch_bn');
            if (!empty($branch['branch_id'])) {
                return $branch;
            }
            if ($location_bn !== '') {
                $combo = $plant . '_' . $location_bn;
                $branch = $branchMdl->dump(array('branch_bn' => $combo), 'branch_id,branch_bn');
                if (!empty($branch['branch_id'])) {
                    return $branch;
                }
            }
            return null;
        }

        if ($location_bn === '') {
            return null;
        }
        $branch = $branchMdl->dump(array('branch_bn' => $location_bn), 'branch_id,branch_bn');
        return !empty($branch['branch_id']) ? $branch : null;
    }

    /**
     * 匹配失败时的提示文案
     */
    public function formatBranchNotFoundMsg($plant, $location_bn)
    {
        $plant = trim((string) $plant);
        $location_bn = trim((string) $location_bn);
        if ($plant !== '' && $location_bn !== '') {
            return sprintf('仓库不存在: plant=%s 或 %s_%s', $plant, $plant, $location_bn);
        }
        if ($plant !== '') {
            return '仓库不存在: plant=' . $plant;
        }
        return '仓库不存在: ' . $location_bn;
    }

    public function parseStocksPayload($params)
    {
        if (!empty($params['stocks'])) {
            return goldentra_api_normalize::decodeJsonBatch($params['stocks'], 'unique_code');
        }

        if (isset($params['STOCKS']['STOCK'])) {
            $stocks = $params['STOCKS']['STOCK'];
            if (isset($stocks['unique_code']) || isset($stocks['UNIQUE_CODE'])
                || isset($stocks['branch_bn']) || isset($stocks['BRANCH_BN'])) {
                return array($stocks);
            }
            return is_array($stocks) ? $stocks : false;
        }

        if (goldentra_api_normalize::pick($params, 'branch_bn') !== null
            && goldentra_api_normalize::pick($params, 'unique_code') !== null) {
            return array($params);
        }

        return false;
    }

    /**
     * 唯一码库存数量归一化：SAP 计价类会传克重（如 4.36），>0 视为在库 1，否则 0
     */
    public function normalizeQty($unique_code, $qty, $unit = '')
    {
        $qty = trim((string) $qty);
        if (!is_numeric($qty)) {
            throw new Exception('qty 须为数字');
        }
        return bccomp($qty, '0', 3) > 0 ? 1 : 0;
    }

    public function normalizeRawQty($qty)
    {
        $qty = trim((string) $qty);
        if (!is_numeric($qty)) {
            throw new Exception('qty 须为数字');
        }
        return $qty;
    }

    public function validateBatchMeta($params, $allow_full = false)
    {
        $sync_batch_no = trim($this->batchField($params, 'sync_batch_no'));
        if ($sync_batch_no === '') {
            throw new Exception('缺少批次参数: sync_batch_no');
        }

        $result = array(
            'sync_batch_no' => $sync_batch_no,
            'snapshot_date' => '',
            'stock_type' => 'increment',
        );

        $snapshot_raw = trim($this->batchField($params, 'snapshot_date'));
        if ($snapshot_raw !== '') {
            $result['snapshot_date'] = $this->normalizeSnapshotDate($snapshot_raw);
        }

        $type_raw = trim($this->batchField($params, 'stock_type'));
        if ($type_raw !== '') {
            $result['stock_type'] = $this->normalizeStockType($type_raw);
            if (!$allow_full && $result['stock_type'] !== 'increment') {
                throw new Exception('OpenAPI 仅支持 TYPE=increment，全量请走 FTP');
            }
        } elseif (!$allow_full) {
            $result['stock_type'] = 'increment';
        }

        return $result;
    }

    /**
     * OpenAPI 增量落库
     */
    public function validateApiBatchMeta($params)
    {
        return $this->validateBatchMeta($params, false);
    }

    public function batchField($params, $name)
    {
        if (isset($params[$name])) {
            return $params[$name];
        }
        $upper = strtoupper($name);
        return isset($params[$upper]) ? $params[$upper] : '';
    }

    /**
     * 同 sync_batch_no 重推：先删后插
     *
     * @return array inserted
     */
    public function ingestBatchFromApi($params)
    {
        $meta = $this->validateApiBatchMeta($params);
        $lines = $this->parseStocksPayload($params);
        if ($lines === false || empty($lines)) {
            throw new Exception('库存明细为空或结构错误');
        }

        $mdl = app::get('goldentra')->model('stock');
        $now = time();
        $mdl->delete(array('sync_batch_no' => $meta['sync_batch_no']));

        $codes = array();
        foreach ($lines as $line) {
            $uc = goldentra_api_normalize::pickString((array) $line, 'unique_code');
            if ($uc !== '') {
                $codes[] = $uc;
            }
        }
        $this->preloadMoldCodes($codes);

        $inserted = 0;
        foreach ($lines as $line) {
            try {
                $row = $this->parseLine($line, $meta, $now);
            } catch (Exception $e) {
                throw new Exception($this->formatStockRowError($line, $e->getMessage()));
            }
            if ($row['stock_type'] !== 'increment') {
                throw new Exception($this->formatStockRowError($line, 'OpenAPI 明细 TYPE 须为 increment'));
            }
            $mdl->insert($row);
            $inserted++;
        }

        return array(
            'inserted' => $inserted,
            'sync_batch_no' => $meta['sync_batch_no'],
            'stock_type' => 'increment',
        );
    }

    public function parseLine($line, $meta, $now)
    {
        $normalized = goldentra_stock_fields::normalizeLine($line);
        foreach (goldentra_stock_fields::$ext_fields as $extKey) {
            $val = goldentra_api_normalize::pick($line, $extKey);
            if ($val !== null && $val !== '') {
                $normalized[$extKey] = $val;
            }
        }

        if ($normalized['type'] === '' && !empty($meta['stock_type'])) {
            $normalized['type'] = $meta['stock_type'];
        }
        if ($normalized['snapshot_time'] === '' && !empty($meta['snapshot_date'])) {
            $normalized['snapshot_time'] = $meta['snapshot_date'];
        }
        if ($normalized['plant'] === '') {
            $normalized['plant'] = goldentra_api_normalize::pickString($line, 'plant');
        }

        return $this->buildRowFromLine($normalized, $meta['sync_batch_no'], $now, 'increment');
    }

    private function buildExtField($line)
    {
        return goldentra_stock_fields::buildExtField($line);
    }

    /**
     * 已处理记录中的最新快照时间（plant + 库位 + unique_code）
     */
    public function getLatestProcessedSnapshot($plant, $branch_bn, $unique_code)
    {
        $db = kernel::database();
        $plant = kernel::single('goldentra_db')->escape((string) $plant, $db);
        $branch_bn = kernel::single('goldentra_db')->escape($branch_bn, $db);
        $unique_code = kernel::single('goldentra_db')->escape($unique_code, $db);
        $status_in = "'" . implode("','", self::$processed_status) . "'";
        $sql = "SELECT MAX(snapshot_date) AS latest FROM sdb_goldentra_stock
            WHERE plant='{$plant}' AND branch_bn='{$branch_bn}' AND unique_code='{$unique_code}'
            AND process_status IN ({$status_in})";
        $row = $db->selectrow($sql);
        return !empty($row['latest']) ? $row['latest'] : '';
    }

    public function markSkip($stock_id, $msg)
    {
        $ret = $this->updateStatus($stock_id, 'skip', $msg);
        goldentra_stock_operation_log::write($stock_id, 'sap_stock_process_skip', $msg);
        return $ret;
    }

    public function markSucc($stock_id, $msg, $oms_qty, $diff_qty)
    {
        $ret = app::get('goldentra')->model('stock')->update(
            array(
                'process_status' => 'succ',
                'process_msg' => $msg,
                'process_time' => time(),
                'oms_qty' => $oms_qty,
                'diff_qty' => $diff_qty,
                'up_time' => time(),
            ),
            array('stock_id' => $stock_id)
        );
        goldentra_stock_operation_log::write(
            $stock_id,
            'sap_stock_process_succ',
            sprintf('%s；OMS=%s 差异=%s', $msg, $oms_qty, $diff_qty)
        );
        return $ret;
    }

    public function markFail($stock_id, $msg, $oms_qty = null, $diff_qty = null)
    {
        $data = array(
            'process_status' => 'fail',
            'process_msg' => $msg,
            'process_time' => time(),
            'up_time' => time(),
        );
        if ($oms_qty !== null) {
            $data['oms_qty'] = $oms_qty;
        }
        if ($diff_qty !== null) {
            $data['diff_qty'] = $diff_qty;
        }
        $ret = app::get('goldentra')->model('stock')->update($data, array('stock_id' => $stock_id));
        $detail = $msg;
        if ($oms_qty !== null || $diff_qty !== null) {
            $detail .= sprintf('；OMS=%s 差异=%s', $oms_qty, $diff_qty);
        }
        goldentra_stock_operation_log::write($stock_id, 'sap_stock_process_fail', $detail);
        return $ret;
    }

    public function updateStatus($stock_id, $status, $msg)
    {
        return app::get('goldentra')->model('stock')->update(
            array(
                'process_status' => $status,
                'process_msg' => $msg,
                'process_time' => time(),
                'up_time' => time(),
            ),
            array('stock_id' => $stock_id)
        );
    }

    private function formatStockRowError(array $line, $message)
    {
        $normalized = goldentra_stock_fields::normalizeLine($line);
        return goldentra_api_normalize::formatRowError($message, array(
            'branch_bn' => $normalized['branch_bn'],
            'unique_code' => $normalized['unique_code'],
        ));
    }
}
