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

class brush_finder_delivery{
    public $detail_basic = "发货单详情";
    public $detail_item = "基础物料详情";
    private $write = '2';


    function __construct(){}

    public $addon_cols = 'expre_status,status,order_createtime,shop_type';
    //显示状态
    public $column_process = "发货状态";
    public $column_process_width = "80";
    
    function column_process($row){
        $expre = $row[$this->col_prefix.'expre_status'];
        $status  = $row[$this->col_prefix.'status'];
        if($status == 'succ') {
            return '已发货';
        }
        if ($expre == 'true'){
            return '已打印';
        }else {
            return '未打印';
        }

    }

    //显示状态
    public $column_status = "打印状态";
    public $column_status_width = "80";

    function column_status($row) {
        $expre = $row[$this->col_prefix . 'expre_status'];
        $expreColor = ($expre == 'true') ? '#9a6913' : '#eeeeee';
        $ret = $this->getViewPanel('快递单', $expreColor);
        return $ret;
    }

    private function getViewPanel($caption, $color) {
        if ($color == '#eeeeee')
            $caption .= '未打印';
        else
            $caption .= '已打印';
        return sprintf("<div style='width:18px;padding:2px;height:16px;background-color:%s;float:left;'><span alt='%s' title='%s' style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>", $color, $caption, $caption, substr($caption, 0, 3));
    }

    //显示状态
    public $column_create = "下单距今";
    public $column_create_width = "100";
    public $column_create_order_field= 'order_createtime';
    function column_create($row) {
        $time = $row[$this->col_prefix . 'order_createtime'];
        $difftime = kernel::single('ome_func')->toTimeDiff(time(), $time);
        $status = $row[$this->col_prefix . 'status'];
        $days = $difftime['d'];
        $html = $difftime['d']?$difftime['d']. '天':'';
        $html .= $difftime['h']?$difftime['h'] . '小时':'';
        $html .= $difftime['m']?$difftime['m'] . '分':'';
        if ($status != 'succ') {
            if ($days >= 7) {
                $ret = "<div style='width:90px;height:20px;background-color:red;color:#FFFFFF;text-align:center;'>超过一周</div>";
            } elseif ($days >= 1) {
                $ret = "<div style='width:90px;height:20px;background-color:blue;color:#FFFFFF;text-align:center;'>" . $html . "</div>";
            } else {
                $ret = "<div style='width:90px;height:20px;background-color:green;color:#FFFFFF;text-align:center;'>" . $html . "</div>";
            }
        } else {
            $ret = "<div style='width:90px;height:20px;background-color:#dddddd;color:#FFFFFF;text-align:center;'>完成</div>";
        }
        return $ret;
    }

    
    public $column_beartime = "成单时间";
    public $column_beartime_width = '140';
    public $column_beartime_order_field= 'order_createtime';
    /**
     * column_beartime
     * @param mixed $row row
     * @return mixed 返回值
     */
    public function column_beartime($row) {
        return $row[$this->col_prefix . 'order_createtime'] ? date('Y-m-d H:i:s',$row[$this->col_prefix . 'order_createtime']) : '-';
    }

    function detail_basic($dly_id){
        $render = app::get('brush')->render();
        $dlyObj = app::get('brush')->model('delivery');
        $dlyOrderObj = app::get('brush')->model('delivery_order');
        $orderObj = app::get('ome')->model('orders');
        $ordeArchiveObj = app::get('archive')->model('orders');
        $braObj = app::get('ome')->model('branch');
        $opObj  = app::get('ome')->model('operation_log');
        $dlyCorpObj = app::get('ome')->model('dly_corp');
        $dly = $dlyObj->dump($dly_id);

        // 加密
        $dly['is_encrypt'] = kernel::single('ome_security_router',$dly['shop_type'])->show_encrypt($dly, 'delivery');
        
        $tmp = app::get('ome')->model('members')->dump($dly['member_id']);
        $dly['member_name'] = $tmp['account']['uname'];
        $dly['members'] = "手机：".$tmp['contact']['phone']['mobile']."<br>";
        $dly['members'] .= "电话：".$tmp['contact']['phone']['telephone']."<br>";
        if(empty($dly['branch_id'])) {
            $branch_id = 0;
        } else {
            $branch_id = $dly['branch_id'];
        };
        $shop = app::get('ome')->model('shop')->dump($dly['shop_id']);
        $dly['area'] = $shop['area'];

        /* 发货单日志 */
        $logdata = $opObj->read_log(array('obj_id'=>$dly_id,'obj_type'=>'delivery@brush'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }

        /* 同批处理的订单 */
        $order_ids = $dlyOrderObj->getList('order_id', array('delivery_id' => $dly_id));
        $orderLogs = array();
        $tax_titles = '';
        foreach($order_ids as $v){
            $order = $orderObj->dump($v,'order_id,order_bn,custom_mark,mark_text');
            if(empty($order)) {
                $order = $ordeArchiveObj->dump($v,'order_id,order_bn,custom_mark,mark_text');
            }
            //编号
            $order_bn[] = $order['order_bn'];
            $custom_mark[$v['order_bn']] = kernel::single('ome_func')->format_memo($order['custom_mark']);
            $mark_text[$v['order_bn']] = kernel::single('ome_func')->format_memo($order['mark_text']);
            if($v['tax_company'] != null){
                $tax_titles .= ($tax_titles ? '、' : '') . $v['tax_company'];
            }
            $orderLogs[$order['order_bn']] = $opObj->read_log(array('obj_id'=>$v,'obj_type'=>'orders@ome'), 0, -1);
            foreach($orderLogs[$order['order_bn']] as $k=>$v){
                if($v) {
                    $orderLogs[$order['order_bn']][$k]['operate_time'] = date('Y-m-d H:i:s', $v['operate_time']);
                }
            }
        }
        $corp = $dlyCorpObj->getList('corp_id,name,type,weight,tmpl_type,channel_id', array('disable'=>'false'), 0, -1);
        foreach($corp as $rCorp) {
            if($rCorp['corp_id'] == $dly['logi_id']) {
                $dlyCorp = $rCorp;
                break;
            }
        }
        //物流公司使用电子面单时物流单号不能被编辑(菜鸟）
        if ($dlyCorp['tmpl_type'] == 'electron' || $dlyCorp['tmpl_type'] == 'cainiao' || $dly['status'] == 'succ') {
            $this->write = 1;
            $dly['logi_name'] = $dlyCorp['name'];
        }
        $render->pagedata['custom_mark'] = $custom_mark;
        $render->pagedata['mark_text'] = $mark_text;
        $render->pagedata['write']    = $this->write;
        $render->pagedata['url']    = $this->url;
        $render->pagedata['log']      = $logdata;
        $render->pagedata['orderLogs'] = $orderLogs;
        $render->pagedata['dly_corp'] = $corp;
        $dly['create_time'] = date('Y-m-d H:i:s',$dly['create_time']);
        $render->pagedata['dly']      = $dly;
        $render->pagedata['order_bn'] = $order_bn;
        $render->pagedata['status'] = $_GET['status'];
        $render->pagedata['tax_titles'] = $tax_titles;

        return $render->fetch('admin/delivery/delivery_detail.html');
    }
    
    function detail_item($dly_id)
    {
        $basicMaterialObj = app::get('material')->model('basic_material');
        
        $render = app::get('brush')->render();
        
        $items = app::get('brush')->model('delivery_items')->getList('*', array('delivery_id' => $dly_id));
        foreach ($items as $key => $item){
            $productId[] = $item['product_id'];
        }
        
        $productList = array();
        $bmList = $basicMaterialObj->getList('bm_id,material_name,material_bn', array('bm_id'=>$productId));
        foreach ((array)$bmList as $key => $val)
        {
            $bm_id = $val['bm_id'];
            
            $val['name'] = $val['material_name'];
            $val['bn'] = $val['material_bn'];
            
            $productList[$bm_id] = $val;
        }
        
        $render->pagedata['items'] = $items;
        $render->pagedata['product'] = $productList;

        return $render->fetch('admin/delivery/delivery_item.html');
    }

    function row_style($row){
        $style='';
        if($row[$this->col_prefix.'is_cod'] == 'true'){
            $style .= " list-even ";
        }
        return $style;
    }

    public $column_order_marktext = "商家备注";
    /**
     * column_order_marktext
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_order_marktext($row,$list) {
        $orders = $this->_getOrders($row['delivery_id'],$list);

        $mark_text = array();
        foreach ($orders as $value) {
            $mark_text[] = $value['mark_text'];
        }

        return implode(';&nbsp;&nbsp;&nbsp;&nbsp;&nbsp', $mark_text);
    }

    public $column_order_custommark = "客户备注";
    /**
     * column_order_custommark
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_order_custommark($row,$list) {
        $orders = $this->_getOrders($row['delivery_id'],$list);

        $custom_mark = array();
        foreach ($orders as $value) {
            $custom_mark[] = $value['custom_mark'];
        }

        return implode(';&nbsp;&nbsp;&nbsp;&nbsp;&nbsp', $custom_mark);
    }

    public $column_order_bn = "订单号";
    /**
     * column_order_bn
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_order_bn($row,$list) {
        $orders = $this->_getOrders($row['delivery_id'],$list);

        $order_bn = array();
        foreach ($orders as $value) {
            $order_bn[] = $value['order_bn'];
        }

        return implode(',',$order_bn);
    }

    /**
     * summary
     * 
     * @return void
     * @author 
     */
    private function _getOrders($delivery_id, $list)
    {
        static $orders;

        if (isset($orders)) return $orders[$delivery_id];

        $orders = array();

        $filter['delivery_id'] = array(0);
        foreach ($list as $value) {
            $filter['delivery_id'][] = $value['delivery_id'];
        }

        $rows = kernel::database()->select('select o.order_id,o.order_bn,o.custom_mark,o.mark_text,od.delivery_id from sdb_ome_orders o join sdb_brush_delivery_order od on o.order_id=od.order_id and od.delivery_id in ('.implode(',',$filter['delivery_id']).')');

        foreach ($rows as $row) {
            $custom_mark = '';
            if($custom = kernel::single('ome_func')->format_memo($row['custom_mark'])){
                 // 取最后一条
                 $custom = array_pop($custom);
                 $custom_mark = $custom['op_content'];
            }
            $row['custom_mark'] = $custom_mark;

            $mark_text = '';
            if($mark = kernel::single('ome_func')->format_memo($row['mark_text'])){
                 // 取最后一条
                 $mark = array_pop($mark);
                 $mark_text = $mark['op_content'];
            }
            $row['mark_text'] = $mark_text;


            $orders[$row['delivery_id']][$row['order_id']] = $row;
        }

        return $orders[$delivery_id];
    }

}
