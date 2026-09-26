<?php
class ome_ctl_admin_return_sv extends desktop_controller {

    var $name = "退换货服务";
    var $workground = "aftersale_center";

    function index(){
        $oBranch = app::get('ome')->model('branch');
        $this->pagedata['error_msg'] = '';
        $wms_id = kernel::single('wms_branch')->getBranchByselfwms();

        $branch_list = $oBranch->getList('branch_id', array('wms_id'=>$wms_id), 0, -1);

        if ($branch_list)
        $branch_ids = array();
        foreach ($branch_list as $branch_list) {
            $branch_ids[] = $branch_list['branch_id'];

        }
        if($_POST['logi_no']){

            $oReship = $this->app->model('reship');
            $reship_id = $oReship->getLogiInfo($_POST['logi_no'],$branch_ids);

            if($reship_id){
               $return = $this->edit($reship_id,'index');
               if ($return !== false) {
                   exit;
               }
            }else{
               $this->pagedata['error_msg'] = '没有找到对应的物流单号';
            }
        }
        
        
        $this->pagedata['count'] = $this->app->model('reship')->count(array('is_check'=>array('3','1'),'branch_id'=>$branch_ids));

        $this->page("admin/return_product/sv/process_check_index.html");
    }

    /**
     * 质检
     *
     * @param Int $reship_id  退货单ID
     * @param String $from_type index:通过物流单质检，‘’:通过鼠标点击质检
     * @return void
     * @author
     **/
    function edit($reship_id,$from_type='')
    {
        @ini_set('memory_limit','512M');
        # 先进行收货处理 判断是否已经收货
        $reship_detail = $this->app->model('reship')->dump($reship_id,'is_check,branch_id');
        $is_check = $reship_detail['is_check'];
        $branch_id = $reship_detail['branch_id'];
        
        if ($is_check == '1') {
            kernel::single('ome_return_rchange')->accept_returned($reship_id,'3',$error_msg);
        }

        switch ($is_check) {
            case '0':
                $error_msg = '售后服务单未审核!';
                break;
            case '2':
                $error_msg = '售后服务单审核失败!';
                break;
            case '4':
                $error_msg = '拒绝收货!';
                break;
            case '5':
                $error_msg = '售后服务单审核被拒绝!';
                break;
            case '7':
                $error_msg = '售后服务单审核已经完成!';
                break;
            case '8':
                $error_msg = '质检已经通过!';
                break;
            case '9':
                $error_msg = '质检已经被拒绝!';
                break;
            default:
                # code...
                break;
        }

        if ($error_msg) {
            if ($from_type == 'index') {
                $this->pagedata['error_msg'] = $error_msg;
                return false;
            }
        }

        $basicMaterialObj        = app::get('material')->model('basic_material');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        $storageLifeLib = kernel::single('material_storagelife');

        $oProduct_pro = $this->app->model('return_process');
        $oOrder = $this->app->model('orders');
        $oProblem = $this->app->model('return_product_problem');

        $goodsObj = $this->app->model('goods');
        $productSerialObj = $this->app->model('product_serial');
        $oSeriallog = $this->app->model('product_serial_log');
        $memo='';
        $serial['merge'] = $this->app->getConf('ome.product.serial.merge');
        $serial['separate'] = $this->app->getConf('ome.product.serial.separate');

        $oBranch = app::get('ome')->model('branch');
        $orders = $oProduct_pro->dump(array('reship_id'=>$reship_id),'order_id,memo');
        $oProduct_pro_detail = $oProduct_pro->product_detail($reship_id,$orders['order_id']);

        $order_id = $orders['order_id'];
        $reason = unserialize($oProduct_pro_detail['reason']);

        $oProduct_pro_detail['check_memo'] = $reason['check'];
        $wms_id = kernel::single('wms_branch')->getBranchByselfwms();
        $isExistOfflineBranch = $oBranch->isExistOfflineBranchBywms($wms_id);

        # 如果没有线下仓 去除残仓、报仓
        if (!$isExistOfflineBranch) {
            unset($oProduct_pro_detail['StockType'][1],$oProduct_pro_detail['StockType'][2]);
        }
        $isExistOnlineBranch = $oBranch->isExistOnlineBranchBywms($wms_id);

        $forNum = array();
        $serial_numbers = array();
        $mixed_array = array();

        $bnArr = array();
        $gArr = array();
        $serialLogArr = array();
        $serialProductArr = array();
        $product_process = $oProduct_pro_detail;
        unset($product_process['items']);
        //$product_process['items'] = array();
        foreach ($oProduct_pro_detail['items'] as $key => $val){
            if($val['return_type'] == 'change'){
                unset($oProduct_pro_detail['items'][$key]);
                break;
            }

            if (!isset($bnArr[$val['bn']]))
            {
                $bnArr[$val['bn']]     = $basicMaterialObj->dump(array('material_bn'=>$val['bn']), '*');
                $bnArr[$val['bn']]['barcode']    = $basicMaterialBarcode->getBarcodeById($bnArr[$val['bn']]['bm_id']);
                
                #检查基础物料是否是保质期类型
                $is_use_expire = $storageLifeLib->checkStorageLifeById($bnArr[$val['bn']]['bm_id']);
                $val['use_expire']    = ($is_use_expire ? 1 : 0);
            }
            
            $p = $bnArr[$val['bn']];
            
            $mixed_array['material_bn_'.$val['bn']] = $val['bn'];

            //判断条形码是否为空
            if(!empty($p['barcode'])){
               $mixed_array['barcode_'.$p['barcode']] = $val['bn'];
            }

            /* 退货数量 */
            if($product_process['items'][$val['bn']]){
                $product_process['items'][$val['bn']]['num'] += $val['num'];
            }else{
                $product_process['items'][$val['bn']] = $val;
            }

            $product_process['items'][$val['bn']]['barcode'] = $p['barcode'];

            /* 校验数量 */
            if($val['is_check'] == 'true'){
                $product_process['items'][$val['bn']]['checknum'] += $val['num'];
                $oProduct_pro_detail['items'][$key]['checknum'] = $val['num'];
            }

            // 按明细数量展开槽位，保证可质检数量与 id_{bn}{n} 一一对应
            // （同一行 num>1 时，每个件数一个槽位，供唯一码逐个扫描）
            $itemId = $val['reship_item_id'] ? : $val['item_id'];
            $slotQty = max(1, intval($val['num']));
            for ($slot = 0; $slot < $slotQty; $slot++) {
                $product_process['items'][$val['bn']]['itemIds'][] = $itemId;
            }

            if($val['is_check'] == 'false'){
                /* 退货数量 */
                if($forNum[$val['bn']]){
                    $forNum[$val['bn']] += 1;
                    $oProduct_pro_detail['items'][$key]['fornum'] = $forNum[$val['bn']];
                }else{
                    $oProduct_pro_detail['items'][$key]['fornum'] = 1;
                    $forNum[$val['bn']] = 1;
                }
            }
            $product_process['items'][$val['bn']]['spec_info'] = $p['spec_info'];
            unset($oProduct_pro_detail['items'][$key]);
            $product_process['por_id'] = $val['por_id'];
        }

        //can return serial number info
        $dlyItemsSerialLib    = kernel::single('wms_receipt_dlyitemsserial');
        $serial_list = $dlyItemsSerialLib->getCanReturnSerial(array('order_id'=>$order_id));
        if($serial_list){
            foreach($serial_list as $serial_number => $bn){
                $mixed_array['serial_number_'.$serial_number] = $bn;
                $serial_numbers[] = array('bn'=>$bn, 'serial_number'=>$serial_number);
            }
        }
        $this->pagedata['serial_numbers'] = $serial_numbers;

        $list = $oProblem->getList('problem_id,problem_name');
        $product_process['problem_type'] = $list;


        $return_apply = $this->app->model('return_product')->getList('*',array('return_id'=>$oProduct_pro_detail['return_id']));
        $this->pagedata['return_apply'] = $return_apply[0];
        #
        $plugin_html = '';
        
        if ($return_apply[0]['source']=='matrix') {
          $plugin_html = kernel::single('ome_aftersale_service')->reship_edit($return_apply[0]);
        }
        $this->pagedata['plugin_html'] = $plugin_html;
        #
        $this->pagedata['mixed_array'] = json_encode($mixed_array);
        if (!is_numeric($oProduct_pro_detail['attachment'])){
            $this->pagedata['attachment_type'] = 'remote';
            $attachment = explode("|",$returnProcess['attachment']);
            if($attachment[0]!='')$returnProcess['attachment'] = $attachment;
        }
        $this->pagedata['pro_detail']=$product_process;
        $oReship = $this->app->model('reship');
        $Orship_items = $oReship->getReshipItems($reship_id);
        $this->pagedata['order'] = $Orship_items;
        $this->pagedata['branch_id'] = $branch_id;
		unset($bnArr);
		unset($gArr);
		unset($serialLogArr);
		unset($serialProductArr);
		//默认商品入库类型
        $goodsBranchTypeConf = app::get('ome')->getConf('ome.aftersale.goods.branch.type');
        $goodsBranchType = 0;
        if ($goodsBranchTypeConf == 'aftersale') {
            $goodsBranchType = 1;
        }elseif ($goodsBranchTypeConf == 'damaged') {
            $goodsBranchType = 2;
        }
        $this->pagedata['goods_branch_type'] =  $goodsBranchType;

        if($from_type == 'index'){
            $this->pagedata['from_type'] = $from_type;
            $this->page("admin/return_product/sv/edit.html");
        }else{
            $this->display("admin/return_product/sv/edit.html");
        }
    }

    /**
     * 保存质检信息
     * @param Int $reship_id 退换单据ID
     * @param Int $status 8:质检通过 9:拒绝质检
     **/
    function tosave($reship_id,$status){
        set_time_limit(0);
        if($_POST['from_type'] == 'index'){
           $this->begin();
        }else{
           $this->begin('index.php?app=wms&ctl=admin_return_rchange&act=index&flt=process_list');
        }

        $oProduct_pro = $this->app->model('return_process');
        $oReship = $this->app->model('reship');
        $oProblem = $this->app->model('return_product_problem');
        $oBranch = app::get('ome')->model('branch');
        $productSerialObj = $this->app->model('product_serial');
        $serialLogObj = $this->app->model('product_serial_log');
        $oOperation_log = $this->app->model('operation_log');
        $pro_items =$this->app->model('return_process_items');
        $mdl_reship_items  = $this->app->model('reship_items');

        switch($status){
            case '9' :
                //拒绝质检 获取拒绝原因
                $reason = $oReship->select()->columns('reason')->where('reship_id=?',$reship_id)->instance()->fetch_one();
                $refuseMemo = unserialize($reason);
                $refuseMemo['refuse'] = $_POST['info']['refuse_memo'];
                $reship = $oReship->dump($reship_id,'is_check,return_type,changebranch_id');
                
                $refuse = array(
                    'reason' => serialize($refuseMemo),
                );
                if ($reship['is_check'] == '13'){#有过收货记录置拒绝
                    $refuse['is_check'] = '9';
                }else{#否则异常
                    $refuse['is_check'] = '12';
                }
                
                $reship_result = $oReship->update($refuse,array('reship_id'=>$reship_id));
                if ($reship_result){
                    #如果为异常，删除收货记录
                    if ($refuse['is_check']=='12'){
                        $oProduct_pro->delete(array('reship_id'=>$reship_id));
                        $pro_items->delete(array('reship_id'=>$reship_id));
                    }
                    //库存管控 拒绝换货时释放冻结
                    kernel::single('console_reship')->releaseChangeFreeze($reship_id);
                }
                # 写LOG
                $oOperation_log->write_log('reship@ome',$reship_id,'拒绝质检');
                $this->end(true,$this->app->_('拒绝质检成功'));
                break;
            case '10':
                //质检异常
                $filter = array('reship_id'=>$reship_id);
                $reshipinfo = $oReship->getList('memo',$filter,0,1);
                $memo = $reshipinfo[0]['memo'].'！'.$_POST['process_memo'];
                $oReship->update(array('is_check'=>'10','memo'=>$memo),$filter);
                $oOperation_log->write_log('reship@ome',$reship_id,'质检异常');
                $this->end(true,$this->app->_('质检异常更新成功!'));
                break;
            case '8' :   # 质检
                if(empty($_POST['process_id'])) $this->end(false,$this->app->_('请先扫描货号/条形码'));

                $opInfo = kernel::single('ome_func')->getDesktopUser();
                $row = $oReship->getList('reship_id, reship_bn, branch_id, order_id',array('reship_id'=>$reship_id,'is_check'=>array('1','3','13')));
                if (!$row) {
                    $this->end(false,$this->app->_('退换货单据未审核!'));
                }
                if (!$_POST['por_id']) {
                    $this->end(false,$this->app->_('请选择质检明细!'));
                }

                $reship_bn = $row[0]['reship_bn'];
                $order_id = $row[0]['order_id'];
                # 已经扫描的货号
                $scans = $_POST['process_id'];

                #保质期变更仓库
                $change_expire_bn    = array();
                $branch_id    = $row[0]['branch_id'];

                # 验证可质检数
                $checknum = $noscan = array();
                foreach ($scans as $key=>$pbn) {
                    // 兼容旧的数字 item_id，以及新的 slot_id（如 123_1）
                    if ($key === '' || $key === null || (is_numeric($key) && $key <= 0)) {
                        continue;
                    }
                    # 可质检数
                    if (!$noscan[$pbn]) {
                        $filter  = array(
                            'bn' => $pbn,
                            'por_id' => $_POST['por_id'],
                            'is_check' => 'false',
                        );
                        $row = $pro_items->getList('sum(num) as _s',$filter,0,1);
                        $noscan[$pbn] = $row[0]['_s'];
                    }

                    $checkKey = $pbn.$key;
                    $checknum[$pbn] += $_POST['check_num'][$checkKey];

                    if (!isset($_POST['instock_branch'][$checkKey])) {
                        $this->end(false,$this->app->_('请选择入库类型!'));
                    }
                    $tmp_branch = $_POST['instock_branch'][$checkKey];
                    
                    #保质期变更仓库
                    $get_expire_bn    = trim($_POST['expire_bn'][$checkKey]);
                    $get_expire_bn    = ($get_expire_bn == 'undefined' ? '' : $get_expire_bn);
                    
                    //保质期信息组织
                    if($get_expire_bn){
                        $expire_bn_key = md5($tmp_branch.'_'.$pbn.'_'.$get_expire_bn);
                        if(isset($change_expire_bn[$expire_bn_key])){
                            $change_expire_bn[$expire_bn_key]['num']               += intval($_POST['check_num'][$checkKey]);
                        }else{
                            $change_expire_bn[$expire_bn_key]['material_bn']       = $pbn;
                            $change_expire_bn[$expire_bn_key]['expire_bn']         = $get_expire_bn;
                            $change_expire_bn[$expire_bn_key]['num']               = intval($_POST['check_num'][$checkKey]);
                            $change_expire_bn[$expire_bn_key]['instock_branch']    = $tmp_branch;
                        }
                    }
                }
                unset($tmp_branch);
                
                foreach ($noscan as $pbn=>$sum) {
                    if ($checknum[$pbn]!=$sum) {
                        $this->end(false,$this->app->_('请全部扫描完!'));
                        return false;
                    }
                }
                #将数据以货号+仓库+备注方式重新组合
                $arrItemUpData = array();
                foreach ( $scans as  $sk=>$sbn) {
                    if ($sk === '' || $sk === null || (is_numeric($sk) && $sk <= 0)) {
                        continue;
                    }
                    $checkKey = $sbn.$sk;
                    // slot_id 映射回真实 reship_item_id；旧数据无映射时 slot key 本身即 item_id
                    $realReshipItemId = isset($_POST['process_reship_item'][$sk]) && $_POST['process_reship_item'][$sk] !== ''
                        ? $_POST['process_reship_item'][$sk]
                        : $sk;
                    $tmp_branch = intval($_POST['instock_branch'][$checkKey]);
                    $bnBmIdName = app::get('material')->model('basic_material')->db_dump(array('material_bn'=>$sbn), 'bm_id,material_name');
                    $productId = $bnBmIdName['bm_id'];
                    $num = $_POST['check_num'][$checkKey];
                    if($arrItemUpData[$bnBmIdName['bm_id']]) {
                        $arrItemUpData[$bnBmIdName['bm_id']]['check_num'] += $num;
                        $arrItemUpData[$productId]['items'][] = array(
                            'memo' => $_POST['memo'][$checkKey],
                            'store_type' => $_POST['store_type'][$checkKey],
                            'branch_id' => $tmp_branch,
                            'num' => $num,
                            'reship_item_id' => $realReshipItemId,
                        );
                    } else {
                        $arrItemUpData[$productId]['check_num'] = $num;
                        $arrItemUpData[$productId]['items'][] = array(
                            'memo' => $_POST['memo'][$checkKey],
                            'store_type' => $_POST['store_type'][$checkKey],
                            'branch_id' => $tmp_branch,
                            'num' => $num,
                            'reship_item_id' => $realReshipItemId,
                        );
                    }
                }
           
                if ($arrItemUpData) {
                    $reshipProcess = app::get('ome')->model('return_process')->db_dump(['reship_id' => $reship_id]);
                    $rsp = kernel::single('ome_return_process')->qualityCheckItemsSave($arrItemUpData, $reshipProcess);
                    if($rsp['rsp'] == 'fail') {
                        $this->end(false, $this->app->_($rsp['msg']));
                    }
                }

                //serial number return process
                if($_POST['serial_id']){
                    //Load Lib
                    $dlyItemsSerialLib    = kernel::single('wms_receipt_dlyitemsserial');
                    
                    $history_serial = array();
                    foreach($_POST['serial_id'] as $item_id => $item){
                        foreach($item as $bn => $serial_number){
                            //new
                            $key = $bn.$item_id;
                            $serial_branch_id = intval($_POST['instock_branch'][$key]);

                            $serialItem = array(
                                'serial_number' => $serial_number,
                                'reship_id' => $_POST['reship_id'],
                                'reship_bn' => $reship_bn,
                                'branch_id' => $serial_branch_id,
                                'bn' => $bn,
                            );
                            $rs = $dlyItemsSerialLib->returnProduct($serialItem, $err_msg, $return_serial);
                            if(!$rs){
                                $url = 'index.php?app=ome&ctl=admin_return_sv&act=edit&p[0]='.$_POST['reship_id'];
                                $this->end(false, $this->app->_('唯一码退入失败'), $url , array('msg'=>$err_msg));
                            }else{
                                $history_serial[] = $return_serial;
                            }
                        }
                    }

                    //write history serial
                    kernel::single('ome_receipt_dlyitemsserial')->returnProduct($history_serial, $msg);

                    // OME 质检路径原仅写 OME/WMS 表；机器中心入库需与 WMS 回传一致，否则 material_sn_flow 无 reship 流水、权益回退等埋点不执行
                    if (app::get('machinecenter')->is_installed() && app::get('console')->is_installed()) {
                        $mcItems = array();
                        foreach ($_POST['serial_id'] as $item_id => $item) {
                            if (!is_array($item)) {
                                continue;
                            }
                            foreach ($item as $bn => $serial_number) {
                                $serial_number = trim((string) $serial_number);
                                if ($serial_number === '') {
                                    continue;
                                }
                                if (!isset($mcItems[$bn])) {
                                    $mcItems[$bn] = array('bn' => $bn, 'sn_list' => array());
                                }
                                $mcItems[$bn]['sn_list'][] = $serial_number;
                            }
                        }
                        if ($mcItems) {
                            kernel::single('console_receipt_reship')->machineCenterReshipAdd(array(
                                'items' => array_values($mcItems),
                                'reship_info' => array(
                                    'reship_id' => (int) $_POST['reship_id'],
                                    'reship_bn' => $reship_bn,
                                    'branch_id' => $branch_id,
                                    'order_id' => $order_id,
                                    't_end' => time(),
                                ),
                            ));
                        }
                    }
                }

                //storagelife return process
                $dlyItemsStorageLifeObj    = app::get('wms')->model('delivery_items_storage_life');
                $dlyItemsStorageLifeLib    = kernel::single('wms_receipt_dlyitemsstoragelife');
                $omeDlyObj  = app::get('ome')->model('delivery');
                $wmsDlyObj  = app::get('wms')->model('delivery');

                //storagelife info
                if($change_expire_bn){
                    $omeDeliveryInfo = $omeDlyObj->getFinishDeliveryByorderId($order_id);
                    foreach($omeDeliveryInfo as $omeDlyInfo){
                        $omeDlyBns[] = $omeDlyInfo['delivery_bn'];
                    }

                    $wmsDeliveryInfo = $wmsDlyObj->getList('delivery_id', array('outer_delivery_bn'=>$omeDlyBns), 0, -1);
                    foreach($wmsDeliveryInfo as $wmsDlyInfo){
                        $wmsDlyIds[] = $wmsDlyInfo['delivery_id'];
                    }

                    $history_storagelife = array();
                    foreach($change_expire_bn as $item){
                        $dlyItemsStorageLifeInfo = $dlyItemsStorageLifeObj->getList('delivery_id', array('bn'=>$item['material_bn'],'delivery_id'=>$wmsDlyIds,'expire_bn'=>$item['expire_bn']), 0, 1);

                        $wms_delivery    = $wmsDlyObj->dump(array('delivery_id'=>$dlyItemsStorageLifeInfo[0]['delivery_id']), 'branch_id');

                        //params
                        $storagelifeItem = array(
                            'expire_bn' => $item['expire_bn'],
                            'nums' => $item['num'],
                            'bill_id' => $_POST['reship_id'],
                            'bill_bn' => $reship_bn,
                            'branch_id' => $item['instock_branch'],
                            'material_bn' => $item['material_bn'],
                            'old_branch_id' => $wms_delivery['branch_id'],
                            'bill_type' => '30',
                            'bill_io_type' => '1',
                        );

                        $rs = $dlyItemsStorageLifeLib->returnProduct($storagelifeItem, $err_msg, $return_storagelife);
                        if(!$rs){
                            $url = 'index.php?app=ome&ctl=admin_return_sv&act=edit&p[0]='.$_POST['reship_id'];
                            $this->end(false, $this->app->_('保质期批次退入失败'), $url , array('msg'=>$err_msg));
                        }else{
                            $history_storagelife[] = $return_storagelife;
                        }
                    }

                    //write history storagelife
                    kernel::single('ome_receipt_dlyitemsstoragelife')->returnProduct($history_storagelife, $msg);
                    //保存记录
                    kernel::single('console_useful_life')->returnProduct($history_storagelife);
                }

                $return_id=$_POST['return_id'];
                $oProduct_pro->changeverify($_POST['por_id'],$_POST['reship_id'],$return_id,$_POST['process_memo']);
                $return_product_detail = $oProduct_pro->dump(array('por_id'=>$_POST['por_id']), 'verify');
                
                # 质检成功后的操作
                if ($return_product_detail['verify'] == 'true'){
                    //库存管控 质检入库
                    $storeManageLib = kernel::single('ome_store_manage');
                    $storeManageLib->loadBranch(array('branch_id'=>$branch_id));
                    $params_stock = array(
                            "params" => array('por_id' => $_POST['por_id']),
                            "node_type" => "confirmReshipReturn",
                    );
                    $result_store_manage = $storeManageLib->processBranchStore($params_stock, $err_msg);
                    if (!$result_store_manage){
                        $url = 'index.php?app=ome&ctl=admin_return_sv&act=edit&p[0]='.$_POST['reship_id'];
                        $this->end(false, $this->app->_('质检入库失败'), $url , array('msg'=>$err_msg));
                    }
                    
                    //质检成功后，根据退换货类型生成相应的单据
                    if ($status == '8') {
                        $oReship->finish_aftersale($_POST['reship_id']);
                        //批次回写
                    }else if($return_id){
                        //售后申请状态更新同步
                        foreach(kernel::servicelist('service.aftersale') as $object=>$instance){
                            if(method_exists($instance,'update_status')){
                                $instance->update_status($return_id);
                            }
                        }
                    }

                }

                $this->end(true, app::get('base')->_('质检成功'));
                break;
            default:

                $this->end(true, app::get('base')->_('质检失败'));
                break;
        }

    }

    /**
     * @description 重新质检
     * @access public
     * @param void
     * @return void
     */
    public function recheck($reship_id)
    {
        $this->begin();
        if (!$reship_id) {
            $this->end(false,$this->app->_('退换货单不能为空!'));
        }

        # 删除收货记录
        $this->app->model('return_process')->delete(array('reship_id'=>$reship_id));
        $this->app->model('return_process_items')->delete(array('reship_id'=>$reship_id));

        # 更改状态
        $this->app->model('reship')->update(array('is_check'=>'1'),array('reship_id'=>$reship_id));

        # 删除操作记录
        //$this->app->model('operation_log')->delete(array('obj_type' => 'reship@ome','obj_id'=>$reship_id));
        $this->app->model('operation_log')->write_log('reship@ome',$reship_id,'重新质检');

        $this->end(true,$this->app->_('退换货单不能为空!'),'javascript:finderGroup["'.$_GET['finder_id'].'"].refresh.delay(400,finderGroup["'.$_GET['finder_id'].'"]);');
    }
    
    /**
     * Ajax保质期物料质检
     * @param $_POST
     * @return void
     */
    public function ajaxCheckExpire(){
        $reship_id = $_POST['reship_id'];
        $expire_bn = $_POST['expire_bn'];
        if(empty($reship_id) || empty($expire_bn)){
            echo json_encode(array('res'=>'fail', 'msg'=>'无效操作，请检查'));
            exit;
        }
        $oDelivery = app::get('ome')->model('delivery');
        $wmsDelivery = app::get('wms')->model('delivery');
        $lib_ome_return_process = kernel::single('ome_return_process');
        $delivery_list = $lib_ome_return_process->get_delivery_list_by_reship_id($reship_id);
        if(isset($delivery_list["error_msg"])){
            echo json_encode(array('res'=>'fail', 'msg'=>$delivery_list["error_msg"]));
            exit;
        }
        //检查保质期条码
        $dlyItemsSLObj = app::get('wms')->model('delivery_items_storage_life');
        foreach ($delivery_list as $key => $val){
            if($val['parent_id'] > 0){ //合并发货单信息
                $parent_delivery = $oDelivery->dump(array('delivery_id'=>$val['parent_id']), 'delivery_id, delivery_bn');
                $parent_delivery_bn = $parent_delivery['delivery_bn'];
            }else{
                $parent_delivery_bn = $val['delivery_bn'];
            }
            #wms发货单
            $wms_delivery = $wmsDelivery->dump(array('outer_delivery_bn'=>$parent_delivery_bn), 'delivery_id, delivery_bn');
            $filter = array('delivery_id'=>$wms_delivery['delivery_id'], 'bm_id'=>$val['product_id'], 'expire_bn'=>$expire_bn);
            $get_expire = $dlyItemsSLObj->dump($filter,'itemsl_id');
            if($get_expire){
                echo json_encode(array('res'=>'succ', 'expire_bn'=>$expire_bn, 'msg'=>'保质期条码：'.$expire_bn.',检测成功'));
                exit;
            }
        }
        echo json_encode(array('res'=>'fail', 'msg'=>'保质期条码：'.$expire_bn.',检测失败'));
        exit;
    }
    
    //ajax加载保质下拉框
    public function ajax_load_expire(){
        $obj_type = $_POST["obj_type"]; //所选的类型（基础物料编码，唯一码，条形码）
        $input_code = $_POST["code"]; //input框填写的内容
        $reship_id = $_POST["reship_id"];
        $checked_expire = $_POST["checked_expire"]; //json格式的已扫描的保质期条码(会有重复的保质期条码，用来计算数量)
       //判断是否是保质期商品(先获取bm_id)
       $mdl_ma_ba_ma = app::get('material')->model('basic_material');
       $mdl_ome_dly_items_serial = app::get('ome')->model('delivery_items_serial');
       $lib_ma_ba_ma_ba = kernel::single('material_basic_material_barcode');
       $lib_ome_return_process = kernel::single('ome_return_process');
       $lib_ma_storagelife = kernel::single('material_storagelife');
       //根据reship_id获取相应发货单信息
       $delivery_list = $lib_ome_return_process->get_delivery_list_by_reship_id($reship_id);
       if(isset($delivery_list["error_msg"])){
           echo json_encode(array('res'=>'succ', 'msg'=>$delivery_list["error_msg"]));
           exit;
       }
       //获取ome的delivery_id
       $ome_delivery_ids = array();
       foreach($delivery_list as $var_dly_li){
           if(!in_array($var_dly_li["delivery_id"],$ome_delivery_ids)){
               $ome_delivery_ids[] = $var_dly_li["delivery_id"];
           }
       }
       switch($obj_type){
           case "material_bn":
               $rs_ma = $mdl_ma_ba_ma->dump(array("material_bn"=>$input_code));
               $bm_id = $rs_ma["bm_id"];
               break;
           case "barcode":
               $bm_id = $lib_ma_ba_ma_ba->getIdByBarcode($input_code);
               break;
           case "serial_number":
               $current_item = $mdl_ome_dly_items_serial->dump(array("delivery_id"=>$ome_delivery_ids,"serial_number"=>$input_code),"product_id");
               $bm_id = $current_item["product_id"];
               break;
       }
       //检查基础物料是否是保质期类型
       $is_use_expire = $lib_ma_storagelife->checkStorageLifeById($bm_id);
       if(!$is_use_expire){//没开启保质期
           echo json_encode(array('res'=>'succ','use_expire'=>false));
           exit;
       }
       //获取可选择的保质期条码
       $mdl_ome_dly_items_sl = app::get('ome')->model('delivery_items_storagelife');
       $filter_get_expire_bn = array("delivery_id"=>$ome_delivery_ids,"bm_id"=>$bm_id);
       $expire_info = $mdl_ome_dly_items_sl->getList("expire_bn,number",$filter_get_expire_bn);
       $arr_expire_bn = array();
       //已扫描的保质期条码信息
       if($checked_expire){
           $arr_checked_expire = json_decode($checked_expire);
           $arr_checked_expire_num = array(); //已检查的保质期条码值和数的数组
           foreach($arr_checked_expire as $var_ace){
               if(isset($arr_checked_expire_num[$var_ace])){
                   $arr_checked_expire_num[$var_ace] = $arr_checked_expire_num[$var_ace]+1;
               }else{
                   $arr_checked_expire_num[$var_ace] = 1;
               }
           }
           $arr_expire_num = array(); //数据库中保质期条码值和数的数组
           foreach($expire_info as $var_ei){
               if(isset($arr_expire_num[$var_ei["expire_bn"]])){
                   $arr_expire_num[$var_ei["expire_bn"]] = $arr_expire_num[$var_ei["expire_bn"]]+$var_ei["number"];
               }else{
                   $arr_expire_num[$var_ei["expire_bn"]] = $var_ei["number"];
               }
           }
           //以数据为准比较已检查的保质期条码
           foreach($arr_expire_num as $key_expire_bn => $value_number){
               if(isset($arr_checked_expire_num[$key_expire_bn])){
                   if($value_number > $arr_checked_expire_num[$key_expire_bn]){
                       $arr_expire_bn[] = $key_expire_bn;
                   }
               }else{
                   $arr_expire_bn[] = $key_expire_bn;
               }
           }
       }else{ //无已检查的保质期条码 直接拿数据库的值
           foreach($expire_info as $var_ei){
               if(!in_array($var_ei["expire_bn"],$arr_expire_bn)){
                   $arr_expire_bn[] = $var_ei["expire_bn"];
               }
           }
       }
       if(empty($arr_expire_bn)){
           echo json_encode(array('res'=>'fail', 'msg'=>"该商品已无可用的保质期条码了"));
           exit;
       }
       echo json_encode(array('res'=>'succ','use_expire'=>true,"arr_expire_bn"=>$arr_expire_bn));
       exit;
    }
    
}
