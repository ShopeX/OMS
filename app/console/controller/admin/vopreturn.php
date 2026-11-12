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

/**
 * 退供单管理
 */
class console_ctl_admin_vopreturn extends desktop_controller{

    var $name = "退供单";
    var $workground = "console_center";

    function _views(){
        $billMdl = app::get('console')->model('vopreturn');
        $base_filter = array();
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'filter'=>array(),'optional'=>false),
            1 => array('label'=>app::get('base')->_('未确认'),'filter'=>array('status'=>'0'),'optional'=>false),
            2 => array('label'=>app::get('base')->_('已确认'),'filter'=>array('status'=>'1'),'optional'=>false),
        );
        
        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }
            
            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $billMdl->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=console&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$k;
        }

        return $sub_menu;
    }

    /**
     * index
     * @return mixed 返回值
     */
    public function index(){
        $this->title = '退供单';
        $actions = [];
        $user = kernel::single('desktop_user');
        if($user->has_permission('console_vop_return_get')){

            $actions[] = array(
                'label' => '获取退供单',
                'href' => $this->url.'&act=sync',
                'target' => "dialog::{width:700,height:400,title:'获取退供单'}",
            );
        }
        
        if ($_GET['view'] == 1){
            $actions[] = array(
                'label'     =>  app::get('console')->_('刷新退供单'),
                'submit'    =>  "index.php?app=console&ctl=admin_vopreturn&act=batchRefreshrefund",
                'confirm'   =>  '你确定要对勾选的退供单更新退货单信息吗？',
                'target'    =>  'refresh',
            );
        }
       
        $actions[] = array(
            'label' => '京东退供单导入',
            'href' => $this->url.'&act=batchimport&shop_type=360buy',
            'target' => "dialog::{width:700,height:400,title:'京东退供单导入'}",
        );
        $actions[] = array(
            'label' => '唯品退供单导入',
            'href' => $this->url.'&act=batchimport&shop_type=vop',
            'target' => "dialog::{width:700,height:400,title:'唯品退供单导入'}",
        );
        $params = array(
            'title'=>$this->title,
            'base_filter' =>[],
            'actions' => $actions,
            'orderBy' => 'id DESC',
            'use_buildin_recycle'=>false,
            //'use_buildin_import'=>true,
            //'use_buildin_importxls'=>true,
            'use_buildin_export'=>true,
            'use_buildin_filter'=>true,
        );
        $this->finder('console_mdl_vopreturn',$params);
    }
   
    /**
     * sync
     * @return mixed 返回值
     */
    public function sync() {
        $shop = app::get('ome')->model('shop')->getList('shop_id, name', ['node_type'=>array('vop','360buy')]);
        if(empty($shop)) {
            exit('缺少店铺');
        }
        $vopjit = kernel::single('ome_event_trigger_shop_vopjit');
        $warehouse = $vopjit::$returnBranchs;

        $this->pagedata['shop'] = $shop;
        $this->pagedata['warehouse'] = $warehouse;
        $this->pagedata['start_time'] = strtotime('-7 days');
        $this->pagedata['end_time'] = time();

        $this->pagedata['request_url'] = $this->url.'&act=do_sync';
        $this->display('admin/vop/sync_return.html');
    }

    /**
     * do_sync
     * @return mixed 返回值
     */
    public function do_sync() {
        $shop_id = $_POST['shop_id'];
        $warehouse = $_POST['warehouse'];
        $start_time   = $_POST['start_time'].' '.$_POST['_DTIME_']['H']['start_time'].':'.$_POST['_DTIME_']['M']['start_time'].':00';
        $end_time     = $_POST['end_time'].' '.$_POST['_DTIME_']['H']['end_time'].':'.$_POST['_DTIME_']['M']['end_time'].':00';
        $pageNo = (int) $_POST['page_no'];

        if (strtotime($start_time) >= strtotime($end_time)) {
            echo json_encode(['total'=>0]);exit;
        }
        
        $shops = kernel::single('ediws_event_trigger_jdlvmi')->getShops($shop_id);

        $ret = ['total'=>0,'succ'=>0,'fail'=>0];
        if($shops['shop_type'] == '360buy'){

             $sdf = [
                'start_time'    =>  strtotime($start_time), 
                'end_time'      =>  strtotime($end_time), 
                'shop_id'       =>  $shop_id,
                'shop_bn'       =>  $shops['shop_bn'],
                'shop_type'     =>  $shops['shop_type'],
                'vendorcode'    =>  $shops['config']['ediwsuser'],
             ];

            kernel::single('ediws_task_shippackage')->getShipPackage($sdf);

            $ret['succ'] += 1;
        }else{
            $sdf = ['start_date' => date('Y-m-d H:i:s', strtotime($start_time)), 'end_date' => date('Y-m-d H:i:s', strtotime($end_time)), 'warehouse'=>$warehouse, 'page_no'=>$pageNo, 'page_size'=>'10'];

            $result = kernel::single('erpapi_router_request')->set('shop', $shop_id)->purchase_getReturnInfo($sdf);
            if (empty($result['data'])) {
                echo json_encode($ret);exit;
            }
            if (isset($result['data']) && is_array($result['data'])) {
                $ret['total'] = count($result['data']);
            }
            
            foreach ($result['data'] as $v) {
                list($rs, $msg) = kernel::single('ome_event_trigger_shop_vopjit')->getReturnDetail($v, $shop_id);
                if($rs) {
                    $ret['succ'] += 1;
                } else {
                    $ret['fail'] += 1;
                    $ret['err_msg'][] = $msg;
                }
            }
        }
        
        echo json_encode($ret);exit;
    }

    /**
     * 获取Item
     * @param mixed $id ID
     * @return mixed 返回结果
     */
    public function getItem($id) {
        $rObj = app::get('console')->model('vopreturn');
        $main = $rObj->db_dump($id);
        list($rs, $msg) = kernel::single('ome_event_trigger_shop_vopjit')->getReturnDetail($main, $main['shop_id']);
        if(!$rs) {
            $this->splash('error', $this->url, $msg);
        }
        $this->splash('success', $this->url, '操作完成');
    }

   
    /**
     * cancel
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function cancel($id) {
        $rObj = app::get('console')->model('vopreturn');
        $this->pagedata['data'] = $rObj->db_dump($id, 'id,return_sn');
        $this->display('admin/vop/return_cancel.html');
    }
    
    public function doCancel() {
        $id = $_POST['id'];
        if(!$id) {
            $this->splash('error', $this->url, '请选择参数');
        }
        $vrObj = app::get('console')->model('vopreturn');
        $row = $vrObj->db_dump($id, 'id,return_sn,status');
        if(empty($row)) {
            $this->splash('error', $this->url, '不存在该单据');
        }
        if(!in_array($row['status'], ['0'])) {
            $this->splash('error', $this->url, '该单据不能取消');
        }
        $rs = $vrObj->update(['status'=>'3'], ['id'=>$id, 'status'=>'0']);
        if(!is_bool($rs)) {
            app::get('ome')->model('operation_log')->write_log('vopreturn@console',$id,"退供单取消");
        }
        $this->splash('success', $this->url, '取消成功');
    }
    
    /**
     * 检查
     * @param mixed $id ID
     * @return mixed 返回验证结果
     */
    public function check($id) {
        $data = app::get('console')->model('vopreturn')->db_dump($id);
        $branch = app::get('ome')->model('branch')->db_dump(['branch_bn'=>$data['return_address_no']], 'branch_id');
        $data['branch_id'] = $branch['branch_id'];
        $items = app::get('console')->model('vopreturn_items')->getList('*', ['return_id'=>$id]);
        foreach ($items as &$item) {
            $item['remaining_qty'] = max(0, $item['qty'] - $item['split_num']);
        }
        
        $this->pagedata['data'] = $data;
        $this->pagedata['items'] = $items;
        
        $branchMdl = app::get('ome')->model('branch');
        $branchs   = $branchMdl->getList('branch_id, name',array('b_type'=>1));

        $this->pagedata['branchs'] = $branchs;
        $this->singlepage('admin/vop/return_check.html');
    }

    /**
     * doCheck
     * @return mixed 返回值
     */
    public function doCheck() {
        $id = (int) $_POST['id'];
        
        //判断数量是否相等
        if(empty($_POST['branch_id'])){
            $this->splash('error', $this->url, '请选择唯品会退供入库仓库');
        }
        $branch_id = $_POST['branch_id'];

        $returns = app::get('console')->model('vopreturn')->db_dump($id);

        if($returns['shop_type'] == '360buy'){
            list($rs, $msg) = kernel::single('ediws_jdlvmi')->confirm($id,$branch_id,$_POST['items']);
        }else{
            list($rs, $msg) = kernel::single('console_vopreturn')->doCheck($id,$branch_id,$_POST['items']);
        }
        
        if(!$rs) {
            $this->splash('error', $this->url, $msg);
        }
        $this->splash('success', $this->url, '操作成功');
    }

    
    /**
     * batchimport
     * @return mixed 返回值
     */
    public function batchimport(){

        $shop_type = $_GET['shop_type'];
        $this->pagedata['shop_type'] = $shop_type;
        if($shop_type=='360buy'){
            $shopList = kernel::single('ediws_autotask_timer_accountsettlement')->getJdlwmiShop();
            
        }else{
            $shopMdl = app::get('ome')->model('shop');
            $shopList = $shopMdl->getList('shop_id, name', ['node_type'=>'vop']);
        }
        $this->pagedata['shoplist'] = $shopList;
        $this->display('admin/vop/return_import.html');
    }

     public function exportTemplate($shop_type) {
        $row = app::get('console')->model('vopreturn')->getTemplateColumn($shop_type);
        if($shop_type=='360buy'){

            $exporttitle = '京东退供单模板';
        }else{
            $exporttitle = '唯品会退供单模板';
        }
        
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel(null, $exporttitle, 'xls', $row);
    }
    
    public function batchRefreshrefund(){

        $this->begin('');
        kernel::database()->exec('commit');
        kernel::single('ediws_task_refundinfo')->refreshReturn();
        $ids = $_POST['id'];
        if (!empty($ids)) {
            foreach ($ids as  $id) {

                kernel::single('ediws_task_refundinfo')->updateRefundId($id);
            }
        }

        $this->end(true, '命令已经被成功发送！！');
    }
    
    /**
     * 导入退供单页面
     * @param $return_id
     * @date 2025-04-22 下午5:29
     */
    public function importCheck($return_id)
    {
        $this->pagedata['source_type'] = 'import_check';
        $this->pagedata['return_id']   = $return_id;
        $this->display('admin/vop/return_import_check.html');
    }
    
    /**
     * 导入确认模板
     * @param $return_id
     * @throws Exception
     * @date 2025-04-23 下午3:39
     */
    public function importExportTemplate($return_id)
    {
        $row   = app::get('console')->model('vopreturn')->getVopCheckTitle();
        $title = array_keys($row);
        
        $vopReturn   = app::get('console')->model('vopreturn')->db_dump(['id' => $return_id], 'id,return_sn,in_branch_id');
        $returnItems = app::get('console')->model('vopreturn_items')->getList('*', ['return_id' => $return_id, 'filter_sql' => 'split_num < qty']);
        $branch_name   = '';
        if (!empty($vopReturn['in_branch_id'])) {
            $branchInfo = app::get('ome')->model('branch')->db_dump(['branch_id' => $vopReturn['in_branch_id']], 'branch_bn,name');
            $branch_name  = $branchInfo['name'];
        }
        
        $data = [];
        foreach ($returnItems as $item) {
            $info   = [
                'return_sn'   => $vopReturn['return_sn'],
                'branch_name' => $branch_name,
                'material_bn' => $item['material_bn'],
                'box_no'      => $item['box_no'],
                'qty'         => $item['qty'],
                'split_num'   => $item['split_num'],
                'num'         => $item['qty'] - $item['split_num'],
            ];
            $data[] = array_values($info);
        }
        
        $title_name = sprintf('退供单%s', $vopReturn['return_sn']);
        
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel($data, $title_name, 'xls', $title);
    }
    
    /**
     * edit
     * @param mixed $id ID
     * @return mixed 返回值
     */
    public function edit($id)
    {
        $data = app::get('console')->model('vopreturn')->db_dump(['id'=>$id]);
        $list = app::get('console')->model('vopreturn_items')->getList('*',['return_id'=>$id,'source'=>'local']);
    
        $this->pagedata['data']   = $data;
        $this->pagedata['list']   = $list;
        $this->singlepage("admin/vop/return_edit.html");
    }
    
    /**
     * editSave
     * @return mixed 返回值
     */
    public function editSave()
    {
        $id = (int) $_POST['return_id'];
        $return = app::get('console')->model('vopreturn')->db_dump($id);
        
        if(!$return){
            $this->splash('error', $this->url, '退供单不存在！');
        }
        
        if($return['status'] != 0){
            $this->splash('error', $this->url, '已确认或部分确认后不能在编辑退供单！');
        }
        
        list($rs, $msg) = kernel::single('console_vopreturn')->editVopreturn($_POST);
    
        if(!$rs) {
            $this->splash('error', $this->url, $msg);
        }
        $this->splash('success', $this->url, '保存成功');
    }
    
    function getProducts($branch_id = null)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $basicMaterialBarcode    = kernel::single('material_basic_material_barcode');
        
        $pro_id = $_POST['product_id'];
        $supplier_id = $_POST['supplier_id'];
        $pro_bn= $_GET['bn'];
        $pro_name= $_GET['name'];
        $pro_barcode= $_GET['barcode'];
        if (is_array($pro_id)){
            $filter['bm_id'] = $pro_id;
        }
    
        // 选定全部
        if(is_array($filter['bm_id'][0]) && $filter['bm_id'][0]['_ALL_']){
            if (isset($_POST['filter']['advance']) && $_POST['filter']['advance'])
            {
                $arr_filters    = explode(',', $_POST['filter']['advance']);
                foreach ($arr_filters as $obj_filter)
                {
                    $arr    = explode('=', $obj_filter);
                    $filter[$arr[0]]    = $arr[1];
                }
                unset($_POST['filter']['advance']);
            }
        }
        
        if($pro_bn){
            $filter = array(
                'material_bn'=>$pro_bn
            );
        }
        if($pro_name){
            $filter = array(
                'material_name'=>$pro_name
            );
        }
        
        if($pro_barcode){
            // 查询条形码对应的bm_id
            $bm_ids    = $basicMaterialBarcode->getBmidListByBarcode($pro_barcode);
            $filter = array('bm_id'=>$bm_ids);
        }
        
        $data    = $basicMaterialSelect->getlist_ext('*', $filter);
        if (!empty($data)){
            $branchProductModel = app::get('ome')->model('branch_product');
            $branch_product = array();
            if (empty($pro_id) || !isset($_POST['product_id'])) {
                $pro_id = array_column($data,'product_id');
            }
            foreach ($branchProductModel->getList('*',array('branch_id'=>$branch_id,'product_id'=>$pro_id)) as $key => $value) {
                $branch_product[$value['branch_id']][$value['product_id']] = $value;
            }
            
            //虚拟仓累计成本
            $costSetting = kernel::single('tgstockcost_system_setting')->getCostSetting();
            $branchCost = false;
            if ($costSetting['branch_cost']['value'] == '2') {
                $branchCost = true;
                $entityBranchProduct = $res = kernel::single('ome_entity_branch_product')->getBranchCountCostPrice($branch_id, $pro_id);
            }
            foreach ($data as $k => $item)
            {
                // 查询关联的条形码
                $item['barcode']    = $basicMaterialBarcode->getBarcodeById($item['product_id']);
                
                // 基础物料规格
                $item['spec_info']    = $item['specifications'];
                
                $item['num'] = 0;
                if($supplier_id > 0){
                    $item['price'] = $this->app->model('po')->getPurchsePriceBySupplierId($supplier_id, $item['product_id'], 'desc');
                    
                    if (!$item['price'])
                    {
                        $item['price'] = 0;
                    }
                }else{
                    $item['price']    = $item['cost']; //成本价
                }
                
                $unitCost = $branch_product[$branch_id][$item['product_id']]['unit_cost'];
                $item['price'] = $unitCost > 0 ? $unitCost : $item['cost'];
                $item['unit_cost'] = $unitCost > 0 ? $unitCost : $item['cost'];
                $store = $branch_product[$branch_id][$item['product_id']]['store'];
                $store_freeze = $branch_product[$branch_id][$item['product_id']]['store_freeze'];
                $item['store'] = $store;
                $item['valid_store'] = (string)($store - $store_freeze);
                //使用虚拟仓累计成本
                if ($branchCost) {
                    $entityUnitCost    = $entityBranchProduct[$branch_id][$item['product_id']]['unit_cost'];
                    $item['price']     = isset($entityUnitCost) ? $entityUnitCost : $item['price'];
                    $item['unit_cost'] = isset($entityUnitCost) ? $entityUnitCost : $item['price'];
                }
                $item['product_name'] = $item['name'];
                $item['material_bn'] = $item['bn'];
    
                $rows[]    = $item;
            }
        }
    
        echo "window.autocompleter_json=".json_encode($rows);
    }
    
    /**
     * 基础物料列表弹窗数据获取方法
     * 
     * @param Void
     * @return String
     */
    function findMaterial($supplier_id=null)
    {
        // 供应商频道
        if ($supplier_id){
            //根据供应商商品
            $oSupplierGoods = app::get('purchase')->model('supplier_goods');
            $supplier_goods = $oSupplierGoods->getSupplierGoods($supplier_id);
            $base_filter['bm_id']    = $supplier_goods['bm_id'];
        }
        
        //只能选择可见的物料作为组合的明细内容
        $base_filter['visibled'] = 1;
        
        if($_GET['type'] == 1){
            $base_filter['type'] = 1;
        }
        
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
}
