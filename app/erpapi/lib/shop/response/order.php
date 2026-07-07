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
 * 订单接口处理
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_response_order extends erpapi_shop_response_abstract
{
    /**
     * ERP订单
     * 
     * @var string
     * */

    public $_tgOrder = array();

    /**
     * 订单接收格式
     * 
     * @var array
     * */
    public $_ordersdf = array();

    /**
     * 订单标准结构
     * 
     * @var string
     * */
    public $_newOrder = array();

    /**
     * 可接收未付款订单
     * 
     * @var string
     * */
    protected $_accept_unpayed_order = false;

    /**
     * 更新订单是否接收死单
     * 
     * @var string
     * */
    protected $_update_accept_dead_order = false;

    #平台订单状态
    protected $_sourceStatus = array();

    /**
     * 订单obj明细唯一标识
     * 
     * @var string
     * */
    public $object_comp_key = 'bn-shop_goods_id-obj_type';

    /**
     * 订单item唯一标识
     * 
     * @var string
     * */
    public $item_comp_key = 'bn-shop_product_id-item_type';

    /**
     * 防并发key
     * 
     * @return void
     * @author
     * */
    public function concurrentKey($sdf)
    {
        $this->__lastmodify            = kernel::single('ome_func')->date2time($sdf['lastmodify']);
        $this->__apilog['original_bn'] = $sdf['order_bn'];

        if ($sdf['method'] && $sdf['node_id'] && $sdf['order_bn']) {
            $key = $sdf['method'] . $sdf['node_id'] . $sdf['order_bn'];
        }

        return $key ? md5($key) : false;
    }

    public function add($sdf)
    {
        //sdf
        $this->_ordersdf = $sdf;
        $this->_tgOrder  = $this->_newOrder  = array();

        $this->__apilog['result']['data'] = array('tid' => $this->_ordersdf['order_bn']);
        $this->__apilog['original_bn']    = $this->_ordersdf['order_bn'];
        $this->__apilog['title']          = '创建订单[' . $this->_ordersdf['order_bn'] . ']';

        // 数据格式化
        $this->_analysis();
        
        //是否接收订单
        $accept = $this->_canAccept();
        if ($accept === false) {
            return array();
        }
        
        //订单明细格式化
        $this->formatItemsSdf();
        
        //订单操作：创建 or 更新
        $this->_operationSel();

        switch ($this->_operationSel) {
            case 'create':

                $rs = $this->_createOrder();

                if ($rs === false) {
                    return array();
                }

                break;
            case 'update':
                $this->formatItemsUpdateSkuSdf();
                $rs = $this->_updateOrder();

                if ($rs === false) {
                    return array();
                }

                if (!$this->_newOrder && !$this->__apilog['result']['msg']) {
                    $this->__apilog['result']['msg'] = '订单无结构变化，无需更新';
                }

                if ($this->_newOrder) {
                    $this->_newOrder['order_id'] = $this->_tgOrder['order_id'];
                    if($this->_ordersdf['order_bool_type']){
                        $this->_newOrder['order_bool_type'] = $this->_ordersdf['order_bool_type'];
                        $this->_newOrder['change_sku'] = $this->_ordersdf['change_sku'];
                        $this->_newOrder['old_sku'] = $this->_ordersdf['old_sku'];
                    }
                }

                break;
            case 'close':
                $this->_newOrder['order_id'] = $this->_tgOrder['order_id'];
                $this->_newOrder['flag'] = 'close';
                $this->__apilog['title']         = '取消订单['.$this->_ordersdf['order_bn'].']';
                $rs = $this->_closeOrder();

                if ($rs ===false) return array();
                break;
            default:
                $this->__apilog['title']         = '更新订单[' . $this->_ordersdf['order_bn'] . ']';
                $this->__apilog['result']['msg'] = '更新时间没变，无需更新';
                return array();
                break;
        }

        return $this->_newOrder;
    }

    /**
     * 订单操作：创建 or 更新
     * 
     * @return void
     * @author
     * */
    protected function _operationSel()
    {
        $lastmodify = kernel::single('ome_func')->date2time($this->_ordersdf['lastmodify']);

        $orderModel     = app::get('ome')->model('orders');
        $filter         = array('order_bn' => $this->_ordersdf['order_bn'], 'shop_id' => $this->__channelObj->channel['shop_id']);
        $this->_tgOrder = $orderModel->dump($filter, '*', array('order_objects' => array('*', array('order_items' => array('*')))));

        if (empty($this->_tgOrder)) {

            $this->_operationSel = 'create';

        } elseif ($lastmodify > $this->_tgOrder['outer_lastmodify']) {
            $upData        = array('outer_lastmodify' => $lastmodify);

            if ($this->_ordersdf['source_status']) {
                $upData['source_status'] = $this->_ordersdf['source_status'];
                if($this->_ordersdf['source_status'] == 'TRADE_CLOSED') {
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
                
                //平台订单是已完成状态
                if($this->_ordersdf['source_status'] == 'TRADE_FINISHED'){
                    //执行补发赠品任务,创建补发订单并自动审核
                    $error_msg = '';
                    $giftResult = kernel::single('ome_preprocess_crm')->bufaOrderGifts($this->_tgOrder['order_id'], $error_msg);
                }
            }

            //买家确认收货时间
            if($this->_ordersdf['end_time']) {
                $upData['end_time'] = $this->_ordersdf['end_time'];
            }

            $orderModel->update($upData, array('order_id' => $this->_tgOrder['order_id'], 'outer_lastmodify|lthan' => $lastmodify));

            $affect_row = $orderModel->db->affect_row();

            if ($affect_row > 0) {
                $this->_operationSel = 'update';
            }

        } elseif ($lastmodify == $this->_tgOrder['outer_lastmodify']) {
            $this->_operationSel = 'update';
        }

        if(!$this->_operationSel && $this->_tgOrder) {
            $orderExtendModel = app::get('ome')->model('order_extend');
            $orderExtendInfo = $orderExtendModel->dump(['order_id' => $this->_tgOrder['order_id']], 'bool_extendstatus');
            if ($orderExtendInfo) {
                if ($orderExtendInfo['bool_extendstatus'] & ome_order_bool_extendstatus::__UPDATESKU_ORDER) {
                    $this->_operationSel = 'update';
                }
            }
        }
    }

    //平台自发明细is_sh_ship=true
    protected function _setPlatformDelivery() {
        foreach($this->_ordersdf['order_objects'] as $key=>$object){
            foreach($object['order_items'] as $k=>$item){
                $this->_ordersdf['order_objects'][$key]['order_items'][$k]['is_sh_ship'] = 'true';
            }
            $this->_ordersdf['order_objects'][$key]['is_sh_ship'] = 'true';
        }

        $this->_ordersdf['order_type'] = 'platform';
    }
    
    protected function _createAnalysis(){}
    
    /**
     * 创建订单
     * 
     * @return void
     * @author
     * */
    protected function _createOrder()
    {
        $this->__apilog['title'] = '创建订单[' . $this->_ordersdf['order_bn'] . ']';
    
        $this->_createAnalysis();
    
        if (false === $this->_canCreate()) {
            return false;
        }

        if ($service = kernel::servicelist('service.order')) {
            foreach ($service as $instance) {
                if (method_exists($instance, 'pre_add_order')) {
                    $instance->pre_add_order($this->_ordersdf);
                }
            }
        }

        // 组件集合
        $broker = kernel::single('erpapi_shop_response_components_order_broker');

        $broker->clearComponents();

        foreach ($this->get_convert_components() as $component) {
            $broker->registerComponent($component);
        }

        $broker->setPlatform($this)->convert();

        // 插件的SDF
        foreach (array_unique($this->get_create_plugins()) as $plugin) {
            $pluginObj = kernel::single('erpapi_shop_response_plugins_order_' . $plugin);

            if (method_exists($pluginObj, 'convert')) {
                $pluginsdf = $pluginObj->convert($this);

                if ($pluginsdf) {
                    $this->_newOrder['plugins'][$plugin] = $pluginsdf;
                }

            }
        }

        if($this->_ordersdf['t_type'] == 'fenxiao'){
            //分销标示
            $this->_ordersdf['order_bool_type'] = 0 | ome_order_bool_type::__DISTRIBUTION_CODE;
            $this->_newOrder['order_bool_type'] = $this->_ordersdf['order_bool_type'];
        }

        return true;
    }

    /**
     * 订单组件
     * 
     * @return void
     * @author
     * */
    protected function get_convert_components()
    {
        $components = array('master', 'items', 'shipping', 'consignee', 'consigner', 'custommemo', 'markmemo', 'marktype', 'member', 'tax','booltype');
        return $components;
    }

    /**
     * 创建订单的插件
     * 
     * @return array
     * @author
     * */
    protected function get_create_plugins()
    {
        $plugins = array(
            'payment',
            'promotion',
            'cod',
            'ordertype',
            'combine',
            'service',
            'invoice',
            'encryptsourcedata',
            'crm',
            'orderextend',
            'orderlabels',
            'abnormal',
            'luckybag',
            'present',
            'coupon',
        );
        
        return $plugins;
    }
    
    protected function _updateAnalysis(){}
    
    /**
     * 更新订单
     * 
     * @return void
     * @author
     * */
    protected function _updateOrder()
    {
        $this->__apilog['title'] = '更新订单[' . $this->_ordersdf['order_bn'] . ']';
        
        $this->_updateAnalysis();
        
        if (false === $this->_canUpdate()) {
            return false;
        }

        // 组件集合
        $broker = kernel::single('erpapi_shop_response_components_order_broker');

        $broker->clearComponents();

        foreach (array_unique($this->get_update_components()) as $component) {
            $broker->registerComponent($component);
        }

        $broker->setPlatform($this)->update();

        // 插件的SDF
        foreach (array_unique($this->get_update_plugins()) as $plugin) {
            $pluginObj = kernel::single('erpapi_shop_response_plugins_order_' . $plugin);
            if (method_exists($pluginObj, 'convert')) {
                $pluginsdf = $pluginObj->convert($this);

                if ($pluginsdf) {
                    $this->_newOrder['plugins'][$plugin] = $pluginsdf;
                }

            }
        }

        return true;
    }
    
        /**
     * order_get_obj_key
     * @param mixed $object object
     * @return mixed 返回值
     */
    public function order_get_obj_key($object)
    {
        $objkey = '';
        foreach (explode('-', $this->object_comp_key) as $field) {
            $objkey .= ($object[$field] ? trim($object[$field]) : '').'-';
        }
        return sprintf('%u',crc32(ltrim($objkey,'-')));
    }

    /**
     * 订单组件
     * 
     * @return void
     * @author
     * */
    protected function get_update_components()
    {
        $components = array('master', 'items', 'shipping', 'consignee', 'consigner', 'custommemo', 'markmemo', 'marktype', 'member', 'tax');
        return $components;
    }

    /**
     * 更新插件
     * 
     * @return array
     * @author
     * */
    protected function get_update_plugins()
    {
        $plugins = array('encryptsourcedata','ordertype','coupon');

        return $plugins;
    }

    /**
     * 数据解析
     * 
     * @return void
     * @author
     * */
    protected function _analysis()
    {
        $ordFunLib = kernel::single('ome_order_func');
        
        //[转换订单]调用service进行转单逻辑
        foreach(kernel::servicelist('erpapi.service.order.analysis.prepose') as $object)
        {
            if(method_exists($object, 'prepose_analysis')){
                // shop
                $this->_ordersdf['shop_id'] = $this->__channelObj->channel['shop_id'];
                $this->_ordersdf['shop_type'] = $this->__channelObj->channel['shop_type'];
                
                // analysis
                $analysisSdf = $object->prepose_analysis($this->_ordersdf);
                
                //merge
                if($analysisSdf){
                    $this->_ordersdf = array_merge($this->_ordersdf, $analysisSdf);
                }
            }
        }
        
        // source_status
        $source_status = $this->_sourceStatus[$this->_ordersdf['source_status']] ?: $this->_ordersdf['source_status'];
        $this->_ordersdf['source_status'] = kernel::single('ome_order_func')->get_source_status($source_status);
        if(in_array($this->_ordersdf['status'], ['close', 'dead'])) {
            $this->_ordersdf['source_status'] = 'TRADE_CLOSED';
        }
        if(in_array($this->_ordersdf['status'], ['finish'])) {
            $this->_ordersdf['source_status'] = 'TRADE_FINISHED';
        }
        // 配送信息
        if (is_string($this->_ordersdf['shipping'])) {
            $this->_ordersdf['shipping'] = json_decode($this->_ordersdf['shipping'], true);
        }

        // 支付信息
        if (is_string($this->_ordersdf['payinfo'])) {
            $this->_ordersdf['payinfo'] = json_decode($this->_ordersdf['payinfo'], true);
        }

        // 收货人信息
        if (is_string($this->_ordersdf['consignee'])) {
            $this->_ordersdf['consignee'] = json_decode($this->_ordersdf['consignee'], true);
        }

        //替换表情符
        if($this->_ordersdf['consignee']['name']){
            $this->_ordersdf['consignee']['name'] = $ordFunLib->filterEmoji($this->_ordersdf['consignee']['name']);
        }

        // 发货人信息
        if (is_string($this->_ordersdf['consigner'])) {
            $this->_ordersdf['consigner'] = json_decode($this->_ordersdf['consigner'], true);
        }

        // 代销人信息
        if (is_string($this->_ordersdf['selling_agent'])) {
            $this->_ordersdf['selling_agent'] = json_decode($this->_ordersdf['selling_agent'], true);
        }

        // 菜鸟直销订单
        if (is_string($this->_ordersdf['cn_info'])) {
            $this->_ordersdf['cn_info'] = json_decode($this->_ordersdf['cn_info'], true);
        }

        // 买家会员信息
        if (is_string($this->_ordersdf['member_info'])) {
            $this->_ordersdf['member_info'] = json_decode($this->_ordersdf['member_info'], true);
        }

        //替换表情符
        if ($this->_ordersdf['member_info']['uname']) {
            $this->_ordersdf['member_info']['uname'] = $ordFunLib->filterEmoji($this->_ordersdf['member_info']['uname']);
        }

        // 订单优惠方案
        if (is_string($this->_ordersdf['pmt_detail'])) {
            $this->_ordersdf['pmt_detail'] = json_decode($this->_ordersdf['pmt_detail'], true);
        }

        // 订单商品
        if (is_string($this->_ordersdf['order_objects'])) {
            $this->_ordersdf['order_objects'] = json_decode($this->_ordersdf['order_objects'], true);
        }

        // 支付单(兼容老版本)
        if (is_string($this->_ordersdf['payment_detail'])) {
            $this->_ordersdf['payment_detail'] = json_decode($this->_ordersdf['payment_detail'], true);
        }

        if (is_string($this->_ordersdf['payments'])) {
            $this->_ordersdf['payments'] = $this->_ordersdf['payments'] ? json_decode($this->_ordersdf['payments'], true) : array();
        }

        // 非可逆索引
        if (is_string($this->_ordersdf['index_field'])) {
            $this->_ordersdf['index_field'] = $this->_ordersdf['index_field'] ? json_decode($this->_ordersdf['index_field'], true) : array();
        }

        if (is_string($this->_ordersdf['other_list'])) {
            $this->_ordersdf['other_list'] = json_decode($this->_ordersdf['other_list'], true);
        }
        // 平台自发货
        foreach ($this->_ordersdf['other_list'] as $value) {
            if ($value['type'] == 'store' && $value['is_store'] == '1') {
                $this->_ordersdf['order_type'] = 'platform';
            }
        }
        
        $is_platform = false;//是否平台自发标识
        if($this->__channelObj->channel['delivery_mode'] == 'jingxiao') {
            $is_platform = true;
        }
    
        if (isset($this->_ordersdf['trade_type']) && $this->_ordersdf['trade_type'] && $this->_ordersdf['trade_type'] == 'auto_delivery') {
            $is_platform = true;
        }
    
        if ($is_platform) {
            $this->_setPlatformDelivery();
        }
        // 去首尾空格
        self::trim($this->_ordersdf);

        // 如果是担保交易,订单支付状态修复成已支付
        if ($this->_ordersdf['pay_status'] == '2') {
            $this->_ordersdf['pay_status'] = '1';
        }

        // 如果是货到付款的，重置支付金额，支付单
        if ($this->_ordersdf['shipping']['is_cod'] == 'true' && $this->_ordersdf['pay_status'] == '0') {
            $this->_ordersdf['payed']          = '0';
            $this->_ordersdf['payments']       = array();
            $this->_ordersdf['payment_detail'] = array();
        }

        $this->_ordersdf['pmt_goods'] = abs((float)$this->_ordersdf['pmt_goods']);
        $this->_ordersdf['pmt_order'] = abs((float)$this->_ordersdf['pmt_order']);

        if ($this->_ordersdf['pay_status'] == '5') {
            $this->_ordersdf['payed'] = 0;
        }

        if (is_string($this->_ordersdf['service_order_objects'])) {
            $this->_ordersdf['service_order_objects'] = json_decode($this->_ordersdf['service_order_objects'], true);
        }

        $title=array();
        // 由于OBJ货号太长，导致更新的时候明细不一致
        foreach ((array) $this->_ordersdf['order_objects'] as $objkey => $object) {
            if ($object['bn'] && $object['bn'][40]) {
                $this->_ordersdf['order_objects'][$objkey]['bn'] = substr($object['bn'], 0, 40);
            }
            $title[]=array('name'=>$object['name'],'num'=>$object['quantity']);

            foreach ($object['order_items'] as $itemkey => $item) {
                if (is_string($item['extend_item_list'])) {
                    $this->_ordersdf['order_objects'][$objkey]['order_items'][$itemkey]['extend_item_list'] = json_decode($item['extend_item_list'], true);
                }
                
                //customization定制信息
                if(isset($item['customization']) && $item['customization']) {
                    //定制订单标识
                    $this->_ordersdf['order_customs'] = 'Y';
                }
            }
        }
        
        if($title){
            $this->_ordersdf['title']=json_encode($title);
        }
       

        if ($this->_ordersdf['end_time']) {
            $this->_ordersdf['end_time'] = strtotime($this->_ordersdf['end_time']);
        }

        if (isset($this->_ordersdf['o2o_info']) && is_string($this->_ordersdf['o2o_info'])) {
            $this->_ordersdf['o2o_info'] = json_decode($this->_ordersdf['o2o_info'], true);
        }

        //扩展信息
        if ($this->_ordersdf['extend_field'] && is_string($this->_ordersdf['extend_field'])) {
            $this->_ordersdf['extend_field'] = json_decode($this->_ordersdf['extend_field'], true);
        }

        //优惠明细平台原始字段
        if ($this->_ordersdf['coupon_field'] && is_string($this->_ordersdf['coupon_field'])) {
            $this->_ordersdf['coupon_field'] = json_decode($this->_ordersdf['coupon_field'], true);
        }
    
        // 纸票转电票
        if ('on' == app::get('ome')->getConf('ome.invoice.p2e') && $this->_ordersdf['is_tax'] == 'true' && $this->_ordersdf['invoice_kind'] != '1' && !$this->_ordersdf['value_added_tax_invoice']) {
            $this->_ordersdf['invoice_kind'] = '1';
        }

        // 强制开票
        if ($this->_ordersdf['is_tax'] != 'true' 
            && 'on' == app::get('ome')->getConf('ome.invoice.force')) {
            $this->_ordersdf['is_tax'] = 'true';
        }

        // 强制开电票
        if ($this->_ordersdf['is_tax'] == 'true'
            && !is_numeric($this->_ordersdf['invoice_kind']) ) {
            $invoiceMode = app::get('ome')->getConf('ome.invoice.mode');

            // 开电票
            if ('electron' == $invoiceMode){
                $this->_ordersdf['invoice_kind'] = '1';
            }
            
            // 开专票
            if ('special' == $invoiceMode){
                $this->_ordersdf['invoice_kind'] = '3';
                $this->_ordersdf['value_added_tax_invoice'] = 'true';
            }

        }

        // 默认开个人抬头
        if ($this->_ordersdf['is_tax'] == 'true' && !$this->_ordersdf['tax_title']) {
            $this->_ordersdf['tax_title'] = '个人';
        }

        // 收货地址处理
        $this->_ordersdf['consignee']['addr'] = str_replace(array("\r\n","\r","\n","'","\"","\\"), '', htmlspecialchars($this->_ordersdf['consignee']['addr']));
        
        //加密字段处理
        $this->_securityHashCode();
        
    }

    /**
     * 是否接收订单
     * 
     * @return void
     * @author
     * */
    protected function _canAccept()
    {
        if (empty($this->_ordersdf) || empty($this->_ordersdf['order_bn']) || empty($this->_ordersdf['order_objects'])) {
            $this->__apilog['result']['msg'] = '接收数据不完整';
            return false;
        }

        $wuliubao = app::get('ome')->getConf('ome.delivery.wuliubao');
        if ($wuliubao == 'false' && strtolower($this->_ordersdf['is_force_wlb']) == 'true') {
            $this->__apilog['result']['msg'] = '物流宝发货订单不接收';
            return false;
        }

        if ($this->_ordersdf['order_from'] == 'omeapiplugin') {
            $this->__apilog['result']['msg'] = '来自omeapiplugin的订单不接收';
            return false;
        }
        
        // if ($this->_accept_unpayed_order !== true) {
        //     if ($this->_ordersdf['shipping']['is_cod'] != 'true' && $this->_ordersdf['pay_status'] == '0') {
        //         $this->__apilog['result']['msg'] = '未支付订单不接收';
        //         return false;
        //     }
        // }
        // 检查是否有自定义的 service 拦截接单，允许扩展处理
        foreach(kernel::servicelist('erpapi.service.order.canaccept') as $service){
            if(method_exists($service, 'canAccept')){
                $result = $service->canAccept($this);
                // service 返回明确的拒绝理由
                if(is_array($result) && isset($result['succ']) && !$result['succ']){
                    $this->__apilog['result']['msg'] = $result['msg'] ?: '服务插件拒绝接单';
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 创建接收
     * 
     * @return void
     * @author
     * */
    protected function _canCreate()
    {
        // if ($this->_ordersdf['order_type'] != 'platform' && $this->_ordersdf['ship_status'] != '0') {
        //     $this->__apilog['result']['msg'] = '已发货订单不接收';
        //     return false;
        // }

        // if ($this->_ordersdf['order_type'] == 'platform' && $this->_ordersdf['ship_status'] != '1') {
        //     $this->__apilog['result']['msg'] = '平台发货订单只接受已发货订单';
        //     return false;
        // }

        // 只接收活动订单
        // if ($this->_ordersdf['order_type'] != 'platform' && $this->_ordersdf['status'] != 'active') {
        //     $this->__apilog['result']['msg'] = $this->_ordersdf['status'] == 'close' ? '取消订单不接收' : '完成订单不接收';

        //     return false;
        // }
        
        if($this->__channelObj->channel['config']['order_receive'] === 'no'){
            $this->__apilog['result']['msg'] = '未开启收单配置';
            return false;
        }

        $config_order_buyer = array_filter(explode("\n",$this->__channelObj->channel['config']['order_buyer']));
        if($this->__channelObj->channel['config']['order_receive'] === 'appoint' &&
            (
                !$config_order_buyer ||
                (
                    !in_array($this->_ordersdf['member_info']['uname'],$config_order_buyer)
                    && !in_array($this->_ordersdf['member_info']['buyer_open_uid'],$config_order_buyer )
                    && !in_array($this->_ordersdf['index_field']['open_address_id'],$config_order_buyer)
                )

            )
        ){
            $this->__apilog['result']['msg'] = '只接收指定购买人的订单';
            return false;
        }
        

        $shShip = app::get('ome')->getConf('ome.platform.order.consign');
        $accept_platform = $this->_ordersdf['accept_platform'];
 
        if ($this->_ordersdf['order_type'] == 'platform') {
            if($accept_platform==true){
                $shShip = 'payed';
            }
            if (!$shShip || !in_array($shShip, array ('true', 'signed', 'payed'))) {
                $this->__apilog['result']['msg'] = '未开启接收平台发货订单选项';
                return false;
            }

            if ($shShip == 'true' && $this->_ordersdf['ship_status'] != '1') {
                $this->__apilog['result']['msg'] = '只接收已发货平台发货订单(ship_status='.$this->_ordersdf['ship_status'].')';
                return false;
            }

            if ($shShip == 'signed' && $this->_ordersdf['status'] != 'finish') {
                $this->__apilog['result']['msg'] = '只接收已签收平台发货订单(status='.$this->_ordersdf['status'].')';
                return false;
            }

            if ($shShip == 'payed' && $this->_ordersdf['pay_status'] != '1') {
                $this->__apilog['result']['msg'] = '只接收已支付平台发货订单(pay_status='.$this->_ordersdf['pay_status'].')';
                return false;
            }
        } else {
            if(!in_array($this->_ordersdf['order_type'], array('offline','eticket')) && $this->_ordersdf['ship_status'] != '0') {
                $this->__apilog['result']['msg'] = '已发货订单不接收';
                return false;
            }
            // 只接收活动订单
            if ($this->_ordersdf['status'] != 'active') {
                $this->__apilog['result']['msg'] = $this->_ordersdf['status'] == 'close' ? '取消订单不接收' : '完成订单不接收';
                return false;
            }
        }

        // 只接收活动订单
        // if ($this->_ordersdf['status'] != 'active') {
        //     $this->__apilog['result']['msg'] = $this->_ordersdf['status'] == 'close' ? '取消订单不接收' : '完成订单不接收';
        //     return false;
        // }

        $archiveModel = app::get('archive')->model('orders');
        $archive      = $archiveModel->dump(array('order_bn' => $this->_ordersdf['order_bn'], 'shop_id' => $this->__channelObj->channel['shop_id']), 'order_id');
        if ($archive) {
            $this->__apilog['result']['msg'] = '归档订单不接收';
            return false;
        }

        if ($this->_accept_unpayed_order !== true && $this->_ordersdf['shipping']['is_cod'] != 'true' && $this->_ordersdf['pay_status'] == '0') {
            if($this->_ordersdf['delivery_mode'] == 'shopyjdf'){
                //经销商一件代发订单，接收未支付订单
            }else{
                $this->__apilog['result']['msg'] = '未支付订单不接收';
                return false;
            }
        }

        if (in_array($this->_ordersdf['pay_status'], array('4', '5', '7', '8'))) {
            $this->__apilog['result']['msg'] = '退款订单不接收';
            return false;
        }
        $rno = app::get('ome')->model('refund_no_order')->getList('id', array('order_bn' => $this->_ordersdf['order_bn'], 'shop_id' => $this->__channelObj->channel['shop_id']));
        if ($rno) {
            $this->_ordersdf['pay_status'] =6;
            //$this->__apilog['result']['msg'] = '该订单已经有退款单了， 不接收';
            //return false;
        }
        return true;
    }

    /**
     * 更新接收
     * 
     * @return void
     * @author
     * */
    protected function _canUpdate()
    {
        if ($this->_ordersdf['order_type'] == 'platform') {

        }else{
            if ($this->_ordersdf['status'] == 'finish' && ($this->_ordersdf['end_time'] == '' || $this->_tgOrder['end_time'] > 0)) {
                $this->__apilog['result']['msg'] = '完成订单不接收';
                
                // [更新]预约订单的相关状态
                $error_msg = '';
                kernel::single('ome_order_reservation')->operateReservationOrder($this->_ordersdf, $error_msg);
                
                return false;
            }
            if ($this->_update_accept_dead_order === false && $this->_ordersdf['status'] == 'dead') {
                $this->__apilog['result']['msg'] = '取消订单不接收';
                return false;
            }
        }

        if (!in_array($this->_ordersdf['status'], array('active', 'finish', 'close', 'dead'))) {
            $this->__apilog['result']['msg'] = '不明订单状态不接收';
            return false;
        }

        if ($this->_ordersdf['status'] == 'close') {
            $this->__apilog['result']['msg'] = '关闭订单不接收';
            return false;
        }

        if ($this->_tgOrder['status'] == 'dead') {
            // [更新]预约订单的相关状态
            $error_msg = '';
            kernel::single('ome_order_reservation')->operateReservationOrder($this->_ordersdf, $error_msg);
            
            $this->__apilog['result']['msg'] = 'ERP取消订单，不做更新';
            return false;
        }
        
        if (in_array($this->_tgOrder['ship_status'], array('1', '2')) || $this->_tgOrder['status'] == 'finish') {
            $this->__apilog['result']['msg'] = 'ERP发货订单，不做更新';
            return false;
        }

        if (in_array($this->_tgOrder['ship_status'], array('3', '4'))) {
            $this->__apilog['result']['msg'] = 'ERP退货订单，不做更新';
            return false;
        }
    }

        /**
     * status_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function status_update($sdf)
    {
        $this->__apilog['original_bn'] = $sdf['order_bn'];
        $this->__apilog['title']       = '修改订单状态[' . $sdf['order_bn'] . ']';

        // 只接收作废订单
        if ($sdf['status'] == '') {
            $this->__apilog['result']['msg'] = '订单状态不能为空';
            return false;
        }

        if ($this->__channelObj->get_ver() > '1') {
            if ($sdf['status'] != 'dead') {
                $this->__apilog['result']['msg'] = '不接收除作废以外的其他状态';
                return false;
            }
        }

        // 读取订单
        $orderModel = app::get('ome')->model('orders');

        $filter  = array('order_bn' => $sdf['order_bn'], 'shop_id' => $this->__channelObj->channel['shop_id']);
        $tgOrder = $orderModel->getList('pay_status,order_id,op_id,ship_status,status,process_status', $filter, 0, 1);
        $tgOrder = $tgOrder[0];

        if (!$tgOrder) {
            $this->__apilog['result']['msg'] = 'ERP订单不存在';
            return false;
        }

        if (in_array($tgOrder['pay_status'], array('1', '2', '3', '4'))) {
            $this->__apilog['result']['msg'] = '订单已经支付，不更新';
            return false;
        }

        if ($tgOrder['ship_status'] != 0) {
            $this->__apilog['result']['msg'] = '订单未发货，不更新';
            return false;
        }

        if ($tgOrder['status'] != 'active' || $tgOrder['process_status'] == 'cancel') {
            $this->__apilog['result']['msg'] = '订单已取消，不更新';
            return false;
        }

        $updateOrder = array();

        if (!$tgOrder['op_id']) {
            $userModel            = app::get('desktop')->model('users');
            $userinfo             = $userModel->getList('user_id', array('super' => '1'), 0, 1, 'user_id asc');
            $updateOrder['op_id'] = $userinfo[0]['op_id'];
        }

        $updateOrder['status'] = $sdf['status'];

        if ($updateOrder) {
            $updateOrder['order_id'] = $tgOrder['order_id'];
        }

        return $updateOrder;
    }

    /**
     * pay_status_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function pay_status_update($sdf)
    {
        $this->__apilog['original_bn'] = $sdf['order_bn'];
        $this->__apilog['title']       = '修改订单支付状态[' . $sdf['order_bn'] . ']';

        if ($this->__channelObj->get_ver() > '1') {
            $this->__apilog['result']['msg'] = '版本2不走此接口';
            return false;
        }

        // 读取订单
        $orderModel = app::get('ome')->model('orders');

        $filter  = array('order_bn' => $sdf['order_bn'], 'shop_id' => $this->__channelObj->channel['shop_id']);
        $tgOrder = $orderModel->getList('order_id,mark_text,cost_payment,total_amount,final_amount', $filter, 0, 1);
        $tgOrder = $tgOrder[0];

        if (!$tgOrder) {
            $this->__apilog['result']['msg'] = 'ERP订单不存在';
            return false;
        }

        $updateOrder = array();

        $updateOrder['pay_status'] = $sdf['pay_status'];

        if ($updateOrder) {
            $updateOrder['order_id'] = $tgOrder['order_id'];
        }

        return $updateOrder;

    }

    /**
     * ship_status_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function ship_status_update($sdf)
    {
        $this->__apilog['original_bn'] = $sdf['order_bn'];
        $this->__apilog['title']       = '修改订单发货状态[' . $sdf['order_bn'] . ']';

        if ($this->__channelObj->get_ver() > '1') {
            $this->__apilog['result']['msg'] = '版本2不走此接口';
            return false;
        }

        // 读取订单
        $orderModel = app::get('ome')->model('orders');

        $filter  = array('order_bn' => $sdf['order_bn'], 'shop_id' => $this->__channelObj->channel['shop_id']);
        $tgOrder = $orderModel->getList('order_id', $filter, 0, 1);
        $tgOrder = $tgOrder[0];

        if (!$tgOrder) {
            $this->__apilog['result']['msg'] = 'ERP订单不存在';
            return false;
        }

        $updateOrder = array();

        $updateOrder['ship_status'] = $sdf['ship_status'];

        if ($updateOrder) {
            $updateOrder['order_id'] = $tgOrder['order_id'];
        }

        return $updateOrder;
    }

    /**
     * custom_mark_add
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function custom_mark_add($sdf)
    {
        $this->__apilog['original_bn'] = $sdf['order_bn'];
        $this->__apilog['title']       = '添加订单买家备注[' . $sdf['order_bn'] . ']';

        if ($this->__channelObj->get_ver() > '1') {
            $this->__apilog['result']['msg'] = '版本2不走此接口';
            return false;
        }

        // 读取订单
        $orderModel = app::get('ome')->model('orders');

        $filter  = array('order_bn' => $sdf['order_bn'], 'shop_id' => $this->__channelObj->channel['shop_id']);
        $tgOrder = $orderModel->getList('order_id,custom_mark', $filter, 0, 1);
        $tgOrder = $tgOrder[0];

        if (!$tgOrder) {
            $this->__apilog['result']['msg'] = 'ERP订单不存在';
            return false;
        }

        $updateOrder = array();
        if ($sdf['message']) {
            $custom_mark = $tgOrder['custom_mark'] ? unserialize($tgOrder['custom_mark']) : array();

            $custom_mark[] = array(
                'op_name'    => $sdf['sender'],
                'op_time'    => kernel::single('ome_func')->date2time($sdf['add_time']),
                'op_content' => htmlspecialchars($sdf['message']),
            );

            $updateOrder['custom_mark'] = serialize($custom_mark);
        }

        if ($updateOrder) {
            $updateOrder['order_id'] = $tgOrder['order_id'];
        }

        return $updateOrder;
    }

    /**
     * custom_mark_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function custom_mark_update($sdf)
    {
        $this->__apilog['original_bn'] = $sdf['order_bn'];
        $this->__apilog['title']       = '更新订单买家备注[' . $sdf['order_bn'] . ']';

        if ($this->__channelObj->get_ver() > '1') {
            $this->__apilog['result']['msg'] = '版本2不走此接口';
            return false;
        }

        // 读取订单
        $orderModel = app::get('ome')->model('orders');

        $filter  = array('order_bn' => $sdf['order_bn'], 'shop_id' => $this->__channelObj->channel['shop_id']);
        $tgOrder = $orderModel->getList('order_id,custom_mark', $filter, 0, 1);
        $tgOrder = $tgOrder[0];

        if (!$tgOrder) {
            $this->__apilog['result']['msg'] = 'ERP订单不存在';
            return false;
        }

        $updateOrder = array();
        if ($sdf['message']) {
            $custom_mark = $tgOrder['custom_mark'] ? unserialize($tgOrder['custom_mark']) : array();

            $custom_mark[] = array(
                'op_name'    => $sdf['sender'],
                'op_time'    => kernel::single('ome_func')->date2time($sdf['add_time']),
                'op_content' => htmlspecialchars($sdf['message']),
            );

            $updateOrder['custom_mark'] = serialize($custom_mark);
        }

        if ($updateOrder) {
            $updateOrder['order_id'] = $tgOrder['order_id'];
        }

        return $updateOrder;
    }

    /**
     * memo_add
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function memo_add($sdf)
    {
        $this->__apilog['original_bn'] = $sdf['order_bn'];
        $this->__apilog['title']       = '添加订单商家备注[' . $sdf['order_bn'] . ']';

        if ($this->__channelObj->get_ver() > '1') {
            $this->__apilog['result']['msg'] = '版本2不走此接口';
            return false;
        }

        // 读取订单
        $orderModel = app::get('ome')->model('orders');

        $filter  = array('order_bn' => $sdf['order_bn'], 'shop_id' => $this->__channelObj->channel['shop_id']);
        $tgOrder = $orderModel->getList('order_id,mark_text,cost_payment,total_amount,final_amount', $filter, 0, 1);
        $tgOrder = $tgOrder[0];

        if (!$tgOrder) {
            $this->__apilog['result']['msg'] = 'ERP订单不存在';
            return false;
        }

        $updateOrder = array();
        if ($sdf['memo']) {
            $mark_text = $tgOrder['mark_text'] ? (array) unserialize($tgOrder['mark_text']) : array();

            $mark_text[] = array(
                'op_name'    => $sdf['sender'],
                'op_time'    => kernel::single('ome_func')->date2time($sdf['add_time']),
                'op_content' => htmlspecialchars($sdf['memo']),
            );

            $updateOrder['mark_text'] = serialize($mark_text);
        }

        if ($sdf['flag']) {
            $updateOrder['mark_type'] = $sdf['flag'];
        }

        if ($updateOrder) {
            $updateOrder['order_id'] = $tgOrder['order_id'];
        }

        return $updateOrder;
    }

    /**
     * memo_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function memo_update($sdf)
    {
        // $this->__apilog['result']['data'] = array('tid'=>$this->_ordersdf['order_bn']);
        $this->__apilog['original_bn'] = $sdf['order_bn'];
        $this->__apilog['title']       = '更新订单商家备注[' . $sdf['order_bn'] . ']';

        if ($this->__channelObj->get_ver() > '1') {
            $this->__apilog['result']['msg'] = '版本2不走此接口';
            return false;
        }

        // 读取订单
        $orderModel = app::get('ome')->model('orders');

        $filter  = array('order_bn' => $sdf['order_bn'], 'shop_id' => $this->__channelObj->channel['shop_id']);
        $tgOrder = $orderModel->getList('order_id,mark_text,cost_payment,total_amount,final_amount', $filter, 0, 1);
        $tgOrder = $tgOrder[0];

        if (!$tgOrder) {
            $this->__apilog['result']['msg'] = 'ERP订单不存在';
            return false;
        }

        $updateOrder = array();
        if ($sdf['memo']) {
            $mark_text = $tgOrder['mark_text'] ? (array) unserialize($tgOrder['mark_text']) : array();

            $mark_text[] = array(
                'op_name'    => $sdf['sender'],
                'op_time'    => kernel::single('ome_func')->date2time($sdf['add_time']),
                'op_content' => htmlspecialchars($sdf['memo']),
            );

            $updateOrder['mark_text'] = serialize($mark_text);
        }

        if ($sdf['flag']) {
            $updateOrder['mark_type'] = $sdf['flag'];
        }

        if ($updateOrder) {
            $updateOrder['order_id'] = $tgOrder['order_id'];
        }

        return $updateOrder;
    }

    /**
     * payment_update
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function payment_update($sdf)
    {
        // $this->__apilog['result']['data'] = array('tid'=>$this->_ordersdf['order_bn']);
        $this->__apilog['original_bn'] = $sdf['order_bn'];
        $this->__apilog['title']       = '更新订单支付方式[' . $sdf['order_bn'] . ']';

        if ($this->__channelObj->get_ver() > '1') {
            $this->__apilog['result']['msg'] = '版本2不走此接口';
            return false;
        }

        // 读取订单
        $orderModel = app::get('ome')->model('orders');

        $filter  = array('order_bn' => $sdf['order_bn'], 'shop_id' => $this->__channelObj->channel['shop_id']);
        $tgOrder = $orderModel->getList('order_id,mark_text,cost_payment,total_amount,final_amount', $filter, 0, 1);
        $tgOrder = $tgOrder[0];

        if (!$tgOrder) {
            $this->__apilog['result']['msg'] = 'ERP订单不存在';
            return false;
        }

        $total_amount = bcsub(bcadd($tgOrder['total_amount'], $sdf['cost_payment'], 3), $tgOrder['cost_payment'], 3);
        $updateOrder  = array(
            'pay_bn'       => $sdf['pay_bn'],
            'payinfo'      => array(
                'pay_name'     => $sdf['payment'],
                'cost_payment' => $sdf['cost_payment'],
            ),
            'cur_amount'   => $total_amount,
            'total_amount' => $total_amount,
            'order_id'     => $tgOrder['order_id'],
        );
        return $updateOrder;
    }

    /**
     * 
     * 订单明细格式化,该方法兼容订单打下来的明细结构，非常重要
     * @param void
     * @return void
     */
    protected function formatItemsSdf()
    {
        //非新的订单明细格式，老的两层结构做格式化
        if (!isset($this->_ordersdf['new_orderobj'])) {
            $adjunctObj = array(); //ec附件
            $giftObj    = array(); //ec商品促销指定商品赠品

            $line_i = 0;
            foreach ($this->_ordersdf['order_objects'] as $k => $object) {
                //如果1个item对1个obj认为是普通商品,item合并到obj层,item矩阵打过来的真实信息
                if (count($object['order_items']) == 1) {
                    $tmp_obj_items = $object['order_items'][0];
                    unset($object['order_items']);
                    $tmp_obj                 = $object;
                    $tmp['order_object'][$k] = array_merge($tmp_obj, $tmp_obj_items);
                }elseif($object['is_transform_obj'] === true){
                    //objectInfo
                    $tmp_obj = $object;
                    unset($tmp_obj['order_items']);
                    
                    //防止冲掉原object层下标
                    $line_i = $line_i + 100;
                    
                    //定制订单转换了object层
                    foreach ($object['order_items'] as $itemKey => $itemInfo)
                    {
                        $line_i++;
                        
                        //objects
                        $tmp['order_object'][$line_i] = array_merge($tmp_obj, $itemInfo);
                        
                        //unset
                        unset($object['order_items'][$itemKey]);
                    }
                }else {
                    //如果是促销组合类，就直接以obj层为准
                    $adjunct_amount = 0;
                    foreach ($object['order_items'] as $item) {
                        $adjunct_flag = false;
                        $gift_flag    = false;
                        if ($item['item_type'] == 'adjunct') {
                            $adjunct_flag = true;
                        }
                        if ($item['item_type'] == 'gift') {
                            $gift_flag = true;
                        }
                        if ($item['status'] != 'close') {
                            if ($adjunct_flag) {
                                //配件对应amount pmt_price要从object里去除 另组一个object
                                $adjunct_amount += $item['amount'];
                                $adjunctObj[] = $item;
                            } elseif ($gift_flag) {
                                $giftObj[] = $item;
                            } else {
                                $object_pmt += (float) $item['pmt_price'];
                            }

                        } else {
                            $is_delete = true;
                        }

                    }
                    $object_pmt += $object['pmt_price'];
                    if ($adjunct_amount > 0) {
                        $object['amount'] = $object['amount'] - $adjunct_amount;
                    }
                    unset($object['order_items']);
                    $tmp_obj           = $object;
                    $tmp_obj['status'] = $is_delete ? 'close' : 'active';
                    //这个定义是否是捆绑无意义，主要看在erp里销售物料是什么类型 by xiayuanjun
                    //if($tmp_obj['obj_type'] != 'pkg'){$tmp_obj['obj_type'] = 'pkg';}
                    $tmp['order_object'][$k] = array_merge($tmp_obj, array('pmt_price' => $object_pmt));
                }
            }
            
            //赋值重组后的数据
            $this->_ordersdf['order_objects'] = array_merge($tmp['order_object'], $adjunctObj, $giftObj);
        }
    }

    /**
     * 生产更换sku相关参数
     */
    protected function formatItemsUpdateSkuSdf()
    {
        //非新的订单明细格式，老的两层结构做格式化
        $order_bn = $this->_ordersdf['order_bn'];
        $new_bns = [];

        foreach ($this->_ordersdf['order_objects'] as $k => $object) {
            if(isset($object['oid'])){
                if(!$new_bns[$object['oid']]){
                    $new_bns[$object['oid']] = [];
                }
                $new_bns[$object['oid']][] = $object['bn'];
            }
        }

        $order_info = app::get('ome')->model('orders')->dump(['order_bn' => $order_bn], 'order_id,order_bool_type');
        if ($order_info) {

            $orderExtendModel = app::get('ome')->model('order_extend');
            $orderExtendInfo = $orderExtendModel->dump(['order_id' => $order_info['order_id']], 'bool_extendstatus');
            if(!$orderExtendInfo){
                return;
            }

            if (!($orderExtendInfo['bool_extendstatus'] & ome_order_bool_extendstatus::__UPDATESKU_ORDER)) {
                return;
            }

            $order_objects = app::get('ome')->model('order_objects')->getList('obj_id,bn,oid', ['order_id' => $order_info['order_id'], 'delete' => 'false']);

            $change_sku = '';
            $old_sku = '';
            $old_obj_id = [];
            $is_change = false;
            foreach ($order_objects as $order_object) {
                if($new_bns[$order_object['oid']] && !in_array($order_object['bn'], $new_bns[$order_object['oid']])){
                    $is_change = true;
                    $change_sku = implode(',', $new_bns[$order_object['oid']]);
                    $old_sku .= $order_object['bn'].' ';
                    $old_obj_id[] = $order_object['obj_id'];
                }
            }
            if(!$is_change){
                return;
            }
            
            $err = '';
            $this->_ordersdf['order_bool_type'] = intval($order_info['order_bool_type']) | ome_order_bool_type::__UPDATEITEM_CODE;
            $this->_ordersdf['change_sku'] = $change_sku;
            $this->_ordersdf['old_sku'] = $old_sku;
            $this->_ordersdf['old_obj_id'] = $old_obj_id;
            kernel::single('ome_bill_label')->markBillLabel($order_info['order_id'], '', 'SOMS_UPDATE_ITEM', 'order', $err, 0);
        }
    }
    
    /**
     * 取消订单
     * @return boolen
     */
    protected function _closeOrder()
    {

        if ($this->_ordersdf['status'] == 'dead' && $this->_tgOrder['status']=='active' &&  $this->_ordersdf['pay_status']=='5' && ($this->_tgOrder['pay_status']=='4' || ($this->_tgOrder['pay_status']=='1' && $this->_ordersdf['payed']==0) ) && $this->_ordersdf['ship_status']=='0'){
            return true;

        }
        return false;
    }

    /**
     * 订单报文校验
     * @param string $source 来源 create 创建订单 update 更新订单
     * @param Array $excludeCheckList 校验排除项,传入指定项目则跳过该项检验
     * @throws Exception
     */
    function _docheck($source = 'create' ,$excludeCheckList = [])
    {
        $checkItemList = [
            'main' => 1, // 主层
            'item_amount' => 1, // 明细原价 
            'item_sale_price' => 1, // 明细扣除商品级优惠
            'item_divide_order_fee' => 1, // 明细扣除商品级&&订单级优惠(明细实付)
            'item_movement_code' => 1, // 赠品明细movement_code
            'sum_cost_item' => 1, // 货品金额汇总 
            'sum_pmt_goods' => 1, // 商品优惠汇总 
            'sum_pmt_order' => 1, // 订单优惠汇总 
            'sum_divide_order_fee' => 1, // 实付金额汇总 
            //'pmt_discount_code' => 1, // discount_code
        ];   
        
        // 进行排除项扣除
        if(!empty($excludeCheckList)){
            foreach ($excludeCheckList as $excludeCheckItem){
                if(isset($checkItemList[$excludeCheckItem])){
                    unset($checkItemList[$excludeCheckItem]);
                }
            }
        }
        
        
        // 支付手续费总额
        $paycost_amount = 0;
        foreach ($this->_ordersdf['payments'] as $value) {
            $paycost_amount += $value['paycost'] ?: 0;
        }
        
        // 验证订单金额是否正确
        $total_amount = (float)$this->_ordersdf['cost_item'] + (float)$this->_ordersdf['shipping']['cost_shipping'] + (float)$this->_ordersdf['shipping']['cost_protect'] + (float)$this->_ordersdf['discount'] + (float)$this->_ordersdf['cost_tax'] + (float)$paycost_amount - (float)$this->_ordersdf['pmt_goods'] - (float)$this->_ordersdf['pmt_order'];

        $total_amount = round($total_amount, 3);
 
        if (isset($checkItemList['main']) && 0 != bccomp($total_amount, $this->_ordersdf['total_amount'], 3)) {
            throw new Exception('订单总金额不正确');
        }

        //验证明细金额是否正确
        // 重新计算优惠金额
        $amount = $pmt_price = $part_mjz_discount = $divide_order_fee = 0;

        foreach ($this->_ordersdf['order_objects'] as $objkey => $obj) {
            //订单是否关单
            if ($obj['status'] == 'close') {
                continue;
            }
            # price *  quantity  = amount
            if (isset($checkItemList['item_amount']) && bccomp($obj['amount'], bcmul($obj['price'], $obj['quantity'], 3), 3)) {
                throw new Exception('商品单价*数量不等于商品原价金额小计！');
            }
            # amount - pmt_price = sale_price
            if (isset($checkItemList['item_sale_price']) && bccomp($obj['sale_price'], bcsub($obj['amount'], $obj['pmt_price'], 3), 3)) {
                throw new Exception('商品原价金额-商品优惠金额不等于商品销售金额！');
            }
            
            # sale_price - part_mjz_discount = divide_order_fee
            if (isset($checkItemList['item_divide_order_fee']) && bccomp($obj['divide_order_fee'], bcsub($obj['sale_price'], $obj['part_mjz_discount'], 3))) {
                throw new Exception('商品销售金额-订单均摊优惠不等于商品实付金额！');
            }

            $amount = bcadd($amount, $obj['amount'], 3);
            $pmt_price = bcadd($pmt_price, $obj['pmt_price'], 3);
            $part_mjz_discount = bcadd($part_mjz_discount, $obj['part_mjz_discount'], 3);
            $divide_order_fee = bcadd($divide_order_fee, $obj['divide_order_fee'], 3);

            //商品是赠品时movement_code必填
            if (isset($checkItemList['item_movement_code']) && $obj['obj_type'] == 'gift' && (!isset($obj['movement_code']) || !$obj['movement_code'])) {
                throw new Exception('赠品明细请填写movement_code！');
            }
        }
        //验证明细金额是否正确
        # sum(amount ) = cost_item
        if (isset($checkItemList['sum_cost_item']) && 0 != bccomp($amount, $this->_ordersdf['cost_item'], 3)) {
            throw new Exception('商品原价金额总和不等于商品总额！');
        }
        # sum(pmt_price ) = pmt_goods
        if (isset($checkItemList['sum_pmt_goods']) && 0 != bccomp($pmt_price, $this->_ordersdf['pmt_goods'], 3)) {
            throw new Exception('子订单商品优惠金额总和不等于商品优惠总金额！');
        }
        # sum(part_mjz_discount ) = pmt_order
        if (isset($checkItemList['sum_pmt_order']) && 0 != bccomp($part_mjz_discount, $this->_ordersdf['pmt_order'], 3)) {
            throw new Exception('子订单均摊优惠总和不等于订单优惠总金额！');
        }

        # 明细层汇总金额与 主层校验,公式sum(divide_order_fee) = total_amount
        # 明细实付金额汇总 + 配送费 + 保价费 + 手动调价金额 + 税金 + 支付手续费总额 = 订单总额

        $check_total_amount = (float)$divide_order_fee + (float)$this->_ordersdf['shipping']['cost_shipping'] + (float)$this->_ordersdf['shipping']['cost_protect'] + (float)$this->_ordersdf['discount'] + (float)$this->_ordersdf['cost_tax'] + (float)$paycost_amount;

        if (isset($checkItemList['sum_divide_order_fee']) && 0 != bccomp($check_total_amount, $this->_ordersdf['total_amount'], 3)) {
            throw new Exception('子订单商品实付金额总和不等于订单总金额');
        }

        /*if (isset($checkItemList['pmt_discount_code']) && $this->_ordersdf['pmt_detail'] && !empty($this->_ordersdf['pmt_detail'])) {
            foreach ($this->_ordersdf['pmt_detail'] as $pmt) {
                if (!$pmt['discount_code'] || !isset($pmt['discount_code'])) {
                    throw new Exception('优惠信息中优惠编码必填');
                }
            }
        }*/

        //校验状态
        if (!isset($this->_ordersdf['status'])) {
            throw new Exception('交易状态不正确');
        }
        if (!isset($this->_ordersdf['pay_status'])) {
            throw new Exception('交易支付状态不正确');
        }
        if (!isset($this->_ordersdf['ship_status'])) {
            throw new Exception('交易物流状态不正确');
        }
    }
    
    /**
     * [加密字段处理]收货人、会员信息加密
     * @todo：平台相关加密方式可参考（语雀-->OMS开发手册-->隐私加密）
     * @todo：注意特殊平台：meituan4medicine、xhs、dangdang(当当是明文)
     * 
     * @return void
     */
    public function _securityHashCode()
    {
        $hashCode = kernel::single('ome_security_hash')->get_code();
        
        //format
        $this->_ordersdf['index_field'] = is_array($this->_ordersdf['index_field']) ? $this->_ordersdf['index_field'] : [];
        $this->_ordersdf['extend_field'] = is_array($this->_ordersdf['extend_field']) ? $this->_ordersdf['extend_field'] : [];
        
        //oaid
        $oaid = ''; $buyer_open_uid = '';
        if ($this->_ordersdf['extend_field']['oaid']) {
            //taobao、tmall
            $oaid = $this->_ordersdf['extend_field']['oaid'];

            $buyer_open_uid = $this->_ordersdf['member_info']['buyer_open_uid'];
        }elseif($this->_ordersdf['index_field']['open_address_id']){
            //luban、pinduoduo、wxshipin
            $oaid = $this->_ordersdf['index_field']['open_address_id'];

            // 如果买家open_uid和买家昵称不一样，则使用买家open_uid， 得物买家直接使用的是密文
            if ( $this->_ordersdf['member_info']['buyer_open_uid'] != $this->_ordersdf['member_info']['uname'] ) {
                $buyer_open_uid = $this->_ordersdf['member_info']['buyer_open_uid'] ?: $this->_ordersdf['index_field']['buyer_uname_index_origin'];
            }
        }elseif($this->_ordersdf['index_field']['index_oaid_field']){
            //alibaba4ascp
            $oaid = $this->_ordersdf['index_field']['index_oaid_field'];

            // 暂无uname，先用oaid替代
            $buyer_open_uid = $oaid;
        }elseif($this->_ordersdf['index_field']['index_caid_field']){
            //alibaba
            $oaid = $this->_ordersdf['index_field']['index_caid_field'];

            // 如果买家open_uid和买家昵称不一样，则使用买家open_uid
            $buyer_open_uid = $this->_ordersdf['member_info']['buyer_open_uid'];
        }elseif($this->_ordersdf['index_field']['ocid']){
            //huawei
            $oaid = $this->_ordersdf['index_field']['ocid'];

            // 买家只有OCID
            $buyer_open_uid = $oaid;
        }elseif($this->_ordersdf['index_field']['index_encrypt_field']){
            //suning
            $oaid = md5($this->_ordersdf['index_field']['index_encrypt_field']);

            // 暂无客户使用
            $buyer_open_uid = $oaid;
        }elseif($this->_ordersdf['index_field']['is_consignee_encrypt'] && $this->__channelObj->channel['shop_type']=='haoshiqi') {
            //haoshiqi
            $oaid = md5(serialize($this->_ordersdf['index_field']));

            // 暂无客户使用
            $buyer_open_uid = $oaid;
        }elseif($this->_ordersdf['extend_field']['performance_type'] == 2 && $this->__channelObj->channel['shop_type']=='dewu') {
            //dewu
            $oaid = md5(serialize($this->_ordersdf['consignee']));

            // 买家是明文，无需处理
        }elseif($this->_ordersdf['extend_field']['openAddressId']){
            //xhs
            $oaid = $this->_ordersdf['extend_field']['openAddressId'];

            // 使用昵称索引
            $buyer_open_uid = $this->_ordersdf['index_field']['buyer_name_index'];
        }
        //微信视频号oaid调整
        if($this->_ordersdf['extend_field']['delivery_info']['ewaybill_order_code'] && $this->__channelObj->channel['shop_type']=='wxshipin'){
            $oaid = $this->_ordersdf['extend_field']['delivery_info']['ewaybill_order_code'];
            // 如果买家open_uid和买家昵称不一样，则使用买家open_uid， 得物买家直接使用的是密文
            if ( $this->_ordersdf['member_info']['buyer_open_uid'] != $this->_ordersdf['member_info']['uname'] ) {
                $buyer_open_uid = $this->_ordersdf['member_info']['buyer_open_uid'] ?: $this->_ordersdf['index_field']['buyer_uname_index_origin'];
            }
        }
        
        //receiver_mobile_index
        $receiver_mobile_index = $this->_ordersdf['index_field']['receiver_mobile_index'];
        $receiver_mobile_index = (!empty($receiver_mobile_index) ? $receiver_mobile_index : $this->_ordersdf['index_field']['receiver_mobile_index_origin']);
        
        //receiver_phone_index
        $receiver_phone_index = $this->_ordersdf['index_field']['receiver_phone_index'];
        $receiver_phone_index = (!empty($receiver_phone_index) ? $receiver_phone_index : $this->_ordersdf['index_field']['receiver_phone_index_origin']);
        
        //receiver_name_index
        $receiver_name_index = $this->_ordersdf['index_field']['receiver_name_index'];
        $receiver_name_index = (!empty($receiver_name_index) ? $receiver_name_index : $this->_ordersdf['index_field']['receiver_name_index_origin']);
        
        //receiver_address_index
        $receiver_address_index = $this->_ordersdf['index_field']['receiver_address_index'];
        $receiver_address_index = (!empty($receiver_address_index) ? $receiver_address_index : $this->_ordersdf['index_field']['receiver_address_index_origin']);
        
        //优先使用oaid进行加密
        if($oaid){
            //consignee
            foreach ($this->_ordersdf['consignee'] as $key => $value) {
                // 指定字段不参与 oaid 加密拼接
                //@todo：sdb_ome_member_address数据库表中ship_email字段只有150个字符长度，不能使用oaid进行加密拼接；
                if (in_array($key, ['email', 'zip', 'ship_zip'])) {
                    continue;
                }
                
                if(strpos($value, '*') !== false) {
                    $this->_ordersdf['consignee'][$key] .= '>>' . $oaid . $hashCode;
                }elseif($key == 'mobile' && $receiver_mobile_index){
                    //mobile
                    $this->_ordersdf['consignee'][$key] .= '>>' . $oaid . $hashCode;
                }elseif($key == 'telephone' && $receiver_phone_index){
                    //telephone
                    $this->_ordersdf['consignee'][$key] .= '>>' . $oaid . $hashCode;
                }elseif($key == 'name' && $receiver_name_index){
                    //name
                    $this->_ordersdf['consignee'][$key] .= '>>' . $oaid . $hashCode;
                }elseif($key == 'addr' && $receiver_address_index){
                    //addr
                    $this->_ordersdf['consignee'][$key] .= '>>' . $oaid . $hashCode;
                }
                
                /****暂时不加此段代码****
                elseif(in_array($key, array('name', 'mobile', 'telephone', 'addr'))){
                    //重要字段没有打*星号(抖音平台订单收货人姓名一个字符时未进行加密)
                    $this->_ordersdf['consignee'][$key] .= '>>' . $oaid . $hashCode;
                }
                ***/
            }
            
            //member_info
            foreach ($this->_ordersdf['member_info'] as $key => $value) {
                //@todo：taobao淘宝平台不需要加密会员信息,保存矩阵给的原信息;
                if(in_array($this->__channelObj->channel['shop_type'], array('taobao'))){
                    continue;
                }
                
                if (in_array($key, array('uname', 'name')) && strpos($value, '*') !== false) {
                    $this->_ordersdf['member_info'][$key] .= '>>' . $buyer_open_uid . $hashCode;
                }

                if (in_array($key, array('mobile', 'tel', 'addr')) && strpos($value, '*') !== false) {
                    $this->_ordersdf['member_info'][$key] .= '>>' . $hashCode;
                }
            }
            
            //保存oaid字段值
            $this->_ordersdf['index_field']['oaid'] = $oaid;
        }else{
            //mobile、member_mobile
            if ($receiver_mobile_index){
                if ($this->_ordersdf['consignee']['mobile'] != $receiver_mobile_index) {
                    $this->_ordersdf['consignee']['mobile'] .= '>>' . $receiver_mobile_index;
                }
                $this->_ordersdf['consignee']['mobile'] .= $hashCode;
                $this->_ordersdf['member_info']['mobile'] = $this->_ordersdf['consignee']['mobile'];
            }
            
            //telephone、member_telephone
            if ($receiver_phone_index) {
                if ($this->_ordersdf['consignee']['telephone'] != $receiver_phone_index) {
                    $this->_ordersdf['consignee']['telephone'] .= '>>' . $receiver_phone_index;
                }
                $this->_ordersdf['consignee']['telephone'] .= $hashCode;
                $this->_ordersdf['member_info']['tel'] = $this->_ordersdf['consignee']['telephone'];
            }
            
            //name
            if ($receiver_name_index) {
                if ($this->_ordersdf['consignee']['name'] != $receiver_name_index) {
                    $this->_ordersdf['consignee']['name'] .= '>>' . $receiver_name_index;
                }
                $this->_ordersdf['consignee']['name'] .= $hashCode;
            }
            
            //addr
            if ($receiver_address_index) {
                if ($this->_ordersdf['consignee']['addr'] != $receiver_address_index) {
                    $this->_ordersdf['consignee']['addr'] .= '>>' . $receiver_address_index;
                }
                $this->_ordersdf['consignee']['addr'] .= $hashCode;
            }
            
            //member_uname（对接平台：tmall、luban、pinduoduo）
            $buyer_uname_index = $this->_ordersdf['index_field']['buyer_uname_index'];
            $buyer_uname_index = (!empty($buyer_uname_index) ? $buyer_uname_index : $this->_ordersdf['index_field']['buyer_uname_index_origin']);
            if ($buyer_uname_index){
                if ($this->_ordersdf['member_info']['uname'] != $buyer_uname_index) {
                    $this->_ordersdf['member_info']['uname'] .= '>>' . $buyer_uname_index;
                }
                $this->_ordersdf['member_info']['uname'] .= $hashCode;
            }
            
            //member_name：对接平台：pingduoduo，淘宝平台、抖音,此字段已经是空值;
            $buyer_name_index = $this->_ordersdf['index_field']['buyer_name_index'];
            $buyer_name_index = (!empty($buyer_name_index) ? $buyer_name_index : $this->_ordersdf['index_field']['buyer_name_index_origin']);
            if ($buyer_name_index) {
                if ($this->_ordersdf['member_info']['name'] != $buyer_name_index) {
                    $this->_ordersdf['member_info']['name']  .= '>>' . $buyer_name_index;
                }
                $this->_ordersdf['member_info']['name']  .= $hashCode;
            }
        }
        
        //payments（对接平台：tmall）
        //@todo：观察下来taobao平台没有此字段值；
        $buyer_alipay_no_index = $this->_ordersdf['index_field']['buyer_alipay_no_index'];
        if ($buyer_alipay_no_index) {
            $this->_ordersdf['member_info']['alipay_no'] .= $hashCode;
            foreach ($this->_ordersdf['payments'] as $key => $value) {
                $this->_ordersdf['payments'][$key]['pay_account'] .= $hashCode;
            }
        }
        
        return true;
    }
}
