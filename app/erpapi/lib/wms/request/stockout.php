<?php
/**
 * 出库单推送
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_request_stockout extends erpapi_wms_request_abstract
{
    protected $_stockout_pagination = false;
    protected $_stockout_pagesize = 3000;

    protected function transfer_stockout_type($io_type)
    {
        $stockout_type = array(
            'PURCHASE_RETURN' => 'OUT_PURCHASE_RETURN', //采购退货
            'ALLCOATE'        => 'OUT_ALLCOATE',        //调拨出库
            'DEFECTIVE'       => 'OUT_DEFECTIVE',       // 残损出库
            'ADJUSTMENT'      => 'OUT_ADJUSTMENT',      // 调帐出库
            'VOPSTOCKOUT'     => 'OUT_JITCK',           // 唯品会JIT出库
        );

        return isset($stockout_type[$io_type]) ? $stockout_type[$io_type] : 'OUT_OTHER';
    }

    /**
     * 出库单创建
     *
     * @return void
     * @author
     **/
    public function stockout_create($sdf){
        $stockout_bn = $sdf['io_bn'];

        //唯品会出库
        $io_type    = purchase_purchase_stockout::_io_type;
        $is_vop     = ($sdf['io_type']==$io_type ? true : false);

        //是否取消状态
        $iscancel = kernel::single('console_service_commonstock')->iscancel($stockout_bn, $is_vop);
        if ($iscancel) {
            return $this->succ('出库单已取消,终止同步');
        }

        $title = $this->__channelObj->wms['channel_name'] . '出库单添加';

        // 分页请求
        $items = $sdf['items']; sort($items);

        $total = count($items); $page_no = 1; $page_size =  $this->_stockout_pagesize;
        $total_page = $this->_stockout_pagination ? ceil($total/$page_size) : 1;

        $retry = array(
            'obj_bn'        => $stockout_bn,
            'obj_type'      => strtolower($sdf['io_type']),
            'channel'       => 'wms',
            'channel_id'    => $this->__channelObj->wms['channel_id'],
            'method'        => 'stockout_create',
            'total_page'    => $total_page,
            'args'          => func_get_args(),
            'next_obj_type' => $this->_getNextObjType(strtolower($sdf['io_type']))
        );
        $apiFailId = app::get('erpapi')->model('api_fail')->saveRunning($retry);

        do {

            $offset = ($page_no - 1) * $page_size;

            $sdf['items'] = $total_page > 1 ? array_slice($items, $offset, $page_size, true) : $items;

            //唯品会出库
            if($is_vop){
                $params = $this->_vop_format_stockout_create_params($sdf);
            }else{
                $params = $this->_format_stockout_create_params($sdf);
            }

            $params['is_finished']      = ($page_no >= $total_page) ? 'true' : 'false';
            $params['current_page']     = $page_no;
            $params['total_page']       = $total_page;
            $params['item_total_num']   = $total;
            $params['line_total_count'] = $total;


            $callback = array(
                'class'  => get_class($this),
                'method' => 'stockout_create_callback',
                'params' => array('stockout_bn'=>$stockout_bn,'io_type'=>$sdf['io_type'],'obj_bn'=>$stockout_bn,'obj_type'=>strtolower($sdf['io_type'])),
            );

            if($apiFailId) {
                $callback['params']['api_fail_id'] = $apiFailId . '-' . $page_no;
            }

            $this->__caller->call(WMS_OUTORDER_CREATE, $params, $callback, $title, 10, $stockout_bn);

            if ($params['is_finished'] == 'true') break;

            $page_no++;
        } while (true);
    }

    public function stockout_create_callback($response, $callback_params)
    {
        // 更新外部编码
        $rsp     = $response['rsp'];
        $err_msg = $response['err_msg'];
        $data    = @json_decode($response['data'],true);
        $data = is_array($data) ? $data : [];
        $msg_id  = $response['msg_id'];
        $res     = $response['res'];

        $stockout_bn = $callback_params['stockout_bn'];
        $io_type     = $callback_params['io_type'];
    
        $upData = [];
        if($rsp == 'succ') {
            $upData['sync_status'] = '3';
            $upData['sync_msg'] = '';
        } else {
            $upData['sync_status'] = '2';
            $upData['sync_msg'] = '失败：'.$err_msg;
        }
        if ($data['wms_order_code']) {
            $upData['out_iso_bn'] = $data['wms_order_code'];
        }
        if($upData) {
            if ($io_type == 'PURCHASE_RETURN') {
                app::get('purchase')->model('returned_purchase')->update($upData, ['rp_bn'=>$stockout_bn]);
            } else {
                app::get('taoguaniostockorder')->model('iso')->update($upData, ['iso_bn'=>$stockout_bn]);
            }
        }
        // if ($rsp =='succ' && $this->_getNextObjType()){
        //     //把单号加进队列
        //     $failApiModel = app::get('erpapi')->model('api_fail');
        //     $api_data = array(
        //         'obj_type'  =>  $this->_getNextObjType(),
        //         'obj_bn'    =>  $stockout_bn,
        //         'obj_id'    =>  '',
        //         'params'    =>  $callback_params['params'],

        //     );
        //     $failApiModel->publish_api_fail(WMS_OUTORDER_GET,$api_data,array('rsp'=>'fail'));
        // }
        
        $callback_params['obj_bn']   = $stockout_bn;
        $callback_params['obj_type'] = strtolower($io_type);
        return $this->callback($response, $callback_params);
    }

    protected function _format_stockout_create_params($sdf)
    {
        $stockout_bn = $sdf['io_bn'];
        
        //items
        $items = array('item'=>array());
        if ($sdf['items']){
            foreach ($sdf['items'] as $k => $v){
                $items['item'][] = array(
                    'item_code'      => $v['bn'],
                    'item_name'      => $v['name'],
                    'item_quantity'  => (int)$v['num'],
                    'item_price'     => $v['price'] ? (float)$v['price'] : 0,// TODO: 商品价格
                    'item_line_num'  => ($k + 1),// TODO: 订单商品列表中商品的行项目编号，即第n行或第n个商品
                    'trade_code'     => '',//可选(若是淘宝交易订单，并且不是赠品，必须要传订单来源编号)
                    'item_id'        => $v['bn'],// 商品ID
                    'is_gift'        => '0',// TODO: 判断是否为赠品0:不是1:是
                    'item_remark'    => '',// TODO: 商品备注
                    'inventory_type' => $sdf['branch_type'] == 'damaged' ? '101' : '1',// TODO: 库存类型1可销售库存101类型用来定义残次品201冻结类型库存301在途库存
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
        
        // 物流公司ID
        $sdf['logi_id'] = ($sdf['logi_id'] ? $sdf['logi_id'] : $sdf['corp_id']);
        
        // 获取与WMS的物流映射关系
        $logistics_code = $this->_getCpCode(['corp_id' => $sdf['logi_id'], 'type' => $sdf['logi_code']]);
        
        //params
        $params = array(
            'uniqid'            => self::uniqid(),
            'customer_id'       => '',// 客户编码
            'out_order_code'    => $stockout_bn,
            'order_type'        => $this->transfer_stockout_type($sdf['io_type']),
            'created'           => $create_time,
            'wms_order_code'    => $stockout_bn,
            'shipping_type'     => 'EXPRESS',// TODO: 运输方式 EXPRESS-快递 EMS-邮政速递
            'logistics_code'    => $logistics_code ? $logistics_code : $sdf['logi_code'],// TODO: 快递公司（如果是汇购传递快递公司，则该项目不能为空，否则可以为空处理）
            'remark'            => $sdf['memo'],
            'total_amount'      => $sdf['total_goods_fee'],// 订单商品总价（精确到小数点后2位）
            'receiver_name'     => $sdf['receiver_name']  ? $sdf['receiver_name'] : '未知',
            'receiver_zip'      => $sdf['receiver_zip'] ? $sdf['receiver_zip'] : '123211',// TODO: 收货人邮政编码
            'receiver_state'    => $sdf['receiver_state'] ? $sdf['receiver_state'] : '未知',// TODO: 退货人所在省
            'receiver_city'     => $sdf['receiver_city'] ? $sdf['receiver_city'] : '未知',// TODO: 退货人所在市
            'receiver_district' => $sdf['receiver_district'] ? $sdf['receiver_district'] : '未知',// TODO: 退货人所在县（区），注意有些市下面是没有区的
            'receiver_address'  => $sdf['receiver_address'] ? $sdf['receiver_address'] : '未知',// TODO: 收货地址（出库时非空）
            'receiver_phone'    => $sdf['receiver_phone'] ? $sdf['receiver_phone'] : '11111111111',// TODO: 收货人电话号码（如有分机号用“-”分隔）(电话和手机必选一项)
            'receiver_mobile'   => $sdf['receiver_mobile'] ? $sdf['receiver_mobile'] : '11111111111',// TODO: 收货人手机号码(电话和手机必选一项)
            'receiver_email'   => $sdf['receiver_email'] ? $sdf['receiver_email'] : '',// TODO: 收货人手机号码(电话和手机必选一项)
            'receiver_time'     => '',
            'sign_standard'     => '',// TODO: 签收标准（如：身仹证150428197502205130）
            'source_plan'       => '',// TODO: 来源计划点
            'storage_code'      => $sdf['storage_code'],// 库内存放点编号
            'items'             => json_encode($items),
            'supplierCode' => $sdf['supplier_bn'], //供应商编码
            'supplierName' => $sdf['shipper_name'], //供应商名称
        );
        
        return $params;
    }

    /**
     * 出库单取消
     *
     * @return void
     * @author
     **/
    public function stockout_cancel($sdf){
        $stockout_bn = $sdf['io_bn'];

        $title = $this->__channelObj->wms['channel_name'] . '出库单取消';

        $params = $this->_format_stockout_cancel_params($sdf);

        return $this->__caller->call(WMS_OUTORDER_CANCEL, $params, null, $title, 10, $stockout_bn);

    }

    protected function _format_stockout_cancel_params($sdf)
    {
        $params = array(
            'out_order_code' => $sdf['io_bn'],
            'order_type'     => $this->transfer_stockout_type($sdf['io_type']),
        );

        return $params;
    }

    /**
     * 出库查询
     *
     * @return void
     * @author
     **/
    public function stockout_search($sdf)
    {
        $stockout_bn = $sdf['stockout_bn'];

        $title = $this->__channelObj->wms['channel_name'] . '出库单查询';

        $params = $this->_format_stockout_search_params($sdf);

        $rs = $this->__caller->call(WMS_OUTORDER_GET, $params, null, $title, 10, $stockout_bn);
        $failModel = app::get('erpapi')->model('api_fail');
        if($rs['rsp'] == 'succ') {
            $result = $this->_deal_search_result($rs);
            if($result['data']) {

                $rs = kernel::single('erpapi_router_response')->set_node_id($this->__channelObj->wms['node_id'])->set_api_name('wms.stockout.status_update')->dispatch($result['data']);
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

    protected function _format_stockout_search_params($sdf)
    {
        $params = array(
            'out_order_code' =>$sdf['stockout_bn'],
        );
        return $params;
    }

    protected function _deal_search_result($rs) {
        return $rs;
    }
}
