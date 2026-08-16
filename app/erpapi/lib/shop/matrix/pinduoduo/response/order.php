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

        // 矩阵回调原始数据不保证携带 shop_type；付费送货上门插件需据此限定拼多多订单，
        // 直接使用已通过路由校验的店铺配置，避免依赖可选的预处理服务补齐该字段。
        $this->_ordersdf['shop_type'] = $this->__channelObj->channel['shop_type'];

        // 消费者付费送货上门依赖平台服务费明细。先统一格式化服务信息，
        // 后续订单标签和物流白名单插件只读取标准结构，避免各处重复兼容矩阵嵌套数据。
        $this->_formatChargeHomeDeliveryDoor();

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
        
        // 通过平台cn_info.express_memos：获取买家拒用的快递 → buyer_black_delivery_cps
        $this->_parseBuyerRejectExpressMemos();
    }

    /**
     * 解析平台推送的买家拒用快递备注
     *
     * scene=1 且 tag 非空时，按关键字映射 dly_corp.type，写入 SDF 供 orderextend 合并进 black_delivery_cps。
     */
    private function _parseBuyerRejectExpressMemos()
    {
        // check
        if(!isset($this->_ordersdf['cn_info']) || empty($this->_ordersdf['cn_info'])){
            return false;
        }
        
        // 从 cn_info 解析买家拒用快递编码与命中 tag
        $parsed = kernel::single('ome_order_platform_pinduoduo_expressmemos')->parseFromCnInfo($this->_ordersdf['cn_info'] ?? null);
        if (empty($parsed['types'])) {
            return false;
        }
        
        // [黑名单]物流公司编码
        $this->_ordersdf['buyer_black_delivery_cps'] = $parsed['types'];
        
        if (!isset($this->_ordersdf['extend_field']) || !is_array($this->_ordersdf['extend_field'])) {
            $this->_ordersdf['extend_field'] = [];
        }
        
        $this->_ordersdf['extend_field']['pdd_express_memos'] = [
            'tags'  => $parsed['tags'],
            'types' => $parsed['types'],
        ];
        
        return true;
    }

    protected function get_update_plugins()
    {
        $plugins = parent::get_update_plugins();

        //判断如果是已完成只更新时间
        if ($this->_ordersdf['status'] == 'finish' && $this->_ordersdf['end_time']>0){
            $plugins = array();
            $plugins[] = 'confirmreceipt';
        }
        
        // flag
        $is_loading_plugins_extend = false;
        
        // 买家拒用快递黑名单同样需要在不可发货场景落库，供后续审单过滤。
        if(!empty($this->_ordersdf['buyer_black_delivery_cps'])){
            $is_loading_plugins_extend = true;
        }
        
        // 本服务的标签和白名单需要在服务取消时主动清理，不能完全依赖 is_delivery。
        // 风险单等场景会先变为 N；若历史已打标，仍需运行插件恢复其他业务白名单。
        if ($this->_ordersdf['is_delivery'] == 'Y' || $this->_needSyncChargeHomeDeliveryDoor() || $is_loading_plugins_extend) {
            $plugins[] = 'orderextend';
            $plugins[] = 'orderlabels';
        }

        // 直邮活动标签需要响应平台的 1/0 更新，不能依赖订单是否可发货。
        if ($this->_hasDirectMailActivityTag()) {
            $plugins[] = 'orderlabels';
        }

        return $plugins;
    }

    /**
     * 判断平台是否明确下发直邮活动标签
     */
    private function _hasDirectMailActivityTag()
    {
        $orderTagList = isset($this->_ordersdf['extend_field']['order_tag_list'])
            ? $this->_ordersdf['extend_field']['order_tag_list']
            : [];
        if (!is_array($orderTagList)) {
            return false;
        }

        foreach ($orderTagList as $tag) {
            if (is_array($tag)
                && isset($tag['name'])
                && $tag['name'] === 'direct_mail_activity') {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断不可配送订单是否仍需同步或清理消费者付费送货上门服务
     */
    private function _needSyncChargeHomeDeliveryDoor()
    {
        if (!empty($this->_ordersdf['charge_home_delivery_door']['active'])) {
            return true;
        }

        $orderId = $this->_tgOrder['order_id'] ?? 0;
        if (!$orderId) {
            return false;
        }

        $oldExtend = app::get('ome')->model('order_extend')->db_dump(
            ['order_id' => $orderId],
            'extend_field'
        );
        $oldExtendField = $oldExtend['extend_field']
            ? json_decode($oldExtend['extend_field'], true)
            : [];
        if (!empty($oldExtendField['charge_home_delivery_door_oms'])) {
            return true;
        }

        // 兼容历史执行中扩展信息保存失败、但标签已经写入的半完成状态。
        $labelInfo = kernel::single('ome_bill_label')->getBillLabelInfo(
            $orderId,
            'order',
            'SOMS_ZFSHSM'
        );
        return !empty($labelInfo);
    }

    /**
     * 格式化拼多多消费者付费送货上门服务信息
     *
     * 矩阵的 service_fee_detail 可能是数组、嵌套数组或 JSON 字符串。
     * 这里只识别 charge_home_delivery_door，并将有效服务费汇总成 OMS 标准结构，
     * 供订单标签打标、费用保存和审单物流白名单处理共同使用。
     */
    private function _formatChargeHomeDeliveryDoor()
    {
        $serviceFee = 0;
        $serviceDetail = $this->_ordersdf['extend_field']['service_fee_detail'] ?? [];
        $this->_collectChargeHomeDeliveryDoorFee($serviceDetail, $serviceFee);

        $serviceFee = round($serviceFee, 2);
        $serviceInfo = [
            'active'             => $serviceFee > 0,
            'service_name'       => 'charge_home_delivery_door',
            'service_fee'        => number_format($serviceFee, 2, '.', ''),
            'white_delivery_cps' => ['ZTO', 'YTO', 'STO', 'jitu', 'YUNDA'],
        ];
        $this->_ordersdf['charge_home_delivery_door'] = $serviceInfo;

        if ($serviceInfo['active']) {
            $this->_ordersdf['extend_field']['charge_home_delivery_door_oms'] = $serviceInfo;
        } else {
            unset($this->_ordersdf['extend_field']['charge_home_delivery_door_oms']);
        }
    }

    /**
     * 递归汇总消费者付费送货上门服务费
     *
     * @param mixed $serviceDetail
     * @param float $serviceFee
     */
    private function _collectChargeHomeDeliveryDoorFee($serviceDetail, &$serviceFee)
    {
        if (is_string($serviceDetail)) {
            $decoded = json_decode($serviceDetail, true);
            if (!is_array($decoded)) {
                return;
            }
            $serviceDetail = $decoded;
        }

        if (!is_array($serviceDetail)) {
            return;
        }

        if (isset($serviceDetail['service_name'])) {
            if ($serviceDetail['service_name'] == 'charge_home_delivery_door'
                && is_numeric($serviceDetail['service_fee'])
                && $serviceDetail['service_fee'] > 0) {
                $serviceFee += (float)$serviceDetail['service_fee'];
            }
            return;
        }

        foreach ($serviceDetail as $detail) {
            $this->_collectChargeHomeDeliveryDoorFee($detail, $serviceFee);
        }
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
