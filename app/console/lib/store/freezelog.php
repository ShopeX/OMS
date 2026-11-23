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

class console_store_freezelog{
    /**
    * 记录库存日志
    * @access public
    * @param Int $product_id 货品ID
    * @param String $shop_id 店铺ID
    * @param int $branch_id 仓库ID
    * @param Int $freeze_store 预占库存量
    * @param int $operator 运算符:0代表减少,1代表增加
    * @param array $log_data 日志数组array('original_id'=>'单据id','original_type'=>'单据类型'，'memo'=>'备注') 三个字段都必填
    * @return bool
    */
    public function add_log($product_id,$shop_id,$branch_id,$freeze_store,$operator,$log_data=array()){
        $logObj = app::get('console')->model('store_freeze_log');
        $log_data['original_type'] = $this->get_original_type($log_data['original_type']);
        $data = array(
            'product_id'=>$product_id,
            'num'=>$freeze_store,
            'shop_id'=>$shop_id ? $shop_id : '0',
            'branch_id'=>$branch_id ? $branch_id : '0',
            'original_id'=>$log_data['original_id'],
            'operator'=>$operator,
            'operate_time'=>time(),
            'original_type'=>$log_data['original_type'],
            'memo'=>$log_data['memo'],
            'status' => $log_data['status'],
            'addon' => isset($log_data['addon']) ? serialize($log_data['addon']) : ''
        );
        $rs = $logObj->save($data);
        if(!$rs){
            return false;
        }
        return true;
    }

    public function get_original_type($type){
        $data = array(
            'return_product'=>0,#售后
            'order'=>1,#订单
            'delivery'=>2,#发货
            'iostock'=>3,#出入库单
            'occupy'=>4,#占货
            'reship'=>5,#退货
            'product' => 6,#货品管理
            'purchase_return'=>7,#采购退货
            'allocate_out'=>8,//调拔出库
        );
        return $data[$type];
    }
   //第一个参数（reship）是model名称，第二个参数（reship_id）是主键名称，第三个参数（reship_bn）是bn
    public function get_original_id($type){
        $data = array(
            '0'=>array('return_product','return_id','return_bn'),#退货
            '1'=>array('orders','order_id','order_bn'),#订单
            '2'=>array('delivery','delivery_id','delivery_bn'),#发货
            '3'=>array('iostock','iostock_id','iostock_bn'),#出入库单
            '4'=>array('occupy','occupy_id','occupy_bn'),#占货
            '5'=>array('reship','reship_id','reship_bn'),#退货
            '6'=>array('product','product_id','product_bn'),#货品管理
            '7'=>array('purchase_return','product_id','product_bn'),#
            '8'=>array('iso','iso_id','iso_bn'),//调拔出库
        );
        return $data[$type];
    }
}