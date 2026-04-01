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
class console_ctl_admin_stock extends desktop_controller{
    var $name = "库存查看";
    var $workground = "console_center";

    // function _views(){
    //     $sub_menu = $this->_views_stock();
    //     return $sub_menu;
    // }
    function _views_stock()
    {
        $basicMaterialSelect  = kernel::single('material_basic_select');
        $branch_productObj    = app::get('ome')->model('branch_product');

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
                'href'=>'index.php?app=console&ctl=admin_stock&act=index',

            )
        );

        $i=0;
        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }
            if($k==0){
                $sub_menu[$k]['addon'] = $basicMaterialSelect->countAnother($base_filter);
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
        ini_set('memory_limit','128M');

        # 商品可视状态
        if (!isset($_POST['visibility'])) {
            $filter['visibled']    = 1;//过滤隐藏的物料
        }elseif(empty($_POST['visibility'])){
            unset($_POST['visibility']);
        }

        /* 获取操作员管辖仓库 */
        $oBranch = app::get('ome')->model('branch');
        $is_super = kernel::single('desktop_user')->is_super();
        $branch_ids = $oBranch->getBranchByUser(true);
        if (!$is_super){
            $branch_ids = $oBranch->getBranchByUser(true);
            if ($branch_ids){
                $filter['branch_id'] = $branch_ids;
            }else{
                $filter['branch_id'] = 'false';
            }
        }

        $actions = array(
            array(
                'label' => '批量设置安全库存',
                'href'=>'index.php?app=console&ctl=admin_stock&act=batch_safe_store',
                'target' => "dialog::{width:700,height:400,title:'批量设置安全库存'}",
            ),

        );

        $params = array(
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
                'object_method'=>array('count'=>'countStock','getlist'=>'getListStock')
        );
        $this->finder('console_mdl_basic_material', $params);
    }

    /**
     * 库存初始化，测试中
     * redis库存高可用，废弃掉直接修改db库存、冻结的方法
     */
    function init_stock()
    {
        return false;
        return false;
        return false;

        $oBranchPorduct = app::get('ome')->model('branch_product');
        $oBranchPorduct -> update(array('store'=>50));

        $oMaterialStock    = app::get('material')->model('basic_material_stock');
        $oMaterialStock->update(array('store'=>50));
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
        $basicMaterialObj    = app::get('material')->model('basic_material');

        //获取该商品对应的供应商
        $bm_ids      = $basicMaterialObj->dump(array('bm_id'=>$product_id), 'bm_id');
        $goods_id    = $bm_ids['bm_id'];

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
    public function batch_upd_products()
    {
        $sql = 'UPDATE sdb_material_basic_material_stock SET alert_store=0';
        kernel::database()->exec($sql);

        $sql = 'UPDATE sdb_material_basic_material_stock SET alert_store=999 WHERE bm_id IN
            (
                SELECT product_id FROM sdb_ome_branch_product
                WHERE safe_store>(store - store_freeze + arrive_store)
            )';
        kernel::database()->exec($sql);
    }

    public function batch_safe_store_set(){
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
            if($init_all != 1) $filter['safe_store'] = 0;
            $oBranchPorduct = app::get('ome')->model('branch_product');

            if($init_type == 1)://固定数量设置
            $result = $oBranchPorduct -> update(array('safe_store'=>$safe_store,'last_modified'=>$last_modified),$filter);
                $this -> batch_upd_products();
                echo('finish');
                die();

            elseif($init_type == 2)://按销量计算
                $days = intval($_POST['days']);
                $hour = intval($_POST['hour']);

                //所有供应商的到货天数
                if ($supply_type == 2) {
                    $oSupplier = app::get('purchase')->model('supplier');
                    $suppliers = $oSupplier -> getList('supplier_id,arrive_days');
                    foreach($suppliers as $v){
                        $this -> suppliers[$v['supplier_id']] = $v['arrive_days'];
                    }
                }

                $branch_products = $oBranchPorduct -> getList('product_id',$filter,$page_no*$page_size,$page_size);
                if (!$branch_products) {
                    $this -> batch_upd_products();
                    echo('finish');
                    die();
                }else{
                    if ($page_no == 0){
                        $total_products = $oBranchPorduct -> count($filter);
                        echo(ceil($total_products/$page_size));
                    }
                }
                for($i=0;$i<sizeof($branch_products);$i++) {
                    $safe_store = $this -> calc_safe_store($branch_products[$i]['product_id'],$days,$hour,$filter['branch_id'],$supply_type);
                    $filter['product_id'] = $branch_products[$i]['product_id'];
                    $oBranchPorduct -> update(array('safe_store'=>$safe_store,'last_modified'=>$last_modified),$filter);
                }
        else:
            echo('Fatal error:init_type is null');
            endif;

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

        #过滤o2o门店虚拟仓库
        $brObj = app::get('ome')->model('branch');
        $row   = $brObj->getList('branch_id, name', array('b_type'=>1),0,-1);

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

        $this->display("admin/stock/batch_safe_store.html");
    }

    /*详情
     *ss备注：货位相关方法，可以删除此方法，同时可以删除页面'admin/stock/edit_stock.html','admin/stock/edit_stocks.html'
     */
    function edit($product_id=0)
    {
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');

       if($_POST){
           $this->begin('index.php?app=ome&ctl=admin_stock&act=edit&p[0]='.$_POST['product_id']);
           $product_id = $_POST['product_id'];
           $branch_id = $_POST['branch_id'];
           $pos_id = $_POST['pos_id'];

           $libBranchProductPos->create_branch_pos($product_id,$branch_id,$pos_id);
           $this->end(true, app::get('base')->_('关联成功'));
       }

       //基础物料信息
       $basicMaterialLib    = kernel::single('material_basic_material');
       $bMaterialRow        = $basicMaterialLib->getBasicMaterialExt($product_id);
       $productInfo         = array('product_id'=>$bMaterialRow['bm_id'], 'bn'=>$bMaterialRow['material_bn'], 'name'=>$bMaterialRow['material_name']);

       $oBranch = app::get('ome')->model("branch");
       $branch_list=$oBranch->Get_branchlist();

       $oPos = app::get('ome')->model("branch_pos");
       $pos = $oPos->select('*');
       $this->pagedata['branch_list'] = $branch_list;
       $this->pagedata['pos'] = $pos;
       $this->pagedata['product_id'] = $product_id;
       $this->pagedata['pro_detail'] = $productInfo;
       $this->page("admin/stock/edit_stock.html");
    }

    /*
     *ss备注：货位相关方法，可以删除此方法
     */
    function dosave()
    {
        $libBranchProduct    = kernel::single('ome_branch_product');
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');

        $this->begin('index.php?app=ome&ctl=admin_stock&act=edit&p[0]='.$_POST['product_id']);

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

            $oBranch_product_pos = app::get('ome')->model("branch_product_pos");
            $oBranch_product = app::get('ome')->model("branch_product");

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
    function get_op($branch_id,$ajax='false')
    {
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');

        $oBranch = app::get('ome')->model('branch');

        $Pos =$libBranchProductPos->get_unassign_pos($branch_id);

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
    function getPosByBranchProduct()
    {
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');

        $branch_id = $_GET['branch_id'];
        $pos_name = $_GET['store_position'];
        $product_id = $_POST['product_id'];

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
    function get_op1($branch_id,$ajax='false',$type,$product_id='')
    {
        $libBranchProductPos    = kernel::single('ome_branch_product_pos');

        $oBranch = app::get('ome')->model('branch');
        $Pos = $oBranch->Get_poslist($branch_id);

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

       $oBranch = app::get('ome')->model("branch");
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

       $oBranch_product_pos = app::get('ome')->model("branch_product_pos");
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

       if ($oBranch_product_pos->dump(array('product_id'=>$product_id,'pos_id'=>$pos_id), 'pos_id')){
           $this->end(false, $pos_name.'货位已与此货品关联。');
       }

       $libBranchProductPos->create_branch_pos($product_id,$branch_id,$pos_id);
       $this->end(true, app::get('base')->_('关联成功'));
    }

    /**
     * 库存查询相关方法，2011.11.01更新
     */
    function search(){

        $basicMaterialSelect    = kernel::single('material_basic_select');

        if($_POST['stock_search']){
            $keywords = addslashes(trim($_POST['stock_search']));

            $data = $basicMaterialSelect->search_stockinfo($keywords, $branch_type, 500);

            $str = '<em style="color:red">'.$keywords.'</em>';
            foreach ($data as &$row) {
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
        $oBranchPorductPos = app::get('ome')->model('branch_product_pos');
        $pos_store = $oBranchPorductPos->dump(array('product_id'=>$product_id,'pos_id'=>$pos_id),'store');

        if($pos_store){
            echo $pos_store['store'];
        }else{
            echo 0;
        }
    }

    function checkPos($branch_id,$pos_name){
        $oBranchPos = app::get('ome')->model('branch_pos');
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
    function show_store_freeze_list()
    {
        $page = $_GET['page'] ? $_GET['page'] : 1;
        $pagelimit = 10;
        $store_freeze_num = $_GET['store_freeze_num'];
        $product_id = intval($_GET['product_id']);
        $oiObj = app::get('ome')->model('order_items');
        $offset = ($page-1)*$pagelimit;

        // 重置冻结库存
        define('FRST_TRIGGER_OBJECT_TYPE','库存表：查看冻结库存时,重置冻结库存');
        define('FRST_TRIGGER_ACTION_TYPE','ome_ctl_admin_stock：show_store_freeze_list');
        
        /**
         * 重置冻结库存
         * 
         * @todo：只允许有权限的操作员点击重新计算库存冻结数量;
         */
        /*
        $isPower = kernel::single('desktop_user')->has_permission('console_reset_freeze');
        if($page==1 && $isPower){
            kernel::single('ome_sync_product')->reset_freeze($product_id);
            
            //重置预占流水记录
            $result = kernel::single('console_stock_freeze')->reset_stock_freeze($product_id);
            if(!$result){
                die('操作错误!');
            }
        }
        */
        
        $store_freeze = $oiObj->getStoreByProductId($product_id,$offset,$pagelimit);
        $count = $oiObj->count_order_id($product_id);
        $total_page = ceil($count/$pagelimit);
        $pager = $this->ui()->pager(array(
            'current'=>$page,
            'total'=>$total_page,
            'link'=>'index.php?app=ome&ctl=admin_stock&act=show_store_freeze_list&store_freeze_num='.$store_freeze_num.'&product_id='.$product_id.'&target=container&page=%d',
        ));

        $statusObj = kernel::single('ome_order_status');
        $shopObj = app::get('ome')->model('shop');

        $basicMaterialLib    = kernel::single('material_basic_material');
        $productInfo    = $basicMaterialLib->getBasicMaterialStock($product_id);

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
            
            //是否指定仓发货
            $rows['is_assign_store'] = $v['is_assign_store'];
            
            $row[] = $rows;
        }
        $this->pagedata['rows'] = $row;
        $db = kernel::database();
        //采购退货
         $sql = "select ai.product_id,a.branch_id,ai.num,a.rp_bn from sdb_purchase_returned_purchase as a LEFT JOIN sdb_purchase_returned_purchase_items as ai ON a.rp_id = ai.rp_id
                        where a.rp_type in ('eo') AND a.return_status in ('1','4')   AND a.check_status in ('2') and ai.product_id in (".$product_id.") ";

        $return_list = $db->select($sql);
        //其他出库
        $stock_sql = "select ai.product_id,a.branch_id,ai.nums,a.iso_bn from sdb_taoguaniostockorder_iso as a LEFT JOIN sdb_taoguaniostockorder_iso_items as ai ON a.iso_id = ai.iso_id
                        where a.iso_status in ('1','2') AND a.check_status in ('2') AND a.type_id in('5','7','100','300','40') AND ai.product_id in (".$product_id.")";
        $stock_list = $db->select($stock_sql);
        //换货出库
        $reship_sql = "SELECT ai.product_id,r.changebranch_id as branch_id,ai.num,r.reship_bn FROM sdb_ome_reship as r LEFT JOIN sdb_ome_reship_items as ai ON r.reship_id=ai.reship_id WHERE r.return_type='change' AND r.change_status='0' AND ai.return_type='change' AND r.is_check in('1','11') AND ai.product_id in (".$product_id.")";
        $reship_list = $db->select($reship_sql);

        //转储出库
        $stockdump_sql = "SELECT d.stockdump_bn,ai.num FROM sdb_console_stockdump as d LEFT JOIN sdb_console_stockdump_items as ai ON d.stockdump_id=ai.stockdump_id WHERE d.confirm_type='1' AND d.self_status='1' AND d.in_status='0' AND ai.product_id in (".$product_id.") ";
        $stockdump_list = $db->select($stockdump_sql);
        $this->pagedata['stockdump_list'] = $stockdump_list;
        unset($stockdump_list);

        //唯品会出库记录
        $bn    = $productInfo['material_bn'];
        $sql   = "SELECT a.stockout_id, a.stockout_no, a.branch_id, b.stockout_item_id, b.num, b.bn FROM sdb_purchase_pick_stockout_bills AS a
                  LEFT JOIN sdb_purchase_pick_stockout_bill_items AS b ON a.stockout_id=b.stockout_id
                  WHERE a.status=1 AND a.confirm_status=2 AND b.bn='". $bn ."'";
        $stockout_list    = $db->select($sql);
        $this->pagedata['stockout_list']    = $stockout_list;

        $this->pagedata['return_list'] = $return_list;
        $this->pagedata['stock_list'] = $stock_list;
        $this->pagedata['reship_list'] = $reship_list;

        //人工库存预占记录(状态：预占中) 展示
        $sql_af = "select bmsaf_id,freeze_num,original_bn,original_type from sdb_material_basic_material_stock_artificial_freeze where status=1 and bm_id in (".$product_id.")";
        $this->pagedata["af_list"] = $db->select($sql_af);
        
        //检查是否安装dealer应用
        $dealerOrders = array();
        if(app::get('dealer')->is_installed()){
            //经销一件代发订单库存预占
            $sql = "SELECT a.plat_item_id,a.product_id,a.bn,a.nums, b.plat_order_id,b.plat_order_bn,b.shop_id,b.process_status,b.createtime FROM sdb_dealer_platform_order_items AS a ";
            $sql .= " LEFT JOIN sdb_dealer_platform_orders AS b ON a.plat_order_id=b.plat_order_id WHERE a.product_id=". $product_id ." AND is_shopyjdf_type='2' ";
            $sql .= " AND a.process_status='unconfirmed' AND a.is_delete='false' AND b.process_status IN('unconfirmed', 'fail')";
            $tempList = $db->select($sql);
            if($tempList){
                foreach ($tempList as $itemKey => $itemVal)
                {
                    $plat_order_id = $itemVal['plat_order_id'];
                    
                    //shop
                    $shopInfo = $shopObj->dump($itemVal['shop_id'], 'name');
                    $itemVal['shop_name'] = $shopInfo['name'];
                    
                    $dealerOrders[$plat_order_id] = $itemVal;
                }
            }
        }
        
        $this->pagedata['dealerOrders'] = $dealerOrders;

        //获取唯品会JIT销售订单列表
        $jitOrders = kernel::single('console_inventory_orders')->getVopOrderStockFreeze($product_id);
        $this->pagedata['jitOrders'] = $jitOrders;

        
        $this->pagedata['pager'] = $pager;
        $this->pagedata['store_freeze_num'] = $productInfo['store_freeze'];
        if($_GET['target']){
            return $this->display('admin/stock/freeze.html');
        }
        $this->singlepage('admin/stock/freeze.html');

    }

    /**
     * @sunjing@shopex.cn
     * @DateTime  2017-09-13T14:28:40+0800
     * @return
     *  在途库存列表查看
     */
    public function show_arrive_store_list(){
        $product_id = intval($_GET['product_id']);
        $data = kernel::single('ome_arrivestock')->getStreamList($product_id);
        $this->pagedata['data'] = $data;
        unset($po_list,$iso_list);
        if($_GET['target']){
            return $this->display('admin/stock/arrive_stock.html');
        }
        $this->singlepage('admin/stock/arrive_stock.html');

    }

    /**
     * 单个订单重置库存预占流水
     */
    public function reset_order_store_freeze(){
        
        if($_POST){
            $act = $_POST['page_act'];
            $order_bn = $_POST['order_bn'];
            
            if(empty($order_bn)){
                die("订单号不能为空");
            }
            
            $orderObj = app::get('ome')->model("orders");
            $shopObj = app::get('ome')->model('shop');
            
            //订单信息
            $sql = "SELECT order_id,order_bn,shop_id,ship_status,process_status FROM sdb_ome_orders WHERE order_bn='". $order_bn ."' AND ship_status IN('0','2') AND status='active' 
                    AND process_status IN('unconfirmed','splitting','splited')";
            $orderInfo = $orderObj->db->selectrow($sql);
            if(empty($orderInfo)){
                die("订单不存在或者已发货");
            }
            $order_id = $orderInfo['order_id'];
            $shop_id = $orderInfo['shop_id'];
            
            //重置冻结流水
            // 禁止手动重算
            // 禁止手动重算
            // 禁止手动重算
            if($act == 'reset' && 0){
                //发货单
                $sql = "SELECT dord.delivery_id FROM sdb_ome_delivery_order AS dord LEFT JOIN sdb_ome_delivery AS d ON(dord.delivery_id=d.delivery_id) 
                        WHERE dord.order_id='". $order_id ."' AND d.is_bind='false' AND d.disabled='false' AND d.status NOT IN('failed','cancel','back','return_back')";
                $deliveryList = $orderObj->db->select($sql);
                
                $dlyItemList = array();
                if($deliveryList){
                    $delivery_ids = array();
                    foreach ($deliveryList as $key => $val){
                        $delivery_ids[] = $val['delivery_id'];
                    }
                    
                    //发货明细
                    $sql = "SELECT product_id,number FROM sdb_ome_delivery_items WHERE delivery_id in(". implode(',', $delivery_ids) .")";
                    $temp_data  = $orderObj->db->select($sql);
                    foreach ($temp_data as $key => $val){
                        $product_id = $val['product_id'];
                        
                        $dlyItemList[$product_id] += $val['number'];
                    }
                }
                
                //订单明细
                $ordItemList = array();
                $sql = "SELECT product_id,nums FROM sdb_ome_order_items WHERE order_id=".$order_id." AND `delete`='false'";
                $itemList  = $orderObj->db->select($sql);
                foreach ($itemList as $key => $val){
                    $product_id = $val['product_id'];
                    
                    $ordItemList[$product_id] += $val['nums'];
                }
                
                //计算库存冻结数量
                foreach ($ordItemList as $product_id => $val){
                    if($dlyItemList[$product_id]){
                        $ordItemList[$product_id] = $ordItemList[$product_id] - $dlyItemList[$product_id];
                    }
                }
                
                //重置库存冻结流水
                $update_sql = "UPDATE sdb_material_basic_material_stock_freeze SET num=0 WHERE obj_id=".$order_id." AND obj_type=1";
                $orderObj->db->exec($update_sql);
                
                foreach ($ordItemList as $product_id => $freeze_num){
                    
                    $sel_sql = "SELECT * FROM sdb_material_basic_material_stock_freeze WHERE obj_id=".$order_id." AND bm_id=".$product_id." AND obj_type=1";
                    $tempData = $orderObj->db->selectrow($sel_sql);
                    
                    if($tempData){
                        $save_sql = "UPDATE sdb_material_basic_material_stock_freeze SET num=".$freeze_num." WHERE obj_id=".$order_id." AND bm_id=".$product_id." AND obj_type=1";
                    }else{
                        $save_sql = "INSERT INTO sdb_material_basic_material_stock_freeze(bmsq_id,bm_id,obj_id,obj_type,shop_id,branch_id,num) 
                                     VALUES(-1,".$product_id.",".$order_id.",1,'". $shop_id ."',0,".$freeze_num.")";
                    }
                    $orderObj->db->exec($save_sql);
                    
                    //重置冻结库存
                    kernel::single('ome_sync_product')->reset_freeze($product_id);
                }
                
                //修复订单状态
                if($_POST['update_status'] == 'true'){
                    //确认状态
                    $process_status = 'unconfirmed';
                    if($delivery_ids){
                        $sql = "SELECT SUM(number) as nums FROM sdb_ome_delivery_items WHERE delivery_id in(". implode(',', $delivery_ids) .")";
                        $tempData  = $orderObj->db->selectrow($sql);
                        $dly_num = intval($tempData['nums']);
                        
                        $sql = "SELECT SUM(nums) as nums FROM sdb_ome_order_items WHERE order_id=".$order_id." AND `delete`='false'";
                        $tempData  = $orderObj->db->selectrow($sql);
                        $item_num = intval($tempData['nums']);
                        
                        if($item_num == $dly_num){
                            $process_status = 'splited';
                        }else{
                            $process_status = 'splitting';
                        }
                        
                        $orderInfo['process_status'] = $process_status;
                    }
                    
                    //发货状态
                    $ship_status = '0';
                    if($delivery_ids){
                        $sql = "SELECT SUM(a.number) as nums FROM sdb_ome_delivery_items AS a LEFT JOIN sdb_ome_delivery as b ON a.delivery_id=b.delivery_id 
                                WHERE b.delivery_id in(". implode(',', $delivery_ids) .") AND b.status='succ'";
                        $tempData  = $orderObj->db->selectrow($sql);
                        $dly_num = intval($tempData['nums']);
                        
                        $sql = "SELECT SUM(nums) as nums FROM sdb_ome_order_items WHERE order_id=".$order_id." AND `delete`='false'";
                        $tempData  = $orderObj->db->selectrow($sql);
                        $item_num = intval($tempData['nums']);
                        
                        if($item_num == $dly_num){
                            $ship_status = '1';
                        }elseif($dly_num && $item_num > $dly_num){
                            $ship_status = '2';
                        }else{
                            $ship_status = '0';
                        }
                        
                        $orderInfo['ship_status'] = $ship_status;
                    }
                    
                    //更新
                    $save_sql = "UPDATE sdb_ome_orders SET process_status='". $process_status ."', ship_status='". $ship_status ."' WHERE order_id=".$order_id;
                    $orderObj->db->exec($save_sql);
                }
            }
            
            //格式化
            if($orderInfo['ship_status'] == '2'){
                $orderInfo['shipStatus'] = '部分发货';
            }
            else
            {
                $orderInfo['shipStatus'] = '未发货';
            }
            
            if($orderInfo['process_status'] == 'splited'){
                $orderInfo['processStatus'] = '已拆分完';
            }elseif($orderInfo['process_status'] == 'splitting'){
                $orderInfo['processStatus'] = '部分拆分';
            }else{
                $orderInfo['processStatus'] = '未确认';
            }
            $this->pagedata['orderInfo'] = $orderInfo;
            
            //店铺信息
            $shopInfo = $shopObj->dump($shop_id, 'name');
            $this->pagedata['shop_name'] = $shopInfo['name'];
            
            //查询冻结流水
            $sql = "SELECT * FROM sdb_material_basic_material_stock_freeze WHERE obj_id=".$order_id." AND obj_type=1";
            $freezeList = $orderObj->db->select($sql);
            if($freezeList){
                foreach ($freezeList as $key => $val){
                    $material_sql = "SELECT material_bn FROM sdb_material_basic_material WHERE bm_id=".$val['bm_id'];
                    $materialInfo = $orderObj->db->selectrow($material_sql);
                    
                    $freezeList[$key]['material_bn'] = $materialInfo['material_bn'];
                }
            }
            
            $this->pagedata['freezeList'] = $freezeList;
            
            return $this->display('admin/stock/reset_order_store_freeze.html');
        }
        
        $this->singlepage('admin/stock/reset_order_store_freeze.html');
    }
}
?>
