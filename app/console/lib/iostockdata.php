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
* 出入库发起通知方法
* 数据组织
*/
class console_iostockdata{
    
   

   /***
    * 组织入库详情和明细
    * @iso_id 出入库单号
    */

    function get_iostockData($iso_id){
        $oIso = app::get('taoguaniostockorder')->model("iso");
        $iostockObj = kernel::single('siso_receipt_iostock');
        $oIsoItems = app::get('taoguaniostockorder')->model("iso_items");
        $Iso = $oIso->dump(array('iso_id'=>$iso_id));
        $oSupplier = app::get('purchase')->model('supplier');
        $supplier = $oSupplier->dump($Iso['supplier_id'],'area,name,zip,addr,telphone,bn,supplier_id');
        
        $branch_detail = $this->getBranchByid($Iso['branch_id']);
        $iso_items = $oIsoItems->getList('product_id,bn,nums as num,product_name as name,price,defective_num,normal_num,iso_items_id,partcode',array('iso_id'=>$iso_id));
        //ASN批次出库取详细数据
        $oIsoItemsDetail = app::get('taoguaniostockorder')->model("iso_items_detail");
        $isoItemsDetail = $oIsoItemsDetail->getList('product_id,bn,nums as num,product_name as name,price,iso_items_id,id,batch_code,product_date,expire_date,sn,box_no,extendpro',array('iso_id'=>$iso_id));
        if($isoItemsDetail){

            // 行项目编号
            foreach ($isoItemsDetail as $key => $value) {
                if (in_array($Iso['bill_type'], ['vopjitrk'])) {
                    $isoItemsDetail[$key]['item_line_num'] = $value['id'];
                }
            }

            $iso_items = $isoItemsDetail;
        }
        $data = array(
            'io_bn' => $Iso['iso_bn'],
            'appropriation_no' => $Iso['appropriation_no'],
            'branch_bn'=> $branch_detail['branch_bn'],
            'branch_id'=> $Iso['branch_id'],
            'branch_type'=>$branch_detail['type'],
            'storage_code'=> $branch_detail['storage_code'],
            'owner_code'=> $branch_detail['owner_code'],
            'create_time'=>$Iso['create_time'],
            'memo'=>$Iso['memo'],
            'type_id'=>$Iso['type_id'],
            'supplier_bn'=>$supplier['bn'],
            'supplier_id'=>$supplier['supplier_id'],
            'extrabranch_id' => $Iso['extrabranch_id'],
            'corp_id'=>$Iso['corp_id'],
            'bill_type'=>$Iso['bill_type'],
            'logi_no' => $Iso['logi_no'], //物流单号
            'original_bn' => $Iso['original_bn'],
            'business_bn'=>$Iso['business_bn'],
        );
        $iostock_type = $iostockObj->getIoByType($Iso['type_id']);
        
        // 查询仓库扩展属性-活动号（不区分出库/入库，只要查询到就组装，便于后续扩展）
        $arr_props = app::get('ome')->model('branch_props')->getPropsByBranchId($Iso['branch_id']);
        foreach ($arr_props as $k => $v) {
            if ($k == 'activity_no' && $v) {
                $data['activity_no'] = $v;
            }
        }
        
        $extrabranch_id = $Iso['extrabranch_id'];
        if (in_array($Iso['type_id'],array('4','40'))) {
            $extrabranch_id = $Iso['branch_id'];
        }

        // 如果填了联系人
        if ($Iso['extra_ship_name'] && $Iso['extra_ship_mobile'] && $Iso['extra_ship_addr']) {
            list(,$area) = explode(':', $Iso['extra_ship_area']);
            list($province, $city, $district) = explode('/', $area);


            if ($iostock_type=='0'){#出库
                $data['receiver_name']      = $Iso['extra_ship_name'];
                $data['receiver_zip']       = $Iso['extra_ship_zip'];
                $data['receiver_state']     = $province;
                $data['receiver_city']      = $city;
                $data['receiver_district']  = $district;
                $data['receiver_address']   = $Iso['extra_ship_addr'];
                $data['receiver_phone']     = $Iso['extra_ship_tel'] ? $Iso['extra_ship_tel'] : $Iso['extra_ship_mobile'];
                $data['receiver_mobile']    = $Iso['extra_ship_mobile'];
                $data['receiver_email']     = $Iso['extra_ship_email'];
            }else{#入库
                $data['shipper_name']      = $Iso['extra_ship_name'];
                $data['shipper_zip']       = $Iso['extra_ship_zip'];
                $data['shipper_state']     = $province;
                $data['shipper_city']      = $city;
                $data['shipper_district']  = $district;
                $data['shipper_address']   = $Iso['extra_ship_addr'];
                $data['shipper_phone']     = $Iso['extra_ship_tel'];
                $data['shipper_mobile']    = $Iso['extra_ship_mobile'];
                $data['shipper_email']     = $Iso['extra_ship_email'];
            }

        } elseif ($extrabranch_id){
            $extrabranch_detail = $this->getExtrabranch($extrabranch_id,$iostock_type,$Iso['type_id']);
            $data = array_merge($data,$extrabranch_detail);
        }
        
        //物流公司
        if ($Iso['corp_id']) {
            $corp_id = $Iso['corp_id'];
            $oDly_corp = app::get('ome')->model('dly_corp');
            $dly_corp = $oDly_corp->dump($corp_id,'type');
            $data['logi_code'] = $dly_corp['type'];
        }
        $total_goods_fee = 0;
        foreach($iso_items as $item){
            $total_goods_fee +=$item['price'] * $item['num'];
        }
        $data['total_goods_fee'] = $total_goods_fee;
        $data['items'] = $iso_items;
   
        return $data;
    }

   
   /**
    * 调拔入库
    * 
    */
   function allocate_out($iso_id)
   {
       $basicMaterialLib    = kernel::single('material_basic_material');
       
        $isoObj = app::get('taoguaniostockorder')->model('iso');
        
        $iso = $isoObj->dump($iso_id,'*');
        #取调拔单入的仓库
		$original_id = $iso['original_id'];
        $oAppropriation_items = app::get('taoguanallocate')->model('appropriation_items');
        $appropriaton_items = $oAppropriation_items->getlist('*',array('appropriation_id'=>$original_id),0,1);
        $to_branch_id = $appropriaton_items[0]['to_branch_id'];
        $from_branch_id = $appropriaton_items[0]['from_branch_id'];

        $extrabranch = [];
        if ($from_branch_id) {
            $extrabranch = app::get('ome')->model('branch')->db_dump([
                'branch_id' => $from_branch_id,
                'check_permission' => false,
            ], 'branch_bn');
        }


        $iso_itemsObj = app::get('taoguaniostockorder')->model('iso_items');
        $iso_items = $iso_itemsObj->getlist('*',array('iso_id'=>$iso_id),0,-1);
        #生成调拔出库单根据出入库单
        #组织明细
        $items = array();
        
        foreach($iso_items as $iso_item)
        {
            if ($iso_item['normal_num']>0){
                $Products    = $basicMaterialLib->getBasicMaterialStock($iso_item['product_id']);
            
                $items[$iso_item['product_id']] = array(
                    'product_id' => $iso_item['product_id'],
                    'product_bn' => $iso_item['bn'],
                    'name' => $Products['material_name'],
                    'bn' => $iso_item['bn'],
                    'unit' => $iso_item['unit'],
                    'store' => $Products['store'],
                    'price' => $iso_item['price'],
                    'nums' => $iso_item['normal_num'],
                  );
            }
            
        }
        $op_name = kernel::single('desktop_user')->get_name();
        $iostock_instance = kernel::single('console_iostockorder');
        $shift_data = array (
                'iostockorder_name' => date('Ymd').'入库单',
                'supplier_id' => $iso['supplier_id'],
                'branch' => $to_branch_id,
                'extrabranch_id'=>$from_branch_id,
                'extrabranch_bn'=>$extrabranch['branch_bn'],
                'type_id' => 4,//调拔入库
                'iso_price' => 0,
                'memo' => $iso['memo'],
                'operator' => $op_name,
                'products' => $items,
                'original_bn' => $iso['iso_bn'],
                'original_id' => $iso['iso_id'],
                'appropriation_no' => $iso['appropriation_no'],
       			'confirm' => 'N',
                'business_bn'      => $iso['business_bn'] ? $iso['business_bn'] : $iso['appropriation_no'], 
                'bill_type'        => $iso['bill_type'], 
                'logi_no'          => $iso['logi_no'], 
        );
        
        if(in_array($iso['bill_type'],array('o2oprepayed'))){
            $shift_data['confirm'] = 'Y';
        }
        $result = $iostock_instance->save_iostockorder($shift_data,$msg);
//        if($result){
//            $this->notify_otherstock('0',$result,'create');
//            
//        }
        return $result;
   }
    /***
    * 获取仓库对应售后仓
    * @access public
    * @param Array $branch_id 仓库ID
    */
   function getDamagedbranch($branch_id){
       $oBranch = app::get('ome')->model('branch');
       $branch = $oBranch->db->selectrow("select branch_id,branch_bn FROM sdb_ome_branch WHERE branch_id=".$branch_id." AND type='damaged'");
       if ($branch) {
            return $branch;
       }else{
            $branch_damaged = $oBranch->db->selectrow("select branch_id,branch_bn FROM sdb_ome_branch WHERE parent_id=".$branch_id." AND type='damaged'");
            return $branch_damaged;
       }
       
       


   }

    /**
     * 根据仓库编号返回仓库名称
     * 
     */
    function getBranchBybn($branch_bn){
        $oBranch = app::get('ome')->model('branch');
        $branch = $oBranch->db->selectrow("select branch_id,wms_id,name,type FROM sdb_ome_branch WHERE branch_bn='$branch_bn'");
        return $branch;
    }
    /**
     * 获取仓库详情
     * @access public
     * @param  $branch_id 仓库ID
     * 
     * @return Array 
     */
    function getBranchByid($branch_id){
        $oBranch = app::get('ome')->model('branch');
        $branch_damaged = $oBranch->getlist('type,branch_id,branch_bn,storage_code,owner_code,wms_id',array('branch_id' => $branch_id),0,1);
        $branch_damaged = $branch_damaged[0];
       return $branch_damaged;

    }

    /**
     * 获取仓库详情
     * @access public
     * @param  $branch_id 仓库ID
     * 
     * @return Array 
     */
    function getBranchByStorageCode($storage_code){
        $oBranch = app::get('ome')->model('branch');
        $branchInfo = $oBranch->dump(array('storage_code' => $storage_code),'type,branch_id,branch_bn,storage_code');
       return $branchInfo ? $branchInfo : '';

    }

    /**
     * 发起转储单创建
     * @access public
     * 
     * @return 
     */
    function notify_stockdump($stockdump_id,$method){
        
        ini_set('memory_limit','512M');
        $OStockdump = app::get('console')->model('stockdump');
        $Oitems = app::get('console')->model('stockdump_items');
        $stockdump = $OStockdump->dump($stockdump_id,'stockdump_bn,create_time,memo,from_branch_id,to_branch_id');
        $items = $Oitems->getlist('bn,product_name as name,num,appro_price as price',array('stockdump_id'=>$stockdump_id),0,-1);
        $from_branch_id = $stockdump['from_branch_id'];
        $to_branch_id = $stockdump['to_branch_id'];
        $from_branch = $this->getBranchByid($from_branch_id);
        $to_branch = $this->getBranchByid($to_branch_id);
        
        $branch_id = $stockdump['from_branch_id'];
        $wms_id = kernel::single('ome_branch')->getWmsIdById($branch_id);

        if ($method == 'create'){
            $method = 'create';
            $data = $stockdump;
            $data['src_storage'] = $from_branch['storage_code'];
            $data['owner_code'] = $from_branch['owner_code'];
            $data['dest_storage'] = $to_branch['storage_code'];
            $data['items'] = $items;

            // 如果是同个仓，自动转储完成
            if ($from_branch['wms_id'] == $to_branch['wms_id']) {
                $branchRelMdl = app::get('wmsmgr')->model('branch_relation');

                $wms_from_branch = $branchRelMdl->db_dump(array('wms_id' => $from_branch['wms_id'], 'sys_branch_bn' => $from_branch['branch_bn']));
                $wms_to_branch   = $branchRelMdl->db_dump(array('wms_id' => $to_branch['wms_id'], 'sys_branch_bn' => $to_branch['branch_bn']));

                if ($wms_from_branch['wms_branch_bn'] && $wms_to_branch['wms_branch_bn']) {
                    $autoFinish = app::get('ome')->getConf('stockdump.auto.finish');
                    if ((!isset($autoFinish) || ($autoFinish && $autoFinish=='true')) && $wms_from_branch['wms_branch_bn'] == $wms_to_branch['wms_branch_bn']) {
                        $data['status'] = 'FINISH';
                        $data['memo']   = '同WMS转储自动完成';
                        return kernel::single('console_receipt_stockdump')->do_save($stockdump['stockdump_bn'], $data);
                    }
                }
            }
        }else if($method == 'cancel'){
            $method = 'updateStatus';
            $data = array(
                'stockdump_bn'=>$stockdump['stockdump_bn'],    
            );
        }else{
            echo '无此方法';
            exit;
        }
        $result = kernel::single('console_event_trigger_stockdump')->$method($wms_id, $data, true);
     
        return $result;
    }

    /*
     * 获取操作员管辖仓库
     * 
     */

    function getBranchByUser($dataType=null,$type='online') {
        $oBops = app::get('ome')->model('branch_ops');
        $Obranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        $branch_list = array();
        if (!$is_super){
            $opInfo = kernel::single('ome_func')->getDesktopUser();
            $op_id = $opInfo['op_id'];

            if($type=='online'){
                $filter = array('op_id' => $op_id,'b_type'=>1);
            }elseif($type=='offline'){
                $filter = array('op_id' => $op_id,'b_type'=>2);
            }else{
                $filter = array('op_id' => $op_id);
            }

            $bops_list = $oBops->getList('branch_id', $filter, 0, -1);
            if ($bops_list)
                foreach ($bops_list as $k => $v) {
                    $bps[] = $v['branch_id'];
                }
                if ($bps){
                    $branch_list = $Obranch->getList('branch_id,name,uname,phone,mobile', array('type'=>'main','branch_id' => $bps), 0, -1);
                }
        }else{
            $branch_list = $Obranch->getList('branch_id,name,uname,phone,mobile', array('type'=>'main','b_type'=>1), 0, -1);
        }
        
        
        if ($branch_list)
            ksort($branch_list);
        return $branch_list;
    }

 

    /**
     * 获取外部仓库信息
     */
    function getExtrabranch($extrabranch_id,$iostock_type,$type_id=0){
        if (in_array($type_id,array('4','40'))) {
            $oExtrabranch = app::get('ome')->model('branch');
        }else{
            $oExtrabranch = app::get('ome')->model('extrabranch');
        }
        $extrabranch = $oExtrabranch->dump($extrabranch_id,'*');
        $area = $extrabranch['area'];
        $area = explode(':',$area);
        $area = explode('/',$area[1]);
        $extrabranch_detail = array();
        if ($iostock_type=='0'){#出库
                $extrabranch_detail = array(
                    'receiver_name'=>$extrabranch['uname'],
                    'receiver_zip'=>$extrabranch['zip'],
                    'receiver_state'=>$area[0],
                    'receiver_city'=>$area[1],
                    'receiver_district'=>$area[2],
                    'receiver_address'=>$extrabranch['address'],
                    'receiver_phone'=>$extrabranch['phone'],
                    'receiver_mobile'=>$extrabranch['mobile'],
                    'receiver_email'=>$extrabranch['email'],
                );
            }else{#入库
                $extrabranch_detail = array(
                    'shipper_name'=>$extrabranch['uname'],
                    'shipper_zip'=>$extrabranch['zip'],
                    'shipper_state'=>$area[0],
                    'shipper_city'=>$area[1],
                    'shipper_district'=>$area[2],
                    'shipper_address'=>$extrabranch['address'],
                    'shipper_phone'=>$extrabranch['phone'],
                    'shipper_mobile'=>$extrabranch['mobile'],
                    'shipper_email'=>$extrabranch['email'],
                );
            }
        return $extrabranch_detail;
    }
    
    //获取转仓数据
    function get_warehouse_iostockData($iso_id){
        $oIso = app::get('warehouse')->model("iso");
        $iostockObj = kernel::single('siso_receipt_iostock');
        $oIsoItems = app::get('warehouse')->model("iso_items");
        $oSupplier = app::get('purchase')->model('supplier');
        
        $Iso = $oIso->dump(array('iso_id'=>$iso_id),'iso_bn,branch_id,type_id,create_time,memo,extrabranch_id,supplier_id');
        $supplier = $oSupplier->dump($Iso['supplier_id'],'area,name,zip,addr,telphone,bn');
        $branch_detail = $this->getBranchByid($Iso['branch_id']);
        $iso_items = $oIsoItems->getList('product_id,bn,nums as num,product_name as name,price,defective_num,normal_num,iso_items_id,po_name,dly_note_number,box_number',array('iso_id'=>$iso_id));
        
        $data = array(
            'io_bn' => $Iso['iso_bn'],
            'branch_bn'=> $branch_detail['branch_bn'],
            'branch_id'=> $Iso['branch_id'],
            'branch_type'=>$branch_detail['type'],
            'storage_code'=> $branch_detail['storage_code'],
            'create_time'=>$Iso['create_time'],
            'memo'=>$Iso['memo'],
            'type_id'=>$Iso['type_id'],
            'supplier_bn'=>$supplier['bn'],
            'extrabranch_id' => $Iso['extrabranch_id'],
        );
        $iostock_type = $iostockObj->getIoByType($Iso['type_id']);
        $extrabranch_id = $Iso['extrabranch_id'];
        if ($extrabranch_id){
            $extrabranch_detail = $this->getExtrabranch($extrabranch_id,$iostock_type,$Iso['type_id']);
            $data = array_merge($data,$extrabranch_detail);
        }
        
        $total_goods_fee = 0;
        foreach($iso_items as $item){
            $total_goods_fee +=$item['price'] * $item['num'];
        }
        $data['total_goods_fee'] = $total_goods_fee;
        $data['items'] = $iso_items;
        
        return $data;
    }
}
