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
 * 支付单插件
 *
 * @author chenping<chenping@shopex.cn>
 * @version $Id: payment.php 2013-3-12 17:23Z
 */
class erpapi_shop_response_plugins_order_payment extends erpapi_shop_response_plugins_order_abstract
{

    private static $__codPartPay = array('yihaodian', 'dangdang');

    /**
     * convert
     * @param erpapi_shop_response_abstract $platform platform
     * @return mixed 返回值
     */

    public function convert(erpapi_shop_response_abstract $platform)
    {
        $paymentsdf = array();
        # 支付单结构
        $payment_list = isset($platform->_ordersdf['payments']) ? $platform->_ordersdf['payments'] : array($platform->_ordersdf['payment_detail']);

        $paymentObj = app::get('ome')->model('payments');
        # 支付单存在且订单是已支付创建支付单(或者一号店订单，货到付款，部分支付的,创建支付单)
        if (($payment_list && $platform->_ordersdf['pay_status'] == '1') ||
            ('true' == $platform->_ordersdf['shipping']['is_cod']
                && in_array($platform->__channelObj->channel['node_type'], self::$__codPartPay)
                && $platform->_ordersdf['pay_status'] == '3')) {

            $payment_cfg = $platform->get_payment($platform->_ordersdf['pay_bn'], $platform->__channelObj->channel['node_type']);

            $obj_pam = app::get('pam')->model('account');
            foreach ($payment_list as $payment) {
                $payment['op_name'] = trim($payment['op_name']);

                $account_info = array();
                if ($payment['op_name']) {
                    #查询支付单上的操作人,是否能在系统找到
                    $account_info = $obj_pam->getList('account_id', array('login_name' => $payment['op_name']));

                    if ($account_info[0]) {
                        $payment['op_id'] = $account_info[0]['account_id'];
                    }

                }

                if (!$payment['pay_time']) {
                    $payment['pay_time'] = time();
                }

                $t_begin = $t_end = kernel::single('ome_func')->date2time($payment['pay_time']);

                if ($payment['trade_no'] === 'null') {
                    unset($payment['trade_no']);
                }

                $paymentsdf[] = array(
                    'payment_bn'    => $payment['trade_no'],
                    'shop_id'       => $platform->__channelObj->channel['shop_id'],
                    'order_id'      => null,
                    'account'       => $payment['account'],
                    'bank'          => $payment['bank'],
                    'pay_account'   => $payment['pay_account'],
                    'currency'      => 'CNY',
                    'money'         => (float) $payment['money'],
                    'paycost'       => (float) $payment['paycost'],
                    'cur_money'     => (float) $payment['money'],
                    'pay_type'      => $payment_cfg['pay_type'],
                    'payment'       => $payment_cfg['id'],
                    'pay_bn'        => $payment['pay_bn'],
                    'paymethod'     => $payment['paymethod'],
                    't_begin'       => $t_begin ? $t_begin : time(),
                    't_end'         => $t_end ? $t_end : time(),
                    'download_time' => time(),
                    'status'        => 'succ',
                    'trade_no'      => $payment['outer_no'] ? $payment['outer_no'] : $payment['trade_no'],
                    'memo'          => $payment['memo'],
                    'op_id'         => $payment['op_id'] ? $payment['op_id'] : '0',
                    'op_name'       => $payment['op_name'] ? $payment['op_name'] : $platform->__channelObj->channel['node_type'],
                    'org_id'        => $platform->__channelObj->channel['org_id'],
                );
            }
        }

        // 更新的时候
        if ($platform->_tgOrder) {
            $tgPayments = $paymentObj->getList('payment_bn', array('order_id' => $platform->_tgOrder['order_id']));
            $paymentbns = $tgPayments ? array_map('current', $tgPayments) : array();

            foreach ($paymentsdf as $key => $value) {
                if (in_array($value['payment_bn'], $paymentbns)) {
                    unset($paymentsdf[$key]);
                    continue;
                }
            }
        }

        return $paymentsdf;
    }

    /**
     * @param Array $params
     *
     * @return void
     * @author
     **/
    public function postCreate($order_id, $payments)
    {
        $paymentObj = app::get('ome')->model('payments');
        $bank       = app::get('ome')->model('bank_account');
        foreach ($payments as $key => $value) {
            $payments[$key]['order_id']   = $order_id;
            $payments[$key]['payment_bn'] = $value['payment_bn'] ? $value['payment_bn'] : $paymentObj->gen_id();
            if(!empty($value['bank']) || !empty($value['account'])) {
                $bankAccount = array('bank' => $value['bank'], 'account' => $value['account']);
                if (!$bank->dump($bankAccount)) {
                    $bank->save($bankAccount);
                }
            }
            if($paymentObj->db_dump(['payment_bn'=>$payments[$key]['payment_bn'], 'shop_id'=>$payments[$key]['shop_id']], 'payment_id')) {
                unset($payments[$key]);
            }
        }
        if($payments) {
            $sql = ome_func::get_insert_sql($paymentObj, $payments);

            kernel::database()->exec($sql);

            foreach ($payments as $key => $value) {
                foreach(kernel::servicelist('ome.service.order.payment.after') as $service) {
                    if(method_exists($service,'after_payment')) {
                        $service->after_payment($value);
                    }
                }
            }
            
        }


    }

    /**
     * @param Array $params
     *
     * @return void
     * @author
     **/
    public function postUpdate($order_id, $payments)
    {
        $shop_id = $payments[0]['shop_id'];

        $shop = $this->getShop($shop_id);

        $paymentObj = app::get('ome')->model('payments');

        if (in_array($shop['node_type'], array('ecshop_b2c', 'bbc'))) {
            $paymentObj->delete(array('order_id' => $order_id));
        }

        foreach ($payments as $key => $value) {

            $payments[$key]['order_id'] = $order_id;

            if (in_array($shop['node_type'], array('ecshop_b2c', 'bbc'))) {
                $payments[$key]['payment_bn'] = $value['payment_bn'] ? $value['payment_bn'] : $paymentObj->gen_id();
            }
        }

        if ($payments) {
            $sql = ome_func::get_insert_sql($paymentObj, $payments);

            kernel::database()->exec($sql);

            $logModel = app::get('ome')->model('operation_log');
            $logModel->write_log('order_pay@ome', $order_id, '支付单添加');
            kernel::single('ome_order_branch')->preSelect($order_id);
        }
    }
}
