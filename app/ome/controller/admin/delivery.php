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
class ome_ctl_admin_delivery extends desktop_controller{
    const URGENT_LABEL_CODE = 'SOMS_URGENT_SHIP';
    var $name = "发货单";
    var $workground = "console_center";

    function _views(){
        $mdl_delivery = $this->app->model('delivery');
        $baseFilter = array(
            'type' => 'normal',
            'pause' => 'false',
            'parent_id' => 0,
            'disabled' => 'false',
            'status' => array('ready','progress','succ')
        );
        $sub_menu = array(
            0 => array('label' => app::get('base')->_('全部'), 'filter' => $baseFilter, 'optional' => false),
            1 => array('label' => app::get('base')->_('加急发货'), 'filter' => array_merge($baseFilter, array('delivery_label_code' => self::URGENT_LABEL_CODE)), 'optional' => false),
        );
        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = $v['filter'];
            $sub_menu[$k]['addon'] = $mdl_delivery->count($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$k;
        }
        return $sub_menu;
    }

    function index(){
        $filter = array(
            'type' => 'normal',
            'pause' => 'false',
            'parent_id' => 0,
            'disabled' => 'false',
            'status' => array('ready','progress','succ')
        );
        
        if(isset($_POST['status']) && ($_POST['status']!='')){
            $filter['status'] = $_POST['status'];
        }
        $actions = array();
        $user = kernel::single('desktop_user');
        if($user->has_permission('console_process_receipts_print_export')){

            $actions[] =  array(
            'label'=>'导出',
            'submit'=>'index.php?app=omedlyexport&ctl=ome_delivery&act=index&action=export',
            'target'=>'dialog::{width:600,height:300,title:\'导出\'}'
            );
       }
        $this->finder('ome_mdl_delivery',array(
            'title' => '发货单',
            'base_filter' => $filter,
            'actions' => $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'use_view_tab'=>true,
       ));
    }

    function reback(){
        if ($_GET['order_id']){
            $orderObj = app::get('ome')->model('orders');
            $orders = $orderObj->dump(array('order_id'=>$_GET['order_id']),'order_bn');
            if($orders) $this->pagedata['order_bn'] = $orders['order_bn'];
        }
        $this->singlepage('admin/delivery/reback_delivery.html');
    }

    function back()
    {
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        
        $Objdly  = app::get('ome')->model('delivery');
        $OiObj  = app::get('ome')->model('delivery_items');
        $ObjdlyOrder  = app::get('ome')->model('delivery_order');
        $orderObj = app::get('ome')->model('orders');
        
        $select_type = 'order_bn';
        $finder_id = $_GET['_finder']['finder_id'];
        
        //order
        $orders = $orderObj->dump(array('order_id'=>intval($_GET['order_id'])),'order_id');
        if (empty($orders)) {
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('此订单号不存在！！！');opener.finderGroup['{$finder_id}'].refresh.delay(100,opener.finderGroup['{$finder_id}']);window.close();</script>";
            exit;
        }
        $order_id = $orders['order_id'];

        $deliveryids = $Objdly->getDeliverIdByOrderId($order_id);
        if (!$deliveryids) {
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('订单号不存在对应发货单！');opener.finderGroup['{$finder_id}'].refresh.delay(100,opener.finderGroup['{$finder_id}']);window.close();</script>";
            exit;
        }
        $delivery_list = $Objdly->getList('*',array('delivery_id'=>$deliveryids,'process'=>'false'));
        if(empty($delivery_list)){
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('没有该单号的发货单！！！');opener.finderGroup['{$finder_id}'].refresh.delay(100,opener.finderGroup['{$finder_id}']);window.close();</script>";
            exit;
        }
        $detail = array();
        foreach ($delivery_list as $delivery ) {
            $items = $OiObj->getList('*',array('delivery_id'=>$delivery['delivery_id']));
            #获取订单
            $order_bn = $ObjdlyOrder->getOrderInfo('order_bn',$delivery['delivery_id']);
            foreach($items as $k=>$value)
            {
                $barcode_val    = $basicMaterialBarcode->getBarcodeById($value['product_id']);
                
                $items[$k]['barcode'] = $barcode_val;
            }
            if(($delivery['stock_status']=='true') || ($delivery['deliv_status']=='true') || ($delivery['expre_status']=='true')){
                $this->pagedata['is_confirm'] = true;
            }
            if($delivery['is_bind']=='true'){
              $countinfo = $Objdly->getList('count(parent_id)',array('parent_id'=>$delivery['delivery_id']));
              $count = $countinfo[0]['count(parent_id)'];
              $this->pagedata['height'] = 372+26*$count;
            }
                $consignee['name'] = $delivery['ship_name'];
                $consignee['area'] = $delivery['ship_area'];
                $consignee['province'] = $delivery['ship_province'];
                $consignee['city'] = $delivery['ship_name'];
                $consignee['district'] = $delivery['ship_district'];
                $consignee['addr'] = $delivery['ship_addr'];
                $consignee['zip'] = $delivery['ship_zip'];
                $consignee['telephone'] = $delivery['ship_telephone'];
                $consignee['mobile'] = $delivery['ship_mobile'];
                $consignee['email'] = $delivery['ship_email'];
                $consignee['r_time'] = $delivery['ship_name'];
            $detail[] = array(
                'items'=>$items,
                'consignee'=>$consignee,
                'delivery_bn'=>$delivery['delivery_bn'],
                'delivery'=>$delivery['delivery'],
                'logi_name'=>$delivery['logi_name'],
                'logi_no'=>$delivery['logi_no'],
                'weight'=>$delivery['weight'],
                 'delivery_id'=>$delivery['delivery_id'],
            
            );
        }
        $this->pagedata['select_type'] = $select_type;
        $this->pagedata['bn_select']   = $_POST['bn_select'];
         $this->pagedata['orders'] = $orders;
        $this->pagedata['detail']      = $detail;
        $this->pagedata['find_id'] = $_GET['find_id'];
        $this->singlepage('admin/delivery/reback_delivery.html');
    }

    /**
     * 打回操作
     *
     */
    function doReback(){
        $rs = array('rsp'=>'succ','msg'=>'撤销成功');
        $autohide = array('autohide'=>3000);
        $memo = $_POST['memo'];
        $Objdly  = app::get('ome')->model('delivery');
        $delivery_id = $_POST['delivery_id'];
        $flag = $_POST['flag'];
        if ($delivery_id) {
            if ($flag == 'OK') {//合单时拆分
                foreach ($delivery_id as $deliveryid ) {
                    $result = $Objdly->splitDelivery($deliveryid, $_POST['id'], false);
      
                    if ($result) {
                        $Objdly->rebackDelivery($_POST['id'], $memo);
                    }else{
                       $rs = array('rsp'=>'fail','msg'=>'撤销失败');
                    }
                }
            }else{
                $result = $Objdly->rebackDelivery($delivery_id, $memo);
                if (!$result) {
                    $rs = array('rsp'=>'fail','msg'=>'撤销失败');
                }
            }
        }
        
        
        echo json_encode($rs);
    }

 

    /**
     * 填写打回备注
     *
     * @param bigint $dly_id
     */
    function showmemo(){
        $deliveryObj  = $this->app->model('delivery');
        $dly_id = $_GET['delivery_id'];

        $dly          = $deliveryObj->getlist('delivery_id,is_bind,delivery_bn,status,process',array('delivery_id'=>$dly_id));
        $idd = array();
        foreach ($dly as $dk=>$dy ) {
            if ($dy['process'] == 'true' || in_array($dy['status'],array('failed', 'cancel', 'back', 'succ','return_back'))){
            echo '<script>alert("当前发货单已发货或者已取消不可撤销!");</script>';
            exit;
            
            }
            if ($dy['is_bind'] == 'true'){
                $ids = $deliveryObj->getItemsByParentId($dy['delivery_id'], 'array');
                $returnids = implode(',', $ids);
                
                if ($ids){
                    foreach ($ids as $v){
                        $delivery = $deliveryObj->dump($v, 'delivery_bn');
                        $order_id = $deliveryObj->getOrderBnbyDeliveryId($v);
                        $idd[$v]['delivery_bn'] = $delivery['delivery_bn'];
                        $idd[$v]['order_bn'] = $order_id['order_bn'];
                        $idd[$v]['delivery_id'] = $v;
                        $dly[$dk]['idd'] =$idd; 
                    }
                }
                
            }
        }
 
        $this->pagedata['returnids'] = $returnids;
        $this->pagedata['ids'] = $ids;
        $this->pagedata['idd'] = $idd;
        $this->pagedata['dly'] = $dly;
        $this->pagedata['find_id'] = $_GET['find_id'];
        $this->display("admin/delivery/delivery_showmemo.html");
    }

    /**
     * [拆单]编辑订单时,显示发货单基础物料详情
     */
    public function show_delivery_items()
    {
        $dly_id    = intval($_REQUEST['id']);
        if(empty($dly_id))
        {
            die('无效操作！');
        }
        
        $basicMaterialObj = app::get('material')->model('basic_material');
        $materialExtObj   = app::get('material')->model('basic_material_ext');
        
        $dlyObj = app::get('ome')->model('delivery');
        
        $items  = $dlyObj->getItemsByDeliveryId($dly_id);
        
        /*获取货品优惠金额*/
        $dlyorderObj = app::get('ome')->model('delivery_order');
        $dly_order = $dlyorderObj->getlist('*',array('delivery_id'=>$dly_id),0,-1);
        
        $pmt_orders = $dlyObj->getPmt_price($dly_order);
        $sale_orders = $dlyObj->getsale_price($dly_order);
        
        $pmt_order = array();
        if($items)
        {
            foreach ($items as $key => $item)
            {
                //将商品的显示名称改为后台的显示名称
                $productInfo    = $basicMaterialObj->dump(array('material_bn'=>$items[$key]['bn']), 'bm_id, material_bn, material_name');
                
                $basicMaterialExt    = $materialExtObj->dump(array('bm_id'=>$productInfo['bm_id']), 'specifications');
                
                $items[$key]['spec_info'] = $basicMaterialExt['specifications'];
                $items[$key]['product_name'] = $productInfo['material_name'];
                
                $items[$key]['pmt_price'] = $pmt_order[$items[$key]['bn']]['pmt_price'];
                $items[$key]['sale_price'] = ($sale_orders[$items[$key]['bn']]*$item['number'])-$pmt_order[$items[$key]['bn']]['pmt_price'];
                
                $items[$key]['price'] = $sale_orders[$items[$key]['bn']];
            }
        }
        $this->pagedata['items'] = $items;
        
        $this->singlepage('admin/delivery/show_delivery_items.html');
    }
}
?>
