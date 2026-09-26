<?php

/**
 * 财务收款凭证上传数据组装
 */
class goldentra_push_voucher
{
    /**
     * 发货销售 → ZLX=1
     *
     * @param int $delivery_id
     * @return int|false
     */
    public function createFromDelivery($delivery_id)
    {
        $delivery_id = intval($delivery_id);
        if ($delivery_id <= 0) {
            return false;
        }

        $delivery = app::get('ome')->model('delivery')->db_dump(
            array('delivery_id' => $delivery_id, 'parent_id' => 0),
            'delivery_id,delivery_bn,shop_id,branch_id,delivery_time,sign_time,status'
        );
        if (!$delivery || empty($delivery['delivery_bn'])) {
            return false;
        }

        $orderRel = kernel::database()->selectrow(
            'SELECT o.order_bn FROM sdb_ome_delivery_order do '
            . 'INNER JOIN sdb_ome_orders o ON o.order_id=do.order_id '
            . 'WHERE do.delivery_id=' . $delivery_id
        );

        $sales = app::get('ome')->model('sales')->db_dump(
            array('delivery_id' => $delivery_id),
            'sale_bn,shop_id,branch_id,actually_amount,sale_amount,ship_time'
        );
        if (!$sales) {
            return false;
        }

        $eventTime = !empty($delivery['sign_time'])
            ? intval($delivery['sign_time'])
            : (!empty($delivery['delivery_time'])
                ? intval($delivery['delivery_time'])
                : (!empty($sales['ship_time']) ? intval($sales['ship_time']) : time()));
        $amount = isset($sales['actually_amount']) && $sales['actually_amount'] !== ''
            ? floatval($sales['actually_amount'])
            : floatval($sales['sale_amount']);

        $shop_id = !empty($sales['shop_id']) ? $sales['shop_id'] : $delivery['shop_id'];
        $branch_id = !empty($sales['branch_id']) ? $sales['branch_id'] : $delivery['branch_id'];

        $header = array(
            'zyzddh' => $this->cut($delivery['delivery_bn'], 64),
            'zlx' => '1',
            'zyddh' => $this->cut($orderRel ? $orderRel['order_bn'] : '', 64),
            'zmdbm' => $this->cut($this->resolveStoreBn($shop_id, $branch_id), 10),
            'waers' => 'CNY',
            'wrbtr' => round($amount, 2),
            'zshrq' => date('Ymd', $eventTime),
        );

        return kernel::single('goldentra_push')->saveVoucher($header);
    }

    /**
     * 售后退货/退款 → ZLX=2
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
            'aftersale_bn,shop_id,order_bn,reship_id,reship_bn,return_type,aftersale_time,acttime,refundmoney'
        );
        if (!$aftersale) {
            return false;
        }
        if (!in_array($aftersale['return_type'], array('return', 'refund'), true)) {
            return false;
        }

        $bizBn = !empty($aftersale['reship_bn'])
            ? $aftersale['reship_bn']
            : $aftersale['aftersale_bn'];
        if ($bizBn === '' || $bizBn === null) {
            return false;
        }

        $branch_id = 0;
        $reshipTotal = null;
        if (!empty($aftersale['reship_id'])) {
            $reship = app::get('ome')->model('reship')->db_dump(
                array('reship_id' => $aftersale['reship_id']),
                'branch_id,totalmoney'
            );
            if ($reship) {
                $branch_id = intval($reship['branch_id']);
                if (isset($reship['totalmoney']) && $reship['totalmoney'] !== '') {
                    $reshipTotal = $reship['totalmoney'];
                }
            }
        }

        $eventTime = !empty($aftersale['aftersale_time'])
            ? intval($aftersale['aftersale_time'])
            : (!empty($aftersale['acttime']) ? intval($aftersale['acttime']) : time());
        if (isset($aftersale['refundmoney']) && $aftersale['refundmoney'] !== '') {
            $amount = abs(floatval($aftersale['refundmoney']));
        } elseif ($reshipTotal !== null) {
            $amount = abs(floatval($reshipTotal));
        } else {
            $amount = 0;
        }

        $header = array(
            'zyzddh' => $this->cut($bizBn, 64),
            'zlx' => '2',
            'zyddh' => $this->cut($aftersale['order_bn'], 64),
            'zmdbm' => $this->cut($this->resolveStoreBn($aftersale['shop_id'], $branch_id), 10),
            'waers' => 'CNY',
            'wrbtr' => round($amount, 2),
            'zshrq' => date('Ymd', $eventTime),
        );

        return kernel::single('goldentra_push')->saveVoucher($header);
    }

    /**
     * 解析 OMS 门店编码：仓关联门店 > 仓编码 > 店铺编码
     *
     * @param string $shop_id
     * @param int $branch_id
     * @return string
     */
    protected function resolveStoreBn($shop_id, $branch_id)
    {
        $branch_id = intval($branch_id);
        if ($branch_id > 0) {
            $branch = app::get('ome')->model('branch')->db_dump(
                array('branch_id' => $branch_id),
                'branch_bn,store_id'
            );
            if ($branch) {
                if (!empty($branch['store_id'])) {
                    $store = app::get('o2o')->model('store')->db_dump(
                        array('store_id' => $branch['store_id']),
                        'store_bn'
                    );
                    if (!empty($store['store_bn'])) {
                        return trim((string) $store['store_bn']);
                    }
                }
                if (!empty($branch['branch_bn'])) {
                    return trim((string) $branch['branch_bn']);
                }
            }
        }
        if ($shop_id) {
            $shop = app::get('ome')->model('shop')->db_dump(array('shop_id' => $shop_id), 'shop_bn');
            if (!empty($shop['shop_bn'])) {
                return trim((string) $shop['shop_bn']);
            }
        }
        return '';
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
