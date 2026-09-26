<?php

/**
 * goldentra / yuyuan 异步 task 队列推送
 */
class goldentra_autotask
{
    /**
     * @param string $task_type whitelist 中的 task_type
     * @param array $data 附加参数（unique_codes、raw_id、sync_batch_no 等）
     * @return bool
     */
    public static function push($task_type, $data = array())
    {
        if (!self::ensureTaskmgrConfig()) {
            return false;
        }

        $params = array_merge(array('task_type' => $task_type), $data);
        $push_params = array(
            'data' => $params,
            'url'  => kernel::openapi_url('openapi.autotask', 'service'),
        );

        return kernel::single('taskmgr_interface_connecter')->push($push_params);
    }

    /**
     * Web 请求场景下 taskmgr config 可能尚未加载
     */
    private static function ensureTaskmgrConfig()
    {
        if (defined('__CONNECTER_MODE')) {
            return true;
        }

        $path = dirname(__FILE__) . '/../../taskmgr/config/config.php';
        if (is_readable($path)) {
            include_once $path;
        }

        return defined('__CONNECTER_MODE');
    }
}
