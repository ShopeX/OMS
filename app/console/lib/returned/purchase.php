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

class console_returned_purchase{

    /**
    * 采购退货单详情
    *
    * return $array
    */
    function detail($rp_id){
        $returned_purchaseObj = app::get('purchase')->model('returned_purchase');
        $branchObj = app::get('ome')->model('branch');
        $supplierObj = app::get('purchase')->model('supplier');

        $data = $returned_purchaseObj->dump($rp_id,'*',array('returned_purchase_items' =>array("*")));
        $data['branch_name'] = $branchObj->Get_name($data['branch_id']);
        $supplier = $supplierObj->supplier_detail($data['supplier_id'],'name');
        $data['supplier_name'] = $supplier['name'];
        $data['memo'] = unserialize(($data['memo']));
        return $data;
    }

    /**
    * 更新采购退货单状态
    */
    function update_status($check_status,$rp_id){
        $db = kernel::database();
        $sql = 'UPDATE sdb_purchase_returned_purchase SET check_status='.$check_status.' WHERE rp_id='.$rp_id;
        $result = $db->exec($sql);
        return $result;
    }

    /**
    * 取消采购退货单
    * @param int $rp_id 退货单ID
    * @param string $memo 取消原因备注
    * @param bool $check_wms 是否检查WMS取消，默认true
    * @return array 返回结果
    */
    function cancel($rp_id, $memo = '', $check_wms = true){
        $rpObj = app::get('purchase')->model('returned_purchase');
        $itemsObj = app::get('purchase')->model('returned_purchase_items');
        $returnedObj = kernel::single('console_event_trigger_purchasereturn');
        $purchasereturnObj = kernel::single('console_receipt_purchasereturn');
        
        $rp = $rpObj->dump($rp_id, 'memo,branch_id,rp_bn,return_status,check_status,branch_id,out_iso_bn');
        
        if (empty($rp_id)){
            return array('rsp'=>'fail','error_msg'=>'操作出错，请重新操作');
        }
        
        if ($rp['return_status']>1){
            return array('rsp'=>'fail','error_msg'=>'出库取消失败');
        }
        
        // 如果需要检查WMS取消，先调用WMS取消接口
        if ($check_wms && $rp['check_status'] == '2') {
            if ($rp['return_status'] != '1') {
                return array('rsp'=>'fail','error_msg'=>'单据所在状态不允许此次操作');
            }
            
            $data = array(
                'io_type'=>'PURCHASE_RETURN',
                'io_bn'=>$rp['rp_bn'],
                'branch_id'=>$rp['branch_id'],
                'out_iso_bn'=>$rp['out_iso_bn'],
            );
            
            $wms_result = $returnedObj->cancel($data, true);
            if ($wms_result['rsp'] == 'fail') {
                return $wms_result; // WMS取消失败，直接返回，不进行后续OMS处理
            }
        }
        
        // OMS本地取消处理（始终执行）
        $updateData = array('return_status'=>5);
        if ($memo){
            $newmemo = htmlspecialchars($memo);
            $updateData['memo'] = $purchasereturnObj->format_memo($rp['memo'],$newmemo);
        }

        $rpObj->update($updateData,array('rp_id'=>$rp_id));
        
        if ($rp['check_status'] == '2') {#已审核取消需要取消冻结库存
            $items = $itemsObj->getlist('*',array('rp_id'=>$rp_id));

            //库存管控处理(审核通过后,库存处理)
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id'=>$rp['branch_id']));

            $params = array();
            $params['node_type'] = 'cancelReturned';
            $params['params'] = array('rp_id'=>$rp_id, 'branch_id'=>$rp['branch_id']);
            $params['params']['items'] = $items;

            $processResult = $storeManageLib->processBranchStore($params, $err_msg);
            if(!$processResult)
            {
                return array('rsp'=>'fail','error_msg'=>$err_msg);
            }
        }

        return array('rsp'=>'succ','msg'=>'出库取消已完成!');
    }
}


?>