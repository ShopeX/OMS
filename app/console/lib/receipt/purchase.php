<?php
class console_receipt_purchase extends console_receipt_common{

    private static $eo_status = array(
        'PARTIN'=>'2',
        'FINISH'=>'3',

    );

    private $_po= array();
    private $_items = array();
    function __construct() {
        $this->poObj = app::get('purchase')->model('po');
        $this->itemsObj = app::get('purchase')->model('po_items');

        $this->_branproductObj = kernel::single('ome_branch_product');

    }

    /**
     *
     * 采购单更新方法
     * @param array $data 采购单数据信息
     *
     */
    public function update(&$data,&$msg){

        $Po = $this->_po;
        $po_id = $Po['po_id'];
        $items = $data['items'];
        $iostock_update = true;
        $io_status = $data['io_status'];
        $iostock_data = array(
            'memo'=>$data['memo'],
            'operate_time'=>$data['operate_time'],
            'arrival_no'=>$data['arrival_no'],

        );
        kernel::database()->beginTransaction();

        $eo_status = self::$eo_status[$io_status];
        #检查货品否都存在
        $auto_iostock = false;
        if ($items){
            if(!$this->checkBnexist($po_id,$items)){
                $msg = '有货品不存在!';
                kernel::database()->rollBack();
                return false;
            }
            $iostock_data['items'] = $this->__format_items($items,$iostock_update,$auto_iostock);

        }

        if($eo_status=='3' && $Po['eo_status']!='2' && $auto_iostock==true){
            $this->poObj->update(array('receive_status'=>console_const::_FINISH_CODE),array('po_id'=>$po_id));
            $msg = '入库数量大于申请数量,请在列表里操作!';
            kernel::database()->commit();
            return true;
        }
        if (count($iostock_data['items'])>0){
            if(!$this->save_eo($po_id,'normal',$iostock_data)){

                $msg = '入库单保存失败';
                kernel::database()->rollBack();

                return false;
            }
        }
        $wsiMdl = app::get('console')->model('wms_stockin');
        $wsiRow = $wsiMdl->db_dump(['stockin_bn'=>$Po['po_bn'], 'iso_status'=>'1'], 'id');
        if($wsiRow) {
            $wsiRs = $wsiMdl->update(['iso_id'=>$po_id, 'iso_status'=>'2'], ['id'=>$wsiRow['id'], 'iso_status'=>'1']);
            if(!is_bool($wsiRs)) {
                app::get('ome')->model('operation_log')->write_log('wms_stockin@console',$wsiRow['id'], '入库完成');
            }
        }
        $eo_status = self::$eo_status[$io_status];
        $po_update_data = array('eo_status'=>$eo_status);
        if ($eo_status == '3'){#更新为入库完成
            $po_update_data['po_status'] = '4';
        }
        #备注处理
        if ($data['memo']){
            $po_update_data['memo'] = $this->format_memo($data['memo']);
        }

        if (!$iostock_update){
            $po_update_data['defective_status'] = '1';#未确认
        }

        if(!$this->poObj->update($po_update_data,array('po_id'=>$po_id))){
            $msg = '采购单更新状态失败';

            kernel::database()->rollBack();

            return false;

        }
        kernel::database()->commit();
        $this->cleanArriveStore($iostock_data['items'],$io_status);

        if (app::get('machinecenter')->is_installed()) {
            $this->machineCenterPurchaseStockAdd($Po, $iostock_data);
        }

        return true;
    }

    private function __format_items($items,&$iostock_update,&$auto_iostock){
        $iostock_items = array();
        $iostock_autoConf = app::get('ome')->getConf('ome.iostock.auto_finish');
        foreach($items as $item){
            $po_item         = $this->_items[$item['bn']];
            $products_detail = $this->getProducts($item['bn']);
            $defective_num   = $item['defective_num']+$po_item['defective_num'];
            $in_num          = $item['normal_num']+$po_item['in_num'];
            $effective_num   = $po_item['num']-$po_item['in_num']-$po_item['out_num'];
            $status          = 1;
            if($po_item['num']>$in_num+$po_item['out_num']){
                $status = 2;
            }else if($po_item['num']==$po_item['in_num']+$po_item['out_num']){
                $status=3;
            }
            #更新采购单明细数量和状态
            $item_data = array(
                'defective_num'=>$defective_num,
                'in_num'=>$in_num,
                'status'=>$status,
            );

            $new_sn_array = [];
            if ($item['sn_list']) {
                $old_sn_list = $po_item['sn_list'] ? json_decode($po_item['sn_list'], 1) : [];
                $old_sn_array = [];
                if (is_array($old_sn_list) && isset($old_sn_list['sn'])) {
                    $old_sn_array = is_array($old_sn_list['sn']) ? $old_sn_list['sn'] : [$old_sn_list['sn']];
                } elseif (is_array($old_sn_list) && !isset($old_sn_list['sn'])) {
                    $old_sn_array = $old_sn_list;
                }
                if (is_array($item['sn_list']) && isset($item['sn_list']['sn'])) {
                    $new_sn_array = is_array($item['sn_list']['sn']) ? $item['sn_list']['sn'] : [$item['sn_list']['sn']];
                } elseif (is_array($item['sn_list']) && !isset($item['sn_list']['sn'])) {
                    $new_sn_array = $item['sn_list'];
                }
                $merged_sn_array = array_merge($old_sn_array, $new_sn_array);
                $merged_sn_array = array_filter($merged_sn_array);
                $item_data['sn'] = !empty($merged_sn_array) ? implode(',', $merged_sn_array) : '';
                $new_sn_array = array_filter($new_sn_array);
            }
            if($item['batch']) {
                $useLogModel = app::get('console')->model('useful_life_log');
                $useful = [];
                foreach ($item['batch'] as $bv) {
                    $tmpUseful = [];
                    $tmpUseful['product_id'] = $products_detail['product_id'];
                    $tmpUseful['bn'] = $item['bn'];
                    $tmpUseful['original_bn'] = $this->_po['po_bn'];
                    $tmpUseful['original_id'] = $this->_po['po_id'];
                    $tmpUseful['sourcetb'] = 'po';
                    $tmpUseful['business_bn'] = $this->_po['po_bn'];
                    $tmpUseful['bill_type'] = 'po';
                    $tmpUseful['create_time'] = time();
                    $tmpUseful['stock_status'] = '0';
                    $tmpUseful['num'] = $bv['num'];
                    $tmpUseful['normal_defective'] = $bv['normal_defective'];
                    $tmpUseful['product_time'] = $bv['product_time'];
                    $tmpUseful['expire_time'] = $bv['expire_time'];
                    $tmpUseful['purchase_code'] = $bv['purchase_code'];
                    $tmpUseful['produce_code'] = $bv['produce_code'];
                    $useful[] = $tmpUseful;
                }
             
                $useLogModel->db->exec(ome_func::get_insert_sql($useLogModel, $useful));
            }

            $this->itemsObj->update($item_data,array('item_id'=>$po_item['item_id']));
            if($iostock_autoConf =='true'){
                if(($in_num+$defective_num)>$po_item['num']){$auto_iostock = true;}
            }
            if ($item['normal_num']>0){
                $return_sn_list = '';
                if (!empty($new_sn_array)) {
                    $return_sn_list = json_encode($new_sn_array, JSON_UNESCAPED_UNICODE);
                } elseif (isset($item['sn_list'])) {
                    $return_sn_list = is_string($item['sn_list']) ? $item['sn_list'] : json_encode($item['sn_list'], JSON_UNESCAPED_UNICODE);
                }

                $iostock_items[] = array(
                     'name'        => $products_detail['name'],
                     'bn'          => $item['bn'],
                     'price'       => $po_item['price'],
                    'purchase_num' => $po_item['num'],
                    'nums'         => $item['normal_num'],
                    'is_new'       => $po_item['is_new'],
                    'memo'         => $data['memo'],
                    'product_id'   => $products_detail['product_id'],
                    'goods_id'     => $products_detail['goods_id'],
                    'unit'         => $products_detail['unit'],
                    'item_id'      => $po_item['item_id'],
                    'effective_num'=> $effective_num,
                    'sn_list'      => $return_sn_list,
                );
            }
            if($defective_num>0){#需要残损确认
                $iostock_update = false;
            }

        }
        return $iostock_items;
    }

    private function format_memo($memo){
        if ($memo){#有备注更新

            $oldmemo = $this->_po['memo'];
            if (!$oldmemo){
                $oldmemo= unserialize($oldmemo);
            }
            $memo = serialize($oldmemo.$oldmemo);
            return $memo;
        }
    }

    /**
     *
     * 采购单取消
     * @param array $po_bn 采购单编号
     */
    public function cancel($po_bn){

        $po = $this->_po;
        $po_data = array('po_status'=>'2','eo_status'=>'4');
        $result = $this->poObj->update($po_data,array('po_id'=>$po['po_id']));
        if ($result){
            $items = $this->_items;
            $this->cleanArriveStore($items);
        }
        return $result;

    }

    /**
     *
     * 检查需要入库的货号是否存在于采购单中
     * @param array
     */
    public function checkBnexist($po_id,$items){

        $bn_array = array();
        foreach($items as $item){
            $bn_array[]=$item['bn'];
        }
        $bn_total = count($bn_array);

        $bn_array = '\''.implode('\',\'',$bn_array).'\'';

        $po_items = $this->poObj->db->selectrow('SELECT count(item_id) as count FROM sdb_purchase_po_items WHERE po_id='.$po_id.' AND bn in ('.$bn_array.')');

        if ($bn_total!=$po_items['count']){#比较数目是否相等
            return false;
        }
        return true;
    }

    /**
     *
     * 检查采购单是否存在判断
     * @param array $po_bn 采购单编号
     */
    public function checkExist($po_bn){
        $oPo = app::get('purchase')->model("po");
        $Po = $oPo->dump(array('po_bn'=>$po_bn),'po_id,po_bn,po_status,branch_id');
        $this->_po = $Po;
        $items = $this->itemsObj->getlist('*',array('po_id'=>$Po['po_id']));
        foreach( $items as $item){
            $bn = trim($item['bn']);
            $this->_items[$bn] = $item;
        }

        return $Po;
    }

    /**
     *
     * 检查采购单是否有效
     * @param  $po_bn 采购单编号
     * @param  $status 根据传入状态判断对应状态是否可以操作
     */
    public function checkValid($po_bn,$status,&$msg){
        $po = $this->checkExist($po_bn);
        if(!$po){
            $msg = '采购单不存在!';
            return false;
        }
        $po_status = $po['po_status'];
        switch($status){
            case 'PARTIN':
            case 'FINISH':
                if ($po_status=='2'){
                    $msg = '采购已取消不可以入库';
                    return false;
                }
                if ($po_status=='4'){
                    $msg = '已入库不可以再入库';
                    return false;
                }
                break;
            case 'CANCEL':
            case 'CLOSE':
                if ($po_status=='4'){
                    $msg = '采购已完成不可以取消';
                    return false;
                }
                break;
        }
        return true;
    }

    /**
    * 执行采购入库
    * $po_id 采购单号
    * type normal正常出入库 否则残损入库
    * #为供应商与商品建立关联
    */
    function save_eo($po_id,$type,$iostock_data){

        $iostockObj = kernel::single('console_iostockdata');
        $iostock_instance = kernel::single('console_iostockorder');
        $Po = $this->poObj->dump($po_id,'*');
        $branch_id = $Po['branch_id'];

        if ($type == 'normal'){//采购
            $type_id = 1;

        }else{//残损
            $type_id = 50;
            $damagedbranch = $iostockObj->getDamagedbranch( $Po['branch_id'] );
            $branch_id = $damagedbranch['branch_id'];
        }

        $amount = 0;
        $shift_data = array();

        foreach($iostock_data['items'] as $item){
            $shift_data[$item['product_id']] = $item;
            $amount+=$item['price']*$item['nums'];
        }
        $eo_data = array (
            'iostockorder_name' => date('Ymd').'入库单',
            'supplier'          => $supplier['name'],
            'supplier_id'       => $Po['supplier_id'],
             'supplier'         => $this->getSupplier($Po['supplier_id']),
            'branch'            => $branch_id,
            'iso_price'         => $Po['delivery_cost'],
            'memo'              => $iostock_data['memo'],
            'operate_time'      => $iostock_data['operate_time'],
            'operator'          => $data['operator'],
            'products'          => $shift_data,
            'original_bn'       => $Po['po_bn'],
            'original_id'       => $po_id,
            'confirm'           => 'Y',
            'type_id'           => $type_id,
            'po_type'           => $Po['po_type'],
            'orig_type_id'      =>  '1',
            'arrival_no' => $iostock_data['arrival_no'],
        );


        $eo_data['eo_id'] = $iostock_instance->save_iostockorder($eo_data, $msg);

        $eo_data['eo_bn'] = $iostock_instance->getIoStockOrderBn();

        if ($eo_data['eo_id']){
            $eorder_data = array(
                'eo_id'       => $eo_data['eo_id'],
                'supplier_id' => $Po['supplier_id'],
                'eo_bn'       => $eo_data['eo_bn'],
                'po_id'       => $Po['po_id'],
                'amount'      => $amount,
                'entry_time'  => time(),
                'operator'    => $Po['operator'],
                'branch_id'   => $branch_id,
            );
            app::get('purchase')->model("eo")->save($eorder_data);
            if ($type_id == '1'){
                foreach($iostock_data['items'] as $k2=>$v2){
                    $v2['supplier_id']  = $Po['supplier_id'];
                    $v2['eo_id']        = $eo_data['eo_id'];
                    $v2['eo_bn']        = $eo_data['eo_bn'];
                    $v2['purchase_time']= time();
                    $v2['in_num']       = $v2['nums'];
                    $v2['branch_id']       = $branch_id;
                    app::get('purchase')->model("branch_product_batch")->save($v2);
                }
            }
            return true;
        }else{
            return false;
        }
    }

    /**
    * 扣减在途库存
    *
    */
    function cleanArriveStore($items,$io_status=''){
        $po = $this->_po;
        $_items = $this->_items;

        $productIds = array();

        $changeItems = [];
        foreach ((array)$items as $item) {

            $effective_num = isset($item['effective_num']) ? $item['effective_num'] : ($item['num']-$item['in_num']-$item['out_num']);
            $num = 0;
            if ($io_status == 'FINISH' || $io_status == ''){
                $num = $effective_num;
            }else{
                $num = $effective_num>0 ? $item['nums'] : $effective_num;
            }

            if($num>0){
                $changeItems[] = ['product_id'=>$item['product_id'], 'num'=>$num];
            }

        }
        if($changeItems) {
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id' => $po['branch_id']));
            $params                    = array();
            $params['node_type']       = 'changeArriveStore';
            $params['params']          = array(
                'obj_id' => $po['po_id'], 
                'branch_id' => $po['branch_id'], 
                'obj_type' => 'purchase',
                'operator' => '-'
            );
            $params['params']['items'] = $changeItems;
            $storeManageLib->processBranchStore($params, $err_msg);
        }
        //当状态为全部入库时需将未产生过出入库记录释放在途库存
        if ($io_status == 'FINISH'){
            //取消在途
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id' => $po['branch_id']));
            $params                    = array();
            $params['node_type']       = 'deleteArriveStore';
            $params['params']          = array(
                'obj_id' => $po['po_id'], 
                'branch_id' => $po['branch_id'], 
                'obj_type' => 'purchase',
            );
            $storeManageLib->processBranchStore($params, $err_msg);
        }

    }

    public function machineCenterPurchaseStockAdd($poInfo, $iostockData)
    {
        $status = '1';
        $original_id = $poInfo['po_id'];
        $original_bn = $poInfo['po_bn'];
        $branch_id   = $poInfo['branch_id'];
        $branchInfo  = app::get('ome')->model('branch')->db_dump($branch_id, 'branch_bn');
        $items       = $iostockData['items'];
        $snParams    = $this->purchaseSnParams();

        foreach ($items as $item) {
            $snList = json_decode($item['sn_list']);
            if ($snList) {
                foreach ($snList as $serial) {
                    if (!$serial) {
                        continue;
                    }
                    $bn = $item['bn'];
                    $bm_id = $item['product_id'];
                    $barcode = material_codebase::getBarcodeBybn($bn) ?: '';

                    $tmpSnParams = [];
                    $tmpSnParams['sn']                = $serial;
                    $tmpSnParams['bm_id']             = $bm_id;
                    $tmpSnParams['material_bn']       = $bn;
                    $tmpSnParams['barcode']           = $barcode;
                    $tmpSnParams['imei_number']       = '';
                    $tmpSnParams['order_bn']          = '';
                    $tmpSnParams['branch_id']         = $branch_id;
                    $tmpSnParams['branch_bn']         = $branchInfo['branch_bn'];
                    $tmpSnParams['original_id']       = $original_id;
                    $tmpSnParams['original_bn']       = $original_bn;
                    $tmpSnParams['bill_bn']           = $original_bn;
                    $tmpSnParams['original_type']     = 'purchase';
                    $tmpSnParams['status']            = $status;
                    $tmpSnParams['io_status']         = $status;
                    $tmpSnParams['io_time']           = time();
                    $tmpSnParams['platform_order_bn'] = '';

                    kernel::single('machinecenter_event_receive_sn')->add(array_merge($snParams, $tmpSnParams));
                }
            }
        }
    }

    public function purchaseSnParams()
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
