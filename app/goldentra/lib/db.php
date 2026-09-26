<?php

/**
 * goldentra 内 SQL 字符串转义（框架 DB 仅提供 quote）
 */
class goldentra_db
{
    public function escape($string, $db = null)
    {
        if ($db === null) {
            $db = kernel::database();
        }
        $quoted = $db->quote($string);

        return substr($quoted, 1, -1);
    }

    public function quote($string, $db = null)
    {
        if ($db === null) {
            $db = kernel::database();
        }

        return $db->quote($string);
    }

    /**
     * 连接级命名锁（GET_LOCK）。同一连接可嵌套不同锁名。
     *
     * @param string   $lockKey 业务键（会 md5 成锁名）
     * @param int      $timeout 秒
     * @param callable $callback
     * @return mixed
     */
    public function withLock($lockKey, $timeout, $callback)
    {
        $db = kernel::database();
        $timeout = max(1, intval($timeout));
        $name = $this->escape('gtlk_' . md5((string) $lockKey), $db);
        $row = $db->selectrow("SELECT GET_LOCK('{$name}', {$timeout}) AS got");
        if (empty($row['got']) || intval($row['got']) !== 1) {
            throw new Exception('获取数据库锁超时: ' . $lockKey);
        }
        try {
            return call_user_func($callback);
        } finally {
            $db->selectrow("SELECT RELEASE_LOCK('{$name}') AS r");
        }
    }
}
