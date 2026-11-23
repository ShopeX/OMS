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

/* *
 * @author ykm 2015-11-17
 * @describe 特殊订单列表
 *
 */
class brush_ctl_admin_orders extends desktop_controller
{
    private $base_filter = array(
        'order_type' => 'brush',
    );
    
    public function _views()
    {
        static $sub_menu;
        
        $orderMdl = app::get('ome')->model('orders');
        
        if($sub_menu) {
            return $sub_menu;
        }
        
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('待审核'),'filter'=>array('process_status'=>'unconfirmed' ),'optional'=>false),
            1 => array('label'=>app::get('base')->_('已审核'),'filter'=>array( 'process_status'=>'confirmed'),'optional'=>false),
            2 => array('label'=>app::get('base')->_('已完成'),'filter'=>array( 'status'=>'finish'),'optional'=>false),
            3 => array('label'=>app::get('base')->_('全部'),'filter'=>array(),'optional'=>false),
        );
        
        foreach ($sub_menu as $k => $v) {
            $sub_menu[$k]['filter'] = array_merge($this->base_filter, $v['filter']);
            $sub_menu[$k]['addon'] = '_FILTER_POINT_';
            $sub_menu[$k]['href'] = 'index.php?app=brush&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&view=' . $k;
        }
        
        return $sub_menu;
    }

    public function index()
    {
        $this->app->app_id = 'ome';
        
        if(!isset($_GET['view']) || $_GET['view'] == '') {
            $subMenu = $this->_views();
            foreach($subMenu as $k => $v) {
                if($v['addon'] > 0 || $v['addon'] == '_FILTER_POINT_') {
                    $_GET['view'] = $k;
                    break;
                }
            }
        }
        
        $params = array(
            'title'=>'特殊订单列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>true,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'use_view_tab' => true,
            'base_filter' => array('order_type'=>'brush'),
        );
        
        if($_GET['view'] == 0) {
            $params['actions'] = array(
                array('label'=>'批量审核', 'submit'=>'index.php?app=brush&ctl=admin_orders&act=confirm', 'target'=>'dialog::{width:680,height:280,title:\'特殊订单审核\'}'),
                array('label'=>'设为普通订单', 'submit'=>'index.php?app=brush&ctl=admin_orders&act=normal', 'confirm'=>'确认要设置为普通订单么？'),
                array('label'=>'批量设置备注', 'submit'=>'index.php?app=brush&ctl=admin_memo&act=batch_order', 'target'=>'dialog::{width:600,height:250,title:\'批量设置备注\'}'),
                array('label'=>'删除订单', 'submit'=>'index.php?app=brush&ctl=admin_orders&act=delete', 'confirm'=>'删除订单后不可恢复，您确定要删除么？'),
            );
        } elseif($_GET['view'] == 1) {
            $params['actions'] = array(
                array('label'=>'撤回订单', 'submit' => 'index.php?app=brush&ctl=admin_orders&act=rollback', 'confirm' => '撤回后发货单无效,确定撤回么？')
            );
        }
        if($_GET['action'] == 'to_export') {
            $this->finder('ome_mdl_orders',$params);
        } else {
            $this->finder('brush_mdl_orders',$params);
        }
    }

    public function delete()
    {
        $orderMdl = app::get('ome')->model('orders');
        
        $arrOrderId = $this->_getSelectedId();
        
        $unValid = $orderMdl->dump(array('order_id'=>$arrOrderId, 'process_status|noequal'=>'unconfirmed'), 'order_id');
        if($unValid) {
            $this->splash('error', '', '所选订单必须都是未确定状态');
        }
        
        $order_bn = array ('0');
        foreach ($orderMdl->getList('order_bn', array ('order_id' => $arrOrderId)) as $value) {
            $order_bn[] = $value['order_bn'];
        }
        
        $this->begin('index.php?app=brush&ctl=admin_orders&act=index&view='.$_POST['view']);
        
        $ret = $orderMdl->delete(array('order_id' => $arrOrderId));
        !$ret && $this->end(false, '删除订单失败');
        
        $ret = app::get('ome')->model('order_items')->delete(array('order_id' => $arrOrderId));
        !$ret && $this->end(false, '删除订单order_items失败');
        
        $ret = app::get('ome')->model('order_objects')->delete(array('order_id' => $arrOrderId));
        !$ret && $this->end(false, '删除订单order_objects失败');
        
        $ret = $this->app->model('farm_order')->delete(array('order_id' => $arrOrderId));
        !$ret && $this->end(false, '删除farm_order失败');
        
        $ret = app::get('ome')->model('payments')->delete(array('order_id' => $arrOrderId));
        !$ret && $this->end(false, '删除payments失败');
        
        $ret = app::get('ome')->model('refund_apply')->delete(array('order_id' => $arrOrderId));
        !$ret && $this->end(false, '删除refund_apply失败');
        
        $ret = app::get('ome')->model('refunds')->delete(array('order_id' => $arrOrderId));
        !$ret && $this->end(false, '删除refunds失败');
        
        $ret = app::get('ome')->model('order_pmt')->delete(array('order_id' => $arrOrderId));
        !$ret && $this->end(false, '删除order_pmt失败');
        
        $ret = app::get('ome')->model('order_service')->delete(array('order_id'=>$arrOrderId));
        !$ret && $this->end(false, '删除order_service失败');
        
        //发票
        if(app::get('invoice')->is_installed()) {
            $ret = app::get('invoice')->model('order')->delete(array('order_id'=>$arrOrderId));
            !$ret && $this->end(false, '删除发票失败');
        }
        
        //删除相关赠品发放记录
        if(app::get('crm')->is_installed()){
            app::get('ome')->model('gift_logs')->delete(array('order_bn'=>$order_bn));
        }
        
        $this->end(true, '删除成功');
    }

    /**
     * 特殊订单转换为普通订单
     */
    public function normal()
    {
        $orderMdl = app::get('ome')->model('orders');
        $operLogMdl = app::get('ome')->model('operation_log');
        
        $arrOrderId = $this->_getSelectedId();
        
        //order
        $unValid = $orderMdl->dump(array('order_id'=>$arrOrderId, 'process_status|noequal'=>'unconfirmed'), 'order_id');
        if($unValid) {
            $this->splash('error', '', '所选订单必须都是未确定状态');
        }
        
        $this->begin('index.php?app=brush&ctl=admin_orders&act=index&view='.$_POST['view']);
        
        //update
        $ret = $orderMdl->update(array('order_type'=>'normal'), array('order_id'=>$arrOrderId, 'order_type'=>'brush'));
        if(is_bool($ret) || $ret != count($arrOrderId)) {//防止并发
            $this->end(false, '设置失败，存在普通订单');
        }
        
        $ret = $this->app->model('farm_order')->delete(array('order_id'=>$arrOrderId));
        !$ret && $this->end(false, '设置失败');
        
        //添加订单预占冻结
        $this->_addOrderFreeze($arrOrderId);
        
        //logs
        foreach($arrOrderId as $order_id){
            $operLogMdl->write_log('order_modify@ome', $order_id, "从特殊订单设置为普通订单");
        }
        
        $this->end(true, '设置完成');
    }

    /**
     * 普通订单转换为特殊订单
     */
    public function brush()
    {
        $operLogMdl = app::get('ome')->model('operation_log');
        
        $arrOrderId = $this->_getSelectedId();
        
        $refundApply = app::get('ome')->model('refund_apply')->getList('apply_id,order_id', array('order_id'=>$arrOrderId, 'status|noequal'=>'3'));
        $statusChange = app::get('ome')->model('orders')->getList('order_id', array('order_id'=>$arrOrderId, 'process_status|notin' => array('unconfirmed','is_retrial'), 'order_type'=>'_ALL_','assigned' => 'notassigned'));
        $delChange = array_merge($refundApply, $statusChange);
        if($delChange) {
            foreach($delChange as $val) {
                $index = array_search($val['order_id'], $arrOrderId);
                if($arrOrderId[$index]) unset($arrOrderId[$index]);
            }
            
            if(empty($arrOrderId)) {
                $this->splash('error', '', '设置失败，所选订单有可用退款申请单或已分派');
            }
        }

        $this->begin('index.php?app=ome&ctl=admin_order&act=dispatch&flt=buffer&view='.$_POST['view']);
        
        $ret = app::get('ome')->model('orders')->update(array('process_status'=>'unconfirmed', 'order_type'=>'brush'), array(
            'order_id'=>$arrOrderId, 
            'order_type|noequal'=>'brush',
            'order_confirm_filter' => '( op_id IS NULL AND group_id IS NULL)',
            'process_status'=>'unconfirmed'));

        //防止并发
        if(!is_bool($ret) && $ret == count($arrOrderId)) {
            //释放订单预占冻结
            $this->_releaseOrderFreeze($arrOrderId);
            
            //logs
            foreach($arrOrderId as $order_id) {
                $operLogMdl->write_log('order_modify@ome', $order_id, "从普通订单设置为特殊订单");
            }
            
            $this->end(true, '设置完成');
        } else {
            $this->end(false, '设置失败，存在特殊订单');
        }
    }

    public function confirm()
    {
        $orderMdl = app::get('ome')->model('orders');
        
        $arrOrderId = $this->_getSelectedId();
        
        $unValid = $orderMdl->dump(array('order_id'=>$arrOrderId, 'process_status|noequal'=>'unconfirmed'), 'order_id');
        if($unValid) {
            $this->splash('error', '', '所选订单必须都是未确定状态');
        }
        
        $corpList = app::get('ome')->model('dly_corp')->getList('corp_id, name', array('disabled' => 'false'), 0, -1);
        
        $this->pagedata['orderCount'] = count($arrOrderId);
        $this->pagedata['orderGroup'] = json_encode($arrOrderId);
        $this->pagedata['corpList'] = $corpList;
        
        $this->display('admin/confirm.html');
    }

    public function rollback()
    {
        $orderMdl = app::get('ome')->model('orders');
        $opModel = app::get('ome')->model('operation_log');
        
        $arrOrderId = $this->_getSelectedId();
        
        $deliveryOrder = $this->app->model('delivery_order')->getList('*', array('order_id'=>$arrOrderId));
        $rbDelivery = $rbOrder = array();
        foreach($deliveryOrder as $val) {
            $rbDelivery[] = $val['delivery_id'];
            $rbOrder[] = $val['order_id'];
        }
        
        $rbOrder = array_unique($rbOrder);
        if(empty($rbOrder)) {
            $this->splash('error', '', '撤回完成或无订单可撤回');
        }
        
        $this->begin('index.php?app=brush&ctl=admin_orders&act=index&view='.$_POST['view']);
        
        $orderUpData = array(
            'process_status'=>'unconfirmed',
            'status'=>'active',
            'ship_status'=>0,
            'print_finish' => 'false',
            'print_status' => 0,
            'logi_id' => 0,
            'logi_no' => ''
        );
        
        $orderFilter = array(
            'order_id' => $rbOrder,
            'status|noequal' => 'finish',
            'process_status|noequal' => 'unconfirmed'
        );
        
        $ret = $opModel->batch_write_log('order_back@ome', '撤回特殊订单', time(), $orderFilter);
        !$ret && $this->end(false, '撤回失败');
        
        $ret = $orderMdl->update($orderUpData, $orderFilter);
        !$ret && $this->end(false, '撤回失败');
        
        $deliveryUpData = array(
            'status' => 'cancel'
        );
        
        $deliveryFilter = array(
            'delivery_id' => $rbDelivery,
            'status|notin' => array('succ', 'cancel')
        );
        
        $ret = $opModel->batch_write_log('delivery_brush_back@brush', '特殊订单撤回,发货单取消', time(), $deliveryFilter);
        !$ret && $this->end(false, '撤回失败');
        
        $ret = $this->app->model('delivery')->update($deliveryUpData, $deliveryFilter);
        !$ret && $this->end(false, '撤回失败');
        
        $this->end(true, '撤回完成');
    }

    private function _getSelectedId()
    {
        if($_POST['isSelectedAll'] == '_ALL_') {
            unset($_POST['isSelectedAll']);
            
            $this->_setBaseFilter();
            
            $param = array_merge($this->base_filter, $_POST);
            
            $orderMdl = app::get('ome')->model('orders');
            
            $orderMdl->defaultOrder = '';
            $orderMdl->filter_use_like = true;
            $selOrder = $orderMdl->getList('order_id', $param, 0, -1);
            $arrOrderId = array();
            foreach($selOrder as $val) {
                $arrOrderId[] = $val['order_id'];
            }
        } else {
            $arrOrderId = $_POST['order_id'];
        }
        
        if(empty($arrOrderId)) {
            $this->splash('success', $this->url, '没有选择的订单');
        }
        
        return $arrOrderId;
    }

    private function _setBaseFilter()
    {
        $view = intval($_POST['view']);
        
        if($_POST['app'] == 'ome') {
            $baseFilter = array(
                'assigned' => 'buffer',
                'abnormal' => 'false',
                'ship_status' => '0',
                'is_fail' => 'false',
                'process_status' => array('unconfirmed','is_retrial'),
                'status' => 'active',
                'is_auto' => 'false',
                'order_confirm_filter' => '( op_id IS NULL AND group_id IS NULL)'
            );
            
            if($view == 1) {
                $baseFilter['is_cod'] = 'true';
            } else if($view == 2) {
                $baseFilter['pay_status'] = array('0','3');
            } else if($view == 3) {
                $baseFilter['pay_status'] = 1;
            }
        } else {
            $subMenu = $this->_views();
            $baseFilter = $subMenu[$view]['filter'];
        }
        
        $this->base_filter = $baseFilter;
    }
    
    /**
     * 添加订单预占库存
     *
     * @param array $orderIds
     * @return bool
     */
    private function _addOrderFreeze($orderIds)
    {
        $orderMdl = app::get('ome')->model('orders');
        $orderItemMdl  = app::get('ome')->model('order_items');
        $orderObjMdl = app::get('ome')->model('order_objects');
        
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        $freeze_obj_type = material_basic_material_stock_freeze::__ORDER;
        $bmsq_id = material_basic_material_stock_freeze::__SHARE_STORE;
        
        //orders
        $orderList = $orderMdl->getList('order_id,order_bn,shop_id', array('order_id'=>$orderIds));
        $orderList = array_column($orderList, null, 'order_id');
        
        $branchBatchList = [];
        //items
        $itemList = $orderItemMdl->getList('order_id,product_id,nums,sendnum,`delete`,obj_id', array('order_id'=>$orderIds));
        uasort($itemList, [kernel::single('console_iostockorder'), 'cmp_productid']);

        // objects
        $objectList = $orderObjMdl->getList('order_id,obj_id,goods_id,bn,obj_type', ['order_id'=>$orderIds]);
        $objectList = array_column($objectList, null, 'obj_id');

        foreach($itemList as $val)
        {
            $order_id = $val['order_id'];
            $product_id = $val['product_id'];
            $order_bn = $orderList[$order_id]['order_bn'];
            $shop_id = $orderList[$order_id]['shop_id'];
            $log_type = '';
            $store_code = '';
            $goods_id = $objectList[$val['obj_id']]['goods_id'];
            
            //delete
            if($val['delete'] == 'true'){
                continue;
            }
            
            //nums
            $item_nums = intval($val['nums']) - intval($val['sendnum']);
            
            //修改预占库存流水
            $freezeData = array();
            $freezeData['bm_id'] = $product_id;
            $freezeData['sm_id'] = $goods_id;
            $freezeData['obj_type'] = $freeze_obj_type;
            $freezeData['bill_type'] = 0;
            $freezeData['obj_id'] = $order_id;
            $freezeData['shop_id'] = $shop_id;
            $freezeData['branch_id'] = 0;
            $freezeData['bmsq_id'] = $bmsq_id;
            $freezeData['num'] = $item_nums;
            $freezeData['log_type'] = $log_type;
            $freezeData['store_code'] = $store_code;
            $freezeData['obj_bn'] = $order_bn;

            $branchBatchList[] = $freezeData;
        }
        //修改预占库存流水
        $basicMStockFreezeLib->freezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        
        return true;
    }
    
    /**
     * 释放订单预占库存
     * 
     * @param array $orderIds
     * @return bool
     */
    private function _releaseOrderFreeze($orderIds)
    {
        $orderItemMdl  = app::get('ome')->model('order_items');
        $orderObjMdl   = app::get('ome')->model('order_objects');
        
        $basicMStockFreezeLib = kernel::single('material_basic_material_stock_freeze');
        
        $branchBatchList = [];
        // objects
        $objectList = $orderObjMdl->getList('obj_id,order_id,goods_id,bn,obj_type', ['order_id'=>$orderIds]);
        $objectList = array_column($objectList, null, 'obj_id');
        //items
        $itemList = $orderItemMdl->getList('order_id,product_id,nums,sendnum,`delete`', array('order_id'=>$orderIds));
        uasort($itemList, [kernel::single('console_iostockorder'), 'cmp_productid']);
        foreach($itemList as $val)
        {
            $order_id = $val['order_id'];
            $product_id = $val['product_id'];
            $goods_id = $objectList[$val['obj_id']]['goods_id'];
            
            //delete
            if($val['delete'] == 'true'){
                continue;
            }
            
            //nums
            $item_nums = intval($val['nums']) - intval($val['sendnum']);
            
            //[扣减]基础物料店铺冻结
            $branchBatchList[] = [
                'bm_id'     =>  $product_id,
                'sm_id'     =>  $goods_id,
                'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                'bill_type' =>  0,
                'obj_id'    =>  $order_id,
                'branch_id' =>  '',
                'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                'num'       =>  $item_nums,
            ];
        }
        //[扣减]基础物料店铺冻结
        $basicMStockFreezeLib->unfreezeBatch($branchBatchList, __CLASS__.'::'.__FUNCTION__, $err);
        
        //清除订单级预占店铺冻结流水
        // unfreezeBatch已经清除
        // foreach($orderIds as $order_id)
        // {
        //     $basicMStockFreezeLib->delOrderFreeze($order_id);
        // }
        
        return true;
    }
}
