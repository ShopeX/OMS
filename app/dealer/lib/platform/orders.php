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
 * 经销商订单公共Lib方法类
 *
 * @author wangbiao@shopex.cn
 * @version 2024.04.12
 */
class dealer_platform_orders extends dealer_abstract
{
    /**
     * 创建平台订单
     * 
     * @param $sdf
     * @param $errmsg
     * @return bool
     */

    public function create_order(&$sdf, &$error_msg='')
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $jxObjectMdl = app::get('dealer')->model('platform_order_objects');
        $jxItemMdl = app::get('dealer')->model('platform_order_items');
        $logMdl = app::get('ome')->model('operation_log');
        
        $regionLib = kernel::single('eccommon_regions');
        $basicMStockLib = kernel::single('material_basic_material_stock');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        //判断订单号是否重复
        if($jxOrderMdl->dump(array('plat_order_bn'=>$sdf['plat_order_bn'], 'shop_id'=>$sdf['shop_id']))){
            $error_msg = $sdf['plat_order_bn'].':订单号重复';
            return $this->error($error_msg);
        }
        
        //开启事务(防止订单创建失败,但是冻结却预占的问题)
        $jxOrderMdl->db->exec('begin');
        
        //收货人/发货人地区转换
        $area = $sdf['consignee']['area'];
        
        $regionLib->region_validate($area);
        $sdf['consignee']['area'] = $area;
        
        $consigner_area = $sdf['consigner']['area'];
        $regionLib->region_validate($consigner_area);
        
        $sdf['consigner']['area'] = $consigner_area;
        
        //格式化订单明细
        foreach($sdf['order_objects'] as $key => $object)
        {
            $object['bn'] = trim($object['bn']);
            if($object['order_items']){
                foreach($object['order_items'] as $k => $item)
                {
                    $item['bn'] = trim($item['bn']);
                    
                    $object['order_items'][$k] = $item;
                }
            }
            
            $sdf['order_objects'][$key] = $object;
        }
        
        //计算实付
        //@todo：现在使用的销售物料表是sdb_dealer_sales_material,不能调用ome此方法;
        //$orderLib = kernel::single('ome_order');
        //$orderLib->create_divide_pay($sdf);
        
        !$sdf['splited_num'] && $sdf['splited_num'] = 0;
        
        //save订单主数据
        if(!$jxOrderMdl->save($sdf)){
            //事务回滚
            $error_msg = $sdf['plat_order_bn'].'平台订单创建失败：'. $jxOrderMdl->db->errorinfo();
            
            $jxOrderMdl->db->rollBack();
            
            return $this->error($error_msg);
        }
        
        //order_id
        $plat_order_id = $sdf['plat_order_id'];
        
        //保存订单明细
        foreach($sdf['order_objects'] as $objKey => $objVal)
        {
            $objVal['plat_order_id'] = $plat_order_id;
            $objVal['is_delete'] = $objVal['delete'];
            
            //子订单支付状态
            $objVal['pay_status'] = $sdf['pay_status'];
            
            //insert
            $plat_obj_id = $jxObjectMdl->insert($objVal);
            if(!$plat_obj_id){
                //事务回滚
                $error_msg = $sdf['plat_order_bn'].'平台订单创建object明细失败：'. $jxOrderMdl->db->errorinfo();;
                
                $jxOrderMdl->db->rollBack();
                
                return $this->error($error_msg);
            }
            
            //items
            foreach($objVal['order_items'] as $itemKey => $itemVal)
            {
                $itemVal['plat_order_id'] = $plat_order_id;
                $itemVal['plat_obj_id'] = $plat_obj_id;
                $itemVal['is_delete'] = $itemVal['delete'];
                
                //insert
                $plat_item_id = $jxItemMdl->insert($itemVal);
                if(!$plat_item_id){
                    //事务回滚
                    $error_msg = $sdf['plat_order_bn'].'平台订单创建items明细失败：'. $jxOrderMdl->db->errorinfo();;
                    
                    $jxOrderMdl->db->rollBack();
                    
                    return $this->error($error_msg);
                }
            }
        }
        
        //事务确认
        $jxOrderMdl->db->commit();
        
        //添加订单预占
        $needFreezeItems = [];
        foreach($sdf['order_objects'] as $objKey => $object)
        {
            //[刷单]brush特殊订单,不用预占库存
            if($sdf['order_type'] == 'brush'){
                continue;
            }
            
            //check
            if(empty($object['order_items'])){
                continue;
            }
            
            //order_items
            $store_code = '';
            $branch_id = 0;
            foreach($object['order_items'] as $itemKey => $item)
            {
                $nums = intval($item['nums']);
                $product_id = $item['product_id'];
                
                //只有发货方式为：代发货，才允许冻结库存
                if($item['is_shopyjdf_type'] != '2'){
                    continue;
                }
                
                //data
                if($product_id > 0 && $nums > 0 && $item['delete'] != 'true'){
                    $item['store_code'] = $store_code;
                    $item['branch_id'] = $branch_id;
                    
                    $needFreezeItems[] = $item;
                }
            }
        }
        
        if($needFreezeItems) {
            //库存预占类型
            $freeze_obj_type = material_basic_material_stock_freeze::__ORDER;
            $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
            $bill_type = material_basic_material_stock_freeze::__DEALER_ORDER;
            
            //对数组排序
            uasort($needFreezeItems, [kernel::single('console_iostockorder'), 'cmp_productid']);
            
            //items
            foreach($needFreezeItems as $item)
            {
                $nums = intval($item['nums']);
                $product_id = $item['product_id'];
                
                //增加基础物料冻结数
                $basicMStockLib->freeze($product_id, $nums);
                
                //修改预占库存流水
                $freezeData = array(
                    'bm_id' => $product_id,
                    'obj_type' => $freeze_obj_type,
                    'bill_type' => $bill_type,
                    'obj_id' => $sdf['plat_order_id'],
                    'shop_id' => $sdf['shop_id'],
                    'branch_id' => $item['branch_id'],
                    'bmsq_id' => $bmsq_id,
                    'num' => $nums,
                    'log_type' => '',
                    'store_code' => $item['store_code'],
                    'obj_bn' => $sdf['plat_order_bn'], //平台订单号
                );
                $basicMStockFreezeLib->freeze($freezeData);
            }
        }
        
        //log_msg
        $log_msg = ($sdf['source'] == 'local' ? '本地创建订单成功' : '平台订单创建成功');
        
        //logs
        $logMdl->write_log('order_create@dealer', $sdf['plat_order_id'], $log_msg);
        
        return $this->succ();
    }
    
    /**
     * 获取指定平台订单主表信息
     * 
     * @param $filter 查询条件
     * @param $fields 字段名(默认*表示所有)
     * @return array
     */
    public function getOrderMainInfo($filter, $fields='*')
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        
        //filter
        $filter = array_filter($filter);
        if(empty($filter)){
            return array();
        }
        
        //order
        $orderInfo = $jxOrderMdl->dump($filter, $fields);
        if(empty($orderInfo)){
            return array();
        }
        
        return $orderInfo;
    }
    
    /**
     * 获取指定平台订单信息(包含：objects、items商品明细)
     * 
     * @param $filter 查询条件
     * @return array
     */
    public function getOrderDetail($filter)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $jxObjectMdl = app::get('dealer')->model('platform_order_objects');
        $jxItemMdl = app::get('dealer')->model('platform_order_items');
        
        //filter
        $filter = array_filter($filter);
        if(empty($filter)){
            return array();
        }
        
        //order
        $orderInfo = $jxOrderMdl->dump($filter, '*');
        if(empty($orderInfo)){
            return array();
        }
        
        //items
        $itemList = array();
        $tempList = $jxItemMdl->getList('*', array('plat_order_id'=>$orderInfo['plat_order_id']));
        foreach ((array)$tempList as $key => $itemVal)
        {
            $plat_obj_id = $itemVal['plat_obj_id'];
            $plat_item_id = $itemVal['plat_item_id'];
            
            $itemList[$plat_obj_id][$plat_item_id] = $itemVal;
        }
        
//        //merge
//        $orderInfo['platform_order_items'] = $itemList;
        
        //objects
        $objectList = array();
        $tempList = $jxObjectMdl->getList('*', array('plat_order_id'=>$orderInfo['plat_order_id']));
        foreach ((array)$tempList as $key => $itemVal)
        {
            $plat_obj_id = $itemVal['plat_obj_id'];
            
            //platform_order_items
            $itemVal['order_items'] = (isset($itemList[$plat_obj_id]) ? $itemList[$plat_obj_id] : array());
            
            //失败订单：销售物料找不到的
            if($itemVal['goods_id'] <= 0){
                $itemVal['obj_fail'] = 1;
            }elseif(!isset($itemVal['order_items'])){
                $itemVal['obj_item_fail'] = 1;
            }
            
            //merge
            $objectList[$plat_obj_id] = $itemVal;
        }
        
        //merge
        $orderInfo['order_objects'] = $objectList;
        
        //format consignee
        $orderInfo['consignee'] = array(
            'name' => $orderInfo['ship_name'],
            'area' => $orderInfo['ship_area'],
            'mobile' => $orderInfo['ship_mobile'],
            'tel' => $orderInfo['ship_tel'],
            'zip' => $orderInfo['ship_zip'],
            'addr' => $orderInfo['ship_addr'],
        );
        
        //unset
        unset($tempList, $itemList, $objectList);
        
        return $orderInfo;
    }
    
    /**
     * 获取基础物料的发货方式(自发、代发)、所属产品线、所属贸易公司
     * @todo：只有基础物料设置了发货方式为：代发，其它情况都是：自发；
     * 
     * @param $productList
     * @param $businessInfo 经销商信息
     * @return array
     */
    public function getProductDespatchType($productList, $businessInfo)
    {
        $seriesMdl = app::get('dealer')->model('series');
        $seProductMdl = app::get('dealer')->model('series_endorse_products');
        
        //product_id
        $productIds = array_column($productList, 'product_id');
        
        //产品授权信息
        $dataList = $seProductMdl->getList('*', array('bm_id'=>$productIds, 'shop_id'=>$businessInfo['shop_id']));
        if(empty($dataList)){
            return $productList;
        }
        
        $seriesIds = array_column($dataList, 'series_id');
        
        //产品线列表
        $seriesList = $seriesMdl->getList('*', array('series_id'=>$seriesIds));
        $seriesList = array_column($seriesList, null, 'series_id');
        
        //format
        foreach ($dataList as $key => $val)
        {
            $bm_id = $val['bm_id'];
            $series_id = intval($val['series_id']);
            
            //获取发货方式（自发or代发）
            $getProductMode = $this->getProductShopyjdfType($val);
            
            $productList[$bm_id]['is_shopyjdf_type'] = $getProductMode['is_shopyjdf_type'];
            $productList[$bm_id]['remark'] = $getProductMode['remark'];
            
            //贸易公司ID
            $productList[$bm_id]['betc_id'] = intval($seriesList[$series_id]['betc_id']);
        }
        
        return $productList;
    }
    
    /**
     * 获取发货方式（1:自发，2:代发）
     * 
     * @param $productInfo
     * @return array
     */
    public function getProductShopyjdfType($productInfo)
    {
        $dateline = time();
        $result = array('is_shopyjdf_type'=>'1', 'remark'=>'');
        
        //自发
        if($productInfo['is_shopyjdf_type'] != '2'){
            $result['remark'] = '发货方式是：自发货';
            return $result;
        }
        
        //检查代发货开始时间
        if($productInfo['from_time'] && $productInfo['from_time'] > $dateline){
            $result['remark'] = '代发开始时间是：'.date('Y-m-d H:i:s', $productInfo['from_time']);
            return $result;
        }
        
        //检查代发货结束时间
        if($productInfo['end_time'] && $productInfo['end_time'] < $dateline){
            $result['remark'] = '代发结束时间是：'.date('Y-m-d H:i:s', $productInfo['end_time']);
            return $result;
        }
        
        //代发
        $result['is_shopyjdf_type'] = '2';
        return $result;
    }
    
    /**
     * 设置订单Hold单时间
     * @todo：创建订单完成后，根据配置项设置hold单时间，防止避免发货前退款；
     * 
     * @param $plat_order_id
     * @return void
     */
    public function setOrderHoldTime($plat_order_id)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        
        //默认6小时(后面可在店铺中配置hold时间选项)
        $hold_time = 60 * 60 * 6;
        $hold_msg = '系统默认';
        
        //获取HOLD单规则
        $result = kernel::single('dealer_platform_order_hold')->process($plat_order_id);
        if($result['rsp'] == 'succ' && $result['data']){
            $hours = $result['data']['hours'];
            if($hours > 0){
                $hold_time = 60 * 60 * $hours;
                $hold_msg = 'hold单规则';
            }
        }
        
        $timing_confirm = time() + $hold_time;
        
        //update
        $updateData = array('timing_confirm'=>$timing_confirm);
        $jxOrderMdl->update($updateData, array('plat_order_id'=>$plat_order_id));
        
        //放入misc_task任务里,延迟自动审单
        $task = array(
            'obj_id' => $plat_order_id,
            'obj_type' => 'timing_dealer_order',
            'exec_time' => $timing_confirm,
        );
        app::get('ome')->model('misc_task')->saveMiscTask($task);
        
        //logs
        $logMdl = app::get('ome')->model('operation_log');
        $logMdl->write_log('order_modify@dealer', $plat_order_id, '设置hold单时间('. $hold_msg .')：'. date('Y-m-d H:i:s', $timing_confirm) .' 延迟自动审单');
        
        return true;
    }
    
    /**
     * 审核经销商订单
     * @todo: 根据基础物料发货方式，创建OMS订单(多个SKU对应多个分销商,拆分成多个OMS订单);
     * 
     * @param array $params
     * @return array
     */
    public function confirmOrder($params)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        //setting
        $updateData = array('convert_status'=>'fail', 'last_modified'=>time());
        $error_msg = '';
        
        //plat_order_id
        if(isset($params[0]) && $params[0]){
            $plat_order_id = $params[0];
        }elseif(isset($params['obj_id']) && $params['obj_id']){
            $plat_order_id = $params['obj_id'];
        }else{
            return false;
        }
        
        //平台订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $this->getOrderDetail($filter);
        
        //check检查订单主数据
        $isCheck = $this->checkConfirmOrder($orderInfo, $error_msg);
        if(!$isCheck){
            $error_msg = '审核订单失败：'. $error_msg;
            
            //fail
            $updateData['dispose_msg'] = $error_msg;
            $jxOrderMdl->update($updateData, array('plat_order_id'=>$plat_order_id));
            
            //logs
            $logMdl->write_log('order_confirm@dealer', $plat_order_id, $error_msg);
            
            return $this->error($error_msg);
        }
        
        //按照[贸易公司]进行分组,获取需要代发货的objects数据
        $getObjectResult = $this->getConfirmObjects($orderInfo, $error_msg);
        if($getObjectResult['rsp'] != 'succ'){
            $error_msg = '审核代发货商品失败，'. $getObjectResult['error_msg'];
            
            //fail
            $updateData['dispose_msg'] = $error_msg;
            $jxOrderMdl->update($updateData, array('plat_order_id'=>$plat_order_id));
            
            //logs
            $logMdl->write_log('order_confirm@dealer', $plat_order_id, $error_msg);
            
            return $this->error($error_msg);
        }else{
            //订单发货方式
//            if(isset($getObjectResult['data']['dispose_status'])){
//                $updateData['dispose_status'] = $getObjectResult['data']['dispose_status'];
//            }
            
            //succ没有贸易公司分组数据,直接返回succ
            if(!isset($getObjectResult['data']['betcGroup'])){
                $updateData['convert_status'] = 'needless';
                $updateData['dispose_msg'] = ($getObjectResult['msg'] ? $getObjectResult['msg'] : $getObjectResult['error_msg']);
                
                //update
                $jxOrderMdl->update($updateData, array('plat_order_id'=>$plat_order_id));
                
                //logs
                $logMdl->write_log('order_confirm@dealer', $plat_order_id, $updateData['dispose_msg']);
                
                return $this->succ('审核订单成功：不需要创建OMS订单；'. $updateData['dispose_msg']);
            }
        }
        
        //贸易公司分组数据
        $betcGroup = $getObjectResult['data']['betcGroup'];
        
        //[按贸易公司纬度]获取OMS订单结构数据
        $responseOrders = $this->formatResponseOrders($orderInfo, $betcGroup);
        if($responseOrders['rsp'] != 'succ'){
            $error_msg = '获取OMS订单结构数据失败：'. $getObjectResult['error_msg'];
            
            //fail
            $updateData['dispose_msg'] = $error_msg;
            $jxOrderMdl->update($updateData, array('plat_order_id'=>$plat_order_id));
            
            //logs
            $logMdl->write_log('order_confirm@dealer', $plat_order_id, $error_msg);
            
            return $this->error($error_msg);
        }
        
        $responseOrders = $responseOrders['data'];
        
        //批量创建OMS订单
        $result = $this->batchCreateErpOrders($orderInfo, $responseOrders);
        if($result['rsp'] != 'succ'){
            $error_msg = '请求创建OMS订单失败，'. $result['error_msg'];
            
            //fail
            $updateData['dispose_msg'] = $error_msg;
            $jxOrderMdl->update($updateData, array('plat_order_id'=>$plat_order_id));
            
            //不需要记录logs(请求OMS创建订单失败时,已经记录了)
            //$logMdl->write_log('order_confirm@dealer', $plat_order_id, $error_msg);
            
            return $this->error($error_msg);
        }elseif(!isset($result['data']['erp_orders'])){
            $error_msg = '请求创建OMS订单失败：没有创建成功的OMS订单';
            
            //fail
            $updateData['dispose_msg'] = $error_msg;
            $jxOrderMdl->update($updateData, array('plat_order_id'=>$plat_order_id));
            
            //logs
            $logMdl->write_log('order_confirm@dealer', $plat_order_id, $error_msg);
            
            return $this->error($error_msg);
        }
        
        //后续业务处理
        $erpOrderBns = array();
        foreach ($result['data']['erp_orders'] as $erpKey => $erpVal)
        {
            $erp_order_bn = $erpVal['erp_order_bn'];
            
            //order_bn
            $erpOrderBns[$erp_order_bn] = $erp_order_bn;
            
            //按erp订单号更新关联信息
            $this->updateOrderRelevanceInfo($orderInfo, $erp_order_bn);
        }
        
        //释放经销商订单库存预占
        $this->unOrderFreeze($orderInfo);
        
        //update
        $updateData['convert_status'] = 'converted';
        $updateData['dispose_msg'] = '';
        $jxOrderMdl->update($updateData, array('plat_order_id'=>$plat_order_id));
        
        //logs
        $error_msg = '创建OMS订单成功：'.implode(',', $erpOrderBns);
        $logMdl->write_log('order_confirm@dealer', $plat_order_id, $error_msg);
        
        //请求smart业务处理
        foreach ($result['data']['erp_orders'] as $erpKey => $erpVal)
        {
            $erp_order_bn = $erpVal['erp_order_bn'];
            
            //按erp订单号请求smart接口获取价格
            $this->requestSmart($erp_order_bn);
        }
        
        return $this->succ('审核经销商订单成功');
    }
    
    /**
     * 检查订单信息
     * 
     * @param $orderInfo
     * @param $error_msg
     * @return bool
     */
    public function checkConfirmOrder($orderInfo, &$error_msg=null)
    {
        if(empty($orderInfo)){
            $error_msg = '经销商订单信息不存在';
            return false;
        }
        
        //check
        if($orderInfo['pay_status'] != '1'){
            $error_msg = '订单不是已支付状态';
            return false;
        }
        
        if($orderInfo['is_fail'] == 'true'){
            $error_msg = '失败订单请先修正';
            return false;
        }
        
        if(!in_array($orderInfo['convert_status'], array('unconvert', 'splitting', 'fail'))){
            $error_msg = '订单转单状态不允许操作';
            return false;
        }
        
        if(!in_array($orderInfo['dispose_status'], array('all_daifa', 'part_daifa'))){
            $error_msg = '订单允许代发';
            return false;
        }
        
        if(empty($orderInfo['order_objects'])){
            $error_msg = '没有订单商品object明细';
            return false;
        }
        
        return true;
    }
    
    /**
     * 按照[贸易公司]进行分组,获取需要代发货的objects数据
     * 
     * @param $orderInfo
     * @return array
     */
    public function getConfirmObjects($orderInfo)
    {
        //check
        if(empty($orderInfo['order_objects'])){
            $error_msg = '订单object数据不存在';
            return $this->error($error_msg);
        }
        
        //format
        $objectList = array();
        $betcIds = array();
        $abnormalList = array();
        $shopyjdf_types = array();
        foreach ($orderInfo['order_objects'] as $objKey => $objVal)
        {
            $plat_obj_id = $objVal['plat_obj_id'];
            
            //已删除
            if($objVal['is_delete'] == 'true'){
                continue;
            }
            
            //商品有退款
            if(!in_array($objVal['pay_status'], array('0', '1'))){
                continue;
            }
            
            //items
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $betc_id = $itemVal['betc_id'];
                $plat_item_id = $itemVal['plat_item_id'];
                $is_shopyjdf_type = $itemVal['is_shopyjdf_type'];
                
                //已删除
                if($itemVal['is_delete'] == 'true'){
                    continue;
                }
                
                //未转换的item明细
                if($is_shopyjdf_type == '0'){
                    //异常-基础物料未转换发货方式
                    $abnormalList['item_transition'][$plat_item_id] = $itemVal['bn'];
                    
                    continue;
                }
                
                //不是代发货方式,则跳过
                if($is_shopyjdf_type != '2'){
                    $shopyjdf_types[$is_shopyjdf_type] = $is_shopyjdf_type;
                    
                    continue;
                }
                
                //明细已经被处理过
                if($itemVal['process_status'] != 'unconfirmed'){
                    //异常-有部分明细被处理过
                    $abnormalList['item_process'][$plat_item_id] = $itemVal['bn'];
                    
                    continue;
                }
                
                //代发货方式
                $abnormalList['despatch_object'][$plat_obj_id][$is_shopyjdf_type] = $plat_item_id;
                $shopyjdf_types[$is_shopyjdf_type] = $is_shopyjdf_type;
                
                //检查贸易公司ID
                if(empty($betc_id)){
                    //异常-贸易公司ID为空
                    $abnormalList['betc_empty'][$plat_item_id] = $itemVal['bn'];
                    
                    continue;
                }
                
                //按照[贸易公司]进行分组
                if(!isset($betcIds[$betc_id][$plat_obj_id])){
                    $betcIds[$betc_id][$plat_obj_id] = $objVal;
                }
                $betcIds[$betc_id][$plat_obj_id]['order_items'][$plat_item_id] = $itemVal;
                
                //统计object层分组的贸易公司
                $abnormalList['betc_object'][$plat_obj_id][$betc_id] = $plat_item_id;
            }
            
            $objectList[$plat_obj_id] = $objVal;
        }
        
        //检查几种异常
        if($abnormalList){
            $errorMsgs = array();
            
            //item_transition
            if(isset($abnormalList['item_transition'])){
                $abnormalBns = array();
                foreach ($abnormalList['item_transition'] as $plat_item_id => $product_bn)
                {
                    $abnormalBns[] = $product_bn;
                }
                
                if($abnormalBns){
                    $errorMsgs[] = '基础物料编码：'. implode(',', $abnormalBns) .'没有转换发货方式';
                }
            }
            
            //item_process
            if(isset($abnormalList['item_process'])){
                $abnormalBns = array();
                foreach ($abnormalList['item_process'] as $plat_item_id => $product_bn)
                {
                    $abnormalBns[] = $product_bn;
                }
                
                if($abnormalBns){
                    $errorMsgs[] = '基础物料编码：'. implode(',', $abnormalBns) .'已经被处理过';
                }
            }
            
            //检测PKG组合：一个商品关联多个基础物料时,有多种发货方式
            if(isset($abnormalList['despatch_object'])){
                $abnormalBns = array();
                foreach ($abnormalList['despatch_object'] as $plat_obj_id => $betcItems)
                {
                    if(count($betcItems) > 1){
                        $abnormalBns[] = $objectList[$plat_obj_id]['bn'];
                    }
                }
                
                if($abnormalBns){
                    $errorMsgs[] = '销售物料编码：'. implode(',', $abnormalBns) .'有多种发货方式';
                }
            }
            
            //betc_empty
            if(isset($abnormalList['betc_empty'])){
                $abnormalBns = array();
                foreach ($abnormalList['betc_empty'] as $plat_item_id => $product_bn)
                {
                    $abnormalBns[] = $product_bn;
                }
                
                if($abnormalBns){
                    $errorMsgs[] = '基础物料编码：'. implode(',', $abnormalBns) .'贸易公司ID为空';
                }
            }
            
            //检测PKG组合：一个商品关联多个基础物料时,有多个贸易公司
            if(isset($abnormalList['betc_object'])){
                $abnormalBns = array();
                foreach ($abnormalList['betc_object'] as $plat_obj_id => $betcItems)
                {
                    if(count($betcItems) > 1){
                        $abnormalBns[] = $objectList[$plat_obj_id]['bn'];
                    }
                }
                
                if($abnormalBns){
                    $errorMsgs[] = '销售物料编码：'. implode(',', $abnormalBns) .'有多个贸易公司';
                }
            }
            
            //error
            if($errorMsgs){
                $error_msg = '转换创建OMS订单失败，'. implode('；', $errorMsgs);
                return $this->error($error_msg);
            }
        }
        
        //[自发货方式]代发货方式的商品为空,则返回成功,无需处理订单;
        if(!isset($shopyjdf_types['2'])){
            //全部自发货
            $data = array('dispose_status'=>'zifa');
            return $this->succ('没有代发货的商品', $data);
        }
        
        //check
        if(empty($betcIds)){
            $error_msg = '没有可处理的贸易公司';
            return $this->error($error_msg);
        }
        
        //发货方式
        $dispose_status = 'zifa';
        if(count($shopyjdf_types) > 1){
            $dispose_status = 'part_daifa'; //部分代发货
        }else{
            $is_shopyjdf_type = current($shopyjdf_types);
            if($is_shopyjdf_type == '2'){
                $dispose_status = 'all_daifa'; //全部代发货
            }elseif($is_shopyjdf_type == '1'){
                $dispose_status = 'zifa'; //全部自发货
            }
        }
        
        $msg = '获取代发货的objects数据成功';
        return $this->succ($msg, array('betcGroup'=>$betcIds, 'dispose_status'=>$dispose_status));
    }
    
    /**
     * [按贸易公司纬度]格式化OMS订单结构数据
     * 
     * @param $orderInfo
     * @param $betcGroup
     * @return array
     */
    public function formatResponseOrders($orderInfo, $betcGroup)
    {
        $plat_order_id = $orderInfo['plat_order_id'];
        $plat_order_bn = $orderInfo['plat_order_bn'];
        $bs_id = $orderInfo['bs_id'];
        $cos_id = $orderInfo['cos_id'];
        
        //贸易公司分组
        $responseOrders = array();
        foreach ($betcGroup as $betc_id => $objList)
        {
            foreach ($objList as $plat_obj_id => $objVal)
            {
                //check
                if(empty($objVal['order_items'])){
                    unset($objList[$plat_obj_id]);
                }
            }
            
            //check
            if(empty($objList)){
                continue;
            }
            
            //params
            $requestParams = array(
                'plat_order_id' => $plat_order_id,
                'plat_order_bn' => $plat_order_bn,
                'betc_id' => $betc_id,
                'bs_id' => $bs_id,
                'cos_id' => $cos_id,
            );
            
            //格式化平台订单数据(删除掉自发商品明细)
            $originResult = $this->formatOriginaOrder($requestParams, $objList);
            if($originResult['rsp'] != 'succ'){
                //update
                $error_msg = '格式化平台订单数据失败：'. $originResult['error_msg'];
                return $this->error($error_msg);
            }
            
            //data
            $responseOrders[$betc_id] = $originResult['data'];
        }
        
        return $this->succ('按贸易公司纬度,格式化OMS订单结构数据成功', $responseOrders);
    }
    
    /**
     * 格式化平台推送过来的订单原数据(删除掉自发商品明细)
     * @todo：object层所有商品金额固定为0元，创建OMS订单后，请求smart接口获取价格，再更新订单明细上的所有金额及创建支付单；
     * 
     * @param $params
     * @param $objectList 需要处理的订单明细
     * @return array
     */
    public function formatOriginaOrder($params, $objectList)
    {
        $extendMdl = app::get('dealer')->model('platform_order_extend');
        
        //params
        $betc_id = intval($params['betc_id']);
        $bs_id = intval($params['bs_id']);
        $cos_id = intval($params['cos_id']);
        $plat_order_id = intval($params['plat_order_id']);
        $plat_order_bn = $params['plat_order_bn'];
        
        //check
        if(empty($plat_order_id) || empty($betc_id) || empty($objectList)){
            $error_msg = '订单ID、贸易公司ID、订单明细列表为空,请检查';
            return $this->error($error_msg);
        }
        
        //获取平台订单原数据
        $extendInfo = $extendMdl->dump(array('plat_order_id'=>$plat_order_id), '*');
        if(empty($extendInfo) || empty($extendInfo['extend_info'])){
            $error_msg = '平台订单数据不存在';
            return $this->error($error_msg);
        }
        
        //data
        $originalInfo = json_decode($extendInfo['extend_info'], true);
        if(empty($originalInfo)){
            $error_msg = '平台订单json数据为空';
            return $this->error($error_msg);
        }
        
        $originalInfo['order_objects'] = json_decode($originalInfo['order_objects'], true);
//        $originalInfo['payments'] = json_decode($originalInfo['payments'], true);
//        $originalInfo['payment_detail'] = json_decode($originalInfo['payment_detail'], true);
//        $originalInfo['pmt_detail'] = json_decode($originalInfo['pmt_detail'], true);
        
        //check
        if(empty($originalInfo['order_objects'])){
            $error_msg = '平台订单商品明细不存在';
            return $this->error($error_msg);
        }
        
        //goods_bn
        $goodsBns = array_column($objectList, 'bn');
        
        //format
        foreach ($originalInfo['order_objects'] as $objKey => $objVal)
        {
            $goods_bn = $objVal['bn'];
            
            //check过滤掉自发商品
            if(!in_array($goods_bn, $goodsBns)){
                unset($originalInfo['order_objects'][$objKey]);
                
                continue;
            }
            
            //金额固定为0元
            $originalInfo['order_objects'][$objKey]['price'] = 0;
            $originalInfo['order_objects'][$objKey]['sale_price'] = 0;
            $originalInfo['order_objects'][$objKey]['amount'] = 0;
            $originalInfo['order_objects'][$objKey]['divide_order_fee'] = 0;
            $originalInfo['order_objects'][$objKey]['pmt_price'] = 0;
            $originalInfo['order_objects'][$objKey]['part_mjz_discount'] = 0;
            
            //items
            $originalInfo['order_objects'][$objKey]['order_items'] = array();
        }
        
        //check
        if(empty($originalInfo['order_objects'])){
            $error_msg = '没有可处理的平台订单object明细';
            return $this->error($error_msg);
        }
        
        //金额固定为0元、支付状态为：未支付
        //@todo：所有商品都是0元，创建OMS订单成功会请求smart接口，再更新价格创建支付单；
        $originalInfo['cur_amount'] = 0;
        $originalInfo['payed'] = 0;
        $originalInfo['credit_card_fee'] = 0;
        $originalInfo['total_amount'] = 0;
        $originalInfo['cost_item'] = 0;
        $originalInfo['pmt_order'] = 0;
        $originalInfo['pmt_goods'] = 0;
        
        $originalInfo['pay_status'] = '0'; //支付状态
        $originalInfo['payments'] = array(); //支付信息
        $originalInfo['payment_detail'] = array(); //支付单
        $originalInfo['pmt_detail'] = array(); //订单优惠方案
        $originalInfo['coupon_field'] = array(); //优惠明细平台原始字段
        
        //json_encode
        $originalInfo['order_objects'] = json_encode($originalInfo['order_objects']);
        $originalInfo['payments'] = json_encode($originalInfo['payments']);
        $originalInfo['payment_detail'] = json_encode($originalInfo['payment_detail']);
        $originalInfo['pmt_detail'] = json_encode($originalInfo['pmt_detail']);
        $originalInfo['coupon_field'] = json_encode($originalInfo['coupon_field']);
        
        //平台订单号
        $originalInfo['platform_order_bn'] = $plat_order_bn;
        
        //贸易公司、组织架构ID
        $originalInfo['betc_id'] = $betc_id;
        $originalInfo['cos_id'] = $cos_id;
        
        //flag
        $originalInfo['delivery_mode'] = 'shopyjdf'; //发货模式：一件代发
        $originalInfo['is_dealer_order'] = 'true'; //经销商订单
        
        $error_msg = '格式化平台订单数据成功';
        
        return $this->succ($error_msg, $originalInfo);
    }
    
    /**
     * 批量创建OMS订单
     * 
     * @param $orderInfo
     * @param $error_msg
     * @return array
     */
    public function batchCreateErpOrders($params, $responseOrders)
    {
        $orderMdl = app::get('ome')->model('orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        //params
        $plat_order_id = $params['plat_order_id'];
        $plat_order_bn = $params['plat_order_bn'];
        
        //setting
        $prefix = 'JX';
        $sign_check = false;
        
        //模拟推送erpapi_response创建OMS订单
        $reStatus = array();
        foreach ($responseOrders as $postKey => $postData)
        {
            $node_id = $postData['node_id'];
            $erp_order_bn = $orderMdl->gen_id($prefix);
            
            //重置OMS订单号
            $postData['order_bn'] = $erp_order_bn;
            
//            //生成支付单号
//            //@todo：一个订单有多个基础物料，多个贸易公司创建多个OMS订单时，支付单号不能重复；
//            $paymentObj = app::get('ome')->model('payments');
//            $trade_no = 'JX'. $paymentObj->gen_id();
//            $trade_no = $postData['order_bn']; //直接使用经销订单号
            
            //response
            $result = kernel::single('erpapi_router_response')->set_node_id($node_id)->set_api_name('daifa.order.add')->dispatch($postData, $sign_check);
            if($result['rsp'] != 'succ'){
                $error_msg = 'ERP订单号：'. $erp_order_bn .'创建失败('. $result['msg'] .')';
                
                //flag
                $reStatus['fail'][$postKey] = $error_msg;
                
                //logs
                $logMdl->write_log('order_confirm@dealer', $plat_order_id, $error_msg);
                
                continue;
            }
            
            //flag
            $error_msg = 'ERP订单号：'. $erp_order_bn .'创建成功';
            $reStatus['succ'][$postKey] = array('erp_order_bn'=>$erp_order_bn, 'error_msg'=>$error_msg);
            
            //logs
            $logMdl->write_log('order_confirm@dealer', $plat_order_id, $error_msg);
        }
        
        //check
        if(!isset($reStatus['succ'])){
            //全部失败
            $error_msg = implode('；', $reStatus['fail']);
            return $this->error($error_msg);
        }elseif(isset($reStatus['fail']) && isset($reStatus['succ'])){
            //部分失败
            $error_msg = implode('；', $reStatus['fail']);
            return $this->error($error_msg);
        }
        
        return $this->succ('请求erpapi创建OMS订单成功', array('erp_orders'=>$reStatus['succ']));
    }
    
    /**
     * 释放订单库存冻结
     * 
     * @param $orderInfo
     * @return bool
     */
    public function unOrderFreeze($orderInfo)
    {
        $basicMStockLib = kernel::single('material_basic_material_stock');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        //库存预占类型
        $freeze_obj_type = material_basic_material_stock_freeze::__ORDER;
        $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
        $bill_type = material_basic_material_stock_freeze::__DEALER_ORDER;
        
        //params
        $plat_order_id = $orderInfo['plat_order_id'];
        
        //objects
        foreach($orderInfo['order_objects'] as $objKey => $objVal)
        {
            //items
            foreach($objVal['order_items'] as $itemKey => $itemVal)
            {
                $product_id = $itemVal['product_id'];
                $nums = $itemVal['nums'];
                
                //check
                if(empty($product_id) || empty($nums)){
                    continue;
                }
                
                //释放基础物料冻结数
                $basicMStockLib->unfreeze($product_id, $nums);
                
                //释放基础物料库存冻结流水
                $basicMStockFreezeLib->unfreeze($product_id, $freeze_obj_type, $bill_type, $plat_order_id, '', $bmsq_id, $nums);
            }
        }
        
        //清除经销商订单预占流水
        // unfreezeBatch已经清除
        // $basicMStockFreezeLib->delOrderFreeze($plat_order_id, $bill_type);
        
        return true;
    }
    
    /**
     * 按erp订单号：更新订单关联信息
     * 更新数据包括：
     * 1、更新分销商平台订单items层：erp_order_id、erp_order_bn
     * 2、更新order_objects层：obj_line_no
     * 3、更新order_items层：item_line_no
     * 
     * @param $orderInfo
     * @param $erp_order_bn
     * @return array
     */
    public function updateOrderRelevanceInfo($orderInfo, $erp_order_bn)
    {
        $orderMdl = app::get('dealer')->model('orders');
        $jxObjectMdl = app::get('dealer')->model('platform_order_objects');
        $jxItemMdl = app::get('dealer')->model('platform_order_items');
        $orderObjMdl = app::get('dealer')->model('order_objects');
        $orderItemMdl = app::get('dealer')->model('order_items');
        
        //params
        $plat_order_id = $orderInfo['plat_order_id'];
        $plat_order_bn = $orderInfo['plat_order_bn'];
        
        //info
        $erpOrderInfo = $orderMdl->dump(array('order_bn'=>$erp_order_bn), '*', array('order_objects'=>array('*', array('order_items'=>array('*')))));
        if(empty($erpOrderInfo)){
            $error_msg = 'ERP订单号：'. $erp_order_bn .'数据不存在';
            return $this->error($error_msg);
        }
        
        if(empty($erpOrderInfo['order_objects'])){
            $error_msg = 'ERP订单号：'. $erp_order_bn .'商品明细不存在';
            return $this->error($error_msg);
        }
        
        //[经销商订单]获取订单明细
        $platOidList = array();
        foreach($orderInfo['order_objects'] as $objKey => $objVal)
        {
            $plat_oid = $objVal['plat_oid'];
            
            //check
            if(empty($plat_oid)){
                continue;
            }
            
            $platOidList[$plat_oid] = $objVal;
            
            //items
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $product_bn = $itemVal['bn'];
                
                $platOidList[$plat_oid]['order_items'][$product_bn] = $itemVal;
            }
        }
        
        //[OMS订单]objects
        $erpOids = array();
        foreach($erpOrderInfo['order_objects'] as $objKey => $objVal)
        {
            $oid = $objVal['oid'];
            
            //check
            if(empty($oid)){
                continue;
            }
            
            $erpOids[$oid] = $oid;
            
            //经销商订单商品信息
            $platObjInfo = $platOidList[$oid];
            
            //items
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $product_bn = $itemVal['bn'];
                
                //update items
                if(isset($platObjInfo['order_items'][$product_bn])){
                    $orderItemMdl->update(array('item_line_no'=>$platObjInfo['order_items'][$product_bn]['plat_item_id']), array('item_id'=>$itemVal['item_id']));
                }
            }
            
            //update objects
            if(isset($platObjInfo['plat_obj_id'])){
                $orderObjMdl->update(array('obj_line_no'=>$platObjInfo['plat_obj_id']), array('obj_id'=>$objVal['obj_id']));
            }
        }
        
        //获取objects层
        $tempList = $jxObjectMdl->getList('plat_obj_id,plat_oid', array('plat_order_id'=>$plat_order_id, 'plat_oid'=>$erpOids));
        if(empty($tempList)){
            $error_msg = '经销商订单号：'. $plat_order_bn .'关联oid子单不存在';
            return $this->error($error_msg);
        }
        
        //ids
        $plat_obj_ids = array_column($tempList, 'plat_obj_id');
        
        //update objects
        $updateData = array('erp_order_id'=>$erpOrderInfo['order_id'], 'erp_order_bn'=>$erpOrderInfo['order_bn'], 'process_status'=>'confirmed', 'last_modified'=>time());
        $jxObjectMdl->update($updateData, array('plat_obj_id'=>$plat_obj_ids));
        
        //update items
        $updateData = array('erp_order_id'=>$erpOrderInfo['order_id'], 'erp_order_bn'=>$erpOrderInfo['order_bn'], 'process_status'=>'confirmed', 'last_modified'=>time());
        $jxItemMdl->update($updateData, array('plat_obj_id'=>$plat_obj_ids));
        
        return $this->succ();
    }
    
    /**
     * 请求smart接口获取经销商物料价格
     *
     * @param $erp_order_bn
     * @return void
     */
    public function requestSmart($erp_order_bn)
    {
        $orderMdl = app::get('ome')->model('orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        //info
        $orderInfo = $orderMdl->dump(array('order_bn'=>$erp_order_bn), '*', array('order_objects'=>array('*', array('order_items'=>array('*')))));
        if(empty($orderInfo)){
            $error_msg = 'ERP订单号：'. $erp_order_bn .'数据不存在';
            return $this->error($error_msg);
        }
        
        //check
        if($orderInfo['pay_status'] != '0'){
            $error_msg = 'ERP订单号：'. $erp_order_bn .'支付状态：'. $orderInfo['pay_status'] .'不支持处理(已经获取SMART价格)';
            return $this->error($error_msg);
        }
        
        //channel_id
        //@todo：现在没有真实的smart报价授权,后面可以在(系统集成-->报价系统授权)绑定使用对应的渠道ID;
        $channel_id = 1;
        
        //request
        $result = kernel::single('erpapi_router_request')->set('smart', $channel_id)->order_addOrder($orderInfo);
        if($result['rsp'] != 'succ'){
            $error_msg = '请求Smart接口失败：'. $result['error_msg'];
            
            //logs
            $logMdl->write_log('order_confirm@ome', $orderInfo['order_id'], $error_msg);
            
            return $this->error($error_msg);
        }
        
//        //获取smart订单价格
//        $result = $this->updateOmsOrderMoney($orderInfo);
//        if($result['rsp'] != 'succ'){
//            $error_msg = '获取Smart订单价格失败：'. $result['error_msg'];
//
//            //logs
//            $logMdl->write_log('order_confirm@ome', $orderInfo['order_id'], $error_msg);
//
//            return $this->error($error_msg);
//        }
        
        //获取smart订单价格
        $result = $this->updateSmartOrderMoney($orderInfo);
        if($result['rsp'] != 'succ'){
            $error_msg = '获取Smart订单价格失败：'. $result['error_msg'];
            
            //logs
            $logMdl->write_log('order_confirm@ome', $orderInfo['order_id'], $error_msg);
            
            return $this->error($error_msg);
        }
        
        return $this->succ();
    }
    
    /**
     * 更新订单明细发货方式
     * 
     * @param $params
     * @return array
     */
    public function updateOrderShopyjdfType($sdf)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $jxObjectMdl = app::get('dealer')->model('platform_order_objects');
        $jxItemMdl = app::get('dealer')->model('platform_order_items');
        $logMdl = app::get('ome')->model('operation_log');
        
        $basicMStockLib = kernel::single('material_basic_material_stock');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        //params
        $plat_order_id = $sdf['plat_order_id'];
        
        //平台订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $this->getOrderDetail($filter);
        if(empty($orderInfo)){
            $error_msg = '订单：'. $sdf['plat_order_bn'] .'未找到';
            return $this->error($error_msg);
        }
        
        //objects
        $productList = array();
        foreach($orderInfo['order_objects'] as $objKey => $objVal)
        {
            //check
            if(empty($objVal['order_items'])){
                continue;
            }
            
            //items
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $product_id = $itemVal['product_id'];
                $material_bn = $itemVal['bn'];
                
                //products
                $productList[$product_id] = array(
                    'product_id' => $product_id,
                    'product_bn' => $material_bn,
                    'betc_id' => 0, //贸易公司ID
                    'is_shopyjdf_type' => '0', //发货方式
                );
            }
        }
        
        //check
        if(empty($productList)){
            $error_msg = '订单：'. $orderInfo['plat_order_bn'] .'没有可操作的明细';
            return $this->error($error_msg);
        }
        
        //通过基础物料获取发货方式：自发、代发，所属贸易公司ID；
        $businessInfo = array('shop_id'=>$orderInfo['shop_id']);
        $productList = $this->getProductDespatchType($productList, $businessInfo);
        
        //format
        $needFreezeItems = array();
        foreach ($orderInfo['order_objects'] as $objKey => $objVal)
        {
            //check
            if(empty($objVal['order_items'])){
                continue;
            }
            
            //items
            $shopyjdf_types = array();
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $product_id = $itemVal['product_id'];
                $betc_id = 0; //贸易公司ID
                $is_shopyjdf_type = '1'; //发货方式(默认自发)
                
                //发货方式
                if(isset($productList[$product_id])){
                    $betc_id = intval($productList[$product_id]['betc_id']);
                    $is_shopyjdf_type = $productList[$product_id]['is_shopyjdf_type'];
                }
                
                //记录发货方式发生变更的基础物料
                if($itemVal['is_shopyjdf_type'] != $is_shopyjdf_type && $is_shopyjdf_type == '2'){
                    if($itemVal['is_delete'] != 'true'){
                        $item['store_code'] = '';
                        $item['branch_id'] = 0;
                        
                        $needFreezeItems[] = $itemVal;
                    }
                }
                
                //汇总发货方式
                $shopyjdf_types[$is_shopyjdf_type] = $is_shopyjdf_type;
                
                //update item
                $itemSdf = array(
                    'betc_id' => $betc_id,
                    'is_shopyjdf_type' => $is_shopyjdf_type,
                    'last_modified' => time(),
                );
                $jxItemMdl->update($itemSdf, array('plat_item_id'=>$itemVal['plat_item_id']));
            }
            
            //object层发货方式
            if(count($shopyjdf_types) > 1){
                $objVal['is_shopyjdf_type'] = '3'; //部分代发货，即有自发货，也有代发货；
            }else{
                $objVal['is_shopyjdf_type'] = current($shopyjdf_types);
            }
            
            //转换状态
            if($objVal['is_shopyjdf_type'] == '' || $objVal['is_shopyjdf_type'] == '0'){
                $objVal['is_shopyjdf_step'] = '0';
            }else{
                $objVal['is_shopyjdf_step'] = '2';
            }
            
            //update object
            $objectSdf = array(
                'is_shopyjdf_step' => $objVal['is_shopyjdf_step'], //转换状态
                'is_shopyjdf_type' => $objVal['is_shopyjdf_type'], //发货方式
                'last_modified' => time(),
            );
            $jxObjectMdl->update($objectSdf, array('plat_obj_id'=>$objVal['plat_obj_id']));
        }
        
        //update order
        $dispose_status = 'zifa';
        if(count($shopyjdf_types) > 1){
            $dispose_status = 'part_daifa'; //部分代发货
        }else{
            $is_shopyjdf_type = current($shopyjdf_types);
            if($is_shopyjdf_type == '2'){
                $dispose_status = 'all_daifa'; //全部代发货
            }elseif($is_shopyjdf_type == '1'){
                $dispose_status = 'zifa'; //全部自发货
            }
        }
        
        $orderSdf = array(
            'dispose_status' => $dispose_status, //转换状态
            'last_modified' => time(),
        );
        $jxOrderMdl->update($orderSdf, array('plat_order_id'=>$plat_order_id));
        
        //库存冻结
        if($needFreezeItems) {
            //库存预占类型
            $freeze_obj_type = material_basic_material_stock_freeze::__ORDER;
            $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
            $bill_type = material_basic_material_stock_freeze::__DEALER_ORDER;
            
            //对数组排序
            uasort($needFreezeItems, [kernel::single('console_iostockorder'), 'cmp_productid']);
            
            //items
            foreach($needFreezeItems as $item)
            {
                $nums = intval($item['nums']);
                $product_id = $item['product_id'];
                
                //增加基础物料冻结数
                $basicMStockLib->freeze($product_id, $nums);
                
                //修改预占库存流水
                $freezeData = array(
                    'bm_id' => $product_id,
                    'obj_type' => $freeze_obj_type,
                    'bill_type' => $bill_type,
                    'obj_id' => $orderInfo['plat_order_id'],
                    'shop_id' => $orderInfo['shop_id'],
                    'branch_id' => $item['branch_id'],
                    'bmsq_id' => $bmsq_id,
                    'num' => $nums,
                    'log_type' => '',
                    'store_code' => $item['store_code'],
                    'obj_bn' => $orderInfo['plat_order_bn'], //平台订单号
                );
                $basicMStockFreezeLib->freeze($freezeData);
            }
        }
        
        //logs
        $log_msg = '没有需要转换的基础物料';
        if($needFreezeItems){
            $productBns = array_column($needFreezeItems, 'bn');
            $log_msg = '基础物料编码：'. implode('、', $productBns) .'转换发货方式成功';
            
        }
        $logMdl->write_log('order_modify@dealer', $plat_order_id, $log_msg);
        
        return $this->succ();
    }
    
    /**
     * 修复经销商失败订单
     * 
     * @param $params
     * @return array
     */
    public function repairOrder($sdf)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $jxObjectMdl = app::get('dealer')->model('platform_order_objects');
        $jxItemMdl = app::get('dealer')->model('platform_order_items');
        $logMdl = app::get('ome')->model('operation_log');
        
        $deMaterialLib = kernel::single('dealer_material');
        $basicMStockLib = kernel::single('material_basic_material_stock');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        //params
        $plat_order_id = $sdf['plat_order_id'];
        
        //平台订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $this->getOrderDetail($filter);
        if(empty($orderInfo)){
            $error_msg = '订单：'. $sdf['plat_order_bn'] .'未找到';
            return $this->error($error_msg);
        }
        
        if($orderInfo['is_fail'] != 'true'){
            $error_msg = '订单：'. $sdf['plat_order_bn'] .'不是失败订单';
            return $this->error($error_msg);
        }
        
        $shop_id = $orderInfo['shop_id'];
        
        //items
        $is_fail_order = false;
        $succGoods = array();
        $failGoods = array();
        $productList = array();
        foreach($orderInfo['order_objects'] as $objKey => $objVal)
        {
            $plat_obj_id = $objVal['plat_obj_id'];
            $obj_quantity = $objVal['quantity'];
            $obj_sale_price = $objVal['sale_price'];
            $obj_amount = $objVal['amount'];
            
            //check
            if($objVal['is_delete'] == 'true'){
                continue;
            }
            
            //有items明细，则跳过
            if($objVal['order_items']){
                continue;
            }
            
            //检查货品是否存在销售物料中
            $salesMInfo = $deMaterialLib->getSaleMaterialInfo($shop_id, $objVal['bn']);
            if(empty($salesMInfo)){
                $is_fail_order = true;
                $failGoods[] = $objVal['bn'];
                
                continue;
            }
            
            //check
            $smIds = array($salesMInfo['sm_id']);
            $bmList = $deMaterialLib->getBasicMatBySmIds($smIds);
            
            //check
            if(empty($bmList)){
                $is_fail_order = true;
                $failGoods[] = $objVal['bn'];
                
                continue;
            }
            
            //组织item数据
            $obj_type = 'goods';
            switch ($salesMInfo['sales_material_type']) {
                case "2":
                    $obj_type = 'pkg';
                    
                    //根据促销总价格计算每个物料的贡献金额值
                    $deMaterialLib->calProSaleMatPriceByRate($obj_sale_price, $bmList);
                    
                    //根据优惠价格计算每个物料的贡献金额值
                    $pmt_price_rate = $deMaterialLib->getPmtPriceByRate($objVal['pmt_price'], $bmList);
                    break;
                case "3":
                    $obj_type = 'gift';
                    break;
            }
            
            //items
            foreach ($bmList as $k => $basicMInfo)
            {
                $product_id = $basicMInfo['bm_id'];
                $material_bn = $basicMInfo['material_bn'];
                
                //type
                if ($obj_type == 'pkg') {
                    $cost = $basicMInfo['cost'];
                    $pmt_price = $pmt_price_rate[$material_bn] ? ($pmt_price_rate[$material_bn]['rate_price'] > 0 ? $pmt_price_rate[$material_bn]['rate_price'] : 0) : 0.00;
                    
                    $sale_price        = $basicMInfo['rate_price'] > 0 ? $basicMInfo['rate_price'] : 0;
                    $amount            = bcadd((float)$pmt_price, (float)$sale_price, 2);
                    $price             = bcdiv($amount, $basicMInfo['number'] * $obj_quantity, 2);
                    $weight            = $basicMInfo['weight'];
                    $shop_product_id   = 0;
                    $divide_order_fee  = 0;
                    $part_mjz_discount = 0;
                    $item_type         = 'pkg';
                    $item_nums         = $basicMInfo['number'] * $obj_quantity;
                } else {
                    $cost              = (float) $objVal['cost'] ? $objVal['cost'] : $basicMInfo['cost'];
                    $price             = (float) $objVal['price'];
                    $pmt_price         = (float) $objVal['pmt_price'];
                    $sale_price        = $objVal['sale_price'];
                    $amount            = $obj_amount;
                    $weight            = (float) $objVal['weight'] ? $objVal['weight'] : ($basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00);
                    $shop_product_id   = $objVal['shop_product_id'] ? $objVal['shop_product_id'] : 0;
                    $item_type         = $obj_type == 'goods' ? 'product' : $obj_type;
                    $divide_order_fee  = $objVal['divide_order_fee'];
                    $part_mjz_discount = $objVal['part_mjz_discount'];
                    $item_nums         = $basicMInfo['number'] * $obj_quantity;
                }
                
                //insert
                $itemSdf = array(
                    'plat_order_id' => $plat_order_id,
                    'plat_obj_id' => $plat_obj_id,
                    'shop_goods_id'     => $objVal['shop_goods_id'] ? $objVal['shop_goods_id'] : 0,
                    'product_id'        => $product_id,
                    'shop_product_id'   => $shop_product_id,
                    'bn'                => $material_bn,
                    'name'              => $basicMInfo['material_name'],
                    'cost'              => $cost ? $cost : 0.00,
                    'price'             => $price ? $price : 0.00,
                    'pmt_price'         => $pmt_price,
                    'sale_price'        => $sale_price ? $sale_price : 0.00,
                    'amount'            => $amount ? $amount : 0.00,
                    'weight'            => $weight ? $weight : 0.00,
                    'nums'              => $item_nums, //购买数量
                    'addon'             => '',
                    'item_type'         => $item_type,
                    'delete'            => ($objVal['status'] == 'close') ? 'true' : 'false',
                    'divide_order_fee'  => $divide_order_fee,
                    'part_mjz_discount' => $part_mjz_discount,
                    'product_attr'      => $objVal['product_attr'] ? $objVal['product_attr'] : "",
                );
                $jxItemMdl->insert($itemSdf);
                
                //products
                $productList[$product_id] = array(
                    'product_id' => $product_id,
                    'product_bn' => $material_bn,
                    'betc_id' => 0, //贸易公司ID
                    'is_shopyjdf_type' => '0', //发货方式
                );
            }
            
            //update
            $jxObjectMdl->update(array('goods_id'=>$salesMInfo['sm_id']), array('plat_obj_id'=>$plat_obj_id));
            
            //succ
            $succGoods[] = $objVal['bn'];
        }
        
        //修复失败
        if($is_fail_order){
            //logs
            $error_msg = ($succGoods ? '订单部分商品修复失败' : '订单修复失败，商品编码：'. implode(',', $failGoods) .'不存在');
            $logMdl->write_log('order_modify@dealer', $sdf['plat_order_id'], $error_msg);
            
            return $this->error($error_msg);
        }
        
        //重新读取订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $this->getOrderDetail($filter);
        
        //通过基础物料获取发货方式：自发、代发，所属贸易公司ID；
        $businessInfo = array('shop_id'=>$shop_id);
        $productList = $this->getProductDespatchType($productList, $businessInfo);
        
        //objects
        $needFreezeItems = array();
        foreach($orderInfo['order_objects'] as $objKey => $object)
        {
            //check
            if(empty($object['order_items'])){
                continue;
            }
            
            if($object['is_delete'] == 'true'){
                continue;
            }
            
            //items
            $shopyjdf_types = array();
            foreach($object['order_items'] as $itemKey => $itemVal)
            {
                $product_id = $itemVal['product_id'];
                $betc_id = 0; //贸易公司ID
                $is_shopyjdf_type = '1'; //发货方式(默认自发)
                
                //check
                if($itemVal['is_delete'] == 'true'){
                    continue;
                }
                
                //已经转换过的则跳过
                if($itemVal['is_shopyjdf_type'] != '0'){
                    continue;
                }
                
                //发货方式
                if(isset($productList[$product_id])){
                    $betc_id = intval($productList[$product_id]['betc_id']);
                    $is_shopyjdf_type = $productList[$product_id]['is_shopyjdf_type'];
                }
                
                $itemVal['betc_id'] = $betc_id;
                $itemVal['is_shopyjdf_type'] = $is_shopyjdf_type;
                
                //汇总发货方式
                $shopyjdf_types[$is_shopyjdf_type] = $is_shopyjdf_type;
                
                //update
                $jxItemMdl->update(array('betc_id'=>$betc_id, 'is_shopyjdf_type'=>$is_shopyjdf_type, 'last_modified'=>time()), array('plat_item_id'=>$itemVal['plat_item_id']));
                
                //freeze
                if($is_shopyjdf_type == '2'){
                    $itemVal['store_code'] = '';
                    $itemVal['branch_id'] = 0;
                    
                    $needFreezeItems[] = $itemVal;
                }
            }
            
            //check
            if(empty($shopyjdf_types)){
                continue;
            }
            
            //object层发货方式
            if(count($shopyjdf_types) > 1){
                $obj_is_shopyjdf_type = '3'; //部分代发货，即有自发货，也有代发货；
            }else{
                $obj_is_shopyjdf_type = current($shopyjdf_types);
            }
            
            //转换状态
            if($obj_is_shopyjdf_type == '0'){
                $is_shopyjdf_step = '0';
            }else{
                $is_shopyjdf_step = '2';
            }
            
            //update
            $jxObjectMdl->update(array('is_shopyjdf_step'=>$is_shopyjdf_step, 'is_shopyjdf_type'=>$obj_is_shopyjdf_type, 'last_modified'=>time()), array('plat_obj_id'=>$object['plat_obj_id']));
        }
        
        //订单转换状态
        $dispose_status = 'zifa';
        $convert_status = '';
        if(count($shopyjdf_types) > 1){
            $dispose_status = 'part_daifa'; //部分代发货
        }else{
            $is_shopyjdf_type = current($shopyjdf_types);
            if($is_shopyjdf_type == '2'){
                $dispose_status = 'all_daifa'; //全部代发货
            }elseif($is_shopyjdf_type == '1'){
                $dispose_status = 'zifa'; //全部自发货
                $convert_status = 'needless'; //无需转单
            }
        }
        
        //update order
        $updateSdf = array('is_fail'=>'false', 'dispose_status'=>$dispose_status, 'last_modified'=>time());
        
        //转单状态
        if($convert_status){
            $updateSdf['convert_status'] = $convert_status;
        }
        
        $jxOrderMdl->update($updateSdf, array('plat_order_id'=>$plat_order_id));
        
        //添加订单预占
        if($needFreezeItems) {
            //库存预占类型
            $freeze_obj_type = material_basic_material_stock_freeze::__ORDER;
            $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
            $bill_type = material_basic_material_stock_freeze::__DEALER_ORDER;
            
            //对数组排序
            uasort($needFreezeItems, [kernel::single('console_iostockorder'), 'cmp_productid']);
            
            //items
            foreach($needFreezeItems as $item)
            {
                $nums = intval($item['nums']);
                $product_id = $item['product_id'];
                
                //增加基础物料冻结数
                $basicMStockLib->freeze($product_id, $nums);
                
                //增加预占库存流水
                $freezeData = array(
                    'bm_id' => $product_id,
                    'obj_type' => $freeze_obj_type,
                    'bill_type' => $bill_type,
                    'obj_id' => $sdf['plat_order_id'],
                    'shop_id' => $sdf['shop_id'],
                    'branch_id' => $item['branch_id'],
                    'bmsq_id' => $bmsq_id,
                    'num' => $nums,
                    'log_type' => '',
                    'store_code' => $item['store_code'],
                    'obj_bn' => $sdf['plat_order_bn'], //平台订单号
                );
                $basicMStockFreezeLib->freeze($freezeData);
            }
            
            //[延迟自动审单]设置hold单，防止避免发货前退款；并且放入队列任务里,延迟自动审单；
            $this->setOrderHoldTime($plat_order_id);
        }
        
        //logs
        $logMdl->write_log('order_modify@dealer', $sdf['plat_order_id'], '失败订单修复成功');
        
        return $this->succ();
    }
    
    /**
     * 取消订单
     * 参考OMS取消订单：kernel::single('ome_batch_order')->create_refund($sdf['order_id']);
     * 
     * @param $sdf
     * @return void
     */
    public function closeOrder($sdf)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        //params
        $plat_order_id = $sdf['plat_order_id'];
        
        //平台订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $jxOrderMdl->dump($filter, '*');
        if(empty($orderInfo)){
            $error_msg = '订单：'. $sdf['plat_order_bn'] .'未找到';
            return $this->error($error_msg);
        }
        
        //check
        if($orderInfo['status'] == 'dead'){
            $error_msg = '订单：'. $sdf['plat_order_bn'] .'已经是：已作废的状态';
            return $this->error($error_msg);
        }
        
        if($orderInfo['pay_status'] == '5' || $orderInfo['process_status'] == 'cancel'){
            $error_msg = '订单：'. $sdf['plat_order_bn'] .'已经是：全额退款、已取消的状态';
            return $this->error($error_msg);
        }
        
        //更新已付金额
        if($orderInfo['payed'] > 0){
            $updateSql ="UPDATE sdb_dealer_platform_orders SET payed=IF((CAST(payed AS char)-IFNULL(0,cost_payment)-". $orderInfo['payed'] .")>=0,payed-IFNULL(0,cost_payment)-". $orderInfo['payed'] .",0)  ";
            $updateSql .= " WHERE order_id=". $plat_order_id;
            $jxOrderMdl->db->exec($updateSql);
        }
        
        //更新订单支付状态
        $isUpdate = $this->update_dealer_order_pay_status($plat_order_id);
        
        //logs
        $logMdl->write_log('order_modify@dealer', $sdf['plat_order_id'], '取消订单成功');
        
        return $this->succ();
    }
    
    /**
     * 更新订单支付状态&&打回发货单
     * 
     * @param $plat_order_id
     * @return bool
     */
    public function update_dealer_order_pay_status($plat_order_id)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $apLogMdl = app::get('ome')->model('api_log');
        
        //setting
        $updateSdf = array();
        $logTitle = '更新经销商订单支付状态[订单ID：' . $plat_order_id . ']';
        $logInfo  = '更新经销商订单ID：'. $plat_order_id .' <br>';
        $payStatusList = array(
            0 => '未支付',
            1 => '已支付',
            2 => '处理中',
            3 => '部分付款',
            4 => '部分退款',
            5 => '全额退款',
            6 => '退款申请中',
            7 => '退款中',
            8 => '支付中',
        );
        
        //获取订单详细信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $this->getOrderDetail($filter);
        $payed = strval($orderInfo['payed']);
        $total_amount = strval($orderInfo['total_amount']);
        
        //金额相减运算
        $total_amount = kernel::single('eccommon_math')->number_minus(array($total_amount, $orderInfo['refund_money']));
        
        //logInfo
        $logInfo .= '订单信息：<BR>' . var_export($orderInfo, true) . '<BR>';
        $logInfo .= '当前支付金额：' . $payed . '(支付状态：' . $payStatusList[$orderInfo['pay_status']] .')<BR>';
        $logInfo .= '当前总计金额：' . $total_amount . '<BR>';
        
        //支付状态
        $pay_status = '';
        if ($payed == '0' && $total_amount > '0') {
            $pay_status = '0'; //未支付
        } elseif ($payed < $total_amount) {
            $pay_status = '3'; //部分支付
        } elseif ($payed >= $total_amount) {
            $pay_status = '1'; //已支付
        }
        
        //退款状态
        if ($payed == '0') {
            //全额退款
            $pay_status = '5';
            
            //取消经销商订单
            $cancelRs = $this->canceldealerOrder($orderInfo);
            if($cancelRs['rsp'] != 'succ'){
                $logInfo .= '取消订单失败：'. $cancelRs['error_msg'];
            }
            
            $logInfo .= '全额退款并且未发货的取消订单ID：' . $plat_order_id . '<BR>';
        } elseif ($payed < $total_amount) {
            //部分退款
            $pay_status = '4';
            
            //取消取消OMS订单、OMS发货单（取消失败时需要打标异常）
            $cancelResult = $this->cancelOmsOrder($orderInfo);
            if($cancelResult['rsp'] != 'succ'){
                //打标异常
                $updateSdf['is_abnormal'] = 'true';
                
                //异常类型
                $abnormal_status = $orderInfo['abnormal_status'];
                $abnormal_status = $abnormal_status | dealer_operation_const::__CANCEL_OMS_ORDER;
                $updateSdf['abnormal_status'] = $abnormal_status;
            }else{
                //去除异常标识
                $updateSdf['is_abnormal'] = 'false';
                
                //清除异常类型
                $orderInfo['abnormal_status'] = $orderInfo['abnormal_status'] ^ dealer_operation_const::__CANCEL_OMS_ORDER;
                $updateSdf['abnormal_status'] = $orderInfo['abnormal_status'];
            }
        }
        
        //订单支付状态
        if($pay_status && $pay_status != $orderInfo['pay_status']){
            $updateSdf['pay_status'] = $pay_status;
            
            $logInfo .= '更新支付状态为：' . $payStatusList[$pay_status] .'<BR>';
        }
        
        //更新订单数据
        if($updateSdf){
            $updateSdf['last_modified'] = time();
            $jxOrderMdl->update($updateSdf, array('plat_order_id'=>$plat_order_id));
        }
        
        //logs
        $logsdf = array(
            'log_id'        => $apLogMdl->gen_id(),
            'task_name'     => $logTitle,
            'status'        => 'success',
            'worker'        => '',
            'params'        => json_encode([$logInfo], JSON_UNESCAPED_UNICODE),
            'transfer'      => '[]',
            'response'      => '[]',
            'msg'           => '支付状态更新成功',
            'log_type'      => '',
            'api_type'      => 'response',
            'memo'          => '',
            'original_bn'   => $orderInfo['plat_order_bn'],
            'createtime'    => time(),
            'last_modified' => time(),
            'msg_id'        => '',
            'spendtime'     => '0',
        );
        $apLogMdl->insert($logsdf);
        
        return true;
    }
    
    /**
     * 取消经销商订单产生的OMS订单、OMS发货单
     * 
     * @param $orderInfo
     * @return void
     */
    public function cancelOmsOrder($orderInfo)
    {
        $orderMdl = app::get('ome')->model('orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        //params
        $plat_order_id = $orderInfo['plat_order_id'];
        
        //format
        $erpOrders = array();
        foreach($orderInfo['order_objects'] as $objKey => $objVal)
        {
            //check
            if(empty($objVal['order_items'])){
                continue;
            }
            
            //items
            foreach($objVal['order_items'] as $itemKey => $itemVal)
            {
                $erp_order_id = $itemVal['erp_order_id'];
                
                //check
                if($itemVal['is_shopyjdf_type'] != '2'){
                    continue;
                }
                
                if(empty($itemVal['erp_order_bn'])){
                    continue;
                }
                
                $erpOrders[$erp_order_id] = $itemVal['erp_order_bn'];
            }
        }
        
        //check没有关联的OMS订单
        if(empty($erpOrders)){
            return $this->succ('没有ERP订单');
        }
        
        //ERP订单列表
        $orderList = $orderMdl->getList('order_id,order_bn,process_status', array('order_bn'=>$erpOrders));
        if(empty($orderList)){
            return $this->succ('没有查找到ERP订单');
        }
        
        //orders
        $cancelMsgs = array();
        foreach ($orderList as $key => $orderRow)
        {
            $order_id = $orderRow['order_id'];
            $order_bn = $orderRow['order_bn'];
            
            //check
            if(!in_array($orderRow['process_status'], array('confirmed','splitting','splited'))){
                continue;
            }
            
            //取消发货单
            $cancelDlyResult = $orderMdl->cancel_delivery($order_id);
            if($cancelDlyResult['rsp'] != 'succ'){
                if($cancelDlyResult['succ_num'] > 0){
                    //只有部分发货单取消成功
                    $cancelMsgs[] = 'ERP订单号：'. $order_bn .'取消发货单部分失败';
                }else{
                    //发货单取消失败
                    $cancelMsgs[] = 'ERP订单号：'. $order_bn .'取消发货单失败';
                }
            }else{
                //发货单取消成功
                $cancelMsgs[] = 'ERP订单号：'. $order_bn .'取消发货单成功';
            }
        }
        
        //logs取消发货单日志
        if($cancelMsgs){
            $log_msg = implode('；', $cancelMsgs);
            $logMdl->write_log('order_back@dealer', $plat_order_id, $log_msg);
        }
        
        //取消OMS订单
        $cancelFail = array();
        $cancelSucc = array();
        foreach ($orderList as $key => $orderRow)
        {
            $order_id = $orderRow['order_id'];
            $order_bn = $orderRow['order_bn'];
            
            //check
            if(in_array($orderRow['process_status'], array('cancel'))){
                continue;
            }
            
            //获取订单关联未取消的发货单(包含：已经完成发货的发货单)
            $deliveryList = array();
            if(!in_array($orderRow['process_status'], array('unconfirmed'))){
                $sql = "SELECT d.delivery_id,d.delivery_bn,d.is_bind,d.status FROM sdb_ome_delivery_order AS dord
                    LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id)
                    WHERE dord.order_id=". $order_id ." AND d.disabled='false' AND d.parent_id=0 AND d.status NOT IN('cancel','back','return_back')";
                $deliveryList = $orderMdl->db->select($sql);
            }
            
            //有发货单则异常
            if($deliveryList){
                $cancelFail[] = $order_bn;
                continue;
            }
            
            //取消OMS订单
            $cancelResult = $orderMdl->cancel($order_id, '经销商订单请求取消OMS订单', false, 'async');
            $cancelResult['rsp'] = ($cancelResult['rsp'] == 'success' ? 'succ' : $cancelResult['rsp']);
            if($cancelResult['rsp'] != 'succ'){
                //fail
                $cancelFail[] = $order_bn;
            }else{
                //succ
                $cancelSucc[] = $order_bn;
            }
        }
        
        //check
        if($cancelFail){
            $error_msg = '取消失败的OMS订单：'. implode('、', $cancelFail);
            
            //logs
            $log_msg = '';
            if($cancelSucc){
                $log_msg .= '取消成功的OMS订单：'. implode('、', $cancelSucc) .'；';
            }
            $log_msg .= $error_msg;
            $logMdl->write_log('order_back@dealer', $plat_order_id, $log_msg);
            
            return $this->error($error_msg, $cancelFail);
        }
        
        //logs
        $log_msg = '取消成功的OMS订单：'. implode('、', $cancelSucc) .'；';
        $logMdl->write_log('order_back@dealer', $plat_order_id, $log_msg);
        
        return $this->succ($log_msg, $cancelSucc);
    }
    
    /**
     * 取消经销商订单产生的OMS发货单
     * 
     * @param $orderInfo
     * @return void
     */
    public function cancelOrderDelivery($orderInfo)
    {
        
        return $this->succ();
    }
    
    /**
     * 取消经销商订单
     * 
     * @param $orderInfo
     * @return void
     */
    public function canceldealerOrder($orderInfo)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $orderMdl = app::get('ome')->model('orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        //params
        $plat_order_id = $orderInfo['plat_order_id'];
        $updateSdf = array();
        
        //先取消OMS订单、OMS发货单（取消失败时需要打标异常）
        $cancelResult = $this->cancelOmsOrder($orderInfo);
        if($cancelResult['rsp'] != 'succ'){
            $error_msg = '取消OMS订单、发货单失败：'. $cancelResult['error_msg'];
            
            //打标异常
            $updateSdf['is_abnormal'] = 'true';
            
            //异常类型
            $abnormal_status = $orderInfo['abnormal_status'];
            $abnormal_status = $abnormal_status | dealer_operation_const::__CANCEL_OMS_ORDER;
            $updateSdf['abnormal_status'] = $abnormal_status;
            $updateSdf['last_modified'] = time();
            
            //update
            $jxOrderMdl->update($updateSdf, array('plat_order_id'=>$plat_order_id));
            
            //logs
            $logMdl->write_log('order_back@dealer', $plat_order_id, $error_msg);
            
            return $this->error($error_msg);
        }
        
        //[取消OMS订单成功]去除异常标识
        $updateSdf['is_abnormal'] = 'false';
        
        //[取消OMS订单成功]清除异常类型
        $orderInfo['abnormal_status'] = $orderInfo['abnormal_status'] ^ dealer_operation_const::__CANCEL_OMS_ORDER;
        $updateSdf['abnormal_status'] = $orderInfo['abnormal_status'];
        
        //format
        $erpOrders = array();
        foreach($orderInfo['order_objects'] as $objKey => $objVal)
        {
            //check
            if(empty($objVal['order_items'])){
                continue;
            }
            
            //items
            foreach($objVal['order_items'] as $itemKey => $itemVal)
            {
                $erp_order_id = $itemVal['erp_order_id'];
                
                //check
                if($itemVal['is_shopyjdf_type'] != '2'){
                    continue;
                }
                
                if(empty($itemVal['erp_order_bn'])){
                    continue;
                }
                
                $erpOrders[$erp_order_id] = $itemVal['erp_order_bn'];
            }
        }
        
        //ERP订单列表
        $orderList = array();
        if($erpOrders){
            $orderList = $orderMdl->getList('order_id,order_bn,process_status', array('order_bn'=>$erpOrders));
            foreach ((array)$orderList as $key => $orderRow)
            {
                //已经取消的订单
                if(in_array($orderRow['process_status'], array('cancel'))){
                    unset($orderList[$key]);
                }
            }
        }
        
        //check
        if($orderList){
            $orderBns = array_column($orderList, 'order_bn');
            $error_msg = '无法取消订单，存在OMS订单号：'. implode('、', $orderBns);
            
            //打标异常
            $updateSdf['is_abnormal'] = 'true';
            
            //异常类型
            $abnormal_status = $orderInfo['abnormal_status'];
            $abnormal_status = $abnormal_status | dealer_operation_const::__CANCEL_DEALER_ORDER;
            $updateSdf['abnormal_status'] = $abnormal_status;
            
            //update
            $jxOrderMdl->update($updateSdf, array('plat_order_id'=>$plat_order_id));
            
            //logs
            $logMdl->write_log('order_back@dealer', $plat_order_id, $error_msg);
            
            return $this->error($error_msg, $orderBns);
        }else{
            //所有OMS订单都已经取消成功
            //清除异常类型
            $orderInfo['abnormal_status'] = $orderInfo['abnormal_status'] ^ dealer_operation_const::__CANCEL_DEALER_ORDER;
            $updateSdf['abnormal_status'] = $orderInfo['abnormal_status'];
            
            //更新状态
            $updateSdf['process_status'] = 'cancel';
            $updateSdf['convert_status'] = 'fail';
            $updateSdf['status'] = 'dead';
            $updateSdf['archive'] = 1; //订单归档
            
            //update
            $jxOrderMdl->update($updateSdf, array('plat_order_id'=>$plat_order_id));
            
            //释放经销商订单库存预占
            $this->unOrderFreeze($orderInfo);
            
            //logs
            $error_msg = '取消经销订单成功';
            $logMdl->write_log('order_back@dealer', $plat_order_id, $error_msg);
        }
        
        return $this->succ();
    }
    
    /**
     * 订单暂停
     * 
     * @param int $order_id
     * @return array
     */
    public function pauseDealerOrder($plat_order_id)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        //平台订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $this->getOrderDetail($filter);
        if(empty($orderInfo)){
            $error_msg = '订单信息不存在';
            return $this->error($error_msg);
        }
        
        //订单已经是暂停状态,直接返回
        if ($orderInfo['pause'] != 'false'){
            $error_msg = '订单已经是暂停状态,直接返回成功';
            return $this->succ($error_msg);
        }
        
        //取消OMS订单并且撤消发货单
        $cancelResult = $this->cancelOmsOrder($orderInfo);
        if($cancelResult['rsp'] != 'succ'){
            $updateSdf = array();
            
            //打标异常
            $updateSdf['is_abnormal'] = 'true';
            
            //异常类型
            $abnormal_status = $orderInfo['abnormal_status'];
            $abnormal_status = $abnormal_status | dealer_operation_const::__PAUSE_DEALER_ORDER;
            $updateSdf['abnormal_status'] = $abnormal_status;
            
            $jxOrderMdl->update($updateSdf, array('plat_order_id'=>$plat_order_id));
            
            return $this->error($cancelResult['error_msg']);
        }
        
        //更新订单状态
        $updateSdf = array();
        $updateSdf['process_status'] = 'unconfirmed';
        $updateSdf['convert_status'] = 'unconvert';
        $updateSdf['pause'] = 'true';
        
        $jxOrderMdl->update($updateSdf, array('plat_order_id'=>$plat_order_id));
        
        //logs
        $log_msg = '暂停订单成功';
        $logMdl->write_log('order_modify@dealer', $plat_order_id, $log_msg);
        
        return $this->succ($log_msg);
    }
    
    /**
     * 订单恢复
     * 
     * @param int $plat_order_id
     * @return boolean
     */
    public function renewDealerOrder($plat_order_id)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        //平台订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $this->getOrderDetail($filter);
        if(empty($orderInfo)){
            $error_msg = '订单信息不存在';
            return $this->error($error_msg);
        }
        
        //订单已经是暂停状态,直接返回
        if ($orderInfo['pause'] != 'true'){
            $error_msg = '订单不是暂停状态,直接返回成功';
            return $this->succ($error_msg);
        }
        
        //更新订单状态
        $updateSdf = array();
        $updateSdf['pause'] = 'false';
        
        $jxOrderMdl->update($updateSdf, array('plat_order_id'=>$plat_order_id));
        
        //logs
        $log_msg = '暂停恢复成功';
        $logMdl->write_log('order_modify@dealer', $plat_order_id, $log_msg);
        
        return $this->succ($log_msg);
    }
    
    /**
     * OMS订单发货后更新经销订单数据
     * 
     * @param $orderIds
     * @return void
     */
    public function updateDlyOrders($orderIds)
    {
        $orderMdl = app::get('ome')->model('orders');
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $jxItemMdl = app::get('dealer')->model('platform_order_items');
        
        //check
        if(empty($orderIds)){
            $error_msg = '无效的更新操作';
            return $this->error($error_msg);
        }
        
        //ERP订单
        $orderList = $orderMdl->getList('order_id,order_bn,platform_order_bn,process_status,ship_status', array('order_id'=>$orderIds), 0, -1);
        if(empty($orderList)){
            $error_msg = '没有查询到ERP订单';
            return $this->error($error_msg);
        }
        
        //按ERP订单号进行更新明细发货状态
        foreach ($orderList as $key => $val)
        {
            $order_bn = $val['order_bn'];
            
            //update
            $update_sql = "UPDATE sdb_dealer_platform_order_items SET ship_status='". $val['ship_status'] ."', sendnum=nums WHERE erp_order_bn='". $order_bn ."'";
            $orderMdl->db->exec($update_sql);
        }
        
        //经销订单
        $platOrderBns = array_column($orderList, 'platform_order_bn');
        $jxOrderList = $jxOrderMdl->getList('plat_order_id,plat_order_bn', array('plat_order_bn'=>$platOrderBns));
        if(empty($jxOrderList)){
            $error_msg = '没有关联的经销订单';
            return $this->error($error_msg);
        }
        
        $platOrderIds = array_column($jxOrderList, 'plat_order_id');
        
        //汇总经销订单明细
        $filter = array('plat_order_id'=>$platOrderIds, 'is_delete'=>'false', 'is_shopyjdf_type'=>'2');
        $jxItemList = $jxItemMdl->getList('plat_item_id,plat_order_id,plat_obj_id,ship_status', $filter, 0, -1);
        
        //items
        $orderShipStatus = array();
        foreach ((array)$jxItemList as $itemKey => $itemVal)
        {
            $plat_order_id = $itemVal['plat_order_id'];
            $ship_status = $itemVal['ship_status'];
            
            //order
            $orderShipStatus[$plat_order_id][$ship_status] = $ship_status;
        }
        
        //更新经销订单发货状态
        foreach ($orderShipStatus as $plat_order_id => $shipItem)
        {
            if(count($shipItem) > 1){
                $ship_status = '2';
            }else{
                $ship_status = current($shipItem);
            }
            
            //data
            $updateData = array('ship_status'=>$ship_status, 'last_modified'=>time());
            if(in_array($ship_status, array('1','4'))){
                $updateData['status'] = 'finish'; //已完成
            }
            
            //update
            $jxOrderMdl->update($updateData, array('plat_order_id'=>$plat_order_id));
        }
        
        return $this->succ('更新经销订单成功');
    }
    
    /**
     * 获取支付状态名称
     * 
     * @return string
     */
    public function getPayStatusName($pay_status)
    {
        $payStatusList = array(
            0 => '未支付',
            1 => '已支付',
            2 => '处理中',
            3 => '部分付款',
            4 => '部分退款',
            5 => '全额退款',
            6 => '退款申请中',
            7 => '退款中',
            8 => '支付中',
        );
        
        return $payStatusList[$pay_status];
    }
    
    /**
     * 获取订单状态名称
     * 
     * @return string
     */
    public function getShipStatusName($ship_status)
    {
        $shipStatusList = array(
            0 => '未发货',
            1 => '已发货',
            2 => '部分发货',
            3 => '部分退货',
            4 => '已退货',
        );
        
        return $shipStatusList[$ship_status];
    }
    
    /**
     * 生成本地经销订单号
     * 
     * @param $flag
     * @return string
     */
    public function gen_plat_order_bn($flag='local')
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        
        $i = rand(0,9999);
        
        do{
            if(9999==$i){
                $i=0;
            }
            
            $i++;
            
            $prefix = '';
            if ($flag == 'local'){
                $prefix = 'L';
            }elseif ($flag == 'change'){
                $prefix = 'C';
            }elseif ($flag == 'bufa'){
                $prefix = 'B';
            }elseif($flag){
                $prefix = $flag;
            }
            
            //order_bn
            $plat_order_bn = $prefix . date('YmdH') .'90'. str_pad($i,6,'0',STR_PAD_LEFT);
            
            //select
            $row = $jxOrderMdl->dump(array('plat_order_bn'=>$plat_order_bn), 'plat_order_id');
        }while($row);
        
        return $plat_order_bn;
    }
    
    /**
     * [已经作废]获取smart订单价格(直接使用基础物料的销售价)
     * 
     * @param $orderInfo
     * @return array|void
     */
    public function updateOmsOrderMoney($orderInfo)
    {
        $orderMdl = app::get('ome')->model('orders');
        $orderObjMdl = app::get('ome')->model('order_objects');
        $orderItemMdl = app::get('ome')->model('order_items');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $logMdl = app::get('ome')->model('operation_log');
        
        //objects
        $productIds = array();
        foreach ($orderInfo['order_objects'] as $objKey => $objVal)
        {
            //items
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $product_id = $itemVal['product_id'];
                
                $productIds[$product_id] = $product_id;
            }
        }
        
        //获取基础物料价格
        $materialList = $basicMaterialExtObj->getList('bm_id,cost,retail_price,weight,unit', array('bm_id'=>$productIds));
        $materialList = array_column($materialList, null, 'bm_id');
        if(empty($materialList)){
            $error_msg = '获取基础物料价格失败：';
            
            //logs
            $logMdl->write_log('order_confirm@ome', $orderInfo['order_id'], $error_msg);
            
            return $this->error($error_msg);
        }
        
        //objects
        foreach ($orderInfo['order_objects'] as $objKey => $objVal)
        {
            $obj_quantity = $objVal['quantity'];
            
            //check
            if($objVal['delete'] == 'true'){
                continue;
            }
            
            //items
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $product_id = $itemVal['product_id'];
                $item_nums = $itemVal['nums'];
                
                //check
                if($itemVal['delete'] == 'true'){
                    continue;
                }
                
                //check
                if(empty($materialList[$product_id])){
                    $error_msg = '基础物料编码：'. $itemVal['bn'] .'相关金额不存在';
                    
                    //logs
                    $logMdl->write_log('order_confirm@ome', $orderInfo['order_id'], $error_msg);
                    
                    return $this->error($error_msg);
                }
                
                //基础物料金额
                $retail_price = ($materialList[$product_id]['retail_price'] ? $materialList[$product_id]['retail_price'] : 0.00);
                $material_cost = ($materialList[$product_id]['cost'] ? $materialList[$product_id]['cost'] : 0.00);
                
                //amount
                $cost = $material_cost; //成本价
                $price = $retail_price; //零售价
                $pmt_price = 0.00; //优惠小计
                $amount = $price * $item_nums; //零售小计 = price * nums
                $sale_price = $amount - $pmt_price; //销售小计 = amount - pmt_price
                $part_mjz_discount = 0.00; //优惠分摊
                $divide_order_fee = $sale_price - $part_mjz_discount; //订单实付金额 = sale_price - part_mjz_discount
                
                //更新订单items层明细金额
                $updateItemData = array(
                    'cost' => $cost,
                    'price' => $price,
                    'pmt_price' => $pmt_price,
                    'amount' => $amount,
                    'sale_price' => $sale_price,
                    'part_mjz_discount' => $part_mjz_discount,
                    'divide_order_fee' => $divide_order_fee,
                );
                $orderItemMdl->update($updateItemData, array('item_id'=>$itemVal['item_id']));
                
                //计算object层明细金额
                $objVal['amount'] += $amount;
                $objVal['pmt_price'] += $pmt_price;
                $objVal['sale_price'] += $sale_price;
                $objVal['part_mjz_discount'] += $part_mjz_discount;
                $objVal['divide_order_fee'] += $divide_order_fee;
            }
            
            //更新订单objects层明细金额
            $updateObjectData = array(
                'price' => number_format($objVal['amount'] / $obj_quantity, 2, '.', ' '),
                'pmt_price' => $objVal['pmt_price'],
                'amount' => $objVal['amount'],
                'sale_price' => $objVal['sale_price'],
                'part_mjz_discount' => $objVal['part_mjz_discount'],
                'divide_order_fee' => $objVal['divide_order_fee'],
            );
            $orderObjMdl->update($updateObjectData, array('obj_id'=>$objVal['obj_id']));
            
            //计算订单总金额
            $orderInfo['cost_item'] += $updateObjectData['amount'];
            $orderInfo['pmt_goods'] += $updateObjectData['pmt_price'];
            $orderInfo['total_amount'] += $updateObjectData['sale_price'];
            $orderInfo['payed'] += $updateObjectData['divide_order_fee'];
        }
        
        //更新订单总金额
        $updateOrderData = array(
            'pay_status' => '1',
            'cost_item' => $orderInfo['cost_item'], //商品金额
            'pmt_goods' => $orderInfo['pmt_goods'], //订单商品优惠
            'total_amount' => $orderInfo['total_amount'], //订单总额
            'final_amount' => $orderInfo['total_amount'], //订单换算汇率后总额
            'payed' => $orderInfo['payed'], //已付金额
            'discount' => 0.00, //订单折扣
            'pmt_order' => 0.00, //订单优惠
        );
        $orderMdl->update($updateOrderData, array('order_id'=>$orderInfo['order_id']));
        
        //创建支付单
        $paymentData = array(
            'order_id' => $orderInfo['order_id'],
            'shop_id' => $orderInfo['shop_id'],
            'money' => $orderInfo['payed'],
            'memo' => $orderInfo['platform_order_bn'],
        );
        kernel::single('ome_order')->_paymentadd($paymentData);
        
        //logs
        $logMdl->write_log('order_confirm@ome', $orderInfo['order_id'], '请求Smart成功：更新订单金额并创建支付单成功');
        
        return $this->succ();
    }
    
    /**
     * 获取smart订单价格(使用平台订单明细上的金额)
     * 
     * @param $orderInfo
     * @return array|void
     */
    public function updateSmartOrderMoney($orderInfo)
    {
        $orderMdl = app::get('ome')->model('orders');
        $orderObjMdl = app::get('ome')->model('order_objects');
        $orderItemMdl = app::get('ome')->model('order_items');
        $logMdl = app::get('ome')->model('operation_log');
        
        //平台订单信息
        $filter = array('plat_order_bn'=>$orderInfo['platform_order_bn']);
        $dealerOrderInfo = $this->getOrderDetail($filter);
        if(empty($dealerOrderInfo)){
            $error_msg = '平台订单信息不存在';
            return $this->error($error_msg);
        }
        
        //objects
        $dealerObjects = array();
        foreach($dealerOrderInfo['order_objects'] as $objKey => $objVal)
        {
            $goods_bn = $objVal['bn'];
            
            //check
            if(empty($objVal['order_items'])){
                continue;
            }
            
            if($objVal['is_delete'] == 'true'){
                continue;
            }
            
            //obj
            $dealerObjects[$goods_bn] = $objVal;
            unset($dealerObjects[$goods_bn]['order_items']);
            
            //items
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $product_bn = $itemVal['bn'];
                
                $dealerObjects[$goods_bn]['order_items'][$product_bn] = $itemVal;
            }
        }
        
        //objects
        foreach ($orderInfo['order_objects'] as $objKey => $objVal)
        {
            $goods_bn = $objVal['bn'];
            $obj_quantity = $objVal['quantity'];
            
            //check
            if($objVal['delete'] == 'true'){
                continue;
            }
            
            //items
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $product_bn = $itemVal['bn'];
                //$product_id = $itemVal['product_id'];
                //$item_nums = $itemVal['nums'];
                
                //itemInfo
                $dealerItemInfo = $dealerObjects[$goods_bn]['order_items'][$product_bn];
                
                //check
                if($itemVal['delete'] == 'true'){
                    continue;
                }
                
                //amount
                $cost = $dealerItemInfo['cost']; //成本价
                $price = $dealerItemInfo['price']; //零售价
                $amount = $dealerItemInfo['amount']; //零售小计 = price * nums
                $sale_price = $dealerItemInfo['sale_price']; //销售小计 = amount - pmt_price
                $divide_order_fee = $dealerItemInfo['divide_order_fee']; //订单实付金额 = sale_price - part_mjz_discount
                
                $pmt_price = $dealerItemInfo['pmt_price'] + $dealerItemInfo['part_mjz_discount']; //优惠小计
                $part_mjz_discount = 0; //优惠分摊
                
                //更新订单items层明细金额
                $updateItemData = array(
                    'cost' => $cost,
                    'price' => $price,
                    'pmt_price' => $pmt_price,
                    'amount' => $amount,
                    'sale_price' => $sale_price,
                    'part_mjz_discount' => $part_mjz_discount,
                    'divide_order_fee' => $divide_order_fee,
                );
                $orderItemMdl->update($updateItemData, array('item_id'=>$itemVal['item_id']));
                
                //计算object层明细金额
                $objVal['amount'] += $amount;
                $objVal['pmt_price'] += $pmt_price;
                $objVal['sale_price'] += $sale_price;
                $objVal['part_mjz_discount'] += $part_mjz_discount;
                $objVal['divide_order_fee'] += $divide_order_fee;
            }
            
            //更新订单objects层明细金额
            $updateObjectData = array(
                'price' => number_format($objVal['amount'] / $obj_quantity, 2, '.', ''),
                'pmt_price' => $objVal['pmt_price'],
                'amount' => $objVal['amount'],
                'sale_price' => $objVal['sale_price'],
                'part_mjz_discount' => $objVal['part_mjz_discount'],
                'divide_order_fee' => $objVal['divide_order_fee'],
            );
            $orderObjMdl->update($updateObjectData, array('obj_id'=>$objVal['obj_id']));
            
            //计算订单总金额
            $orderInfo['cost_item'] += $updateObjectData['amount'];
            $orderInfo['pmt_goods'] += ($updateObjectData['pmt_price'] + $updateObjectData['part_mjz_discount']);
            $orderInfo['total_amount'] += $updateObjectData['sale_price'];
            $orderInfo['payed'] += $updateObjectData['divide_order_fee'];
        }
        
        //更新订单总金额
        $updateOrderData = array(
            'pay_status' => '1',
            'cost_item' => $orderInfo['cost_item'], //商品金额
            'pmt_goods' => $orderInfo['pmt_goods'], //订单商品优惠
            'total_amount' => $orderInfo['total_amount'], //订单总额
            'final_amount' => $orderInfo['total_amount'], //订单换算汇率后总额
            'payed' => $orderInfo['payed'], //已付金额
            'discount' => 0.00, //订单折扣
            'pmt_order' => 0.00, //订单优惠
        );
        $orderMdl->update($updateOrderData, array('order_id'=>$orderInfo['order_id']));
        
        //创建支付单
        $paymentData = array(
            'order_id' => $orderInfo['order_id'],
            'shop_id' => $orderInfo['shop_id'],
            'money' => $orderInfo['payed'],
            'memo' => $orderInfo['platform_order_bn'],
        );
        kernel::single('ome_order')->_paymentadd($paymentData);
        
        //logs
        $logMdl->write_log('order_confirm@ome', $orderInfo['order_id'], '请求Smart接口成功：更新订单金额并且创建支付单成功');
        
        return $this->succ();
    }
    
    /**
     * 获取ERP分销订单、发货单信息
     * 
     * @param $filter
     * @return array
     */
    public function getFenxiaoErpInfo($filter)
    {
        $orderMdl = app::get('ome')->model('orders');
        $deliveryMdl = app::get('ome')->model('delivery');
        $dlyItemMdl = app::get('ome')->model('delivery_items');
        $branchMdl = app::get('ome')->model('branch');
        $corpMdl = app::get('ome')->model('dly_corp');
        
        $businessLib = kernel::single('dealer_business');
        
        //filter
        $filter = array_filter($filter);
        if(empty($filter)){
            return array();
        }
        
        //order
        $orderInfo = $orderMdl->dump($filter, '*', array('order_objects'=>array('*', array('order_items'=>array('*')))));
        if(empty($orderInfo)){
            return array();
        }
        
        //获取贸易公司列表
        $betcList = $businessLib->getAssignBetcs();
        
        //delivery_status
        $dlyStatus = array (
            'succ' => '已发货',
            'failed' => '发货失败',
            'cancel' => '已取消',
            'progress' => '等待配货',
            'timeout' => '超时',
            'ready' => '待处理',
            'stop' => '暂停',
            'back' => '打回',
            'return_back'=>'退回',
        );
        
        //分销订单信息
        $fenxiaoOrder = array();
        foreach ($orderInfo['order_objects'] as $objKey => $objVal)
        {
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $itemVal['order_bn'] = $orderInfo['order_bn'];
                $itemVal['download_time'] = $orderInfo['download_time'];
                $itemVal['cos_id'] = $orderInfo['cos_id'];
                
                $itemVal['oid'] = $objVal['oid'];
                $itemVal['goods_bn'] = $objVal['bn'];
                $itemVal['goods_name'] = $objVal['name'];
                
                //贸易公司
                $itemVal['betc_id'] = $orderInfo['betc_id'];
                if($itemVal['betc_id']){
                    $itemVal['betc_name'] = $betcList[$itemVal['betc_id']]['betc_name'];
                }
                
                $fenxiaoOrder[] = $itemVal;
            }
        }
        
        //分销发货单信息
        $fenxiaoDlys = array();
        $fields = 'branch_id,create_time,delivery_id,delivery_bn,logi_id,logi_no,logi_name,ship_name,delivery,branch_id,stock_status,deliv_status,expre_status,status,weight,betc_id';
        $tempList = $deliveryMdl->getDeliveryByOrder($fields, $orderInfo['order_id']);
        if($tempList){
            $deliveryIds = array_column($tempList, 'delivery_id');
            $deliveryList = array_column($tempList, null, 'delivery_id');
            
            //branch_id
            $branchIds = array_column($tempList, 'branch_id');
            
            //logi_id
            $logiIds = array_column($tempList, 'logi_id');
            
            //仓库信息
            $branchList = $branchMdl->getList('branch_id,branch_bn,name', array('branch_id'=>$branchIds), 0, -1);
            $branchList = array_column($branchList, null, 'branch_id');
            
            //物料公司信息
            $corpList = $corpMdl->getList('corp_id,type,name', array('corp_id'=>$logiIds), 0, -1);
            $corpList = array_column($corpList, null, 'corp_id');
            
            //items
            $itemList = $dlyItemMdl->getList('*', array('delivery_id'=>$deliveryIds));
            
            //items
            foreach($itemList as $itemKey => $itemVal)
            {
                $delivery_id = $itemVal['delivery_id'];
                
                $deliveryInfo = $deliveryList[$delivery_id];
                $branch_id = intval($deliveryInfo['branch_id']);
                $logi_id = intval($deliveryInfo['logi_id']);
                
                //merge
                $itemVal = array_merge($itemVal, $deliveryInfo);
                
                //status
                $itemVal['delivery_status'] = $dlyStatus[$deliveryInfo['status']];
                
                //order_bn
                $itemVal['order_bn'] = $orderInfo['order_bn'];
                
                //branch
                $itemVal['branch_bn'] = (isset($branchList[$branch_id]) ? $branchList[$branch_id]['branch_bn'] : '');
                $itemVal['branch_name'] = (isset($branchList[$branch_id]) ? $branchList[$branch_id]['name'] : '');
                
                //logi
                $itemVal['logi_type'] = (isset($corpList[$logi_id]) ? $corpList[$logi_id]['type'] : '');
                $itemVal['logi_name'] = (isset($corpList[$logi_id]) ? $corpList[$logi_id]['name'] : '');
                
                //贸易公司
                if($itemVal['betc_id']){
                    $itemVal['betc_name'] = $betcList[$itemVal['betc_id']]['betc_name'];
                }
                
                $fenxiaoDlys[] = $itemVal;
            }
        }
        
        return array('orders'=>$fenxiaoOrder, 'delivery'=>$fenxiaoDlys);
    }
    
    /**
     * 平台原订单顾客修改地址
     * 1、撤消审核生成的OMS发货单；
     * 2、不用取消审核生成的OMS订单；
     * 
     * @param int $order_id
     * @return array
     */
    public function pausePlatformOrder($plat_order_id)
    {
        $jxOrderMdl = app::get('dealer')->model('platform_orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        //平台订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $this->getOrderDetail($filter);
        if(empty($orderInfo)){
            $error_msg = '平台订单信息不存在';
            return $this->error($error_msg);
        }
        
        //订单已经是暂停状态,直接返回
        if ($orderInfo['pause'] != 'false'){
            $error_msg = '订单已经是暂停状态,直接返回成功';
            return $this->succ($error_msg);
        }
        
        //format
        $erpOrders = array();
        foreach($orderInfo['order_objects'] as $objKey => $objVal)
        {
            //check
            if(empty($objVal['order_items'])){
                continue;
            }
            
            //items
            foreach($objVal['order_items'] as $itemKey => $itemVal)
            {
                $erp_order_id = $itemVal['erp_order_id'];
                
                //check
                if($itemVal['is_shopyjdf_type'] != '2'){
                    continue;
                }
                
                if(empty($itemVal['erp_order_bn'])){
                    continue;
                }
                
                $erpOrders[$erp_order_id] = array('erp_order_id'=>$erp_order_id, 'erp_order_bn'=>$itemVal['erp_order_bn']);
            }
        }
        
        //更新订单状态
        $updateSdf = array();
        $updateSdf['pause'] = 'true';
        $updateSdf['pause'] = 'true';
        
        //check没有关联的OMS订单
        if(empty($erpOrders)){
            //update
            $jxOrderMdl->update($updateSdf, array('plat_order_id'=>$plat_order_id));
            
            //logs
            $log_msg = '暂停订单成功';
            $logMdl->write_log('order_modify@dealer', $plat_order_id, $log_msg);
            
            return $this->succ('没有ERP订单');
        }
        
        //撤消OMS发货单
        $is_fail = false;
        $error_msg = '';
        foreach ($erpOrders as $erp_order_id => $erpOrderInfo)
        {
            //取消OMS订单并且撤消发货单
            $rebackResult = $this->rebackOmsDelivery($erpOrderInfo);
            if($rebackResult['rsp'] != 'succ'){
                //logs
                $error_msg = 'OMS订单号['. $erpOrderInfo['erp_order_bn'] .']撤消发货单失败：'. $rebackResult['error_msg'];
                $logMdl->write_log('order_modify@dealer', $plat_order_id, $error_msg);
                
                $is_fail = true;
            }else{
                //更新OMS订单收货人信息
                $this->updateOmeConsignee($erpOrderInfo, $orderInfo);
            }
        }
        
        if($is_fail){
            //logs
            $log_msg = '暂停订单失败,撤消OMS发货单失败';
            $logMdl->write_log('order_modify@dealer', $plat_order_id, $log_msg);
            
            return $this->fail($error_msg);
        }
        
        //update
        $jxOrderMdl->update($updateSdf, array('plat_order_id'=>$plat_order_id));
        
        //logs
        $log_msg = '暂停订单成功';
        $logMdl->write_log('order_modify@dealer', $plat_order_id, $log_msg);
        
        return $this->succ($log_msg);
    }
    
    /**
     * 取消分销订单审核生成的OMS发货单
     * 
     * @param $orderInfo
     * @return void
     */
    public function rebackOmsDelivery($erpOrderInfo)
    {
        $orderMdl = app::get('ome')->model('orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        //check没有关联的OMS订单
        if(empty($erpOrderInfo)){
            return $this->succ('没有OMS订单信息');
        }
        
        //ERP订单列表
        $order_id = $erpOrderInfo['erp_order_id'];
        $order_bn = $erpOrderInfo['erp_order_bn'];
        $orderRow = $orderMdl->dump(array('order_bn'=>$order_bn), 'order_id,order_bn,process_status');
        if(empty($orderRow)){
            return $this->succ('OMS订单数据未找到');
        }
        
        //orders
        if(!in_array($orderRow['process_status'], array('confirmed','splitting','splited'))){
            return $this->succ('OMS订单未审核');
        }
        
        //取消发货单
        $cancelDlyResult = $orderMdl->cancel_delivery($order_id);
        if($cancelDlyResult['rsp'] != 'succ'){
            if($cancelDlyResult['succ_num'] > 0){
                //只有部分发货单取消成功
                $error_msg = 'ERP订单号：'. $order_bn .'取消发货单部分失败';
            }else{
                //发货单取消失败
                $error_msg = 'ERP订单号：'. $order_bn .'取消发货单失败';
            }
            
            return $this->succ($error_msg);
        }
        
        //logs
        $logMdl->write_log('order_modify@ome', $order_id, '平台原订单有变化,发货单撤销成功');
        
        return $this->succ('取消OMS发货单成功');
    }
    
    /**
     * 更新OMS订单收货人信息
     * 
     * @param $erpOrderInfo
     * @param $platformOrderInfo
     * @return void
     */
    public function updateOmeConsignee($erpOrderInfo, $platformOrderInfo)
    {
        $orderMdl = app::get('ome')->model('orders');
        $logMdl = app::get('ome')->model('operation_log');
        
        //check没有关联的OMS订单
        if(empty($erpOrderInfo)){
            return $this->succ('OMS订单信息为空');
        }
        
        if(empty($platformOrderInfo)){
            return $this->succ('平台订单信息为空');
        }
        
        //get
        $order_id = $erpOrderInfo['erp_order_id'];
        $order_bn = $erpOrderInfo['erp_order_bn'];
        
        //data
        $newOrderInfo = array('order_id'=>$order_id);
        $newOrderInfo['consignee']['name'] = $platformOrderInfo['consignee']['name'];
        $newOrderInfo['consignee']['area'] = $platformOrderInfo['consignee']['area'];
        $newOrderInfo['consignee']['addr'] = $platformOrderInfo['consignee']['addr'];
        $newOrderInfo['consignee']['zip'] = $platformOrderInfo['consignee']['zip'];
        $newOrderInfo['consignee']['telephone'] = $platformOrderInfo['consignee']['telephone'];
        $newOrderInfo['consignee']['email'] = $platformOrderInfo['consignee']['email'];
        $newOrderInfo['consignee']['r_time'] = $platformOrderInfo['consignee']['r_time'];
        $newOrderInfo['consignee']['mobile'] = $platformOrderInfo['consignee']['mobile'];
        
        //save
        $orderMdl->save($newOrderInfo);
        
        //logs
        $log_msg = '平台订单顾客修改地址,更新收货人信息成功';
        $logMdl->write_log('order_modify@ome', $order_id, $log_msg);
        
        return $this->succ($log_msg);
    }
}
