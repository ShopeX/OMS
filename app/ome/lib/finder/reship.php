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

class ome_finder_reship{
    
    //平台售后状态
    static $_platformStatus = null;
    
    function __construct($app){
        $this->app = $app;
        if($_GET['app'] == 'console' ){
            unset($this->column_edit);
        }
    }
    var $addon_cols = 'need_sv,is_check,archive,source,order_id,changebranch_id,return_id,return_type,change_order_id,shop_type,flag_type,sync_code,status,abnormal_status,platform_status,return_freight,shop_id';

    var $detail_basic = "退货单详情";

    public static $change_order = array();
    function detail_basic($reship_id)
    {
        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $render = app::get('ome')->render();
        $oReship = app::get('ome')->model('reship');
        $corpObj = app::get('ome')->model('dly_corp');
        $orderItemMdl = app::get('ome')->model('order_items');

        $branchLib = kernel::single('ome_branch');
        $reshipLib = kernel::single('ome_reship');

        $keplerLib = kernel::single('ome_reship_kepler');
        
        //退货单信息
        $detail = $oReship->getCheckinfo($reship_id);

        $oDesktop          = app::get('desktop')->model('users');
        $desktop_detail    = $oDesktop->dump(array('user_id'=>$detail['op_id']), 'name');
        $detail['op_name'] = $desktop_detail['name'];

        if($detail['is_check'] == '3') $detail['is_check'] = '1';

        $cols = $oReship->_columns();
        $detail['is_check'] = $cols['is_check']['type'][$detail['is_check']];

        $reason = unserialize($detail['reason']);
        $detail['check_memo'] = $reason['check'];

        # 售后问题类型
        if ($detail['problem_id']) {
            $detail['problem_name'] = app::get('ome')->model('return_product_problem')->getCatName($detail['problem_id']);
        }

        //退回物流信息
        $return_id    = $detail['return_id'];
        if($return_id && empty($detail['return_logi_id']))
        {
            $reProductObj    = app::get('ome')->model('return_product');
            $product_detail  = $reProductObj->dump(array('return_id'=>$return_id), 'process_data');
            $process_data    = ($product_detail['process_data'] ? unserialize($product_detail['process_data']) : '');
            if($process_data)
            {
                $corpinfo = $corpObj->dump(array('name'=>$process_data['shipcompany']), 'corp_id');

                $detail['return_logi_no']    = $process_data['logino'];
            }
        }
        if($return_id) {
            $render->pagedata['return_freight_data'] = app::get('ome')->model('return_freight')->db_dump(['return_id'=>$return_id]);
        }
        if(is_numeric($detail['return_logi_name'])){
            $corpinfo = $corpObj->dump($detail['return_logi_name'], 'name');
            $detail['return_logi_name']=$corpinfo['name'];
        }

        $render->pagedata['detail'] = $detail;
        //[换货]关联的销售物料的类型
        if($detail['return_type'] == 'change'){
            $reshipObjects    = app::get('ome')->model('reship_objects');
            $temp_data    = $reshipObjects->getList('obj_id, obj_type,bn', array('reship_id'=>$reship_id));
            $reship_objects    = array();
            if($temp_data){
                foreach ($temp_data as $key => $val){
                    $reship_objects[$val['obj_id']]['obj_type']    = $val['obj_type'];
                    $reship_objects[$val['obj_id']]['sales_material_bn']    = $val['bn'];
                }
            }
            unset($temp_data);
        }
        $branchlib = kernel::single('ome_branch');

        //退换货明细
        $reship_item = $oReship->getItemList($reship_id);
        
        //obj_type
        $objTypeList = $orderItemMdl->_obj_alias;
        
        //获取订单obj层信息
        $orderItemList = array();
        $orderItemIds = array_column($reship_item, 'order_item_id');
        if($orderItemIds){
            $orderLib = kernel::single('ome_order');
            $orderItemList = $orderLib->getOrderItemByItemIds($orderItemIds);
        }
        
        //format
        $lucky_flag = false;
        foreach ($reship_item as $key => $value)
        {
            $order_item_id = $value['order_item_id'];
            
            $spec_info    = $basicMaterialExtObj->dump(array('bm_id'=>$value['product_id']), '*');
            $reship_item[$key]['spec_info'] = $spec_info['specifications'];
            $reship_item[$key]['specifications'] = $spec_info['specifications'];
            
            //price
            $reship_item[$key]['price'] = sprintf('%.2f', $reship_item[$key]['price']);
            
            //amount
            $reship_item[$key]['amount'] = $reship_item[$key]['amount'] > 0 ? $reship_item[$key]['amount'] : sprintf('%.2f',$reship_item[$key]['num'] * $reship_item[$key]['price']);
            $reship_item[$key]['gap']    = $value['return_type'] == 'return' ? $value['num'] -  $value['normal_num'] - $value['defective_num'] : 0;
            #换货的销售物料类型
            if($value['obj_id']){
                $reship_item[$key]['item_type']    = $reship_objects[$value['obj_id']]['obj_type'];
                $reship_item[$key]['sales_material_bn'] = $reship_objects[$value['obj_id']]['sales_material_bn'];
            }else{
                $reship_item[$key]['item_type'] = $value['obj_type'];
            }

            if ($value['return_type'] == 'change' && $value['changebranch_id']){

                $branch_detail = $branchlib->getBranchInfo($value['changebranch_id'],'name');

                $reship_item[$key]['branch_name'] = $branch_detail['name'];

            }
            
            //销售物料类型名称
            $obj_type = $reship_item[$key]['item_type'];
            $reship_item[$key]['obj_type_name'] = ($obj_type ? $objTypeList[$obj_type] : '');
            
            //关联的订单object层信息
            if(isset($orderItemList[$order_item_id])){
                $orderItemInfo = $orderItemList[$order_item_id];
                
                //销售物料编码
                $reship_item[$key]['sales_material_bn'] = $orderItemInfo['sales_material_bn'];
                
                //福袋组合编码
                $reship_item[$key]['combine_bn'] = $orderItemInfo['combine_bn'];
                
                //福袋组合编码
                if($orderItemInfo['combine_bn']){
                    $lucky_flag = true;
                }
            }
            
            $recover[$value['return_type']][] = $reship_item[$key];
        }

        $is_hqepay_on =  app::get('ome')->getConf('ome.delivery.hqepay');
        if($is_hqepay_on == 'false'){
            $is_hqepay_on = false;
        }else{
            $is_hqepay_on = true;
        }

        //[京东一件代发]
        $wms_type = $branchLib->getNodetypBybranchId($detail['branch_id']);
        if($wms_type == 'yjdf'){
            //物流公司列表
            $corpList = $corpObj->getList('corp_id,type,name', array('disabled'=>'false'));
            $render->pagedata['corpList'] = $corpList;

            //获取退货包裹明细
            $delivery_ids = array();
            $error_msg = '';
            $packageList = $reshipLib->get_reship_package($reship_id, $error_msg);
            if($packageList){
                foreach ($packageList as $key => $val)
                {
                    $delivery_id = $val['delivery_id'];

                    $delivery_ids[$delivery_id] = $delivery_id;
                }

                //京东包裹配送状态
                $deliveryLib = kernel::single('console_delivery');
                $shipStatusList = $deliveryLib->getShippingStatus();

                //发货包裹
                $packageObj = app::get('ome')->model('delivery_package');
                $tempList = $packageObj->getList('*', array('delivery_id'=>$delivery_ids));
                $dlyPackageList = array();
                foreach ($tempList as $key => $val)
                {
                    $package_bn = $val['package_bn'];

                    $dlyPackageList[$package_bn] = $val;
                }

                //京东包裹配送状态
                foreach ($packageList as $key => $val)
                {
                    $package_bn = $val['package_bn'];
                    $shipping_status = $dlyPackageList[$package_bn]['shipping_status'];

                    $packageList[$key]['shipping_status'] = $shipping_status;
                    $packageList[$key]['ship_status_name'] = $shipStatusList[$shipping_status];
                }
            }
            $render->pagedata['packageList'] = $packageList;

            //获取售后服务单
            $error_msg = '';
            $serviceList = $keplerLib->get_reship_services($reship_id, true, $error_msg);
            $render->pagedata['serviceList'] = $serviceList;
            
            //寄件地址列表
            $addressList = $keplerLib->getReturnAddressList($reship_id);
            $render->pagedata['addressList'] = $addressList;
        }
        
        if($detail['return_id']) {
            $itemMdl = app::get('ome')->model('change_items');
            $changeItems = $itemMdl->getlist('*',array('return_id'=>$detail['return_id']));
            if ($changeItems && !isset($recover['change'])) {
                $recover['change'] = $changeItems;
            }
        
            $render->pagedata['exchangeAddress'] = app::get('ome')->model('return_exchange_receiver')->db_dump(['return_id'=>$detail['return_id']]);
        }
        $render->pagedata['finder_id'] = ($_GET['_finder']['finder_id'] ? $_GET['_finder']['finder_id'] : $_GET['finder_id']);
        $render->pagedata['is_hqepay_on'] = $is_hqepay_on;
        $render->pagedata['items'] = $recover;
        $render->pagedata['lucky_flag'] = $lucky_flag;
        
        return $render->fetch('admin/reship/detail.html');
    }

    var $detail_log = "操作日志";
    function detail_log($reship_id){
        $render = app::get('ome')->render();
        $oOperation_log = app::get('ome')->model('operation_log');
        $render->pagedata['log'] = $oOperation_log->read_log(array('obj_type'=>'reship@ome','obj_id'=>$reship_id),0,20,'log_id desc');
        return $render->fetch('admin/reship/detail_log.html');
    }

    var $detail_acceptreturned = "收货记录";
    function detail_acceptreturned($reship_id){
        $render = app::get('ome')->render();
        $Oreship = app::get('ome')->model('reship');
        $Oreturn_process = app::get('ome')->model('return_process');
        $return = $Oreturn_process->getList('process_data',array('reship_id'=>$reship_id));
        $process_data = unserialize($return[0]['process_data']);
        $process_data['shipdaofu'] = $process_data['shipdaofu']==1?'是':'否';
        if($process_data['shipcompany']){
            $oDc = app::get('ome')->model('dly_corp');
            $dc_data = $oDc->dump($process_data['shipcompany']);
            $process_data['shipcompany'] = !empty($dc_data['name'])?$dc_data['name']:'';
        }
        $Oreason = $Oreship->dump(array('reship_id'=>$reship_id),'reason');
        $Oreason['reason'] = unserialize($Oreason['reason']);
        $render->pagedata['detail'] = $process_data;
        return $render->fetch('admin/reship/detail_acceptreturned.html');
    }

    var $detail_returnedsv = "质检记录";
    function detail_returnedsv($reship_id){
        $render = app::get('ome')->render();
        $Oreship = app::get('ome')->model('reship');
        $Oreturn_process = app::get('ome')->model('return_process');
        $oProblem = app::get('ome')->model('return_product_problem');
        $oBranch = app::get('ome')->model('branch');

        $return = $Oreturn_process->getList('process_data,memo',array('reship_id'=>$reship_id));

        $Oreturn_process_items = app::get('ome')->model('return_process_items');
        $process_items = $Oreturn_process_items->getList('bn,memo,store_type,branch_id,num',array('is_check'=>'true','reship_id'=>$reship_id));

        $process_data = unserialize($return[0]['process_data']);
        $process_data['shipdaofu'] = $process_data['shipdaofu']==1?'是':'否';
        $Oreason = $Oreship->dump(array('reship_id'=>$reship_id),'reason,is_check,need_sv');

        $process_data['reason'] = unserialize($Oreason['reason']);

        foreach($process_items as $k=>$v){
           $process_items[$k]['store_type'] = $oProblem->get_store_type($process_items[$k]['store_type']);
           $branch = $oBranch->db->selectrow("SELECT name from sdb_ome_branch WHERE branch_id=".$process_items[$k]['branch_id']);
           $process_items[$k]['branch_id'] = $branch['name'];
        }

        $render->pagedata['memo'] = $return[0]['memo'];
        $render->pagedata['process_items'] = $process_items;
        $render->pagedata['detail'] = $process_data;

        $s = kernel::single('ome_reship')->is_precheck_reship($Oreason['is_check'],$Oreason['need_sv']);
        if ($s) {
            $render->pagedata['memo'] = '质检异常';
        }

        return $render->fetch('admin/reship/detail_returnsv.html');
    }

    var $detail_abnormalreturn = "pda退货信息";
    function detail_abnormalreturn($reship_id){
        $render = app::get('ome')->render();
        $oReship = app::get('ome')->model('reship');
        $oReshipItems = app::get('ome')->model('reship_items');
        $oReshipPDAReturnInfo = app::get('ome')->model('reship_pda_return_info');
        $reshipData = $oReship->dump(array('reship_id' => $reship_id), 'reship_id, reship_bn, is_check');
        $tmpreshipItemsData = $oReshipItems->getList( 'item_id, bn, product_name, num, normal_num, defective_num', array('reship_id' => $reship_id, 'return_type' => 'return') );
        $item_ids = $reshipItemsData = array();
        foreach ($tmpreshipItemsData as $reshipItem) {
            $item_ids[] = $reshipItem['item_id'];
            $reshipItemsData[$reshipItem['item_id']] = $reshipItem;
        }
        $tmpReturnInfo = $oReshipPDAReturnInfo->getList('*', array('item_id|in' => $item_ids), 0, -1, 'id desc');
        $branch_tmp = $oReship->db->select("SELECT branch_id,name FROM sdb_ome_branch WHERE b_type=1");
        foreach ($branch_tmp as $branch_item) {
            $branch[$branch_item['branch_id']] = $branch_item['name'];
        }
        foreach ($tmpReturnInfo as $key => &$rinfo) {
            $pdaReturnInfo[$key][$rinfo['item_id']] = $rinfo;
            // 退货仓库
            $pdaReturnInfo[$key][$rinfo['item_id']]['return_branch_id'] = $branch[$rinfo['return_branch_id']];
        }
        $render->pagedata['reshipData'] = $reshipData;
        $render->pagedata['reshipItemsData'] = $reshipItemsData;
        $render->pagedata['pdaReturnInfo'] = $pdaReturnInfo;
        return $render->fetch('admin/reship/detail_abnormalreturn.html');
    }

    var $column_edit = "操作";
    var $column_edit_width = "220";
    function column_edit($row){
        if ($_REQUEST['act'] == 'jingxiao') {
            return '';
        }
        if(!$this->dmShop[$row[$this->col_prefix.'shop_id']]) {
            $this->dmShop[$row[$this->col_prefix.'shop_id']] = app::get('ome')->model('shop')->db_dump(['shop_id'=>$this->__currDlyInfo['shop_id']], 'delivery_mode');
        }
        if($this->dmShop[$row[$this->col_prefix.'shop_id']]['delivery_mode'] == 'jingxiao') {
            return '';
        }
        $addressObj = app::get('ome')->model('return_address');
        $oReship = app::get('ome')->model('reship');
        $reship_items = $oReship->dump($row['reship_id'],'reship_bn,source,return_type,return_id,change_order_id,change_status,shop_type,branch_id');

        //判断来源是天猫且类型为换货才可以有换货提前生成按钮
        $is_check = $row[$this->col_prefix.'is_check'];
        if ($is_check == '11' || $is_check=='9'){
            $edit_title = '最终收货';
        }else{
            $edit_title = '编辑';
        }
        $precheck = kernel::single('ome_reship')->is_precheck_reship($row[$this->col_prefix.'is_check'],$row[$this->col_prefix.'need_sv']);

        //查询寄件地址
        $select_address = '  <a target="dialog::{width:350,height:180,title:\'获取寄件地址:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_return_rchange&act=select_address&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">查询寄件地址</a>  ';

        $check = '<a href="index.php?app=ome&ctl=admin_return_rchange&act=check&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">'.($precheck ? '最终收货' : '审核').'</a>  ';

        $anti_check = '  <a target="dialog::{width:250,height:100,title:\'反审核退换货单号:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_return_rchange&act=anti_check&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">反审核</a>  ';

        $edit ='  <a target="dialog::{width:1200,height:680,title:\''.$edit_title.'退换货单:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_return_rchange&act=edit&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">'.$edit_title.'</a>  ';

        $accept_returned = '  <a target="dialog::{width:1200,height:546,title:\'收货退换货单:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_return_rchange&act=accept_returned&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">收货</a>  ';

        $cancel = '  <a target="dialog::{width:700,height:350,title:\'取消退换货单号:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_return_rchange&act=do_cancel&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">取消</a>  ';

        $part_cancel = '  <a target="dialog::{width:280,height:100,title:\'部分取消退换货单号:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_return_rchange&act=do_cancel&cancel_type=part&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">部分取消</a>  ';

        $doback = '  <a target="dialog::{width:280,height:100,title:\'退换货单号:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_return_rchange&act=do_back&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">退回</a>';

        $quality_check = '  <a target="dialog::{width:1200,height:546,title:\'质检退换货单:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_return_sv&act=edit&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">收货/质检</a>';

        
        $rejectFreight = '';
        if($row[$this->col_prefix.'return_id'] && $row[$this->col_prefix.'return_freight'] > 0) {
            $rfRow = app::get('ome')->model('return_freight')->db_dump(['return_id'=>$row[$this->col_prefix.'return_id']], 'handling_advice');
            if($rfRow['handling_advice'] == '1') {
                $rejectFreight = '  <a target="dialog::{width:700,height:350,title:\'拒绝退货寄回运费:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_return_rchange&act=rejectFreight&p[0]='.$row[$this->col_prefix.'return_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">拒绝寄回运费</a>';
            }
        }
        
        $change_orderBtn = '';
        $close_changeBtn = '';
        if ($reship_items['return_type'] == 'change'){
            if($reship_items['change_order_id']==0 && $reship_items['change_status']=='0'){
                $change_orderBtn = '  <a target="dialog::{width:400,height:200,title:\'换货订单:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_reship&act=create_change_order&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">换货订单</a>';
                $close_changeBtn = sprintf('<a href="javascript:if (confirm(\'确认关闭换货订单,关闭后,换货订单不会生成\')){W.page(\'index.php?app=ome&ctl=admin_reship&act=close_change&p[0]=%s&finder_id=%s\', $extend({method: \'get\'}, JSON.decode({})), this);}void(0);" target="">关闭换货</a>',$row['reship_id'],$_GET['_finder']['finder_id']);
            }
            
            
            //取消按钮
            $cancel = '  <a target="dialog::{width:600,height:400,title:\'取消退换货单号:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_reship&act=refuse_message&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">取消</a>  ';
        }
        
        $permissions = array(
            'check'         => 'aftersale_rchange_check',
            'anti_check'    => 'aftersale_rchange_recheck',
            'edit'          => 'aftersale_rchange_edit',
            'cancel'        => 'aftersale_rchange_refuse',
            'part_cancel'   => 'aftersale_rchange_refuse',
            'doback'        => 'aftersale_rchange_back',
            'quality_check' => 'aftersale_rchange_sv',

        );
        if(!kernel::single('desktop_user')->is_super()){
            $returnLib = kernel::single('ome_return');
            foreach ($permissions as $key=>$permission) {
                $has_permission = $returnLib->chkground('aftersale_center','',$permission);
                // $has_permission = kernel::single('desktop_user')->has_permission($permission);
                if (!$has_permission) {
                    $$key = '';
                }
            }
        }
        $ismemo = kernel::single('desktop_user')->has_permission('add_reship_memo');
        $memobtn='';
        if($ismemo){
            $memobtn = '  <a target="dialog::{width:700,height:350,title:\'添加退货单备注:'.$reship_items['reship_bn'].'\'}" href="index.php?app=ome&ctl=admin_reship&act=addmemo&p[0]='.$row['reship_id'].'&finder_id='.$_GET['_finder']['finder_id'].'">添加退货备注</a>  ';
        }
        switch($row[$this->col_prefix.'is_check']){
            case '0':
            case '12':
               $cols = $check.$edit.$cancel.$rejectFreight.$memobtn;
            break;
            case '1':
              if($_GET['flt'] == 'process_list'){
                $cols = $quality_check;
              }else{
                  $cols .= $cancel . $change_orderBtn . $rejectFreight.$memobtn;
                    //关闭换货订单
                    if($close_changeBtn){
                        $cols .= ' | '. $close_changeBtn;
                    }
                  //[京东一件代发]
                  $branchLib = kernel::single('ome_branch');
                  $wms_type = $branchLib->getNodetypBybranchId($reship_items['branch_id']);
                  if($wms_type == 'yjdf' && $row[$this->col_prefix.'status']=='ready'){
                      //寄件地址
                      $addressInfo = $addressObj->dump(array('reship_id'=>$row['reship_id']), 'address_id,wms_type');
                      if(empty($addressInfo)){
                          $cols .= $select_address;
                      }
                  }

              }
            break;
            case '2':
               $cols = $check.$edit.$rejectFreight.$memobtn;
               
               //请求失败,允许取消
               if(in_array($row['sync_status'], array('1','2')) && $row['sync_msg']){
                   $cols .= $cancel;
               }
               
            break;
            case '3':
            case '13':
              if($_GET['flt'] == 'process_list'){
                $cols = $quality_check;
              }else{
                $cols = $rejectFreight.$memobtn;
              }
            break;
            case '10':
            case '4':
              if($_GET['flt'] == 'process_list'){
                $cols = '';
              }else{
                $cols = $anti_check.$cancel;
              }
            break;
            case '5':
            case '6':
            case '7':
                $cols = '';
            break;
            case '8':
               $cols = <<<EOF
            <a href="index.php?app=ome&ctl=admin_return_sv&act=recheck&p[0]={$row['reship_id']}&finder_id={$_GET['_finder']['finder_id']}">重新质检</a>
EOF;
            break;
            case '9':
              if($_GET['flt'] == 'process_list'){
                $cols = '';
              }else{
                $cols = $edit.$cancel.$doback;
              }
            break;
            case '11':
                $cols = $edit.$cancel.$rejectFreights.$memobtn;
                break;
            case '14':
                $cols = $part_cancel;
                break;
            default:
               $cols = 'wrong';
        }
       
        return $cols ? $cols : '';
    }

    /**
     * 行样式
     * todo: 绿色list-even、黄色selected、红色list-warning
     * 
     * @param array $row
     * @return string
     */
    public function row_style($row) {
        $s = kernel::single('ome_reship')->is_precheck_reship($row['is_check'],$row[$this->col_prefix.'need_sv']);
        $style = '';

        if ($s){
            $style.= 'highlight-row';
        }

        if ($row[$this->col_prefix.'source'] == 'matrix' && $row[$this->col_prefix.'return_type'] == 'change'){
            $style.= 'selected';
        }elseif($row['is_check'] == '11'){
            $style .= 'selected'; //待确认记录加黄色背景
        }

        return $style;
    }

    var $column_order_id = '订单号';
    var $column_order_id_width = '180';
    function column_order_id($row, $list)
    {
        $archive = $row[$this->col_prefix . 'archive'];
        $source = $row[$this->col_prefix . 'source'];
        $order_id = $row[$this->col_prefix . 'order_id'];
        $order_ids = array_column($list, $this->col_prefix . 'order_id');
        $filter = array('order_id'=>$order_ids);

        if ($archive == '1' || in_array($source,array('archive'))) {
            static $a_order;
            if (!isset($a_order)) {
                $archive_ordObj = kernel::single('archive_interface_orders');
                $a_order = $archive_ordObj->getOrder_list($filter,'order_id,order_bn');
                $a_order = array_column($a_order, 'order_bn', 'order_id');
            }
            return $a_order[$order_id];
            /*
            $archive_ordObj = kernel::single('archive_interface_orders');
            $order = $archive_ordObj->getOrders($filter,'order_bn');
            */
        }else{
            static $o_order;
            if (!isset($o_order)) {
                $orderObj = app::get('ome')->model('orders');
                $o_order = $orderObj->getList('order_id, order_bn', $filter);
                $o_order = array_column($o_order, 'order_bn', 'order_id');
            }
            return $o_order[$order_id];
            /*
            $orderObj = app::get('ome')->model('orders');
            $order = $orderObj->dump($filter,'order_bn');
            */
        }
        // return $order['order_bn'];
    }


    var $column_changebranch_id = '换货仓';
    var $column_changebranch_id_width='80';
    function column_changebranch_id($row, $list){
        $changebranch_id = $row[$this->col_prefix .'changebranch_id'];

        if($changebranch_id){
            static $branchList;
            if (!isset($branchList)) {
                $changebranch_ids = array_unique(array_column($list, $this->col_prefix . 'changebranch_id'));
                $branchObj = app::get('ome')->model('branch');
                $branchList = $branchObj->getList('branch_id,name', ['branch_id'=>$changebranch_ids,'check_permission'=>'false']);
                $branchList = array_column($branchList, 'name', 'branch_id');
            }
            return $branchList[$changebranch_id];
            /*
            $branchObj = app::get('ome')->model('branch');
            $branch = $branchObj->db->selectrow('select name FROM sdb_ome_branch WHERE branch_id='.$changebranch_id);
            return $branch['name'];
            */
        }else{
            return '-';
        }
    }

    

    var $column_change_order_id = '换货订单号';
    var $column_change_order_id_width='80';
    /**
     * column_change_order_id
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回值
     */
    public function column_change_order_id($row,$list){
        $change_order_id = $row[$this->col_prefix .'change_order_id'];
        if ($change_order_id){
            static $orderList;
            if (!isset($orderList)) {
                $change_order_ids = array_unique(array_column($list, $this->col_prefix . 'change_order_id'));
                $orderObj = app::get('ome')->model('orders');
                $orderList = $orderObj->getList('order_id,order_bn',['order_id'=>$change_order_ids]);
                $orderList = array_column($orderList, 'order_bn', 'order_id');
            }
            return $orderList[$change_order_id];
            /*
            if (self::$change_order[$change_order_id]){
                return self::$change_order[$change_order_id];
            }else{
                $orderObj = app::get('ome')->model('orders');
                $order_detail = $orderObj->dump(array('order_id'=>$change_order_id),'order_bn');
                self::$change_order[$change_order_id] = $order_detail['order_bn'];
                return self::$change_order[$change_order_id];
            }
            */
        }
        return '';
    }

    var $column_time_out = '超时时间';
    var $column_time_out_width='120';
    function column_time_out($row, $list){

        if ($row[$this->col_prefix.'source'] == 'matrix' && $row[$this->col_prefix.'shop_type']=='tmall' && $row[$this->col_prefix.'return_type'] == 'change'){
            static $dataList;
            if (!isset($dataList)) {
                $return_ids = array_column($list, 'return_id');
                $tmallObj = app::get('ome')->model('return_product_tmall');
                $dataList = $tmallObj->getList('return_id,current_phase_timeout', array('return_id'=>$return_ids,'refund_type'=>'change'));
                $dataList = array_column($dataList,'current_phase_timeout','return_id');
            }
            $return_id = $row['return_id'];
            if ($dataList[$return_id]) {
                return "<div style='width:120px;height:20px;background-color:green;color:#FFFFFF;text-align:center;'>".date('Y-m-d H:i:s',$dataList[$return_id])."</div>";
            } else {
                return '-';
            }
            /*
            $tmallObj = app::get('ome')->model('return_product_tmall');
            $tmall_detail = $tmallObj->dump(array('return_id'=>$row['return_id'],'refund_type'=>'change'),'current_phase_timeout');
            if ($tmall_detail && $tmall_detail['current_phase_timeout']){
               return "<div style='width:120px;height:20px;background-color:green;color:#FFFFFF;text-align:center;'>".date('Y-m-d H:i:s',$tmall_detail['current_phase_timeout'])."</div>";;
            }
            */

        }else{
            return '-';
        }

    }

    //退货单标识
    var $column_fail_status = '标识';
    var $column_fail_status_width = 260;
    function column_fail_status($row)
    {
        $flag_type = $row[$this->col_prefix.'flag_type'];
        return kernel::single('ome_reship_const')->getHtml($flag_type);
    }


    //同步WMS错误码
    var $column_sync_code = "同步WMS错误码";
    var $column_sync_code_width = 150;
    function column_sync_code($row, $list)
    {
        if(empty($row[$this->col_prefix.'sync_code'])){
            return '';
        }

        static $error_codes;
        if(!isset($error_codes)){
            $error_codes = $sync_codes = [];
            foreach ($list as $key => $v) {
                if ($v[$this->col_prefix.'sync_code']) {
                    $sync_codes[] = $v[$this->col_prefix.'sync_code'];
                }
            }
            $abnormalObj = app::get('wmsmgr')->model('abnormal_code');
            $tempList = $abnormalObj->getList('abnormal_id,abnormal_code,abnormal_name', array('abnormal_type'=>'return','abnormal_code'=>$sync_codes), 0, 500);
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
    

    var $column_abnormal_status = '异常标识';
    var $column_abnormal_status_width = 150;
    function column_abnormal_status($row)
    {
        return kernel::single('ome_constants_reship_abnormal')->getIdentifier($row[$this->col_prefix.'abnormal_status']);
    }
    
    var $column_aftersale_type = '售后类型';
    var $column_aftersale_type_width = 85;
    
    function column_aftersale_type($row)
    {
        $flag_type      = $row[$this->col_prefix . 'flag_type'];
        $aftersale_type = '客退';
        if ($flag_type & ome_reship_const::__LANJIE_RUKU) {
            $aftersale_type = '原单退';
        }
        return $aftersale_type;
    }
    
    var $column_delivery_bn = '发货单号';
    var $column_delivery_bn_width = 120;
    
    function column_delivery_bn($row,$list)
    {
        $data = $this->getDelivery($row,$list);
        return isset($data['delivery_bn']) ? $data['delivery_bn'] : '';
    }
    
    protected $__deliveryList = [];
    
    /**
     * 获取Delivery
     * @param mixed $row row
     * @param mixed $list list
     * @return mixed 返回结果
     */
    public function getDelivery($row, $list)
    {
        $reship_id = $row['reship_id'];
        if (isset($this->__deliveryList[$reship_id])) {
            return $this->__deliveryList[$reship_id] ?: [];
        }
        
        $reshipIds             = array_column($list, 'reship_id');
        $itemMdl               = app::get('ome')->model('reship_items');
        $itemList              = $itemMdl->getList('reship_id,order_item_id', ['reship_id' => $reshipIds]);
        $orderItemIds          = array_column($itemList, 'order_item_id');
        $itemList              = array_column($itemList, null, 'reship_id');
        $salesDelOrderitemList = [];
        if ($orderItemIds) {
            $salesDelOrderitemList = app::get('sales')->model('delivery_order_item')->getList('delivery_id,delivery_bn,order_id,order_bn,order_item_id', ['order_item_id' => $orderItemIds]);
            $salesDelOrderitemList = array_column($salesDelOrderitemList, null, 'order_item_id');
        }
        foreach ($itemList as $item) {
            $delivery_bn                                             = $salesDelOrderitemList[$item['order_item_id']]['delivery_bn'] ?? '';
            $this->__deliveryList[$item['reship_id']]['delivery_bn'] = $delivery_bn;
        }
        
        return $this->__deliveryList[$reship_id] ?: [];
    }
}
?>
