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
class ome_ctl_admin_refund_apply extends desktop_controller
{
    public $name       = "退款单";
    public $workground = "finance_center";

    public function index($is_jingxiao = false)
    {
        $base_filter = array();
        
        $_GET['view'] = intval($_GET['view']);
        if(empty($_GET['view'])){
            $base_filter = array('status'=>'0', 'disabled'=>'false');
        }
        
        //批量按钮
        $buttonList = array(
                'accept' => array(
                        'label'  => '批量同意',
                        'submit' => 'index.php?app=ome&ctl=admin_refund_apply&act=batch_Updatestatus&status_type=agree',
                        'target' => "dialog::{width:700,height:490,title:'批量同意'}",
                ),
                'refuse' => array(
                        'label'  => '批量拒绝',
                        'submit' => 'index.php?app=ome&ctl=admin_refund_apply&act=batch_Updatestatus&status_type=refuse',
                        'target' => "dialog::{width:700,height:490,title:'批量拒绝'}",
                ),
                'agree' => array(
                        'label'  => '批量退款',
                        'submit' => 'index.php?app=ome&ctl=admin_refund_apply&act=batchAgreeRefund&view=' . $_GET['view'],
                        'target' => "dialog::{width:700,height:490,title:'批量退款'}",
                ),
                'refundonly' => array(
                        'label'  => '确认为仅退款',
                        'submit' => 'index.php?app=ome&ctl=admin_refund_apply&act=batchRefundOnly&view=' . $_GET['view'],
                        'target' => "dialog::{width:700,height:490,title:'确认为仅退款'}",
                ),
                
        );
        
        //action
        $action = array();
        switch ($_GET['view']) {
            case '0':
            case '1':
            case '2':
                $action[] = $buttonList['accept'];
                $action[] = $buttonList['refuse'];
                $action[] = $buttonList['agree'];
                $action[] = $buttonList['refundonly'];
                break;
            case '3':
                $action[] = $buttonList['refuse'];
                $action[] = $buttonList['agree'];
                $action[] = $buttonList['refundonly'];
                break;
            case '4':
            case '5':
            case '6':
            case '7':
                $action[] = $buttonList['refundonly'];
                break;
        }

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $base_filter['org_id'] = $organization_permissions;
        }

        if ($is_jingxiao) {
            $action = [];
            $base_filter['delivery_mode'] = 'jingxiao';
        } else {
            if (!$base_filter['filter_sql']) {
                $base_filter['filter_sql'] = '1';
            }
            $base_filter['filter_sql'] .= ' AND delivery_mode <> "jingxiao"';
        }
        
        $this->finder('ome_mdl_refund_apply', array(
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'use_view_tab'           => true,
            'base_filter'            => $base_filter,
            'title'                  => $is_jingxiao ? '平台自发退款确认' : '退款确认',
            'actions'                => $action,
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
        ));
    }

    public function jingxiao()
    {
        $is_jingxiao = true;
        return $this->index($is_jingxiao);
    }

    public function _views()
    {
        $mdl_refund_apply = $this->app->model('refund_apply');
        $sub_menu = array(
                0 => array('label' => __('未处理'), 'filter' => array('status' => '0', 'disabled' => 'false'), 'optional' => false),
                1 => array('label' => __('全部'), 'filter' => array('disabled' => 'false'), 'optional' => false),
                2 => array('label' => __('审核中'), 'filter' => array('status' => '1', 'disabled' => 'false'), 'optional' => false),
                3 => array('label' => __('已接受申请'), 'filter' => array('status' => '2', 'disabled' => 'false'), 'optional' => false),
                4 => array('label' => __('已拒绝'), 'filter' => array('status' => '3', 'disabled' => 'false'), 'optional' => false),
                5 => array('label' => __('已退款'), 'filter' => array('status' => '4', 'disabled' => 'false'), 'optional' => false),
                6 => array('label' => __('退款中'), 'filter' => array('status' => '5', 'disabled' => 'false'), 'optional' => false),
                7 => array('label' => __('退款失败'), 'filter' => array('status' => '6', 'disabled' => 'false'), 'optional' => false),
                10 => array('label'=>__('卖家拒绝退款'), 'filter' => array('source_status'=>'SELLER_REFUSE_BUYER'), 'optional'=>false),
        );

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();

        $act = 'index';
        $i = 0;
        foreach ($sub_menu as $k => $v) {
            if ($organization_permissions) {
                $v['filter']['org_id'] = $organization_permissions;
            }

            if ($_GET['act'] == 'jingxiao') {
                $act = $_GET['act'];
                $v['filter']['delivery_mode'] = $act;
            } else {
                if (!$v['filter']['filter_sql']) {
                    $v['filter']['filter_sql'] = '1';
                }
                $v['filter']['filter_sql'] .= ' AND delivery_mode <> "jingxiao"';
            }
            
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $mdl_refund_apply->viewcount($v['filter']);
            $sub_menu[$k]['href']   = 'index.php?app=ome&ctl=admin_refund_apply&act=' . $act . '&view=' . $i++;
        }
        
        return $sub_menu;
    }

    public function request($order_id, $return_id = 0)
    {
        $result = kernel::single('ome_refund_apply')->show_refund_html($order_id, $return_id);
        if ($result['result'] == true) {
            return $result;
        } else {
            exit($result['msg']);
        }
    }

    public function accept($apply_id)
    {
        $url = "index.php?ctl=admin_refund_apply&act=accept&app=ome&p[0]=" . $apply_id;
        if (!$apply_id) {
            $this->splash('error', $url, '退款申请号传递出错');
        }

        $oRefaccept = $this->app->model('refund_apply');
        $oOrder     = $this->app->model('orders');
        $is_archive = kernel::single('archive_order')->is_archive($_GET['source']);
        if ($is_archive) {
            $oOrder = kernel::single('archive_interface_orders');
        }

        $deoObj    = app::get('ome')->model('delivery_order');
        $finder_id = $_GET['finder_id'];
        if ($_POST) {
            $oRefund = $this->app->model('refunds');
            $oLoger  = $this->app->model('operation_log');

            //只有已经接受申请的才能确认。
            $apply_detail = $oRefaccept->refund_apply_detail($apply_id);
            if (in_array($apply_detail['status'], array('2', '5', '6'))) {
                $order_id = $apply_detail['order_id'];
                if ($is_archive) {
                    $order_detail = $oOrder->getOrders(array('order_id' => $order_id), '*');
                } else {
                    $order_detail = $oOrder->order_detail($order_id);
                }

                $ids = $deoObj->getList('delivery_id', array('order_id' => $order_id));
                //如果申请金额大于已付款金额，则报错、退出
                $money = $apply_detail['money'] - $apply_detail['bcmoney'];
                if (round($money, 3) > round(($order_detail['payed']), 3)) {
                    $this->splash('error', $url, '退款申请金额' . $money . '大于订单上的余额！' . $order_detail['payed']);
                }

                //检查当前退款申请单是否允许请求接口
                $refundApplyLib = kernel::single('ome_refund_apply');
                $checkParams    = array(
                    'orderInfo'          => $order_detail,
                    'applyInfo'          => $apply_detail,
                    'is_archive'         => $is_archive,
                    'api_fail_flag'      => $_POST['api_fail_flag'],
                    'api_refund_request' => $_POST['api_refund_request'],
                );
                $refund_request = $refundApplyLib->checkForRequest($checkParams, $fail_msg);

                //发起前端退款请求
                if ($refund_request == true) {
                    if (!$_POST['pay_type']) {
                        $this->splash('error', $url, '请选择付款类型。');
                    }

                    $_POST['order_id']            = $order_id;
                    $_POST['order_bn']            = $order_detail['order_bn'];
                    $_POST['apply_id']            = $apply_id;
                    $_POST['refund_bn']           = $apply_detail['refund_apply_bn'];
                    $_POST['bcmoney']             = $apply_detail['bcmoney'];
                    $_POST['is_aftersale_refund'] = ($apply_detail['return_id'] > 0) ? true : false;
                    $_POST['shop_id']             = $order_detail['shop_id'];
                    $_POST['return_id']           = $apply_detail['return_id'];
                    $_POST['oid']                 = $apply_detail['oid'];
                    $_POST['product_data']                 = $apply_detail['product_data'];
                    
                    // 补充reship_id, 用于判断是否退货退款
                    $_POST['reship_id'] = $apply_detail['reship_id'];
                    
                    //检查当前订单的状态，标记天猫售前退款的取消发货标记
                    if (in_array($order_detail['process_status'], array('unconfirmed', 'confirmed'))) {
                        $_POST['cancel_dly_status'] = 'SUCCESS';
                    } else {
                        $_POST['cancel_dly_status'] = 'FAIL';
                    }

                    if (!$_POST['refund_type'] || $_POST['refund_type'] == '') {
                        $_POST['refund_type'] = 'apply';
                    }

                    if ($is_archive) {
                        $_POST['is_archive'] = '1';
                    }

                    //检查是否为退货退款，如果是，则回写退货单号
                    if ($apply_detail['reship_id']) {
                        $reship = app::get('ome')->model('reship')->dump($apply_detail['reship_id'], 'return_logi_no');
                        if ($reship) {
                            $_POST['logistics_no'] = $reship['return_logi_no'];
                        }
                    }

                    if ($oRefund->refund_request($_POST)) {
                        $this->splash('success', 'index.php?app=ome&ctl=admin_refund_apply&act=index', '退款请求发起成功');
                    } else {
                        $this->splash('error', 'index.php?app=ome&ctl=admin_refund_apply&act=index', '退款请求发起失败,请重试');
                    }
                } else {
                    $this->begin("index.php?ctl=admin_refund_apply&act=accept&app=ome&p[0]=" . $apply_id);
                    //查找本申请是否是与售后相关的，如果相关，则检查并回写数据
                    $oretrun_refund_apply  = $this->app->model('return_refund_apply');
                    $return_refund_appinfo = $oretrun_refund_apply->dump(array('refund_apply_id' => $apply_id));
                    if ($return_refund_appinfo['return_id']) {
                        $oreturn     = $this->app->model('return_product');
                        $return_info = $oreturn->product_detail($return_refund_appinfo['return_id']);
                        if (($return_info['refundmoney'] + $apply_detail['money']) > $return_info['tmoney']) {
                            $this->end(false, '申请退款金额大于售后的退款金额！');
                        }
                        $return_info['refundmoney'] = $return_info['refundmoney'] + $apply_detail['money'];

                        $oreturn->save($return_info);

                        $oLoger->write_log('return@ome', $return_info['return_id'], "售后退款成功。");
                    } else {
                        if(empty($apply_detail['reship_id']) && app::get('ome')->model('reship')->db_dump(['reship_bn'=>$apply_detail['refund_apply_bn'],'is_check|noequal'=>'5'], 'reship_id')) {
                            $this->end(false, '该退款单存在退货单，不能完成');
                        }
                    }
                    //订单信息更新
                    $is_full_refund = false;
                    $orderdata      = array();
                    if (round($apply_detail['money'], 3) == round(($order_detail['payed']), 3)) {
                        $orderdata['pay_status'] = 5;

                        if ($order_detail['ship_status'] == '2') {
                            $is_full_refund = true; //部分发货&&全额退款打标
                        }

                        //2011.12.13删除屏蔽
                        //将原来的全额退款的 未发货的订单取消 封装成一个方法check_iscancel
                        //$oRefaccept->check_iscancel($apply_detail['order_id'],$apply_detail['memo']); 下面更新订单状态的时候也会释放掉冻结库存
                    } else {
                        $orderdata['pay_status'] = 4;
                        // 部分退款置异常，防止客服不看直接审核
                        if (!$is_archive) {
                            kernel::single('ome_order_abnormal')->abnormal_set($apply_detail['order_id'], '订单未发货部分退款');
                        }
                    }
                    $orderdata['order_id'] = $apply_detail['order_id'];
                    $orderdata['payed']    = $order_detail['payed'] - ($apply_detail['money'] - $apply_detail['bcmoney']); //需要将补偿运费减掉
                    $oOrder->save($orderdata);

                    $oLoger->write_log('order_modify@ome', $orderdata['order_id'], $fail_msg . "退款成功，更新订单退款金额。");

                    //退款申请状态更新
                    $applydata                  = array();
                    $applydata['apply_id']      = $apply_id;
                    $applydata['status']        = 4; //已经退款
                    $applydata['refunded']      = $apply_detail['money']; // + $order_detail['payinfo']['cost_payment'];
                    $applydata['last_modified'] = time();
                    $applydata['account']       = $_POST['account'];
                    $applydata['pay_account']   = $_POST['pay_account'];
                    $applydata['pay_type']      = $_POST['pay_type']; //退款类型
                    $_POST['payment'] && $applydata['payment']       = $_POST['payment']; //退款支付方式
                    $oRefaccept->save($applydata, true);
                    $oLoger->write_log('refund_apply@ome', $applydata['apply_id'], "退款成功，更新退款申请状态。");

                    //更新售后退款金额
                    $return_id = intval($_POST['return_id']);
                    if (!empty($return_id)) {
                        $sql = "UPDATE `sdb_ome_return_product` SET `refundmoney`=IFNULL(`refundmoney`,0)+{$apply_detail['money']} WHERE `return_id`='" . $return_id . "'";
                        kernel::database()->exec($sql);
                    }

                    //单据生成：生成退款单
                    $refunddata      = array();
                    $refund_apply_bn = $apply_detail['refund_apply_bn'];
                    if ($refund_apply_bn) {
                        $refund_bn = $refund_apply_bn;
                    } else {
                        $refund_bn = $oRefund->gen_id();
                    }
                    $refunddata['refund_bn']   = $refund_bn;
                    $refunddata['order_id']    = $apply_detail['order_id'];
                    $refunddata['shop_id']     = $order_detail['shop_id'];
                    $refunddata['account']     = $_POST['account'];
                    $refunddata['bank']        = $_POST['bank'];
                    $refunddata['pay_account'] = $apply_detail['pay_account'];
                    $refunddata['currency']    = $order_detail['currency'];
                    $refunddata['money']       = $apply_detail['money'];
                    $refunddata['paycost']     = 0; //没有第三方费用
                    $refunddata['cur_money']   = $apply_detail['money']; //汇率计算 TODO:应该为汇率后的金额，暂时是人民币金额
                    $refunddata['pay_type']    = $_POST['pay_type'];
                    $_POST['payment'] && $refunddata['payment']     = $_POST['payment'];
                    $paymethods                = ome_payment_type::pay_type();
                    $refunddata['paymethod']   = $paymethods[$refunddata['pay_type']];
                    //Todo ：确认paymethod
                    $opInfo              = kernel::single('ome_func')->getDesktopUser();
                    $refunddata['op_id'] = $opInfo['op_id'];

                    $refunddata['t_ready'] = time();
                    $refunddata['t_sent']  = time();
                    $refunddata['status']  = "succ"; #支付状态
                    $refunddata['memo']    = $apply_detail['memo'];
                    $refunddata['org_id']  = $apply_detail['org_id'];
                    $refunddata['product_data']  = $apply_detail['product_data'];

                    $oRefund->save($refunddata);

                    //更新订单支付状态
                    if ($is_archive) {
                        kernel::single('archive_order_func')->update_order_pay_status($apply_detail['order_id']);
                    } else {
                        kernel::single('ome_order_func')->update_order_pay_status($apply_detail['order_id'], true, __CLASS__.'::'.__FUNCTION__);
                    }

                    if ($apply_detail["refund_refer"] == "1") {
                        //生成售后单
                        kernel::single('sales_aftersale')->generate_aftersale($apply_id, 'refund');
                    }

                    $oLoger->write_log('refund_accept@ome', $refunddata['refund_id'], "退款成功，生成退款单" . $refunddata['refund_bn']);
                    if (!empty($return_id)) {
                        $return_data     = array('return_id' => $_POST['return_id'], 'status' => '4', 'refundmoney' => $refunddata['money'], 'last_modified' => time());
                        $Oreturn_product = $this->app->model('return_product');
                        $Oreturn_product->update_status($return_data);
                    }

                    //部分发货并且全额退款成功,系统自动执行“余单撤消”操作
                    if ($is_full_refund) {
                        kernel::single('ome_order_order')->fullRefund_order_revoke($apply_detail['order_id']);
                    }
                    kernel::single('ome_refund_apply')->updateOrderObjectsPayStatusByItemIds($apply_id);
                    // 增加service扩展点，允许外部service在退款成功后做自定义处理
                    $services = kernel::servicelist('ome.service.refund.apply.accept.refund.after');
                    if ($services) {
                        foreach ($services as $service) {
                            if (method_exists($service, 'after_refund')) {
                                $service->after_refund($apply_id);
                            }
                        }
                    }
                    $this->end(true, '申请退款成功', 'index.php?app=ome&ctl=admin_refund_apply&act=index');
                }
            }
        } else {
            //退款请求失败标识
            $refunds                  = $oRefaccept->refund_apply_detail($apply_id);
            $this->pagedata['refund'] = $refunds;
            if ($refunds['status'] == '6') {
//退款失败
                $api_fail_flag = 'true';
            } else {
                $api_fail_flag = 'false';
            }
            $this->pagedata['api_fail_flag'] = $api_fail_flag;

            if ($is_archive) {
                $order_detail = $oOrder->getOrders(array('order_id' => $this->pagedata['refund']['order_id']), '*');
            } else {
                $order_detail = $oOrder->order_detail($this->pagedata['refund']['order_id']);
            }
            $this->pagedata['order'] = $order_detail;
            $oPayment                = $this->app->model('payments');

            //前端店铺支付方式
            $payment_cfgObj = $this->app->model('payment_cfg');
            $oShop          = $this->app->model('shop');
            $c2c_shop       = ome_shop_type::shop_list();
            $shop_id        = $order_detail['shop_id'];
            $shop_detail    = $oShop->dump($shop_id, 'node_type,node_id');
            if ($shop_id) {
                $payment = kernel::single('ome_payment_type')->paymethod($shop_id);
            } else {
                $payment = $oPayment->getMethods();
            }
            $payment_cfg = $payment_cfgObj->dump(array('pay_bn' => $order_detail['pay_bn']), 'id,pay_type');

            $this->pagedata['shop_id']  = $shop_id;
            $this->pagedata['node_id']  = $shop_detail['node_id'];
            $this->pagedata['payment']  = $payment;
            $this->pagedata['pay_type'] = $payment_cfg['pay_type'];
            if ($payment_cfg['id']) {
                $order_paymentcfg = kernel::single('ome_payment_type')->paymethod($shop_id, $payment_cfg['pay_type']);
            }
            $this->pagedata['order_paymentcfg'] = $order_paymentcfg;
            $this->pagedata['payment_id']       = $payment_cfg['id'];
            $this->pagedata['typeList']         = ome_payment_type::pay_type();
            $this->pagedata['pay_type']         = $this->pagedata['pay_type'];
            $aRet                               = $oPayment->getAccount();
            $aAccount                           = array('--使用已存在帐户--');
            foreach ($aRet as $v) {
                $aAccount[$v['bank'] . "-" . $v['account']] = $v['bank'] . "-" . $v['account'];
            }
            $addon                         = unserialize($refunds['addon']);
            $this->pagedata['return_id']   = $addon['return_id'];
            $this->pagedata['pay_status']  = kernel::single('ome_order_status')->pay_status();
            $this->pagedata['finder_id']   = $finder_id;
            $this->pagedata['pay_account'] = $aAccount;
            $memberid                      = $this->pagedata['order']['member_id'];
            $oMember                       = $this->app->model('members');
            $this->pagedata['member']      = $oMember->member_detail($memberid);

            $this->display('admin/refund/refund_accept.html');
        }
    }

    /*add by hujie 添加退款申请*/
    public function showRefund()
    {
        if ($_POST) {
            $source_url = $_POST['back_url'];
            $error_msg = '';

            if ($source_url != 'order_confirm') {
                $begin_url = "index.php?ctl=admin_refund_apply&act=request&app=ome&p[0]=" . $_POST['order_id'];
            }
            $this->begin($begin_url);

            $back_url = explode("|", $source_url);
            if (count($back_url)) {
                $back_url = 'index.php?app=ome&ctl=' . $back_url[0] . '&act=' . $back_url[1] . '&' . $back_url[2];
            }

            //创建退款申请单
            $_POST['source']       = 'local';
            $_POST['refund_refer'] = '0';
            $is_update_order       = true; //是否更新订单付款状态
            $return                = kernel::single('ome_refund_apply')->createRefundApply($_POST, $is_update_order, $error_msg);
            if (!$return) {
                //创建失败
                if ($source_url != 'order_confirm') {
                    $this->end(false, $error_msg, $back_url);
                } else {
                    $this->end(false, $error_msg);
                }
            }
            
            // 订单支付状态发生变化
            // [更新]预约订单的相关状态
            $orderSdf = [
                'order_id' => $_POST['order_id'],
                'pay_status' => '6', //退款申请中
            ];
            kernel::single('ome_order_reservation')->operateReservationOrder($orderSdf, $error_msg);
            
            //创建成功
            if ($source_url != 'order_confirm') {
                $this->end(true, $return['msg'], $back_url);
            } else {
                $this->end(true, $return['msg']);
            }
        }
    }

    public function do_export()
    {
        $selected   = $_POST['apply_id'];
        $oRefaccept = $this->app->model('refund_apply');
        foreach ($selected as $oneappid) {
            $export[] = $oRefaccept->refund_apply_detail($oneappid);
        }
        echo '<pre>';
        print_r($export);
        echo '</pre>';
    }

    /**
     * 上传凭证留言
     * @param   type taobao/tmall
     * @return  type    description
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function refuse_message($apply_id=null, $shop_type=null)
    {
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $op_name       = kernel::single('desktop_user')->get_name();
        $shop_type = empty($_POST['shop_type']) ? $shop_type : $_POST['shop_type'];
        $apply_id= empty($_POST['apply_id']) ? $apply_id : $_POST['apply_id'];
        $refuse_reason = $memo = array();

        if($shop_type == 'luban'){
            $refunddata = $oRefund_apply->refund_apply_detail($apply_id);
            $refuse_reason = kernel::single('erpapi_router_request')->set('shop', $refunddata['shop_id'])->aftersale_getRefuseReason($refunddata);
        }

        if ($_POST) {
            $this->begin();
            $apply_id   = $_POST['apply_id'];
            $data = array(
                'apply_id'        => $apply_id,
                'shop_id'         => $refunddata['shop_id'],
                'refund_apply_bn' => $refunddata['refund_apply_bn'],
            );

            $newmemo     = array('op_name' => $op_name, 'op_time' => date('Y-m-d H:i:s', time()), 'op_content' => htmlspecialchars($_POST['memo']));

            if ($shop_type == 'tmall') {
                $oRefund_apply_type = $this->app->model('refund_apply_tmall');
            } elseif($shop_type == 'luban'){
                $oRefund_apply_type = $this->app->model('refund_apply_luban');
                $newmemo['seller_refuse_reason_id'] = $_POST['seller_refuse_reason_id'];
                $data['refuse_message'] = htmlspecialchars($_POST['memo']);
            }else {
                $oRefund_apply_type = $this->app->model('refund_apply_taobao');
            }

            if($refunddata['shop_type'] == 'luban' && $refunddata['source'] == 'matrix' && $_FILES['attachment']['size'] == 0){
                $this->end(false, '请上传图片');
            }

            $upload_file = "";

            if ($_FILES['attachment']['size'] != 0) {
                if ($_FILES['attachment']['size'] > 512000) {
                    $this->end(false, '上传文件不能超过500K!');
                }
                $type   = array('gif', 'jpg', 'png');
                $imgext = strtolower(substr(strrchr($_FILES['attachment']['name'], '.'), 1));
                if ($_FILES['attachment']['name']) {
                    if (!in_array($imgext, $type)) {
                        $text = implode(",", $type);
                        $this->end(false, "您只能上传以下类型文件{$text}!");
                    }
                }

                $ss               = kernel::single('base_storager');
                $id               = $ss->save_upload($_FILES['attachment'], "file", "", $msg); //返回file_id;
                $newmemo['image'] = $ss->getUrl($id, "file");
                $imagebinary      = $newmemo['image'];
                //$imagebinary = app::get('ome')->model('return_product')->imagetobinary($_FILES['attachment']['tmp_name']);;
            }
            $memo[]       = $newmemo;
            $refund_apply = $oRefund_apply_type->dump(array('apply_id' => $apply_id));

            if ($refund_apply) {
                $oldmemo = $refund_apply['message_text'];
                if ($oldmemo) {
                    $oldmemo = unserialize($oldmemo);
                    foreach ($oldmemo as $oldmemo) {
                        $memo[] = $oldmemo;
                    }

                }
                if ($memo) {
                    $data['message_text'] = serialize($memo);
                }

                $oRefund_apply_type->update($data, array('apply_id' => $apply_id));

            } else {
                if ($memo) {
                    $data['message_text'] = serialize($newmemo);
                }
                $oRefund_apply_type->save($data);
            }
            #回写

            if($shop_type == 'luban'){
                //更新主表信息 快手为拒绝退款操作
                $oRefund_apply->update(array('status'=>3,'memo'=>$newmemo['op_content']),array('apply_id' => $apply_id));
                $ary_notice = array(
                    'order_id' => $refunddata['order_id'],
                    'return_bn' => $refunddata['refund_apply_bn'],
                    'shop_type' => $refunddata['shop_type'],
                    'return_type' => 'refund',
                    'kinds' => 'refund',
                    'memo' => array(
                        'reject_reason_code' => $newmemo['seller_refuse_reason_id'],
                        'remark' => $newmemo['op_content'],
                        'refuse_proof' => $newmemo['image'],
                        'parse' => 'first',
                    )
                );

                $rs = kernel::single('erpapi_router_request')->set('shop', $refunddata['shop_id'])->aftersale_updateAfterSaleStatus($ary_notice,'5','async');
                if($rs['rsp'] == 'fail'){
                    $this->end(false, $rs['msg']);
                }
            }else{
                foreach (kernel::servicelist('service.refund') as $object => $instance) {
                    if (method_exists($instance, 'add_refundmemo')) {
                        $data['newmemo'] = $newmemo;
                        $data['seller_refuse_reason_id'] = $_POST['seller_refuse_reason_id'];

                        if ($imagebinary) {
                            $data['imagebinary'] = $imagebinary;
                        }
                        $instance->add_refundmemo($data);
                    }
                }
            }

            $this->end(true, '上传成功');
        }


        $this->pagedata['shop_type'] = $shop_type;
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->pagedata['apply_id']  = $apply_id;
        $this->pagedata['refuse_reason'] = $refuse_reason;

        if ($type == 'bbc') {
            $this->display('admin/refund/plugin/refund_bbc_memo.html');
        } else {
            $this->display('admin/refund/plugin/refund_memo.html');
        }
    }

    public function file_download2($apply_id)
    {
        $oProduct = $this->app->model('return_product');
        $oApply   = $this->app->model('refund_apply_tmall');
        $info     = $oApply->dump($apply_id);
        $filename = $info['refuse_proof'];
        if (is_numeric($filename)) {
            $ss = kernel::single('base_storager');
            $a  = $ss->getUrl($filename, "file");
            $oProduct->file_download($a);
        } else {
            header('Location:' . $filename);
        }

    }

    /**
     * 拒绝
     * @param   type    $varname    description
     * @return  type    description
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function upload_refuse_message($apply_id=0, $type = 'taobao')
    {
        set_time_limit(0);
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $op_name       = kernel::single('desktop_user')->get_name();
        $oLoger        = app::get('ome')->model('operation_log');
        if ($_POST) {
            $this->begin();
            $apply_id   = $_POST['apply_id'];
            $shop_type  = $_POST['type'];
            $reason_id = $_POST['seller_refuse_reason_id'];
            $refunddata = $oRefund_apply->refund_apply_detail($apply_id);

            if ($shop_type == 'tmall') {
                $oRefund_apply_type  = $this->app->model('refund_apply_tmall');
                $refund_tmall        = $oRefund_apply_type->dump(array('apply_id' => $apply_id));
                $operation_contraint = $refund_tmall['operation_contraint'];
                if ($operation_contraint) {
                    $operation_contraint = explode('|', $operation_contraint);
                    if (in_array('cannot_refuse', $operation_contraint)) {
                        $this->end(false, '此单据,不允许拒绝，必须同意');
                    }
                    if (in_array('refund_onweb', $operation_contraint)) {
                        $this->end(false, '此单据,回到web页面上操作');
                    }
                }
            } else {
                $oRefund_apply_type = $this->app->model('refund_apply_taobao');
            }
            $data = array(
                'apply_id'        => $apply_id,
                'shop_id'         => $refunddata['shop_id'],
                'refund_apply_bn' => $refunddata['refund_apply_bn'],

            );
            $memo        = array('op_name' => $op_name, 'op_time' => date('Y-m-d H:i:s', time()), 'op_content' => htmlspecialchars($_POST['memo']));
            $upload_file = "";
            
            //拒绝退款是否请求平台(birken勃肯客户搬物料代码功能)
            $refuseRequest = app::get('ome')->getConf("ome.refund.refuse.request");
            
            //reuqest
            if ($refuseRequest != 'false' && in_array($shop_type, array('taobao', 'tmall'))) {
                if ($_FILES['attachment']['size'] != 0) {
                    if ($_FILES['attachment']['size'] > 512000) {
                        $this->end(false, '上传文件不能超过500K!');
                    }

                    $type   = array('gif', 'jpg', 'png');
                    $imgext = strtolower(substr(strrchr($_FILES['attachment']['name'], '.'), 1));
                    if ($_FILES['attachment']['name']) {
                        if (!in_array($imgext, $type)) {
                            $text = implode(",", $type);
                            $this->end(false, "您只能上传以下类型文件{$text}!");
                        }
                    }

                    $ss            = kernel::single('base_storager');
                    $id            = $ss->save_upload($_FILES['attachment'], "file", "", $msg); //返回file_id;
                    $memo['image'] = $ss->getUrl($id, "file");
                    if ($shop_type == 'tmall') {
                        $rh          = fopen($_FILES['attachment']['tmp_name'], 'rb');
                        $imagebinary = fread($rh, filesize($_FILES['attachment']['tmp_name']));
                        $imagebinary = base64_encode($imagebinary);
                        fclose($rh);
                    } else {
                        $imagebinary = $memo['image'];
                    }
                } else {

                    $this->end(false, '请上传凭证图片!');
                }
            }

            $refund_apply = $oRefund_apply_type->dump(array('apply_id' => $apply_id));
            if ($memo) {
                $data['memo'] = serialize($memo);
            }
            if ($refund_apply) {

                $oRefund_apply_type->update($data, array('apply_id' => $apply_id));

            } else {
                $oRefund_apply_type->save($data);
            }
            #回写
            $refund_service = kernel::single('ome_service_refund_apply');

            if (method_exists($refund_service, 'update_status')) {
                $adata = array(
                    'refuse_message' => htmlspecialchars($_POST['memo']),
                    'refuse_proof'   => $imagebinary,
                    'apply_id'       => $apply_id,
                    'imgext'         => $imgext,
                    'reason_id'         => $reason_id,
                );

                $rs = $refund_service->update_status($adata, 3, 'sync');

                if ($rs['rsp'] == 'succ') {
                    kernel::single('ome_refund_apply')->update_refund_applyStatus('3', $refunddata);
                } else {

                    $this->end(false, $rs['msg']);
                }
            }
            $this->end(true, '上传成功');
        }

        $this->pagedata['apply_id']  = $apply_id;
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->pagedata['type']      = $type;
        if (in_array($type,array( 'meituan4medicine'))) {
            $refuse_reason = kernel::single('ome_aftersale_request_'.$type)->getAftersaleReason('refund');
            $this->pagedata['refuse_reason'] = $refuse_reason;
        }
        #BBC的不需要上传凭证
        if (in_array($type, array('bbc', 'ecstore'))) {
            $this->display('admin/refund/plugin/refuse_bbc_message.html');
        } elseif ($type == "kaola") {
            $this->display('admin/refund/plugin/refuse_kaola_message.html');
        } else {
            $this->display('admin/refund/plugin/refuse_message.html');
        }
    }

    /**
     * 批量变更退款申请单状态
     * @param
     * @return
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function batch_Updatestatus()
    {
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $oReturn_batch = app::get('ome')->model('return_batch');
        $status_type   = $_GET['status_type'];
        if (!in_array($status_type, array('agree', 'refuse'))) {
            echo '暂不支持此状态变更';
            exit;
        }
        $error_msg  = array();
        $chk_msg    = array(); //检测
        $shopArr = app::get('ome')->model('shop')->getList('shop_id', ['delivery_mode'=>'jingxiao']);
        $shopJXid = array_column($shopArr,'shop_id');
        $applyFilter = array('apply_id' => $_POST['apply_id']);
        if($shopJXid) {
            $applyFilter['shop_id|notin'] = $shopJXid;
        }
        $apply_list = $oRefund_apply->getlist('apply_id,shop_id,refund_apply_bn,status,shop_type,source', $applyFilter);
        if ($status_type == 'agree') {
#同意
            foreach ($apply_list as $apply) {
                $apply_id = $apply['apply_id'];
                $status   = $apply['status'];

                if (!in_array($status, array('0', '1'))) {
                    $error_msg[] = '单据号:' . $apply['refund_apply_bn'] . ',的状态不可以接受申请';
                }
                if ($apply['shop_type'] == 'tmall' && $apply['source'] == 'matrix') {
                    $return_batch = $oReturn_batch->dump(array('shop_id' => $apply['shop_id'], 'batch_type' => 'accept_refund', 'is_default' => 'true'));
                    if (!$return_batch) {
                        $chk_msg[] = '此次提交包含天猫店铺,请设置默认信息!';
                        break;
                    }
                }
            }
        } elseif ($status_type == 'refuse') {
            foreach ($apply_list as $apply) {
                $apply_id = $apply['apply_id'];
                $status   = $apply['status'];
                $msg      = '';
                if (!in_array($status, array('0', '1', '2'))) {
                    $msg = '单据号:' . $apply['refund_apply_bn'] . ',的当前状态不可以拒绝';

                }
                if ($apply['shop_type'] == 'tmall' && $apply['source'] == 'matrix') {
                    $return_batch = $oReturn_batch->dump(array('shop_id' => $apply['shop_id'], 'batch_type' => 'refuse', 'is_default' => 'true'));
                    if (!$return_batch) {
                        $chk_msg[] = '此次提交包含天猫店铺,请设置默认信息拒绝留言和凭证!';
                        break;
                    }
                }
                if ($msg) {
                    $error_msg[] = $msg;
                }

            }
        }
        //查询是否都是线上单据，是否淘宝和天猫
        $applyObj                                 = kernel::single('ome_refund_apply');
        $this->pagedata['error_msg']              = $error_msg;
        $this->pagedata['chk_msg']                = $chk_msg;
        $need_refund_list                         = $applyObj->refund_list($status_type, array_column($apply_list, 'apply_id'));
        $this->pagedata['need_refund_list_count'] = count($need_refund_list);
        $need_refund_list                         = json_encode($need_refund_list);
        $this->pagedata['need_refund_list']       = $need_refund_list;
        $this->pagedata['status_type']            = $status_type;
        $this->pagedata['finder_id']              = $_GET['finder_id'];
        $this->display('admin/refund/plugin/batch_taobao.html');
    }

    //批量更新
    public function ajax_batch()
    {
        set_time_limit(0);
        $refundObj  = kernel::single('ome_refund_apply');
        $data       = $_POST;
        $ajaxParams = trim($data['ajaxParams']);
        if (strpos($ajaxParams, ';')) {
            $params = explode(';', $ajaxParams);
        } else {
            $params = array($ajaxParams);
        }
        $status_type = $data['status_type'];
        $refund_id   = json_decode($data['refund_id'], true);
        $rs          = $refundObj->batch_update($status_type, $params);
        echo json_encode(array('total' => count($params), 'succ' => $rs['succ'], 'fail' => $rs['fail'], 'error_msg' => $rs['error_msg']));
    }

    //更新退款单状态
    public function do_updateApply($apply_id, $status)
    {
        $oRefund_apply      = app::get('ome')->model('refund_apply');
        $applyObj           = kernel::single('ome_refund_apply');
        $apply              = $oRefund_apply->dump($apply_id);
        $apply['oper_memo'] = '向线上请求拒绝失败,本地拒绝';
        $applyObj->update_refund_applyStatus($status, $apply);
        $data = array('rsp' => 'succ');
        echo json_encode($data);
    }

    //批量同步退款申请单状态
    public function batch_get_refund_detial()
    {
        $oRefund_apply    = app::get('ome')->model('refund_apply');
        $oReturn_batch    = app::get('ome')->model('return_batch');
        $error_msg        = array();
        $chk_msg          = array(); //检测
        $apply_list       = $oRefund_apply->getlist('apply_id,shop_id,refund_apply_bn,status,shop_type,source', array('apply_id' => $_POST['apply_id']));
        $need_refund_list = array();
        foreach ($apply_list as $key => $apply) {
            $apply_id = $apply['apply_id'];
            $status   = $apply['status'];
            if (!in_array($status, array('0', '1')) || ($apply['shop_type'] != 'tmall')) {
                $error_msg[] = '单据号:' . $apply['refund_apply_bn'] . ',的状态或来源不可以批量同步！';
                unset($apply_list[$key]);
            }
            if ($apply['source'] != 'matrix') {
                $error_msg[] = '单据号:' . $apply['refund_apply_bn'] . ',的不是线上订单！';
                unset($apply_list[$key]);
            }
            if (!empty($apply_list[$key])) {
                $need_refund_list[] = $apply_list[$key]['apply_id'];
            }
        }
        if (empty($apply_list)) {
            $chk_msg[] = '没有符合更新条件的退款单！';
        }
        $this->pagedata['error_msg']              = $error_msg;
        $this->pagedata['chk_msg']                = $chk_msg;
        $this->pagedata['need_refund_list_count'] = count($need_refund_list);
        $need_refund_list                         = json_encode($need_refund_list);
        $this->pagedata['need_refund_list']       = $need_refund_list;
        $this->pagedata['ctl']                    = 'refund_apply';
        $this->pagedata['finder_id']              = $_GET['finder_id'];
        $this->display('admin/refund/plugin/batch_tmall.html');
    }

    /**
     * 天猫同步更新退款单
     */
    public function ajax_get_refund_detial()
    {
        set_time_limit(0);
        $data       = $_POST;
        $ajaxParams = trim($data['ajaxParams']);
        if (strpos($ajaxParams, ';')) {
            $params = explode(';', $ajaxParams);
        } else {
            $params = array($ajaxParams);
        }
        $rs = $this->get_refund_detial($params);
        echo json_encode(array('total' => count($params), 'succ' => $rs['succ'], 'fail' => $rs['fail'], 'error_msg' => $rs['error_msg']));
    }
    #重新更新退款单
    public function get_refund_detial($all_apply_id)
    {
        set_time_limit(0);
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $obj_orders    = app::get('ome')->model('orders');

        $error_msg     = array();
        $need_apply_id = array();

        foreach ($all_apply_id as $_apply_id) {
            $apply_id        = explode('||', $_apply_id);
            $need_apply_id[] = $apply_id[1];
        }
        $sql = 'SELECT
                    apply.source,apply.shop_type,apply.refund_apply_bn,apply.shop_id,orders.order_bn
                FROM sdb_ome_refund_apply  apply
                left join sdb_ome_orders orders
                on apply.order_id=orders.order_id
                WHERE apply_id in(' . implode(',', $need_apply_id) . ')';
        $apply_list = $oRefund_apply->db->select($sql);
        foreach ($apply_list as $apply) {
            $shop_id      = $apply['shop_id'];
            $refund_id    = $apply['refund_apply_bn'];
            $refund_phase = 'onsale';
            $order_bn     = $apply['order_bn'];
            $returnRsp = kernel::single('erpapi_router_request')->set('shop', $shop_id)->finance_getRefundDetail($refund_id, $refund_phase, $order_bn);
            if ($returnRsp && $returnRsp['rsp'] == 'fail') {
                $fail++;
                $error_msg[] = '单号:' . $apply['refund_apply_bn'] . "," . $returnRsp['err_msg'];
            } else {
                if ($returnRsp['rsp'] == 'succ') {
                    #在退款模块，只处理退款的,不处理售后
                    if ($returnRsp['data']['has_good_return'] == false) {
                        if ($returnRsp['data']['refund_fee'] > 0) {
                            //$returnRsp['data']['refund_type'] = 'refund';#只退款
                            kernel::single('ome_return')->get_return_log($returnRsp['data'], $shop_id, $msg);
                        } else {
                            $fail++;
                            $error_msg[] = '单号:' . $apply['refund_apply_bn'] . "," . ' error refund money';
                        }
                    } else {
                        #在退款这边，不处理售后的单子
                        $fail++;
                        $error_msg[] = '单号:' . $apply['refund_apply_bn'] . "," . $rs['msg'];
                    }
                }
            }
        }
        $result = array('error_msg' => $error_msg, 'fail' => $fail);
        return $result;
    }

    /**
     * 批量同意退款
     */
    public function batchAgreeRefund()
    {
        global $shop_id;
        $payments_obj     = $this->app->model('payments');
        $request          = kernel::single('base_component_request');
        $refund_ids       = $request->get_post();
        $refund_apply_obj = $this->app->model('refund_apply');
        if ($refund_ids["isSelectedAll"] == "_ALL_" && $_GET["view"] == "3") {
            //“已接受申请”tab “全选”操作  根据filter条件获取所有数据
            $rs_apply_ids = $refund_apply_obj->getlist("apply_id", array("status" => "2", "disabled" => "false"));
            $apply_ids    = array();
            foreach ($rs_apply_ids as $var_ai) {
                $apply_ids[] = $var_ai["apply_id"];
            }
            $refund_ids["apply_id"] = $apply_ids;
        } elseif ($refund_ids["isSelectedAll"] == "_ALL_") {
            //全部、未处理、审核中
            echo '<span style="color:red;font-weight:bold;">批量同意退款不支持这种选择方式！</span>';exit;
        }
        $shopArr = app::get('ome')->model('shop')->getList('shop_id', ['delivery_mode'=>'jingxiao']);
        $shopJXid = array_column($shopArr,'shop_id');
        $applyFilter = array('apply_id' => $refund_ids['apply_id']);
        if($shopJXid) {
            $applyFilter['shop_id|notin'] = $shopJXid;
        }
        $apply_list = app::get('ome')->model('refund_apply')->getList('apply_id', $applyFilter);
        $refund_ids['apply_id'] = array_column($apply_list, 'apply_id');
        $total_money   = 0;
        $all_order_ids = array(); // 对应的订单id
        $_key          = 0;
        foreach ($refund_ids['apply_id'] as $key => $apply_id) {
            $_key++;
            $a_refund = $refund_apply_obj->refund_apply_detail($apply_id);

            // 检查是否选中了没有接受申请的退款单
            if (!in_array($a_refund['status'], array('2', '5'))) {
                echo '<span style="color:red;font-weight:bold;">不处理未接受申请和请求失败的退款申请单!</span>';exit;
            }

            // 检查是否属于同一个店铺
            $new_shop_id = $a_refund['shop_id'];
            if ($_key > 1) {
                if ($new_shop_id != $shop_id) {
                    echo '<span style="color:red;font-weight:bold;">只有同一来源店铺订单，才可以批量退款！</span>';exit;
                }
            }

            // 计算所有接受申请的单子的总金额
            $total_money += $a_refund['money'];
            $all_order_ids[] = $a_refund['order_id'];

            $shop_id = $a_refund['shop_id'];
        }

        $applys_nums = count($refund_ids['apply_id']);

        // 选中单子数量检测
        if ($applys_nums <= 1) {
            echo '<span style="color:red;font-weight:bold;">至少选择两个退款申请单！</span>';exit;
        }

        // 已经存在的账户
        $all_banks   = $payments_obj->getAccount();
        $all_account = array('--使用已存在帐户--');
        foreach ($all_banks as $v) {
            $all_account[$v['bank'] . "-" . $v['account']] = $v['bank'] . " - " . $v['account'];
        }

        $this->pagedata['pay_account'] = $all_account;
        $this->pagedata['apply_ids']   = serialize($refund_ids['apply_id']);
        $this->pagedata['total_money'] = $total_money;
        $this->pagedata['applys_nums'] = $applys_nums;
        $this->pagedata['shop_id']     = $shop_id;
        $this->pagedata['typeList']    = ome_payment_type::pay_type(); //付款类型
        $this->pagedata['order_id']    = serialize($all_order_ids); #本次批量处理的订单号

        $this->display('admin/refund/batch_agree_refund.html');

    }

    /**
     * 处理批量同意退款
     */
    public function doBatchAgreeRefund()
    {
        $url = 'index.php#app=ome&ctl=admin_refund_apply&act=index';
        $this->begin($url);

        $data      = $_POST;unset($_POST);
        $apply_ids = unserialize($data['apply_ids']);

        $refund_apply_obj  = $this->app->model('refund_apply');
        $refund_obj        = $this->app->model('refunds');
        $shop_obj          = $this->app->model('shop');
        $operation_log_obj = $this->app->model('operation_log');

        $refundApplyLib = kernel::single('ome_refund_apply');

        foreach ($apply_ids as $key => $apply_id) {
            $a_apply    = $refund_apply_obj->refund_apply_detail($apply_id);
            $is_archive = kernel::single('archive_order')->is_archive($a_apply['source']);

            if ($is_archive) {
                $order_obj    = kernel::single('archive_interface_orders');
                $order_detail = $order_obj->getOrders(array('order_id' => $a_apply['order_id']), '*');
            } else {
                $order_obj    = $this->app->model('orders');
                $order_detail = $order_obj->order_detail($a_apply['order_id']);
            }

            $shop_detail = $shop_obj->dump($order_detail['shop_id'], 'node_id,node_type');
            
            // 商责退运费
            if(in_array($a_apply['tag_type'], ['9'])){
                $this->splash('error', $url, '退款申请单号：' . $a_apply['refund_apply_bn'] . ' 为：商责退运费，不能批量退款!');
            }
            
            // 如果申请金额大于已付金额, 则退出
            $money = $a_apply['money'] - $a_apply['bcmoney'];
            if (round($money, 3) > round($order_detail['payed'], 3)) {
                $this->splash('error', $url, '退款申请单号：' . $a_apply['refund_apply_bn'] . ' 退款申请金额' . $money . '大于订单上的余额' . $order_detail['payed']);
            }

            //检查
            // 检查是否选中了没有接受申请的退款单
            if (!in_array($a_apply['status'], array('2', '5'))) {
                $this->splash('error', $url, '退款申请单号：' . $a_apply['refund_apply_bn'] . ' 状态不正确(只处理已接受申请、退款中状态)');
            }

            //检查当前退款申请单是否允许请求退款接口
            $api_fail_flag      = 'false'; //是否存在请求接口失败
            $api_refund_request = 'true'; //是否发起前端退款请求
            $checkParams        = array(
                'orderInfo'          => $order_detail,
                'applyInfo'          => $a_apply,
                'is_archive'         => $is_archive,
                'api_fail_flag'      => $api_fail_flag,
                'api_refund_request' => $api_refund_request,
            );
            $refund_request = $refundApplyLib->checkBatchForRequest($checkParams, $err_msg);

            // 发起前端退款请求
            if ($refund_request) {
                if (!$data['pay_type']) {
                    $this->splash('error', $url, '请选择付款类型。');
                }

                $data['order_id']  = $a_apply['order_id'];
                $data['apply_id']  = $apply_id;
                $data['refund_bn'] = $a_apply['refund_apply_bn'];
                $data['bcmoney']   = $a_apply['bcmoney'];
                $data['money']     = $money;
                if ($is_archive) {$data['is_archive'] = 1;}

                if ($refund_obj->refund_request($data)) {
                    //$this->splash('success',$url,'退款请求发起成功');
                } else {
                    $this->splash('error', $url, '退款申请单号：' . $a_apply['refund_apply_bn'] . ' 退款请求发起失败,请重试');
                }

            } else {

                // 查找本申请是否是与售后相关的，如果相关，则检查并回写数据
                $return_refund_obj  = $this->app->model('return_refund_apply');
                $return_refund_info = $return_refund_obj->dump(array('refund_apply_id' => $apply_id));
                if ($return_refund_info['return_id']) {
                    $return_product_obj = $this->app->model('return_product');
                    $return_info        = $return_product_obj->product_detail($return_refund_info['return_id']);
                    if (($return_info['refundmoney'] + $a_apply['money']) > $return_info['tmoney']) {
 
                        $this->end(false, '申请退款金额大于售后的退款金额！');
                    }
                    $return_info['refundmoney'] = $return_info['refundmoney'] + $a_apply['money'];

                    $return_product_obj->save($return_info);

                    $operation_log_obj->write_log('return@ome', $return_info['return_id'], "售后退款成功。");
                } else {
                    if(empty($a_apply['reship_id']) && app::get('ome')->model('reship')->db_dump(['reship_bn'=>$a_apply['refund_apply_bn'],'is_check|noequal'=>'5'], 'reship_id')) {
 
                        $this->end(false, '该退款单存在退货单，不能完成');
                    }
                }

                // 更新订单信息
                $order_data = array();
                if (round($a_apply['money'], 3) == round($order_detail['payed'], 3)) {
                    $order_data['pay_status'] = 5;
                } else {
                    $order_data['pay_status'] = 4;
                    // 部分退款置异常，防止客服不看直接审核
                    if (!$is_archive) {
                        kernel::single('ome_order_abnormal')->abnormal_set($a_apply['order_id'], '订单未发货部分退款');
                    }
                }
                $order_data['order_id'] = $a_apply['order_id'];
                $order_data['payed']    = $order_detail['payed'] - ($a_apply['money'] - $a_apply['bcmoney']);
                $order_obj->save($order_data);
                $operation_log_obj->write_log('order_modify@ome', $order_data['order_id'], "退款成功，更新订单退款金额。");

                // 退款申请状态更新
                $apply_data                  = array();
                $apply_data['apply_id']      = $apply_id;
                $apply_data['status']        = 4;
                $apply_data['refunded']      = $a_apply['money'];
                $apply_data['last_modified'] = time();
                $apply_data['account']       = $data['account'];
                $apply_data['pay_account']   = $data['pay_account'];
                $apply_data['pay_type']      = $data['pay_type'];
                $apply_data['payment']       = $data['payment'];
                $refund_apply_obj->save($apply_data);
                $operation_log_obj->write_log('refund_apply@ome', $a_apply['apply_id'], "退款成功，更新退款申请状态。");

                //更新售后退款金额
                $addon     = unserialize($a_apply['addon']);
                $return_id = intval($addon['return_id']);
                if (!empty($return_id)) {
                    $sql = "UPDATE `sdb_ome_return_product` SET `refundmoney`=IFNULL(`refundmoney`,0)+{$a_apply['money']} WHERE `return_id`='" . $return_id . "'";
                    kernel::database()->exec($sql);
                }

                // 生成退款单
                $refund_data = array();
                if ($a_apply['refund_apply_bn']) {
                    $refund_data['refund_bn'] = $a_apply['refund_apply_bn'];
                } else {
                    $refund_data['refund_bn'] = $refund_obj->gen_id();
                }
                $refund_data['order_id']    = $a_apply['order_id'];
                $refund_data['shop_id']     = $order_detail['shop_id'];
                $refund_data['account']     = $data['account'];
                $refund_data['bank']        = $data['bank'];
                $refund_data['pay_account'] = $a_apply['pay_account'];
                $refund_data['currency']    = $order_detail['currency'];
                $refund_data['money']       = $a_apply['money'];
                $refund_data['paycost']     = 0; //没有第三方费用
                $refund_data['cur_money']   = $a_apply['money']; //汇率计算 TODO:应该为汇率后的金额，暂时是人民币金额
                $refund_data['pay_type']    = $data['pay_type'];
                $refund_data['payment']     = $data['payment'];
                $paymethods                 = ome_payment_type::pay_type();
                $refund_data['paymethod']   = $paymethods[$refund_data['pay_type']];
                //Todo ：确认paymethod
                $opInfo               = kernel::single('ome_func')->getDesktopUser();
                $refund_data['op_id'] = $opInfo['op_id'];

                $refund_data['t_ready'] = time();
                $refund_data['t_sent']  = time();
                $refund_data['status']  = "succ"; #支付状态
                $refund_data['memo']    = $a_apply['memo'];
                $refund_obj->save($refund_data);

                //更新订单支付状态
                if ($is_archive) {
                    kernel::single('archive_order_func')->update_order_pay_status($a_apply['order_id']);
                } else {
                    kernel::single('ome_order_func')->update_order_pay_status($a_apply['order_id'], true, __CLASS__.'::'.__FUNCTION__);
                }

                if ($a_apply["refund_refer"] == "1") {
                    //生成售后单
                    kernel::single('sales_aftersale')->generate_aftersale($apply_id, 'refund');
                }
                // 调用方法更新order_objects表的pay_status
                kernel::single('ome_refund_apply')->updateOrderObjectsPayStatusByItemIds($apply_id);

                $operation_log_obj->write_log('refund_accept@ome', $refund_data['refund_id'], "退款成功，生成退款单" . $refund_data['refund_bn']);
                if (!empty($return_id)) {
                    $return_data     = array('return_id' => $return_id, 'status' => '4', 'refundmoney' => $refund_data['money'], 'last_modified' => time());
                    $Oreturn_product = $this->app->model('return_product');
                    $Oreturn_product->update_status($return_data);
                }
                // 增加service扩展点，允许外部service在退款成功后做自定义处理
                $services = kernel::servicelist('ome.service.refund.apply.accept.refund.after');
                if ($services) {
                    foreach ($services as $service) {
                        if (method_exists($service, 'after_refund')) {
                            $service->after_refund($apply_id);
                        }
                    }
                }
            }
        }

        $this->end(true, app::get('base')->_('申请退款成功'), 'index.php?app=ome&ctl=admin_refund_apply&act=index');
    }

    public function batchRefundOnly() {
        $model = app::get('ome')->model('refund_apply');
        $baseFilter = array('status|nequal' => '0', 'disabled' => 'false', );
        $pageData = array(
            'billName' => '退款申请单',
            'request_url' => $this->url.'&act=dealBatchRefundOnly',
            'maxProcessNum' => 20,
            'close' => true
        );
        $this->pagedata['notice'] = '设置仅退款后，不发起发货单拦截！';
        $this->selectToPageRequest($model, $pageData, $baseFilter);
    }

    public function dealBatchRefundOnly() {
        $ids = explode(';', $_POST['ajaxParams']);
        $retArr = array(
            'total' => count($ids),
            'succ' => 0,
            'fail' => 0,
            'fail_msg' => array()
        );
        $model = app::get('ome')->model('refund_apply');
        $rows = $model->getList('apply_id,refund_apply_bn,bool_type,reship_id,tag_type', array('apply_id'=>$ids));
        foreach ($rows as $row) { 
            if($row['bool_type'] & ome_refund_bool_type::__ONLY_REFUND) {
                continue;
            }
            
            // 商责退运费
            if(in_array($row['tag_type'], ['9'])){
                continue;
            }
            
            if($row['reship_id'] > 0) {
                $retArr['fail']++;
                $retArr['fail_msg'][] = ['obj_bn'=>$row['refund_apply_bn'],'msg'=>'有退货单，不能仅退款！'];
                continue;
            }
            $upData = [
                'bool_type' => ome_refund_bool_type::__ONLY_REFUND | $row['bool_type']
            ];
            $rs = $model->update($upData, ['apply_id'=>$row['apply_id'], 'bool_type'=>$row['bool_type']]);
            if(is_bool($rs)) {
                $retArr['fail']++;
                $retArr['fail_msg'][] = ['obj_bn'=>$row['refund_apply_bn'],'msg'=>'仅退款状态更新失败！'];
                continue;
            }
            app::get('ome')->model('operation_log')->write_log('refund_apply@ome', $row['apply_id'], "设置仅退款");
            foreach(kernel::servicelist('ome.service.refund.apply.refundonly.after') as $object) {
                if(method_exists($object, 'after_refund_only')){
                    $object->after_refund_only($row['apply_id']);
                }
            }
        }
        $retArr['succ'] = $retArr['total'] - $retArr['fail'];
        echo json_encode($retArr);
    }

    public function intercept() {
        $apply_id = (int) $_POST['apply_id'];
        $refund_version = $_POST['refund_version'];
        $refundinfo = app::get('ome')->model('refund_apply')->db_dump($apply_id);
        $refundinfo['refund_version'] = $refund_version;
        $rsp = kernel::single('erpapi_router_request')->set('shop',$refundinfo['shop_id'])->finance_intercept($refundinfo);
        if($rsp['rsp'] == 'succ') {
            kernel::single('ome_refund_flag')->update($apply_id, '拦截包裹');
            app::get('ome')->model('operation_log')->write_log('refund_apply@ome',$apply_id,"发起拦截包裹");
        }
        echo json_encode($rsp);
    }

    public function negotiatereturnRender() {
        $apply_id = (int) $_POST['apply_id'];
        $refund_version = $_POST['refund_version'];
        $refundinfo = app::get('ome')->model('refund_apply')->db_dump($apply_id);
        $refundinfo['refund_version'] = $refund_version;
        $rsp = kernel::single('erpapi_router_request')->set('shop',$refundinfo['shop_id'])->finance_negotiatereturnRender($refundinfo);
        if($rsp['rsp'] != 'succ') {
            echo '操作失败：'.$rsp['msg'];
            exit;
        }
        $this->pagedata['refundinfo'] = $refundinfo;
        $this->pagedata['data'] = $rsp['data'];
        $this->display('admin/refund/negotiatereturn.html');
    }

    public function negotiatereturn() {
        $sdf = [
            'refund_id' => $_POST['refund_id'],
            'refund_version' => $_POST['refund_version'],
            'refund_fee' => $_POST['refund_fee'],
            'address_id' => $_POST['address_id'],
        ];
        if (empty($sdf['address_id'])) {
            $this->splash('error', $this->url, '退货地址必须选择');
        }
        $rsp = kernel::single('erpapi_router_request')->set('shop',$_POST['shop_id'])->finance_negotiatereturn($sdf);
        if($rsp['rsp'] != 'succ') {
            $this->splash('error', $this->url, '操作失败：'.$rsp['msg']);
        }
        kernel::single('ome_refund_flag')->update($_POST['apply_id'], '协商退货退款');
            app::get('ome')->model('operation_log')->write_log('refund_apply@ome',$_POST['apply_id'],"发起协商退货退款");
        $this->splash('success', $this->url, '操作成功');
    }

    public function transfer_reship($apply_id) {
        $url = $this->url.'&act=index';
        
        // 获取退款申请详情
        $refundApplyModel = app::get('ome')->model('refund_apply');
        $refundApply = $refundApplyModel->db_dump(['apply_id' => $apply_id], '*');
        
        if (!$refundApply) {
            $this->splash('error', $url, '退款申请不存在');
        }

        // 商责退运费：不能生成退换货单、拦截退货单
        if (kernel::single('ome_order_platform_taobao_aftersale')->isShangzeByTagType($refundApply['tag_type'])) {
            $this->splash('error', $url, '商责退运费单据不允许生成退货单');
        }
        
        // 调用qzRefundToLJRK方法
        $result = kernel::single('console_reship')->qzRefundToLJRK($refundApply);
        
        if ($result[0] === false) {
            $this->splash('error', $url, $result[1]['msg']);
        }
        
        $this->splash('success', $url, '转退货单成功');
    }

    /**
     * 商家协商页面（退款申请单）
     */
    public function merchant_negotiation($apply_id)
    {
        if(empty($apply_id)){
            die('无效操作！');
        }
        
        // 调用协商类获取数据，传入来源参数区分退款申请单
        $negotiateLib = kernel::single('ome_refund_negotiate');
        $result = $negotiateLib->getMerchantNegotiationData($apply_id, 'refund_apply');
    
        if($result['rsp'] == 'fail'){
            die($result['msg']);
        }
        // 设置页面数据 - 明确列出每个字段
        $data = $result['data'];
        
        // 退款申请单信息
        $this->pagedata['refund_info'] = $data['refund_info'];
        
        
        // 退款申请单ID
        $this->pagedata['apply_id'] = $data['refund_id'];
        
        // 已保存的协商数据（编辑模式）
        $this->pagedata['negotiate_data'] = $data['negotiate_data'];
        
        // 协商渲染数据（包含所有协商相关数据）
        $negotiation_data = $data['negotiation_data'];
        
        // 申请提示
        $this->pagedata['apply_tips'] = $negotiation_data['apply_tips'];
        
        // 建议原因列表
        $this->pagedata['reason_list'] = $negotiation_data['reason_list'];
        
        // 最大退款金额 - 平台返回的是分，需要转换成元
        $max_refund_fee = $negotiation_data['max_refund_fee'];
        if (isset($max_refund_fee['max_refund_fee']) && is_numeric($max_refund_fee['max_refund_fee'])) {
            $max_refund_fee['max_refund_fee'] = number_format($max_refund_fee['max_refund_fee'] / 100, 2, '.', '');
        }
        $this->pagedata['max_refund_fee'] = $max_refund_fee;
        
        // 收货地址列表
        $this->pagedata['address_list'] = $negotiation_data['address_list'];
        
        // 协商类型代码（用于默认选中）
        $this->pagedata['negotiate_type_code'] = $negotiation_data['negotiate_type']['negotiate_code'];
        
        // 协商类型列表（所有可选类型）
        $this->pagedata['negotiate_types'] = $negotiation_data['negotiate_types'];
        
        // 推荐协商话术
        $this->pagedata['negotiate_text'] = $negotiation_data['negotiate_type']['negotiate_text'];
        
        // 拒绝原因列表
        $this->pagedata['refuse_reason_list'] = $negotiation_data['refuse_reason_list'];
        
        // 退款类型选项列表
        $this->pagedata['refund_type_options'] = $negotiation_data['refund_type_options'] ?: array();
        
        // 退款版本号
        $this->pagedata['refund_version'] = $negotiation_data['refund_version'] ?: '';
        
        $this->display("admin/refund/merchant_negotiation.html");
    }
    
    /**
     * 处理商家协商提交（退款申请单）
     */
    public function save_merchant_negotiation()
    {
        $apply_id = $_POST['apply_id'];
        $post_data = $_POST;
        // 调用协商类处理数据，传入来源参数区分退款申请单
        $negotiateLib = kernel::single('ome_refund_negotiate');
        $result = $negotiateLib->processMerchantNegotiation($apply_id, $post_data, 'refund_apply');
        
        // 直接输出JSON响应
        echo json_encode($result);
        exit;
    }
}
