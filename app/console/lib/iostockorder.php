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

class console_iostockorder{
    var $iostockorder_bn;

    /**
     * 保存出入库单，并根据类型生成出入库明细
     * @param array $data
     * @param string $msg
     */
    function save_iostockorder($data,&$msg){

         //检查出入库类型id是否合法
         $type = $data['type_id'];
         $iostockorder_createtime = time();
         $iostockorder_bn = $data['io_bn'] ? $data['io_bn'] : $this->get_iostockorder_bn($type);
         $isoObj = app::get('taoguaniostockorder')->model('iso');
         //$iso_id = $this->gen_id();

         $product_cost = 0;
         $iso_items = $iso_items_detail = array();
         $check = isset($data['check']) && $data['check'] == 'Y' ? 'Y' : 'N';
         $confirm = isset($data['confirm']) && $data['confirm'] == 'Y' ? 'Y' : 'N';
         $batch_flag = false;
         foreach($data['products'] as $product_id=>$product){
            $items = array(
                'iso_bn'=>$iostockorder_bn,
                'product_id'=>$product_id,
                'product_name'=>$product['name'],
                'bn'=>$product['bn'],
                'unit'=>$product['unit'],
                'nums'=>$product['nums'],
                'price'=>$product['price'],

            );
            if($product['partcode']) $items['partcode'] = $product['partcode'];
            if ($confirm == 'Y'){
                $items['normal_num'] = $product['nums'];
            }
            if($product['sn_list']) {
                $items['sn_list'] = json_encode($product['sn_list']);
            }
            if($product['batch']) {

                $items['batch'] = $product['batch'];
                $batch_flag = true;
            }
             if (isset($product['items_detail'])) {
                 foreach ($product['items_detail'] as $val) {
                     $detail                  = [
                         'product_id'   => $val['product_id'],
                         'product_name' => $val['name'],
                         'price'        => $val['price'],
                         'bn'           => $val['bn'],
                         'nums'         => $val['nums'],
                         'batch_code'   => $val['batch_code'],
                         'product_date' => $val['product_date'],
                         'expire_date'  => $val['expire_date'],
                         'sn'           => $val['sn'],
                         'original_id'  => $val['original_id'] ?? 0,
                         'box_no'       => $val['box_no'] ?? '',
                         'extendpro'    => $val['extendpro'] ?? '',
                     ];
                     $items['items_detail'][] = $detail;
                 }
             }
            $iso_items[] = $items;
            $product_cost+= $product['nums'] * $product['price'];
        }

        $operator = kernel::single('desktop_user')->get_name();
        $operator = $operator ? $operator : 'system';
        $iostockorder_data = array(
            'confirm'=>$confirm,
            'name' => $data['iostockorder_name'],
            'iso_bn' => $iostockorder_bn,
            'type_id' => $data['type_id'],
            'branch_id' => $data['branch'],
            'extrabranch_id'=>(int)$data['extrabranch_id'],
            'extrabranch_bn'=>(string)$data['extrabranch_bn'],
            'supplier_id' => (int)$data['supplier_id'],
            'supplier_name' => $data['supplier'],
            'iso_price' => $data['iso_price'],
            'cost_tax' => 0,
            'oper' => $data['operator'],
            'create_time' => $iostockorder_createtime,
            'operator' => $operator ,
            'settle_method' => '',
            'settle_status' => '0',
            'settle_operator' => '',
            'settle_time' => null,
            'settle_num' => 0,
            'settlement_bn' => '',
            'settlement_money' => '0',
            'product_cost'=>$product_cost,
            'memo' => $data['memo'],
            'emergency' => $data['emergency'] ? 'true' : 'false',
            'original_bn'=> $data['original_bn'] ? $data['original_bn'] : '',
            'original_id'=> $data['original_id'] ? $data['original_id'] : 0,
            'appropriation_no'=> $data['appropriation_no'],
            'iso_items'=>$iso_items,
            'arrival_no' => $data['arrival_no'],
            'business_bn'=>$data['business_bn'],
            'source'     =>isset($data['source']) ? $data['source'] : 'local',
            'extra_ship_name' => $data['extra_ship_name'],
            'extra_ship_area' => $data['extra_ship_area'],
            'extra_ship_addr' => $data['extra_ship_addr'],
            'extra_ship_zip' => $data['extra_ship_zip'],
            'extra_ship_tel' => $data['extra_ship_tel'],
            'extra_ship_mobile' => $data['extra_ship_mobile'],
            'extra_ship_email' => $data['extra_ship_email'],
            'cost_type' => $data['cost_type'],
            'cost_department' => $data['cost_department'],
        );
        if($data['logi_no']){
            $iostockorder_data['logi_no'] = $data['logi_no'];
        }
        if ($data['bill_type']) {
            $iostockorder_data['bill_type'] = $data['bill_type'];
        }
        if ($data['corp_id']) {
            $iostockorder_data['corp_id'] = $data['corp_id'];
        }
        if ($confirm == 'Y'){#
            $iostockorder_data['check_status'] = '2';#已审核
            $iostockorder_data['iso_status'] = '3';#全部入库
            $iostockorder_data['complete_time'] = time();//出入库完成时间
        }
        $this->iostockorder_bn = $iostockorder_bn;

        if($this->set($iostockorder_data)){

            //
            if($batch_flag){
                $this->processBatch($iostockorder_data);
            }
            if($confirm == 'Y' ){
                $confirm_data = array(
                    'iso_id'=>$iostockorder_data['iso_id'],
                    'memo'=>$data['memo'],
                    'operate_time'=>$data['operate_time']!='' ? strtotime($data['operate_time']) : '',
                    'branch_id'=>$data['branch'],
                    'po_type'=>$data['po_type'],
                    'items'=>$iostockorder_data['iso_items'],
                    'orig_type_id'=>$data['orig_type_id'] ? $data['orig_type_id'] : '0',
                );
                if(in_array($data['type_id'],array('1','10','5','50'))){
                    if ($data['original_bn'] && $data['original_id']){
                        $confirm_data['original_bn'] = $data['original_bn'];
                        $confirm_data['original_id'] = $data['original_id'];
                    }

                }
                if($this->confirm_iostockorder($confirm_data,$type,$msg,$data['extend'])){
                    

                    if($iostockorder_data['appropriation_no']){
                        $process_status = '4';
                        if($data['type_id']=='4' && $confirm == 'Y'){
                            $process_status = '5';
                        }
                        $approMdl       = app::get('taoguanallocate')->model('appropriation');
                        $filter = array('appropriation_no' => $iostockorder_data['appropriation_no']);
                        $approMdl->update(array('process_status' => $process_status, 'delivery_time' => time()), $filter);
                    }
                    return $iostockorder_data['iso_id'];
                }else{
                    return false;
                }
            }elseif($check == 'Y'){
                $io = kernel::single('ome_iostock')->getIoByType($data['type_id']);
                list($rs, $rsData) = $this->doCkeck($iostockorder_data['iso_id'], $io);
                if(!$rs) {
                    $msg = $rsData['msg'];
                    return false;
                }
                return $iostockorder_data['iso_id'];
            }else{
                return $iostockorder_data['iso_id'];
            }
         }else{
            return false;
         }
    }

    public function doCkeck($iso_id, $io) {

        #库存状态判断
        $isoObj = app::get('taoguaniostockorder')->model('iso');
        $iso       = $isoObj->dump($iso_id, 'check_status,branch_id,iso_bn,type_id,bill_type,business_bn');
        $branch_id = $iso['branch_id'];
        if ($iso['check_status'] != '1') {
            return [false, ['msg'=>'此单据已审核!']];
        }
        if($iso['type_id'] == '70' && $iso['bill_type'] == 'oms_reship_diff') {
            if(empty($iso['business_bn'])
                || !app::get('ome')->model('reship')->db_dump(['reship_bn'=>$iso['business_bn'], 'return_type'=>'return', 'is_check'=>['0','1','3']], 'reship_id')) {
                return [false, ['msg'=>'没有找到对应的退货单：'.$iso['business_bn']]];
            }
        }
        $branchLib = kernel::single('ome_store_manage');
        $branchLib->loadBranch(array('branch_id' => $branch_id));
        $oIso_items = app::get('taoguaniostockorder')->model('iso_items');
        #需要判断可用库存是否足够
        $iso_items = $oIso_items->getlist('bn,nums,nums as num,product_id', array('iso_id' => $iso_id), 0, -1);
        if ($io == '0') {

            foreach ($iso_items as $ik => $iv) {
                #获取单仓库-单个基础物料中的可用库存
                $params = array(
                    'node_type' => 'getAvailableStore',
                    'params'    => array(
                        'branch_id'  => $branch_id,
                        'product_id' => $iv['product_id'],
                    ),
                );
                $usable_store = $branchLib->processBranchStore($params, $err_msg);

                if ($iv['nums'] > $usable_store) {
                    return [false, ['msg'=>$iv['bn'] . '出库数量不可大于库存数量.' . $usable_store]];
                }
            }

        }
        $iso_data = array('check_status' => '2', 'check_time'=>time());
        $result   = $isoObj->update($iso_data, array('iso_id' => $iso_id, 'check_status'=>'1'));
        if (is_bool($result)) {
            return [false, ['msg'=>'更新状态失败']];
        }
        if ($io == '0') {
            #将库存冻结
            //库存管控处理
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id' => $branch_id));

            $params                    = array();
            $params['node_type']       = 'checkStockout';
            $params['params']          = array('iso_id' => $iso_id, 'branch_id' => $branch_id);
            $params['params']['items'] = $iso_items;

            $processResult = $storeManageLib->processBranchStore($params, $err_msg);
            if (!$processResult) {
                return [false, ['msg'=>'审核失败,' . $err_msg]];
            }

            #出库
            kernel::single('console_event_trigger_otherstockout')->create(array('iso_id' => $iso_id), false);
        } else {

            // if ($iso['type_id'] == '4') {
                $storeManageLib = kernel::single('ome_store_manage');
                $storeManageLib->loadBranch(array('branch_id' => $branch_id));

                $params                    = array();
                $params['node_type']       = 'changeArriveStore';
                $params['params']          = array(
                            'obj_id' => $iso_id,
                            'branch_id' => $branch_id,
                            'obj_type' => 'iostockorder',
                            'operator' => '+'
                        );
                $params['params']['items'] = $iso_items;

                $processResult = $storeManageLib->processBranchStore($params, $err_msg);

                if (!$processResult) {
                    return [false, ['msg'=>'审核失败,' . $err_msg]];
                }

            // }

            kernel::single('console_event_trigger_otherstockin')->create(array('iso_id' => $iso_id), false);
        }
        return [true, ['msg'=>'操作成功']];
    }

    function getIoStockOrderBn(){
        return $this->iostockorder_bn;
    }

    function set(&$data,&$msg=array()){
        $itemsObj = app::get('taoguaniostockorder')->model('iso_items');
        $itemsDetailMdl = app::get('taoguaniostockorder')->model('iso_items_detail');
        if(is_array($data) && count($data)>0){
            if(!$this->check_required($data,$msg)){
                return false;
            }
            $this->divide_data($data,$main,$item);
            if($this->_mainvalue($main,$msg) && $this->_itemvalue($item,$msg)){
                if($this->_add_iso($main)){
                    $data['iso_id'] = $main['iso_id'];
                    foreach($item as $item_key=>$value){
                        $value['iso_id'] = $data['iso_id'];
                        $items_detail = $value['items_detail'] ?? [];
                        unset($value['items_detail']);
                        $isSave = $itemsObj->insert($value);
                        if(!$isSave){
                            //事务回滚
                            $msg[] = '保存出入库单items明细失败[product_bn:'. $value['bn'] .']';
                            return false;
                        }
                        $iso_items_id = $value['iso_items_id'];
    
                        if ($items_detail) {
                            $itemDetail = [];
                            foreach ($items_detail as $detail_key => $detail_value) {
                                $detail_value['iso_id']       = $data['iso_id'];
                                $detail_value['iso_items_id'] = $iso_items_id;
                                $itemDetail[$detail_key]      = $detail_value;
                            }
                            $item_sql = ome_func::get_insert_sql($itemsDetailMdl, $itemDetail);
                            kernel::database()->exec($item_sql);
                        }
                    }
                }
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    function _add_iso(&$data){
        $objIoStockOrder = app::get('taoguaniostockorder')->model('iso');
        return $objIoStockOrder->save($data);
    }

    /**
     *
     * 生成各类型出入库单的相关单据
     * @param unknown_type $iso_id
     * @param unknown_type $io
     * @param unknown_type $msg
     */
    function confirm_iostockorder($params,$type_id,&$msg,$extend=null){

        $stockLib = '';
        switch($type_id){
            case '1'://采购入库

                $stockLib = 'siso_receipt_iostock_purchase';
            break;
            case '4'://调拨入库
                $stockLib = 'siso_receipt_iostock_allocatein';

            break;
            case '50':;//残损入库
                $stockLib = 'siso_receipt_iostock_damagedin';
            case '70'://直接入库
            case '800'://分销入库
            case '200'://赠品入库
            case '400'://样品入库
            case '11'://调拨入库取消 原仓库入库
                $stockLib = 'siso_receipt_iostock_stockin';
            break;
            case '10'://采购退货
                $stockLib = 'siso_receipt_iostock_purchasereturn';
            break;
            case '40'://调拨出库
                $stockLib = 'siso_receipt_iostock_allocateout';
                break;
            case '5'://残损出库
                $stockLib = 'siso_receipt_iostock_damagedout';
                break;
            case '7'://直接出库
            case '700'://分销出库
            case '100'://赠品出库
            case '300'://样品出库
                $stockLib = 'siso_receipt_iostock_stockout';
            break;

            case '6'://盘亏
                $stockLib = 'siso_receipt_iostock_shortage';
            break;
            case '60'://盘盈
                $stockLib = 'siso_receipt_iostock_overage';
            break;
            case '500': #期初入库
                $stockLib = 'siso_receipt_iostock_defaultstore';

            break;
            case '600'://转储入库
                $stockLib = 'siso_receipt_iostock_stockdumpin';
            break;
            case '9'://转储出库
                $stockLib = 'siso_receipt_iostock_stockdumpout';
            break;

            default:
                return true;
            break;
        }
        $allow_commit = false;
        if ($stockLib){

            $stockinLib = kernel::single($stockLib);
            $stockinLib->_typeId = $type_id;

           if ($stockinLib->create($params, $data, $msg)){
                $allow_commit = true;
            }

            if ($allow_commit == true){
                //kernel::database()->commit();
                return true;
            }else{
                //kernel::database()->rollBack();
                $msg = ['iostock_msg' => $msg];
                $msg['other_msg'] = $other_msg;
                return false;
            }

        }

    }


    function do_iostock_credit_sheet($iso_id,$io,&$msg){
        //出入库及赊购单记录
       $credit_sheet_instance = kernel::single('purchase_credit_sheet');
        if ( method_exists($credit_sheet_instance, 'save_credit_sheet') ){
            if($this->isCredit($iso_id,$credit_sheet_instance)){
                $credit_sheet =  $this->get_credit_sheet_data($iso_id,$credit_sheet_instance->gen_id());
                if($credit_sheet_instance->save_credit_sheet($credit_sheet,$credit_sheet_msg)){
                    return true;
                }else{
                    return false;
                }
            }else{
                return true;
            }
        }
    }

    /**
     *
     * 是否要生成赊购单
     * @param unknown_type $iso_id
     * @param unknown_type $credit_sheet_instance
     */
    function isCredit($iso_id,$credit_sheet_instance){
        $iso = $this->getIso($iso_id,'original_id');
        // 当original_id为0时，直接返回true，对应直接入库生成赊购单
        if($iso['original_id']==0) {
            return true;
        }
        return $credit_sheet_instance->isCredit($iso['original_id']);
    }

    function do_iostock_refunds($iso_id,$io,&$msg){
        //出入库及赊购单记录

        $refunds_instance = kernel::single('purchase_refunds');
        if ( method_exists($refunds_instance, 'save_refunds') ){
            $refunds_data =  $this->get_refunds_data($iso_id);
            if($refunds_data['po_type'] == 'cash'){
                if($refunds_instance->save_refunds($refunds_data,$refunds_msg)){
                    return true;
                }else{
                    return false;
                }
            }else{
                return true;
            }
        }
    }

//    function do_iostock($iso_id,$io,&$msg){
//        //生成出入库明细
//        $allow_commit = false;
//        kernel::database()->beginTransaction();
//        $iostock_instance = kernel::service('ome.iostock');
//        if ( method_exists($iostock_instance, 'set') ){
//            //存储出入库记录
//            $iostock_data = $this->get_iostock_data($iso_id,$type);
//            //eval('$type='.get_class($iostock_instance).'::DIRECT_STORAGE;');
//            $iostock_bn = $iostock_instance->get_iostock_bn($type);
//            if ( $iostock_instance->set($iostock_bn, $iostock_data, $type, $iostock_msg, $io) ){
//                $allow_commit = true;
//            }
//        }
//        if ($allow_commit == true){
//            kernel::database()->commit();
//            return true;
//        }else{
//            kernel::database()->rollBack();
//            $msg = $iostock_msg;
//            return false;
//        }
//    }

    function gen_id(){
        list($msec, $sec) = explode(" ",microtime());
        $id = $sec.strval($msec*1000000);
        $conObj = app::get('ome')->model('concurrent');
        if($conObj->is_pass($id,'iostockorder')){
            return $id;
        } else {
            return $this->gen_id();
        }
    }

    /**
     *检验必填字段是否全部填写
     *
     **/
    function check_required($data,&$msg){return true;
        $msg = array();
        $arrFrom = array('branch_id','bn','iostockorder_price','nums','operator');
        if($data){
            foreach($data as $key=>$val){
                $arrExit = array_keys($val);
                if( count(array_diff($arrFrom,$arrExit)) ){
                   $msg[] =$key . '- -所有必填字段';
                }
            }
            if(count($msg)){

                return false;
            }
        }
        return true;
    }

    /**
    *检验字段类型是否符合要求
    *
    **/
    function check_value($data,&$msg){
        $msg = array();
        $rea = '字段类型不符';
        foreach($data as $keys=>$val){
            foreach($val as $key=>$value){
                switch($key){
                    case 'iostockorder_bn':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=32){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'original_bn':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=32){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'original_id':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'original_item_id':
                       if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'supplier_id':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'bn':
                        if(is_string($value) && strlen($value)<=32){
                            break;
                        } else{
                            $msg[] = $keys .'-'. $key.'-'.$rea;
                        }
                        break;
                    case 'nums':
                        if(is_numeric($value) && strlen($value)<=8 && $value>0){
                            break;
                        } else{
                            $msg[] = $keys .'-'. $key.'-'.$rea;
                        }
                        break;
                    case 'cost_tax':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=20){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'oper':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=30){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'operator':
                        if(is_string($value) && strlen($value)<=30){
                            break;
                        } else{
                            $msg[] = $keys .'-'. $key.'-'.$rea;
                        }
                        break;
                    case 'settle_method':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=32){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_status':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=2){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_operator':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=30){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_time':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=10 && $value>0){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settle_num':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=8 && $value>0){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settlement_bn':
                        if(!empty($value)){
                            if(is_string($value) && strlen($value)<=32){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                    case 'settlement_money':
                        if(!empty($value)){
                            if(is_numeric($value) && strlen($value)<=20){
                                break;
                            } else{
                                $msg[] = $keys .'-'. $key.'-'.$rea;
                            }
                        }
                        break;
                }
            }
        }
        if(!count($msg)){
            return true;
        } else {
            return false;
        }
    }

    /**
    * 生成出入库单号
    * $type 类型 如：iostock-1
    **/
    function get_iostockorder_bn($type,$num = 0){
        $iostock_instance = kernel::service('ome.iostock');
        $kt = $iostock_instance->iostock_rules($type);
        $iostockorder_type = 'iostockorder-'.$type;
        $prefix = $kt.date('ymd');
        $sign   = kernel::single('eccommon_guid')->incId($iostockorder_type, $prefix, 8);
        return $sign;
    }

    /**
     * 组织出库数据
     * @access public
     * @param String $iso_id 出入库ID
     * @return sdf 出库数据
     */
    public function get_iostock_data($iso_id,&$type){
        $objIsoItems = app::get('taoguaniostockorder')->model('iso_items');

        $iostock_data = array();
        $db = kernel::database();
        $sql = 'SELECT * FROM `sdb_taoguaniostockorder_iso` WHERE `iso_id`=\''.$iso_id.'\'';
        $iso_detail = $db->selectrow($sql);
        $iso_items_detail = $objIsoItems->getList('*', array('iso_id'=>$iso_id), 0, -1);
        if ($iso_items_detail){
            foreach ($iso_items_detail as $k=>$v){
                $iostock_data[$v['iso_items_id']] = array(
                    'branch_id' => $iso_detail['branch_id'],
                    'original_bn' => $iso_detail['iso_bn'],
                    'original_id' => $iso_id,
                    'original_item_id' => $v['iso_items_id'],
                    'supplier_id' => $iso_detail['supplier_id'],
                    'supplier_name' => $iso_detail['supplier_name'],
                    'bn' => $v['bn'],
                    'iostock_price' => $v['price'],
                    'nums' => $v['nums'],
                    'cost_tax' => $iso_detail['cost_tax'],
                    'oper' => $iso_detail['oper'],
                    'create_time' => $iso_detail['create_time'],
                    'operator' => $iso_detail['operator'],
                    'settle_method' => $iso_detail['settle_method'],
                    'settle_status' => $iso_detail['settle_status'],
                    'settle_operator' => $iso_detail['settle_operator'],
                    'settle_time' => $iso_detail['settle_time'],
                    'settle_num' => $iso_detail['settle_num'],
                    'settlement_bn' => $iso_detail['settlement_bn'],
                    'settlement_money' => $iso_detail['settlement_money'],
                    'memo' => $iso_detail['memo'],
                );
            }
        }
        $type = $iso_detail['type_id'];

        return $iostock_data;
    }

    /**
     * 组织销售单数据
     * @access public
     * @param String $iso_id 出入库单ID
     * @return sdf 销售单数据
     */
    public function get_sales_data($iso_id){
        $db = kernel::database();
        $sales_items_data = array();
        $sales_data = array();
        $goods_amount = $delivery_cost = $additional_costs = $pkg_remain_money = $discount = $deposit = 0;
        $operator = $order_text = $member_id = $shop_id = $pay_status = '';
        $order_ids = $obj_ids = array();

        $iostockObj = app::get('ome')->model('iostock');
        $objIsoItems = app::get('taoguaniostockorder')->model('iso_items');

        $iso_items_detail = $objIsoItems->getList('*', array('iso_id'=>$iso_id), 0, -1, '`bn`');
        $sql = 'SELECT * FROM `sdb_taoguaniostockorder_iso` WHERE `iso_id`=\''.$iso_id.'\'';
        $iso_detail = $db->selectrow($sql);
        $branch_id = $iso_detail['branch_id'];

        if ($iso_items_detail){
            foreach ($iso_items_detail as $k=>$v){
                $iso_items = array();
                //销售单明细
                $sales_items_data[] = array(
                    'item_detail_id' => $v['iso_items_id'],
                    'bn' => $v['bn'],
                    'price' => $v['price'],
                    'nums' => $v['nums'],
                    'branch_id' => $branch_id,
                    //'cost' => $v['cost'],
                );
            }
        }

        //销售单数据
        $operator = kernel::single('desktop_user')->get_name();
        $operator = $operator ? $operator : 'system';
        $sales_data = array(
            'sale_amount' => $goods_amount,
            'delivery_cost' => $delivery_cost,
            'additional_costs' => $additional_costs,
            'deposit' => $deposit,
            'discount' => $discount,
            'memo' => $iso_detail['memo'],
            'member_id' => $member_id,
            'branch_id' => $branch_id,
            'pay_status' => $pay_status,
            'shop_id' => $shop_id,
            'operator' => $operator,
            'sale_time' => time(),
            'sales_items' => $sales_items_data,
        );

        return $sales_data;
    }

    public function get_credit_sheet_data($iso_id,$gen_id){
          $iso_detail = $this->getIso($iso_id);
          $payable = $iso_detail['product_cost'] + $iso_detail['iso_price'];
          $credit_data = array(
               'cs_bn'=>$gen_id,
               'add_time'=>time(),
               'po_bn'=>$iso_detail['original_bn'],
               'supplier_id'=>$iso_detail['supplier_id'] ? $iso_detail['supplier_id'] : 0,
               'operator'=>kernel::single('desktop_user')->get_name(),
               'op_id'=>kernel::single('desktop_user')->get_id(),
               'iso_bn'=>$iso_detail['iso_bn'],
               'payable'=>$payable,
               'eo_id'=>$iso_id,
               'delivery_cost'=>$iso_detail['iso_price'],
               'product_cost'=>$iso_detail['product_cost']
           );

           return $credit_data;
    }


    public function get_refunds_data($iso_id){
        $iso_detail = $this->getIso($iso_id);

        if(intval($iso_detail['original_id']) == 0) {
            // 处理直接出库
            $iso_detail = $this->getIso($iso_id);
            $payable = $iso_detail['product_cost'] + $iso_detail['iso_price'];
            $credit_data = array(
               'cs_bn'=>$gen_id,
               'add_time'=>time(),
               'supplier_id'=>$iso_detail['supplier_id'] ? $iso_detail['supplier_id'] : 0,
               'operator'=>kernel::single('desktop_user')->get_name(),
               'op_id'=>kernel::single('desktop_user')->get_id(),
               'refund'=>$payable,
               'eo_id'=>$iso_id,
               'delivery_cost'=>$iso_detail['iso_price'],
               'product_cost'=>$iso_detail['product_cost'],
               'type'=>'iso',
               'rp_id' => $iso_id,
               'po_type'=>'cash'
            );
            return $credit_data;
        }

        // 根据original_id查询sdb_purchase_returned_purchase
        $data = kernel::single('purchase_mdl_returned_purchase')->getList('amount,product_cost,delivery_cost,delivery_cost,po_type',array('rp_id'=>$iso_detail['original_id']));
        $total = $data[0]['amount'];
        //$total = $data[0]['product_cost'];

        $refund = array();
        $refund['add_time'] = time();
        $refund['refund'] = $total;
        $refund['product_cost'] = $data[0]['product_cost'];
        $refund['delivery_cost'] = $data[0]['delivery_cost'];
        $refund['po_type'] = $data[0]['po_type'];
        $refund['type'] = 'eo';
        $refund['rp_id'] = $iso_detail['original_id'];
        $refund['supplier_id'] = $iso_detail['supplier_id'];

       return $refund;
    }

    function get_create_iso_type($io=1,$isReturnId=false){
        $iostock_instance = kernel::service('ome.iostock');
        if(!$iostock_instance)return array();

        $iso_types = array();
        foreach($iostock_instance->get_iostock_types() as $id=>$type){
            if(isset($type['is_new']) && $type['io'] == $io){
                $iso_types[$id] = $type['info'];
            }
        }

        if($isReturnId){
            return array_keys($iso_types);
        }else{
            return $iso_types;
        }
    }

    function get_iso_type($io=1,$isReturnId=false){
        $iostock_instance = kernel::service('ome.iostock');
        if(!$iostock_instance)return array();

        $iso_types = array();
        foreach($iostock_instance->get_iostock_types() as $id=>$type){
            if($type['io'] == $io){
                $iso_types[$id] = $type['info'];
            }
        }

        if($isReturnId){
            return array_keys($iso_types);
        }else{
            return $iso_types;
        }

    }

	//拆分出主表与子表数据
    function divide_data($data,&$mainArr,&$itemArr){
        if($data){
            foreach($data as $key=>$value){
                if($key == 'iso_items'){
                    $itemArr = $data[$key];
                }else{
                    $mainArr[$key] = $data[$key];
                }
            }
            return true;
        }
        return false;
    }

	//检查明细表值是否符合
    function _itemvalue($data,&$msg){return true;
        $rea = '字段类型不符(子表)';
        if(is_array($data)){
            foreach($data as $key=>$val){
                foreach($val as $field=>$content){
                    if($content != ''){
                        switch ($field){
                                //bigint(20) unsigned
                            case 'sale_id':
                            case 'iostock_id':
                                if(is_numeric($content) && strlen($content)<=20 && $content>0){
                                } else{
                                    $msg[] = $key .'-'. $field.'-'.$rea;
                                }
                                break;
                                //int(10) unsigned
                            case 'item_id':
                                if(is_numeric($content) && strlen($content)<=10 && $content>0){
                                } else{
                                    $msg[] = $key .'-'. $field.'-'.$rea;
                                }
                                break;
                                //varchar(32)
                            case 'bn':
                                if(is_string($content) && strlen($content)<=32){
                                } else{
                                    $msg[] = $key .'-'. $field.'-'.$rea;
                                }
                                break;
                                // mediumint(8) unsigned
                            case 'nums':
                            case 'branch_id':
                                if(is_numeric($content) && strlen($content)<=8 && $content>0){
                                } else{
                                    $msg[] = $key .'-'. $field.'-'.$rea;
                                }
                                break;
                                //decimal(20,3)
                            case 'price':
                            case 'cost':
                            case 'cost_tax':
                                if(is_numeric($content) && strlen($content)<=20){
                                } else{
                                    $msg[] = $key .'-'. $field.'-'.$rea;
                                }
                                break;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }


//检查主表字段值是否符合
    function _mainvalue($data,&$msg){return true;
        $rea = '字段类型不符(主表)';
        foreach($data as $key=>$content){
            if($content != ''){
                switch ($key){
                        //bigint(20) unsigned
                    case 'sale_id':
                        if(is_numeric($content) && strlen($content)<=20 && $content>0){
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                        //varchar(32)
                    case 'sale_bn':
                    case 'iostock_bn':
                    case 'shop_id':
                        if(is_string($content) && strlen($content)<=32){
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                        //int(10) unsigned
                    case 'sale_time':
                    case 'member_id':
                        if (!empty($content)){
                            if(is_numeric($content) && strlen($content)<=10 && $content>0){
                            } else{
                                $msg[] = $key .'-'.$rea;
                            }
                        }
                        break;
                        //decimal(20,3)
                    case 'sale_amount':
                    case 'cost':
                    case 'delivery_cost':
                    case 'additional_costs':
                    case 'deposit':
                    case 'discount':
                        if(is_numeric($content) && strlen($content)<=20){
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                        //varchar(30)
                    case 'operator':
                        if(is_string($content) && strlen($content)<=30){
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                        //mediumint(8) unsigned
                    case 'branch_id':
                        if(is_numeric($content) && strlen($content)<=8 && $content>0){
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                        //enum('0','1')
                    case 'pay_status':
                        if(is_numeric($content) && strlen($content)<=2){
                        } else{
                            $msg[] = $key .'-'.$rea;
                        }
                        break;
                }
            }
        }
        return true;
    }

    function check_iostockorder($data,&$msg){
        $iso_id = $data['iso_id'];
        $type = $data['type_id'];
        $oper = $data['operator'];//经手人
        if($this->confirm_iostockorder($iso_id,$type,$msg)){
            $objIoStockOrder = app::get('taoguaniostockorder')->model('iso');
            $data = array(
                        'iso_id'   => $iso_id,
                        'confirm'  => 'Y',
                        'oper'     => $oper,//经手人
                        'operator' => kernel::single('desktop_user')->get_name(),//操作员
            );
            #更新出入库数据
            return $objIoStockOrder->save($data);
        }else{
            return false;
        }
    }

    function getIsoList($original_id,$type_id){
        $db = kernel::database();
        $sql = 'SELECT * FROM `sdb_taoguaniostockorder_iso` WHERE `original_id`="'.$original_id.'" AND `type_id`="'.$type_id.'"';
        return $db->select($sql);
    }

    function getIsoItems($iso_id){
        $objIsoItems = app::get('taoguaniostockorder')->model('iso_items');
        $iso_items_detail = $objIsoItems->getList('*', array('iso_id'=>$iso_id), 0, -1);
        return $iso_items_detail;
    }

    function getIso($iso_id,$field='*'){
        $db = kernel::database();
        $sql = 'SELECT '.$field.' FROM `sdb_taoguaniostockorder_iso` WHERE `iso_id`=\''.$iso_id.'\'';
        return $db->selectrow($sql);
    }

    /**
     * 自动审核调拨单
     * 
     * @param int $iso_id
     * @param bool $is_store_return
     * @return array
     */
    public function autoCheck($iso_id, $is_store_return=false)
    {
        $isoObj = app::get('taoguaniostockorder')->model('iso');
        $iso    = $isoObj->dump($iso_id, 'check_status,branch_id,iso_bn,type_id,appropriation_no');
        
        if (!$iso) {
            return array(false, '出入库单不存在');
        }
        
        $io = kernel::single('ome_iostock')->getIoByType($iso['type_id']);
        
        $branch_id = $iso['branch_id'];
        if ($iso['check_status'] != '1') {
            return array(false, '此单据已审核');
        }
        
        $branchLib = kernel::single('ome_store_manage');
        $branchLib->loadBranch(array('branch_id' => $branch_id));
        
        $oIso_items = app::get('taoguaniostockorder')->model('iso_items');
        
        $iso_items = $oIso_items->getlist('bn,nums,product_id', array('iso_id' => $iso_id));
        
        if ($io == '0') {
            foreach ($iso_items as $ik => $iv) {
                $params = array(
                        'node_type' => 'getAvailableStore',
                        'params'    => array(
                                'branch_id'  => $branch_id,
                                'product_id' => $iv['product_id'],
                        ),
                );
                if (!$is_store_return) {
                    $usable_store = $branchLib->processBranchStore($params, $err_msg);
        
                    if ($iv['nums'] > $usable_store) {
                        return array(false, $iv['bn'] . '出库数量不可大于库存数量.' . $usable_store);
                    }
                }
            }
        }
        
        $operator    = kernel::single('desktop_user')->get_name();
        $iso_data    = array('check_status' => '2', 'confirm_time' => time(), 'oper' => $operator);
        $affect_rows = $isoObj->update($iso_data, array('iso_id' => $iso_id, 'check_status' => '1'));
        
        if (is_bool($affect_rows)) {
            return array(false, '审核失败：影响0行');
        }
        
        if ($io == '0') {
            $storeManageLib = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id' => $branch_id));
            
            $params                    = array();
            $params['node_type']       = 'checkStockout';
            $params['params']          = array('iso_id' => $iso_id, 'branch_id' => $branch_id);
            $params['params']['items'] = $iso_items;
            if (!$is_store_return) {
                $processResult = $storeManageLib->processBranchStore($params, $err_msg);
                if (!$processResult) {
                    return array(false, '审核失败：' . $err_msg);
                }
            }
            
            $approMdl = app::get('taoguanallocate')->model('appropriation');
            $filter   = array('appropriation_no' => $iso['appropriation_no']);
            $approMdl->update(array('process_status' => 3), $filter);
            
            kernel::single('console_event_trigger_otherstockout')->create(array('iso_id' => $iso_id), false);
        }else{
            if ($iso['type_id'] == '4') {
                $storeManageLib = kernel::single('ome_store_manage');
                $storeManageLib->loadBranch(array('branch_id' => $branch_id));
            
                $params = array(
                        'node_type' => 'changeArriveStore',
                        'params'    => array(
                                'branch_id' => $branch_id,
                                'items'     => $iso_items,
                                'operator'  => '+',
                        ),
                );
            
                if (!$storeManageLib->processBranchStore($params, $err_msg)) {
                    return array(false, '审核失败：' . $err_msg);
                }
            }
            
            kernel::single('console_event_trigger_otherstockin')->create(array('iso_id' => $iso_id), false);
        }
        
        return array(true, '审核成功');
    }


     public function cmp_productid($arr1, $arr2)
    {
        if ($arr1['product_id'] == $arr2['product_id']) {
            return 0;
        }
        return ($arr1['product_id'] < $arr2['product_id']) ? -1 : 1;
    }
    
    /**
     * 转仓单
     * @param $data
     * @param $msg
     * @return bool|mixed|string
     * @author db
     * @date 2023-12-11 4:33 下午
     */
    function save_warehouse_iostockorder($data,&$msg){
        //检查出入库类型id是否合法
        $type = $data['type_id'];
        $iostockorder_bn = $this->get_iostockorder_bn($type);
        $product_cost = 0;
        $iso_items = array();
        $iso_items_simple = array();
        $confirm = isset($data['confirm']) && $data['confirm'] == 'Y' ? 'Y' : 'N';
        $mdl_wiis = app::get('warehouse')->model('iso_items_simple'); //忽视以bn po_name dly_note_number为维度的明细情况 这张表统计只以bn作为维度的明细
        foreach($data['products'] as $product_id=>$product){
            if(!$product['bn']){ //获取pbook数据的时候 会有bn po_name dly_note_number 为一个维度
                $rl_pid_items = array();
                foreach($product as $var_p){
                    $items = array(
                        'iso_bn'=>$iostockorder_bn,
                        'product_id'=>$product_id,
                        'product_name'=>$var_p['name'],
                        'bn'=>$var_p['bn'],
                        'unit'=>$var_p['unit'],
                        'nums'=>$var_p['nums'],
                        'price'=>$var_p['price'],
                        'po_name'=>$var_p['po_name'],
                        'dly_note_number'=>$var_p['dly_note_number'],
                        'box_number'=>$var_p['box_number'],
                    );
                    if ($confirm == 'Y'){
                        $items['normal_num'] = $var_p['nums'];
                    }
                    $iso_items[] = $items;
                    $product_cost+= $var_p['nums'] * $var_p['price'];
                    //处理iso_items_simple表数据
                    if(isset($rl_pid_items[$product_id])){ //存在处理
                        $rl_pid_items[$product_id]["nums"] = $rl_pid_items[$product_id]['nums'] + $var_p['nums']; //叠加product_id
                    }else{ //不存在处理
                        $rl_pid_items[$product_id] = array(
                            'iso_bn'=>$iostockorder_bn,
                            'product_id'=>$product_id,
                            'product_name'=>$var_p['name'],
                            'bn'=>$var_p['bn'],
                            'unit'=>$var_p['unit'],
                            'nums'=>$var_p['nums'],
                            'price'=>$var_p['price'],
                        );
                    }
                }
                //处理iso_items_simple表数据
                foreach($rl_pid_items as $var_rpi){
                    $items_simple = $var_rpi;
//                     if ($confirm == 'Y'){
//                         $items_simple['normal_num'] = $var_rpi['nums'];
//                     }
                    $iso_items_simple[] = $items_simple;
                }
            }else{
                $items = array(
                    'iso_bn'=>$iostockorder_bn,
                    'product_id'=>$product_id,
                    'product_name'=>$product['name'],
                    'bn'=>$product['bn'],
                    'unit'=>$product['unit'],
                    'nums'=>$product['nums'],
                    'price'=>$product['price'],
                );
                $items_simple = $items;
                if($product["po_name"]){
                    $items["po_name"] = $product["po_name"];
                }
                if($product["dly_note_number"]){
                    $items["dly_note_number"] = $product["dly_note_number"];
                }
                if($product["box_number"]){
                    $items["box_number"] = $product["box_number"];
                }
                if ($confirm == 'Y'){
                    $items['normal_num'] = $product['nums'];
                    //$items_simple['normal_num'] = $product['nums'];
                }
                $iso_items[] = $items;
                $iso_items_simple[] = $items_simple;
                $product_cost+= $product['nums'] * $product['price'];
            }
        }
        $operator = kernel::single('desktop_user')->get_name();
        $operator = $operator ? $operator : 'system';
        $iostockorder_data = array(
            'confirm'=>$confirm,
            'name' => $data['iostockorder_name'],
            'iso_bn' => $iostockorder_bn,
            'type_id' => $data['type_id'],
            'branch_id' => $data['branch'],
            'extrabranch_id'=>$data['extrabranch_id'],
            'supplier_id' => $data['supplier_id'],
            'supplier_name' => $data['supplier'],
            'iso_price' => $data['iso_price'],
            'cost_tax' => 0,
            'oper' => $data['operator'],
            'create_time' => time(),
            'check_time' => time(),
            'operator' => $operator ,
            'product_cost'=>$product_cost,
            'memo' => $data['memo'],
            'emergency' => $data['emergency'] ? 'true' : 'false',
            'original_bn'=> $data['original_bn'] ? $data['original_bn'] : '',
            'original_id'=> $data['original_id'] ? $data['original_id'] : 0,
            'iso_items'=>$iso_items,
        );
        if ($data['original_iso_bn']) {
            $iostockorder_data['original_iso_bn'] = $data['original_iso_bn'];
        }
        if ($confirm == 'Y'){
            $iostockorder_data['check_status'] = '2';#已审核
            $iostockorder_data['iso_status'] = '3';#全部入库
        }
        $this->iostockorder_bn = $iostockorder_bn;
        if($this->set_warehouse($iostockorder_data)){
    
            if($confirm == 'Y' ){
                $confirm_data = array(
                    'iso_id'=>$iostockorder_data['iso_id'],
                    'memo'=>$data['memo'],
                    'operate_time'=>$data['operate_time']!='' ? strtotime($data['operate_time']) : '',
                    'branch_id'=>$data['branch'],
                    'po_type'=>$data['po_type'],
                    'items'=>$iostockorder_data['iso_items'],
                    'orig_type_id'=>$data['orig_type_id'] ? $data['orig_type_id'] : '0',
                );
                if($this->confirm_iostockorder($confirm_data,$type,$msg,$data['extend'])){
                    return $iostockorder_data['iso_id'];
                }else{
                    return false;
                }
            }else{
    
                //处理iso_items_simple表数据
                foreach($iso_items_simple as $var_iis){
                    $var_iis["iso_id"] = $iostockorder_data['iso_id'];
                    $mdl_wiis->insert($var_iis);
                }
    
                return $iostockorder_data['iso_id'];
            }
        }else{
            return false;
        }
    }
    
    function set_warehouse(&$data,&$msg=array()){
        $itemsObj = app::get('warehouse')->model('iso_items');
        if(is_array($data) && count($data)>0){
            if(!$this->check_required($data,$msg)){
                return false;
            }
            $this->divide_data($data,$main,$item);
            if($this->_mainvalue($main,$msg) && $this->_itemvalue($item,$msg)){
                if($this->_add_warehouse_iso($main)){
                    $data['iso_id'] = $main['iso_id'];
                    foreach($item as $item_key=>$value){
                        $value['iso_id'] = $data['iso_id'];
                        $item[$item_key] = $value;
                    }
                    $item_sql = ome_func::get_insert_sql($itemsObj,$item);
                    kernel::database()->exec($item_sql) ;
                }
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
    
    function _add_warehouse_iso(&$data){
        $objIoStockOrder = app::get('warehouse')->model('iso');
        return $objIoStockOrder->save($data);
    }

    public function processBatch($iostock_data) {

      
        $iso_items = $iostock_data['iso_items'];
        $useLogModel = app::get('console')->model('useful_life_log');

        if(in_array($iostock_data['type_id'],array('1','10'))){
            $iostock_data['iso_bn'] = $iostock_data['original_bn'];
            $iostock_data['iso_id'] = $iostock_data['original_id'];
        }
        $useful = [];
        foreach($iso_items as $item){
            foreach ($item['batch'] as $bv) {
                $tmpUseful = [];
                $tmpUseful['product_id'] = $item['product_id'];
                $tmpUseful['bn'] = $item['bn'];
                $tmpUseful['original_bn'] = $iostock_data['iso_bn'];
                $tmpUseful['original_id'] = $iostock_data['iso_id'];
                $tmpUseful['bill_type'] = $iostock_data['bill_type'];
                $tmpUseful['business_bn'] = $iostock_data['business_bn'] ? $iostock_data['business_bn'] : $iostock_data['original_bn'];
                $tmpUseful['sourcetb'] = 'iso';
                $tmpUseful['create_time'] = time();
                $tmpUseful['stock_status'] = '0';
                $tmpUseful['num'] = abs($bv['num']);
                $tmpUseful['normal_defective'] = $bv['normal_defective'];
                $tmpUseful['product_time'] = $bv['product_time'];
                $tmpUseful['expire_time'] = $bv['expire_time'];
                $tmpUseful['purchase_code'] = $bv['purchase_code'];
                $tmpUseful['produce_code'] = $bv['produce_code'];

                $useful[] = $tmpUseful;
            }
        }
        if($useful){

            $useLogModel->db->exec(ome_func::get_insert_sql($useLogModel, $useful));
        }
        
    }
}
