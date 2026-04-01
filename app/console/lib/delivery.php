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

class console_delivery
{
    /**
     * 更新发货单推送WMS返回状态
     * 
     * @param int $dly_id
     * @param string $status
     * @param string $msg WMS错误原因
     * @param string $error_code WMS错误码
     * @return boolean
     */
    public function update_sync_status($dly_id, $status, $msg='', $error_code=null)
    {
        if(!$dly_id || !$status){
            return false;
        }
        
        $updateArr = array(
            'sync_status'=>&$status_int
        );
        $old = app::get('ome')->model('delivery')->db_dump(['delivery_id'=>$dly_id],'sync_send_succ_times,sync,delivery_bn');
        //根据仓库识别是否门店仓还是电商仓
        $store_id = kernel::single('ome_branch')->isStoreBranch($old['branch_id']);
        if($store_id){
            $channel_type = 'store';
        }else{
            $channel_type = 'wms';
        }
        switch($status){
            case 'send':
                $status_int = '1';
                $updateArr['sync'] = $old['sync'] | console_delivery_bool_sync::__SEND_CODE;
                break;
            case 'send_fail':
                $status_int = '2';
                $updateArr['sync'] = $old['sync'] | console_delivery_bool_sync::__SEND_FAIL;
                break;
            case 'send_succ':
                $status_int = '3';
                $updateArr['sync'] = $old['sync'] | console_delivery_bool_sync::__SEND_SUCC;
                $updateArr['sync_send_succ_times'] = $old['sync_send_succ_times'] + 1;
                
                // 监控到发货单取消请求早于创建请求，故推送成功后重新发起取消
                if ($old['sync'] & console_delivery_bool_sync::__CANCEL_FAIL) {
                    $deliveryObj = app::get('ome')->model('delivery');
                    // $deliveryObj->rebackDelivery($dly_id, '推送成功后重新取消发货单', false, false);
                }
                break;
            case 'cancel_fail':
                $status_int = '4';
                $updateArr['sync'] = $old['sync'] | console_delivery_bool_sync::__CANCEL_FAIL;
                break;
            case 'cancel_succ':
                $updateArr['sync'] = $old['sync'] | console_delivery_bool_sync::__CANCEL_SUCC;
                $status_int = '5';
                break;
            case 'search_fail':
                $updateArr['sync'] = $old['sync'] | console_delivery_bool_sync::__SEARCH_FAIL;
                $status_int = '9';
            break;
            case 'search_succ':
                $updateArr['sync'] = $old['sync'] | console_delivery_bool_sync::__SEARCH_SUCC;
                $status_int = '10';
            break;
            default:
                return false;
                break;
        }
        

        if($msg){
            //过滤html标签
            $msg = strip_tags($msg);
            $msg = str_replace(array("\r\n", "\r", "\n"), "", $msg);
            
            $updateArr['sync_msg'] = $msg;
        }elseif($status == 'send_succ'){
            $updateArr['sync_msg'] = ''; //清空同步失败原因
        }
        
        //WMS错误码
        if($status == 'send_succ'){
            $updateArr['sync_code'] = '';
        }elseif($error_code){
            $error_code = str_replace(array('"', "'"), '', $error_code);
            
            $updateArr['sync_code'] = $error_code;
        }
        
        $dlyObj = app::get('console')->model('delivery');
        return $dlyObj->update($updateArr, array('delivery_id'=>$dly_id));
    }
    
    /**
     * [京东一件代发]获取发货单对应所有包裹信息
     * 
     * @param int $order_id
     * @param array $skus
     * @param string $error_msg
     * @param string $status 
     * @return array
     */
    public function getDeliveryPackage($delivery_id, &$error_msg=null, $status='delivery')
    {
        $packageObj = app::get('ome')->model('delivery_package');
        
        //filter
        $filter = array('delivery_id'=>$delivery_id);
        if($status){
            $filter['status'] = $status;
        }
        
        //获取已发货的京东包裹单
        $dataList = $packageObj->getList('*', $filter);
        if(empty($dataList)){
            $error_msg = '没有找到关联的京东包裹';
            return false;
        }
        
        $packageList = array();
        foreach ($dataList as $key => $val)
        {
            $delivery_id = $val['delivery_id'];
            $product_id = $val['product_id']; //赠品会有0的场景
            $package_bn = $val['package_bn'];
            
            if(empty($packageList[$package_bn])){
                $packageList[$package_bn] = array(
                        'package_id' => $val['package_id'],
                        'package_bn' => $val['package_bn'],
                        'delivery_id' => $val['delivery_id'],
                        'logi_bn' => $val['logi_bn'], //物流编码
                        'logi_no' => $val['logi_no'], //物流单号
                        'status' => $val['status'], //状态
                        'shipping_status' => $val['shipping_status'], //配送状态
                );
                
                //item
                $packageList[$package_bn]['items'][] = array(
                        'product_id' => $val['product_id'],
                        'bn' => $val['bn'],
                        'outer_sku' => $val['outer_sku'],
                        'number' => $val['number'],
                        'product_name' => $val['product_name'],
                        'create_time' => $val['create_time'],
                        'is_wms_gift' => $val['is_wms_gift'],
                );
            }else{
                //item
                $packageList[$package_bn]['items'][] = array(
                        'product_id' => $val['product_id'],
                        'bn' => $val['bn'],
                        'outer_sku' => $val['outer_sku'],
                        'number' => $val['number'],
                        'product_name' => $val['product_name'],
                        'create_time' => $val['create_time'],
                        'is_wms_gift' => $val['is_wms_gift'],
                );
            }
        }
        
        return $packageList;
    }
    
    /**
     * 京东配送方式
     * 
     * @param string $shipping_type
     * @return string
     */
    public function getShippingType($shipping_type=null)
    {
        $shipping_types = array(
                '0' => '默认',
                '1' => '京东配送',
                '2' => '京配转三方配送',
                '3' => '第三方配送',
                '4' => '普通快递配送',
                '9' => '不支持配送',
        );
        
        if($shipping_type || $shipping_type === '0'){
            return $shipping_types[$shipping_type];
        }
        
        return $shipping_types;
    }
    
    /**
     * 京东包裹发货状态
     * 
     * @param string $status
     * @return string
     */
    public function getShippingStatus($status=null)
    {
        $status_list = array(
                '-100' => '已取消',
                '0' => '提单成功',
                '1' => '等待付款',
                '4' => '已支付',
                '6' => '等待打印',
                '7' => '拣货完成',
                '8' => '出库完成',
                '15' => '待用户确认收货',
                '16' => '用户拒收',
                '18' => '用户签收',
                '21' => '订单锁定',
                '9' => '等待发货',
        );
        
        if($status || $status === '0'){
            return $status_list[$status];
        }
        
        return $status_list;
    }
    
    /**
     * 京东包裹状态
     * 
     * @param string $status
     * @return string
     */
    public function getPackageStatus($status=null)
    {
        $status_list = array(
                'accept' => '未发货',
                'delivery' => '已发货',
                'return_back' => '拦截追回',
                'cancel' => '已取消', //现在没有此状态
        );
        
        if($status){
            return $status_list[$status];
        }
        
        return $status_list;
    }
    
    /**
     * 更新发货单上京东云交易采购价格
     */
    public function updatePurchasePrice($params, &$error_msg=null)
    {
        $dlyObj = app::get('ome')->model('delivery');
        
        //发货单号
        $delivery_bn = ($params['wms_order_code'] ? $params['wms_order_code'] : $params['out_order_code']);
        
        //发货明细
        $items = $params['items'];
        
        if(empty($delivery_bn) || empty($items)){
            $error_msg = '无效的数据!';
            return false;
        }
        
        $items = json_decode($items, true);
        if(empty($items)){
            $error_msg = '无效的数据。';
            return false;
        }
        
        //发货单信息
        $dlyInfo = $dlyObj->dump(array('delivery_bn'=>$delivery_bn), 'delivery_id');
        if(empty($dlyInfo)){
            $error_msg = '没有找到发货单信息';
            return false;
        }
        $delivery_id = $dlyInfo['delivery_id'];
        
        //items
        foreach ($items['item'] as $key => $val)
        {
            $item_code = $val['item_code'];
            $item_price = $val['item_price'];
            
            if(empty($item_code)){
                continue;
            }
            
            //更新仓储采购价
            $update_sql = "UPDATE sdb_ome_delivery_items SET purchase_price='%s' WHERE delivery_id=%d AND bn='%s'";
            $update_sql = sprintf($update_sql, $item_price, $delivery_id, $item_code);
            $dlyObj->db->exec($update_sql);
        }
        
        return true;
    }
    
    /**
     * 获取已发货完成的发货单关联包裹列表
     */
    public function getShipDlyPackages($delivery_id, &$error_msg=null)
    {
        $packageObj = app::get('ome')->model('delivery_package');
        
        //获取已发货的京东包裹单
        $filter = array('delivery_id'=>$delivery_id, 'status'=>array('delivery', 'return_back'));
        $dataList = $packageObj->getList('package_id,package_bn,status,logi_bn,logi_no,bn,outer_sku,number', $filter);
        if(empty($dataList)){
            $error_msg = '没有找到关联的京东包裹';
            return false;
        }
        
        $packageList = array();
        foreach ($dataList as $key => $val)
        {
            $product_id = $val['product_id']; //赠品会有0的场景
            $package_bn = $val['package_bn'];
            
            //包裹状态
            $val['package_status'] = $this->getPackageStatus($val['status']);
            
            $packageList[] = $val;
        }
        
        return $packageList;
    }
    
    /**
     * [包裹发货完成]添加WMS仓库赠送的赠品到OMS发货单明细上,并且扣减库存
     * @todo：插入赠品到订单object层明细、订单items层明细、发货单items层明细、发货单item_detail层明细
     * 
     * @param array $dlyParams
     * @param string $error_msg
     * @return bool
     */
    public function addWmsGifts($dlyParams, &$error_msg=null)
    {
        $operLogObj = app::get('ome')->model('operation_log');
        $dlyObj = app::get('console')->model('delivery');
        $dlyItemObj = app::get('ome')->model('delivery_items');
        $dlyItemDetailObj = app::get('ome')->model('delivery_items_detail');
        $foreignObj = app::get('console')->model('foreign_sku');
        $saleMaterialObj = app::get('material')->model('sales_material');
        $objectObj = app::get('ome')->model('order_objects');
        $itemObj = app::get('ome')->model('order_items');
        
        $salesBasicMaterialLib = kernel::single('material_sales_material');
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        $deliveryId = $dlyParams['delivery_id'];
        $wms_id = $dlyParams['wms_id'];
        $oid = $dlyParams['oid'];
        $shop_id = $dlyParams['shop_id'];
        $branch_id = $dlyParams['branch_id'];
        $gift_list = $dlyParams['gift_list'];
        
        //关联订单
        $sql = "SELECT * FROM sdb_ome_delivery_order WHERE delivery_id=". $deliveryId;
        $dlyOrderList = $dlyItemObj->db->select($sql);
        if(empty($dlyOrderList)){
            $error_msg = '没有找到关联订单';
            return false;
        }
        
        //现在只取第一个订单
        $order_id = $dlyOrderList[0]['order_id'];
        
        //赠送的货品
        $outer_skus = array();
        foreach ($gift_list as $key => $val)
        {
            $outer_skus[] = $val['product_bn'];
        }
        
        //WMS货品信息
        $filter = array(
                'wms_id' => $wms_id,
                'outer_sku' => $outer_skus,
        );
        $tempList = $foreignObj->getList('inner_sku,outer_sku,price', $filter);
        if(empty($tempList)){
            //[兼容]读取基础物料
            $basicMaterialObj = app::get('material')->model('basic_material');
            $tempList = $basicMaterialObj->dump(array('material_bn'=>$outer_skus), 'bm_id,material_bn');
            if(empty($tempList)){
                $error_msg = 'WMS赠送的货品,在OMS商品关系中不存在';
                return false;
            }
            
            $product_bns = array();
            foreach ($tempList as $key => $val)
            {
                $material_bn = $val['material_bn'];
                
                $product_bns[$material_bn] = $material_bn;
            }
            
        }else{
            $product_bns = array();
            foreach ($tempList as $key => $val)
            {
                $inner_sku = $val['inner_sku']; //oms基础物料编码
                $outer_sku = $val['outer_sku']; //WMS货号
                
                $product_bns[$inner_sku] = $inner_sku;
            }
        }
        
        //检查赠品是否已经存在
        $isCheck = $dlyItemObj->dump(array('delivery_id'=>$deliveryId, 'bn'=>$product_bns, 'is_wms_gift'=>'true'), 'delivery_id');
        if($isCheck){
            $error_msg = '赠品已经存在,不能重复添加';
            return false;
        }
        
        //获取销售物料信息
        $tempList = $saleMaterialObj->getList('sm_id,sales_material_bn,sales_material_name,shop_id,sales_material_type', array('sales_material_bn'=>$product_bns));
        if(empty($tempList)){
            $error_msg = 'WMS赠送的货品,在OMS销售物料里不存在';
            return false;
        }
        
        $smList = array();
        foreach ($tempList as $key => $val)
        {
            $sm_id = $val['sm_id'];
            $sales_material_bn = $val['sales_material_bn'];
            
            //绑定的基础物料
            $bindInfo = $salesBasicMaterialLib->getBasicMBySalesMIds($sm_id);
            $bindInfo = $bindInfo[$sm_id];
            $bindInfo = $bindInfo ? array_column($bindInfo, null, 'bm_id') : null;
            if(empty($bindInfo)){
                continue;
            }
            
            $val['basicMaterial'] = $bindInfo;
            $smList[$sales_material_bn] = $val;
        }
        
        //组织新加的赠品
        $new_order_object = array();
        $obj_i = 0;
        foreach ($gift_list as $key => $val)
        {
            $product_bn = $val['product_bn'];
            $val['price'] = ($val['price'] ? $val['price'] : 0);
            
            //销售物料
            $smInfo = $smList[$product_bn];
            
            //销售物料绑定的基础物料
            $salesBasicMaterial = $smInfo['basicMaterial'];
            
            //check
            if(empty($smInfo) || empty($salesBasicMaterial)){
                continue;
            }
            
            //object
            $new_order_object[$obj_i] = array(
                    'order_id' => $order_id,
                    'obj_type' => 'gift',
                    'obj_alias' => 'gift',
                    'bn' => $smInfo['sales_material_bn'],
                    'name' => $smInfo['sales_material_name'],
                    'goods_id' => $smInfo['sm_id'],
                    'quantity' => $val['num'],
                    'price' => 0,
                    'amount' => 0,
                    'pmt_price' => 0,
                    'sale_price' => 0,
                    'divide_order_fee' => 0,
                    'part_mjz_discount' => 0,
                    'is_wms_gift' => 'true', //是否WMS赠品
            );
            
            //订单item层明细
            $new_order_item = array();
            foreach ($salesBasicMaterial as $bmItem)
            {
                $item_type = 'gift';
                
                $new_order_item[] = array(
                        'order_id' => $order_id,
                        'bn' => $bmItem['material_bn'],
                        'name' => $bmItem['material_name'],
                        'product_id' => $bmItem['bm_id'],
                        'item_type' => 'gift',
                        'nums' => $val['num'],
                        'price' => 0,
                        'amount' => 0,
                        'pmt_price' => 0,
                        'sale_price' => 0,
                        'divide_order_fee' => 0,
                        'part_mjz_discount' => 0,
                        'is_wms_gift' => 'true', //是否WMS赠品
                );
            }
            
            $new_order_object[$obj_i]['order_items'] = $new_order_item;
            
            $obj_i++;
        }
        
        if(empty($new_order_object)){
            $error_msg = '没有需要添加的赠品明细';
            return false;
        }
        
        //开启事务
        kernel::database()->beginTransaction();
        
        $obj_type = material_basic_material_stock_freeze::__BRANCH;
        $bill_type = material_basic_material_stock_freeze::__DELIVERY;
        $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
        
        //插入赠品
        $freeze_product_list = array();
        foreach ($new_order_object as $objKey => $objVal)
        {
            //新增obj
            $isSave = $objectObj->insert($objVal);
            if(!$isSave){
                //事务回滚
                kernel::database()->rollBack();
                
                $err_msg = sprintf('添加obj层销售物料[%s]失败', $objVal['bn']);
                return false;
            }
            
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $itemVal['obj_id'] = $objVal['obj_id'];
                $product_id = $itemVal['product_id'];
                
                //insert
                $isSave = $itemObj->insert($itemVal);
                if(!$isSave){
                    //事务回滚
                    kernel::database()->rollBack();
                    
                    $err_msg = sprintf('添加item层基础物料[%s]失败', $itemVal['bn']);
                    return false;
                }
                
                //插入发货单items层明细
                $saveData = array(
                        'delivery_id' => $deliveryId,
                        'product_id' => $product_id,
                        'bn' => $itemVal['bn'],
                        'product_name' => $itemVal['name'],
                        'number' => $itemVal['nums'],
                        'purchase_price' => 0, //仓储采购价格:$wmsProcutInfo['price']
                        'is_wms_gift' => 'true', //是否WMS赠品
                        'oid' => $oid, //WMS包裹号
                );
                $isSave = $dlyItemObj->insert($saveData);
                if(!$isSave){
                    //事务回滚
                    kernel::database()->rollBack();
                    
                    $err_msg = sprintf('添加发货单items层明细[%s]失败', $itemVal['bn']);
                    return false;
                }else{
                    
                    if($freeze_product_list[$product_id]){
                        $freeze_product_list[$product_id]['nums'] += $itemVal['nums'];
                    }else{
                        $freeze_product_list[$product_id] = array(
                                'product_id' => $itemVal['product_id'],
                                'goods_id' => $objVal['goods_id'],
                                'obj_type' => $obj_type,
                                'bill_type' => $bill_type,
                                'delivery_id' => $deliveryId,
                                'shop_id' => $shop_id,
                                'branch_id' => $branch_id,
                                'bmsq_id' => $bmsq_id,
                                'nums' => $itemVal['nums'],
                        );
                    }
                    
                }
                
                //插入发货单item_detail层明细
                $saveData = array(
                        'delivery_id' => $deliveryId,
                        'delivery_item_id' => $saveData['item_id'],
                        'order_id' => $order_id,
                        'order_obj_id' => $objVal['obj_id'],
                        'order_item_id' => $itemVal['item_id'],
                        'item_type' => 'gift',
                        
                        'product_id' => $itemVal['product_id'],
                        'bn' => $itemVal['bn'],
                        'product_name' => $itemVal['name'],
                        'number' => $itemVal['nums'],
                        'is_wms_gift' => 'true', //是否WMS赠品
                );
                $isSave = $dlyItemDetailObj->insert($saveData);
                if(!$isSave){
                    //事务回滚
                    kernel::database()->rollBack();
                    
                    $err_msg = sprintf('添加发货单item_detail层明细[%s]失败', $itemVal['bn']);
                    return false;
                }
            }
        }
        
        
        //添加仓库预占流水
        if($freeze_product_list){
            
            $batchList = [];
            //排序防止死锁
            ksort($freeze_product_list);
            
            foreach ($freeze_product_list as $key => $val)
            {
                $freezeData = [];
                $freezeData['bm_id'] = $val['product_id'];
                $freezeData['sm_id'] = $val['goods_id'];
                $freezeData['obj_type'] = $val['obj_type'];
                $freezeData['bill_type'] = $val['bill_type'];
                $freezeData['obj_id'] = $val['delivery_id'];
                $freezeData['shop_id'] = $val['shop_id'];
                $freezeData['branch_id'] = $val['branch_id'];
                $freezeData['bmsq_id'] = $val['bmsq_id'];
                $freezeData['num'] = $val['nums'];
                $freezeData['obj_bn'] = $dlyParams['delivery_bn'];
                $freezeData['sub_bill_type'] = 'wms赠品';
                $freezeData['sync_sku'] = false;

                $batchList[] = $freezeData;
            }
            //添加仓库预占流水
            $rs = $basicMStockFreezeLib->freezeBatch($batchList, __CLASS__.'::'.__FUNCTION__, $error_msg);
            if ($rs == false) {
                //$err_msg .= '仓库货品冻结预占失败!';
                //return false;
            }
        }
        
        //打标发货单有WMS赠品
        $update_sql = "UPDATE sdb_ome_delivery SET is_wms_gift='true' WHERE delivery_id=". $deliveryId;
        $dlyObj->db->exec($update_sql);
        
        //提交事务
        kernel::database()->commit();
        
        //log
        $operLogObj->write_log('order_modify@ome', $order_id, 'WMS仓库赠送赠品添加成功。');
        
        $operLogObj->write_log('delivery_modify@ome', $deliveryId, 'WMS仓库赠送赠品添加成功。');
        
        return true;
    }
    
    /**
     * 删除WMS仓库赠送的赠品
     * 
     * @param array $params
     * @param string $error_msg
     * @return bool
     */
    public function deleteWmsGifts($params, &$error_msg=null)
    {
        $operLogObj = app::get('ome')->model('operation_log');
        $deliveryObj = app::get('ome')->model('delivery');
        $dlyItemObj = app::get('ome')->model('delivery_items');
        $dlyItemDetailObj = app::get('ome')->model('delivery_items_detail');
        $foreignObj = app::get('console')->model('foreign_sku');
        $objectObj = app::get('ome')->model('order_objects');
        $itemObj = app::get('ome')->model('order_items');
        
        //params
        $delivery_bn = $params['delivery_bn'];
        $gift_list = $params['gift_list'];
        if(empty($delivery_bn) || empty($gift_list)){
            $error_msg = '没有发货单号或者赠品';
            return false;
        }
        
        //发货单信息
        $deliveryInfo = $deliveryObj->dump(array('delivery_bn'=>$delivery_bn), 'delivery_id,status');
        if(empty($deliveryInfo)){
            $error_msg = '发货单不存在';
            return false;
        }
        
        if(in_array($deliveryInfo['status'], array('succ', 'return_back'))){
            $error_msg = '发货单已经发货,不允许删除赠品';
            return false;
        }
        
        $deliveryId = $deliveryInfo['delivery_id'];
        
        //关联订单
        $sql = "SELECT * FROM sdb_ome_delivery_order WHERE delivery_id=". $deliveryId;
        $dlyOrderList = $dlyItemObj->db->select($sql);
        if(empty($dlyOrderList)){
            $error_msg = '没有找到关联订单';
            return false;
        }
        
        //现在只取第一个订单
        $order_id = $dlyOrderList[0]['order_id'];
        
        /****
        //赠送的货品
        $outer_skus = array();
        foreach ($gift_list as $key => $val)
        {
            $outer_skus[] = $val['product_bn'];
        }
        
        //WMS货品信息
        $filter = array(
                'wms_id' => $wms_id,
                'outer_sku' => $outer_skus,
        );
        $tempList = $foreignObj->getList('inner_sku,outer_sku,price', $filter);
        if(empty($tempList)){
            $error_msg = 'WMS赠送的货品,在OMS商品关系中不存在';
            return false;
        }
        
        $product_bns = array();
        foreach ($tempList as $key => $val)
        {
            $inner_sku = $val['inner_sku']; //oms基础物料编码
            
            $product_bns[$inner_sku] = $inner_sku;
        }
        ***/
        
        //删除订单object层赠品
        $objectObj->delete(array('order_id'=>$order_id, 'is_wms_gift'=>'true')); //'bn'=>$product_bns
        
        //删除订单items层赠品
        $itemObj->delete(array('order_id'=>$order_id, 'is_wms_gift'=>'true')); //'bn'=>$product_bns
        
        //删除订单delivery_items层赠品
        $dlyItemObj->delete(array('delivery_id'=>$deliveryId, 'is_wms_gift'=>'true')); //'bn'=>$product_bns
        
        //删除订单delivery_items_detail层赠品
        $dlyItemDetailObj->delete(array('delivery_id'=>$deliveryId, 'is_wms_gift'=>'true')); //'bn'=>$product_bns
        
        //删除标识(WMS有拆单可能)
        //$update_sql = "UPDATE sdb_ome_delivery SET is_wms_gift='false' WHERE delivery_id=". $deliveryId;
        //$dlyObj->db->exec($update_sql);
        
        //log
        $operLogObj->write_log('order_modify@ome', $order_id, '发货失败,撤消添加的WMS仓库赠品。');
        
        $operLogObj->write_log('delivery_modify@ome', $deliveryId, '发货失败,撤消添加的WMS仓库赠品。');
        
        return true;
    }
    
    /**
     * 自动批量取消订单对应所有(已发货)发货单
     */
    public function autoCancelDelivery(&$cursor_id, $params, &$error_msg=null)
    {
        $deliveryObj = app::get('ome')->model('delivery');
        $operLogObj = app::get('ome')->model('operation_log');
        
        //data
        $sdfdata = $params['sdfdata'];
        $order_id = intval($sdfdata['order_id']);
        
        //关联发货单(获取已发货、已追回的发货单)
        $sql = "SELECT b.delivery_id,b.delivery_bn,b.is_wms_gift,b.wms_channel_id,b.branch_id FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id ";
        $sql .= " WHERE a.order_id=". $order_id ." AND b.status='succ'";
        $dataList = $deliveryObj->db->select($sql);
        if(empty($dataList)){
            //$error_msg = '没有退货单';
            return false;
        }
        
        foreach ($dataList as $key => $delivery)
        {
            $delivery_id = $delivery['delivery_id'];
            
            $res = ome_delivery_notice::cancel($delivery, true);
            
            if($res['rsp'] == 'succ'){
                $operLogObj->write_log('delivery_modify@ome', $delivery_id, '请求撤消发货单,发送命令成功。');
            }else{
                $log_error_msg = ($res['error_msg'] ? $res['error_msg'] : $res['msg']);
                
                $operLogObj->write_log('delivery_modify@ome', $delivery_id, '请求撤消发货单,发送命令失败：'.$log_error_msg);
            }
        }
        
        return false;
    }
    
    /**
     * 按发货单ID进行取消京东订单包裹
     * 
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return boolean
     */
    public function autoCancelPackage(&$cursor_id, $params, &$error_msg=null)
    {
        $deliveryObj = app::get('ome')->model('delivery');
        $packageObj = app::get('ome')->model('delivery_package');
        $operLogObj = app::get('ome')->model('operation_log');
        
        //data
        $sdfdata = $params['sdfdata'];
        $delivery_id = intval($sdfdata['delivery_id']);
        $delivery_bn = $sdfdata['delivery_bn'];
        
        $filter = array();
        if($delivery_id){
            $filter['delivery_id'] = $delivery_id;
        }else{
            $filter['delivery_bn'] = $delivery_bn;
        }
        
        //发货单信息
        $deliveryInfo = $deliveryObj->dump($filter, '*');
        if(empty($deliveryInfo)){
            $error_msg = '没有找到发货单';
            return false;
        }
        
        //京东订单号
        $packageRows = $packageObj->getList('package_id,package_bn,status', array('delivery_id'=>$deliveryInfo['delivery_id'], 'status|noequal'=>'cancel'));
        if(empty($packageRows)){
            $error_msg = '发货单没有对应京东订单号';
            return false;
        }
        
        //cancel
        $res = ome_delivery_notice::cancel($deliveryInfo, true);
        if($res['rsp'] == 'succ'){
            $operLogObj->write_log('delivery_modify@ome', $deliveryInfo['delivery_id'], '自动请求取消京东订单包裹,请求成功.');
        }else{
            $error_msg = ($res['error_msg'] ? $res['error_msg'] : $res['msg']);
            
            $operLogObj->write_log('delivery_modify@ome', $delivery_id, '自动取消京东订单包裹,请求失败：'.$error_msg);
        }
        
        return false;
    }
    
    /**
     * 物流包裹单状态
     * 
     * @param string $status
     * @return bool
     */
    public function getDeliveryBillStatus($status=null)
    {
        $status_list = array(
                'normal' => '0',
                'delivery' => '1',
                'cancel' => '2',
                'accept' => '3',
                'sign' => '4',
                'payed' => '5',
        );
        
        if($status){
            return ($status_list[$status] ? $status_list[$status] : '0');
        }
        
        return $status_list;
    }
    
    /**
     * 保存物流包裹单
     * 
     * @param int $cursor_id
     * @param array $params
     * @param string $error_msg
     * @return boolean
     */
    public function saveDeliveryBill(&$cursor_id, $params, &$error_msg=null)
    {
        $dlyBillObj = app::get('ome')->model('delivery_bill');
        
        //data
        $sdfdata = $params['sdfdata'];
        
        $delivery_id = $sdfdata['delivery_id'];
        $delivery_bn = $sdfdata['delivery_bn'];
        $package_bn = $sdfdata['package_bn'];
        $wms_id = $sdfdata['wms_id'];
        
        //京东订单发货明细
        $items = $sdfdata['items'];
        
        //check
        if(empty($delivery_id) || empty($delivery_bn) || empty($package_bn)){
            $error_msg = '无效的发货单数据';
            return false;
        }
        
        //包裹状态
        $status = $this->getDeliveryBillStatus($sdfdata['status']);
        $weight = ($sdfdata['weight'] ? $sdfdata['weight'] : 0);
        
        //物流包裹单信息
        $billInfo = $dlyBillObj->dump(array('delivery_bn'=>$delivery_bn, 'package_bn'=>$package_bn), 'log_id,logi_code,status');
        
        //接收状态与存在的物流单状态相同,直接返回
        if($billInfo && $billInfo['status'] === $status){
            if($billInfo['status'] == 'delivery' && $billInfo['logi_code']){
                return true;
            }else{
                return true;
            }
        }
        
        //获取订单
        $sql = 'SELECT a.order_id,a.order_bn FROM sdb_ome_orders AS a LEFT JOIN sdb_ome_delivery_order AS b ON a.order_id=b.order_id 
                WHERE b.delivery_id='. $delivery_id;
        $orderInfo = $dlyBillObj->db->selectrow($sql);
        if(empty($orderInfo)){
            $error_msg = '没有找到订单信息';
            return false;
        }
        
        //save
        $saveData = array(
                'delivery_id' => $delivery_id,
                'delivery_bn' => $delivery_bn,
                'logi_code' => $sdfdata['logi_id'],
                'logi_no' => $sdfdata['logi_no'],
                'status' => $status,
                'weight' => floatval($weight),
                'order_id' => $orderInfo['order_id'],
                'order_bn' => $orderInfo['order_bn'],
                'package_bn' => $package_bn,
                'last_modified' => time(),
        );
        
        //已发货逻辑
        if($sdfdata['status'] == 'delivery'){
            //发货时间
            $saveData['delivery_time'] = time();
            
            //[兼容]获取物流信息(京东订单SKU发货明细中有物流信息)
            if(empty($saveData['logi_code'])){
                foreach ($items as $itemKey => $itemVal)
                {
                    $itemVal['logistics'] = trim($itemVal['logistics']);
                    
                    $saveData['logi_code'] = $this->getLogiCode($itemVal['logistics'], $wms_id);
                    
                    $saveData['logi_no'] = ($saveData['logi_no'] ? $saveData['logi_no'] : $itemVal['logi_no']);
                }
            }
        }
        
        //[特殊处理]指定转换物流公司
        if($sdfdata['status']=='delivery' && strtoupper($saveData['logi_code'])=='JD' && $sdfdata['wms_channel_id']){
            $change_logi = $sdfdata['crop_config']['change_logi_off']; //转换物流公司开关
            $change_channel_id = trim($sdfdata['crop_config']['change_channel_id']); //指定渠道ID
            $change_logi_code = $sdfdata['crop_config']['change_logi_code']; //指定物流公司
            
            //转换物流开启&&指定渠道ID&&设置了转换物流公司
            if($change_logi=='1' && $change_channel_id==$sdfdata['wms_channel_id'] && $change_logi_code){
                $saveData['logi_code'] = $change_logi_code;
            }
        }
        
        //保存物流包裹单
        if($billInfo){
            unset($saveData['delivery_id'], $saveData['delivery_bn'], $saveData['order_id'], $saveData['order_bn'], $saveData['package_bn']);
            
            //更新
            $dlyBillObj->update($saveData, array('log_id'=>$billInfo['log_id']));
        }else{
            $saveData['create_time'] = time();
            
            //新建
            $dlyBillObj->insert($saveData);
        }
        
        //检查发货单只有一个京东订单,则不用单独回传平台
        $is_flag = true;
        $sql = "SELECT count(distinct package_bn) AS nums FROM sdb_ome_delivery_package WHERE delivery_id=". $delivery_id ." AND status!='cancel'";
        $count = $dlyBillObj->db->selectrow($sql);
        if($count['nums'] < 2){
            return true;
        }
        
        //发货回写方式
        $is_send_delivery = false;
        $delivery_mode = $sdfdata['crop_config']['delivery_mode'];
        if($delivery_mode == 'package'){
            $is_send_delivery = true; //按京东订单号回写
        }
        
        //[已发货or签收]推送给平台发货状态
        if(in_array($sdfdata['status'], array('delivery','sign')) && $is_send_delivery){
            $sync_error_msg = '';
            $params = array(
                    'delivery_bn' => $delivery_bn,
                    'package_bn' => $package_bn,
            );
            $result = $this->pushDeliveryConfirm($params, $sync_error_msg);
            if(!$result){
                $error_msg = $sync_error_msg;
            }
        }
        
        return true;
    }
    
    /**
     * 按京东订单纬度,推送给平台发货状态
     * 
     * @param string $status
     * @return bool
     */
    public function pushDeliveryConfirm($params, &$error_msg=null)
    {
        $deliveryObj = app::get('ome')->model('delivery');
        $dlyBillObj = app::get('ome')->model('delivery_bill');
        $packageObj = app::get('ome')->model('delivery_package');
        $deliveryOrderObj = app::get('ome')->model('delivery_order');
        
        $routeLib = kernel::single('ome_event_trigger_shop_data_delivery_router');
        
        $delivery_bn = $params['delivery_bn'];
        $package_bn = $params['package_bn'];
        
        //物流包裹单信息
        $billInfo = $dlyBillObj->dump(array('delivery_bn'=>$delivery_bn, 'package_bn'=>$package_bn), '*');
        if(empty($billInfo)){
            $error_msg = '没有获取到物流包裹单信息';
            return false;
        }
        
        //已经推送平台成功
        if($billInfo['sync_status'] == 'succ'){
            return true;
        }
        
        if(!in_array($billInfo['status'], array('1', '4'))){
            $error_msg = '物流包裹单不是发货状态';
            return false;
        }
        
        //获取物流单号
        if(empty($billInfo['logi_code']) || empty($billInfo['logi_no'])){
            $sql = "SELECT * FROM sdb_ome_delivery_package WHERE package_bn='". $package_bn ."' AND status IN('delivery', 'sign')";
            $packageRow = $deliveryObj->db->selectrow($sql);
            if($packageRow){
                $billInfo['logi_code'] = $packageRow['logi_bn'];
                $billInfo['logi_no'] = $packageRow['logi_no'];
            }
        }
        
        //check
        if(empty($billInfo['logi_code']) || empty($billInfo['logi_no'])){
            $error_msg = '没有物流信息';
            return false;
        }
        
        $delivery_id = $billInfo['delivery_id'];
        
        //物流公司名称
        $sql = "SELECT corp_id,type,name FROM sdb_ome_dly_corp WHERE type='". $billInfo['logi_code'] ."'";
        $corpInfo = $dlyBillObj->db->selectrow($sql);
        $billInfo['logi_name'] = $corpInfo['name'];
        
        //获取已发货的京东包裹单
        $filter = array('delivery_id'=>$delivery_id, 'package_bn'=>$package_bn, 'status'=>array('delivery','sign'));
        $dataList = $packageObj->getList('*', $filter);
        if(empty($dataList)){
            $error_msg = '没有找到关联的京东包裹明细';
            return false;
        }
        
        //按sku组织发货明细
        $dlyBns = array();
        foreach ($dataList as $key => $val)
        {
            $product_bn = $val['bn'];
            $number = $val['number'];
            
            //过滤京东赠品
            if($val['is_wms_gift'] == 'true'){
                continue;
            }
            
            if($dlyBns[$product_bn]){
                $dlyBns[$product_bn] += $number;
            }else{
                $dlyBns[$product_bn] = $number;
            }
        }
        
        //发货单信息
        $deliveryInfo = $deliveryObj->dump(array('delivery_id'=>$delivery_id), '*');
        
        //合并发货单类型
        if($deliveryInfo['is_bind'] == 'true'){
            
            //所有子发货单
            $childrenDlyIds = array();
            $childrednDeliveryList = $deliveryObj->getList('*', array('parent_id'=>$deliveryInfo['delivery_id']));
            foreach ($childrednDeliveryList as $c_key => $c_delivery)
            {
                //使用父发货单上物流信息
                $childrednDeliveryList[$c_key]['status']               = $deliveryInfo['status'];
                $childrednDeliveryList[$c_key]['logi_id']              = $deliveryInfo['logi_id'];
                $childrednDeliveryList[$c_key]['logi_name']            = $deliveryInfo['logi_name'];
                $childrednDeliveryList[$c_key]['logi_no']              = $deliveryInfo['logi_no'];
                $childrednDeliveryList[$c_key]['delivery_cost_actual'] = $deliveryInfo['delivery_cost_actual'];
                
                $childrenDlyIds[] = $c_delivery['delivery_id'];
            }
            
            //获取发货单对应的订单
            $delivery_orders = array();
            $sql = "SELECT a.*, b.delivery_id FROM sdb_ome_orders AS a LEFT JOIN sdb_ome_delivery_order AS b ON a.order_id=b.order_id 
                    WHERE b.delivery_id IN(". implode(',', $childrenDlyIds) .")";
            $orderList = $dlyBillObj->db->select($sql);
            foreach ($orderList as $key => $val)
            {
                $c_delivery_id = $val['delivery_id'];
                
                $delivery_orders[$c_delivery_id] = $val;
            }
            
            foreach ($childrednDeliveryList as $c_delivery)
            {
                //组织回传参数
                $dlySdf = $routeLib->set_shop_id($deliveryInfo['shop_id'])->init($c_delivery, $delivery_orders)->get_sdf($deliveryInfo['delivery_id']);
                
                //[兼容]发货物流信息
                $dlySdf['logi_type'] = ($billInfo['logi_code'] ? $billInfo['logi_code'] : $dlySdf['logi_type']);
                $dlySdf['logi_no'] = ($billInfo['logi_no'] ? $billInfo['logi_no'] : $dlySdf['logi_no']);
                $dlySdf['logi_name'] = ($billInfo['logi_name'] ? $billInfo['logi_name'] : $dlySdf['logi_name']);
                
                //格式化请求参数
                $sdf = $this->formatDeliveryParmas($dlySdf, $dlyBns, $error_msg);
                if(!$sdf){
                    return false;
                }
                
                //request
                $result = kernel::single('erpapi_router_request')->set('shop', $deliveryInfo['shop_id'])->delivery_confirm($sdf);
                if($result['rsp'] == 'fail'){
                    $error_msg = $result['err_msg'];
                    
                    $dlyBillObj->update(array('sync_status'=>'fail'), array('log_id'=>$billInfo['log_id']));
                    
                    return false;
                }elseif($result['rsp'] == 'succ'){
                    $dlyBillObj->update(array('sync_status'=>'succ'), array('log_id'=>$billInfo['log_id']));
                }
            }
            
        }else{
            //获取订单信息
            $sql = "SELECT a.* FROM sdb_ome_orders AS a LEFT JOIN sdb_ome_delivery_order AS b ON a.order_id=b.order_id 
                    WHERE b.delivery_id=". $delivery_id;
            $orderInfo = $dlyBillObj->db->selectrow($sql);
            
            //组织回传参数
            $deliveryList = array($deliveryInfo);
            $delivery_orders[$delivery_id] = $orderInfo;
            $dlySdf = $routeLib->set_shop_id($deliveryInfo['shop_id'])->init($deliveryList, $delivery_orders)->get_sdf($deliveryInfo['delivery_id']);
            
            //[兼容]发货物流信息
            $dlySdf['logi_type'] = ($billInfo['logi_code'] ? $billInfo['logi_code'] : $dlySdf['logi_type']);
            $dlySdf['logi_no'] = ($billInfo['logi_no'] ? $billInfo['logi_no'] : $dlySdf['logi_no']);
            $dlySdf['logi_name'] = ($billInfo['logi_name'] ? $billInfo['logi_name'] : $dlySdf['logi_name']);
            
            //格式化请求参数
            $sdf = $this->formatDeliveryParmas($dlySdf, $dlyBns, $error_msg);
            if(!$sdf){
                return false;
            }
            
            //request
            $result =kernel::single('erpapi_router_request')->set('shop', $deliveryInfo['shop_id'])->delivery_confirm($sdf);
            if($result['rsp'] == 'fail'){
                $error_msg = $result['err_msg'];
                
                $dlyBillObj->update(array('sync_status'=>'fail'), array('log_id'=>$billInfo['log_id']));
                
                return false;
            }elseif($result['rsp'] == 'succ'){
                $dlyBillObj->update(array('sync_status'=>'succ'), array('log_id'=>$billInfo['log_id']));
            }
        }
        
        unset($billInfo, $filter, $dataList, $deliveryInfo);
        
        return true;
    }
    
    /**
     * 按京东订单纬度,推送给平台发货状态
     * 
     * @param string $status
     * @return bool
     */
    public function formatDeliveryParmas($dlySdf, $dlyBns, &$error_msg=null)
    {
        $delivery_items = $dlySdf['delivery_items'];
        if(empty($delivery_items)){
            $error_msg = '没有获取到发货商品明细';
            return false;
        }
        
        //加入拆单标识
        $dlySdf['is_split'] = 1;
        
        //重置发货状态
        $dlySdf['status'] = 'succ';
        
        //items
        $dlyItems = array();
        foreach ($delivery_items as $key => $val)
        {
            $product_bn = ($val['bn'] ? $val['bn'] : $val['product_bn']);
            $number = $val['number'];
            
            //过滤不是京东订单上的商品
            if(empty($dlyBns[$product_bn])){
                continue;
            }
            
            //[兼容]发货物流信息
            $val['logi_type'] = ($dlySdf['logi_type'] ? $dlySdf['logi_type'] : $val['logi_type']);
            $val['logi_no'] = ($dlySdf['logi_no'] ? $dlySdf['logi_no'] : $val['logi_no']);
            
            $dlyItems[$key] = $val;
        }
        
        $dlySdf['delivery_items'] = $dlyItems;
        
        //删除收货人信息
        unset($dlySdf['consignee']);
        
        //销毁
        unset($delivery_items, $dlyItems);
        
        return $dlySdf;
    }
    
    /**
     * 映射WMS仓储物流编码
     * @todo：兼容京东传的物流单号是ID
     * 
     * @param string $logi_code
     * @return string
     */
    public function getLogiCode($logi_code, $wms_id)
    {
        $dlyCorpObj = app::get('ome')->model('dly_corp');
        $relationObj = app::get('wmsmgr')->model('express_relation');
        
        //获取物流公司对照关系
        $logiMapInfo = $relationObj->dump(array('wms_id'=>$wms_id, 'wms_express_bn'=>$logi_code), 'sys_express_bn');
        
        $logi_code = ($logiMapInfo['sys_express_bn'] ? $logiMapInfo['sys_express_bn'] : $logi_code);
        
        //获取物流公司信息
        $dlyInfo = $dlyCorpObj->dump(array('type'=>$logi_code), 'corp_id,type,name');
        
        //[兼容]指定物流公司
        if(empty($dlyInfo)){
            //QT其它物流公司
            $logi_type = 'QT';
            $dlyInfo = $dlyCorpObj->dump(array('type'=>$logi_type), 'corp_id,type,name');
            
            //JD京东物流公司
            if(empty($dlyInfo)){
                $logi_type = 'JD';
                $dlyInfo = $dlyCorpObj->dump(array('type'=>$logi_type), 'corp_id,type,name');
            }
        }
        
        $logi_code = ($dlyInfo['type'] ? $dlyInfo['type'] : $logi_code);
        
        return $logi_code;
    }
    
    /**
     * 处理京东云交易赠品
     */
    public function disposeKeplerGift($delivery, $params)
    {
        $packageObj = app::get('ome')->model('delivery_package');
        
        $deliveryId = $delivery['delivery_id'];
        $status = strtolower($params['status']);
        
        //父单号
        $rootOrderId = trim($params['rootOrderId']);
        $rootOrderId = ($rootOrderId && $rootOrderId != '0') ? $rootOrderId : '';
        
        //子单号
        $jd_order_id = $params['oid'];
        
        //check
        if(empty($delivery) || empty($jd_order_id) || empty($params['item']) || $status != 'delivery'){
            return false;
        }
        
        //获取京东包裹里赠品
        $tempList = $packageObj->getList('*', array('delivery_id'=>$deliveryId, 'is_wms_gift'=>'true'));
        if(empty($tempList)){
            return false;
        }
        
        $packageList = array();
        foreach ($tempList as $key => $val)
        {
            $package_bn = $val['package_bn'];
            $outer_sku = ($val['outer_sku'] ? $val['outer_sku'] : $val['bn']);
            
            //过滤已取消记录
            if($val['status'] == 'cancel'){
                continue;
            }
            
            $packageList[$package_bn][$outer_sku] = $val;
        }
        
        if(empty($packageList)){
            return false;
        }
        
        //items
        $itemsList = json_decode($params['item'], true);
        foreach ($itemsList as $key => $val)
        {
            $isRootFlag = false;
            $outer_sku = $val['product_bn'];
            
            //包裹信息
            if($packageList[$rootOrderId][$outer_sku]){
                $isRootFlag = true;
                $packageInfo = $packageList[$rootOrderId][$outer_sku];
            }else{
                $packageInfo = $packageList[$jd_order_id][$outer_sku];
            }
            
            $package_id = $packageInfo['package_id'];
            if(empty($package_id)){
                continue;
            }
            
            //获取物流信息
            $logi_code = '';
            $val['logistics'] = trim($val['logistics']);
            $val['logistics'] = str_replace(array('"', "'"), '', $val['logistics']);
            if($val['logistics'] && $val['logi_no']){
                $logi_code = $this->getLogiCode($val['logistics'], $delivery['wms_id']);
            }
            
            //如果有子单取消父单
            if ($isRootFlag && $rootOrderId) {
                //取消父单
                $packageObj->update(array('status'=>'cancel'), array('package_bn'=>$rootOrderId));
                
                //创建子单
                $saveData = array(
                        'delivery_id' => $packageInfo['delivery_id'],
                        'order_id' => $packageInfo['order_id'],
                        'package_bn' => $jd_order_id,
                        'logi_bn' => $logi_code,
                        'logi_no' => $val['logi_no'],
                        'product_id' => $val['product_id'],
                        'bn' => $packageInfo['bn'],
                        'outer_sku' => $packageInfo['outer_sku'],
                        'status' => $status,
                        'number' => $val['num'],
                        'create_time' => time(),
                        'delivery_time' => time(),
                        'is_wms_gift' => 'true', //赠品类型
                        'main_sku_id' => $packageInfo['main_sku_id'], //关联主sku
                );
                $packageObj->insert($saveData);
            }elseif($logi_code){
                //更新物流信息
                $saveData = array(
                        'status' => $status,
                        'logi_bn' => $logi_code,
                        'logi_no' => $val['logi_no'],
                );
                $packageObj->update($saveData, array('package_id'=>$package_id));
            }
        }
        
        return true;
    }
    
    /**
     * 重试推送请求WMS失败的发货单
     * 
     * @return bool
     */
    public function auto_retry_wms_delivery()
    {
        $orderMdl = app::get('ome')->model('orders');
        $deliveryMdl = app::get('ome')->model('delivery');
        $deliveryOrderMdl = app::get('ome')->model('delivery_order');
        $apiFailModel = app::get('erpapi')->model('api_fail');
        
        // 是否开启配置项：发货单推送失败
        $retryConfig = app::get('ome')->getConf('ome.delivery.retry_push');
        if($retryConfig != 'on'){
            return false;
        }
        
        //filter
        $objType = 'delivery';
        $retryError = array(
//                '商品无货', //商品无货
                'timeout', //超时
                'e00090', //请求超时
                'timed out', //请求超时
        );
        
        //查找时间范围：1天前 ~ 10分钟之前请求WMS失败的发货单
        $start_time = strtotime('-1 days');
        $end_time = time() - (10 * 60);
        $filter  = array(
            'create_time|between' => [$start_time, $end_time],
            'obj_type' => $objType,
            'fail_times|lthan' => 5, //失败次数
            'status' => 'fail',
            'filter_sql' => ' err_msg REGEXP "' . implode('|', $retryError) . '"',
        );
        $dataList = $apiFailModel->getList('id,obj_bn,err_code,err_msg,fail_times', $filter, 0, 50, 'create_time DESC,fail_times ASC');
        if(empty($dataList)){
            return false;
        }
        
        //list
        foreach ($dataList as $key => $value)
        {
            $fail_id = $value['id'];
            $obj_bn = $value['obj_bn'];
            
            // 查询发货单状态
            //@todo：必须实时查询发货单状态，防止发货单状态已经变化；
            $deliveryInfo = $deliveryMdl->dump(array('delivery_bn'=>$obj_bn), 'delivery_id,delivery_bn,status,pause,process,sync_status');
            if(empty($deliveryInfo)){
                //删除此记录
                $apiFailModel->delete(array('id'=>$fail_id));
                
                continue;
            }elseif(!in_array($deliveryInfo['status'], array('progress','ready'))){
                //删除此记录
                $apiFailModel->delete(array('id'=>$fail_id));
                
                continue;
            }
            
            // exec
            if($deliveryInfo['pause'] == 'false' && $deliveryInfo['process'] == 'false' && in_array($deliveryInfo['sync_status'], ['0','1','2','11'])){
                // 查询发货单关联的订单ID
                $orderIdList = $deliveryOrderMdl->getList('order_id,delivery_id', array('delivery_id'=>$deliveryInfo['delivery_id']));
                $orderIds = array_column($orderIdList,'order_id');
                
                // 查询订单状态
                //@todo：必须实时查询订单状态，防止订单状态已经变化；
                $orderList = $orderMdl->getList('order_id,status,process_status,pay_status,ship_status', array('order_id'=>$orderIds));
                if(empty($orderList)){
                    continue;
                }
                
                // 订单状态判断
                $is_push = true;
                foreach ($orderList as $orderKey => $orderInfo)
                {
                    // 订单已作废 or 已发货 or 全额退款
                    if ($orderInfo['status'] == 'dead' || $orderInfo['ship_status'] == '1' || in_array($orderInfo['pay_status'], ['5'])) {
                        //删除此记录
                        $apiFailModel->delete(array('id'=>$fail_id));
                        
                        // flag
                        $is_push = false;
                        
                        continue 2;
                    }
                    
                    // 退款申请中 or 退款中
                    if (in_array($orderInfo['pay_status'], ['6', '7'])) {
                        // 失败次数+1
                        $update_sql = "UPDATE sdb_erpapi_api_fail SET fail_times=fail_times+1, last_modify=". time()." WHERE id=". $fail_id;
                        $apiFailModel->db->exec($update_sql);
                        
                        // flag
                        $is_push = false;
                        
                        continue 2;
                    }
                }
                
                // 发货单通知单推送仓库
                if ($is_push) {
                    ome_delivery_notice::create($deliveryInfo['delivery_id']);
                }
            }
        }
        
        return true;
    }
}