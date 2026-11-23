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
 * 经销商订单管理
 *
 * @author wangbiao@shopex.cn
 * @version 2024.04.10
 */
class dealer_ctl_admin_platform_orders extends desktop_controller
{
    var $title = '平台原始订单';
    var $workground = 'dealer_center';
    
    private $_mdl = null; //model类
    private $_commonLib = null;
    private $_orderLib = null;
    private $_businessLib = null;
    
    private $_primary_id = null; //主键ID字段名
    private $_primary_bn = null; //单据编号字段名
    
    public function __construct($app)
    {
        parent::__construct($app);
        
        $this->_mdl = app::get('dealer')->model('platform_orders');
        $this->_commonLib = kernel::single('dealer_common');
        $this->_orderLib = kernel::single('dealer_platform_orders');
        $this->_businessLib = kernel::single('dealer_business');
        
        //primary_id
        $this->_primary_id = 'plat_order_id';
        
        //primary_bn
        $this->_primary_bn = 'plat_order_bn';
    }
    
    public function index()
    {
        $user = kernel::single('desktop_user');
        $actions = array();
        
        //filter
        $base_filter = $this->getFilters();
        
        //button
        $buttonList = array();
        $buttonList['add'] = array('label'=>'新建经销订单', 'href'=>$this->url.'&act=addOrder');
        $buttonList['dispose'] = array('label'=>'批量转换订单', 'submit'=>$this->url.'&act=batchDispose', 'target'=>'dialog::{width:600,height:230,title:\'批量转换订单\'}');
        $buttonList['repair'] = array('label'=>'修复失败订单', 'submit'=>$this->url.'&act=batchRepair', 'target'=>'dialog::{width:600,height:230,title:\'批量修复失败订单(需要经销销售商品已经存在)\'}');
        
        //已弃用
        //$buttonList['updateItems'] = array('label'=>'更新发货方式', 'submit'=>$this->url.'&act=batchUpdateItems', 'target'=>'dialog::{width:600,height:230,title:\'批量重新更新订单明细发货方式\'}');
        
        //view
        $_GET['view'] = (empty($_GET['view']) ? '0' : $_GET['view']);
        switch ($_GET['view'])
        {
            case '0':
                $actions[] = $buttonList['dispose'];
                
                //新建经销订单
                if($user->has_permission('dealer_order_add')){
                    $actions[] = $buttonList['add'];
                }
                break;
            case '1':
                //新建经销订单
                if($user->has_permission('dealer_order_add')){
                    $actions[] = $buttonList['add'];
                }
                break;
            case '2':
                $actions[] = $buttonList['dispose'];
                
                break;
            case '4':
                //修复失败订单
                //$actions[] = $buttonList['repair'];
                break;
        }
        
        //导出权限
        $use_buildin_export = false;
        if($user->has_permission('order_export')){
            $use_buildin_export = true;
        }
        
        //params
        $orderby = 'createtime DESC';
        $params = array(
            'title' => $this->title,
            'base_filter' => $base_filter,
            'actions'=> $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_export' => $use_buildin_export,
            'use_buildin_import' => false,
            'use_buildin_filter' => true,
            'orderBy' => $orderby,
        );
        
        $this->finder('dealer_mdl_platform_orders', $params);
    }
    
    public function _views()
    {
        //filter
        $base_filter = $this->getFilters();
        
        //menu
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('未转单'), 'filter'=>array('convert_status'=>array('unconvert', 'fail'), 'status'=>'active', 'is_fail'=>'false'), 'optional'=>false),
            1 => array('label'=>app::get('base')->_('全部'), 'filter'=>$base_filter, 'optional'=>false),
            2 => array('label'=>app::get('base')->_('部分转单'), 'filter'=>array('convert_status'=>array('splitting'), 'status'=>'active', 'is_fail'=>'false'), 'optional'=>false),
            4 => array('label'=>app::get('base')->_('失败订单'), 'filter'=>array('is_fail'=>'true', 'status'=>'active'), 'optional'=>false),
            5 => array('label'=>app::get('base')->_('异常订单'), 'filter'=>array('is_abnormal'=>'true', 'status'=>'active'), 'optional'=>false),
        );
        
        foreach($sub_menu as $k => $v)
        {
            if (isset($v['filter'])){
                $v['filter'] = array_merge($base_filter, $v['filter']);
            }
            
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['href'] = 'index.php?app='. $_GET['app'] .'&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$k;
            
            //第一个TAB菜单没有数据时显示全部
            if($k == 0){
                //count
                $sub_menu[$k]['addon'] = $this->_mdl->count($v['filter']);
                if($sub_menu[$k]['addon'] == 0){
                    unset($sub_menu[$k]);
                }
            }else{
                //count
                $sub_menu[$k]['addon'] = $this->_mdl->viewcount($v['filter']);
            }
        }
        
        return $sub_menu;
    }
    
    /**
     * 公共filter条件
     *
     * @return array
     */
    public function getFilters()
    {
        $base_filter = array();
        
        //获取操作人员的企业组织架构ID权限
        $cosData = $this->_businessLib->getOperationCosIds();
        if($cosData[1]){
            $base_filter['cos_id'] = $cosData[1];
        }
        
        return $base_filter;
    }
    
    /**
     * 批量转换订单
     *
     * @return void
     */
    public function batchDispose()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        //post
        $orderIds = $_POST[$this->_primary_id];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(empty($orderIds)){
            die('请选择需要操作的订单!');
        }
        
        if(count($orderIds) > 500){
            die('每次最多只能选择500条!');
        }
        
        $this->pagedata['GroupList'] = json_encode($orderIds);
        $this->pagedata['request_url'] = $this->url .'&act=ajaxDispose';
        
        //调用desktop公用进度条(第4个参数是增量传offset,否则默认一直为0)
        parent::dialog_batch('dealer_mdl_platform_orders', false, 50, 'incr');
    }
    
    public function ajaxDispose()
    {
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取订单
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择订单';
            exit;
        }
        
        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        
        if(empty($filter)){
            echo 'Error: 没有找到查询条件';
            exit;
        }
        
        //data
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到订单';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //list
        foreach ($dataList as $key => $val)
        {
            //check
            if(!in_array($val['convert_status'], array('unconvert', 'fail', 'splitting'))){
                //fail
                $retArr['err_msg'][] = '订单转单状态，不允许审核订单';
                $retArr['ifail'] += 1;
                
                continue;
            }
            
            if(!in_array($val['dispose_status'], array('all_daifa', 'part_daifa'))){
                //fail
                $retArr['err_msg'][] = '订单不是代发货，不允许审核订单';
                $retArr['ifail'] += 1;
                
                continue;
            }
            
            if(!in_array($val['pay_status'], array('1'))){
                //fail
                $retArr['err_msg'][] = '订单不是已支付状态，不允许审核订单';
                $retArr['ifail'] += 1;
                
                continue;
            }
            
            if(!in_array($val['ship_status'], array('0'))){
                //fail
                $retArr['err_msg'][] = '订单已经发货，不允许审核订单';
                $retArr['ifail'] += 1;
        
                continue;
            }
            
            //confirm
            $params = array('obj_id'=>$val['plat_order_id']);
            $result = $this->_orderLib->confirmOrder($params);
            if($result['rsp'] != 'succ'){
                //fail
                $retArr['err_msg'][] = $result['error_msg'];
                
                $retArr['ifail'] += 1;
            }else{
                //succ
                $retArr['isucc'] += 1;
            }
        }
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
    
    /**
     * 批量重新更新订单明细发货方式
     *
     * @return void
     */
    public function batchUpdateItems()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        //post
        $orderIds = $_POST[$this->_primary_id];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(empty($orderIds)){
            die('请选择需要操作的订单!');
        }
        
        if(count($orderIds) > 500){
            die('每次最多只能选择500条!');
        }
        
        $this->pagedata['GroupList'] = json_encode($orderIds);
        $this->pagedata['request_url'] = $this->url .'&act=ajaxUpdateItems';
        
        //调用desktop公用进度条(第4个参数是增量传offset,否则默认一直为0)
        parent::dialog_batch('dealer_mdl_platform_orders', false, 50, 'incr');
    }
    
    public function ajaxUpdateItems()
    {
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取订单
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择订单';
            exit;
        }
        
        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        
        if(empty($filter)){
            echo 'Error: 没有找到查询条件';
            exit;
        }
        
        //data
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到订单';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //list
        foreach ($dataList as $key => $val)
        {
            //check
            if(!in_array($val['convert_status'], array('unconvert', 'fail'))){
                //fail
                $retArr['err_msg'][] = '订单转单状态，不允许更新订单明细发货方式';
                $retArr['ifail'] += 1;
                
                continue;
            }
            
//            if(!in_array($val['dispose_status'], array('fail'))){
//                //fail
//                $retArr['err_msg'][] = '订单已经转换，不允许操作订单';
//                $retArr['ifail'] += 1;
//
//                continue;
//            }
            
            if(!in_array($val['pay_status'], array('1'))){
                //fail
                $retArr['err_msg'][] = '订单不是已支付状态，不允许操作订单';
                $retArr['ifail'] += 1;
                
                continue;
            }
            
            if(!in_array($val['ship_status'], array('0'))){
                //fail
                $retArr['err_msg'][] = '订单已经发货，不允许审核订单';
                $retArr['ifail'] += 1;
                
                continue;
            }
            
            //更新订单明细发货方式
            $result = $this->_orderLib->updateOrderShopyjdfType($val);
            if($result['rsp'] != 'succ'){
                //fail
                $retArr['err_msg'][] = $result['error_msg'];
                
                $retArr['ifail'] += 1;
            }else{
                //succ
                $retArr['isucc'] += 1;
            }
        }
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
    
    /**
     * 修正失败订单
     *
     * @return void
     */
    public function batchRepair()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        //post
        $orderIds = $_POST[$this->_primary_id];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(empty($orderIds)){
            die('请选择需要操作的订单!');
        }
        
        if(count($orderIds) > 500){
            die('每次最多只能选择500条!');
        }
        
        $this->pagedata['GroupList'] = json_encode($orderIds);
        $this->pagedata['request_url'] = $this->url .'&act=ajaxRepair';
        
        //调用desktop公用进度条(第4个参数是增量传offset,否则默认一直为0)
        parent::dialog_batch('dealer_mdl_platform_orders', false, 50, 'incr');
    }
    
    public function ajaxRepair()
    {
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取订单
        parse_str($_POST['primary_id'], $postdata);
        if(!$postdata){
            echo 'Error: 请先选择订单';
            exit;
        }
        
        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        
        if(empty($filter)){
            echo 'Error: 没有找到查询条件';
            exit;
        }
        
        //data
        $dataList = $this->_mdl->getList('*', $filter, $offset, $limit);
        if(empty($dataList)){
            echo 'Error: 没有获取到订单';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($dataList);
        
        //list
        foreach ($dataList as $key => $val)
        {
            //check
            if($val['is_fail'] != 'true'){
                //fail
                $retArr['err_msg'][] = '订单不是失败订单，不需要操作订单';
                $retArr['ifail'] += 1;
                
                continue;
            }
            
            //更新订单明细发货方式
            $result = $this->_orderLib->repairOrder($val);
            if($result['rsp'] != 'succ'){
                //fail
                $retArr['err_msg'][] = $result['error_msg'];
                
                $retArr['ifail'] += 1;
            }else{
                //succ
                $retArr['isucc'] += 1;
            }
        }
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
    
    /**
     * 新建经销订单
     *
     * @return void
     */
    public function addOrder()
    {
        $shopMdl = app::get('ome')->model('shop');
        $user = kernel::single('desktop_user');
        
        //新建经销订单
        if(!$user->has_permission('dealer_order_add')){
            die('您没有权限新建经销订单');
        }
        
        //获取操作人员的企业组织架构ID权限
        $filter = array('s_type'=>1, 'delivery_mode'=>'shopyjdf');
        $cosData = $this->_businessLib->getOperationCosIds();
        if($cosData[1]){
            $filter['cos_id'] = $cosData[1];
        }
        
        $shopList = $shopMdl->getList('shop_id,shop_bn,name,shop_type', $filter);
        if(empty($shopList)){
            die('没有分销一件代发店铺或者没有权限');
        }
        
        $this->pagedata['title'] = '新建订单';
        $this->pagedata['shopData'] = $shopList;
        $this->pagedata['finder_vid'] = $_GET['finder_vid'];
        $this->pagedata['creatime'] = date("Y-m-d",time());
        
        $this->page("admin/order/add_order.html");
    }
    
    public function doAddOrder()
    {
        $this->begin($this->url."&act=addOrder");
        
        define('FRST_TRIGGER_OBJECT_TYPE','订单：手工新建订单');
        define('FRST_TRIGGER_ACTION_TYPE','dealer_ctl_admin_platform_orders：doAddOrder');
        
        $shopMdl = app::get('ome')->model('shop');
        
        $user = kernel::single('desktop_user');
        $jxOrderLib = kernel::single('dealer_platform_orders');
        $deMaterialLib = kernel::single('dealer_material');
        
        //新建经销订单
        if(!$user->has_permission('dealer_order_add')){
            $this->end(false, '您没有创建分销订单的权限');
        }
        
        //post
        $post = $_POST;
        
        //area
        $temp = explode(':', $post['consignee_area']);
        $consignee_area = $temp[1];
        
        //product
        $buyNums = $_POST['num'];
        $buyPrices = $_POST['price'];
        
        //check
        if (!$post['shop_id']){
            $this->end(false, '请选择经销店铺');
        }
        
        if (!$post['member_id']){
            $this->end(false, '请选择会员');
        }
        
        if (empty($post['consignee_name']) || empty($post['consignee_mobile'])){
            $this->end(false, '请填写收货人姓名、收货人手机号');
        }
        
        if (empty($consignee_area) || empty($post['consignee_addr'])){
            $this->end(false, '请填写收货人地区、收货人地址');
        }
        
        if (empty($buyNums)){
            $this->end(false, '请选择销售物料与购买数量');
        }
        
        if (empty($buyPrices)){
            $this->end(false, '请填写销售物料价格');
        }
        
        //format
        $goodsList = array();
        foreach ($buyNums as $sm_id => $num)
        {
            $num = intval($num);
            $goodsList[$sm_id] = array('quantity'=>$num);
            
            //check
            if(empty($sm_id) || empty($num)){
                $this->end(false, '销售物料数量为空，请检查');
            }
            
            if ($num < 1 || $num > 10000){
                $this->end(false, '数量必须大于1且小于10000');
            }
        }
        
        foreach ($buyPrices as $sm_id => $price)
        {
            $price = number_format($price, 2, '.', ' ');
            $goodsList[$sm_id]['price'] = $price;
            
            //check
            if(empty($sm_id)){
                $this->end(false, '销售物料价格未设置，请检查');
            }
            
            if ($price < 0){
                $this->end(false, '销售物料价格不正确，请检查');
            }
        }
        
        //获取操作人员的企业组织架构ID权限
        $filter = array('s_type'=>1, 'delivery_mode'=>'shopyjdf', 'shop_id'=>$post['shop_id']);
        $cosData = $this->_businessLib->getOperationCosIds();
        if($cosData[1]){
            $filter['cos_id'] = $cosData[1];
        }
        
        $shopInfo = $shopMdl->db_dump($filter, '*');
        if(empty($shopInfo)){
            $this->end(false, '您没有选择的经销店铺权限，请检查');
        }elseif(empty($shopInfo['cos_id']) || empty($shopInfo['bs_id'])){
            $this->end(false, '店铺编码：'. $shopInfo['shop_bn'] .'没有组织架构ID、经销商ID，请检查');
        }
        $shop_id = $shopInfo['shop_id'];
        
        //order_objects
        $order_pmt_order = $order_cost_item = $order_discount = $order_total_amount = $order_pmt_goods = 0;
        $order_objects = array();
        $productList = array();
        foreach ($goodsList as $sm_id => $goodsRow)
        {
            //check
            if(empty($goodsRow['quantity']) || empty($goodsRow['price'])){
                $this->end(false, '销售物料编ID：'. $sm_id .'没有数量、价格，请检查');
            }
            
            //检查货品是否存在销售物料中
            $salesMInfo = $deMaterialLib->getSaleMaterialInfoByIds($shop_id, $sm_id);
            if (empty($salesMInfo)) {
                $this->end(false, '销售物料ID：'. $sm_id .'没有找到，请检查');
            }
            
            //销售物料关联的基础物料列表
            $bmList = $deMaterialLib->getBasicMatBySmIds($sm_id);
            if(empty($bmList)) {
                $this->end(false, '销售物料编码：'. $salesMInfo['sales_material_bn'] .'没有关联的基础物料，请检查');
            }
            
            //price
            $obj_quantity = intval($goodsRow['quantity']);
            $obj_price = $goodsRow['price'];
            $obj_sale_price = $obj_quantity * $obj_price;
            $obj_amount = $obj_sale_price;
            $obj_pmt_price = 0;
            $obj_divide_order_fee = $obj_sale_price;
            $obj_part_mjz_discount = 0;
            
            //order money
            $order_discount = 0;
            $order_pmt_goods = 0;
            $order_pmt_order += $obj_pmt_price;
            $order_cost_item += $obj_sale_price;
            $order_total_amount += $obj_sale_price;
            
            //material
            $obj_type = 'goods';
            switch ($salesMInfo['sales_material_type']) {
                case "2":
                    $obj_type = 'pkg';
                    
                    //根据促销总价格计算每个物料的贡献金额值
                    $deMaterialLib->calProSaleMatPriceByRate($obj_sale_price, $bmList);
                    
                    //根据优惠价格计算每个物料的贡献金额值
                    $pmt_price_rate = $deMaterialLib->getPmtPriceByRate($obj_pmt_price, $bmList);
                    break;
                case "3":
                    $obj_type = 'gift';
                    break;
            }
            
            //order_items
            $order_items = array();
            foreach ($bmList as $bmKey => $basicMInfo)
            {
                $product_id = $basicMInfo['bm_id'];
                $material_bn = $basicMInfo['material_bn'];
                $item_nums = $basicMInfo['number'] * $obj_quantity;
                
                if ($obj_type == 'pkg') {
                    $item_type = 'pkg';
                    $shop_product_id = 0;
                    
                    $cost = $basicMInfo['cost'];
                    $pmt_price = $pmt_price_rate[$material_bn] ? ($pmt_price_rate[$material_bn]['rate_price'] > 0 ? $pmt_price_rate[$material_bn]['rate_price'] : 0) : 0.00;
                    $sale_price = $basicMInfo['rate_price'] > 0 ? $basicMInfo['rate_price'] : 0;
                    $amount = bcadd((float)$pmt_price, (float)$sale_price, 2);
                    $price = bcdiv($amount, $basicMInfo['number'] * $obj_quantity, 2);
                    $divide_order_fee  = 0;
                    $part_mjz_discount = 0;
                    $weight = $basicMInfo['weight'];
                }else {
                    $item_type = $obj_type == 'goods' ? 'product' : $obj_type;
                    $shop_product_id = 0;
                    
                    $cost              = $basicMInfo['cost'];
                    $price             = $obj_price;
                    $pmt_price         = $obj_pmt_price;
                    $sale_price        = (isset($obj_sale_price) && is_numeric($obj_sale_price) && -1 != bccomp($obj_sale_price, 0, 3)) ? $obj_sale_price : bcsub($obj_amount, (float)$obj_sale_price, 3);
                    $amount            = $obj_amount;
                    $divide_order_fee  = $obj_divide_order_fee;
                    $part_mjz_discount = $obj_part_mjz_discount;
                    $weight            = $basicMInfo['weight'] ? $basicMInfo['weight'] : 0.00;
                }
                
                $order_items[] = array(
                    'shop_goods_id'     => 0,
                    'product_id'        => $product_id,
                    'shop_product_id'   => $shop_product_id,
                    'bn'                => $material_bn,
                    'name'              => $basicMInfo['material_name'],
                    'cost'              => $cost ? $cost : 0.00,
                    'price'             => $price ? $price : 0.00,
                    'pmt_price'         => $pmt_price,
                    'sale_price'        => $sale_price ? $sale_price : 0.00,
                    'amount'            => $amount ? $amount : 0.00,
                    'weight'            => $weight ? $weight : 0.00,
                    'nums'              => $item_nums, //购买数量
                    'item_type'         => $item_type,
                    'divide_order_fee'  => $divide_order_fee,
                    'part_mjz_discount' => $part_mjz_discount,
                    'addon'             => '',
                    'product_attr'      => '',
                    'is_delete'         => 'false', //经销订单明细删除状态
                );
                
                //products
                $productList[$product_id] = array(
                    'product_id' => $product_id,
                    'product_bn' => $material_bn,
                    'betc_id' => 0, //贸易公司ID
                    'is_shopyjdf_type' => '0', //发货方式
                );
            }
            
            //check
            if(empty($order_items)){
                $this->end(false, '销售物料编码：'. $salesMInfo['sales_material_bn'] .'没有基础物料明细，请检查');
            }
            
            //objects
            $order_objects[] = array(
                'plat_oid'          => '', //oid
                'obj_type'          => $obj_type,
                'obj_alias'         => '',
                'shop_goods_id'     => 0,
                'goods_id'          => $salesMInfo['sm_id'],
                'bn'                => $salesMInfo['sales_material_bn'],
                'name'              => $salesMInfo['sales_material_name'],
                'price'             => $obj_price,
                'amount'            => $obj_amount,
                'quantity'          => $obj_quantity, //购买数量
                'weight'            => 0,
                'pmt_price'         => $obj_pmt_price,
                'sale_price'        => $obj_sale_price,
                'divide_order_fee'  => $obj_divide_order_fee,
                'part_mjz_discount' => $obj_part_mjz_discount,
                'pay_status'        => '1', //已支付
                'is_delete'         => 'false', //经销订单明细删除状态
                'order_items'       => $order_items,
            );
            
            unset($order_items);
        }
        
        //check
        if(empty($order_objects)){
            $this->end(false, '没有购买的销售物料信息，请检查');
        }
        
        //sdf
        $sdf = array(
            'shop_id' => $shop_id,
            'shop_bn' => $shopInfo['shop_bn'],
            'cos_id' => $shopInfo['cos_id'], //组织架构ID
            'bs_id' => $shopInfo['bs_id'], //经销商ID
            
            'member_id' => intval($post['member_id']),
            'ship_name' => $post['consignee_name'],
            'ship_mobile' => $post['consignee_mobile'],
            'ship_area' => $consignee_area,
            'ship_addr' => $post['consignee_addr'],
            
            'currency' => 'CNY',
            'pmt_order' => $order_pmt_order, //订单优惠
            'cost_item' => $order_cost_item, //商品金额
            'discount' => $order_discount,
            'total_amount' => $order_total_amount, //订单总额
            'pmt_goods' => $order_pmt_goods, //商品优惠
            'cost_freight' => 0, //配送费用
            'payed' => $order_total_amount, //已支付金额
            'pay_status' => '1', //已支付
            
            'source' => 'local', //local为本地新建订单
            'order_source' => 'local', //local为本地新建订单
            'mark_text' => $post['order_memo'], //商家备注
            'createtime' => time(),
            'itemnum' => count($order_objects),
            'order_objects' => $order_objects,
        );
        
        //通过基础物料获取发货方式：自发、代发，所属贸易公司ID；
        $businessInfo = array('shop_id'=>$shop_id);
        $productList = $jxOrderLib->getProductDespatchType($productList, $businessInfo);
        
        //format
        foreach ($sdf['order_objects'] as $objKey => $objVal)
        {
            //check
            if(empty($objVal['order_items'])){
                continue;
            }
            
            //items
            $shopyjdf_types = array();
            foreach ($objVal['order_items'] as $itemKey => $itemVal)
            {
                $product_id = $itemVal['product_id'];
                $betc_id = 0; //贸易公司ID
                $is_shopyjdf_type = '1'; //发货方式(默认自发)
                
                //发货方式
                if(isset($productList[$product_id])){
                    $betc_id = intval($productList[$product_id]['betc_id']);
                    $is_shopyjdf_type = $productList[$product_id]['is_shopyjdf_type'];
                }
                
                $itemVal['betc_id'] = $betc_id;
                $itemVal['is_shopyjdf_type'] = $is_shopyjdf_type;
                
                //汇总发货方式
                $shopyjdf_types[$is_shopyjdf_type] = $is_shopyjdf_type;
                
                //merge
                $objVal['order_items'][$itemKey] = $itemVal;
            }
            
            //object层发货方式
            if(count($shopyjdf_types) > 1){
                $objVal['is_shopyjdf_type'] = '3'; //部分代发货，即有自发货，也有代发货；
            }else{
                $objVal['is_shopyjdf_type'] = current($shopyjdf_types);
            }
            
            //转换状态
            if($objVal['is_shopyjdf_type'] == '' || $objVal['is_shopyjdf_type'] == '0'){
                $objVal['is_shopyjdf_step'] = '0';
            }else{
                $objVal['is_shopyjdf_step'] = '2';
            }
            
            //merge
            $sdf['order_objects'][$objKey] = $objVal;
        }
        
        //订单转换状态
        $dispose_status = 'zifa';
        $convert_status = '';
        if(count($shopyjdf_types) > 1){
            $dispose_status = 'part_daifa'; //部分代发货
        }else{
            $is_shopyjdf_type = current($shopyjdf_types);
            if($is_shopyjdf_type == '2'){
                $dispose_status = 'all_daifa'; //全部代发货
            }elseif($is_shopyjdf_type == '1'){
                $dispose_status = 'zifa'; //全部自发货
                $convert_status = 'needless'; //无需转单
            }
        }
        $sdf['dispose_status'] = $dispose_status;
        
        //转单状态
        if($convert_status){
            $sdf['convert_status'] = $convert_status;
        }
        
        //plat_order_bn
        $sdf['plat_order_bn'] = $jxOrderLib->gen_plat_order_bn();
        
        //create
        $result = $jxOrderLib->create_order($sdf);
        if($result['rsp'] != 'succ'){
            $this->end(false, '创建订单失败：'. $result['error_msg']);
        }
        
        $this->end(true, '创建订单成功', $this->url.'&act=createOrderResult&finder_vid='. $_GET['finder_vid'] .'&plat_order_bn='.$sdf['plat_order_bn']);
    }
    
    public function createOrderResult()
    {
        $this->pagedata['plat_order_bn'] = $_GET['plat_order_bn'];
        $this->pagedata['finder_vid'] = $_GET['finder_vid'];
        
        $this->display('admin/order/create_order_result.html');
    }
    
    /**
     * 弹窗获取：经销销售商品列表
     *
     * @param Void
     * @return String
     */
    public function findSalesMaterial()
    {
        //已绑定的销售物料才可选择
        $base_filter = array('is_bind'=>1);
        
        //shop
        if($_GET['shop_id']){
            $shop = explode('*',$_GET['shop_id']);
            $base_filter['shop_id'] = array($shop[0], '_ALL_');
        }
        
        //销售物料类型,可选值:1(普通),2(组合)
        if($_GET['type']){
            $base_filter['sales_material_type'] = $_GET['type'];
        }
        
        //params
        $params = array(
            'title'=>'经销销售商品列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'use_view_tab' => false,
            'base_filter' => $base_filter,
        );
        $this->finder('dealer_mdl_sales_material', $params);
    }
    
    public function getSalesMaterialByAddNormalOrder()
    {
//        $sm_id = $_POST['sm_id'];
//        $sales_material_bn = $_GET['bn'];
//        $sales_material_name = $_GET['name'];
//        $basic_material_barcode = $_GET['barcode'];
//        $code_list = array();
//
//        //filter
//        $filter = array();
//        if (is_array($sm_id)){
//            if ($sm_id[0] != "_ALL_"){
//                $filter['sm_id'] = $sm_id;
//            }
//        }elseif($sales_material_bn){
//            $filter['sales_material_bn|head'] = $sales_material_bn;
//        }elseif($sales_material_name){
//            $filter['sales_material_name|head'] = $sales_material_name;
//        }
//
//        $salesBasicMaterialObj = app::get('material')->model('sales_basic_material');
//        if($basic_material_barcode){
//            $basicMaterialBarcode = kernel::single('material_basic_material_barcode');
//
//            //code
//            $filter = array(
//                'code|head'=>$basic_material_barcode
//            );
//
//            $bm_ids = $basicMaterialBarcode->getBmidListByFilter($filter, $code_list);
//            $_tmp = $salesBasicMaterialObj->getList('*',array('bm_id|in'=>$bm_ids),0,-1);
//
//            $filter = $_code_list = array();
//            foreach ($_tmp as $_k => $_v) {
//                $filter['sm_id|in'][] = $_v['sm_id'];
//
//                if (isset($code_list[$_v['bm_id']]) && $code_list[$_v['bm_id']]) {
//                    $_code_list[$_v['sm_id']] = $code_list[$_v['bm_id']];
//                }
//            }
//        }
//
//        $salesMaterialObj = app::get('material')->model('sales_material');
//        $salesMaterialExtObj = app::get('material')->model('sales_material_ext');
//        $salesMStockLib = kernel::single('material_sales_material_stock');
//
//        $filter['use_like'] = 1;
//        $data = $salesMaterialObj->getList('sm_id,sales_material_name,sales_material_bn,sales_material_type',$filter,0,-1);
//        $rows = array();
//        if (!empty($data)){
//            foreach ($data as $k => &$item){
//                $store = $salesMStockLib->getSalesMStockById($item['sm_id']);
//                $ExtInfo = $salesMaterialExtObj->dump($item['sm_id'],'retail_price');
//
//                $item['store'] = $store;
//                $item['num'] = 1;
//                $item['price'] = $ExtInfo['retail_price'];
//                if($item["sales_material_type"] == 4){ //手工新建订单 福袋类型不能修改售价price
//                    $item["tpl_price_readonly"] = "readonly";
//                }
//
//                $item['product_id'] = $item['sm_id'];
//                $item['bn'] = $item['sales_material_bn'];
//                $item['name'] = $item['sales_material_name'];
//                $item['barcode'] = (string)$_code_list[$item['sm_id']];
//
//                $rows[] = $item;
//            }
//        }
        
        //define
        $rows = array();
        
        echo "window.autocompleter_json=".json_encode($rows);
    }
    
    public function dosave()
    {
        $url      = 'index.php?app=dealer&ctl=admin_platform_orders&act=index&view=4&finder_vid='. $_REQUEST['finder_vid'];
        $plat_order_id = $_POST['order_id'];
        $oldProductBns = $_POST['oldPbn']; //失败的货号
        $newProductBns = $_POST['pbn']; //替换的新货号
        
        //opinfo
        $opinfo = kernel::single('ome_func')->getDesktopUser();
        
        //修正订单项
        $result = kernel::single('dealer_platform_order_fail')->modifyOrderItems($plat_order_id, $oldProductBns, $newProductBns, $opinfo);
        if ($result['rsp'] == 'succ') {
            $this->splash('success', $url, '指定订单修复成功');
        } else {
            $this->splash('error', $url, '指定订单修复失败：'. $result['error_msg']);
        }
    }
    
    /**
     * 失败订单批量修复货号
     */
    public function batchsave()
    {
        $queueObj = app::get('base')->model('queue');
        
        //opinfo
        $opinfo = kernel::single('ome_func')->getDesktopUser();
        
        $url    = 'index.php?app=dealer&ctl=admin_platform_orders&act=index&view=4&finder_vid='. $_REQUEST['finder_vid'];
        $plat_order_id = $_POST['order_id'];
        $oldProductBns = $_POST['oldPbn']; //失败的货号
        $newProductBns = $_POST['pbn']; //替换的新货号
        
        //check
        if(empty($oldProductBns)) {
            $this->splash('error', $url, '存在原始货号为空的情况不允许批量修改！');
        }
        
        foreach ($oldProductBns as $bn)
        {
            if (empty($bn)) {
                $this->splash('error', $url, '货号为空,不允许批量修改！');
                break;
            }
        }
        
        //获取所有同样的失败订单
        $sql = "SELECT I.plat_order_id FROM sdb_dealer_platform_order_objects AS I LEFT JOIN sdb_dealer_platform_orders AS O ON I.plat_order_id=O.plat_order_id ";
        $sql .= "WHERE O.is_fail='true' AND O.edit_status='true' AND O.status='active' AND I.bn IN ('". implode("','", $oldProductBns)  ."') GROUP BY plat_order_id";
        $failOrderList = $this->_mdl->db->select($sql);
        
        //处理拥有相同旧货号明细大于1，且新货号明细货号又不同的情况
        $arr = array_combine($newProductBns, $oldProductBns);
        
        //过滤键为空
        unset($arr['']);
        
        $arr_count_values = array_count_values($arr);
        foreach ($arr_count_values as $key => $count)
        {
            if ($key && $count > 1) {
                $this->splash('error', $url, '相同旧货号对应不同新货号，不允许批量修改!');
                break;
            }
        }
        
        //重置订单号，并得到队列的sdf数据
        $count = 0;
        $limit = 10;
        $page = 0;
        $orderSdfs = array();
        $type = 'bn';
        foreach ($failOrderList as $order)
        {
            if ($count < $limit) {
                $count++;
            } else {
                $count = 0;
                $page++;
            }
            
            $orderSdfs[$page]['orderIds'][] = $order['plat_order_id'];
            $orderSdfs[$page]['oldProductBns'] = $oldProductBns;
            $orderSdfs[$page]['newProductBns'] = $newProductBns;
        }
        
        //check
        if(empty($orderSdfs)){
            $this->splash('error', $url, '没有找到需要修正的订单！');
        }
        
        //queue
        foreach ($orderSdfs as $page => $items)
        {
            $queueData = array(
                'queue_title' => '异常订单批量修正',
                'start_time' => time(),
                'params' => array(
                    'sdfdata' => $items,
                    'opinfo' => $opinfo,
                    'app' => 'dealer',
                    'ctl' => 'admin_platform_orders',
                ),
                'worker' => 'dealer_platform_order_fail.batchModifyOrder',
            );
            $queueObj->save($queueData);
        }
        $queueObj->flush();
        
        $this->splash('success', $url, '批量修改请求已提交!');
    }
    
    /**
     * 订单拦截
     *
     * @param $order_id
     * @return void
     */
    public function do_cancel($plat_order_id)
    {
        $logMdl = app::get('ome')->model('operation_log');
        
        //平台订单信息
        $filter = array('plat_order_id'=>$plat_order_id);
        $orderInfo = $this->_orderLib->getOrderDetail($filter);
        
        //check
        if(empty($orderInfo) || empty($plat_order_id)){
            die('订单信息不存在');
        }
        
        //post
        if ($_POST) {
            $memo = '订单被拦截取消：'. $_POST['memo'];
            
            //cancel
            $cancelRs = $this->_orderLib->canceldealerOrder($orderInfo);
            if($cancelRs['rsp'] == 'succ'){
                //logs
                $logMdl->write_log('order_modify@dealer', $plat_order_id, '拦截取消订单成功：'. $memo);
                
                echo "<script>alert('拦截取消订单成功');</script>";
            }else{
                //logs
                $logMdl->write_log('order_modify@dealer', $plat_order_id, '拦截取消订单失败');
                
                echo "<script>alert('拦截取消订单失败：". $cancelRs['error_msg'] .");</script>";
                echo "<script>window.finderGroup[$(document.body).getElement('input[name^=_finder\[finder_id\]]').value].refresh();$$('.dialog').getLast().retrieve('instance').close();</script>";
            }
        }
        
        $this->pagedata['order'] = $orderInfo;
        $this->display("admin/order/detail_cancel.html");
    }
}