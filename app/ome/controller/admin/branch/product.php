<?php
class ome_ctl_admin_branch_product extends desktop_controller{
    var $name = "仓库库存查看";
    var $workground = "storage_center";
//    function _views(){
//        $sub_menu = $this->_views_stock();
//        return $sub_menu;
//    }
//    function _views_stock(){
//       
//        $branch_productObj = $this->app->model('branch_product');
//       
//        $oBranch = app::get('ome')->model('branch');
//        $is_super = kernel::single('desktop_user')->is_super();
//        if (!$is_super){
//            $branch_ids = $oBranch->getBranchByUser(true);
//            if ($branch_ids){
//                $base_filter['branch_id'] = $branch_ids;
//            }else{
//                $base_filter['branch_id'] = 'false';
//            }
//        }
//      
//        $sub_menu = array(
//            0 => array('label'=>app::get('base')->_('全部'),'optional'=>false,
//                'href'=>'index.php?app=ome&ctl=admin_stock&act=index',
//            
//            ),
//            1 => array('label'=>app::get('base')->_('按仓库查看'),'optional'=>true,
//                'href'=>'index.php?app=ome&ctl=admin_branch_product')
//           );
//           
//           
//          
//
//
//        $i=0;
//        foreach($sub_menu as $k=>$v){
//            if (!IS_NULL($v['filter'])){
//                $v['filter'] = array_merge($v['filter'], $base_filter);
//            }
//           
//         
//            if($k==0){
//                $sub_menu[$k]['addon']=$productObj->countAnother($base_filter);
//            }else if($k==1){
//                $sub_menu[$k]['addon']=$branch_productObj->countlist($base_filter);
//            }
//
//            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
//            $sub_menu[$k]['href'] = $v['href'].'&view='.$i++;
//        }
//        return $sub_menu;
//    }
        function index(){
            
            #列表新增仓库搜索
            if(!isset($_GET['action'])) {
                $panel = new desktop_panel($this);

                $panel->setId('ome_branch_finder_top');
                $panel->setTmpl('admin/finder/finder_branch_panel_filter.html');

                $panel->show('ome_mdl_branch_product', $params);

            }
         /*
         * 获取操作员管辖仓库
         */
        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();

        // 多选仓库去空值
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
        
        if (!$is_super){
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids){
                if (!empty($posted_branch_ids)) {
                    $selected = array_values(array_intersect($posted_branch_ids, $branch_ids));
                    $base_filter['branch_id'] = !empty($selected) ? $selected : array('false');
                } else {
                    $base_filter['branch_ids'] = $branch_ids;
                }
            }else{
                $base_filter['branch_id'] = 'false';
            }

        } else {
            if (!empty($posted_branch_ids)) {
                $base_filter['branch_id'] = $posted_branch_ids;
            }
        }
        
        $actions =  array(array(
                        'label'=>app::get('ome')->_('全部导出'),
                        'class'=>'export',
                        'icon'=>'add.gif',
                        'href'=>'index.php?app=ome&ctl=admin_branch_product&act=export',
                        'target'=>'dialog::{width:400,height:170,title:\'导出\'}'),);
    
        $this->finder('ome_mdl_branch_product',array(
            'title'=>'仓库库存列表',
            'base_filter' => $base_filter,
            'actions' => $actions,
            
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            //'use_buildin_export'=>true,
            'actions'=>$actions,
            'use_buildin_filter'=>true,
            //'use_buildin_selectrow'=>true,
            //'use_view_tab' => true,
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
}
?>
