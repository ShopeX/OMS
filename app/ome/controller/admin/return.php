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

class ome_ctl_admin_return extends desktop_controller
{
    public $name       = "售后服务";
    public $workground = "aftersale_center";

    public function index($is_jingxiao = false)
    {
        $action = array(array(
            'label'  => '新建售后服务',
            'href'   => 'index.php?app=ome&ctl=admin_return&act=add_return',
        ));
        
        switch (intval($_GET['view'])) {
            case '0':
            case '1':
            case '2':
                $action[] = array(
                    'label'  => '批量同意退货',
                    'submit' => 'index.php?app=ome&ctl=admin_return&act=batch_syncUpdate&status_type=agree',
                    'target' => "dialog::{width:700,height:490,title:'批量同意退货'}",
                );
                break;
            default:
                break;
        }
        $base_filter = array('is_fail' => 'false');

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $base_filter['org_id'] = $organization_permissions;
        }

        $params = array(
            'title'                  => '售后服务',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => true,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'actions'                => $action,
            'base_filter'            => $base_filter,
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
        );

        # 权限判定
        if (!$this->user->is_super()) {
            $returnLib = kernel::single('ome_return');
            foreach ($params['actions'] as $key => $action) {
                $url = parse_url($action['href']);
                parse_str($url['query'], $url_params);
                $has_permission = $returnLib->chkground($this->workground, $url_params);
                if (!$has_permission) {
                    unset($params['actions'][$key]);
                }
            }
        }

        if ($is_jingxiao) {
            $params['actions'] = [];
            $params['base_filter']['delivery_mode'] = 'jingxiao';
            $params['use_buildin_export'] = false;
            $params['title'] = '平台自发售后服务';
        } else {
            if (!$params['base_filter']['filter_sql']) {
                $params['base_filter']['filter_sql'] = '1';
            }
            $params['base_filter']['filter_sql'] .= ' AND delivery_mode <> "jingxiao"';
        }
        //售后申请表
        $this->finder('ome_mdl_return_product', $params);
    }

    public function jingxiao()
    {
        $is_jingxiao = true;
        return $this->index($is_jingxiao);
    }

    public function _views()
    {
        if ($_GET['act'] == 'return_io') {
            return [];
        }

        $mdl_return_product = $this->app->model('return_product');
        $sub_menu           = array(
            0 => array('label' => __('全部'), 'optional' => false, 'filter' => array('is_fail' => 'false')),
            1 => array('label' => __('未处理'), 'filter' => array('status' => '1', 'is_fail' => 'false'), 'optional' => false),
            2 => array('label' => __('审核中'), 'filter' => array('status' => '2', 'is_fail' => 'false'), 'optional' => false),
            3 => array('label' => __('接受申请'), 'filter' => array('status' => '3', 'is_fail' => 'false'), 'optional' => false),
            4 => array('label' => __('完成'), 'filter' => array('status' => '4', 'is_fail' => 'false'), 'optional' => false),
            5 => array('label' => __('拒绝'), 'filter' => array('status' => '5', 'is_fail' => 'false'), 'optional' => false),
            6 => array('label' => __('已收货'), 'filter' => array('status' => '6', 'is_fail' => 'false'), 'optional' => false),
            7 => array('label' => __('已质检'), 'filter' => array('status' => '7', 'is_fail' => 'false'), 'optional' => false),
            8 => array('label' => __('补差价'), 'filter' => array('status' => '8', 'is_fail' => 'false'), 'optional' => false),
            9 => array('label' => __('已拒绝退款'), 'filter' => array('status' => '9', 'is_fail' => 'false'), 'optional' => false),
            10 => array('label' => __('卖家拒绝退款'), 'filter' => array('platform_status'=>'SELLER_REFUSE_BUYER'), 'optional' => false),
        );

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();

        $act = 'index';
        $i = 0;
        foreach ($sub_menu as $k => $v) {
            if ($organization_permissions) {
                $v['filter']['org_id'] = $organization_permissions;
            }

            if ($_GET['act'] == 'jingxiao') {
                $act = $_GET['act'];
                $v['filter']['delivery_mode'] = $act;
            } else {
                if (!$v['filter']['filter_sql']) {
                    $v['filter']['filter_sql'] = '1';
                }
                $v['filter']['filter_sql'] .= ' AND delivery_mode <> "jingxiao"';
            }

            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $mdl_return_product->viewcount($v['filter']);
            $sub_menu[$k]['href']   = 'index.php?app=ome&ctl=admin_return&act=' . $act . '&view=' . $i++;
        }
        return $sub_menu;
    }

    //编辑弹窗触发
    public function edit($return_id)
    {
        if (!intval($return_id)) {
            die('单据号传入错误');
        }

        $return_info = $this->app->model('return_product')->dump(array('return_id' => $return_id), 'order_id');
        $this->apply($return_info['order_id'], $return_id, 'edit');
    }

    /*
     * 对当前售后申请各状态进行保存
     * @param Int $status 申请售后状态
     */
    public function save($status)
    {
        $oProduct           = $this->app->model('return_product');
        $oOrder             = $this->app->model('orders');
        $_POST['return_id'] = (int) $_POST['return_id'];
        
        $this->begin();
        
        $return_id   = $_POST['return_id'];
        $address_id = intval($_POST['address_id']); //平台退货地址ID
        
        $bn_array    = array();
        $oPro_detail = $oProduct->dump($return_id, 'status,order_id,shop_id,source,attachment,shop_type');
        
        //如果是本地订单不需要根据平台去读取扩展
        $source     = $oPro_detail['source'];
        $archiveLib = kernel::single('archive_order');
        if ($archiveLib->is_archive($source)) {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $order_detail   = $archive_ordObj->getOrders(array('order_id' => $oPro_detail['order_id']), 'ship_status,shop_type');
        } else {
            $order_detail = $oOrder->dump($oPro_detail['order_id'], 'ship_status,shop_type');
        }
        if ($status != '5') {
            //当提交状态为拒绝时不判断 判断订单是否已经全部退货
            if ($order_detail['ship_status'] == '4') {
                $this->end(false, app::get('base')->_('订单已全部退货,请拒绝此售后申请!'));
            }
        }
        $_POST['shop_id'] = $oPro_detail['shop_id'];
        
        if ($status == 2 || $status == 3) {
            $adata    = $_POST;
            
            //平台退货地址ID
            if($address_id){
                $adata['address_id'] = $address_id;
            }
            
            //售后---审核出现两次,begin
            $now_status = $oProduct->getList('status,delivery_id', array('return_id' => $return_id));
            if (empty($now_status[0]['delivery_id'])) {
                $this->end(false, app::get('base')->_('收货地址未选择，请先进入编辑界面选择'));
            }
            if ($now_status[0]['status'] != 3) {
                //预先做判断看是否可以生成
                $choose_type = $adata['choose_type'];
                if (in_array($choose_type, array('1', '2'))) {
                    $choose_flag    = true;
                    $Oproduct_items = $this->app->model('return_product_items');
                    $reship_items   = $this->app->model('reship_items');
                    $pro_items      = $Oproduct_items->getList('*', array('return_id' => $return_id, 'disabled' => 'false'));
                    foreach ($pro_items as $k => $v) {
                        $apply_num = $v['num'];
                        $bn        = $v['bn'];
                        if (in_array($source, array('archive'))) {
                            $effective = $archive_ordObj->Get_refund_count($oPro_detail['order_id'], $bn);
                        } else {
                            $effective = $reship_items->Get_refund_count($oPro_detail['order_id'], $bn, '', $v['order_item_id']);
                        }
                        if ($effective <= 0) {
                            $choose_flag = false;
                            break;
                        }
                    }
                    if (!$choose_flag) {
                        $this->end(false, app::get('base')->_('货品剩余数量不足,不可以申请!'));
                    }
                }
                
                //更新售后申请单,并且创建退换货单
                $error_msg = '';
                $result = $oProduct->tosave($adata, true, $error_msg);
                if($result === false){

                    $this->end(false, app::get('base')->_('售后申请单接受申请失败('. $error_msg .')!'));
                }
                
                //更新退换货单上最后合计金额totalmoney
                if ($adata['status'] == 3 && $adata['choose_type_flag']) {
                    kernel::single('ome_return_rchange')->update_totalmoney($return_id);
                }
            }
            //售后---审核出现两次,end
        } else {
            if ($status == 5 && $order_detail['shop_type'] == 'youzan') {
                //$this->end(false, app::get('base')->_('有赞平台商家拒绝退货，仅支持 “买家已经退货，等待卖家确认收货 ”的场景'));
            }
            
            //[京东一件代发]抖单平台需要拒绝图片
            if($status == 5 && $order_detail['shop_type']=='luban')
            {
                $fileObj = app::get('base')->model('files');
                
                //获取拒绝退货图片(抖单平台需要拒绝图片)
                $file_url = '';
                if(is_numeric($oPro_detail['attachment']))
                {
                    $fileLib = kernel::single('base_storager');
                    $file_url = $fileLib->getUrl($oPro_detail['attachment']);
                }
                else
                {
                    $tempData = explode('|', $oPro_detail['attachment']);
                    $file_url = $tempData[0];
                }
                
                //没有上传退货图片,不允许拒绝退货
                if(empty($file_url))
                {
                    $this->end(false, app::get('base')->_('抖单平台需要拒绝图片，请先编辑单据，上传拒绝图片!'));
                }
            }
            
            $data = array('return_id' => $return_id, 'status' => $_POST['status'], 'last_modified' => time());
            
            //平台退货地址ID
            if($address_id){
                $data['address_id'] = $address_id;
            }
            
            $oProduct->save($data);
            $memo = $oProduct->schema['columns']['status']['type'][$_POST['status']];
            $this->app->model('operation_log')->write_log('return@ome', $return_id, '售后服务:' . $memo);

            // 售后申请拒绝：淘宝/天猫极速换货需取消换出订单并回告平台
            $refuseShopType = (string)$oPro_detail['shop_type'];
            if (((string)$status === '5' || (string)$_POST['status'] === '5') && in_array($refuseShopType, array('taobao', 'tmall'), true)) {
                kernel::single('ome_order_platform_taobao_aftersale')->notifyConsigngoodsRefuseOnReturnRefuse(
                    $return_id,
                    '售后服务:' . $memo
                );
            }
        }
        
        //平台对保存前的扩展
        if ($source == 'matrix') {
            $pre_result = kernel::single('ome_aftersale_service')->pre_save_return($_POST);
            if ($pre_result['rsp'] == 'fail') {
                $this->end(false, $pre_result['msg']);
            }
        }
        $this->end(true, app::get('base')->_('操作成功'));
    }

    //新建售后服务弹出页
    public function add_return()
    {
        $source                          = trim($_GET['source']);
        $this->pagedata['source']        = $source;
        $this->pagedata['search_filter'] = array('ship_name' => '收货人', 'ship_tel' => '收货人电话', 'ship_mobile' => '收货人手机', 'order_bn' => '订单号');
        $this->display("admin/return_product/add_return.html");
    }

    //新增或者编辑售后服务申请 弹窗展示
    public function apply($order_id, $return_id = '', $act = 'add')
    {
        $orderItemMdl = app::get('ome')->model('order_items');
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $archiveLib          = kernel::single('archive_order');
        $archive_ordObj      = kernel::single('archive_interface_orders');
        $order_id            = intval($order_id);
        if (!$order_id) {
            die('订单传入错误');
        }
        
        $oReship_item = $this->app->model('reship_items');
        $oProduct     = $this->app->model('return_product');
        
        $product    = $oProduct->dump(array('return_id' => $return_id));
        $source = trim($_GET['source']);
        
        //是否归档售后单
        if(empty($source)){
            $source = ($product['archive'] ? 'archive' : '');
        }
        
        if (!is_numeric($product['attachment'])) {
            $this->pagedata['attachment_type'] = 'remote';
            $attachment                        = explode("|", $product['attachment']);
            if ($attachment[0] != '') {
                $product['attachment'] = $attachment;
            }
        }
        
        $archiveLib = kernel::single('archive_order');
        if (($source && $archiveLib->is_archive($source)) || ($product && $archiveLib->is_archive($product['source']))) {
            $order_items = $archive_ordObj->getItemList($order_id);
            $items       = $archive_ordObj->getItemList($order_id);
            $orderInfo   = $archive_ordObj->getOrders(['order_id'=>$order_id]);
        } else {
            $oOrders_item = $this->app->model('order_items');
            $order_items  = $oOrders_item->getList('*', array('order_id' => $order_id, 'delete' => 'false'));
            $items        = $oOrders_item->getList('*', array('order_id' => $order_id));
            $orderInfo    = $this->app->model('orders')->db_dump(['order_id'=>$order_id],'*');
        }
        
        //obj_type
        $objTypeList = $orderItemMdl->_obj_alias;
        
        //获取订单obj层信息
        $orderItemList = array();
        $orderItemIds = array_column($items, 'item_id');
        if($orderItemIds){
            $orderLib = kernel::single('ome_order');
            $orderItemList = $orderLib->getOrderItemByItemIds($orderItemIds);
        }
        
        $returnBranchId   = '';
        $returnBranchIds  = [];
        $returnBranchList = [];
    
        $return_auto_branch    = app::get('ome')->getConf('return.auto_branch');// 获取默认退货仓配置
        $orderShopBranchConfig = app::get('ome')->getConf('return.auto_shop_branch');// 获取店铺级别的默认退货仓配置
    
        // 如果有默认退货仓配置
        if ($return_auto_branch) {
            $returnBranchId    = $return_auto_branch;
            $returnBranchIds[] = $returnBranchId;
        }
    
        // 如果有店铺级别的退货仓配置
        if (isset($orderShopBranchConfig[$orderInfo['shop_id']])) {
            $returnBranchId    = $orderShopBranchConfig[$orderInfo['shop_id']];
            $returnBranchIds[] = $returnBranchId;
        }
    
        // 如果有有效的退货仓ID列表，则获取退货仓列表
        if (!empty($returnBranchIds)) {
            $returnBranchList = app::get('ome')->model('branch')->getList('name as branch_name, branch_id', ['branch_id' => $returnBranchIds]);
        }
        
        $lucky_flag = false;
        $newItems = array();
        if ($act == 'edit') {
            //编辑展示
            $oProduct_items = $this->app->model('return_product_items');
            $items          = $oProduct_items->getList('*', array('return_id' => $return_id));
            $sendnum_list   = array();
            foreach ($order_items as $oitems) {
                $sendnum_list[$oitems['bn']] = $oitems;
            }
            foreach ($items as $k => $v) {
                $obj_type = $v['obj_type'];
                $order_item_id = $v['order_item_id'];
                
                $spec_info              = $basicMaterialExtObj->dump(array('bm_id' => $v['product_id']), '*');
                $items[$k]['spec_info'] = $spec_info['specifications'];
                //if($newItems[$v['bn']] && $newItems[$v['bn']]['bn'] !=''){
                //$newItems[$v['bn']]['nums'] += $items[$k]['nums'];
                // $newItems[$v['bn']]['sendnum'] += $items[$k]['sendnum'];
                //$newItems[$v['bn']]['price'] = $order_items[$k]['price'];
                //$newItems[$v['bn']]['sale_price'] = $order_items[$k]['sale_price'];
                //}else{
                if (in_array($product['source'], array('archive'))) {
                    $refund              = $archive_ordObj->Get_refund_count($order_id, $v['bn']);
                    $items[$k]['branch'] = $archive_ordObj->getBranchCodeByBnAndOd($v['bn'], $order_id);
                } else {
                    $refund              = $oReship_item->Get_refund_count($order_id, $v['bn'], '', $v['order_item_id']);
                    $items[$k]['branch'] = $oReship_item->getBranchCodeByBnAndOd($v['bn'], $order_id);
                }
                $items[$k]['effective']  = $refund;
                $items[$k]['sendnum']    = $sendnum_list[$v['bn']]['sendnum'];
                $items[$k]['price']      = $sendnum_list[$v['bn']]['price'];
                $items[$k]['sale_price'] = $sendnum_list[$v['bn']]['sale_price'];
                $items[$k]['nums']       = $sendnum_list[$v['bn']]['nums'];
                
                //销售物料类型名称
                $items[$k]['obj_type_name'] = ($obj_type ? $objTypeList[$obj_type] : '');
                
                //关联的订单object层信息
                if(isset($orderItemList[$order_item_id])){
                    $orderItemInfo = $orderItemList[$order_item_id];
                    
                    $items[$k]['sales_material_bn'] = $orderItemInfo['sales_material_bn'];
                    
                    //福袋组合编码
                    if($orderItemInfo['combine_bn']){
                        $lucky_flag = true;
                        $items[$k]['combine_bn'] = $orderItemInfo['combine_bn'];
                    }
                }
                
                $newItems[]              = $items[$k];
                // }
            }
            #扩展页面
            $plugin_html = kernel::single('ome_aftersale_service')->pre_return_product_edit($product);
            if (is_array($plugin_html) && isset($plugin_html['rsp'])) {
                if($plugin_html['rsp'] != 'fail'){
                    $this->pagedata['plugin_html_show'] = $plugin_html;
                }
            }
        } else {
            //新增展示
            foreach ($items as $k => $v) {
                $item_type = $v['item_type'];
                $order_item_id = $v['item_id'];
                
                $spec_info              = $basicMaterialExtObj->dump(array('bm_id' => $v['product_id']), '*');
                $items[$k]['spec_info'] = $spec_info['specifications'];
                /*去除订单明细ID*/

                //if($newItems[$v['bn']] && $newItems[$v['bn']]['bn'] !=''){
                //$newItems[$v['bn']]['nums'] += $items[$k]['nums'];
                //$newItems[$v['bn']]['sendnum'] += $items[$k]['sendnum'];
                //}else{
                if ($source && in_array($source, array('archive'))) {
                    $refund              = $archive_ordObj->Get_refund_count($order_id, $v['bn']);
                    $items[$k]['branch'] = $archive_ordObj->getBranchCodeByBnAndOd($v['bn'], $order_id);
                } else {
                    $refund              = $oReship_item->Get_refund_count($order_id, $v['bn'], '', $v['item_id']);
                    $items[$k]['branch'] = $oReship_item->getBranchCodeByBnAndOd($v['bn'], $order_id);
                }
                $items[$k]['effective']     = $refund;
                $items[$k]['order_item_id'] = $v['item_id'];
                
                //销售物料类型名称
                $items[$k]['obj_type_name'] = ($item_type ? $objTypeList[$item_type] : '');
                
                //关联的订单object层信息
                if(isset($orderItemList[$order_item_id])){
                    $orderItemInfo = $orderItemList[$order_item_id];
                    
                    $items[$k]['sales_material_bn'] = $orderItemInfo['sales_material_bn'];
                    
                    //福袋组合编码
                    if($orderItemInfo['combine_bn']){
                        $lucky_flag = true;
                        $items[$k]['combine_bn'] = $orderItemInfo['combine_bn'];
                    }
                }
                
                unset($items[$k]['item_id']);
                $newItems[] = $items[$k];
                //}
                unset($v['item_id']);
            }
        }
        $items = $newItems;

        //获取仓库模式
        $branch_mode                   = app::get('ome')->getConf('ome.branch.mode');
        $this->pagedata['branch_mode'] = $branch_mode;
        $this->pagedata['items']       = $items;
        $this->pagedata['lucky_flag'] = $lucky_flag;
        $this->pagedata['order_id']    = $order_id;
        $this->pagedata['act']         = $act;
        $this->pagedata['finder_id']   = $_GET['finder_id'] ? $_GET['finder_id'] : $_POST['finder_id'];

        if ($product['delivery_id']) {
            if (in_array($product['source'], array('archive'))) {
                $archive_delObj = kernel::single('archive_interface_delivery');
                $deli_info      = $archive_delObj->getDelivery(array('delivery_id' => $product['delivery_id']), 'delivery_bn,ship_name,ship_area,ship_addr');
            } else {
                $Odelivery = $this->app->model('delivery');
                $deli_info = $Odelivery->dump(array('delivery_id' => $product['delivery_id']), 'delivery_bn,ship_name,ship_area,ship_addr,shop_type');
            }
            list($package, $region_name, $region_id) = explode(':', $deli_info['consignee']['area']);
            if ($region_name) {
                $deli_info['consignee']['area'] = str_replace('/', '-', $region_name);
            }
            $product = array_merge($product, $deli_info);
        }

        $this->pagedata['source']  = $source;
        $this->pagedata['product'] = $product;
        $this->pagedata['returnBranchId']   = $returnBranchId;
        $this->pagedata['returnBranchList'] = $returnBranchList;
        $this->display("admin/return_product/return_apply.html");
    }

    //售后申请执行新增、编辑操作
    public function apply_add()
    {
        $this->begin('index.php?app=ome&ctl=admin_return&act=index');
        $url         = $_POST['url'];
        $oProduct    = $this->app->model('return_product');
        $oItems      = $this->app->model('return_product_items');
        $oOrder      = $this->app->model('orders');
        $oOrder_item = $this->app->model('order_items');

        $order_id       = $_POST['order_id'];
        $order          = $oOrder->dump($order_id, 'member_id,shop_id,org_id');
        $oShop          = $this->app->model('shop');
        $shop_info      = $oShop->getShopInfo($order['shop_id']);
        $shop_type      = $shop_info['shop_type'];
        $source         = $_POST['source'];
        
        //归档订单加载类
        if ($source && in_array($source,array('archive'))) {
            $oOrder = app::get('archive')->model('orders');
            $oOrder_item = app::get('archive')->model('order_items');
        }
        
        $delivery_id    = $_POST['delivery_id'];
        $title          = $_POST['title'];
        $archive_ordObj = kernel::single('archive_interface_orders');
        if ($title == '') {
            $this->end(false, '售后标题不可为空!');
        } else if (empty($_POST['goods_bn'])) {
            $this->end(false, '您没有选择商品!');
        } else if ($delivery_id == '') {
            $this->end(false, '请为此售后服务选择收货人信息!');
        }
        $return_branch_id = isset($_POST['return_branch_id']) ? $_POST['return_branch_id'] : '';//系统配置售后仓

        $apply_money = 0;
        $items       = array();
        foreach ($_POST['goods_bn'] as $key => $val) {

            $product_id = intval($_POST['product_id'][$val]);

            //获取订单货品信息
            $itemInfo = $oOrder_item->dump(array('order_id' => $_POST['order_id'], 'product_id' => $product_id, 'item_id' => $val), 'price,sale_price,nums,divide_order_fee');
            if (empty($itemInfo)) {
                $this->end(false, '货号：' . $val . ' 在订单明细中没有找到!');
            }

            //订单有实付金额时,货品退款金额使用实付金额
            if ($itemInfo['divide_order_fee'] && $itemInfo['divide_order_fee'] > 0) {
                $sale_price = $itemInfo['divide_order_fee']; //实付金额
            } else {
                $sale_price = $itemInfo['sale_price']; //货品销售总价
            }

            $item_num = ($itemInfo['quantity'] ? $itemInfo['quantity'] : $itemInfo['nums']);//货品销售数量(归档是nums)

            //售后申请数量及价格
            $apply_num   = intval($_POST['num'][$val]); //售后申请数量
            $apply_price = $sale_price / $item_num; //申请退换货单价 = 货品销售总价 / 货品销售数量;

            //售后合计申请金额
            $apply_money += ($apply_price * $apply_num);

            $item               = array();
            $item['bn']         = $_POST['bn'][$val];
            $item['item_id']    = $_POST['item_id'][$val];
            $item['name']       = $_POST['goods_name'][$val];
            $item['product_id'] = $product_id;

            $item['num']   = $apply_num;
            $item['price'] = $apply_price;

            $_POST['effective'][$val] = intval($_POST['effective'][$val]);

            if ($apply_num <= 0) {
                $this->end(false, '申请数量不可以为小于1的整数!');
            }
            if ($_POST['effective'][$val] <= 0) {
                $this->end(false, '剩余数量不足，不可以操作!');

            } else if ($apply_num > $_POST['effective'][$val]) {
                $this->end(false, '申请数量大于剩余数量，不可以操作!');
            }

            $val       = str_replace(" ", "_", $val);
            $branch_id = $return_branch_id ?: $_POST['branch_id' . $val]; //fix by danny 2012-5-18
            if (empty($branch_id)) {
                $this->end(false, '货品仓库不能为空!');
            }
            if ($source && in_array($source, array('archive'))) {
                $branch_num = $archive_ordObj->Get_delivery($branch_id, $val, $order_id);
            } else {
                $branch_num = $oProduct->Get_delivery($branch_id, $val, $order_id);
            }

            if ($apply_num > $branch_num) {
                $this->end(false, '所选仓库数量不足!');
            }

            $item['branch_id']     = $branch_id;
            $item['order_item_id'] = $val;
            $items[]               = $item;
        }
        $upload_file = "";
        if ($_FILES['attachment']['size'] != 0) {
            if ($_FILES['attachment']['size'] > 314572800) {
                $this->end(false, '上传文件不能超过300M!');
            }
            $type = array("jpg", "gif", "bmp", "jpeg", "rar", "zip");
            if ($_FILES['attachment']['name']) {
                if (!in_array(strtolower(substr(strrchr($_FILES['attachment']['name'], '.'), 1)), $type)) {
                    $text = implode(",", $type);
                    $this->end(false, "您只能上传以下类型文件{$text}!");
                }
            }

            $ss                  = kernel::single('base_storager');
            $id                  = $ss->save_upload($_FILES['attachment'], "file", "", $msg); //返回file_id;
            $upload_file         = $id;
            $aData['attachment'] = $upload_file;
        }
        $aData['order_id'] = $order_id;

        //合计申请金额
        $aData['money'] = $apply_money;

        $aData['title']     = $_POST['title'];
        $aData['add_time']  = time();
        $aData['member_id'] = $order['member_id']; //申请人
        $aData['content']   = $_POST['content'];
        $aData['memo']      = $_POST['memo'];
        $aData['status']    = 1;
        $aData['shop_id']   = $order['shop_id']; //店铺id
        $aData['shop_type'] = $shop_type; //店铺类型
        $opInfo             = kernel::single('ome_func')->getDesktopUser();
        $aData['op_id']     = $opInfo['op_id'];
        if ($source && in_array($source, array('archive'))) {
            $aData['source']  = $source;
            $aData['archive'] = '1';
        }
        $aData['delivery_id'] = $_POST['delivery_id'];

        // 经销店铺的单据，delivery_mode冗余到售后申请表
        if ($shop_info['delivery_mode'] == 'jingxiao') {
            $aData['delivery_mode'] = $shop_info['delivery_mode'];
        }

        if ($_POST['return_id']) {
            $aData['return_bn'] = $_POST['return_bn'];
            $aData['return_id'] = $_POST['return_id'];
            $add_operation      = '修改';
            $method             = 'update_status';
        } else {
            $return_bn          = $oProduct->gen_id();
            $aData['return_bn'] = $return_bn;
            $aData['org_id']    = $order['org_id'];
            $add_operation      = '创建';
            $method             = 'add_aftersale';
        }

        $oProduct->save($aData);
        $oItems->update(array('disabled' => 'true'), array('return_id' => $aData['return_id']));

        foreach ($items as $k => $v) {
            $v['return_id'] = $aData['return_id'];
            $v['disabled']  = 'false';
            $oItems->save($v);
        }

        //日志记录
        $oOperation_log = $this->app->model('operation_log');
        $memo           = $add_operation . '售后服务';
        $oOperation_log->write_log('return@ome', $aData['return_id'], $memo);

        //售后申请 API
        foreach (kernel::servicelist('service.aftersale') as $object => $instance) {
            if (method_exists($instance, $method)) {
                $instance->$method($aData['return_id']);
            }
        }

        #售后操作
        kernel::single('ome_aftersale_service')->return_product_edit_after($_POST);

        $this->end(true, "售后服务{$add_operation}成功!");
    }

    //ajax 根据仓库ID，货号订单号获取发货单号以及对应收货相关信息
    public function check()
    {
        $branch_id = $_GET['branch_id'];
        $bn        = $_GET['bn'];
        $order_id  = $_GET['order_id'];
        $source    = $_GET['source'];
        if ($source && in_array($source, array('archive'))) {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $result         = $archive_ordObj->Get_delivery($branch_id, $bn, $order_id);
        } else {
            $oProduct = $this->app->model('return_product');
            $result   = $oProduct->Get_delivery($branch_id, $bn, $order_id);
        }
        $result['ship_addr'] = kernel::single('base_view_helper')->modifier_cut($result['ship_addr'],-1,'*',false,true);
        $result['ship_name'] = kernel::single('base_view_helper')->modifier_cut($result['ship_name'],-1,'*',false,true);
    
        echo json_encode($result);
    }

    //文件下载
    public function file_download2($return_id)
    {
        $oProduct = $this->app->model('return_product');
        $info     = $oProduct->dump($return_id);
        $filename = $info['attachment'];
        if (is_numeric($filename)) {
            $ss = kernel::single('base_storager');
            $a  = $ss->getUrl($filename, "file");
            $oProduct->file_download($a);
        } else {
            header('Location:' . $filename);
        }
    }

    /**
     * 展开明细 接受申请 选择操作类型
     * 1.退货单，2.换货单，3.退款申请单
     **/
    public function choose_type($return_id, $status)
    {
        if (!$return_id) {
            die("单据号传递错误！");
        }

        //根据类型转化是否继续，否则保存当前状态
        $Oreturn = $this->app->model('return_product');
        $reship = $Oreturn->dump(array('return_id' => $return_id), '*');
        $shop_id = $reship['shop_id'];
        $return_type = $reship['return_type'];
        
        $choose_type_flag  = 0;
        $choose_type_value = 0;
        if ($reship['source'] == 'matrix') {
            $router = kernel::single('ome_aftersale_request');
            if (!$router->setShopId($shop_id)->choose_type()) {
                $choose_type_flag = 1;
            }
            $choose_type_value = $router->setShopId($shop_id)->choose_type_value($return_id);
        }
        
        //判断类型
        $bbcObj = app::get('ome')->model('return_product_bbc');

        $bbc_detail  = $bbcObj->dump(array('return_bn' => $reship['return_bn'], 'shop_id' => $shop_id), 'return_type');
        if($bbc_detail){
            $return_type = $bbc_detail['return_type'];
        }
        
        // 根据天猫类型来定义
        $tmall_detail = kernel::single('ome_service_aftersale')->get_return_type($reship);

        if ($tmall_detail) {
            $return_type = $tmall_detail['refund_type'];
        }
        
        //获取退货地址列表
        $addressObj = app::get('ome')->model('return_address');
        $addressList = $addressObj->getList('*', array('shop_id'=>$shop_id, 'reship_id'=>0), 0, 100, 'cancel_def ASC');
        $this->pagedata['addressList'] = $addressList;
        
        $this->pagedata['return_type'] = $return_type;
        $this->pagedata['reason'] = app::get('ome')->model('refund_reason')->getList('reason',[]);

        $this->pagedata['choose_type_value'] = $choose_type_value;
        $this->pagedata['choose_type_flag']  = $choose_type_flag;
        $this->pagedata['return_id']         = $return_id;
        $this->pagedata['return_bn']         = $reship['return_bn'];
        $this->pagedata['finder_id']         = $_GET['finder_iid'];
        $this->pagedata['status']            = $status;
        $this->pagedata['is_edit']           = 'false';
        
        //平台退货地址ID
        $this->pagedata['select_address_id'] = $reship['address_id'];
        
        $this->getRefundinfo($reship['order_id']);
        
        //售后服务类型
        $server_type = array('1' => '退货单', '2' => '换货单', '3' => '退款申请单');
        if ($this->pagedata['order']['pay_status'] == '5') {
            unset($server_type['2'], $server_type['3']); //全额退款的订单只允许申请退货单
        }
        $this->pagedata['server_type'] = $server_type;
        $this->display('admin/return_product/choose_type.html');
    }

    public function getRefundinfo($orderid)
    {
        //判断是否为失败订单
        $api_failObj = $this->app->model('api_fail');
        $api_fail    = $api_failObj->dump(array('order_id' => $orderid, 'type' => 'payment'));
        if ($api_fail) {
            $api_fail_flag = 'true';
        } else {
            $api_fail_flag = 'false';
        }
        $this->pagedata['api_fail_flag'] = $api_fail_flag;

        $this->pagedata['orderid'] = $orderid;
        $objOrder                  = $this->app->model('orders');
        $aORet                     = $objOrder->order_detail($orderid);

        $aORet['cur_name'] = 'CNY';
        $aORet['cur_sign'] = 'CNY';

        $oPayment       = $this->app->model('payments');
        $payment_cfgObj = app::get('ome')->model('payment_cfg');
        $oShop          = $this->app->model('shop');
        $c2c_shop       = ome_shop_type::shop_list();
        $shop_id        = $aORet['shop_id'];
        $shop_detail    = $oShop->dump($shop_id, 'node_type,node_id');
        if ($shop_id && !in_array($shop_detail['node_type'], $c2c_shop)) {
            $payment = kernel::single('ome_payment_type')->paymethod($shop_id);
        } else {
            $payment = $oPayment->getMethods();
        }

        $payment_cfg = $payment_cfgObj->dump(array('pay_bn' => $aORet['pay_bn']), 'id,pay_type');

        $this->pagedata['shop_id']    = $shop_id;
        $this->pagedata['node_id']    = $shop_detail['node_id'];
        $this->pagedata['payment']    = $payment;
        $this->pagedata['payment_id'] = $payment_cfg['id'];
        $this->pagedata['pay_type']   = $payment_cfg['pay_type'];
        if ($payment_cfg['id']) {
            $order_paymentcfg = kernel::single('ome_payment_type')->paymethod($shop_id, $payment_cfg['pay_type']);
        }
        $this->pagedata['order_paymentcfg'] = $order_paymentcfg;
        $this->pagedata['op_name']          = 'admin';
        $this->pagedata['typeList']         = ome_payment_type::pay_type();

        if ($aORet['member_id'] > 0) {
            $objMember                = $this->app->model('members');
            $aRet                     = $objMember->member_detail($aORet['member_id']);
            $this->pagedata['member'] = $aRet;
        } else {
            $this->pagedata['member'] = array();
        }
        $this->pagedata['order'] = $aORet;

        $aRet     = $oPayment->getAccount();
        $aAccount = array('--使用已存在帐户--');
        foreach ($aRet as $v) {
            $aAccount[$v['bank'] . "-" . $v['account']] = $v['bank'] . " - " . $v['account'];
        }
        $this->pagedata['pay_account'] = $aAccount;
        //剩余支付金额
        $pay_money                   = kernel::single('eccommon_math')->number_minus(array($aORet['total_amount'], $aORet['payed']));
        $this->pagedata['pay_money'] = $pay_money;
        $this->pagedata['aItems']    = $objOrder->getItemList($orderid);

    }

    //构造一个已发货订单列表供选择（新建售后申请和新建退换货，两处获取订单列表）
    public function getOrders()
    {
        $op_id       = kernel::single('desktop_user')->get_id();
        $this->title = '订单查看';
        $source      = trim($_GET['source']);
        if (in_array($source, array('archive'))) {
            //已发货、部分退货 并且 支付方式为已付款、部分退款、全额退款
            $base_filter = array('disabled' => 'false', 'is_fail' => 'false', 'ship_status' => array('1', '3'), 'pay_status' => array('1', '4', '5','6'));

            //check shop permission
            // $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
            // if ($organization_permissions) {
            //     $base_filter['org_id'] = $organization_permissions;
            // }

            $params = array(
                'title'                  => $this->title,
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'    => false,
                'use_buildin_recycle'    => false,
                'use_buildin_export'     => false,
                'use_buildin_import'     => false,
                'use_buildin_filter'     => true,
                'use_view_tab'           => false,
                'finder_aliasname'       => 'order_view' . $op_id,
                'finder_cols'            => 'order_bn,shop_id,total_amount,process_status,is_cod,pay_status,ship_status,payment,shipping,logi_id,logi_no,createtime,paytime',
                'orderBy'                => 'order_id',
                'orderType'              => 'desc',
                'base_filter'            => $base_filter,
            );
            $this->finder('archive_mdl_orders', $params);
        } else {
            $base_filter = array( 'ship_status'=>['1', '3'], 'status'=>['active','finish']);
            
            // //支持货到付款--已发货的订单
            // $base_filter['order_confirm_filter'] = "(pay_status IN('1', '4', '5') OR is_cod='true')";

            // 订单量大了会后，新建退换货单的查找订单会很慢，隐藏掉此条件，只是会在弹窗的列表也多一些数据
            // $base_filter['filter_sql'] = 'shop_id IN (SELECT shop_id FROM sdb_ome_shop WHERE delivery_mode <> "jingxiao" OR delivery_mode is null)';
            
            //check shop permission
            $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
            if ($organization_permissions) {
                $base_filter['org_id'] = $organization_permissions;
            }

            $params = array(
                'title'                  => $this->title,
                'use_buildin_new_dialog' => false,
                'use_buildin_set_tag'    => false,
                'use_buildin_recycle'    => false,
                'use_buildin_export'     => false,
                'use_buildin_import'     => false,
                'use_buildin_filter'     => true,
                'use_view_tab'           => false,
                'finder_aliasname'       => 'order_view' . $op_id,
                'finder_cols'            => 'order_bn,shop_id,total_amount,column_print_status,process_status,is_cod,pay_status,ship_status,payment,shipping,logi_id,logi_no,createtime,paytime,mark_type',
                'orderBy'                => 'order_id',
                'orderType'              => 'desc',
                'base_filter'            => $base_filter,
            );
            $this->finder('ome_mdl_orders', $params);
        }
    }

    /**
     * 跳转至单据编辑页
     * @param INT $return_id 售后服务ID
     * @param String $type 单据类型(return:退货单，change:换货单)
     **/
    public function gotoreceipt($return_id, $type)
    {
        if (!in_array($type, array('return', 'change'))) {
            echo '单据类型错误!';exit;
        }
        $reship_id = $this->app->model('reship')->select()->columns('reship_id')->where('return_id=?', $return_id)->instance()->fetch_one();
        if (!$reship_id) {
            echo '单据不存在!';exit;
        }
        kernel::single('ome_ctl_admin_return_rchange')->edit($reship_id);
    }

    //售后入库单页面显示 （单据报表->仓库作业单据）
    public function return_io()
    {
        #增加单据导出权限
        $is_export        = kernel::single('desktop_user')->has_permission('bill_export');
        $this->workground = "invoice_center";

        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $base_filter['org_id'] = $organization_permissions;
        }

        $params = array(
            'title'                  => '售后入库单',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => $is_export,
            'use_buildin_import'     => false,
            'use_buildin_filter'     => true,
            'base_filter'            => $base_filter,
        );
        $this->finder('ome_mdl_return_iostock', $params);
    }

    //新建售后页面弹窗（售后->售后  这里选择新建类型）
    public function create_return()
    {
        $this->page('admin/return_product/create_return.html');
    }

    //退款留言
    public function refund_message($apply_id, $type = 'return')
    {
        $this->pagedata['apply_id']  = $apply_id;
        $this->pagedata['type']      = $type;
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->pagedata['shop_type'] = $_GET['shop_type'];
        $this->display('admin/refund/plugin/refund_message.html');
    }

    //ajax下载信息
    public function download_message($apply_id, $type)
    {
        set_time_limit(0);
        
        $error_msg = '';
        $message_list = kernel::single('ome_service_aftersale')->refund_message($apply_id, $type, $error_msg);
        $shop_type    = $_POST['shop_type'];
        $rsp          = array('rsp' => 'succ', 'msg' => '获取成功');
        if ($message_list) {
            $online_memo = serialize($message_list);
            $oRefund     = $this->app->model('refund_apply');
            $refund      = $oRefund->dump($apply_id, 'shop_id');
            $oShop       = $this->app->model('shop');
            $shop        = $oShop->dump(array('shop_id' => $refund['shop_id']));
            if ($type == 'refund') {
                // 退款
                if ($shop_type == 'tmall') {
                    $oRefund_apply_model = $this->app->model('refund_apply_tmall');
                } else {
                    $oRefund_apply_model = $this->app->model('refund_apply_taobao');
                }
                $result = $oRefund_apply_model->update(array('online_memo' => $online_memo), array('apply_id' => $apply_id));
            } else {
                //退货
                if ($shop_type == 'tmall') {
                    $oRefund_apply_model = $this->app->model('return_product_tmall');
                } elseif ($shop_type == '360buy') {
                    $oRefund_apply_model = $this->app->model('return_product_360buy');
                } else {
                    $oRefund_apply_model = $this->app->model('return_product_taobao');
                }
                $result = $oRefund_apply_model->update(array('online_memo' => $online_memo), array('return_id' => $apply_id));
            }
            
            if (!$result) {
                $rsp = array('rsp' => 'fail', 'msg' => '更新失败,请稍后再试');
            }
        } else {
            $rsp_msg = '暂无凭证' . ($error_msg ? ':' . $error_msg : '');
            $rsp = array('rsp' => 'fail', 'msg' => $rsp_msg);
        }
        
        echo json_encode($rsp);
    }

    //拒绝留言.
    public function refuse_message($return_id=null, $shop_type=null)
    {
        set_time_limit(0);
        if ($_POST) {
            $this->begin();
            $oProduct       = $this->app->model('return_product');
            $refuse_message = $_POST['refuse_message'];
            $return_id      = $_POST['return_id'];
            $shop_type      = $_POST['shop_type'];
            $error_msg = '';
            
            // check
            if (in_array($shop_type,array('taobao','tmall','luban'))) {
                if ($_FILES['refuse_proof']['size'] <= 0) {
                    $this->end(false,'请上传凭证图片!',null,array('status'=>'-1'));
                }
            }
            if ($shop_type == 'taobao') {
                $return_model = $this->app->model('return_product_taobao');
            } else if ($shop_type == 'tmall') {
                $return_model        = $this->app->model('return_product_tmall');
                $return_tmall        = $return_model->dump(array('return_id' => $return_id));
                $operation_contraint = $return_tmall['operation_contraint'];
                if ($operation_contraint) {
                    $operation_contraint = explode('|', $operation_contraint);
                    if (in_array('cannot_refuse', $operation_contraint)) {
                        $this->end(false,'此单据,不允许拒绝，必须同意',null,array('status'=>'-1'));
                    }
                    if (in_array('refund_onweb', $operation_contraint)) {
                        $this->end(false,'此单据,回到web页面上操作',null,array('status'=>'-1'));
                    }
                }
            } else if ($shop_type == 'yhd') {
                $return_model = $this->app->model('return_product_yihaodian');
            }else if($shop_type == 'luban'){
                $return_model = $this->app->model('return_product_luban');
            } else if ($shop_type == 'kaola') {
                $return_model = $this->app->model('return_product_kaola');
            }
            $refuse_memo['refuse_message'] = $refuse_message;
            if ($_FILES['refuse_proof']['size'] != 0) {
                if ($_FILES['refuse_proof']['size'] > 512000) {
                    $this->end(false,'上传文件不能超过500K',null,array('status'=>'-1'));
                }
                $type   = $type   = array('gif', 'jpg', 'png');
                $imgext = strtolower(substr(strrchr($_FILES['refuse_proof']['name'], '.'), 1));
                if ($_FILES['refuse_proof']['name']) {
                    if (!in_array($imgext, $type)) {
                        $text = implode(",", $type);
                        $this->end(false,"您只能上传以下类型文件{$text}!",null,array('status'=>'-1'));
                    }
                }

                $ss                   = kernel::single('base_storager');
                $id                   = $ss->save_upload($_FILES['refuse_proof'], "file", "", $msg); //返回file_id;
                $refuse_memo['image'] = $ss->getUrl($id, "file");
                if ($shop_type == 'tmall') {
                    $rh          = fopen($_FILES['refuse_proof']['tmp_name'], 'rb');
                    $imagebinary = fread($rh, filesize($_FILES['refuse_proof']['tmp_name']));
                    fclose($rh);
                    $imagebinary = base64_encode($imagebinary);

                } else {
                    $imagebinary = $refuse_memo['image'];
                }
            }
            $product = $oProduct->dump($return_id);
            if ($refuse_memo && $return_model) {
                $data = array(
                    'return_id'   => $return_id,
                    'shop_id'     => $product['shop_id'],
                    'refund_type'     => $product['return_type'],
                    'return_bn'   => $product['return_bn'],
                    'refuse_memo' => serialize($refuse_memo),
                    'imgext'      => $imgext,
                );

                $return_result = $return_model->save($data);

            }
            $aftersale_service = kernel::single('ome_service_aftersale');
            if (method_exists($aftersale_service, 'update_status')) {
                $memo['refuse_message'] = $refuse_message;
                $memo['refuse_proof']   = $imagebinary;
                if ($return_tmall['refund_type'] == 'change') {
                    $memo['seller_refuse_reason_id'] = $_POST['seller_refuse_reason_id'];
                    $memo['refund_type']             = 'kinds';
                }
                if(in_array($shop_type, array( 'luban','meituan4medicine'))){
                    $memo['reject_reason_code'] = $_POST['seller_refuse_reason_id'];
                    $memo['remark'] = $refuse_message;
                    unset($memo['refuse_message']);
                }

                $rs = $aftersale_service->update_status($return_id, '5', 'sync', $memo);
                if (!$rs || $rs['rsp'] == 'fail') {
                    $this->end(true,$rs['msg'],null,array('status'=>'-2'));
                }
                $adata = array(
                    'return_id' => $return_id,
                    'shop_id'   => $product['shop_id'],
                    'status'    => '5',
                );
                $oProduct->tosave($adata, true, $error_msg);
                
                // 极速换货单取消换出订单并告知平台
                if (in_array($shop_type, array('taobao','tmall'))) {
                    kernel::single('ome_order_platform_taobao_aftersale')->notifyConsigngoodsRefuseOnReturnRefuse(
                        $return_id,
                        $refuse_message ? ('拒绝留言:' . $refuse_message) : '售后申请拒绝'
                    );
                }
            }
            $this->end(true, '成功');
        }
        $this->pagedata['shop_type'] = $shop_type;
        if ($shop_type == 'tmall') {

            $aftersaleObj        = kernel::single('ome_service_aftersale');
            $return_tmall_detail = $aftersaleObj->get_return_type(array('return_id' => $return_id));
            if ($return_tmall_detail['refund_type'] == 'change') {
                $refuse_reason = $return_tmall_detail['refusereason'];
                if ($refuse_reason) {
                    $refuse_reason = json_decode($refuse_reason, true);
                } else {
                    $refuse_reason = $aftersaleObj->refuse_reason($return_id);
                }
                $this->pagedata['refuse_reason'] = $refuse_reason;
            }
        }elseif($shop_type == 'luban'){
            $returnModel = app::get('ome')->model('return_product');
            $returninfo = $returnModel->dump(array('return_id'=>$return_id,'source'=>'matrix'),'return_bn,return_id,shop_id');
            $refuse_reason = kernel::single('erpapi_router_request')->set('shop', $returninfo['shop_id'])->aftersale_getRefuseReason($returninfo);
            $this->pagedata['refuse_reason'] = $refuse_reason;
        }elseif(in_array($shop_type ,array( 'meituan4medicine'))){
            $refuse_reason = kernel::single('ome_aftersale_request_'.$shop_type)->getAftersaleReason('return_reship');
            $this->pagedata['refuse_reason'] = $refuse_reason;
        }
        $this->pagedata['return_id'] = $return_id;
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->display('admin/return_product/plugin/refuse_message.html');
    }

    //批量同步更新状态（“批量拒绝退货”已被注释）
    public function batch_syncUpdate()
    {
        $oReturn         = app::get('ome')->model('return_product');
        $oReturn_tmall   = app::get('ome')->model('return_product_tmall');
        $oReturn_address = app::get('ome')->model('return_address'); //cancel_def
        $oReturn_batch   = app::get('ome')->model('return_batch');
        $status_type     = $_GET['status_type'];
        if (!in_array($status_type, array('agree', 'refuse'))) {
            echo '暂不支持此状态变更';
            exit;
        }
        $shopArr = app::get('ome')->model('shop')->getList('shop_id', ['delivery_mode'=>'jingxiao']);
        $shopJXid = array_column($shopArr,'shop_id');
        $returnFilter = array('return_id' => $_POST['return_id']);
        if($shopJXid) {
            $returnFilter['shop_id|notin'] = $shopJXid;
        }
        $return_list = $oReturn->getlist('return_id,status,return_bn,source,shop_type,shop_id', $returnFilter);
        $chk_msg     = array();
        $error       = array();
        $addressList = array();
        
        //淘宝异步 天猫是即时的
        if ($status_type == 'agree') {
            //批量同意退货
            $shop_ids = array();
            foreach ($return_list as $return)
            {
                $shop_id = $return['shop_id'];
                $return_id = $return['return_id'];
                $status    = $return['status'];
                if (!in_array($status, array('1', '2'))) {
                    $error_msg = $return['return_bn'] . ':状态不可以批量接受退货申请';
                }
                
                //淘宝、天猫、抖音平台必须填地址等信息
                if (in_array($return['shop_type'], array('tmall', 'taobao', 'luban')) && $return['source'] == 'matrix') {
                    $return_address = $oReturn_address->dump(array('shop_id' => $return['shop_id'], 'cancel_def' => 'true'));
                    if (!$return_address) {
                        $chk_msg[] = '请为店铺设置默认退货地址,否则批量将无法操作';
                        break;
                    }
                }
                
                if ($error_msg) {
                    $error[] = array('error_msg' => $error_msg);
                }
                
                $shop_ids[$shop_id] = $shop_id;
            }
            
            //只支持同店铺批量同意操作
            if(count($shop_ids) > 1){
                die("不支持多个店铺同时操作批量同意。");
            }
            
            //获取退货地址列表
            $addressList = $oReturn_address->getList('*', array('shop_id'=>$shop_ids, 'reship_id'=>0), 0, 100, 'cancel_def ASC');
            
        } elseif ($status_type == 'refuse') {
            //批量拒绝退货
            foreach ($return_list as $return) {
                $return_id = $return['return_id'];
                $status    = $return['status'];
                if (!in_array($status, array('1', '2'))) {
                    $error_msg = $return['return_bn'] . ':状态不可以批量拒绝';
                }
                //淘宝天猫必须填地址等信息
                if (($return['shop_type'] == 'tmall' || $return['shop_type'] == 'taobao') && $return['source'] == 'matrix') {
                    $return_batch = $oReturn_batch->dump(array('shop_id' => $return['shop_id'], 'is_default' => 'true', 'batch_type' => 'refuse_return'));
                    if (!$return_batch) {
                        $chk_msg[] = '请为店铺设置默认拒绝凭证和留言!';
                        break;
                    }
                }
                if ($error_msg) {
                    $error[] = array('error_msg' => $error_msg);
                }
            }
        }

        $this->pagedata['error']   = $error;
        $this->pagedata['chk_msg'] = $chk_msg;
        
        //查询是否都是线上单据，是否淘宝和天猫
        //获取可操作数据
        $returnObj                                = kernel::single('ome_return_product');
        $need_return_list                         = $returnObj->return_list(array_column($return_list, 'return_id'));
        $this->pagedata['status_type']            = $status_type;
        $this->pagedata['need_return_list']       = json_encode($need_return_list);
        $this->pagedata['need_return_list_count'] = count($need_return_list);
        
        $this->pagedata['addressList'] = $addressList;
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->display('admin/return_product/plugin/batch_taobao.html');
    }

    //请求执行可操作
    public function ajax_batch()
    {
        set_time_limit(0);
        $returnObj  = kernel::single('ome_return_product');
        $data       = $_POST;
        $ajaxParams = trim($data['ajaxParams']);
        if (strpos($ajaxParams, ';')) {
            $params = explode(';', $ajaxParams);
        } else {
            $params = array($ajaxParams);
        }
        
        $status_type = $data['status_type'];
        $return_id = json_decode($data['return_id'], true);
        $address_id = intval($data['address_id']);
        
        //同意退货请求
        if ($status_type == 'agree') {
            //预先处理邮费承担方
            $post_data = array('post_fee_bear_role'=>$_POST['post_fee_bear_role'], 'address_id'=>$address_id);
            app::get('ome')->model('return_product')->batch_update_product_data($params, $post_data);
        }
        
        $rs = $returnObj->batch_update($status_type, $params);
        echo json_encode(array('total' => count($params), 'succ' => $rs['succ'], 'fail' => $rs['fail'], 'error_msg' => $rs['error_msg']));
    }

    //点击事件显示图片
    public function showImage($filepath)
    {
        echo "<img src='$filepath'>";
    }

    //更新退款单状态
    public function do_updateReturn($return_id, $status)
    {
        $oReturn = app::get('ome')->model('return_product');
        $return  = $oReturn->dump($return_id, 'return_id,shop_id');
        $adata   = array(
            'return_id' => $return_id,
            'shop_id'   => $return['shop_id'],
            'status'    => '5',
            'memo'      => '向线上请求拒绝失败,本地拒绝',
        );
        $oReturn->tosave($adata, true, $error_msg);
        $data = array('rsp' => 'succ');
        echo json_encode($data);
    }

    #设置运费承担方
    public function set_post_fee_bear_role($return_id)
    {
        $this->pagedata['finder_id'] = $_GET['finder_iid'];
        $this->pagedata['return_id'] = $return_id;

        $this->display('admin/return_product/post_fee_bear_role.html');
    }
    public function save_post_fee_bear_role()
    {
        $this->begin();
        $return_id          = $_POST['return_id'];
        $post_fee_bear_role = $_POST['post_fee_bear_role'];
        $params             = array('0||' . $return_id);
        app::get('ome')->model('return_product')->batch_update_product_data($params, array('post_fee_bear_role' => $post_fee_bear_role));
        $this->end(true, app::get('base')->_('操作成功'));
    }

    public function sync_aftersale()
    {

        $oShop     = $this->app->model('shop');
        $shop_list = $oShop->getlist('*', array('shop_type' => 'taobao', 'tbbusiness_type' => 'B'));
        if ($shop_list) {
            foreach ($shop_list as $k => $shop) {
                if ($shop['node_id'] == '') {
                    unset($shop_list[$k]);
                }
            }
        }

        $this->pagedata['shop']      = $shop_list;
        $this->pagedata['finder_id'] = $_GET['finder_id'];
        $this->display('admin/return_product/sync_aftersale.html');
    }

    public function do_sync_aftersale()
    {
        $shop_id      = $_POST['shop_id'];
        $aftersale_bn = trim($_POST['aftersale_bn']);
        $shopObj      = $this->app->model('shop');

        $shop_detail = $shopObj->dump(array('shop_id' => $shop_id), 'node_id,shop_id');

        $node_id     = $shop_detail['node_id'];
        if ($shop_detail) {
            $rsp_data = kernel::single('erpapi_router_request')->set('shop', $shop_detail['shop_id'])->aftersale_get_aftersale_detail(trim($aftersale_bn));

            if ($rsp_data['rsp'] == 'succ') {

                $sdf_exchange = $rsp_data['data']['exchange'];
                $rs           = kernel::single('erpapi_router_response')->set_node_id($node_id)->set_api_name('ome.exchange.add')->dispatch($sdf_exchange);
                echo json_encode(array('rsp' => 'succ', 'msg' => '单拉成功'));
                exit;

            } else {
                echo json_encode(array('rsp' => 'fail', 'msg' => $rsp_data['err_msg'] ? $rsp_data['err_msg'] : "同步售后单失败。"));
                exit;
            }

        }

    }

    /**
     * 商家协商页面
     */
    public function merchant_negotiation($return_id)
    {
        if(empty($return_id)){
            die('无效操作！');
        }
        
        // 调用协商类获取数据
        $negotiateLib = kernel::single('ome_refund_negotiate');
        $result = $negotiateLib->getMerchantNegotiationData($return_id, 'return_product');
    
        if($result['rsp'] == 'fail'){
            die($result['msg']);
        }
        // 设置页面数据 - 明确列出每个字段
        $data = $result['data'];
        
        // 退货单信息
        $this->pagedata['return_info'] = $data['refund_info'];
        
        
        // 退货单ID
        $this->pagedata['return_id'] = $data['refund_id'];
        
        // 已保存的协商数据（编辑模式）
        $this->pagedata['negotiate_data'] = $data['negotiate_data'];
        
        // 协商渲染数据（包含所有协商相关数据）
        $negotiation_data = $data['negotiation_data'];
        
        // 申请提示
        $this->pagedata['apply_tips'] = $negotiation_data['apply_tips'];
        
        // 建议原因列表
        $this->pagedata['reason_list'] = $negotiation_data['reason_list'];
        
        // 最大退款金额 - 平台返回的是分，需要转换成元
        $max_refund_fee = $negotiation_data['max_refund_fee'];
        if (isset($max_refund_fee['max_refund_fee']) && is_numeric($max_refund_fee['max_refund_fee'])) {
            $max_refund_fee['max_refund_fee'] = number_format($max_refund_fee['max_refund_fee'] / 100, 2, '.', '');
        }
        $this->pagedata['max_refund_fee'] = $max_refund_fee;
        
        // 收货地址列表
        $this->pagedata['address_list'] = $negotiation_data['address_list'];
        
        // 协商类型代码（用于默认选中）
        $this->pagedata['negotiate_type_code'] = $negotiation_data['negotiate_type']['negotiate_code'];
        
        // 协商类型列表（所有可选类型）
        $this->pagedata['negotiate_types'] = $negotiation_data['negotiate_types'];
        
        // 推荐协商话术
        $this->pagedata['negotiate_text'] = $negotiation_data['negotiate_type']['negotiate_text'];
        
        // 拒绝原因列表
        $this->pagedata['refuse_reason_list'] = $negotiation_data['refuse_reason_list'];
        
        // 退款类型选项列表
        $this->pagedata['refund_type_options'] = $negotiation_data['refund_type_options'] ?: array();
        
        // 退款版本号
        $this->pagedata['refund_version'] = $negotiation_data['refund_version'] ?: '';
        
        $this->display("admin/return_product/merchant_negotiation.html");
    }
    
    /**
     * 处理商家协商提交
     */
    public function save_merchant_negotiation()
    {
        $return_id = $_POST['return_id'];
        $post_data = $_POST;
        // 调用协商类处理数据
        $negotiateLib = kernel::single('ome_refund_negotiate');
        $result = $negotiateLib->processMerchantNegotiation($return_id, $post_data, 'return_product');
        
        // 直接输出JSON响应
        echo json_encode($result);
        exit;
    }
}
