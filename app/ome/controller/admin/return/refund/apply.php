<?php
class ome_ctl_admin_return_refund_apply extends desktop_controller {
    var $name = "售后退款单";
    var $workground = "aftersale_center";

    function index(){
       switch ($_GET['status']){
           case '0':
               $base_filter = array('status'=>'0'); //未处理
               break;
           case '1':
               $base_filter = array('status'=>'1'); //审核中
               break;
           case '2':
               $base_filter = array('status'=>'2'); //已接受申请
               break;
           case '3':
               $base_filter = array('status'=>'3'); //已拒绝
               break;
           case '4':
               $base_filter = array('status'=>'4'); //已退款
               break;
           case '5':
               $base_filter = array('status'=>'5'); //退款中
               break;
           case '6':
               $base_filter = array('status'=>'6'); //退款失败
               break;
           default:
               //全部
       }

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }

        $params = array(
            'title' => '售后退款申请单',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'base_filter' => $base_filter,
            'actions' =>array(
                  array(
                    'label' => '新建售后退款申请单',
                    'href' => 'index.php?app=ome&ctl=admin_return_refund_apply&act=add_refund',
                  )
                ),
            );

        # 权限判定
        if(!$this->user->is_super()){
            $returnLib = kernel::single('ome_return');
           foreach ($params['actions'] as $key=>$action) {
               $url = parse_url($action['href']);
               parse_str($url['query'],$url_params);
                $has_permission = $returnLib->chkground($this->workground,$url_params);
                if (!$has_permission) {
                    unset($params['actions'][$key]);
                }
           }
        }

        $params ['base_filter'] ['refund_refer'] = '1';
        $this->finder ( 'ome_mdl_refund_apply' , $params );
    }

    function _views(){
        $mdl_refund_apply = $this->app->model('refund_apply');
        $sub_menu = array(
            0 => array('label'=>__('全部'),'filter'=>array('refund_refer'=>'1'),'optional'=>false),
            1 => array('label'=>__('未处理'),'filter'=>array('status'=>'0','refund_refer'=>'1'),'optional'=>false),
            2 => array('label'=>__('已接受申请'),'filter'=>array('status'=>'2','refund_refer'=>'1'),'optional'=>false),
            3 => array('label'=>__('已拒绝'),'filter'=>array('status'=>'3','refund_refer'=>'1'),'optional'=>false),
            4 => array('label'=>__('退款中'),'filter'=>array('status'=>'5','refund_refer'=>'1'),'optional'=>false),
            5 => array('label'=>__('退款失败'),'filter'=>array('status'=>'6','refund_refer'=>'1'),'optional'=>false),
            6 => array('label'=>__('卖家拒绝退款'), 'filter'=>array('source_status'=>'SELLER_REFUSE_BUYER'), 'optional'=>false),
        );

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();

        $i=0;
        foreach($sub_menu as $k=>$v){
            if($organization_permissions){
                $v['filter']['org_id'] = $organization_permissions;
            }

            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $mdl_refund_apply->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl=admin_return_refund_apply&act=index&view='.$i++;
        }
        return $sub_menu;
    }

    function add_refund(){
        $this->pagedata['order_filter'] = array('pay_status'=>'1','ship_status'=>'1');
        $this->pagedata['is_edit'] = 'true';
        $source = trim($_GET['source']);
        $this->pagedata['source'] = $source;
        $this->pagedata['reason'] = app::get('ome')->model('refund_reason')->getList('reason',[]);
        $this->display('admin/return_product/refund/refund.html');
    }

    //获取订单信息
    function ajax_getOrderinfo(){
        $orderid = $_POST['order_id'];
        if(!$orderid){
            echo app::get('base')->_('订单号传递出错');
            return false;
        }
        //判断是否为失败订单
        $api_failObj = $this->app->model('api_fail');
        $api_fail = $api_failObj->dump(array('order_id'=>$orderid,'type'=>'payment'));
        if ($api_fail){
            $api_fail_flag = 'true';
        }else{
            $api_fail_flag = 'false';
        }
        $this->pagedata['api_fail_flag'] = $api_fail_flag;
        $this->pagedata['orderid'] = $orderid;
        $source = $_GET['source'];
        if ($source && in_array($source,array('archive'))) {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $aORet = $archive_ordObj->getOrders(array('order_id'=>$orderid),'*');
             $items = $archive_ordObj->getItemList($orderid);
        }else{
            $objOrder = $this->app->model('orders');
            $aORet = $objOrder->order_detail($orderid);
            $items = $objOrder->getItemList($orderid);
        }
        $aORet['cur_name'] = 'CNY';
        $aORet['cur_sign'] = 'CNY';
        $oPayment = $this->app->model('payments');
        $payment_cfgObj = app::get('ome')->model('payment_cfg');
        $oShop = $this->app->model('shop');
        $c2c_shop = ome_shop_type::shop_list();
        $shop_id = $aORet['shop_id'];
        $shop_detail = $oShop->dump($shop_id,'node_type,node_id');
        if ($shop_id && !in_array($shop_detail['node_type'], $c2c_shop)){
            $payment = kernel::single('ome_payment_type')->paymethod($shop_id);
        }else{
            $payment = $oPayment->getMethods();
        }
        $payment_cfg = $payment_cfgObj->dump(array('pay_bn'=>$aORet['pay_bn']), 'id,pay_type');
        $this->pagedata['shop_id'] = $shop_id;
        $this->pagedata['node_id'] = $shop_detail['node_id'];
        $this->pagedata['payment'] = $payment;
        $this->pagedata['payment_id'] = $payment_cfg['id'];
        $this->pagedata['pay_type'] = $payment_cfg['pay_type'];
        if ($payment_cfg['id']){
            $order_paymentcfg = kernel::single('ome_payment_type')->paymethod($shop_id,$payment_cfg['pay_type']);
        }
        $this->pagedata['order_paymentcfg'] = $order_paymentcfg;
        $this->pagedata['op_name'] = 'admin';
        $this->pagedata['typeList'] = ome_payment_type::pay_type();
        if($aORet['member_id'] > 0){
            $objMember = $this->app->model('members');
            $aRet = $objMember->member_detail($aORet['member_id']);
            $this->pagedata['member'] = $aRet;
        }else{
            $this->pagedata['member'] = array();
        }
        $this->pagedata['order'] = $aORet;

        $aRet = $oPayment->getAccount();
        $aAccount = array('--使用已存在帐户--');
        foreach ($aRet as $v){
            $aAccount[$v['bank']."-".$v['account']] = $v['bank']." - ".$v['account'];
        }
        $this->pagedata['pay_account'] = $aAccount;
        //剩余支付金额
        $pay_money = kernel::single('eccommon_math')->number_minus(array($aORet['total_amount'],$aORet['payed']));
        $this->pagedata['pay_money'] = $pay_money;
        $this->pagedata['aItems'] = $items;
        $this->pagedata['reason'] = app::get('ome')->model('refund_reason')->getList('reason',[]);
        $this->display('admin/return_product/refund/detail.html');
    }

    //保存退款申请
    function apply_refund(){
       $post = kernel::single('base_component_request')->get_params(true);
       $source = $_POST['source'] ? $_POST['source'] : 'local';

       // 归档订单从弹窗提交：splash 不带跳转，避免把归档列表换成售后退款页
       if ($source === 'archive') {
           $this->begin('');
       } else {
           $this->begin('index.php?app=ome&ctl=admin_return_refund_apply&act=index');
       }

       //退款申请来源(0:普通流程产生的退款申请 1:通过售后流程产生的退款申请)
       $post['refund_refer']    = '1';
       $post['source']          = $source;
       $is_update_order         = false;//是否更新订单付款状态
       $return = kernel::single('ome_refund_apply')->createRefundApply($post, $is_update_order, $error_msg);
       if(!$return)
       {
           $this->end(false, $error_msg);
       }

       $msg = (is_array($return) && !empty($return['msg'])) ? $return['msg'] : '申请退款成功';
       $this->end(true, $msg);
    }

    //$apply_id 退款申请单号,$refund_refer 退款申请来源 0:普通退款申请 1:通过售后流程产生的退款申请
    function accept($apply_id,$refund_refer = '0'){
        //显示退款确认form
        if(!$apply_id){
            echo app::get('base')->_('退款申请号传递出错');
            return false;
        }
       $oRefaccept = $this->app->model('refund_apply');
       $oOrder = $this->app->model('orders');
       $deoObj = app::get('ome')->model('delivery_order');
       $finder_id = $_GET['finder_id'];
       if ($_POST){
            $this->begin("index.php?ctl=admin_return_refund_apply&act=accept&app=ome&p[0]=".$apply_id);

            $oRefund = $this->app->model('refunds');
            $oLoger = $this->app->model('operation_log');
            $objShop = $this->app->model('shop');
            //只有已经接受申请的才能确认。
            $apply_detail = $oRefaccept->refund_apply_detail($apply_id);
            if (in_array($apply_detail['status'],array('2','6'))){
                $order_id = $apply_detail['order_id'];
                $order_detail = $oOrder->order_detail($order_id);
                $ids = $deoObj->getList('delivery_id',array('order_id'=>$order_id));
                //如果申请金额大于已付款金额，则报错、退出
                if (round($apply_detail['money'],3)>round(($order_detail['payed']),3)){
                    $this->end(false, '退款申请金额大于订单上的余额！');
                }
                $shop_detail = $objShop->dump($order_detail['shop_id'], 'node_id,node_type');
                $c2c_shop = ome_shop_type::shop_list();
                $refund_request = false;
                if ($_POST['api_fail_flag'] == 'false'){
                    if ($shop_detail['node_id'] && !in_array($shop_detail['node_type'],$c2c_shop)){
                        $refund_request = true;
                    }
                }else{
                    if ($_POST['api_refund_request'] == 'true'){
                        $refund_request = true;
                    }
                }
                //退款金额为零将不发起前端同步
                if ($apply_detail['money'] <= 0){
                    //$refund_request = false;
                }
                // 退款中不再发起同步请求
                if ($apply_detail['pay_status'] == '7'){
                    $refund_request = false;
                }
                //发起前端退款请求
                if ($refund_request == true){
                    /*if (!$_POST['payment']){
                        $this->end(false, app::get('base')->_('请选择支付方式。'));
                    }*/
                    if (!$_POST['pay_type']){
                        $this->end(false, app::get('base')->_('请选择付款类型。'));
                    }
                    $_POST['order_id'] = $order_id;
                    $_POST['apply_id'] = $apply_id;
                    $_POST['refund_bn'] = $apply_detail['refund_apply_bn'];
                    if ($oRefund->refund_request($_POST)){
                        $result = true;
                        $msg = '退款请求发起成功';
                    }else{
                        $result = false;
                        $msg = '退款请求发起失败,请重试';

                    }
                    $this->end($result, app::get('base')->_($msg), 'index.php?app=ome&ctl=admin_refund_apply&act=index');
                }else{
                    //查找本申请是否是与售后相关的，如果相关，则检查并回写数据
                    $oretrun_refund_apply = $this->app->model('return_refund_apply');
                    $return_refund_appinfo = $oretrun_refund_apply->dump(array('refund_apply_id'=>$apply_id));
                    if ($return_refund_appinfo['return_id']){
                        $oreturn = $this->app->model('return_product');
                        $return_info = $oreturn->product_detail($return_refund_appinfo['return_id']);
                        if (($return_info['refundmoney']+$apply_detail['money'])>$return_info['tmoney']){
                            $this->end(false, '申请退款金额大于售后的退款金额！');
                        }
                        $return_info['refundmoney'] = $return_info['refundmoney']+$apply_detail['money'];
                        $return_info['status'] = '4';
                        $oreturn->save($return_info);
                        $oLoger->write_log('return@ome',$return_info['return_id'],"售后退款成功。");
                    }
                    //订单信息更新
                    $orderdata = array();
                    if (round($apply_detail['money'],3)== round(($order_detail['payed']),3)){
                        $orderdata['pay_status'] = 5;
                        //2011.12.13删除屏蔽
                        //将原来的全额退款的 未发货的订单取消 封装成一个方法check_iscancel
                        //$oRefaccept->check_iscancel($apply_detail['order_id'],$apply_detail['memo']); 下面更新订单状态的时候也会释放掉冻结库存
                    }else{
                        $orderdata['pay_status'] = 4;
//                        //部分退款时打回未发货的发货单
//                        $oOrder->rebackDelivery($ids,'',true, false);
                    }
                    $orderdata['order_id'] =  $apply_detail['order_id'];
                    $orderdata['payed'] = $order_detail['payed'] - $apply_detail['money'];
                    $oOrder->save($orderdata);
                    $oLoger->write_log('order_modify@ome',$orderdata['order_id'],"售后退款成功，更新订单退款金额。");
                    //退款申请状态更新
                    $applydata = array();
                    $applydata['apply_id'] = $apply_id;
                    $applydata['status'] = 4;//已经退款
                    $applydata['refunded'] = $apply_detail['money'];// + $order_detail['payinfo']['cost_payment'];
                    $applydata['last_modified'] = time();
                    $applydata['account'] = $_POST['account'];
                    $applydata['pay_account'] = $_POST['pay_account'];
                    $oRefaccept->save($applydata,true);
                    $oLoger->write_log('refund_apply@ome',$applydata['apply_id'],"售后退款成功，更新退款申请状态。");
                    //更新售后退款金额
                    $return_id = intval($_POST['return_id']);
                    $sql = "UPDATE `sdb_ome_return_product` SET `refundmoney`=IFNULL(`refundmoney`,0)+{$apply_detail['money']} WHERE `return_id`='".$return_id."'";
                    kernel::database()->exec($sql);
                    //单据生成：生成退款单
                    $refunddata = array();
                    $refund_apply_bn = $apply_detail['refund_apply_bn'];
                    if ($refund_apply_bn){
                        $refund_bn = $refund_apply_bn;
                    }else{
                        $refund_bn = $oRefund->gen_id();
                    }
                    $refunddata['refund_bn'] = $refund_bn;
                    $refunddata['order_id'] = $apply_detail['order_id'];
                    $refunddata['shop_id'] = $order_detail['shop_id'];
                    $refunddata['account'] = $_POST['account'];
                    $refunddata['bank'] = $_POST['bank'];
                    $refunddata['pay_account'] = $apply_detail['pay_account'];
                    $refunddata['currency'] = $order_detail['currency'];
                    $refunddata['money'] = $apply_detail['money'];
                    $refunddata['paycost'] = 0;//没有第三方费用
                    $refunddata['cur_money'] = $apply_detail['money'];//汇率计算 TODO:应该为汇率后的金额，暂时是人民币金额
                    $refunddata['pay_type'] = $_POST['pay_type'];
                    $refunddata['payment'] = $_POST['payment'];
                    $paymethods = ome_payment_type::pay_type();
                    $refunddata['paymethod'] = $paymethods[$refunddata['pay_type']];
                    //Todo ：确认paymethod
                    $opInfo = kernel::single('ome_func')->getDesktopUser();
                    $refunddata['op_id'] = $opInfo['op_id'];
                    $refunddata['t_ready'] = time();
                    $refunddata['t_sent'] = time();
                    $refunddata['status'] = "succ";#支付状态
                    $refunddata['memo'] = $apply_detail['memo'];
                    $refunddata['trade_no'] = $_POST['trade_no'];
                    $refunddata['refund_refer'] = $refund_refer;
                    $oRefund->save($refunddata);
                    //更新订单支付状态
                    kernel::single('ome_order_func')->update_order_pay_status($apply_detail['order_id'], true, __CLASS__.'::'.__FUNCTION__);
                    $oLoger->write_log('refund_accept@ome',$refunddata['refund_id'],"售后退款成功，生成退款单".$refunddata['refund_bn']);
                    $this->end(true, '售后申请退款成功', 'index.php?app=ome&ctl=admin_refund_apply&act=index');
                }
            }
       }else{
           //退款请求失败标识
           $refunds = $oRefaccept->refund_apply_detail($apply_id);
           $this->pagedata['refund'] = $refunds;
           if ($refunds['status'] == '6'){//退款失败
               $api_fail_flag = 'true';
           }else{
               $api_fail_flag = 'false';
           }
           $this->pagedata['api_fail_flag'] = $api_fail_flag;
           $order_detail = $oOrder->order_detail($this->pagedata['refund']['order_id']);
           $this->pagedata['order'] = $order_detail;
           $oPayment = $this->app->model('payments');
           //前端店铺支付方式
           $payment_cfgObj = $this->app->model('payment_cfg');
           $oShop = $this->app->model('shop');
           $c2c_shop = ome_shop_type::shop_list();
           $shop_id = $order_detail['shop_id'];
           $shop_detail = $oShop->dump($shop_id,'node_type,node_id');
           if ($shop_id){
               $payment = kernel::single('ome_payment_type')->paymethod($shop_id);
           }else{
               $payment = $oPayment->getMethods();
           }
           $payment_cfg = $payment_cfgObj->dump(array('pay_bn'=>$order_detail['pay_bn']), 'id,pay_type');
           $this->pagedata['shop_id'] = $shop_id;
           $this->pagedata['node_id'] = $shop_detail['node_id'];
           $this->pagedata['payment'] = $payment;
           $this->pagedata['pay_type'] = $payment_cfg['pay_type'];
           if ($payment_cfg['id']){
               $order_paymentcfg = kernel::single('ome_payment_type')->paymethod($shop_id,$payment_cfg['pay_type']);
           }
           $this->pagedata['order_paymentcfg'] = $order_paymentcfg;
           $this->pagedata['payment_id'] = $payment_cfg['id'];
           $this->pagedata['typeList'] = ome_payment_type::pay_type();
           $this->pagedata['pay_type'] = $this->pagedata['pay_type'];
           $aRet = $oPayment->getAccount();
           $aAccount = array('--使用已存在帐户--');
            foreach ($aRet as $v){
                $aAccount[$v['bank']."-".$v['account']] = $v['bank']."-".$v['account'];
            }
           $addon = unserialize($refunds['addon']);
           $this->pagedata['return_id'] = $addon['return_id'];
           $this->pagedata['pay_status'] = kernel::single('ome_order_status')->pay_status();
           $this->pagedata['finder_id'] = $finder_id;
           $this->pagedata['pay_account'] = $aAccount;
           $memberid = $this->pagedata['order']['member_id'];
           $oMember = $this->app->model('members');
           $this->pagedata['member'] = $oMember->member_detail($memberid);
           $this->display('admin/refund/refund_accept.html');
       }
    }

    /**
     * @description 获取可允许退款的订单
     * @author chenping<chenping@shopex.cn>
     * @access public
     * @param String $order_bn
     */
    public function getRefundOrder(){
        $order_bn = trim($_GET['order_bn']);
        $source = trim($_GET['source']);
        if ($order_bn){
            //已支付部分退款
            $base_filter = array('order_bn'=>$order_bn,'disabled'=>'false','is_fail'=>'false','ship_status'=>array('1','3','4'),'pay_status'=>array('1','4'));
            $order = $this->app->model('orders');
            $is_archive = kernel::single('archive_order')->is_archive($source);
            if ($is_archive) {
                $archive_ordObj = kernel::single('archive_interface_orders');
                $data = $archive_ordObj->getOrder_list($base_filter,'order_id,order_bn');
            }else{
                $data = $order->getList('order_id,order_bn',$base_filter);
            }
            echo "window.autocompleter_json=".json_encode($data);
        }
    }

    /**
     * @description 获取可允许退款订单列表
     * @author chenping<chenping@shopex.cn>
     * @access public
     */
    public function getRefundOrderFinder(){
        $op_id = $this->user->get_id();
        $this->title = '订单查看';
        $source = $_GET['source'];
        if ($source && in_array($source,array('archive'))) {
            $base_filter = array('is_fail'=>'false','ship_status'=>array('1','3','4'),'pay_status'=>array('1','4'));
            $params = array(
                'title'=>$this->title,
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'=>false,
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_filter'=>true,
                'use_view_tab'=>false,
                'finder_aliasname' => 'order_view'.$op_id,
                'finder_cols' => 'order_bn,shop_id,total_amount,process_status,is_cod,pay_status,ship_status,payment,shipping,logi_id,logi_no,createtime,paytime,mark_type',
                'orderBy' => 'order_id',
                'orderType' => 'desc',
                'base_filter' => $base_filter,
           );
           $this->finder('archive_mdl_orders',$params);
        }else{
            $base_filter = array('disabled'=>'false','is_fail'=>'false','ship_status'=>array('1','3','4'),'pay_status'=>array('1','4'));
            $params = array(
                'title'=>$this->title,
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'=>false,
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_filter'=>true,
                'use_view_tab'=>false,
                'finder_aliasname' => 'order_view'.$op_id,
                'finder_cols' => 'order_bn,shop_id,total_amount,column_print_status,process_status,is_cod,pay_status,ship_status,payment,shipping,logi_id,logi_no,createtime,paytime,mark_type',
                'orderBy' => 'order_id',
                'orderType' => 'desc',
                'base_filter' => $base_filter,
           );
           $this->finder('ome_mdl_orders',$params);
        }
    }

}
