<?php
/**
 * 日志记录类
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */

class taskmgr_log{

    /**
     * 写日志
     *
     * @param string $filename
     * @param string $info
     * @return null
     */
    static public function log($filename, $info){
        $logfile = dirname(__FILE__) . '/../logs/'.date('Ymd').'/'.$filename.'.log';
        if(!file_exists($logfile)){
            if(!is_dir(dirname($logfile)))  self::mkdir_p(dirname($logfile));
        }
        error_log(date('Y-m-d H:i:s') . "\t" . $info."\n",3,$logfile);
    }

    // 判断文件目录是否存在
    static public function getDirPath($dir)
    {
        $logfile = dirname(__FILE__) . '/../logs/'.$dir;

        return $logfile;
    }

    static public function mkdir_p($dir,$dirmode=0755){
        $path = explode('/',str_replace('\\','/',$dir));
        $depth = count($path);
        for($i=$depth;$i>0;$i--){
            if(file_exists(implode('/',array_slice($path,0,$i)))){
                break;
            }
        }
        for($i;$i<$depth;$i++){
            if($d= implode('/',array_slice($path,0,$i+1))){
                // 多进程并发时目录可能已被其他进程创建，忽略 File exists
                if(!is_dir($d)) @mkdir($d,$dirmode);
            }
        }
        return is_dir($dir);
    }

    /**
     * Detailed debug information
     */
    public const DEBUG = 'info';

    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    public const INFO = 'normal';

    /**
     * Uncommon events
     */
    public const NOTICE = 'warning';

    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    public const WARNING = 'warning';

    /**
     * Runtime errors
     */
    public const ERROR = 'error';

    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     */
    public const CRITICAL = 'error';

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    public const ALERT = 'error';

    /**
     * Urgent alert.
     */
    public const EMERGENCY = 'error';


    private static $logBasePath = __ROOT_DIR . '/logs/';

    /**
     * 在DEBUG级别添加一条日志记录。
     *
     * @param string $message 日志信息
     * @param mixed[] $context 日志上下文数组
     */
    public static function debug($message, array $context = [], $task = 'system')
    {
        return self::addRecord(self::DEBUG, $message, $context, $task);
    }

    /**
     * 添加INFO级别的日志记录.
     *
     * @param string $message 日志信息
     * @param mixed[] $context 日志上下文数组
     */
    public static function info($message, array $context = [], $task = 'system')
    {
        return self::addRecord(self::INFO, $message, $context, $task);
    }

    /**
     * 添加通知级别的日志记录l.
     *
     * @param string $message 日志信息
     * @param mixed[] $context 日志上下文数组
     */
    public static function notice($message, array $context = [], $task = 'system')
    {
        return self::addRecord(self::NOTICE, $message, $context, $task);
    }

    /**
     * 添加警告级别的日志记录.
     *
     * @param string $message 日志信息
     * @param mixed[] $context 日志上下文数组
     */
    public static function warning($message, array $context = [], $task = 'system')
    {
        return self::addRecord(self::WARNING, $message, $context, $task);
    }

    /**
     * 添加错误级别的日志记录
     *
     * @param string $message 日志信息
     * @param mixed[] $context 日志上下文数组
     */
    public static function  error($message, array $context = [], $task = 'system')
    {
        return self::addRecord(self::ERROR, $message, $context, $task);
    }

    /**
     * 添加危急级别的日志记录.
     *
     * @param string $message 日志信息
     * @param mixed[] $context 日志上下文数组
     */
    public static function critical($message, array $context = [], $task = 'system')
    {
        return self::addRecord(self::CRITICAL, $message, $context, $task);
    }

    /**
     * 添加ALERT级别的日志记录.
     *
     * @param string $message 日志信息
     * @param mixed[] $context 日志上下文数组
     */
    public static function alert($message, array $context = [], $task = 'system')
    {
        return self::addRecord(self::ALERT, $message, $context, $task);
    }

    /**
     * 添加紧急级别的日志记录.
     *
     * @param string $message 日志信息
     * @param mixed[] $context 日志上下文数组
     */
    public static function emergency($message, array $context = [], $task = 'system')
    {
        return self::addRecord(self::EMERGENCY, $message, $context, $task);
    }

    /**
     * 记录日志（会重置通道缓存）
     * @param int $level 日志级别
     * @param string $message 日志信息
     * @param array $context 日志上下文数组
     */
    private static function addRecord($level, string $message, array $context = [], $task)
    {
        $logPath = self::$logBasePath . date('Ymd') . '/';

        $logFile = $logPath . $task .'.log';

        if (!is_dir($logPath)){
            self::mkdir_p($logPath);
        }

        $logFile = defined('LOG_OUTPUT') && LOG_OUTPUT ? LOG_OUTPUT : $logFile;

        $message = sprintf("[%s][Message=%s => %s %s]\n", strtoupper($level), $message, json_encode($context), date('Y/m/d H:i:s'));

        if (taskmgr_swprocess_conf::daemon()) {
            error_log($message, 3, $logFile);
        } elseif (method_exists('taskmgr_swconsole_output', $level)) {
            call_user_func(array('taskmgr_swconsole_output',$level),$message . PHP_EOL);
        } else {
            taskmgr_swconsole_output::info($message . PHP_EOL);
        }
    }
}
