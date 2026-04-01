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
 * 退款申请类
 * @access public
 * @copyright www.shopex.cn 2010.12.1
 * @author ome
 */
class ome_refund_apply
{
    
    /**
     * 显示退款申请页面
     * @access public
     * @param int $order_id 订单ID
     * @param decimal 退款金额
     * @param $addon 附加属性，用于特殊情况处理
     * @param int $return_id 退款ID
     * @return html
     */
    public function show_refund_html($order_id,$return_id='0',$refund_money='0',$addon=null){
        $render = app::get('ome')->render();
        $msg = array('result'=>true, 'msg'=>'');
        if(!$order_id){
            $msg['result'] = false;
            $msg['msg'] = '订单号传递出错';
            return $msg;
        }
        $finder_id = $_GET['finder_id'];
        $orefapply = app::get('ome')->model('orders');
        $oRefund = app::get('ome')->model('refund_apply');
        $refunddata = $oRefund->getList('*',array('order_id'=>$order_id),0,-1);
        $amount = 0;
        foreach ($refunddata as $row){
            if ($row['status'] != 3 && $row['status'] != 4){
                $msg['result'] = false;
                $msg['msg'] = '上次申请未处理完成，请完成上次处理';
                return $msg;
            }
        }
       $render->pagedata['order'] = $orefapply->order_detail($order_id);
       if ($render->pagedata['order']['pay_status'] == '5'){
           $msg['result'] = false;
           $msg['msg'] = '订单已全额退款，无需再处理';
           return $msg;
       }
       $payment_cfgObj = app::get('ome')->model('payment_cfg');
       $oPayment = app::get('ome')->model('payments');
       $oShop = app::get('ome')->model('shop');
       $shop_id = $render->pagedata['order']['shop_id'];
       $shop_detail = $oShop->dump($shop_id,'node_type,node_id');
       if ($shop_id){
           $payment = kernel::single('ome_payment_type')->paymethod($shop_id);
       }else{
           $payment = $oPayment->getMethods();
       }
       $payment_cfg = $payment_cfgObj->dump(array('pay_bn'=>$render->pagedata['order']['pay_bn']), 'id,pay_type');
       $render->pagedata['shop_id'] = $shop_id;
       $render->pagedata['node_id'] = $shop_detail['node_id'];
       $render->pagedata['payment'] = $payment;
       $render->pagedata['payment_id'] = $payment_cfg['id'];
       $render->pagedata['pay_type'] = $payment_cfg['pay_type'];
       if ($payment_cfg['id']){
           $order_paymentcfg = kernel::single('ome_payment_type')->paymethod($shop_id,$payment_cfg['pay_type']);
       }
       $render->pagedata['order_paymentcfg'] = $order_paymentcfg;
       $render->pagedata['typeList'] = ome_payment_type::pay_type();
       $aRet = $oPayment->getAccount();
       $aAccount = array('--使用已存在帐户--');
        foreach ($aRet as $v){
            $aAccount[$v['bank']."-".$v['account']] = $v['bank']."-".$v['account'];
        }
       $render->pagedata['pay_account'] = $aAccount;
       
       $paymentInfo = $oPayment->dump(array('order_id'=>$order_id));
       $render->pagedata['account'] = $paymentInfo['account'];
       $render->pagedata['payment_bank'] = $paymentInfo['bank'];
       $render->pagedata['payment_type'] = $paymentInfo['pay_type'];
       $render->pagedata['payment_account'] = $paymentInfo['pay_account'];
       $render->pagedata['reason'] = app::get('ome')->model('refund_reason')->getList('reason',[]);
       
       $render->pagedata['is_c2cshop'] = in_array($shop_detail['node_type'],ome_shop_type::shop_list()) ?true:false;
       $render->pagedata['shop_name'] = ome_shop_type::shop_name($shop_detail['node_type']);

       if (!$refund_money){
           if ($return_id)
            {
                //处理售后的退款
                $render->pagedata['return_id'] = $return_id;
                $oReturn = app::get('ome')->model('return_product');
                $return_detail = $oReturn->product_detail($return_id);
                if ($return_detail['tmoney'] > 0)
                {
                    $render->pagedata['refund_money'] = $return_detail['tmoney'];
                }
                else
                {
                    $render->pagedata['refund_money'] = 0;
                }
            }
            else
            {
                $render->pagedata['refund_money'] = $render->pagedata['order']['payed'];
            }
       }else{
           $render->pagedata['refund_money'] = $refund_money;
       }
       $render->pagedata['finder_id'] = $finder_id;
       $memberid = $render->pagedata['order']['member_id'];
       $oMember = &$render->app->model('members');
       $render->pagedata['member'] = $oMember->member_detail($memberid);

       $objects = $orefapply->order_objects($order_id);
        foreach ($objects as $key => $val) {
            if ($val['delete'] == 'true') {
                unset($objects[$key]);
                continue;
            }
        }
       switch ($addon['from']){
           case 'order_edit'://订单编辑
               $render->pagedata['ctl'] = 'admin_order';
               $render->pagedata['act'] = 'do_refund';
               $render->pagedata['addon'] = $addon;
               break;
           case 'remain_order_cancel'://余单撤消
               $render->pagedata['ctl'] = 'admin_order';
               $render->pagedata['act'] = 'remain_order_cancel_refund';
               $render->pagedata['addon'] = $addon;
               $diff_price = kernel::single('ome_order_func')->order_items_diff_money($order_id);
               $render->pagedata['diff_price'] = $diff_price;
               $render->pagedata['remain_cancel_flag'] = 'true';
               break;
           default:
               $render->pagedata['ctl'] = 'admin_refund_apply';
               $render->pagedata['act'] = 'showRefund';
       }
        $render->pagedata['aItems'] = $objects;
        $render->pagedata['pay_status'] = kernel::single('ome_order_status')->pay_status();
       return $render->display('admin/refund/refund_apply.html');
    }
    
    /**
     * !!!废弃,已不在使用!!!
     * 现使用：kernel::single('ome_refund_apply')->createRefundApply($sdf, $is_update_order, $error_msg);
     * 
     * 
     * 添加退款申请
     * @access public
     * @param $data $data POST提交数据  $refund_refer 退款申请来源 0:普通流程产生的退款申请 1:通过售后流程产生的退款申请
     * @return 退款申请成功与失败状态及消息
     */
    public function refund_apply_add($data,$refund_refer='0'){
        $mathLib = kernel::single('eccommon_math');
        if($data){
            
            if(empty($data['pay_type']))
            {
                $msg['result'] = false;
                $msg['msg'] = '请选择退款类型';
                return $msg;
            }
            /*if(empty($data['payment']))
            {
                $msg['result'] = false;
                $msg['msg'] = '请选择退款支付方式';
                return $msg;
            }*/
            
            if( $data['refund_money'] <= 0)
            {
                $msg['result'] = false;
                $msg['msg'] = '退款金额必须大于0';
                return $msg;
            }
            
            $objOrder = app::get('ome')->model('orders');
            $refundapp = app::get('ome')->model('refund_apply');
            $oOrderItems = app::get('ome')->model('order_items');
            $oLoger = app::get('ome')->model('operation_log');
            $oShop = app::get('ome')->model ( 'shop' );
            $bcmoney = $mathLib->getOperationNumber($data['bcmoney']);//补偿费用
            $countPrice=0;
            $countPrice=$data['refund_money'];
            $totalPrice=0;
            $totalPrice=$countPrice+$bcmoney;
            $refund_apply_bn = $refundapp->gen_id();
            if ($data['source'] &&  in_array($data['source'],array('archive'))) {
                $archive_ordObj = kernel::single('archive_interface_orders');
                $source = $data['source'];
                $orderdata = $archive_ordObj->getOrders(array('order_id'=>$data['order_id']),'*');
            }else{
                $objOrder = app::get('ome')->model('orders');
                $orderdata = $objOrder->order_detail($data['order_id']);
            }
           
            $data=array(
                 'return_id'=>$data['return_id'],
                 'refund_apply_bn'=>$refund_apply_bn,
                 'order_id'=>$data['order_id'],
                 'shop_id'=>$orderdata['shop_id'],
                 'pay_type'=>$data['pay_type'],
                 'bank'=>$data['bank'],
                 'account'=>$data['account'],
                 'pay_account'=>$data['pay_account'],
                 'money'=>$totalPrice,
                'bcmoney'=>$bcmoney,
                 'apply_op_id'=>kernel::single('desktop_user')->get_id(),
                 'payment'=>is_numeric($data['payment'])?$data['payment']:null,
                 'memo'=>$data['memo'],
                 'verify_op_id' =>kernel::single('desktop_user')->get_id(),
                 'addon' => serialize(array('return_id'=>$data['return_id'])),
                 'refund_refer' => $refund_refer,
                 'cost_freight' =>$data['cost_freight'] ? $data['cost_freight'] : 0, 
            );
            if ($source && in_array($source,array('archive'))) {
                $data['source'] = 'archive';
                $data['archive'] = 1;
            }
            // $shop_type = $oShop->getShoptype($orderdata['shop_id']);
            $shop_info = $oShop->getShopInfo($orderdata['shop_id']);
            $shop_type = $shop_info['shop_type'];
            $data['shop_type'] = $shop_type;
            $msg = array('result'=>true, 'msg'=>'申请退款成功,单据号为:'.$refund_apply_bn);
            if(round($countPrice,3)>round(($orderdata['payed']),3))
            {
                $msg['result'] = false;
                $msg['msg'] = '退款申请金额大于订单上的余额';
                return $msg;
            }
            //余单撤销退款金额判断
            if ($data['remain_cancel_flag'] == 'true' && $countPrice > $data['diff_price']){
                $msg['result'] = false;
                $msg['msg'] = '退款申请金额大于余单撤销金额';
                return $msg;
            }
            
            $data['create_time'] = time();

            // 经销店铺的单据，delivery_mode冗余到退款单申请表
            if ($shop_info['delivery_mode'] == 'jingxiao') {
                $data['delivery_mode'] = $shop_info['delivery_mode'];
            }
            
            if($refundapp->save($data))
            {   //将订单更改为退款申请中

                 kernel::single('ome_order_func')->update_order_pay_status($data['order_id'], true, __CLASS__.'::'.__FUNCTION__);
                 /*if ($data['return_id'])
                 {
                     //插入return_refund_apply
                     $oreturn_refund_apply = app::get('ome')->model('return_refund_apply');
                     $return_ref_data = array('refund_apply_id'=>$data['apply_id'],'return_id'=>$data['return_id']);
                     $oreturn_refund_apply->save($return_ref_data);
                 }*/
                 $oLoger->write_log('refund_apply@ome',$data['apply_id'],'申请退款成功');
                 return $msg;
            }
        }
    }
    

    
    /**
     * 批量处理售后申请单
     * @param   array apply_id
     * @return  
     * @access  public
     * @author cyyr24@sina.cn
     */
    function batch_update($status_type,$apply_id){
        set_time_limit(0);
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $oLoger = app::get('ome')->model('operation_log');
        $oReturn_batch = app::get('ome')->model('return_batch');
        
        $error_msg = array();
        $need_apply_id = array();

        foreach ($apply_id as $apply_id ) {
            $apply_id = explode('||',$apply_id);
            $need_apply_id[] = $apply_id[1];
        }
        $apply_list = $oRefund_apply->db->select('SELECT status,source,shop_type,apply_id,refund_apply_bn,shop_id,order_id,return_id,`oid` FROM sdb_ome_refund_apply WHERE apply_id in('.implode(',',$need_apply_id).')');
        
        $fail = 0;$succ=0;
        if ($status_type == 'agree') {
            $batchList = $this->return_batch('accept_refund');
            foreach ( $apply_list as $apply ) {#同意退款目前只有天猫需要回写
                $apply_id = $apply['apply_id'];
                $status = $apply['status'];
                if (in_array( $status,array('0','1'))){
                    if ($apply['source'] == 'matrix') {
                        $return_batch = $batchList[$apply['shop_id']];
                        $refund = array(
                            'apply_id'=>$apply['apply_id'],
                            'refuse_message'=>$return_batch['memo'],
                            'oid'   => $apply['oid'],
                        );
                        $rs = kernel::single('ome_service_refund_apply')->update_status($refund,2,'sync');
                    }
                    
                    if ($rs && $rs['rsp'] == 'fail') {
                        $fail++;
                        $error_msg[] = '单号:'.$apply['refund_apply_bn'].",".$rs['msg'];
                        
                    }else{
                        #更新退款单接受状态
                        $this->update_refund_applyStatus('2',$apply);
                    }
                }
            }
            
        }elseif($status_type == 'refuse') {
            $batchList = $this->return_batch('refuse');
            
            //拒绝退款是否请求平台(birken勃肯客户搬物料代码功能)
            $refuseRequest = app::get('ome')->getConf("ome.refund.refuse.request");
            
            //淘宝拒绝天猫需上传凭证
            foreach ( $apply_list as $apply ) {
                $status = $apply['status'];
                $apply_id = $apply['apply_id'];
                if ( in_array( $status , array( '0','1','2' ) )) {
                    $rs = array();
                    if($refuseRequest != 'false' &&  $apply['source'] == 'matrix'){
                        $return_batch = $batchList[$apply['shop_id']];
                        $refund = array(
                            'apply_id'=>$apply_id,
                            'refuse_message'=>$return_batch['memo'],
                            'oid'   => $apply['oid'],
                        );
                        $picurl = $return_batch['picurl'];
                        if ($apply['shop_type'] == 'tmall') {
                            $picurl = file_get_contents($picurl);
                            $picurl = base64_encode($picurl);
                            
                        }
                        $refund['refuse_proof'] = $picurl;
                        
                        //[抖音]不支持售前拒绝退款
                        if($apply['shop_type'] == 'luban'){
                            $params = $apply;
                            $params['status'] = '3';
                            
                            $rs = kernel::single('ome_aftersale_service')->pre_save_refund($apply_id, $params);
                        }else{
                            $rs = kernel::single('ome_service_refund_apply')->update_status($refund,3,'sync');
                        }
                        
                    }
                    
                    if ( ($rs && $rs['rsp'] == 'fail') ) {
                        $fail++;
                        $error_msg[] = '单号:'.$apply['refund_apply_bn'].",".$rs['msg'];
                        
                    }else{
                        $this->update_refund_applyStatus('3',$apply);                       
                    }
                }
            }
        }
        $result = array('error_msg'=>$error_msg,'fail'=>$fail);
        return $result;
    }

    
    /**
     * 退款单列表.
     * @param   type    $varname    description
     * @return  type    description
     * @access  public
     * @author cyyr24@sina.cn
     */
    function refund_list($status_type,$apply_id)
    {
        $oRefund_apply = app::get('ome')->model('refund_apply');
        if ($status_type == 'agree') {
            $apply_list = $oRefund_apply->db->select('SELECT apply_id FROM sdb_ome_refund_apply WHERE apply_id in("'.implode('","',$apply_id).'") AND `status` in (\'0\',\'1\')');
        }else{
            $apply_list = $oRefund_apply->db->select('SELECT apply_id FROM sdb_ome_refund_apply WHERE apply_id in("'.implode('","',$apply_id).'") AND `status` in (\'0\',\'1\',\'2\')');
        }
        
        $apply_id_list = array();
        foreach ($apply_list as $apply ) {
            $apply_id_list[] = $apply['apply_id'];
        }
        return $apply_id_list;
    }
    
    /**
    * 售后默认设置
    */
    public function return_batch($batch_type){
        $oReturn_batch = app::get('ome')->model('return_batch');
        $batch = $oReturn_batch->getlist('*',array('is_default'=>'true','batch_type'=>$batch_type));
        $batchList = array();
        foreach ($batch as $item ) {
            $batchList[$item['shop_id']] = $item;
        }
        return $batchList;
    }

    
    /**
     * 更新退款申请单状态.
     * @param   status
     * @return  
     * @access  public
     * @author cyyr24@sina.cn
     */
    public function update_refund_applyStatus($status,$apply)
    {
        $oLoger = app::get('ome')->model('operation_log');
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $Oreturn_product = app::get('ome')->model('return_product');
        $apply_id = $apply['apply_id'];
        $order_id = $apply['order_id'];
        switch ($status) {
            case '2':#接受
                $refund_op = 'refund_pass@ome';
                $refund_op_name = '接受';
                break;
            case '3':#拒绝
                $refund_op = 'refund_refuse@ome';
                $refund_op_name = '拒绝';
                break;
            default:
                return true;
                break;
        }
        $data['apply_id'] = $apply_id;
        $data['status'] = $status;
        $data['last_modified'] = time();

        $oRefund_apply->save($data,true);
        $memo = "退款申请 $refund_op_name";
        if ($apply['oper_memo']) {
            $memo = $apply['oper_memo'];
        }
        $oLoger->write_log($refund_op,$apply_id,$memo);
        if ( $status == '3' ) {
            $return_id = $apply['return_id'];
            if ($return_id) {
                $return_data = array ('return_id' => $return_id, 'status' => '9', 'last_modified' => time () );
                $Oreturn_product->save ( $return_data );

                $oLoger->write_log('return@ome',$return_id,$apply['refund_apply_bn'].$memo);    
            }
            
            kernel::single('ome_order_func')->update_order_pay_status($order_id, true, __CLASS__.'::'.__FUNCTION__);
            //生成售后单
            kernel::single('sales_aftersale')->generate_aftersale($apply_id,'refund');
        }
    }

    /**
     * 识别当前退款申请是否需要请求接口进行退款
     * @param  $params  参数
     * @param  $err_msg  错误信息
     * @return  boolean  true/false
     * @access  public
     * @author xiayuanjun@shopex.cn
     */
    public function checkForRequest($params, &$err_msg)
    {
        $err_msg = '';
        $refund_request = false;
        $c2c_shop = ome_shop_type::shop_list();
        $orderInfo = $params['orderInfo']; //订单参数信息
        $applyInfo = $params['applyInfo']; //退款申请单参数信息
        $api_fail_flag = $params['api_fail_flag']; //是否存在请求接口失败，根据退款申请是否退款失败的状态标记
        $api_refund_request = $params['api_refund_request']; //表单上标记是否允许请求
        $is_archive = $params['is_archive']; //是否归档订单
        $is_aftersale_refund = ($applyInfo['return_id'] > 0) ? true : false; //识别售后退款还是售前退款

        //获取当前店铺相关信息
        $shopObj = app::get('ome')->model('shop');
        $shopInfo = $shopObj->dump($orderInfo['shop_id'], 'node_id,node_type,business_type,tbbusiness_type');
        
        if ($api_fail_flag == 'false'){
            //识别是否是已绑定的线上退款申请
            if ($shopInfo['node_id'] && $orderInfo['source'] == 'matrix'){
                //非C2C销售平台,这里主要指自有体系，不管线上线下产生的退款申请单
                if(!in_array($shopInfo['node_type'],$c2c_shop)){
                    if ($api_refund_request == 'true'){
                        $refund_request = true;
                    }else{
                        $err_msg = '向前端退款失败,仅本地退款!';
                    }
                }elseif($shopInfo['node_type'] == 'taobao' && $shopInfo['business_type'] == 'zx' && (strtolower($shopInfo['tbbusiness_type']) == 'b') && ($is_aftersale_refund || (!$is_aftersale_refund && $applyInfo['source'] == 'matrix'))){
                    if ($api_refund_request == 'true'){
                        //天猫店判断逻辑，如果是线上售前退款或者售后才允许发起
                        //售后退款的识别状态是否已退货入仓完成状态
                        if($is_aftersale_refund){
                            $returnPrdObj = app::get('ome')->model('return_product');
                            $return_info = $returnPrdObj->dump($applyInfo['return_id']);
                            $return_status = ($return_info['status'] == 4) ? true : false;

                            $refundExtMdl = 'return_product_tmall';
                            $refundExtFilter = array('return_id' => $applyInfo['return_id']);
                        }else{
                            $return_status = true;

                            $refundExtMdl = 'refund_apply_tmall';
                            $refundExtFilter = array('apply_id' => $applyInfo['apply_id']);
                        }

                        //获取原始退款金额
                        $refundOriginalObj = app::get('ome')->model($refundExtMdl);
                        $refundOriginalInfo = $refundOriginalObj->getList('refund_fee', $refundExtFilter , 0 , 1);
                        $original_refund_fee = $refundOriginalInfo[0]['refund_fee'];

                        //本次申请退款的金额
                        $money = $applyInfo['money'];

                        //当AG自动退款开启，原始退款金额存在并且实际退款等于原始申请退款金额，则允许接口请求自动退款
                        $aliag_status = app::get('ome')->getConf('shop.aliag.config.'.$orderInfo['shop_id']);
                        if($aliag_status && $return_status && $original_refund_fee && (round($original_refund_fee,3)== round($money,3))){
                            $refund_request = true;
                        }
                    }else{
                        $err_msg = '向前端退款失败,仅本地退款!';
                    }
                }elseif(in_array($shopInfo['node_type'], array ('360buy')) && ($is_aftersale_refund || (!$is_aftersale_refund && $applyInfo['source'] == 'matrix'))){
                    if ($api_refund_request == 'true'){
                        //当AG自动退款开启，原始退款金额存在并且实际退款等于原始申请退款金额，则允许接口请求自动退款
                        $aliag_status = app::get('ome')->getConf('shop.aliag.config.'.$orderInfo['shop_id']);
                        if($aliag_status){
                            $refund_request = true;
                        }
                    }else{
                        $err_msg = '向前端退款失败,仅本地退款!';
                    }
                }elseif(in_array($shopInfo['node_type'], ome_shop_type::shop_refund_list())){
                    if ($api_refund_request == 'true'){
                        $refund_request = true;
                    }
                }
            }
        }else{
            //请求失败过的如果还要发起接口请求允许发起
            if ($api_refund_request == 'true'){
                $refund_request = true;
            }
        }

        if($shopInfo['node_type']=='website'){
            $refund_request = true;
        }
        //bbc的0元退款申请单，需要发起同意请求，其他平台0元不发起
        if($shopInfo['node_type'] !='bbc' ){
            //退款金额为零将不发起前端同步
            if ($applyInfo['money'] <= 0){
                //$refund_request = false;
            }
        }

        //归档订单不发起
        if ($is_archive) {
            $refund_request = false;
        }

        return $refund_request;
    }

    
    /**
     * 创建退款申请单
     * 
     * @param Array $data 提交的数据
     * @param bool $is_update_order 更新订单付款状态
     * @param String $error_msg
     * 
     * @return Array
     */
    public function createRefundApply(&$sdf, $is_update_order=false, &$error_msg)
    {
        $refundapp = app::get('ome')->model('refund_apply');
        $logObj    = app::get('ome')->model('operation_log');
        
        //格式化数据
        $sdf    = $this->_format_refund_apply($sdf);
        
        //[检查]普通流程产生的退款申请单
        if($sdf['refund_refer'] == '0' && $sdf['source'] != 'matrix')
        {
            $result    = $this->_check_refund_apply_data($sdf, $error_msg);
            if(!$result)
            {
                return false;
            }
        }
        
        //创建退款单
        $result    = $refundapp->save($sdf);
        if(!$result)
        {
            $error_msg    = '创建退款申请单失败,单据号为:'. $sdf['refund_apply_bn'];
            return false;
        }
        
        //[更新订单状态]普通流程产生的退款单
        if($is_update_order)
        {
            $must_pause = true;
            if($sdf['bool_type'] & ome_refund_bool_type::__PROTECTED_CODE){
                $must_pause = false;
            }
            //将订单更改为退款申请中
            kernel::single('ome_order_func')->update_order_pay_status($sdf['order_id'],$must_pause, __CLASS__.'::'.__FUNCTION__);
            
            //log日志
            $sdf['status'] = intval($sdf['status']);
            if($sdf['status'])
            {
                $status_str    = ome_refund_func::refund_apply_status_name($sdf['status']);
                $memo          = '创建退款申请单,状态：' . $status_str;
            }
            else
            {
                $memo    = '申请退款成功';
            }
            $logObj->write_log('refund_apply@ome', $sdf['apply_id'], $memo);
            
            return array('result'=>true, 'msg'=>'申请退款成功,单据号为:'. $sdf['refund_apply_bn']);
        }
        
        return true;
    }
    
    /**
     * 检查普通流程产生的退款申请单
     *
     * @param Array $data 提交的数据
     * @param String $error_msg
     */
    public function _check_refund_apply_data($sdf, &$error_msg)
    {
        if(empty($sdf['order_id']))
        {
            $error_msg    = '订单号不存在';
            return false;
        }
        
        if(empty($sdf['pay_type']))
        {
            $error_msg    = '请选择退款类型';
            return false;
        }
        
        if($sdf['refund_money'] <= 0)
        {
            $error_msg    = '退款金额必须大于0';
            return false;
        }
        
        //退款申请金额
        $countPrice    = $sdf['refund_money'];
        if(round($countPrice,3) > round($sdf['payed'],3))
        {
            $error_msg    = '退款申请金额大于订单上的余额';
            return false;
        }
        
        //余单撤销退款金额判断
        if($sdf['remain_cancel_flag'] == 'true' && ($countPrice > $sdf['diff_price']))
        {
            $error_msg    = '退款申请金额大于余单撤销金额';
            return false;
        }
        
        return true;
    }
    
    /**
     * 格式化退款申请单数据
     * 
     * @param Array $data 提交的数据
     * 
     * @return Array
     */
    public function _format_refund_apply($data)
    {
        //本地新建OR线上新建(local:本地, matrix:线上, archive:本地归档订单)
        $source    = (empty($data['source']) ? 'local' : $data['source']);
        
        //退款申请来源(0:普通流程产生的退款申请 1:通过售后流程产生的退款申请)
        $refund_refer    = ($data['refund_refer'] == '1' ? '1' : '0');
        
        //审核操作员
        $op_id    = kernel::single('desktop_user')->get_id();
        $op_id    = ($op_id ? $op_id :0);
        
        //退款单状态
        $status    = ($data['status'] ? $data['status'] : '0');
        
        //创建时间
        $create_time    = ($data['create_time'] ? $data['create_time'] : time());
        
        //申请退款单号
        $refundapp        = app::get('ome')->model('refund_apply');
        $refund_apply_bn  = $data['refund_apply_bn'] ? $data['refund_apply_bn'] : $refundapp->gen_id();
        
        //订单信息
        if($data['order_id'])
        {
            $filter    = array('order_id'=>$data['order_id']);
        }
        else 
        {
            $filter    = array('order_bn'=>$data['order_bn']);
        }
        
        if($source == 'archive')
        {
            $orderObj    = app::get('archive')->model('orders');
            $orderData   = $orderObj->dump($filter, '*');
        }else{
            $orderObj    = app::get('ome')->model('orders');
            $orderData   = $orderObj->dump($filter, '*');
        }
        
        if(empty($orderData))
        {
            return false;
        }
        //退款明细
        if ($data['obj_id'] && $source == 'local' && !isset($data['product_data'])) {
            $objList = app::get('ome')->model('order_objects')->getList('*',['order_id'=>$data['order_id']]);
            $order_items = app::get('ome')->model('order_items')->getList('*',['order_id'=>$data['order_id']]);
    
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
            $productData = [];
            foreach ($objList as $key => $val) {
                if ($val['delete'] == 'true' || !in_array($val['obj_id'],$data['obj_id'])) {
                    unset($objList[$key]);
                    continue;
                }
                foreach ($val['order_items'] as $itemKey => $itemVal) {
                    $item = [];
                    $item['order_item_id'] = $itemVal['item_id'];
                    $item['num'] = $itemVal['nums'];
                    $item['product_id'] = $itemVal['product_id'];
                    $item['bn'] = $itemVal['bn'];
                    $item['name'] = $itemVal['name'];
                    $item['price'] = $itemVal['price'];
                    $item['oid'] = $val['oid'];
                    $item['item_type'] = $itemVal['item_type'];
                    $item['obj_id'] = $itemVal['obj_id'];
                    $item['divide_order_fee'] = $itemVal['divide_order_fee'];
                    $productData[] = $item;
                }
            }
            if (!empty($productData)) {
                $orderLib = kernel::single('ome_order');
                $options = array(
                    'part_total'  => $data['money'] ? $data['money'] : $data['refund_money'],
                    'part_field'  => 'amount',
                    'porth_field' => 'divide_order_fee',
                );
                $productData = $orderLib->calculate_part_porth($productData, $options);
                foreach ($productData as $k => $item) {
                    $productData[$k]['price'] = sprintf('%.2f', $item['amount'] / $item['num']);
                }
            }
            $data['bn']  = implode(',', array_column((array)$objList, 'bn'));
            $data['obj_id']  = implode(',', array_column((array)$objList, 'obj_id'));
            $data['oid'] = implode(',', array_column((array)$objList, 'oid'));
            $data['product_data'] = serialize($productData);
        }

        //店铺类型
        $shopObj    = app::get('ome')->model('shop');
        // $shop_type  = $shopObj->getShoptype($orderData['shop_id']);
        $shop_info = $shopObj->getShopInfo($orderData['shop_id']);
        $shop_type = $shop_info['shop_type'];
        
        //普通申请单数据格式化
        $data['bcmoney']    = ($data['bcmoney'] ? $data['bcmoney'] : 0);
        if($source == 'local' && isset($data['refund_money']))
        {
            //金额
            $mathLib = kernel::single('eccommon_math');
            $data['bcmoney']  = $mathLib->getOperationNumber($data['bcmoney']);//补偿费用
            $data['money']    = $data['refund_money'] + $data['bcmoney'];
            
            //退款明细序列化字段
            $data['addon']    = serialize(array('return_id'=>$data['return_id']));
        }
        
        //已退款金额
        $data['refunded']    = ($data['refunded'] ? $data['refunded'] : 0);
        
        //组织数据
        $sdf    = array(
                'return_id' => (int)$data['return_id'],
                'refund_apply_bn' => $refund_apply_bn,
                'order_id' => $orderData['order_id'],
                'shop_id' => $orderData['shop_id'],
                'shop_type' => $shop_type,
                'pay_type' => $data['pay_type'],
                'bank' => $data['bank'],
                'account' => $data['account'],
                'pay_account' => $data['pay_account'],
                'refund_money' => $data['refund_money'],
                'money' => $data['money'],
                'refunded' => $data['refunded'],
                'bcmoney' => $data['bcmoney'],
                'payed' => $orderData['payed'],
                'payment' => is_numeric($data['payment'])?$data['payment']:null,
                'memo' => $data['memo'],
                'apply_op_id' => $op_id,//申请操作员
                'verify_op_id'  => $op_id,//审核操作员
                'status' => $status,
                'addon'  =>  $data['addon'],//退款明细序列化字段
                'create_time' => $create_time,
                'refund_refer' => $refund_refer,
                'source' => $source,
                'org_id' => $orderData['org_id'],
                'bn' => $data['bn'],
                'oid' => $data['oid'],
                'bool_type'=>$data['bool_type'],
                'source_status'=>$data['source_status'],
                'cost_freight' =>0, 
        );
        if($data['cost_freight']){
            $sdf['cost_freight'] = $data['cost_freight'];
        }

        // 经销店铺的单据，delivery_mode冗余到退款单申请表
        if ($shop_info['delivery_mode'] == 'jingxiao') {
            $sdf['delivery_mode'] = $shop_info['delivery_mode'];
        }

        // 更新
        if ($data['apply_id']){
            $sdf['apply_id'] = $data['apply_id'];
        }
        
        if($data['reship_id']) $sdf['reship_id'] = $data['reship_id'];
        //退货商品序列化字段
        if($data['product_data'])
        {
            $sdf['product_data']  = $data['product_data'];
        }
        
        //前端店铺最后更新时间
        if($data['outer_lastmodify'])
        {
            $sdf['outer_lastmodify']  = $data['outer_lastmodify'];
        }
        
        //归档订单标识
        if($source == 'archive')
        {
            $sdf['archive'] = 1;
        }
        
        //余单撤消标识
        if($data['remain_cancel_flag'])
        {
            $sdf['remain_cancel_flag']  = $data['remain_cancel_flag'];
            $sdf['diff_price']  = $data['diff_price'];
        }
        if ($data['order_source']) {
            $sdf['order_source'] = $data['order_source'];
        }

        return $sdf;
    }
    
    /**
     * [批量同意退款]识别当前退款申请是否需要请求接口进行退款
     *
     * @param  $params  参数
     * @param  $err_msg  错误信息
     * @return bool
     */
    public function checkBatchForRequest($params, &$err_msg)
    {
        $refund_request = false;
        
        //C2C前端店铺列表
        $c2c_shop = ome_shop_type::shop_list();
        
        $orderInfo = $params['orderInfo']; //订单参数信息
        $applyInfo = $params['applyInfo']; //退款申请单参数信息
        $api_fail_flag = $params['api_fail_flag'];
        $api_refund_request = $params['api_refund_request'];
        
        $is_archive = $params['is_archive']; //是否归档订单
        
        $is_aftersale_refund = ($applyInfo['return_id'] > 0) ? true : false;//识别售后退款还是售前退款
        
        //获取当前店铺相关信息
        $shopObj = app::get('ome')->model('shop');
        $shopInfo = $shopObj->dump($orderInfo['shop_id'], 'node_id,node_type,business_type,tbbusiness_type');
        
        //识别是否是已绑定的线上退款申请
        if($shopInfo['node_id'] && $orderInfo['source'] == 'matrix')
        {
            //非C2C销售平台,这里主要指自有体系
            if(!in_array($shopInfo['node_type'], $c2c_shop))
            {
                if ($api_refund_request == 'true'){
                    $refund_request = true;
                }else{
                    $err_msg = '向前端退款失败,仅本地退款!';
                }
            }
            elseif($shopInfo['node_type'] == 'taobao' && $shopInfo['business_type'] == 'zx' && (strtolower($shopInfo['tbbusiness_type']) == 'b') && ($is_aftersale_refund || (!$is_aftersale_refund && $applyInfo['source'] == 'matrix')))
            {
                if ($api_refund_request == 'true'){
                    //天猫店判断逻辑，如果是线上售前退款或者售后才允许发起
                    //售后退款的识别状态是否已退货入仓完成状态
                    if($is_aftersale_refund){
                        $returnPrdObj = app::get('ome')->model('return_product');
                        $return_info = $returnPrdObj->dump($applyInfo['return_id']);
                        $return_status = ($return_info['status'] == 4) ? true : false;
    
                        $refundExtMdl = 'return_product_tmall';
                        $refundExtFilter = array('return_id' => $applyInfo['return_id']);
                    }else{
                        $return_status = true;
    
                        $refundExtMdl = 'refund_apply_tmall';
                        $refundExtFilter = array('apply_id' => $applyInfo['apply_id']);
                    }
                    
                    //获取原始退款金额
                    $refundOriginalObj = app::get('ome')->model($refundExtMdl);
                    $refundOriginalInfo = $refundOriginalObj->getList('refund_fee', $refundExtFilter , 0 , 1);
                    $original_refund_fee = $refundOriginalInfo[0]['refund_fee'];
                    
                    //本次申请退款的金额
                    $money = $applyInfo['money'];
                    
                    //当AG自动退款开启，原始退款金额存在并且实际退款等于原始申请退款金额，则允许接口请求自动退款
                    $aliag_status = app::get('ome')->getConf('shop.aliag.config.'.$orderInfo['shop_id']);
                    if($aliag_status && $return_status && $original_refund_fee && (round($original_refund_fee,3)== round($money,3))){
                        $refund_request = true;
                    }
                }else{
                    $err_msg = '向前端退款失败,仅本地退款!';
                }
            }elseif(in_array($shopInfo['node_type'], ome_shop_type::shop_refund_list())){
                $refund_request = true;
            }
        }
        
        //bbc的0元退款申请单，需要发起同意请求，其他平台0元不发起
        if($shopInfo['node_type'] != 'bbc'){
            //退款金额为零将不发起前端同步
            if ($applyInfo['money'] <= 0){
                $refund_request = false;
            }
        }
        
        //归档订单不发起
        if ($is_archive) {
            $refund_request = false;
        }
        
        return $refund_request;
    }

    /**
     * AG退款
     * @param  [type] $sdf [description]
     * @return [type]      [description]
     */
    public function refund_ag($refund_apply_bn,$logi)
    {
        $refundApplyMdl = app::get('ome')->model('refund_apply');
        
        //refund_appy
        $refund_apply = $refundApplyMdl->db_dump(['refund_apply_bn' => $refund_apply_bn]);
        if (!$refund_apply) {
            return [false, '未检测到退款申请单'];
        }

        $order = app::get('ome')->model('orders')->db_dump(['order_id' => $refund_apply['order_id']],'order_id,order_bn,process_status,source,shop_id,shop_type,ship_status');
        $shopType = $order['shop_type'];
        $status   = $refund_apply['status'];

        // website来源订单允许 status: 0,1,2；其他来源只允许 status: 0
        if (($shopType != 'website' && $status != 0) || ($shopType == 'website' && !in_array($status, [0, 1, 2]))) {
            return [false, '退款申请状态不符合要求'];
        }
        
        if (!in_array($order['shop_type'], ['taobao','tmall','360buy','luban','pinduoduo','website'])) {
            return [false ,'平台不支持AG退款'];
        }

        if ($order['source'] != 'matrix') {
            return [false, '只支持平台订单AG退款'];
        }

        $aliag_status = app::get('ome')->getConf('shop.aliag.config.'.$order['shop_id']);
        if (!$aliag_status) {
            return [false, '未开启AG退款'];
        }

        $params = array(
            'order_bn'            => $order['order_bn'],
            'apply_id'            => $refund_apply['apply_id'],
            'refund_bn'           => $refund_apply['refund_apply_bn'],
            'is_aftersale_refund' => false,
            'shop_id'             => $order['shop_id'],
            'oid'                 => $refund_apply['oid'],
            'company_code'        => $logi['company_code'],
            'logistics_no'        => $logi['logistics_no'],
            'trigger_event'       => $logi['trigger_event'],
            'refund_refer'        => $refund_apply['refund_refer'],
            'op_time'             => $refund_apply['last_modified'],
            'money'               => $refund_apply['money'],
            'order_id'            => $refund_apply['order_id'],
            'product_data'        => $refund_apply['product_data'],
        );

        $params = array_merge((array)$params, (array)$logi);

        //检查当前订单的状态
        if(in_array($order['process_status'],array('unconfirmed','confirmed')) || $order['ship_status'] == '4'){
            $params['cancel_dly_status'] = 'SUCCESS';
        }else{
            $params['cancel_dly_status'] = 'FAIL';
        }

        // 判断当前子单状态
        if ($refund_apply['oid']) {
            $order_object = app::get('ome')->model('order_objects')->db_dump([ 'order_id' => $order['order_id'], 'oid' => $refund_apply['oid'] ],'obj_id');

            if ($order_object) {
                $order_item = app::get('ome')->model('order_items')->db_dump([ 'order_id' => $order['order_id'], 'obj_id' => $order_object['obj_id'], 'split_num|than' => '0']);

                if (!$order_item) {
                    $params['cancel_dly_status'] = 'SUCCESS';
                }
            }
        }


        // 指定状态
        if ($logi['cancel_dly_status']) {
            $params['cancel_dly_status'] = $logi['cancel_dly_status'];
        }

        kernel::single('ome_service_refund')->refund_request($params);

        //触发拒绝退款,需要打异常标识
        if($params['cancel_dly_status'] == 'FAIL'){
            //标记为异常
            $refundApplyMdl->set_abnormal_status($refund_apply['apply_id'], ome_constants_refundapply_abnormal::__REPET_REFUND_CODE);
        }
        
        return [true, 'AG退款已发起'];
    }

    /**
     * 创建完成退款申请单和退款单
     * @Author: XueDing
     * @Date: 2025/2/5 3:49 PM
     * @param $refundapply|array|售后申请单相关字段
     * @param $is_update_order|bool|是否更新订单状态
     * @return array|true[]
     */
    public function createFinishRefundApply($refundapply, $is_update_order = false)
    {
        $refundApplyMdl = app::get('ome')->model('refund_apply');
        $opObj          = app::get('ome')->model('operation_log');

        //创建退款申请单
        $apply = $this->createRefundApply($refundapply, $is_update_order, $error_msg);
        if (!$apply) {
            return [false, $error_msg];
        }
        $apply_id = $refundapply['apply_id'];
        //更新为已退款
        $applydata                  = array ();
        $applydata['apply_id']      = $apply_id;
        $applydata['status']        = 4; //已经退款
        $applydata['last_modified'] = time();
        $refundApplyMdl->save($applydata, true);
        $opObj->write_log('refund_apply@ome', $applydata['apply_id'], "已退款成功，更新退款申请状态");
        //创建退款单
        $refundapply['refund_bn']    = $refundapply['refund_apply_bn'];
        $refundapply['refund_fee']   = $refundapply['refund_money'];
        $refundapply['t_ready']      = $refundapply['create_time'];
        $refundapply['t_sent']       = $refundapply['create_time'];
        $refundapply['t_received']   = $refundapply['create_time'];
        $refundapply['refund_refer'] = $refundApplyMdl->get_schema()['columns']['refund_refer']['type'][$refundapply['refund_refer']];
        list($res, $msg) = kernel::single('ome_order_refund')->create($refundapply);
        if (!$res) {
            return [false, $msg];
        }

        return [true];
    }

    /**
     * 根据退款申请单ID更新sdb_ome_order_objects表的pay_status
     * @param int $apply_id 退款申请单ID
     * @return bool 更新是否成功
     */
    public function updateOrderObjectsPayStatusByItemIds($apply_id)
    {
        if (empty($apply_id)) {
            return false;
        }

        $refundApplyMdl = app::get('ome')->model('refund_apply');
        $orderItemMdl = app::get('ome')->model('order_items');
        $orderObjectMdl = app::get('ome')->model('order_objects');

        // 获取退款申请单信息
        $refundApply = $refundApplyMdl->db_dump($apply_id);
        if (empty($refundApply)) {
            return false;
        }

        $order_id = $refundApply['order_id'];
        $status = $refundApply['status'];
        $refunded = $refundApply['refunded']; // 已退款金额

        // 获取退款申请单关联的order_item_ids
        $order_item_ids = array();
        if (!empty($refundApply['product_data'])) {
            $product_data = unserialize($refundApply['product_data']);
            if (is_array($product_data)) {
                foreach ($product_data as $item) {
                    if (!empty($item['order_item_id'])) {
                        $order_item_ids[] = $item['order_item_id'];
                    }
                }
            }
        }

        if (empty($order_item_ids)) {
            return false;
        }

        // 获取对应的obj_id列表
        $filter = array('item_id' => $order_item_ids, 'order_id' => $order_id);
        $orderItems = $orderItemMdl->getList('obj_id', $filter);

        if (empty($orderItems)) {
            return false;
        }

        $obj_ids = array_column($orderItems, 'obj_id');
        $divide_order_fee_sum = 0;
        $objects = $orderObjectMdl->getList('divide_order_fee', array('obj_id' => $obj_ids));
        foreach ($objects as $obj) {
            $divide_order_fee_sum = bcadd($divide_order_fee_sum, $obj['divide_order_fee'], 3);
        }

        // 根据退款申请单状态和金额确定pay_status
        $pay_status = 1; // 默认已支付

        switch ($status) {
            case 0: // 未审核
            case 1: // 审核中
            case 2: // 已接受申请
                $pay_status = 6; // 退款申请中
                break;

            case 3: // 已拒绝
            case 10: // 卖家拒绝退款
                $pay_status = 1; // 已支付
                break;

            case 4: // 已退款
                if (bccomp($refunded, $divide_order_fee_sum, 3) >= 0) {
                    $pay_status = 5; // 全额退款
                } else {
                    $pay_status = 4; // 部分退款
                }
                break;

            case 5: // 退款中
            case 6: // 退款失败
                $pay_status = 7; // 退款中
                break;

            default:
                $pay_status = 1; // 已支付
                break;
        }

        // 更新order_objects表的pay_status
        $update_data = array('pay_status' => $pay_status);
        $update_filter = array('obj_id' => $obj_ids, 'pay_status|notin' => ['5']);

        $result = $orderObjectMdl->update($update_data, $update_filter);

        // 记录操作日志
        if (!is_bool($result)) {
            $status_names = array(
                0 => '未审核',
                1 => '审核中',
                2 => '已接受申请',
                3 => '已拒绝',
                4 => '已退款',
                5 => '退款中',
                6 => '退款失败',
                10 => '卖家拒绝退款'
            );
            $pay_status_names = array(
                1 => '已支付',
                4 => '部分退款',
                5 => '全额退款',
                6 => '退款申请中',
                7 => '退款中'
            );

            $log_msg = sprintf(
                '根据退款申请单:%s(状态:%s)更新商品pay_status为%s',
                $refundApply['refund_apply_bn'],
                $status_names[$status] ?? '未知',
                $pay_status_names[$pay_status] ?? '未知'
            );
            app::get('ome')->model('operation_log')->write_log('order_edit@ome', $order_id, $log_msg);
        }
        
        // 订单支付状态发生变化
        if(in_array($pay_status, [4, 5, 6, 7])){
            $orderSdf = [
                'order_id' => $order_id,
                'pay_status' => $pay_status,
            ];
            
            // [更新]预约订单的相关状态
            $error_msg = '';
            kernel::single('ome_order_reservation')->operateReservationOrder($orderSdf, $error_msg);
        }
        
        // 增加service调用，便于扩展
        foreach (kernel::servicelist('ome.service.refund.apply.objectspaystatus.after') as $service) {
            if (method_exists($service, 'afterUpdateOrderObjectsPayStatus')) {
                $service->afterUpdateOrderObjectsPayStatus($order_id);
            }
        }

        return !is_bool($result);
    }
}
