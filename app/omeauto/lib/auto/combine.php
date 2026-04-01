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
 * 合并订单
 *
 * @author hzjsq@msn.com
 * @version 0.1b
 */
ini_set('memory_limit', '256M');

define('__STATUS_ON_PAY', 1);
define('__STATUS_MEMO', 2);

class omeauto_auto_combine
{
    /**
     * 订单模块APP名
     * @var String
     */

    const __ORDER_APP = 'ome';

    /**
     * 配置参数
     * @var Array
     */
    static $cnf = null;

    /**
     * 已支付的状态列表
     * @var Array
     */
    static $_PAY_STATUS = array('1', '4');

    /**
     * 插件列表
     * @var Array
     */
    static $_plugObjects = array();

    /**
     * 订单数据缓存
     * @var Array
     */
    private $_group = array();

    private $_instanceItemObjectFrom; #process 审单，dispatch 分派，branch 获取仓库；

    /**
     * 插件组
     */
    private $_plugins;

    //订单来自手工获取还是自动审单
    private $parentClass;
    /**
     * @param Boolean $sys_auto_combine  是否系统自动审单
     */
    public function __construct($parentClass = 'ordertaking')
    {

        $this->parentClass = $parentClass;

        $combine_select = app::get('ome')->getConf('ome.combine.select');

        //不自动合单 或者 系统自动审单_加载的插件组
        if ($combine_select == '1' || $this->parentClass == 'combine') {
            $this->_plugins = array('split','checksplitgift', 'routernum', 'pay', 'flag', 'logi', 'branch', 'store', 'abnormal', 'oversold', 'tbgift', 'crm', 'tax', 'arrived','reclogi', 'refundstatus');
        } else {
            $this->_plugins = array('split','checksplitgift', 'routernum', 'pay', 'flag', 'logi', 'member', 'ordermulti', 'ordersingle', 'branch', 'store', 'abnormal', 'oversold', 'tbgift', 'shopcombine', 'crm', 'tax', 'arrived','reclogi', 'refundstatus');
        }
        
        if (self::getCnf('chkProduct') == 'Y') {
            $this->_plugins[] = 'product';
        }

    }

    /**
     * 订单合并处理
     *
     * @param Array $group 订单组
     * @return Mixed
     * @author hzjsq (2011/3/28)
     */
    public function process($group)
    {
        if (!is_array($group) || empty($group)) {
            return null;
        }
        
        $this->_instanceItemObjectFrom = 'process';
        
        //初始化订单组结构
        $this->_instanceItemObject($group);
        
        //获取审单规则用到的所有订单分组
        $orderFilters = $this->_getAutoOrderFilter();
        
        foreach ($this->_group as $key => $order) {
            foreach ($orderFilters as $filter) {

               // 门店现货走系统默认
               if ($order->getO2oPick() && !$filter->getDefault()){
                    continue;
               }

                if ($filter->vaild($order)) {
                    //加入订单
                    $filter->addItem($order);
                    break;
                }
            }
        }

        //按发组类型开始审单
        $result = array('total' => 0, 'succ' => 0, 'fail' => 0);
        foreach ($orderFilters as $orderGroup) {

            $ret = $orderGroup->process($this->parentClass);

            $result['total'] += $ret['total'];
            $result['succ'] += $ret['succ'];
            $result['fail'] += $ret['fail'];
        }
        return $result;
    }

    /**
     * 订单分派处理
     *
     * @param Array $group 订单组
     * @return Mixed
     * @author hzjsq (2011/3/28)
     */
    public function dispatch($group)
    {
        if (!is_array($group) || empty($group)) {
            return [];
        }
        $this->_instanceItemObjectFrom = 'dispatch';
        //初始化订单组结构
        $this->_instanceItemObject($group);

        //获取审单规则用到的所有订单分组
        $orderFilters = $this->_getAutoOrderFilter();

        foreach ($this->_group as $key => $order) {
            foreach ($orderFilters as $filter) {
                if ($filter->vaild($order)) {
                    return $filter->getConfig();
                    break;
                }
            }
        }

        return [];
    }

    /**
     * 获取所有可用的审单相关订单分组
     *
     * @param void
     * @return mixed
     */
    private function _getAutoOrderFilter()
    {
        $types = kernel::single('omeauto_auto_type')->getAutoOrderTypes();
        
        //[兼容]设置了"虚拟商品拆单规则",则优先使用虚拟拆单规则
        $sql = "SELECT sid FROM `sdb_omeauto_order_split` WHERE split_type='virtualsku'";
        $splitInfo = kernel::database()->selectrow($sql);
        if($splitInfo && $types)
        {
            foreach($types as $key => $type)
            {
                if(empty($type['confirm_config'])){
                    continue;
                }
                
                //虚拟拆单
                $configInfo = unserialize($type['confirm_config']);
                if($configInfo['split_id'] == $splitInfo['sid']){
                    unset($types[$key]);
                    array_unshift($types, $type);
                }
            }
        }
        
        //设置已配置分组
        $filters = array();
        foreach ((array) $types as $type)
        {
            //生效时间范围
            $now_time = time();
            $confirm_config = unserialize($type['confirm_config']);
            if($confirm_config['confirmStartTime'] && $confirm_config['confirmEndTime']){
                if($now_time < $confirm_config['confirmStartTime']){
                    continue; //当前时间小于开始审单时间
                }
                
                if($now_time > $confirm_config['confirmEndTime']){
                    continue; //当前时间大于结束审单时间
                }
            }
            
            //排除时间范围
            if($confirm_config['excludeStartTime'] && $confirm_config['excludeEndTime']){
                if($confirm_config['excludeStartTime']<$now_time && $confirm_config['excludeEndTime']>$now_time){
                    continue; //当前时间在排除审单时间范围内
                }
            }
            
            //[注销]获取的审单config字段
            unset($type['confirm_config']);
            
            $filter = new omeauto_auto_group();
            $filter->setConfig($type);
            $filters[] = $filter;
        }
        
        //增加缺省订单分组
        $filter = new omeauto_auto_group();
        $filter->setDefault();
        $filters[] = $filter;
        
        //返回订单组
        return $filters;
    }

    /**
     * 生成订单结构
     *
     * @param Array $group
     * @retun void
     */
    private function _instanceItemObject($group)
    {
        //准备数据
        $ids  = $this->_mergeGroup($group);
        $filter = array(
            'order_id' => $ids,
            'process_status' => array('unconfirmed', 'confirmed', 'splitting', 'remain_cancel'),
            'is_not_combine' => 0,
        );
        $rows = app::get(self::__ORDER_APP)->model('orders')->getList('*', $filter);
        if (!$rows) {
            return;
        }
        
        $orders = [];
        foreach ($rows as $order) {
            //[标识]是否系统自动审单
            if ($this->parentClass == 'combine') {
                $order['is_sys_auto_combine'] = true;
            }

            // 检测京东订单是否有微信支付先用后付的单据
            $use_before_payed = false;
            if ($order['shop_type'] == '360buy') {
                $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($order['order_id']);
                $labelCode = array_column($labelCode, 'label_code');
                $use_before_payed = kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode);
            }

            if($order['pay_status'] == '3' && $order['step_trade_status']== 'FRONT_PAID_FINAL_NOPAID'){
                $use_before_payed = kernel::single('ome_order_func')->checkPresaleOrder();
            }
            $isAuto = ($order['pay_status'] == '1' || $order['is_cod'] == 'true' || $use_before_payed || $this->_instanceItemObjectFrom == 'branch' || $this->_instanceItemObjectFrom == 'dispatch')
            && !in_array($order['status'], array('finish', 'dead'))
            && in_array($order['order_type'], kernel::single('ome_order_func')->get_normal_order_type())
            && $order['is_fail'] == 'false';
            if ($isAuto) {
                $orders[$order['order_id']] = $order;
            }
        }

        $ids           = array_keys($orders);
        $now           = time();
        $objectsFilter = array(
            'order_id'   => $ids,
            'is_sh_ship' => 'false',
        );
        
        //[预售订单]是否开启hold单(默认开启)
        $isPresaleHold = true;
        $presaleHoldSet = app::get('ome')->getConf('ome.order.presale.hold');
        if($presaleHoldSet == 'false'){
            $isPresaleHold = false;
        }
        
        //[预售订单]hold单时限发货(天猫平台会传estimate_con_time预售发货时间)
        if ($this->_instanceItemObjectFrom == 'process' && $isPresaleHold) {
            $objectsFilter['estimate_con_time|sthan'] = $now;
            $this->_rewriteMiscTask($ids, $now);
        }
        
        $objects = app::get(self::__ORDER_APP)->model('order_objects')->getList('*', $objectsFilter);
        foreach ($objects as $object) {
            // addon json转array
            $object['addon'] = @json_decode($object['addon'], 1);
            if (!$object['addon']) {
                $object['addon'] = [];
            }
            $orders[$object['order_id']]['objects'][$object['obj_id']] = $object;
        }
        
        //增加物流升级服务
        $orderExt = app::get(self::__ORDER_APP)->model('order_extend')->getList('order_id,cpup_service,extend_field',array('order_id'=>$ids));
        if ($orderExt) {
            foreach ($orderExt as $ext) {
                $orders[$ext['order_id']]['cpup_service'] = $ext['cpup_service'];
                $orders[$ext['order_id']]['extend_field'] = $ext['extend_field'] ? json_decode($ext['extend_field'], 1) : [];
            }
        }
        
        //查询订单标签信息
        $billLabels = app::get('ome')->model('bill_label')->getList('bill_id,label_id', array('bill_type' => 'order', 'bill_id' => $ids));
        if ($billLabels) {
            foreach ($billLabels as $label) {
                $orderId = $label['bill_id'];
                if (!isset($orders[$orderId]['labels'])) {
                    $orders[$orderId]['labels'] = array();
                }
                $orders[$orderId]['labels'][] = array('label_id' => $label['label_id']);
            }
        }
        $items = app::get(self::__ORDER_APP)->model('order_items')->getList('*', array(
            'order_id'      => $ids, 
            'obj_id'        => array_column($objects, 'obj_id'),
            'delete'        => 'false', 
            'filter_sql'    => '(split_num < nums)'));
        foreach ($items as $item) {
            if ($orders[$item['order_id']]['objects'][$item['obj_id']]) {
                $item['original_num'] = $item['nums'];
                $orders[$item['order_id']]['objects'][$item['obj_id']]['items'][$item['item_id']] = $item;
            }
        }

        //过滤掉没有明细的订单
        foreach ($orders as $order_id => $order) {
            if ($order['objects']) {
                foreach ($order['objects'] as $ik => $item) {
                    if (empty($item['items'])) {
                        unset($orders[$order_id]['objects'][$ik]);
                    }
                }
            }
            if (empty($orders[$order_id]['objects'])) {
                unset($orders[$order_id]);
            }
        }

        if (empty($orders)) {
            return;
        }

        //生成对像
        foreach ($group as $item) {

            $gOrder = array(); $isO2oPick = false;
            foreach ($item['orders'] as $orderId) {
                if ($orders[$orderId]) {
                    $gOrder[$orderId] = $orders[$orderId];

                   $isO2oPick = $isO2oPick && kernel::single('ome_order_bool_type')->isO2opick($orders[$orderId]['order_bool_type']);
                }
            }
            $this->_group[$item['hash']] = new omeauto_auto_group_item($gOrder);

            if ($isO2oPick === true) {
                $this->_group[$item['hash']]->setO2oPick(true);
            }
        }

        unset($rows);
        unset($order);
    }

    protected function _rewriteMiscTask($ids, $now)
    {
        $objectsFilter = array(
            'order_id'               => $ids,
            'estimate_con_time|than' => $now,
        );
        $objects = app::get(self::__ORDER_APP)->model('order_objects')->getList('order_id,estimate_con_time', $objectsFilter);
        $orders  = array();
        foreach ($objects as $object) {
            if ($object['estimate_con_time']) {
                if (!$orders[$object['order_id']] || $orders[$object['order_id']] > $object['estimate_con_time']) {
                    $orders[$object['order_id']] = $object['estimate_con_time'];
                }
            }
        }
        $maxHoldTime = kernel::single('omeauto_auto_hold')->getMaxHoldTime();
        foreach ($orders as $orderId => $time) {
            app::get(self::__ORDER_APP)->model('orders')->update(array('timing_confirm' => $time), array('order_id' => $orderId));
            if ($time == $maxHoldTime) {
                continue;
            }
            app::get('ome')->model('operation_log')->write_log('order_edit@ome', $orderId, "订单延时定时审单记录写入成功:" . date('Y-m-d H:i:s', $time));
            $task = array(
                'obj_id'    => $orderId,
                'obj_type'  => 'timing_confirm_order',
                'exec_time' => $time,
            );
            app::get('ome')->model('misc_task')->saveMiscTask($task);
        }
    }

    /**
     * 得到订单组结构
     *
     * @param Array $group
     * @retun void
     */
    public function getItemObject($group)
    {
        $this->_instanceItemObjectFrom = 'branch';
        $this->_instanceItemObject($group);
        return $this->_group;
    }

    /**
     * 获取所有订单ID
     *
     * @param Array $group 要处理的订单组结构
     * @return Array
     */
    private function _mergeGroup($group)
    {

        $ids = array();
        foreach ($group as $item) {

            $ids = array_merge($ids, $item['orders']);
        }

        return $ids;
    }

    /**
     * 通过插件名获取插件类并返回
     *
     * @param String $plugName 插件名
     * @return Object
     */
    private function &_instancePlugin($plugName)
    {

        $fullPluginName = sprintf('omeauto_auto_plugin_%s', $plugName);
        
        $fix = md5(strtolower($fullPluginName));

        if (!isset(self::$_plugObjects[$fix])) {

            $obj = new $fullPluginName();
            if ($obj instanceof omeauto_auto_plugin_interface) {

                self::$_plugObjects[$fix] = $obj;
            }
        }
        return self::$_plugObjects[$fix];
    }

    /**
     * 获取配置中的指定变量名
     *
     * @param String $name 参数名
     * @return Mixed
     */
    public static function getCnf($name)
    {
        if (empty(self::$cnf)) {
            self::$cnf = kernel::single('omeauto_config_setting')->getAutoCnf();
        }

        if (isset(self::$cnf[$name])) {
            return self::$cnf[$name];
        } else {
            return '';
        }
    }

    /**
     * 获取缓存时间
     *
     * @param void
     * @return integer
     */
    private function _getBufferTime()
    {
        return time() - self::getCnf('bufferTime') * 60;
    }

    /**
     * 获取所有可操作的订单组
     *
     * @param Integer $bufferTime 缓冲时间
     * @return Array
     */
    public function getBufferGroup($filter = array())
    {
        $bufferTime = $this->_getBufferTime();
        
        $bufferFilter                         = $this->_getBufferFilter();
        $bufferFilter['timing_confirm|sthan'] = time();
        
        if ($filter['shop_id'] && $filter['shop_id'] != 'all') {
            $bufferFilter['shop_id'] = $filter['shop_id'];
        }
        
        //订单类型
        if ($filter['order_type'] && $filter['order_type'] != 'all') {
            $bufferFilter['order_type'] = $filter['order_type'];
        }
        
        //获取所有可处理订单
        $cols     = 'order_id, order_combine_hash, order_combine_idx, pay_status, is_cod, createtime, paytime,step_trade_status';
        $subquery = "SELECT `order_id` 
            FROM `sdb_ome_orders` 
            WHERE " . app::get(self::__ORDER_APP)->model('orders')->_filter($bufferFilter) . " 
            ORDER BY `paytime` ASC 
            LIMIT 0, 5000";
        $orderSql = "SELECT " . $cols . " FROM `sdb_ome_orders` INNER JOIN (" . $subquery . ") `tmp_orders` USING (`order_id`)";
        $this->bufferOrder = kernel::database()->select($orderSql);

        $orderGroup = array();
        if ($this->bufferOrder) {
            //整合数据, 合成订单组
            foreach ($this->bufferOrder as $key => $row) {
                $idx                              = sprintf('%s||%s', $row['order_combine_hash'], $row['order_combine_idx']);
                $combine_select = app::get('ome')->getConf('ome.combine.select');
                if($combine_select !== '0'){
                    $idx .= '||' . $row['order_id']; //未开启自动合并订单
                }
                $orderGroup[$idx]['orders'][$key] = $row['order_id'];
                $orderGroup[$idx]['cnt'] += 1;
            }
            
             //合并订单条数限制
            $orderGroup = $this->_restrictCombineLimit($orderGroup);
            
            //去除无效数据
            foreach ($orderGroup as $key => $group) {
                if ($this->vaildBufferGroup($group['orders'], $bufferTime)) {
                    $orderGroup[$key]['orders'] = join(',', $group['orders']);
                } else {
                    unset($orderGroup[$key]);
                }
            }
        }
        
        return $orderGroup;
    }

    /**
     * 根据订单ID返回与buffer group相同的订单数据结构
     * @param 订单ID $orders
     * @return 订单信息  array
     */
    public function getOrderGroup($ids)
    {
        $orders = app::get(self::__ORDER_APP)->model('orders')->getList('order_id, order_combine_hash, order_combine_idx, pay_status, is_cod, createtime, createtime as paytime', array('order_id' => $ids));

        $orderGroup = array();
        if ($orders) {
            //整合数据, 合成订单组
            foreach ($orders as $key => $row) {
                $idx                              = sprintf('%s||%s', $row['order_combine_hash'], $row['order_combine_idx']);
                $orderGroup[$idx]['orders'][$key] = $row['order_id'];
                $orderGroup[$idx]['cnt'] += 1;
            }

            //去除无效数据
            foreach ($orderGroup as $key => $group) {
                $orderGroup[$key]['orders'] = join(',', $group['orders']);
            }
        }
        
        return $orderGroup;
    }

    /**
     * 检查订单组是否有效
     *
     * @param Array $orders 订单组
     * @param Integer $bufferTime 缓存时间
     * @return Boolean
     */
    private function vaildBufferGroup($orders, $bufferTime)
    {
        $gOrder = array();
        foreach ($orders as $idx => $ordersId) {
            $gOrder[$ordersId] = $this->bufferOrder[$idx];
        }

        $gObj = new omeauto_auto_group_item($gOrder);

        return $gObj->vaildBufferGroup($bufferTime);
    }

    /**
     * 获取缓冲池中订单的过滤条件
     *
     * @author hzjsq (2011/3/24)
     * @param void
     * @return Array
     */
    private function _getBufferFilter()
    {
        if(kernel::single('ome_order_func')->checkPresaleOrder()){
            return array('order_confirm_filter' => '(op_id IS NULL AND group_id IS NULL AND ((is_cod=\'true\' and pay_status=\'0\')  or (pay_status in(\'3\') AND step_trade_status in(\'FRONT_PAID_FINAL_NOPAID\')) or pay_status in (\'1\',\'4\')))', 'status' => 'active', 'ship_status' => '0', 'f_ship_status' => '0', 'confirm' => 'N', 'abnormal' => 'false', 'is_auto' => 'false', 'is_fail' => 'false', 'pause' => 'false', 'is_not_combine'=>0, 'order_type|in' => kernel::single('ome_order_func')->get_normal_order_type());
        }else{
            return array('order_confirm_filter' => '(op_id IS NULL AND group_id IS NULL AND ((is_cod=\'true\' and pay_status=\'0\') or pay_status in (\'1\')))', 'status' => 'active', 'ship_status' => '0', 'f_ship_status' => '0', 'confirm' => 'N', 'abnormal' => 'false', 'is_auto' => 'false', 'is_fail' => 'false', 'pause' => 'false', 'is_not_combine'=>0, 'order_type|in' => kernel::single('ome_order_func')->get_normal_order_type());
        }
    }

    /**
     * 通过输入的错误标志显示获取对应的错误信息
     *
     * @param Integer $status 错误标志
     * @prams Array $order 订单信息
     * @return Array
     */
    public function fetchAlertMsg($staus, $order)
    {
        if ($staus == 0) {
            return array();
        }
        
        $result = array();
        foreach ($this->_plugins as $plug) {
            $obj = $this->_instancePlugin($plug);
            if (is_object($obj)) {
                $_msg = $obj->getMsgFlag();
                if (($staus & $_msg) > 0) {
                    $result[] = $obj->getAlertMsg($order);
                }
            }
        }
        
        $mark    = kernel::single('omeauto_auto_group_mark');
        $mResult = $mark->fetchAlertMsg($staus, $order);
        $result  = array_merge($result, $mResult);

        return $result;
    }

    /**
     * 获取各种状态的标志位及对应信息
     *
     * @param Void
     * @return Array
     */
    public function getErrorFlags()
    {
        $result = array();
        foreach ($this->_plugins as $plug) {
            $obj = $this->_instancePlugin($plug);
            if (is_object($obj)) {
                $_msg          = $obj->getMsgFlag();
                $result[$_msg] = $obj->getTitle();
            }
        }

        return $result;
    }

    /**
     * 转换订单格式
     *
     * @param array $o订单数组
     * @return array
     */
    private function convertOrderFormat($o)
    {
        $orderExtMdl = app::get('ome')->model('order_extend');
        
        //数据格式转换
        $difftime       = kernel::single('ome_func')->toTimeDiff(time(), $o['createtime']);
        $o['difftime']  = $difftime['d'] . '天' . $difftime['h'] . '小时' . $difftime['m'] . '分';
        $markShowMethod = app::get('ome')->getConf('ome.order.mark');
        if ($markShowMethod == 'all') {
            $o['mark_text']   = $this->_formatMemo(unserialize($o['mark_text']));
            $o['custom_mark'] = $this->_formatMemo(unserialize($o['custom_mark']));
        } else {
            $mark_text = unserialize($o['mark_text']);
            $mark_text        = is_array($mark_text) ? array_pop($mark_text) : [];
            $custom_mark      = unserialize($o['custom_mark']);
            $custom_mark      = is_array($custom_mark) ? array_pop($custom_mark) : [];
            $o['mark_text']   = $mark_text['op_content'];
            $o['custom_mark'] = $custom_mark['op_content'];
        }

        //淘宝订单是否优惠赠品
        if ($o['shop_type'] == 'taobao' && $o['abnormal_status'] > 0 && (($o['abnormal_status'] & ome_preprocess_const::__HASGIFT_CODE) == ome_preprocess_const::__HASGIFT_CODE)) {
            $tbgiftOrderItemsObj = app::get('ome')->model('tbgift_order_items');
            $tmp_tbgifts         = $tbgiftOrderItemsObj->getList('*', array('order_id' => $o['order_id']), 0, -1);
            $o['tbgifts']        = $tmp_tbgifts;
            $o['has_tbgifts']    = 1;
        }

        $o['items'] = app::get(self::__ORDER_APP)->model('orders')->getItemBranchStore($o['order_id']);
        // 汇总本订单已出现过库存的仓库ID（branch_store 的 key）
        // 非拆单页面：只有当所有商品都有足够库存时，门店仓才可用
        $hasStoreBranch = array();
        if (!empty($o['items']['goods']) && is_array($o['items']['goods'])) {
            foreach ($o['items']['goods'] as $objId => $object) {
                if (empty($object['order_items']) || !is_array($object['order_items'])) continue;
                foreach ($object['order_items'] as $pid => $product) {
                    if (!empty($product['branch_store']) && is_array($product['branch_store'])) {
                        foreach ($product['branch_store'] as $bid => $availableStock) {
                            $requiredQty = $product['nums']; // 订单需要的数量
                            
                            // 判断库存是否足够
                            if (intval($availableStock) >= $requiredQty) {
                                // 库存足够，标记门店仓为可用
                                $hasStoreBranch[(int)$bid] = true;
                            } else {
                                // 库存不足，如果该门店仓已经被标记为可用，则移除
                                if (isset($hasStoreBranch[(int)$bid])) {
                                    unset($hasStoreBranch[(int)$bid]);
                                }
                            }
                        }
                    }
                }
            }
        }
        $o['has_store_branch'] = array_keys($hasStoreBranch);
        //地区数据转换
        $consignee['area']      = $o['ship_area'];
        $consignee['addr']      = $o['ship_addr'];
        $consignee['name']      = $o['ship_name'];
        $consignee['mobile']    = $o['ship_mobile'];
        $consignee['hash']      = md5(join('-', $consignee));
        $consignee['telephone'] = $o['ship_tel'];
        $consignee['r_time']    = $o['r_time'];
        $consignee['email']     = $o['ship_email'];
        $consignee['zip']       = $o['ship_zip'];
        
        $o['consignee']         = $consignee;
        
        //读取店铺名称
        $shop           = app::get(self::__ORDER_APP)->model('shop')->getList('name', array('shop_id' => $o['shop_id']), 0, 1);
        $o['shop_name'] = $shop[0]['name'];
        if ($o['member_id']) {
            $member           = app::get(self::__ORDER_APP)->model('members')->getList('uname', array('member_id' => $o['member_id']), 0, 1);
            $o['member_name'] = $member[0]['uname'];
        } else {
            $o['member_name'] = '无用户';
        }
        $o['identifier'] = kernel::single('ome_order_bool_type')->getBoolTypeIdentifier($o['order_bool_type'], $o['shop_type'], true, $o['order_id']);

        //[拆单]合线
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');

        //转换数据  addon
        foreach ($o['items'] as $type => $item) {
            foreach ($item as $objId => $object) {
                foreach ($object['order_items'] as $pid => $product) {
                    $o['items'][$type][$objId]['order_items'][$pid]['bn']            = preg_replace('/^:::/is', '', $o['items'][$type][$objId]['order_items'][$pid]['bn']);
                    $o['items'][$type][$objId]['order_items'][$pid]['max_left_nums'] = $product['left_nums'];

                    if ($o['items'][$type][$objId]['order_items'][$pid]['bn'] == '___') {
                        $o['items'][$type][$objId]['order_items'][$pid]['bn'] = '-';
                    }
                    if (empty($product['addon'])) {

                        $o['items'][$type][$objId]['order_items'][$pid]['addon'] = '-';
                    } else {
                        $spec = '';
                        $tmp  = unserialize($product['addon']);

                        if ($tmp) {
                            foreach ($tmp['product_attr'] as $value) {
                                $spec .= sprintf("%s：%s", $value['label'], $value['value']);
                            }
                            $o['items'][$type][$objId]['order_items'][$pid]['addon'] = $spec;
                        }
                    }

                    $pinfo                                                    = $basicMaterialExtObj->dump($product['product_id'], 'weight');
                    $o['items'][$type][$objId]['order_items'][$pid]['weight'] = $pinfo['weight'];
                }

                if ($object['obj_type'] == 'pkg') {
                    $pkgGood = $salesMaterialExtObj->dump(array('sm_id' => $object['goods_id']), 'weight');
                    if ($pkgGood) {
                        $o['items'][$type][$objId]['weight'] = $pkgGood['weight'];
                    }

                    $o['items'][$type][$objId]['max_left_nums'] = $object['left_nums'];
                }
                if ($object['obj_type'] == 'lkb') {
                    $lkbGood = $salesMaterialExtObj->dump(array('sm_id' => $object['goods_id']), 'weight');
                    if ($lkbGood) {
                        $o['items'][$type][$objId]['weight'] = $lkbGood['weight'];
                    }
                    $o['items'][$type][$objId]['max_left_nums'] = $object['left_nums'];
                }
            }
        }
        
        //order_extend
        $fields = 'cpup_service,order_id,promise_service';
        $orderExt = $orderExtMdl->db_dump($o['order_id']);
        $o['cpup_service'] = explode(',', $orderExt['cpup_service']);
        $o['promise_service'] = $orderExt['promise_service'];

        return $o;
    }

    /**
     * 获取订单无用户信息情况下的过渡条件
     *
     * @param Array $order
     * @return Array
     */
    public function _getNullMemberFilter($order)
    {
        $filter       = array();
        $memberidconf = intval(app::get('ome')->getConf('ome.combine.memberidconf'));
        $memberidconf = $memberidconf == '1' ? '1' : '0';
        if ($memberidconf == '0') {
            $filter['order_id'] = $order['order_id'];
        } else {
            $filter = $this->_getAddrFilter($order);
        }
        return $filter;
    }

    /**
     * 获取地址一致的过渡条件
     *
     * @param Array $order
     * @return Array
     */
    public function _getAddrFilter($order)
    {
        $filter       = array();
        $combine_conf = app::get('ome')->getConf('ome.combine.addressconf');
        $ship_address = intval($combine_conf['ship_address']) == '1' ? '1' : '0';
        $mobile       = intval($combine_conf['mobile']) == '1' ? '1' : '0';

        if ($ship_address == '0') {
            $filter['ship_name'] = $order['ship_name'];
            $filter['ship_area'] = $order['ship_area'];
            $filter['ship_addr'] = $order['ship_addr'];
            
            if($order['shop_type'] == 'taobao' && strpos($order['ship_name'], '>>') !== false) {
                unset($filter['ship_addr']);
            }
            
            $filter['no_encrypt'] = true;
        }
        
        if ($mobile == '0') {
            $filter['ship_mobile'] = $order['ship_mobile'];
            $filter['no_encrypt'] = true;
        }

        return $filter;
    }

    private function _getCombineConf(&$combine_member_id, &$combine_shop_id)
    {
        if (strval(app::get('ome')->getConf('ome.combine.member_id')) == '0') {
            $combine_member_id = false;
        }
        
        if (strval(app::get('ome')->getConf('ome.combine.shop_id')) == '0') {
            $combine_shop_id = false;
        }
    }

    /**
     * 获取相关可以合并订单
     *
     * @param Array $order 订单内容
     * @return Array
     */
    public function fetchCombineOrder($order)
    {
        //初始化变量
        $ids               = array();
        $orders            = array();
        $combine_member_id = true;
        $combine_shop_id   = true;
        
        // 是否支持手工合单（On：开启，Off：关闭）
        //@todo：手工审单界面才会有此字段;
        $is_manual_merge = isset($order['is_manual_merge']) ? $order['is_manual_merge'] : null;
        
        //统一查询收获相关信息，以免抛进来的不一致
        $order     = app::get(self::__ORDER_APP)->model('orders')->getList('*', array('order_id' => $order['order_id']));
        $order     = $order[0];
        $orderHash = $order['order_combine_hash'];
        $orderIdx  = $order['order_combine_idx'];

        //新增合单逻辑
        $this->_getCombineConf($combine_member_id, $combine_shop_id);
        
        //基础过滤条件[增加部分发货、部分退货 可继续审单
        $filter = array('ship_status' => array(0, 2, 3), 'process_status' => array('unconfirmed', 'confirmed', 'splitting'), 'status' => 'active', 'order_bn|noequal' => '0', 'is_cod' => $order['is_cod']);
        
        // 过滤不允许审核的订单
        $filter['is_not_combine'] = 0;
        
        if ($order['shop_type'] == 'shopex_b2b') {
            //分销单,对支持跨店合的参数无视,直接内置规则处理
            if ($combine_member_id) {
                //需判断同一用户，因分销没有实际客户信息，以无用户信息方式处理
                if (empty($order['member_id'])) {
                    $filter['order_id'] = $order['order_id'];
                } else {
                    $filter['member_id'] = $order['member_id'];
                    $filter['shop_id']   = $order['shop_id'];
                    $filter              = array_merge($filter, $this->_getNullMemberFilter($order));
                }
            } else {
                //检查是否导入订单
                if (empty($order['member_id'])) {
                    //如是导入的无用户订单，则无法判定前端销售的实际店铺，只取出当前订单
                    $filter['order_id'] = $order['order_id'];
                } else {
                    //有用户名,可确认前端销售的实际店铺
                    $filter['member_id'] = $order['member_id'];
                    $filter['shop_id']   = $order['shop_id'];
                    //判定地址一致
                    $filter = array_merge($filter, $this->_getAddrFilter($order));
                }
            }
        } else if ($order['shop_type'] == 'dangdang' && $order['is_cod'] == 'true') {
            //当当，且是货到付款不合并
            $filter['order_id'] = $order['order_id'];
        } else if (($order['shop_type'] == 'amazon') && $order['self_delivery'] == 'false') {
            //如果店铺类型是亚马逊，且不是自发货的不合并
            $filter['order_id'] = $order['order_id'];
        } else if ($order['shop_type'] == 'aikucun') {
            //爱库存不可合并
            $filter['order_id'] = $order['order_id'];
        } else if ($order['shop_type'] == 'taobao' && $order['order_source'] == 'tbdx') {
            //淘宝代销订单不合并 823修改淘代销走B2B逻辑
            if ($combine_member_id) {
                //需判断同一用户，因分销没有实际客户信息，以无用户信息方式处理
                if (empty($order['member_id'])) {
                    $filter['order_id'] = $order['order_id'];
                } else {
                    $filter['member_id'] = $order['member_id'];
                    $filter['shop_id']   = $order['shop_id'];
                    $filter              = array_merge($filter, $this->_getNullMemberFilter($order));
                }

            } else {
                //检查是否导入订单
                if (empty($order['member_id'])) {
                    //如是导入的无用户订单，则无法判定前端销售的实际店铺，只取出当前订单
                    $filter['order_id'] = $order['order_id'];
                } else {
                    //有用户名,可确认前端销售的实际店铺
                    $filter['member_id'] = $order['member_id'];
                    $filter['shop_id']   = $order['shop_id'];
                    //判定地址一致
                    $filter = array_merge($filter, $this->_getAddrFilter($order));

                }
            }
        } else if (($order['shop_type'] == 'taobao'
                && (kernel::single('ome_order_bool_type')->isCnService($order['order_bool_type'])
                    || $order['order_source'] == 'maochao')
            )
            || $order['order_type'] == 'vopczc') {
            $filter['order_id'] = $order['order_id'];
        } else {
            //直销单
            if ($combine_member_id) {
                if (empty($order['member_id'])) {
                    //以无用户信息方式处理
                    $filter = array_merge($filter, $this->_getNullMemberFilter($order));
                } else {
                    //有用户名
                    $filter['member_id'] = $order['member_id'];
                }
            } else {
                //判定地址
                $filter = array_merge($filter, $this->_getAddrFilter($order));
            }
            
            if ($combine_shop_id) {
                $filter['shop_id'] = $order['shop_id'];
            }

            if ($order['shop_type'] == 'vop') {
                $filter['order_combine_hash'] = $orderHash;
            }

            if ($order['shop_type'] == '360buy') {
                // 是否集运，如果是集运，不能和非集运的合单
                $jyInfo = kernel::single('ome_bill_label')->getBillLabelInfo($order['order_id'], 'order', 'SOMS_GNJY');
                if ($jyInfo) {
                    $filter['order_combine_hash'] = $orderHash;
                }
            }

            //排除b2b单否则如果相同地址的单子由普通订单点入时，会显示出b2b单在可合并中
            $filter['filter_sql'] = "(shop_type IS NOT NULL AND order_source<>'tbdx' and shop_type<>'shopex_b2b' and (is_cod='false' or (shop_type<>'dangdang' AND is_cod='true')) and (self_delivery='true' or (shop_type<>'amazon' and self_delivery='false')) OR shop_type IS NULL)";
    
            //京东厂直
            if ($order['shop_type'] == 'jd' && kernel::single('ome_bill_label')->getBillLabelInfo($order['order_id'], 'order', kernel::single('ome_bill_label')->isSomsGxd())) {
                $filter['order_id'] = $order['order_id'];//工小达 未接合单
            }
        }
        
        //[开启拆单]部分拆分-部分发货-部分退货的订单 不允许合单
        if ($order['process_status'] == 'splitting' && $order['ship_status'] == '3') {
            $filter['order_id'] = $order['order_id'];
        }elseif($order['betc_id'] && $order['cos_id']){
            //分销一件代发订单,不允许合单
            $filter['order_id'] = $order['order_id'];
        }elseif($is_manual_merge == 'Off'){
            // 不允许手工合单（配置项：系统设置--》订单规则--》支持手工合单）
            $filter['order_id'] = $order['order_id'];
        }
        
        //获取相关订单
        $row = app::get(self::__ORDER_APP)->model('orders')->getList('*', $filter);
        
        if (!empty($order['member_id'])) {
            if ($order['shop_type'] == 'shopex_b2b') {
                $tmp = array();
            } else if ($order['shop_type'] == 'dangdang' && $order['is_cod'] == 'true') {
                $tmp = array();
            } else if ($order['shop_type'] == 'amazon' && $order['self_delivery'] == 'false') {
                $tmp = array();
            } else if ($order['shop_type'] == 'aikucun') {
                $tmp = array();
            } else if ($order['shop_type'] == 'taobao' && ($order['order_source'] == 'tbdx' || $order['order_source'] == 'maochao')) {
                $tmp = array();
            } elseif ($order['shop_type'] == 'vop') {
                $tmp = array();
            } elseif ($order['shop_type'] == '360buy' && kernel::single('ome_bill_label')->getBillLabelInfo($order['order_id'], 'order', 'SOMS_GNJY')) {
                // 如果是集运，不能和非集运的合单
                $tmp = array();
            } elseif ($order['shop_type'] == 'jd' && kernel::single('ome_bill_label')->getBillLabelInfo($order['order_id'], 'order', kernel::single('ome_bill_label')->isSomsGxd())) {
                // 京东厂直 工小达 未接合单
                $tmp = array();
            } elseif($is_manual_merge == 'Off'){
                // 人工审单界面：不允许手工合单
                $tmp = array();
            } else {
                // 拆单_增加确认状态('splitting')与发货状态(ship_status=2)部分发货条件
                $tmp_filter = array('member_id' => $order['member_id'], 'shop_id' => $order['shop_id'], 'status' => 'active', 'process_status' => array('unconfirmed', 'confirmed', 'splitting'), 'ship_status' => array(0, 2), 'f_ship_status' => '0', 'order_bn|noequal' => '0', 'is_cod' => $order['is_cod']);
                
                // 过滤不允许审核的订单
                $tmp_filter['is_not_combine'] = 0;
                
                $tmp = app::get(self::__ORDER_APP)->model('orders')->getList('*', $tmp_filter);

                $row = array_merge($row, $tmp);
                unset($tmp);
            }
        }

        foreach ((array) $row as $o) {
            if (!in_array($o['order_id'], $ids)) {
                if ($o['order_combine_idx'] == $orderIdx && $o['order_combine_hash'] == $orderHash) {
                    $o['isCombine'] = true;
                } else {
                    $o['isCombine'] = false;

                }
                $o['is_encrypt']  = kernel::single('ome_security_router',$o['shop_type'])->show_encrypt($o,'order');

                $orders[$o['order_id']] = $this->convertOrderFormat($o);
                $ids[]                  = $o['order_id'];
            }
        }
        unset($row);

        return $orders;
    }

    /**
     * 获该用户除指定用户外的所有订单数
     *
     * @param Integer $memberId 会员编号
     * @param Integer $shopId 店铺ID
     * @return Integer
     */
    public function getCombineMemberCount($memberId, $shopId)
    {

        $row = app::get(self::__ORDER_APP)->model('orders')->count(array('member_id' => $memberId, 'shop_id'                             => $shopId, 'status'    => 'active',
            'process_status'                                                             => array('unconfirmed', 'confirmed'), 'ship_status' => '0', 'f_ship_status' => '0', 'order_bn|noequal' => '0'));
        return $row;
    }

    /**
     * 获取备注及留言的显示格式信息
     *
     * @param Arrar $input 输入的内容
     * @return String
     */
    private function _formatMemo($input)
    {

        $result = '';
        if (is_array($input)) {

            foreach ($input as $memo) {

                $result .= sprintf("%s\n", $memo['op_content']);
            }
        }

        return $result;
    }
    
    /**
     * 生成发货单
     *
     * @param $orderIds
     * @param $consignee
     * @param $corpId
     * @param $splitting_product
     * @param $errmsg
     * @param $split_auto
     * @param $extendInfo 扩展信息：例如指定发货单号['delivery_bn'=>'****']
     * @return bool|void
     */
    public function mkDelivery($orderIds, $consignee, $corpId, $splitting_product = array(), &$errmsg=null, $split_auto = array(), $extendInfo=array())
    {
        $orderIds = is_array($orderIds) ? $orderIds : [$orderIds];
        if($corpId == 'auto') {
            $corp = ['corp_id'=>'auto'];
        } else {
            $corp = app::get('ome')->model('dly_corp')->dump($corpId, 'corp_id, name, type, is_cod, weight');
        }
        
        // orders
        $rows = app::get(self::__ORDER_APP)->model('orders')->getList('*', array('order_id'=>$orderIds, 'is_not_combine'=>0));
        if(empty($rows)){
            $errmsg = '没有可生成发货单的订单!';
            return false;
        }
        
        $shop_group      = array();
        $createway_group = array();
        foreach ($rows as $order) {
            $shop_group[$order['shop_id']]        = $order['shop_id'];
            $createway_group[$order['createway']] = $order['createway'];
            if(kernel::single('ome_order_bool_type')->isJDLVMI($order['order_bool_type'])){
                $order_id_arr[$order['order_id']] = $order['order_id'];
            }
        }

        if($order_id_arr){
            $extends = array();
            $extendData = app::get('ome')->model('order_extend')->getList('presale_pay_status,order_id,platform_logi_no', array('order_id'=>$order_id_arr));
            foreach($extendData as $ev) {
                $extends[$ev['order_id']] = $ev;
            }
        }
        
        $is_check_channel = false;
        foreach ($rows as $order) {
            // 验证发货数据
            if ($order['createway'] != 'matrix' && $order['order_source'] != 'platformexchange' && kernel::single('ome_security_router', $order['shop_type'])->is_encrypt(array('consignee' => $consignee), 'order')) {
                //补发订单是原平台订单的加密收货人信息
                if(in_array($order['order_type'], array('bufa'))){
                    //--不需要检查收货信息
                }else{
                    $errmsg = '收货信息异常，请重新编辑';
                    return false;
                }
            }
            if(empty($order['ship_addr'])) {
                $errmsg = '收货地址不能为空';
                return false;
            }

            // 京东/拼多多店铺订单不能和其他平台订单合并
            if (count($orderIds) > 1 && in_array($order['shop_type'], array('360buy', 'pinduoduo')) && $order['createway'] == 'matrix' && (count($shop_group) > 1 || count($createway_group) > 1)) {
                $errmsg = '京东/拼多多订单线上线下不能合并';
                return false;
            }

            if (count($orderIds) > 1
                && (in_array($order['shop_type'], array('aikucun', 'yangsc'))
                    || !kernel::single('ome_branch')->isCanMerge($consignee['branch_id']))) {
                $errmsg = '订单不能合单';
                return false;
            }
            
            if($order['pay_status']=='3' && !kernel::single('ome_order_func')->checkPresaleOrder()){
                $errmsg = '部分支付不可以审单';
                return false;
            }
            
            $is_part_split = false;
            if($order['process_status'] == 'splitting') $is_part_split  = true;

            // 得物品牌直发不支持合单
            if (count($orderIds) > 1 && strtolower($order['shop_type']) == 'dewu' && kernel::single('ome_order_bool_type')->isDWBrand($order['order_bool_type'])) {
                $errmsg = '得物品牌直发订单不能合单';
                return false;
            }

            // 得物品牌直发不支持拆单
            if ($is_part_split && strtolower($order['shop_type']) == 'dewu' && kernel::single('ome_order_bool_type')->isDWBrand($order['order_bool_type'])) {
                $errmsg = '得物品牌直发订单不能拆单';
                return false;
            }

            if (!in_array($order['order_type'], kernel::single('ome_order_func')->get_normal_order_type())) {
                $errmsg = kernel::single('ome_order_func')->get_order_type($order['order_type']) . '不能生成发货单';
                return false;
            }

            if($order['source_status'] == 'TRADE_CLOSED') {
                $errmsg = '订单平台已取消，不能生成发货单';
                return false;
            }

            if($order['shop_type'] == 'luban' 
               && $order['createway'] == 'matrix'
               && in_array($order['source_status'], array('WAIT_SELLER_SEND_GOODS'))
            ){
                $errmsg = '抖音订单尚在风控，未进入备货中';
                return false;
            }

            // HOLD单
            if ($order['is_delivery'] == 'N'){
                $errmsg = '订单审核中，暂不能发货';
                return false;
            }
            
            $orders[$order['order_id']] = $order;
            
            //[抖音平台]订单标识
            if(in_array($order['shop_type'], array('luban'))){
                $is_check_channel = true;
            }
            
            // 判读是否是指定仓
            if (kernel::single('ome_order_bool_type')->isJDLVMI($order['order_bool_type']) ) {
                if(empty($consignee['waybillCode']) || !isset($consignee['waybillCode'])){
                    $consignee['waybillCode'] = $extends[$order['order_id']]['platform_logi_no'];
                }
                
                // 不允许合单
                 if (count($rows) >= 2) {
                    return false;
                 }
                 
                $store_code = app::get('ome')->model('order_objects')->db_dump(array ('order_id' => $order['order_id']), 'store_code');

                if ($store_code['store_code']) {
                    $branch_relation = app::get('ome')->model('branch_relation')->db_dump(array (
                        'relation_branch_bn' => $store_code['store_code'],
                        'type' => 'jdlvmi',
                    ));

                    if ($branch_relation['branch_id'] != $consignee['branch_id']) {
                        return false;
                    }   
                }
            }
        }
        
        // order_objects
        $objects = app::get(self::__ORDER_APP)->model('order_objects')->getList('*', array('order_id' => $orderIds, 'is_sh_ship' => 'false'));
        foreach ($objects as $object) {
            $object['addon'] = @json_decode($object['addon'], 1);
            if (!$object['addon']) {
                $object['addon'] = [];
            }
            $orders[$object['order_id']]['objects'][$object['obj_id']] = $object;
        }

        //[拆单]订单明细条件
        $filter_sql = array(' nums > sendnum');
        
        //是否是拆的订单
        if ($splitting_product && count($orderIds) == 1) {
            $type_filter = array();
            foreach ($splitting_product as $item_type => $product) {
                foreach ($product as $product_id => $nums) {
                    $type_filter[] = ' (product_id="' . $product_id . '" and item_type="' . $item_type . '" ) ';
                }
            }

            if ($type_filter) {
                $filter_sql[] = '(' . implode(' OR ', $type_filter) . ')'; //过滤删除的商品
            }
        }
        
        // filter
        $filter = array(
            'order_id'   => $orderIds,
            'obj_id'     => array_column($objects, 'obj_id'),
            'delete'     => 'false',
            'filter_sql' => implode(' AND ', $filter_sql),
        );

        $items = app::get(self::__ORDER_APP)->model('order_items')->getList('*', $filter);
        foreach ($items as $item) {
            $obj_ids[] = $item['obj_id'];
        }

        $objects = app::get(self::__ORDER_APP)->model('order_objects')->getList('*', array('obj_id' => $obj_ids));
        foreach ($objects as $ok => $object) {
            $object['addon'] = @json_decode($object['addon'], 1);
            if (!$object['addon']) {
                $object['addon'] = [];
            }
            $tmp_objects[$object['obj_id']] = $object;
        }

        //重组数据
        $orderSplitLib = kernel::single('ome_order_split');
        $orders        = $orderSplitLib->format_mkDelivery($orders, $tmp_objects, $items, $splitting_product);
        
        // 过滤掉没有明细的订单
        foreach ($orders as $order_id => $order) {
            foreach ($order['objects'] as $ok => $object) {
                if (empty($object['items'])) {
                    unset($orders[$order_id]['objects'][$ok]);
                } else {
                    // nums变成可拆分数量 split_num需要重置为0
                    foreach ($object['items'] as $ik => $iv) {
                        $orders[$order_id]['objects'][$ok]['items'][$ik]['split_num'] = 0;
                        // 震坤行不支持按行拆分
                        if ($rows[0]['shop_type'] == 'zkh') {
                            if ($iv['nums'] > 0 && $iv['original_num'] != $iv['nums']) {
                                $errmsg = '震坤行订单不支持按数量拆分订单！';
                                return false;
                            }
                        }
                    }

                    // 判断行明细是否有退款
                    if ($object['pay_status'] == '5') {
                        $errmsg = $object['bn'].' '.'已退款不能审核';

                        return false;
                    }
                }
            }
            
            if (empty($orders[$order_id]['objects'])) {
                unset($orders[$order_id]);
            }
            
            if($orders[$order_id]) {
                list($rs, $rsData) = kernel::single('ome_order_refund')->checkRefundStatus($orders[$order_id]);
                if($rs) {
                    $errmsg = '检查退款失败：'.$rsData['msg'];
                    return false;
                }
            }
        }
        
        unset($rows);

        //没有可操作的有效订单(例如：订单没有商品明细)
        if (empty($orders)) {
            $errmsg = '没有可操作的有效订单或库存不足';
            return false;
        }
        
        //[抖音平台]关联京东云交易渠道ID
        if($is_check_channel){
            $branchLib = kernel::single('ome_branch');
            $wms_type = $branchLib->getNodetypBybranchId($consignee['branch_id']);
            if($wms_type == 'yjdf'){
                $orderLib = kernel::single('ome_order');
                $error_msg = '';
                $orders = $orderLib->getOrderProductChannelId($orders, $error_msg);
                if(!$orders){
                    $errmsg = '错误：'.$error_msg;
                    return false;
                }
            }
        }
        
        list($rs, $rsData) = kernel::single('material_basic_material_stock_freeze')->deleteOrderBranchFreeze(array_column($orders, 'order_id'));
        if(!$rs) {
            $errmsg = '错误：'.$rsData['msg'];
            return false;
        }
        
        // canMkDelivery
        $group = new omeauto_auto_group_item($orders);
        if ($group->canMkDelivery()) {
            if (!empty($corp)) {
                $branchId = $consignee['branch_id'];
                
                // 菜鸟的智选物流，会返回物流单号
                $waybill_arr = explode(',', $consignee['waybillCode']);
                $group->setWaybillCode($waybill_arr[0]);
                $group->setSubWaybillCode(array_slice($waybill_arr, 1));
                
                unset($consignee['branch_id'], $consignee['waybillCode']);
                
                $errmsg = '库存不足，生成发货单失败';
                
                foreach ($orders as $o_k => $o_v) {
                    if ($o_v['shop_type'] == 'kuaishou') {
                        kernel::single('omeauto_auto_plugin_checksplitgift')->process($group);
                        $groupStatus = $group->getStatus();
                        if ($groupStatus['opt'] == omeauto_auto_group_item::__OPT_HOLD) {
                            $errmsg = '仅赠品不能生成发货单';
                            return false;
                        }
                    }
                }

                if ($split_auto['split_id'] && kernel::single('ome_order_split')->get_delivery_seting()) {
                    $confirmRoles = array(
                        'inlet_class' => 'split',
                        'corp'        => $corp,
                        'branch_id'   => $branchId,
                        'split_id'    => $split_auto['split_id'],
                        'source'      => $split_auto['source'],
                    );
                    $rs = $this->_splitDeliveryOne($group, $confirmRoles, $orders, $consignee);
                } else {
                    if($branchId == 'auto') {
                        $confirmRoles = array(
                            'inlet_class' => 'ordertaking',
                            'corp'        => $corp,
                            'branch_id'   => $branchId,
                        );
                        $rs = $this->_splitDeliveryOne($group, $confirmRoles, $orders, $consignee);
                    } else {
                        $group->setBranchId($branchId);
                        $group->setDlyCorp($corp);
                        
                        // 指定的发货单号
                        $group->setAssignDeliveryBn($extendInfo);
                        
                        // 设置发货单扩展信息
                        $group->setDeliveryExtendInfo($extendInfo);
                        
                        // 生成发货单位
                        $rs = $group->mkDelivery($consignee);
                    }
                }
                
                if ($rs['rsp'] == 'fail') {
                    $errmsg = $rs['msg'] ? $rs['msg'] : '审单操作失败：'.$errmsg;
                    return false;
                }
                return true;
            }
        } else {
            $errmsg = '该订单不满足条件';
            return false;
        }
    }

    protected function _splitDeliveryOne($sourceGroup, $sourceConfirmRoles, $arrOrder, $consignee)
    {
        $group = $sourceGroup;
        $confirmRoles = $sourceConfirmRoles;
        $group->updateOrderInfo($arrOrder);
        if($confirmRoles['branch_id'] == 'auto') {
            kernel::single('omeauto_auto_plugin_branch')->process($group,$confirmRoles);
            $groupStatus = $group->getStatus();
            if ($groupStatus['opt'] == omeauto_auto_group_item::__OPT_HOLD) {
                $msg = '智选仓库失败';
                return array('rsp'=>'fail', 'msg'=>$msg);
            }
            $pluginSplit = kernel::single('omeauto_auto_plugin_split');
            $pluginSplit->process($group, $confirmRoles);
            $groupStatus = $group->getStatus();
            if ($groupStatus['opt'] == omeauto_auto_group_item::__OPT_HOLD) {
                $msg = '拆分商品失败';
                return array('rsp' => 'fail', 'msg' => $msg);
            }
            kernel::single('omeauto_auto_plugin_store')->process($group,$confirmRoles);
            $groupStatus = $group->getStatus();
            if ($groupStatus['opt'] == omeauto_auto_group_item::__OPT_HOLD) {
                $msg = '验证仓库库存失败';
                return array('rsp'=>'fail', 'msg'=>$msg);
            }
            kernel::single('omeauto_auto_plugin_logi')->process($group,$confirmRoles);
            $groupStatus = $group->getStatus();
            if ($groupStatus['opt'] == omeauto_auto_group_item::__OPT_HOLD) {
                return array('rsp'=>'fail', 'msg'=>'智选物流失败');
            }
            kernel::single('omeauto_auto_plugin_arrived')->process($group,$confirmRoles);
            $groupStatus = $group->getStatus();
            if ($groupStatus['opt'] == omeauto_auto_group_item::__OPT_HOLD) {
                return array('rsp'=>'fail', 'msg'=>'物流公司不到');
            }
        } else {
            $group->setBranchId($confirmRoles['branch_id']);
            $group->setDlyCorp($confirmRoles['corp']);
            $pluginSplit = kernel::single('omeauto_auto_plugin_split');
            $pluginSplit->process($group, $confirmRoles);
            $groupStatus = $group->getStatus();
            if ($groupStatus['opt'] == omeauto_auto_group_item::__OPT_HOLD) {
                $msg = '拆分商品失败';
                return array('rsp' => 'fail', 'msg' => $msg);
            }
        }
        $msg = '拆单成功';
        $rs  = $group->mkDelivery($consignee);
        $deliveryOrder = $group->getOrders();
        foreach ($arrOrder as $k => $order) {
            foreach ($order['objects'] as $ok => $object) {
                foreach ($object['items'] as $ik => $ival) {
                    $deliveryOrderItems = $deliveryOrder[$k]['objects'][$ok]['items'][$ik];
                    if ($deliveryOrderItems) {
                        $ival['split_num'] += $deliveryOrderItems['nums'];
                        if ($ival['nums'] > $ival['split_num']) {
                            $arrOrder[$k]['objects'][$ok]['items'][$ik]['split_num'] = $ival['split_num'];
                        } else {
                            unset($arrOrder[$k]['objects'][$ok]['items'][$ik]);
                        }
                    }
                }
                if (empty($arrOrder[$k]['objects'][$ok]['items'])) {
                    unset($arrOrder[$k]['objects'][$ok]);
                }
            }
            if (empty($arrOrder[$k]['objects'])) {
                unset($arrOrder[$k]);
            }
        }
        if (empty($arrOrder)) {
            return array('rsp' => 'succ');
        }
        return $this->_splitDeliveryOne($sourceGroup, $sourceConfirmRoles, $arrOrder, $consignee);
    }

    public function getStatus($order)
    {
        $plugin = array('pay' => array(), 'flag' => array(), 'logi' => array(), 'member' => array(), 'ordermulti' => array(), 'branch' => array(), 'store' => array(), 'oversold' => array(), 'tbgift' => array(), 'shopcombine' => array(), 'crm' => array(), 'tax' => array(), 'arrived' => array());

        $statusList = array();

        foreach ($plugin as $p => $h) {
            $pInstance = $this->_instancePlugin($p);

            //$status = $order['auto_status'] & $pInstance->getMsgFlag();
            $status = $pInstance->getStatus($order['auto_status'], $order);

            if ($status > 0) {
                $msg        = $pInstance->getAlertMsg($order);
                $msg['msg'] = str_replace(array('<b>', '</b>', '<br />'), '', $msg['msg']);

                $statusList[] = $msg;
            }
        }

        return $statusList;
    }

    /**
     * 获该用户除指定用户外的所有订单数
     *
     * @param Integer $memberId 会员编号
     * @param Integer $shopId 店铺ID
     * @return Integer
     */
    public function getCombineShopMemberCount($orders)
    {
        $combine_member_id = true;
        $combine_shop_id   = true;
        $this->_getCombineConf($combine_member_id, $combine_shop_id);
        $filter = array('status' => 'active', 'process_status' => array('unconfirmed', 'confirmed'), 'ship_status' => '0', 'f_ship_status' => '0', 'order_bn|noequal' => '0', 'is_cod' => $orders['is_cod']);

        if ($orders['shop_type'] == 'shopex_b2b') {
            //分销单,对支持跨店合的参数无视,直接内置规则处理
            if ($combine_member_id) {
                //需判断同一用户，因分销没有实际客户信息，以无用户信息方式处理
                if (empty($orders['member_id'])) {
                    $filter['order_id'] = $orders['order_id'];
                } else {
                    $filter['member_id'] = $orders['member_id'];
                    $filter['shop_id']   = $orders['shop_id'];
                    $filter              = array_merge($filter, $this->_getNullMemberFilter($orders));
                }
            } else {
                //检查是否导入订单
                if (empty($orders['member_id'])) {
                    //如是导入的无用户订单，则无法判定前端销售的实际店铺，只取出当前订单
                    $filter['order_id'] = $orders['order_id'];
                } else {
                    //有用户名,可确认前端销售的实际店铺
                    $filter['member_id'] = $orders['member_id'];
                    $filter['shop_id']   = $orders['shop_id'];
                    //判定地址一致
                    $filter = array_merge($filter, $this->_getAddrFilter($orders));
                }
            }
        } else if ($orders['shop_type'] == 'dangdang' && $orders['is_cod'] == 'true') {
            $filter['order_id'] = $orders['order_id'];
        } else if ($orders['shop_type'] == 'amazon' && $orders['self_delivery'] == 'false') {
            $filter['order_id'] = $orders['order_id'];
        } else if ($orders['shop_type'] == 'aikucun') {
            $filter['order_id'] = $orders['order_id'];
        } else if ($orders['shop_type'] == 'taobao' && $orders['order_source'] == 'tbdx') {
            //823修改淘分销走b2b流程
            if ($combine_member_id) {
                //需判断同一用户，因分销没有实际客户信息，以无用户信息方式处理
                if (empty($orders['member_id'])) {
                    $filter['order_id'] = $orders['order_id'];
                } else {
                    $filter['member_id'] = $orders['member_id'];
                    $filter['shop_id']   = $orders['shop_id'];
                    $filter              = array_merge($filter, $this->_getNullMemberFilter($orders));
                }
            } else {
                //检查是否导入订单
                if (empty($orders['member_id'])) {
                    //如是导入的无用户订单，则无法判定前端销售的实际店铺，只取出当前订单
                    $filter['order_id'] = $orders['order_id'];
                } else {
                    //有用户名,可确认前端销售的实际店铺
                    $filter['member_id'] = $orders['member_id'];
                    $filter['shop_id']   = $orders['shop_id'];
                    //判定地址一致
                    $filter = array_merge($filter, $this->_getAddrFilter($orders));
                }
            }
        } else {
            //直销单
            if ($combine_member_id) {
                if (empty($orders['member_id'])) {
                    //以无用户信息方式处理
                    $filter = array_merge($filter, $this->_getNullMemberFilter($orders));
                } else {
                    //有用户名
                    $filter['member_id'] = $orders['member_id'];
                    $filter              = array_merge($filter, $this->_getAddrFilter($orders));
                }
            } else {
                //判定地址
                $filter = array_merge($filter, $this->_getAddrFilter($orders));
            }
            if ($combine_shop_id) {
                $filter['shop_id'] = $orders['shop_id'];
            }
            $filter['filter_sql'] = "(shop_type IS NOT NULL AND order_source<>'tbdx' and shop_type<>'shopex_b2b' and (is_cod='false' or (shop_type<>'dangdang' AND is_cod='true')) and (self_delivery='true' or (shop_type<>'amazon' and self_delivery='false')) OR shop_type IS NULL)";
        }
        
        if (!isset($orders['shipping_name']) && !isset($orders['store_code']) && !isset($orders['cpup_service'])) {
            $row = app::get(self::__ORDER_APP)->model('orders')->count($filter);
        }else{
            $filter['shipping'] = $orders['shipping_name'];
            $orderIds = app::get(self::__ORDER_APP)->model('orders')->getList('order_id',$filter);
            $orderIds = array_column($orderIds,'order_id');
            $objOrderIds = app::get(self::__ORDER_APP)->model('order_objects')->getList('order_id',array('order_id'=>$orderIds,'store_code'=>$orders['store_code']));
            $objOrderIds = array_column($objOrderIds,'order_id');
            $row = app::get(self::__ORDER_APP)->model('order_extend')->count(array('order_id'=>$objOrderIds,'cpup_service'=>$orders['cpup_service']));
        }

        return $row;
    }


    /**
     * 合并订单条数限制
     * @todo：如果合单条数大于配置的限制合单条数,会被拆分成多个分组
     *
     * @param array $orderGroup
     * @return array
     */
    public function _restrictCombineLimit($orderGroup)
    {
        //[获取配置]合并订单条数限制
        $combine_select = app::get('ome')->getConf('ome.combine.select');
        $combine_merge_limit = app::get('ome')->getConf('ome.combine.merge.limit');
        $combine_merge_limit = intval($combine_merge_limit);
        if($combine_select !== '0'){
            $combine_merge_limit = 0; //未开启自动合并订单
        }
        
        //没有限制合单条数,直接返回
        if($combine_merge_limit <= 0){
            return $orderGroup;
        }
        
        //group
        foreach ($orderGroup as $key => $group)
        {
            if(count($group['orders']) <= $combine_merge_limit) {
                continue;
            }
            
            //注销原分组数据
            unset($orderGroup[$key]);
            
            list($combine_hash, $combine_idx) = explode('||', $key);
            
            //重新组织分组数据
            $groupNum = 0;
            $groupPage = 1;
            foreach ($group['orders'] as $order_key => $order_id)
            {
                //分页
                if($groupNum >= $combine_merge_limit){
                    //todo:当可合并订单 大于 配置的合并条数限制,需要多次合并时:本次只会合并一次
                    //break;
                    
                    $groupNum = 0;
                    
                    $groupPage++;
                }
                
                $groupNum++;
                
                $newGroupKey = sprintf('%s||%s', $combine_hash.'-'.$groupPage, $combine_idx . $groupPage);
                
                $orderGroup[$newGroupKey]['orders'][$order_key] = $order_id;
                $orderGroup[$newGroupKey]['cnt'] += 1;
            }
        }
        
        return $orderGroup;
    }
}
