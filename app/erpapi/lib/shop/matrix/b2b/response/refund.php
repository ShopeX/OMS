<?php
/**
 * @desc
 * @author:
 * @since:
 */
class erpapi_shop_matrix_b2b_response_refund extends erpapi_shop_response_refund {

    var $status = array(
        'apply' => array(
            'APPLY'  => '0',
            'VERIFY' => '1',
            'SUCC'   => '2',
            'REFUND' => '4',
            'FAIL'   => '3',
        ),
        'refund' => array(
            'SUCC'     => 'succ',
            'FAILED'   => 'failed',
            'CANCEL'   => 'cancel',
            'ERROR'    => 'error',
            'INVALID'  => 'invalid',
            'PROGRESS' => 'progress',
            'TIMEOUT'  => 'timeout',
            'READY'    => 'ready',
        )

    );

    protected function _formatAddParams($params)
    {
        // 兼容套娃换货退款, 获取实际退款订单号
        $params['tid'] = $this->_getActualTid($params);
        $this->__apilog['title'] = '店铺(' . $this->__channelObj->channel['name'] . ')退款业务处理[退款单：' . $params['refund_id'] . ']';
        $this->__apilog['original_bn'] = $params['tid'];
        $this->__apilog['result']['data'] = array('tid' => $params['tid'], 'refund_id' => $params['refund_id'], 'retry' => 'false');
        //todo check paymentype 检查支付方式是否已同步至退款申请单及退款单

        $t_ready = $params['t_begin'] ? $params['t_begin'] : $params['t_sent'];
        $t_ready = kernel::single('ome_func')->date2time($t_ready);

        $t_sent = $params['t_sent'] ? $params['t_sent'] : $params['t_ready'];
        $t_sent = kernel::single('ome_func')->date2time($t_sent);

        $t_received = kernel::single('ome_func')->date2time($params['t_received']);
        $t_received = $t_received ?: time();
        
        $sdf = array(
            'refund_bn' => $params['refund_id'],
            'order_bn' => $params['tid'],
            'status' => $this->status[$params['refund_type']][$params['status']],
            'refund_type' => $params['refund_type'],
            'money' => sprintf('%.2f', $params['refund_fee']),
            'cod_zero_accept' => false, //货到付款0元退款单是否接受
            'memo' => $params['memo'],
            'account' => $params['seller_account'],
            'bank' => $params['buyer_bank'],
            'pay_account' => $params['buyer_account'],
            'paycost' => 0,
            'cur_money' => $params['refund_fee'],
            'pay_type' => $params['pay_type'] ? $params['pay_type'] : 'online',
            'payment' => $params['payment_tid'],
            'paymethod' => $params['payment_type'],
            'trade_no' => $params['outer_no'],
            'oid' => $params['oid'],
            't_ready' => $t_ready ? $t_ready : time(),
            't_sent' => $t_sent ? $t_sent : time(),
            't_received' => $t_received,
            'update_order_payed' => true, //是否更新订单金额
            'version' => '',
            'refund_version_change' => false
        );
        if ($params['items']) {
            $params['items'] = json_decode($params['items'],true);
            $order = app::get('ome')->model('orders')->db_dump(['order_bn'=>$params['tid']],'order_id');
            $objList = app::get('ome')->model('order_objects')->getList('*',['order_id'=>$order['order_id']]);
            $order_items = app::get('ome')->model('order_items')->getList('*',['order_id'=>$order['order_id']]);
    
            if ($order_items){
                $tmp_items = array();
                foreach ($order_items as $i_key=>$i_val){
                    $tmp_items[$i_val['obj_id']][] = $i_val;
                }
                $order_items = NULL;
            }
    
            if ($objList){
                foreach ($objList as $o_key=>&$o_val){
                    $o_val['order_items'] = $tmp_items[$o_val['obj_id']];
                }
            }
            $objList = array_column($objList,null,'oid');
    
            $productData = array();
            foreach ($params['items'] as $key => $val) {
                if ($objList[$val['oid']]) {
                    $obj = $objList[$val['oid']];
                    foreach ($obj['order_items'] as $itemKey => $itemVal) {
                        $item = [];
                        $item['order_item_id'] = $itemVal['item_id'];
                        $item['num'] = $val['number'] ?? $itemVal['nums'];
                        $item['product_id'] = $itemVal['product_id'];
                        $item['bn'] = $val['sku_bn'] ?? $itemVal['bn'];
                        $item['name'] = $val['sku_name'] ?? $itemVal['name'];
                        $item['price'] = $val['price'] ?? $itemVal['sale_price'];
                        $item['oid'] = $val['oid'];
                        $item['item_type'] = $itemVal['item_type'];
                        $item['obj_id'] = $itemVal['obj_id'];
                        $item['divide_order_fee'] = $itemVal['divide_order_fee'];
                        $productData[] = $item;
                    }
                }
            }
            if ($productData) {
                $sdf['product_data'] = $productData;
                $sdf['bn']  = implode(',', array_column((array)$productData, 'bn'));
                $sdf['oid']  = implode(',', array_column((array)$productData, 'oid'));
                $sdf['obj_id']  = implode(',', array_column((array)$productData, 'obj_id'));
            }
        }
        return $sdf;
    }

    protected function _getActualTid($params)
    {
        $refundApplyMdl = app::get('ome')->model('refund_apply');
        // 查询退款申请表是否有此单
        $filter = [
            'refund_apply_bn' => $params['refund_id'],
            'shop_id' => $this->__channelObj->channel['shop_id']
        ];

        $refundApply = $refundApplyMdl->dump($filter,'apply_id,refund_apply_bn,order_id,archive');
        
        // 没有则原样返回
        if(!$refundApply){
            return $params['tid'];
        }
        
        // 查订单号
        if($refundApply['archive']){
            $orderMdl = app::get('archive')->model('orders');
        }else{
            $orderMdl = app::get('ome')->model('orders');
        }
       
        $order = $orderMdl->dump($refundApply['order_id'],'order_bn');
        

        // 没有则原样返回
        if(!$order){
            return $params['tid'];
        }
        
        return $order['order_bn'];
    }
    
} 