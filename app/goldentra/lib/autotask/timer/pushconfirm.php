<?php

/**
 * 定时扫描：仅销售发货成功且已签收 → SD004 确认收货 + 财务凭证
 * 退货不做定时确认收货/凭证
 */
class goldentra_autotask_timer_pushconfirm
{
    public function process($params = array(), &$error_msg = '')
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(0);

        $limit = !empty($params['limit']) ? intval($params['limit']) : 50;
        if ($limit <= 0) {
            $limit = 50;
        }

        $mdl = app::get('goldentra')->model('sap_push_sales');
        $pushLib = kernel::single('goldentra_push');
        $ok = 0;
        $skip = 0;
        $fail = 0;

        // 仅销售：发货成功 + 确认收货未成功 + 发货单已签收
        $salesRows = $mdl->getList(
            'push_id,delivery_id,order_bn,zsalestype,sync_status,sync_status2,bonnummer',
            array(
                'zsalestype' => '01',
                'sync_status' => 'succ',
                'sync_status2|in' => array('wait', 'pending', 'fail'),
            ),
            0,
            $limit,
            'push_id ASC'
        );
        foreach ((array) $salesRows as $row) {
            $delivery_id = intval($row['delivery_id']);
            if ($delivery_id <= 0) {
                $skip++;
                continue;
            }
            $delivery = app::get('ome')->model('delivery')->db_dump(
                array('delivery_id' => $delivery_id),
                'sign_time,delivery_bn,shop_id'
            );
            if (!$delivery || empty($delivery['sign_time']) || intval($delivery['sign_time']) <= 0) {
                $skip++;
                continue;
            }

            $signTime = intval($delivery['sign_time']);
            $orderRel = kernel::database()->selectrow(
                'SELECT o.order_bn,o.shipping FROM sdb_ome_delivery_order do '
                . 'INNER JOIN sdb_ome_orders o ON o.order_id=do.order_id '
                . 'WHERE do.delivery_id=' . $delivery_id
            );
            $zysfs = '1';
            if ($orderRel && !empty($orderRel['shipping']) && mb_strpos($orderRel['shipping'], '自提') !== false) {
                $zysfs = '2';
            }

            $phase2 = array(
                'zyzstatus' => '1',
                'zysfs' => $zysfs,
                'confirm_datum' => date('Ymd', $signTime),
                'confirm_zeit' => date('His', $signTime),
                'sign_time' => $signTime,
                'bonnummer' => $orderRel ? $orderRel['order_bn'] : $row['bonnummer'],
                'zremarks1' => $orderRel ? $orderRel['order_bn'] : $row['order_bn'],
            );

            if ($pushLib->preparePhase2(intval($row['push_id']), $phase2)) {
                $voucherId = kernel::single('goldentra_push_voucher')->createFromDelivery($delivery_id);
                if ($voucherId) {
                    $pushLib->dispatch(goldentra_push::MODEL_VOUCHER, $voucherId);
                }
                $ok++;
            } else {
                $fail++;
            }
        }

        $error_msg = sprintf('销售确认收货扫描完成：成功%d，跳过%d，失败%d', $ok, $skip, $fail);
        return true;
    }
}
