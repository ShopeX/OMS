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
class wms_ctl_admin_check extends desktop_controller{
    var $name = "货物校验";
    var $workground = "wms_delivery";

    /**
     *
     * 逐单校验/整单校验的入口展示页
     */
    function index(){
        $deliveryObj  = app::get('wms')->model('delivery');

        $numShow = app::get('wms')->getConf('wms.delivery.checknum.show');
        if($numShow == 'false' || (cachecore::fetch('quick_access') !== false)){
            $this->pagedata['num'] = '未知';
        }else{
            $this->pagedata['num'] = $deliveryObj->countNoVerifyDelivery();
        }

        $this->pagedata['checkType'] = $_GET['type'];
        $this->page("admin/delivery/process_check_index.html");
    }

    /**
     *
     * 批量校验的入口展示页
     */
    function batchIndex(){
        $stock_confirm= app::get('ome')->getConf('purchase.stock_confirm');
        $stock_cancel= app::get('ome')->getConf('purchase.stock_cancel');
        $this->pagedata['stock_confirm'] = $stock_confirm;
        $this->pagedata['stock_cancel'] = $stock_cancel;
        $deliveryObj  = app::get('wms')->model('delivery');
        $this->pagedata['num'] = $deliveryObj->countNoVerifyDelivery();
        $this->page("admin/delivery/process_batch_check_index.html");
    }

    /**
     *
     * 分组校验的入口展示页
     */
    function group_check(){
        $deliCfgLib = kernel::single('wms_delivery_cfg');
        $verfy = $deliCfgLib->getValue('verify');
        if($verfy == 1){
            $deliveryObj = app::get('wms')->model('delivery');
            $orderTypeObj = app::get('omeauto')->model('order_type');
            $groupFilter['tid'] = $deliCfgLib->getValue('wms_delivery_verify_group');
            $groupFilter['disabled'] = 'false';
            $groupFilter['delivery_group'] = 'true';
            $orderTypes = $orderTypeObj->getList('*',$groupFilter);

            $filter = array(
                'status'=> 0,
                'process_status'=> 1,
                'disabled'=>'false',
                //'type'=>'normal',
            );

            $oBranch = app::get('ome')->model('branch');
            $is_super = kernel::single('desktop_user')->is_super();
            if (!$is_super) {
                $branch_ids = $oBranch->getBranchByUser(true);
                if ($branch_ids) {
                    $filter['branch_id'] = $branch_ids;
                }
            }

            $deliverys = $deliveryObj->getList('delivery_id,delivery_group',$filter);

            $deliveryGroup = array();
            foreach($orderTypes as $key => $type){
                $deliveryGroup[$type['tid']] = $type;
                $deliveryGroup[$type['tid']]['deliverys'] = array();
                $deliveryGroup[$type['tid']]['dCount'] = 0;
            }

            foreach($deliverys as $key => $value){
                if($value['delivery_group']>0 && $deliveryGroup[$value['delivery_group']]){
                    $deliveryGroup[$value['delivery_group']]['deliverys'][] = $value['delivery_id'];
                    $deliveryGroup[$value['delivery_group']]['dCount']++;
                }
                $deliveryAll[] = $value['delivery_id'];
            }

            /*
            $deliveryGroup[8388607] = array(
                'tid' => 8388607,
                'name' => '全部分组',
                'deliverys' => $deliveryAll,
                'dCount' => count($deliveryAll),
            );
            */

            $this->pagedata['num_available'] = is_array($deliveryAll) ? count($deliveryAll) : 0;
            $this->pagedata['deliveryGroup'] = $deliveryGroup;
            $this->pagedata['jsonDeliveryGroup'] = json_encode($deliveryGroup);

            /* 操作时间间隔 start */
            $lastGroupCalibration = app::get('wms')->getConf('lastGroupCalibration'); //上次获取订单信息(key为execTime表示执行时间，key为pageBn表示页面编号),
            $groupCalibrationIntervalTime = app::get('wms')->getConf('wms.groupCalibration.intervalTime'); //每次操作的时间间隔

            if(($lastGroupCalibration['execTime']+60*$groupCalibrationIntervalTime)<time()){
                $this->pagedata['is_allow'] = true;
            }else{
                $this->pagedata['is_allow'] = false;
            }
            $this->pagedata['lastGroupCalibrationTime'] = !empty($lastGroupCalibration['execTime']) ? date('Y-m-d H:i:s',$lastGroupCalibration['execTime']) : '';
            $this->pagedata['groupCalibrationIntervalTime'] = $groupCalibrationIntervalTime;
            $this->pagedata['currentTime'] = time();
            /* 操作时间间隔 end */

            $this->page("admin/delivery/process_group_check.html");
        }else{
            echo "未开启分组校验！";
        }
    }

    /**
     * @description 校验成功
     * @access public
     * @param void
     * @return void
     */
    public function check_pass()
    {
        $deliveryObj = app::get('wms')->model('delivery');
        $dlyBillMdl = app::get('wms')->model('delivery_bill');
        
        $deliveryBillLib = kernel::single('wms_delivery_bill');
        
        $pass = $_POST['pass'];
        if ($pass == 'false') {
            echo 'check fail!!!'; exit;
        }

        $checkType = $_POST['checkType'];
        $logi_no = $_POST['logi_no'];

        $deliveryBill = $dlyBillMdl->db_dump(['logi_no' => $logi_no]);
        $delivery_id = $deliveryBill ? $deliveryBill['delivery_id'] : 0;
        $bill_id = $deliveryBill ? $deliveryBill['b_id'] : 0;

        // [提货物流] delivery_model='pickup' 运单号后取，扫描的是发货单号
        if (empty($deliveryBill)) {
            $pickupDly = $deliveryObj->dump(array('delivery_bn' => $logi_no, 'delivery_model' => 'pickup'), 'delivery_id');
            if ($pickupDly) {
                $delivery_id = $pickupDly['delivery_id'];
                $deliveryBill = $dlyBillMdl->db_dump(array('delivery_id' => $delivery_id, 'type' => 1));
                $bill_id = $deliveryBill ? $deliveryBill['b_id'] : 0;
            }
        }

        //[同城配]商家配送支持配送员手机号搜索
        if(empty($deliveryBill) && strlen($logi_no) == 11){
            $deliveryInfo = $deliveryObj->dump(array('deliveryman_mobile'=>$logi_no, 'process_status'=>array(0,1)), '*');
            if($deliveryInfo){
                $delivery_id = $deliveryInfo['delivery_id'];
                
                $deliveryBill = $dlyBillMdl->db_dump(array('delivery_id'=>$delivery_id));
                $bill_id = $deliveryBill['b_id'];
            }
        }
        
        // 箱包裹
        $deliveryBillItems = app::get('wms')->model('delivery_bill_items')->getList('*', ['bill_id' => $bill_id]);
        $deliveryBillItems = array_column($deliveryBillItems, null, 'product_id');

        //参数赋值
        $dly = array(
            'logi_no' => $logi_no,
            'delivery_id' => $delivery_id,
        );

        //捡货绩效开始记录点
        foreach(kernel::servicelist('tgkpi.pick') as $o){
            if(method_exists($o,'begin_check')){
                $o->begin_check($delivery_id);
                $this->pagedata['tgkpi_status'] = 'true';
            }
        }

        //获取相关订单的备注信息，走接口
        $deliveryInfo = $deliveryObj->dump($delivery_id, 'outer_delivery_bn,ship_name,branch_id');
        $res = kernel::single('ome_extint_order')->getMemoByDlyId($deliveryInfo['outer_delivery_bn']);

        $this->pagedata['ship_name'] = $deliveryInfo['consignee']['name'];

        $order_bns = array();
        foreach ($res['mark_text'] as $k =>$v){
            if(!in_array($k, $order_bns)){
                $order_bns[] = $k;
            }
        }

        foreach ($res['custom_mark'] as $k =>$v){
            if(!in_array($k, $order_bns)){
                $order_bns[] = $k;
            }
        }

        foreach ($order_bns as $k => $order_bn){
            $markandtext[$k]['order_bn'] = $order_bn;
            $markandtext[$k]['_mark'] = isset($res['custom_mark'][$order_bn]) ? $res['custom_mark'][$order_bn] : '';
            $markandtext[$k]['_mark_text'] = isset($res['mark_text'][$order_bn]) ? $res['mark_text'][$order_bn] : '';
        }

        $this->pagedata['markandtext']  = $markandtext;

        # 基础物料名称显示方式(stock:后台,front:前台)
        $product_name_show_type = app::get('wms')->getConf('wms.delivery.check_show_type');
        $product_name_show_type = empty($product_name_show_type) ? 'stock' : $product_name_show_type;

        $goods = 0; $newItems = array();

        $basicMaterialLib    = kernel::single('material_basic_material');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        $dlyItemsSLObj    = app::get('wms')->model('delivery_items_storage_life');

        $items = $deliveryObj->getItemsByDeliveryId($delivery_id);
        $bm_id = '';
        foreach ($items as $k => $i)
        {
            // 箱包数
            if ($deliveryBillItems) {
                if (!$deliveryBillItems[$i['product_id']]){
                    continue;
                }
                $items[$k]['number'] = $i['number']     = $deliveryBillItems[$i['product_id']]['number'];
                $items[$k]['verify_num'] = $i['verify_num'] = $deliveryBillItems[$i['product_id']]['verify_num'];
            }

            $bm_id = $i['product_id'];
            //查询关联的条形码
            $barcode    = $basicMaterialBarcode->getBarcodeById($i['product_id']);
            $count += $i['number'];
            $goods ++;
            $verify_num += $i['verify_num'];
            $verify += $i['verify_num'];

            $items[$k]['barcode'] = trim($barcode);
            $items[$k]['bn'] = trim($items[$k]['bn']);
            
            $p    = $basicMaterialLib->getBasicMaterialExt($i['product_id']);
            
            $items[$k]['serial_number'] = $p['serial_number'];

            if($i['use_expire'] == 1){
                $item_expire_arr = $dlyItemsSLObj->getList('*',array('delivery_id'=>$delivery_id,'item_id'=>$i['item_id']));
                foreach($item_expire_arr as $item_expire){
                    $strkey = $item_expire['expire_bn'];

                    if($newItems[$strkey] && $newItems[$strkey]['bn'] !=''){
                        $newItems[$strkey]['number'] += $item_expire['number'];
                        $newItems[$strkey]['verify_num'] += $item_expire['verify_num'];
                    }else{
                        $newItems[$strkey] = array(
                            'bn' => $items[$k]['bn'],
                            'product_id' => $items[$k]['product_id'],
                            'product_name' => $items[$k]['product_name'],
                            'barcode' => $strkey,
                            'number' => $item_expire['number'],
                            'verify_num' => $item_expire['verify_num'],
                            'is_expire_bn' => 'true',
                            'serial_number' => $p['serial_number'],
                        );
                    }
                }
            }else{
                $strkey = $i['bn'];

                if($newItems[$strkey] && $newItems[$strkey]['bn'] !=''){
                    $newItems[$strkey]['number'] += $items[$k]['number'];
                    $newItems[$strkey]['verify_num'] += $items[$k]['verify_num'];
                }else{
                    $newItems[$strkey] = $items[$k];
                }
            }
        }

        $items = $newItems;
        //增加发货单校验显示前的扩展
        foreach(kernel::servicelist('ome.delivery') as $o){
            if(method_exists($o,'pre_check_display')){
                $o->pre_check_display($items);
            }
        }

        //增加日志
        $opObj = app::get('ome')->model('operation_log');
        $msg= "发货单开始校验";
        $opObj->write_log('delivery_check@wms', $delivery_id, $msg);

        if($product_name_show_type == 'stock') {
            $this->toGoodsName($items);
        }
        $serial['merge'] = app::get('ome')->getConf('ome.product.serial.merge');
        $serial['separate'] = app::get('ome')->getConf('ome.product.serial.separate');
        $this->pagedata['serial'] = $serial;

        $conf = app::get('wms')->getConf('wms.delivery.check');
        $this->pagedata['normal'] = 0;
        $this->pagedata['conf'] = $conf;
        $this->pagedata['count'] = $count;
        $this->pagedata['number'] = $verify;
        $this->pagedata['goodsNum'] = $goods;
        $this->pagedata['items'] = $items;
        $this->pagedata['dly'] = $dly;
        $this->pagedata['verify_num'] = $verify_num;
        $this->pagedata['remain'] = $count - $verify_num;
        $this->pagedata['userName'] = kernel::single('desktop_user')->get_name();
        $this->pagedata['date'] = date('Y-m-d H:i');
        $this->pagedata['checkType'] = $checkType;
        $this->pagedata['bh_id'] = $deliveryInfo['branch_id'];

        // 特殊扫码配置
        $materialSpecConf = app::get('material')->model('basic_material_conf_special');
        $specialConf = $materialSpecConf->getList('openscan,fromposition,toposition', array('bm_id'=>$bm_id),0,1);

        $this->pagedata['openscan'] = $specialConf[0]['openscan'];
        $this->pagedata['fromposition'] = $specialConf[0]['fromposition'];
        $this->pagedata['toposition'] = $specialConf[0]['toposition'];

        //获取
        if (app::get('tgkpi')->is_installed()) {
            $pickInfo = kernel::database()->selectrow("SELECT pick_owner FROM sdb_tgkpi_pick WHERE delivery_id={$delivery_id}");
            if (!empty($pickInfo['pick_owner'])){
                $pickUser = app::get('desktop')->model('users')->dump(array('op_no'=>$pickInfo['pick_owner']), 'name');
                if ($pickUser) {
                    $this->pagedata['picktName']= $pickUser['name'];
                }
            }
        }

        if ($checkType == 'all') {
            $view = 'admin/delivery/delivery_checkout2.html';
            $delivery_weight =  app::get('wms')->getConf('wms.delivery.weight'); #发货配置，开启称重
            $check_delivery = app::get('wms')->getConf('wms.delivery.check_delivery'); #发货配置，检验完即发货

            #开启称重时，不能使用校验完即发货功能
            if($delivery_weight == 'on'){
                $check_delivery = 'off';
            }
            if(!isset($check_delivery)||empty($check_delivery)){
                $check_delivery = 'off';
            }
            $this->pagedata['check_delivery'] = $check_delivery;
            #逐单发货，如果不称重，且,开启了校验后直接发货
            if($delivery_weight == 'off' && $check_delivery == 'on'){
                $minWeight = app::get('wms')->getConf('wms.delivery.minWeight');
                $this->pagedata['weight'] = $minWeight;
                $this->pagedata['check_delivery'] = $check_delivery;
                #校验后,直接发货的view页面
                $view = 'admin/delivery/delivery_checkout3.html';
            }
        } else {
            $view = 'admin/delivery/delivery_checkout.html';
        }

        $this->display($view);
    }

    /**
     * 校验发货单是否可打印
     *
     */
    function check(){
        if ($_POST['pass'] == 'true') {
            $this->check_pass();exit;
        }
        
        $msg = '';
        $autohide = array('autohide'=>3000);
        $this->begin('index.php?app=wms&ctl=admin_check');
        $checkType = $_POST['checkType'];
        $logi_no = $_POST['delivery']['logi_no'];

        # barcode:逐单 all:整单
        if (!in_array($checkType,array('barcode','all'))) {
            $this->end(false, '参数传递错误', '', $autohide);
        }

        $dlyCheckLib = kernel::single('wms_delivery_check');

        $check_result = $dlyCheckLib->checkAllow($logi_no, $msg, $checkType);
        if (!$check_result){
            $this->end(false, $msg, '', $autohide);
        }else{
            $this->end(true,'快递单合法，开始校验。','',array('pass'=>'true','checkType'=>$checkType,'logi_no'=>$logi_no));
        }
    }

    /**
     *
     * 批量校验的校验验证检查方法
     */
    function batchCheck(){
        $msg = '';
        $ids = urldecode($_POST['delivery_id']);
        if (empty($ids)){
            $tmp = array(array('bn'=>'*','msg'=>'请扫描快递单号'));
            echo json_encode($tmp);die;
        }

        $deliveryObj  = app::get('wms')->model('delivery');
        $dlyCheckLib = kernel::single('wms_delivery_check');
        $delivery_ids = array_unique(explode(',', $ids));
        $tmp = array();
        foreach($delivery_ids as $logi_no){
            if(!$logi_no)continue;

            $delivery = $dlyCheckLib->checkAllow($logi_no, $msg, 'batch');
            if (!$delivery) {
                $tmp[] = array('bn'=>$logi_no,'msg'=>$msg);
                continue;
            }
        }

        if ($tmp){ echo json_encode($tmp);die;}

        echo "";
    }

    private function toGoodsName(& $items)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');

        $bn_string = '';
        foreach($items as $k=>$v)
        {
            $product     = $basicMaterialObj->dump(array('material_bn'=>$items[$k]['bn']), 'material_name');
            if($product)
            {
                $items[$k]['product_name'] = $product['material_name'];
            }
        }
    }

    /**
     *
     * 逐单校验/整单校验提交处理方法
     */
    function doCheck()
    {
        $dlyCheckLib = kernel::single('wms_delivery_check');
    
        $errmsg = '';
        $autohide = array('autohide'=>2000);
        $checkType = in_array($_POST['checkType'],array('barcode','all')) ? $_POST['checkType'] : 'barcode';
        $this->begin('index.php?app=wms&ctl=admin_check&act=index&type='.$checkType);
        if (empty($_POST['delivery_id'])){
            $this->end(false, '发货单ID传入错误', '', $autohide);
        }
        if ($_POST['logi_no'] == ''){
            $this->end(false, '请扫描快递单号', '', $autohide);
        }

        foreach(kernel::servicelist('ome.delivery') as $o){
            if(method_exists($o,'pre_docheck')){
                $message = "";
                $result = $o->pre_docheck($_POST,$message);
                if(!$result){
                    $this->end(false, $message, '', $autohide);
                }
            }
        }

        $dly_id   = $_POST['delivery_id'];
        $count    = $_POST['count'];
        $number   = $_POST['number'];
        $logi_no  = $_POST['logi_no'];

        //检查订单的当前状态
        if(!$dlyCheckLib->checkOrderStatus($dly_id, true, $errmsg)){
            $this->end(false, $errmsg, '', $autohide);
        }

        if ($count == 0 || $number == 0){
            $this->end(false, '对不起，校验提交的数据错误', '', $autohide);
        }

        $deliveryObj  = app::get('wms')->model('delivery');
        $dly          = $deliveryObj->dump($dly_id,'*', array('delivery_items'=>array('*')));

        //filter
        $filter = array(
            'logi_no' => $logi_no,
            'delivery_id' => $dly_id,
        );
        
        //[同城配]商家配送 / [提货物流]
        if($dly['delivery_model'] == 'seller' || $dly['delivery_model'] == 'pickup'){
            $filter = array(
                'delivery_id' => $dly_id,
            );
        }
        
        //检查运单号是否属于同一个处理的发货单
        $deliveryBill = app::get('wms')->model('delivery_bill')->db_dump($filter);
        if (!$deliveryBill){
            $this->end(false, '扫描的快递单号与系统中的快递单号不对应', '', $autohide);
        }

        $deliveryBillItemMdl = app::get('wms')->model('delivery_bill_items');

        if ($dly['logi_number'] > '1' && $_POST['checkType'] == 'all'){
            $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');

            $deliveryBillItems = $deliveryBillItemMdl->getList('*', ['bill_id' => $deliveryBill['b_id']]);
            foreach ($deliveryBillItems as $item) {
                $barcode    = $basicMaterialBarcode->getBarcodeById($item['product_id']);

                $_POST['number_'. $barcode] = $item['number'];
            }
        }

        //合计发货单明细的总数
        // $total = 0;
        // foreach ($dly['delivery_items'] as $i){
        //     $total += $i['number'];
        // }

        $opObj        = app::get('ome')->model('operation_log');
        $deliveryLib = kernel::single('wms_delivery_process');

        // 包裹校验
        if (!$deliveryLib->verifyPackage($deliveryBill['b_id'], $_POST, $_POST['checkType'])){
            $this->end(false, '包裹校验失败', '', $autohide);
        }

        $checkFinish = false;

        // 判断发货单是否全部校验完成
        if ($dly['logi_number'] == '1' && $count == $number){
            $checkFinish = true;
        }


        $packageList =  app::get('wms')->model('delivery_bill')->getList('b_id', [
            'delivery_id' => $dly_id,
        ]);
        $packageId = $packageList ? array_column($packageList, 'b_id') : [0];
        if ($dly['logi_number'] > '1' && 0 == $deliveryBillItemMdl->count(['bill_id' => $packageId, 'filter_sql' => 'number!=verify_num'])) {
            $checkFinish = true;
        }

        if ($checkFinish) {
            //对发货单详情进行校验完成处理
            if ($deliveryLib->verifyDelivery($dly_id)){
                //增加发货单校验把保存后的扩展
                foreach(kernel::servicelist('ome.delivery') as $o){
                    if(method_exists($o,'after_docheck')){
                        $data = $_POST;
                        $o->after_docheck($data);
                    }
                }

                $this->end(true, '发货单校验完成');
            }else {
                $this->end(false, '发货单校验未完成，请重新校验', 'index.php?app=wms&ctl=admin_check', $autohide);
            }
        } else {
            //保存部分校验结果
            $flag = $deliveryLib->verifyItemsByDeliveryIdFromPost($dly_id);
            if ($flag){
                $opObj->write_log('delivery_check@wms', $dly_id, '发货单部分检验数据保存完成');
                
                if ($count != $number){
                    $autohide["logi_no"]        = $logi_no;
                    $autohide["checkType"]      = $checkType;
                    $autohide["pass"]           = "true";
                    $autohide["part_check"]     = "true";
                    $this->end(true, '发货单部分检验数据保存完成', '', $autohide);
                } else {
                    $this->end(true, '发货单部分检验数据保存完成');
                }

                
            }else {
                $this->end(false, '发货单校验未完成，请重新校验', '', $autohide);
            }
        }
    }

    function ajaxDoGroupCheck(){
        $tmp = explode('||', $_POST['ajaxParams']);
        $group = $tmp[0];
        $delivery = explode(';', $tmp[1]);
        $msg = '';
        $failInfo = array();
        
        if($delivery && count($delivery)>0){
            /* 执行时间判断 start */
            $pageBn = intval($_POST['pageBn']);
            $lastGroupCalibration = app::get('wms')->getConf('lastGroupCalibration'); //上次获取订单信息(key为execTime表示执行时间，key为pageBn表示页面编号),
            $groupCalibrationIntervalTime = app::get('wms')->getConf('wms.groupCalibration.intervalTime'); //每次操作的时间间隔

            if($pageBn !=$lastGroupCalibration['pageBn'] && ($lastGroupCalibration['execTime']+60*$groupCalibrationIntervalTime)>time()){
                echo json_encode(array('total' => count($delivery), 'succ' => 0, 'fail' => count($delivery), 'failInfo'=>array('执行时间不合法')));
                exit;
            }
            if($pageBn !=$lastGroupCalibration['pageBn'] && $pageBn<$lastGroupCalibration['execTime']){
                echo json_encode(array('total' => count($delivery), 'succ' => 0, 'fail' => count($delivery), 'failInfo'=>array('提交参数过期')));
                exit;
            }

            //记录本次获取订单时间
            $currentGroupCalibration = array(
                'execTime'=>time(),
                'pageBn'=>$pageBn,
            );
            app::get('ome')->setconf('lastGroupCalibration',$currentGroupCalibration);
            /* 执行时间判断 end */

            $deliveryObj = app::get('wms')->model('delivery');
            $deliveryLib = kernel::single('wms_delivery_process');
            $dlyCheckLib = kernel::single('wms_delivery_check');
            $dlyBillObj = kernel::single('wms_delivery_bill');

            $filter = array(
                'delivery_id'=>$delivery,
            );
            $deliverys = $deliveryObj->getList('delivery_id,delivery_bn,delivery_model',$filter);
            $succ = 0;
            $fail = 0;
            foreach($deliverys as $value){
                if (isset($value['delivery_model']) && $value['delivery_model'] === 'pickup'){
                    $fail++;
                    $failInfo[] = $value['delivery_bn'] . '(提货单不支持分组校验)';
                    continue;
                }
                $logi_no = $dlyBillObj->getPrimaryLogiNoById($value['delivery_id']);
                $checkInfo = $dlyCheckLib->checkAllow($logi_no, $msg, 'group', true);
                if ($checkInfo){
                    if($deliveryLib->verifyDelivery($value['delivery_id'],true)){
                        $succ++;
                    }else{
                        $fail++;
                        $failInfo[] = $value['delivery_bn'];
                    }
                }else{
                    $fail++;
                    $failInfo[] = $value['delivery_bn'];
                }
                usleep(200000);
            }
            echo json_encode(array('total' => count($delivery), 'succ' => $succ, 'fail' => $fail, 'failInfo'=>$failInfo));
        }else{
            echo json_encode(array('total' => 0, 'succ' => 0, 'fail' => 0, 'failInfo'=>$failInfo));
        }
    }

    /**
     *
     * 获取发货记录历史
     */
    function batchConsignLog(){
        $blObj  = app::get('wms')->model('batch_log');
        $batchLogLib = kernel::single('wms_batch_log');

        $dayBegin = mktime(0,0,0,date("m"),date("d"),date("Y"));//当天开始时间戳
        $dayEnd = mktime(23,59,59,date("m"),date("d"),date("Y"));//当天结束时间戳
        $blResult = $blObj->getList('*', array('log_type'=>'check','createtime|than'=>$dayBegin,'createtime|lthan'=>$dayEnd), 0, -1,'createtime desc');

        foreach($blResult as $k=>$v){
            $blResult[$k]['status_value'] = $batchLogLib->getStatus($v['status']);

            $blResult[$k]['fail_number'] = $v['fail_number'];
            $blResult[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
        }

        if($blResult){
            echo json_encode($blResult);
        }
    }

    /**
     *
     * 更新处理中发货记录值
     */
    function updateBatchCheckLog(){
        $log_id = $_POST['log_id'];
        if($log_id){
            $status="'0','2'";
            $batchLogLib = kernel::single('wms_batch_log');
            // 处理log_id参数：使用explode分割字符串为数组
            $log_id_param = explode(',', $log_id);
            $blResult = $batchLogLib->get_List('check',$log_id_param,$status);
            foreach($blResult as $k=>$v){
                $blResult[$k]['status_value'] = $batchLogLib->getStatus($v['status']);
                $blResult[$k]['fail_number'] = $v['fail_number'];
                $blResult[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
            }

            echo json_encode($blResult);
        }

    }

    /**
     *
     * 保存批量发货至记录队列表中
     */
    function saveBatchCheck(){
        $goto_url = 'index.php?app=wms&ctl=admin_check&act=batchIndex';
        $ids = $_POST['delivery_id'];
        $delivery_ids = explode(',', $ids);
        $delivery_ids = array_filter($delivery_ids);

        if ( !$delivery_ids ) {
            $this->splash('success',$goto_url ,'快递单列表为空！');
        }

        $batch_number = count($delivery_ids);
        $blObj  = app::get('wms')->model('batch_log');

        $bldata = array(
            'op_id' => kernel::single('desktop_user')->get_id(),
            'op_name' => kernel::single('desktop_user')->get_name(),
            'createtime' => time(),
            'batch_number' => $batch_number,
            'log_type'=>'check',
            'log_text'=>serialize($delivery_ids)
        );
        $result = $blObj->save($bldata);

        //校验任务加队列
        $push_params = array(
            'data' => array(
                'log_text' => $bldata['log_text'],
                'log_id' => $bldata['log_id'],
                'task_type' => 'autochk'
            ),
            'url' => kernel::openapi_url('openapi.autotask','service')
        );
        kernel::single('taskmgr_interface_connecter')->push($push_params);

        $this->splash('success',$goto_url ,'已提交至队列');
    }

    function batch_log_detail(){
        $log_id = $_GET['log_id'];
        $filter = array('log_id'=>$log_id);
        if ($_GET['status']) {
            $filter['status'] = $_GET['status'];
        }

        $bldObj  = app::get('wms')->model('batch_detail_log');
        $bldData = $bldObj->getList('*',$filter,0,-1);
        foreach($bldData as $k=>$v){
            $bldData[$k]['createtime'] = date('Y-m-d H:i:s',$v['createtime']);
        }
        $this->pagedata['bldData'] = $bldData;
        $this->display('admin/delivery/batch_chklog_detail.html');
    }
}
