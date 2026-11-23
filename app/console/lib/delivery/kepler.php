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
 * 开普勒发货业务处理Lib类
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class console_delivery_kepler
{
    /**
     * Obj对象
     */
    private $__deliveryObj = null;
    private $__packageObj = null;
    private $__operLogObj = null;
    
    /**
     * 初始化
     */
    public function __construct()
    {
        //Obj
        $this->__deliveryObj = app::get('ome')->model('delivery');
        $this->__packageObj = app::get('ome')->model('delivery_package');
        $this->__operLogObj = app::get('ome')->model('operation_log');
    }
    
    /**
     * [重试发货]京东取消父单后,使用Queue队列进行子订单DELIVERY发货
     * @todo：防止京东同分同秒推送DELIVERY、ACCEPT状态;
     *
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return boolean
     */
    public function responseChildJdOrder(&$cursor_id, $params, &$error_msg=null)
    {
        //data
        $sdfdata = $params['sdfdata'];
        $delivery_id = intval($sdfdata['delivery_id']);
        $delivery_bn = $sdfdata['delivery_bn'];
        $package_bn = $sdfdata['package_bn'];
        $status = strtolower($sdfdata['status']);
        $node_id = $sdfdata['node_id'];
        
        //[防止并发]延迟2秒执行
        sleep(2);
        
        //check
        if($status != 'delivery'){
            return false;
        }
        
        if(empty($node_id)){
            $error_msg = '没有获取到node_id';
            return false;
        }
        
        //datalist
        $packageList = $this->__packageObj->getList('package_id,status', array('delivery_id'=>$delivery_id, 'package_bn'=>$package_bn));
        if(empty($packageList)){
            $error_msg = '没有获取到京东订单信息(package_bn:'. $package_bn .',delivery_id:'. $delivery_id .')';
            return false;
        }
        
        $isResponse = false;
        foreach ($packageList as $key => $val)
        {
            if(strtolower($val['status']) == 'accept'){
                $isResponse = true;
                break;
            }
        }
        
        //重试请求WMS京东订单发货
        if($isResponse){
            unset($sdfdata['sign'], $sdfdata['app_id'], $sdfdata['from_node_id'], $sdfdata['node_id'], $sdfdata['task'], $sdfdata['msg_id'], $sdfdata['certi_id']);
            
            //response
            $result = kernel::single('erpapi_router_response')->set_node_id($node_id)->set_api_name('wms.delivery.status_update')->dispatch($sdfdata);
            
            //log
            if($result['rsp'] == 'fail'){
                $this->__operLogObj->write_log('delivery_modify@ome', $delivery_id, '京东订单号['. $package_bn .']重试子单发货并取消父单失败('. $result['msg'] .')');
            }else{
                $this->__operLogObj->write_log('delivery_modify@ome', $delivery_id, '京东订单号['. $package_bn .']重试子单发货并取消父单');
            }
        }
        
        return false;
    }
    
    /**
     * 拉取接收京东云交易的发货同步日志
     * 
     * @param string $delivery_bn
     * @param array $keplerOrders 京东云交易订单
     * @param string $error_msg
     * @return array
     */
    public function getResponseJdDly($delivery_bn, $keplerOrders, &$error_msg=null)
    {
        $apiLogObj = app::get('ome')->model('api_log');
        
        //check
        if(empty($delivery_bn) || empty($keplerOrders)){
            $error_msg = '发货单：'. $delivery_bn .'，没有发货单信息';
            return false;
        }
        
        //重试推送京东订单号API发货记录
        $base_filter = array('status'=>'fail', 'api_type'=>'response', 'original_bn'=>$delivery_bn);
        $logList = $apiLogObj->getList('*', $base_filter);
        if(empty($logList)){
            $error_msg = '发货单：'. $delivery_bn .'，没有respone发货记录';
            return false;
        }
        
        //logList
        $responeList = array();
        foreach ($logList as $logKey => $logVal)
        {
            $log_id = $logVal['log_id'];
            
            //fail
            if($logVal['status'] != 'fail'){
                continue;
            }
            
            //日志标题
            $log_name = '发货单['. $delivery_bn .']DELIVERY';
            if(strpos($logVal['task_name'], $log_name) === false){
                continue;
            }
            
            //日志详细
            $apiLogInfo = $apiLogObj->dump($log_id);
            if(empty($apiLogInfo)){
                continue;
            }
            
            $message = $apiLogInfo['msg'];
            
            $tempData = explode('<hr/>', $message);
            $log_str = $tempData[0];
            $log_str = str_replace('接收参数：', '', $log_str);
            
            if(empty($log_str)){
                $error_msg = '发货单：'. $delivery_bn .'，没有API接收参数...';
                return false;
            }
            
            //转换成数组
            eval("\$tempData = ".$log_str.'; ');
            
            //check
            if(!is_array($tempData)){
                $error_msg = '发货单：'. $delivery_bn .'，API接收参数不是数组...';
                return false;
            }
            
            if(!in_array($tempData['status'], array('DELIVERY'))){
                continue;
            }
            
            $orderId = trim($tempData['orderId']);
            if(empty($keplerOrders[$orderId])){
                continue;
            }
            
            //response数据 
            $responeList[$orderId] = $tempData;
            
            //@todo：现在只需要推送一次发货即可
            return $responeList;
        }
        
        //return
        if(empty($responeList)){
            return false;
        }else{
            return $responeList;
        }
    }
    
    /**
     * 强制修复发货单
     * 
     * @param int $delivery_id
     * @param string $error_msg
     * @return bool
     */
    public function repairDelivery($delivery_id, &$error_msg=null)
    {
        $dlyOrderMdl = app::get('ome')->model('delivery_order');
        $channelObj = app::get('channel')->model('channel');
        
        $branchLib = kernel::single('ome_branch');
        $syncProductLib = kernel::single('ome_sync_product');
        $freeLib = kernel::single('console_stock_freeze');
        
        //delivery
        $deliveryInfo = $this->__deliveryObj->dump(array('delivery_id'=>$delivery_id), '*');
        if(empty($deliveryInfo)){
            $error_msg = '发货单不存在';
            return false;
        }
        $delivery_bn = $deliveryInfo['delivery_bn'];
        
        //check
        if(!in_array($deliveryInfo['status'], array('ready', 'progress'))){
            $error_msg = '发货单状态不正确,不能操作';
            return false;
        }
        
        //关联订单order_id
        $dlyOrderInfo = $dlyOrderMdl->dump(array('delivery_id'=>$delivery_id), '*');
        $order_id = $dlyOrderInfo['order_id'];
        
        //wms
        $wms_id = $branchLib->getWmsIdById($deliveryInfo['branch_id']);
        if(empty($wms_id)){
            $error_msg = '没有wms仓库信息';
            return false;
        }
        
        $channelInfo = $channelObj->dump(array('channel_id'=>$wms_id), '*');
        if(empty($channelInfo)){
            $error_msg = '没有wms第三方仓储信息';
            return false;
        }
        $node_id = $channelInfo['node_id'];
        
        //京东云交易订单明细
        $packageStatus = array();
        $packageList = $this->__packageObj->getList('*', array('delivery_id'=>$delivery_id));
        if(empty($packageList)){
            $error_msg = '京东云交易订单不存在';
            return false;
        }
        
        $keplerOrders = array();
        foreach ($packageList as $key => $val)
        {
            $package_bn = $val['package_bn'];
            $product_id = $val['product_id'];
            $status = strtolower($val['status']);
            
            //status
            if($status == 'cancel'){
                $packageStatus['cancel'][$product_id] = $package_bn;
            }elseif($status == 'delivery'){
                $packageStatus['delivery'][$product_id] = $package_bn;
                
                //已发货的京东订单号
                $keplerOrders[$package_bn] = $package_bn;
                
                //注销,防止更新拆分数量
                unset($packageStatus['cancel'][$product_id]);
            }else{
                $packageStatus['other'][$product_id] = $package_bn;
            }
        }
        
        //check
        if($packageStatus['other']){
            $error_msg = '京东云交易订单状态不允许修复。';
            return false;
        }elseif(empty($packageStatus['delivery']) || empty($packageStatus['cancel'])){
            $error_msg = '京东云交易订单状态不允许修复!';
            return false;
        }
        
        //获取已发货的京东订单号API发货记录
        $error_msg = '';
        $responseList = $this->getResponseJdDly($delivery_bn, $keplerOrders, $error_msg);
        if(empty($responseList)){
            $error_msg = '拉取京东云交易发货消息失败：'.$error_msg;
            return false;
        }
        
        //已发货的商品
        $productIds = array_keys($packageStatus['delivery']);
        
        //已取消的商品
        $cancelProductIds = array_keys($packageStatus['cancel']);
        
        //除了已发货的商品,其它都删除掉
        //删除发货单明细
        $delSql = "DELETE FROM sdb_ome_delivery_items WHERE delivery_id=". $delivery_id. " AND product_id NOT IN(". implode(',', $productIds) .")";
        $result = $this->__deliveryObj->db->exec($delSql);
        if(!$result){
            $error_msg = '订单号：'. $order_bn .'，删除发货单明细失败';
            return false;
        }
        
        //删除发货单主信息
        $delSql = "DELETE FROM sdb_ome_delivery_items_detail WHERE delivery_id=". $delivery_id. " AND product_id NOT IN(". implode(',', $productIds) .")";
        $result = $this->__deliveryObj->db->exec($delSql);
        if(!$result){
            $error_msg = '订单号：'. $order_bn .'，删除发货单详细记录失败';
            return false;
        }
        
        //更新订单明细拆分数量
        $updateSql = "UPDATE sdb_ome_order_items SET split_num=0 WHERE order_id=". $order_id ." AND product_id IN(". implode(',', $cancelProductIds) .")";
        $result = $this->__deliveryObj->db->exec($updateSql);
        
        //更新订单确认状态
        $updateSql = "UPDATE sdb_ome_orders SET process_status='splitting' WHERE order_id=". $order_id;
        $result = $this->__deliveryObj->db->exec($updateSql);
        
        //库存
        foreach($cancelProductIds as $key => $product_id)
        {
            //释放库存--重置商品的冻结库存
            $syncProductLib->reset_freeze($product_id);
            
            //释放库存--重置预占流水记录
            $result = $freeLib->reset_stock_freeze($product_id);
        }
        
        //[重试发货]推送京东订单发货API日志
        foreach ($responseList as $responseKey => $responseVal)
        {
            $result = kernel::single('erpapi_router_response')->set_node_id($node_id)->set_api_name('wms.delivery.status_update')->dispatch($responseVal);
            if($result['rsp'] != 'succ'){
                $error_msg = '发货单号：'. $delivery_bn .'，重试发货失败';
                return false;
            }
        }
        
        //log
        $this->__operLogObj->write_log('delivery_modify@ome', $delivery_id, '手工强制修复发货单成功');
        
        return true;
    }
}