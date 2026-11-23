<?php
class ome_ctl_admin_delivery_back extends desktop_controller {

    var $name = "退回服务";
    var $workground = "aftersale_center";

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

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $params['base_filter']['org_id'] = $organization_permissions;
        }

        $actions = array();
        if ($_GET['view'] == '1'){
            $actions[] =
                        array('label' => '发送至第三方',
                                'submit' => 'index.php?app=ome&ctl=admin_delivery_back&act=batch_sync',
                                'confirm' => '你确定要对勾选的追回单发送至第三方吗？',
                                'target' => 'refresh');

        }
        $params['actions'] = $actions;
        $this->finder ( 'ome_mdl_reship_refuse' , $params );
    }



    //未发货 已发货 全部
    function _views(){
        $oDelivery = app::get('ome')->model('reship');
        $base_filter = array('return_type' => 'refuse');

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $base_filter['org_id'] = $organization_permissions;
        }

        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'filter'=>$base_filter,'optional'=>false),
            1 => array('label'=>app::get('base')->_('审核成功'),'filter'=>array('is_check'=>array('1')),'optional'=>false),
            2 => array('label'=>app::get('base')->_('待确认'),'filter'=>array('is_check'=>array('11')),'optional'=>false),
            3 => array('label'=>app::get('base')->_('已完成'),'filter'=>array('is_check'=>array('7')),'optional'=>false),
        );

        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }

            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $oDelivery->count($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=ome&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$i++;
        }

        return $sub_menu;
    }
    /**
     * 发货拒收默认页
     */
    function check(){
        if($_POST['order_bn']){
            
            $this->pagedata['error_msg'] = "此功能已下架";
            $this->page("admin/delivery/return/check.html");
            return;
            
            $orderObj = app::get('ome')->model('orders');
            $deliveryObj = app::get('ome')->model('delivery');
            $dlyItemsObj = app::get('ome')->model('delivery_items');
            $branchObj = app::get('ome')->model('branch');
            
            $refuseLib = kernel::single('ome_delivery_refuse');
            
            //post
            $order_bn = trim($_POST['order_bn']);
            $has_error = false;
            
            //result
            $error_msg = '';
            $result = $refuseLib->getRefuseByOrderBn($order_bn, $error_msg);
            if($result){
                //追回列表展示
                
                //branch
                $branchList = array();
                $tempData = $branchObj->getAllBranchs('branch_id,name');
                foreach ($tempData as $key => $val){
                    $branchList[$val['branch_id']] = $val;
                }
                
                //订单信息
                $orderInfo = $orderObj->dump(array('order_bn'=>$order_bn), '*');
                $this->pagedata['order'] = $orderInfo;
                
                //发货单列表(todo:一个订单有拆分多个发货单的情况)
                $dlyList = array();
                foreach ($result as $key => $val){
                    $delivery_id = $val['delivery_id'];
                    
                    //发货单信息
                    $dlyInfo = $deliveryObj->dump(array('delivery_id'=>$delivery_id), 'delivery_id, delivery_bn, logi_id, logi_name, logi_no, branch_id');
                    $dlyInfo['branch_name'] = $branchList[$dlyInfo['branch_id']]['name'];
                    
                    //发货单明细
                    $dlyInfo['items'] = $dlyItemsObj->getList('*', array('delivery_id'=>$delivery_id));
                    
                    $dlyList[] = $dlyInfo;
                }
                
                $this->pagedata['branch_lists'] = $branchList;
                $this->pagedata['dlyList'] = $dlyList;
                $this->pagedata['dly_count'] = count($dlyList);
                $this->page("admin/delivery/return/show_refuse_list.html");
            }else{
                //报错信息返回
                $this->pagedata['error_msg'] = $error_msg;
                $this->page("admin/delivery/return/check.html");
            }
        }else{
            $this->page("admin/delivery/return/check.html");
        }
    }

    /**
     * 显示待拒收发货单明细及相关信息(作废: 此方法已不在使用)
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
        
        //can return serial number info
        $serial_numbers = array();
        $dlyItemsSerialLib = kernel::single('wms_receipt_dlyitemsserial');
        foreach($orderIds as $var_order_id){
            $serial_list = $dlyItemsSerialLib->getCanReturnSerial(array('order_id'=>$var_order_id));
            if($serial_list){
                foreach($serial_list as $serial_number => $bn){
                    $serial_numbers[] = array('bn'=>$bn, 'serial_number'=>$serial_number);
                }
            }
        }
        $this->pagedata['serial_numbers'] = $serial_numbers;
        
        $this->page("admin/delivery/return/process_show.html");
    }

    /**
     * 执行发货拒收的具体数据处理
     * todo: 手工输入订单号追回时,如果一个订单拆分多个发货单时,不支持多次追回,仅支持一次性追回所有;
     * 
     */
    function doprocess(){
        $this->begin();
        $deliveryObj = app::get('ome')->model('delivery');
        $branchObj = app::get('ome')->model('branch');
        
        $order_id = intval($_POST['order_id']);
        $order_bn = $_POST['order_bn'];
        $instock_branch = $_POST['instock_branch'];
        
        if(empty($order_id)){
            $this->end(false, app::get('base')->_('无效的操作'));
        }
        
        if(empty($instock_branch)){
            $this->end(false, app::get('base')->_('请选择退回仓库'));
        }
        
        //branch list
        $branchList = array();
        $tempData = $branchObj->getAllBranchs('branch_id,name');
        foreach ($tempData as $key => $val){
            $branchList[$val['branch_id']] = $val;
        }
        
        //组织数据
        $refuseList = array();
        foreach ($instock_branch as $delivery_id => $in_branch_id){
            $delivery_id = intval($delivery_id);
            $in_branch_id = intval($in_branch_id);
            
            //发货单
            $sql = "SELECT b.delivery_id FROM sdb_ome_delivery_order AS a LEFT JOIN sdb_ome_delivery AS b ON a.delivery_id=b.delivery_id 
                    WHERE a.order_id=". $order_id ." AND a.delivery_id=". $delivery_id ." AND b.status='succ'";
            $dlyInfo = $deliveryObj->db->selectrow($sql);
            if(empty($dlyInfo)){
                $this->end(false, app::get('base')->_('订单号:'. $order_bn .',关联的发货单不存在!'));
            }
            
            //追回仓库
            if(empty($branchList[$in_branch_id])){
                $this->end(false, app::get('base')->_('订单号:'. $order_bn .',没有选择退回仓库!'));
            }
            
            $refuseList[] = array('order_id'=>$order_id, 'order_bn'=>$order_bn, 'delivery_id'=>$delivery_id, 'branch_id'=>$in_branch_id);
        }
        
        //最终处理
        $refuseLib = kernel::single('ome_delivery_refuse');
        foreach ($refuseList as $key => $val){
            
            $error_msg = '';
            $params = array(
                    'type' => 'order', //追回方式
                    'bill_no' => $val['order_bn'], //订单号
                    'order_id' => $val['order_id'], //订单ID
                    'delivery_id' => $val['delivery_id'], //发货单号ID
                    'branch_id' => $val['branch_id'], //退回仓库ID
            );
            $result = $refuseLib->finish_refuse($params, $error_msg);
            if(!$result){
                $this->end(false, app::get('base')->_($error_msg));
            }
        }
        
        $this->end(true, app::get('base')->_('发货拒收确认成功'));
    }


    function batch_sync(){
        // $this->begin('');
        $ids = $_POST['reship_id'];
        if (!empty($ids)) {
            foreach ($ids as  $reshipid) {
                $reship = app::get('ome')->model('reship')->dump(array('reship_id'=>$reshipid,'is_check'=>1),'reship_id,is_check');
                if ($reship) {
                    $reship_data = kernel::single('ome_receipt_reship')->reship_create(array('reship_id'=>$reship['reship_id']));
                    $wms_id = kernel::single('ome_branch')->getWmsIdById($reship_data['branch_id']);
                    kernel::single('console_event_trigger_reship')->create($wms_id, $reship_data, false);

                }
            }
        }
        $this->splash('success', null, '命令已经被成功发送！！');
    }

    public function finish_check($reship_id){

        $Oreship = $this->app->model('reship');

        $reship_data = $Oreship->getCheckinfo($reship_id,false);
        $reship_data['consignee']['area'] = explode(":",$reship_data['consignee']['area']);
        $reship_data['consignee']['area'] = $reship_data['consignee']['area'][1];
        $this->pagedata['reship_data'] = $reship_data;

        $this->page('admin/delivery/return/finish_check.html');
    }

    public function do_finish_check($reship_id){
        $this->begin();
        $reshipobj = $this->app->model('reship');
        $oReship_item = $this->app->model ( 'reship_items' );
        $reship_detail = $reshipobj->dump(array('reship_id'=>$reship_id),'is_check,order_id');
        $reship_item = $reshipobj->getItemList($reship_id);
        foreach($reship_item as $item){
            $refund = 0;
            $refund = $oReship_item->Get_refund_count( $reship_detail['order_id'], $item['bn'] ,$reship_id);
            if ($refund<($item['defective_num']+$item['normal_num'])){
                $this->end(false,$item['bn'].'剩余可退数量不足!');
            }
        }

        if (in_array($reship_detail['is_check'],array('7'))){
            $this->end(false,'此单据已完成!');
        }
        if($reshipobj->finish_aftersale($reship_id)){
            $result = kernel::single('console_reship')->siso_iostockReship($reship_id);

        }
        $this->end(true,'操作成功！');
    }
    
    /**
     * 发货追回导入
     */
    public function import(){
        $this->page("admin/delivery/return/import.html");
    }
    
    /**
     * 处理导入的数据
     */
    function doImport(){
        
        echo json_encode(array('result'=>'fail', 'msg'=>array('此功能已下架')));
        exit;
        
        $refuseLib = kernel::single('ome_delivery_refuse');
        $oQueue = app::get('base')->model('queue');
        
        //检查是否有任务在执行
        $queueInfo = $oQueue->dump(array('queue_title'=>'发货追回导入', 'status'=>array('running', 'hibernate')), 'queue_id');
        if($queueInfo){
            $result['error_msg'] = '已经有追回导入任务在队列中执行，请稍候再试！查看队列任务【控制面板-其他-队列管理】';
            echo json_encode(array('result'=>'fail', 'msg'=>(array)$result['error_msg']));
            exit;
        }
        
        //解析导入的文件
        $result = $refuseLib->dispose_import_csv();
        
        header("content-type:text/html; charset=utf-8");
        if($result['rsp'] == 'fail'){
            echo json_encode(array('result'=>'fail', 'msg'=>(array)$result['error_msg']));
            exit;
        }
        
        //发货追回方式
        $title = $result['title'][0];
        $type = '';
        if($title == '*:退回物流单号'){
            $type = 'logistics';
        }elseif($title == '*:发货单号'){
            $type = 'delivery';
        }else{
            $result['error_msg'] = '导入文件不正确,标题必须是: 退回物流单号 或者 发货单号!';
            echo json_encode(array('result'=>'fail', 'msg'=>(array)$result['error_msg']));
            exit;
        }
        
        //格式化数据
        $data = array();
        foreach ($result['data'] as $key => $val){
            $bill_no = trim($val[0]);
            $data[$bill_no] = $bill_no;
        }
        
        //加入队列处理
        $error_msg = '';
        $result = $refuseLib->dispose_refuse($type, $data, $error_msg);
        
        if(!$result){
            echo json_encode(array('result'=>'fail', 'msg'=>(array)$error_msg));
            exit;
        }
        
        echo json_encode(array('result'=>'succ', 'msg'=>'上传成功,等待队列自动执行!'));
        exit;
    }
    
    /**
     * 模板导出
     */
    function exportTemplate(){
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=".date('Ymds').".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        
        $type = $_GET['mode'];
        if($type == 'delivery_no'){
            $contents = array('*:发货单号');
        }else{
            $contents = array('*:退回物流单号');
        }
        
        foreach ($contents as $v){
            $title[] = kernel::single('base_charset')->utf2local($v);
        }
        
        echo '"'.implode('","', $title).'"';
    }
}
