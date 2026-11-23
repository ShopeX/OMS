<?php
/**
 * 退货单
 * @author ome team
 * @copyright www.shopex.cn 2010.4.27
 */
class purchase_ctl_admin_returned_purchase extends desktop_controller{

    var $name = "退货单";
    var $workground = "purchase_manager";


    /**
     * 退货单显示
     * @param number
     * @return string
     */
    function index($rp_type=NULL, $io=null){

        //列表标题及过滤条件
        switch($rp_type)
        {
            case 'po':
                $sub_title = "入库取消单";
                break;
            case 'eo':
                $sub_title = "采购退货单";
                break;
            default:
                $sub_title = "退货单";
        }
        $params = array(
            'title'=>$sub_title,
            'actions' => array(
                array(
                    'label' => '新建',
                    'href' => 'index.php?app=purchase&ctl=admin_returned_purchase&act=add',
                    'target' => '_blank',
                ),
                array(
                    'label' => '导出模板',
                    'href' => 'index.php?app=purchase&ctl=admin_returned_purchase&act=exportTemplate',
                    'target' => '_blank',
                ),
            ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => true,
            'use_buildin_filter' => true,
            'finder_cols'=>'column_edit,supplier_id,name,product_cost,delivery_cost,amount,logi_no,return_status,operator',
            'orderBy' => 'returned_time desc'
        );
        if($rp_type){
            $params['base_filter']['rp_type'] = $rp_type;
        }
        $this->finder('purchase_mdl_returned_purchase', $params);
    }

    function oList() {
        $this->workground = 'storage_center';
        $params = array(
            'title'=> '采购退货',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'orderBy' => 'returned_time desc',
            'base_filter' => array('rp_type'=>'eo','return_status'=>'1'),
        	'finder_cols'=>'column_edit,supplier_id,name,product_cost,delivery_cost,amount,logi_no,return_status,operator',
        );
        $this->finder('purchase_mdl_returned_purchase', $params);
    }


    function exportTemplate(){
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=CT".date('Ymd').".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
         //采购-采购退货单-模板-导出
        $logParams = array(
            'app' => $this->app->app_id,
            'ctl' => trim($_GET['ctl']),
            'act' => trim($_GET['act']),
            'modelFullName' => '',
            'type' => 'export',
            'params' => array(),
        );
        ome_operation_log::insert('purchase_purchaseReturn_template_export', $logParams);
        $pObj = $this->app->model('returned_purchase');
        $title1 = $pObj->exportTemplate('return');
        $title2 = $pObj->exportTemplate('item');
        echo '"'.implode('","',$title1).'"';
        echo "\n\n";
        echo '"'.implode('","',$title2).'"';
    }

    /**
     * 保存详情
     *
     */
    function doDetail(){
        $this->begin('index.php?app=purchase&ctl=admin_returned_purchase');
        if (empty($_POST['rp_id'])){
            $this->end(false, '操作出错，请重新操作');
        }
        $rpObj = $this->app->model('returned_purchase');
        $rpObj->save($_POST);
        $this->end(true, '操作成功');
    }

    /*
     * 追加备注 append_memo
     */
    function append_memo(){

        $rpObj = $this->app->model('returned_purchase');
        $rp['rp_id'] = $_POST['rp_id'];
        if ($_POST['oldmemo']){
            $oldmemo = $_POST['oldmemo'].'<br/>';
        }
        $memo = $oldmemo.$_POST['memo'].' &nbsp;&nbsp;('.date('Y-m-d H:i',time()).' by '.kernel::single('desktop_user')->get_name().')';
        $rp['memo'] = $memo;
        $rpObj->save($rp);
        echo $memo;
    }

    /*
     * 退货单明细
     * @param rp_id
     */
    function rp_items($rp_id=null, $type=null){
        if ($rp_id){
            $oReturned = app::get('purchase')->model('returned_purchase');
            if ($type=='eo'){
                //$detail = $oReturned->returned_purchase_items($refundDetail['rp_id']);
            }elseif ($type=='po'){
                $detail = $oReturned->returned_purchase_items($rp_id);
            }
        }
        $this->pagedata['detail'] = $detail;
        $this->display("admin/eo/eo_cancel_items.html");
    }

    /**
     * 新建采购退货单
     *
     */
    function add(){
        $suObj = $this->app->model('supplier');
        $data  = $suObj->getList('supplier_id, name','',0,-1);

        $brObj = app::get('ome')->model('branch');
        $row = $brObj->getList('branch_id, name','',0,-1);

        /*
         * 获取操作员管辖仓库
         */
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
           $branch_list = $brObj->getBranchByUser();
        }
        $this->pagedata['branch_list'] = $branch_list;
        $is_super = 1;
        $this->pagedata['is_super'] = $is_super;

        $this->pagedata['supplier'] = $data;
        $operator = kernel::single('desktop_user')->get_name();
        $this->pagedata['operator'] = $operator;

        $this->pagedata['branch'] = $row;
        $this->pagedata['branchid'] = $branch_id;
        $this->pagedata['cur_date'] = date('Ymd',time()).'采购退货单';
        $this->singlepage("admin/returned/purchase/purchase_add.html");
    }

    function getEditProducts($rp_id)
    {
        $basicMaterialObj        = app::get('material')->model('basic_material');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        $basicMaterialSelect    = kernel::single('material_basic_select');
        if ($rp_id == ''){
            $rp_id = $_POST['p[0]'];
        }
        
        $piObj = $this->app->model('returned_purchase_items');
        $items = $piObj->getList('item_id,product_id,num,price,bn,name,spec_info,barcode',array('rp_id'=>$rp_id),0,-1);
        $filter = array(
            'bm_id|in'=>array_column($items,'product_id')
        );
        $extData    = $basicMaterialSelect->getlist_ext('bm_id, material_bn, material_name, visibled, retail_price, cost, specifications,purchasing_price', $filter);
        if ($extData) {
            $extData = array_column($extData,null,'product_id');
        }
        foreach ($items as $key=>$value) {
            
            #条形码
            $barcode_val    = $basicMaterialBarcode->getBarcodeById($value['product_id']);
            $items[$key]['barcode'] = $barcode_val;
            
            $items[$key]['visibility'] = &$product[$value['product_id']]['visibility'];
            $items[$key]['purchasing_price'] = $extData[$value['product_id']]['purchasing_price'];
            $product_ids[] = $value['product_id'];
        }
        if ($product_ids)
        {
            $plist    = $basicMaterialObj->getList('bm_id, material_bn, visibled', array('bm_id'=>$product_ids), 0, -1);
            
            foreach ($plist as $key=>$value) {
                $product[$value['product_id']]['visibility'] = ($value['visibled'] ? 'true' : 'false');
            }
            unset($plist);
        }
        echo json_encode($items);
    }

    function getBranchStore()
    {
        $libBranchProduct    = kernel::single('ome_branch_product');
        
        $productIds = explode('_', $_POST['product_id']);
        $branch_id = intval($_POST['branch_id']);
        
        // 过滤数组的空值
        $productIds = array_filter($productIds);
        
        $row = $libBranchProduct->getStoreListByBranch($branch_id, $productIds);
        echo json_encode($row);
    }

    function doSave()
    {
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $this->begin();
        $at = $_POST['at'];
        $pr = $_POST['pr'];
        $name = $_POST['purchase_name'];
        $emergency = ($_POST['emergency']=='true')?'true':'false';
        $supplier = $_POST['supplier'];
        $branch = $_POST['branch'];
        $memo = $_POST['memo'];
        $operator = $_POST['operator'];
        $d_cost = $_POST['d_cost'];
        $total = 0;

        if (empty($supplier)){
            $this->end(false, '请输入供应商', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
        }
        if(empty($at) || empty($pr)){
            $this->end(false, '采购退货单中必须有商品', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
        }
        if ($at)$oBranchProduct = app::get('ome')->model('branch_product');
        foreach ($at as $k => $a){
            if (!$a){
                $this->end(false, '请输入退货数量', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
            }

            if (!is_numeric($a) || $a < 1){
                $this->end(false, '退货数量必须为数字且大于0', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
            }

            $aRow = $oBranchProduct->dump(array('product_id'=>$k, 'branch_id'=>$branch),'store');
            if($a > $aRow['store']){
                $this->end(false, '退货数量不可大于库存数量.');
            }
            $ids[] = $k;
            $total += $a*$pr[$k];
            unset($k,$a);
        }
        if ($pr)
        foreach ($pr as $p){
            if ($p<0){
                $this->end(false, '请完成单价的填写', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
            }

            if (!is_numeric($p) || $p <= 0 ){
                $this->end(false, '单价必须为数字且大于0', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
            }
            unset($p);
        }
        //判断供应商是否存在
        $oSupplier = $this->app->model('supplier');
        $supplier_ = $oSupplier->dump(array('name'=>$supplier), 'supplier_id');
        if (!$supplier_['supplier_id']){
            $this->end(false, '输入的供应商不存在！', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
        }
        if ($branch == ''){
            $this->end(false, '请选择仓库', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
        }

        $oPurchase = $this->app->model('returned_purchase');
        $rp_bn = $oPurchase->gen_id();
        $data['rp_bn'] = $rp_bn;
        $data['name'] = $name;
        $data['supplier_id'] = $supplier_['supplier_id'];
        $data['operator'] = $operator;
        $data['emergency'] = $emergency;
        $data['branch_id'] = $branch;
        $data['amount'] = $total+$d_cost;
        $data['product_cost'] = $total;
        $data['delivery_cost'] = $d_cost;
        $data['logi_no'] = $_POST['logi_no'];
        $data['returned_time'] = time();
        $data['rp_type'] = 'eo';
        $data['po_type'] = 'cash';
        if ($memo){
            $op_name = kernel::single('desktop_user')->get_login_name();
            $newmemo = array();
            $newmemo[] = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$memo);
        }
        $data['memo'] = serialize($newmemo);
        $rs = $oPurchase->save($data);
        if ($rs){
            $rp_id = $data['rp_id'];
            $oPurchase_items = $this->app->model("returned_purchase_items");
            
            if ($ids)
            foreach ($ids as $i){//插入采购退货单详情
                
                $p    = $basicMaterialLib->getBasicMaterialExt($i);
                
                $row = array();
                $row['rp_id'] = $rp_id;
                $row['product_id'] = $i;
                $row['num'] = $at[$i];
                $row['price'] = sprintf('%.2f',$pr[$i]);
                $row['bn'] = $p['material_bn'];
                $row['barcode'] = $p['barcode'];
                $row['name'] = $p['material_name'];
                $row['spec_info'] = $p['specifications'];
                $oPurchase_items->save($row);
                unset($i,$p);
            }
            //--生成退货单日志记录
            $log_msg = '生成了编号为:'.$rp_bn.'的采购退货单';
            $opObj = app::get('ome')->model('operation_log');
            $opObj->write_log('purchase_refund@purchase', $rp_id, $log_msg);
            $this->end(true, '已完成');
        }
        $this->end(false, '未完成', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
    }

    /**
     * 修改退货单
     *
     */
    function editReturn($rp_id){
        $this->begin('index.php?app=purchase&ctl=admin_returned_purchase&act=index');
        if (empty($rp_id)){
            $this->end(false,'操作出错，请重新操作');
        }
        $rpObj = $this->app->model('returned_purchase');
        $suObj = $this->app->model('supplier');
        $brObj = app::get('ome')->model('branch');
        
        $data = $rpObj->dump($rp_id, '*', array('returned_purchase_items' => array('*')));
        //当前供应商
        $supplier_detail = $suObj->dump($data['supplier_id'], 'supplier_id,name');
        $this->pagedata['supplier_detail'] = $supplier_detail;

        /*编辑不允许改变仓库，所以默认为单仓库
        //获取仓库模式
        $branch_mode = app::get('ome')->getConf('ome.branch.mode');
        */
        if (!$branch_mode){
            $branch_mode = 'single';
        }
        $this->pagedata['branch_mode'] = $branch_mode;

        $su = $suObj->dump($data['supplier_id'],'name');
        $br = $brObj->dump($data['branch_id'], 'name');
        $data['branch_name']   = $br['name'];
        $data['supplier_name'] = $su['name'];
        $this->pagedata['po_items'] = $data['returned_purchase_items'];
        $data['memo'] = unserialize($data['memo']);
        $this->pagedata['po'] = $data;
        $this->singlepage("admin/returned/purchase/purchase_edit.html");
    }

    function doEdit()
    {
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $this->begin();
        $rp_id = $_POST['rp_id'];
        $rpObj = $this->app->model('returned_purchase');
        $rp_itemObj = $this->app->model('returned_purchase_items');
        $data = $rpObj->dump($rp_id, '*', array('returned_purchase_items'=>array('*')));
        $at = $_POST['at'];
        $pr = $_POST['pr'];
        $branch = $_POST['branch'];
        $d_cost = $_POST['d_cost'];
        $total = 0;
        if($data['return_status']==2){
            $this->end(false, '退货已完成，不允许编辑', 'index.php?app=purchase&ctl=admin_returned_purchase&act=editReturn');
        }
        if(empty($at) || empty($pr)){
            $this->end(false, '退货单中必须有商品', 'index.php?app=purchase&ctl=admin_returned_purchase&act=editReturn');
        }
        foreach ($data['returned_purchase_items'] as $v){
            $p_id = $v['product_id'];
            if (empty($at[$p_id])){
                $del_item_id[] = $v;
            }
            unset($v);
        }
        if ($del_item_id){
            foreach ($del_item_id as $item){//删除详情
                $rp_itemObj->delete(array('item_id'=>$item['item_id']));
                unset($item);
            }
        }
        if ($pr)
        foreach ($pr as $p){
            if ($p<0){
                $this->end(false, '请完成单价的填写', 'index.php?app=purchase&ctl=admin_purchase&act=editPo');
            }
            if (!is_numeric($p) || $p <= 0 ){
                $this->end(false, '单价必须为数字且大于0', 'index.php?app=purchase&ctl=admin_purchase&act=editPo');
            }
            if ($p <= 0){
                $this->end(false, '采购数量必须大于零', 'index.php?app=purchase&ctl=admin_purchase&act=editPo');
            }
            unset($p);
        }
        $oBranchProduct = app::get('ome')->model('branch_product');
        foreach ($at as $k => $a){
            if (!$a){
                $this->end(false, '请输入采购数量', 'index.php?app=purchase&ctl=admin_purchase&act=editPo');
            }

            if (!is_numeric($a) || $a < 1 ){
                $this->end(false, '采购数量必须为数字且大于0', 'index.php?app=purchase&ctl=admin_purchase&act=editPo');
            }

            $aRow = $oBranchProduct->dump(array('product_id'=>$k, 'branch_id'=>$branch),'store');
            if($a > $aRow['store']){
                $this->end(false, '退货数量不可大于库存数量.');
            }
            //$edit_pi = array();
            $pi = $rp_itemObj->dump(array('rp_id'=>$rp_id,'product_id'=>$k));
            if ($pi){
                if ($a != $pi['num'] || $pr[$k] != $pi['price']){
                    $edit_pi[$k]['item_id'] = $pi['item_id'];
                    $edit_pi[$k]['num'] = $a;
                    $edit_pi[$k]['price'] = $pr[$k];
                    $total += $a*$pr[$k];
                    $ids[] = $k;
                    continue;
                }
                $total += $a*$pr[$k];
            }else {
                $edit_pi[$k]['num'] = $a;
                $edit_pi[$k]['price'] = $pr[$k];
                $total += $a*$pr[$k];
                $ids[] = $k;
            }
            unset($k,$a,$aRow,$pi);
        }
        //追加备注信息
        $memo = array();
        $oldmemo= unserialize($data['memo']);
        if ($oldmemo)
        foreach($oldmemo as $k=>$v){
            $memo[] = $v;
            unset($v);
        }
        $newmemo =  htmlspecialchars($_POST['memo']);
        if ($newmemo){
            $op_name = kernel::single('desktop_user')->get_name();
            $memo[] = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$newmemo);
        }
        $edit_memo = serialize($memo);

        $rp = array();
        $rp['rp_id'] = $rp_id;
        $rp['name'] = $_POST['purchase_name'];
        $rp['emergency'] = ($_POST['emergency']=='true')?'true':'false';
        $rp['operator'] = $_POST['operator'];
        $rp['memo'] = $edit_memo;
        $rp['amount'] = $total+$d_cost;
        $rp['product_cost'] = $total;
        $rp['delivery_cost'] = $d_cost;
        $rp['logi_no'] = $_POST['logi_no'];
        $rpObj->save($rp);//更新退货单
        
        if ($ids)
        foreach ($ids as $i){//插入退货单详情
            
            $p    = $basicMaterialLib->getBasicMaterialExt($i);
            
            $row = $edit_pi[$i];
            $row['rp_id'] = $rp_id;
            $row['product_id'] = $i;
            $row['num'] = $at[$i];
            $row['price'] = sprintf('%.2f', $pr[$i]);
            $row['bn'] = $p['material_bn'];
            $row['barcode'] = $p['barcode'];
            $row['name'] = $p['material_name'];
            $row['spec_info'] = $p['specifications'];
            $rp_itemObj->save($row);
            unset($i,$p,$row);
        }

        //--修改退货单日志记录
        $log_msg = '修改了编号为:'.$data['rp_bn'].'的退货单';
        $opObj = app::get('ome')->model('operation_log');
        $opObj->write_log('purchase_modify@purchase', $rp_id, $log_msg);
        $this->end(true, '已完成');
    }

     /**
     * 退货出库
     *
     */
    function purchaseShift($rp_id){
        $this->begin('index.php?app=purchase&ctl=admin_returned_purchase&act=oList');
        if (empty($rp_id)){
            $this->end(false,'操作出错，请重新操作');
        }
        $rpObj = $this->app->model('returned_purchase');
        $suObj = $this->app->model('supplier');
        $brObj = app::get('ome')->model('branch');
        
        $data = $rpObj->dump($rp_id, '*', array('returned_purchase_items' => array('*')));
        //当前供应商
        $supplier_detail = $suObj->dump($data['supplier_id'], 'supplier_id,name');
        $this->pagedata['supplier_detail'] = $supplier_detail;

        /*编辑不允许改变仓库，所以默认为单仓库
        //获取仓库模式
        $branch_mode = app::get('ome')->getConf('ome.branch.mode');
        */
        if (!$branch_mode){
            $branch_mode = 'single';
        }
        $this->pagedata['branch_mode'] = $branch_mode;

        $su = $suObj->dump($data['supplier_id'],'name');
        $br = $brObj->dump($data['branch_id'], 'name');
        $data['branch_name']   = $br['name'];
        $data['supplier_name'] = $su['name'];
        $this->pagedata['po_items'] = $data['returned_purchase_items'];
        $data['memo'] = unserialize($data['memo']);
        $this->pagedata['po'] = $data;
        $this->singlepage("admin/returned/purchase/purchase_shift.html");
    }

     /**
     * 保存退货出库
     *
     */
    function doShift() {
        $this->begin('index.php?app=purchase&ctl=admin_returned_purchase&act=oList');


        $rp_id = $_POST['rp_id'];
        $at = $_POST['at'];
        $pr = $_POST['pr'];
        $ato = $_POST['at_o'];
        $ids = $_POST['ids'];

        $basicMaterialLib    = kernel::single('material_basic_material');
        $basicMaterialStockObj = app::get('material')->model('basic_material_stock');
        
        $rpObj = $this->app->model('returned_purchase');
        $rp_itemObj = $this->app->model('returned_purchase_items');
        
        $data = $rpObj->dump($rp_id, '*', array('returned_purchase_items'=>array('*')));

        $total = 0;
        if(empty($at) || empty($pr)){
            $this->end(false, '暂无出库货品', 'index.php?app=purchase&ctl=admin_returned_purchase&act=purchaseShift');
        }
        foreach($at as $k=>$v){
            if($v != $ato[$k]){
               $this->end(false, '出库数量与退货数量不符', 'index.php?app=purchase&ctl=admin_returned_purchase&act=purchaseShift');
            }
            unset($v);
        }

        foreach($ids as $k=> $i){
            $rp_items = $rp_itemObj->dump($i,'price,product_id,num,barcode,name,spec_info,bn');

            //基础物料信息和库存
            $Products              = $basicMaterialLib->getBasicMaterialExt($rp_items['product_id']);
            $basicMateriaStock    = $basicMaterialStockObj->dump(array('bm_id'=>$Products['bm_id']), 'store');
            
            $Products['store']    = $basicMateriaStock['store'];
            
            if(app::get('taoguaninventory')->is_installed()){
                 $check_inventory = kernel::single('taoguaninventory_inventorylist')->checkproductoper($rp_items['product_id'],$data['branch_id']);

                if(!$check_inventory){
                    $this->end(false, '此商品正在盘点中，不可以出入库操作!', 'index.php?app=purchase&ctl=admin_returned_purchase&act=purchaseShift');
                }
            }
            $total += $at[$k]*$rp_items['price'];
            $shift_items[$rp_items['product_id']] = array(
                'product_id' => $rp_items['product_id'],
                'product_bn' => $rp_items['bn'],
                'name' => $rp_items['name'],
                'spec_info' => $rp_items['spec_info'],
                'bn' => $rp_items['bn'],
                'unit' => $Products['unit'],
                'store' => $Products['store'],
                'price' => $rp_items['price'],//1212增加
                'nums' => $at[$k],
            );
            unset($i,$rp_items,$Products);
        }

        foreach($shift_items as $v){
            if($v['nums']<=0){
               $this->end(false, '产品条码: ' . $v['product_bn'].' 出库数量必须大于0', 'index.php?app=purchase&ctl=admin_returned_purchase&act=purchaseShift');
            }
            if($v['nums'] > $v['store']){
               $this->end(false, '产品条码: ' . $v['product_bn'].' 出库数量大于实际库存', 'index.php?app=purchase&ctl=admin_returned_purchase&act=purchaseShift');
            }
            unset($v);
        }

        //追加备注信息
        $memo = array();
        $op_name = kernel::single('desktop_user')->get_name();
        $newmemo =  htmlspecialchars($_POST['memo']);
        $memo[] = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$newmemo);
        $memo = serialize($memo);

        $iostock_instance = kernel::service('taoguaniostockorder.iostockorder');
        $shift_data = array (
                'iostockorder_name' => date('Ymd').'出库单',
                'supplier' => $_POST['supplier'],
                'supplier_id' => $_POST['supplier_id'],
                'branch' => $_POST['branch'],
                'type_id' => ome_iostock::PURCH_RETURN,
                'iso_price' => 0,
                'memo' => $newmemo,
                'operator' => $_POST['operator'],
                'products' => $shift_items,
                'original_bn' => $data['rp_bn'],
                'original_id' => $rp_id,
       			'confirm' => 'Y',
        );

        if ( method_exists($iostock_instance, 'save_iostockorder') ){
            $iostock_instance->save_iostockorder($shift_data, $msg);
        }

       //更新采购退货单状态
       $rp_data = array(
           'return_status' => 2,
           'rp_id' => $rp_id,
       );
       $rpObj->save($rp_data);

       //生成退款单
       /* diabled by yewei 110915
       if($data['po_type'] == 'cash'){
            $refund['add_time'] = time();
            $refund['refund'] = $total;
            $refund['product_cost'] = $data['product_cost'];
            $refund['delivery_cost'] = $data['delivery_cost'];
            $refund['po_type'] = $data['po_type'];
            $refund['type'] = 'eo';
            $refund['rp_id'] = $rp_id;
            $refund['supplier_id'] = $data['supplier_id'];
            kernel::single('purchase_refunds')->save_refunds($refund, $msg);
       }
       */
       $this->end(true, '出库成功');
    }

    /**
     * 打印退货单
     *
     * @param int $rp_id
     */
    function printItem($rp_id){
        $rpObj = $this->app->model('returned_purchase');
        $suObj = $this->app->model('supplier');
        $brObj = app::get('ome')->model('branch');
        
        $brpObj = app::get('ome')->model('branch_product_pos');
        $rp = $rpObj->dump($rp_id, '*', array('returned_purchase_items'=>array('*')));
        $brposObj = app::get('ome')->model('branch_pos');
        $su = $suObj->dump($rp['supplier_id'],'name');
        $bran = $brObj->dump($rp['branch_id'],'name');
        $rp['supplier'] = $su['name'];
        $rp['branch'] = $bran['name'];
        $rp['memo'] = unserialize($rp['memo']);
        $items = $rp['returned_purchase_items'];
       
        $total = 0;
        foreach ($items as $ik=>$iv) {
            $brp = $brpObj->dump(array('product_id'=>$iv['product_id'],'branch_id'=>$rp['branch_id']),'pos_id');

            $brpos = $brposObj->dump($brp['pos_id'],'store_position');
            $items[$ik]['store_position'] = $brpos['store_position'];
            $total+=$iv['num'];
        }
        
         // 对usort进行扩展，对多位数组进行值的排序
        function cmp($a, $b) {
            return strcmp($a["store_position"], $b["store_position"]);
        }
        usort($items, "cmp");
        $rp['po_items'] = $items;
        $rp['total'] = $total;
        $this->pagedata['po'] = $rp;
        $this->pagedata['time'] = time();
        $this->pagedata['base_dir'] = kernel::base_url();

        kernel::single('ome_print_otmpl')->printOTmpl($_GET['otmplId'],'purreturn',$this);
        /*
        $this->_systmpl = app::get('ome')->model('print_tmpl_diy');
        $this->_systmpl->singlepage('purchase','admin/returned/return_print',$this->pagedata);
        $this->display("admin/prints.html");
        */
    }

    /**
     * 拒绝退货
     *
     * @param int $po_id
     */
    function cancel($rp_id){
        $rpObj = $this->app->model('returned_purchase');
        if(count($_POST)>0){
            $rp_id = $_POST['rp_id'];
            $rp = $rpObj->dump($rp_id, 'memo');
            $operator = $_POST['operator'];
            $this->begin('index.php?app=purchase&ctl=admin_returned_purchase&act=oList');
            if (empty($rp_id)){
                $this->end(false,'操作出错，请重新操作');
            }
            $memo = array();
            $oldmemo= unserialize($rp['memo']);
            if ($oldmemo){
                foreach($oldmemo as $k=>$v){
                    $memo[] = $v;
                }
            }
            $newmemo =  htmlspecialchars($_POST['memo']);
            if ($newmemo){
                $op_name = kernel::single('desktop_user')->get_name();
                $memo[] = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$newmemo, 'op_type'=>'return_cancel');
            }
            $edit_memo = serialize($memo);
            //更新采购退货单状态
            $rp_data = array(
               'return_status' => 3,
               'rp_id' => $rp_id,
            );
            $rpObj->save($rp_data);
            $this->end(true, '出库拒绝已完成');
        }else{
            $rp = $rpObj->dump($rp_id, 'supplier_id');
            $oSupplier = $this->app->model('supplier');
            $supplier = $oSupplier->dump($rp['supplier_id'], 'operator');
            $this->pagedata['operator'] = kernel::single('desktop_user')->get_name();
            $this->pagedata['id'] = $rp_id;
            $this->display("admin/returned/purchase/purchase_cancel.html");
        }
    }
    #使用扫描枪时，根据条形码,获取product_id
    function getProductId()
    {
        $barcode = $_POST['barcode'];
        
        echo NULL;
    }

}
