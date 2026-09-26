<?php
/**
 * 其它渠道处理发票Lib方法类
 *
 * @author wangbiao@shopex.cn
 * @version 2026.04.01
 */
class invoice_order_front_other extends invoice_order_front_abstract
{
    /**
     * 获取主表特殊信息
     *
     * @param $main
     * @return array
     */
    public function getMain($main)
    {
        // check
        if(empty($main['source_id'])){
            return [];
        }
        
        // order
        $orderInfo = app::get('ome')->model('orders')->db_dump(['order_id'=>$main['source_id']], '*');
        if(empty($orderInfo)) {
            $orderInfo = app::get('archive')->model('orders')->db_dump(['order_id'=>$main['source_id']], '*');
        }
        
        // shop
        $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$orderInfo['shop_id']], 'delivery_mode');
        if($shop['delivery_mode'] == 'jingxiao') {
            return [];
        }
        
        // format main
        $main['is_status'] = isset($main['is_status']) ? $main['is_status'] : ($orderInfo['is_tax'] == 'true' ? '0' : '2');
        $main['source_bn'] = $orderInfo['order_bn'];
        $main['shop_id'] = $orderInfo['shop_id'];
        $main['shop_type'] = $orderInfo['shop_type'];
        $main['amount'] = $main['amount'] ? : $orderInfo['total_amount'];
        $main['cost_freight'] = $orderInfo['cost_freight'];
        $main['org_id'] = intval($orderInfo['org_id']);
        
        // 对pos判断btq trade 不在列表里显示
        if($main['shop_type'] == 'pekon'){
            $stores = app::get('o2o')->model('store')->db_dump(array('shop_id'=>$main['shop_id']),'store_sort');
            if(in_array($stores['store_sort'],array('BTQ','Trade'))){
                $main['disabled'] = 'true';
            }
        }
        
        return $main;
    }
    
    /**
     * 获取明细信息
     *
     * @param $main
     * @return array
     */
    public function getItems($main)
    {
        // check
        if(empty($main['source_id']) && empty($main['order_id'])){
            return [];
        }
        
        $orderMdl = app::get('ome')->model('orders');
        $archiveOrderMdl = app::get('archive')->model('orders');
        
        // order_id
        $order_id = ($main['source_id'] ? $main['source_id'] : $main['order_id']);
        
        // app
        $app = 'ome';
        
        // 获取订单信息
        $orderInfo = $orderMdl->db_dump(array('order_id'=>$order_id), 'order_id,order_bn');
        if(empty($orderInfo)){
            $orderInfo = $archiveOrderMdl->db_dump(array('order_id'=>$order_id), 'order_id,order_bn');
            
            // app
            $app = 'archive';
        }
        
        // check
        if(empty($orderInfo)){
            return array();
        }
        
        // order_objects
        $objField = 'obj_id,goods_id,bn,name,divide_order_fee,actually_amount,quantity,`delete`';
        $objRows = app::get($app)->model('order_objects')->getList($objField, ['order_id'=>$order_id, 'delete'=>'false']);
        
        // order_items
        $itemField = 'item_id,obj_id,nums,return_num,product_id,bn,name,divide_order_fee,actually_amount,return_num,`delete`';
        $itemRows = app::get($app)->model('order_items')->getList($itemField, ['order_id'=>$order_id, 'delete'=>'false']);
        
        // 开票物料类型：basic按基础物料（默认）、sales按销售物料
        $get_material_type = app::get('ome')->getConf('ome.invoice.material.type');
        
        $ret = [];
        if ($get_material_type == 'sales') {
            $return = [];
            foreach((array)$itemRows as $v)
            {
                $obj_id = $v['obj_id'];
                
                $returnRadio = $v['return_num'] / $v['nums'];
                
                // check
                if($return[$obj_id] && $return[$obj_id] <= $returnRadio) {
                    continue;
                }
                
                $return[$obj_id] = $returnRadio;
            }
            
            foreach((array)$objRows as $v)
            {
                $obj_id = $v['obj_id'];
                
                $ret[$obj_id] = [
                    'item_type' => 'sales',
                    'of_id' => $main['id'],
                    'source_item_id' => $obj_id,
                    'bm_id' => $v['goods_id'],
                    'bn' => $v['bn'],
                    'name' => $v['name'],
                    'divide' => ($v['actually_amount'] ? $v['actually_amount'] : $v['divide_order_fee']), // 客户实付 or 分摊之后的实付金额
                    'amount' => ($v['actually_amount'] ? $v['actually_amount'] : $v['divide_order_fee']), // 客户实付 or 分摊之后的实付金额
                    'divide_order_fee' => ($v['actually_amount'] ? $v['actually_amount'] : $v['divide_order_fee']), // 客户实付 or 分摊之后的实付金额
                    'quantity' => $v['quantity'],
                    'reship_num' => sprintf('%.0f', $v['quantity'] * $return[$v['obj_id']]),
                    'is_delete' => $v['delete'],
                    
                    'order_item_id' => $obj_id,
                    'material_bn' => $v['bn'],
                    'material_name' => $v['name'],
                    'return_num' => sprintf('%.0f', $v['quantity'] * $return[$v['obj_id']]),
                    'invoice_item_type' => 'sales',
                ];
            }
        }else{
            foreach((array)$itemRows as $v)
            {
                $item_id = $v['item_id'];
                
                $ret[$item_id] = [
                    'item_type' => 'basic',
                    'of_id' => $main['id'],
                    'source_item_id' => $v['item_id'],
                    'bm_id' => $v['product_id'],
                    'bn' => $v['bn'],
                    'name' => $v['name'],
                    'divide' => ($v['actually_amount'] ? $v['actually_amount'] : $v['divide_order_fee']), // 客户实付 or 分摊之后的实付金额
                    'amount' => ($v['actually_amount'] ? $v['actually_amount'] : $v['divide_order_fee']), // 客户实付 or 分摊之后的实付金额
                    'divide_order_fee' => ($v['actually_amount'] ? $v['actually_amount'] : $v['divide_order_fee']), // 客户实付 or 分摊之后的实付金额
                    'quantity' => $v['nums'],
                    'reship_num' => $v['return_num'],
                    'is_delete' => $v['delete'],
                    
                    'order_item_id' => $v['item_id'],
                    'material_bn' => $v['bn'],
                    'material_name' => $v['name'],
                    'return_num' => $v['return_num'],
                    'invoice_item_type' => 'basic',
                ];
            }
        }
        
        return $ret;
    }
    
    /**
     * 人工操作
     *
     * @param $operateParams
     * @return array
     */
    public function operateTax($operateParams)
    {
        $objFront = app::get('invoice')->model('order_front');
        
        // order_id
        $order_id = ($operateParams['order_id'] ? $operateParams['order_id'] : $operateParams['source_id']);
        $order_id = intval($order_id);
        
        // check
        if(empty($order_id)){
            return [false, ['msg'=>'没有订单order_id']];
        }
        
        // 注册service，定制处理人工操作发票业务
        foreach(kernel::servicelist('ome.service.invoice.analysis.prepose') as $object)
        {
            if(method_exists($object, 'custom_invoice_operateTax')){
                // 获取项目上定制的发票来源
                $customResult = $object->custom_invoice_operateTax($operateParams);
                if(isset($customResult['rsp']) && $customResult['rsp']){
                    if($customResult['rsp'] == 'succ'){
                        return [false, ['msg'=>'定制操作发票成功!']];
                    }else{
                        return [false, ['msg'=>'定制操作发票失败：'. $customResult['error_msg']]];
                    }
                }
            }
        }
        
        // source_id
        $operateParams['source_id'] = $order_id;
        
        // filter
        $filter = ['source_id'=>$order_id];
        
        // invoice_source
        $invoice_source = $operateParams['invoice_source'];
        if($invoice_source){
            $filter['source'] = $invoice_source;
        }
        
        // order_front
        $oldRow = $objFront->db_dump($filter, '*');
        if($oldRow) {
            $upData = [];
            if($operateParams['is_tax']) {
                $is_status = $operateParams['is_tax'] == 'true' ? '0' : '2';
                if($is_status != $oldRow['is_status']) {
                    $upData['is_status'] = $is_status;
                }
            }
            
            if($operateParams['invoice_kind']) {
                if($operateParams['invoice_kind'] != $oldRow['mode']) {
                    $upData['mode'] = $operateParams['invoice_kind'];
                }
            }
            
            if($operateParams['source_status'] == 'TRADE_FINISHED') {
                if($oldRow['status'] != 'finish') {
                    $upData['status'] = 'finish';
                }
            } elseif ($operateParams['source_status'] == 'TRADE_CLOSED') {
                if($oldRow['status'] != 'close') {
                    $upData['status'] = 'close';
                }
            }
            
            if($operateParams['title']) {
                if($operateParams['title'] != $oldRow['title']) {
                    $upData['title'] = $operateParams['title'];
                }
            }
            
            if($operateParams['ship_tax']) {
                if($operateParams['ship_tax'] != $oldRow['ship_tax']) {
                    $upData['ship_tax'] = $operateParams['ship_tax'];
                }
            }
            
            if(empty($upData)) {
                return [false, ['msg'=>'无更新数据']];
            }
            
            // update
            $objFront->update($upData, ['id'=>$oldRow['id']]);
            
            // 更新发票信息
            kernel::single('invoice_order_front')->eventUpdate($oldRow['id'], $upData);
            
            return [true, ['msg'=>'更新成功']];
        }
        
        // order
        $orderInfo = app::get('ome')->model('orders')->db_dump(['order_id'=>$order_id], '*');
        if(empty($orderInfo)) {
            $orderInfo = app::get('archive')->model('orders')->db_dump(['order_id'=>$order_id], '*');
        }
        
        // unset order cost_tax
        unset($orderInfo['cost_tax']);
        
        // unset order is_auto
        unset($orderInfo['is_auto']);
        
        // 获取订单明细sdb_ome_order_items并且是已发货状态（sendnum > 0）
        $invoiceItems = $this->getItems($operateParams);
        if(empty($invoiceItems)){
            return [false, ['msg'=>'手工开票没有获取到订单开票明细!']];
        }
        
        // 订单开票明细
        $orderInfo['items'] = $invoiceItems;
        
        // params
        $params = $orderInfo;
        
        // source_id
        $params['source_id'] = $order_id;
        
        // source
        $params['source'] = $invoice_source;
        
        // source_status
        $source_status = $operateParams['source_status'] ? : $orderInfo['source_status'];
        
        // 指定的开票申请单号
        if (isset($operateParams['invoice_apply_bn']) && !empty($operateParams['invoice_apply_bn'])) {
            $params['invoice_apply_bn'] = trim((string) $operateParams['invoice_apply_bn']);
        }
        
        // 税率
        if(isset($operateParams['tax_rate']) && $operateParams['tax_rate'] >= 0){
            $params['tax_rate'] = $operateParams['tax_rate'];
        }
        
        // 税金
        if(isset($operateParams['cost_tax']) && $operateParams['cost_tax'] >= 0){
            $params['cost_tax'] = $params['cost_tax'];
        }
        
        // 是否自动开票(1:自动开票, 2:手工开票（默认）)
        if(isset($operateParams['is_auto']) && $operateParams['is_auto']){
            $params['is_auto'] = $operateParams['is_auto'];
        }
        
        // channel
        $shopInfo =  app::get('invoice')->model('channel')->get_channel_info($orderInfo['shop_id']);
        if ($shopInfo['einvoice_operating_conditions'] == '1' && $orderInfo['ship_status'] == '1' && $source_status != 'TRADE_CLOSED') {
            //发货完成
            $source_status = 'TRADE_FINISHED';
        }
        
        if($source_status == 'TRADE_FINISHED') {
            $params['status'] = 'finish';
        } elseif ($source_status == 'TRADE_CLOSED') {
            $params['status'] = 'close';
        }
        
        $oi = app::get('ome')->model('order_invoice')->db_dump(['order_id'=>$order_id], '*');
        if($oi) {
            // 开发票金额
            if($oi['invoice_amount'] == 0) {
                unset($oi['invoice_amount']);
            }
            
            // merge
            $params = array_merge($params, $oi);
        }
        
        // 获取平台最新开票金额
        list($rs, $rsData) = kernel::single('invoice_order')->getInvoiceMoney($orderInfo);
        if($rs) {
            $params['invoice_amount'] = $rsData['amount'];
        }
        
        // 开票方式
        if(isset($operateParams['invoice_kind']) && $operateParams['invoice_kind']){
            $params['invoice_kind'] = $operateParams['invoice_kind'];
        }elseif(isset($params['invoice_kind']) && $params['invoice_kind']){
            $params['invoice_kind'] = $params['invoice_kind'] == '1' ? '1' : ($params['invoice_kind'] == '0' ? '2' : '3');
        }
        
        // 发票抬头
        if ($operateParams['tax_title']) {
            $params['tax_title'] = $operateParams['tax_title'];
        }elseif($operateParams['title']) {
            $params['tax_title'] = $operateParams['title'];
        }
        
        if ($operateParams['ship_tax']) {
            $params['register_no'] = $operateParams['ship_tax'];
        }
        
        return [false, ['msg'=>'禁止使用此方法!']];
    }
}