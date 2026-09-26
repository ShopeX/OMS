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
 * @desc 退款单数据保存
 * @author: jintao
 * @since: 2016/7/21
 */
class erpapi_shop_response_process_refund {

    /**
     * 添加
     * @param mixed $params 参数
     * @return mixed 返回值
     */

    public function add($params) {
        $refundApply = $params['refund_apply'];
        if($params['refund']) {
            $msg = '退款单' . $params['refund_bn'] . '已存在';
            
            return array('rsp' => 'succ', 'msg' => $msg);
        }
        if($params['status'] == 'succ' || $params['refund_type'] == 'refund') {
            return $this->_dealRefund($params);
        } elseif ($params['refund_type'] == 'apply') {
            return $this->_dealRefundApply($params);
        }
        return array('rsp'=>'fail', 'msg'=>'没有该类型(refund_type: ' . $params['refund_type'] . ')');
    }

    private function _dealRefund($params) {
        $refundApply = $params['refund_apply'];
        $msg = '生成退款单';
        $params['t_ready'] = kernel::single('ome_func')->date2time($params['t_ready']);
        $params['t_received'] = kernel::single('ome_func')->date2time($params['t_received']);
        $sdf = array(
            'refund_bn'     => $params['refund_bn'],
            'shop_id'       => $params['shop_id'],
            'order_id'      => $params['order']['order_id'],
            'account'       => $params['account'],
            'bank'          => $params['bank'],
            'pay_account'   => $params['pay_account'],
            'currency'      => 'CNY',
            'money'         => $params['money'],
            'paycost'       => $params['paycost'],
            'cur_money'     => $params['cur_money'],
            'pay_type'      => $params['pay_type'],
            'payment'       => $params['payment'] ? $params['payment'] : $refundApply['payment'],
            'paymethod'     => $params['paymethod'],
            'download_time' => time(),
            'status'        => $params['status'],
            'memo'          => $params['memo'],
            'trade_no'      => $params['trade_no'],
            'return_id'     => $params['refund_apply']['return_id'],
            'refund_refer'  => $params['refund_apply']['refund_refer'],
            'oid'           => $params['oid'],
            't_ready'       => $params['t_ready'],
            't_sent'        => $params['t_sent'],
            't_received'    => $params['t_received'],
        );
        $rs = app::get('ome')->model('refunds')->insert($sdf);
        if(!$rs) {
            return array('rsp'=>'fail', 'msg' => '退款单生成失败');
        }
        if ($refundApply) {
            $msg .= $this->_dealAfterRefund($refundApply, $sdf);
        }
        if($params['update_order_payed']) {
            $rs = $this->_updateOrder($params['order']['order_id'],$params['money']);
            $rs && $msg .= "\n更新订单[{$params['order_bn']}]支付状态";
        }

        $services = kernel::servicelist('ome.service.refund.apply.accept.refund.after');
        if ($services) {
            foreach ($services as $service) {
                if (method_exists($service, 'after_refund')) {
                    $service->after_refund($refundApply['apply_id']);
                }
            }
        }
        return array('rsp'=>'succ', 'msg'=>$msg);
    }

    private function _dealAfterRefund($refundApply, $sdf) {
        $filter = array(
            'apply_id' => $refundApply['apply_id'],
        );
        $updateData = array('status' => '4','refunded' => $sdf['money']);
        app::get('ome')->model('refund_apply')->update($updateData,$filter);
        $msg = "\n" . '更新退款申请单' . $refundApply['refund_apply_bn'];
        if ($refundApply['addon']) {
            $addon = unserialize($refundApply['addon']);
            $return_id = $addon['return_id'];
            $reship_id = $addon['reship_id'];
            if ($return_id) {
                $pReturnModel = app::get('ome')->model('return_product');
                $pReturnData = $pReturnModel->getList('refundmoney,return_bn', array('return_id' => $return_id), 0, 1);
                $pReturn = $pReturnData[0];
                $refundMoney = bcadd((float)$sdf['money'], (float)$pReturn['refundmoney'],3);
                $pReturnModel->update(array('refundmoney'=>$refundMoney,'status' => '4'),array('return_id'=>$return_id));
                $return_bn = $pReturn['return_bn'];
                $msg .= "\n更新售后申请单[{$return_bn}]金额：".$refundMoney;
            }
            if ($return_id || $reship_id) {
                //生成售后单
                kernel::single('sales_aftersale')->generate_aftersale($refundApply['apply_id'],'refund');
            }
        }
        return $msg;
    }

    private function _updateOrder($orderId, $refundMoney) {
        if (empty($orderId)) return false;
        //更新订单支付金额
        if ($refundMoney){
            $sql ="update sdb_ome_orders set payed=IF((CAST(payed AS char)-IFNULL(0,cost_payment)-".$refundMoney.")>=0,payed-IFNULL(0,cost_payment)-".$refundMoney.",0)  where order_id=".$orderId;
            kernel::database()->exec($sql);
            //更新订单支付状态
            if (kernel::single('ome_order_func')->update_order_pay_status($orderId, true, __CLASS__.'::'.__FUNCTION__)){
                return true;
            }else{
                return false;
            }
        }
    }

    private function _dealRefundApply($params) {
        if($params['refund_apply']) {
            $oOperation_log = app::get('ome')->model('operation_log');//写日志
            $refundApply = $params['refund_apply'];
            $updateData = array(
                'status' => $params['status'],
            );
            if($updateData['status'] == '0') {
                $sdf = $this->refund_apply_convert($params);
                $rs = app::get('ome')->model('refund_apply')->update($sdf, array('apply_id'=>$refundApply['apply_id']));
                $memo = '(退款金额、原因或版本变化)退款申请单更新为未审核';
            } else {
                if ($params['memo']) {
                    if ($refundApply['memo'] && false === strpos($params['memo'], $refundApply['memo'])) {
                        $updateData['memo'] = $refundApply['memo'] . ',' . $params['memo'];
                    } elseif (!$refundApply['memo']) {
                        $updateData['memo'] = $params['memo'];
                    }
                }
                $filter = array('apply_id' => $refundApply['apply_id'], 'money' => $params['money']);
                $rs = app::get('ome')->model('refund_apply')->update($updateData, $filter);
                $memo = "更新退款申请单[{$refundApply['refund_apply_bn']}]状态成功：{$params['status']},影响行数：" . $rs;
            }

            if (is_bool($rs)) {
                return array('rsp' => 'fail', 'msg' => "更新退款申请单[{$refundApply['refund_apply_bn']}]状态失败：可能是金额不一致");
            } else {
                kernel::single('ome_order_func')->update_order_pay_status($params['order']['order_id'], true, __CLASS__.'::'.__FUNCTION__);

                $oOperation_log->write_log('refund_apply@ome', $refundApply['apply_id'], $memo);
                return array('rsp' => 'succ', 'msg' => "更新退款申请单[{$refundApply['refund_apply_bn']}]状态成功：{$params['status']},影响行数：" . $rs);
            }

        } else {
            $sdf = $this->refund_apply_convert($params);
            
            //创建退款单
            $is_update_order    = true;//是否更新订单付款状态
            kernel::single('ome_refund_apply')->createRefundApply($sdf, $is_update_order, $error_msg);
    
            // 补充售前ag
            $this->_noticeAg($sdf);
            return array('rsp'=>'succ', 'msg'=>'退款申请单新建成功');
        }
    }

    private function refund_apply_convert($params) {
        $addon = serialize(array('refund_bn'=>$params['refund_bn']));

        $params['t_ready'] = kernel::single('ome_func')->date2time($params['t_ready']);
        $sdf = array(
            'order_id'        => $params['order']['order_id'],
            'refund_apply_bn' => $params['refund_bn'],
            'pay_type'        => $params['pay_type'],
            'account'         => $params['account'],
            'bank'            => $params['bank'],
            'pay_account'     => $params['pay_account'],
            'money'           => $params['money'] ? $params['money'] : '0',
            'refunded'        => '0',
            'memo'            => $params['memo'],
            'create_time'     => $params['t_ready'],
            'status'          => $params['status'],
            'shop_id'         => $params['shop_id'],
            'addon'           => $addon,
            'source'          => 'matrix',
            'shop_type'       => $params['shop_type'],
        );
        if($params['refund_refer']){
            $sdf['refund_refer'] = $params['refund_refer'];
        }

        return $sdf;
    }

    /**
     * statusUpdate
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function statusUpdate($params) {
        $filter = array(
            'refund_id' => $params['refund']['refund_id'],
            'status|noequal' => $params['status']
        );
        $updateData = array('status' => $params['status']);
        $msg = '更新退款单状态成功';
        $rs = app::get('ome')->model('refunds')->update($updateData,$filter);
        if(!is_bool($rs)) {
            if($params['update_order_payed']) {
                $rs = $this->_updateOrder($params['order']['order_id'],$params['refund']['money']);
                $rs && $msg .= "\n更新订单[{$params['order_bn']}]支付状态";
            }
        }
        return array('rsp' => 'succ', 'msg'=>$msg);

    }
    
    private function _noticeAg($sdf)
    {
        //取当前订单的处理状态
        $orderObj = app::get('ome')->model('orders');
        $order_filter = array("order_id" => $sdf['order_id']);
        $order_detail = $orderObj->dump($order_filter, 'order_bn,process_status,source');
        
        $aliag_status = app::get('ome')->getConf('shop.aliag.config.' . $sdf['shop_id']);
        // 兼容自有体系对接
        if ($aliag_status && in_array($sdf['shop_type'], array('ecos.ecshopx')) && $sdf['status'] == 0 && $order_detail['source'] == 'matrix') {
            //识别是否开启AG并且是天猫订单的新建退款申请
            $params = array(
                'order_bn' => $order_detail['order_bn'],
                'tid' => $order_detail['order_bn'],
                'order_id' => $sdf['order_id'],
                'apply_id' => $sdf['apply_id'],
                'refund_apply_id' => $sdf['apply_id'],
                'refund_bn' => $sdf['refund_apply_bn'],
                'is_aftersale_refund' => false,
                'shop_id' => $sdf['shop_id'],
                'money' => $sdf['money'],
                'pay_account' => $sdf['pay_account'],
            );
            $params['cancel_dly_status'] = 'SUCCESS';
            $dlyId = app::get('ome')->model('delivery')->getDeliverIdByOrderId($sdf['order_id']);
            //检查当前订单的状态
            if ($dlyId) {
                $params['cancel_dly_status'] = 'FAIL';
            }
            
            kernel::single('ome_service_refund')->refund_request($params);
        }
    }
}
