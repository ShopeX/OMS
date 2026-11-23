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

class console_ctl_admin_delivery_refuse extends desktop_controller {

    var $name = "拒收服务";
    var $workground = "console_center";


    /**
     *
     * 拒收退货单列表
     */
    function index(){

        $params = array(
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
                $this->page("admin/delivery/refuse_check.html");
            }else{
                $this->process($delivery['delivery_id'],$orderIds);
            }
        }else{
            $this->page("admin/delivery/refuse_check.html");
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
        $this->page("admin/delivery/refuse_process_show.html");
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

        $reshipObj = app::get('ome')->model('reship');
        $deliveryObj = app::get('ome')->model('delivery');
        $orderObj = app::get('ome')->model('orders');
        $operationLogObj = app::get('ome')->model('operation_log');
        $shopObj = app::get('ome')->model('shop');

        $deliveryInfo = $deliveryObj->dump($delivery_id);
        $shopInfo = $shopObj->dump(array('shop_id'=>$deliveryInfo['shop_id']),'node_type,node_id,delivery_mode');
        $c2c_shop_type = ome_shop_type::shop_list();

        $op_id = kernel::single('desktop_user')->get_id();

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
                'return_logi_name'=> $deliveryInfo['logi_name'],
                'return_type'=> 'refuse',
                'return_logi_no'=> $deliveryInfo['logi_no'],
                'delivery_id'=> $deliveryInfo['delivery_id'],
                'logi_name'=> $deliveryInfo['logi_name'],
                'logi_no'=> $deliveryInfo['logi_no'],
                'logi_id' => $deliveryInfo['logi_id'],
                'delivery'=> $deliveryInfo['delivery'],
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
                //发起回传开始
                #通知前端按仓库重新整下
                $reship_data = $reshipData['reship_items'];
                $Odly_corp = app::get('ome')->model('dly_corp');
                $dly_corp = $Odly_corp->dump($deliveryInfo['logi_id'],'type');
                $tmp = array();
                foreach($reship_data as $reship_data){
                    if(isset($tmp[$reship_data['branch_id']])){
                        $tmp[$reship_data['branch_id']]['item'][] = $reship_data;
                    }else{
                        $tmp[$reship_data['branch_id']]['item'][] = $reship_data;
                        $tmp[$reship_data['branch_id']]['reship_bn'] = $reshipData['reship_bn'];
                        $tmp[$reship_data['branch_id']]['order_bn'] = $orderdata['order_bn'];
                        $tmp[$reship_data['branch_id']]['logi_no'] = $reshipData['logi_no'];
                        $tmp[$reship_data['branch_id']]['logistics'] = $dly_corp['type'];
                    }
                }
                foreach($tmp as $tk=>$tv){
                    $wms_id = kernel::single('ome_branch')->getWmsIdById($tk);
                    kernel::single('wms_event_trigger_deliveryrefuse')->updateStatus($wms_id, $tv, true);
                }
                #发起回传结束
                $orderObj->cancel($orderid,'订单拒收自动取消',true,$mod, false);
            }else{

                $this->end(false, app::get('base')->_('发货拒收确认失败'));
            }
        }
        $this->end(true, app::get('base')->_('发货拒收确认成功'));
    }
}
?>