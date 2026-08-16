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
class console_finder_delivery{
    var $detail_basic = "发货单详情";
    var $detail_item = "基础物料详情";
    var $detail_delivery = "物流单列表";
    
    private $write = '1';
    var $has_many = array(
       'members' => 'members',
    );
    
    var $addon_cols = "skuNum,itemNum,bnsContent,delivery_id,status,process,stock_status,deliv_status,expre_status,verify,is_bind,type,bind_key,order_createtime,deli_cfg,is_cod,logi_status,sign_time,bool_type,shop_type,sync_code,delivery";

    var $cache_mark = array(); //买家留言&&客服备注
    
    function __construct(){
        if($_GET['ctl'] == 'admin_receipts_print' && $_GET['act'] == 'index'){
            $this->write = '2';
            $this->url = 'admin_receipts_print';
        }elseif($_GET['ctl'] == 'admin_refunded' && $_GET['act'] == 'index'){
            $this->write = '2';
            $this->url = 'admin_refunded';
        }else{
           unset($this->column_op);
        }
    }

    var $column_control='注意事项';
    var $column_control_width='120';
    var $column_control_order  = COLUMN_IN_HEAD;
    function column_control($row, $list){
      $delivery_time = $row['delivery_time'];
      $branch_id = $row['branch_id'];
      $logi_status = $row[$this->col_prefix.'logi_status'];
      $difftime = kernel::single('ome_func')->toTimeDiff(time(), $delivery_time);

      $branch_detail = $this->_getBranch($branch_id, $list);

      $ordersLib = kernel::single('ome_finder_orders'); 
      $str = '';
      if ($row[$this->col_prefix.'status'] == 'succ' && $logi_status!='3' && $difftime['d']>$branch_detail['logistics_limit_time']){
        $str = $ordersLib->getViewPanel('red','超时配送','超');
      }
      if($row[$this->col_prefix.'logi_status'] == '4'){
        $str.= $ordersLib->getViewPanel('purple','问题件','问');
      }
      return $str;
    }

    //补发原因
    var $column_bufa_reason = "补发原因";
    var $column_bufa_reason_width = "120";

    function column_bufa_reason($row, $list)
    {
        $markInfo    = $this->_getOrder($row['delivery_id'], $list);
        $bufa_reasons = [];
        foreach($markInfo as $v){
            if(empty($v['bufa_reason'])){
                continue;
            }
            $bufa_reasons[] = $v['bufa_reason'];
        }

        return empty($bufa_reasons) ? '' : implode('|', $bufa_reasons);
    }

    //补发原因
    var $column_relate_order_bn = "关联订单号";
    var $column_relate_order_bn_width = "120";

    function column_relate_order_bn($row, $list)
    {
        $markInfo    = $this->_getOrder($row['delivery_id'], $list);
        $relate_order_bns = [];
        foreach($markInfo as $v){
            if(empty($v['relate_order_bn'])){
                continue;
            }
            $relate_order_bns[] = $v['relate_order_bn'];
        }

        return empty($relate_order_bns) ? '' : implode('|', $relate_order_bns);
    }

    //显示状态
    var $column_custom_mark = "买家留言";
    var $column_custom_mark_width = 500;
    
    var $column_mark_text = "客服备注";
    var $column_mark_text_width = 500;
    
    //买家留言
    function column_custom_mark($row, $list){
         #根据物流单号，获取买家留言
         $markInfo    = $this->_getOrder($row['delivery_id'], $list);
         
         $custom_mark = '';
         foreach($markInfo as $v){
             
             if(empty($v['custom_mark']))
             {
                 continue;
             }
             
             $custom = kernel::single('ome_func')->format_memo($v['custom_mark']);
             if($custom){
                 // 取最后一条
                 $custom = array_pop($custom);
                 $custom_mark .= $custom['op_content'].'；'."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp";
             }
         }
       return $custom_mark;
    }
    
    //客服备注
    function column_mark_text($row, $list){
        #根据物流单号，获取客服备注
         $markInfo    = $this->_getOrder($row['delivery_id'], $list);
         
         $mark_text = '';
         foreach($markInfo as $v){
             
             if(empty($v['mark_text']))
             {
                 continue;
             }
             
             $mark = kernel::single('ome_func')->format_memo($v['mark_text']);
             if($mark){
                 // 取最后一条
                 $mark = array_pop($mark);
                 $mark_text .= $mark['op_content'].'；'."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp";
             }
         }
       return $mark_text;
    }
    
    //显示状态
    var $column_process = "发货状态";
    var $column_process_width = 180;
    function column_process($row){
        $render = app::get('ome')->render();
        $stock = $row[$this->col_prefix.'stock_status'];
        $deliv = $row[$this->col_prefix.'deliv_status'];
        $expre = $row[$this->col_prefix.'expre_status'];
        $proc  = $row[$this->col_prefix.'process'];
        $verify= $row[$this->col_prefix.'verify'];
        $status = $row[$this->col_prefix.'status'];
        $logi_status = $row[$this->col_prefix.'logi_status'];
        
        //未设置的默认是打印发货单，备货单跟快递单算是打印完成
        if ($proc=='true'){
            if ($status == 'return_back') {
                return '已追回';
            }elseif($logi_status == '7'){
                return '已发货-拦截通知已发送';
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
        
        $branchLib = kernel::single('ome_branch');
     
        //发货单信息
        $dly = $dlyObj->dump($dly_id);
        
        // 加密
        $dly['is_encrypt'] = kernel::single('ome_security_router',$dly['shop_type'])->show_encrypt($dly, 'delivery');

        $dlyExtObj = app::get('console')->model('delivery_extension');
        $dlyExtInfo = $dlyExtObj->dump(array('delivery_bn'=>$dly['delivery_bn']),'original_delivery_bn');

        $dly['original_delivery_bn'] = $dlyExtInfo['original_delivery_bn'];
        
        $tmp = app::get('ome')->model('members')->dump($dly['member_id']);
        $dly['member_name'] = $tmp['account']['uname'];
        $dly['members'] = "手机：".$tmp['contact']['phone']['mobile']."<br>";
        $dly['members'] .= "电话：".$tmp['contact']['phone']['telephone']."<br>";
        empty($dly['branch_id'])?$branch_id=0:$branch_id=$dly['branch_id'];
        $shop = $dlyObj->getShopInfo($dly['shop_id']);
        $dly['area'] = $shop['area'];

        $orderIds = $dlyObj->getOrderIdByDeliveryId($dly_id);
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
        $dlyCorp = $dlyCorpObj->dump($dly['logi_id'], 'prt_tmpl_id,type,tmpl_type');
        //物流公司使用电子面单时物流单号不能被编辑
        if ($dlyCorp['tmpl_type'] == 'electron') {
            $render->pagedata['write'] = 1;
        }
        
        //[京东一件代发]展示包裹信息
        $wms_type = $branchLib->getNodetypBybranchId($dly['branch_id']);
      
        $deliveryLib = kernel::single('console_delivery');
        
        $status = 'delivery';
        if(in_array($dly['status'], array('progress', 'ready'))){
            $status = 'accept';
        }elseif($dly['status'] == 'return_back'){
            $status = $dly['status'];
        }
        
        //京东包裹发货状态
        $shipStatusList = $deliveryLib->getShippingStatus();
        
        $packageStatus = array();
        $packageList = app::get('ome')->model('delivery_package')->getList('*', ['delivery_id' => $dly_id]);
       
        foreach ($packageList as $key => $value)
        {
            $package_bn = $value['package_bn'];
            $product_id = $value['product_id'];
            $status = strtolower($value['status']);
            
            //status
            if($status == 'delivery'){
                $packageStatus['delivery'][$product_id] = $package_bn;
            }elseif($status == 'cancel'){
                $packageStatus['cancel'][$product_id] = $package_bn;
            }else{
                $packageStatus['other'][$product_id] = $package_bn;
            }
            
            //配送状态
            $packageList[$key]['ship_status_name'] = $shipStatusList[$value['shipping_status']];
        }

       
        $render->pagedata['packageList'] = $packageList;
        
        //是否强制修复发货单
        $isRepairDelivery = false;
        if(in_array($dly['status'], array('ready', 'progress'))){
            if(empty($packageStatus['other']) && $packageStatus['delivery'] && $packageStatus['cancel']){
                $isRepairDelivery = true;
            }
        }
        $render->pagedata['isRepairDelivery'] = $isRepairDelivery;
        
            //获取京东配送方式
        $dly['shipping_type_name'] = $deliveryLib->getShippingType($dly['shipping_type']);

        $deliveryImages = kernel::single('wap_deliveryimg')->getdeliveryImages($dly_id);
        if($deliveryImages){
           
            $dly['signimage_attachment_url'] = json_encode($deliveryImages);
        }
        
        
        $render->pagedata['url']    = $this->url;
        $render->pagedata['log']      = $logdata;
        $render->pagedata['orderLogs'] = $orderLogs;
        $render->pagedata['dly_corp'] = $braObj->get_corp($branch_id,$dly['consignee']['area']);//$dlyObj->db->select($sql);
        $dly['create_time'] = date('Y-m-d H:i:s',$dly['create_time']);
        $render->pagedata['dly']      = $dly;
        $render->pagedata['order_bn'] = $order_bn;
        $render->pagedata['status'] = $_GET['status'];
        

        
    
        return $render->fetch('admin/delivery/delivery_detail.html');
    }

    function detail_item($dly_id)
    {
        $basicMaterialObj    = app::get('material')->model('basic_material');
        $basicMaterialLib    = kernel::single('material_basic_material');
        
        $render = app::get('ome')->render();
        $dlyObj = app::get('ome')->model('delivery');
        $goodsObj = app::get('ome')->model('goods');
        
        $items = $dlyObj->getItemsByDeliveryId($dly_id);
        
        /*获取货品优惠金额*/
        $dlyorderObj = app::get('ome')->model('delivery_order');
        $dly_order = $dlyorderObj->getlist('*',array('delivery_id'=>$dly_id),0,-1);

        $pmt_orders = $dlyObj->getPmt_price($dly_order);
        $sale_orders = $dlyObj->getsale_price($dly_order);
        
        //发货单信息
        $delivery = $dlyObj->dump($dly_id);
        if ($items)
        foreach ($items as $key => $item)
        {
            //将商品的显示名称改为后台的显示名称
            $bm_ids        = $basicMaterialObj->dump(array('material_bn'=>$items[$key]['bn']), 'bm_id');
            $productInfo   = $basicMaterialLib->getBasicMaterialExt($bm_ids['bm_id']);

            $items[$key]['unit'] = isset($productInfo['unit']) ? $productInfo['unit'] : '';
            
            $items[$key]['spec_info'] = $productInfo['specifications'];
            $items[$key]['product_name'] = $productInfo['material_name'];
            $items[$key]['pmt_price'] = $pmt_orders[$items[$key]['bn']]['pmt_price'];
            $items[$key]['sale_price'] = ($sale_orders[$items[$key]['bn']]*$item['number'])-$pmt_orders[$items[$key]['bn']]['pmt_price'];

            $items[$key]['price'] = $sale_orders[$items[$key]['bn']];
        }
        
        //WMS仓储类型
        $branchLib = kernel::single('ome_branch');
        $wms_type = $branchLib->getNodetypBybranchId($delivery['branch_id']);
        
        //是否超级管理员
        $isSuper = kernel::single('desktop_user')->is_super();
        
        //发货单明细详情
        $detailItems = array();
        if($wms_type == 'yjdf' && $isSuper){
            $dlyItemDetailMdl = app::get('ome')->model('delivery_items_detail');
            
            $detailItems = $dlyItemDetailMdl->getList('*', array('delivery_id'=>$dly_id), 0, -1);
        }
        
        $render->pagedata['detailItems'] = $detailItems;
        $render->pagedata['wms_type'] = $wms_type;
        $render->pagedata['write'] = $this->write;
        $render->pagedata['items'] = $items;
        $render->pagedata['dly'] = $delivery;
        $render->pagedata['show_unit'] = true;

        if ($delivery['shop_type'] == 'vop') {
            $checkMdl  = app::get('ome')->model('order_objects_check_items');
            $orderInfo = app::get('ome')->model('delivery')->getOrderByDeliveryBn($delivery['delivery_bn']);

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
        
        return $render->fetch('admin/delivery/delivery_item.html');
    }
    
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

    //物流单列表
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
        if($row[$this->col_prefix.'logi_number']>1)
        {
            $str    = "共有 ".$row[$this->col_prefix.'logi_number']." 单物流单，已完成发货 ".$row[$this->col_prefix.'delivery_logi_number']." 单 主物流单号为 ".$row['logi_no'];
            
            return  '<span title="'.$str.'" alt="'.$str.'"><font color="red">('.$row[$this->col_prefix.'delivery_logi_number'].' / '.$row[$this->col_prefix.'logi_number'].')</font> '.$row['logi_no'].'</span>';
        }else{
            return $row['logi_no'];
        }
    }

    var $column_order_bn = "订单号";
    var $column_order_bn_width = "180";
    function column_order_bn($row, $list) {

        $orders    = $this->_getOrder($row['delivery_id'], $list);

        $orderBn = is_array($orders) ? array_column($orders, 'order_bn') : [];

        return count($orderBn) > 1 ? '<span title="' . implode(',', $orderBn) . '">' . implode(',', $orderBn) . '</span>' : implode(',', $orderBn);
    }
    
    var $column_pay_status = '付款状态';
    var $column_pay_status_width = 120;
    function column_pay_status($row, $list)
    {
        $orders = $this->_getOrder($row['delivery_id'], $list);
        
        // format
        $pay_status_str = '';
        if($orders){
            $payStatus = array(
                0 => '未支付',
                1 => '已支付',
                2 => '处理中',
                3 => '部分付款',
                4 => '部分退款',
                5 => '全额退款',
                6 => '退款申请中',
                7 => '退款中',
                8 => '支付中',
            );
            
            $payStatuVals = [];
            foreach($orders as $val)
            {
                $pay_status = $val['pay_status'];
                if(empty($pay_status) && $pay_status !== '0'){
                    continue;
                }
                
                $payStatuVals[] = $payStatus[$pay_status];
            }
            
            $pay_status_str = implode('、', $payStatuVals);
        }
        
        return $pay_status_str;
    }
    
    var $column_process_status = '确认状态';
    var $column_process_status_width = 120;
    function column_process_status($row, $list)
    {
        $orders = $this->_getOrder($row['delivery_id'], $list);
        
        // format
        $process_status_str = '';
        if($orders){
            $processStatus = array(
                'unconfirmed'   => '未确认',
                'confirmed'     => '已确认',
                'splitting'     => '部分拆分',
                'splited'       => '已拆分完',
                'cancel'        => '取消',
                'remain_cancel' => '余单撤销',
                'is_retrial'    => '复审订单',
                'is_declare'    => '跨境申报订单',
            );
            
            $processStatuVals = [];
            foreach($orders as $val)
            {
                $process_status = $val['process_status'];
                if(empty($process_status)){
                    continue;
                }
                
                $processStatuVals[] = $processStatus[$process_status];
            }
            
            $process_status_str = implode('、', $processStatuVals);
        }
        
        return $process_status_str;
    }

    /**
     * 列表行加背景色
     */
    function row_style($row){
        $style='';
        if($row[$this->col_prefix.'is_cod'] == 'true'){
            $style .= " list-even ";
        }
        return $style;
    }

    var $column_bool_type='标识';
    function column_bool_type($row)
    {
        return kernel::single('ome_delivery_bool_type')->getBoolTypeIdentifier($row[$this->col_prefix.'bool_type'],$row[$this->col_prefix.'shop_type']);
    }
    
    //同步WMS错误码
    var $column_sync_code = "同步WMS错误码";
    var $column_sync_code_width = 150;
    function column_sync_code($row)
    {
        if(empty($row[$this->col_prefix.'sync_code'])){
            return '';
        }
        
        static $error_codes = array();
        
        if(empty($error_codes)){
            $abnormalObj = app::get('wmsmgr')->model('abnormal_code');
            $tempList = $abnormalObj->getList('abnormal_id,abnormal_code,abnormal_name', array('abnormal_type'=>'delivery'), 0, 500);
            if($tempList){
                foreach ($tempList as $key => $val)
                {
                    $abnormal_code = $val['abnormal_code'];
                    $error_codes[$abnormal_code] = $val['abnormal_name'];
                }
            }
            unset($tempList);
        }
        
        $sync_code = $row[$this->col_prefix.'sync_code'];
        $error_msg = ($error_codes[$sync_code] ? '('. $error_codes[$sync_code].')' : '');
        
        return $sync_code . $error_msg;
    }
    
    var $column_delivery = '配送方式';
    var $column_delivery_width = 90;
    public function column_delivery($row)
    {
        $delivery_mode = $row[$this->col_prefix . 'delivery'];
        if($delivery_mode == 'instatnt'){
            return '同城配送';
        }elseif($delivery_mode == 'seller'){
            return '商家配送';
        }else{
            return $delivery_mode;
        }
    }

    /**
     * 查询仓库
     *
     * @return void
     * @author 
     **/
    private function _getBranch($branch_id, $list)
    {
        static $branchList;

        if (isset($branchList)) {
            return $branchList[$branch_id]; 
        }

        $branchList = [];

        $filter = [
            'branch_id' => array_unique(array_column($list, 'branch_id')),
            'check_permission' => 'false',
        ];

        $branchList = app::get('ome')->model('branch')->getList('branch_id,logistics_limit_time',$filter);
        $branchList = array_column($branchList, null, 'branch_id');

        return $branchList[$branch_id];
    }

    /**
     * 获取买家留言 与 客服备注
     */
    private function _getOrder($delivery_id, $list)
    {
        static $deliveryList;

        if (isset($deliveryList)) {
            return $deliveryList[$delivery_id];
        }

        $deliveryList = [];
        
        $fields = 'custom_mark, mark_text, delivery_id, sdb_ome_orders.order_id, sdb_ome_orders.order_bn, sdb_ome_orders.bufa_reason, sdb_ome_orders.relate_order_bn, sdb_ome_orders.pay_status, sdb_ome_orders.process_status';
        $rows = app::get('ome')->model('delivery_order')->getOrderInfo($fields, implode(',', array_unique(array_column($list, 'delivery_id'))));

        foreach ($rows as $value) {
            $deliveryList[$value['delivery_id']][$value['order_id']] = $value;
        }

        return $deliveryList[$delivery_id];
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
        $labelData = $orderLabelObj->getBIllLabelList($deliveryIds, 'ome_delivery');
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
