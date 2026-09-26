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

class wms_delivery_process{

    /**
     *
     * 发货单校验完成处理方法
     * @param int $delivery_id 发货单ID
     * @param boolean $auto 是否整单校验
     */
    function verifyDelivery($delivery_id,$auto=false){
        $dly_id = $delivery_id;
        $dlyObj = app::get('wms')->model('delivery');
        $dly_itemObj  = app::get('wms')->model('delivery_items');
        $opObj        = app::get('ome')->model('operation_log');
        //对发货单详情进行校验完成处理
        if ($this->verifyItemsByDeliveryId($dly_id)){
            $res = $dlyObj->db->exec("update sdb_wms_delivery set process_status = (process_status | 2) where delivery_id =".$dly_id);
            if (!$res){
                return false;
            }

            //增加捡货绩效
            foreach(kernel::servicelist('tgkpi.pick') as $o){
                if(method_exists($o,'finish_pick')){
                    $o->finish_pick($dly_id);
                }
            }

            if($auto){
                $msg = '发货单校验完成(免校验)';
            }else{
                $msg = '发货单校验完成';
            }

            if (kernel::single('desktop_user')->get_id()){
                $opObj->write_log('delivery_check@wms', $dly_id, $msg);
            }

            //同步校验状态到oms
            $deliveryInfo = $dlyObj->dump($dly_id,'outer_delivery_bn,branch_id');
            $wms_id = kernel::single('ome_branch')->getWmsIdById($deliveryInfo['branch_id']);
            $data = array(
                'delivery_bn' => $deliveryInfo['outer_delivery_bn'],
            );
            $res = kernel::single('wms_event_trigger_delivery')->doCheck($wms_id, $data, true);

            return true;
        }else {

            if (kernel::single('desktop_user')->get_id()){
                $opObj->write_log('delivery_check@wms', $dly_id, '发货单校验未完成');
            }
            return false;
        }
    }

    /**
     *
     * 校验完成，对发货单对应详情进行更新保存方法
     * @param bigint $dly_id
     * @return boolean
     */
    function verifyItemsByDeliveryId($dly_id){
        $dlyObj  = app::get('wms')->model('delivery');
        $dly_itemObj  = app::get('wms')->model('delivery_items');
        $dlyItemsSLObj    = app::get('wms')->model('delivery_items_storage_life');
        $dlyItemsSerialLib    = kernel::single('wms_receipt_dlyitemsserial');

        $dlyInfo = $dlyObj->getList('delivery_bn,branch_id', array('delivery_id'=>$dly_id), 0, 1);
        $items = $dly_itemObj->getList('item_id,number,product_id,bn,product_name,verify,verify_num,use_expire', array('delivery_id'=>$dly_id), 0, -1);
        foreach ($items as $item){
            //唯一码预占处理
            if(isset($_POST['serial_data'][$item['bn']])){
                foreach($_POST['serial_data'][$item['bn']] as $key => $serial){
                    $serialData = array(
                        'delivery_id' => $dly_id,
                        'delivery_bn' => $dlyInfo[0]['delivery_bn'],
                        'branch_id' => $dlyInfo[0]['branch_id'],
                        'product_id' => $item['product_id'],
                        'bn' => $item['bn'],
                        'product_name' => $item['product_name'],
                        'serial_number' => $serial,
                        'status' => 0,
                    );

                    $rs = $dlyItemsSerialLib->generate($serialData, $msg);
                    if(!$rs){
                        return false;
                    }
                    unset($serialData);
                }
            }

            if($item['use_expire'] == 1){
                $item_expire_arr = $dlyItemsSLObj->getList('*',array('delivery_id'=>$dly_id,'item_id'=>$item['item_id']));
                foreach($item_expire_arr as $item_expire){
                    $data['verify'] = 'true';
                    $data['verify_num'] = $item_expire['number'];

                    if ($dlyItemsSLObj->update($data, array('itemsl_id'=>$item_expire['itemsl_id'])) == false) return false;
                    $data = null;
                }
            }

            $data['verify'] = 'true';
            $data['verify_num'] = $item['number'];

            if ($dly_itemObj->update($data, array('item_id'=>$item['item_id'])) == false) return false;
            $data = null;
        }
        return true;
    }

    /**
     *
     * 校验内容的临时保存方法
     * @param int $dly_id 发货单ID
     */
    function verifyItemsByDeliveryIdFromPost($dly_id)
    {
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        $dlyObj  = app::get('wms')->model('delivery');
        $dly_itemObj  = app::get('wms')->model('delivery_items');
        $dlyItemsSLObj    = app::get('wms')->model('delivery_items_storage_life');
        $dlyItemsSerialLib    = kernel::single('wms_receipt_dlyitemsserial');

        $dlyInfo = $dlyObj->getList('delivery_bn,branch_id', array('delivery_id'=>$dly_id), 0, 1);
        $items = $dly_itemObj->getList('item_id,number,product_id,bn,product_name,verify,verify_num,use_expire', array('delivery_id'=>$dly_id), 0, -1);
        foreach ($items as $item)
        {
            //唯一码预占处理
            if(isset($_POST['serial_data'][$item['bn']])){
                foreach($_POST['serial_data'][$item['bn']] as $key => $serial){
                    $serialData = array(
                        'delivery_id' => $dly_id,
                        'delivery_bn' => $dlyInfo[0]['delivery_bn'],
                        'branch_id' => $dlyInfo[0]['branch_id'],
                        'product_id' => $item['product_id'],
                        'bn' => $item['bn'],
                        'product_name' => $item['product_name'],
                        'serial_number' => $serial,
                        'status' => 0,
                    );

                    $rs = $dlyItemsSerialLib->generate($serialData, $msg);
                    if(!$rs){
                        return false;
                    }
                    unset($serialData);
                }
            }

            //保质期批次校验
            if($item['use_expire'] == 1){
                $item_expire_arr = $dlyItemsSLObj->getList('*',array('delivery_id'=>$dly_id,'item_id'=>$item['item_id']));
                $all_item_num = 0;
                foreach($item_expire_arr as $item_expire){
                    $barcode = $item_expire['expire_bn'];
                    $num = intval($_POST['number_'. $barcode]);

                    $num = $num>$item_expire['number']? $item_expire['number'] : $num;
                    $data['verify'] = 'false';
                    $data['verify_num'] = $num;
                    if ($dlyItemsSLObj->update($data, array('itemsl_id'=>$item_expire['itemsl_id'])) == false) return false;
                    $data = null;
                    $all_item_num += $num;
                    $_POST['number_'. $barcode] -= $num;
                }

                $data['verify'] = 'false';
                $data['verify_num'] = $all_item_num;
                if ($dly_itemObj->update($data, array('item_id'=>$item['item_id'])) == false) return false;
                $data = null;
            }else{
                //普通条码校验
                $barcode    = $basicMaterialBarcode->getBarcodeById($item['product_id']);
                $num = intval($_POST['number_'. $barcode]);
                $num = $num>$item['number']? $item['number'] : $num;
                $data['verify'] = 'false';
                $data['verify_num'] = $num;
                if ($dly_itemObj->update($data, array('item_id'=>$item['item_id'])) == false) return false;
                $data = null;
                $_POST['number_'. $barcode] -= $num;
            }
        }
        return true;
    }

    /**
     * 包裹验证
     * @param  Int $bill_id 包裹ID
     * @param  Array $data    货品校验数据
     * @param  String $type    校验类型。barcode:条码校验，all:整单校验
     * @return boolean          成功/失败
     */
    public function verifyPackage($bill_id, $data, $type = 'barcode')
    {
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        $deliveryBillItemMdl     = app::get('wms')->model('delivery_bill_items');

        $items = $deliveryBillItemMdl->getList('*', ['bill_id' => $bill_id]);
        foreach ($items as $item)
        {
            $upData = [];

            // 普通条码校验
            $barcode    = $basicMaterialBarcode->getBarcodeById($item['product_id']);

            $upData['verify_num'] = $type == 'all' ? $item['number'] : intval($data['number_'. $barcode]);

            
            $affect_row = $deliveryBillItemMdl->update($upData, [
                'bill_item_id' => $item['bill_item_id'], 
                'number|bthan'=>$upData['verify_num']
            ]);
            if (!$affect_row) {
                return false;
            }
        }

        return true;
    }

    /**
     *
     * 执行具体的发货处理事务
     * @param int $dly_id 发货单id
     * @param string $type 发货处理的方式batch:批量/group:分组
     */
    function consignDelivery($dly_id, $type='') {
        $deliveryObj = app::get('wms')->model('delivery');
        $opinfo = array();

        $delivery_time = time();
        $filter['delivery_id'] = $dly_id;
        $dlydata['status'] = 3;
        $dlydata['process_status'] = 7;
        $dlydata['delivery_time'] = $delivery_time;

        $affect_row = $deliveryObj->update($dlydata, $filter);
        if(!is_numeric($affect_row) || $affect_row <= 0){
            return false;
        }else{
            $deliveryInfo = $deliveryObj->dump($dly_id, '*');

            //如果发货成功，处理保质期批次的变化:冻结释放，实际数量扣减，单据转正
            $storageLifeReceiptLib = kernel::single('material_receipt_storagelife');
            $storagelife_data['branch_id'] = $deliveryInfo['branch_id'];
            $storagelife_data['bill_id'] = $dly_id;
            $storagelife_data['bill_type'] = 3;
            $rs = $storageLifeReceiptLib->consign($storagelife_data , $out_storagelife, $msg);
            if(!$rs){
                return false;
            }

            //唯一码出库处理
            $dlyItemsSerialLib    = kernel::single('wms_receipt_dlyitemsserial');
            $serialItem = array('delivery_id' => $dly_id, 'delivery_bn' => $deliveryInfo['delivery_bn'], 'branch_id' => $deliveryInfo['branch_id']);
            $rs = $dlyItemsSerialLib->consign($serialItem, $out_serial);
            if(!$rs){
                return false;
            }

            $msg = '发货单发货完成';
            if($type == wms_const::__BATCH){
                $msg = '发货单发货(批量)完成';
            }elseif($type == wms_const::__GROUP){
                $msg = '发货单发货(分组)完成';
            }

            //发货成功记录相应日志
            $opObj = app::get('ome')->model('operation_log');
            $opObj->write_log('delivery_process@wms', $dly_id, $msg,'',$opinfo);

            //WMS发货单发货触发通知OMS发货
            $wms_id = kernel::single('ome_branch')->getWmsIdById($deliveryInfo['branch_id']);

            $data = array(
                'delivery_bn' => $deliveryInfo['outer_delivery_bn'],
                'delivery_time' => $delivery_time,
                'weight' => $deliveryInfo['weight'],
                'delivery_cost_actual' => $deliveryInfo['delivery_cost_actual'],
                'out_serial' => $out_serial,
                'out_storagelife' => $out_storagelife,
            );

            //补打运单号
            $billObj = app::get('wms')->model('delivery_bill');
            $deliveryBillItemMdl = app::get('wms')->model('delivery_bill_items');

            // 逐单发货主路径不会先把包裹单置为已发货(status=1)；若仍按 status=1 过滤，
            // 会导致 packages=[]、无 logi_no，OME delivery_package 明细落空。
            $billObj->update(
                array(
                    'status'        => '1',
                    'delivery_time' => $delivery_time,
                ),
                array(
                    'delivery_id' => $dly_id,
                    'status'      => '0',
                )
            );

            // 已发货 + 未取消（兼容历史数据仍为 0 的情况）
            $bill_list = $billObj->getList('*', array('delivery_id' => $dly_id, 'status|noequal' => '2'));
            
            // 获取主单运单号（不强制 status=1，避免主单未翻转时丢 logi_no）
            $primary_bill = $billObj->dump(array('delivery_id'=>$dly_id, 'type'=>'1'), '*');

            $other_list_0 = array(); $packages = [];
            foreach ($bill_list as $bill) {
                if ((string) $bill['status'] === '2') {
                    continue;
                }
                // 补打运单号
                if ($bill['type'] == '2'){
                    $other_list_0[] = array('logi_no'=>$bill['logi_no'],'weight'=>$bill['weight']);
                }

                // 包裹号
                $billItemList = $deliveryBillItemMdl->getList('product_id,bn,product_name,number', ['bill_id' => $bill['b_id']]);
                $packages[] = array (
                    'package_bn'    => $bill['package_bn'] ? $bill['package_bn'] : $deliveryInfo['outer_delivery_bn'],
                    'logi_no'       => $bill['logi_no'],
                    'delivery_time' => $bill['delivery_time'] ? $bill['delivery_time'] : $delivery_time,
                    'items'         => $billItemList,
                );
            }

            // logi_id
            if ($deliveryInfo['logi_id']) {
                $data['logistics'] = $deliveryInfo['logi_id'];
            }
            
            // logi_no
            if (isset($primary_bill['logi_no']) && $primary_bill['logi_no']) {
                $data['logi_no'] = $primary_bill['logi_no'];
            }

            $data['other_list_0'] = json_encode($other_list_0);
            $data['packages']     = json_encode($packages);

            // 参照 WAP/奇门：补齐 flat package / bill_logi_weight，供 ome 落 delivery_package / delivery_bill
            $packageBillParams = $this->buildConsignPackageAndBillParams($deliveryInfo, $data, $bill_list, $packages);
            if (!empty($packageBillParams['package'])) {
                $data['package'] = $packageBillParams['package'];
            }
            if (!empty($packageBillParams['bill_logi_weight'])) {
                $data['bill_logi_weight'] = $packageBillParams['bill_logi_weight'];
            }

            //因保质期批次重写需要兼容新的方式组数据
            if($data['out_storagelife']){
                $iostockLib = kernel::single('wms_event_trigger_otherinstorage');
                $out_storagelife = $data['out_storagelife'];
                $out_storagelife = array_column($out_storagelife,null,'bm_id');
                $dly_itemObj  = app::get('wms')->model('delivery_items');
                $dlyitems = $dly_itemObj->getList('item_id,number,product_id,bn,product_name', array('delivery_id'=>$dly_id), 0, -1);
                $items = array();
                foreach ($dlyitems as $v)
                {

                    $batchs = [];
                    $product_id = $v['product_id'];
                    $branch_id = $deliveryInfo['branch_id'];
                    $storagelifes = $out_storagelife[$product_id];
                    $expire_bn = $storagelifes['expire_bn'];
                    $lifes = $iostockLib->getlifedetail($branch_id,$product_id,$expire_bn);
                    $batchs[] = array(
                        'purchase_code'     => $storagelifes['expire_bn'],

                        'product_time'      => $lifes['production_date'],
                        'expire_time'       => $lifes['expiring_date'],
                        'normal_defective'  => 'normal',
                        'num'               => $storagelifes['nums'],

                    );
                    $items[] = array(
                        'bn'    =>  $v['bn'],
                        'num'           =>  $v['number'],
                        'batch'         =>  $batchs,
                    );
                }
                $data['items'] = $items;
            }
            
            $res = kernel::single('wms_event_trigger_delivery')->consign($wms_id, $data, true);
            if ($res === false || (is_array($res) && $res['rsp'] == 'fail')) {
                $msg = is_array($res) && $res['msg'] ? '通知OMS发货失败：' . $res['msg'] : '通知OMS发货失败';
                $opObj->write_log('delivery_process@wms', $dly_id, $msg,'',$opinfo);
                
                return false;
            }

            return true;
        }
    }

    /**
     * 打回发货单操作
     *
     * @param array() $dly_ids
     * @param string $memo
     * @return boolean
     */
    function rebackDelivery($dly_ids, $memo){
        if (is_array($dly_ids)){
            $ids = $dly_ids;
        }else {
            $ids[] = $dly_ids;
        }
        $data['memo']    = $memo;
        $data['status']  = 1;
        $data['logi_no'] = null;

        $dlyObj            = app::get('wms')->model('delivery');
        $dlyItemsObj       = app::get('wms')->model('delivery_items');
        $dlyBillObj        = app::get('wms')->model('delivery_bill');
        $branch_productObj = app::get('ome')->model('branch_product');
        $dlyCorpObj        = app::get('ome')->model('dly_corp');

        foreach ($ids as $item)    {
            $deliveryInfo = $dlyObj->dump($item,'delivery_bn, status, branch_id, outer_delivery_bn, logi_id');
            if ($deliveryInfo['status'] == 3){
                continue;
            }

            $data['delivery_id'] = $item;

            //撤销所有发货单包裹单
            $billdata = array(
                'status'=> 2,
                'logi_no' => null,
            );
            $billfilter = array('delivery_id'=>$item);

            //回收电子面单
            $dlyCorp = $dlyCorpObj->dump($deliveryInfo['logi_id'],'tmpl_type,channel_id');

            if ($dlyCorp['tmpl_type'] == 'electron') {
                $logiBillList = $dlyBillObj->getList('logi_no',$billfilter);
                foreach ((array) $logiBillList as $_logi_bill) {
                    if ($_logi_bill['logi_no']) {
                        kernel::single('logisticsmanager_service_waybill')->recycle_waybill($_logi_bill['logi_no'],$dlyCorp['channel_id'],$item,$deliveryInfo['delivery_bn']);
                    }
                }
            }

            $dlyBillObj->update($billdata,$billfilter);


            //将发货单状态更新为打回并记录备注
            if ($dlyObj->save($data)){
                $wms_id = kernel::single('ome_branch')->getWmsIdById($deliveryInfo['branch_id']);
                $data = array(
                    'delivery_bn' => $deliveryInfo['outer_delivery_bn'],
                    'memo' => $data['memo'],
                );
                $res = kernel::single('wms_event_trigger_delivery')->reback($wms_id, $data, true);
                if($res['rsp'] == 'succ'){
                    //自建仓储发起发货单撤销，保质期条码预占释放
                    $storageLifeReceiptLib = kernel::single('material_receipt_storagelife');
                    $storagelife_data['branch_id'] = $deliveryInfo['branch_id'];
                    $storagelife_data['bill_id'] = $item;
                    $storagelife_data['bill_type'] = 3;
                    $storageLifeReceiptLib->unfreeze($storagelife_data ,$msg);

                    //发货单预占唯一码释放
                    $dlyItemsSerialLib    = kernel::single('wms_receipt_dlyitemsserial');
                    $serialItem = array('delivery_id' => $item, 'delivery_bn' => $deliveryInfo['delivery_bn']);
                    $dlyItemsSerialLib->cancel($serialItem);
                }
                
                //自有仓发货单取消撤回合单标识
                $label_code      = 'SOMS_COMBINE_ORDER';
                $omeDeliveryInfo = app::get('ome')->model('delivery')->db_dump(['delivery_bn' => $deliveryInfo['outer_delivery_bn']], 'delivery_id,delivery_bn');
                $combineLabel    = kernel::single('ome_bill_label')->getBillLabelInfo($omeDeliveryInfo['delivery_id'], 'ome_delivery', $label_code);
                if ($combineLabel) {
                    $labelAll = app::get('omeauto')->model('order_labels')->getList('*', ['label_code' => $label_code]);
                    if ($labelAll) {
                        $labelAll = array_column($labelAll, 'label_id');
                        kernel::single('ome_bill_label')->delLabelFromBillId($item, $labelAll, 'wms_delivery', $error_msg);
                        kernel::single('ome_bill_label')->delLabelFromBillId($omeDeliveryInfo['delivery_id'], $labelAll, 'ome_delivery', $error_msg);
                        $deliveryOrderList = app::get('ome')->model('delivery_order')->getList('order_id', ['delivery_id' => $omeDeliveryInfo['delivery_id']]);
                        foreach ($deliveryOrderList as $val) {
                            kernel::single('ome_bill_label')->delLabelFromBillId($val['order_id'], $labelAll, 'order', $error_msg);
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * 组装自建仓发货回写的包裹/子运单参数（对齐 WAP buildConsignPackageAndBillParams / 奇门 package）
     * 供 ome_event_receive_delivery::_dealDeliveryPackage / _save_bill_logi_weight / _saveMainDeliveryBillIfNeeded 落库
     *
     * @param array $deliveryInfo WMS 发货单
     * @param array $data         即将回写 OMS 的参数（需已含 logi_no 等）
     * @param array $bill_list    WMS delivery_bill 列表
     * @param array $packages     已组装的嵌套 packages（含 items）
     * @return array{package?:array,bill_logi_weight?:array}
     */
    public function buildConsignPackageAndBillParams(array $deliveryInfo, array $data, array $bill_list = array(), array $packages = array())
    {
        $result = array();
        $outerBn = trim((string) (isset($deliveryInfo['outer_delivery_bn']) ? $deliveryInfo['outer_delivery_bn'] : ''));
        $logi_no = trim((string) (isset($data['logi_no']) ? $data['logi_no'] : ''));
        if ($outerBn === '') {
            return $result;
        }

        $omeDly = app::get('ome')->model('delivery')->dump(
            array('delivery_bn' => $outerBn, 'disabled' => 'false'),
            'delivery_id,delivery_bn,logi_id,logi_no'
        );
        if (empty($omeDly['delivery_id'])) {
            return $result;
        }
        $deliveryId = (int) $omeDly['delivery_id'];
        if ($logi_no === '' && !empty($omeDly['logi_no'])) {
            $logi_no = trim((string) $omeDly['logi_no']);
        }
        // 仍无运单号时，从 WMS 主包裹单兜底（逐单发货时常为 status=0）
        if ($logi_no === '' && !empty($bill_list)) {
            foreach ($bill_list as $bill) {
                if ((string) $bill['type'] === '1' && !empty($bill['logi_no'])) {
                    $logi_no = trim((string) $bill['logi_no']);
                    break;
                }
            }
        }

        $logisticsCode = '';
        $logiId = !empty($deliveryInfo['logi_id']) ? $deliveryInfo['logi_id'] : (!empty($omeDly['logi_id']) ? $omeDly['logi_id'] : 0);
        if ($logiId) {
            $corp = app::get('ome')->model('dly_corp')->db_dump(array('corp_id' => $logiId), 'type');
            if (!empty($corp['type'])) {
                $logisticsCode = (string) $corp['type'];
            }
        }

        $now = time();
        $package = array();

        // 优先：WMS 包裹明细（多包裹/多运单）
        foreach ((array) $packages as $pkg) {
            $pkgLogiNo = trim((string) (isset($pkg['logi_no']) ? $pkg['logi_no'] : $logi_no));
            $pkgBn = trim((string) (isset($pkg['package_bn']) && $pkg['package_bn'] !== '' ? $pkg['package_bn'] : $outerBn));
            $pkgTime = !empty($pkg['delivery_time']) ? $pkg['delivery_time'] : $now;
            foreach ((array) (isset($pkg['items']) ? $pkg['items'] : array()) as $item) {
                $bn = isset($item['bn']) ? trim((string) $item['bn']) : '';
                if ($bn === '') {
                    continue;
                }
                $package[] = array(
                    'package_bn'    => $pkgBn,
                    'logi_bn'       => $logisticsCode,
                    'logi_no'       => $pkgLogiNo !== '' ? $pkgLogiNo : $logi_no,
                    'product_id'    => isset($item['product_id']) ? (int) $item['product_id'] : 0,
                    'bn'            => $bn,
                    'outer_sku'     => '',
                    'status'        => 'delivery',
                    'number'        => isset($item['number']) ? (int) $item['number'] : 0,
                    'create_time'   => $now,
                    'delivery_time' => $pkgTime,
                );
            }
        }

        // 回退：OME 明细（bill_items 常为空时与 WAP 一致）
        if (!$package && $logi_no !== '') {
            $detailRows = app::get('ome')->model('delivery_items_detail')->getList(
                '*',
                array('delivery_id' => $deliveryId),
                0,
                -1,
                'item_detail_id ASC'
            );
            if ($detailRows) {
                foreach ($detailRows as $d) {
                    $bn = isset($d['bn']) ? trim((string) $d['bn']) : '';
                    if ($bn === '') {
                        continue;
                    }
                    $package[] = array(
                        'package_bn'    => $outerBn,
                        'logi_bn'       => $logisticsCode,
                        'logi_no'       => $logi_no,
                        'product_id'    => isset($d['product_id']) ? (int) $d['product_id'] : 0,
                        'bn'            => $bn,
                        'outer_sku'     => '',
                        'status'        => 'delivery',
                        'number'        => isset($d['number']) ? (int) $d['number'] : 0,
                        'create_time'   => $now,
                        'delivery_time' => $now,
                    );
                }
            } else {
                $items = app::get('ome')->model('delivery_items')->getList('*', array('delivery_id' => $deliveryId));
                foreach ((array) $items as $iv) {
                    $bn = isset($iv['bn']) ? trim((string) $iv['bn']) : '';
                    if ($bn === '') {
                        continue;
                    }
                    $package[] = array(
                        'package_bn'    => $outerBn,
                        'logi_bn'       => $logisticsCode,
                        'logi_no'       => $logi_no,
                        'product_id'    => isset($iv['product_id']) ? (int) $iv['product_id'] : 0,
                        'bn'            => $bn,
                        'outer_sku'     => '',
                        'status'        => 'delivery',
                        'number'        => isset($iv['number']) ? (int) $iv['number'] : 0,
                        'create_time'   => $now,
                        'delivery_time' => $now,
                    );
                }
            }
        }

        if ($package) {
            $result['package'] = $package;
        }

        // 多运单：主运单外的子单 → bill_logi_weight
        $billLogiWeight = array();
        foreach ((array) $bill_list as $bill) {
            if (!isset($bill['type']) || (string) $bill['type'] !== '2') {
                continue;
            }
            $bnNo = trim((string) (isset($bill['logi_no']) ? $bill['logi_no'] : ''));
            if ($bnNo === '' || ($logi_no !== '' && $bnNo === $logi_no)) {
                continue;
            }
            $billLogiWeight[$bnNo] = isset($bill['weight']) ? $bill['weight'] : 0;
        }
        if ($billLogiWeight) {
            $result['bill_logi_weight'] = $billLogiWeight;
        }

        return $result;
    }
}