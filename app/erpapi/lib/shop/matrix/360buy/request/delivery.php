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
 * 发货单处理
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_matrix_360buy_request_delivery extends erpapi_shop_request_delivery
{

    protected $_delivery_errcode = array(
        'w06000'=>'成功',
        'w06001'=>'其他',
        'w06101'=>'已经出库',
        'w06102'=>'出库订单不存在或已被删除',
        'w06104'=>'订单状态不为等待发货',
        'w06105'=>'订单已经发货',
        'w06106'=>'正在出库中', 
    );

    /**
     * confirm
     * @param mixed $sdf sdf
     * @param mixed $queue queue
     * @return mixed 返回值
     */

    public function confirm($sdf,$queue=false)
    {
        // 检测是否为国补订单，如果是国补并且有sn码，回传sn码再通知发货
        if ($sdf['serial_number']) {

            $order_id = $sdf['orderinfo']['order_id'];
            $isGuobu  = kernel::single('ome_bill_label')->getBillLabelInfo($order_id, 'order', 'SOMS_GB');

            $serial_number_arr = $imei_number_arr = [];
            $shop_product_id = '';
            if ($isGuobu) {
                $delivery_bm_id = [];
                foreach ($sdf['delivery_items'] as $_delivery_items) {
                    if ($_delivery_items['bm_id']) {
                        $delivery_bm_id[$_delivery_items['bm_id']] = $_delivery_items['shop_product_id'];
                    }
                }
                foreach ($sdf['serial_number'] as $_bm_id => $_v) {
                    if ($delivery_bm_id[$_bm_id]) {
                        $serial_number_arr = $_v['sn'];
                        $imei_number_arr   = $_v['imei'];
                        $shop_product_id   = $delivery_bm_id[$_bm_id];
                        break;
                    }
                }
            }

            if ($serial_number_arr && $shop_product_id) {
                $serial_params = [
                    'order_bn'          =>  $sdf['orderinfo']['order_bn'],
                    'delivery_id'       =>  $sdf['delivery_id'],
                    'delivery_bn'       =>  $sdf['delivery_bn'],
                    'shop_product_id'   =>  $shop_product_id,
                    'serial_number_arr' =>  $serial_number_arr,
                    'imei_number_arr'   =>  $imei_number_arr,
                    'order_source'      =>  $sdf['orderinfo']['order_source'],
                ];
                $res = kernel::single('erpapi_router_request')->set('shop',$this->__channelObj->channel['shop_id'])->order_serial_sync($serial_params);
                // if ($res['rsp'] != 'succ') {
                //     return $this->error('唯一码上传失败');
                // }
            }
        }

        return parent::confirm($sdf,$queue);
    }

    /**
     * 发货请求参数
     *
     * @return void
     * @author 
     **/
    protected function get_confirm_params($sdf)
    {
        $param = parent::get_confirm_params($sdf);

        $param['360buy_business_type'] = $this->__channelObj->channel['addon']['type'];

        if ('SOPL' == $this->__channelObj->channel['addon']['type']) {
            $param['package_num'] = $sdf['itemNum'];
        }
        if($sdf['is_split'] == 1) {
            $param['package_type'] = 'break';
            $packages = $this->_getPackages($sdf);
            $param['packages'] = json_encode($packages);
        } else {
            // 拆单回写
            $logi_no = array ();
            foreach ($sdf['delivery_items'] as $key => $value) {
                $logi_no[$value['logi_type']][$value['logi_no']] = $value['logi_no'];
            }

            foreach ($sdf['delivery_bill_list'] as $key => $value) {
                if(strpos($value['logi_no'], '-')) {
                    continue;
                }
                $logi_no[$value['logi_type']][$value['logi_no']] = $value['logi_no'];
            }

            foreach ($logi_no as $key => $value) {
                $logi_no[$key] = implode(',',(array)$value);
            }

            if ($logi_no) {
                $param['company_code'] = implode('|',array_keys($logi_no));
                $param['logistics_no'] = implode('|',array_values($logi_no));
            }
        }
        $order_id = $sdf['orderinfo']['order_id'];
        $fenxiao_order = kernel::single('ome_bill_label')->getBillLabelInfo($order_id, 'order', 'SOMS_FENXIAO');
        if ($fenxiao_order) {
            $param['360buy_is_dx'] = 'true';
        }

        return $param;
    }

    protected function _getPackages($sdf)
    {
        //多包裹
        $dlyPackages = array();
        foreach ($sdf['delivery_package'] as $key => $val)
        {
            $product_bn = $val['bn'];
            $logi_no = $val['logi_no'] ? $val['logi_no'] : $sdf['logi_no'];
            
            //check
            if(empty($product_bn) || empty($logi_no)){
                continue;
            }
            
            //按[货号+物流单号]纬度
            //@todo：天猫平台一个订单有2行SKU一模一样（买一赠一商品有金额多数量）并且有多个不同物流单号的场景；
            if(isset($dlyPackages[$product_bn][$logi_no])){
                $dlyPackages[$product_bn][$logi_no]['number'] += $val['number'];
            }else{
                $dlyPackages[$product_bn][$logi_no] = array(
                    'package_key' => $key,
                    'number' => $val['number'],
                );
            }
        }
        
        //按发货单明细获取包裹信息
        $packageList = array();
        foreach ($sdf['delivery_items'] as $itemKey => $itemVal)
        {
            $product_bn = $itemVal['product_bn'];
            $item_number = $itemVal['number'];
            $oid = $itemVal['sku_uuid'];
            
            //check
            if(empty($oid)){
                continue;
            }
            
            if(empty($dlyPackages[$product_bn])){
                continue;
            }
            
            //初始化打包数量
            $sdf['delivery_items'][$itemKey]['pack_nums'] = 0;
            
            //oid信息
            $oidList[$oid] = array('nums'=>$itemVal['nums'], 'sendnum'=>$itemVal['sendnum']);
            
            //一个货号有多个物流包裹的场景
            foreach ($dlyPackages[$product_bn] as $logi_no => $packVal)
            {
                $package_key = $packVal['package_key'];
                $packageInfo = $sdf['delivery_package'][$package_key];
                
                //check
                if($packVal['number'] < 1){
                    continue;
                }
                
                if(empty($packageInfo)){
                    continue;
                }
                
                //检查已经打包的数量(PKG组合商品没有sendnum字段)
                if(isset($sdf['delivery_items'][$itemKey]['sendnum'])){
                    if($sdf['delivery_items'][$itemKey]['pack_nums'] >= $sdf['delivery_items'][$itemKey]['sendnum']){
                        continue;
                    }
                }
                
                //包裹发货数量
                if($packVal['number'] >= $item_number){
                    $package_num = $item_number;
                    
                    $dlyPackages[$product_bn][$logi_no]['number'] -= $item_number;
                }else{
                    $package_num = $packVal['number'];
                    
                    $dlyPackages[$product_bn][$logi_no]['number'] = 0;
                }
                
                //已经打包的数量
                $sdf['delivery_items'][$itemKey]['pack_nums'] += $package_num;
                //data
                if(!isset($packageList[$logi_no])){
                    $packageList[$logi_no] = array(
                        'company_code' => $sdf['logi_type'],
                        'company_name' => $sdf['logi_name'],
                        'out_sid' => $packageInfo['logi_no'],
                        'goods' => array(),
                    );
                    if($sdf['logi_type'] == 'OTHER' || $sdf['logi_type'] == 'virtual_delivery'){
                        $packageList[$logi_no]['company_code'] = 'OTHER';
                        $packageList[$logi_no]['zsDelivererName'] = $sdf['logi_name'];
                        $packageList[$logi_no]['zsDelivererPhone'] = 12341234123;
                    }
                }
                $packageList[$logi_no]['goods'][] = array(
                    'num' => $package_num,
                    'sku_id' => $itemVal['shop_product_id'],
                    'sku_uuid' => $oid,
                    'sub_tid' => $sdf['orderinfo']['order_bn'],
                );
            }
        }
        
        // 调用miele service处理特定物流编码，应用OTHER相同的处理逻辑
        if ($service = kernel::servicelist('erpapi.service.shop.360buy.delivery.packages.format')) {
            foreach ($service as $object => $instance) {
                if (method_exists($instance, 'getPackageAfter')) {
                    $packageList = $instance->getPackageAfter($packageList, $sdf);
                }
            }
        }
        
        return array_values($packageList);
    }

   /**
     * 发货回调
     *
     * @return void
     * @author
     **/
    public function confirm_callback($response, $callback_params)
    {

        $failApiModel = app::get('erpapi')->model('api_fail');
        $order_id        = $callback_params['order_id'];
        $err_msg = $response['err_msg'];
        $rsp             = $response['rsp'];
        $rsp=='success' ? 'succ' : $rsp;
        if($callback_params['company_code'] == 'JDCOD'){

            if($rsp == 'fail' && ($err_msg == '运单没有在青龙系统生成' || $err_msg == '平台连接后端服务不可用')){
                $response['msg_code'] = 'G40012';
            }
        }
        $callback_params['obj_type'] = 'JDDELIVERY';
        $rs = parent::confirm_callback($response,$callback_params);
        return $rs;
    }

    protected function get_delivery_apiname($sdf)
    {
        
        if($sdf['is_jdzd']){
            return SHOP_LOGISTICS_CONSIGN_RESEND;
        }else{
            return SHOP_LOGISTICS_OFFLINE_SEND;
            
        }
        
    }
}