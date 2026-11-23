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
 * 唯品会平台拣货单任务Lib类
 *
 * @author wangbiao@shopex.cn
 * @version 2025.03.31
 */
class console_inventory_orders extends console_abstract
{
    /**
     * 通过拣货单号获取JIT订单明细
     *
     * @param $pickInfo
     * @return void
     */
    public function getJitorderdetail($pickInfo)
    {
        $pickObj = app::get('purchase')->model('pick_bills');
        $pickOrderItemMdl = app::get('purchase')->model('pick_order_items');
        
        $barcodeLib = kernel::single('material_basic_material_barcode');
        
        $po_bn = $pickInfo['po_bn'];
        $pick_no = $pickInfo['pick_no'];
        $shop_id = $pickInfo['shop_id'];
        $error_msg = '';
        
        //check
        if(empty($po_bn) || empty($pick_no) || empty($shop_id)){
            $error_msg = '无效的请求数据';
        }
        
        //update
        $pickObj->update(array('pull_status'=>'running', 'pull_order_msg'=>$error_msg), array('pick_no'=>$pick_no));
        
        //error
        if($error_msg){
            return $this->error($error_msg);
        }
        
        //params
        $params = array();
        $params['request'] = array(
            'system' => 'shopex',
            'po' => $po_bn,
            'pick_no' => $pick_no,
        );
        
        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->purchase_getJitorderdetail($params);
        if($result['rsp'] != 'succ'){
            $error_msg = ($result['err_msg'] ? $result['err_msg'] : $result['error_msg']);
            
            //update
            $pickObj->update(array('pull_status'=>'fail', 'pull_order_msg'=>$error_msg), array('pick_no'=>$pick_no));
            
            return $this->error($error_msg);
        }
        
        $total = $result['total'];
        $dataList = $result['dataList'];
        
        //check
        if(empty($dataList)){
            $error_msg = '没有返回数据';
            
            //update
            $pickObj->update(array('pull_status'=>'fail', 'pull_order_msg'=>$error_msg), array('pick_no'=>$pick_no));
            
            return $this->error($error_msg);
        }
        
        //update
        $pickObj->update(array('pull_status'=>'succ'), array('pick_no'=>$pick_no));
        
        //order_sn
        $orderBns = array_column($dataList, 'order_sn');
        $orderBns = array_unique($orderBns);
        
        //list
        $pickItemList = array();
        $tempList = $pickOrderItemMdl->getList('item_id,order_sn,good_sn,amount', array('order_sn'=>$orderBns));
        if($tempList){
            foreach ($tempList as $tempKey => $tempVal)
            {
                $order_sn = $tempVal['order_sn'];
                $good_sn = $tempVal['good_sn'];
                
                $pickItemList[$order_sn][$good_sn] = $tempVal;
            }
        }
        
        //barcode
        $barcodes = array_column($dataList, 'good_sn');
        $barcodes = array_filter($barcodes);
        
        //product
        $barcodeList = array();
        if($barcodes){
            $productList = $barcodeLib->getBmListByBarcode($barcodes);
            if($productList){
                //format
                foreach ($productList as $productKey => $productVal)
                {
                    $barcode = $productVal['barcode'];
                    
                    $barcodeList[$barcode] = $productVal;
                }
            }
        }
        
        //save
        foreach ($dataList as $dataKey => $dataVal)
        {
            $order_sn = $dataVal['order_sn'];
            $barcode = $dataVal['good_sn'];
            
            //check
            if(empty($order_sn)){
                continue;
            }
            
            if($pickItemList[$order_sn][$barcode]){
                continue;
            }
            
            //shop
            $dataVal['shop_id'] = $shop_id;
            
            //product
            $productInfo = array();
            if(isset($barcodeList[$barcode])){
                $productInfo = $barcodeList[$barcode];
            }
            
            $dataVal['product_id'] = ($productInfo['bm_id'] ? $productInfo['bm_id'] : 0);
            $dataVal['product_bn'] = ($productInfo['material_bn'] ? $productInfo['material_bn'] : '');
            
            //time
            $dataVal['add_time'] = ($dataVal['add_time'] ? strtotime($dataVal['add_time']) : 0);
            $dataVal['update_time'] = ($dataVal['update_time'] ? strtotime($dataVal['update_time']) : 0);
            $dataVal['delivery_kpi_start_time'] = ($dataVal['delivery_kpi_start_time'] ? strtotime($dataVal['delivery_kpi_start_time']) : 0);
            
            //insert
            $pickOrderItemMdl->insert($dataVal);
        }
        
        return $this->succ('获取JIT订单明细成功');
    }
    
    /**
     * 拉取唯品会实时销售订单并进行库存冻结预占
     *
     * @param $pickInfo
     * @return void
     */
    public function getInventoryOccupiedOrders($params)
    {
        $inventoryOrderMdl = app::get('console')->model('inventory_orders');
        $invOrderItemMdl = app::get('console')->model('inventory_order_items');
        
        $shop_id = $params['shop_id'];
        $start_time = $params['start_time'];
        $end_time = $params['end_time'];
        $page = $params['page'];
        $has_next = false;
        $error_msg = '';
        
        //[唯品会销售订单]库存预占类型
        $freeze_obj_type = material_basic_material_stock_freeze::__VOP_INVENTORY_ORDER;
        $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
        $log_type = $this->inventory_freeze_type;
        
        //check
        if(empty($shop_id)){
            $error_msg = 'shop_id不能为空,请检查';
            $this->error($error_msg);
        }
        
        if(empty($page)){
            $error_msg = '请求页码不能为空';
            $this->error($error_msg);
        }
        
        if(empty($start_time) || empty($end_time)){
            $error_msg = '开始时间、结束时间不能为空';
            $this->error($error_msg);
        }
        
        //params
        $requestParams = array(
            'start_time' => $start_time,
            'end_time' => $end_time,
            'page' => $page,
            'page_size' => $this->page_size,
        );
        
        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->purchase_getInventoryOccupiedOrders($requestParams);
        if($result['rsp'] != 'succ') {
            $error_msg = $result['error_msg'];
            
            return $this->error($error_msg);
        }
        
        //check
        if(empty($result['dataList'])) {
            $error_msg = '返回数据为空';
            
            return $this->error($error_msg);
        }
        
        //是否有下一页
        if ($result['has_next']) {
            $has_next = true;
        }
        $current_num = count($result['dataList']);
        
        //order_sn
        $orderBns = array_column($result['dataList'], 'order_sn');
        
        //orders
        $orderList = $this->_getInventoryOrders($orderBns);
        
        //barcode
        $barcodeList = $this->_getBarcodeList($result['dataList']);
        
        //list
        $freezeList = [];
        foreach ($result['dataList'] as $dataKey => $dataInfo)
        {
            $order_sn = $dataInfo['order_sn'];
            $create_time = isset($dataInfo['barcodes'][0]['create_time'])  ? strtotime($dataInfo['barcodes'][0]['create_time']) :  time();
            
            //check
            if(empty($order_sn)){
                continue;
            }
            
            //master
            $masterData = array(
                'shop_id'                => $shop_id,
                'order_sn'               => $dataInfo['order_sn'],
                'root_order_sn'          => $dataInfo['root_order_sn'],
                'hold_flag'              => $dataInfo['hold_flag'],
                'address_code'           => $dataInfo['address_code'],
                'is_prebuy'              => $dataInfo['is_prebuy'],
                'occupied_order_sn'      => $dataInfo['occupied_order_sn'],
                'sale_warehouse'         => $dataInfo['sale_warehouse'],
                'brand_id'               => isset($dataInfo['barcodes'][0]['brand_id'])  ? $dataInfo['barcodes'][0]['brand_id'] : 0,
                'warehouse'              => isset($dataInfo['barcodes'][0]['warehouse'])  ? $dataInfo['barcodes'][0]['warehouse'] : '',
                'warehouse_flag'         => isset($dataInfo['barcodes'][0]['warehouse_flag'])  ? $dataInfo['barcodes'][0]['warehouse_flag'] : 0,
                'cooperation_no'         => isset($dataInfo['barcodes'][0]['cooperation_no'])  ? $dataInfo['barcodes'][0]['cooperation_no'] : '',
                'cooperation_mode'       => isset($dataInfo['barcodes'][0]['cooperation_mode'])  ? $dataInfo['barcodes'][0]['cooperation_mode'] : '',
                'sales_source_indicator' => isset($dataInfo['barcodes'][0]['sales_source_indicator'])  ? $dataInfo['barcodes'][0]['sales_source_indicator'] : 0,
                'create_time'            => $create_time,
            );
            
            //save master
            if(empty($orderList[$order_sn])){
                $id = $inventoryOrderMdl->insert($masterData);
                
                $orderList[$order_sn] = $masterData;
            }else{
                $id = $orderList[$order_sn]['id'];
            }
            
            //id
            $orderList[$order_sn]['id'] = $id;
            
            //check
            if(!isset($dataInfo['barcodes']) || empty($dataInfo['barcodes'])){
                continue;
            }
            
            //barcode
            foreach ($dataInfo['barcodes'] as $barcodeKey => $barcodeInfo)
            {
                $barcode = $barcodeInfo['barcode'];
                if(empty($barcode)){
                    continue;
                }
                
                //time
                $item_create_time = isset($barcodeInfo['create_time'])  ? strtotime($barcodeInfo['create_time']) :  time();
                
                //product
                $productInfo = array();
                if(isset($barcodeList[$barcode])){
                    $productInfo = $barcodeList[$barcode];
                }
                
                //data
                $itemData = array(
                    'barcode' => $barcodeInfo['barcode'],
                    'product_id' => ($productInfo['bm_id'] ? $productInfo['bm_id'] : 0),
                    'product_bn' => ($productInfo['material_bn'] ? $productInfo['material_bn'] : ''),
                    'amount' => $barcodeInfo['amount'],
                    'sales_no' => $barcodeInfo['sales_no'],
                    'pick_no' => $barcodeInfo['pick_no'],
                    'brand_id' => $barcodeInfo['brand_id'],
                    'warehouse' => $barcodeInfo['warehouse'],
                    'warehouse_flag' => $barcodeInfo['warehouse_flag'],
                    'sale_warehouse' => $barcodeInfo['sale_warehouse'],
                    'cooperation_no' => $barcodeInfo['cooperation_no'],
                    'cooperation_mode' => $barcodeInfo['cooperation_mode'],
                    'sales_source_indicator' => $barcodeInfo['sales_source_indicator'],
                    'create_time' => $item_create_time,
                );
                
                //is_fail
                if(empty($itemData['product_id'])){
                    $itemData['is_fail'] = true;
                    
                    //update
                    $inventoryOrderMdl->update(array('is_fail'=>true), array('id'=>$id));
                }
                
                //save items
                if(empty($orderList[$order_sn]['items'][$barcode])){
                    $itemData['id'] = $id;
                    
                    $invOrderItemMdl->insert($itemData);
                    
                    //冻结数据列表
                    $freezeList[] = [
                        'bmsq_id' => $bmsq_id, //配额ID  -1代表非配额货品
                        'obj_type' => $freeze_obj_type, //库存预占类型ID
                        'log_type' => $log_type, //库存预占类型
                        'bill_type' => 0, //业务类型
                        'shop_id' => $shop_id,
                        'obj_id' => $id, //单据ID
                        'obj_bn' => $order_sn, //单据单号
                        'bm_id' => $productInfo['bm_id'],
                        'sm_id' => 0, //销售物料ID
                        'branch_id' => 0, //仓库ID
                        'num' => $barcodeInfo['amount'],
                    ];
                }
            }
        }
        
        //批量预占库存
        if($freezeList){
            $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
            $basicMStockFreezeLib->freezeBatch($freezeList, __CLASS__.'::'.__FUNCTION__, $error_msg);
        }
        
        $data = array(
            'has_next' => $has_next,
            'total_num' => $current_num, //数据总记录数
            'current_num' => $current_num, //本次拉取记录数
            'current_succ_num' => $current_num, //处理成功记录数
            'current_succ_num' => 0, //处理失败记录数
        );
        
        return $this->succ('获取实时销售订单成功', $data);
    }
    
    /**
     * 拉取唯品会实时销售订单并进行库存冻结预占
     *
     * @param $pickInfo
     * @return void
     */
    public function getInventoryCancelledOrders($params)
    {
        $inventoryOrderMdl = app::get('console')->model('inventory_orders');
        $invOrderItemMdl = app::get('console')->model('inventory_order_items');
        
        $shop_id = $params['shop_id'];
        $start_time = $params['start_time'];
        $end_time = $params['end_time'];
        $page = $params['page'];
        $has_next = false;
        
        //[唯品会销售订单]库存预占类型
        $freeze_obj_type = material_basic_material_stock_freeze::__VOP_INVENTORY_ORDER;
        $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
        $log_type = $this->inventory_freeze_type;
        $error_msg = '';
        
        //check
        if(empty($shop_id)){
            $error_msg = 'shop_id不能为空,请检查';
            $this->error($error_msg);
        }
        
        if(empty($page)){
            $error_msg = '请求页码不能为空';
            $this->error($error_msg);
        }
        
        if(empty($start_time) || empty($end_time)){
            $error_msg = '开始时间、结束时间不能为空';
            $this->error($error_msg);
        }
        
        //params
        $requestParams = array();
        $requestParams['inventoryCancelledOrdersRequest'] = array(
            'st_query_time' => $start_time,
            'et_query_time' => $end_time,
            'page' => $page,
            'limit' => $this->page_size,
        );
        
        //request
        $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->purchase_getInventoryCancelledOrders($requestParams);
        if($result['rsp'] != 'succ') {
            $error_msg = $result['error_msg'];
            
            return $this->error($error_msg);
        }
        
        //check
        if(empty($result['dataList'])) {
            $error_msg = '返回数据为空';
            
            return $this->error($error_msg);
        }
        
        //是否有下一页
        if ($result['has_next']) {
            $has_next = true;
        }
        $current_num = count($result['dataList']);
        
        //order_sn
        $orderBns = array_column($result['dataList'], 'order_sn');
        
        //orders
        $orderList = $this->_getInventoryOrders($orderBns);
        
        //list
        $unFreezeList = [];
        foreach ($result['dataList'] as $dataKey => $dataInfo)
        {
            $order_sn = $dataInfo['order_sn'];
            
            //check
            if(empty($order_sn)){
                continue;
            }
            
            //check
            if(empty($orderList[$order_sn])){
                continue;
            }
            
            if($orderList[$order_sn]['platform_status'] == 'cancel' || $orderList[$order_sn]['dispose_status'] == 'cancel'){
                //已取消的订单已经存在,无需再更新
                continue;
            }
            
            if(!isset($dataInfo['barcodes']) || empty($dataInfo['barcodes'])){
                continue;
            }
            
            //info
            $orderInfo = $orderList[$order_sn];
            $order_id = $orderInfo['id'];
            
            //items
            foreach ($dataInfo['barcodes'] as $barcodeKey => $barcodeInfo)
            {
                $barcode = $barcodeInfo['barcode'];
                
                //check
                if(empty($barcode)){
                    continue;
                }
                
                if(!isset($orderInfo['items'][$barcode]) || empty($orderInfo['items'][$barcode])){
                    continue;
                }
                
                $barcodeInfo = $orderInfo['items'][$barcode];
                $item_id = $barcodeInfo['item_id'];
                $product_id = $barcodeInfo['product_id'];
                $num = $barcodeInfo['amount'];
                
                //check
                if(empty($product_id)){
                    continue;
                }
                
                //未预占库存,不需要释放冻结库存
                if(in_array($barcodeInfo['status'], array('succ','cancel','needless'))){
                    continue;
                }
                
                //[释放]库存预占列表
                $unFreezeList[] = [
                    'bmsq_id' => $bmsq_id, //配额ID：-1代表非配额货品
                    'obj_type' => $freeze_obj_type, //库存预占类型ID
                    'log_type' => $log_type, //库存预占类型
                    'bill_type' => 0, //业务类型
                    'shop_id' => $shop_id,
                    'obj_id' => $order_id, //单据ID
                    'bm_id' => $product_id,
                    'sm_id' => 0,
                    'branch_id' => 0, //仓库ID
                    'num' => $num,
                ];
                
                //update
                $invOrderItemMdl->update(array('status'=>'cancel', 'dispose_msg'=>''), array('item_id'=>$item_id));
            }
            
            //update
            $inventoryOrderMdl->update(array('platform_status'=>'cancel', 'dispose_status'=>'cancel', 'order_source'=>'jitx'), array('id'=>$order_id));
        }
        
        //[批量释放]预占库存
        if($unFreezeList){
            $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
            $basicMStockFreezeLib->unfreezeBatch($unFreezeList, __CLASS__.'::'.__FUNCTION__, $error_msg);
        }
        
        //data
        $data = array(
            'has_next' => $has_next,
            'total_num' => $current_num, //数据总记录数
            'current_num' => $current_num, //本次拉取记录数
            'current_succ_num' => $current_num, //处理成功记录数
            'current_succ_num' => 0, //处理失败记录数
        );
        
        return $this->succ('获取已取消的销售单成功', $data);
    }
    
    /**
     * 按订单号+条形码汇总订单列表
     * 
     * @param $orderBns
     * @return array|false
     */
    public function _getInventoryOrders($orderBns)
    {
        $inventoryOrderMdl = app::get('console')->model('inventory_orders');
        $invOrderItemMdl = app::get('console')->model('inventory_order_items');
        
        $orderList = array();
        
        //orders
        $tempList = $inventoryOrderMdl->getList('id,order_sn,dispose_status,platform_status', array('order_sn'=>$orderBns));
        if(empty($tempList)){
            return false;
        }
        
        $tempList = array_column($tempList, null, 'id');
        $ids = array_keys($tempList);
        
        //items
        $itemList = $invOrderItemMdl->getList('item_id,id,product_id,product_bn,barcode,amount', array('id'=>$ids));
        if($itemList){
            foreach ($itemList as $itemKey => $itemVal)
            {
                $id = $itemVal['id'];
                $barcode = $itemVal['barcode'];
                
                //check
                if(empty($tempList[$id])){
                    continue;
                }
                
                $orderInfo = $tempList[$id];
                $order_sn = $orderInfo['order_sn'];
                
                //merge
                if(!isset($orderList[$order_sn])){
                    $orderList[$order_sn] = $orderInfo;
                }
                
                $orderList[$order_sn]['items'][$barcode] = $itemVal;
            }
        }
        
        return $orderList;
    }
    
    /**
     * 按barcode条形码获取关联的OMS货品信息
     *
     * @param $orderBns
     * @return array|false
     */
    public function _getBarcodeList($dataList)
    {
        $barcodeLib = kernel::single('material_basic_material_barcode');
        
        //check
        if(empty($dataList)){
            return array();
        }
        
        //list
        $barcodes = array();
        foreach ($dataList as $dataKey => $dataInfo)
        {
            if(!isset($dataInfo['barcodes']) || empty($dataInfo['barcodes'])){
                continue;
            }
            
            //barcode
            foreach ($dataInfo['barcodes'] as $barcodeKey => $barcodeInfo)
            {
                $barcode = $barcodeInfo['barcode'];
                if(empty($barcode)){
                    continue;
                }
                
                $barcodes[$barcode] = $barcode;
            }
        }
        
        //check
        if(empty($barcodes)){
            return array();
        }
        
        //product
        $productList = $barcodeLib->getBmListByBarcode($barcodes);
        if(empty($productList)){
            return array();
        }
        
        //format
        $barcodeList = array();
        foreach ($productList as $productKey => $productVal)
        {
            $barcode = isset($productVal['code']) ? $productVal['code'] : $productVal['barcode'];
            
            $barcodeList[$barcode] = $productVal;
        }
        
        return $barcodeList;
    }
    
    /**
     * 通过销售订单ID获取对应的所有订单明细(以订单ID为下标)
     *
     * @param $ids
     * @return array
     */
    public function getOrderitemList($ids)
    {
        $invOrderItemMdl = app::get('console')->model('inventory_order_items');
        
        //check
        if(empty($ids)){
            return false;
        }
        
        //items
        $tempList = $invOrderItemMdl->getList('item_id,id,barcode,product_id,product_bn,status,amount,warehouse', array('id'=>$ids, 'status'=>array('none','fail')), 0, -1);
        if(empty($tempList)){
            return false;
        }
        
        //format
        $itemList = array();
        foreach ($tempList as $tempKey => $tempVal)
        {
            $id = $tempVal['id'];
            $item_id = $tempVal['item_id'];
            
            $itemList[$id][$item_id] = $tempVal;
        }
        
        return $itemList;
    }
    
    /**
     * [唯品会销售订单]增加货品的库存预占冻结数量和店铺冻结数量
     * @todo：调用之前，已经有方法重置指定单个货品,店铺冻结数量为0;这里只需要累加店铺冻结数量;
     *
     * @param $product_id
     * @param $error_msg
     * @return bool
     */
    public function getVopOrderStockFreeze($product_id=0, &$error_msg=null)
    {
        $inventoryOrderMdl = app::get('console')->model('inventory_orders');
        $invOrderItemMdl = app::get('console')->model('inventory_order_items');
        
        //filter
        $filter = array('status'=>array('none','running','fail'));
        if($product_id){
            $filter['product_id'] = $product_id;
        }
        
        //count
        $count = $invOrderItemMdl->count($filter);
        if($count <= 0){
            $error_msg = '没有可执行的货品数据';
            return false;
        }
        
        //page
        $page_size = $this->page_size;
        $pageNum = ceil($count / $page_size);
        
        //exec
        $dataList = array();
        for($page=1; $page<=$pageNum; $page++)
        {
            $offset = ($page -1) * $page_size;
            
            //list
            $itemList = $invOrderItemMdl->getList('item_id,id,product_id,product_bn,status,amount', $filter, $offset, $page_size);
            if(empty($itemList)){
                continue;
            }
            
            //id
            $ids = array_column($itemList, 'id');
            $ids = array_unique($ids);
            
            //order
            $orderList = $inventoryOrderMdl->getList('id,order_sn,shop_id', array('id'=>$ids));
            $orderList = array_column($orderList, null, 'id');
            
            //items
            foreach ($itemList as $itemKey => $itemVal)
            {
                $id = $itemVal['id'];
                $item_id = $itemVal['item_id'];
                $product_id = $itemVal['product_id'];
                $nums = $itemVal['amount'];
                $shop_id = '';
                
                //orderInfo
                $orderInfo = $orderList[$id];
                if($orderInfo){
                    $shop_id = $orderInfo['shop_id'];
                }
                
                //check
                if(empty($product_id) || empty($nums) || empty($shop_id)){
                    continue;
                }
                
                //订单确认状态不是(部分拆分、已确认),并且confirm确认状态为Y
                if(!in_array($itemVal['status'], array('none','running','fail'))){
                    continue;
                }
                
                //merge
                $itemVal = array_merge($itemVal, $orderInfo);
                
                $dataList[$id][$item_id] = $itemVal;
                
            }
        }
        
        return $dataList;
    }
    
    /**
     * [唯品会销售订单]增加货品的库存预占冻结数量和店铺冻结数量
     * @todo：调用之前，已经有方法重置指定单个货品,店铺冻结数量为0;这里只需要累加店铺冻结数量;
     *
     * @param $product_id
     * @param $error_msg
     * @return bool
     */
    public function reset_vopshop_stock_freeze($product_id=0, &$error_msg=null)
    {
        $inventoryOrderMdl = app::get('console')->model('inventory_orders');
        $invOrderItemMdl = app::get('console')->model('inventory_order_items');
        
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        //[唯品会销售订单]库存预占类型
        $freeze_obj_type = material_basic_material_stock_freeze::__VOP_INVENTORY_ORDER;
        $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
        $log_type = $this->inventory_freeze_type;
        $error_msg = '';
        
        //filter
        $filter = array('status'=>array('none','running','fail'));
        if($product_id){
            $filter['product_id'] = $product_id;
        }
        
        //count
        $count = $invOrderItemMdl->count($filter);
        if($count <= 0){
            $error_msg = '没有可执行的货品数据';
            return false;
        }
        
        //page
        $page_size = $this->page_size;
        $pageNum = ceil($count / $page_size);
        
        //exec
        for($page=1; $page<=$pageNum; $page++)
        {
            $offset = ($page -1) * $page_size;
            
            //list
            $itemList = $invOrderItemMdl->getList('item_id,id,product_id,product_bn,status,amount', $filter, $offset, $page_size);
            if(empty($itemList)){
                continue;
            }
            
            //id
            $ids = array_column($itemList, 'id');
            $ids = array_unique($ids);
            
            //order
            $orderList = $inventoryOrderMdl->getList('id,order_sn,shop_id', array('id'=>$ids));
            $orderList = array_column($orderList, null, 'id');
            
            //items
            $freezeList = [];
            foreach ($itemList as $itemKey => $itemVal)
            {
                $id = $itemVal['id'];
                $product_id = $itemVal['product_id'];
                $nums = $itemVal['amount'];
                $shop_id = '';
                
                //orderInfo
                $orderInfo = $orderList[$id];
                $order_sn = $orderInfo['order_sn'];
                if($orderInfo){
                    $shop_id = $orderInfo['shop_id'];
                }
                
                //check
                if(empty($product_id) || empty($nums) || empty($shop_id)){
                    continue;
                }
                
                //订单确认状态不是(部分拆分、已确认),并且confirm确认状态为Y
                if(!in_array($itemVal['status'], array('none','running','fail'))){
                    continue;
                }
                
                //冻结数据列表
                $freezeList[] = [
                    'bmsq_id' => $bmsq_id, //配额ID  -1代表非配额货品
                    'obj_type' => $freeze_obj_type, //库存预占类型ID
                    'log_type' => $log_type, //库存预占类型
                    'bill_type' => 0, //业务类型
                    'shop_id' => $shop_id,
                    'obj_id' => $id, //单据ID
                    'obj_bn' => $order_sn, //单据单号
                    'bm_id' => $product_id,
                    'sm_id' => 0, //销售物料ID
                    'branch_id' => 0, //仓库ID
                    'num' => $nums,
                ];
            }
            
            //check
            if(empty($freezeList)){
                continue;
            }
            
            //批量预占库存
            if($freezeList){
                $basicMStockFreezeLib->freezeBatch($freezeList, __CLASS__.'::'.__FUNCTION__, $error_msg);
            }
        }
        
        return true;
    }
    
    /**
     * 核销处理订单
     *
     * @param $filter
     * @return void
     */
    public function disposeInventoryOrders($filter)
    {
        $orderMdl = app::get('ome')->model('orders');
        $inventoryOrderMdl = app::get('console')->model('inventory_orders');
        $invOrderItemMdl = app::get('console')->model('inventory_order_items');
        
        //[唯品会销售订单]库存预占类型
        $freeze_obj_type = material_basic_material_stock_freeze::__VOP_INVENTORY_ORDER;
        $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
        $log_type = $this->inventory_freeze_type;
        $error_msg = '';
        
        //check
        if(empty($filter)){
            $error_msg = '无效的处理操作!';
            return $this->error($error_msg);
        }
        
        //list
        $orderList = $inventoryOrderMdl->getList('id,order_sn,shop_id,platform_status,dispose_status', $filter, 0, -1);
        if(empty($orderList)){
            $error_msg = '没有可操作的销售订单';
            return $this->error($error_msg);
        }
        
        //id
        $ids = array_column($orderList, 'id');
        $orderBns = array_column($orderList, 'order_sn');
        $shop_id = $orderList[0]['shop_id'];
        
        //running
        $inventoryOrderMdl->update(array('dispose_status'=>'running'), array('id'=>$ids));
        
        //获取OMS订单列表
        $omsOrderList = $orderMdl->getList('order_id,order_bn,process_status,ship_status', array('order_bn'=>$orderBns, 'shop_id'=>$shop_id));
        if($omsOrderList){
            $omsOrderList = array_column($omsOrderList, null, 'order_bn');
        }
        
        //items
        $itemList = $this->getOrderitemList($ids);
        
        //处理订单
        $errorList = array();
        $unFreezeList = [];
        foreach ($orderList as $orderKey => $orderInfo)
        {
            $id = $orderInfo['id'];
            $order_sn = $orderInfo['order_sn'];
            $shop_id = $orderInfo['shop_id'];
            
            //check
            if(in_array($orderInfo['platform_status'], array('cancel'))){
                //fail
                $errorList[$order_sn] = '平台订单是已取消状态';
                
                //unset
                unset($orderList[$orderKey]);
                
                continue;
            }
            
            if(in_array($orderInfo['dispose_status'], array('finish','needless'))){
                //fail
                $errorList[$order_sn] = '状态不允许处理('. $orderInfo['dispose_status'] .')';
                
                //unset
                unset($orderList[$orderKey]);
                
                continue;
            }
            
            //check
            if(empty($itemList[$id])){
                //fail
                $errorList[$order_sn] = '销售订单没有商品明细';
                
                //unset
                unset($orderList[$orderKey]);
                
                continue;
            }
            
            //items
            $orderInfo['items'] = $itemList[$id];
            
            //订单已经在OMS系统里存在,设置为：无需处理,并且释放预占冻结库存
            if(isset($omsOrderList[$order_sn])){
                foreach ($orderInfo['items'] as $itemKey => $itemInfo)
                {
                    $item_id = $itemInfo['item_id'];
                    $product_id = $itemInfo['product_id'];
                    $num = $itemInfo['amount'];
                    
                    //[释放]库存预占列表
                    $unFreezeList[] = [
                        'bmsq_id' => $bmsq_id, //配额ID：-1代表非配额货品
                        'obj_type' => $freeze_obj_type, //库存预占类型ID
                        'log_type' => $log_type, //库存预占类型
                        'bill_type' => 0, //业务类型
                        'shop_id' => $shop_id,
                        'obj_id' => $id, //单据ID
                        'bm_id' => $product_id,
                        'sm_id' => 0,
                        'branch_id' => 0, //仓库ID
                        'num' => $num,
                    ];
                }
                
                //update
                $inventoryOrderMdl->update(array('dispose_status'=>'finish', 'order_source'=>'jitx', 'dispose_msg'=>''), array('id'=>$id));
                
                $invOrderItemMdl->update(array('status'=>'succ', 'dispose_msg'=>''), array('id'=>$id));
                
                //unset
                unset($orderList[$orderKey]);
            }
        }
        
        //[批量释放]预占库存
        if($unFreezeList){
            $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
            $basicMStockFreezeLib->unfreezeBatch($unFreezeList, __CLASS__.'::'.__FUNCTION__, $error_msg);
        }
        
        //核销拣货订单明细任务
        if($orderList){
            //order_sn
            $orderBns = array_column($orderList, 'order_sn');
            
            //exec
            $paramsFilter = array('order_sn'=>$orderBns);
            $result = $this->disposePickOrderItems($paramsFilter);
            if($result['rsp'] != 'succ' && $result['error_msg']){
                //fail
                $order_sn = $orderBns[0];
                $errorList[$order_sn] = $result['error_msg'];
            }
        }
        
        //check
        if($errorList){
            $error_msg = '';
            foreach ($errorList as $order_bn => $msg)
            {
                $error_msg .= '订单号：'. $order_bn .',失败原因：'. $msg .';';
            }
            
            return $this->error($error_msg);
        }
        
        return $this->succ('核销处理订单成功');
    }
    
    /**
     * 核销拣货订单明细任务
     *
     * @param $filter
     * @return void
     */
    public function disposePickOrderItems($filter)
    {
        $pickOrderItemMdl = app::get('purchase')->model('pick_order_items');
        $inventoryOrderMdl = app::get('console')->model('inventory_orders');
        $invOrderItemMdl = app::get('console')->model('inventory_order_items');
        
        //[唯品会销售订单]库存预占类型
        $freeze_obj_type = material_basic_material_stock_freeze::__VOP_INVENTORY_ORDER;
        $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
        $log_type = $this->inventory_freeze_type;
        $error_msg = '';
        
        //check
        if(empty($filter)){
            $error_msg = '无效的处理操作。';
            return $this->error($error_msg);
        }
        
        //list
        $itemList = $pickOrderItemMdl->getList('item_id,order_sn,status,shop_id,product_id,product_bn,amount', $filter, 0, -1);
        if(empty($itemList)){
            $error_msg = '没有可操作的销售订单';
            return $this->error($error_msg);
        }
        
        //id
        $itemIds = array_column($itemList, 'item_id');
        $orderBns = array_column($itemList, 'order_sn');
        $shop_id = $itemList[0]['shop_id'];
        
        //running
        $pickOrderItemMdl->update(array('status'=>'running'), array('item_id'=>$itemIds));
        
        //获取唯品会销售订单列表
        $orderList = $inventoryOrderMdl->getList('id,order_sn,shop_id,platform_status,dispose_status', array('order_sn'=>$orderBns, 'shop_id'=>$shop_id), 0, -1);
        if($orderList){
            $orderList = array_column($orderList, null, 'order_sn');
            
            //id
            $ids = array_column($orderList, 'id');
            
            $orderItems = $this->getOrderitemList($ids);
            
            //format
            foreach ($orderList as $orderKey => $orderInfo)
            {
                $id = $orderInfo['id'];
                
                //item
                $orderInfo['items'] = $orderItems[$id];
                
                $orderList[$orderKey] = $orderInfo;
            }
        }
        
        //items
        $unFreezeList = [];
        foreach ($itemList as $itemKey => $itemInfo)
        {
            $item_id = $itemInfo['item_id'];
            $order_sn = $itemInfo['order_sn'];
            $product_id = $itemInfo['product_id'];
            $shop_id = $itemInfo['shop_id'];
            
            //check
            if(in_array($itemInfo['status'], array('finish','needless','cancel'))){
                continue;
            }
            
            if(!isset($orderList[$order_sn])){
                //update
                $pickOrderItemMdl->update(array('status'=>'fail'), array('item_id'=>$item_id));
                
                continue;
            }
            
            if(!isset($orderList[$order_sn]['items'])){
                //update
                $pickOrderItemMdl->update(array('status'=>'fail'), array('item_id'=>$item_id));
                
                continue;
            }
            
            //check dispose_status
            if(in_array($orderList[$order_sn]['dispose_status'], array('finish','needless','cancel'))){
                //update
                $status = $orderList[$order_sn]['dispose_status'];
                $pickOrderItemMdl->update(array('status'=>$status), array('item_id'=>$item_id));
                
                continue;
            }
            
            //平台订单已经是取消状态
            if(in_array($orderList[$order_sn]['platform_status'], array('cancel'))){
                //update
                $pickOrderItemMdl->update(array('status'=>'cancel'), array('item_id'=>$item_id));
                
                continue;
            }
            
            $orderInfo = $orderList[$order_sn];
            $order_id = $orderInfo['id'];
            
            //exec
            foreach ($orderInfo['items'] as $ordItemKey => $ordItemInfo)
            {
                $order_item_id = $ordItemInfo['item_id'];
                $order_product_id = $ordItemInfo['product_id'];
                $order_item_num = $ordItemInfo['amount'];
                
                //check
                if(in_array($ordItemInfo['status'], array('succ','needless','cancel'))){
                    continue;
                }
                
                if($order_product_id != $product_id){
                    continue;
                }
                
                //[释放]库存预占列表
                $unFreezeList[] = [
                    'bmsq_id' => $bmsq_id, //配额ID：-1代表非配额货品
                    'obj_type' => $freeze_obj_type, //库存预占类型ID
                    'log_type' => $log_type, //库存预占类型
                    'bill_type' => 0, //业务类型
                    'shop_id' => $shop_id,
                    'obj_id' => $order_id, //单据ID
                    'bm_id' => $product_id,
                    'sm_id' => 0,
                    'branch_id' => 0, //仓库ID
                    'num' => $order_item_num,
                ];
                
                //flag
                $orderList[$order_sn]['items'][$ordItemKey]['status'] = 'succ';
                $orderList[$order_sn]['items'][$ordItemKey]['exec_flag'] = true;
                
                //update
                $pickOrderItemMdl->update(array('status'=>'finish'), array('item_id'=>$item_id));
                
                $invOrderItemMdl->update(array('status'=>'succ', 'dispose_msg'=>''), array('item_id'=>$order_item_id));
            }
        }
        
        //[批量释放]预占库存
        if($unFreezeList){
            $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
            $basicMStockFreezeLib->unfreezeBatch($unFreezeList, __CLASS__.'::'.__FUNCTION__, $error_msg);
        }
        
        //status
        if($orderList){
            foreach ($orderList as $orderKey => $orderInfo)
            {
                $id = $orderInfo['id'];
                
                //check
                if(empty($orderInfo['items'])){
                    continue;
                }
                
                $succItems = array();
                foreach ($orderInfo['items'] as $itemKey => $itemInfo)
                {
                    $item_id = $itemInfo['item_id'];
                    
                    if(in_array($itemInfo['status'], array('succ','needless','cancel'))){
                        $succItems[$item_id] = $itemInfo;
                    }
                }
                
                //update
                $dispose_status = $orderInfo['dispose_status'];
                if(count($succItems) == count($orderInfo['items'])){
                    $dispose_status = 'finish';
                }elseif($succItems){
                    $dispose_status = 'part';
                }
                
                //update
                $inventoryOrderMdl->update(array('dispose_status'=>$dispose_status, 'order_source'=>'jit'), array('id'=>$id));
            }
        }
        
        //unset
        unset($itemList, $orderList, $itemIds, $orderBns);
        
        return $this->succ();
    }
    
    /**
     * 批量查询唯品会商品库存并保存
     *
     * @param $vopShopInfo
     * @param $materialList
     * @return void
     */
    public function downloadVopSkuStock($vopShopInfo, $materialList)
    {
        $vopSkuStockMdl = app::get('vop')->model('sku_stock');
        $vopLib = kernel::single('inventorydepth_shop_vop');
        
        //shop_id
        $shop_id = $vopShopInfo['shop_id'];
        $shop_bn = $vopShopInfo['shop_bn'];
        
        //barcode
        $barcodes = array_column($materialList, 'barcode');
        $barcodes = array_filter($barcodes);
        if(empty($barcodes)){
            $error_msg = '没有可查询的条形码';
            return $this->error($error_msg);
        }
        
        //批量获取唯品会商品库存
        $result = $vopLib->getSkuCartFreezeStocks($vopShopInfo, $barcodes);
        if($result['rsp'] != 'succ'){
            $error_msg = $result['error_msg'];
            return $this->error($error_msg);
        }elseif(empty($result['data'])){
            $error_msg = '没有查询到库存记录';
            return $this->error($error_msg);
        }
        
        //format
        $materialList = array_column($materialList, null, 'barcode');
        
        //已经存在的数据
        $skuStockList = $vopSkuStockMdl->getList('id,shop_id,bm_id,barcode', ['shop_id'=>$shop_id, 'barcode'=>$barcodes]);
        if($skuStockList){
            $skuStockList = array_column($skuStockList, null, 'barcode');
        }
        
        //exec
        foreach ($result['data'] as $key => $val)
        {
            $barcode = $val['barcode'];
            $bm_id = $materialList[$barcode]['bm_id'];
            $material_bn = $materialList[$barcode]['material_bn'];
            
            //data
            $saveData = [
                'shop_id' => $shop_id,
                'shop_bn' => $shop_bn,
                'bm_id' => $bm_id,
                'material_bn' => $material_bn,
                'barcode' => $barcode,
                'leaving_stock' => $val['leaving_stock'], //剩余库存
                'current_hold' => $val['current_hold'], //库存占用(目前为购物车+未支付订单占用的库存值)
                'circuit_break_value' => $val['circuit_break_value'], //熔断值
                'area_warehouse_id' => $val['area_warehouse_id'], //分区仓库代码ID
                'warehouse_flag' => $val['warehouse_flag'], //仓库编码标识
                'unpaid_hold' => $val['unpaid_hold'], //未支付占用数
            ];
            
            //save
            if(isset($skuStockList[$barcode])){
                //unset
                unset($saveData['shop_id'], $saveData['shop_bn'], $saveData['bm_id'], $saveData['material_bn'], $saveData['barcode']);
                
                //update
                $vopSkuStockMdl->update($saveData, ['shop_id'=>$shop_id, 'barcode'=>$barcode]);
            }else{
                //insert
                $vopSkuStockMdl->insert($saveData);
            }
        }
        
        return $this->succ();
    }
}