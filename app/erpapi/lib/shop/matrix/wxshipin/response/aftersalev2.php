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
 * @Author: xueding@shopex.cn
 * @Date: 2023/4/20
 * @Describe: 售后接收类
 */
class erpapi_shop_matrix_wxshipin_response_aftersalev2 extends erpapi_shop_response_aftersalev2
{
    protected function _formatAddParams($params)
    {
        $sdf = parent::_formatAddParams($params);

        $platformDiscountReturnAmountFen = isset($sdf['extend_field']['refund_info']['platform_discount_return_amount'])
            ? $sdf['extend_field']['refund_info']['platform_discount_return_amount']
            : null;
        if ($platformDiscountReturnAmountFen === null || $platformDiscountReturnAmountFen === '' || !is_numeric($platformDiscountReturnAmountFen)) {
            return $sdf;
        }

        $platformDiscountReturnAmount = bcdiv((string) $platformDiscountReturnAmountFen, '100', 2);
        if (bccomp($platformDiscountReturnAmount, '0', 2) <= 0 || bccomp($sdf['refund_fee'], $platformDiscountReturnAmount, 2) < 0) {
            return $sdf;
        }

        /*
         * 视频号下发的refund_fee是商品结算退款总额，包含客户实付退款和平台优惠退回金额，
         * 但OMS订单payed只记录客户实付。若直接按refund_fee校验并扣减payed，连续部分退款时
         * 会因首笔多扣平台优惠，导致后续退款被“订单支付金额小于退款金额”错误拦截。
         * 参考SCRUM-7365的通用处理，将平台优惠单独记录供售后结算金额加回，并将refund_fee
         * 还原为客户退款金额，后续商品明细也会按该金额走公共分摊逻辑。
         */
        $sdf['platform_discount_return_amount'] = $platformDiscountReturnAmount;
        $sdf['refund_fee'] = bcsub($sdf['refund_fee'], $platformDiscountReturnAmount, 2);

        return $sdf;
    }

    protected function _getAddType($sdf)
    {
        //需要退货才更新为售后单
        if ($sdf['has_good_return'] == 'true') {
            if (in_array($sdf['order']['ship_status'],array('0'))) {
                //有退货，未发货的,做退款
                return 'refund';
            }else{
                //识别如果是已完成的售后，转成退款单更新的逻辑
                if(strtolower($sdf['status']) == 'success'){
                    $refundOriginalObj = app::get('ome')->model('return_product');
                    //退货状态必须是已完成
                    $refundOriginalInfo = $refundOriginalObj->getList('return_id', array('return_bn'=>$sdf['refund_bn'],'status' =>'4') , 0 , 1);
                    
                    if($refundOriginalInfo){
                        $refundApplyObj = app::get('ome')->model('refund_apply');
                        //售后退款申请单的退款状态，不能是已退款
                        $refundApplyInfo = $refundApplyObj->getList('refund_apply_bn', array('return_id'=>$refundOriginalInfo[0]['return_id'],'status' =>array('0','1','2','5','6')) , 0 , 1);
                        if($refundApplyInfo){
                            $sdf['refund_bn'] = $refundApplyInfo[0]['refund_apply_bn'];
                            return 'refund';
                        }
                    }
                }
                
                //有退货，已发货的,做售后
                return 'returnProduct';
            }
        }else{
            //无退货的，直接退款
            return 'refund';
        }
    }
    
    protected function _formatAddItemList($sdf, $convert=array())
    {
        $convert = array(
            'sdf_field'=>'oid',
            'order_field'=>'oid',
            'default_field'=>'outer_id'
        );
        
        return parent::_formatAddItemList($sdf, $convert);
    }
    
    protected function _refundAddSdf($sdf)
    {
        $refundOriginalObj = app::get('ome')->model('return_product');
        $refundApplyObj = app::get('ome')->model('refund_apply');
        
        if(strtolower($sdf['status']) == 'success'){
            //退货状态必须是已完成
            $refundOriginalInfo = $refundOriginalObj->getList('return_id', array('return_bn'=>$sdf['refund_bn'],'status' =>'4') , 0 , 1);
            if($refundOriginalInfo){
                //售后退款申请单的退款状态，不能是已退款
                $refundApplyInfo = $refundApplyObj->getList('refund_apply_bn', array('return_id'=>$refundOriginalInfo[0]['return_id'],'status' =>array('0','1','2','5','6')) , 0 , 1);
                if($refundApplyInfo){
                    $sdf['refund_bn'] = $refundApplyInfo[0]['refund_apply_bn'];
                }
            }
        }
        
        $sdf = parent::_refundAddSdf($sdf);
        
        return $sdf;
    }
}
