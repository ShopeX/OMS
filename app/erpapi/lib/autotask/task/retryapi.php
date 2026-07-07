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

@include_once(dirname(__FILE__).'/../../apiname.php');
class erpapi_autotask_task_retryapi{

    /**
     * 处理
     * @param mixed $params 参数
     * @param mixed $error_msg error_msg
     * @return mixed 返回值
     */
    public function process($params, &$error_msg) 
    {
        $apiModel = app::get('erpapi')->model('api_fail');
        $apilog = $apiModel->dump(array('id'=>$params['id']),'status,obj_type,params,fail_times');

        if ($apilog['status'] != 'fail') {
            $error_msg = $params['obj_bn'].'已经重试，不允许发起';
            return false;
        }
        $apiModel->update(array('status'=>'running'),array('id'=>$params['id']));

        //[兼容]发货单之前没有保存method方法名(后面需要删除掉此兼容)
        if(empty($params['method'])){
            $params['method'] = $params['obj_type'];
        }
        
        //dispose
        try {
            switch ($params['method']) {
                case WMS_INORDER_CREATE:
                    // 入库
                    if ($apilog['obj_type'] == 'purchase') {
                        $iso = app::get('purchase')->model('po')->dump(array('po_bn'=>$params['obj_bn'],'check_status'=>'2','eo_status'=>'1'));

                        if (!$iso) {
                            $error_msg = $params['obj_bn'].'状态(check_status:'.$iso['check_status'].'、eo_status:'.$iso['eo_status'].')不允许发起';
                            return false;
                        }

                        kernel::single('console_event_trigger_purchase')->create(array('po_id'=>$iso['po_id']), false);
                    } else {
                        $iso  = app::get('taoguaniostockorder')->model('iso')->dump(array('iso_bn'=>$params['obj_bn'],'check_status'=>'2','iso_status'=>'1'),'iso_id,check_status,iso_status');


                        if (!$iso) {
                            $error_msg = $params['obj_bn'].'状态(check_status:'.$iso['check_status'].'、iso_status:'.$iso['iso_status'].')不允许发起';
                            return false;
                        }

                        kernel::single('console_event_trigger_otherstockin')->create(array('iso_id'=>$iso['iso_id']),false);
                    }

                    break;
                case WMS_OUTORDER_CREATE:
                    // 出库
                    if ($apilog['obj_type'] == 'purchase_return') {
                        $iso = app::get('purchase')->model('returned_purchase')->dump(array('rp_bn'=>$params['obj_bn'],'check_status'=>'2'));

                        if (!$iso) {
                            $error_msg = $params['obj_bn'].'状态(check_status:'.$iso['check_status'].'不允许发起';
                            return false;
                        }
                        kernel::single('console_event_trigger_purchasereturn')->create(array('rp_id'=>$iso['rp_id']), false);
                    } else {
                        $iso  = app::get('taoguaniostockorder')->model('iso')->dump(array('iso_bn'=>$params['obj_bn'],'check_status'=>'2','iso_status'=>'1'),'iso_id,check_status,iso_status');
                        if (!$iso) {
                            $error_msg = $params['obj_bn'].'状态(check_status:'.$iso['check_status'].'、iso_status:'.$iso['iso_status'].')不允许发起';
                            return false;
                        }

                        kernel::single('console_event_trigger_otherstockout')->create(array('iso_id'=>$iso['iso_id']),false);        
                    }


                    break;
                case WMS_SALEORDER_CREATE:
                case 'delivery_create':
                    // 销售出库
                    $delivery = app::get('ome')->model('delivery')->dump(array('delivery_bn'=>$params['obj_bn']),'delivery_id,status,pause,process');

                    if (in_array($delivery['status'],array('succ','cancel','back'))){
                        //删除此记录
                        $id = $params['id'];
                        $apiModel->db->exec("DELETE FROM sdb_erpapi_api_fail WHERE id=".$id);
                        return false;
                    }

                    if(in_array($delivery['status'],array('progress','ready')) && $delivery['pause'] == 'false' && $delivery['process'] == 'false'){
                        //发货单通知单推送仓库
                        ome_delivery_notice::create($delivery['delivery_id']);
                    }
                    
                    break;
                case WMS_RETURNORDER_CREATE:
                    // 退货入库
                    $reship = app::get('ome')->model('reship')->dump(array('reship_bn'=>$params['obj_bn'],'is_check'=>1),'reship_id,is_check');
                    if (!$reship) {
                        $error_msg = $params['obj_bn'].'状态(is_check:'.$reship['is_check'].')不允许发起';
                        return false;
                    }


                    $reship_data = kernel::single('ome_receipt_reship')->reship_create(array('reship_id'=>$reship['reship_id']));
                    $wms_id = kernel::single('ome_branch')->getWmsIdById($reship_data['branch_id']);
                    kernel::single('console_event_trigger_reship')->create($wms_id, $reship_data, false);
                    break;
                case 'wms.delivery.status_update':
                    $data = $apilog['params'] ? json_decode($apilog['params'],true) : array();

                    if($data){
                        $data['status'] = 'delivery';
                        $result = kernel::single('ome_event_receive_delivery')->update($data);

                        if($result['rsp'] == 'succ'){
                            $apiModel->db->exec("DELETE FROM sdb_erpapi_api_fail WHERE id=".$params['id']);
                        }else{
                            $apiModel->update(array('status'=>'fail','fail_times'=>$apilog['fail_times']+1),array('id'=>$params['id']));
                        }
                    }

                    break;
                case 'deliveryBack':
                case SHOP_LOGISTICS_OFFLINE_SEND:
                    //回传平台发货状态
                    $deliveryObj = app::get('ome')->model('delivery');
                    
                    //发货单信息
                    $delivery_bn = $params['obj_bn'];
                    $deliveryInfo = $deliveryObj->dump(array('delivery_bn'=>$delivery_bn, 'process'=>'true', 'parent_id'=>'0'), 'delivery_id,logi_no,status');
                    if(empty($deliveryInfo)){
                        //已回写成功,删除失败记录
                        $apiModel->db->exec("DELETE FROM sdb_erpapi_api_fail WHERE id=".$params['id']);
                        
                        $error_msg = '没有获取到发货单信息';
                        return false;
                    }
                    
                    //check
                    if(!in_array($deliveryInfo['status'], array('succ','progress','ready','failed'))){
                        //已回写成功,删除失败记录
                        $apiModel->db->exec("DELETE FROM sdb_erpapi_api_fail WHERE id=".$params['id']);
                        
                        $error_msg = '发货单状态不允许推送发货状态';
                        return false;
                    }
                    
                    //关联订单信息
                    $sql = "SELECT b.order_id,b.order_bn,b.ship_status FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_orders AS b ON a.order_id=b.order_id WHERE a.delivery_id=". $deliveryInfo['delivery_id'];
                    $orderInfo = $deliveryObj->db->selectrow($sql);
                    
                    //check
                    if(!in_array($orderInfo['ship_status'], array('1','2'))){
                        //已回写成功,删除失败记录
                        $apiModel->db->exec("DELETE FROM sdb_erpapi_api_fail WHERE id=".$params['id']);
                        
                        $error_msg = '订单不是发货状态';
                        return false;
                    }
                    
                    //前端回写日志
                    $sql = "SELECT log_id,status FROM sdb_ome_shipment_log WHERE orderBn='". $orderInfo['order_bn'] ."' AND deliveryCode='". $deliveryInfo['logi_no'] ."'";
                    $shipmentInfo = $deliveryObj->db->selectrow($sql);
                    if($shipmentInfo['status'] == 'succ'){
                        //已回写成功,删除失败记录
                        $apiModel->db->exec("DELETE FROM sdb_erpapi_api_fail WHERE id=".$params['id']);
                        
                        return true;
                    }elseif($shipmentInfo){
                        //删除前端回写日志(不能删除失败的记录,否则重试未请求,失败记录也被删除掉了)
                        //$apiModel->db->exec("DELETE FROM sdb_ome_shipment_log WHERE log_id='". $shipmentInfo['log_id'] ."'");
                    }
                    
                    //重新推送发货状态给平台
                    $delieryLib = kernel::single('ome_event_trigger_shop_delivery');
                    $result = $delieryLib->delivery_confirm_send($deliveryInfo['delivery_id']);
                    
                    break;
                case SHOP_AFTERSALE_EXCHANGE_AGREE:
                case SHOP_AGREE_RETURN_GOOD:
                    //同意退货、换货请求
                    $returnObj = app::get('ome')->model('return_product');
                    
                    $obj_bn = $params['obj_bn'];
                    
                    //售后申请单信息
                    $returnInfo = $returnObj->dump(array('return_bn'=>$obj_bn), '*');
                    if(empty($returnInfo)){
                        //已回写成功,删除失败记录
                        $apiModel->db->exec("DELETE FROM sdb_erpapi_api_fail WHERE id=".$params['id']);
                        
                        $error_msg = '没有获取到售后申请单信息';
                        return false;
                    }
                    
                    //check
                    if(!in_array($returnInfo['status'], array('3','4','6','7','8','9'))){
                        //单据已完成或已拒绝,删除失败记录
                        $apiModel->db->exec("DELETE FROM sdb_erpapi_api_fail WHERE id=".$params['id']);
                        
                        $error_msg = '售后申请单状态不允许重试推送';
                        return false;
                    }
                    
                    //重新推送发货状态给平台(无返回结果)
                    $result = kernel::single('ome_service_aftersale')->update_status($returnInfo['return_id']);
                    
                    break;
                case SHOP_REFUSE_RETURN_GOOD:
                case SHOP_AFTERSALE_EXCHANGE_REFUSE:
                    //拒绝退货、换货状态
                    $returnObj = app::get('ome')->model('return_product');
                    
                    $obj_bn = $params['obj_bn'];
                    
                    //售后申请单信息
                    $returnInfo = $returnObj->dump(array('return_bn'=>$obj_bn), '*');
                    if(empty($returnInfo)){
                        //已回写成功,删除失败记录
                        $apiModel->db->exec("DELETE FROM sdb_erpapi_api_fail WHERE id=".$params['id']);
                        
                        $error_msg = '没有获取到售后申请单信息';
                        return false;
                    }
                    
                    //check
                    if(!in_array($returnInfo['status'], array('5'))){
                        //单据不是拒绝状态,删除失败记录
                        $apiModel->db->exec("DELETE FROM sdb_erpapi_api_fail WHERE id=".$params['id']);
                        
                        $error_msg = '售后申请单状态不允许重试推送';
                        return false;
                    }
                    
                    //重新推送发货状态给平台(无返回结果)
                    $result = kernel::single('ome_service_aftersale')->update_status($returnInfo['return_id'], '5');
                    
                    break;
                default:
                    # code...
                    break;
            }
        } catch (Exception $e) {
            
        }
        
        return true;
    }
}