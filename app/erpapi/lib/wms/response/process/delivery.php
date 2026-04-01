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
 * 发货
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_response_process_delivery
{
    /**
     * 发货单
     * @param Array $params=array(
     *                  'status'=>@状态@ delivery
     *                  'delivery_bn'=>@发货单号@
     *                  'out_delivery_bn'=>@外部发货单号@
     *                  'logi_no'=>@运单号@
     *                  'delivery_time'=>@发货时间@
     *                  'weight'=>@重量@
     *                  'delivery_cost_actual'=>@物流费@
     *                  'logi_id'=>@物流公司编码@
     *                  ===================================
     *                  'status'=>print,
     *                  'delivery_bn'=>@发货单号@
     *                  'stock_status'=>@备货单打印状态@
     *                  'deliv_status'=>@发货单打印状态@
     *                  'expre_status'=>@快递单打印状态@
     *                  ===================================
     *                  'status'=>check
     *                  'delivery_bn'=>@发货单号@
     *                  ===================================
     *                  'status'=>cancel
     *                  'delivery_bn'=>@发货单号@
     *                  'memo'=>@备注@
     *                  ===================================
     *                  'status'=>update
     *                  'delivery_bn'=>@发货单号@
     *                  'action'=>updateDetail|addLogiNo
     *
     *
     *              )
     * @return void
     * @author
     **/
    public function status_update($params)
    {
        // 如果包裹追回，退货入库自动完成 BEGIN ----------------8<
        if ($params['status'] == 'return_back') {
            // 触发AG
            $this->delivery_refundAg($params['delivery_bn'], $params['status']);

            return kernel::single('erpapi_wms_response_process_reship')->add_complete($params);
        }elseif($params['status'] == 'payed'){
            //京东子订单已支付
            return array('rsp'=>'succ', 'msg'=>'京东子订单已支付');
        }
        // 如果包裹追回，退货入库自动完成 END ----------------8<

        if ($params['operate_time']) {
            $params['delivery_time'] = $params['operate_time'];
        }

        if (!empty($params['bill_logi_no']) && is_array($params['bill_logi_no'])) {
            $dliBill = app::get('ome')->model('delivery_bill');
            foreach ($params['bill_logi_no'] as $val) {
                $bill                = array();
                $bill['status']      = $params['status'] == 'delivery' ? 1 : ($params['status'] == 'cancel' ? 2 : 0);
                $bill['logi_no']     = $val;
                
                $delivery_data = app::get('ome')->model('delivery')->dump(array('delivery_bn' => $params['delivery_bn']), 'status,delivery_id,delivery_bn');
                $bill['delivery_id'] = $delivery_data['delivery_id'];
                $bill['delivery_bn'] = $delivery_data['delivery_bn'];
                
                $hadBill             = $dliBill->dump(array('delivery_id' => $bill['delivery_id'], 'logi_no' => $bill['logi_no']), 'log_id');
                if (empty($hadBill)) {
                    $bill['create_time'] = strtotime($params['operate_time']);
                } else {
                    $bill['log_id'] = $hadBill['log_id'];
                }
                $bill['delivery_time'] = strtotime($params['operate_time']);
                $dliBill->save($bill);
            }
        }

        // WMS 回传无物流公司、无运单号时：非大件物流须报错（大件在 OMS 内会用发货单号兜底运单号）
        if ($params['status'] == 'delivery') {
            $logiIdEmpty = empty($params['logi_id']);
            $logiNoEmpty = trim((string)($params['logi_no'] ?? '')) === '';
            $billHasLogi = !empty($params['bill_logi_no']) && is_array($params['bill_logi_no'])
                && array_filter(array_map('trim', $params['bill_logi_no']));
            if ($logiIdEmpty && $logiNoEmpty && !$billHasLogi) {
                $deliveryRow = app::get('ome')->model('delivery')->dump(
                    array('delivery_bn' => $params['delivery_bn']),
                    'logi_id'
                );
                if (empty($deliveryRow['logi_id'])) {
                    return array('rsp' => 'fail', 'msg' => '缺少物流公司');
                }
                $corpRow = app::get('ome')->model('dly_corp')->dump(
                    array('corp_id' => $deliveryRow['logi_id']),
                    'corp_model'
                );
                if (empty($corpRow['corp_model']) || $corpRow['corp_model'] != 'heavy') {
                    return array('rsp' => 'fail', 'msg' => '缺少物流公司运单号');
                }
            }
        }

        // 如果发货提前触发AG
        if ($params['status'] == 'delivery' && $params['node_type'] == 'yjdf') {
            $this->delivery_refundAg($params['delivery_bn'], $params['status']);
        }

        $result = kernel::single('ome_event_receive_delivery')->update($params);

        // 报警
        if($result['rsp'] == 'fail' && $params['status'] == 'delivery') {
            kernel::single('monitor_event_notify')->addNotify('wms_delivery_consign', [
                'delivery_bn' => $params['delivery_bn'],
                'errmsg'      => $result['msg'],
            ]);
        }

        /****
        //发货失败
        if ($result['rsp'] == 'fail') {
            //[京东一件代发]发货失败,删除WMS仓库赠送的赠品
            if ($params['status'] == 'delivery' && $params['gift_list']) {
                $deliveryLib = kernel::single('console_delivery');
                $isDelete    = $deliveryLib->deleteWmsGifts($params, $error_msg);
                if (!$isDelete) {
                    $operLogObj = app::get('ome')->model('operation_log');
                    $operLogObj->write_log('delivery_modify@ome', $params['delivery_id'], '发货失败,删除赠品失败:' . $error_msg);
                }
            }
        }
        ****/
        

        // 一件代发取消成功触发AG
        if ($result['rsp'] == 'succ' && in_array($params['status'],['cancel','cancel_fail']) && $params['node_type'] == 'yjdf') {
            $agrs = $this->delivery_refundAg($params['delivery_bn'], $params['status']);

            // 如果没有退款，重新路由
            if (!$agrs && $params['status'] == 'cancel' && $params['delivery_id']) {
                $orderModel = app::get('ome')->model('orders');
                $deliveryObj = app::get('ome')->model('delivery');
                
                //订单信息
                $sql = "SELECT a.order_id,a.order_bn,a.pay_status,a.ship_name,a.ship_area,a.ship_addr,a.ship_mobile 
                        FROM sdb_ome_orders AS a LEFT JOIN sdb_ome_delivery_order AS b ON a.order_id=b.order_id WHERE b.delivery_id=". $params['delivery_id'];
                $orderInfo = $orderModel->db->selectrow($sql);
                if ($orderInfo['order_id']) {
                    //发货单信息
                    $sql = "SELECT delivery_id,ship_name,ship_area,ship_addr,ship_mobile FROM sdb_ome_delivery WHERE delivery_id=". $params['delivery_id'];
                    $dlyInfo = $orderModel->db->selectrow($sql);
                    
                    //订单收货人信息有变化时,才会重新路由自动审单
                    $order_str = $orderInfo['ship_name'].'-'.$orderInfo['ship_mobile'].'-'.$orderInfo['ship_area'].'-'.$orderInfo['ship_addr'];
                    $order_str = str_replace(array(" ","　","\n","\r","\t"), '', $order_str);
                    
                    $delivery_str = $dlyInfo['ship_name'].'-'.$dlyInfo['ship_mobile'].'-'.$dlyInfo['ship_area'].'-'.$dlyInfo['ship_addr'];
                    $delivery_str = str_replace(array(" ","　","\n","\r","\t"), '', $delivery_str);
                    
                    if($order_str != $delivery_str){
                        $orderModel->renewOrder($orderInfo['order_id']);
                        kernel::single('ome_order')->auto_order_combine($orderInfo['order_id']);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 一件代发取消成功触发AG
     *
     * @return void
     * @author
     **/
    public function delivery_refundAg($delivery_bn, $status='')
    {
        $orderItemMdl   = app::get('ome')->model('order_items');
        $orderObjectMdl = app::get('ome')->model('order_objects');
        $itemDetailMdl  = app::get('ome')->model('delivery_items_detail');
        $packageMdl     = app::get('ome')->model('delivery_package');
        $refundApplyMdl = app::get('ome')->model('refund_apply');
        $deliveryMdl    = app::get('ome')->model('delivery');

        $delivery = $deliveryMdl->db_dump(['delivery_bn' => $delivery_bn], 'delivery_id');
        if (!$delivery) {
            return false;
        }
        $delivery_id = $delivery['delivery_id'];

        $items_detail = $itemDetailMdl->getList('order_obj_id,order_id,bn', ['delivery_id' => $delivery_id]);

        if (!$items_detail) {
            return false;
        }

        $order_id_arr = array_column($items_detail, 'order_id');
        $bn_arr       = array_column($items_detail, 'bn');

        // 判断是否有退款单
        $refund_list = $refundApplyMdl->getList('refund_apply_bn,oid,order_id,bn', ['order_id' => $order_id_arr, 'status' => '0']);
        if (!$refund_list) {
            return false;
        }

        foreach ($refund_list as $refund) {
            if (!in_array($refund['bn'], $bn_arr)) {
                continue;
            }

            $logi = ['trigger_event' => $status];
            if (in_array($status, ['delivery', 'cancel_fail'])) {
                // 发货单发货/取消失败，拒绝退款 
                $logi['cancel_dly_status'] = 'FAIL';
            } elseif (in_array($status, ['return_back', 'cancel'])) {
                // 发货单拦截成功|取消成功，同意退款
                $logi['cancel_dly_status'] = 'SUCCESS';
            }

            $package_list = $packageMdl->getList('logi_bn,logi_no,status', ['delivery_id' => $delivery_id, 'bn' => $refund['bn'], 'status|notin' => ['cancel', 'return_back'] ]);

            foreach ($package_list as $package) {
                if ($package['status'] == 'delivery') {
                    $logi['company_code'] = $package['logi_bn'];
                    $logi['logistics_no'] = $package['logi_no'];
                }
            }

            // 如果是同意退款，
            if ($logi['cancel_dly_status'] == 'SUCCESS' && count($package_list) > 0) {
                $logi['cancel_dly_status'] == 'FAIL';
            }

            $order_item = $orderItemMdl->db_dump(['order_id' => $refund['order_id'], 'bn' => $refund['bn']]);
            if ($logi['cancel_dly_status'] == 'SUCCESS' && $order_item['split_num'] != $order_item['return_num'] )  {
                $logi['cancel_dly_status'] == 'FAIL';
            }

            kernel::single('ome_refund_apply')->refund_ag($refund['refund_apply_bn'], $logi);
        }

        return true;
    }
}
