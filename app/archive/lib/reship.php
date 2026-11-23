<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
* 换货类
*
* @author sunjing<sunjing@shopex.cn>
*/
class archive_reship
{

    public function __construct(){
        
        $this->db = kernel::database();
        
    }

    /**
     * 取消退款申请
     *
     * @param String $apply_id 退款申请ID
     * @param String $memo 取消理由
     * @return void
     * @author
     **/
    public function cancelRefundApply($apply_id,$memo='')
    {
        $refundApplyModel = app::get('ome')->model('refund_apply');
        $applyUpdate = array(
            'status' => '3',
            'memo'=>$memo
        );
        $refundApplyModel->update($applyUpdate,array('apply_id'=>$apply_id));
    }

    /**
     * @description 退换货申请退款生成退款单据
     * @access public
     * @param void
     * @return void
     */
    public function createRefund($refundApply,$order) 
    {
        # 更新退款金额
        $orderModel = app::get('archive')->model('orders');
        $payed = $order['payed'] - $refundApply['money'];
        $payed = ( $payed > 0 ) ? $payed : 0;
        $orderModel->update(array('payed'=>$payed),array('order_id'=>$order['order_id']));

        $opLogModel = app::get('ome')->model('operation_log');
        $opLogModel->write_log('order_modify@ome',$order['order_id'],"售后退款成功，更新订单退款金额。系统自动操作，退款金额用于支付新订单。");

        # 退款申请单处理
        $refundApplyUpdate = array(
            'status' => '4',
            'refunded' => $refundApply['money'],
            'last_modified' => time(),
            'account' => $refundApply['account'],
            'pay_account' => $refundApply['pay_account'],
        );
        $refundApplyModel = app::get('ome')->model('refund_apply');
        $refundApplyModel->update($refundApplyUpdate,array('apply_id'=>$refundApply['apply_id']));

        $opLogModel->write_log('refund_apply@ome',$refundApply['apply_id'],"售后退款成功，更新退款申请状态。系统自动操作，退款金额用于支付新订单。");

        # 退款单处理
        $paymethods = ome_payment_type::pay_type();
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $refunddata = array(
            'refund_bn' => $refundApply['refund_apply_bn'],
            'order_id' => $order['order_id'],
            'shop_id' => $order['shop_id'],
            'account' => $refundApply['account'],
            'bank' => $refundApply['bank'],
            'pay_account' => $refundApply['pay_account'],
            'currency' => $order['currency'],
            'money' => $refundApply['money'],
            'paycost' => 0,
            'cur_money' => $refundApply['money'],
            'pay_type' => $refundApply['pay_type'],
            'payment' => $refundApply['payment'],
            'paymethod' => $paymethods[$refundApply['pay_type']],
            'op_id' => $opInfo['op_id'],
            't_ready' => time(),
            't_sent' => time(),
            'memo' => $refundApply['memo'],
            'status' => 'succ',
            'refund_refer' => '1',
            'return_id' => $refundApply['return_id'],
        );
        $oRefund = app::get('ome')->model('refunds');
        $oRefund->save($refunddata);

        // 更新订单支付状态
        kernel::single('archive_order_func')->update_order_pay_status($order['order_id']);
        $opLogModel->write_log('refund_accept@ome',$refunddata['refund_id'],"售后退款成功，生成退款单".$refunddata['refund_bn']."，退款金额用于支付新订单。");
    }

    /**
     * 取消补差价订单
     *
     * @param Int $order_id 订单ID
     * @param String $shop_id 店铺ID
     * @return void
     * @author
     **/
    public function cancelDiffOrder($order_id,$shop_id,$memo='')
    {
        define('FRST_TRIGGER_OBJECT_TYPE','订单：订单作为补差价订单取消');
        define('FRST_TRIGGER_ACTION_TYPE','ome_ctl_admin_order：do_cancel');


        $c2c_shop_list = ome_shop_type::shop_list();

        $node_type = app::get('ome')->model('shop')
                        ->select()->columns('node_type')
                        ->where('shop_id=?',$shop_id)
                        ->instance()->fetch_one();

        $mod = in_array($node_type,$c2c_shop_list) ? 'async' : 'sync';

        return app::get('ome')->model('orders')->cancel($order_id,$memo,true,$mod, false);
    }

    /**
     * 对换货订单进行支付操作
     *
     * @param Array $order 订单信息
     * @return void
     * @author
     **/
    public function payChangeOrder($order)
    {
        $mathLib      = kernel::single('eccommon_math');
        $orderModel   = app::get('ome')->model('orders');
        $paymentModel = app::get('ome')->model('payments');

        $orderdata = array(
            'order_id' => $order['order_id'],
            'pay_status' => $order['pay_status'],
            'paytime' => time(),
        );

        # 支付配置
        //$cfg = $this->app->model('payment_cfg')->dump();
        $cfg = array();

        $orderdata['pay_bn'] = $cfg['pay_bn'];

        $orderdata['payed'] = $mathLib->getOperationNumber($order['pay_money']);

        $orderdata['payment'] = '线下支付';

        $orderModel->update($orderdata,array('order_id'=>$order['order_id']));

        //日志
        $memo = '做质检时连带操作;订单付款操作,用订单('.$order['reship_order_bn'].')的退款金额作支付金额';
        $oOperation_log = app::get('ome')->model('operation_log');
        $oOperation_log->write_log('order_modify@ome',$order['order_id'],$memo);

        //生成支付单
        $payment_bn = $paymentModel->gen_id();
        $paymentdata = array();
        $paymentdata['payment_bn']  = $payment_bn;
        $paymentdata['order_id']    = $order['order_id'];
        $paymentdata['shop_id']     = $order['shop_id'];
        $paymentdata['account']     = '';
        $paymentdata['bank']        = '';
        $paymentdata['pay_account'] = '';
        $paymentdata['currency']    = $order['currency'];
        $paymentdata['money']       = $order['pay_money'];
        $paymentdata['paycost']     = 0;
        $curr_time                  = time();
        $paymentdata['t_begin']     = $curr_time;//支付开始时间
        $paymentdata['t_end']       = $curr_time;//支付结束时间
        $paymentdata['trade_no']    = '';//支付网关的内部交易单号，默认为空
        $paymentdata['cur_money']   = $paymentdata['money'];
        $paymentdata['pay_type']    = 'offline';
        $paymentdata['payment']     = '';
        $paymentdata['paymethod']   = '线下支付';
        $paymentdata['payment_refer'] = '1';

        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $paymentdata['op_id'] = $opInfo['op_id'];

        $paymentdata['ip'] = kernel::single("base_request")->get_remote_addr();
        $paymentdata['status'] = 'succ';
        $paymentdata['memo'] = '做质检时连带操作;系统生成换货订单支付单据;通过退款金额进行支付;补换货的订单:'.$order['reship_order_bn'];
        $paymentdata['is_orderupdate'] = 'false';
        $paymentModel->create_payments($paymentdata);

        //日志
        $oOperation_log->write_log('payment_create@ome',$paymentdata['payment_id'],'生成支付单');
    }

    /**
     * @description 判断是否为反审单据
     * @access public
     * @param void
     * @return void
     */
    public function is_precheck_reship($is_check,$need_sv='true') 
    {
        return ($is_check=='0' && $need_sv == 'false') ? true : false;
    }

    function finish_aftersale($Reshipitem,$order_id){
        $oOrder = app::get('archive')->model('orders');
        $oItemModel = app::get('archive')->model('order_items');
        foreach($Reshipitem as $k=>$v){
            $bn = $v['bn'];
            $itemsql = "SELECT sendnum,bn,item_id, return_num FROM sdb_archive_order_items 
                                        WHERE order_id='".$order_id."' AND bn='$bn' AND sendnum != return_num";
            $orderItems=$this->db->select($itemsql);
            $num = intval($v['normal_num']+$v['defective_num']);

            $residue_num    = 0;//剩余退货量

            foreach ($orderItems as $ivalue) {
                if($num <= 0) break;

                $residue_num    = intval($ivalue['sendnum'] - $ivalue['return_num']);//剩余数量=已发货量-已退货量

                if ($num > $residue_num) {
                    $num -= $residue_num;
                    #更新_已退货量 = 已发货量
                    $oItemModel->update(array('return_num' => $ivalue['sendnum']),array('item_id'=>$ivalue['item_id']));
                    
                } else {
     
                    #更新_已退货量 = 已退货量 + 本次退货量
                    $oItemModel->update(array('return_num' => ($ivalue['return_num'] + $num)),array('item_id'=>$ivalue['item_id']));
                    
                    $num = 0;
                }
            }
                
        }
        $order_sum = $this->db->selectrow("SELECT sum(sendnum) as count FROM sdb_archive_order_items WHERE order_id='".$order_id."' AND sendnum != return_num");
        $orders    = $oOrder->dump(array('order_id'=>$order_id));
        if(intval($order_sum['count']) == 0)
        {
            if($orders['ship_status'] == '2' || $orders['ship_status'] == '3')
            {
                $sql    = "SELECT sum(nums - sendnum) as count FROM sdb_archive_order_items WHERE order_id = '".$order_id."' AND nums != sendnum";#归档订单明细无delete字段
                $order_sum    = $this->db->selectrow($sql);
            }
        }
        $ship_status = (intval($order_sum['count']) == 0) ? '4' : '3';
        $oOrder->update(array('ship_status'=>$ship_status),array('order_id'=>$order_id));

    }
}