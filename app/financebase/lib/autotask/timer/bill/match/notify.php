<?php
/**
 * 店铺收支明细匹配失败日报（每日定时，默认 10:00）
 *
 * 调度：taskmgr billmatchnotify / billmatchnotifydomainqueue
 */
class financebase_autotask_timer_bill_match_notify
{
    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit', '256M');

        if (!app::get('financebase')->is_installed()) {
            return true;
        }

        if (!kernel::single('financebase_bill_match_notify')->isEnabled()) {
            return true;
        }

        $notifyLib = kernel::single('financebase_bill_match_notify');
        $cronTime = $notifyLib->getCronTime();
        $nowHi = date('H:i');
        if ($nowHi !== $cronTime) {
            return true;
        }

        $cacheKey = $notifyLib->buildDailyCacheKey();
        if (cachecore::fetch($cacheKey) === 'done') {
            $this->_logTimerSkip('今日该时刻已执行', array(
                'cache_key' => $cacheKey,
            ));
            return true;
        }

        @ini_set('memory_limit', '512M');

        $ok = $notifyLib->run($params, $error_msg);
        if ($ok) {
            cachecore::store($cacheKey, 'done', 86400);
            $this->_logTimerSkip('执行完成', array(
                'cache_key' => $cacheKey,
                'error_msg' => $error_msg,
            ));
        } else {
            cachecore::delete($cacheKey);
            $this->_logTimerSkip('执行失败', array(
                'cache_key' => $cacheKey,
                'error_msg' => $error_msg,
            ));
        }

        return $ok;
    }

    /**
     * @param string $reason
     * @param array $context
     */
    protected function _logTimerSkip($reason, array $context = array())
    {
        if (!class_exists('financebase_func', false)) {
            return;
        }
        financebase_func::writelog('匹配失败日报-定时', 'settlement', array_merge(
            array('reason' => $reason),
            $context
        ));
    }
}
