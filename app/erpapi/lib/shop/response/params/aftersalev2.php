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
 * @desc 售后单数据验证
 * @author: jintao
 * @since: 2016/7/21
 */
class erpapi_shop_response_params_aftersalev2 extends erpapi_shop_response_params_abstract {

    protected function add() {
        $arr = array(
            'order_bn' => array(
                'required' => 'true',
                'errmsg' => '订单号不能为空'
            ),
            'refund_bn' => array(
                'required' => 'true',
                'errmsg' => '售后单单号不能为空'
            ),
            'status' => array(
                'required' => 'true',
                'errmsg' => '售后状态不能为空'
            ),
//            'order' => array(
//                'type' => 'array',
//                'col' => array(
//                    'process_status' => array(
//                        'type' => 'enum',
//                        'in_out' => 'out',
//                        'value' => array('cancel'),
//                        'errmsg' => '订单已经取消，不能做售后'
//                    )
//                )
//            ),
            'response_bill_type' => array(
                'type' => 'method',
                'method' => 'validAddResponseBillType',
            )
        );
        return $arr;
    }

    protected function validAddResponseBillType($sdf) {
        //场景：解决更新订单全额退款(或取消订单)后,创建退款单失败;
        if($sdf['response_bill_type'] == 'refundonly') {
            return $this->_checkRefundOnlySdf($sdf);
        }
        
        if($sdf['response_bill_type'] == 'refund_apply') {
            return $this->_checkRefundApplySdf($sdf);
        }
        if($sdf['response_bill_type'] == 'refund') {
            return $this->_checkRefundSdf($sdf);
        }
        if($sdf['response_bill_type'] == 'return_product') {
            $rs = $this->_checkReturnProductSdf($sdf);
            return $rs;
        }
        if($sdf['response_bill_type'] == 'reship') {
            return $this->_checkReshipSdf($sdf);
        }
    }

    private function _checkRefundBaseSdf($sdf) {
        $refundApply = $sdf['refund_apply'];
        if($refundApply) {
            if (!$sdf['refund_version_change']) {
                if (in_array($refundApply['status'], array('3'))) {
                    return array('rsp' => 'fail', 'msg' => '退款申请单[' . $refundApply['refund_apply_bn'] . ']已拒绝，无法退款！');
                }
            }
            if (in_array($refundApply['status'], array('4'))) {
                return array('rsp' => 'fail', 'msg' => '退款申请单[' . $refundApply['refund_apply_bn'] . ']已退款，无法退款！');
            }
        }
//        if (($sdf['refund_fee'] <= 0 && $sdf['shop_type'] != 'xiaomi') && ($sdf['order']['is_cod'] == 'false' || ($sdf['order']['is_cod'] == 'true' && $sdf['order']['ship_status']!='0'))) {
//            return array('rsp'=>'fail', 'msg'=>'退款金额为0，只有货到付款并且未发货的订单才接收');
//        }
        return array('rsp'=>'succ');
    }

    protected function _checkRefundApplySdf($sdf) {
        $rs = $this->_checkRefundBaseSdf($sdf);
        if($rs['rsp'] != 'succ') {
            return $rs;
        }
        $refundApply = $sdf['refund_apply'];
        if($refundApply) {
            if (!$sdf['refund_version_change']) {
                if($sdf['status'] == '0') {
                    return array('rsp' => 'fail', 'msg' => '退款申请单(' . $refundApply['refund_apply_bn'] . ')已存在，且无版本变化');
                }
                
                if ($refundApply['status'] == '4') {
                    return array('rsp' => 'fail', 'msg' => '退款申请单已退款，无需处理');
                }
                
                //当modified修改时间字段为空时,退款单是(关闭、完成)状态时,无条件进行后续处理
                //@todo：京东退款申请单创建、更新时，平台modified字段有时候一直没有传值的情况;
                if(empty($sdf['modified']) && in_array($sdf['status'], array('3','4'))){
                    return array('rsp'=>'succ');
                }
                
                //检查更新时间是否变化
                if($sdf['modified'] < $refundApply['outer_lastmodify']) {
                    return array('rsp' => 'fail', 'msg' => '更新时间未变化,不更新');
                }
            }
        }
        return array('rsp'=>'succ');
    }
    
    /**
     * 单独只创建退款申请单、退款单,不编辑订单明细、也不更新订单金额
     * @todo：平台订单全额退款、已取消，同分同秒推送过来，OMS只更新订单为全额退款或取消订单，未创建退款单;
     *
     * @param $sdf
     * @return string[]
     */
    protected function _checkRefundOnlySdf($sdf)
    {
        $rs = $this->_checkRefundBaseSdf($sdf);
        if($rs['rsp'] != 'succ') {
            return $rs;
        }
        
        return array('rsp'=>'succ');
    }
    
    protected function _checkRefundSdf($sdf) {
        $rs = $this->_checkRefundBaseSdf($sdf);
        if($rs['rsp'] != 'succ') {
            return $rs;
        }
        if(!in_array($sdf['shop_type'],array('360buy','website'))){
            if (bccomp($sdf['order']['payed'], $sdf['refund_fee'],3) < 0) {
                return array('rsp'=>'fail', 'msg' => '退款失败,订单支付金额('.$sdf['order']['payed'].')小于退款金额('.$sdf['refund_fee'].')');
            }

        }
        
        if (!$sdf['refund_apply'] && $sdf['status'] == '3') {
            return array('rsp'=>'fail', 'msg' => '退款单退款失败：未生成退款申请单');
        }

        return array('rsp'=>'succ');
    }

    private function _checkItemNum($itemList, $otherItemList) {
        if(empty($itemList) || !is_array($itemList)) {
            return array('rsp'=>'fail', 'msg'=>'售后商品格式不正确');
        }
        $arrOtherItem = array();
        if($otherItemList) {
            foreach($otherItemList as $val) {
                if($arrOtherItem[$val['order_item_id']]) {
                    $arrOtherItem[$val['order_item_id']] += $val['num'];
                } else {
                    $arrOtherItem[$val['order_item_id']] = $val['num'];
                }
            }
        }
        foreach($itemList as $val) {
            if($val['product_id'] && $val['order_item_id']) {
                $num = intval($val['num']);
                $sendNum = intval($val['sendNum']);
                if($sendNum == 0) {
                    continue;
                }
                $otherNum = intval($arrOtherItem[$val['order_item_id']]);
                if($num > ($sendNum - $otherNum)) {
                    $msg = '商品' . $val['bn'] . '超出了可申请数量.申请数：' . $num . ',订单发货数：' . $sendNum;
                    if($otherNum) {
                        $msg .= ',已申请退货数量：'.$otherNum;
                    }
                    return array('rsp'=>'fail', 'msg'=>$msg);
                }
            }
        }
        return array('rsp'=>'succ');
    }

    protected function _checkReturnProductSdf($sdf) {
        if(in_array($sdf['order']['ship_status'], array(0,4)) && $sdf['return_type']!='jdchange') {
            $reship = $sdf['reship'];
            if($reship['change_order_id']>0){
                kernel::single('ome_return')->pauseChangeOrder($reship['change_order_id']);
            }
            return array('rsp'=>'fail', 'msg'=>'订单未发货或已退货，不能申请售后');
        }
        $returnProduct = $sdf['return_product'];
        if($returnProduct) {
            if ($returnProduct['status'] == '5' && !$sdf['refund_version_change']) {
                return array('rsp'=>'fail', 'msg' => '售后单已经拒绝,版本无变化,不接收');
            }
            if ($returnProduct['status'] == '9') {
                return array('rsp'=>'fail', 'msg' => '售后申请单已经完成，不能更改');
            }
            switch($sdf['status']) {
                case '1' :
                    if($returnProduct) {
                        if ($sdf['refund_version_change']) {
                            if($sdf['reship'] && !in_array($sdf['reship']['is_check'], array('0','5'))) {
                                return array('rsp' => 'fail', 'msg' => '退货单非未审核状态，单号：' . $sdf['reship']['reship_bn'] . '，不能重置');
                            }
                            $rs = $this->_checkItemNum($sdf['refund_item_list'], $sdf['other_reship_items']);
                            if ($rs['rsp'] != 'succ') {
                                return $rs;
                            }
                        } else {
                            return array('rsp' => 'fail', 'msg' => '售后申请单已经存在，且版本无变化');
                        }
                    }
                    break;
                case '3' :
                    if($returnProduct && $returnProduct['status']>=3 && $sdf['reship']) {
                        return array('rsp'=>'fail', 'msg'=>'退货单已经生成,单号：' . $sdf['reship']['reship_bn']);
                    }
                    if($returnProduct && $returnProduct['is_fail'] == 'true') {
                        return array('rsp'=>'fail', 'msg'=>'售后申请单处于失败状态，无法生成退货单，不处理');
                    }
                    break;
                case '4' :
                case '6' :
                    if($returnProduct && $returnProduct['is_fail'] == 'true') {
                        return array('rsp'=>'fail', 'msg'=>'售后申请单处于失败状态，无法生成退货单，不处理');
                    }
                    break;
                case '5' :
                    break;
                case '10' :
                    //平台商家拒绝退款,不是取消售后单
                    if(!$sdf['refund_version_change']){
                        return array('rsp'=>'fail', 'msg'=>'线上卖家拒绝退款,版本无变化,不接收');
                    }
                    break;
                default :
                    break;
            }
        } else {
            if($sdf['return_type']!='jdchange') {
                $rs = $this->_checkItemNum($sdf['refund_item_list'], $sdf['other_reship_items']);
                if($rs['rsp'] != 'succ') {
                    return $rs;
                }
            }
        }
        return array('rsp'=>'succ');
    }

    protected function _checkReshipSdf($sdf) {
        $reship = $sdf['reship'];
        if($reship) {
            if($reship['is_check'] == '5') {
                return array('rsp'=>'fail', 'msg'=>'退货单已拒绝');
            }
            
            if($sdf['modified'] < $reship['outer_lastmodify']) {
                return array('rsp' => 'fail', 'msg' => '更新时间未变化,不更新');
            }
        } else {
            $rs = $this->_checkItemNum($sdf['refund_item_list'], $sdf['other_reship_items']);
            if($rs['rsp'] != 'succ') {
                return $rs;
            }
        }
        return array('rsp'=>'succ');
    }
    
    protected function logisticsUpdate() {
        $arr = array(
            'return_bn' => array(
                'required' => 'true',
                'errmsg' => '售后申请单单号不能为空',
            ),
            'order_bn' => array(
                'required' => 'true',
                'errmsg' => '订单单号不能为空'
            ),
            'process_data' => array(
                'type' => 'array',
                'errmsg' => '缺少物流信息'
            )
        );
        return $arr;
    }
}
