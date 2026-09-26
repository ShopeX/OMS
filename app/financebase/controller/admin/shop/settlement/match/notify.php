<?php
/**
 * 店铺收支明细匹配失败通知配置
 */
class financebase_ctl_admin_shop_settlement_match_notify extends desktop_controller
{
    public function index()
    {
        $notifyLib = kernel::single('financebase_bill_match_notify');
        $monitorConfig = $notifyLib->checkMonitorConfig();

        $cronTime = $notifyLib->getCronTime();
        $todayStatus = $notifyLib->getTodayStatusSummary();
        $configuredHourPassed = false;
        if (preg_match('/^(\d{2}):00$/', $cronTime, $m)) {
            $configuredHourPassed = (int) date('G') >= (int) $m[1];
        }

        $this->pagedata['config'] = array(
            'enabled' => app::get('financebase')->getConf('bill.match_fail_notify.enabled'),
            'cron_time' => $cronTime,
        );
        $todayStatus['pending_dates_text'] = !empty($todayStatus['pending_dates'])
            ? implode('、', $todayStatus['pending_dates'])
            : '';
        $todayStatus['pending_dates_rows'] = !empty($todayStatus['pending_dates'])
            ? array_chunk($todayStatus['pending_dates'], 8)
            : array();
        $todayStatus['sent_all'] = (
            (int) $todayStatus['failure_days'] > 0
            && (int) $todayStatus['pending_count'] === 0
        );
        $todayStatus['daily_sent'] = (int) $todayStatus['sent_today_count'] > 0;
        $this->pagedata['today_status'] = $todayStatus;
        $this->pagedata['configured_hour_passed'] = $configuredHourPassed;
        $this->pagedata['monitor_config'] = $monitorConfig;
        $this->pagedata['monitor_links'] = array(
            'template' => 'index.php?app=monitor&ctl=admin_alarm_template&act=index',
            'group' => 'index.php?app=monitor&ctl=admin_alarm_group&act=index',
            'receiver' => 'index.php?app=monitor&ctl=admin_alarm_receiver&act=index',
            'email' => 'index.php?app=monitor&ctl=admin_setting&act=index',
        );
        $this->page('admin/shop/settlement/match/notify.html');
    }

    public function save()
    {
        $this->begin();

        $notifyLib = kernel::single('financebase_bill_match_notify');
        $oldCronTime = $notifyLib->getCronTime();

        $enabled = (!empty($_POST['enabled']) && $_POST['enabled'] === 'true') ? 'true' : 'false';
        $cronTime = trim((string) $_POST['cron_time']);
        if ($cronTime === '' || !preg_match('/^\d{2}:\d{2}$/', $cronTime)) {
            $cronTime = '10:00';
        } elseif (!preg_match('/^\d{2}:00$/', $cronTime)) {
            $this->end(false, app::get('desktop')->_('发送时间须为整点（分钟须为 00），例如 14:00、15:00'));
        }

        app::get('financebase')->setConf('bill.match_fail_notify.enabled', $enabled);
        app::get('financebase')->setConf('bill.match_fail_notify.cron_time', $cronTime);

        if ($cronTime !== $oldCronTime) {
            $notifyLib->clearTodayCache();
        }

        $msg = app::get('desktop')->_('保存成功');
        if (preg_match('/^(\d{2}):00$/', $cronTime, $m) && (int) date('G') >= (int) $m[1]) {
            $msg .= '；今日 ' . $cronTime . ' 整点已过，将于明日该时刻自动发送';
        }

        $this->end(true, $msg);
    }
}
