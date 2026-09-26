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
* 订单主表信息
*
* @author chenping<chenping@shopex.cn>
* @version $Id: master.php 2013-3-12 17:23Z
*/
class erpapi_shop_response_components_order_master extends erpapi_shop_response_components_order_abstract
{
    const _APP_NAME = 'ome';
    /**
     * 订单格式转换
     * 
     * @return void
     * @author 
     * */

    public function convert()
    {
        $funcObj = kernel::single('ome_func');

        # 店铺
        $this->_platform->_newOrder['shop_id']   = $this->_platform->__channelObj->channel['shop_id'];
        $this->_platform->_newOrder['shop_type'] = $this->_platform->__channelObj->channel['shop_type'];

        # 订单主信息
        $this->_platform->_newOrder['order_bn']         = $this->_platform->_ordersdf['order_bn'];
        $this->_platform->_newOrder['platform_order_bn'] = $this->_platform->_ordersdf['order_bn']; //平台订单号
        $this->_platform->_newOrder['cost_item']        = (float)$this->_platform->_ordersdf['cost_item'];
        $this->_platform->_newOrder['discount']         = (float)$this->_platform->_ordersdf['discount'];
        $this->_platform->_newOrder['total_amount']     = (float)$this->_platform->_ordersdf['total_amount'];
        $this->_platform->_newOrder['pmt_goods']        = (float)$this->_platform->_ordersdf['pmt_goods'];
        $this->_platform->_newOrder['pmt_order']        = (float)$this->_platform->_ordersdf['pmt_order'];
        $this->_platform->_newOrder['cur_amount']       = (float)$this->_platform->_ordersdf['cur_amount'];
        $this->_platform->_newOrder['score_u']          = (float)$this->_platform->_ordersdf['score_u'];
        $this->_platform->_newOrder['score_g']          = (float)$this->_platform->_ordersdf['score_g'];
        $this->_platform->_newOrder['currency']         = $this->_platform->_ordersdf['currency'] ? $this->_platform->_ordersdf['currency'] : 'CNY';
        $this->_platform->_newOrder['source']           = 'matrix';
        $this->_platform->_newOrder['status']           = $this->_platform->_ordersdf['status'];
        $this->_platform->_newOrder['weight']           = (float)$this->_platform->_ordersdf['weight'];
        $this->_platform->_newOrder['order_source']     = $this->_platform->_ordersdf['order_source'];
        $this->_platform->_newOrder['cur_rate']         = $this->_platform->_ordersdf['cur_rate'] ? $this->_platform->_ordersdf['cur_rate'] : 1;
        $this->_platform->_newOrder['title']            = $this->_platform->_ordersdf['title'];
        $this->_platform->_newOrder['source_status']    = $this->_platform->_ordersdf['source_status'];
        $this->_platform->_newOrder['coupons_name']     = $this->_platform->_ordersdf['coupons_name'];
        $this->_platform->_newOrder['createway']        = 'matrix';
        $this->_platform->_newOrder['order_bool_type']     = $this->_platform->_ordersdf['order_bool_type'];
        $this->_platform->_newOrder['change_sku']     = $this->_platform->_ordersdf['change_sku'];
        $this->_platform->_newOrder['service_price']     = $this->_platform->_ordersdf['service_price'] ?? 0;
        $this->_platform->_newOrder['platform_service_fee']     = $this->_platform->_ordersdf['platform_service_fee'] ?? 0;
        
        $this->_platform->_newOrder['o2o_info']         = $this->_platform->_ordersdf['o2o_info'];
        
        $this->_platform->_newOrder['order_type'] = $this->_platform->_ordersdf['order_type'] ? strtolower($this->_platform->_ordersdf['order_type']) : 'normal';

        if (isset($this->_platform->_ordersdf['self_delivery'])) $this->_platform->_newOrder['self_delivery']        = $this->_platform->_ordersdf['self_delivery'];

        # 时间
        $this->_platform->_newOrder['download_time']    = time();
        $this->_platform->_newOrder['createtime']       = $funcObj->date2time($this->_platform->_ordersdf['createtime']);
        $outer_lastmodify = $this->_platform->_ordersdf['lastmodify'] ? $this->_platform->_ordersdf['lastmodify'] : time();
        $this->_platform->_newOrder['outer_lastmodify'] = $funcObj->date2time($outer_lastmodify);

        if ($this->_platform->_ordersdf['order_limit_time']) {
            $this->_platform->_newOrder['order_limit_time'] = $funcObj->date2time($this->_platform->_ordersdf['order_limit_time']);
        } else {
            $this->_platform->_newOrder['order_limit_time'] = time() + 60 * (app::get('ome')->getConf('ome.order.failtime'));
        }

        if ($this->_platform->_ordersdf['relate_order_bn']){
            $this->_platform->_newOrder['relate_order_bn'] = $this->_platform->_ordersdf['relate_order_bn'];
        }
        if($this->_platform->_ordersdf['platform_order_bn']){
            $this->_platform->_newOrder['platform_order_bn'] = $this->_platform->_ordersdf['platform_order_bn'];
        }
        # 支付方式
        $payment_cfg             = $this->_platform->get_payment($this->_platform->_ordersdf['pay_bn'],$this->_platform->__channelObj->channel['node_type']);
        $this->_platform->_newOrder['pay_bn']      = $payment_cfg['pay_bn'];
        $this->_platform->_newOrder['pay_status'] =$this->_platform->_ordersdf['pay_status'];
        $this->_platform->_newOrder['payed']      = $this->_platform->_ordersdf['payed'];

        //接口版本号
        if ($this->_platform->_ordersdf['extend_field']['version']) {
            $this->_platform->_newOrder['api_version'] = $this->_platform->_ordersdf['extend_field']['version'];
        }
        # 支付金额
        $this->_platform->_newOrder['payinfo']['pay_name']     = $this->_platform->_ordersdf['payinfo']['pay_name'];
        $this->_platform->_newOrder['payinfo']['cost_payment'] = $this->_platform->_ordersdf['payinfo']['cost_payment'];

        # 支付单结构
        $payment_list = isset($this->_platform->_ordersdf['payments']) ? $this->_platform->_ordersdf['payments'] : (array)$this->_platform->_ordersdf['payment_detail'];
        if($this->_platform->_ordersdf['payment_detail']){
            $payment_detail = $this->_platform->_ordersdf['payment_detail'];
            unset($this->_platform->_ordersdf['payment_detail']);
            $this->_platform->_ordersdf['payment_detail'][] = $payment_detail;
        }
        if ($payment_list && is_array($payment_list)) {
            $total_amount = $this->_platform->_ordersdf['total_amount'];

            $pay_status = '0';
            $payed      = '0.000';
            $paytime    = null;

            foreach ($payment_list as $key => $value) {
                $payed += $value['money'];
                
                if ($value['pay_time']) {
                    $paytime = kernel::single('ome_func')->date2time($value['pay_time']);
                }
            }

            if ($total_amount <= $payed) {
                $pay_status = '1';
                if ($this->_platform->_ordersdf['order_type'] == 'presale' && $this->_platform->_ordersdf['order_source'] == 'jingdong') {
                    $payed = $total_amount; // 支付金额不应该大于订单总额 
                }

                if (!$paytime) $paytime = time();
            } elseif ($payed <= 0) {
                $pay_status = '0';
            } else {
                if (!$paytime) $paytime = time();

                $comp = bccomp(round($payed,3), $total_amount,3);

                $pay_status = $comp < 0 ? '3' : '1';
            }

            $this->_platform->_newOrder['pay_status'] = $pay_status;
            $this->_platform->_newOrder['payed']      = $payed;
            
            if ($paytime)
                $this->_platform->_newOrder['paytime']    = intval($paytime);
            
        }

        if (in_array($this->_platform->_ordersdf['pay_status'], ['4', '5', '6', '7'])) {
            $this->_platform->_newOrder['pay_status'] = $this->_platform->_ordersdf['pay_status'];

            if ($this->_platform->_ordersdf['pay_status'] == '5') {
                $this->_platform->_newOrder['archive']          = '1';
                $this->_platform->_newOrder['process_status']   = 'cancel';
                $this->_platform->_newOrder['status']           = 'dead';
            }
        }


        //加入手动单拉订单_标记_防止自动审单
        $this->_platform->_newOrder['auto_combine']    = ($this->_platform->_ordersdf['auto_combine'] === false ? false : true);
        if($this->_platform->_ordersdf['is_service_order']){
            $this->_platform->_newOrder['is_service_order']     = $this->_platform->_ordersdf['is_service_order'];
        }

        // 平台运单号
        if ($this->_platform->_ordersdf['shipping']['shipping_id']) {
            $this->_platform->_newOrder['platform_logi_no'] = $this->_platform->_ordersdf['shipping']['shipping_id'];
            $this->_platform->_newOrder['logi_no']          = $this->_platform->_ordersdf['shipping']['shipping_id'];
        }
        
        // 物流公司ID（通过平台推送的物流公司编码，获取OMS的物流公司ID）
        if (isset($this->_platform->_ordersdf['shipping']['logi_id']) && $this->_platform->_ordersdf['shipping']['logi_id']) {
            $this->_platform->_newOrder['logi_id'] = $this->_platform->_ordersdf['shipping']['logi_id'];
        }
        
        //是否指定仓发货
        if ($this->_platform->_ordersdf['is_assign_store'] == 'true') {
            $this->_platform->_newOrder['is_assign_store'] = $this->_platform->_ordersdf['is_assign_store'];
        }
    
        //物流升级服务
        if ($this->_platform->_ordersdf['cpup_service']) {
            $this->_platform->_newOrder['cpup_service'] = $this->_platform->_ordersdf['cpup_service'];
        }
        
        //是否允许发货标识
        if($this->_platform->_ordersdf['is_delivery'] && in_array($this->_platform->_ordersdf['is_delivery'], array('N', 'Y'))) {
            $this->_platform->_newOrder['is_delivery'] = $this->_platform->_ordersdf['is_delivery'];
        }

        // // 得物品牌直发履约类型
        // if($this->_platform->_ordersdf['extend_field']['performance_type']){
        //     $tempData = $this->_platform->_ordersdf['extend_field']['performance_type'];
        //     $this->_platform->_newOrder['performance_type'] = $tempData;
        //     unset($tempData);
        // }

        // 唯品会，如果有merged_code,传merged_code，ome_mdl_order->create_order用到 逻辑迁移至erpapi_shop_matrix_vop_response_order::_analysis方法
//        if ($this->_platform->_ordersdf['vop_merged_code']) {
//            $this->_platform->_newOrder['extend_field'] = [
//                'merged_code' => $this->_platform->_ordersdf['vop_merged_code'],
//            ];
//        }

        // 快手，如果有集运中转，传给extend_field，ome_mdl_order->create_order用到
        if ($this->_platform->_ordersdf['extend_field']['consolidate_info']) {
            $this->_platform->_newOrder['extend_field']['consolidate_info'] = $this->_platform->_ordersdf['extend_field']['consolidate_info'];
        }
        
        //翱象订单
        if($this->_platform->_ordersdf['cn_info']['asdp_biz_type'] == 'aox' || $this->_platform->_ordersdf['cn_info']['logistics_agreement']['asdp_biz_type'] == 'aox'){
            //翱象订单
            $this->_platform->_newOrder['order_bool_type'] = $this->_platform->_newOrder['order_bool_type'] | ome_order_bool_type::__AOXIANG_CODE;
            
            //物流服务标签
            if($this->_platform->_ordersdf['logistics_service_msg']){
                $this->_platform->_newOrder['promise_service'] = $this->_platform->_ordersdf['logistics_service_msg'];
            }elseif($this->_platform->_ordersdf['cn_info']['logistics_agreement']['logistics_service_msg']){
                $this->_platform->_newOrder['promise_service'] = $this->_platform->_ordersdf['cn_info']['logistics_agreement']['logistics_service_msg'];
            }
        }
        
        //logistics_infos
        if($this->_platform->_ordersdf['logistics_infos']){
            //转换为数组(矩阵单拉订单是数组,主推订单是json字符串)
            if(is_string($this->_platform->_ordersdf['logistics_infos'])){
                $this->_platform->_ordersdf['logistics_infos'] = json_decode($this->_platform->_ordersdf['logistics_infos'], true);
            }
            
            $this->_platform->_newOrder['logistics_infos'] = $this->_platform->_ordersdf['logistics_infos'];
        }
    
        if ($this->_platform->_ordersdf['extend_field']) {
            $this->_platform->_newOrder['extend_field'] = $this->_platform->_ordersdf['extend_field'];
        }


        if($this->_platform->_ordersdf['step_trade_status']){
            $this->_platform->_newOrder['step_trade_status'] = $this->_platform->_ordersdf['step_trade_status'];
        }
        if ($this->_platform->_ordersdf['timing_confirm']) {
            $this->_platform->_newOrder['timing_confirm'] = $this->_platform->_ordersdf['timing_confirm'];
        }
        
        //定制订单
        if($this->_platform->_ordersdf['order_customs'] == 'Y'){
            //订单类型：定制订单
            //@todo：禁止使用此类型,升级大版本，sdb_ome_orders表中order_type字段刷不动,好来客户表太大刷不动;
            //$this->_platform->_newOrder['order_type'] = 'custom';
            
            //hold单:不用审核订单,禁止发货
            $this->_platform->_newOrder['is_delivery'] = 'N';
            
            //转换定制订单销售物料失败
            if(isset($this->_platform->_ordersdf['custom_abnormal_msg']) && $this->_platform->_ordersdf['custom_abnormal_msg']){
                $this->_platform->_newOrder['custom_transform_status'] = $this->_platform->_ordersdf['custom_transform_status'];
                $this->_platform->_newOrder['custom_abnormal_msg'] = $this->_platform->_ordersdf['custom_abnormal_msg'];
            }
        }
        
        //组织架构ID
        if(isset($this->_platform->_ordersdf['cos_id'])){
            $this->_platform->_newOrder['cos_id'] = $this->_platform->_ordersdf['cos_id'];
        }
        
        //贸易公司ID
        if(isset($this->_platform->_ordersdf['betc_id'])){
            $this->_platform->_newOrder['betc_id'] = $this->_platform->_ordersdf['betc_id'];
        }
        
        // trade_type
        if(isset($this->_platform->_ordersdf['trade_type']) && $this->_platform->_ordersdf['trade_type']){
            $this->_platform->_newOrder['trade_type'] = $this->_platform->_ordersdf['trade_type'];
        }
        
        // t_type
        if (isset($this->_platform->_ordersdf['t_type']) && $this->_platform->_ordersdf['t_type']){
            $this->_platform->_newOrder['t_type'] = $this->_platform->_ordersdf['t_type'];
        }
    }

        /**
     * 更新
     * @return mixed 返回值
     */
    public function update()
    {
        if (in_array($this->_platform->_tgOrder['pay_status'], array('6','7')) && in_array($this->_platform->_ordersdf['pay_status'], array('1','3','4','5'))) {
            $this->_platform->_newOrder['pause'] = 'false';
        }

        $master = array();

        if ($this->_platform->_ordersdf['order_limit_time']) {
            $order_limit_time = kernel::single('ome_func')->date2time($this->_platform->_ordersdf['order_limit_time']);
            if ($order_limit_time != $this->_platform->_tgOrder['order_limit_time'] && $this->_platform->_tgOrder['pay_status'] == '0') {
                $master['order_limit_time'] = $order_limit_time;
            }
        }
        
        //discount
        if(empty($this->_platform->_ordersdf['discount'])){
            $this->_platform->_ordersdf['discount'] = 0;
        }
        
        if(isset($this->_platform->_tgOrder['discount']) && empty($this->_platform->_tgOrder['discount'])){
            $this->_platform->_tgOrder['discount'] = 0;
        }
        
        $master['pay_status']                = $this->_platform->_ordersdf['pay_status'];
        $master['discount']                  = (float)$this->_platform->_ordersdf['discount'];
        $master['pmt_goods']                 = (float)$this->_platform->_ordersdf['pmt_goods'];
        $master['pmt_order']                 = (float)$this->_platform->_ordersdf['pmt_order'];
        $master['total_amount']              = (float)$this->_platform->_ordersdf['total_amount'];
        $master['cur_amount']                = (float)$this->_platform->_ordersdf['cur_amount'];
        $master['payed']                     = $this->_platform->_ordersdf['payed'];
        $master['cost_item']                 = $this->_platform->_ordersdf['cost_item'];
        $master['coupons_name']              = $this->_platform->_ordersdf['coupons_name'];
        $master['is_tax']                    = $this->_platform->_ordersdf['is_tax'] ? $this->_platform->_ordersdf['is_tax'] : 'false';
        $master['tax_no']                    = $this->_platform->_ordersdf['tax_no'];
        $master['cost_tax']                  = $this->_platform->_ordersdf['cost_tax'];
        $master['tax_title']                 = $this->_platform->_ordersdf['tax_title'];
        $master['weight']                    = $this->_platform->_ordersdf['weight'];
        $master['title']                     = $this->_platform->_ordersdf['title'];
        $master['score_u']                   = $this->_platform->_ordersdf['score_u'];
        $master['score_g']                   = $this->_platform->_ordersdf['score_g'];
        $master['status']                    = $this->_platform->_ordersdf['status'];
        $master['order_bool_type']           = $this->_platform->_ordersdf['order_bool_type'];
        $master['service_price']             = $this->_platform->_ordersdf['service_price'] ?? 0;
        $master['platform_service_fee']      = $this->_platform->_ordersdf['platform_service_fee'] ?? 0;
        //是否允许发货标识
        if($this->_platform->_ordersdf['is_delivery'] && in_array($this->_platform->_ordersdf['is_delivery'], array('N', 'Y'))) {
            $master['is_delivery'] = $this->_platform->_ordersdf['is_delivery'];
            if ($this->_platform->_ordersdf['shipping']['shipping_id'] && empty($this->_platform->_tgOrder['logi_no']) ) {
                $this->_platform->_newOrder['platform_logi_no'] = $this->_platform->_ordersdf['shipping']['shipping_id'];
                $this->_platform->_newOrder['logi_no']          = $this->_platform->_ordersdf['shipping']['shipping_id'];
            }
        }
        
        $payment_cfg = $this->_platform->get_payment($this->_platform->_ordersdf['pay_bn'],$this->_platform->__channelObj->channel['node_type']);
        $master['pay_bn'] = $payment_cfg['pay_bn'];

        # 支付单结构
        $payment_list = isset($this->_platform->_ordersdf['payments']) ? $this->_platform->_ordersdf['payments'] : array($this->_platform->_ordersdf['payment_detail']);
        if ($payment_list 
            && is_array($payment_list) 
            && $this->_platform->_ordersdf['payed'] >= $this->_platform->_tgOrder['payed']
            && in_array($this->_platform->_tgOrder['pay_status'], array('0','3','8')) ) {
            
            $last_payment = array_pop($payment_list);
            $master['paytime'] = $last_payment['pay_time'] ? kernel::single('ome_func')->date2time($last_payment['pay_time']) : time();
        }

        $master = array_filter($master,array($this,'filter_null'));

        $diff_master = array_udiff_assoc($master, $this->_platform->_tgOrder,array($this,'comp_array_value'));

        if ($diff_master) {
            $this->_platform->_newOrder = array_merge($this->_platform->_newOrder,$diff_master);

            if (in_array($this->_platform->_newOrder['pay_status'], array('6','7'))) {
               $this->_platform->_newOrder['pause'] = 'true';
            }      
        }

        $payinfo = array();
        $payinfo['pay_name']     = $this->_platform->_ordersdf['payinfo']['pay_name'];
        $payinfo['cost_payment'] = $this->_platform->_ordersdf['payinfo']['cost_payment'];
        $payinfo = array_filter($payinfo,array($this,'filter_null'));
        $diff_payinfo = array_udiff_assoc($payinfo, $this->_platform->_tgOrder['payinfo'],array($this,'comp_array_value'));
        if ($diff_payinfo) {
            $this->_platform->_newOrder['payinfo'] = array_merge((array)$this->_platform->_newOrder['payinfo'],$diff_payinfo);
        }
    
        // 天猫物流升级
        if ($this->_platform->_ordersdf['order_type'] == 'presale' && $this->_platform->_ordersdf['cn_info']['asdp_biz_type'] == 'logistics_upgrade'){
            $this->_platform->_newOrder['order_bool_type']           = $this->_platform->_tgOrder['order_bool_type'] | ome_order_bool_type::__CPUP_CODE;
            $this->_platform->_newOrder['shipping']['shipping_name'] = $this->_platform->_ordersdf['shipping']['shipping_name'];
        
            if ($this->_platform->_ordersdf['cpuperr']) {
                $this->_platform->_newOrder['abnormal_status'] = ome_preprocess_const::__CPUPAB_CODE;
            }
        }
        
        //logistics_infos
        if($this->_platform->_ordersdf['logistics_infos']){
            //转换为数组(矩阵单拉订单是数组,主推订单是json字符串)
            if(is_string($this->_platform->_ordersdf['logistics_infos'])){
                $this->_platform->_ordersdf['logistics_infos'] = json_decode($this->_platform->_ordersdf['logistics_infos'], true);
            }
        }
        
    }
}
