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
 * @author ykm 2016/12/15
 * @describe 订单处理
 */

class erpapi_shop_matrix_pinduoduo_request_order extends erpapi_shop_request_order
{
    /**
     * @param array
     * @return array
     * order_status 	订单状态 	0：异常状态，1：待发货，2：已发货待签收，3：已签收
     * refund_status 	售后状态 	0：异常状态，1：无售后或售后关闭，2：售后处理中，3：退款中，4：退款成功
     */
    protected function doGetOrderStatusRet($rsp) {
        $data = array();
        if($rsp['data']) {
            $tmp = json_decode($rsp['data'], 1);
            foreach($tmp as $val){
                $data[$val['orderSn']] = ($val['order_status'] != 0 && $val['refund_status'] == 1) ? true : false;
            }
        }
        $rsp['data'] = $data;
        return $rsp;
    }

    protected function __formatUpdateOrderShippingInfo($order) {
        $consignee_area = $order['consignee']['area'];
        if(strpos($consignee_area,":")){
            $t_area            = explode(":",$consignee_area);
            $t_area_1          = explode("/",$t_area[1]);
            $receiver_state    = $t_area_1[0];
            $receiver_city     = $t_area_1[1];
            $receiver_district = $t_area_1[2];
            $receiver_town = $t_area_1[3]; //街道
        }
        $params = array();
        $params['tid']               = $order['order_bn'];
        $params['receiver_name']     = $order['consignee']['name']?$order['consignee']['name']:'';
        $params['receiver_phone']    = $order['consignee']['telephone']?$order['consignee']['telephone']:'';
        $params['receiver_mobile']   = $order['consignee']['mobile']?$order['consignee']['mobile']:'';
        $params['receiver_state']    = $receiver_state ? $receiver_state : '';
        $params['receiver_city']     = $receiver_city ? $receiver_city : '';
        $params['receiver_district'] = $receiver_district ? $receiver_district : '';
        $params['receiver_street'] = $receiver_town ? $receiver_town : ''; //镇、街道
        $params['receiver_address']  = $order['consignee']['addr']?$order['consignee']['addr']:'';
        $params['receiver_zip']      = $order['consignee']['zip']?$order['consignee']['zip']:'';
        
        //获取平台省市区code
        $regionInfo = kernel::single('eccommon_platform_regions')->getPlatformRegions($params, 'pinduoduo');
        $params['province_id'] = $regionInfo['provinceCode']; //省
        $params['city_id'] = $regionInfo['cityCode']; //市
        $params['town_id'] = $regionInfo['districtCode']; //区
        
        return $params;
    }
}