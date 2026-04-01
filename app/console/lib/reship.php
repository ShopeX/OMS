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
class console_reship{
    function reship_data($reship_id){
        $oReship = app::get('ome')->model('reship');
        $oOrders = app::get('ome')->model('orders');
        $reship_data = $oReship->dump(array('reship_id'=>$reship_id),'reship_bn,logi_no,logi_id,order_id,t_begin,return_type,shop_id,flag_type');
        $Oreship_items = app::get('ome')->model('reship_items');
        $oProcess_items = app::get('ome')->model('return_process_items');
        $Odly_corp = app::get('ome')->model('dly_corp');
        $dly_corp = $Odly_corp->dump($reship_data['logi_id'],'type');
        $Oreship_items = app::get('ome')->model('reship_items');
        $orders = $oOrders->dump($reship_data['order_id'],'order_bn');
        $reship_list = $Oreship_items->getlist('order_id,reship_id,bn,product_name,product_id,num,branch_id',array('reship_id'=>$reship_id),0,-1);


        $shop = app::get('ome')->model('shop')->db_dump($reship_data['shop_id'],'shop_bn,shop_id,name');

        $data = array();
        foreach ($reship_list as $list) {
            $branch_id = $list['branch_id'];

            if(isset($data[$branch_id])){
                $data[$branch_id]['items'][] = $list;
            }else{
                //获取仓库详情
                $branch_detail = kernel::single('console_iostockdata')->getBranchByid($branch_id);

                $data[$branch_id]['items'][] = $list;
                $data[$branch_id]['reship_bn'] = $reship_data['reship_bn'];
                $data[$branch_id]['branch_bn'] = $branch_detail['branch_bn'];
                $data[$branch_id]['storage_code'] = $branch_detail['storage_code'];
                $data[$branch_id]['owner_code'] = $branch_detail['owner_code'];
                $data[$branch_id]['create_time'] = $reship_data['t_begin'];//storage_code
                $data[$branch_id]['memo'] = '';//storage_code
                $data[$branch_id]['return_type'] = $reship_data['return_type'];
                $data[$branch_id]['flag_type'] = $reship_data['flag_type'];
                //memo original_delivery_bn
                $data[$branch_id]['original_delivery_bn'] = '';
                $data[$branch_id]['logi_no'] = $reship_data['logi_no'];
                $data[$branch_id]['logi_name'] = $reship_data['logi_name'];
                $data[$branch_id]['logi_code'] = $dly_corp['type'];
                $data[$branch_id]['order_bn'] = $orders['order_bn'];
                $data[$branch_id]['receiver_name'] = $reship_data['ship_name'];
                $data[$branch_id]['receiver_zip'] = $reship_data['ship_zip'];
                $data[$branch_id]['receiver_state'] = $reship_data['ship_area'];
                $data[$branch_id]['receiver_city'] = '';
                $data[$branch_id]['receiver_district'] = '';
                $data[$branch_id]['receiver_address'] = $reship_data['ship_addr'];
                $data[$branch_id]['receiver_phone'] = $reship_data['ship_tel'];
                $data[$branch_id]['receiver_mobile'] = $reship_data['ship_mobile'];
                $data[$branch_id]['receiver_email'] = $reship_data['ship_email'];
                // $data[$branch_id]['shop'] = $shop;
            }
        }

        return $data;
    }

    /**
    * 取消退货单
    */
    function notify_reship($type,$reship_id){
        $reship_data = kernel::single('console_reship')->reship_data($reship_id);
        if ($type == 'create'){//创建

            foreach ($reship_data as $rk=>$rv) {
                $wms_id = kernel::single('ome_branch')->getWmsIdById($rk);
                $tmp = $rv;
                kernel::single('console_event_trigger_reship')->create($wms_id, $tmp, false);
            }
        }else if($type == 'cancel'){//取消

            foreach ($reship_data as $rk=>$rv) {
                $wms_id = kernel::single('ome_branch')->getWmsIdById($rk);
                $tmp = $rv;
                kernel::single('console_event_trigger_reship')->cancel($wms_id, $tmp, true);
            }

        }else{
            return true;
        }

    }

    /**
     * 换货商品明细
     * @param
     * @return  array
     * @access  public
     * @author sunjng@shopex.cn
     */
    function change_items($reship_id)
    {
        $oReship_item = app::get('ome')->model('reship_items');
        $reship_item = $oReship_item->getList('bn,product_name,num,product_id,changebranch_id,obj_id',array('reship_id'=>$reship_id,'return_type'=>'change'),0,-1);
        return $reship_item;
    }

    /**
     * 退换货商品object
     * @param
     * @return  array
     * @access  public
     * @author sunjng@shopex.cn
     */
    function change_objects($reship_id)
    {
        $oReship_object = app::get('ome')->model('reship_objects');
        $reship_item = $oReship_object->getList('*',array('reship_id'=>$reship_id),0,-1);
        return $reship_item;
    }

    public function siso_iostockReship($reship_id, &$msg=''){
        $oReship = app::get('ome')->model('reship');
        $reship = $oReship->dump(array('reship_id'=>$reship_id),'branch_id,return_type');
        $oReship_item = app::get('ome')->model('reship_items');
        
        if(app::get('ome')->getConf('wms.goods.type.branch') != 1) {

            $normal_reship_item = $oReship_item->getList('*',array('reship_id'=>$reship_id,'normal_num|than'=>0));
            $defective_reship_item = $oReship_item->getList('*',array('reship_id'=>$reship_id,'defective_num|than'=>0));
            $reshipLib = kernel::single('siso_receipt_iostock_reship');
            $flag = true;
            $data = array(); // 定义$data变量
            
            if (count($normal_reship_item)>0){
                $return_type = $reship['return_type'];
                if ($return_type == 'refuse'){
                    $reshipLib->_typeId=32;
                }else{
                    $reshipLib->_typeId=30;
                }
               $normal_result = $reshipLib->create(array('reship_id'=>$reship_id,'items'=>$normal_reship_item,'branch_id'=>$reship['branch_id']), $data, $msg);
                if (!$normal_result){
                    $flag = false;
                }
            }

            if (count($defective_reship_item)>0) {
                $damaged = kernel::single('console_iostockdata')->getDamagedbranch($reship['branch_id']);
                $reshipLib->_typeId=50;
                $defective_result = $reshipLib->create(array('reship_id'=>$reship_id,'items'=>$defective_reship_item,'branch_id'=>$damaged['branch_id'],'orig_type_id'=>'30'), $data, $msg);
                if (!$defective_result){
                    $flag = false;
                }
            }
            return $flag;
        }
        
        // 根据return_process_items进行入库，按branch_id和bn分组累加数量
        $return_process_items = app::get('ome')->model('return_process_items');
        $process_items = $return_process_items->getList('*', array('reship_id'=>$reship_id, 'is_check'=>'true'), 0, -1);

        // 按branch_id和bn分组处理入库，累加数量
        $branch_items = array();
        foreach ($process_items as $item) {
            $branch_id = $item['branch_id'] ? $item['branch_id'] : $reship['branch_id'];
            $bn = $item['bn'];
            $key = $branch_id . '_' . $bn;
            
            if (!isset($branch_items[$key])) {
                $branch_items[$key] = $item;
            } else {
                $branch_items[$key]['num'] += $item['num'];
            }
        }
        
        // 按仓库分组整理数据
        $branch_grouped_items = array();
        foreach ($branch_items as $item) {
            $branch_id = $item['branch_id'];
            if (!isset($branch_grouped_items[$branch_id])) {
                $branch_grouped_items[$branch_id] = array();
            }
            
            // 直接使用process_items的数据构建入库项
            $stock_item = array(
                'item_id' => $item['item_id'],
                'bn' => $item['bn'],
                'normal_num' => $item['num'],
            );
            
            $branch_grouped_items[$branch_id][] = $stock_item;
        }
        
        $reshipLib = kernel::single('siso_receipt_iostock_reship');
        $flag = true;
        
        // 按仓库分别生成入库单
        foreach ($branch_grouped_items as $branch_id => $items) {
            $data = array(); // 为每个仓库定义$data变量
            
            if (!empty($items)) {
                $return_type = $reship['return_type'];
                if ($return_type == 'refuse'){
                    $reshipLib->_typeId=32;
                }else{
                    $reshipLib->_typeId=30;
                }
                $result = $reshipLib->create(array('reship_id'=>$reship_id,'items'=>$items,'branch_id'=>$branch_id), $data, $msg);
                if (!$result){
                    $flag = false;
                }
            }
        }
        
        return $flag;
    }

    public function update_sync_status($reship_id, $status, $msg=''){
        if(!$reship_id || !$status){
            return false;
        }



        $updateArr = array(
            'sync_status'=>$status
        );

        if($msg){
            $updateArr['sync_msg'] = $msg;
        }

        $reshipObj = app::get('ome')->model('reship');
        return $reshipObj->update($updateArr, array('reship_id'=>$reship_id));
    }

    /**
     * @ 退货单取消
     * @DateTime  2018-01-30T17:06:22+0800
     * @param
     * @param
     * @return
     */
    static public function cancel($reship,$log_memo=''){
        $reshipObj = app::get('ome')->model('reship');
        $logObj = app::get('ome')->model('operation_log');//写日志
        $updata = array();
        $updata = array('is_check'=>'5','t_end'=>time());
        $memo = $log_memo;

        if (in_array($reship['is_check'],array('1','11')) && $reship['return_type'] == 'change' && $reship['change_status'] == '0') {
            //如果是退货已审核成功,单据不取消。但是把 换货订单状态生成关闭 释放冻结库存
            $updata['change_status']    ='2';
            $memo.=',换货订单状态更新为:不生成';
        }
        kernel::single('console_reship')->releaseChangeFreeze($reship['reship_id']);
        if (in_array($reship['is_check'],array('11'))){

            unset($updata['is_check']);
        }

        if($updata){

            $reshipObj->update($updata,array('reship_id'=>$reship['reship_id']));
        }
        if($updata['is_check']) {
            // 退货单取消后的service扩展点
            foreach(kernel::servicelist('console.service.reship.cancel.after') as $object) {
                if(method_exists($object, 'cancel_reship_after')) {
                    $object->cancel_reship_after($reship['reship_id']);
                }
            }
        }

        // 退货单取消成功通知
        kernel::single('ome_reship')->sendReshipCancelSuccessNotify($reship, $log_memo);
  
        
        $logObj->write_log('reship@ome',$reship['reship_id'],$memo);
    }

    static public function notice($reship){
        $res = array('rsp'=>'succ');
        if ($reship['is_check'] == '1'){
            $branch_id = $reship['branch_id'];
            $wms_id = kernel::single('ome_branch')->getWmsIdById($branch_id);
            $branch = kernel::single('ome_branch')->getBranchInfo($branch_id,'branch_bn,storage_code,owner_code');
            $data = array(
                'branch_id'=>$branch_id,
                'order_id' => $reship['order_id'],
                'reship_id' => $reship['reship_id'],
                'reship_bn' => $reship['reship_bn'],
                'branch_bn' => $branch['branch_bn'],
                'owner_code' => $branch['owner_code'],
            );

            $res = kernel::single('console_event_trigger_reship')->cancel($wms_id, $data, true);
             // 退货单取消成功报警通知
            if ($res['rsp'] == 'succ'){
                kernel::single('ome_reship')->sendReshipCancelSuccessNotify($reship, '平台取消');
            }
            
        }


        return $res;

    }

    /**
     * 换货冻结释放
     * @param  
     * @return 
     */
    public function releaseChangeFreeze($reship_id, $bm_id = ''){
        $reshipObj = app::get('ome')->model('reship');
        $reship_detail = $reshipObj->dump(array('reship_id'=>$reship_id),'reship_bn,changebranch_id,shop_type,source,is_check');
        $changebranch_id = $reship_detail['changebranch_id'];
        $storeManageLib = kernel::single('ome_store_manage');
        $reship_item = [];
        $stockFre = app::get('material')->model('basic_material_stock_freeze');
        // 换货单审核换出商品库存不足会冻结到商品上（不冻仓）, 所以obj_type也有可能是1
        $filter = array(
            // 'obj_type'=>material_basic_material_stock_freeze::__BRANCH, 
            'obj_id'=>$reship_id, 
            'bill_type'=>material_basic_material_stock_freeze::__RESHIP
        );
        if($bm_id) {
            $filter['bm_id'] = $bm_id;
        }
        $freeze = $stockFre->getList('*', $filter);
        if(empty($freeze)) {
            return [true, ['msg'=>'没用预占明细']];
        }
        foreach($freeze as $v) {
            $bn = app::get('material')->model('basic_material')->db_dump(['bm_id'=>$v['bm_id']], 'material_bn')['material_bn'];
            #bn,product_name,num,product_id,changebranch_id
            $reship_item[] = [
                'changebranch_id'     =>  $v['branch_id'],
                'product_id'    =>  $v['bm_id'],
                'num'           =>  $v['num'],
                'bn'            =>  $bn,
                'goods_id'      =>  $v['sm_id'],
                'obj_type'      =>  $v['obj_type'],
            ];
        }
        $storeManageLib->loadBranch(array('branch_id'=>$changebranch_id));

        $params = array(
            'reship_item'       =>  $reship_item,
            'changebranch_id'   =>  $changebranch_id,
            'reship_id'         =>  $reship_id,
        );
        $params_stock = array(
                'params'    => $params,
                'node_type' => 'refuseChangeReship',
        );
        $rs = $storeManageLib->processBranchStore($params_stock, $err_msg);
        return [$rs, ['msg'=>$err_msg]];
    }

    /**
     * 新增换货冻结
     * @param 
     */
    public function addChangeFreeze($reship_id, &$err_msg=null){
        $reshipObj = app::get('ome')->model('reship');
        $reship_detail = $reshipObj->dump(array('reship_id'=>$reship_id),'changebranch_id,source,shop_type,return_id,shop_id');
        $changebranch_id = $reship_detail['changebranch_id'];
        $shop_id = $reship_detail['shop_id'];
        $storeManageLib = kernel::single('ome_store_manage');
        $reship_item = $this->change_items($reship_id);
        if(empty($reship_item) && $reship_detail['shop_type']=='360buy' && $reship_detail['source']=='matrix'){
            $itemMdl = app::get('ome')->model('change_items');
            $reship_item = $itemMdl->getlist('bn,num,product_id',array('return_id'=>$reship_detail['return_id']));
        }
        if(empty($reship_item)) {
            $err_msg = '缺少换货明细';
            return false;
        }
        $storeManageLib = kernel::single('ome_store_manage');
        $storeManageLib->loadBranch(array('branch_id'=>$changebranch_id));
        //加判断是京东
        $params = array(
            'shop_id'           =>  $shop_id,
            'reship_item'       =>  $reship_item,
            'changebranch_id'   =>  $changebranch_id,
            'reship_id'         =>  $reship_id,
        );
        $params_stock = array(
                "params"    => $params,
                "node_type" => 'checkChangeReship',
        );

        $result = $storeManageLib->processBranchStore($params_stock, $err_msg);
        
        return $result;
    }

    public function batchOrderRefundToLJRK($dlyIds) {
        foreach($dlyIds as $dlyId) {
            $this->orderRefundToLJRK($dlyId);
        }
    }

    public function orderRefundToLJRK($dlyId) {
        $dlyInfo = app::get('ome')->model('delivery')->db_dump(['delivery_id'=>$dlyId], 'delivery_id,branch_id,logi_no,logi_name,process,logi_status');
        if(empty($dlyInfo)) {
            return [false, ['msg'=>'缺少发货单']];
        }
        if($dlyInfo['process'] != 'true') {
            return [false, ['msg'=>'未发货']];
        }
        if($dlyInfo['logi_status'] == '7') {
            return [false, ['msg'=>'已被拦截']];
        }
        $didItems = app::get('ome')->model('delivery_items_detail')->getList('*', ['delivery_id'=>$dlyId]);
        $tgOrder = app::get('ome')->model('orders')->getList('pay_status,order_type,shop_type', ['order_id'=>array_column($didItems, 'order_id')]);
        $refundApply = app::get('ome')->model('refund_apply')->getList('apply_id,bool_type,product_data,tag_type,order_id', array('order_id' => array_column($didItems, 'order_id'), 'status' => '4'));
        $needLJ =  false;
        foreach($tgOrder as $v) {
            //[天猫定制订单]申请售后仅退款,不用转换拦截退货单
            if($v['order_type'] == 'custom' && in_array($v['shop_type'], ['taobao', 'tmall'])){
                continue;
            }
            
            if($v['pay_status'] == 5) {
                $needLJ = true;
                break;
            }
        }
        $refundItemId = [];
        $orderQZ = [];
        foreach($refundApply as $val) {
            if($val['bool_type'] & ome_refund_bool_type::__PROTECTED_CODE) {
                continue;
            }
            $arrProduct = unserialize($val['product_data']);
            if(!is_array($arrProduct)) {
                continue;
            }
            if($val['tag_type'] == 8) {
                $orderQZ[] = $val['order_id'];
            }
            $order_item_id = array_column($arrProduct, 'order_item_id');
            $refundItemId = array_merge($refundItemId, $order_item_id);
            foreach($didItems as $v) {
                if(in_array($v['order_item_id'], $order_item_id)) {
                    $needLJ = true;
                    break;
                }
            }
        }
        if(!$needLJ) {
            return [false, ['msg'=>'不需要拦截']];
        }
        $orderDidItems = [];
        foreach($didItems as $v) {
            $orderDidItems[$v['order_id']][] = $v;
        }
        foreach($orderDidItems as $order_id => $v) {
            $dlyInfo['qz_refund'] = in_array($order_id, $orderQZ);
            $this->oneOrderRefundToLJRK($v, $dlyInfo, $refundItemId);
        }
    }

    public function qzRefundToLJRK($refundApply){
        // 1. 解析product_data字段
        $arrProduct = unserialize($refundApply['product_data']);
        if (!is_array($arrProduct)) {
            return [false, ['msg' => '退款单缺少商品明细']];
        }
        
        // 获取退款商品ID数组
        $refundItemId = array_column($arrProduct, 'order_item_id');
        
        // 使用product_data组织didItems参数
        $didItems = [];
        foreach ($arrProduct as $product) {
            $didItems[] = [
                'order_id' => $refundApply['order_id'],
                'order_item_id' => $product['order_item_id'],
                'product_id' => $product['product_id'],
                'bn' => $product['bn'],
                'number' => $product['num'], // product_data中使用的是num字段
                'oid' => $product['oid']
            ];
        }
        
        // 获取发货单信息
        $deliveryOrderItemModel = app::get('sales')->model('delivery_order_item');
        $filter = [
            'order_id' => $refundApply['order_id'],
            'order_item_id' => $refundItemId,
        ];
        $deliveryOrderItem = $deliveryOrderItemModel->db_dump($filter, 'delivery_id');
        if (!$deliveryOrderItem) {
            return [false, ['msg' => '发货单不存在']];
        }
        $deliveryId = $deliveryOrderItem['delivery_id'];
        $deliveryModel = app::get('ome')->model('delivery');
        $deliveryInfo = $deliveryModel->db_dump(['delivery_id' => $deliveryId], 
            'delivery_id,branch_id,logi_no,logi_name,process,logi_status');
        
        $dlyInfo = [
            'delivery_id' => $deliveryInfo['delivery_id'],
            'branch_id' => $deliveryInfo['branch_id'],
            'logi_no' => $deliveryInfo['logi_no'],
            'logi_name' => $deliveryInfo['logi_name'],
            'process' => $deliveryInfo['process'],
            'logi_status' => $deliveryInfo['logi_status'],
            'qz_refund' => ($refundApply['tag_type'] == 8), // 强制退款标识
            'reship_bn' => 'LJ'.$refundApply['refund_apply_bn'],
        ];
        
        return $this->oneOrderRefundToLJRK($didItems, $dlyInfo, $refundItemId);
    }

    public function oneOrderRefundToLJRK($didItems, $dlyInfo, $refundItemId){
        $tgOrder = app::get('ome')->model('orders')->db_dump(['order_id'=>array_column($didItems, 'order_id')], '*');
        $opInfo     = kernel::single('ome_func')->get_system();
        $insertData = array(
            'reship_bn'        => $dlyInfo['reship_bn'] ? : 'LJ'.$tgOrder['order_id'].$dlyInfo['delivery_id'],
            'shop_id'          => $tgOrder['shop_id'],
            'shop_type'        => $tgOrder['shop_type'],
            'order_id'         => $tgOrder['order_id'],
            'member_id'        => $tgOrder['member_id'],
            'logi_name'        => $dlyInfo['logi_name'],
            'logi_no'          => $dlyInfo['logi_no'],
            'logi_id'          => $tgOrder['logi_id'],
            'ship_name'        => $tgOrder['ship_name'],
            'ship_area'        => $tgOrder['ship_area'],
            'delivery'         => $tgOrder['shipping'],
            'ship_addr'        => $tgOrder['ship_addr'],
            'ship_zip'         => $tgOrder['ship_zip'],
            'ship_tel'         => $tgOrder['ship_tel'],
            'ship_email'       => $tgOrder['ship_email'],
            'ship_mobile'      => $tgOrder['ship_mobile'],
            'is_protect'       => $tgOrder['is_protect'],
            'delivery_id'      => $dlyInfo['delivery_id'],
            'branch_id'        => $dlyInfo['branch_id'],
            'return_logi_name' => $dlyInfo['logi_name'],
            'return_logi_no'   => $dlyInfo['logi_no'],
            't_begin'          => time(),
            'op_id'            => $opInfo['op_id'],
            'flag_type'        => ome_reship_const::__LANJIE_RUKU,
            'org_id'           => $tgOrder['org_id'],
        );
        if($dlyInfo['qz_refund']) {
            $insertData['flag_type'] = $insertData['flag_type'] | ome_reship_const::__QZ_REFUND_CODE;
        }
        $shop_info = app::get('ome')->model('shop')->getShopInfo($insertData['shop_id']);
        // 经销店铺的单据，delivery_mode冗余到售后申请表
        if ($shop_info['delivery_mode'] == 'jingxiao') {
            $insertData['delivery_mode'] = $shop_info['delivery_mode'];
        }

        $modelReship = app::get('ome')->model('reship');
        $rs          = $modelReship->insert($insertData);
        if (!$rs) {
            return [false, ['msg' => '退货单新建失败'.$modelReship->db->errorinfo()]];
        }
        $objs = app::get('ome')->model('order_objects')->getList('obj_id, oid', ['order_id'=>$tgOrder['order_id']]);
        $objs = array_column($objs, null, 'obj_id');
        $orderItems = app::get('ome')->model('order_items')->getList('*', ['order_id'=>$tgOrder['order_id']]);
        $orderItems = array_column($orderItems, null, 'item_id');
        $reshipItems = array();
        $tmoney = $had_refund = 0;
        foreach ($didItems as $item) {
            if($item['number'] > 0) {
                $order_item_id = $item['order_item_id'];
                $obj_id = $orderItems[$order_item_id]['obj_id'];
                $price = sprintf('%.2f', $orderItems[$order_item_id]['divide_order_fee'] / $orderItems[$order_item_id]['nums']);
                if($item['number'] ==  $orderItems[$order_item_id]['nums']) {
                    $amount = $orderItems[$order_item_id]['divide_order_fee'];
                } else {
                    $amount = sprintf('%.2f', $price * $item['number']);
                }
                $reshipItems[] = array(
                    'reship_id'    => $insertData['reship_id'],
                    'op_id'        => $opInfo['op_id'],
                    'bn'           => $item['bn'],
                    'num'          => $item['number'],
                    'price'        => $price,
                    'amount'        => $amount,
                    'branch_id'    => $dlyInfo['branch_id'],
                    'product_name' => $orderItems[$order_item_id]['name'],
                    'product_id'   => $item['product_id'],
                    'order_item_id' => $order_item_id,
                    'oid'           => $objs[$obj_id]['oid'],
                    'order_object_id'=> $obj_id,
                );
                $tmoney += $amount;
                if($tgOrder['pay_status'] == '5' || in_array($order_item_id, $refundItemId)) {
                    $had_refund += $amount;
                }
            }
        }
        if($reshipItems) {
            $modelItem = app::get('ome')->model('reship_items');
            $sql       = ome_func::get_insert_sql($modelItem, $reshipItems);
            $modelItem->db->exec($sql);
        } else { 
            $oOperation_log = app::get('ome')->model('operation_log');
            $memo           = '退款订单发货新建退货拦截单，缺少退货明细';
            $oOperation_log->write_log('reship@ome', $insertData['reship_id'], $memo);
            return [false, ['msg'=>'缺少退货明细']];
        }
        $upData = [
            'totalmoney' => $tmoney - $had_refund,
            'tmoney' => $tmoney,
            'had_refund' => $had_refund
        ];
        $modelReship->update($upData, ['reship_id' => $insertData['reship_id']]);
        # 操作日志
        $oOperation_log = app::get('ome')->model('operation_log');
        $memo           = '退款订单发货新建退货拦截单';
        $oOperation_log->write_log('reship@ome', $insertData['reship_id'], $memo);
        foreach(kernel::servicelist('ome.service.reship.create.after') as $object) {
            if(method_exists($object, 'after_create')){
                $object->after_create($insertData['reship_id']);
            }
        }
        $insertData = array_merge($insertData, $upData);
        $this->reshipToReturn($insertData);
        //退换货自动审批(系统-->退换货自动审核设置-->是否启用退换货自动审批)
        $is_auto_approve = app::get('ome')->getConf('return.auto_approve');
        if($is_auto_approve == 'on'){
            $reshipLib = kernel::single('ome_reship');
            $rs = $reshipLib->confirm_reship(array(
                'reship_id' => $insertData['reship_id'],
                'status'    => '1',
                'is_anti'   => false,
                'exec_type' => 1,
            ), $msg, $is_rollback);
            if(!$rs) {
                app::get('ome')->model('operation_log')->write_log('reship@ome', $insertData['reship_id'], '自动审核失败:'.$msg);
            }
        }
        $err = '';
        $label_code = 'SOMS_MREFUND';
        kernel::single('ome_bill_label')->markBillLabel($tgOrder['order_id'], '', $label_code, 'order', $err, 0);
        return [true, ['msg'=>'操作完成']];
    }

    public function reshipToReturn($reship) {
        if($reship['return_id']) {
            return [false, ['msg'=>'售后申请单已存在']];
        }
        $opInfo = kernel::single('ome_func')->get_system();
        $data = array(
            'return_bn'  => (strpos($reship['reship_bn'], 'LJ') === false ? 'G'.str_pad($reship['reship_id'], 12, '0', STR_PAD_LEFT) : $reship['reship_bn']),
            'shop_id'    => $reship['shop_id'],
            //'member_id'  => $reship['member_id'],
            'order_id'   => $reship['order_id'],
            'title'      => $reship['reship_bn'].'补售后申请单',
            'add_time'   => $reship['t_begin'],
            'status'     => $reship['is_check'] == '7' ? '4': '3',
            'op_id'      => $opInfo['op_id'],
            'refundmoney'=> $reship['totalmoney'],
            'money'      => $reship['totalmoney'],
            'source'     => 'local',
            'shop_type'  => $reship['shop_type'],
            'org_id'     => $reship['org_id'],
            'flag_type'  => $reship['flag_type'],
            'kinds'      => $reship['return_type'] == 'change' ? 'change' : 'reship',
            'archive'    => $reship['archive'],
        );
        
        //售后类型
        if ($reship['return_type']){
            $data['return_type'] = $reship['return_type'];
        }
        if ($reship['delivery_id']){
            $data['delivery_id'] = $reship['delivery_id'];
        }
        if ($reship['member_id']){
            $data['member_id'] = $reship['member_id'];
        }
        
        //平台订单号
        if($reship['platform_order_bn']){
            $data['platform_order_bn'] = $reship['platform_order_bn'];
        }

        // 经销店铺的单据，delivery_mode冗余到售后申请表
        if ($reship['delivery_mode'] == 'jingxiao') {
            $data['delivery_mode'] = $reship['delivery_mode'];
        }
        $rs = app::get('ome')->model('return_product')->insert($data);
        if(!$rs) {
            return [false, ['msg'=>'主表写入失败：'.kernel::database()->errorinfo()]];
        }
        app::get('ome')->model('operation_log')->write_log('return@ome', $data['return_id'], '退货单补售后申请单');
        $reshipItems = app::get('ome')->model('reship_items')->getList('*', ['reship_id'=>$reship['reship_id'], 'return_type'=>'return']);
        $return_product_items = [];
        foreach($reshipItems as $val) {
            $return_product_items[] = array(
                'return_id'  => $data['return_id'],
                'product_id' => $val['product_id'],
                'bn'         => $val['bn'],
                'name'       => $val['product_name'],
                'num'        => $val['num'],
                'price'      => $val['price'],
                'amount'     => $val['amount'],
                'branch_id'   =>$reship['branch_id'],
                'order_item_id'=>$val['order_item_id'],
                'obj_type'  =>'product',
                'quantity'  =>$val['num'],
            );
        }
        $modelItems = app::get('ome')->model('return_product_items');
        $sql = kernel::single('ome_func')->get_insert_sql($modelItems, $return_product_items);
        kernel::database()->exec($sql);
        app::get('ome')->model('reship')->update(['return_id'=>$data['return_id']], ['reship_id'=>$reship['reship_id']]);
        $aftersale = app::get('sales')->model('aftersale');
        $id = $aftersale->db_dump(['reship_id'=>$reship['reship_id'],'reship_bn'=>$reship['reship_bn']], 'aftersale_id')['aftersale_id'];
        if($id) {
            $aftersale->update(
                ['return_id'=>$data['return_id'], 'return_bn'=>$data['return_bn']],
                ['aftersale_id'=>$id]
            );
        }
        return [true];
    }

    public function addReshipDiff($isoId) {
        $iso = app::get('taoguaniostockorder')->model('iso')->db_dump(['iso_id'=>$isoId], 'iso_id,iso_bn,type_id,iso_status,bill_type,business_bn,branch_id');
        if($iso['type_id'] != '70') {
            return [false, ['msg' => '不是其他入库单']];
        }
        if($iso['iso_status'] != '3') {
            return [false, ['msg' => '其他入库单未完成']];
        }
        if($iso['bill_type'] != 'oms_reship_diff') {
            return [false, ['msg' => '其他入库单不是退货差异入库']];
        }
        if(empty($iso['business_bn'])){
            return [false, ['msg' => '其他入库单缺少业务单号']];
        }
        $modelReship = app::get('ome')->model('reship');
        $reship = $modelReship->db_dump(['reship_bn'=>$iso['business_bn']]);
        if(empty($reship)) {
            return [false, ['msg'=>'没有找到对应的退货单：'.$iso['business_bn']]];
        }
        if(in_array($reship['is_check'], ['1', '3'])) {
            self::cancel($reship, $iso['iso_bn'].':退货差异入库完成，取消退货单');
        }
        $insertData = $reship;
        unset($insertData['reship_id'], $insertData['return_id']);
        $insertData['reship_bn'] = 'DF'.$insertData['reship_bn'];
        $insertData['status'] = 'ready';
        $insertData['branch_id'] = $iso['branch_id'];
        $insertData['is_check'] = '0';
        $insertData['change_status'] = '0';
        $insertData['out_iso_bn'] = $iso['iso_bn'];
        $insertData['flag_type'] = ome_reship_const::__RESHIP_DIFF;
        $rs          = $modelReship->insert($insertData);
        if (!$rs) {
            return [false, ['msg' => '退货单新建失败'.$modelReship->db->errorinfo()]];
        }
        $opInfo     = kernel::single('ome_func')->get_system();
        $isoItems = app::get('taoguaniostockorder')->model('iso_items')->getList('*', ['iso_id'=>$isoId]);
        $bmExt = app::get('material')->model('basic_material_ext')->getList('bm_id,retail_price', ['bm_id'=>array_column($isoItems, 'product_id')]);
        $bmExt = array_column($bmExt, null, 'bm_id');
        $reshipItems = [];
        foreach($isoItems as $item) {
            $tmp = array(
                'reship_id'    => $insertData['reship_id'],
                'op_id'        => $opInfo['op_id'],
                'bn'           => $item['bn'],
                'num'          => $item['normal_num'] + $item['defective_num'],
                'normal_num'   => $item['normal_num'],
                'defective_num' => $item['defective_num'],
                'branch_id'    => $iso['branch_id'],
                'product_name' => $item['product_name'],
                'product_id'   => $item['product_id'],
            );
            $tmp['porth'] = $tmp['num'] * $bmExt[$item['product_id']]['retail_price'];
            $reshipItems[] = $tmp;
        }
        $options = array (
            'part_total'  => $insertData['tmoney'],
            'part_field'  => 'amount',
            'porth_field' => 'porth',
        );
        $reshipItems = kernel::single('ome_order')->calculate_part_porth($reshipItems, $options);
        foreach($reshipItems as $k => $v) {
            $reshipItems[$k]['price'] = sprintf("%.2f", $v['amount'] / $v['num']);
        }
        $modelItem = app::get('ome')->model('reship_items');
        $sql       = ome_func::get_insert_sql($modelItem, $reshipItems);
        $modelItem->db->exec($sql);
        $memo           = $iso['iso_bn'].':退货差异入库完成，生成退货单';
        app::get('ome')->model('operation_log')->write_log('reship@ome', $insertData['reship_id'], $memo);
        return [true];
    }


    public function releaseShopChangeFreeze($reship_bn, $shop_id) {
        $reship = app::get('ome')->model('reship')->db_dump(['reship_bn'=>$reship_bn, 'shop_id'=>$shop_id], 'reship_id');
        if($reship) {
            $this->releaseChangeFreeze($reship['reship_id']);
        }
    }



}
?>
