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
 * 发货单推送
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_request_delivery extends erpapi_wms_request_abstract
{
    // 平台收货人信息是否密文
    protected $is_platform_encrypt = false;
    
    /**
     * 发货单暂停
     *
     * @return void
     * @author
     **/
    public function delivery_pause($sdf)
    {}

    /**
     * 发货单暂停恢复
     *
     * @return void
     * @author
     **/
    public function delivery_renew($sdf)
    {}

    protected function _getEncryptOriginData(&$sdf) {
        if(kernel::single('ome_security_router',$sdf['shop_type'])->is_encrypt($sdf,'delivery')) {
            $encryptData = [
                'shop_id' => $sdf['shop_id'],
                'order_bn' => current(explode('|', $sdf['order_bn'])),
                'ship_name' => $sdf['consignee']['name'],
                'ship_tel' => $sdf['consignee']['telephone'],
                'ship_mobile' => $sdf['consignee']['mobile'],
                'ship_addr' => $sdf['consignee']['addr'],
            ];
            $originalEncrypt = kernel::single('ome_security_router',$sdf['shop_type'])->get_encrypt_origin($encryptData, 'delivery');
            if($originalEncrypt['ship_name']) $sdf['consignee']['name'] = $originalEncrypt['ship_name'];
            if($originalEncrypt['ship_tel']) $sdf['consignee']['telephone'] = $originalEncrypt['ship_tel'];
            if($originalEncrypt['ship_mobile']) $sdf['consignee']['mobile'] = $originalEncrypt['ship_mobile'];
            if($originalEncrypt['ship_addr']) $sdf['consignee']['addr'] = $originalEncrypt['ship_addr'];

            // 标识此订单为平台加密订单
            if ($originalEncrypt){
                $sdf['platform_encrypt'] = true;
            }
        }
    }

    protected function _needEncryptOriginData($sdf) {
        $need_encrypt_list = $this->__channelObj->wms['config']['need_encrypt'];
        $need_encrypt_list['xhs'] = true;
        $need_encrypt_list['taobao'] = true;
        $need_encrypt_list['luban'] = true;
        
        //birken勃肯客户抖音订单需要明文推送
//        if(strtolower($_SERVER['SERVER_NAME']) == 'birkenstock.erp.taoex.com'){
//            $need_encrypt_list['luban'] = false;
//        }
        
        //check
        if ($need_encrypt_list && $need_encrypt_list[$sdf['shop_type']]) {
            return true;;
        }
        return false;
    }
    /**
     * 发货单创建
     *
     * @return void
     * @author
     **/
    public function delivery_create($sdf)
    {
        $delivery_bn = $sdf['outer_delivery_bn'];
        $channel_id = $this->__channelObj->wms['channel_id'];
        
        $iscancel = kernel::single('ome_interface_delivery')->iscancel($delivery_bn);
        if ($iscancel) {
            return $this->succ('发货单已取消,终止同步');
        }

        if ($sdf['member']['uname'] && $encrytPos = strpos($sdf['member']['uname'] , '>>')){
            $sdf['member']['uname'] = substr($sdf['member']['uname'] , 0, $encrytPos);
        }
        if ($sdf['member']['name'] && $encrytPos = strpos($sdf['member']['name'] , '>>')){
            $sdf['member']['name'] = substr($sdf['member']['name'] , 0, $encrytPos);
        }
        
        // 自发货标记：调用selfwms渠道自动完成发货
        //@todo：发货单明细里都是虚拟商品,则打标记为虚拟发货单，并推送selfwms接口，自动完成发货
        if(isset($sdf['is_self_shipment']) && $sdf['is_self_shipment'] === true){
            // params
            $responseData = [
                'delivery_bn' => $delivery_bn, // 发货单号
                'status' => 'delivery',
                'logi_id' => $sdf['logi_id'], // 物流公司ID：直接使用发货单上的物流公司ID
                'logistics' => $sdf['logi_id'], // 物流公司ID：直接使用发货单上的物流公司ID
                'logi_no' => $delivery_bn, // 物流单号：直接使用发货单号作为虚拟物流单号
            ];
            
            // delivery_items
            if($sdf['delivery_items']){
                $itemList = [];
                foreach($sdf['delivery_items'] as $itemVal)
                {
                    $itemList[] = array(
                        'product_bn' => $itemVal['bn'],
                        'num' => $itemVal['number'],
                    );
                }
                
                $responseData['item'] = json_encode($itemList);
            }
            
            return kernel::single('erpapi_router_response')->set_channel_id($channel_id)->set_api_name('wms.delivery.status_update')->dispatch($responseData);
        }
        
        // 加密推送
        if ($this->_needEncryptOriginData($sdf)) {
            $this->_getEncryptOriginData($sdf);
        }
        $title = $this->__channelObj->wms['channel_name'] . '发货单添加';

        // 通过 service 处理不同 APP 的参数格式化
        $params = $this->_format_delivery_create_params($sdf);
        
        // 调用 service 进行参数扩展或修改
        if ($service = kernel::servicelist('erpapi.service.wms.delivery.params.format')) {
            foreach ($service as $object => $instance) {
                if (method_exists($instance, 'format_delivery_create_params')) {
                    $params = $instance->format_delivery_create_params($sdf, $params, $this->__channelObj);
                }
            }
        }
        
        if (!$params) {
            return $this->error('参数为空,终止同步');
        }

        // 判断是否加密
        $gateway = '';
        if (kernel::single('ome_security_router', $sdf['shop_type'])->is_encrypt($sdf, 'delivery')) {
            $params['s_node_type'] = $sdf['shop_type'];
            $params['s_node_id']   = $sdf['node_id'];
            
            // 平台收货人信息是否密文
            $this->is_platform_encrypt = true;
            
            // 加密推送
            $need_encrypt_list = $this->__channelObj->wms['config']['need_encrypt'];
            if ($need_encrypt_list && $need_encrypt_list[$sdf['shop_type']]) {
                $params['need_encrypt'] = 'true';
            }

            $params['order_bns'] = implode(',', explode('|', $sdf['order_bn']));

            $gateway = $sdf['shop_type'];
        }

        $callback = array(
            'class'  => get_class($this),
            'method' => 'delivery_create_callback',
            'params' => array('delivery_bn' => $delivery_bn, 'obj_bn' => $delivery_bn, 'obj_type' => 'delivery'),
        );

        $retry = array(
            'obj_bn'        => $delivery_bn,
            'obj_type'      => 'delivery',
            'channel'       => 'wms',
            'channel_id'    => $this->__channelObj->wms['channel_id'],
            'method'        => 'delivery_create',
            'args'          => func_get_args(),
            'next_obj_type' => $this->_getNextObjType()
        );
        $apiFailId = app::get('erpapi')->model('api_fail')->saveRunning($retry);
        if($apiFailId) {
            $callback['params']['api_fail_id'] = $apiFailId;
        }

        return $this->__caller->call($this->_get_create_api_name(), $params, $callback, $title, 10, $delivery_bn, true, $gateway);
    }

    /**
     * 发货单创建接口名
     *
     * @return void
     * @author
     */
    protected function _get_create_api_name()
    {
        return WMS_SALEORDER_CREATE;
    }

    protected function _format_delivery_create_params($sdf)
    {
        $operInfo = kernel::single('ome_func')->getDesktopUser();
        
        $productId = array(0);
        foreach ($sdf['delivery_items'] as $item) {
            $productId[] = $item['product_id'];
        }
        $outSysProductBn = $this->_getOutSysProductBn($productId);

        $itemCode = array();
        if ($this->outSysProductField == 'item_code') {
            $itemCode = $outSysProductBn;
        }

        $delivery_bn = $sdf['outer_delivery_bn'];

        $delivery_items        = $sdf['delivery_items'];
        $sdf['item_total_num'] = $sdf['line_total_count'] = count($delivery_items);

        $items = array('item' => array());
        if ($delivery_items) {
            
            if($sdf['wms_node_type'] == 'yjdf'){
                //@todo:升序排序后,金额平摊不均拆分成2条时,数量1的排序在前面,导致推送云交易失败了;
                //sort($delivery_items);
            }else{
                sort($delivery_items);
            }
            
            $line_i = 0;
            foreach ($delivery_items as $k => $v)
            {
                //行号
                $line_i++;
                
                //items
                $items['item'][$line_i] = array(
                    'item_code'       => $itemCode[$v['product_id']] ? $itemCode[$v['product_id']] : $v['bn'],
                    'item_name'       => $v['product_name'],
                    'item_quantity'   => (int) $v['number'],
                    'item_price'      => (float) $v['price'],
                    'item_line_num'   => $line_i, // 订单商品列表中商品的行项目编号，即第n行或第n个商品
                    'trade_code'      => $sdf['order_bn'], //可选(若是淘宝交易订单，并且不是赠品，必须要传订单来源编号)
                    'item_id'         => $v['bn'], // 外部系统商品sku
                    'is_gift'         => $v['is_gift'] == 'ture' ? '1' : '0', // 是否赠品
                    'item_remark'     => $v['memo'], // TODO: 商品备注
                    'inventory_type'  => '1', // TODO: 库存类型1可销售库存101类型用来定义残次品201冻结类型库存301在途库存
                    'item_sale_price' => (float) $v['sale_price'], //成交额
                );
                
                //额外的字段
                if(isset($v['discount_price'])){
                    $items['item'][$line_i]['discount_price'] = $v['discount_price'];
                }
            }
            
            //去除数组下标
            $items['item'] = array_values($items['item']);
        }

        // 发票信息
        if ($sdf['is_order_invoice'] == 'true' && $sdf['is_wms_invoice'] == 'true') {
            $invoice       = $sdf['invoice'];
            $is_invoice    = 'true';
            $invoice_type  = $invoice['invoice_type']; // ?什么情况
            $invoice_title = $invoice['invoice_title']['title'];

            // 增值税抬头信息
            if ($invoice['invoice_type'] == 'increment') {
                $invoice_info = array(
                    'name'         => $invoice['invoice_title']['uname'],
                    'phone'        => $invoice['invoice_title']['tel'],
                    'address'      => $invoice['invoice_title']['reg_addr'],
                    'taxpayer_id'  => $invoice['invoice_title']['identify_num'],
                    'bank_name'    => $invoice['invoice_title']['bank_name'],
                    'bank_account' => $invoice['invoice_title']['bank_account'],
                );
                $invoice_info = json_encode($invoice_info);
            }

            // 发票明细
            if ($invoice['invoice_items']) {
                $invoice_items = array();
                $i_money       = 0;
                foreach ($invoice['invoice_items'] as $val) {
                    $price           = round($val['money'], 2);
                    $invoice_items[] = array(
                        'name'     => $val['item_name'],
                        'spec'     => $val['spec'],
                        'quantity' => $val['nums'],
                        'price'    => $price,
                    );
                    $i_money += $price;
                }
            }

            if ($invoice['content_type'] == 'items') {
                $invoice_item  = json_encode($invoice_items);
                $invoice_money = $i_money;
            } else {
                $invoice_desc  = $invoice['invoice_desc'];
                $invoice_money = round($invoice['invoice_money'], 2);
            }
        }

        $create_time = preg_match('/-|\//', $sdf['create_time']) ? $sdf['create_time'] : date("Y-m-d H:i:s", $sdf['create_time']);

        $logistics_code = $this->_getCpCode(['corp_id' => $sdf['logi_id'], 'type' => $sdf['logi_code']]);

        $shop_code      = kernel::single('wmsmgr_func')->getWmsShopCode($this->__channelObj->wms['channel_id'], $sdf['shop_code']);
        $params         = array(
            'uniqid'              => self::uniqid(),
            'out_order_code'      => $delivery_bn,
            'order_source'        => $sdf['shop_type'] ? strtoupper($sdf['shop_type']) : 'OTHER',
            'shipping_type'       => 'EXPRESS',
            'shipping_fee'        => $sdf['logistics_costs'],
            'platform_order_code' => $sdf['order_bn'],
            'logistics_code'      => $logistics_code ? $logistics_code : $sdf['logi_code'],
            'shop_code'           => $shop_code ? $shop_code : $sdf['shop_code'],
            'seller_nick'         => $sdf['shop_name'],
            'remark'              => $sdf['memo'], //订单上的客服备注
            'created'             => $create_time,
            'wms_order_code'      => $delivery_bn,
            'is_finished'         => 'true',
            'current_page'        => 1, // 当前批次,用于分批同步
            'total_page'          => 1, // 总批次,用于分批同步
            'has_invoice'         => $is_invoice == 'true' ? 'true' : 'false',
            'invoice_type'        => $invoice_type,
            'invoice_title'       => $invoice_title,
            'invoice_fee'         => $invoice_money,
            'invoice_info'        => $invoice_info,
            'invoice_desc'        => $invoice_desc,
            'invoice_item'        => $invoice_item,
            'discount_fee'        => $sdf['discount_fee'],
            'is_protect'          => $sdf['is_protect'],
            'protect_fee'         => $sdf['cost_protect'],
            'is_cod'              => $sdf['is_cod'], //是否货到付款。可选值:true(是),false(否)
            'cod_fee'             => $sdf['cod_fee'], //应收货款（用于货到付款）
            'cod_service_fee'     => '0', //cod服务费（货到付款 必填）
            'total_goods_fee'     => $sdf['total_goods_amount'] - $sdf['goods_discount_fee'], //商品原始金额-商品优惠金额
            'total_trade_fee'     => $sdf['total_amount'], //订单交易金额
            'receiver_name'       => $sdf['consignee']['name'],
            'receiver_zip'        => $sdf['consignee']['zip'],
            'receiver_phone'      => $sdf['consignee']['telephone'],
            'receiver_mobile'     => $sdf['consignee']['mobile'],
            'receiver_state'      => (string)$sdf['consignee']['province'], //省
            'receiver_city'       => (string)$sdf['consignee']['city'], //市
            'receiver_district'   => (string)$sdf['consignee']['district'], //区
            'receiver_town'       => (string)$sdf['consignee']['town'], //镇
            'receiver_address'    => $sdf['consignee']['addr'], //详细地址
            'receiver_email'      => $sdf['consignee']['email'],
            'receiver_time'       => $sdf['consignee']['r_time'], // TODO: 要求到货时间
            'line_total_count'    => $sdf['line_total_count'], // TODO: 订单行项目数量
            'item_total_num'      => $sdf['item_total_num'],
            'storage_code'        => $sdf['storage_code'], // 库内存放点编号
            'items'               => json_encode($items),
            'print_remark'        => $sdf['print_remark'] ? json_encode($sdf['print_remark']) : '',
            'dispatch_time'       => $sdf['delivery_time'],
            'warehouse_code'      => $this->get_warehouse_code($this->__channelObj->wms['channel_id'], $sdf['branch_bn']),
            'cert_id'             => $sdf['cert_id'], // 身份证
            'buyer_nick'          => (string)$sdf['member']['uname'],
            'operatorName' => $operInfo['op_name'], //操作员(审核员)名称
        );
        
        //补发订单传平台原订单号给WMS
        if($sdf['order_type'] == 'bufa' && $sdf['relate_order_bn']){
            //关联订单号
            $params['platform_order_code'] = $sdf['relate_order_bn'];
        }elseif($sdf['order_source'] == 'platformexchange' && $sdf['platform_order_bn']){
            //平台订单号(平台换货生成新订单的场景)
            $params['platform_order_code'] = $sdf['platform_order_bn'];
        }
        
        if($sdf['logi_no']) {
            $params['logistics_no'] = $sdf['logi_no'];
        }
        return $params;
    }

    public function delivery_create_callback($response, $callback_params)
    {
        $deliveryObj = app::get('ome')->model('delivery');
        
        $rsp = $response['rsp'];
        $err_msg = $response['err_msg'];
        $data = $response['data'];
        $msg_id  = $response['msg_id'];
        $res = $response['res']; //error_code错误编码

        $delivery_bn = $callback_params['delivery_bn'];

        if ($data) {
            $data = @json_decode($data, true);
        }

        if (is_array($data) && $data['wms_order_code'])
        {
            $oDelivery_extension              = app::get('console')->model('delivery_extension');
            $ext_data['original_delivery_bn'] = $data['wms_order_code'];
            $ext_data['delivery_bn']          = $delivery_bn;
            $oDelivery_extension->create($ext_data);
            
            //保存WMS仓储返回的外部单号
            $deliveryObj->update(array('original_delivery_bn'=>$data['wms_order_code']), array('delivery_bn'=>$delivery_bn));
        }

        //发货单信息
        $deliverys = $deliveryObj->dump(array('delivery_bn' => $delivery_bn), 'delivery_id');

        $msg = $err_msg ? $err_msg : $res;
        $api_status = $rsp == 'succ' ? 'send_succ' : 'send_fail';
        kernel::single('console_delivery')->update_sync_status($deliverys['delivery_id'], $api_status, $msg, $res);
        
        // if ($rsp == 'succ' && $this->_getNextObjType()) {
        //     //把单号加进队列
        //     $failApiModel = app::get('erpapi')->model('api_fail');
        //     $api_data     = array(
        //         'obj_type' => $this->_getNextObjType(),
        //         'obj_bn'   => $delivery_bn,
        //         'obj_id'   => $deliverys['delivery_id'],

        //     );
        //     $failApiModel->publish_api_fail(WMS_SALEORDER_GET, $api_data, array('rsp' => 'fail'));

        // }
        $callback_params['obj_bn']   = $delivery_bn;
        // $callback_params['obj_type'] = 'delivery';
        
        //log
        if($rsp != 'succ'){
            $operationLogObj = app::get('ome')->model('operation_log');
            $operationLogObj->write_log('delivery_modify@ome', $deliverys['delivery_id'], 'WMS响应结果：'. $msg);
        }
        
        return $this->callback($response, $callback_params);
    }

    /**
     * 发货单取消
     *
     * @return void
     * @author
     **/
    public function delivery_cancel($sdf)
    {
        $delivery_bn = $sdf['outer_delivery_bn'];

        $title = $this->__channelObj->wms['channel_name'] . '发货单取消';

        $params = $this->_format_delivery_cancel_params($sdf);

        return $this->__caller->call(WMS_SALEORDER_CANCEL, $params, null, $title, 10, $delivery_bn);

    }

    protected function _format_delivery_cancel_params($sdf)
    {
        $params = array(
            'warehouse_code' => $sdf['branch_bn'],
            'out_order_code' => $sdf['outer_delivery_bn'],
        );
        return $params;
    }

    /**
     * 发货单查询
     *
     * @return void
     * @author
     **/
    public function delivery_search($sdf)
    {
        $delivery_bn = $sdf['delivery_bn'];

        $title = $this->__channelObj->wms['channel_name'] . '发货单查询';

        $params    = $this->_format_delivery_search_params($sdf);
        $rs        = $this->__caller->call(WMS_SALEORDER_GET, $params, null, $title, 10, $delivery_bn);
        $failModel = app::get('erpapi')->model('api_fail');

        $retry = array(
            'obj_bn'        => $delivery_bn,
            'obj_type'      => 'search_delivery',
            'channel'       => 'wms',
            'channel_id'    => $this->__channelObj->wms['channel_id'],
            'method'        => 'delivery_search',
            'args'          => func_get_args(),
        );
        $apiFailId = $failModel->saveRunning($retry);

        $rsp        = 'fail';
        $api_status = 'search_succ';
        if ($rs['rsp'] == 'fail') {
            $api_status = 'search_fail';
        }
        if ($rs['rsp'] == 'succ') {
            $result = $this->_deal_search_result($rs);

            if ($result['data']) {
                $rs = kernel::single('erpapi_router_response')->set_node_id($this->__channelObj->wms['node_id'])->set_api_name('wms.delivery.status_update')->dispatch($result['data']);

                //如果succ 删除fail_log
                if ($rs['rsp'] == 'succ') {
                    $rsp        = 'succ';
                    $api_status = 'search_succ';
                }

                if ($rs['rsp'] == 'succ' 
                    && in_array($result['data']['status'], array('CLOSE', 'FAILED', 'DELIVERY'))) {
                    $failModel->delete(array('id' => $apiFailId));
                }
            }
        }

        //更新推单状态为失败
        $deliveryObj = app::get('ome')->model('delivery');
        $deliverys   = $deliveryObj->dump(array('delivery_bn' => $delivery_bn), 'delivery_id');

        $msg = $rs['err_msg'] ? $rs['err_msg'] : $rs['res'];

        kernel::single('console_delivery')->update_sync_status($deliverys['delivery_id'], $api_status, $msg);
        //把单号加进队列
        // $failApiModel = app::get('erpapi')->model('api_fail');
        // $api_data     = array(
        //     'obj_type' => 'search_delivery',
        //     'obj_bn'   => $delivery_bn,

        // );

        // $failApiModel->publish_api_fail(WMS_SALEORDER_GET, $api_data, array('rsp' => $rsp));

        return $rs;
    }

    protected function _format_delivery_search_params($sdf)
    {
        $params = array(
            'out_order_code' => $sdf['out_order_code'],
        );
        return $params;
    }

    protected function _deal_search_result($rs)
    {
        return $rs;
    }

    protected function _getNextObjType()
    {
        return '';
    }

    # 发货单截单
    public function delivery_cut($sdf){
        $deliveryBn = $sdf['outer_delivery_bn'];
        $title = $this->__channelObj->channel['channel_name'] . '发货单截单';
        $params = $this->_format_cut_params($sdf);
        if(empty($params['delivery_order_code'])) {
            return $this->error('单号缺失');
        }
        $apiName = $this->_get_cut_name($sdf);
        $callback = array();
        $result = $this->__caller->call($apiName, $params, $callback, $title,10,$deliveryBn);
        return $result;
    }

    /**
     * @param $sdf = array('delivery_bn'=>'', 'branch_id'=>'', 'branch_bn'=>'', 'original_delivery_bn'=>'')
     * @return array
     */
    protected function _format_cut_params($sdf) {
        $params = array(
            'warehouse_code' => $this->get_warehouse_code($this->__channelObj->wms['channel_id'], $sdf['branch_bn']),
            'delivery_order_code' => $sdf['outer_delivery_bn'],
            'order_id' => $sdf['original_delivery_bn'],
        );
        return $params;
    }

    protected function _get_cut_name($sdf) {
        return WMS_SALEORDER_CALLBACK;
    }


     /**
     * 预售付尾款通知wms接口
     *
     * @param $sdf
     * @return string
     */
    protected function get_delivery_notify_apiname($sdf)
    {
        return WMS_SALEORDER_CONFIRM;
    }
    
    public function delivery_notify($sdf){

        $delivery_bn = $sdf['delivery_bn'];

        $title = $this->__channelObj->wms['channel_name'] . '预售单通知';

        $params = $this->_format_delivery_notify_params($sdf);
        
        //method
        $api_method = $this->get_delivery_notify_apiname($sdf);
        
        //callback
        $callback = array(
            'class' => get_class($this),
            'method' => 'delivery_notify_callback',
            'params' => array('delivery_bn'=>$delivery_bn,'delivery_id'=>$sdf['delivery_id']),
        );
        
        return $this->__caller->call($api_method, $params, $callback, $title,10,$delivery_bn);
    }
    

    protected function _format_delivery_notify_params($sdf)
    {
        
        return array();
    }

    public function delivery_notify_callback($response, $callback_params)
    {
        $rsp     = $response['rsp'];
        $err_msg = $response['err_msg'];
        $data    = $response['data'];
        $msg_id  = $response['msg_id'];
        $res     = $response['res'];


        $delivery_bn = $callback_params['delivery_bn'];
        $delivery_id = $callback_params['delivery_id'];
        //更新同步状态
        $deliveryOrderModel = app::get('ome')->model('delivery_order');
        $deliveryOrderList = $deliveryOrderModel->getList('order_id',array('delivery_id'=>$delivery_id));

        
        if ($rsp == 'succ'){
            $presale_sync_status = 1;
        }else{
            $presale_sync_status = 2;
        }
        $extendObj = app::get('ome')->model('order_extend');
        if ($deliveryOrderList){
            foreach($deliveryOrderList as $v){
                $order_id = $v['order_id'];
                
                // 好来客户刷不动表,放弃使用
                //$extendObj->update(array('presale_sync_status'=>$presale_sync_status),array('order_id'=>$order_id));
            }
        }
        
        return $this->callback($response, $callback_params);
    }

}
