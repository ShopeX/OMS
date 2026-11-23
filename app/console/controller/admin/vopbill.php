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
 * @DateTime: alt+t
 * @describe: 控制器
 * ============================
 */
class console_ctl_admin_vopbill extends desktop_controller {

    function _views(){
        $billMdl = app::get('console')->model('vopbill');
        $base_filter = array(
           
        );
        $sub_menu = array(
           
            0 => array('label'=>app::get('base')->_('全部'),'filter'=>array(),'optional'=>false),
            
            1 => array('label'=>app::get('base')->_('未确认'),'filter'=>array('status'=>'0'),'optional'=>false),
           
            2 => array('label'=>app::get('base')->_('已确认'),'filter'=>array('status'=>'1'),'optional'=>false),
            
        );

       
        foreach($sub_menu as $k=>$v){
            if (!IS_NULL($v['filter'])){
                $v['filter'] = array_merge($v['filter'], $base_filter);
            }
            
            $sub_menu[$k]['filter'] = $v['filter']?$v['filter']:null;
            $sub_menu[$k]['addon'] = $billMdl->viewcount($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=console&ctl='.$_GET['ctl'].'&act='.$_GET['act'].'&view='.$k;
        }

        return $sub_menu;
    }

    public function index() {
        $actions = array();
        /*
        $actions[] = array(
        'label'=>app::get('ome')->_('单拉vop账单'),
        'href'=>"index.php?app=console&ctl=admin_vopbill&act=sync",'target'=>'dialog::{width:690,height:500,title:\'单拉vop账单\'}"'

        );
        */
        $params = array(
                'title'=>'货款账单',
                'use_buildin_set_tag'=>false,
                'use_buildin_filter'=>true,
                'use_buildin_export'=>true,
                'use_buildin_import'=>false,
                'use_buildin_recycle'=>false,
                'actions'=>$actions,
                'orderBy' => 'id DESC',
        );
        
        $this->finder('console_mdl_vopbill', $params);
    }

    public function exportTemplate()
    {
        $row = [
            'ID', '商品编码', '商品条形码', '商品名称', '行类型名称', '数量', '金额'
        ];
        $id = (int) $_GET['id'];
        $data = app::get('console')->model('vopbill_amount')->getList('id,bn,barcode,product_name,detail_line_name,qty,amount',['bill_id'=>$id]);
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel($data, '帐单模板', 'xls', $row);
    }

    public function confirm($id) {
        $vopModel = app::get('console')->model('vopbill');
        $bills = $vopModel->db_dump(['id'=>$id],'*');


        $this->pagedata['bills'] = $bills;

        

        $billLib = kernel::single('console_vopbill');

     
        $details = $billLib->getBills($id);

       
        $this->pagedata['details'] = $details;

        $summary = $billLib->getBillsAmount($id);
        $this->pagedata['summary'] = $summary;
       
        $objMath    = kernel::single('eccommon_math');
        

        $total_qty = $objMath->number_minus(array($bills['cr_cust_quantity'], $bills['dr_cust_quantity']));

        $total_qty = $objMath->number_plus( array($total_qty, $bills['other_quantity']) );
        $this->pagedata['total_qty'] = $total_qty;


        $total_amount = $objMath->number_plus(array($bills['cr_cust_amount'], $bills['dr_cust_amount'],$bills['other_amount'],$bills['discount_amount']));

        $this->pagedata['total_amount'] = $total_amount;
        $this->singlepage('admin/vop/bill_confirm.html');
    }

    public function doConfirm() {
        $this->begin($this->url);
        $bill_id = (int) $_POST['bill_id'];
        $vbModel = app::get('console')->model('vopbill');
        $oldRow = $vbModel->db_dump(['id'=>$bill_id],'id,bill_number,status');
        if($oldRow['status'] != '0') {
            $this->end(false, '账单已经被确认');
        }
        $rs = $vbModel->update(['status'=>'1','confirm_time'=>time()], ['id'=>$bill_id, 'status'=>'0']);
        if(is_bool($rs)) {
            $this->end(false, '账单确认失败');
        }
        app::get('ome')->model('operation_log')->write_log('vopbill@console',$bill_id,"账单确认");

        
        $this->end(true, '操作成功');
    }

  

   

    public function sync($downloadType = '', $shop_id = '')
    {
        
       $shop = app::get('ome')->model('shop')->getList('shop_id, name', ['node_type'=>'vop']);
        if(empty($shop)) {
            exit('缺少唯品会店铺');
        }
        
       
        $this->pagedata['shop'] = $shop;
      
        $this->pagedata['start_time'] = strtotime('-7 days');
        $this->pagedata['end_time'] = time();

        $this->pagedata['request_url'] = $this->url.'&act=do_sync';

        $this->display('admin/vop/download_vopbill.html');
    }


    public function do_sync() {
        $shop_id = $_POST['shop_id'];
        $bill_type = $_POST['bill_type'];
        $bill_number = $_POST['bill_number'];


        $start_time   = $_POST['start_time'].' '.$_POST['_DTIME_']['H']['start_time'].':'.$_POST['_DTIME_']['M']['start_time'].':00';
        $end_time     = $_POST['end_time'].' '.$_POST['_DTIME_']['H']['end_time'].':'.$_POST['_DTIME_']['M']['end_time'].':00';
        $pageNo = (int) $_POST['page_no'];
        //加判断
       

        if (strtotime($start_time) >= strtotime($end_time)) {
            echo json_encode(['total'=>0]);exit;
        }

        $ret = ['total'=>0,'succ'=>0,'fail'=>0];
        
        
        
        $billMdl = app::get('console')->model('vopbill');
        $billObj = kernel::single('ome_event_trigger_shop_vopbill');
        if ( in_array($bill_type ,array('items','discount') ) && empty($bill_number)){
            $ret['err_msg'] = '请填入账单号';
            $ret['fail'] += 1;
            exit;
        }
        if (in_array($bill_type ,array('items','discount') )){
            
            $bills = $billMdl->dump(array('bill_number'=>$bill_number),'*');
            if(!$bills){

                echo json_encode(['total'=>0,'err_msg'=>$bill_number.':账单号不存在']);
                exit;
            }

            if(!in_array($bills['status'],array('0')) ){
                echo json_encode(['total'=>0,'err_msg'=>$bill_number.':账单号状态不可以单拉']);
                exit;
            }
        }
       
        if($bill_type == 'items'){
            
            $filter = [
                'status' => '0',
                'sync_status' => '1',
                'last_modified|lthan' => (time() - 600),
                'id'                    =>  $bills['id'],
            ];
            $oldRow = $billMdl->db_dump($filter);

            if($oldRow) {
                $billMdl->update(['sync_status'=>'0'], ['id'=>$bills['id'], 'status'=>'0']);
            }
            $sdf = ['get_time' => strtotime($end_time), 'bill_number'=>$bills['bill_number'],'id'=>$bills['id'],'shop_id'=>$bills['shop_id']];

            $result = $billObj->getBillDetail($sdf,$msg);


        }else if($bill_type == 'discount'){

            $filter = [
                'status' => '0',
                'discount_sync_status' => '1',
                'last_modified|lthan' => (time() - 600),
                'id'                    =>  $bills['id'],
            ];
            $oldRow = $billMdl->db_dump($filter);

            if($oldRow) {
                $billMdl->update(['discount_sync_status'=>'0'], ['id'=>$bills['id'], 'status'=>'0']);
            }
            $sdf = ['get_time' => strtotime($end_time), 'bill_number'=>$bills['bill_number'],'id'=>$bills['id'],'shop_id'=>$bills['shop_id'],'force_sync'=>true];
            $result = $billObj->getBillDiscountDetail($sdf);

        }else if($bill_type == 'bills'){
            $startTime = strtotime($start_time);
            $endTime = strtotime($end_time);

            $result = $billObj->getBillNumber($startTime, $endTime, $shop_id);
        }

        list($rs, $msg) = $result;
 
        if($rs) {
            $ret['succ'] += 1;
        } else {
            $ret['fail'] += 1;
            $ret['err_msg'] = $msg;
        }

        echo json_encode($ret);exit;
    }

    
}