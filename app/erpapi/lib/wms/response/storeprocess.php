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
 * @DateTime: 2022/11/28 16:42:33
 * @describe: 加工单
 * ============================
 */

class erpapi_wms_response_storeprocess extends erpapi_wms_response_abstract
{    
    /**
     * wms.storeprocess.status_update
     *
     **/
    public function status_update($params){
        $this->__apilog['title']       = '加工单确认';
        $this->__apilog['original_bn'] = $params['processOrderCode'];
        
        // productitems
        if(is_array($params['productitems'])){
            $params['productitems'] = json_encode($params['productitems']);
        }
        
        // materialitems
        if(is_array($params['materialitems'])){
            $params['materialitems'] = json_encode($params['materialitems']);
        }
        
        // 预先保存WMS回传结果数据
        $this->_dealWMSParams($params);
        
        // data
        $data = [
            'mp_bn' => $params['processOrderCode'],
            'out_mp_bn' => $params['processOrderId'],
            'complete_time' => $params['orderCompleteTime'],
        ];
        
        // productitems
        $inItems = [];
        $items = isset($params['productitems']) ? json_decode($params['productitems'],true) : array();
        if($items){
            // 兼容WMS未传item结构体
            if(isset($items['item'])){
                $productitems = $this->transfer_items($items);
            }elseif(isset($items['items'])){
                $productitems = isset($items['items'][0]) ? $items['items'] : [$items['items']];
            }else{
                if(isset($items[0])){
                    $productitems = $items;
                }else{
                    $productitems = [$items];
                }
            }
            
            foreach($productitems as $key=>$val){
                if($inItems[$val['itemCode']]) {
                    $inItems[$val['itemCode']]['number'] += $val['quantity'];
                } else {
                    $inItems[$val['itemCode']] = [
                        'bm_bn' => $val['itemCode'],
                        'number' => $val['quantity'],
                    ];
                }

                if($val['batchCode']){
                    $inItems[$val['itemCode']]['batch'][] = array(
                        'bn'                => $val['itemCode'],
                        'purchase_code'     => $val['batchCode'],
                        'produce_code'      => $val['produceCode'],
                        'product_time'      => strtotime($val['productDate']) ? strtotime($val['productDate']) : 0,
                        'expire_time'       => strtotime($val['expireDate']) ? strtotime($val['expireDate']) : 0,
                        'normal_defective'  => ($val['inventoryType'] == 'CC' ? 'defective' : 'normal'),
                        'num'               => $val['quantity'],
                    );
                }
            }
        }
        $data['material_items'] = $inItems;
        
        // materialitems
        $outItems = [];
        $items = isset($params['materialitems']) ? json_decode($params['materialitems'],true) : array();
        if($items){
            // 兼容WMS未传item结构体
            if(isset($items['item'])){
                $materialitems = $this->transfer_items($items);
            }elseif(isset($items['items'])){
                $materialitems = isset($items['items'][0]) ? $items['items'] : [$items['items']];
            }else{
                if(isset($items[0])){
                    $materialitems = $items;
                }else{
                    $materialitems = [$items];
                }
            }
            
            foreach($materialitems as $key=>$val){
                if($outItems[$val['itemCode']]) {
                    $outItems[$val['itemCode']]['number'] += $val['quantity'];
                } else {
                    $outItems[$val['itemCode']] = [
                        'bm_bn' => $val['itemCode'],
                        'number' => $val['quantity'],
                    ];
                }

                if($val['batchCode']){
                    $outItems[$val['itemCode']]['batch'][] = [
                        'bn'                => $val['itemCode'],
                        'purchase_code'     => $val['batchCode'],
                        'produce_code'      => $val['produceCode'],
                        'product_time'      => strtotime($val['productDate']) ? strtotime($val['productDate']) : 0,
                        'expire_time'       => strtotime($val['expireDate']) ? strtotime($val['expireDate']) : 0,
                        'normal_defective'  => ($val['inventoryType'] == 'CC' ? 'defective' : 'normal'),
                        'num'               => $val['quantity'],
                    ];
                }
            }
        }
        $data['product_items'] = $outItems;
        
        return $data;
    }

    protected function transfer_items($items) {
        return isset($items['item'][0]) ? $items['item'] : [$items['item']];
    }

    protected function _dealWMSParams($params) {
        if((empty($params['processOrderId']) && empty($params['processOrderCode']))) {
            return [false, ['msg'=>'参数不符合规范']];
        }
        $nodeId = $this->__channelObj->channel['node_id'];
        $wstMdl = app::get('console')->model('wms_storeprocess');
        if($params['processOrderCode']) {
            if($row = $wstMdl->db_dump(['mp_bn'=>$params['processOrderCode'], 'wms_node_id'=>$nodeId], 'id')) {
                return [true, ['msg'=>'已经存在']];
            }
        } else {
            if($row = $wstMdl->db_dump(['processOrderId'=>$params['processOrderId']], 'id')) {
                return [true, ['msg'=>'已经存在']];
            }
        }
        $inData = [];
        $inData['process_order_id'] = $params['processOrderId'];
        $inData['mp_bn'] = $params['processOrderCode'];
        $inData['wms_node_id'] = $nodeId;  
        $inData['order_type'] = $params['orderType'];
        $inData['order_complete_time'] = $params['orderCompleteTime'];
        $inData['actual_qty'] = $params['actualQty'];
        $inData['remark'] = $params['remark'];
        $inData['warehouse_code'] = $params['warehouseCode'];
        $inData['extend_props'] = $params['extendProps'];
        $id = $wstMdl->insert($inData);
        if(!$id) {
            return [false, ['msg'=>'主表写入失败']];
        }
        
        // materialitems
        $items = isset($params['materialitems']) ? json_decode($params['materialitems'],true) : array();
        if($items){
            $inItems = [];
            
            // 兼容WMS未传item结构体
            if(isset($items['item'])){
                $materialitems = $this->transfer_items($items);
            }elseif(isset($items['items'])){
                $materialitems = isset($items['items'][0]) ? $items['items'] : [$items['items']];
            }else{
                if(isset($items[0])){
                    $materialitems = $items;
                }else{
                    $materialitems = [$items];
                }
            }
            
            foreach($materialitems as $key=>$val){
                $inItems[] = [
                    'wsp_id' => $id,
                    'item_code' => $val['itemCode'],
                    'item_id' => $val['itemId'],
                    'inventory_type' => $val['inventoryType'],
                    'quantity' => $val['quantity'],
                    'product_date' => $val['productDate'],
                    'expire_date' => $val['expireDate'],
                    'produce_code' => $val['produceCode'],
                    'batch_code' => $val['batchCode'],
                    'remark' => $val['remark'],
                ];
            }
            $wriMdl = app::get('console')->model('wms_storeprocess_materialitems');
            $sql = kernel::single('ome_func')->get_insert_sql($wriMdl, $inItems);
            $wriMdl->db->exec($sql);
        }
        
        // productitems
        $items = isset($params['productitems']) ? json_decode($params['productitems'],true) : array();
        if($items){
            $outItems = [];
            
            // 兼容WMS未传item结构体
            if(isset($items['item'])){
                $productitems = $this->transfer_items($items);
            }elseif(isset($items['items'])){
                $productitems = isset($items['items'][0]) ? $items['items'] : [$items['items']];
            }else{
                if(isset($items[0])){
                    $productitems = $items;
                }else{
                    $productitems = [$items];
                }
            }
            
            foreach($productitems as $key=>$val){
                $outItems[] = [
                    'wsp_id' => $id,
                    'item_code' => $val['itemCode'],
                    'item_id' => $val['itemId'],
                    'inventory_type' => $val['inventoryType'],
                    'quantity' => $val['quantity'],
                    'product_date' => $val['productDate'],
                    'expire_date' => $val['expireDate'],
                    'produce_code' => $val['produceCode'],
                    'batch_code' => $val['batchCode'],
                    'remark' => $val['remark'],
                ];
            }
            $wriMdl = app::get('console')->model('wms_storeprocess_productitems');
            $sql = kernel::single('ome_func')->get_insert_sql($wriMdl, $outItems);
            $wriMdl->db->exec($sql);
        }
        
        // log
        app::get('ome')->model('operation_log')->write_log('wms_storeprocess@console',$id, '接收成功');
        
        return [true, ['msg'=>'处理完成']];
    }
}
