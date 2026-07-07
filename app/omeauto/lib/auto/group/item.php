<?php

/**
 * 订单组结构
 *
 * @author hzjsq@msn.com
 * @version 0.1b
 */
class omeauto_auto_group_item
{
    /**
     * 检查通过
     */
    const __OPT_ALLOW = 0;
    /**
     * 需提示或其它
     */
    const __OPT_ALERT = 1;
    /**
     * 无法合并
     */
    const __OPT_HOLD = 2;

    /**
     * 发货单分组
     */
    static $_orderGroups = null;
    /**
     * 短信分组
     */
    static $_smsGroups = null;
    /**
     * 原始订单数据
     * @var Array
     */
    private $original_orders = array();
    /**
     * 订单数据
     * @var Array
     */
    private $orders = array();
    /**
     * 要修正的订单状态
     * @var Array
     */
    private $orderStatus = array();
    /**
     * 检查结果
     * @var Array
     */
    private $status = array('opt' => 0, 'log' => array());

    private $orderWeight;
    
    /**
     * 发货单扩展信息
     *
     * @var array
     */
    private $_deliveryExtendInfo = array();
    
    /**
     * 符合自动仓库规则
     *
     * @var array
     */
    private $_autobranch = array();

    #指定仓无需判库存
    private $confirmBranch = false;

    #仓库指定物流
    private $branchIdCorpId = array();

    #合单队列订单
    private $combineOrderId = array();

    #拆单队列订单
    private $splitOrderId = array();

    #是否跑拆单
    private $isProcessSplit = false;

    #仓库分组情况
    private $branchGroup = array();
    
    //自动发货标识
    private $_isAutoDelivery = false;

    //拆分不生成发货单
    private $splitNotDly = false;

    //门店现货订单
    private $isO2oPick = false;
    
    //分销一件代发
    private $_is_shopyjdf = false;
    
    #菜鸟的智选物流会返回物流单号
    public function setWaybillCode($waybillCode)
    {
        $this->status['change']['waybillCode'] = $waybillCode;
    }

    public function getWaybillCode()
    {
        return $this->status['change']['waybillCode'];
    }

    /**
     * 析构
     *
     * @param Array $orders 订单组数据
     * @return void
     */
    public function __construct($orders)
    {

        $this->original_orders = $orders;
        $this->orders          = $orders;
        $this->orderNums       = count($orders);
    }

    /**
     * 获取原始订单内容
     *
     * @param void
     * @return Array
     */
    public function getOriginalOrders()
    {

        return $this->original_orders;
    }

    /**
     * 获取原始订单内容
     *
     * @param void
     * @return Array
     */
    public function setOriginalOrders($orders)
    {

        $this->original_orders=$orders;
    }
    /**
     * 获取订单内容
     *
     * @param void
     * @return Array
     */
    public function &getOrders()
    {

        return $this->orders;
    }

    /**
     * 获取送货地址
     *
     * @param void
     * @return String
     */
    public function getShipArea()
    {

        foreach ($this->orders as $key => $order) {

            return $order['ship_area'];
            break;
        }
    }

    //获取shop_id
    public function getShopId()
    {
        foreach ($this->orders as $key => $order) {
            return $order['shop_id'];
            break;
        }
    }

    /**
     * 识别是否全渠道订单
     *
     * @param void
     * @return String
     */
    public function isOmnichannel()
    {

        foreach ($this->orders as $key => $order) {
            if ($order['omnichannel'] == 1) {
                return true;
            } else {
                return false;
            }
            break;
        }
    }

    /**
     * 获取订单包含的门店信息
     *
     * @param void
     * @return String
     */
    public function getStoreInfo()
    {
        $orderExtendObj = app::get('ome')->model("order_extend");
        foreach ($this->orders as $key => $order) {
            $storeInfo = $orderExtendObj->dump(array('order_id' => $order['order_id']), 'store_dly_type,store_bn');
            return $storeInfo;
            break;
        }
    }

    /**
     * 设置是否是门店仓
     *
     * @param void
     * @return String
     */
    public function setStoreBranch()
    {
        $this->isStoreBranch = true;
    }

    /**
     * 是否是门店仓
     *
     * @param void
     * @return String
     */
    public function isStoreBranch()
    {
        return $this->isStoreBranch;
    }

    /**
     * 设置门店现货
     *
     * @param $isO2oPick
     *
     * @return void
     */
    public function setO2oPick($isO2oPick)
    {
        $this->isO2oPick = $isO2oPick;
    }
    
    /**
     * 获取门店现货标识
     * @return bool
     */
    public function getO2oPick()
    {
        return $this->isO2oPick;
    }

    /**
     * 设置门店的履约方式
     *
     * @param $type 类型 o2o_pickup 门店自提 o2o_ship 门店配送
     * @return String
     */
    public function setStoreDlyType($type)
    {
        $this->storeDlyType = $type;
    }

    /**
     * 获取门店的履约方式
     *
     * @param void
     * @return String
     */
    public function getStoreDlyType()
    {
        return $this->storeDlyType;
    }

    /**
     * 获取订单条数
     *
     * @param void
     * @return Integer
     */
    public function getOrderNum()
    {

        if (empty($this->orders)) {

            return 0;
        } else {

            return count($this->orders);
        }
    }

    /**
     * 获取指定字段的值的健的分布
     *
     * @param String $field 字段名称
     * @return array
     */
    public function getGroupByField($field)
    {

        $result = array();
        foreach ($this->orders as $order) {

            $result[$order[$field]][] = $order['order_id'];
        }

        return $result;
    }

    public function updateOrderInfo($arrOrder)
    {
        $this->orders = $arrOrder;
    }

    public function setConfirmBranch($bool)
    {
        $this->confirmBranch = $bool;
    }

    public function getConfirmBranch()
    {
        return $this->confirmBranch;
    }

    public function setBranchIdCorpId($branchIdCorpId)
    {
        $this->branchIdCorpId = $branchIdCorpId;
    }

    public function getBranchIdCorpId()
    {
        return $this->branchIdCorpId;
    }

    public function setCombineOrderId($arrOrderId)
    {
        $this->combineOrderId = $arrOrderId;
    }

    public function setSplitOrderId($arrOrderId)
    {
        $this->splitOrderId = $arrOrderId;
    }

    public function setProcessSplit($split = 'split')
    {
        $this->isProcessSplit = $split;
    }

    public function getProcessSplit()
    {
        return $this->isProcessSplit;
    }

    public function setBranchGroup($branchGroup)
    {
        $this->branchGroup = $branchGroup;
    }

    public function getBranchGroup()
    {
        return $this->branchGroup;
    }

    /**
     * 设置物流公司
     *
     * @param Array $corp 物流公司信息
     * @return void
     */
    public function setDlyCorp($corp)
    {

        $this->status['change']['dlyCorp'] = $corp;
    }
    /**
     * 得到物流公司
     *
     * @param Array $corp 物流公司信息
     * @return void
     */
    public function getDlyCorp()
    {

        return $this->status['change']['dlyCorp'];
    }

    public function setSubWaybillCode($subWaybillCodeArr)
    {
        $this->status['change']['subWaybillCode'] = $subWaybillCodeArr;
    }

    public function getSubWaybillCode()
    {
        return $this->status['change']['subWaybillCode'];
    }

    /**
     * 设置仓库规则
     *
     * @return void
     * @author
     */
    public function setAutoBranch($autobranch)
    {
        $this->_autobranch = $autobranch;
    }

    /**
     * 得到仓库规则
     *
     * @return void
     * @author
     */
    public function getAutoBranch()
    {
        return $this->_autobranch;
    }

    /**
     * 设置仓库
     *
     * @param Integer $branchId
     * @return void
     */
    public function setBranchId($branchId)
    {

        $this->status['change']['branchId'] = $branchId;
    }

    /**
     * 获取已经设定的仓库编号
     *
     * @param void
     * @return Integer
     */
    public function getBranchId()
    {

        return $this->status['change']['branchId'];
    }

    public function setDeliveryStatus($deliveryStatus)
    {
        $this->status['change']['deliveryStatus'] = $deliveryStatus;
    }

    public function getDeliveryStatus()
    {
        return $this->status['change']['deliveryStatus'];
    }

    /**
     * 设置指定订单的提示状态
     *
     * @param Integer $oId 订单ID
     * @param Integer $status 要设置的订单提示状态
     * @return void
     */
    public function setOrderStatus($oId, $status)
    {

        if ($oId == '*') {

            foreach ($this->orders as $oid => $order) {

                $this->setOrderStatus($oid, $status);
            }
        } else {
            if ($this->orders[$oId]['pay_status'] == 0) {

                return;
            }
            if($status == omeauto_auto_const::__STORE_CODE) {
                $branch_id = 0;
                if(is_array($this->getBranchId())) {
                    $branch_id = current($this->getBranchId());
                }
                kernel::single('ome_order_store')->lackNotify($this->orders[$oId], $branch_id);
            }
            if (isset($this->orderStatus[$oId])) {

                $this->orderStatus[$oId] = $this->orderStatus[$oId] | $status;
            } else {

                $this->orderStatus[$oId] = $status;
            }
        }
    }

    /**
     * 设置指定插件的检查结果
     *
     * @param Integer $optStatus 检查结果
     * @param String $plugFix 插件名
     * @return void
     */
    public function setStatus($optStatus, $plugFix, $msg='')
    {

        //$optStatus = intval($optStatus);
        $this->status['opt']   = $this->status['opt'] > $optStatus ? $this->status['opt'] : $optStatus;
        $this->status['log'][] = array('plug' => $plugFix, 'result' => $optStatus, 'msg'=>$msg);
    }

    //设置拆分不生成发货单
    public function setSplitNotDly($bool) {
        $this->splitNotDly = $bool;
    }

    /**
     * 验证状态
     *
     * @return void
     * @author
     **/
    public function validStatus()
    {
        return $this->status['opt'] != self::__OPT_ALLOW ? false : true;
    }

    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * 设置指定的发货单号
     *
     * @param Integer $branchId
     * @return void
     */
    public function setAssignDeliveryBn($extendInfo)
    {
        if(isset($extendInfo['delivery_bn']) && $extendInfo['delivery_bn']){
            $this->status['extendInfo']['delivery_bn'] = $extendInfo['delivery_bn'];
        }else{
            $this->status['extendInfo']['delivery_bn'] = '';
        }
    }
    
    /**
     * 获取指定的发货单号
     *
     * @return String
     */
    public function getAssignDeliveryBn()
    {
        return $this->status['extendInfo']['delivery_bn'];
    }
    
    /**
     * 设置发货单扩展信息
     *
     * @param $extendInfo
     * @return void
     */
    public function setDeliveryExtendInfo($extendInfo)
    {
        if($extendInfo){
            $this->_deliveryExtendInfo = $extendInfo;
        }else{
            $this->_deliveryExtendInfo = [];
        }
    }
    
    /**
     * 获取发货单扩展信息
     *
     * @return mixed
     */
    public function getDeliveryExtendInfo()
    {
        return $this->_deliveryExtendInfo;
    }
    
    /**
     * 检查订单组内容是否有效
     *
     * @param Array $orders 订单组
     * @return Boolean
     */
    public function vaild($config)
    {

        $autoDelivery = $config['autoConfirm'];

        if (($this->status['opt'] == self::__OPT_ALLOW || empty($this->status['opt'])) && ($autoDelivery == '1')) {
            if ($this->combineOrderId) {
                foreach ($this->combineOrderId as $orderId) {
                    if ($this->orders[$orderId]) {
                        unset($this->orders[$orderId]);
                    }
                }
            }
            if ($this->splitOrderId) {
                if (!$this->isProcessSplit) {
                    foreach ($this->splitOrderId as $orderId) {
                        if ($this->orders[$orderId]) {
                            unset($this->orders[$orderId]);
                        }
                    }
                }
            }
            if (empty($this->orders)) {
                return false;
            }
            return true;
        } else {
            $this->setSplitOrderId(array());
            $this->setCombineOrderId(array());
            return false;
        }
    }

    /**
     * 检查是否有效的缓存订单组内容
     *
     * @param Array $orders 订单组s
     * @return Boolean
     */
    public function vaildBufferGroup($bufferTime)
    {

        $payStatus = $this->getGroupByField('pay_status');
        $codStatus = $this->getGroupByField('is_cod');

        

        if(kernel::single('ome_order_func')->checkPresaleOrder()){

            //多是未支付，且不为货到付款,返回 false
            if (count($payStatus) == 1 && (isset($payStatus[0]) || isset($payStatus[2]) ) && !isset($codStatus['true'])) {

                return false;
            }

            if(count($payStatus) == 1 && isset($payStatus[3]) && !isset($codStatus['true'])){
                foreach ($payStatus as $pay => $ordes) {
               
                    foreach ($ordes as $orderId) {
                       
                        if ($this->orders[$orderId]['step_trade_status'] !='FRONT_PAID_FINAL_NOPAID') {
                            return false;
                        }else{
                            return true;
                        }
                    }
                
                }
                
            }
        }else{
            //多是未支付，且不为货到付款,返回 false
            if (count($payStatus) == 1 && (isset($payStatus[0]) || isset($payStatus[2]) || isset($payStatus[3])) && !isset($codStatus['true'])) {

                return false;
            }
        }
        

        
        //检查时间，正确应为 cod 为 createtime, 非 cod 为 支付时间，目前没有支付时间,暂用 createtime 判断
        if (!isset($codStatus['true'])) {
            //款到发货，需有一个已付款订单的支付时间已大于缓冲时间
            foreach ($payStatus as $pay => $ordes) {
                if ($pay == 1 || $pay == 4 || $pay == 5) {
                    foreach ($ordes as $orderId) {
                        if ($this->orders[$orderId]['paytime'] < $bufferTime) {
                            return true;
                        }
                    }
                }
            }
        } else {
            //货到付款，需有一个订单创建时间已经过缓冲时间
            foreach ($this->orders as $order) {
                if ($order['createtime'] < $bufferTime) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 对当前结果进行处理
     *
     * @param void
     * @return boolean
     */
    public function process($config)
    {
        if ($this->vaild($config)) {
            $bgData = $this->getBranchGroup();
            if($this->splitNotDly) {
                $nodeStart = microtime(true);
                foreach ($this->orders as $order) {
                    $return = $this->_processSplitNotDly($config, $order);
                }
                $nodeTime = microtime(true) - $nodeStart;
                $orderIds = array_column($this->orders, 'order_id');
                $orderBns = array_column($this->orders, 'order_bn');
                ome_api_log::add_message('node', 'split_not_dly', $nodeTime, array(
                    'order_ids' => $orderIds,
                    'order_bns' => $orderBns
                ));
            } elseif ($bgData) {
                $nodeStart = microtime(true);
                $return = $this->_processBranchGroupConfirm($config);
                $nodeTime = microtime(true) - $nodeStart;
                $orderIds = array_column($this->orders, 'order_id');
                $orderBns = array_column($this->orders, 'order_bn');
                ome_api_log::add_message('node', 'branch_group_confirm', $nodeTime, array(
                    'order_ids' => $orderIds,
                    'order_bns' => $orderBns
                ));
            } else {
                $nodeStart = microtime(true);
                $return = $this->_processConfirm($config);
                $nodeTime = microtime(true) - $nodeStart;
                $orderIds = array_column($this->orders, 'order_id');
                $orderBns = array_column($this->orders, 'order_bn');
                ome_api_log::add_message('node', 'process_confirm', $nodeTime, array(
                    'order_ids' => $orderIds,
                    'order_bns' => $orderBns
                ));
            }
            
            //虚拟商品拆单后自动完成发货
            $auto_dly = $this->isAutoDelivery();
            if($return && $auto_dly){
                $nodeStart = microtime(true);
                $queueObj = app::get('base')->model('queue');
                
                foreach ($this->orders as $order)
                {
                    $order_id = $order['order_id'];
                    $order_bn = $order['order_bn'];
                    
                    //[队列]生成发货单,自动完成发货
                    $queueData = array(
                            'queue_title' => '订单号['. $order_bn .']虚拟货品自动完成发货',
                            'start_time' => time(),
                            'params' => array(
                                    'sdfdata' => array('order_id'=>$order_id, 'order_bn'=>$order_bn),
                                    'app' => 'ome', //随便写,不用此参数
                                    'mdl' => 'orders' //随便写,不用此参数
                            ),
                            'worker'=>'ome_order.auto_delivery',
                    );
                    $queueObj->save($queueData);
                }
                $nodeTime = microtime(true) - $nodeStart;
                $orderIds = array_column($this->orders, 'order_id');
                $orderBns = array_column($this->orders, 'order_bn');
                ome_api_log::add_message('node', 'auto_delivery_queue', $nodeTime, array(
                    'order_ids' => $orderIds,
                    'order_bns' => $orderBns
                ));
            }
            
        } else {
            $nodeStart = microtime(true);
            $return = $this->_processDispatch($config);
            $nodeTime = microtime(true) - $nodeStart;
            $orderIds = array_column($this->orders, 'order_id');
            $orderBns = array_column($this->orders, 'order_bn');
            ome_api_log::add_message('node', 'process_dispatch', $nodeTime, array(
                'order_ids' => $orderIds,
                'order_bns' => $orderBns
            ));
        }
        
        // 合单处理
        if ($this->combineOrderId) {
            $nodeStart = microtime(true);
            kernel::single('ome_batch_log')->combineAgain($this->combineOrderId);
            $nodeTime = microtime(true) - $nodeStart;
            ome_api_log::add_message('node', 'combine_again', $nodeTime, array(
                'order_ids' => $this->combineOrderId
            ));
        }
        
        // 拆单处理
        if ($this->splitOrderId) {
            $nodeStart = microtime(true);
            kernel::single('ome_batch_log')->split($this->splitOrderId);
            $nodeTime = microtime(true) - $nodeStart;
            ome_api_log::add_message('node', 'split_log', $nodeTime, array(
                'order_ids' => $this->splitOrderId
            ));
        }
        
        return $return;
    }


    private function _processSplitNotDly($config, $order) {
        $split_status = '';
        list($rs, $msg) = kernel::single('ome_order_platform_split')->dealOrderObjects($order, $split_status);
        if(!$rs) {
            $logMsg = sprintf('订单拆分失败:%s,审单规则:%s(%s),仓库规则:%s(%s)', $msg, $config['confirmName'], $config['confirmId'], (string) $this->_autobranch['name'], $this->_autobranch['tid'] ? $this->_autobranch['tid'] : '-');
            app::get('ome')->model('operation_log')->write_log('order_confirm@ome', $order['order_id'], $logMsg);
            return false;
        }
        $sdf = array(
            'order_id'       => $order['order_id'],
            'confirm'        => 'Y',
            'dispatch_time'  => time(),
        );
        $dispacthUser = omeauto_auto_dispatch::getAutoDispatchUser($this);
        if ($dispacthUser['group_id']) {
            $sdf['group_id'] = $dispacthUser['group_id'];
            $sdf['op_id']    = $dispacthUser['op_id'];
        }
        if ($split_status == 'splited') {
            $cTxt = '确认';
        } else {
            $cTxt = '部分确认';
        }
        $orderObj = app::get('ome')->model('orders');
        $orderObj->save($sdf);
        $curOpInfo = kernel::single('ome_func')->getDesktopUser();
        $logMsg = sprintf('订单%s,操作员:%s,审单规则:%s(%s),仓库规则:%s(%s)', $cTxt, $curOpInfo['op_name'], $config['confirmName'], $config['confirmId'], (string) $this->_autobranch['name'], $this->_autobranch['tid'] ? $this->_autobranch['tid'] : '-');
        app::get('ome')->model('operation_log')->write_log('order_confirm@ome', $order['order_id'], $logMsg);
        return true;
    }

    private function _processBranchGroupConfirm($config)
    {
        $bgData = $this->getBranchGroup();
        $bgData = current($bgData);
        $orders = $this->orders;
        foreach ($bgData['branch_product'] as $bId => $bmNum) {
            $this->setBranchId($bId);
            if ($bgData['branch_corp'][$bId]) {
                $corp = array('corp_id' => $bgData['branch_corp'][$bId]);
                $this->setDlyCorp($corp);
            }
            $tmpOrders = array();
            foreach ($orders as $orderId => $order) {
                foreach ($order['objects'] as $objId => $object) {
                    foreach ($object['items'] as $itemId => $item) {
                        $bpNum = $bmNum[$item['product_id']];
                        if ($bpNum < 1) {
                            continue;
                        }
                        if ($item['nums'] < 1 || $item['delete'] != 'false') {
                            unset($orders[$orderId]['objects'][$objId]['items'][$itemId]);
                            continue;
                        }
                        if (!$tmpOrders[$orderId]) {
                            $tmpOrders[$orderId] = $order;
                            unset($tmpOrders[$orderId]['objects']);
                        }
                        if (!$tmpOrders[$orderId]['objects'][$objId]) {
                            $tmpOrders[$orderId]['objects'][$objId] = $object;
                            unset($tmpOrders[$orderId]['objects'][$objId]['items']);
                        }
                        if ($bpNum < $item['nums']) {
                            $orders[$orderId]['objects'][$objId]['items'][$itemId]['nums'] -= $bpNum;
                            $num                        = $bpNum;
                            $bmNum[$item['product_id']] = 0;
                        } else {
                            $bmNum[$item['product_id']] -= $item['nums'];
                            $num = $item['nums'];
                            unset($orders[$orderId]['objects'][$objId]['items'][$itemId]);
                        }
                        $item['nums']                                             = $num;
                        $tmpOrders[$orderId]['objects'][$objId]['items'][$itemId] = $item;
                    }
                    if (empty($orders[$orderId]['objects'][$objId]['items'])) {
                        unset($orders[$orderId]['objects'][$objId]);
                    }
                }
                if (empty($orders[$orderId]['objects'])) {
                    unset($orders[$orderId]);
                }
            }
            if (empty($tmpOrders)) {
                continue;
            }
            $this->updateOrderInfo($tmpOrders);
            $this->_processConfirm($config);
        }
        return true;
    }

    private function _processConfirm($config)
    {
        $curOpInfo = kernel::single('ome_func')->getDesktopUser();

        $systemUser   = omeauto_auto_dispatch::getSystemUser();
        $deliveryInfos = $this->fetchDeliveryFormat();

        $ids            = array();
        $deliveryObj    = app::get('ome')->model("delivery");
        $orderObj       = app::get('ome')->model("orders");
        $oOperation_log = app::get('ome')->model('operation_log');

        $deliveryid_nums = count($deliveryInfos);
        #本次发货单数量，如果大于1，就是属于合单的，合单的发货单，不能传物流单号，因为物流单号表字段唯一，会导致生成单据只能保存进一个子发货单
        if ($deliveryid_nums > 1) {
            $waybillCode    = null;
            $subWaybillCode = null;
        } else {
            $waybillCode    = $this->getWaybillCode();
            $subWaybillCode = $this->getSubWaybillCode();
        }

        $orderBranch = [];
        $shop_types = [];
        $orderIds = [];
        foreach ($deliveryInfos as $orderId => $deliveryInfo) {
            if ($orderId && $orderId > 0) {
                $order_items                              = $deliveryInfo['order_items'];
                $deliveryInfo['delivery_waybillCode']     = $waybillCode; #菜鸟的智选物流会返回物流单号
                $deliveryInfo['delivery_sub_waybillCode'] = $subWaybillCode;
                unset($deliveryInfo['order_items']);
                $split_status = '';
                $addDeliveryStart = microtime(true);
                $result       = $deliveryObj->addDelivery($orderId, $deliveryInfo, array(), $order_items, $split_status);
                $addDeliveryTime = microtime(true) - $addDeliveryStart;
                
                // 记录addDelivery性能数据
                ome_api_log::add_message('node', 'addDelivery', $addDeliveryTime, array(
                    'order_id' => $orderId,
                    'delivery_id' => $result['data'] ?? null
                ));
                if ($result['rsp'] == 'succ') {

                    $orderBranch[$orderId] = $deliveryInfo['branch_id'];

                    $deliveryid = $result['data'];
                    $ids[]      = $deliveryid;
                    $shop_types[] = $deliveryInfo['shop_type'];
                    $orderIds[]   = $orderId;
                    //更新订单信息
                    $sdf = array(
                        'order_id'       => $orderId,
                        'process_status' => $split_status ? $split_status : 'splited',
                        'confirm'        => 'Y',
                        'dispatch_time'  => time(),
                        'op_id'          => $systemUser['op_id'],
                        'logi_no'        => $this->getWaybillCode() ? $this->getWaybillCode() : '', #菜鸟智选物流，会返回物流单号，保存到订单上
                        'group_id'       => $systemUser['group_id'],
                        'splited_num_upset_sql' => 'IF(`splited_num` IS NULL, 1, `splited_num` + 1)',
                        'auto_status'    => '0',
                    );
                    if ($sdf['process_status'] == 'splited') {
                        $dispacthUser = omeauto_auto_dispatch::getAutoDispatchUser($this);
                        if ($dispacthUser['group_id']) {
                            $sdf['group_id'] = $dispacthUser['group_id'];
                            $sdf['op_id']    = $dispacthUser['op_id'];
                        }

                        $cTxt = '确认';
                    } else {
                        $cTxt = '部分确认';
                    }
                    $orderObj->save($sdf);
                    $opInfo = kernel::single('ome_func')->get_system();

                    if ($this->isStoreBranch) {
                        $logMsg = "订单" . $cTxt . ",操作员:" . $curOpInfo['op_name'];
                    } else {
                        $logMsg = sprintf('订单%s,操作员:%s,审单规则:%s(%s),仓库规则:%s(%s)', $cTxt, $curOpInfo['op_name'], $config['confirmName'], $config['confirmId'], (string) $this->_autobranch['name'], $this->_autobranch['tid'] ? $this->_autobranch['tid'] : '-');
                    }
                    $get_dly_bn    = $deliveryObj->getList('delivery_id, delivery_bn', array('delivery_id' => $deliveryid), 0, 1);
                    $get_dly_bn    = $get_dly_bn[0];
                    $logMsg       .= sprintf('(发货单号：<a href="index.php?app=ome&ctl=admin_receipts_print&act=show_delivery_items&id=%s" target="_blank">%s</a>)', $deliveryid, $get_dly_bn['delivery_bn']);
                    $oOperation_log->write_log('order_confirm@ome', $orderId, $logMsg, null, $opInfo);
                    unset($logMsg);

                    //标记当前门店履约订单已分派
                    if ($this->isStoreBranch) {
                        kernel::single('ome_o2o_performance_orders')->updateProcessStatus($orderId, 'confirm');
                    }
                    // 全链路审单/通知配货回流
                    $messageStartTime = microtime(true);
                    kernel::single('ome_event_trigger_shop_order')->order_message_produce(array($orderId),['check','to_wms']);
                    $messageTime = microtime(true) - $messageStartTime;
                    
                    // 记录全链路消息生产性能数据
                    ome_api_log::add_message('message', 'order_message_produce', $messageTime, array(
                        'order_id' => $orderId,
                        'message_types' => 'check,to_wms'
                    ));
                } elseif ($result['rsp'] == 'fail') {
                    if (!in_array($result['msg'], array('明细已经生成发货单'))) {
                        if ($this->isProcessSplit) {
                            if (!in_array($orderId, $this->splitOrderId)) {
                                $this->splitOrderId[] = $orderId;
                            }
                        } else {
                            if (!in_array($orderId, $this->combineOrderId)) {
                                $this->combineOrderId[] = $orderId;
                            }
                        }
                    }
                    $oOperation_log->write_log('order_confirm@ome',$orderId,'路由失败：' . $result['msg']);
                    app::get('ome')->model('order_extend')->addRouterNum($orderId);
                }
            }
        }
        
        //如果物流公司是当当物流不可以合并发货单
        $dly_corp       = app::get(omeauto_auto_combine::__ORDER_APP)->model('dly_corp')->dump(array('corp_id' => $this->status['change']['dlyCorp']['corp_id']), 'type');
        $combine_select = app::get('ome')->getConf('ome.combine.select');
        $_isCombine     = true;
        // if (in_array('vop',$shop_types) && $_SERVER['SERVER_NAME'] !='crocs.erp.taoex.com') {
        //     $_isCombine = false;
        // }
        if ($dly_corp['type'] == 'DANGDANG' || $dly_corp['type'] == 'AMAZON' || $combine_select == '1'
            || !kernel::single('ome_branch')->isCanMerge($this->getBranchId())) {
            $_isCombine = false;
        }
        
        //分销一件代发的店铺,不允许合单
        if($this->_is_shopyjdf === true){
            $_isCombine = false;
        }
        
        $waybillCode = $this->getWaybillCode();

        //合并发货单
        $err = '';
        if (!empty($ids) && count($ids) > 1 && $_isCombine) {
            //多个订单合并审核，合并发货单
            $newdly_id = $deliveryObj->merge($ids, array('logi_no' => $this->getWaybillCode(), 'logi_id' => $this->status['change']['dlyCorp']['corp_id'], 'logi_name' => $this->status['change']['dlyCorp']['name']));
            //标识合单
            kernel::single('ome_bill_label')->markBillLabel($newdly_id, '', 'SOMS_COMBINE_ORDER', 'ome_delivery');
            foreach ($orderIds as $order_id) {
                kernel::single('ome_bill_label')->markBillLabel($order_id, '', 'SOMS_COMBINE_ORDER', 'order', $err, 0);
            }
            // 发货单创建完成后的service扩展点
            $serviceStart = microtime(true);
            foreach(kernel::servicelist('omeauto.service.group.item.confirm.after') as $serviceName => $object) {
                $individualServiceStart = microtime(true);
                if(method_exists($object, 'afterCreateDelivery')) {
                    $object->afterCreateDelivery($newdly_id);
                }
                $serviceTime = microtime(true) - $individualServiceStart;
                
                // 记录每个service的性能数据
                ome_api_log::add_message('service', $serviceName, $serviceTime, array(
                    'delivery_id' => $newdly_id
                ));
            }
            //发货单通知单推送仓库
            $wmsStart = microtime(true);
            
            $wmsTime = microtime(true) - $wmsStart;
            
            // 记录WMS通知性能数据
            ome_api_log::add_message('node', 'wms_notification', $wmsTime, array(
                'delivery_id' => $newdly_id
            ));
            if ($waybillCode) {
                $deliveryObj->db->exec("UPDATE sdb_ome_delivery SET logi_no='" . $waybillCode . "' WHERE delivery_id=" . $newdly_id);
            }
            ome_delivery_notice::create($newdly_id);
        } else {
            //发货单通知单推送仓库
            foreach ($ids as $newdly_id) {
                // 发货单创建完成后的service扩展点
                foreach(kernel::servicelist('omeauto.service.group.item.confirm.after') as $serviceName => $object) {
                    $individualServiceStart = microtime(true);
                    if(method_exists($object, 'afterCreateDelivery')) {
                        $object->afterCreateDelivery($newdly_id);
                    }
                    $serviceTime = microtime(true) - $individualServiceStart;
                    
                    // 记录每个service的性能数据
                    ome_api_log::add_message('service', $serviceName, $serviceTime, array(
                        'delivery_id' => $newdly_id
                    ));
                }
                $wmsStart = microtime(true);
                
                $wmsTime = microtime(true) - $wmsStart;
                
                // 记录WMS通知性能数据
                ome_api_log::add_message('node', 'wms_notification', $wmsTime, array(
                    'delivery_id' => $newdly_id
                ));
                if ($waybillCode) {
                    $deliveryObj->db->exec("UPDATE sdb_ome_delivery SET logi_no='" . $waybillCode . "' WHERE delivery_id=" . $newdly_id);
                }
                ome_delivery_notice::create($newdly_id);
            }
        }
        // todo maxiaochen 得物品牌直发 请求接单接口，如果检测到有取消的发货单，则先调用发货仓修改接口
        $this->_dewu_afterAddDelivery($orderBranch);
        $return = true;
        return $return;
    }

    private function _processDispatch($config)
    {
        $curOpInfo = kernel::single('ome_func')->getDesktopUser();

        //需要重新获取分组规则，所以将拆单插件更改的明细重置回来
        $this->updateOrderInfo($this->original_orders);
        $dispacthUser   = omeauto_auto_dispatch::getAutoDispatchUser($this);
        $orderIds       = array_keys($this->orders);
        $orderObj       = app::get('ome')->model("orders");
        $oOperation_log = app::get('ome')->model('operation_log');

        foreach ($this->orders as $order) {

            // 检测京东订单是否有微信支付先用后付的单据
            $use_before_payed = false;
            if ($order['shop_type'] == '360buy') {
                $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($order['order_id']);
                $labelCode = array_column($labelCode, 'label_code');
                $use_before_payed = kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode);
            }

            if ($order['pay_status'] == '1' || $order['pay_status'] == '4' || $order['pay_status'] == '5' || $order['is_cod'] == 'true' || $use_before_payed) {
                $sdf        = array('order_id' => $order['order_id']);
                $isSplit    = false;
                $modelItems = app::get('ome')->model('order_items');
                $itemFilter = array('order_id' => $order['order_id'], 'split_num|than' => 0, 'disable' => 'false');
                if ($modelItems->db_dump($itemFilter, 'item_id')) {
                    $isSplit = true;
                }
                if (isset($this->orderStatus[$order['order_id']])) {
                    $processStatus         = $isSplit ? 'splitting' : 'unconfirmed';
                    $sdf['op_id']          = (int)$dispacthUser['op_id'];
                    $sdf['group_id']       = (int)$dispacthUser['group_id'];
                    $sdf['confirm']        = $processStatus == 'unconfirmed' ? 'N' : 'Y';
                    $sdf['process_status'] = $processStatus;
                    $sdf['dispatch_time']  = time();
                    $sdf['auto_status']    = $this->orderStatus[$order['order_id']];
                } else {
                    $sdf['op_id']          = (int)$dispacthUser['op_id'];
                    $sdf['group_id']       = (int)$dispacthUser['group_id'];
                    $sdf['dispatch_time']  = time();
                    $sdf['confirm']        = 'Y';
                    $sdf['process_status'] = $isSplit ? 'splitting' : 'confirmed';
                }
                if ($sdf['order_id'] && $sdf['order_id'] > 0) {
                    // $orderObj->save($sdf);
                    $affect_row = $orderObj->update($sdf, array('order_id' => $sdf['order_id'], 'process_status|noequal' => 'splited'));
                    if ($affect_row) {
                        $opInfo          = kernel::single('ome_func')->get_system();
                        $usersObj        = app::get('desktop')->model('users');
                        $groupsObj       = app::get('ome')->model('groups');
                        $confirm_opname  = $usersObj->dump($dispacthUser['op_id'], 'name');
                        $confirm_opgroup = $groupsObj->dump($dispacthUser['group_id'], 'name');

                        $logMsg = sprintf('订单自动审单未通过,审单规则:%s(%s),仓库规则:%s(%s),详情见<br/>%s', $config['confirmName'] . '<span style="display:none">' . var_export($config, 1) . 'order_id:' . var_export($orderIds, 1) . '</span>', $config['confirmId'], (string) $this->_autobranch['name'], $this->_autobranch['tid'] ? $this->_autobranch['tid'] : '-', json_encode($this->status['log'], JSON_UNESCAPED_UNICODE)) . "<br>";

                        $logMsg .= "操作员:" . $curOpInfo['op_name'] . "获取订单，订单自动分派给确认组:" . $confirm_opgroup['name'] . ",确认人:" . ($confirm_opname ? $confirm_opname['name'] : '-');
                        $oOperation_log->write_log('order_dispatch@ome', $sdf['order_id'], $logMsg, null, $opInfo);
                        unset($logMsg);
                        kernel::single('ome_order_branch')->preSelect($sdf['order_id']);
                    }

                }
            }
        }
        $return = false;
        return $return;
    }

    /**
     * 获取发货单数据格式
     *
     * @param void
     * @return Array
     */
    private function fetchDeliveryFormat($consignee = null)
    {
        $result                                        = array();
        $this->delivery_group || $this->delivery_group = $this->getDeliveryGroup();
        $this->sms_gorup || $this->sms_gorup           = $this->getSendSmsGroup();
        
        foreach ($this->orders as $order) {
            //[抖音平台]获取本次拆单使用的渠道ID
            $channel_id = $order['channel_id'];
            
            //delivery
            $delivery = array('branch_id' => $this->getBranchId(),
                'logi_id'                     => $this->status['change']['dlyCorp']['corp_id'],
                'delivery_group'              => $this->delivery_group,
                'sms_group'                   => $this->sms_gorup,
                'consignee'                   => ($consignee ? $consignee : $this->getConsignee($order)),
                'delivery'                    => $this->getDeliveryStatus(),
                'delivery_items'              => array(),
                'wms_channel_id' => $channel_id, //WMS渠道ID
            );

            foreach ($order['objects'] as $obj) {
                foreach ($obj['items'] as $item) {
                    if ($item['delete'] == 'false') {
                        if ($delivery['delivery_items'][$item['product_id']]) {
                            $delivery['delivery_items'][$item['product_id']]['number'] += $item['nums'];
                        } else {
                            $delivery['delivery_items'][$item['product_id']] = array(
                                'item_type'       => $item['item_type'],
                                'product_id'      => $item['product_id'],
                                'shop_product_id' => $item['shop_product_id'],
                                'bn'              => $item['bn'],
                                'number'          => $item['nums'],
                                'product_name'    => $item['name'],
                                'spec_info'       => $item['addon'],
                            );
                        }

                        $delivery['order_items'][] = array(
                            'item_id'          => $item['item_id'],
                            'product_id'       => $item['product_id'],
                            'number'           => $item['nums'],
                            'bn'               => $item['bn'],
                            'product_name'     => $item['name'],
                            'oid'              => $obj['oid'],
                            's_type'           => $obj['s_type'],
                            'obj_id'           => $obj['obj_id'],
                        );
                    }
                }
            }
            $result[$order['order_id']] = $delivery;
            
            //分销一件代发订单
            if($order['betc_id'] && $order['cos_id']){
                $this->_is_shopyjdf = true;
            }
        }

        return $result;
    }

    /**
     * 获取发货单分组
     */
    public function getDeliveryGroup()
    {

        $this->initFilters();
        foreach ((array) self::$_orderGroups as $tId => $filter) {
            if ($filter->vaild($this)) {
                return $tId;
            }
        }
        return null;
    }

    /**
     * 检查涉及仓库选择的订单分组对像是否已经存在
     *
     * @param void
     * @return void
     */
    private function initFilters()
    {

        if (self::$_orderGroups === null) {

            $filters            = kernel::single('omeauto_auto_type')->getDeliveryGroupTypes();
            self::$_orderGroups = array();
            if ($filters) {

                foreach ($filters as $config) {

                    $filter = new omeauto_auto_group();
                    $filter->setConfig($config);
                    self::$_orderGroups[$config['tid']] = $filter;
                }
            }
        }
    }
    /**
     * 获取短信发送分组
     *
     * @param  void
     * @return void
     * @author
     **/
    public function getSendSmsGroup()
    {
        $this->initSmsFilters();
        foreach ((array) self::$_smsGroups as $tId => $filter) {
            if ($filter->vaild($this)) {
                return $tId;
            }
        }
        return null;
    }
    /**
     * 检查涉短信发送分组
     *
     * @param void
     * @return void
     */
    private function initSmsFilters()
    {

        if (self::$_smsGroups === null) {

            $filters          = kernel::single('omeauto_auto_type')->getAutoSendSmsTypes();
            self::$_smsGroups = array();
            if ($filters) {

                foreach ($filters as $config) {

                    $filter = new omeauto_auto_group();
                    $filter->setConfig($config);
                    self::$_smsGroups[$config['tid']] = $filter;
                }
            }
        }
    }
    /**
     * 获取发货地址信息
     *
     * @param Array $order 订单数据
     * @return Array
     */
    private function getConsignee($orders)
    {

        return array(
            'name'      => $orders['ship_name'],

            'r_time'    => $orders['ship_time'],
            'mobile'    => $orders['ship_mobile'],
            'zip'       => $orders['ship_zip'],
            'area'      => $orders['ship_area'],
            'telephone' => $orders['ship_tel'],
            'email'     => $orders['ship_email'],
            'addr'      => $orders['ship_addr'],
        );
    }

    /**
     * 检查是否能生成发货单
     *
     * @param Void
     * @return Boolean
     */
    public function canMkDelivery()
    {

        foreach ($this->orders as $order) {

            //增加防并发机制，防止由于操作问题导致的1订单生成2相同发货单的问题
            $_inner_key = sprintf("confirm_order_%s", $order['order_id']);
            $aData      = cachecore::fetch($_inner_key);
            if ($aData === false) {
                cachecore::store($_inner_key, 'confirmed', 5);
            } else {
                return false;
            }

            #增加 splitting、ship_status in('0', '2')
            if ($order['status'] != 'active' || $order['pause'] != 'false' || $order['abnormal'] != 'false' || !in_array($order['process_status'], array('unconfirmed', 'confirmed', 'splitting')) || !in_array($order['ship_status'], array('0', '2', '3'))) {
                return false; #开启拆单_部分发货、部分退货可继续拆分
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

            if (!$use_before_payed && $order['is_cod'] == 'false' && !in_array($order['pay_status'], array('1', '4', '5'))) {
                return false;
            }

            # [拆单]检查是否还可生成发货单
            $oOrder = app::get('ome')->model('orders');

            $canSplit  = false;
            $item_list = $oOrder->getItemBranchStore($order['order_id']);
            foreach ((array) $item_list as $il) {
                foreach ((array) $il as $var) {
                    foreach ((array) $var['order_items'] as $v) {
                        if ($v['left_nums'] > 0) {
                            $canSplit = true;
                        }
                    }
                }
            }

            if ($canSplit == false) {
                return false;
            }

        }
        return true;
    }

    /**
     * 生成发货单位
     *
     * @param Array $consignee 收件人信息
     * @return boolean
     */
    public function mkDelivery($consignee)
    {
        $split_status = '';
        $returnTxt = array('rsp' => 'succ');
        $oOperation_log = app::get('ome')->model('operation_log');
        $orderObj    = app::get('ome')->model("orders");
        $opInfo         = kernel::single('ome_func')->getDesktopUser();
        $user_id        = $opInfo['op_id'];
        

        if($this->splitNotDly) {
            foreach ($this->orders as $order) {
                list($rs, $msg) = kernel::single('ome_order_platform_split')->dealOrderObjects($order, $split_status);
                $log_msg = $split_status == 'splited' ? '订单确认' : '订单部分确认';
                if ($consignee['confirmFrom']) {
                    $log_msg .= '[' . $consignee['confirmFrom'] . ']';
                }
                if($rs) {
                    $sdf = array(
                        'order_id'       => $order['order_id'],
                        'confirm'        => 'Y',
                        'dispatch_time'  => time(),
                        'op_id'          => $user_id,
                    );
                    $orderObj->save($sdf);
                } else {
                    $log_msg .= '失败:'.$msg;
                    $returnTxt = ['rsp'=>'fail', 'msg'=>$msg];
                }
                $oOperation_log->write_log('order_confirm@ome', $order['order_id'], $log_msg);
            }
            return $returnTxt;
        }
        
        if (isset($consignee['memo'])) {
            $remark = $consignee['memo'];
            unset($consignee['memo']);
        } else {
            $remark = '';
        }
        
        $oper_source = '';
        if (isset($consignee['oper_source'])) {
            $oper_source = $consignee['oper_source'];
            unset($consignee['oper_source']);
        }
        
        $deliveryInfos = $this->fetchDeliveryFormat($consignee);

        $ids         = array();
        $deliveryObj = app::get('ome')->model("delivery");
        $branchLib   = kernel::single('ome_branch');

        //此处要增加判断
        $deliveryid_nums = count($deliveryInfos);
        
        // 本次发货单数量，如果大于1，就是属于合单的，合单的发货单，不能传物流单号，因为物流单号表字段唯一，会导致生成单据只能保存进一个子发货单
        if ($deliveryid_nums > 1) {
            $delivery_waybillCode   = null;
            $deliverySubWaybillCode = null;
        } else {
            $delivery_waybillCode   = $this->getWaybillCode();
            $deliverySubWaybillCode = $this->getSubWaybillCode();
        }
        
        // 是否指定发货单号
        $assign_delivery_bn = $this->getAssignDeliveryBn();
        if($assign_delivery_bn){
            if(count($deliveryInfos) > 1){
                $error_msg = '本次会生成多个发货单，不允许使用指定发货单号';
                return ['rsp'=>'fail', 'msg'=>$error_msg];
            }
            
            // 检查指定发货单号是否已经存在
            $deliveryRow = $deliveryObj->db_dump(array('delivery_bn'=>$assign_delivery_bn), 'delivery_id, delivery_bn');
            if($deliveryRow){
                $error_msg = '指定发货单号：'. $assign_delivery_bn .'已经存在,请检查';
                return ['rsp'=>'fail', 'msg'=>$error_msg];
            }
        }
        
        $orderBranch = [];
        $orderIds = [];
        foreach ($deliveryInfos as $orderId => $deliveryInfo) {
            if ($orderId && $orderId > 0) {
                $deliveryInfo['memo']                     = $remark;
                $deliveryInfo['delivery_waybillCode']     = $delivery_waybillCode; #菜鸟的智选物流会返回物流单号
                $deliveryInfo['delivery_sub_waybillCode'] = $deliverySubWaybillCode;
                $order_items                              = $deliveryInfo['order_items'];
                unset($deliveryInfo['order_items']);
                
                // 指定发货单号
                if($assign_delivery_bn && empty($deliveryInfo['delivery_bn'])){
                    $deliveryInfo['delivery_bn'] = $assign_delivery_bn;
                }
                
                // 是否补差价订单
                $is_diff_order = false;
                
                // 发货单扩展信息
                $deliveryExtendInfo = $this->getDeliveryExtendInfo();
                
                // 生成发货单
                $addDeliveryStart = microtime(true);
                $result = $deliveryObj->addDelivery($orderId, $deliveryInfo, array(), $order_items, $split_status, $is_diff_order, $deliveryExtendInfo);
                $addDeliveryTime = microtime(true) - $addDeliveryStart;
                
                // 记录addDelivery性能数据
                ome_api_log::add_message('node', 'addDelivery', $addDeliveryTime, array(
                    'order_id' => $orderId,
                    'delivery_id' => $result['data'] ?? null
                ));

                if ($result['rsp'] == 'succ') {
                    $delivery_id = $result['data'];
                    $ids[]       = $delivery_id;
                    $orderIds[]  = $orderId;

                    $orderBranch[$orderId] = $deliveryInfo['branch_id'];

                    //更新订单信息
                    $sdf = array(
                        'order_id'       => $orderId,
                        'process_status' => $split_status, //addDelivery()中引用值
                        'confirm'        => 'Y',
                        'dispatch_time'  => time(),
                        'op_id'          => $user_id,
                        'logi_no'        => $this->getWaybillCode() ? $this->getWaybillCode() : '', #菜鸟智选物流，会返回物流单号，保存到订单上
                        'splited_num_upset_sql' => 'IF(`splited_num` IS NULL, 1, `splited_num` + 1)',
                    );
                    $orderObj->save($sdf);

                    //订单部分确认加入发货单号
                    $log_msg = $split_status == 'splited' ? '订单确认' : '订单部分确认';
                    if ($consignee['confirmFrom']) {
                        $log_msg .= '[' . $consignee['confirmFrom'] . ']';
                    }
                    $get_dly_bn = $deliveryObj->getList('delivery_id, delivery_bn', array('delivery_id' => $delivery_id), 0, 1);
                    $get_dly_bn = $get_dly_bn[0];
                    $log_msg .= $oper_source . '（发货单号：<a href="index.php?app=ome&ctl=admin_receipts_print&act=show_delivery_items&id=' . $delivery_id . '" target="_blank">'
                        . $get_dly_bn['delivery_bn'] . '</a>）';

                    $oOperation_log->write_log('order_confirm@ome', $orderId, $log_msg, null, $opInfo);

                    //标记当前门店履约订单已分派
                    $store_id = $branchLib->isStoreBranch($deliveryInfo['branch_id']);
                    if ($store_id) {
                        kernel::single('ome_o2o_performance_orders')->updateProcessStatus($orderId, 'confirm');
                    }
                    // 全链路审单/通知配货回流
                    $messageStartTime = microtime(true);
                    kernel::single('ome_event_trigger_shop_order')->order_message_produce(array($orderId),['check','to_wms']);
                    $messageTime = microtime(true) - $messageStartTime;
                    
                    // 记录全链路消息生产性能数据
                    ome_api_log::add_message('message', 'order_message_produce', $messageTime, array(
                        'order_id' => $orderId,
                        'message_types' => 'check,to_wms'
                    ));
                    
                    // [更新]预约订单的确认状态
                    $error_msg = '';
                    kernel::single('ome_order_reservation')->operateReservationOrder($sdf, $error_msg);
                    
                } elseif ($result['rsp'] == 'fail') {
                    $returnTxt = array('rsp' => 'fail', 'msg' => $result['msg']);
                }
            }
        }

        //如果物流公司是当当物流不可以合并发货单
        $dly_corp       = app::get(omeauto_auto_combine::__ORDER_APP)->model('dly_corp')->dump(array('corp_id' => $this->status['change']['dlyCorp']['corp_id']), 'type');
        $combine_select = app::get('ome')->getConf('ome.combine.select');
        $_isCombine     = true;
        if ($dly_corp['type'] == 'DANGDANG' || $dly_corp['type'] == 'AMAZON' || $combine_select == '1') {
            $_isCombine = false;
        }
        
        $waybillCode = $this->getWaybillCode();
        
        //合并发货单
        $err = '';
        if (!empty($ids) && count($ids) > 1 && $_isCombine) {
            //多个订单合并审核，合并发货单
            $newdly_id = $deliveryObj->merge($ids, array('logi_no' => $this->getWaybillCode(), 'logi_id' => $this->status['change']['dlyCorp']['corp_id'], 'logi_name' => $this->status['change']['dlyCorp']['name'], 'memo' => $remark));

            //更新运单号
            if ($waybillCode) {
                $deliveryObj->db->exec("UPDATE sdb_ome_delivery SET logi_no='" . $waybillCode . "' WHERE delivery_id=" . $newdly_id);
            }
            //标识合单
            kernel::single('ome_bill_label')->markBillLabel($newdly_id, '', 'SOMS_COMBINE_ORDER', 'ome_delivery');
            foreach ($orderIds as $order_id) {
                kernel::single('ome_bill_label')->markBillLabel($order_id, '', 'SOMS_COMBINE_ORDER', 'order', $err, 0);
            }
            // 发货单创建完成后的service扩展点
            $serviceStart = microtime(true);
            foreach(kernel::servicelist('omeauto.service.group.item.confirm.after') as $serviceName => $object) {
                $individualServiceStart = microtime(true);
                if(method_exists($object, 'afterCreateDelivery')) {
                    $object->afterCreateDelivery($newdly_id);
                }
                $serviceTime = microtime(true) - $individualServiceStart;
                
                // 记录每个service的性能数据
                ome_api_log::add_message('service', $serviceName, $serviceTime, array(
                    'delivery_id' => $newdly_id
                ));
            }
            //发货单通知单推送仓库
            $wmsStart = microtime(true);
            ome_delivery_notice::create($newdly_id);
            $wmsTime = microtime(true) - $wmsStart;
            
            // 记录WMS通知性能数据
            ome_api_log::add_message('node', 'wms_notification', $wmsTime, array(
                'delivery_id' => $newdly_id
            ));
        } else {
            //发货单通知单推送仓库
            foreach ($ids as $newdly_id) {
                if ($waybillCode) {
                    //更新运单号
                    $deliveryObj->db->exec("UPDATE sdb_ome_delivery SET logi_no='" . $waybillCode . "' WHERE delivery_id=" . $newdly_id);

                }
                
                // 发货单创建完成后的service扩展点
                foreach(kernel::servicelist('omeauto.service.group.item.confirm.after') as $serviceName => $object) {
                    $individualServiceStart = microtime(true);
                    if(method_exists($object, 'afterCreateDelivery')) {
                        $object->afterCreateDelivery($newdly_id);
                    }
                    $serviceTime = microtime(true) - $individualServiceStart;
                    
                    // 记录每个service的性能数据
                    ome_api_log::add_message('service', $serviceName, $serviceTime, array(
                        'delivery_id' => $newdly_id
                    ));
                }
                $wmsStart = microtime(true);
                ome_delivery_notice::create($newdly_id);
                $wmsTime = microtime(true) - $wmsStart;
                
                // 记录WMS通知性能数据
                ome_api_log::add_message('node', 'wms_notification', $wmsTime, array(
                    'delivery_id' => $newdly_id
                ));
            }
        }
        
        // todo maxiaochen 得物品牌直发 请求接单接口，如果检测到有取消的发货单，则先调用发货仓修改接口
        $this->_dewu_afterAddDelivery($orderBranch);

        return $returnTxt;
    }

    /**
     * 获取订单重量
     *
     * @param Array $order 订单信息
     * @return
     */
    public function getWeight()
    {
        if (isset($this->orderWeight)) {
            //订单分组使用的是整个重量，拆单取的是拆出来的重量
            if (!$this->isProcessSplit) {
                return $this->orderWeight;
            }
        }

        $weight = 0;
        foreach ($this->orders as $key => $order) {
            $order_weight = app::get('ome')->model('orders')->getOrderWeight($order['order_id']);
            if ($order_weight == 0) {
                $weight = 0;
                break;
            } else {
                $weight += $order_weight;
            }

        }

        $this->orderWeight = $weight;
        return $weight;
    }

    /**
     * 获取店铺类型
     *
     * @param void
     * @return String
     */
    public function getShopType()
    {

        foreach ($this->orders as $key => $order) {

            return $order['shop_type'];
            break;
        }
    }

    #智选物流订单数据
    public function get_order_data()
    {
        #本次所有的合并订单
        $combine_order_ids = array_keys($this->orders);
        $data              = array('main_order_bn' => $this->orders[$combine_order_ids[0]]['order_bn'], 'main_ship_area' => $this->getShipArea(), 'combine_order_ids' => $combine_order_ids); #任取一个合并订单id,做主单order_bn3
        return $data;
    }
    
    /**
     * 设置自动发货
     *
     * @return String
     */
    public function setAutoDelivery()
    {
        $this->_isAutoDelivery = true;
    }
    
    /**
     * 是否自动发货
     *
     * @return String
     */
    public function isAutoDelivery()
    {
        return $this->_isAutoDelivery;
    }

    // 先调用发货仓修改接口，再去调用接单接口
    public function _dewu_afterAddDelivery($orderBranch = [])
    {
        if (!$orderBranch) {
            return true;
        }
        $order_id_arr   = array_keys($orderBranch);
        $orderMdl       = app::get('ome')->model('orders');
        $orderExtMdl    = app::get('ome')->model('order_extend');
        $branchMdl      = app::get('ome')->model('branch');
        $oAddressMdl    = app::get('ome')->model('return_address');

        $orderList = $orderMdl->getList('order_id,order_bool_type,shop_type,shop_id,order_bn', ['order_id|in'=>$order_id_arr, 'shop_type'=>'dewu']);
        if (!$orderList) {
            return true;
        }

        $branchList = $branchMdl->getList('branch_id, branch_bn, name', ['skip_permission'=>false, 'branch_id|in'=>array_values($orderBranch)]);
        $branchList = array_column($branchList, null, 'branch_id');

        $dewuBrandList = $oAddressMdl->getList('branch_bn, contact_id', ['shop_type'=>'dewu']);
        $dewuBrandList = array_column($dewuBrandList, null, 'branch_bn');

        $orderExtList = $orderExtMdl->getList('extend_field,order_id,platform_logi_no', ['order_id|in'=>$order_id_arr]);
        $orderExtList = array_column($orderExtList, null, 'order_id');
        foreach ($orderExtList as $ok => $ov) {
            if ($ov['extend_field'] && is_string($ov['extend_field'])) {
                $ov['extend_field'] = json_decode($ov['extend_field'], 1);
                $orderExtList[$ok]  = $ov;
            }
        }

        foreach ($orderList as $k => $v) {
            if (!kernel::single('ome_order_bool_type')->isDWBrand($v['order_bool_type'])) {
                continue;
            }
            if ($orderExtList[$v['order_id']]['extend_field']['performance_type'] != '3') {
                continue;
            }

            if (!$dewuBrandList[$branchList[$orderBranch[$v['order_id']]]['branch_bn']]) {
                continue;
            }

            $addressInfo = $dewuBrandList[$branchList[$orderBranch[$v['order_id']]]['branch_bn']];
            if (!$orderExtList[$v['order_id']]['platform_logi_no']) {
                // 接单，虽然接口返回物流单号和承运商，但是不保存，打印发货单的时候统一获取
                $res = kernel::single('erpapi_router_request')->set('shop', $v['shop_id'])->order_acceptOrder($v['order_bn'], $addressInfo['contact_id']);
            } else {
                // 更新仓库
                $res = kernel::single('erpapi_router_request')->set('shop', $v['shop_id'])->delivery_changeDeliveryWarehouse($v['order_bn'], $addressInfo['contact_id'], 1);
            }
        }
        return true;
    }


}
