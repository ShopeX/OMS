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
 * @description 电商仓库存处理类
 * @access public
 * @author xiayuanjun@shopex.cn
 * @ver 0.1
 */
class ome_store_manage_branch extends ome_store_manage_abstract implements ome_store_manage_interface
{
    /** @var ome_branch_product */
    private $_libBranchProduct;
    /** @var material_basic_material_stock */
    private $_basicMaterialStock;
    /** @var material_basic_material_stock_freeze */
    private $_basicMStockFreezeLib;

    public function __construct($is_ctrl_store)
    {
        parent::__construct($is_ctrl_store);

        $this->_libBranchProduct     = kernel::single('ome_branch_product');
        $this->_basicMaterialStock   = kernel::single('material_basic_material_stock');
        $this->_basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
    }

    /**
     * 添加发货单节点的库存处理方法
     *
     * @param array $params 传入参数
     * @param string $err_msg 错误信息
     * @return boolean true/false
     */
    public function addDly($params, &$err_msg)
    {
        $branchObj            = app::get('ome')->model("branch");
        
        $delivBranch = $branchObj->getDelivBranch($params['branch_id']);
        $branchIds   = $delivBranch[$params['branch_id']]['bind_conf'];
        $branchIds[] = $params['branch_id'];

        $nitems         = array();
        $delivery_items = $params['delivery_items'];

        foreach ($delivery_items as $item) {
            // 基础物料不管控库存，则跳过
            $is_product_ctrl_store = true;
            if(isset($item['is_product_ctrl_store']) && $item['is_product_ctrl_store'] === false){
                $is_product_ctrl_store = false;
            }
            
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['number'] += $item['number'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'goods_id'   => $item['goods_id'],
                    'number'     => $item['number'],
                    'bn' => $item['bn'],
                    'is_wms_gift' => $item['is_wms_gift'], //是否京东云交易赠品
                    'is_product_ctrl_store' => $is_product_ctrl_store, //是否管控库存
                );
            }

        }

        ksort($nitems);
        $theDly = app::get('ome')->model('delivery')->db_dump(['delivery_id'=>$params['delivery_id']],'delivery_bn,delivery');
        $delivery_bn = $theDly['delivery_bn'];
        $branchInfo = $branchObj->db_dump(['branch_id'=>$params['branch_id']],'branch_id,branch_bn,is_negative_store');
    
        //开始 SHIPED && 线下订单 order_type  && 仓允许负库存
        $negative_store = false;
        if ($theDly['delivery'] == 'SHIPED' && $branchInfo['is_negative_store'] == 1 && $params['order_type'] == 'offline') {
            $negative_store = true;
        }
        $batchList = $wmsGiftBatchList = [];
        
        // product_id
        $productIds = array_column($nitems, 'product_id');
        
        // 获取指定不需要管控库存的基础物料
        $notCtrlStoreBmList = kernel::single('material_basic_material')->getNotCtrlStoreProducts($productIds);
        
        //增加branch_product的冻结库存
        foreach ($nitems as $key => $items) {
            $product_id = $items['product_id'];
            
            if (!is_numeric($items['number'])) {
                $err_msg .= $items['product_name'] . ":请输入正确数量";
                return false;
            }
            
            // 是否管控库存
            if(isset($notCtrlStoreBmList[$product_id]) && $notCtrlStoreBmList[$product_id]){
                $items['is_product_ctrl_store'] = false;
            }else{
                $items['is_product_ctrl_store'] = true;
            }
            
            //订单货品预占释放
            $tmp = [
                'bm_id'     =>  $items['product_id'],
                'sm_id'     =>  $items['goods_id'],
                'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                'bill_type' =>  0,
                'obj_id'    =>  $params['order_id'],
                'branch_id' =>  '',
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $items['number'], 
                'sync_sku'  =>  false,
                'is_product_ctrl_store' => $items['is_product_ctrl_store'], //是否管控库存
            ];

            //添加仓库预占流水
            $freezeData = [];
            $freezeData['bm_id'] = $items['product_id'];
            $freezeData['sm_id'] = $items['goods_id'];
            $freezeData['bn'] = $items['bn'];
            $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
            $freezeData['bill_type'] = material_basic_material_stock_freeze::__DELIVERY;
            $freezeData['obj_id'] = $params['delivery_id'];
            $freezeData['shop_id'] = $params['shop_id'];
            $freezeData['branch_id'] = $params['branch_id'];
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $items['number'];
            $freezeData['obj_bn'] = $delivery_bn;
            $freezeData['sync_sku'] = false;
            $freezeData['log_type'] = $negative_store === true ? 'negative_stock' : '';
            
            // 是否管控库存
            $freezeData['is_product_ctrl_store'] = $items['is_product_ctrl_store'];
            
            // format
            if ($items['is_wms_gift'] == 'true') {
                //临时解决
                $wmsGiftBatchList['-'][] = $tmp;
                $wmsGiftBatchList['+'][] = $freezeData;
            } else {
                $batchList['-'][] = $tmp;
                $batchList['+'][] = $freezeData;
            }
        }
        //订单货品预占释放
        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($batchList['-'], __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg .= '订单货品冻结释放失败:'. $err .'!';
            return false;
        }
        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($wmsGiftBatchList['-'], __CLASS__.'::'.__FUNCTION__, $err);

        //添加仓库预占流水
        $rs = $this->_basicMStockFreezeLib->freezeBatch($batchList['+'], __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg .= '仓库货品冻结预占失败:'. $err .'!';
            return false;            
        }
        $rs = $this->_basicMStockFreezeLib->freezeBatch($wmsGiftBatchList['+'], __CLASS__.'::'.__FUNCTION__, $err);

        return true;
    }

    /**
     * 取消发货单节点的库存处理方法
     *
     * @param array $params 传入参数
     * @param string $err_msg 错误信息
     * @return boolean true/false
     */
    public function cancelDly($params, &$err_msg)
    {
        $nitems         = array();
        $delivery_items = $params['delivery_items'];

        // 获取sm_id
        $itemIdArr = array_column($delivery_items, 'item_id');
        if ($itemIdArr) {
            $itemDetailMdl = app::get('ome')->model('delivery_items_detail');
            $deliveryItemDetailList = $itemDetailMdl->getList('order_obj_id,delivery_item_id', ['delivery_item_id'=>$itemIdArr]);
            $orderObjList = app::get('ome')->model('order_objects')->getList('obj_id,goods_id',['obj_id'=>array_column($deliveryItemDetailList, 'order_obj_id')]);
            $orderObjList = array_column($orderObjList, null, 'obj_id');

            foreach ($delivery_items as $dik => $div) {
                $delivery_item_id = $div['item_id'];
                foreach ($deliveryItemDetailList as $d) {
                    if ($d['delivery_item_id'] == $div['item_id']) {
                        $delivery_items[$dik]['order_obj_ids'][] = $d['order_obj_id'];
                    }
                }
                foreach ($delivery_items[$dik]['order_obj_ids'] as $o_obj_id) {
                    if ($orderObjList[$o_obj_id]) {
                        $delivery_items[$dik]['goods_id'] = $orderObjList[$o_obj_id]['goods_id'];
                        break;
                    }
                }

            }
        }

        foreach ($delivery_items as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['number'] += $item['number'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'goods_id'   => $item['goods_id'],
                    'number'     => $item['number'],
                    'bn' => $item['bn'],
                    'is_wms_gift' => $item['is_wms_gift'], //是否京东云交易赠品
                );
            }

        }
        ksort($nitems);
        $theOrder = app::get('ome')->model('orders')->db_dump(['order_id'=>$params['order_id']], 'order_bn');
        $order_bn = $theOrder['order_bn'];
        $batchList = $wmsGiftBatchList = [];
        $freezeBatchList = $freezeWmsGiftBatchList = [];
        
        // product_id
        $productIds = array_column($nitems, 'product_id');
        
        // 获取指定不需要管控库存的基础物料
        $notCtrlStoreBmList = kernel::single('material_basic_material')->getNotCtrlStoreProducts($productIds);
        
        // format
        foreach ($nitems as $key => $dly_item) {
            $product_id = $dly_item['product_id'];
            
            // 是否管控库存
            if(isset($notCtrlStoreBmList[$product_id]) && $notCtrlStoreBmList[$product_id]){
                $dly_item['is_product_ctrl_store'] = false;
            }else{
                $dly_item['is_product_ctrl_store'] = true;
            }
            
            //释放仓库预占流水
            $tmp = [
                'bm_id'     =>  $dly_item['product_id'],
                'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'bill_type' =>  material_basic_material_stock_freeze::__DELIVERY,
                'obj_id'    =>  $params['delivery_id'],
                'branch_id' =>  $params['branch_id'],
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $dly_item['number'],
                'log_type'  =>  '',
                'bm_bn'     =>  $dly_item['bn'],
                'sync_sku'  =>  false,
                'is_product_ctrl_store' => $dly_item['is_product_ctrl_store'], // 是否管控库存
            ];
            
            if($dly_item['is_wms_gift'] == 'true'){
                //临时解决
                $wmsGiftBatchList[] = $tmp;
            } else {
                $batchList[] = $tmp;
            }

            //添加订单货品预占流水
            $freezeData = [];
            $freezeData['bm_id'] = $dly_item['product_id'];
            $freezeData['sm_id'] = $dly_item['goods_id'];
            $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
            $freezeData['bill_type'] = 0;
            $freezeData['obj_id'] = $params['order_id'];
            $freezeData['shop_id'] = $params['shop_id'];
            $freezeData['branch_id'] = 0;
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $dly_item['number'];
            $freezeData['obj_bn'] = $order_bn;
            $freezeData['sync_sku'] = false;
            $freezeData['is_product_ctrl_store'] = $dly_item['is_product_ctrl_store']; // 是否管控库存
            
            if ($dly_item['is_wms_gift'] == 'true') {
                //临时解决
                $freezeWmsGiftBatchList[] = $freezeData;
            } else {
                $freezeBatchList[] = $freezeData;
            }
        }
        
        //释放仓库预占流水
        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($batchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败:'. $err .'!';
            return false;                
        }
        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($wmsGiftBatchList, __CLASS__.'::'.__FUNCTION__, $err);

        //添加订单货品预占流水
        $rs = $this->_basicMStockFreezeLib->freezeBatch($freezeBatchList, __CLASS__.'::'.__FUNCTION__. $err);
        if ($rs == false) {
            $err_msg = '订单货品冻结预占失败:'. $err .'!';
            return false;                
        }
        $rs = $this->_basicMStockFreezeLib->freezeBatch($freezeWmsGiftBatchList, __CLASS__.'::'.__FUNCTION__. $err);

        //发货单取消，电商仓预占流水删除
        $this->_basicMStockFreezeLib->delDeliveryFreeze($params['delivery_id']);

        return true;
    }

    /**
     * 发货单发货节点的库存处理方法
     *
     * @param array $params 传入参数
     * @param string $err_msg 错误信息
     * @return boolean true/false
     */
    public function consignDly($params, &$err_msg)
    {
        $delivery_items = $params['delivery_items'];

        // 获取sm_id
        $itemIdArr = array_column($delivery_items, 'item_id');
        if ($itemIdArr) {
            $itemDetailMdl = app::get('ome')->model('delivery_items_detail');
            $deliveryItemDetailList = $itemDetailMdl->getList('order_obj_id,delivery_item_id', ['delivery_item_id'=>$itemIdArr]);
            $orderObjList = app::get('ome')->model('order_objects')->getList('obj_id,goods_id',['obj_id'=>array_column($deliveryItemDetailList, 'order_obj_id')]);
            $orderObjList = array_column($orderObjList, null, 'obj_id');

            foreach ($delivery_items as $dik => $div) {
                $delivery_item_id = $div['item_id'];
                foreach ($deliveryItemDetailList as $d) {
                    if ($d['delivery_item_id'] == $div['item_id']) {
                        $delivery_items[$dik]['order_obj_ids'][] = $d['order_obj_id'];
                    }
                }
                foreach ($delivery_items[$dik]['order_obj_ids'] as $o_obj_id) {
                    if ($orderObjList[$o_obj_id]) {
                        $delivery_items[$dik]['goods_id'] = $orderObjList[$o_obj_id]['goods_id'];
                        break;
                    }
                }

            }
        }

        $nitems         = array();
        foreach ($delivery_items as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['number'] += $item['number'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'goods_id'   => $item['goods_id'],
                    'number'     => $item['number'],
                    'bn' => $item['bn'],
                    'is_wms_gift' => $item['is_wms_gift'], //是否京东云交易赠品
                );
            }

        }

        ksort($nitems);
        
        // product_id
        $productIds = array_column($nitems, 'product_id');
        
        // 获取指定不需要管控库存的基础物料
        $notCtrlStoreBmList = kernel::single('material_basic_material')->getNotCtrlStoreProducts($productIds);
        
        // format
        $branchBatchList = $branchWmsGiftBatchList = [];
        foreach ($nitems as $key => $dly_item) {
            $product_id = $dly_item['product_id'];
            
            $tmp = [
                'bm_id'     =>  $dly_item['product_id'],
                'sm_id'     =>  $dly_item['goods_id'],
                'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'bill_type' =>  material_basic_material_stock_freeze::__DELIVERY,
                'obj_id'    =>  $params['delivery_id'],
                'branch_id' =>  $params['branch_id'],
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $dly_item['number'],
                'log_type'  =>  '',
                'bm_bn'     =>  $dly_item['bn'],
            ];
            
            // 是否管控库存
            if(isset($notCtrlStoreBmList[$product_id]) && $notCtrlStoreBmList[$product_id]){
                $tmp['is_product_ctrl_store'] = false;
            }else{
                $tmp['is_product_ctrl_store'] = true;
            }
            
            if ($dly_item['is_wms_gift'] == 'true') {
                //临时解决
                $branchWmsGiftBatchList[] = $tmp;
            } else {
                $branchBatchList[] = $tmp;
            }
        }

        //释放仓库预占流水
        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败：'. $err .'!';
            return false;                
        }
        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchWmsGiftBatchList, __CLASS__.'::'.__FUNCTION__, $err);

        //发货完成，电商仓预占流水删除
        $this->_basicMStockFreezeLib->delDeliveryFreeze($params['delivery_id']);

        //电商仓发货，仓库货品实际库存扣减跟着出入库明细生成，所以不在这里处理
        return true;
    }

    /**
     * 订单暂停发货单取消节点的库存处理方法
     *
     * @param array $params 传入参数
     * @param string $err_msg 错误信息
     * @return boolean true/false
     */
    public function pauseOrd($params, &$err_msg)
    {

        $delivery_items = $params['delivery_items'];

        // 获取sm_id
        $itemIdArr = array_column($delivery_items, 'item_id');
        if ($itemIdArr) {
            $itemDetailMdl = app::get('ome')->model('delivery_items_detail');
            $deliveryItemDetailList = $itemDetailMdl->getList('order_obj_id,delivery_item_id', ['delivery_item_id'=>$itemIdArr]);
            $orderObjList = app::get('ome')->model('order_objects')->getList('obj_id,goods_id',['obj_id'=>array_column($deliveryItemDetailList, 'order_obj_id')]);
            $orderObjList = array_column($orderObjList, null, 'obj_id');

            foreach ($delivery_items as $dik => $div) {
                $delivery_item_id = $div['item_id'];
                foreach ($deliveryItemDetailList as $d) {
                    if ($d['delivery_item_id'] == $div['item_id']) {
                        $delivery_items[$dik]['order_obj_ids'][] = $d['order_obj_id'];
                    }
                }
                foreach ($delivery_items[$dik]['order_obj_ids'] as $o_obj_id) {
                    if ($orderObjList[$o_obj_id]) {
                        $delivery_items[$dik]['goods_id'] = $orderObjList[$o_obj_id]['goods_id'];
                        break;
                    }
                }

            }
        }

        $nitems         = array();
        foreach ($delivery_items as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['number'] += $item['number'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'goods_id'   => $item['goods_id']?$item['goods_id']:0,
                    'number'     => $item['number'],
                    'bn' => $item['bn'],
                    'is_wms_gift' => $item['is_wms_gift'], //是否京东云交易赠品
                );
            }

        }

        ksort($nitems);

        $theOrder = app::get('ome')->model('orders')->db_dump(['order_id'=>$params['order_id']], 'order_bn');
        $order_bn = $theOrder['order_bn'];
        $batchList = $wmsGiftBatchList = [];
        $branchBatchList = $branchWmsGiftBatchList = [];
        foreach ($nitems as $key => $dly_item) {
            //释放仓库预占流水
            $tmp = [
                'bm_id'     =>  $dly_item['product_id'],
                'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'bill_type' =>  material_basic_material_stock_freeze::__DELIVERY,
                'obj_id'    =>  $params['delivery_id'],
                'branch_id' =>  $params['branch_id'],
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $dly_item['number'],
                'log_type'  =>  '',
                'bm_bn'     =>  $dly_item['bn'],
                'sync_sku'  =>  false,
            ];
            if($dly_item['is_wms_gift'] == 'true'){
                //临时解决
                $branchWmsGiftBatchList[] = $tmp;
            } else {
                $branchBatchList[] = $tmp;
            }

            //添加订单货品预占流水
            $freezeData = [];
            $freezeData['bm_id'] = $dly_item['product_id'];
            $freezeData['sm_id'] = $dly_item['goods_id'];
            $freezeData['bn'] = $dly_item['bn'];
            $freezeData['obj_type'] = material_basic_material_stock_freeze::__ORDER;
            $freezeData['bill_type'] = 0;
            $freezeData['obj_id'] = $params['order_id'];
            $freezeData['shop_id'] = $params['shop_id'];
            $freezeData['branch_id'] = 0;
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $dly_item['number'];
            $freezeData['obj_bn'] = $order_bn;
            $freezeData['sync_sku'] = false;

            if ($dly_item['is_wms_gift'] == 'true') {
                // 临时解决
                $wmsGiftBatchList[] = $freezeData;
            } else {
                $batchList[] = $freezeData;
            }
        }
        $rs = $this->_basicMStockFreezeLib->freezeBatch($batchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '订单货品冻结预占失败:'. $err .'!';
            return false;
        }
        $rs = $this->_basicMStockFreezeLib->freezeBatch($wmsGiftBatchList, __CLASS__.'::'.__FUNCTION__);

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败:'. $err .'!';
            return false;
        }
        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchWmsGiftBatchList, __CLASS__.'::'.__FUNCTION__, $err);

        //发货完成，电商仓预占流水删除
        $this->_basicMStockFreezeLib->delDeliveryFreeze($params['delivery_id']);

        return true;
    }

    /**
     * 订单恢复节点的库存处理方法
     *
     * @param array $params 传入参数
     * @param string $err_msg 错误信息
     * @return boolean true/false
     */
    public function renewOrd($params, &$err_msg)
    {
        //订单暂停、第三方仓发货单直接走的是撤销，如果订单恢复后不需要相应的货品库存处理逻辑
    }

    public function createChangeReturn($params, &$err_msg)
    {

        $return_id = $params['return_id'];
        $return_bn = $params['return_bn'];
        $branch_id = $params['changebranch_id'];
        $shop_id   = $params['shop_id'];
        $log_type  = 'return';
        // $log_type  = 'negative_stock';

        if (empty($return_id) || empty($branch_id)) {
            $err_msg = '无效操作!';
            return false;
        }

        $items = $params['items'];
        if (empty($items)) {
            $err_msg = '没有换货明细!';
            return false;
        }

        $nitems = array();

        foreach ($items as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'goods_id'   => $item['goods_id'],
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                );
            }

        }
        $tran = kernel::database()->beginTransaction();
        ksort($nitems);


        // 查库存，是否足够预占，如果不足，则不冻到仓，冻结到商品上。
        $bpList = app::get('ome')->model('branch_product')->getList('product_id,store,store_freeze', ['branch_id'=>$branch_id,'product_id|in'=>array_keys($nitems)]);
        if ($bpList) {
            $bpList = array_column($bpList, null, 'product_id');
        } else {
            $bpList = [];
        }

        //增加预占
        $branchBatchList = [];
        foreach ($nitems as $item) {

            $product_id = $item['product_id'];
            $goods_id   = $item['goods_id'];
            $num        = $item['num'];
            $bn         = $item['bn'];

            $freezeData = [];
            $freezeData['bm_id'] = $product_id;
            $freezeData['sm_id'] = $goods_id;
            $freezeData['bn'] = $bn;
            // $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
            $freezeData['bill_type'] = material_basic_material_stock_freeze::__RETURN;
            $freezeData['obj_id'] = $return_id;
            $freezeData['shop_id'] = $shop_id;
            // $freezeData['branch_id'] = $branch_id;
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $num;
            $freezeData['log_type'] = $log_type;
            $freezeData['obj_bn'] = $return_bn;

            $bpInfo = $bpList[$product_id];
            if ($bpInfo && $bpInfo['store']-$bpInfo['store_freeze']>=$num) {
                // 可用库存足够，保持原逻辑
                $freezeData['obj_type']  = material_basic_material_stock_freeze::__BRANCH;
                $freezeData['branch_id'] = $branch_id;
            } else {
                // 可用库存不够，冻结到商品
                $freezeData['obj_type']  = material_basic_material_stock_freeze::__AFTERSALE;
                $freezeData['branch_id'] = 0;
            }

            $branchBatchList[] = $freezeData;
        }

        $rs = $this->_basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            kernel::database()->rollBack();
            $err_msg = '仓库货品冻结预占失败!'.$err;
            return false;
        }
        kernel::database()->commit($tran);
        return true;
    }

    public function deleteChangeReturn($params, &$err_msg)
    {

        $return_id = $params['return_id'];
        $branch_id = $params['branch_id'];
        $log_type  = 'return';

        if (empty($return_id) || empty($branch_id)) {
            $err_msg = '无效操作!';
            return false;
        }

        $items = $params['items'];
        if (empty($items)) {
            $err_msg = '没有换货明细!';
            return false;
        }

        $nitems = array();

        foreach ($items as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'goods_id'   => $item['goods_id'],
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                    'obj_type'   => $item['obj_type'],
                );
            }
        }

        ksort($nitems);
        //释放库存预占
        $branchBatchList = [];
        foreach ($nitems as $item) {

            $product_id = $item['product_id'];
            $goods_id   = $item['goods_id'];
            $num        = $item['num'];
            $bn         = $item['bn'];
            $obj_type   = $item['obj_type'];

            $_branch_id = $branch_id;
            if ($obj_type == material_basic_material_stock_freeze::__AFTERSALE) {
                $_branch_id = 0;
            }

            $branchBatchList[] = [
                'bm_id'     =>  $product_id,
                'sm_id'     =>  $goods_id,
                // 'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                // 换货单审核换出商品库存不足会冻结到商品上（不冻仓）, 所以obj_type也有可能是1
                'obj_type'  =>  $obj_type,
                'bill_type' =>  material_basic_material_stock_freeze::__RETURN,
                'obj_id'    =>  $return_id,
                'branch_id' =>  $_branch_id,
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $num,
                'log_type'  =>  $log_type,
                'bm_bn'     =>  $bn,
            ];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        //删除预占流水
        $this->_basicMStockFreezeLib->delOtherFreeze($return_id, material_basic_material_stock_freeze::__RETURN);

        return true;
    }

    /*
     * 售后：退换货单审核库存处理方法
     * @param array $params 传入参数
     * @param string $err_msg 错误信息
     */
    public function checkChangeReship($params, &$err_msg)
    {

        $reship_id = $params['reship_id'];
        $branch_id = $params['changebranch_id'];
        $shop_id   = $params['shop_id'];
        $log_type  = 'reship';
        // $log_type  = 'negative_stock';

        if (empty($reship_id) || empty($branch_id)) {
            $err_msg = '无效操作!';
            return false;
        }

        $reship_item = $params['reship_item'];
        if (empty($reship_item)) {
            $err_msg = '没有换货明细!';
            return false;
        }

        $tran = kernel::database()->beginTransaction();

        $reship_object = kernel::single('console_reship')->change_objects($reship_id);
        $reship_object = array_column($reship_object, null, 'obj_id');

        $nitems = array();

        foreach ($reship_item as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'goods_id'   => $reship_object[$item['obj_id']]?$reship_object[$item['obj_id']]['product_id']:0,
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                );
            }

        }

        ksort($nitems);
        $theReship = app::get('ome')->model('reship')->db_dump(['reship_id'=>$reship_id], 'reship_bn');
        $reship_bn = $theReship['reship_bn'];

        // 查库存，是否足够预占，如果不足，则不冻到仓，冻结到商品上。
        $bpList = app::get('ome')->model('branch_product')->getList('product_id,store,store_freeze', ['branch_id'=>$branch_id,'product_id|in'=>array_keys($nitems)]);
        if ($bpList) {
            $bpList = array_column($bpList, null, 'product_id');
        } else {
            $bpList = [];
        }

        //增加预占
        $branchBatchList = [];
        foreach ($nitems as $item) {

            $product_id = $item['product_id'];
            $goods_id   = $item['goods_id'];
            $num        = $item['num'];
            $bn         = $item['bn'];

            $freezeData = [];
            $freezeData['bm_id'] = $product_id;
            $freezeData['sm_id'] = $goods_id;
            $freezeData['bn'] = $bn;
            // $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
            $freezeData['bill_type'] = material_basic_material_stock_freeze::__RESHIP;
            $freezeData['obj_id'] = $reship_id;
            $freezeData['shop_id'] = $shop_id;
            // $freezeData['branch_id'] = $branch_id;
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $num;
            $freezeData['log_type'] = $log_type;
            $freezeData['obj_bn'] = $reship_bn;

            $bpInfo = $bpList[$product_id];
            if ($bpInfo && $bpInfo['store']-$bpInfo['store_freeze']>=$num) {
                // 可用库存足够，保持原逻辑
                $freezeData['obj_type']  = material_basic_material_stock_freeze::__BRANCH;
                $freezeData['branch_id'] = $branch_id;
            } else {
                // 可用库存不够，冻结到商品
                $freezeData['obj_type']  = material_basic_material_stock_freeze::__AFTERSALE;
                $freezeData['branch_id'] = 0;
            }

            $branchBatchList[] = $freezeData;
        }

        $rs = $this->_basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            kernel::database()->rollBack();
            $err_msg = '仓库货品冻结预占失败!'.$err;
            return false;
        }
        kernel::database()->commit($tran);
        return true;
    }

    /*
     * 售后：拒绝换货单库存处理方法（质检拒绝质检/wap换货单确认拒绝）
     * @param array $params 传入参数
     * @param string $err_msg 错误信息
     */
    public function refuseChangeReship($params, &$err_msg)
    {

        $reship_id = $params['reship_id'];
        $branch_id = $params['changebranch_id'];
        $log_type  = 'reship';

        if (empty($reship_id) || empty($branch_id)) {
            $err_msg = '无效操作!';
            return false;
        }

        //$reship_item = kernel::single('console_reship')->change_items($reship_id);
        //
        $reship_item = $params['reship_item'];
        if (empty($reship_item)) {
            $err_msg = '没有换货明细!';
            return false;
        }
        $reship_object = kernel::single('console_reship')->change_objects($reship_id);
        $reship_object = array_column($reship_object, null, 'obj_id');

        $nitems = array();

        foreach ($reship_item as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'goods_id'   => $reship_object[$item['obj_id']]?$reship_object[$item['obj_id']]['product_id']:0,
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                    'obj_type'   => $item['obj_type'], 
                );
            }
        }

        ksort($nitems);
        //释放库存预占
        $branchBatchList = [];
        foreach ($nitems as $item) {

            $product_id = $item['product_id'];
            $goods_id   = $item['goods_id'];
            $num        = $item['num'];
            $bn         = $item['bn'];
            $obj_type   = $item['obj_type'];

            $_branch_id = $branch_id;
            if ($obj_type == material_basic_material_stock_freeze::__AFTERSALE) {
                $_branch_id = 0;
            }

            $branchBatchList[] = [
                'bm_id'     =>  $product_id,
                'sm_id'     =>  $goods_id,
                // 'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                // 换货单审核换出商品库存不足会冻结到商品上（不冻仓）, 所以obj_type也有可能是1
                'obj_type'  =>  $obj_type,
                'bill_type' =>  material_basic_material_stock_freeze::__RESHIP,
                'obj_id'    =>  $reship_id,
                'branch_id' =>  $_branch_id,
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $num,
                'log_type'  =>  $log_type,
                'bm_bn'     =>  $bn,
            ];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        //删除预占流水
        $this->_basicMStockFreezeLib->delOtherFreeze($reship_id, material_basic_material_stock_freeze::__RESHIP);

        return true;
    }

    /*
     * 售后：确认退换货单库存处理方法（质检确认收货的退入/wap退换货单确认的退入）
     * @param array $params 传入参数
     * @param string $err_msg 错误信息
     */
    public function confirmReshipReturn($params, &$err_msg)
    {
        //实际库存增加
        return kernel::single('ome_return_process')->do_iostock($params['por_id'], 1, $err_msg);
    }

    /*
     * 售后：确认换货单库存处理方法（质检确认收货的换出/wap换货单确认的换出）
     * @param array $params 传入参数
     * @param string $err_msg 错误信息
     */
    public function confirmReshipChange($params, &$err_msg)
    {

        $reship_id = $params['reship_id'];
        $branch_id = $params['changebranch_id'];
        $log_type  = 'reship';

        if (empty($reship_id) || empty($branch_id)) {
            $err_msg = '无效操作!';
            return false;
        }

        $reship_item = kernel::single('console_reship')->change_items($reship_id);
        if (empty($reship_item)) {
            $err_msg = '没有换货明细!';
            return false;
        }
        $reship_object = kernel::single('console_reship')->change_objects($reship_id);
        $reship_object = array_column($reship_object, null, 'obj_id');

        $nitems = array();

        foreach ($reship_item as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'goods_id'   => $reship_object[$item['obj_id']]?$reship_object[$item['obj_id']]['product_id']:0,
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                );
            }

        }

        ksort($nitems);

        $old = $this->_basicMStockFreezeLib->getStockFreezeByObj($reship_id, '', material_basic_material_stock_freeze::__RESHIP);
        $old = array_column((array)$old, null, 'bm_id');

        //释放库存预占
        $branchBatchList = [];
        foreach ($nitems as $item) {
            $product_id = $item['product_id'];
            $goods_id   = $item['goods_id'];
            $num        = $item['num'];
            $bn         = $item['bn'];

            // 换货单审核换出商品库存不足会冻结到商品上（不冻仓）, 所以obj_type也有可能是1
            $obj_type  = material_basic_material_stock_freeze::__BRANCH;
            $_branch_id = $branch_id;
            if ($old[$product_id]['obj_type'] == material_basic_material_stock_freeze::__AFTERSALE) {
                $obj_type = material_basic_material_stock_freeze::__AFTERSALE;
                $_branch_id = 0;
            }
            $branchBatchList[] = [
                'bm_id'     =>  $product_id,
                'sm_id'     =>  $goods_id,
                // 'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'obj_type'  =>  $obj_type,
                'bill_type' =>  material_basic_material_stock_freeze::__RESHIP,
                'obj_id'    =>  $reship_id,
                'branch_id' =>  $_branch_id,
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $num,
                'log_type'  =>  $log_type,
                'bm_bn'     =>  $bn,
            ];

        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        //删除预占流水
        $this->_basicMStockFreezeLib->delOtherFreeze($reship_id, material_basic_material_stock_freeze::__RESHIP);

        return true;
    }

    /*
     * 退换货单回传拒绝换货库存处理方法
     * @param array $params 传入参数
     * @param string $err_msg 错误信息
     */
    public function reshipReturnRefuseChange($params, &$err_msg)
    {

        $reship_id = $params['reship_id'];
        $branch_id = $params['changebranch_id'];
        $log_type  = 'reship';

        if (empty($reship_id) || empty($branch_id)) {
            $err_msg = '无效操作!';
            return false;
        }

        $reship_item = kernel::single('console_reship')->change_items($reship_id);
        if (empty($reship_item)) {
            $err_msg = '没有换货明细!';
            return false;
        }
        $reship_object = kernel::single('console_reship')->change_objects($reship_id);
        $reship_object = array_column($reship_object, null, 'obj_id');

        $nitems = array();

        foreach ($reship_item as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'goods_id'   => $reship_object[$item['obj_id']]?$reship_object[$item['obj_id']]['product_id']:0,
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                );
            }

        }

        ksort($nitems);

        $old = $this->_basicMStockFreezeLib->getStockFreezeByObj($reship_id, '', material_basic_material_stock_freeze::__RESHIP);
        $old = array_column((array)$old, null, 'bm_id');
        //释放库存预占
        $branchBatchList = [];
        foreach ($nitems as $item) {
            $product_id = $item['product_id'];
            $goods_id   = $item['goods_id'];
            $num        = $item['num'];
            $bn         = $item['bn'];

            // 换货单审核换出商品库存不足会冻结到商品上（不冻仓）, 所以obj_type也有可能是1
            $obj_type  = material_basic_material_stock_freeze::__BRANCH;
            $_branch_id = $branch_id;
            if ($old[$product_id]['obj_type'] == material_basic_material_stock_freeze::__AFTERSALE) {
                $obj_type = material_basic_material_stock_freeze::__AFTERSALE;
                $_branch_id = 0;
            }

            $branchBatchList[] = [
                'bm_id'     =>  $product_id,
                'sm_id'     =>  $goods_id,
                // 'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'obj_type'  =>  $obj_type,
                'bill_type' =>  material_basic_material_stock_freeze::__RESHIP,
                'obj_id'    =>  $reship_id,
                'branch_id' =>  $_branch_id,
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $num,
                'log_type'  =>  $log_type,
                'bm_bn'     =>  $bn,
            ];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        //删除预占流水
        $this->_basicMStockFreezeLib->delOtherFreeze($reship_id, material_basic_material_stock_freeze::__RESHIP);

        return true;
    }

    /*
     * 最终收货确认由换货变为退货库存处理方法
     * @param array $params 传入参数
     * @param string $err_msg 错误信息
     */
    public function editChangeToReturn($params, &$err_msg)
    {

        $reship_id = $params['reship_id'];
        $branch_id = $params['changebranch_id'];
        $log_type  = 'reship';

        if (empty($reship_id) || empty($branch_id)) {
            $err_msg = '无效操作!';
            return false;
        }

        $reship_item = kernel::single('console_reship')->change_items($reship_id);
        if (empty($reship_item)) {
            $err_msg = '没有换货明细!';
            return false;
        }
        $reship_object = kernel::single('console_reship')->change_objects($reship_id);
        $reship_object = array_column($reship_object, null, 'obj_id');
        $nitems = array();

        foreach ($reship_item as $item) {

            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'goods_id'   => $reship_object[$item['obj_id']]?$reship_object[$item['obj_id']]['product_id']:0,
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                );

            }

        }

        ksort($nitems);

        $old = $this->_basicMStockFreezeLib->getStockFreezeByObj($reship_id, '', material_basic_material_stock_freeze::__RESHIP);
        $old = array_column((array)$old, null, 'bm_id');

        //释放库存预占
        $branchBatchList = [];
        foreach ($nitems as $item) {

            $product_id = $item['product_id'];
            $goods_id   = $item['goods_id'];
            $num        = $item['num'];
            $bn         = $item['bn'];

            // 换货单审核换出商品库存不足会冻结到商品上（不冻仓）, 所以obj_type也有可能是1
            $obj_type   = material_basic_material_stock_freeze::__BRANCH;
            $_branch_id = $branch_id;
            if ($old[$product_id]['obj_type'] == material_basic_material_stock_freeze::__AFTERSALE) {
                $obj_type = material_basic_material_stock_freeze::__AFTERSALE;
                $_branch_id = 0;
            }

            $branchBatchList[] = [
                'bm_id'     =>  $product_id,
                'sm_id'     =>  $goods_id,
                // 'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'obj_type'  =>  $obj_type,
                'bill_type' =>  material_basic_material_stock_freeze::__RESHIP,
                'obj_id'    =>  $reship_id,
                'branch_id' =>  $_branch_id,
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $num,
                'log_type'  =>  $log_type,
                'bm_bn'     =>  $bn,
            ];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        //删除预占流水
        $this->_basicMStockFreezeLib->delOtherFreeze($reship_id, material_basic_material_stock_freeze::__RESHIP);

        return true;
    }

    /**
     * 审核采购退货
     */
    public function checkReturned($params, &$err_msg)
    {
        $rp_id     = $params['rp_id'];
        $branch_id = $params['branch_id'];
        $log_type  = 'return_purchase';

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }

        $nitems = array();

        foreach ($params['items'] as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                );
            }

        }

        ksort($nitems);
        $returnObj    = app::get('purchase')->model('returned_purchase');
        $returnInfo   = $returnObj->dump(array('rp_id'=>$rp_id), 'rp_bn');
        $obj_bn       = $returnInfo['rp_bn'];
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $freezeData = [];
            $freezeData['bm_id'] = $item['product_id'];
            $freezeData['sm_id'] = 0; // 采购退货单不涉及到销售物料
            $freezeData['bn'] = $item['bn'];
            $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
            $freezeData['bill_type'] = material_basic_material_stock_freeze::__RETURNED;
            $freezeData['obj_id'] = $rp_id;
            $freezeData['shop_id'] = '';
            $freezeData['branch_id'] = $branch_id;
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $item['num'];
            $freezeData['log_type'] = $log_type;
            $freezeData['obj_bn'] = $obj_bn;

            $branchBatchList[] = $freezeData;
        }

        $rs = $this->_basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结预占失败!'.$err;
            return false;
        }

        return true;
    }

    /**
     * 完成采购退货
     */
    public function finishReturned($params, &$err_msg)
    {
        $rp_id      = $params['rp_id'];
        $branch_id  = $params['branch_id'];
        $log_type   = 'return_purchase';
        $product_id = $params['product_id'];
        $num        = $params['num'];
        $bn         = $params['bn'];

        $branchBatchList = [];
        $branchBatchList[] = [
            'bm_id'     =>  $product_id,
            'sm_id'     =>  0, // 采购退货单不涉及到销售物料
            'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
            'bill_type' =>  material_basic_material_stock_freeze::__RETURNED,
            'obj_id'    =>  $rp_id,
            'branch_id' =>  $branch_id,
            'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
            'num'       =>  $num,
            'log_type'  =>  $log_type,
            'bm_bn'     =>  $bn,
        ];
        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        return true;
    }

    /**
     * 取消采购退货
     */
    public function cancelReturned($params, &$err_msg)
    {
        $rp_id     = $params['rp_id'];
        $branch_id = $params['branch_id'];
        $log_type  = 'return_purchase';

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $nitems = array();

        foreach ($params['items'] as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                );
            }

        }

        ksort($nitems);
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $branchBatchList[] = [
                'bm_id'     =>  $item['product_id'],
                'sm_id'     =>  0, // 采购退货单不涉及到销售物料
                'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'bill_type' =>  material_basic_material_stock_freeze::__RETURNED,
                'obj_id'    =>  $rp_id,
                'branch_id' =>  $branch_id,
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $item['num'],
                'log_type'  =>  $log_type,
                'bm_bn'     =>  $item['bn'],
            ];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        //删除预占流水
        $this->_basicMStockFreezeLib->delOtherFreeze($rp_id, material_basic_material_stock_freeze::__RETURNED);

        return true;
    }

    /**
     * 审核调拨出库单
     */
    public function checkStockout($params, &$err_msg)
    {
        $iso_id    = $params['iso_id'];
        $branch_id = $params['branch_id'];
        $log_type  = 'other';

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $nitems = array();

        foreach ($params['items'] as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['nums'] += $item['nums'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'nums'       => $item['nums'],
                    'bn'         => $item['bn'],
                );
            }

        }

        ksort($nitems);
        $isoObj    = app::get('taoguaniostockorder')->model("iso");
        $isoInfo   = $isoObj->dump(array('iso_id'=>$iso_id), 'iso_bn');
        $obj_bn    = $isoInfo['iso_bn'];
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $freezeData = [];
            $freezeData['bm_id'] = $item['product_id'];
            $freezeData['sm_id'] = 0; // 调拨单不涉及到销售物料
            $freezeData['bn'] = $item['bn'];
            $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
            $freezeData['bill_type'] = material_basic_material_stock_freeze::__STOCKOUT;
            $freezeData['obj_id'] = $iso_id;
            $freezeData['shop_id'] = '';
            $freezeData['branch_id'] = $branch_id;
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $item['nums'];
            $freezeData['log_type'] = $log_type;
            $freezeData['obj_bn'] = $obj_bn;

            $branchBatchList[] = $freezeData;
        }

        $rs = $this->_basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结预占失败!'.$err;
            return false;
        }

        return true;
    }

    /**
     * 最终处理调拨出库单
     */
    public function finishStockout($params, &$err_msg)
    {
        $iso_id     = $params['iso_id'];
        $branch_id  = $params['branch_id'];
        $log_type   = 'other';
        $product_id = $params['product_id'];
        $num        = $params['num'];
        $bn         = $params['bn'];

        $branchBatchList = [];
        $branchBatchList[] = [
            'bm_id'     =>  $product_id,
            'sm_id'     =>  0, // 调拨单不涉及到销售物料
            'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
            'bill_type' =>  material_basic_material_stock_freeze::__STOCKOUT,
            'obj_id'    =>  $iso_id,
            'branch_id' =>  $branch_id,
            'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
            'num'       =>  $num,
            'log_type'  =>  $log_type,
            'bm_bn'     =>  $bn,
        ];
        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        return true;
    }

    /**
    /**
     * 确定加工单
     */
    public function confirmMaterialPackage($params, &$err_msg)
    {
        $main_id = $params['main']['id'];
        $branch_id = $params['main']['branch_id'];
        $obj_bn    = $params['main']['mp_bn'];
        $sub_bill_type = '';

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $nitems = array();
        foreach ($params['items'] as $item) {
            if (isset($nitems[$item['bm_id']])) {
                $nitems[$item['bm_id']]['nums'] += abs($item['number']);
            } else {
                $nitems[$item['bm_id']] = array(
                    'product_id' => $item['bm_id'],
                    'bn' => $item['bm_bn'],
                    'nums' => abs($item['number']),
                );
            }

        }

        ksort($nitems);
        $return = true;
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $freezeData = [];
            $freezeData['bm_id'] = $item['product_id'];
            $freezeData['sm_id'] = 0; // 加工单不涉及到销售物料
            $freezeData['bn'] = $item['bn'];
            $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
            $freezeData['bill_type'] = material_basic_material_stock_freeze::__MATERIALPACKAGEOUT;
            $freezeData['obj_id'] = $main_id;
            $freezeData['shop_id'] = '';
            $freezeData['branch_id'] = $branch_id;
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $item['nums'];
            $freezeData['log_type'] = 'main';
            $freezeData['obj_bn'] = $obj_bn;
            $freezeData['sub_bill_type'] = $sub_bill_type;

            $branchBatchList[] = $freezeData;
        }
        // 原始逻辑是累加返回所有失败bn
        $rs = $this->_basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg .= ':仓库货品冻结预占失败!'.$err;
            return false;
            // continue;
        }

        return $return;
    }

    #完成加工单
    public function finishMaterialPackage($params, &$err_msg)
    {
        $main_id = $params['main']['id'];
        $branch_id = $params['main']['branch_id'];

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $nitems = array();
        foreach ($params['items'] as $item) {
            if (isset($nitems[$item['bm_id']])) {
                $nitems[$item['bm_id']]['nums'] += abs($item['number']);
            } else {
                $nitems[$item['bm_id']] = array(
                    'product_id' => $item['bm_id'],
                    'bn' => $item['bm_bn'],
                    'nums' => abs($item['number']),
                );
            }

        }

        ksort($nitems);
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $branchBatchList[] = [
                'bm_id'     =>  $item['product_id'],
                'sm_id'     =>  0, // 加工单不涉及到销售物料
                'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'bill_type' =>  material_basic_material_stock_freeze::__MATERIALPACKAGEOUT,
                'obj_id'    =>  $main_id,
                'branch_id' =>  $branch_id,
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $item['nums'],
                'log_type'  =>  'material_package',
                'bm_bn'     =>  $item['bn'],
            ];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }
        $this->_basicMStockFreezeLib->delOtherFreeze($main_id, material_basic_material_stock_freeze::__MATERIALPACKAGEOUT);

        return true;
    }

    #取消加工单
    public function cancelMaterialPackage($params, &$err_msg)
    {
        $main_id    = $params['main']['id'];
        $branch_id  = $params['main']['branch_id'];

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $nitems = array();
        foreach ($params['items'] as $item) {
            if (isset($nitems[$item['bm_id']])) {
                $nitems[$item['bm_id']]['nums'] += abs($item['number']);
            } else {
                $nitems[$item['bm_id']] = array(
                    'product_id' => $item['bm_id'],
                    'bn' => $item['bm_bn'],
                    'nums' => abs($item['number']),
                );
            }
        }

        ksort($nitems);
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {
            $batchList['-'][] = [
                'bm_id' =>  $item['product_id'],
                'num'   =>  $item['nums'],
            ];

            $branchBatchList[] = [
                'bm_id'     =>  $item['product_id'],
                'sm_id'     =>  0, // 加工单不涉及到销售物料
                'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'bill_type' =>  material_basic_material_stock_freeze::__MATERIALPACKAGEOUT,
                'obj_id'    =>  $main_id,
                'branch_id' =>  $branch_id,
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $item['nums'],
                'log_type'  =>  'material_package',
                'bm_bn'     =>  $item['bn'],
            ];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        $this->_basicMStockFreezeLib->delOtherFreeze($main_id, material_basic_material_stock_freeze::__MATERIALPACKAGEOUT);

        return true;
    }

    public function cmp_by_bm_id($a, $b) {
        if($a['bm_id'] == $b['bm_id']) {
            return 0;
        }
        return $a['bm_id'] < $b['bm_id'] ? -1 : 1;
    }
    /**
     * 生成差异单
     */
    public function addDifference($params, &$err_msg)
    {
        $difference_id = $params['difference']['id'];
        $branch_id = $params['difference']['branch_id'];
        $obj_bn    = $params['difference']['diff_bn'];
        $sub_bill_type = '';

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $nitems = $params['items'];
        uasort($nitems, [$this, 'cmp_by_bm_id']);
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $freezeData = [];
            $freezeData['bm_id'] = $item['bm_id'];
            $freezeData['sm_id'] = 0; // 差异单不涉及到销售物料
            $freezeData['bn'] = $item['material_bn'];
            $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
            $freezeData['bill_type'] = material_basic_material_stock_freeze::__DIFFERENCEOUT;
            $freezeData['obj_id'] = $difference_id;
            $freezeData['shop_id'] = '';
            $freezeData['branch_id'] = $item['branch_id'];
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $item['freeze_num'];
            $freezeData['log_type'] = 'difference';
            $freezeData['obj_bn'] = $obj_bn;
            $freezeData['sub_bill_type'] = $sub_bill_type;

            $branchBatchList[] = $freezeData;
        }

        $rs = $this->_basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结预占失败!'.$err;
            return false;
        }

        return true;
    }
    /**
     * 确定差异单
     */
    public function confirmDifference($params, &$err_msg)
    {
        $difference_id = $params['diff_id'];
        $branch_id = $params['branch_id'];

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $nitems = $params['items'];
        uasort($nitems, [$this, 'cmp_by_bm_id']);
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $branchBatchList[] = [
                'bm_id'     =>  $item['bm_id'],
                'sm_id'     =>  0, // 差异单不涉及到销售物料
                'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'bill_type' =>  material_basic_material_stock_freeze::__DIFFERENCEOUT,
                'obj_id'    =>  $difference_id,
                'branch_id' =>  $item['branch_id'],
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $item['freeze_num'],
                'log_type'  =>  'difference',
                'bm_bn'     =>  $item['material_bn'],
            ];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        return true;
    }
    /**
     * 取消差异单
     */
    public function cancelDifference($params, &$err_msg)
    {
        $difference_id = $params['id'];
        $branch_id = $params['branch_id'];

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $nitems = $params['items'];
        uasort($nitems, [$this, 'cmp_by_bm_id']);
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $branchBatchList[] = [
                'bm_id'     =>  $item['bm_id'],
                'sm_id'     =>  0, // 差异单不涉及到销售物料
                'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'bill_type' =>  material_basic_material_stock_freeze::__DIFFERENCEOUT,
                'obj_id'    =>  $difference_id,
                'branch_id' =>  $item['branch_id'],
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $item['freeze_num'],
                'log_type'  =>  'difference',
                'bm_bn'     =>  $item['material_bn'],
            ];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }
        $this->_basicMStockFreezeLib->delOtherFreeze($difference_id, material_basic_material_stock_freeze::__DIFFERENCEOUT);

        return true;
    }

    /**
     * 审核库内转储单
     */
    public function saveStockdump($params, &$err_msg)
    {
        $stockdump_id = $params['stockdump_id'];
        $branch_id    = $params['branch_id'];
        $log_type     = 'stockdump';

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $nitems = array();

        foreach ($params['items'] as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                );
            }

        }

        ksort($nitems);

        $oAppro    = app::get('console')->model('stockdump');
        $tempInfo  = $oAppro->dump(array('stockdump_id'=>$stockdump_id), 'stockdump_bn');
        $obj_bn    = $tempInfo['stockdump_bn'];
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $freezeData = [];
            $freezeData['bm_id'] = $item['product_id'];
            $freezeData['sm_id'] = 0; // 库内转储单不涉及到销售物料
            $freezeData['bn'] = $item['bn'];
            $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
            $freezeData['bill_type'] = material_basic_material_stock_freeze::__STOCKDUMP;
            $freezeData['obj_id'] = $stockdump_id;
            $freezeData['shop_id'] = '';
            $freezeData['branch_id'] = $branch_id;
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $item['num'];
            $freezeData['log_type'] = $log_type;
            $freezeData['obj_bn'] = $obj_bn;

            $branchBatchList[] = $freezeData;
        }

        $rs = $this->_basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结预占失败!'.$err;
            return false;
        }

        return true;
    }

    /**
     * 最终处理库内转储单
     */
    public function finishStockdump($params, &$err_msg)
    {
        $stockdump_id = $params['stockdump_id'];
        $branch_id    = $params['branch_id'];
        $log_type     = 'stockdump';

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $nitems = array();

        foreach ($params['items'] as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                );
            }

        }

        ksort($nitems);
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $branchBatchList[] = [
                'bm_id'     =>  $item['product_id'],
                'sm_id'     =>  0, // 库内转储单不涉及到销售物料
                'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'bill_type' =>  material_basic_material_stock_freeze::__STOCKDUMP,
                'obj_id'    =>  $stockdump_id,
                'branch_id' =>  $branch_id,
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $item['num'],
                'log_type'  =>  $log_type,
                'bm_bn'     =>  $item['bn'],
            ];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        //删除预占流水
        $this->_basicMStockFreezeLib->delOtherFreeze($stockdump_id, material_basic_material_stock_freeze::__STOCKDUMP);

        return true;
    }

    /**
     * 审核唯品会出库单
     */
    public function checkVopstockout($params, &$err_msg)
    {
        $stockout_id = $params['stockout_id'];
        $branch_id   = $params['branch_id'];
        $log_type    = 'vop';

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $nitems = array();

        foreach ($params['items'] as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'num'        => $item['num'],
                    'bn'         => $item['bn'],
                );
            }

        }

        ksort($nitems);
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $tempInfo       = $stockoutObj->dump(array('stockout_id'=>$stockout_id), 'stockout_no');
        $obj_bn         = $tempInfo['stockout_no'];
        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $freezeData = [];
            $freezeData['bm_id'] = $item['product_id'];
            $freezeData['sm_id'] = 0; //唯品会拣货单明细是用barcode映射bm_id，所以没有sm_id
            $freezeData['bn'] = $item['bn'];
            $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
            $freezeData['bill_type'] = material_basic_material_stock_freeze::__VOPSTOCKOUT;
            $freezeData['obj_id'] = $stockout_id;
            $freezeData['shop_id'] = '';
            $freezeData['branch_id'] = $branch_id;
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $item['num'];
            $freezeData['log_type'] = $log_type;
            $freezeData['obj_bn'] = $obj_bn;

            $branchBatchList[] = $freezeData;
        }

        $rs = $this->_basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结预占失败!'.$err;
            return false;
        }

        return true;
    }

    /**
     * 最终处理唯品会出库单
     */
    public function finishVopstockout($params, &$err_msg)
    {
        $stockout_id = $params['stockout_id'];
        $branch_id   = $params['branch_id'];
        $log_type    = 'vop';

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '扣减库存缺少仓库ID或明细';
            return false;
        }

        $nitems = array();
        foreach ($params['items'] as $item) {
            if (isset($nitems[$item['product_id']])) {
                $nitems[$item['product_id']]['num'] += $item['num'];
            } else {
                $nitems[$item['product_id']] = array(
                    'product_id' => $item['product_id'],
                    'num'        => $item['num'],
                    'material_bn' => $item['bn'],
                );
            }

        }
        
        ksort($nitems);

        $branchBatchList = [];
        foreach ($nitems as $key => $item) {

            $branchBatchList[] = [
                'bm_id'     =>  $item['product_id'],
                'sm_id'     =>  0, //唯品会拣货单明细是用barcode映射bm_id，所以没有sm_id
                'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'bill_type' =>  material_basic_material_stock_freeze::__VOPSTOCKOUT,
                'obj_id'    =>  $stockout_id,
                'branch_id' =>  $branch_id,
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $item['num'],
                'log_type'  =>  'vop',
                'bm_bn'     =>  $item['material_bn'],
            ];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }

        return true;
    }

    //人工库存预占
    public function artificialFreeze($params, &$err_msg)
    {
        $log_type = "artificialfreeze";
        $arrObjBn = [];
        uasort($params, [$this, 'cmp_by_bm_id']);
        $branchBatchList = [];
        foreach ($params as $var_p) {

            if(!$arrObjBn[$var_p["obj_id"]]) {
                $artFreezeObj = app::get('material')->model('basic_material_stock_artificial_freeze');
                $artFreezeInfo = $artFreezeObj->dump(array('bmsaf_id'=>$var_p["obj_id"]), 'bmsaf_id,original_bn');
                
                $arrObjBn[$var_p["obj_id"]] = ($artFreezeInfo['original_bn'] ? $artFreezeInfo['original_bn'] : $var_p["obj_id"]);
            }

            $freezeData = [];
            $freezeData['bm_id'] = $var_p["bm_id"];
            $freezeData['sm_id'] = 0; // 人工库存预占不涉及到销售物料
            $freezeData['bn'] = $var_p["bn"];
            $freezeData['obj_type'] = material_basic_material_stock_freeze::__BRANCH;
            $freezeData['bill_type'] = material_basic_material_stock_freeze::__ARTIFICIALFREEZE;
            $freezeData['obj_id'] = $var_p["obj_id"];
            $freezeData['shop_id'] = isset($var_p['shop_id']) ? $var_p['shop_id'] : '';
            $freezeData['branch_id'] = $var_p["branch_id"];
            $freezeData['bmsq_id'] = material_basic_material_stock_freeze::__SHARE_STORE;
            $freezeData['num'] = $var_p["freeze_num"];
            $freezeData['log_type'] = $log_type;
            $freezeData['obj_bn'] = $arrObjBn[$var_p["obj_id"]];

            $branchBatchList[] = $freezeData;
        }

        $rs = $this->_basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结预占失败!'.$err;
            return false;
        }
        return true;
    }

    //人工库存预占释放
    public function artificialUnfreeze($params, &$err_msg)
    {
        $log_type = "artificialunfreeze";
        $obj_ids  = array();
        uasort($params, [$this, 'cmp_by_bm_id']);
        $branchBatchList = [];
        foreach ($params as $var_p) {
            //@todo： sync_sku 只有传了not_unfreeze标识为true时,才不用释放冻结库存
            $branchBatchList[] = [
                'bm_id'     =>  $var_p["bm_id"],
                'sm_id'     =>  0, // 人工库存预占不涉及到销售物料
                'obj_type'  =>  material_basic_material_stock_freeze::__BRANCH,
                'bill_type' =>  material_basic_material_stock_freeze::__ARTIFICIALFREEZE,
                'obj_id'    =>  $var_p["bmsaf_id"],
                'branch_id' =>  $var_p["branch_id"],
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $var_p["freeze_num"],
                'log_type'  =>  $log_type,
                'bm_bn'     =>  $var_p['bn'],
                'sync_sku'  =>  ($var_p['not_unfreeze'] !== true) ? true : false,
            ];
            $obj_ids[] = $var_p["bmsaf_id"];
        }

        $rs = $this->_basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        if ($rs == false) {
            $err_msg = '仓库货品冻结释放失败!'.$err;
            return false;
        }
        //删除预占流水
        $this->_basicMStockFreezeLib->delArtificialFreeze($obj_ids);
        return true;
    }

    /**
     * 更新仓库库存
     *
     * @param array $params (branch_id="仓库ID" product_id="商品ID" nums="100" #数量 operator="+"#增减操作 update_material=true #是否更新物料)
     * @return boolean
     * @author
     **/
    public function changeStore($params, &$err_msg)
    {
        // 废弃，redis高可用，改用changeStoreBatch方法
        return false;
        return false;
        return false;

        $branchObj = app::get('ome')->model('branch_product');
        if (!$branchObj->count(array('branch_id' => $params['branch_id'], 'product_id' => $params['product_id']))) {
            $branch_arr                  = array();

            $stores = $this->getStoreByBranchId($params['branch_id']);
            $branch_arr['branch_id']     = $params['branch_id'];
            $branch_arr['product_id']    = $params['product_id'];
            $branch_arr['store_id']    = $stores['store_id'];
            $branch_arr['store_bn']    = $stores['store_bn'];
           
            $branch_arr['store']         = 0;
            $branch_arr['last_modified'] = time();
            $branchObj->insert($branch_arr);
        }

        $libBranchProduct = kernel::single('ome_branch_product');

        $res = $libBranchProduct->change_store($params['branch_id'], $params['product_id'], $params['nums'], $params['operator'], $params['update_material'],$params['negative_stock']);
        return $res;
    }


    /**
     * 更新仓库库存
     *
     * @param array $params (branch_id="仓库ID" product_id="商品ID" nums="100" #数量 operator="+"#增减操作 update_material=true #是否更新物料)
     * redis库存高可用 迭代掉本类changeStore方法
     * @return boolean
     * @author
     **/
    public function changeStoreBatch($params, &$err_msg)
    {
        $branchObj = app::get('ome')->model('branch_product');
        
        // product_id
        $productIds = array_column($params['items'], 'product_id');
        
        // 获取指定不需要管控库存的基础物料
        $notCtrlStoreBmList = kernel::single('material_basic_material')->getNotCtrlStoreProducts($productIds);
        
        // items
        $items = [];
        foreach ($params['items'] as $item) {
            $product_id = $item['product_id'];
            
            // 初始化，添加仓库基础物料
            if (!$branchObj->count(array('branch_id' => $item['branch_id'], 'product_id' => $item['product_id']))) {
                $branch_arr                  = array();

                $stores = $this->getStoreByBranchId($item['branch_id']);
                $branch_arr['branch_id']     = $item['branch_id'];
                $branch_arr['product_id']    = $item['product_id'];
                $branch_arr['store_id']    = $stores['store_id'];
                $branch_arr['store_bn']    = $stores['store_bn'];
               
                $branch_arr['store']         = 0;
                $branch_arr['last_modified'] = time();
                $branchObj->insert($branch_arr);
            }
            
            // check
            if ($item['nums'] == 0) {
                continue;
            }
            
            // 是否管控库存
            if(isset($notCtrlStoreBmList[$product_id]) && $notCtrlStoreBmList[$product_id]){
                continue;
            }
            
            $items[] = [
                'branch_id'     =>  $item['branch_id'],
                'product_id'    =>  $item['product_id'],
                'quantity'      =>  $item['nums'],
                'bn'            =>  $item['bn'],
                'iostock_bn'    =>  $item['iostock_bn'],
                'negative_stock' =>  $item['negative_stock']?true:false,
            ];
        }
        
        // check
        if(empty($items)){
            return true;
        }
        
        $rs  = ome_branch_product::storeInRedis($items, $params['operator'], __CLASS__.'::'.__FUNCTION__);

        $res     = $rs[0];
        $err_msg = $rs[1];
        return $res;
    }

    /**
     *  获取可用库存
     *  @param  branch_id="仓库id" product_id="商品id"
     */
    public function getAvailableStore($params, &$err_msg)
    {
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        $bpModel              = app::get('ome')->model('branch_product');

        $branch = $bpModel->getList('store, store_freeze', array('product_id' => $params['product_id'], 'branch_id' => $params['branch_id']), 0, 1);

        //根据仓库ID、基础物料ID获取该物料仓库级的预占
        //$branch[0]['store_freeze'] = $basicMStockFreezeLib->getBranchFreeze($product_id, $branch_id);

        return $branch[0]['store'] - $branch[0]['store_freeze'];
    }

    # 在途库存
    public function changeArriveStore($params, &$err_msg)
    {
        $obj_id = $params['obj_id'];
        $obj_type = $params['obj_type'];
        $branch_id = $params['branch_id'];
        $operator = $params['operator'];

        if (empty($branch_id) || empty($params['items'])) {
            $err_msg = '无效操作!';
            return false;
        }
        $libBranchProduct = kernel::single('ome_branch_product');
        $mdlBMSA = app::get('material')->model('basic_material_stock_arrive');
        $nitems = $this->_sortAddBmNum($params['items'], 'product_id', 'num');
        foreach ($nitems as $item) {
            $libBranchProduct->change_arrive_store($branch_id, $item['product_id'], $item['num'], $operator);
            $mdlBMSA->addRecord($obj_type, $obj_id, $branch_id, $item['product_id'], $item['num'], $operator);
        }

        return true;
    }

    #释放在途
    public function deleteArriveStore($params, &$err_msg)
    {
        $obj_id = $params['obj_id'];
        $obj_type = $params['obj_type'];
        $branch_id = $params['branch_id'];
        $libBranchProduct = kernel::single('ome_branch_product');
        $mdlBMSA = app::get('material')->model('basic_material_stock_arrive');
        $items = $mdlBMSA->getList('bm_id,num', ['obj_id'=>$obj_id,'obj_type'=>$obj_type]);
        if(empty($items)) {
            return false;
        }
        $nitems = $this->_sortAddBmNum($items, 'bm_id', 'num');
        foreach ($nitems as $item) {
            if($item['num'] > 0) {
                $libBranchProduct->change_arrive_store($branch_id, $item['bm_id'], $item['num'], '-');
            }
        }
        $mdlBMSA->delete(['obj_id'=>$obj_id,'obj_type'=>$obj_type]);
        return true;
    }

    /**
     *  获取库存
     *  @param  branch_id="仓库id" product_id="商品id"
     */
    public function getStoreByBranch($params, &$err_msg)
    {
        if ($params['from_mysql'] == 'true') {
            $bpModel = app::get('ome')->model('branch_product');

            $branch = $bpModel->dump(array('product_id' => $params['product_id'], 'branch_id' => $params['branch_id']), 'store');

            return $branch['store'];
        }

        $store = ome_branch_product::storeFromRedis($params);

        if (!$store[0]) {
            $err_msg = $store[1];
            return false;
        }

        return $store[2]['store'];

    }

    public function getStoreByBranchId($branch_id){


        $storeMdl = app::get('o2o')->model('store');

        $branchs = $storeMdl->db->selectrow("SELECT store_id FROM sdb_ome_branch WHERE branch_id=".$branch_id."");
        $store_id = $branchs['store_id'];
        if($store_id){
            $stores = $storeMdl->db_dump(array('store_id'=>$store_id),'store_bn,store_id');
        }else{
            $stores = $storeMdl->db_dump(array('branch_id'=>$branch_id),'store_bn,store_id');
        }
        
        return $stores;
    }
}
