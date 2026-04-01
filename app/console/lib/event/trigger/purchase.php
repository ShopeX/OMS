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

class console_event_trigger_purchase extends console_event_trigger_stockinabstract{

    
    protected $_io_type = 'PURCHASE';

    /**
     * 入库数据
     */
    function getStockInParam($param){
        
        $Opo = app::get('purchase')->model('po');
        $po_id = $param['po_id'];
        $aRow = $Opo->dump($po_id, '*', array('po_items' => array('name,price,num,bn,product_id')));
        $branch_id = $aRow['branch_id'];
        $branch_detail = $this->getBranchByid($branch_id);
        $supplier_id = $aRow['supplier_id'];
        $purchase = array(
            'io_type'         => 'PURCHASE',
            'io_bn'           => $aRow['po_bn'],
            'branch_bn'       => $branch_detail['branch_bn'],
            'storage_code'    => $branch_detail['storage_code'],
            'owner_code'    => $branch_detail['owner_code'],
            'create_time'     => $aRow['purchase_time'],
            'arrive_time'     => $aRow['arrive_time'],
            'total_goods_fee' => $aRow['amount'],
            'branch_id'       => $branch_id,
            'supplier_id'     => $supplier_id,
            'receiver_name'   => $branch_detail['uname'],
            'receiver_address' => $branch_detail['address'],
            'receiver_phone' => $branch_detail['phone'],
            'receiver_mobile' => $branch_detail['mobile'],
            'receiver_zip' => $branch_detail['zip'],
        );
        
        $area = $branch_detail['area'];
        if ($area) {
            kernel::single('eccommon_regions')->split_area($area);
            $purchase['receiver_state']    = $area[0];
            $purchase['receiver_city']     = $area[1];
            $purchase['receiver_district'] = $area[2];
        }


        $memo = $aRow['memo'];
        if ($memo){
            $memo = unserialize($memo);
            if($memo){
                $memo = array_pop($memo);
                $purchase['memo'] = $memo['op_content'];
            }
            
        }
        $item = array();
        foreach($aRow['po_items'] as $po_items){
            $item[] = array(
                'num'  =>$po_items['num'],
                'bn'  =>$po_items['bn'],
                'name'  =>$po_items['name'],
                'price' => $po_items['price'],
            
            );
        }
        $purchase['items'] = $item;
       
        return $purchase;
    }

    protected function update_out_bn($io_bn,$result)
    {
        $out_iso_bn = is_array($result['data']) ? $result['data']['wms_order_code'] : '';
        $oPo = app::get('purchase')->model('po');
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
        $result = $oPo->update($data,array('po_bn'=>$io_bn));
       
    }

    /**
     * 查询采购单结果
     *
     * @return void
     * @author 
     **/
    public function searchPurchase($po_id)
    {
        $po = app::get('purchase')->model('po')->db_dump($po_id);

        $wms_id = kernel::single('ome_branch')->getWmsIdById($po['branch_id']);
        $data = array(
            'out_order_code'    =>  $po['out_iso_bn'],
            'stockin_bn'        =>  $po['po_bn'],
        );

        return $this->search($wms_id, $data);
    }
}