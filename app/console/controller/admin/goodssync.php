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

class console_ctl_admin_goodssync extends desktop_controller{
    var $workground = "console_center";
       function index(){
        $finder_id = $_REQUEST['_finder']['finder_id'] ? : substr(md5($_SERVER['QUERY_STRING']), 5, 6);
        if(!isset($_GET['wms_id'])){
            $wms_list = kernel::single('channel_func')->getWmsChannelList();
            $_GET['wms_id'] = $wms_list[0]['wms_id'];
        }
        
        $cur_wms_id = $_GET['wms_id'];
        $pUrl='index.php?app=desktop&act=alertpages&goto='.urlencode('index.php?app=console&ctl=admin_goodssync&act=findMaterial&wms_id='.$cur_wms_id);
        $pkgUrl='index.php?app=desktop&act=alertpages&goto='.urlencode('index.php?app=material&ctl=admin_material_sales&act=findSalesMaterial&type=2');
        $params = array(
            'title'=>app::get('wms')->_('基础物料分配'),
            'actions'=>array(
                 array(
                    'label' => '批量同步',
                    'submit' => 'index.php?app=console&ctl=admin_goodssync&act=sync&wms_id='.$_GET['wms_id'],
                 ),
                 array(
                    'label' => '货品分配',
                    'onclick' => <<<JS
javascript:Ex_Loader('modedialog',function() {new finderDialog('{$pUrl}',{params:{url:'index.php?app=console&ctl=admin_goodssync&act=handwork_allot',name:'product_id[]',postdata:'wms_id=$cur_wms_id'},onCallback:function(rs){MessageBox.success('分配成功');window.finderGroup['{$finder_id}'].refresh();}});});
JS
                 ),
                  array('label'=>'捆绑商品分配','onclick'=><<<JS
javascript:Ex_Loader('modedialog',function() {new finderDialog('{$pkgUrl}',{params:{url:'index.php?app=console&ctl=admin_goodssync&act=handwork_allot',name:'good_id[]',postdata:'wms_id=$cur_wms_id'},onCallback:function(rs){MessageBox.success('分配成功');window.finderGroup['{$finder_id}'].refresh();}});});
JS
                    ),
                  array('label'=>'组合关系同步','submit'=>'index.php?app=console&ctl=admin_goodssync&act=sync_combination&wms_id='.$_GET['wms_id'],'target'=>'dialog::{width:700,height:160,title:\'组合关系同步\'}'),
                 array(
                    'label' => '货品分配模板',
                     'href' => 'index.php?app=console&ctl=admin_goodssync&act=downloadTemplate',
                     'target' => "_blank",
                 ),
                array(
                    'label' => '货品分配导入',
                    'href' => 'index.php?app=console&ctl=admin_goodssync&act=importTemplate&wms_id='.$_GET['wms_id'],
                    'target' => "dialog::{width:400,height:170,title:'导入商品'}",
                ),
            ),
            'use_buildin_recycle'=>true,
            'use_buildin_selectrow'=>true,
            'use_bulidin_view'=>true,
            'use_buildin_filter'=>true,
        );
        
        if($_GET['wms_id'] != '0'){
            $params['base_filter'] = array('wms_id'=>$_GET['wms_id']);

            // 获取node_type
            $node_type = kernel::single('channel_func')->getWmsNodeTypeById($_GET['wms_id']);
            if (in_array(strtolower($node_type), array('qimen', 'hufu'))) {
                $params['actions'][0] = array(
                    'label'  => '批量同步',
                    'submit' => 'index.php?app=console&ctl=admin_goodssync&act=batchSyncDialog&p[0]='.$_GET['wms_id'],
                    'target' => 'dialog::{width:690,height:400,title:\'批量同步\'}',
                );
            }
            
            // 如果是保税，增加同步库存按钮
            if ($node_type == 'bim') {
                $params['actions'][] = array(
                    'label' => '同步仓储库存',
                    'submit'=>'index.php?app=console&ctl=admin_goodssync&act=sync_store&p[0]='.$_GET['wms_id'],
                    'target'=>'dialog::{width:700,height:250,title:\'同步仓储库存\'}'
                );
            }
            
            if ($node_type == 'yjdf') {
                $params['actions'] = array(
                    array(
                       'label' => '批量同步商品',
                       'href' => 'index.php?app=console&ctl=admin_goodssync&act=goodsGet&wms_id='.$_GET['wms_id'],
                       'target' => "dialog::{width:690,height:300,title:'批量同步商品'}",
                    ),
                    array(
                       'label' => '批量同步价格',
                       'submit' => 'index.php?app=console&ctl=admin_goodssync&act=syncPrice&wms_id='.$_GET['wms_id'],
                       'target' => "dialog::{width:690,height:300,title:'批量同步价格'}",
                    ),
                     array(
                        'label' => '货品分配模板',
                         'href' => 'index.php?app=console&ctl=admin_goodssync&act=downloadTemplate',
                         'target' => "_blank",
                     ),
                    array(
                        'label' => '货品分配导入',
                        'href' => 'index.php?app=console&ctl=admin_goodssync&act=importTemplate&wms_id='.$_GET['wms_id'],
                        'target' => "dialog::{width:400,height:170,title:'导入商品'}",
                    ),
                );
            }
        }
        
        //商品主数据
        $this->finder('console_mdl_foreign_sku',$params);
    }
    
    function _views($flag = 'true'){
        $wfsObj = app::get('console')->model('foreign_sku');
        $data = kernel::single('channel_func')->getWmsChannelList();
        $show_menu = array();
        foreach((array)$data as $c_k=>$c_v)
        {
            $result['label'] = $c_v['wms_name'];
            $result['optional'] = '';
            $result['filter'] = array('wms_id' => $c_v['wms_id']);
            $result['href'] =  $this->_views_href($c_k,$c_v['wms_id']);
            $result['addon'] = $wfsObj->count($result['filter']);
            $result['addon'] = $result['addon'] ?$result['addon'] :'_FILTER_POINT_';
            $result['show'] = 'true';
            $wms[] = $c_v['wms_id'];
            $show_menu[] = $result;
        }
        
        $count = count($show_menu);
        $show_menu[$count]['label'] = '全部';
        $show_menu[$count]['optional'] = '';
        $show_menu[$count]['filter'] = array('wms_id|in'=>$wms);
        $show_menu[$count]['href'] =  $this->_views_href($count,0);
        $show_menu[$count]['addon'] = $wfsObj->count($show_menu[$count]['filter']);
        $show_menu[$count]['addon'] =  $show_menu[$count]['addon'] ? $show_menu[$count]['addon'] :'_FILTER_POINT_';
        $show_menu[$count]['show'] = 'true';
        
        return $show_menu;
    }

    function _views_href($view,$wms_id)
    {
        $href = "index.php?app=console&ctl=admin_goodssync&act=".$_GET['act']."&view=".($view)."&wms_id=".($wms_id);
        return $href;
    }

    //商品同步
    function sync()
    {
        $basicMaterialSelect    = kernel::single('material_basic_select');

        if ($_POST['filter']) {
            parse_str($_POST['filter'],$filter);unset($_POST['filter']);
            $_POST = array_merge((array)$_POST, (array)$filter);
            $_REQUEST = array_merge((array)$_REQUEST, (array)$filter);
        }

        if(empty($_REQUEST['fsid']) && $_REQUEST['isSelectedAll'] != '_ALL_') return NULL;
        $wms_id = $_POST['wms_id'] ? $_POST['wms_id'] : $_GET['wms_id'];
        $view =  $_POST['view'] ? intval($_POST['view']) : intval($_GET['view']);
        
        $wfsObj = app::get('console')->model('foreign_sku');
        
        //全部选中处理
        if($_POST['isSelectedAll'] == '_ALL_'){
            $this->splash('error', $this->url.'&act=index&wms_id='.$wms_id.'&view='.$view, '该方法不支持全量，请使用batchSyncDialog， ajaxBatchSync');
        }else{
            $fsids = $_REQUEST['fsid'];
            $foreign_list = $wfsObj->getlist('inner_product_id',array('fsid'=>$fsids));
            $product_ids = array_map('current',$foreign_list);
        }

        if(count($product_ids) > 100){
            $this->splash('error', $this->url.'&act=index&wms_id='.$wms_id.'&view='.$view, '选取数量过多，请使用batchSyncDialog， ajaxBatchSync');
        }else{
            if ($product_ids){
                $product_sdf = array();
                $product_ids = (array)$product_ids;

                if ($_POST['inner_type']){
                    $goodsData = app::get('material')->model('sales_material')->getList('sm_id as product_id, sales_material_bn as bn, sales_material_name as name', array('sm_id'=>$product_ids));
                    foreach($goodsData as $val) {
                        $val['type'] = 'pkg';
                        $product_sdf[$val['goods_id']] = $val;
                    }
                }else{
                    $product_sdf = kernel::single('console_goodssync')->getProductSdf($product_ids);
                    $goodsData = $basicMaterialSelect->db->select("SELECT sm_id as product_id, sales_material_bn as bn, sales_material_name as name FROM sdb_console_foreign_sku as s  LEFT JOIN sdb_material_sales_material as m ON s.inner_product_id=m.sm_id WHERE s.inner_type='1' AND m.sm_id in (".implode(',',$product_ids).")");
                    if ($goodsData){
                        foreach($goodsData as $val) {
                            $val['type'] = 'pkg';
                            $product_sdf[] = $val;
                        }
                    }
                }
            }
            
            // 发起商品同步
           $wms_id = $_REQUEST['wms_id'];
           $branch_bn = $_POST['branch_bn'];

           kernel::single('console_goodssync')->syncProduct_notifydata($wms_id,$product_sdf,$branch_bn);
        }

        // $this->end(true,'操作成功');
        $this->splash('success', $this->url.'&act=index&wms_id='.$wms_id.'&view='.$view);
    }

    //分配商品
    function dispatch(){
        $wms_id = $_REQUEST['wms_id'];

        $view = $_REQUEST['view'];
        $finder_id = $_REQUEST['finder_id'];
        $page = $_REQUEST['page'] ? $_REQUEST['page'] : 1;
        $pagelimit = 50;
        $offset = ($page-1) * $pagelimit;
        if($_REQUEST['search']){
            $params['wms_id'] = $wms_id;
            $params['search_key'] = $_REQUEST['search_key'];
            if(empty($_REQUEST['search_value'])){
                $params['search_value'] = $_REQUEST['search_value_'.$_REQUEST['search_key']];
                $this->pagedata['search_value_key'] = $params['search_value'];
            }else{
                $params['search_value'] = $_REQUEST['search_value'];
                $this->pagedata['search_value'] = $params['search_value'];
            }
            $search_filter = kernel::single("console_goodssync")->get_filter($params,$offset,$pagelimit);
            if($search_filter === false){
                return false;
            }
            $data = kernel::single("console_goodssync")->get_goods_by_product_ids($search_filter);
            $count = kernel::single("console_goodssync")->get_goods_count_by_search($params);
            $link = 'index.php?app=console&ctl=admin_goodssync&act=dispatch&view='.$view.'&wms_id='.$params['wms_id'].'&search=true&search_value='.$params['search_value'].'&search_key='.$params['search_key'].'&target=container&page=%d';
            $this->pagedata['search_key'] = $params['search_key'];
            $this->pagedata['search_value_last'] = $params['search_value'];
        }else{
            $data = kernel::single("console_goodssync")->get_goods_by_wms($wms_id,$offset,$pagelimit);
            $count = kernel::single("console_goodssync")->get_goods_count_by_wms($wms_id);
            $count = $count[0]['count'];
            $link = 'index.php?app=console&ctl=admin_goodssync&act=dispatch&view='.$view.'&wms_id='.$wms_id.'&target=container&page=%d';
        }
        $total_page = ceil($count/$pagelimit);
        $pager = $this->ui()->pager(array(
            'current'=>$page,
            'total'=>$total_page,
            'link'=>$link,
        ));
        
        //获取自定义搜索选项
        $search = kernel::single("console_goodssync")->get_search_options();
        
        //获取自定义搜索项下拉列表
        $search_list = kernel::single("console_goodssync")->get_search_list();
        
        $this->pagedata['search'] = $search;
        $this->pagedata['count'] = $count;
        $this->pagedata['search_list'] = $search_list;
        $this->pagedata['rows'] = $data;
        $this->pagedata['pager'] = $pager;
        $this->pagedata['wms_id'] = $wms_id;
        $this->pagedata['finder_id'] = $finder_id;
        if($_GET['target'] || $_POST['search'] =='true'){
            return $this->display('admin/goodssync/index.html');
        }
        
        $this->singlepage('admin/goodssync/index.html');
    }

    function do_save(){
        $wms_id = $_POST['wms_id'];
        $product_ids = $_POST['product_id'];
        
        $db = kernel::database();
        $limit = 50;//设置多少个货品组一个sql语句
        $this->begin();
        
        //全选时候的处理
        if($_POST['select_all'] == 'true'){
            $search_key = $_POST['search_key'];
            $search_value = $_POST['search_value'];
            if(!empty($search_key) && !empty($search_value)){
                $data = kernel::single("console_goodssync")->get_data_by_search($search_key,$search_value,$wms_id);
            }else{
                $data = kernel::single("console_goodssync")->get_goods_by_wms($wms_id);
            }
        }else{
            $data = kernel::single("console_goodssync")->get_wms_goods($product_ids);
        }
        
        //标签为全部 表示所有wms都分配商品
        if($wms_id =='0'){
            $all = 'true';
            $wms = kernel::single('channel_func')->getWmsChannelList();
            $sdf = array();
            foreach($wms as $wms_id){
                $sdf[] = $this->get_foreign_sku_sdf($data,$wms_id['wms_id']);
            }
        }else{
            $sdf = $this->get_foreign_sku_sdf($data,$wms_id);
        }

        if($all == 'true'){
            foreach($sdf as $value){
                foreach($value as $v){
                    $sql_find = "select inner_product_id from sdb_console_foreign_sku where inner_sku = '".$v['inner_sku']."' and wms_id = '".$v['wms_id']."'";
                    $rs = $db->select($sql_find);
                    if(!$rs){
                        $update_value[] = "('".$v['inner_sku']."','".$v['inner_product_id']."','".$v['wms_id']."')";
                    }
                }
            }
        }else{
            foreach($sdf as $value){
                $update_value[] = "('".$value['inner_sku']."','".$value['inner_product_id']."','".$value['wms_id']."')";
            }
        }
        
        $count = ceil(count($update_value) / $limit);
        $update_value_tmp = array_chunk($update_value, $limit,true);
        
        for($i=0;$i<$count;$i++){
            //插入数据
            $update_sql = "insert into sdb_console_foreign_sku (`inner_sku`,`inner_product_id`,`wms_id`) values ".implode($update_value_tmp[$i], ',');
            $db->exec($update_sql);
        }
        
        $this->end(true,'操作成功');
    }

    function get_foreign_sku_sdf($data,$wms_id){
        $sdf = array();
        foreach($data as $v){
            $sdf[] = array(
                'inner_sku'=>$v['bn'],
                'inner_product_id'=>$v['product_id'],
                'wms_id'=>$wms_id,
            );
        }
        return $sdf;
    }

    function downloadTemplate(){
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=货品分配模板.csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $oObj = $this->app->model('foreign_sku');
        $title = $oObj->exportTemplate();
        
        echo '"'.implode('","',$title).'"';
    }

   /**
    * 追加导出模板内容
    *
    * @param Array $filter
    */
    public function importTemplate(){
       $wms_id = $_GET['wms_id'];
       if ($wms_id){
            $wms_info = array(
                'wms_name' => kernel::single('channel_func')->getChannelNameById($wms_id),
                'wms_id' => $wms_id
            );
        }else{
            $wms_info = array(
                'wms_name' => '全部',
                'wms_id' => '_ALL_'
            );
        }
       
        $this->pagedata['wms_name'] = $wms_info['wms_name'];
        $this->pagedata['wms_id'] = $wms_info['wms_id'];

        return $this->page('admin/goodssync/create_import.html');
    }

    //导入（默认当前wms，所有时，所有wms都插）
    public function import(){
        if( $_POST ){
            $this->begin();
            
            //所有wms 不包括自有仓储
            if($_POST['wms_id']=='0'){
                $wms = kernel::single('channel_func')->getWmsChannelList();
                $wms_id = array();
                foreach($wms as $v){
                    $wms_id[] = $v['wms_id'];
                }
            }else{
                $wms_id = (array)$_POST['wms_id'];
            }
            $files = $_FILES['upload_file'];

            if( $files['name'] == ''){
                $result['status'] = 'fail';
                $result['msg'] = '文件不能为空，请重新选择';
                $this->end(false,'上传失败!','',$result);
                exit;
            }

            $tmp = explode('.',$files['name']);
            $file_type = $tmp[(count($tmp)-1)];
            if( $file_type != 'csv' ){
                $result['status'] = 'fail';
                $result['msg'] = '文件类型错误，请重新选择';
                $this->end(false,'上传失败!','',$result);
                exit;
            }

            $_temp_file = iconv('UTF-8','gb2312',$files['tmp_name']);
            @chmod($_temp_file,0777);
            if(file_exists($_temp_file)){
                $result['status'] = 'success';
                $result['msg'] = '文件已成功上传，并进入导入队列';

                //生成本次导入任务
                $params = app::get('console')->model('foreign_sku')->import_params();
                $params['read_line'] = $params['read_line']>0?$params['read_line']:1000;
                $params['name'] = '导入任务('.$files['name'].')';
                $params['type'] = 'import';
                $params['filetype'] = 'csv';
                $params['file'] = $_temp_file;
                $params['app'] = 'console';
                $params['model'] = 'foreign_sku';
                $public = array(
                    'wms_id' => $wms_id,
                );
                $params['public'] = $public;

                $task = kernel::service('service.queue.ietask');
                $task->create($params);
                $this->end(true,'上传成功!','',$result);
                exit;
            }
        }
        
        $this->pagedata['wms_id'] = $_GET['_params']['wms_id'];
        $this->display('admin/goodssync/create_import.html');
    }

    /**
     * 奇门同步物料
     *
     * @return void
     * @author
     **/
    public function batchSyncDialog($wms_id)
    {
        // 根据WMS获取仓库
        $branchMdl = app::get('ome')->model('branch');

        $branchList = $branchMdl->getList('branch_bn,name',array(
            'wms_id'=>$wms_id, 
            'b_type'=>'1',
            'type' => 'main',
        ));

        $branchopts = array();
        foreach ($branchList as $key => $value) {
            $branchopts[$value['branch_bn']] = $value['name'];
        }
        $this->pagedata['branchopts'] = $branchopts;
        $_POST['wms_id'] = $wms_id;

        $this->pagedata['request_url'] = 'index.php?app=console&ctl=admin_goodssync&act=ajaxBatchSync';
        $this->pagedata['custom_html'] = $this->fetch('admin/goodssync/batch_dialog.html');

        parent::dialog_batch('console_mdl_foreign_sku');
    }

    public function ajaxBatchSync()
    {
        parse_str($_POST['primary_id'], $postdata);

        if (!$postdata) { echo 'Error: 请先选择仓储商品';exit;}

        $materialMdl = app::get('console')->model("foreign_sku");
        $materialMdl->filter_use_like = true;

        $products = $materialMdl->getList('*',$postdata['f'],$postdata['f']['offset'],$postdata['f']['limit']);
        if ($products){
            $product_ids = array();
            foreach ($products as $p){
                $product_ids[] = $p['inner_product_id'];
            }
        }

        $retArr = array(
            'itotal'  => count($product_ids),
            'isucc'   => count($product_ids),
            'ifail'   => 0,
            'err_msg' => array(),
        );

        $products_sdf = kernel::single('console_goodssync')->getProductSdf($product_ids);

        kernel::single('console_goodssync')->syncProduct_notifydata($postdata['f']['wms_id'],$products_sdf,$_POST['branch_bn']);
        echo json_encode($retArr),'ok.';exit;
    }
    
    public function handwork_allot()
    {
        $product_id = $_POST['product_id'];
        $sm_id = $_POST['good_id'];
        $wms_id     = $_POST['wms_id'];

        if (!$product_id && !$sm_id) $this->splash('error',null,'请先选择商品');
        if (!$wms_id) $this->splash('error',null,'请先选择仓储');
        $data = array();
        if($product_id) {
            $products = app::get('material')->model('basic_material')->getList('bm_id,material_bn,type',array('bm_id'=>$product_id));
            foreach ($products as $key => $value) {
                $data[] = array(
                    'inner_sku'        => $value['material_bn'],
                    'inner_product_id' => $value['bm_id'],
                    'wms_id'           => $wms_id,
                    'inner_type'       => $value['type']=='4' ? '2' : '0', 
                    'combinsync_status'=> $value['type']=='4' ? '0' : '3', 
                );
            }
        }
        
        if($sm_id) {
            $goods = app::get('material')->model("sales_material")->getList('sm_id,sales_material_name,sales_material_bn',array('sm_id'=>$sm_id));

            foreach ($goods as $key => $value) {
                $data[] = array(
                    'inner_sku'        => $value['sales_material_bn'],
                    'inner_product_id' => $value['sm_id'],
                    'inner_type'       => '1',
                    'wms_id'           => $wms_id,
                );
            }
        }

        if ($data) app::get('console')->model('foreign_sku')->replaceinsert($data);

        $this->splash();
    }

    public function sync_combination()
    {
        $filter = $_REQUEST;
        $filter['inner_type'] = array('1','2');

        $list = app::get('console')->model('foreign_sku')->getList('inner_product_id',$filter);
        $wms_id = $_GET['wms_id'];
        $this->pagedata['material_data'] = $list;
        $this->pagedata['branches'] = app::get('ome')->model('branch')->getList('*',array('wms_id'=>$wms_id));
        $this->display('admin/material/sync_combination.html');
    }

    public function doSyncCombination() {
        $branchId = (int) $_POST['branch_id'];
        $id = (int) $_POST['id'];

        $rs = kernel::single('console_event_trigger_goodssync')->syncCombination($id, $branchId);
        echo ($rs['rsp'] == 'succ' ? '' : '同步失败：' . ($rs['err_msg']?$rs['err_msg']:$rs['msg'])) . '|ok.';
        exit();
    }

    /**
     * 同步仓储库存进度条页
     *
     * @return void
     * @author 
     */
    public function sync_store($wms_id)
    {
        $_POST['wms_id'] = $wms_id;

        $this->pagedata['branches'] = app::get('ome')->model('branch')->getList('*',array('wms_id'=>$wms_id));

        $this->pagedata['request_url'] = 'index.php?app=console&ctl=admin_goodssync&act=ajaxSyncStore';
        $this->pagedata['custom_html'] = $this->fetch('admin/material/sync_store.html');

        parent::dialog_batch('wms_mdl_material');
    }

    /**
     * 同步仓储库存处理逻辑
     *
     * @return void
     * @author 
     **/
    public function ajaxSyncStore()
    {
        parse_str($_POST['primary_id'], $postdata);

        if (!$postdata) { echo 'Error: 请先选择仓储商品';exit;}

        $retArr = array(
            'itotal'  => 0,
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );

        $materialMdl = app::get('material')->model("basic_material");
        $materialMdl->filter_use_like = true;

        $list = $materialMdl->getList('*',$postdata['f'],$postdata['f']['offset'],$postdata['f']['limit']);

        $branch = app::get('ome')->model('branch')->dump($_POST['branch_id']);

        $object = kernel::single('erpapi_router_request')->set('wms',$postdata['f']['wms_id']);
        foreach ($list as $value) {
            $value['branch_info'] = $branch;

            $rs = $object->goods_syncStore($value);

            if ($rs['rsp'] == 'succ') {
                $retArr['isucc']++;
            } else {
                $retArr['ifail']++;
                $retArr['err_msg'][] = $rs['msg'];
            }
        }

        echo json_encode($retArr),'ok.';exit;
    }

    /**
     * 同步商品进度条页
     *
     * @return void
     * @author 
     */
    public function goodsGet()
    {
        $wmsId = (int)$_GET['wms_id'];

        $this->pagedata['branches'] = app::get('ome')->model('branch')->getList('*',array('wms_id'=>$wmsId));
        $this->pagedata['pre_time'] = strtotime('-1 month');
        $this->pagedata['now_time'] = time();
        $this->display('admin/material/goods_get.html');
    }

    /**
     * 同步商品处理逻辑
     *
     * @return void
     * @author 
     **/
    public function ajaxGoodsGet()
    {
        if (!$_POST['branchId']) { echo json_encode(array('err_msg'=>'请先选择仓库'));exit;}

        $retArr = array(
            'itotal'  => 0,
            'ifail'   => 0,
            'total'   => 0,
            'scrollId'   => '',
        );
        $data = [
            'scroll_id' => $_POST['scrollId'],
            'branch_id' => $_POST['branchId'],
            'start_time' => $_POST['startTime'],
            'end_time' => $_POST['endTime'],
        ];
        $rs = kernel::single('console_event_trigger_goodssync')->syncGet($data);
        if($rs['rsp'] == 'succ') {
            $retArr['itotal'] += ($rs['succ'] + $rs['fail']);
            $retArr['ifail'] += $rs['fail'];
            $retArr['total'] = $rs['data']['total'];
            $retArr['scrollId'] = $rs['data']['scrollId'];
        } else {
            $retArr = array('err_msg'=>$rs['msg']);
        }
        echo json_encode($retArr);exit;
    }
    /**
     * 同步价格进度条页
     *
     * @return void
     * @author 
     */
    public function syncPrice()
    {
        $_POST['wms_id'] = intval($_GET['wms_id']);

        $this->pagedata['branches'] = app::get('ome')->model('branch')->getList('*',array('wms_id'=>$_POST['wms_id']));
        $this->pagedata['request_url'] = 'index.php?app=console&ctl=admin_goodssync&act=ajaxSyncPrice';
        $this->pagedata['custom_html'] = $this->fetch('admin/material/sync_price.html');

        //调用desktop公用进度条(第4个参数是增量传offset,否则默认一直为0)
        parent::dialog_batch('console_mdl_foreign_sku', false, 10, 'incr');
    }

    /**
     * 同步价格处理逻辑
     *
     * @return void
     * @author 
     **/
    public function ajaxSyncPrice()
    {
        parse_str($_POST['primary_id'], $postdata);

        if (!$postdata) { echo 'Error: 请先选择仓储商品';exit;}

        $retArr = array(
            'itotal'  => 0,
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );

        $foreignMdl = app::get('console')->model("foreign_sku");
        $foreignMdl->filter_use_like = true;
        $list = $foreignMdl->getList('*',$postdata['f'],$postdata['f']['offset'],$postdata['f']['limit']);
        $retArr['itotal'] = count($list);
        $rs = kernel::single('console_event_trigger_goodssync')->syncPrice($list, $_POST['branch_id']);
        if($rs['rsp'] == 'succ') {
            $retArr['isucc'] += $rs['succ'];
            $retArr['err_msg'] = $rs['error_msg'];
        } else {
            $retArr['err_msg'][] = $rs['msg'];
        }
        $retArr['ifail'] = $retArr['itotal'] - $retArr['isucc'];

        echo json_encode($retArr),'ok.';exit;
    }

    public function findMaterial() {
        $this->view_source = 'dialog';

        //只能选择可售的物料
        $base_filter['visibled'] = 1;

        $base_filter['filter_sql'] = 'bm_id not in (select inner_product_id from sdb_console_foreign_sku where wms_id="'.intval($_GET['wms_id']).'")';

        $params = array(
            'title'                  => '基础物料列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'base_filter'            => $base_filter,
        );
        $this->finder('material_mdl_basic_material', $params);
    }
}
