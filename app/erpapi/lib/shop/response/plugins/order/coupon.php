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
 * 订单优惠明细数据
 *
 * @access public
 * @author xueding<xueding@shopex.cn>
 * @date  2021-07-01
 */
class erpapi_shop_response_plugins_order_coupon extends erpapi_shop_response_plugins_order_abstract
{
    /**
     * convert
     * @param erpapi_shop_response_abstract $platform platform
     * @return mixed 返回值
     */

    public function convert(erpapi_shop_response_abstract $platform)
    {
        // 更新的时候
        if ($platform->_tgOrder) {
            if(app::get('ome')->model('order_coupon')->db_dump(array('order_id'=>$platform->_tgOrder['order_id']), 'id')){
                return array();
            }
        }
        $coupon       = array();
        if (isset($platform->_ordersdf['coupon_data']) && !empty($platform->_ordersdf['coupon_data'])) {
            $coupon['coupon'] = $platform->_ordersdf['coupon_data'];
        }

        if (isset($platform->_ordersdf['objects_coupon_data']) && !empty($platform->_ordersdf['objects_coupon_data'])) {
            $coupon['objects_coupon_data'] = $platform->_ordersdf['objects_coupon_data'];
        }
        if(!$coupon && $platform->_ordersdf['coupon_oid_field'] && is_array($platform->_ordersdf['coupon_field'])){
            $couponData = $platform->_ordersdf['coupon_field'];
            $couponItems = $objects_coupon_data = [];
            foreach ($couponData as $key => $value)
            {
                $oid = $value[$platform->_ordersdf['coupon_oid_field']];
                $objects_coupon_data[] = array(
                    'order_bn'      => $platform->_ordersdf['order_bn'],
                    'num'           => $value['num'],
                    'material_name' => $value['name'],
                    'material_bn'   => $value['bn'],
                    'oid'           => $oid,
                    'create_time'   => sprintf('%.0f', time()),
                    'shop_id'       => $platform->__channelObj->channel['shop_id'],
                    'shop_type'     => $platform->__channelObj->channel['shop_type'],
                    'org_id'        => $platform->__channelObj->channel['org_id'],
                    'addon'         => serialize($value),
                    'source'        => 'push'
                );
                $pmt_info = $value['pmt_info'];
                $addType = ['calcActuallyPay'=>'实付金额'];
                foreach($addType as $k=>$v){
                    if($value[$k]){
                        $pmt_info[] = [
                            'coupon_type' => '0',
                            'pmt_type'    => $k,
                            'pmt_name'    => $v,
                            'pmt_amount'  => $value[$k],
                        ];
                    }
                }
                foreach ($pmt_info as $k => $v)
                {
                    if ($v['pmt_amount'] <= 0) {
                        continue;
                    }
                    $couponItems[] = array(
                        'num'           => $value['num'],
                        'oid'           => $oid,
                        'material_bn'   => $value['bn'],
                        'material_name' => $value['name'],
                        'coupon_type'   => $v['coupon_type'],
                        'type'          => $v['pmt_type'],
                        'type_name'     => $v['pmt_name'],
                        'amount'        => sprintf('%.3f', $v['pmt_amount'] / $value['num']),
                        'total_amount'  => $v['pmt_amount'],
                        'create_time'   => sprintf('%.0f', time()),
                        'pay_time'      => !empty($platform->_newOrder['paytime']) ? kernel::single('ome_func')->date2time($platform->_newOrder['paytime']) : null,
                        'shop_type'     => $platform->__channelObj->channel['shop_type'],
                        'source'        => 'push'
                    );
                }
            }

            $coupon['objects_coupon_data'] = $objects_coupon_data;
            $coupon['coupon'] = $couponItems;
        }
        if (isset($platform->_ordersdf['coupon_actuallypay_field']) && $platform->_ordersdf['coupon_actuallypay_field']) {
            $field = $platform->_ordersdf['coupon_actuallypay_field'];
            if (isset($platform->_ordersdf['order_objects']) && is_array($platform->_ordersdf['order_objects'])) {
                foreach ($platform->_ordersdf['order_objects'] as $orderObj) {
                    if(strpos($field, '/')) {
                        $arrField = explode('/', $field);
                        $calcActuallyPay = $orderObj[$arrField[0]][$arrField[1]];
                    }else{
                        $calcActuallyPay = $orderObj[$field];
                    }
                    if (isset($platform->_ordersdf['coupon_actuallypay_field_unit']) && $platform->_ordersdf['coupon_actuallypay_field_unit']) {
                        $calcActuallyPay = $calcActuallyPay / $platform->_ordersdf['coupon_actuallypay_field_unit'];
                    }
                    if (floatval($calcActuallyPay) > 0) {
                        $coupon['coupon'][] = [
                            'num'           => $orderObj['quantity'],
                            'material_bn'   => $orderObj['bn'],
                            'oid'           => $orderObj['oid'],
                            'material_name' => $orderObj['name'],
                            'type'          => 'calcActuallyPay',
                            'type_name'     => '实付金额',
                            'coupon_type'   => 0,
                            'amount'        => sprintf('%.3f', floatval($calcActuallyPay) / (intval($orderObj['quantity']) ?: 1)),
                            'total_amount'  => floatval($calcActuallyPay),
                            'create_time'   => sprintf('%.0f', time()),
                            'pay_time'      => !empty($platform->_newOrder['paytime']) ? kernel::single('ome_func')->date2time($platform->_newOrder['paytime']) : null,
                            'shop_type'     => $platform->__channelObj->channel['shop_type'],
                            'source'        => 'push'
                        ];
                    }
                }
            }
        }

        // 如果是国补，并且是下单立减，把国补优惠金额加到优惠明细里
        /*
        if ($platform->_ordersdf['guobu_info'] && in_array('2', $platform->_ordersdf['guobu_info']['guobu_type']) && $platform->_ordersdf['guobu_info']['gov_subsidy_amount_new']) {
            $num = 1;
            $amount = $platform->_ordersdf['guobu_info']['gov_subsidy_amount_new'];
            $material_bn = $platform->_ordersdf['order_objects'][0]['bn'];
            $coupon['coupon'][] = [
                'num'           => $num,
                'material_bn'   => $material_bn,
                'oid'           => $platform->_ordersdf['order_objects'][0]['oid'],
                'material_name' => $platform->_ordersdf['order_objects'][0]['name'],
                'type'          => 'guobu',
                'type_name'     => '下单立减',
                'coupon_type'   => 1,
                'amount'        => $amount,
                'total_amount'  => $num * $amount,
                'create_time'   => kernel::single('ome_func')->date2time($platform->_ordersdf['createtime']),
                'pay_time'      => kernel::single('ome_func')->date2time($platform->_ordersdf['pay_time']),
                'shop_type'     => $platform->_ordersdf['shop_type'],
                'source'        => 'rpc',
            ];
            $bm_from_obj = [];
            if ($coupon['objects_coupon_data']) {
                $bm_from_obj = array_column($coupon['objects_coupon_data'], 'material_bn');
            }
            if (in_array($material_bn, $bm_from_obj)) {
                foreach ($coupon['objects_coupon_data'] as $_k => $_v) {
                    if ($material_bn == $_v['material_bn']) {
                        $addon = unserialize($_v['addon']);
                        $addon['guobu'] = $num * $amount;
                        $coupon['objects_coupon_data'][$_k]['addon'] = serialize($addon);
                        break;
                    }
                }
            } else {                
                $shopInfo = kernel::single('ome_shop')->getRowByShopId($platform->__channelObj->channel['shop_id']);
                $coupon['objects_coupon_data'][] = [
                    'order_bn'      => $platform->_ordersdf['order_bn'],
                    'num'           => $num,
                    'material_name' => $platform->_ordersdf['order_objects'][0]['name'],
                    'material_bn'   => $platform->_ordersdf['order_objects'][0]['bn'],
                    'oid'           => $platform->_ordersdf['order_objects'][0]['oid'],
                    'create_time'   => kernel::single('ome_func')->date2time($platform->_ordersdf['createtime']),
                    'shop_id'       => $shopInfo['shop_id'],
                    'shop_type'     => $platform->_ordersdf['shop_type'],
                    'source'        => 'rpc',
                    'addon'         => serialize(['guobu'=>$num * $amount]),
                    'org_id'        => $shopInfo['org_id'],
                ];
            }
        }
        */

        // 平台优惠金额(原始)
        if ($platform->_ordersdf['platform_cost_amount']) {
            $platform->_newOrder['platform_cost_amount'] = $platform->_ordersdf['platform_cost_amount'];
            $_coupon = kernel::single('ome_order_coupon')->couponFromPlatformDiscount($platform->_newOrder);
            if ($_coupon['coupon_data']) {
                !$coupon['coupon'] && $coupon['coupon'] = [];
                $coupon['coupon'] = array_merge($coupon['coupon'], $_coupon['coupon_data']);
            }
            if ($_coupon['objects_coupon_data']) {
                !$coupon['objects_coupon_data'] && $coupon['objects_coupon_data'] = [];
                $coupon['objects_coupon_data'][] = array_merge($coupon['objects_coupon_data'], $_coupon['objects_coupon_data']);
            }
        }

        return $coupon;
    }
    
    /**
     * 订单完成后处理
     * 
     * @return void
     * @author
     * */
    public function postCreate($order_id, $coupon_data)
    {
        $coupon = $coupon_data['coupon'];
        //记录优惠明细
        if ($coupon) {
            $couponObj = app::get('ome')->model('order_coupon');
            foreach ($coupon as $key => $value) {
                $coupon[$key]['order_id'] = $order_id;
            }
        
            $sql = ome_func::get_insert_sql($couponObj, $coupon);
        
            kernel::database()->exec($sql);
        }

        $coupon_sku = $coupon_data['objects_coupon_data'];
        if ($coupon_sku) {
            //汇总优惠明细
            $objectsCouponMdl = app::get('ome')->model('order_objects_coupon');
            foreach ($coupon_sku as $key => $value) {
                $coupon_sku[$key]['order_id'] = $order_id;
            }
    
            $sql = ome_func::get_insert_sql($objectsCouponMdl, $coupon_sku);
            kernel::database()->exec($sql);
        }
    }
    
        /**
     * postUpdate
     * @param mixed $order_id ID
     * @param mixed $coupon_data 数据
     * @return mixed 返回值
     */
    public function postUpdate($order_id,$coupon_data)
    {
        $coupon = $coupon_data['coupon'];
        $coupon_sku = $coupon_data['objects_coupon_data'];
        //记录优惠明细
        $couponObj = app::get('ome')->model('order_coupon');
        foreach ($coupon as $key => $value) {
            $coupon[$key]['order_id'] = $order_id;
        }
        if (!empty($coupon) && !$couponObj->db_dump(array('order_id'=>$order_id))) {
            $sql = ome_func::get_insert_sql($couponObj, $coupon);
            kernel::database()->exec($sql);
        }
        
        //汇总优惠明细
        $objectsCouponMdl = app::get('ome')->model('order_objects_coupon');
        foreach ($coupon_sku as $key => $value) {
            $coupon_sku[$key]['order_id'] = $order_id;
        }
        if (!empty($coupon_sku) && !$objectsCouponMdl->db_dump(array('order_id'=>$order_id))) {
            $sql = ome_func::get_insert_sql($objectsCouponMdl, $coupon_sku);
            kernel::database()->exec($sql);
        }
    }
}