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
 * 人工库存预占
 * by wangjianjun 20171023
 */
class console_ctl_admin_stock_artificial_freeze extends desktop_controller{
    
    //库存预占列表展示
    function index(){
        
        //页面加载
        $_POST["status"] = "1"; //默认 预占中
        if($_GET["view"] == "1"){ //已释放
            $_POST["status"] = "2";
        }
        
        //仓库权限
        $base_filter = array();
        $is_super = kernel::single('desktop_user')->is_super();
        if(!$is_super){ //不是超级管理员 仓库权限判断
            $base_filter = kernel::single('console_stock_artificial_freeze')->get_user_branch();
        }
        
        $params = array(
                'title'=>'人工库存预占列表',
                'actions' => array(
                        array(
                                'label' => '新增货品预占',
                                'href' => 'index.php?app=console&ctl=admin_stock_artificial_freeze&act=add',
                                'target'=> "dialog::{width:500,height:220,title:'新增货品预占'}",
                        ),
                        array(
                                'label' => '批量释放',
                                'submit' => 'index.php?app=console&ctl=admin_stock_artificial_freeze&act=batch_unfreeze&view='.$_GET["view"],
                                'confirm' => '你确定要对勾选的数据进行释放预占的库存操作吗？',
                                'target' => 'refresh'
                        ),
                        array(
                                'label' => '删除',
                                'submit' => 'index.php?app=console&ctl=admin_stock_artificial_freeze&act=delete_rows&view='.$_GET["view"],
                                'confirm' => '你确定要对勾选的数据进行删除记录操作吗？',
                                'target' => 'refresh'
                        ),
                ),
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'=>false,
                'use_buildin_export'=>true,
                'use_buildin_import'=>false,
                'use_buildin_recycle'=>false, //直接删除数据 不走recycle
                'use_view_tab'=>true,
                'base_filter'=>$base_filter,
                'orderBy' =>'bmsaf_id DESC'
        );
        
        //列表新增组搜索
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            $panel->setId('stock_artificial_freeze_finder_top');
            $panel->setTmpl('admin/finder/stock_artificial_freeze_top_filter.html');
            $panel->show('console_mdl_basic_material_stock_artificial_freeze',$params);
        }
        
        $this->finder('console_mdl_basic_material_stock_artificial_freeze',$params);
        
    }
    
    //TAB显示
    function _views(){
        //仓库权限
        $branch_arr = array();
        $is_super = kernel::single('desktop_user')->is_super();
        if(!$is_super){ //不是超级管理员 仓库权限判断
            $branch_arr = kernel::single('console_stock_artificial_freeze')->get_user_branch();
        }
        $mdl_maf = app::get('material')->model('basic_material_stock_artificial_freeze');
        $sub_menu = array(
                0 => array('label'=>app::get('base')->_('预占中'),'filter'=>array_merge(array('status'=>'1'),$branch_arr),'optional'=>false),
                1 => array('label'=>app::get('base')->_('已释放'),'filter'=>array_merge(array('status'=>'2'),$branch_arr),'optional'=>false),
        );
        $i=0;
        foreach($sub_menu as $k=>$v){
            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $mdl_maf->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=console&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$i++;
        }
        return $sub_menu;
    }
    
    //库存预占导入展示
    function import(){
        echo $this->page('admin/stock/artificial/import.html');
    }
    
    //执行导入操作
    function doImport(){
        //开启事务
        $trans = kernel::database()->beginTransaction();
        $result = kernel::single('console_stock_artificial_freeze')->process($_POST);
        header("content-type:text/html; charset=utf-8");
        if($result['rsp'] == 'succ'){
            kernel::database()->commit($trans);
            echo json_encode(array('result' => 'succ'));
        }else{
            kernel::database()->rollBack();
            echo json_encode(array('result' => 'fail', 'msg' =>(array)$result['res']));
        }
    }
    
    //导出模板
    function exportTemplate(){
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=".date('Ymd').".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $oObj = kernel::single('console_stock_artificial_freeze');
        $title = $oObj->exportTemplate();
        echo '"'.implode('","',$title).'"';
    }
    
    //单个释放
    function single_unfreeze($bmsaf_id){
        $this->begin('javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();');
        //开启事务
        kernel::database()->beginTransaction();

        $result = kernel::single('console_stock_artificial_freeze')->do_unfreeze(array($bmsaf_id), false);
        if($result['rsp'] == 'succ'){
            kernel::database()->commit();
            $this->end(true,'释放成功');
        }else{

            kernel::database()->rollBack();
            $this->end(false,$result['res']);
        }
    }
    
    //批量释放
    function batch_unfreeze(){
        $this->begin('');
        if($_GET["view"] == "1"){ //已释放TAB页
            $this->end(false, '已释放状态的数据无法进行此操作。');
        }
        $mdl_maf = app::get('material')->model('basic_material_stock_artificial_freeze');
        if($_POST['isSelectedAll'] == '_ALL_'){ //全部选中
            //默认预占中
            $filter = array("status"=>"1");
            if($_POST["branch_id"]){
                $filter["branch_id"] = $_POST["branch_id"];
            }
            if($_POST["group_id"]){
                $filter["group_id"] = $_POST["group_id"];
            }
            $rs_maf = $mdl_maf->getlist("bmsaf_id,status",$filter);
        }elseif(!empty($_POST["bmsaf_id"])){ //勾选的数据
            $rs_maf = $mdl_maf->getlist("bmsaf_id,status",array("bmsaf_id"=>$_POST["bmsaf_id"]));
        }else{
            $this->end(false, '未选择数据');
        }
        //检查必须都为预占中的数据
        $bmsaf_ids = array();
        foreach($rs_maf as $var_rm){
            if($var_rm["status"] != 1){
                $this->end(false, '存在非预占中状态的数据。');
            }
            $bmsaf_ids[] = $var_rm["bmsaf_id"];
        }
        //开启事务
        kernel::database()->beginTransaction();

        $result = kernel::single('console_stock_artificial_freeze')->do_unfreeze($bmsaf_ids,true);
        if($result['rsp'] == 'succ'){
            kernel::database()->commit();
            $this->end(true,'释放成功');
        }else{

            kernel::database()->rollBack();
            $this->end(false,$result['res']);
        }
    }
    
    //新增展示页
    function add(){
        $mdl_ome_branch = app::get('ome')->model('branch');
        $this->pagedata["branch_list"] = $mdl_ome_branch->getList("branch_id,name",array("b_type"=>1)); //目前只支持电商线上仓
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->display("admin/stock/artificial/add_freeze.html");
    }
    
    //执行新增操作
    function do_add(){
        $rsp = array('rsp'=>'fail', 'error_msg'=>'');
        if(empty($_POST["bm_id"]) || !is_array($_POST["bm_id"])){
            $rsp['error_msg'] = '请选择基础物料';
            echo json_encode($rsp, true);
            exit;
        }
        //开启事务
        kernel::database()->beginTransaction();

        $result = kernel::single('console_stock_artificial_freeze')->do_add($_POST);
        if($result['rsp'] == 'succ'){
            kernel::database()->commit();
            $rsp = array('rsp'=>'succ');
            echo json_encode($rsp, true);
        }else{
            kernel::database()->rollBack();

            echo json_encode(array('rsp' => 'fail', 'error_msg' =>(array)$result['res']));
        }
    }
    
    //加载
    function ajax_basic_material_html($branch_id){
        $this->pagedata["branch_id"] = $branch_id;
        $this->display('admin/stock/artificial/select_basic_material.html');
    }
    
    //显示已选基础物料
    public function show_selected_products(){
        $mdl_ma_ba_ma = app::get('material')->model('basic_material');
        $bm_ids = kernel::single('base_component_request')->get_post('bm_id');
        if (!empty($bm_ids)){
            $this->pagedata['_input'] = array(
                'name' => 'bm_id',
                'idcol' => 'bm_id',
                '_textcol' => 'material_name',
            );
            $list = $mdl_ma_ba_ma->getList('bm_id,material_name,material_bn', array('bm_id'=>$bm_ids),0,-1,'bm_id asc');
            $this->pagedata['_input']['items'] = $list;
        }
        $this->display('admin/stock/artificial/show_products.html');
    }
    
    //删除
    public function delete_rows(){
        $this->begin('');
        if($_GET["view"] == "1"){ //已释放的页面 能做删除操作
            $mdl_maf = app::get('material')->model('basic_material_stock_artificial_freeze');
            $mdl_mafg = app::get('material')->model('basic_material_stock_artificial_freeze_group');
            if($_POST['isSelectedAll'] == '_ALL_'){ //全部选中
                //“已释放”TAB 目前$_GET["view"]为1时（已释放） 默认预占中
                $filter = array("status"=>"2");
                if($_POST["branch_id"]){
                    $filter["branch_id"] = $_POST["branch_id"];
                }
                if($_POST["group_id"]){
                    $filter["group_id"] = $_POST["group_id"];
                }
                $rs_maf = $mdl_maf->getlist("bmsaf_id,status",$filter);
            }elseif(!empty($_POST["bmsaf_id"])){ //勾选的数据
                $rs_maf = $mdl_maf->getlist("bmsaf_id,status",array("bmsaf_id"=>$_POST["bmsaf_id"]));
            }else{
                $this->end(false, '未选择数据');
            }
            //检查必须都为预占中的数据
            $bmsaf_ids = array();
            foreach($rs_maf as $var_rm){
                if($var_rm["status"] != 2){
                    $this->end(false, '只能删除已释放状态的数据。');
                }
                $bmsaf_ids[] = $var_rm["bmsaf_id"];
            }
            $mdl_ome_operation_log = app::get('ome')->model('operation_log');
            foreach ($bmsaf_ids as $var_bi){
                //获取当前group_id 
                $rs_each = $mdl_maf->dump(array("bmsaf_id"=>$var_bi));
                $rs_each_group_id = $rs_each["group_id"];
                //删除操作
                $mdl_ome_operation_log->write_log('delete_artificial_freeze@ome',$var_bi,"删除人工库存预占记录");
                $mdl_maf->delete(array('bmsaf_id'=>$var_bi));
                //判断当前group下没有数据删除组
                $rs_each_group = $mdl_maf->dump(array("group_id"=>$rs_each_group_id));
                if(empty($rs_each_group)){
                    $mdl_mafg->delete(array("group_id"=>$rs_each_group_id));
                }
            }
            $this->end(true,'删除成功');
        }else{
            //预占中TAB页
            $this->end(false, '预占中状态的数据无法进行此操作。');
        }
    }
    
}
?>