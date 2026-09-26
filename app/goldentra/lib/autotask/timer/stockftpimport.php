<?php

class goldentra_autotask_timer_stockftpimport
{
    public function process($params = array(), &$error_msg = '')
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(0);

        $file_date = isset($params['file_date']) ? $params['file_date'] : date('Ymd');
        $from_archive = !empty($params['from_archive']);
        $lib = kernel::single('goldentra_stock_full_import');

        try {
            if (!empty($params['local_file'])) {
                $stats = $lib->importLocalFile($params['local_file'], $file_date);
            } elseif (!empty($params['file_name'])) {
                $stats = $lib->runFromFtp($file_date, $params['file_name'], $from_archive);
            } else {
                $stats = $lib->runFromFtp($file_date, null, $from_archive);
            }
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            return false;
        }

        $error_msg = sprintf(
            'FTP全量导入 %d 条，批次 %s，文件 %d 个%s',
            $stats['inserted'],
            $stats['sync_batch_no'],
            isset($stats['files']) ? count($stats['files']) : 1,
            $from_archive ? '（从备份目录）' : ''
        );
        if (!empty($stats['archived'])) {
            $error_msg .= '，已归档 ' . count($stats['archived']) . ' 个';
        }

        if (empty($params['import_only'])) {
            goldentra_autotask::push('goldentrastock', array(
                'sync_batch_no' => $stats['sync_batch_no'],
            ));
            $error_msg .= '；已加入异步比对队列';
        }

        return true;
    }
}
