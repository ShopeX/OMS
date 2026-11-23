<?php

/**
 * 监控：需发货订单-发货失败且全额退款，但未生成拦截入库单
 */
class monitor_autotask_timer_misslanjie
{
    const EVENT_TYPE = 'miss_lanjie_reship';
    const KV_KEY     = 'miss_lanjie_reship';
    const DEBOUNCE   = 1800; // 半小时间隔

    public function process($params, &$error_msg = '')
    {
        set_time_limit(0);
        ignore_user_abort(1);
        @ini_set('memory_limit', '512M');

        // 未配置/未开启模板时不查询数据
        $templateList = app::get('monitor')->model('event_template')->getList('template_id', [
            'event_type' => self::EVENT_TYPE,
            'status'     => '1',
            'disabled'   => 'false',
        ], 0, 1);
        if (!$templateList) {
            $error_msg = self::EVENT_TYPE . ' notice is close';
            return true;
        }

        $now = time();
        base_kvstore::instance('monitor')->fetch(self::KV_KEY, $lastExecTime);
        if ($lastExecTime && $lastExecTime > ($now - self::DEBOUNCE)) {
            $error_msg = '30分钟内不能重复执行';
            return false;
        }

        // 查询上次跑完之后的数据；首次默认回溯半小时
        $sinceTime = $lastExecTime ?: ($now - self::DEBOUNCE);
        $mask      = (int) ome_reship_const::__LANJIE_RUKU;
        $db        = kernel::database();

        // 与需发货订单「发货失败」一致，并限定全额退款；排除已有拦截入库单
        $sql = "SELECT o.order_id, o.order_bn, o.order_type, o.shop_type, o.logi_no, o.last_modified
                FROM sdb_ome_orders AS o
                WHERE o.status IN ('active', 'finish')
                  AND o.ship_status IN ('1', '2')
                  AND o.logi_no <> ''
                  AND o.createway = 'matrix'
                  AND o.sync = 'fail'
                  AND o.pay_status = '5'
                  AND o.last_modified > {$sinceTime}
                  AND EXISTS (
                      SELECT 1
                      FROM sdb_ome_delivery_order AS do
                      INNER JOIN sdb_ome_delivery AS d ON d.delivery_id = do.delivery_id
                      WHERE do.order_id = o.order_id
                        AND d.process = 'true'
                        AND d.parent_id = 0
                        AND d.disabled = 'false'
                  )
                  AND NOT EXISTS (
                      SELECT 1
                      FROM sdb_ome_reship AS r
                      WHERE r.order_id = o.order_id
                        AND (r.flag_type & {$mask}) > 0
                        AND r.is_check <> '5'
                  )
                ORDER BY o.last_modified DESC
                LIMIT 50";

        $orderList = $db->select($sql);
        if (empty($orderList)) {
            base_kvstore::instance('monitor')->store(self::KV_KEY, $now);
            $error_msg = '暂无缺失拦截单的订单';
            return true;
        }

        $missOrders = [];
        foreach ($orderList as $order) {
            // 与 orderRefundToLJRK 一致：天猫定制单不转拦截退货单
            if ($order['order_type'] == 'custom' && in_array($order['shop_type'], ['taobao', 'tmall'])) {
                continue;
            }
            // 延时拦截任务未执行完时不报警，避免误报
            if ($this->hasPendingLanjieTask($order['order_id'])) {
                continue;
            }
            $missOrders[] = $order;
        }

        if (empty($missOrders)) {
            base_kvstore::instance('monitor')->store(self::KV_KEY, $now);
            $error_msg = '暂无缺失拦截单的订单';
            return true;
        }

        $orderBns = array_column($missOrders, 'order_bn');
        $count    = count($orderBns);
        $showBns  = array_slice($orderBns, 0, 50);
        $bnText   = implode(',', $showBns);
        if ($count > 50) {
            $bnText .= '...等共' . $count . '单';
        }

        kernel::single('monitor_event_notify')->addNotify(self::EVENT_TYPE, [
            'order_bns' => $bnText,
            'count'     => $count,
        ]);

        base_kvstore::instance('monitor')->store(self::KV_KEY, $now);
        $error_msg = '发现' . $count . '单缺失拦截入库单';
        return true;
    }

    /**
     * 是否仍有未执行的延时拦截任务（delivery_lan_jie）
     */
    protected function hasPendingLanjieTask($orderId)
    {
        static $pendingMap = null;

        if ($pendingMap === null) {
            $pendingMap = [];
            $miscList   = app::get('ome')->model('misc_task')->getList('obj_id', [
                'obj_type' => 'delivery_lan_jie',
            ]);
            if ($miscList) {
                $deliveryIds = array_column($miscList, 'obj_id');
                $rows        = app::get('ome')->model('delivery_order')->getList('order_id', [
                    'delivery_id' => $deliveryIds,
                ]);
                foreach ($rows as $row) {
                    $pendingMap[$row['order_id']] = true;
                }
            }
        }

        return !empty($pendingMap[$orderId]);
    }
}
