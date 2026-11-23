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
 * 门店店铺商品库存
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version $Id: Z
 */
class console_ctl_admin_product_store extends desktop_controller
{
    function index()
    {
        //==
    }
    
    /**
     * 正价店铺商品
     */
    function normal()
    {
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
        
        //门店销售类型
        $base_filter['store_mode'] = 'normal';
        
        //action
        $actions = array();
        $actions[] = array(
                'label' => '批量设置库存范围',
                'submit' => $this->url.'&act=batchSetScope&store_mode=normal',
                'target' => 'dialog::{width:700,height:300,title:\'批量设置库存范围\'}"',
        );
        
        //params
        $params = array(
                'title' => '正价店铺商品',
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>false,
                'base_filter'=>$base_filter,
                'actions' => $actions,
                'orderBy'=>'branch_id ASC',
        );
        
        //top filter
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            $panel->setId('product_store_finder_top');
            $panel->setTmpl('admin/finder/branch_product_finder_panel_filter.html');
            $panel->show('console_mdl_product_store', $params);
        }
        
        $this->finder('console_mdl_product_store', $params);
    }
    
    /**
     * 奥莱店铺商品
     */
    function discount()
    {
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
        
        //门店销售类型
        $base_filter['store_mode'] = 'discount';
        
        //action
        $actions = array();
        $actions[] = array(
                'label' => '批量设置库存范围',
                'submit' => $this->url.'&act=batchSetScope&store_mode=discount',
                'target' => 'dialog::{width:700,height:300,title:\'批量设置库存范围\'}"',
        );
        
        //params
        $params = array(
                'title' => '奥莱店铺商品',
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>false,
                'base_filter'=>$base_filter,
                'actions' => $actions,
                'orderBy'=>'branch_id ASC',
        );
        
        //top filter
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            $panel->setId('product_store_finder_top');
            $panel->setTmpl('admin/finder/branch_product_finder_panel_filter.html');
            $panel->show('console_mdl_product_store', $params);
        }
        
        $this->finder('console_mdl_product_store', $params);
    }
    
    /**
     * 批量设置库存范围
     */
    public function batchSetScope()
    {
        $ids = $_POST['id'];
        $store_mode = $_GET['store_mode'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(count($ids) > 500){
            die('每次最多只能选择500条!');
        }
        
        if(empty($store_mode)){
            die('无效的操作,请检查');
        }
        
        //pagedata
        $this->pagedata['store_mode'] = $store_mode;
        $this->pagedata['request_url'] = 'index.php?app=console&ctl=admin_product_store&act=ajaxSetScope';
        $this->pagedata['custom_html'] = $this->fetch('admin/branch/set_store_scope.html');
        
        parent::dialog_batch('console_mdl_product_store', false, 10);
    }
    
    /**
     * 修改物流公司
     **/
    public function ajaxSetScope()
    {
        $key_id_name = 'id';
        
        //获取订单号
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择需要修改的数据';
            exit;
        }
        
        $proStoreObj = app::get('console')->model('product_store');
        $storeObj = app::get('o2o')->model('store');
        
        $ids = $postdata['f'][$key_id_name];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        
        $store_mode = trim($_POST['store_mode']);
        $min_store = intval($_POST['min_store']);
        $max_store = intval($_POST['max_store']);
        
        //check
        if(empty($store_mode)){
            echo 'Error: 无效的操作,请检查';
            exit;
        }
        
        if($min_store <= 0 || $max_store <= 0){
            echo 'Error: MIN最小库存或者MAX最大库存必须大于0';
            exit;
        }
        
        if($min_store > $max_store){
            echo 'Error: MIN最小库存不能大于MAX最大库存';
            exit;
        }
        
        $retArr = array(
                'itotal'  => 0,
                'isucc'   => 0,
                'ifail'   => 0,
                'err_msg' => array(),
        );
        
        //不同仓库列表(为了兼容更新store_bn、store_mode)
        $sql = "SELECT store_bn,branch_id FROM sdb_o2o_product_store WHERE id IN(". implode(',', $ids) .") GROUP BY branch_id";
        $branchList = $proStoreObj->db->select($sql);
        if(empty($branchList)){
            echo 'Error: 没有获取到门店库存记录';
            exit;
        }
        
        //门店列表
        $branch_ids = array();
        foreach ($branchList as $key => $val)
        {
            $branch_id = $val['branch_id'];
            $branch_ids[$branch_id] = $branch_id;
        }
        
        $tempList = $storeObj->getList('store_id,store_bn,branch_id,store_mode', array('branch_id'=>$branch_ids));
        $storeList = array_column($tempList, null, 'branch_id');
        
        //update
        foreach ($branch_ids as $key => $branch_id)
        {
            $store_bn = $storeList[$branch_id]['store_bn'];
            $store_mode = $storeList[$branch_id]['store_mode'];
            
            if(empty($store_bn)){
                continue;
            }
            
            $sdf = array('min_store'=>$min_store, 'max_store'=>$max_store, 'store_bn'=>$store_bn, 'store_mode'=>$store_mode);
            $proStoreObj->update($sdf, array('branch_id'=>$branch_id, 'id'=>$ids));
        }
        
        //count
        $retArr['itotal'] = count($ids);
        $retArr['isucc'] = count($ids);
        
        echo json_encode($retArr),'ok.';
        exit;
    }
    
    /**
     * 库存异常单列表
     */
    function abnormal()
    {
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
        
        //异常库存条件
        $base_filter['abnormal_store'] = true;
        
        //action
        $actions = array();
        
        //params
        $params = array(
                'title' => '库存异常单列表',
                'use_buildin_recycle'=>false,
                'use_buildin_export'=>false,
                'base_filter'=>$base_filter,
                'actions' => $actions,
                'orderBy'=>'branch_id ASC',
        );
        
        //top filter
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            $panel->setId('product_store_finder_top');
            $panel->setTmpl('admin/finder/branch_product_finder_panel_filter.html');
            $panel->show('console_mdl_product_store', $params);
        }
        
        $this->finder('console_mdl_product_store', $params);
    }
}
