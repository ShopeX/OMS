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
class console_ctl_admin_stockdump extends desktop_controller{

    var $name = "库内转储";
    var $workground = "console_center";
    public function index(){
        
        $this->title = '库内转储';
        $params = array(
            'title'=>$this->title,
            'base_filter' =>$base_filter,
            'actions' => array(
                array(
                    'label' => '新建转储单',
                    'icon' => 'add.gif',
                    'href' => 'index.php?app=console&ctl=admin_stockdump&act=add',
                    'target' => '_blank',
                ),
                array(
                        'label' => '导出模版',
                        'icon' => 'add.gif',
                        'href' => 'index.php?app=console&ctl=admin_stockdump&act=export_template',
                        'target' => '_blank',
                    ),
            ),
           'orderBy' => 'create_time DESC',
            'use_buildin_recycle'=>false,
            'use_buildin_import'=>true,
            'use_buildin_export'=>true,
            'use_buildin_filter'=>true,
        );
        
        $is_super = kernel::single('desktop_user')->is_super();

        if (!$is_super){
            // 普通管理员：默认无权限
            $base_filter['stockdump_id'] = array('false');
            
            // 获取有权限的门店仓库（b_type=2 表示门店）
            $mdlOmeBranch = app::get('ome')->model('branch');
            $branchList = $mdlOmeBranch->getList('branch_id', array(
                'b_type' => '2',
                'is_ctrl_store' => '1'
            ), 0, -1);
            
            if (!empty($branchList)) {
                $branch_ids = array_column($branchList, 'branch_id');
                $where_branch_id = '('.implode(',', $branch_ids).')';
                // 调出仓或调入仓有一方有权限就可以展示
                $base_filter['filter_sql'] = "(from_branch_id in ".$where_branch_id." or to_branch_id in ".$where_branch_id.")";
                unset($base_filter['stockdump_id']); // 有权限时移除默认的false限制
            }
        }
        $this->finder('console_mdl_stockdump',$params);
    }

   /*
    * 新建调拨单
    */
    public function add(){
        $OBranch = app::get('ome')->model('branch');

        $branch_id= $OBranch->getBranchByUser(true);
        if($branch_id){
            $where= " AND wb.branch_id in(".implode(',', $branch_id).")";
        }
        #获取非自有WMS_id
        $wms_list = kernel::single('console_goodssync')->get_wms_list('selfwms','notequal');
        if($wms_list){
            $sql = "SELECT wb.branch_bn,wb.name,wb.branch_id FROM sdb_ome_branch as wb  WHERE wb.wms_id != ''".$where." AND wb.wms_id in(".implode(',',$wms_list).") AND wb.b_type=1";
            $branch = kernel::database()->select($sql);
        }
        
        $this->pagedata['operator'] = kernel::single('desktop_user')->get_name();
        $this->pagedata['branch'] = $branch ;
        $this->singlepage("admin/stockdump/add.html");
    }

    /*
     * 库存入库单保存
     */
    public function do_save()
    {
        $libBranchProduct    = kernel::single('ome_branch_product');
        
        $url = 'index.php?app=console&ctl=admin_stockdump&act=add';
        $this->begin($url);

        if(empty($_POST['product_id'])){
            $this->end(false,'请添加转储商品');
        }

        $oStockdump = $this->app->model('stockdump');
        $from_branch_id = $_POST['from_branch_id'];
        $to_branch_id = $_POST['to_branch_id'];
        $memo = $_POST['memo'];
        $num = $_POST['num'];
        $ckid = $_POST['ckid'];
        $product_id = $_POST['product_id'];
        $product_bn = $_POST['product_bn'];
        $appro_price = $_POST['to_stock_price'];
        
        $op_name = kernel::single('desktop_user')->get_login_name();
        $options = array(
            #'type' => 600,
            #'otype' => 2,
            'op_name' => $op_name,
            #'in_status' => 8,
            'from_branch_id' => $from_branch_id,
            'to_branch_id' => $to_branch_id,
            'memo' => $memo,
        );
        if(empty($ckid)){
           $this->end(false,'请勾选您需要操作的商品',$url);
        }

        //选择商品数量判断
        $spmn = console_stock::SELECT_PRODUCT_MAX_NUM;
        if(count($ckid)>$spmn){
            $this->end(false,'选择转储商品最大数量不能大于'.$spmn.'!');
        }

        if($from_branch_id=='' && $to_branch_id==''){
            $this->end(false,'调出仓库和调入仓库必须选择',$url);
        }
        
        foreach($ckid as $k=>$v){
           if(intval($num[$v])<=0){
               $this->end(false,($k+1).'行数量应大于0',$url);
           }
          if(!is_numeric($appro_price[$v]) || intval($appro_price[$v])<0){
               $this->end(false,($k+1).'行金额格式错误',$url);
           }
           
           //获取单仓库-单个基础物料中的可用库存
           $usable_store    = $libBranchProduct->get_available_store($from_branch_id, $product_id[$v]);
           
           if($usable_store < $num[$v]){
                $this->end(false,($k+1).'行仓库可用库存不足!','index.php?app=console&ctl=admin_stockdump&act=add');
           }
           
           $adata[$k] = array(
               'product_id'=>$product_id[$v],
               'num'=>$num[$v],
               'appro_price'=>$appro_price[$v],
               'bn'=>$product_bn[$v],
           );
        }

        $appro_data  = [];
        $approResult = $oStockdump->to_savestore($adata,$options, $appro_data);
        
        if($approResult){
            kernel::single('console_iostockdata')->notify_stockdump($approResult['stockdump_id'],'create');

            $this->end(true,'转储单保存成功!');
        }else{

            $this->end(false,'转储单保存未成功!');
        }
    }

    /**
    * 取消转储单
    * @access public
    * @param String $stockdump_id 转储单编号
    * @return 
    */
    function do_cancel($stockdump_id){
        $oStockdump = $this->app->model('stockdump');
        if (empty($stockdump_id)){
            $result['rsp'] = 'fail';
        }else{
            $result = kernel::single('console_iostockdata')->notify_stockdump($stockdump_id,'cancel');
            
        }
        if(!is_array($result)) {
            $result = [
                'rsp' => 'fail',
                'msg' => $result
            ];
        }
        die(json_encode($result));
    }

    public function do_save_operation(){
        $this->begin('index.php?app=console&ctl=admin_stockdump&act=index');

        $stockdump_bn = $_POST['stock_bn'];
        
        $approObj = app::get('console')->model('stockdump');
        $app_detail = $approObj->getList('self_status,in_status',array('stockdump_bn'=>$stockdump_bn),0,1);
        if ($app_detail[0]['self_status'] != '1' || !in_array($app_detail[0]['in_status'],array('0'))){
            $this->end(true,'转储单取消成功!'); 
        }
        $type = $approObj->update(array('self_status'=>'0','response_time'=>time()),array('stockdump_bn'=>$stockdump_bn));
        
        $stock_save = kernel::single('console_stock');
        $clear = $stock_save->clear_stockout_store_freeze($stockdump_bn);
        if($type){
            $this->end(true,'转储单取消成功!');
        }else{

            $this->end(false,'转储单取消失败!');
        }
    }

    public function get_wms_branch(){
        $branch_id = intval($_POST['branch_id']);
        if ($branch_id <= 0) {
            echo '';
            return;
        }
        $branch_model = app::get('ome')->model('branch');

        $sql = "SELECT wb.wms_id,wb.branch_bn FROM `sdb_ome_branch` as wb WHERE wb.branch_id = ".$branch_id;
        $wms_info = kernel::database()->select($sql);
        $bn_array = array();    
        $bn_str = '';    
        $bn = $branch_model->getList('branch_bn',array('wms_id'=>$wms_info[0]['wms_id']));
        $bn_array[] = 0;
        foreach($bn as $v){
            if( $v['branch_bn'] == $wms_info[0]['branch_bn'] ) continue;
            $bn_array[] = $v['branch_bn'];
        }
        
        $where = '';
        
        $where .= ' AND branch_bn in(\''.join('\',\'',$bn_array).'\')';
        

        $obranch_id= $branch_model->getBranchByUser(true);
        if($obranch_id){
            foreach($obranch_id as $k=>&$v){
                if($v == $branch_id) unset($obranch_id[$k]);
            }
            $where .= " AND branch_id in(".implode(',', $obranch_id).")";
        }
        
        //过滤掉门店仓
        $where .= " AND b_type=1";
        
        $sql = "SELECT * FROM sdb_ome_branch WHERE 1=1 ".$where;
        $branch_info = kernel::database()->select($sql);
        
        $str = '<select id="to_branch_id" class=" x-input-select inputstyle" vtype="required" name="to_branch_id" >';
        $str .= '<option></option>';
        foreach($branch_info as $v){
            $str .= '<option value='.$v['branch_id'].'>'.$v['name'].'</option>';
        }
        $str .= '</select> <span style="color:red">*</span> ';

        echo $str;
    }

    /*下载导入模版*/
    public function exportTemplate(){
        $filename = "转储单".date('Y-m-d').".csv";
        $encoded_filename = urlencode($filename);
        $encoded_filename = str_replace("+", "%20", $encoded_filename);
        $ua = $_SERVER["HTTP_USER_AGENT"];
        header("Content-Type: text/csv");
        if (preg_match("/MSIE/", $ua)) {
            header('Content-Disposition: attachment; filename="' . $encoded_filename . '"');
        } else if (preg_match("/Firefox$/", $ua)) {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $filename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $pObj = $this->app->model('stockdump');
        $title1 = $pObj->exportTemplate('title');
        echo '"'.implode('","',$title1).'"';
        echo "\n";
        $title2 = $pObj->exportTemplate('items');
        echo '"'.implode('","',$title2).'"';
    }
    
    function remind_setting()
    {
        $this->begin('index.php?app=omestorage&ctl=admin_stockdump&act=remind_setting');
        if(isset($_POST['remind_setting_days'])){
            if ($_POST['remind_setting_days'] &&!preg_match("/^[0-9]*[1-9][0-9]*$/",$_POST['remind_setting_days'])) {
                $this->end(false,app::get('omestorage')->_('超时提醒设置请输入正整数'));
            }
            if(empty($_POST['remind_setting_days'])) $_POST['remind_setting_days'] = 'nosetting';
            $this->app->setConf("stockdump_remind_setting_days",$_POST['remind_setting_days']);
            $this->end(true,"设置成功！");
        }
        $stockdump_remind_setting_days = $this->app->getConf("stockdump_remind_setting_days");
        if($stockdump_remind_setting_days == 'nosetting') 
            $this->pagedata['days'] = '';
        elseif(empty($stockdump_remind_setting_days))
            $this->pagedata['days'] = 3;
        else
            $this->pagedata['days'] = $stockdump_remind_setting_days;
        $this->pagedata['action_url'] = "index.php?app=omestorage&ctl=admin_stockdump&act=remind_setting";
        $this->pagedata['msg'] = "天,仍未开始转储,则发出超时提醒";
        $this->page("admin/remind_setting.html");
    }

    /**
     * 基础物料列表弹窗数据获取方法
     * 
     * @param Void
     * @return String
     */
    function findMaterial($supplier_id=null)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        
        #供应商频道
        if ($supplier_id)
        {
            //根据供应商商品
            $oSupplierGoods = app::get('purchase')->model('supplier_goods');
        
            $supplier_goods = $oSupplierGoods->getSupplierGoods($supplier_id);
        
            $products    = $basicMaterialObj->getList('bm_id', array('bm_id'=>$supplier_goods['bm_id']));
        
            $product_list = array();
            if ($products)
            foreach ($products as $k=>$v){
                $product_list[] = $v['bm_id'];
            }
            
            $base_filter['bm_id']    = $product_list;
        }
        
        //只能选择可见的物料作为组合的明细内容
        $base_filter['visibled'] = 1;

        if($_GET['type'] == 1){
            $base_filter['type'] = 1;
        }
        
        $params = array(
            'title'=>'基础物料列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'base_filter' => $base_filter,
        );
        $this->finder('material_mdl_basic_material', $params);
    }

    function get_proinfo()
    {
        $filter = array();
        if($_POST['product_id'][0] != '_ALL_')
        {
            $filter['bm_id'] = $_POST['product_id'];
        }
        
        $basicMaterialSelect    = kernel::single('material_basic_select');
        
        $oBranch = app::get('ome')->model('branch');
        $oBranch_product = app::get('ome')->model('branch_product');
        
        #基础物料
        $product = $basicMaterialSelect->getlist_ext('bm_id, material_bn, material_name, retail_price, unit, specifications', $filter);
        
        foreach($product as $k=>$v){
            $product[$k]['price'] = $product[$k]['price'] ? $product[$k]['price'] : '0';
            $product[$k]['spec_value']=$product[$k]['specifications'];

            /*获取和此商品建立过关系的仓库*/
            $branch_product = array();
            $branch_product = $oBranch_product->getlist('*',array('product_id'=>$v['product_id']));
            foreach($branch_product as $bk=>$bv){
                $branch = $oBranch->dump($bv['branch_id'],'name');
                $branch_product[$bk]['branch_name'] = $branch['name'];
                $branch = array();
            }
            $product[$k]['branch_product'] = $branch_product;
        }
        
        echo json_encode($product);
    }

    public function get_products(){
        $name = $_GET['name'];
        $bn = $_GET['bn'];
        if($_GET['name']){
            $filter['material_name'] = $_GET['name'];
        }
        if($_GET['bn']){
            $filter['material_bn'] = $_GET['bn'];
        }
        if($filter)
        {
            $basicMaterialSelect    = kernel::single('material_basic_select');
            
            $branchObj = app::get('ome')->model('branch');
            $branchProductObj = app::get('ome')->model('branch_product');
            
            $products    = $basicMaterialSelect->getlist('*', $filter, 0, 10);
            
            foreach($products as $key=>$product)
            {
                $branch_product = $branchProductObj->getlist('*',array('product_id'=>$product['product_id']));

                foreach($branch_product as $k=>$v){
                    $branch = $branchObj->dump($v['branch_id'],'name');
                    $branch_product[$k]['branch_name'] = $branch['name'];
                }

                $data[$key]['name'] = $product['name'];
                $data[$key]['id'] = $product['product_id'];
                $data[$key]['bn'] = $product['bn'];
                $products[$key]['branch_product'] = $branch_product;
            }
        }
       
        echo "window.autocompleter_json=".json_encode($products);
    }

    /**
    * 取消出入库单确定框
    * @access public
    * @param Number $stock_id 入库单ID
    * @param String $stock_bn 入库单编号
    * @param String $type 出入库,stockin入库;stockout出库
    * @return 
    */
    function cancel($stock_id,$stock_bn){
        $this->pagedata['stock_id'] = $stock_id;
        $this->pagedata['stock_bn'] = $stock_bn;
        
        $title = '转储单';
        $ctl = 'admin_stockdump';
        $this->pagedata['title'] = '转储单';
        $this->pagedata['ctl'] = $ctl;
        $this->display('admin/stockdump/cancel_confirm.html');
    }

     /**
    * 转储单查异查看
    * @access public
    * @param Number $stock_id 入库单ID
    * @param String $stock_bn 入库单编号
    * @param String $type 出入库,stockin入库;stockout出库
    * @return 
    */
    function difference($stockdump_id){
        $branchObj = app::get('ome')->model('branch');
        
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $oStockdump = app::get('console')->model('stockdump');
              
        $oStockdump_items = app::get('omestorage')->model('stockdump_items');
        $items = $itemObj->db->select("SELECT * from sdb_console_stockdump_items where stockdump_id=".intval($stockdump_id)." and (`in_nums`!=`num` or `defective_num`!=0)");
        if (!$items){header('Content-Type:text/html; charset=utf-8');
                 echo "该单没有差异！";exit;}
        foreach ($items as $key => $item)
        {
            //将商品的显示名称改为后台的显示名称
            $bm_ids    = $basicMaterialObj->dump(array('material_bn'=>$items['bn']), 'bm_id');
            
            $product_name    = $basicMaterialLib->getBasicMaterialExt($bm_ids['bm_id']);
            
            $items[$key]['product_name'] = $product_name['material_name'];
            $items[$key]['spec_info'] = $product_name['specifications'];
            $items[$key]['unit'] = $product_name['unit'];
        
        }
        $finder_id = $_GET['_finder']['finder_id'];

        $this->pagedata['items'] = $items;
        $this->pagedata['finder_id'] = $finder_id;
        $this->pagedata['appr_id'] = $appr_id;
        
        $this->singlepage('admin/stockdump/stockin_diff_item.html');
    }

    public function do_save_confirm_type(){
        $this->begin('javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();');
        
        $stockdump_bn = $_GET['stockdump_bn'];
        $finder_id = $_GET['finder_id'];
        
        $stock_save = kernel::single('console_receipt_stockdump');
        $result = $stock_save->confirm_stock($stockdump_bn);
        
        if($result['rsp'] == 'succ'){
            $this->end(true,'转储单确认成功!');
        }else{
            $this->end(false,$result['msg']);    
        }
    }

    
    /**
     * 转储导出模板
     * @param 
     * @return 
     * @access  public
     * @author sunjing@shopex.cn
     */
    public function export_template()
    {
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=转储单".date('Ymd').".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $stoObj = app::get('console')->model('stockdump');
        $title1 = $stoObj->exportTemplate('title');
        $title2 = $stoObj->exportTemplate('items');
        echo '"'.implode('","',$title1).'"';
        echo "\n\n";
        echo '"'.implode('","',$title2).'"';
    }
    
}
