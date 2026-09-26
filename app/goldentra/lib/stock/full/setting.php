<?php

/**
 * 全量库存 FTP 配置（后台保存至 app conf，覆盖文件/环境变量）
 */
class goldentra_stock_full_setting
{
    const CONF_KEY = 'stock.ftp.config';

    public static function defaults()
    {
        return array(
            'ftp_host' => 'mdftp.yuyuanjewelry.com',
            'ftp_user' => 'mdoa',
            'ftp_pass' => '',
            'ftp_remote_dir' => '/yydvpt/ZPOSSTOCK',
            'ftp_remote_archive_dir' => '',
            'file_naming' => 'dated',
            'file_prefix' => 'stock_',
            'file_name' => '',
            'sap_oms_dir' => '/mnt/SAPOMS',
            'sap_oms_archive_dir' => '',
            'local_dir' => defined('DATA_DIR') ? DATA_DIR . '/posSyncFiles/serial' : '',
            'insert_chunk' => 3000,
        );
    }

    /**
     * 合并：默认值 ← config 文件常量 ← 环境变量 ← 后台配置
     */
    public function getEffective()
    {
        $cfg = self::defaults();

        if (defined('GOLDENTRA_STOCK_FTP_HOST')) {
            $cfg['ftp_host'] = GOLDENTRA_STOCK_FTP_HOST;
        }
        if (defined('GOLDENTRA_STOCK_FTP_USER')) {
            $cfg['ftp_user'] = GOLDENTRA_STOCK_FTP_USER;
        }
        if (defined('GOLDENTRA_STOCK_FTP_PASS') && GOLDENTRA_STOCK_FTP_PASS !== '') {
            $cfg['ftp_pass'] = GOLDENTRA_STOCK_FTP_PASS;
        }
        if (defined('GOLDENTRA_STOCK_FTP_REMOTE_DIR')) {
            $cfg['ftp_remote_dir'] = GOLDENTRA_STOCK_FTP_REMOTE_DIR;
        }
        if (defined('GOLDENTRA_STOCK_FTP_REMOTE_ARCHIVE_DIR')) {
            $cfg['ftp_remote_archive_dir'] = GOLDENTRA_STOCK_FTP_REMOTE_ARCHIVE_DIR;
        }
        if (defined('GOLDENTRA_STOCK_FTP_FILE_PREFIX')) {
            $cfg['file_prefix'] = GOLDENTRA_STOCK_FTP_FILE_PREFIX;
        }
        if (defined('GOLDENTRA_STOCK_FTP_FILE_NAME') && GOLDENTRA_STOCK_FTP_FILE_NAME !== '') {
            $cfg['file_naming'] = 'fixed';
            $cfg['file_name'] = GOLDENTRA_STOCK_FTP_FILE_NAME;
        }
        if (defined('GOLDENTRA_STOCK_SAP_OMS_DIR') && GOLDENTRA_STOCK_SAP_OMS_DIR !== '') {
            $cfg['sap_oms_dir'] = GOLDENTRA_STOCK_SAP_OMS_DIR;
        }
        if (defined('GOLDENTRA_STOCK_SAP_OMS_ARCHIVE_DIR') && GOLDENTRA_STOCK_SAP_OMS_ARCHIVE_DIR !== '') {
            $cfg['sap_oms_archive_dir'] = GOLDENTRA_STOCK_SAP_OMS_ARCHIVE_DIR;
        }
        if (defined('GOLDENTRA_STOCK_FTP_LOCAL_DIR') && GOLDENTRA_STOCK_FTP_LOCAL_DIR !== '') {
            $cfg['local_dir'] = GOLDENTRA_STOCK_FTP_LOCAL_DIR;
        }
        if (defined('GOLDENTRA_STOCK_INSERT_CHUNK')) {
            $cfg['insert_chunk'] = (int) GOLDENTRA_STOCK_INSERT_CHUNK;
        }

        $env_map = array(
            'ftp_host' => 'GOLDENTRA_STOCK_FTP_HOST',
            'ftp_user' => 'GOLDENTRA_STOCK_FTP_USER',
            'ftp_pass' => 'GOLDENTRA_STOCK_FTP_PASS',
            'ftp_remote_dir' => 'GOLDENTRA_STOCK_FTP_REMOTE_DIR',
            'ftp_remote_archive_dir' => 'GOLDENTRA_STOCK_FTP_REMOTE_ARCHIVE_DIR',
            'file_prefix' => 'GOLDENTRA_STOCK_FTP_FILE_PREFIX',
            'file_name' => 'GOLDENTRA_STOCK_FTP_FILE_NAME',
            'sap_oms_dir' => 'GOLDENTRA_STOCK_SAP_OMS_DIR',
            'sap_oms_archive_dir' => 'GOLDENTRA_STOCK_SAP_OMS_ARCHIVE_DIR',
            'local_dir' => 'GOLDENTRA_STOCK_FTP_LOCAL_DIR',
            'insert_chunk' => 'GOLDENTRA_STOCK_INSERT_CHUNK',
        );
        foreach ($env_map as $key => $env) {
            $val = getenv($env);
            if ($val !== false && $val !== '') {
                if ($key === 'insert_chunk') {
                    $cfg[$key] = (int) $val;
                } elseif ($key === 'file_name') {
                    $cfg['file_naming'] = 'fixed';
                    $cfg['file_name'] = $val;
                } else {
                    $cfg[$key] = $val;
                }
            }
        }

        $saved = app::get('goldentra')->getConf(self::CONF_KEY);
        if (is_array($saved) && $saved) {
            foreach ($saved as $k => $v) {
                if ($v !== '' && $v !== null && array_key_exists($k, $cfg)) {
                    $cfg[$k] = $v;
                }
            }
        }

        if (empty($cfg['local_dir']) && defined('DATA_DIR')) {
            $cfg['local_dir'] = DATA_DIR . '/posSyncFiles/serial';
        }

        return $cfg;
    }

    public function getForForm()
    {
        $cfg = $this->getEffective();
        $cfg['ftp_pass_display'] = $cfg['ftp_pass'] ? '******' : '';
        $cfg['has_password'] = $cfg['ftp_pass'] ? '1' : '0';
        return $cfg;
    }

    public function saveFromPost($post)
    {
        $old = app::get('goldentra')->getConf(self::CONF_KEY);
        if (!is_array($old)) {
            $old = array();
        }

        $data = array(
            'ftp_host' => trim($post['ftp_host']),
            'ftp_user' => trim($post['ftp_user']),
            'ftp_remote_dir' => trim($post['ftp_remote_dir']),
            'ftp_remote_archive_dir' => trim(isset($post['ftp_remote_archive_dir']) ? $post['ftp_remote_archive_dir'] : ''),
            'file_naming' => (isset($post['file_naming']) && $post['file_naming'] === 'fixed') ? 'fixed' : 'dated',
            'file_prefix' => trim(isset($post['file_prefix']) ? $post['file_prefix'] : ''),
            'file_name' => trim(isset($post['file_name']) ? $post['file_name'] : ''),
            'sap_oms_dir' => trim($post['sap_oms_dir']),
            'sap_oms_archive_dir' => trim(isset($post['sap_oms_archive_dir']) ? $post['sap_oms_archive_dir'] : ''),
            'local_dir' => trim($post['local_dir']),
            'insert_chunk' => max(500, (int) $post['insert_chunk']),
        );

        $pass = isset($post['ftp_pass']) ? $post['ftp_pass'] : '';
        if ($pass !== '' && $pass !== '******') {
            $data['ftp_pass'] = $pass;
        } elseif (!empty($old['ftp_pass'])) {
            $data['ftp_pass'] = $old['ftp_pass'];
        } else {
            $effective = $this->getEffective();
            $data['ftp_pass'] = isset($effective['ftp_pass']) ? $effective['ftp_pass'] : '';
        }

        if ($data['ftp_host'] === '' || $data['ftp_user'] === '') {
            throw new Exception('FTP 服务器与用户名不能为空');
        }
        if ($data['ftp_remote_dir'] === '') {
            throw new Exception('远程目录不能为空');
        }
        if ($data['file_naming'] === 'fixed') {
            if ($data['file_name'] === '') {
                throw new Exception('指定文件名不能为空');
            }
        } elseif ($data['file_prefix'] === '') {
            throw new Exception('文件名前缀不能为空');
        }

        app::get('goldentra')->setConf(self::CONF_KEY, $data);
        return true;
    }

    public function buildFromPost($post, $use_saved_pass = true)
    {
        $cfg = $this->getEffective();
        if (!empty($post['ftp_host'])) {
            $cfg['ftp_host'] = trim($post['ftp_host']);
        }
        if (!empty($post['ftp_user'])) {
            $cfg['ftp_user'] = trim($post['ftp_user']);
        }
        if (!empty($post['ftp_remote_dir'])) {
            $cfg['ftp_remote_dir'] = trim($post['ftp_remote_dir']);
        }
        if (isset($post['ftp_remote_archive_dir'])) {
            $cfg['ftp_remote_archive_dir'] = trim($post['ftp_remote_archive_dir']);
        }
        if (isset($post['file_naming'])) {
            $cfg['file_naming'] = $post['file_naming'] === 'fixed' ? 'fixed' : 'dated';
        }
        if (isset($post['file_prefix'])) {
            $cfg['file_prefix'] = trim($post['file_prefix']);
        }
        if (isset($post['file_name'])) {
            $cfg['file_name'] = trim($post['file_name']);
        }
        if (isset($post['sap_oms_dir'])) {
            $cfg['sap_oms_dir'] = trim($post['sap_oms_dir']);
        }
        if (isset($post['sap_oms_archive_dir'])) {
            $cfg['sap_oms_archive_dir'] = trim($post['sap_oms_archive_dir']);
        }
        if (isset($post['local_dir'])) {
            $cfg['local_dir'] = trim($post['local_dir']);
        }
        $pass = isset($post['ftp_pass']) ? $post['ftp_pass'] : '';
        if ($pass !== '' && $pass !== '******') {
            $cfg['ftp_pass'] = $pass;
        } elseif (!$use_saved_pass) {
            $cfg['ftp_pass'] = '';
        }
        return $cfg;
    }

    public function testConnection($cfg = null)
    {
        $cfg = $cfg ?: $this->getEffective();
        if (empty($cfg['ftp_pass'])) {
            throw new Exception('请先配置 FTP 密码');
        }

        $conn = @ftp_connect($cfg['ftp_host'], 21, 15);
        if (!$conn) {
            throw new Exception('无法连接 ' . $cfg['ftp_host']);
        }
        if (!@ftp_login($conn, $cfg['ftp_user'], $cfg['ftp_pass'])) {
            ftp_close($conn);
            throw new Exception('FTP 登录失败，请检查用户名或密码');
        }
        ftp_pasv($conn, true);

        $remote_dir = rtrim($cfg['ftp_remote_dir'], '/');
        $today = date('Ymd');
        $pattern = goldentra_stock_full_config::filePatternLabel($today, $cfg);
        $archive_dir = goldentra_stock_full_config::resolveArchiveDir($cfg);

        if (goldentra_stock_full_config::isFixedNaming($cfg)) {
            $file = goldentra_stock_full_config::resolveFileName($today, $cfg);
            $remote_file = $remote_dir . '/' . $file;
            $probe = kernel::single('goldentra_stock_full_ftp')->probeRemoteFile($conn, $remote_dir, $file);
            ftp_close($conn);
            if ($probe['exists']) {
                if ($probe['size'] !== false) {
                    return '连接成功；目标文件 ' . $remote_file . ' 大小 ' . $probe['size'] . ' 字节；备份目录 ' . $archive_dir;
                }
                return '连接成功；目标文件 ' . $remote_file . ' 已存在；备份目录 ' . $archive_dir;
            }
            $hint = $this->buildMissingFileHint($probe['names'], $file, $cfg);
            return '连接成功；目标文件 ' . $remote_file . ' 尚未上传或不存在' . $hint . '；备份目录 ' . $archive_dir;
        }

        $names = kernel::single('goldentra_stock_full_ftp')->listRemoteBasenames($conn, $remote_dir);
        ftp_close($conn);
        $matched = goldentra_stock_full_config::filterFilesForDate($names, $today, $cfg);
        $hint = $this->buildMissingFileHint($names, $pattern, $cfg);

        return sprintf(
            '连接成功；当日匹配 %s：%s（共 %d 个）；备份目录 %s%s',
            $pattern,
            $matched ? implode('、', $matched) : '尚未上传或不存在',
            count($matched),
            $archive_dir,
            $hint
        );
    }

    /**
     * @param array $names
     * @param string $expected_file
     * @param array|null $cfg
     */
    private function buildMissingFileHint($names, $expected_file, $cfg = null)
    {
        if (!$names) {
            return '';
        }
        sort($names);
        $csv = array_values(array_filter($names, function ($n) {
            return preg_match('/\.csv$/i', $n);
        }));
        $hint = '；目录内 CSV：' . implode('、', array_slice($csv ?: $names, 0, 8));
        if (count($csv ?: $names) > 8) {
            $hint .= ' 等';
        }
        if ($cfg && goldentra_stock_full_config::isFixedNaming($cfg)) {
            $hint .= '（期望文件：' . goldentra_stock_full_config::resolveFileName(null, $cfg) . '）';
        } else {
            $hint .= '（期望命名：' . goldentra_stock_full_config::filePatternLabel(date('Ymd'), $cfg) . '）';
        }
        return $hint;
    }
}
