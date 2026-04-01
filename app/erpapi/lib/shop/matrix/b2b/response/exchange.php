<?php

/**
 * B2B 换货
 * Class erpapi_shop_matrix_b2b_response_exchange
 */
class erpapi_shop_matrix_b2b_response_exchange extends erpapi_shop_response_exchange
{
    protected function _formatAddParams($params)
    {
        $sdf        = $params;
        $b2bSdf = array(
            'oid'                  => $sdf['tid'],//订单号
            'return_bn'            => $sdf['dispute_id'],//换货单号ID
            'status'               => $sdf['status'],//换货状态
            'reason'               => $sdf['reason'],//换货原因申请理由
            'comment'              => $sdf['desc'],//换货理由补充说明
            'modified'             => $sdf['modified'] ? kernel::single('ome_func')->date2time($sdf['modified']) : '',//修改时间
            'created'              => $sdf['created'] ? kernel::single('ome_func')->date2time($sdf['created']) : time(),//创建时间
            'buyer_name'           => $sdf['buyer_name'],//买家名称
            'buyer_nick'           => $sdf['buyer_nick'],//买家昵称
            'desc'                 => $sdf['desc'],//换货理由补充说明
            'logistics_no'         => $sdf['buyer_logistic_no'] ? $sdf['buyer_logistic_no'] : '',//买家发货物流单号
            'buyer_address'        => $sdf['buyer_address'] ? $sdf['buyer_address'] : '',//买家换货地址
            'logistics_company'    => $sdf['buyer_logistic_name'] ? $sdf['buyer_logistic_name'] : '',//买家发货物流公司名称
            'buyer_phone'          => $sdf['buyer_phone'] ? $sdf['buyer_phone'] : '',//买家联系方式
            'seller_address'       => $sdf['address'] ? $sdf['address'] : '',//卖家换货地址
            'seller_logistic_no'   => $sdf['seller_logistic_no'] ? $sdf['seller_logistic_no'] : '',//卖家发货快递单号
            'seller_logistic_name' => $sdf['seller_logistic_name'] ? $sdf['seller_logistic_name'] : '',//卖家发货物流公司名称
            'bought_bn'            => $sdf['bought_bn'],//购买商品货号
            'title'                => $sdf['title'],//商品名称
            'num'                  => $sdf['num'],//换货数量
            'price'                => $sdf['price'],//换货价格
            'exchange_bn'          => $sdf['exchange_bn'],//换货货号
            'time_out'             => $sdf['time_out'],//换货业务超时时间
            'shop_type'            => $this->__channelObj->channel['node_type'],
            'shop_id'              => $this->__channelObj->channel['shop_id'],
        );
        
        $formatSdf     = parent::_formatAddParams($params);
        $b2bSdf    = array_merge($formatSdf, $b2bSdf);
        $orders_detail = $this->getOrderByoid($this->__channelObj->channel['shop_id'], $sdf);
        if (!$orders_detail) {
            $this->__apilog['result']['msg'] = '订单不存在';
            return array();
        }
        $b2bSdf['order'] = array(//o.order_id,o.status,o.process_status,o.ship_status,o.pay_status
            'order_id'       => $orders_detail['order_id'],
            'status'         => $orders_detail['status'],
            'process_status' => $orders_detail['process_status'],
            'ship_status'    => $orders_detail['ship_status'],
            'pay_status'     => $orders_detail['pay_status'],
            'order_bn'       => $orders_detail['order_bn'],
        );
        if ($orders_detail['tran_type'] == 'archive') {
            $b2bSdf['order']['tran_type'] = 'archive';
        }
        $return_items = array();
        foreach ($orders_detail['item_list'] as $o_v) {
            $price          = round($o_v['divide_order_fee'] / $o_v['nums'], 2);
            $radio          = $sdf['num'] / $o_v['quantity'];
            $return_items[] = array(
                'bn'            => $o_v['item_bn'],
                'name'          => $o_v['name'],
                'product_id'    => $o_v['product_id'],
                'num'           => $o_v['obj_type'] == 'pkg' ? (int)($radio * $o_v['nums']) : $sdf['num'],
                'price'         => $price,//换货目前价格就为0
                'sendNum'       => $o_v['sendnum'],
                'order_item_id' => $o_v['item_id'],
                'sku_uuid'      => $sdf['sku_uuid'],

            );
        }
        
        $change_items = array();
        
        
        if ($params['exchange_bn']) {
            $change_items[] = array(
                'bn'    => $params['exchange_bn'],
                'num'   => $params['num'],
                'price' => floatval($params['price']),
            );
        }
        $b2bSdf['change_items'] = $change_items;
        $b2bSdf['return_items'] = $return_items;
        return $b2bSdf;
    }
    
    protected function _returnProductAdditional($sdf)
    {
        $ret = array(
            'model' => 'return_apply_special',
            'data'  => array(
                'special' => json_encode(array(
                    'shop_id'               => $sdf['shop_id'],
                    'buyer_nick'            => $sdf['buyer_nick'],
                    'buyer_logistic_no'     => $sdf['logistics_no'],
                    'buyer_address'         => $sdf['buyer_address'],
                    'buyer_logistic_name'   => $sdf['logistics_company'],
                    'buyer_phone'           => $sdf['buyer_phone'],
                    'seller_address'        => $sdf['seller_address'],
                    'seller_logistic_no'    => $sdf['seller_logistic_no'],
                    'seller_logistic_name'  => $sdf['seller_logistic_name'],
                    'exchange_sku'          => $sdf['exchange_bn'],
                    'current_phase_timeout' => $sdf['time_out'] ? strtotime($sdf['time_out']) : '',
                    'refund_version'        => $sdf['refund_version'],
                    'refund_type'           => 'change',
                    'exchange_num'          => $sdf['num'],
                    'exchange_price'        => floatval($sdf['price']),
                    'oid'                   => $sdf['oid'],
                ), JSON_UNESCAPED_UNICODE)
            )
        );
        return $ret;
    }
    
    public function getOrderByoid($shop_id, $sdf)
    {
        $orderModel = app::get('ome')->model('orders');
        $order      = $orderModel->db->selectrow("SELECT o.order_bn,o.order_id,o.status,o.process_status,o.ship_status,o.pay_status FROM sdb_ome_orders as o  WHERE  o.order_bn='" . $sdf['tid'] . "' AND o.shop_id='" . $shop_id . "' ");
        if (!$order) {
            return false;
        }
        $items_list = $orderModel->db->select("SELECT ob.bn,ob.obj_type,ob.obj_id,ob.quantity,ob.sale_price,i.bn as item_bn,i.name,i.product_id,i.nums,i.sale_price,i.sendnum,i.divide_order_fee,i.item_id FROM sdb_ome_order_objects as ob  left join sdb_ome_order_items as i on ob.obj_id=i.obj_id  WHERE ob.order_id=" . $order['order_id'] . " AND ob.bn='" . $sdf['bought_bn'] . "'  AND i.delete='false'");
        if (!$items_list) {
            return false;
        }
        
        $order['item_list'] = $items_list;
        
        return $order;
    }
    
} 