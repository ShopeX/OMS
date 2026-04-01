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
 * @DateTime: 2022/7/4 10:25:21
 * @describe: 猫超国际发货回写
 * ============================
 */
class erpapi_shop_matrix_tmall_request_maochao_delivery extends erpapi_shop_request_delivery {

    /**
     * 发货请求参数
     *
     * @return void
     * @author 
     **/

    protected function get_confirm_params($sdf) {

        $order_id = $sdf['orderinfo']['order_id'];
        $isGuobu  = kernel::single('ome_bill_label')->getBillLabelInfo($order_id, 'order', 'SOMS_GB');
        
        // 喵速达
        $isMiaosuda = kernel::single('ome_bill_label')->getBillLabelInfo($order_id, 'order', 'SOMS_ASCP');
        
        // sdf
        $order = $sdf['orderinfo'];
        $orderExtend = $sdf['order_extend'];
        $extend_field = @json_decode($orderExtend['extend_field'], 1);
        $order_items = [];
        $one_tms_orders = [
            'tms_order_code' => $sdf['logi_no'],
            'company_code' => $sdf['logi_type'],
            'company_name' => $sdf['logi_name'],
            'tms_items' => []
        ];
        
        $storeCodes = [];
        $productOidNum =[];//捆绑商品只取其中一个为维度
        foreach ($sdf['order_objects'] as $v) {
            $obj_oid = $v['oid'];
            
            // 过滤编辑订单添加的商品
            if(empty($v['oid']) || $v['oid'] == '0'){
                continue;
            }
            
            $order_items[] = [
                'sub_order_code' => $v['oid'],
                'sc_item_id' => $v['shop_goods_id'],
                'item_quantity' => $v['quantity'],
            ];
            $item =current($v['order_items']);
            $productOidNum[$item['product_id']][$v['oid']] = ['shop_goods_id'=>$v['shop_goods_id'], 'radio'=>$v['quantity'] / $item['nums']];

            $_tmp = [
                'sub_order_code' => $v['oid'],
                'sc_item_id' => $v['shop_goods_id'],
                'item_quantity' => $v['quantity'],
            ];
            // 国补订单的发货回写需要回传sn
            if ($isGuobu && $sdf['serial_number']) {
                if ($sdf['serial_number'][$item['product_id']]) {
                    $_tmp['sn'] = implode(',', $sdf['serial_number'][$item['product_id']]['sn']);
                }
            }
            
            // store_code
            $store_code = $v['store_code'];
            
            // 如果 store_code 为空，且是喵速达订单，则从 extend_info 中获取
            if(empty($store_code) && $isMiaosuda && isset($isMiaosuda['extend_info']) && $isMiaosuda['extend_info'] && $obj_oid){
                $extendInfo = json_decode($isMiaosuda['extend_info'], true);
                if(is_array($extendInfo) && isset($extendInfo['storeCodeMap']) && is_array($extendInfo['storeCodeMap'])){
                    if(isset($extendInfo['storeCodeMap'][$obj_oid])){
                        $store_code = $extendInfo['storeCodeMap'][$obj_oid];
                    }
                }
            }
            
            if($store_code){
                $storeCodes[] = $store_code;
            }
            
            $one_tms_orders['tms_items'][] = $_tmp;
        }
        $tms_orders = [];
        if($sdf['delivery_package']) {
            foreach ($sdf['delivery_package'] as $key => $value) {
                if(!$productOidNum[$value['product_id']]) {
                    continue;
                }
                $value['logi_no'] || $value['logi_no'] = $sdf['logi_no'];
                $value['logi_bn'] = $sdf['logi_type']; #获取电子面单和发货用的编码不一致
                $value['logi_name'] || $value['logi_name'] = $sdf['logi_name'];
                if(!$tms_orders[$value['logi_no']]) {
                    $tms_orders[$value['logi_no']] = [
                        'tms_order_code' => $value['logi_no'],
                        'company_code' => $value['logi_bn'],
                        'company_name' => $value['logi_name'],
                        'tms_items' => []
                    ];
                }
                foreach ($productOidNum[$value['product_id']] as $oid => $pon) {
                    
                    // 过滤编辑订单添加的商品
                    if(empty($oid) || $oid == '0'){
                        continue;
                    }
                    
                    $tms_orders[$value['logi_no']]['tms_items'][] = [
                        'sub_order_code' => $oid,
                        'sc_item_id' => $pon['shop_goods_id'],
                        'item_quantity' => bcmul($pon['radio'], $value['number']),
                    ];
                }
            }
        } else {
            $tms_orders = [$one_tms_orders];
        }
        $objWaybill = app::get('logisticsmanager')->model('waybill');
        $waybillExtendModel = app::get('logisticsmanager')->model('waybill_extend');
        $waybill = $objWaybill->dump(array('waybill_number' => $sdf['logi_no']),'id');
        if($waybill) {
            $filter = array('waybill_id' => $waybill['id']);
            $wePc = $waybillExtendModel->dump($filter, 'print_config');
            $print_config = @json_decode($wePc['print_config'], 1);
            $shop_seller = $print_config['shop_seller'] ?  : [];
            $sender_info = [
                'sender_country' => '中国',
                'sender_province' => $shop_seller['province'],
                'sender_city' => $shop_seller['city'],
                'sender_area' => $shop_seller['area'],
                'sender_address' => $shop_seller['address_detail'],
                'sender_name' => $shop_seller['shop_name'],
                'sender_mobile' => $shop_seller['mobile'],
                'sender_phone' => $shop_seller['tel'],
            ];
        } else {
            $branch = $sdf['branch'];
            list(,$area,) = explode(':', $branch['area']);
            $area = explode('/', $area);
            $sender_info = [
                'sender_country' => '中国',
                'sender_province' => $area[0],
                'sender_city' => $area[1],
                'sender_area' => $area[2],
                'sender_address' => $branch['address'],
                'sender_name' => '天猫国际',
                'sender_mobile' => $branch['mobile'],
            ];
        }
        
        // store_code
        if($isMiaosuda && $storeCodes){
            // 喵速达：取平台推送的store_code
            $store_code = $storeCodes[0];
        }else{
            $store_code = $sdf['branch']['branch_bn'];
        }
        
        // params
        $params = [
            'tmall_type' => 'direct_marketing',
            "supplier_id"=> $extend_field['supplierId'],
            "biz_order_code"=> $order['order_bn'],
            "business_model"=> $extend_field['businessModel'],
            "out_biz_id" => $sdf['delivery_bn'],
            "store_code" => $store_code,
            "order_items" => json_encode($order_items),
            "tms_orders" => json_encode(array_values($tms_orders)),
            "sender_info" => json_encode($sender_info),
        ];
        return $params;
    }
}