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
class ome_ctl_admin_order_fail extends desktop_controller
{
    public $name       = "订单中心";
    public $workground = "order_center";
    public $order_type = 'all';

    public function _views()
    {
        $mdl_order   = $this->app->model('order_fail');
        $base_filter = array('is_fail' => 'true', 'archive' => '1', 'edit_status' => 'true', 'status' => 'active');
        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $base_filter['org_id'] = $organization_permissions;
        }

        $sub_menu = array(
            0 => array('label' => app::get('base')->_('全部'), 'filter' => $base_filter, 'optional' => false),
            1 => array('label' => app::get('base')->_('活动'), 'filter' => array('process_status|noequal' => 'cancel'), 'optional' => false),
            2 => array('label' => app::get('base')->_('取消'), 'filter' => array('process_status' => 'cancel'), 'optional' => false),
        );

        $i = 0;
        foreach ($sub_menu as $k => $v) {
            if (!IS_NULL($v['filter'])) {
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }

            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon']  = $mdl_order->viewcount($v['filter']);
            $sub_menu[$k]['href']   = 'index.php?app=ome&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&view=' . $i++;
        }
        return $sub_menu;
    }

    public function index()
    {
        $op_id       = kernel::single('desktop_user')->get_id();
        $this->title = '失败订单';

        $base_filter = array('is_fail' => 'true', 'archive' => '1', 'edit_status' => 'true', 'status' => array('active','finish'));

        //check shop permission
        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $base_filter['org_id'] = $organization_permissions;
            
            //post
            if($_POST['org_id']){
                if(in_array($_POST['org_id'],$base_filter['org_id'] )){
                    $base_filter['org_id'] = $_POST['org_id'];
                }else{
                    $base_filter['org_id'] = -1;
                }
            }
        }

        $is_export = kernel::single('desktop_user')->has_permission('order_export'); #增加失败订单导出权限
        $params    = array(
            'title'               => $this->title,
            'use_buildin_recycle' => false,
            'use_buildin_filter'  => true,
            'use_buildin_export'  => $is_export,
            'actions'             => array(
                array(
                    'label'   => app::get('ome')->_('删除订单'),
                    'submit'  => "index.php?app=ome&ctl=admin_order_fail&act=toDeleteFail",
                    'confirm' => '订单删除后无法恢复，您确定删除选择的订单吗？',
                    'target'  => 'dialog::{width:600,height:250,title:\'删除订单(订单删除后无法恢复,可以手工重新获取订单)\'}',
                ),
            ),

            'use_view_tab'        => true,
            'finder_aliasname'    => 'order_fail' . $op_id,
            'finder_cols'         => 'order_bn,shop_id,shop_type,total_amount,is_cod,pay_status,createtime',
            'base_filter'         => $base_filter,
            'object_method' => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
            'orderBy'     => 'createtime DESC'
        );
        $this->finder('ome_mdl_order_fail', $params);
    }

    public function dosave()
    {
        $url      = 'index.php?app=ome&ctl=admin_order_fail&act=index';
        $pbn      = $_POST['pbn'];
        $oldPbn   = $_POST['oldPbn'];
        $order_id = $_POST['order_id'];

        //danny_freeze_stock_log
        define('FRST_TRIGGER_OBJECT_TYPE', '订单：失败订单恢复');
        define('FRST_TRIGGER_ACTION_TYPE', 'ome_ctl_admin_order_fail：dosave');
        
        //check
        if(empty($pbn) || empty($oldPbn)){
            $this->splash('error', $url, '请填写调整货号');
        }
        
        //修正订单项
        if (kernel::single("ome_order_fail")->modifyOrderItems($order_id, $oldPbn, $pbn)) {
            $this->splash('success', $url, '订单处理成功');
        } else {
            $this->splash('error', $url, '存在异常商品，订单修正失败！');
        }
    }

    /**
     * 失败订单批量修复货号
     */
    public function batchsave($type = 'bn')
    {

        $pbn    = $_POST['pbn'];
        $oldPbn = $type == 'bn' ? $_POST['oldPbn'] : $_POST['oldGoodsId'];
        $finder_id = $_GET['finder_id'];
        
        //check
        if(empty($oldPbn)){
            echo "<button id='close_btn'>存在原始货号为空的情况不允许批量修改！</button><script>;if(finderGroup['{$finder_id}']) finderGroup['{$finder_id}'].refresh.delay(100,finderGroup['{$finder_id}']);var oDialog = $('close_btn').getParent('.dialog').retrieve('instance');oDialog.close.delay(2000, oDialog);</script>";
            exit;
        }
        
        //type
        if ($type == 'bn') {
            $orderData = kernel::single('ome_order_fail')->getFailOrderByBn($oldPbn);
        } else {
            $orderData = kernel::single('ome_order_fail')->getFailOrderByName($oldPbn);
        }
        
        // check
        if(empty($orderData)){
            echo "<button id='close_btn'>没有找到匹配的失败订单！</button><script>;if(finderGroup['{$finder_id}']) finderGroup['{$finder_id}'].refresh.delay(100,finderGroup['{$finder_id}']);var oDialog = $('close_btn').getParent('.dialog').retrieve('instance');oDialog.close.delay(2000, oDialog);</script>";
            exit;
        }
        
        if (!$oldPbn) {
            echo "<button id='close_btn'>存在原始货号为空的情况不允许批量修改！</button><script>;if(finderGroup['{$finder_id}']) finderGroup['{$finder_id}'].refresh.delay(100,finderGroup['{$finder_id}']);var oDialog = $('close_btn').getParent('.dialog').retrieve('instance');oDialog.close.delay(2000, oDialog);</script>";
            exit;
        } else {
            foreach ($oldPbn as $bn) {
                if (!$bn || $bn == '') {
                    echo "<button id='close_btn'>存在原始货号为空的情况不允许批量修改！</button><script>;if(finderGroup['{$finder_id}']) finderGroup['{$finder_id}'].refresh.delay(100,finderGroup['{$finder_id}']);var oDialog = $('close_btn').getParent('.dialog').retrieve('instance');oDialog.close.delay(2000, oDialog);</script>";
                    exit;
                }
            }
        }

        /**
         * 处理拥有相同旧货号明细大于1，且新货号明细货号又不同的情况。
         * 只能单个处理
         */
        $arr = array_combine($pbn, $oldPbn);
        
        // 过滤键为空
        unset($arr['']);
        $arr_count_values = array_count_values($arr);
        foreach ($arr_count_values as $key => $count) {
            if ($key && $count > 1) {
                echo "<button id='close_btn'>相同旧货号对应不同新货号，不允许批量修改！</button><script>;if(finderGroup['{$finder_id}']) finderGroup['{$finder_id}'].refresh.delay(100,finderGroup['{$finder_id}']);var oDialog = $('close_btn').getParent('.dialog').retrieve('instance');oDialog.close.delay(2000, oDialog);</script>";
                exit;
            }
        }
        $GroupList = array_column($orderData, 'order_id');
        $this->pagedata['oldPbn'] = base64_encode(json_encode($oldPbn));
        $this->pagedata['pbn'] = base64_encode(json_encode($pbn));
        $this->pagedata['modifyType'] = $type;
        $this->pagedata['custom_html'] = $this->fetch('admin/order/fail/batchsave.html');
        $this->pagedata['request_url'] = $this->url.'&act=doBatchSave';
        $this->pagedata['itemCount'] = count($GroupList);
        $this->pagedata['GroupList'] = json_encode($GroupList);
        $this->pagedata['maxNum']    = 10;
        $this->pagedata['startNow']  = true;
        parent::dialog_batch();
    }

    public function doBatchSave()
    {
        $order_ids = explode(',', $_POST['primary_id']);
        $order_ids = array_filter($order_ids);
        if (empty($order_ids)) {
            echo 'Error: 请先选择订单';exit;
        }
        $oldPbn = json_decode(base64_decode($_POST['oldPbn']), true);
        $pbn = json_decode(base64_decode($_POST['pbn']), true);
        $modifyType = $_POST['modifyType'];
        $retArr = array(
            'itotal'    => count($order_ids),
            'isucc'     => 0,
            'ifail'     => 0,
            'err_msg'   => array(),
        );
        foreach ($order_ids as $v) {
            kernel::single('ome_order_fail')->addFailOrderLog($v);
            $result = kernel::single('ome_order_fail')->modifyOrderItemsByBn($v, $oldPbn, $pbn, $modifyType);
            if ($result) {
                $retArr['isucc']++;
            } else {
                $retArr['ifail']++;
                $retArr['err_msg'][] = '订单修正失败！';
            }
        }
        echo json_encode($retArr),'ok.';exit;
    }

    public function toDeleteFail()
    {
        $_POST = array_merge($_POST, array('is_fail' => 'true', 'archive' => '1', 'edit_status' => 'true'));

        $organization_permissions = kernel::single('desktop_user')->get_organization_permission();
        if ($organization_permissions) {
            $_POST['org_id'] = $organization_permissions;
        }

        $this->pagedata['request_url'] = $this->url.'&act=deleteFailOrder';

        parent::dialog_batch('ome_mdl_order_fail',true, 500);
    }

    #删除失败订单
    public function deleteFailOrder()
    {
        parse_str($_POST['primary_id'], $postdata);

        if (!$postdata['f']) { echo 'Error: 请先选择订单';exit;}

        $retArr  = array(
            'itotal'    => 0,
            'isucc'     => 0,
            'ifail'     => 0,
            'err_msg'   => array(),
        );

        $orderMdl = app::get('ome')->model('orders');
        $operLogObj = app::get('ome')->model('operation_log');

        $orders = $orderMdl->getList('order_id,order_bn,pay_status,is_cod,is_fail,status', $postdata['f'], $postdata['f']['offset'], $postdata['f']['limit']);
        
        $orders = array_column($orders, null, 'order_id');

        if (!$orders) {echo 'Error: 未查询到失败订单';exit;}

        $retArr['itotal'] = count($orders);

        // 排除掉正在恢复的订单
        $repairOrders = app::get('base')->model('queue')->getList('params', array('status' => 'running', 'work' => 'ome_order_fail.batchModifyOrder'));
        if (!empty($repairOrders)) {
            foreach ($repairOrders as $o) {
                $arrRepairOrderId = $o['params']['sdfdata']['orderId'];
                if ( $arrRepairOrderId) {
                    foreach($arrRepairOrderId as $roi) {
                        if ($orders[$roi]) {

                            $retArr['ifail']++;
                            $retArr['err_msg'][] = sprintf('%s：删除订单中有订单在失败订单恢复队列中不能删除', $orders[$o['params']['sdfdata']['orderId']]['order_bn']);

                            unset($orders[$roi]);
                        }
                    }
                }
            }
        }

        if (!$orders) {
            echo json_encode($retArr),'ok.';exit;
        }

        $extendOrderMdl  = app::get('ome')->model('order_extend');
        $invoiceOrderMdl = app::get('ome')->model('order_invoice');
        $serviceOrderMdl = app::get('ome')->model('order_service');
        $objectMdl       = app::get('ome')->model('order_objects');
        $itemMdl         = app::get('ome')->model('order_items');
        $pmtMdl          = app::get('ome')->model('order_pmt');
        $paymentMdl      = app::get('ome')->model('payments');
        $refundApplyMdl  = app::get('ome')->model('refund_apply');
        $refundMdl       = app::get('ome')->model('refunds');
        $tbfxOrderMdl    = app::get('ome')->model('tbfx_orders');
        $tbfxObjectMdl   = app::get('ome')->model('tbfx_order_objects');
        $tbfxItemMdl     = app::get('ome')->model('tbfx_order_items');
        $tbgiftItemMdl   = app::get('ome')->model('tbgift_order_items');
        $tbjzOrderMdl    = app::get('ome')->model('tbjz_orders');
        $orderAgentMdl   = app::get('ome')->model('order_selling_agent');
        $orderPrePcsMdl  = app::get('ome')->model('order_preprocess');

        $orderIdArr = array_column($orders, 'order_id');

        $orderObjList = $objectMdl->getList('order_id,obj_id,goods_id', ['order_id'=>$orderIdArr]);
        $orderObjList = array_column($orderObjList, null, 'obj_id');

        $orderItemList = [];
        $_order_items = $itemMdl->getList('product_id,nums,order_id,obj_id', array ('order_id' => $orderIdArr));
        foreach ($_order_items as $k => $v) {
            $orderItemList[$v['order_id']][] = $v;
            unset($_order_items[$k]);
        }

        $db = kernel::database();
        foreach ($orders as $o) {
            if ($o['order_id'] && $o['is_fail'] == 'true') {
                $transaction = $db->beginTransaction();

                $order_items = $orderItemList[$o['order_id']];
                //释放冻结
                $batchList = [];
                foreach ($order_items as $item) {
                    $batchList[] = [
                        'bm_id'     =>  $item['product_id'],
                        'sm_id'     =>  $orderObjList[$item['obj_id']]['goods_id'],
                        'obj_type'  =>  material_basic_material_stock_freeze::__ORDER,
                        'bill_type' =>  0,
                        'obj_id'    =>  $item['order_id'],
                        'branch_id' =>  0,
                        'bmsq_id'   =>  material_basic_material_stock_freeze::__SHARE_STORE,
                        'num'       =>  $item['nums'],
                        'sync_sku'  =>  false,
                    ];
                }
                
                $err = '';
                kernel::single('material_basic_material_stock_freeze')->unfreezeBatch($batchList, __CLASS__.'::'.__FUNCTION__, $err);

                // 订单表信息
                kernel::database()->exec('DELETE FROM `sdb_ome_orders` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_order_extend` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_order_invoice` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_order_service` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_order_objects` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_order_items` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_order_pmt` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_payments` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_refund_apply` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_refunds` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_tbfx_orders` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_tbfx_order_objects` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_tbfx_order_items` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_tbgift_order_items` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_tbjz_orders` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_order_selling_agent` WHERE order_id='.$o['order_id']);
                kernel::database()->exec('DELETE FROM `sdb_ome_order_preprocess` WHERE preprocess_order_id='.$o['order_id']);
                
                // 删除订单后，service扩展触发其他服务
                foreach(kernel::servicelist('ome.service.order.fail.delete.after') as $service) {
                    if(method_exists($service, 'after_order_delete')) {
                        $service->after_order_delete($o['order_id']);
                    }
                }
                
                if(app::get('invoice')->is_installed()){
                    kernel::database()->exec('DELETE FROM `sdb_invoice_order` WHERE order_id='.$o['order_id']);
                }
                
                //记录操作日志
                $operLogObj->write_log('order_modify@ome', $o['order_id'], '删除失败订单号：'.$o['order_bn']);
                
                $retArr['isucc']++;
                $db->commit($transaction);
            } else {
                $retArr['ifail']++;
                $retArr['err_msg'][] = sprintf('%s：非失败订单', $o['order_bn']);
            }
        }

        echo json_encode($retArr),'ok.';exit;
    }
}
