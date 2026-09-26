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

class wap_ctl_logistics extends wap_controller
{
    function __construct($app)
    {
        parent::__construct($app);
    }

    /**
     * 获取运单号
     * @return void
     */
    public function getWaybillNumber()
    {
        if (empty($_POST['delivery_id'])) {
            $this->error('发货单ID参数不能为空');
        }

        $params = [
            'delivery_id' => $_POST['delivery_id'],
            'logi_code' => $_POST['logi_code'],              // 物流公司编码
            'monthly_account' => $_POST['monthly_account'],  // 月结账号， 如果值是default 则默认是小镇月结账号
            'product_type' => $_POST['product_type'],        // 产品类型 0  表示使用默认的产品类型
        ];

        $delivery = app::get('wap')->model('delivery')->dump(array('delivery_id' => $_POST['delivery_id']), 'order_bn,shop_id,shop_type');
        if (!$delivery['shop_type']) {
            $shop = app::get('ome')->model('shop')->dump(array('shop_id'=>$delivery['shop_id']), 'shop_type');
            $shop_type = $shop['shop_type'];
        } else {
            $shop_type = $delivery['shop_type'];
        }

        $params['product_type'] = kernel::single('logisticsmanager_waybill_func')->getCorpProductType($shop_type, $_POST['logi_code'], $_POST['product_type']);

        $result = kernel::single('wap_event_trigger_logistics')->getWaybill($params);
        if ($result['rsp'] == 'fail' || $result['res'] == 'fail') {
            $this->error($result['msg']);
        }
        $this->success('success', $result['data']);
    }

    /**
     * 打印快递单号
     * @return void
     */
    public function doPrint()
    {
        if (empty($_POST['delivery_id'])) {
            $this->error('发货单ID参数不能为空');
        }

        $only_print = (isset($_POST['only_print']) && $_POST['only_print'] == 'true') ? true : false;
        $action = $_POST['action'];
        $this->checkDelivery($action);
        $result = kernel::single('wap_event_trigger_cloudprint')->print($_POST['delivery_id']);
      
        if ($result['rsp'] == 'fail') {
            $this->error('打印失败:' . $result['err_msg']);
        }
        $this->success(empty($result['msg']) ? '打印提交成功' : $result['msg']);
       
    }

    public function checkDelivery($action)
    {
        $delivery_id = $_POST['delivery_id'];
        // 检查发货单是否已撤销

        $msg = '';
        $res = kernel::single("wap_delivery")->checkDeliveryPrint($delivery_id, $action, $msg);
        if (!$res) {
            $result['rsp'] = 'fail';
            $result['msg'] = $msg;
            echo json_encode($result);
            exit;
        }

        return true;
    }

    /**
     * showLogistics
     * @return mixed 返回值
     */
    public function showLogistics()
    {
        $delivery_id = $_REQUEST['delivery_id'];
        $delivery = app::get('wap')->model('delivery')->dump(array('delivery_id' => $delivery_id), '*');

        $omeDeliveryMdl = app::get('ome')->model('delivery');
        $omeDeliveryInfo = $omeDeliveryMdl->dump(array('delivery_bn' => $delivery['outer_delivery_bn']), '*');

        $storeMdl = app::get("o2o")->model("store");
        $storeInfo = $storeMdl->dump(array("branch_id" => $delivery['branch_id']));
        $area_str = '';
        if ($storeInfo['area']) {
            $area = explode(":", $storeInfo['area']);
            $area_str = str_replace("/", "", $area[1]);
            $storeInfo['address'] = $area_str . $storeInfo['addr'];
        }

        $orderId = $omeDeliveryMdl->getOrderIdByDeliveryId($omeDeliveryInfo['delivery_id']);
        $orderInfo = app::get('ome')->model('orders')->dump(array('order_id' => $orderId), '*');

        $waybill = app::get('logisticsmanager')->model('waybill')->dump(array('waybill_number' => $omeDeliveryInfo['logi_no']), 'monthly_account');
        if ($waybill) {
            $delivery['monthly_account'] = $waybill['monthly_account'];
        }

        $deliveryBill = app::get('wap')->model('delivery_bill')->dump(array('delivery_id' => $delivery_id), '*');

        $this->pagedata['title'] = '物流轨迹查询';
        $this->pagedata['orderInfo'] = $orderInfo;
        $this->pagedata['storeInfo'] = $storeInfo;
        $this->pagedata['delivery'] = $delivery;
        $this->pagedata['delivery_bill'] = $deliveryBill;

        if (strpos($omeDeliveryInfo['consignee']['name'], '>>')) {
            $omeDeliveryInfo['consignee']['name'] = substr($omeDeliveryInfo['consignee']['name'], 0, strpos($omeDeliveryInfo['consignee']['name'], '>>'));
        }
        $this->pagedata['omeDeliveryInfo'] = $omeDeliveryInfo;

        $corpMdl = app::get('ome')->model('dly_corp');
        //物流公司信息
        $corpInfo = $corpMdl->dump(array('corp_id' => $delivery['logi_id']), '*');
        //物流渠道类型
        $traceObj = kernel::single('ome_hqepay');

        $rpc_data['order_bn'] = $orderInfo['order_bn'];
        $rpc_data['logi_code'] = $corpInfo['type'];#物流编码
        $rpc_data['company_name'] = $corpInfo['name'];
        $rpc_data['logi_no'] = $omeDeliveryInfo['logi_no'];
        if (strtoupper($corpInfo['type']) == 'SF') {
            $rpc_data['customer_name'] = substr($deliveryBill['consigner_mobile'], -4);
        }
        $rpc_result = [];
        if($traceObj->is_wait_distribute($omeDeliveryInfo['logi_status'])) {
            $msg = '快递未揽收,无物流轨迹';
            $rpc_result['rsp'] = 'fail';
        }else {
            $rpc_result = $traceObj->get_dly_info($rpc_data);
            if ($rpc_result['rsp'] != 'succ') {
                if ($rpc_result['err_msg'] == "'HTTP Error 500: Internal Server Error'") {
                    $msg = '此订单可能缺少物流公司或运单号';
                } else {
                    $msg = $rpc_result['err_msg'];
                    if ($msg == '业务错误[手机尾号不正确]') {
                        $msg = '无法查看物流轨迹，寄件人手机号与快递单号不匹配';
                    }
                }
            } else {
                $msg = $rpc_result['Reason'];
            }
        }

        if ($rpc_result['data']) {
            // 获取第一行的key值
            $this->pagedata['last_logistics_key'] = array_key_first($rpc_result['data']);
        }
        // 物流轨迹
        $this->pagedata['logistics_result'] = array('rsp' => $rpc_result['rsp'], 'msg' => $msg, 'data' => $rpc_result['data']);

        $this->display('order/order_logistics.html');
    }

    /**
     * doAddLogiNo
     * @return mixed 返回值
     */
    public function doAddLogiNo()
    {
        $delivery_id = $_POST['delivery_id'];
        $logi_no = $_POST['logi_no'];
        $logi_code = $_POST['logi_code']; // 物流公司编码

        if (empty($delivery_id) || empty($logi_no) || empty($logi_code)) {
            $this->error('参数不能为空');
        }

        $this->validateLogiNoInput($delivery_id, $logi_no, $logi_code);
        $this->checkDelivery('offline');

        $msg = '';
        // 补录快递单号
        $data = array('delivery_id' => $delivery_id, 'logi_no' => $logi_no, 'logi_code' => $logi_code);
        $res = kernel::single('wap_delivery_process')->insertExpress($data);
        if ($res['rsp'] == 'succ') {
            kernel::single('wap_event_trigger_cloudprint')->updatePrintStatus($delivery_id);
            $this->success('录入运单号成功');
        } else {
            $this->error($res['msg']);
        }
        exit;
    }

    /**
     * doUpdateLogiNo
     * @return mixed 返回值
     */
    public function doUpdateLogiNo()
    {
        $delivery_id = $_POST['delivery_id'];
        $logi_no = $_POST['logi_no'];
        $logi_code = isset($_POST['logi_code']) ? $_POST['logi_code'] : '';
        $wapdeliveryObj = app::get('wap')->model('delivery');
        $wap_delivery_bill_obj = app::get('wap')->model('delivery_bill');

        if (empty($delivery_id) || empty($logi_no)) {
            $this->error('参数不能为空');
        }

        $wap_delivery_info = $wapdeliveryObj->dump(array('delivery_id' => $delivery_id), 'outer_delivery_bn,logi_id,logi_status,status');
        if (!$wap_delivery_info) {
            $this->error('wap 发货单不存在');
        }

        // 首次录入：仅保存运单号（insertExpress），不触发门店发货完成（consign）
        $delivery_bill = $wap_delivery_bill_obj->dump(array('delivery_id' => $delivery_id), 'logi_no');
        $is_first_entry = empty($delivery_bill['logi_no']);
        if ($is_first_entry) {
            if (empty($logi_code)) {
                $this->error('请选择快递公司');
            }
            $this->validateLogiNoInput($delivery_id, $logi_no, $logi_code);
            $this->checkDelivery('offline');
            $data = array('delivery_id' => $delivery_id, 'logi_no' => $logi_no, 'logi_code' => $logi_code);
            $res = kernel::single('wap_delivery_process')->insertExpress($data);
            if ($res['rsp'] == 'succ') {
                $this->success('录入运单号成功');
            } else {
                $this->error($res['msg']);
            }
            exit;
        }

        if (!in_array($wap_delivery_info['logi_status'], array('1', '2', '5', '6', '7'))) {
            $this->error('当前物流状态不允许修改运单号');
        }

        $dlydata = app::get('ome')->model("dly_corp")->dump(array('corp_id' => $wap_delivery_info['logi_id']), '*');
        $logi_code = strtoupper($dlydata['type']);

        // 验证快递单号:最少8位,只允许字母和数字
        if (!preg_match('/^[a-zA-Z0-9]{8,}$/', $logi_no)) {
            $this->error('快递单号必须至少8位且只能包含字母和数字');
        }

        // 验证圆通快递单号：YT+12/13位数字
        if($logi_code == 'YT'){
            if(!preg_match('/^YT[0-9]{12,13}$/', $logi_no)){
                $this->error('圆通快递单号必须以YT开头+12/13位数字');
            }
        }
        // 验证德邦快递单号：DB+12/13位数字
        if($logi_code == 'DBL'){
            if(!preg_match('/^DP[A-Z]{1}[0-9]{12}$/', $logi_no)){
                $this->error('德邦物流单号必须以DP开头+1位大写字母+12位数字');
            }
        }

        // 验证中通快递单号：7+12至14位数字
        if($logi_code == 'ZTO'){
            if(!preg_match('/^7[0-9]{12,14}$/', $logi_no)){
                $this->error('中通快递单号必须以7开头+12至14位数字');
            }
        }

        $orderBn = app::get("wap")->model('delivery')->dump(array('delivery_id' => $delivery_id), 'order_bn');
        $order = app::get("ome")->model('orders')->dump(array('order_bn' => $orderBn), 'order_bn,order_id,shop_type,shop_id');

        if ($order['shop_type'] == 'luban') {
            $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($order['order_id'], 'order', 'XJJY');
            if ($jyInfo) {
                $this->error('中转订单请联系客服修改直邮后再进行操作');
            }
        }

        $isCheckStore = kernel::single('wap_delivery_process')->checkDeliveryItemStore($delivery_id);
        if (!$isCheckStore) {
            $this->error('库存不足，请联系库存管理员处理');
        }


        // 修改快递单号
        $update_data = array('logi_no' => $logi_no);

        $wapdeliveryObj = app::get('wap')->model('delivery');
        $omedeliveryObj = app::get('ome')->model('delivery');
        $wmsdeliveryObj = app::get('wms')->model('delivery');
        $wap_delivery_bill_obj = app::get('wap')->model('delivery_bill');
        $ome_delivery_bill_obj = app::get('ome')->model('delivery_bill');
        $wms_delivery_bill_obj = app::get('wms')->model('delivery_bill');
        $ome_delivery_package_obj = app::get('ome')->model('delivery_package');
        $order_obj = app::get('ome')->model('orders');
        $operationLogObj = app::get('ome')->model('operation_log');

        $wap_delivery_info = $wapdeliveryObj->dump(array('delivery_id' => $delivery_id), 'outer_delivery_bn');
        if(!$wap_delivery_info){
            $this->error('wap 发货单不存在');
        }

        $ome_delivery_info = $omedeliveryObj->dump(['delivery_bn'=>$wap_delivery_info['outer_delivery_bn']]);
        if(!$ome_delivery_info){
            $this->error('ome 发货单不存在');
        }

        $wms_delivery_info = $wmsdeliveryObj->dump(array('outer_delivery_bn' => $ome_delivery_info['delivery_bn']), '*');

        //订单数量(抖音和视频号不支持拆单的修改)
        $order_items = app::get('ome')->model('order_items')->getList('*',['order_id'=>$order['order_id'],'delete'=>'false']);
        $goods = [];
        foreach ($order_items as $oinfo){
            $goods[] = ['product_cnt'=>intval($oinfo['nums']),'product_id'=>$oinfo['shop_goods_id'],'sku_id'=>$oinfo['shop_product_id']];
        }

        //如果是商城
        if ($order['shop_type'] == XCXSHOPTYPE) {
            $params = array(
                'order_bn' => $order['order_bn'],
                'shop_id'  => $order['shop_id'],
                'delivery_bn' => $ome_delivery_info['delivery_bn'],
                'delivery_id' => $ome_delivery_info['delivery_id'],
                'old_code' => $ome_delivery_info['logi_no'],
                'new_code' => $logi_no,
            );
            $err_msg = '';
            $res = kernel::single("ome_ecapi_delivery")->update_delivery_code($params, $err_msg);
            if (!$res) {
                $this->error($err_msg?$err_msg:'商城更新物流单号失败');
            }
        }
        else {
            //同步给平台
            $params = array(
                'order_bn' => $order['order_bn'],
                'delivery_bn' => $ome_delivery_info['delivery_bn'],
                'logi_code' => $logi_code,
                'logi_no' => $logi_no,
                'goods' => json_encode($goods)
            );
            $res = kernel::single('erpapi_router_request')->set('shop', $order['shop_id'])->delivery_logistics_edit($params);
            if ($res['rsp'] != 'succ') {
                $this->error($res['err_msg'] ? $res['err_msg'] : $res['error_msg']);
            }
        }

        try {
            kernel::database()->beginTransaction();

            //数据修改
            $wap_delivery_bill_obj->update($update_data,['delivery_id'=>$delivery_id]);
            $omedeliveryObj->update($update_data,['delivery_id'=>$ome_delivery_info['delivery_id']]);
            $ome_delivery_bill_obj->update($update_data,['delivery_id'=>$ome_delivery_info['delivery_id']]);
            $ome_delivery_package_obj->update($update_data,['delivery_id'=>$ome_delivery_info['delivery_id']]);
            $order_obj->update($update_data,['order_id'=>$order['order_id']]);
            $wms_delivery_bill_obj->update($update_data, ['delivery_id' => $wms_delivery_info['delivery_id']]);

            //添加日志
            $memo = "h5修改物流单号为:".$logi_no;
            $operationLogObj->write_log('order_modify@ome',$order['order_id'],$memo);
            $operationLogObj->write_log('delivery_modify@ome',$ome_delivery_info['delivery_id'],$memo);
            //wms
            if($wms_delivery_info) {
                $operationLogObj->write_log('delivery_logi@wms',$wms_delivery_info['delivery_id'],$memo);
            }

            kernel::database()->commit();
        } catch (\Exception $e) {
            kernel::database()->rollBack();
            $this->error('修改运单号失败');
        }

        //重新订阅快递鸟
        kernel::single('ome_event_trigger_shop_hqepay')->hqepay_pub($ome_delivery_info['delivery_id']);

        $this->success('修改快递单号成功');

    }

    /**
     * 录入/修改运单号共用校验
     */
    protected function validateLogiNoInput($delivery_id, $logi_no, $logi_code)
    {
        $processLib = kernel::single('wap_delivery_process');
        $err_msg    = '';
        if (!$processLib->validateLogiNoFormat($logi_no, $logi_code, $err_msg)) {
            $this->error($err_msg);
        }
        if (!$processLib->validateLogiNoNotUsedByOthers($delivery_id, $logi_no, $err_msg)) {
            $this->error($err_msg);
        }
    }
}
