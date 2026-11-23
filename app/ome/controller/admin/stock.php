<?php
class ome_ctl_admin_stock extends desktop_controller{
    var $name = "全部库存查看";
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
        # 商品可视状态
        if (!isset($_POST['visibility'])) {
            $filter['visibility'] = 'true';
        }elseif(empty($_POST['visibility'])){
            unset($_POST['visibility']);
        }
     
        /* 获取操作员管辖仓库 */
        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        
        
        if (!$is_super){
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids){
                $filter['branch_ids'] = $branch_ids;
            }else{
                $filter['branch_id'] = 'false';
            }
        }

        if(isset($_POST['branch_id']) && $_POST['branch_id']>0){
            $filter['branch_id'] = $_POST['branch_id'];
        }
        $filter['product_group'] = true;
        
        $actions = array(
            array(
                'label' => '批量设置安全库存',
                'href'=>'index.php?app=ome&ctl=admin_stock&act=batch_safe_store',
                'target' => "dialog::{width:700,height:400,title:'批量设置安全库存'}",
            ),
            /*
            array(
                'label' => '库存初始化',
                'href'=>'index.php?app=ome&ctl=admin_stock&act=init_stock',
                'target' => "dialog::{width:700,height:400,title:'库存初始化'}",
            ),
            */
        );
      
       $this->finder('ome_mdl_products',array(
            'title'=>'总库存列表',
            'base_filter' => $filter,
            'actions' => $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>true,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'use_buildin_selectrow'=>true,
            'use_view_tab' => true,
            'object_method'=>array('count'=>'countAnother','getlist'=>'getListAnother')

        ));
    }

    /**
     * 库存初始化，测试中
     * redis库存高可用，废弃掉直接修改db库存、冻结的方法
     */
    function init_stock(){
        return false;
        return false;
        return false;

        $oBranchPorduct = app::get('ome')->model('branch_product');
        $oBranchPorduct -> update(array('store'=>50));

        $basicMaterialStockObj = app::get('material')->model('basic_material_stock');
        $basicMaterialStockObj -> update(array('store'=>50));
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
         * 3	销售出库
         * 100	赠品出库
         * 300	样品出库
         * 7	直接出库
         * 6	盘亏
         * 5	残损出库
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
    public function calc_safe_store($product_id,$days,$hour,$branch_id,$supply_type)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');

        //获取该商品对应的供应商
        $bm_ids      = $basicMaterialSelect->getlist('bm_id', array('bm_id'=>$product_id));
        $goods_id    = $bm_ids[0]['product_id'];
        
        $oSupplierGoods = app::get('purchase')->model('supplier_goods');
        $supplier_id = $oSupplierGoods -> getList('supplier_id',array('bm_id'=>$goods_id));
        $supplier_id = $supplier_id[0]['supplier_id'];

        //供应商对应的到货天数
        if ($supply_type == 2) {
            $arrive_days = $this -> suppliers[$supplier_id];
        }else{
            $arrive_days = $days;
        }

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
     * 批量更新标志位，增加库存告警颜色提示
     */
    public function batch_upd_products() {
        #基础物料_安全库存数
        $sql = 'UPDATE sdb_material_basic_material_stock SET alert_store=0';
        kernel::database()->exec($sql);

        $sql = 'UPDATE sdb_material_basic_material_stock SET alert_store=999 WHERE bm_id IN
            (
                SELECT product_id FROM sdb_ome_branch_product
                WHERE safe_store>(store - store_freeze + arrive_store)
            )
        ';
        kernel::database()->exec($sql);
    }

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
                $filter['product_id|in'] = array_map('intval', explode(',', $_POST['product_ids']));
            }
        }

        $oBranchPorduct = app::get('ome')->model('branch_product');
        if($init_type == 1) {//固定数量设置
            $result = $oBranchPorduct->update(array('safe_store' => $safe_store, 'last_modified' => $last_modified), $filter);
            $this->batch_upd_products();
            echo('finish');
            die();

        } elseif($init_type == 2) {//按销量计算
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
     * 批量设置安全库存
     */
    public function batch_safe_store() {

        //批量设置任务
        if($_POST) {
            $this -> batch_safe_store_set();
        }

        $suObj = app::get('purchase')->model('supplier');
        $data  = $suObj->getList('supplier_id, name','',0,-1);

        $brObj = app::get('ome')->model('branch');
        $row   = $brObj->getList('branch_id, name','',0,-1);

        // 获取操作员管辖仓库
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
           $branch_list = $brObj->getBranchByUser();
        }
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

    /*详情
     *ss备注：货位相关方法，可以删除此方法，同时可以删除页面'admin/stock/edit_stock.html','admin/stock/edit_stocks.html'
     */
    function edit($product_id=0)
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');
        
       if($_POST){
           $this->begin('index.php?app=ome&ctl=admin_stock&act=edit&p[0]='.$_POST['product_id']);
           $product_id = $_POST['product_id'];
           $branch_id = $_POST['branch_id'];
           $pos_id = $_POST['pos_id'];
           //if($oBranch_product_pos->get_branch_pos_exist($product_id,$pos_id)>0){
               //$this->end(false, app::get('base')->_('此货品已和此货位建立关联'));
           //}
           $libBranchProductPos->create_branch_pos($product_id,$branch_id,$pos_id);
           $this->end(true, app::get('base')->_('关联成功'));
       }
       
       $oBranch = $this->app->model("branch");
       $branch_list=$oBranch->Get_branchlist();

       $oPos = $this->app->model("branch_pos");
       $pos = $oPos->select('*');
       $this->pagedata['branch_list'] = $branch_list;
       $this->pagedata['pos'] = $pos;
       $this->pagedata['product_id'] = $product_id;
        $this->pagedata['pro_detail'] = $basicMaterialSelect->products_detail($product_id);
        $this->page("admin/stock/edit_stock.html");
    }

    /*
     *ss备注：货位相关方法，可以删除此方法
     */
    function dosave(){
        $this->begin('index.php?app=ome&ctl=admin_stock&act=edit&p[0]='.$_POST['product_id']);

        $libBranchProductPos    = kernel::single('ome_branch_product_pos');
        
        $do_action = $_POST['do_action'];
        $ckid = $_POST['ckid'];
        $store = $_POST['store'];
        $branch_id = $_POST['branch_id'];
        $product_id = $_POST['product_id'];

        if($do_action=='save_branch'){
            foreach($ckid as $k=>$v){
              $adata = array('branch_id'=>$_POST['branch_id'][$v],'store'=>$_POST['store'][$v],'product_id'=>$_POST['product_id'],'pos_id'=>$v);
              
              $libBranchProductPos->change_store($_POST['branch_id'][$v], $_POST['product_id'], $v, $_POST['store'][$v], '=');
            }

            $this->end(true, app::get('base')->_('保存成功'));
        }else if($do_action=='reset_branch'){

            $oBranch_product_pos = $this->app->model("branch_product_pos");
            $oBranch_product = $this->app->model("branch_product");

                $pro = $oBranch_product_pos->dump(array('product_id'=>$product_id,'pos_id'=>$_POST['repos_id']),'store');
                if($pro['store']>0){
                  $this->end(false, app::get('base')->_('库存量大于0,不可以重置'));
                }
                /*判断仓库对应几个货位。货位是否大于1*/
                $assign = $libBranchProductPos->get_pos($product_id,$_POST['rebranch_id']);

                $arrive = $oBranch_product->dump(array('product_id'=>$product_id,'branch_id'=>$_POST['rebranch_id']),'arrive_store');
                if($arrive['arrive_store']>0){
                    if(count($assign)==1){
                        $this->end(false, app::get('base')->_('不可重置在途库存大于0的最后一个货位'));
                    }
                }

            $libBranchProductPos->reset_branch_pos($product_id,$_POST['rebranch_id'],$_POST['repos_id']);
            $this->end(true, app::get('base')->_('重置成功'));

        }else{
            $this->end(false, app::get('base')->_('不明参数。。。。'));
        }
    }

    /*
     * ss备注：货位相关方法，可以删除
     */
    function get_op($branch_id,$ajax='false'){
        $oBranch = $this->app->model('branch');
        
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');
        
        $Pos = $libBranchProductPos->get_unassign_pos($branch_id);

        $branch_name=$oBranch->Get_name($branch_id);
        if($ajax == 'true'){
            $options = "<option value=''>请选择</option>";
            if($Pos && is_array($Pos)){
                foreach($Pos as $v){
                    $options .= "<option value=".$v['pos_id'].">".$v['store_position']."</option>";
                }
            }
            echo $options."</select>";
        }else{

        }
    }

    /*
     * 获取货位JSON
     * ss备注：货位相关方法，可以删除此方法
     */
    function getPosByBranchProduct(){
        $branch_id = $_GET['branch_id'];
        $pos_name = $_GET['store_position'];
        $product_id = $_POST['product_id'];
        
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');
        
        if ($pos_name)
        {
            //获取所有货位
            $Pos = $libBranchProductPos->getPosByName($branch_id, $pos_name);
        }

        echo "window.autocompleter_json=".json_encode($Pos);
    }

    /*
     * 获取货位名称
     * getPosNameById
     * param id
     * ss备注：货位相关方法，可以删除此方法
     */
     function getPosNameById(){
        $id = $_POST['id'];
        $oBranchPos = app::get('ome')->model('branch_pos');
        $branchpos = $oBranchPos->dump(array('pos_id'=>$id), 'pos_id,store_position');
        $tmp['id'] = $branchpos['pos_id'];
        $tmp['name'] = $branchpos['store_position'];
        echo json_encode($tmp);
        //echo "{'id':'".$branchpos['pos_id']."','name':'".$branchpos['store_position']."'}";
     }

    /*
     * 关联货位，全部展示
     * getPosFinder
     * ss备注：货位相关方法，可以删除此方法
     */
    function view($branch_id=null){

        $branch_id = intval($branch_id);
        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();

        $branch_mode = app::get('ome')->getConf('ome.branch.mode');
        if ($branch_mode=='single'){
            $cols = 'store_position,column_product_bn,column_product_name';
        }else{
            $cols = 'store_position,branch_id,column_product_bn,column_product_name';
        }

        $params = array(
                        'title'=>'货位',
                        'use_buildin_new_dialog' => false,
                        'use_buildin_set_tag'=>false,
                        'use_buildin_recycle'=>false,
                        'use_buildin_export'=>false,
                        'use_buildin_import'=>false,
                        'use_buildin_filter'=>true,
                        'finder_aliasname'=>'search_branch_pos_finder',
                        'finder_cols'=>$cols,
                        'orderBy' => 'p.bn asc ',
                        'object_method' => array(
                            'count'=>'finder_count',   //获取数量的方法名
                            'getlist'=>'finder_list',   //获取列表的方法名
                        ),
                    );

        /*
         * 获取操作员管辖仓库
         */
        if (!$is_super){
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids){
                $params['base_filter']['branch_id'] = $branch_ids;
            }else{
                $params['base_filter']['branch_id'] = 'false';
            }
        }

        if($branch_id){
          if (!$is_super){
             if (in_array($branch_id,$branch_ids)){
               $params['base_filter']['branch_id'] = $branch_id;
             }else{
               $params['base_filter']['branch_id'] = '-';
             }
          }else{
               $params['base_filter']['branch_id'] = $branch_id;
          }
        }

        $this->finder('ome_mdl_branch_pos', $params);
    }

    /*
     * ss备注：货位相关方法，可以删除
     */
    function get_op1($branch_id,$ajax='false',$type,$product_id=''){
        $oBranch = $this->app->model('branch');
        $Pos = $oBranch->Get_poslist($branch_id);
        
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');
        
        $branch_name=$oBranch->Get_name($branch_id);


        if($ajax == 'true'){
            if($type=="from"){
                    //建立关联货位
                    $unpos_list = $libBranchProductPos->get_pos($product_id,$branch_id);
                    //未建立关联的货位
                    $pos_list = $libBranchProductPos->get_unassign_pos($branch_id);
                    //全部货位
                    $formpos = array_merge($unpos_list,$pos_list);
                    $options = "<select id=from_pos_id name=from_pos_id>";

                    if($unpos_list && is_array($unpos_list)){
                        foreach($unpos_list as $v){
                            $options .= "<option value=".$v['pos_id'].">".$v['store_position']."</option>";
                        }
                    }
             }else{

                    //调入货位由所有货位改为读取与商品关联货位
                    //建立关联货位
                    $unpos_list = $libBranchProductPos->get_pos($product_id,$branch_id);
                    //未建立关联的货位
                    //全部货位
                    $options = "<select id=to_pos_id name=to_pos_id>";
                    $options .= "<option value=''>选择</option>";
                    if($unpos_list && is_array($unpos_list)){
                        foreach($unpos_list as $v){
                            $options .= "<option value=".$v['store_position'].">".$v['store_position']."</option>";
                        }
                    }

                    /*
                     * 所有货位
                    $options = "<select id=to_pos_id name=to_pos_id><option value=''>请选择</option>";
                    if($Pos && is_array($Pos)){
                        foreach($Pos as $v){
                            $options .= "<option value=".$v['store_position'].">".$v['store_position']."</option>";
                        }
                    }
                    */
             }

            $options.="</select>";
                 if($type=="from"){

                    $options.="<input type=hidden id=from_branch_name name=from_branch_name value=".$branch_name.">";
                }else{
                    $options.="<input type=hidden id=to_branch_name name=to_branch_name value=".$branch_name.">";
                }
                echo $options;
        }else{

        }
    }

    /*
     * 关联货位
     * ss备注：货位相关方法，可以删除此方法，同时可删除页面：admin/stock/change_stock.html
     */
    function change_pos($product_id){

       $oBranch = $this->app->model("branch");
       $branch_list=$oBranch->Get_branchlist();
       $this->pagedata['branch_list'] = $branch_list;
       $this->pagedata['product_id'] = $product_id;

       //获取仓库模式
       //$branch_mode = app::get('ome')->getConf('ome.branch.mode');
       //$this->pagedata['branch_mode'] = $branch_mode;

       /*
         * 获取操作员管辖仓库
         */
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
           $branch_list_byuser = $oBranch->getBranchByUser();
        }
        $this->pagedata['branch_list_byuser']   = $branch_list_byuser;
        $is_super = 1;
        $this->pagedata['is_super']   = $is_super;

        $this->display("admin/stock/change_stock.html");
    }
    /*
     * ss备注：货位相关方法，可以删除此方法
     */
    function create_pos()
    {
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');
        
       $oBranch_product_pos = $this->app->model("branch_product_pos");
       $this->begin('index.php?app=ome&ctl=admin_stock&act=edit&p[0]='.$_POST['product_id']);
       $product_id = $_POST['product_id'];
       $branch_id = $_POST['branch_id'];
       $pos_id = $_POST['pos_id'];
       $pos_name = $_POST['pos_name'];
       $oBranch_pos = app::get('ome')->model("branch_pos");
       //判断货位是否存在
       $branch_pos = $oBranch_pos->dump(array('store_position'=>$pos_name,'branch_id'=>$branch_id), 'pos_id');
       if (!$branch_pos){
           $this->end(false, $pos_name.'货位不存在');
       }
       $pos_id = $branch_pos['pos_id'];
       //f($oBranch_product_pos->get_branch_pos_exist($product_id,$pos_id,$branch_id)>0){
       //if($oBranch_product_pos->get_branch_pos_exist($product_id,$branch_id)>0){
           //$this->end(false, app::get('base')->_('此商品已和此仓库建立过关联'));
       //}
       if ($oBranch_product_pos->dump(array('product_id'=>$product_id,'pos_id'=>$pos_id), 'pos_id')){
           $this->end(false, $pos_name.'货位已与此货品关联。');
       }
       $libBranchProductPos->create_branch_pos($product_id,$branch_id,$pos_id);
       $this->end(true, app::get('base')->_('关联成功'));
    }

    /**
     * 库存查询相关方法，2011.11.01更新
     */
    function search()
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');
        
        if($_POST['stock_search']){
            $keywords = addslashes(trim($_POST['stock_search']));
            
            $data    = $basicMaterialSelect->search_stockinfo($keywords);
            
            $str = '<em style="color:red">'.$keywords.'</em>';
            foreach ($data as &$row)
            {
                $row['bn']      = str_replace($keywords,$str,$row['bn']);
                $row['barcode'] = str_replace($keywords,$str,$row['barcode']);
                $row['name']    = str_replace($keywords,$str,$row['name']);
            }
            $this->pagedata['data'] = $data;
            $this->pagedata['keywords'] = $keywords;
        }
        $this->page("admin/stock/search.html");
    }

    /*
     * 货号及名称 自动填充
     */
    function getProductsByAuto(){
        $keywords = trim($_GET['stock_search']);
        if($keywords){
            $data1 = array();
            $data2 = array();

            $data1 = $this->getAutoData($keywords);
            $data1 = array_unique($data1);
            if(count($data1)<10){
                $data2 = $this->getAutoData($keywords,'has');
                $data2 = array_unique($data2);
            }
            $data = array_merge($data1,$data2);
            $data = array_unique($data);
            foreach($data as $key=>$val){
                $result[]['stock_search'] = $val;
            }
        }
        echo "window.autocompleter_json=".json_encode($result);
    }

    function getAutoData($keywords,$type='head')
    {
        $basicMaterial    = app::get('material')->model('basic_material');
        $materialBarcodeObj = app::get('material')->model('barcode');
        
        $data = array();
        if($keywords)
        {
            $filter = array(
                "material_bn|$type"=>$keywords
            );
            $data_ini    = $basicMaterial->getList('bm_id, material_bn, material_name', $filter, 0, 10);
            
            $data = array();
            if ($data_ini)
            foreach($data_ini as $k=>$v)
            {
                $data[] = $v['material_bn'];
                unset($v['material_bn']);
            }
            
            #查询条形码
            $filter = array(
                "code|$type"=>$keywords
            );
            $data_ini    = $materialBarcodeObj->getList('code', $filter, 0, 10);
            
            if ($data_ini)
            foreach($data_ini as $k=>$v){
                $data[] = $v['code'];
                unset($v['code']);
            }
            
            $filter = array(
                "material_name|head"=>$keywords
            );
            $data_ini    = $basicMaterial->getList('bm_id, material_bn, material_name', $filter, 0, 10);
            if ($data_ini)
            foreach($data_ini as $k=>$v)
            {
                $data[] = $v['material_name'];
                unset($v['material_name']);
            }
        }
        return $data;
    }

    function get_pos_store($product_id,$pos_id){
        $oBranchPorductPos = $this->app->model('branch_product_pos');
        $pos_store = $oBranchPorductPos->dump(array('product_id'=>$product_id,'pos_id'=>$pos_id),'store');

        if($pos_store){
            echo $pos_store['store'];
        }else{
            echo 0;
        }
    }

    function checkPos($branch_id,$pos_name){
        $oBranchPos = $this->app->model('branch_pos');
        $branch_pos = $oBranchPos->dump(array('branch_id'=>$branch_id,'store_position'=>$pos_name),'pos_id');

        if($branch_pos){
            echo $branch_pos['pos_id'];
        }else{
            echo 0;
        }
    }
    /**
     * 显示冻结库存的详情
     */
    function show_store_freeze_list(){
        $page = $_GET['page'] ? $_GET['page'] : 1;
        $pagelimit = 10;
        $store_freeze_num = $_GET['store_freeze_num'];
        $product_id = intval($_GET['product_id']);
        $oiObj = $this->app->model('order_items');
        $offset = ($page-1)*$pagelimit;

        // 重置冻结库存
        //danny_freeze_stock_log
        define('FRST_TRIGGER_OBJECT_TYPE','库存表：查看冻结库存时,重置冻结库存');
        define('FRST_TRIGGER_ACTION_TYPE','ome_ctl_admin_stock：show_store_freeze_list');
        if($page==1) kernel::single('ome_sync_product')->reset_freeze($product_id);

        $store_freeze = $oiObj->getStoreByProductId($product_id,$offset,$pagelimit);
        $count = $oiObj->count_order_id($product_id);
        $total_page = ceil($count/$pagelimit);
        $pager = $this->ui()->pager(array(
            'current'=>$page,
            'total'=>$total_page,
            'link'=>'index.php?app=ome&ctl=admin_stock&act=show_store_freeze_list&store_freeze_num='.$store_freeze_num.'&product_id='.$product_id.'&target=container&page=%d',
        ));

        $statusObj = kernel::single('ome_order_status');
        $shopObj = $this->app->model('shop');

        //$basicMaterialStockObj = app::get('material')->model('basic_material_stock');
        //$productInfo = $basicMaterialStockObj->dump($product_id,'store_freeze');
        
        //根据基础物料ID获取对应的冻结库存
        $basicMStockFreezeLib  = kernel::single('material_basic_material_stock_freeze');
        $productInfo    = array();
        $productInfo['store_freeze']    = $basicMStockFreezeLib->getMaterialStockFreeze($product_id);
        
        foreach($store_freeze as $k=>$v){
            $rows['nums'] = $v['nums'];
            $rows['sendnum'] = $v['sendnum'];
            $rows['status'] = $statusObj->ship_status($v['ship_status']);
            $rows['order_bn'] = $v['order_bn'];
            $rows['createtime'] = date("Y-m-d H:i:s",$v['createtime']);
            $rows['paytime'] = date("Y-m-d H:i:s",$v['paytime']);
            $rows['order_limit_time'] = date("Y-m-d H:i:s",$v['order_limit_time']);
            $rows['pay_status'] = $statusObj->pay_status($v['pay_status']);
            $shopInfo = $shopObj->dump($v['shop_id'],'name');
            $rows['shop_name'] = $shopInfo['name'];
            $row[] = $rows;
        }
        $this->pagedata['rows'] = $row;
        $this->pagedata['pager'] = $pager;
        $this->pagedata['store_freeze_num'] = $productInfo['store_freeze'];
        if($_GET['target']){
        	return $this->display('admin/stock/freeze.html');
        }
        $this->singlepage('admin/stock/freeze.html');

    }

    /**
     * 在途库存
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function show_arrive_store()
    {
        $arrivestockObj = kernel::single('ome_arrivestock');
        $arrive_list = kernel::single('ome_arrivestock')->get_all_diff();

        $basicMaterial    = app::get('material')->model('basic_material');
        $materials = array();
        $material_list    = $basicMaterial->getList('bm_id, material_bn', array('bm_id'=>array_keys($arrive_list)));

        foreach($material_list as $material){
            $materials[$material['bm_id']] = $material['material_bn'];
        
        }
        foreach ( $arrive_list as $ak=>$arrive ) {
            
             $arrive_list[$ak]['bn'] = $materials[$arrive['product_id']];
        }
      
        $this->pagedata['arrive_list'] =$arrive_list;
        unset($arrive_list);
        $this->display('admin/stock/arrivelist.html');
    }

    
    /**
     * 修复在途库存
     * @param   
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function repare_arrive_stock()
    {
        $product_ids = array_map('intval', (array)$_POST['product_ids']);
      
        $arrivestockObj = kernel::single('ome_arrivestock');
        $db = kernel::database();
        foreach ($product_ids as $product_id ) {
            $local_arrive_stock=0;
            $local_arrive_stock =$arrivestockObj->get_local_arrive_store($product_id);
            $local_arrive_stock = $local_arrive_stock[$product_id];
            $db->exec("UPDATE sdb_ome_branch_product SET arrive_store=0 WHERE product_id=".$product_id." AND arrive_store>0");
            $db->exec("delete from sdb_material_basic_material_stock_arrive where bm_id={$product_id}");
            if ($local_arrive_stock) {
                $arrivestockObj->repare_stream_arrive_store($product_id);
                foreach ( $local_arrive_stock as $lk=>$lv ) {
                    $branch_id = $lk;
                    $arrive_store = $lv;
                   
                    $db->exec("UPDATE sdb_ome_branch_product SET arrive_store=".$arrive_store." WHERE product_id=".$product_id." AND branch_id=".$branch_id);
                }
            }
        }
        
        
        $rs = 'success';
        echo json_encode($rs);
    }
}
?>
