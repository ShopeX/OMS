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
 * 发货通知单推送通知仓库封装类
 *
 * @author xiayuanjun
 * @Time: 2016-04-20
 * @version 0.1
 */

class ome_delivery_notice{

    /**
     * @description 修改配送信息
     * @access public
     * @param int $delivery_id 发货单ID
     * @return void
     */
    static public function create($delivery_id) {
        $operationLogObj = app::get('ome')->model('operation_log');
        $operationLogObj->write_log('delivery_modify@ome',$delivery_id,"发货通知单推送仓库开始");
        
        // 生成发货数据
        $nodeStart = microtime(true);
        $original_data = kernel::single('ome_event_data_delivery')->generate($delivery_id);
        $nodeTime = microtime(true) - $nodeStart;
        
        // 记录generate性能数据
        ome_api_log::add_message('node', 'generate_delivery_data', $nodeTime, array(
            'delivery_id' => $delivery_id
        ));
        
        if (empty($original_data)){
            return array(true);
        }

        // 更新同步状态
        $consoleDlyLib = kernel::single('console_delivery');
        $consoleDlyLib->update_sync_status($delivery_id, 'send');

        // 根据仓库识别是否门店仓还是电商仓
        $channelSelectStartTime = microtime(true);
        $store_id = kernel::single('ome_branch')->isStoreBranch($original_data['branch_id']);
        if($store_id){
            $channel_type = 'store';
            $channel_id = $store_id;
        }else{
            $wms_id = kernel::single('ome_branch')->getWmsIdById($original_data['branch_id']);
            list($rs, $rsData) = self::_get_electron_logi_no($wms_id,$original_data);
            if(!$rs) {
                $consoleDlyLib->update_sync_status($delivery_id, 'send_fail', $rsData['msg']);
                return array(true);
            }

            $channel_type = 'wms';
            $channel_id = $wms_id;
        }
        $channelSelectTime = microtime(true) - $channelSelectStartTime;
        
        // 记录运单号获取性能数据
        ome_api_log::add_message('logistics', 'get_waybill_code', $channelSelectTime, array(
            'delivery_id' => $delivery_id,
            'branch_id' => $original_data['branch_id'],
            'channel_type' => $channel_type,
            'channel_id' => $channel_id
        ));

        // 创建发货通知
        $nodeStart = microtime(true);
        $dlyTriggerLib = kernel::single('ome_event_trigger_delivery');
        $result = $dlyTriggerLib->create($channel_type,$channel_id,$original_data,false);
        $nodeTime = microtime(true) - $nodeStart;
        
        // 记录create性能数据
        ome_api_log::add_message('node', 'create_delivery_trigger', $nodeTime, array(
            'delivery_id' => $delivery_id,
            'channel_type' => $channel_type,
            'channel_id' => $channel_id
        ));
        
        if($result['rsp'] == 'fail'){
            $msg = "失败. 原因:".$result['msg'];
        }else{
            $msg ="成功.";

            // WMS相关操作（订单码处理 + 通知）
            $wmsProcessStartTime = microtime(true);
            
            // 处理WMS订单码
            if (isset($result['data']['wms_order_code'])) {
                $dlyExtObj = app::get('console')->model('delivery_extension');
                $ext_data['delivery_bn'] = $original_data['outer_delivery_bn'];
                $ext_data['original_delivery_bn'] = $result['data']['wms_order_code'];
                $dlyExtObj->create($ext_data);
            }
            
            // WMS通知
            kernel::single('ome_wms')->notify($original_data['outer_delivery_bn']);
            
            $wmsProcessTime = microtime(true) - $wmsProcessStartTime;
            
            // 记录WMS处理性能数据
            ome_api_log::add_message('wms', 'wms_process', $wmsProcessTime, array(
                'delivery_id' => $delivery_id,
                'outer_delivery_bn' => $original_data['outer_delivery_bn'],
                'wms_order_code' => $result['data']['wms_order_code'] ?? ''
            ));
        }

        if(isset($result['msg_id'])){
            $msg .=" msg_id:".$result['msg_id'];
        }

        // 更新最终同步状态
        if ($result['rsp'] != 'running') {
            $consoleDlyLib->update_sync_status($delivery_id, $result['rsp'] == 'succ' ? 'send_succ' : 'send_fail');
        }

        $operationLogObj->write_log('delivery_modify@ome',$delivery_id,"推送结果:".$msg);
        
        return array(true);
    }

    static public function isAutoNoticeWms($delivery_id) {
        $orderId = app::get('ome')->model('delivery')->getOrderIdByDeliveryId($delivery_id);

        $orderMdl = app::get('ome')->model('orders');

        $order = $orderMdl->db_dump(['order_id' => $orderId], 'order_id,source_status,createway,shop_type');

        if (!$order) {
            return false;
        }
        
        if($order['shop_type'] == 'luban' && $order['createway'] == 'matrix'){
            if (!in_array($order['source_status'], array('SELLER_READY_GOODS', 'SELLER_CONSIGNED_PART', 'TRADE_FINISHED', 'WAIT_BUYER_CONFIRM_GOODS'))) {
                app::get('ome')->model('delivery')->update(['sync_status'=>'6'], ['delivery_id'=>$delivery_id]);
                return false;
            }
        }
        
        return true;
    }

    public function sendAgain($arrDeliveryId)
    {
        //设置发起二次推送的时效
        $syncTime = 30 * 86400;
        $startTime = time() - $syncTime;
        
        //list
        $dlyArr = app::get('ome')->model('delivery')->getList('delivery_id,sync_send_succ_times,create_time', ['delivery_id'=>$arrDeliveryId, 'status'=>['ready','progress']]);
        $arrDly = array_column($dlyArr, null, 'delivery_id');
        foreach ($arrDeliveryId as $id) {
            if($arrDly[$id]) {
                if($arrDly[$id]['create_time'] < $startTime) {
                    app::get('ome')->model('operation_log')->write_log('delivery_modify@ome',$id,"发货单创建超过7天不再自动发起");
                    continue;
                }
                
                if(self::isAutoNoticeWms($id)) {
                    app::get('ome')->model('operation_log')->write_log('delivery_modify@ome',$id,"二次自动推送发起");
                    self::notification($id);
                } else {
                    $time = time() + 3600;
                    $task = array(
                        'obj_id'    => $id,
                        'obj_type'  => 'delivery_send_again',
                        'exec_time' => $time,
                    );
                    app::get('ome')->model('misc_task')->saveMiscTask($task);
                    app::get('ome')->model('operation_log')->write_log('delivery_modify@ome',$id,"二次自动推送因订单尚未备货故延时发起:".date('Y-m-d H:i', $time));
                }
                continue;
            }
            app::get('ome')->model('operation_log')->write_log('delivery_modify@ome',$id,"二次自动推送因状态变更不发起");
        }
    }

    static public function cancel($params, $sync = false){

        $dly_id = $params['delivery_id'];
        $data = array();
        $data['outer_delivery_id'] = $dly_id;
        $data['outer_delivery_bn'] = $params['delivery_bn'];
        $error_msg = '';

        $dlyObj = app::get('ome')->model("delivery");
        $branchObj = app::get('ome')->model("branch");

        //根据仓库识别是否门店仓还是电商仓
        $store_id = kernel::single('ome_branch')->isStoreBranch($params['branch_id']);
        if($store_id){
            $channel_type = 'store';
            $channel_id = $store_id;
        }else{
            //$branch =$branchObj->getList('branch_bn',array('branch_id'=>$params['branch_id']),0,1);
            $branch =$branchObj->db->selectrow("SELECT branch_bn,owner_code FROM sdb_ome_branch WHERE branch_id=".$params['branch_id']);
            $data['branch_bn'] =$branch['branch_bn'];
            $data['owner_code'] =$branch['owner_code'];
            $dlyExtObj = app::get('console')->model('delivery_extension');
            $dlyExtInfo = $dlyExtObj->dump(array('delivery_bn'=>$params['delivery_bn']),'original_delivery_bn');

            $data['original_delivery_bn'] = $dlyExtInfo['original_delivery_bn'];


            $deliveryInfo = $dlyObj->dump(array('delivery_id'=>$dly_id), 'delivery_id,sync_status,wms_channel_id');
            $data['sync_status']    = $deliveryInfo['sync_status'];
            $data['wms_channel_id'] = $deliveryInfo['wms_channel_id'];



            if(isset($params['wms_id'])){
                $wms_id = $params['wms_id'];
            }else{
                $branchLib = kernel::single('ome_branch');
                $wms_id = $branchLib->getWmsIdById($params['branch_id']);
            }

            $channel_type = 'wms';
            $channel_id = $wms_id;
        }

        //订单号  店铺
        $deliveryOrderObj = app::get('ome')->model('delivery_order');
        $dly_order = $deliveryOrderObj->dump(array('delivery_id'=>$dly_id),'order_id');
        $order_id = $dly_order['order_id'];
        $orderObj = app::get('ome')->model('orders');
        $order_detail = $orderObj->dump($order_id, 'order_bn,shop_type,shop_id');
        $data['shop_type'] = $order_detail['shop_type'];

        $data['order_bn'] = $order_detail['order_bn'];
        $shopObj = app::get('ome')->model('shop');


        $shopInfo = $shopObj->dump($order_detail['shop_id'],'shop_bn,name');

        $data['shop_code'] = isset($shopInfo['shop_bn']) ? $shopInfo['shop_bn'] : '';
        $dlyTriggerLib = kernel::single('ome_event_trigger_delivery');
        $rs = $dlyTriggerLib->cancel($channel_type,$channel_id,$data,$sync);

        if ($rs['rsp'] == 'succ'){
            if(!$store_id && $rs['data']){
                !is_array($rs['data']) && $rs['data'] = @json_decode($rs['data'],true);
                if ($rs['data']['response']['message'] == 'Picked'){
                    kernel::single('ome_delivery_freeze')->add($dly_id);
                }
            }
            //取消合单标识
            $label_code   = 'SOMS_COMBINE_ORDER';
            $combineLabel = kernel::single('ome_bill_label')->getBillLabelInfo($dly_id, 'ome_delivery', $label_code);
            if ($combineLabel) {
                $labelAll = app::get('omeauto')->model('order_labels')->getList('*', ['label_code' => $label_code]);
                if ($labelAll) {
                    $labelAll = array_column($labelAll, 'label_id');
                    kernel::single('ome_bill_label')->delLabelFromBillId($dly_id, $labelAll, 'ome_delivery', $error_msg);
                    $deliveryOrderList = app::get('ome')->model('delivery_order')->getList('order_id', ['delivery_id' => $dly_id]);
                    foreach ($deliveryOrderList as $val) {
                        kernel::single('ome_bill_label')->delLabelFromBillId($val['order_id'], $labelAll, 'order', $error_msg);
                    }
                }
            }
            
            // 发送发货单撤销WMS通知邮件
            self::_sendDeliveryCancelWmsNotify($dly_id);

        } else {
            kernel::single('console_delivery')->update_sync_status($dly_id, 'cancel_fail', $rs['msg']);
        }
        return $rs;
    }

    static public function cut($params){
        $dly = app::get('ome')->model('delivery')->db_dump(['logi_no'=>$params['logi_no']]);
        if(empty($dly)) {
            return ['rsp'=>'fail', 'msg'=>'未找到物流单：'.$params['logi_no'].'的发货单'];
        }
        $dly_id = $dly['delivery_id'];
        $data = array();
        $data['outer_delivery_id'] = $dly_id;
        $data['outer_delivery_bn'] = $dly['delivery_bn'];
        $data['branch_id'] = $dly['branch_id'];

        $dlyObj = app::get('ome')->model("delivery");
        $branchObj = app::get('ome')->model("branch");

        //根据仓库识别是否门店仓还是电商仓
        $store_id = kernel::single('ome_branch')->isStoreBranch($dly['branch_id']);
        if($store_id){
            return ['rsp'=>'succ', 'msg'=>'门店无拦截'];
        }else{
            //$branch =$branchObj->getList('branch_bn',array('branch_id'=>$params['branch_id']),0,1);
            $branch =$branchObj->db->selectrow("SELECT branch_bn FROM sdb_ome_branch WHERE branch_id=".$dly['branch_id']);
            $data['branch_bn'] =$branch['branch_bn'];
            $dlyExtObj = app::get('console')->model('delivery_extension');
            $dlyExtInfo = $dlyExtObj->dump(array('delivery_bn'=>$dly['delivery_bn']),'original_delivery_bn');
            $data['original_delivery_bn'] = $dlyExtInfo['original_delivery_bn'];
            $branchLib = kernel::single('ome_branch');
            $wms_id = $branchLib->getWmsIdById($dly['branch_id']);
            $channel_type = 'wms';
            $channel_id = $wms_id;
        }

        $wmsMdl = app::get('wmsmgr')->model('wms');
        $wms = $wmsMdl->dump($wms_id, 'channel_id,crop_config,node_type');
        if (isset($wms['crop_config']['saleorder_callback']) && $wms['crop_config']['saleorder_callback'] == '0') {
            return ['rsp'=>'succ', 'msg'=>'配送拦截未开启'];
        }

        $dlyTriggerLib = kernel::single('ome_event_trigger_delivery');
        $rs = $dlyTriggerLib->cut($channel_type,$channel_id,$data);
        if($rs['msg'] == '接口方法不存在' || strpos($rs['msg'], '重复请求') !== false) {
            $rs['rsp'] = 'succ';
        }
        return $rs;
    }

    static public function pause($params, $sync = false){

        $dly_id = $params['delivery_id'];
        $data['outer_delivery_bn'] = $params['delivery_bn'];

        //根据仓库识别是否门店仓还是电商仓
        $store_id = kernel::single('ome_branch')->isStoreBranch($params['branch_id']);
        if($store_id){
            $channel_type = 'store';
            $channel_id = $store_id;
        }else{
            if(isset($params['wms_id'])){
                $wms_id = $params['wms_id'];
            }else{
                $branchLib = kernel::single('ome_branch');
                $wms_id = $branchLib->getWmsIdById($params['branch_id']);
            }

            $channel_type = 'wms';
            $channel_id = $wms_id;
        }

        $dlyTriggerLib = kernel::single('ome_event_trigger_delivery');
        return $dlyTriggerLib->pause($channel_type,$channel_id,$data,$sync);
    }

    static public function renew($params, $sync = false){

        $dly_id = $params['delivery_id'];
        $data['outer_delivery_bn'] = $params['delivery_bn'];
        $dlyExtObj = app::get('console')->model('delivery_extension');
        $dlyExtInfo = $dlyExtObj->dump(array('delivery_bn'=>$params['delivery_bn']),'original_delivery_bn');

        //根据仓库识别是否门店仓还是电商仓
        $store_id = kernel::single('ome_branch')->isStoreBranch($params['branch_id']);
        if($store_id){
            $channel_type = 'store';
            $channel_id = $store_id;
        }else{
            if(isset($params['wms_id'])){
                $wms_id = $params['wms_id'];
            }else{
                $branchLib = kernel::single('ome_branch');
                $wms_id = $branchLib->getWmsIdById($params['branch_id']);
            }

            $channel_type = 'wms';
            $channel_id = $wms_id;
        }

        $dlyTriggerLib = kernel::single('ome_event_trigger_delivery');
        return $dlyTriggerLib->renew($channel_type,$channel_id,$data,$sync);
    }

    /**
     * 查询发货单状态
     *
     * @return void
     * @author 
     **/
    public function searchDelivery($delivery_id)
    {
        $delivery = app::get('ome')->model('delivery')->db_dump($delivery_id);

        return self::search($delivery);
    }

    static public function search($params, $sync = false){

        $dly_id = $params['delivery_id'];

        $dlyExtObj = app::get('console')->model('delivery_extension');
        $dlyExtInfo = $dlyExtObj->dump(array('delivery_bn'=>$params['delivery_bn']),'original_delivery_bn');
        $data = array(
            'delivery_bn'=>$params['delivery_bn'],
            'out_order_code'=>$dlyExtInfo['original_delivery_bn'],
            'obj_id'    =>  $params['obj_id'],
        );
        $branchLib = kernel::single('ome_branch');
        if(isset($params['wms_id'])){
            $wms_id = $params['wms_id'];
        }else{
            
            $wms_id = $branchLib->getWmsIdById($params['branch_id']);
        }

        //订单号  店铺
        $deliveryOrderObj = app::get('ome')->model('delivery_order');
        $dly_order = $deliveryOrderObj->dump(array('delivery_id'=>$dly_id),'order_id');
        $order_id = $dly_order['order_id'];
       
        $orderObj = app::get('ome')->model('orders');
        $order_detail = $orderObj->dump($order_id, 'order_bn,shop_type,shop_id');
      
        $shopObj = app::get('ome')->model('shop');

        $shopInfo = $shopObj->dump($order_detail['shop_id'],'shop_type,shop_bn,name');

        $data['shop_type'] = $shopInfo['shop_type'];
        $data['shop_code'] = isset($shopInfo['shop_bn']) ? $shopInfo['shop_bn'] : '';
        
        $data['branch_bn'] = $branchLib->getBranchBnById($params['branch_id']);

        return kernel::single('erpapi_router_request')->set('wms',$wms_id)->delivery_search($data);
    }

    static public function _get_electron_logi_no($wms_id,&$original_data){

        //获取目前支持获取WMS 目前只支持科捷和伊藤忠 境宴和360
        $node_type = array('ilc','kejie','cnss','sku360','mixture', 'qimen');

        $channel_adapter = app::get('channel')->model('channel');

        $is_jitx = kernel::single('ome_order_bool_type')->isJITX($original_data['order_bool_type']);
        $is_jdlvmi = kernel::single('ome_order_bool_type')->isJDLVMI($original_data['order_bool_type']);

        $wms_detail = $channel_adapter->dump(array('channel_id'=>$wms_id,'node_id|noequal'=>''),'config');
        $wms_detail['config'] = $wms_detail['config'] ? @unserialize($wms_detail['config']) : [];

        if($original_data['shop_type'] == '360buy' && $is_jdlvmi){
            
            return [true, ['msg'=>'不需要获取']];
        }

        if ($is_jitx) {
            $is_logi_no = true;
            // 唯品会合单强制获取一次物流单号，用于更新父发货单和订单上的物流单号为同一个
            if ($original_data['is_vop_merge'] && in_array($wms_detail['node_type'],$node_type)) {
                $original_data['logi_no'] = '';
            }
        }

        if (kernel::single('ome_security_router',$original_data['shop_type'])->is_encrypt($original_data,'delivery')
            && is_array($wms_detail['config']['need_encrypt_logistics'])
            && $wms_detail['config']['need_encrypt_logistics'][$original_data['shop_type']]
        )  {
            if (!$original_data['logi_no'])  {
                $dlyCorpObj = app::get('ome')->model('dly_corp');
                $dlyCorp = $dlyCorpObj->dump($original_data['logi_id'], 'channel_id');
                app::get('ome')->model('dly_corp_channel')->getChannel($dlyCorp, array($original_data));
                if (!$dlyCorp['channel_id']) {
                    return [false, ['msg'=>'没有电子面单来源']];
                }
                $waybill = kernel::single('ome_event_trigger_logistics_electron')->directGetWaybill($original_data['delivery_id'], $dlyCorp['channel_id']);
                if ($waybill['succ']) {
                    $original_data['logi_no'] = $waybill['succ'][0]['logi_no'];
                }
            }
            if($original_data['logi_no']) {
                foreach($original_data['consignee'] as $k => $v) {
                    if(kernel::single('ome_security_hash')->get_code() == substr($v, -5)) {
                        $original_data['consignee'][$k] = kernel::single('ome_view_helper2')->modifier_ciphertext($v, 'order', $k);
                    }
                }
                $waybillExMdl = app::get('logisticsmanager')->model('waybill_extend');
                $waybill = app::get('logisticsmanager')->model('waybill')->db_dump(array ('waybill_number' => $original_data['logi_no']), 'id');
                if (!$waybill) {
                    return [false, ['msg'=>'电子面单查询失败']];
                }
                $original_data['waybill_extend'] = $waybillExMdl->db_dump(array ('waybill_id' => $waybill['id']));
            } else {
                return [false, ['msg'=>'电子面单获取失败']];
            }
            return [true, ['msg'=>'电子面单获取成功']];
        }
        if($original_data['logi_no'] && $is_jitx){
            $waybillExMdl = app::get('logisticsmanager')->model('waybill_extend');

            // 判断大头笔
            $waybill = app::get('logisticsmanager')->model('waybill')->dump(array ('waybill_number' => $original_data['logi_no']));

            if (!$waybill) {
                return [false, ['msg'=>'大头笔获取失败']];
            }

            $waybillEx = $waybillExMdl->dump(array ('waybill_id' => $waybill['id']));
            if (!$waybillEx) {
                $corp = app::get('ome')->model('dly_corp')->dump($original_data['logi_id'], 'channel_id');
                $data = kernel::single('erpapi_router_request')->set('logistics', $corp['channel_id'])->electron_waybillExtend($original_data);

                if ($data) {
                    $waybillEx = array (
                        'waybill_id'  => $waybill['id'],
                        'position_no' => $data['position_no'],
                        'json_packet' => $data['json_packet'],
                    );

                    $waybillExMdl->save($waybillEx);
                }
            }
        }
        return [true, ['msg'=>'处理完成']];
    }

    static public function cancelYJDF($orderId, $bmIds) {
        $rows = app::get('ome')->model('delivery_items_detail')
                    ->getList('delivery_id', array('order_id'=>$orderId,'product_id'=>$bmIds));
        if(empty($rows)){
            return array(false, '没有发货单');
        }
        $dly = app::get('ome')->model('delivery')->getList('delivery_id,delivery_bn,branch_id', array('delivery_id'=>array_map('current', $rows), 'process'=>'true','parent_id'=>'0'));
        if(empty($dly)) {
            return array(false, '没有已发货的发货单');
        }
        $wmsType = array();
        foreach ($dly as $v) {
            if(!isset($wmsType[$v['branch_id']])) {
                $wmsType[$v['branch_id']] = kernel::single('ome_branch')->getNodetypBybranchId($v['branch_id']);
                $wmsType[$v['branch_id']] || $wmsType[$v['branch_id']] = '';
            }
            if($wmsType[$v['branch_id']] == 'yjdf') {
                ome_delivery_notice::cancel($v, false);
            }
        }
    }
    
    /**
     * [京东云交易]通知提前发货
     * 
     * @param int $delivery_id
     * @return boolean
     */
    static public function notification($delivery_id, &$error_msg=null)
    {
        $deliveryObj = app::get('ome')->model('delivery');
        
        $branchLib = kernel::single('ome_branch');
        
        //发货单信息
        $deliveryInfo = $deliveryObj->dump(array('delivery_id'=>$delivery_id), 'delivery_id,delivery_bn,branch_id,wms_channel_id');
        if(empty($deliveryInfo)){
            $error_msg = '没有发货单信息';
            return false;
        }

        if(!self::isAutoNoticeWms($delivery_id)) {
            $error_msg = '订单未备货,不能推送';
            return false;
        }
        
        //branch_bn
        $branch_bn = $branchLib->getBranchBnById($deliveryInfo['branch_id']);
        
        //channel_id
        $channel_id = $branchLib->getWmsIdById($deliveryInfo['branch_id']);
        
        //params
        $params = array(
                'delivery_id' => $deliveryInfo['delivery_id'],
                'delivery_bn' => $deliveryInfo['delivery_bn'],
                'branch_id' => $deliveryInfo['branch_id'],
                'branch_bn' => $branch_bn,
                'wms_channel_id' => $deliveryInfo['wms_channel_id'],
        );
        
        //request
        $result = kernel::single('erpapi_router_request')->set('wms', $channel_id)->delivery_makedly($params);
        
        return $result;
    }

    static public function notify($delivery_id) {

        $deliveryMdl = app::get('ome')->model('delivery');
        $deliverys = $deliveryMdl->db_dump(array('delivery_id'=>$delivery_id),'branch_id,delivery_bn,status,shop_id,shop_type,wms_status,delivery_time,sync_status');

        if(empty($deliverys)) return true;


        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $deliveryorder = $deliveryOrderModel->getList('order_id',array('delivery_id'=>$delivery_id));

        $orderIds =array_map('current',$deliveryorder); 

        $orderMdl = app::get('ome')->model('orders');

        $orders = $orderMdl->getlist('order_bn,shop_id,order_bool_type',array('order_id'=>$orderIds,'createway'=>'matrix','order_bool_type'=>ome_order_bool_type::__JDLVMI_CODE));

        if(empty($orders)) return true;

        $status_value = ome_wms::$wms_status;
        $status = $deliverys['status'];

        if($deliverys['wms_status']=='0' && in_array($deliverys['sync_status'],array('0','1'))){
           // $status = 'unsync';
        }
        $status_value = $status_value[$status];


        if(!$status_value) return true;

        if($deliverys['wms_status'] == end($status_value)) return true; //等于最后一个就不回写了

        if($deliverys['wms_status']!='0'){
            $key = array_search($deliverys['wms_status'], $status_value);//截取已经回写状态位
            if($key){
                $status_value = array_slice($status_value, $key+1); 
            }
            
        }


        foreach($orders as $v){

            $order_bool_type = $v['order_bool_type'];

            $is_jdlvmi = kernel::single('ome_order_bool_type')->isJDLVMI($order_bool_type);

            if(!$is_jdlvmi) continue;
            $time = time();
            foreach($status_value as $sv){
                $sdf = array(
                    'order_bn'      =>  $v['order_bn'],
                    'status'        =>  $sv,
                    'branch_id'     =>  $deliverys['branch_id'],
                    'delivery_time' =>$deliverys['delivery_time'],
                    'operatetime'   =>$time,

                );

                $time=$time+5;
                $rs = kernel::single('erpapi_router_request')->set('shop',$deliverys['shop_id'])->delivery_notify($sdf);

                if($rs['rsp'] == 'succ'){
                    $deliveryMdl->db->exec("UPDATE sdb_ome_delivery  set wms_status='".$sv."' WHERE delivery_id=".$delivery_id."");
                }

 
            }
            
        }
    }

    /**
     * 需要判断如果是合单都付尾款才可以通知
     * 
     */

    static public function notify_presale($order_id, &$error_msg=null){
        $branchLib = kernel::single('ome_branch');
        
        $orderObj = app::get('ome')->model('orders');
        $orders = $orderObj->dump(array('order_id'=>$order_id,'pay_status'=>'1','process_status'=>'splited','order_type'=>'presale'), 'order_id,order_bn,payed,total_amount,paytime');
        $logMdl = app::get('ome')->model('operation_log');
        if (!$orders) return false;
        $delivery_list = self::getDeliveryByorderId($order_id);
        if(empty($delivery_list)){
            $error_msg = '没有可操作的发货单';
            return false;
        }
        
        $isPrepayed = kernel::single('ome_bill_label_delivery')->isPrepayed($order_id);
       
        if(!$isPrepayed){
            return true;
        }
        //delivery
        foreach($delivery_list as $delivery)
        {
            $branch_id = $delivery['branch_id'];
            
            $wms_id = $branchLib->getWmsIdById($branch_id);
            //wms_id
           
            if(empty($wms_id)){
                continue;
            }
            $delivery_id = $delivery['delivery_id'];
            //没有提前推送的不打
            $isPrepackage = kernel::single('ome_bill_label_delivery')->isPrepackage($delivery_id);

            if(!$isPrepackage){
                continue;
            }
            
            //branch_bn
            $branch_bn = $branchLib->getBranchBnById($branch_id);
            
            //request
            $data = array(
                'delivery_bn' => $delivery['delivery_bn'],
                'delivery_id' => $delivery['delivery_id'],
                'branch_bn' => $branch_bn,
                'order_bn' => $orders['order_bn'], //订单号
                'payed' => $orders['payed'], //已付金额
                'total_amount' => $orders['total_amount'], //订单总额
                'paytime' => ($orders['paytime'] ? $orders['paytime'] : time()), //付款时间
            );
            $dlyTriggerLib = kernel::single('ome_event_trigger_delivery');
            $rs = $dlyTriggerLib->notify($wms_id, $data);
            $logMdl->write_log('delivery_modify@ome',$delivery_id,"预售订单付尾款推送");
        }
        
        return true;
    }

    static function getDeliveryByorderId($order_id){
        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $deliveryorder = $deliveryOrderModel->getList('delivery_id',array('order_id'=>$order_id));


        $deliveryIds =array_map('current',$deliveryorder); 

        $SQL= "SELECT d.delivery_id,d.delivery_bn,d.branch_id FROM sdb_ome_delivery as d  WHERE d.delivery_id in(".implode(',',$deliveryIds).") AND d.parent_id=0 AND d.status in('succ','ready','progress')";
     
        $delivery_list = $deliveryOrderModel->db->select($SQL);

       
        if ($delivery_list){
            
            $delivery_ids = array();
            foreach($delivery_list as $v){
                $delivery_ids[] = $v['delivery_id'];
            }

          
            return $delivery_list;

            
        }else{
            return array();
        }
    }
    
    /**
     * 发送发货单撤销WMS通知邮件
     * 
     * @param int $delivery_id 发货单ID
     * @return void
     */
    static public function _sendDeliveryCancelWmsNotify($delivery_id) {
        try {
            // 获取发货单信息
            $deliveryObj = app::get('ome')->model('delivery');
            $deliveryInfo = $deliveryObj->db_dump(['delivery_id' => $delivery_id], 'delivery_bn,branch_id,shop_type,shop_id');
            
            if (empty($deliveryInfo)) {
                return;
            }
            
            // 获取仓库信息，包括邮箱
            $branchObj = app::get('ome')->model('branch');
            $branchInfo = $branchObj->db_dump(['branch_id' => $deliveryInfo['branch_id']], 'name,email');
            
            // 获取发货单明细
            $deliveryItemsObj = app::get('ome')->model('delivery_items');
            $items = $deliveryItemsObj->getList('bn,product_name,number', ['delivery_id' => $delivery_id]);
            
            if (empty($items)) {
                return;
            }
            
            // 构建明细列表字符串
            $detail_list = '';
            foreach ($items as $index => $item) {
                $seq = $index + 1;
                $product_bn = $item['bn'];
                $product_name = $item['product_name'];
                $quantity = $item['number'];
                
                $detail_list .= "\r\n<br/>序号：<font color=\"warning\">{$seq}</font>，物料号：<font color=\"warning\">{$product_bn}</font>，物料名称：<font color=\"warning\">{$product_name}</font>，数量：<font color=\"warning\">{$quantity}</font>";
            }
            
            // 获取店铺信息
            $shopObj = app::get('ome')->model('shop');
            $shopInfo = $shopObj->dump($deliveryInfo['shop_id'], 'name');
            $shop_name = $shopInfo['name'] ?? '';
            
            // 构建通知参数
            $notifyParams = [
                'delivery_bn' => $deliveryInfo['delivery_bn'],
                'shop_type' => ome_shop_type::shop_name($deliveryInfo['shop_type']),
                'shop_name' => $shop_name,
                'detail_list' => $detail_list,
                'branch_name' => $branchInfo['name'] ?? '',
            ];
            
            // 如果仓库有邮箱，将仓库邮箱作为收件人
            if (!empty($branchInfo['email'])) {
                $notifyParams['receiver'] = explode(',', $branchInfo['email']);
            }
            
            // 发送通知
            $monitorNotifyObj = kernel::single('monitor_event_notify');
            $monitorNotifyObj->addNotify('delivery_cancel_wms', $notifyParams, true);
            
        } catch (Exception $e) {
            // 记录错误日志，但不影响主流程
        }
    }
    
}
