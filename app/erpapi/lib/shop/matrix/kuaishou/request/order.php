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
 * @author ykm 2020/8/26 18:13:16
 * @describe 订单处理
 */
class erpapi_shop_matrix_kuaishou_request_order extends erpapi_shop_request_order
{
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
        $regionInfo = kernel::single('eccommon_platform_regions')->getPlatformRegions($params, 'kuaishou');
        $params['provinceCode'] = $regionInfo['provinceCode']; //省
        $params['cityCode'] = $regionInfo['cityCode']; //市
        $params['districtCode'] = $regionInfo['districtCode']; //区
        $params['townCode'] = $regionInfo['townCode']; //镇
        
        return $params;
    }


    /**
     * confirmModifyAdress
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */

    public function confirmModifyAdress($sdf){
        $params = [
            'tid'=>$sdf['order_bn'],
            'is_approved'=>$sdf['confirm'] ? '0' : '1003', /*：0:同意;
拒绝需要传入以下参数：
1001:订单已进入拣货环节
1002:订单已进入配货环节
1003:订单已进入仓库环节
1004:订单已进入出库环节
1005:订单已进入发货环节*/
        ];
        $title = '买家修改地址确认修改';
        $rs = $this->__caller->call(SHOP_CONFIRM_ADDRESS_MODIFY,$params,array(),$title,20,$sdf['order_bn']);
        return $rs;
    }
}