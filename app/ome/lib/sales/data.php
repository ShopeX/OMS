<?php

class ome_sales_data
{
    private $appName = 'ome';
    
    public function setAppName($appName) {
        $this->appName = $appName;
        return $this;
    }
    
    public function generate($original_data, $delivery_id)
    {
        if (!$original_data || !$delivery_id) {
            return false;
        }

        $tmp_sales_data = array();
        $tmp_sales_data = $this->_generate_basic($original_data, $delivery_id);

        $effectiveObjRefund = $this->_objectEffectiveRefundMoney($original_data);
        $smBmRate = kernel::single('ome_order')->getSmBmRate($original_data);
        $refundByItemId = $this->_effectiveRefundMoneyByOrderItems($original_data, $effectiveObjRefund, $smBmRate);

        //生成销售明细信息
        $tmp_sales_data['sales_items'] = array();
        $deliveryObj = app::get('ome')->model('delivery');
        $delivery_items_detailObj = app::get('ome')->model('delivery_items_detail');
        $basicMaterialExtObj    = app::get('material')->model('basic_material_ext');
        foreach ($original_data['order_objects'] as $key => $obj)
        {
            $obj_id = $obj['obj_id'];
            $obj_refund_sum = 0.0;

            $items = $obj['order_items'];
            foreach($items as $k =>$item) {
                $line_refund = isset($refundByItemId[$item['item_id']]) ? (float) $refundByItemId[$item['item_id']] : 0.0;
                $obj_refund_sum += $line_refund;
                //物料规格
                $material_ext = $basicMaterialExtObj->db_dump(array('bm_id'=>$item['product_id']), 'bm_id, specifications');
                //sale_items
                $tmp_sales_data['sales_items'][$item['item_id']] = array(
                    'iostock_id'=>'',
                    'product_id' => $item['product_id'],
                    'bn' => $item['bn'],
                    'name' => $item['name'],
                    'spec_name'=> $material_ext['specifications'],
                    'cost'=> $item['cost'],
                    'obj_id' => $obj['obj_id'],
                    'obj_type'=>$item['item_type'],
                    'sales_material_bn'=>$obj['bn'],
                    's_type' => $obj['s_type'],
                    'oid' => $obj['oid'],
                    'order_item_id' => $item['item_id'],
                    'orginal_price' => $item['price'],
                    'price' => $item['price'],
                    'nums' => $item['quantity'],
                    'amount' => $item['price'] * $item['quantity'],
                    'pmt_price' => $item['pmt_price'],
                    'sale_price' => $item['sale_price'],
                    'apportion_pmt' => $item['part_mjz_discount'], //商品总优惠分摊金额
                    'sales_amount' => $item['divide_order_fee'],
                    'platform_amount' => $item['platform_amount'],
                    'settlement_amount' => sprintf('%.2f', (float) $item['settlement_amount'] - $line_refund),
                    'platform_pay_amount' => $item['platform_pay_amount'],
                    'actually_amount' => sprintf('%.2f', (float) $item['actually_amount'] - $line_refund),
                    'refund_money' => $line_refund,
                    'addon' => json_encode(['shop_goods_id' => $item['shop_goods_id'], 'shop_product_id' => $item['shop_product_id']],JSON_UNESCAPED_UNICODE),
                );

                $delivery_items_detail_info = $delivery_items_detailObj->db_dump(array('order_id'=>$item['order_id'],'order_item_id'=>$item['item_id'],'order_obj_id'=>$item['obj_id']), 'item_detail_id, delivery_id');
                $tmp_sales_data['sales_items'][$item['item_id']]['item_detail_id'] = $delivery_items_detail_info['item_detail_id'];

                $delivery_info = $deliveryObj->db_dump(array('delivery_id'=>$delivery_items_detail_info['delivery_id']),'branch_id');
                $tmp_sales_data['sales_items'][$item['item_id']]['branch_id'] = $delivery_info['branch_id'];
            }
            $obj_refund_money = isset($effectiveObjRefund[$obj_id]) ? (float) $effectiveObjRefund[$obj_id] : $obj_refund_sum;
            //销售单objects对象明细
            $tmp_sales_data['sales_objects'][$obj_id] = array(
                    'order_id' => $obj['order_id'],
                    'order_obj_id' => $obj_id, //订单对象obj_id
                    'obj_type' => $obj['obj_type'],
                    'goods_id' => $obj['goods_id'],
                    'goods_bn' => $obj['bn'],
                    'goods_name' => $obj['name'],
                    'quantity' => $obj['quantity'], //发货数量
                    'price' => $obj['price'], //商品单价
                    'sale_price' => $obj['amount'], //商品销售总价(单价*数量)
                    'pmt_price' => $obj['pmt_price'], //商品总优惠金额(优惠额*数量)
                    'apportion_pmt' => $obj['part_mjz_discount'], //商品总优惠分摊金额
                    'sales_amount' => $obj['divide_order_fee'],
                    'settlement_amount' => sprintf('%.2f', (float) $obj['settlement_amount'] - $obj_refund_money),
                    'actually_amount' => sprintf('%.2f', (float) $obj['actually_amount'] - $obj_refund_money),
                    'platform_amount' => $obj['platform_amount'],
                    'platform_pay_amount' => $obj['platform_pay_amount'],
                    'refund_money' => sprintf('%.3f', $obj_refund_money),
                    //'cost' => $obj['cost'], //商品成本单价
                    //'cost_amount' => $obj['cost_amount'], //商品成本金额
                    //'cost_tax' => $obj['cost_tax'], //商品开票税率
                    //'iostock_id' => $obj['iostock_id'], //出入库单号
                    'oid' => $obj['oid'], //子订单号
            );
        }

        //补全销售明细中的平摊优惠，货品优惠，平摊优惠后的成交价
        $ome_sales_priceLib = kernel::single('ome_sales_price');
        if ($ome_sales_priceLib->calculate($original_data, $tmp_sales_data)) {
            return $tmp_sales_data;
        } else {
            return false;
        }
    }

    /**
     * 各 object 表 refund 之和若小于主单 refund，差额按 object 的 divide_order_fee 分摊到各 obj
     *
     * @param array $original_data
     * @return array obj_id => float
     */
    protected function _objectEffectiveRefundMoney(array $original_data)
    {
        $byObj = array();
        if (empty($original_data['order_objects']) || !is_array($original_data['order_objects'])) {
            return $byObj;
        }
        $totalStored = 0.0;
        foreach ($original_data['order_objects'] as $obj) {
            $oid = $obj['obj_id'];
            $v = isset($obj['refund_money']) ? (float) $obj['refund_money'] : 0.0;
            $byObj[$oid] = $v;
            $totalStored += $v;
        }
        $orderRefund = isset($original_data['refund_money']) ? (float) $original_data['refund_money'] : 0.0;
        $gap = round($orderRefund - $totalStored, 4);
        if ($gap <= 0.00001) {
            return $byObj;
        }
        $objs = array_values($original_data['order_objects']);
        $n = count($objs);
        if ($n < 1) {
            return $byObj;
        }
        $totalW = 0.0;
        foreach ($objs as $obj) {
            $totalW += isset($obj['divide_order_fee']) ? (float) $obj['divide_order_fee'] : 0.0;
        }
        if ($totalW <= 0) {
            if ($n === 1) {
                $byObj[$objs[0]['obj_id']] += $gap;
            } else {
                $each = round($gap / $n, 2);
                $used = 0.0;
                for ($i = 0; $i < $n - 1; $i++) {
                    $oid = $objs[$i]['obj_id'];
                    $byObj[$oid] += $each;
                    $used += $each;
                }
                $byObj[$objs[$n - 1]['obj_id']] += round($gap - $used, 2);
            }
            return $byObj;
        }
        $allocated = 0.0;
        foreach ($objs as $i => $obj) {
            $oid = $obj['obj_id'];
            $fee = isset($obj['divide_order_fee']) ? (float) $obj['divide_order_fee'] : 0.0;
            if ($i === $n - 1) {
                $byObj[$oid] += round($gap - $allocated, 2);
            } else {
                $add = round($gap * ($fee / $totalW), 2);
                $byObj[$oid] += $add;
                $allocated += $add;
            }
        }
        return $byObj;
    }

    /**
     * 每个 object：行 refund 之和小于该 object 有效退款时，非 pkg 按 divide_order_fee 摊分；pkg 按组合贡献比摊分，尾差记最后一行
     *
     * @param array $original_data
     * @param array $effectiveObjRefund obj_id => float
     * @param array $smBmRate sm_id => bm_id => rate
     * @return array item_id => float
     */
    protected function _effectiveRefundMoneyByOrderItems(array $original_data, array $effectiveObjRefund, array $smBmRate = array())
    {
        $out = array();
        if (empty($original_data['order_objects']) || !is_array($original_data['order_objects'])) {
            return $out;
        }
        foreach ($original_data['order_objects'] as $obj) {
            if (empty($obj['order_items']) || !is_array($obj['order_items'])) {
                continue;
            }
            $itemsById = array();
            foreach ($obj['order_items'] as $item) {
                $itemsById[$item['item_id']] = $item;
            }
            $objectRefund = isset($effectiveObjRefund[$obj['obj_id']]) ? (float) $effectiveObjRefund[$obj['obj_id']] : 0.0;
            $sumLine = 0.0;
            foreach ($itemsById as $it) {
                $sumLine += isset($it['refund_money']) ? (float) $it['refund_money'] : 0.0;
            }
            $local = array();
            foreach ($itemsById as $id => $it) {
                $local[$id] = isset($it['refund_money']) ? (float) $it['refund_money'] : 0.0;
            }
            $residual = round($objectRefund - $sumLine, 4);
            if ($residual > 0.00001) {
                if (isset($obj['obj_type']) && $obj['obj_type'] == 'pkg') {
                    $goodsId = isset($obj['goods_id']) ? $obj['goods_id'] : 0;
                    $alloc = $this->_allocatePkgRefundByContributionRatio($itemsById, $goodsId, $residual, $smBmRate);
                    foreach ($alloc as $id => $add) {
                        $local[$id] += $add;
                    }
                } else {
                    $totalW = 0.0;
                    foreach ($itemsById as $it) {
                        $totalW += isset($it['divide_order_fee']) ? (float) $it['divide_order_fee'] : 0.0;
                    }
                    $ids = array_keys($itemsById);
                    $n = count($ids);
                    if ($totalW <= 0) {
                        if ($n === 1) {
                            $local[$ids[0]] += $residual;
                        } else {
                            $each = round($residual / $n, 2);
                            $used = 0.0;
                            for ($i = 0; $i < $n - 1; $i++) {
                                $local[$ids[$i]] += $each;
                                $used += $each;
                            }
                            $local[$ids[$n - 1]] += round($residual - $used, 2);
                        }
                    } else {
                        $allocated = 0.0;
                        $lastIdx = $n - 1;
                        foreach ($ids as $i => $id) {
                            $fee = isset($itemsById[$id]['divide_order_fee']) ? (float) $itemsById[$id]['divide_order_fee'] : 0.0;
                            if ($i === $lastIdx) {
                                $local[$id] += round($residual - $allocated, 2);
                            } else {
                                $add = round($residual * ($fee / $totalW), 2);
                                $local[$id] += $add;
                                $allocated += $add;
                            }
                        }
                    }
                }
            }
            foreach ($local as $id => $v) {
                $out[$id] = $v;
            }
        }
        return $out;
    }

    /**
     * pkg 退款按组合价格贡献比分摊，尾差记最后一行 order_item
     *
     * @param array $itemsById item_id => order_item
     * @param int $goodsId 销售物料 sm_id
     * @param float $amount 待分摊金额
     * @param array $smBmRate sm_id => bm_id => rate
     * @return array item_id => float
     */
    protected function _allocatePkgRefundByContributionRatio(array $itemsById, $goodsId, $amount, array $smBmRate = array())
    {
        $amount = (float) $amount;
        if ($amount <= 0.00001) {
            return array();
        }
        $ids = array_keys($itemsById);
        $n = count($ids);
        if ($n < 1) {
            return array();
        }
        if ($n === 1) {
            return array($ids[0] => round($amount, 2));
        }

        $totalRate = 0.0;
        $rates = array();
        foreach ($ids as $id) {
            $it = $itemsById[$id];
            $rate = 0.0;
            $productId = isset($it['product_id']) ? $it['product_id'] : 0;
            if ($goodsId && $productId && isset($smBmRate[$goodsId][$productId])) {
                $rate = (float) $smBmRate[$goodsId][$productId];
            }
            $rates[$id] = $rate;
            $totalRate += $rate;
        }

        $lastId = $ids[$n - 1];
        $result = array();
        if ($totalRate <= 0.00001) {
            $result[$lastId] = round($amount, 2);
            return $result;
        }

        $allocated = 0.0;
        foreach ($ids as $id) {
            if ($id === $lastId) {
                $result[$id] = round($amount - $allocated, 2);
            } else {
                $add = round($amount * ($rates[$id] / $totalRate), 2);
                $result[$id] = $add;
                $allocated += $add;
            }
        }
        return $result;
    }

    private function _generate_basic($original_data, $delivery_id)
    {
        $deliveryObj     = app::get('ome')->model('delivery');
        $delivery_detail = $deliveryObj->dump(array('delivery_id' => $delivery_id), '*');

        $delivery_billObj    = app::get('ome')->model('delivery_bill');
        $delivery_bill_infos = $delivery_billObj->getList('delivery_cost_actual', array('delivery_id' => $delivery_id));

        //配送费用
        $tmp_sales_data['cost_freight'] = $original_data['shipping']['cost_shipping'];

        //预收物流费用
        $tmp_sales_data['delivery_cost'] = $original_data['shipping']['cost_shipping'];

        //发货时输入重量淘管预估物流费用
        $tmp_sales_data['delivery_cost_actual'] = $delivery_detail['delivery_cost_actual'];

        //追加多包裹单的物流费用
        if ($delivery_bill_infos) {
            foreach ($delivery_bill_infos as $k => $delivery_bill_info) {
                $tmp_sales_data['delivery_cost_actual'] += $delivery_bill_info['delivery_cost_actual'];
            }
        }

        // 代销人ID
        $tmp_sales_data['selling_agent_id'] = $original_data['selling_agent_id'];

        //附加费：保价费+税金+支付费用
        $tmp_sales_data['additional_costs'] = $original_data['shipping']['cost_protect'] + $original_data['cost_tax'] + $original_data['payinfo']['cost_payment'];

        //追加订单手工加价
        if ($original_data['discount'] > 0) {
            $tmp_sales_data['additional_costs'] += $original_data['discount'];
        }

        //预付款:所有为预付款支付方式的支付单总额
        $sql                       = 'SELECT sum(money) AS deposit FROM `sdb_ome_payments` WHERE pay_type=\'deposit\' AND order_id=\'' . $original_data['order_id'] . '\'';
        $payments                  = $deliveryObj->db->selectrow($sql);
        $tmp_sales_data['deposit'] = $payments['deposit'] ? $payments['deposit'] : 0.00;

        //订单折扣费用:订单促销优惠+订单折扣+商品促销优惠
        $tmp_sales_data['discount'] = $original_data['pmt_goods'] + $original_data['pmt_order'];
        if ($original_data['discount'] < 0) {
            $tmp_sales_data['discount'] += abs($original_data['discount']);
        }

        $tmp_sales_data['member_id']    = $original_data['member_id'];
        $tmp_sales_data['shop_id']      = $original_data['shop_id'];
        $tmp_sales_data['total_amount'] = $original_data['cost_item']; //商品金额

        $tmp_sales_data['payment']           = $original_data['payinfo']['pay_name']; //支付方式
        $tmp_sales_data['order_check_id']    = $original_data['op_id'];
        $tmp_sales_data['order_create_time'] = $original_data['createtime'];
        $tmp_sales_data['paytime']           = $original_data['paytime'];
        $tmp_sales_data['is_tax']            = $original_data['is_tax'];
        $tmp_sales_data['sale_amount']       = $original_data['total_amount']; //销售金额
        $tmp_sales_data['service_price']     = $original_data['service_price']; //服务订单费
        $tmp_sales_data['platform_service_fee']     = $original_data['platform_service_fee']; //平台服务费用
        $tmp_sales_data['refund_money']      = $original_data['refund_money']; //退款金额
        $tmp_sales_data['memo']              = '';
        $tmp_sales_data['order_id']          = $original_data['order_id'];
        $tmp_sales_data['order_bn']          = $original_data['order_bn'];
        $tmp_sales_data['branch_id']         = $delivery_detail['branch_id'];
        $tmp_sales_data['pay_status']        = 1;
        $tmp_sales_data['payed']             = $original_data['payed'];
        $operator                            = kernel::single('desktop_user')->get_name();
        $tmp_sales_data['operator']          = $operator ? $operator : 'system';
        $tmp_sales_data['sale_time']         = time();
        $tmp_sales_data['shopping_guide']    = '';
        $tmp_sales_data['logi_id']           = $delivery_detail['logi_id'];
        $tmp_sales_data['logi_name']         = $delivery_detail['logi_name'];
        $tmp_sales_data['logi_no']           = $delivery_detail['logi_no'];
        $tmp_sales_data['delivery_id']       = $delivery_id;
        $tmp_sales_data['order_check_time']  = $delivery_detail['create_time'];
        $tmp_sales_data['ship_time']         = $delivery_detail['delivery_time'] ? $delivery_detail['delivery_time'] : time();
        $tmp_sales_data['shop_type']         = $original_data['shop_type'];
        $tmp_sales_data['org_id']            = $original_data['org_id'];
        $tmp_sales_data['order_type'] = $original_data['order_type'];
        $tmp_sales_data['platform_order_bn'] = $original_data['platform_order_bn'];
        $tmp_sales_data['order_source']      = $original_data['order_source'];

        //权限相关
        $tmp_sales_data['betc_id'] = isset($original_data['betc_id']) ? intval($original_data['betc_id']) : 0; //贸易公司ID
        $tmp_sales_data['cos_id'] = isset($original_data['cos_id']) ? intval($original_data['cos_id']) : 0; //组织架构ID
        
        return $tmp_sales_data;
    }

    /**
     *  校验销售单价格是否平
     * @param  
     * @return array
     */
    public function proofSales($sale_id){

        $db = kernel::database();
        $items = $db->selectrow("SELECT sum(sales_amount) as item_sales_amount FROM sdb_ome_sales_items WHERE sale_id='".$sale_id."'");
        $sales = $db->selectrow("SELECT (sale_amount-cost_freight) as sale_amount FROM sdb_ome_sales WHERE sale_id='".$sale_id."'");

        if(0 != bccomp($items['item_sales_amount'], $sales['sale_amount'],3)){

            $check_msg = "明细金额:".$items['item_sales_amount'].",订单总金额:".$sales['sale_amount'];
            $db->exec("UPDATE sdb_ome_sales  SET `check`='true',check_msg='".$check_msg."' WHERE sale_id=".$sale_id."");
        }

    }
}
