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
 * 订单处理
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_matrix_360buy_request_order extends erpapi_shop_request_order
{
    function couponDetailGet($order_bn, $ext_data)
    {
        //api请求
        $timeout = 10;
        $param   = array(
            'tid' => $order_bn,
        );
        $title   = '获取平台订单优惠[' . $order_bn . ']明细';
    
        $result = $this->__caller->call(STORE_TRADE_COUPONDETAIL_GET, $param, array(), $title, $timeout, $order_bn);
    
        if ($result['rsp'] == 'succ' && !empty($result['data'])) {
            $result['data'] = json_decode($result['data'], true);
            //优惠明细数据format
            $ext_data['coupon_source']         = 'rpc';
            $formatData     = kernel::single('ome_order_coupon')->couponDataFormat($result['data'], $ext_data);
            //优惠金额明细数据
            $result['coupon_data'] = $formatData['coupon_data'];
            //实付金额小计
            $result['divide_order_fee_mount'] = $formatData['divide_order_fee_mount'];
            //实付金额与优惠金额计算
            $result['price_list'] = $formatData['price_list'];
            //扣除运费
            $result['vender_fee_amount'] = $formatData['vender_fee_amount'];
            //汇总优惠明细
            $result['objects_coupon_data'] = $formatData['objects_coupon_data'];
        }

        return $result;
    }

    /**
     * 获取WMS发货信息
     * 
     * @return
     * @author
     * */

    public function oid_sync($sdf)
    {
        $order = $sdf['order'];
        $oidArr = [];
        foreach ($sdf['split_oid'] as $v) {
            $oidArr[$v['split_oid']][] = $v;
        }
        $objArr = array_column($sdf['order_objects'], null, 'obj_id');
        $skuGroup = [];
        foreach ($oidArr as $split_oid => $value) {
            $skuInfo = [];
            foreach ($value as $v) {
                $obj_id = $v['obj_id'];
                $tmp = [
                    'sku_id'=>$objArr[$obj_id]['sku_id'],
                    'num'=>$objArr[$obj_id]['quantity']
                ];
                if($objArr[$obj_id]['sku_uuid']) {
                    $tmp['uuid'] = $objArr[$obj_id]['sku_uuid'];
                    $tmp['num'] = $v['num'];
                }
                $skuInfo[] = $tmp;
            }
            $skuGroup[] = ['sku_info_list'=>$skuInfo];
        }
        $data = [
            'tid'=>$order['order_bn'],
            'sku_group_list'=>json_encode($skuGroup)
        ];

        $result = $this->__caller->call(SHOP_ORDER_SPLIT, $data, array(), '拆单结果同步', 20, $order['order_bn']);
        if ($result['rsp'] == 'succ') {
            unset($result['response']);
        }

        return $result;
    }

        /**
     * serial_sync
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function serial_sync($sdf) {

        $billId  = $sdf['delivery_id'];
        $billNo  = $sdf['delivery_bn']; 
        $orderBn = $sdf['order_bn'];

        $title    = '同步唯一码'.$billNo;
        $obj      = app::get('ome')->model('product_serial_history');
        $snFilter = ['bill_id'=>$billId, 'bill_type'=>'3', 'sync|noequal'=>'succ'];
        $obj->update(['sync'=>'run'], $snFilter);

        if ($sdf['order_source'] == 'jdjx') {

            $api_name = SHOP_FX_SERIALNUMBER_UPDATE;

            $params = array(
                'tid'               =>  $sdf['order_bn'],
                'skuSerialList'     =>  [],
            );
            foreach ($sdf['serial_number_arr'] as $_k => $serial_number) {

                list($imei1, $imei2) = explode(',', $sdf['imei_number_arr'][$_k]);

                $params['skuSerialList'][] = array_filter([
                    'jdSkuId'   =>  $sdf['shop_product_id'], // 商品skuId
                    'sn'        =>  $serial_number, // 序列号码
                    'imei1'     =>  $imei1,
                    'imei2'     =>  $imei2,
                ]);
            }

            $return_data = $this->__caller->call($api_name,$params,array(),$title,10,$orderBn);
            if($return_data['rsp'] == 'succ') {
                $obj->update(['sync'=>'succ'], $snFilter);
            } else {
                $obj->update(['sync'=>'fail'], $snFilter);
            }

        } else {

            $api_name = SHOP_SERIALNUMBER_UPDATE;

            foreach ($sdf['serial_number_arr'] as $serial_number) {

                $params = array(
                    'tid'               =>  $sdf['order_bn'],
                    'serial_code_type'  =>  '1', // 序列号类型。1：sn码 2：imei
                    'serial_code'       =>  $serial_number, // 序列号码
                    'sku_id'            =>  $sdf['shop_product_id'], // 商品skuId
                );

                $return_data = $this->__caller->call($api_name,$params,array(),$title,10,$orderBn);
                if($return_data['rsp'] == 'succ') {
                    $obj->update(['sync'=>'succ'], $snFilter);
                } else {
                    $obj->update(['sync'=>'fail'], $snFilter);
                }
            }
        }
        return $return_data;
    }
    
    /**
     * [京东700虚拟号]隐私号G组更新：报备外呼主叫号码组
     *
     * @param array $params
     * @return array
     */
    public function bindSecretMobiles($params)
    {
        $title = '隐私号G组更新';
        
        $original_bn = $params['order_bn'];
        
        // check
        if(empty($original_bn) || empty($params['mobile_list'])){
            return $this->error('没有order_bn 或 mobile_list');
        }
        
        // mobile_list
        if(is_array($params['mobile_list'])){
            $json_mobile = json_encode($params['mobile_list'], JSON_UNESCAPED_UNICODE);
        }else{
            $json_mobile = $params['mobile_list'];
        }
        
        // params
        $params = array(
            'operate_type' => 'ADD_GXB_GROUP', // ADD_GXB_GROUP: 添加，DELETE_GXB_GROUP: 删除
            'tid' => $params['order_bn'], //订单号
            'mobile_list' => $json_mobile, // JSON格式
            //'virtual_id' => '', //此字段不需要传入
        );
        
        // callback
        $callback = array();
        
        // 使用矩阵透传接口请求淘宝
        $result = $this->__caller->call(STORE_VIRTUAL_NUMBER_GROUP_UPDATE, $params, $callback, $title, 10, $original_bn);
        
        return $result;
    }
}
