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
 * 预定退款
 * 20180927 by wangjianjun
 */
class erpapi_shop_response_bookingrefund extends erpapi_shop_response_abstract {

    public function ordermsg($params){
        // 原有的预约退款逻辑
        $this->__apilog['title'] = '客户有意退款';
        $this->__apilog['original_bn'] = $params['tid'];
        $sdf = [
            'tid' => $params['tid'],
            'msg_id' => $params['msg_id'],
            'seller_nick' => $params['seller_nick'],
            'user_nick' => $params['user_nick'],
            'call_type' => $params['call_type'],
            'oid_list' => $params['oid_list'],
            'refundStatus' => $params['refundStatus'] ? : 1,
            'shop_id' => $this->__channelObj->channel['shop_id'],
            'sub_business_type' => [],
        ];

        if ( is_array($this->__channelObj->channel['config']) && $this->__channelObj->channel['config']['sub_business_type'] ) {
            $sdf['sub_business_type'] = (array) $this->__channelObj->channel['config']['sub_business_type'];
        }

        return $sdf;
    }

    public function fxordermsg($params){
        // shopbee供销供应商订单信息同步
        $this->__apilog['title'] = '供销供应商订单信息同步';
        $this->__apilog['original_bn'] = $params['bizOrderCode'];
        if (empty($params['bizOrderCode'])) {
            return array('rsp' => 'fail', 'msg' => '业务单号不能为空');
        }
        $sdf = [
            'order_bn' => $params['bizOrderCode'],
            'shop_id' => $this->__channelObj->channel['shop_id'],
            'supplierName' => $params['supplierName'],
            'buyerComments' => $params['buyerComments'],
            'supplierId' => $params['supplierId'],
            'sellerId' => $params['sellerId'],
            'bizType' => $params['bizType'],
            'outBizCode' => $params['outBizCode'],
            'extraContent' => $params['extraContent'],
            'sellerComments' => $params['sellerComments'],
            'sellerName' => $params['sellerName'],
            'bizOrderCode' => $params['bizOrderCode'],
            'deliverRequirement' => $params['deliverRequirement'],
            'appointArrivedTime' => $params['appointArrivedTime'],
            'appointDeliveryTime' => $params['appointDeliveryTime'],
        ];
        return $sdf;
    }
    

    public function ordercancle($sdf){

        $this->__apilog['result']['data'] = array('tid'=>$sdf['orderId']);
        $this->__apilog['original_bn']    = $sdf['orderId'];
        $this->__apilog['title']          = '单据取消['.$sdf['orderId'].']';

        $shop_id = $this->__channelObj->channel['shop_id'];
        $data = array(
            'order_bn'      =>  $sdf['orderId'],
            'shop_id'       =>  $shop_id,
            'reason'        =>  $sdf['cancelReason'],

        );

        return $data;

    }
}
