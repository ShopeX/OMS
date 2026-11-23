<?php
class ome_ctl_admin_delivery_refuse extends desktop_controller {

    var $name = "拒收服务";
    var $workground = "wms_center";


    /**
     *
     * 拒收退货单列表
     */
    function index(){

        #如果没有导出权限，则屏蔽导出按钮
        $is_export = kernel::single('desktop_user')->has_permission('aftersale_rchange_export');
        $params = array(
            'title' => '拒收单',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_import'=>false,
            'use_buildin_export'=>$is_export,
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

            $deliveryObj = $this->app->model('delivery');
            $delivery = $deliveryObj->dump(array('logi_no'=>$tmplogiNo,'parent_id'=>0,'is_cod'=>'true','status'=>'succ','process'=>'true'),'delivery_id');
            if(!$delivery['delivery_id'] && !$has_error){
                $has_error = true;
                $msg = '没有找到对应的物流单号';
            }

            $orderIds = $deliveryObj->getOrderIdByDeliveryId($delivery['delivery_id']);
            if(count($orderIds) < 1 && !$has_error){
                $has_error = true;
                $msg = '没有找到对应的物流单号的订单';
            }

            $orderObj = $this->app->model('orders');
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
                $this->process($delivery['delivery_id'],$orderIds);
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
        $deliveryObj = $this->app->model('delivery');
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

        $productIds = $_POST['product_id'];
        $branches = array();
        foreach($productIds as $k => $product_id){
            $branches[$product_id] = $_POST['instock_branch'][$k];
        }

        $reshipObj = $this->app->model('reship');
        $deliveryObj = $this->app->model('delivery');
        $orderObj = $this->app->model('orders');
        $operationLogObj = $this->app->model('operation_log');
        $shopObj = $this->app->model('shop');

        $deliveryInfo = $deliveryObj->dump($delivery_id);
        $shopInfo = $shopObj->dump(array('shop_id'=>$deliveryInfo['shop_id']),'node_type,node_id,delivery_mode');
        $c2c_shop_type = ome_shop_type::shop_list();

        $op_id = kernel::single('desktop_user')->get_id();

        $orderIds = array_map('intval', explode(',',$orderIdString));

        foreach((array)$orderIds as $orderid){
            $reshipData = array();
            $orderItems = array();

            $orderdata = $orderObj->dump($orderid,'ship_status');
            $orderItems = $orderObj->getItemList($orderid);
             if ($orderdata['ship_status']!='1'){
            
                $this->end(false, app::get('base')->_('订单发货状态不可以再拒收'));
            }
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
        			    'branch_id' => $branches[$orderitem['product_id']],
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

                //发货单关联订单sendnum扣减
                foreach($orderItems as $orderitem){
                    $orderObj->db->exec('UPDATE sdb_ome_order_items SET sendnum=0 WHERE order_id='.$orderid.' AND bn=\''.$orderitem['bn'].'\'');
                }

                //订单发货状态变更
                $orderObj->db->exec('UPDATE sdb_ome_orders SET ship_status=\'4\' WHERE order_id='.$orderid);

                //增加拒收退货入库明细
                kernel::single('ome_delivery_refuse')->do_iostock($reshipData['reship_id'],1,$msg);

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
