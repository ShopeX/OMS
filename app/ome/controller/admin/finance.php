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

class ome_ctl_admin_finance extends desktop_controller
{
    public $workground = "finance_center";

    /**
     * _views
     * @return mixed 返回值
     */
    public function _views()
    {
        $mdl_order = $this->app->model('orders');
        $sub_menu  = array(
            0 => array(
                'label'    => __('未支付'),
                'filter'   => array(
                    'pay_status'             => ['0', '8'],
                    'process_status' => array('unconfirmed', 'confirmed', 'is_retrial', 'is_declare'),
                    'is_fail'                => 'false',
                ),
                'optional' => false,
            ),
            1 => array(
                'label'    => __('全部'),
                'filter'   => array(
                    'pay_status'             => ['0','3','8'],
                    'process_status_noequal' => array('cancel', 'remain_cancel', 'is_retrial'),
                    'is_fail'                => 'false',
                ),
                'optional' => false,
            ),
            2 => array(
                'label'    => __('部分支付'),
                'filter'   => array(
                    'pay_status'             => ['3'],
                    'process_status_noequal' => array('cancel', 'remain_cancel', 'is_retrial'),
                    'is_fail'                => 'false',
                ),
                'optional' => false,
            ),
        );
        
        // 订单发货状态（需要支持货到付款订单：已发货 + 未付款）
        $base_filter = array('ship_status' => ['0', '1']);

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $base_filter['org_id'] = $organization_permissions;
        }

        $i = 0;
        foreach ($sub_menu as $k => $v) {
            #加入固定filter
            $v['filter'] = array_merge($v['filter'], $base_filter);

            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $mdl_order->viewcount($v['filter']);
            $sub_menu[$k]['href']   = 'index.php?app=ome&ctl=admin_finance&act=index&view=' . $i++;
        }
        return $sub_menu;
    }

    /**
     * index
     * @return mixed 返回值
     */
    public function index()
    {
        $params = array(
            'title'                  => '付款确认',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_view_tab'           => true,
            'finder_aliasname'       => 'order_finance',
            'actions'                => array(
                array('label' => app::get('ome')->_('批量支付'), 'submit' => "index.php?app=ome&ctl=admin_finance&act=batchPayed&area_id=100", 'target' => 'dialog::{width:690,height:470,title:\'批量支付\'}"'),
            ),
            'finder_cols'            => 'payment,total_amount,cost_item,is_tax,cost_tax,pay_status,payed',
            //'orderBy'                => 'last_modified desc',
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ]
        );

        if (!isset($_GET['view'])) {
            $params['base_filter'] = array(
                'process_status' => array('unconfirmed', 'confirmed', 'is_retrial', 'is_declare'),
                'pay_status' => ['0', '8'],
                'ship_status' => ['0', '1'], // 订单发货状态（需要支持货到付款订单：已发货 + 未付款）
                'is_fail' => 'false',
            );
        }

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $params['base_filter']['org_id'] = $organization_permissions;
        }

        $this->pagedata['act_finance'] = 1;
        $this->pagedata['operate']     = true;
        $this->finder('ome_mdl_orders', $params);
    }

    /**
     * pay_confirm
     * @param mixed $orderid ID
     * @return mixed 返回值
     */
    public function pay_confirm($orderid)
    {
        if (!$orderid) {
            echo app::get('base')->_('订单号传递出错');
            return false;
        }

        //判断是否为失败订单
        $api_failObj = $this->app->model('api_fail');
        $api_fail    = $api_failObj->dump(array('order_id' => $orderid, 'type' => 'payment'));
        if ($api_fail) {
            $api_fail_flag = 'true';
        } else {
            $api_fail_flag = 'false';
        }
        $this->pagedata['api_fail_flag'] = $api_fail_flag;

        $this->pagedata['orderid'] = $orderid;
        $objOrder                  = $this->app->model('orders');
        $aORet                     = $objOrder->order_detail($orderid);
        if ($aORet['pay_status'] == '1') {
            exit("此订单已支付完成");
        }
        if ($aORet['total_amount'] < $aORet['payed']) {
            exit("订单支付状态有误");
        }
        $aORet['cur_name'] = 'CNY';
        $aORet['cur_sign'] = 'CNY';

        $oPayment       = $this->app->model('payments');
        $payment_cfgObj = app::get('ome')->model('payment_cfg');
        $oShop          = $this->app->model('shop');
        $c2c_shop       = ome_shop_type::shop_list();
        $shop_id        = $aORet['shop_id'];
        $shop_detail    = $oShop->dump($shop_id, 'node_type,node_id');
        if ($shop_id && !in_array($shop_detail['node_type'], $c2c_shop)) {
            $payment = kernel::single('ome_payment_type')->paymethod($shop_id);
        } else {
            $payment = $oPayment->getMethods();
        }

        $payment_cfg = $payment_cfgObj->dump(array('pay_bn' => $aORet['pay_bn']), 'id,pay_type');

        $this->pagedata['shop_type']  = $shop_detail['node_type'];
        $this->pagedata['shop_id']    = $shop_id;
        $this->pagedata['node_id']    = $shop_detail['node_id'];
        $this->pagedata['payment']    = $payment;
        $this->pagedata['payment_id'] = $payment_cfg['id'];
        $this->pagedata['pay_type']   = $payment_cfg['pay_type'];
        if ($payment_cfg['id']) {
            $order_paymentcfg = kernel::single('ome_payment_type')->paymethod($shop_id, $payment_cfg['pay_type']);
        }
        $this->pagedata['order_paymentcfg'] = $order_paymentcfg;
        $this->pagedata['op_name']          = 'admin';
        $this->pagedata['typeList']         = ome_payment_type::pay_type();

        if ($aORet['member_id'] > 0) {
            $objMember                = $this->app->model('members');
            $aRet                     = $objMember->member_detail($aORet['member_id']);
            $this->pagedata['member'] = $aRet;
        } else {
            $this->pagedata['member'] = array();
        }
        $this->pagedata['order'] = $aORet;

        $aRet     = $oPayment->getAccount();
        $aAccount = array('--使用已存在帐户--');
        foreach ($aRet as $v) {
            $aAccount[$v['bank'] . "-" . $v['account']] = $v['bank'] . " - " . $v['account'];
        }
        $this->pagedata['pay_account'] = $aAccount;

        //剩余支付金额
        $pay_money                   = ome_func::number_math(array($aORet['total_amount'], $aORet['payed']), '-', 3);
        $this->pagedata['pay_money'] = $pay_money;
        $this->display('admin/finance/orderpayed.html');
    }

    /**
     * payment_by_pay_type
     * @param mixed $order_id ID
     * @param mixed $shop_id ID
     * @param mixed $pay_type pay_type
     * @return mixed 返回值
     */
    public function payment_by_pay_type($order_id = '', $shop_id = '', $pay_type = '')
    {
        $payment = kernel::single('ome_payment_type')->payment_by_pay_type($order_id, $shop_id, $pay_type);
        die($payment);
    }

    /**
     * do_payorder
     * @param mixed $order_id ID
     * @return mixed 返回值
     */
    public function do_payorder($order_id)
    {
        if ($_POST) {
            $url = "index.php?app=ome&ctl=admin_finance&act=index";
            $this->begin($url);
            //获得order信息
            $objOrder   = $this->app->model('orders');
            $objShop    = $this->app->model('shop');
            $objMath    = kernel::single('eccommon_math');
            $oPayment   = $this->app->model('payments');
            $aORet      = $objOrder->order_detail($order_id);
            $paymethods = ome_payment_type::pay_type();
            //验证金额
            $pay_money = strval($_POST['money']);
            if (!is_numeric($pay_money)) {
                $this->end(false, app::get('base')->_('收款金额输入有误。'));
            }

            $not_payed = ome_func::number_math(array($aORet['total_amount'], $aORet['payed']), '-', 3);

            if ($pay_money < 0) {
                $this->end(false, app::get('base')->_('收款金额不能为负数。'));
            }
            if ($pay_money > $not_payed) {
                $this->end(false, app::get('base')->_('收款金额大于需要付款的金额。'));
            }
            $shop_detail = $objShop->dump($aORet['shop_id'], 'node_id,node_type');
            $c2c_shop    = ome_shop_type::shop_list();

            $payment_request = false;
            if ($_POST['api_fail_flag'] == 'false') {
                if ($shop_detail['node_id'] && !in_array($shop_detail['node_type'], $c2c_shop)) {
                    $payment_request = true;
                }
            } else {
                if ($_POST['api_payment_request'] == 'true') {
                    $payment_request = true;
                }
            }
            // 如果是本地订单，则不发起支付请求
            if ($aORet['source'] == 'local' || $aORet['pay_status'] == '8') {
                $payment_request = false;
            }
            if ($payment_request == true) {

                /*if (!$_POST['payment']){
                $this->end(false, app::get('base')->_('请选择支付方式。'));
                }*/
                if (!$_POST['pay_type']) {
                    $this->end(false, app::get('base')->_('请选择付款类型。'));
                }
                $_POST['order_id'] = $order_id;
                $_POST['task']     = $order_id;
                if ($oPayment->payment_request($_POST)) {
                    if ($_POST['api_payment_request'] == 'true') {
                        //从支付失败列表中删除
                        $api_failObj = app::get('ome')->model('api_fail');
                        $data        = array(
                            'order_id' => $order_id,
                            'type'     => 'payment',
                        );
                        $api_failObj->delete($data);
                    }
                    $result = true;
                    $msg    = '支付请求发起成功';
                } else {
                    $result = false;
                    $msg    = '支付请求发起失败,请重试';

                }
                $this->end($result, app::get('base')->_($msg));

            } else {

                //付款
                $orderdata             = array();
                $paymentCfgObj         = $this->app->model('payment_cfg');
                $cfg                   = $paymentCfgObj->dump($_POST['payment']);
                $orderdata['order_id'] = $order_id;
                $orderdata['pay_bn']   = $cfg['pay_bn'];

                #付款后更新发票号为空
                if (!empty($_POST['tax_no'])) {
                    $orderdata['tax_no'] = $_POST['tax_no'];
                }

                $orderdata['payed']    = $objMath->number_plus(array($aORet['payed'], $pay_money));
                $orderdata['payed']    = floatval($orderdata['payed']);
                $aORet['total_amount'] = floatval($aORet['total_amount']);
                if ($orderdata['payed'] < $aORet['total_amount']) {
                    //如果已经付款金额小于总金额，则为部分付款
                    $orderdata['pay_status'] = 3;
                }
                if ($orderdata['payed'] == $aORet['total_amount']) {
                    //如果已经付款金额等于总金额，则为全部付款
                    $orderdata['pay_status'] = 1;
                }
                $orderdata['paytime'] = time();
                $orderdata['payment'] = $paymethods[$_POST['pay_type']];

                // 检测京东订单是否有微信支付先用后付的单据
                $use_before_payed = false;
                if ($aORet['shop_type'] == '360buy') {
                    $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($aORet['order_id']);
                    $labelCode = array_column($labelCode, 'label_code');
                    $use_before_payed = kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode);
                }
                //更新订单状态为finish
                if ($orderdata['pay_status'] == '1' && ($aORet['shipping']['is_cod'] == 'true' || $use_before_payed) && $aORet['ship_status'] == '1') {

                    $orderdata['status'] = 'finish';

                }
                $filter = array('order_id' => $order_id);
                $objOrder->update($orderdata, $filter);
                #货到付款订单,更新销售单付款时间
                if ($aORet['shipping']['is_cod'] == 'true' || $use_before_payed) {
                    if ($aORet['ship_status'] == '1') {
                        $objsales = $this->app->model('sales');
                        #检查销售单单是否存在
                        $sale_id = $objsales->getList('sale_id', array('order_id' => $order_id));
                        if (!empty($sale_id)) {
                            $objsales->update(array('paytime' => $orderdata['paytime']), array('order_id' => $order_id));
                        }
                    }
                }

                //日志
                $memo           = '订单付款操作';
                $oOperation_log = $this->app->model('operation_log');
                $oOperation_log->write_log('order_modify@ome', $order_id, $memo);
                //生成支付单
                $payment_bn                 = $oPayment->gen_id();
                $paymentdata                = array();
                $paymentdata['payment_bn']  = $payment_bn;
                $paymentdata['order_id']    = $order_id;
                $paymentdata['shop_id']     = $aORet['shop_id'];
                $paymentdata['account']     = $_POST['account'];
                $paymentdata['bank']        = $_POST['bank'];
                $paymentdata['pay_account'] = $_POST['pay_account'];
                $paymentdata['currency']    = $aORet['currency'];
                $paymentdata['money']       = $pay_money;
                $paymentdata['paycost']     = 0;
                $curr_time                  = time();
                $paymentdata['t_begin']     = $curr_time; //支付开始时间
                $paymentdata['t_end']       = $curr_time; //支付结束时间
                $paymentdata['trade_no']    = $_POST['trade_no'] ? $_POST['trade_no'] : ''; //支付网关的内部交易单号，默认为空
                $paymentdata['cur_money']   = $paymentdata['money'];
                $paymentdata['pay_type']    = $_POST['pay_type'];
                $paymentdata['payment']     = $_POST['payment'] ? : null;
                $paymentdata['paymethod']   = $cfg['custom_name'];

                $opInfo                 = kernel::single('ome_func')->getDesktopUser();
                $paymentdata['op_id']   = $opInfo['op_id'];
                $paymentdata['op_name'] = $opInfo['op_name'];

                $paymentdata['ip']             = kernel::single("base_request")->get_remote_addr();
                $paymentdata['status']         = 'succ';
                $paymentdata['memo']           = $_POST['memo'];
                $paymentdata['is_orderupdate'] = 'false';
                $paymentdata['org_id']         = $aORet['org_id'];

                $oPayment->create_payments($paymentdata);

                $order_info = $objOrder->dump($order_id, 'order_bn,pay_status,shop_id');

                if ($_POST['api_fail_flag'] == 'true') {
                    //从支付失败列表中删除
                    $api_failObj = app::get('ome')->model('api_fail');
                    $data        = array(
                        'order_id' => $order_id,
                        'type'     => 'payment',
                    );
                    $api_failObj->delete($data);
                }
                //执行自动审单
                kernel::single('ome_order')->auto_order_combine($order_id);
                //日志
                $oOperation_log->write_log('payment_create@ome', $paymentdata['payment_id'], '生成支付单');
                $this->end(true, app::get('base')->_('支付成功'));
            }
        }
    }

    /**
     * import_finance
     * @return mixed 返回值
     */
    public function import_finance()
    {
        $oOperation_log = $this->app->model('operation_log');
        $time           = time();
        if (strtolower(substr($_FILES['upload1']['name'], -4)) != '.csv') {
            echo "<script>parent.MessageBox.success('文件格式有误!');</script>";
            exit;
        }
        $tmp      = $_FILES['upload1']['tmp_name'];
        $content1 = file_get_contents($_FILES['upload1']['tmp_name']);
        if (substr($content1, 0, 3) == "\xEF\xBB\xBF") {
            $content1 = substr($content1, 3); //去BOM头
        }
        $content1 = mb_convert_encoding($content1, 'UTF-8', 'GB2312');
        file_put_contents('tempdata', $content1);
        $handle = fopen('tempdata', 'r');
        $i      = 0;
        while ($row = fgetcsv($handle, 1000, ",")) {
            $i++;
            if ($i == 1) {
//第一行
                if ($row[0] != 'order_bn' || $row[1] != 'shop_name' || $row[2] != 'money' || $row[3] != 'account' || $row[4] != 'bank' || $row[5] != 'pay_account' || $row[6] != 'pay_type' || $row[7] != 'payment' || $row[8] != 'memo') {
                    echo '格式不正确';
                    echo "<script>parent.MessageBox.success('格式不正确');</script>";
                    exit;
                }
                continue;
            }
            $f_data         = array('order_bn' => $row[0], 'shop_name' => $row[1], 'money' => $row[2], 'account' => $row[3], 'bank' => $row[4], 'pay_account' => $row[5], 'pay_type' => $row[6], 'payment' => $row[7], 'memo' => $row[8]);
            $finance_data[] = $f_data;
        }
        @unlink('tempdata');
        $shopObj  = $this->app->model('shop');
        $objOrder = $this->app->model('orders');
        //处理数据
        foreach ($finance_data as $onepay) {
            //获得店铺信息
            $shopinfo = $shopObj->dump(array("name" => $onepay['shop_name']));
            //获得order信息
            $aORet = $objOrder->dump(array("order_bn" => $onepay['order_bn'], "shop_id" => $shopinfo['shop_id']));
            //验证金额
            $orderdata = array();
            $not_payed = $aORet['total_amount'] - $aORet['payed'];
            if ($not_payed <= 0 || $onepay['money'] > $not_payed || $onepay['money'] <= 0) {
                //设置订单abnormal
                $orderdata['abnormal'] = 'true';
            }
            //付款
            $orderdata['order_id'] = $aORet['order_id'];
            $orderdata['payed']    = $aORet['payed'] + $onepay['money'];
            if ($orderdata['payed'] < $aORet['total_amount']) {
                //如果已经付款金额小于总金额，则为部分付款
                $orderdata['pay_status'] = 3;
            }
            if ($orderdata['payed'] == $aORet['total_amount']) {
                //如果已经付款金额等于总金额，则为全部付款
                $orderdata['pay_status'] = 1;
            }
            $objOrder->save($orderdata);

            //日志
            $memo = '订单付款操作';
            $oOperation_log->write_log('order_modify@ome', $aORet['order_id'], $memo);
            //生成支付单
            $oPayment                   = $this->app->model('payments');
            $payment_bn                 = $oPayment->gen_id();
            $paymentdata                = array();
            $paymentdata['payment_bn']  = $payment_bn;
            $paymentdata['order_id']    = $aORet['order_id'];
            $paymentdata['shop_id']     = $shopinfo['shop_id'];
            $paymentdata['account']     = $onepay['account'];
            $paymentdata['bank']        = $onepay['bank'];
            $paymentdata['pay_account'] = $onepay['pay_account'];
            $paymentdata['currency']    = '';
            $paymentdata['money']       = $onepay['money'];
            $paymentdata['paycost']     = 0;
            $paymentdata['cur_money']   = $paymentdata['money'];
            $paymentdata['pay_type']    = $onepay['pay_type'];
            $paymentdata['payment']     = $onepay['payment'];
            $paymethods                 = ome_payment_type::pay_type();
            $paymentdata['paymethod']   = $paymethods[$paymentdata['pay_type']];

            $opInfo               = kernel::single('ome_func')->getDesktopUser();
            $paymentdata['op_id'] = $opInfo['op_id'];

            $paymentdata['ip']     = kernel::single("base_request")->get_remote_addr();
            $paymentdata['status'] = 'succ';
            $paymentdata['memo']   = $onepay['memo'];
            $oPayment->save($paymentdata);

            //日志
            $oOperation_log->write_log('payment_create@ome', $paymentdata['payment_id'], '生成支付单');
        }
        echo "<script>parent.MessageBox.success('导入成功!');</script>";
    }

    /**
     * import
     * @return mixed 返回值
     */
    public function import()
    {
        $this->display('admin/finance/import.html');
    }
    #批量支付,wangkezheng
    /**
     * batchPayed
     * @return mixed 返回值
     */
    public function batchPayed()
    {
        $pay_status = [];
        $api_fail_flag = [];
        #批量支付时，批量支付订单的店铺来源必须是同一个
        global $shop_id;
        $this->_request = kernel::single('base_component_request');
        $order_info     = $this->_request->get_post();
        #统计批量支付订单数量
        $all_order_count = count($order_info['order_id']);
        if ($all_order_count <= 0) {
            #检测是否点击了全选操作
            if ($order_info['isSelectedAll'] == '_ALL_') {
                echo '<span style="color:red;font-weight:bold;">批量支付不支持这种选择方式！</span>';exit;
            }
        }
        #检测订单
        if ($all_order_count <= 1) {
            echo '<span style="color:red;font-weight:bold;">至少选择两个订单！</span>';exit;
        }
        $oShop           = $this->app->model('shop');
        $c2c_shop        = ome_shop_type::shop_list();
        $oPayment        = $this->app->model('payments');
        $obj_Order       = $this->app->model('orders');
        $api_failObj     = $this->app->model('api_fail');
        $all_total_amont = $all_payed = 0;
        #获取所有准备批量支付订单的所有数据
        $orders = $obj_Order->getList('*', array('order_id' => $order_info['order_id']));
        $_key   = 0;
        foreach ($orders as $key => $order) {
            $_key++;
            #检测是否属于同一个店铺，只有同一个店铺才能批量支付
            $new_shop_id = $order['shop_id'];
            if ($_key > 1) {
                if ($new_shop_id != $shop_id) {
                    echo '<span style="color:red;font-weight:bold;">只有同一来源店铺订单，才可以批量支付！</span>';exit;
                }
            }
            if ($order['shop_type'] == 'shopex_fy' && $order['order_source'] == 'market') {
                echo '<span style="color:red;font-weight:bold;">全民分销拼团不支持erp支付！</span>';exit;
            }
            $orderid = $arr_order_id[] = $order['order_id'];
            #对这批订单进行验证操作
            if ($order['pay_status'] == '1') {
                echo "<span style='color:red;font-weight:bold;'>订单 {$order['order_bn']} 已支付完成!</span><br/>";exit;
            }
            if ($order['total_amount'] < $order['payed']) {
                echo "<span style='color:red;font-weight:bold;'>订单 {$order['order_bn']} 已付金额大于总金额!</span><br/>";exit;
            }

            // 检测京东订单是否有微信支付先用后付的单据
            $use_before_payed = false;
            if ($order['shop_type'] == '360buy') {
                $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($order['order_id']);
                $labelCode = array_column($labelCode, 'label_code');
                $use_before_payed = kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode);
            }

            // 是否包含货到付款订单
            if ($order['is_cod'] == 'true' && !$use_before_payed && $order['ship_status'] != '1') {
                echo "<span style='color:red;font-weight:bold;'>包含未发货货到付款订单，不允许批量支付</span><br/>";exit;
            }

            // 是否包含先用后付订单
            if ($use_before_payed && $order['ship_status'] != '1') {
                echo "<span style='color:red;font-weight:bold;'>包含未发货先用后付订单，不允许批量支付</span><br/>";exit;
            }

            #上次同步超时订单
            if ($order['pay_status'] == 8) {
                $pay_status[$order['order_bn']] = 8;
            }
            $api_fail = $api_failObj->dump(array('order_id' => $orderid, 'type' => 'payment'));
            #上次同步失败订单
            if ($api_fail) {
                $api_fail_flag[$order['order_bn']] = 'true';
            }
            #批量支付订单的总金额
            $all_total_amont += $order['total_amount'];
            #批量支付订单的已付金额
            $all_payed += $order['payed'];
            $shop_id = $order['shop_id']; #以上处理完毕，把这个shop_id存起来
        }
        #根据这批订单的shop_id，找到店铺相关信息
        $shop_detail = $oShop->dump($shop_id, 'node_type,node_id');
        #检测订单的来源店铺类型，并获取相关支付类型
        if ($shop_id && !in_array($shop_detail['node_type'], $c2c_shop)) {
            $payment = kernel::single('ome_payment_type')->paymethod($shop_id);
        } else {
            $payment = $oPayment->getMethods();
        }
        #同步超时订单，单独支付
        if (count($pay_status) > 0) {
            foreach ($pay_status as $key => $status) {
                echo "<span style='color:red;font-weight:bold;'>订单 {$key} 上次同步请求超时，请单独支付！</span><br/>";
            }
            exit;
        }
        #同步失败订单，单独支付
        if (count($api_fail_flag) > 0) {
            foreach ($api_fail_flag as $key => $v) {
                if ($v == 'true') {
                    echo "<span style='color:red;font-weight:bold;'>订单 {$key} 上次同步请求失败，请单独支付！</span><br/>";
                }
            }
            exit;
        }
        $this->pagedata['order_id']        = serialize($arr_order_id); #本次批量处理的订单号
        $this->pagedata['payment']         = $payment; #店铺支付方式
        $this->pagedata['all_order_count'] = $all_order_count;
        $this->pagedata['all_total_amont'] = $all_total_amont; #本次批量总金额
        $this->pagedata['all_payed']       = $all_payed; #本次批量已经支付金额
        $this->pagedata['shop_id']         = $shop_id;
        $this->pagedata['node_id']         = $shop_detail['node_id'];
        $this->pagedata['cur_name']        = 'CNY'; #客户支付货币,默认为人民币
        $this->pagedata['typeList']        = ome_payment_type::pay_type(); #付款类型
        $this->pagedata['member']          = array(); #支付账号
        #收款银行
        $aRet     = $oPayment->getAccount();
        $aAccount = array('--使用已存在帐户--');
        foreach ($aRet as $v) {
            $aAccount[$v['bank'] . "-" . $v['account']] = $v['bank'] . " - " . $v['account'];
        }
        $this->pagedata['pay_account'] = $aAccount;
        #剩余支付金额
        $pay_money                   = ome_func::number_math(array($all_total_amont, $all_payed), '-', 3);
        $this->pagedata['pay_money'] = $pay_money;
        $this->display('admin/finance/batchPayed.html');
    }

    /**
     * do_batchPayed
     * @param mixed $order_id ID
     * @return mixed 返回值
     */
    public function do_batchPayed($order_id)
    {
        #批量处理的订单号
        $arr_order_id = unserialize($order_id);
        $url          = "index.php?app=ome&ctl=admin_finance&act=index";
        $time         = time();

        $saleModel = app::get('ome')->model('sales');

        $this->begin();

        foreach ($arr_order_id as $order_id) {
            if ($_POST) {
                //获得order信息
                $objOrder   = $this->app->model('orders');
                $objShop    = $this->app->model('shop');
                $objMath    = kernel::single('eccommon_math');
                $oPayment   = $this->app->model('payments');
                $aORet      = $objOrder->order_detail($order_id);
                $paymethods = ome_payment_type::pay_type();
                //验证金额
                $pay_money = $aORet['total_amount'];
                if (!is_numeric($pay_money)) {

                    $this->end(false, app::get('base')->_('收款金额输入有误。'));exit;
                }
                $not_payed = ome_func::number_math(array($aORet['total_amount'], $aORet['payed']), '-', 3);
                if ($pay_money < 0) {

                    $this->end(false, app::get('base')->_('收款金额不能为负数。'));exit;
                }
                if ($not_payed < 0) {

                    $this->end(false, app::get('base')->_('未付金额不能为负数。'));exit;
                }
                $shop_detail     = $objShop->dump($aORet['shop_id'], 'node_id,node_type');
                $c2c_shop        = ome_shop_type::shop_list();
                $payment_request = false;
                if ($shop_detail['node_id'] && !in_array($shop_detail['node_type'], $c2c_shop)) {
                    $payment_request = true;
                }
                // 如果是本地订单，则不发起支付请求
                if ($aORet['source'] == 'local' || $aORet['pay_status'] == '8') {
                    $payment_request = false;
                }
                if ($payment_request == true) {
                    if (!$_POST['pay_type']) {

                        $this->end(false, app::get('base')->_('请选择付款类型。'));exit;
                    }
                    $pdata             = $_POST;
                    $pdata['money']    = $not_payed; #未付金额,即本次需要支付金额
                    $pdata['order_id'] = $order_id;
                    $pdata['task']     = $order_id;
                    if ($oPayment->payment_request($pdata)) {
                        $result = true;
                        $msg    = '支付请求发起成功';
                    } else {
                        $result = false;
                        $msg    = '支付请求发起失败,请重试';
                    }
                    //$this->end($result, app::get('base')->_($msg));

                } else {
                    //付款
                    $orderdata             = array();
                    $paymentCfgObj         = $this->app->model('payment_cfg');
                    $cfg                   = $paymentCfgObj->dump($_POST['payment']);
                    $orderdata['order_id'] = $order_id;
                    $orderdata['pay_bn']   = $cfg['pay_bn'];

                    #付款后更新发票号为空
                    if (!empty($_POST['tax_no'])) {
                        $orderdata['tax_no'] = $_POST['tax_no'];
                    }

                    $orderdata['payed']      = $pay_money; #商品总金额
                    $orderdata['payed']      = floatval($orderdata['payed']);
                    $orderdata['pay_status'] = 1; #全额付款
                    $orderdata['paytime']    = time();
                    $orderdata['payment']    = $paymethods[$_POST['pay_type']];

                    // 检测京东订单是否有微信支付先用后付的单据
                    $use_before_payed = false;
                    if ($aORet['shop_type'] == '360buy') {
                        $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($aORet['order_id']);
                        $labelCode = array_column($labelCode, 'label_code');
                        $use_before_payed = kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode);
                    }
                    //更新订单状态为finish
                    if ($orderdata['pay_status'] == '1' && ($aORet['shipping']['is_cod'] == 'true' || $use_before_payed) && $aORet['ship_status'] == '1') {

                        $orderdata['status'] = 'finish';

                    }
                    $filter                  = array('order_id' => $order_id);
                    $objOrder->update($orderdata, $filter);

                    if (($aORet['shipping']['is_cod'] == 'true' || $use_before_payed) && $aORet['ship_status'] == '1') {

                        $saleinfo = $saleModel->getList('sale_id', array('order_id' => $order_id));
                        if ($saleinfo) {
                            $saleModel->update(array('paytime' => $orderdata['paytime']), array('order_id' => $order_id));
                        }
                    }

                    //日志
                    $memo           = '订单批量支付操作';
                    $oOperation_log = $this->app->model('operation_log');
                    $oOperation_log->write_log('order_modify@ome', $order_id, $memo);
                    //生成支付单
                    $payment_bn                 = $oPayment->gen_id();
                    $paymentdata                = array();
                    $paymentdata['payment_bn']  = $payment_bn;
                    $paymentdata['order_id']    = $order_id;
                    $paymentdata['shop_id']     = $aORet['shop_id'];
                    $paymentdata['account']     = $_POST['account'];
                    $paymentdata['bank']        = $_POST['bank'];
                    $paymentdata['pay_account'] = $_POST['pay_account'];
                    $paymentdata['currency']    = $aORet['currency'];
                    $paymentdata['money']       = $not_payed; #未付金额
                    $paymentdata['paycost']     = 0;
                    $curr_time                  = $time;
                    $paymentdata['t_begin']     = $curr_time; //支付开始时间
                    $paymentdata['t_end']       = $curr_time; //支付结束时间
                    $paymentdata['trade_no']    = $_POST['trade_no'] ? $_POST['trade_no'] : ''; //支付网关的内部交易单号，默认为空
                    $paymentdata['cur_money']   = $paymentdata['money'];
                    $paymentdata['pay_type']    = $_POST['pay_type'];
                    $paymentdata['payment']     = $_POST['payment'] ? : null;
                    $paymentdata['paymethod']   = $cfg['custom_name'];

                    $opInfo                 = kernel::single('ome_func')->getDesktopUser();
                    $paymentdata['op_id']   = $opInfo['op_id'];
                    $paymentdata['op_name'] = $opInfo['op_name'];

                    $paymentdata['ip']             = kernel::single("base_request")->get_remote_addr();
                    $paymentdata['status']         = 'succ';
                    $paymentdata['memo']           = $_POST['memo'];
                    $paymentdata['is_orderupdate'] = 'false';
                    $oPayment->create_payments($paymentdata);

                    //日志
                    $oOperation_log->write_log('payment_create@ome', $paymentdata['payment_id'], '生成支付单');
                }
                //执行自动审单
                kernel::single('ome_order')->auto_order_combine($order_id);
            }
        }
        $this->end(true, app::get('base')->_('支付成功'));
    }
}
