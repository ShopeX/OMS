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

class wms_ctl_admin_consign extends desktop_controller{

    var $name = "发货处理";
    var $workground = "wms_delivery";

    public function _views()
    {

        $mdl_order = app::get('ome')->model('orders');

        # 未发货分两部分：sync=none+线上店铺 OR ship_status=0+线下店铺
        $shops = app::get('ome')->model('shop')->getList('shop_id,node_id');

        $bindShop = $unbindShop = array();
        foreach ($shops as $key=>$shop) {
            if ($shop['node_id']) {
                $bindShop[] = $shop['shop_id'];
            } else {
                $unbindShop[] = $shop['shop_id'];
            }
        }
        $sync_none_filter = array('ship_status' => '0');
        if ($bindShop && $unbindShop) {
            $sync_none_filter['filter_sql'] = '(({table}sync="none" AND {table}shop_id in("'.implode('","',$bindShop).'"))'.' OR '.'({table}ship_status="0" AND shop_id in("'.implode('","',$unbindShop).'")))';
        } elseif ($bindShop) {
            $sync_none_filter['filter_sql'] = '{table}sync="none" AND {table}shop_id in("'.implode('","',$bindShop).'")';
        } elseif ($unbindShop) {
            $sync_none_filter['filter_sql'] = '{table}ship_status="0" AND {table}shop_id in("'.implode('","',$unbindShop).'")';
        }

        $base_filter = $this->getFilters();

        $sub_menu[0] = array('label' => app::get('base')->_('全部'), 'filter' => $base_filter, 'optional' => false);
        $sub_menu[1] = array('label' => app::get('base')->_('待发货'), 'filter' => array_merge($base_filter, $sync_none_filter), 'optional' => false);
        $sub_menu[2] = array('label' => app::get('base')->_('回写未发起'),'filter' => array_merge($base_filter,array('createway' => 'matrix','sync' => 'none' ,'ship_status' => '1')),'optional' => false);
        $sub_menu[3] = array('label' => app::get('base')->_('发货中'), 'filter' => array_merge($base_filter, array('createway'=>'matrix','sync' => 'run')), 'optional' => false);
        $sub_menu[4] = array('label' => app::get('base')->_('发货失败'), 'filter' => array_merge($base_filter, array('createway'=>'matrix','sync' => 'fail','sync_fail_type'=>array('none','unbind'))), 'optional' => false);
        $sub_menu[5] = array('label' => app::get('base')->_('京东发货失败'), 'filter' => array_merge($base_filter, array('createway'=>'matrix','sync' => 'fail','shop_type'=>'360buy','sync_fail_type' => array('none','unbind'))), 'optional' => false);
        $sub_menu[6] = array('label' => app::get('base')->_('回写参数错误'), 'filter' => array_merge($base_filter, array('createway' => 'matrix','sync' => 'fail','sync_fail_type' => 'params')), 'optional' => false);
        $sub_menu[7] = array('label' => app::get('base')->_('前端已发货'), 'filter' => array_merge($base_filter, array('createway' => 'matrix','sync' => 'fail','sync_fail_type' => 'shipped')), 'optional' => false);
        $sub_menu[8] = array('label' => app::get('base')->_('发货成功'), 'filter' => array_merge($base_filter, array('createway' => 'matrix','sync' => 'succ')), 'optional' => false);
        $sub_menu[9] = array('label' => app::get('base')->_('不予回写'),'filter' => array_merge($base_filter, array('createway' => array('local','import','after'))),'optional' => false);

        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $mdl_order->count($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=wms&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&flt=' . $_GET['flt'] . '&view=' . $k . $s;
        }

        return $sub_menu;
    }

    /**
     *
     * 逐单发货的入口展示页
     */
    function index(){
        $weightSet = app::get('wms')->getConf('wms.delivery.weight');
        $minWeight = app::get('wms')->getConf('wms.delivery.minWeight');
        $deliveryObj  = app::get('wms')->model('delivery');
        $this->pagedata['weightSet'] = $weightSet;
        $this->pagedata['minWeight'] = $minWeight;

        //称重报警max_weightwarn，weightpercent,min_weight,warnproblem_package
        $weightWarn = app::get('wms')->getConf('wms.delivery.weightwarn');
        $min_weightwarn = app::get('wms')->getConf('wms.delivery.min_weightwarn');
        $max_weightwarn = app::get('wms')->getConf('wms.delivery.max_weightwarn');
        $minpercent = app::get('wms')->getConf('wms.delivery.minpercent');
        $maxpercent = app::get('wms')->getConf('wms.delivery.maxpercent');
        $problem_package = app::get('wms')->getConf('wms.delivery.problem_package');

        $this->pagedata['weightWarn'] = $weightWarn;
        $this->pagedata['max_weightwarn'] = $max_weightwarn;
        $this->pagedata['min_weightwarn'] = $min_weightwarn;
        $this->pagedata['minpercent'] = $minpercent;
        $this->pagedata['maxpercent'] = $maxpercent;
        $this->pagedata['problem_package'] = $problem_package;

        $numShow = app::get('wms')->getConf('wms.delivery.consignnum.show');
        if ($numShow == 'false' || (cachecore::fetch('quick_access') !== false)) {
            $this->pagedata['deliverynum'] = '未知';
        } else {
            $this->pagedata['deliverynum'] = $deliveryObj->countNoProcessDeliveryBill();
        }

        $this->pagedata['logi_no_position'] = (0==($pos=app::get('wms')->getConf('wms.delivery.logi'))) ? 'up' : 'down';

        $this->page("admin/delivery/process_consign_index.html");
    }

    /**
     *
     * 批量发货的入口展示页
     */
    function batch(){
        $stock_confirm= app::get('ome')->getConf('purchase.stock_confirm');
        $stock_cancel= app::get('ome')->getConf('purchase.stock_cancel');
        $this->pagedata['stock_confirm'] = $stock_confirm;
        $this->pagedata['stock_cancel'] = $stock_cancel;
        $deliveryObj  = app::get('wms')->model('delivery');
        $this->pagedata['deliverynum'] = $deliveryObj->countNoProcessDeliveryBill();
        $this->page("admin/delivery/process_consign_batch.html");
    }

    /**
     *
     * 分组发货的入口展示页
     */
    function group_consign(){
        $deliCfgLib = kernel::single('wms_delivery_cfg');
        $consign = $deliCfgLib->getValue('consign');
        if($consign == 1){
            $deliveryObj = app::get('wms')->model('delivery');
            $orderTypeObj = app::get('omeauto')->model('order_type');
            $groupFilter['tid'] = $deliCfgLib->getValue('wms_delivery_consign_group');
            $groupFilter['disabled'] = 'false';
            $groupFilter['delivery_group'] = 'true';
            $orderTypes = $orderTypeObj->getList('*',$groupFilter);

            $filter = array(
                'disabled' => 'false',
                'process_status' => 3,
                'status' => 0
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
            /*$deliveryGroup[8388607] = array(
                'tid' => 8388607,
                'name' => '全部分组',
                'deliverys' => $deliveryAll,
                'dCount' => count($deliveryAll),
            );*/

            $this->pagedata['num'] = $deliveryObj->countNoProcessDelivery();
            $this->pagedata['deliveryGroup'] = $deliveryGroup;
            $this->pagedata['jsonDeliveryGroup'] = json_encode($deliveryGroup);

            /* 操作时间间隔 start */
            $lastGroupDelivery = app::get('wms')->getConf('lastGroupDelivery'); //上次获取订单信息(key为execTime表示执行时间，key为pageBn表示页面编号),
            $groupDeliveryIntervalTime = app::get('wms')->getConf('wms.groupDelivery.intervalTime'); //每次操作的时间间隔

            if(($lastGroupDelivery['execTime']+60*$groupDeliveryIntervalTime)<time()){
                $this->pagedata['is_allow'] = true;
            }else{
                $this->pagedata['is_allow'] = false;
            }
            $this->pagedata['lastGroupDeliveryTime'] = !empty($lastGroupDelivery['execTime']) ? date('Y-m-d H:i:s',$lastGroupDelivery['execTime']) : '';
            $this->pagedata['groupDeliveryIntervalTime'] = $groupDeliveryIntervalTime;
            $this->pagedata['currentTime'] = time();
            /* 操作时间间隔 end */

            $this->page("admin/delivery/process_group_consign.html");
        }else{
            echo "未开启分组发货！";
        }
    }

    /**
     *
     * 发货前的发货单相关信息检查
     */
    function batchCheck(&$rs = false){
        $logi_nos        = urldecode($_POST['delivery_id']);
        $weight  = $_POST['weight'];
        if (empty($logi_nos)){
            $tmp = array(array('bn'=>'*','msg'=>'请扫描快递单号'));
            echo json_encode($tmp);
            die;
        }

        $dlyCheckLib = kernel::single('wms_delivery_check');

        // 先做系统业务校验（consignAllow），通过后再针对 pickup 且无 logi_no 时调官方提货
        if ($_GET['delivery_type'] == 'single'){
            $return_error = $dlyCheckLib->consignAllow('', $logi_nos, $weight);
            if ($return_error){
                $tmp = array('status'=>'error','msg'=>$return_error);
                echo json_encode($tmp);
                die;
            }
            $pickup_err=null;
            $pickup_err = $this->_batchCheckPickupOfficial($logi_nos);
            if ($pickup_err !== null) {
                $tmp = array('status'=>'error','msg'=>$pickup_err);
                echo json_encode($tmp);
                die;
            }
        }else{
            $logi_no_arr = array_unique(explode(',', $logi_nos));
            $tmp = array();
            $delivery_list = array();

            if ($logi_no_arr){
                foreach ($logi_no_arr as $v){
                    //过滤空值
                    if (empty($v)) continue;

                    //验证
                    $return_error = $dlyCheckLib->consignAllow('', $v, false);
                    if ($return_error) {
                        $tmp[] = array('bn'=>$v,'msg'=>$return_error);
                        continue;
                    }
                }
            }
        }

        if (!empty($tmp)){
            echo json_encode($tmp); die;
        }

        $rs = true;#发货判断完成的标示,这个对校验完成即发货有用
        if ($_GET['delivery_type'] == 'single') {
            $dlyObj = app::get('wms')->model('delivery');
            $deliveryBillLib = kernel::single('wms_delivery_bill');
            $delivery_id = $deliveryBillLib->getDeliveryIdByPrimaryLogi($logi_nos);
            if (empty($delivery_id)) {
                $delivery_id = $deliveryBillLib->getDeliveryIdBySecondaryLogi($logi_nos);
            }
            if (empty($delivery_id)) {
                $pickupDly = $dlyObj->dump(array('delivery_bn' => $logi_nos, 'delivery_model' => 'pickup'), 'delivery_id');
                if (!empty($pickupDly)) {
                    $delivery_id = $pickupDly['delivery_id'];
                }
            }
            $is_pickup = false;
            if ($delivery_id) {
                $dly = $dlyObj->dump(array('delivery_id' => $delivery_id), 'delivery_model');
                $is_pickup = (isset($dly['delivery_model']) && $dly['delivery_model'] === 'pickup');
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('status' => 'ok', 'is_pickup' => $is_pickup));
            return;
        }
        echo "";
    }

    /**
     * pickup 单：仅当 logi_no 为空时调官方提货，已有 logi_no 则不请求；失败返回错误文案（兼容 PHP5.2，不用闭包）
     * @param string $scan_val 扫描值（对应界面「输入快递单号」/ POST delivery_id，可为发货单号）
     * @return string|null 失败时返回错误信息，否则 null
     */
    private function _batchCheckPickupOfficial($scan_val)
    {
        $deliveryBillLib = kernel::single('wms_delivery_bill');
        $dlyObj = app::get('wms')->model('delivery');
        $dlyBillObj = app::get('wms')->model('delivery_bill');

        $delivery_id = $deliveryBillLib->getDeliveryIdByPrimaryLogi($scan_val);
        if ($delivery_id === null) {
            $delivery_id = $deliveryBillLib->getDeliveryIdBySecondaryLogi($scan_val);
        }
        if ($delivery_id === null) {
            $pickupDly = $dlyObj->dump(array('delivery_bn' => $scan_val, 'delivery_model' => 'pickup'), 'delivery_id');
            if ($pickupDly) {
                $delivery_id = $pickupDly['delivery_id'];
            }
        }
        if (empty($delivery_id)) {
            return null;
        }
        $dly = $dlyObj->dump(array('delivery_id' => $delivery_id), 'delivery_id,delivery_model');
        if (empty($dly) || !isset($dly['delivery_model']) || $dly['delivery_model'] !== 'pickup') {
            return null;
        }
        $bill = $dlyBillObj->db_dump(array('delivery_id' => $delivery_id, 'type' => 1), 'logi_no');
        // 已有 logi_no 则不请求官方提货，避免重复调用
        if (!empty($bill['logi_no'])) {
            return null;
        }
        $ret = kernel::single('wms_logistics')->officialPickup($delivery_id);
        if (is_array($ret) && isset($ret['rsp']) && $ret['rsp'] === 'fail') {
            return isset($ret['msg']) ? $ret['msg'] : '官方提货失败';
        }
        return null;
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
        $this->display('admin/delivery/batch_log_detail.html');
    }

	/**
     * 批量发货确认
     * 显示批量发货订单中有退款申请或已退款的订单，让管理员确认是否发货或不发货
     * @param json $data
     * @return null
     */
    function batch_delivery_confirm($data=null){
        $this->pagedata['delivery_type'] = $_GET['delivery_type'];
        $this->display('admin/order/batch_delivery_confirm.html');
    }

    /**
     * 发货处理
     *
     */
    function consign($is_from_check = false){
        if($is_from_check){
            $redirct_url = "index.php?app=wms&ctl=admin_check&act=index&type=all";
        }else{
            $redirct_url = "index.php?app=wms&ctl=admin_consign";
        }
        $this->begin($redirct_url);

        $logi_no = $_POST['logi_no'];
        $weight  = $_POST['weight'];

        $dlyObj = app::get('wms')->model('delivery');
        $dlyBillObj = app::get('wms')->model('delivery_bill');
        $deliveryBillLib = kernel::single('wms_delivery_bill');
        $opObj = app::get('ome')->model('operation_log');
        $wmsCommonLib = kernel::single('wms_common');
        $dlyProcessLib = kernel::single('wms_delivery_process');

        $primary = false;
        $secondary = false;
        
        //如果没有发货单信息，则根据物流单号识别是主单还是次单,并获取相关信息
        $delivery_id = $deliveryBillLib->getDeliveryIdByPrimaryLogi($logi_no);
        
        //[同城配]商家配送支持配送员手机号搜索
        if(empty($delivery_id) && strlen($logi_no) == 11){
            $deliveryInfo = $dlyObj->dump(array('deliveryman_mobile'=>$logi_no, 'process_status'=>array(2,3)), '*');
            if($deliveryInfo){
                $delivery_id = $deliveryInfo['delivery_id'];
                
                $dlyBillInfo = $dlyBillObj->db_dump(array('delivery_id'=>$delivery_id), '*');
                $logi_no = $dlyBillInfo['logi_no'];
            }
        }

        // [提货物流] delivery_model='pickup' 运单号后取，扫描的是发货单号
        if (empty($delivery_id)) {
            $pickupDly = $dlyObj->dump(array('delivery_bn' => $logi_no, 'delivery_model' => 'pickup'), 'delivery_id');
            if ($pickupDly) {
                $delivery_id = $pickupDly['delivery_id'];
            }
        }

        if(!is_null($delivery_id)){
            $primary = true;
            $dly = $dlyObj->dump(array('delivery_id' => $delivery_id),'*',array('delivery_items'=>array('*')));
        }else{
            $delivery_id = $deliveryBillLib->getDeliveryIdBySecondaryLogi($logi_no);
            if(!is_null($delivery_id)){
                $secondary = true;
                $dly = $dlyObj->dump(array('delivery_id' => $delivery_id),'*',array('delivery_items'=>array('*')));
            }
        }

        $logi_number = $dly['logi_number'];
        $delivery_logi_number =$dly['delivery_logi_number'];

        // 确认出库成功后用于触发的快递单打印（仅 delivery_model=pickup 时返回，前端将拉取 toPrintExpre 同源数据并直接触发菜鸟打印）
        $consignExtra = (isset($dly['delivery_model']) && $dly['delivery_model'] === 'pickup')
            ? array(
                'print_delivery_id' => $dly['delivery_id'],
                'print_logi_id' => $dly['logi_id'],
            )
            : array();

        //检查前端订单是否退款,原有逻辑是否需要?

        //danny_freeze_stock_log
        define('FRST_TRIGGER_OBJECT_TYPE','发货单：逐单发货');
        define('FRST_TRIGGER_ACTION_TYPE','ome_ctl_admin_consign：consign');

        //报警发货处理
        /*
        if($_POST['warn_status']=='1'){
            $opObj->write_log('delivery_weightwarn@wms', $dly["delivery_id"],'物流单号:'.$logi_no.',仍然发货（称重为：'.$weight.'g）');
        }
		*/

        //获取物流费用
        $area = $dly['consignee']['area'];
        $arrArea = explode(':', $area);
        $area_id = $arrArea[2];
        $delivery_cost_actual = $wmsCommonLib->getDeliveryFreight($area_id,$dly['logi_id'],$weight);

        //如果是次物流单号
        if($secondary){
            $data = array(
                'status'=>'1',
                'weight'=>$weight,
                'delivery_cost_actual'=>$delivery_cost_actual,
                'delivery_time'=>time(),
                'type' => 2,
            );
            $filter = array('logi_no'=>$logi_no);
            $dlyBillObj->update($data,$filter);

            $logstr = '快递单号:'.$logi_no.' 发货';
            $opObj->write_log('delivery_bill_express@wms', $dly["delivery_id"], $logstr);

            if(($logi_number==$delivery_logi_number)&&$dly['status'] != 3){
                if ($dlyProcessLib->consignDelivery($dly['delivery_id'])){
                     $this->_endConsignSuccess($consignExtra);
                }else {
                     $msg['delivery_bn'] = $dly['delivery_bn'];
                     $this->end(false, '发货未完成', '', array('msg'=>$msg));
                }
            }else{
                $data = array('delivery_logi_number'=>$delivery_logi_number+1,'weight'=>$dly['weight']+$weight,'delivery_cost_actual'=>$dly['delivery_cost_actual']+$delivery_cost_actual);
                $filter = array('delivery_id'=>$dly['delivery_id']);
                $dlyObj->update($data,$filter);

                if($logi_number==($delivery_logi_number+1)){
                     if ($dlyProcessLib->consignDelivery($dly['delivery_id'])){
                          $this->_endConsignSuccess($consignExtra);
                     }else {
                          $msg['delivery_bn'] = $dly['delivery_bn'];
                          $this->end(false, '发货未完成', '', array('msg'=>$msg));
                     }
                }else{
                    $this->_endConsignSuccess($consignExtra);
                }
            }
        }else{
            //判断这个主物流单有没有对应的次物流单,等于1的时候只有一个包裹单
            if($logi_number == 1){
                if(($logi_number==$delivery_logi_number)&&$dly['status'] != 3){
                     if ($dlyProcessLib->consignDelivery($dly['delivery_id'])){
                        $this->_endConsignSuccess($consignExtra);
                     }else {
                        $msg['delivery_bn'] = $dly['delivery_bn'];
                        $this->end(false, '发货未完成', '', array('msg'=>$msg));
                     }
                }else{
                    $data = array(
                        'status'=>'1',
                        'weight'=>$weight,
                        'delivery_cost_actual'=>$delivery_cost_actual,
                        'delivery_time'=>time(),
                        'type' => 1,
                    );
                    $filter = array('logi_no'=>$logi_no);
                    $dlyBillObj->update($data,$filter);

                    $data = array('delivery_logi_number'=>$delivery_logi_number+1,'weight'=>$dly['weight']+$weight,'delivery_cost_actual'=>$dly['delivery_cost_actual']+$delivery_cost_actual);
                    $filter = array('delivery_id'=>$dly['delivery_id']);
                    $dlyObj->update($data,$filter);

                    if ($dlyProcessLib->consignDelivery($dly['delivery_id'])){
                        $this->_endConsignSuccess($consignExtra);
                    }else {
                        $msg['delivery_bn'] = $dly['delivery_bn'];
                        $this->end(false, '发货未完成', '', array('msg'=>$msg));
                    }
                }
            }else{
                //如果存在子物流单
                //计算已经发货的子物流单、总共的物流单
                //1,查询实际的发货数量，和总物流数量
                if(($logi_number > $delivery_logi_number)){
                    $data = array(
                        'status'=>'1',
                        'weight'=>$weight,
                        'delivery_cost_actual'=>$delivery_cost_actual,
                        'delivery_time'=>time(),
                        'type' => 1,
                    );
                    $filter = array('logi_no'=>$logi_no);
                    $dlyBillObj->update($data,$filter);

                    $data = array(
    					'delivery_logi_number'=>$delivery_logi_number+1,
    					'weight'=>$dly['weight']+$weight,
    					'delivery_cost_actual'=>$dly['delivery_cost_actual']+$delivery_cost_actual,
                    );
                    $filter = array('delivery_id'=>$dly['delivery_id']);
                    $dlyObj->update($data,$filter);

                    if($logi_number==($delivery_logi_number+1)){
                        if ($dlyProcessLib->consignDelivery($dly['delivery_id'])){
                            $this->_endConsignSuccess($consignExtra);
                        }else {
                            $msg['delivery_bn'] = $dly['delivery_bn'];
                            $this->end(false, '发货未完成', '', array('msg'=>$msg));
                        }
                    }
                    $this->_endConsignSuccess($consignExtra);
                //加入如果$logi_number==$delivery_logi_number 但是发货状态没有改变的判断
                }elseif(($delivery_logi_number == $logi_number) && $dly['status'] != 3){
                    if ($dlyProcessLib->consignDelivery($dly['delivery_id'])){
                        $this->_endConsignSuccess($consignExtra);
                    }else {
                        $msg['delivery_bn'] = $dly['delivery_bn'];
                        $this->end(false, '发货未完成', '', array('msg'=>$msg));
                    }
                }else{
                    $this->end(false, '此物流运单已发货');
                }
            }
        }
    }

    /**
     * 生成确认出库成功后的快递单打印页链接（与 admin_receipts_print toPrintExpre 参数一致）
     * @param int|string $delivery_id wms 发货单 id
     * @param int|string $logi_id 物流公司 id
     * @return string
     */
    protected function _getConsignPrintExpreUrl($delivery_id, $logi_id) {
        return 'index.php?app=wms&ctl=admin_receipts_print&act=toPrintExpre&status=3&logi_id=' . urlencode((string)$logi_id) . '&delivery_id=' . urlencode((string)$delivery_id);
    }

    /**
     * consign 成功返回封装：
     * - pickup 场景：返回纯 JSON，避免 desktop 的 splash/doCommand 触发 redirect 打断自动打印流程
     * - 其他场景：保持原有 end() 行为
     *
     * @param array $consignExtra
     */
    protected function _endConsignSuccess($consignExtra = array())
    {
        if (is_array($consignExtra) && isset($consignExtra['print_delivery_id']) && $consignExtra['print_delivery_id']) {
            // 先提交事务（与 end() 一致），再返回纯 JSON，避免 desktop 的 splash/redirect 打断自动打印
            $this->endonly(true);
            header('Content-Type: application/json; charset=utf-8');
            $out = array_merge(array('success' => '成功：发货处理完成'), $consignExtra);
            $out['redirect'] = 'index.php?app=wms&ctl=admin_consign&act=index';
            echo json_encode($out);
            exit;
        }
        // 非 pickup：保持原有 end()，框架会处理 splash/redirect
        $this->end(true, '发货处理完成', null, $consignExtra);
    }

    /**
     *
     * 分组发货执行方法
     */
    function ajaxDoGroupConsign(){
        $tmp = explode('||', $_POST['ajaxParams']);
        $group = $tmp[0];
        $delivery = explode(';', $tmp[1]);
        $minWeight = app::get('wms')->getConf('wms.delivery.minWeight');
        if(count($delivery)>0 && $group>0){
            /* 执行时间判断 start */
            $pageBn = intval($_POST['pageBn']);
            $lastGroupDelivery = app::get('wms')->getConf('lastGroupDelivery'); //上次获取订单信息(key为execTime表示执行时间，key为pageBn表示页面编号),
            $groupDeliveryIntervalTime = app::get('wms')->getConf('ome.groupDelivery.intervalTime'); //每次操作的时间间隔

            if($pageBn !=$lastGroupDelivery['pageBn'] && ($lastGroupDelivery['execTime']+60*$groupDeliveryIntervalTime)>time()){
                echo json_encode(array('total' => count($delivery), 'succ' => 0, 'fail' => count($delivery), 'failInfo'=>array('执行时间不合法')));
                exit;
            }
            if($pageBn !=$lastGroupDelivery['pageBn'] && $pageBn<$lastGroupDelivery['execTime']){
                echo json_encode(array('total' => count($delivery), 'succ' => 0, 'fail' => count($delivery), 'failInfo'=>array('提交参数过期')));
                exit;
            }

            //记录本次获取订单时间
            $currentGroupDelivery = array(
                'execTime'=>time(),
                'pageBn'=>$pageBn,
            );
            app::get('ome')->setconf('lastGroupDelivery',$currentGroupDelivery);
            /* 执行时间判断 end */

            $deliCfgLib = kernel::single('wms_delivery_cfg');
            $weightGroup = $deliCfgLib->getValue('wms_delivery_consign_weight');
            $group_weight = $weightGroup[$group]  ? intval($weightGroup[$group]) : 0;
            #获取商品重量


            $deliveryObj = app::get('wms')->model('delivery');
            $deliveryBillObj = app::get('wms')->model('delivery_bill');
            $dlyBillLib = kernel::single('wms_delivery_bill');
            $opObj = app::get('ome')->model('operation_log');
            $wmsCommonLib = kernel::single('wms_common');
            $dlyCheckLib = kernel::single('wms_delivery_check');
            $dlyProcessLib = kernel::single('wms_delivery_process');

            $filter = array(
                'delivery_id'=>$delivery,
            );
            $deliverys = $deliveryObj->getList('*',$filter);
            $succ = 0;
            $fail = 0;
            $failInfo = array();
            foreach($deliverys as $value){
                if (isset($value['delivery_model']) && $value['delivery_model'] === 'pickup'){
                    $fail++;
                    $failInfo[] = $value['delivery_bn'] . '(提货单不支持分组发货)';
                    continue;
                }
                $weight = $value['net_weight']>0 ? $value['net_weight'] : $group_weight;
                $weight = $weight ? $weight : $minWeight;

                //获取物流费用
                $area = $value['ship_area'];
                $arrArea = explode(':', $area);
                $area_id = $arrArea[2];
                $delivery_cost_actual = $wmsCommonLib->getDeliveryFreight($area_id,$value['logi_id'],$weight);

                $logi_no = $dlyBillLib->getPrimaryLogiNoById($value['delivery_id']);
                $return_error = $dlyCheckLib->consignAllow('', $logi_no, $weight);
                if(empty($return_error)) {

                    $billInfo = $dlyCheckLib->unDlyChildBills($value['delivery_id']);
                    if($billInfo){

                        $flag = true;
                        foreach($billInfo as $v){
                            if(empty($v['logi_no'])){
                                $flag = false;
                                break;
                            }
                        }

                        if($flag){
                            $data = array(
                                'status'=> 1,
                                'weight'=>0.00,
                                'delivery_cost_actual'=> 0.00,
                                'delivery_time'=>time(),
                            );
                            $filter = array('delivery_id'=>$value['delivery_id'],'status'=> 0, 'type'=>2);
                            $num = $deliveryBillObj->update($data,$filter);

                            $logstr = '分组发货('.$num.')份';
                            $opObj->write_log('delivery_bill_express@wms', $value['delivery_id'], $logstr);

                            $data = array(
                                'status'=> 1,
                                'weight'=>$weight,
                                'delivery_cost_actual'=>$delivery_cost_actual,
                                'delivery_time'=>time(),
                            );
                            $filter = array('delivery_id'=>$value['delivery_id'],'status'=> 0, 'type'=>1);
                            $deliveryBillObj->update($data,$filter);

                            $numdata = array('delivery_logi_number'=>$value['logi_number'],'weight'=>$weight,'delivery_cost_actual'=>$delivery_cost_actual);
                            $numfilter = array('delivery_id'=>$value['delivery_id']);
                            $deliveryObj->update($numdata,$numfilter);

                            if($dlyProcessLib->consignDelivery($value['delivery_id'], wms_const::__GROUP)){
                                $succ++;
                            }else{
                                $fail++;
                                $failInfo[] = $value['delivery_bn'];
                            }
                        }else{
                            $fail++;
                            $failInfo[] = $value['delivery_bn'];
                        }
                    }else{
                        $data = array(
                            'status'=> 1,
                            'weight'=>$weight,
                            'delivery_cost_actual'=>$delivery_cost_actual,
                            'delivery_time'=>time(),
                        );
                        $filter = array('delivery_id'=>$value['delivery_id'],'status'=> 0, 'type'=>1);
                        $deliveryBillObj->update($data,$filter);

                        $numdata = array('delivery_logi_number'=>$value['logi_number'],'weight'=>$weight,'delivery_cost_actual'=>$delivery_cost_actual);
                        $numfilter = array('delivery_id'=>$value['delivery_id']);
                        $deliveryObj->update($numdata,$numfilter);

                        if($dlyProcessLib->consignDelivery($value['delivery_id'])){
                            //分组发货日志
                            $logstr = '分组发货完成';
                            $opObj->write_log('delivery_process@wms', $value['delivery_id'], $logstr);
                            $succ++;
                        }else{
                            $fail++;
                            $failInfo[] = $value['delivery_bn'];
                        }
                    }
                }else{
                    $fail++;
                    $failInfo[] = $value['delivery_bn'].$return_error;
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
     * 补打物流单入口页
     */
    public function fill_delivery(){
        $this->page("admin/delivery/fill_delivery.html");
    }

    /**
     *
     * 补打物流单的详细页
     */
    public function fill_delivery_confirm(){
        $str = array();
        $logi_no = $_POST['logi_no'];

        $dlyObj = app::get('wms')->model('delivery');
        $dlyBillObj = app::get('wms')->model('delivery_bill');
        $dlyCheckLib = kernel::single('wms_delivery_check');
        $deliveryBillItemMdl  = app::get('wms')->model('delivery_bill_items');

        //识别是主单还是次单
        $dlyBillInfo = $dlyBillObj->dump(array('logi_no'=>$logi_no),'delivery_id,type');
        $delivery_id = $dlyBillInfo['delivery_id'];

        if($delivery_id && $dlyBillInfo['type'] == 2){
            $dlyInfo = $dlyObj->dump(array('delivery_id'=>$delivery_id),'*');
            $return_error = $dlyCheckLib->extLogiNoAllow($dlyInfo, $logi_no);
            if ($return_error){
                $this->splash('error',null, $return_error);
                // $str['str'] = $return_error;
                // $tmp = array('status'=>'error','msg'=>$str);
                // echo json_encode($tmp);
                // die;
            }
        }elseif($delivery_id && $dlyBillInfo['type'] == 1){
            $dlyInfo = $dlyObj->dump(array('delivery_id'=>$delivery_id),'*');
            $return_error = $dlyCheckLib->extLogiNoAllow($dlyInfo, $logi_no);
            if($return_error){
                $this->splash('error',null, $return_error);
            }
        }else{
            // $str['str'] = '物流单号不存在';
            $this->splash('error',null, '物流单号不存在');
        }

        $dlyBillAllInfo = $dlyBillObj->getList('*',array('delivery_id'=>$dlyInfo['delivery_id']));
        foreach($dlyBillAllInfo as &$item){
            $item['logi_name'] = $dlyInfo['logi_name'];

            $item['products'] = $deliveryBillItemMdl->getList('*', ['bill_id' => $item['b_id']]);
            $item['status'] = $this->statusinfo($item['status']);
        }
        $this->pagedata['dlyBillAllInfo'] = $dlyBillAllInfo;
        $this->pagedata['dlyInfo']        = $dlyInfo;
        $this->display('admin/delivery/consign/package_print.html','wms');exit;

//         foreach($dlyBillAllInfo as $item){
//             $deliveryBillItemList = $deliveryBillItemMdl->getList('*', ['bill_id' => $item['b_id']]);

//             if($item['type'] == 1){
//                 $str['str'] = '<tr style="background-color:#f6f6f6; color:red; font-weight:bold;height:35px;"><td>'.$dlyInfo['logi_name'].'</td>';
//                 $str['str'] .= '<td>'.$item['logi_no'].'</td>';
//                 $str['str'] .= '<td>'.$item['package_bn'].'</td>';
//                 $str['str'] .= '<td>'.$item['weight'].'</td>';
//                 $str['str'] .= '<td></td>';
//                 $str['str'] .= '<td>'.$this->statusinfo($item['status']).'</td>';

//                 // 包裹明细----------------8<
//                 $str['str'] .= '<td>';
//                 if ($deliveryBillItemList){
//                     foreach ($deliveryBillItemList as $value) {
//                         $str['str'] .=<<<HTML
//                         <div>
//                             <div class="span-6">物料编码：{$value['bn']}</div>
//                             <div class="span-auto">物料名称：{$value['product_name']}</div>
//                             <div class="span-3">包裹数量：{$value['number']}</div>
//                         </div>
// HTML;
//                     }
//                 }
//                 $str['str'] .=  '</td>';
//                 // 包裹明细----------------8<


//                 $str['str'] .= '<td></td></tr>';
//             }else{
//                 $str['str'] .= '<tr style="background-color:#f6f6f6; font-weight:bold;height:35px;"><td>'.$dlyInfo['logi_name'].'</td>';
//                 $str['str'] .= '<td>'.$item['logi_no'].'</td>';
//                 $str['str'] .= '<td>'.$item['package_bn'].'</td>';
//                 $str['str'] .= '<td>'.$item['weight'].'</td>';
//                 $str['str'] .= '<td>'.date('Y-m-d H:s:m',$item['create_time']).'</td>';
//                 $str['str'] .= '<td>'.$this->statusinfo($item['status'],0).'</td>';
//                 // 包裹明细----------------8<
//                 $str['str'] .= '<td>';
//                 if ($deliveryBillItemList){
//                     foreach ($deliveryBillItemList as $value) {
//                         $str['str'] .=<<<HTML
//                         <div>
//                             <div class="span-6">物料编码：{$value['bn']}</div>
//                             <div class="span-auto">物料名称：{$value['product_name']}</div>
//                             <div class="span-3">包裹数量：{$value['number']}</div>
//                         </div>
// HTML;
//                     }
//                 }
//                 $str['str'] .=  '</td>';
//                 // 包裹明细----------------8<
//                 if($item['status']==1){
//                     $str['str'] .= '<td></td></tr>';
//                 }else{
//                     $str['str'] .= '<td><button type="button" onclick="del_deliveryBill('.$item['b_id'].','.$dlyInfo['delivery_id'].')" style="height:28px;width:40px;">删除</button></td></tr>';
//                 }
//             }
//         }

        // $str['delivery_id'] = $dlyInfo['delivery_id'];
        // $tmp = array('status'=>'success','msg'=>$str);
        // echo json_encode($tmp);
        // die;
    }

	/**
	 *
	 * 删除子快递单
	 */
    function del_deliveryBill(){
        $b_id = $_POST['b_id'];
        $delivery_id = $_POST['delivery_id'];

        $dlyBillObj = app::get('wms')->model('delivery_bill');
        $deliveryBillItemMdl  = app::get('wms')->model('delivery_bill_items');

        if($dbo=$dlyBillObj->dump(array('b_id'=>$b_id))){
            $dlyCorpObj = app::get('ome')->model('dly_corp');
            $delivery = app::get('wms')->model('delivery');
            $dlyInfo = $delivery->dump(array('delivery_id'=>$delivery_id));
            $status = false;
            if($dbo['status'] == 1){
                $status = true;
            }

            //写入日志
            $opObj = app::get('ome')->model('operation_log');
            if(empty($dbo['logi_no'])){
                $logstr = '删除快递单,单号为空';
            }else{
                $dlyCorp = $dlyCorpObj->dump($dlyInfo['logi_id'], 'tmpl_type,channel_id');
                //回收电子面单
                
                if ($dlyCorp['tmpl_type'] == 'electron') {
                    $waybillObj = kernel::single('logisticsmanager_service_waybill');
                    
                    $waybillObj->recycle_waybill($dbo['logi_no'],$dlyCorp['channel_id'],$delivery_id);
                    //如果是申通发送取消

                }
                $logstr = '删除快递单,单号:'.$dbo['logi_no'];

            }
            $opObj->write_log('delivery_bill_delete@wms', $delivery_id, $logstr);

            $result = $dlyBillObj->delete(array('b_id'=>$b_id));
            if($result){
                $deliveryObj = app::get('wms')->model('delivery');
                if($status){
                    $sql = "update sdb_wms_delivery set logi_number=logi_number-1,delivery_logi_number=delivery_logi_number-1 where delivery_id=".$delivery_id;
                }else{
                    $sql = "update sdb_wms_delivery set logi_number=logi_number-1 where delivery_id=".$delivery_id;
                }
                $deliveryObj->db->exec($sql);

                // 删除包裹明细
                $deliveryBillItemMdl = app::get('wms')->model('delivery_bill_items');
                $deliveryBillItemMdl->delete(['bill_id' => $b_id]);

                //京东电子面单补打
                $channel_info = kernel::single('wms_delivery_print_ship')->getWaybillType($delivery_id);
                if (in_array($channel_info['channel_type'],array('360buy'))) {
                    $logi_total = $dlyBillObj->count(array('delivery_id'=>$delivery_id,'type'=>'2'));
                    $delivery_data = $dlyBillObj->dump(array('delivery_id' => $delivery_id,'type'=>'1'),'logi_no');
                    $logi_no_total = $logi_total+1;
                    $logi_no_num = 1;
                    if ($logi_total>0) {
                        $dlyBillObj->db->exec("DELETE FROM sdb_wms_delivery_bill WHERE delivery_id=".$delivery_id." AND `type`='2'");
                        for($i=0;$i<$logi_total;$i++) {
                            $data = array('delivery_id' => $delivery_id,'create_time'=>time(),'type'=>2);
                            $logi_no_num++;
                            $data['logi_no'] = $delivery_data['logi_no'].'-'.$logi_no_num.'-'.$logi_no_total.'-';
                            $dlyBillObj->insert($data);
                        }
                    }
                }
                //
                $dlyInfo = $deliveryObj->dump(array('delivery_id'=>$delivery_id));
                $dlyBillAllInfo = $dlyBillObj->getList('*',array('delivery_id'=>$delivery_id));


//                 foreach($dlyBillAllInfo as $item){
//                     $deliveryBillItemList = $deliveryBillItemMdl->getList('*', ['bill_id' => $item['b_id']]);

//                     if($item['type'] == 1){
//                         $str['str'] = '<tr style="background-color:#f6f6f6; color:red;font-weight:bold;height:35px;"><td>'.$dlyInfo['logi_name'].'</td>';
//                         $str['str'] .= '<td>'.$item['logi_no'].'</td>';
//                         $str['str'] .= '<td>'.$item['package_bn'].'</td>';
//                         $str['str'] .= '<td>'.$item['weight'].'</td>';
//                         $str['str'] .= '<td></td>';
//                         $str['str'] .= '<td>'.$this->statusinfo($item['status']).'</td>';
//                         // 包裹明细----------------8<
//                         $str['str'] .= '<td>';
//                         if ($deliveryBillItemList){
//                             foreach ($deliveryBillItemList as $value) {
//                                 $str['str'] .=<<<HTML
//                                 <div>
//                                     <div class="span-6">物料编码：{$value['bn']}</div>
//                                     <div class="span-auto">物料名称：{$value['product_name']}</div>
//                                     <div class="span-3">包裹数量：{$value['number']}</div>
//                                 </div>
// HTML;
//                             }
//                         }
//                         $str['str'] .=  '</td>';
//                         // 包裹明细----------------8<

//                         $str['str'] .= '<td></td></tr>';
//                     }else{
//                         $str['str'] .= '<tr style="background-color:#f6f6f6; font-weight:bold;height:35px;"><td>'.$dlyInfo['logi_name'].'</td>';
//                         $str['str'] .= '<td>'.$item['logi_no'].'</td>';
//                         $str['str'] .= '<td>'.$item['package_bn'].'</td>';
//                         $str['str'] .= '<td>'.$item['weight'].'</td>';
//                         $str['str'] .= '<td>'.date('Y-m-d H:s:m',$item['create_time']).'</td>';
//                         $str['str'] .= '<td>'.$this->statusinfo($item['status'],0).'</td>';

//                         // 包裹明细----------------8<
//                         $str['str'] .= '<td>';
//                         if ($deliveryBillItemList){
//                             foreach ($deliveryBillItemList as $value) {
//                                 $str['str'] .=<<<HTML
//                                 <div>
//                                     <div class="span-6">物料编码：{$value['bn']}</div>
//                                     <div class="span-auto">物料名称：{$value['product_name']}</div>
//                                     <div class="span-3">包裹数量：{$value['number']}</div>
//                                 </div>
// HTML;
//                             }
//                         }
//                         $str['str'] .=  '</td>';
//                         // 包裹明细----------------8<

//                         if($item['status']==1){
//                             $str['str'] .= '<td></td></tr>';
//                         }else{
//                             $str['str'] .= '<td><button type="button" onclick="del_deliveryBill('.$item['b_id'].','.$dlyInfo['delivery_id'].')" style="height:28px;width:40px;">删除</button></td></tr>';
//                         }
//                     }
//                 }

                // $str['delivery_id'] = $delivery_id;

                //判断总包裹是否等于包裹数如果等于执行发货操作
                if ($dlyInfo['logi_number'] ==$dlyInfo['delivery_logi_number'] ) {
                    if($dlyInfo['status'] != 3){
                        $dlyProcessLib = kernel::single('wms_delivery_process');
                        $dlyProcessLib->consignDelivery($dlyInfo['delivery_id']);
                    
                    }
                }

                // $tmp = array('status'=>'success','msg'=>$str);
                // echo json_encode($tmp);

                foreach($dlyBillAllInfo as &$item){
                    $item['logi_name'] = $dlyInfo['logi_name'];

                    $item['products'] = $deliveryBillItemMdl->getList('*', ['bill_id' => $item['b_id']]);
                    $item['status'] = $this->statusinfo($item['status']);
                }

                $this->pagedata['dlyBillAllInfo'] = $dlyBillAllInfo;
                $this->pagedata['dlyInfo']        = $dlyInfo;
                $this->display('admin/delivery/consign/package_print.html','wms');
            }else{
                $this->splash('error',null, '删除失败');
            }
        }else{
                $this->splash('error',null, '删除失败');
        }
    }

	/**
	 *
     * 快递单状态翻译显示
     */
    function statusinfo($status,$style=1){
        switch($style){
            case 1:
                switch($status){
                    case 0:
                        return '未发货';
                    case 1:
                        return '已发货';
                }
            case 0:
                switch($status){
                    case 0:
                        return '未发货';
                    case 1:
                        return '<font color=red>已发货</font>';
                    case 2:
                        return '已取消';
                }
        }
    }

    /**
     *
     * 保存批量发货至记录队列表中
	 */
    function doBatchConsign(){
        $goto_url = 'index.php?app=wms&ctl=admin_consign&act=batch';
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
          'log_type'=>'consign',
          'log_text'=>serialize($delivery_ids)
         );
        $result = $blObj->save($bldata);

        //发货任务加队列
        $push_params = array(
            'data' => array(
                'log_text' => $bldata['log_text'],
                'log_id' => $bldata['log_id'],
                'task_type' => 'autodly'
            ),
            'url' => kernel::openapi_url('openapi.autotask','service')
        );
        kernel::single('taskmgr_interface_connecter')->push($push_params);

        $this->splash('success',$goto_url ,'发货完成');
    }

    /**
     *获取发货记录历史
    */
    function batchConsignLog(){
        $blObj  = app::get('wms')->model('batch_log');
        $batchLogLib = kernel::single('wms_batch_log');

        $dayBegin = mktime(0,0,0,date("m"),date("d"),date("Y"));//当天开始时间戳
        $dayEnd = mktime(23,59,59,date("m"),date("d"),date("Y"));//当天结束时间戳

        $blResult = $blObj->getList('*', array('log_type'=>'consign','createtime|than'=>$dayBegin,'createtime|lthan'=>$dayEnd), 0, -1,'createtime desc');
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
    function updateBatchConsignLog(){
        $log_id = urldecode($_POST['log_id']);
        if($log_id){
            $batchLogLib = kernel::single('wms_batch_log');
            $log_id = explode(',',$log_id);
            $status="'0','2'";

            $blResult = $batchLogLib->get_List('consign',$log_id,$status);
            foreach($blResult as $k=>$v){
                $status = $v['status'];
                $fail_number = $v['fail_number'];
                $blResult[$k]['status_value'] = $batchLogLib->getStatus($status);
                $blResult[$k]['fail_number'] = $fail_number;

            }
            if($blResult){
                echo json_encode($blResult);
            }
        }
    }


    /**
    * 商品重量报警判断
    */
    function weightWarn(){
        $type = $_GET['type'];
        $logi_no = $_POST['logi_no'];
        if($type=='countpackage'){
            #判断发货单是否多包裹
            $delivery = app::get('ome')->model('delivery')->dump(array('logi_no|nequal' => $logi_no),'delivery_id');
            $dlyBillObj = app::get('ome')->model('delivery_bill');
            $dlyBill = $dlyBillObj->dump(array('logi_no|nequal' => $logi_no),'delivery_id');
            if($delivery){
                $delivery_id = $delivery['delivery_id'];
            }else{
                $delivery_id = $dlyBill['delivery_id'];
            }
            $billfilter = array(

              'delivery_id'=>$delivery_id,
             );
            $num = $dlyBillObj->count($billfilter);
            echo $num;


        }else{

            $weight = $_POST['weight'];

            $product_weight = app::get('ome')->model('delivery')->getWeightbydelivery_id($logi_no);

            echo json_encode($product_weight);
        }



    }

    /**
    * 发货重量报警页面提醒
    */
    function showWeightWarn(){

        $logi_no = $_GET['logi_no'];
        $weight = $_GET['weight'];
        $type = $_GET['type'];
        #确认取消条码
        $stock_confirm= app::get('ome')->getConf('purchase.stock_confirm');
        $stock_cancel= app::get('ome')->getConf('purchase.stock_cancel');
        $this->pagedata['stock_confirm'] = $stock_confirm;
        $this->pagedata['stock_cancel'] = $stock_cancel;
        if($type=='unweight'){

            $product_weight = app::get('ome')->model('delivery')->getWeightbydelivery_id($logi_no);
            $this->pagedata['product_weight'] = $product_weight;

            unset($product_weight);
            $this->pagedata['logi_no'] = $logi_no;
            $this->pagedata['weight'] = $weight;
            $this->page('admin/delivery/delivery_noweight.html');
        }else if($type=='weightwarn'){
            $this->pagedata['logi_no'] = $logi_no;
            $this->pagedata['weight'] = $weight;
            $problem_package = app::get('wms')->getConf('wms.delivery.problem_package');
            $this->pagedata['problem_package'] = $problem_package;
            #如果是按退回去检查
            if($problem_package==0){
                $this->page('admin/delivery/delivery_weightwarnback.html');
            }else{
                $this->page('admin/delivery/delivery_weightwarn.html');
            }
            #两种情况时

        }else if($type=='addLog'){
             //写入日志
             $logi_no = $_POST['logi_no'];
             $weight = $_POST['weight'];
             $logerror = $_POST['logerror'];
            $dlyObj = app::get('ome')->model('delivery');
            
            $dlyBillObj = app::get('ome')->model('delivery_bill');
            $dlyBill = $dlyBillObj->dump(array('logi_no|nequal' => $logi_no),'delivery_id');
            $dlyfather = $dlyObj->dump(array('logi_no|nequal' => $logi_no),'delivery_id');
            if($dlyBill){
                $delivery_id = $dlyBill['delivery_id'];
            }elseif($dlyfather){
                $delivery_id = $dlyfather['delivery_id'];

            }

            $opObj = app::get('ome')->model('operation_log');
            if($logerror=='1'){
                $logstr = '运单号:'.$logi_no.',检查后再发货(包裹内有商品未录入重量)';
            }else{
                $logstr = '运单号:'.$logi_no.',检查后再发货(称重为:'.$weight.'g)';
            }
            echo $logstr;
            $opObj->write_log('delivery_weightwarn@wms', $delivery_id, $logstr);
            return true;

        }
    }

    /**
     * 极速发货
     *
     * @param void
     * @return void
     */
    function fast_consign() {
        $this->app = app::get('ome');
        $op_id = kernel::single('desktop_user')->get_id();
        switch ($_GET['view']) {
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
                $action = array(
                    array('label' => '批量发货', 'submit' => 'index.php?app=wms&ctl=admin_consign&act=batch_sync', 'confirm' => '你确定要对勾选的订单进行发货操作吗？', 'target' => 'refresh'),
                    array('label' => '已回写成功', 'submit' => 'index.php?app=wms&ctl=admin_consign&act=batch_sync_succ', 'confirm' => "这些订单系统认为都是在前台(淘宝、京东等)已经发货，请确认这些订单前端已经发货！！！\n\n警告：本操作不会再同步发货状态，并不可恢复，请谨慎使用！！！", 'target' => 'refresh'),
                );
                break;
            default:
                break;
        }

        $params = array(
            'title' => '需发货订单',
            'actions' => $action,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => false,
            'use_view_tab' => true,
            'finder_aliasname' => 'order_consign_fast'.$op_id,
            'finder_cols' => '_func_0,column_confirm,order_bn,column_sync_status,column_print_status,logi_id,logi_no,column_deff_time,member_id, ship_name,ship_area,total_amount',
            'base_filter' => $this->getFilters(),
        );
        $this->finder('ome_mdl_orders', $params);
    }

    function getFilters() {

        $base_filter = array();
        $base_filter['status'] = array('active', 'finish');
        //$base_filter['order_confirm_filter'] = "(sdb_ome_orders.op_id is not null AND sdb_ome_orders.group_id is not null ) AND (sdb_ome_orders.is_cod='true' OR sdb_ome_orders.pay_status='1' OR sdb_ome_orders.pay_status='4' OR sdb_ome_orders.pay_status='5') and logi_no <> ''";
        $base_filter['order_confirm_filter'] = "(sdb_ome_orders.op_id is not null OR sdb_ome_orders.group_id is not null ) AND (sdb_ome_orders.is_cod='true' OR sdb_ome_orders.pay_status='1' OR sdb_ome_orders.pay_status='4' OR sdb_ome_orders.pay_status='5') and logi_no <> ''";
        $base_filter['process_status'] = array('splited', 'confirmed', 'splitting');

        return $base_filter;
    }
    
    function getProcutInfo()
    {
        $dlyMdl = app::get('wms')->model('delivery');
        $dlyBillMdl = app::get('wms')->model('delivery_bill');
        
        $logi_no                = $_POST['logi_no'];
        $deliveryBillLib        = kernel::single('wms_delivery_bill');
        $basicMaterialExtObj    = app::get('material')->model('basic_material_ext');

        $deliveryBill = $dlyBillMdl->db_dump(['logi_no' => $logi_no]);

        // [提货物流] delivery_model='pickup' 运单号后取，扫描的是发货单号(delivery_bn)，按 delivery_bn 查主单
        if (empty($deliveryBill)) {
            $pickupDly = $dlyMdl->dump(array('delivery_bn' => $logi_no, 'delivery_model' => 'pickup'), 'delivery_id');
            if ($pickupDly) {
                $deliveryBill = $dlyBillMdl->db_dump(array('delivery_id' => $pickupDly['delivery_id'], 'type' => 1));
            }
        }
        
        //[同城配]商家配送支持配送员手机号搜索
        if(empty($deliveryBill) && strlen($logi_no) == 11){
            $delivery = $dlyMdl->getList('*', array('deliveryman_mobile'=>$logi_no, 'process_status'=>array(2,3)), 0, 1);
            if($delivery){
                $delivery_id = $delivery[0]['delivery_id'];
                
                $deliveryBill = $dlyBillMdl->db_dump(array('delivery_id'=>$delivery_id), '*');
            }
        }
        
        $total_weight   = 0;
        $procut_info    = [];
        if ($deliveryBill) {
            $procut_info = app::get('wms')->model('delivery_bill_items')->getList('*', ['bill_id' => $deliveryBill['b_id']]);

            if (!$procut_info && $deliveryBill['type'] == '1'){
                $procut_info = app::get('wms')->model('delivery')->getProcutInfo($deliveryBill['delivery_id']);
            }

            if ($procut_info){
                foreach($procut_info as $k => $v){
                    if(isset($v['bn']) && isset($v['product_name']) && isset($v['number'])){
                        $products = $basicMaterialExtObj->dump(array('bm_id'=>$v['product_id']),'weight');
                        $weight = sprintf('%.2f',$products['weight']*$v['number']);

                        $procut_info[$k]['weight'] = $weight;

                        $total_weight+=$weight;
                    }
                }
            }

        }

        $this->pagedata['procut_info']  = $procut_info;
        $this->pagedata['total_weight'] = $total_weight;
        $this->display('admin/delivery/consign/package_items.html');
    }

    #校验完成即发货：准备校验
    function CheckDelivery(){
        $db = kernel::database();
        $transaction =  $db->beginTransaction();
        $result = $this->doCheck($_rs);
        #校验已完成
        if($_rs){
            $_POST['delivery_id']  = urldecode($_POST['logi_no']);
            $_GET['delivery_type'] = 'single';
            $rs2 = 'CheckDelivery';#校验完即发货类型
            #继续判断发货条件
            $result = $this->batchCheck($rs2);
            if($rs2 !== 'CheckDelivery'){
                if($rs2){
                    $db->commit($transaction);
                }else{
                    $db->rollBack();
                }
            }
            $db->rollBack();
            return $result;
        }else{
            $db->rollBack();
            #校验未完成，返回校验错误
            return $result;
        }
    }

    //校验直接发货检查方法
    function doCheck(&$rs = false){
        $autohide = array('autohide'=>2000);
        $checkType = in_array($_POST['checkType'],array('barcode','all')) ? $_POST['checkType'] : 'barcode';
        
        if (empty($_POST['delivery_id'])){
            $tmp = array('result'=>false,'msg'=>'发货单ID传入错误');
            echo json_encode($tmp);die;
        }
        if ($_POST['logi_no'] == ''){
            $tmp = array('result'=>false,'msg'=>'请扫描快递单号');
            echo json_encode($tmp);die;
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
        $dlyCheckLib = kernel::single('wms_delivery_check');
        if(!$dlyCheckLib->checkOrderStatus($dly_id, true, $errmsg)){
            $tmp = array('result'=>false,'msg'=>$errmsg);
            echo json_encode($tmp);die;
        }

        if ($count == 0 || $number == 0){
            $tmp = array('result'=>false,'msg'=>'对不起，校验提交的数据错误');
            echo json_encode($tmp);die;
        }


        $deliveryObj  = app::get('wms')->model('delivery');
        $dly          = $deliveryObj->dump($dly_id,'*', array('delivery_items'=>array('*')));

        //检查运单号是否属于同一个处理的发货单
        $deliveryBillLib = kernel::single('wms_delivery_bill');
        $db_logi_no = $deliveryBillLib->getPrimaryLogiNoById($dly_id);
        if ($db_logi_no != $logi_no && $dly['delivery_model'] != 'pickup'){
            $tmp = array('result'=>false,'msg'=>'扫描的快递单号与系统中的快递单号不对应');
            echo json_encode($tmp);die;
        }

        //合计发货单明细的总数
        $total = 0;
        foreach ($dly['delivery_items'] as $i){
            $total += $i['number'];
        }

        $opObj        = app::get('ome')->model('operation_log');
        $deliveryLib = kernel::single('wms_delivery_process');

        if ($count === $number) {

            //对发货单详情进行校验完成处理
            if ($deliveryLib->verifyDelivery($dly_id)){
                if(is_array($_POST['serial_data']) && count($_POST['serial_data'])>0)
                {
                    $productSerialObj = app::get('ome')->model('product_serial');
                    $serialLogObj = app::get('ome')->model('product_serial_log');
                    $opInfo = kernel::single('ome_func')->getDesktopUser();
                    foreach($_POST['serial_data'] as $key=>$val){
                        foreach($val as $serial){
                            $serialData['branch_id'] = $dly['branch_id'];
                            $serialData['product_id'] = $_POST['product'][$key];
                            $serialData['bn'] = $key;
                            $serialData['serial_number'] = $serial;
                            $serialData['status'] = 1;
                            $productSerialObj->save($serialData);

                            $logData['item_id'] = $serialData['item_id'];
                            $logData['act_type'] = 0;
                            $logData['act_time'] = time();
                            $logData['act_owner'] = $opInfo['op_id'];
                            $logData['bill_type'] = 0;
                            $logData['bill_no'] = $dly['delivery_id'];
                            $logData['serial_status'] = 1;
                            $serialLogObj->save($logData);
                            unset($serialData,$logData);
                        }
                    }
                }

                //增加发货单校验把保存后的扩展
                foreach(kernel::servicelist('ome.delivery') as $o){
                    if(method_exists($o,'after_docheck')){
                        $data = $_POST;
                        $o->after_docheck($data);
                    }
                }

                $rs = true;
                return true;
            }else {
                $tmp = array('result'=>false,'msg'=>'发货单校验未完成，请重新校验');
                echo json_encode($tmp);die;
            }
        } else {
            $tmp = array('result'=>false,'msg'=>'发货单校验未完成，请重新校验');
            echo json_encode($tmp);die;
        }
    }

    #校验完成即发货：开始发货
    function doCheckDelivery(){
        $goto_url = 'index.php?app=wms&ctl=admin_check&act=index&type=all';
        $ids = $_POST['delivery_id'];
    
        $delivery_ids = explode(',', $ids);
        $delivery_ids = array_filter($delivery_ids);
         
        if ( !$delivery_ids ) {
            $this->splash('success',$goto_url ,'快递单列表为空！');
        }
        $delivery_id = $_POST['delivery_id'];
        $msg = '校验完成即发货：开始发货';
        $ObjOp        = app::get('ome')->model('operation_log');
        $ObjOp->write_log('delivery_checkdelivery@wms',$delivery_id,$msg,time());
        #走原来发货流程
        return $this->consign(true);
    }

    /**
     * 往包裹里添加货品
     *
     * @param String $delivery_id 发货单ID
     * @param String $num 补打数量
     * @return void
     * @author CP
     * @version 4.3.9 2021-08-15T20:54:58+08:00
     **/
    public function addPackageItem($delivery_id, $num)
    {
        $deliveryMdl = app::get('wms')->model('delivery');
        $delivery = $deliveryMdl->db_dump(['delivery_id' => $delivery_id]);
        if (!$delivery) {
            echo '发货单不存在！';exit;
        }
        if (!$num) {
            echo '补打数量必填！';exit;
        }

        // 包裹明细
        $deliveryBillMdl = app::get('wms')->model('delivery_bill');
        $deliveryBillList = $deliveryBillMdl->getList('*', ['delivery_id' => $delivery_id]);

        $billId = $deliveryBillList ? array_column($deliveryBillList, 'b_id') : [0];

        $sequence = [0];
        foreach ($deliveryBillList as $key => $value) {
            if ($value['package_bn']) {
                list(,$sequence[]) = explode('_', $value['package_bn']);
            }
        }
        $maxPackageBn = max($sequence);

        $packages = [];
        foreach ($deliveryBillList as $key => $value) {
            if ($value['type'] == '1' && !$value['package_bn']){
                $maxPackageBn++;
                $packages[$maxPackageBn]['package_bn'] = $delivery['delivery_bn'].'_'.$maxPackageBn;
                $packages[$maxPackageBn]['package_type'] = '1';
                $packages[$maxPackageBn]['bill_id'] = $value['b_id'];
                $packages[$maxPackageBn]['package_title'] = '主包裹'.$maxPackageBn.'，运单号：'.$value['logi_no'];
                break;
            }
        }

        for ($i=1; $i <= $num; $i++) { 
            $maxPackageBn++;
            $packages[$maxPackageBn]['package_bn'] = $delivery['delivery_bn'].'_'.$maxPackageBn;
            $packages[$maxPackageBn]['package_title'] = '补打包裹'.$maxPackageBn;
        }

        $deliveryBillItemMdl = app::get('wms')->model('delivery_bill_items');
        $billItemList = [];
        foreach ($deliveryBillItemMdl->getList('*', ['bill_id' => $billId]) as $value) {
            $billItemList[$value['product_id']]['product_id'] = $value['product_id'];
            $billItemList[$value['product_id']]['number']     += $value['number'];
        }

        $materialCodeMdl = app::get('material')->model('codebase');
        $deliveryItemMdl = app::get('wms')->model('delivery_items');
        $deliveryItems = $deliveryItemMdl->getList('*', ['delivery_id' => $delivery_id]);

        $product_id = array_column($deliveryItems, 'product_id');
        $materialCodeList = $materialCodeMdl->getList('*', ['bm_id' => $product_id, 'type' => '1']);
        $materialCodeList = array_column($materialCodeList, null, 'bm_id');

        foreach ($deliveryItems as $key => $value) {
            $deliveryItems[$key]['barcode'] = (string) $materialCodeList[$value['product_id']]['code'];
            $deliveryItems[$key]['package_num'] = (int) $billItemList[$value['product_id']]['number'];
        }

        $this->pagedata['deliveryItems'] = $deliveryItems;
        $this->pagedata['packages'] = $packages;
        $this->pagedata['delviery_id'] = $delivery_id;
        $this->pagedata['num'] = $num;
        $this->display('admin/delivery/package/add_items.html');
    }
    
}
