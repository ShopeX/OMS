<?php
class iostock_ctl_admin_iostocksearch extends desktop_controller{
    public function index(){
        $is_export = kernel::single('desktop_user')->has_permission('iostock_search_export');#增加出入库导出权限
        $this->title = '出入库查询<span class="c-red">（默认查询近1年数据）</span>';
        //仓库编号，仓库名称，出入库单号，出入库类型，原始单据号，供应商（供应商/用户名/公司名称），货号，货品名称（确认信息），出入库数量，出入库价格，经手人，出入库时间，操作员，备注
        //出入库时间-倒序，出入库类型-正序，货号-正序，
        $base_filter = array();
        if(!$_POST['create_time'])
        {
            // $time_from = strtotime($_POST['createtime_from']);
            // $filter = array('createtime|than' => strtotime('-7 days'));
            
            $base_filter['create_time|than'] = strtotime('-365 days');
        }

        $params = array(
            'title'=>$this->title,
            'finder_cols' => 'column_branch_id,branch_id,iostock_bn,type_id,orginal_bn,column_supplier,bn,column_name,nums,iostock_price,oper,create_time,operator,shop_name,memo',
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>$is_export,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'orderBy' => 'iostock_id desc',
        );

        if($base_filter){
            $params['base_filter'] = $base_filter;
        }
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);

            $panel->setId('iostock_finder_top');

            $panel->setTmpl('admin/finder/finder_panel_filter.html');
            $panel->show('iostock_mdl_iostocksearch', $params);

        }
        $this->finder('iostock_mdl_iostocksearch',$params);
    }

    
    /**
     * 重新算一下库存
     * @param  
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function stock()
    {
        if ($_POST) {
            $bn = $_POST['bn'];
            $branch_id = $_POST['branch_id'];
            $basicMaterialObj = app::get('material')->model('basic_material');
            $product = $basicMaterialObj->dump(array('material_bn'=>$bn),'*');
            if (empty($product)) {
                echo "<script>alert('货号不存在请确认');this.history.go(-1);</script>";
                exit;
            }
            $stock = kernel::single('iostock_stock')->get_stock_list($bn,$branch_id);
            
            $this->pagedata['data'] = $_POST;
            $this->pagedata['stock'] = $stock;

        }
        

        $branchObj = app::get('ome')->model('branch');
        $branchList = $branchObj->Get_branchlist();

        $this->pagedata['branchList'] = $branchList;
        $this->page("stock.html");
    }

    
    


}
