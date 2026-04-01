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
 * 订单接口处理
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_response_process_order
{
    //店铺ID
    public $_shop_id = null;
    
    // 是否更新订单中的商品
    protected $_is_update_change_goods = false;
    
    /**
     * undocumented function
     * 
     * @return void
     * @author 
     * */

    function __construct()
    {
        $this->_plugin_broker = kernel::single('erpapi_shop_response_plugins_order_broker');
    }

    /**
     * 订单接收
     * 
     * @return void
     * @author 
     * */
    public function add($ordersdf)
    {

        
        if ($ordersdf['order_id']) {
            if ($ordersdf['flag'] == 'close'){
                
                return $this->_closeorder($ordersdf);
            }else{
                return   $this->_updateOrder($ordersdf);
            }
        } else {
            return   $this->_createOrder($ordersdf);
        }
    }

    /**
     * 创建订单
     * 
     * @return void
     * @author 
     * */
    private function _createOrder($ordersdf)
    {
        $plugins = $ordersdf['plugins']; unset($ordersdf['plugins']);

        if (!$ordersdf) return array('rsp'=>'fail','msg'=>'创建失败：格式化数据为空');
        
        $errorinfo = '';
        $rs = app::get('ome')->model('orders')->create_order($ordersdf, $errorinfo);
        if (!$rs) {
            if (!$errorinfo) {
                $errorinfo = kernel::database()->errorinfo();
            }

            return array('rsp'=>'fail','msg'=>$errorinfo ? $errorinfo : '订单已经存在','data'=>array('tid'=>$ordersdf['order_bn']));
        }
        
        //plugin
        if ($plugins && is_array($plugins)) {
            foreach ($plugins as $name => $params) {
                if ($ordersdf['order_id'] && $params){
                    //增加订单order_objects层数据
                    if(in_array($name, array('ordertype', 'luckybag'))){
                        $params['order_objects'] = $ordersdf['order_objects'];
                    }
                    
                    //exec
                    kernel::single('erpapi_shop_response_plugins_order_'.$name)->postCreate($ordersdf['order_id'],$params);
                }
            }
        }

        // 更新订单下载时间
        $shopModel = app::get('ome')->model('shop');
        $shopModel->update(array('last_download_time'=>time()), array('shop_id'=>$ordersdf['shop_id']));
        
        if($service = kernel::servicelist('service.order')){
            foreach ($service as $instance){
                if (method_exists($instance, 'after_add_order')){
                    $instance->after_add_order($ordersdf);
                }
            }
        }
        
        //[SAP创建]调用service进行SAP创建
        foreach(kernel::servicelist('ome.service.order.create.after') as $object)
        {
            if(method_exists($object, 'after_create')){
                $object->after_create($ordersdf);
            }
        }
        
        // 抖音全链路 已转单
        kernel::single('ome_event_trigger_shop_order')->order_message_produce($ordersdf['order_id'],'build');
        
        # 闪购订单确认
        kernel::single('ome_event_trigger_shop_order')->confirmFlashOrder($ordersdf['order_id']);
        
        return array('rsp'=>'succ','msg'=>'返回值：订单创建成功！订单ID：'.$ordersdf['order_id'],'data'=>array('tid'=>$ordersdf['order_bn']));
    }

    /**
     * 更新订单
     * 
     * @return void
     * @author 
     * */
    private function _updateOrder($ordersdf)
    {
        $plugins = $ordersdf['plugins']; unset($ordersdf['plugins']);
        $newordersdf = $ordersdf; unset($newordersdf['status'],$newordersdf['order_id']);

        $modelOrder = app::get('ome')->model('orders');
        $upFilter = array('order_id' => $ordersdf['order_id']);

        if ($newordersdf) {
            if ($newordersdf['pay_status'] == '6' && $newordersdf['pause']=='true'){
                $modelOrder->pauseOrder($ordersdf['order_id']);
            }
            $newordersdf['order_id'] = $ordersdf['order_id'];
            $plainData = $modelOrder->sdf_to_plain($newordersdf);
            $rs = $modelOrder->update($plainData, $upFilter);
            $modelOrder->_save_depends($newordersdf);
        }

        // 保存后插件处理
        if ($plugins && is_array($plugins)) {
            foreach ($plugins as $name => $params) {
                if ($ordersdf['order_id'] && $params){
                    kernel::single('erpapi_shop_response_plugins_order_'.$name)->postUpdate($ordersdf['order_id'],$params);
                }

            }
        }

        if ($ordersdf) $this->_afterUpdate($ordersdf);
        
        $msg = $ordersdf['status'] == 'dead' ? '订单取消成功' : '订单更新成功,影响行数:'.intval($rs);
        
        //shop_id
        if($this->_shop_id){
            $ordersdf['shop_id'] = $this->_shop_id;
        }
        
        // [更新]预约订单的相关状态
        $error_msg = '';
        kernel::single('ome_order_reservation')->operateReservationOrder($ordersdf, $error_msg);
        
        // 是否订单商品被修改
        $is_change_order_goods = false;
        if($this->_is_update_change_goods){
            $is_change_order_goods = true;
        }
        
        //[SAP更新]调用service进行SAP更新
        foreach(kernel::servicelist('ome.service.order.update.after') as $object)
        {
            if(method_exists($object, 'afterUpdateOrder')){
                $object->afterUpdateOrder($ordersdf['order_id'], $is_change_order_goods);
            }
        }
        return array('rsp' => 'succ','msg'=>$msg,);
    }

    /**
     * 更新后，是否撤回发货单
     * 
     * @return void
     * @author 
     * */
    private function _afterUpdate($ordersdf)
    {
        $orderModel = app::get('ome')->model('orders');

        // 如果订单已经拆分
        $tgorder = $this->_plugin_broker->getOrder($ordersdf['order_id']);

        $oOrder_sync = app::get('ome')->model('order_sync_status');
        $oOrder_sync->update(array('sync_status'=>'2'),array('order_id'=>$tgorder['order_id']));

        //更新订单hash值
        $this->combinehash_update($tgorder);
        kernel::single('console_map_order')->getLocation($tgorder['order_id']);
        
        //shop_id
        $this->_shop_id = $tgorder['shop_id'];

        //检测source_status变化，如果从WAIT_SELLER_SEND_GOODS变为其他状态，触发重新审单
        // 注意：在luban/response/order.php的_operationSel()中，source_status已经被更新到数据库
        // 所以这里需要从$ordersdf中获取更新前的old_source_status和更新后的new_source_status进行比较
        if (isset($ordersdf['old_source_status']) && isset($ordersdf['new_source_status'])) {
            $oldSourceStatus = $ordersdf['old_source_status'];
            $newSourceStatus = $ordersdf['new_source_status'];
            
            // 只处理 Luban 订单
            if ($tgorder['shop_type'] == 'luban' && $tgorder['createway'] == 'matrix') {
                // 如果从 WAIT_SELLER_SEND_GOODS 变为其他状态
                if ($oldSourceStatus == 'WAIT_SELLER_SEND_GOODS' 
                    && $newSourceStatus != 'WAIT_SELLER_SEND_GOODS') {
                    
                    // 触发重新审单（延迟5分钟）
                    if ($tgorder['status'] == 'active' 
                        && in_array($tgorder['process_status'], array('unconfirmed', 'confirmed'))
                        && $tgorder['pay_status'] == '1' 
                        && $tgorder['ship_status'] == '0') {
                        
                        //订单恢复暂停状态
                        $orderModel->renewOrder($tgorder['order_id'], '平台订单状态更新自动');
                        
                        //延迟5分钟自动重新路由审核订单
                        $sdf = array(
                            'op_type' => 'timing_confirm', 
                            'timing_time' => strtotime('5 minutes'), 
                            'memo' => '平台订单状态从WAIT_SELLER_SEND_GOODS更新为'.$newSourceStatus.'，触发重新路由', 
                            'is_check_last_time' => true
                        );
                        kernel::single('ome_order')->auto_order_combine($tgorder['order_id'], $sdf);
                    }
                }
            }
        }
        
        //是否允许自动审核订单
        $is_review_order = false;
        $cnAuto = 'false'; // 未开启自动审单的情况下是否允许自动审核订单
        $renewMemo = '';
        
        // 写一下日志
        $write_log = array();
        if ($ordersdf['consignee']) {
            $write_log[] = array(
                'obj_id'    => $tgorder['order_id'],
                'obj_name'  => $tgorder['order_bn'],
                'operation' => 'order_modify@ome',
                'memo'      => "订单收货人信息被修改",
            );
            
            $is_review_order = true;
            $is_change_address = true;
            $renewMemo .= '地址变更';
            $cnAuto = 'true';
        }

        if ($ordersdf['mark_text']) {
            $write_log[] = array(
                'obj_id'    => $tgorder['order_id'],
                'obj_name'  => $tgorder['order_bn'],
                'operation' => 'order_modify@ome',
                'memo'      => "订单商家备注被修改",
            );
            
            $is_review_order = false;
        }

        if ($ordersdf['mark_type']) {
            $write_log[] = array(
                'obj_id'    => $tgorder['order_id'],
                'obj_name'  => $tgorder['order_bn'],
                'operation' => 'order_modify@ome',
                'memo'      => "订单旗标被修改",
            );
            
            $is_review_order = false;
        }

        if ($ordersdf['order_objects']) {
            $write_log[] = array(
                'obj_id'    => $tgorder['order_id'],
                'obj_name'  => $tgorder['order_bn'],
                'operation' => 'order_modify@ome',
                'memo'      => "前端订单商品信息修改",
            );
            
            $is_review_order = false;
            
            // 订单商品信息修改
            $this->_is_update_change_goods = true;
        }

        if ($ordersdf['order_bool_type'] & ome_order_bool_type::__UPDATEITEM_CODE) {
            $write_log[] = array(
                'obj_id'    => $tgorder['order_id'],
                'obj_name'  => $tgorder['order_bn'],
                'operation' => 'order_modify@ome',
                'memo'      => "原sku: ".$ordersdf['old_sku']."已更换为: ".$ordersdf['change_sku'],
            );
            
            $is_review_order = true;
            $is_change_goods = true;
            $renewMemo .= 'sku替换';
        }

        if ($ordersdf['is_delivery'] == 'Y') {
            $renewMemo .= '平台发货状态变更';
            $is_review_order = true;
        }

        $opObj = app::get('ome')->model('operation_log');
        if ($write_log) {
            $opObj->batch_write_log2($write_log);
        }
        
        //失败订单并且福袋分配错误信息
        if($ordersdf['is_fail'] && $ordersdf['luckybag_error']){
            $opObj->write_log('order_modify@ome', $tgorder['order_id'], $ordersdf['luckybag_error']);
        }
        
        // 如果到付已经发货更新销售单上的时候
        if ($ordersdf['paytime'] && ($tgorder['is_cod'] == 'true' || $ordersdf['use_before_payed']) && $tgorder['ship_status'] == '1') {
            $saleModel = app::get('ome')->model('sales');
            $saleModel->update(array('paytime'=>$ordersdf['paytime']),array('order_id'=>$tgorder['order_id']));
        }

        // 非活动订单，已发货，部分发货不做处理
        if ($tgorder['status'] != 'active' || !in_array($tgorder['ship_status'],array('0','2')) ) return true;

        if ($ordersdf['pay_status'] == '5' || $ordersdf['status'] == 'dead') {
            if($tgorder['ship_status'] == 0){
                //cancel
                $orderModel->cancel($tgorder['order_id'],'订单全额退款后取消！',false,'async', false);
            } elseif($tgorder['ship_status'] == 2) {
               $this->_reback_serial($tgorder['order_id']);
               $orderModel->rebackDeliveryByOrderId($tgorder['order_id']);
               
               if($tgorder['process_status'] == 'splited'){
                   $orderModel->update(array('process_status'=>'splitting'), array('order_id'=>$tgorder['order_id']));
               }
           }
            return true;
        }

        // 如果已经拆分
        if (in_array($tgorder['process_status'],array('splited','splitting'))) {
            
            $reback_delivery = false;
            if ($ordersdf['consignee']['name'] 
                || $ordersdf['consignee']['area'] 
                || $ordersdf['consignee']['addr'] 
                || $ordersdf['consignee']['telephone'] 
                || $ordersdf['consignee']['mobile']) {   // 收货人信息发生变更
                $reback_delivery = true;
            }elseif ($ordersdf['pay_status'] == '4') { // 部分退款  
                $reback_delivery = true;
            }elseif ($ordersdf['order_objects']) { // 明细发生变更
                $checkObj  = array('bn','quantity');
                $checkItem = array('bn','quantity','delete');

                foreach ($ordersdf['order_objects'] as $order_object) {
                    if (array_intersect($checkObj, array_keys($order_object))) {
                        $reback_delivery = true; break;
                    }
                    if ($order_object['order_items']) 
                        foreach ($order_object['order_items'] as $order_item) {
                            if (array_intersect($checkItem,array_keys($order_item))) {
                                $reback_delivery = true; break 2;
                            }
                        }
                }
            }

            if ($reback_delivery) {
                $this->_reback_serial($tgorder['order_id']);
                $orderModel->rebackDeliveryByOrderId($tgorder['order_id']);
                
                //订单恢复暂停状态
                $orderModel->renewOrder($tgorder['order_id'], '平台更新订单,撤消发货单成功');
                
                //延迟5分钟自动重新路由审核订单
                //@todo：防止有退款，并发导致明细未删除生成了发货单;
                $sdf = array('op_type'=>'timing_confirm', 'timing_time'=>strtotime('5 minutes'), 'memo'=>'更新订单撤销发货单后重新路由', 'is_check_last_time'=>true);
                $cnAuto == 'true' && $sdf['cnAuto'] = 'true';
                kernel::single('ome_order')->auto_order_combine($tgorder['order_id'], $sdf);
                
                return true;
            }

            // 有备注
            $orderPauseAllow = app::get('ome')->getConf('ome.orderpause.to.syncmarktext');
            if ($ordersdf['mark_text'] && $orderPauseAllow !== 'false') {
                $orderModel->pauseOrder($tgorder['order_id']);

                return true;
            }
        }
        
        //千牛修改收货人地址
        if(in_array($tgorder['process_status'], array('unconfirmed', 'confirmed'))){
            if($tgorder['pay_status'] == '1' && $tgorder['status'] == 'active' && $tgorder['ship_status'] == '0'){
                if($is_review_order) {
                    //订单恢复暂停状态
                    $orderModel->renewOrder($tgorder['order_id'], $renewMemo.'自动');
                    
                    //延迟5分钟自动重新路由审核订单
                    //@todo：防止有退款，并发导致明细未删除生成了发货单;
                    $sdf = array('op_type'=>'timing_confirm', 'timing_time'=>strtotime('5 minutes'), 'memo'=>'订单更新后重新路由', 'is_check_last_time'=>true);
                    
                    //@todo：当系统设置里未开启[是否开启系统自动审核]时，未调用延迟自动审核;
                    $sdf['cnAuto'] = 'true';
                    
                    kernel::single('ome_order')->auto_order_combine($tgorder['order_id'], $sdf);
                }
            }
        }
        
        // [大家电]收货人信息或SKU商品变更,重新进行预约
        if($is_change_address){
            kernel::single('ome_order_reservation')->againReservation($tgorder['order_id'], 'address_change');
        }elseif($is_change_goods){
            kernel::single('ome_order_reservation')->againReservation($tgorder['order_id'], 'goods_change');
        }
        
        return true;
    }

    private function _reback_serial($order_id)
    {
        $serialObj    = app::get('ome')->model('product_serial');
        $serialLogObj = app::get('ome')->model('product_serial_log');

        $sql = sprintf("SELECT dord.delivery_id FROM sdb_ome_delivery_order AS dord LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id) WHERE dord.order_id=%s AND (d.parent_id=0 OR d.is_bind='true') AND d.disabled='false' AND d.status IN('ready','progress')",$order_id);

        $rows = kernel::database()->select($sql);

        if (!$rows) return ;

        $deliveryIds = array_map('current',$rows);

        $filter = array(
            'act_type'  => '0',
            'bill_type' => '0',
            'bill_no'   => $deliveryIds,
        );
        $serialLogs = $serialLogObj->getList('item_id',$filter);

        if (!$serialLogs) return ;

        $itemIds = array();
        foreach ($serialLogs as $value) {
            $itemIds[] = $value['item_id'];
        }

        $serialObj->update(array('status'=>'0'),array('item_id'=>$itemIds,'status'=>'1'));
    }

        /**
     * status_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function status_update($sdf)
    {
        if (!$sdf['order_id']) return array('rsp'=>'fail','msg'=>'订单ID不存在');

        $orderModel = app::get('ome')->model('orders');

        $affect_row = $orderModel->update($sdf,array('order_id'=>$sdf['order_id']));

        if ($sdf['status'] == 'dead') {
            $orderModel->cancel($sdf['order_id'],'前端订单取消',false,'async', false);
        }

        return array('rsp'=>'succ','msg'=>'更新成功，影响行数：'.$affect_row);
    }

    /**
     * pay_status_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function pay_status_update($sdf)
    {
        if (!$sdf['order_id']) return array('rsp'=>'fail','msg'=>'订单ID不存在');

        $orderModel = app::get('ome')->model('orders');

        $affect_row = $orderModel->update($sdf,array('order_id'=>$sdf['order_id']));

        return array('rsp'=>'succ','msg'=>'更新成功，影响行数：'.$affect_row);
    }

    /**
     * ship_status_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function ship_status_update($sdf)
    {
        if (!$sdf['order_id']) return array('rsp'=>'fail','msg'=>'订单ID不存在');

        $orderModel = app::get('ome')->model('orders');

        $affect_row = $orderModel->update($sdf,array('order_id'=>$sdf['order_id']));

        return array('rsp'=>'succ','msg'=>'更新成功，影响行数：'.$affect_row);
    }

    /**
     * custom_mark_add
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function custom_mark_add($sdf)
    {
        if (!$sdf['order_id']) return array('rsp'=>'fail','msg'=>'订单ID不存在');

        $orderModel = app::get('ome')->model('orders');

        $affect_row = $orderModel->update($sdf,array('order_id'=>$sdf['order_id']));

        return array('rsp'=>'succ','msg'=>'更新成功，影响行数：'.$affect_row);
    }

    /**
     * custom_mark_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function custom_mark_update($sdf)
    {
        if (!$sdf['order_id']) return array('rsp'=>'fail','msg'=>'订单ID不存在');

        $orderModel = app::get('ome')->model('orders');

        $affect_row = $orderModel->update($sdf,array('order_id'=>$sdf['order_id']));

        return array('rsp'=>'succ','msg'=>'更新成功，影响行数：'.$affect_row);
    }

    /**
     * memo_add
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function memo_add($sdf)
    {
        if (!$sdf['order_id']) return array('rsp'=>'fail','msg'=>'订单ID不存在');

        $orderModel = app::get('ome')->model('orders');

        $affect_row = $orderModel->update($sdf,array('order_id'=>$sdf['order_id']));

        return array('rsp'=>'succ','msg'=>'更新成功，影响行数：'.$affect_row);
    }

    /**
     * memo_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function memo_update($sdf)
    {
        if (!$sdf['order_id']) return array('rsp'=>'fail','msg'=>'订单ID不存在');

        $orderModel = app::get('ome')->model('orders');

        $affect_row = $orderModel->update($sdf,array('order_id'=>$sdf['order_id']));

        return array('rsp'=>'succ','msg'=>'更新成功，影响行数：'.$affect_row);
    }

    /**
     * payment_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function payment_update($sdf)
    {
        if (!$sdf['order_id']) return array('rsp'=>'fail','msg'=>'订单ID不存在');

        $orderModel = app::get('ome')->model('orders');

        $affect_row = $orderModel->update($sdf,array('order_id'=>$sdf['order_id']));

        return array('rsp'=>'succ','msg'=>'更新成功，影响行数：'.$affect_row);
    }

    private function combinehash_update($sdf){
        if(empty($sdf['order_id'])) {
            return;
        }
        //组织hash的计算入参
        $params = array(
            'order_id' => $sdf['order_id'],
            'member_id' => $sdf['member_id'],
            'shop_id' => $sdf['shop_id'],
            'shop_type' => $sdf['shop_type'],
            'order_source' => $sdf['order_source'],
            'order_bn' => $sdf['order_bn'],
            'self_delivery' => $sdf['self_delivery'],
            'order_bool_type' => $sdf['order_bool_type'],
            'shipping' => array (
                'is_cod' => $sdf['is_cod'],
                'ship_name' => $sdf['shipping'],
            ),
            'consignee' => array(
                'name' => $sdf['ship_name'],
                'area' => $sdf['ship_area'],
                'addr' => $sdf['ship_addr'],
                'telephone' => $sdf['ship_tel'],
                'mobile' => $sdf['ship_mobile'],
            ),
        );
        
        //淘宝物流升级
        $orderExtendMdl = app::get('ome')->model('order_extend');
        $orderExt = $orderExtendMdl->db_dump(array('order_id'=>$sdf['order_id']),'cpup_service,extend_field');
        if ($orderExt) {
            $params['cpup_service'] = $orderExt['cpup_service'];
            if ($orderExt['extend_field']) {
                $params['extend_field'] = json_decode($orderExt['extend_field'], 1);
            }
        }
        $orderObjectMdl = app::get('ome')->model('order_objects');
        $orderObjectList = $orderObjectMdl->getList('*',array('order_id'=>$sdf['order_id']));
        if ($orderObjectList) {
            $params['order_objects'] = $orderObjectList;
        }
        
        $orderLib = kernel::single('ome_order');
        $combieHashIdxInfo = $orderLib->genOrderCombieHashIdx($params);
        if($combieHashIdxInfo){
            $update['order_combine_hash'] = $combieHashIdxInfo['combine_hash'];
            $update['order_combine_idx'] = $combieHashIdxInfo['combine_idx'];
        }

        $orderModel = app::get('ome')->model('orders');
        $orderModel->update($update,array('order_id'=>$sdf['order_id']));
    }

    private function _closeorder($sdf){

        kernel::single('ome_batch_order')->create_refund($sdf['order_id']);
        
        // [更新]预约订单取消状态
        $error_msg = '';
        kernel::single('ome_order_reservation')->operateReservationOrder($sdf, $error_msg);
        
        $msg = '订单取消成功';

        return array('rsp' => 'succ','msg'=>$msg,);
    }
    
}
