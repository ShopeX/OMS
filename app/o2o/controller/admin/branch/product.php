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
 +----------------------------------------------------------
 * 门店供货管理
 +----------------------------------------------------------
 * 20160808 wangjianjun
 +----------------------------------------------------------
 */
class o2o_ctl_admin_branch_product extends desktop_controller{
    
    function index(){
        $finder_id = $_REQUEST['_finder']['finder_id'];
        
        $base_filter = array();
        
        //过滤物料编码 类别头部筛选
        $post_material_bn = trim($_POST['material_bn']);
        if($post_material_bn){
            //获取bm_id
            $mdlMaterialBasicMaterial = app::get('material')->model('basic_material');
            $rs_bm_id = $mdlMaterialBasicMaterial->dump(array("material_bn"=>$post_material_bn),"bm_id");
            $base_filter['bm_id'] = $rs_bm_id['bm_id'];
        }
        
        //过滤选择门店的下拉框 类别头部筛选
        $post_selected_store_bn = trim($_POST['selected_store_bn']);
        if($post_selected_store_bn && $post_selected_store_bn!="_NULL_"){
            //获取branch_id
            $mdlOmeBranch = app::get('ome')->model('branch');
            $rs_branch_id = $mdlOmeBranch->dump(array("branch_bn"=>$post_selected_store_bn),"branch_id");
            $base_filter['branch_id'] = $rs_branch_id['branch_id'];
        }
        
        $params = array('title'=>'门店供货管理',
            'actions'=>array(
                array(
                    'label' => '物料关联',
                    'href' => 'index.php?app=o2o&ctl=admin_branch_product&act=addDispatch',
                    'target' => "_blank",
                ),
//                 array(
//                     'label' => '批量同步',
//                     'submit' => 'index.php?app=o2o&ctl=admin_branch_product&act=is_bind',
//                 ),
                array(
                    'label' => '导出模板',
                    'href' => 'index.php?app=o2o&ctl=admin_branch_product&act=downloadTemplate',
                    'target' => "_blank",
                ),
                array(
                    'label' => '导入',
                    'href' => 'index.php?app=o2o&ctl=admin_branch_product&act=importTemplate',
                    'target' => "dialog::{width:400,height:110,title:'导入商品'}",
                ),
            ),
            'use_buildin_filter'=>true,
            'use_buildin_recycle'=>true,
            'use_buildin_selectrow'=>true,
            'use_bulidin_view'=>true,
            'base_filter'=>$base_filter,
            'orderBy'=>'branch_id asc',
        );
        
        //top filter
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            $panel->setId('branch_product_finder_top');
            $panel->setTmpl('admin/finder/branch_product_finder_panel_filter.html');
            $panel->show('o2o_mdl_branch_product', $params);
        }
        
        $this->finder('o2o_mdl_branch_product', $params);
    }

    //分类导航
    function _views(){
        $mdlO2oBranchProduct = $this->app->model('branch_product');
        $sub_menu = array(
            0 => array('label'=>__('全部')),
//             1 => array('label'=>__('未绑定'),'filter'=>array('is_bind'=>'1'),'optional'=>false),
//             2 => array('label'=>__('绑定'),'filter'=>array('is_bind'=>'2'),'optional'=>false),
        );
        
        $i=0;
        foreach($sub_menu as $k => $v){
            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $mdlO2oBranchProduct->count($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=o2o&ctl='.$_GET['ctl'].'&act=index&view='.$i++;
        }
        return $sub_menu;
    }
    
    /**
     * 门店供货管理，物料关联
     */

    function addDispatch(){
        $this->singlepage("admin/branch/add_dispatch.html");
    }
    
    /**
     * 获取_product_info
     * @return mixed 返回结果
     */
    public function get_product_info()
    {
        $params = array(
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_view_tab'=>false,
        );
    
        $this->finder('o2o_mdl_branch_productinfo', $params);
    }

    //门店关联物料----废弃，改为上面的addDispatch
    function dispatch(){
        
        $view = $_REQUEST['view'];
        $finder_id = $_REQUEST['finder_id'];
        $page = $_REQUEST['page'] ? $_REQUEST['page'] : 1;
        $pagelimit = 50;
        $offset = ($page-1) * $pagelimit;
        
        if($_REQUEST['search']){
            //搜索操作
            $params['search_key'] = $_REQUEST['search_key'];
            //选择品牌或者分类 此值为空
            if(empty($_REQUEST['search_value'])){
                $params['search_value'] = $_REQUEST['search_value_'.$_REQUEST['search_key']];
                $this->pagedata['search_value_key'][$_REQUEST['search_key']] = $params['search_value'];
            }else{
                $params['search_value'] = $_REQUEST['search_value'];
                $this->pagedata['search_value'] = $params['search_value'];
            }
            $this->pagedata['search_key'] = $params['search_key'];
            $this->pagedata['search_value_last'] = $params['search_value'];
            //获取基础物料列表
            $data = kernel::single("o2o_branch_product")->get_product_info($offset,$pagelimit,$params);
            //获取记录数
            $count = kernel::single("o2o_branch_product")->do_count($params);
            $link = 'index.php?app=o2o&ctl=admin_branch_product&act=dispatch&view='.$view;
            $link    .= '&search=true&search_value='.$params['search_value'].'&search_key='.$params['search_key'].'&target=container&page=%d&finder_id='. $finder_id;
        }else{
            //获取基础物料列表
            $data = kernel::single("o2o_branch_product")->get_product_info($offset,$pagelimit);
            //获取记录数
            $count = kernel::single("o2o_branch_product")->do_count();
            $link = 'index.php?app=o2o&ctl=admin_branch_product&act=dispatch&view='.$view.'&target=container&page=%d&finder_id='. $finder_id;
        }
        
        $total_page = ceil($count/$pagelimit);
        $pager = $this->ui()->pager(array(
                'current'=>$page,
                'total'=>$total_page,
                'link'=>$link,
        ));
        
        $this->pagedata['rows'] = $data;
        
        //获取搜索选项
        $this->pagedata['search'] = kernel::single("o2o_branch_product")->get_search_options();
        //获取自定义搜索项下拉列表
        $this->pagedata['search_list'] = kernel::single("o2o_branch_product")->get_search_list();
        
        $this->pagedata['count'] = $count;
        $this->pagedata['pager'] = $pager;
        $this->pagedata['finder_id'] = $finder_id;
        
        if($_GET['target'] || $_POST['search'] =='true'){
            return $this->display('admin/branch/product_index.html');
        }
        $this->singlepage('admin/branch/product_index.html');
    }

    //保存
    function do_save(){
        $this->begin();
        
        $bm_ids = array_map('intval', (array)$_POST['bm_id']);
        if(!$bm_ids){
            $this->end(false,'请选择基础物料');
        }
        $post_selected_store_bn = $_POST["selected_store_bn"];
        if($post_selected_store_bn == "_NULL_" || !isset($_POST['selected_store_bn'])){
            $this->end(false,'请选择门店');
        }
        
        //获取branch_id
        $mdlOmeBranch = app::get('ome')->model('branch');
        $rs_branch_id = $mdlOmeBranch->dump(array("branch_bn"=>$post_selected_store_bn),"branch_id");
        $branch_id = intval($rs_branch_id['branch_id']);
        
        $mdlO2oBranchProduct = app::get('o2o')->model('branch_product');
        
        //全选时候的处理
        if($_POST['select_all'] == 'true'){
            $params["search_key"] = $_POST['search_key'];
            $params["search_value"] = $_POST['search_value'];
            if(!empty($search_key) && !empty($search_value)){
                $data = kernel::single("o2o_branch_product")->get_product_info("","",$params);
            }else{
                $data = kernel::single("o2o_branch_product")->get_product_info();
            }
            $bm_ids = array();
            foreach ($data as $var_data){
                $bm_ids[] = intval($var_data["bm_id"]);
            }
        }
        $bm_ids = array_map('intval', (array)$bm_ids);
    
        //先判断是否存在供货关系 
        $rs_b_p = $mdlO2oBranchProduct->getList("bm_id",array('branch_id'=>$branch_id,"bm_id|in"=>$bm_ids));
        if(!empty($rs_b_p)){
            $exist_bm_ids = array();
            foreach ($rs_b_p as $var_b_p){
                $exist_bm_ids[] = $var_b_p["bm_id"];
            }
        }
        if(!empty($exist_bm_ids)){
            //移除bm_ids中存在供货关系的bm_id
            foreach ($bm_ids as $bk => &$var_b_i){
                if(in_array($var_b_i,$exist_bm_ids)){
                    unset($bm_ids[$bk]);
                }
            }
            unset($var_b_i);
        }
        if(empty($bm_ids)){
            $this->end(false,'没有可添加的供货关联');
        }
        
        foreach ($bm_ids as $var_bm_id){
            //插入数据
            $var_bm_id = intval($var_bm_id);
            $insert_sql = "insert into sdb_o2o_branch_product (`branch_id`,`bm_id`) values (".$branch_id.",".$var_bm_id.")";
            $mdlO2oBranchProduct->db->exec($insert_sql);
        }
        
        //[批量创建]淘宝门店关联宝贝
        if(app::get('tbo2o')->is_installed())
        {
            $storeItemLib    = kernel::single('tbo2o_store_items');
            $result          = $storeItemLib->batchCreate($bm_ids, $branch_id, $errormsg);
        }
        
        $this->end(true,'操作成功');
    }
    
    //配置页
    function setConfig(){
        $id = intval($_GET["id"]);
        if(!$id){
            return false;
        }
        
        //获取配置信息
        $mdlO2oBranchProduct = app::get('o2o')->model('branch_product');
        $this->pagedata["o2o_branch_product"] = $mdlO2oBranchProduct->dump(array("id"=>$id),"*");
        
        $this->page('admin/branch/product_config.html');
    }
    
    //保存配置
    function doSetConfig(){
        $this->begin();
        
        $id = $_POST["id"];
        if(!$id){
            return false;
        }
        
        $is_ctrl_store = $_POST["is_ctrl_store"];
        $status = $_POST["status"];
        
        $mdlO2oBranchProduct = app::get('o2o')->model('branch_product');
        
        $filter_arr = array("id"=>$id);
        $update_arr = array("is_ctrl_store"=>$is_ctrl_store,"status"=>$status);
        
        $result = $mdlO2oBranchProduct->update($update_arr,$filter_arr);
        if($result){
            $this->end(true, '设置成功。');
        }else{
            $this->end(false, '设置失败。');
        }
    }
    
    //禁用 启用 (已弃用)
    function doAction(){
        $url = 'javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();';
        
        $id = $_GET["id"];
        $type = $_GET["type"];
        if(!$id || !$type){
            $this->splash('error',$url,'操作出错，不存在此条发票记录，请重新操作。');
        }
        
        $mdlO2oBranchProduct = app::get('o2o')->model('branch_product');
        $filter_arr = array("id"=>$id);
        switch($type){
            case "active":
                $update_arr = array("status"=>1);
                break;
            case "unactive":
                $update_arr = array("status"=>2);
                break;
        }
        $result = $mdlO2oBranchProduct->update($update_arr,$filter_arr);
        if($result){
            $this->splash('success',$url,'更新成功');
        }else{
            $this->splash('error',$url,'更新失败。');
        }
    }
    
    //导出模板
    function downloadTemplate(){
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=门店物料关联模板.csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $oObj = $this->app->model('branch_product');
        $title = $oObj->exportTemplate();
        echo '"'.implode('","',$title).'"';

    }
    
    //导入确认 选择文件
    /**
     * importTemplate
     * @return mixed 返回值
     */
    public function importTemplate(){
        return $this->page('admin/branch/product_import.html');
    }
    
    //ajax获取页面上的选择门店的select
    function organization_stores_list() {
        $params = $_POST;
        $storeListLib = kernel::single('o2o_view_select');
        $html = $storeListLib->list_store($params);
        echo($html);
        exit;
    }
    
}
