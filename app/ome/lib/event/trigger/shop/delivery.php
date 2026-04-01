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
 * 发货单请求
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class ome_event_trigger_shop_delivery
{
    protected function _getDelivery($delivery_id){
        $deliveryFilter = array('delivery_id'=>$delivery_id,'parent_id'=>'0');
        $deliveryModel = app::get('ome')->model('delivery');
        $deliveryinfo = $deliveryModel->db_dump($deliveryFilter);
        if (!$deliveryinfo) return array();

        // 回写节点
        if($deliveryinfo['process'] != 'true') {
            $rb = app::get('ome')->getConf('ome.delivery.back_node');
            if ($rb == 'check') {
                if($deliveryinfo['verify'] != 'true') return array();
            } elseif ($rb == 'print') {
                if ( $deliveryinfo['expre_status'] != 'true' ) return array();
            } else {
                return array();
            }
        }

        $shipmentLogModel = app::get('ome')->model('shipment_log');
        $deliverySuccOrderBn = $shipmentLogModel->getList('orderBn', array('deliveryCode'=>$deliveryinfo['logi_no'], 'status'=>'succ'));
        $deliveryinfo['dly_succ_order'] = array_column($deliverySuccOrderBn, 'orderBn');
        // 发货单对应的订单
        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $deliveryOrderList = $deliveryOrderModel->getList('order_id',array('delivery_id'=>$delivery_id));

        $order_ids = array();
        foreach ($deliveryOrderList as $key => $value) {
            $order_ids[] = $value['order_id'];
        }

        $orderModel = app::get('ome')->model('orders');
        $orderList = $orderModel->getList('*',array('order_id'=>$order_ids));

        $deliveryinfo['orders']         = $orderList;
        $deliveryinfo['delivery_order'] = $deliveryOrderList;
        return $deliveryinfo;
    }

    public function delivery_confirm_send_fromsub($delivery_id) {
        $delivery = app::get('ome')->model('delivery')->dump(array('delivery_id'=>$delivery_id),'parent_id');
        $did = $delivery['parent_id'] ? : $delivery_id;
        return $this->delivery_confirm_send($did);
    }
    /**
     * 通过发货单信息回打发货状态(发货调用)
     *
     * @return bool
     * @author 
     **/
    public function delivery_confirm_send($delivery_id)
    {
        $deliveryModel = app::get('ome')->model('delivery');
        $delivery = $this->_getDelivery($delivery_id);

        if (empty($delivery['logi_no'])) {
            // 提货物流允许 logi_no 为空，运单号由平台接口返回
            $corpInfo = app::get('ome')->model('dly_corp')->dump($delivery['logi_id'], 'corp_model');
            if ($corpInfo['corp_model'] != 'pickup') {
                return ['rsp'=>'fail', 'msg'=>'运单号为空'];
            }
        }
        if ($delivery['is_bind'] == 'true') { //  合单
            $children_delivery_id = array();

            $childrednDeliveryList = $deliveryModel->getList('*',array('parent_id'=>$delivery['delivery_id']));
            foreach ($childrednDeliveryList as $c_key => $c_delivery) {
                $childrednDeliveryList[$c_key]['status']               = $delivery['status'];
                $childrednDeliveryList[$c_key]['logi_id']              = $delivery['logi_id'];
                $childrednDeliveryList[$c_key]['logi_name']            = $delivery['logi_name'];
                $childrednDeliveryList[$c_key]['logi_no']              = $delivery['logi_no'];
                $childrednDeliveryList[$c_key]['delivery_cost_actual'] = $delivery['delivery_cost_actual'];

                $children_delivery_id[] = $c_delivery['delivery_id'];
            }

            // 发货单对应的订单
            $delivery_orders = array();

            $deliveryOrderModel = app::get('ome')->model('delivery_order');
            $deliveryOrderList = $deliveryOrderModel->getList('*',array('delivery_id'=>$children_delivery_id));
            foreach ($deliveryOrderList as $key => $value) {
                $delivery_orders[$value['delivery_id']] = &$orderList[$value['order_id']];
            }

            $orderModel = app::get('ome')->model('orders');
            $rows = $orderModel->getList('*',array('order_id'=>array_keys($orderList)));
            foreach ($rows as $key => $value) {
                $orderList[$value['order_id']] = $value;
            }

            foreach ($childrednDeliveryList as $c_delivery) {
                if(in_array($delivery_orders[$c_delivery['delivery_id']]['order_bn'], $delivery['dly_succ_order'])) {
                    continue;
                }
                $this->_process_retry($c_delivery,$childrednDeliveryList,$delivery_orders);
            }

        } else {
            // 发货单对应的订单
            $order = current($delivery['orders']);
            $order_id = $order['order_id'];
            if(in_array($order['order_bn'], $delivery['dly_succ_order'])) {
                return ['rsp'=>'fail', 'msg'=>'已经回写过了'];
            }
            // 发货单对应的订单
            $delivery_orders = [0];

            $deliveryOrderModel = app::get('ome')->model('delivery_order');
            $deliveryOrderList = $deliveryOrderModel->getList('*',array('order_id'=>$order_id));
            foreach ($deliveryOrderList as $key => $value) {
                $delivery_orders[$value['delivery_id']] = &$orderList[$value['order_id']];
            }

            $orderModel = app::get('ome')->model('orders');
            $rows = $orderModel->getList('*',array('order_id'=>array_keys($orderList)));
            foreach ($rows as $key => $value) {
                $orderList[$value['order_id']] = $value;
            }

            $deliveryList = $deliveryModel->getList('*',array('delivery_id'=>array_keys($delivery_orders)));

            $this->_process_retry($delivery,$deliveryList,$delivery_orders);
        }

        return true;
    }

    /**
     * 通过发货单ID回打发货状态(回打失败重试)
     *
     * @param Array $orderids
     * @return void
     * @author 
     **/
    public function delivery_confirm_retry($orderids)
    {
        if (!$orderids) return false;

        $orderModel = app::get('ome')->model('orders');
        $returnObj = app::get('ome')->model('return_product');
        
        //orders
        $rows = $orderModel->getList('*',array('order_id'=>$orderids));
        $orderList = array();
        foreach ($rows as $row) {
            $orderList[$row['order_id']] = $row;
        }

        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $deliveryOrderList = $deliveryOrderModel->getList('*',array('order_id'=>$orderids));

        if (!$deliveryOrderList) return false;
        
        //获取售后列表
        $returnList = $returnObj->getList('return_id,order_id', array('order_id'=>$orderids));
        if($returnList){
            $returnList = array_column($returnList, null, 'order_id');
        }
        
        //delivery_orders
        $delivery_orders = array();
        foreach ($deliveryOrderList as $key => $value)
        {
            $order_id = $value['order_id'];
            
            //过滤已经有售后单的订单
            if($returnList[$order_id]){
                //continue;
            }
            
            $delivery_orders[$value['delivery_id']] = $orderList[$value['order_id']];
        }
        
        //check
        if(empty($delivery_orders)){
            return false;
        }
        
        $deliveryModel = app::get('ome')->model('delivery');
        $deliveryList = $deliveryModel->getList('*',array('delivery_id'=>array_keys($delivery_orders),'process'=>'true','parent_id'=>'0'));

        foreach ($deliveryList as $delivery)
        {
            $delivery_id = $delivery['delivery_id'];
            
            //订单信息
            $order_bn = $delivery_orders[$delivery_id]['order_bn'];
            if($order_bn && $delivery['logi_no']){
                //前端回写日志
                $sql = "SELECT log_id,status FROM sdb_ome_shipment_log WHERE orderBn='". $order_bn ."' AND deliveryCode='". $delivery['logi_no'] ."'";
                $shipmentInfo = $deliveryModel->db->selectrow($sql);
                if($shipmentInfo['status'] == 'succ'){
                    //[拆单发货]发货单是回写成功状态,则跳过
                    continue;
                }elseif($shipmentInfo){
                    //删除前端回写日志(不能删除失败的记录,否则重试未请求,失败记录也被删除掉了)
                    //$deliveryModel->db->exec("DELETE FROM sdb_ome_shipment_log WHERE log_id='". $shipmentInfo['log_id'] ."'");
                }
            }
            
            kernel::single('ome_event_trigger_logistics_electron')->delivery($delivery['delivery_id']);

            if ($delivery['is_bind'] == 'true') {
                $childrednDeliveryList = $deliveryModel->getList('*',array('parent_id'=>$delivery['delivery_id']));

                foreach ($childrednDeliveryList as $c_key => $c_delivery) {
                    $childrednDeliveryList[$c_key]['status']               = $delivery['status'];
                    $childrednDeliveryList[$c_key]['logi_id']              = $delivery['logi_id'];
                    $childrednDeliveryList[$c_key]['logi_name']            = $delivery['logi_name'];
                    $childrednDeliveryList[$c_key]['logi_no']              = $delivery['logi_no'];
                    $childrednDeliveryList[$c_key]['delivery_cost_actual'] = $delivery['delivery_cost_actual'];
                }

                foreach ($childrednDeliveryList as $c_delivery) {
                    $this->_process_retry($c_delivery,$childrednDeliveryList,$delivery_orders,true);
                }

            } else {
                $this->_process_retry($delivery,$deliveryList,$delivery_orders,true);
            }
        }

        return true;
    }

    private function _process_retry($curDelivery,$deliveryList,$delivery_orders,$retry=false)
    {
        $sdf = kernel::single('ome_event_trigger_shop_data_delivery_router')
                    ->set_shop_id($curDelivery['shop_id'])
                    ->init($deliveryList,$delivery_orders)
                    ->get_sdf($curDelivery['delivery_id']);

        if (!$sdf) return;

        if ($retry) kernel::single('erpapi_router_request')->set('shop',$curDelivery['shop_id'])->delivery_add($sdf);
        kernel::single('erpapi_router_request')->set('shop',$curDelivery['shop_id'])->delivery_logistics_update($sdf);
        kernel::single('erpapi_router_request')->set('shop',$curDelivery['shop_id'])->delivery_confirm($sdf);
        
        //是否安装了应用
        if(app::get('dchain')->is_installed()) {
            //[翱象系统]同步仓库作业信息
            $aoxiangLib = kernel::single('dchain_aoxiang');
            $isAoxiang = $aoxiangLib->isSignedShop($curDelivery['shop_id'], $curDelivery['shop_type']);
            
            //get config
            $aoxiangConfig = $aoxiangLib->getAoxiangSyncConfig($curDelivery['shop_id']);
            
            //只要店铺签约就推送发货单给翱象
            if($isAoxiang && $aoxiangConfig['sync_delivery'] != 'false'){
                //task队列任务
                if (defined('SAAS_COMBINE_MQ') && SAAS_COMBINE_MQ == 'true') {
                    //sdfdata
                    $sdfdata = array(
                        'uniqid' => sprintf('aoxiang_sync_delivery_%s', $curDelivery['delivery_id']),
                        'shop_id' => $curDelivery['shop_id'],
                        'delivery_id' => $curDelivery['delivery_id'],
                        'delivery_bn' => $curDelivery['delivery_bn'],
                        'process_status' => 'confirm', //accept仓库接单,confirm确认出库
                        'task_type' => 'aoxiangdelivery',
                    );
                    
                    //MQ4服务器执行
                    taskmgr_func::multiQueue($GLOBALS['_MQ_API_CONFIG'],'TG_API_EXCHANGE','TG_API_QUEUE','tg.sys.api.*', $sdfdata);
                }else{
                    //queue队列任务
                    $queueMdl = app::get('base')->model('queue');
                
                    //自动确认翱象发货单队列任务
                    $sdfData = array(
                        'shop_id' => $curDelivery['shop_id'],
                        'shop_type' => $curDelivery['shop_type'],
                        'delivery_id' => $curDelivery['delivery_id'],
                        'delivery_bn' => $curDelivery['delivery_bn'],
                        'process_status' => 'confirm', //accept仓库接单,confirm确认出库
                    );
                    
                    $queueData = array(
                        'queue_title' => '自动同步翱象发货单状态队列任务',
                        'start_time' => time(),
                        'params' => array(
                            'sdfdata' => $sdfData,
                            'app' => 'dchain',
                            'mdl' => 'aoxiang_delivery',
                        ),
                        'worker'=> 'dchain_delivery.syncAoxiangDelivery',
                    );
                    $queueMdl->save($queueData);
                }
            }
        }
        
    }


    /**
     *  发货单状态变更
     *
     * @return void
     * @author 
     **/
    public function delivery_process_update($deliveryids)
    {   
        $deliveryModel = app::get('ome')->model('delivery');
        $rows = $deliveryModel->getList('shop_id,delivery_bn,delivery_id,status,is_bind,logi_id',array('delivery_id'=>$deliveryids));

        if (!$rows) return;
        $deliveryList = array();
        
        foreach ($rows as $row) {
            $deliveryList[$row['delivery_id']] = $row;
           
        }


        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $rows = $deliveryOrderModel->getList('*',array('delivery_id'=>$deliveryids));
        if (!$rows) return;

        $delivery_order = array();
        foreach ($rows as $row) {
            $delivery_order[$row['order_id']] = $row['delivery_id'];
        }

        $orderModel = app::get('ome')->model('orders');
        $rows = $orderModel->getList('order_id,order_bn,shop_id',array('order_id'=>array_keys($delivery_order)));
        if (!$rows) return;

        $orderList = array();
        foreach ($rows as $row) {
            $orderList[$row['order_id']] = $row;
        }
        
        foreach ($delivery_order as $order_id => $delivery_id) {
            $delivery = $deliveryList[$delivery_id]; $order = $orderList[$order_id];

            if ($delivery['is_bind'] == 'true') {
                $c_delivery_order = $deliveryOrderModel->getList('*',array('order_id'=>$order_id));
                $c_deliveryIds = array();
                foreach ($c_delivery_order as $k=>$v) {
                    $c_deliveryIds[] = $v['delivery_id'];
                }

                $childDelivery = $deliveryModel->getList('delivery_id,delivery_bn,shop_id',array('delivery_id'=>$c_deliveryIds, 'parent_id'=>$delivery_id),0,1);
  
                $sdf = array(
                    'delivery_bn' => $childDelivery[0]['delivery_bn'],
                    'status'      => $delivery['status'],
                    'orderinfo'   => array('order_bn'=>$order['order_bn'])
                );

                kernel::single('erpapi_router_request')->set('shop',$childDelivery[0]['shop_id'])->delivery_deliveryprocess_update($sdf);

            } else {
                $sdf = array(
                    'delivery_bn' => $delivery['delivery_bn'],
                    'status'      => $delivery['status'],
                    'orderinfo'   => array('order_bn'=>$order['order_bn'])
                );

                kernel::single('erpapi_router_request')->set('shop',$delivery['shop_id'])->delivery_deliveryprocess_update($sdf);
            }
        }
    }

    /**
     * 发货单添加
     *
     * @return void
     * @author 
     **/
    public function delivery_add($delivery_id)
    {
        $deliveryModel = app::get('ome')->model('delivery');
        $deliveryList = $deliveryModel->getList('*',array('delivery_id'=>$delivery_id));

        if (!$deliveryList) return;
        $delivery = $deliveryList[0];

        // 发货单对应的订单
        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $deliveryOrderList = $deliveryOrderModel->getList('delivery_id,order_id',array('delivery_id'=>$delivery_id));

        $order_ids = array(); $delivery_orders = array();
        foreach ($deliveryOrderList as $key => $value) {
            $order_ids[] = $value['order_id'];

            $delivery_orders[$value['delivery_id']] = &$orderList[$value['order_id']];
        }

        $orderModel = app::get('ome')->model('orders');
        $rows = $orderModel->getList('*',array('order_id'=>$order_ids));
        foreach ($rows as $key => $value) {
            $orderList[$value['order_id']] = $value;
        }
       
        $sdf = kernel::single('ome_event_trigger_shop_data_delivery_router')
                    ->set_shop_id($delivery['shop_id'])
                    ->init($deliveryList,$delivery_orders)
                    ->get_add_delivery_sdf($delivery['delivery_id']);

        //通知平台创建发货单
        if($sdf){
            kernel::single('erpapi_router_request')->set('shop',$delivery['shop_id'])->delivery_add($sdf);
        }
        
        //是否安装了应用
        if(app::get('dchain')->is_installed()) {
            //[翱象系统]同步发货单作业信息
            $aoxiangLib = kernel::single('dchain_aoxiang');
            $isAoxiang = $aoxiangLib->isSignedShop($delivery['shop_id'], $delivery['shop_type']);
            
            //get config
            $aoxiangConfig = $aoxiangLib->getAoxiangSyncConfig($delivery['shop_id']);
            
            //只要店铺签约就推送发货单给翱象
            if($isAoxiang && $aoxiangConfig['sync_delivery'] != 'false'){
                //task任务队列
                if (defined('SAAS_COMBINE_MQ') && SAAS_COMBINE_MQ == 'true') {
                    //sdfdata
                    $sdfdata = array(
                        'uniqid' => sprintf('aoxiang_sync_delivery_%s', $delivery['delivery_id']),
                        'shop_id' => $delivery['shop_id'],
                        'delivery_id' => $delivery['delivery_id'],
                        'delivery_bn' => $delivery['delivery_bn'],
                        'process_status' => 'accept', //accept仓库接单,confirm确认出库
                        'task_type' => 'aoxiangdelivery',
                    );
                    
                    //MQ4服务器执行
                    taskmgr_func::multiQueue($GLOBALS['_MQ_API_CONFIG'],'TG_API_EXCHANGE','TG_API_QUEUE','tg.sys.api.*', $sdfdata);
                }else{
                    //queue队列
                    $queueMdl = app::get('base')->model('queue');
                    
                    //自动同步翱象发货单队列任务
                    $sdfData = array(
                        'shop_id' => $delivery['shop_id'],
                        'shop_type' => $delivery['shop_type'],
                        'delivery_id' => $delivery['delivery_id'],
                        'delivery_bn' => $delivery['delivery_bn'],
                        'process_status' => 'accept', //accept仓库接单,confirm确认出库
                    );
                    
                    $queueData = array(
                        'queue_title' => '自动同步翱象发货单队列任务',
                        'start_time' => time(),
                        'params' => array(
                            'sdfdata' => $sdfData,
                            'app' => 'dchain',
                            'mdl' => 'aoxiang_delivery',
                        ),
                        'worker'=> 'dchain_delivery.addAoxiangDelivery',
                    );
                    $queueMdl->save($queueData);
                }
            }
        }
        
        return true;
    }

    /**
     *  发货单状态变更
     *
     * @return void
     * @author 
     **/
    public function delivery_logistics_update($deliveryids)
    {   
        $deliveryModel = app::get('ome')->model('delivery');
        $rows = $deliveryModel->getList('shop_id,delivery_bn,delivery_id,status,is_bind,logi_id,logi_name,logi_no',array('delivery_id'=>$deliveryids));

        if (!$rows) return;
        $deliveryList = array();
        $corpIds = array();
        foreach ($rows as $row) {
            $deliveryList[$row['delivery_id']] = $row;
            $corpIds[] = $row['logi_id'];
        }

        $corpModel = app::get('ome')->model('dly_corp');
        $rows = $corpModel->getList('type,corp_id',array('corp_id'=>$corpIds));
        $corps = array();
        foreach ($rows as $row) {
            $corps[$row['corp_id']] = $row['type'];
        }
        unset($rows);
        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $rows = $deliveryOrderModel->getList('*',array('delivery_id'=>$deliveryids));
        if (!$rows) return;

        $delivery_order = array();
        foreach ($rows as $row) {
            $delivery_order[$row['order_id']] = $row['delivery_id'];
        }

        $orderModel = app::get('ome')->model('orders');
        $rows = $orderModel->getList('order_id,order_bn,shop_id',array('order_id'=>array_keys($delivery_order)));
        if (!$rows) return;

        $orderList = array();
        foreach ($rows as $row) {
            $orderList[$row['order_id']] = $row;
        }

        foreach ($delivery_order as $order_id => $delivery_id) {
            $delivery = $deliveryList[$delivery_id]; $order = $orderList[$order_id];

            if ($delivery['is_bind'] == 'true') {
                $c_delivery_order = $deliveryOrderModel->getList('*',array('order_id'=>$order_id));
                $c_deliveryIds = array();
                foreach ($c_delivery_order as $k=>$v) {
                    $c_deliveryIds[] = $v['delivery_id'];
                }

                $childDelivery = $deliveryModel->getList('delivery_id,delivery_bn,shop_id',array('delivery_id'=>$c_deliveryIds, 'parent_id'=>$delivery_id),0,1);
                    foreach ($childDelivery as $child){
                        $sdf = array(
                            'delivery_bn' => $child['delivery_bn'],
                            'orderinfo'   => array('order_bn'=>$order['order_bn']),
                            'logi_type'   => $corps[$delivery['logi_id']],
                            'logi_name'   => $delivery['logi_name'],
                            'logi_no'     => $delivery['logi_no'],
                        );

                    kernel::single('erpapi_router_request')->set('shop',$child[0]['shop_id'])->delivery_logistics_update($sdf);

                }
                
            } else {
                $sdf = array(
                    'delivery_bn' => $delivery['delivery_bn'],
                    'orderinfo'   => array('order_bn'=>$order['order_bn']),
                    'logi_type'   => $corps[$delivery['logi_id']],
                    'logi_name'   => $delivery['logi_name'],
                    'logi_no'     => $delivery['logi_no'],
                );

                kernel::single('erpapi_router_request')->set('shop',$delivery['shop_id'])->delivery_logistics_update($sdf);
            }
        }
    }

    public function printThirdBill($printDly){
        $branchBn = '';
        foreach ($printDly as $shopId => $arrDly) {
            if(!$branchBn) {
                $tmp = current($arrDly);
                $branchId = $tmp['branch_id'];
                $branchR = app::get('ome')->model('branch_relation')->db_dump(array('branch_id'=>$branchId, 'type'=>'vopczc'));
                if($branchR['relation_branch_bn']) {
                    $branchBn = $branchR['relation_branch_bn'];
                } else {
                    $branch = app::get('ome')->model('branch')->db_dump(array('branch_id'=>$branchId), 'branch_bn');
                    $branchBn = $branch['branch_bn'];
                }
            }
            $rs = kernel::single('erpapi_router_request')->set('shop', $shopId)->delivery_printThirdBill($arrDly, $branchBn);
            if($rs['rsp'] == 'succ') {
                $updateData = array(
                    'process_status' => '1',
                    'print_status'=>'7',
                );
                app::get('wms')->model('delivery')->update($updateData, array('delivery_id'=>array_keys($arrDly), 'status'=>array('0')));
                $write_log = array();
                foreach ($arrDly as $d){
                    
                    $logMsg ='打印三单' ;
                    app::get('ome')->model('operation_log')->write_log('delivery_expre@wms', $d['delivery_id'], $logMsg);
                }
                
            }
        }
    }

    public function getDeliveryInfo($branchId, $arrDly) {
        $branchR = app::get('ome')->model('branch_relation')->db_dump(array('branch_id'=>$branchId, 'type'=>'vopczc'));
        if($branchR['relation_branch_bn']) {
            $branchBn = $branchR['relation_branch_bn'];
        } else {
            $branch = app::get('ome')->model('branch')->db_dump(array('branch_id'=>$branchId), 'branch_bn');
            $branchBn = $branch['branch_bn'];
        }
        $primaryBn = uniqid('od');
        $write_log = array();
        $wms_deliveryObj = app::get('wms')->model('delivery');
        $orderDly = array();
        foreach ($arrDly as $d){
            $write_log[$d['delivery_id']] = array(
                'obj_id'    => $d['delivery_id'],
                'obj_name'  => $d['delivery_bn'],
                'operation' => 'delivery_getwaybill@ome',
                'memo'      => "获取运单号请求({$primaryBn})",
            );
            $order_ids = $wms_deliveryObj->getOrderIdByDeliveryId($d['delivery_id']);
            $order_ids = current($order_ids);
            $orderDly[$order_ids][] = $d['delivery_id'];
        }


        $arrOrder = app::get('ome')->model('orders')->getList('order_id, shop_id, order_bn', array('order_id'=>array_keys($orderDly)));
        $shopOrder = array();
        $bnId = array();
        foreach ($arrOrder as $val) {
            $shopOrder[$val['shop_id']][] = $val['order_bn'];
            $bnId[$val['order_bn']] = $val['order_id'];
        }
     
        $billModel = app::get('wms')->model('delivery_bill');
        
        $return = array();
        foreach ($shopOrder as $shopId => $arrOrderBn) {
            $sdf = array(
                'order_bn' => $arrOrderBn,
                'branch_bn' => $branchBn,
                'primary_bn' => $primaryBn
            );
            $rs = kernel::single('erpapi_router_request')->set('shop', $shopId)->delivery_getDeliveryInfo($sdf);
            
            if($rs['data']) {
                $orderWaybill = $rs['data'];
               
                $allWaybill = array();
                foreach ($orderWaybill as $val) {
                    foreach ($val['delivery_list'] as $v) {
                        $allWaybill[] = $v['delivery_no'];
                    }
                }
                $hasDly = $billModel->getList('logi_no', array('logi_no'=>$allWaybill));
                $hasWaybill = array();
                foreach ($hasDly as $v) {
                    $hasWaybill[] = $v['logi_no'];
                }
                foreach ($orderWaybill as $val) {
                    $tmpDly = $orderDly[$bnId[$val['order_bn']]];
                    

                    foreach ($val['delivery_list'] as $v) {
                        if(in_array($v['delivery_no'], $hasWaybill)){
                            continue;
                        }
                     
                        foreach ($tmpDly as $k => $dlyId) {
                            $ret = $billModel->update(array('logi_no' => $v['delivery_no']), array('delivery_id' => $dlyId,'type'=>'1'));
                           
                            unset($tmpDly[$k]);
                            if($ret) {
                                $return[$dlyId] = true;
                                $delivery = $arrDly[$dlyId];
                                $logiNo = $v['delivery_no'];
                                $logMsg ='获取物流单号：' . $logiNo;
                                app::get('ome')->model('operation_log')->write_log('delivery_expre@wms', $dlyId, $logMsg);
                                
                            }
                        }
                    }
                }
            }
        }
        
        return $return;
    }
}
