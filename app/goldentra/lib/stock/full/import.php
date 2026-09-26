<?php

/**
 * FTP 库存 CSV 流式导入
 *
 * 支持两种格式（均无表头）：
 * - SAP POS 逗号 6 列：branch_bn,plant,unique_code,qty,unit,snapshot_time
 * - Legacy 分号 5 列：branch_bn;unique_code;qty;type;snapshot_time
 */
class goldentra_stock_full_import
{
    /**
     * @param string|null $file_date Ymd
     * @param string|null $file_name
     * @param bool $from_archive 从备份目录重导（成功后不再二次归档）
     */
    public function runFromFtp($file_date = null, $file_name = null, $from_archive = false)
    {
        $file_date = $file_date ?: date('Ymd');
        $ftp = kernel::single('goldentra_stock_full_ftp');

        if ($file_name || goldentra_stock_full_config::isFixedNaming()) {
            $file_name = $file_name ?: goldentra_stock_full_config::resolveFileName($file_date);
            $local = $ftp->download($file_date, $file_name, $from_archive);
            $stats = $this->importLocalFile($local, $file_date);
            if (!$from_archive) {
                $ftp->archiveFiles(array($file_name), $file_date);
                $stats['archived'] = array($file_name);
            } else {
                $stats['archived'] = array();
            }
            $stats['files'] = array($file_name);
            $stats['from_archive'] = $from_archive;
            return $stats;
        }

        $bundle = $ftp->downloadAllForDate($file_date, $from_archive);
        $stats = $this->importLocalFiles($bundle['local_paths'], $file_date);
        if (!$from_archive) {
            $ftp->archiveFiles($bundle['files'], $file_date);
            $stats['archived'] = $bundle['files'];
        } else {
            $stats['archived'] = array();
        }
        $stats['files'] = $bundle['files'];
        $stats['from_archive'] = $from_archive;
        return $stats;
    }

    /**
     * @param array $filepaths
     * @param string|null $file_date Ymd
     */
    public function importLocalFiles(array $filepaths, $file_date = null)
    {
        if (!$filepaths) {
            throw new Exception('无文件可导入');
        }

        $file_date = $file_date ?: $this->guessDateFromPath($filepaths[0]);
        $sync_batch_no = goldentra_stock_full_config::syncBatchNoForDate($file_date);

        @ini_set('memory_limit', '512M');
        set_time_limit(0);

        $mdl = app::get('goldentra')->model('stock');
        $mdl->delete(array('sync_batch_no' => $sync_batch_no));

        $inserted = 0;
        $skipped = 0;
        $snapshot_dates = array();

        foreach ($filepaths as $filepath) {
            $result = $this->importLocalFileAppend($filepath, $sync_batch_no);
            $inserted += $result['inserted'];
            $skipped += $result['skipped'];
            foreach ($result['snapshot_dates'] as $snapshot_date) {
                $snapshot_dates[$snapshot_date] = true;
            }
        }

        if ($inserted === 0) {
            throw new Exception(
                '当日 CSV 无有效明细（共 ' . count($filepaths) . ' 个文件，已跳过 ' . $skipped . ' 行）'
            );
        }

        $snapshots = array_keys($snapshot_dates);
        sort($snapshots);

        base_kvstore::instance('goldentra')->store(
            'stock_full_last_import',
            array(
                'sync_batch_no' => $sync_batch_no,
                'snapshot_dates' => $snapshots,
                'inserted' => $inserted,
                'skipped' => $skipped,
                'files' => $filepaths,
                'time' => time(),
            ),
            86400 * 7
        );

        return array(
            'inserted' => $inserted,
            'skipped' => $skipped,
            'sync_batch_no' => $sync_batch_no,
            'snapshot_dates' => $snapshots,
            'stock_type' => 'full',
            'file' => $filepaths[0],
            'files' => $filepaths,
        );
    }

    /**
     * @param string $filepath
     * @param string|null $file_date Ymd
     */
    public function importLocalFile($filepath, $file_date = null)
    {
        if (!is_readable($filepath)) {
            throw new Exception('文件不可读: ' . $filepath);
        }

        $file_date = $file_date ?: $this->guessDateFromPath($filepath);
        $sync_batch_no = goldentra_stock_full_config::syncBatchNoForDate($file_date);

        @ini_set('memory_limit', '512M');
        set_time_limit(0);

        $mdl = app::get('goldentra')->model('stock');
        $mdl->delete(array('sync_batch_no' => $sync_batch_no));

        $result = $this->importLocalFileAppend($filepath, $sync_batch_no);
        if ($result['inserted'] === 0) {
            throw new Exception(
                'CSV 无有效明细: ' . $filepath
                . '（已跳过 ' . $result['skipped'] . ' 行；SAP POS 格式应为 branch,plant,unique_code,qty,unit,snapshot_time）'
            );
        }

        $snapshots = $result['snapshot_dates'];
        sort($snapshots);

        base_kvstore::instance('goldentra')->store(
            'stock_full_last_import',
            array(
                'sync_batch_no' => $sync_batch_no,
                'snapshot_dates' => $snapshots,
                'inserted' => $result['inserted'],
                'skipped' => $result['skipped'],
                'file' => $filepath,
                'time' => time(),
            ),
            86400 * 7
        );

        return array(
            'inserted' => $result['inserted'],
            'skipped' => $result['skipped'],
            'sync_batch_no' => $sync_batch_no,
            'snapshot_dates' => $snapshots,
            'stock_type' => 'full',
            'file' => $filepath,
        );
    }

    /**
     * @param string $filepath
     * @param string $sync_batch_no
     * @return array{inserted:int,skipped:int,snapshot_dates:array}
     */
    private function importLocalFileAppend($filepath, $sync_batch_no)
    {
        $cfg = goldentra_stock_full_config::get();
        $chunk_size = max(500, (int) $cfg['insert_chunk']);
        $bulk = kernel::single('goldentra_stock_bulk');
        $stockLib = kernel::single('goldentra_stock');
        $now = time();
        $buffer = array();
        $inserted = 0;
        $skipped = 0;
        $snapshot_dates = array();

        $fh = fopen($filepath, 'r');
        if (!$fh) {
            throw new Exception('无法打开文件: ' . $filepath);
        }

        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            try {
                $row = $stockLib->parseFullImportLine($line, $sync_batch_no, $now);
            } catch (Exception $e) {
                $skipped++;
                continue;
            }
            if ((int) $row['qty'] === 0) {
                $skipped++;
                continue;
            }

            $snapshot_dates[$row['snapshot_date']] = true;
            $buffer[] = $row;

            if (count($buffer) >= $chunk_size) {
                $stockLib->fillMoldCodes($buffer);
                $inserted += $bulk->insertRows($buffer, $chunk_size);
                $buffer = array();
            }
        }
        fclose($fh);

        if ($buffer) {
            $stockLib->fillMoldCodes($buffer);
            $inserted += $bulk->insertRows($buffer, $chunk_size);
        }

        return array(
            'inserted' => $inserted,
            'skipped' => $skipped,
            'snapshot_dates' => array_keys($snapshot_dates),
        );
    }

    private function guessDateFromPath($filepath)
    {
        if (preg_match('/(?:stock|Stock)(\d{8})/i', basename($filepath), $m)) {
            return $m[1];
        }
        if (preg_match('/(?:stock_|ZPOSSK_)(\d{8})/', $filepath, $m)) {
            return $m[1];
        }
        if (preg_match('/test(\d{4})\.csv/i', basename($filepath), $m)) {
            $year = is_readable($filepath) ? date('Y', filemtime($filepath)) : date('Y');
            return $year . $m[1];
        }
        if (preg_match('/(\d{8})/', basename($filepath), $m)) {
            return $m[1];
        }
        return date('Ymd');
    }
}
