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
 * 开普勒退换货业务处理Lib类
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class ome_reship_kepler
{
    /**
     * Obj对象
     */
    private $__reshipObj = null;
    private $__reshipItemObj = null;
    private $__returnProductObj = null;
    private $__operLogObj = null;
    
    /**
     * Lib类
     */
    private $__reshipLib = null;
    
    /**
     * CONFIG配置项
     */
    private $_config = array();
    
    /**
     * 当前传入的参数
     */
    private $__inputParams = array();
    
    /**
     * 单据信息
     */
    private $_order_id = null;
    private $_reship_id = null;
    private $_return_id = null;
    private $_reshipInfo = array();
    private $_serviceList = array();
    
    /**
     * 初始化
     */
    public function __construct()
    {
        //Obj
        $this->__reshipObj = app::get('ome')->model('reship');
        $this->__reshipItemObj = app::get('ome')->model('reship_items');
        $this->__returnProductObj = app::get('ome')->model('return_product');
        $this->__operLogObj = app::get('ome')->model('operation_log');
        
        //Lib
        $this->__reshipLib = kernel::single('ome_reship');
    }
    
    /**
     * 退换货单业务处理
     * 
     * @param array $data 退换货单信息
     * @return array
     */
    public function process($data)
    {
        $msg_code = '';
        $error_msg = '';
        
        //售后相关配置
        $this->__getReturnSetting();
        
        //接口传入参数
        $this->__inputParams = $data;
        
        //check
        $isCheck = $this->check($data, $error_msg);
        if(!$isCheck){
            return $this->send_error($error_msg, $msg_code);
        }
        
        //business
        switch($data['action'])
        {
            case 'confirm':
                //创建京东售后服务申请单
                $result = $this->syncCreateServerBn($data, $error_msg);
                break;
            case 'cancelService':
                //取消京东售后服务单
                $result = $this->cancelService($data, $error_msg);
                break;
            case 'disposeMQ':
                //处理京东服务单MQ消息
                $result = $this->disposeMQ($error_msg);
                break;
            default:
                $result = false;
                $error_msg = '未知的操作行为';
                break;
        }
        
        //result
        if(!$result){
            return $this->send_error($error_msg, $msg_code);
        }
        
        return $this->send_succ('执行成功');
    }
    
    /**
     * 返回成功信息
     * 
     * @param string $msg
     * @param string $msg_code
     * @param array $data
     * @return array
     */
    public function send_succ($msg='', $msg_code=null, $data=null)
    {
        $result = array(
                'rsp' => 'succ',
                'msg' => $msg,
                'msg_code' => null,
                'data' => null,
        );
    
        return $result;
    }
    
    /**
     * 返回失败信息
     *
     * @param string $msg
     * @param string $msg_code
     * @param array $data
     * @return array
     */
    public function send_error($msg, $msg_code=null, $data=null)
    {
        $result = array(
                'rsp' => 'fail',
                'msg' => $msg,
                'msg_code' => $msg_code,
                'data' => $data,
        );
    
        return $result;
    }
    
    /**
     * 获取售后相关配置
     */
    public function __getReturnSetting()
    {
        //自动审核平台售后申请(系统-->退换货自动审核设置-->是否自动审核平台售后申请)
        $this->_config['auto_confirm'] = app::get('ome')->getConf('return.auto_confirm');
    }
    
    public function check($data, &$error_msg=null)
    {
        $this->_reship_id = $data['reship_id'];
        
        if(!isset($data['action'])){
            $error_msg = '必要参数缺失';
            return false;
        }
        
        //退货单信息
        $filter = array();
        if($data['reship_id']){
            $filter['reship_id'] = $data['reship_id'];
        }else{
            $filter['reship_bn'] = $data['reship_bn'];
        }
        $this->_reshipInfo = $this->__reshipObj->dump($filter, '*');
        if(empty($this->_reshipInfo)){
            $error_msg = '退货单不存在';
            return false;
        }
        
        $this->_reship_id = $this->_reshipInfo['reship_id'];
        $this->_order_id = $this->_reshipInfo['order_id'];
        $this->_return_id = $this->_reshipInfo['return_id'];
        
        if(empty($this->_order_id)){
            $error_msg = '订单ID不存在';
            return false;
        }
        
        return true;
    }
    
    /**
     * 请求创建京东云交易售后服务
     * 
     * @param array $data
     * @return array
     */
    private function syncCreateServerBn($data, &$error_msg=null)
    {
        //退货明细
        $reshipItemList = $this->__reshipItemObj->getList('item_id,product_id,bn,num,product_name', array('reship_id'=>$this->_reship_id, 'return_type'=>'return'));
        if(empty($reshipItemList)){
            $error_msg = '没有退货明细';
            return false;
        }
        
        //[换货]A换B商品不支持推送给京东去交易
        if($this->_reshipInfo['return_type'] == 'change'){
            $lubanProductObj = app::get('ome')->model('return_product_luban');
            $exchangeList = $lubanProductObj->dump(array('return_id'=>$this->_return_id, 'refund_type'=>'change'), '*');
            if($exchangeList['exchange_sku']){
                $exchange_sku = $exchangeList['exchange_sku'];
                $productBns = array_column($reshipItemList, 'bn');
                
                //[A换B]换出商品与发货商品不同,则打标异常,需要人工处理
                if(!in_array($exchange_sku, $productBns)){
                    $error_msg = '异常：换出商品与发货商品不相同,OMS拒绝请求创建服务单';
                    
                    //设置异常：不是整单退货
                    $status = ome_constants_reship_abnormal::__EXCHANGE_DIFF_CODE;
                    $sql = "UPDATE sdb_ome_reship SET is_check='2',abnormal_status=abnormal_status | ". $status .",sync_msg='". $error_msg ."' WHERE reship_id=".$this->_reship_id;
                    $this->__reshipObj->db->exec($sql);
                    return false;
                }
            }
        }
        
        //查询包裹发货状态
        $returnPackages = $this->__reshipLib->get_reship_package($this->_reship_id, $error_msg);
        if(empty($returnPackages)){
            $error_msg = '异常：没有找到退货的包裹信息';
            
            //设置异常：不是整单退货
            $this->__reshipObj->update(array('is_check'=>'2', 'sync_msg'=>$error_msg), array('reship_id'=>$this->_reship_id));
            
            return false;
        }
        
        //关联发货单
        $sql = "SELECT b.delivery_id,b.delivery_bn,b.is_wms_gift FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id ";
        $sql .= " WHERE a.order_id=". $this->_order_id ." AND b.status IN('succ', 'return_back')";
        $dataList = $this->__reshipObj->db->select($sql);
        if(empty($dataList)){
            $error_msg = '没有找到关联发货单';
            return false;
        }
        
        //多个发货单
        $deliveryIds = array();
        $giftDeliverys = array();
        foreach ($dataList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            
            $deliveryIds[] = $delivery_id;
            
            //[京东云交易]有赠品的发货单
            if($val['is_wms_gift'] == 'true'){
                $giftDeliverys[$delivery_id] = $val;
            }
        }
        
        //场景：发货包裹包含赠品,抖音平台必须整单退货,否则异常
        if($giftDeliverys){
            //主品和赠品相同,不允许退货
            $is_equal = $this->__reshipLib->checkPackageSkuEqual($deliveryIds);
            if($is_equal){
                $error_msg = '异常：京东订单里主品与赠品相同,不支持部分退货;';
                
                //设置异常：主品与赠品相同,不支持部分退货
                $status = ome_constants_reship_abnormal::__EQUAL_CODE;
                $sql = "UPDATE sdb_ome_reship SET is_check='2',abnormal_status=abnormal_status | ". $status .",sync_msg='". $error_msg ."' WHERE reship_id=".$this->_reship_id;
                $this->__reshipObj->db->exec($sql);
                
                return false;
            }
            
            //必须整单退货
            $is_diff = $this->__reshipLib->checkReturnPackageSku($this->_order_id, $reshipItemList);
            if($is_diff){
                $error_msg = '异常：发货单包裹有赠品,必须是整单退货,不支持部分退货;';
                
                //设置异常：不是整单退货
                $status = ome_constants_reship_abnormal::__GIFT_CODE;
                $sql = "UPDATE sdb_ome_reship SET is_check='2',abnormal_status=abnormal_status | ". $status .",sync_msg='". $error_msg ."' WHERE reship_id=".$this->_reship_id;
                $this->__reshipObj->db->exec($sql);
                
                return false;
            }
        }
        
        //Api查询订单是否允许申请售后
        $error_msg = '';
        $result = ome_return_notice::query($this->_reship_id, $error_msg);
        if(!$result){
            return false;
        }
        
        //扩展信息
        $extend_info = json_decode($data['extend_info'], true);
        $save_extend = array();
        $is_flag = false;
        
        /***
         * 暂时不用转换地址
         * 
        //转换一：京标四级地址转换
        if(empty($extend_info['area_info']['provinceid'])){
            $result = ome_return_notice::getAddressAreaId($this->_reship_id, $error_msg);
            if(!$result){
                $error_msg = '获取京标四级地址失败：'.$error_msg;
                return false;
            }
            
            //京标四级地址区域ID
            if($result['provinceid']){
                $save_extend['area_info'] = array(
                        'provinceid' => $result['provinceid'],
                        'cityid' => $result['cityid'],
                        'streetid' => $result['streetid'],
                        'townid' => $result['townid'],
                );
                
                $is_flag = true;
            }
        }
        ***/
        
        //转换二：抖音平台售后原因转换为京东云交易售后原因
        if(empty($extend_info['reason_info']['problem_id'])){
            $problemInfo = $this->__reshipLib->getReturnWmsReason($data);
            
            $save_extend['reason_info'] = array(
                    'problem_id' => $problemInfo['problem_id'],
                    'reason_id' => $problemInfo['reason_id'],
                    'problem_name' => $problemInfo['problem_name'],
            );
            
            $is_flag = true;
        }
        
        //保存WMS售后原因、京标四级地址
        if($is_flag && $save_extend){
            $this->__reshipObj->update(array('reason_info'=>json_encode($save_extend)), array('reship_id'=>$this->_reship_id));
        }
        
        return true;
    }
    
    /**
     * 自动拒绝平台售后申请单
     * 
     * @param string $error_msg
     * @return bool
     */
    public function autoRefuseAftersale(&$error_msg=null)
    {
        $processObj = app::get('ome')->model('return_process');
        
        //未配置[自动审核平台售后申请]则不进行处理
        if($this->_config['auto_confirm'] != 'on'){
            return true;
        }
        
        //check
        if(in_array($this->_reshipInfo['is_check'], array('7','8','14'))){
            $error_msg = '退换货单状态不允许自动取消';
            
            //logs
            $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
            
            return false;
        }
        
        //获取售后京东服务单号
        $processList = $processObj->getList('*', array('reship_id'=>$this->_reship_id));
        foreach ((array)$processList as $key => $val)
        {
            if($val['logi_no']){
                $error_msg = '已有退回物流单号,不允许自动取消';
                
                //logs
                $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
                
                return false;
            }
        }
        
        $this->_serviceList = $processList;
        
        //仓库信息
        $branchLib = kernel::single('ome_branch');
        $branchInfo = $branchLib->getBranchInfo($this->_reshipInfo['branch_id'], 'branch_bn,wms_id,owner_code');
        
        //取消京东售后单
        if($processList && $branchInfo['wms_id']){
            $params = array(
                    'order_id' => $this->_order_id,
                    'reship_id' => $this->_reship_id,
                    'reship_bn' => $this->_reshipInfo['reship_bn'],
                    'branch_bn' => $branchInfo['branch_bn'],
                    'owner_code' => $branchInfo['owner_code'],
            );
            $res = kernel::single('console_event_trigger_reship')->cancel($branchInfo['wms_id'], $params, true);
            if($res['rsp'] != 'succ'){
                $error_msg = '自动取消京东服务单失败：'. $res['error_msg'];
                
                //logs
                $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
                
                return false;
            }
        }
        
        //取消退货包裹
        $cancelResult = $this->__reshipLib->cancel_reship_package($this->_reshipInfo, $error_msg);
        
        //取消退货单
        $updateSdf = array('is_check'=>'5', 't_end'=>time());
        $this->__reshipObj->update($updateSdf, array('reship_id'=>$this->_reship_id));
        
        kernel::single('console_reship')->releaseChangeFreeze($this->_reship_id);
        // 退货单取消后的service扩展点
        foreach(kernel::servicelist('console.service.reship.cancel.after') as $object) {
            if(method_exists($object, 'cancel_reship_after')) {
                $object->cancel_reship_after($this->_reship_id);
            }
        }
        //logs
        $memo = '自动拒绝退货单('. $this->__inputParams['afsResultType'] .')';
        $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $memo);
        
        //更新售后申请单为拒绝,并且推送给平台拒绝状态
        if($this->_return_id){
            $updateSdf = array('return_id'=>$this->_return_id, 'status'=>'5', 'memo'=>'商品不支持售后', 'last_modified'=>time());
            $this->__returnProductObj->update_status($updateSdf);
            
            //logs
            $memo = '自动拒绝平台售后申请单('. $this->__inputParams['afsResultType'] .')';
            $this->__operLogObj->write_log('return@ome', $this->_return_id, '售后服务:'. $memo);
            
            //拉取京东售后审核意见,并同步抖音售后单备注内容
            $wms_id = $branchInfo['wms_id'];
            $data = array(
                    'reship_id' => $this->_reshipInfo['reship_id'],
                    'reship_bn' => $this->_reshipInfo['reship_bn'],
                    'order_id' => $this->_reshipInfo['order_id'],
            );
            $result = kernel::single('erpapi_router_request')->set('wms', $wms_id)->reship_search($data);
        }
        
        return true;
    }
    
    /**
     * 处理京东服务单MQ消息
     */
    public function disposeMQ(&$error_msg=null)
    {
        switch($this->__inputParams['afsResultType'])
        {
            case 'AUDIT_FAIL':
            case 'cannotApply':
            case 'CANCEL':
                //京东服务单返回:审核失败
                $result = $this->autoRefuseAftersale($error_msg);
                break;
            case 'PICKWARE_SEND':
                //京东服务单返回:客户发货(顾客寄回商品)
                $result = $this->disposeAgree($error_msg);
                break;
            case 'AFS_RECV_PRODUCT':
                //京东服务单返回:收到商品(商家已经收到退回商品)
                $result = $this->disposeReturn($error_msg);
                break;
            case 'service_refund':
                //云交易订单退款成功
                $result = $this->disposeRefund($error_msg);
                break;
            default:
                $result = false;
                $error_msg = '未知的服务单状态';
                break;
        }
        
        //京东售后服务单列表
        $afsResultTypes = array();
        if($this->_serviceList){
            foreach ($this->_serviceList as $key => $val)
            {
                if(empty($val['afsResultType'])){
                    continue;
                }
                
                if(in_array($val['afsResultType'], array('AUDIT_FAIL', 'CANCEL', 'RETURN_CANCEL', 'FORCECOMPLETE'))){
                    $afsResultTypes['fail'][] = $val['service_bn'];
                }else{
                    $afsResultTypes['succ'][] = $val['service_bn'];
                }
            }
        }
        
        //审核结果不一致,设置为异常
        $abnormal_status = ome_constants_reship_abnormal::__RESULT_CODE;
        if(count($afsResultTypes) > 1){
            $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status | ". $abnormal_status ." WHERE reship_id=". $this->_reship_id;
            $this->__reshipObj->db->exec($sql);
        }else{
            //清除异常:审核结果不一致
            if(($this->_reshipInfo['abnormal_status'] & $abnormal_status) ==  $abnormal_status){
                $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status ^ ". $abnormal_status ." WHERE reship_id=". $this->_reship_id;
                $this->__reshipObj->db->exec($sql);
            }
        }
        
        return $result;
    }
    
    /**
     * 京东服务单返回:客户发货(顾客寄回商品)
     * 
     * @todo：京东会按下面的步骤依次返回;
     * 处理环节stepType：APPLY(申请)，AUDIT(审核)，RECEIVED(收货)，PROCESS(处理)，CONFIRM(确认)，COMPLETE(用户点击已解决)
     */
    public function disposeAgree(&$error_msg=null)
    {
        $processObj = app::get('ome')->model('return_process');
        
        //未配置[自动审核平台售后申请]则不进行处理
        if($this->_config['auto_confirm'] != 'on'){
            return true;
        }
        
        //check
        if(in_array($this->_reshipInfo['is_check'], array('0','5'))){
            $error_msg = '退换货单状态不允许自动同意';
            return false;
        }
        
        //获取售后京东服务单号
        $processList = $processObj->getList('*', array('reship_id'=>$this->_reship_id));
        if(empty($processList)){
            $error_msg = '没有售后京东服务单';
            return false;
        }
        
        $this->_serviceList = $processList;
        
        //list
        $isAgree = true;
        foreach($processList as $key => $val)
        {
            //stepType：处理环节,去除了'PROCESS'状态
            if(in_array($val['step_type'], array('RECEIVED', 'CONFIRM', 'COMPLETE'))){
                continue;
            }
            
            //afsResultType：服务单状态
            if(!in_array($val['afsResultType'], array('PICKWARE_SEND'))){
                $isAgree = false;
                break;
            }
        }
        
        if(!$isAgree){
            $error_msg = '京东服务单包含不是[客户发货]状态,不允许自动同意';
            
            //logs
            $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
            
            return false;
        }
        
        //查询寄件地址&&请求平台同意退货申请
        $result = ome_return_notice::selectAddress($this->_reship_id, $error_msg);
        if(!$result){
            //logs
            $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * 京东服务单返回:收到商品(商家已经收到退回商品)
     */
    public function disposeReturn(&$error_msg=null)
    {
        $processObj = app::get('ome')->model('return_process');
        
        //未配置[自动审核平台售后申请]则不进行处理
        if($this->_config['auto_confirm'] != 'on'){
            return true;
        }
        
        //check
        if(in_array($this->_reshipInfo['is_check'], array('0','5'))){
            $error_msg = '退换货单状态不允许自动同意';
            return false;
        }
        
        //获取售后京东服务单号
        $processList = $processObj->getList('*', array('reship_id'=>$this->_reship_id));
        if(empty($processList)){
            $error_msg = '没有售后京东服务单';
            return false;
        }
        
        $this->_serviceList = $processList;
        
        //list
        $isAgree = true;
        $wms_refund_fee = 0;
        foreach($processList as $key => $val)
        {
            //stepType：处理环节,去除了'PROCESS'状态
            if(in_array($val['step_type'], array('CONFIRM', 'COMPLETE'))){
                continue;
            }
            
            //afsResultType：服务单状态
            if(!in_array($val['afsResultType'], array('AFS_RECV_PRODUCT'))){
                $isAgree = false;
                break;
            }
            
            //WMS退款金额
            if($val['wms_refund_fee']){
                $wms_refund_fee = bcadd($wms_refund_fee, $val['wms_refund_fee'], 3);
            }
        }
        
        if(!$isAgree){
            $error_msg = '京东服务单包含不是[收到商品]状态,不允许自动完成退货';
            
            //logs
            $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
            
            return false;
        }
        
        //获取发货单上SKU货品推送给京东采购金额小计
        $purchaseAmount = $this->getDeliveryPurchaseAmount($error_msg);
        if($purchaseAmount === false){
            //logs
            $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
            
            return false;
        }
        
        //比较京东退款金额与京东创建订单采购金额小计
        if(bccomp($purchaseAmount, $wms_refund_fee, 3) != 0){
            $error_msg = '京东与OMS退款金额不一致,不允许自动完成退货。';
            
            //logs
            $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
            
            return false;
        }
        
        //完成退货
        if($this->_reshipInfo['return_type'] == 'return'){
            //退款申请单
            $refundApplyObj = app::get('ome')->model('refund_apply');
            $refundInfo = $refundApplyObj->dump(array('refund_apply_bn'=>$this->_reshipInfo['reship_bn']), 'apply_id');
            if(empty($refundInfo)){
                $error_msg = '退货单未生成退款申请单,不允许自动完成退货';
                
                //logs
                $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
                
                return false;
            }
            
            //订单信息
            $orderInfo = $this->__reshipObj->db->selectrow("SELECT order_id,order_bn FROM sdb_ome_orders WHERE order_id=". $this->_reshipInfo['order_id']);
            
            //params
            $params = array(
                    'order_bn' => $orderInfo['order_bn'],
                    'apply_id' => $refundInfo['apply_id'],
                    'refund_bn' => $this->_reshipInfo['reship_bn'],
                    'return_bn' => $this->_reshipInfo['reship_bn'],
                    'is_aftersale_refund' => true,
                    'shop_id' => $this->_reshipInfo['shop_id'],
            );
            kernel::single('ome_service_refund')->refund_request($params);
        }
        
        return true;
    }
    
    /**
     * 京东服务单返回:收到商品(商家已经收到退回商品)
     */
    public function disposeRefund(&$error_msg=null)
    {
        $processObj = app::get('ome')->model('return_process');
        
        //未配置[自动审核平台售后申请]则不进行处理
        if($this->_config['auto_confirm'] != 'on'){
            return true;
        }
        
        //check
        if(in_array($this->_reshipInfo['is_check'], array('0','5'))){
            $error_msg = '退换货单状态不允许自动同意';
            return false;
        }
        
        //获取售后京东服务单号
        $processList = $processObj->getList('*', array('reship_id'=>$this->_reship_id));
        if(empty($processList)){
            $error_msg = '没有售后京东服务单';
            return false;
        }
        
        $this->_serviceList = $processList;
        
        //list
        $wms_refund_fee = 0;
        foreach($processList as $key => $val)
        {
            //WMS退款金额
            if($val['wms_refund_fee']){
                $wms_refund_fee = bcadd($wms_refund_fee, $val['wms_refund_fee'], 3);
            }
        }
        
        //获取发货单上SKU货品推送给京东采购金额小计
        $purchaseAmount = $this->getDeliveryPurchaseAmount($error_msg);
        if($purchaseAmount === false){
            //logs
            $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
            
            return false;
        }
        
        //比较京东退款金额与OMS退款金额
        if(bccomp($purchaseAmount, $wms_refund_fee, 3) != 0){
            $error_msg = '京东与OMS退款金额不一致,不允许自动完成退货!';
            
            //logs
            $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
            
            return false;
        }
        
        //完成退货
        if($this->_reshipInfo['return_type'] == 'return'){
            //退款申请单
            $refundApplyObj = app::get('ome')->model('refund_apply');
            $refundInfo = $refundApplyObj->dump(array('refund_apply_bn'=>$this->_reshipInfo['reship_bn']), 'apply_id');
            if(empty($refundInfo)){
                $error_msg = '退货单未生成退款申请单,不允许自动完成退货';
                
                //logs
                $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
                
                return false;
            }
            
            //订单信息
            $orderInfo = $this->__reshipObj->db->selectrow("SELECT order_id,order_bn FROM sdb_ome_orders WHERE order_id=". $this->_reshipInfo['order_id']);
            
            //params
            $params = array(
                    'order_bn' => $orderInfo['order_bn'],
                    'apply_id' => $refundInfo['apply_id'],
                    'refund_bn' => $this->_reshipInfo['reship_bn'],
                    'return_bn' => $this->_reshipInfo['reship_bn'],
                    'is_aftersale_refund' => true,
                    'shop_id' => $this->_reshipInfo['shop_id'],
            );
            kernel::single('ome_service_refund')->refund_request($params);
        }
        
        return true;
    }
    
    /**
     * 取消京东售后服务单
     *
     * @param array $data
     * @return array
     */
    public function cancelService($data, &$error_msg=null)
    {
        //取消退货包裹
        $cancelResult = $this->__reshipLib->cancel_reship_package($this->_reshipInfo, $error_msg);
        
        //取消京东售后服务单
        if($data['service_bn']){
            $processObj = app::get('ome')->model('return_process');
            
            $updateData = array('service_status'=>'cancel', 'last_modified'=>time());
            
            //平台售后状态
            if($data['afsResultType']){
                $updateData['afsResultType'] = $data['afsResultType'];
            }
            
            //平台处理环节
            if($data['stepType']){
                $updateData['step_type'] = $data['stepType'];
            }
            
            $processObj->update($updateData, array('reship_id'=>$this->_reship_id, 'service_bn'=>$data['service_bn']));
            
            //获取未取消的服务单
            $sql = "SELECT por_id FROM sdb_ome_return_process WHERE reship_id=". $this->_reship_id ." AND service_status!='cancel'";
            $processInfo = $processObj->db->selectrow($sql);
            if($processInfo){
                //当部分取消服务单时,更新退货单为"质检异常"状态,并直接return
                $reship_update_data = array('is_check'=>'12');
                $this->__reshipObj->update($reship_update_data, array('reship_id'=>$this->_reship_id));
                
                return true;
            }
        }
        
        return true;
    }
    
    /**
     * 获取发货单上SKU货品推送给京东采购金额小计
     *
     * @param array $data
     * @return array
     */
    public function getDeliveryPurchaseAmount(&$error_msg=null)
    {
        $dlyItemObj = app::get('ome')->model('delivery_items');
        
        //获取退货SKU明细
        $reshipItemList = $this->__reshipItemObj->getList('item_id,product_id,bn,num', array('reship_id'=>$this->_reship_id, 'return_type'=>'return'));
        if(empty($reshipItemList)){
            $error_msg = '没有退货明细';
            return false;
        }
        
        $productIds = array_column($reshipItemList, 'product_id');
        
        //关联发货单(获取已发货、已追回的发货单)
        $sql = "SELECT b.delivery_id FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id ";
        $sql .= " WHERE a.order_id=". $this->_order_id ." AND b.status IN('succ','return_back')";
        $deliveryList = $this->__reshipObj->db->select($sql);
        if(empty($deliveryList)){
            $error_msg = '没有关联发货单';
            return false;
        }
        
        $deliveryIds = array_column($deliveryList, 'delivery_id');
        
        //获取发货SKU明细
        $purchaseAmount = 0;
        $itemList = $dlyItemObj->getList('item_id,product_id,bn,number,purchase_price', array('delivery_id'=>$deliveryIds));
        foreach ($itemList as $key => $val)
        {
            if(!in_array($val['product_id'], $productIds)){
                continue; //不是退货的商品,是跳过
            }
            
            $purchase_price = bcmul($val['purchase_price'], $val['number'], 3);
            $purchaseAmount = bcadd($purchaseAmount, $purchase_price, 3);
        }
        
        return $purchaseAmount;
    }
    
    /**
     * [京东云交易]当不可申请京东售后时
     * @todo：第一版时,需要OMS自动拒绝抖音平台售后申请;
     * @todo：第二版修改为,需要人工手动审核 并且 打标异常标识;
     *
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return boolean
     */
    public function cannotApplyAftersale(&$cursor_id, $params, &$error_msg=null)
    {
        //data
        $sdfdata = $params['sdfdata'];
        $order_id = intval($sdfdata['order_id']);
        $reship_id = intval($sdfdata['reship_id']);
        $canApply = $sdfdata['canApply']; //该订单是否可申请售后 0：不可申请 1：可申请
        $cannotApplyTip = $sdfdata['cannotApplyTip']; //不可申请提示,例如：该商品已超过售后期
        
        /***
         * @todo：不再自动拒绝平台售后申请单
         * 
        //result
        $data = array(
                'reship_id' => $reship_id,
                'order_id' => $order_id,
                'canApply' => $canApply,
                'cannotApplyTip' => $cannotApplyTip,
                'afsResultType' => 'cannotApply',
        );
        
        $data['action'] = 'disposeMQ';
        
        $result = $this->process($data);
        if($result['rsp'] == 'fail' && $result['msg']){
            $error_msg = $result['msg'];
            return false;
        }
        
        //logs
        $memo = '自动拒绝平台售后申请单('. $this->__inputParams['afsResultType'] .')';
        $this->__operLogObj->write_log('return@ome', $this->_return_id, '售后服务:'. $memo);
        ***/
        
        //设置异常标识
        $status = ome_constants_reship_abnormal::__ERVICE_AUDIT_CODE;
        $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status | ". $status ." WHERE reship_id=".$reship_id;
        $this->__reshipObj->db->exec($sql);
        
        //logs
        $log_msg = '京东服务单审核不通过,需要人工进行审核';
        $this->__operLogObj->write_log('reship@ome', $reship_id, $log_msg);
        
        return false;
    }
    
    /**
     * [京东云交易]拉取退货寄件地址(只支持抖音店铺)
     * 
     * @param array $reship_ids
     * @param string $error_msg
     * @return boolean
     */
    public function getReshipAddress($reship_ids=null, &$error_msg=null)
    {
        $addressObj = app::get('ome')->model('return_address');
        
        $branchLib = kernel::single('ome_branch');
        
        //where
        if($reship_ids){
            $where = "WHERE reship_id IN(". implode(',', $reship_ids) .")";
        }else{
            //获取京东一件代发类型仓库
            $wms_type = 'yjdf';
            $error_msg = '';
            $yjdfBranchList = $branchLib->getWmsBranchIds($wms_type, $error_msg);
            if(!$yjdfBranchList){
                $error_msg = '没有京东一件代发类型仓库';
                return false;
            }
            
            $branchIds = array_keys($yjdfBranchList);
            
            //$where = "WHERE is_check='1' AND shop_type='luban' AND return_logi_no!='' ";
            $where = "WHERE is_check='1' AND shop_type='luban' ";
            $where .= "AND branch_id IN(". implode(',', $branchIds) .") ORDER BY reship_id DESC LIMIT 0, 300";
        }
        
        //获取可操作数据
        $sql = "SELECT reship_id,reship_bn,branch_id,return_logi_no,check_time FROM sdb_ome_reship ". $where;
        $dataList = $this->__reshipObj->db->select($sql);
        if(empty($dataList)){
            $error_msg = '未找到退货单';
            return false;
        }
        
        foreach ($dataList as $key => $val)
        {
            $reship_id = $val['reship_id'];
            
            //售后单审核时间之后1小时时间戳
            $startTime = $val['check_time'] + 3600;
            $nowTime = time();
            
            //wms
            $wms_type = $branchLib->getNodetypBybranchId($val['branch_id']);
            if($wms_type != 'yjdf'){
                $error_msg = '不是京东一件代发仓库类型';
                continue;
            }
            
            //check
            //if(empty($val['return_logi_no'])){
            //    $error_msg = '没有退货物流单号';
            //    continue;
            //}
            
            if($nowTime < $startTime){
                $error_msg = '只拉取售后单审核之后1小时的单据';
                continue;
            }
            
            //address
            $addressInfo = $addressObj->dump(array('reship_id'=>$reship_id), 'address_id');
            if($addressInfo){
                $error_msg = '寄件地址已经存在';
                continue;
            }
            
            //query
            $log_msg = ($reship_ids ? '批量' : '定时自动') .'获取寄件地址';
            $error_msg = '';
            $result = ome_return_notice::selectAddress($reship_id, $error_msg);
            if(!$result){
                $log_msg .= '失败:'. $error_msg;
            }else{
                $log_msg .= '成功!';
            }
            
            //log
            $this->__operLogObj->write_log('reship@ome', $reship_id, $log_msg);
        }
        
        return true;
    }
    
    /**
     * [京东云交易]请求WMS妥投签收完成
     *
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return boolean
     */
    public function autoSignWmsDelivery(&$cursor_id, $params, &$error_msg=null)
    {
        $orderObj = app::get('ome')->model('orders');
        
        //data
        $sdfdata = $params['sdfdata'];
        $order_id = intval($sdfdata['order_id']);
        $reship_id = intval($sdfdata['reship_id']);
        
        //订单信息
        $orderInfo = $orderObj->dump(array('order_id'=>$order_id), 'order_id,order_bn,ship_status,pay_status');
        
        if(!in_array($orderInfo['ship_status'], array('1','3'))){
            $log_error_msg = '订单号：'.$orderInfo['order_bn'].'不是已发货或部分退货状态;';
            
            //log
            $this->__operLogObj->write_log('order_confirm@ome', $order_id, $log_error_msg);
            
            return false;
        }
        
        if(in_array($orderInfo['pay_status'], array('0','4','5'))){
            $error_msg = '订单号：'.$orderInfo['order_bn'].'有退款不能妥投';
            
            //log
            $this->__operLogObj->write_log('order_confirm@ome', $order_id, $error_msg);
            
            return false;
        }
    
        //关联发货单
        $sql = "SELECT b.delivery_id,b.delivery_bn,b.branch_id FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id ";
        $sql .= " WHERE a.order_id=". $order_id ." AND b.status IN('succ', 'return_back')";
        $deliveryInfo = $orderObj->db->selectrow($sql);
        if(empty($deliveryInfo)){
            $log_error_msg = '没有找到关联发货单';
            
            //log
            $this->__operLogObj->write_log('order_confirm@ome', $order_id, $log_error_msg);
            
            return false;
        }
        
        //branch_bn
        $branch_bn = kernel::single('ome_branch')->getBranchBnById($deliveryInfo['branch_id']);
        
        //wms_id
        $channel_id = kernel::single('ome_branch')->getWmsIdById($deliveryInfo['branch_id']);
        
        //Api请求订单确认收货接口
        $params = array('order_id'=>$order_id, 'order_bn'=>$orderInfo['order_bn'], 'branch_bn'=>$branch_bn);
        $result = kernel::single('erpapi_router_request')->set('wms', $channel_id)->delivery_confirm($params);
        if($result['rsp'] != 'succ'){
            $error_msg = '订单推送WMS确认收货失败：'.$result['err_msg'];
            
            //log
            $this->__operLogObj->write_log('order_confirm@ome', $order_id, $error_msg);
            
            return false;
        }
        
        //退换货单
        if($reship_id){
            //注销：判断重复审核(1分钟之内不能重复)
            $cacheKeyName = sprintf("confirm_reship_id_%s", $reship_id);
            cachecore::delete($cacheKeyName);
            
            //退换货单自动审批
            $this->__reshipLib->batch_reship_queue($reship_id);
        }
        
        return false;
    }
    
    /**
     * 获取退货包裹明细(按京东订单号、WMS货号为数组下标)
     *
     * @param unknown $reship_id
     * @param string $error_msg
     * @return boolean
     */
    public function getReshipPackageInfo($filter, &$error_msg=null)
    {
        //filter
        $reship_filter = array();
        if($filter['reship_id']){
            $reship_filter['reship_id'] = $filter['reship_id'];
        }else{
            $reship_filter['reship_bn'] = $filter['reship_bn'];
        }
        
        if(empty($reship_filter)){
            $error_msg = '无效的查询';
            return false;
        }
        
        //退货单信息
        $reshipInfo = $this->__reshipObj->dump($reship_filter, 'reship_id,reship_bn');
        if(empty($reshipInfo)){
            $error_msg = '没有找到退货单';
            return false;
        }
        $reship_id = $reshipInfo['reship_id'];
        
        //获取退货包裹信息
        $sql = "SELECT package_id,delivery_bn,wms_channel_id,wms_package_bn,sync_status FROM sdb_ome_reship_package WHERE reship_id=". $reship_id;
        $dataList = $this->__reshipObj->db->select($sql);
        if(empty($dataList)){
            $error_msg = '没有获取到退货包裹';
            return false;
        }
        
        $packageList = array();
        foreach ($dataList as $key => $val)
        {
            $package_id = $val['package_id'];
            $wms_package_bn = $val['wms_package_bn']; //京东订单号(包裹单号)
            
            //过滤已取消的
            if($val['sync_status'] == 'cancel'){
                continue;
            }
            
            //退货包裹明细
            $item_sql = "SELECT * FROM sdb_ome_reship_package_items WHERE package_id=". $package_id;
            $itemTemp = $this->__reshipObj->db->select($item_sql);
            if(empty($itemTemp)){
                continue;
            }
            
            //items
            $itemList = array();
            foreach ($itemTemp as $itemKey => $itemVal)
            {
                //以京东云交易货号为准
                $product_bn = ($itemVal['outer_sku'] ? $itemVal['outer_sku'] : $itemVal['bn']);
                
                //是否赠品
                $item_type = ($itemVal['is_wms_gift']=='true' ? 'gift' : 'product');
                
                $itemList[$item_type][$product_bn] = $itemVal;
            }
            
            $val['items'] = $itemList;
            
            $packageList[$wms_package_bn] = $val;
        }
        
        return $packageList;
    }
    
    /**
     * [京东云交易]发货已签收,自动审核对应的退货单
     *
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return boolean
     */
    public function autoDlyConfirmReship(&$cursor_id, $params, &$error_msg=null)
    {
        //data
        $sdfdata = $params['sdfdata'];
        $delivery_id = intval($sdfdata['delivery_id']);
        $delivery_bn = intval($sdfdata['delivery_bn']);
        
        //关联发货单(获取已发货、已追回的发货单)
        $sql = "SELECT a.order_id FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id WHERE a.delivery_id=". $delivery_id;
        $dataList = $this->__reshipObj->db->select($sql);
        if(empty($dataList)){
            $error_msg = '没有找到关联订单';
            return false;
        }
        
        $order_ids = array();
        foreach ($dataList as $key => $val)
        {
            $order_id = $val['order_id'];
    
            $order_ids[$order_id] = $order_id;
        }
        
        //获取可操作数据
        $filter = array('order_id'=>$order_ids, 'is_check'=>array('0','2'));
        $dataList = $this->__reshipObj->getList('reship_id,reship_bn,return_type,is_check,status', $filter);
        if(empty($dataList)){
            return false;
        }
        
        //list
        foreach ($dataList as $key => $val)
        {
            $reship_id = $val['reship_id'];
            
            //check
            if(!in_array($val['return_type'], array('return', 'change'))){
                continue;
            }
            
            if(!in_array($val['status'], array('ready'))){
                continue;
            }
            
            //注销：判断重复审核(1分钟之内不能重复)
            $cacheKeyName = sprintf("confirm_reship_id_%s", $reship_id);
            cachecore::delete($cacheKeyName);
            
            //退换货单自动审批
            //$this->batch_reship_queue($reship_id);
            
            //执行审核退货单
            $log_error_msg = '';
            $params = array('reship_id'=>$reship_id, 'status'=>'1', 'is_anti'=>false, 'exec_type'=>1);
            $confirm = $this->__reshipLib->confirm_reship($params, $log_error_msg, $is_rollback);
            if(!$confirm){
                $error_msg .= '退货单号：'.$val['reship_bn'].$log_error_msg;
            }
        }
        
        return false;
    }
    
    /**
     * [京东一件代发]获取京东售后服务单号(MQ消息)
     *
     * @param int $reship_id
     * @param string $error_msg
     * @return array
     */
    public function get_reship_services($reship_id, $getItems=false, &$error_msg=null)
    {
        $processObj = app::get('ome')->model('return_process');
        $proItemObj = app::get('ome')->model('return_process_items');
        $rePackageObj = app::get('ome')->model('reship_package');
        
        //退货单信息
        $sql = "SELECT reship_id,return_logi_no,return_logi_name FROM sdb_ome_reship where reship_id=".$reship_id;
        $reshipInfo = $processObj->db->selectrow($sql);
        
        $return_logi_no = $reshipInfo['return_logi_no']; //退回物流单号
        $return_logi_code = $reshipInfo['return_logi_name']; //退回物流编码
        
        //[兼容]匹配OMS中物流公司编码
        if($return_logi_code){
            $sql = "SELECT corp_id,type FROM sdb_ome_dly_corp WHERE name='". $return_logi_code ."' OR type='". $return_logi_code ."'";
            $corpInfo = $processObj->db->selectrow($sql);
            
            //过滤抖音平台的物流公司名称
            if(empty($corpInfo)){
                $return_logi_code = str_replace('(常用)', '', $return_logi_code);
                
                $sql = "SELECT corp_id,type FROM sdb_ome_dly_corp WHERE name='". $return_logi_code ."' OR type='". $return_logi_code ."'";
                $corpInfo = $processObj->db->selectrow($sql);
            }
            
            $return_logi_code = $corpInfo['type'];
        }
        
        //list
        $processList = $processObj->getList('*', array('reship_id'=>$reship_id));
        if(empty($processList)){
            $error_msg = '没有获取到售后服务单号';
            return false;
        }
        
        //items
        if($getItems){
            $itemTemp = $proItemObj->getList('item_id,por_id,bn,name,wms_sku_bn,num', array('reship_id'=>$reship_id));
            if(empty($itemTemp)){
                $error_msg = '没有获取到售后服务单明细';
                return false;
            }
            
            $itemList = array();
            foreach ($itemTemp as $key => $val)
            {
                $por_id = $val['por_id'];
    
                $itemList[$por_id][] = $val;
            }
        }
        
        //退货包裹列表
        $rePackageList = array();
        $tempList = $rePackageObj->getList('*', array('reship_id'=>$reship_id));
        foreach ($tempList as $key => $val)
        {
            $wms_package_bn = $val['wms_package_bn'];
            
            $rePackageList[$wms_package_bn] = $val;
        }
        
        //list
        $dataList = array();
        foreach ($processList as $key => $val)
        {
            $por_id = $val['por_id'];
            $package_bn = $val['package_bn'];
            
            //check
            if(empty($val['service_bn'])){
                continue;
            }
            
            //items
            if($getItems){
                $val['items'] = $itemList[$por_id];
            }
            
            //[兼容]抖音平台退货物流信息
            $val['logi_code'] = ($val['logi_code'] ? $val['logi_code'] : $return_logi_code);
            $val['logi_no'] = ($val['logi_no'] ? $val['logi_no'] : $return_logi_no);
            
            //渠道ID
            $val['wms_channel_id'] = $rePackageList[$package_bn]['wms_channel_id'];
            
            //服务类型
            if($val['service_type']=='10'){
                $val['service_type_value'] = '退货';
            }elseif($val['service_type']=='20'){
                $val['service_type_value'] = '换货';
            }
            
            $dataList[] = $val;
        }
        
        //check
        if(empty($dataList)){
            $error_msg = '没有可用的售后服务单号';
            return false;
        }
        
        return $dataList;
    }
    
    /**
     * [抖音平台]请求更新京东云交易退货物流信息
     *
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return boolean
     */
    public function syncLogisticInfo(&$cursor_id, $params, &$error_msg=null)
    {
        $processObj = app::get('ome')->model('return_process');
        
        //data
        $sdfdata = $params['sdfdata'];
        $order_id = intval($sdfdata['order_id']);
        $reship_id = intval($sdfdata['reship_id']);
        
        //退货单信息
        $reshipInfo = $this->__reshipObj->dump(array('reship_id'=>$reship_id), 'reship_id,reship_bn,return_logi_no,return_logi_name');
        if(empty($reshipInfo['return_logi_no'])){
            $log_error_msg = '退货单没有退货物流单号';
            
            //log
            $this->__operLogObj->write_log('reship@ome', $reship_id, $log_error_msg);
            
            return false;
        }
        
        $reshipInfo['return_logi_name'] = str_replace(array('"', "'"), '', $reshipInfo['return_logi_name']);
        
        //获取售后服务单
        $log_error_msg = '';
        $serviceList = $this->get_reship_services($reship_id, false, $log_error_msg);
        if(empty($serviceList)){
            $log_error_msg = '没有服务单,不能保存退回物流信息';
            
            //log
            $this->__operLogObj->write_log('reship@ome', $reship_id, $log_error_msg);
            
            return false;
        }
        
        //过滤危险字符
        $reshipInfo['return_logi_name'] = str_replace(array('"', "'"), '', $reshipInfo['return_logi_name']);
        
        //去除抖音平台(常用)关键字
        $temp_logi_name = str_replace('(常用)', '', $reshipInfo['return_logi_name']);
        
        //check
        $sql = "SELECT corp_id,type FROM sdb_ome_dly_corp WHERE name IN('". $reshipInfo['return_logi_name'] ."', '". $temp_logi_name ."') OR type='". $reshipInfo['return_logi_name'] ."'";
        $corpInfo = $this->__reshipObj->db->selectrow($sql);
        if(empty($corpInfo)){
            $error_msg = '退回物流公司['. $reshipInfo['return_logi_name'] .']不存在,请在OMS系统里添加物流公司。';
            
            //log
            $this->__operLogObj->write_log('reship@ome', $reship_id, $error_msg);
            
            //没有找到物流公司,给个默认值
            $corpInfo['type'] = 'JD';
        }
        
        //更新服务单退货物流信息
        $processObj->update(array('logi_code'=>$corpInfo['type'], 'logi_no'=>$reshipInfo['return_logi_no']), array('reship_id'=>$reship_id));
        
        //log
        //$memo = '京东云交易服务单号,更新退回物流公司成功';
        //$this->__operLogObj->write_log('reship@ome', $reship_id, $memo);
        
        //request
        $rsp_error_msg = '';
        $result = ome_return_notice::updateLogistics($reship_id, $rsp_error_msg);
        if(!$result){
            $rsp_error_msg = '更新退回物流公司失败：'.$rsp_error_msg;
            
            //log
            $this->__operLogObj->write_log('reship@ome', $reship_id, $rsp_error_msg);
            
            //设置异常：更新退回物流信息失败
            $abnormal_status = ome_constants_reship_abnormal::__LOGISTICS_CODE;
            $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status | ". $abnormal_status ." WHERE reship_id=".$reship_id;
            $this->__reshipObj->db->exec($sql);
            
            return false;
        }else{
            //清除异常:同步WMS物流信息失败
            $abnormal_status = ome_constants_reship_abnormal::__LOGISTICS_CODE;
            if(($this->_reshipInfo['abnormal_status'] & $abnormal_status) ==  $abnormal_status){
                $sql = "UPDATE sdb_ome_reship SET abnormal_status=abnormal_status ^ ". $abnormal_status ." WHERE reship_id=".$reship_id;
                $this->__reshipObj->db->exec($sql);
            }
        }
        
        return false;
    }
    
    /**
     * [京东一件代发]获取订单对应的发货包裹信息
     *
     * @param int $order_id
     * @param array $skus
     * @param string $error_msg
     * @return array
     */
    public function get_delivery_package($order_id, $skus=array(), &$error_msg=null)
    {
        $deliveryObj = app::get('ome')->model('delivery');
        $packageObj = app::get('ome')->model('delivery_package');
        
        //关联发货单(获取已发货、已追回的发货单)
        $sql = "SELECT b.delivery_id,b.delivery_bn,b.is_wms_gift,b.wms_channel_id FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id ";
        $sql .= " WHERE a.order_id=". $order_id ." AND b.status IN('succ', 'return_back')";
        $dataList = $deliveryObj->db->select($sql);
        if(empty($dataList)){
            $error_msg = '没有找到关联发货单';
            return false;
        }
        
        //多个发货单
        $deliveryList = array();
        foreach ($dataList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            
            $deliveryList[$delivery_id] = $val;
        }
        
        //sku
        $product_ids = array();
        $product_list = array();
        if($skus){
            foreach ($skus as $key => $val)
            {
                $product_id = $val['product_id'];
                
                $product_ids[] = $product_id;
                
                $product_list[$product_id] = $val;
            }
        }
        
        //获取已发货、已追回的京东包裹单
        $dataList = $packageObj->getList('*', array('delivery_id'=>array_keys($deliveryList), 'status'=>array('delivery', 'return_back')));
        if(empty($dataList)){
            $error_msg = '没有找到关联的京东包裹。';
            return false;
        }
        
        $packageList = array();
        foreach ($dataList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            $product_id = $val['product_id']; //有赠品是0的场景
            $package_bn = $val['package_bn'];
            
            //过滤不需要的SKU包裹信息
            if($skus){
                if(!in_array($product_id, $product_ids)){
                    continue;
                }
            }
            
            //渠道ID
            $val['wms_channel_id'] = $deliveryList[$delivery_id]['wms_channel_id'];
            
            //发货单号
            $val['delivery_bn'] = $deliveryList[$delivery_id]['delivery_bn'];
            
            //[兼容]赠品没有维护OMS基础物料信息
            if($val['is_wms_gift'] == 'true' && empty($product_list[$product_id])){
                //退货物料名称
                $val['product_name'] = $val['bn'];
                
                //退货数量
                $val['number'] = $val['number'];
            }else{
                //退货物料名称
                $val['product_name'] = $product_list[$product_id]['product_name'];
                
                //退货数量
                $val['number'] = $product_list[$product_id]['num'];
            }
            
            //package
            if(empty($packageList[$package_bn])){
                $packageList[$package_bn] = array(
                        'package_id' => $val['package_id'],
                        'package_bn' => $val['package_bn'],
                        'delivery_id' => $val['delivery_id'],
                        'delivery_bn' => $val['delivery_bn'],
                        'logi_bn' => $val['logi_bn'], //物流编码
                        'logi_no' => $val['logi_no'], //物流单号
                        'status' => $val['status'], //状态
                        'is_wms_gift' => $val['is_wms_gift'], //是否WMS赠品
                        'wms_channel_id' => $val['wms_channel_id'], //渠道ID
                        'shipping_status' => $val['shipping_status'], //配送状态
                );
                
                //item
                $packageList[$package_bn]['items'][] = array(
                        'product_id' => $val['product_id'],
                        'bn' => $val['bn'],
                        'outer_sku' => $val['outer_sku'],
                        'number' => $val['number'],
                        'product_name' => $val['product_name'],
                );
            }else{
                //item
                $packageList[$package_bn]['items'][] = array(
                        'product_id' => $val['product_id'],
                        'bn' => $val['bn'],
                        'outer_sku' => $val['outer_sku'],
                        'number' => $val['number'],
                        'product_name' => $val['product_name'],
                );
            }
        }
        
        //销毁
        unset($dataList, $deliveryList, $product_ids, $product_list);
        
        return $packageList;
    }
    
    /**
     * 换货完成京东云交易创建新订单
     * @todo：现在京东只支持退A换A,不支持退A换B;
     *
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return boolean
     */
    public function createYjdfNewOrder(&$cursor_id, $params, &$error_msg=null)
    {
        $processObj = app::get('ome')->model('return_process');
        $orderObj = app::get('ome')->model('orders');
        
        //data
        $sdfdata = $params['sdfdata'];
        $reship_id = intval($sdfdata['reship_id']);
        
        //京东新订单信息
        $newOrderList = $sdfdata['newOrders'];
        if(empty($newOrderList)){
            $error_msg = '没有京东新订单信息';
            return false;
        }
        
        //退货单信息
        $reshipInfo = $this->__reshipObj->dump(array('reship_id'=>$reship_id), '*');
        if(empty($reshipInfo)){
            $error_msg = '同步京东审核意见失败：没有获取到退货单信息';
            return false;
        }
        
        if($reshipInfo['return_type'] != 'change'){
            $error_msg = '退货单不是换货类型';
            return false;
        }
        
        //订单信息
        $orderInfo = $orderObj->dump(array('order_id'=>$reshipInfo['order_id']), '*', array('order_objects'=>array('*',array('order_items'=>array('*')))));
        
        //create
        $errorList = array();
        foreach ($newOrderList as $key => $val)
        {
            $service_bn = $val['service_bn'];
            $new_package_bn = $val['new_package_bn'];
            
            //获取售后服务单信息
            $processInfo = $processObj->dump(array('reship_id'=>$reship_id, 'new_package_bn'=>$new_package_bn), '*');
            if(empty($processInfo)){
                $errorList[] = '没有获取到京东新订单信息';
                continue;
            }
            
            //check
            if(!in_array($processInfo['service_status'], array('finish'))){
                $errorList[] = '服务单号['. $processInfo['service_bn'] .']不是退货完成状态';
                continue;
            }
            
            //获取退货明细
            //@todo：现在京东只支持退A换A,不支持退A换B,所以新订单明细直接读取退货商品;
            $sql = "SELECT * FROM sdb_ome_return_process_items WHERE por_id=".$processInfo['por_id'];
            $returnItems = $this->__reshipObj->db->select($sql);
            if(empty($returnItems)){
                $errorList[] = '没有获取到售后服务单退货明细';
                continue;
            }
            
            //京东新订单号
            $orderInfo['new_package_bn'] = $new_package_bn;
            
            //换出商品明细
            $reshipInfo['items'] = $returnItems;
            
            //新信息主结构
            $newOrderInfo = $this->_formatOrderMaster($orderInfo, $reshipInfo, $error_msg);
            if(!$newOrderInfo){
                $errorList[] = $error_msg;
                continue;
            }
            
            //设置订单为暂停、异常(防止被系统自动审单)
            $newOrderInfo['pause'] = 'true';
            $newOrderInfo['abnormal'] = 'true';
            
            //创建OMS本地新订单
            $result = $orderObj->create_order($newOrderInfo);
            if(!$result){
                $error_msg = '换货创建新订单号['. $newOrderInfo['order_bn'] .']创建失败';
                $errorList[] = $error_msg;
                
                //log
                $this->__operLogObj->write_log('order_edit@ome', $orderInfo['order_id'], $error_msg);
                
                continue;
            }
            
            //自动生成发货单
            $delivery_id = $this->autoCreateDelivery($newOrderInfo['order_id'], $reshipInfo['order_id'], $error_msg);
            if(!$delivery_id){
                $errorList[] = ($error_msg ? $error_msg : '自动生成发货单失败。');
                
                //log
                $this->__operLogObj->write_log('order_edit@ome', $newOrderInfo['order_id'], $error_msg);
                
                continue;
            }
            
            //自动创建京东订单号
            $result = $this->_autoCreatePackage($delivery_id, $new_package_bn, $error_msg);
            if(!$result){
                $errorList[] = ($error_msg ? $error_msg : '生成京东订单号失败。');
                
                //log
                $this->__operLogObj->write_log('delivery_modify@ome', $delivery_id, $error_msg);
                
                continue;
            }
        }
        
        //error
        if($errorList){
            $error_msg = implode(';', $errorList);
            return false;
        }
        
        return false;
    }
    
    /**
     * [京东一件代发]生成新的OMS订单号
     * 
     * @param string $order_bn
     * @return string
     */
    public function _createOrderBn($order_bn)
    {
        $prefix = 'YJDF';
        
        return $prefix . $order_bn;
    }
    
    /**
     * 组织新订单主结构(现在没有PKG捆绑商品换货业务)
     * 
     * @param array $orderInfo 订单信息
     * @param array $reshipInfo 换货单信息
     * @param string $error_msg 错误信息
     * @return array
     */
    public function _formatOrderMaster($orderInfo, $reshipInfo, &$error_msg=null)
    {
        $orderObj = app::get('ome')->model('orders');
        
        //OMS新订单号
        $new_order_bn = $this->_createOrderBn($orderInfo['new_package_bn']);
        
        //check
        $checkOrder = $orderObj->dump(array('order_bn'=>$new_order_bn), 'order_id');
        if($checkOrder){
            $error_msg = '新订单号已经存在';
            return false;
        }
        
        //换出商品明细
        $exchangeItems = $reshipInfo['items'];
        $itemnum = count($exchangeItems);
        
        //收货地区
        if($reshipInfo['ship_area'] != ''){
            $reshipInfo['ship_area'] = str_replace('-', '/', $reshipInfo['ship_area']);
            kernel::single('eccommon_regions')->region_validate($reshipInfo['ship_area']);
            $ship_area = $reshipInfo['ship_area'];
        }else{
            $ship_area = $orderInfo['consignee']['area'];
        }
        
        //master
        $orderSdf = array(
                'order_bn' => $new_order_bn,
                'order_type' => 'platform', //平台自发货：生成发货单不判断库存
                'createway' => 'yjdf', //订单生成类型：云交易售后自建
                'member_id' => $orderInfo['member_id'],
                'currency' => 'CNY',
                'createtime' => time(),
                'last_modified' => time(),
                'confirm' => 'N',
                'status' => 'active',
                'pay_status' => '0',
                'ship_status' => '0',
                'is_delivery' => 'N',
                'shop_id' => $orderInfo['shop_id'],
                'itemnum' => $itemnum,
                'relate_order_bn' => $orderInfo['order_bn'], //关联订单号
                'shipping' => array(
                        'shipping_id' => $orderInfo['shipping']['shipping_id'],
                        'is_cod' => 'false',
                        'shipping_name' => $orderInfo['shipping']['shipping_name'], //配送方式
                        'cost_shipping' => 0, //配送费用
                        'is_protect' => $orderInfo['shipping']['is_protect'],
                        'cost_protect' => 0,
                ),
                'consignee' => array(
                        'name' => $reshipInfo['ship_name'] ? $reshipInfo['ship_name'] : $orderInfo['consignee']['name'],
                        'addr' => ($reshipInfo['ship_addr']!='') ? $reshipInfo['ship_addr'] : $orderInfo['consignee']['addr'],
                        'zip' => ($reshipInfo['ship_zip']!='') ? $reshipInfo['ship_zip'] : $orderInfo['consignee']['zip'],
                        'telephone'=>($reshipInfo['ship_tel']!='') ? $reshipInfo['ship_tel'] : $orderInfo['consignee']['telephone'],
                        'mobile'=>($reshipInfo['ship_mobile']!='') ? $reshipInfo['ship_mobile'] : $orderInfo['consignee']['mobile'],
                        'email'=>($reshipInfo['ship_email']!='') ? $reshipInfo['ship_email'] : $orderInfo['consignee']['email'],
                        'area' => $ship_area,
                        'r_time' => $orderInfo['consignee']['r_time'],
                ),
                'mark_type' => 'b1', //订单备注图标
                'source' => 'local', //来源
                'is_tax' => $orderInfo['is_tax'], //是否开发票
                'tax_title' => $orderInfo['tax_title'], //发票公司名称
                'shop_type' => $orderInfo['shop_type'], //店铺类型
        );
        
        //订单备注
        $mark_text = array(
                array(
                    'op_name' => 'system',
                    'op_time' => time(),
                    'op_content' => '售后换货，创建的换出订单。要求换货的订单('.$orderInfo['order_bn'].')',
                ),
        );
        
        if($reshipInfo['memo']){
            $user = app::get('desktop')->model('users')->getList('name', array('user_id'=>$reshipInfo['op_id']), 0, 1);
            $mark_text[] = array(
                    'op_name' => $user[0]['name'],
                    'op_time' => time(),
                    'op_content' => $reshipInfo['memo'],
            );
        }
        
        $orderSdf['mark_text'] = $mark_text;
        
        //订单商品明细
        $item_cost = 0;
        $order_objects = $this->_formatOrderItems($orderInfo, $reshipInfo, $item_cost);
        if(!$order_objects){
            return false;
        }
        
        $orderSdf['order_objects'] = $order_objects;
        
        //新订单金额
        $orderSdf['total_amount'] = $item_cost; //订单总额
        $orderSdf['final_amount'] = $item_cost;
        $orderSdf['cost_item'] = $item_cost; //商品金额
        $orderSdf['payed'] = $item_cost; //已付款金额
        
        //原始订单如果是开票的
//        if($Order_detail['is_tax'] == 'true'){
//            $rs_invoice_info = kernel::single('invoice_common')->getInvoiceInfoByOrderId($reshipInfo['order_id']);
//            $orderSdf["invoice_mode"] = $rs_invoice_info[0]["mode"]; //发票类型 0纸质 1电子
//            $orderSdf["business_type"] = $rs_invoice_info[0]["business_type"]; //客户类型
//            $orderSdf["ship_tax"] = $rs_invoice_info[0]["ship_tax"]; //客户税号
//        }
        
        return $orderSdf;
    }
    
    /**
     * 组织新订单商品明细(现在没有PKG捆绑商品换货业务)
     * 
     * @param array $orderInfo 订单信息
     * @param array $reshipInfo 换货单信息
     * @param int $item_cost 订单总金额
     * @param string $error_msg 错误信息
     * @return array
     */
    public function _formatOrderItems($orderInfo, $reshipInfo, &$item_cost=0, &$error_msg=null)
    {
        $ordObjectMdl = app::get('ome')->model('order_objects');
        $ordItemObj = app::get('ome')->model('order_items');
        
        $order_id = $orderInfo['order_id'];
        
        //换出商品明细
        $exchangeItems = $reshipInfo['items'];
        
        $bnList = array();
        foreach ($exchangeItems as $key => $val)
        {
            $bn = $val['bn'];
            $bnList[$bn] = $bn;
        }
        
        //销售物料信息
        $tempList = $ordObjectMdl->getList('*', array('order_id'=>$order_id, 'bn'=>$bnList));
        $ordObjectList = array_column($tempList, null, 'bn');
        
        //基础物料信息
        $tempList = $ordItemObj->getList('*', array('order_id'=>$order_id, 'bn'=>$bnList));
        $ordItemList = array_column($tempList, null, 'bn');
        
        //check
        if(empty($ordObjectList) || empty($ordItemList)){
            $error_msg = '没有获取到原订单商品明细';
            return false;
        }
        
        //换出商品明细
        $item_cost = 0;
        $orderObjects = array();
        $line_i = 0;
        foreach ($exchangeItems as $key => $val)
        {
            $bn = $val['bn'];
            
            $objectInfo = $ordObjectList[$bn];
            $itemInfo = $ordItemList[$bn];
            
            //obj单件商品价格
            $obj_divide_order_fee = bcdiv($objectInfo['divide_order_fee'], $objectInfo['quantity'], 2);
            
            //objects
            $orderObjects[$line_i] = array(
                    'order_id' => $order_id,
                    'obj_type' => $objectInfo['obj_type'], //商品类型
                    'oid' => $objectInfo['oid'], //子订单号
                    'goods_id' => $objectInfo['goods_id'],
                    'bn' => $objectInfo['bn'],
                    'name' => $objectInfo['name'],
                    'quantity' => $val['num'],
                    'price' => $obj_divide_order_fee, //原价
                    'amount' => $obj_divide_order_fee, //商品总额
                    'sale_price' => $obj_divide_order_fee, //销售金额
                    'divide_order_fee' => $obj_divide_order_fee, //分摊之后的实付金额
                    'part_mjz_discount' => 0.00, //优惠分摊
                    'weight' => 0.00,
                    'author_id' => $objectInfo['author_id'], //活动主播ID
                    'author_name' => $objectInfo['author_name'], //活动主播名
            );
            
            //items
            $orderObjects[$line_i]['order_items'][] = array(
                'order_id' => $order_id,
                'item_type' => $itemInfo['item_type'],
                'product_id' => $itemInfo['product_id'],
                'bn' => $val['bn'],
                'name' => $itemInfo['name'],
                'quantity' => $val['num'],
                'nums' => $val['num'],
                'cost' => 0.00,
                'price' => $obj_divide_order_fee, //原价
                'pmt_price' => 0.00,
                'sale_price' => $obj_divide_order_fee, //销售金额
                'amount' => $obj_divide_order_fee, //商品总额
                'weight' => 0.00,
                'divide_order_fee' => $obj_divide_order_fee, //分摊之后的实付金额
                'part_mjz_discount' => 0.00, //优惠分摊
            );
            
            //订单总金额
            $item_cost += $obj_divide_order_fee;
            
            $line_i++;
        }
        
        return $orderObjects;
    }
    
    /**
     * OMS自动生成发货单
     *
     * @param intval $order_id 订单ID
     * @param string $error_msg 错误信息
     * @return bool
     */
    function autoCreateDelivery($new_order_id, $old_order_id, &$error_msg=null)
    {
        $orderObj = app::get('ome')->model('orders');
        $orderItemObj = app::get('ome')->model('order_items');
        $branchObj = app::get('ome')->model('branch');
        
        $platformLib = kernel::single('ome_order_platform');
        
        //check
        if(empty($new_order_id) || empty($old_order_id)){
            $error_msg = '自动生成发货单失败：没有订单ID信息';
            return false;
        }
        
        //订单信息
        $orderInfo = $orderObj->dump(array('order_id'=>$new_order_id), '*');
        
        //check
        if ($orderInfo['order_type'] != 'platform') {
            $error_msg = '自动生成发货单失败：不是平台自发货订单';
            return false;
        }
        
        //订单明细
        $itemList = $orderItemObj->getList('*', array('order_id'=>$new_order_id, 'delete'=>'false'));
        $orderInfo['order_items'] = array_column($itemList, null, 'item_id');
        
        //[原始订单号]指定仓库
        $sql = "SELECT b.delivery_id,b.delivery_bn,b.branch_id FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id ";
        $sql .= " WHERE a.order_id=". $old_order_id ." AND b.status IN('succ', 'return_back')";
        $deliveryInfo = $orderObj->db->selectrow($sql);
        if(empty($deliveryInfo)){
            $error_msg = '自动生成发货单失败：没有找原订单对应发货单信息';
            return false;
        }
        
        $branchInfo = $branchObj->db_dump(array('branch_id'=>$deliveryInfo['branch_id'], 'check_permission'=>'false'), 'branch_id,branch_bn');
        $store_code = $branchInfo['branch_bn'];
        
        //自动生成发货单&&自动发货
        //$platformLib->deliveryConsign($order_id);
        
        //自动生成发货单
        $result = $platformLib->addDelivery($orderInfo, $store_code);
        
        if(!$result[0]){
            $error_msg = '自动生成发货单失败：'.$result[1];
            return false;
        }
        
        //创建的发货单ID
        $delivery_id = $result[2];
        
        //log
        $this->__operLogObj->write_log('order_edit@ome', $new_order_id, '自动创建发货单成功');
        
        return $delivery_id;
    }
    
    /**
     * OMS自动生成京东订单号
     *
     * @param intval $delivery_id 发货单ID
     * @param string $new_package_bn 京东订单号
     * @param string $error_msg 错误信息
     * @return bool
     */
    function _autoCreatePackage($delivery_id, $new_package_bn, &$error_msg=null)
    {
        $deliveryObj = app::get('ome')->model('delivery');
        $dlyItemObj = app::get('ome')->model('delivery_items');
        $channelObj = app::get('channel')->model('channel');
        
        $branchLib = kernel::single('ome_branch');
        
        //发货单信息
        $deliveryInfo = $deliveryObj->dump(array('delivery_id'=>$delivery_id), '*');
        if(empty($deliveryInfo)){
            $error_msg = '生成京东订单号失败：没有发货单信息delivery_id:'.$delivery_id;
            return false;
        }
        
        //更新第三方单号
        $deliveryObj->update(array('original_delivery_bn'=>$new_package_bn), array('delivery_id'=>$delivery_id));
        
        //发货单明细
        $tempList = $dlyItemObj->getList('*', array('delivery_id'=>$delivery_id));
        if(empty($tempList)){
            $error_msg = '生成京东订单号失败：没有发货单明细delivery_bn:'.$deliveryInfo['delivery_bn'];
            return false;
        }
        
        $itemList = array();
        foreach ($tempList as $key => $val)
        {
            $itemList[] = array(
                    'logistics' => '',
                    'logi_no' => '',
                    'main_sku_id' => '',
                    'product_bn' => $val['bn'],
                    'num' => $val['number'],
                    'serial_list' => '',
                    'outbound_time' => '',
                    'price' => 10, //固定写个值
                    'type' => 'goods',
            );
        }
        
        //wms
        $wms_id = $branchLib->getWmsIdById($deliveryInfo['branch_id']);
        if(empty($wms_id)){
            $error_msg = '生成京东订单号失败：没有仓库信息delivery_bn:'.$deliveryInfo['delivery_bn'];
            return false;
        }
        
        $channelInfo = $channelObj->dump(array('channel_id'=>$wms_id), '*');
        if(empty($channelInfo)){
            $error_msg = '生成京东订单号失败：没有第三方仓储信息delivery_bn:'.$deliveryInfo['delivery_bn'];
            return false;
        }
        
        $node_id = $channelInfo['node_id'];
        
        //post
        $postdata = array (
            'logistics' => '',
            'status' => 'ACCEPT',
            'oid' => $new_package_bn, //京东订单号
            'orderId' => $new_package_bn,
            'rootOrderId' => '0',
            'weight' => '',
            //'msg_id' => '78778BEBC0A8004A9BF65FDB1EFDD677',
            'app_id' => 'ecos.ome',
            'sign' => '07CA620E3F40106372E5D5766A2F7D5C',
            'volume' => '',
            'node_id' => $node_id,
            'date' => date('Y-m-d H:i:s', time()),
            'operate_time' => date('Y-m-d H:i:s', time()),
            //'to_node_id' => '1184160733',
            //'from_node_id' => '2021052703',
            'business_type' => $channelInfo['node_type'],
            'delivery_bn' => $deliveryInfo['delivery_bn'],
            'remark' => '',
            'logi_no' => '',
            'item' => json_encode($itemList),
            'warehouse' => '',
            'method' => 'wms.delivery.status_update',
            'task' => '6014531cb7b9e1b4cbb00178dbc41e61',
        );
        $result = kernel::single('erpapi_router_response')->set_node_id($node_id)->set_api_name('wms.delivery.status_update')->dispatch($postdata);
        if($result['rsp'] != 'succ'){
            $error_msg = '创建京东订单号失败:'.$result['msg'];
            return false;
        }
        
        //log
        $this->__operLogObj->write_log('delivery_modify@ome', $delivery_id, '自动创建京东订单号成功[orderId：'. $new_package_bn .']');
        
        return true;
    }
    
    /**
     * 匹配退货地址中的省、市、区
     * 
     * @param string $shop_id
     * @param string $address
     * @return array
     */
    public function mappingAddressCity($shop_id, $address)
    {
        $addressObj = app::get('ome')->model('return_address');
        
        //check
        if(empty($shop_id) || empty($address)){
            return false;
        }
        
        //md5
        $md5_address = md5($address);
        
        //查询历史记录(@todo：当推送给抖音平台成功后,会更新contact_id固定为1)
        $sql = "SELECT address_id,province,city,country FROM sdb_ome_return_address ";
        $sql .= " WHERE shop_id='". $shop_id ."' AND md5_address='". $md5_address ."' AND city!='' AND country!='' ORDER BY contact_id DESC";
        $regionInfo = $addressObj->db->selectrow($sql);
        if(empty($regionInfo)){
            //[分词]匹配详细地址中的省、市、区
            $speedLib = kernel::single('ome_groupon_plugin_speed');
            $regionInfo = $speedLib->getMappingJdRegions($address);
        }
        
        return $regionInfo;
    }
    
    /**
     * 获取退货单关联退货地址列表
     * 
     * @param int $reship_id
     * @return array
     */
    public function getReturnAddressList($reship_id)
    {
        $addressObj = app::get('ome')->model('return_address');
        
        //address
        $addressList = $addressObj->getList('*', array('reship_id'=>$reship_id));
        if(empty($addressList)){
            return false;
        }
        
        //list
        foreach($addressList as $key => $val)
        {
            //寄件地址已经推送平台成功
            if($val['contact_id']){
                continue;
            }
            
            //省、市、区为空则允许编辑
            if(empty($val['province']) || empty($val['city']) || empty($val['country'])){
                $addressList[$key]['isEdit'] = 'true';
            }
        }
        
        return $addressList;
    }
    
    /**
     * 校验京东云交易MQ退款消息的退款金额
     * 
     * @param array $reshipInfo 退货单信息
     * @param int $refund_apply_id 退款申请单ID
     * @return bool
     */
    public function checkMqRefundAmount($reshipInfo, $refund_apply_id=0)
    {
        $processObj = app::get('ome')->model('return_process');
        $refundApplyMdl = app::get('ome')->model('refund_apply');
        
        $this->_reship_id = $reshipInfo['reship_id'];
        $this->_order_id = $reshipInfo['order_id'];
        
        //实际需要退款的金额
        $totalmoney = $reshipInfo['totalmoney'];
        
        //服务单信息
        $processList = $processObj->getList('por_id,reship_id,service_bn,wms_refund_fee', array('reship_id'=>$this->_reship_id));
        if(empty($processList)){
            return false;
        }
        
        //京东云交易退款金额
        $wms_refund_fee = 0;
        foreach ($processList as $key => $val)
        {
            if(empty($val['wms_refund_fee'])){
                continue;
            }
            
            $wms_refund_fee = bcadd($wms_refund_fee, $val['wms_refund_fee'], 3);
        }
        
        //获取发货单上SKU货品推送给京东采购金额小计
        $error_msg = '';
        $purchaseAmount = $this->getDeliveryPurchaseAmount($error_msg);
        if($purchaseAmount === false){
            //logs
            $error_msg = 'AG自动退款失败：'.$error_msg;
            $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
            
            //标记为异常
            if($refund_apply_id){
                $refundApplyMdl->set_abnormal_status($refund_apply_id, ome_constants_refundapply_abnormal::__AG_NO_AUTO_REFUND);
                
                $this->__operLogObj->write_log('refund_apply@ome', $refund_apply_id, $error_msg);
            }
            
            return false;
        }
        
        //比较京东退款金额与OMS退款金额
        if(bccomp($purchaseAmount, $wms_refund_fee, 3) != 0){
            $error_msg = 'AG自动退款失败：京东云交易与OMS退款金额不一致,不允许AG自动退款!';
            
            //logs
            $this->__operLogObj->write_log('reship@ome', $this->_reship_id, $error_msg);
            
            //标记为异常
            if($refund_apply_id){
                $refundApplyMdl->set_abnormal_status($refund_apply_id, ome_constants_refundapply_abnormal::__AG_NO_AUTO_REFUND);
                
                $this->__operLogObj->write_log('refund_apply@ome', $refund_apply_id, $error_msg);
            }
            
            return false;
        }
        
        return true;
    }
}