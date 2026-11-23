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
 * 翱象系统发货单Lib类
 * 
 * @author wangbiao@shopex.cn
 * @version 2023.03.04
 */
class dchain_delivery extends dchain_abstract
{
    /**
     * 自动同步翱象发货单队列任务
     * @param $deliveryIds
     * @param $error_msg
     * @return void
     */
    public function addAoxiangDelivery(&$cursor_id, $params, &$error_msg=null)
    {
        $aoDeliveryObj = app::get('dchain')->model('aoxiang_delivery');
        $deliveryObj = app::get('ome')->model('delivery');

        //data
        $sdfdata = $params['sdfdata'];
        $delivery_id = $sdfdata['delivery_id'];
        $process_status = $sdfdata['process_status'];

        //check
        if(empty($delivery_id)){
            //$error_msg = '没有发货单信息数据!';
            return false;
        }

        //delivery
        $deliveryList = $deliveryObj->getList('*', array('delivery_id'=>$delivery_id));
        $delivery = $deliveryList[0];
        if(empty($delivery)){
            //$error_msg = '发货单信息不存在!';
            return false;
        }
        
        //推送状态
        $delivery['process_status'] = $process_status;
        
        //创建翱象发货单
        $aoDeliveryInfo = $aoDeliveryObj->dump(array('delivery_id'=>$delivery_id), 'did');
        if($aoDeliveryInfo){
            return false;
        }
        
        $sdf = array(
            'shop_id' => $delivery['shop_id'],
            'shop_type' => $delivery['shop_type'],
            'delivery_id' => $delivery['delivery_id'],
            'delivery_bn' => $delivery['delivery_bn'],
            'create_time' => time(),
            'last_modified' => time(),
        );
        $aoDeliveryObj->insert($sdf);
        
        //sync
        $sync_error_msg = '';
        $result = $this->syncDelivery($delivery, 'auto', $sync_error_msg);
        
        return false;
    }

    /**
     * 自动同步翱象发货单状态队列任务
     *
     * @param $deliveryIds
     * @param $error_msg
     * @return void
     */
    public function syncAoxiangDelivery(&$cursor_id, $params, &$error_msg=null)
    {
        $deliveryObj = app::get('ome')->model('delivery');

        //data
        $sdfdata = $params['sdfdata'];
        $delivery_id = $sdfdata['delivery_id'];
        $process_status = $sdfdata['process_status'];

        //check
        if(empty($delivery_id)){
            //$error_msg = '没有发货单信息数据。';
            return false;
        }

        //delivery
        $deliveryList = $deliveryObj->getList('*', array('delivery_id'=>$delivery_id));
        $delivery = $deliveryList[0];
        if(empty($delivery)){
            //$error_msg = '发货单信息不存在。';
            return false;
        }

        //delivery
        $delivery['process_status'] = $process_status;

        //sync
        $operation = 'auto';
        $request_error_msg = '';
        $result = $this->syncDelivery($delivery, $operation, $request_error_msg);

        return false;
    }

    /**
     * 请求创建发货单给到翱象系统
     *
     * @param array $deliveryInfo
     * @param string $operation
     * @return array
     */
    public function syncDelivery($deliveryInfo, $operation='', &$error_msg=null)
    {
        $aoDeliveryObj = app::get('dchain')->model('aoxiang_delivery');

        //params
        $delivery_id = $deliveryInfo['delivery_id'];
        $process_status = $deliveryInfo['process_status'];
        if(empty($process_status)){
            $process_status = ($deliveryInfo['status'] == 'succ' ? 'confirm' : 'accept');
        }

        //获取发货单对应的订单信息(支持多订单合并发货)
        $sdf = $this->getDeliveryByOrder($deliveryInfo, $error_msg);
        if($sdf === false && $error_msg){
            //update
            $updateData = array('sync_status'=>'fail', 'sync_msg'=>$error_msg, 'last_modified'=>time());
            $aoDeliveryObj->update($updateData, array('delivery_id'=>$delivery_id));

            return $this->error($error_msg);
        }

        //check
        if(empty($sdf)){
            $error_msg = '发货单关联的信息不存在';

            //update
            $updateData = array('sync_status'=>'fail', 'sync_msg'=>$error_msg, 'last_modified'=>time());
            $aoDeliveryObj->update($updateData, array('delivery_id'=>$delivery_id));

            return $this->error($error_msg);
        }

        //发货单作业状态:accept仓库接单,confirm确认出库
        $sdf['process_status'] = $process_status;

        //request
        $updateData = array('last_modified'=>time());
        $result = kernel::single('erpapi_router_request')->set('shop', $deliveryInfo['shop_id'])->delivery_aoxiangReport($sdf);
        if($result['rsp'] == 'succ'){
            $updateData['sync_status'] = 'succ';
        }else{
            $updateData['sync_status'] = 'fail';
            $updateData['sync_msg'] = $result['error_msg'];
        }

        //update
        $aoDeliveryObj->update($updateData, array('delivery_id'=>$deliveryInfo['delivery_id']));

        return $result;
    }

    /**
     * 请求取消发货单
     *
     * @param array $deliveryInfo
     * @param string $operation
     * @return array
     */
    public function syncCancelDelivery($deliveryInfo, $operation='')
    {
        return false;
    }

    /**
     * 获取发货单对应的订单信息(支持多订单合并发货)
     * @param $deliveryInfo
     * @return void
     */
    public function getDeliveryByOrder($deliveryInfo, &$error_msg=null)
    {
        $orderObj = app::get('ome')->model('orders');
        $dlyOrderObj = app::get('ome')->model('delivery_order');
        $dlyDetailObj = app::get('ome')->model('delivery_items_detail');
        $orderObjMdl = app::get('ome')->model('order_objects');
        $orderitemMdl = app::get('ome')->model('order_items');
        $branchObj = app::get('ome')->model('branch');
        $corpObj = app::get('ome')->model('dly_corp');

        //params
        $delivery_id = $deliveryInfo['delivery_id'];
        $process_status = $deliveryInfo['process_status'];
        if(empty($process_status)){
            $deliveryInfo['process_status'] = ($deliveryInfo['status'] == 'succ' ? 'confirm' : 'accept');
        }

        //合并发货单(获取父发货单信息)
        if($deliveryInfo['parent_id'] > 0){
            $sql = "SELECT delivery_id,delivery_bn,logi_id,logi_name,logi_no,branch_id FROM sdb_ome_delivery WHERE delivery_id=". $deliveryInfo['parent_id'];
            $parentDlyInfo = $orderObj->db->selectrow($sql);
            if($parentDlyInfo['logi_no']){
                $deliveryInfo['logi_no'] = $parentDlyInfo['logi_no'];
                $deliveryInfo['logi_id'] = $parentDlyInfo['logi_id'];
            }
        }

        //branch
        $branch_id = $deliveryInfo['branch_id'];
        $branchInfo = $branchObj->db_dump(array('branch_id'=>$branch_id), 'branch_id,branch_bn,name');

        //logistics
        $dlyCorp = $corpObj->dump(array('corp_id'=>$deliveryInfo['logi_id']), 'corp_id,type,name');
        $deliveryInfo['logi_type'] = $dlyCorp['type'];
        $deliveryInfo['logi_name'] = $dlyCorp['name'];

        //发货单详细明细
        $detailList = $dlyDetailObj->getList('delivery_id,order_id,order_item_id,order_obj_id,bn,number', array('delivery_id'=>$delivery_id));
        $order_obj_ids = array_column($detailList, 'order_obj_id');
        $detailItems = array_column($detailList, null, 'order_obj_id');

        //发货单对应的订单
        $deliveryOrderList = $dlyOrderObj->getList('delivery_id,order_id', array('delivery_id'=>$delivery_id));
        $orderIds = array_column($deliveryOrderList, 'order_id');

        //order
        $orderList = $orderObj->getList('order_id,order_bn,createway', array('order_id'=>$orderIds));
        $orderOne = $orderList[0]; //只取一个订单信息
        $orderList = array_column($orderList, null, 'order_id');

        //订单objects明细
        $objectList = $orderObjMdl->getList('obj_id,order_id,obj_type,goods_id,bn,name,oid,quantity', array('obj_id'=>$order_obj_ids));
        if(empty($objectList)){
            $error_msg = '关联订单购买明细为空';

            return false;
        }

        //有组合商品时
        $obj_types = array_column($objectList, 'obj_type', 'obj_type');
        $orderItems = array();
        if($obj_types['pkg']){
            //订单items明细
            $orderItems = $orderitemMdl->getList('item_id,obj_id,order_id,item_type,product_id,bn,nums', array('obj_id'=>$order_obj_ids));

            //按obj_id层格式化数据
            $orderItems = array_column($orderItems, null, 'obj_id');
        }

        //merge
        $deliveryInfo = array_merge($deliveryInfo, $branchInfo);
        $deliveryInfo = array_merge($deliveryInfo, $orderOne);

        //是否拆单多批次发货(现在没有用到此字段)
        $deliveryInfo['is_split'] = false;

        //order_items
        $itemList = array();
        foreach ($objectList as $objKey => $objVal)
        {
            $obj_id = $objVal['obj_id'];
            $order_id = $objVal['order_id'];
            $orderRow = $orderList[$order_id];
            $order_bn = $orderRow['order_bn'];

            //发货数量
            if($objVal['order_id'] == 'pkg'){
                $obj_nums = $objVal['quantity'];
                $item_nums = intval($orderItems[$obj_id]['nums']);
                $dly_nums = intval($detailItems[$obj_id]['number']);

                //组合商品发货数量
                $delivery_nums = ($obj_nums / $item_nums) * $dly_nums;
            }else{
                $delivery_nums = intval($detailItems[$obj_id]['number']);

                //普通商品发货数量
                $delivery_nums = ($delivery_nums ? $delivery_nums : $objVal['quantity']);
            }

            //check本地创建的订单
            if(!in_array($orderRow['createway'], array('matrix', 'after'))){
                $error_msg = '本地创建的订单不推送发货单';

                return false;
            }

            //items
            $itemList[] = array(
                'order_id' => $order_id,
                'order_bn' => $order_bn,
                'oid' => $objVal['oid'],
                'bn' => $objVal['bn'],
                'nums' => $delivery_nums,
            );
        }

        $deliveryInfo['delivery_items'] = $itemList;

        return $deliveryInfo;
    }
}
