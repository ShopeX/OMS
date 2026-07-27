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
 * 店铺商品信息调整controller
 *
 *
 * @author chenping<chenping@shopex.cn>
 */

class inventorydepth_ctl_shop_adjustment extends desktop_controller {

    var $workground = 'resource_center';
    var $defaultWorkground = 'resource_center';

    function __construct($app)
    {
        parent::__construct($app);
    }

    /**
     * 调整 列表
     *
     * @return void
     * @author
     **/
    public function index($source = '')
    {
        $base_filter = array();

        if($_POST['shop_id']) {
            $shop_id = $_POST['shop_id'];
        }elseif($_GET['shop_id']) {
            $shop_id = $_GET['shop_id'];
        } elseif($_GET['filter']['shop_id']) {
            $shop_id = $_GET['filter']['shop_id'];
        } elseif($_COOKIE['adj_shop_id']) {
            $shop_id = $_COOKIE['adj_shop_id'];
        }

        
        $shopdata = app::get('ome')->model('shop')->getList('shop_id,name', ['node_id|noequal'=>'','delivery_mode'=>'self']);
        if(empty($shop_id) && $shopdata) {
            $shop_id = $shopdata[0]['shop_id'];
        }
        if(empty($shop_id)) {
            $shop_id = 0;
        }
        
        $base_filter['shop_id'] = $shop_id;
        
        $shop = $this->app->model('shop')->getList('name', array('shop_id'=>$shop_id));
        $title = "<span style='color:#ff0000;'>" .$shop[0]['name']."</span>在售库存管理";
        if ($source != 'stock') {
            $back = '<a href="javascript:W.page(\'index.php?app=inventorydepth&ctl=shop&act=index\');" style=\'font-size: 12px;border-radius: 4px;background: #157FE3;color: #FFFFFF;font-weight: 400;margin-right: 10px;padding: 2px 6px;text-align: center;\'>返回列表</a>';
            $title = $back . $title;
        }
    
        $extra_view = array(
            'inventorydepth' => 'admin/show.html',
        );
        
        $this->pagedata['shopdata']= $shopdata;
        $this->pagedata['shop_id']= $shop_id;
        
        $params = array(
            'title' => $title,
            'actions' => array(
                0 => array('label'=>$this->app->_('批量开启回写库存'),'submit'=>'index.php?app=inventorydepth&ctl=shop_skus&act=set_request&p[0]=true','target'=>'refresh'),
                1 => array('label'=>$this->app->_('批量关闭回写库存'),'submit'=>'index.php?app=inventorydepth&ctl=shop_skus&act=set_request&p[0]=false','target'=>'refresh'),
                2 => array('label'=>$this->app->_('发布库存'),'submit'=>'index.php?app=inventorydepth&ctl=shop_adjustment&act=releasePage','target'=>'dialog::{title:\'批量发布\'}'),//弃用
                //3 => array('label'=>$this->app->_('导出发布库存模板'),'submit'=>'index.php?app=inventorydepth&ctl=shop_adjustment&act=export_data&view='.$_GET["view"].'&p[0]=release_stock','target'=>'_blank'),
                //4 => array('label'=>$this->app->_('导入发布库存'),'href'=>'index.php?app=inventorydepth&ctl=shop_adjustment&act=index&action=import','target' =>  'dialog::{width:400,height:150,title:\'导入发布库存\'}'),
                //6 => array('label'=>$this->app->_('批量发布'),'submit'=>'index.php?app=inventorydepth&ctl=shop_adjustment&act=displayBatchRelease','target'=>'dialog::{width:690,height:200,title:\'批量发布  (只处理已匹配SKU)\'}'),
            ),
            'use_buildin_recycle' => false,
            'use_buildin_filter' => true,
            'use_buildin_export' => true,
            'base_filter' => $base_filter,
            'top_extra_view' => $extra_view,
            'orderBy' => 'request asc',
            'object_method' => array(
                'count'=>'count',
                'getlist'=>'getFinderList',
            ),
        );

        if($_GET['view'] == 2){
            $params['actions'][5] = array('label' => '批量转换生成物料', 'submit'=>'index.php?app=inventorydepth&ctl=shop_adjustment&act=batchGenMaterial','target'=>'dialog::{width:690,height:200,title:\'批量恢复\'}');
        }
        if (kernel::single('desktop_user')->has_permission('shop_adjustment_delete')) {
            $params['actions'][6] = array(
                'label' => app::get('ome')->_('批量删除'),
                'submit' => 'index.php?app=inventorydepth&ctl=shop_adjustment&act=batch_delete&finder_id='.$_GET['finder_id'],
                'target' => "dialog::{width:500,height:200,title:'批量删除'}",
            );
        }

        $this->pagedata['benchobj']    = kernel::single('inventorydepth_stock')->get_benchmark();
        $this->pagedata['calculation'] = kernel::single('inventorydepth_math')->get_calculation();
        $this->pagedata['res_full_url'] = $this->app->res_full_url;

        $this->finder('inventorydepth_mdl_shop_adjustment',$params);
    }

    /**
     * 列表TAB页
     *
     * @return void
     * @author
     **/
    public function _views()
    {
        $lib_inventorydepth_shop_skus = kernel::single("inventorydepth_shop_skus");
        $views = $lib_inventorydepth_shop_skus->get_shop_adjustment_views_arr();
        $skusModel = $this->app->model('shop_adjustment');
        foreach ($views as $key=>&$view) {
            $view['filter']['shop_id'] = $_POST['shop_id'] ? $_POST['shop_id']: ($_GET['shop_id'] ?: $_SESSION['shop_id']);
            $view['addon'] = $skusModel->count($view['filter']);
            $view['href'] = 'index.php?app=inventorydepth&ctl=shop_adjustment&act=index&view='.$key.'&shop_id='.$view['filter']['shop_id'];
        }
        return $views;
    }


    /**
     * 保存公式
     *
     * @return void
     * @author
     **/
    public function saveFormula()
    {
        $this->begin();

        if ($_POST['heading'] == ''){
            $this->end(false,$this->app->_('编号或者中文标识不能为空'));
        }

        $formulaModel = $this->app->model('formula');

        $is_exist = $formulaModel->select()->columns('formula_id')
                    ->where('heading=?',$_POST['heading'])
                    ->instance()->fetch_row();
        if ($is_exist) {
            $this->end(false,$this->app->_('公式名已经存在！'));
        }

        $data['style'] = 'stock';
//       $data['bn'] = $_POST['bn'];
        $data['heading'] = $_POST['heading'];
        $data['content'] = array(
//            'benchmark'   => $_POST['benchmark'],
//            'calculation' => $_POST['calculation'],
//            'increment'   => $_POST['increment'],
            'result'      => $_POST['result']
        );
        $data['operator'] = $this->user->get_id();
        $data['operator_ip'] = kernel::single('base_component_request')->get_remote_ip();

        $result = $this->app->model('formula')->insert($data);

        $this->end($result);
    }

    /**
     * 执行公式 弃用
     *
     * @return void
     * @author
     **/
    public function execFormula()
    {
        $this->begin();

        if (!$_POST['id']) {
            $this->end(false,$this->app->_('请选择店铺商品'));
        }

        $result = $_POST['result'];unset($_POST['result']);
        if (!$result) {
            $this->end(false,$this->app->_('先填写公式'));
        }

        #   验证公式
        $errormsg = array();
        $rs = kernel::single('inventorydepth_stock')->formulaRun($result, array(), $errormsg);
        if ($rs === false) {
            $this->end(false, $errormsg);
        }

        # 执行公式
        kernel::single('inventorydepth_stock')->updateReleaseByformula($_POST,$result,$errormsg);

        $msg = $errormsg ? 'javascript:alert("修改失败：'.implode("\n", $errormsg).'");' : '修改成功';
        $result = $errormsg ? false : true;
        $this->end($result,$msg);
    }

    /**
     * 公式列表
     *
     * @return void
     * @author
     **/
    public function listFormula($pageno = 1)
    {
        $pagelimit = 10;

        $formulaModel = $this->app->model('formula');
        $PG['list'] = $formulaModel->getList('*',array('style'=>'stock'),($pageno-1)*$pagelimit,$pagelimit);
        $count = $formulaModel->count(array('style'=>'stock'));

        $total_page = ceil($count/$pagelimit);
        $pager = $this->ui()->pager(array(
            'current'=>$pageno,
            'total'=>$total_page,
            'link'=>'index.php?app=inventorydepth&ctl=shop_adjustment&act=listFormula&p[0]=%d',
        ));

        $this->pagedata = $PG;
        $this->pagedata['pager'] = $pager;
        $this->pagedata['count'] = $count;
        $this->pagedata['pagelimit'] = $pagelimit;
        $this->display('shop/adjustment/stock/list_formula.html');
    }

    /**
     * 删除公式
     *
     * @return void
     * @author
     **/
    public function delFormula()
    {
        $this->begin();
        if (!$_POST) {
            $this->end(false,$this->app->_('请选择公式'));
        }
        $result = $this->app->model('formula')->delete($_POST);
        $this->end($result);
    }

    /**
     * 更新发布库存
     *
     * @return void
     * @author
     **/
    public function update_release_stock()
    {
        $this->begin();
        if (!$_POST['id'] || !isset($_POST['release_stock'])) {
            $this->end(false,$this->app->_('参数错误!'));
        }
        if (!(is_numeric($_POST['release_stock']) && $_POST['release_stock']>=0)) {
            $this->end(false,'发布库存必须是非负数值型！');
        }

        $mdl    = $this->app->model('shop_adjustment');
        $filter = ['id'=>$_POST['id']];
        $info   = $mdl->db_dump($filter);
        $result = $mdl->update(array('release_stock'=>(int)$_POST['release_stock'],'release_status'=>'sleep'),$filter);

        $operInfo    = kernel::single('inventorydepth_func')->getDesktopUser();
        $optLogModel = app::get('inventorydepth')->model('operation_log');
        $optLogModel->write_log('sku',$_POST['id'],'edit','更新发布库存：'.$info['release_stock'].'->'.$_POST['release_stock'],$operInfo);


        $this->end($result);
    }

    /**
     * 发布页
     *
     * @return void
     * @author
     **/
    public function releasePage($id = null,$release_stock = null)
    {
        if ($_POST['isSelectedAll'] == '_ALL_') {
            echo '<div style="color:red;font-weight:bold;font-size:30px;">不支持全部货品发布！！！</div>';exit;
        } elseif ( $_POST['id'] ) {
            $way = 'batch';

        }elseif($id){
            $way = 'single';
            $_POST['id'] = $id;

            # 发布库存超出可售库存提示
            $sku = $this->app->model('shop_adjustment')->select()->columns('shop_product_bn,shop_id,shop_bn,release_stock,shop_sku_id,shop_iid')
                    ->where('id=?',$id)->instance()->fetch_row();
            
            $delivery_mode = app::get('ome')->model('shop')->db_dump(['shop_id'=>$sku['shop_id']], 'delivery_mode')['delivery_mode'];
            if($delivery_mode == 'shopyjdf') {
                $products = app::get('dealer')->model('sales_material')->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type,shop_id',array('sales_material_bn'=>$sku['shop_product_bn'], 'shop_id'=>$sku['shop_id']));
            } else {
                $products = app::get('material')->model('sales_material')->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type,shop_id',array('sales_material_bn'=>$sku['shop_product_bn']));
            }
            kernel::single('inventorydepth_calculation_basicmaterial')->init($products);
            kernel::single('inventorydepth_calculation_salesmaterial')->init($products);
            
            # 可售库存
            $stockCalLib = kernel::single('inventorydepth_calculation_salesmaterial');
            list($actual_stock, ) = $stockCalLib->get_actual_stock($sku);


            if ( is_numeric($release_stock) && $release_stock>=0 ) {
                $sku['release_stock'] = $release_stock;
            }

            $_POST['release_stock'] = $sku['release_stock'];

            $this->pagedata['warning'] = ($sku['release_stock'] > $actual_stock) ? true : false;
        }

        if ($_POST) {
            $post = http_build_query($_POST);
            $this->pagedata['post'] = $post;
        }

        # 发布库存覆盖店铺库存
        //$this->app->model('shop_adjustment')->convert_shop_stock($_POST);

        $this->pagedata['way'] = $way;
        $this->display('shop/adjustment/release/show.html');
    }

    /**
     * 单个发布
     *
     * @return void
     * @author
     **/
    public function singleRelease()
    {
        $this->begin();
        $id = $_POST['id'];

        if (!$id) {
            $this->end(false,$this->app->_('参数错误!'));
        }

        $sku = $this->app->model('shop_adjustment')
                    ->select()->columns('id,shop_id,release_stock,shop_product_bn,mapping,request,shop_type,shop_sku_id,shop_iid')
                    ->where('id=?',$id)
                    ->instance()->fetch_row();

        if(!$sku) $this->end(false,$this->app->_('货品不存在!'));

        // 获取商品条码
        // $barcode = kernel::single('material_codebase')->getBarcodeBySmbn($sku['shop_product_bn'], $sku['shop_id'], 1);

        // 唯品会单个商品发布库存，需要走队列的方式
        //@todo：唯品会按照区域仓的方式，进行回写库存;
        if(in_array($sku['shop_type'], ['vop'])){
            $res = [];
            
            // shop_id
            $shop_id = $sku['shop_id'];
            $shopIds = [$shop_id];
            
            // 获取销售物料
            $field = 'sm_id,sales_material_name,sales_material_bn,sales_material_type,shop_id,class_id';
            $filter = array('sales_material_bn'=>$sku['shop_product_bn']);
            $products = app::get('material')->model('sales_material')->getList($field, $filter, 0, -1);
            
            // request
            kernel::single('inventorydepth_logic_stock')->do_sync_products_stock($products, $shopIds);
            
            $this->end(true,$this->app->_('单个商品发布中'), null, $res);
        }
        
        // stock
        $memo = array('last_modified'=>time());
        $stocks[$sku['id']] = array(
            'bn' => $sku['shop_product_bn'],
            'quantity' => (is_numeric($_POST['release_stock']) && $_POST['release_stock']>=0) ? $_POST['release_stock'] : $sku['release_stock'],
            'memo' => json_encode($memo),
            'sku_id' => $sku['shop_sku_id'],
            'num_iid' => $sku['shop_iid'],
            'barcode' => $sku['shop_sku_id'],
            'shop_sku_id' => $sku['shop_sku_id'] ? $sku['shop_sku_id'] : $sku['shop_iid'],
        );

        // 查询增量库存
        if (kernel::single('inventorydepth_sync_set')->isModeSupportInc($sku['shop_type'])) {
            $stockLogMdl   = app::get('ome')->model('api_stock_log');
            $last_quantity = $stockLogMdl->getLastStockLog($sku['shop_id'], $sku['shop_product_bn']);
            if ($last_quantity) {
                $stocks[$sku['id']]['inc_quantity'] = $stocks[$sku['id']]['quantity'] - $last_quantity['store'];
            }
        }

        $res = kernel::single('inventorydepth_shop')->doStockRequest($stocks,$sku['shop_id'],true);

        // 记录操作日志
        $optLogModel = app::get('inventorydepth')->model('operation_log');
        $optLogModel->write_log('sku',$id,'stockup','单个发布库存：'.$stocks[$sku['id']]['quantity']);

        $this->end(true,$this->app->_('发布中'), null, $res);
    }

    /**
     * 批量发布
     *
     * @return void
     * @author
     **/
    public function batchRelease()
    {
        $this->begin();

        if (!$_POST) {
            $this->end(false,$this->app->_('请选择店铺商品!'));
        }

        $adjustmentModel = $this->app->model('shop_adjustment');

        /*
        $_POST['request'] = 'false';
        $row = $adjustmentModel->getList('id',$_POST,0,1);
        if ($row) {
            $this->end(false,$this->app->_('存在不允许回写的货品!'));
        }
        unset($_POST['request']);*/

        $_POST['mapping'] = '0';
        $row = $adjustmentModel->getList('id',$_POST,0,1);
        if ($row) {
            $this->end(false,$this->app->_('存在未匹配的货品!'));
        }
        unset($_POST['mapping']);

        $adjustmentModel->appendCols = '';
        $shops = $adjustmentModel->getList('distinct shop_id,shop_name',$_POST);

        foreach ($shops as $key => $shop) {
            $offset = 0; $limit = 50; $_POST['shop_id'] = $shop['shop_id'];

            $params = $_POST; $params['limit'] = $limit;

            // 操作员信息
            $params['operInfo'] = kernel::single('inventorydepth_func')->getDesktopUser();

            $count = $adjustmentModel->count($_POST);
            
            if($count<=0) continue;
            $title = "批量店铺【{$shop['shop_name']}】库存回写";

            $total = floor($count/$limit);
            for ($i=$total; $i>=0 ; $i--) {
                $params['offset'] = $i*$limit;

                # 插入队列
                kernel::single('inventorydepth_queue')->insert_release_queue($title,$params);
            }
        }

        $this->end(true,$this->app->_('成功插入队列!'));
    }

    /**
     * @description 更新店铺所有货品库存
     * @access public
     * @param void
     * @return void
     */
    public function uploadPage($shop_id)
    {
        if ( !$shop_id ) {
            $this->pagedata['error'] = '请先选择店铺！！！';
        } else {
            $shop = $this->app->model('shop')->select()->columns('*')->where('shop_id=?',$shop_id)->instance()->fetch_row();
            if ( !$shop ) {
                $this->pagedata['error'] = '店铺不存在！！！';
            } elseif (!$shop['node_id']) {
                $this->pagedata['error'] = '店铺【'.$shop['shop_bn'].'】未绑定';
            }
            
            //判断店铺当前是否有队列任务在执行
            $queue_title = '批量店铺【' . $shop['name'] . '】库存回写';
            $queueRow = app::get('base')->model('queue')->getList('queue_id,params', array('queue_title'=>$queue_title), 0, 1);
            if($queueRow){
                $this->pagedata['error'] = '该店铺【'.$shop['name'].'】有库存回写队列任务在执行，请稍后更新商品库存。';
            }
            
        }

        $this->pagedata['shop'] = $shop;

        $this->display('shop/adjustment/stock/upload.html');
    }

    /**
     * @description
     * @access public
     * @param void
     * @return void
     */
    public function batchUpload($shop_id)
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        
        set_time_limit(0);
        $this->begin();

        if ( !$shop_id ) {
            $this->end(false,'请先选择店铺！！！');
        }

        $shop = $this->app->model('shop')->select()->columns('*')->where('shop_id=?',$shop_id)->instance()->fetch_row();
        if ( !$shop ) {
            $this->end(false,'店铺不存在！！！');
        } elseif (!$shop['node_id']) {
            $this->end(false,'店铺【'.$shop['shop_bn'].'】未绑定');
        }
        $skutype = $_POST['skutype'];
        //skutype==2是仅店铺销售物料
        if ($skutype == '2') {
            $shopSkuMdl = app::get('inventorydepth')->model('shop_skus');
            $count      = $shopSkuMdl->count(array('shop_id' => $shop_id, 'mapping' => '1'));
        } else {
            $count = $salesMaterialObj->count(array('is_bind' => 1, 'shop_id' => array($shop_id, '_ALL_')));
        }
        if($count<=0) {
            $this->end(false,'无商品，请先在淘管中添加商品！！！');
        }

        $title = "批量店铺【{$shop['name']}】库存回写";
        $offset = 0; $limit = 50;  $total = floor($count/$limit);
        $params = array('shop_id'=>$shop_id,'limit'=>$limit, 'skutype' => $skutype);
        for ($i=$total; $i>=0 ; $i--) {
            $params['offset'] = $i*$limit;

            
            
            
            # 插入队列
            kernel::single('inventorydepth_queue')->insert_stock_update_queue($title,$params);
        }

        // 记录操作日志
        $optLogModel = $this->app->model('operation_log');
        $optLogModel->write_log('shop',$shop_id,'stockup','批量全局库存回写');

        $this->end(true,$this->app->_('成功插入队列!'));
    }

    public function exportTemplate(){

        $filename = "店铺商品分配模板".date('Y-m-d').".csv";
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
        $pObj = $this->app->model('shop_adjustment');
        $title = $pObj->exportTemplate('title');
        echo '"'.implode('","',$title).'"';
    }

    /**
     * 读取发布后的状态
     *
     * @return void
     * @author
     **/
    public function getResult()
    {
        $id = $_POST['id'];
        if(!$id) $this->splash('error',null,$this->app->_('数据为空!'));

        $adjustmentModel = $this->app->model('shop_adjustment');

        $release_status = $adjustmentModel->select()->columns('release_status')
                            ->where('id=?',$id)->instance()->fetch_one();
        if ($release_status == 'success') {
            $this->splash('finish',null,$this->app->_('发布成功'));
        }elseif($release_status == 'fail'){
            $this->splash('finish',null,$this->app->_('发布失败'));
        }else{
            $this->splash('running',null,$this->app->_('运行中'));
        }
    }

    /**
     * @description 获取货品的应用规则
     * @access public
     * @param String $id
     * @return void
     */
    public function getApplyRegu($id)
    {
        if(!$id){ echo '';exit;}
        $sku = $this->app->model('shop_adjustment')->getList('shop_product_bn,bind,shop_id,shop_bn',array('id'=>$id),0,1);
        if(!$sku){ echo ''; exit;}
        $sku = $sku[0];

        if ($sku['bind'] == '1') { //促销
            $rr = [];
        }elseif($sku['bind'] == '2'){ //多选一
            $rr = [];
        }else{ //普通
            $rr = kernel::single('inventorydepth_logic_stock')->getExecRegu($sku['shop_product_bn'],$sku['shop_id'],$sku['shop_bn']);
        }

        echo <<<EOF
        <a href="index.php?app=inventorydepth&ctl=regulation&act=dialogEdit&p[0]={$rr['regulation_id']}&finder_id={$_GET['_finder']['finder_id']}&regulation_readonly=true" target="dialog::{title:'修改规则'}">{$rr['heading']}</a>
EOF;
    }

    /**
     * @description 获取前端店铺库存
     * @access public
     * @param void
     * @return void
     */
    public function getShopStock()
    {
        $iids = $_POST['iid'];$shop_id = $_POST['shop_id']; $shop_bn = $_POST['shop_bn'];$shop_type=$_POST['shop_type'];
        if( !$iids || !$shop_id || !$shop_bn) {
            $result = array('status'=>'fail','msg'=>'参数为空!');
            echo json_encode($result);exit;
        }
        
        $shop = $this->app->model('shop')->dump(array('shop_id'=>$shop_id));
        $shopfactory = inventorydepth_service_shop_factory::createFactory($shop['shop_type'],$shop['business_type']);
        if ($shopfactory === false) {
            $result = array('status'=>'fail','msg'=>'店铺类型有误！');
            echo json_encode($result);exit;
        }

        $result = $shopfactory->downloadByIIds($iids,$shop_id,$errormsg);
        if (empty($result)) {
            $result = array('status'=>'fail','msg'=>$errormsg);
            echo json_encode($result);exit;
        }

        foreach ($result as $r) {
            if ($r['skus']) {
                foreach ($r['skus']['sku'] as $sku) {
                    $items[] = array(
                        'iid' => strval($r['iid']),
                        'sku_id' => $sku['sku_id'],
                        'num' => $sku['quantity'],
                        'id' => md5($shop_id.$r['iid'].$sku['sku_id']),
                    );
                }
            } else {
                $items[] = array(
                    'iid' => strval($r['iid']),
                    'num' => $r['num'],
                    'id' => md5($shop_id.$r['iid']),
                );
            }
        }

        $result = array('status'=>'succ','data'=>$items);
        echo json_encode($result);exit;
    }

    /**
     * @description 获取发布库存
     * @access public
     * @param void
     * @return void
     */
    public function getReleaseStock()
    {
        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesMLib = kernel::single('material_sales_material');
        
        $ids = $_POST['ids'];$shop_id = $_POST['shop_id']; $shop_bn = $_POST['shop_bn'];
        if( !$ids || !$shop_id || !$shop_bn) {
            $result = array('status'=>'fail','msg'=>'参数为空!');
            echo json_encode($result);exit;
        }

        $adjustmentModel = $this->app->model('shop_adjustment');
        $skus = $adjustmentModel->getList('shop_product_bn,bind,shop_id,shop_bn,id,mapping,shop_sku_id,shop_iid',array('id'=>$ids));
        $pbns = [];
        foreach ($skus as $sku) {
            $pbns[] = $sku['shop_product_bn'];
        }
        
        //[普通]销售物料
        $delivery_mode = app::get('ome')->model('shop')->db_dump(['shop_id'=>$shop_id], 'delivery_mode')['delivery_mode'];
        if($delivery_mode == 'shopyjdf') {
            $products = app::get('dealer')->model('sales_material')->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type,shop_id',array('sales_material_bn'=>$pbns, 'shop_id'=>$shop_id));
        } else {
            $products = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type,shop_id,class_id',array('sales_material_bn'=>$pbns));

            $products = array_column($products, null, 'sales_material_bn');
        }
        
        if(!$products){
            $result = array('status'=>'fail','msg'=>'无关联货号!');
            echo json_encode($result);exit;
        }

        kernel::single('inventorydepth_calculation_basicmaterial')->init($products);
        kernel::single('inventorydepth_calculation_salesmaterial')->init($products);

        $data = array();
        foreach ($skus as $sku) {
            $product = $products[$sku['shop_product_bn']];

            $product['shop_sku_id'] = $sku['shop_sku_id'] ? $sku['shop_sku_id'] : $sku['shop_iid'];

            // 发布库存
            $stock = kernel::single('inventorydepth_logic_stock')->getStock($product,$sku['shop_id'],$sku['shop_bn']);

            $quantity     = $stock['quantity'];
            $actual_stock = $stock['actual_stock'];
            $asRs         = $stock['regulation']['detail']['可售库存']['info'];

            if ($actual_stock === false) continue;
            // 详情
            $actual_product_stock = array();
            foreach ($asRs['basic'] as $bn => $bcRs) {
                $actual_product_stock[] = array(
                    'bn'=>$bn,
                    'stock'=>$bcRs['quantity'],
                    'detail'=>json_encode($bcRs['info'], JSON_UNESCAPED_UNICODE),
                );
            }

            if ($quantity !== false) {
                $adjustmentModel->update(array('release_stock'=>$quantity),array('id'=>$sku['id']));
            }

            if ($sku['mapping'] =='1') {
                $reguhtml = <<<EOF
                <a href="index.php?app=inventorydepth&ctl=regulation&act=dialogEdit&p[0]={$stock['regulation']['规则ID']}&regulation_readonly=true" target="dialog::{title:'修改规则'}">{$stock['regulation']['规则名称']}</a>
EOF;
            } else {
                $reguhtml = '-';
            }

            $data[] = array(
                'id' => $sku['id'],
                'quantity' => $quantity,
                'actual_stock' => $sku['mapping']=='1' ? $actual_stock : '-',
                'actual_product_stock'=>$actual_product_stock,
                'reguhtml' => $reguhtml,
            );
        }

        $result = array('status'=>'succ','data'=>$data);
        echo json_encode($result);exit;
    }

    function trans(){
        $sku_id = $_GET['sku_id'];
        $shop_id = $_GET['shop_id'];

        $sku_info = $this->app->model('shop_skus')->dump(array('shop_sku_id'=>$sku_id,'shop_id'=>$shop_id),'*');
        
        #默认基础物料条码
        $sku_info['shop_barcode']    = ($sku_info['shop_barcode'] ? $sku_info['shop_barcode'] : $sku_info['shop_product_bn']);
        
        $this->pagedata['shop_sku_info'] = $sku_info;

        $shopObj = app::get('ome')->model('shop');
        $shopList = $shopObj->getList('shop_id,name', array(), 0, -1);
        array_unshift($shopList,array('shop_id'=>'_ALL_','name'=>'全部店铺'));
        $this->pagedata['shops'] = $shopList;

        #获取物料品牌
        $brand_obj = app::get('ome')->model('brand');
        $brands = $brand_obj->getList('brand_id,brand_name', array());
        $this->pagedata['material_brand']       =  $brands;
        
        #获取物料分类
        $goods_type_obj = app::get('ome')->model('goods_type');
        $goods_types = $goods_type_obj->getList('type_id,name', array());
        $this->pagedata['goods_types'] = $goods_types;
        
        $this->display('shop/adjustment/trans_material.html');
    }

    /**
     * 基础物料新增提交方法
     * 
     * @param Post
     * @return Boolean
     */
    function toAdd($single=true){
        if($single){
            $this->begin('index.php?app=inventorydepth&ctl=shop_adjustment&act=index&filter[shop_id]='.$_POST['shop_id']);
        }

        //根据类型判断如果是自动的就自动生成基础物料，失败则提示
        if($_POST['sales_material_type'] != 2 && $_POST['sales_material_type'] != 5 && $_POST['gen_mode'] == 1){
            if(!($bm_id = $this->autoGenBasicMaterial($_POST, $err_msg))){
                if($single){
                    $this->end(false, $err_msg);
                }else{
                    return false;
                }
            }else{
                $_POST['bm_id'] = $bm_id;
            }
        }

        if(!$this->checkAddParams($_POST, $err_msg)){
            if($single){
                $this->end(false, $err_msg);
            }else{
                return false;
            }
        }

        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');
        $salesMaterialShopFreezeObj = app::get('material')->model('sales_material_shop_freeze');

        //保存物料主表信息
        $addData = array(
            'sales_material_name' => $_POST['sales_material_name'],
            'sales_material_bn' => $_POST['sales_material_bn'],
            'sales_material_bn_crc32' => $_POST['sales_material_bn_crc32'],
            'sales_material_type' => $_POST['sales_material_type'],
            'shop_id' => $_POST['shop_id'],
            'create_time' => time(),
        );
        $is_save = $salesMaterialObj->save($addData);

        if($is_save){
            $is_bind = false;
            //如果有关联物料就做绑定操作
            $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
            //普通销售物料关联
            if(($_POST['sales_material_type'] == 1 || $_POST['sales_material_type'] == 3) && !empty($_POST['bm_id'])){
                $addBindData = array(
                        'sm_id' => $addData['sm_id'],
                        'bm_id' => $_POST['bm_id'],
                        'number' => 1,
                );
                $salesBasicMaterialObj->insert($addBindData);

                $is_bind = true;
            }elseif($_POST['sales_material_type'] == 2 && !empty($_POST['at'])){
            //促销销售物料关联
                foreach($_POST['at'] as $k=>$v){
                    $addBindData = array(
                        'sm_id' => $addData['sm_id'],
                        'bm_id' => $k,
                        'number' => $v,
                        'rate' => $_POST['pr'][$k],
                    );
                    $salesBasicMaterialObj->insert($addBindData);
                    $addBindData = null;
                }

                $is_bind = true;
            }elseif($_POST['sales_material_type'] == 5 && !empty($_POST['sort'])){ //多选一
                $mdl_ma_pickone_ru = app::get('material')->model('pickone_rules');
                $select_type = $_POST["pickone_select_type"] ? $_POST["pickone_select_type"] : 1; //默认“随机”
                foreach($_POST['sort'] as $key_bm_id => $val_sort){
                    $current_insert_arr = array(
                        "sm_id" => $addData['sm_id'],
                        "bm_id" => $key_bm_id,
                        "sort" => $val_sort ? $val_sort: 0,
                        "select_type" => $select_type,
                    );
                    $mdl_ma_pickone_ru->insert($current_insert_arr);
                }
                $is_bind = true;
            }

            //如果有绑定物料数据，设定销售物料为绑定状态
            if($is_bind){
                $salesMaterialObj->update(array('is_bind'=>1),array('sm_id'=>$addData['sm_id']));
            }

            //保存销售物料扩展信息
            $addExtData = array(
                'sm_id' => $addData['sm_id'],
                'cost' => $_POST['cost'] ? $_POST['cost'] : 0.00,
                'retail_price' => $_POST['retail_price'] ? $_POST['retail_price'] : 0.00,
                'weight' => $_POST['weight'] ? $_POST['weight'] : 0.00,
                'unit' => $_POST['unit'],
            );
            $salesMaterialExtObj->insert($addExtData);
            
            //保存销售物料店铺级冻结
            if($_POST['shop_id'] != '_ALL_'){
                $addStockData = array(
                    'sm_id' => $addData['sm_id'],
                    'shop_id' => $_POST['shop_id'],
                    'shop_freeze' => 0,
                );
                $salesMaterialShopFreezeObj->insert($addStockData);
            }

            //更新当前店铺商品与erp本地关联
            if($_POST['sales_material_type'] == 2){ //捆绑
                $bind = 1;
            }elseif($_POST['sales_material_type'] == 5){ //多选一
                $bind = 2;
            }else{
                $bind = 0;
            }
            $this->app->model('shop_skus')->update(array('mapping'=>'1', 'bind'=>$bind),array('shop_sku_id'=>$_POST['shop_sku_id'],'shop_id'=>$_POST['shop_id']));

            if($single){
                $this->end(true, '操作成功');
            }else{
                return true;
            }
        }else{
            if($single){
                $this->end(false, '保存失败');
            }else{
                return false;
            }
        }

    }

    /**
     * 销售物料新增时的参数检查方法
     * 
     * @param Array $params 
     * @param String $err_msg
     * @return Boolean
     */
    function checkAddParams(&$params, &$err_msg){
        if(empty($params['sales_material_name']) || empty($params['sales_material_bn'])){
            $err_msg ="必填信息不能为空";
            return false;
        }

        $salesMaterialObj = app::get('material')->model('sales_material');
        $salesMaterialInfo = $salesMaterialObj->getList('sales_material_bn',array('sales_material_bn'=>$params['sales_material_bn']));
        if($salesMaterialInfo){
            $err_msg ="当前新增的物料编码已被使用，不能重复";
            return false;
        }

        $params['sales_material_bn_crc32'] = sprintf('%u',crc32($params['sales_material_bn']));

        if($params['sales_material_type'] == 2){
            if(!isset($params['at'])){
                $err_msg ="促销物料请至少设置一个物料明细内容";
                return false;
            }

            foreach ($params['at'] as $val){
                if (count($params['at']) == 1){
                    if ($val <2){
                        $err_msg ="只有一种物料时，数量必须大于1";
                        return false;
                    }
                }else {
                    if ($val < 1){
                        $err_msg ="数量必须大于0";
                        return false;
                    }
                }
            }

            foreach ($params['pr'] as $val){
                $tmp_rate +=$val;
            }

            if($tmp_rate > 100){
                $err_msg ="分摊销售价合计百分比:".$tmp_rate.",已超100%";
                return false;
            }elseif($tmp_rate < 100){
                $err_msg ="分摊销售价合计百分比:".$tmp_rate.",不足100%";
                return false;
            }
        }
        
        //多选一
        if($params['sales_material_type'] == 5){
            if(!$params['pickone_select_type']){
                $err_msg = "缺少多选一的选择方式";
                return false;
            }
            if(!isset($params['sort']) || count($params['sort']) < 2){
                $err_msg ="多选一物料请至少设置二个基础物料明细内容";
                return false;
            }
            $reg_number = "/^[1-9][0-9]*$/"; //正整数
            foreach($params['sort'] as $key_bm_id => $var_w){
                if($var_w){
                    if(!preg_match($reg_number,$var_w)){
                        $err_msg = "权重必须是数值";
                        return false;
                    }
                }else{ //0和空 数据库默认给0
                }
            }
        }
        
        return true;
    }

    function autoGenBasicMaterial($params, &$err_msg){
        
        $_POST    = $params;
        
        $_POST['material_name'] = $params['sales_material_name'];
        $_POST['material_bn'] = $params['sales_material_bn'];
        $_POST['material_code'] = (trim($params['material_code']) ? $params['material_code'] : $params['sales_material_bn']);#基础物料条码必填项
        $_POST['retail_price'] = floatval($params['retail_price']);
        $_POST['serial_number'] = $params['serial_number'];#基础物料唯一码
        $_POST['type'] = 1;
        $_POST['visibled'] = 1;
        
        $_POST['specifications'] = $params['material_specification'];#物料规格
        
        $_POST['cost'] = floatval($params['cost']);#成本 价
        $_POST['weight'] = intval($params['weight']);#重量
        
        $_POST['cat_id'] = intval($params['goods_type']);#类型
        $_POST['brand'] = intval($params['brand_id']);#品牌

        if(!$this->checkAddBasicMParams($_POST, $err_msg)){
            return false;
        }

        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $basicMaterialFeatureGrpObj = app::get('material')->model('basic_material_feature_group');
        $basicMaterialStockObj = app::get('material')->model('basic_material_stock');
        $basicMaterialConfObj = app::get('material')->model('basic_material_conf');
        $barocdeObj = app::get('material')->model('barcode');
        
        #检验保质期监控配置
        $use_expire    = intval($_POST['use_expire']);
        $warn_day      = intval($_POST['warn_day']);
        $quit_day      = intval($_POST['quit_day']);
        if($use_expire == 1 && ($warn_day <= $quit_day))
        {
            $this->end(false, '预警天数必须大于自动退出库存天数');
        }

        //保存物料主表信息
        $addData = array(
            'material_name' => $_POST['material_name'],
            'material_bn' => $_POST['material_bn'],
            'material_bn_crc32' => $_POST['material_bn_crc32'],
            'type' => $_POST['type'],
            'serial_number' => $_POST['serial_number'],
            'visibled' => $_POST['visibled'],
            'create_time' => time(),
        );
        $is_save = $basicMaterialObj->save($addData);

        if($is_save){

            //保存条码信息
            $sdf = array(
                'bm_id' => $addData['bm_id'],
                'type' => material_codebase::getBarcodeType(),
                'code' => $_POST['material_code'],
            );
            $barocdeObj->insert($sdf);

            //保存保质期配置
            $useExpireConfData = array(
                'bm_id' => $addData['bm_id'],
                'use_expire' => $_POST['use_expire'] == 1 ? 1 : 2,
                'warn_day' => $_POST['warn_day'] ?  $_POST['warn_day'] : 0,
                'quit_day' => $_POST['quit_day'] ? $_POST['quit_day'] : 0,
                'create_time' => time(),
            );
            $basicMaterialConfObj->save($useExpireConfData);

            //如果关联半成品数据
            if($addData['type'] == 1){
                $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
                if(isset($_POST['at'])){
                    foreach($_POST['at'] as $k=>$v){
                        $tmpChildMaterialInfo = $basicMaterialObj->dump($k, 'material_name,material_bn');

                        $addCombinationData = array(
                            'pbm_id' => $addData['bm_id'],
                            'bm_id' => $k,
                            'material_num' => $v,
                            'material_name' => $tmpChildMaterialInfo['material_name'],
                            'material_bn' => $tmpChildMaterialInfo['material_bn'],
                            'material_bn_crc32' => sprintf('%u',crc32($tmpChildMaterialInfo['material_bn'])),
                        );
                        $basicMaterialCombinationItemsObj->insert($addCombinationData);
                        $addCombinationData = null;
                    }
                }
            }

            //保存基础物料的关联的特性
            if($_POST['ftgp_id']){
                $addBindFeatureData = array(
                    'bm_id' => $addData['bm_id'],
                    'feature_group_id' => $_POST['ftgp_id'],
                );
                $basicMaterialFeatureGrpObj->insert($addBindFeatureData);
                $addBindFeatureData = null;
            }

            //保存物料扩展信息
            $addExtData = array(
                'bm_id' => $addData['bm_id'],
                'cost' => $_POST['cost'] ? $_POST['cost'] : 0.00,
                'retail_price' => $_POST['retail_price'] ? $_POST['retail_price'] : 0.00,
                'weight' => $_POST['weight'] ? $_POST['weight'] : 0.00,
                'unit' => $_POST['unit'],
                'specifications' => $_POST['specifications'],
                'brand_id' => $_POST['brand_id'],
                'cat_id' => $_POST['cat_id'],
            );
            $basicMaterialExtObj->insert($addExtData);
            
            //保存物料库存信息
            // * redis库存高可用，废弃掉直接修改db库存、冻结的方法
            $addStockData = array(
                'bm_id' => $addData['bm_id'],
                // 'store' => $_POST['store'] ? $_POST['store'] : 0,
                // 'store_freeze' => $_POST['store_freeze'] ? $_POST['store_freeze'] : 0,
                'store' => 0,
                'store_freeze' => 0,
            );
            $basicMaterialStockObj->insert($addStockData);

            return $addData['bm_id'];
        }else{
            $err_msg = '保存失败';
            return false;
        }

    }

    function checkAddBasicMParams(&$params, &$err_msg){

        //检查物料名称
        if(empty($params['material_name']) || empty($params['material_bn'])){
            $err_msg ="必填信息不能为空";
            return false;
        }
        //检查物料编号
        $basicMaterialObj = app::get('material')->model('basic_material');
        $basicMaterialInfo = $basicMaterialObj->getList('material_bn',array('material_bn'=>$params['material_bn']));
        if($basicMaterialInfo){
            $err_msg ="当前新增的物料编码已存在，不能重复创建";
            return false;
        }
        //检查物料条码
        $barcode = app::get('material')->model('barcode')->getList('bm_id',array('code'=>$params['material_code'], 'type' => material_codebase::getBarcodeType()));
        if($basicMaterialInfo){
            $err_msg ="当前新增的物料条码已被使用，不能重复使用";
            return false;
        }

        $params['material_bn_crc32'] = sprintf('%u',crc32($params['material_bn']));

        if($params['type'] == 1){
            if(isset($params['at'])){
                foreach ($params['at'] as $val){
                    if ($val < 1){
                        $err_msg ="数量必须大于0";
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function batchGenMaterial()
    {
        $sub_menu = $this->_views();
        foreach ($sub_menu as $key => $value) {
            if($key == 2){
                $base_filter = $value['filter'];
            }
        }
        
        $filter = array_merge((array)$_POST,(array)$base_filter);

        $skuObj = app::get('inventorydepth')->model('shop_adjustment');
        $skuObj->filter_use_like = true;
        $count = $skuObj->count($filter);

        $this->pagedata['total'] = $count;
        $this->pagedata['filter'] = http_build_query($filter);
        $this->display('shop/adjustment/batch_trans_material.html');
    }

    public function dobatchGenMaterial(){
        $page_no = intval($_GET['page_no']) ? intval($_GET['page_no']) : 1;
        $page_size = 10;
        $offset = 0;
        $total = intval($_GET['total']);
        parse_str($_POST['filter'],$filter);

        $skuObj = app::get('inventorydepth')->model('shop_adjustment');
        $skuObj->filter_use_like = true;
        $skuList = $skuObj->getList('*',$filter,$offset,$page_size);
        $succ_num = $fail_num = 0;
        if ($skuList) {
            foreach ((array) $skuList as $sku_info) {
                unset($_POST);
                $_POST['sales_material_name'] = $sku_info['shop_title'];
                $_POST['sales_material_bn'] = $sku_info['shop_product_bn'];
                $_POST['sales_material_type'] = 1;
                $_POST['shop_id'] = $sku_info['shop_id'];
                $_POST['gen_mode'] = 1;
                $_POST['retail_price'] = $sku_info['shop_price'];
                $_POST['shop_sku_id'] = $sku_info['shop_sku_id'];

                $rs = $this->toAdd(false);
                if ($rs) $succ_num++; else $fail_num++;
            }
        }

        $result = array('status'=>'running','data'=>array('succ_num'=>$succ_num,'fail_num'=>$fail_num));

        if ( ($page_size * $page_no) >= $total) {
            $result['status'] = 'complete';
            $result['data']['rate'] = '100';
        } else {
            $result['data']['rate'] =  $page_no * $page_size / $total * 100;
        }

        echo json_encode($result);exit;
    }
    
    //导出数据
    public function export_data($type){
        @ini_set('memory_limit','64M');
        $lib_inventorydepth_shop_skus = kernel::single("inventorydepth_shop_skus");
        $filter_arr = array();
        if(!empty($_POST['id'])){ //勾选的项
            $filter_arr['id'] = $_POST['id'];
        }
        if($_POST["isSelectedAll"] == "_ALL_" && empty($_POST['id'])){ //全选
            $views = $lib_inventorydepth_shop_skus->get_shop_adjustment_views_arr();
            if(intval($_GET["view"])>0){
                $filter_arr = $views[$_GET["view"]]["filter"];
            }
            $filter_arr['shop_id'] = $_SESSION['shop_id'];
        }
        $mdl_ome_shop = app::get('ome')->model('shop');
        $rs_ome_shop = $mdl_ome_shop->dump(array("shop_id"=>$_SESSION['shop_id']),"name");
        switch($type){
            case "release_stock":
                $part_shop_name = '发布库存模版'.date('Ymd');
                $content_arr = $lib_inventorydepth_shop_skus->get_release_stock_export_content($filter_arr);
                break;
        }
        $filename = $rs_ome_shop["name"].$part_shop_name;
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=".$filename.".csv");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        $title_arr = $lib_inventorydepth_shop_skus->get_title_arr($type);
        echo '"'.implode('","',$title_arr).'"';
        echo "\n";
        if(!empty($content_arr)){
            foreach($content_arr as $var_ca){
                echo '"'.implode('","',$var_ca).'"';echo "\n";
            }
        }
    }

    public function displayAssignUpload()
    {
        // $shopList = app::get('ome')->model('shop')->getList('shop_id,name', [
        //     's_type'        => '1',
        //     'filter_sql'    => 'node_id is not null and node_id !=""',
        // ]);

        // $this->pagedata['shopList'] = $shopList;

        $this->display('shop/adjustment/assign_upload.html');
    }

    public function ajaxAssignUpload($shop_id)
    {
        $this->begin();

        $bn = $_POST['bn'];

        $bn = array_filter(array_unique(explode("\n", $bn)));

        if (!$bn){
            $this->end(false, '请填写货号');
        }

        if (!$shop_id) {
            $this->end(false, '请选择店铺');
        }

        // 判断店铺
        $shop = app::get('ome')->model('shop')->dump($shop_id);

        if ($shop['s_type'] != '1'){
            $this->end(false, '非线上店铺不能同步');
        }

        if (!$shop['node_id']) {
            $this->end(false, '未绑定店铺不能同步');
        }

        $list = app::get('material')->model('sales_material')->getList('sales_material_bn', ['sales_material_bn' => $bn]);
        if (!$list) {
            $this->end(false, '销售物料不存在');
        }

        foreach ($list as $v) {
            $params = [
                    'sales_material_bn' => trim($v['sales_material_bn']),
                    'offset'            => '0',
                    'limit'             => '10',
                    'shop_id'           => $shop_id,
            ];

            kernel::single('inventorydepth_queue')->insert_stock_update_queue($v['sales_material_bn'],$params);
        }

        $this->end(true);
    }

    public function showActualStock() {
        $id = $_GET['id'];
        $sku = app::get('inventorydepth')->model('shop_adjustment')->db_dump(['id'=>$id], 'shop_product_bn,shop_id,shop_bn,shop_type');
        # [普通]销售物料
        $products = app::get('material')->model('sales_material')->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type,shop_id,class_id',array('sales_material_bn'=>$sku['shop_product_bn']));
        if(!$products){
            echo '无关联货号!';exit;
        }

        kernel::single('inventorydepth_calculation_basicmaterial')->init($products);
        kernel::single('inventorydepth_calculation_salesmaterial')->init($products);

        $stock = kernel::single('inventorydepth_logic_stock')->getStock($products[0],$sku['shop_id'],$sku['shop_bn']);
        $quantity = $stock['quantity'];
        $actual_stock = $stock['actual_stock'];
        $asRs =  $stock['regulation']['detail']['可售库存']['info'];

        if($actual_stock === false) {
            echo '获取可售库存失败!';exit;
        }
        
        //format福袋组合信息
        if(isset($asRs['luckybag'])){
            $combineMdl = app::get('material')->model('fukubukuro_combine');
            
            //combine_bn
            $combineBns = array_keys($asRs['luckybag']);
            
            //福袋组合列表
            $filter = array('combine_bn'=>$combineBns);
            $combineList = $combineMdl->getList('combine_id,combine_bn,selected_number,include_number', $filter, 0, -1);
            $combineList = array_column($combineList, null, 'combine_bn');
            
            //luckbag
            foreach ($asRs['luckybag'] as $combine_bn => $luckyVal)
            {
                $combineInfo = $combineList[$combine_bn];
                
                $luckyVal = array_merge($luckyVal, $combineInfo);
                
                $asRs['luckybag'][$combine_bn] = $luckyVal;
            }
        }
        
        $this->pagedata['quantity'] = $quantity;
        $this->pagedata['rr'] = [
            'heading' => $stock['regulation']['规则名称'],
            'content' => $stock['regulation']['规则内容']['result'],
        ];
        $this->pagedata['asRs'] = $asRs;
        
        $this->display('shop/adjustment/actual_stock.html');
    }

    /**
     * 批量发布库存弹窗页面
     * @date 2024-12-31 2:44 下午
     */
    public function displayBatchRelease()
    {
        $_POST['mapping'] = '1';//只处理已匹配的SKU
        $this->pagedata['request_url'] = $this->url.'&act=addBatchRelease';
        parent::dialog_batch('inventorydepth_mdl_shop_adjustment',true,100,'incr');
    }
    
    /**
     * 批量发布库存
     * 走队列
     * @date 2024-12-31 2:54 下午
     */
    public function addBatchRelease()
    {
        parse_str($_POST['primary_id'], $postdata);
    
        if (!$postdata['f']) { echo 'Error: 请先选择在售商品';exit;}
    
        $retArr  = array(
            'itotal'    => 0,
            'isucc'     => 0,
            'ifail'     => 0,
            'err_msg'   => array(),
        );
    
        $adjustmentMdl = app::get('inventorydepth')->model('shop_adjustment');
    
        $postdata['f']['mapping'] = '1';//只处理已匹配的SKU
        $adjustmentList = $adjustmentMdl->getList('id,shop_id,shop_name', $postdata['f'], $postdata['f']['offset'], $postdata['f']['limit'],'shop_id DESC');
        if (!$adjustmentList) {echo 'Error: 未查询到已关联在售商品';exit;}
    
        $retArr['itotal'] = count($adjustmentList);
    
        $shopAdjustment = [];
        $shop = [];
        foreach($adjustmentList as $info){
            $shopAdjustment[$info['shop_id']][] = $info;
            $shop[$info['shop_id']] = $info['shop_name'];
        }
        foreach ($shopAdjustment as $shop_id => $adjustment) {
            
            $offset = 0; $limit = 20; $_POST['shop_id'] =$shop_id;
        
            $params = $_POST; $params['limit'] = $limit;
            $params['id'] = array_column($adjustment,'id');
            
            // 操作员信息
            $params['operInfo'] = kernel::single('inventorydepth_func')->getDesktopUser();
        
            $count = count($adjustment);
        
            if($count<=0) continue;
            $title = "批量店铺【{$shop[$shop_id]}】库存回写";
        
            $total = floor($count/$limit);
            for ($i=$total; $i>=0 ; $i--) {
                $params['offset'] = $i*$limit;
                //插入队列
                kernel::single('inventorydepth_queue')->insert_release_queue($title,$params);
            }
            $retArr['isucc'] += $count;
        }
        echo json_encode($retArr),'ok.';exit;
    }
    
    /**
     * 批量删除
     * @date 2025-04-30 下午2:34
     */
    public function batch_delete()
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(0);
        
        if (isset($_POST['isSelectedAll']) && $_POST['isSelectedAll'] == '_ALL_') {
            if (!isset($_POST['shop_id']) || empty($_POST['shop_id'])) {
                die('缺少具体店铺查询条件!');
            }
        }
    
        $filter = array(
            'request' => 'false',
        );
        $_POST  = array_merge($filter, $_POST);
        $this->pagedata['request_url'] = $this->url . '&act=ajaxBatchDelete';
        
        //调用desktop公用进度条(第4个参数是增量传offset,否则默认一直为0)
        parent::dialog_batch('inventorydepth_mdl_shop_adjustment', false, 100);
    }
    
    public function ajaxBatchDelete()
    {
        $retArr = array(
            'itotal'  => 0,
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => [],
        );
        
        //获取发货单号
        parse_str($_POST['primary_id'], $postdata);
        if (!$postdata) {
            echo 'Error: 请先选择数据';
            exit;
        }
        
        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit  = intval($postdata['f']['limit']);
        
        if (empty($filter)) {
            echo 'Error: 没有找到查询条件';
            exit;
        }
        
        if (isset($_POST['isSelectedAll']) && $_POST['isSelectedAll'] == '_ALL_') {
            if (!isset($filter['shop_id']) || empty($filter['shop_id'])) {
                echo 'Error: 缺少具体店铺查询条件';
                exit;
            }
        }
        
        $shopAdjustmentMdl = $this->app->model('shop_adjustment');
        //data
        $dataList = $shopAdjustmentMdl->getFinderList('id', $filter, $offset, $limit);
        //check
        if (empty($dataList)) {
            echo 'Error: 没有获取到在售库存数据';
            exit;
        }
        
        $ids = array_column($dataList, 'id');
        
        //count
        $count            = count($dataList);
        $retArr['itotal'] = $count;
        
        $shopAdjustmentMdl->delete(['id' => $ids]);
        
        $retArr['isucc'] = $count;
        
        echo json_encode($retArr), 'ok.';
        exit;
    }
}
