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

class console_event_trigger_purchasereturn extends console_event_trigger_stockoutabstract{

    
    protected $_io_type = 'PURCHASE_RETURN';

    /**
     * 
     */
    function getStockOutParam($param){
        $rp_id = $param['rp_id'];
        $returned_purchaseObj = app::get('purchase')->model('returned_purchase');
        $purchaseReturn = $returned_purchaseObj->dump($rp_id, '*', array('returned_purchase_items'=>array('product_id,num,bn,name,price')));

        $corp_id = $purchaseReturn['corp_id'];
        $branch_id = $purchaseReturn['branch_id'];
        $branch_detail = $this->getBranchByid($branch_id);
        $memo = $purchaseReturn['memo'];
        $supplier_id = $purchaseReturn['supplier_id'];
       
        
        $tmp = array(
            'io_type'=> 'PURCHASE_RETURN',
            'io_bn'=> $purchaseReturn['rp_bn'],
            'branch_bn'=> $branch_detail['branch_bn'],
            'storage_code'=> $branch_detail['storage_code'],
            'owner_code'=> $branch_detail['owner_code'],
            'create_time'=> $purchaseReturn['returned_time'],
            'total_goods_fee'=> $purchaseReturn['amount'],
            'branch_id'=>$branch_id,
            'branch_type'=>$branch_detail['type'],
            'supplier_id'=>$supplier_id,
            );
        if ($purchaseReturn['corp_id']) {
            $corp_id = $purchaseReturn['corp_id'];
            $oDly_corp = app::get('ome')->model('dly_corp');
            $dly_corp = $oDly_corp->dump($corp_id,'type');
            $tmp['logi_code'] = $dly_corp['type'];
        }
   
        if ($memo){
            $memo = unserialize($memo);
            if($memo){
                $memo = array_pop($memo);
                $tmp['memo'] = $memo['op_content'];
            }
            
        }
        $tmp['items'] = $purchaseReturn['returned_purchase_items'];

        return $tmp;
    }

    
    /**
     * 更新外部单编号
     * @param   
     * @return  
     * @access  protected
     * @author cyyr24@sina.cn
     */
    protected function update_out_bn($io_bn,$result)
    {
        $out_iso_bn = $result['data']['wms_order_code'];
        $returned_purchaseObj = app::get('purchase')->model('returned_purchase');
        $data = array(
            'out_iso_bn'=>(string)$out_iso_bn
        );
        if($result['rsp'] == 'fail') {
            $data['sync_status'] = '2';
            $data['sync_msg'] = $result['msg'];
        }else{
            if($out_iso_bn) {
                $data['sync_status'] = '3';
                $data['sync_msg'] = '';
            }
        }
        $returned_purchaseObj->update($data,array('rp_bn'=>$io_bn));
    }

    /**
     * 查询采购退货结果
     *
     * @return void
     * @author 
     **/
    public function searchPurchaseReturn($rp_id)
    {
        $rp = app::get('purchase')->model('returned_purchase')->db_dump($rp_id);

        $wms_id = kernel::single('ome_branch')->getWmsIdById($rp['branch_id']);

        $data = array(
            'out_order_code'    =>  $rp['out_iso_bn'],
            'stockout_bn'       =>  $rp['rp_bn'],
        );

        return $this->search($wms_id, $data);
    }
}