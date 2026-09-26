<?php
class wms_ctl_admin_stock extends desktop_controller{
    var $name = "库存查看";
    var $workground = "wms_center";

    function _views_stock()
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $branch_productObj = app::get('ome')->model('branch_product');

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
                'href'=>'index.php?app=wms&ctl=admin_stock&act=index',

            )
        );

        $i=0;
        foreach($sub_menu as $k=>$v){
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
    /**
     * 自有仓储库存查看列表
     *
     */
    function index()
    {
        $is_super = kernel::single('desktop_user')->is_super();
        $branch_ids = kernel::single('wms_branch')->getBranchwmsByUser($is_super);
        if ($branch_ids){
            $base_filter['branch_id'] = $branch_ids;
        }else{
            $base_filter['branch_id'] = 'false';
        }

        $actions = array(
            array(
                'label' => '批量设置安全库存',
                'href'=>'index.php?app=wms&ctl=admin_stock&act=batch_safe_store',
                'target' => "dialog::{width:700,height:400,title:'批量设置安全库存'}",
            ),

        );

        //只显示设置保质期的基础物料
        if(app::get('material')->getConf('show.use_expire_material') == 1 && $_GET['expire'] == '1')
        {
            $label = '显示全部基础物料';
            $filter_val = 0;

            $base_filter['use_expire']    = 1;
        }
        else
        {
            $label = '只显示保质期物料';
            $filter_val = 1;

            unset($base_filter['use_expire']);
        }
        $actions['use_expire']    = array(
            'label' => $label,
            'href'=>'index.php?app=wms&ctl=admin_stock&act=show_expire_material&p[0]=' . $filter_val . '&p[1]=1',
        );

        $params = array(
            'title'=>'基础物料列表',
            'base_filter' => $base_filter,
            'actions' => $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>true,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'use_buildin_selectrow'=>true,
            'use_view_tab' => true,
            'finder_cols' => material_basic_material_master::mergeFinderCols(
                'column_edit,material_name,material_bn,store,column_barcode,column_cost,column_weight,column_unit,column_retail_price,visibled'
            ),
            'object_method'=>array('count'=>'countlist','getlist'=>'getlist')
        );
        $this->finder('wms_mdl_basic_material', $params);
    }


    /**
     * 库存查询相关方法，2011.11.01更新
     */
    function search(){
        if($_POST['stock_search']){
            $keywords = addslashes(trim($_POST['stock_search']));
            $stockObj = kernel::single('wms_stock');
            $data = $stockObj->search_stockinfo($keywords,'selfwms');
            $str = '<em style="color:red">'.$keywords.'</em>';
            foreach ($data as &$row) {
                $row['bn']      = str_replace($keywords,$str,$row['bn']);
                $row['barcode'] = str_replace($keywords,$str,$row['barcode']);
                $row['name']    = str_replace($keywords,$str,$row['name']);
            }

            $basicMaterial = kernel::single('material_info');
            $info = $basicMaterial->get_material_info($keywords);

            foreach($data as &$row){
                $row['brand_name'] = $info['brand_name'];
                $row['spec_info'] = $info['specifications'];
            }

            $this->pagedata['data'] = $data;
            $this->pagedata['keywords'] = $keywords;
        }
        $this->page("admin/stock/search.html");
    }

    /**
     * 批量设置安全库存
     */
    public function batch_safe_store() {

        //批量设置任务
        if($_POST) {
            $this -> batch_safe_store_set();
        }
    
        $branch_list = array();
        $branch_id = 0;
        $order_label = '';
        $io = '';
        
        $suObj = app::get('purchase')->model('supplier');
        $data  = $suObj->getList('supplier_id, name','',0,-1);
        $branchObj = kernel::single('wms_branch');
        // 获取操作员管辖仓库
        $is_super = kernel::single('desktop_user')->is_super();
        $selfbranch_id = $branchObj->getBranchwmsByUser($is_super);
        $brObj = app::get('ome')->model('branch');
        $row   = $brObj->getList('branch_id, name',array('branch_id'=>$selfbranch_id),0,-1);
        $this->pagedata['branch_list']   = $branch_list;
        $is_super = 1;
        $this->pagedata['is_super']   = $is_super;

        $this->pagedata['supplier'] = $data;
        $operator = kernel::single('desktop_user')->get_name();
        $this->pagedata['operator'] = $operator;

        $this->pagedata['branch']   = $row;
        $this->pagedata['branchid']   = $branch_id;
        $this->pagedata['sel_branch_id']   = intval($_GET['branch_id']);
        $this->pagedata['cur_date'] = date('Ymd',time()).$order_label;
        $this->pagedata['io'] = $io;
        $this->pagedata['finder_id'] = $_GET['finder_id'];

        $this->display("admin/stock/batch_safe_store.html");
    }

    /**
     * 批量安全库存设置保存
     *
     */
    public function batch_safe_store_set()
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');

        $page_no = intval($_POST['page_no']); // 分页处理
        $page_size = 10;
        $filter['branch_id'] = intval($_POST['branch']);//仓库
        //$filter['is_locked'] = '0';//跳过已经锁定的商品
        $filter['filter_sql'] = "( is_locked is null or is_locked = '0')";//修复当是否锁定字段为null的部分信息更新不到的问题
        $init_all = intval($_POST['init_all']);
        $init_type = intval($_POST['init_type']);//1固定数量，2按销量计算
        $safe_store = intval($_POST['safe_store']);
        $supply_type = intval($_POST['supply_type']);//1固定订货周期 　　 2供应商补货
        $last_modified = time();
        if($init_all == 2) {//设置安全库存为0的商品
            $filter['safe_store'] = 0;
        } elseif($init_all == 3) {//设置选定的商品
            if($_POST['product_ids'] == '_ALL_') {
                $preFilter = explode(',', $_POST['selcondition']);
                foreach($preFilter as $val) {
                    $oneFilter = explode('|', $val);
                    if($searchIndex = strpos($oneFilter[0], '_search')) {
                        $key = substr($oneFilter[0], 1, $searchIndex-1);
                        $compare[$key] = $oneFilter[1];
                    } else {
                        $key = $compare[$oneFilter[0]] ? $oneFilter[0] . '|' . $compare[$oneFilter[0]] : $oneFilter[0];

                        #基础物料名称_模糊搜索
                        if($key == 'material_name')
                        {
                            $key    .= '|has';
                        }

                        $anotherFilter[$key] = $oneFilter[1];
                    }
                }

                $productData    = $basicMaterialSelect->getlist('bm_id', $anotherFilter);

                $product_ids = array();
                foreach($productData as $key=>$_v){
                    if(!in_array($_v['product_id'], $product_ids)) {
                        $product_ids[] = $_v['product_id'];
                    }
                }
                $filter['product_id|in'] = $product_ids;
            } else {
                $filter['product_id|in'] = explode(',', $_POST['product_ids']);
            }
        }
        $oBranchPorduct = app::get('ome')->model('branch_product');

        if($init_type == 1) {//固定数量设置
            $result = $oBranchPorduct->update(array('safe_store' => $safe_store, 'last_modified' => $last_modified), $filter);
            $this->batch_upd_products();
            echo('finish');
            die();

        }elseif($init_type == 2) {//按销量计算
            $days = intval($_POST['days']);
            $hour = intval($_POST['hour']);

            //所有供应商的到货天数
            if ($supply_type == 2) {
                $oSupplier = app::get('purchase')->model('supplier');
                $suppliers = $oSupplier->getList('supplier_id,arrive_days');
                foreach ($suppliers as $v) {
                    $this->suppliers[$v['supplier_id']] = $v['arrive_days'];
                }
            }

            $branch_products = $oBranchPorduct->getList('product_id', $filter, $page_no * $page_size, $page_size);
            if (!$branch_products) {
                $this->batch_upd_products();
                echo('finish');
                die();
            } else {
                if ($page_no == 0) {
                    $total_products = $oBranchPorduct->count($filter);
                    echo(ceil($total_products / $page_size));
                }
            }
            for ($i = 0; $i < sizeof($branch_products); $i++) {
                $safe_store = $this->calc_safe_store($branch_products[$i]['product_id'], $days, $hour, $filter['branch_id'], $supply_type);
                $filter['product_id'] = $branch_products[$i]['product_id'];
                $oBranchPorduct->update(array('safe_store' => $safe_store, 'last_modified' => $last_modified), $filter);
            }
        } else {
            echo('Fatal error:init_type is null');
        }

        die();
        // echo "<script>$$('.dialog').getLast().retrieve('instance').close();</script>";
    }


    /**
     * 批量更新标志位，增加库存告警颜色提示
     */
    public function batch_upd_products() {
        $branchObj = kernel::single('wms_branch');
        // 获取操作员管辖仓库
        $is_super = kernel::single('desktop_user')->is_super();
        $selfbranch_id = $branchObj->getBranchwmsByUser($is_super);

        $sql = 'UPDATE sdb_material_basic_material_stock SET alert_store=0';
        kernel::database()->exec($sql);

        $sql = 'UPDATE sdb_material_basic_material_stock SET alert_store=999 WHERE bm_id IN
            (
                SELECT product_id FROM sdb_ome_branch_product
                WHERE safe_store>(store - store_freeze + arrive_store) AND branch_id in ('.implode(',',$selfbranch_id).')
            )
        ';
        kernel::database()->exec($sql);
    }

    /**
     * 计算商品的日平均销量
     * @param int $product_id 商品ID
     * @param int $days 天数,1-30
     * @param int $hour 时间点,0-23
     */
    public function calc_product_vol($product_id,$days,$hour,$branch_id){
        $end_time = strtotime(date('Y-m-d '.$hour.':00:00'));
        if(date('H')<$hour) {
            $end_time = strtotime('-1 days',$end_time);
        }
        $start_time = strtotime('-'.$days.' days',$end_time);
        /**
         * sdb_ome_iostock type_id
         * 3    销售出库
         * 100  赠品出库
         * 300  样品出库
         * 7    直接出库
         * 6    盘亏
         * 5    残损出库
         */
        $oIostock = app::get('ome')->model('iostock');
        $sql = 'SELECT sum(nums) as total FROM sdb_ome_iostock AS A
                    LEFT JOIN sdb_ome_delivery_items_detail AS B ON A.original_item_id = B.item_detail_id
                    WHERE A.type_id=3
                    AND A.branch_id='.$branch_id.'
                    AND B.product_id='.$product_id.'
                    AND A.create_time>='.$start_time.'
                    AND A.create_time<='.$end_time.' ';
        $sale_volumes = $oIostock -> db -> select($sql);
        $sale_volumes = ceil($sale_volumes[0]['total']/$days);
        return $sale_volumes;
    }

    /**
     * 计算商品的安全库存数
     * @param int $product_id 商品ID
     * @param int $days 天数,1-30
     * @param int $hour 时间点,0-23
     */
    public function calc_safe_store($product_id,$days,$hour,$branch_id,$supply_type){

        $arrive_days = $days;

        //最近几天的日平均销量
        $sale_volumes = $this -> calc_product_vol($product_id,$days,$hour,$branch_id);

        //返回安全库存数
        $safe_store = 0;
        if($arrive_days) {
            $safe_store = $sale_volumes * $arrive_days;
        }
        return $safe_store;
    }
    
    /**
     * 基础物料保质期批次列表
     * @return void
     */
    function show_storage_life()
    {
        $branch_id    = $_POST['branch_id'];
        $bm_id        = $_POST['bm_id'];

        if(empty($branch_id) || empty($bm_id))
        {
            die('无效操作，请检查！');
        }

        $basicMStorageLifeLib    = kernel::single('material_storagelife');

        #基础物料保质期开关
        $material_conf    = $basicMStorageLifeLib->checkStorageLifeById($bm_id);
        if(!$material_conf)
        {
            die('基础物料保质期开关未开启！');
        }

        #保质期批次列表
        $storage_life_list    = $basicMStorageLifeLib->getStorageLifeBatchList($bm_id, $branch_id);

        $this->pagedata['branch_id']    = $branch_id;
        $this->pagedata['bm_id']    = $bm_id;
        $this->pagedata['storage_life_list']  = $storage_life_list;
        $this->page('admin/stock/storage_life_list.html');
    }
    
    /**
     * 只显示设置保质期的基础物料
     * @param $filter_val
     * @return void
     */
    function show_expire_material($filter_val)
    {
        $this->begin('index.php?app=wms&ctl=admin_stock&act=index&expire=1');

        if($filter_val == 1)
        {
            $value = 1;
        }
        else
        {
            $value = 0;
        }

        app::get('material')->setConf('show.use_expire_material', $value);
        $this->end(true,'设置成功');
    }
    
    /**
     * 导出基础物料对应自有仓库关联的保质期批次
     * @param $bm_id
     * @param $branch_id
     * @return void
     */
    function export_expire($bm_id, $branch_id)
    {
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=".date('Ymd').".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');

        if(empty($branch_id) || empty($bm_id))
        {
            die('无效操作，请检查！');
        }

        $type    = $_GET['type'];

        #保质期批次列表
        if($type == 'warn_date')
        {
            $sql    = "SELECT * FROM sdb_material_basic_material_storage_life
                       WHERE bm_id='". $bm_id ."' AND branch_id='". $branch_id ."' AND warn_date<=" . time();
            $storage_life_list    = kernel::database()->select($sql);
        }
        else
        {
            $basicMStorageLifeLib    = kernel::single('material_storagelife');
            $storage_life_list    = $basicMStorageLifeLib->getStorageLifeBatchList($bm_id, $branch_id);
        }

        #基础物料明细
        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicMaterialRow    = $basicMaterialObj->dump(array('bm_id'=>$bm_id), 'material_name');

        #导出标题
        $data_title      = array();
        $data_title[]    = kernel::single('base_charset')->utf2local('*:序号');
        $data_title[]    = kernel::single('base_charset')->utf2local('*:保质期条码');
        $data_title[]    = kernel::single('base_charset')->utf2local('*:物料编码');
        $data_title[]    = kernel::single('base_charset')->utf2local('*:物料名称');
        $data_title[]    = kernel::single('base_charset')->utf2local('*:生产日期');
        $data_title[]    = kernel::single('base_charset')->utf2local('*:过期日期');
        $data_title[]    = kernel::single('base_charset')->utf2local('*:预警日期');
        $data_title[]    = kernel::single('base_charset')->utf2local('*:入库数量');
        $data_title[]    = kernel::single('base_charset')->utf2local('*:剩余数量');
        $data_title[]    = kernel::single('base_charset')->utf2local('*:预占数量');

        #数据格式化
        $data_list    = array();
        $material_name    = kernel::single('base_charset')->utf2local($basicMaterialRow['material_name']);
        foreach ($storage_life_list as $key => $val)
        {
            $data_list[$key]    = array(
                'key' => $key+1,
                'expire_bn' => $val['expire_bn'],
                'material_bn' => $val['material_bn'],
                'material_name' => $material_name,
                'production_date' => date('Y-m-d', $val['production_date']),
                'expiring_date' => date('Y-m-d H:i:s', $val['expiring_date']),
                'warn_date' => date('Y-m-d H:i:s', $val['warn_date']),
                'in_num' => $val['in_num'],
                'balance_num' => $val['balance_num'],
                'freeze_num' => $val['freeze_num'],
            );
        }
        unset($storage_life_list, $basicMaterialRow);

        #output
        echo '"'.implode('","', $data_title).'"';
        echo "\n";

        foreach ($data_list as $key => $val)
        {
            echo '"'.implode('","', $val).'"';
            echo "\n";
        }
    }
    
    /**
     * 基础物料保质期批次列表[预警库存]
     * @return void
     */
    function show_warn_storage_life()
    {
        $branch_id    = $_POST['branch_id'];
        $bm_id        = $_POST['bm_id'];

        if(empty($branch_id) || empty($bm_id))
        {
            die('无效操作，请检查！');
        }

        $basicMStorageLifeLib    = kernel::single('material_storagelife');

        #基础物料保质期开关
        $material_conf    = $basicMStorageLifeLib->checkStorageLifeById($bm_id);
        if(!$material_conf)
        {
            die('基础物料保质期开关未开启！');
        }

        #保质期批次列表
        $sql    = "SELECT * FROM sdb_material_basic_material_storage_life
                   WHERE bm_id='". $bm_id ."' AND branch_id='". $branch_id ."' AND warn_date<=" . time();
        $storage_life_list    = kernel::database()->select($sql);

        $this->pagedata['branch_id']    = $branch_id;
        $this->pagedata['bm_id']    = $bm_id;
        $this->pagedata['storage_life_list']  = $storage_life_list;
        $this->page('admin/stock/show_warn_storage_life.html');
    }
}
?>
