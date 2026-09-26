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
 * 补发订单业务Lib方法
 *
 * @author wangbiao@shopex.cn
 * @version 2024.03.20
 */
class ome_order_bufa
{
    /**
     * 延迟创建补发赠品订单
     *
     * @param array $orderIds 订单ID
     * @return array
     */
    public function createOrder($params)
    {
        $logMdl = app::get('ome')->model('operation_log');
        
        //opinfo
        $opinfo = kernel::single('ome_func')->getDesktopUser();
        
        //get
        $order_id = $params['obj_id'];
        
        //exec
        $result = $this->createBufaOrder($params);
        if($result['rsp'] == 'succ'){
            //log原订单记录日志
            $msg = 'CRM补发赠品订单号：'. $result['order_bn'] .'创建成功';
            $logMdl->write_log('order_preprocess@ome', $order_id, $msg, time(), $opinfo);
        }elseif($result['is_create_order'] == 'true'){
            //log原订单记录日志
            $msg = 'CRM补发赠品订单创建成功，复制敏感信息失败：'. $result['error_msg'];
            $logMdl->write_log('order_preprocess@ome', $order_id, $msg, time(), $opinfo);
        }else{
            //log原订单记录日志
            $msg = 'CRM补发赠品订单创建失败：'. $result['error_msg'];
            $logMdl->write_log('order_preprocess@ome', $order_id, $msg, time(), $opinfo);
        }
        
        return $result;
    }
    
    /**
     * 创建补发赠品订单
     *
     * @param array $orderIds 订单ID
     * @return array
     */
    public function createBufaOrder($params)
    {
        $orderMdl = app::get('ome')->model('orders');
        $salesMdl = app::get('material')->model('sales_material');
        
        $orderLib = kernel::single('ome_order');
        $salesMLib = kernel::single('material_sales_material');
        
        //setting
        $result = array('rsp'=>'fail', 'error_msg'=>'');
        $error_msg = '';
        
        //get
        $order_id = $params['obj_id'];
        $extendInfo = json_decode($params['extend_info'], true);
        $giftSalesMaterial = $extendInfo['giftSalesMaterial'];
        
        //check
        if(empty($order_id) || empty($giftSalesMaterial)){
            $error_msg = '任务参数无效';
            $result['error_msg'] = $error_msg;
            return $result;
        }
        
        //订单信息
        $ordersdf = $orderMdl->db_dump(array('order_id'=>$order_id), '*');
        if(empty($ordersdf)){
            $error_msg = '订单信息不存在';
            $result['error_msg'] = $error_msg;
            return $result;
        }
        
        $order_bn = $ordersdf['order_bn'];
        
        //check是否已经退货
        if(in_array($ordersdf['ship_status'], array('4'))){
            $error_msg = '订单已经退货，不能赠送赠品';
            $result['error_msg'] = $error_msg;
            return $result;
        }
        
        //check是否已经全额退款
        if(in_array($ordersdf['pay_status'], array('5'))){
            $error_msg = '订单已经全额退款，不能赠送赠品';
            $result['error_msg'] = $error_msg;
            return $result;
        }
        
        //check是否有退货申请单
        $sql = "SELECT return_id,return_bn FROM sdb_ome_return_product WHERE order_id=". $order_id ." AND status NOT IN('5','9','10')";
        $returnInfo = $orderMdl->db->selectrow($sql);
        if($returnInfo){
            $error_msg = '订单有退货申请单：'. $returnInfo['return_bn'] .'，不能赠送赠品';
            $result['error_msg'] = $error_msg;
            return $result;
        }
        
        //检查是否已经创建过补发订单
        $checkOrder = $orderMdl->db_dump(array('relate_order_bn'=>$order_bn, 'order_type'=>'bufa'), 'order_id,order_bn');
        if($checkOrder){
            $error_msg = '已经有关联补发订单：'. $checkOrder['order_bn'] .'不能重复创建';
            $result['error_msg'] = $error_msg;
            return $result;
        }
        
        //format
        $giftBns = array();
        $goodsList = array();
        foreach ($giftSalesMaterial as $key => $val)
        {
            $sales_material_bn = $val['sales_material_bn'];
            
            $giftBns[$sales_material_bn] = $sales_material_bn;
            $goodsList[$sales_material_bn] = $val;
        }
        
        //根据赠品货号,找到对应的销售物料
        $saleMaterList = $salesMdl->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type',array('sales_material_bn'=>$giftBns));
        if(empty($saleMaterList)){
            $error_msg = '销售物料('. implode(',', $giftBns) .')不存在';
            $result['error_msg'] = $error_msg;
            return $result;
        }
        
        //补发订单信息(复制原平台订单信息)
        $buOrderSdf = $ordersdf;
        
        //订单号
        $bufa_order_bn = $orderMdl->gen_id('bufa');
        $buOrderSdf['order_bn'] = $bufa_order_bn;
        unset($buOrderSdf['order_id'], $buOrderSdf['archive'], $buOrderSdf['splited_num'], $buOrderSdf['itemnum'], $buOrderSdf['discount'], $buOrderSdf['pmt_goods'], $buOrderSdf['pmt_order']);
        unset($buOrderSdf['is_tax'], $buOrderSdf['cost_tax'], $buOrderSdf['tax_company'], $buOrderSdf['is_protect'], $buOrderSdf['refund_money'], $buOrderSdf['print_finish']);
        unset($buOrderSdf['up_time'], $buOrderSdf['download_time'], $buOrderSdf['last_modified'], $buOrderSdf['outer_lastmodify'], $buOrderSdf['logi_id'], $buOrderSdf['logi_no']);
        unset($buOrderSdf['group_id'], $buOrderSdf['op_id'], $buOrderSdf['sync']);
        
        //重置订单信息
        $buOrderSdf['order_type'] = 'bufa'; //补发订单类型
        $buOrderSdf['source'] = 'local'; //订单来源
        $buOrderSdf['createway'] = 'local'; //订单生成类型
        $buOrderSdf['relate_order_bn'] = $order_bn; //关联订单号
        
        $buOrderSdf['confirm'] = 'N';
        $buOrderSdf['process_status'] = 'unconfirmed';
        $buOrderSdf['status'] = 'active';
        $buOrderSdf['pay_status'] = '1';
        $buOrderSdf['ship_status'] = '0';
        
        $buOrderSdf['pay_bn'] = $bufa_order_bn; //支付单号,直接使用补发订单号
        $buOrderSdf['createtime'] = time();
        $buOrderSdf['paytime'] = time();
        $buOrderSdf['modifytime'] = time();
        $buOrderSdf['is_modify'] = 'false';
        $buOrderSdf['logi_no'] = '';
        $buOrderSdf['source_status'] = '';
        $buOrderSdf['payed'] = 0.00; //已付金额
        $buOrderSdf['total_amount'] = 0.00; //订单总额
        $buOrderSdf['final_amount'] = 0.00; //订单换算汇率后总额
        $buOrderSdf['cost_item'] = 0.00; //商品金额
        $buOrderSdf['custom_mark'] = ''; //客户备注
        $buOrderSdf['mark_text'] = ''; //商家备注
        
        //收货人信息
        $buOrderSdf['consignee'] = array(
            'name' => $ordersdf['ship_name'],
            'addr' =>$ordersdf['ship_addr'],
            'zip' => $ordersdf['ship_zip'],
            'telephone' => $ordersdf['ship_tel'],
            'mobile' => $ordersdf['ship_mobile'],
            'email' => $ordersdf['ship_email'],
            'area' => $ordersdf['ship_area'],
        );
        
        //快递信息
        $buOrderSdf['shipping'] = array(
            'shipping_id' => 0,
            'is_cod' => 'false',
            'shipping_name' => '快递',
            'cost_shipping' => 0,
            'is_protect' => '',
            'cost_protect' => 0,
        );
        
        //订单明细
        $order_objects = array();
        foreach($saleMaterList as $goodsInfo)
        {
            $obj_type = 'gift';
            $obj_alias = '赠品';
            $item_type = 'gift';
            $sales_material_bn = $goodsInfo['sales_material_bn'];
            
            //CRM赠品赠送的数量
            $quantity = $goodsList[$sales_material_bn]['quantity'];
            
            //obj
            $orderObjs = array(
                'obj_type' => $obj_type,
                'obj_alias' => $obj_alias,
                'shop_goods_id' => '-1', //CRM赠品类型标识
                'goods_id' => $goodsInfo['sm_id'],
                'bn' => $goodsInfo['sales_material_bn'],
                'name' => $goodsInfo['sales_material_name'],
                'price' => 0.00,
                'sale_price' => 0.00,
                'pmt_price' => 0.00,
                'amount' => 0.00,
                'quantity' => $quantity,
            );
            
            //获取基础物料列表
            $order_items = array();
            $basicMInfos = $salesMLib->getBasicMBySalesMId($goodsInfo['sm_id']);
            foreach($basicMInfos as $basiKey => $basicMInfo)
            {
                $items = array(
                    'product_id' => $basicMInfo['bm_id'],
                    'shop_goods_id' => '-1', //CRM赠品类型标识
                    'shop_product_id' => '-1', //CRM赠品类型标识
                    'bn' => $basicMInfo['material_bn'],
                    'name' => $basicMInfo['material_name'],
                    'cost' => 0.00,
                    'price' => 0.00,
                    'amount' => 0.00,
                    'sale_price'=> 0.00,
                    'pmt_price' => 0.00,
                    'quantity' => $quantity * $basicMInfo['number'], //CRM返回的赠品数量
                    'sendnum' => 0,
                    'item_type' => $item_type,
                );
                
                $order_items[] = $items;
            }
            
            $orderObjs['order_items'] = $order_items;
            
            //merge
            $order_objects[] = $orderObjs;
        }
        
        $buOrderSdf['order_objects'] = $order_objects;
        
        //创建补发类型的订单
        $orderResult = $orderMdl->create_order($buOrderSdf, $error_msg);
        if(!$orderResult){
            $error_msg = '创建补发赠品订单号失败：'. $error_msg;
            $result['error_msg'] = $error_msg;
            return $result;
        }
        
        //更新为已支付(0元订单创建时是未支付)
        $orderMdl->update(array('pay_status'=>'1'), array('order_id'=>$buOrderSdf['order_id']));
        
        //复制原订单收件人敏感数据
        $bufaResult = $orderLib->createBufaOrderEncrypt($buOrderSdf, $error_msg);
        if(!$bufaResult && $error_msg){
            $error_msg = '复制原平台订单收件人敏感数据失败：'. $error_msg;
            $result['error_msg'] = $error_msg;
            $result['is_create_order'] = 'true';
            
            return $result;
        }
        
        $result['rsp'] = 'succ';
        $result['order_bn'] = $buOrderSdf['order_bn'];
        return $result;
    }
    
    /**
     * [订单创建之前]处理补发订单相关数据
     *
     * @param array $orderSdf 待创建的补发订单数据
     * @return array
     */
    public function disposeBufaCreateOrder($orderSdf)
    {
        if (!is_array($orderSdf) || ($orderSdf['order_type'] ?? '') !== 'bufa') {
            return $orderSdf;
        }
        
        // 按关联原订单明细回填客户实付、分摊实付等金额
        $orderSdf = $this->applyRelateOrderPayAmount($orderSdf);
        
        // 扣减原订单价保退款金额
        $orderSdf = $this->deductionProtectionAmount($orderSdf);
        
        return $orderSdf;
    }

    /**
     * 按关联原订单明细回填补发单商品实付金额
     *
     * 数量一致时直接使用原单 actually_amount / divide_order_fee；
     * 数量不一致时按原单数量比例折算。
     *
     * @param array $orderSdf
     * @return array
     */
    protected function applyRelateOrderPayAmount($orderSdf)
    {
        $relateOrderBn = trim($orderSdf['relate_order_bn'] ?? '');
        if ($relateOrderBn === '' || empty($orderSdf['order_objects'])) {
            return $orderSdf;
        }

        $relateOrder = app::get('ome')->model('orders')->dump(['order_bn' => $relateOrderBn], 'order_id');
        if (empty($relateOrder)) {
            return $orderSdf;
        }
        
        // 获取关联原订单销售物料明细
        $fields = 'oid,goods_id,bn,quantity,divide_order_fee,actually_amount,settlement_amount,part_mjz_discount,`delete`';
        $obj_fiter = ['order_id' => $relateOrder['order_id'], 'delete' => 'false'];
        $originalObjects = app::get('ome')->model('order_objects')->getList($fields, $obj_fiter);
        if (empty($originalObjects)) {
            return $orderSdf;
        }
        
        $originalObjectMap = $this->buildOriginalObjectMap($originalObjects);
        $sumDivideOrderFee = 0;

        foreach ($orderSdf['order_objects'] as $objKey => $orderObject) {
            $originalObject = $this->matchOriginalOrderObject($orderObject, $originalObjectMap);
            if (empty($originalObject)) {
                $sumDivideOrderFee += floatval($orderObject['divide_order_fee'] ?? $orderObject['sale_price'] ?? 0);
                continue;
            }

            $newQuantity = floatval($orderObject['quantity'] ?? 0);
            $originalQuantity = floatval($originalObject['quantity'] ?? 0);
            if ($newQuantity <= 0 || $originalQuantity <= 0) {
                continue;
            }

            if (bccomp((string) $newQuantity, (string) $originalQuantity, 3) === 0) {
                $divideOrderFee = sprintf('%.2f', floatval($originalObject['divide_order_fee']));
                $actuallyAmount = sprintf('%.2f', floatval($originalObject['actually_amount']));
                $settlementAmount = sprintf('%.2f', floatval($originalObject['settlement_amount']));
            } else {
                $ratio = $newQuantity / $originalQuantity;
                $divideOrderFee = sprintf('%.2f', floatval($originalObject['divide_order_fee']) * $ratio);
                $actuallyAmount = sprintf('%.2f', floatval($originalObject['actually_amount']) * $ratio);
                $settlementAmount = sprintf('%.2f', floatval($originalObject['settlement_amount']) * $ratio);
            }

            $salePrice = floatval($orderObject['sale_price'] ?? $orderObject['amount'] ?? 0);
            $platformPayAmount = sprintf('%.2f', floatval($divideOrderFee) - floatval($actuallyAmount));
            $platformAmount = sprintf('%.2f', floatval($settlementAmount) - floatval($divideOrderFee));
            $partMjzDiscount = sprintf('%.2f', $salePrice - floatval($divideOrderFee));

            $orderSdf['order_objects'][$objKey]['oid'] = $originalObject['oid'];
            $orderSdf['order_objects'][$objKey]['divide_order_fee'] = $divideOrderFee;
            $orderSdf['order_objects'][$objKey]['actually_amount'] = $actuallyAmount;
            $orderSdf['order_objects'][$objKey]['settlement_amount'] = $settlementAmount;
            $orderSdf['order_objects'][$objKey]['platform_pay_amount'] = $platformPayAmount;
            $orderSdf['order_objects'][$objKey]['platform_amount'] = $platformAmount;
            $orderSdf['order_objects'][$objKey]['part_mjz_discount'] = $partMjzDiscount;

            $sumDivideOrderFee += floatval($divideOrderFee);
        }

        if (bccomp((string) $sumDivideOrderFee, '0', 3) > 0) {
            $mathLib = kernel::single('eccommon_math');
            $orderSdf['total_amount'] = $mathLib->number_minus([
                $mathLib->number_plus([
                    $sumDivideOrderFee,
                    floatval($orderSdf['shipping']['cost_shipping'] ?? 0),
                    floatval($orderSdf['discount'] ?? 0),
                ]),
                floatval($orderSdf['pmt_order'] ?? 0),
            ]);
        }

        return $orderSdf;
    }

    /**
     * 构建原订单明细索引（goods_id / bn -> 明细）
     *
     * @param array $originalObjects
     * @return array
     */
    protected function buildOriginalObjectMap($originalObjects)
    {
        $map = ['goods_id' => [], 'bn' => []];
        foreach ($originalObjects as $originalObject) {
            if (($originalObject['delete'] ?? 'false') === 'true') {
                continue;
            }
            if (!empty($originalObject['goods_id'])) {
                $map['goods_id'][$originalObject['goods_id']] = $originalObject;
            }
            if (!empty($originalObject['bn'])) {
                $map['bn'][$originalObject['bn']] = $originalObject;
            }
        }

        return $map;
    }

    /**
     * 按 goods_id / bn 匹配原订单明细
     *
     * @param array $orderObject
     * @param array $originalObjectMap
     * @return array|null
     */
    protected function matchOriginalOrderObject($orderObject, $originalObjectMap)
    {
        if (!empty($orderObject['goods_id']) && isset($originalObjectMap['goods_id'][$orderObject['goods_id']])) {
            return $originalObjectMap['goods_id'][$orderObject['goods_id']];
        }
        if (!empty($orderObject['bn']) && isset($originalObjectMap['bn'][$orderObject['bn']])) {
            return $originalObjectMap['bn'][$orderObject['bn']];
        }

        return null;
    }
    
    /**
     * [订单创建之前]扣减掉原订单的价保金额
     * 场景：原订单 refund_money（价保费用）大于 0 时，按子订单号 oid 匹配价保退款申请单，
     * 将价保退款金额累加到补发单商品优惠及订单商品促销优惠，并扣减分摊实付、客户实付、结算金额。
     *
     * @param $orderSdf
     * @return array|mixed
     */
    public function deductionProtectionAmount($orderSdf)
    {
        $relateOrderBn = trim($orderSdf['relate_order_bn'] ?? '');
        if ($relateOrderBn === '' || empty($orderSdf['order_objects'])) {
            return $orderSdf;
        }
        
        $orderMdl = app::get('ome')->model('orders');
        $relateOrder = $orderMdl->dump(['order_bn' => $relateOrderBn], 'order_id,refund_money');
        if (empty($relateOrder) || bccomp((string) ($relateOrder['refund_money'] ?: 0), '0', 3) <= 0) {
            return $orderSdf;
        }
        
        $mathLib = kernel::single('eccommon_math');
        $oidByGoodsId = [];
        $oidByBn = [];
        $originalObjects = app::get('ome')->model('order_objects')->getList(
            'oid,goods_id,bn',
            ['order_id' => $relateOrder['order_id'], 'delete' => 'false']
        );
        foreach ($originalObjects as $originalObject) {
            $originalOid = trim($originalObject['oid']);
            if ($originalOid === '') {
                continue;
            }
            if (!empty($originalObject['goods_id'])) {
                $oidByGoodsId[$originalObject['goods_id']] = $originalOid;
            }
            if (!empty($originalObject['bn'])) {
                $oidByBn[$originalObject['bn']] = $originalOid;
            }
        }
        
        // 获取价保退款类型的退款申请单
        $refundByOid = [];
        $refundApplyList = app::get('ome')->model('refund_apply')->getList(
            'oid,money',
            [
                'order_id' => $relateOrder['order_id'],
                'tag_type' => '1',
                'disabled' => 'false',
            ]
        );
        foreach ($refundApplyList as $refundApplyRow) {
            $refundOid = trim($refundApplyRow['oid']);
            $refundMoney = floatval($refundApplyRow['money']);
            if ($refundOid === '' || $refundMoney <= 0) {
                continue;
            }
            if (!isset($refundByOid[$refundOid])) {
                $refundByOid[$refundOid] = 0;
            }
            $refundByOid[$refundOid] = $mathLib->number_plus([$refundByOid[$refundOid], $refundMoney]);
        }
        
        if (empty($refundByOid)) {
            return $orderSdf;
        }
        
        $extraPmtGoods = 0;
        foreach ($orderSdf['order_objects'] as $objKey => $orderObject)
        {
            $oid = isset($orderObject['oid']) ? trim($orderObject['oid']) : '';
            if ($oid === '') {
                if (!empty($orderObject['goods_id']) && isset($oidByGoodsId[$orderObject['goods_id']])) {
                    $oid = $oidByGoodsId[$orderObject['goods_id']];
                } elseif (!empty($orderObject['bn']) && isset($oidByBn[$orderObject['bn']])) {
                    $oid = $oidByBn[$orderObject['bn']];
                }
            }
            
            if ($oid === '' || !isset($refundByOid[$oid])) {
                continue;
            }
            
            $refundAmount = $refundByOid[$oid];
            $objectAmount = floatval(
                $orderObject['amount'] ?: $mathLib->number_multiple([$orderObject['price'], $orderObject['quantity']])
            );
            $objectPmtPrice = floatval(isset($orderObject['pmt_price']) ? $orderObject['pmt_price'] : 0);
            $objectPmtPrice = $mathLib->number_plus([$objectPmtPrice, $refundAmount]);
            
            $orderSdf['order_objects'][$objKey]['oid'] = $oid;
            $orderSdf['order_objects'][$objKey]['pmt_price'] = $objectPmtPrice;
            $orderSdf['order_objects'][$objKey]['sale_price'] = $mathLib->number_minus([$objectAmount, $objectPmtPrice]);

            $divideOrderFee = floatval($orderObject['divide_order_fee'] ?? 0);
            $actuallyAmount = floatval($orderObject['actually_amount'] ?? 0);
            $settlementAmount = floatval($orderObject['settlement_amount'] ?? 0);
            $newDivideOrderFee = $mathLib->number_minus([$divideOrderFee, $refundAmount]);
            $newActuallyAmount = $mathLib->number_minus([$actuallyAmount, $refundAmount]);
            $newSettlementAmount = $mathLib->number_minus([$settlementAmount, $refundAmount]);
            $newSalePrice = floatval($orderSdf['order_objects'][$objKey]['sale_price']);

            $orderSdf['order_objects'][$objKey]['divide_order_fee'] = sprintf('%.2f', $newDivideOrderFee);
            $orderSdf['order_objects'][$objKey]['actually_amount'] = sprintf('%.2f', $newActuallyAmount);
            $orderSdf['order_objects'][$objKey]['settlement_amount'] = sprintf('%.2f', $newSettlementAmount);
            $orderSdf['order_objects'][$objKey]['part_mjz_discount'] = sprintf('%.2f', $newSalePrice - floatval($newDivideOrderFee));
            $orderSdf['order_objects'][$objKey]['platform_pay_amount'] = sprintf('%.2f', floatval($newDivideOrderFee) - floatval($newActuallyAmount));
            $orderSdf['order_objects'][$objKey]['platform_amount'] = sprintf('%.2f', floatval($newSettlementAmount) - floatval($newDivideOrderFee));
            
            $extraPmtGoods = $mathLib->number_plus([$extraPmtGoods, $refundAmount]);
        }
        
        if (bccomp((string) $extraPmtGoods, '0', 3) > 0) {
            $orderSdf['pmt_goods'] = $mathLib->number_plus([
                floatval(isset($orderSdf['pmt_goods']) ? $orderSdf['pmt_goods'] : 0),
                $extraPmtGoods,
            ]);
            
            $orderSdf['total_amount'] = $mathLib->number_minus([
                floatval(isset($orderSdf['total_amount']) ? $orderSdf['total_amount'] : 0),
                $extraPmtGoods,
            ]);
        }
        
        return $orderSdf;
    }
}