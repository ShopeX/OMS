<?php
/**
 * 拼多多消费者付费送货上门审单物流校验
 *
 * 仅处理带 SOMS_ZFSHSM 标签的拼多多订单。所有仓库校验承运商白名单，
 * OMS 自有仓额外校验物流公司使用启用中的拼多多电子面单；第三方 WMS
 * 不校验电子面单来源，避免依赖第三方在 OMS 中不存在的面单配置。
 */
class ome_order_platform_vop_bufa extends ome_abstract
{
    /**
     * 唯品会 JITX 补寄订单
     *
     * @param array $ordersdf
     * @param array $channelInfo 绑定的店铺信息
     * @return bool
     */
    public function validateBufaOrder($ordersdf, $channelInfo=null)
    {
        if (strtolower($channelInfo['shop_type']) != 'vop' || empty($ordersdf['order_bn'])) {
            return $this->error('无效的唯品会订单信息!');
        }
        
        // 唯品会平台推送给矩阵：order_type = '5'
        if (strtolower($ordersdf['order_type']) != 'reshipping') {
            return $this->error('不是唯品会JITX补寄订单!');
        }
        
        $data = [];
        
        // 扩展信息
        if(isset($ordersdf['extend_field']) && $ordersdf['extend_field']){
            // 原平台主订单号
            if(isset($ordersdf['extend_field']['old_order_sn']) && $ordersdf['extend_field']['old_order_sn']){
                // 关联订单号（原主订单号）
                $data['relate_order_bn'] = $ordersdf['extend_field']['old_order_sn'];
                
//                // 平台单号
//                $data['platform_order_bn'] = $ordersdf['extend_field']['old_order_sn'];
            }
            
            // mark_text
            if(isset($ordersdf['extend_field']['csc_remark']) && $ordersdf['extend_field']['csc_remark']){
                // 关联订单号（原主订单号）
                $data['mark_text'] = $ordersdf['mark_text'] .'；'. $ordersdf['extend_field']['csc_remark'];
            }
        }
        
        // 配送信息
        if(isset($ordersdf['shipping']) && $ordersdf['shipping']){
            // 物流公司编码
            if(isset($ordersdf['shipping']['shipping_name'])){
                $logi_code = trim($ordersdf['shipping']['shipping_name']);
                $logi_code = strtolower($logi_code);
                
                // OMS物流公司编码
                if($this->logiMapCode[$logi_code]){
                    $logi_code = $this->logiMapCode[$logi_code]['code'];
                    
                    // 获取物流公司信息
                    $dlyCorpInfo = app::get('ome')->model('dly_corp')->dump(array('type'=>$logi_code), '*');
                    
                    $data['logi_id'] = intval($dlyCorpInfo['corp_id']);
                    $data['logi_code'] = $logi_code;
                }
            }
        }
        
        return $this->succ('匹配唯品会JITX补寄订单成功!', $data);
    }
    
    /**
     * 检测货单是否为：发唯品会 JITX 补寄订单
     *
     * @param array $sdf
     * @return bool
     */
    public function isDeliveryJitxBufaOrder($sdf)
    {
        // check
        if(empty($sdf['delivery_id']) || empty($sdf['shop_id'])){
            return $this->error('无效的唯品会发货单信息!');
        }
        
        $orderMdl = app::get('ome')->model('orders');
        $labelLib = kernel::single('ome_bill_label');
        
        // 通过delivery_id获取关联的订单(获取已发货、已追回的发货单)
        $dlySql = "SELECT a.order_id,b.delivery_id,b.delivery_bn,b.branch_id FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id ";
        $dlySql .= " WHERE a.delivery_id=". $sdf['delivery_id'];
        $deliveryList = $orderMdl->db->select($dlySql);
        if(empty($deliveryList)){
            return $this->error('没有发货单关联的订单!');
        }
        
        // check
        $orderIds = array_column($deliveryList, 'order_id');
        if(empty($orderIds)){
            return $this->error('无效的唯品会订单ID!');
        }
        
        // 获取订单信息
        $orderList = $orderMdl->getList('order_id,order_bn,order_type,shop_id', ['order_id'=>$orderIds]);
        if(empty($orderList)){
            return $this->error('未获取到唯品会订单信息!');
        }
        
        // check
        $is_jix_bufa_order = false;
        $error_msg = '';
        foreach ($orderList as $orderKey => $orderInfo)
        {
            $order_id = $orderInfo['order_id'];
            
            // check
            if($orderInfo['order_type'] != 'bufa'){
                continue;
            }
            
            // 检查订单是否打标[补寄]标签
            $labelInfo = $labelLib->getBillLabelInfo($order_id, 'order', 'SOMS_BUFA_PKG', $error_msg);
            if(!empty($labelInfo)){
                $is_jix_bufa_order = true;
            }
        }
        
        if($is_jix_bufa_order){
            return $this->succ('当前发货单是：唯品会JITX补寄订单!');
        }else{
            return $this->error('该发货单非唯品会JITX补寄订单!');
        }
    }
    
    /**
     * 获取唯品会JITX补寄订单的物流单号
     *
     * @param $sdf
     * @return void
     */
    public function getVopWaybillNumber($sdf)
    {
        // check
        if(empty($sdf['delivery_id']) || empty($sdf['shop_id'])){
            return $this->error('无效的唯品会发货单信息。');
        }
        
        $orderMdl = app::get('ome')->model('orders');
        $orderObjectMdl = app::get('ome')->model('order_objects');
        $labelLib = kernel::single('ome_bill_label');
        
        // 通过delivery_id获取关联的订单(获取已发货、已追回的发货单)
        $dlySql = "SELECT a.order_id,b.delivery_id,b.delivery_bn,b.branch_id FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id ";
        $dlySql .= " WHERE a.delivery_id=". $sdf['delivery_id'];
        $deliveryList = $orderMdl->db->select($dlySql);
        if(empty($deliveryList)){
            return $this->error('没有发货单关联的订单。');
        }
        
        // check
        $orderIds = array_column($deliveryList, 'order_id');
        if(empty($orderIds)){
            return $this->error('无效的唯品会订单ID!');
        }
        
        // 获取订单信息
        $orderList = $orderMdl->getList('order_id,order_bn,order_type,shop_id', ['order_id'=>$orderIds, 'order_type'=>'bufa']);
        if(empty($orderList)){
            return $this->error('未获取到唯品会订单信息。');
        }
        
        $shop_id = $sdf['shop_id'];
        $order_bn = $orderList[0]['order_bn'];
        $order_id = $orderList[0]['order_id'];
        
        // 检查订单是否打标[补寄]标签
        $labelInfo = $labelLib->getBillLabelInfo($order_id, 'order', 'SOMS_BUFA_PKG', $error_msg);
        if(empty($labelInfo)){
            return $this->error('订单号：'. $order_bn .'不是唯品会JITX补寄订单。');
        }
        
        // 通过订单object层明细，获取预选仓编码
        $objectList = $orderObjectMdl->getList('obj_id,order_id,store_code,bn,oid,quantity', ['order_id'=>$order_id, 'delete'=>'false']);
        if(empty($objectList)){
            return $this->error('订单号：'. $order_bn .'没有可发货的销售物料明细。');
        }
        
        $storeCodes = array_column($objectList, 'store_code');
        $storeCodes = array_unique($storeCodes);
        if(empty($storeCodes)){
            return $this->error('订单号：'. $order_bn .'没有预选仓编码。');
        }
        
        // reset
        $sdf['order_bn'] = $order_bn;
        $sdf['store_sn'] = $storeCodes[0];
        
        // 请求平台获取物流单号
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->logistics_getWaybillNumber($sdf);
        if($result['rsp'] == 'succ'){
            $resultData = [];
            $transport_no = '';
            if(isset($result['data'])){
                $resultData = $result['data'];
                if(is_string($resultData)){
                    $resultData = json_decode($resultData, true);
                }
                
                if(isset($resultData['transport_no']) && $resultData['transport_no']){
                    $transport_no = $resultData['transport_no'];
                }elseif(isset($resultData['result']['transport_nos']) && $resultData['result']['transport_nos']){
                    $transport_no = $resultData['result']['transport_nos'];
                }
            }
            
            if($transport_no){
                return $this->succ('获取唯品会JITX补寄订单的物流单号成功!', ['transport_no'=>$transport_no]);
            }
        }
        
        $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['res']);
        return $this->error('获取唯品会JITX补寄订单的物流单号失败：'. $error_msg);
    }
}
