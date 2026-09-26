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
 * 电子发票接口事件类
 *
 * @author xiayuanjun<xiayuanjun@shopex.cn>
 * @version 0.1
 */
class invoice_event_trigger_einvoice extends invoice_event_response
{
    /**
     * 电子发票开票(蓝票)发起方法
     * 
     * @param string $shop_id 来源店铺ID
     * @param array $data 开电子发票（开蓝票）通知数据信息
     * @param string $error_msg
     * @return array
     */
    public function create($shop_id, &$data, &$error_msg=null)
    {
        $data['ship_tel'] = ($index = strpos($data['ship_tel'], '>>')) ? substr($data['ship_tel'], 0, $index) : $data['ship_tel'];
        //开票配置信息
        $rs         = kernel::single('invoice_func')->get_order_setting($shop_id, $data['mode']);
        $rs_setting = $rs[0];
        
        #电子发票,没有开票信息设置的，不创建
        if ($data['mode'] == 1 && empty($rs_setting)) {
            $error_msg = '当前店铺没有配置电子发票开票信息';
            return false;
        }

        $updateData['payee_name']     = $data['payee_name']     = $rs_setting["payee_name"] ? $rs_setting["payee_name"] : '';
        $updateData['tax_no']         = $data['tax_no']         = $rs_setting["tax_no"] ? $rs_setting["tax_no"] : '';
        $updateData['address']        = $data['address']        = $rs_setting["address"] ? $rs_setting["address"] : '';
        $updateData['telephone']      = $data['telephone']      = $rs_setting["telphone"] ? $rs_setting["telphone"] : '';
        $updateData['payee_operator'] = $data['payee_operator'] = $rs_setting["payee_operator"] ? $rs_setting["payee_operator"] : '';
        $updateData['bank']           = $data['bank']           = $rs_setting["bank"] ? $rs_setting["bank"] : '';
        $updateData['bank_no']        = $data['bank_no']        = $rs_setting["bank_no"] ? $rs_setting["bank_no"] : '';
        $updateData['payee_checker']  = $data['payee_checker']  = $rs_setting['payee_checker'] ? $rs_setting['payee_checker'] : '';
        $updateData['payee_receiver'] = $data['payee_receiver'] = $rs_setting['payee_receiver'] ? $rs_setting['payee_receiver'] : '';
        $updateData['tax_rate'] = $data['tax_rate'] = $rs_setting['tax_rate'] ? $rs_setting['tax_rate'] : '';
        //组织发票数据
        $sdf = kernel::single('invoice_event_trigger_data_router')->set_shop_id($shop_id)->getEinvoiceRequestParams($data, 'blue');
        if (!$sdf || $sdf['rsp']=='fail'){
            $error_msg = $sdf['error_msg'];
            return false;
        }
        // 保存开票内容
        if ($data['serial_no']) {
            $invEleItemMdl = app::get('invoice')->model('order_electronic_items');
            $invEleItemMdl->update(['content' => json_encode($sdf)],['serial_no' => $data['serial_no']]);
        }
        //更新配置信息
        app::get('invoice')->model('order')->update($updateData,['id'=>$data['id']]);
        //判断是否加密
        $shop = app::get('ome')->model('shop')->db_dump($data['shop_id'],'node_id');

        $encryptKey = array (
            'ship_tel'         => $data['ship_tel'],
            'ship_bank_no'     => $data['ship_bank_no'],
            'ship_company_tel' => $data['ship_company_tel'],
        );
        $result = kernel::single('ome_security_router', $data['shop_type'])->is_encrypt($encryptKey,'invoice');
        if($result){
            $sdf['s_node_id']        = $shop['node_id'];
            $sdf['s_node_type']      = $data['shop_type'];
            $sdf['order_bns']        = $data['order_bn'];
            $sdf['ship_tel']         = $data['ship_tel'];
            $sdf['ship_bank']        = $data['ship_bank'];
            $sdf['ship_bank_no']     = $data['ship_bank_no'];
            $sdf['ship_company_tel'] = $data['ship_company_tel'];

            $sdf['is_encrypt']  = true;
        }
        
        //request
        if ($sdf['order_bn'] && $sdf['invoice_type'] == 'merge') {
            $orderBns = explode(',',$sdf['order_bn']);
            if ($orderBns > 2) {
                list($firstOrder,$secondOrder) = explode(',',$sdf['order_bn']);
                $sdf['order_bn'] = $firstOrder.','.$secondOrder;
            }
        }
        $invoiceMdl = app::get('invoice')->model('order');
        //开票发起前 标记开电子发票同步状态为 开蓝票中
        $filter_arr = array("id" => $sdf['order']["id"]);
        $update_sync_arr = array (
            "sync"               => "1",
            "channel_id"         => $sdf['order']['channel_id'],
        );//提前标记开蓝票中的状态
        if (isset($data['channel_golden_tax_version'])) {
            $update_sync_arr['golden_tax_version'] = $data['channel_golden_tax_version'];
        }

        $invoiceMdl->update($update_sync_arr, $filter_arr);

        $result = kernel::single('erpapi_router_request')->set('invoice', $shop_id)->invoice_create($sdf);

        // 失败处理
        if($result["rsp"] == "fail"){
            $update["sync"] = "2"; //开蓝失败
            $update["is_status"] = "0"; //保持为：未开票状态
            $update["sync_msg"] = $result["err_msg"];

            //更新发票记录主表
            $filter = ["id" => $data['id']];
            $invoiceMdl->update($update, $filter);
            kernel::single('monitor_event_notify')->addNotify('invoice_result_error', [
                'invoice_type' => 'B2C发票开蓝失败',
                'order_bn'     => $data['order_bn'],
                'errmsg'       => $result['err_msg'],
            ]);
        }else{
            //百望开票响应没有开票状态，直接更新为开票中然后去走获取开票结果脚本更新
            if ($data['channel_type'] == 'baiwang') {
                app::get('invoice')->model('order_electronic_items')->update(['invoice_status' => '10'],['serial_no' => $data['serial_no']]);
            }

            if (is_string($result['data'])) {
                $result['data'] = @json_decode($result['data'], true);
            }
            $result['data']['msg_id'] = $result['msg_id'];
            // 模拟接口回传, 触发更新
            kernel::single('erpapi_router_response')->set_node_id($data['channel_node_id'])->set_api_name('invoice.order.status_update')->dispatch($result['data']);
        }

        return $result;
    }

    /**
     * 电子发票开票作废(红票)发起方法
     * 
     * @param string $shop_id 来源店铺ID
     * @param array $data  作废电子发票（冲红）通知数据信息
     * @return array
     */
    public function cancel($shop_id, &$data)
    {
        $data['ship_tel'] = ($index = strpos($data['ship_tel'], '>>')) ? substr($data['ship_tel'], 0, $index) : $data['ship_tel'];

        $dataLib = kernel::single('invoice_event_trigger_data_router')->set_shop_id($shop_id);

        // 确认当前订单红冲是否需确认
        $needConfirm = $dataLib->checkCancelConfirm($data);

        // 如需确认,则先发起红字申请单,待确认后红冲
        if($needConfirm){
            $result = kernel::single('invoice_event_trigger_redapply')->create($shop_id, $data);

            // 格式化错误信息
            $result['msg'] = '红字确认单申请失败：'.$result['msg'].'('.$result['msg_id'].')';
            if ($result['rsp'] == 'succ'){
                $result['msg'] = '红字确认单申请中('.$result['msg_id'].')';
            }else{
                //失败预警
                kernel::single('monitor_event_notify')->addNotify('invoice_result_error', [
                    'invoice_type' => 'B2C红字确认单申请失败',
                    'order_bn'     => $data['order_bn'],
                    'errmsg'       => $result['err_msg'],
                ]);
            }

            return $result;
        }

        $sdf = $dataLib->getEinvoiceRequestParams($data, 'red');

        if (!$sdf) {
            return $this->send_error('冲红参数错误');
        }
        // 判断是否加密
        $shop = app::get('ome')->model('shop')->db_dump($data['shop_id'],'node_id');
        if (kernel::single('ome_security_router',$data['shop_type'])->is_encrypt(array ('ship_tel' => $data['ship_tel']),'invoice')) {
            $sdf['s_node_type'] = $data['shop_type'];
            $sdf['s_node_id']   = $shop['node_id'];
            $sdf['order_bns']   = $data['order_bn'];
            $sdf['is_encrypt']  = true;
        }
    
        if ($sdf['order_bn'] && $sdf['invoice_type'] == 'merge') {
            $orderBns = explode(',',$sdf['order_bn']);
            if ($orderBns > 2) {
                list($firstOrder,$secondOrder) = explode(',',$sdf['order_bn']);
                $sdf['order_bn'] = $firstOrder.','.$secondOrder;
            }
        }
        // 开票发起前 标记开电子发票同步状态为 开红票中
        $invoiceMdl = app::get('invoice')->model('order');
        $filter_arr = array("id" => $data['id']);
        $update_sync_arr = array("sync" => "4");//标记开红票中的状态
        $invoiceMdl->update($update_sync_arr, $filter_arr);
        //百望取消走红字确认单
        if ($data['channel_type'] != 'baiwang' || $data['channel_golden_tax_version'] != 1) {
            $result = kernel::single('erpapi_router_request')->set('invoice',$shop_id)->invoice_cancel($sdf);
        }else{
            $sdf = $dataLib->getCancelApplyRequestParams($data);

            if (!$sdf) {
                return $this->send_error('冲红红字确认单参数错误');
            }

            $result = kernel::single('erpapi_router_request')->set('invoice', $shop_id)->redapply_create($sdf);
            $createRes = is_string($result['data']) ? json_decode($result['data'], true) : $result['data'];
            $responseParams = [
                'invoice' => $data,
                'data'    => $createRes,
            ];

            kernel::single('erpapi_router_response')->set_node_id($data['channel_node_id'])->set_api_name('invoice.redapply.status_update')->dispatch($responseParams);
        }

        // 格式化错误信息
        $result['msg'] = '冲红失败：'.$result['msg'].'('.$result['msg_id'].')';
        if ($result['rsp'] == 'succ'){
            $result['msg'] = '冲红中('.$result['msg_id'].')';
        }

        // 失败处理
        if ($result["rsp"] == "fail") {

            $update = [
                'sync' => '5',
                'is_status' => '1',
                'sync_msg' => $result['err_msg'],
            ];

            $filter = ['id' => $data['id'], 'sync' => ['3', '4', '5']];
            $invoiceMdl->update($update, $filter);

            kernel::single('monitor_event_notify')->addNotify('invoice_result_error', [
                'invoice_type' => 'B2C发票冲红失败',
                'order_bn'     => $data['order_bn'],
                'errmsg'       => $result['err_msg'],
            ]);
        } else {
            if (is_string($result['data'])) {
                $result['data'] = @json_decode($result['data'], true);
            }
            $result['data']['msg_id'] = $result['msg_id'];
            // 模拟接口回传, 触发更新
            if ($data['channel_type'] != 'baiwang' || $data['channel_golden_tax_version'] != 1) {
                kernel::single('erpapi_router_response')->set_node_id($data['channel_node_id'])->set_api_name('invoice.order.status_update')->dispatch($result['data']);
            }
        }
        return $result;
    }
    
    /**
     * 电子发票开票(蓝票或者红票)后获取发票开票结果查询
     * @param string $shop_id 来源店铺ID
     * @param array $data 开电子发票（开蓝票或者红票）后获取发票开票结果查询 通知数据信息
     * @return array
     */
    public function getEinvoiceCreateResult($item_id, $sendsms=true)
    {
        $invEleItemMdl = app::get('invoice')->model('order_electronic_items');
        $invOrderMdl   = app::get('invoice')->model('order');
        $shopMdl       = app::get('ome')->model('shop');
        $opObj = app::get('ome')->model('operation_log');

        $item = $invEleItemMdl->db_dump($item_id);
        if ($item['invoice_status'] == '0') {
            return array('true');
        }
        if (!$item['serial_no']) {
            return array('fail','开票交易流水号不存在');
        }

        $invoice = $invOrderMdl->db_dump($item['id']);
        if ( $invoice['mode'] != '1' || !in_array($invoice['sync'],array('1','4','7','9'))) {
            return array('fail','电子发票未同步中，无需同步');
        }

        $shop = $shopMdl->db_dump($invoice['shop_id']);

        $data = array(
            'serial_no'         => $item['serial_no'],
            'order_bn'          => $invoice['order_bn'],
            'payee_register_no' => $invoice['tax_no'],
            'node_type'         => $shop['node_type'],
            'node_id'           => $shop['node_id'],
            'shop_id'           => $shop['shop_id'],
            'billing_type'      => $item['billing_type'],
            'sendsms'           => $sendsms,
            'create_time'       => $invoice['create_time'],
            'invoice_apply_bn'  => $invoice['invoice_apply_bn'],
        );
        $data['order_electronic_items'] = $item;
        $channelMdl = app::get('invoice')->model('channel');
        $channel = $channelMdl->dump($invoice['channel_id']);
        $result = kernel::single('erpapi_router_request')->set('invoice',$invoice['shop_id'])->invoice_create_result($data);
        if ($result["rsp"] == "succ") {

            // 模拟接口回传, 触发更新

            if(is_string($result['data'])){
                $result['data'] = @json_decode($result['data'],true);
            }
            $result['data']['msg_id'] = $result['msg_id'];
            if ($data['billing_type'] == '2') {
                $result['data']['invoice_apply_bn']        = $invoice['invoice_apply_bn'];
                $result['data']['response']['success'][0]['invoiceType'] = '1';
            }
            kernel::single('erpapi_router_response')->set_node_id($channel['node_id'])->set_api_name('invoice.order.status_update')->dispatch($result['data']);
        }else{
            $result['rsp'] = 'succ';
            $msg = '开票结果查询：'.$result['err_msg'].'('.$result['msg_id'].')';
            $opObj->write_log('invoice_cancel@invoice', $item['id'], '冲红失败:'.$msg);
            //为开票的单据，设置同步状态为开蓝失败
            if ($invoice['is_status'] == '0') {
                $updateData = ['sync'=>'2'];
            }else{
                $updateData = ['sync'=>'5'];
            }
            $invOrderMdl->update($updateData,['id'=>$item['id']]);
        }

        return array($result['rsp'], $result['err_msg']);
    }
    
    /**
     * 电子发票获取url发起方法
     * @param string $shop_id 来源店铺ID
     * @param array $data  获取电子发票url（蓝票或红票）通知数据信息
     * @return array
     */
    public function getInvoiceUrl($shop_id, &$data)
    {
        $sdf = kernel::single('invoice_event_trigger_data_router')->set_shop_id($shop_id)->getEinvoiceGetUrlRequestParams($data);
        if (!$sdf) return false;
        
        $result = kernel::single('erpapi_router_request')->set('invoice',$shop_id)->invoice_get_url($sdf);
        
        return $result;
    }

    /**
     * 查询已回传淘宝的电子发票
     * @param string $shop_id 来源店铺ID
     * @param array $data  获取已回传淘宝的电子发票通知数据信息
     * @return array
     */
    public function getEinvoiceInfo($shop_id, &$data)
    {
        $result = kernel::single('erpapi_router_request')->set('invoice',$shop_id)->invoice_get_info($data);
        
        return $result;
    }
    
    /**
     * 回流天猫前 电子发票状态更新
     * @param string $shop_id 来源店铺ID
     * @param array $data  ERP在给tmall开电子发票蓝票和红票前 需要调用此接口把开票状态同步给tmall
     */
    public function einvoicePrepare($shop_id, &$data)
    {
        $result = kernel::single('erpapi_router_request')->set('invoice',$shop_id)->invoice_einvoice_prepare($data);
        
        return $result;
    }
    
    /**
     * 电子发票回流天猫
     * @param string $shop_id 来源店铺ID
     * @param array $data 电子发票回流天猫 通知数据信息
     */
    public function uploadTmall($shop_id, &$data)
    {
        $result = kernel::single('erpapi_router_request')->set('invoice',$shop_id)->invoice_upload_tmall($data);
        
        return $result;
    }

    /**
     * 开票上传
     *
     * @param int $item_id 
     * @return void
     * @author 
     **/
    public function upload($item_id, $sync = false)
    {
        $eOrderMdl = app::get('invoice')->model('order');
        $eItemMdl  = app::get('invoice')->model('order_electronic_items');

        $electronic = $eItemMdl->dump($item_id);

        if ($electronic['upload_tmall_status'] == '2') return array('rsp'=>'succ');

        $invoice = $eOrderMdl->dump($electronic['id']);

        if (!in_array($invoice['sync'], array('3','6'))) return array('rsp'=>'fail','msg'=>'尚未同步开票结果');

        // 京东：订单未签收不允许上传，失败留给 api_fail 下次重试
        $shop = app::get('ome')->model('shop')->db_dump($invoice['shop_id'], 'node_type');
        if ($shop['node_type'] == '360buy') {
            $order = app::get('ome')->model('orders')->db_dump($invoice['order_id'], 'end_time');
            if (empty($order['end_time'])) {
                return array('rsp' => 'fail', 'msg' => '京东订单未签收，暂不可上传');
            }
        }

        $sdf = kernel::single('invoice_event_trigger_data_router')->set_shop_id($invoice['shop_id'])->getUploadParams($invoice,$electronic);

        if (!$sdf) return false;
        
        // 注册Service，判断是否支持合并开发票
        foreach(kernel::servicelist('ome.service.invoice.index.action') as $object)
        {
            if(method_exists($object, 'check_upload_invoice_platform')){
                $checkResult = $object->check_upload_invoice_platform($invoice);
                if(isset($checkResult['rsp']) && $checkResult['rsp'] == 'succ'){
                    if(isset($checkResult['data']['is_allow_update_succ']) && $checkResult['data']['is_allow_update_succ'] == 'true'){
                        return array('rsp'=>'succ', 'msg'=>$checkResult['data']['msg']);
                    }
                }
            }
        }
        
        $result = kernel::single('erpapi_router_request')->set('shop',$invoice['shop_id'])->invoice_upload($sdf, $sync);
        
        return $result;
    }

    /**
     * 创建板式文件
     * @Author: XueDing
     * @Date: 2024/8/12 3:59 PM
     * @param $shop_id
     * @param $data
     * @return mixed
     */
    public function createPdfFile($shop_id, &$data)
    {
        $dataLib = kernel::single('invoice_event_trigger_data_router')->set_shop_id($shop_id);

        $sdf = $dataLib->getEinvoiceCreateFileRequestParams($data);

        $result = kernel::single('erpapi_router_request')->set('invoice',$shop_id)->invoice_createPdfFile($sdf);

        return $result;
    }
}
