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

class wms_finder_delivery{
    var $detail_basic = "发货单详情";
    var $detail_item = "基础物料详情";
    var $detail_delivery = "物流单列表";
    var $detail_serial = "唯一码详情";
    var $detail_storagelife = "保质期批次详情";

    private $write = '1';
    private $write_memo = '1';
    var $has_many = array(
        'members' => 'members',
    );
    
    //核销状态
    public $_writeoffStatus = array (
        '0' => '未核销',
        '1' => '成功核销',
        '2' => '查询成功',
        '3' => '查询失败',
        '4' => '核销失败',
    );
    
    var $addon_cols = "skuNum,itemNum,bnsContent,delivery_id,status,process_status,print_status,type,bind_key,order_createtime,deli_cfg,is_cod,outer_delivery_bn,logi_number,delivery_logi_number,bool_type,shop_type,cpup_service,writeoff_status";

    function __construct(){
        if($_GET['ctl'] == 'admin_receipts_print' && $_GET['act'] == 'index'){
            $this->write = '2';
            $this->write_memo = '2';
            $this->url = 'admin_receipts_print';
        }elseif($_GET['ctl'] == 'admin_refunded' && $_GET['act'] == 'index'){
            $this->write = '2';
            $this->write_memo = '2';
            $this->url = 'admin_refunded';
        }else{
            unset($this->column_op);
        }
    }

    //单据状态
    var $column_status = "单据状态";
    var $column_status_width = "80";
    function column_status($row){
        $status = $row[$this->col_prefix.'status'];
        switch($status){
            case 0:
                return '处理中';
                break;
            case 1:
                return '取消';
                break;
            case 2:
                return '暂停';
                break;
            case 3:
                return '已完成';
                break;
        }
    }

    //处理状态
    var $column_process_status = "处理状态";
    var $column_process_status_width = "80";
    function column_process_status($row){
        $process_status  = $row[$this->col_prefix.'process_status'];
        $tmp_status = '未打印';
        if(($process_status & 1) == 1){
            $tmp_status = '已打印';
        }
        if(($process_status & 2) == 2){
            $tmp_status = '已校验';
        }
        if(($process_status & 4) == 4){
            $tmp_status = '已称重打包';
        }
        if(($process_status & 8) == 8){
            $tmp_status = '已物流交接';
        }
        return $tmp_status;
    }

    //打印状态
    var $column_print_status = "打印状态";
    var $column_print_status_width = "80";
    function column_print_status($row){
        $print_status  = $row[$this->col_prefix.'print_status'];
        $stock = false;
        $deliv = false;
        $expre = false;
        if(($print_status & 1) == 1){
            $stock = true;
        }
        if(($print_status & 2) == 2){
            $deliv = true;
        }
        if(($print_status & 4) == 4){
            $expre = true;
        }

        $stockColor = ($stock == 'true') ? 'green' : '#eeeeee';
        $delivColor = ($deliv == 'true') ? 'red' : '#eeeeee';
        $expreColor = ($expre == 'true') ? '#9a6913' : '#eeeeee';
        $ret = $this->getViewPanel('备货单', $stockColor);
        $ret .= $this->getViewPanel('发货单', $delivColor);
        $ret .= $this->getViewPanel('快递单', $expreColor);
        return $ret;
    }

    private function getViewPanel($caption, $color) {
        if ($color == '#eeeeee')
            $caption .= '未打印';
        else
            $caption .= '已打印';
        return sprintf("<div style='width:18px;padding:2px;height:16px;background-color:%s;float:left;'><span alt='%s' title='%s' style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>", $color, $caption, $caption, substr($caption, 0, 3));
    }

    var $column_create = "下单距今";
    var $column_create_width = "100";
    var $column_create_order_field= 'order_createtime';
    function column_create($row) {
        $time = $row[$this->col_prefix . 'order_createtime'];
        $difftime = kernel::single('ome_func')->toTimeDiff(time(), $time);
        $status = $row[$this->col_prefix . 'status'];
        $days = $difftime['d'];
        $html .= $difftime['d']?$difftime['d']. '天':'';
        $html .= $difftime['h']?$difftime['h'] . '小时':'';
        $html .= $difftime['m']?$difftime['m'] . '分':'';
        if ($status != 3) {
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

    var $column_beartime = "成单时间";
    var $column_beartime_width = '140';
    var $column_beartime_order_field= 'order_createtime';
    public function column_beartime($row) {
        return $row[$this->col_prefix . 'order_createtime'] ? date('Y-m-d H:i:s',$row[$this->col_prefix . 'order_createtime']) : '-';
    }

    var $column_content = "订单内容";
    var $column_content_width = "160";
    function column_content($row) {
        $skuNum = $row[$this->col_prefix . 'skuNum'];
        $itemNum = $row[$this->col_prefix . 'itemNum'];
        $content = $row[$this->col_prefix . 'bnsContent'];

        $cnts = unserialize($content);
        $cnt = sprintf("共有 %d 种基础物料，总共数量为 %d 件， 具体 SKU 为： %s", $skuNum, $itemNum, @implode(', ', $cnts));

        @reset($cnts);
        $content = $cnts[@key($cnts)];
        if ($skuNum >1) {
            $content .= ' 等';
        }
        return sprintf("<span alt='%s' title='%s'><font color='red'>(%d / %d)</font> %s</span>",$cnt, $cnt, $skuNum, $itemNum, $content);
    }
    
    
    var $column_product_name = "基础物料名称";
    var $column_product_name_width = "160";
    
    function column_product_name($row,$list) {
        
        $skuNum = $row[$this->col_prefix . 'skuNum'];
        $itemNum = $row[$this->col_prefix . 'itemNum'];
        $content = $row[$this->col_prefix . 'bnsContent'];
    
        $productName = $this->_getMaterialNames($list);
        $cnts = unserialize($content);
        $product_id = array_keys($cnts);
        foreach ($product_id as $pid) {
            $names[] = $productName[$pid];
        }
        $cnt = sprintf("共有 %d 种基础物料，总共数量为 %d 件， 具体 名称 为： %s", $skuNum, $itemNum, @implode(', ', $names));
        
        @reset($names);
        $content = $names[@key($names)];
        if ($skuNum >1) {
            
            $content .= ' 等';
        }

        return sprintf("<span alt='%s' title='%s'><font color='red'></font> %s</span>",$cnt, $cnt, $content);
    }
    
    /*
     * 获取商品名称
     */
    public function _getMaterialNames($list)
    {
        
        static $newData;
        if (isset($newData)) {
            return $newData;
        }
        
        $bm_id = array();
        foreach ($list as $key => $value) {
            $cnts = unserialize($value[$this->col_prefix .'bnsContent']);
            $bm_id = array_unique(array_merge($bm_id,array_keys($cnts)));
        }
        
        $filter['bm_id'] = $bm_id;
        $productList = app::get('material')->model('basic_material')->getList('bm_id,material_name',$filter);
        foreach ($productList as $v) {
            $newData[$v['bm_id']] = $v['material_name'];
        }
        return $newData;
    }
    
    var $column_deliveryNumInfo = "物流单号-多包";
    var $column_deliveryNumInfo_width = "160";
    function column_deliveryNumInfo($row){

        #获取主物流单号
        $dlyBillLib    = kernel::single('wms_delivery_bill');
        $logi_no       = $dlyBillLib->getPrimaryLogiNoById($row['delivery_id']);

        if($row[$this->col_prefix.'logi_number']>1)
        {
            $str    = "共有 ".$row[$this->col_prefix.'logi_number']." 单物流单，已完成发货 ".$row[$this->col_prefix.'delivery_logi_number']." 单 主物流单号为 ".$logi_no;

            return  '<span title="'.$str.'" alt="'.$str.'"><font color="red">('.$row[$this->col_prefix.'delivery_logi_number'].' / '.$row[$this->col_prefix.'logi_number'].')</font> '.$logi_no.'</span>';
        }else{
            return $logi_no;
        }
    }

    function row_style($row){
        $style='';
        if($row[$this->col_prefix.'is_cod'] == 'true'){
            $style .= " list-even ";
        }
        return $style;
    }

    function detail_basic($dly_id){
        $render = app::get('wms')->render();
        $dlyObj = app::get('wms')->model('delivery');
        $dlyBillLib = kernel::single('wms_delivery_bill');
        $orderObj = app::get('ome')->model('orders');
        $braObj = app::get('ome')->model('branch');
        $opObj  = app::get('ome')->model('operation_log');
        $omeExtOrdLib = kernel::single('ome_extint_order');
        $memberObj = app::get('ome')->model('members');
        $finder_id = $_GET['_finder']['finder_id'];
        $render->pagedata['finder_id'] = $finder_id;
        $dly = $dlyObj->dump($dly_id);

        // 加密
        $dly['is_encrypt'] = kernel::single('ome_security_router',$dly['shop_type'])->show_encrypt($dly, 'delivery');


        $tmp = $memberObj->dump($dly['member_id']);

        $dly['member_name'] = $tmp['account']['uname'];
        $dly['members'] = "手机：".$tmp['contact']['phone']['mobile']."<br>";
        $dly['members'] .= "电话：".$tmp['contact']['phone']['telephone']."<br>";
        empty($dly['branch_id'])?$branch_id=0:$branch_id=$dly['branch_id'];
        //$shop = $dlyObj->getShopInfo($dly['shop_id']);
        //$dly['area'] = $shop['area'];

        //获取主物流单号
        $logi_no = $dlyBillLib->getPrimaryLogiNoById($dly_id);
        $dly['logi_no'] = $logi_no;

        //发货单日志
        $logdata = $opObj->read_log(array('obj_id'=>$dly_id,'obj_type'=>'delivery@wms'), 0, -1);
        foreach($logdata as $k=>$v){
            $logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
        }
        $render->pagedata['log'] = $logdata;

        //发货单关联订单号
        $order_bns = $omeExtOrdLib->getOrderBns($dly['outer_delivery_bn']);
        $render->pagedata['order_bn'] = $order_bns;

        //买家备注、商家备注要走接口查询?还是wms冗余
        $res = $omeExtOrdLib->getMemoByDlyId($dly['outer_delivery_bn']);
        $render->pagedata['custom_mark'] = $res['custom_mark'];#会员备注与订单备注信息
        $render->pagedata['mark_text'] = $res['mark_text'];#会员备注与订单备注信息

        $render->pagedata['write']    = $this->write;
        $render->pagedata['write_memo']    = $this->write_memo;
        $dlyCorpObj = app::get('ome')->model('dly_corp');
        $dlyCorp = $dlyCorpObj->dump($dly['logi_id'], 'prt_tmpl_id,type,tmpl_type');
        //物流公司使用电子面单时物流单号不能被编辑
        if ($dlyCorp['tmpl_type'] == 'electron') {
            $render->pagedata['write'] = 1;
        }
        if ($dlyCorp['tmpl_type'] == 'electron' && $dly['status']!='succ') {
            $render->pagedata['write_memo'] = 1;
        }
        $render->pagedata['url']    = $this->url;

        $render->pagedata['dly_corp'] = $braObj->get_corp($branch_id,$dly['consignee']['area']);
        $dly['create_time'] = date('Y-m-d H:i:s',$dly['create_time']);

        //根据原始发货单获取配送方式
        $shipping_type = kernel::single('ome_interface_delivery')->getOmeDlyShipType($dly['outer_delivery_bn']);
        $dly['delivery'] = $shipping_type;
        
        //销售状态
        $writeoff_status = $dly['writeoff_status'];
        $dly['writeoff_status_name'] = $this->_writeoffStatus[$writeoff_status];
        
        //delivery
        $render->pagedata['dly']      = $dly;

        $render->pagedata['status'] = $_GET['status'];

        return $render->fetch('admin/delivery/delivery_detail.html');
    }

    function detail_item($dly_id){
        $render = app::get('wms')->render();
        $dlyObj = app::get('wms')->model('delivery');
        //$brandObj = app::get('ome')->model('brand');

        $items = $dlyObj->getItemsByDeliveryId($dly_id);
        $delivery = $dlyObj->dump($dly_id);
        //$product_info = $brandObj->getBrandName($items[0]['product_name']);

        #商品品牌
        $brandList    = array();
        $oBrand       = app::get('ome')->model('brand');
        $tempData     = $oBrand->getList('brand_id, brand_name', '', 0, -1);
        foreach ($tempData as $key => $val)
        {
            $brandList[$val['brand_id']]    = $val['brand_name'];
        }
        unset($tempData, $oBrand);
        
        #基础物料扩展数据
        $basicMaterialExtObj    = app::get('material')->model('basic_material_ext');
        if($items)
        {
            foreach ($items as $key => $val)
            {
                $material_ext    = $basicMaterialExtObj->dump(array('bm_id'=>$val['product_id']), 'bm_id, specifications, brand_id, cat_id');
                $items[$key]['specifications']    = $material_ext['specifications'];
                
                if($material_ext['brand_id'])
                {
                    $items[$key]['brand_name']    = $brandList[$material_ext['brand_id']];
                }
            }
        }
        //销售价权限判断
        $showSalePrice = true;
        if (!kernel::single('desktop_user')->has_permission('sale_price')) {
            $showSalePrice = false;
        }
        $render->pagedata['show_sale_price'] = $showSalePrice;
        $render->pagedata['write'] = $this->write;
        $render->pagedata['items'] = $items;
        $render->pagedata['dly']   = $delivery;

        if ($delivery['shop_type'] == 'vop') {
            $checkMdl = app::get('ome')->model('order_objects_check_items');
            $outer_delivery_bn = $delivery['outer_delivery_bn'];
            $orderInfo  = app::get('ome')->model('delivery')->getOrderByDeliveryBn($outer_delivery_bn);

            $checkItems = $checkMdl->getList('*', ['order_id'=>$orderInfo['order_id']]);
            $items_bn_arr = array_column($items, 'bn');
            $mdl = app::get('purchase')->model('pick_bill_check_items'); 
            foreach ($checkItems as $c_k => $c_v) {
                if (!in_array($c_v['bn'], $items_bn_arr)) {
                    unset($checkItems[$c_k]);
                }
                if ($mdl->order_label[$c_v['order_label']]) {
                    $checkItems[$c_k]['order_label'] = $mdl->order_label[$c_v['order_label']];
                }
            }
            $checkItems = array_values($checkItems);
            $render->pagedata['checkItems'] = $checkItems;
        }
        
        //$render->pagedata['specifications'] = $product_info['specifications'];
        //$render->pagedata['brand_name'] = $product_info['brand_name'];

        return $render->fetch('admin/delivery/delivery_item.html');
    }

    function detail_delivery($dly_id){
        $dlyObj = app::get('wms')->model('delivery');
        $dlyChildObj = app::get('wms')->model('delivery_bill');
        $deliveryBillItemMdl = app::get('wms')->model('delivery_bill_items');



        $opObj = app::get('ome')->model('operation_log');
        $dlyCheckLib = kernel::single('wms_delivery_check');
        if(!empty($_POST)){
            $billarr =  $_POST["dlylist"];
            foreach($billarr as $k=>$v){
                $v = trim($v);
                if ($dlyCheckLib->existExpressNoBill($v, $_POST['delivery_id'],$k)){
                    echo '<script>alert("已有此物流单号:'.$v.'")</script>'; break;
                }else{
                    $dlybillinfoget = $dlyChildObj->dump(array('b_id'=>$k));
                    if(empty($dlybillinfoget['logi_no'])){
                        $logstr = '录入快递单号:'.$v;
                        $opObj->write_log('delivery_bill_add@wms', $dly_id, $logstr);
                    }else{
                        $logstr = '修改快递单号:'.$dlybillinfoget['logi_no'].'->'.$v;
                        $opObj->write_log('delivery_bill_modify@wms', $dly_id, $logstr);
                    }
                    $dlyChildObj->update(array('logi_no'=>$v),array('b_id' => $k));
                }
            }
        }

        $render = app::get('wms')->render();
        $braObj = app::get('ome')->model('branch');

        $dly = $dlyObj->dump($dly_id);
        empty($dly['branch_id'])?$branch_id=0:$branch_id=$dly['branch_id'];
        $dlyChildList = $dlyChildObj->getList('*',array('delivery_id'=>$dly_id),0,-1);
        foreach ($dlyChildList as $key => $value) {
            $dlyChildList[$key]['items'] = $deliveryBillItemMdl->getList('*', ['bill_id' => $value['b_id']]);
        }

        $render->pagedata['dlyChildListCount'] = count($dlyChildList);
        $render->pagedata['dlyChildList'] = $dlyChildList;
        $render->pagedata['dly_corp'] = $braObj->get_corp($branch_id,$dly['consignee']['area']);
        $render->pagedata['dly']   = $dly;
        $render->pagedata['write'] = $this->write;

        $dlyCorpObj = app::get('ome')->model('dly_corp');
        $dlyCorp = $dlyCorpObj->dump($dly['logi_id'], 'prt_tmpl_id,type,tmpl_type');
        //物流公司使用电子面单时物流单号不能被编辑
        if ($dlyCorp['tmpl_type'] == 'electron') {
            $render->pagedata['write'] = 1;
        }
        $render->pagedata['dlyCorp'] = $dlyCorp;
        return $render->fetch('admin/delivery/delivery_list.html');
    }

    function detail_serial($dly_id){
        $render = app::get('wms')->render();
        $dlyItemsSerialObj = app::get('wms')->model('delivery_items_serial');

        $items = $dlyItemsSerialObj->getList('*',array('delivery_id'=>$dly_id), 0, -1);
        $render->pagedata['items'] = $items;

        return $render->fetch('admin/delivery/delivery_serial.html');
    }

    function detail_storagelife($dly_id){
        $render = app::get('wms')->render();
        $dlyItemsStorageLifeObj = app::get('wms')->model('delivery_items_storage_life');

        $items = $dlyItemsStorageLifeObj->getList('*',array('delivery_id'=>$dly_id), 0, -1);
        $render->pagedata['items'] = $items;
//echo '<pre>';print_r($items);exit;
        return $render->fetch('admin/delivery/delivery_storagelife.html');
    }
    
    
    public $column_cpup= '物流升级标';
    public $column_cpup_width= 180;
    public function column_cpup($row)
    {
        $img = '';
        if (kernel::single('ome_delivery_bool_type')->isCPUP($row[$this->col_prefix . 'bool_type'])) {
            $cn_service = explode(',', $row[$this->col_prefix . 'cpup_service']);
            
            if (in_array('201', $cn_service)) {
                $img .= "<span style='color:#6666ff'>按需配送</span><br>";
            }
            
            if (in_array('202', $cn_service)) {
                $img .= "<span style='color:#c64ae2'>顺丰配送</span><br>";
            }
            if (in_array('203', $cn_service)) {
                $img .= "<span style='color:#4ae25e'>承诺发货</span><br>";
            }
            if (in_array('204', $cn_service)) {
                $img .= "<span style='color:#e2bc4a'>承诺送达</span><br>";
            }
            if (in_array('210', $cn_service)) {
                $img .= "<span style='color:#e2bc4a'>极速上门</span><br>";
            }
            if (in_array('sug_home_deliver', $cn_service)) {
                if (!empty($img)) {
                    $img.= '<br>';
                }
                $img .= "<a style='color:#FF8800;text-decoration:none;' target='_blank' href='https://school.jinritemai.com/doudian/web/article/aHL7CAWFuopG'>建议使用音尊达</a><br>";
            }
        }
        return $row[$this->col_prefix . 'delivery'] . $img;
    }

    var $column_bool_type='标识';
    function column_bool_type($row)
    {
        return kernel::single('ome_delivery_bool_type')->getBoolTypeIdentifier($row[$this->col_prefix.'bool_type'],$row[$this->col_prefix.'shop_type']);
    }
    
    /***
    var $column_delivery_model = '配送方式';
    var $column_delivery_model_width = 90;
    public function column_delivery_model($row)
    {
        $delivery_model = $row[$this->col_prefix . 'delivery_model'];
        if($delivery_model == 'instatnt'){
            return '同城配送';
        }elseif($delivery_model == 'seller'){
            return '商家配送';
        }else{
            return $delivery_model;
        }
    }
    ***/
    
    var $column_writeoff_status = '核销状态';
    var $column_writeoff_status_width = 92;
    public function column_writeoff_status($row)
    {
        $status = $row[$this->col_prefix . 'writeoff_status'];
        
        $value = $this->_writeoffStatus[$status];
        
        return $value;
    }
    
    var $column_edit = '操作';
    var $column_edit_width = "75";
    var $column_edit_order = "1";
    
    function column_edit($row,$list)
    {
        $delivery_bn = $row[$this->col_prefix . 'outer_delivery_bn'];
        $status = $row[$this->col_prefix.'status'];
    
        $delivery          = $this->_getDelivery($delivery_bn, $list);
        $str = '<a href="index.php?app=wms&ctl=admin_receipts_print&act=toPrintDeliverGoods&delivery_bn=%s" target="framename">%s</a>';
        if ($row[$this->col_prefix . 'shop_type'] == 'zkh' && ($status == '3' || $delivery['delivery_order_number'])) {
            return sprintf($str,$delivery_bn,'打印送货单');
        } elseif (kernel::single('ome_bill_label')->getBillLabelInfo($row['delivery_id'], 'wms_delivery', kernel::single('ome_bill_label')->isSomsGxd())) {
            return sprintf($str,$delivery_bn,'打印配送清单');
        } else {
            return '-';
        }
    }
    
    var $column_delivery_order_number = '送货单号';
    var $column_delivery_order_number_width = 100;
    
    public function column_delivery_order_number($row, $list)
    {
        $outer_delivery_bn = $row[$this->col_prefix . 'outer_delivery_bn'];
        $delivery          = $this->_getDelivery($outer_delivery_bn, $list);
        
        return $delivery['delivery_order_number'];
    }
    
    private function _getDelivery($delivery_bn, $list)
    {
        static $deliveryList;
        if (isset($deliveryList[$delivery_bn])) {
            return $deliveryList[$delivery_bn];
        }
        $filter['delivery_bn'] = array_column($list, 'outer_delivery_bn');
        $rows                  = app::get('ome')->model('delivery')->getList('delivery_bn,delivery_order_number', $filter);
        
        foreach ($rows as $row) {
            $deliveryList[$row['delivery_bn']]['delivery_order_number'] = $row['delivery_order_number'] ? $row['delivery_order_number'] : '';
        }
        return $deliveryList[$delivery_bn];
    }

    /**
     * 订单标记
     */
    public $column_order_label = '标记';
    public $column_order_label_width = 160;
    public $column_order_label_order = 30;
    public function column_order_label($row, $list)
    {
        $delivery_id = $row['delivery_id'];
        
        //获取订单标记列表
        $labelList = $this->__getOrderLabel($list);
        $dataList = $labelList[$delivery_id];
        if(empty($dataList)){
            return '';
        }
        
        //默认只显示三条记录
        $str = [];
        $color_i = 0;
        foreach ($dataList as $key => $val)
        {
            $color_i++;
            
            // if($color_i > 3){
            //     continue;
            // }
            
            $str[] = sprintf("<span title='%s' style='filter: brightness(0.9) contrast(0.9);border:1px solid %s; color:%s;margin: 2px;padding: 0px 2px;border-radius: 5px;white-space: nowrap;'>%s</span>", $val['label_name'], $val['label_color'], $val['label_color'], $val['label_name']);
        }
        $str = '<div style="overflow: auto;word-break: break-word;white-space: normal;width: 100%;flex-wrap: wrap;display: flex;">'.implode("", $str).'</div>';
        return $str;
    }

    /**
     * 订单标记列表
     * 
     * @param array $list
     * @return null
     */
    private function __getOrderLabel($list)
    {
        static $arrOrderLabel;
        
        if(isset($arrOrderLabel)){
            return $arrOrderLabel;
        }
        
        $deliveryIds = array();
        foreach($list as $val) {
            $deliveryIds[] = $val['delivery_id'];
        }
        
        //获取订单标记列表
        $orderLabelObj = app::get('ome')->model('bill_label');
        $labelData = $orderLabelObj->getBIllLabelList($deliveryIds, 'wms_delivery');
        foreach($labelData as $val)
        {
            $delivery_id = $val['bill_id'];
            
            $arrOrderLabel[$delivery_id][] = array(
                    'label_id' => $val['label_id'],
                    'label_name' => $val['label_name'],
                    'label_color' => $val['label_color'],
            );
        }
        
        unset($deliveryIds, $labelData);
        
        return $arrOrderLabel;
    }
}