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

class entermembercenter_stat
{
    public function __construct($app = null)
    {
    }

    /**
     * 安装完成后上报一次（与 setup success / install_product 共用；哨兵幂等）
     *
     * @return void
     */
    public function reportInstall()
    {
        $secret = defined('OPEN_SOURCE_STAT_SECRET_OMS') ? (string) OPEN_SOURCE_STAT_SECRET_OMS : '';
        if ($secret === '') {
            return;
        }

        $sentinel = $this->sentinelPath();
        if ($sentinel && is_file($sentinel)) {
            return;
        }

        $params = $this->buildParams();
        $params['sign'] = $this->sign($params, $secret);

        $body = json_encode($params, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return;
        }

        $url = defined('OPEN_SOURCE_STAT_REPORT_URL') ? trim((string) OPEN_SOURCE_STAT_REPORT_URL) : '';
        if ($url === '') {
            return;
        }
        $timeout = defined('OPEN_SOURCE_STAT_HTTP_TIMEOUT') ? (int) OPEN_SOURCE_STAT_HTTP_TIMEOUT : 20;
        if ($timeout < 1) {
            $timeout = 20;
        }

        $ok = $this->postJson($url, $body, $timeout);
        if ($ok && $sentinel) {
            @file_put_contents($sentinel, (string) time(), LOCK_EX);
        }
    }

    /**
     * @param array $params payload without sign
     * @param string $secret
     * @return string uppercase md5
     */
    public function sign(array $params, $secret)
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);
        $s = '';
        foreach ($params as $k => $v) {
            if ($v === null) {
                continue;
            }
            if (is_bool($v)) {
                $v = $v ? 1 : 0;
            }
            $s .= $k . (is_array($v) ? json_encode($v, JSON_UNESCAPED_SLASHES) : $v);
        }

        return strtoupper(md5($secret . $s . $secret));
    }

    private function buildParams()
    {
        $product = defined('OPEN_SOURCE_STAT_PRODUCT') ? (string) OPEN_SOURCE_STAT_PRODUCT : 'oms';

        return array(
            'product'     => $product,
            'instance_id' => $this->detectInstanceId(),
            'version'     => $this->detectVersion(),
            'timestamp'   => time(),
            'is_docker'   => $this->detectDocker() ? 1 : 0,
            'is_vm'       => $this->detectVm() ? 1 : 0,
        );
    }

    private function sentinelPath()
    {
        if (!defined('ROOT_DIR')) {
            return null;
        }
        $dir = defined('DATA_DIR') ? DATA_DIR : (ROOT_DIR . '/data');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $name = defined('OPEN_SOURCE_STAT_SENTINEL_NAME') ? trim((string) OPEN_SOURCE_STAT_SENTINEL_NAME) : '.opensource_install_stat_reported';
        if ($name === '' || strpos($name, '/') !== false || strpos($name, "\0") !== false) {
            $name = '.opensource_install_stat_reported';
        }

        return $dir . '/' . $name;
    }

    private function detectVersion()
    {
        if (!class_exists('base_setup_config')) {
            return '0.0.0';
        }
        try {
            $info = base_setup_config::deploy_info();
            if (!empty($info['ver'])) {
                return (string) $info['ver'];
            }
        } catch (Exception $e) {
            // ignore
        }

        return '0.0.0';
    }

    private function detectInstanceId()
    {
        if (PHP_OS_FAMILY === 'Linux' || stripos(PHP_OS, 'Linux') !== false) {
            $glob = @glob('/sys/class/net/*/address');
            if (is_array($glob)) {
                foreach ($glob as $path) {
                    if (strpos($path, '/lo/') !== false) {
                        continue;
                    }
                    $raw = @trim((string) @file_get_contents($path));
                    if ($raw !== '' && strcasecmp($raw, '00:00:00:00:00:00') !== 0) {
                        return strtolower(str_replace('-', ':', $raw));
                    }
                }
            }
        }

        $host = function_exists('php_uname') ? php_uname('n') : 'unknown';
        $root = defined('ROOT_DIR') ? ROOT_DIR : __DIR__;

        return md5($host . '|' . $root);
    }

    private function detectDocker()
    {
        if (@is_file('/.dockerenv')) {
            return true;
        }
        if (@is_readable('/proc/1/cgroup')) {
            $c = @file_get_contents('/proc/1/cgroup');
            if ($c !== false && strpos($c, 'docker') !== false) {
                return true;
            }
        }

        return false;
    }

    private function detectVm()
    {
        $paths = array(
            '/sys/class/dmi/id/product_name',
            '/sys/class/dmi/id/sys_vendor',
        );
        foreach ($paths as $p) {
            if (!@is_readable($p)) {
                continue;
            }
            $t = strtolower((string) @file_get_contents($p));
            if ($t === '') {
                continue;
            }
            if (preg_match('/vmware|virtualbox|kvm|qemu|xen|parallels|microsoft corporation|bochs|innotek/', $t)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $url
     * @param string $body
     * @param int $timeoutSec
     * @return bool true if HTTP 2xx
     */
    private function postJson($url, $body, $timeoutSec)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return false;
            }
            curl_setopt_array($ch, array(
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeoutSec,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSec),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ));
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $code >= 200 && $code < 300;
        }

        $ctx = stream_context_create(array(
            'http' => array(
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nConnection: close\r\n",
                'content'       => $body,
                'timeout'       => $timeoutSec,
                'ignore_errors' => true,
            ),
            'ssl'  => array(
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ),
        ));
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return false;
        }
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $code = (int) $m[1];

            return $code >= 200 && $code < 300;
        }

        return false;
    }
}
