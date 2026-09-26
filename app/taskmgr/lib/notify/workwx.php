<?php
/**
 * 企业微信群机器人告警通知
 *
 * 文档: https://developer.work.weixin.qq.com/document/path/99110
 * 频率限制: 每个机器人不超过 20 条/分钟，故对同类告警做本地节流
 */
class taskmgr_notify_workwx
{
    /** 同类告警默认节流秒数 */
    const DEFAULT_THROTTLE = 300;

    /**
     * 连接器（Redis/RabbitMQ）不可用告警
     * 覆盖：服务未启用、服务中断（两类分别节流，互不影响）
     *
     * @param string $errorMsg worker/timer 返回的错误信息
     * @return bool
     */
    public static function alertConnecterFail($errorMsg)
    {
        $mode = defined('__CONNECTER_MODE') ? __CONNECTER_MODE : 'unknown';
        $cfgKey = strtoupper(sprintf('__%s_CONFIG', $mode));
        $cfg = isset($GLOBALS[$cfgKey]) && is_array($GLOBALS[$cfgKey]) ? $GLOBALS[$cfgKey] : array();

        $host = isset($cfg['HOST']) ? $cfg['HOST'] : '-';
        $port = isset($cfg['PORT']) ? $cfg['PORT'] : '-';
        $domain = defined('DOMAIN') ? DOMAIN : '-';
        $hostname = gethostname() ?: '-';

        // 区分文案类型；节流按连接器模式合并（多 task 同时失败只告警 1 次）
        if (false !== strpos($errorMsg, '服务中断')) {
            $failType = '服务中断';
            $hint = '连接曾可用后中断，请检查 Redis/队列进程是否宕掉或网络抖动。';
        } else {
            $failType = '服务未启用';
            $hint = '启动时连接失败，请检查 Redis/队列服务是否启动及 taskmgr 配置是否正确。';
        }

        $content = sprintf(
            "## taskmgr %s告警\n" .
            "> **级别**: <font color=\"warning\">ERROR</font>\n" .
            "> **类型**: <font color=\"warning\">%s</font>\n" .
            "> **主机**: <font color=\"comment\">%s</font>\n" .
            "> **域名**: <font color=\"comment\">%s</font>\n" .
            "> **连接器**: <font color=\"comment\">%s</font>\n" .
            "> **地址**: <font color=\"comment\">%s:%s</font>\n" .
            "> **样例错误**: <font color=\"warning\">%s</font>\n" .
            "> **时间**: <font color=\"comment\">%s</font>\n" .
            "\n%s\n同类错误 %d 分钟内合并为 1 条，避免多任务刷屏。",
            $failType,
            $failType,
            $hostname,
            $domain,
            $mode,
            $host,
            $port,
            self::escapeMarkdown($errorMsg),
            date('Y-m-d H:i:s'),
            $hint,
            (int) (self::DEFAULT_THROTTLE / 60)
        );

        // 同一连接器模式共用一个节流 key（服务未启用/中断合并）
        return self::sendMarkdown($content, 'connecter_fail_' . $mode, self::DEFAULT_THROTTLE);
    }

    /**
     * 发送 markdown 消息
     *
     * @param string $content markdown 正文
     * @param string|null $throttleKey 节流 key，空则不节流
     * @param int $throttleSeconds 节流秒数
     * @return bool
     */
    public static function sendMarkdown($content, $throttleKey = null, $throttleSeconds = self::DEFAULT_THROTTLE)
    {
        $webhook = self::getWebhook();
        if (!$webhook) {
            taskmgr_log::warning('企业微信 webhook 未配置，跳过告警通知', array(), 'system');
            return false;
        }

        // 原子抢占发送权：多 worker 并发时只有 1 个能发出去
        $claimed = false;
        if ($throttleKey !== null && $throttleKey !== '') {
            if (!self::tryClaim($throttleKey, $throttleSeconds)) {
                return false;
            }
            $claimed = true;
        }

        $payload = json_encode(array(
            'msgtype'  => 'markdown',
            'markdown' => array(
                'content' => $content,
            ),
        ), JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            if ($claimed) {
                self::releaseClaim($throttleKey);
            }
            taskmgr_log::error('企业微信告警 JSON 编码失败', array(), 'system');
            return false;
        }

        try {
            $response = self::httpPost($webhook, $payload);
            $result = json_decode($response, true);
            if (!is_array($result) || (string) $result['errcode'] !== '0') {
                if ($claimed) {
                    self::releaseClaim($throttleKey);
                }
                taskmgr_log::error('企业微信告警发送失败: ' . $response, array(), 'system');
                return false;
            }
            return true;
        } catch (Exception $e) {
            if ($claimed) {
                self::releaseClaim($throttleKey);
            }
            taskmgr_log::error('企业微信告警异常: ' . $e->getMessage(), array(), 'system');
            return false;
        } catch (\Throwable $e) {
            if ($claimed) {
                self::releaseClaim($throttleKey);
            }
            taskmgr_log::error('企业微信告警异常: ' . $e->getMessage(), array(), 'system');
            return false;
        }
    }

    /**
     * 读取 webhook 地址
     * 优先级: 常量 __WORKWX_WEBHOOK__ > $GLOBALS['__WORKWX_WEBHOOK__'] > 环境变量 TASKMGR_WORKWX_WEBHOOK
     *
     * @return string
     */
    protected static function getWebhook()
    {
        if (defined('__WORKWX_WEBHOOK__') && __WORKWX_WEBHOOK__) {
            return __WORKWX_WEBHOOK__;
        }
        if (!empty($GLOBALS['__WORKWX_WEBHOOK__'])) {
            return $GLOBALS['__WORKWX_WEBHOOK__'];
        }
        $env = getenv('TASKMGR_WORKWX_WEBHOOK');
        return $env ? $env : '';
    }

    /**
     * 原子抢占发送权（flock），防止多 task/worker 同时通过检查导致刷屏
     *
     * @param string $key
     * @param int $seconds
     * @return bool true=抢到可发送；false=窗口内已有人发过
     */
    protected static function tryClaim($key, $seconds)
    {
        $file = self::throttleFile($key);
        $dir = dirname($file);
        // 多 worker 并发建目录，用 recursive + @ 避免 mkdir File exists 告警
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }

        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        $raw = stream_get_contents($fp);
        $last = (int) $raw;
        $now = time();
        if ($last > 0 && ($now - $last) < $seconds) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) $now);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

    /**
     * 发送失败时释放占位，允许尽快重试
     *
     * @param string $key
     * @return void
     */
    protected static function releaseClaim($key)
    {
        $file = self::throttleFile($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * @param string $key
     * @return string
     */
    protected static function throttleFile($key)
    {
        $dir = dirname(__FILE__) . '/../../logs/notify';
        return $dir . '/workwx_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.ts';
    }

    /**
     * markdown 内容转义（避免破坏格式）
     *
     * @param string $text
     * @return string
     */
    protected static function escapeMarkdown($text)
    {
        return str_replace(array('`', '<', '>'), array("'", '', ''), (string) $text);
    }

    /**
     * HTTP POST：优先原生 curl（在 Swoole 协程中关闭 CURL hook，避免 openssl 编译缺失）
     * 失败再回退系统 curl 命令
     *
     * @param string $url
     * @param string $body
     * @return string
     * @throws Exception
     */
    protected static function httpPost($url, $body)
    {
        try {
            return self::curlPostNative($url, $body);
        } catch (Exception $e) {
            // Swoole 未编译 openssl 时，hook 后的 curl 会报 enable-openssl；回退系统 curl
            if (false === strpos($e->getMessage(), 'openssl') && false === strpos($e->getMessage(), 'SSL')) {
                throw $e;
            }
            return self::curlPostCli($url, $body);
        }
    }

    /**
     * @param string $url
     * @param string $body
     * @return string
     * @throws Exception
     */
    protected static function curlPostNative($url, $body)
    {
        $hookFlags = null;
        // 协程内禁用 CURL hook，走 PHP 自带 libcurl（通常带 openssl）
        if (class_exists('\\Swoole\\Runtime') && defined('SWOOLE_HOOK_CURL')
            && method_exists('\\Swoole\\Runtime', 'getHookFlags')) {
            $hookFlags = \Swoole\Runtime::getHookFlags();
            if ($hookFlags & SWOOLE_HOOK_CURL) {
                \Swoole\Runtime::enableCoroutine($hookFlags & ~SWOOLE_HOOK_CURL);
            } else {
                $hookFlags = null;
            }
        }

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno) {
                throw new Exception($error, $errno);
            }
            if ($httpCode !== 200) {
                throw new Exception($response ?: ('http status ' . $httpCode), $httpCode);
            }

            return $response;
        } finally {
            if ($hookFlags !== null) {
                \Swoole\Runtime::enableCoroutine($hookFlags);
            }
        }
    }

    /**
     * 系统 curl 回退（兼容 Swoole 未启用 openssl 的环境）
     *
     * @param string $url
     * @param string $body
     * @return string
     * @throws Exception
     */
    protected static function curlPostCli($url, $body)
    {
        if (!function_exists('exec')) {
            throw new Exception('exec disabled, cannot fallback to system curl');
        }

        $cmd = sprintf(
            'curl -sS -m 5 -H %s -d %s %s 2>&1',
            escapeshellarg('Content-Type: application/json'),
            escapeshellarg($body),
            escapeshellarg($url)
        );

        $output = array();
        $code = 0;
        exec($cmd, $output, $code);
        $response = implode("\n", $output);

        if ($code !== 0) {
            throw new Exception('system curl failed: ' . $response, $code);
        }

        return $response;
    }
}
