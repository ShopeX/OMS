<?php
/**
 *
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_matrix_b2b_response_order extends erpapi_shop_response_order
{
    var $status = array('TRADE_ACTIVE' => 'active', 'TRADE_CLOSED' => 'dead', 'TRADE_FINISHED' => 'finish');
    var $pay_status = array('PAY_NO'        => 0,
                            'PAY_FINISH'    => 1,
                            'PAY_TO_MEDIUM' => 2,
                            'PAY_PART'      => 3,
                            'REFUND_PART'   => 4,
                            'REFUND_ALL'    => 5,
                            'REFUNDING'     => 6
    );
    var $ship_status = array('SHIP_NO'      => 0,
                             'SHIP_FINISH'  => 1,
                             'SHIP_PREPARE' => 1,
                             'SHIP_PART'    => 2,
                             'RESHIP_PART'  => 3,
                             'RESHIP_ALL'   => 4
    );

    var $item_status = array(
        'TRADE_ACTIVE' => 'active',
        'TRADE_CLOSED'  => 'close',
        'TRADE_FINISHED' => 'close',
    );

    // 校验排除项
    protected $_checkExcludeList = [
        'item_movement_code', // 赠品明细movement_code
        //'pmt_discount_code' // discount_code
    ];

    protected function _analysis()
    {

        $this->formatData();

        $this->__apilog['result']['data'] = array('tid' => $this->_ordersdf['order_bn']);
        $this->__apilog['original_bn'] = $this->_ordersdf['order_bn'];

        parent::_analysis();


        if(in_array($this->_ordersdf['trade_type'],array('step')) || in_array($this->_ordersdf['t_type'],array('step'))){
            $this->_ordersdf['order_type'] = 'presale';
        }
        foreach($this->_ordersdf['order_objects'] as $object){
            if($object['zhengji_status']){
                if(in_array($object['zhengji_status'],array('1','2'))){
                    $this->_ordersdf['order_type'] = 'presale';

                }
            }
        }

    }

    function formatData(){
        $aData = $this->_ordersdf;
        unset($this->_ordersdf);

        $order_sdf['order_bn'] = $aData['tid'];
        $order_sdf['status'] = $this->status[$aData['status']];
        $order_sdf['source_status'] = $aData['status'];
        $order_sdf['pay_status'] = $this->pay_status[$aData['pay_status']];
        $order_sdf['ship_status'] = $this->ship_status[$aData['ship_status']];
        $order_sdf['is_delivery'] = $aData['is_delivery'];

        //配送信息 begin
        $shipping['shipping_id'] = $aData['shipping_tid'];
        $shipping['shipping_name'] = $aData['shipping_type'];
        $shipping['cost_shipping'] = $aData['shipping_fee'];
        $shipping['is_protect'] = $aData['is_protect'];
        $shipping['cost_protect'] = $aData['protect_fee'];
        $shipping['is_cod'] = $aData['is_cod'];
        //配送信息 end
        $order_sdf['shipping'] = $shipping;
        //支付方式信息 begin
        $payinfo['pay_name'] = $aData['payment_type'];
        $payinfo['cost_payment'] = $aData['pay_cost'];  //支付费用

        //支付方式信息 end
        $order_sdf['payinfo'] = $payinfo;
        $order_sdf['is_sh_ship'] = $aData['is_sh_ship'] ? $aData['is_sh_ship'] : '';#菜鸟自动流转订单
        $order_sdf['pay_bn'] = $aData['payment_tid'];
        $order_sdf['weight'] = $aData['total_weight'];
        $order_sdf['title'] = $aData['title'];
        $order_sdf['createtime'] = $aData['created'];
        // 收货人信息 begin
        $consignee['name'] = $aData['receiver_name'];
        $consignee['area_state'] = $aData['receiver_state'];
        $consignee['area_city'] = $aData['receiver_city'];
        $consignee['area_district'] = $aData['receiver_district'];
        $consignee['addr'] = $aData['receiver_address'];
        $consignee['zip'] = $aData['receiver_zip'];
        $consignee['telephone'] = $aData['receiver_phone'];
        $consignee['mobile'] = $aData['receiver_mobile'];
        $consignee['email'] = $aData['receiver_email'];
        $consignee['r_time'] = $aData['receiver_time'];
        //收货人信息 end
        $order_sdf['consignee'] = $consignee;
        //发货人信息 begin    暂时没有找到 用发货人信息代替
        $consigner['name'] = $aData['receiver_name'];
        $consigner['area_state'] = $aData['receiver_state'];
        $consigner['area_city'] = $aData['receiver_city'];
        $consigner['area_district'] = $aData['receiver_district'];
        $consigner['addr'] = $aData['receiver_address'];
        $consigner['zip'] = $aData['receiver_zip'];
        $consigner['telephone'] = $aData['receiver_phone'];
        $consigner['mobile'] = $aData['receiver_mobile'];
        $consigner['email'] = $aData['receiver_email'];
        //发货人信息 end
        $order_sdf['consigner'] = $consigner;

        //买家会员信息 begin
        $member_info['uname'] = $aData['buyer_uname'];
        $member_info['name'] = $aData['buyer_name'];
        $member_info['alipay_no'] = $aData['buyer_alipay_no'];
        $member_info['area_state'] = $aData['buyer_state'];
        $member_info['area_city'] = $aData['buyer_city'];
        $member_info['area_district'] = $aData['buyer_district'];
        $member_info['addr'] = $aData['buyer_address'];
        $member_info['mobile'] = $aData['buyer_mobile'];
        $member_info['tel'] = $aData['buyer_phone'];
        $member_info['email'] = $aData['buyer_email'];
        $member_info['zip'] = $aData['buyer_zip'];

        //买家会员信息 end
        $order_sdf['member_info'] = json_encode($member_info);
        //订单来源
        $order_sdf['order_source'] = $aData['order_source'];

        if ($aData['order_source'] == 'app') {
            $order_sdf['order_source'] = 'I3';
        } else {
            $order_sdf['order_source'] = 'I';
        }

        //订单优惠方案信息  begin
        $tmp_pmt_detail = json_decode($aData['promotion_details'], true);

        $order_sdf['pmt_detail'] = array();
        $order_sdf['other_list'] = array();
        $k_count = 0;
        if ($tmp_pmt_detail) {
            foreach ((array)$tmp_pmt_detail as $k => $v) {
                $order_sdf['pmt_detail'][$k]['pmt_amount'] = $v['promotion_fee'] ? $v['promotion_fee'] : $v['pmt_amount'];
                $order_sdf['pmt_detail'][$k]['pmt_describe'] = $v['promotion_name'] ? $v['promotion_name'] : $v['pmt_describe'];
                $order_sdf['pmt_detail'][$k]['oid'] = $v['oid'];
                $order_sdf['pmt_detail'][$k]['promotion_id'] = $v['promotion_id'];
                $order_sdf['pmt_detail'][$k]['discount_code'] = isset($v['discount_code']) ? $v['discount_code'] :'';
            }
        }

        $order_sdf['other_list'] = json_encode($aData['other_list']);

        $order_sdf['t_type'] = empty($aData['tradetype']) ? 'fixed' : $aData['tradetype'];
        $order_sdf['is_yushou'] = $aData['is_yushou'];  //全款预售标识 可选值：true（是），false（否）
        $order_sdf['trade_type'] = $aData['trade_type'];  //定金预售标识。可选值：step（是）
        $order_sdf['step_trade_status'] = $aData['step_trade_status'];  //分阶段付款状态
        $order_sdf['step_paid_fee'] = $aData['step_paid_fee'];  //定金金额

        $trade_memo = $aData['trade_memo'];

        if($trade_memo=='内购订单'){
            $order_sdf['order_type'] = 'staff';
        }
        //订单优惠方案信息  end
        //支付单信息  新版本
        $aData['payment_lists'] = json_decode($aData['payment_lists'], true);

        foreach ((array)$aData['payment_lists'] as $p_k => $p_v) {
            $payments[$p_k]['trade_no'] = $p_v['payment_id'];
            $payments[$p_k]['money'] = isset($p_v['currency_fee']) ? $p_v['currency_fee'] : $p_v['pay_fee'];
            $payments[$p_k]['pay_time'] = $p_v['pay_time'];
            $payments[$p_k]['account'] = $p_v['seller_account'];
            $payments[$p_k]['bank'] = $p_v['seller_bank'];
            $payments[$p_k]['pay_bn'] = $p_v['payment_id'];
            $payments[$p_k]['paycost'] = $p_v['paycost'];
            $payments[$p_k]['pay_account'] = $p_v['buyer_account'];
            $payments[$p_k]['paymethod'] = $p_v['payment_name'];
            $payments[$p_k]['memo'] = $p_v['memo'];
            $payments[$p_k]['outer_no'] = $p_v['outer_no'];  //支付网关的内部交易单号
        }

        $order_sdf['payments'] = $payments;
        //支付单信息  新版本
        $order_sdf['cost_item'] = $aData['total_goods_fee'];
        $order_sdf['currency'] = $aData['currency'];
        $order_sdf['cur_rate'] = $aData['currency_rate'];
        $order_sdf['score_u'] = $aData['point_fee'];
        $order_sdf['score_g'] = $aData['buyer_obtain_point_fee'];

        $this->_ordersdf = $order_sdf;
    }

    protected function _canCreate()
    {
        return true;
    }

    protected function _canUpdate()
    {
        return true;
    }

    public function _canAccept()
    {
        return true;
    }

    protected function get_update_plugins()
    {
        return array(
            'ome_plugin_order_update',
        );
    }

    protected function get_update_components()
    {
        return array(
            'ome_plugin_order_update',
        );
    }
} 