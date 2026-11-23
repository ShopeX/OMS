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
 * 唯品会JIT采购单管理
 * 
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 1.0 vopurchase.php 2017-02-23
 */
class console_ctl_admin_vopurchase extends desktop_controller{
    
    var $workground = "console_purchasecenter";
    
    function _views()
    {
        $purchaseObj    = app::get('console')->model('order');
        
        $base_filter    = array();
        $dateline       = time();
        $sub_menu = array(
                0 => array('label'=>__('全部'),'filter'=>$base_filter),
                1 => array('label'=>__('进行中'),'filter'=>array('sell_et_time|than'=>$dateline), 'optional'=>false),
                2 => array('label'=>__('档期已结束'),'filter'=>array('sell_et_time|lthan'=>$dateline), 'optional'=>false),
        );
        
        foreach($sub_menu as $k => $v)
        {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $purchaseObj->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=console&ctl=admin_vopurchase&act=index&view='. $k;
        }
        
        return $sub_menu;
    }
    
    function index()
    {
        $this->title = '采购单列表';
        
        $params = array(
                'title'=>$this->title,
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>true,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_recycle'=>false,
                'actions'=>array(
                    array(
                        'label' => '获取采购单',
                        'href' => 'index.php?app=console&ctl=admin_vopurchase&act=get_po_page',
                        'target' => 'dialog::{width:600,height:300,title:\'获取采购单\'}'
                    ),
                ),
        );
        
        $this->finder('console_mdl_order', $params);
    }
    
    /**
     * 人工点击生成拣货单
     */
    function createPick()
    {
        @set_time_limit(0);
        @ini_set('memory_limit','128M');
        
        $rsp = array('rsp'=>'fail', 'error_msg'=>'');
        
        $po_id    = $_POST['po_id'];
        if(empty($po_id))
        {
            $rsp['error_msg']    = '无效操作';
            echo json_encode($rsp, true);
            exit;
        }
        
        $pickLib        = kernel::single('purchase_purchase_pick');
        $purchaseObj    = app::get('purchase')->model('order');
        $poInfo         = $purchaseObj->dump(array('po_id'=>$po_id), 'po_bn, shop_id, unpick_num');
        
        $po_no        = $poInfo['po_bn'];
        $shop_id      = $poInfo['shop_id'];
        list($rs, $msg) = kernel::single('vop_purchase_pick')->createPick($po_no, $shop_id);

        $this->splash($rs ? 'success' : 'error', null, $msg);
    }
    
    /**
     * [创建拣货单]弹窗页
     */
    function confirm($po_id)
    {
        $purchaseObj    = app::get('purchase')->model('order');
        $poInfo         = $purchaseObj->dump(array('po_id'=>$po_id), 'po_id, po_bn, shop_id, unpick_num');
        
        $finder_id    = $_GET['finder_id'];
        
        $this->pagedata['finder_id'] = $finder_id;
        $this->pagedata['data'] = $poInfo;
        $this->pagedata['single'] = 1;
        $this->display("admin/vop/order_alert.html");
    }
    
    /**
     * [获取采购单]弹窗页
     */
    function get_po_page()
    {
        $purchaseLib  = kernel::single('purchase_purchase_order');
        $purchaseObj  = app::get('purchase')->model('order');
        
        //唯品会JIT店铺
        $shopList     = $purchaseLib->get_vop_shop_list();
        $this->pagedata['shop_list']    = $shopList;
        
        $this->display('admin/vop/order_po.html');
    }
    
    function get_order()
    {
        
        @set_time_limit(0);
        @ini_set('memory_limit','128M');
        
        $po_no      = trim($_POST['purchase_bn']);
        $pick_no = trim($_POST['pick_no']);
        $shop_id    = $_POST['shop_id'];
        $st_sell_st_time    = $_POST['st_sell_st_time'];
        $et_sell_st_time    = $_POST['et_sell_st_time'];
        $st_po_start_time    = $_POST['st_po_start_time'];
        $et_po_start_time    = $_POST['et_po_start_time'];
        
        if(empty($shop_id)){
            $this->splash('error', null, '请选择店铺!');
        }
        
        $pickLib   = kernel::single('purchase_purchase_pick');
        
        //唯品会店铺
        $purchaseLib  = kernel::single(class_name: 'purchase_purchase_order');
        $shopInfo     = $purchaseLib->get_vop_shop_list($shop_id);
        $shopInfo     = $shopInfo[0];
        
        if(empty($shopInfo['node_id'])) {
            $this->splash('error', null, '店铺未绑定!');
        }
        
        $params = [];

        if ($po_no) {
            $params['po_no'] = $po_no;
        }

        if ($st_sell_st_time) {
            $params['st_sell_st_time'] = date('Y-m-d H:i:s', strtotime($st_sell_st_time));
        }
        if ($et_sell_st_time) {
            $params['et_sell_st_time'] = date('Y-m-d H:i:s', strtotime($et_sell_st_time));
        }
        if ($st_po_start_time) {
            $params['st_po_start_time'] = date('Y-m-d H:i:s', strtotime($st_po_start_time));
        }
        if ($et_po_start_time) {
            $params['et_po_start_time'] = date('Y-m-d H:i:s', strtotime($et_po_start_time));
        }

        if ($pick_no) {
            list($result, $msg) = kernel::single('vop_purchase_pick')->getPullList(null, $shop_id, $pick_no);
        } elseif ($po_no) {
            list($result, $msg) = kernel::single('vop_purchase_order')->getPullList(['po_no' => $po_no], $shop_id);
        } else {
            list($result, $msg) = kernel::single('vop_purchase_order')->getPullList($params, $shop_id);
        }

        $this->splash($result ? 'success' : 'error', null, $msg);
    }

    function getPicks($po_id)
    {
        
        @set_time_limit(0);
        @ini_set('memory_limit','128M');
        
        $po = app::get('purchase')->model('order')->dump($po_id, 'po_bn,shop_id');

        if (!$po) {
            $this->splash('error', null, '采购单不存在');
        }


        list($result, $msg) = kernel::single('vop_purchase_pick')->getPullList($po['po_bn'], $po['shop_id']);


        $this->splash($result ? 'success' : 'error', null, $msg);
    }
}