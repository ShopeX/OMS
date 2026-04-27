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
 * 更新订单状态(所有关于订单状态的更改的公用方法)
 * @author wangyunkai
 * @version 0.1 b
 */
class ome_order {

    /**
    * 订单编辑 iframe
    * @access public
    * @param Number $order_id 订单ID
    * @param Bool $is_request 是否发起请求
    * @param Array $ext 扩展参数
    * @return Array
    */
    public function update_iframe($order_id,$is_request=true,$ext=array()){
        $instance = kernel::service('service.order');
        return $instance->update_iframe($order_id,$is_request,$ext);
    }
    
    /**
   * 更新订单同步状态
   * @access public
   * @param number $order_id 订单ID
   * @param String $sync_status 编辑同步状态
   * @return json
   */
   public function set_sync_status($order_id,$sync_status=''){
        if (!empty($order_id) && in_array($sync_status,array('fail','success'))){
           $oOrder_sync = app::get('ome')->model('order_sync_status');
           $sdf = array(
               'order_id' => $order_id,
               'sync_status' => $sync_status == 'success' ? '2' : '1',
           );
           return $oOrder_sync->save($sdf);
       }
       return false;
    }
    
    /**
    * 订单快照 （编辑前 订单存留）
    * @access public
    * @param Number $order_id 订单ID
    * @return Array
    */
    public function order_photo($order_id){
        $oOrder = app::get('ome')->model('orders');
        $opObj = app::get('ome')->model('order_pmt');
        $paymentsObj = app::get('ome')->model('payments');
        $memObj = app::get('ome')->model('members');
        $data = $oOrder->dump($order_id,"*",array("order_objects"=>array("*",array("order_items"=>array("*")))));
        $data['pmt'] = $opObj->getList('*',array('order_id'=>$order_id));//订单优惠方案
        $data['payments'] = $paymentsObj->getList('*',array('order_id'=>$order_id));//支付单
        $mem_info = $memObj->getList('*',array('member_id'=>$data['member_id']));//会员信息
        $data['mem_info'] = $mem_info[0];
        return $data;
    }

    /**
    * 获取子订单的订单号
    * @access public 
    * @param String $oid 子订单号
    * @return String 订单号
    */
    public function getOrderBnByoid($oid='',$node_id=''){
        if (empty($oid)) return NULL;
        
        if ($this->order_is_exists($oid,$node_id)){
            return $oid; //子订单与订单号相同,直接返回
        }

        $objModel = app::get('ome')->model('order_objects');
        $obj_detail = $objModel->getList('order_id',array('oid'=>$oid),0,1);
        if (isset($obj_detail[0]) && $order_id = $obj_detail[0]['order_id']){
            $oModel = app::get('ome')->model('orders');
            $filter = array('order_id'=>$order_id);
            if ($node_id) {
              $shopObj = kernel::single('ome_shop');
              $shops = $shopObj->getRowByNodeId($node_id);
              $shop_id = $shops['shop_id'];
              $filter['shop_id'] = $shop_id;
            }
            
            $orders = $oModel->getRow($filter,'order_bn');
            return $orders['order_bn'];
        }else{
            return NULL;
        }
    }

    /**
    * 订单号是否存在
    * @access public
    * @param String $order_bn 订单号
    * @param String $node_id 节点ID
    * @return bool
    */
    public function order_is_exists($order_bn='',$node_id=''){
        if (empty($order_bn)) return false;
        
        $filter = array('order_bn'=>$order_bn);

        $oModel = app::get('ome')->model('orders');
        if ($node_id){
            $shopObj = kernel::single('ome_shop');
            $shops = $shopObj->getRowByNodeId($node_id);
            $shop_id = $shops['shop_id'];

            $filter['shop_id'] = $shop_id;
        }
        $orders = $oModel->getRow($filter,'order_id,shop_id');
        if (isset($orders['order_id']) && $orders['order_id']){
            $shop = kernel::single('ome_shop')->getRowByShopId($orders['shop_id']);
            $orders['shop_name'] = $shop['name'];

            return $orders;
        }else{
            return false;
        }
    }

    /**
     * 发送全链路参数
     * @param Int $status 全路径状态
     * @param Int $order_id 订单序号
     * @param String $order_bn 订单号
     * @param String $remark 备注信息
     */
    public function sendMessageProduce($status, $order_id = '', $order_bn = '', $remark = '') {
        // //全链路开关
        // if (!(defined('MESSAGE_PRODUCE') && MESSAGE_PRODUCE)) {
        //     return '';
        // }
        $statusList = array(
            'taobao' => array(
                0 => array('key' => 'X_TO_SYSTEM', 'value' => ''),
                1 => array('key' => 'X_SERVICE_AUDITED', 'value' => ''),
                2 => array('key' => 'X_FINANCE_AUDITED', 'value' => ''),
                3 => array('key' => 'X_ALLOCATION_NOTIFIED', 'value' => ''),
                4 => array('key' => 'X_WAIT_ALLOCATION', 'value' => ''),
                5 => array('key' => 'X_SORT_PRINTED', 'value' => ''),
                6 => array('key' => 'X_SEND_PRINTED', 'value' => ''),
                7 => array('key' => 'X_LOGISTICS_PRINTED', 'value' => ''),
                8 => array('key' => 'X_SORTED', 'value' => ''),
                9 => array('key' => 'X_EXAMINED', 'value' => ''),
                10 => array('key' => 'X_PACKAGED', 'value' => ''),
                11 => array('key' => 'X_WEIGHED', 'value' => ''),
                12 => array('key' => 'X_OUT_WAREHOUSE', 'value' => ''),
            ),
        );
        if (empty($order_id) && empty($order_bn)) {
            return false;
        }
        $ordersModel = app::get('ome')->model('orders');
        $filter = array();
        if ($order_id) {
            $filter['order_id'] = $order_id;
        }
        if ($order_bn) {
            $filter['order_bn'] = $order_bn;
        }

        $result = '';
        $orderInfo = $ordersModel->getList('order_id,order_bn,shop_id,createway', $filter);
        if ($orderInfo) {
            //线上订单
            if ($orderInfo[0]['createway'] == 'matrix') {
                //检查店铺类型是否合法
                $shopModel = app::get('ome')->model('shop');
                $shopInfo = $shopModel->getList('node_type', array('shop_id' => $orderInfo[0]['shop_id']));
                if (in_array($shopInfo[0]['node_type'], array_keys($statusList))) {
                    $tid = $orderInfo[0]['order_bn'];
                    //获得自订单
                    $orderObjectsModel = app::get('ome')->model('order_objects');
                    $orderObjectsInfo = $orderObjectsModel->getList('oid', array('order_id' => $orderInfo[0]['order_id']));
                    $orderIds = '';
                    if ($orderObjectsInfo) {
                        foreach ($orderObjectsInfo as $v) {
                            $orderIds .= strval($v['oid']) . ',';
                        }
                        $orderIds = trim(strval($orderIds), ',');
                    }
                    else {
                        $orderIds = strval($tid);
                    }
                    $statusTitle = $statusList[$shopInfo[0]['node_type']][$status]['key'];
                    $remarkTitle = empty($remark) ? $statusList[$shopInfo[0]['node_type']][$status]['value'] : $remark;
                    $dateTime = date("Y-m-d H:i:s");
                    $params = array(
                        'topic'=>'taobao_jds_TradeTrace', 
                        'tid' => $tid,
                        'order_ids' => $orderIds,
                        'status' => $statusTitle,
                        'action_time' => $dateTime,
                        'remark' => $remarkTitle,
                    );
                    $shop_id = $orderInfo[0]['shop_id'];
                    if ($shop_id) {

                    }
                }
            }
        }
        return $result;
    }

    /**
     * 订单分派
     *
     * @return void
     * @author 
     */
    public function dispatch($order_id, $group_id = 0, $op_id = 0, $zh_source='')
    {
        $orderMdl         = app::get('ome')->model('orders');
        $orderObjMdl      = app::get('ome')->model('order_objects');
        $orderItemMdl     = app::get('ome')->model('order_items');
        $branchMdl        = app::get('ome')->model('branch');
        $branchProductMdl = app::get('ome')->model('branch_product');
        
        // order
        $order = $orderMdl->db_dump($order_id, 'auto_status,abnormal_status');

        if (!$order) return array(false,'订单不存在');

        // 预处理送赠品
        $preProcessLib = new ome_preprocess_entrance();
        $preProcessLib->process($order_id, $errmsg);

        $data = array(
            'auto_status'   => $order['auto_status'],
            'group_id'      => $group_id,
            'op_id'         => $op_id,
            'dt_begin'      => time(),
            'dispatch_time' => time(),
        );

        // 淘宝赠品打标
       if( $order['abnormal_status'] & ome_preprocess_const::__HASGIFT_CODE ){
            $data['auto_status'] = $data['auto_status'] | omeauto_auto_const::__PMTGIFT_CODE;
        }

        // CRM/ERP赠品打标
        $crm_cfg = app::get('crm')->getConf('crm.setting.cfg');
        base_kvstore::instance('crm/set/gift_erp_setting')->fetch('crm_gift_erp_setting',  $erp_cfg);
        
        if(!$crm_cfg || $erp_cfg['radio'] == 'on'){
            if( $order['abnormal_status'] & ome_preprocess_const::__HASCRMGIFT_CODE ){
                $data['auto_status'] = $data['auto_status'] | ome_preprocess_const::__HASCRMGIFT_CODE;
            }
        }

        // 超卖打标
        $order_objects = $orderObjMdl->getList('order_id',array('order_id'=>$order_id,'is_oversold'=>1),0,1);
        if($order_objects){
            $data['auto_status'] = $data['auto_status'] | omeauto_auto_const::__OVERSOLD_CODE;
        }

        // 缺货打标
        $data['auto_status'] = $data['auto_status'] & ~omeauto_auto_const::__STORE_CODE;

        $items = array();
        foreach ($orderItemMdl->getList('product_id,nums',array('order_id'=>$order_id,'delete'=>'false')) as $value) {
            $items[$value['product_id']]['nums'] += $value['nums'];
        }

        $branch_id = array(0);
        foreach ($branchMdl->getList('branch_id',array('skip_permission'=>true,'is_deliv_branch' => 'true', 'owner|noequal'=>'3')) as $value) {
            $branch_id[] = $value['branch_id'];
        }

        $sql = 'SELECT product_id,SUM(store-store_freeze) AS store FROM sdb_ome_branch_product WHERE branch_id IN("'.implode('","', $branch_id).'") AND product_id IN("'.implode('","', array_keys($items)).'") AND store>store_freeze GROUP BY product_id';

        $branch_stores = array();
        foreach (kernel::database()->select($sql) as $value) {
            $branch_stores[$value['product_id']] = $value;
        }

        foreach ($items as $key => $value) {
            if ($value['nums'] > $branch_stores[$key]['store']) {
                $data['auto_status'] = $data['auto_status'] | omeauto_auto_const::__STORE_CODE;
                break;
            }
        }

        $filter = array(
            'order_id'       => $order_id,
            'filter_sql'     => '(group_id is null or group_id=0)',
            'process_status' => array('unconfirmed', 'confirmed', 'splitting', 'is_retrial'),
        );


        $rs = $orderMdl->dispatch($data,$filter,array($order_id));

        return array($rs,$rs?'分派成功':'分派失败');
    }

    public function create_order(&$sdf, &$errmsg = '')
    {
        if (in_array($sdf['status'], ['close', 'dead'])) {
            $sdf['status'] = 'dead';
            $sdf['process_status'] = 'cancel';
            $sdf['archive'] = '1';
        }
        $orderObj = app::get('ome')->model('orders');
        $logObj = app::get('ome')->model('operation_log');
        
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        //判断订单号是否重复
        if($orderObj->dump(array('order_bn'=>$sdf['order_bn'],'shop_id'=>$sdf['shop_id']))){
            $errmsg = $sdf['order_bn'].':订单号重复';
            return false;
        }
        
        //指定仓发货平台
        $assign_shop_types = $this->getAssignBranchByShop();
        if($sdf['is_assign_store']=='true' && in_array($sdf['shop_type'], $assign_shop_types)){
            //选择发货指定仓
            $sdf = $this->assignOutWarehouse($sdf);
        }
        
        //开启事务，防止订单创建失败但是冻结却预占的问题
        $transaction = $orderObj->db->beginTransaction();

        $regionLib = kernel::single('eccommon_regions');
        
        //收货人/发货人地区转换
        $area = $sdf['consignee']['area'];
        $regionLib->region_validate($area);
        $sdf['consignee']['area'] = $area;
        $consigner_area = $sdf['consigner']['area'];
        $regionLib->region_validate($consigner_area);
        $sdf['consigner']['area'] = $consigner_area;

        //计算实付
        $this->create_divide_pay($sdf);
        $sdf['actually_amount'] = $sdf['shipping']['cost_shipping'];
        $sdf['settlement_amount'] = $sdf['shipping']['cost_shipping'];
        //格式化订单明细
        foreach($sdf['order_objects'] as $key=>$object){
            $object['bn'] = trim($object['bn']);
            $sdf['actually_amount'] += $object['actually_amount'];
            $sdf['settlement_amount'] += $object['settlement_amount'];
            if($object['order_items']){
                foreach($object['order_items'] as $k=>$item){
                    $item['bn'] = trim($item['bn']);
                    
                    //货品属性
                    $product_attr = $orderObj->_format_productattr($item['product_attr'], $item['product_id'],$item['original_str']);
                    $item['addon'] = $product_attr;
                    
                    if($item['product_id'] > 0){
                        $basic_material_arr[] = $item['product_id'];
                    }

                    $object['order_items'][$k] = $item;
                }
            }
            
            $sdf['order_objects'][$key] = $object;
        }
        
        //订单可合并标记hash生成
        $combieHashIdxInfo = $this->genOrderCombieHashIdx($sdf);
        if($combieHashIdxInfo){
            $sdf['order_combine_hash'] = $combieHashIdxInfo['combine_hash'];
            $sdf['order_combine_idx'] = $combieHashIdxInfo['combine_idx'];
        }

        //判断基础物料门店是否供货，供货的标记订单为全渠道订单
        if(app::get('o2o')->is_installed()){
            if($basic_material_arr)
            {
                $basicMaterialLib = kernel::single('material_basic_material');
                $is_omnichannel = $basicMaterialLib->isOmnichannelOrder($basic_material_arr);
                if($is_omnichannel){
                    $sdf['omnichannel'] = 1;
                }
            }
        }else{
            unset($basic_material_arr);
        }
        
        //根据店铺取运营组织
        $shopInfo = kernel::single('ome_shop')->getRowByShopId($sdf['shop_id']);
        $sdf['org_id'] = $shopInfo['org_id'];

        !$sdf['splited_num'] && $sdf['splited_num'] = 0;
        
        if(!$orderObj->save($sdf)){
            //事务回滚
            $errmsg = $sdf['order_bn'].'订单创建失败:'.$orderObj->db->errorinfo();;
            $orderObj->db->rollBack();
            return false;
        }else{
            //事务确认
            $orderObj->db->commit($transaction);
        }
        

        if ($sdf['order_type'] == 'presale'){
            //补全支付金额
            $this->presale_paymentadd($sdf);
            //$sdf['pay_status'] = 1;
        }
        kernel::single('console_map_order')->getLocation($sdf['order_id']);
        
        if( $sdf['createway'] != 'matrix' && (bccomp('0.000', ($sdf['total_amount']/1),3) == 0) && $sdf['shipping']['is_cod'] != 'true'  ){
            //0元订单是否需要财审.
            kernel::single('ome_order_order')->order_pay_confirm($sdf['shop_id'],$sdf['order_id'],$sdf['total_amount']);
        }
        
        //添加订单预占
        $freeze_obj_type = material_basic_material_stock_freeze::__ORDER;
        $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
        $needFreezeItems = [];
        foreach($sdf['order_objects'] as $key => $object)
        {
            $store_code = $object['store_code'];
            
            //[刷单]brush特殊订单,不用预占库存
            if($sdf['order_type'] == 'brush'){
                continue;
            }

            // 已关闭订单不用预占库存
            if (in_array($sdf['status'], ['close', 'dead']) || in_array($sdf['source_status'], ['TRADE_CLOSED'])) {
                continue;
            }

            $branch_id = 0;
            if($store_code) {
                $appointBranch = kernel::single('ome_branch_type')->isAppointBranch($sdf);
                $arrBranch     = kernel::single('ome_branch_type')->getBranchIdByStoreCode($store_code, $appointBranch);
                $branch_id = $arrBranch[$store_code];
            }
            //order_items
            if($object['order_items']){
                foreach($object['order_items'] as $k=>$item)
                {
                    $num = intval($item['quantity'])-intval($item['sendnum']);
                    $product_id = $item['product_id'];
                    
                    if($product_id > 0 && ($item['delete'] == 'false' || !isset($item['delete'])) && $num > 0){
                        $item['store_code'] = $store_code;
                        $item['branch_id'] = $branch_id;
                        $item['goods_id'] = $object['goods_id'];
                        $needFreezeItems[] = $item;
                    }
                }
            }
        }
        $branchBatchList = [];
        if($needFreezeItems) {
            uasort($needFreezeItems, [kernel::single('console_iostockorder'), 'cmp_productid']);
            foreach($needFreezeItems as $item) {
                $num = intval($item['quantity'])-intval($item['sendnum']);
                $product_id = $item['product_id'];
                $goods_id = $item['goods_id'];

                $freezeData = [];
                $freezeData['bm_id'] = $product_id;
                $freezeData['sm_id'] = $goods_id;
                $freezeData['obj_type'] = $freeze_obj_type;
                $freezeData['bill_type'] = 0;
                $freezeData['obj_id'] = $sdf['order_id'];
                $freezeData['shop_id'] = $sdf['shop_id'];
                $freezeData['branch_id'] = $item['branch_id'];
                $freezeData['bmsq_id'] = $bmsq_id;
                $freezeData['num'] = $num;
                $freezeData['log_type'] = '';
                $freezeData['store_code'] = $item['store_code'];
                $freezeData['obj_bn'] = $sdf['order_bn'];
                //修改预占库存流水
                $branchBatchList[] = $freezeData;
            }
        }
        //修改预占库存流水
        $err = '';
        $rsFB = $basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);

        //增加订单创建日志
        $logObj->write_log('order_create@ome',$sdf['order_id'],'订单创建成功'.($rsFB ? '' : '，预占库存失败：'.$err));
        
        //判断是否jdl
        if(kernel::single('ome_order_bool_type')->isJDLVMI($sdf['order_bool_type'])){
            if($sdf['platform_order_bn']){
                kernel::single('console_stock_occupy')->deleteOrderOccupy($sdf['platform_order_bn']);
            }
            
        }
        
        //失败订单并且福袋分配错误信息
        if($sdf['is_fail'] && $sdf['luckybag_error']){
            $logObj->write_log('order_modify@ome', $sdf['order_id'], $sdf['luckybag_error']);
        }
        
        //福袋分配成功，添加标识
        $hasLuckybag = false;
        foreach($sdf['order_objects'] as $object){
            if($object['obj_type'] == 'lkb'){
                $hasLuckybag = true;
                break;
            }
        }
        if($hasLuckybag){
            kernel::single('ome_bill_label')->markBillLabel($sdf['order_id'], '', 'SOMS_FUKUBUKURO', 'order', $err, 0);
        }
        
        return true;
    }

    #子商品在商品中的占比
    public function getSmBmRate($order) {
        $smIds = array();
        $bmIds = array();
        $luckySmIds = array();
        $luckyRuleList = array();
        foreach ($order['order_objects'] as $object)
        {
            $goods_id = $object['goods_id'];
            
            //obj_type
            if($object['obj_type'] == 'lkb'){
                //福袋类型
                $luckySmIds[$goods_id] = $goods_id;
            }else{
                //其它类型
                $smIds[$goods_id] = $goods_id;
            }
            
            //items
            foreach ($object['order_items'] as $i => $item)
            {
                $product_id = $item['product_id'];
                $luckybag_id = intval($item['luckybag_id']);
                
                if($item['delete'] == 'false') {
                    $bmIds[$item['product_id']] = $item['product_id'];
                }
                
                //福袋规则
                $luckyRuleList[$goods_id][$luckybag_id][$product_id] = array(
                    'combine_id' => $luckybag_id,
                    'quantity' => $item['quantity'],
                );
            }
        }
        
        //sm_id
        $smBmRate = array();
        if($smIds){
            $smBc = app::get('material')->model('sales_basic_material')->getList('sm_id, bm_id, rate', array('sm_id'=>$smIds));
            foreach ($smBc as $v) {
                $smBmRate[$v['sm_id']][$v['bm_id']] = $v['rate'];
            }
        }
        
        //福袋类型
        if($luckySmIds){
            $combineLib = kernel::single('material_fukubukuro_combine');
            $error_msg = '';
            $smRateList = $combineLib->getFudaiRateBySmids($luckySmIds, $luckyRuleList, $error_msg);
            if($smRateList){
                foreach ($smRateList as $sm_id => $smVal)
                {
                    //一个销售物料对应多个福袋
                    foreach ($smVal as $combine_id => $bmRaios)
                    {
                        //基础物料对应的价格占比
                        foreach ($bmRaios as $bm_id => $bm_ratio)
                        {
                            //按销售物料ID + 福袋ID = 纬度,汇总每个福袋总占比
                            if(!isset($smBmRate[$sm_id][$combine_id]['total_ratio'])){
                                $smBmRate[$sm_id][$combine_id]['total_ratio'] = $bm_ratio;
                            }else{
                                $smBmRate[$sm_id][$combine_id]['total_ratio'] += $bm_ratio;
                            }
                            
                            //按销售物料ID + 福袋ID + 基础物料ID = 纬度
                            $smBmRate[$sm_id][$combine_id][$bm_id] = $bm_ratio;
                        }
                    }
                }
            }
        }
        
        $bmExt = app::get('material')->model('basic_material_ext')->getList('bm_id,cost', ['bm_id'=>$bmIds]);
        $bmExt = array_column($bmExt, null, 'bm_id');
        foreach ($order['order_objects'] as $object) {
            $goods_id = $object['goods_id'];
            if(!$smBmRate[$goods_id]) {
                $porth = 'number';
                foreach ($object['order_items'] as $i => $item) {
                    if($bmExt[$item['product_id']]['cost'] > 0) {
                        $porth = 'cost';
                    }
                }
                foreach ($object['order_items'] as $i => $item) {
                    if($item['delete'] != 'false') {
                        continue;
                    }
                    $smBmRate[$object['goods_id']][$item['product_id']] = $porth == 'number' ? ($item['quantity'] ? : $item['nums']) : $bmExt[$item['product_id']]['cost'];
                }
            }
        }
        
        return $smBmRate;
    }
    
    public function divide_objects_to_items($order) {
        // 处理SET商品(pkg类型)固定价销售价计算
        $order = $this->processSetFixedPrice($order);
        
        $smBmRate = $this->getSmBmRate($order);
        foreach ($order['order_objects'] as $k => $object)
        {
            $goods_id = $object['goods_id'];
            $obj_type = $object['obj_type'];
            
            //check
            if(empty($smBmRate[$goods_id])) {
                continue;
            }
            
            //items
            $tmpOrderItems = $object['order_items'];
            $lkbOrderItems = [];
            
            // 如果是pkg类型并且有固定价格，需要过滤掉固定价格的items
            $fixedOrderItems = [];
            $is_fixed_price = false;
            if($obj_type == 'pkg' && $object['is_set_fixed_price']){
                foreach ($tmpOrderItems as $i => $item)
                {
                    $product_id = $item['product_id'];
                    if($product_id < 1) {
                        continue 2;
                    }
                    
                    // 过滤掉固定价的item商品明细
                    if($item['is_fixed_price'] == 'true'){
                        $is_fixed_price = true;
                        $fixedOrderItems[$i] = $item;
                        unset($tmpOrderItems[$i]);
                        continue;
                    }
                    
                    $tmpOrderItems[$i]['porth_field'] = $smBmRate[$goods_id][$product_id];
                }
                
                // 如果有固定价格，使用剩余金额进行分摊
                $newOrderObjects = $object;
                if($is_fixed_price){
                    if(isset($object['true_sale_price']) && $object['true_sale_price'] >= 0){
                        $newOrderObjects['sale_price'] = $object['true_sale_price'];
                    }
                    if(isset($object['true_amount']) && $object['true_amount'] >= 0){
                        $newOrderObjects['amount'] = $object['true_amount'];
                    }
                    if(isset($object['true_divide_order_fee']) && $object['true_divide_order_fee'] >= 0){
                        $newOrderObjects['divide_order_fee'] = $object['true_divide_order_fee'];
                    }
                    if(isset($object['true_actually_amount']) && $object['true_actually_amount'] >= 0){
                        $newOrderObjects['actually_amount'] = $object['true_actually_amount'];
                    }
                    if(isset($object['true_settlement_amount']) && $object['true_settlement_amount'] >= 0){
                        $newOrderObjects['settlement_amount'] = $object['true_settlement_amount'];
                    }
                }
                
                // 对剩余items进行分摊
                if($tmpOrderItems){
                    $tmpOrderItems = $this->recalculate_items_proportion($tmpOrderItems, $newOrderObjects);
                }
                
                // 合并固定价格的items
                $tmpOrderItems = array_replace($tmpOrderItems, $fixedOrderItems);
            }else{
                // 没有基础物料有固定价格，按原来的逻辑处理
                foreach ($tmpOrderItems as $i => $item)
                {
                    $product_id = $item['product_id'];
                    if($product_id < 1) {
                        continue 2;
                    }
                    
                    //福袋类型
                    if($obj_type == 'lkb'){
                        $combine_id = $item['luckybag_id'];
                        
                        //福袋占比
                        $total_ratio = $smBmRate[$goods_id][$combine_id]['total_ratio'];
                        $total_ratio = ($total_ratio ? $total_ratio : 0);
                        
                        //基础物料占比
                        $bm_ratio = $smBmRate[$goods_id][$combine_id][$product_id];
                        $bm_ratio = ($bm_ratio ? $bm_ratio : 0);
                        
                        //object
                        if(!isset($lkbOrderItems[$combine_id])){
                            $lkbOrderItems[$combine_id] = $object;
                            $lkbOrderItems[$combine_id]['combine_id'] = $combine_id;
                            $lkbOrderItems[$combine_id]['porth_field'] = $total_ratio;
                            
                            //unset
                            unset($lkbOrderItems[$combine_id]['order_items']);
                        }
                        
                        //按福袋ID的纬度进行分摊比例
                        $lkbOrderItems[$combine_id]['items'][$i] = $item;
                        $lkbOrderItems[$combine_id]['items'][$i]['porth_field'] = $bm_ratio;
                        
                        //记录基础物料价格贡献比
                        $lkbOrderItems[$combine_id]['items'][$i]['price_rate'] = $bm_ratio;
                    }else{
                        $tmpOrderItems[$i]['porth_field'] = $smBmRate[$goods_id][$product_id];
                    }
                }
                
                //福袋
                if($obj_type == 'lkb'){
                    //先按每个福袋占比分摊金额
                    $lkbOrderItems = $this->recalculate_items_proportion($lkbOrderItems, $object);
                    
                    //再按福袋下面的基础物料占比分摊金额
                    foreach ($lkbOrderItems as $combine_id => $lkbItemRow)
                    {
                        $tempItemList = $lkbItemRow['items'];
                        $tempItemList = $this->recalculate_items_proportion($tempItemList, $lkbItemRow);
                        
                        //覆盖order_items
                        foreach ($tempItemList as $temp_i => $tempItemRow)
                        {
                            $tmpOrderItems[$temp_i] = $tempItemRow;
                        }
                    }
                }else{
                    $tmpOrderItems = $this->recalculate_items_proportion($tmpOrderItems, $object);
                }
            }
            
            //order_items
            $order['order_objects'][$k]['order_items'] = $tmpOrderItems;
        }
        
        return $order;
    }
    
    /**
     * 重新分摊计算订单object层下的items货品相关金额
     *
     * @param $tmpOrderItems 单个order_object关联的order_items明细列表
     * @param $object 单个order_object信息
     * @return void
     */
    public function recalculate_items_proportion($tmpOrderItems, $object)
    {
        //sale_price
        $options = array (
            'part_total'  => $object['sale_price'],
            'part_field'  => 'sale_price',
            'porth_field' => 'porth_field',
        );
        $tmpOrderItems = $this->calculate_part_porth($tmpOrderItems, $options);
        
        //pmt_price
        $options = array (
            'part_total'  => $object['pmt_price'],
            'part_field'  => 'pmt_price',
            'porth_field' => 'porth_field',
        );
        $tmpOrderItems = $this->calculate_part_porth($tmpOrderItems, $options);
        
        //part_mjz_discount
        $options = array (
            'part_total'  => $object['part_mjz_discount'],
            'part_field'  => 'part_mjz_discount',
            'porth_field' => 'porth_field',
            'minuend_field' => 'sale_price',
        );
        $tmpOrderItems = $this->calculate_part_porth($tmpOrderItems, $options);
        
        //amount
        $options = array (
            'part_total' => $object['amount'],
            'part_field' => 'amount',
            'porth_field' => 'porth_field',
        );
        $tmpOrderItems = $this->calculate_part_porth($tmpOrderItems, $options);
        
        //format
        foreach ($tmpOrderItems as $i => $v)
        {
            //购买数量
            $num = $v['quantity'] ? : $v['nums'];
            
            //price
            $tmpOrderItems[$i]['price'] = sprintf('%.2f', ($v['sale_price'] + $v['pmt_price']) / $num);
            
            //divide_order_fee
            $tmpOrderItems[$i]['divide_order_fee'] = sprintf('%.2f', $v['sale_price'] - $v['part_mjz_discount']);
        }
        
        //settlement_amount
        $options = array (
            'part_total' => $object['settlement_amount'],
            'part_field' => 'settlement_amount',
            'porth_field' => 'porth_field',
        );
        $tmpOrderItems = $this->calculate_part_porth($tmpOrderItems, $options);
        
        //actually_amount
        $options = array (
            'part_total' => $object['actually_amount'],
            'part_field' => 'actually_amount',
            'porth_field' => 'porth_field',
            'minuend_field' => 'divide_order_fee',
        );
        $tmpOrderItems = $this->calculate_part_porth($tmpOrderItems, $options);

        foreach ($tmpOrderItems as $i => $v)
        {
            //不保存，用完重置
            $tmpOrderItems[$i]['porth_field'] = null;
            $tmpOrderItems[$i]['platform_pay_amount'] = sprintf('%.2f', $v['divide_order_fee'] - $v['actually_amount']);
            $tmpOrderItems[$i]['platform_amount'] = sprintf('%.2f', $v['settlement_amount'] - $v['divide_order_fee']);
        }
        return $tmpOrderItems;
    }
    
    /**
     * 新建订单 计算实付
     * @param  array sdf order保存前的数据
     * @return void
     */
    public function create_divide_pay(&$sdf) {
        if($sdf['discount'] < 0) {
            $all_discount = $sdf['pmt_order'] - $sdf['discount'];
        } else {
            $all_discount = $sdf['pmt_order'];
        }
        
        $needCaculate = array();
        $saleAmount = 0;
        foreach ($sdf['order_objects'] as $k => $object) {
            // check divide_order_fee
            if(floatval($object['divide_order_fee']) > 0){
                $needCaculate = array();
                break;
            }
            $obj_sale_price = $object['sale_price'];
            $currentItem = current($object['order_items']);
            if(floatval($obj_sale_price) > 0 && $currentItem['delete'] != 'true') {
                $needCaculate[$k] = $object;
                $saleAmount += $obj_sale_price;
            }
        }
        
        //平台没有推送实付金额,计算并赋值实付金额
        if($needCaculate && $saleAmount) {
            $options = array (
                'part_total'  => $all_discount,
                'part_field'  => 'part_mjz_discount',
                'porth_field' => 'sale_price',
                'minuend_field' => 'sale_price',
            );
            $needCaculate = $this->calculate_part_porth($needCaculate, $options);
            foreach ($needCaculate as $k => $object) {
                $sdf['order_objects'][$k]['part_mjz_discount'] = $object['part_mjz_discount'];
                $sdf['order_objects'][$k]['divide_order_fee'] = sprintf('%.2f', $object['sale_price'] - $sdf['order_objects'][$k]['part_mjz_discount']);
            }
        }
        foreach ($sdf['order_objects'] as $k => $object) {
            if(!$object['settlement_amount']){
                $sdf['order_objects'][$k]['settlement_amount'] = $object['divide_order_fee'];
            }
            if(!$object['actually_amount']){
                $sdf['order_objects'][$k]['actually_amount'] = $object['divide_order_fee'];
            }
            $sdf['order_objects'][$k]['platform_pay_amount'] = sprintf('%.2f', $object['divide_order_fee'] - $sdf['order_objects'][$k]['actually_amount']);
            $sdf['order_objects'][$k]['platform_amount'] = sprintf('%.2f', $sdf['order_objects'][$k]['settlement_amount'] - $object['divide_order_fee']);
        }
        
        // 计算订单items分摊金额
        $sdf = $this->divide_objects_to_items($sdf);
    }

    /**
     * 编辑订单 计算实付
     * @param  array &$rs  编辑的明细参数
     * @param  array $post 编辑页面传输的post参数
     * @return void
     */
    public function edit_divide_pay(&$rs, $post) {
        $need_divide = array();
        foreach ($rs['obj'] as $k => $obj) {
            if($obj['delete'] == 'true') {
                continue;
            }
            if($obj['sale_price'] > 0) {
                $obj['order_items'] = $obj['items'];
                unset($obj['items']);
                $need_divide['obj-'.$k] = $obj;
            } else {
                $rs['obj'][$k]['part_mjz_discount'] = 0;
                $rs['obj'][$k]['divide_order_fee'] = 0;
                $rs['obj'][$k]['actually_amount'] = 0;
                $rs['obj'][$k]['settlement_amount'] = 0;
                $rs['obj'][$k]['platform_pay_amount'] = 0;
                $rs['obj'][$k]['platform_amount'] = 0;
                foreach ($obj['items'] as $i => $item) {
                    $rs['obj'][$k]['items'][$i]['part_mjz_discount'] = 0;
                    $rs['obj'][$k]['items'][$i]['divide_order_fee'] = 0;
                    $rs['obj'][$k]['items'][$i]['actually_amount'] = 0;
                    $rs['obj'][$k]['items'][$i]['settlement_amount'] = 0;
                    $rs['obj'][$k]['items'][$i]['platform_pay_amount'] = 0;
                    $rs['obj'][$k]['items'][$i]['platform_amount'] = 0;
                }
            }
        }
        foreach ($rs['new'] as $k => $new) {
            if($new['sale_price'] > 0) {
                $new['order_items'] = $new['items'];
                unset($new['items']);
                $need_divide['new-'.$k] = $new;
            } else {
                $rs['new'][$k]['part_mjz_discount'] = 0;
                $rs['new'][$k]['divide_order_fee'] = 0;
                $rs['new'][$k]['actually_amount'] = 0;
                $rs['new'][$k]['settlement_amount'] = 0;
                $rs['new'][$k]['platform_pay_amount'] = 0;
                $rs['new'][$k]['platform_amount'] = 0;
                foreach ($new['items'] as $i => $item) {
                    $rs['new'][$k]['items'][$i]['part_mjz_discount'] = 0;
                    $rs['new'][$k]['items'][$i]['divide_order_fee'] = 0;
                    $rs['new'][$k]['items'][$i]['actually_amount'] = 0;
                    $rs['new'][$k]['items'][$i]['settlement_amount'] = 0;
                    $rs['new'][$k]['items'][$i]['platform_pay_amount'] = 0;
                    $rs['new'][$k]['items'][$i]['platform_amount'] = 0;
                }
            }
        }
        $rs['settlement_amount'] = 0;
        $rs['actually_amount'] = 0;
        foreach ($need_divide as $n => $v) {
            list($type, $k) = explode('-', $n);
            if($v['settlement_amount'] > 0 && !empty($v['oid']) && $v['oid'] != -1) {
                $need_divide[$n]['part_mjz_discount'] = sprintf('%.2f', $v['sale_price'] - $v['divide_order_fee']);
                $need_divide[$n]['platform_pay_amount'] = sprintf('%.2f', $v['divide_order_fee'] - $v['actually_amount']);
                $need_divide[$n]['platform_amount'] = sprintf('%.2f', $v['settlement_amount'] - $v['divide_order_fee']);
            } else {
                $need_divide[$n]['part_mjz_discount'] = 0;
                $need_divide[$n]['divide_order_fee'] = sprintf('%.2f', $v['sale_price']);
                $need_divide[$n]['platform_pay_amount'] = 0;
                $need_divide[$n]['platform_amount'] = 0;
                $need_divide[$n]['actually_amount'] = $need_divide[$n]['divide_order_fee'];
                $need_divide[$n]['settlement_amount'] = $need_divide[$n]['divide_order_fee'];
            }
            $rs['settlement_amount'] += $need_divide[$n]['settlement_amount'];
            $rs['actually_amount'] += $need_divide[$n]['actually_amount'];
        }
        $order = array('order_objects' => $need_divide);
        $order = $this->divide_objects_to_items($order);
        foreach ($order['order_objects'] as $n => $v) {
            list($type, $k) = explode('-', $n);
            $rs[$type][$k]['part_mjz_discount'] = $v['part_mjz_discount'];
            $rs[$type][$k]['divide_order_fee'] = $v['divide_order_fee'];
            $rs[$type][$k]['actually_amount'] = $v['actually_amount'];
            $rs[$type][$k]['settlement_amount'] = $v['settlement_amount'];
            $rs[$type][$k]['platform_pay_amount'] = $v['platform_pay_amount'];
            $rs[$type][$k]['platform_amount'] = $v['platform_amount'];
            foreach ($v['order_items'] as $i => $item) {
                $rs[$type][$k]['items'][$i]['price'] = $item['price'];
                $rs[$type][$k]['items'][$i]['pmt_price'] = $item['pmt_price'];
                $rs[$type][$k]['items'][$i]['sale_price'] = $item['sale_price'];
                $rs[$type][$k]['items'][$i]['part_mjz_discount'] = $item['part_mjz_discount'];
                $rs[$type][$k]['items'][$i]['divide_order_fee'] = $item['divide_order_fee'];
                $rs[$type][$k]['items'][$i]['actually_amount'] = $item['actually_amount'];
                $rs[$type][$k]['items'][$i]['settlement_amount'] = $item['settlement_amount'];
                $rs[$type][$k]['items'][$i]['platform_pay_amount'] = $item['platform_pay_amount'];
                $rs[$type][$k]['items'][$i]['platform_amount'] = $item['platform_amount'];
            }
        }
    }

    /**
     * 根据传入参数重置订单分派及审核状态，主要用于发货单叫回以后的后续处理
     *
     * @param Array $param 传入参数
     * @return void
     * @author xiayuanjun
     **/
    public function resumeOrdStatus($param)
    {
        $orderObj = app::get('ome')->model('orders');
        $dlyObj = app::get('ome')->model("delivery");
        $operLogObj = app::get('ome')->model('operation_log');
        
        // check
        if(empty($param['order_id'])){
            return false;
        }
        
        // order info
        $orderInfo = $orderObj->dump(array('order_id'=>$param['order_id']), '*');
        
        //订单已全额退款
        if(isset($orderInfo['payed']) && $orderInfo['payed'] == 0 && $orderInfo['pay_status'] == '5'){
            return false;
        }
        
        //是否重新分派
        $need_dispatch = true;
        $is_log = false;
        $log_message = '';
        
        //修改订单确认状态
        $opData['confirm'] = 'N';
        $opData['process_status'] = 'unconfirmed';
        $opData['pause'] = 'true';
        $opData['splited_num_upset_sql'] = 'IF(`splited_num` IS NULL OR `splited_num`<=1, 0, `splited_num`-1)';
        
        //获取订单关联的有效发货单个数
        $existDelivery = $dlyObj->validDeiveryByOrderId($orderInfo['order_id']);
        
        //[拆单]判断有部分拆分的有效发货单存在(确认状态为splitting)
        if($existDelivery){
            $opData['confirm'] = 'Y';
            
            //订单拆分状态
            $opData['process_status'] = 'splited';

            $item_list = $orderObj->getItemBranchStore($param['order_id']);

            foreach ((array) $item_list as $il) {
                foreach ((array) $il as $var) {
                    foreach ((array) $var['order_items'] as $v)
                    {
                        //[拆单]部分拆单状态
                        if ($v['left_nums'] > 0 && $v['delete'] == 'false'){
                            $opData['process_status'] = 'splitting';
                        }
                    }
                }
            }
            
            $opData['pause'] = 'false';//因部分拆分后订单"基本信息"Tab没有操作按扭
            $need_dispatch = false;
            
            $is_log = true;
            $log_message = '有部分拆分的有效发货单存在,不重新分派订单';
        }elseif(isset($orderInfo['payed']) && $orderInfo['payed'] == 0 && $orderInfo['ship_status'] == '0'){
            //[兼容]订单已全额退款并且无有效的发货单
            
            //获取退款单及退款申请记录
            $refundObj = app::get('ome')->model('refunds');
            $refund_detail = $refundObj->dump(array('order_id'=>$orderInfo['order_id'], 'status'=>'succ'), 'refund_id');
            if($refund_detail){
                $need_dispatch = false;
                
                //取消订单
                $opData['pay_status'] = '5';
                $opData['pause'] = 'false';
                $opData['confirm'] = 'N';
                $opData['process_status'] = 'cancel';
                $opData['status'] = 'dead';
                $opData['archive'] = 1;//订单归档
                
                $is_log = true;
                $log_message = '[重置订单]全额退款并取消订单';
            }
        }
        
        if(in_array($orderInfo['ship_status'],array('2','3'))){
            $need_dispatch = false;
        }
        
        $rs = $orderObj->update($opData,array('order_id'=>$orderInfo['order_id'], 'status|noequal'=>'dead'));
        if(is_bool($rs)) {
            //log
            $log_message = '更新订单确认状态失败,未重新分派订单';
            $operLogObj->write_log('order_modify@ome', $orderInfo['order_id'], $log_message);
            
            return false;
        }
        
        //log
        if($is_log && $log_message){
            $operLogObj->write_log('order_modify@ome', $orderInfo['order_id'], $log_message);
        }
        
        //订单状态重置后判断重新分派，放后面是因为分派的时候只认未拆分的订单，不然取规则的时候初始化订单信息会是空
        if($need_dispatch){
            $is_dispatch = $orderObj->updateDispatchinfo($orderInfo['order_id']);
            if($is_dispatch === false){
                // 上一次是系统审核订单,是更新订单为未分派
                if($orderInfo['group_id'] == 16777215 || $orderInfo['op_id'] == 16777215){
                    $orderObj->update(array('group_id'=>0, 'op_id'=>0), array('order_id'=>$orderInfo['order_id']));
                    
                    $log_message = '发货单叫回成功,订单更新为未分派';
                }else{
                    $log_message = '发货单叫回成功,未配置自动分派规则,订单不会自动分派';
                }
                
                //log
                $operLogObj->write_log('order_modify@ome', $orderInfo['order_id'], $log_message);
            }
        }
        
        // data
        $updateOrderData = $opData;
        $updateOrderData['order_id'] = $orderInfo['order_id'];
        
        // [更新]预约订单的状态
        $error_msg = '';
        kernel::single('ome_order_reservation')->operateReservationOrder($updateOrderData, $error_msg);
    }
    
    /**
     * 系统自动审单
     *
     * @todo 前端店铺订单进入ERP时系统自动审单
     * @param intval $order_id
     * @author wangbiao@shopex.cn
     */
    public function auto_order_combine($order_id,$sdf=array())
    {
        $order_ids = array();
        $order_ids[] = $order_id;
        
        if (empty($order_ids)){
            return false;
        }
        
        // 是否延迟自动审核订单
        $is_delay_confirm = false;
        if($sdf['op_type'] == 'timing_confirm' && $sdf['timing_time'] && $sdf['cnAuto'] == 'true') {
            $is_delay_confirm = true;
        }
        
        //是否开启_系统自动审单[默认:忽略可合并的订单 ]
        $cfg_combine    = app::get('ome')->getConf('ome.order.is_auto_combine');
        $is_cnAuto   = app::get('ome')->getConf('ome.order.Auto');
        if($cfg_combine != 'true' && !$is_delay_confirm){
            if ( ($sdf['cnAuto'] == 'false' ||  $is_cnAuto=='false' || empty($sdf['cnAuto']))
                && $sdf['offlineAuto'] != 'true' 
            ){
                kernel::single('ome_order_branch')->preSelect($order_id);
                return false;
            }
        }
        
        //是否选择指定时间段自动审单
        $cfg_auto_timer = app::get('ome')->getConf('ome.order.auto_timer');
        if ($cfg_auto_timer == 2){ //选择了 “指定时间” 匹配时间段
            $cfg_auto_exec_timer = app::get('ome')->getConf('ome.order.auto_exec_timer');
            $has_belong_timer = false;
            $current_time = time();
            $current_date = date('Y-m-d');
            foreach ($cfg_auto_exec_timer as $var_caet){
                $start_time_int = strtotime($current_date.' '.intval($var_caet["start_hour"]).':'.intval($var_caet["start_minute"]).':00');
                $end_time_int = strtotime($current_date.' '.intval($var_caet["end_hour"]).':'.intval($var_caet["end_minute"]).':00');
                if ($current_time >= $start_time_int && $current_time <= $end_time_int){
                    $has_belong_timer= true;
                    break;
                }
            }
            if (!$has_belong_timer){ //没有匹配到时间段
                kernel::single('ome_order_branch')->preSelect($order_id);
                return false;
            }
        }
        
        //检查上一次延迟自动审核订单任务
        //@todo: 防止京东订单一直打更新接口,订单数据没有变化也更新oaid,一直延迟审核订单;
        if($sdf['op_type'] == 'timing_confirm' && $sdf['timing_time']) {
            if(isset($sdf['is_check_last_time']) && $sdf['is_check_last_time'] === true){
                $filter = array(
                    'obj_id' => $order_id,
                    'obj_type' => 'timing_confirm_order',
                );
                $miscTaskInfo = app::get('ome')->model('misc_task')->db_dump($filter, '*');
                
                //检查上一次延迟执行时间
                if($miscTaskInfo && $miscTaskInfo['exec_time'] > time()){
                    return false;
                }
            }
        }
        
        // 查询订单状态
        $order = app::get('ome')->model('orders')->db_dump($order_id, '*');
        
        //是否允许审核
        if($order['is_not_combine'] > 0 && !$is_delay_confirm){
            //logs
            app::get('ome')->model('operation_log')->write_log('order_edit@ome', $order_id, '订单已被标记：不允许审核;');
            
            return false;
        }
        
        // 检测京东订单是否有微信支付先用后付的单据
        $use_before_payed = false;
        if ($order['shop_type'] == '360buy') {
            $labelCode = kernel::single('ome_bill_label')->getLabelFromOrder($order['order_id']);
            $labelCode = array_column($labelCode, 'label_code');
            $use_before_payed = kernel::single('ome_order')->canDeliveryFromBillLabel($labelCode);
        }
        
        //是否允许自动审核订单
        $isAuto = ($order['pay_status'] == '1' || $order['is_cod'] == 'true' || $use_before_payed) 
                && $order['status'] == 'active' 
                && in_array($order['order_type'], kernel::single('ome_order_func')->get_normal_order_type()); 
        if(!$isAuto){
            kernel::single('ome_order_branch')->preSelect($order_id);
            return false;
        }
        
        //千牛修改收货人地址或者售前退款完成编辑订单删除商品,延迟5分钟自动审核订单
        if($sdf['op_type'] == 'timing_confirm' && $sdf['timing_time']) {
            $timing_time = $sdf['timing_time'];
            
            //logs
            app::get('ome')->model('operation_log')->write_log('order_edit@ome', $order_id, $sdf['memo'] .'，延时自动审单：' . date('Y-m-d H:i:s', $timing_time));
            
            //延时任务
            $task = array(
                'obj_id' => $order_id,
                'obj_type' => 'timing_confirm_order',
                'exec_time' => $timing_time,
            );
            
            app::get('ome')->model('misc_task')->saveMiscTask($task);
            
            return true;
        }
        
        //获取system账号信息
        $opinfo = kernel::single('ome_func')->get_system();
        
        //自动审单_批量日志
        $blObj  = app::get('ome')->model('batch_log');
        
        $batch_number = count($order_ids);
        $bldata = array(
                'op_id' => $opinfo['op_id'],
                'op_name' => $opinfo['op_name'],
                'createtime' => time(),
                'batch_number' => $batch_number,
                'log_type'=> 'combine',
                'log_text'=> serialize($order_ids)
        );
        $result = $blObj->save($bldata);
        
        //自动审单_任务队列(改成多队列多进程)
        if (defined('SAAS_COMBINE_MQ') && SAAS_COMBINE_MQ == 'true') {
            $data = array();
            $data['spider_data']['url'] = kernel::openapi_url('openapi.autotask','service');

            $push_params = array(
                'log_text'  => $bldata['log_text'],
                'log_id'    => $bldata['log_id'],
                'task_type' => 'autorder',
            );
            $push_params['taskmgr_sign'] = taskmgr_rpc_sign::gen_sign($push_params);
            foreach ($push_params as $key => $val) {
                $postAttr[] = $key . '=' . urlencode($val);
            }

            $data['spider_data']['params']    = empty($postAttr) ? '' : join('&', $postAttr);
            $data['relation']['to_node_id']   = base_shopnode::node_id('ome');
            $data['relation']['from_node_id'] = '0';
            $data['relation']['tid']          = $bldata['log_id'];
            $data['relation']['to_url']       = $data['spider_data']['url'];
            $data['relation']['time']         = time();

            $routerKey = 'tg.order.combine.'.$data['nodeId'];

            $message = json_encode($data);
            $mq = kernel::single('base_queue_mq');
            $mq->connect($GLOBALS['_MQ_COMBINE_CONFIG'], 'TG_COMBINE_EXCHANGE', 'TG_COMBINE_QUEUE');
            $mq->publish($message, $routerKey);
            $mq->disConnect();
        } else {
            $push_params = array(
                'data' => array(
                        'log_text'  => $bldata['log_text'],
                        'log_id'    => $bldata['log_id'],
                        'task_type' => 'autorder',
                ),
                'url' => kernel::openapi_url('openapi.autotask','service')
            );
            
            kernel::single('taskmgr_interface_connecter')->push($push_params);  
        }

        return true;
    }

    /**
     * 货到付款订单签收后自动支付
     *
     * @param int $deliveryId 发货单ID
     * @author wangbiao@shopex.cn
     */
    public function codAutoPay($deliveryId) {
        $objOrder = app::get('ome')->model('orders');
        $oPayment = app::get('ome')->model('payments');
        $orderDelivery = app::get('ome')->model('delivery_order')->getList('*',array('delivery_id'=>$deliveryId));
        $arrOrderId = array();
        foreach($orderDelivery as $od) {
            $arrOrderId[] = $od['order_id'];
        }
        $filter = array('order_id'=>$arrOrderId,'is_cod'=>'true','pay_status'=>'0');
        $arrOrderData = $objOrder->getList('order_id,total_amount,ship_status,shop_id,currency,source', $filter);
        $arrShopId = array();
        foreach($arrOrderData as $val) {
            $arrShopId[$val['shop_id']] = $val['shop_id'];
        }
        $shop = app::get('ome')->model('shop')->getList('shop_id, node_id, node_type', array('shop_id'=>$arrShopId));
        $arrShopData = array();
        foreach($shop as $val) {
            $arrShopData[$val['shop_id']] = $val;
        }
        $objsales = app::get('ome')->model('sales');
        $saleData = $objsales->getList('sale_id,order_id', array('order_id' => $arrOrderId));
        $orderSale = array();
        foreach($saleData as $sale) {
            $orderSale[$sale['order_id']][] = $sale['sale_id'];
        }
        $paymentCfg =  app::get('ome')->model('payment_cfg')->getList('id', array('pay_bn'=>'offline'), 0, 1);
        $c2c_shop = ome_shop_type::shop_list();
        foreach ($arrOrderData as $order) {
            $shop_detail = $arrShopData[$order['shop_id']];
            if ($shop_detail['node_id'] && !in_array($shop_detail['node_type'],$c2c_shop) && $order['source'] == 'matrix'){
                $params = array (
                    'order_id' => $order['order_id'],
                    'shop_id' => $order['shop_id'],
                    'money' => $order['total_amount'],
                    'pay_type' => 'offline',
                    'payment' => intval($paymentCfg[0]['id']),
                    'memo' => '签收COD订单系统自动确认支付',
                );
                $oPayment->payment_request($params);
                continue;
            }
            $orderData = array();
            $orderData['payed']      = $order['total_amount'];
            $orderData['pay_status'] = 1;
            $orderData['paytime']    = time();
            $orderData['payment']    = '线下支付';
            if($order['ship_status'] == 1) {
                $orderData['status'] = 'finish';
                if($orderSale[$order['order_id']]) {
                    $objsales->update(array('paytime' => $orderData['paytime']), array('order_id' => $order['order_id']));
                }
            }
            $orderFilter = array('order_id'=>$order['order_id']);
            $objOrder->update($orderData, $orderFilter);
            //日志
            $memo = '签收COD订单自动确认支付';
            app::get('ome')->model('operation_log')->write_log('order_modify@ome',$order['order_id'],$memo);
            //生成支付单
            $payment_bn = $oPayment->gen_id();
            $paymentdata = array();
            $paymentdata['payment_bn'] = $payment_bn;
            $paymentdata['order_id'] = $order['order_id'];
            $paymentdata['shop_id'] = $order['shop_id'];
            $paymentdata['currency'] = $order['currency'];
            $paymentdata['money'] = $orderData['payed'];
            $paymentdata['paycost'] = 0;
            $curr_time = time();
            $paymentdata['t_begin'] = $curr_time;//支付开始时间
            $paymentdata['t_end'] = $curr_time;//支付结束时间
            $paymentdata['cur_money'] = $orderData['payed'];
            $paymentdata['pay_type'] = 'offline';
            $paymentdata['ip'] = kernel::single("base_request")->get_remote_addr();
            $paymentdata['status'] = 'succ';
            $paymentdata['memo'] = '签收COD订单系统自动确认支付';
            $paymentdata['is_orderupdate'] = 'false';
            $oPayment->create_payments($paymentdata);
            //日志
            app::get('ome')->model('operation_log')->write_log('payment_create@ome',$paymentdata['payment_id'],'生成支付单');
        }
    }

    public function genOrderCombieHashIdx($params){

        $order_combine_hash = '';
        $order_combine_idx = '';
        
        $order_id = $params['order_id'];
        $member_id = $params['member_id'];
        $order_bn = $params['order_bn'];
        $shop_id = $params['shop_id'];
        $ship_name = $params['consignee']['name'];
        $ship_mobile = $params['consignee']['mobile'];
        $ship_area = $params['consignee']['area'];
        $ship_addr = $params['consignee']['addr'];
        $order_source = $params['order_source'];
        $ship_tel = $params['consignee']['telephone'];
        $is_cod = $params['shipping']['is_cod'];
        $self_delivery = $params['self_delivery'];
        $shop_type = $params['shop_type'];
        $order_bool_type = $params['order_bool_type'];
        //物流公司
        $shipping_name = $params['shipping']['shipping_name'];
        
        //发货仓库
        $firstOrderObj = current($params['order_objects']);
        $store_code = $firstOrderObj['store_code'];
        $order_type = $params['order_type'];
        //升级服务
        $cpup_service = $params['cpup_service'];

        $uniqueness = $member_id ? $member_id : $order_bn;
        $uniqueness2 = in_array($order_source, ['tbdx','maochao']) ? $order_bn : $order_source;
        $uniqueness3 = $is_cod == 'true' ? $order_bn : $is_cod;
        $uniqueness4 = $self_delivery == 'false' ? $order_bn : $self_delivery;
    
        $extend_field = is_array($params['extend_field']) ? $params['extend_field'] : [];
    
        if ($params['extend_field'] && is_string($params['extend_field'])){
            $extend_field = @json_decode($params['extend_field'],1);
        }
    
        //自选快递订单履约发货
        $optionalExpress = false;
        if (is_array($extend_field['order_tag']) && isset($extend_field['order_tag']['shop_optional_express_info'])) {
            $shop_optional_express_info = $extend_field['order_tag']['shop_optional_express_info'];
            if (is_string($shop_optional_express_info)) {
                $extend_field['order_tag']['shop_optional_express_info'] = @json_decode($shop_optional_express_info, true);
            }
            foreach ($extend_field['order_tag']['shop_optional_express_info']['ExpressCompanys'] as $val) {
                if (isset($val['ExpressCompanyCode']) && $val['ExpressCompanyCode']) {
                    $shipping_name   = $val['ExpressCompanyCode'];
                    $optionalExpress = true;
                    break;
                }
            }
        }

        // 集运
        $jy_code = '';
        if ($sendpayMaps = $extend_field['sendpayMap']) {
            foreach($sendpayMaps as $sendpayMap){                
                if (is_string($sendpayMap)) {
                    $sendpayMap = json_decode($sendpayMap, 1);
                }
                // sendpaymap1086:1属于京东集运订单
                if ($sendpayMap && $sendpayMap['1086'] == '1') {
                    $jy_code = 'SOMS_GNJY-1'; // $label_code.'-'.$label_value
                }
            }
        }
        
        // vop的合单码
        $merged_code  = $params['extend_field']['merged_code'];
        $use_vop_code = false;

        switch (strtolower($shop_type)) {

            case 'taobao':
                $boolTypeLib = kernel::single('ome_order_bool_type');
                
                //是否是天猫物流升级订单
                $isCpup = $boolTypeLib->isCPUP($order_bool_type);
                
                //是否翱象订单
                $isAoxiang = $boolTypeLib->isAoxiang($order_bool_type);
                
                // 淘宝加密后相同的地址会出现不同的加密结果，所以加密的数据生成hashkey的时候排除 addr
                if (strpos($ship_mobile, '>>') !== false) {
                    $ship_addr = '';
                }
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$uniqueness2.'-'.$ship_tel.'-'.$shop_type;
                $combine_idx= $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$ship_tel.'-'.$shop_type;
                
                //物流公司，仓库，升级服务
                if ($isCpup) {
                    $combine_hash .= '-'.$shipping_name.'-'.$store_code.'-'.$cpup_service;
                    $combine_idx .= '-'.$shipping_name.'-'.$store_code.'-'.$cpup_service;
                }elseif($isAoxiang){
                    //[翱象订单]建议仓类型为2时,只允许相同指定仓进行合单
                    if($firstOrderObj['biz_sd_type'] == '2' && $store_code){
                        $combine_hash .= '-'.$store_code;
                        $combine_idx .= '-'.$store_code;
                    }
                }
                

                //预售
                if ($order_type == 'presale') {
                    $presaleCombine = false;
                    $this->_getpresaleCombine($presaleCombine);

                    if(!$presaleCombine){
                        //平时允许合单
                        if (!in_array(date('m'),array('5','6','10','11'))){
                            $combine_hash .= '-'.$order_type;
                            $combine_idx .= '-'.$order_type;
                        }else{
                            $combine_hash .= '-'.$order_bn;
                            $combine_idx .= '-'.$order_bn;
                        }
                    }
                    
                }

               
                break;
            case 'dangdang':
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$uniqueness3.'-'.$ship_tel.'-'.$shop_type;
                $combine_idx= $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$ship_tel.'-'.$shop_type;
                break;
            case 'amazon':
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$uniqueness4.'-'.$ship_tel.'-'.$shop_type;
                $combine_idx = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$ship_tel.'-'.$shop_type;
                break;
            case 'aikucun':
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$order_bn.'-'.$ship_tel.'-'.$shop_type;
                $combine_idx = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$ship_tel.'-'.$shop_type;
                break;
            case 'shopex_b2b':
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$ship_tel.'-'.$shop_type;
                $combine_idx = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$ship_tel.'-'.$shop_type;
                break;
            case 'vop':
                // 默认和default的一样，如果有merged_code，用merged_code
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$order_bn;
                $combine_idx  = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$order_bn;

                // 如果是erpapi新建订单，因为orderextend是在plugins中处理，此时加载不到orderextend，所以在plugins的orderextend更新一次
                if (kernel::single('ome_order_bool_type')->isJITX($order_bool_type) && $merged_code) {
                    $combine_hash = $merged_code;
                    $combine_idx  = $merged_code;
                    $use_vop_code = true;
                }
                break;
            case '360buy':
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$uniqueness3.'-'.$ship_tel.'-'.$shop_type;
                $combine_idx = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$ship_tel.'-'.$shop_type;
                if(kernel::single('ome_order_bool_type')->isJDLVMI($order_bool_type)){
                    $combine_hash .= '-'.$order_bn;
                    $combine_idx .= '-'.$order_bn;
                }
                // 京东集运
                if ($jy_code) {
                    $combine_hash .= '-'.$jy_code;
                    $combine_idx .= '-'.$jy_code;
                }
                break;
            case 'zkh':
                //震坤行不支持合单
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$order_bn;
                $combine_idx = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$order_bn;
                break;
            case 'kuaishou':
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod;
                $combine_idx = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod;

                // 非中转和中转不能合单
                if ($order_id) {
                    $billLabelMdl = kernel::single('ome_bill_label');
                    $billLabel = $billLabelMdl->getLabelFromOrder($order_id, 'order');
                    if ($billLabel) {
                        foreach ($billLabel as $b_k => $b_v) {
                            if ($b_v['label_code'] == 'XJJY') {
                                $combine_hash .= '-'.$b_v['label_code'];
                                $combine_idx  .= '-'.$b_v['label_code'];
                                break;
                            }
                        }
                    }
                } elseif (isset($params['extend_field']) && $params['extend_field']) {
                    $_tmp = $params['extend_field'];
                    if ($_tmp['consolidate_info']) {
                        if ($_tmp['consolidate_info']['consolidate_type'] && $_tmp['consolidate_info']['consolidate_type'] == 'XJJY') {
                            $combine_hash .= '-'.$_tmp['consolidate_info']['consolidate_type'];
                            $combine_idx  .= '-'.$_tmp['consolidate_info']['consolidate_type'];
                        }
                    }
                }
                break;
            case 'dewu':
                // 得物品牌直发不支持合单
                if (kernel::single('ome_order_bool_type')->isDWBrand($order_bool_type)) {
                    $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$ship_tel.'-'.$shop_type.'-'.$order_bn;
                    $combine_idx = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod.'-'.$ship_tel.'-'.$shop_type.'-'.$order_bn;
                    break;
                }
            case 'luban':
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod;
                $combine_idx = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod;
                if ($extend_field['openAddressId']) {
                    $combine_hash = $combine_hash . '-' . $extend_field['openAddressId'];
                    $combine_idx  = $combine_idx . '-' . $extend_field['openAddressId'];
                }
                //自选快递履约发货
                if($optionalExpress){
                    $combine_hash = $combine_hash . '-' . $shipping_name;
                    $combine_idx  = $combine_idx . '-' . $shipping_name;
                }
                break;
            case 'jd':
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod;
                $combine_idx = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod;
                if ($extend_field['openAddressId']) {
                    $combine_hash = $combine_hash . '-' . $extend_field['openAddressId'];
                    $combine_idx  = $combine_idx . '-' . $extend_field['openAddressId'];
                }
                
                //京东厂直 工小达 暂未接合单
                if (kernel::single('ome_bill_label')->getBillLabelInfo($order_id, 'order', kernel::single('ome_bill_label')->isSomsGxd())) {
                    $combine_hash = $combine_hash . '-' . $order_bn;
                    $combine_idx  = $combine_idx . '-' . $order_bn;
                }
                break;
    
            default:
                $combine_hash = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod;
                $combine_idx = $uniqueness.'-'.$shop_id.'-'.$ship_name.'-'.$ship_mobile.'-'.$ship_area.'-'.$ship_addr.'-'.$is_cod;
                if ($extend_field['openAddressId']) {
                    $combine_hash = $combine_hash . '-' . $extend_field['openAddressId'];
                    $combine_idx  = $combine_idx . '-' . $extend_field['openAddressId'];
                }
                break;
        }
        
        //分销一件代发订单,不允许合单
        if($params['betc_id'] && $params['cos_id']){
            $combine_hash .= '-'. $order_bn;
            $combine_idx .= '-'. $order_bn;
        }
        
        // 如果jitx订单（包括省仓订单）有merged_code，combine_hash直接用merged_code
        $result['combine_hash'] = $use_vop_code ? $combine_hash : MD5($combine_hash);
        $result['combine_idx'] = CRC32($combine_idx);
        
        return $result;
    }
    
    //格式化新建订单items层数据
    public function format_order_items_data($item_type,$obj_number,$basicMInfos){
        $weight = 0;
        $order_items = array();
        foreach($basicMInfos as $basicMInfo){
            $current_order_items = array(
                "product_id" => $basicMInfo['bm_id'],
                "bn" => $basicMInfo['material_bn'],
                "name" => $basicMInfo['material_name'],
                "sendnum" => 0,
                "item_type" => $item_type,
            );
            switch($item_type){
                case "pkg": //促销
                    $current_order_items["price"] = $basicMInfo['rate_price'] ? bcdiv($basicMInfo['rate_price'], $basicMInfo['number']*$obj_number, 2) : 0.00;
                    $current_order_items["amount"] = $basicMInfo['rate_price'] ? $basicMInfo['rate_price'] : 0.00;
                    $current_order_items["sale_price"] = $basicMInfo['rate_price'] ? $basicMInfo['rate_price'] : 0.00;
                    $current_order_items["quantity"] = $basicMInfo['number']*$obj_number;
                    $weight += $basicMInfo['weight']*$basicMInfo['number']*$obj_number;
                    break;
                case "pko": //多选一
                    $current_order_items["price"] = $basicMInfo['price'];
                    $current_order_items["amount"] = $basicMInfo['price']*$basicMInfo['number'];
                    $current_order_items["sale_price"] = $basicMInfo['sale_price']*$basicMInfo['number'];
                    $current_order_items["quantity"] = $basicMInfo['number'];
                    $weight += $basicMInfo['weight']*$basicMInfo['number'];
                    break;
                case 'lkb':
                    //福袋组合
                    $current_order_items['price'] = $basicMInfo['price'];
                    $current_order_items['amount'] = $basicMInfo['price'] * $obj_number * $basicMInfo['number'];
                    $current_order_items['sale_price'] = $basicMInfo['price'] * $obj_number * $basicMInfo['number'];
                    $current_order_items['quantity'] = $basicMInfo['number'] * $obj_number;
                    
                    //weight
                    $weight += $basicMInfo['weight'] * $basicMInfo['number'] * $obj_number;
                    
                    //福袋组合ID
                    $luckybag_id = ($basicMInfo['combine_id'] ? $basicMInfo['combine_id'] : 0);
                    
                    //福袋规则
                    $current_order_items['luckybag_id'] = $luckybag_id; //福袋组合ID
                    
                    break;
                default: //赠品 普通销售物料
                    $current_order_items["price"] = $basicMInfo['price'];
                    $current_order_items["amount"] = $basicMInfo['price']*$obj_number;
                    $current_order_items["sale_price"] = $basicMInfo['sale_price']*$obj_number;
                    $current_order_items["quantity"] = $basicMInfo['number']*$obj_number;
                    $weight += $basicMInfo['weight']*$basicMInfo['number']*$obj_number;
                    break;
            }
            
            //优惠金额
            if($basicMInfo['pmt_price'] && $basicMInfo['pmt_price'] > 0){
                $current_order_items["pmt_price"] = $basicMInfo['pmt_price'];
            }
            
            $order_items[] = $current_order_items; //组order_items
        }
        return array("order_items" => $order_items,"weight" => $weight);
    }
    
    /**
     * 根据已拆分的发货单货品数量,判断订单的拆分状态
     *
     * @param int $order_id
     * @param string
     */
    function get_order_process_status($order_id)
    {
        $deliveryObj = app::get('ome')->model('delivery');
        
        //默认为"未确认"状态
        $process_status = 'unconfirmed';
        
        //获取已生成的发货单delivery_id
        $delivery_ids = $deliveryObj->getDeliverIdByOrderId($order_id);
        if($delivery_ids)
        {
            //订单明细货品总数量
            $sql = "SELECT sum(nums) AS nums FROM sdb_ome_order_items WHERE order_id=". $order_id ." AND `delete`='false'";
            $tempData = $deliveryObj->db->selectrow($sql);
            $orderItemNums = $tempData['nums'];
            
            //发货单货品总数量
            $sql = "SELECT sum(number) AS nums FROM sdb_ome_delivery_items WHERE delivery_id IN(". implode(',', $delivery_ids) .")";
            $tempData = $deliveryObj->db->selectrow($sql);
            $dlyItemNums = $tempData['nums'];
            
            if($orderItemNums == $dlyItemNums){
                $process_status = 'splited';
            }else{
                $process_status = 'splitting';
            }
        }
        
        return $process_status;
    }

    public function calculate_part_porth($data, $options, $dotNum = 2) {
        $part_total = $options['part_total']; #需要分摊的总额
        $part_field = $options['part_field']; #需要分摊的字段
        $porth_field = $options['porth_field']; #作为基数的字段
        $minuend_field = $options['minuend_field']; #被减的字段

        $porth_total = 0; #作为基数的总额
        foreach ($data as $k => $v) {
            if($v[$porth_field] > 0) {
                $porth_total = bcadd($porth_total, $v[$porth_field], 10);
            }
        }
        
        $part_left = $part_total;//分摊剩余
        foreach ($data as $k => $v) {
            if($v[$porth_field] > 0) {
                //使用四位小数,精度效果更高(原来是三位小数)
                $data[$k][$part_field] = bcmul(bcdiv($v[$porth_field], $porth_total, 10), $part_total, $dotNum);
                // $data[$k][$part_field] = bcmul( $v[$porth_field]/$porth_total, $part_total, $dotNum);

                $part_left = bcsub($part_left, $data[$k][$part_field], $dotNum);
            } else {
                $data[$k][$part_field] = 0;
            }
        }
        
        if($part_left) {
            foreach ($data as $k => $v) {
                if($v[$porth_field] > 0) {
                    $addValue = bcadd($part_left, $data[$k][$part_field], $dotNum);
                    if($minuend_field && $v[$minuend_field] > 0) {
                        if($v[$minuend_field] - $addValue < 0) {
                            if($v[$minuend_field] > $data[$k][$part_field]) {
                                $diff = bcsub($v[$minuend_field], $data[$k][$part_field], $dotNum);
                                $part_left = bcsub($part_left, $diff, $dotNum);
                                $data[$k][$part_field] = $v[$minuend_field];
                            }
                            continue;
                        }
                    }
                    $data[$k][$part_field] = $addValue;
                    break;
                }
            }
        }
        return $data;
    }
    
    /**
     * 重试推送上一小时内回传发货状态失败的订单
     */
    public function retry_push_order_delivery($filter=null, &$error_msg=null)
    {
        $orderObj = app::get('ome')->model('orders');
        
        //check
        if(empty($filter)){
            $error_msg = '没有查询条件';
            return false;
        }
        
        //回写发货失败的订单
        $tempList = $orderObj->getList('order_id,sync', $filter);
        if(empty($tempList)){
            $error_msg = '没有相关订单记录';
            return false;
        }
        
        $order_ids = array();
        foreach ($tempList as $key => $val)
        {
            $order_id = $val['order_id'];
            
            if(!in_array($val['sync'], array('run', 'fail'))){
                continue;
            }
            
            $order_ids[$order_id] = $order_id;
        }
        
        //check
        if(empty($order_ids)){
            $error_msg = '没有需要推送的记录';
            return false;
        }
        
        //request
        kernel::single('ome_event_trigger_shop_delivery')->delivery_confirm_retry($order_ids);
        
        return true;
    }
    
    /**
     * 获取支持指定仓库发货的平台
     *
     * @param int $order_id
     * @param string $error_msg
     * @return bool
     */
    public function getAssignBranchByShop()
    {
        $shop_types = array('luban');
        
        return $shop_types;
    }
    
    /**
     * 获取指定仓库发货
     *
     * @param int $order_id
     * @param string $error_msg
     * @return bool
     */
    public function getOrderAssignBranch($order_id, &$error_msg=null)
    {
        $orderObj = app::get('ome')->model('orders');
        $branchLib = kernel::single('ome_branch');
        
        //订单信息
        $orderInfo = $orderObj->dump($order_id, '*', array('order_objects'=>array('*',array('order_items'=>array('*')))));
        if(empty($orderInfo)){
            $error_msg = '没有获取到订单信息';
            return false;
        }
        
        //指定仓发货平台
        $assign_shop_types = $this->getAssignBranchByShop();
        $assgin_branchs = array();
        
        foreach($orderInfo['order_objects'] as $objKey => $object)
        {
            if ($object['delete'] == 'true') {
                continue;
            }

            $store_code = $object['store_code'];
            
            //获取发货仓库编码
            if(in_array($orderInfo['shop_type'], $assign_shop_types) && $store_code){
                $assgin_branchs[$store_code] = $store_code;
            }
        }
        
        //获取仓库信息
        $branchList = $branchLib->getBranchByBns($assgin_branchs);
        
        //没有可用的仓库,设置订单异常
        if(empty($branchList)){
            $error_msg = '没有获取到发货仓库编码';
            return false;
        }
        
        return $branchList;
    }
    
    /**
     * [抖音平台]获取指定区域仓关联OMS发货仓库编码
     */
    public function getOrderStoreCode($params)
    {
        $orderObj = app::get('ome')->model('orders');
        
        $warehouseList = $params['warehouseList'];
        $area_state = $params['area_state']; //省
        $area_city = $params['area_city']; //市
        $area_district = $params['area_district']; //区
        
        if(empty($warehouseList) || empty($area_state)){
            return false;
        }
        
        //过滤文字
        $area_state = str_replace(array('省', '市'), '', $area_state);
        
        //mapping
        $mapping = array();
        foreach ($warehouseList as $oid => $itemVal)
        {
            $out_warehouse_ids = $itemVal['out_warehouse_ids'];
            if(empty($out_warehouse_ids)){
                continue;
            }
            
            //平台只给一个区域仓,直接返回(后面会自动取第一个)
            if(count($out_warehouse_ids) == 1){
                continue;
            }
            
            foreach ($out_warehouse_ids as $outKey => $out_branch_bn)
            {
                //直接使用已匹配上的OMS仓库编码
                if($mapping[$out_branch_bn]){
                    $warehouseList[$oid]['store_code'] = $branchInfo['branch_bn']; //指定OMS发货仓库编码
                    break;
                }
                
                //获取京东开普勒区域仓编码
                $sql = "SELECT branch_id,branch_bn,region_names FROM sdb_logisticsmanager_warehouse WHERE branch_bn='". $out_branch_bn ."'";
                $branchInfo = $orderObj->db->selectrow($sql);
                if(empty($branchInfo)){
                    continue;
                }
                
                if(empty($branchInfo['region_names'])){
                    continue;
                }
                
                //覆盖区域
                $region_names = explode(',', $branchInfo['region_names']);
                if(in_array($area_state, $region_names)){
                    $mapping[$out_branch_bn] = $branchInfo['branch_bn']; //WMS区域仓编码
                    
                    $warehouseList[$oid]['store_code'] = $branchInfo['branch_bn']; //指定OMS发货仓库编码
                }
            }
        }
        
        return $warehouseList;
    }
    
    /**
     * 选择指定仓库编码
     * 
     * @param array $objects
     * @return array
     */
    public function assignOutWarehouse($sdf)
    {
        if(empty($sdf['order_objects'])){
            return $sdf;
        }
        
        $orderObj = app::get('ome')->model('orders');
        
        $tempList = explode('/', $sdf['consignee']['area']);
        if(empty($tempList)){
            return $sdf;
        }
        
        $area_state = $tempList[0]; //省
        $area_city = $tempList[1]; //市
        $area_district = $tempList[2]; //区
        
        //过滤文字
        $area_state = str_replace(array('省', '市'), '', $area_state);
        
        //objects
        $mapping = array();
        foreach($sdf['order_objects'] as $objKey => $object)
        {
            $oid = $object['oid'];
            
            //只有一个指定仓,不用验证覆盖区域
            if(count($object['out_warehouse_ids']) == 1){
                $sdf['order_objects'][$objKey]['store_code'] = $object['out_warehouse_ids'][0];
                
                continue;
            }
            
            //多个指定仓,验证覆盖区域
            $out_warehouse_ids = array();
            foreach ($object['out_warehouse_ids'] as $wareKey => $out_branch_bn)
            {
                //已验证过,直接取
                if(in_array($out_branch_bn, $mapping)){
                    $out_warehouse_ids[] = $out_branch_bn;
                    continue;
                }
                
                //获取京东开普勒区域仓编码
                $sql = "SELECT branch_id,branch_bn,region_names FROM sdb_logisticsmanager_warehouse WHERE branch_bn='". $out_branch_bn ."'";
                $branchInfo = $orderObj->db->selectrow($sql);
                if(empty($branchInfo['region_names'])){
                    continue;
                }
                
                //覆盖区域
                $region_names = explode(',', $branchInfo['region_names']);
                if(!in_array($area_state, $region_names)){
                    continue;
                }
                
                $out_warehouse_ids[] = $out_branch_bn;
                
                $mapping[] = $out_branch_bn;
            }
            
            //没有覆盖区域的指定仓,直接用第一个
            if(empty($out_warehouse_ids)){
                $sdf['order_objects'][$objKey]['store_code'] = $object['out_warehouse_ids'][0];
                
                continue;
            }
            
            //[验证库存]只有一个可用指定仓,不用验证库存
            if(count($out_warehouse_ids) == 1){
                $sdf['order_objects'][$objKey]['store_code'] = $out_warehouse_ids[0];
                
                continue;
            }
            
            if(empty($object['order_items'])){
                continue;
            }
            
            //[验证库存]多个指定仓,选择"就全满足库存"
            $out_warehouse_ids = $this->checkOutWarehouseStock($object['order_items'], $out_warehouse_ids);
            
            $sdf['order_objects'][$objKey]['store_code'] = $out_warehouse_ids[0];
        }
        
        return $sdf;
    }
    
    /**
     * 验证库存
     * 
     * @param unknown $itemList
     * @param unknown $out_warehouse_ids
     */
    public function checkOutWarehouseStock($itemList, $out_warehouse_ids)
    {
        $orderObj = app::get('ome')->model('orders');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        //获取指定仓库branch_id
        $sql = "SELECT branch_id,branch_bn FROM sdb_ome_branch WHERE branch_bn IN('". implode("','", $out_warehouse_ids) ."')";
        $tempList = $orderObj->db->select($sql);
        if(empty($tempList)){
            return false;
        }
        
        $branchList = array();
        foreach ($tempList as $key => $val)
        {
            $branch_bn = $val['branch_bn'];
            $branchList[$branch_bn] = $val['branch_id'];
        }
        
        //items
        foreach ($out_warehouse_ids as $key => $branch_bn)
        {
            $is_flag = true;
            foreach ($itemList as $itemKey => $itemVal)
            {
                $product_id = $itemVal['product_id'];
                $product_bn = trim($itemVal['bn']);
                $item_num = intval($itemVal['quantity']) - intval($itemVal['sendnum']);
                
                if($itemVal['delete'] != 'false'){
                    continue;
                }
                
                $branch_id = $branchList[$branch_bn];
                if(empty($branch_id)){
                    $is_flag = false;
                    continue;
                }
                
                //查询库存
                $sql = "SELECT product_id,branch_id,store FROM sdb_ome_branch_product WHERE product_id=". $product_id ." AND branch_id=".$branch_id;
                $storeInfo = $orderObj->db->selectrow($sql);
                if(empty($storeInfo)){
                    $is_flag = false;
                    continue;
                }
                
                if($storeInfo['store'] < $item_num){
                    $is_flag = false;
                    continue;
                }
                
                //根据仓库ID、基础物料ID获取该物料仓库级的预占
                $store_freeze = $basicMStockFreezeLib->getBranchFreeze($product_id, $branch_id);
                
                //可用库存
                $storeInfo['store'] = ($storeInfo['store'] < $store_freeze) ? 0 : ($storeInfo['store'] - $store_freeze);
                if($storeInfo['store'] < $item_num){
                    $is_flag = false;
                    continue;
                }
            }
            
            //验证成功
            if($is_flag){
                return array($branch_bn);
            }
        }
        
        //没有满足库存的指定仓,取第一个
        return array($out_warehouse_ids[0]);
    }
    
    /**
     * 获取订单上基础物料关联京东云交易渠道ID
     */
    public function getOrderProductChannelId($orderList, &$error_msg=null)
    {
        if(empty($orderList)){
            return $orderList;
        }
        
        //list
        foreach ($orderList as $order_id => $order)
        {
            $line_i = 0;
            $channel_id = '';
            foreach ($order['objects'] as $objKey => $objVal)
            {
                //每个订单,只检查第一个货品
                if($line_i > 1){
                    break;
                }
                
                foreach ($objVal['items'] as $itemKey => $itemVal)
                {
                    $product_bn = $itemVal['bn'];
                    
                    $line_i++;
                    
                    //每个订单,只检查第一个货品
                    if($line_i == 1){
                        $channel_id = $this->getProductChannelId($product_bn, $error_msg);
                        
                        //等于空：说明基础物料没有关联京东云交易渠道ID
                        if($channel_id == 'empty'){
                            continue;
                        }
                        
                        //有关联渠道,但有异常信息
                        if($channel_id === false){
                            $error_msg = '订单号：'.$order['order_bn'].','.$error_msg;
                            return false;
                        }
                    }else{
                        break;
                    }
                }
            }
            
            //设置发货的渠道ID(不能等于empty)
            if($channel_id && $channel_id != 'empty'){
                $order['channel_id'] = $channel_id;
                $orderList[$order_id] = $order;
            }
        }
        
        return $orderList;
    }
    
    /**
     * 获取指定商品对应的渠道ID
     */
    public function getProductChannelId($product_bn, &$error_msg=null)
    {
        $orderObj = app::get('ome')->model('orders');
        
        //获取上架状态：只拿最新2条数据
        $sql = "SELECT channel_id,approve_status FROM sdb_material_basic_material_channel ";
        $sql .= " WHERE material_bn='". $product_bn ."' ORDER BY approve_status,id DESC LIMIT 0,2";
        $channelList = $orderObj->db->select($sql);
        if(empty($channelList)){
            return 'empty';
        }
        
        //检查商品关联的渠道ID
        $channel_id = 0;
        $channel_count = 0;
        foreach ($channelList as $key => $val)
        {
            $channel_id = $val['channel_id'];
            
            //上架状态
            if($val['approve_status']=='1'){
                $channel_count++;
            }
        }
        
        //没有上架渠道
        if($channel_count == 0){
            $error_msg = $product_bn.':没有渠道上架;';
            return false;
        }
        
        //商品在多个渠道上架
        if($channel_count > 1){
            $error_msg = $product_bn.':在多个渠道上架;';
            return false;
        }
        
        return $channel_id;
    }

    function presale_paymentadd($sdf){
        $orderObj = app::get('ome')->model('orders');
        $presaleconf = app::get('ome')->getConf('ome.order.presale');
        $presalesetting = app::get('ome')->getConf('ome.order.presalemoney');
        if ($presaleconf == '1'){//加判断当是预售且开启预售配置时才存扩展表
            $oObj_orextend = app::get('ome')->model("order_extend");
            $code_data = array('order_id'=>$sdf['order_id'],'presale_pay_status'=>'1','presale_auto_paid'=>$sdf['payed']);
            $oObj_orextend->save($code_data);
        }
        if ($presalesetting =='1' && $presaleconf =='1'){

            /*
            $paid_feedata = array(
                'shop_id'=>$sdf['shop_id'],
                'order_id'=>$sdf['order_id'],
                'money'=>$sdf['payed'],
                'memo'=>'系统自动生成定金付款单',
            );
            $this->_paymentadd($paid_feedata);
            $money=0;
            $money = ome_func::number_math(array($sdf['total_amount'],$sdf['payed']),'-',3);
            $end_feedata = array(
                'shop_id'=>$sdf['shop_id'],
                'order_id'=>$sdf['order_id'],
                'money'=>$money,
                'memo'=>'系统自动生成尾款付款单',
            );
            $result = $this->_paymentadd($end_feedata);
            if ($result){
                $oObj_orextend->db->exec("UPDATE sdb_ome_orders SET payed=payed+".$money." WHERE order_id=".$sdf['order_id']);
            }

            $order_detail = $orderObj->dump($sdf['order_id'],'order_bn,payed,total_amount,shop_type, confirm, process_status, ship_status,source,is_cod');
            $payed = strval($order_detail['payed']);
            $total_amount = strval($order_detail['total_amount']);
            if ($payed>0 && $payed < $total_amount)
            {
                $pay_status = '3';
            }else if( $payed>0 && $payed >= $total_amount){
                $pay_status = '1';
            }
            if ($pay_status){
                $orderObj->db->exec("UPDATE sdb_ome_orders SET pay_status='".$pay_status."' WHERE order_id=".$sdf['order_id']);

            }
            */
        }

    }

    function _paymentadd($data){
        $pay_time = time();
        $paymentObj = app::get('ome')->model('payments');
        $sdf = array(
        'payment_bn' => $paymentObj->gen_id(),
        'shop_id' => $data['shop_id'],
        'order_id' => $data['order_id'],
        'currency' => 'CNY',
        'money' => $data['money'],
        'paycost' =>0,
        'cur_money' => 0,
        'pay_type' => 'online',
        't_begin' => $pay_time,
        'download_time' => $pay_time,
        't_end' => $pay_time,
        'status' => 'succ',
        'memo'=>$data['memo'],
        'op_id'=> 16777215,
        );

        return $paymentObj->save($sdf);

    }
    
    /**
     * [兼容]重试推送回传平台发货状态是"发货中"的订单
     */
    public function push_sync_delivery_confirm()
    {
        $orderObj = app::get('ome')->model('orders');
        $returnObj = app::get('ome')->model('return_product');
        
        //获取"发货中"的订单
        $sql = "SELECT order_id,pay_status,createway FROM sdb_ome_orders WHERE ship_status='1' AND sync='run' AND createway='matrix' LIMIT 0, 200";
        $dataList = $orderObj->db->select($sql);
        if(empty($dataList)){
            return true;
        }
        
        //orderIds
        $orderIds = array_column($dataList, 'order_id');
        
        //获取售后列表
        $returnList = $returnObj->getList('return_id,order_id', array('order_id'=>$orderIds));
        if($returnList){
            $returnList = array_column($returnList, null, 'order_id');
        }
        
        //order_ids
        $order_ids = array();
        foreach ($dataList as $key => $val)
        {
            $order_id = $val['order_id'];
            
            //check
            if($val['createway'] != 'matrix'){
                continue;
            }
            
            //过滤已经有售后单的订单
            if($returnList[$order_id]){
                continue;
            }
            
            $order_ids[] = $order_id;
        }
        
        if($order_ids){
            kernel::single('ome_event_trigger_shop_delivery')->delivery_confirm_retry($order_ids);
        }
        
        return true;
    }
    
    /**
     * 虚拟商品拆单生成发货单,自动完成发货
     *
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return bool
     */
    public function auto_delivery(&$cursor_id, $params, &$error_msg= null)
    {
        $orderObj = app::get('ome')->model('orders');
        $deliveryObj = app::get('ome')->model('delivery');
        $wmsDlyObj = app::get('wms')->model('delivery');
        
        $branchLib = kernel::single('ome_branch');
        $channelLib = kernel::single('channel_func');
        
        //data
        $data = $params['sdfdata'];
        $order_id = $data['order_id'];
        if(empty($order_id)){
            $error_msg = '没有订单信息';
            return false;
        }
        
        //关联发货单(获取未发货的发货单,合并发货单只读取父发货单)
        $sql = "SELECT b.delivery_id,b.delivery_bn FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id ";
        $sql .= " WHERE a.order_id=". $order_id ." AND b.status IN('progress', 'ready') AND parent_id=0";
        $dataList = $deliveryObj->db->select($sql);
        if(empty($dataList)){
            $error_msg = '没有发货单信息';
            return false;
        }
        
        $delivery_ids = array();
        foreach ($dataList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            
            //发货单明细
            $tempList = $deliveryObj->db->select("SELECT product_id FROM sdb_ome_delivery_items WHERE delivery_id=".$delivery_id);
            if(empty($tempList)){
                continue;
            }
            
            $product_ids = array();
            foreach ($tempList as $tempKey => $tempVal)
            {
                $product_ids[] = $tempVal['product_id'];
            }
            
            //判断是否虚拟商品
            $sel_sql = "SELECT bm_id FROM sdb_material_basic_material WHERE bm_id IN(". implode(',', $product_ids) .") AND type != '5'";
            $isCheck = $deliveryObj->db->selectrow($sel_sql);
            if($isCheck){
                continue; //有明细不是虚拟商品,直接跳过
            }
            
            $delivery_ids[] = $delivery_id;
        }
        
        if(empty($delivery_ids)){
            $error_msg = '没有找到虚拟货品发货单';
            return false;
        }
        
        //获取虚拟发货单(现在一个订单只会有一个虚拟发货单)
        $deliveryInfo = $deliveryObj->dump(array('delivery_id'=>$delivery_ids), 'delivery_id,delivery_bn,net_weight,branch_id,logi_id,logi_no,logi_name');
        $delivery_id = $deliveryInfo['delivery_id'];
        if(empty($delivery_id)){
            $error_msg = '没有找到关联发货单号';
            return false;
        }
        
        $branch_id = $deliveryInfo['branch_id'];
        if(empty($branch_id)){
            $error_msg = '发货单没有关联仓库';
            return false;
        }
        
        //订单对应发货单(合并发货单对应多个订单号)
        $sql = "SELECT b.order_id FROM sdb_ome_delivery AS a LEFT JOIN sdb_ome_delivery_order AS b ON (a.delivery_id=b.delivery_id)
                WHERE a.delivery_id=".$delivery_id." AND (a.parent_id=0 OR a.is_bind='true') AND a.disabled='false' AND a.status='ready'";
        $tempList = $deliveryObj->db->select($sql);
        if(empty($tempList)){
            $error_msg = '没有找到关联订单号';
            return false;
        }
        
        $order_ids = array();
        foreach ($tempList as $key => $val)
        {
            $order_ids[] = $val['order_id'];
        }
        
        // 物流公司ID
        $logi_id = ($deliveryInfo['logi_id'] ? $deliveryInfo['logi_id'] : 1);
        
        // 获取物流公司
        if(empty($deliveryInfo['logi_no'])){
            // 优先获取虚拟物流公司
            $sql = "SELECT * FROM sdb_ome_dly_corp WHERE `type`='virtual_delivery'";
            $corpInfo = $deliveryObj->db->selectrow($sql);
            
            // 获取发货单上指定的物流公司
            if(empty($corpInfo) && $deliveryInfo['logi_id']){
                $sql = "SELECT * FROM sdb_ome_dly_corp WHERE corp_id=". $deliveryInfo['logi_id'];
                $corpInfo = $deliveryObj->db->selectrow($sql);
            }
            
            // 默认读取仓库关联的第一个物流公司
            if(empty($corpInfo)){
                $sql = "SELECT a.*, b.name FROM sdb_ome_branch_corp AS a LEFT JOIN sdb_ome_dly_corp AS b ON a.corp_id=b.corp_id WHERE a.branch_id=". $branch_id;
                $corpInfo = $deliveryObj->db->selectrow($sql);
            }
            
            // 获取第一个物流公司
            if(empty($corpInfo)){
                $sql = "SELECT * FROM `sdb_ome_dly_corp` WHERE `type` NOT IN('o2o_pickup', 'o2o_ship')";
                $corpInfo = $deliveryObj->db->selectrow($sql);
            }
            
            //物流公司ID
            $logi_id = ($corpInfo['corp_id'] ? $corpInfo['corp_id'] : 1);
        }
        
        // 获取物流公司信息
        $sql = "SELECT * FROM sdb_ome_dly_corp WHERE corp_id=". $logi_id;
        $corpInfo = $deliveryObj->db->selectrow($sql);
        
        // 生成唯一性的物流单号
        $logi_no = 'V'.uniqid();
        
        // 物流公司名称
        $logi_name = ($corpInfo['name'] ? $corpInfo['name'] : '默认物流公司');
        
        //默认使用_商品重量
        $weight = ($deliveryInfo['net_weight'] ? $deliveryInfo['net_weight'] : 0);
        $weight = floatval($weight);
        
        //开启事务
        $deliveryObj->db->beginTransaction();
        
        // 更新ome发货单单物流信息和打印状态
        $sql = "UPDATE sdb_ome_delivery SET stock_status='true',deliv_status='true',expre_status='true',verify='true',weight='". $weight ."'";
        $sql .= ",print_status=1,logi_id=". $logi_id .",logi_name='". $logi_name ."'";
        
        // logi_no
        if(empty($deliveryInfo['logi_no'])){
            $sql .= ", logi_no='". $logi_no ."'";
        }
        
        $sql .= " WHERE delivery_id=". $delivery_id;
        $deliveryObj->db->exec($sql);
        
        //更新订单打印状态
        foreach ($order_ids as $ordKey => $order_id)
        {
            $update_order = array();
            $update_order['order_id'] = $order_id;
            $update_order['print_finish'] = 'true';
            $update_order['print_status'] = 7;
            $update_order['logi_id'] = $logi_id;
            $update_order['logi_no'] = $logi_no;
            
            $orderObj->save($update_order);
        }
        
        //是否自有仓储
        $wms_id = $branchLib->getWmsIdById($branch_id);
        $is_selfWms = $channelLib->isSelfWms($wms_id);
        
        // 自有仓储发货单信息
        $wmsDlyInfo = [];
        if($is_selfWms){
            $wmsDlyInfo = $wmsDlyObj->dump(array('outer_delivery_bn'=>$deliveryInfo['delivery_bn']), 'delivery_id');
        }
        
        // 自动发货流程
        if($wmsDlyInfo){
            // 更新wms发货单打印状态物流信息
            $dlyData = array();
            $dlyData['logi_id'] = $logi_id;
            $dlyData['logi_name'] = $logi_name;
            $dlyData['weight'] = $weight;
            $dlyData['print_status'] = 7;//已打印
            
            // update wms_delivery
            $wmsDlyObj->update($dlyData, array('delivery_id'=>$wmsDlyInfo['delivery_id']));
            
            // update wms_delivery_bill
            $sql = "UPDATE sdb_wms_delivery_bill SET logi_no='". $logi_no ."' WHERE delivery_id=". $wmsDlyInfo['delivery_id'];
            $deliveryObj->db->exec($sql);
            
            //触发wms自动发货流程
            foreach ($order_ids as $ordKey => $order_id)
            {
                $result = $this->wmsConsignDelivery($order_id, $logi_no, $weight, $error_msg);
                if(!$result){
                    //回滚事务
                    $deliveryObj->db->rollBack();
                    
                    $error_msg = 'WMS发货单发货失败：'. $error_msg;
                    return false;
                }
            }
        }else{
            // 模拟response自动响应发货
            $data = array(
                'delivery_bn' => $deliveryInfo['delivery_bn'],
                'delivery_time' => time(),
                'weight' => $weight,
                'delivery_cost_actual' => 0,
                'status' => 'delivery',
                'logistics' => $logi_id,
                'logi_no' => $logi_no,
            );
            $result = kernel::single('erpapi_router_response')->set_channel_id($wms_id)->set_api_name('wms.delivery.status_update')->dispatch($data);
            if($result['rsp'] == 'fail'){
                //回滚事务
                $orderObj->db->rollBack();
                
                $error_msg = '发货单自动发货失败：'. ($result['msg'] ? $result['msg'] : $result['error_msg']);
                return false;
            }
        }
        
        //事务提交
        $deliveryObj->db->commit();
        
        //注销
        unset($deliveryInfo, $tempInfo, $update_order);
        
        return false;
    }
    
    /**
     * WMS自动发货
     *
     * @param intval $order_id
     * @param string $logi_no
     * @param number $weight
     * @return boolean
     */
    function wmsConsignDelivery($order_id, $logi_no, $weight, &$error_msg=null)
    {
        if(empty($order_id) || empty($logi_no)){
            $error_msg = '订单ID或者物流单号不能为空';
            return false;
        }
        
        $dlyObj = app::get('wms')->model('delivery');
        $dlyBillObj = app::get('wms')->model('delivery_bill');
        $deliveryBillLib = kernel::single('wms_delivery_bill');
        $opObj = app::get('ome')->model('operation_log');
        
        $wmsCommonLib = kernel::single('wms_common');
        $dlyProcessLib = kernel::single('wms_delivery_process');
        
        //如果没有发货单信息，则根据物流单号识别是主单还是次单,并获取相关信息
        $delivery_id = $deliveryBillLib->getDeliveryIdByPrimaryLogi($logi_no);
        if(empty($delivery_id)){
            $opObj->write_log('order_edit@ome', $order_id, '自动发货失败：未找到对应发货单号');
            
            $error_msg = '自动发货失败：未找到对应发货单号';
            return false;
        }
        
        $dly = $dlyObj->dump(array('delivery_id' => $delivery_id),'*',array('delivery_items'=>array('*')));
        if(empty($dly)){
            $opObj->write_log('order_edit@ome', $order_id, '自动发货失败：未找到发货单信息');
            
            $error_msg = '自动发货失败：未找到发货单信息';
            return false;
        }
        
        $delivery_logi_number = $dly['delivery_logi_number']+1;
        
        //获取物流费用
        $area = $dly['consignee']['area'];
        $arrArea = explode(':', $area);
        $area_id = $arrArea[2];
        $delivery_cost_actual = $wmsCommonLib->getDeliveryFreight($area_id, $dly['logi_id'], $weight);
        
        //更新bill发货单
        $data = array(
            'status' => '1',
            'weight' => $weight,
            'delivery_cost_actual' => $delivery_cost_actual,
            'delivery_time' => time(),
            'type' => 1,
        );
        $dlyBillObj->update($data, array('logi_no'=>$logi_no));
        
        //更新wms发货单
        $dly['delivery_cost_actual'] += $delivery_cost_actual;
        $data = array(
            'delivery_logi_number' => $delivery_logi_number,
            'weight' => $dly['weight'],
            'delivery_cost_actual' => $dly['delivery_cost_actual'],
        );
        $dlyObj->update($data, array('delivery_id'=>$dly['delivery_id']));
        
        //发货
        if($dlyProcessLib->consignDelivery($dly['delivery_id'])){
            $opObj->write_log('order_edit@ome', $order_id, '订单发货完成!');
            return true;
        }else {
            $opObj->write_log('order_edit@ome', $order_id, '发货失败,发货单:'.$dly['delivery_bn']);
            $error_msg = '发货失败,发货单:'.$dly['delivery_bn'];
            return false;
        }
    }
    
    /**
     * 订单是否拆单
     * 
     * @param int $order_id
     * @return boolean
     */
    public function is_split_order($order_id)
    {
        $orderMdl = app::get('ome')->model('orders');
        
        $orderInfo = $orderMdl->dump(array('order_id'=>$order_id), '*');
        
        //如果存在未发货订单
        if ($orderInfo['ship_status'] == '2' || in_array($orderInfo['process_status'], array('remain_cancel', 'splitting'))){
            return true;
        }
        
        //订单关联的发货单数量
        $sql = "SELECT d.delivery_id,d.status,d.delivery_time FROM sdb_ome_delivery_order AS do 
                LEFT JOIN sdb_ome_delivery AS d ON(do.delivery_id=d.delivery_id) 
                WHERE do.order_id='". $order_id ."' AND d.parent_id='0' AND d.disabled='false' AND d.status IN('succ','progress','ready')";
        $rows = $orderMdl->db->select($sql);
        if(count($rows) > 1){
            return true;
        }
        
        return false;
    }

    /**
     * 标签是否可以审单、发货
     * 
     * @param array $label_code 标签代码
     * @param String $bill_type 单据类型
     * @return boolean
     */
    public function canDeliveryFromBillLabel($label_code, $bill_type = 'order')
    {
        if (!$label_code) {
            return false;
        }
        is_string($label_code) && $label_code = [$label_code];
        if ($bill_type == 'order') {
            foreach ($label_code as $value) {
                if (in_array($value, ['use_before_payed'])) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * 补发订单复制原订单收件人敏感数据
     *
     * @param $orderInfo
     * @param $error_msg
     * @return false|void
     */
    public function createBufaOrderEncrypt($orderInfo, &$error_msg=null)
    {
        $orderObj = app::get('ome')->model('orders');
        $receiverObj = app::get('ome')->model('order_receiver');
        
        //check
        if(!in_array($orderInfo['order_type'], array('bufa'))){
            $error_msg = '不是补发订单类型';
            return false;
        }
        
        if(empty($orderInfo['relate_order_bn']) || empty($orderInfo['order_id'])){
            $error_msg = '没有关联订单号';
            return false;
        }
        
        //原平台订单
        $originalInfo = $orderObj->dump(array('order_bn'=>$orderInfo['relate_order_bn']), 'order_id,order_bn,createway,relate_order_bn');
        if(empty($originalInfo)){
            $error_msg = '没有原平台订单信息';
            return false;
        }
        
        //售后换货生成的新订单
        if($originalInfo['createway'] != 'matrix' && $originalInfo['relate_order_bn']){
            for ($i=0; $i<10; $i++)
            {
                $tempOrderInfo = $orderObj->dump(array('order_bn'=>$originalInfo['relate_order_bn']), 'order_id,order_bn,createway,relate_order_bn');
                if(empty($tempOrderInfo)){
                    break;
                }
                
                //父订单
                $originalInfo = $tempOrderInfo;
                
                //已找到原始平台订单,跳出
                if($tempOrderInfo['createway'] == 'matrix'){
                    break;
                }
                
                //没有关联订单
                if(empty($tempOrderInfo['relate_order_bn'])){
                    break;
                }
            }
        }
        
        //敏感数据信息
        $receiverInfo = $receiverObj->dump(array('order_id'=>$originalInfo['order_id']), '*');
        if(empty($receiverInfo)){
            $error_msg = '原订单号：'. $originalInfo['order_bn'] .'没有加密敏感数据';
            return false;
        }
        
        //order_id
        $receiverInfo['order_id'] = $orderInfo['order_id'];
        $receiverObj->save($receiverInfo);
        
        //msg
        $log_msg = '复制订单号：'. $orderInfo['relate_order_bn'] .'收件人敏感信息；';
        
        //更新关联订单
        if($orderInfo['relate_order_bn'] != $originalInfo['order_bn']){
            $orderObj->update(array('relate_order_bn'=>$originalInfo['order_bn']), array('order_id'=>$orderInfo['order_id']));
            $log_msg .= '并更新为平台订单号：'. $originalInfo['order_bn'];
        }
        
        //logs
//        $logObj = app::get('ome')->model('operation_log');
//        $logObj->write_log('order_modify@ome', $orderInfo['order_id'], $log_msg);
        
        return true;
    }
    
    /**
     * 通过订单item层明细ID,获取订单object层信息
     *
     * @param $itemIds
     * @return array
     */
    public function getOrderItemByItemIds($itemIds)
    {
        $orderItemMdl = app::get('ome')->model('order_items');
        $orderObjMdl = app::get('ome')->model('order_objects');
        $combineMdl = app::get('material')->model('fukubukuro_combine');
        
        //order_items
        $tempItems = $orderItemMdl->getList('item_id,order_id,obj_id,item_type,bn,nums,luckybag_id', array('item_id'=>$itemIds));
        if(empty($tempItems)){
            return array();
        }
        
        //order_objects
        $objIds = array_column($tempItems, 'obj_id');
        $objectList = $orderObjMdl->getList('obj_id,order_id,obj_type,bn,quantity', array('obj_id'=>$objIds));
        if(empty($objectList)){
            return array();
        }
        $objectList = array_column($objectList, null, 'obj_id');
        
        //luckybag_id
        $combineIds = array_column($tempItems, 'luckybag_id');
        
        //fukubukuro_combine
        $combineList = array();
        if($combineIds){
            $combineList = $combineMdl->getList('combine_id,combine_bn', array('combine_id'=>$combineIds), 0, -1);
            $combineList = array_column($combineList, null, 'combine_id');
        }
        
        //format
        $orderItemList = array();
        foreach ((array)$tempItems as $tempKey => $tempVal)
        {
            $item_id = $tempVal['item_id'];
            $obj_id = $tempVal['obj_id'];
            $luckybag_id = $tempVal['luckybag_id'];
            
            //order_objects
            $objInfo = $objectList[$obj_id];
            
            //merge
            $tempVal['obj_type'] = $objInfo['obj_type'];
            $tempVal['sales_material_bn'] = $objInfo['bn'];
            $tempVal['quantity'] = $objInfo['quantity'];
            
            //福袋组合编码
            if(isset($combineList[$luckybag_id])){
                $tempVal['combine_bn'] = $combineList[$luckybag_id]['combine_bn'];
            }
            
            $orderItemList[$item_id] = $tempVal;
        }
        
        //unset
        unset($tempItems, $objectList);
        
        return $orderItemList;
    }
    
    /**
     * 根据订单object层实付金额获取items层明细对应的金额占比
     *
     * @param array $orderObjInfo
     * @param int $decimal 小数位,默认保留5位小数
     * @return void
     */
    public function getItemRateByObject($orderObjInfo, $decimal=5)
    {
        //check
        if(empty($orderObjInfo) || empty($orderObjInfo['order_items'])){
            return array(false, []);
        }
        if($orderObjInfo['divide_order_fee'] <= 0){
            return array(false, []);
        }
        
        //商品实付金额
        $divide_order_fee = $orderObjInfo['divide_order_fee'];
        
        //items
        $itemRates = array();
        foreach ($orderObjInfo['order_items'] as $itemKey => $itemVal)
        {
            $item_id = $itemVal['item_id'];
            $item_amount = $itemVal['divide_order_fee'];
            if($item_amount <= 0){
                $itemRates[$item_id] = 0;
                continue;
            }
            //实付金额贡献占比(保留四位小数)
            $rate = bcdiv($item_amount, $divide_order_fee, $decimal);
            
            $itemRates[$item_id] = $rate;
        }
        if(array_sum($itemRates) == 0){
            return array(false, []);
        }
        return array(true, $itemRates);
    }
    
    /**
     * 通过order_bn获取根订单信息
     *
     * @param array $orderInfo 主要使用order_bn订单号
     * @return void
     */
    public function getRootOrderInfo($orderInfo)
    {
        $orderMdl = app::get('ome')->model('orders');
        $archiveOrderMdl = app::get('archive')->model('orders');
        
        $order_bn = $orderInfo['order_bn'];
        
        //check
        if(empty($order_bn)){
            return false;
        }
        
        //根订单信息
        $rootOrderInfo = [];
        
        //while
        $line_i = 0;
        $is_while = true;
        do {
            $line_i++;
            
            $orderInfo = $orderMdl->db_dump(array('order_bn'=>$order_bn), 'order_id,order_bn,platform_order_bn,relate_order_bn');
            
            //归档订单信息
            if(empty($orderInfo)){
                $orderInfo = $archiveOrderMdl->db_dump(array('order_bn'=>$order_bn), '*');
            }
            
            //check
            if(empty($orderInfo)){
                break;
            }
            
            $rootOrderInfo = $orderInfo;
            
            //platform_order_bn
            if($orderInfo['platform_order_bn']){
                $order_bn = $orderInfo['platform_order_bn'];
                
                //平台订单号与订单号相同,则跳出
                if($orderInfo['platform_order_bn'] == $orderInfo['order_bn']){
                    break;
                }
            }elseif($orderInfo['relate_order_bn']){
                //继续使用关联订单号继续查找
                $order_bn = $orderInfo['relate_order_bn'];
            }else{
                break;
            }
            
            //防止有未想到的场景，导致死循环
            if($line_i >= 6){
                $is_while = false;
            }
        } while($is_while === true);
        
        //root_order_bn
        if($rootOrderInfo['platform_order_bn']){
            $root_order_bn = $rootOrderInfo['platform_order_bn'];
        }elseif($rootOrderInfo['relate_order_bn']){
            $root_order_bn = $rootOrderInfo['relate_order_bn'];
        }else{
            $root_order_bn = $rootOrderInfo['order_bn'];
        }
        $rootOrderInfo['root_order_bn'] =$root_order_bn;
        
        return $rootOrderInfo;
    }


    /**
     * 获取预售订单过滤条件
     *
     * @param Array $order
     * @return Array
     */
    public function _getpresaleCombine(&$presaleCombine){
        $presalesetting = app::get('ome')->getConf('ome.order.presale');
        $presaleconf = app::get('ome')->getConf('ome.order.presale.combine');

        if($presaleconf == '1'){
            $presaleCombine = true;
        }else {
            $presaleCombine = false;
        }
    }
    
    /**
     * 获取订单解密信息
     *
     * @param $sdf
     * @return void
     */
    public function getEncryptOriginData(&$sdf)
    {
        if(kernel::single('ome_security_router',$sdf['shop_type'])->is_encrypt($sdf,'delivery')) {
            $encryptData = [
                'shop_id' => $sdf['shop_id'],
                'order_bn' => current(explode('|', $sdf['order_bn'])),
                'ship_name' => $sdf['consignee']['name'],
                'ship_tel' => $sdf['consignee']['telephone'],
                'ship_mobile' => $sdf['consignee']['mobile'],
                'ship_addr' => $sdf['consignee']['addr'],
            ];
            $originalEncrypt = kernel::single('ome_security_router',$sdf['shop_type'])->get_encrypt_origin($encryptData, 'delivery');
            
            if($originalEncrypt['ship_name']) $sdf['consignee']['name'] = $originalEncrypt['ship_name'];
            if($originalEncrypt['ship_tel']) $sdf['consignee']['telephone'] = $originalEncrypt['ship_tel'];
            if($originalEncrypt['ship_mobile']) $sdf['consignee']['mobile'] = $originalEncrypt['ship_mobile'];
            if($originalEncrypt['ship_addr']) $sdf['consignee']['addr'] = $originalEncrypt['ship_addr'];
            
            // 标识此订单为平台加密订单
            if ($originalEncrypt){
                $sdf['platform_encrypt'] = true;
            }
        }
    }
    
    /**
     * 处理SET商品(pkg类型)固定价销售价计算
     *
     * @param array $order 订单数据
     * @return array 处理后的订单数据
     */
    public function processSetFixedPrice($order)
    {
        // 获取SET商品固定价信息
        $fixedPriceData = $this->getSetFixedPriceData($order);
        if (empty($fixedPriceData)) {
            return $order;
        }
        
        // 获取销售物料和基础物料占比
        $smBmRate = $this->getSmBmRate($order);
        
        // order_objects
        foreach ($order['order_objects'] as $k => $object)
        {
            $goods_id = $object['goods_id'];
            
            // check pkg
            if(!in_array($object['obj_type'], ['pkg'])){
                continue;
            }
            
            // check rate
            if (empty($smBmRate[$goods_id])) {
                continue;
            }
            
            // item
            $tmpOrderItems = $object['order_items'];
            
            $remainingSalePrice = $object['sale_price'];
            $remain_divide_order_fee = $object['divide_order_fee'];
            $remain_amount = $object['amount'];
            $remain_actually_amount = $object['actually_amount']; // 客户实付
            $remain_settlement_amount = $object['settlement_amount']; // 结算金额
            
            $fixedObjSalePrice = $object['sale_price'];
            $remainingItems = [];
            $remainingRatio = 0;
            
            // 第一遍：处理有固定价的基础物料
            foreach ($tmpOrderItems as $i => $item)
            {
                $product_id = $item['product_id'];
                
                // check
                if (empty($product_id) || $product_id < 1) {
                    continue;
                }
                
                // 如果有固定价，直接计算销售价
                if (isset($fixedPriceData[$goods_id][$product_id]) && $fixedPriceData[$goods_id][$product_id]['fixed_price'] >= 0) {
                    $fixedPrice = $fixedPriceData[$goods_id][$product_id]['fixed_price'];
                    
                    // sale_price
                    $itemSalePrice = $fixedPrice * $item['quantity'];
                    
                    // format
                    if($fixedObjSalePrice < 0){
                        $itemSalePrice = 0;
                    }elseif($fixedObjSalePrice < $itemSalePrice){
                        $itemSalePrice = $fixedObjSalePrice;
                    }
                    
                    // pmt_price
                    $pmt_price = 0;
                    
                    // amount
                    $item_amount = bcadd($pmt_price, $itemSalePrice, 2);
                    
                    // 剩余销售价
                    $remainingSalePrice -= $itemSalePrice;
                    $fixedObjSalePrice -= $itemSalePrice;
                    
                    // 剩余实付金额
                    $remain_divide_order_fee -= $itemSalePrice;
                    $remain_amount -= $item_amount;
                    $remain_actually_amount -= $itemSalePrice;
                    $remain_settlement_amount -= $itemSalePrice;
                    
                    // 设置固定价计算的销售价
                    $tmpOrderItems[$i]['price'] = $fixedPrice;
                    $tmpOrderItems[$i]['pmt_price'] = $pmt_price;
                    $tmpOrderItems[$i]['part_mjz_discount'] = 0;
                    $tmpOrderItems[$i]['amount'] = $item_amount;
                    $tmpOrderItems[$i]['sale_price'] = $itemSalePrice;
                    $tmpOrderItems[$i]['divide_order_fee'] = $itemSalePrice;
                    $tmpOrderItems[$i]['actually_amount'] = $itemSalePrice; // 客户实付
                    $tmpOrderItems[$i]['settlement_amount'] = $itemSalePrice; // 结算金额
                    $tmpOrderItems[$i]['porth_field'] = 0; // 固定价不需要贡献比
                    
                    // flag
                    $tmpOrderItems[$i]['is_fixed_price'] = 'true';
                    $tmpOrderItems[$i]['item_fixed_price'] = $fixedPrice;
                } else {
                    // 设置固定的金额
                    $tmpOrderItems[$i]['pmt_price'] = 0;
                    $tmpOrderItems[$i]['amount'] = 0;
                    $tmpOrderItems[$i]['sale_price'] = 0;
                    $tmpOrderItems[$i]['divide_order_fee'] = 0;
                    $tmpOrderItems[$i]['actually_amount'] = 0;
                    $tmpOrderItems[$i]['settlement_amount'] = 0;
                    
                    // flag
                    $tmpOrderItems[$i]['is_fixed_price'] = 'false';
                    $tmpOrderItems[$i]['item_fixed_price'] = '';
                    
                    // 收集没有固定价的基础物料
                    $remainingItems[$i] = $tmpOrderItems[$i];
                    $remainingRatio += $smBmRate[$goods_id][$product_id];
                }
            }
            
            // 第二遍：按贡献比分配剩余销售价
            if ($remainingSalePrice > 0 && $remainingRatio > 0) {
                foreach ($remainingItems as $i => $item)
                {
                    $product_id = $item['product_id'];
                    $bm_ratio = $smBmRate[$goods_id][$product_id];
                    
                    // 按贡献比计算销售价
                    $itemSalePrice = ($bm_ratio / $remainingRatio) * $remainingSalePrice;
                    
                    // 按贡献比计算实付金额
                    $item_divide_order_fee = ($bm_ratio / $remainingRatio) * $remain_divide_order_fee;
                    
                    // 总金额
                    $itemAmount = ($bm_ratio / $remainingRatio) * $remain_amount;
                    $itemActuallyAmount = ($bm_ratio / $remainingRatio) * $remain_actually_amount;
                    $itemSettlementAmount = ($bm_ratio / $remainingRatio) * $remain_settlement_amount;
                    
                    // price
                    $price = bcdiv($itemSalePrice, $item['quantity'], 2);
                    
                    // format
                    $tmpOrderItems[$i]['price'] = $price;
                    $tmpOrderItems[$i]['amount'] = $itemAmount;
                    $tmpOrderItems[$i]['divide_order_fee'] = $item_divide_order_fee;
                    $tmpOrderItems[$i]['sale_price'] = $itemSalePrice;
                    $tmpOrderItems[$i]['actually_amount'] = $itemActuallyAmount;
                    $tmpOrderItems[$i]['settlement_amount'] = $itemSettlementAmount;
                    
                    $tmpOrderItems[$i]['porth_field'] = $bm_ratio;
                }
            }
            
            // object层减掉固定价，剩余可分摊的销售金额
            $order['order_objects'][$k]['true_sale_price'] = ($remainingSalePrice > 0 ? $remainingSalePrice : 0);
            $order['order_objects'][$k]['true_amount'] = ($remain_amount > 0 ? $remain_amount : 0);
            $order['order_objects'][$k]['true_divide_order_fee'] = ($remain_divide_order_fee > 0 ? $remain_divide_order_fee : 0);
            $order['order_objects'][$k]['true_actually_amount'] = ($remain_actually_amount > 0 ? $remain_actually_amount : 0);
            $order['order_objects'][$k]['true_settlement_amount'] = ($remain_settlement_amount > 0 ? $remain_settlement_amount : 0);
            
            // 更新订单明细
            $order['order_objects'][$k]['order_items'] = $tmpOrderItems;
            
            // flag
            $order['order_objects'][$k]['is_set_fixed_price'] = true;
        }
        
        return $order;
    }
    
    /**
     * 获取SET商品固定价数据
     *
     * @param array $order 订单数据
     * @return array 销售物料包含的基础物料编码：固定价数据
     */
    private function getSetFixedPriceData($order)
    {
        $salesBasicMaterialMdl = app::get('material')->model('sales_basic_material');
        
        // order_objects
        $bmIds = [];
        $materialBns = [];
        $fixedPriceData = [];
        foreach ($order['order_objects'] as $object)
        {
            // check pkg
            if(!in_array($object['obj_type'], ['pkg'])){
                continue;
            }
            
            $goods_id = $object['goods_id']; // 销售物料ID
            
            // order_items
            foreach ($object['order_items'] as $item)
            {
                if ($item['product_id'] > 0) {
                    $product_id = $item['product_id']; // 基础物料ID
                    
                    $bmIds[] = $product_id;
                    $materialBns[$product_id] = $item['bn'];
                }
            }
            
            // 构建查询条件：需要匹配sm_id和bm_id的组合
            $filter = array('sm_id'=>$goods_id, 'bm_id' => $bmIds);
            $priceList = $salesBasicMaterialMdl->getList('sm_id,bm_id,fixed_price', $filter);
            if($priceList){
                foreach ($priceList as $priceKey => $priceVal)
                {
                    $sm_id = $priceVal['sm_id'];
                    $bm_id = $priceVal['bm_id'];
                    $fixed_price = $priceVal['fixed_price'];
                    
                    // check
                    if (empty($fixed_price) && $fixed_price !== 0) {
                        continue;
                    }
                    
                    $fixedPriceData[$sm_id][$bm_id] = [
                        'material_bn' => $materialBns[$bm_id],
                        'fixed_price' => $fixed_price,
                    ];
                }
            }
        }
        
        return $fixedPriceData;
    }
    
    /**
     * 判断订单是否编辑过商品
     * 规则：只有平台子订单 oid 被删除且不存在对应未删除商品时，才算编辑过商品；
     *
     * @param int $order_id 订单ID
     * @return bool
     */
    public function isOrderGoodsModified($order_id)
    {
        $orderMdl = app::get('ome')->model('orders');
        
        // order
        $orderInfo = $orderMdl->dump(array('order_id'=>$order_id), 'order_bn,process_status,status,pay_status,ship_status,is_modify');
        if(empty($orderInfo)){
            return false;
        }
        
        if($orderInfo['is_modify'] != 'true'){
            return false;
        }
        
        // order_objects
        $objectList = app::get('ome')->model('order_objects')->getList('obj_id,oid,`delete`', array('order_id'=>$order_id));
        if (empty($objectList)) {
            return false;
        }
        
        $oidStatus = array();
        foreach ($objectList as $object)
        {
            $oid = $object['oid'];
            if (empty($oid)) {
                continue;
            }
            
            if (!isset($oidStatus[$oid])) {
                $oidStatus[$oid] = array(
                    'has_deleted' => false,
                    'has_active' => false,
                );
            }
            
            if ($object['delete'] == 'true') {
                $oidStatus[$oid]['has_deleted'] = true;
            } else {
                $oidStatus[$oid]['has_active'] = true;
            }
        }
        
        // 订单明细没有子订单oid
        if(empty($oidStatus)){
            if($orderInfo['is_modify'] == 'true'){
                return true;
            }else{
                return false;
            }
        }
        
        // check
        foreach ($oidStatus as $status)
        {
            if ($status['has_deleted'] && !$status['has_active']) {
                return true;
            }
        }
        
        return false;
    }
}
