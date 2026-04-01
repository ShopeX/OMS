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
 * 获取数据
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class ome_event_trigger_shop_data_delivery_360buy extends ome_event_trigger_shop_data_delivery_common
{
    public function get_sdf($delivery_id)
    {
        $this->__sdf = parent::get_sdf($delivery_id);

        if ($this->__sdf) {
            $this->_get_delivery_items_sdf($delivery_id);
            $this->_get_split_sdf($delivery_id);

            if(empty($this->__sdf['delivery_items'])) {
                return array ();
            }

            $delivery_bill_detail = $this->_get_delivery_bill_detail($delivery_id);
            
            $delivery_bill_list = array ();
            foreach ($delivery_bill_detail as $d_b_detail) {
                if ($d_b_detail['logi_no']) {
                    $delivery_bill_list[] = array(
                        'logi_type' => $this->__sdf['logi_type'],
                        'logi_name' => $this->__sdf['logi_name'],
                        'logi_no' => $d_b_detail['logi_no'],
                    );
                }
            }
            $this->__sdf['delivery_bill_list'] = $delivery_bill_list;

            // 唯一码
            $this->__sdf['serial_number'] = $this->_get_product_serial_sn_imei($delivery_id);
        }
        $order    = $this->__delivery_orders[$delivery_id];

        $is_jdlvmi = kernel::single('ome_order_bool_type')->isJDLVMI($order['order_bool_type']);
  
        if ($is_jdlvmi || $this->__sdf['is_split'] == 1) {
            $this->_get_order_objects_sdf($delivery_id);

            $delivery_package = $this->_get_delivery_package($delivery_id);
            $this->__sdf['delivery_package'] = $delivery_package;

            $packages = array();

            foreach($delivery_package as $v){
                $packages[$v['package_bn']][] = $v; 
            }
            $this->__sdf['packages'] = $packages;
            
            //不支持单运单全件分批出库
            // 如果delivery_items中包含order_objects的所有商品且只有一个运单号，重置is_split为0
            if ($this->__sdf['is_split'] == 1) {
                $this->_check_and_reset_split($delivery_id);
            }
        }
        $is_jdzd = kernel::single('ome_bill_label')->isJDZD($order['order_id']);

        if($is_jdzd){
            $this->__sdf['is_jdzd'] = true;
        }

        return $this->__sdf;
    }
    
    /**
     * 检查并重置is_split
     * 当delivery_items包含order_objects的所有商品且只有一个运单号时，重置is_split为0
     * 
     * @param int $delivery_id 发货单ID
     * @return void
     */
    private function _check_and_reset_split($delivery_id)
    {
        // 1. 从delivery_items中提取所有order_obj_id和对应的sendnum，按order_obj_id分组汇总
        $delivery_items = $this->__sdf['delivery_items'];
        if (empty($delivery_items)) {
            return;
        }
        
        $delivery_items_by_obj = array();
        foreach ($delivery_items as $item) {
            if (isset($item['order_obj_id']) && isset($item['sendnum'])) {
                $obj_id = $item['order_obj_id'];
                $sendnum = intval($item['sendnum']);
                if (!isset($delivery_items_by_obj[$obj_id])) {
                    $delivery_items_by_obj[$obj_id] = $sendnum;
                } else {
                    // 取更大值
                    $delivery_items_by_obj[$obj_id] = max($delivery_items_by_obj[$obj_id], $sendnum);
                }
            }
        }
        
        // 2. 从order_objects中获取所有order_obj_id和对应的quantity，排除已删除的商品
        $order_objects = isset($this->__sdf['orderinfo']['order_objects']) ? $this->__sdf['orderinfo']['order_objects'] : array();
        if (empty($order_objects)) {
            return;
        }
        
        $order_objects_by_obj = array();
        foreach ($order_objects as $obj_id => $obj) {
            // 排除已删除的order_objects
            if (isset($obj['delete']) && $obj['delete'] == 'true') {
                continue;
            }
            
            if (isset($obj['quantity'])) {
                $order_objects_by_obj[$obj_id] = intval($obj['quantity']);
            }
        }
        
        // 3. 遍历order_objects中的每个order_obj_id，检查是否在delivery_items中存在且sendnum == quantity
        $all_items_included = true;
        foreach ($order_objects_by_obj as $obj_id => $quantity) {
            if (!isset($delivery_items_by_obj[$obj_id])) {
                // order_obj_id不在delivery_items中
                $all_items_included = false;
                break;
            }
            if ($delivery_items_by_obj[$obj_id] != $quantity) {
                // sendnum不等于quantity，表示未全部发货
                $all_items_included = false;
                break;
            }
        }
        
        if (!$all_items_included) {
            return;
        }
        
        // 4. 检查运单号是否唯一
        $logi_nos = array();
        
        // 从delivery_package中提取所有非空的logi_no
        $delivery_package = isset($this->__sdf['delivery_package']) ? $this->__sdf['delivery_package'] : array();
        foreach ($delivery_package as $package) {
            if (isset($package['logi_no']) && !empty($package['logi_no'])) {
                $logi_nos[$package['logi_no']] = true;
            }
        }
        
        // 获取主单的运单号
        if (isset($this->__sdf['logi_no']) && !empty($this->__sdf['logi_no'])) {
            $logi_nos[$this->__sdf['logi_no']] = true;
        }
        
        // 检查是否只有一个唯一的运单号
        if (count($logi_nos) == 1) {
            // 5. 如果所有条件满足，重置is_split为0
            $this->__sdf['is_split'] = 0;
        }
    }
}
