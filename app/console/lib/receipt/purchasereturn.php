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

class console_receipt_purchasereturn extends console_receipt_common{

    function __construct() {
        $this->rpObj = app::get('purchase')->model('returned_purchase');
        $this->rp_itemObj = app::get('purchase')->model('returned_purchase_items');
        
    }
    private $_returnpurchase = array();

    private static $return_status = array(
        'PARTIN'=>'4',
        'FINISH'=>'2',
        'CANCEL'=>'3',
        'CLOSE'=>'3',
        'FAILED'=>'3'
        
    );

    /**
     * 
     * 采购退货单更新方法
     * @param array $data 采购退货单数据信息
     * @msg 错误信息
     */
    public function update($data,&$msg){
        $purchasereturn = $this->_returnpurchase;
        $io_status = $data['io_status'];
        $rp_id = $purchasereturn['rp_id'];
        $iostock_update = true;
        $items = $data['items'];#货品明细
        
        $out_stock_data = array(
            'supplier_id'       => $purchasereturn['supplier_id'],
            'branch_id'         => $purchasereturn['branch_id'],
            'rp_bn'             => $purchasereturn['rp_bn'],
            'rp_id'             => $rp_id,
            'memo'              => $data['memo'],
            'operate_time'      => $data['operate_time'],
            'operator'          => $purchasereturn['operator'],
            'po_bn'             => $purchasereturn['po_bn'],
        );
        if ($items){
            #检查明细
            
            if (!$this->checkBnexist($rp_id,$items)){
                $msg = '不存在的货号';
                return false;
            }
            #检测库存是否不足
            if (!$this->checkStore($purchasereturn['branch_id'],$items,$msg)) {
                return false;
            }
            
        }
        kernel::database()->beginTransaction();

        if($items) {
            $out_stock_data['items'] = $this->_format_items($items);
        }
        if (count($out_stock_data['items'])>0){#有明细时才会执行退货
            if (!$this->_save_iostockorder($out_stock_data)){

                $msg = "保存采购退货出库结果失败";
                kernel::database()->rollBack();

                return false;
            }
        }
        
        $wsoMdl = app::get('console')->model('wms_stockout');
        $wsoRow = $wsoMdl->db_dump(['stockout_bn'=>$purchasereturn['rp_bn'], 'iso_status'=>'1'], 'id');
        if($wsoRow) {
            $wsoRs = $wsoMdl->update(['iso_id'=>$purchasereturn['rp_id'], 'iso_status'=>'2'], ['id'=>$wsoRow['id'], 'iso_status'=>'1']);
            if(!is_bool($wsoRs)) {
                app::get('ome')->model('operation_log')->write_log('wms_stockout@console',$wsoRow['id'], '出库完成');
            }
        }
        $po_data = array('return_status'=>self::$return_status[$io_status]);
        #备注处理
        if ($data['memo']){#有备注更新
            $po_data['memo'] = $this->format_memo($purchasereturn['memo'],$data['memo']);
        }
        
        $result = $this->rpObj->update($po_data,array('rp_id'=>$rp_id));
        if (!$result){

            $msg = '采购退货单状态更新失败';
            kernel::database()->rollBack();

            return false;
        }
        kernel::database()->commit();
        $this->clear_stockout_store_freeze($out_stock_data,$io_status);
        
        // 机器中心采购退货出库添加
        if (app::get('machinecenter')->is_installed()) {
            $this->machineCenterPurchaseReturnStockAdd($purchasereturn, $out_stock_data);
        }
        
        return true;
    }

    private function _format_items($items){
        $purchasereturn = $this->_returnpurchase;
        $iostock_items = array();
        foreach($items as $item){
            $return_item   = $purchasereturn['items'][$item['bn']];
            $effective_num = $return_item['num']-$return_item['out_num'];
            $out_num       = $item['num'] + intval($return_item['out_num']);
            $item_data     = array('out_num'=>$out_num);
            
            // 处理 sn_list - 参照 console_receipt_stock
            if($item['sn_list']) {
                $sn_list = $return_item['sn_list'] ? json_decode($return_item['sn_list'], 1) : [];
                $item_data['sn_list'] = json_encode(array_merge($sn_list, $item['sn_list']), JSON_UNESCAPED_UNICODE);
            }

            $this->rp_itemObj->update($item_data,array('item_id'=>$return_item['item_id']));
            $products = $this->getProducts($item['bn']);
            $iostock_items[] = array(
                'bn'           => $item['bn'],
                'product_id'   => $return_item['product_id'],
                'nums'         => $item['num'],
                'price'        => $return_item['price'],
                'item_id'      => $return_item['item_id'],
                'effective_num'=> $effective_num,
                'name'         => $products['name'],
                'unit'         => $products['unit'],
                'sn_list'      => $item_data['sn_list'] ?? '',
            );
            if($item['batch']) {
                $useLogModel = app::get('console')->model('useful_life_log');
                $useful = [];
                foreach ($item['batch'] as $bv) {
                    if(empty($bv['purchase_code']) && empty($bv['produce_code'])) {
                        continue;
                    }
                    $tmpUseful = [];
                    $tmpUseful['product_id'] = $return_item['product_id'];
                    $tmpUseful['bn'] = $item['bn'];
                    $tmpUseful['original_bn'] = $purchasereturn['rp_bn'];
                    $tmpUseful['original_id'] = $purchasereturn['rp_id'];
                    $tmpUseful['business_bn'] = $purchasereturn['rp_bn'];
                    $tmpUseful['bill_type'] = 'returned_purchase';
                    $tmpUseful['sourcetb'] = 'returned_purchase';
                    $tmpUseful['create_time'] = time();
                    $tmpUseful['stock_status'] = '0';
                    $tmpUseful['num'] = $bv['num'];
                    $tmpUseful['normal_defective'] = $bv['normal_defective'];
                    $bv['product_time'] && $tmpUseful['product_time'] = $bv['product_time'];
                    $bv['expire_time'] && $tmpUseful['expire_time'] = $bv['expire_time'];
                    $tmpUseful['purchase_code'] = $bv['purchase_code'];
                    $tmpUseful['produce_code'] = $bv['produce_code'];
                    $useful[] = $tmpUseful;
                }
                if($useful) {
                    $useLogModel->db->exec(ome_func::get_insert_sql($useLogModel, $useful));
                }
            }
        }
        return $iostock_items;
    }
    /**
     * 
     * 采购退款单取消
     * @param array $po_bn 采购退款单编号
     */
    public function cancel($data){
       
        $rp_bn = $data['io_bn'];
        $purchasereturnInfo = $this->_returnpurchase;
        $po_data = array('return_status'=>'3');
        if ($data['memo']){#有备注更新
            $memo = $this->format_memo($purchasereturn['memo'],$data['memo']);
            $po_data['memo'] = $memo;
        }
        
        $result = $this->rpObj->update($po_data,array('rp_bn'=>$rp_bn));
        
        $this->clear_stockout_store_freeze($purchasereturnInfo, '');
        return true;
      
    }

    /**
     * 
     * 检查采购退款单货号是否存在
     * @param array $rp_id 
     */
    public function checkBnexist($rp_id,$items){
        $rpObj = app::get('purchase')->model('returned_purchase');
        $bn_array = array();
        foreach($items as $item){
            $bn_array[]=$item['bn'];
        }
        $bn_total = count($bn_array);
        
        $bn_array = '\''.implode('\',\'',$bn_array).'\'';
        $rp_items = $rpObj->db->selectrow('SELECT count(item_id) as count FROM sdb_purchase_returned_purchase_items WHERE rp_id='.$rp_id.' AND bn in ('.$bn_array.')');
       
        if ($bn_total!=$rp_items['count']){#比较数目是否相等
            return false;
        }
        return true;
    }

    /**
     * 
     * 检查采购单是否存在判断
     * @param array $rp_bn 采购单编号
     */
    public function checkExist($rp_bn){
        $rpObj = app::get('purchase')->model('returned_purchase');
        $purchasereturn = $this->rpObj->dump(array('rp_bn'=>$rp_bn), '*', array('returned_purchase_items'=>array('*')));
        if ($purchasereturn){
            $returned_purchase_items =$purchasereturn['returned_purchase_items'];
            $items = array();
            foreach ($returned_purchase_items as $k=>$item){
                $item['nums'] = $item['num'];
                $items[$item['bn']] = $item;
            }
            unset($purchasereturn['returned_purchase_items']);
            $purchasereturn['items'] = $items;
            $this->_returnpurchase = $purchasereturn;
            
             
        }
    }

    /**
     * 
     * 检查采购退款单是否有效
     * @param  $rp_bn 采购单编号
     * @param $status 需要执行状态
     * @msg 返回结果
     * 
     */
    public function checkValid($rp_bn,$status,&$msg){
        $this->checkExist($rp_bn);
        if(!$this->_returnpurchase){
            $msg = '采购退货单编号不存在';
            return false;
        }
        $return_status = $this->_returnpurchase['return_status'];
        switch($status){
            case 'PARTIN':
            case 'FINISH':
                if ($return_status=='2' || $return_status=='3' || $return_status=='5'){
                    $msg = '退货单状态为不可以入库';
                    return false;
                }
                break;
            case 'CANCEL':
            case 'CLOSE':
                if ($return_status=='2' || $return_status=='3' || $return_status=='5'){

                    $msg = '退货单状态为不可以取消';
                    return false;
                }
                break;
        }
        return true;
    }

   
   private function _save_iostockorder($data){
        
        $op_name = kernel::single('desktop_user')->get_name();
        $iostock_instance = kernel::single('console_iostockorder');
        $shift_data = array();
        foreach($data['items'] as $item){
            $shift_data[$item['product_id']] = $item;
        }
        $shift_data = array (
                'iostockorder_name' => date('Ymd').'出库单',
                'supplier_id'       => $data['supplier_id'],
                'branch'            => $data['branch_id'],
                'type_id'           => '10',
                'iso_price'         => 0,
                'memo'              => $data['memo'],
                'operate_time'      => $data['operate_time'],
                'operator'          => $op_name,
                'products'          => $shift_data,
                'original_bn'       => $data['rp_bn'],
                'original_id'       => $data['rp_id'],
                'confirm'           => 'Y',
                'supplier'          =>$this->getSupplier($data['supplier_id']),
                'appropriation_no'  => $data['po_bn'],
                'business_bn'       => $data['rp_bn'],
        );

        $result = $iostock_instance->save_iostockorder($shift_data, $msg);
        return $result;
    }
    

    /**
     * 参数校验
     * @param array $params 
     * @param string $msg 
     */
    private function checkParams($params,&$msg){
        return true;
    }

    /**
     * 冻结库存添加与释放
     * 
     */
    public function clear_stockout_store_freeze($data,$io_status=''){
        //$basicMaterialStock    = kernel::single('material_basic_material_stock');
        //$libBranchProduct    = kernel::single('ome_branch_product');
        
        $_items = $this->_returnpurchase['items'];
        $items = $data['items'];
       
        $branch_id = $data['branch_id'];
        $productIds = array();
        
        //库存管控处理
        $storeManageLib    = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id'=>$branch_id));
        
        $params    = array();
        $params['node_type'] = 'finishReturned';
        $params['params']    = array('rp_id'=>$data['rp_id'], 'branch_id'=>$branch_id);
        
        foreach($items as $item){
            if ($item['nums']<=0) continue;
            $effective_num = isset($item['effective_num']) ? $item['effective_num'] : ($item['nums']-$item['out_num']);
            $num = 0;
            if ($io_status == 'FINISH' || $io_status == ''){
                $num = $effective_num;
            }else{
                $num = $effective_num>0 ? $item['nums'] : $effective_num;
            }
            
            $product_id = $item['product_id'];
            $bn = $item['bn'];
            
            if ($num>0){
                //库存管控处理
                $params['params']['product_id'] = $product_id;
                $params['params']['num']        = $num;
                $params['params']['bn']         = $bn;
                $storeManageLib->processBranchStore($params, $err_msg);
            }
        }
        
        //当状态为全部出库时需将未产生过出入库记录释放在途库存
        if ($io_status == 'FINISH'){
            $iostock_list = $this->getIostockList(10,$this->_returnpurchase['rp_id']);
            
            foreach ($_items as $_item){
                $num = 0;
                
                if($_item['out_num']==0 && !in_array($_item['product_id'],$iostock_list)){
                    $num = $_item['nums'];
                    
                }
                
                //处理之前部分出库
                if(($_item['out_num']>0 && $_item['nums']>$_item['out_num']) && in_array($_item['product_id'],$iostock_list) && (in_array($_item['product_id'],$productIds)==false)){

                    $num = $_item['nums']-$_item['out_num'];
                    
                }
               
                if ($num>0){
                    //库存管控处理
                    $params['params']['product_id'] = $_item['product_id'];
                    $params['params']['num']        = $num;
                    $storeManageLib->processBranchStore($params, $err_msg);
                }
            }
        }
        
        //删除预占流水
        if($io_status=='FINISH' || $io_status=='')
        {
            $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
            $basicMStockFreezeLib->delOtherFreeze($data['rp_id'], material_basic_material_stock_freeze::__RETURNED);
        }
    }

     /**
      * 转换备注
      * 
      */
     function format_memo($oldmemo,$newmemo){
        if ($newmemo){#有备注更新
            $memo = array();
            $operator       = kernel::single('desktop_user')->get_name();
            $operator = $operator=='' ? 'system' : $operator;
            if (!$oldmemo){
                $oldmemo= unserialize($oldmemo);
                if ($oldmemo)
                foreach($oldmemo as $k=>$v){
                    $memo[] = $v;
                }
            }
            $memo[]= array('op_name'=>$operator, 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>htmlspecialchars($newmemo));

            $memo = serialize($memo);
            return $memo;
        }
    }

   

    
    /**
     * 检查库存是否不足
     * @param   array    $items
     * @param   int      $branch_id
     * @return  string   $msg
     * @access  public
     */
    public function checkStore($branch_id,$items,&$msg)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        
        $libBranchProduct    = kernel::single('ome_branch_product');
        
        
        $error_msg = array();
        foreach($items as $item){
          
            $bn = $item['bn'];
            $products    = $basicMaterialObj->dump(array('material_bn'=>$bn), 'bm_id, material_bn');
            
            $store = $libBranchProduct->getStoreByBranch($products['bm_id'], $branch_id);
            
            if ($item['num'] > $store) {
                $error_msg[] = $bn.'库存不足';
            }
        }
        if (count($error_msg) > 0 ) {
            $msg = implode(',',$error_msg);
            return false;
        }
        return true;
    } // end func

    /**
     * 机器中心采购退货出库添加 - 参照 console_receipt_stock::machineCenterOtherStockAdd
     * @param array $purchasereturn 采购退货单信息
     * @param array $outStockData 出库数据
     */
    public function machineCenterPurchaseReturnStockAdd($purchasereturn, $outStockData)
    {
        $status = '0'; // 出库
        
        $original_id = $purchasereturn['rp_id'];
        $original_bn = $purchasereturn['rp_bn'];
        $branch_id   = $purchasereturn['branch_id'];
        $branchInfo  = app::get('ome')->model('branch')->db_dump($branch_id, 'branch_bn');
        $items       = $outStockData['items'];
        
        if (empty($items)) {
            return;
        }
        
        // 基础SN参数（空值）
        $snParams = $this->purchaseReturnSnParams();
        
        foreach ($items as $item) {
            $snList = json_decode($item['sn_list']);
            if ($snList && is_array($snList)) {
                foreach ($snList as $serial) {
                    if (!$serial) {
                        continue;
                    }
                    // 出库时记录bn、product_id和barcode
                    $bn = $item['bn'];
                    $bm_id = $item['product_id'];
                    // 获取条码 - 通过码库获取
                    $barcode = material_codebase::getBarcodeBybn($bn) ?: '';
                    
                    $tmpSnParams                      = [];
                    $tmpSnParams['sn']                = $serial;
                    $tmpSnParams['bm_id']             = $bm_id;
                    $tmpSnParams['material_bn']       = $bn;
                    $tmpSnParams['barcode']           = $barcode;
                    $tmpSnParams['imei_number']       = $item['imei_number'] ?? '';
                    $tmpSnParams['order_bn']          = '';
                    $tmpSnParams['branch_id']         = $branch_id;
                    $tmpSnParams['branch_bn']         = $branchInfo['branch_bn'];
                    $tmpSnParams['original_id']       = $original_id;
                    $tmpSnParams['original_bn']       = $original_bn;
                    $tmpSnParams['bill_bn']           = $original_bn;
                    $tmpSnParams['original_type']     = 'returned_purchase';
                    $tmpSnParams['status']            = $status;
                    $tmpSnParams['io_status']         = $status;
                    $tmpSnParams['io_time']           = time();
                    $tmpSnParams['platform_order_bn'] = '';
                    
                    // 机器中心增加数据
                    kernel::single('machinecenter_event_receive_sn')->add(array_merge($snParams, $tmpSnParams));
                }
            }
        }
    }
    
    /**
     * 获取采购退货出库SN参数（空值）
     * @return array
     */
    public function purchaseReturnSnParams()
    {
        return [
            'buyer_open_id'   => '',
            'buyer_nickname'  => '',
            'buyer_name'      => '',
            'buyer_mobile'    => '',
            'buyer_email'     => '',
            'buyer_address'   => '',
            'ship_oaid'       => '',
            'ship_name'       => '',
            'ship_mobile'     => '',
            'ship_email'      => '',
            'ship_address'    => '',
            'ship_province'   => '',
            'ship_city'       => '',
            'ship_district'   => '',
            'ship_town'       => '',
            'ship_village'    => '',
            'user_name'       => '',
            'user_mobile'     => '',
            'user_email'      => '',
            'user_address'    => '',
            'user_province'   => '',
            'user_city'       => '',
            'user_district'   => '',
            'user_town'       => '',
            'user_village'    => '',
            'source'          => 'local',
            'shop_id'         => '',
            'shop_bn'         => '',
        ];
    }
}