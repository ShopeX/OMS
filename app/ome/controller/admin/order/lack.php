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
 * 缺货列表
 */
class ome_ctl_admin_order_lack extends desktop_controller {

    var $workground = "order_center";
    function __construct($app){
        if(in_array($_GET['act'], ['createPurchase'])) {
            $this->checkCSRF = false;
        }
        parent::__construct($app);
    }
    /**
     * 缺货搜索
     *
     * @param void
     * @return void
     */
    function index() {

        $params = array(
            'title'=>'缺货列表',
            'actions' => array(),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,

        );
        $query_data = $_POST;

        if ($_POST['shop_id']) {
            $_POST['from']['shop_id'] = $_POST['shop_id'];
        }
        if ($_POST['branch_id']) {
            $_POST['from']['branch_id'] = $_POST['branch_id'];
        }

        unset($query_data['act'],$query_data['ctl'],$query_data['app']);
        $params['actions'][] = array(
            'label'=>app::get('ome')->_('导出'),
            'class'=>'export',
            'icon'=>'add.gif',
            'submit'=>'index.php?app=ome&ctl=admin_order_lack&act=export&'.http_build_query($query_data),
            'target'=>'dialog::{width:400,height:170,title:\'导出\'}'
        );
        $is_export_purchase = kernel::single('desktop_user')->has_permission('order_lack_purchase');#增加商品导出权限
        if ($is_export_purchase) {
            $params['actions'][]= array( 
                'label' => '生成采购单',
                'submit' => 'index.php?app=ome&ctl=admin_order_lack&act=createPurchase&'.http_build_query($query_data),
                'target' => '_blank'
                        
            );
              
        }
        if(!isset($_GET['action'])) {
            $panel = new desktop_panel($this);
            $panel->setId('orderlack_finder_top');
            $panel->setTmpl('admin/finder/finder_lackpanel_filter.html');
            $panel->show('ome_mdl_order_lack', $params);
        }
        // 只加载发货模式为自发的
        $params['base_filter'] = [
            'filter_sql'    =>  'o.order_type <> "platform"',
        ];
        $this->finder('ome_mdl_order_lack',$params);
    }

    
    /**
     * 列表搜索.
     * @
     * @
     * @access  public
     * @author cyyr24@sina.cn
     */
    function search()
    {
        $oBranch = $this->app->model('branch');
        $branch_list = $oBranch->getOnlineBranchs('branch_id,name');
        $this->pagedata['branch_list'] = $branch_list;
        unset($branch_list);
        
        $filter = array('s_type'=>1, 'delivery_mode'=>'self');

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if($organization_permissions){
            $filter['org_id'] = $organization_permissions;
        }

        #过滤o2o门店店铺
        $oShop = $this->app->model('shop');
        $shop_list = $oShop->getlist('shop_id,name', $filter, 0, -1);
        $this->pagedata['shop_list'] = $shop_list;
        unset($shop_list);
        $this->page('admin/order/lack_search.html');
    }

    
    /**
     * 查看商品冻结列表
     * @param   product_id
     * @return  
     * @access  public
     * @author cyyr24@sina.cn
     */
    function show_store_freeze_list($product_id)
    {
        
        $oOrder_lack = $this->app->model('order_lack');
        $order_lack = $oOrder_lack->get_stocklist($product_id);
        $this->pagedata['order_lack'] = $order_lack;
        unset($order_lack);
        $this->singlepage('admin/order/lack_list.html');
    }

    
    /**
     * 订单冻结列表
     * @param   int product_id
     * @return
     * @access  public
     * @author cyyr24@sina.cn
     */
    function show_order_freeze_list($product_id,$bn)
    {
        $oOrder_lack = $this->app->model('order_lack');

        $count  = count($oOrder_lack->get_order($product_id,$bn));
        $page = $_GET['page'] ? $_GET['page'] : 1;
        $pagelimit = 10;
        $offset = ($page-1)*$pagelimit;
        $total_page = ceil($count/$pagelimit);
        $pager = $this->ui()->pager(array(
            'current'=>$page,
            'total'=>$total_page,
            'link'=>'index.php?app=ome&ctl=admin_order_lack&act=show_order_freeze_list&p[0]='.$product_id.'&p[1]='.$bn.'&target=container&page=%d',
        ));
        $order_lack = $oOrder_lack->get_orderlist($product_id,$bn,$pagelimit,$offset);
        $this->pagedata['order_lack'] = $order_lack;
        $this->pagedata['pager'] = $pager;
        unset($order_lack);
        if($_GET['target']){
            return $this->display('admin/order/orderlack_list.html');
        }
        $this->singlepage('admin/order/orderlack_list.html');
    }

    
    /**
     * 显示在途库存.
     * @param   product_id
     * @return  
     * @access  public
     * @author cyyr24@sina.cn
     */
    function show_arrive_store($product_id)
    {
        $oOrder_lack = $this->app->model('order_lack');
        $count  = $oOrder_lack->getArrivestore($product_id);
        $page = $_GET['page'] ? $_GET['page'] : 1;
        $pagelimit = 10;
        $offset = ($page-1)*$pagelimit;
        $total_page = ceil($count/$pagelimit);
        $pager = $this->ui()->pager(array(
            'current'=>$page,
            'total'=>$total_page,
            'link'=>'index.php?app=ome&ctl=admin_order_lack&act=show_arrive_store&p[0]='.$product_id.'&target=container&page=%d',
        ));
        $order_lack = $oOrder_lack->getArrivestorelist($product_id,$pagelimit,$offset);
        $this->pagedata['order_lack'] = $order_lack;
        $this->pagedata['pager'] = $pager;
        unset($order_lack);
         if($_GET['target']){
            return $this->display('admin/order/arrivestore_list.html');
        }
        $this->singlepage('admin/order/arrivestore_list.html');
    }

    
    /**
     * 生成采购单
     * @param  product_id
     * @return  
     * @access  public
     * @author cyyr24@sina.cn
     */
    function createPurchase()
    {
        $filter = array();
         // 商品查询参数
        if($_POST['isSelectedAll']=='_ALL_') {
            $product_ids = app::get('ome')->model('supply_product')->getList('*',$_POST,0,-1);
            for($i=0;$i<sizeof($product_ids);$i++){
                $product_id[] = $product_ids[$i]['product_id'];
            }
        }else{
            $product_id = $_POST['product_id'];
        }

        $this->pagedata['product_ids'] = implode(',',$product_id);
        // 获取供应商id
        $sql = 'SELECT a.supplier_id FROM sdb_purchase_supplier_goods AS a
                LEFT JOIN sdb_material_basic_material AS b ON a.bm_id=b.bm_id
                WHERE b.bm_id IN ('.implode(',',$product_id).')
                LIMIT 1';
        
        $rs = kernel::database()->select($sql);
        if($rs) $supplier_id = $rs[0]['supplier_id'];
    
        $filter = $_GET;
        unset($filter['act'],$filter['ctl'],$filter['app']);
        $this->pagedata['filter'] = http_build_query($filter);
        $suObj = app::get('purchase')->model('supplier');
        $data = $suObj->getList('supplier_id, name','',0,-1);

        $brObj = app::get('ome')->model('branch');
        $row = $brObj->getList('branch_id, name','',0,-1);

        /*
         * 获取操作员管辖仓库
         */
        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super){
           $branch_list = $brObj->getBranchByUser();
        }
        $this->pagedata['branch_list'] = $branch_list;
        $is_super = 1;
        $this->pagedata['is_super'] = $is_super;

        //获取设置的采购方式
        $po_type = app::get('ome')->getConf('purchase.po_type');
        if (!$po_type) $po_type = 'credit';
        $this->pagedata['po_type'] = $po_type;
        

        $supplier = $suObj->dump($supplier_id, 'supplier_id,name,arrive_days');

        


        $operator = kernel::single('desktop_user')->get_name();
        $this->pagedata['operator'] = $operator;
        $this->pagedata['supplier'] = $supplier;
        $this->pagedata['branchid'] = $branch_id;
        $this->pagedata['branch'] = $row;
        $this->pagedata['cur_date'] = date('Ymd',time()).'采购单';

        $this->singlepage("admin/order/lack/purchase_create.html");
    }

    
    /**
     * 获取需采购货品
     * @param   
     * @return  
     * @access  public
     * @author cyyr24@sina.cn
     */
    function getSafeStock($product_ids,$supplier_id)
    {
        if ($product_ids) {
            $filter['product_id'] = explode(',',$product_ids);
        }
        $filter_data = $_GET;
        unset($filter_data['act'],$filter_data['ctl'],$filter_data['app'],$filter_data['p']);
        $filter = array_merge($filter,$filter_data);
        $oOrder_lack = $this->app->model('order_lack');
        $oPo = app::get('purchase')->model('po');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $data = $oOrder_lack->getlist('*',$filter);
        $lack_data = array();
        foreach ($data as $k=>$v ) {
            if ($v['product_id']>0) {
                $v['num'] = $v['product_lack'];
                if($supplier_id > 0){
                    $v['price'] = $oPo->getPurchsePriceBySupplierId($supplier_id, $v['product_id'], 'desc');
                    if (!$v['price']){
                        $v['price'] = 0;
                    }
                }else{
                    $product = $basicMaterialExtObj->dump(array('bm_id'=>$v['product_id']),'cost');
                    $v['price'] = $product['price']['cost']['price'];
                }
                $lack_data[] = $v;
            }
        }
        echo json_encode($lack_data);
    }

    
    /**
     * 供应商.
     * @param 
     * @return
     * @access  public
     * @author cyyr24@sina.cn
     */
    function supplier()
    {
        
    }

    /**
     * 缺货商品导出
     * @param  array
     * @return 
     * @access  public
     * @author cyyr24@sina.cn
     */
    function export()
    {
        $filter = $_GET;
        unset($filter['act'],$filter['ctl'],$filter['app']);
        $this->pagedata['filter'] = $filter;
        if( !$this->pagedata['thisUrl'] )
            $this->pagedata['thisUrl'] = $this->url;
        $ioType = array();
        foreach( kernel::servicelist('desktop_io') as $aio ){
            $ioType[] = $aio->io_type_name;
        }
        $this->pagedata['ioType'] = $ioType;
        echo $_GET['change_type'];
        if( $_GET['change_type'] )
            $this->pagedata['change_type'] = $_GET['change_type'];
        echo $this->fetch('admin/order/lack/export.html');
    }

    /**
     * 缺货订单列表
     *
     * @return void
     * @author
     */
    public function olist()
    {
        $params = array(
            'title'                  => '缺货订单',
            'actions'                => array(
                array('label'=>'重新路由', 'submit' => 'index.php?app=ome&ctl=admin_order_lack&act=routerAgain', 'target' => 'dialog::{width:600,height:250,title:\'重新路由\'}')
            ),
            'base_filter'            => $this->_getOlistFilter(),
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'finder_aliasname'       => 'order_lack_olist',
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
        );

        // 导出
        $user = kernel::single('desktop_user');
        if($user->has_permission('order_export')){
            $params['use_buildin_export'] = true;
        }

        $this->finder('ome_mdl_orders',$params);
    }

    public function routerAgain() {

        $batchLogModel = app::get('ome')->model('batch_log');
        $old = $batchLogModel->db_dump(array('log_type' => 'ordertaking', 'status' => 0,'createtime|than'=>(time()-600)));
        if ($old) {
            echo '<div style="color:red">队列中已存在相关任务,请稍后再试.</div>';
            exit();
        }
        
        $model = app::get('ome')->model('orders');
        $pageData = array(
            'billName' => '订单',
            'request_url' => 'index.php?app=ome&ctl=admin_order_lack&act=routerSpilt',
            'maxProcessNum' => 50,
            'close' => true
        );
        $this->selectToPageRequest($model, $pageData, $this->_getOlistFilter());
    }

    protected function _getOlistFilter() {
        $params = array(
            //'assigned'      => 'assigned',
            'abnormal'       => 'false',
            'is_fail'        => 'false',
            'status'         => 'active',
            'process_status' => array('unconfirmed','confirmed','splitting'),
            'archive'       => 0,
        );
//        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
//        if($organization_permissions){
//            $params['org_id'] = $organization_permissions;
//        }
        if(!kernel::single('desktop_user')->is_super()){
            $op_id = kernel::single('desktop_user')->get_id();
            $params['op_id'] = array($op_id, 0);
        }
        /*$flag = omeauto_auto_const::__STORE_CODE;
        $params['order_confirm_filter'] = sprintf("(sdb_ome_orders.auto_status & %s = %s)", $flag, $flag); */
        
        return $params;
    }

    public function routerSpilt() {
//        $key = 'ome-order-lack-router';
//        cachecore::store($key, time(), 360);
        $splitOrderId = explode(';', $_POST['ajaxParams']);
        $retArr = array(
            'total' => count($splitOrderId),
            'succ' => count($splitOrderId),
            'fail' => 0,
            'fail_msg' => array()
        );
        // 重置一下路由次数
        if ($splitOrderId) {
            app::get('ome')->model('order_extend')->update(['router_num' => '0'],[
                'order_id' => $splitOrderId,
            ]);
        }
        kernel::single('ome_batch_log')->split($splitOrderId);
        $oOperation_log = $this->app->model('operation_log');
        if ($splitOrderId) {
            $oOperation_log->batch_write_log('order_dispatch@ome', ['order_id' => $splitOrderId], '手动触发重新路由', time());
        }
        echo json_encode($retArr);
    }

    public function apply() {
        $order_id = (int) $_GET['order_id'];
        $order = app::get('ome')->model('orders')->db_dump($order_id, 'shop_type, order_source');
        if (!($order['shop_type'] == 'taobao' && $order['order_source'] == 'maochao')) {
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('目前仅支持猫超国际缺货申请');window.close();</script>";
            exit;
        }
        $order_objects = app::get('ome')->model('order_objects')->getList('*', ['order_id'=>$order_id, 'delete'=>'false']);
        $store_code = $order_objects[0]['store_code'];
        $order_objects = array_column($order_objects, null, 'obj_id');
        $order_items = app::get('ome')->model('order_items')->getList('*', ['order_id'=>$order_id, 'delete'=>'false']);
        $appointBranch = kernel::single('ome_branch_type')->getBranchIdByStoreCode([$store_code]);
        if(empty($appointBranch)) {
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('没有指定仓{$store_code},不能进行缺货申请');window.close();</script>";
            exit;
        }
        $bmIds = array_column($order_items, 'product_id');
        $branch_id = current($appointBranch);
        $branch_product = app::get('ome')->model('branch_product')->getList('product_id, store, store_freeze', 
            ['branch_id'=>$branch_id, 'product_id'=>$bmIds]);
        $branch_product = array_column($branch_product, null, 'product_id');
        $object_items = [];
        foreach ($order_items as $v) {
            $obj_quantity = $order_objects[$v['obj_id']]['quantity'];
            $radio = $v['nums'] / $obj_quantity;
            $v['valid_num'] = $branch_product[$v['product_id']]['store'] - $branch_product[$v['product_id']]['store_freeze'];
            $objValid = bcdiv($v['valid_num'], $radio);
            $outStockNum = $objValid < $obj_quantity ? ($obj_quantity - $objValid) : 0;
            if($object_items[$v['obj_id']]) {
                if($objValid < $object_items[$v['obj_id']]['valid_num']) {
                    $object_items[$v['obj_id']]['valid_num'] = $objValid;
                    $object_items[$v['obj_id']]['out_stock_num'] = $outStockNum;
                }
                $object_items[$v['obj_id']]['items'][$v['item_id']] = $v; 
            } else {
                $object_items[$v['obj_id']] = $order_objects[$v['obj_id']];
                $object_items[$v['obj_id']]['valid_num'] = $objValid;
                $object_items[$v['obj_id']]['out_stock_num'] = $outStockNum;
                $object_items[$v['obj_id']]['items'][$v['item_id']] = $v; 
            }
        }
        $this->pagedata['order_id'] = $order_id;
        $this->pagedata['object_items'] = $object_items;
        $this->singlepage('admin/order/lack/apply.html');
    }

    public function dealApply() {
        $order_id = (int) $_POST['order_id'];
        $out_stock = $_POST['out_stock'];
        if(empty($out_stock)) {
            $this->splash('error', $this->url, '没有缺货商品， 可以重新审单');
        }
        $order = app::get('ome')->model('orders')->db_dump($order_id, 'shop_id, order_bn');
        if(empty($order)) {
            return;
        }
        $orderExtend = app::get('ome')->model('order_extend')->db_dump($order_id, 'extend_field');
        $orderObject = app::get('ome')->model('order_objects')->getList('obj_id, oid, shop_goods_id', ['obj_id'=>array_keys($out_stock)]);
        foreach ($orderObject as $key => $value) {
            $orderObject[$key]['out_stock'] = $out_stock[$value['obj_id']];
        }
        $sdf = [
            'order'=>$order,
            'order_extend'=>$orderExtend,
            'order_objects'=>$orderObject
        ];
        $rs = kernel::single('erpapi_router_request')->set('shop', $order['shop_id'])->order_lackApply($sdf);
        if($rs['rsp'] != 'succ') {
            $this->splash('error', $this->url, '请求失败:'.json_encode($rs, JSON_UNESCAPED_UNICODE));
        }
        app::get('ome')->model('orders')->update(['is_delivery'=>'N'], ['order_id'=>$order_id]);
        app::get('ome')->model('operation_log')->write_log('order_confirm@ome',$order_id,"订单缺货申请成功");
        $this->splash('success', $this->url);
    }
}
