<?php

/**
 * FTP 全量库存配置
 *
 * 生效优先级：defaults ← config/goldentra_stock_ftp.php ← 环境变量 ← 后台菜单配置
 */
class goldentra_stock_full_config
{
    public static function get()
    {
        return kernel::single('goldentra_stock_full_setting')->getEffective();
    }

    public static function fileNameForDate($date = null, $cfg = null)
    {
        if (!$cfg) {
            $cfg = self::get();
        }
        $date = $date ?: date('Ymd');
        return self::filePatternLabel($date, $cfg);
    }

    /**
     * 当日库存文件命名：{prefix}{Ymd}*.csv
     */
    public static function filePatternLabel($date = null, $cfg = null)
    {
        if (!$cfg) {
            $cfg = self::get();
        }
        $date = $date ?: date('Ymd');
        $prefix = isset($cfg['file_prefix']) ? $cfg['file_prefix'] : 'Stock';
        return $prefix . $date . '*.csv';
    }

    /**
     * 匹配当日库存文件（不区分大小写）
     */
    public static function matchFileForDate($basename, $date = null, $cfg = null)
    {
        if (!$cfg) {
            $cfg = self::get();
        }
        $date = $date ?: date('Ymd');
        $prefix = isset($cfg['file_prefix']) ? $cfg['file_prefix'] : 'Stock';
        $pattern = '/^' . preg_quote($prefix, '/') . preg_quote($date, '/') . '.*\.csv$/i';
        return (bool) preg_match($pattern, basename($basename));
    }

    /**
     * @param array $names
     * @return array
     */
    public static function filterFilesForDate(array $names, $date = null, $cfg = null)
    {
        $matched = array();
        foreach ($names as $name) {
            $base = basename($name);
            if (self::matchFileForDate($base, $date, $cfg)) {
                $matched[] = $base;
            }
        }
        sort($matched, SORT_STRING);
        return $matched;
    }

    /**
     * 远程备份目录：/SAPOMS/QAS -> /SAPOMS/QAS-ARCHIVE
     */
    public static function resolveArchiveDir($cfg = null)
    {
        if (!$cfg) {
            $cfg = self::get();
        }
        if (!empty($cfg['ftp_remote_archive_dir'])) {
            return rtrim($cfg['ftp_remote_archive_dir'], '/');
        }
        $dir = rtrim($cfg['ftp_remote_dir'], '/');
        if (preg_match('#/(QAS|PRD)$#i', $dir)) {
            return $dir . '-ARCHIVE';
        }
        return $dir . '/ARCHIVE';
    }

    /**
     * SAP 共享源目录：/SAPOMS/QAS + /mnt/SAPOMS => /mnt/SAPOMS/QAS
     */
    public static function sapSourceDir($cfg = null)
    {
        if (!$cfg) {
            $cfg = self::get();
        }
        if (empty($cfg['sap_oms_dir'])) {
            return '';
        }
        $sap_base = rtrim($cfg['sap_oms_dir'], '/');
        $remote = rtrim($cfg['ftp_remote_dir'], '/');
        if (stripos($remote, '/SAPOMS') === 0) {
            return $sap_base . substr($remote, strlen('/SAPOMS'));
        }
        return $sap_base . '/' . basename($remote);
    }

    /**
     * SAP 共享备份目录
     */
    public static function sapArchiveDir($cfg = null)
    {
        if (!$cfg) {
            $cfg = self::get();
        }
        if (!empty($cfg['sap_oms_archive_dir'])) {
            return rtrim($cfg['sap_oms_archive_dir'], '/');
        }
        $source = self::sapSourceDir($cfg);
        if (preg_match('#/(QAS|PRD)$#i', $source)) {
            return $source . '-ARCHIVE';
        }
        return $source . '/ARCHIVE';
    }

    /**
     * 按配置解析远程文件名：固定文件名 或 前缀+日期
     */
    public static function resolveFileName($date = null, $cfg = null)
    {
        if (!$cfg) {
            $cfg = self::get();
        }
        $naming = isset($cfg['file_naming']) ? $cfg['file_naming'] : 'dated';
        if ($naming === 'fixed' && !empty($cfg['file_name'])) {
            return ltrim(trim($cfg['file_name']), '/');
        }
        return self::fileNameForDate($date, $cfg);
    }

    public static function isFixedNaming($cfg = null)
    {
        if (!$cfg) {
            $cfg = self::get();
        }
        return isset($cfg['file_naming']) && $cfg['file_naming'] === 'fixed';
    }

    public static function syncBatchNoForDate($date = null)
    {
        $date = $date ?: date('Ymd');
        return 'FTP' . $date;
    }

    public static function snapshotDateForDate($date = null)
    {
        $date = $date ?: date('Ymd');
        return substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2) . ' 00:00:00';
    }
}
