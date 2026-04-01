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
 * 抖音售后业务处理Lib类
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class ome_reship_luban
{
    /**
     * 推送抖音平台同意退货状态
     * 
     * @param array $data
     * @return boolean
     */

    public function syncAgreeReturn($data, &$error_msg=null)
    {
        $operLogObj = app::get('ome')->model('operation_log');
        
        //check
        if(empty($data['return_id'])){
            $error_msg = '没有获取到售后申请单ID';
            return false;
        }
        
        //request
        $result = kernel::single('ome_service_aftersale')->update_status($data['return_id']);
        if($result['rsp'] == 'fail'){
            $log_msg = '自动推送平台同意状态失败：'.$result['msg'];
        }else{
            $log_msg = '自动推送平台同意状态成功';
        }
        
        //logs
        $operLogObj->write_log('return@ome', $data['return_id'], $log_msg);
        
        return true;
    }
    
    /**
     * 生成作废的售后申请单号
     */
    public function _cancelReturnBn($return_bn)
    {
        $cancel_return_bn = $return_bn.'-'.date('His');
        
        return $cancel_return_bn;
    }
    
    /**
     * 报废原售后申请单
     * 
     * @param array $returnInfo
     * @param string $cancel_return_bn
     * @return bool
     */
    public function _scrapReturnProduct($returnInfo, $cancel_return_bn)
    {
        $returnProductObj = app::get('ome')->model('return_product');
        $operateLog = app::get('ome')->model('operation_log');
        
        //sql
        $update_sql = "UPDATE sdb_ome_return_product SET return_bn='". $cancel_return_bn ."',status='5' WHERE return_bn='". $returnInfo['return_bn'] ."'";
        $returnProductObj->db->exec($update_sql);

        //[售后申请单]取消售后申请单,释放冻结库存
        if($returnInfo['return_type'] == 'change'){
            //释放冻结库存
            kernel::single('ome_return_product')->releaseChangeFreeze($returnInfo['return_id']);
        }
        
        //log
        $operateLog->write_log('return@ome', $returnInfo['return_id'], '[平台更换售后类型]并再次申请,自动拒绝售后申请单：'.$returnInfo['return_bn']);
        
        return true;
    }
    
    /**
     * 报废原退换货单
     * 
     * @param array $reshipInfo
     * @param string $cancel_return_bn
     * @return bool
     */
    public function _scrapReship($reshipInfo, $cancel_return_bn)
    {
        $returnProductObj = app::get('ome')->model('return_product');
        $operateLog = app::get('ome')->model('operation_log');
        
        //sql
        $update_sql = "UPDATE sdb_ome_reship SET reship_bn='". $cancel_return_bn ."',is_check='5',status='cancel' WHERE reship_bn='". $reshipInfo['reship_bn'] ."'";
        $returnProductObj->db->exec($update_sql);
        
        //[换货单]取消换货单,释放冻结库存
        if($reshipInfo['return_type'] == 'change'){
            //释放冻结库存
            kernel::single('console_reship')->releaseChangeFreeze($reshipInfo['reship_id']);
        }
        // 退货单取消后的service扩展点
        foreach(kernel::servicelist('console.service.reship.cancel.after') as $object) {
            if(method_exists($object, 'cancel_reship_after')) {
                $object->cancel_reship_after($reshipInfo['reship_id']);
            }
        }
        //log
        $operateLog->write_log('reship@ome', $reshipInfo['reship_id'], '[平台更换售后类型]并再次申请,自动拒绝退换货单：'.$reshipInfo['reship_bn']);
        
        return true;
    }
    
    /**
     * 报废原退款申请单
     * 
     * @param array $reshipInfo
     * @param string $cancel_return_bn
     * @return bool
     */
    public function _scrapRefundApply($applyInfo, $cancel_return_bn)
    {
        $returnProductObj = app::get('ome')->model('return_product');
        $operateLog = app::get('ome')->model('operation_log');
        
        $order_id = $applyInfo['order_id'];
        
        //sql
        $update_sql = "UPDATE sdb_ome_refund_apply SET refund_apply_bn='". $cancel_return_bn ."',status='3' WHERE refund_apply_bn='". $applyInfo['refund_apply_bn'] ."'";
        $returnProductObj->db->exec($update_sql);
        
        //更新订单支付状态(不需要打回发货单和暂停订单)
        kernel::single('ome_order_func')->update_order_pay_status($order_id, false, __CLASS__.'::'.__FUNCTION__);
        
        //log
        $operateLog->write_log('refund_apply@ome', $applyInfo['apply_id'], '[平台更换售后类型]并再次申请退换货,自动拒绝原退款申请单：'.$applyInfo['refund_apply_bn']);
        
        return true;
    }
    
    /**
     * [抖音]"退货"修改为"仅退款"类型时
     * @todo：OMS自动拒绝原退货单,并且创建退款申请单
     * 
     * @param array $data 换货参数
     * @return array
     */
    public function transformRefundApply($data)
    {
        $returnProductObj = app::get('ome')->model('return_product');
        $operateLog = app::get('ome')->model('operation_log');
        
        $result = array('rsp'=>'fail', 'action'=>'', 'error_msg'=>'');
        
        //退款申请单号
        $return_bn = ($data['refund_apply_bn'] ? $data['refund_apply_bn'] : $data['refund_bn']);
        if(empty($return_bn)){
            //$result['error_msg'] = '没有获取到退款申请单信息';
            $result['rsp'] = 'succ';
            return $result;
        }
        
        //售后申请单信息
        $returnProduct = $returnProductObj->dump(array('return_bn'=>$return_bn), '*');
        if(empty($returnProduct)){
            $result['rsp'] = 'succ';
            return $result;
        }
        
        //生成作废的售后申请单号
        $cancel_return_bn = $this->_cancelReturnBn($return_bn);
        
        //售后申请单已完成或已拒绝
        if(in_array($returnProduct['status'], array('4','5'))){
            $result['rsp'] = 'succ';
            return $result;
        }
        
        //退换货单信息
        $reshipObj = app::get('ome')->model('reship');
        $reshipInfo = $reshipObj->dump(array('return_id'=>$returnProduct['return_id']), '*');
        if(empty($reshipInfo)){
            
            //报废售后申请单
            $this->_scrapReturnProduct($returnProduct, $cancel_return_bn);
            
            $result['rsp'] = 'succ';
            return $result;
        }
        
        //退货单已审核,拒绝仅退款
        if(!in_array($reshipInfo['is_check'], array('0','2'))){
            $applyLib = kernel::single('ome_service_refund_apply');
            if (method_exists($applyLib, 'update_status')) {
                $requestData = array(
                        'refuse_message' => '订单有退货申请单并且已审核,拒绝仅退款',
                        'apply_id' => $data['apply_id'],
                );
                
                $rs = $applyLib->update_status($requestData, 3, 'sync');
                if($rs['rsp'] == 'succ') {
                    $operateLog->write_log('refund_refuse@ome', $data['apply_id'], $requestData['refuse_message'].'成功');
                    
                    $result['rsp'] = 'succ';
                }else{
                    $operateLog->write_log('refund_refuse@ome', $data['apply_id'], $requestData['refuse_message'].'失败');
                    
                    $result['rsp'] = 'fail';
                }
            }
            
            $result['error_msg'] = '已有退换货单并且已审核,不允许创建仅退款单';
            return $result;
        }
        
        //报废退换货单
        $this->_scrapReship($reshipInfo, $cancel_return_bn);
        
        //报废售后申请单
        $this->_scrapReturnProduct($returnProduct, $cancel_return_bn);
        
        $result['rsp'] = 'succ';
        return $result;
    }
    
    /**
     * [抖音平台]"退货"与"换货"类型切换时
     * @todo：OMS自动拒绝原退货单,并且新创建换货单
     * 
     * @param array $data 换货参数
     * @return array
     */
    public function transformExchange($data)
    {
        $returnProductObj = app::get('ome')->model('return_product');
        
        $result = array('rsp'=>'fail', 'action'=>'', 'error_msg'=>'');
        
        //售后申请单信息
        $returnProduct = $data['return_product'];
        $return_bn = $returnProduct['return_bn'];
        
        //退换货单信息
        $reshipInfo = $data['reship'];
        
        //check
        if(empty($returnProduct)){
            $result['error_msg'] = '没有获取到售后退货信息';
            
            $result['rsp'] = 'succ';
            return $result;
        }
        
        //生成作废的售后申请单号
        $cancel_return_bn = $this->_cancelReturnBn($return_bn);
        
        //没有生成退货单
        if(empty($reshipInfo)){
            //报废售后申请单
            $this->_scrapReturnProduct($returnProduct, $cancel_return_bn);
            
            $result['rsp'] = 'succ';
            $result['action'] = 'refuse_return';
            return $result;
        }
        
        //单据是拒绝状态
        if($reshipInfo['is_check'] == '5'){
            //自动取消退货单
            $this->_scrapReship($reshipInfo, $cancel_return_bn);
            
            //报废售后申请单
            $this->_scrapReturnProduct($returnProduct, $cancel_return_bn);
            
            $result['rsp'] = 'succ';
            $result['action'] = 'refuse_return';
            return $result;
        }
        
        //退货单未审核,直接拒绝，并重新创建退货单
        if(in_array($reshipInfo['is_check'], array('0','2'))){
            //报废退换货单
            $this->_scrapReship($reshipInfo, $cancel_return_bn);
            
            //报废售后申请单
            $this->_scrapReturnProduct($returnProduct, $cancel_return_bn);
            
            //action
            $result['action'] = 'refuse_return';
            $result['rsp'] = 'succ';
        }else{
            //请求WMS取消退货单
            $error_msg = '';
            $cancelRsp = $this->_cancelWmsReship($reshipInfo, $error_msg);
            if($cancelRsp){
                //报废退换货单
                $this->_scrapReship($reshipInfo, $cancel_return_bn);
                
                //报废售后申请单
                $this->_scrapReturnProduct($returnProduct, $cancel_return_bn);
                
                //action
                $result['action'] = 'refuse_return';
                $result['rsp'] = 'succ';
            }else{
                //请求WMS取消退货单失败,打标记
                $result['rsp'] = 'fail';
                $result['error_msg'] = '请求WMS取消换货单失败';
            }
        }
        
        return $result;
    }
    
    /**
     * 取消WMS退货单
     * 
     * @param array $reshipInfo
     * @param string $error_msg
     * @return boolean
     */
    public function _cancelWmsReship($reshipInfo, &$error_msg=null)
    {
        $operateLog = app::get('ome')->model('operation_log');
        
        //仓库信息
        $branchLib = kernel::single('ome_branch');
        $branchInfo = $branchLib->getBranchInfo($reshipInfo['branch_id'], 'branch_bn,wms_id,owner_code');
        if(empty($branchInfo['wms_id'])){
            return true;
        }
        
        //params
        $params = array(
                'order_id' => $reshipInfo['order_id'],
                'reship_id' => $reshipInfo['reship_id'],
                'reship_bn' => $reshipInfo['reship_bn'],
                'branch_bn' => $branchInfo['branch_bn'],
                'owner_code' => $branchInfo['owner_code'],
        );
        $res = kernel::single('console_event_trigger_reship')->cancel($branchInfo['wms_id'], $params, true);
        if($res['rsp'] != 'succ'){
            $error_msg = '售后申请单类型变更,自动取消WMS退货单失败：'. $res['err_msg'];
            
            //logs
            $operateLog->write_log('reship@ome', $reshipInfo['reship_id'], $error_msg);
            
            //设置异常
            $status = ome_constants_reship_abnormal::__TRANSFORM_RETURN_CODE;
            $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status | ". $status .",sync_msg='". $error_msg ."' WHERE reship_id=".$reshipInfo['reship_id'];
            $operateLog->db->exec($sql);
            
            return false;
        }
        
        //logs
        $log_msg = '售后申请单类型变更,自动取消WMS退货单成功';
        $operateLog->write_log('reship@ome', $reshipInfo['reship_id'], $log_msg);
        
        return true;
    }
    
    /**
     * [抖音平台]"仅退款"修改为"退货、换货"类型时
     * @todo：
     * a. 顾客申请仅退款,OMS已经有发货单,会自动拒绝仅退款;
     * b. 仅退款修改为"退换货"类型时,需要重置退款申请单号,否则退货完成创建退款申请单会失败;
     * 
     * @param array $data 退换货参数
     * @return array
     */
    public function transformReturnProduct($data)
    {
        $applyObj = app::get('ome')->model('refund_apply');
        $operateLog = app::get('ome')->model('operation_log');
        
        $result = array('rsp'=>'fail', 'action'=>'', 'error_msg'=>'');
        
        //售后申请单号
        $return_bn = $data['return_bn'];
        
        //生成作废的售后申请单号
        $cancel_return_bn = $this->_cancelReturnBn($return_bn);
        
        //退款申请单信息
        $applyInfo = $applyObj->dump(array('refund_apply_bn'=>$return_bn), '*');
        if(empty($applyInfo)){
            $result['rsp'] = 'succ';
            return $result;
        }
        
        //退款申请单已拒绝,作废退款申请单号并返回succ
        if(in_array($applyInfo['status'], array('3'))){
            
            //报废退款申请单
            $this->_scrapRefundApply($applyInfo, $cancel_return_bn);
            
            $result['rsp'] = 'succ';
            return $result;
        }
        
        //退款申请单未完成之前,作废退款申请单号并返回succ
        if(in_array($applyInfo['status'], array('0','1','2'))){
            
            //报废退款申请单
            $this->_scrapRefundApply($applyInfo, $cancel_return_bn);
            
            $result['rsp'] = 'succ';
            return $result;
        }
        
        //log
        $operateLog->write_log('refund_apply@ome', $applyInfo['apply_id'], '此单顾客再次申请退货,导致创建退货单失败');
        
        $result['error_msg'] = '已经有退款申请单,并且已同意退款';
        
        return $result;
    }
    
    /**
     * [抖音平台]"仅退款"修改为"退货、换货"类型时
     * @todo：
     * a. 顾客申请仅退款,OMS已经有发货单,会自动拒绝仅退款;
     * b. 仅退款修改为"退换货"类型时,需要重置退款申请单号,否则退货完成创建退款申请单会失败;
     * 
     * @param array $data 退换货参数
     * @return array
     */
    public function yjdfTransformReturn($data)
    {
        $returnProductObj = app::get('ome')->model('return_product');
        $operateLog = app::get('ome')->model('operation_log');
        
        $result = array('rsp'=>'fail', 'action'=>'', 'error_msg'=>'');
        
        //售后申请单号
        $return_bn = ($data['refund_bn'] ? $data['refund_bn'] : $data['return_bn']);
        
        //退换货信息
        $sql = "SELECT * FROM sdb_ome_reship WHERE reship_bn='". $return_bn ."'";
        $reshipInfo = $returnProductObj->db->selectrow($sql);
        if(empty($reshipInfo)){
            $result['rsp'] = 'succ';
            return $result;
        }
        
        //生成作废的售后申请单号
        $cancel_return_bn = $this->_cancelReturnBn($return_bn);
        
        //报废退换货单,防止创建会重复
        if($reshipInfo['is_check'] == '5'){
            $this->_scrapReship($reshipInfo, $cancel_return_bn);
            
            //action
            $result['action'] = 'refuse_return';
            $result['rsp'] = 'succ';
        }elseif(in_array($reshipInfo['is_check'], array('0','2'))){
            //报废退换货单
            $this->_scrapReship($reshipInfo, $cancel_return_bn);
            
            //action
            $result['action'] = 'refuse_return';
            $result['rsp'] = 'succ';
        }else{
            //请求WMS取消退货单
            $error_msg = '';
            $cancelRsp = $this->_cancelWmsReship($reshipInfo, $error_msg);
            if($cancelRsp){
                //报废退换货单
                $this->_scrapReship($reshipInfo, $cancel_return_bn);
                
                //action
                $result['action'] = 'refuse_return';
                $result['rsp'] = 'succ';
            }else{
                //请求WMS取消退货单失败,打标记
                $result['rsp'] = 'fail';
                
                //action
                $result['action'] = 'refuse_fail';
            }
        }
        
        return $result;
    }
    
    /**
     * 同步京东审核意见给到抖音平台
     * 
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return boolean
     */
    public function autoSyncReturnRemark(&$cursor_id, $params, &$error_msg=null)
    {
        $reshipObj = app::get('ome')->model('reship');
        $returnObj = app::get('ome')->model('return_product');
        $processObj = app::get('ome')->model('return_process');
        $operLogObj = app::get('ome')->model('operation_log');
        
        //data
        $sdfdata = $params['sdfdata'];
        $reship_id = intval($sdfdata['reship_id']);
        $reship_bn = trim($sdfdata['reship_bn']);
        
        //退货单信息
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), 'reship_id,reship_bn,shop_id,shop_type,order_id,return_id');
        if(empty($reshipInfo)){
            $error_msg = '同步京东审核意见失败：没有获取到退货单信息';
            return false;
        }
        
        if($reshipInfo['shop_type'] != 'luban'){
            $error_msg = '店铺不是抖音类型,无需同步审核意见';
            return false;
        }
        
        if(empty($reshipInfo['return_id'])){
            $error_msg = '同步京东审核意见失败：退货单没有关联的售后申请单';
            return false;
        }
        
        //售后申请单信息
        $returninfo = $returnObj->dump(array('return_id'=>$reshipInfo['return_id']), 'return_id,return_bn');
        if(empty($returninfo)){
            $error_msg = '同步京东审核意见失败：没有获取到售后申请单';
            return false;
        }
        
        $reshipInfo = array_merge($reshipInfo, $returninfo);
        
        //获取京东服务单列表
        $fields = 'por_id,reship_id,order_id,service_bn,remark';
        $processList = $processObj->getList($fields, array('reship_id'=>$reship_id));
        if(empty($processList)){
            $error_msg = '同步京东审核意见失败：没有获取到售后服务单';
            return false;
        }
        
        //循环获取售后服务单京东审核意见
        $remarkList = array();
        foreach ($processList as $key => $val)
        {
            if(empty($val['service_bn'])){
                continue;
            }
    
            if(empty($val['remark'])){
                continue;
            }
    
            $remarkList[] = sprintf("京东服务单号:%s，审核意见:%s", $val['service_bn'], $val['remark']);
        }
        
        //同步添加抖音售后单备注内容
        if($remarkList){
            $reshipInfo['remark'] = implode(';', $remarkList);
            $result = kernel::single('ome_service_aftersale')->syncReturnRemark($reshipInfo);
        }
        
        return false;
    }
    
    /**
     * [抖音平台]回传同意退货状态
     * 
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return boolean
     */
    public function syncAfterSaleStatus(&$cursor_id, $params, &$error_msg=null)
    {
        $reshipObj = app::get('ome')->model('reship');
        $operLogObj = app::get('ome')->model('operation_log');
        
        //data
        $sdfdata = $params['sdfdata'];
        $order_id = intval($sdfdata['order_id']);
        $reship_id = intval($sdfdata['reship_id']);
        
        //退货单信息
        $reshipInfo = $reshipObj->dump(array('reship_id'=>$reship_id), '*');
        $return_id = $reshipInfo['return_id'];
        if(empty($return_id)){
            //$error_msg = '退货单没有关联售后申请单';
            return false;
        }
        
        if(in_array($reshipInfo['is_check'], array('0','5'))){
            $error_msg = '退货单未审核或已拒绝,不能回传平台同意退货';
            
            //log
            $operLogObj->write_log('reship@ome', $reship_id, $error_msg);
            
            return false;
        }
        
        //获取退回寄件地址
        $return_address = app::get('ome')->model('return_address')->dump(array('reship_id'=>$reship_id), '*');
        if(empty($return_address)){
            $log_error_msg = '没有退货寄件地址,不会自动回传平台同意退货状态';
            
            //log
            $operLogObj->write_log('reship@ome', $reship_id, $log_error_msg);
            
            return false;
        }
        
        //request
        $aftersale_service = kernel::single('ome_service_aftersale');
        if(method_exists($aftersale_service, 'update_status')){
            $return = $aftersale_service->update_status($return_id, '3', 'sync');
            if($return['rsp'] == 'fail'){
                $error_msg = '回传平台同意退货状态失败：'. $return['msg'];
                
                //[设置异常]同意失败
                $abnormal_status = ome_constants_reship_abnormal::__AGREE_CODE;
                $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status | ". $abnormal_status ." WHERE reship_id=". $reship_id;
                $reshipObj->db->exec($sql);
                
                //log
                $operLogObj->write_log('reship@ome', $reship_id, $error_msg);
                
                return false;
            }else{
                //清除异常:平台同意售后单失败
                $abnormal_status = ome_constants_reship_abnormal::__AGREE_CODE;
                if(($reshipInfo['abnormal_status'] & $abnormal_status) ==  $abnormal_status){
                    $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status ^ ". $abnormal_status ." WHERE reship_id=". $reship_id;
                    $reshipObj->db->exec($sql);
                }
                
                //清除异常：京东寄件地址解析失败
                $abnormal_status = ome_constants_reship_abnormal::__ADDRESS_FAIL_CODE;
                if(($reshipInfo['abnormal_status'] & $abnormal_status) ==  $abnormal_status){
                    $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status ^ ". $abnormal_status ." WHERE reship_id=". $reship_id;
                    $reshipObj->db->exec($sql);
                }
                
                //更新寄件地址库推送成功
                $contact_id = 1; //地址库ID,固定写1
                $addressObj = app::get('ome')->model('return_address');
                $addressObj->update(array('contact_id'=>$contact_id), array('reship_id'=>$reship_id));
            }
            
            //拉取京东售后审核意见,并同步抖音售后单备注内容
            $wms_id = kernel::single('ome_branch')->getWmsIdById($reshipInfo['branch_id']);
            $data = array(
                    'reship_id' => $reshipInfo['reship_id'],
                    'reship_bn' => $reshipInfo['reship_bn'],
                    'order_id' => $reshipInfo['order_id'],
            );
            $result = kernel::single('erpapi_router_request')->set('wms', $wms_id)->reship_search($data);
        }
        
        return false;
    }
    
    /**
     * 获取抖音退货地址库ID
     * 
     * @param array $data
     * @return int
     */
    public function getReturnContactId($data)
    {
        $addressObj = app::get('ome')->model('return_address');
        
        if(empty($data['shop_id']) || empty($data['province']) || empty($data['city']) || empty($data['country'])){
            return false;
        }
        
        $filter = array(
                'shop_id' => $data['shop_id'],
                'province' => $data['province'],
                'city' => $data['city'],
                'country' => $data['country'],
        );
        
        //镇、街道
        if($data['street']){
            $filter['street'] = $data['street'];
        }
        
        //退货地址库ID
        $addressInfo = $addressObj->dump($filter, 'contact_id');
        
        return $addressInfo;
    }
    
    /**
     * 获取抖音平台省、市、区、镇/街道ID
     * 
     * @param array $data
     * @return int
     */
    public function getPlatformRegions($data)
    {
        $regionsObj = app::get('eccommon')->model('platform_regions');
        
        if(empty($data['shop_type']) || empty($data['province']) || empty($data['city']) || empty($data['country'])){
            return false;
        }
        
        $regionList = array();
        $regionKeys = array(1=>'street_id','town_id','city_id','province_id');
        $filter = array(
                'shop_type' => $data['shop_type'],
        );
        
        //镇、街道
        if($data['street']){
            $filter['local_region_name'] = $data['street'];
            
            $i = 1;
            while ($i<=4){
                if($i == 1){
                    $regionInfo = $this->getMappingRegions($data['street'], $data['country']);
                }else{
                    $regionInfo = $regionsObj->dump($filter, '*');
                    if(empty($regionInfo)){
                        $filter['outregion_name'] = $filter['local_region_name'];
                        unset($filter['local_region_name']);
                        
                        $regionInfo = $regionsObj->dump($filter, '*');
                    }
                }
                
                //check
                if(empty($regionInfo)){
                    break;
                }
                
                $key = $regionKeys[$i];
                
                $regionList[$key] = $regionInfo['outregion_id'];
                
                unset($filter['local_region_name'], $filter['outregion_name']);
                $filter['outregion_id'] = $regionInfo['outparent_id'];
                
                $i++;
            }
            
            if($regionList['street_id']){
                return $regionList;
            }
        }
        
        //省、市、区
        $filter['local_region_name'] = $data['country'];
        
        $i = 2;
        while ($i<=4)
        {
            if($i == 2){
                $regionInfo = $this->getMappingRegions($data['country'], $data['city']);
            }else{
                $regionInfo = $regionsObj->dump($filter, '*');
                if(empty($regionInfo)){
                    $filter['outregion_name'] = $filter['local_region_name'];
                    unset($filter['local_region_name']);
                    
                    $regionInfo = $regionsObj->dump($filter, '*');
                }
            }
            
            //check
            if(empty($regionInfo)){
                break;
            }
            
            $key = $regionKeys[$i];
            
            $regionList[$key] = $regionInfo['outregion_id'];
            
            unset($filter['local_region_name'], $filter['outregion_name']);
            
            //region_id
            $filter['outregion_id'] = $regionInfo['outparent_id'];
            
            $i++;
        }
        
        if($regionList['town_id']){
            return $regionList;
        }
        
        return false;
    }
    
    /**
     * 获取精准匹配的区域信息(防止多个地区名称相同)
     */
    public function getMappingRegions($region_name, $parent_region_name)
    {
        $regionsObj = app::get('eccommon')->model('platform_regions');
        
        //filter
        $filter = array('shop_type'=>'luban', 'outregion_name'=>$region_name);
        $regionList = $regionsObj->getList('id,outregion_id,outregion_name,outparent_id,local_region_name', $filter);
        if(empty($regionList)){
            $filter = array('shop_type'=>'luban', 'local_region_name'=>$region_name);
            $regionList = $regionsObj->getList('id,outregion_id,outregion_name,outparent_id,local_region_name', $filter);
        }
        
        //check
        if(empty($regionList)){
            return false;
        }
        
        //list
        foreach ($regionList as $key => $val)
        {
            $outparent_id = $val['outparent_id'];
            $regionInfo = $regionsObj->dump(array('outregion_id'=>$outparent_id, 'outregion_name'=>$parent_region_name), 'id');
            if($regionInfo){
                return $val;
            }
        }
        
        return false;
    }
    
    /**
     * 获取售后申请单推送平台状态
     * 
     * @param string $return_bn
     * @param string $response_status
     * @return string
     */
    public function getReturnSyncStatus($return_bn, $rsp_status)
    {
        $returnProductObj = app::get('ome')->model('return_product');
        
        $sync_status = '';
        
        //check
        if(empty($return_bn) || empty($rsp_status)){
            return $sync_status;
        }
        
        //售后申请单信息
        $returnInfo = $returnProductObj->dump(array('return_bn'=>$return_bn), 'return_id,status,return_type,sync_status');
        if(empty($returnInfo)){
            return $sync_status;
        }
        
        //请求失败场景
        if($rsp_status == 'fail'){
            if($returnInfo['status'] == '5'){
                //拒绝
                if(in_array($returnInfo['sync_status'], array('1','3'))){
                    $sync_status = '8';
                }else{
                    $sync_status = '6';
                }
            }else{
                //同意
                if(in_array($returnInfo['sync_status'], array('1','3'))){
                    $sync_status = '4';
                }else{
                    $sync_status = '2';
                }
            }
            
            return $sync_status;
        }
        
        //请求成功场景
        if($returnInfo['status'] == '5'){
            //拒绝
            if(in_array($returnInfo['sync_status'], array('1','3'))){
                $sync_status = '7';
            }else{
                $sync_status = '5';
            }
        }else{
            //同意
            if(in_array($returnInfo['sync_status'], array('1','3'))){
                $sync_status = '3';
            }else{
                $sync_status = '1';
            }
        }
        
        return $sync_status;
    }
    
    /**
     * 售后仅退款是否自动拒绝
     * 
     * @param int $refund_apply_bn 退款申请单号
     * @param array $oldApplyInfo 原退款单信息
     * @return boolean
     */
    public function getAutoRefuse($refund_apply_bn, $oldApplyInfo=null)
    {
        $refundApplyMdl = app::get('ome')->model('refund_apply');
        $operLogMdl = app::get('ome')->model('operation_log');
        
        //refund_apply
        $refundApplyInfo = $refundApplyMdl->db_dump(array('refund_apply_bn'=>$refund_apply_bn), '*');
        if(empty($refundApplyInfo)) {
            return false;
        }
        
        //获取已拒绝的售后仅退款
        $order_id = $refundApplyInfo['order_id'];
        $isCheck = $refundApplyMdl->db_dump(array('order_id'=>$order_id, 'status'=>'3', 'refund_refer'=>'1'), '*');
        if($isCheck || $oldApplyInfo['status']=='3') {
            //标记为异常
            $refundApplyMdl->set_abnormal_status($refundApplyInfo['apply_id'], ome_constants_refundapply_abnormal::__REPET_REFUND_CODE);
            
            //log
            $operLogMdl->write_log('refund_apply@ome', $refundApplyInfo['apply_id'], '此订单已存在拒绝的售后仅退款单据，需要人工进行审核');
            
            return false;
        }
        
        //是否已经有拒绝记录
        $abnormal_status = ome_constants_refundapply_abnormal::__REPET_REFUND_CODE;
        if(($refundApplyInfo['abnormal_status'] & $abnormal_status) ==  $abnormal_status){
            //log
            $operLogMdl->write_log('refund_apply@ome', $refundApplyInfo['apply_id'], '退款申请单已有拒绝异常标识，需要人工进行审核');
            
            return false;
        }
        
        return true; 
    }
    
    /**
     * [格式化]"省"使用平台区域名称
     * @todo：OMS系统里很多地区没有"省"关键字
     * 
     * @param array $jdAddressInfo
     * @return array
     */
    public function formatPlatformAddressInfo($jdAddressInfo)
    {
        $regionsObj = app::get('eccommon')->model('platform_regions');
        
        //check
        if(empty($jdAddressInfo['province_id'])){
            return $jdAddressInfo;
        }
        
        //平台地址库信息
        $regionInfo = $regionsObj->dump(array('outregion_id'=>$jdAddressInfo['province_id'], 'shop_type'=>'luban'), '*');
        if(empty($regionInfo)){
            return $jdAddressInfo;
        }
        
        //重置省份名称
        $jdAddressInfo['province_name'] = ($regionInfo['outregion_name'] ? $regionInfo['outregion_name'] : $jdAddressInfo['province_name']);
        
        return $jdAddressInfo;
    }

    /**
     * 取消换货产生的新订单
     * @todo场景：
     * 1、换货转仅退款,并且平台已经退款完成;
     * 2、商家拒绝确认收货,售后关闭;
     * 
     * @param array $sdf
     * @return array
     */
    public function cancelExchangeOrder($sdf)
    {
        $orderMdl = app::get('ome')->model('orders');
        
        $result = array('rsp'=>'fail', 'error_msg'=>'');
        
        $return_bn = $sdf['return_bn'];
        $order_bn = ($sdf['order']['order_bn'] ? $sdf['order']['order_bn'] : $sdf['order_bn']);
        
        //获取换货生成的新订单
        $orderInfo = $orderMdl->db_dump(array('relate_order_bn'=>$order_bn), 'order_id,order_bn,process_status,status,ship_status');
        if(empty($orderInfo)){
            $result['error_msg'] = '没有关联订单号';
            return $result;
        }
        
        $order_id = $orderInfo['order_id'];
        
        if($orderInfo['ship_status'] == '1'){
            $result['error_msg'] = '订单已发货,无法取消';
            return $result;
        }
        
        if($orderInfo['process_status'] == 'cancel'){
            $result['error_msg'] = '订单已是取消状态';
            return $result;
        }
        
        $opinfo = kernel::single('ome_func')->get_system();
        
        //撤销发货单
        if(in_array($orderInfo['process_status'], array('splitting', 'splited'))){
            $result = $orderMdl->cancel_delivery($order_id, false);
            if($result['rsp'] != 'succ'){
                //设置订单异常
                $abnormal_data  = array();
                $abnormal_data['order_id'] = $order_id;
                $abnormal_data['op_id'] = $opinfo['op_id'];
                $abnormal_data['group_id'] = $opinfo['group_id'];
                $abnormal_data['abnormal_type_id'] = 1; //订单异常类型
                $abnormal_data['is_done'] = 'false';
                $abnormal_data['abnormal_memo'] = '换货已经仅退款完成,取消发货单失败';
                
                $orderMdl->set_abnormal($abnormal_data);
                
                //error
                $result['error_msg'] = '取消发货单失败:'. $result['msg'];
                return $result;
            }
        }
        
        //取消订单
        $memo = '换货单平台仅退款,不需要换货';
        $result = $orderMdl->cancel($order_id, $memo, false, 'async', false);
        if(!in_array($result['rsp'], array('succ', 'success'))){
            //设置订单异常
            $abnormal_data  = array();
            $abnormal_data['order_id'] = $order_id;
            $abnormal_data['op_id'] = $opinfo['op_id'];
            $abnormal_data['group_id'] = $opinfo['group_id'];
            $abnormal_data['abnormal_type_id'] = 1; //订单异常类型
            $abnormal_data['is_done'] = 'false';
            $abnormal_data['abnormal_memo'] = '换货已经仅退款完成,取消订单失败';
            
            $orderMdl->set_abnormal($abnormal_data);
            
            //error
            $result['error_msg'] = '取消订单失败:'. $result['msg'];
            return $result;
        }
        
        $result['rsp'] = 'succ';
        return $result;
    }
    
    /**
     * 作废换货单失败
     * 1、换货单未完成则不用生成OMS新订单；
     * 2、换货单已经完成，生成的OMS新订单还没有发货,则请求WMS撤消发货单;
     * @param $sdf
     * @return void
     */
    public function disposeExchangeBusiness($data, &$error_msg=null)
    {
        $reshipObj = app::get('ome')->model('reship');
        $operateLog = app::get('ome')->model('operation_log');
        
        //售后申请单信息
        //$returnProduct = $data['return_product'];
        //$return_bn = $returnProduct['return_bn'];
        
        //退换货单信息
        $reshipInfo = $data['reship'];
        $reship_bn = $reshipInfo['reship_bn'];
        
        //check
        if(empty($reship_bn)){
            $error_msg = '没有reship_bn字段值';
            return false;
        }
        
        //退换货单信息
        $reshipInfo = $reshipObj->dump(array('reship_bn'=>$reship_bn, 'return_type'=>'change'), '*');
        if(empty($reship_bn)){
            $error_msg = '换货单不存在';
            return false;
        }
        
        //处理
        if($reshipInfo['is_check'] == '7' || $reshipInfo['change_order_id']>0){
            $orderMdl = app::get('ome')->model('orders');
            
            //换货单已经完成
            $orderInfo = $orderMdl->dump(array('order_bn'=>$reship_bn), '*');

            if($orderInfo){
                //取消发货单
                $log_msg = '';
                $result = $orderMdl->cancel_delivery($orderInfo['order_id']);
                if($result['rsp'] == 'succ'){
                    $log_msg = '顾客换货已修改为退货,取消发货单成功!';
                }else{
                    $log_msg = '顾客换货已修改为退货,取消发货单失败。';

                    //打标记
                    kernel::single('ome_bill_label_delivery')->ToChangeOrderLabel($orderInfo['order_id']);
                }
                
                //退款前提是未发货
                if($orderInfo['ship_status']=='0'){
                    list($rs,$msg) = $this->refundOrder($orderInfo);
                }
                
                if($rs){
                    $log_msg.='因换转退,订单全额退款';
                }
                if($msg){
                    $log_msg.=$msg;
                }

                //log
                $operateLog->write_log('order_modify@ome', $orderInfo['order_id'], $log_msg);
            }
        }else{
            //换货单还未完成,更新为不生成OMS新订单
            $reshipObj->update(array('change_status'=>'2'), array('reship_id'=>$reshipInfo['reship_id'], 'change_status'=>'0'));
            
            //logs
            $operateLog->write_log('reship@ome', $reshipInfo['reship_id'], '顾客已修改为退货,自动修改为：不生成OMS新订单');
        }
        
        return true;
    }
    
    /**
     * 通过京东退货地址匹配抖音退货地址库ID
     * 
     * @param array $data
     * @return int
     */
    public function matchingReturnContactId($jdAddressInfo)
    {
        $addressObj = app::get('ome')->model('return_address');
        
        //setting
        $zhixiashi = array('北京', '上海', '天津', '重庆');
        $zizhiqu   = array('内蒙古', '宁夏回族', '新疆维吾尔', '西藏', '广西壮族');
        
        //check
        if(empty($jdAddressInfo['shop_id'])){
            return array();
        }
        
        if(empty($jdAddressInfo['province']) && empty($jdAddressInfo['addr'])){
            return array();
        }
        
        //先使用详细地址进行匹配
        $filter = array(
            'shop_id' => $jdAddressInfo['shop_id'],
            'reship_id' => 0,
            'addr' => $jdAddressInfo['addr'],
        );
        $addressInfo = $addressObj->dump($filter, '*');
        if($addressInfo){
            $addressInfo['matching_type'] = 'address';
            
            return $addressInfo;
        }
        
        //再使用省、市、区进行匹配
        $filter = array(
            'shop_id' => $jdAddressInfo['shop_id'],
            'reship_id' => 0,
            'province' => $jdAddressInfo['province'],
            'city' => $jdAddressInfo['city'],
            'country' => $jdAddressInfo['country'],
        );
        $addressInfo = $addressObj->dump($filter, '*');
        if($addressInfo){
            $addressInfo['matching_type'] = 'region';
            
            return $addressInfo;
        }
        
        //兼容直辖市
        $province = $this->formateReceiverCitye($jdAddressInfo['province']);
        
        //兼容使用省、市、区进行匹配
        $filter = array(
            'shop_id' => $jdAddressInfo['shop_id'],
            'reship_id' => 0,
            'province' => $province,
            'city' => $jdAddressInfo['city'],
            'country' => $jdAddressInfo['country'],
        );
        $addressInfo = $addressObj->dump($filter, '*');
        if($addressInfo){
            $addressInfo['matching_type'] = 'region_receiver';
            
            return $addressInfo;
        }
        
        //最后使用平台默认退货地址
        $filter = array(
            'shop_id' => $jdAddressInfo['shop_id'],
            'cancel_def' => 'true',
        );
        $addressInfo = $addressObj->dump($filter, '*');
        
        //matching_type
        $addressInfo['matching_type'] = 'default';
        
        return $addressInfo;
    }
    
    /**
     * 格式化直辖市
     * 
     * @param $receiver_city
     * @return mixed|string
     */
    public function formateReceiverCitye($receiver_city)
    {
        $zhixiashi = array('北京', '上海', '天津', '重庆');
        $zizhiqu = array('内蒙古', '宁夏回族', '新疆维吾尔', '西藏', '广西壮族');
        
        if (in_array($receiver_city, $zhixiashi)) {
            $receiver_city = $receiver_city . '市';
        } else if (in_array($receiver_city, $zizhiqu)) {
            $receiver_city = $receiver_city . '自治区';
        } elseif (!preg_match('/(.*?)省/', $receiver_city)) {
            $receiver_city = $receiver_city . '省';
        }
        
        return $receiver_city;
    }
    
    /**
     * [抖音]循环获取所有店铺的商家退货地址库
     * 
     * @param $error_msg
     * @return bool
     */
    public function getAllShopReturnAddress(&$error_msg=null)
    {
        $shopObj = app::get('ome')->model('shop');
        $shopList = $shopObj->getlist('shop_id,shop_bn,name,shop_type,node_id', array('shop_type'=>'luban'));
        if(empty($shopList)){
            $error_msg = '没有可获取地址的店铺';
            return false;
        }
        
        foreach($shopList as $key => $val)
        {
            $shop_id = $val['shop_id'];
            
            //check
            if(empty($val['node_id'])){
                continue;
            }
            
            //request
            $result = $this->pullShopReturnAddress($shop_id, $error_msg);
        }
        
        return true;
    }
    
    /**
     * [抖音]获取指定店铺的商家退货地址库
     * 
     * @param $shop_id
     * @param $error_msg
     * @return bool
     */
    public function pullShopReturnAddress($shop_id, &$error_msg=null)
    {
        //check
        if(empty($shop_id)){
            return false;
        }
        
        //setting
        $page = 1;
        $search_type = '';
        
        //while
        do {
            $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->logistics_searchAddress($search_type, $page);
            if($result['rsp'] == 'succ' && $result['data']){
                $data = json_decode($result['data'], true);
                
                //check
                if(empty($data['address_list'])){
                    //没有退货地址,终止拉取
                    $page = 0;
                }else{
                    //继续拉取下一页
                    $page++;
                }
            }else{
                //拉取失败,终止
                $page = 0;
            }
        } while($page > 0);
        
        return true;
    }


    /**
     * refundOrder
     * @param mixed $orders orders
     * @return mixed 返回值
     */
    public function refundOrder($orders){
        $applyBn = $orders['order_bn'] . 'refund';
        $refund_money = $orders['payed'];
        $refundApplySdf = array (
            'refund_apply_bn' => $applyBn,
            'pay_type'        => 'online',
            'money'           => $refund_money,//退款金额
            'refund_money'    => $refund_money,//退款金额
            'bcmoney'         => 0,//补偿费用
            'refunded'        => $refund_money,
            'memo'            => '换货申请转退货取消订单退款',
            'create_time'     => time(),
            'status'          => '2',
            'shop_id'         => $orders['shop_id'],
            'source'          => 'local',//来源：本地新建
            'refund_refer'    => '0',//退款申请来源：普通流程产生的退款申请
        );

        $refundApplySdf['order_id'] = $orders['order_id'];
        $rs = kernel::single('ome_refund_apply')->createFinishRefundApply($refundApplySdf, false);


        if($rs[0]){
            $sql ="update sdb_ome_orders set payed=IF((CAST(payed AS char)-IFNULL(0,cost_payment)-".$refund_money.")>=0,payed-IFNULL(0,cost_payment)-".$refund_money.",0)  where order_id=".$orders['order_id'];
            kernel::database()->exec($sql);
        }
        kernel::single('ome_order_func')->update_order_pay_status($orders['order_id'], true , __CLASS__.'::'.__FUNCTION__);
        return $rs;
    }
}
