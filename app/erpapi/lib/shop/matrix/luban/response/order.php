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

class erpapi_shop_matrix_luban_response_order extends erpapi_shop_response_order
{
    protected $_update_accept_dead_order = true;

    //平台订单状态
    protected $_sourceStatus = array(
        '1' => 'WAIT_BUYER_PAY',  //待确认/待支付（订单创建完毕）
        '105' => 'WAIT_SELLER_SEND_GOODS', //已支付
        '2' => 'SELLER_READY_GOODS',  //备货中
        '101' => 'SELLER_CONSIGNED_PART', //部分发货
        '3' => 'WAIT_BUYER_CONFIRM_GOODS',  //已发货（全部发货）
        '4' => 'TRADE_CLOSED',  //已取消
        '5' => 'TRADE_FINISHED',  //已完成（已收货）
    );
    //平台优惠名称
    protected $couponTypeName = [
        'promotion_talent_amount' => '达人优惠金额',
        'pay_amount' => '支付金额',
        'promotion_pay_amount' => '支付优惠金额',
        'promotion_platform_amount' => '平台优惠金额',
        'shop_cost_amount' => '商家承担金额',
        'promotion_shop_amount' => '店铺优惠金额',
        'promotion_redpack_platform_amount' => '平台红包优惠金额',
        'origin_amount' => '商品现价',
        'order_amount' => '订单金额',
        'promotion_amount' => '订单优惠总金额',
        'promotion_redpack_amount' => '红包优惠金额',
        'platform_cost_amount' => '平台承担金额',
        'author_cost_amount' => '作者（达人）承担金额',
    ];
    
    /**
     * _canAccept
     * @return mixed 返回值
     */
    public function _canAccept()
    {
        $presalesetting = app::get('ome')->getConf('ome.order.presale');
        
        if(in_array($this->_ordersdf['trade_type'],array('step')) || in_array($this->_ordersdf['t_type'],array('step'))){
            $this->_ordersdf['order_type'] = 'presale';
        }
        
        if(app::get('presale')->is_installed() && $presalesetting == '1' && $this->_ordersdf['order_type'] == 'presale'){
            if(in_array($this->_ordersdf['step_trade_status'],array('FRONT_PAID_FINAL_NOPAID'))){
                $this->_accept_unpayed_order = true;
            }
        }
        
        if(($this->_accept_unpayed_order==false && in_array($this->_ordersdf['step_trade_status'],array('FRONT_NOPAID_FINAL_NOPAID','FRONT_PAID_FINAL_NOPAID'))) || ($this->_accept_unpayed_order == true && in_array($this->_ordersdf['step_trade_status'],array('FRONT_NOPAID_FINAL_NOPAID')))){
            $this->__apilog['result']['msg'] = '定金未付尾款未付或定金已付尾款未付订单不接收';
            return false;
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

    protected function get_update_plugins()
    {
        $plugins = parent::get_update_plugins();

        //判断如果是已完成只更新时间
        if ($this->_ordersdf['status'] == 'finish' && $this->_ordersdf['end_time']>0){
            $plugins = array();
            $plugins[] = 'confirmreceipt';
        }

        if ( (in_array($this->_tgOrder['order_type'], array('presale'))) 
                && ($this->_tgOrder['pay_status'] == '3' || $this->_tgOrder['total_amount'] != $this->_ordersdf['total_amount'])
                && $this->_tgOrder['cost_item'] == $this->_ordersdf['cost_item'] 
                && $this->_ordersdf['step_trade_status'] == 'FRONT_PAID_FINAL_PAID')
            {
            $plugins[] = 'payment';
        }
        $plugins[] = 'orderlabels'; // 更新抖音小店订单可更新中转集运标签
        
        return $plugins;
    }

    protected function get_update_components()
    {
        $components = array('markmemo','marktype','custommemo','booltype');

        if (($this->_ordersdf['pay_status'] != $this->_tgOrder['pay_status']) ||($this->_ordersdf['shipping']['is_cod']=='true' && $this->_ordersdf['status'] == 'dead')) {
            $refundApply = app::get('ome')->model('refund_apply')->getList('apply_id',array('order_id'=>$this->_tgOrder['order_id'],'status|noequal'=>'3'));
            // 如果没有退款申请单，以前端为主
            if (!$refundApply) {
                $components[] = 'master';
            }
        }

        // 更新收货地址
        $rs = app::get('ome')->model('order_extend')->getList('extend_status',array('order_id'=>$this->_tgOrder['order_id']));
        if ($rs[0]['extend_status'] != 'consignee_modified') {
            $orRe = app::get('ome')->model('order_receiver')->db_dump(['order_id'=>$this->_tgOrder['order_id']], 'encrypt_source_data');
            $ensd = json_decode($orRe['encrypt_source_data'], 1);
            if(empty($ensd['open_address_id']) || $ensd['open_address_id'] != $this->_ordersdf['index_field']['open_address_id']) {
                $components[] = 'consignee';
            }
        }
        
        //查看是否预售订单已支付状态
        if(in_array($this->_tgOrder['order_type'], array('presale'))){
            $components[] = 'tbpresale';
        }
        
        if ( (in_array($this->_tgOrder['order_type'], array('presale'))) 
                && ($this->_tgOrder['pay_status'] == '3' || $this->_tgOrder['total_amount'] != $this->_ordersdf['total_amount'])
                && $this->_tgOrder['cost_item'] == $this->_ordersdf['cost_item'] 
                && $this->_ordersdf['step_trade_status'] == 'FRONT_PAID_FINAL_PAID')
            {
            if(!array_search('master', $components)) $components[] = 'master';
            $components[] = 'items';
        }

        if($this->_tgOrder['status']=='finish'){
            $components = [];
        }
        return $components;
    }
    
    //创建订单的插件
    protected function get_create_plugins()
    {
        $plugins = parent::get_create_plugins();
        
        $plugins[] = 'orderextend';
        
        //费用明细（比如佣金、附加费）
        array_push($plugins,'settle','coupon');
        
        $plugins[] = 'orderlabels';
        return $plugins;
    }
    
    protected function _analysis()
    {
        parent::_analysis();
        if ($this->_ordersdf['member_info']['buyer_open_uid']) {
            $this->_ordersdf['member_info']['uname'] = $this->_ordersdf['member_info']['buyer_open_uid'];
        }
        
        $this->_ordersdf['shipping']['shipping_name'] = '';

        // 虚拟发货
        if ($this->_ordersdf['shipping']['is_virtual_delivery'] == 'true') {
            $this->_ordersdf['shipping']['shipping_name'] = 'virtual_delivery';
        }
        
        //订单没有明细,直接返回
        if(empty($this->_ordersdf['order_objects']) && !is_array($this->_ordersdf['order_objects'])){
            return true;
        }

        // 抖音券包类商品可能不下发商家编码，先用平台ID兜底，避免失败订单无法人工匹配销售物料。
        $this->_fillEmptyBnWithPlatformIds();
        
        //抖音订单扩展信息
        $authoList = array();
        $warehouseList = array();
        if($this->_ordersdf['extend_field']){
            //主播信息
            if($this->_ordersdf['extend_field']['author_info']){
                foreach ($this->_ordersdf['extend_field']['author_info'] as $oid => $orderVal)
                {
                    if($orderVal['author_id']){
                        $authoList[$oid]['author_id'] = $orderVal['author_id'];
                    }
                    
                    if($orderVal['author_name']){
                        $authoList[$oid]['author_name'] = $orderVal['author_name'];
                    }

                    if($orderVal['room_id']){
                        $authoList[$oid]['room_id'] = $orderVal['room_id'];
                    }
                }
            }
            // 订单达人
            if ($authoList) {
                $this->_ordersdf['extend_field']['is_host'] = true;
            }

            //抖音增值服务
            $dy_added_service = '';
            if($this->_ordersdf['extend_field']['platform_order_tag_ui']){
                foreach($this->_ordersdf['extend_field']['platform_order_tag_ui'] as $tag_val){
                    $dy_added_service .= $tag_val['key'].',';
                    
                    //抽奖类型订单 只打标自发订单
                    if ($tag_val['key'] == 'draw_lottery' && $this->__channelObj->channel['delivery_mode'] == 'self') {
                        $this->_ordersdf['extend_field']['is_lucky_flag'] = true;
                    }
                    // 集运标识转成oms本地标识（二期）
                    // 改用platform_order_tag_ui识别，
                    // key=logistics_transit是偏远中转；key=remote_derict是偏远直邮,偏远直邮不用打标
                    if ($tag_val['key'] == 'logistics_transit') {
                        $consolList = [
                            '1'  => 'XJJY', // 中国新疆中转
                        ];
                        if (!$this->_ordersdf['extend_field']['consolidate_info']) {
                            $this->_ordersdf['extend_field']['consolidate_info'] = [];
                        }
                        $this->_ordersdf['extend_field']['consolidate_info']['consolidate_type'] = $consolList['1'];
                    }
                }
            }
            if(!empty($dy_added_service)){
                $this->_ordersdf['extend_field']['dy_added_service'] = substr($dy_added_service,0,strlen($dy_added_service)-1);
            }
            
            //指定仓发货
            if($this->_ordersdf['extend_field']['store_info']){
                $warehouseMdl   = app::get('logisticsmanager')->model('warehouse_shop');
                $branchMdl      = app::get('ome')->model('branch');

                foreach ($this->_ordersdf['extend_field']['store_info'] as $oid => $orderVal)
                {
                    if(empty($orderVal['inventory_list'])){
                        continue;
                    }
                    
                    foreach ($orderVal['inventory_list'] as $waKey => $waVal){
                        // 兼容 out_warehouse_id
                        if (!$waVal['out_warehouse_id'] && $waVal['warehouse_id']) {
                            $local_warehouse = $warehouseMdl->db_dump(['outwarehouse_id' => $waVal['warehouse_id'], 'shop_id' => $this->__channelObj->channel['shop_id']], 'branch_id');

                            $branch = $branchMdl->db_dump(intval($local_warehouse['branch_id']), 'branch_bn');

                            $waVal['out_warehouse_id'] = $branch['branch_bn'];
                        }

                        if($waVal['warehouse_id']){
                            $warehouseList[$oid]['warehouse_ids'][] = $waVal['warehouse_id'];
                        }
                        
                        if($waVal['out_warehouse_id']){
                            $warehouseList[$oid]['out_warehouse_ids'][] = $waVal['out_warehouse_id'];
                        }
                    }
                }
            }

            // 商品单标签，只要有一个商品是顺丰包邮，则整单打顺丰包邮的标签
            if($this->_ordersdf['extend_field']['sku_order_tag_ui']){
                foreach ($this->_ordersdf['extend_field']['sku_order_tag_ui'] as $oid => $skuVal) {
                    foreach ($skuVal as $sk => $sv) {
                        $sv['extra'] = is_string($sv['extra']) ? json_decode($sv['extra'], true) : [];
                        switch ($sv['key']) {
                            case 'sf_free_shipping':
                                $this->_ordersdf['sf_free_shipping'] = 'true';
                                break;
                            case 'PriorityDelivery':
                                if ($sv['extra'] && $sv['extra']['suggestDeliveryUnix']) {
                                    $this->_ordersdf['latest_delivery_time'] = $sv['extra']['suggestDeliveryUnix'];
                                }
                                break;
                            case 'high_quality_express':
                                if ($sv['extra'] && $sv['extra']['express_company_code']) {
                                    $this->_ordersdf['biz_delivery_code'] = json_encode(is_string($sv['extra']['express_company_code']) ? [$sv['extra']['express_company_code']] : $sv['extra']['express_company_code']);
                                }
                                break;
                            case 'paid_shunfeng_express':
                            case 'paid_shunfeng_express_tk':

                                $this->_ordersdf['shipping_name'] = 'SF';
                                $this->_ordersdf['assign_express_code'] = 'SF';
                                break;
                        }
                    }
                }
            }

            if ($this->_ordersdf['sf_free_shipping'] == 'true') {
                $dly = app::get('ome')->model('dly_corp')->db_dump([
                    'type|in'     => ['shunfeng', 'shunfengkuaiyun','SF'],
                    'disabled' => 'false',
                ]);
                if ($dly){
                    $this->_ordersdf['shipping']['shipping_name'] = $dly['type'];
                } else {
                    $this->_ordersdf['shipping']['shipping_name'] = 'shunfeng';
                }
            }

            /*
            // 集运标识转成oms本地标识
            if ($this->_ordersdf['extend_field']['consolidate_info']) {

                $consolList = [
                    '1'  => 'XJJY', // 中国新疆中转
                    // '2'  => 'XZJY', // 中国西藏中转 现在只有新疆中转
                ];
                $consolType = $this->_ordersdf['extend_field']['consolidate_info']['consolidate_type'];
                if ($consolList[$consolType]) {
                    $this->_ordersdf['extend_field']['consolidate_info']['consolidate_type'] = $consolList[$consolType];
                }
            }
            */
            
            //自选快递订单履约发货
            if (is_array($this->_ordersdf['extend_field']['order_tag']) && isset($this->_ordersdf['extend_field']['order_tag']['shop_optional_express_info'])) {
                $shop_optional_express_info = $this->_ordersdf['extend_field']['order_tag']['shop_optional_express_info'];
                if (is_string($shop_optional_express_info)) {
                    $this->_ordersdf['extend_field']['order_tag']['shop_optional_express_info'] = @json_decode($shop_optional_express_info, true);
                }
                foreach ($this->_ordersdf['extend_field']['order_tag']['shop_optional_express_info']['ExpressCompanys'] as $val) {
                    if(isset($val['ExpressCompanyCode']) && $val['ExpressCompanyCode']){
                        $this->_ordersdf['shipping']['shipping_name'] = $val['ExpressCompanyCode'];
                        break;
                    }
                }
            }

            // 国补
            if ($sku_order_tag_ui = $this->_ordersdf['extend_field']['sku_order_tag_ui']) {
                $this->_ordersdf['guobu_info'] = [];
                foreach ($sku_order_tag_ui as $_oid => $_sotag) {
                    if ($_sotag['key'] == 'gov_subsidy') {
                        $this->_ordersdf['guobu_info']['use_gov_subsidy_new'] = true;
                        $this->_ordersdf['guobu_info']['guobu_type'][] = 1; // 支付立减 (临时给支付立减类型)
                    }
                }

                if ($this->_ordersdf['guobu_info']) {
                    if ($this->_ordersdf['coupon_field']) {
                        $this->_ordersdf['guobu_info']['coupon_field'] = $this->_ordersdf['coupon_field'];
                    }
                    foreach ($this->_ordersdf['order_objects'] as $k => $_object) {
                        foreach ( $_object['order_items'] as  $_item ){
                            if (isset($_item['extend_item_list']['gov_subsidy_detail']) && $_item['extend_item_list']['gov_subsidy_detail']) {
                                if ($_item['extend_item_list']['gov_subsidy_detail']['product_tag'] == 'government_subsidy_multis') {
                                    $this->_ordersdf['guobu_info']['use_gov_subsidy_new'] = true;
                                    $this->_ordersdf['guobu_info']['guobu_type'][] = 4; // 一品卖多地
                                }

                                $this->_ordersdf['guobu_info']['gov_subsidy_detail'][$_object['oid']][] = $_item['extend_item_list']['gov_subsidy_detail'];
                            }
                            // 国补 唯一码获取
                            if (isset($_item['extend_item_list']['serial_no_info']) && $_item['extend_item_list']['serial_no_info']) {
                                $this->_ordersdf['guobu_info']['serial_no_info'] = $_item['extend_item_list']['serial_no_info'];
                            }
                        }
                    }
                    if ($this->_ordersdf['pmt_orders']) {
                        if ($this->_ordersdf['pmt_orders']['pmt_campaign_type'] && $this->_ordersdf['pmt_orders']['pmt_campaign_type'] == '416') {
                            $this->_ordersdf['guobu_info']['pmt_campaign_type'] = $this->_ordersdf['pmt_orders']['pmt_campaign_type'];
                            $this->_ordersdf['guobu_info']['gov_subsidy_amount_new'] = $this->_ordersdf['pmt_orders']['pmt_amount']/100; // 对应的补贴金额,单位是分
                        }
                    }

                }
            }

            //最晚发货时间
            if($this->_ordersdf['extend_field']['exp_ship_time']){
                $this->_ordersdf['latest_delivery_time'] = strtotime($this->_ordersdf['extend_field']['exp_ship_time']);
            }
        }
        
        //list
        if($authoList || $warehouseList){
            $ordFunLib = kernel::single('ome_order_func');
            
            $is_flag = false;
            foreach ($this->_ordersdf['order_objects'] as $key => $val)
            {
                $oid = $val['oid'];
                
                //主播信息
                if($authoList[$oid]['author_id']){
                    $this->_ordersdf['order_objects'][$key]['authod_id'] = $authoList[$oid]['author_id'];
                }
                
                if($authoList[$oid]['author_name']){
                    //主播姓名未过滤表情符,京东需要表情符
                    //@todo：需要sdb_ome_order_objects表支持utf8mb4字符集
                    $this->_ordersdf['order_objects'][$key]['author_name'] = $authoList[$oid]['author_name'];
                }

                //直播间ID
                if($authoList[$oid]['room_id']){
                    if (!$this->_ordersdf['order_objects'][$key]['addon']) {
                        $this->_ordersdf['order_objects'][$key]['addon'] = [];
                    }
                    $this->_ordersdf['order_objects'][$key]['addon']['room_id'] = $authoList[$oid]['room_id'];
                }
                
                //指定仓发货
                if($warehouseList[$oid]['warehouse_ids']){
                    $this->_ordersdf['order_objects'][$key]['warehouse_ids'] = $warehouseList[$oid]['warehouse_ids'];
                }
                
                if($warehouseList[$oid]['out_warehouse_ids']){
                    $this->_ordersdf['order_objects'][$key]['out_warehouse_ids'] = $warehouseList[$oid]['out_warehouse_ids'];
                    
                    $is_flag = true;
                }

                // // 顺丰包邮
                // if ($sf_free_shipping[$oid]) {
                //     $this->_ordersdf['order_objects'][$key]['sf_free_shipping'] = 'true';
                // }
            }
            
            //是否指定仓发货
            if($is_flag){
                $this->_ordersdf['is_assign_store'] = 'true';
            }
        }
        
        //抖音sku维度实付金额与优惠明细
        $this->_ordersdf['coupon_data'] = array();
        $oidActuallyPayMap = [];
        $oidPlatformAmountMap = [];
        if (isset($this->_ordersdf['coupon_field'])) {
            $couponData = $this->_ordersdf['coupon_field'];
            $coupon = [];
            
            // 检查是否有运费
            $shippingCost = isset($this->_ordersdf['shipping']['cost_shipping']) ? (float)$this->_ordersdf['shipping']['cost_shipping'] : 0;
            $shippingDeducted = false; // 标记运费是否已扣除，确保只扣除一次
            
            // 构建oid到divide_order_fee的映射
            $oidDivideOrderFeeMap = array();
            if (isset($this->_ordersdf['order_objects']) && is_array($this->_ordersdf['order_objects'])) {
                foreach ($this->_ordersdf['order_objects'] as $orderObject) {
                    if (isset($orderObject['oid']) && isset($orderObject['divide_order_fee'])) {
                        $oidDivideOrderFeeMap[$orderObject['oid']] = (float)$orderObject['divide_order_fee'];
                    }
                }
            }
            
            foreach ($couponData as $key => $value)
            {
                //type
                foreach ($value as $k => $v)
                {
                    if ($v <= 0 || $k == 'sku_id' || $k == 'item_num' || $k == 'oid') {
                        continue;
                    }
                    if($k == 'platform_cost_amount'){
                        $oidPlatformAmountMap[$value['oid']] += $v;
                    }
                    $coupon[] = array(
                        'num'           => $value['item_num'],
                        'oid'           => $value['oid'],
                        'material_bn'   => '',
                        'material_name' => '',
                        'type'          => $k,
                        'type_name'     => (string)$this->couponTypeName[$k],
                        'amount'        => $v,
                        'total_amount'  => $v,
                        'create_time'   => sprintf('%.0f', time()),
                        'pay_time'      => !empty($this->_ordersdf['payment_detail']['pay_time']) ? kernel::single('ome_func')->date2time($this->_ordersdf['payment_detail']['pay_time']) : null,
                        'shop_type'     => $this->__channelObj->channel['shop_type'],
                    );
                }
                
                //实付金额
                $realPay = $value['pay_amount'] - $value['promotion_pay_amount'];
                $oidActuallyPayMap[$value['oid']] += $realPay;
                $realPayData = [
                    'num'           => $value['item_num'],
                    'oid'           => $value['oid'],
                    'material_bn'   => '',
                    'material_name' => '',
                    'type'          => 'calcActuallyPay',
                    'type_name'     => '实付金额',
                    'amount'        => $realPay,
                    'total_amount'  => $realPay,
                    'create_time'   => sprintf('%.0f', time()),
                    'pay_time'      => !empty($this->_ordersdf['payment_detail']['pay_time']) ? kernel::single('ome_func')->date2time($this->_ordersdf['payment_detail']['pay_time']) : null,
                    'shop_type'     => $this->__channelObj->channel['shop_type'],
                ];
                
                array_push($coupon,$realPayData);
            }

            $this->_ordersdf['coupon_data'] = $coupon;
            unset($this->_ordersdf['coupon_field']);
        }
        
        unset($extendList);

        // 处理gift_mids
        reset($this->_ordersdf['order_objects']);
        foreach ($this->_ordersdf['order_objects'] as $objectKey => $object) {
            if(isset($oidPlatformAmountMap[$object['oid']])){
                $this->_ordersdf['order_objects'][$objectKey]['settlement_amount'] = $object['divide_order_fee'] + $oidPlatformAmountMap[$object['oid']];
            }
            if(isset($oidActuallyPayMap[$object['oid']])){
                $this->_ordersdf['order_objects'][$objectKey]['actually_amount'] = $oidActuallyPayMap[$object['oid']];
            }
            if(!isset($object['gift_mids']) || !$object['gift_mids']){
                continue;
            }

            $gift_mids = json_decode($object['gift_mids'], true);
            if(!is_array($gift_mids) || !$gift_mids){
                continue;
            }

            $gift_mids_str = implode(',', $gift_mids);

            $this->_ordersdf['order_objects'][$objectKey]['gift_mids'] = $gift_mids_str;
            // 订单明细层也需处理,存在提层情况
            foreach ($object['order_items'] as $itemKey => $orderItem) {
                if (!isset($orderItem['gift_mids']) || !$object['gift_mids']) {
                    continue;
                }

                $this->_ordersdf['order_objects'][$objectKey]['order_items'][$itemKey]['gift_mids'] = $gift_mids_str;
            }
        }
        //直降商品优惠更新订单商品优惠金额
        if($this->_ordersdf['extend_field']['campaign_info']){
            $this->updateOrderPmtGoods();
        }
    }

    /**
     * 抖音部分券包类商品只下发 shop_goods_id/shop_product_id，不下发商家编码 bn。
     *
     * 这种订单仍然会因为销售物料不存在进入失败订单，但如果 bn 为空，失败订单页面无法直接按编码人工匹配销售物料。
     * 因此仅在 luban 且 bn 为空时，用平台商品ID/SKU ID兜底，保留可人工识别和匹配的编码。
     *
     * 注意：通用 formatItemsSdf() 对单 object 单 item 的老结构会把 item 提到 object 层，并用 item 字段覆盖 object 同名字段。
     * 所以单 SKU 场景最终 order_objects.bn 会跟随 item.bn，使用 shop_product_id 作为兜底值。
     */
    protected function _fillEmptyBnWithPlatformIds()
    {
        foreach ((array)$this->_ordersdf['order_objects'] as $objectKey => $object) {
            if (!$object['bn'] && $object['shop_goods_id'] && $object['shop_goods_id'] != '0') {
                $this->_ordersdf['order_objects'][$objectKey]['bn'] = $object['shop_goods_id'];
            }

            foreach ((array)$object['order_items'] as $itemKey => $item) {
                if (!$item['bn'] && $item['shop_product_id'] && $item['shop_product_id'] != '0') {
                    $this->_ordersdf['order_objects'][$objectKey]['order_items'][$itemKey]['bn'] = $item['shop_product_id'];
                }
            }
        }
    }
    
    /**
     * 订单更新
     * @description：解决抖音修改备注,但更新时间无变化,导致OMS订单备注没有更新
     * @description：抖音订单去A
     */
    protected function _operationSel()
    {
        $lastmodify = kernel::single('ome_func')->date2time($this->_ordersdf['lastmodify']);
    
        $orderModel = app::get('ome')->model('orders');
        $filter     = array(
            'order_bn' => $this->_ordersdf['order_bn'],
            'shop_id'  => $this->__channelObj->channel['shop_id']
        );
        $tgOrder    = $orderModel->dump($filter, '*',array('order_objects' => array('*', array('order_items' => array('*')))));
        if (empty($tgOrder) && substr($this->_ordersdf['order_bn'], -1) === 'A') {
            //把订单号去A再查下一遍，看订单是否存在
            $this->_ordersdf['order_bn'] = substr($this->_ordersdf['order_bn'], 0, -1);
            $filter = array(
                'order_bn' => $this->_ordersdf['order_bn'],
                'shop_id'  => $this->__channelObj->channel['shop_id']
            );
            $tgOrder = $orderModel->dump($filter, '*', array('order_objects' => array('*', array('order_items' => array('*')))));
        }
        $this->_tgOrder = $tgOrder;
    
        $this->__apilog['result']['data'] = array('tid' => $this->_ordersdf['order_bn']);
        $this->__apilog['original_bn']    = $this->_ordersdf['order_bn'];
        $this->__apilog['title']          = '创建订单[' . $this->_ordersdf['order_bn'] . ']';
    
        if (empty($this->_tgOrder)) {
            $this->_operationSel = 'create';
        } elseif ($lastmodify > $this->_tgOrder['outer_lastmodify']) {
            $upData = array('outer_lastmodify' => $lastmodify);
        
            if ($this->_ordersdf['source_status']) {
                $upData['source_status'] = $this->_ordersdf['source_status'];
                
                //保存旧的source_status，用于后续判断是否需要重新审单
                if ($this->_ordersdf['source_status'] != $this->_tgOrder['source_status']) {
                    if (!isset($this->_newOrder)) {
                        $this->_newOrder = array();
                    }
                    $this->_newOrder['old_source_status'] = $this->_tgOrder['source_status'];
                    $this->_newOrder['new_source_status'] = $this->_ordersdf['source_status'];
                }
                
                if ($this->_ordersdf['source_status'] == 'TRADE_CLOSED') {
                    $rdboRs = $orderModel->rebackDeliveryByOrderId($this->_tgOrder['order_id'], false, '平台订单状态取消');
                    if ($rdboRs) {
                        kernel::single('ome_order_func')->update_order_pay_status($this->_tgOrder['order_id'],false, __CLASS__.'::'.__FUNCTION__);
                    }
                }
                $arr_create_invoice = array(
                    'order_id'=>$this->_tgOrder['order_id'],
                    'source_status' => $this->_ordersdf['source_status']
                );
                kernel::single('invoice_order_front_router', 'b2c')->operateTax($arr_create_invoice);
            }
        
            //买家确认收货时间
            if ($this->_ordersdf['end_time']) {
                $upData['end_time'] = $this->_ordersdf['end_time'];
            }
        
            $orderModel->update($upData, array('order_id' => $this->_tgOrder['order_id'], 'outer_lastmodify|lthan' => $lastmodify));
        
            $affect_row = $orderModel->db->affect_row();
        
            if ($affect_row > 0) {
                $this->_operationSel = 'update';
            }
        }
    
        if (!$this->_operationSel && $this->_tgOrder) {
            $orderExtendModel = app::get('ome')->model('order_extend');
            $orderExtendInfo  = $orderExtendModel->dump(['order_id' => $this->_tgOrder['order_id']], 'bool_extendstatus');
            if ($orderExtendInfo) {
                if ($orderExtendInfo['bool_extendstatus'] & ome_order_bool_extendstatus::__UPDATESKU_ORDER) {
                    $this->_operationSel = 'update';
                }
            }
        }
        
        //订单操作不为空,则跳过
        if($this->_operationSel){
            return true;
        }
        
        ///////////////////////////////////////////
        // 解决订单备注没更新(淘宝平台问题，备注修改订单最后时间不变),
        // 同时防止比较明细，失败订单恢复后又重新更新为失败订单
        ///////////////////////////////////////////
        $memochg = false;
        
        if ($this->_tgOrder) {
            $last_custom_mark = array();
            $last_mark_text = array();
            $custom_mark = array();
            
            if ($this->_tgOrder['custom_mark'] && is_string($this->_tgOrder['custom_mark'])) {
                $custom_mark = unserialize($this->_tgOrder['custom_mark']);
            }
            
            $mark_text = array();
            if ($this->_tgOrder['mark_text'] && is_string($this->_tgOrder['mark_text'])) {
                $mark_text = unserialize($this->_tgOrder['mark_text']);
            }
            
            foreach ((array) $custom_mark as $key => $value)
            {
                if (strstr($value['op_time'], "-")) $value['op_time'] = strtotime($value['op_time']);
                
                if ( intval($value['op_time']) > intval($last_custom_mark['op_time']) ) {
                    $last_custom_mark = $value;
                }
            }
            
            foreach ((array) $mark_text as $key => $value)
            {
                if (strstr($value['op_time'], "-")) $value['op_time'] = strtotime($value['op_time']);
                
                if (intval($value['op_time']) > intval($last_mark_text['op_time'])) {
                    $last_mark_text = $value;
                }
            }
            
            if ($this->_ordersdf['custom_mark'] && $this->_ordersdf['custom_mark'] != $last_custom_mark['op_content']){
                $memochg = true;
            }
            
            if ($this->_ordersdf['mark_text'] && $this->_ordersdf['mark_text'] != $last_mark_text['op_content']){
                $memochg = true;
            }
            
            //更新订单平台状态(平台时间未变化,也需要更新)
            //@todo：抖音平台source_status字段状态值已经变化,但推送给OMS时lastmodify时间字段值未变,导致提示：更新时间没变，无需更新;
            if ($this->_ordersdf['source_status'] && $this->_ordersdf['source_status'] != $this->_tgOrder['source_status']) {
                $orderMdl = app::get('ome')->model('orders');
                
                //lastmodify
                $lastmodify = kernel::single('ome_func')->date2time($this->_ordersdf['lastmodify']);
                
                //sdf
                $upData = array();
                $upData['source_status'] = $this->_ordersdf['source_status'];
                
                //保存旧的source_status，用于后续判断是否需要重新审单
                if (!isset($this->_newOrder)) {
                    $this->_newOrder = array();
                }
                $this->_newOrder['old_source_status'] = $this->_tgOrder['source_status'];
                $this->_newOrder['new_source_status'] = $this->_ordersdf['source_status'];
                
                //平台订单已取消，撤消OMS发货单
                if($this->_ordersdf['source_status'] == 'TRADE_CLOSED') {
                    $rdboRs = $orderModel->rebackDeliveryByOrderId($this->_tgOrder['order_id'], false, '平台订单状态取消');
                    if ($rdboRs) {
                        kernel::single('ome_order_func')->update_order_pay_status($this->_tgOrder['order_id'],false, __CLASS__.'::'.__FUNCTION__);
                    }
                }
                
                //update
                $orderMdl->update($upData, array('order_id'=>$this->_tgOrder['order_id'], 'outer_lastmodify|sthan'=>$lastmodify));
                $affect_row = $orderMdl->db->affect_row();
                if ($affect_row > 0) {
                    $this->_operationSel = 'update';
                }
            }
        }
        
        //更新订单操作
        if($memochg) {
            $this->_operationSel = 'update';
        }
    }

    protected function get_convert_components()
    {
        $components = parent::get_convert_components();
     
        $components[] = 'tbpresale';
        return $components;
    }

    protected function formatItemsUpdateSkuSdf(){
        parent::formatItemsUpdateSkuSdf();
        if($this->_ordersdf['change_sku']) {
            $change_sku = $this->_ordersdf['old_sku'];
            $old_sku = $this->_ordersdf['change_sku'];
            $this->_ordersdf['change_sku'] = $change_sku;
            $this->_ordersdf['old_sku'] = $old_sku;
        }
    }

    protected function _updateAnalysis(){
        // 更新订单的时候先清理当前订单的集运标识
        $order_id = $this->_tgOrder['order_id'];
        $omsConsolidateType = kernel::single('ome_bill_label')->consolidateTypeBox;
        $labelAll = app::get('omeauto')->model('order_labels')->getList('*', ['label_code|in'=>$omsConsolidateType]);
        if ($labelAll) {
            $labelAll = array_column($labelAll, 'label_id');
            $error_msg = '';
            kernel::single('ome_bill_label')->delLabelFromBillId($order_id, $labelAll, 'order', $error_msg);
        }
    }
    
    protected function _createAnalysis(){
        parent::_createAnalysis();
        //保存优惠明细
        $this->getCouponDetailParamsFormat();
    }
    
    protected function getCouponDetailParamsFormat()
    {
        $ext_data['shop_type']      = $this->__channelObj->channel['shop_type'];
        $ext_data['payment_detail'] = $this->_ordersdf['payment_detail'];
        $ext_data['createtime']     = $this->_ordersdf['createtime'];
        $coupon_data                = $this->_ordersdf['coupon_data'] ? : [];
        //自选快递订单履约发货
        if (is_array($this->_ordersdf['extend_field']['order_tag']) && isset($this->_ordersdf['extend_field']['order_tag']['shop_optional_express_info'])) {
            foreach ($this->_ordersdf['extend_field']['order_tag']['shop_optional_express_info']['ExpressCompanys'] as $val) {
                $amount = $this->_ordersdf['extend_field']['order_tag']['shop_optional_express_info']['Amount'];
                if (isset($val['ExpressCompanyCode']) && $val['ExpressCompanyCode']) {
                    $coupon_data[] = array(
                        'type'          => 'expressCompanyFee',
                        'type_name'     => '自选快递运费',
                        'material_bn'   => 'expressCompanyFee',
                        'material_name' => '自选快递运费',
                        'amount'        => $amount,
                        'total_amount'  => $amount,
                        'create_time'   => sprintf('%.0f', time()),
                        'pay_time'      => kernel::single('ome_func')->date2time($ext_data['payment_detail']['pay_time']),
                        'shop_type'     => $ext_data['shop_type'],
                        'source'        => 'local',
                    );
                }
            }
        
        }
        $this->_ordersdf['coupon_data'] = $coupon_data;
    }

    /**
     * 直降优惠更新订单上面的商品优惠金额
     * @Author: xueding
     * @Vsersion: 2023/5/6 上午10:11
     */
    public function updateOrderPmtGoods()
    {
        $campaignInfoList = array();
        $campaignAmount   = 0;
        foreach ($this->_ordersdf['extend_field']['campaign_info'] as $oid => $campaign_info) {
            if (!empty($campaign_info['campaign_info'])) {
                !$this->_ordersdf['pmt_detail'] && $this->_ordersdf['pmt_detail'] = [];
                foreach ($campaign_info['campaign_info'] as $infoRow) {
                    $campaignInfoList[$oid] += sprintf("%.2f", $infoRow['campaign_amount'] / 100);
                    //插入优惠方案
                    $tmp_pmt['pmt_amount'] = sprintf("%.2f", $infoRow['campaign_amount'] / 100);
                    $tmp_pmt['pmt_describe'] = $infoRow['campaign_name'];
                    $tmp_pmt['oid'] = $oid;
                    $tmp_pmt['promotion_id'] = $infoRow['campaign_id'];
                    $tmp_pmt['discount_code'] = '';
                    $tmp_pmt['quantity'] = 1;
                    array_push($this->_ordersdf['pmt_detail'],$tmp_pmt);
                }
            }
        }

        foreach ($this->_ordersdf['order_objects'] as $key => $val) {
            if ($campaignInfoList[$val['oid']] && $val['pmt_price'] == 0) {
                $this->_ordersdf['order_objects'][$key]['price']     = $val['price'] + ($campaignInfoList[$val['oid']] / $val['quantity']);
                $this->_ordersdf['order_objects'][$key]['pmt_price'] = $val['pmt_price'] + $campaignInfoList[$val['oid']];
                foreach ($val['order_items'] as $k => $ite) {
                    $this->_ordersdf['order_objects'][$key]['order_items'][$k]['price']     = $ite['price'] + ($campaignInfoList[$val['oid']] / $val['quantity']);
                    $this->_ordersdf['order_objects'][$key]['order_items'][$k]['pmt_price'] = $ite['pmt_price'] + $campaignInfoList[$val['oid']];
                }
                $campaignAmount += $campaignInfoList[$val['oid']];
            }
        }

        if ($campaignAmount > 0) {
            $this->_ordersdf['cost_item'] = $this->_ordersdf['cost_item'] + $campaignAmount;
            $this->_ordersdf['pmt_goods'] = $this->_ordersdf['pmt_goods'] + $campaignAmount;
        }
    }
}
