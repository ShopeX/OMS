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
 * @author ykm 2016/12/15
 * @describe 订单处理
 */

class erpapi_shop_matrix_pinduoduo_response_order extends erpapi_shop_response_order
{
    //平台订单状态
    protected $_sourceStatus = array(
        '1'  => 'WAIT_SELLER_SEND_GOODS', //待发货
        '2'  => 'WAIT_BUYER_CONFIRM_GOODS', //已发货待签收
        '3'  => 'TRADE_FINISHED', //已签收
    );
    
    protected $_update_accept_dead_order = true;

    public function _securityHashCode(){
        $this->_ordersdf['member_info']['buyer_open_uid'] = $this->_ordersdf['index_field']['open_address_id'];
        parent::_securityHashCode();
    }

    protected function _analysis()
    {
        parent::_analysis();

        if($this->_ordersdf['consignee']['area_city'] == '县') {
            $this->_ordersdf['consignee']['area_city'] = $this->_ordersdf['consignee']['area_state'];
        }

        // 拼多多顺丰加价订单强制发顺丰
        if (0 === strpos($this->_ordersdf['mark_text'],'顺丰加价;')) {
            $this->_ordersdf['shipping']['shipping_name'] = '顺丰';
        }
    
        // 拼多多集运标识转成oms本地标识
        if (isset($this->_ordersdf['extend_field']['consolidate_info'])
            && isset($this->_ordersdf['extend_field']['consolidate_info']['consolidate_type'])) {
            $pddConsolidateType = (string)$this->_ordersdf['extend_field']['consolidate_info']['consolidate_type'];
        
            // 国内集运映射：0-香港(0x0040), 1-新疆(0x0001), 3-西藏(0x0002), 6-台湾(0x0080), 14-甘肃(0x0004), 15-内蒙古(0x0008), 16-宁夏(0x0010), 17-青海(0x0020), 18-澳门(0x0100)
            $domesticMap = [
                '0'  => 0x0040, // 香港
                '1'  => 0x0001, // 新疆
                '3'  => 0x0002, // 西藏
                '6'  => 0x0080, // 台湾
                '14' => 0x0004, // 甘肃
                '15' => 0x0008, // 内蒙古
                '16' => 0x0010, // 宁夏
                '17' => 0x0020, // 青海
                '18' => 0x0100, // 澳门
            ];
        
            // 国外集运映射：2,5,7-13,19-25
            $overseasMap = [
                '2'  => 0x0001,   // 哈萨克斯坦
                '5'  => 0x0002,   // 日本
                '7'  => 0x0004,   // 韩国
                '8'  => 0x0008,   // 新加坡
                '9'  => 0x0010,   // 马来西亚
                '10' => 0x0020,   // 泰国
                '11' => 0x0040,   // 越南
                '12' => 0x0080,   // 吉尔吉斯斯坦
                '13' => 0x0100,   // 乌兹别克斯坦
                '19' => 0x0200,   // 柬埔寨
                '20' => 0x0400,   // 老挝
                '21' => 0x0800,   // 塔吉克斯坦
                '22' => 0x1000,   // 亚美尼亚
                '23' => 0x2000,   // 格鲁吉亚
                '24' => 0x4000,   // 蒙古
                '25' => 0x8000,   // 加拿大
            ];
        
            if (isset($domesticMap[$pddConsolidateType])) {
                $this->_ordersdf['extend_field']['consolidate_info'] = [
                    'consolidate_type'  => 'SOMS_GNJY',
                    'consolidate_value' => $domesticMap[$pddConsolidateType],
                ];
            } elseif (isset($overseasMap[$pddConsolidateType])) {
                $this->_ordersdf['extend_field']['consolidate_info'] = [
                    'consolidate_type'  => 'SOMS_GYJY',
                    'consolidate_value' => $overseasMap[$pddConsolidateType],
                ];
            }
        }
        $this->_ordersdf['is_delivery']= 'Y';
     
        if ($this->_ordersdf['extend_field']) {
            // 国补
            if ($gov_subsidy = $this->_ordersdf['extend_field']['gov_subsidy']) {
                $this->_ordersdf['guobu_info'] = [];

                if ($gov_subsidy['trade_in_national_subsidy_amount_type'] == '1') {

                    $this->_ordersdf['guobu_info']['use_gov_subsidy_new'] = true;
                    $this->_ordersdf['guobu_info']['guobu_type'][] = 1; // 支付立减
                    $this->_ordersdf['guobu_info']['gov_subsidy_amount_new'] = $gov_subsidy['trade_in_national_subsidy_amount'];

                } elseif ($gov_subsidy['trade_in_national_subsidy_amount_type'] == '2') {

                    $this->_ordersdf['guobu_info']['use_gov_subsidy_new'] = true;
                    $this->_ordersdf['guobu_info']['guobu_type'][] = 2; // 下单立减
                    $this->_ordersdf['guobu_info']['gov_subsidy_amount_new'] = $gov_subsidy['trade_in_national_subsidy_amount'];
                }

                if ($this->_ordersdf['guobu_info']) {
                    $this->_ordersdf['guobu_info']['order_tag_list'] = $this->_ordersdf['extend_field']['order_tag_list'];
                }
            }

            
            if($this->_ordersdf['extend_field']['order_tag_list']){

                foreach($this->_ordersdf['extend_field']['order_tag_list'] as $v){
                    if($v['name']=='bought_from_vegetable' && $v['value']==1){

                        $this->_ordersdf['order_bool_type'] = ome_order_bool_type::__CPUP_CODE;
                        $this->_ordersdf['cpup_service'] = '204';
                    }
                    if($v['name']=='promise_delivery' && $v['value']==1){
                        $this->_ordersdf['logictics_labels'][]=
                        ['label_code'=>'SOMS_LOGISTICS','label_value'=>0x0004];
                    }
                }

                
            }

           if(isset($this->_ordersdf['extend_field']['gift_order_status']) && in_array($this->_ordersdf['extend_field']['gift_order_status'],['0','1','2'])){

                if($this->_ordersdf['extend_field']['gift_order_status']==0){
  

                    $this->_ordersdf['is_delivery']= 'N';
                }

               
           }



        }


        //is_delivery
        if($this->_ordersdf['is_risk'] && in_array($this->_ordersdf['is_risk'],array('true','false')) ){
            if($this->_ordersdf['is_risk'] == 'true'){
                $this->_ordersdf['is_delivery']= 'N';
            }
            

        }

        // 拼多多平台优惠金额
        if ($this->_ordersdf['platform_discount']) {
            $this->_ordersdf['platform_cost_amount'] = $this->_ordersdf['platform_discount'];
        }

    }

    protected function get_update_plugins()
    {
        $plugins = parent::get_update_plugins();

        //判断如果是已完成只更新时间
        if ($this->_ordersdf['status'] == 'finish' && $this->_ordersdf['end_time']>0){
            $plugins = array();
            $plugins[] = 'confirmreceipt';
        }
        
         if($this->_ordersdf['is_delivery']=='Y'){
            $plugins[] = 'orderlabels';
            
        }

        return $plugins;
    }

    protected function get_update_components()
    {
        $components = array('markmemo','marktype','custommemo');
        if (($this->_ordersdf['pay_status'] != $this->_tgOrder['pay_status']) ||($this->_ordersdf['shipping']['is_cod']=='true' && $this->_ordersdf['status'] == 'dead')) {
        	$refundApply = app::get('ome')->model('refund_apply')->getList('apply_id',array('order_id'=>$this->_tgOrder['order_id'],'status|noequal'=>'3'));
        	if (!$refundApply) {

            	$components[] = 'master';
        	}
        }
        if($this->_tgOrder['order_bool_type'] & ome_order_bool_type::__RISK_CODE) {
            $components[] = 'member';
        }
        if($this->_tgOrder && $this->_ordersdf['consignee']['name']){
            $rs = app::get('ome')->model('order_extend')->getList('extend_status',array('order_id'=>$this->_tgOrder['order_id']));
            // 如果ERP收货人信息未发生变动时，则更新拼多多收货人信息
            if ($rs[0]['extend_status'] != 'consignee_modified') {
    
                $shouldUpdateConsignee = true;
    
                // 集运订单地址更新控制：如果集运仓没有变化，不更新地址
                if ($this->_tgOrder['consignee']['addr']
                    && $this->_ordersdf['extend_field']['consolidate_info']
                    && isset($this->_ordersdf['extend_field']['consolidate_info']['consolidate_type'])
                    && isset($this->_ordersdf['extend_field']['consolidate_info']['consolidate_value'])) {
                    $newConsolidateValue = $this->_ordersdf['extend_field']['consolidate_info']['consolidate_value'];
                    $newConsolidateType = $this->_ordersdf['extend_field']['consolidate_info']['consolidate_type'];
        
                    // 获取旧订单的集运标签
                    $oldLabels = kernel::single('ome_bill_label')->getLabelFromOrder($this->_tgOrder['order_id'], 'order');
                    $oldConsolidateLabel = null;
                    foreach ($oldLabels as $label) {
                        if (in_array($label['label_code'], ['SOMS_GNJY', 'SOMS_GYJY'])) {
                            $oldConsolidateLabel = $label;
                            break;
                        }
                    }
        
                    // 如果旧订单有集运标签，且小标值相同，说明集运仓没变化，不更新地址
                    if ($oldConsolidateLabel && isset($oldConsolidateLabel['label_value'])) {
                        if ($oldConsolidateLabel['label_value'] == $newConsolidateValue && $oldConsolidateLabel['label_code'] == $newConsolidateType) {
                            $shouldUpdateConsignee = false;
                        }
                    } else {
                        // 如果旧订单没有集运标签（可能是旧格式编码或第一次创建），不更新地址
                        $shouldUpdateConsignee = false;
                    }
                }
                
                if ($shouldUpdateConsignee) {
                    $orRe = app::get('ome')->model('order_receiver')->db_dump(['order_id'=>$this->_tgOrder['order_id']], 'encrypt_source_data');
                    $ensd = json_decode($orRe['encrypt_source_data'], 1);
                    if(empty($ensd['open_address_id']) || $ensd['open_address_id'] != $this->_ordersdf['index_field']['open_address_id'] || !$this->_tgOrder['consignee']['name']) {
                        $components[] = 'consignee';
                    }
                }
            }
        }
    
        if($this->_ordersdf['is_delivery'] == 'Y' && $this->_tgOrder['is_delivery']=='N'){
            $components[] = 'master';

        }

        if($this->_tgOrder['status']=='finish'){
            $components = [];
        }
        return $components;
    }
    
    /**
     * 是否接收订单
     *
     * @return void
     * @author
     **/
    protected function _canAccept()
    {
        if (isset($this->_ordersdf['extend_field']['group_status']) && $this->_ordersdf['extend_field']['group_status'] != '1') {
//            $this->__apilog['result']['msg'] = '未拼团成功不接收';
//            return false;
        }
        
        return parent::_canAccept();
    }


    protected function _canCreate()
    {
        $res = parent::_canCreate();
        if (!$res) {
            if ('1'!=app::get('ome')->getConf('ome.get.all.status.order')){
                return $res;
            } else {
                if ($this->__apilog['result']['msg'] == '取消订单不接收') {
                    return true;
                }
                return $res;
            }
        }
    }


    //创建订单的插件
    protected function get_create_plugins()
    {
        $plugins = parent::get_create_plugins();

        $plugins[] = 'orderlabels';
        $plugins[] = 'coupon';
        return $plugins;
    }

    protected function _operationSel()
    {
        parent::_operationSel();

        if($this->_operationSel == 'update'){
            if ($this->_ordersdf['status'] == 'dead' && $this->_tgOrder['status']=='active' &&  $this->_ordersdf['pay_status']=='5' && $this->_tgOrder['pay_status']=='4' && $this->_ordersdf['ship_status']=='0'){
                $this->_operationSel = 'close';
            }
        }
    }
}
