<?php
/**
 * Copyright © ShopeX （http://www.shopex.cn）. All rights reserved.
 * See LICENSE file for license details.
 */
/**
 * @desc
 * @author: jintao
 * @since: 2016/7/21
 */
class erpapi_shop_response_process_aftersalev2 {

    /**
     * 添加
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */

    public function add($sdf) {
       
        switch($sdf['response_bill_type']) {
            case 'refund_apply' :
                $rs = $this->_dealRefundApply($sdf);
                break;
            case 'refund' :
                $rs = $this->_dealRefund($sdf);
                break;
            case 'refundonly' :
                //只创建退款申请单、退款单,不编辑订单明细、也不更新订单金额
                $rs = $this->_dealRefundOnly($sdf);
                break;
            case 'return_product' :
                $rs = $this->_dealReturnProduct($sdf);
                break;
            case 'reship' :
                $rs = $this->_dealReship($sdf);
                break;
            default :
                $rs = array('rsp'=>'fail', 'msg'=>'没有单据类型');
        }
        return $rs;
    }

    private function _dealRefundApply($sdf) {
        if ($sdf['refund']) {
            return $this->_hadRefund($sdf);
        }
        $modelRefundApply = app::get('ome')->model('refund_apply');
        $oOperation_log = app::get('ome')->model('operation_log');//写日志
        if($sdf['refund_apply']) {
            $refundApply = $sdf['refund_apply'];
            $filter = array('apply_id' => $refundApply['apply_id']);
            switch($sdf['status']) {
                case '0':
                    $upData = $this->_refundApplySdfToData($sdf);
                    $memo = '(退款金额、原因或版本变化)退款申请单更新为未审核';
                    break;
                default :
                    $upData = array(
                        'status' => $sdf['status'],
                        'source_status' => $sdf['source_status'], //平台状态
                        'outer_lastmodify'=> $sdf['modified'],
                    );
                    if($sdf['refund_version_change']) {
                        $upData['memo']  = $sdf['reason'];
                        $upData['money'] = $sdf['refund_fee'];
                    } 
                    $memo = '更新成功,状态：' . $sdf['status'];
                    break;
            }
            $rs = $modelRefundApply->update($upData, $filter);
            $idBn = array(
                'apply_id' => $refundApply['apply_id'],
                'refund_apply_bn' => $upData['refund_apply_bn']
            );
            $this->_dealTableAdditional($sdf['table_additional'], $idBn);
            if(!is_bool($rs)) {
                $refundApply = array_merge($refundApply, $upData);
                $oOperation_log->write_log('refund_apply@ome', $refundApply['apply_id'], $memo);
            }
            $must_pause = true;
            if($sdf['bool_type'] & ome_refund_bool_type::__PROTECTED_CODE){
                $must_pause = false;
            }
            $trans = kernel::database()->beginTransaction();
            //先更新订单主信息， 避免退款申请和退款完成并发导致订单因退款完成撤销发货单失败而编辑失败（退款申请撤销发货单成功）
            $update_sql = "UPDATE sdb_ome_orders SET createtime=`createtime` WHERE order_id=". $sdf['order']['order_id'];
            kernel::database()->exec($update_sql);
            //更新订单支付状态、叫回发货单等
            kernel::single('ome_order_func')->update_order_pay_status($sdf['order']['order_id'],$must_pause, __CLASS__.'::'.__FUNCTION__);
            kernel::database()->commit($trans);

            // 退款申请拒绝，重新路由
            if ($sdf['status'] == '3') {
                //延迟5分钟自动重新路由审核订单
                $sdf = array('op_type'=>'timing_confirm', 'timing_time'=>strtotime('5 minutes'), 'memo'=>'退款申请拒绝后重新路由');
                kernel::single('ome_order')->auto_order_combine($sdf['order']['order_id'], $sdf);
            }
        } else {
            $insertData = $this->_refundApplySdfToData($sdf);
            
            //创建退款单
            $is_update_order    = true;//是否更新订单付款状态
            $error_msg = '';
            $trans = kernel::database()->beginTransaction();
            //先更新订单主信息， 避免退款申请和退款完成并发导致订单因退款完成撤销发货单失败而编辑失败（退款申请撤销发货单成功）
            $update_sql = "UPDATE sdb_ome_orders SET createtime=`createtime` WHERE order_id=". $sdf['order']['order_id'];
            kernel::database()->exec($update_sql);
            $rs = kernel::single('ome_refund_apply')->createRefundApply($insertData, $is_update_order, $error_msg);
            if(!$rs) {
                kernel::database()->rollBack();
                return array('rsp'=>'fail', 'msg'=>'退款申请单生成失败');
            }
            kernel::database()->commit($trans);
            
            $idBn = array(
                'apply_id' => $insertData['apply_id'],
                'refund_apply_bn' => $insertData['refund_apply_bn']
            );
            $this->_dealTableAdditional($sdf['table_additional'], $idBn);
            $memo = '创建退款申请单,状态：' . $sdf['status'].$sdf['memo'];
            $oOperation_log->write_log('refund_apply@ome',$insertData['apply_id'],$memo);
            
            //[抖音]兼容退货单修改为仅退款单
            if(in_array($insertData['shop_type'],['luban'])){
                $lubanLib = kernel::single('ome_reship_luban');
                
                $result = $lubanLib->transformRefundApply($insertData);
            }
            
        }
        
        //识别是否天猫退款触发AG接口
        $noticeParams = array_merge($sdf, $idBn);
        $this->_noticeAg($noticeParams);

        return array('rsp'=>'succ', 'msg'=>$memo);
    }

    private function _refundApplySdfToData($sdf)
    {
        //退款来源(normal:普通退款,aftersale:售后仅退款,不退货;)
        if($sdf['refund_refer'] == 'aftersale'){
            $refund_refer = '1';
        }elseif($sdf['refund_refer'] == 'normal'){
            $refund_refer = '0';
        }else{
            $refund_refer = in_array($sdf['order']['ship_status'], array('1','3','4')) ? '1' : '0';
        }
        if($sdf['refund_to_returnProduct']) {
            $refund_refer = '0';
        }
        
        //oid子单过滤掉重复的oid
        //@todo：之前代码中--PKG组合商品退款时会保存多个oid; 本次只是过滤了重复的oid子单;
        $oids = array_column((array)$sdf['refund_item_list'], 'oid');
        $oids = array_unique($oids);
        $oids = implode(',', $oids);
        
        //data
        $data = array(
            'order_id'        => $sdf['order']['order_id'],
            'refund_apply_bn' => $sdf['refund_bn'],
            'pay_type'        => $sdf['pay_type'],
            'account'         => $sdf['account'],
            'bank'            => $sdf['bank'],
            'pay_account'     => $sdf['pay_account'],
            'money'           => $sdf['refund_fee'],
            'refunded'        => '0',
            'memo'            => $sdf['reason'],
            'create_time'     => $sdf['created']?:time(),
            'status'          => $sdf['status'],
            'shop_id'         => $sdf['shop_id'],
            'addon'           => serialize(array('refund_bn'=>$sdf['refund_bn'])),
            'source'          => 'matrix',
            'shop_type'       => $sdf['shop_type'],
            'outer_lastmodify'=> $sdf['modified'],
            'real_refund_amount' => $sdf['real_refund_amount'] ? : 0,
            'refund_refer'    => $refund_refer, //退款来源
            'org_id'          => $sdf['org_id'],
            'bn'              => implode(',',array_column((array)$sdf['refund_item_list'], 'bn')),
            'oid'             => $oids, //oid子单
            'bool_type'       => $sdf['bool_type'],  
            'source_status'   => kernel::single('ome_refund_func')->get_source_status($sdf['source_status']),
            'tag_type'        => ($sdf['tag_type'] ? $sdf['tag_type'] : '0'), //退款类型
            'jsrefund_flag'   => ($sdf['jsrefund_flag'] ? $sdf['jsrefund_flag'] : '0'), //极速退款标识
        );

        // 经销店铺的单据，delivery_mode冗余到退款单申请表
        $shopMdl    = app::get('ome')->model('shop');
        $shop_info  = $shopMdl->getShopInfo($data['shop_id']);
        if ($shop_info['delivery_mode'] == 'jingxiao') {
            $data['delivery_mode'] = $shop_info['delivery_mode'];
        }

        $bmIds = array();
        if($sdf['refund_item_list']) {
            $arrProduct = array();
            foreach($sdf['refund_item_list'] as $val) {
                $bmIds[] = $val['product_id'];
                $arrProduct[] = array(
                    'product_id'    => $val['product_id'],
                    'bn'            => $val['bn'],
                    'name'          => $val['title'] ? $val['title'] : $val['name'],
                    'num'           => $val['num'],
                    'price'         => $val['price'],
                    'order_item_id' =>$val['order_item_id'],
                    'oid'           => $val['oid'],
                    'refund_phase'  => $val['refund_phase'],
                    'refund_memo'   => $val['refund_memo'],
                    'modified'      => kernel::single('ome_func')->date2time($val['modified']),
                    'sku_uuid'      => $val['sku_uuid'],

                );
            }
            $data['product_data'] = serialize($arrProduct);
        }
        
        // OMS已发货的售前仅退款，发货拦截
        if ($sdf['refund_refer'] != 'aftersale' && $sdf['order']['ship_status'] == '1') {
            ome_delivery_notice::cancelYJDF($data['order_id'], $bmIds);
        }
        if ($sdf['order_source']) {
            $data['order_source'] = $sdf['order_source'];
        }
        
        return $data;
    }

    private function _dealTableAdditional($tableAdditional, $idBn) {
        if(empty($tableAdditional) || empty($idBn)) {
            return false;
        }
        $model = app::get('ome')->model($tableAdditional['model']);
        if($tableAdditional['model'] == 'return_apply_special') {
            $old = $model->db_dump($idBn, 'id');
            if($old) {
                $model->update($tableAdditional['data'], array('id'=>$old['id']));
                return;
            }
        }
        $data = array_merge($tableAdditional['data'], $idBn);
        $model->db_save($data);
    }

    #退款单已经存在处理
    private function _hadRefund($sdf) {
        $modelRefundApply = app::get('ome')->model('refund_apply');
        $oOperation_log = app::get('ome')->model('operation_log');//写日志
        $msg = '退款单' . $sdf['refund']['refund_bn'] . '已经存在';
        if($sdf['refund_apply'] && $sdf['refund_apply']['status'] != '4') {
            $modelRefundApply->update(array('status' => '4','refunded' => $sdf['refund_fee']), array('apply_id' => $sdf['refund_apply']['apply_id']));
            $msg .= '  更新退款申请单为已退款';
            $oOperation_log->write_log('refund_apply@ome', $sdf['refund_apply']['apply_id'], '退款单已存在，自动更新为已退款');
        }
        return array('rsp'=>'succ', 'msg'=>$msg);
    }

    private function _dealRefund($sdf)
    {
        if ($sdf['refund']) {
            return $this->_hadRefund($sdf);
        }
        $msg = '创建退款单';
        $refundApply = $sdf['refund_apply'];
        if(!$refundApply) {
            $applyData = $this->_refundApplySdfToData($sdf);
            #避免退款单和退款申请单并发，退款申请单写入后事务未提交，这里单据查不到
            $trans = kernel::database()->beginTransaction();
            $modelRefundApply = app::get('ome')->model('refund_apply');
            $insertRes = $modelRefundApply->insert($applyData);
            if (!$insertRes) {
                $applyData = $modelRefundApply->db_dump(array('refund_apply_bn' => $sdf['refund_bn']));
            }
            kernel::database()->commit($trans);
            $refundApply = $sdf['refund_apply'] = $applyData;
        }
        if(empty($refundApply['reship_id']) && app::get('ome')->model('reship')->db_dump(['reship_bn'=>$refundApply['refund_apply_bn'],'is_check|notin'=>array('5','7')], 'reship_id')) {
            return array('rsp'=>'fail', 'msg'=>'该退款单存在退货单，不能完成');
        }
        //data
        $data = $this->_refundsdfToData($sdf);
        
        //insert
        $rs = app::get('ome')->model('refunds')->insert($data);
        if(!$rs) {
            return array('rsp'=>'fail', 'msg'=>'退款单创建失败');
        }
        if ($refundApply) {
            $filter = array(
                'apply_id' => $refundApply['apply_id'],
            );
            $updateData = array('status' => '4','refunded' => $sdf['refund_fee']);
            app::get('ome')->model('refund_apply')->update($updateData,$filter);
            app::get('ome')->model('operation_log')->write_log('refund_apply@ome', $refundApply['apply_id'], '退款成功');
            $msg .= "&nbsp;&nbsp;更新退款申请单[{$refundApply['refund_apply_bn']}]为已退款";
            if ($refundApply['addon']) {
                $addon = unserialize($refundApply['addon']);
                $return_id = $addon['return_id'];
                if ($return_id) {
                    $pReturnModel = app::get('ome')->model('return_product');
                    $pReturnData = $pReturnModel->getList('refundmoney,return_bn', array('return_id' => $return_id), 0, 1);
                    $pReturn = $pReturnData[0];
                    //$refundMoney = bcadd((float)$sdf['refund_fee'], (float)$pReturn['refundmoney'],3);
                    
                    //更新售后申请单退款金额(不能累加refundmoney字段金额,否则会double)
                    if($sdf['refund_fee']){
                        $refundMoney = $sdf['refund_fee'];
                        $upReturnData = array('refundmoney'=>$refundMoney);
                        if($sdf['real_refund_amount']) {
                            $upReturnData['real_refund_amount'] = $sdf['real_refund_amount'];
                        }
                        $pReturnModel->update($upReturnData,array('return_id'=>$return_id));
                        $return_bn = $pReturn['return_bn'];
                        $msg .= "&nbsp;&nbsp;更新售后申请单[{$return_bn}]金额：".$refundMoney;
                    }
                    
                    //如果是售后退货完成产生的退款完成更新，生成售后单
                    //if($sdf['tmall_has_finished_return_product']){
                        //kernel::single('sales_aftersale')->generate_aftersale($refundApply['apply_id'],'refund');
                    //}
                }
            }
        }
        $must_pause = true;
        //价保订单更新退款价格
        if($sdf['bool_type'] & ome_refund_bool_type::__PROTECTED_CODE){
            $must_pause = false;
            $this->_updateProtectPrice($sdf);
            
            //价保退款标识
            $sdf['isPriceProtect'] = true;
        }
        if($this->_updateOrderPayed($sdf['order']['order_id'],$sdf['refund_fee'],$must_pause)) {
            $msg .= '&nbsp;&nbsp;更新订单支付状态';
        }
    
        //扣除原单的运费 要在原单退款单上加日志
        if($sdf['shop_type'] == 'website'){
           $this->_upOriginalOrder($sdf,$refundApply);
        }
        
        if($sdf['order']['createtime'] > (time() - 600)) {
            //如果10分钟内取消，则订单需要发起库存回写
            kernel::single('inventorydepth_stock')->storeNeedUpdateSku($sdf['order']['order_id'], $sdf['shop_id']);
        }
        
        if ($refundApply['refund_refer'] == '1' || $sdf['tmall_has_finished_return_product']) {
            kernel::single('sales_aftersale')->generate_aftersale($refundApply['apply_id'],'refund');
        }
        
        //自动编辑订单&&符合条件并自动审单
        $error_msg = '';
        $is_abnormal = false;
        $isResultEdit = $this->_autoEditorder($sdf, $error_msg, $is_abnormal);
        if(!$isResultEdit){
            $msg .= '&nbsp;&nbsp;'. $error_msg;
        }
        
        //判断如果订单总额和已支付金额相等
        $order_detail = app::get('ome')->model('orders')->dump(array('order_id'=>$sdf['order']['order_id']),'total_amount,payed,pay_status');
        if($order_detail['pay_status']!='1' && 0 == bccomp((float) $order_detail['total_amount'],(float) $order_detail['payed'],3)){
            kernel::single('ome_order_func')->update_order_pay_status($sdf['order']['order_id'],true, __CLASS__.'::'.__FUNCTION__);
        }
        
        //订单信息
        $orderInfo = $sdf['order'];
        
        //换货转退款
        if($sdf['desc']=='换货转退款'){

            kernel::single('ome_return')->changeToRefund($sdf);
        }
        //部分发货、全额退款的订单，自动执行余单撤消操作
        if($orderInfo['pay_status'] == '5' && $orderInfo['ship_status'] == '2'){
            kernel::single('ome_order_order')->fullRefund_order_revoke($sdf['order']['order_id']);
        }
        //售后仅退款转售前退款做发货单拦截
        if($sdf['refund_to_returnProduct'] && $refundApply['apply_id']) {
            $label_code = 'SOMS_MREFUND';
            $err = '';
            if($sdf['order']['sync'] != 'succ') {
                kernel::single('ome_bill_label')->markBillLabel($sdf['order']['order_id'], '', $label_code, 'order', $err, 0);
            }
            kernel::single('ome_order_refund')->lanjieDelivery($refundApply['apply_id']);
        }
        //退款未退货
        $this->_refundNoreturn($sdf,$refundApply);
        
        return array('rsp'=>'succ', 'msg'=>$msg);
    }
    
    /**
     * 单独只创建退款申请单、退款单,不编辑订单明细、也不更新订单金额
     * @todo：平台订单全额退款、已取消，同分同秒推送过来，OMS只更新订单为全额退款或取消订单，未创建退款单;
     * 
     * @param $sdf
     * @return string[]
     */
    private function _dealRefundOnly($sdf)
    {
        //处理退款单已经存在的情况
        if ($sdf['refund']) {
            return $this->_hadRefund($sdf);
        }
        
        $refund_bn = $sdf['refund_bn'];
        $shop_id = $sdf['shop_id'];
        $msg = '仅创建退款单、退款申请单';
        
        //reship
        $reshipInfo = app::get('ome')->model('reship')->db_dump(['reship_bn'=>$refund_bn, 'shop_id'=>$shop_id]);
        if(!empty($reshipInfo) && $reshipInfo['is_check'] != '5'){
            return array('rsp'=>'fail', 'msg'=>'该退款单存在退货单,不能创建');
        }
        
        //refund_apply
        $refundApply = [];
        if(isset($sdf['refund_apply'])){
            $refundApply = $sdf['refund_apply'];
        }
        
        //先创建退款申请单
        if(empty($refundApply)) {
            $modelRefundApply = app::get('ome')->model('refund_apply');
            #避免退款单和退款申请单并发，退款申请单写入后事务未提交，这里单据查不到
            $trans = kernel::database()->beginTransaction();
            //data
            $applyData = $this->_refundApplySdfToData($sdf);
            
            //insert
            $insertRes = $modelRefundApply->insert($applyData);
            if (!$insertRes) {
                $applyData = $modelRefundApply->db_dump(array('refund_apply_bn'=>$refund_bn));
            }
            kernel::database()->commit($trans);
            $refundApply = $sdf['refund_apply'] = $applyData;
        }
        
        //创建退款单
        $data = $this->_refundsdfToData($sdf);
        $rs = app::get('ome')->model('refunds')->insert($data);
        if(!$rs) {
            return array('rsp'=>'fail', 'msg'=>'仅创建退款单失败');
        }
        
        //更新退款申请单状态
        if ($refundApply) {
            //update
            $filter = array(
                'apply_id' => $refundApply['apply_id'],
            );
            $updateData = array('status' => '4','refunded' => $sdf['refund_fee']);
            app::get('ome')->model('refund_apply')->update($updateData, $filter);
            
            //logs
            app::get('ome')->model('operation_log')->write_log('refund_apply@ome', $refundApply['apply_id'], '仅创建退款单成功');
            
            $msg .= "&nbsp;&nbsp;更新退款申请单[{$refundApply['refund_apply_bn']}]为已退款状态";
        }
        
        //换货转退款
        if($sdf['desc']=='换货转退款'){

            kernel::single('ome_return')->changeToRefund($sdf);
        }
        //如果10分钟内取消，则订单需要发起库存回写
        if($sdf['order']['createtime'] > (time() - 600)) {
            kernel::single('inventorydepth_stock')->storeNeedUpdateSku($sdf['order']['order_id'], $sdf['shop_id']);
        }
        
        //自动编辑订单：主要更新[已经拆分]状态订单object层明细为已退款状态,并且打异常标识;
        if(in_array($sdf['order']['process_status'], ['splitting', 'splited'])){
            $error_msg = '';
            $is_abnormal = false;
            $isResultEdit = $this->_autoEditorder($sdf, $error_msg, $is_abnormal);
            if(!$isResultEdit){
                $msg .= '&nbsp;&nbsp;'. $error_msg;
            }
        }
        //售后仅退款转售前退款做发货单拦截
        if($sdf['refund_to_returnProduct'] && $refundApply['apply_id']) {
            $label_code = 'SOMS_MREFUND';
            $err = '';
            if($sdf['order']['sync'] != 'succ') {
                kernel::single('ome_bill_label')->markBillLabel($sdf['order']['order_id'], '', $label_code, 'order', $err, 0);
            }
            kernel::single('ome_order_refund')->lanjieDelivery($refundApply['apply_id']);
        }
        return array('rsp'=>'succ', 'msg'=>$msg);
    }
    
    private function _refundSdfToData($sdf)
    {
        //退款来源(normal:普通退款,aftersale:售后仅退款,不退货;)
        $refund_refer = '0';
        if($sdf['refund_refer'] == 'aftersale'){
            $refund_refer = '1';
        }
        
        $data = array(
            'refund_bn'     => $sdf['refund_bn'],
            'shop_id'       => $sdf['shop_id'],
            'order_id'      => $sdf['order']['order_id'],
            'currency'      => 'CNY',
            'money'         => $sdf['refund_fee'],
            'cur_money'     => $sdf['cur_money'] ? $sdf['cur_money'] : $sdf['refund_fee'],
            'pay_type'      => $sdf['pay_type'],
            'download_time' => time(),
            'status'        => 'succ',
            'memo'          => $sdf['reason'],
            'trade_no'      => $sdf['alipay_no'],
            'modifiey'      => $sdf['modified'],
            'payment'       => $sdf['payment'],
            't_ready'       => $sdf['t_ready'] ? $sdf['t_ready'] : $sdf['t_sent'],
            't_sent'        => $sdf['t_sent'] ? $sdf['t_sent'] : $sdf['t_ready'],
            't_received'    => $sdf['t_received'] ? $sdf['t_received'] : 0,
            'org_id'    => $sdf['org_id'],
            'refund_refer' => $refund_refer, //退款来源
        );
        
        return $data;
    }

    private function _updateOrderPayed($orderId, $money,$must_pause=true) {
        if (empty($orderId) || !$money) {
            return false;
        }
        #更新订单分先后，避免订单退款并发时发货单撤回不及时导致订单明细未删除
        $transaction = kernel::database()->beginTransaction();
        $sql ="update sdb_ome_orders set payed=IF((CAST(payed AS char)-IFNULL(0,cost_payment)-".$money.")>=0,payed-IFNULL(0,cost_payment)-".$money.",0)  where order_id=".$orderId;
        kernel::database()->exec($sql);

        //更新订单支付状态
        if (kernel::single('ome_order_func')->update_order_pay_status($orderId,$must_pause, __CLASS__.'::'.__FUNCTION__)){
            kernel::database()->commit($transaction);
            return true;
        }else{
            kernel::database()->commit($transaction);
            return false;
        }
    }

    private function _dealReturnProduct($sdf)
    {
        $modelReturnProduct = app::get('ome')->model('return_product');
        if($sdf['return_product']) {
            $idBn = array(
                'return_id' => $sdf['return_product']['return_id'],
                'return_bn' => $sdf['return_product']['return_bn']
            );
            $this->_dealTableAdditional($sdf['table_additional'], $idBn);
            
             //换转退处理
            kernel::single('ome_return')->processReturn($sdf);

            //更新退换货单
            $this->_returnProductUpdateStatus($sdf);
            
            $msg = '更新成功';
        } else {
            $insertData = $this->_returnProductSdfToData($sdf);
            $returnProductItems = $insertData['return_product_items'];
            if(empty($returnProductItems)) {
                return array('rsp'=>'fail', 'msg'=>'新建售后申请单失败:商品明细为空');
            }
            unset($insertData['return_product_items']);
            $returnGiftItems = $insertData['return_gift_items'];
            unset($insertData['return_gift_items']);
            
            //[抖音平台]"仅退款"转换为"退货"申请时
            if(in_array($insertData['shop_type'],['luban'])){
                $lubanLib = kernel::single('ome_reship_luban');
                
                $result = $lubanLib->transformReturnProduct($insertData);
                if($result['rsp'] != 'succ'){
                    return array('rsp'=>'fail', 'msg'=>'新建售后申请单失败:'.$result['error_msg']);
                }
            }
            
            //insert
            $rs = $modelReturnProduct->insert($insertData);
            if(!$rs) {
                return array('rsp'=>'fail', 'msg'=>'售后申请单新建失败');
            }
            
            $this->_insertReturnProductItems($returnProductItems, $insertData['return_id']);
            
            if($sdf['return_type']=='jdchange'){
                $this->saveChangeItems($sdf, $insertData['return_id']);
            }
            
            //防止并发赠品多次携带到售后申请单明细，update阻塞查询判断。
            $giftMsg = '';
            $operateLog = app::get('ome')->model('operation_log');
            if ($returnGiftItems) {
                $trt_status = kernel::database()->beginTransaction();
                $giftOrderItemId = array_column($returnGiftItems, 'order_item_id');
                $returnProductItemMdl = app::get('ome')->model('return_product_items');
                $res = kernel::database()->exec("UPDATE sdb_ome_orders SET createtime=`createtime` WHERE order_id =".$insertData['order_id']);
                if ($res['rs']) {
                    $isInsert = false;
                    $returnGifts = $returnProductItemMdl->getList('order_item_id,return_id',array('order_item_id'=>$giftOrderItemId));
                    //未添加过赠品可以插入
                    if (!$returnGifts) {
                        $isInsert = true;
                    }else{
                        $returnProductInfo = $modelReturnProduct->getList('return_id',['return_id'=>array_column($returnGifts,'return_id'),'status|noequal'=>'5']);
                        //已取消的退货申请单可以继续添加赠品
                        if (!$returnProductInfo) {
                            $isInsert = true;
                        }
                    }
                    if ($isInsert) {
                        foreach($returnGiftItems as &$val) {
                            $val['return_id'] = $insertData['return_id'];
                        }
                        $sql = ome_func::get_insert_sql($returnProductItemMdl, $returnGiftItems);
                        $insertGiftRs = $returnProductItemMdl->db->exec($sql);
                        $giftMsg = '自动带出赠品成功';
                        if (!$insertGiftRs['rs']) {
                            $giftMsg = '自动带出赠品失败';
                        }
                    }
                }
                kernel::database()->commit($trt_status);
            }
            
            //log
            $log_msg = '创建售后申请单';
            if(isset($insertData['is_reset_flag'])){
                if($insertData['is_reset_flag']){
                    $log_msg .= '(系统自动按销售单金额更新了平台退货金额)';
                }elseif(isset($insertData['reset_error_msg']) && $insertData['reset_error_msg']){
                    $log_msg .= '(系统自动更新平台退货金额失败：'. $insertData['reset_error_msg'] .')';
                }else{
                    $log_msg .= '(系统自动更新平台退货金额失败)';
                }
            }
            
            app::get('ome')->model('operation_log')->write_log('return@ome',$insertData['return_id'], $log_msg);
            
            //gift msg
            if ($giftMsg) {
                $operateLog->write_log('return@ome', $insertData['return_id'], $giftMsg);
            }
            $msg = '创建售后申请单成功';
            $idBn = array(
                'return_id' => $insertData['return_id'],
                'return_bn' => $insertData['return_bn']
            );
            $this->_dealTableAdditional($sdf['table_additional'], $idBn);
            if($sdf['table_return_freight']) {
                $returnFreight = array_merge($idBn, $sdf['table_return_freight']);
                app::get('ome')->model('return_freight')->insert($returnFreight);
            }
            if(in_array($sdf['status'], ['3','4','6']) && $insertData['is_fail'] == 'true') {
                return array('rsp'=>'fail', 'msg'=>'售后申请单处于失败状态，不能生成退货单');
            }
            
            //售后申请单自动审批(系统-->退换货自动审核设置-->是否启用售后申请单自动审批)
            $is_auto_approve = app::get('ome')->getConf('aftersale.auto_approve');
            $is_gift_auto_approve = app::get('ome')->getConf('aftersale.gift_auto_approve');
            //有赠品根据开发判断是否字段审核
            $isHaveGift = array_column($returnProductItems,'item_type');
            $isAutoGiftApprove = true;
            if ($isHaveGift && in_array('gift',$isHaveGift)) {
                if ($is_auto_approve == 'on'  && $is_gift_auto_approve != 'on' ) {
                    $isAutoGiftApprove = false;
                }
            }
            if ($is_auto_approve == 'on' && in_array($sdf['status'],array('1')) && $isAutoGiftApprove) {
                $sdf['status'] = '3';
            }
            
            if (in_array($sdf['status'],array('3','4','5','6'))) {
                $sdf['return_product'] = $modelReturnProduct->db_dump(['return_id'=>$insertData['return_id']]);
                
                //[抖音平台]推送同意退货状态给平台
                $sdf['sync_platform'] = true;
                $itemsObj = app::get('ome')->model('return_product_items');
                $sdf['refund_item_list'] = $itemsObj->getList('product_id,bn,name,num,price,order_item_id,amount,obj_type,shop_goods_bn,quantity,sku_uuid',array('return_id'=>$insertData['return_id']));
                $this->_returnProductUpdateStatus($sdf);
            }
        }
        
        return array('rsp'=>'succ', 'msg' => $msg);
    }

    private function _returnProductSdfToData($sdf) {
        $opInfo = kernel::single('ome_func')->get_system();
        $data = array(
            'return_bn'  => $sdf['refund_bn'],
            'shop_id'    => $sdf['shop_id'],
            'member_id'  => $sdf['member_id'],
            'order_id'   => $sdf['order']['order_id'],
            'title'      => $sdf['order_bn'].'售后申请单',
            'content'    => $sdf['reason'],
            'comment'    => $sdf['desc'],
            'add_time'   => $sdf['created']?:time(),
            'status'     => '1',
            'op_id'      => $opInfo['op_id'],
            'refundmoney'=> $sdf['refund_fee'], //@todo：退货金额大于OMS订单已支付金额,下面会按销售单重新获取金额;
            'money'      => $sdf['refund_fee'],
            'shipping_type'=> $sdf['shipping_type'],
            'source'     => 'matrix',
            'real_refund_amount' => $sdf['real_refund_amount'] ? : 0,
            'shop_type'  => $sdf['shop_type'],
            'outer_lastmodify'=>$sdf['modified']?:time(),
            'delivery_id'=> $sdf['delivery_id'],
            'memo'      =>  $sdf['memo'],
            'org_id' => $sdf['org_id'],
            'flag_type'     => $sdf['flag_type'],
            'platform_status' => $sdf['platform_status'], //平台售后单状态
            'apply_remark' => $sdf['apply_remark'], //售后申请描述
            'kinds'            =>'reship',
            'jsrefund_flag'   => ($sdf['jsrefund_flag'] ? $sdf['jsrefund_flag'] : '0'), //极速退款标识
            'refund_shipping_fee'=>$sdf['refund_shipping_fee'],
        );
        if($sdf['refund_to_returnProduct']) {
            $data['source'] = 'refund';
            $data['flag_type'] = $data['flag_type'] | ome_reship_const::__LANJIE_RUKU;
            $label_code = 'SOMS_MREFUND';
            $err = '';
            if($sdf['order']['sync'] != 'succ') {
                kernel::single('ome_bill_label')->markBillLabel($sdf['order']['order_id'], '', $label_code, 'order', $err, 0);
            }
        }
        
        //售后类型
        if ($sdf['return_type']){
            $data['return_type'] = $sdf['return_type'];
        }
        
        //平台订单号
        if($sdf['platform_order_bn']){
            $data['platform_order_bn'] = $sdf['platform_order_bn'];
        }
        
        if($sdf['platform_aftersale_bn']){
            $data['platform_aftersale_bn'] = $sdf['platform_aftersale_bn'];
        }
        
        if($sdf['cos_id']){
            $data['cos_id'] = $sdf['cos_id'];
        }
        
        if($sdf['betc_id']){
            $data['betc_id'] = $sdf['betc_id'];
        }
        
        // 经销店铺的单据，delivery_mode冗余到售后申请表
        if ($sdf['shop']['delivery_mode'] == 'jingxiao') {
            $data['delivery_mode'] = $sdf['shop']['delivery_mode'];
        }
        
        $isFail = 'false';
        $data['return_product_items'] = [];
        foreach($sdf['refund_item_list'] as $val) {
            if(isset($val['sendNum']) && $val['sendNum'] == 0) {
                continue;
            }
            $data['return_product_items'][] = array(
                'product_id' => $val['product_id'] ? $val['product_id'] : 0,
                'bn'         => $val['bn'],
                'name'       => $val['title'] ? $val['title']: $val['name'],
                'num'        => $val['num'],
                'price'      => $val['price'],
                'amount'     => $val['amount'],
                'branch_id'   =>$sdf['branch_id'],
                'order_item_id'=>$val['order_item_id'],
                'shop_goods_bn'=>$val['shop_goods_bn'],
                'obj_type'  =>$val['obj_type'],
                'quantity'  =>$val['quantity'],
                'oid'       =>$val['oid'],
                'sku_uuid'  =>$val['sku_uuid'],
            );
            
            if(empty($val['product_id'])) {
                $isFail = 'true';
            }
        }
        $data['is_fail'] = $isFail;
        
        //退货单增加赠品明细
        $data['return_gift_items'] = app::get('ome')->model('reship')->addReturnGiftItems($data['return_product_items'],$data['order_id'],$sdf['branch_id']);
    
        if ($sdf['reason']) {
            $problemMdl = app::get('ome')->model('return_product_problem');
            $problem = $problemMdl->db_dump(['problem_name' => $sdf['reason']]);
            if (!$problem) {
                $problem = [
                    'problem_name' => $sdf['reason'],
                    'last_sync_time' => time(),
                    'createtime' => time(),
                ];
            
                $problemMdl->save($problem);
            }
        
            $data['problem_id'] = $problem['problem_id'];
        }

        // custom 修复未匹配归档字段的问题
        if (isset($sdf['archive'])) {
            $data['archive'] = $sdf['archive'];
        }
        
        //平台退货金额大于OMS订单已支付金额,通过销售单明细重新获取退货金额
        if(isset($sdf['order']['payed']) && $sdf['order']['payed'] < $data['refundmoney']){
            //目前只用于天猫平台(购物金导致退货单金额自动审核不通过)
            if(in_array($sdf['shop_type'], ['taobao','tmall'])){
                $result = kernel::single('ome_return_product')->resetReturnProductSdf($data);
                if($result['rsp'] == 'succ' && isset($result['data'])){
                    //重置数据
                    $data = $result['data'];
                    
                    //flag
                    $data['is_reset_flag'] = true;
                }else{
                    //flag
                    $data['is_reset_flag'] = false;
                    $data['reset_error_msg'] = $result['error_msg'];
                }
            }
        }
        
        return $data;
    }
    
    private function _insertReturnProductItems($returnProductItems, $returnId) {
        if(empty($returnId) || empty($returnProductItems)) {
            return false;
        }
        foreach($returnProductItems as &$val) {
            $val['return_id'] = $returnId;
        }
        $modelItem = app::get('ome')->model('return_product_items');
        $sql = ome_func::get_insert_sql($modelItem, $returnProductItems);
        $rs = $modelItem->db->exec($sql);
        return $rs['rs'];
    }

    private function _returnProductUpdateStatus($sdf) {
        $operateLog = app::get('ome')->model('operation_log');
        $modelReturnProduct = app::get('ome')->model('return_product');
        $reshipObj = app::get('ome')->model('reship');
        
        $returnProduct = $sdf['return_product'];
        switch($sdf['status']) {
            case '1':
                $upData = $this->_returnProductSdfToData($sdf);
                $returnProductItems = $upData['return_product_items'];
                if(empty($returnProductItems)) {
                    return array('rsp'=>'fail', 'msg'=>'重置售后申请单失败:商品明细为空');
                }
                unset($upData['return_product_items']);
                
                //商家拒绝退款后重置,不用更新售后申请单状态
                if($returnProduct['platform_status'] == 'SELLER_REFUSE_BUYER'){
                    unset($upData['status']);
                }
                
                kernel::database()->beginTransaction();//开启事务，防并发，京东并发导致退货明细多了一个
                
                $modelReturnProduct->update($upData, array('return_id'=>$returnProduct['return_id']));
                
                app::get('ome')->model('return_product_items')->delete(array('return_id'=>$returnProduct['return_id']));
                
                $this->_insertReturnProductItems($returnProductItems, $returnProduct['return_id']);
                $operateLog->write_log('return@ome', $returnProduct['return_id'], '退款原因、金额或版本变化，重置售后申请单');
                
                if($sdf['reship']) {
                    
                    //[抖音平台&&京东一件代发]拒绝原退换货单,并且重新生成退货单&&自动审核退货单
                    $branchLib = kernel::single('ome_branch');
                    $wms_type = $branchLib->getNodetypBybranchId($sdf['branch_id']);
                    if($sdf['shop_type']=='luban' && $wms_type == 'yjdf'){
                        $lubanLib = kernel::single('ome_reship_luban');
                        
                        //作废原退换货单
                        $result = $lubanLib->yjdfTransformReturn($sdf);
                        if($result['rsp'] == 'succ'){
                            
                            //清空已拒绝退货单
                            $sdf['reship'] = array();
                            
                            //售后申请单自动审批(系统-->退换货自动审核设置-->是否启用售后申请单自动审批)
                            $is_auto_approve = app::get('ome')->getConf('aftersale.auto_approve');
                            if ($is_auto_approve == 'on' && in_array($sdf['status'], array('1'))) {
                                $sdf['status'] = '3';
                            }
                            
                            //自动创建退货单
                            if(in_array($sdf['status'], array('3'))){
                                $this->_returnProductUpdateStatus($sdf);
                            }
                        }else{
                            //请求WMS取消退货单失败
                        }
                        
                    }else{
                        $this->cleanReturnStatus($sdf['reship']);
                    }
                }elseif($returnProduct['platform_status'] == 'SELLER_REFUSE_BUYER'){
                    //更新平台售后状态
                    $updateSdf = array(
                        'platform_status' => $sdf['platform_status'],
                    );
                    $reshipObj->update($updateSdf, array('reship_id'=>$sdf['reship']['reship_id']));
                }
                
                kernel::database()->commit();
                break;
            case '3':
                $sdf['return_product']['status'] = $sdf['status'];
                $upData = array('status'=>$sdf['status'],'last_modified'=>time());
                
                //平台售后单状态
                if($sdf['platform_status']){
                    $upData['platform_status'] = $sdf['platform_status'];
                    $upData['is_modify'] = 'true';
                }
                
                $result = $modelReturnProduct->update($upData, array('return_id'=>$returnProduct['return_id'], 'status|noequal'=>$sdf['status']));
                if(!is_bool($result)) {
                    $this->_dealReship($sdf);
                    $operateLog->write_log('return@ome', $returnProduct['return_id'], '线上状态为3，生成退货单');
                }
                break;
            case '4':
                if($returnProduct['status'] == '4') {
                    break;
                }
                $msg = '';
                if(!$sdf['reship']) {
                    if ($sdf['attribute']){
                        $has_jsrefund = strstr($sdf['attribute'],'agreeReturnAuto:1;');
                        if($has_jsrefund) {
                            $sdf['jsrefund_flag'] = 'true';
                            $msg = ',极速退款生成退货单';
                        }
                    }
                    $this->_dealReship($sdf);
                } else  {
                    if (!$sdf['reship']['return_logi_no']) {
                        $this->_updateReshipLogistics($sdf);
                    }
                    if($sdf['platform_status']){
                        //更新平台售后状态
                        $updateSdf = array(
                            'platform_status' => $sdf['platform_status'],
                        );
                        $reshipObj->update($updateSdf, array('reship_id'=>$sdf['reship']['reship_id']));
                    }
                }
                
                $upData = array('status'=>'4');
                
                //平台售后单状态
                if($sdf['platform_status']){
                    $upData['platform_status'] = $sdf['platform_status'];
                    $upData['is_modify'] = 'false'; //完成后,设置为false
                }
                if($sdf['real_refund_amount']) {
                    $upData['real_refund_amount'] = $sdf['real_refund_amount'];
                }
                
                $rs = $modelReturnProduct->update($upData, array('return_id'=>$returnProduct['return_id'], 'status|noequal'=>'4'));
                if(is_bool($rs)) {
                    break;
                }
                $operateLog->write_log('return@ome', $returnProduct['return_id'],'线上已完成,请进行收货/质检等操作'.$msg);
                $reship = $reshipObj->db_dump(['return_id'=>$returnProduct['return_id']], 'reship_id,is_check,return_type');
                $reship_id = $reship['reship_id'];
                if(empty($reship_id)) {
                    break;
                }
                if($sdf['shop']['delivery_mode'] == 'jingxiao' || $sdf['shop']['config']['return_refund_oms_return_complete'] == 'enable') {;
                    if($reship['is_check'] == '0') {
                        $reshipObj->update(['is_check'=>'1'], ['reship_id'=>$reship_id]);
                        $sql = 'update sdb_ome_reship_items set normal_num = num where reship_id="'.$reship_id.'" and return_type="return"';
                        $reshipObj->db->exec($sql);
                    }
                    if ($reshipObj->finish_aftersale($reship_id)) {
                        kernel::single('console_reship')->siso_iostockReship($reship_id);
                    }
                }
                // 和上面的if换一下位置，保证查询ome_refund_apply的时候有数据
                $aftersales_set = app::get('ome')->getConf('ome.aftersales.auto_finish');
                if($aftersales_set == 'refunded'){
                    kernel::single('sales_aftersale')->generate_aftersale($reship['reship_id'],$reship['return_type'], '3');
                }
                break;
            case '5':
                //检查退货单是否已经接收到WMS退货结果
                if(isset($sdf['reship'])) {
                    if(in_array($sdf['reship']['is_check'], array('7','11','12','14'))) {
                        //平台售后单状态
                        if($sdf['platform_status'] != $returnProduct['platform_status']){
                            $updateReturnData = [
                                'platform_status' => $sdf['platform_status'],
                                'is_modify' => 'true',
                            ];
                            $modelReturnProduct->update($updateReturnData, array('return_id'=>$returnProduct['return_id']));
                            
                            //更新平台售后状态
                            $updateReshipSdf = array(
                                'platform_status' => $sdf['platform_status'],
                                'is_modify' => 'true',
                            );
                            $reshipObj->update($updateReshipSdf, array('reship_id'=>$sdf['reship']['reship_id']));
                        }
                        
                        $operateLog->write_log('reship@ome', $sdf['reship']['reship_id'], '前端拒绝失败：WMS已经返回退货结果');
                        break;
                    }
                }
                
                $data = array(
                    'status'    => $sdf['status'],
                    'return_id' => $returnProduct['return_id'],
                    'outer_lastmodify' => $sdf['modified'],
                );
                
                //平台售后单状态
                if($sdf['platform_status']){
                    $data['platform_status'] = $sdf['platform_status'];
                    $data['is_modify'] = 'true';
                }
                $error_msg = '';
                $modelReturnProduct->tosave($data, true, $error_msg);
                kernel::single('ome_return_product')->releaseChangeFreeze($returnProduct['return_id']);
                // 同步拒绝退货单
                if ($sdf['reship']){
                    $reship = $sdf['reship'];
                    
                    //取消退货包裹信息
                    $branchLib = kernel::single('ome_branch');
                    $wms_type = $branchLib->getNodetypBybranchId($reship['branch_id']);
                    if($wms_type == 'yjdf'){
                        //还原京东订单号(包裹号)申请退货数量
                        $reshipLib = kernel::single('ome_reship');
                        $error_msg = '';
                        $cancelPackage = $reshipLib->cancel_reship_package($reship, $error_msg);
                    }
                    
                    //平台售后单状态
                    $is_update_platform_status = false;
                    if($sdf['platform_status'] && $reship['platform_status'] != $sdf['platform_status']){
                        $is_update_platform_status = true;
                    }
                    
                    //请求WMS拒绝退货单
                    $rs = console_reship::notice($reship);
                    if ($rs['rsp'] == 'succ'){
                        $updateSdf = array(
                            'is_check' => '5',
                            'platform_status' => $sdf['platform_status'],
                            't_end' => time(),
                        );
                        $reshipObj->update($updateSdf, array('reship_id'=>$reship['reship_id']));
                        kernel::single('console_reship')->releaseChangeFreeze($reship['reship_id']);
                    }elseif($is_update_platform_status){
                        //更新平台售后单状态(并标记异常)
                        $updateSdf = array(
                            'platform_status' => $sdf['platform_status'],
                            'return_abnormal' => 'platform_close',
                        );
                        $reshipObj->update($updateSdf, array('reship_id'=>$reship['reship_id']));
                    }
                    
                    $msg = '';
                    if ($rs['rsp'] == 'fail'){
                        $msg = " 请求WMS取消失败，返回:".$rs['msg'];
                    }
                    
                    $operateLog->write_log('reship@ome',$reship['reship_id'],'前端拒绝'.$msg);
                    
                }
                break;
            case '6':
                $is_auto_confirm = false;
                if(!$sdf['reship']) {
                    $this->_dealReship($sdf);
                    $operateLog->write_log('return@ome', $returnProduct['return_id'], '线上状态为6，生成退货单');
                } else {
                    $updateLogiResult = $this->_updateReshipLogistics($sdf);
                    
                    //自动审核标记
                    if($sdf['reship']['is_check'] == '0'){
                        $is_auto_confirm = true;
                    }elseif($updateLogiResult && $sdf['reship']['reship_id']){
                        //请求WMS更新最新物流单号
                        $error_msg = '';
                        kernel::single('ome_reship')->request_wms_returnorder($sdf['reship']['reship_id'], $error_msg);
                    }
                }
                
                //更新平台售后状态
                if($sdf['platform_status']){
                    $updateSdf = array(
                        'status' => $sdf['status'],
                        'platform_status' => $sdf['platform_status'],
                        'outer_lastmodify' => $sdf['modified'],
                        'last_modified' => time(),
                    );
                    $modelReturnProduct->update($updateSdf, array('return_id'=>$returnProduct['return_id']));
                }
                
                //退换货自动审批(系统-->退换货自动审核设置-->是否启用退换货自动审批)
                $is_auto_approve = app::get('ome')->getConf('return.auto_approve');
                if($is_auto_approve == 'on' && $is_auto_confirm){
                    $reshipLib = kernel::single('ome_reship');
                    $reshipLib->batch_reship_queue($sdf['reship']['reship_id']);
                }
                
                break;
            case '10':
                //卖家拒绝退款
                $updateSdf = array(
                    //'status' => $sdf['status'], //@todo：只更新平台售后状态,不用更新OMS单据状态;
                    'platform_status' => $sdf['platform_status'],
                    'outer_lastmodify' => $sdf['modified'],
                    'last_modified' => time(),
                );
                $modelReturnProduct->update($updateSdf, array('return_id'=>$returnProduct['return_id']));
                
                //logs
                $operateLog->write_log('return@ome', $returnProduct['return_id'], '卖家在平台上拒绝退款');
                
                //更新OMS退货单
                if($sdf['reship']) {
                    $this->_dealReship($sdf);
                }
                break;
            default:
                break;
        }
    }

    private function _updateReturnProductLogistics($returnId, $logisticsCompany, $logisticsNo,$sdf = array()) {
        if($returnId && $logisticsCompany && $logisticsNo) {
            $logisticsInfo = array(
                'shipcompany' => $logisticsCompany,
                'logino' => $logisticsNo,
            );
            $rData = array(
                'process_data' => serialize($logisticsInfo)
            );
            $memo = '更新物流公司:' . $logisticsCompany . ',物流单号:' . $logisticsNo;
            if (isset($sdf['exchange_to_return']) && $sdf['exchange_to_return'] && $sdf['return_product']['return_type'] == 'change') {
                $rData['return_type'] = 'return';
                $rData['kinds']       = 'reship';
                $rData['money']       = $sdf['refund_fee'];
                $memo .= '，换货转退货删除换货明细。';
            }
            $rs = app::get('ome')->model('return_product')->update($rData, array('return_id' => $returnId));
            
            $operateLog = app::get('ome')->model('operation_log');
            $operateLog->write_log('return@ome', $returnId, $memo);
            return $rs;
        }
        return false;
    }

    private function _updateReshipLogistics($sdf){
        $reship = $sdf['reship'];
        $logisticsCompany = $sdf['logistics_company'];
        $logisticsNo = $sdf['logistics_no'];
        if ($reship  && $logisticsNo) {
            $memo ='更新物流公司：'.$logisticsCompany.',物流单号：'.$logisticsNo;
            $upData = array(
                'return_logi_name'=>$logisticsCompany,
                'return_logi_no'=>$logisticsNo,
                'outer_lastmodify'=>$sdf['modified'],
            );
            
            //平台售后单状态
            if($sdf['platform_status']){
                $upData['platform_status'] = $sdf['platform_status'];
                $upData['is_modify'] = 'true';
            }
            //换货转退货更新类型
            if (isset($sdf['exchange_to_return']) && $sdf['exchange_to_return'] && $sdf['reship']['return_type'] == 'change') {
                $upData['return_type'] = 'return';
                $upData['totalmoney'] = $sdf['refund_fee'];
            }else{
                $sdf['exchange_to_return'] = false;
            }
            
            $rs = app::get('ome')->model('reship')->update($upData,array('reship_id'=>$reship['reship_id']));
            if(is_bool($rs)) {
                return false;
            }
            //换转退删除reship明细
            if ($sdf['exchange_to_return']) {
                app::get('ome')->model('reship_items')->delete(['reship_id'=>$reship['reship_id'],'return_type'=>'change']);
                $memo .= '，换货转退货删除换货明细。';
            }
            $operateLog = app::get('ome')->model('operation_log');
            $operateLog->write_log('reship@ome',$reship['reship_id'],$memo);
            $this->_updateReturnProductLogistics($reship['return_id'], $logisticsCompany, $logisticsNo,$sdf);
            
            return $rs;
        }
        return false;
    }

    private function _dealReship($sdf) {
        $modelReship = app::get('ome')->model('reship');
        $returnProductObj = app::get('ome')->model('return_product');
        $operateLog = app::get('ome')->model('operation_log');
        
        $reshipLib = kernel::single('ome_reship');
        $branchLib = kernel::single('ome_branch');
        
        $error_msg = '';
        if($sdf['reship']) {
            $is_update_logistics = false;
            $cancelReshipFLag = false;
            $is_update_platform_status = false;
            
            $msg = '仅处理未审核、拒绝状态和物流信息';
            if($sdf['reship']['shipcompany'] != $sdf['logistics_company'] || $sdf['reship']['return_logi_no'] != $sdf['logistics_no']) {
                if($this->_updateReshipLogistics($sdf)) {
                    $msg = '更新物流信息成功';
                    
                    $is_update_logistics = true;
                }
            }
            
            //是否更新平台售后状态
            if($sdf['platform_status'] && $sdf['reship']['platform_status'] != $sdf['platform_status']){
                $is_update_platform_status = true;
            }
            if($sdf['reship']['jsrefund_flag']!=$sdf['jsrefund_flag']){
                $is_update_platform_status = true;
            }
            //拒绝
            if ($sdf['status'] == '5') {
                if ($sdf['reship']['is_check']>0) {
                    return array('rsp'=>'fail', '本地退货单非未审核状态，不处理拒绝状态');
                }else{
                    $upData = array('is_check'=>'5');
                    
                    //平台售后单状态
                    if($sdf['platform_status']){
                        $upData['platform_status'] = $sdf['platform_status'];
                        //$upData['is_modify'] = 'false'; //拒绝后,编辑标识设置为false
                        
                        //是否更新平台售后状态
                        $is_update_platform_status = false;
                    }
                    
                    $rs = $modelReship->update($upData, array('reship_id'=>$sdf['reship']['reship_id'], 'is_check|noequal'=>'5'));
                    if(is_bool($rs)) {
                        $msg = '退货单已经被拒绝';
                    } else {
                        $msg = '退货单更新为拒绝状态';
                        $memo = '状态:拒绝';
                        $operateLog->write_log('reship@ome', $sdf['reship']['reship_id'], $memo);
                        
                        // 退货单取消成功通知
                        $reshipInfo = app::get('ome')->model('reship')->dump($sdf['reship']['reship_id'], 'reship_bn,branch_id');
                        if ($reshipInfo) {
                            kernel::single('ome_reship')->sendReshipCancelSuccessNotify($reshipInfo, $memo);
                        }
                        
                        kernel::single('console_reship')->releaseChangeFreeze($sdf['reship']['reship_id']);
                        if ($sdf['return_product']) {
                            $returnId = $sdf['return_product']['return_id'];
                            $rpData = array('status' => '5', 'last_modified' => time());
                            $returnProductObj->update($rpData, array('return_id'=>$returnId));
                            
                            //logs
                            $operateLog->write_log('return@ome', $returnId, $memo);
                        }
                    }
                }
            }elseif($is_update_logistics){
                //退换货自动审批(系统-->退换货自动审核设置-->是否启用退换货自动审批)
                $is_auto_approve = app::get('ome')->getConf('return.auto_approve');
                if($is_auto_approve == 'on' && $cancelReshipFLag !== true){
                    $result = $reshipLib->batch_reship_queue($sdf['reship']['reship_id']);
                }
                
                //请求WMS更新顾客退货物流单号
                if(in_array($sdf['reship']['is_check'], array('1'))){
                    $branchLib = kernel::single('ome_branch');
                    
                    //order_id
                    $order_id = $sdf['order']['order_id'];
                    
                    //wms_type
                    $wms_type = '';
                    if($sdf['reship']['branch_id']){
                        $wms_type = $branchLib->getNodetypBybranchId($sdf['reship']['branch_id']);
                    }
                    
                    //[京东云交易]更新顾客退货物流单号
                    if($wms_type == 'yjdf'){
                        $queueObj = app::get('base')->model('queue');
                        
                        //放入queue队列中执行
                        $queueData = array(
                            'queue_title' => '退货单：'. $sdf['reship']['reship_bn'] .'自动更新京东云交易退货物流信息',
                            'start_time' => time(),
                            'params' => array(
                                'sdfdata' => array('reship_id'=>$sdf['reship']['reship_id'], 'order_id'=>$order_id),
                                'app' => 'oms',
                                'mdl' => 'reship',
                            ),
                            'worker' => 'ome_reship_kepler.syncLogisticInfo',
                        );
                        $queueObj->save($queueData);
                    }else{
                        //请求WMS更新最新物流单号
                        $error_msg = '';
                        kernel::single('ome_reship')->request_wms_returnorder($sdf['reship']['reship_id'], $error_msg);
                    }
                }
            }
            
            //更新平台售后状态
            if($is_update_platform_status){
                $updateSdf = array('platform_status'=>$sdf['platform_status']);
                if($sdf['jsrefund_flag']){
                    $updateSdf['jsrefund_flag'] = $sdf['jsrefund_flag'];
                }
                $modelReship->update($updateSdf, array('reship_id'=>$sdf['reship']['reship_id']));
                
                //logs
                $operateLog->write_log('reship@ome', $sdf['reship']['reship_id'], '更新平台售后状态('. $sdf['reship']['platform_status'] .'-->'. $sdf['platform_status'] .')');
            }
            
            //[售后申请单]更新平台售后状态
            if($sdf['response_bill_type'] == 'reship' && $is_update_platform_status && $sdf['return_product']['return_id']){
                //sdf
                $updateSdf = array(
                    'platform_status' => $sdf['platform_status'],
                    'outer_lastmodify' => $sdf['modified'],
                    'last_modified' => time(),
                );
                
                //update
                $returnProductObj->update($updateSdf, array('return_id'=>$sdf['return_product']['return_id']));
            }
            
        } else {
            $insertData = $this->_reshipSdfToData($sdf);
            if($insertData['reship_items']) {
                $reshipItems = $insertData['reship_items'];
                unset($insertData['reship_items']);
            }
            if ($insertData['reship_gift_items']) {
                $reshipGiftItems = $insertData['reship_gift_items'];
                unset($insertData['reship_gift_items']);
            }
            $rs = $modelReship->insert($insertData);
            if(!$rs) {
                return array('rsp'=>'succ', 'msg'=>'退货单新建失败');
            }
            
            $this->_insertReshipItems($reshipItems, $insertData['reship_id']);
            
            $operateLog->write_log('reship@ome',$insertData['reship_id'], '新建退货单');
            if ($sdf['return_product']['return_type']=='jdchange') {
                kernel::single('ome_return_product')->releaseChangeFreeze($insertData['return_id']);

                $result = kernel::single('console_reship')->addChangeFreeze($insertData['reship_id'], $error_msg);
                if(!$result){
                    //log
                    $operateLog->write_log('reship@ome', $insertData['reship_id'], '换货预占库存失败:'. $error_msg);
                }
            }
            if($sdf['return_product']['status'] < 3) {
                $returnProductObj->update(array('status' => '3'), array('return_id' => $sdf['return_product']['return_id']));
                $operateLog->write_log('return@ome', $sdf['return_product']['return_id'], '由于退货单下载,售后单不为已接受更新为已接受');
            }
            
            $this->_updateReturnProductLogistics($sdf['return_product']['return_id'], $sdf['logistics_company'], $sdf['logistics_no'],$sdf);
            
            //极速退款打标在扩展表
            if($sdf['jsrefund_flag'] == 'true'){
               
                $modelReship->db->exec("UPDATE sdb_ome_return_product_tmall set jsrefund_flag='true' WHERE return_bn='".$sdf['return_product']['return_bn']."'");
            }
            $msg = '新建退货单';
            
            //[京东一件代发]保存退货单与京东包裹关系明细
            $wms_type = $branchLib->getNodetypBybranchId($insertData['branch_id']);
            if($wms_type == 'yjdf'){
                $error_msg = '';
                $result = $reshipLib->create_reship_package($insertData, $error_msg);
                if(!$result){
                    $operateLog->write_log('reship@ome', $insertData['reship_id'], '创建退货包裹失败：'. $error_msg);
                }
                
                //必须有京东寄件地址,才能推送同意状态给抖音平台
                $sdf['sync_platform'] = false;
            }
            
            //[抖音平台]推送同意退货状态给平台
            if($sdf['sync_platform'] && in_array($insertData['shop_type'], array('luban'))){
                $lubanLib = kernel::single('ome_reship_luban');
                
                $error_msg = '';
                $result = $lubanLib->syncAgreeReturn($insertData, $error_msg);
            }
            
            //退换货自动审批(系统-->退换货自动审核设置-->是否启用退换货自动审批)
            $is_auto_approve = app::get('ome')->getConf('return.auto_approve');
            if($is_auto_approve == 'on'
                || ($sdf['flag_type'] & ome_reship_const::__LANJIE_RUKU)
            ){
                $result = $reshipLib->batch_reship_queue($insertData['reship_id']);
            }
        }
        
        return array('rsp'=>'succ', 'msg'=>$msg);
    }

    private function _reshipSdfToData($sdf) {
        $tgOrder = $sdf['order'];
        $returnProduct = $sdf['return_product'];
        $opInfo = kernel::single('ome_func')->get_system();
        
        //退换货单号
        $reship_bn = $sdf['refund_bn'];
        
        //原退货单已经作废,需要生成一个新的退货单,防止WMS无法接收相同退货单号
        //场景：顾客申请退货，商家在天猫后台拒绝退货退款;顾客上传退货凭证后,平台自动同意退货申请,恢复原退货单；
        if($sdf['cancel_reship_bn'] && $tgOrder['order_id']){
            $reshipObj = app::get('ome')->model('reship');
            $oldReshipInfo = $reshipObj->dump(array('reship_bn'=>$sdf['cancel_reship_bn'], 'order_id'=>$tgOrder['order_id']), 'reship_id');
            if($oldReshipInfo){
                $reship_bn = $reshipObj->gen_id();
            }
        }
        
        //data
        $data = array(
            'reship_bn'     => $reship_bn,
            'shop_id'       => $sdf['shop_id'],
            'order_id'      => $tgOrder['order_id'],
            'delivery_id'   => $returnProduct['delivery_id'],
            'member_id'     => $tgOrder['member_id'],
            'logi_name'     => $sdf['logi_name'],
            'logi_no'       => $tgOrder['logi_no'],
            'logi_id'       => $tgOrder['logi_id'],
            'ship_name'     => $tgOrder['ship_name'],
            'ship_area'     => $tgOrder['ship_area'],
            'delivery'      => $tgOrder['shipping'],
            'ship_addr'     => $tgOrder['ship_addr'],
            'ship_zip'      => $tgOrder['ship_zip'],
            'ship_tel'      => $tgOrder['ship_tel'],
            'ship_email'    => $tgOrder['ship_email'],
            'ship_mobile'   => $tgOrder['ship_mobile'],
            'is_protect'    => $tgOrder['is_protect'],
            'return_id'     => $returnProduct['return_id'],
            'return_logi_name' => $sdf['logistics_company'],
            'return_logi_no' => $sdf['logistics_no'],
            'return_freight' => $sdf['return_freight'],
            'outer_lastmodify' => $sdf['modified'],
            'source'        => 'matrix',
            't_begin'       => $sdf['created'],
            'op_id'         => $opInfo['op_id'],
            'is_check'      => in_array($sdf['status'], array('0', '5')) ? $sdf['status'] : '0',
            'branch_id'     => $sdf['branch_id'],
            'org_id'        => $sdf['org_id'],
            'flag_type'     => $sdf['flag_type'],
            'platform_status' => $sdf['platform_status'], //平台售后单状态
            'shop_type'       => $sdf['shop_type'], //店铺类型
            // 'reason'        => $sdf['reason'],
            'jsrefund_flag'   => ($sdf['jsrefund_flag'] ? $sdf['jsrefund_flag'] : '0'), //极速退款标识
            'refund_shipping_fee'   => $sdf['refund_shipping_fee'] ?: 0, //退运费金额
        );
        
        //组织架构ID
        if(isset($sdf['cos_id'])){
            $data['cos_id'] = $sdf['cos_id'];
        }
        
        //贸易公司ID
        if(isset($sdf['betc_id'])){
            $data['betc_id'] = $sdf['betc_id'];
        }
        
        if (isset($sdf['archive'])) {
            $data['archive'] = $sdf['archive'];
        }
        
        if($sdf['refund_to_returnProduct']) {
            $data['flag_type'] = $data['flag_type'] | ome_reship_const::__LANJIE_RUKU;
        }
        
        //平台订单号
        if($sdf['platform_order_bn']){
            $data['platform_order_bn'] = $sdf['platform_order_bn'];
        }
        if($sdf['return_product']['changebranch_id']) {
            $data['changebranch_id'] = $sdf['return_product']['changebranch_id'];
        }
        //delivery_mode
        if($sdf['shop']['delivery_mode'] == 'jingxiao') {
            $data['is_check'] = '1';

            // 经销店铺的单据，delivery_mode冗余到售后申请表
            $data['delivery_mode'] = $sdf['shop']['delivery_mode'];
        }
        
        if ($sdf['reason']) {
            $problemMdl = app::get('ome')->model('return_product_problem');

            $problem = $problemMdl->db_dump(['problem_name' => $sdf['reason']]);
            if (!$problem) {
                $problem = [
                    'problem_name' => $sdf['reason'],
                    'last_sync_time' => time(),
                    'createtime' => time(),
                ];

                $problemMdl->save($problem);
            }

            $data['problem_id'] = $problem['problem_id'];
        }


        if($sdf['refund_item_list']) {
            foreach ($sdf['refund_item_list'] as $item ) {
                $data['reship_items'][] = array(
                    'op_id'  => $opInfo['op_id'],
                    'bn'     => $item['bn'],
                    'num'    => $item['num'],
                    'normal_num' => $sdf['shop']['delivery_mode'] == 'jingxiao' ? $item['num'] : 0,
                    'price'  => $item['price'],
                    'amount'  => $item['amount'],
                    'branch_id' => $sdf['branch_id'],
                    'product_name' => $item['name'],
                    'product_id' => $item['product_id'],
                    'order_item_id'=>$item['order_item_id'],
                    'shop_goods_bn'=>$item['shop_goods_bn'],
                    'obj_type'  =>$item['obj_type'],
                    'quantity'  =>$item['quantity'],
                    'oid'  =>$item['oid'],
                    'sku_uuid'=>$item['sku_uuid'],

                );
            }
            
            //退货单增加赠品明细
            // $data['reship_gift_items'] = app::get('ome')->model('reship')->addReturnGiftItems($data['reship_items'],$data['order_id'],$sdf['branch_id'],$opInfo['op_id']);
            $data['tmoney'] = $returnProduct['money'];
            $data['totalmoney'] = $returnProduct['money'];//总计应退金额
        }

        return $data;
    }

    private function _insertReshipItems($reshipItems, $reshipId) {
        if(empty($reshipId) || empty($reshipItems)) {
            return false;
        }
        foreach($reshipItems as &$val) {
            $val['reship_id'] = $reshipId;
        }
        $modelItem = app::get('ome')->model('reship_items');
        $sql = ome_func::get_insert_sql($modelItem, $reshipItems);
        $rs = $modelItem->db->exec($sql);
        return $rs['rs'];
    }

    #清空本地状态和已生成单据
    /**
     * cleanReturnStatus
     * @param mixed $reship reship
     * @return mixed 返回值
     */
    public function cleanReturnStatus($reship){
        $return_id = (int) $reship['return_id'];
        $reship_id = (int) $reship['reship_id'];
        $oReship = app::get('ome')->model('reship');
        $oOperation_log = app::get('ome')->model('operation_log');//写日志
        $oReship->db->exec('DELETE FROM sdb_ome_reship WHERE reship_id='.$reship_id);
        $oReship->db->exec('DELETE FROM sdb_ome_reship_items WHERE reship_id='.$reship_id);
        $oReship->db->exec('DELETE FROM sdb_ome_return_process WHERE reship_id='.$reship_id);
        $oReship->db->exec('DELETE FROM sdb_ome_return_process_items WHERE reship_id='.$reship_id);
        $memo = '退款原因、金额或版本变化,已生成退货单' . $reship['reship_bn'] . '清除';
        $oOperation_log->write_log('return@ome',$return_id,$memo);
    }

    //识别是否天猫开启AG，如果开启的做标记接口请求
    private function _noticeAg($sdf){
        //取当前订单的处理状态
        // $orderObj = app::get('ome')->model('orders');
        // $order_filter = array("order_id"=>$sdf['order']['order_id']);
        // $order_detail = $orderObj->dump($order_filter, 'order_bn,process_status,source');

        // $aliag_status = app::get('ome')->getConf('shop.aliag.config.'.$sdf['shop_id']);
        // if($aliag_status && in_array($sdf['shop_type'], array ('tmall','360buy','luban')) && $sdf['status'] == 0 && $order_detail['source'] == 'matrix'){
        //     //识别是否开启AG并且是天猫订单的新建退款申请
        //     $params = array(
        //         'order_bn'              => $order_detail['order_bn'],
        //         'apply_id'              => $sdf['apply_id'],
        //         'refund_bn'             => $sdf['refund_apply_bn'],
        //         'is_aftersale_refund'   => false,
        //         'shop_id'               => $sdf['shop_id'],
        //         'oid'                   => implode(',',array_column((array)$sdf['refund_item_list'], 'oid')),
        //     );

        //     //检查当前订单的状态
        //     if(in_array($order_detail['process_status'],array('unconfirmed','confirmed'))){
        //         $params['cancel_dly_status'] = 'SUCCESS';
        //     }else{
        //         $params['cancel_dly_status'] = 'FAIL';
        //     }

        //     kernel::single('ome_service_refund')->refund_request($params);
        // }

        $logi = ['trigger_event' => 'refund_apply'];

        // 判断是否有一件代发包裹
        $packageMdl          = app::get('ome')->model('delivery_package');
        $deliveryOrderMdl    = app::get('ome')->model('delivery_order');
        $deliveryMdl         = app::get('ome')->model('delivery');
        $branchMdl           = app::get('ome')->model('branch');
        
        $autoAgRefund = true; //自动AG退款
        
        //获取发货单
        $delivery_list = $deliveryMdl->getDeliversByOrderId($sdf['order']['order_id']);
        if ($delivery_list) {
            $node_type = $branchMdl->getChannelBybranchID($delivery_list[0]['branch_id']);

            // 发货单不做AG处理
            if ($node_type == 'yjdf' && $sdf['refund_refer'] != 'aftersale') {
                return false;
            }

            $logi['is_yjdf'] = $node_type == 'yjdf' ? true : false;
            $logi['logi_status'] = $delivery_list[0]['logi_status'];

            // 验证一个子单是否按数量拆包裹
            $bn     = array_column((array)$sdf['refund_item_list'], 'bn');
            $did    = array_column((array)$delivery_list, 'delivery_id');

            $package_list = $packageMdl->getList('logi_bn,logi_no', ['delivery_id' => $did, 'bn' => $bn, 'status' => 'delivery']);

            if (1 == count($package_list)) {
                $logi['company_code'] = $package_list ? $package_list[0]['logi_bn'] : '';
                $logi['logistics_no'] = $package_list ? $package_list[0]['logi_no'] : '';
            }
        }
        
        //订单已发货&&售后仅退款
        if($delivery_list && $sdf['response_bill_type'] == 'refund_apply' && $sdf['shop_id']){
            $shopObj = app::get('ome')->model('shop');
            $shopInfo = $shopObj->dump(array('shop_id'=>$sdf['shop_id']), 'shop_id,shop_bn,shop_type,config');
            
            //是否手动审核仅退款的单据
            $shopConfig = unserialize($shopInfo['config']);
            if($shopConfig['audo_refund_refuse'] == 'confirm'){
                $autoAgRefund = false;
            }elseif(empty($shopConfig['audo_refund_refuse']) || $shopConfig['audo_refund_refuse'] == 'retry_refuse'){
                //@todo场景：售后仅退款OMS拒绝后,顾客又重新申请;
                if(in_array($shopInfo['shop_type'], array('luban'))){
                    $lubanLib = kernel::single('ome_reship_luban');
                    $oldApplyInfo = ($sdf['refund_apply'] ? $sdf['refund_apply'] : array());
                    $autoAgRefund = $lubanLib->getAutoRefuse($sdf['refund_apply_bn'], $oldApplyInfo);
                }
            }
        }
        
        if ($sdf['refund_type'] == 'return' && in_array($sdf['order']['ship_status'],array('1'))){
            //手动审核售后仅退款
            if(!$autoAgRefund){
                return true;
            }
        }
        
        if($autoAgRefund){
            kernel::single('ome_refund_apply')->refund_ag($sdf['refund_apply_bn'], $logi);
        }
    }
    
    /**
     * 平台推送的已退款单,需要编辑订单删除退款的商品
     * 
     * @param $sdf 平台推送的退款数据
     * @param $error_msg 错误信息
     * @param $is_abnormal 是否为异常(订单已生成发货单,平台已退款但撤消发货单失败,导致删除订单退款商品失败)
     * @return bool
     */
    public function _autoEditorder($sdf, &$error_msg=null, &$is_abnormal=false)
    {

        $orderObj = app::get('ome')->model('orders');
        $orderObjMdl = app::get('ome')->model('order_objects');
        $orderItemObj = app::get('ome')->model("order_items");
        $logObj = app::get('ome')->model('operation_log');
        
        $refundLib = kernel::single('ome_order_refund');
        
        //orderInfo
        $order_id = $sdf['order']['order_id'];
        $order_filter = array('order_id'=>$order_id);
        $order_detail = $orderObj->dump($order_filter, '*');
        
        //check支持的平台
        if(!in_array($order_detail['shop_type'], array('taobao','tmall','luban','website_d1m','website','weimobv','youzan','website_v2','pinduoduo','wxshipin','meituan4sg'))){
            $error_msg = $order_detail['shop_type'] . '平台订单不支持编辑';
            return false;
        }
        
        if($order_detail['source'] != 'matrix'){
            $error_msg = '订单来源不是matrix类型';
            return false;
        }
        
        //check
//        if ($order_detail['pay_status'] == '5'){
//            $error_msg = '订单已经全额退款,无需编辑';
//            return false;
//        }
        
        if(!in_array($order_detail['ship_status'], array('0','2'))){
            $error_msg = '订单发货状态ship_status='. $order_detail['ship_status'] .',不允许编辑订单';
            return false;
        }
        
        //天猫只退款价保金额时,不编辑订单明细
        if($sdf['isPriceProtect']){
            $error_msg = '只退款价保金额,不允许编辑订单';
            return false;
        }
        
        //check过滤不需要删除订单明细的退款申请备注
        $reason = $sdf['reason'];
        if($reason){
            $reasonList = $refundLib->setReasonTypes();
            if(in_array($reason, $reasonList)){
                $error_msg = '退款原因：'. $reason .',不允许编辑订单';
                return false;
            }
        }
        
        //编辑订单失败时,更新异常状态
        $new_abnormal_status = $order_detail['abnormal_status'] | ome_preprocess_const::__ORDER_REFUND_ABNORMAL;
        
        //判断订单是否编辑过
        $item_list = $orderObj->getItemBranchStore($order_id);
        
        //格式化订单结构数据扩展信息
        ome_order_func::order_sdf_extend($item_list);
        
        //增加事务避免并发导致订单为部分退款状态
        $trans = kernel::database()->beginTransaction();
        //先更新订单主信息， 避免行明细更新导致死锁
        $update_sql = "UPDATE sdb_ome_orders SET createtime=`createtime` WHERE order_id=". $order_id;
        $orderObj->db->exec($update_sql);
        $status = app::get('ome')->model('orders')->db_dump(['order_id'=>$order_id], 'status')['status'];
        if($status == 'dead') {
            kernel::database()->rollBack();
            $error_msg = '订单已经取消不能再编辑';
            return false;
        }
        $refund_item_list = $sdf['refund_item_list'];
        
        if ($refund_item_list && is_array($refund_item_list)) {
            $oid_refund_item_list = [];
            foreach ($refund_item_list as $val) {
                if (isset($val['oid'])) {
                    $oid_refund_item_list[$val['oid']][] = $val;
                }
            }
            $refund_item_list = $oid_refund_item_list ?: $refund_item_list;
        }

        //items
        $edit_flag = false;
        $needChangeFreezeItem = [];
        foreach($item_list as $itemKey => $items)
        {
            foreach($items as $item)
            {

                $refund_items = $refund_item_list[$item['oid']] ? $refund_item_list[$item['oid']] : array();
                if($item['oid'] != $sdf['oid'] && !$refund_items){
                    continue;
                }
                
                
                //天猫权益金
                if ($sdf['refund_fee'] != $item['divide_order_fee'] && $sdf['tmall_mcard_pz_sp']){
                    $sdf['refund_fee'] = $sdf['refund_fee'] + $sdf['tmall_mcard_pz_sp'];
                }
                
                //加上价保退款的金额
                if ($sdf['refund_fee'] != $item['divide_order_fee'] && $sdf['isPriceProtect'] !== true && $item['refund_money']){
                    $sdf['refund_fee'] = $sdf['refund_fee'] + $item['refund_money'];
                }
    
                if ($sdf['shop_type'] == 'website' && $refund_items) {
                    $refund_fee = array_sum(array_column($refund_items,'amount'));
                    $sdf['refund_fee'] = number_format($refund_fee, 3, '.', '');
                }
                
                //check
                if ($item['delete'] == 'true'){
                    //logs
                    $error_msg = '删除商品bn：'. $item['bn'] .'失败,订单商品已经是删除状态。';
                    $logObj->write_log('order_edit@ome', $order_id, $error_msg);
                    $oObjRow = app::get('ome')->model('order_objects')->db_dump(['obj_id'=>$item['obj_id']], 'pay_status');
                    if($oObjRow && $oObjRow['pay_status'] != 5) {
                        $update_sql = "UPDATE sdb_ome_order_objects SET pay_status='5' WHERE order_id=". $order_id ." AND obj_id=". $item['obj_id'];
                        $orderObj->db->exec($update_sql);
                        $order_sql = "UPDATE sdb_ome_orders SET pmt_goods=pmt_goods-".$item['pmt_price'].", pmt_order=pmt_order-".($item['part_mjz_discount']?:0).", cost_item=cost_item-".$item['amount'];
                        $order_sql .= ", total_amount=total_amount-".$sdf['refund_fee'].", final_amount=final_amount-".$sdf['refund_fee']." WHERE order_id=". $order_id;
                        $orderObj->db->exec($order_sql);
                        $edit_flag = true;
                    }
                    continue;
                }
                
                //check退款金额是否与订单金额相等
                if(($sdf['refund_fee'] != $item['divide_order_fee'] && $sdf['refund_fee'] != $item['sale_price'])){
                    //logs
                    $error_msg = '删除商品bn：'. $item['bn'] .'失败,退款金额与订单商品金额不匹配';
                    $logObj->write_log('order_edit@ome', $order_id, $error_msg);
                    
                    //加标签
                    //
                    if($item['order_items']){
                        foreach($item['order_items'] as $order_item)
                        {
                            if($order_item['split_num'] > 0){
                                $label_code = 'SOMS_MREFUND';
                                $err = '';
                                kernel::single('ome_bill_label')->markBillLabel($order_id, '', $label_code, 'order', $err, 0);
                            }   
                        }
                    }
                    continue;
                }
                
                //check订单商品明细
                if($item['order_items']){
                    foreach($item['order_items'] as $order_item)
                    {
                        //check退款商品已经发货完成
                        if($order_item['sendnum'] > 0){
                            //update order_objects
                            $update_sql = "UPDATE sdb_ome_order_objects SET pay_status='5' WHERE order_id=". $order_id ." AND obj_id=". $item['obj_id'];
                            $orderObj->db->exec($update_sql);
                            
                            //秒退标签
                           
                            
                            //更新订单为异常状态
                            if(!$is_abnormal){
                                $order_sql = "UPDATE sdb_ome_orders SET abnormal_status=". $new_abnormal_status ." WHERE order_id=". $order_id;
                                $orderObj->db->exec($order_sql);
                            }
                            
                            //logs
                            $error_msg = '退款商品bn：'. $item['bn'] .'已经发货完成,无法删除!';
                            $logObj->write_log('order_edit@ome', $order_id, $error_msg);
                            
                            //异常标记
                            $is_abnormal = true;
                            
                            continue 2;
                        }
                        
                        //check退款商品已经生成发货单,发货单打回失败
                        if($order_item['split_num'] > 0){
                            //update order_objects
                            $update_sql = "UPDATE sdb_ome_order_objects SET pay_status='5' WHERE order_id=". $order_id ." AND obj_id=". $item['obj_id'];
                            $orderObj->db->exec($update_sql);
                            $label_code = 'SOMS_MREFUND';
                            $err = '';
                            kernel::single('ome_bill_label')->markBillLabel($order_id, '', $label_code, 'order', $err, 0);
                            //更新订单为异常状态
                            if(!$is_abnormal){
                                $order_sql = "UPDATE sdb_ome_orders SET abnormal_status=". $new_abnormal_status ." WHERE order_id=". $order_id;
                                $orderObj->db->exec($order_sql);
                            }
                            
                            //logs
                            $error_msg = '退款商品bn：'. $item['bn'] .'已经拆分生成发货单,无法删除!';
                            $logObj->write_log('order_edit@ome', $order_id, $error_msg);
                            
                            //异常标记
                            $is_abnormal = true;
                            
                            continue 2;
                        }
                    }
                }
                
                //优惠分摊金额
                $item['part_mjz_discount'] = $item['part_mjz_discount'] ? $item['part_mjz_discount'] : 0;
                
                //删除订单object层商品
                $delete_sql = "UPDATE sdb_ome_order_objects SET `delete`='true', pay_status='5' WHERE order_id=". $order_id ." AND obj_id=". $item['obj_id']." AND `delete`='false'";

                $affect_row = $orderObj->db->exec($delete_sql);

                
                if ($orderObj->db->affect_row() > 0){
                  
                    $edit_flag = true;
                    $item['part_mjz_discount']= $item['part_mjz_discount'] ? $item['part_mjz_discount'] : 0;
                    
                    //删除item层货品
                    //$delete_sql = "UPDATE sdb_ome_order_items SET `delete`='true' WHERE order_id=". $order_id ." AND obj_id=". $item['obj_id'];
                    //$affect_row = $orderObj->db->exec($delete_sql);
                    $affect_row = $orderItemObj->update(array('delete'=>'true'), array('order_id'=>$order_id, 'obj_id'=>$item['obj_id']));
                   
                    //删除商品后释放冻结库存(有更新影响行数,才需要释放库存)
                    //@todo：现在矩阵同分同秒推送退款完成单和更新订单时，会导致并发重复释放库存冻结;
                    if (!is_bool($affect_row) && !empty($item['order_items'])) {
                        foreach ($item['order_items'] as $order_item)
                        {
                            if (isset($order_item['delete']) && $order_item['delete'] == 'true') {
                                continue;
                            }
                            $order_item['goods_id'] = $item['goods_id'];
                            $needChangeFreezeItem[] = $order_item;
                        }
                    }else{
                        //logs
                        $error_msg = '删除订单item层商品bn：'. $item['bn'] .'失败';
                        $logObj->write_log('order_edit@ome', $order_id, $error_msg);
                    }
                }else{
                    //logs
                    $error_msg = '删除订单object层商品bn：'. $item['bn'] .'失败';
                    $logObj->write_log('order_edit@ome', $order_id, $error_msg);
                    continue;
                }

                
                //更新订单金额
                $order_sql = "UPDATE sdb_ome_orders SET pmt_goods=pmt_goods-".$item['pmt_price'].", pmt_order=pmt_order-".($item['part_mjz_discount']?:0).", cost_item=cost_item-".$item['amount'];
                $order_sql .= ", total_amount=total_amount-".$sdf['refund_fee'].", final_amount=final_amount-".$sdf['refund_fee']." WHERE order_id=". $order_id;
       
                $orderObj->db->exec($order_sql);
                
                // custom 根据子订单id查询是否有关联赠品
                $this->__deletePlatformGift($item['oid'], $order_id, $needChangeFreezeItem);
            }
        }
        if($needChangeFreezeItem) {
            uasort($needChangeFreezeItem, [kernel::single('console_iostockorder'), 'cmp_productid']);
            
            $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
            // 预占释放
            $branchBatchList = [];
            foreach($needChangeFreezeItem as $item) {

                $branchBatchList[] = [
                    'bm_id'     =>  $item['product_id'],
                    'sm_id'     =>  $item['goods_id'],
                    'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                    'bill_type' =>  0,
                    'obj_id'    =>  $order_id,
                    'branch_id' =>  '',
                    'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                    'num'       =>  abs($item['nums']), 
                ];
            }

            $rs = $basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
            if(!$rs) {
                kernel::database()->rollBack();
                $logObj->write_log('order_edit@ome', $order_id, '明细删除失败：'.$err);
                return false;
            }
        }
        
        //提交事务
        kernel::database()->commit($trans);
        
        //编辑订单成功
        if ($edit_flag){
            $objectInof = $orderObjMdl->getList('name,quantity as num', array('order_id' => $order_id,'delete'=>'false'));
            if($objectInof){
                $tostr=json_encode($objectInof);
                $orderObj->update(['tostr'=>$tostr], ['order_id'=>$order_id]);
            }
            
            //重置状态
            if($order_detail['process_status'] == 'splitting') {
                $unSplitNum = app::get('ome')->model('delivery')->countOrderSplitNumber($order_detail['order_id']);
                if($unSplitNum == 0) {
                    $orderObj->update(['process_status'=>'splited'], ['order_id'=>$order_id]);
                }
            }
            
            //log
            $logObj->write_log('order_edit@ome',$order_id,"订单修改并恢复");
            
            //余单撤销
            if ($order_detail['ship_status'] == '2') {
                $unShipNum = app::get('ome')->model('delivery')->countOrderSendNumber($order_detail['order_id']);
                if($unShipNum == 0) {
                    kernel::single('ome_order_order')->order_revoke($order_detail['order_id'], '', '');
                }
            }
            
            //将未修改以前的数据存储以便查询
            $log_id = $logObj->getList('log_id',array('operation'=>'order_edit@ome','obj_id'=>$order_id),0,1,'log_id DESC');
            $log_id = $log_id[0]['log_id'];
            $order_detail['item_list'] = $item_list;
            $orderObj->write_log_detail($log_id,$order_detail);
            
            //更新订单支付状态
            kernel::single('ome_order_func')->update_order_pay_status($order_id,true, __CLASS__.'::'.__FUNCTION__);
            
            //发票
            kernel::single('invoice_order_front')->updateItemsByOrder($order_id, 'b2c');
            
            //编辑订单删除退款商品后,重新调用赠品规则
            $this->_dealCrmGift($order_id);
            
            //延迟5分钟自动重新路由审核订单
            $sdf = array('op_type'=>'timing_confirm', 'timing_time'=>strtotime('5 minutes'), 'memo'=>'退款完成编辑订单后重新路由');
            kernel::single('ome_order')->auto_order_combine($order_id, $sdf);
        }else{
            //订单退款商品无法删除,如有CRM赠品则打标记
            $this->_labelOrderCrmGift($order_id);
        }
        
        //订单编辑失败：返回false,这样同步日志会有error_msg信息
        if($is_abnormal){
            return false;
        }
        
        return true;
    }

    /**
     * 更新退款费用
     * @param  sdf
     * @return bool
     * sunjing@shopex.cn
     */
    public function _updateProtectPrice($sdf){

        $orderObj = app::get('ome')->model('orders');
        $order_filter = array("order_id"=>$sdf['order']['order_id']);
        $order_id = $sdf['order']['order_id'];
        $order_detail = $orderObj->dump($order_filter, '*');
        
        if ($order_detail['pay_status'] == '5') return true;//全额退款时不用编辑
        
        if( $order_detail['source'] == 'matrix'){
            $sql ="update sdb_ome_orders set refund_money=refund_money+".$sdf['refund_fee']." where order_id=".$order_id;

            kernel::database()->exec($sql);

            $item_list = $orderObj->getItemBranchStore($sdf['order']['order_id']);
            ome_order_func::order_sdf_extend($item_list);
            $refund_item_list = $sdf['refund_item_list'][0];

            foreach($item_list as $k=>$items){

                foreach($items as $item){

                    if (($item['oid'] == $sdf['oid'])){
                       
                        $orderObj->db->exec("UPDATE sdb_ome_order_objects SET refund_money=refund_money+".$sdf['refund_fee'].",pay_status='4' WHERE order_id=".$order_id." AND obj_id=".$item['obj_id']." AND `delete`='false'");

                        if($item['obj_type'] == 'goods'){
                            foreach($item['order_items'] as $order_item){

                                if ($order_item['delete'] == 'false'){

                                    $orderObj->db->exec("UPDATE sdb_ome_order_items set refund_money=refund_money+".$sdf['refund_fee']." WHERE order_id=".$order_id." AND item_id=".$order_item['item_id']." AND `delete`='false'");
                                   
                                }
                            }
                        }   
                    }
                }
            }

        }

    }
    
    /**
     * 订单中售前退款完成,编辑订单删除退款商品后,重新调用赠品规则
     * 
     * @param $order_id
     * @return void
     */
    public function _dealCrmGift($order_id)
    {
        $orderItemObj   = app::get('ome')->model("order_items");
        $orderObjectObj = app::get('ome')->model("order_objects");
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        $Obj_preprocess = app::get('ome')->model('order_preprocess');
        $operLogObj = app::get('ome')->model('operation_log');
        
        $labelLib = kernel::single('ome_bill_label');
        
        $err = '';

        //增加事务避免并发导致订单为部分退款状态
        $trans = kernel::database()->beginTransaction();
        //先更新订单主信息， 避免行明细更新导致死锁
        $update_sql = "UPDATE sdb_ome_orders SET createtime=`createtime` WHERE order_id=". $order_id;
        $orderItemObj->db->exec($update_sql);
        
        //order
        $orderInfo = app::get('ome')->model('orders')->db_dump($order_id, 'order_bn,shop_id,process_status,status');
        
        //订单已经是取消状态
        if($orderInfo['process_status'] == 'cancel' || $orderInfo['status'] == 'dead'){
            kernel::database()->rollBack();
            return false;
        }
        
        //获取CRM赠品
        $items = $orderItemObj->getList('item_id,obj_id,product_id,bn,nums as quantity,split_num', ['order_id'=>$order_id, 'shop_goods_id'=>'-1', 'item_type'=>'gift', 'delete'=>'false']);
        if(empty($items)) {
            kernel::database()->rollBack();
            return false;
        }

        // objects
        $objects = $orderObjectObj->getList('order_id,obj_id,goods_id,bn', ['order_id'=>$order_id]);
        $objects = array_column($objects, null, 'obj_id');
        
        //check
        $error_msg = '';
        foreach($items as $value)
        {
            //赠品已经生成发货单
            if($value['split_num'] > 0) {
                //订单打标记
                // $labelLib->deleteOrderGiftLable($order_id, $error_msg);
                $labelLib->markBillLabel($order_id, '', 'deleteordergift', 'order', $error_msg);
                
                //logs
                kernel::database()->rollBack();
                $operLogObj->write_log('order_preprocess@ome', $order_id, '赠品：'. $value['bn'] .' 删除失败，已经生成发货单不能删除;');
                return false;
            }
        }

        //CRM赠品明细
        $item_ids = [];
        $obj_ids = [];
        $product_bns = [];
        $branchBatchList = [];
        foreach($items as $tmp_item)
        {
            $product_id = $tmp_item['product_id'];
            $goods_id = $objects[$tmp_item['obj_id']]['goods_id'];
            $quantity = $tmp_item['quantity'];
    
            $product_bns[] = $tmp_item['bn'];
            $item_ids[] = $tmp_item['item_id'];
            $obj_ids[] = $tmp_item['obj_id'];
            
            $branchBatchList[] = [
                'bm_id'     =>  $product_id,
                'sm_id'     =>  $goods_id,
                'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                'bill_type' =>  0,
                'obj_id'    =>  $order_id,
                'branch_id' =>  0,
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $quantity,
            ];
        }
        
        //删除CRM相关记录记录(shop_goods_id=-1是， CRM赠品类型)
        $orderItemObj->delete(array('item_id'=>$item_ids));
        $affectRow = $orderItemObj->db->affect_row();
        
        //只有当删除有影响行数时，才释放赠品冻结，避免并发时重复释放
        if($affectRow > 0) {
            //释放赠品冻结
            $rs = $basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
            if(!$rs) {
                kernel::database()->rollBack();
                $operLogObj->write_log('order_preprocess@ome', $order_id, '赠品冻结释放失败：'.$err);
                return false;
            }
        }
        
        $orderObjectObj->delete(array('obj_id'=>$obj_ids));
        $Obj_preprocess->delete(array('preprocess_order_id'=>$order_id, 'preprocess_type'=>'crm'));
        
        //删除赠品赠送记录
        if ($orderInfo) {
            $giftLogMdl = app::get('ome')->model('gift_logs');
            $giftLogMdl->delete([
                'order_bn' => $orderInfo['order_bn'],
                'shop_id' => $orderInfo['shop_id'],
            ]);
        }
        
        //logs
        $operLogObj->write_log('order_preprocess@ome', $order_id, '赠品：'. implode(',', $product_bns) .'删除成功');
        
        //重新获取CRM赠品
        $msg = '';
        kernel::single('ome_preprocess_crm')->process($order_id,$msg,1);
        
        kernel::database()->commit($trans);
        return true;
    }
    
    /**
     * 订单退款商品无法删除,如有CRM赠品则打标记
     * 
     * @param $order_id
     * @return void
     */
    public function _labelOrderCrmGift($order_id)
    {
        $orderItemObj   = app::get('ome')->model("order_items");
        $operLogObj = app::get('ome')->model('operation_log');
    
        $labelLib = kernel::single('ome_bill_label');
        
        //获取CRM赠品
        $items = $orderItemObj->getList('item_id,obj_id,product_id,bn,split_num', ['order_id'=>$order_id, 'shop_goods_id'=>'-1', 'item_type'=>'gift', 'delete'=>'false']);
        if(empty($items)) {
            return false;
        }
        
        //check
        $error_msg = '';
        foreach($items as $value)
        {
            //赠品已经生成发货单
            if($value['split_num'] > 0) {
                //订单打标记
                // $labelLib->deleteOrderGiftLable($order_id, $error_msg);
                $labelLib->markBillLabel($order_id, '', 'deleteordergift', 'order', $error_msg);
                
                //logs
                $operLogObj->write_log('order_preprocess@ome', $order_id, '赠品：'. $value['bn'] .' 删除失败，已经生成发货单不能删除;');
                return true;
            }
        }
        
        return false;
    }
    /**
     * 删除平台下发赠品
     * @param $oid
     * @param $order_id
     * @return void
     */
    public function __deletePlatformGift($oid, $order_id, &$needChangeFreezeItem)
    {
        $orderObjectObj = app::get('ome')->model('order_objects');
        $orderItemObj = app::get('ome')->model("order_items");

        // 查询销售子订单所关联的赠品子订单
        $giftFilter = [
            'order_id' => $order_id,
            'main_oid|findinset' => $oid,
            // 'obj_type' => 'gift',
            // 'shop_goods_id|than' => 0,
            'delete' => 'false',
            'sale_price' => '0',
        ];
        $giftOrderObjects = $orderObjectObj->getList('obj_id,main_oid,part_mjz_discount,divide_order_fee,amount,pmt_price', $giftFilter);

        if (empty($giftOrderObjects)) {
            return;
        }

        // 不为空,则逐个循环,检查关联销售子订单是否都已删除
        foreach ($giftOrderObjects as $giftOrderObject) {
            // check1 子订单明细已审单,则不处理
            $splitGiftOrderObjectItemFilter = [
                'order_id' => $order_id,
                'obj_id' => $giftOrderObject['obj_id'],
                'split_num|than' => 0,
            ];
            $splitGiftOrderObjectItemCount = $orderItemObj->count($splitGiftOrderObjectItemFilter);

            if ($splitGiftOrderObjectItemCount > 0) {
                continue;
            }

            // check2 赠品还存在未删除的关联销售子订单,则不处理
            /* $giftMainFilter = [
                'order_id' => $order_id,
                'oid|in' => explode(',', $giftOrderObject['main_oid']),
                'delete' => 'false',
            ];
            $giftOrderMainObjCount = $orderObjectObj->count($giftMainFilter);

            // 还存在未删除销售子订单,则不处理
            if ($giftOrderMainObjCount > 0) {
                continue;
            } */

            // 获取明细
            $giftOrderObjItemsFilter = [
                'order_id' => $order_id,
                'obj_id' => $giftOrderObject['obj_id'],
                'delete' => 'false',
            ];
            $giftOrderObjItems = $orderItemObj->getList('item_id,product_id,nums', $giftOrderObjItemsFilter);

            // 删除子订单层
            $affect_row = $orderObjectObj->db->exec("UPDATE sdb_ome_order_objects SET `delete`='true',pay_status='5' WHERE order_id=" . $order_id . " AND obj_id=" . $giftOrderObject['obj_id'] . " AND `delete`='false'");

            if (!$orderObjectObj->db->affect_row()) {
                // todo 是否要报警
                continue;
            }

            // 删除订单明细层
            foreach ($giftOrderObjItems as $giftOrderObjItem) {
                $affect_row = $orderItemObj->db->exec("UPDATE sdb_ome_order_items set `delete`='true' WHERE order_id=" . $order_id . " AND item_id=" . $giftOrderObjItem['item_id'] . " AND `delete`='false'");
                if (!$orderItemObj->db->affect_row()) {
                    // todo 是否要报警
                    continue;
                }
                $needChangeFreezeItem[] = $giftOrderObjItem;
            }

            // 订单金额处理
            $giftOrderObject['part_mjz_discount'] = $giftOrderObject['part_mjz_discount'] ? $giftOrderObject['part_mjz_discount'] : 0;
            $giftOrderObject['divide_order_fee'] = $giftOrderObject['divide_order_fee'] ? $giftOrderObject['divide_order_fee'] : 0;
            $giftOrderObject['amount'] = $giftOrderObject['amount'] ? $giftOrderObject['amount'] : 0;
            $giftOrderObject['pmt_price'] =  $giftOrderObject['pmt_price'] ?  $giftOrderObject['pmt_price'] : 0;

            $sql = "update sdb_ome_orders set pmt_goods=pmt_goods-" . $giftOrderObject['pmt_price'] . ",pmt_order=pmt_order-" .($giftOrderObject['part_mjz_discount']?:0). ",cost_item=cost_item-" . $giftOrderObject['amount'] . ",total_amount=total_amount-" . $giftOrderObject['divide_order_fee'] . ",final_amount=final_amount-" . $giftOrderObject['divide_order_fee'] . "  where order_id=" . $order_id;
            kernel::database()->exec($sql);
        }
    }
    
    /**
     * 退款未退货报表
     * @param $sdf
     * @param $refundApply
     * @return bool
     * @date 2024-07-02 10:40 上午
     */
    public function _refundNoreturn($sdf,$refundApply)
    {
        //需要退货 或 售后仅退款不退货
        if(!$sdf['has_good_return'] || $sdf['refund_refer'] == 'aftersale'){
            return true;
        }
        if(!isset($sdf['refund_item_list'])){
            return true;
        }
        //经销发货不需要进退款未退货报表
        if (isset($sdf['shop']['delivery_mode']) && $sdf['shop']['delivery_mode'] == 'jingxiao') {
            return true;
        }
        //退款单下来时 通过商品检测 该商品是否已发货，若已发货就创建报表
        $refundItemList = $sdf['refund_item_list'];
        $errMsg = [];
        kernel::single('ome_refund_noreturn')->checkRefundNoreturn($refundItemList,[$refundApply['apply_id']],$errMsg);
        return true;
    }

    /**
     * 保存ChangeItems
     * @param mixed $sdf sdf
     * @param mixed $returnId ID
     * @return mixed 返回操作结果
     */
    public function saveChangeItems($sdf, $returnId) {
        $shop_id = $sdf['shop_id'];
        $branch_id = $sdf['branch_id'];
        $refund_items = $sdf['refund_item_list'];
       
        $exchange_bn = $sdf['extend_field']['exchange_bn'];
       
        if(empty($returnId) || empty($exchange_bn)) {
          
            return false;
        }
        $exchange_num = $refund_items[0]['num'];   
        $tmall_detail = [
            'refund_type' => 'change',
            'exchange_sku' => $exchange_bn,
            'exchange_num' => $exchange_num
        ];
        kernel::single('ome_return_product')->addExchangeFreeze($returnId, $branch_id, $tmall_detail);
        $salesMLib = kernel::single('material_sales_material');
        $salesMInfo = $salesMLib->getSalesMByBn($sdf['shop_id'],$exchange_bn);

        $basicMInfos = $salesMLib->getBasicMBySalesMId($salesMInfo['sm_id']);
        $items = array();

        if($basicMInfos){
            foreach($basicMInfos as $k => $basicMInfo){
                $items[] = array(
                    'bm_id'         => $basicMInfo['bm_id'],
                    'material_name' => $basicMInfo['material_name'],
                    'material_bn'   => $basicMInfo['material_bn'],
                    'type'          => $basicMInfo['type'],
                    'number'        => $basicMInfo['number']*$exchange_num,
                    'change_num'    => $basicMInfo['number']*$exchange_num,
                    'sales_material_bn' =>  $exchange_bn,
                    'branch_id'     =>  $branch_id,
                );
            }
        }

        $change_items = array();
        if($items){
            foreach($items as $v){
                $change_items[] = array(
                    'shop_id'           =>  $shop_id,
                    'return_id'         =>  $returnId,
                    'num'               =>  $v['change_num'],
                    'product_id'        =>  $v['bm_id'],
                    'name'              =>  $v['material_name'],
                    'bn'                =>  $v['material_bn'],
                    'sales_material_bn' =>  $exchange_bn,
                );

            }

        }
        
        if($change_items){

            $modelItem = app::get('ome')->model('change_items');
            $sql = ome_func::get_insert_sql($modelItem, $change_items);

            $rs = $modelItem->db->exec($sql);
           

        }

    }
    
    /**
     * 更新物流信息
     * @param $params
     * @return string[]
     * @date 2024-10-08 7:41 下午
     */
    public function logisticsUpdate($params)
    {
        $processData = array_merge((array)$params['old_process_data'], (array)$params['process_data']);
        app::get('ome')->model('return_product')->update(array('process_data' => serialize($processData)), array('return_id' => $params['return_id']));
        
        $obj_reship = app::get('ome')->model('reship');
        $rs         = $obj_reship->count(array('return_id' => $params['return_id']));
        if ($rs > 0) {
            $_data['return_logi_name'] = $params['process_data']['shipcompany'];
            $_data['return_logi_no']   = $params['process_data']['logino'];
            $obj_reship->update($_data, array('return_id' => $params['return_id']));
        }
        return array('rsp' => 'succ', 'msg' => '物流信息更新成功');
    }
    
    /**
     * 宝尊小程序换后在退会包含原单运费 特殊处理
     * @param $sdf
     * @param $refundApply
     * @return array
     * @date 2024-12-16 5:35 下午
     */
    public function _upOriginalOrder($sdf, $refundApply)
    {
        $order_bn          = $sdf['order_bn'];//换出订单号
        $shop_id           = $sdf['shop_id'];
        $order_id          = $sdf['order']['order_id'];
        $platform_order_bn = $sdf['order']['platform_order_bn'];//平台订单号
        $orderMdl          = app::get('ome')->model('orders');
        if ($order_bn == $platform_order_bn) {
            return [false, '不符合换了在退场景！'];
        }
        $refundInfo = app::get('ome')->model('refund_apply')->db_dump(['apply_id' => $refundApply['apply_id'], 'shop_id' => $shop_id], 'apply_id,return_id');
        $returnInfo = app::get('ome')->model('return_product')->db_dump(['return_id' => $refundInfo['return_id'], 'shop_id' => $shop_id], 'return_id,refund_shipping_fee');
        if (!$returnInfo) {
            return [false, '售后单不存在！'];
        }
        if ($returnInfo['refund_shipping_fee'] <= 0) {
            return [false, '平台未退运费！'];
        }
        
        $refund_shipping_fee = $returnInfo['refund_shipping_fee'];
        $order               = $orderMdl->db_dump(array('order_bn' => $platform_order_bn, 'shop_id' => $shop_id), 'order_id,order_bn,platform_order_bn,cost_freight,payed');
        if (!$order) {
            return [false, '平台订单不存在！'];
        }
        if ($order['cost_freight'] <= 0 || $order['payed'] < $refund_shipping_fee) {
            return [false, '没有可退运费！'];
        }
        if ($this->_updateOrderPayed($order['order_id'], $refund_shipping_fee, true)) {
            $opLogModel = app::get('ome')->model('operation_log');
            $systemMsg  = sprintf("换出订单%s退款成功，退款包含平台订单运费。系统自动操作，扣除平台订单%s运费%s元。", $order_bn, $platform_order_bn, $refund_shipping_fee);
            $opLogModel->write_log('order_modify@ome', $order_id, $systemMsg);//换出订单操作日志
            $opLogModel->write_log('order_modify@ome', $order['order_id'], $systemMsg);//原单操作日志
            $opLogModel->write_log('refund_apply@ome', $refundApply['apply_id'], $systemMsg);//换出订单退款单操作日志
        }
        return [true, '运费扣除完成'];
    }



}
