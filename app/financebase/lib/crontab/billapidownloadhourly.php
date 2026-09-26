<?php
/**
 * 京东商家账单（360buy_bills）按小时入队，与 billapidownload（按天）互不影响。
 *
 * billapidownload：每天 10:30，bill_date=前一天，每店 1 条任务拉整日；
 * 本任务：同样每天 10:30、同样 bill_date=前一天，将整日按 4 小时一段拆任务入队（每店 6 条）。
 */
class financebase_crontab_billapidownloadhourly extends financebase_abstract_crontab
{
    /** 每段小时数 */
    const SLOT_HOURS = 4;

    public function __construct()
    {
        $this->_time_key = sprintf('%s_time', __CLASS__);
        parent::__construct();
    }

    public function process()
    {
        $oFunc = kernel::single('financebase_func');
        $list  = app::get('channel')->model('channel')->getList('*', array('channel_type' => 'ipay'));

        if (!$list) {
            return;
        }

        $tmp_shop_list = financebase_func::getShopList(financebase_func::getShopType());
        if (empty($tmp_shop_list)) {
            return;
        }

        $tmp_shop_list = array_column($tmp_shop_list, null, 'shop_id');
        $node_type_ref = $oFunc->getConfig('node_type');

        // 与 billapidownload 一致：对账日 = 前一天
        $bill_date = date('Y-m-d', strtotime('-1 day'));
        $day_base  = strtotime($bill_date . ' 00:00:00');

        foreach ($list as $v) {
            if (!$tmp_shop_list[$v['channel_bn']]) {
                continue;
            }
            if (!isset($node_type_ref[$v['node_type']])) {
                continue;
            }
            if ($node_type_ref[$v['node_type']] !== '360buy') {
                continue;
            }

            // 仅处理店铺配置创建的商家账单渠道（与钱包 ipay 渠道区分，避免同店重复入队）
            if (empty($v['biz_type']) || $v['biz_type'] !== '360buy_bills') {
                continue;
            }

            $config = $tmp_shop_list[$v['channel_bn']]['config'];
            $config = is_string($config) ? @unserialize($config) : $config;

            if (!$config || ($config['auto_download_merchant_bill'] ?? 'no') !== 'yes') {
                continue;
            }

            $slot_count = 24 / self::SLOT_HOURS;
            for ($slot = 0; $slot < $slot_count; $slot++) {
                $start_hour = $slot * self::SLOT_HOURS;
                $end_hour   = $start_hour + self::SLOT_HOURS - 1;
                $start_ts   = $day_base + $start_hour * 3600;
                $end_ts     = $day_base + $end_hour * 3600;
                $startTime  = date('Y-m-d H:00:00', $start_ts);
                $endTime    = date('Y-m-d H:59:59', $end_ts);
                $hour_slot  = $bill_date . '_' . sprintf('%02d-%02d', $start_hour, $end_hour);

                $queue_data = array(
                    'bill_date'        => $bill_date,
                    'startTime'        => $startTime,
                    'endTime'          => $endTime,
                    'shop_name'        => $v['channel_name'],
                    'shop_type'        => '360buy',
                    'shop_id'          => $v['channel_bn'],
                    'node_type'        => $v['node_type'],
                    'node_id'          => $v['node_id'],
                    'channel_id'       => $v['channel_id'],
                    'biz_type'         => '360buy_bills',
                    'import_shop_type' => '360buy_bills',
                );

                $queueData                = array();
                $queueData['queue_mode']  = 'billApiDownload';
                $queueData['create_time'] = time();
                $queueData['queue_name']  = sprintf('%s_%s_360buy_bills分段下载', $v['channel_name'], $hour_slot);
                $queueData['queue_data']  = $queue_data;
                $queueData['queue_no']    = $hour_slot;
                $queueData['shop_id']     = $v['channel_bn'];

                $queue_id = $this->oQueue->insert($queueData);
                $queue_id and financebase_func::addTaskQueue(array('queue_id' => $queue_id), 'billapidownload');
            }
        }
    }

    public function setTime()
    {
        $next_run_time = strtotime(date('Y-m-d', strtotime('+1 day')) . ' 10:30:00');

        $this->financeObj->store($this->_time_key, $next_run_time);
        return true;
    }
}
