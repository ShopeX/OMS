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

class invoice_event_receive_invoice extends invoice_event_response
{
    private $_invoice;
    private $_invoice_apply_bn;
    private $_invoiceMdl;
    private $_invEleItemMdl;
    private $_orderMdl;

    /**
     * @param array $constructParams 构造传参,  [invoice_apply_bn => 开票申请单号]
     */
    public function __construct($constructParams = [])
    {
        parent::__construct();
        $this->_invoice_apply_bn = $constructParams['invoice_apply_bn'];
        $this->_invoiceMdl       = app::get('invoice')->model('order');
        $this->_invEleItemMdl    = app::get('invoice')->model('order_electronic_items');
        $this->_orderMdl         = app::get('ome')->model('orders');
    }

    /**
     *
     * 发货通知单处理总入口
     * @param array $data
     */
    public function update($dataList)
    {
        foreach ($dataList as $data) {
            $this->_invoice = null;
            try {
                $this->_check($data);
                //冲红成功获取冲红成功发票信息
                if ($data['sync'] == '6' && empty($data['url'])) {
                    $this->getRedInvoice($data);
                }
            } catch (Exception $e) {
                $msg = $e->getMessage();
                return $this->send_error($msg);
            }

            // 明细表更新
            $this->_updateItem($data);
            // 主表更新
            $this->_updateInvoice($data);

            $this->_afterUpdate($data);
        }

        return $this->send_succ('更新成功');
    }

    private function _afterUpdate($data)
    {
        switch ($data['sync']) {
            // 已开票
            case 3:
                // 更新订单
                $this->_updateOrder($data);
                // 更新订单开票申请表
                $this->_updateOrderInvoice($data);
                // 发短信^自动上传
                $this->_uploadInvoice($data);
                break;
            // 已红冲
            case 6:
                $this->_updateOrder($data);
                // 发短信^自动上传
                $this->_uploadInvoice($data);
                // 冲红成功根据改票信息新建发票
                $this->_reinvoice($data);
                break;
        }
    }

    private function _check($data)
    {
        // 字段校验
        $requireCols = ['is_status', 'invoice_status', 'sync', 'invoice_apply_bn'];
        foreach ($requireCols as $requireCol) {
            if (!isset($data[$requireCol])) {
                throw new Exception('必要更新参数:' . $requireCol . "缺失");
            }
        }

        // 获取发票对应记录
        $invOrderMdl = $this->_invoiceMdl;
        $filter      = ['invoice_apply_bn' => $data['invoice_apply_bn']];
        $invOrder    = $invOrderMdl->dump($filter);
        if (!$invOrder) {
            throw new Exception('发票信息不存在');
        }

        // 获取发票明细表对应记录
        $invEleItemMdl = $this->_invEleItemMdl;
        $itemFilter    = ['id' => $invOrder['id']];
        // invoice_type定位
        $itemFilter['billing_type'] = $data['invoice_type'] == '0' ? '1' : '2';
        $invEleItem                 = $invEleItemMdl->dump($itemFilter);
        if (!$invEleItem) {
            throw new Exception('发票明细信息不存在');
        }

        $invOrder['order_electronic_items'] = $invEleItem;
        // 注入数据至类属性
        $this->_invoice = $invOrder;

        // 状态校验
        $this->_checkStatus($data);

    }

    /**
     *
     * 检查采购单是否有效
     * @param  $po_bn 采购单编号
     * @param  $status 根据传入状态判断对应状态是否可以操作
     */
    public function _checkStatus($data)
    {
        $sync = $data['sync'];
        //   0 => '未同步',
        //   1 => '开蓝票中',
        //   2 => '开蓝失败',
        //   3 => '开蓝成功',
        //   4 => '开红票中',
        //   5 => '冲红失败',
        //   6 => '冲红成功',
        //   7 => '冲红确认中',
        //   8 => '冲红确认失败',
        //   9 => '冲红确认成功',
        switch ($sync) {
            // 开票成功
            case '3':
                if ($this->_invoice['sync'] == '3') {
                    throw new Exception('已开票不可重复开票');
                }
                if ($this->_invoice['sync'] == '6') {
                    throw new Exception('已冲红成功不可开蓝');
                }
                break;
            // 冲红成功
            case '6':
                if ($this->_invoice['sync'] == '6') {
                    throw new Exception('已冲红不可重复冲红');
                }
                break;
            // 冲红成功
            case '9':
                if ($this->_invoice['sync'] == '9') {
                    throw new Exception('已冲红确认不可重复确认');
                }
                break;
        }
        return true;
    }

    private function _cancel($data)
    {
        // 推红冲
        $rs_invoice                        = kernel::single('invoice_check')->checkInvoiceCancel($data['id']);
        $rs_invoice['invoice_action_type'] = 2;
        $rs_invoice                        = kernel::single('invoice_electronic')->getEinvoiceSerialNo($rs_invoice, "2");
        kernel::single('invoice_event_trigger_einvoice')->cancel($rs_invoice['shop_id'], $rs_invoice);
    }

    private function _updateItem($data)
    {
        // 发票本地存储（发票是否存储到本地服务器）
        $is_save_file = app::get('ome')->getConf('ome.invoice.local.storage');
        
        // data
        $updateInfo = [
            'invoice_status' => $data['invoice_status']
        ];
        $file_path = [];

        // 特殊状态
        switch ($data['invoice_status']) {
            // 已开票
            // 已红冲
            case 0:
            case 5:
                $updateInfo['invoice_code'] = $data['invoice_code'] ?? '';
                $updateInfo['invoice_no']   = $data['invoice_no'];
                $updateInfo['url']          = $data['url'] ?? '';
                $updateInfo['create_time']  = $data['invoice_date'];
                $pdfUrl = array_key_exists('pdf_url', $data) ? $data['pdf_url'] : $data['url'];
                // 文件上传
                if ($pdfUrl) {
                    // 发票是否存储到本地服务器
                    if($is_save_file == 'on'){
                        $file_id = $this->_uploadInvoiceFile($pdfUrl);
                    }else{
                        $file_id = 0;
                    }
                    
                    $updateInfo['file_id'] = $file_id;
                    $file_path['pdf_url'] = $pdfUrl;
                }
                if($data['xml_url']){
                    // 发票是否存储到本地服务器
                    if($is_save_file == 'on'){
                        $xml_file_id = $this->_uploadInvoiceFile($data['xml_url']);
                    }else{
                        $xml_file_id = 0;
                    }
                    
                    $updateInfo['xml_file_id'] = $xml_file_id;
                    $file_path['xml_url'] = $data['xml_url'];
                }
                if($data['ofd_url']){
                    // 发票是否存储到本地服务器
                    if($is_save_file == 'on'){
                        $ofd_file_id = $this->_uploadInvoiceFile($data['ofd_url']);
                    }else{
                        $ofd_file_id = 0;
                    }
                    
                    $updateInfo['ofd_file_id'] = $ofd_file_id;
                    $file_path['ofd_url'] = $data['ofd_url'];
                }
                if ($file_path) {
                    $updateInfo['file_path'] = json_encode($file_path,JSON_UNESCAPED_SLASHES);
                }
                break;

        }
        if ($this->_invoice['order_electronic_items']['serial_no'] != $data['serial_no']) {
            $updateInfo['serial_no'] = $data['serial_no'];
        }

        $itemFilter = [
            'item_id' => $this->_invoice['order_electronic_items']['item_id']
        ];

        $invEleItemMdl = $this->_invEleItemMdl;
        // 更新ITEM
        $invEleItemMdl->update($updateInfo, $itemFilter);
    }

    private function _updateInvoice($data)
    {
        // 更新明细表
        $update = [
            'sync'        => $data['sync'],
            'is_status'   => $data['is_status'],
            'update_time' => time(),
            'sync_msg'    => '',
        ];

        switch ($data['sync']) {
            // 已开票
            // 已红冲
            case 3:
            case 6:
                $update['invoice_code']    = $data['invoice_code'] ?? '';
                $update['invoice_no']      = $data['invoice_no'];
                $update['dateline']        = $data['invoice_date'];
                $update['is_make_invoice'] = '0';
                break;
        }

        $filter = [
            'id' => $this->_invoice['id']
        ];

        $invOrderMdl = $this->_invoiceMdl;

        $invOrderMdl->update($update, $filter);
    }

    private function _uploadInvoiceFile($url)
    {
        $file_id = kernel::single('invoice_order')->save_base_file(['url' => $url, 'order_bn' => $this->_invoice['order_bn']]);

        return $file_id;
    }

    private function _updateOrder($data)
    {
        $update = [
            'tax_no' => $data['invoice_no']
        ];

        $filter   = [
            'order_id' => $this->_invoice['order_id']
        ];
        $orderMdl = app::get('ome')->model('orders');
        $orderMdl->update($update, $filter);
    }

    private function _updateOrderInvoice($data)
    {
        $update = [
            'tax_no' => $data['invoice_no']
        ];

        $filter = [
            'order_id' => $this->_invoice['order_id']
        ];

        $orderInvoiceMdl = app::get('ome')->model('order_invoice');
        $orderInvoiceMdl->update($update, $filter);
    }

    private function _reinvoice($data)
    {
        if ($this->_invoice['order_electronic_items']['billing_type'] != '2' || $data['sync'] != '6' || $data['is_status'] != '2') {
            return;
        }
        // todo 更新信息覆盖
        //冲红成功根据改票信息新建发票
        if ($this->_invoice['changesdf'] && $this->_invoice['change_status'] == '1') {
            $params = array_merge($this->_invoice, json_decode($this->_invoice['changesdf'], 1));
            unset($params['is_status'], $params['sync'], $params['is_print'], $this->_invoice['itemsdf']);
            $type = 'change_ticket';
            $params['is_edit']     = 'true';
        }
        if ($this->_invoice['action_type'] != 'create_order') {
            //自动重新开票
            $params = !empty($params) ? $params : $this->_invoice;
            unset($params['is_status'],$params['sync']);
            $type = $this->_invoice['action_type'];
            $params['is_make_invoice'] = '1';
        }

        if ($params) {
            $data = kernel::single('invoice_order')->formatAddData($params);
            list($res,$msg) = kernel::single('invoice_process')->newCreate($data,$type);
        }
        //冲红成功修改合票发票
        kernel::single('invoice_order')->cancelSuccessRes($this->_invoice['id']);
    }

    private function _uploadInvoice($data)
    {
        app::get('ome')->model('operation_log')->write_log('invoice_billing@invoice', $this->_invoice['id'],
            $data['sync'] == '3' ? '开蓝成功' : '冲红成功');

        // if (defined('APP_TOKEN') && defined('APP_SOURCE') && $callback_params['sendsms'] !== false){
        //     kernel::single('taoexlib_sms_send_router','einvoice')->sendmsg($value['id']);
        // }

        $shopList = array ();
        $shopMdl  = app::get('ome')->model('shop');

        $shop = $shopMdl->dump($this->_invoice['shop_id']);

        if ('on' != app::get('ome')->getConf('ome.invoice.autoupload') || !in_array($shop['node_type'],
                array ('taobao', '360buy', 'wesite', 'd1mwestore', 'pekon', 'luban'))) {
            return;
        }
        
        // params
        $api_method = 'upload_invoice'; // 发票上传方法
        $sub_obj_bn = (isset($this->_invoice['order_bn']) ? (string)$this->_invoice['order_bn'] : ''); // 这里保存订单号
        $errMsg = '';
        
        // 触发上传发票
        $apiFailMdl = app::get('erpapi')->model('api_fail');
        $insert_id = $apiFailMdl->saveTriggerRequest($data['invoice_no'], 'upload_invoice', $api_method, $errMsg, $sub_obj_bn);
        
        // log
        app::get('ome')->model('operation_log')->write_log('einvoice_upload@invoice', $this->_invoice['id'],
            $data['sync'] == '3' ? '开蓝成功准备上传' : '冲红成功准备上传');
        
        // 开蓝成功，实时回传平台，不用再等待一个小时
        if($insert_id){
            $apilogInfo = $apiFailMdl->dump(array('id'=>$insert_id), '*');
            if($apilogInfo){
                $apiFailMdl->retry($apilogInfo);
            }
        }
    }

    //根据红票号获取红票信息创建红票pdf文件
    public function getRedInvoice(&$data)
    {
        if (empty($data['invoice_no'])) {
            throw new Exception('红票号码不能为空');
        }

        $rs_invoice                        = kernel::single('invoice_check')->checkInvoiceCancel($this->_invoice['id']);
        $rs_invoice['invoice_action_type'] = 2;
        $rs_invoice                        = kernel::single('invoice_electronic')->getEinvoiceSerialNo($rs_invoice, "2");
        $result = kernel::single('invoice_event_trigger_einvoice')->createPdfFile($rs_invoice['shop_id'], $rs_invoice);
        if ($result['rsp'] == 'fail') {
            return throw new Exception('板式文件生成失败稍后重试');
        }
        if(is_string($result['data'])){
            $result['data'] = @json_decode($result['data'],true);
        }
        $response = $result['data']['response'];
        //发票预览地址无效抛出
        if (in_array($data['sync'],['3','6'])) {
            if ($response['urlMap']['pdfUrl'] && !$this->is_valid_pdf_link($response['urlMap']['pdfUrl'],'pdf')) {
                throw new Exception('发票预览地址无效');
            }else{
                $data['url'] = $response['urlMap']['pdfUrl'];
            }
            if ($response['urlMap']['xmlUrl'] && !$this->is_valid_pdf_link($response['urlMap']['xmlUrl'],'xml')) {
                throw new Exception('发票XML地址无效');
            }else{
                $data['xml_url'] = $response['urlMap']['xmlUrl'];
            }
            if ($response['urlMap']['ofdUrl'] && !$this->is_valid_pdf_link($response['urlMap']['ofdUrl'],'ofd')) {
                throw new Exception('发票OFD地址无效');
            }else{
                $data['ofd_url'] = $response['urlMap']['ofdUrl'];
            }
        }
    }

    /**
     * 校验url PDF文件是否正常
     * @Author: XueDing
     * @Date: 2024/5/30 10:18 AM
     * @param $url
     * @return bool
     */
    public function is_valid_pdf_link($url,$type = 'pdf')
    {
        // 初始化cURL会话
        $ch = curl_init($url);

        // 设置cURL选项
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);      // 返回内容到变量
        curl_setopt($ch, CURLOPT_HEADER, true);           // 只获取头部信息
        curl_setopt($ch, CURLOPT_NOBODY, false);         // 需要执行BODY的检查以获取头部信息
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);      // 跟随服务器端的重定向
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOSTNAME, false); // 取消SSL主机名验证，对于测试环境可能需要
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // 取消SSL证书验证，对于测试环境可能需要

        // 执行cURL会话
        $response = curl_exec($ch);

        // 获取HTTP响应码
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        // 关闭cURL会话
        curl_close($ch);

        // 检查证HTTP状态码是否为200且内容类型为PDF
        return ($httpCode == 200 && stripos($contentType, 'application/'.$type) !== false);
    }
}
