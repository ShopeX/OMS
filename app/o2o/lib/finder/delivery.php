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
 * ============================
 * @Author:   yaokangming
 * @Version:  1.0 
 * @DateTime: 2022/6/6 10:35:00
 * @describe: 类
 * ============================
 */
class o2o_finder_delivery {
    var $detail_basic = "发货单详情";
    var $detail_item = "基础物料详情";

    public function __construct()
    {
        if($_GET['ctl'] == 'admin_delivery_pending' && $_GET['act'] == 'index' && $_GET['view'] == 0){
            //
        }else{
            unset($this->column_edit);
        }
    }

    public $column_edit = "操作";
    public $column_edit_width = "80";
    public $column_edit_order = "-1";
    function column_edit($row) {
        $ret = '<a class="lnk" target="_blank" 
                href="index.php?app=o2o&ctl=admin_delivery_pending&act=edit&p[0]='.$row[$this->col_prefix.'delivery_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">
                操作</a>';
        return $ret;
    }
    var $addon_cols = "skuNum,itemNum,bnsContent,delivery_id,status,process_status,print_status,order_createtime,outer_delivery_bn";

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
        $cnt = sprintf("共有 %d 种商品，总共数量为 %d 件， 具体 SKU 为： %s", $skuNum, $itemNum, @implode(', ', $cnts));

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
     * 获取基础物料名称
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
    
    var $column_deliveryNumInfo = "物流单号";
    var $column_deliveryNumInfo_width = "160";
    function column_deliveryNumInfo($row){
        $deliveryBillObj = app::get('wap')->model('delivery_bill');
        $deliveryBillInfo = $deliveryBillObj->dump(array('delivery_id'=>$row['delivery_id'],'type'=>1),'logi_no');
        return isset($deliveryBillInfo['logi_no']) ? $deliveryBillInfo['logi_no'] : '';
    }

    function row_style($row){
        $style='';
        if($row[$this->col_prefix.'is_cod'] == 'true'){
            $style .= " list-even ";
        }
        return $style;
    }

    function detail_basic($dly_id){
        $render = app::get('o2o')->render();
        $dlyObj = app::get('wap')->model('delivery');
        $deliveryBillObj = app::get('wap')->model('delivery_bill');
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

        //获取主物流单号
        $deliveryBillInfo = $deliveryBillObj->dump(array('delivery_id'=>$dly_id,'type'=>1),'logi_no');
        $dly['logi_no'] = $deliveryBillInfo['logi_no'];

        //发货单日志
        $logdata = $opObj->read_log(array('obj_id'=>$dly_id,'obj_type'=>'delivery@o2o'), 0, -1);
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

        $dly['create_time'] = date('Y-m-d H:i:s',$dly['create_time']);

        //根据原始发货单获取配送方式
        $shipping_type = kernel::single('ome_interface_delivery')->getOmeDlyShipType($dly['outer_delivery_bn']);
        $dly['delivery'] = $shipping_type;

        $render->pagedata['dly']      = $dly;

        return $render->fetch('admin/delivery/delivery_detail.html');
    }

    function detail_item($dly_id){
        $render = app::get('o2o')->render();
        $dlyObj = app::get('wap')->model('delivery');
        $dly_itemObj = app::get('wap')->model('delivery_items');
        $items = $dly_itemObj->getList('*', array('delivery_id' => $dly_id),0,-1);
        $delivery = $dlyObj->dump($dly_id);

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
        
        $render->pagedata['items'] = $items;
        $render->pagedata['dly']   = $delivery;

        return $render->fetch('admin/delivery/delivery_item.html');
    }
}