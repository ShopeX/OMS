<?php
/**
 * 店铺收支明细匹配失败日报
 *
 * 定时任务每天检查近 30 个账单日（截止昨日 T-1），存在匹配失败时发送 1 封日报邮件。
 * 收件人与发送渠道在【系统预警】中配置预警模板与邮件组。
 */
class financebase_bill_match_notify
{
    const EVENT_TYPE = 'financebase_bill_match_fail';
    const DAILY_LOG_BILL_DATE = 'daily';

    /** 回溯检查的账单日数量（含昨日 T-1） */
    const LOOKBACK_DAYS = 30;

    /**
     * @param array $params bill_date 可选，指定单日发送；不传则检查近 30 天并发送 1 封日报
     * @param string $error_msg
     * @return bool
     */
    public function run($params = array(), &$error_msg = '')
    {
        if (!app::get('financebase')->is_installed()) {
            return true;
        }

        if (!$this->isEnabled()) {
            return true;
        }

        if (!app::get('monitor')->is_installed()) {
            $error_msg = 'monitor 应用未安装，无法发送预警通知';
            return false;
        }

        if (!empty($params['bill_date'])) {
            if (!$this->validateBillDate($params['bill_date'])) {
                $error_msg = '账单日期格式错误';
                return false;
            }

            return $this->sendForBillDate($params['bill_date'], $params, $error_msg);
        }

        $configCheck = $this->checkMonitorConfig();
        if (!$configCheck['ok']) {
            $error_msg = $configCheck['msg'];
            return false;
        }

        if (empty($params['force']) && $this->hasSentDailyToday()) {
            if (class_exists('financebase_func', false)) {
                financebase_func::writelog('匹配失败日报-批量入队', 'settlement', array(
                    'queued' => 0,
                    'skip_already_sent' => 1,
                    'failure_days' => count($this->aggregateFailuresGroupedByDay()),
                    'ok' => true,
                    'errors' => array(),
                ));
            }
            return true;
        }

        return $this->sendDaily($params, $error_msg);
    }

    /**
     * 发送指定账单日的匹配失败日报（该日无失败则跳过）
     *
     * @param string $billDate
     * @param array $params
     * @param string $error_msg
     * @param array|null $shopRows 已按店铺汇总的失败行（批量模式传入，避免重复查库）
     * @return bool
     */
    protected function sendForBillDate($billDate, $params = array(), &$error_msg = '', $shopRows = null)
    {
        $configCheck = $this->checkMonitorConfig();
        if (!$configCheck['ok']) {
            $error_msg = $configCheck['msg'];
            $this->writeLog($billDate, array(), 0, '', 'fail', $error_msg);
            return false;
        }

        if ($shopRows === null) {
            $shopRows = $this->aggregateByShop($billDate);
        }
        if (!$shopRows) {
            return true;
        }

        $totalFailCount = array_sum(array_column($shopRows, 'fail_count'));
        $notifyParams = array(
            'table_html' => $this->buildFailureTableHtml(
                $this->flattenFailureRows(array($billDate => $shopRows))
            ),
        );

        $receiverDesc = $this->describeReceivers();

        if (!kernel::single('monitor_event_notify')->addNotify(self::EVENT_TYPE, $notifyParams, false)) {
            $error_msg = '匹配失败日报入队失败';
            $this->writeLog($billDate, $shopRows, $totalFailCount, $receiverDesc, 'fail', $error_msg);
            return false;
        }

        $this->writeLog(
            $billDate,
            $shopRows,
            $totalFailCount,
            $receiverDesc,
            'succ',
            '已入队'
        );
        return true;
    }

    /**
     * 每天发送 1 封日报，正文表格列出近 30 个账单日内所有匹配失败的店铺汇总。
     *
     * @param array $params
     * @param string $error_msg
     * @return bool
     */
    protected function sendDaily($params = array(), &$error_msg = '')
    {
        $configCheck = $this->checkMonitorConfig();
        if (!$configCheck['ok']) {
            $error_msg = $configCheck['msg'];
            $this->writeLog(self::DAILY_LOG_BILL_DATE, array(), 0, '', 'fail', $error_msg);
            return false;
        }

        $failuresByDay = $this->aggregateFailuresGroupedByDay();
        if (!$failuresByDay) {
            return true;
        }

        $rows = $this->flattenFailureRows($failuresByDay);
        $totalFailCount = array_sum(array_column($rows, 'fail_count'));
        $receiverDesc = $this->describeReceivers();
        $notifyParams = array(
            'table_html' => $this->buildFailureTableHtml($rows),
        );

        if (!kernel::single('monitor_event_notify')->addNotify(self::EVENT_TYPE, $notifyParams, false)) {
            $error_msg = '匹配失败日报入队失败';
            $this->writeLog(self::DAILY_LOG_BILL_DATE, $rows, $totalFailCount, $receiverDesc, 'fail', $error_msg);
            return false;
        }

        $this->writeLog(self::DAILY_LOG_BILL_DATE, $rows, $totalFailCount, $receiverDesc, 'succ', '已入队');

        if (class_exists('financebase_func', false)) {
            financebase_func::writelog('匹配失败日报-批量入队', 'settlement', array(
                'queued' => 1,
                'skip_already_sent' => 0,
                'failure_days' => count($failuresByDay),
                'failure_rows' => count($rows),
                'total_fail_count' => $totalFailCount,
                'ok' => true,
                'errors' => array(),
            ));
        }

        return true;
    }

    public function isEnabled()
    {
        $enabled = app::get('financebase')->getConf('bill.match_fail_notify.enabled');
        return $enabled === 'true' || $enabled === true;
    }

    public function getCronTime()
    {
        $cronTime = app::get('financebase')->getConf('bill.match_fail_notify.cron_time');
        return ($cronTime === null || $cronTime === '') ? '10:00' : $cronTime;
    }

    /**
     * 定时任务当日缓存键（每天仅执行一次）
     *
     * @return string
     */
    public function buildDailyCacheKey()
    {
        return 'financebase_bill_match_notify_' . date('Y-m-d');
    }

    /**
     * 清除当日执行缓存（改发送时间且尚未到今日整点时，允许新时刻触发）
     */
    public function clearTodayCache()
    {
        cachecore::delete($this->buildDailyCacheKey());
    }

    /**
     * 今日发送情况摘要（配置页展示）
     *
     * @return array{pending_count:int,sent_today_count:int,failure_days:int,pending_dates:string[]}
     */
    public function getTodayStatusSummary()
    {
        $failureDates = $this->listFailureBillDatesInWindow();
        $pending = $this->hasSentDailyToday() ? array() : $failureDates;
        $sentToday = $this->hasSentDailyToday() ? 1 : 0;
        if ($this->isNotifyLogTableReady()) {
            $logMdl = app::get('financebase')->model('bill_match_notify_log');
            $sentToday = (int) $logMdl->count(array(
                'bill_date' => self::DAILY_LOG_BILL_DATE,
                'send_date' => date('Y-m-d'),
                'send_status' => 'succ',
            ));
        }

        return array(
            'pending_count' => count($pending),
            'sent_today_count' => $sentToday,
            'failure_days' => count($failureDates),
            'pending_dates' => $pending,
        );
    }

    public function getLookbackDays()
    {
        return self::LOOKBACK_DAYS;
    }

    /**
     * 检查 monitor 预警模板与邮件组是否已配置
     *
     * @return array{ok:bool,msg:string}
     */
    public function checkMonitorConfig()
    {
        if (!app::get('monitor')->is_installed()) {
            return array(
                'ok' => false,
                'msg' => 'monitor 应用未安装，请安装并配置系统预警后再启用本通知',
            );
        }

        if (!$this->hasActiveTemplate()) {
            return array(
                'ok' => false,
                'msg' => '请先在【系统预警 → 预警模板】新建并启用模板，事件类型选择「店铺收支明细匹配失败日报」，发送类型选择「邮箱」',
            );
        }

        if (!$this->hasReceiverGroup()) {
            return array(
                'ok' => false,
                'msg' => '请先在【系统预警 → 邮件组配置】关联事件「店铺收支明细匹配失败日报」并选择收件人',
            );
        }

        return array('ok' => true, 'msg' => '');
    }

    public function formatBillDateCn($billDate)
    {
        return date('Y年n月j号', strtotime($billDate));
    }

    /**
     * 近 LOOKBACK_DAYS 个账单日（截止 T-1）中仍有匹配失败的日期列表
     *
     * @return string[] Y-m-d
     */
    public function listFailureBillDatesInWindow()
    {
        return array_keys($this->aggregateFailuresGroupedByDay());
    }

    /**
     * 今日尚未成功发送、但窗口内仍有失败的账单日
     *
     * @return string[]
     */
    public function listPendingBillDatesToday()
    {
        return $this->hasSentDailyToday() ? array() : $this->listFailureBillDatesInWindow();
    }

    protected function validateBillDate($billDate)
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $billDate);
    }

    protected function hasSentTodayForBillDate($billDate)
    {
        if (!$this->isNotifyLogTableReady()) {
            return false;
        }

        $logMdl = app::get('financebase')->model('bill_match_notify_log');
        return $logMdl->count(array(
            'bill_date' => $billDate,
            'send_date' => date('Y-m-d'),
            'send_status' => 'succ',
        )) > 0;
    }

    protected function hasSentDailyToday()
    {
        return $this->hasSentTodayForBillDate(self::DAILY_LOG_BILL_DATE);
    }

    protected function isNotifyLogTableReady()
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $db    = kernel::database();
        $table = $db->prefix . 'financebase_bill_match_notify_log';
        $rows  = $db->select(
            'SELECT 1 FROM information_schema.tables'
            . ' WHERE table_schema = DATABASE()'
            . ' AND table_name = ' . $db->quote($table)
            . ' LIMIT 1'
        );
        $ready = !empty($rows);

        return $ready;
    }

    protected function hasActiveTemplate()
    {
        return app::get('monitor')->model('event_template')->count(array(
            'event_type' => self::EVENT_TYPE,
            'status' => '1',
            'disabled' => 'false',
        )) > 0;
    }

    protected function hasReceiverGroup()
    {
        $eventGroupTempMdl = app::get('monitor')->model('event_group_template');
        $groupTemps = $eventGroupTempMdl->getList('group_id', array(
            'filter_sql' => "FIND_IN_SET('" . self::EVENT_TYPE . "',event_type)",
        ));
        if (!$groupTemps) {
            return false;
        }

        $eventGroupMdl = app::get('monitor')->model('event_group');
        foreach ($groupTemps as $groupTemp) {
            $group = $eventGroupMdl->dump(array('group_id' => $groupTemp['group_id']));
            if (!empty($group['receiver_id'])) {
                return true;
            }
        }

        return false;
    }

    protected function describeReceivers()
    {
        $eventGroupTempMdl = app::get('monitor')->model('event_group_template');
        $groupTemps = $eventGroupTempMdl->getList('group_id', array(
            'filter_sql' => "FIND_IN_SET('" . self::EVENT_TYPE . "',event_type)",
        ));
        if (!$groupTemps) {
            return '';
        }

        $eventGroupMdl = app::get('monitor')->model('event_group');
        $eventReceiverMdl = app::get('monitor')->model('event_receiver');
        $receiverIds = array();

        foreach ($groupTemps as $groupTemp) {
            $group = $eventGroupMdl->dump(array('group_id' => $groupTemp['group_id']));
            if (empty($group['receiver_id'])) {
                continue;
            }
            $receiverIds = array_merge($receiverIds, explode(',', $group['receiver_id']));
        }

        $receiverIds = array_filter(array_unique($receiverIds));
        if (!$receiverIds) {
            return '';
        }

        $receivers = $eventReceiverMdl->getList('receiver', array('id' => $receiverIds));
        return implode(';', array_column($receivers, 'receiver'));
    }

    protected function getWindowTimeRange()
    {
        $endDate   = date('Y-m-d', strtotime('-1 day'));
        $startDate = date('Y-m-d', strtotime($endDate . ' -' . (self::LOOKBACK_DAYS - 1) . ' days'));
        $from      = strtotime($startDate . ' 00:00:00');
        $to        = strtotime($endDate . ' 00:00:00') + 86400;

        return array($from, $to, $startDate, $endDate);
    }

    /**
     * 近 30 账单日窗口内：按 trade_time 日期 + shop_id GROUP BY，再按 bill_date 分组
     *
     * @return array<string, array<int, array{shop_id:string,shop_name:string,fail_count:int}>>
     */
    protected function aggregateFailuresGroupedByDay()
    {
        list($from, $to) = $this->getWindowTimeRange();
        $table = kernel::database()->prefix . 'financebase_bill';

        $sql = "SELECT FROM_UNIXTIME(trade_time, '%Y-%m-%d') AS bill_date,
                       shop_id,
                       COUNT(*) AS fail_count
                FROM `{$table}`
                WHERE disabled='true'
                  AND trade_time >= {$from}
                  AND trade_time < {$to}
                GROUP BY bill_date, shop_id
                HAVING fail_count > 0
                ORDER BY bill_date ASC, fail_count DESC";

        $rows = kernel::database()->select($sql);
        if (!$rows) {
            return array();
        }

        $shopNames = $this->loadShopNames(array_column($rows, 'shop_id'));
        $grouped   = array();

        foreach ($rows as $row) {
            $billDate = $row['bill_date'];
            $shopId   = $row['shop_id'];
            if (!isset($grouped[$billDate])) {
                $grouped[$billDate] = array();
            }
            $grouped[$billDate][] = array(
                'shop_id'    => $shopId,
                'shop_name'  => isset($shopNames[$shopId]) ? $shopNames[$shopId] : $shopId,
                'fail_count' => intval($row['fail_count']),
            );
        }

        return $grouped;
    }

    protected function aggregateByShop($billDate)
    {
        $from = strtotime($billDate . ' 00:00:00');
        $to = $from + 86400;
        $table = kernel::database()->prefix . 'financebase_bill';

        $sql = "SELECT shop_id, COUNT(*) AS fail_count
                FROM `{$table}`
                WHERE disabled='true'
                  AND trade_time >= {$from}
                  AND trade_time < {$to}
                GROUP BY shop_id
                HAVING fail_count > 0
                ORDER BY fail_count DESC";

        $rows = kernel::database()->select($sql);
        if (!$rows) {
            return array();
        }

        $shopNames = $this->loadShopNames(array_column($rows, 'shop_id'));
        $result = array();

        foreach ($rows as $row) {
            $shopId = $row['shop_id'];
            $result[] = array(
                'shop_id' => $shopId,
                'shop_name' => isset($shopNames[$shopId]) ? $shopNames[$shopId] : $shopId,
                'fail_count' => intval($row['fail_count']),
            );
        }

        return $result;
    }

    protected function flattenFailureRows(array $failuresByDay)
    {
        $rows = array();
        foreach ($failuresByDay as $billDate => $shopRows) {
            $billDateCn = $this->formatBillDateCn($billDate);
            foreach ($shopRows as $row) {
                $rows[] = array(
                    'bill_date' => $billDate,
                    'bill_date_cn' => $billDateCn,
                    'shop_name' => isset($row['shop_name']) ? $row['shop_name'] : '',
                    'fail_count' => isset($row['fail_count']) ? (int) $row['fail_count'] : 0,
                );
            }
        }

        return $rows;
    }

    protected function buildFailureTableHtml(array $rows)
    {
        $html = '<table border="1" cellspacing="0" cellpadding="6" style="border-collapse:collapse;">'
            . '<thead><tr>'
            . '<th>账单日期</th>'
            . '<th>店铺</th>'
            . '<th>失败条数</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . $this->escapeHtml(isset($row['bill_date_cn']) ? $row['bill_date_cn'] : '') . '</td>'
                . '<td>' . $this->escapeHtml(isset($row['shop_name']) ? $row['shop_name'] : '') . '</td>'
                . '<td align="right">' . (int) (isset($row['fail_count']) ? $row['fail_count'] : 0) . '</td>'
                . '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    protected function escapeHtml($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    protected function loadShopNames($shopIds)
    {
        $shopIds = array_filter(array_unique((array) $shopIds));
        if (!$shopIds) {
            return array();
        }

        $shops = app::get('ome')->model('shop')->getList('shop_id,name', array('shop_id' => $shopIds));
        $map = array();
        foreach ($shops as $shop) {
            $map[$shop['shop_id']] = $shop['name'];
        }

        return $map;
    }

    protected function buildDetailUrl($billDate)
    {
        $query = http_build_query(array(
            'app' => 'financebase',
            'ctl' => 'admin_shop_settlement_bill',
            'act' => 'base',
            'bill_status' => 'fail',
            'time_from' => $billDate,
            'time_to' => $billDate,
        ));

        return kernel::base_url(1) . '/index.php?' . $query;
    }

    protected function writeLog($billDate, $shopRows, $totalFailCount, $receivers, $status, $resultMsg)
    {
        if (!$this->isNotifyLogTableReady()) {
            return;
        }

        $data = array(
            'bill_date' => $billDate,
            'send_date' => date('Y-m-d'),
            'shop_summary' => json_encode($shopRows, JSON_UNESCAPED_UNICODE),
            'total_fail_count' => intval($totalFailCount),
            'receivers' => $receivers,
            'send_status' => $status,
            'send_result' => $resultMsg,
            'send_time' => time(),
        );
        app::get('financebase')->model('bill_match_notify_log')->insert($data);
    }
}
