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

class ome_finder_return_product{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "售后服务详情";
    
    //平台售后状态
    static $_platformStatus = null;
    
    function __construct($app){
        $this->app = $app;
        if($_GET['app']!='ome'){
            unset($this->column_edit);
        }
    }

    function detail_basic($return_id){
        $render         = app::get('ome')->render();
        $oProduct       = app::get('ome')->model('return_product');
        $oProduct_items = app::get('ome')->model('return_product_items');
        $oProcess_items = app::get('ome')->model('return_process_items');
        $oReship_item   = app::get('ome')->model('reship_items');
        $oOrder         = app::get('ome')->model('orders');
        $oBranch        = app::get('ome')->model('branch');
        $oReship   = app::get('ome')->model('reship');
        $oDly_corp   = app::get('ome')->model('dly_corp');

        if ($_POST['delivery_id']){
            foreach($_POST['item_id'] as $key => $val){
                $item = array();
                $item['item_id'] = $val;
                $branch_id = $_POST['branch_id'.$val];
                $item['branch_id'] = $branch_id;
                $oProduct_items->save($item);
           }
           $return_product['return_id'] = $return_id;
           $return_product['delivery_id'] = $_POST['delivery_id'];
           $oProduct->save($return_product);
        }
        $product_detail = $oProduct->product_detail($return_id);
        $is_archive = kernel::single('archive_order')->is_archive($product_detail['source']);
        if ($is_archive || $product_detail['archive']=='1') {
           
            $archive_ordObj = kernel::single('archive_interface_orders');
            $order_detail = $archive_ordObj->getOrders(array('order_id'=>$product_detail['order_id']),'*');
        }else{
            $order_detail = $oOrder->dump($product_detail['order_id']);
        }
        $reshipinfo = $oReship->dump(array('return_id'=>$return_id),'return_logi_name,return_logi_no');
        if($reshipinfo){
            $corpinfo = $oDly_corp->dump($reshipinfo['return_logi_name'],'name');
            $product_detail['process_data']['shipcompany'] = is_array($product_detail['process_data']) && $product_detail['process_data']['shipcompany']?$product_detail['process_data']['shipcompany']:$corpinfo['name'];
            $product_detail['process_data']['shiplogino'] = is_array($product_detail['process_data']) && $product_detail['process_data']['logino']?$product_detail['process_data']['logino']:$reshipinfo['return_logi_no'];
        }

        $lucky_flag = false;
        $order_id = $product_detail['order_id'];
        if (!$product_detail['delivery_id']){
            $product_items = array();
            if ($product_detail['items'])
               foreach($product_detail['items'] as $k=>$v){
                $refund = $oReship_item->Get_refund_count($order_id,$v['bn']);
                $v['effective']=$refund;
                $v['branch']=$oReship_item->getBranchCodeByBnAndOd($v['bn'],$order_id);
                
                $v['check_num']=$oProduct_items->getList('num', array('bn'=>$v['bn'],'order_id'=>$order_id));
                
                //福袋组合编码
                if($v['combine_bn']){
                    $lucky_flag = true;
                }
                
                //price
                $v['price'] = sprintf("%.2f", $v['price']);
                $v['amount'] = sprintf("%.2f", $v['amount']);
                
                $product_items[] = $v;
            }
            //获取仓库模式
            $branch_mode = app::get('ome')->getConf('ome.branch.mode');
            $render->pagedata['branch_mode'] = $branch_mode;
            $product_detail['items'] = $product_items;
        }else{
            if ($product_detail['items']){
                foreach($product_detail['items'] as $itemKey => $itemVal)
                {
                    //price
                    $product_detail['items'][$itemKey]['price'] = sprintf("%.2f", $itemVal['price']);
                    $product_detail['items'][$itemKey]['amount'] = sprintf("%.2f", $itemVal['amount']);
                    
                    //福袋组合编码
                    if($itemVal['combine_bn']){
                        $lucky_flag = true;
                    }
                }
            }
        }
        
        //增加售后服务详情显示前的扩展
        foreach(kernel::servicelist('ome.aftersale') as $o){
            if(method_exists($o,'pre_detail_display')){
                $o->pre_detail_display($product_detail);
            }
        }
        if (!is_numeric($product_detail['attachment'])){
            $render->pagedata['attachment_type'] = 'remote';
            $attachment = explode("|",$product_detail['attachment']);
            if($attachment[0]!='')$product_detail['attachment'] = $attachment;
        }
        if ($product_detail['source']=='matrix') {
            $plugin_html_show = kernel::single('ome_aftersale_service')->return_product_detail($product_detail);
        
            $render->pagedata['plugin_html_show'] = $plugin_html_show;
            //售后拒绝按钮
            $return_button = kernel::single('ome_aftersale_service')->return_button($return_id,'5');
            $render->pagedata['return_button'] = json_encode($return_button);    
        }
        
        $cnum=$oProcess_items->getList('num,bn,branch_id',array('order_id'=>$order_id));
        if ($cnum){
            foreach($cnum as $k=>$v){
                $pro_num[$v['bn']][$v['branch_id']] = $v['num'];
            }
            foreach($product_detail['check_data'] as $k=>$v){
                $product_detail['check_data'][$k]['num'] = $pro_num[$v['bn']][$v['branch_id']];
            }
        }
        
        // $pcount = $oProduct->count(array('order_id'=>$product_detail['order_id']));
        // if($pcount > 1){
        //    $render->pagedata['is_return_order'] = true;
        // }else{
           $render->pagedata['is_return_order'] = false;
        // }
        $choose_type_flag = 1;
        $shop_id = $product_detail['shop_id'];
        $router = kernel::single('ome_aftersale_request');
        if ($product_detail['source']=='matrix') {

            if ($product_detail['shop_type'] == 'tmall' || $product_detail['return_type'] == 'jdchange'){
                $return_model = $this->app->model('return_product_tmall');
                $return_tmall = kernel::single('ome_service_aftersale')->get_return_type(array('return_id' => $return_id));
            }

            if (!$router->setShopId($shop_id)->choose_type()) {
                $choose_type_flag = 0;
            }

            if ($return_tmall && $return_tmall['refund_type'] == 'change'){//天猫换货
                $choose_type_flag = 1;
            }
        }
        $render->pagedata['shop'] = app::get('ome')->model('shop')->db_dump(['shop_id' => $shop_id]);
        $render->pagedata['choose_type_flag'] = $choose_type_flag;
        $render->pagedata['product'] = $product_detail;
        $render->pagedata['order'] = $order_detail;
        $render->pagedata['lucky_flag'] = $lucky_flag;
        
        return $render->fetch('admin/return_product/detail/basic.html');
    }

    public $detail_member_info = '会员信息';
    /**
     * detail_member_info
     * @param mixed $return_id ID
     * @return mixed 返回值
     */
    public function detail_member_info($return_id)
    {
        $render = $this->app->render();

        $preturnModel = $this->app->model('return_product');
        $return_detail = $preturnModel->select()->columns('member_id,delivery_id')->where('return_id=?',$return_id)->instance()->fetch_row();
        $render->pagedata['memberInfo'] = $this->app->model('members')->select()->columns('uname,tel,zip,email,mobile')
                                            ->where('member_id=?',(int)$return_detail['member_id'])
                                            ->instance()->fetch_row();

        $oProduct_delivery = $this->app->model('delivery');
        $render->pagedata['delivery']=$oProduct_delivery->dump($return_detail['delivery_id'],'ship_area,ship_name,ship_addr,ship_zip,ship_tel,ship_email,ship_mobile');
        
        return $render->fetch('admin/return_product/detail/member_info.html');
    }

    public $detail_operation_log = '操作日志';
    /**
     * detail_operation_log
     * @param mixed $return_id ID
     * @return mixed 返回值
     */
    public function detail_operation_log($return_id)
    {
        $render = $this->app->render();

        $preturnModel = $this->app->model('return_product');
        $return_detail = $preturnModel->select()->columns('return_bn')->where('return_id=?',$return_id)->instance()->fetch_row();

        $opLogModel = $this->app->model('operation_log');
        $logFilter = array('obj_type'=>'return_product@ome','obj_id'=>$return_id,'obj_name'=>$return_detail['return_bn']);

        $render->pagedata['logs'] = $opLogModel->read_log($logFilter,0,20,'log_id');

        if ($this->vue_mode) { return []; }
        return $render->fetch('admin/return_product/detail/operation_log.html');
    }

    var $column_edit = "操作";
    var $column_edit_width = "200";
    function column_edit($row){
        if ($_REQUEST['act'] == 'jingxiao') {
            return '';
        }

        if(!kernel::single('desktop_user')->is_super()){
            $returnLib = kernel::single('ome_return');

            $has_permission = $returnLib->chkground('aftersale_center','','aftersale_return_edit');
            if (!$has_permission) {
                return false;
            }

        }

        $buttons = '';
        if($row['status'] == '1'||$row['status'] == '2'){
           $buttons .= '<a  href="index.php?app=ome&ctl=admin_return&act=edit&p[0]='.$row['return_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">编辑</a>  ';
        }
        
        // 添加协商按钮 - 限制条件：source != 'local' && status = 1|2|3 && 支持taobao和tmall
        $source = $row[$this->col_prefix.'source'];
        $shop_type = $row[$this->col_prefix.'shop_type'];
        $negotiate_sync_status = $row[$this->col_prefix.'negotiate_sync_status'];
        $status = $row[$this->col_prefix.'status'];
        if ($source != 'local' && in_array($status, array('1', '2', '3')) && in_array($shop_type, array('taobao', 'tmall')) && in_array($negotiate_sync_status,['pending'])) {
            $buttons .= '<a href="index.php?app=ome&ctl=admin_return&act=merchant_negotiation&p[0]='.$row['return_id'].'&finder_id='.$_GET['_finder']['finder_id'].'" target="dialog::{width:1200,height:680,title:\'商家协商\'}">协商</a>';
        }
        
        return $buttons;
    }

    var $addon_cols = 'archive,source,order_id,delivery_id,shop_type,return_type,return_id,platform_status,flag_type,negotiate_sync_status,status';
    var $column_order_id = '订单号';
    var $column_order_id_width = '180';
    function column_order_id($row)
    {
        $archive = $row[$this->col_prefix . 'archive'];
        $source = $row[$this->col_prefix . 'source'];
        $order_id = $row[$this->col_prefix . 'order_id'];
        if ($archive == '1' || in_array($source,array('archive'))) {
            
            $archive_ordObj = kernel::single('archive_interface_orders');
            $filter = array('order_id'=>$order_id);
            $order = $archive_ordObj->getOrders($filter,'*');
        }else{
            $orderObj = app::get('ome')->model('orders');
            $filter = array('order_id'=>$order_id);
            $order = $orderObj->dump($filter,'order_bn');
        }
        return $order['order_bn'];
    }

    /**
     * row_style
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function row_style($row)
    {

        if ($row[$this->col_prefix.'source'] == 'matrix' && $row[$this->col_prefix.'shop_type']=='tmall' && $row[$this->col_prefix.'return_type'] == 'change'){
            $style.= 'selected';
        }
        return $style;
    }

    var $column_time_out = '超时时间';
    var $column_time_out_width='120';
    function column_time_out($row){

        if ($row[$this->col_prefix.'source'] == 'matrix' && $row[$this->col_prefix.'shop_type']=='tmall' && $row[$this->col_prefix.'return_type'] == 'change'){
            $tmallObj = app::get('ome')->model('return_product_tmall');
            $tmall_detail = $tmallObj->dump(array('return_id'=>$row[$this->col_prefix . 'return_id'],'refund_type'=>'change'),'current_phase_timeout');
            if ($tmall_detail && $tmall_detail['current_phase_timeout']){
               return "<div style='width:120px;height:20px;background-color:green;color:#FFFFFF;text-align:center;'>".date('Y-m-d H:i:s',$tmall_detail['current_phase_timeout'])."</div>";;
            }

        }else{
            return '-';
        }

    }
    
    var $column_platform_status = '平台售后状态';
    var $column_platform_status_width = 130;
    function column_platform_status($row)
    {
        $platform_status = $row[$this->col_prefix.'platform_status'];
        
        //check
        if(empty($platform_status)){
            return '';
        }
        
        //平台售后状态列表
        if(empty(self::$_platformStatus)){
            $reshipLib = kernel::single('ome_reship');
            self::$_platformStatus = $reshipLib->get_platform_status();
        }
        
        return self::$_platformStatus[$platform_status];
    }
    
    //退货单标识
    var $column_fail_status = '标识';
    var $column_fail_status_width = 260;
    var $column_fail_status_order = 110;
    
    function column_fail_status($row)
    {
        $flag_type = $row[$this->col_prefix . 'flag_type'];
        return kernel::single('ome_reship_const')->getHtml($flag_type);
    }
    
}
?>