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
 * 采购退货单
 * @author sunjing@shopex.cn
 *
 */
class console_ctl_admin_returned_purchase extends desktop_controller
{
    var $workground = "console_purchasecenter";
    
    function index($rp_type=NULL, $io=null){
        //列表标题及过滤条件
        switch($rp_type)
        {
            case 'po':
                $sub_title = "入库取消单";
                break;
            case 'eo':
                $sub_title = "采购退货列表";
                break;
            default:
                $sub_title = "退货单";
        }
        
        $params = array(
            'title'=>$sub_title,
            'actions' => array(
                array(
                    'label' => '新建',
                    'href' => 'index.php?app=console&ctl=admin_returned_purchase&act=add',
                    'target' => '_blank',
                ),
                array(
                    'label' => '导出模板',
                    'href' => 'index.php?app=console&ctl=admin_returned_purchase&act=exportTemplate',
                    'target' => '_blank',
                ),
                array(
                    'label' => '推送单据至WMS',
                    'submit' => 'index.php?app=console&ctl=admin_returned_purchase&act=batch_sync',
                    'confirm' => '你确定要对勾选的采购单发送至第三方吗？',
                    'target' => 'refresh',
                ),
            ),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => false,
            'use_buildin_importxls'  => true,
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

    /**
    * 采购退货单新建
    *
    */
    function add(){
        $supplierObj = app::get('purchase')->model('supplier');
        $branchObj = app::get('ome')->model('branch');
        $data  = $supplierObj->getList('supplier_id, name','',0,-1);

        /*
         * 获取操作员管辖仓库
         */
        $is_super = kernel::single('desktop_user')->is_super();
        $user_branch_list = array();
        
        if (!$is_super){
            $user_branch_list = $branchObj->getBranchByUser();
        }
        
        //判断仓库是否设置的仓库
        $config_branch_list = kernel::single('ome_branch_type')->getBranchByIOType(ome_iostock::PURCH_RETURN);
        // 获取配置的主仓
        $config_main_branch_list = kernel::single('ome_branch_type')->getBranchByIOType(ome_iostock::PURCH_STORAGE);
    
        // 合并配置的采购退货仓库和配置的主仓
        $all_config_branches = array_merge($config_branch_list, $config_main_branch_list);
        
        // 去重，避免重复的仓库
        $unique_branches = array();
        $seen_ids = array();
        foreach ($all_config_branches as $branch) {
            if (!in_array($branch['branch_id'], $seen_ids)) {
                $unique_branches[] = $branch;
                $seen_ids[] = $branch['branch_id'];
            }
        }
        
        // 合并两个仓库列表，只保留同时满足账号权限和配置项的仓库
        if (!$is_super && !empty($user_branch_list)) {
            $user_branch_ids = array_column($user_branch_list, 'branch_id');
            $unique_branch_ids = array_column($unique_branches, 'branch_id');
            
            // 取交集
            $common_branch_ids = array_intersect($user_branch_ids, $unique_branch_ids);
            
            // 根据交集ID重新构建仓库列表
            $branch_list = array();
            foreach ($unique_branches as $branch) {
                if (in_array($branch['branch_id'], $common_branch_ids)) {
                    $branch_list[] = $branch;
                }
            }
        } else {
            // 超级管理员或没有用户权限限制时，使用合并后的仓库列表
            $branch_list = $unique_branches;
        }
        
        $this->pagedata['branch_list'] = $branch_list;
        $is_super = 1;
        $this->pagedata['is_super'] = $is_super;

        $this->pagedata['supplier'] = $data;
        $operator = kernel::single('desktop_user')->get_name();
        $this->pagedata['operator'] = $operator;

        $this->pagedata['branch'] = $branch_list;
        $this->pagedata['cur_date'] = date('Ymd',time()).'采购退货单';

        #过滤o2o门店虚拟物流公司
        $oDly_corp = app::get('ome')->model('dly_corp');
        $dly_corp = $oDly_corp->getlist('*',array('disabled'=>'false', 'd_type'=>1));

        $this->pagedata['dly_corp'] = $dly_corp;
        $this->singlepage("admin/returned/purchase/purchase_add.html");
    }

    function doSave()
    {
        $basicMaterialLib    = kernel::single('material_basic_material');

        $this->begin();
        $supplierObj = app::get('purchase')->model('supplier');
        $returned_purchaseObj = app::get('purchase')->model('returned_purchase');
        $returned_itemsObj = app::get('purchase')->model("returned_purchase_items");

        $at = $_POST['at'];
        $pr = $_POST['pr'];
        $name = $_POST['purchase_name'];
        $emergency = ($_POST['emergency']=='true')?'true':'false';
        $supplier = $_POST['supplier'];
        $branch = $_POST['branch'];
        $memo = $_POST['memo'];
        $operator = $_POST['operator'];
        $d_cost = $_POST['d_cost'];
        $po_bn = $_POST['po_bn'];
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

            //判断选择商品库存是否充足
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

            if (!is_numeric($p)){
                $this->end(false, '单价必须为数字', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
            }

            unset($p);
        }
        //判断供应商是否存在

        $supplier_ = $supplierObj->dump(array('name'=>$supplier), 'supplier_id');
        if (!$supplier_['supplier_id']){
            $this->end(false, '输入的供应商不存在！', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
        }
        if ($branch == ''){
            $this->end(false, '请选择仓库', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
        }
        
        if (!empty($po_bn)) {
            $poMdl = app::get('purchase')->model('po');
            $poDetail = $poMdl->dump(array('po_bn'=>$po_bn));
            if (!$poDetail) {
                $this->end(false, '采购单不存在', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
            }
        }
        if ($poDetail) {
            $poItems = app::get('purchase')->model('po_items')->getList('*',array('po_id'=>$poDetail['po_id']));
            $poItems = array_column($poItems,null,'product_id');
            $returnId = $returned_purchaseObj->getList('rp_id',array('po_bn'=>$po_bn,'return_status|notin' => array ('3','5')));
            if ($returnId) {
                $returnId = array_column($returnId,'rp_id');
                $rpItemsList = $returned_itemsObj->getList('product_id,num',array('rp_id'=>$returnId));
                $rpItemsList = ome_func::filter_by_value($rpItemsList,'product_id');
                foreach ($rpItemsList as $key =>$value) {
                    $rp_items[$key]['num'] = array_sum(array_column($value,'num'));
                }
            }
        }
        $rp_bn = $returned_purchaseObj->gen_id();
        $data['rp_bn'] = $rp_bn;
        $data['name'] = $name;
        $data['supplier_id'] = $supplier_['supplier_id'];
        $data['operator'] = $operator;
        $data['emergency'] = $emergency;
        $data['branch_id'] = $branch;
        $data['amount'] = $total+(int)$d_cost;
        $data['product_cost'] = $total;
        $data['delivery_cost'] = $d_cost;
        $data['logi_no'] = $_POST['logi_no'];
        $data['returned_time'] = time();
        $data['rp_type'] = 'eo';
        $data['po_type'] = 'cash';
        $data['po_bn'] = $po_bn;
        //
        $data['corp_id'] = (int)$_POST['corp_id'];
        if ($memo){
            $op_name = kernel::single('desktop_user')->get_login_name();
            $newmemo = array();
            $newmemo[] = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i',time()), 'op_content'=>$memo);
            $data['memo'] = serialize($newmemo);
        }

        $rs = $returned_purchaseObj->save($data);
        if ($rs){
            $rp_id = $data['rp_id'];
            if ($ids)
            foreach ($ids as $i)
            {
                //插入采购退货单详情
                $p    = $basicMaterialLib->getBasicMaterialExt($i);

                //判断采购退货数量不能超过采购单数量
                if (isset($poItems[$i])) {
                    if (!$rp_items) {
                        $num = $at[$i];
                    }else{
                        $num = $at[$i] + $rp_items[$i]['num'];
                    }
                    if ($num > $poItems[$i]['num']) {
                        $this->end(false, $p['material_bn'] . '不能超过采购单数量', 'index.php?app=purchase&ctl=admin_returned_purchase&act=add');
                    }
                }
                $row['rp_id'] = $rp_id;
                $row['product_id'] = $i;
                $row['num'] = $at[$i];
                $row['price'] = sprintf('%.2f',$pr[$i]);
                $row['bn'] = $p['material_bn'];
                $row['barcode'] = $p['barcode'];
                $row['name'] = $p['material_name'];
                $row['spec_info'] = $p['spec_info'];
                $row['purchasing_price'] = $p['purchasing_price'];
                $returned_itemsObj->save($row);
                $row = null;
            }
            //--生成退货单日志记录
            $log_msg = '生成了编号为:'.$rp_bn.'的采购退货单';
            $opObj = app::get('ome')->model('operation_log');
            $opObj->write_log('purchase_refund@purchase', $rp_id, $log_msg);
            //将商品加入冻结库存

            $this->end(true, '已完成');
        }
        $this->end(false, '未完成', 'index.php?app=console&ctl=admin_returned_purchase&act=add');
    }

    /**
    * 审核采购退货单
    *
    * @access public
    * return
    */
    function check($rp_id){
        $detail = kernel::single('console_returned_purchase')->detail($rp_id);
        $this->pagedata['detail'] = $detail;
        unset($detail);
        $this->singlepage('admin/returned/purchase/purchase_check.html');
    }

    /**
    * 保存审核采购退货单
    *
    * @access public
    * return
    */
    function do_check()
    {
        $libBranchProduct    = kernel::single('ome_branch_product');
        //$basicMaterialStock    = kernel::single('material_basic_material_stock');

        $this->begin();

        $data = $_POST;
        $branch_id = $data['branch_id'];
        $rpObj = app::get('purchase')->model('returned_purchase');
        $rows = $rpObj->dump($data['rp_id'], 'check_status,return_status');
        if ($rows['check_status'] =='2' || $rows['return_status']!='1'){
            $this->end(false, '单据当前状态不可以审核!');
        }
        #冻结库存
        $returned_itemsObj = app::get('purchase')->model("returned_purchase_items");
        $items = $returned_itemsObj->getlist('bn,product_id,num',array('rp_id'=>$data['rp_id']),0,-1);
        
        //items
        foreach ($items as $item)
        {
            #获取单仓库-单个基础物料中的可用库存
            $usable_store    = $libBranchProduct->get_available_store($branch_id, $item['product_id']);

            if($item['num'] > $usable_store)
            {
                $this->end(false, $item['bn'].',退货数量不可大于库存数量.');
            }
        }
        
        $result = kernel::single('console_returned_purchase')->update_status(2,$data['rp_id']);
        if ($result) {
            //库存管控处理(审核通过后,库存处理)
            $storeManageLib    = kernel::single('ome_store_manage');
            $storeManageLib->loadBranch(array('branch_id'=>$branch_id));

            $params    = array();
            $params['node_type'] = 'checkReturned';
            $params['params']    = array('rp_id'=>$data['rp_id'], 'branch_id'=>$branch_id);
            $params['params']['items'] = $items;

            $processResult       = $storeManageLib->processBranchStore($params, $err_msg);
            if(!$processResult)
            {
                $this->end(false, '审核失败,'. $err_msg);
            }
            
            kernel::single('console_event_trigger_purchasereturn')->create(array('rp_id'=>$data['rp_id']), false);
            $this->end(true, '审核完成');
        }else{
            $this->end(false, '审核失败');
        }
    }
    
    /**
     * 编辑采购退货单
     *
     */
    function editReturn($rp_id){
        $this->begin('index.php?app=console&ctl=admin_returned_purchase&act=index');
        if (empty($rp_id)){
            $this->end(false,'操作出错，请重新操作');
        }
        $rpObj = app::get('purchase')->model('returned_purchase');
        $suObj = app::get('purchase')->model('supplier');
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

        #过滤o2o门店虚拟物流公司
        $oDly_corp = app::get('ome')->model('dly_corp');
        $dly_corp = $oDly_corp->getlist('*',array('disabled'=>'false', 'd_type'=>1));
        $this->pagedata['dly_corp'] = $dly_corp;

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
        $rpObj = app::get('purchase')->model('returned_purchase');
        $rp_itemObj = app::get('purchase')->model('returned_purchase_items');
        $data = $rpObj->dump($rp_id, '*', array('returned_purchase_items'=>array('*')));
        $at = $_POST['at'];
        $pr = $_POST['pr'];
        $branch = $_POST['branch'];
        $d_cost = $_POST['d_cost'];

        $total = 0;
        if($data['return_status']==2){
            $this->end(false, '退货已完成，不允许编辑', 'index.php?app=console&ctl=admin_returned_purchase&act=editReturn');
        }
        if(empty($at) || empty($pr)){
            $this->end(false, '退货单中必须有商品', 'index.php?app=console&ctl=admin_returned_purchase&act=editReturn');
        }
        foreach ($data['returned_purchase_items'] as $v){
            $p_id = $v['product_id'];
            if (empty($at[$p_id])){
                $del_item_id[] = $v;
            }
        }
        if ($del_item_id){
            foreach ($del_item_id as $item){//删除详情
                $rp_itemObj->delete(array('item_id'=>$item['item_id']));
            }
        }
        if ($pr)
        foreach ($pr as $p){
            if ($p<0){
                $this->end(false, '请完成单价的填写', 'index.php?app=console&ctl=admin_purchase&act=editPo');
            }
            if (!is_numeric($p)){
                $this->end(false, '单价必须为数字', 'index.php?app=console&ctl=admin_purchase&act=editPo');
            }
        }
        $oBranchProduct = app::get('ome')->model('branch_product');
        foreach ($at as $k => $a){
            if (!$a){
                $this->end(false, '请输入采购数量', 'index.php?app=console&ctl=admin_purchase&act=editPo');
            }

            if (!is_numeric($a) || $a < 1 ){

                $this->end(false, '采购数量必须为数字且大于0', 'index.php?app=console&ctl=admin_purchase&act=editPo');
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
        }
        //追加备注信息
        $memo = array();
        if ($data['memo']){
            $oldmemo= unserialize($data['memo']);
        }
        if ($oldmemo)
        foreach($oldmemo as $k=>$v){
            $memo[] = $v;
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
        $rp['corp_id'] = $_POST['corp_id'];
        $rpObj->save($rp);//更新退货单

        if ($ids)
        foreach ($ids as $i)
        {
            //插入退货单详情
            $p    = $basicMaterialLib->getBasicMaterialExt($i);

            $row = $edit_pi[$i];
            $row['rp_id'] = $rp_id;
            $row['product_id'] = $i;
            $row['num'] = $at[$i];
            $row['price'] = $pr[$i];
            $row['bn'] = $p['material_bn'];
            $row['barcode'] = $p['barcode'];
            $row['name'] = $p['material_name'];
            $row['spec_info'] = $p['spec_info'];
            $rp_itemObj->save($row);
            $row = null;
        }

        //--修改退货单日志记录
        $log_msg = '修改了编号为:'.$data['rp_bn'].'的退货单';
        $opObj = app::get('ome')->model('operation_log');
        $opObj->write_log('purchase_modify@purchase', $rp_id, $log_msg);
        $this->end(true, '已完成');
    }

    /**
     * 打印退货单
     *
     * @param int $rp_id
     */
    function printItem($rp_id){
        $rpObj = app::get('purchase')->model('returned_purchase');
        $suObj = app::get('purchase')->model('supplier');
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
        if(is_array($items)) usort($items, "cmp");
        $rp['po_items'] = $items;
        $rp['total'] = $total;
        $this->pagedata['po'] = $rp;
        $this->pagedata['time'] = time();
        $this->pagedata['base_dir'] = kernel::base_url();

        kernel::single('ome_print_otmpl')->printOTmpl($_GET['otmplId'],'purreturn',$this);
    }

    /**
    * 采购退货入库查异查看
    * @ int rp_id
    */
    function difference($rp_id){
        $returnedObj = app::get('purchase')->model('returned_purchase');
        $eoObj = app::get('purchase')->model('eo');
        $SupplierObj = app::get('purchase')->model('supplier');
        $branchObj = app::get('ome')->model('branch');
        $rp = $returnedObj->dump($rp_id, '*',array('returned_purchase_items'=>array('*')));
        $eo = $eoObj->dump($rp['object_id'], 'eo_bn');
        $rp['eo_bn'] = $eo['eo_bn'];
        $supplier = $SupplierObj->dump($rp['supplier_id'], 'name');
        $rp['supplier_name'] = $supplier['name'];
        $branch = $branchObj->dump($rp['branch_id'], 'name');
        $rp['branch_name'] = $branch['name'];

        $rp['memo'] = unserialize(($rp['memo']));
        $this->pagedata['rp'] = $rp;
        
        $this->singlepage('admin/returned/purchase/purchase_difference.html');
    }

    /**
    * 取消采购退货
    */
    function cancel($rp_id){
        $rpObj = app::get('purchase')->model('returned_purchase');

        $rp = $rpObj->dump($rp_id, 'supplier_id');
        $oSupplier = app::get('purchase')->model('supplier');
        $supplier = $oSupplier->dump($rp['supplier_id'], 'operator');
        $this->pagedata['operator'] = kernel::single('desktop_user')->get_name();
        $this->pagedata['rp_id'] = $rp_id;
        $this->display("admin/returned/purchase/purchase_cancel.html");
    }

    /**
    * 执行取消
    */
    function doCancel(){
        $this->begin('index.php?app=console&ctl=admin_returned_purchase&act=index&p[0]=eo');
        
        $rp_id = $_POST['rp_id'];
        $rpObj = app::get('purchase')->model('returned_purchase');
        $rp = $rpObj->dump($rp_id, 'memo,branch_id,rp_bn,return_status,check_status,branch_id');
        $itemsObj = app::get('purchase')->model('returned_purchase_items');
        $returnedObj = kernel::single('console_event_trigger_purchasereturn');
        $purchasereturnObj = kernel::single('console_receipt_purchasereturn');
        
        $check_status = $rp['check_status'];
        if (empty($rp_id)){
            $this->end(false,'操作出错，请重新操作');
        }
        if ($rp['return_status']>1){
            $this->end(false,'出库取消失败');
        }else{
            $updateData = array('return_status'=>5);
            if ($_POST['memo']){
                $newmemo =  htmlspecialchars($_POST['memo']);
                $updateData['memo'] = $purchasereturnObj->format_memo($rp['memo'],$newmemo);
            }

            $rpObj->update($updateData,array('rp_id'=>$rp_id));
            if ($rp['check_status'] == '2') {#已审核取消需要取消冻结库存
                $items = $itemsObj->getlist('*',array('rp_id'=>$rp_id));

                //库存管控处理(审核通过后,库存处理)
                $storeManageLib    = kernel::single('ome_store_manage');
                $storeManageLib->loadBranch(array('branch_id'=>$rp['branch_id']));

                $params    = array();
                $params['node_type'] = 'cancelReturned';
                $params['params']    = array('rp_id'=>$rp_id, 'branch_id'=>$rp['branch_id']);
                $params['params']['items'] = $items;

                $processResult       = $storeManageLib->processBranchStore($params, $err_msg);
                if(!$processResult)
                {
                    $this->end(false, $err_msg);
                }
            }

            $this->end(true, '出库取消已完成!');
        }
    }

    function checkCancel($rp_id){
        $returnedObj = kernel::single('console_event_trigger_purchasereturn');

        $rpObj = app::get('purchase')->model('returned_purchase');
        $rp = $rpObj->dump($rp_id,'memo,branch_id,rp_bn,check_status,return_status,out_iso_bn');
        $return_status = $rp['return_status'];

        if ($rp['check_status'] == '2'){
            if ($return_status != '1') {
                $result = array('rsp'=>'fail','error_msg'=>'单据所在状态不允许此次操作');
            }else{
                $rp_bn = $rp['rp_bn'];
                $branch_id = $rp['branch_id'];
                 $data = array(
                    'io_type'=>'PURCHASE_RETURN',
                    'io_bn'=>$rp_bn,
                    'branch_id'=>$branch_id,
                    'out_iso_bn'=>$rp['out_iso_bn'],
                );

                $result = $returnedObj->cancel($data, true);

            }

        }else{
            $result = array('rsp'=>'succ');
        }
        
        echo json_encode($result);
    }
    
    function exportTemplate()
    {
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

        $pObj   = app::get('purchase')->model('returned_purchase');
        $row    = $pObj->exportTemplate('return');
        $data[] = array();
        $data[] = $pObj->exportTemplate('item');
        $lib    = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel($data, '采购退货单模板', 'xls', $row);
    }
    
    /**
     * 单据发送至WMS第三方
     */
    public function batch_sync()
    {
        // $this->begin('');
        $rePurchaseMdl = app::get('purchase')->model('returned_purchase');
        $rePruchaseLib = kernel::single('console_event_trigger_purchasereturn');
        
        //post
        $ids = $_POST['rp_id'];
        
        //data
        $dataList = $rePurchaseMdl->getList('rp_id', array('rp_id'=>$ids, 'return_status'=>array('1'), 'check_status'=>array('2')));
        if(empty($dataList)){
            $this->splash('error', null, '没有可操作的数据：单据必须是已审核状态');
        }
        
        foreach ($dataList as $key => $val)
        {
            $rePruchaseLib->create(array('rp_id'=>$val['rp_id']), false);
        }
        
        $this->splash('success', null, '命令已经被成功发送！！');
    }
}
?>
