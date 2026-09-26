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
 * 唯品会JIT出库单管理
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 1.0 vopick.php 2017-02-23
 */
class console_ctl_admin_vopstockout extends desktop_controller{

    var $workground = "console_purchasecenter";
    
    function _views()
    {
        $pickObj    = app::get('console')->model('pick_stockout_bills');
        
        $base_filter    = array();
        $sub_menu = array(
                0 => array('label'=>__('全部'),'filter'=>$base_filter),
                1 => array('label'=>__('未审核'),'filter'=>array('confirm_status'=>1), 'optional'=>false),
                2 => array('label'=>__('已审核'),'filter'=>array('confirm_status'=>2), 'optional'=>false),
                3 => array('label'=>__('同步唯品会失败'),'filter'=>array('confirm_status'=>2, 'status'=>3, 'rsp_code|than'=>0), 'optional'=>false),
        );
        
        foreach($sub_menu as $k => $v)
        {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $pickObj->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=console&ctl=admin_vopstockout&act=index&view='. $k;
        }
        
        return $sub_menu;
    }
    
    function index()
    {
        $this->title = '出库单列表';
        
        $actions    = array();
        switch($_GET['view'])
        {
            case '2':
                $actions    = array(
                        array(
                                'label'=>app::get('console')->_('推送单据至WMS'),
                                'submit'=>"index.php?app=console&ctl=admin_vopstockout&act=batch_stockout",
                                'confirm'=>'你确定要对勾选的出库单发送至第三方吗？',
                                'target' => 'refresh',
                        ),
                );
                break;
            case '3':
                $actions    = array(
                    array(
                        'label'=>app::get('console')->_('批量重试'),
                        'submit'=>"index.php?app=console&ctl=admin_vopstockout&act=batch_sync",
                        'confirm'=>'你确定要对勾选的出库单,批量重试回传唯品会吗？',
                        'target' => 'refresh',
                    ),
                );
                break;
           default:
               
           break;
        }
        
        $params = array(
                'actions' => $actions,
                'title'=>$this->title,
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>true,
                'use_buildin_export'=>true,
                'use_buildin_import'=>false,
                'use_buildin_recycle'=>false,
        );
        
        $this->finder('console_mdl_pick_stockout_bills', $params);
    }
    
    /**
     * 编辑出库单
     */

    function edit($stockout_id)
    {
        if(empty($stockout_id)){
            $error_msg = '无效操作,请检查!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }
        
        $pickObj        = app::get('purchase')->model('pick_bills');
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $stockLib       = kernel::single('purchase_purchase_stockout');
        
        //出库单
        $row            = $stockoutObj->dump(array('stockout_id'=>$stockout_id, 'status'=>1, 'confirm_status'=>1, 'o_status'=>1), '*');
        if(empty($row))
        {
            $error_msg = '没有相关记录,请检查!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }
        
        //出库仓
        $branchObj    = app::get('ome')->model('branch');
        $branchInfo   = $branchObj->dump(array('branch_id'=>$row['branch_id']), 'name');
        $row['branch_name']    = $branchInfo['name'];
        
        //关联拣货单
        $sql            = "SELECT b.pick_no, b.po_id, b.to_branch_bn FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_bills AS b
                           ON a.bill_id=b.bill_id WHERE a.stockout_id=". $stockout_id;
        $pickInfo    = $stockoutObj->db->selectrow($sql);
        
        if($pickInfo['to_branch_bn'])
        {
            $purchaseLib    = kernel::single('purchase_purchase_order');
            $branchInfo     = $purchaseLib->getWarehouse($pickInfo['to_branch_bn']);
            
            $pickInfo['to_branch_name']    = $branchInfo['branch_name'];
        }
        $this->pagedata['pickInfo']    = $pickInfo;
        
        //获取shop_id
        $sql     = "SELECT shop_id FROM sdb_purchase_order WHERE po_id=". $pickInfo['po_id'];
        $poInfo  = $stockoutObj->db->selectrow($sql);
        $shop_id = $poInfo['shop_id'];
        
        //出库单明细
        $stockoutItemsObj    = app::get('purchase')->model('pick_stockout_bill_items');
        $dataList            = $stockoutItemsObj->getList('*', array('stockout_id'=>$stockout_id));

        $stockoutItemIdArr = array_column($dataList, 'stockout_item_id');
        $labelList         = kernel::single('ome_bill_label')->getLabelFromOrder($stockoutItemIdArr, 'pick_stockout_bill_item');
        
        foreach ($dataList as $key => $val)
        {
            $bill_id     = $val['bill_id'];
            $pickInfo    = $pickObj->dump(array('bill_id'=>$bill_id), 'pick_no, po_bn');
            
            $val['pick_no']    = $pickInfo['pick_no'];
            $val['po_bn']      = $pickInfo['po_bn'];
            
            //仓库可用库存
            $val['store']    = $stockLib->getBranchStoreByBn($val['bn'], $row['branch_id']);

            if ($labelList[$val['stockout_item_id']]) {
                $val['order_label'] = '';
                foreach ($labelList[$val['stockout_item_id']] as $lk => $lv) {
                    $val['order_label'] .= sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#FFF;'>%s</span>", $lv['label_name'], $lv['label_color'], $lv['label_name']);
                }
            }
            
            $dataList[$key]    = $val;
        }
        
        $this->pagedata['dataList'] = $dataList;
        
        //状态值
        $dly_mode    = $stockLib->getDlyMode();//配送方式
        $carrier_code    = $stockLib->getCarrierCode($shop_id);//承运商
        $delivery_time   = $stockLib->getDeliveryTime();//送货批次
        
        $this->pagedata['dly_mode'] = $dly_mode;
        $this->pagedata['carrier_code'] = $carrier_code;
        $this->pagedata['json_delivery_time'] = json_encode($delivery_time);
        
        //已选择的送货批次
        $sel_delivery_hour    = $delivery_time[$row['dly_mode']];
        $this->pagedata['sel_delivery_hour'] = $sel_delivery_hour;
        
        //送货批次时间
        $row['delivery_date']    = date('Y-m-d', time());
        if($delivery_time)
        {
            $temp    = explode(' ', $row['delivery_time']);
            $row['delivery_date']    = $temp[0];
            $row['delivery_hour']    = $temp[1];
        }
        
        //要求到货时间
        $tempDeliveryTime = array();
        $sel_dly_mode = $row['dly_mode'];
        if($sel_dly_mode && $row['delivery_time']){
            
            $temp_data = explode(' ', $row['delivery_time']);
            $sel_delivery_date = $temp_data[0];
            $sel_delivery_hour = $temp_data[1];
            
            $tempDeliveryTime = $stockLib->reckonArrivalTime($sel_dly_mode, $sel_delivery_date, $sel_delivery_hour);
        }
        $this->pagedata['arrival_time_list'] = $tempDeliveryTime;
        
        $this->pagedata['data'] = $row;
        $this->singlepage("admin/vop/stockout_edit.html");
    }
    
    /**
     * 编辑保存
     */
    function doEdit()
    {
        $this->begin('index.php?app=console&ctl=admin_vopstockout&act=index');
        
        $stockLib    = kernel::single('purchase_purchase_stockout');
        
        $stockout_id    = intval($_POST['stockout_id']);
        $dly_mode       = intval($_POST['dly_mode']);//配送方式
        $carrier_code   = $_POST['carrier_code'];
        
        $delivery_date   = $_POST['delivery_date'];//送货批次日期
        $delivery_hour   = $_POST['delivery_hour'];//送货批次时间点
        $is_air_embargo   = $_POST['is_air_embargo'];//送货批次时间点
        
        $arrival_time   = $_POST['arrival_time'];//要求到货时间
        $delivery_time  = $delivery_date .' '. $delivery_hour;
        
        if(empty($stockout_id) || empty($dly_mode) || empty($carrier_code))
        {
            $this->end(false, "无效操作，请检查");
        }
        
        if(empty($delivery_date) || empty($delivery_hour) || empty($arrival_time))
        {
            $this->end(false, "请选择送货批次和要求到货时间");
        }
        
        //检查推算出来的送货时间是否正确
        $tempDeliveryTime = $stockLib->reckonArrivalTime($dly_mode, $delivery_date, $delivery_hour);
        if(empty($tempDeliveryTime)){
            $this->end(false, "推算要求到货时间失败!");
        }
        
        $flag = false;
        foreach ($tempDeliveryTime as $key => $val){
            if($val == $arrival_time){
                $flag = true;
            }
        }
        
        if(!$flag){
            $this->end(false, "要求到货时间错误,保存失败!");
        }
        
        //出库单
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $row            = $stockoutObj->dump(array('stockout_id'=>$stockout_id, 'status'=>1, 'confirm_status'=>1, 'o_status'=>1), '*');
        if(empty($row))
        {
            $this->end(false, "出库单不存在");
        }
        
        //时间范围
        $min_time    = strtotime(date('Y-m-d', time()));//今天零晨时间戳
        $max_time    = strtotime("+30 day");//后30天时间戳
        $max_time    = strtotime(date('Y-m-d', $max_time));
        
        //检查送货批次
        $temp_time    = strtotime($delivery_date);
        if($temp_time < $min_time)
        {
            $this->end(false, '送货批次时间：不能小于当天零时时间');
        }
        if($temp_time > $max_time)
        {
            $this->end(false, '送货批次时间：不能大于当天时间超过30天');
        }
        
        $temp_time    = strtotime($delivery_time);
        if($temp_time <= time())
        {
            $this->end(false, '送货批次：不能小于当前时间');
        }
        
        //检查要求到货时间
        $temp_data    = explode(' ', $arrival_time);
        $temp_time    = strtotime($temp_data[0]);
        if($temp_time < $min_time)
        {
            $this->end(false, '要求到货时间：不能小于当天零时时间');
        }
        if($temp_time > $max_time)
        {
            $this->end(false, '要求到货时间：不能大于当天时间超过30天');
        }
        
        $temp_time    = strtotime($arrival_time);
        if($temp_time <= time())
        {
            $this->end(false, '要求到货时间：不能小于当前时间');
        }
        
        //格式化时间(YY-mm-dd HH:ii:ss)
        $delivery_time    = strtotime($delivery_time);
        $delivery_time    = date('Y-m-d H:i', $delivery_time);
        
        $arrival_time    = strtotime($arrival_time);
        $arrival_time    = date('Y-m-d H:i', $arrival_time);
        
        //检查申请出库数量
        if(empty($_POST['item_num']))
        {
            $this->end(false, '出库单明细无效,请检查!');
        }
        
        $error_msg    = '';
        $item_list    = $stockLib->check_edit_items($_POST, $error_msg);
        if(!$item_list)
        {
            $this->end(false, $error_msg);
        }
        
        //更新出库单明细
        $result    = $stockLib->update_edit_items($item_list, $error_msg);
        if(!$result)
        {
            $this->end(false, $error_msg);
        }
        
        //统计拣货数量
        $sql    = "SELECT sum(num) AS pick_num FROM sdb_purchase_pick_stockout_bill_items WHERE stockout_id=". $stockout_id ." AND is_del='false'";
        $pick_num    = $stockoutObj->db->selectrow($sql);
        
        //更新出库单
        $data    = array(
                'stockout_id' => $stockout_id,
                'dly_mode' => $dly_mode,
                'carrier_code' => $carrier_code,
                'delivery_time' => $delivery_time,
                'arrival_time' => $arrival_time,
                'pick_num' => $pick_num['pick_num'],
                'is_air_embargo' => $is_air_embargo,
        );
        $result    = $stockLib->update_stockout($data);
        if(!$result)
        {
            $this->end(false, "更新出库单失败");
        }
        
        $this->end(true, '更新出库单成功');
    }
    
    /**
     * 审核
     */
    function check($stockout_id)
    {
        if(empty($stockout_id)){
            $error_msg = '无效操作,请检查!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }
        
        $pickObj    = app::get('purchase')->model('pick_bills');
        
        //状态值
        $stockLib    = kernel::single('purchase_purchase_stockout');
        
        //出库单
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $row            = $stockoutObj->dump(array('stockout_id'=>$stockout_id, 'status'=>1, 'confirm_status'=>1, 'o_status'=>1), '*');
        if(empty($row))
        {
            $error_msg = '没有相关记录,请检查!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }
        
        //出库仓
        $branchObj    = app::get('ome')->model('branch');
        $branchInfo   = $branchObj->dump(array('branch_id'=>$row['branch_id']), 'name');
        $row['branch_name']    = $branchInfo['name'];
        
        //关联拣货单
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $sql            = "SELECT b.pick_no, b.po_id, b.to_branch_bn FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_bills AS b
                           ON a.bill_id=b.bill_id WHERE a.stockout_id=". $stockout_id;
        $pickInfo    = $stockoutObj->db->selectrow($sql);
        if($pickInfo['to_branch_bn'])
        {
            $purchaseLib    = kernel::single('purchase_purchase_order');
            $branchInfo     = $purchaseLib->getWarehouse($pickInfo['to_branch_bn']);
            
            $pickInfo['to_branch_name']    = $branchInfo['branch_name'];
        }
        $this->pagedata['pickInfo']    = $pickInfo;
        
        //出库单明细
        $stockoutItemsObj    = app::get('purchase')->model('pick_stockout_bill_items');
        $dataList           = $stockoutItemsObj->getList('*', array('stockout_id'=>$stockout_id, 'is_del'=>'false'));

        $stockoutItemIdArr = array_column($dataList, 'stockout_item_id');
        $labelList         = kernel::single('ome_bill_label')->getLabelFromOrder($stockoutItemIdArr, 'pick_stockout_bill_item');
        
        foreach ($dataList as $key => $val)
        {
            $bill_id     = $val['bill_id'];
            $pickInfo    = $pickObj->dump(array('bill_id'=>$bill_id), 'pick_no, po_bn');
            
            $val['pick_no']    = $pickInfo['pick_no'];
            $val['po_bn']      = $pickInfo['po_bn'];
            
            //仓库可用库存
            $val['store']    = $stockLib->getBranchStoreByBn($val['bn'], $row['branch_id']);

            if ($labelList[$val['stockout_item_id']]) {
                $val['order_label'] = '';
                foreach ($labelList[$val['stockout_item_id']] as $lk => $lv) {
                    $val['order_label'] .= sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#FFF;'>%s</span>", $lv['label_name'], $lv['label_color'], $lv['label_name']);
                }
            }
            
            $dataList[$key]    = $val;
        }
        
        $this->pagedata['dataiList'] = $dataList;
        
        //配送方式
        if($row['dly_mode'])
        {
            $row['dly_mode']    = $stockLib->getDlyMode($row['dly_mode']);
        }
        
        //承运商
        if($row['carrier_code'])
        {
            $row['carrier_code']    = $stockLib->getCarrierCode('', $row['carrier_code']);
        }
        
        $this->pagedata['data'] = $row;
        $this->singlepage('admin/vop/stockout_check.html');
    }
    
    /**
     * 保存审核
     */
    function doCheck()
    {
        $this->begin('index.php?app=console&ctl=admin_vopstockout&act=index');
        
        $stockout_id    = $_POST['stockout_id'];
        
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $stockLib       = kernel::single('purchase_purchase_stockout');
        
        //出库单
        $row            = $stockoutObj->dump(array('stockout_id'=>$stockout_id, 'status'=>1, 'o_status'=>1, 'confirm_status'=>1), '*');
        if(empty($row))
        {
            $this->end(false, "出库单不存在");
        }
        
        if(empty($row['dly_mode']))
        {
            $this->end(false, "配送方式未填写");
        }
        if(empty($row['carrier_code']))
        {
            $this->end(false, "承运商未填写");
        }
        if(empty($row['delivery_time']))
        {
            $this->end(false, "送货批次未填写");
        }
        if(empty($row['arrival_time']))
        {
            $this->end(false, "要求到货时间未填写");
        }
        
        //检查货品对应出库仓库存
        $error_msg   = '';
        
        $result      = $stockLib->checkBranchStock($stockout_id, $row['branch_id'], $error_msg);
        if(!$result)
        {
            $this->end(false, $error_msg);
        }
        
        //请求vop创建出库单
        $storage_no    = $row['storage_no'];
        if(empty($storage_no)){
            $is_multiple     = false;
            $to_branch_bn    = '';
            $po_bns          = array();
            $po_id           = 0;
            
            //关联拣货单
            $sql            = "SELECT b.pick_no, b.po_id, b.po_bn, b.to_branch_bn FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_bills AS b 
                               ON a.bill_id=b.bill_id WHERE a.stockout_id=". $stockout_id;
            $pickList    = $stockoutObj->db->select($sql);
            
            foreach ($pickList as $key => $val)
            {
                $po_bns[]      = $val['po_bn'];
                $to_branch_bn  = $val['to_branch_bn'];
                $po_id         = $val['po_id'];
            }
            
            $is_multiple  = (count($po_bns)>1 ? true : false);
            $po_bns       = implode(',', $po_bns);
            
            //承运商
            if($row['carrier_code']){
                $row['carrier_name']    = $stockLib->getCarrierCode('', $row['carrier_code']);
            }
            
            //PO单店铺shop_id
            $sql     = "SELECT shop_id FROM sdb_purchase_order WHERE po_id=". $po_id;
            $poInfo  = $stockoutObj->db->selectrow($sql);
            $shop_id = $poInfo['shop_id'];
            
            //格式化时间
            $row['delivery_time']    = $row['delivery_time'] .':00';
            $row['arrival_time']     = $row['arrival_time'] .':00';
            
            // 获取仓库信息
            $branchInfo = kernel::single('ome_branch')->getBranchInfo($row['branch_id'],'branch_bn');
            
            // 获取仓库关联的供应商发货仓库
            $relBranchInfo = app::get('ome')->model('branch_relation')->dump(array ('branch_id'=>$row['branch_id'],'type' => 'vopjitx'));
            if(empty($relBranchInfo['relation_branch_bn'])){
                $error_msg = '创建JIT出库单时，没有配置【平台仓库编码配置-->唯品会JITX编码】，仓库编码：'. $branchInfo['branch_bn'];
                $this->end(false, $error_msg);
            }
            
            // 供应商发货仓库编码
            $delivery_warehouse = $relBranchInfo['relation_branch_bn'];
            
            //API创建出库单
            $param    = array(
                        'po_no'=>$po_bns,//po号
                        'delivery_no'=>$row['stockout_no'],//运单号
                        'logistics_no' => 'VOP'.time(),
                        'warehouse'=>$to_branch_bn,//送货仓库
                        'delivery_method'=>$row['dly_mode'],//配送方式
                        'carrier_name'=>$row['carrier_name'],//承运商名称
                        'carrier_code'=>$row['carrier_code'],//承运商编码
                        'delivery_time'=>$row['delivery_time'],//送货批次
                        'arrival_time'=>$row['arrival_time'],//要求到货时间
                        'is_air_embargo'=>$row['is_air_embargo'],
                        'delivery_warehouse' => $delivery_warehouse, // 供应商发货仓库(如果是承运商=唯品会，则必填)
            );
            
            //超时请求3次
            $requestCount = 0;
            do {
                    $rsp    = kernel::single('erpapi_router_request')->set('shop', $shop_id)->purchase_createDelivery($param);

                    //判断是否请求超时
                    if ($rsp['rsp'] != 'fail' || ($rsp['res_ltype'] != 1 && $rsp['res_ltype'] != 2))
                    {
                        break;
                    }
                
                $requestCount++;
            } while ($requestCount<3);
            
            if($rsp['rsp'] == 'fail')
            {
                $rsp['err_msg']    = json_decode($rsp['err_msg'], true);
                $error_msg         = $rsp['err_msg']['returnMessage'];
                $error_msg         = ($error_msg ? $error_msg : '创建出仓单失败');
                
                $this->end(false, $error_msg);
            }
            
            //防止出库单号没有创建成功
            $storage_no    = $rsp['delivery']['storage_no'];
            if($rsp['rsp'] == 'succ' && empty($storage_no))
            {
                $this->end(false, "没有创建出仓单号");
            }
        }
        
        $delivery = array (
            'storage_no' => $storage_no,
        );
        if ($stockLib->is_vopcp($row['carrier_code'])) {
            if ($rsp['delivery']['arrival_time']) {
                $delivery['arrival_time'] = $rsp['delivery']['arrival_time'];
            }
            if ($rsp['delivery']['logistics_no']) {
                $delivery['delivery_no'] = $rsp['delivery']['logistics_no'];
            }
            if ($rsp['delivery']['delivery_method']) {
                $delivery['dly_mode'] = $rsp['delivery']['delivery_method'];
            }
        }
        //更新为已审核
        $data    = array('action'=>'is_check', 'stockout_id'=>$stockout_id, 'storage_no'=>$storage_no, 'confirm_status'=>2);

        $data = array_merge($delivery, $data);

        // 更新拣货单并释放拣货单冻结
        $result    = $stockLib->update_stockout($data);
        if(!$result)
        {
            $this->end(false, "更新出库单失败");
        }
        
        //库存冻结
        $result      = $stockLib->freeze($stockout_id, $row['branch_id'], $error_msg);
        if(!$result)
        {
            $this->end(false, $error_msg);
        }
        
        //推送出库信息给仓库
        kernel::single('console_event_trigger_vopstockout')->create(array('iso_id'=>$stockout_id), false);

        // 推送信息给唯品会时效订单结果反馈
        kernel::single('console_event_trigger_vopstockout')->occupied_order_feedback(['stockout_id'=>$stockout_id], false);
        
        $this->end(true, '审核成功');
    }
    
    /**
     * 人工确认出库
     */
    public function confirm($stockout_id)
    {
        @set_time_limit(0);
        @ini_set('memory_limit','128M');
        
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $stockItemObj   = app::get('purchase')->model('pick_stockout_bill_items');
        $pickObj        = app::get('purchase')->model('pick_bills');
        $branchObj      = app::get('ome')->model('branch');
        $logObj         = app::get('ome')->model('operation_log');
        
        $finder_id    = $_GET['finder_id'];
        $url          = 'javascript:finderGroup["'. $finder_id .'"].refresh();';
        
        //出库单
        $row    = $stockoutObj->dump(array('stockout_id'=>$stockout_id, 'confirm_status'=>2, 'receive_status'=>console_const::_FINISH_CODE), '*');
        if(empty($row))
        {
            $this->splash('error', $url, '没有相关记录!');
        }
        
        if($row['o_status'] == 3)
        {
            $this->splash('error', $url, '此单据已出库不能再次操作!');
        }
        
        //出库状态
        $io_status    = 'FINISH';//全部出库
        
        //出库类型
        $iostock_instance  = kernel::single('siso_receipt_iostock');
        $iso_type_id       = $iostock_instance::VOP_STOCKOUT;
        
        //出库单信息
        $iostockData  = array(
                    'iso_id'=>$row['stockout_id'],
                    'stockout_id'=>$row['stockout_id'],
                    'type_id'=>$iso_type_id,
                    'iso_bn'=>$row['stockout_no'],
                    'branch_id'=>$row['branch_id'],
                    'memo'=>$row['memo'],
                    'operate_time'=>time(),
        );
        
        //出库单明细
        $iso_item    = array();
        $temp_bn_num = array();
        
        $dataList    = $stockItemObj->getList('*', array('stockout_id'=>$stockout_id, 'is_del'=>'false'));
        foreach ($dataList as $key => $val)
        {
            $bn       = $val['bn'];
            $num      = $val['actual_num'];
            
            //货品product_id
            $sql         = "SELECT product_id FROM sdb_ome_products WHERE bn='". $bn ."'";
            $tempInfo    = $stockoutObj->db->selectRow($sql);
            $product_id  = $tempInfo['product_id'];
            
            //实际出库数量
            $temp_bn_num[$bn]  += $num;
            
            //组织数据
            $iso_item[$bn]    = array(
                                'bn'=>$bn,
                                'price'=>$val['price'],
                                'iso_items_id'=>$val['stockout_item_id'],
                                'product_id'=>$product_id,
                                'nums'=>$temp_bn_num[$bn],
                                'actual_num'=>$temp_bn_num[$bn],
                                'effective_num'=>$temp_bn_num[$bn],
            );
        }
        $iostockData['items']    = $iso_item;
        
        //开启事务
        $stockoutObj->db->beginTransaction();
        
        //生成出入库明细
        $stockLib    = kernel::single('siso_receipt_iostock_vopstockout');
        if($iostockData['items'])
        {
            $data    = $iostockData;
            $data['io_status']    = $io_status;
            
            $msg = '';
            $result = $stockLib->create($iostockData, $data, $msg);
            if (!$result){
                $stockoutObj->db->rollBack();

                $msg = '手动确认出库出入库失败!'. $msg;
                
                $this->splash('error', $url, $msg);
            }
        } else {
            $stockoutObj->db->rollBack();
            $msg = '出库单明细为空!';
            $this->splash('error', $url, $msg);
        }
        
        $stockObj = kernel::single('console_receipt_vopstock');
        if($stockObj->checkExist($row['stockout_no']))
        {
            //更新出库单
            $save_flag    = $stockObj->update_stockout($io_status, $msg);
            if(!$save_flag)
            {
                $stockoutObj->db->rollBack();
                
                $this->splash('error', $url, $msg);
            }
            
            //更新人工介入标识receive_status
            $stockoutObj->update(array('receive_status'=>0), array('stockout_id'=>$stockout_id));
            
            //提交事务
            $stockoutObj->db->commit();
            
            //释放冻结
            $stockObj->clear_stockout_store_freeze($iostockData, $io_status);
            
        } else {
            $stockoutObj->db->rollBack();
            $msg = '出库单异常!' . $row['stockout_no'] . $msg;
            $this->splash('error', $url, $msg);
        }
        
        //[全部出库]回写唯品会三个接口
        $syncLib    = kernel::single('purchase_purchase_sync');
        $error_msg  = '';
        
        $result    = $syncLib->_initStockoutIfo($stockout_id, $error_msg);//初始化信息
        if(!$result)
        {
            $this->splash('error', $url, '确认出库成功,初始化唯品会接口数据出错：'. $error_msg);
        }
        
        //editDelivery
        $result    = $syncLib->editDelivery($error_msg);
        if(!$result)
        {
            $this->splash('error', $url, '确认出库成功,同步唯品会接口出错：'. $error_msg);
        }
        
        //importDeliveryDetail
        $result    = $syncLib->importDeliveryDetail($error_msg);
        if(!$result)
        {
            $this->splash('error', $url, '确认出库成功,同步唯品会接口出错：'. $error_msg);
        }
        
        //confirmDelivery
        $result    = $syncLib->confirmDelivery($error_msg);
        if(!$result)
        {
            $this->splash('error', $url, '确认出库成功,同步唯品会接口出错：'. $error_msg);
        }
        
        //更新回传状态
        $stockoutObj->update(array('rsp_code'=>0), array('stockout_id'=>$stockout_id));
        
        //增加回传成功日志
        $logObj->write_log('update_stockout_bills@ome', $stockout_id, '回传唯品会出仓单成功');
        
        $this->splash('success', $url, '确认出库成功!');
    }
    
    /**
     * 批量重试回传唯品会出仓单
     * 
     * 回写唯品会三个接口editDelivery、importDeliveryDetail、confirmDelivery
     */
    function batch_sync()
    {
        $this->begin('index.php?app=console&ctl=admin_vopstockout&act=index&view=4');
        
        $logObj    = app::get('ome')->model('operation_log');
        $ids    = $_POST['stockout_id'];
        if($ids)
        {
            $stockoutObj  = app::get('purchase')->model('pick_stockout_bills');
            $syncLib      = kernel::single('purchase_purchase_sync');
            $error_msg    = '';
            
            foreach ($ids as $key => $val)
            {
                $stockout_id    = $val;
                
                $stockout_info    = $stockoutObj->dump(array('stockout_id'=>$stockout_id), 'stockout_no, rsp_code');
                $rsp_code         = $stockout_info['rsp_code'];
                
                if(empty($stockout_info) || $rsp_code == 0)
                {
                    continue;
                }
                
                //初始化信息
                $result        = $syncLib->_initStockoutIfo($stockout_id, $error_msg);
                if(!$result)
                {
                    $this->end(false, '出库单号：'. $stockout_info['stockout_no'] .','. $error_msg);
                }
                
                //editDelivery
                if($rsp_code <= 1)
                {
                    $result    = $syncLib->editDelivery($error_msg);
                    if(!$result)
                    {
                        $this->end(false, '出库单号：'. $stockout_info['stockout_no'] .','. $error_msg);
                    }
                }
                
                //importDeliveryDetail
                if($rsp_code <= 3)
                {
                    $result    = $syncLib->importDeliveryDetail($error_msg);
                    if(!$result)
                    {
                        $this->end(false, '出库单号：'. $stockout_info['stockout_no'] .','. $error_msg);
                    }
                }
                
                //confirmDelivery
                if($rsp_code <= 7)
                {
                    $result    = $syncLib->confirmDelivery($error_msg);
                    if(!$result)
                    {
                        $this->end(false, '出库单号：'. $stockout_info['stockout_no'] .','. $error_msg);
                    }
                }
                
                //更新回传状态
                $stockoutObj->update(array('rsp_code'=>0), array('stockout_id'=>$stockout_id));
                
                //增加回传成功日志
                $logObj->write_log('update_stockout_bills@ome', $stockout_id, '回传唯品会出仓单成功');
            }
        }
        
        $this->end(true, '命令已经发送成功！');
    }
    
    /**
     * 单据发送至第三方
     */
    function batch_stockout()
    {
        // $this->begin('');
        
        $ids    = $_POST['stockout_id'];
        
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $dataList       = $stockoutObj->getList('stockout_id', array('confirm_status'=>2, 'status'=>1, 'o_status'=>1));
        
        //已审核单据
        if (!empty($dataList))
        {
            foreach($dataList as $key => $val)
            {
                //推送出库信息给仓库
                kernel::single('console_event_trigger_vopstockout')->create(array('iso_id'=>$val['stockout_id']), false);
                // 推送信息给唯品会时效订单结果反馈
                kernel::single('console_event_trigger_vopstockout')->occupied_order_feedback(['stockout_id'=>$val['stockout_id']], false);
            }
        }
        
        $this->splash('success', null, '命令已经被成功发送！！');
    }
    
    /**
     * 差异查看
     */
    function difference($stockout_id)
    {
        if(empty($stockout_id)){
            $error_msg = '无效操作,请检查!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }
        
        $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
        $pickObj        = app::get('purchase')->model('pick_bills');
        
        //出库单信息
        $billInfo    = $stockoutObj->dump(array('stockout_id'=>$stockout_id), '*');
        
        //详情
        $stockoutItemsObj    = app::get('purchase')->model('pick_stockout_bill_items');
        $dataList            = $stockoutItemsObj->getList('*', array('stockout_id'=>$stockout_id));
        
        foreach ($dataList as $key => $val)
        {
            $bill_id     = $val['bill_id'];
            $pickInfo    = $pickObj->dump(array('bill_id'=>$bill_id), 'pick_no, po_bn');
            
            $val['pick_no']    = $pickInfo['pick_no'];
            $val['po_bn']      = $pickInfo['po_bn'];
            
            $dataList[$key]    = $val;
        }
        
        $this->pagedata['billInfo'] = $billInfo;
        $this->pagedata['dataList'] = $dataList;
        $this->pagedata['stockout_id'] = $stockout_id;
        
        $this->singlepage('admin/vop/stockout_difference.html');
    }
    
    /**
     * Ajax计算要求到货时间
     */
    function ajax_arrival_time()
    {
        $delivery_date    = $_POST['delivery_date'];
        $delivery_date    = $delivery_date[0];
        $delivery_hour    = $_POST['delivery_hour'];
        
        $dly_mode    = $_POST['dly_mode'];
        
        if(empty($delivery_date) || empty($delivery_hour) || empty($dly_mode))
        {
            echo json_encode(array('res'=>'error'));
            exit;
        }
        
        $stockLib       = kernel::single('purchase_purchase_stockout');
        $arrival_time   = $stockLib->reckonArrivalTime($dly_mode, $delivery_date, $delivery_hour);
        
        echo json_encode(array('res'=>'succ', 'arrival_time'=>$arrival_time));
        exit;
    }

    /**
     * 获取StockoutBillsCheckItems
     * @param mixed $stockout_id ID
     * @return mixed 返回结果
     */
    public function getStockoutBillsCheckItems($stockout_id)
    {
        if(empty($stockout_id)){
            return '';
        }
        $stockoutItemsObj = app::get('purchase')->model('pick_stockout_bill_items');
        $pickItemObj      = app::get('purchase')->model('pick_bill_check_items');

        $stockoutItems  = $stockoutItemsObj->getList('*', ['stockout_id'=>$stockout_id]);
        $billIdArr      = array_unique(array_column($stockoutItems, 'bill_id'));
        $barcodeList    = array_unique(array_column($stockoutItems, 'barcode'));
        $dataList       = $pickItemObj->getList('*', array('bill_id|in'=>$billIdArr));
        foreach ($dataList as $k => $v) {
            if (!in_array($v['barcode'], $barcodeList)) {
                unset($dataList[$k]);
                continue;
            }
            if ($pickItemObj->order_label[$v['order_label']]) {
                $dataList[$k]['order_label'] = $pickItemObj->order_label[$v['order_label']];
            }
        }
        echo(json_encode($dataList));
    }
    
    /**
     * 获取出库单明细
     */
    function getStockoutBillItems($stockout_id)
    {
        $stockoutItemsObj    = app::get('purchase')->model('pick_stockout_bill_items');
        $pickObj             = app::get('purchase')->model('pick_bills');
        
        if(empty($stockout_id))
        {
            return '';
        }
        
        //出库单明细
        $dataList    = $stockoutItemsObj->getList('*', array('stockout_id'=>$stockout_id));

        $stockoutItemIdArr = array_column($dataList, 'stockout_item_id');
        $labelList         = kernel::single('ome_bill_label')->getLabelFromOrder($stockoutItemIdArr, 'pick_stockout_bill_item');
        foreach ($dataList as $key => $val)
        {
            $bill_id     = $val['bill_id'];
            $pickInfo    = $pickObj->dump(array('bill_id'=>$bill_id), 'pick_no, po_bn');
            
            $val['pick_no']    = $pickInfo['pick_no'];
            $val['po_bn']      = $pickInfo['po_bn'];
            
            //是否删除状态
            if($val['is_del'] == 'true')
            {
                $val['item_del']    = 'item_del';
            }

            if ($labelList[$val['stockout_item_id']]) {
                $val['order_label'] = '';
                foreach ($labelList[$val['stockout_item_id']] as $lk => $lv) {
                    $val['order_label'] .= sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#FFF;'>%s</span>", $lv['label_name'], $lv['label_color'], $lv['label_name']);
                }
            }
            
            $dataList[$key]    = $val;
        }
        
        echo(json_encode($dataList));
    }
    
    /**
     * 获取装箱明细
     */
    function getBillItemBoxs($stockout_id)
    {
        $pickObj    = app::get('purchase')->model('pick_bills');
        $stockItemObj = app::get('purchase')->model('pick_stockout_bill_items');
        
        if(empty($stockout_id))
        {
            return '';
        }
        
        //装箱明细
        $sql       = "SELECT a.*, b.bn, b.barcode, b.product_name FROM sdb_purchase_pick_stockout_bill_item_boxs AS a
                      LEFT JOIN sdb_purchase_pick_stockout_bill_items AS b ON a.stockout_item_id=b.stockout_item_id WHERE a.stockout_id=". $stockout_id;
        $dataList  = $stockItemObj->db->select($sql);
        if(empty($dataList))
        {
            return '';
        }
        
        foreach ($dataList as $key => $val)
        {
            $bill_id    = $val['bill_id'];
            $pickInfo   = $pickObj->dump(array('bill_id'=>$bill_id), 'pick_no, po_bn');
            
            $val['pick_no']    = $pickInfo['pick_no'];
            $val['po_bn']      = $pickInfo['po_bn'];
            
            $dataList[$key]    = $val;
        }
        
        echo(json_encode($dataList));
    }
}