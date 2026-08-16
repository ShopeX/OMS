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

class ome_finder_refund_apply{
    
    //平台售后状态
    static $_platformStatus = null;
    
    function __construct(){
        if($_GET['ctl'] == 'admin_refund_apply' ){
            //unset($this->detail_return_problem);
        }
    }
    
    var $detail_basic = "退款申请详情";
    var $detail_mark_text = '卖家备注';
    var $detail_return_problem = '售后问题类型';
    function detail_basic($apply_id){
        $render = app::get('ome')->render();
        $oRefund_apply = app::get('ome')->model('refund_apply');
        $refunddatas = $oRefund_apply->refund_apply_detail($apply_id);
        $oObj= app::get('ome')->model('orders');
        $render->pagedata['refunddata'] = $oRefund_apply->refund_apply_detail($apply_id);
        //根据订单号查询已退金额
        $oOrder = app::get('ome')->model('orders');
        $is_archive = kernel::single('archive_order')->is_archive($refunddatas['archive']);
       if ($is_archive) {
            $order_id = $render->pagedata['refunddata']['order_id'];
            $archive_ordObj = kernel::single('archive_interface_orders');
            $orderinfo = $archive_ordObj->getOrders(array('order_id'=>$order_id),'*');
        }else{
            $orderinfo = $oOrder->order_detail($render->pagedata['refunddata']['order_id']);
        }
        $oLoger = app::get('ome')->model('operation_log');
        $render->pagedata['payed'] = $orderinfo['payinfo']['cost_payment'];
        $plugin_html = '';
        if ($refunddatas['source'] == 'matrix') {
            //if($_POST['status']) $refunddatas['status'] = $_POST['status'];
            $plugin_html = kernel::single('ome_aftersale_service')->refund_detail($refunddatas);
       
        }
        $render->pagedata['plugin_html'] = $plugin_html;
        if ($refunddatas['source'] == 'matrix') {
            $refuse_button = kernel::single('ome_aftersale_service')->refund_button($apply_id,3);
            $render->pagedata['refuse_button'] = $refuse_button;
            $render->pagedata['refuse_button_url'] = $refuse_button['data'];
        }
        
        
        if ($_POST && $orderinfo['order_id'])
        {
            //验证原状态
            $data['apply_id'] = $apply_id;
            $apply_data = $oRefund_apply->refund_apply_detail($apply_id);
            $change_status = $_POST['status'];
            $pre_result = kernel::single('ome_aftersale_service')->pre_save_refund($apply_id,$_POST);
            if ($pre_result['rsp'] == 'fail') {
               kernel::single('desktop_controller')->splash('error','',$pre_result['msg'],'redirect');
            }
            switch ($change_status){
                case 1://未审核->审核中
                    if ($apply_data['status'] == 0){
                        $data['status'] = 1;
                        $order['pause'] = 'false';
                        //最近操作时间
                        $data['last_modified'] = time();
                        $oRefund_apply->save($data,true);
                        $oLoger->write_log('refund_verify@ome',$apply_id,"退款申请审核中");
                        if($refunddatas['return_id']){
                            $oLoger->write_log('return@ome',$refunddatas['return_id'],$refunddatas['refund_apply_bn']."退款申请审核中");
                        }
                    }
                    break;
                case 2:
                case 3:
                    if ($change_status == 2){
                        $refund_op = 'refund_pass@ome';
                        $refund_op_name = '同意';
                    }else {
                        $refund_op = 'refund_refuse@ome';
                        $refund_op_name = '拒绝';
                    }
                    //if ($apply_data['status'] == 1)
                    //{
                        $data['status'] = $change_status;
                        $data['last_modified'] = time();
                        $oRefund_apply->save($data,true);
                        $oLoger->write_log($refund_op,$apply_id,"退款申请 $refund_op_name");
                        if($refunddatas['return_id']){
                            if($change_status == 3){
                               $Oreturn_product = app::get('ome')->model('return_product');
                               $return_data = array ('return_id' => $refunddatas['return_id'], 'status' => '9', 'last_modified' => time () );
                               $Oreturn_product->tosave ( $return_data );
                            }
                            $oLoger->write_log('return@ome',$refunddatas['return_id'],$refunddatas['refund_apply_bn']."退款申请 $refund_op_name");
                        }                        
                          if($data['status'] == 3){
                               //将订单状态还原
                               kernel::single('ome_order_func')->update_order_pay_status($orderinfo['order_id'], true, __CLASS__.'::'.__FUNCTION__);
                                //生成售后单
                                kernel::single('sales_aftersale')->generate_aftersale($apply_id,'refund');
                          }
                   //}
                    break;
                default:
                    break;
                    //参数不正确，不进行任何更新。
            }
        }
        $render->pagedata['refunddata'] = $oRefund_apply->refund_apply_detail($apply_id);
        $render->pagedata['status_info'] = $render->pagedata['refunddata']['status'];
        $render->pagedata['log'] = $oLoger->read_log(array('obj_id'=>$apply_id,'obj_type'=>'refund_apply@ome'),0,-1);
        //发货单取消日志
        //根据发货单号获取
        $delivery_id = $this->_getDeliveryId($orderinfo['order_id']);
        if ($delivery_id) {
            $canceldeliv_log = $oLoger->read_log(array('obj_id'=>$delivery_id,'operation'=>'delivery_back@ome'),0,-1);
            foreach ( $canceldeliv_log as $k=>$deliv ) {
                $canceldeliv_log[$k]['delivery_bn'] = $this->_getDeliverybn($deliv['obj_id']);
            }
            $render->pagedata['deliveryinfo'] = $canceldeliv_log;
        }
        $render->pagedata['shop'] = app::get('ome')->model('shop')->db_dump(['shop_id' => $orderinfo['shop_id']]);
        $render->pagedata['status_display'] = ome_refund_func::refund_apply_status();
        $render->pagedata['orderinfo'] = $orderinfo;
        $render->pagedata['finder_id'] = $_GET['finder_id'];

        // 商责退运费：详情页展示 addon 平台扩展字段
        $refunddata = $render->pagedata['refunddata'];
        $render->pagedata['is_shangze_free_return'] = false;
        $render->pagedata['shangze_addon'] = [];
        if (kernel::single('ome_order_platform_taobao_aftersale')->isShangzeByTagType(
            isset($refunddata['tag_type']) ? $refunddata['tag_type'] : ''
        )) {
            $addon = isset($refunddata['addon']) ? $refunddata['addon'] : '';
            if (is_string($addon) && $addon !== '') {
                $addon = unserialize($addon);
            }
            $render->pagedata['is_shangze_free_return'] = true;
            $render->pagedata['shangze_addon'] = is_array($addon) ? $addon : [];
        }
        
        return $render->fetch('admin/refund/refundapp_detail.html');
    }
    
    var $addon_cols = 'archive,source,order_id,abnormal_status,bool_type,reship_id,return_id,source_status,status,refund_refer,shop_type,negotiate_sync_status,tag_type';
    
    /**
     * 备注
     * @param   type    $varname    description
     * @return  type    description
     * @access  public
     * @author cyyr24@sina.cn
     */
    function detail_mark_text($apply_id){
        $render = app::get('ome')->render();
        $oRefund_apply = app::get('ome')->model('refund_apply');
        if($_POST){
            $memo = array();
            $apply_id = $_POST['apply']['apply_id'];
            //取出原留言信息
            $oldmemo = $oRefund_apply->dump(array('apply_id'=>$apply_id), 'mark_text');
            $oldmemo= unserialize($oldmemo['mark_text']);
            $op_name = kernel::single('desktop_user')->get_name();
            if ($oldmemo)
            foreach($oldmemo as $k=>$v){
                $memo[] = $v;
            }
            $newmemo =  htmlspecialchars($_POST['apply']['custom_mark']);
            $newmemo = array('op_name'=>$op_name, 'op_time'=>date('Y-m-d H:i:s',time()), 'op_content'=>$newmemo);
            $memo[] = $newmemo;
            $_POST['apply']['mark_text'] = serialize($memo);
            $plainData = $_POST['apply'];
            $oRefund_apply->save($plainData);
           
        }
        $refund_detail = $oRefund_apply->dump($apply_id);
        $render->pagedata['base_dir'] = kernel::base_url();
        $refund_detail['mark_text'] = unserialize($refund_detail['mark_text']);
        if ($refund_detail['mark_text'])
        foreach ($refund_detail['mark_text'] as $k=>$v){
            if (!strstr($v['op_time'], "-")){
                $v['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                $refund_detail['mark_text'][$k]['op_time'] = $v['op_time'];
            }
        }
        $render->pagedata['refund']  = $refund_detail;
        return $render->fetch('admin/refund/detail_mark.html');
    }
    
    /**
     * 售后问题类型
     * @access  public
     * @author cyyr24@sina.cn
     */
    function detail_return_problem($apply_id){
        $render = app::get('ome')->render();
        $oRefund = app::get('ome')->model('refund_apply');
        $oProblem = app::get('ome')->model('return_product_problem');
        $problem_type = $oProblem->getList('problem_id,problem_name');
        if ($_POST) {
            $oRefund->update(array('problem_id'=>$_POST['problem_id']),array('apply_id'=>$_POST['apply_id']));
        }
        $refund = $oRefund->dump(array('apply_id'=>$apply_id),'apply_id,problem_id');
        $render->pagedata['refund'] = $refund;
        $render->pagedata['problem_type'] = $problem_type;
        return $render->fetch('admin/refund/return_problem.html');
    }
    
    var $column_consignee_name = "收货人";
    var $column_consignee_name_width = "100";
    function column_consignee_name($row){
        $archive = $row[$this->col_prefix . 'archive'];
        $source = $row[$this->col_prefix . 'source'];
        $order_id = $row[$this->col_prefix . 'order_id'];
        if ($archive == '1' || in_array($source,array('archive'))) {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $order = $archive_ordObj->getOrders(array('order_id'=>$order_id),'ship_name,shop_type');
        }else{
            $orderObj = app::get('ome')->model('orders');
            $order = $orderObj->dump($order_id,'ship_name,shop_type');
        }
        if (kernel::single('ome_security_router',$order['shop_type'])->is_encrypt(array('ship_name'=>$order['consignee']['name']),'order')) {
            return kernel::single('ome_view_helper2')->modifier_ciphertext($order['consignee']['name'],'order','ship_name');
        }else{
            return $order['consignee']['name'];
        }
        
    }

    var $column_member_name = '会员用户名';
    var $column_member_name_width = '100';
    function column_member_name($row) {
        $archive = $row[$this->col_prefix . 'archive'];
        $source = $row[$this->col_prefix . 'source'];
        $order_id = $row[$this->col_prefix . 'order_id'];
        if ($archive == '1' || in_array($source,array('archive'))) {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $order = $archive_ordObj->getOrders(array('order_id'=>$order_id),'member_id,shop_id');
        }else{
            $orderObj = app::get('ome')->model('orders');
            $order = $orderObj->dump($order_id,'member_id,shop_id');
        }
        $member_id = $order['member_id'];
        $member = app::get('ome')->model('members')->dump(array('member_id' => $member_id));
        if ($member) {
            $type = app::get('ome')->model('shop')->dump(array('shop_id' => $order['shop_id']), 'shop_type');
            if ($type['shop_type'] == 'taobao') {
                return kernel::single('ome_order_func')->getWangWangHtml(['nick'=>$member['account']['uname'], 'encryptuid'=>$member['buyer_open_uid']]);
            } else {
                return $member['account']['uname'];
            }
        } 
    }
    
    /**
     * 获取订单对应取消发货单ID
     * @access  public
     * @author sunjing@shopex.cn
     */
    function _getDeliveryId($order_id){
        $deliveryObj = app::get('ome')->model('delivery');
        $sql = "SELECT d.delivery_id FROM sdb_ome_delivery_order as d LEFT JOIN sdb_ome_orders as o ON d.order_id=o.order_id LEFT JOIN sdb_ome_delivery as dd ON dd.delivery_id=d.delivery_id WHERE d.order_id=".$order_id."  ";
        $delivery = $deliveryObj->db->select($sql);
        $delivery_id = array();
        if ($delivery) {
            foreach ( $delivery as $de ) {
                $delivery_id[] = $de['delivery_id'];
            }
        }
        return $delivery_id;
    }

    /**
     * 返回发货单号
     * @access public
     * @author sunjing@shopex.cn
     */
    function _getDeliverybn($delivery_id){
        $deliveryObj = app::get('ome')->model('delivery');
        $delivery = $deliveryObj->db->selectrow("SELECT delivery_bn FROM sdb_ome_delivery WHERE delivery_id=".$delivery_id);
        return $delivery['delivery_bn'];
    }
    
    var $column_order_id='订单号';
    var $column_order_id_width='200';
    function column_order_id($row){
        $archive = $row[$this->col_prefix . 'archive'];
        $source = $row[$this->col_prefix . 'source'];
        $order_id = $row[$this->col_prefix . 'order_id'];
        if ($archive == '1' || in_array($source,array('archive'))) {
            $archive_ordObj = kernel::single('archive_interface_orders');
            $filter = array('order_id'=>$order_id);
            $order = $archive_ordObj->getOrders($filter,'order_bn');
        }else{
            $orderObj = app::get('ome')->model('orders');
            $filter = array('order_id'=>$order_id);
            $order = $orderObj->dump($filter,'order_bn');
        }
        return $order['order_bn'];
    }

    var $column_abnormal_status='异常标识';
    function column_abnormal_status($row)
    {
        return kernel::single('ome_constants_refundapply_abnormal')->getIdentifier($row[$this->col_prefix.'abnormal_status']);
    }

    var $column_bool_type='单据种类';
    function column_bool_type($row)
    {
        return kernel::single('ome_refund_bool_type')->getBoolTypeIdentifier($row[$this->col_prefix.'bool_type']);
    }
    
    var $column_source_status = '平台退款状态';
    var $column_source_status_width = 130;
    function column_source_status($row)
    {
        $platform_status = $row[$this->col_prefix.'source_status'];
        
        //check
        if(empty($platform_status)){
            return '';
        }
        
        //平台售后状态列表
        if(empty(self::$_platformStatus)){
            self::$_platformStatus = kernel::single('ome_refund_func')->get_source_status('all', 'all');
        }
        
        return self::$_platformStatus[$platform_status];
    }
    
    public $column_edit = "操作";
    public $column_edit_width = 120;
    public $column_edit_order = 1;
    public function column_edit($row){
        $btn = [];
        
        // 获取状态和售后退款标识
        $apply_id = $row['apply_id'];
        $status = $row[$this->col_prefix.'status'];
        $refund_refer = isset($row[$this->col_prefix.'refund_refer']) ? $row[$this->col_prefix.'refund_refer'] : '0';
        $tag_type = isset($row[$this->col_prefix.'tag_type']) ? $row[$this->col_prefix.'tag_type'] : $row['tag_type'];
        
        // 商责退运费：列表不允许有任何操作按钮
        if (kernel::single('ome_order_platform_taobao_aftersale')->isShangzeByTagType($tag_type)) {
            return '';
        }
        
        // 条件：售后退款(refund_refer=1)且退款完成(status=4)
        if ($status == '4' && $refund_refer == '1' && empty($row[$this->col_prefix.'reship_id'])) {
            $btn[] = '<a class="lnk" href="javascript:if(confirm(\'确定生成退货单?\')) {W.page(\'index.php?app=ome&ctl=admin_refund_apply&act=transfer_reship&p[0]='.$apply_id.'&finder_id='.$_GET['_finder']['finder_id'].'\');};">生成退货单</a>';
        }

        // 添加协商按钮 - 限制条件：source != 'local' && status = 0|1|2 && 支持taobao和tmall
        $source = $row[$this->col_prefix . 'source'];
        $shop_type = $row[$this->col_prefix.'shop_type'];
        $negotiate_sync_status = $row[$this->col_prefix.'negotiate_sync_status'];
        $status = $row[$this->col_prefix.'status'];
        if ($source != 'local' && in_array($status, array('0', '1', '2')) && in_array($shop_type, array('taobao', 'tmall')) && in_array($negotiate_sync_status,['pending'])) {
            $btn[]= '<a href="index.php?app=ome&ctl=admin_refund_apply&act=merchant_negotiation&p[0]='.$row['apply_id'].'&finder_id='.$_GET['_finder']['finder_id'].'" target="dialog::{width:1200,height:680,title:\'商家协商\'}">协商</a>';
        }
        
        return implode('|', $btn);
    }
}
?>
