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
 * @Author: xueding@shopex.cn
 * @Datetime: 2022/3/19
 * @Describe: 订单优惠明细处理类
 */
class ome_order_coupon
{
    /**
     * 重组优惠明细数据
     * @param $data 优惠明细数据
     * @param array $ext_data 扩展数据
     * @param string $shop_type 店铺类型
     * @return array
     */

    public function couponDataFormat($data, $ext_data = array(), $shop_type = '360buy')
    {
        switch ($shop_type) {
            case '360buy' :
                $rs = $this->_JdCouponDataFormat($data, $ext_data);
                break;
            default :
                $rs = array('rsp' => 'fail', 'msg' => '没有单据类型');
        }
        return $rs;
    }
    
    public function _JdCouponDataFormat($data, $ext_data)
    {
        $orderObjects     = array_column($ext_data['order_objects'], null, 'oid');
        $coupon           = array();
        $objectCouponData = array();
        if ($data && is_array($data['skuList'])) {
            $skuList              = $data['skuList'];
            $venderFee            = 0;
            $count                = count($skuList);
            $i                    = 1;
            //整单平台优惠
            $totalPlatformDiscounts = $data['totalPingTaiChengDanYouHuiQuan'] + $data['totalJingQuan'] + $data['totalDongQuan'] +
                $data['totalXianPinLeiJingQuan'] + $data['totalXianPinLeiDongQuan'] + $data['totalJingDou'] + $data['totalSuperRedEnvelope'];
            //整单平台支付优惠
            $totalPlatformPayDiscounts = $data['totalLiJinYouHui'] + $data['totalZhiFuYingXiaoYouHui'] + $data['totalJdZhiFuYouHui'];
            //整单平台付款总价
            $taotalPlatformTotalPrice = $totalPlatformDiscounts + $totalPlatformPayDiscounts;
            //根据店铺取运营组织
            $shopInfo = kernel::single('ome_shop')->getRowByShopId($ext_data['shop_id']);
            $orgId = $shopInfo['org_id'];
            $divideOrderFeeZero = [];
    
            //整单实付金额
            $totalDivideOrderFee = $data['totalShouldPay'] + $data['totalBalance'] - $data['totalLiJinYouHui'] - $data['totalZhiFuYingXiaoYouHui'] -
                $data['totalJdZhiFuYouHui'] - $data['totalVenderFee'] -  $data['totalBaseFee'] - $data['totalRemoteFee'] -
                $data['totalTuiHuanHuoWuYou'] - $data['totalTaxFee'] - $data['totalLuoDiPeiService'] -
                $data['totalGlobalGeneralTax'] - $data['totalGlobalGeneralIncludeTax'];
            $divideOrderFeeAmount = $totalDivideOrderFee;
            
            foreach ($skuList as $key => $value) {
                //实付金额
                $divideOrderFee = $value['shouldPay'] + $value['balance'] - $value['liJinYouHui'] - $value['zhiFuYingXiaoYouHui'] -
                    $value['jdZhiFuYouHui'] - $value['venderFee'] - $value['baseFee'] - $value['remoteFee'] -
                    $value['tuiHuanHuoWuYou'] - $value['taxFee'] - $value['luoDiPeiService'] -
                    $value['globalGeneralTax'] - $value['globalGeneralIncludeTax'];
                
                if ($divideOrderFee < 0) {
                    $divideOrderFee = $value['shouldPay'] + $value['balance'] - $value['liJinYouHui'] - $value['zhiFuYingXiaoYouHui'] -
                        $value['jdZhiFuYouHui'] - $value['baseFee'] - $value['remoteFee'] -
                        $value['tuiHuanHuoWuYou'] - $value['taxFee'] - $value['luoDiPeiService'] -
                        $value['globalGeneralTax'] - $value['globalGeneralIncludeTax'];
                    $venderFee      += $value['venderFee'] * $value['count'];
                }
                
                //优惠分摊
                $partMjzDiscount = $value['skuPrice'] - $value['baseDiscount'] + $value['tuiHuanHuoWuYou'] +
                    $value['taxFee'] + $value['luoDiPeiService'] + $value['globalGeneralTax'] + $value['globalGeneralIncludeTax'] -
                    $divideOrderFee;
                //商家优惠
                $merchantDiscounts = $value['manJian'] + $value['coupon'] - $value['jingQuan'] - $value['dongQuan'] -
                    $value['xianPinLeiJingQuan'] - $value['xianPinLeiDongQuan'] + $value['plus95'] - $value['pingTaiChengDanYouHuiQuan'];
                //平台优惠
                $platformDiscounts = $value['pingTaiChengDanYouHuiQuan'] + $value['jingQuan'] + $value['dongQuan'] +
                    $value['xianPinLeiJingQuan'] + $value['xianPinLeiDongQuan'] + $value['jingDou'] + $value['superRedEnvelope'];
                //平台支付优惠
                $platformPayDiscounts = $value['liJinYouHui'] + $value['zhiFuYingXiaoYouHui'] + $value['jdZhiFuYouHui'];
                //其他
                $otherPrice = $value['tuiHuanHuoWuYou'] + $value['taxFee'] + $value['luoDiPeiService'] + $value['globalGeneralTax'] + $value['globalGeneralIncludeTax'];
                //平台付款总价
                $platformTotalPrice = $platformDiscounts + $platformPayDiscounts;
                //实付为0 的oid
                if ($divideOrderFee <= 0) {
                    $divideOrderFeeZero[] = $value['skuCode'];
                }
                //明细实付
                $priceList[$value['skuCode'].'-'.$value['count']][] = [
                    'divide_order_fee' => ($divideOrderFee > 0 ? $divideOrderFee : 0) * $value['count'] + ($platformTotalPrice > 0 ? $platformTotalPrice : 0) * $value['count'],
                    'part_mjz_discount' => ($partMjzDiscount >0 ? $partMjzDiscount : 0) * $value['count'],
                    'count' => $value['count'],
                    'cost_freight' => $value['venderFee'] * $value['count'],
                ];
                
                //平台优惠类型
                $platformCouponField = [
                    'superRedEnvelope',
                    'jingQuan',
                    'dongQuan',
                    'jingDou',
                    'xianPinLeiJingQuan',
                    'xianPinLeiDongQuan',
                    'pingTaiChengDanYouHuiQuan',
                ];
                //平台支付优惠
                $platformPayCouponField = [
                    'liJinYouHui',
                    'zhiFuYingXiaoYouHui',
                    'jdZhiFuYouHui',
                    'jingXiangLiJin',
                    'globalGeneralTax',
                    'globalGeneralIncludeTax',
                ];
                //商家优惠类型
                $MerchantCouponField = [
                    'manJian',
                    'plus95'
                ];
                $typeName            = [
                    'skuPrice'                  => '单品金额',
                    'baseDiscount'              => '基础优惠',
                    'manJian'                   => '满减',
                    'venderFee'                 => '商家运费',
                    'baseFee'                   => '基础运费',
                    'remoteFee'                 => '偏远运费',
                    'coupon'                    => '优惠券',
                    'jingDou'                   => '京豆',
                    'balance'                   => '余额',
                    'plus95'                    => 'plus会员95折优惠',
                    'tuiHuanHuoWuYou'           => '退换货无忧',
                    'taxFee'                    => '全球购税费',
                    'luoDiPeiService'           => '落地配服务',
                    'shouldPay'                 => '应付金额',
                    'superRedEnvelope'          => '超级红包',
                    'jingQuan'                  => '京券',
                    'dongQuan'                  => '东券',
                    'xianPinLeiJingQuan'        => '限品类京券',
                    'xianPinLeiDongQuan'        => '限品类东券',
                    'pingTaiChengDanYouHuiQuan' => '按比例平台承担优惠券',
                    'liJinYouHui'               => '礼金优惠',
                    'zhiFuYingXiaoYouHui'       => '支付营销优惠',
                    'jdZhiFuYouHui'             => '京东支付优惠',
                    'jingXiangLiJin'            => '京享礼金(首单礼金或重逢礼金)',
                    'globalGeneralTax'          => '全球购一般贸易税',
                    'globalGeneralIncludeTax'   => '全球购一般贸易税(包税)',
                    'singleProductDirectDiscount' => '单品直降促销',
                    'promotionDiscount' => '跨店满减促销',
                ];
                //优惠明细
                foreach ($value as $k => $v) {
                    if ($v <= 0 || $k == 'count' || $k == 'skuName' || $k == 'skuCode' || is_array($v)) {
                        continue;
                    }
                    //优惠类型
                    $coupon_type = '0';
                    if (in_array($k, $platformCouponField)) {
                        $coupon_type = '1';
                    } elseif (in_array($k, $MerchantCouponField)) {
                        $coupon_type = '2';
                    } elseif (in_array($k, $platformPayCouponField)) {
                        $coupon_type = '3';
                    }
                    $coupon[] = array(
                        'num'           => $value['count'],
                        'material_bn'   => $orderObjects[$value['skuCode']]['bn'],
                        'oid'           => $value['skuCode'],
                        'material_name' => $value['skuName'],
                        'type'          => $k,
                        'type_name'     => $typeName[$k],
                        'coupon_type'   => $coupon_type,
                        'amount'        => $v,
                        'total_amount'  => $v * $value['count'],
                        'create_time'   => sprintf('%.0f', time()),
                        'pay_time'      => kernel::single('ome_func')->date2time($ext_data['payment_detail']['pay_time']),
                        'shop_type'     => $ext_data['shop_type'],
                        'source'        => $ext_data['coupon_source'],
                    );
                }
                
                //扩展字段
                $extendField     = [
                    'calcActuallyPay'          => $divideOrderFee,//实付金额
                    'calcPartMjzDiscount'      => $partMjzDiscount,//优惠分摊
                    'calcMerchantDiscounts'    => $merchantDiscounts,//商家优惠
                    'calcPlatformDiscounts'    => $platformDiscounts,//平台优惠
                    'calcPlatformPayDiscounts' => $platformPayDiscounts,//平台支付优惠
                    'calcOtherPrice'           => $otherPrice,//其他
                    'calcPlatformTotalPrice'   => $platformTotalPrice,//平台付款总价
                ];
                $extendFieldName = [
                    'calcActuallyPay'          => '实付金额',//实付金额
                    'calcPartMjzDiscount'      => '优惠分摊',//优惠分摊
                    'calcMerchantDiscounts'    => '商家优惠',//商家优惠
                    'calcPlatformDiscounts'    => '平台优惠',//平台优惠
                    'calcPlatformPayDiscounts' => '平台支付优惠',//平台支付优惠
                    'calcOtherPrice'           => '其他',//其他
                    'calcPlatformTotalPrice'   => '平台付款总价',//平台付款总价
                ];
                
                foreach ($extendField as $field => $calcPrice) {
                    if ($calcPrice <= 0) {
                        continue;
                    }
                    $total_amount = $calcPrice * $value['count'];
                    if ($count == $i) {
                        if ($field == 'calcPlatformDiscounts') {
                            $total_amount = $totalPlatformDiscounts;
                        } elseif ($field == 'calcPlatformPayDiscounts') {
                            $total_amount = $totalPlatformPayDiscounts;
                        } elseif ($field == 'calcPlatformTotalPrice') {
                            $total_amount = $taotalPlatformTotalPrice;
                        }
                    } else {
                        if ($field == 'calcPlatformDiscounts') {
                            $totalPlatformDiscounts = $totalPlatformDiscounts - $total_amount;
                        } elseif ($field == 'calcPlatformPayDiscounts') {
                            $totalPlatformPayDiscounts = $totalPlatformPayDiscounts - $total_amount;
                        } elseif ($field == 'calcPlatformTotalPrice') {
                            $taotalPlatformTotalPrice = $taotalPlatformTotalPrice - $total_amount;
                        }
                    }
                    $extendData = [
                        'num'           => $value['count'],
                        'material_bn'   => $orderObjects[$value['skuCode']]['bn'],
                        'oid'           => $value['skuCode'],
                        'material_name' => $value['skuName'],
                        'type'          => $field,
                        'type_name'     => $extendFieldName[$field],
                        'coupon_type'   => '0',
                        'amount'        => $calcPrice > 0 ? $calcPrice : 0,
                        'total_amount'  => $total_amount,
                        'create_time'   => sprintf('%.0f', time()),
                        'pay_time'      => kernel::single('ome_func')->date2time($ext_data['payment_detail']['pay_time']),
                        'shop_type'     => $ext_data['shop_type'],
                        'source'        => $ext_data['coupon_source'],
                    ];
                    array_push($coupon, $extendData);
                }
                //优惠明细汇总
                $objectCouponData[] = array(
                    'order_bn'      => $ext_data['order_bn'],
                    'num'           => $value['count'],
                    'material_name' => $value['skuName'],
                    'material_bn'   => $orderObjects[$value['skuCode']]['bn'],
                    'oid'           => $value['skuCode'],
                    'create_time'   => sprintf('%.0f', time()),
                    'shop_id'       => $ext_data['shop_id'],
                    'shop_type'     => $ext_data['shop_type'],
                    'source'        => $ext_data['coupon_source'],
                    'addon'         => serialize(array_merge($extendField, $value)),
                    'org_id'        => $orgId,
                );
                $i++;
            }
        }
        $res['coupon_data']            = $coupon;
        $res['divide_order_fee_mount'] = $divideOrderFeeAmount;
        $res['vender_fee_amount']      = $venderFee;
        $res['price_list']             = $priceList;
        $res['objects_coupon_data']    = $objectCouponData;
        $res['divide_order_fee_zero']  = $divideOrderFeeZero;
        return $res;
    }

    /**
     * couponFromPlatformDiscount
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function couponFromPlatformDiscount($data)
    {
        return $this->_PddCouponDataFormat($data);
    }

    /**
     * _PddCouponDataFormat
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function _PddCouponDataFormat($data = [])
    {
        $coupon = [];
        if (!$data['platform_cost_amount']){
            return $coupon;
        }
        $saleMaterialList = [];
        $cost_item = $data['cost_item'];
        $_tmp_amount = 0;
        $_tmp_num = 1;
        foreach ($data['order_objects'] as $_k => $_orderObject) {
            $_tmp = [
                'oid'       =>  $_orderObject['oid'],
                'goods_id'  =>  $_orderObject['goods_id'],
                'bn'        =>  $_orderObject['bn'],
                'name'      =>  $_orderObject['name'],
                'price'     =>  $_orderObject['price'],
                'quantity'  =>  $_orderObject['quantity'],
                'amount'    =>  $_orderObject['amount'],
            ];
            if ($_tmp_num == count($data['order_objects'])){
                $_tmp['platform_discount'] = $data['platform_cost_amount'] - $_tmp_amount;
            } else {
                $_tmp['platform_discount'] = sprintf('%.3f', $data['platform_cost_amount'] * ($_tmp['amount'] / $cost_item));
                $_tmp_amount += $_tmp['platform_discount'];
            }
            $saleMaterialList[] = $_tmp;
            $_tmp_num ++;
        }

        foreach ($saleMaterialList as $_saleMaterial) {
            $coupon['coupon_data'][] = [
                'num'           => $_saleMaterial['quantity'],
                'material_bn'   => $_saleMaterial['bn'],
                'oid'           => $_saleMaterial['oid'],
                'material_name' => $_saleMaterial['name'],
                'type'          => 'platform_cost_amount',
                'type_name'     => '平台优惠金额',
                'coupon_type'   => '1',
                'amount'        => $_saleMaterial['platform_discount'] / $_saleMaterial['quantity'],
                'total_amount'  => $_saleMaterial['platform_discount'],
                'create_time'   => sprintf('%.0f', time()),
                'pay_time'      => $data['paytime'],
                'shop_type'     => $data['shop_type'],
                'source'        => 'rpc',
            ];
        }
        return $coupon;
    }
    
    /**
     * 查询订单优惠数据format
     * @param $order_id
     * @param $list
     * @param string $index
     * @return array|mixed|null
     */
    public function getOrderCoupon($order_id, $list = array())
    {
        static $newData;
        
        if (isset($newData)) {
            if ($order_id == 0) {
                return $newData;
            }
            if (isset($newData[$order_id])) {
                return $newData[$order_id];
            }
        }
        $filter['order_id'] = array_column($list, 'order_id');
        
        if (empty($list)) {
            $filter['order_id'] = $order_id;
        }
        $orderCouponDetailList = app::get('ome')->model('order_coupon')->getList('order_id,material_bn,type,num,amount,oid,source,total_amount,shop_type',
            $filter);
        
        $orderDetailList = app::get('ome')->model('orders')->getList('order_id,order_bn,shop_id', $filter);
        $orderDetail     = array_column($orderDetailList, null, 'order_id');
        $shopId          = array_unique(array_column($orderDetailList, 'shop_id'));
        $shopDetailList  = app::get('ome')->model('shop')->getList('shop_id,name', array('shop_id' => $shopId));
        $shopDetail      = array_column($shopDetailList, null, 'shop_id');
        
        foreach ($orderCouponDetailList as $key => $value) {
            $newData[$value['order_id']][$value['oid']]['order_id']                     = $value['order_id'];
            $newData[$value['order_id']][$value['oid']]['material_bn']                  = $value['material_bn'];
            $newData[$value['order_id']][$value['oid']][$value['type']]                 = $value['amount'];
            $newData[$value['order_id']][$value['oid']][$value['type'] . 'TotalAmount'] = $value['total_amount'];
            $newData[$value['order_id']][$value['oid']]['num']                          = $value['num'];
            $newData[$value['order_id']][$value['oid']]['order_bn']                     = $orderDetail[$value['order_id']]['order_bn'];
            $newData[$value['order_id']][$value['oid']]['shop_name']                    = $shopDetail[$orderDetail[$value['order_id']]['shop_id']]['name'];
            $newData[$value['order_id']][$value['oid']]['source']                       = $value['source'];
            $newData[$value['order_id']][$value['oid']]['shop_type']                    = $value['shop_type'];
        }
        if ($order_id == 0) {
            return $newData;
        }
        
        return $newData[$order_id];
    }
    
    public function getOrderItemCouponDetail($order_id, $list = array())
    {
        if (!$list) {
            $list = [['order_id' => $order_id]];
        }
        $orderCouponList = $this->getOrderCoupon($order_id, $list);
        if (!$orderCouponList) {
            return array();
        }
        $ordersModel = app::get('ome')->model('orders');
        $order       = $ordersModel->dump($order_id, '*',
            array('order_objects' => array('*', array('order_items' => array('*')))));
        
        $objects = $order['order_objects'];
        if(empty($order['order_objects'])) {
            return array();
        }
        
        // todo.XueDing:共用占比方法app/ome/lib/order.php->calculate_part_porth
        //查询捆绑占比
        $smIds    = array_column($objects, 'goods_id');
        $smBc     = app::get('material')->model('sales_basic_material')->getList('sm_id, bm_id, rate',
            array('sm_id' => $smIds));
        $smBmRate = array();
        foreach ($smBc as $v) {
            $smBmRate[$v['sm_id']][$v['bm_id']] = $v['rate'];
        }
        $newdata = array();
        foreach ($objects as $key => $value) {
            if ($value['delete'] == 'true' || empty($value['order_items'])) {
                continue;
            }
            $countItem            = count($value['order_items']);
            $i                    = 1;
            $couponRow            = $orderCouponList[$value['oid']];
            $actuallyPay          = $couponRow['calcActuallyPay'] * $couponRow['num'];
            $partMjzDiscount      = $couponRow['calcPartMjzDiscount'] * $couponRow['num'];
            $merchantDiscounts    = $couponRow['calcMerchantDiscounts'] * $couponRow['num'];
            $platformDiscounts    = $couponRow['calcPlatformDiscounts'] * $couponRow['num'];
            $platformPayDiscounts = $couponRow['calcPlatformPayDiscounts'] * $couponRow['num'];
            $otherPrice           = $couponRow['calcOtherPrice'] * $couponRow['num'];
            $platformTotalPrice   = $couponRow['calcPlatformTotalPrice'] * $couponRow['num'];
            $skuNum               = $couponRow['num'];
            $actuallyAmount       = $merchantDiscountsAmount = $platformDiscountsAmount = $platformPayDiscountsAmounts = $otherPriceAmounts = $platformTotalPriceAmounts = $partMjzDiscountAmount = 0;
            foreach ($value['order_items'] as $k => $item) {
                if ($item['item_type'] == 'pkg') {
                    $rate = isset($smBmRate[$value['goods_id']][$item['product_id']]) ? $smBmRate[$value['goods_id']][$item['product_id']] : 0;
                    if ($i < $countItem) {
                        $itemActuallyPay = bcmul($rate, $actuallyPay, 2);
                        $actuallyAmount  = bcadd($actuallyAmount, $itemActuallyPay, 2);
                        
                        $itemCalcPartMjzDiscount = bcmul($rate, $partMjzDiscount, 2);
                        $partMjzDiscountAmount   = bcadd($partMjzDiscountAmount, $itemCalcPartMjzDiscount, 2);
                        
                        $itemMerchantDiscounts   = bcmul($rate, $merchantDiscounts, 2);
                        $merchantDiscountsAmount = bcadd($merchantDiscountsAmount, $itemMerchantDiscounts, 2);
                        
                        $itemPlatformDiscounts   = bcmul($rate, $platformDiscounts, 2);
                        $platformDiscountsAmount = bcadd($platformDiscountsAmount, $itemPlatformDiscounts, 2);
                        
                        $itemPlatformPayDiscounts    = bcmul($rate, $platformPayDiscounts, 2);
                        $platformPayDiscountsAmounts = bcadd($platformPayDiscountsAmounts, $itemPlatformPayDiscounts, 2);
                        
                        $itemOtherPrice    = bcmul($rate, $otherPrice, 2);
                        $otherPriceAmounts = bcadd($otherPriceAmounts, $itemOtherPrice, 2);
                        
                        $itemPlatformTotalPrice    = bcmul($rate, $platformTotalPrice, 2);
                        $platformTotalPriceAmounts = bcadd($platformTotalPriceAmounts, $itemPlatformTotalPrice, 2);
                        $i++;
                    } else {
                        $itemActuallyPay          = bcsub($actuallyPay, $actuallyAmount, 2);
                        $itemCalcPartMjzDiscount  = bcsub($partMjzDiscount, $partMjzDiscountAmount, 2);
                        $itemMerchantDiscounts    = bcsub($merchantDiscounts, $merchantDiscountsAmount, 2);
                        $itemPlatformDiscounts    = bcsub($platformDiscounts, $platformDiscountsAmount, 2);
                        $itemPlatformPayDiscounts = bcsub($platformPayDiscounts, $platformPayDiscountsAmounts, 2);
                        $itemOtherPrice           = bcsub($otherPrice, $otherPriceAmounts, 2);
                        $itemPlatformTotalPrice   = bcsub($platformTotalPrice, $platformTotalPriceAmounts, 2);
                    }
                    $item['calcActuallyPay']          = $itemActuallyPay > 0 ? bcdiv($itemActuallyPay, $skuNum, 2) : 0;
                    $item['calcPartMjzDiscount']      = $itemCalcPartMjzDiscount > 0 ? bcdiv($itemCalcPartMjzDiscount, $skuNum, 2) : 0;
                    $item['calcMerchantDiscounts']    = $itemMerchantDiscounts > 0 ? bcdiv($itemMerchantDiscounts, $skuNum, 2) : 0;
                    $item['calcPlatformDiscounts']    = $itemPlatformDiscounts > 0 ? bcdiv($itemPlatformDiscounts, $skuNum, 2) : 0;
                    $item['calcPlatformPayDiscounts'] = $itemPlatformPayDiscounts > 0 ? bcdiv($itemPlatformPayDiscounts, $skuNum, 2) : 0;
                    $item['calcOtherPrice']           = $itemOtherPrice > 0 ? bcdiv($itemOtherPrice, $skuNum, 2) : 0;
                    $item['calcPlatformTotalPrice']   = $itemPlatformTotalPrice > 0 ? bcdiv($itemPlatformTotalPrice, $skuNum, 2) : 0;
                    $item['platform']                 = '京东';
                    $item['source']                   = $couponRow['source'];
                    $newdata[$item['bn']]             = $item;
                } else {
                    if ($item['item_type'] == 'product') {
                        $item['calcActuallyPay']                     = $actuallyPay > 0 ? bcdiv($actuallyPay, $skuNum, 2) : 0;//实付金额
                        $item['calcPartMjzDiscount']                 = $partMjzDiscount > 0 ? bcdiv($partMjzDiscount, $skuNum, 2) : 0;//优惠分摊
                        $item['calcMerchantDiscounts']               = $merchantDiscounts > 0 ? bcdiv($merchantDiscounts, $skuNum, 2) : 0;//商家优惠
                        $item['calcPlatformDiscounts']               = $platformDiscounts > 0 ? bcdiv($platformDiscounts, $skuNum, 2) : 0;//平台优惠
                        $item['calcPlatformPayDiscounts']            = $platformPayDiscounts > 0 ? bcdiv($platformPayDiscounts, $skuNum, 2) : 0;//平台支付优惠
                        $item['calcOtherPrice']                      = $otherPrice > 0 ? bcdiv($otherPrice, $skuNum, 2) : 0;//其他
                        $item['calcPlatformTotalPrice']              = $platformTotalPrice > 0 ? bcdiv($platformTotalPrice, $skuNum, 2) : 0;//平台付款总价
                        $item['platform']                            = '京东';//支付平台方
                        $item['source']                              = $couponRow['source'];
                        $item['calcPlatformDiscountsTotalAmount']    = $couponRow['calcPlatformDiscountsTotalAmount'];
                        $item['calcPlatformPayDiscountsTotalAmount'] = $couponRow['calcPlatformPayDiscountsTotalAmount'];
                        $item['calcPlatformTotalPriceTotalAmount']   = $couponRow['calcPlatformTotalPriceTotalAmount'];
                        $newdata[$item['bn']]                        = $item;
                    }
                }
            }
        }
        return $newdata;
    }
}