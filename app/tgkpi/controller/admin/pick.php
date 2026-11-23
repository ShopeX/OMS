<?php
class tgkpi_ctl_admin_pick extends desktop_controller{
    var $name = "拣货绩效";
    var $workground = "pick";

    function index(){
        //$this->app->model($this->workground)->check_delivery(4839);
        //app::get('ome')->model('orders')->create_order();
        $this->page('admin/pick.html');
    }

    // 查询工号是否存在
    function get_op_info(){
        $pick_owner = strtoupper(trim($_POST['pick_owner']));

        $oUser = app::get('desktop')->model('users');
        $user = $oUser->dump(array('op_no'=>$pick_owner),'name');
        if(!$user) {
            echo('no user');
            exit();
        }

        //查询需要捡货数和已完成数
        //$oPick = $this->app->model('pick');
        //$picks = $oPick->getList('pick_status,delivery_id',array('pick_owner'=>$pick_owner,'pick_start_time'=>array(strtotime(date('Y-m-d')),time())));
        $db = kernel::database();
        $sql = "SELECT pick_status,delivery_id FROM sdb_tgkpi_pick
                WHERE pick_owner=".$db->quote($pick_owner)." AND (pick_start_time BETWEEN '".strtotime(date('Y-m-d'))."' AND '".time()."')";
        $picks = $db->select($sql);

		//增加了检货人姓名的输出
        $arr = array('running'=>0,'finish'=>0, 'name' => $user['name']);

		//增加前台输出检货人姓名
        $delivery_ids = array();
        foreach((array)$picks as $v) {
            if(in_array($v['delivery_id'],$delivery_ids)) continue;
            $delivery_ids[] = $v['delivery_id'];
            $arr[$v['pick_status']] ++;
        }
        $rs = json_encode($arr);
        echo($rs);
    }

    // 根据快递单号获取发货单列表
    function get_delivery(){
        $logi_no = $_POST['logi_no'];
        $pick_owner = strtoupper(trim($_POST['pick_owner']));
        $in_type = $_POST['in_type'];

        // 单个录入
        $dlyBillLib = kernel::single('wms_delivery_bill');

        $delivery_id = $dlyBillLib->getDeliveryIdByPrimaryLogi($logi_no);
        if(!$delivery_id){
            $result['fail'] = array(
                array('logi_no'=>$logi_no,'msg'=>'快递单号不存在!'),
            );
            echo json_encode($result);
            exit;
        }

        $oDelivery = app::get('wms')->model('delivery');
        $rs = $oDelivery->getList('delivery_bn,logi_name,delivery_id,itemNum,skuNum',array('delivery_id'=>$delivery_id),0,1);

        if($rs){
            if (in_array($rs[0]['status'],array('cancel','stop','back','return_back')) || $rs[0]['pause']=='true') {
                 $result['fail'] = array(
                array('logi_no'=>$logi_no,'msg'=>'请确认当前发货单状态是否异常!'),
                );
                echo json_encode($result);
                exit;
            }else{
                $delivery_ids[] = $rs[0]['delivery_id'];
                $oPrintQueue = app::get('ome')->model('print_queue_items');
                $ident = $oPrintQueue->dump(array('delivery_id'=>$rs[0]['delivery_id']),'ident,ident_dly');
                if($ident){
                    $rs[0]['ident'] = $ident['ident'].'_'.$ident['ident_dly'];
                }
                $rs[0]["logi_no"] = $logi_no;
            }
            
        }else{
            $result['fail'] = array(
                array('logi_no'=>$logi_no,'msg'=>'快递单号不存在!'),
            );
            echo json_encode($result);
            exit;
        }

        // 批次录入
        if($in_type == 'batch' && $ident['ident']) {
            $dly_bns = $oPrintQueue->getList('*',array('ident'=>$ident['ident']));
            foreach((array)$dly_bns as $v) {
                $ids[] = $v['delivery_id'];
                $printIdents[$v['delivery_id']] = $v['ident'].'_'.$v['ident_dly'];
            }
            if($dly_bns){
                $rs = $oDelivery->getList('delivery_bn,logi_name,delivery_id,itemNum,skuNum',array('delivery_id'=>$ids));
                foreach((array)$rs as $k=>$v) {
                    $rs[$k]['ident'] = $printIdents[$v['delivery_id']];
                    $delivery_ids[] = $v['delivery_id'];

                    $logi_no = $dlyBillLib->getPrimaryLogiNoById($v['delivery_id']);
                    $rs[$k]['logi_no'] = $logi_no;
                }
            }
        }

        // 根据delivery_id检测发货单是否已经录入
        $oPick = $this->app->model('pick');
        $picks = $oPick->getList('delivery_id',array('delivery_id'=>$delivery_ids));
        if ($picks) {
            foreach((array)$picks as $v) {
                $exist_delivery_ids[] = $v['delivery_id'];
            }
            foreach((array)$rs as $k=>$v) {
                if(in_array($v['delivery_id'],$exist_delivery_ids)){
                    $result['fail'][] = array('logi_no'=>$v['logi_no'],'msg'=>'快递单号已经录入!');
                    unset($rs[$k]);
                }
            }
        }
        $result['succ'] = $rs;
        echo json_encode($result);exit;
        /*
        if(!$rs) {
            $rs = '404';
        }else{
            $rs = json_encode($rs);
        }
        echo($rs);*/
    }

    // 保存捡货关联数据
    function save(){
        $pick_owner = strtoupper(trim($_POST['pick_owner']));//工号
        $delivery_ids = $_POST['delivery_id'];

        if (!$pick_owner || !$delivery_ids) {
            $this->redirect('index.php?app=tgkpi&ctl=admin_pick&act=index');
        }

        $oDeliveryItems = app::get('wms')->model('delivery_items');
        $oDelivery = app::get('wms')->model('delivery');
        
        $oPick = $this->app->model('pick');
        $opObj = app::get('ome')->model('operation_log');
        $printIdentsObj = app::get('ome')->model('print_queue_items');

        // 发货单信息
        $delivery_items = $oDeliveryItems->getList('*',array('delivery_id'=>$delivery_ids));
        foreach((array)$delivery_items as $v) {
            $product_ids[] = $v['product_id'];
        }

        // 获取skuNum 和 itemNum
        $rs = $oDelivery->getList('skuNum,itemNum,delivery_id',array('delivery_id'=>$delivery_ids));
        foreach((array)$rs as $v) {
            $deliverys[$v['delivery_id']] = $v;
        }
        unset($rs);

        // 获取商品的 product_pick_level
        /*
        $rs = $oDelivery->getList('product_pick_level,product_id',array('product_id'=>$product_ids));
        foreach((array)$rs as $v) {
            $pick_levels[$v['product_id']] = $v;
        }
        unset($rs);
		*/

        // 获取商品的 product_pick_level
        $rs = $printIdentsObj->getList('*',array('delivery_id'=>$delivery_ids));
        foreach((array)$rs as $v) {
            $printIdents[$v['delivery_id']] = $v;
        }
        unset($rs);

        $logIds = array();
        foreach((array)$delivery_items as $v) {
            $rs['product_id'] = $v['product_id'];
            $rs['product_bn'] = $v['bn'];
            if(!$pick_levels[$v['product_id']]) $pick_levels[$v['product_id']] = 1;//默认捡货难度为1
            $rs['product_pick_level'] = $pick_levels[$v['product_id']];
            $rs['pick_num'] = $v['number'];
            $rs['pick_owner'] = $pick_owner;
            $rs['pick_start_time'] = time();
            //$rs['pick_end_time'] = time();
            $rs['pick_error_num'] = 0;
            $rs['pick_status'] = 'running';
            $rs['delivery_id'] = $v['delivery_id'];
            $rs['print_ident'] = $printIdents[$v['delivery_id']]['ident'];
            $rs['print_ident_dly'] = $printIdents[$v['delivery_id']]['ident_dly'];
            $rs['delivery_sku_num'] = $deliverys[$v['delivery_id']]['skuNum'];
            $_op_name = $opObj->getList('op_name',array('obj_id'=>$v['delivery_id'],'operation'=>'delivery_process@ome'));
            if($_op_name[0]['op_name']){
                #存在最终发货人，则属于发货完成的
                $rs['pick_status'] = 'deliveryed';
                $rs['op_name'] = $_op_name[0]['op_name'];
            }
            //$rs['branch_id'] = $v['000000000000'];
            //$rs['pos_id'] = $v['000000000000'];
            //$rs['branch_pos_position'] = $v['000000000000'];
            // product_id 和 delivery_id 保持唯一性
            if($oPick->count(array('product_id'=>$rs['product_id'],'delivery_id'=>$rs['delivery_id'])) == 0) {
                $oPick->insert($rs);

                //增加发货单捡货开始日志
                if(!in_array($v['delivery_id'],$logIds)){
                    if (!empty($pick_owner)){
                        $pickUser = app::get('desktop')->model('users')->dump(array('op_no'=>$pick_owner), 'name');
                    }

                    $msg = $pickUser['name'].'(工号:'.$pick_owner.')开始拣货';
                    $opObj->write_log('delivery_pick@wms', $v['delivery_id'], $msg);
                    $logIds[$v['delivery_id']] = $v['delivery_id'];
                }
            }
            unset($rs);
        }
        //echo('<pre>');var_dump($delivery_items);
        die('<script>window.location="?app=tgkpi&ctl=admin_pick&act=index";</script>');
    }

    public function setCheckFail(){
        if($_POST){
            $pickObj = $this->app->model('pick');
            $memoObj = $this->app->model('check_memo');
            $opInfo = kernel::single('ome_func')->getDesktopUser();

            $pickObj->update(array('check_start_time'=>null,'check_op_id'=>null,'check_op_name'=>null,'cost_time'=>null,'pick_status'=>'running'),array('delivery_id'=>$_POST['delivery_id']));

            $data = array(
                'delivery_id' => $_POST['delivery_id'],
                'check_op_id' => $opInfo['op_id'],
                'check_op_name' => $opInfo['op_name'],
                'reason_id' => $_POST['memo_id'],
                'memo' => $_POST['memo'],
                'addtime' => time(),
            );
            $memoObj->save($data);
            exit;
        }else{
            $reasonObj = $this->app->model('reason');
            $reasonInfos = $reasonObj->getList('reason_id,reason_memo',null,0,-1);
            $this->pagedata['reasonInfos'] = $reasonInfos;
            $this->pagedata['delivery_id'] = $_GET['id'];
            $this->pagedata['url'] = $_GET['rurl'];
            $this->page("admin/check_fail_memo.html");
        }
    }
}
