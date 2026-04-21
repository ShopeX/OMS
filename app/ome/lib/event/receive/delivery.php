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

class ome_event_receive_delivery extends ome_event_response
{

    /**
     * 发货单对象
     */
    private $__dlyObj = null;

    /**
     * 日志对象
     */
    private $__operationLogObj = null;

    /**
     * 当前处理的发货单源数据
     */
    private $__currDlyInfo = array();

    /**
     * 当前处理的发货单单号
     */
    private $__currDlyBn = "";

    /**
     * 当前处理的发货单ID
     */
    private $__currDlyId = "";

    /**
     * 当前发货单是否是合并发货单
     */
    private $__isBind = false;

    /**
     * 当前发货单关联仓储是否管控库存
     */
    private $__storeCtrl = true;

    /**
     * 合并发货单的子发货单ID数组
     */
    private $__currDlyChilds = array();

    /**
     * 是否是第三方仓储回传，根据回传参数判断的
     */
    private $__isThirdParty = false;

    /**
     * 当前传入的参数
     */
    private $__inputParams = array();

    /**
     * 格式化后的参数
     */
    private $__formatParams = false;

    /**
     * 加载库存的处理模式Lib类
     */
    private $__storeManageLib = null;

    /**
     *
     * 发货通知单处理总入口
     * @param array $data
     */
    public function update($data)
    {
        $msg_code = '';
        
        //参数检查
        if (!isset($data['status'])) {
            return $this->send_error('必要参数缺失', $msg_code, $data);
        }

        $type = $data['status'];
        unset($data['status']);
        switch ($type) {
            case 'delivery':
                return $this->consign($data);
                break;
            case 'print':
                return $this->setPrint($data);
                break;
            case 'check':
                return $this->setCheck($data);
                break;
            case 'cancel':
                return $this->rebackDly($data);
                break;
            case 'update':
                return $this->updateDetail($data);
                break;
            case 'confirm':
                return $this->confirm($data);
                break;
            case 'sign':
                return $this->sign($data);
                break;
            case 'return_back':
                return $this->returnBackDly($data); //追回物流包裹
                break;
            case 'exception':
                return $this->abnormal($data); //异常EXCEPTION
                break;
            default:
                return $this->send_succ('未知的发货单操作通知行为', $msg_code, $data);
                break;
        }
    }

    /**
     *
     * 初始化核心所需的加载类
     * @param void
     */
    private function _instanceObj()
    {
        $this->__dlyObj          = app::get('ome')->model('delivery');
        $this->__operationLogObj = app::get('ome')->model('operation_log');
        $this->__storeManageLib  = kernel::single('ome_store_manage');
    }

    /**
     *
     * 初始化发货单信息
     * @param array $params 传入参数
     * @param string $msg 错误信息
     */
    private function _initDlyInfo($params, &$msg)
    {
        if (!isset($params['delivery_bn']) || empty($params['delivery_bn'])) {
            $msg = '发货单通知单编号参数没有定义!';
            return false;
        }

        $deliveryInfo = $this->__dlyObj->dump(array('delivery_bn' => $params['delivery_bn']), '*', array('delivery_items' => array('*'), 'delivery_order' => array('*')));
        if (!isset($deliveryInfo['delivery_id'])) {
            $msg = '发货单通知单编号不存在!';
            return false;
        }
        $wdMdl = app::get('console')->model('wms_delivery');
        $wdRow = $wdMdl->db_dump(['delivery_bn'=>$params['delivery_bn'], 'delivery_status'=>'1'], 'id');
        if($wdRow) {
            $wdRs = $wdMdl->update(['delivery_id'=>$deliveryInfo['delivery_id'], 'delivery_status'=>'2'], ['id'=>$wdRow['id'], 'delivery_status'=>'1']);
            if(!is_bool($wdRs)) {
                app::get('ome')->model('operation_log')->write_log('wms_delivery@console',$wdRow['id'], '匹配成功：'.$params['delivery_bn']);
            }
        }
        //WMS仓储订单号(外部发货单号)
        $params['original_delivery_bn'] = ($params['original_delivery_bn'] ? $params['original_delivery_bn'] : '0');

        //接口传入参数
        $this->__inputParams = $params;

        //当前处理的发货单原始数据
        $this->__currDlyInfo = $deliveryInfo;

        //当前处理的发货单ID
        $this->__currDlyId = $deliveryInfo['delivery_id'];

        //当前处理的发货单单号
        $this->__currDlyBn = $deliveryInfo['delivery_bn'];

        // 重置格式化后的参数
        $this->__formatParams = [];

        //识别当前发货单是否是合并的
        $this->__isBind = ($deliveryInfo['is_bind'] == 'true') ? true : false;

        //如果是合并的，取出子发货单的id数组
        if ($this->__isBind) {
            $this->__currDlyChilds = $this->__dlyObj->getItemsByParentId($this->__currDlyId, 'array');
        }

        //检查仓储类型，如果是门店自提不管控库存的，不处理冻结释放和仓库库存扣减的逻辑
        $this->__storeManageLib->loadBranch(array('branch_id' => $deliveryInfo['branch_id']));
        $this->__isStoreBranch = $this->__storeManageLib->isStoreBranch();
        $this->__storeCtrl     = $this->__storeManageLib->isCtrlBranchStore(); //是否管控库存

        unset($deliveryInfo);
        return true;
    }

    /**
     *
     * 执行发货的时候，检查发货单相关状态
     * @param string $msg 错误信息
     */
    private function _checkStatusWhenConsign(&$msg)
    {
        //判断是否发货单已取消如果已取消不更新
        if (in_array($this->__currDlyInfo['status'], array('cancel', 'back', 'return_back'))) {
            foreach ($this->__currDlyInfo['delivery_order'] as $dlyOrder) {
                $this->__operationLogObj->write_log('order_modify@ome', $dlyOrder['order_id'], '第三方仓库回写:已发货状态,因发货单目前状态为已取消或打回,不更新');
            }

            $msg = '发货单状态为已取消不更新发货状态!';
            return false;
        }

        //如果传入有物流公司和运单号，检查运单号是否被其他发货单占用
        if (isset($this->__inputParams['logi_no'])) {
            // 验证运单号是否存在
            $dlyInfo = $this->__dlyObj->dump(array('logi_no'=>$this->__inputParams['logi_no'], 'delivery_id|noequal'=>$this->__currDlyInfo['delivery_id']), 'delivery_id,original_delivery_bn');
            if($dlyInfo && $dlyInfo['original_delivery_bn'] == $this->__inputParams['original_delivery_bn'])
            {
                $msg = '运单号重复!';
                return false;
            }
        }

        return true;
    }

    /**
     *
     * 格式化相应参数
     * @param void
     */
    private function _convertConsignSdf()
    {

        $order_fundObj                                = kernel::single('ome_func');
        $this->__formatParams['delivery_time']        = isset($this->__inputParams['delivery_time']) ? $order_fundObj->date2time($this->__inputParams['delivery_time']) : time();
        $this->__formatParams['weight']               = isset($this->__inputParams['weight']) ? floatval($this->__inputParams['weight']) : 0.00;
        $this->__formatParams['delivery_cost_actual'] = isset($this->__inputParams['delivery_cost_actual']) ? $this->__inputParams['delivery_cost_actual'] : 0.00;

        //第三方回写发货要更新物流相关信息
        $dlyCorpObj = app::get('ome')->model('dly_corp');
        if (isset($this->__inputParams['logi_id']) && isset($this->__inputParams['logi_no'])) {
            $dlyInfo    = $dlyCorpObj->dump(array('type' => $this->__inputParams['logi_id']), 'corp_id,name');

            //物流公司是否发生变化，不变化已原来的为准
            $erpdlyInfo = $dlyCorpObj->dump(array('corp_id' => $this->__currDlyInfo['logi_id'], 'type' => $this->__inputParams['logi_id']), 'corp_id,name');
            if (!$erpdlyInfo) {
                $this->__formatParams['logi_id']   = empty($dlyInfo['corp_id']) ? '' : $dlyInfo['corp_id'];
                $this->__formatParams['logi_name'] = empty($dlyInfo['name']) ? '' : $dlyInfo['name'];
            }

            $this->__formatParams['logi_no'] = $this->__inputParams['logi_no'];
            $this->__isThirdParty            = true;
        }

        if(empty($this->__inputParams['logi_id']) && empty($this->__inputParams['logi_no'])) {
            // 大件发货没有运单号使用发货单号作为运单号
            $logiId = $this->__formatParams['logi_id'] ? : $this->__currDlyInfo['logi_id'];
            $erpdlyInfo = $dlyCorpObj->db_dump(array('corp_id' => $logiId), 'corp_model');
            if ($erpdlyInfo['corp_model'] == 'heavy') {
                $this->__formatParams['logi_no'] = $this->__currDlyInfo['delivery_bn'];
                $this->__isThirdParty            = true;
            }
        }

        if ($this->__inputParams['bill_logi_weight']) {
            $this->__formatParams['bill_logi_weight'] = $this->__inputParams['bill_logi_weight'];
        }

        if ($this->__inputParams['out_serial']) {
            $this->__formatParams['out_serial'] = $this->__inputParams['out_serial'];
        }

        if ($this->__inputParams['out_storagelife']) {
            $this->__formatParams['out_storagelife'] = $this->__inputParams['out_storagelife'];
        }
        return true;
    }

    /**
     *
     * 发货通知单发货事件处理
     * @param array $data
     */
    private function consign($data)
    {
        $msg_code = '';
        
        //初始化类的对象
        $this->_instanceObj();

        //初始化当前处理发货单的数据
        if (!$this->_initDlyInfo($data, $msg)) {
            return $this->send_error($msg, $msg_code, $data);
        }

        //该检查项单拉出来，如果是已发货的返回成功的响应标记，伊藤忠会出现成功发货还重复请求的问题
        if ($this->__currDlyInfo['status'] == 'succ') {
            return $this->send_succ('发货成功');
        }

        //检查当前发货单对应状态是否可以操作
        if (!$this->_checkStatusWhenConsign($msg)) {
            return $this->send_error($msg, $msg_code, $this->__inputParams);
        }

        //组织参数
        $this->_convertConsignSdf();

        /*发货处理核心流程 Begin*/
        //加入事务机制
        $trans = kernel::database()->beginTransaction();

        $this->_dealBatch();

        $this->_dealSnList();

        $this->_dealDeliveryPackage();
        
        //具体发货处理
        if (!$this->_consign($msg, $msg_code)) {

            kernel::database()->rollBack();

            return $this->send_error($msg, $msg_code, array('params' => json_encode($this->__inputParams), 'obj_bn' => $data['delivery_bn']));
        }

        //生成出入库明细及销售单
        if (!$this->_consign_siso($msg)) {

            kernel::database()->rollBack();

            return $this->send_error($msg, $msg_code, array('params' => json_encode($this->__inputParams), 'obj_bn' => $data['delivery_bn']));
        }

        // 事务提交
        kernel::database()->commit($trans);
        /*发货处理核心流程 End*/

        //发货后续处理
        $this->_after_consign();
        
        return $this->send_succ('发货成功');
    }

    private function _dealBatch() {
        if($this->__inputParams['items']){
            foreach ((array)$this->__inputParams['items'] as $item) {
                if($item['batch']) {
                    $useLogModel = app::get('console')->model('useful_life_log');
                    $bnBmId = app::get('material')->model('basic_material')->db_dump(array('material_bn'=>$item['bn']), 'bm_id');
                    $useful = [];
                    foreach ($item['batch'] as $bv) {

                        if (!$bv['purchase_code']) {
                            continue;
                        }

                        $tmpUseful = [];
                        $tmpUseful['product_id'] = $bnBmId['bm_id'];
                        $tmpUseful['bn'] = $item['bn'];
                        $tmpUseful['original_bn'] = $this->__currDlyBn;
                        $tmpUseful['original_id'] = $this->__currDlyId;
                        $tmpUseful['sourcetb'] = 'delivery';
                        $tmpUseful['create_time'] = time();
                        $tmpUseful['stock_status'] = '0';
                        $tmpUseful['num'] = $bv['num'];
                        $tmpUseful['normal_defective'] = $bv['normal_defective'];
                        $bv['product_time'] && $tmpUseful['product_time'] = $bv['product_time'];
                        $bv['expire_time'] && $tmpUseful['expire_time'] = $bv['expire_time'];
                        $tmpUseful['purchase_code'] = $bv['purchase_code'];
                        $tmpUseful['produce_code'] = $bv['produce_code'];
                        $useful[] = $tmpUseful;
                    }

                    if ($useful) {
                        $useLogModel->db->exec(ome_func::get_insert_sql($useLogModel, $useful));
                    }
                }
            }
        }

    }

    private function _dealSnList() {
        foreach ((array)$this->__inputParams['items'] as $item) {

            if (($item['imeiList'] && $item['imeiList']['extSnList']) || $item['sn_list']) {

                $bnBm = app::get('material')->model('basic_material')->db_dump(array('material_bn'=>$item['bn']), 'bm_id,material_name');
                $serialHistoryObj = app::get('ome')->model('product_serial_history');
                $opInfo = kernel::single('ome_func')->getDesktopUser();
                $historyData = [];

                $_tmp = [
                    'branch_id' => $this->__currDlyInfo['branch_id'],
                    'bn' => $item['bn'],
                    'product_name' => $bnBm['material_name'],
                    'act_type' => '1',
                    'act_time' =>time(),
                    'act_owner' => $opInfo['op_id'],
                    'bill_type' => '1',
                    'bill_id' => $this->__currDlyId,
                    'bill_no' => $this->__currDlyBn,
                ];
            }

            if ($item['imeiList'] && $item['imeiList']['extSnList']) {
                
                // 兼容sn vs imei 一对多的情况
                $_tmp_sn_imei = [];
                foreach ($item['imeiList']['extSnList'] as $_extSn) {
                    $_tmp_sn_imei[$_extSn['sn']][] = $_extSn['imei'];
                }

                foreach ($_tmp_sn_imei as $_sn => $_imei_arr) {
                    $tmp = $_tmp;
                    $tmp['serial_number'] = $_sn;
                    $tmp['imei_number'] = implode(',', $_imei_arr);
                    $historyData[] = $tmp;
                }
                $serialHistoryObj->db->exec(ome_func::get_insert_sql($serialHistoryObj, $historyData));

            } elseif ($item['sn_list']) {

                foreach($item['sn_list'] as $serial){
                    $tmp = $_tmp;
                    $tmp['serial_number'] = $serial;
                    $historyData[] = $tmp;
                }
                $serialHistoryObj->db->exec(ome_func::get_insert_sql($serialHistoryObj, $historyData));
            }
        }
    }

    private function _dealDeliveryPackage() {
        $packageObj = app::get('ome')->model('delivery_package');
        if($packageObj->db_dump(['delivery_id'=>$this->__currDlyId], 'package_id')) {
            return;
        }
        if($this->__inputParams['package']) {
            $package = $this->__inputParams['package'];
            foreach($package as $k => $v) {
                $package[$k]['delivery_id'] = $this->__currDlyId;
                if(empty($v['create_time'])) {
                    $package[$k]['create_time'] = time();
                }
                if(empty($v['delivery_time'])) {
                    $package[$k]['delivery_time'] = time();
                }
                if(empty($v['logi_no'])) {
                    $v['logi_no'] = $this->__formatParams['logi_no'] ? : $this->__currDlyInfo['logi_no'];
                }
            }
        } else {
            if($this->__formatParams['logi_id']) {
                $logi_id = $this->__formatParams['logi_id'];
            } else {
                $logi_id = $this->__currDlyInfo['logi_id'];
            }
            $logisticsCode = app::get('ome')->model('dly_corp')->db_dump(['corp_id'=>$logi_id], 'type')['type'];
            $expressCode = $this->__formatParams['logi_no'] ? : $this->__currDlyInfo['logi_no'];
            $package = [];
            foreach($this->__currDlyInfo['delivery_items'] as $iv) {
                $package[] = [
                    'delivery_id' => $this->__currDlyId,
                    'package_bn' => $this->__currDlyBn,
                    'logi_bn' => $logisticsCode,
                    'logi_no' => $expressCode,
                    'product_id' => $iv['product_id'],
                    'bn' => $iv['bn'],
                    'status' => 'delivery',
                    'number' => $iv['number'],
                    'create_time' => time(),
                    'delivery_time' => time(),
                ];
            }
        }
        $packageObj->db->exec(ome_func::get_insert_sql($packageObj, $package));
    }

    /**
     * 发货详细处理流程
     *
     * @param string $msg 错误信息
     * @return void
     **/
    private function _consign(&$msg, &$msg_code)
    {
        //[拆单]配置
        $orderSplitLib = kernel::single('ome_order_split');
        $split_seting  = $orderSplitLib->get_delivery_seting();
        
        $orderObj    = app::get('ome')->model('orders');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        $delivery_sync = app::get('ome')->model('delivery_sync');
        $reservationMdl = app::get('ome')->model('order_reservation');
        
        $err_msg = '';
        $opinfo = [];
        
        //是否合并发货单
        if ($this->__isBind) {
            //主发货单更新信息，如果是第三方仓储的并且物流公司有变化，则赋值更新
            if ($this->__isThirdParty == true) {
                if ($this->__formatParams['logi_id']) {
                    $maindly['logi_id'] = $this->__formatParams['logi_id'];
                }
                if ($this->__formatParams['logi_name']) {
                    $maindly['logi_name'] = $this->__formatParams['logi_name'];
                }

                $maindly['logi_no'] = $this->__formatParams['logi_no'];
            }

            //子发货单相应处理
            foreach ($this->__currDlyChilds as $item) {
                $delivery = $this->__dlyObj->dump($item, 'delivery_id,type,is_cod,branch_id,shop_id', array('delivery_items' => array('*'), 'delivery_order' => array('*')));

                $de     = $delivery['delivery_order'];
                $or     = array_shift($de);
                $ord_id = $or['order_id'];

                //普通发货单才做库存处理、订单发货数量的更新
                if (in_array($this->__currDlyInfo['type'], array('normal'))) {
                    //仓库库存处理
                    $params['params']    = array_merge($delivery, array('order_id' => $ord_id));
                    $params['node_type'] = 'consignDly';
                    $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$delivery['shop_id']], 'delivery_mode');
                    if($shop['delivery_mode'] == 'jingxiao') {
                        $processResult       = true;
                    } else {
                        $processResult       = $this->__storeManageLib->processBranchStore($params, $err_msg);
                    }
                    if (!$processResult) {
                        $msg      = $err_msg;
                        $msg_code = 'W30012';
                        return false;
                    }
                    
                    //[行锁]先更新订单物流单号,防并发
                    //@todo：订单拆分多个发货单,同分同秒发货,导致订单是部分发货状态;
                    if ($this->__isThirdParty == true && $this->__formatParams['logi_no']) {
                        $updateSdf = array();
                        $updateSdf['logi_no'] = $this->__formatParams['logi_no'];
                        if($this->__formatParams['logi_id']){
                            $updateSdf['logi_id'] = $this->__formatParams['logi_id'];
                        }
                        
                        $orderObj->update($updateSdf, array('order_id' => $ord_id));
                    }
                    
                    //订单发货数量更新
                    $this->__dlyObj->consignOrderItem($delivery);
                }

                $childly['delivery_id']   = $delivery['delivery_id'];
                $childly['process']       = 'true';
                $childly['status']        = 'succ';
                $childly['delivery_time'] = $this->__formatParams['delivery_time'];
                $this->__dlyObj->save($childly); //更新子发货单发货状态为已发货

                $item_num = $this->__dlyObj->countOrderSendNumber($ord_id);
                if ($item_num == 0) {
                    //已发货
                    //订单全部发货，清除订单级预占流水
                    //检测仓库是否管控库存，如果是需要释放订单冻结
                    $isCtrlStore = kernel::single('ome_branch')->getBranchCtrlStore($this->__currDlyInfo['branch_id']);
                    if($isCtrlStore === false){
                        $orderObj->unfreez($ord_id,false);
                    }
                    if(isset($shop) && $shop['delivery_mode'] == 'jingxiao') {
                        $freeResult = $basicMStockFreezeLib->delOrderFreeze($ord_id);
                        if (!$freeResult) {
                            $msg      = '删除流水冻结失败!';
                            $msg_code = 'W30012';
                            return false;
                        }
                    }
                    $orderInfo = $orderObj->db_dump(['order_id'=>$ord_id]);
                    // 检测京东订单是否有微信支付先用后付的单据
                    $use_before_payed = false;
                    if ($orderInfo['shop_type'] == '360buy') {
                        $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($orderInfo['order_id']);
                        $labelCode = array_column($labelCode, 'label_code');
                        $use_before_payed = kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode);
                    }

                    if (($delivery['is_cod'] == 'false' && !$use_before_payed) || ($delivery['is_cod'] == 'false' && $use_before_payed && $orderInfo['pay_status'] == '1') || ($delivery['is_cod'] == 'true' && $orderInfo['pay_status'] == '1')) {
                        $orderdata['status'] = 'finish';
                    }
                    $orderdata['archive']     = 1; //订单归档
                    $orderdata['ship_status'] = '1';
                    $orderdata['delivery_time'] = time();
                    $affect_order             = $orderObj->update($orderdata, array('order_id' => $ord_id)); //更新订单发货状态
                } else {
                    //部分发货
                    $orderdata['ship_status'] = '2';
                    $orderdata['delivery_time'] = time();
                    $affect_order             = $orderObj->update($orderdata, array('order_id' => $ord_id)); //更新订单发货状态
                }

                if (!is_numeric($affect_order) || $affect_order <= 0) {
                    $msg_code = 'W30012';
                    $msg      = '订单状态更新失败!';
                    return false;
                }
                
                // 预约订单状态
                $custom_reserved_status = ($orderdata['ship_status'] == '2' ? '6' : '5');
                $reservationMdl->update(['ship_status'=>$orderdata['ship_status'], 'custom_reserved_status'=>$custom_reserved_status], array('order_id'=>$ord_id));
                
                //标记当前门店履约订单已发货
                if ($this->__isStoreBranch) {
                    kernel::single('ome_o2o_performance_orders')->updateProcessStatus($ord_id, 'consign');
                }

                //[拆单]新增_发货单状态回写记录
                $dly_data  = array();
                $frst_info = $orderObj->dump(array('order_id' => $ord_id), 'shop_id, shop_type, order_bn');

                if ($split_seting) {
                    $dly_data['order_id']      = $ord_id;
                    $dly_data['order_bn']      = $frst_info['order_bn'];
                    $dly_data['delivery_id']   = $this->__currDlyId;
                    $dly_data['delivery_bn']   = $this->__currDlyBn;
                    $dly_data['logi_no']       = isset($maindly['logi_no']) ? $maindly['logi_no'] : $this->__currDlyInfo['logi_no'];
                    $dly_data['logi_id']       = isset($maindly['logi_id']) ? $maindly['logi_id'] : $this->__currDlyInfo['logi_id'];
                    $dly_data['branch_id']     = $this->__currDlyInfo['branch_id'];
                    $dly_data['status']        = $childly['status']; //发货状态
                    $dly_data['shop_id']       = $this->__currDlyInfo['shop_id'];
                    $dly_data['delivery_time'] = $this->__formatParams['delivery_time'];
                    $dly_data['dateline']      = $this->__formatParams['delivery_time'];
                    $dly_data['split_model']   = intval($split_seting['split_model']); //拆单方式
                    $dly_data['split_type']    = intval($split_seting['split_type']); //回写方式

                    $delivery_sync->save($dly_data);
                }

                unset($delivery, $childly, $orderdata);
            }

            // 更新主发货单
            $maindly['delivery_id']          = $this->__currDlyId;
            $maindly['delivery_bn']          = $this->__currDlyBn;
            $maindly['process']              = 'true';
            $maindly['status']               = 'succ';
            $maindly['weight']               = $this->__formatParams['weight'];
            $maindly['delivery_time']        = $this->__formatParams['delivery_time'];
            $maindly['delivery_cost_actual'] = $this->__formatParams['delivery_cost_actual'];

            //打印状态
            $maindly['expre_status'] = 'true';
            $maindly['deliv_status'] = 'true';
            $maindly['stock_status'] = 'true';
            $maindly['print_status'] = 1;
            $maindly['delivery_logi_number'] = 1;
            //更新子运单号
            if ($this->__formatParams['bill_logi_weight']) {
                $this->_save_bill_logi_weight($this->__formatParams['bill_logi_weight'], $maindly);
                $num                             = count($this->__formatParams['bill_logi_weight']);
                $maindly['delivery_logi_number'] = $maindly['logi_number'] = $num + 1;

            }

            $affect_row = $this->__dlyObj->update($maindly, array('delivery_id' => $maindly['delivery_id'], 'process' => 'false'));
            if (!is_numeric($affect_row) || $affect_row <= 0) {
                $msg = '发货单发货状态更新失败!';
                return false;
            }
            $this->__operationLogObj->write_log('delivery_process@ome', $this->__currDlyId, '发货单发货完成,（发货单号：' . $this->__currDlyBn . '）', '', $opinfo);

        } else {
            $de     = $this->__currDlyInfo['delivery_order'];
            $or     = array_shift($de);
            $ord_id = $or['order_id'];
            
            $frst_info = $orderObj->dump(array('order_id' => $ord_id), 'shop_id, shop_type, order_bn');
            
            //普通发货单才做库存处理、订单发货数量的更新
            if (in_array($this->__currDlyInfo['type'], array('normal'))) {
                //仓库库存处理
                $params['params']    = array_merge($this->__currDlyInfo, array('order_id' => $ord_id));
                $params['node_type'] = 'consignDly';
                $shop = app::get('ome')->model('shop')->db_dump(['shop_id'=>$this->__currDlyInfo['shop_id']], 'delivery_mode');
                if($shop['delivery_mode'] == 'jingxiao') {
                    $processResult       = true;
                } else {
                    $processResult       = $this->__storeManageLib->processBranchStore($params, $err_msg);
                }
                if (!$processResult) {
                    $msg      = $err_msg;
                    $msg_code = 'W30012';
                    return false;
                }
                
                //[行锁]先更新订单物流单号,防并发
                //@todo：订单拆分多个发货单,同分同秒发货,导致订单是部分发货状态;
                if ($this->__isThirdParty == true && $this->__formatParams['logi_no']) {
                    $updateSdf = array();
                    $updateSdf['logi_no'] = $this->__formatParams['logi_no'];
                    if($this->__formatParams['logi_id']){
                        $updateSdf['logi_id'] = $this->__formatParams['logi_id'];
                    }
                    
                    $orderObj->update($updateSdf, array('order_id' => $ord_id));
                }
                
                //订单发货数量更新
                $this->__dlyObj->consignOrderItem($this->__currDlyInfo);
            }

            //如果是第三方仓储的并且物流公司有变化，则赋值更新
            if ($this->__isThirdParty == true) {
                if ($this->__formatParams['logi_id']) {
                    $singledly['logi_id'] = $this->__formatParams['logi_id'];
                    $orderdata['logi_id'] = $this->__formatParams['logi_id'];
                }
                if ($this->__formatParams['logi_name']) {
                    $singledly['logi_name'] = $this->__formatParams['logi_name'];
                }

                $singledly['logi_no'] = $this->__formatParams['logi_no'];
                $orderdata['logi_no'] = $this->__formatParams['logi_no'];
            }

            // 更新主发货单
            $singledly['delivery_id']          = $this->__currDlyId;
            $singledly['delivery_bn']          = $this->__currDlyBn;
            $singledly['process']              = 'true';
            $singledly['status']               = 'succ';
            $singledly['weight']               = $this->__formatParams['weight'];
            $singledly['delivery_time']        = $this->__formatParams['delivery_time'];
            $singledly['delivery_cost_actual'] = $this->__formatParams['delivery_cost_actual'];

            //打印状态
            $singledly['expre_status'] = 'true';
            $singledly['deliv_status'] = 'true';
            $singledly['stock_status'] = 'true';
            $singledly['stock_status'] = 1;
            $singledly['delivery_logi_number'] = 1;
            //更新子运单号
            if ($this->__formatParams['bill_logi_weight']) {
                $this->_save_bill_logi_weight($this->__formatParams['bill_logi_weight'], $singledly);
                $num                               = count($this->__formatParams['bill_logi_weight']);
                $singledly['delivery_logi_number'] = $singledly['logi_number'] = $num + 1;

            }

            $affect_row = $this->__dlyObj->update($singledly, array('delivery_id' => $singledly['delivery_id'], 'process' => 'false'));
            if (!is_numeric($affect_row) || $affect_row <= 0) {
                $msg = '发货单发货状态更新失败!';
                return false;
            }
            $this->__operationLogObj->write_log('delivery_process@ome', $this->__currDlyId, '发货单发货完成,（发货单号：' . $this->__currDlyBn . '）', '', $opinfo);

            $item_num = $this->__dlyObj->countOrderSendNumber($ord_id);
            if ($item_num == 0) {
                //已发货
                //订单全部发货，清除订单级预占流水
                //检测仓库是否管控库存，如果是需要释放订单冻结
                $isCtrlStore = kernel::single('ome_branch')->getBranchCtrlStore($this->__currDlyInfo['branch_id']);
                if($isCtrlStore === false){
                    $orderObj->unfreez($ord_id,false);
                }
                if(isset($shop) && $shop['delivery_mode'] == 'jingxiao') {            
                    $freeResult = $basicMStockFreezeLib->delOrderFreeze($ord_id);
                    if (!$freeResult) {
                        $msg      = '删除流水冻结失败!';
                        $msg_code = 'W30012';
                        return false;
                    }
                }
                $orderInfo = $orderObj->db_dump(['order_id'=>$ord_id]);
                // 检测京东订单是否有微信支付先用后付的单据
                $use_before_payed = false;
                if ($orderInfo['shop_type'] == '360buy') {
                    $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($orderInfo['order_id']);
                    $labelCode = array_column($labelCode, 'label_code');
                    $use_before_payed = kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode);
                }
                if (($this->__currDlyInfo['is_cod'] == 'false' && !$use_before_payed) || ($this->__currDlyInfo['is_cod'] == 'false' && $use_before_payed && $orderInfo['pay_status'] == '1') || ($this->__currDlyInfo['is_cod'] == 'true' && $orderInfo['pay_status'] == '1')) {
                    $orderdata['status'] = 'finish';
                }
                $orderdata['archive']     = 1; //订单归档
                $orderdata['ship_status'] = '1';
                $orderdata['delivery_time'] = time();
                $affect_order             = $orderObj->update($orderdata, array('order_id' => $ord_id)); //更新订单发货状态
            } else {
                //部分发货
                $orderdata['ship_status'] = '2';
                $orderdata['delivery_time'] = time();
                $affect_order             = $orderObj->update($orderdata, array('order_id' => $ord_id)); //更新订单发货状态
            }

            if (!is_numeric($affect_order) || $affect_order <= 0) {
                $msg_code = 'W30012';
                $msg      = '订单状态更新失败!';
                return false;
            }
            
            // 预约订单状态
            $custom_reserved_status = ($orderdata['ship_status'] == '2' ? '6' : '5');
            $reservationMdl->update(['ship_status'=>$orderdata['ship_status'], 'custom_reserved_status'=>$custom_reserved_status], array('order_id'=>$ord_id));
            
            //标记当前门店履约订单已发货
            if ($this->__isStoreBranch) {
                kernel::single('ome_o2o_performance_orders')->updateProcessStatus($ord_id, 'consign');
            }

            //[拆单]新增_发货单状态回写记录
            if ($split_seting) {
                $dly_data                  = array();
                $dly_data['order_id']      = $ord_id;
                $dly_data['order_bn']      = $frst_info['order_bn'];
                $dly_data['delivery_id']   = $this->__currDlyId;
                $dly_data['delivery_bn']   = $this->__currDlyBn;
                $dly_data['logi_no']       = isset($singledly['logi_no']) ? $singledly['logi_no'] : $this->__currDlyInfo['logi_no'];
                $dly_data['logi_id']       = isset($singledly['logi_id']) ? $singledly['logi_id'] : $this->__currDlyInfo['logi_id'];
                $dly_data['branch_id']     = $this->__currDlyInfo['branch_id'];
                $dly_data['status']        = $singledly['status']; //发货状态
                $dly_data['shop_id']       = $this->__currDlyInfo['shop_id'];
                $dly_data['delivery_time'] = $this->__formatParams['delivery_time'];
                $dly_data['dateline']      = $this->__formatParams['delivery_time'];
                $dly_data['split_model']   = intval($split_seting['split_model']); //拆单方式
                $dly_data['split_type']    = intval($split_seting['split_type']); //回写方式

                $delivery_sync->save($dly_data);
            }
        }


        // 包裹处理
        if ($this->__inputParams['packages']) {
            $packageMdl = app::get('ome')->model('delivery_package');
            $corpMdl    = app::get('ome')->model('dly_corp');

            $logi_id = $singledly['logi_id'] ? $singledly['logi_id'] : $this->__currDlyInfo['logi_id'];
            $corp = $corpMdl->db_dump($logi_id, 'type');

            $packages = [];
            foreach ($this->__inputParams['packages'] as $package) {
                foreach ($package['items'] as $item) {
                    $packages[] = [
                        'delivery_id'       => $this->__currDlyId,
                        'package_bn'        => $package['package_bn'],
                        'logi_bn'           => $corp['type'],
                        'logi_no'           => $package['logi_no'],
                        'product_id'        => $item['product_id'],
                        'bn'                => $item['bn'],
                        'status'            => 'delivery',
                        'shipping_status'   => '8',
                        'number'            => $item['number'],
                        'create_time'       => time(),
                        'delivery_time'     => $package['delivery_time']
                    ];
                }
            }

            if ($packages) $packageMdl->db->exec(ome_func::get_insert_sql($packageMdl, $packages));
        }


        return true;
    }

    /**
     * 发货完成后生成出入库明细及销售单
     *
     * @param string $msg 错误信息
     * @return void
     **/
    private function _consign_siso(&$msg)
    {

        $soldIoLib = kernel::single('siso_receipt_iostock_sold');
        // 门店订单不统计成本
        if ($this->__isStoreBranch) {
            $soldIoLib->set_io_cost(false);
        }

        $soldSalesLib = kernel::single('siso_receipt_sales_sold');
        
        // if (!$this->__isStoreBranch) {
        
        $iostock_data = [];
        $save_iostock = $soldIoLib->create(array('delivery_id' => $this->__currDlyId), $iostock_data, $msg);
        if ($save_iostock) {
            $save_sales = $soldSalesLib->create(array('delivery_id' => $this->__currDlyId, 'iostock' => $iostock_data), $msg);
            if (!$save_sales) {
                $msg = '销售单生成失败!'.(is_array($msg) ? implode(',', $msg) : $msg);
                return false;
            }
        } else {
            $msg = '出入库明细生成失败!'.(is_array($msg) ? implode(',', $msg) : $msg);
            return false;
        }

        // 发货销售单生成
        $rs = kernel::single('ome_sales_delivery')->process($this->__currDlyId, $msg);
        if (!$rs) {
            return false;
        }

        return true;
    }

    /**
     * 发货以后的后续逻辑处理
     *
     * @return void
     **/
    private function _after_consign()
    {
        $wdMdl = app::get('console')->model('wms_delivery');
        $wdRow = $wdMdl->db_dump(['delivery_id'=>$this->__currDlyId, 'delivery_status'=>'2'], 'id');
        if($wdRow) {
            $wdRs = $wdMdl->update(['delivery_status'=>'3'], ['id'=>$wdRow['id'], 'delivery_status'=>'2']);
            if(!is_bool($wdRs)) {
                app::get('ome')->model('operation_log')->write_log('wms_delivery@console',$wdRow['id'], '发货单完成');
            }
        }

        //平台自发仓加判断不处理
        if ($this->__currDlyInfo['bool_type'] & ome_delivery_bool_type::__PLATFORM_CODE) {
            return array();
        }

        if($this->__currDlyInfo['delivery'] == 'SHIPED'){
            return array();
        }
    
        //撤销发货单成功后更新退款未退货报表退货单据状态
        kernel::single('ome_refund_noreturn')->deliveryRefundNoreturn($this->__currDlyId);
        
        //唯一码出库处理
        $this->_after_consign_serial();

        //保质期批次出库处理
        $this->_after_consign_storagelife();

        //电子面单回传
        $this->_after_consign_logisticsmanager();

        //京东仓储扩展信息的保存
        $this->_after_consign_extend();

        //短信
        $this->_after_consign_sms();

        //绩效
        $this->_after_consign_tgkpi();

        $channel_type = kernel::single('logisticsmanager_service_waybill')->getChannelType($this->__currDlyInfo['logi_id']);

        //华强宝 需判断 非银联 才发起 华强宝订阅
        if (!in_array($channel_type, array('unionpay'))) {
            $this->_after_consign_hqepay();
        }
        
        // 补寄发货处理
        $this->_after_consign_reshipping();

        //淘宝全链路
        $this->_after_consign_tmc();

        //状态回写
        $this->_after_consign_shop();

        //电子发票开蓝票处理
        $this->_after_einvoice_create();

        //换货订单回写
        $this->_after_consign_change_order();

        //订单全额退款包裹拦截
        kernel::single('console_reship')->orderRefundToLJRK($this->__currDlyId);
        
        // order_ids
        $orderIds = array_column($this->__currDlyInfo['delivery_order'], 'order_id');
        
        //更新经销商订单(检查是否安装dealer应用)
        if($this->__currDlyInfo['betc_id'] && app::get('dealer')->is_installed()){
            $jxOrderLib = kernel::single('dealer_platform_orders');
            
            //update
            $jxOrderLib->updateDlyOrders($orderIds);
        }
        
        // [更新]预约订单的发货状态
        kernel::single('ome_order_reservation')->updateReservationByOrderIds($orderIds);
        
        // 发货单发货完成后，service扩展触发其他服务
        foreach(kernel::servicelist('ome.service.delivery.after.consign') as $service) {
            if(method_exists($service,'after_consign')) {
                $service->after_consign($this->__currDlyId);
            }
        }
    }

    //自动开蓝票处理
    private function _after_einvoice_create()
    {
        if (!empty($this->__currDlyInfo)) {

            kernel::single('invoice_process')->after_consign_autoinvoice($this->__currDlyId);

        }
    }

    /**
     * 唯一码出库处理
     *
     * @return void
     * @author
     **/
    private function _after_consign_serial()
    {
        $msg = '';
        if ($this->__formatParams['out_serial']) {
            $params = array('delivery_id' => $this->__currDlyId, 'delivery_bn' => $this->__currDlyBn, 'branch_id' => $this->__currDlyInfo['branch_id'], 'out_serial' => $this->__formatParams['out_serial']);
            kernel::single('ome_receipt_dlyitemsserial')->consign($params, $msg);
        }
        return true;
    }

    /**
     * 保质期批次出库处理
     *
     * @return void
     * @author
     **/
    private function _after_consign_storagelife()
    {
        $msg = '';
        if ($this->__formatParams['out_storagelife']) {
            $params = array('delivery_id' => $this->__currDlyId, 'delivery_bn' => $this->__currDlyBn, 'branch_id' => $this->__currDlyInfo['branch_id'], 'out_storagelife' => $this->__formatParams['out_storagelife']);
            kernel::single('ome_receipt_dlyitemsstoragelife')->consign($params, $msg);
        }
        return true;
    }

    /**
     * 电子面单回传
     *
     * @return void
     * @author
     **/
    private function _after_consign_logisticsmanager()
    {
        //对EMS直联电子面单作处理（以及京东360buy）(京东先回写运单号）
        $channel_type = $this->__dlyObj->getChannelType($this->__currDlyInfo['logi_id']);
        if ($channel_type && in_array($channel_type, array('360buy', 'taobao', 'ems', 'wxshipin'))) {
            kernel::single('ome_event_trigger_logistics_electron')->delivery($this->__currDlyId);
        }
        return true;
    }

    /**
     * 京东仓储扩展信息的保存
     *
     * @return void
     * @author
     **/
    private function _after_consign_extend()
    {
        //确认有没外部发货单号如果有更新，目前只针对坑爹的京东仓储
        if ($this->__inputParams['out_delivery_bn']) {
            $oDelivery_ext = app::get('console')->model('delivery_extension');
            $delivery_ext  = $oDelivery_ext->dump(array('delivery_bn' => $this->__currDlyBn), 'original_delivery_bn');
            if (!$delivery_ext) {
                $ext_data = array(
                    'delivery_bn'          => $this->__currDlyBn,
                    'original_delivery_bn' => $this->__inputParams['out_delivery_bn'],
                );
                $oDelivery_ext->create($ext_data);
            }
        }
        return true;
    }

    /**
     * 状态回写
     *
     * @return void
     * @author
     **/
    private function _after_consign_shop()
    {
        //调用发货相关api，比如订单的发货状态，库存的回写，发货单的回写
        $this->__dlyObj->call_delivery_api($this->__currDlyId, false);
        return true;
    }

    /**
     * 短信
     *
     * @return void
     * @author
     **/
    private function _after_consign_sms()
    {
        $sms_error_msg = '';
        $error_msg = '';
        
        if (defined('APP_TOKEN') && defined('APP_SOURCE')) {
            /* 门店发货完成后,短信提醒用户收货 */
            if ($this->__isStoreBranch) {
                $event_type = ($this->__inputParams['dly_corp_type'] == 'o2o_pickup' ? 'o2opickup' : 'o2oship');

                //门店自提单发送短信
                $sendArr = array(
                    'event_type'        => $event_type,
                    'ship_mobile'       => $this->__inputParams['ship_mobile'],
                    'ship_name'         => $this->__inputParams['ship_name'],
                    'pickup_bn'         => $this->__inputParams['pickup_bn'], #提货单
                    'pickup_code'       => $this->__inputParams['pickup_code'], #校验码
                    'store_name'        => $this->__inputParams['store_name'], #门店名称
                    'store_addr'        => $this->__inputParams['store_addr'], #门店地址
                    'store_contact_tel' => $this->__inputParams['store_contact_tel'], #门店联系方式
                );

                if ($event_type == 'o2opickup') {
                    $log_str = '请到(' . $this->__inputParams['store_name'] . ')门店自提,';
                } else {
                    unset($sendArr['store_addr']); //不需要门店地址
                    $log_str = '请耐心等待门店(' . $this->__inputParams['store_name'] . ')为您配送,';
                }

                $sendSms = kernel::single('taoexlib_sms')->sendSms($sendArr, $sms_error_msg);
                if (!$sendSms) {
                    $log_str = $log_str . "短信发送失败(" . $sms_error_msg . ");";
                } else {
                    $log_str = $log_str . '短信发送成功;';
                }

                $this->__operationLogObj->write_log('delivery_process@ome', $this->__currDlyId, $log_str);
            } else {
                kernel::single('taoexlib_sms')->sendSms(array('event_type' => 'delivery', 'delivery_id' => $this->__currDlyId), $error_msg);
            }
        }

        return true;
    }

    /**
     * 绩效
     *
     * @return void
     * @author
     **/
    private function _after_consign_tgkpi()
    {
        if (!app::get('tgkpi')->is_installed()) {
            return;
        }

        $sql = 'select delivery_id from sdb_tgkpi_pick WHERE delivery_id =' . $this->__currDlyId;
        $row = $this->__dlyObj->db->selectrow($sql);
        if ($row) {
            $opInfo = kernel::single('ome_func')->getDesktopUser();
            $sql    = sprintf('UPDATE `sdb_tgkpi_pick` SET `pick_status`="deliveryed",`op_name`="%s" WHERE delivery_id="%s"', $opInfo['op_name'], $this->__currDlyId);
            kernel::database()->exec($sql);
        }
        return true;
    }

    /**
     * 华强宝
     *
     * @return void
     * @author
     **/
    private function _after_consign_hqepay()
    {

        #订阅物流信息
        kernel::single('ome_event_trigger_shop_hqepay')->hqepay_pub($this->__currDlyId);
        return true;
    }
    
    /**
     * 补寄发货处理
     *
     * @return void
     * @author
     **/
    private function _after_consign_reshipping()
    {
        // 补寄发货处理
        $reshippingTrigger = kernel::single('ome_event_trigger_reshipping_delivery');
        $reshippingTrigger->afterDelivery($this->__currDlyId);
        return true;
    }

    /**
     * 淘宝全链路
     *
     * @return void
     * @author
     **/
    private function _after_consign_tmc()
    {
        $this->__dlyObj->sendMessageProduce($this->__currDlyId, 'dispatch'); #淘宝全链路 已打包，已称重，已出库
        return true;
    }

    /**
     *
     * 发货打印的时候，检查相关状态
     * @param string $msg 错误信息
     */
    private function _checkStatusWhenPrint(&$msg)
    {

        // 发货单状态判断
        if (in_array($this->__currDlyInfo['status'], array('succ', 'back', 'stop', 'cancel', 'failed', 'timeout', 'return_back'))) {
            $msg = '发货单状态异常:' . $this->__currDlyInfo['status'] . '!';
            return false;
        }

        return true;
    }

    /**
     * 打印发货单具体处理逻辑
     *
     * @param string $msg 错误信息
     * @return void
     **/
    private function _printDly(&$msg)
    {
        //更新发货单打印状态
        $stock_status = isset($this->__inputParams['stock_status']) ? $this->__inputParams['stock_status'] : 'true';
        $deliv_status = isset($this->__inputParams['deliv_status']) ? $this->__inputParams['deliv_status'] : 'true';
        $expre_status = isset($this->__inputParams['expre_status']) ? $this->__inputParams['expre_status'] : 'true';
        $upData = array('print_status' => 1, 'stock_status' => $stock_status, 'deliv_status' => $deliv_status, 'expre_status' => $expre_status, 'status' => 'progress');
        if($this->__inputParams['logi_no']) {
            $upData['logi_no'] = $this->__inputParams['logi_no'];
        }
        $this->__dlyObj->update($upData, array('delivery_id' => $this->__currDlyId, 'status' => ['ready', 'progress']));

        //更新订单打印状态
        $this->__dlyObj->updateOrderPrintFinish($this->__currDlyId);

        //淘宝全链路
        if ($stock_status == 'true') {
            // $flag[] = 5;
            $flag[] = 'print_stock';
        }

        if ($deliv_status == 'true') {
            // $flag[] = 6;
            $flag[] = 'print_deliv';
        }

        if ($expre_status == 'true') {
            // $flag[] = 7;
            $flag[] = 'print_expre';
        }

        if (isset($flag)) {
            $this->__dlyObj->sendMessageProduce($this->__currDlyId, $flag);
        }

        //请求前端发货单进行更新
        if ($this->__currDlyInfo['status'] == 'ready') {
            kernel::single('ome_event_trigger_shop_delivery')->delivery_process_update($this->__currDlyId);
        }

        return true;
    }

    /**
     *
     * 更新接收发货单已打印
     * @param array $data
     */
    private function setPrint($data)
    {
        $msg_code = '';
        
        //初始化类的对象
        $this->_instanceObj();

        //初始化当前处理发货单的数据
        if (!$this->_initDlyInfo($data, $msg)) {
            return $this->send_error($msg, $msg_code, $data);
        }

        //检查当前发货单对应状态是否可以操作
        if (!$this->_checkStatusWhenPrint($msg)) {
            return $this->send_error($msg, $msg_code, $this->__inputParams);
        }

        //加入事务机制
        kernel::database()->beginTransaction();

        //具体发货单打印的详细处理逻辑
        if (!$this->_printDly($msg)) {
            kernel::database()->rollBack();
            return $this->send_error($msg, $msg_code, $this->__inputParams);
        }

        // 事务提交
        kernel::database()->commit();
        $corpMdl    = app::get('ome')->model('dly_corp');
        $corp = $corpMdl->db_dump($this->__currDlyInfo['logi_id'], 'type');

        //库内作业操作通知
        $sdf = array (
            'logi_no'     => $this->__inputParams['logi_no'],
            'type'        => $corp['type'],
            'delivery_bn' => $this->__currDlyInfo['delivery_bn'],
            'status'      => 'print',
            'orderinfo'   => app::get('ome')->model('orders')->db_dump(['order_id' => array_keys($this->__currDlyInfo['delivery_order'])]),
        );
        kernel::single('erpapi_router_request')->set('shop',$this->__currDlyInfo['shop_id'])->delivery_operationInWarehouse($sdf);
        $rb = app::get('ome')->getConf('ome.delivery.back_node');
        if($rb == 'print') {
            kernel::single('ome_event_trigger_shop_delivery')->delivery_confirm_send($this->__currDlyInfo['delivery_id']);
        }
        return $this->send_succ();
    }

    /**
     * 校验发货单具体处理逻辑
     *
     * @param string $msg 错误信息
     * @return void
     **/
    private function _checkDly(&$msg)
    {

        $dlyItemObj = app::get('ome')->model('delivery_items');

        if ($dlyItemObj->verifyItemsByDeliveryId($this->__currDlyId)) {
            $filter['delivery_id'] = $this->__currDlyId;
            $delivery['verify']    = 'true';

            $affect_dly = $this->__dlyObj->update($delivery, $filter);
            if (!is_numeric($affect_dly) || $affect_dly <= 0) {
                $msg = '发货单校验更新失败!';
                return false;
            }

            if ($this->__isBind) {
                foreach ($this->__currDlyChilds as $i) {
                    $dlyItemObj->verifyItemsByDeliveryId($i);
                }
            }

            #淘宝全链路 已捡货，已验货
            $this->__dlyObj->sendMessageProduce($this->__currDlyId, 'picking');
        } else {
            $msg = '发货单校验更新失败!';
            return false;
        }

        return true;
    }

    /**
     *
     * 更新接收发货单已校验
     * @param array $data
     */
    private function setCheck($data)
    {
        $msg_code = '';
        
        //初始化类的对象
        $this->_instanceObj();

        //初始化当前处理发货单的数据
        if (!$this->_initDlyInfo($data, $msg)) {
            return $this->send_error($msg, $msg_code, $data);
        }

        //检查当前发货单对应状态是否可以操作

        //加入事务机制
        kernel::database()->beginTransaction();

        //具体打回发货单的详细处理逻辑
        if (!$this->_checkDly($msg)) {
            kernel::database()->rollBack();
            return $this->send_error($msg, $msg_code, $this->__inputParams);
        }

        // 事务提交
        kernel::database()->commit();

        $rb = app::get('ome')->getConf('ome.delivery.back_node');
        if($rb == 'check') {
            kernel::single('ome_event_trigger_shop_delivery')->delivery_confirm_send($this->__currDlyInfo['delivery_id']);
        }
        return $this->send_succ();
    }

    /**
     *
     * 更新发货单信息格式化相应参数
     * @param void
     */
    private function _convertUpdateSdf()
    {

        $validKeys = array('logi_id', 'logi_name', 'logi_no', 'delivery_cost_expect', 'delivery_cost_actual', 'weight', 'memo');
        $arrKeys   = array_keys($this->__inputParams);
        foreach ($arrKeys as $arrKey) {
            if (in_array($arrKey, $validKeys)) {
                $this->__formatParams[$arrKey] = $this->__inputParams[$arrKey];
            }
        }

        return true;
    }

    /**
     * 更新发货单详情具体处理逻辑
     *
     * @param string $msg 错误信息
     * @return void
     **/
    private function _updateDly(&$msg)
    {

        //保存发货单变更信息
        $affect_dly = $this->__dlyObj->updateDelivery($this->__formatParams, array('delivery_id' => $this->__currDlyId));
        if (!is_numeric($affect_dly) || $affect_dly <= 0) {
            $msg = '发货单校验更新失败!';
            return false;
        }

        //根据动作类型记录相关日志
        if ($this->__inputParams['action']) {
            switch ($this->__inputParams['action']) {
                case 'updateDetail':
                    $this->__operationLogObj->write_log('delivery_modify@ome', $this->__currDlyId, '修改发货单详情');
                    break;
                case 'addLogiNo':
                    $this->__operationLogObj->write_log('delivery_logi_no@ome', $this->__currDlyId, '录入快递单号:' . $this->__formatParams['logi_no']);
                    break;
            }
        }

        return true;
    }

    /**
     *
     * 更新接收发货单信息变更
     * @param array $data
     */
    private function updateDetail($data)
    {
        $msg_code = '';
        
        //初始化类的对象
        $this->_instanceObj();

        //初始化当前处理发货单的数据
        if (!$this->_initDlyInfo($data, $msg)) {
            return $this->send_error($msg, $msg_code, $data);
        }

        //检查当前发货单对应状态是否可以操作

        //组织参数
        $this->_convertUpdateSdf();

        //加入事务机制
        kernel::database()->beginTransaction();

        //具体更新发货单的详细处理逻辑
        if (!$this->_updateDly($msg)) {
            kernel::database()->rollBack();
            return $this->send_error($msg, $msg_code, $this->__inputParams);
        }

        // 事务提交
        kernel::database()->commit();

        return $this->send_succ();
    }

    /**
     *
     * 打回发货的时候，检查相关状态
     * @param string $msg 错误信息
     */
    private function _checkStatusWhenReback(&$msg)
    {

        if ($this->__currDlyInfo['status'] == 'back') {
            $msg = '该发货单已经被打回，无法继续操作!';
            return false;
        }

        if ($this->__currDlyInfo['delivery_logi_number'] > 0) {
            $msg = '该发货单已部分发货，无法继续操作!';
            return false;
        }

        if ($this->__currDlyInfo['pause'] == 'true') {
            $msg = '该发货单已暂停，无法继续操作!';
            return false;
        }

        if ($this->__currDlyInfo['process'] == 'true') {
            $msg = '该发货单已经发货，无法继续操作!';
            return false;
        }

        return true;
    }

    /**
     * 打回发货单具体处理逻辑
     *
     * @param string $msg 错误信息
     * @return void
     **/
    private function _rebackDly(&$msg)
    {
        $branchPrdObj = app::get('ome')->model('branch_product');
        $orderObj     = app::get('ome')->model('orders');
    
        $err_msg = '';
        $tmp['memo']           = $this->__isStoreBranch ? '门店拒绝：' . $this->__inputParams['memo'] : $this->__inputParams['memo'];
        $tmp['status']         = 'back';
        $tmp['logi_no']        = null;
        $filter['delivery_id'] = $this->__currDlyId;

        $affect_dly = $this->__dlyObj->update($tmp, $filter);
        if (!is_numeric($affect_dly) || $affect_dly <= 0) {
            $msg = '发货单状态更新失败!';
            return false;
        }
        
        $log_prefix = ($tmp['memo'] ? $tmp['memo'] : '发货单打回');
        $logi_info = $this->__currDlyInfo['logi_no'] ? ',物流单号' . $this->__currDlyInfo['logi_no'] : '';
        $this->__operationLogObj->write_log('delivery_back@ome', $this->__currDlyId, $log_prefix . $logi_info);

        //门店拒绝添加统计
        if ($this->__isStoreBranch && app::get('o2o')->is_installed()) {
            $reason_data = array(
                'delivery_bn' => $this->__currDlyBn,
                'delivery_id' => $this->__currDlyId,
                'store_bn'    => $this->__inputParams['store_bn'],
                'store_name'  => $this->__inputParams['store_name'],
                'reason_id'   => $this->__inputParams['reason_id'],
                'memo'        => $this->__inputParams['memo'],
                'createtime'  => time(),
            );
            $reasonAnalysisObj = app::get('o2o')->model('store_refuse_analysis');
            $reasonAnalysisObj->save($reason_data);
        }

        //子发货单处理
        if ($this->__isBind) {
            foreach ($this->__currDlyChilds as $i) {
                $tmpdly = array(
                    'status'    => 'cancel',
                    'logi_id'   => null,
                    'logi_name' => '',
                    'logi_no'   => null,
                );

                $affect_dly = $this->__dlyObj->update($tmpdly, array('delivery_id' => $i));
                if (!is_numeric($affect_dly) || $affect_dly <= 0) {
                    $msg = '发货单状态更新失败!';
                    return false;
                }

                $this->__operationLogObj->write_log('delivery_back@ome', $i, $log_prefix);

                $delivery = $this->__dlyObj->dump($i, 'delivery_id,branch_id,shop_id', array('delivery_items' => array('*'), 'delivery_order' => array('*')));

                $de     = $delivery['delivery_order'];
                $or     = array_shift($de);
                $ord_id = $or['order_id'];

                //仓库库存处理
                $params['params']    = array_merge($delivery, array('order_id' => $ord_id));
                $params['node_type'] = 'cancelDly';
                $processResult       = $this->__storeManageLib->processBranchStore($params, $err_msg);
                
                if (!$processResult) {
                    $msg = $err_msg;
                    return false;
                }
                kernel::single('ome_order_object_splitnum')->backDeliverySplitNum($i);
            }
        } else {
            $de     = $this->__currDlyInfo['delivery_order'];
            $or     = array_shift($de);
            $ord_id = $or['order_id'];

            //仓库库存处理
            $params['params']    = array_merge($this->__currDlyInfo, array('order_id' => $ord_id));
            $params['node_type'] = 'cancelDly';
            $processResult       = $this->__storeManageLib->processBranchStore($params, $err_msg);
            if (!$processResult) {
                $msg = $err_msg;
                return false;
            }
            kernel::single('ome_order_object_splitnum')->backDeliverySplitNum($this->__currDlyId);
        }

        //单个发货单的对应订单号
        $validOrderIdsForDelete = array(); // 收集pay_status != 5的订单ID，用于后续删除明细
        foreach ($this->__currDlyInfo['delivery_order'] as $dly_order) {
            $orderInfo = $orderObj->dump($dly_order['order_id'], 'order_id,order_bn,order_combine_idx,order_combine_hash,pay_status,ship_status,payed,process_status');
            $pay_status = $orderInfo['pay_status'];
            $memo       = '';
            if ($pay_status == '5' && $orderInfo['ship_status'] == '0') {
                $memo .= '全额退款订单取消';
                app::get('ome')->model('refund_apply')->check_iscancel($dly_order['order_id'], $memo, false);
            } else {
                $memo .= '更新订单状态(payed:'. $orderInfo['payed'] .',pay_status:'. $pay_status .',ship_status:'. $orderInfo['ship_status'] .')';
                
                //发货单打回，更新订单状态
                kernel::single('ome_order')->resumeOrdStatus($orderInfo);
                
                // 收集pay_status != 5的订单ID
                $validOrderIdsForDelete[] = $dly_order['order_id'];
            }
            
            $this->__operationLogObj->write_log('order_back@ome', $dly_order['order_id'], '发货单' . $this->__currDlyBn . $logi_info . '打回+' . '备注:' . $tmp['memo'] . $memo);

            //标记当前门店履约订单已拒绝
            if ($this->__isStoreBranch) {
                kernel::single('ome_o2o_performance_orders')->updateProcessStatus($dly_order['order_id'], 'refuse');
            }
        }

        // 处理发货单关联的订单明细：订单主结构pay_status != 5时，删除明细并扣除金额
        if (!empty($validOrderIdsForDelete)) {
            $deliveryItemsDetail = app::get('ome')->model('delivery_items_detail')->getList('order_obj_id', 
                array('delivery_id' => $this->__currDlyId, 'order_id' => $validOrderIdsForDelete));
            
            if (!empty($deliveryItemsDetail)) {
                $orderObjIds = array_unique(array_column($deliveryItemsDetail, 'order_obj_id'));
                
                // 批量查询符合条件的order_objects（pay_status=5且delete=false）
                $orderObjectsObj = app::get('ome')->model('order_objects');
                $orderObjects = $orderObjectsObj->getList('obj_id', 
                    array('obj_id' => $orderObjIds, 'order_id' => $validOrderIdsForDelete, 'pay_status' => '5', 'delete' => 'false'));
                
                if (!empty($orderObjects)) {
                    $ordersObj = app::get('ome')->model('orders');
                    
                    // 对每个符合条件的obj_id调用封装的方法
                    foreach ($orderObjects as $obj) {
                        $ordersObj->deleteRefundObject($obj['obj_id'], '发货单撤销');
                    }
                }
            }
        }

        $this->__dlyObj->updateOrderPrintFinish($this->__currDlyId, 1);

        return true;
    }

    /**
     *
     * 撤销发货单
     * @param array $data
     */
    private function rebackDly($data)
    {
        $msg_code = '';
        
        //初始化类的对象
        $this->_instanceObj();

        //初始化当前处理发货单的数据
        if (!$this->_initDlyInfo($data, $msg)) {
            return $this->send_error($msg, $msg_code, $data);
        }

        //检查当前发货单对应状态是否可以操作
        if (!$this->_checkStatusWhenReback($msg)) {
            return $this->send_error($msg, $msg_code, $this->__inputParams);
        }

        //加入事务机制
        $transaction = kernel::database()->beginTransaction();


        //具体打回发货单的详细处理逻辑
        if (!$this->_rebackDly($msg)) {

            kernel::database()->rollBack();

            return $this->send_error($msg, $msg_code, $this->__inputParams);
        }

        // 事务提交
        kernel::database()->commit($transaction);
        // 发货单取消后的service扩展点
        foreach(kernel::servicelist('ome.service.delivery.cancel.after') as $object) {
            if(method_exists($object, 'cancel_delivery_after')) {
                $object->cancel_delivery_after($this->__currDlyId);
            }
        }
        return $this->send_succ();
    }

    /**
     *
     * 确认接单的时候，检查相关状态
     * @param string $msg 错误信息
     */
    private function _checkStatusWhenConfirm(&$msg)
    {
        return true;
    }

    /**
     *
     * 确认接单详细处理逻辑
     * @param array $data
     */
    public function _confirmDly($data)
    {
        $filter['delivery_id'] = $this->__currDlyId;
        $filter['status']      = ['ready', 'progress'];
        $delivery['status']    = 'progress';

        //更新发货单为处理中状态，说明门店已接单
        $affect_dly = $this->__dlyObj->update($delivery, $filter);
        if (is_numeric($affect_dly) || $affect_dly > 0) {
            //记录日志
            $this->__operationLogObj->write_log('delivery_modify@ome', $this->__currDlyId, $this->__inputParams['memo']);

            //标记当前门店履约订单已接单
            if ($this->__isStoreBranch) {
                $dly_order = $this->__currDlyInfo['delivery_order'];
                $order_arr = array_shift($dly_order);
                kernel::single('ome_o2o_performance_orders')->updateProcessStatus($order_arr['order_id'], 'accept');
            }
        }

        return true;
    }

    /**
     *
     * 确认接单
     * @param array $data
     */
    private function confirm($data)
    {
        $msg_code = '';
        $msg = '';
        
        //初始化类的对象
        $this->_instanceObj();

        //初始化当前处理发货单的数据
        if (!$this->_initDlyInfo($data, $msg)) {
            return $this->send_error($msg, $msg_code, $data);
        }

        //检查当前发货单对应状态是否可以操作
        if (!$this->_checkStatusWhenConfirm($msg)) {
            return $this->send_error($msg, $msg_code, $this->__inputParams);
        }

        //加入事务机制
        kernel::database()->beginTransaction();

        //具体确认接单的详细处理逻辑
        if (!$this->_confirmDly($msg)) {
            kernel::database()->rollBack();
            return $this->send_error($msg, $msg_code, $this->__inputParams);
        }

        // 事务提交
        kernel::database()->commit();

        return $this->send_succ();
    }

    /**
     *
     * 发货单签收的时候，检查相关状态
     * @param string $msg 错误信息
     */
    private function _checkStatusWhenSign($data, &$msg=null)
    {
        //WMS仓储类型
        $sql = "SELECT a.branch_id,b.channel_id,b.node_type FROM sdb_ome_branch AS a LEFT JOIN sdb_channel_channel AS b ON a.wms_id=b.channel_id ";
        $sql .= " WHERE a.branch_id=". $this->__currDlyInfo['branch_id'] ." AND b.channel_type='wms'";
        $row = $this->__dlyObj->db->selectrow($sql);
        if($row){
            $this->__currDlyInfo['wms_type'] = $row['node_type'];
        }

        //[WMS仓储类型]京东一件代发
        if($this->__currDlyInfo['wms_type'] == 'yjdf'){
            $packageObj = app::get('ome')->model('delivery_package');

            $packageInfo = $packageObj->dump(array('delivery_id'=>$this->__currDlyId, 'package_bn'=>$data['oid']), 'package_id,package_bn');
            if(empty($packageInfo)){
                $msg = '没有找到包裹单号：'.$data['oid'];
                return false;
            }

            $this->__currDlyInfo['package_bn'] = $packageInfo['package_bn'];
        }

        return true;
    }

    /**
     *
     * 发货单签收详细处理逻辑
     * @param array $data
     */
    public function _signDly($data)
    {
        //$saveData = array('logi_status'=>3);
        $saveData = array('logistics_status'=>'499','logi_status'=>3);
        $error_msg = '';
        
       
        //签收时间
        $sign_time = ($data['sign_time'] ? strtotime($data['sign_time']) : time());
        if($sign_time){
            $saveData['sign_time'] = $sign_time;
        }
        
        //标记发货单已签收
        $this->__dlyObj->update($saveData, array('delivery_id' => $this->__currDlyId));

        //日志记录
        $this->__inputParams['memo'] = ($this->__inputParams['memo'] ? $this->__inputParams['memo'] : '发货单已签收');
        $this->__operationLogObj->write_log('delivery_modify@ome', $this->__currDlyId, $this->__inputParams['memo']);

        //标记当前门店履约订单已核销签收
        if ($this->__isStoreBranch) {
            $dly_order = $this->__currDlyInfo['delivery_order'];
            $order_arr = array_shift($dly_order);
            kernel::single('ome_o2o_performance_orders')->updateProcessStatus($order_arr['order_id'], 'sign');
        }

        //自提、配送签收发送短消息
        if (kernel::service('message_setting') && defined('APP_TOKEN') && defined('APP_SOURCE')) {
            kernel::single('taoexlib_sms')->sendSms(array('event_type' => 'received', 'delivery_id' => $this->__currDlyId), $error_msg);
        }

        //只有已签收的货到付款单才自动支付
        if ($this->__currDlyInfo['is_cod'] == 'true' && 'false' != app::get('ome')->getConf('ome.codorder.autopay')) {
            kernel::single('ome_order')->codAutoPay($this->__currDlyId);
        }

        //订单签收收自动开蓝票埋点
        $funcObj       = kernel::single('invoice_func');
        $orderDelivery = app::get('ome')->model('delivery_order')->getList('*', array('delivery_id' => $this->__currDlyId));
        foreach ($orderDelivery as $od) {
            $funcObj->do_einvoice_bill($od['order_id'], "2");
        }

        //[WMS仓储类型]京东一件代发
        if($this->__currDlyInfo['wms_type'] == 'yjdf' && $this->__currDlyInfo['package_bn']){
            $packageObj = app::get('ome')->model('delivery_package');

            $saveData = array('shipping_status'=>'18');

            //签收时间
            if($sign_time){
                $saveData['sign_time'] = $sign_time;
            }

            $packageObj->update($saveData, array('package_bn'=>$this->__currDlyInfo['package_bn']));
        }
        if($sign_time){
           
            foreach($this->__currDlyInfo['delivery_order'] as $dly_order) {
                $order_id = $dly_order['order_id'];
                $orderObj = app::get('ome')->model('orders');
                $orderInfo = $orderObj->dump($order_id, 'order_id,createway');
                if(in_array($orderInfo['createway'], ['local'])) {
                    $orderObj->update(array('end_time' => $sign_time), array('order_id' => $order_id));

                }
            }
        }
        
        return true;
    }

    /**
     *
     * 发货单签收
     * @param array $data
     */
    private function sign($data)
    {
        $msg_code = '';
        
        //初始化类的对象
        $this->_instanceObj();

        //初始化当前处理发货单的数据
        if (!$this->_initDlyInfo($data, $msg)) {
            return $this->send_error($msg, $msg_code, $data);
        }

        //检查当前发货单对应状态是否可以操作
        if (!$this->_checkStatusWhenSign($data, $msg)) {
            return $this->send_error($msg, $msg_code, $this->__inputParams);
        }

        //加入事务机制
        kernel::database()->beginTransaction();

        //具体发货单签收的详细处理逻辑
        if (!$this->_signDly($data)) {

            kernel::database()->rollBack();

            return $this->send_error($msg, $msg_code, $this->__inputParams);
        }

        // 事务提交
        kernel::database()->commit();

        // 发货签收后触发服务
        foreach(kernel::servicelist('ome.service.delivery.sign.after') as $service) {
            if(method_exists($service,'after_sign')) {
                $payload = [];
                if (isset($data['sign_time'])) {
                    $payload['sign_time'] = $data['sign_time'];
                }
                
                $service->after_sign($this->__currDlyId, $payload);
            }
        }
        
        
        return $this->send_succ();
    }

    private function _save_bill_logi_weight($bill_logi_weight, $delivery)
    {
        $dliBill = app::get('ome')->model('delivery_bill');

        foreach ($bill_logi_weight as $k => $val) {
            $bill                  = array();
            $bill['status']        = '1';
            $bill['logi_no']       = $k;
            $bill['weight']        = $val;
            $bill['delivery_id']   = $delivery['delivery_id'];
            $bill['delivery_bn']   = $delivery['delivery_bn'];
            $bill['create_time']   = $this->__formatParams['delivery_time'];
            $bill['delivery_time'] = $this->__formatParams['delivery_time'];

            $dliBill->db_save($bill);

        }
        return true;

    }

    /**
     * 换货订单发货回写
     *
     */
    private function _after_consign_change_order()
    {

        foreach ($this->__currDlyInfo['delivery_order'] as $v_o) {

            kernel::single('ome_service_aftersale')->exchange_consigngoods($v_o['order_id']);

        }
    }

    /**
     * 追回物流包裹
     *
     * @param unknown $data
     * @return multitype:string unknown |multitype:string NULL
     */
    private function returnBackDly($data)
    {
        $msg_code = '';

        //初始化类的对象
        $this->_instanceObj();

        //初始化当前处理发货单的数据
        if (!$this->_initDlyInfo($data, $msg)) {
            return $this->send_error($msg, $msg_code, $data);
        }

        //检查当前发货单对应状态是否可以操作
        if (in_array($this->__currDlyInfo['status'], array('succ', 'return_back')))
        {
            $error_msg = '该发货单不是已发货或打回状态，无法继续操作!';
            $this->send_error($error_msg, $msg_code, $this->__inputParams);
        }

        //加入事务机制
        kernel::database()->beginTransaction();

        //更新发货单追回状态
        $affect_row = $this->__dlyObj->update(array('status'=>'return_back'), array('delivery_id'=>$this->__currDlyId));
        if (!is_numeric($affect_row) || $affect_row <= 0)
        {
            kernel::database()->rollBack();
            $msg = '发货单发货状态更新失败!';
            return false;
        }

        $this->__operationLogObj->write_log('delivery_process@ome', $this->__currDlyId, '发货单包裹追回成功');

        //事务提交
        kernel::database()->commit();

        //[兼容]追回包裹成功,自动完成退货单
        $order_ids = array();
        foreach ($this->__currDlyInfo['delivery_order'] as $dlyOrder)
        {
            $order_id = $dlyOrder['order_id'];

            $order_ids[$order_id] = $order_id;
        }

        $reshipObj = app::get('ome')->model('reship');
        $reshipList = $reshipObj->getList('reship_id,reship_bn', array('order_id'=>$order_ids, 'is_check'=>array('0', '1')));
        if($reshipList)
        {
            $queueObj = app::get('base')->model('queue');

            foreach ($reshipList as $key => $val)
            {
                //放入queue队列中执行
                $queueData = array(
                        'queue_title' => '退货单号：'. $val['reship_bn'] .'追回包裹成功,自动完成退货单',
                        'start_time' => time(),
                        'params' => array(
                                'sdfdata' => $val,
                                'app' => 'oms',
                                'mdl' => 'reship',
                        ),
                        'worker'=>'ome_reship.autoCompleteReship',
                );
                $queueObj->save($queueData);
            }
        }

        return $this->send_succ();
    }
    
    /**
     * 仓库作业返回异常状态
     * @param $data
     * @return array|bool
     * @author db
     * @date 2024-01-19 3:30 下午
     */
    private function abnormal($data)
    {
        $msg_code = '';
        
        //初始化类的对象
        $this->_instanceObj();
        
        //初始化当前处理发货单的数据
        if (!$this->_initDlyInfo($data, $msg)) {
            return $this->send_error($msg, $msg_code, $data);
        }
        
        //更新仓库异常作业状态
        $wms_msg = htmlspecialchars($data['operate_info'], ENT_QUOTES, 'UTF-8');
        $affect_row = $this->__dlyObj->update(array('wms_status' => '5', 'wms_msg' => $wms_msg), array('delivery_id' => $this->__currDlyId));
        if (!is_numeric($affect_row)) {
            $msg = '发货单仓库作业状态更新失败!';
            $this->__operationLogObj->write_log('delivery_process@ome', $this->__currDlyId, '发货单仓库作业返回异常状态');
        }
        
        return $this->send_succ();
    }
}
