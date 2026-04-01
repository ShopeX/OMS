<?php
/**
 * Created by PhpStorm.
 * User: yaokangming
 * Date: 2018/12/07
 * Time: 15:02
 */

class erpapi_shop_matrix_b2b_request_finance extends erpapi_shop_request_finance
{
    /**
     * 对应第三方B2B接口文档, b2b.refund.create 同意退款单 接口
     * @param $refund
     * @return string[]|void
     */
    public function addRefund($refund)
    {
        $rs = array('rsp' => 'fail', 'msg' => '', 'data' => '');
        if (!$refund) {
            $rs['msg'] = 'no refund';
            return $rs;
        }
        
        // 没有金额则不回传
//        if (!isset($refund['money']) || bccomp($refund['money'], 0, 3) === 0) {
//            $rs['msg'] = '退款信息缺失金额,不回传';
//            return $rs;
//        }
        
        $params = array();
        $params = $this->_getAddRefundParams($refund);
        // 直连请求暂不支持异步回调
        $callback = array();

        $callbackParams = array(
            'shop_id' => $refund['shop_id'],
            'tid' => $params['order_bn'],
            'refund_apply_id' => $refund['refund_apply_id']
        );

        $title = '店铺(' . $this->__channelObj->channel['name'] . ')添加[交易退款单(金额:' . $refund['money'] . ')](订单号:' . $params['order_bn'] . '退款单号:' . $refund['refund_bn'] . ')';
        $result = $this->__caller->call(SHOP_ADD_REFUND_RPC, $params, $callback, $title, 10, $params['order_bn']);
        // 直连情况下,执行callback函数
        $result = $this->addRefundCallback($result, $callbackParams);
        return $result;
    }

    /**
     * 退款单添加参数组装
     * @param $refund
     * @return array
     */
    public function _getAddRefundParams($refund)
    {
        // 订单信息
        $orderModel = app::get('ome')->model('orders');
        $order = $orderModel->dump($refund['order_id'], 'order_id,order_bn,member_id,shop_id,createway,relate_order_bn');
        
        // 订单号处理,兼容套娃换货退款情况
        $orderBn  = $this->_getOriginalOrderBn($order, $refund);
        
        // 会员
        $memberModel = app::get('ome')->model('members');
        $member = $memberModel->dump(array('member_id' => $order['member_id']), 'uname,name,member_id');
        $params = array();
        $params['account'] = $refund['account'] ? $refund['account'] : '';
        $params['bank'] = $refund['bank'] ? $refund['bank'] : '';
        $params['buyer_id'] = $member['account']['uname'];#买家会员帐号
        $params['cur_money'] = $refund['cur_money'] ? $refund['cur_money'] : $refund['money'];
        $params['currency'] = $refund['currency'] ? $refund['currency'] : 'CNY';
        $params['money'] = $refund['money'];
        $params['order_bn'] = $orderBn;
        $params['pay_account'] = $refund['pay_account'] ? $refund['pay_account'] : '';
        $params['pay_name'] = $refund['payment'] ?? '';
        $params['pay_type'] = $refund['pay_type'] ? $refund['pay_type'] : '';
        $params['refund_bn'] = $refund['refund_bn'];
     //   $params['status'] = $refund['status'] ? strtoupper($refund['status']) : '';
        $params['t_begin'] = isset($refund['t_ready']) ? date("Y-m-d H:i:s", $refund['t_ready']) : date("Y-m-d H:i:s");
        $params['t_payed'] = isset($refund['t_payed']) ? date("Y-m-d H:i:s", $refund['t_payed']) : '';
        $params['t_confirm'] = date("Y-m-d H:i:s");
        //获取售后申请单号
        if($refund['return_id']){
           $return = app::get('ome')->model('return_product')->dump(array('return_id'=>$refund['return_id']),'return_bn');
        }
        $params['memo'] = $return['return_bn']?$return['return_bn']:'';

        $params['trade_no'] = $refund['trade_no'] ? $refund['trade_no'] : '';

        $items = $this->__getRefundItems($refund);
        $params['items'] = json_encode($items); 
        return $params;
    }

    /**
     * 获取原始订单号
     * @param $order 
     */
    protected function _getOriginalOrderBn($order, $refund)
    {
        // 原样返回
        if(!isset($order['createway']) || $order['createway'] =='matrix' || !isset($order['relate_order_bn']) || !$order['relate_order_bn']){
            return $order['order_bn'];
        }
        
        $filter = array(
            'order_bn' => $order['relate_order_bn'],
        );
     
        if($refund['archive']){
            $archive_ordObj = kernel::single('archive_interface_orders');
            $originalOrder = $archive_ordObj->getOrders($filter, 'order_bn,createway,relate_order_bn');
        }else{
            $orderMdl = app::get('ome')->model('orders');
            $originalOrder = $orderMdl->dump($filter, 'order_bn,createway,relate_order_bn');
        }
        
        // 没有原始订单则返回
        if(!$originalOrder){
            return $order['order_bn'];
        }elseif($originalOrder['createway'] == 'matrix'){
            return $originalOrder['order_bn'];
        }
        // 仍非平台订单,则再次调用查询
        elseif($originalOrder['relate_order_bn']){
            return $this->_getOriginalOrderBn($originalOrder);
        }
        // 兜底返回原样
        else{
            return $order['order_bn'];
        }
    }

    /**
     * 退款单明细,参数组装
     * todo 售后退款单缺失明细,
     * @param $refund
     * @return mixed
     */
    private function __getRefundItems($refund)
    {
        
        if(!$refund['product_data']){
            return $this->__getAllRefundItems($refund);
        }

        $itemList = unserialize($refund['product_data']);
        if (!is_array($itemList) || empty($itemList)) {
            return $this->__getAllRefundItems($refund);
        }

        $objects = [];
        foreach ($itemList as $item) {
            // 兼容oid不存在
            if (!$item['oid'] && $item['obj_id']) {
                $objMdl = app::get('ome')->model('order_objects');
                $obj = $objMdl->dump($item['obj_id'], 'oid');
                if ($obj) {
                    $item['oid'] = $obj['oid'];
                }
            }
            $tmp = [
                'oid' => $item['oid'],
                'sku_bn' => $item['bn'],
                'sku_name' => $item['name'],
                'price' => $item['price'],
                'number' => $item['num'],
                'sku_uuid'=>$item['sku_uuid'],

            ];
            $objects[] = $tmp;
        }
    
        return $objects;
    }

    private function __getAllRefundItems($refund)
    {
        $orderObjMdl = app::get('ome')->model('order_objects');
        $filter = ['order_id' => $refund['order_id']];
        $objects = $orderObjMdl->getList('oid,bn as sku_bn,name as sku_name,quantity as number,price', $filter);

        return $objects;
    }

    /**
     * 退款单添加回调
     * @param $response
     * @param $callback_params
     * @return array
     */
    public function addRefundCallback($response, $callback_params)
    {
        $status = $response['rsp'];
        if ($status != 'succ') {
            $shop_id = $callback_params['shop_id'];
            $order_bn = $callback_params['tid'];
            $refund_apply_id = $callback_params['refund_apply_id'];
            $oOrder = app::get('ome')->model('orders');
            $order_detail = $oOrder->dump(array('order_bn' => $order_bn, 'shop_id' => $shop_id), 'order_id,pay_status');
           
            if (!$order_detail) {
                $oOrder = app::get('archive')->model('orders');
                $order_detail = $oOrder->dump(array('order_bn' => $order_bn, 'shop_id' => $shop_id), 'order_id,pay_status');
            }
            $order_id = $order_detail['order_id'];
            //状态回滚，变成已支付/部分付款/部分退款
            kernel::single('ome_order_func')->update_order_pay_status($order_id, true, __CLASS__.'::'.__FUNCTION__);
            #bugfix:解决如果退款单请求先到并生成单据于此同时由于网络超时造成退款申请失败，从而造成退款申请单状态错误问题。
            $refund_applyObj = app::get('ome')->model('refund_apply');
            $refundapply_detail = $refund_applyObj->getList('refund_apply_bn', array('apply_id' => $refund_apply_id));
            $refundsObj = app::get('ome')->model('refunds');
            $refunds_detail = $refundsObj->getList('refund_id', array('refund_bn' => $refundapply_detail[0]['refund_apply_bn'], 'status' => 'succ'));
            if (!$refunds_detail) {
                $refund_applyObj->update(array('status' => '6'), array('status|notin' => array('4'), 'apply_id' => $refund_apply_id));
                //操作日志
                $oOperation_log = app::get('ome')->model('operation_log');
                $oOperation_log->write_log('order_refund@ome', $order_id, '订单:' . $order_bn . '发起退款请求,前端拒绝退款，退款失败');
            }
        }
        return $this->callback($response, $callback_params);
    }

    /**
     * 更新退款单状态,对应第三方B2B接口文档, b2b.refund.refuse 退款拒绝 接口
     * @param $refund
     * @param $status
     * @param string $mod
     * @return array|false
     */
    public function updateRefundApplyStatus($refund, $status, $mod = 'sync')
    {
        $rs = parent::updateRefundApplyStatus($refund, $status, $mod);
        return $rs;
    }

    /**
     * 退款申请单状态同步接口名
     * @param string $status 2:已接受申请、3:已拒绝
     * @return [type]         [description]
     */
    protected function _updateRefundApplyStatusApi($status, $refundInfo = null)
    {
        $api_method = '';
        switch ($status) {
            case '3':
                $api_method = SHOP_REFUSE_REFUND;#拒绝退款接口
                break;
        }
        return $api_method;
    }

    /**
     * 退款申请单状态同步参数
     * @param $refund
     * @param $status
     * @return array
     */
    public function _updateRefundApplyStatusParam($refund, $status)
    {
        $params = array();
        $params['order_bn'] = $refund['order_bn'];
        $params['refund_bn'] = $refund['refund_bn'];
        $params['status'] = $status;
        return $params;
    }
} 