<?php
/**
 * 订单金额计算类
 */
class ome_order_object_amount {
    
    /**
     * 重新计算订单金额（在SQL中实现减法）
     * @param int $order_id 订单ID
     * @param array $orderObjectsData 已查询好的order_object数据
     * @param bool $isSubGoodsPrice 是否减去商品优惠
     * @return array [bool, array]
     */
    public function recalculateOrderAmount($order_id, $orderObjectsData = array(), $isSubGoodsPrice = true)
    {
        try {
            if (empty($orderObjectsData)) {
                return [true, ['msg' => '没有需要删除的订单子单数据']];
            }
            
            // 在PHP中计算需要减去的金额
            $subtract_amount = 0;
            $subtract_divide_order_fee = 0;
            $subtract_pmt_goods = 0;
            $subtract_pmt_order = 0;
            $subtract_settlement_amount = 0;
            $subtract_actually_amount = 0;
            
            foreach ($orderObjectsData as $objData) {
                $amount = floatval($objData['amount']);
                $divide_order_fee = floatval($objData['divide_order_fee']);
                $pmt_price = floatval($objData['pmt_price']);
                $part_mjz_discount = floatval($objData['part_mjz_discount']);
                $settlement_amount = floatval($objData['settlement_amount']);
                $actually_amount = floatval($objData['actually_amount']);
                
                // 累加需要减去的金额（在PHP中计算）
                $subtract_amount += $amount;
                $subtract_divide_order_fee += $divide_order_fee;
                $subtract_pmt_goods += $pmt_price;
                $subtract_pmt_order += $part_mjz_discount;
                $subtract_settlement_amount += $settlement_amount;
                $subtract_actually_amount += $actually_amount;
            }
            
            // 使用SQL直接更新订单金额（使用PHP计算的结果）
            $order_sql = "UPDATE sdb_ome_orders SET ";
            $order_sql .= "cost_item = cost_item - " . $subtract_amount . ", ";
            if ($isSubGoodsPrice) {
                $order_sql .= "pmt_goods = pmt_goods - " . $subtract_pmt_goods . ", ";
            }
            $order_sql .= "pmt_order = pmt_order - " . $subtract_pmt_order . ", ";
            $order_sql .= "total_amount = total_amount - " . $subtract_divide_order_fee . ", ";
            $order_sql .= "final_amount = final_amount - " . $subtract_divide_order_fee . ", ";
            $order_sql .= "settlement_amount = settlement_amount - " . $subtract_settlement_amount . ", ";
            $order_sql .= "actually_amount = actually_amount - " . $subtract_actually_amount . " ";
            $order_sql .= "WHERE order_id = " . intval($order_id);
            
            $result = kernel::database()->exec($order_sql);
            
            if ($result === false) {
                return [false, ['msg' => '更新订单金额失败：' . kernel::database()->errorinfo()]];
            }
            
            return [true, ['msg' => '订单金额重新计算成功']];
            
        } catch (Exception $e) {
            return [false, ['msg' => '重新计算订单金额失败：' . $e->getMessage()]];
        }
    }
}
