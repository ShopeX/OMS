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
class erpapi_shop_matrix_taobao_request_delivery extends erpapi_shop_request_delivery
{
    protected function get_delivery_apiname($sdf)
    {
        //是否支持虚拟发货回传
        $enableVirtual = $this->__channelObj->channel['config']['enable_virtual'] == 'close' ? 'close' : 'enable';
        
        //虚拟发货接口
        if($sdf['is_virtual'] && $enableVirtual == 'enable') {
            return SHOP_LOGISTICS_DUMMY_SEND;
        }
        
        // 如果开启了线上发货
        if ('on' == app::get('ome')->getConf('ome.delivery.method') && $sdf['orderinfo']['sync'] == 'none') {
            $api_name = SHOP_LOGISTICS_ONLINE_SEND;
        } else {
            $api_name = $sdf['orderinfo']['is_cod'] == 'true' ? SHOP_LOGISTICS_ONLINE_SEND : SHOP_LOGISTICS_OFFLINE_SEND;
        }
        
        // 如果是家装，调用家装接口
        if ($sdf['jzpartner']) {
            $api_name = SHOP_WLB_ORDER_JZ_CONSIGN;
        }
        #检查是不是天猫国际
        if($sdf['corp_type'] ==1 && (($sdf['orderinfo']['order_bool_type'] & ome_order_bool_type::__INTERNAL_CODE) ==  ome_order_bool_type::__INTERNAL_CODE)){
            $api_name = SHOP_WLB_THREEPL_OFFLINE_SEND;
        }
        
        //同城配场景
        if($sdf['delivery_type'] == 'instatnt'){
            //同城配送
            $api_name = SHOP_LOGISTICS_OFFLINE_SEND;
        }elseif($sdf['delivery_type'] == 'seller'){
            //商家配送
            $api_name = SHOP_LOGISTICS_SELLER_SEND;
        }
        
        return $api_name;
    }

    /**
     * 发货请求参数
     *
     * @return void
     * @author 
     **/

    protected function get_confirm_params($sdf)
    {
        if ($sdf['jzpartner']) {// 家装参数
            
            $jz_top_args = array(
                'mail_no'         => $sdf['logi_no'],
                'zy_consign_ti' => date('Y-m-d',$sdf['delivery_time']),
                'package_rem'  => '',
                'zy_company'      => $sdf['logi_name'],
                'zy_phone_nu' => $this->__channelObj->channel['mobile'] ? $this->__channelObj->channel['mobile'] : $this->__channelObj->channel['tel'],
                //'package_num'=>1,
                
            );
            
            $param = array(
                'tid'             =>$sdf['orderinfo']['order_bn'],
                'lg_tp_dto'     => json_encode($sdf['jzpartner']['lg_tp_dto']),
                'ins_tp_dto'=>json_encode($sdf['jzpartner']['ins_tp_dto']),
                'jz_top_args' => json_encode($jz_top_args),
            );
        } elseif(($sdf['orderinfo']['order_bool_type'] & ome_order_bool_type::__INTERNAL_CODE) ==  ome_order_bool_type::__INTERNAL_CODE){
            $param['trade_id'] = $sdf['orderinfo']['order_bn'];# 交易单号
            $param['waybill_no'] = $sdf['logi_no'];# 运单号
            $param['res_id'] =  $sdf['crossborder_res_id'];#跨境配送资源id
            $param['res_code'] = $sdf['logi_type'];# ;#资源code
            $param['from_id'] = $sdf['crossborder_region_id']; #发货地区域id
        }else {
            $param = parent::get_confirm_params($sdf);
            
            // 拆单子单回写
            if($sdf['is_split'] == 1) {
                $param['is_split']  = $sdf['is_split'];
                $param['oid_list']  = implode(',',$sdf['oid_list']);
            }

            // 判断是否开启唯一码回写
            if ($sdf['feature']) $param['feature'] = $sdf['feature'];
        }

        // 淘宝直销、分销是否是国补订单的发货回写
        $order_id = $sdf['orderinfo']['order_id'];
        $isGuobu  = kernel::single('ome_bill_label')->getBillLabelInfo($order_id, 'order', 'SOMS_GB');
        if ($isGuobu) {
            if ($isGuobu['label_value'] & 0x0040 || $isGuobu['label_value'] & 0x0080) {
                if ($sdf['feature'] && !$param['feature']) $param['feature'] = $sdf['feature'];
            } else {
                unset($param['feature']);
            }
        }
        
        //同城配场景
        if($sdf['delivery_type'] == 'instatnt'){
            //同城配送
            $wmsDlyMdl = app::get('wms')->model('delivery');
            $wmdDlyInfo = $wmsDlyMdl->dump(array('outer_delivery_bn'=>$sdf['delivery_bn']), 'delivery_id,delivery_bn,deliveryman,deliveryman_mobile');
            
            //收货人手机号
            $param['feature'] = 'instantMobilePhoneNumber='. $wmdDlyInfo['deliveryman_mobile'];
            
        }elseif($sdf['delivery_type'] == 'seller'){
            //商家配送
            $wmsDlyMdl = app::get('wms')->model('delivery');
            $wmdDlyInfo = $wmsDlyMdl->dump(array('outer_delivery_bn'=>$sdf['delivery_bn']), 'delivery_id,delivery_bn,deliveryman,deliveryman_mobile');
            
            //配送员信息
            $param['delivery_name'] = $wmdDlyInfo['deliveryman']; //快递员姓名
            $param['delivery_mobile'] = $wmdDlyInfo['deliveryman_mobile']; //快递员手机号
        }
        
        //获取多包裹信息
        $packageInfo = array();
        $is_packages = false;
        if($sdf['delivery_package']){
            $packageInfo = $this->_getDeliveryPackages($sdf, 'taobao');
            if(isset($packageInfo['packages']) && count($packageInfo['packages']) > 1){
                $is_packages = true;
            }
        }
        
        //[部分拆单 OR 多包裹]订单没有编辑过商品,按拆单子单回写
        if(($sdf['is_split'] == 1 || $is_packages) && $sdf['orderinfo']['is_modify'] != 'true') {
            //按多包裹
            if($packageInfo){
                $param['packages'] = json_encode($packageInfo['packages']);
                $param['consign_status'] = json_encode($packageInfo['consignStatus']);
                $param['consign_type'] = 1; //发货类型,0:普通发货(老链路),1:普通发货(新链路)
                $param['sync_mode'] = 'delivery_package'; //回写标记(OMS内部字段)
            }else{
                //按发货子订单明细
                $deliveryItems = $this->_getDeliveryItems($sdf, 'taobao');
                if($deliveryItems){
                    $param['packages'] = json_encode($deliveryItems['packageList']);
                    $param['consign_status'] = json_encode($deliveryItems['consignStatus']);
                    $param['consign_type'] = 1; //发货类型,0:普通发货(老链路),1:普通发货(新链路)
                    $param['sync_mode'] = 'delivery_items'; //回写标记(OMS内部字段)
                }
            }
        }else{
            //发货类型,0:普通发货(老链路),1:普通发货(新链路)
            $param['consign_type'] = 0;
            
            //订单编辑过商品
            if($sdf['orderinfo']['is_modify'] == 'true'){
                //注销oid_list子订单列表
                unset($param['oid_list']);
                
                $param['sync_mode'] = 'order_modify'; //回写标记(OMS内部字段)
            }else{
                //[未拆单]按oid子订单列表,整单回写平台
                $oids = $this->_getDeliveryOids($sdf);
                if($oids){
                    $param['sub_tid'] = implode(',', $oids);
                }
                
                $param['sync_mode'] = 'order_oids'; //回写标记(OMS内部字段)
            }
        }
        
        return $param;
    }

    /**
     * 数据处理
     *
     * @return void
     * @author 
     **/
    protected function format_confirm_sdf(&$sdf)
    {
        parent::format_confirm_sdf($sdf);

        // 如果是家装
        if ('1' == app::get('ome')->getConf('shop.jzorder.config.'.$this->__channelObj->channel['shop_id'])) {
            $partner = $this->jzpartner_query($sdf);

            if ($partner) {
                $sdf['jzpartner'] = $partner;
            }
        }
    }
}