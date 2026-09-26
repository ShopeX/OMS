<?php

/**
 * 清理 goldentra 库存同步历史记录（默认保留30天）
 */
class goldentra_autotask_timer_stockcleanup
{
    const RETAIN_DAYS = 30;

    public function process($params = array(), &$error_msg = '')
    {
        $days = isset($params['days']) ? (int) $params['days'] : self::RETAIN_DAYS;
        if ($days < 1) {
            $days = self::RETAIN_DAYS;
        }
        $cutoff = time() - $days * 86400;
        $db = kernel::database();
        $db->exec(
            "DELETE FROM sdb_goldentra_stock
            WHERE at_time < {$cutoff}
            AND process_status IN ('succ','fail','skip')"
        );
        $deleted = $db->affect_row();
        $error_msg = sprintf('清理 %d 天前库存同步记录 %d 条', $days, $deleted);
        return true;
    }
}
