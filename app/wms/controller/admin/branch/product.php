<?php
class wms_ctl_admin_branch_product extends desktop_controller{
    var $name = "库存查看";
    var $workground = "wms_center";
    function _views(){
        $sub_menu = $this->_views_stock();
        return $sub_menu;
    }
    function _views_stock(){
        $branch_productObj = app::get('ome')->model('branch_product');
        $basicMaterialObj = &kernel::single('material_basic_material');

        /*
        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
            $branch_ids = kernel::single('wms_branch')->getBranchwmsByUser($is_super);
            if ($branch_ids){
                $base_filter['branch_id'] = $branch_ids;
            }else{
                $base_filter['branch_id'] = 'false';
            }
        }*/

        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('全部'),'optional'=>false,
                'href'=>'index.php?app=wms&ctl=admin_branch_product&act=index',

            ),
        );

        $i=0;
        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $this->base_filter);
            }


            if($k==0){
                $sub_menu[$k]['addon']=$basicMaterialObj->countAnother($this->base_filter);
            }else if($k==1){
                $sub_menu[$k]['addon']=$branch_productObj->countlist($this->base_filter);
            }

            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['href'] = $v['href'].'&view='.$i++;
        }
        return $sub_menu;
    }

    function index(){
        #商品可视状态
        if (!isset($_POST['visibility'])) {
            $this->base_filter['visibility'] = 'true';

        }elseif(empty($_POST['visibility'])){
            unset($_POST['visibility']);

        }


        #列表新增仓库搜索
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);

            $panel->setId('wms_branch_finder_top');
            $panel->setTmpl('admin/finder/finder_branch_panel_filter.html');

            $panel->show('wms_mdl_branch_product', $params);

        }
        /*
        * 获取操作员管辖仓库
        */

        $is_super = kernel::single('desktop_user')->is_super();

        if (!isset($_POST['branch_id']) || empty($_POST['branch_id'])) {
            $branch_ids = kernel::single('wms_branch')->getBranchwmsByUser($is_super);
            if ($branch_ids) {
                $this->base_filter['branch_id'] = $branch_ids;
            }else{
                $this->base_filter['branch_id'] = 'false';
            }
        }else{
            $this->base_filter['branch_id'] = $_POST['branch_id'];
        }
        $actions =  array(array(
            'label'=>app::get('ome')->_('全部导出').$_POST['branch_id'],
            'class'=>'export',
            'icon'=>'add.gif',
            'href'=>'index.php?app=wms&ctl=admin_branch_product&act=export',
            'target'=>'dialog::{width:400,height:170,title:\'导出\'}'),);

        $this->finder('wms_mdl_branch_product',array(
            'title'=>'库存表',
            'base_filter' => $this->base_filter,
            'actions' => $actions,

            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            //'use_buildin_export'=>true,
            'actions'=>$actions,
            'use_buildin_filter'=>true,
            'finder_cols' => material_basic_material_master::mergeFinderCols(
                'column_branch_name,column_bn,column_product_name,column_barcode,column_spec_info,column_brand,store,column_store_freeze,arrive_store',
                'column_bn'
            ),
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
