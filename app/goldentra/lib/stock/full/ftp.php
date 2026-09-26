<?php

class goldentra_stock_full_ftp
{
    /**
     * 列出当日匹配的远程库存文件名
     *
     * @param string|null $file_date Ymd
     * @param bool $from_archive 从备份目录列举（已归档文件重导）
     * @return array
     */
    public function listRemoteFilesForDate($file_date = null, $from_archive = false)
    {
        $file_date = $file_date ?: date('Ymd');
        $cfg = goldentra_stock_full_config::get();

        if (goldentra_stock_full_config::isFixedNaming($cfg)) {
            return array(goldentra_stock_full_config::resolveFileName($file_date, $cfg));
        }

        $tried = array();
        $names = array();

        if ($from_archive) {
            // 1) 本地挂载备份目录
            $local_archive = goldentra_stock_full_config::sapArchiveDir($cfg);
            if ($local_archive !== '' && is_dir($local_archive) && is_readable($local_archive)) {
                $names = $this->scandirBasenames($local_archive);
                $tried[] = '本地 ' . $local_archive . '（' . count($names) . ' 个文件）';
                $matched = goldentra_stock_full_config::filterFilesForDate($names, $file_date, $cfg);
                if ($matched) {
                    return $matched;
                }
            } else {
                $tried[] = '本地 '
                    . ($local_archive !== '' ? $local_archive : '(未配置)')
                    . '（不可用）';
            }

            // 2) FTP 备份目录（本地无匹配时务必再试，避免空挂载挡住 FTP）
            try {
                $names = $this->listArchiveFileNamesViaFtp($cfg);
                $ftp_archive = goldentra_stock_full_config::resolveArchiveDir($cfg);
                $tried[] = 'FTP ' . $ftp_archive . '（' . count($names) . ' 个文件）';
                $matched = goldentra_stock_full_config::filterFilesForDate($names, $file_date, $cfg);
                if ($matched) {
                    return $matched;
                }
            } catch (Exception $e) {
                $tried[] = 'FTP：' . $e->getMessage();
            }

            $sample = $names ? implode(', ', array_slice($names, 0, 5)) : '(无)';
            throw new Exception(
                '未找到当日库存文件（备份目录）: '
                . goldentra_stock_full_config::filePatternLabel($file_date, $cfg)
                . '；已尝试：' . implode('；', $tried)
                . '；目录抽样：' . $sample
            );
        }

        $names = $this->listSourceFileNames($cfg);
        $matched = goldentra_stock_full_config::filterFilesForDate($names, $file_date, $cfg);
        if (!$matched) {
            throw new Exception(
                '未找到当日库存文件（源目录）: '
                . goldentra_stock_full_config::filePatternLabel($file_date, $cfg)
            );
        }
        return $matched;
    }

    /**
     * @param string|null $file_date Ymd
     * @param bool $from_archive
     * @return array{files:array,local_paths:array,file_date:string}
     */
    public function downloadAllForDate($file_date = null, $from_archive = false)
    {
        $file_date = $file_date ?: date('Ymd');
        $files = $this->listRemoteFilesForDate($file_date, $from_archive);
        $local_paths = array();
        foreach ($files as $file_name) {
            $local_paths[] = $this->download($file_date, $file_name, $from_archive);
        }
        return array(
            'files' => $files,
            'local_paths' => $local_paths,
            'file_date' => $file_date,
        );
    }

    /**
     * 导入成功后，将远程文件移至备份目录
     *
     * @param array $file_names
     * @param string|null $file_date Ymd
     */
    public function archiveFiles(array $file_names, $file_date = null)
    {
        if (!$file_names) {
            return;
        }
        $cfg = goldentra_stock_full_config::get();
        if ($this->archiveViaSapShare($cfg, $file_names)) {
            return;
        }
        if (empty($cfg['ftp_pass'])) {
            throw new Exception('归档失败：FTP 密码未配置');
        }
        $this->archiveViaFtp($cfg, $file_names);
    }

    /**
     * @param array $cfg
     * @return array
     */
    public function listSourceFileNames(array $cfg)
    {
        $sap_dir = goldentra_stock_full_config::sapSourceDir($cfg);
        if ($sap_dir !== '' && is_dir($sap_dir) && is_readable($sap_dir)) {
            return $this->scandirBasenames($sap_dir);
        }

        if (empty($cfg['ftp_pass'])) {
            throw new Exception('FTP 密码未配置，且 SAP 共享目录不可用: ' . $sap_dir);
        }

        $conn = @ftp_connect($cfg['ftp_host'], 21, 30);
        if (!$conn) {
            throw new Exception('无法连接 FTP: ' . $cfg['ftp_host']);
        }
        if (!@ftp_login($conn, $cfg['ftp_user'], $cfg['ftp_pass'])) {
            ftp_close($conn);
            throw new Exception('FTP 登录失败');
        }
        ftp_pasv($conn, true);
        $names = $this->listRemoteBasenames($conn, $cfg['ftp_remote_dir']);
        ftp_close($conn);
        return $names;
    }

    /**
     * 备份目录文件名列表（SAP 共享 ARCHIVE 或 FTP ARCHIVE）
     *
     * @param array $cfg
     * @return array
     */
    public function listArchiveFileNames(array $cfg)
    {
        $archive_dir = goldentra_stock_full_config::sapArchiveDir($cfg);
        if ($archive_dir !== '' && is_dir($archive_dir) && is_readable($archive_dir)) {
            $names = $this->scandirBasenames($archive_dir);
            // 本地有文件才直接返回；空目录继续走 FTP（挂载点常与 FTP ARCHIVE 不同步）
            if ($names) {
                return $names;
            }
        }

        return $this->listArchiveFileNamesViaFtp($cfg);
    }

    /**
     * 仅通过 FTP 列举备份目录
     *
     * @param array $cfg
     * @return array
     */
    public function listArchiveFileNamesViaFtp(array $cfg)
    {
        if (empty($cfg['ftp_pass'])) {
            $archive_dir = goldentra_stock_full_config::sapArchiveDir($cfg);
            throw new Exception('FTP 密码未配置，且 SAP 备份目录不可用: ' . $archive_dir);
        }

        $remote_archive = goldentra_stock_full_config::resolveArchiveDir($cfg);
        $conn = @ftp_connect($cfg['ftp_host'], 21, 30);
        if (!$conn) {
            throw new Exception('无法连接 FTP: ' . $cfg['ftp_host']);
        }
        if (!@ftp_login($conn, $cfg['ftp_user'], $cfg['ftp_pass'])) {
            ftp_close($conn);
            throw new Exception('FTP 登录失败');
        }
        ftp_pasv($conn, true);
        $names = $this->listRemoteBasenames($conn, $remote_archive);
        ftp_close($conn);
        return $names;
    }

    /**
     * @param string $dir
     * @return array
     */
    private function scandirBasenames($dir)
    {
        $names = array();
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_file($path)) {
                $names[] = $item;
            }
        }
        return $names;
    }

    private function archiveViaSapShare(array $cfg, array $file_names)
    {
        $source_dir = goldentra_stock_full_config::sapSourceDir($cfg);
        if ($source_dir === '' || !is_dir($source_dir) || !is_readable($source_dir)) {
            return false;
        }
        $archive_dir = goldentra_stock_full_config::sapArchiveDir($cfg);
        if (!is_dir($archive_dir)) {
            if (!@mkdir($archive_dir, 0755, true) && !is_dir($archive_dir)) {
                throw new Exception('无法创建 SAP 备份目录: ' . $archive_dir);
            }
        }

        foreach ($file_names as $file_name) {
            $from = $source_dir . '/' . $file_name;
            $to = $archive_dir . '/' . $file_name;
            if (!is_readable($from)) {
                continue;
            }
            if (is_file($to)) {
                @unlink($to);
            }
            if (!@rename($from, $to)) {
                throw new Exception('SAP 共享归档失败: ' . $from . ' -> ' . $to);
            }
        }
        return true;
    }

    private function archiveViaFtp(array $cfg, array $file_names)
    {
        $source_dir = rtrim($cfg['ftp_remote_dir'], '/');
        $archive_dir = goldentra_stock_full_config::resolveArchiveDir($cfg);

        $conn = @ftp_connect($cfg['ftp_host'], 21, 60);
        if (!$conn) {
            throw new Exception('归档失败：无法连接 FTP ' . $cfg['ftp_host']);
        }
        if (!@ftp_login($conn, $cfg['ftp_user'], $cfg['ftp_pass'])) {
            ftp_close($conn);
            throw new Exception('归档失败：FTP 登录失败');
        }
        ftp_pasv($conn, true);
        $this->ensureFtpDir($conn, $archive_dir);

        foreach ($file_names as $file_name) {
            $from = $source_dir . '/' . $file_name;
            $to = $archive_dir . '/' . $file_name;
            if (!@ftp_rename($conn, $from, $to)) {
                if (!@ftp_chdir($conn, $source_dir) || !@ftp_rename($conn, $file_name, $to . '/' . $file_name)) {
                    ftp_close($conn);
                    throw new Exception('FTP 归档失败: ' . $from . ' -> ' . $to);
                }
            }
        }
        ftp_close($conn);
    }

    /**
     * @param resource $conn
     * @param string $remote_dir
     */
    private function ensureFtpDir($conn, $remote_dir)
    {
        $remote_dir = trim(str_replace('\\', '/', $remote_dir), '/');
        if ($remote_dir === '') {
            return;
        }
        $parts = explode('/', $remote_dir);
        $path = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $path .= '/' . $part;
            if (@ftp_chdir($conn, $path)) {
                continue;
            }
            if (!@ftp_mkdir($conn, $path)) {
                @ftp_chdir($conn, $path);
            }
        }
    }

    /**
     * @param resource $conn
     * @param string $remote_dir
     * @return array
     */
    public function listRemoteBasenames($conn, $remote_dir)
    {
        $remote_dir = rtrim(str_replace('\\', '/', (string) $remote_dir), '/');
        $candidates = array();
        if ($remote_dir !== '') {
            $candidates[] = $remote_dir;
            $candidates[] = ltrim($remote_dir, '/');
        }
        $candidates[] = '.';

        foreach ($candidates as $dir) {
            if ($dir !== '.' && $dir !== '') {
                $list = @ftp_nlist($conn, $dir);
                $names = $this->normalizeFtpNameList($list);
                if ($names) {
                    return $names;
                }
            }
        }

        // 部分 FTP 对绝对路径 nlist 失败，需先 CWD 再列当前目录
        if ($remote_dir !== '') {
            foreach (array($remote_dir, ltrim($remote_dir, '/')) as $dir) {
                if (!@ftp_chdir($conn, $dir)) {
                    continue;
                }
                $list = @ftp_nlist($conn, '.');
                if (!is_array($list)) {
                    $list = @ftp_nlist($conn, '-a');
                }
                $names = $this->normalizeFtpNameList($list);
                if ($names) {
                    return $names;
                }
            }
        }

        return array();
    }

    /**
     * @param array|false|null $list
     * @return array
     */
    private function normalizeFtpNameList($list)
    {
        if (!is_array($list)) {
            return array();
        }
        $names = array();
        foreach ($list as $item) {
            $name = basename(str_replace('\\', '/', (string) $item));
            if ($name !== '' && $name !== '.' && $name !== '..') {
                $names[] = $name;
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * @param resource $conn
     * @param string $remote_dir
     * @param string $file_name
     * @return array{exists:bool,size:int|false,names:array}
     */
    public function probeRemoteFile($conn, $remote_dir, $file_name)
    {
        $remote_dir = rtrim($remote_dir, '/');
        $remote_file = $remote_dir . '/' . $file_name;
        $names = $this->listRemoteBasenames($conn, $remote_dir);

        foreach (array($remote_file, $file_name) as $path) {
            $size = @ftp_size($conn, $path);
            if ($size >= 0) {
                return array('exists' => true, 'size' => $size, 'names' => $names);
            }
        }

        foreach ($names as $name) {
            if (strcasecmp($name, $file_name) === 0) {
                return array('exists' => true, 'size' => false, 'names' => $names);
            }
        }

        return array('exists' => false, 'size' => false, 'names' => $names);
    }

    /**
     * @param string|null $file_date Ymd
     * @param string|null $file_name
     * @param bool $from_archive 从备份目录读取
     * @return string
     */
    public function download($file_date = null, $file_name = null, $from_archive = false)
    {
        $cfg = goldentra_stock_full_config::get();
        $file_name = $file_name ?: goldentra_stock_full_config::resolveFileName($file_date);

        $sap_path = $this->resolveReadablePath($cfg, $file_name, $from_archive);
        if ($sap_path) {
            return $sap_path;
        }

        if (empty($cfg['local_dir'])) {
            throw new Exception('DATA_DIR 未定义，无法保存 FTP 文件');
        }
        if (!is_dir($cfg['local_dir'])) {
            if (!mkdir($cfg['local_dir'], 0755, true)) {
                throw new Exception('无法创建目录: ' . $cfg['local_dir']);
            }
        }

        $local_file = rtrim($cfg['local_dir'], '/') . '/' . $file_name;
        if (is_readable($local_file) && filesize($local_file) > 0) {
            return $local_file;
        }

        if (empty($cfg['ftp_pass'])) {
            $hint_dir = $from_archive
                ? goldentra_stock_full_config::sapArchiveDir($cfg)
                : goldentra_stock_full_config::sapSourceDir($cfg);
            throw new Exception(
                'FTP 密码未配置且本地/SAP'
                . ($from_archive ? '备份' : '共享')
                . '文件不存在: ' . $file_name
                . ($hint_dir !== '' ? '（目录 ' . $hint_dir . '）' : '')
            );
        }

        $local_dir = dirname($local_file);
        if (!is_writable($local_dir)) {
            throw new Exception('本地下载目录不可写: ' . $local_dir);
        }

        $tmp = $this->allocateTempFile($local_file);
        @unlink($tmp);

        $remote_dir = $from_archive
            ? goldentra_stock_full_config::resolveArchiveDir($cfg)
            : $cfg['ftp_remote_dir'];
        $this->downloadRemoteFile($cfg, $remote_dir, $file_name, $tmp, $local_file);

        if (!is_readable($tmp) || filesize($tmp) <= 0) {
            @unlink($tmp);
            throw new Exception('FTP 下载失败: 文件为空或无法写入 ' . $local_file);
        }

        if ($tmp !== $local_file) {
            if (file_exists($local_file)) {
                @unlink($local_file);
            }
            if (!@rename($tmp, $local_file)) {
                if (!@copy($tmp, $local_file)) {
                    @unlink($tmp);
                    throw new Exception('无法移动下载文件到: ' . $local_file);
                }
                @unlink($tmp);
            }
        }

        return $local_file;
    }

    private function allocateTempFile($local_file)
    {
        $part = $local_file . '.part';
        if (@touch($part) && is_writable($part)) {
            @unlink($part);
            return $part;
        }

        $tmp = rtrim(sys_get_temp_dir(), '/') . '/goldentra_ftp_' . uniqid('', true) . '.part';
        return $tmp;
    }

    private function downloadRemoteFile(array $cfg, $remote_dir, $file_name, $local_tmp, $local_file)
    {
        $errors = array();
        $remote = rtrim(str_replace('\\', '/', $remote_dir), '/') . '/' . $file_name;

        try {
            $this->downloadViaPhpFtp($cfg, $remote_dir, $file_name, $local_tmp);
            return;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            @unlink($local_tmp);
        }

        $curl_err = '';
        if ($this->downloadViaCurl($cfg, $remote_dir, $file_name, $local_tmp, $curl_err)) {
            return;
        }
        if ($curl_err !== '') {
            $errors[] = $curl_err;
        }

        $stream_err = '';
        if ($this->downloadViaStream($cfg, $remote_dir, $file_name, $local_tmp, $stream_err)) {
            return;
        }
        if ($stream_err !== '') {
            $errors[] = $stream_err;
        }

        $shell_err = '';
        if ($this->downloadViaShellCurl($cfg, $remote_dir, $file_name, $local_tmp, $shell_err)) {
            return;
        }
        if ($shell_err !== '') {
            $errors[] = $shell_err;
        }

        $hint = $this->buildFailureHint($cfg, $file_name, $local_file);
        throw new Exception(
            'FTP 下载失败: ' . $remote . '。' . implode('；', $errors) . '。' . $hint
        );
    }

    private function buildFailureHint(array $cfg, $file_name, $local_file)
    {
        $sap = goldentra_stock_full_config::sapSourceDir($cfg) . '/' . $file_name;
        $parts = array(
            '远程文件存在但 OMS 服务器无法通过 FTP 数据通道下载，常见于防火墙未放行 PASV 端口',
        );
        if ($sap !== '') {
            $parts[] = '可将文件手动放到 SAP 共享目录 ' . $sap;
        }
        $parts[] = '或放到本地下载目录 ' . $local_file;
        $parts[] = '或联系运维开放 ' . $cfg['ftp_host'] . ' 的 FTP 被动模式数据端口';
        return implode('；', $parts);
    }

    private function downloadViaPhpFtp(array $cfg, $remote_dir, $file_name, $local_tmp)
    {
        $conn = @ftp_connect($cfg['ftp_host'], 21, 60);
        if (!$conn) {
            throw new Exception('PHP-FTP 无法连接 ' . $cfg['ftp_host']);
        }
        if (!@ftp_login($conn, $cfg['ftp_user'], $cfg['ftp_pass'])) {
            ftp_close($conn);
            throw new Exception('PHP-FTP 登录失败');
        }

        $last = 'PHP-FTP 未知错误';
        foreach (array(true, false) as $passive) {
            @ftp_pasv($conn, $passive);
            if (defined('FTP_USEPASVADDRESS')) {
                @ftp_set_option($conn, FTP_USEPASVADDRESS, false);
            }
            try {
                $this->ftpDownloadToLocal($conn, $remote_dir, $file_name, $local_tmp);
                ftp_close($conn);
                return;
            } catch (Exception $e) {
                @unlink($local_tmp);
                $last = ($passive ? '被动模式' : '主动模式') . ': ' . $e->getMessage();
            }
        }
        ftp_close($conn);
        throw new Exception($last);
    }

    private function ftpDownloadToLocal($conn, $remote_dir, $file_name, $local_tmp)
    {
        $remote_dir = rtrim(str_replace('\\', '/', $remote_dir), '/');
        $attempts = array();
        $remote_paths = $this->collectRemotePaths($conn, $remote_dir, $file_name);

        foreach ($remote_paths as $remote_path) {
            foreach ($this->ftpTransferModes() as $mode_label => $mode) {
                if ($this->tryFtpGet($conn, $local_tmp, $remote_path, $mode)) {
                    return;
                }
                $attempts[] = 'GET ' . $remote_path . ' (' . $mode_label . ')';
            }
        }

        foreach (array($remote_dir, ltrim($remote_dir, '/')) as $dir) {
            if ($dir === '' || !@ftp_chdir($conn, $dir)) {
                if ($dir !== '') {
                    $attempts[] = 'CWD ' . $dir . ' 失败';
                }
                continue;
            }
            foreach ($this->ftpTransferModes() as $mode_label => $mode) {
                if ($this->tryFtpGet($conn, $local_tmp, $file_name, $mode)) {
                    return;
                }
                $attempts[] = 'CWD ' . $dir . ' + GET ' . $file_name . ' (' . $mode_label . ')';
            }
        }

        throw new Exception(implode('；', $attempts));
    }

    private function tryFtpGet($conn, $local_tmp, $remote_path, $mode)
    {
        @unlink($local_tmp);
        if (!@ftp_get($conn, $local_tmp, $remote_path, $mode)) {
            return false;
        }
        return is_readable($local_tmp) && filesize($local_tmp) > 0;
    }

    private function collectRemotePaths($conn, $remote_dir, $file_name)
    {
        $paths = array();
        foreach (array(rtrim($remote_dir, '/'), ltrim(rtrim($remote_dir, '/'), '/')) as $dir) {
            $list = @ftp_nlist($conn, $dir);
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $item) {
                $item = str_replace('\\', '/', $item);
                if (strcasecmp(basename($item), $file_name) === 0) {
                    $paths[] = $item;
                    $paths[] = basename($item);
                }
            }
        }

        $paths[] = rtrim($remote_dir, '/') . '/' . $file_name;
        $paths[] = ltrim(rtrim($remote_dir, '/'), '/') . '/' . $file_name;
        $paths[] = $file_name;

        return array_values(array_unique($paths));
    }

    private function downloadViaCurl(array $cfg, $remote_dir, $file_name, $local_tmp, &$errorMsg)
    {
        if (!function_exists('curl_init')) {
            $errorMsg = 'curl 扩展未安装';
            return false;
        }

        $errors = array();
        $urls = $this->buildFtpUrls($cfg, $remote_dir, $file_name);
        $modes = array(
            array('label' => 'PASV+EPSV', 'epsv' => true, 'skip_ip' => false, 'active' => false),
            array('label' => 'PASV', 'epsv' => false, 'skip_ip' => false, 'active' => false),
            array('label' => 'PASV skip-ip', 'epsv' => false, 'skip_ip' => true, 'active' => false),
            array('label' => 'active', 'epsv' => false, 'skip_ip' => false, 'active' => true),
        );

        foreach ($urls as $url) {
            foreach ($modes as $mode) {
                @unlink($local_tmp);
                $fp = @fopen($local_tmp, 'wb');
                if (!$fp) {
                    $errors[] = $mode['label'] . ': 无法写入临时文件';
                    continue;
                }
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_TIMEOUT, 600);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
                curl_setopt($ch, CURLOPT_FTP_USE_EPSV, $mode['epsv']);
                if ($mode['active']) {
                    curl_setopt($ch, CURLOPT_FTPPORT, '-');
                }
                if ($mode['skip_ip'] && defined('CURLOPT_FTP_SKIP_PASV_IP')) {
                    curl_setopt($ch, CURLOPT_FTP_SKIP_PASV_IP, true);
                }
                $ok = curl_exec($ch);
                $err = curl_error($ch);
                curl_close($ch);
                fclose($fp);

                if ($ok && is_readable($local_tmp) && filesize($local_tmp) > 0) {
                    return true;
                }
                @unlink($local_tmp);
                $errors[] = $mode['label'] . ($err ? ': ' . $err : ': 空文件');
            }
        }

        $errorMsg = 'curl ' . implode('；', $errors);
        return false;
    }

    private function downloadViaStream(array $cfg, $remote_dir, $file_name, $local_tmp, &$errorMsg)
    {
        if (!ini_get('allow_url_fopen')) {
            $errorMsg = 'stream: allow_url_fopen 未开启';
            return false;
        }

        $errors = array();
        foreach ($this->buildFtpUrls($cfg, $remote_dir, $file_name) as $url) {
            @unlink($local_tmp);
            $in = @fopen($url, 'rb');
            if (!$in) {
                $errors[] = '无法打开 ' . preg_replace('/\/\/([^:@\/]+):([^@\/]+)@/', '//$1:***@', $url);
                continue;
            }
            $out = @fopen($local_tmp, 'wb');
            if (!$out) {
                fclose($in);
                $errors[] = '无法写入临时文件';
                continue;
            }
            $copied = @stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);
            if ($copied !== false && is_readable($local_tmp) && filesize($local_tmp) > 0) {
                return true;
            }
            @unlink($local_tmp);
            $errors[] = 'stream 复制失败';
        }

        $errorMsg = implode('；', $errors);
        return false;
    }

    private function downloadViaShellCurl(array $cfg, $remote_dir, $file_name, $local_tmp, &$errorMsg)
    {
        if (!function_exists('exec')) {
            $errorMsg = 'shell curl: exec 被禁用';
            return false;
        }
        $curl = trim((string) @shell_exec('command -v curl 2>/dev/null'));
        if ($curl === '') {
            $errorMsg = 'shell curl: 未找到 curl 命令';
            return false;
        }

        $errors = array();
        $urls = $this->buildFtpUrls($cfg, $remote_dir, $file_name, false);
        $user = $cfg['ftp_user'] . ':' . $cfg['ftp_pass'];
        $modes = array('--ftp-pasv', '--disable-epsv --ftp-pasv', '--ftp-port -');

        foreach ($urls as $url) {
            foreach ($modes as $mode) {
                @unlink($local_tmp);
                $cmd = escapeshellcmd($curl) . ' -g -sS -f --connect-timeout 30 --max-time 600 '
                    . $mode . ' --user ' . escapeshellarg($user) . ' '
                    . escapeshellarg($url) . ' -o ' . escapeshellarg($local_tmp) . ' 2>&1';
                $output = array();
                $code = 1;
                @exec($cmd, $output, $code);
                if ($code === 0 && is_readable($local_tmp) && filesize($local_tmp) > 0) {
                    return true;
                }
                @unlink($local_tmp);
                $errors[] = trim($mode . ' ' . implode(' ', $output));
            }
        }

        $errorMsg = 'shell ' . implode('；', array_slice($errors, 0, 4));
        return false;
    }

    private function buildFtpUrls(array $cfg, $remote_dir, $file_name, $encode = true)
    {
        $remote_dir = trim(str_replace('\\', '/', $remote_dir), '/');
        $host = $cfg['ftp_host'];
        $user = $encode ? rawurlencode($cfg['ftp_user']) : $cfg['ftp_user'];
        $pass = $encode ? rawurlencode($cfg['ftp_pass']) : $cfg['ftp_pass'];
        $auth = $user . ':' . $pass . '@';

        $paths = array();
        if ($remote_dir !== '') {
            if ($encode) {
                $paths[] = implode('/', array_map('rawurlencode', explode('/', $remote_dir))) . '/'
                    . implode('/', array_map('rawurlencode', explode('/', $file_name)));
            } else {
                $paths[] = $remote_dir . '/' . $file_name;
            }
        }
        if ($encode) {
            $paths[] = implode('/', array_map('rawurlencode', explode('/', $file_name)));
        } else {
            $paths[] = $file_name;
        }

        $urls = array();
        foreach (array_unique($paths) as $path) {
            $urls[] = 'ftp://' . $auth . $host . '/' . ltrim($path, '/');
        }
        return $urls;
    }

    private function ftpTransferModes()
    {
        return array(
            'BINARY' => FTP_BINARY,
            'ASCII' => FTP_ASCII,
        );
    }

    private function resolveReadablePath(array $cfg, $file_name, $from_archive = false)
    {
        $dir = $from_archive
            ? goldentra_stock_full_config::sapArchiveDir($cfg)
            : goldentra_stock_full_config::sapSourceDir($cfg);
        if ($dir === '') {
            return false;
        }
        $path = $dir . '/' . $file_name;
        return (is_readable($path) && filesize($path) > 0) ? $path : false;
    }
}
