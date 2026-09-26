<?php
/**
 * 退货
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_response_process_reship
{
    /**
     * @param Array $params=array(
     *                  'status'=>@状态@ PARTIN|FINISH|DENY|CLOSE|FAILED|ACCEPT
     *                  'reship_bn'=>@退货单号@
     *                  'items'=>array(
     *                      'bn'=>@货号@
     *                      'normal_num"=>@良品@
     *                      'defective_num'=>@不良品@
     *                  )
     *              )
     *
     * @return void
     * @author
     **/
    public function status_update($params)
    {
        // 创建拦截退换货单并且自动内部完成退货（不推送给WMS）
        //@todo：美团闪购、淘宝闪购会推送拦截单，这里做拦截单处理；
        if($params['act'] == 'lanjiereship'){
            $result = $this->lanjieReship($params);
            
            return $result;
        }
        
        $result = kernel::single('console_event_receive_iostock')->reship_result($params);
        // 报警
        if($result['rsp'] == 'fail' && $params['status'] == 'FINISH') {
            kernel::single('monitor_event_notify')->addNotify('wms_reship_finish', [
                'reship_bn' => $params['reship_bn'],
                'errmsg'      => $result['msg'],
            ]);
        }
        return $result;
    }

    public function add_complete($params)
    {
        return array('rsp' => 'succ', 'msg' => '暂未实现');
        $orderBn     = array();
        $bn          = array();
        $bnProduct   = array();
        $bnProductCC = array();
        foreach ($params['items'] as $val) {
            $bnProduct[$val['order_bn'] . '|' . $val['bn']] += $val['num'];
            $bnProductCC[$val['order_bn'] . '|' . $val['bn']] += $val['ccnum'];
            $orderBn[] = $val['order_bn'];
            $bn[]      = $val['bn'];
        }
        $orderObj  = app::get('ome')->model('orders');
        $field     = 'order_id,order_bn,status,process_status,ship_status,pay_status,shop_id,member_id,logi_id,logi_no,ship_name,ship_area,ship_addr,ship_zip,ship_tel,ship_email,ship_mobile,order_bool_type,order_type';
        $orderList = $orderObj->getList($field, array('order_bn' => $orderBn));
        if (empty($orderList)) {
            return array('rsp' => 'fail', 'msg' => '没有该订单' . $params['items'][0]['order_bn']);
        }

        $orderData   = array();
        $strReshipBn = $params['reship_bn'] . '_' . $params['branch']['branch_id'] . '_';
        $arrReshipBn = array();
        foreach ($orderList as $val) {
            $orderData[$val['order_id']] = $val;
            $arrReshipBn[]               = $strReshipBn . $val['order_id'];
        }
        $modelReship = app::get('ome')->model('reship');
        $oldReship   = $modelReship->getList('order_id', array('reship_bn' => $arrReshipBn));
        foreach ($oldReship as $val) {
            if ($orderData[$val['order_id']]) {
                unset($orderData[$val['order_id']]);
            }
        }
        if (empty($orderData)) {
            return array('rsp' => 'fail', 'msg' => '该退货单已经生成' . $params['reship_bn']);
        }

        $itemFilter   = array('order_id' => array_keys($orderData), 'bn' => $bn, 'delete' => 'false');
        $orderItems   = app::get('ome')->model('order_items')->getList('*', $itemFilter);
        $orderBnItems = [];
        foreach ($orderItems as $val) {
            $orderBnItems[$val['order_id'] . '|' . $val['bn']]['order_id']   = $val['order_id'];
            $orderBnItems[$val['order_id'] . '|' . $val['bn']]['product_id'] = $val['product_id'];
            $orderBnItems[$val['order_id'] . '|' . $val['bn']]['item_id']    = $val['item_id'];
            $orderBnItems[$val['order_id'] . '|' . $val['bn']]['bn']         = $val['bn'];
            $orderBnItems[$val['order_id'] . '|' . $val['bn']]['name']       = $val['name'];
            $orderBnItems[$val['order_id'] . '|' . $val['bn']]['sendnum'] += $val['sendnum'];
            $orderBnItems[$val['order_id'] . '|' . $val['bn']]['return_num'] += $val['return_num'];
            $orderBnItems[$val['order_id'] . '|' . $val['bn']]['nums'] += $val['nums'];
            
            // 分摊之后的实付金额
            if(!isset($orderBnItems[$val['order_id'] . '|' . $val['bn']]['divide_order_fee'])){
                $orderBnItems[$val['order_id'] . '|' . $val['bn']]['divide_order_fee'] = $val['divide_order_fee'];
            }else{
                $orderBnItems[$val['order_id'] . '|' . $val['bn']]['divide_order_fee'] += $val['divide_order_fee'];
            }
        }

        $items = array();
        foreach ($orderBnItems as $val) {
            $key                  = $orderData[$val['order_id']]['order_bn'] . '|' . $val['bn'];
            $val['number']        = ($bnProduct ? $bnProduct[$key] : 1);
            $val['defective_num'] = ($bnProductCC ? $bnProductCC[$key] : 0);
            if ($val['number'] > ($val['sendnum'] - $val['return_num'])) {
                return array('rsp' => 'fail', 'msg' => $val['bn'] . '超过发货数量');
            }

            $items[$val['order_id']][] = $val;
        }

        if (empty($items)) {
            return array('rsp' => 'fail', 'msg' => '没有找到退货的明细');
        }

        $msg = '';
        foreach ($items as $orderId => $val) {
            $rs = $this->dealOneReship($val, $orderData[$orderId], $params);

            $msg .= '订单' . $orderData[$orderId]['order_bn'] . '退货单处理:' . $rs['msg'] . '<br/>';
        }
        return array('rsp' => 'succ', 'msg' => $msg);
    }

    /**
     * 生成退货单，款和货分开，款在平台退同步到ERP
     * @param  array $items   明细
     * @param  array $tgOrder 订单
     * @param  array $params  api传参
     * @return array          [description]
     */
    private function dealOneReship($items, $tgOrder, $params)
    {
        $opInfo     = kernel::single('ome_func')->get_system();
        
        // shop
        $shopInfo = app::get('ome')->model('shop')->dump(array('shop_id'=>$tgOrder['shop_id']), '*');
        
        // 运营组织
        $org_id = ($shopInfo['org_id'] ? $shopInfo['org_id'] : 1);
        
        // 来源
        $from_source = (isset($params['from']) ? $params['from'] : '');
        
        // data
        $insertData = array(
            'reship_bn'        => $params['reship_bn'] . '_' . $params['branch']['branch_id'] . '_' . $tgOrder['order_id'],
            'shop_id'          => $tgOrder['shop_id'],
            'order_id'         => $tgOrder['order_id'],
            'member_id'        => $tgOrder['member_id'],
            'logi_name'        => $tgOrder['logi_name'],
            'logi_no'          => $tgOrder['logi_no'],
            'logi_id'          => $tgOrder['logi_id'],
            'ship_name'        => $tgOrder['ship_name'],
            'ship_area'        => $tgOrder['ship_area'],
            'delivery'         => $tgOrder['shipping'],
            'ship_addr'        => $tgOrder['ship_addr'],
            'ship_zip'         => $tgOrder['ship_zip'],
            'ship_tel'         => $tgOrder['ship_tel'],
            'ship_email'       => $tgOrder['ship_email'],
            'ship_mobile'      => $tgOrder['ship_mobile'],
            'is_protect'       => $tgOrder['is_protect'],
            'branch_id'        => $params['branch']['branch_id'],
            'return_logi_name' => $params['logi_name'],
            'return_logi_no'   => $params['logi_no'],
            'outer_lastmodify' => strtotime($params['operate_time']),
            'source'           => 'matrix',
            't_begin'          => time(),
            'op_id'            => $opInfo['op_id'],
            'is_check'         => '1',
            'org_id' => $org_id,
        );
        
        // 是否使用WMS推送过来的reship_bn单据号
        //@todo：flag_type如果有值（例如：flag_type=2048原单退标签），则使用WMS推送过来的reship_bn单据号
        if($params['flag_type']){
            $insertData['flag_type'] = $params['flag_type'];
            $insertData['reship_bn'] = $params['reship_bn'];
        }
        
        $shop_info = app::get('ome')->model('shop')->getShopInfo($insertData['shop_id']);
        
        // 经销店铺的单据，delivery_mode冗余到售后申请表
        if ($shop_info['delivery_mode'] == 'jingxiao') {
            $insertData['delivery_mode'] = $shop_info['delivery_mode'];
        }
        $shop_type = $shop_info['shop_type'];
        $insertData['shop_type'] = $shop_type;
        $delivery = $this->_getOrderDelivery($tgOrder['order_id'], $items);
        $insertData['delivery_id'] = $delivery['delivery_id'];
        $modelReship = app::get('ome')->model('reship');
        
        $rs          = $modelReship->insert($insertData);
        if (!$rs) {
            return array('rsp' => 'succ', 'msg' => '退货单新建失败');
        }
        $reshipItems = array();
        $paramsItems = array();
        $tmoney = 0;
        $item_line_i = 0;
        foreach ($items as $item) {
            $nums = isset($item['nums']) ? (float) $item['nums'] : 0;
            $divide_order_fee = isset($item['divide_order_fee']) ? (float) $item['divide_order_fee'] : 0;
            $number = (float) $item['number'];
            
            // 单价 = 分摊实付 / 订购数量；全退时小计取整行实付，避免四舍五入误差
            $price = ($nums > 0) ? sprintf('%.2f', $divide_order_fee / $nums) : '0.00';
            if ($number == $nums && $nums > 0) {
                $amount = sprintf('%.2f', $divide_order_fee);
            } else {
                $amount = sprintf('%.2f', $price * $number);
            }
            
            $reshipItems[$item_line_i] = array(
                'reship_id'    => $insertData['reship_id'],
                'op_id'        => $opInfo['op_id'],
                'bn'           => $item['bn'],
                'num'          => $item['number'],
                'branch_id'    => $params['branch']['branch_id'],
                'product_name' => $item['name'],
                'product_id'   => $item['product_id'],
                'order_item_id' => $item['item_id'],
            );
            
            // 拦截退货单
            if($from_source == 'lanjie'){
                $reshipItems[$item_line_i]['price'] = $price; // 单价（来自 divide_order_fee）
                $reshipItems[$item_line_i]['amount'] = $amount; // 小计 = 单价 * 退货数量
            }
            
            $tmoney += (float) $amount;
            
            $paramsItems[] = array(
                'bn'            => $item['bn'],
                'normal_num'    => $item['number'] - $item['defective_num'],
                'defective_num' => $item['defective_num'],
            );
            
            // 明细行
            $item_line_i++;
        }
        $modelItem = app::get('ome')->model('reship_items');
        $sql       = ome_func::get_insert_sql($modelItem, $reshipItems);
        $modelItem->db->exec($sql);
        
        // 拦截退货单
        if($from_source == 'lanjie'){
            // 更新退货单相关金额
            $updateData = array(
                'tmoney'     => $tmoney, // 退款的金额
                'had_refund' => $tmoney, // 已退金额
                'totalmoney' => $tmoney, // 最后合计金额
                'sync_status' => '3', // 同步状态：推送成功
            );
            $modelReship->update($updateData, array('reship_id' => $insertData['reship_id']));
        }
        
        // 操作日志
        $oOperation_log = app::get('ome')->model('operation_log');
        $memo           = '销退接口新建退换货单';
        $oOperation_log->write_log('reship@ome', $insertData['reship_id'], $memo);
        
        return $this->status_update(array('status' => 'FINISH', 'auto_confirm' => true, 'reship_bn' => $insertData['reship_bn'], 'items' => $paramsItems));
    }
    protected function _getOrderDelivery($orderId, $items) {
        //获取订单关联的发货单(支持return_back追回状态)
        $sql = "SELECT dord.delivery_id, d.branch_id, d.logi_no, d.logi_name FROM sdb_ome_delivery_order AS dord
                  LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                  WHERE dord.order_id='".$orderId."'
                    AND (d.parent_id=0 OR d.is_bind='true')
                    AND d.disabled='false' AND d.status IN('succ','return_back')";
        $result = kernel::database()->select($sql);
        if(count($result) > 1 && !empty($items)) {
            $arrDelivery = array();
            foreach ($result as $key => $val) {
                $arrDelivery[$val['delivery_id']] = $val;
            }
            $productId = '';
            foreach($items as $iVal) {
                if($iVal['product_id']) {
                    $productId = $iVal['product_id'];
                    break;
                }
            }
            $deliItemModel = app::get('ome')->model('delivery_items');
            $itemData = $deliItemModel->getList('delivery_id', array('delivery_id'=>array_keys($arrDelivery), 'product_id'=>$productId), 0, 1);
        }
        return $itemData ? $arrDelivery[$itemData[0]['delivery_id']] : $result[0];
    }
    /**
     * WMS京东云交易订单退款成功MQ消息
     * 
     * @param array $params
     * @return array
     */
    public function service_refund($params)
    {
        return kernel::single('console_event_receive_reship')->service_refund($params);
    }
    
    /**
     * 创建拦截退换货单并且自动内部完成退货（不推送给WMS）
     * @todo：美团闪购、淘宝闪购会推送拦截单，这里做拦截单处理；
     *
     * @param $params
     * @return string[]
     */
    public function lanjieReship($params)
    {
        $orderBn     = array();
        $oids          = array();
        $bnProduct   = array();
        $bnProductCC = array();
        
        // items
        foreach ($params['items'] as $val)
        {
            $bnProduct[$val['order_bn'] . '|' . $val['oid']] += $val['num'];
            $bnProductCC[$val['order_bn'] . '|' . $val['oid']] += $val['ccnum'];
            $orderBn[] = $val['order_bn'];
            $oids[]      = $val['oid'];
        }
        
        // 检查订单是否存在
        $orderObj  = app::get('ome')->model('orders');
        $field     = 'order_id,order_bn,status,process_status,ship_status,pay_status,shop_id,member_id,logi_id,logi_no,ship_name,ship_area,ship_addr,ship_zip,ship_tel,ship_email,ship_mobile,order_bool_type,order_type';
        $orderList = $orderObj->getList($field, array('order_bn' => $orderBn));
        if (empty($orderList)) {
            return array('rsp' => 'fail', 'msg' => '没有该订单' . $params['items'][0]['order_bn']);
        }
        
        // 拦截退货单号 = 订单编号 + 仓库ID + 订单ID
        $orderData   = array();
        $strReshipBn = $params['reship_bn'] . '_' . $params['branch']['branch_id'] . '_';
        $arrReshipBn = array();
        foreach ($orderList as $val) {
            $orderData[$val['order_id']] = $val;
            $arrReshipBn[]               = $strReshipBn . $val['order_id'];
        }
        
        // 检查退货单号
        $modelReship = app::get('ome')->model('reship');
        $oldReship   = $modelReship->getList('order_id', array('reship_bn' => $arrReshipBn));
        foreach ($oldReship as $val) {
            if ($orderData[$val['order_id']]) {
                unset($orderData[$val['order_id']]);
            }
        }
        
        if (empty($orderData)) {
            return array('rsp' => 'fail', 'msg' => '该退货单已经生成' . $params['reship_bn']);
        }
        
        // 检查订单对象
        $objfilter = array('order_id' => array_keys($orderData), 'oid' => $oids,'delete' => 'false');
        $object = app::get('ome')->model('order_objects')->getList('oid,order_id,bn, obj_id, quantity,`delete`', $objfilter);
        
        // 按订单明细组织数据
        $orderBnItems = [];
        foreach($object as $ov)
        {
            // 获取订单items明细
            $itemFilter   = array('order_id' => $ov['order_id'], 'obj_id' => $ov['obj_id'], 'delete' => 'false');
            $orderItems   = app::get('ome')->model('order_items')->getList('*', $itemFilter);
            if(empty($orderItems)){
                continue;
            }
            
            // items
            foreach ($orderItems as $val) {
                $radio = $val['nums']/$ov['quantity'];
                $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'.$val['bn']]['order_id']   = $val['order_id'];
                $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'.$val['bn']]['oid']   = $ov['oid'];
                $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'.$val['bn']]['product_id'] = $val['product_id'];
                $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'.$val['bn']]['item_id']    = $val['item_id'];
                $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'. $val['bn']]['bn']         = $val['bn'];
                $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'. $val['bn']]['name']       = $val['name'];
                $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'. $val['bn']]['sendnum'] += $val['sendnum'];
                $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'. $val['bn']]['return_num'] += $val['return_num'];
                $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'. $val['bn']]['nums'] += $val['nums'];
                $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'. $val['bn']]['radio']= $radio;
                
                // 分摊之后的实付金额
                if(!isset($orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'. $val['bn']]['divide_order_fee'])){
                    $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'. $val['bn']]['divide_order_fee'] = $val['divide_order_fee'];
                }else{
                    $orderBnItems[$val['order_id'] . '|' .$ov['oid'].'|'. $val['bn']]['divide_order_fee'] += $val['divide_order_fee'];
                }
            }
        }
        
        // 组织退货单明细
        $items = array();
        foreach ($orderBnItems as $val) {
            $key                  = $orderData[$val['order_id']]['order_bn'] . '|' . $val['oid'];
            $normalnumber = ($bnProduct ? $bnProduct[$key] : 0);
            $ccnumber = ($bnProductCC ? $bnProductCC[$key] : 0);
            $number = $normalnumber*$val['radio'];

            $val['number']        = $number;

            $val['defective_num'] = $ccnumber*$val['radio'];
            if ($val['number'] > ($val['sendnum'] - $val['return_num'])) {
                return array('rsp' => 'fail', 'msg' => $val['bn'] . '超过发货数量');
            }

            $items[$val['order_id']][] = $val;
        }
        
        // check
        if (empty($items)) {
            return array('rsp' => 'fail', 'msg' => '没有获取到退货的明细，请检查!');
        }
        
        $msg = '';
        foreach ($items as $orderId => $val) {
            $params['from'] = 'lanjie';
            
            // 原单退
            $params['flag_type'] = ome_reship_const::__YUANDANTUI;
            
            // 生成退货单，款和货分开，款在平台退同步到ERP
            $rs = $this->dealOneReship($val, $orderData[$orderId], $params);

            $msg .= '订单' . $orderData[$orderId]['order_bn'] . '退货单处理:' . $rs['msg'] . '<br/>';
        }
        
        return array('rsp' => 'succ', 'msg' => $msg);
    }
}
