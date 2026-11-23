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

class console_ctl_admin_appropriation extends desktop_controller{
    var $name = "调拔计划";
    var $workground = "console_purchasecenter";
    function index(){

        $actions = array(
                array(
                    'label'=>'新建',
                    'href'=>'index.php?app=console&ctl=admin_appropriation&act=addtransfer',
                    'target'=>'_blank'
                    ),
                array(
                    'label'=>'导出模板',
                    'href'=>'index.php?app=console&ctl=admin_appropriation&act=exportTemplate',
                    'target'=>'_blank'
                    ),
                    
        );

        $params = array('title'=>'新建调拔单',
                        'use_buildin_new_dialog' => false,
                        'use_buildin_set_tag'=>false,
                        'use_buildin_recycle'=>false,
                        'use_buildin_export'=>true,
                        'use_buildin_import'=>true,
                        'use_buildin_filter'=>true,
                        'orderBy' => 'appropriation_id desc'
                    );
        /*
         * 获取操作员管辖仓库
         */
        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();

        //只要有一个仓库管理权限就显示新建调拨单按钮
        $params['actions'] = $actions;
        if (!$is_super){
            $branch_ids = $oBranch->getBranchByUser(true);
            $str_branch_id = $branch_ids ? '"' . implode('","', $branch_ids) . '"' : 0;
            $params['base_filter']['filter_sql'] = "({table}from_branch_id IN ($str_branch_id) OR {table}to_branch_id IN ($str_branch_id))";
    
            if(!$branch_ids){
                unset($params['actions']);
            }
        }

        $this->finder('taoguanallocate_mdl_appropriation', $params);
    }



     /*
    * 新建调拨单
    */
    function addtransfer(){
        $OBranch = app::get('ome')->model('branch');
        $branch  = $OBranch->getList('branch_id, name',array('type'=>array('main','damaged','aftersale')),0,-1);
        $allBranch  = $OBranch->getList('branch_id, name',array('is_ctrl_store'=>'1'),0,-1);
        
        /*
         * 获取操作员管辖仓库
         */
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
           $branch_list = $OBranch->getBranchByUser();
           if(count($branch_list)>1){
               $from_branch_check = $branch_list[0]['branch_id'];
               $to_branch_check = $branch_list[1]['branch_id'];
           }
        }else{
           $branch_list = $branch;
           if(count($branch)>1){
               $from_branch_check = $branch[0]['branch_id'];
               $to_branch_check = $branch[1]['branch_id'];
           }
        }
        
        #过滤o2o门店虚拟物流公司
        $oDly_corp = app::get('ome')->model('dly_corp');
        $dly_corp = $oDly_corp->getlist('*',array('disabled'=>'false', 'd_type'=>'1'));
        $this->pagedata['dly_corp'] = $dly_corp;
        
        $this->pagedata['from_branch_check'] = $from_branch_check;
        $this->pagedata['to_branch_check'] = $to_branch_check;

        $this->pagedata['all_branch']   = $allBranch;
        $this->pagedata['branch_list']   = $branch_list;
        $this->pagedata['is_super']   = $is_super;

        $this->pagedata['operator'] = kernel::single('desktop_user')->get_name();
        $this->pagedata['branch'] = $branch ;
        $appropriation_type = app::get('ome')->getConf('taoguanallocate.appropriation_type');
        if (!$appropriation_type) $appropriation_type = 'directly';
        $this->pagedata['appropriation_type'] = $appropriation_type;

        $this->singlepage("admin/appropriation/transfer.html");
    }

    function getProducts($from_branch_id = null,$to_branch_id = null)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $libBranchProduct    = kernel::single('ome_branch_product');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        
        $pro_id = $_POST['product_id'];
        $pro_bn= $_GET['bn'];
        $pro_barcode= $_GET['barcode'];

        $pro_name= $_GET['name'];

        if (is_array($pro_id)){
            $filter['bm_id'] = $pro_id;
        }

        if($pro_bn){

           $filter = array(
               'material_bn'=>$pro_bn
           );
        }

        if($pro_barcode)
        {
            #查询条形码对应的bm_id
            $bm_ids    = $basicMaterialBarcode->getBmidListByBarcode($pro_barcode);
            $filter = array('bm_id'=>$bm_ids);
        }

        if($pro_name){
            $filter = array(
               'material_name'=>$pro_name
           );
        }
        
        $data    = $basicMaterialSelect->getlist_ext('bm_id, material_bn, material_name, visibled, retail_price, specifications', $filter);

        $rows = array();
        $pids = array();
        if (!empty($data)){
            //$oBranchProduct = app::get('ome')->model('branch_product');
            
            foreach ($data as $k => $item){
                $pids[] = $item['product_id'];
            }

            foreach ($data as $k => $item)
            {
                #查询关联的条形码
                $item['barcode']    = $basicMaterialBarcode->getBarcodeById($item['product_id']);
                
                $item['price'] = app::get('purchase')->model('po')->getPurchsePrice($item['product_id'], 'asc');
                $item['num'] = (isset($bm_ids))?1:0;
                
                $fromBranch      = kernel::single('ome_store_manage');
                $fromBranch->loadBranch(array('branch_id'=>$from_branch_id));
               
                $item['from_branch_num']   = $fromBranch->processBranchStore(array(
                        'node_type' =>  'getAvailableStore',
                        'params'    =>  array(
                            'branch_id' =>  $from_branch_id,
                            'product_id'=>  $item['product_id'],
                        ),
                ), $err_msg);
                $toBranch = kernel::single('ome_store_manage');
                $toBranch->loadBranch(array('branch_id'=>$to_branch_id));
                $toParams = array('branch_id'=>$to_branch_id,'product_id'=>$item['product_id']);
                $item['to_branch_num']  = $toBranch->processBranchStore(
                    array(
                        'node_type' =>'getAvailableStore',
                        'params'    =>array(
                            'branch_id' =>$to_branch_id,
                            'product_id'=>$item['product_id'],
                        ),
                    ), $err_msg);
                
                #基础物料规格
                $item['spec_info']    = $item['specifications'];
                
                $rows[] = $item;
            }
        }

        echo "window.autocompleter_json=".json_encode($rows);
    }

     /*
     * 调拔单保存
     */
    function do_save(){
        $this->begin();

        $oAppropriation = app::get('taoguanallocate')->model('appropriation');
        $oBranch_product = app::get('ome')->model('branch_product');
        $channelLib = kernel::single('channel_func');
        $branchLib = kernel::single('ome_branch');
        $from_branch_id = $_POST['from_branch_id'];
        $to_branch_id = $_POST['to_branch_id'];
        $memo = $_POST['memo'];
        $nums = $_POST['at'];
        $from_branch_num = $_POST['from_branch_num'];
        $to_branch_num = $_POST['to_branch_num'];
        $operator = $_POST['operator'];
        $product_ids = $_POST['product_id'];
        $appropriation_type = $_POST['appropriation_type'];

        if(!$from_branch_id || !$to_branch_id){
           $this->end(false,'请选择调出仓库和调入仓库','index.php?app=console&ctl=admin_appropriation&act=addtransfer');
        }
        $from_wms_id = $branchLib->getWmsIdById($from_branch_id);
        $to_wms_id = $branchLib->getWmsIdById($to_branch_id);
        $from_is_selfWms = $channelLib->isSelfWms($from_wms_id);//调出仓库是否自有仓储
        $to_is_selfWms = $channelLib->isSelfWms($to_wms_id);//调入仓库是否自有仓储
        if($appropriation_type==1&&(!$from_is_selfWms||!$to_is_selfWms)){
            $this->end(false,'第三方仓库只能使用出入库调拨！','index.php?app=console&ctl=admin_appropriation&act=addtransfer');
        }
        if($from_branch_id == $to_branch_id){
            if($from_branch_id[$v] == $to_branch_id[$v]){
                $this->end(false,'调出仓库和新仓库不能是同一个','index.php?app=console&ctl=admin_appropriation&act=addtransfer');
            }
        }

        //保质期基础物料不能直接调拨
        if($appropriation_type == 1)
        {
            $basicMaterialObj = app::get('material')->model('basic_material');
            $basicMConfObj    = app::get('material')->model('basic_material_conf');
            $conf_bm_ids      = $basicMConfObj->getList('bm_id', array('bm_id'=>$product_ids, 'use_expire'=>1), 0, 5);
            
            if($conf_bm_ids)
            {
                $conf_bm_ids       = array_map('current', $conf_bm_ids);
                $basicMInfoList    = $basicMaterialObj->getList('material_bn', array('bm_id'=>$conf_bm_ids));
                $basicMInfoList    = array_map('current', $basicMInfoList);
                
                $this->end(false, '保质期基础物料不能直接调拨,物料编码：' . (implode(',', $basicMInfoList)), 'index.php?app=console&ctl=admin_appropriation&act=addtransfer');
            }
        }
        
        if(empty($nums)){
            $this->end(false, '调拨单中必须有商品', 'index.php?app=console&ctl=admin_appropriation&act=addtransfer');
        }

        foreach($nums as $product_id=>$num){
            if(app::get('taoguaninventory')->is_installed()){

            $check_inventory = kernel::single('taoguaninventory_inventorylist')->checkproductoper($product_id,$to_branch_id);

            if(!$check_inventory){
                $this->end(false, '此商品正在盘点中，不可以调拔!', 'index.php?app=console&ctl=admin_appropriation&act=addtransfer');
            }
             $check_inventory1 = kernel::single('taoguaninventory_inventorylist')->checkproductoper($product_id,$from_branch_id);

            if(!$check_inventory1){
                $this->end(false, '此商品正在盘点中，不可以调拔!', 'index.php?app=console&ctl=admin_appropriation&act=addtransfer');
            }
            }
           if($from_branch_num[$product_id]<intval($num)) {
                $this->end(false,'调拨数量('.$num.')不能大于库存数量('.$from_branch_num[$product_id].')','index.php?app=console&ctl=admin_appropriation&act=addtransfer');
           }

           if(intval($num)==0){
               $this->end(false,'调拨数量不可为0','index.php?app=console&ctl=admin_appropriation&act=addtransfer');
           }

           $adata[] = array('from_pos_id'=>0,'to_pos_id'=>0,'from_branch_id'=>$from_branch_id,'to_branch_id'=>$to_branch_id,'product_id'=>$product_id,'num'=>$num,'from_branch_num'=>$from_branch_num[$product_id],'to_branch_num'=>$to_branch_num[$product_id],'corp_id'=>$_POST['corp_id']);

        }
       
        $allocateObj = kernel::single('console_receipt_allocate');
        $result = $allocateObj->to_savestore($adata,$appropriation_type,$memo,$operator,$msg);
        if($result){

            //调拔出库通知已修改为审核时才发起通知
            #$iostockObj->notify_otherstock(1,$result,'create');
            $this->end(true,'调拔成功!','index.php?app=console&ctl=admin_appropriation');
        }else{

            $this->end(false,'调拔失败!','',array('msg'=>$msg));
        }

    }

    //获取采购物料列表
    function findProduct()
    {
        $base_filter    = array();
        
        $base_filter['visibled']    = 1;//过滤隐藏的商品
                $params = array(
                'title'=>'基础物料列表',
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'=>false,
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_filter'=>true,
                'base_filter' => $base_filter,
        );
        $this->finder('material_mdl_basic_material', $params);
    }

    /**
     * 打印调拔单
     */
    function printAppropriation($iso_id)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        
        $oAppropriation_items = app::get('taoguanallocate')->model('appropriation_items');
        $appropriation_obj = app::get('taoguanallocate')->model('appropriation');
        $memo = $appropriation_obj->dump(array('appropriation_id' => $iso_id), 'memo,appropriation_no');
        $items = $oAppropriation_items->select()->columns('*')->where('appropriation_id=?',$iso_id)->instance()->fetch_all();
        foreach ($items as $key => $item) {
            $items[$key]['spec_info'] = &$spec[$item['product_id']];
            $items[$key]['barcode'] = &$barcode[$item['product_id']];
            $items[$key]['frome_branch_store'] = &$frome_branch_store[$item['from_branch_id']][$item['product_id']];
            $items[$key]['to_branch_store'] = &$to_branch_store[$item['to_branch_id']][$item['product_id']];
    
            $product_id[] = $item['product_id'];
        }
        
        if ($items)
        {
            $productList = $basicMaterialSelect->getlist_ext('bm_id,specifications',array('bm_id'=>$product_id));
            foreach ($productList as $product)
            {
                $spec[$product['product_id']] = $product['specifications'];
                
                #查询关联的条形码
                $barcode[$product['product_id']] = $basicMaterialBarcode->getBarcodeById($product['product_id']);
            }
            
            $branch_products = app::get('ome')->model('branch_product')->getList('product_id,branch_id,store',array('product_id'=>$product_id,'branch_id'=>array($items[0]['from_branch_id'],$items[0]['to_branch_id'])));
            foreach ($branch_products as $key => $value) {
                $frome_branch_store[$value['branch_id']][$value['product_id']] = $value['store'];
                $to_branch_store[$value['branch_id']][$value['product_id']] = $value['store'];
            }
        }
    
        if ($items[0]) {
            $from_branch_id = $items[0]['from_branch_id']; $to_branch_id = $items[0]['to_branch_id'];
    
            $branches = app::get('ome')->model('branch')->getList('name,branch_id',array('branch_id'=>array($from_branch_id,$to_branch_id)));
    
            foreach ($branches as $key => $branch) {
                if ($from_branch_id == $branch['branch_id']) {
                    $this->pagedata['from_branch_name'] = $branch['name'];
                }
    
                if ($to_branch_id == $branch['branch_id']) {
                    $this->pagedata['to_branch_name'] = $branch['name'];
                }
            }
            $items[0]['memo'] = $memo['memo'];
        }
        $this->pagedata['items'] = $items;
    
        $oAppropriation = app::get('taoguanallocate')->model('appropriation');
        $Appropriation_info = $oAppropriation->dump($iso_id,'memo');
        $this->pagedata['memo'] = $Appropriation_info['memo'];
        $this->pagedata['appropriation'] = $Appropriation_info;
        kernel::single('ome_print_otmpl')->printOTmpl($_GET['otmplId'],'appropriation',$this);
    }

    /**
    * 删除未入库调拔单
    *
    *
    */
    function deleteAppropriation($id){
        $this->begin('javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();');
        $iostockorder = app::get('taoguaniostockorder')->model('iso')->dump(array('original_id'=>$id,'type_id'=>40),'confirm');

        if ($iostockorder['confirm']!='N'){
            $this->end(false,'入库单已确认不可以删除!');
        }else{
            $result = app::get('taoguanallocate')->model('appropriation')->deleteAppropriation($id);
            if ($result) {
                $this->end(true,'删除成功!');
            }else{
                $this->end(false,'删除失败!');
            }
        }
    }

    /**
    * 根据采购单编号返回采购单所有信息
    *
    */
    function getPurchaseBybn($bn,$from_branch_id = null,$to_branch_id = null)
    {
        $libBranchProduct    = kernel::single('ome_branch_product');
        
        $data = array();
        $purchase = app::get('purchase');
        $Po = $purchase->model('po')->getlist('branch_id,po_id',array('po_bn'=>$bn,'eo_status'=>3),0,1);//已入库
        $data = $Po[0];
        $total_nums = 0;
        $items = $purchase->model('po_items')->getlist('*',array('po_id'=>$data['po_id']),0,-1);
        foreach($items as $ik=>$iv) {
            $items[$ik]['num'] = $iv['in_num'];
            $total_nums+=$iv['in_num'];

            $pids[] = $iv['product_id'];
        }
        
        $from_branch_product_store = $libBranchProduct->getStoreListByBranch($from_branch_id,$pids);
        $to_branch_product_store = $libBranchProduct->getStoreListByBranch($to_branch_id,$pids);
        foreach ($items as $k => $item){
            $items[$k]['from_branch_num'] = isset($from_branch_product_store[$item['product_id']]) ? $from_branch_product_store[$item['product_id']] : 0;
            $items[$k]['to_branch_num'] = isset($to_branch_product_store[$item['product_id']]) ? $to_branch_product_store[$item['product_id']] : 0;
        }

        $data['total_nums'] = $total_nums;
        $data['items'] = $items;
        echo json_encode($data);

    }
    /**
    * 导出调拨单模板
    *
    */
    function exportTemplate(){
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=调拨单模板".date('YmdHis').".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        //导出操作日志
        $logParams = array(
            'app' => $this->app->app_id,
            'ctl' => trim($_GET['ctl']),
            'act' => trim($_GET['act']),
            'modelFullName' => '',
            'type' => 'export',
            'params' => array(),
        );
        ome_operation_log::insert('warehouse_other_template_export', $logParams);
        $appropriationObj = app::get('taoguanallocate')->model('appropriation');
        $title1 = $appropriationObj->exportTemplate('appropriation');
        $title2 = $appropriationObj->exportTemplate('items');
        echo '"'.implode('","',$title1).'"';
        echo "\n\n";
        echo '"'.implode('","',$title2).'"';
    }
}
?>