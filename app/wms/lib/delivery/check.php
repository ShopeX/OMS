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

class wms_delivery_check{

    /**
     * @description 是否允许校验
     * @access public
     * @param void
     * @return void
     */
    public function checkAllow($logi_no, &$msg, $act,$command = false)
    {
        $dlyModel            = app::get('wms')->model('delivery');
        $dlyItemsModel       = app::get('wms')->model('delivery_items');
        $dlyBillMdl = app::get('wms')->model('delivery_bill');
        
        $dlyBillObj          = kernel::single('wms_delivery_bill');
        $deliveryBillItemMdl = app::get('wms')->model('delivery_bill_items');

        $deliveryBill = $dlyBillMdl->db_dump(['logi_no' => $logi_no]);
        $delivery_id = $deliveryBill ? $deliveryBill['delivery_id'] : 0;

        $delivery = $dlyModel->getList('branch_id,delivery_id,status,deli_cfg,process_status,delivery_bn,print_status,logi_number',array('delivery_id'=>$delivery_id),0,1);

        // [提货物流] delivery_model='pickup' 运单号后取，扫描的是发货单号
        if (empty($delivery)) {
            $pickupDly = $dlyModel->getList('branch_id,delivery_id,status,deli_cfg,process_status,delivery_bn,print_status,logi_number', array('delivery_bn' => $logi_no, 'delivery_model' => 'pickup'), 0, 1);
            if ($pickupDly) {
                $delivery = $pickupDly;
                $delivery_id = $pickupDly[0]['delivery_id'];
                $deliveryBill = $dlyBillMdl->db_dump(array('delivery_id' => $delivery_id, 'type' => 1));
            }
        }

        //[同城配]商家配送支持配送员手机号搜索
        if(empty($delivery) && strlen($logi_no) == 11){
            $delivery = $dlyModel->getList('*', array('deliveryman_mobile'=>$logi_no, 'process_status'=>array(0,1)), 0, 1);
            if($delivery){
                $delivery_id = $delivery[0]['delivery_id'];
                
                $deliveryBill = $dlyBillMdl->db_dump(array('delivery_id'=>$delivery_id), '*');
            }
        }
        
        if (!$delivery) {
            $msg = '快递单号【'.$logi_no.'】不存在！';
            return false;
        }
        $delivery = current($delivery);


        if ($delivery['logi_number'] > '1' && 0 == $deliveryBillItemMdl->count(['bill_id' => $deliveryBill['b_id']])){
            $msg = '快递单号【'.$logi_no.'】未添加货品！';
            return false;
        }

        if($command === false){
            $oBranch = app::get('ome')->model('branch');
            $is_super = kernel::single('desktop_user')->is_super();
            if ($is_super) {
                $branches = array('_ALL_');
            } else {
                $branches = $oBranch->getBranchByUser(true);
            }

            if (!in_array($delivery['branch_id'],$branches) && $branches[0] != '_ALL_') {
                $msg = '你无权对快递单【'.$logi_no.'】进行校验！';
                return false;
            }
        }

        if(!$act || !in_array($act,array('barcode','all','group','batch','outer_consign'))){
            $msg = '快递单号【'.$logi_no.'】无效的校验操作！';
            return false;
        }else{
            if(in_array($act,array('all','group','batch'))){
                $check_bns = $dlyItemsModel->getList('use_expire,product_id',array('delivery_id'=>$delivery_id));
                foreach($check_bns as $bn){
                    //check storagelife
                    if($bn['use_expire'] == 1){
                        $msg = '快递单号【'.$logi_no.'】存在保质期物料，推荐逐单校验！';
                        return false;
                    }

                    //check serial number
                   $isSerialNumber = kernel::single('material_serial')->checkSerialById($bn['product_id']);
                   if($isSerialNumber){
                        $msg = '快递单号【'.$logi_no.'】存在唯一码物料，推荐逐单校验！';
                        return false;
                   }
                }
            }
        }

        if (!$this->existOrderStatus($delivery['delivery_id'])){
            $msg = '快递单号【'.$logi_no.'】对应发货单不处于可校验状态！';
            return false;
        }

        if (!$this->existOrderPause($delivery['delivery_id'])){
            $msg = '快递单号【'.$logi_no.'】对应发货单订单存在异常！';
            return false;
        }

        //判断订单在前端店铺是否可以发货
        if (!$this->checkOrderIsDelivery($delivery['delivery_id'], $error_msg))
        {
            $msg = $error_msg;
            return false;
        }
        
        if (($delivery['process_status'] & 2) == 2){
            $msg = '快递单号【'.$logi_no.'】对应发货单已校验完成！';
            return false;
        }
        if ($delivery['status'] != 0){
            $msg = '快递单号【'.$logi_no.'】对应发货单不满足校验需求！';
            return false;
        }
        if ($delivery['status'] == 2){
            $msg = '快递单号【'.$logi_no.'】对应发货单已暂停！';
            return false;
        }

        $printFinish = $this->checkPrintFinish($delivery,$print_msg);
        if($printFinish == false){
            $msg = $print_msg[0]['msg'];
            return false;
        }

        return true;
    }

    /**
     * 检查发货单是否已经打印完成
     *
     * @author chenping<chenping@shopex.cn>
     * @version 2012-5-15 00:14
     * @param Array $dly 发货单信息 $dly
     * @param Array $msg 错误信息
     * @return TRUE:打印完成、FALSE:打印未完成
     **/
    public function checkPrintFinish($dly,&$msg){
        $deliCfgLib = kernel::single('wms_delivery_cfg');
        if($deliCfgLib->deliveryCfg != '') {
            $btncombi = $deliCfgLib->btnCombi($dly['deli_cfg']);
            list($stock,$delie) = explode('_',$btncombi);
            if(1 == $stock){
                if(($dly['print_status'] & 1) != 1) {
                    $msg[] = array('bn'=>$dly['logi_no'],'msg' => '备货单未打印');
                    return false;
                }
            }
            if(1 == $delie){
                if(($dly['print_status'] & 2) != 2){
                    $msg[] = array('bn' => $dly['logi_no'],'msg'=> '发货单未打印');
                    return false;
                }
            }
        }else{
            # 默认情况全部开启
            if(($dly['print_status'] & 1) != 1){      // 备货单未打印
                $msg[] = array('bn'=> $dly['logi_no'], 'msg'=> '备货单未打印');
                return false;
            }
            if(($dly['print_status'] & 2) != 2){     // 发货单未打印
                $msg[] = array('bn' => $dly['logi_no'], 'msg'=> '发货单未打印');
                return false;
            }
        }
        if(($dly['print_status'] & 4) != 4){   // 快递单未打印
            $msg[] = array('bn'=> $dly['logi_no'], 'msg'=> '快递单未打印');
            return false;
        }
        return true;
    }

    /**
     * 判断发货单号相关订单处理状态是否处于取消或异常
     *
     * @param bigint $dly_id 发货单号
     * @param boolean $msg_flag 是否直接中断显示消息
     * @param string $msg 错误信息
     * @return null
     */
    function checkOrderStatus($dly_id, $msg_flag=false, &$msg=NULL){
        if (!$dly_id) return false;

        if (!$this->existOrderStatus($dly_id)){
            $msg = "发货单已无法操作，请到订单处理中心处理";
            if ($msg_flag == false){
                echo $msg;
                exit("<script>parent.MessageBox.error('发货单已无法操作，请到订单处理中心处理!');</script>");
            }else{
                return false;
            }
        }
        if (!$this->existOrderPause($dly_id)){
            $msg = "发货单相关订单存在异常，请到订单处理中心处理";
            if ($msg_flag == false){
                echo $msg;
                exit("<script>parent.MessageBox.error('发货单相关订单存在异常，请到订单处理中心处理!');</script>");
            }else{
                return false;
            }
        }
        
        //判断订单在前端店铺是否可以发货
        if (!$this->checkOrderIsDelivery($dly_id, $error_msg))
        {
            $msg = $error_msg;
        
            if ($msg_flag == false){
                echo $msg;
                exit("<script>parent.MessageBox.error('发货单已无法操作，请到订单处理中心处理!');</script>");
            }else{
                return false;
            }
        }
        
        return true;
    }

    /**
     * 判断发货单状态是否存在异常
     *
     * @param int $dly_id
     * @return boolean
     */
    function existOrderStatus($dly_id){
        $ids = $dly_id;

        $sql = "SELECT COUNT(*) AS '_count'  FROM sdb_wms_delivery WHERE delivery_id in ($ids) AND (status=1 OR status=2 OR disabled='true')";
        $row = kernel::database()->select($sql);
        if ($row[0]['_count'] > 0){
            return false;
        }else {
            return true;
        }
    }

    /**
     *
     * 判断订单状态是否存在异常
     * @param int $dly_id
     */
    function existOrderPause($dly_id){
        $dlyObj = app::get('wms')->model('delivery');
        $omeExtOrdLib = kernel::single('ome_extint_order');

        $dlyInfo = $dlyObj->dump($dly_id,'outer_delivery_bn');
        if($dlyInfo){
            if($omeExtOrdLib->existOrderPause($dlyInfo['outer_delivery_bn'])){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
        return true;
    }

    /**
     * 是否允许发货
     * @param array $dly 发货单dump标准结构数据
     * @param string $logi_no 物流单号
     * @param number $weight 重量
     */
    function consignAllow($dly,$logi_no,$weight='0',$command = false){
        if (empty($logi_no)){
            return '请输入快递单号';
        }

        if($weight !== false){
            $weightSet = app::get('wms')->getConf('wms.delivery.weight');
            if (empty($weight) && $weightSet=='on'){
                return '请输入重量信息';
            }

            $minWeight = app::get('wms')->getConf('wms.delivery.minWeight');
            $maxWeight = app::get('wms')->getConf('wms.delivery.maxWeight');
            if($weight < $minWeight || $weight > $maxWeight){
                return '包裹重量超出系统设置范围！';
            }
        }

        $dlyObj = app::get('wms')->model('delivery');
        $dlyBillObj = app::get('wms')->model('delivery_bill');
        $deliveryBillLib = kernel::single('wms_delivery_bill');

        $primary = false;
        $secondary = false;
        //如果没有发货单信息，则根据物流单号识别是主单还是次单,并获取相关信息
        if (empty($dly)){
            $delivery_id = $deliveryBillLib->getDeliveryIdByPrimaryLogi($logi_no);
            if(!is_null($delivery_id)){
                $primary = true;
                $dly = $dlyObj->dump(array('delivery_id' => $delivery_id),'*',array('delivery_items'=>array('*')));
            }else{
                $delivery_id = $deliveryBillLib->getDeliveryIdBySecondaryLogi($logi_no);
                if(!is_null($delivery_id)){
                    $secondary = true;
                    $dly = $dlyObj->dump(array('delivery_id' => $delivery_id),'*',array('delivery_items'=>array('*')));
                }
            }
        }

        // [提货物流] delivery_model='pickup' 运单号后取，扫描的是发货单号
        if (empty($dly)) {
            $pickupDly = $dlyObj->dump(array('delivery_bn' => $logi_no, 'delivery_model' => 'pickup'), 'delivery_id');
            if ($pickupDly) {
                $primary = true;
                $delivery_id = $pickupDly['delivery_id'];
                $dly = $dlyObj->dump(array('delivery_id' => $delivery_id), '*', array('delivery_items' => array('*')));
            }
        }

        //[同城配]商家配送支持配送员手机号搜索
        if(empty($dly) && strlen($logi_no) == 11){
            $dly = $dlyObj->dump(array('deliveryman_mobile'=>$logi_no, 'process_status'=>array(2,3)), '*', array('delivery_items'=>array('*')));
            $delivery_id = $dly['delivery_id'];
            if($dly){
                $dlyBillInfo = $dlyBillObj->db_dump(array('delivery_id'=>$delivery_id), '*');
                $logi_no = $dlyBillInfo['logi_no'];
            }
        }
        
        //既不是主物流单号也不次物流单号
        if (!$dly){
            return '无此物流运单号';
        }

        //检查发货单明细的货品是否在盘点
        if($dly['delivery_items']){
            foreach($dly['delivery_items'] as $ik=>$iv){
                if(app::get('taoguaninventory')->is_installed()){
                $check_inventory = kernel::single('taoguaninventory_inventorylist')->checkproductoper($iv['product_id'],$dly['branch_id']);
                    if(!$check_inventory){
                        return '货号:'.$iv['bn'].'正在盘点,请将该货物放回指定区域';
                    }
               }
            }
        }

        //检查当前主物流单号是否已经发货
        if($primary){
            $tmp_status = $dlyBillObj->dump(array('delivery_id'=>$delivery_id,'logi_no'=>$logi_no,'type'=>1),'status');
            if($tmp_status['status'] == 1){
                return '此物流运单已发货';
            }
        }

        //检查当前次物流单号是否已经发货
        if($secondary){
            $tmp_status = $dlyBillObj->dump(array('delivery_id'=>$delivery_id,'logi_no'=>$logi_no,'type'=>2),'status');
            if($tmp_status['status'] == 1){
                return '此物流运单已发货';
            }
        }

        //检查整个发货单是否已经发货,下面已有逻辑
        /*
        $billfilter = array(
    		'status'=>1,
    		'delivery_id'=>$dly['delivery_id'],
        );

        $num = $dlyBillObj->count($billfilter);
        if($dly['delivery_logi_number'] >= $num && $dly['status'] == 3){
                return '此物流运单已发货';
        }
		*/

        if($command === false){
            //获取操作员管辖仓库
            $oBranch = app::get('ome')->model('branch');
            $is_super = kernel::single('desktop_user')->is_super();
            if (!$is_super){
               $branch_ids = $oBranch->getBranchByUser(true);
               if (!in_array($dly['branch_id'],$branch_ids))
                   return '快递单号不在您管辖的仓库范围内';
            }
        }

        //判断发货单相应订单是否有问题
        if (!$this->checkOrderStatus($dly['delivery_id'], true, $msg)){
            return $msg;
        }


        if (($dly['process_status'] & 2) != 2){
            return '此物流运单号对应的发货单未校验';
        }
        if ($dly['status'] == 3){
            return '此物流运单号对应的发货单已发货';
        }

        foreach ($dly['delivery_items'] as $item){
            if ($item['verify'] == 'false'){
                return '此物流运单号对应的发货单详情未校验完成';
            }

            # 库存验证(除了原样寄回发货单)
            if (in_array($dly['type'], kernel::single('wms_delivery_cfg')->getNormalCheckConsign())) {
                $re = $dlyObj->existStockIsPlus($item['product_id'],$item['number'],$item['item_id'],$dly['branch_id'],$err,$item['bn']);
                if (!$re){ return $err; }
            }
        }
    }

	/**
     * 补打物流单检查
     * @param array $dly 发货单dump标准结构数据
     * @param string $logi_no 物流单号
	 */
    function extLogiNoAllow($dly,$logi_no){
        if (empty($logi_no)){
            return '请输入快递单号';
        }

        $dlyObj = app::get('wms')->model('delivery');
        $dlyBillObj = app::get('wms')->model('delivery_bill');
        $deliveryBillLib = kernel::single('wms_delivery_bill');

        $primary = false;
        $secondary = false;
        //如果没有发货单信息，则根据物流单号识别是主单还是次单,并获取相关信息
        if (empty($dly)){
            $delivery_id = $deliveryBillLib->getDeliveryIdByPrimaryLogi($logi_no);
            if(!is_null($delivery_id)){
                $primary = true;
                $dly = $dlyObj->dump(array('delivery_id' => $delivery_id),'*',array('delivery_items'=>array('*')));
            }else{
                $delivery_id = $deliveryBillLib->getDeliveryIdBySecondaryLogi($logi_no);
                if(!is_null($delivery_id)){
                    $secondary = true;
                    $dly = $dlyObj->dump(array('delivery_id' => $delivery_id),'*',array('delivery_items'=>array('*')));
                }
            }
        }

        if (!$dly){
            return '无此物流运单号';
        }

        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
           $branch_ids = $oBranch->getBranchByUser(true);
           if (!in_array($dly['branch_id'],$branch_ids))
               return '快递单号不在您管辖的仓库范围内';
        }

        //判断发货单相应订单是否有问题
        if (!$this->checkOrderStatus($dly['delivery_id'], true, $msg)){
            return $msg;
        }

        if ($dly['process_status'] & 2 != 2){
            return '此物流运单号对应的发货单未校验';
        }

        if ($dly['status'] == 3){
            return '此物流运单号对应的发货单已发货';
        }

        foreach ($dly['delivery_items'] as $item){

            if ($item['verify'] == 'false'){
                return '此物流运单号对应的发货单详情未校验完成';
            }
            $re = $dlyObj->existStockIsPlus($item['product_id'],$item['number'],$item['item_id'],$dly['branch_id'],$err,$item['bn']);
            if (!$re){
                return $err;
            }
        }
    }

    /**
     *
     * 检测主快递单是否有子快递单
     */
    function unDlyChildBills($delivery_id){
        $dlyBillObj = app::get('wms')->model('delivery_bill');
        $dlyBill = $dlyBillObj->getList('*',array('delivery_id|nequal'=>$delivery_id,'status'=>0,'type'=>2));
        if($dlyBill){
            return $dlyBill;
        }else{
            return false;
        }
    }

    /**
     *
     * 判断是否已有此物流单号
     */
    function existExpressNoBill($logi_no, $dly_id=0, $billid=0){
        $dlyBillObj = app::get('wms')->model('delivery_bill');
        if($dly_id > 0){
            $dlyInfo = $dlyBillObj->getList('*',array('delivery_id|noequal'=>$dly_id,'logi_no'=>$logi_no));
        }

        if($billid > 0){
            $dlyBillInfo = $dlyBillObj->getList('*',array('b_id|noequal'=>$billid,'logi_no'=>$logi_no));
        }

        if ($dlyInfo || $dlyBillInfo) {
            return true;
        }else{
            return false;
        }
    }

    //发货单详情页主单判断是否重复被占用
    function existExpressNoBillByMain($logi_no, $dly_id=0){
        $dlyBillObj = app::get('wms')->model('delivery_bill');
        if($dly_id > 0){
            $other_dly = $dlyBillObj->getList('*',array('delivery_id|noequal'=>$dly_id,'logi_no'=>$logi_no));
            $child_bill = $dlyBillObj->getList('*',array('delivery_id'=>$dly_id,'logi_no'=>$logi_no,'type'=>2));
        }

        if ($other_dly || $child_bill) {
            return true;
        }else{
            return false;
        }
    }
    
    /**
     * 判断订单订单在前端是否可以发货
     * 现只支持查询“拼多多”平台店铺，其它店铺会返回'rsp'=>'succ'
     * 
     * @param int $dly_id
     * @return Array
     */
    function checkOrderIsDelivery($dly_id, &$error_msg)
    {
        $dlyObj    = app::get('wms')->model('delivery');
        $dlyInfo   = $dlyObj->dump($dly_id, 'outer_delivery_bn, shop_id, delivery_bn');
        
        if(empty($dlyInfo))
        {
            $error_msg    = '发货单不存在';
            return false;
        }
        
        //只有"拼多多"平台才查询订单状态，其它店铺直接返回true
        $shopObj    = app::get('ome')->model('shop');
        $shopInfo   = $shopObj->dump(array('shop_id'=>$dlyInfo['shop_id']), 'shop_type');
        if($shopInfo['shop_type'] != 'pinduoduo')
        {
            return true;
        }
        
        //订单信息
        $orderObj = app::get('ome')->model('orders');
        
        $sql      = "SELECT dord.order_id FROM sdb_ome_delivery_order AS dord LEFT JOIN sdb_ome_delivery AS d
                     ON dord.delivery_id=d.delivery_id WHERE d.delivery_bn='". $dlyInfo['outer_delivery_bn'] ."'";
        $temp_orer    = $orderObj->db->select($sql);
        
        if(empty($temp_orer))
        {
            $error_msg    = '订单号不存在';
            return false;
        }
        
        $order_ids    = array();
        foreach ($temp_orer as $key => $val)
        {
            $order_ids[]    = $val['order_id'];
        }
        
        $order_bn_list = array();
        $temp_orer     = $orderObj->getList('order_bn', array('order_id'=>$order_ids));
        foreach ($temp_orer as $key => $val)
        {
            $order_bn_list[]    = $val['order_bn'];
        }
        
        $isDeliveryOnShop    = kernel::single('ome_service_order')->isDeliveryOnShop($order_bn_list, $dlyInfo['shop_id']);
        if ($isDeliveryOnShop['rsp'] == 'fail')
        {
            $error_msg    = sprintf('发货单【%s】对应前端订单不能发货：%s', $dlyInfo['delivery_bn'], $isDeliveryOnShop['msg']);
            
            return false;
        }
        
        return true;
    }
}