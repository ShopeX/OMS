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
 * @describe 发货处理
 */

class erpapi_shop_matrix_pinduoduo_request_delivery extends erpapi_shop_request_delivery
{
    /**
     * 发货请求参数
     *
     * @param array $sdf
     * @return array
     **/
    protected function get_confirm_params($sdf)
    {
        $param = parent::get_confirm_params($sdf);

        //修改发货模式：不传则默认为首次发货;
        //@todo：1为首次发货：用于订单首次发货,仅待发货订单可传入;
        //@todo：2为修改发货：用于订单修改发货,调用成功后将会覆盖原发货信息,仅已发货订单可传入(OMS现在没有此业务);
        $param['redelivery_type'] = 1;
        if($sdf['gift_order_flag']){
            $param['package_type'] = 'break';
        }
        if ($sdf['delivery_package'] &&
            (($sdf['is_split'] && !$sdf['is_first_delivery']) || !$sdf['is_split'])
        ) {
            $logistics_list = [];
            $firstDeliveryId = $sdf['first_delivery_id'];//第一张发货单ID
           
            foreach ($sdf['delivery_package'] as $package) {
                    //排除第一张发货单
                    if ($package['delivery_id'] == $firstDeliveryId) {
                        continue;
                    }
    
                    if (!$package['logi_bn'] || !$package['logi_no']) {
                        continue;
                    }
    
                    //未拆单wms多包裹回传，过滤掉主包裹
                    if (!$sdf['is_split'] && $sdf['logi_no'] == $package['logi_no']) {
                        continue;
                    }
                    
                    $logistics_list[$package['logi_no']]['logistics_no'] = $package['logi_no'];
                    $logistics_list[$package['logi_no']]['company_code'] = $package['logi_bn'];
                    $logistics_list[$package['logi_no']]['tid'] = $sdf['orderinfo']['order_bn'];
            }

            if($sdf['gift_items'] && $sdf['gift_order_status']==1){
                foreach($sdf['gift_items'] as $gv){
                    $gift_items = [
                        'tid'=>$gv['order_bn'],
                    ];
                 
                    $logistics_list[$gv['logi_no']]['logistics_no'] = $gv['logi_no'];
                    $logistics_list[$gv['logi_no']]['company_code'] = $gv['logi_bn'];
                    $logistics_list[$gv['logi_no']]['tid'] = $gv['order_bn'];
                }
            }

            if($sdf['gift_order_status'] && in_array($sdf['gift_order_status'],['1'])){

                if($sdf['gift_order_status']=='1' && $gift_items){

                    $tmparm = $param;
                    $param['packages_list'][0] = $param;

                    $giftparams =[];
                    foreach($sdf['gift_items'] as $gv){

                        $giftparams = [
                            'tid'=>$gv['order_bn'],
                            'logistics_no'=>$gv['logi_no'],
                            'company_code'=>$gv['logi_type'],
                            'company_name'=>$gv['logi_name'],
                            'redelivery_type'=>1,
                        ];
                    }

                 
                    $param['packages_list'][1] =$giftparams;
                    $param['is_single_item_send'] = true;
                }



            }else{
                if (count($logistics_list) >= 1){
                    // 第一次成功了，不再回写
                    if (isset($sdf['status_first_delivery']) && $sdf['status_first_delivery'] == 'succ'){
                        $param = [];
                    }
        
                    if (isset($sdf['is_first_delivery']) && !$sdf['is_first_delivery']){
                        $param = [];
                    }
        
                    $param['packages_list'][0] = $param;
                    $param['packages_list'][1] = [
                        'package_type'     => 'break',
                        'extra_track_type' => '1',//1=分包发货，2=补发商品，3=发放赠品
                        'tid'              => $sdf['orderinfo']['order_bn'],
                        'logistics_list'   => json_encode(array_values($logistics_list)),
                        'logistics_no'   => $sdf['logi_no'],//多包裹记日志使用
                    ];
                    
                    $param['is_single_item_send'] = true;
                }
            }
            
        }

        // 是否是国补订单的发货回写
        if ($sdf['serial_number']) {
            $order_id = $sdf['orderinfo']['order_id'];
            $isGuobu  = kernel::single('ome_bill_label')->getBillLabelInfo($order_id, 'order', 'SOMS_GB');
            if ($isGuobu) {
                $param['feature'] = $feature = [];
                foreach ($sdf['serial_number'] as $_v) {
                    if (!$feature['deviceSn']) {
                        $feature['deviceSn'] = implode(',', $_v['sn']);
                    } else {
                        $feature['deviceSn'] .= ','.implode(',', $_v['sn']);
                    }

                    if (!$feature['imei']) {
                        $feature['imei'] = implode(',', $_v['imei']);
                    } else {
                        $feature['imei'] .= ','.implode(',', $_v['imei']);
                    }
                }
                if ($feature['deviceSn']) {
                    $param['feature'][] = 'deviceSn='.$feature['deviceSn'];
                }
                if ($feature['imei']) {
                    $param['feature'][] = 'imei='.$feature['imei'];
                }

                if ($param['feature']) {
                    $param['feature'] = implode(',', $param['feature']);
                } else {
                    unset($param['feature']);
                }
            }
        }

        return $param;
    }

    /**
     * 更新发货单流水状态
     *
     * @return void
     * @author
     **/
    public function operationInWarehouse($sdf)
    {

        if ($sdf['status'] == 'succ') return $this->succ('更新发货状态不走此接口');

        // 获取请示参数
        $params = $this->operation_in_warehouse_params($sdf);

        // 标题
        $title = sprintf('拼多多打单信息同步平台[%s]',$sdf['delivery_bn']);

        return $this->__caller->call(SHOP_TRADE_OPERATION_IN_WAREHOUSE, $params, [], $title,10,$sdf['orderinfo']['order_bn']);
    }

    /**
     * 发货单流水状态参数
     *
     * @return array
     * @author
     **/
    protected function operation_in_warehouse_params($sdf)
    {
        $data['company_code'] = $sdf['type'];
        $data['order_sn']     = $sdf['orderinfo']['order_bn'];
        $data['order_state']  = '1';
        $data['waybill_no']   = $sdf['logi_no'];

        return $data;
    }
}
