<?php
class ome_finder_return_reshipping
{
    var $addon_cols = "reshipping_id,shop_id,order_id,status,at_time,up_time,is_confirm_goods";

    var $detail_basic = "补寄申请单详情";

    /**
     * 补寄申请单详情
     */
    function detail_basic($reshipping_id)
    {
        $render = app::get('ome')->render();
        $reshippingModel = app::get('ome')->model('return_reshipping');
        $reshippingItemsModel = app::get('ome')->model('return_reshipping_items');
        $orderModel = app::get('ome')->model('orders');
        $operationLogModel = app::get('ome')->model('operation_log');

        // 获取补寄申请单基本信息
        $reshipping = $reshippingModel->db_dump($reshipping_id);
        $reshipping['buyer_address'] = $reshipping['buyer_province'] . $reshipping['buyer_city'] . $reshipping['buyer_district'] . $reshipping['buyer_town'] . $reshipping['buyer_address'];
        $reshipping['shop_name'] = app::get('ome')->model('shop')->db_dump(array('shop_id' => $reshipping['shop_id']), 'name')['name'];
        // 获取关联订单信息
        if ($reshipping['order_id']) {
            $order = $orderModel->db_dump(array('order_id' => $reshipping['order_id']), 'order_bn,platform_order_bn,ship_name,ship_mobile,ship_addr,shop_type');
            $reshipping['order_info'] = $order;
        }

        // 获取补发订单信息
        if ($reshipping['reissue_order_id']) {
            $reissueOrder = $orderModel->db_dump(array('order_id' => $reshipping['reissue_order_id']), 'order_bn,order_type,status');
            $reshipping['reissue_order_info'] = $reissueOrder;
        }

        // 获取补寄申请商品明细
        $items = $reshippingItemsModel->getList('*', array('reshipping_id' => $reshipping_id));
        $render->pagedata['items'] = $items;

        // 获取补寄确认商品明细
        $reshippingItemsDetailModel = app::get('ome')->model('return_reshipping_items_detail');
        $itemsDetail = $reshippingItemsDetailModel->getList('*', array('reshipping_id' => $reshipping_id));
        $render->pagedata['items_detail'] = $itemsDetail;

        // 状态映射
        $reshipping['status_name'] = isset($reshippingModel->status[$reshipping['status']]) ? $reshippingModel->status[$reshipping['status']] : $reshipping['status'];

        $render->pagedata['reshipping'] = $reshipping;
        $render->pagedata['finder_id'] = ($_GET['_finder']['finder_id'] ? $_GET['_finder']['finder_id'] : $_GET['finder_id']);

        return $render->fetch('admin/return/reshipping/detail.html');
    }

    /**
     * 操作列
     */
    var $column_edit = '操作';
    var $column_edit_width = '150';
    var $column_edit_order = 1;
    function column_edit($row)
    {
        $reshipping_id = $row[$this->col_prefix . 'reshipping_id'];
        $status = $row[$this->col_prefix . 'status'];
        $is_confirm_goods = $row[$this->col_prefix . 'is_confirm_goods'];
        $finder_id = $_GET['_finder']['finder_id'];
        
        $buttons = array();
        
        // 详情按钮（始终显示）
        $detail_url = 'index.php?app=ome&ctl=admin_return_reshipping&act=detail&id=' . $reshipping_id;
        if ($finder_id) {
            $detail_url .= '&finder_id=' . $finder_id;
        }
        $buttons[] = '<a href="' . $detail_url . '" target="_blank">详情</a>';
        
        // 等待卖家发货状态，且商品未确认，显示商品信息确认按钮
        if ($status == '1' && $is_confirm_goods == '0') {
            $confirm_url = 'index.php?app=ome&ctl=admin_return_reshipping&act=confirmGoodsDialog&reshipping_id=' . $reshipping_id;
            if ($finder_id) {
                $confirm_url .= '&finder_id=' . $finder_id;
            }
            $buttons[] = '<a href="' . $confirm_url . '" target="dialog::{width:800,height:600,title:\'商品信息确认\'}">商品信息确认</a>';
        }
        
        return implode(' | ', $buttons);
    }
    /**
     * 操作日志页签
     */
    public $detail_oplog = "操作记录";
    public function detail_oplog($id){
        $render = app::get('console')->render();
        $opObj  = app::get('ome')->model('operation_log');
        $logdata = $opObj->read_log(array('obj_id'=>$id,'obj_type'=>'return_reshipping@ome'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['log'] = $logdata;
        return $render->fetch('admin/oplog.html');
    }
}