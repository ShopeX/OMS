<?php
class console_ctl_admin_branch_product extends desktop_controller{
    var $name = "仓库库存列表";
    var $workground = "console_center";
    
    function _views_stock()
    {
        $branch_productObj = app::get('ome')->model('branch_product');
        $basicMaterialSelect    = kernel::single('material_basic_select');

        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids){
                $base_filter['branch_id'] = $branch_ids;
            }else{
                $base_filter['branch_id'] = 'false';
            }
        }

        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'optional'=>false,
                'href'=>'index.php?app=console&ctl=admin_branch_product&act=index',
            )
        );
        
        $i=0;
        foreach($sub_menu as $k=>$v)
        {
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }
            
            if($k==0){
                $sub_menu[$k]['addon']=$basicMaterialSelect->countAnother($base_filter);
            }else if($k==1){
                $sub_menu[$k]['addon']=$branch_productObj->countlist($base_filter);
            }
            
            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['href'] = $v['href'].'&view='.$i++;
        }
        
        return $sub_menu;
    }
    
    function index()
    {
        $params = [];
        
        //商品可视状态
        if (!isset($_POST['visibility'])) {
            $base_filter['visibility'] = 'true';
        }elseif(empty($_POST['visibility'])){
            unset($_POST['visibility']);
        }
        
        //列表新增仓库搜索
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            
            $panel->setId('ome_branch_finder_top');
            $panel->setTmpl('admin/finder/finder_branch_panel_filter.html');
            
            $panel->show('ome_mdl_branch_product', $params);
        }
        
        // 多选仓库：「全部」空选项会一起提交，需去掉空值，避免 SQL：IN (,2)
        $posted_branch_ids = array();
        if (isset($_POST['branch_id'])) {
            $posted_branch_ids = array_values(array_filter((array) $_POST['branch_id'], function ($v) {
                return $v !== '' && $v !== null && $v !== '_NULL_';
            }));
            if (!empty($posted_branch_ids)) {
                $_POST['branch_id'] = $posted_branch_ids;
            } else {
                unset($_POST['branch_id']);
            }
        }

        //获取操作员管辖仓库
        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids){
                if (!empty($posted_branch_ids)) {
                    $selected = array_values(array_intersect($posted_branch_ids, $branch_ids));
                    $base_filter['branch_id'] = !empty($selected) ? $selected : array('false');
                } else {
                    $base_filter['branch_id'] = $branch_ids;
                }
            }else{
                $base_filter['branch_id'] = 'false';
            }
        } else {
            if (!empty($posted_branch_ids)) {
                $base_filter['branch_id'] = $posted_branch_ids;
            }
        }
        
        $actions =  array(
            array(
                'label'=>app::get('ome')->_('全部导出'),
                'class'=>'export',
                'icon'=>'add.gif',
                'href'=>'index.php?app=console&ctl=admin_branch_product&act=export',
                'target'=>'dialog::{width:400,height:170,title:\'导出\'}'
            ),
            array(
                'label' => '查询唯品会购物车冻结',
                'href' => $this->url .'&act=pullVopCartStock&view='. $_GET['view'] .'&finder_id='.$_GET['finder_id'],
                'target' => "dialog::{width:550,height:350,title:'拉取唯品会购物车库存冻结数据'}",
            ),
        );
        
        $this->finder('console_mdl_branch_product',array(
            'title'=>'仓库库存列表',
            'base_filter' => $base_filter,
            'actions' => $actions,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'actions' => $actions,
            'use_buildin_filter' => true,
            'finder_cols' => material_basic_material_master::mergeFinderCols(
                'column_branch_name,column_bn,column_product_name,column_barcode,column_spec_info,column_material_spu,store,store_freeze,column_available_store,arrive_store,column_vop_sku_stock',
                'column_bn'
            ),
            'object_method'=>array('count'=>'countlist','getlist'=>'getlists')
        ));
    }
    
    public function export()
    {
        // 多选仓库：规范化为有效 ID 数组，避免模板输出 "Array"
        $branch_id = array();
        if (isset($_GET['branch_id'])) {
            $branch_id = array_values(array_filter(array_map('intval', (array) $_GET['branch_id']), function ($v) {
                return $v > 0;
            }));
        }
        $bn = $_GET['bn'];
        $_actual_store_search = $_GET['_actual_store_search'];
        $actual_store = $_GET['actual_store'];
        $_enum_store_search = $_GET['_enum_store_search'];
        $enum_store = $_GET['enum_store'];
        $this->pagedata['branch_id'] = $branch_id;
        $this->pagedata['bn'] = $bn;
        $this->pagedata['_actual_store_search'] = $_actual_store_search;
        $this->pagedata['actual_store'] = $actual_store;
        $this->pagedata['_enum_store_search'] = $_enum_store_search;
        $this->pagedata['enum_store'] = $enum_store;

        $this->page('admin/branch/product/export.html');
    }
    
    /**
     * 查询唯品会购物车冻结
     */
    public function pullVopCartStock()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        //shop
        $shopObj = app::get('ome')->model('shop');
        $sql = "SELECT shop_id,shop_bn,name AS shop_name,shop_type,node_id FROM sdb_ome_shop WHERE shop_type ='vop' AND node_id IS NOT NULL AND node_id != ''";
        $shopList = $shopObj->db->select($sql);
        $this->pagedata['shopList'] = $shopList;
        
        //开始时间(默认为昨天)
        //$start_time = date('Y-m-d', time());
        $start_time = '';
        $this->pagedata['start_time'] = $start_time;
        
        //help帮助信息
        $this->pagedata['help_msg'] = '*温馨提醒：最多只能拉取近三个月内的T-1日数据。结束时间是今天零点的时间。';
        
        //店铺编码
        $this->pagedata['selectListName'] = '店铺编码';
        
        //post url
        $this->pagedata['postUrl'] = $this->url .'&act=ajaxVopCartStock';
        
        //check
        if(empty($shopList)){
            die('没有绑定唯品会店铺');
        }
        
        $this->display('admin/vop/download_datalist.html');
    }
    
    /**
     * ajax查询唯品会购物车冻结
     */
    public function ajaxVopCartStock()
    {
        $shopObj = app::get('ome')->model('shop');
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        $jitOrderLib = kernel::single('console_inventory_orders');
        $codeBaseLib = kernel::single('material_codebase');
        
        //check
        if(empty($_POST['shop_bn'])){
            $retArr['err_msg'] = array('请先选择店铺编码');
            echo json_encode($retArr);
            exit;
        }
        
        //page
        $nextPage = isset($_POST['nextPage']) && $_POST['nextPage'] > 1 ? $_POST['nextPage'] : 1;
        
        //setting
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'total' => 0,
            'next_page' => 0,
            'err_msg' => array(),
        );
        
        //shop
        $sql = "SELECT shop_id,shop_bn,name,shop_type,node_id FROM sdb_ome_shop WHERE shop_type ='vop' AND shop_bn='". $_POST['shop_bn'] ."' AND node_id IS NOT NULL AND node_id != ''";
        $shopInfo = $shopObj->db->selectrow($sql);
        if(empty($shopInfo)){
            $retArr['err_msg'] = array('唯品会店铺不符合,无法拉取数据');
            echo json_encode($retArr);
            exit;
        }
        
        //total
        $total = $basicMaterialObj->count([]);
        
        //基础物料列表
        $filter = [];
        $limit = 100;
        $offset = ($nextPage - 1) * $limit;
        $orderby = 'bm_id ASC';
        $materialList = $basicMaterialObj->getList('bm_id,material_bn', $filter, $offset, $limit, $orderby);
        
        //check
        if(empty($materialList)){
            $current_num = 0;
            $current_succ_num = 0;
            $current_fail_num = 0;
            
            $retArr['itotal'] += $current_num; //本次拉取记录数
            $retArr['isucc'] += $current_succ_num; //处理成功记录数
            $retArr['ifail'] += $current_fail_num; //处理失败记录数
            $retArr['total'] = $total; //数据总记录数
            $retArr['next_page'] = 0; //下一页页码(如果为0则无需拉取)
            
            echo json_encode($retArr);
            exit;
        }
        
        //获取基础物料关联的条形码
        $materialList = $codeBaseLib->getMergeMaterialCodes($materialList);
        
        //批量查询唯品会商品库存并保存
        $result = $jitOrderLib->downloadVopSkuStock($shopInfo, $materialList);
        
        //setting
        $nextPage++;
        if($result['rsp'] == 'succ'){
            $current_num = count($materialList);
            $current_succ_num = count($materialList);
            $current_fail_num = 0;
        }else{
            $current_num = count($materialList);
            $current_succ_num = 0;
            $current_fail_num = count($materialList);
        }
        
        $retArr['itotal'] += $current_num; //本次拉取记录数
        $retArr['isucc'] += $current_succ_num; //处理成功记录数
        $retArr['ifail'] += $current_fail_num; //处理失败记录数
        $retArr['total'] = $total; //数据总记录数
        $retArr['next_page'] = $nextPage; //下一页页码(如果为0则无需拉取)
        
        echo json_encode($retArr);
        exit;
    }
}
?>