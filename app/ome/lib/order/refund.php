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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0
 * @DateTime: 2022/9/27 16:31:37
 * @describe: 订单退款状态
 * ============================
 */
class ome_order_refund {

    /**
     * 检查RefundStatus
     * @param mixed $order order
     * @return mixed 返回验证结果
     */

    public function checkRefundStatus($order) {
        if(app::get('ome')->getConf('ome.order.refund.check') != 'true') {
            return [false, ['msg'=>'未开启配置']];
        }
        if($order['createway'] != 'matrix') {
            return [false, ['msg'=>'非平台订单']];
        }
        $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$order['shop_id']], 'node_id, node_type, business_type');
        if(empty($shop['node_id'])) {
            return [false, ['msg'=>'店铺未绑定']];
        }
        if(!in_array($shop['node_type'], ['taobao'])) {
            return [false, ['msg'=>'非淘宝店铺']];
        }
        if($shop['business_type'] == 'maochao') {
            return [false, ['msg'=>'猫超国际']];
        }
        
        list($rs, $notice) = kernel::single('ome_order_refund_status')->fetch($order['order_bn'], $shop['node_id'], $order['shop_id']);
        if(!$rs) {
            return [true, ['msg'=>$notice['msg']]];
        }
        if(!$notice['data']) {
            return [false, ['msg'=>'没有退款明细']];
        }
        $needRequest = false;
        foreach ($order['objects'] as $o) {
            if($notice['data'][$o['oid']]) {
                $needRequest = true;
                break;
            }
        }
        if(!$needRequest) {
            return [false, ['msg'=>'不需要请求详情']];
        }
        $rs = kernel::single('erpapi_router_request')->set('shop',$order['shop_id'])->finance_getRefundStatus(['order_bn'=>$order['order_bn']]);
        if($rs['rsp'] == 'fail') {
            return [true, ['msg'=>'接口失败：'.$rs['msg']]];
        }
        if($rs['data']) {
            $oids = [];
            foreach ($rs['data'] as $v) {
                if(!in_array($v['status'], ['SELLER_REFUSE_BUYER','CLOSED'])) {#非拒绝、关闭 都是退款 
                    $oids[] = $v['oid'];
                }
            }
            if($oids) {
                foreach ($order['objects'] as $o) {
                    if(in_array($o['oid'], $oids)) {
                        return [true, ['msg'=>'子单：'.$o['oid'].' 存在退款']];
                    }
                }
            }
        }
        return [false, ['msg'=>'不存在退款']];
    }

    /**
     * [兼容]订单已经全额退款并取消--创建退款单
     * 场景：平台上订单已退款,矩阵先推送了更新取消订单,后面才推送了退款单,导致OMS没有创建退款单;
     * @param $sdf
     * @return void
     */
    public function createFinishRefund($sdf, &$error_msg=null)
    {
        $refundObj = app::get('ome')->model('refunds');
        $refundApplyObj = app::get('ome')->model('refund_apply');

        $order_id = $sdf['order']['order_id'];

        //check
        if(empty($order_id) || empty($sdf['refund_bn']) || empty($sdf['shop_id'])){
            $error_msg = '无效的数据;';
            return false;
        }

        //检查是否存在退款申请单
        $applyINfo = $refundApplyObj->db_dump(array('order_id'=>$order_id), 'apply_id,refund_apply_bn');
        if($applyINfo){
            $error_msg = '已经存在退款申请单：'. $applyINfo['refund_apply_bn'] .'，无法自动创建退款单;';
            return false;
        }

        //检查是否存在退款单
        $refundInfo = $refundObj->db_dump(array('order_id'=>$order_id), 'refund_id,refund_bn');
        if($refundInfo){
            $error_msg = '已经存在退款单：'. $refundInfo['refund_bn'] .'，无法自动创建退款单;';
            return false;
        }

        //退款来源(normal:普通退款,aftersale:售后仅退款,不退货;)
        $refund_refer = '0';
        if($sdf['refund_refer'] == 'aftersale'){
            $refund_refer = '1';
        }

        //data
        $data = array(
            'refund_bn'     => $sdf['refund_bn'],
            'shop_id'       => $sdf['shop_id'],
            'order_id'      => $order_id,
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

        //insert
        $rs = $refundObj->insert($data);
        if(!$rs) {
            $error_msg = '退款单创建失败;';
            return false;
        }

        //logs
        $logObj = app::get('ome')->model('operation_log');
        $logObj->write_log('order_edit@ome', $order_id, '订单全额退款并取消,创建退款单!');

        return true;
    }

    /**
     * 创建退款单
     * @Author: XueDing
     * @Date: 2025/2/7 11:16 AM
     * @param array $sdf 包含退款单信息的数组
     * @return array 返回一个数组，第一个元素表示操作是否成功，第二个元素为成功或失败的消息
     */
    public function create($sdf)
    {
        $refundObj = app::get('ome')->model('refunds');

        $order_id = $sdf['order_id'];

        //check
        if (empty($order_id) || empty($sdf['refund_bn']) || empty($sdf['shop_id'])) {
            return [false, '无效的数据'];
        }

        //检查是否存在退款单
        $refundInfo = $refundObj->db_dump(array ('order_id' => $order_id,'refund_bn'=>$sdf['refund_bn']), 'refund_id,refund_bn');
        if ($refundInfo) {
            return [false, '已经存在退款单：' . $refundInfo['refund_bn'] . '，无法自动创建退款单;'];
        }

        //退款来源(normal:普通退款,aftersale:售后仅退款,不退货;)
        $refund_refer = '0';
        if ($sdf['refund_refer'] == 'aftersale') {
            $refund_refer = '1';
        }

        //data
        $data = array (
            'refund_bn'     => $sdf['refund_bn'],
            'shop_id'       => $sdf['shop_id'],
            'order_id'      => $order_id,
            'currency'      => 'CNY',
            'money'         => $sdf['refund_fee'],
            'cur_money'     => $sdf['cur_money'] ? $sdf['cur_money'] : $sdf['refund_fee'],
            'pay_type'      => $sdf['pay_type'],
            'download_time' => time(),
            'status'        => 'succ',
            'memo'          => $sdf['reason'],
            'modifiey'      => $sdf['modified'],
            'payment'       => $sdf['payment'],
            't_ready'       => $sdf['t_ready'] ? $sdf['t_ready'] : $sdf['t_sent'],
            't_sent'        => $sdf['t_sent'] ? $sdf['t_sent'] : $sdf['t_ready'],
            't_received'    => $sdf['t_received'] ? $sdf['t_received'] : 0,
            'org_id'        => $sdf['org_id'],
            'refund_refer'  => $refund_refer, //退款来源
        );

        //insert
        $rs = $refundObj->insert($data);
        if (!$rs) {
            return [false, '退款单创建失败'];
        }

        return [true];
    }
    
    /**
     * 退款申请备注
     * 
     * @return array
     */
    public function setReasonTypes()
    {
        $reasonList = array('退运费', '申请价保退款', '价保退款');
        
        return $reasonList;
    }

    public function lanjieDelivery($refundApplyId) {
        $refundApply = app::get('ome')->model('refund_apply')->db_dump( array('apply_id' => $refundApplyId, 'status' => '4', 'refund_refer'=>'0'), '*');
        if (!$refundApply) {
            return [false, ['msg'=>'售前完成的退款申请不存在']];
        }
        if($refundApply['bool_type'] & ome_refund_bool_type::__PROTECTED_CODE) {
            return [false, ['msg'=>'退款单为价保退款']];
        }
        if($refundApply['bool_type'] & ome_refund_bool_type::__ONLY_REFUND) {
            return [false, ['msg'=>'退款单为仅退款']];
        }
        $tgOrder = app::get('ome')->model('orders')->db_dump(['order_id'=>$refundApply['order_id']], 'pay_status,order_bn');
        if($refundApply['tag_type'] == '8') {
            kernel::single('monitor_event_notify')->addNotify('order_refund_apply_force_refund', [
                'refund_apply_bn'=>$refundApply['refund_apply_bn'],
                'order_bn'=>$tgOrder['order_bn'],
                'refund_fee'=>$refundApply['money'],
            ]);
            return kernel::single('console_reship')->qzRefundToLJRK($refundApply);
        }
        $filter = array('order_id'=>$refundApply['order_id']);
        $arrProduct = unserialize($refundApply['product_data']);
        if(!is_array($arrProduct)) {
            if($tgOrder['pay_status'] != '5') {
                return [false, ['msg'=>'订单未全额退款,退款单缺少明细']];
            }
        } else {
            $filter['order_item_id'] = array_column($arrProduct, 'order_item_id');
        }
        $didItems = app::get('sales')->model('delivery_order_item')->getList('distinct delivery_id', $filter);
        $timing_time = time() + 300;
        foreach($didItems as $didItem) {
            
            //logs
            app::get('ome')->model('operation_log')->write_log('delivery_back@ome', $didItem['delivery_id'], '退款单：'.$refundApply['refund_apply_bn'].'导致延时自动拦截：' . date('Y-m-d H:i:s', $timing_time));
            
            //延时任务
            $task = array(
                'obj_id' => $didItem['delivery_id'],
                'obj_type' => 'delivery_lan_jie',
                'exec_time' => $timing_time,
            );
            app::get('ome')->model('misc_task')->saveMiscTask($task);
        }
        return [true];
    }
    
    /**
     * 延迟更新订单取消状态
     * 场景：平台更新订单全额退款状态与请求WMS取消发货单同分同秒;
     * @todo：发货单撤消成功,订单是(全额退款+未发货+已拆分完)的状态;
     * 
     * @param array $orderIds
     * @return bool
     */
    public function reundCancelOrder($orderIds)
    {
        $orderObj = app::get('ome')->model('orders');
        $deliveryObj = app::get('ome')->model('delivery');
        
        //check
        if(empty($orderIds)){
            return false;
        }
        
        //list
        foreach ($orderIds as $orderKey => $order_id)
        {
            //如果有未取消的发货单,则退出
            $deliveryList = $deliveryObj->validDeiveryByOrderId($order_id);
            if($deliveryList){
                continue;
            }
            
            //订单信息
            $orderInfo = $orderObj->dump(array('order_id'=>$order_id), 'order_id,order_bn,payed,status,pay_status,ship_status,process_status');
            
            //check
            if(in_array($orderInfo['process_status'], array('cancel', 'remain_cancel'))){
                continue;
            }
            
            if($orderInfo['ship_status'] != '0'){
                continue;
            }
            
            if($orderInfo['pay_status'] != '5'){
                continue;
            }
            
            if($orderInfo['payed'] > 0){
                continue;
            }
            
            //订单取消
            $mod = 'async'; //异步不用请求：订单取消api接口
            $orderObj->cancel($order_id, '延迟更新取消订单状态', false, $mod, false);
        }
        
        return true;
    }
}
