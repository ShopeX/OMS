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
 * @author ykm 2020/8/26 18:12:35
 * @describe 发货处理
 */
class erpapi_shop_matrix_kuaishou_request_delivery extends erpapi_shop_request_delivery
{
    /**
     * 发货请求参数
     *
     * @return array
     * @author 
     **/

    protected function get_confirm_params($sdf)
    {
        $param = parent::get_confirm_params($sdf);

        //快手赠品发货回写需要调用拆单接口回写， 拆单回写接口支持所有场景，顾主品、赠品等场景统一走拆单回写接口
        $param['package_type'] = 'break';
        $imei = kernel::single('ome_bill_label')->getBillLabelInfo($sdf['orderinfo']['order_id'], 'order', 'SOMS_IMEI');
        $sn = kernel::single('ome_bill_label')->getBillLabelInfo($sdf['orderinfo']['order_id'], 'order', 'SOMS_SERIALNUMBER');
        $packages = [];
        foreach($sdf['delivery_items'] as $item){
            // OMS送赠不请求平台
            if (!$item['oid'] || $item['shop_goods_id'] == '-1' || !$item['logi_no']) {
                continue;
            }

            $packages[$item['logi_no']]['logistics_no'] = $item['logi_no'];
            $packages[$item['logi_no']]['company_code'] = $item['logi_type'];
            $tmpGood = [
                'sub_tid' => $item['oid'],
                'num' => $item['number'],
            ];
            if($imei || $sn) {
                $serialnumber = [];
                $imeiList = [];
                foreach($sdf['orderinfo']['order_objects'][$item['order_obj_id']]['order_items'] as $oi) {
                    if($sdf['serial_number'][$oi['product_id']]) {
                        $num = $oi['nums'] * $item['number'] / $sdf['orderinfo']['order_objects'][$item['order_obj_id']]['quantity'];
                        if($num < 1) {
                            continue;
                        }
                        $productSerial = $sdf['serial_number'][$oi['product_id']];
                        
                        // 处理 SN 数组
                        if(isset($productSerial['sn']) && is_array($productSerial['sn'])) {
                            foreach($productSerial['sn'] as $snk => $snv) {
                                if($num < 1) {
                                    break;
                                }
                                
                                // 提取 SN
                                if($sn) {
                                    $serialnumber[] = $snv;
                                }
                                
                                // 提取 IMEI：从 imei 数组中获取对应 SN 的 IMEI 值
                                if($imei && isset($productSerial['imei'][$snv])) {
                                    $imeiValue = $productSerial['imei'][$snv];
                                    // 如果是逗号分隔的字符串，转换为数组
                                    if(is_string($imeiValue) && strpos($imeiValue, ',') !== false) {
                                        $imeiArray = explode(',', $imeiValue);
                                        $imeiList = array_merge($imeiList, array_filter($imeiArray));
                                    } else {
                                        $imeiList[] = $imeiValue;
                                    }
                                }
                                
                                // 从原数组中移除，避免重复分配
                                unset($sdf['serial_number'][$oi['product_id']]['sn'][$snk]);
                                if(isset($sdf['serial_number'][$oi['product_id']]['imei'][$snv])) {
                                    unset($sdf['serial_number'][$oi['product_id']]['imei'][$snv]);
                                }
                                
                                $num--;
                            }
                        }
                    }
                }
                if($serialnumber && $sn) {
                    $tmpGood['serialNumberList'] = $serialnumber;
                }
                if($imeiList && $imei) {
                    $tmpGood['imeiList'] = array_values(array_unique($imeiList));
                }
            }
            $packages[$item['logi_no']]['goods'][] = $tmpGood;
        }

        // 带上赠品 
        /* if ($sdf['gift_logi_no']){
            foreach ($sdf['gift_logi_no'] as $gift){
                if (!$gift['oid'] || $gift['shop_goods_id'] == '-1' || !$gift['logi_no']) {
                    continue;
                }

                $packages[$gift['logi_no']]['logistics_no'] = $gift['logi_no'];
                $packages[$gift['logi_no']]['company_code'] = $gift['logi_type'];
                $packages[$gift['logi_no']]['goods'][] = [
                    'sub_tid' => $gift['oid'],
                    'num' => $gift['number'],
                ];
            }
        } */

        if (!$packages){
            return $this->succ('OMS赠品无需要回写');
        }

        $param['packages'] = json_encode(array_values($packages));

        // 如果明细已经全部发货，追回包裹号
        $packages_list = [];
        if ($sdf['delivery_package']){
            foreach($sdf['delivery_package'] as $value){
                // 不处理主单包裹
                if ($value['logi_no'] == $param['logistics_no']){
                    continue;
                }

                // 如果子单全部发货，进行追回包裹
                // $object = $sdf['orderinfo']['order_objects'][$value['order_obj_id']];
                // $nums = array_sum(array_column($object['order_items'], 'nums'));
                // $sendnum = array_sum(array_column($object['order_items'], 'sendnum'));

                if ($sdf['orderinfo']['ship_status'] == '1'){
                    $packages_list[$value['logi_no']]['package_type'] = 'append';
                    $packages_list[$value['logi_no']]['tid'] = $sdf['orderinfo']['order_bn'];
                    $packages_list[$value['logi_no']]['company_code'] = $value['logi_bn'];
                    $packages_list[$value['logi_no']]['logistics_no'] = $value['logi_no'];
                }
            }
        }

        if ($packages_list){
            $param['packages_list'] = array_merge([
                $param,
            ], array_values($packages_list));
            $param['is_single_item_send'] = true;
        }

        return $param;
    }
    
}