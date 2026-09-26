<?php

/**
 * SD004 确认收货/退货上传数据组装
 */
class goldentra_push_receipt
{
    /**
     * 发货签收 → 确认收货(ZYZSTATUS=1)
     *
     * @param int $delivery_id
     * @return int|false
     */
    public function createFromDeliverySign($delivery_id)
    {
        $delivery_id = intval($delivery_id);
        if ($delivery_id <= 0) {
            return false;
        }

        $delivery = app::get('ome')->model('delivery')->db_dump(
            array('delivery_id' => $delivery_id, 'parent_id' => 0),
            'delivery_id,delivery_bn,shop_id,sign_time,delivery_time,status'
        );
        if (!$delivery || empty($delivery['delivery_bn'])) {
            return false;
        }

        $orderRel = kernel::database()->selectrow(
            'SELECT o.order_bn,o.shipping FROM sdb_ome_delivery_order do '
            . 'INNER JOIN sdb_ome_orders o ON o.order_id=do.order_id '
            . 'WHERE do.delivery_id=' . $delivery_id
        );

        $eventTime = !empty($delivery['sign_time'])
            ? intval($delivery['sign_time'])
            : (!empty($delivery['delivery_time']) ? intval($delivery['delivery_time']) : time());

        $zysfs = '1';
        if ($orderRel && !empty($orderRel['shipping'])) {
            // 自提类配送名粗判
            if (mb_strpos($orderRel['shipping'], '自提') !== false) {
                $zysfs = '2';
            }
        }

        $header = array(
            'zptdhyz' => $this->cut($delivery['delivery_bn'], 24),
            'bonnummer' => $this->cut($orderRel ? $orderRel['order_bn'] : '', 24),
            'vorgdatum' => date('Ymd', $eventTime),
            'vorgzeit' => date('His', $eventTime),
            'zyzstatus' => '1',
            'zysfs' => $zysfs,
        );

        return kernel::single('goldentra_push')->saveReceipt($header);
    }

    /**
     * 售后生成 → 确认退货(ZYZSTATUS=2)
     *
     * @param int $aftersale_id
     * @return int|false
     */
    public function createFromAftersale($aftersale_id)
    {
        $aftersale_id = intval($aftersale_id);
        if ($aftersale_id <= 0) {
            return false;
        }

        $aftersale = app::get('sales')->model('aftersale')->db_dump(
            array('aftersale_id' => $aftersale_id),
            'aftersale_bn,order_bn,reship_bn,delivery_bn,return_type,aftersale_time,acttime'
        );
        if (!$aftersale) {
            return false;
        }
        if (!in_array($aftersale['return_type'], array('return', 'refund'), true)) {
            return false;
        }

        $bizBn = !empty($aftersale['reship_bn'])
            ? $aftersale['reship_bn']
            : (!empty($aftersale['delivery_bn']) ? $aftersale['delivery_bn'] : $aftersale['aftersale_bn']);
        if ($bizBn === '' || $bizBn === null) {
            return false;
        }

        $eventTime = !empty($aftersale['aftersale_time'])
            ? intval($aftersale['aftersale_time'])
            : (!empty($aftersale['acttime']) ? intval($aftersale['acttime']) : time());

        $header = array(
            'zptdhyz' => $this->cut($bizBn, 24),
            'bonnummer' => $this->cut($aftersale['order_bn'], 24),
            'vorgdatum' => date('Ymd', $eventTime),
            'vorgzeit' => date('His', $eventTime),
            'zyzstatus' => '2',
            'zysfs' => '1',
        );

        return kernel::single('goldentra_push')->saveReceipt($header);
    }

    protected function cut($str, $len)
    {
        $str = (string) $str;
        if (strlen($str) <= $len) {
            return $str;
        }
        return substr($str, 0, $len);
    }
}
