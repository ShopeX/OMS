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
 * 
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_matrix_vop_response_order extends erpapi_shop_response_order
{
    protected $_update_accept_dead_order = true;

    protected function get_update_components()
    {
        $components = array('markmemo','custommemo','marktype');

        if ( ($this->_ordersdf['pay_status'] != $this->_tgOrder['pay_status']) || ($this->_ordersdf['shipping']['is_cod']=='true' && $this->_ordersdf['status'] == 'dead') ) {
            $refundApply = app::get('ome')->model('refund_apply')->getList('apply_id',array('order_id'=>$this->_tgOrder['order_id'],'status|noequal'=>'3'));
            // 如果没有退款申请单，以前端为主
            if (!$refundApply) {
                $components[] = 'master';
            }
        }

        // 更新收货地址
        $rs = app::get('ome')->model('order_extend')->getList('extend_status',array('order_id'=>$this->_tgOrder['order_id']));
        if ($rs[0]['extend_status'] != 'consignee_modified') {
            $components[] = 'consignee';
        }

        if($this->_ordersdf['is_risk'] == 'false' && $this->_tgOrder['is_delivery']=='N'){
            $components[] = 'master';
        }
        return $components;
    }

    protected function _analysis()
    {
        $title = $this->_ordersdf['title']; // 父类_analysis会处理title，所以先赋值给变量
        parent::_analysis();

        if ($this->_ordersdf['status'] == 'dead' && $this->_ordersdf['shipping']['is_cod'] != 'true') $this->_ordersdf['pay_status'] = '5';
       
        if($this->_ordersdf['sale_type'] == '114') {
            $this->_ordersdf['order_type'] = 'vopczc';
        }
        $isJITX = false;
        if($title == '唯品会jitx订单') {
            $isJITX = true;
        }

        if($isJITX) {
            $this->_ordersdf['order_bool_type'] = intval($this->_ordersdf['order_bool_type']) |  ome_order_bool_type::__JITX_CODE;

            if ( $this->_ordersdf['is_risk'] == 'true' ){
                $this->_ordersdf['order_bool_type'] = $this->_ordersdf['order_bool_type'] | ome_order_bool_type::__RISK_CODE;
            }

            if ($this->_ordersdf['extend_field']) {
                // jitx合单码
                if ($this->_ordersdf['extend_field']['merged_code']) {
                    $this->_ordersdf['vop_merged_code'] = $this->_ordersdf['extend_field']['merged_code'];
                }
                // 是否有优先发货,当天17点前流入订单，在当天20点前发货并且被揽收
                if ($this->_ordersdf['extend_field']['action_list'] && !$this->_ordersdf['promised_collect_time']) {
                    foreach ($this->_ordersdf['extend_field']['action_list'] as $k => $action_list) {
                        if ($action_list['action_code'] == 'priority_delivery') {
                            $this->_ordersdf['promised_collect_time'] = $this->_ordersdf['createtime']>strtotime('17:00:00')?strtotime('tomorrow 20:00:00'):strtotime('20:00:00');
                        }
                    }
                }
            }

            // 获取货号
            foreach ($this->_ordersdf['order_objects'] as $objkey => &$object) {

                // 唯品会省仓有预选仓，需要转换成oms本地对应仓，否则回写库存的时候，订单级的预占统计不到
                if ($object['store_code']) {
                    $branchId = app::get('ome')->model('branch_relation')->db_dump(['type'=>'vopjitx','relation_branch_bn'=>$object['store_code']]);
                    if ($branchId) {
                        $branchInfo = app::get('ome')->model('branch')->db_dump(['check_permission'=>'false','branch_id'=>$branchId['branch_id']]);
                        $branchInfo && $object['store_code'] = $branchInfo['branch_bn'];
                    }
                }

                $bn = kernel::single('material_codebase')->getBnBybarcode($object['shop_goods_id']);
                
                if(empty($bn)) {
                    continue;
                }
                foreach ($object['order_items'] as $k => &$v) {
                    $v['bn']      = $bn;
                    $object['bn'] = $bn;
                }
            }
        }
        
        //is_delivery
        if($this->_ordersdf['is_risk'] && in_array($this->_ordersdf['is_risk'],array('true','false')) ){
            $this->_ordersdf['is_delivery']= $this->_ordersdf['is_risk'] == 'true' ? 'N' : 'Y';
        }
    
        // 唯品会，如果有merged_code,传merged_code，ome_mdl_order->create_order用到
        if ($this->_ordersdf['vop_merged_code']) {
            $this->_ordersdf['extend_field']['merged_code'] = $this->_ordersdf['vop_merged_code'];
        }
    
        //"r_time": ["送货时间不限"], 数组会序列化
        if($this->_ordersdf['consignee'] && isset($this->_ordersdf['consignee']['r_time']) && is_array($this->_ordersdf['consignee']['r_time'])) {
            $this->_ordersdf['consignee']['r_time'] = !empty($this->_ordersdf['consignee']['r_time']) ? $this->_ordersdf['consignee']['r_time'][0] : '';
        }
        
        // 唯品会JITX补寄订单
        $getBufaResult = kernel::single('ome_order_platform_vop_bufa')->validateBufaOrder($this->_ordersdf, $this->__channelObj->channel);
        if($getBufaResult['rsp'] == 'succ'){
            // flag
            $this->_ordersdf['is_bufa_order'] = 'true';
            
            // 补发订单类型
            $this->_ordersdf['order_type'] = 'bufa';
            
            // 关联订单号（原主订单号）
            if(isset($getBufaResult['data']['relate_order_bn']) && $getBufaResult['data']['relate_order_bn']){
                $this->_ordersdf['relate_order_bn'] = $getBufaResult['data']['relate_order_bn'];
            }
            
//            // 平台单号
//            if(isset($getBufaResult['data']['platform_order_bn']) && $getBufaResult['data']['platform_order_bn']){
//                $this->_ordersdf['platform_order_bn'] = $getBufaResult['data']['platform_order_bn'];
//            }
            
            // 商家备注
            if(isset($getBufaResult['data']['mark_text']) && $getBufaResult['data']['mark_text']){
                $this->_ordersdf['mark_text'] = $getBufaResult['data']['mark_text'];
            }
            
            // OMS物流公司ID
            if(isset($getBufaResult['data']['logi_id']) && $getBufaResult['data']['logi_id']){
                $this->_ordersdf['shipping']['logi_id'] = $getBufaResult['data']['logi_id'];
            }
            
            // OMS物流公司编码
            if(isset($getBufaResult['data']['logi_code']) && $getBufaResult['data']['logi_code']){
                $this->_ordersdf['shipping']['logi_code'] = $getBufaResult['data']['logi_code'];
            }
        }
    }

    protected function get_create_plugins()
    {
        $plugins = parent::get_create_plugins();

        $plugins[] = 'waybill';
        $plugins[] = 'orderextend';
        $plugins[] = 'orderlabels';
        $plugins[] = 'checkitems';
        $plugins[] = 'inventory'; //唯品会实时销售订单提前预占库存
        
        return $plugins;
    }

    protected function get_update_plugins()
    {
        $plugins = parent::get_update_plugins();
        $plugins[] = 'waybill';
        $plugins[] = 'orderextend';
        $plugins[] = 'checkitems';

        return $plugins;
    }
}
