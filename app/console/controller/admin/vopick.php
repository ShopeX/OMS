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
 * 唯品会JIT拣货单管理
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 1.0 vopick.php 2017-02-23
 */
class console_ctl_admin_vopick extends desktop_controller{

    var $workground = "console_purchasecenter";
    
    function _views()
    {
        $pickObj    = app::get('console')->model('pick_bills');
        
        $base_filter    = array();
        $sub_menu = array(
                0 => array('label'=>__('全部'),'filter'=>$base_filter),
                1 => array('label'=>__('未审核'),'filter'=>array('status'=>1), 'optional'=>false),
                2 => array('label'=>__('已审核'),'filter'=>array('status'=>2), 'optional'=>false),
                3 => array('label'=>__('已取消'),'filter'=>array('status'=>3), 'optional'=>false),
                4 => array('label'=>__('未拉取订单'),'filter'=>array('pull_status'=>'none'), 'optional'=>false),
                5 => array('label'=>__('拉取订单失败'),'filter'=>array('pull_status'=>array('running','fail')), 'optional'=>false),
        );
        
        foreach($sub_menu as $k => $v)
        {
            $sub_menu[$k]['filter'] = $v['filter'] ? $v['filter'] : null;
            $sub_menu[$k]['addon'] = $pickObj->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=console&ctl=admin_vopick&act=index&view='. $k;
        }
        
        return $sub_menu;
    }
    
    function index()
    {
        $this->title = '拣货单列表';
        $actions = array();
        
        //button
        $buttonList = array();
        $buttonList['pullOrder'] = array(
            'label' => '批量拉取订单',
            'submit' => $this->url.'&act=batchPullOrder',
            'target' => 'dialog::{width:600,height:230,title:\'批量拉取订单\'}'
        );
        
        //view
        $_GET['view'] = (empty($_GET['view']) ? '0' : $_GET['view']);
        switch ($_GET['view'])
        {
            case '4':
            case '5':
                $actions[] = $buttonList['pullOrder'];
                
                break;
            default:
                //---
        }
        
        //params
        $params = array('title'=>$this->title,
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>true,
                'use_buildin_export'=>false,
                'use_buildin_import'=>false,
                'use_buildin_recycle'=>false,
                'actions'=> $actions,
        );
        
        $this->finder('console_mdl_pick_bills', $params);
    }
    
    /**
     * 审核
     */

    function check($bill_id)
    {
        if(empty($bill_id)){
            $error_msg = '无效操作,请检查!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }
        
        $purchaseLib    = kernel::single('purchase_purchase_order');
        
        $pickInfo   = array();
        $pickObj    = app::get('console')->model('pick_bills');
        $pickInfo   = $pickObj->dump(array('bill_id'=>$bill_id, 'status'=>1), '*');
        
        if(empty($pickInfo)){
            $error_msg = '没有相关记录,请检查!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }
        if($pickInfo['status'] == 2){
            $error_msg = '拣货单已审核,禁止操作!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }elseif($pickInfo['status'] == 3){
            $error_msg = '拣货单已取消,禁止操作!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }
        
        //OMS仓库(只支持自有仓储、伊藤忠仓储)
        $branch_list    = $purchaseLib->get_branch_list();
        $this->pagedata['branchList']    = $branch_list;
        
        //唯品会仓库
        $branchInfo     = $purchaseLib->getWarehouse($pickInfo['to_branch_bn']);
        $pickInfo['in_branch_name']    = $branchInfo['branch_name'];
        
        //同仓拣货单
        $pickLib    = kernel::single('purchase_purchase_pick');
        $combinePicks    = $pickLib->fetchCombinePick($pickInfo);
        
        $this->pagedata['jsonCombinePicks'] = json_encode($combinePicks);
        
        //去除本身
        unset($combinePicks[$bill_id]);
        $this->pagedata['combinePicks'] = $combinePicks;
        
        $pickInfo['create_time']    = date('Y-m-d H:i:s', $pickInfo['create_time']);
        $this->pagedata['pickInfo'] = $pickInfo;
        $this->singlepage('admin/vop/vopick_check.html');
    }
    
    /**
     * 保存审核
     */
    function doCheck()
    {
        $this->begin('index.php?app=console&ctl=admin_vopick&act=index');
        
        $pickObj        = app::get('console')->model('pick_bills');
        $pickItemsObj   = app::get('purchase')->model('pick_bill_items');
        $pickLib        = kernel::single('purchase_purchase_pick');
        $logObj         = app::get('ome')->model('operation_log');
        
        $bill_id        = intval($_POST['bill_id']);
        $out_branch_id  = intval($_POST['out_branch_id']);
        $bill_ids       = $_POST['bill_ids'];
        
        $bill_ids[]    = $bill_id;
        $bill_ids      = array_filter($bill_ids);
        unset($_POST, $bill_id);
        
        if(empty($bill_ids) || empty($out_branch_id))
        {
            $this->end(false, "无效操作,请检查!");
        }
        
        //拣货单信息
        $pickList   = $pickObj->getList('*', array('bill_id'=>$bill_ids));
        if(empty($pickList))
        {
            $this->end(false, "拣货单不存在,请检查!");
        }
        
        //检查
        $itemList        = array();
        $to_branch_bn    = $pickList[0]['to_branch_bn'];
        $true_num        = 0;
        $is_reissue      = false;//是否补发
        $error_msg       = '';
        
        foreach ($pickList as $p_key => $p_val)
        {
            if ($p_val['is_download_finished'] != '1') {
                $this->end(false, sprintf('[%s]拣货单未下载完成,请检查!', $p_val['pick_no']));
            }

            $bill_id         = $p_val['bill_id'];
            
            $pick_num        = $p_val['pick_num'];
            $branch_send_num = $p_val['branch_send_num'];
            $true_num        += ($pick_num - $branch_send_num);
            
            //是否同入仓
            if($to_branch_bn != $p_val['to_branch_bn'])
            {
                $this->end(false, "不是同入库仓拣货单不能合并!");
            }
            
            //状态
            if($p_val['status'] == 2)
            {
                if($p_val['delivery_status'] == 1)
                {
                    $this->end(false, "拣货单已审核过,不能重复操作!");
                }elseif($branch_send_num > $pick_num){
                    $this->end(false, "拣货单已完成发货,不能重复操作!");
                }
                
                $is_reissue    = true;
            }elseif($p_val['status'] == 3){
                $this->end(false, "拣货单已取消,不能操作!");
            }
            
            //检查是否存在未出库的出库单
            $sql    = "SELECT a.stockout_id FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_stockout_bills AS b
                       ON a.stockout_id=b.stockout_id WHERE a.bill_id=". $bill_id ." AND b.status=1";
            $stockoutInfo    = $pickObj->db->selectrow($sql);
            if($stockoutInfo)
            {
                $this->end(false, "出库单已存在或者需要确认出库，请检查!");
            }
            
            //拣货单明细
            $pickDetail = $pickItemsObj->getList('*', array('bill_id'=>$bill_id));
            if(empty($pickDetail)){
                $this->end(false, '拣货单号: '. $p_val['pick_no'] .' 没有明细，请检查!');
            }
            
            foreach ($pickDetail as $i_key => $item)
            {
                //货号是否存在并检查仓库供货关系
                $productInfo    = $pickLib->checkProduct($item['barcode'], $out_branch_id, $error_msg);
                if(!$productInfo)
                {
                    $this->end(false, $error_msg);
                }

                if (!$item['product_id']) {
                    $this->end(false, sprintf('拣货单号: %s 货号: %s 不存在', $p_val['pick_no'], $item['bn']));
                }
                
                $num    = $item['num'];
                
                //补发计算剩余未发货数量
                if($is_reissue)
                {
                    $sql            = "SELECT sum(actual_num) as num FROM sdb_purchase_pick_stockout_bill_items 
                                       WHERE po_id=". $p_val['po_id'] ." AND bill_id=". $bill_id. " AND barcode='". $item['barcode'] ."'";
                    $actual_num    = $pickObj->db->selectrow($sql);
                    
                    $num    = $num - intval($actual_num['num']);
                    if($num <= 0)
                    {
                        continue;//货品数量已发完，跳过
                    }
                }
                
                //组织数据
                $itemList[]    = array(
                            'po_id'=>$p_val['po_id'],//采购单ID
                            'bill_id'=>$bill_id,//拣货单ID
                            'product_name' => $item['product_name'],
                            'bn' => $productInfo['bn'],//OMS货号
                            'barcode' => $item['barcode'],
                            'size' => $item['size'],
                            'item_num' => $num,//拣货数量
                            'num' => $num,//申请数量
                            'actual_num' => 0,//实际出库数量
                            'price' => $item['price'],
                            'market_price' => $item['market_price'],
                            'bill_item_id' => $item['bill_item_id'],
                            'product_id' => $item['product_id'],
                            'po_bn' => $p_val['po_bn'],
                            'pick_no' => $p_val['pick_no'],
                );
            }
        }
        
        //组织数据
        $data    = array(
                'branch_id' => $out_branch_id,//出库仓库
                'pick_num' => $true_num,//拣货数量
                'dly_mode' => 1,//配送方式 默认:1
                'bill_ids'=>$bill_ids,
                'detail'=>$itemList,
        );
        
        //创建
        $stockLib    = kernel::single('purchase_purchase_stockout');
        $stockout_no = $stockLib->create_stockout($data);
        if(!$stockout_no)
        {
            $this->end(true, '创建出库单失败');
        }
        
        //更新审核状态
        $pickObj->update(array('status'=>2), array('bill_id'=>$bill_ids));
        
        //日志
        $log_str    = '审核完成，';
        $log_str    .= (count($bill_ids)> 1 ? '合并' : '') .'创建出库单号：'. $stockout_no;
        foreach ($bill_ids as $key => $bill_id)
        {
            $logObj->write_log('check_vopick@ome', $bill_id, $log_str);
        }
        
        $this->end(true, '审核完成');
    }
    
    /**
     * 补发
     */
    function recheck($bill_id)
    {
        if(empty($bill_id)){
            $error_msg = '无效操作,请检查!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }
        $purchaseLib    = kernel::single('purchase_purchase_order');
        
        $pickInfo   = array();
        $pickObj    = app::get('console')->model('pick_bills');
        $pickInfo   = $pickObj->dump(array('bill_id'=>$bill_id, 'status'=>2, 'delivery_status'=>2), '*');
        
        if(empty($pickInfo)){
            $error_msg = '没有相关记录,请检查!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }
        
        if($pickInfo['pick_num'] <= $pickInfo['branch_send_num']){
            $error_msg = '拣货单已完成发货,禁止操作!';
            header("content-type:text/html; charset=utf-8");
            echo "<script>alert('". $error_msg ."');window.close();</script>";
            exit;
        }
        
        //OMS仓库(只支持自有仓储、伊藤忠仓储)
        $branch_list    = $purchaseLib->get_branch_list();
        $this->pagedata['branchList']    = $branch_list;
        
        //唯品会仓库
        $branchInfo     = $purchaseLib->getWarehouse($pickInfo['to_branch_bn']);
        $pickInfo['in_branch_name']    = $branchInfo['branch_name'];
        
        //同仓拣货单
        $pickLib    = kernel::single('purchase_purchase_pick');
        $combinePicks    = $pickLib->fetchCombinePick($pickInfo, true);
        $this->pagedata['jsonCombinePicks'] = json_encode($combinePicks);
        
        //去除本身
        unset($combinePicks[$bill_id]);
        $this->pagedata['combinePicks'] = $combinePicks;
        
        $pickInfo['create_time']    = date('Y-m-d H:i:s', $pickInfo['create_time']);
        $pickInfo['is_delivery']    = true;
        $this->pagedata['pickInfo'] = $pickInfo;
        $this->singlepage('admin/vop/vopick_check.html');
    }

    /**
     * 获取PickBillsCheckItems
     * @param mixed $bill_id ID
     * @return mixed 返回结果
     */
    public function getPickBillsCheckItems($bill_id)
    {
        if(empty($bill_id)){
            return '';
        }
        $pickItemObj = app::get('purchase')->model('pick_bill_check_items');
        $dataList    = $pickItemObj->getList('*', array('bill_id'=>$bill_id));
        foreach ($dataList as $k => $v) {
            if ($pickItemObj->order_label[$v['order_label']]) {
                $dataList[$k]['order_label'] = $pickItemObj->order_label[$v['order_label']];
            }
        }
        echo(json_encode($dataList));
    }
    
    /**
     * 获取拣货单明细
     */
    function getPickBillsItems($bill_id)
    {
        $pickObj        = app::get('purchase')->model('pick_bills');
        $pickItemObj    = app::get('purchase')->model('pick_bill_items');
        
        if(empty($bill_id))
        {
            return '';
        }
        
        //拣货单信息
        $row        = $pickObj->dump(array('bill_id'=>$bill_id), 'po_id, bill_id, status, delivery_status');
        
        //拣货单明细
        $dataList    = $pickItemObj->getList('*', array('bill_id'=>$bill_id));

        $billItemIdArr = array_column($dataList, 'bill_item_id');
        $labelList     = kernel::single('ome_bill_label')->getLabelFromOrder($billItemIdArr, 'pick_bill_item');
        foreach ($dataList as $k => $v){
            if ($labelList[$v['bill_item_id']]) {
                $dataList[$k]['order_label'] = '';
                foreach ($labelList[$v['bill_item_id']] as $lk => $lv) {
                    $dataList[$k]['order_label'] .= sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#FFF;'>%s</span>", $lv['label_name'], $lv['label_color'], $lv['label_name']);
                }
            }
        }
        
        //计算已出库数量
        if($row['status'] == 2)
        {
            $sql    = "SELECT a.stockout_id FROM sdb_purchase_pick_stockout AS a LEFT JOIN sdb_purchase_pick_stockout_bills AS b
                       ON a.stockout_id=b.stockout_id WHERE a.bill_id=". $bill_id ." AND b.confirm_status=2 AND b.o_status in(2,3)";
            $stockoutInfo    = $pickObj->db->select($sql);
            if($stockoutInfo)
            {
                $stockout_ids    = array();
                foreach ($stockoutInfo as $key => $val)
                {
                    $stockout_ids[]    = $val['stockout_id'];
                }
                
                foreach ($dataList as $key => $val)
                {
                    $sql    = "SELECT stockout_item_id FROM sdb_purchase_pick_stockout_bill_items WHERE stockout_id in(". implode(',', $stockout_ids) .")
                               AND po_id=". $row['po_id'] ." AND bill_id=". $row['bill_id'] . " AND barcode='". $val['barcode'] ."'";
                    
                    $item_ids     = array();
                    $stockItem    = $pickObj->db->select($sql);
                    foreach ($stockItem as $key_i => $val)
                    {
                        $item_ids[]    = $val['stockout_item_id'];
                    }
                    
                    if($item_ids)
                    {
                        $sql    = "SELECT sum(num) AS send_num FROM sdb_purchase_pick_stockout_bill_item_boxs 
                                   WHERE stockout_item_id in(". implode(',', $item_ids) .")";
                        $boxItem    = $pickObj->db->selectrow($sql);
                        
                        $dataList[$key]['send_num'] = $boxItem['send_num'];
                    }
                }
            }
        }
        
        echo(json_encode($dataList));
    }


    /**
     * cancel
     * @param mixed $bill_id ID
     * @return mixed 返回值
     */
    public function cancel($bill_id)
    {
        $this->begin($this->url);

        $pickMdl = app::get('purchase')->model('pick_bills');

        $pick = $pickMdl->dump($bill_id, 'status, delivery_status');

        if ($pick['delivery_status'] == '3' || $pick['delivery_status'] == '2') {
            $this->end(false, '该拣货单已发货，不能取消');
        }

        if ($pick['status'] == '2') {
            $this->end(false, '该拣货单已审核，不能取消');
        }

        $affect_rows = $pickMdl->update(['status' => '3'], [
            'bill_id' => $bill_id,
            'status' => '1',
            'delivery_status' => '1',
        ]);
        
        $logObj         = app::get('ome')->model('operation_log');
        $logObj->write_log('check_vopick@ome', $bill_id, '单据取消');

        $this->end($affect_rows === 1 ? true : false);
    }
    
    /**
     * 批量同步定制订单
     * 
     * @return void
     */
    public function batchPullOrder()
    {
        @ini_set('memory_limit','512M');
        set_time_limit(0);
        
        $pickObj = app::get('console')->model('pick_bills');
        
        //post
        $ids = $_POST['bill_id'];
        
        //check
        if($_POST['isSelectedAll'] == '_ALL_'){
            die('不能使用全选功能,每次最多选择500条!');
        }
        
        if(empty($ids)){
            die('请选择需要操作的订单!');
        }
        
        if(count($ids) > 500){
            die('每次最多只能选择500条!');
        }
        
        //data
        $filter = ['bill_id'=>$ids, 'pull_status'=>['none','running','fail']];
        $dataList = $pickObj->getList('bill_id,pick_no,po_bn,pull_status,shop_id', $filter, 0, -1);
        if(empty($dataList)){
            die('没有可操作的订单，请检查同步状态!');
        }
        
        $this->pagedata['GroupList'] = json_encode($ids);
        $this->pagedata['request_url'] = $this->url .'&act=ajaxPullOrder';
        
        //调用desktop公用进度条(第4个参数是增量传offset,否则默认一直为0)
        parent::dialog_batch('console_mdl_pick_bills', false, 50, 'incr');
    }
    
    /**
     * ajaxPullOrder
     * @return mixed 返回值
     */
    public function ajaxPullOrder()
    {
        $pickObj = app::get('console')->model('pick_bills');
        $jitOrderLib = kernel::single('console_inventory_orders');
        
        //setting
        $retArr = array(
            'itotal' => 0,
            'isucc' => 0,
            'ifail' => 0,
            'err_msg' => array(),
        );
        
        //获取查询条件
        parse_str($_POST['primary_id'], $postdata);
        
        //check
        if(empty($postdata['f'])) {
            echo 'Error: 请选择需要操作的订单!';
            exit;
        }
        
        //filter
        $filter = $postdata['f'];
        $offset = intval($postdata['f']['offset']);
        $limit = intval($postdata['f']['limit']);
        
        //check
        if(empty($filter)){
            echo 'Error: 没有找到查询条件';
            exit;
        }
        
        //data
        $pickList = $pickObj->getList('bill_id,pick_no,po_bn,pull_status,shop_id', $filter, $offset, $limit);
        if(empty($pickList)){
            echo 'Error: 没有获取到拣货单';
            exit;
        }
        
        //count
        $retArr['itotal'] = count($pickList);
        
        //list
        foreach ($pickList as $dataKey => $pickInfo)
        {
            $pick_no = $pickInfo['pick_no'];
            
            //pull
            $result = $jitOrderLib->getJitorderdetail($pickInfo);
            if($result['rsp'] != 'succ'){
                //fail
                $retArr['err_msg'][] = '拣货单：'. $pick_no .'同步失败：'. $result['error_msg'];
                $retArr['ifail'] += 1;
            }else{
                //succ
                $retArr['isucc'] += 1;
            }
        }
        
        echo json_encode($retArr, JSON_UNESCAPED_UNICODE),'ok.';
        exit;
    }
}
