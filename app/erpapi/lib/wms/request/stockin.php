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
 * 入库单推送
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_request_stockin extends erpapi_wms_request_abstract
{
    protected $_stockin_pagination = false;
    protected $_stockin_pagesize   = 3000;

    protected function transfer_stockin_type($io_type)
    {
        $stockin_type = array(
            'PURCHASE'   => 'IN_PURCHASE',  // 采购入库
            'ALLCOATE'   => 'IN_ALLCOATE',  // 调拨入库
            'DEFECTIVE'  => 'IN_DEFECTIVE', // 残损入库
            'ADJUSTMENT' => 'IN_ADJUSTMENT',// 调帐入库
            'EXCHANGE'   => 'IN_EXCHANGE',  // 换货入库
            'OTHER'      => 'IN_OTHER',     // 其他入库
        );

        return isset($stockin_type[$io_type]) ? $stockin_type[$io_type] : 'IN_OTHER';
    }

    /**
     * 入库单创建
     *
     * @return void
     * @author
     **/
    public function stockin_create($sdf){
        $stockin_bn = $sdf['io_bn'];

        $iscancel = kernel::single('console_service_commonstock')->iscancel($stockin_bn);
        if ($iscancel) {
            return $this->succ('入库单已取消,终止同步');
        }

        $title = $this->__channelObj->wms['channel_name'].'入库单添加';

        // 分页请求
        $items = $sdf['items']; sort($items);

        $total = count($items); $page_no = 1; $page_size =  $this->_stockin_pagesize;
        $total_page = $this->_stockin_pagination ? ceil($total/$page_size) : 1;

        $retry = array(
            'obj_bn'        => $stockin_bn,
            'obj_type'      => strtolower($sdf['io_type']),
            'channel'       => 'wms',
            'channel_id'    => $this->__channelObj->wms['channel_id'],
            'method'        => 'stockin_create',
            'total_page'    => $total_page,
            'args'          => func_get_args(),
            'next_obj_type' => $this->_getNextObjType(strtolower($sdf['io_type']))
        );
        $apiFailId = app::get('erpapi')->model('api_fail')->saveRunning($retry);

        do {

            $offset = ($page_no - 1) * $page_size;

            $sdf['items'] = $total_page > 1 ?  array_slice($items, $offset, $page_size, true) : $items;

            $params = $this->_format_stockin_create_params($sdf);

            $params['is_finished']      = ($page_no >= $total_page) ? 'true' : 'false';
            $params['current_page']     = $page_no;
            $params['total_page']       = $total_page;
            $params['item_total_num']   = $total;
            $params['line_total_count'] = $total;

            // 增加service
            foreach(kernel::servicelist('erpapi.service.wms.stockin.create.params') as $service){
                if(method_exists($service, 'format_stockin_create_params')){
                    $params = $service->format_stockin_create_params($sdf, $params);
                }
            }

            $callback = array(
                'class'  => get_class($this),
                'method' => 'stockin_create_callback',
                'params' => array('stockin_bn'=>$stockin_bn,'io_type'=>$sdf['io_type'],'obj_bn'=>$stockin_bn,'obj_type'=>strtolower($sdf['io_type'])),
            );

            if($apiFailId) {
                $callback['params']['api_fail_id'] = $apiFailId . '-' . $page_no;
            }

            $this->__caller->call(WMS_INORDER_CREATE, $params, $callback, $title, 10, $stockin_bn);

            if ($params['is_finished'] == 'true') break;

            $page_no++;
        } while (true);
    }

    public function stockin_create_callback($response, $callback_params)
    {
        // 更新外部编码
        $rsp     = $response['rsp'];
        $err_msg = $response['err_msg'];
        $data    = @json_decode($response['data'],true);
        $msg_id  = $response['msg_id'];
        $res     = $response['res'];

        $stockin_bn = $callback_params['stockin_bn'];
        $io_type    = $callback_params['io_type'];
    
        $upData = [];
        if($rsp == 'succ') {
            $upData['sync_status'] = '3';
            $upData['sync_msg'] = '';
        } else {
            $upData['sync_status'] = '2';
            $upData['sync_msg'] = '失败：'.$err_msg;
        }
        if (is_array($data) && $data['wms_order_code']) {
            $upData['out_iso_bn'] = $data['wms_order_code'];
        }
        if($upData) {
            if ($io_type == 'PURCHASE') {
                app::get('purchase')->model('po')->update($upData, ['po_bn'=>$stockin_bn]);
            } else {
                app::get('taoguaniostockorder')->model('iso')->update($upData, ['iso_bn'=>$stockin_bn]);
            }
        }

        // if ($rsp =='succ' && $this->_getNextObjType()){
        //     //把单号加进队列
        //     $failApiModel = app::get('erpapi')->model('api_fail');
        //     $api_data = array(
        //         'obj_type'  =>  $this->_getNextObjType(),
        //         'obj_bn'    =>  $stockin_bn,
        //         'obj_id'    =>  '',
        //         'params'    =>  $callback_params['params'],

        //     );
        //     $failApiModel->publish_api_fail(WMS_INORDER_GET,$api_data,array('rsp'=>'fail'));
        // }

        $callback_params['obj_bn']   = $stockin_bn;
        $callback_params['obj_type'] = strtolower($io_type);
        return $this->callback($response, $callback_params);
    }

    protected function _format_stockin_create_params($sdf)
    {
        $items = array('item'=>array());
        if ($sdf['items']){
            foreach ((array) $sdf['items'] as $k => $v){
                $material = kernel::single('material_basic_material')->getBasicMaterialBybn($v['bn']);

                // 获取外部商品sku
                $items['item'][] = array(
                    'item_code'       => $v['bn'],
                    'item_name'       => $v['name'],
                    'item_quantity'   => (int)$v['num'],
                    'item_price'      => $v['price'] ? (float)$v['price'] : 0,// TODO: 商品价格
                    'item_line_num'   => ($k + 1),// TODO: 订单商品列表中商品的行项目编号，即第n行或第n个商品
                    'trade_code'      => '',//可选(若是淘宝交易订单，并且不是赠品，必须要传订单来源编号)
                    'item_id'         => $v['bn'],// 商品ID
                    'is_gift'         => '0',// TODO: 判断是否为赠品0:不是1:是
                    'item_remark'     => '',// TODO: 商品备注
                    'inventory_type'  => $sdf['branch_type'] == 'damaged' ? '101' : '1',// TODO: 库存类型1可销售库存101类型用来定义残次品201冻结类型库存301在途库存
                    'item_sale_price' => $material['retail_price'] ? (float)$material['retail_price'] : 0,
                );
            }
        }
        
        //create_time
        $create_time = preg_match('/-|\//',$sdf['create_time']) ? $sdf['create_time'] : date("Y-m-d H:i:s",$sdf['create_time']);
        
        //供应商信息
        $supplierInfo = array();
        if($sdf['supplier_id']) {
            $supplierInfo = app::get('purchase')->model('supplier')->dump($sdf['supplier_id']);
            $supplierInfo['supplier_bn'] = $supplierInfo['bn'];
            
            //获取第三方仓储配置的供应商映射关系
            $sdf['supplier_bn'] = $this->_getSupplierCode($supplierInfo);
            $sdf['shipper_name'] = ($sdf['shipper_name'] ? $sdf['shipper_name'] : $supplierInfo['name']);
        }
        
        //params
        $params = array(
            'uniqid'           => self::uniqid(),
            'out_order_code'   => $sdf['io_bn'],
            'order_type'       => $this->transfer_stockin_type($sdf['io_type']),
            'created'          => $create_time,
            'wms_order_code'   => $sdf['io_bn'],
            'logistics_code'   => '',// TODO: 快递公司（如果是汇购传递快递公司，则该项目不能为空，否则可以为空处理）
            'logistics_no'     => '',// TODO: 运输公司运单号
            'remark'           => $sdf['memo'],
            'shipper_name'     => $sdf['shipper_name'] ? $sdf['shipper_name'] : 'demo',
            'shipper_zip'      => $sdf['shipper_zip'] ? $sdf['shipper_zip'] : '222222',// TODO: 收货人邮政编码
            'shipper_state'    => $sdf['shipper_state'] ? $sdf['shipper_state'] : '未知',// TODO: 退货人所在省
            'shipper_city'     => $sdf['shipper_city'] ? $sdf['shipper_city'] : '未知',// TODO: 退货人所在市
            'shipper_district' => $sdf['shipper_district'] ? $sdf['shipper_district'] : '未知',// TODO: 退货人所在县（区），注意有些市下面是没有区的
            'shipper_address'  => $sdf['shipper_address']  ? $sdf['shipper_address'] : '未知',// TODO: 收货地址（出库时非空）
            'shipper_phone'    => $sdf['shipper_phone']  ? $sdf['shipper_phone'] : '111111',// TODO: 收货人电话号码（如有分机号用“-”分隔）(电话和手机必选一项)
            'shipper_mobile'   => $sdf['shipper_mobile']  ? $sdf['shipper_mobile'] : '11111111111',// TODO: 收货人手机号码(电话和手机必选一项)
            'shipper_email'   => $sdf['shipper_email']  ? $sdf['shipper_email'] : 'demo@demo.com',// TODO: 收货人手机号码(电话和手机必选一项)
            'total_goods_fee'  => $sdf['total_goods_fee'],// 订单商品总价（精确到小数点后2位）
            'storage_code'     => $sdf['storage_code'],// 库内存放点编号
            'items'            => json_encode($items),
            'supplierCode'     => $sdf['supplier_bn'], //供应商编码
            'supplierName'     => $sdf['shipper_name'], //供应商名称
        );
        
        return $params;
    }

    /**
     * 入库单取消
     *
     * @return void
     * @author
     **/
    public function stockin_cancel($sdf){
        $stockin_bn = $sdf['io_bn'];

        $title = $this->__channelObj->wms['channel_name'] . '入库单取消';

        $params = $this->_format_stockin_cancel_params($sdf);

        // 增加service
        foreach(kernel::servicelist('erpapi.service.wms.stockin.cancel.params') as $service){
            if(method_exists($service, 'format_stockin_cancel_params')){
                $params = $service->format_stockin_cancel_params($sdf, $params);
            }
        }

        return $this->__caller->call(WMS_ORDER_CANCEL, $params, null, $title, 10, $stockin_bn);
    }

    protected function _format_stockin_cancel_params($sdf)
    {
        $params = array(
            'out_order_code' => $sdf['io_bn'],
            'order_type' => $this->transfer_stockin_type($sdf['io_type']),
        );
        return $params;
    }

    /**
     * 入库单查询
     *
     * @return void
     * @author
     **/
    public function stockin_search($sdf)
    {
        $stockin_bn = $sdf['stockin_bn'];

        $title = $this->__channelObj->wms['channel_name'] . '入库单查询';

        $params = $this->_format_stockin_search_params($sdf);
        $rs = $this->__caller->call(WMS_INORDER_GET, $params, null, $title, 10, $stockin_bn);
        $failModel = app::get('erpapi')->model('api_fail');
        if($rs['rsp'] == 'succ') {
            $result = $this->_deal_search_result($rs);
            if($result['data']) {

                $rs = kernel::single('erpapi_router_response')->set_node_id($this->__channelObj->wms['node_id'])->set_api_name('wms.stockin.status_update')->dispatch($result['data']);
                //如果succ 删除fail_log
                if ($rs['rsp'] == 'succ'){
                    $failModel->delete(array('id' => $sdf['obj_id']));
                }
            }
        }else{
            $failModel->db->exec("UPDATE sdb_erpapi_api_fail SET fail_times=fail_times+1,msg_id='".$rs['msg_id']."' WHERE id=".$sdf['obj_id']);
        }
        return $rs;
    }

    protected function _format_stockin_search_params($sdf)
    {
        $params = array(
            'out_order_code'=>$sdf['out_order_code'],
        );

        return $params;
    }

    protected function _deal_search_result($rs) {
        return $rs;
    }

}
