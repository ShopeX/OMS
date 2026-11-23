<?php
class wms_ctl_admin_delivery_refuse extends desktop_controller {

    //var $name = "拒收服务";
    var $workground = "wms_center";
    var $defaultWorkground = 'wms_center';

    /**
     *
     * 拒收退货单列表
     */
    function index(){

        $params = array(
            'title' => '拒收单',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
        );

        $params['base_filter']['return_type'] = array('refuse');
        $this->finder ( 'ome_mdl_reship_refuse' , $params );
    }

    /**
     * 发货拒收默认页
     */
    function check(){
        if($_POST['logi_no']){
            $tmplogiNo = trim($_POST['logi_no']);
            $has_error = false;

            $deliveryObj = app::get('ome')->model('delivery');
            $wmsDlyObj = app::get('wms')->model('delivery');
            
            $delivery = $deliveryObj->dump(array('logi_no'=>$tmplogiNo,'parent_id'=>0,'is_cod'=>'true','status'=>'succ','process'=>'true'),'delivery_id,delivery_bn');
            $wmsObj = app::get('wms')->model('delivery');
            $wmsDlyBillObj = app::get('wms')->model('delivery_bill');
            $wmsdelivery = $wmsObj->dump(array('outer_delivery_bn'=>$delivery['delivery_bn']),'delivery_bn');
            if (!$wmsdelivery['delivery_bn']) {
                $has_error = true;
                $msg = '非自建仓发货单不可以在此操作!';
            }
            $wms_deliveryInfo = $wmsDlyBillObj->dump(array('logi_no'=>$tmplogiNo,'type'=>1,'status'=>1),'delivery_id');
            if(!$wms_deliveryInfo['delivery_id'] && !$has_error){
                $has_error = true;
                $msg = '没有找到对应的物流单号';
            }

            $ome_deliveryId = $wmsDlyObj->getOuterIdById($wms_deliveryInfo['delivery_id']);
            if(!$ome_deliveryId && !$has_error){
                $has_error = true;
                $msg = '没有找到对应的物流单号';
            }

            $orderIds = $deliveryObj->getOrderIdByDeliveryId($ome_deliveryId);
            if(is_array($orderIds) && count($orderIds) < 1 && !$has_error){
                $has_error = true;
                $msg = '没有找到对应的物流单号的订单';
            }

            $orderObj = app::get('ome')->model('orders');
            foreach((array)$orderIds as $orderid){

                $order = $orderObj->dump(array('order_id'=>$orderid),'pay_status,ship_status');

                if($order['ship_status'] != 1 && !$has_error){
                    $has_error = true;
                    $msg = '当前发货状态的订单无法做拒收处理';
                    break;
                }

                if($order['pay_status'] != 0 && !$has_error){
                    $has_error = true;
                    $msg = '当前付款状态的订单无法做拒收处理';
                    break;
                }
            }

            if($has_error){
                $this->pagedata['error_msg'] = $msg;
                $this->page("admin/delivery/refuse/check.html");
            }else{
                $this->process($ome_deliveryId,$orderIds);
            }
        }else{
            $this->page("admin/delivery/refuse/check.html");
        }
    }

    /**
     * 显示待拒收发货单明细及相关信息
     *
     **/
    function process($deliveryId,$orderIds)
    {
        $deliveryObj = app::get('ome')->model('delivery');
        $deliveryInfo = $deliveryObj->dump($deliveryId);
        $deliveryItems = $deliveryObj->getItemsByDeliveryId($deliveryId);

        $branchObj = app::get('ome')->model('branch');
        $delivery_branch = $branchObj->Get_name($deliveryInfo['branch_id']);
        $branch_lists = $branchObj->getAllBranchs('branch_id,name');

        $this->pagedata['info'] = $deliveryInfo;
        $this->pagedata['items'] = $deliveryItems;
        $this->pagedata['delivery_branch'] = $delivery_branch;
        $this->pagedata['branch_lists'] = $branch_lists;
        $this->pagedata['deliveryId'] = $deliveryId;
        $this->pagedata['orderIds'] = implode(",",$orderIds);
        $this->page("admin/delivery/refuse/process_show.html");
    }

    /**
     * 执行发货拒收的具体数据处理
     */
    function doprocess(){
        $this->begin();

        $delivery_id = intval($_POST['delivery_id']);
        $orderIdString = $_POST['order_ids'];
        $reshipObj = app::get('ome')->model('reship');
        $deliveryObj = app::get('ome')->model('delivery');
        $orderObj = app::get('ome')->model('orders');
        $operationLogObj = app::get('ome')->model('operation_log');
        $shopObj = app::get('ome')->model('shop');

        $deliveryInfo = $deliveryObj->dump($delivery_id);
        $shopInfo = $shopObj->dump(array('shop_id'=>$deliveryInfo['shop_id']),'node_type,node_id,delivery_mode');
        $c2c_shop_type = ome_shop_type::shop_list();

        $op_id = kernel::single('desktop_user')->get_id();
        $branch_id = intval($_POST['instock_branch']);
        $orderIds = array_map('intval', explode(',',$orderIdString));
        
        foreach((array)$orderIds as $orderid){
            $reshipData = array();
            $orderItems = array();

            $orderdata = $orderObj->dump($orderid);
            $orderItems = $orderObj->getItemList($orderid);

            $reshipData = array(
        		'status' => 'succ',
                'order_id'=> $orderid,
                'member_id'=> $deliveryInfo['member_id'],
                'return_logi_name'=> $deliveryInfo['logi_id'],
                'return_type'=> 'refuse',
                'return_logi_no'=> $deliveryInfo['logi_no'],
                'logi_name'=> $deliveryInfo['logi_name'],
                'logi_no'=> $deliveryInfo['logi_no'],
                'logi_id' => $deliveryInfo['logi_id'],
                'delivery'=> $deliveryInfo['delivery'],
                'delivery_id'=> $deliveryInfo['delivery_id'],
                'memo'=> '',
                'status'=>'succ',
                'is_check'=>7,
                'op_id'=>$op_id,
            	't_begin'=>time(),
                't_end'=>time(),
                'shop_id'=>$deliveryInfo['shop_id'],
                'reship_bn'=>$reshipObj->gen_id(),
    			'ship_name'=>$deliveryInfo['consignee']['name'],
                'ship_addr'=>$deliveryInfo['consignee']['addr'],
                'ship_zip'=>$deliveryInfo['consignee']['zip'],
                'ship_tel'=>$deliveryInfo['consignee']['telephone'],
                'ship_mobile'=>$deliveryInfo['consignee']['mobile'],
                'ship_email'=>$deliveryInfo['consignee']['email'],
                'ship_area'=>$deliveryInfo['consignee']['area'],
                'branch_id'=>$branch_id,
            );

            // 经销店铺的单据，delivery_mode冗余到售后申请表
            if ($shopInfo['delivery_mode'] == 'jingxiao') {
                $reshipData['delivery_mode'] = $shopInfo['delivery_mode'];
            }

            foreach($orderItems as $k =>$orderitem){
                if($orderitem['delete'] == 'false'){
        			$reshipData['reship_items'][$k] = array(
        				'bn' => $orderitem['bn'],
        				'product_name' => $orderitem['name'],
        			    'product_id' => $orderitem['product_id'],
        				'num' => $orderitem['quantity'],
        			    'branch_id' => $branch_id,
        			    'op_id' => $op_id,
        			    'return_type' => 'refuse'
                    );
                }
            }

            //生成退货单
            if($reshipObj->save($reshipData)){
                //退货单创建 API
                if(!empty($shopInfo['node_id']) && !in_array($shopInfo['node_type'],$c2c_shop_type)){
                    foreach(kernel::servicelist('service.reship') as $object=>$instance){
                        if(method_exists($instance,'reship')){
                            $instance->reship($reshipData['reship_id']);
                        }
                    }
                }

                //serial number return process
                //Load Lib
                $dlyItemsSerialObj    = app::get('wms')->model('delivery_items_serial');
                $dlyItemsSerialLib    = kernel::single('wms_receipt_dlyitemsserial');
                $wmsDelivery       = app::get('wms')->model('delivery');

                //wms dly info
                $wms_delivery    = $wmsDelivery->dump(array('outer_delivery_bn'=>$deliveryInfo['delivery_bn']), 'delivery_id, delivery_bn, branch_id');

                //serial info
                $items = $dlyItemsSerialObj->getList('bn,serial_number', array('delivery_id'=>$wms_delivery['delivery_id']), 0, -1);
                if($items){
                    $history_serial = array();
                    foreach($items as $item){
                        //params
                        $serialItem = array(
                            'serial_number' => $item['serial_number'],
                            'reship_id' => $reshipData['reship_id'],
                            'reship_bn' => $reshipData['reship_bn'],
                            'branch_id' => $branch_id,
                            'bn' => $item['bn'],
                        );

                        $rs = $dlyItemsSerialLib->returnProduct($serialItem, $err_msg, $return_serial);
                        if(!$rs){

                            $this->end(false, app::get('base')->_('唯一码退入失败'));
                        }else{
                            $history_serial[] = $return_serial;
                        }
                    }

                    //write history serial
                    kernel::single('ome_receipt_dlyitemsserial')->returnProduct($history_serial, $msg);
                }

                //storagelife return process
                $dlyItemsStorageLifeObj    = app::get('wms')->model('delivery_items_storage_life');
                $dlyItemsStorageLifeLib    = kernel::single('wms_receipt_dlyitemsstoragelife');

                //storagelife info
                $items = $dlyItemsStorageLifeObj->getList('bm_id,expire_bn,number', array('delivery_id'=>$wms_delivery['delivery_id']), 0, -1);
                if($items){
                    $history_storagelife = array();
                    foreach($items as $item){
                        //params
                        $storagelifeItem = array(
                            'expire_bn' => $item['expire_bn'],
                            'nums' => $item['number'],
                            'bill_id' => $reshipData['reship_id'],
                            'bill_bn' => $reshipData['reship_bn'],
                            'branch_id' => $branch_id,
                            'bm_id' => $item['bm_id'],
                            'old_branch_id' => $wms_delivery['branch_id'],
                            'bill_type' => '32',
                            'bill_io_type' => '1',
                        );

                        $rs = $dlyItemsStorageLifeLib->returnProduct($storagelifeItem, $err_msg, $return_storagelife);
                        if(!$rs){

                            $this->end(false, app::get('base')->_('保质期批次退入失败'));
                        }else{
                            $history_storagelife[] = $return_storagelife;
                        }
                    }

                    //write history storagelife
                    kernel::single('ome_receipt_dlyitemsstoragelife')->returnProduct($history_storagelife, $msg);
                }

                //发货单关联订单sendnum扣减
                foreach($orderItems as $orderitem){
                    if($orderitem['delete'] == 'false'){
                        $orderObj->db->exec('UPDATE sdb_ome_order_items SET return_num=return_num+ '.$orderitem['quantity'].' WHERE order_id='.$orderid.' AND bn=\''.$orderitem['bn'].'\' AND obj_id='.$orderitem['obj_id']);
                    }
                }

                //订单发货状态变更
                $orderObj->db->exec('UPDATE sdb_ome_orders SET ship_status=\'4\' WHERE order_id='.$orderid);

                //增加拒收退货入库明细
                $iostock_result = kernel::single('ome_delivery_refuse')->do_iostock($reshipData['reship_id'],1,$msg);
                //
                if(!$iostock_result){

                    $this->end(false, app::get('base')->_('拒收明细生成失败!'));
                }
                //生成负销售单

                //订单添加相应的操作日志
                $operationLogObj->write_log('order_refuse@ome', $orderid, "货到付款订单发货后买家拒收，订单做退货处理");

                //订单自动取消
                $mod = 'sync';
                if(!$shopInfo['node_id'] || in_array($shopInfo['node_type'],$c2c_shop_type) || $orderdata['source'] == 'local'){
                    $mod = 'async';
                }
                $orderObj->cancel($orderid,'订单拒收自动取消',true,$mod, false);
            }else{

                $this->end(false, app::get('base')->_('发货拒收确认失败'));
            }
        }
        $this->end(true, app::get('base')->_('发货拒收确认成功'));
    }

    
  
}
