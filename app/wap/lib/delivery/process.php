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

class wap_delivery_process{

    /**
     * 确认 接单
     * 
     * @param Array $params
     * @return boolean
     */
    function accept($params){
        
        //确认接收该门店门店自提单或门店配送
        $deliveryObj    = app::get('wap')->model('delivery');
        $update_data    = array('delivery_id'=>$params['delivery_id'], 'confirm'=>1, 'last_modified'=>time());
        $deliveryObj->save($update_data);
        
        //门店仓不需要wms_id直接走门店绑定的服务端去识别接口
        $store_id      = kernel::single('ome_branch')->isStoreBranch($params['branch_id']);
        $channel_id    = $store_id;

        #获取发货仓库对应的门店店铺信息
        $wapDeliveryLib    = kernel::single('wap_delivery');
        $branchShopInfo    = $wapDeliveryLib->getBranchShopInfo($params['branch_id']);
        
        //获取是门店自提还是门店配送
        $corpTypeLib = kernel::single('o2o_corp_type');
        $dlyCorpInfo = $corpTypeLib->get_corp_type($params["logi_id"], true);
        
        $memo    = '门店('. $branchShopInfo['store_name'] .')已接单，';
        if($dlyCorpInfo["type"] == 'o2o_pickup')
        {
            $memo .= '请到门店自提';
        }
        else
        {
            $memo .= '请耐心等待门店为您配送';
        }

        //订单确认参数组织
        $request_params    = array(
            'delivery_bn' => $params['outer_delivery_bn'],
            'memo' => $memo,
        );
        
        $res    = kernel::single('wap_event_trigger_delivery')->confirm($channel_id, $request_params, true);
        return true;
    }

    //门店自提拒绝
    function refuse($params){

        $deliveryObj    = app::get('wap')->model('delivery');

        //门店自提单拒绝打回
        $update_data    = array('confirm'=>'2', 'status'=>'1', 'last_modified'=>time());
        $rs = $deliveryObj->update($update_data, ['delivery_id'=>$params['delivery_id'],'confirm'=>'3', 'status'=>'0']);
        if(is_bool($rs)) {
            return false;
        }

        //门店信息
        $wapDeliveryLib    = kernel::single('wap_delivery');
        $branchShopInfo    = $wapDeliveryLib->getBranchShopInfo($params['branch_id']);

        //门店仓不需要wms_id直接走门店绑定的服务端去识别接口
        $store_id      = kernel::single('ome_branch')->isStoreBranch($params['branch_id']);
        $channel_id    = $store_id;

        //获取门店拒绝的原因
        $reasonObj    = app::get('o2o')->model('refuse_reason');
        $reasonInfo         = $reasonObj->dump(array('reason_id'=>$params['reason_id']), '*');
        $memo = $params['memo'] ? $params['memo'] : $reasonInfo['reason_name'];

        $request_params = array(
            'delivery_bn' => $params['outer_delivery_bn'],
            'store_bn' => $branchShopInfo['store_bn'],
            'store_name' => $branchShopInfo['store_name'],
            'reason_id' => $params['reason_id'],
            'memo' => $memo,
        );

        $res = kernel::single('wap_event_trigger_delivery')->reback($channel_id, $request_params, true);
        $rebackOk = is_array($res) ? (($res['rsp'] ?? '') === 'succ') : (bool) $res;
        if (!$rebackOk) {
            return false;
        }

        try {
            kernel::single('wap_branch_daily_counter')->decrDispatchByOuterBn($params['outer_delivery_bn']);
        } catch (Exception $e) {
            // 统计失败不影响拒单
        }

        return true;
    }

    //重发提货校验码短信
    function reSendMsg($params){

        $deliveryObj    = app::get('wap')->model('delivery');

        //门店仓不需要wms_id直接走门店绑定的服务端去识别接口
        $store_id      = kernel::single('ome_branch')->isStoreBranch($params['branch_id']);
        $channel_id    = $store_id;

        #获取发货仓库对应的门店店铺信息
        $wapDeliveryLib    = kernel::single('wap_delivery');
        $branchShopInfo    = $wapDeliveryLib->getBranchShopInfo($params['branch_id']);
        
        //获取是门店自提还是门店配送
        $corpTypeLib = kernel::single('o2o_corp_type');
        $dlyCorpInfo = $corpTypeLib->get_corp_type($params["logi_id"],true);

        //判断是否开启销单校验码 关闭状态下$pickup_code不生成不赋值
        if(app::get('o2o')->getConf('o2o.delivery.confirm.code') == "true"){
            #生成六位提货单的校验码
            $wapCodeLib = kernel::single('wap_code');
            //作废老的校验码
            $wapCodeLib->del_code($params['delivery_bn']);
            //生成六位提货单的校验码
            $pickup_code = $wapCodeLib->gen_code($params['delivery_bn']);
        }
        
        //订单确认参数组织
        $request_params    = array(
            'dly_corp_type' => $dlyCorpInfo["type"],
            'delivery_bn' => $params['outer_delivery_bn'],
            'pickup_bn' => $params['delivery_bn'],
            'pickup_code' => $pickup_code,
            'ship_mobile' => $params['consignee']['mobile'],
            'ship_name' => $params['consignee']['name'],
            'store_name' => $branchShopInfo['store_name'],#门店名称
            'store_contact_tel' => $branchShopInfo['tel'] ? $branchShopInfo['tel'] : $branchShopInfo['mobile'],#门店联系电话
            'sms_flag' => 'reSendMsg',#重发短信标识
        );

        //[自提]需要门店地址
        if($dlyCorpInfo["type"] == 'o2o_pickup'){
            $request_params["store_addr"] = $branchShopInfo['addr'];
        }

        
        /* 重新发送短信给收货人 */
        $logstr    = '';
        
        //门店自提单发送短信
        if($request_params['dly_corp_type'] == 'o2o_pickup')
        {
            $sendArr = array(
                    'event_type' => 'o2opickup',
                    'ship_mobile' => $request_params['ship_mobile'],
                    'ship_name' => $request_params['ship_name'],
                    'pickup_bn' => $request_params['pickup_bn'],#提货单
                    'pickup_code' => $request_params['pickup_code'],#校验码
                    'store_name' => $request_params['store_name'],#门店名称
                    'store_addr' => $request_params['store_addr'],#门店地址
                    'store_contact_tel' => $request_params['store_contact_tel'],#门店联系方式
            );
            
            $logstr = '重新发短信生成提货码,请到('.$request_params['store_name'].')门店自提;';
        }
        elseif($request_params['dly_corp_type'] == 'o2o_ship')
        {
            $sendArr = array(
                    'event_type' => 'o2oship',
                    'ship_mobile' => $request_params['ship_mobile'],
                    'ship_name' => $request_params['ship_name'],
                    'pickup_bn' => $request_params['pickup_bn'],#提货单
                    'pickup_code' => $request_params['pickup_code'],#校验码
                    'store_name' => $request_params['store_name'],#门店名称
                    'store_contact_tel' => $request_params['store_contact_tel'],#门店联系方式
            );
            
            $logstr = '重新发短信生成校验码,请耐心等待门店('.$request_params['store_name'].')为您配送;';
        }
        
        $sendSms    = kernel::single('taoexlib_sms')->sendSms($sendArr, $error_msg);
        if(!$sendSms)
        {
            $logstr = $logstr."短信发送失败(". $error_msg .")";
        }
        
        /* ome订单操作日志 */
        $deliveryOrderObj   = app::get('ome')->model('delivery_order');
        $deliveryObj        = app::get('ome')->model('delivery');
        
        $delivery_info    = $deliveryObj->dump(array('delivery_bn'=>$request_params['delivery_bn']), 'status, delivery_id');
        $order_ids        = $deliveryOrderObj->getList('order_id', array('delivery_id'=>$delivery_info['delivery_id']), 0, -1);
        
        #订单记录日志
        $opObj    = app::get('ome')->model('operation_log');
        foreach ($order_ids as $row)
        {
            $opObj->write_log('order_modify@ome', $row['order_id'], $logstr);
        }
        
        return true;
    }
    
    /**
     * 确认发货
     */
    function consign($params, $is_bulu = false)
    {
        $deliveryObj    = app::get('wap')->model('delivery');
        $consignOp      = $this->getConsignOp();
        
        #wap发货单更新
        $dlydata    = array();
        $delivery_time    = time();
        
        $dlydata['status'] = 3;
        $dlydata['process_status'] = 7;
        if ($params['logi_status'] != '1') {
            $dlydata['logi_status'] = '7';
        }
        $shopInfo = array();
        if (!empty($params['shop_id'])) {
            $shopInfo = app::get('ome')->model('shop')->dump($params['shop_id'], 'shop_type');
        }
        if ($is_bulu && !empty($shopInfo['shop_type']) && $shopInfo['shop_type'] == 'ecos.ecshopx' && !empty($params['logi_no']) && $params['logi_no'] == '987654321') {
            $dlydata['logi_status'] = '1';
        }
        $dlydata['last_modified'] = $delivery_time;
        $dlydata['delivery_time'] = $delivery_time;
        if (!empty($consignOp['op_id'])) {
            $dlydata['op_id']   = $consignOp['op_id'];
            $dlydata['op_name'] = $consignOp['op_name'];
        }
        if($params['retry_sync']) {
            $result = 1;
        } else {
            $result    = $deliveryObj->update($dlydata, ['delivery_id'=>$params['delivery_id'], 'status'=>'0']);
        }
        
        if(is_bool($result))
        {
            return false;
        }
        
        //门店仓不需要wms_id直接走门店绑定的服务端去识别接口
        $store_id      = kernel::single('ome_branch')->isStoreBranch($params['branch_id']);
        $channel_id    = $store_id;
        
        #获取发货仓库对应的门店店铺信息
        $wapDeliveryLib    = kernel::single('wap_delivery');
        $branchShopInfo    = $wapDeliveryLib->getBranchShopInfo($params['branch_id']);
        
        //获取是门店自提还是门店配送
        $corpTypeLib = kernel::single('o2o_corp_type');
        $dlyCorpInfo = $corpTypeLib->get_corp_type($params["logi_id"], true);
        
        //判断是否开启销单校验码 关闭状态下$pickup_code不生成不赋值
        if(app::get('o2o')->getConf('o2o.delivery.confirm.code') == "true"){
            #生成六位提货单的校验码
            $wapCodeLib = kernel::single('wap_code');
            $pickup_code = $wapCodeLib->gen_code($params['delivery_bn']);
        }

        $logi_no = $this->resolveConsignLogiNo($params);
        
        //订单确认参数组织 门店配送短信模板只需要ship_mobile ship_name store_name store_contact_tel
        $request_params = array(
                'delivery_bn' => $params['outer_delivery_bn'],
                'delivery_time' => $delivery_time,
                'weight' => $params['weight'],
                'delivery_cost_actual' => $params['delivery_cost_actual'],
                'logi_id' => $params['logi_id'],
                'logi_no' => $logi_no,
                'dly_corp_type' => $dlyCorpInfo["type"],
                'pickup_bn' => $params['delivery_bn'],
                'pickup_code' => $pickup_code,
                'ship_mobile' => $params['consignee']['mobile'],
                'ship_name' => $params['consignee']['name'],
                'store_name' => $branchShopInfo['store_name'],#门店名称
                'store_contact_tel' => $branchShopInfo['tel'] ? $branchShopInfo['tel'] : $branchShopInfo['mobile'],#门店联系电话
        );
        if (!empty($params['serial_items'])) {
            $request_params['items'] = $params['serial_items'];
        }
        if (!empty($consignOp['op_id'])) {
            $request_params['op_id']   = $consignOp['op_id'];
            $request_params['op_name'] = $consignOp['op_name'];
        }
        $scannedOpNo = trim($params['scanned_op_no'] ?? '');
        if ($scannedOpNo !== '') {
            $request_params['op_no'] = $scannedOpNo;
        }

        // 参照奇门 WMS 回写补齐 package / bill_logi_weight，供 ome_event_receive_delivery 落 delivery_package / delivery_bill
        $packageBillParams = $this->buildConsignPackageAndBillParams($params, $logi_no, $dlyCorpInfo);
        if (!empty($packageBillParams['package'])) {
            $request_params['package'] = $packageBillParams['package'];
        }
        if (!empty($packageBillParams['bill_logi_weight'])) {
            $request_params['bill_logi_weight'] = $packageBillParams['bill_logi_weight'];
        }
        
        //[自提]需要门店地址
        if($dlyCorpInfo["type"] == "o2o_pickup")
        {
            $request_params["store_addr"] = $branchShopInfo['addr'];//自提短信需要门店地址
        }
        
        $res    = kernel::single('wap_event_trigger_delivery')->consign($channel_id, $request_params, true);

        if (!empty($params['serial_items'])) {
            if ($scannedOpNo !== '') {
                kernel::single('machinecenter_sn_delivery_op')->saveFromWapConsign($params, $params['serial_items']);
            }
            // 发货成功后释放 SN 预占并删除 WAP 本地备货唯一码占用
            kernel::single('wap_delivery_serial')->releasePrepareSerialByDelivery(
                intval($params['delivery_id'])
            );
        }
        
        return true;
    }
    
    /**
     * 确认签收
     */
    function sign($params)
    {
        $outer_delivery_bn = trim($params['outer_delivery_bn'] ?? '');
        if ($outer_delivery_bn === '') {
            return false;
        }

        $err_msg = '';
        if (!kernel::single('wap_receipt_delivery')->signDlyLocalByOuterDlyBn($outer_delivery_bn, $err_msg)) {
            return false;
        }

        #获取发货仓库对应的门店店铺信息
        $wapDeliveryLib    = kernel::single('wap_delivery');
        $branchShopInfo    = $wapDeliveryLib->getBranchShopInfo($params['branch_id']);
        
        //获取是门店自提还是门店配送
        $corpTypeLib = kernel::single('o2o_corp_type');
        $dlyCorpInfo = $corpTypeLib->get_corp_type($params['logi_id'], true);
        
        //门店仓不需要wms_id直接走门店绑定的服务端去识别接口
        $store_id      = kernel::single('ome_branch')->isStoreBranch($params['branch_id']);
        $channel_id    = $store_id;

        if($dlyCorpInfo["type"] == 'o2o_pickup')
        {
            $memo = '顾客已提货';
        }
        else
        {
            $memo = '顾客已签收';
        }

        $request_params = array(
            'delivery_bn' => $params['outer_delivery_bn'],
            'sign_time'=>date('Y-m-d H:i:s'),
            'memo' => $memo,
            
        );
        $res    = kernel::single('wap_event_trigger_delivery')->sign($channel_id, $request_params, true);
        
        return true;
    }

    /**
     * 确认发货前保存运单号：校验格式/重复，已存在相同运单号则跳过
     *
     * @param int    $delivery_id
     * @param string $logi_no
     * @param string $logi_code
     * @param string $err_msg
     * @return bool
     */
    public function saveLogiNoForConsign($delivery_id, $logi_no, $logi_code, &$err_msg = '')
    {
        $delivery_id = intval($delivery_id);
        $logi_no     = trim((string) $logi_no);
        $logi_code   = trim((string) $logi_code);

        if ($delivery_id <= 0) {
            $err_msg = '无效操作';
            return false;
        }
        if ($logi_no === '' || $logi_code === '') {
            $err_msg = '请填写快递公司和运单号';
            return false;
        }

        if (!$this->validateLogiNoFormat($logi_no, $logi_code, $err_msg)) {
            return false;
        }

        $deliveryBillObj = app::get('wap')->model('delivery_bill');
        $bill            = $deliveryBillObj->dump(array('delivery_id' => $delivery_id), 'logi_no');
        if (!empty($bill['logi_no'])) {
            if ($bill['logi_no'] === $logi_no) {
                return true;
            }
            $err_msg = '运单号已录入，请直接确认发货';
            return false;
        }

        if (!$this->validateLogiNoNotUsedByOthers($delivery_id, $logi_no, $err_msg)) {
            return false;
        }

        $res = $this->insertExpress(array(
            'delivery_id' => $delivery_id,
            'logi_no'     => $logi_no,
            'logi_code'   => $logi_code,
        ));
        if ($res['rsp'] != 'succ') {
            $err_msg = !empty($res['msg']) ? $res['msg'] : '保存运单号失败';
            return false;
        }

        return true;
    }

    /**
     * 运单号格式校验（与 wap_ctl_logistics::validateLogiNoInput 一致）
     */
    public function validateLogiNoFormat($logi_no, $logi_code, &$err_msg = '')
    {
        $logi_no   = trim((string) $logi_no);
        $logi_code = trim((string) $logi_code);

        if (!preg_match('/^[a-zA-Z0-9]{8,}$/', $logi_no)) {
            $err_msg = '快递单号必须至少8位且只能包含字母和数字';
            return false;
        }
        if ($logi_code == 'YT' && !preg_match('/^YT[0-9]{12,13}$/', $logi_no)) {
            $err_msg = '圆通快递单号必须以YT开头+12/13位数字';
            return false;
        }
        if ($logi_code == 'DBL' && !preg_match('/^DP[A-Z]{1}[0-9]{12}$/', $logi_no)) {
            $err_msg = '德邦物流单号必须以DP开头+1位大写字母+12位数字';
            return false;
        }
        if ($logi_code == 'ZTO' && !preg_match('/^7[0-9]{12,14}$/', $logi_no)) {
            $err_msg = '中通快递单号必须以7开头+12至14位数字';
            return false;
        }

        return true;
    }

    /**
     * 运单号是否已被其他发货单占用
     */
    public function validateLogiNoNotUsedByOthers($delivery_id, $logi_no, &$err_msg = '')
    {
        $logi_no = trim((string) $logi_no);
        if ($logi_no === '') {
            return true;
        }

        $wapDeliveryBillObj = app::get('wap')->model('delivery_bill');
        $existWapBill       = $wapDeliveryBillObj->dump(array('logi_no' => $logi_no), 'delivery_id');
        if (!empty($existWapBill['delivery_id']) && intval($existWapBill['delivery_id']) !== intval($delivery_id)) {
            $err_msg = '运单号已被其他发货单使用';
            return false;
        }

        $wapDelivery = app::get('wap')->model('delivery')->dump(
            array('delivery_id' => $delivery_id),
            'outer_delivery_bn,order_bn'
        );
        if (empty($wapDelivery['outer_delivery_bn'])) {
            $err_msg = '发货单不存在';
            return false;
        }

        $omeDeliveryObj = app::get('ome')->model('delivery');
        $omeDelivery    = $omeDeliveryObj->dump(
            array('delivery_bn' => $wapDelivery['outer_delivery_bn']),
            'delivery_id'
        );
        $omeDeliveryId = !empty($omeDelivery['delivery_id']) ? intval($omeDelivery['delivery_id']) : 0;
        if ($omeDeliveryId > 0 && $omeDeliveryObj->existExpressNoBill($logi_no, $omeDeliveryId)) {
            $err_msg = '运单号已被其他发货单使用';
            return false;
        }

        if (!empty($wapDelivery['order_bn'])) {
            $order = app::get('ome')->model('orders')->dump(
                array('order_bn' => $wapDelivery['order_bn']),
                'order_id,shop_type'
            );
            if (!empty($order['shop_type']) && $order['shop_type'] == 'luban') {
                $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($order['order_id'], 'order', 'XJJY');
                if ($jyInfo) {
                    $err_msg = '中转订单请联系客服修改直邮后再进行操作';
                    return false;
                }
            }
        }

        if (!$this->checkDeliveryItemStore($delivery_id)) {
            $err_msg = '库存不足，请联系库存管理员处理';
            return false;
        }

        return true;
    }

    /**
     * 补录快递单号
     */
    public function insertExpress($data)
    {
        $dlydata = app::get('ome')->model('dly_corp')->dump(array('type' => $data['logi_code']), '*');
        if (!$dlydata) {
            return array('rsp' => 'fail', 'msg' => '不支持该快递公司的快递单号');
        }
        $data['logi_id'] = $dlydata['corp_id'];
        $data['logi_name'] = $dlydata['name'];

        $deliveryObj = app::get('wap')->model('delivery');
        $deliveryInfo = $deliveryObj->dump(array('delivery_id' => $data['delivery_id']), '*');
        $deliveryBillObj = app::get('wap')->model('delivery_bill');

        $branchObj = kernel::single('ome_branch');
        $o2oStoreInfo = $branchObj->getStoreSenderInfoByBranchId($deliveryInfo['branch_id']);
        $consigner_mobile = '';
        if (!empty($o2oStoreInfo['consignee']['custom_login_mobile'])) {
            $consigner_mobile = $o2oStoreInfo['consignee']['custom_login_mobile'];
        } elseif (!empty($o2oStoreInfo['consignee']['mobile'])) {
            $consigner_mobile = $o2oStoreInfo['consignee']['mobile'];
        } elseif (!empty($o2oStoreInfo['consignee']['telephone'])) {
            $consigner_mobile = $o2oStoreInfo['consignee']['telephone'];
        }

        $store_id = kernel::single('ome_branch')->isStoreBranch($deliveryInfo['branch_id']);
        $channel_id = $store_id;
        $delivery_id = $data['delivery_id'];
        unset($data['delivery_id']);
        $data['action'] = 'addLogiNo';
        $data['delivery_bn'] = $deliveryInfo['outer_delivery_bn'];
        $res = kernel::single('wap_event_trigger_delivery')->doUpdate($channel_id, $data, true);
        if ($res['rsp'] == 'succ') {
            $update_data = array(
                'delivery_id' => $delivery_id,
                'confirm' => 1,
                'process_status' => 1,
                'logi_status' => empty($data['check_logi']) ? '7' : '1',
                'last_modified' => time(),
                'logi_id' => $data['logi_id'],
                'logi_name' => $data['logi_name'],
            );
            $info = $deliveryBillObj->dump(array('delivery_id' => $delivery_id), '*');
            if (!$info) {
                $deliveryBillObj->insert(array(
                    'delivery_id' => $delivery_id,
                    'logi_no' => $data['logi_no'],
                    'logi_no_time' => time(),
                    'print_status' => 3,
                    'consigner_mobile' => $consigner_mobile,
                ));
            } else {
                $deliveryBillObj->update(
                    array('logi_no' => $data['logi_no'], 'logi_no_time' => time(), 'print_status' => 3, 'consigner_mobile' => $consigner_mobile),
                    array('delivery_id' => $delivery_id)
                );
            }
            $deliveryObj->save($update_data);
        }
        return $res;
    }

    /**
     * 检查发货单库存是否足够
     */
    public function checkDeliveryItemStore($delivery_id)
    {
        if (app::get('ome')->getConf('ome.store.auto.replenish.delivery') == 'true') {
            return true;
        }
        $delivery_items = app::get('wap')->model('delivery_items')->getList('*', array('delivery_id' => $delivery_id));
        $deliveryInfo = app::get('wap')->model('delivery')->dump(array('delivery_id' => $delivery_id), 'branch_id');
        foreach ($delivery_items as $item) {
            $sql = 'select store from sdb_ome_branch_product where product_id=' . intval($item['product_id'])
                . ' and branch_id=' . intval($deliveryInfo['branch_id']);
            $row = kernel::database()->selectrow($sql);
            if (empty($row) || $row['store'] < $item['number']) {
                return false;
            }
        }
        return true;
    }

    /**
     * 组装门店发货回写的包裹/子运单参数（对齐奇门 wms.delivery.status_update → package / other_list_0）
     * 供 ome_event_receive_delivery::_dealDeliveryPackage / _save_bill_logi_weight 落库
     *
     * @param array  $params      WAP 发货单
     * @param string $logi_no     主运单号
     * @param array  $dlyCorpInfo o2o_corp_type::get_corp_type 结果
     * @return array{package?:array,bill_logi_weight?:array}
     */
    protected function buildConsignPackageAndBillParams(array $params, $logi_no, array $dlyCorpInfo = array())
    {
        $result = array();
        $outerBn = trim((string) ($params['outer_delivery_bn'] ?? ''));
        $logi_no = trim((string) $logi_no);
        if ($outerBn === '' || $logi_no === '') {
            return $result;
        }

        $omeDly = app::get('ome')->model('delivery')->dump(
            array('delivery_bn' => $outerBn, 'disabled' => 'false'),
            'delivery_id,delivery_bn,logi_id'
        );
        if (empty($omeDly['delivery_id'])) {
            return $result;
        }
        $deliveryId = (int) $omeDly['delivery_id'];

        $logisticsCode = '';
        $logiId = !empty($params['logi_id']) ? $params['logi_id'] : (!empty($omeDly['logi_id']) ? $omeDly['logi_id'] : 0);
        if ($logiId) {
            $corp = app::get('ome')->model('dly_corp')->db_dump(array('corp_id' => $logiId), 'type');
            if (!empty($corp['type'])) {
                $logisticsCode = (string) $corp['type'];
            }
        }

        $now = time();
        $package = array();
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
        if ($package) {
            $result['package'] = $package;
        }

        // 多运单：主运单外的子单写入 bill_logi_weight（对齐奇门 other_list_0）
        $wapDeliveryId = intval($params['delivery_id'] ?? 0);
        if ($wapDeliveryId > 0) {
            $bills = app::get('wap')->model('delivery_bill')->getList(
                'logi_no',
                array('delivery_id' => $wapDeliveryId)
            );
            $billLogiWeight = array();
            foreach ((array) $bills as $bill) {
                $bnNo = trim((string) ($bill['logi_no'] ?? ''));
                if ($bnNo === '' || $bnNo === $logi_no) {
                    continue;
                }
                $billLogiWeight[$bnNo] = 0;
            }
            if ($billLogiWeight) {
                $result['bill_logi_weight'] = $billLogiWeight;
            }
        }

        return $result;
    }

    /**
     * 确认发货回写运单号：优先已录入快递单号，避免误用 OME 发货单号
     *
     * @param array $params
     * @return string
     */
    protected function resolveConsignLogiNo(array $params)
    {
        $logiNo = trim((string) ($params['logi_no'] ?? ''));
        if ($logiNo !== '') {
            return $logiNo;
        }

        $deliveryId = intval($params['delivery_id'] ?? 0);
        if ($deliveryId > 0) {
            $bill = app::get('wap')->model('delivery_bill')->dump(
                array('delivery_id' => $deliveryId),
                'logi_no'
            );
            $logiNo = trim((string) ($bill['logi_no'] ?? ''));
            if ($logiNo !== '') {
                return $logiNo;
            }
        }

        $outerBn = trim((string) ($params['outer_delivery_bn'] ?? ''));
        if ($outerBn !== '') {
            $omeDly = app::get('ome')->model('delivery')->dump(
                array('delivery_bn' => $outerBn, 'disabled' => 'false'),
                'logi_no'
            );
            $logiNo = trim((string) ($omeDly['logi_no'] ?? ''));
            if ($logiNo !== '') {
                return $logiNo;
            }
        }

        return $outerBn;
    }

    /**
     * 门店 WAP 发货实际操作员（与 WAP 工作台 store/index 一致）
     * 参照 wap_ctl_store::index() 使用 ome_func->getDesktopUser()
     *
     * @return array{op_id:int|string,op_name:string,op_no:string}
     */
    public function getConsignOp()
    {
        $opInfo = kernel::single('ome_func')->getDesktopUser();
        $opNo   = '';
        if (!empty($opInfo['op_id'])) {
            $userRow = app::get('desktop')->model('users')->db_dump($opInfo['op_id'], 'op_no');
            $opNo    = $userRow['op_no'] ?: '';
        }

        return [
            'op_id'   => $opInfo['op_id'],
            'op_name' => $opInfo['op_name'] ?: '',
            'op_no'   => $opNo,
        ];
    }
}
