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
* 唯品会出库事件
*/
class console_event_trigger_vopstockout extends console_event_trigger_stockoutabstract
{
    /**
     * 出库数据整理
     */
    function getStockOutParam($param)
    {
        $iso_id    = $param['iso_id'];
        
        //组织入库详情和明细
        $stockLib    = kernel::single('purchase_purchase_stockout');
        $error_msg   = '';
        $data        = $stockLib->get_iostockData($iso_id, $error_msg);
        
        return $data;
    }
    
    /**
     * 更新运单号
     */
    protected function update_out_bn($out_order_code, $result)
    {
        $logistics_code = isset($result['data']['wms_order_code']) ? $result['data']['wms_order_code'] : '';
        if($logistics_code)
        {
            $stockObj  = app::get('purchase')->model('pick_stockout_bills');
            $row       = $stockObj->dump(array('stockout_no'=>$out_order_code), 'stockout_id');
            
            //更新
            $stockObj->update(array('delivery_no'=>$logistics_code, 'check_time'=>time(), 'last_modified'=>time()), array('stockout_id'=>$row['stockout_id']));
            
            //更新日志
            $logObj          = app::get('ome')->model('operation_log');
            $logObj->write_log('update_stockout_bills@ome', $row['stockout_id'], '推送出库信息成功,获取运单号：'. $logistics_code);
        }
        
        return true;
    }

    // 返回给唯品会的时效订单结果反馈
    public function occupied_order_feedback($data, $sync = false)
    {
        $stockout_id  = $data['stockout_id'];
        $stockObj     = app::get('purchase')->model('pick_stockout_bills');
        $stockItemObj = app::get('purchase')->model('pick_stockout_bill_items');
        $inventoryObj = app::get('purchase')->model('pick_bill_item_inventory');
        $branchObj    = app::get('ome')->model('branch');

        $stockout     = $stockObj->db_dump(array('stockout_id'=>$stockout_id), '*');
        $branchInfo   = $branchObj->db_dump(array('branch_id'=>$stockout['branch_id'], 'check_permission'=>'false'), 'branch_bn,storage_code,owner_code');
        $wms_id = kernel::single('ome_branch')->getWmsIdById($stockout['branch_id']);
        if ($wms_id) {
            // $wmsModel = app::get('channel')->model('channel');
            // $wms      = $wmsModel->dump(['channel_id'=>$wms_id]);
            $branchInfo['wms_branch_bn'] = kernel::single('erpapi_wms_request_stockout')->get_warehouse_code($wms_id, $branchInfo['branch_bn']);
        }

        $stockoutItems = $stockItemObj->getList('*', ['stockout_id' => $stockout_id]);
        $barcodeList   = array_column($stockoutItems, 'barcode');

        $pick = kernel::single('purchase_purchase_inventory')->getPickFromStockout($stockout_id);
        if (!$pick) {
            return false;
        }
        $pickNoList = array_column($pick, 'pick_no');
        $inventory  = $inventoryObj->getList('*', [
            'pick_no|in'  =>  $pickNoList, 
            'barcode|in'  =>  $barcodeList,
            'num|than'    =>  0,
        ]);
        if (!$inventory) {
            return false;
        }
        $params = [];
        foreach ($inventory as $k => $v) {
            $v['warehouse'] = $branchInfo['wms_branch_bn']; // 仓库编码传出库单的发货仓编码
            $params[$v['order_sn']][] = $v;
        }

        //获取店铺shop_id
        $sql     = "SELECT shop_id FROM sdb_purchase_order WHERE po_id=". $pick[0]['po_id'];
        $poInfo  = kernel::database()->selectrow($sql);

        foreach ($params as $key => $param) {
            $rsp    = kernel::single('erpapi_router_request')->set('shop', $poInfo['shop_id'])->purchase_getOrderFeedback($param);
        }
        return true;
    }
}

?>