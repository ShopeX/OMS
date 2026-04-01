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
class ome_finder_delivery{
    var $detail_basic = "发货单详情";
    var $detail_item = "物料详情";
	var $detail_delivery = "物流单列表";//wujian@shopex.cn 2012年3月7日 增加物流单列表
    private $write = '1';
    private $write_memo = '1';
    var $has_many = array(
       'members' => 'members',
    );

    var $addon_cols = "skuNum,itemNum,bnsContent,delivery_id,status,process,stock_status,deliv_status,expre_status,verify,is_bind,type,bind_key,order_createtime,deli_cfg,is_cod,delivery_logi_number,logi_number,logi_no,bool_type,cpup_service,delivery,promise_service";

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

    //显示状态
    var $column_process = "发货状态";
    var $column_process_width = "80";
    
    function column_process($row){
        $render = app::get('ome')->render();
        $stock = $row[$this->col_prefix.'stock_status'];
        $deliv = $row[$this->col_prefix.'deliv_status'];
        $expre = $row[$this->col_prefix.'expre_status'];
        $proc  = $row[$this->col_prefix.'process'];
        $verify= $row[$this->col_prefix.'verify'];
        $status = $row[$this->col_prefix.'status'];
       
        //未设置的默认是打印发货单，备货单跟快递单算是打印完成
        if ($proc=='true'){
            if ($status=='return_back') {
                return '退回';
            }
            return '已发货';
        }
        if ($verify=='true'){
            return '已校验';
        }

        $deliCfgLib = kernel::single('ome_delivery_cfg');

        if($deliCfgLib->deliveryCfg == ''){
            if ($stock=='false' && $deliv=='false' && $expre=='false'){
                return '未打印';
            }
            if ($stock=='true' && $deliv=='true' && $expre=='true'){
                return '已打印';
            }else {
                return '正在打印';
            }
        }else{
            $sku = $row[$this->col_prefix.'deli_cfg'] ? $row[$this->col_prefix.'deli_cfg'] : $_GET['sku'];
            $btncombi = $deliCfgLib->btnCombi($sku);
            switch ($btncombi) {
                case '1_1':
                    if ($stock=='false' && $deliv=='false' && $expre=='false'){
                        return '未打印';
                    }
                    if ($stock=='true' && $deliv=='true' && $expre=='true'){
                        return '已打印';
                    }else {
                        return '正在打印';
                    }
                    break;
                case '1_0':
                    if ($stock=='false' && $deliv=='false' && $expre=='false'){
                        return '未打印';
                    }
                    if ($stock=='true' && $expre=='true'){
                        return '已打印';
                    }else {
                        return '正在打印';
                    }
                    break;
                case '0_1':
                    if ($stock=='false' && $deliv=='false' && $expre=='false'){
                        return '未打印';
                    }
                    if ($deliv=='true' && $expre=='true'){
                        return '已打印';
                    }else {
                        return '正在打印';
                    }
                    break;
                case '0_0':
                    if ($stock=='false' && $deliv=='false' && $expre=='false'){
                        return '未打印';
                    }
                    if ($expre=='true'){
                        return '已打印';
                    }else {
                        return '正在打印';
                    }
                    break;
            }
        }
    }

    //显示状态
    var $column_status = "打印状态";
    var $column_status_width = "80";

    function column_status($row) {

        $stock = $row[$this->col_prefix . 'stock_status'];
        $stockColor = ($stock == 'true') ? 'green' : '#eeeeee';
        $deliv = $row[$this->col_prefix . 'deliv_status'];
        $delivColor = ($deliv == 'true') ? 'red' : '#eeeeee';
        $expre = $row[$this->col_prefix . 'expre_status'];
        $expreColor = ($expre == 'true') ? '#9a6913' : '#eeeeee';
        $ret = $this->getViewPanel('备货单', $stockColor);
        $ret .= $this->getViewPanel('发货单', $delivColor);
        $ret .= $this->getViewPanel('快递单', $expreColor);
        return $ret;
    }

    var $column_order_bn = "订单号";
    var $column_order_bn_width = "120";

    function column_order_bn($row, $list) {
        static $arrOrderBn = array();
        if(!$arrOrderBn) {
            $deliveryId = array();
            foreach ($list as $v) {
                $deliveryId[] = $v['delivery_id'];
            }
            $rows = app::get('ome')->model('delivery_order')->getList('*', array('delivery_id'=>$deliveryId));
            $deliveryOrderId = array();
            $orderId = array();
            foreach ($rows as $v) {
                $orderId[] = $v['order_id'];
                $deliveryOrderId[$v['delivery_id']][] = $v['order_id'];
            }
            $rows = app::get('ome')->model('orders')->getList('order_id, order_bn', array('order_id'=>$orderId));
            $arrOBn = array();
            foreach ($rows as $v) {
                $arrOBn[$v['order_id']] = $v['order_bn'];
            }
            foreach ($deliveryOrderId as $did => $val) {
                foreach ($val as $v) {
                    $arrOrderBn[$did][$v] = $arrOBn[$v];
                }
            }
        }
        $orderBn = $arrOrderBn[$row['delivery_id']];
        return count($orderBn) > 1 ? '<span title="' . implode(',', $orderBn) . '">' . implode(',', $orderBn) . '</span>' : implode(',', $orderBn);
    }

    public function getViewPanel($caption, $color) {
        if ($color == '#eeeeee')
            $caption .= '未打印';
        else
            $caption .= '已打印';
        return sprintf("<div style='width:18px;padding:2px;height:16px;background-color:%s;float:left;'><span alt='%s' title='%s' style='color:#eeeeee;'>&nbsp;%s&nbsp;</span></div>", $color, $caption, $caption, substr($caption, 0, 3));
    }

    //显示状态
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
        if ($status == 'progress' || $status == 'ready') {
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

    //显示状态
    var $column_content = "订单内容";
    var $column_content_width = "160";
//    var $column_content_order_field = "idx_split";

    function column_content($row) {

        $skuNum = $row[$this->col_prefix . 'skuNum'];
        $itemNum = $row[$this->col_prefix . 'itemNum'];
        $content = $row[$this->col_prefix . 'bnsContent'];

        $cnts = unserialize($content);
        $cnt = sprintf("共有 %d 种商品，总共数量为 %d 件， 具体 SKU 为： %s", $skuNum, $itemNum, @implode(', ', $cnts));

        @reset($cnts);
        $content = $cnts[@key($cnts)];
        if ($skuNum >1) {

            $content .= ' 等';
        }

        return sprintf("<span alt='%s' title='%s'><font color='red'>(%d / %d)</font> %s</span>",$cnt, $cnt, $skuNum, $itemNum, $content);
    }

    function detail_basic($dly_id){
        $render = app::get('ome')->render();
        $dlyObj = app::get('ome')->model('delivery');
        $orderObj = app::get('ome')->model('orders');
        $braObj = app::get('ome')->model('branch');
        $opObj  = app::get('ome')->model('operation_log');
        $dlyCorpObj = app::get('ome')->model('dly_corp');
        $dly = $dlyObj->dump($dly_id);
        $tmp = app::get('ome')->model('members')->dump($dly['member_id']);
        $dly['member_name'] = $tmp['account']['uname'];
        $dly['members'] = "手机：".$tmp['contact']['phone']['mobile']."<br>";
        $dly['members'] .= "电话：".$tmp['contact']['phone']['telephone']."<br>";
        empty($dly['branch_id'])?$branch_id=0:$branch_id=$dly['branch_id'];
        $shop = $dlyObj->getShopInfo($dly['shop_id']);
        $dly['area'] = $shop['area'];

        $order_bn = [];
        $orderIds = $dlyObj->getOrderIdByDeliveryId($dly_id);
        /*$sql = "SELECT dc.* FROM sdb_ome_branch_area ba
                                LEFT JOIN sdb_ome_dly_corp_area dca
                                    ON ba.region_id=dca.region_id
                                LEFT JOIN sdb_ome_dly_corp  dc
                                    ON dca.corp_id=dc.corp_id WHERE ba.branch_id='$branch_id'";*/
        if ($orderIds)
        $ids = implode(',', $orderIds);
        if ($orderIds)
        foreach ($orderIds as $oid)
        {
            $order = $orderObj->dump($oid);
            $order_bn[] = $order['order_bn'];
        }

        /* 发货单日志 */
        $logdata = $opObj->read_log(array('obj_id'=>$dly_id,'obj_type'=>'delivery@ome'), 0, -1);
		foreach($logdata as $k=>$v){
			$logdata[$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
		}

        /* 同批处理的订单日志 */
        $order_ids = $dlyObj->getOrderIdByDeliveryId($dly_id);
        $orderLogs = array();
        foreach($order_ids as $v){
            $order = $orderObj->dump($v,'order_id,order_bn');
            $orderLogs[$order['order_bn']] = $opObj->read_log(array('obj_id'=>$v,'obj_type'=>'orders@ome'), 0, -1);
            foreach($orderLogs[$order['order_bn']] as $k=>$v){
                if($v)
                    if(strpos($v['memo'], 'show_delivery_items')) {
                        $orderLogs[$order['order_bn']][$k]['memo'] = str_replace('show_delivery_items', 'show_delivery_items&finder_id='.$_GET['finder_id'], $v['memo']);
                    }
                    $orderLogs[$order['order_bn']][$k]['operate_time'] = date('Y-m-d H:i:s',$v['operate_time']);
            }
        }

        $dlyorderObj = app::get('ome')->model('delivery_order');
        #根据物流单号，获取会员备注与订单备注
        $markInfo = $dlyorderObj->getMarkInfo($dly_id);
        $custom_mark = array();#会员备注
        $mark_text = array();#订单备注
        foreach($markInfo as $key=>$v){
            $custom_mark[$v['order_bn']] = kernel::single('ome_func')->format_memo($v['custom_mark']);
            $mark_text[$v['order_bn']] = kernel::single('ome_func')->format_memo($v['mark_text']);
        
        }
        $render->pagedata['custom_mark'] = $custom_mark;#会员备注与订单备注信息
        $render->pagedata['mark_text'] = $mark_text;#会员备注与订单备注信息
        $render->pagedata['write']    = $this->write;
        $render->pagedata['write_memo']    = $this->write_memo;
        $dlyCorp = $dlyCorpObj->dump($dly['logi_id'], 'prt_tmpl_id,type,tmpl_type');
        //物流公司使用电子面单时物流单号不能被编辑
        if ($dlyCorp['tmpl_type'] == 'electron' ) {
            $render->pagedata['write'] = 1;
        }
        if ($dlyCorp['tmpl_type'] == 'electron' && $dly['status']!='succ') {
            $render->pagedata['write_memo'] = 1;
        }
        $render->pagedata['url']    = $this->url;
        $render->pagedata['log']      = $logdata;
        $render->pagedata['orderLogs'] = $orderLogs;
        $render->pagedata['dly_corp'] = $braObj->get_corp($branch_id,$dly['consignee']['area']);//$dlyObj->db->select($sql);
		$dly['create_time'] = date('Y-m-d H:i:s',$dly['create_time']);
        $render->pagedata['dly']      = $dly;
        $render->pagedata['order_bn'] = $order_bn;
        //echo "<pre>";
        $render->pagedata['status'] = $_GET['status'];

        // 获取发票抬头 
        $oObj = app::get('ome')->model('orders');
        $tax_info = $oObj->getList('tax_company', array('order_id'=>$order_ids), 0, -1);
        if(count($tax_info)==1){
            $tax_titles=$tax_info[0]['tax_company'];
        }else{
            $tax_titles=null;
            foreach($tax_info as $key=>$v){
                if($v['tax_company']!=null){
                    if($tax_titles!=null){
                       $tax_titles.='、'.$v['tax_company'];
                    }else{
                       $tax_titles=$v['tax_company'];
                    }                   
                }            
            } 
        }
        $render->pagedata['tax_titles'] = $tax_titles;

        return $render->fetch('admin/delivery/delivery_detail.html');
    }
    function detail_item($dly_id){
//echo $dly_id; 
        $render = app::get('ome')->render();
        $dlyObj = app::get('ome')->model('delivery');
        $basicMaterialObj = app::get('material')->model('basic_material');
        $items = $dlyObj->getItemsByDeliveryId($dly_id);
        /*获取货品优惠金额*/
        $dlyorderObj = app::get('ome')->model('delivery_order');
        $dly_order = $dlyorderObj->getlist('*',array('delivery_id'=>$dly_id),0,-1);

        $pmt_orders = $dlyObj->getPmt_price($dly_order);
        $sale_orders = $dlyObj->getsale_price($dly_order);

        $pmt_order = array();
        $delivery = $dlyObj->dump($dly_id);
        if ($items)
        foreach ($items as $key => $item){
        	//将商品的显示名称改为后台的显示名称
            $productInfo= $basicMaterialObj->getList('material_name',array('material_bn'=>$items[$key]['bn']));
            $items[$key]['spec_info'] = '';//$productInfo[0]['spec_info'];
            $items[$key]['product_name'] = $productInfo[0]['material_name'];
            $items[$key]['pmt_price'] = $pmt_order[$items[$key]['bn']]['pmt_price'];
            $items[$key]['sale_price'] = ($sale_orders[$items[$key]['bn']]*$item['number'])-$pmt_order[$items[$key]['bn']]['pmt_price'];

            $items[$key]['price'] = $sale_orders[$items[$key]['bn']];

        }
        $render->pagedata['write'] = $this->write;
        $render->pagedata['items'] = $items;
        $render->pagedata['dly']   = $delivery;

        return $render->fetch('admin/delivery/delivery_item.html');
    }


    //var $column_op = "操作";
    //var $column_op_width = "70";

    function column_op($row){

        $id = $row[$this->col_prefix.'delivery_id'];

        $dlyObj = app::get('ome')->model('delivery');

        //$data = $dlyObj->dump($id);
        $data['status']     = $row[$this->col_prefix.'status'];
        $data['process']    = $row[$this->col_prefix.'process'];
        $data['is_bind']    = $row[$this->col_prefix.'is_bind'];
        $data['type']       = $row[$this->col_prefix.'type'];
        $data['logi_no']    = $row[$this->col_prefix.'logi_no'];
        $data['bind_key']    = $row[$this->col_prefix.'bind_key'];

        $filter['process']  = 'false';
        $filter['status']   = array('ready','progress');
        $filter['type']     = 'normal';
        $filter['parent_id'] = '0';
        $filter['bind_key'] = $data['bind_key'];
        $num = $dlyObj->count($filter);
        $finder_id = $_GET['_finder']['finder_id'];
        $button = <<<EOF
        <a href="index.php?app=ome&ctl=admin_receipts_print&act=merge&p[0]=$id&finder_id=$finder_id" target="_blank" alt="可以合并多张发货单">合并</a>
EOF;
        $button2 = <<<EOF
        <a href="index.php?app=ome&ctl=admin_receipts_print&act=split&p[0]=$id&finder_id=$finder_id" target="_blank" alt="可将合并的发货单拆分为单个发货单">拆分</a>
EOF;
        $button3 = <<<EOF
        <input type="button" title="打印物流运单号" name="print" value="打印" onClick="javascript:window.open('index.php?app=ome&ctl=admin_receipts_print&act=toPrintShip&p[0]=$id')" />
EOF;
        $string = '';
        //拆分
        if ($data['is_bind'] == 'true' && $data['process'] == 'false' && $data['status'] != 'succ'){
            $string .= $button2;
        }
        //合并
        if ($num > 1 && $data['is_bind']=='false' && $data['status'] != 'back' && $data['status'] != 'cancel' && $data['status'] != 'stop' && $data['process'] == 'false' && $data['type'] == 'normal'){
            $string .= $button;
        }
        //打印物流运单号
        //$string .= $button3;

        return $string;
    }

	/*
	 * 物流单列表 wujian@shopex.cn
	 * 2012年3月7日
	 */
    function detail_delivery($dly_id){
        $dlyObj = app::get('ome')->model('delivery');
        $dlyChildObj = app::get('ome')->model('delivery_bill');
        $opObj = app::get('ome')->model('operation_log');
        $dlyCorpObj = app::get('ome')->model('dly_corp');
        if(!empty($_POST)){
            $billarr =  $_POST["dlylist"];
            foreach($billarr as $k=>$v){
                $v = trim($v);
                if ($dlyObj->existExpressNoBill($v, $_POST['delivery_id'],$k)){
                    echo '<script>alert("已有此物流单号:'.$v.'")</script>'; break;
                }else{
                    # 判断此物流单号是否在主发货单中已经存在
                    $exist = $dlyObj->getList('delivery_id',array('logi_no'=>$v),0,1);
                    if ($exist) {
                        echo '<script>alert("已有此物流单号:'.$v.'")</script>'; break;
                    }

                    $billdata = array('logi_no'=>$v,);
                    $billfilter = array('log_id' => $k,);
                    $dlybillinfo = $dlyChildObj->dump(array('log_id'=>$k,'logi_no'=>$v));
                    if(!$dlybillinfo){
                        $dlybillinfoget = $dlyChildObj->dump(array('log_id'=>$k));

                        if(empty($dlybillinfoget['logi_no'])){
                            $logstr = '录入快递单号:'.$v;
                            $opObj->write_log('delivery_bill_add@ome', $dly_id, $logstr);
                        }else{
                            $logstr = '修改快递单号:'.$dlybillinfoget['logi_no'].'->'.$v;
                            $opObj->write_log('delivery_bill_modify@ome', $dly_id, $logstr);
                        }
                        $dlyChildObj->update($billdata,$billfilter);
                    }
                }
            }
        }
        $render = app::get('ome')->render();

        $braObj = app::get('ome')->model('branch');


        $dly = $dlyObj->dump($dly_id);
        $delivery = $dlyObj->dump($dly_id);
        empty($dly['branch_id'])?$branch_id=0:$branch_id=$dly['branch_id'];
        $dlyChildList = $dlyChildObj->getList('*',array('delivery_id'=>$dly_id),0,-1);

        #多包情况,判断主单是否发货
        if(!empty($dlyChildList)){
            #统计已发货的子单数量
            $deliBillCount = $dlyChildObj->count(array('delivery_id'=>$delivery['delivery_id'],'status'=>'1'));
            
            if($dly['delivery_logi_number'] > $deliBillCount){
                $delivery['status']  =  'succ';
            }else{
                $delivery['status']  =  'unsucc';
            }
        }
        $render->pagedata['dlyChildListCount'] = count($dlyChildList);
        $render->pagedata['dlyChildList'] = $dlyChildList;
        $render->pagedata['dly_corp'] = $braObj->get_corp($branch_id,$dly['consignee']['area']);
        $render->pagedata['dly']   = $delivery;
        $render->pagedata['write'] = $this->write;
        $dlyCorp = $dlyCorpObj->dump($dly['logi_id'], 'prt_tmpl_id,type,tmpl_type');
        //物流公司使用电子面单时物流单号不能被编辑
        if ($dlyCorp['tmpl_type'] == 'electron') {
            $render->pagedata['write'] = 1;
        }
        $render->pagedata['dlyCorp'] = $dlyCorp;
        return $render->fetch('admin/delivery/delivery_list.html');
    }

	/*
	 * 物流单号加 总共物流单数量信息
	 * wujian@shopex.cn
	 * 2012年3月7日
	 */
	var $column_deliveryNumInfo = "物流单号-多包";
    var $column_deliveryNumInfo_width = "160";

	function column_deliveryNumInfo($row){
		if($row[$this->col_prefix.'logi_number']>1){
			$str="共有 ".$row[$this->col_prefix.'logi_number']." 单物流单，已完成发货 ".$row[$this->col_prefix.'delivery_logi_number']." 单 主物流单号为 ".$row[$this->col_prefix.'logi_no'];

			return  '<span title="'.$str.'" alt="'.$str.'"><font color="red">('.$row[$this->col_prefix.'delivery_logi_number'].' / '.$row[$this->col_prefix.'logi_number'].')</font> '.$row[$this->col_prefix.'logi_no'].'</span>';
		}else{
			return $row[$this->col_prefix.'logi_no'];
		}
    }
    
    public $column_cpup= '物流升级标';
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
                $img .= "<span style='color:#c64ae2'>极速上门</span><br>";
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

    function row_style($row){
        $style='';
        if($row[$this->col_prefix.'is_cod'] == 'true'){
            $style .= " list-even ";
        }
        return $style;
    }
    
    var $column_promise_service = '物流服务标签';
    var $column_promise_service_width = 320;
    public function column_promise_service($row)
    {
        //check
        if(empty($row[$this->col_prefix.'promise_service'])){
            return '';
        }
        
        $str = '';
        $colorList = array('#6666ff', '#336600', '#FF0000', '#FF8800', '#c64ae2', '#4ae25e', '#e2bc4a', '#668800');
        $promise_services = explode(',', $row[$this->col_prefix.'promise_service']);
        foreach ($promise_services as $key => $val)
        {
            $color = ($colorList[$key] ? $colorList[$key] : $colorList[0]);
            
            $str .= '<span class="tag-label" style="color:'. $color .'"> '. $val .'</span>';
        }
        
        return $str;
    }
}
?>
