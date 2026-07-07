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
class finance_ctl_monthend_verification extends desktop_controller{


    public function index($monthly_id){

        $base_filter = array();
        // $actions = array();

        $mdlMonthlyReport = $this->app->model('monthly_report');
        $this->report = $mdlMonthlyReport->getList('shop_id,bill_in_amount,bill_out_amount,ar_in_amount,ar_out_amount,begin_time,end_time,monthly_date,monthly_id',array('monthly_id'=>$monthly_id,'status'=>1),0,1);

        if(!$this->report) exit('Hack Attack');

        $this->report = $this->report[0];

        $shop_info = app::get('ome')->model('shop')->getList('name',array('shop_id'=>$this->report['shop_id']),0,1);

        $this->report['shop_name'] = $shop_info[0]['name'];

        $base_filter = array('monthly_id'=>$this->report['monthly_id']);

        if (!isset($_GET['view'])) {
            $_GET['view'] = 0;
        }

        $finder_id = isset($_GET['finder_id']) ? $_GET['finder_id'] : '';
        $finder_vid = isset($_GET['finder_vid']) ? $_GET['finder_vid'] : '';
        $return_view = isset($_GET['return_view']) ? $_GET['return_view'] : 0;
        $return_page = isset($_GET['return_page']) ? $_GET['return_page'] : 1;

        #增加销售应收单导出权限

        $actions = array (
            '0' => array('label' => '返回', 'href' => 'index.php?app=finance&ctl=monthend&act=index&finder_id='.$finder_id.'&finder_vid='.$finder_vid.'&view='.$return_view.'&page='.$return_page),
            'export' => array (
                'label'  => '导出',
                'class'  => 'export',
                'icon'   => 'add.gif',
                'submit'   => $this->url.'&act=index&action=export&p[]='.$monthly_id.'&view='.$_GET['view'],
                'target' => 'dialog::{width:600,height:300,title:\'导出\'}'
            ),
        );
        if($_GET['view'] == 0) {
            $actions['hexiao'] = array (
                'label'  => '规则核销',
                'submit'   => $this->url.'&act=ruleVerification&p[]='.$monthly_id.'&view='.$_GET['view'],
                'target' => 'dialog::{width:600,height:300,title:\'规则核销\'}'
            );
            $actions['batch_verification'] = array(
                'label'  => '批量核销',
                'href'   => 'javascript:void(0);',
                'submit' => $this->url.'&act=batchVerificationDialog&p[]='.$monthly_id,
                'target' => 'dialog::{width:760,height:400,title:\'批量核销\'}',
            );
            $actions['import'] = array(
                'label'  => '导入差异类型',
                'href' => 'index.php?app=finance&ctl=monthend_verification&act=displayImportV2&p[0]=finance_gap_type_import&finder_id={finder_id}&monthly_id='.$monthly_id,
                'target' => 'dialog::{width:760,height:300,title:\'' . app::get('desktop')->_('导入差异类型') . '\'}',
            );
        }
        if (!kernel::single('desktop_user')->has_permission('finance_export')) {
            unset($actions['export']);
        }

        $params = array(
            'title'=>sprintf("%s - %s - 待核销",$this->report['shop_name'],$this->report['monthly_date']),
            'actions' => $actions,
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export'=>false,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'use_buildin_selectrow'=>true,
            'base_filter' => $base_filter,
       );

       $this->finder('finance_mdl_monthly_report_items',$params);
    }


    function _views(){
        $finder_id = isset($_GET['finder_id']) ? $_GET['finder_id'] : '';
        $finder_vid = isset($_GET['finder_vid']) ? $_GET['finder_vid'] : '';
        $return_view = isset($_GET['return_view']) ? $_GET['return_view'] : 0;
        $return_page = isset($_GET['return_page']) ? $_GET['return_page'] : 1;
        $return_params = '&finder_id='.$finder_id.'&finder_vid='.$finder_vid.'&return_view='.$return_view.'&return_page='.$return_page;
        $sub_menu = array(
            // 0 => array('label'=>app::get('base')->_('全部'),'filter'=>array('monthly_id'=>$this->report['monthly_id']),'addon'=>'_FILTER_POINT_','optional'=>false,'href'=>'index.php?app=finance&ctl=monthend_verification&act=index&p[0]='.$this->report['monthly_id'].'&view=0'),
            array('label'=>app::get('base')->_('未核销'),'filter'=>array('monthly_id'=>$this->report['monthly_id'],'verification_status'=>'1'),'addon'=>'_FILTER_POINT_','optional'=>false,'href'=>'index.php?app=finance&ctl=monthend_verification&act=index&p[0]='.$this->report['monthly_id'].'&view=0'.$return_params),
            // array('label'=>app::get('base')->_('部分核销'),'filter'=>array('monthly_id'=>$this->report['monthly_id'],'status'=>1),'addon'=>'_FILTER_POINT_','optional'=>false,'href'=>'index.php?app=finance&ctl=monthend_verification&act=index&p[0]='.$this->report['monthly_id'].'&view=1'),
            array('label'=>app::get('base')->_('已核销'),'filter'=>array('monthly_id'=>$this->report['monthly_id'],'verification_status'=>'2'),'addon'=>'_FILTER_POINT_','optional'=>false,'href'=>'index.php?app=finance&ctl=monthend_verification&act=index&p[0]='.$this->report['monthly_id'].'&view=1'.$return_params),
            array('label'=>app::get('base')->_('全部'),'filter'=>array('monthly_id'=>$this->report['monthly_id']),'addon'=>'_FILTER_POINT_','optional'=>false,'href'=>'index.php?app=finance&ctl=monthend_verification&act=index&p[0]='.$this->report['monthly_id'].'&view=2'.$return_params),
        );
        return $sub_menu;
    }

    public function detailVerification($monthly_id,$order_bn)
    {
        $mdlBillBase = app::get('financebase')->model('bill_base');
        $mdlMonthlyReport = $this->app->model('monthly_report');

        $monthly_report_info = $mdlMonthlyReport->getList('begin_time,end_time,shop_id',array('monthly_id'=>$monthly_id));
        if(!$monthly_report_info) exit('无数据');
        $monthly_report_info = $monthly_report_info[0];

        $bill_data = kernel::single('finance_bill')->getListByOrderBn($order_bn);
        $ar_data = kernel::single('finance_ar')->getListByOrderBn($order_bn);

        $billRemark = array();
        $bill_unique_id = array_column($bill_data,'unique_id');
        $base_list = $mdlBillBase->getList('content,unique_id',array('unique_id|in'=>$bill_unique_id));
        $base_list = array_column($base_list,null,'unique_id');
        foreach ($base_list as $k=>$v) {
            $base_list[$k]['content'] = json_decode($v['content'],1);
        }

        $bill_list = $ar_list = array('other'=>array(),'current'=>array());

        foreach ($bill_data as $v) 
        {
            $v['remarks'] = $base_list[$v['unique_id']]['content']['remarks'];
            if($monthly_id == $v['monthly_id'] and $v['status'] == 0 and $v['charge_status'] == 1)
            {
                $bill_list['current'][] = $v;
            }else{
                $bill_list['other'][] = $v;
            }
        }
        unset($bill_data);

        foreach ($ar_data as $v) 
        {
            if($monthly_id == $v['monthly_id']  and $v['status'] == 0 and $v['charge_status'] == 1)
            {
                $ar_list['current'][] = $v;
            }else{
                $ar_list['other'][] = $v;
            }
        }
        unset($ar_data);

        $orderMdl = app::get('ome')->model('orders');
        $order_detail = $orderMdl->dump(array ('order_bn' => $order_bn,'shop_id' => $monthly_report_info['shop_id']),'mark_text');
    
        if ($order_detail['mark_text'] = @unserialize($order_detail['mark_text'])) {
            foreach ($order_detail['mark_text'] as $k=>$v){
                if (!strstr($v['op_time'], "-")){
                    $order_detail['mark_text'][$k]['op_time'] = date('Y-m-d H:i:s',$v['op_time']);
                }
            }
        }
        $this->pagedata['order_detail'] = $order_detail;

       
        $this->pagedata['bill_data'] = $bill_list;
        $this->pagedata['ar_data'] = $ar_list;

        $this->pagedata['monthly_id'] = $monthly_id;
        $this->pagedata['order_bn'] = $order_bn;

        $this->pagedata['shop_id'] = $monthly_report_info['shop_id'];

        $this->pagedata['finder_id'] = $_GET['finder_id'];

        // 获取差异类型列表（只显示有效的）
        $oGap = app::get('financebase')->model("gap");
        $gap_list = $oGap->getList('gap_name', array('status' => '1'));
        $this->pagedata['gap_list'] = $gap_list;

        // 查询已保存的差异类型
        $mdlItem = app::get('finance')->model('monthly_report_items');
        $saved_gap_type = $mdlItem->getList('gap_type', array('order_bn' => $order_bn, 'monthly_id' => $monthly_id), 0, 1);
        $this->pagedata['saved_gap_type'] = $saved_gap_type ? $saved_gap_type[0]['gap_type'] : '';

        $this->singlepage('monthed/verificate_detail.html');
    }


    // 检查核销
    public function checkVerificate(){
        $res = kernel::single('finance_verification')->checkVerificate($_POST);
        $res['data'] = base64_encode(json_encode($res));
        $res = json_encode($res);
        echo $res;
    }


    public function confirmVerification(){
        $data = base64_decode($_POST['data']);
        $data = json_decode($data,1);
        $this->pagedata['info'] = $data;
        $this->page('settlement/verificate_confirm.html');
    }


     //确认核销
    public function doVerificate(){
        $this->begin('');

        if ($_POST['gap_type']) {
            $this->_saveOrderGapType($_POST['monthly_id'], $_POST['order_bn'], $_POST['gap_type']);
        }

        $res = kernel::single('finance_verification')->doManVerificate($_POST);
        $this->end(true, app::get('base')->_('核销成功'));
    }

    // 移除应收应退单
    public function doRemove()
    {
        $ret = array('res'=>'fail','msg'=>'移除失败');
        $mdlBillAr = app::get('finance')->model('ar');
        $ar_id = intval($_POST['ar_id']);
        $ar_info = $mdlBillAr->getList('ar_bn,money,monthly_id',array('ar_id'=>$ar_id),0,1);
        $op_name = kernel::single('desktop_user')->get_name();
        if($ar_info)
        {
            $ar_info = $ar_info[0];
            $monthly_info = app::get('finance')->model('monthly_report')->getList('monthly_date',array('monthly_id'=>$ar_info['monthly_id']));
            if($mdlBillAr->update(array('charge_status'=>0,'charge_time'=>null),array('ar_id'=>$ar_id,'charge_status'=>1)))
            {
                finance_monthly_report::updateMonthlyAmount(array('monthly_id'=>$ar_info['monthly_id']));
                $ret['res'] = 'succ';
                finance_func::addOpLog($ar_info['ar_bn'],$op_name,'账单从'.$monthly_info[0]['monthly_date'].'移除','调账');
            }

        }
        echo json_encode($ret,1);
    }

    public function dialog_memo($monthly_id, $order_bn)
    {
        $this->pagedata['monthly_id'] = $monthly_id;
        $this->pagedata['order_bn']   = $order_bn;

        $this->display('monthed/memo.html');
    }

    public function save_memo($monthly_id, $order_bn)
    {
        $this->begin();

        $memo = $_POST['memo'];

        if (!$memo) $this->end(false, '备注不能为空');

        $mdlBill = app::get('finance')->model('bill');
        foreach ($mdlBill->getList('bill_id,memo', array ('monthly_id' => $monthly_id, 'order_bn' => $order_bn)) as $key => $value) {

            $mdlBill->update(array ('memo' => $value['memo'].'；'.$memo), array ('bill_id' => $value['bill_id']));
        }

        $mdlAr   = app::get('finance')->model('ar');
        foreach ($mdlAr->getList('ar_id,memo', array ('monthly_id' => $monthly_id, 'order_bn' => $order_bn)) as $key => $value) {
            $mdlAr->update(array ('memo' => $value['memo'].'；'.$memo), array ('ar_id' => $value['ar_id']));
        }

        $mdlItem   = app::get('finance')->model('monthly_report_items');
        foreach ($mdlItem->getList('id,memo', array ('monthly_id' => $monthly_id, 'order_bn' => $order_bn)) as $key => $value) {
            $mdlItem->update(array ('memo' => $value['memo'].'；'.$memo), array ('id' => $value['id']));
        }

        $this->end(true);
    }

    public function dialog_gap_type($monthly_id, $order_bn)
    {
        $this->pagedata['monthly_id'] = $monthly_id;
        $this->pagedata['order_bn']   = $order_bn;

        // 获取差异类型列表（只显示有效的）
        $oGap = app::get('financebase')->model("gap");
        $gap_list = $oGap->getList('gap_name', array('status' => '1'));
        $this->pagedata['gap_list'] = $gap_list;

        // 查询已保存的差异类型
        $mdlItem = app::get('finance')->model('monthly_report_items');
        $saved_gap_type = $mdlItem->getList('gap_type', array('order_bn' => $order_bn, 'monthly_id' => $monthly_id), 0, 1);
        $this->pagedata['saved_gap_type'] = $saved_gap_type ? $saved_gap_type[0]['gap_type'] : '';

        $this->display('monthed/gap_type.html');
    }

    public function save_gap_type($monthly_id, $order_bn)
    {
        $this->begin();

        $gap_type = $_POST['gap_type'];

        if (!$gap_type) $this->end(false, '差异类型不能为空');

        $mdlBill = app::get('finance')->model('bill');
        $mdlBill->update(array ('gap_type' => $gap_type),array ('order_bn'=>$order_bn,'monthly_id' => $monthly_id));

        $mdlAr   = app::get('finance')->model('ar');
        $mdlAr->update(array ('gap_type' => $gap_type), array ('order_bn' => $order_bn, 'monthly_id' => $monthly_id));

        $mdlItem   = app::get('finance')->model('monthly_report_items');
        foreach ($mdlItem->getList('id,memo', array ('monthly_id' => $monthly_id, 'order_bn' => $order_bn)) as $key => $value) {
            $mdlItem->update(array ('gap_type' => $gap_type), array ('id' => $value['id']));
        }
        $this->end(true);
    }

    /**
     * 批量核销弹窗（带进度条）
     */
    public function batchVerificationDialog($monthly_id)
    {
        if (isset($_POST['isSelectedAll']) && $_POST['isSelectedAll'] == '_ALL_') {
            die(app::get('finance')->_('暂不支持全选'));
        }

        $ids = isset($_POST['id']) ? $_POST['id'] : null;
        if (!is_array($ids)) {
            if ($ids !== null && $ids !== '') {
                $ids = array($ids);
            }
        }
        if (empty($ids) || !is_array($ids)) {
            die(app::get('finance')->_('请先选择要核销的单据'));
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids);
        if (count($ids) === 0) {
            die(app::get('finance')->_('请先选择要核销的单据'));
        }

        $mdlItem = app::get('finance')->model('monthly_report_items');
        $list = $mdlItem->getList('id', array(
            'id'                  => $ids,
            'monthly_id'          => $monthly_id,
            'verification_status' => '1',
        ), 0, -1);
        $GroupList = array_column($list, 'id');
        if (empty($GroupList)) {
            die(app::get('finance')->_('请先选择未核销的单据'));
        }

        $mr = app::get('finance')->model('monthly_report')->db_dump($monthly_id, 'shop_id');
        if (empty($mr['shop_id'])) {
            die(app::get('finance')->_('账期数据不存在'));
        }

        $oGap = app::get('financebase')->model('gap');
        $this->pagedata['gap_list'] = $oGap->getList('gap_name', array('status' => '1'));
        $this->pagedata['billName'] = '待核销单据';
        $this->pagedata['request_url'] = $this->url.'&act=doBatchVerification&monthly_id='.$monthly_id.'&shop_id='.$mr['shop_id'];
        $this->pagedata['maxProcessNum'] = 1;
        $this->pagedata['close'] = false;
        $this->pagedata['queueNum'] = 1;
        $this->pagedata['custom_html'] = $this->fetch('monthed/batch_verification.html');
        $this->pagedata['count'] = count($GroupList);
        $this->pagedata['arrId'] = json_encode($GroupList);
        $this->display('admin/request_add.html', 'ome');
    }

    /**
     * 批量核销分步执行（request_add 进度条调用）
     */
    public function doBatchVerification()
    {
        $monthly_id = isset($_GET['monthly_id']) ? intval($_GET['monthly_id']) : 0;
        $shop_id = isset($_GET['shop_id']) ? $_GET['shop_id'] : '';
        if (!$monthly_id || $shop_id === '') {
            $this->_batchVerificationResponse(array(
                'total'            => 0,
                'succ'             => 0,
                'fail'             => 0,
                'validation_error' => true,
                'fail_msg'         => array(array('msg' => '参数无效')),
            ));
        }

        $ajaxParams = isset($_POST['ajaxParams']) ? trim($_POST['ajaxParams']) : '';
        $itemIds = $ajaxParams === ''
            ? array()
            : array_values(array_unique(array_filter(array_map('intval', explode(';', $ajaxParams)))));

        $retArr = array(
            'total'    => count($itemIds),
            'succ'     => 0,
            'fail'     => 0,
            'fail_msg' => array(),
        );

        if (count($itemIds) === 0) {
            $retArr['validation_error'] = true;
            $retArr['fail_msg'][] = array('msg' => '缺少待处理数据');
            $this->_batchVerificationResponse($retArr);
        }

        $gap_type = isset($_POST['gap_type']) ? trim($_POST['gap_type']) : '';

        $is_verification = isset($_POST['is_verification']) ? intval($_POST['is_verification']) : 0;
        $verification_memo = isset($_POST['verification_memo']) ? trim($_POST['verification_memo']) : '';
        if ($is_verification == 1 && $gap_type === '') {
            $retArr['validation_error'] = true;
            $retArr['fail_msg'][] = array('msg' => '强制核销时差异类型不能为空');
            $this->_batchVerificationResponse($retArr);
        }
        if ($is_verification == 1 && $verification_memo === '') {
            $retArr['validation_error'] = true;
            $retArr['fail_msg'][] = array('msg' => '填写强制核销备注');
            $this->_batchVerificationResponse($retArr);
        }

        $mdlItem = app::get('finance')->model('monthly_report_items');
        foreach ($itemIds as $itemId) {
            $row = $mdlItem->db_dump(array('id' => $itemId), 'order_bn,monthly_id,verification_status');
            if (empty($row)) {
                $retArr['fail'] += 1;
                $retArr['fail_msg'][] = array('msg' => '单据缺少');
                continue;
            }
            if ($row['verification_status'] == '2') {
                $retArr['fail'] += 1;
                $retArr['fail_msg'][] = array('obj_bn' => $row['order_bn'], 'msg' => '已核销');
                continue;
            }

            $ids = $this->_getCurrentVerificateIds($row['monthly_id'], $row['order_bn']);
            $params = array(
                'monthly_id'        => $row['monthly_id'],
                'order_bn'          => $row['order_bn'],
                'shop_id'           => $shop_id,
                'bill_id'           => $ids['bill_id'],
                'ar_id'             => $ids['ar_id'],
                'gap_type'          => $gap_type,
                'is_verification'   => $is_verification,
                'verification_memo' => $verification_memo,
            );

            $this->_saveOrderGapType($row['monthly_id'], $row['order_bn'], $gap_type);

            $res = kernel::single('finance_verification')->doManVerificate($params);
            if ($res['status'] == 'success') {
                $retArr['succ'] += 1;
            } else {
                $retArr['fail'] += 1;
                $msg = !empty($res['msg']) ? $res['msg'] : '核销失败';
                $retArr['fail_msg'][] = array('obj_bn' => $row['order_bn'], 'msg' => $msg);
            }
        }

        finance_monthly_report::updateMonthlyAmount(array('monthly_id' => $monthly_id));

        $this->_batchVerificationResponse($retArr);
    }

    /**
     * 批量核销统一响应（request_add 约定：total/succ/fail/fail_msg）
     */
    protected function _batchVerificationResponse($retArr)
    {
        echo json_encode($retArr);
        exit;
    }

    /**
     * 获取本期待核销的实收实退、应收应退 id 列表
     */
    protected function _getCurrentVerificateIds($monthly_id, $order_bn)
    {
        $bill_ids = array();
        $ar_ids = array();

        $bill_data = kernel::single('finance_bill')->getListByOrderBn($order_bn);
        foreach ($bill_data as $v) {
            if ($monthly_id == $v['monthly_id'] && $v['status'] == 0 && $v['charge_status'] == 1) {
                $bill_ids[] = $v['bill_id'];
            }
        }

        $ar_data = kernel::single('finance_ar')->getListByOrderBn($order_bn);
        foreach ($ar_data as $v) {
            if ($monthly_id == $v['monthly_id'] && $v['status'] == 0 && $v['charge_status'] == 1) {
                $ar_ids[] = $v['ar_id'];
            }
        }

        return array('bill_id' => $bill_ids, 'ar_id' => $ar_ids);
    }

    /**
     * 保存订单差异类型到 bill、ar、monthly_report_items
     */
    protected function _saveOrderGapType($monthly_id, $order_bn, $gap_type)
    {
        if (!$gap_type) {
            return;
        }

        app::get('finance')->model('bill')->update(
            array('gap_type' => $gap_type),
            array('order_bn' => $order_bn, 'monthly_id' => $monthly_id)
        );

        app::get('finance')->model('ar')->update(
            array('gap_type' => $gap_type),
            array('order_bn' => $order_bn, 'monthly_id' => $monthly_id)
        );

        app::get('finance')->model('monthly_report_items')->update(
            array('gap_type' => $gap_type),
            array('order_bn' => $order_bn, 'monthly_id' => $monthly_id)
        );
    }

    public function ruleVerification($id) {
        $mr = app::get('finance')->model('monthly_report')->db_dump($id, 'shop_id');
        $filter = array(
            'monthly_id' => $id,
            'verification_status' => '1',
        );
        $filter = array_merge($filter, $_POST);
        $list = app::get('finance')->model('monthly_report_items')->getList('id', $filter, 0, 10000);
        $GroupList = array_column($list, 'id');
        $this->pagedata['request_url'] = $this->url.'&act=doRuleVerification&shop_id='.$mr["shop_id"];
        $this->pagedata['itemCount'] = count($GroupList);
        $this->pagedata['GroupList'] = json_encode($GroupList);
        $this->pagedata['maxNum']    = 10;
        parent::dialog_batch();
    }

    public function doRuleVerification() {
        $itemIds = explode(',',$_POST['primary_id']);

        if (!$itemIds) { echo 'Error: 缺少调整单明细';exit;}

        $retArr = array(
            'itotal'  => count($itemIds),
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );
        foreach($itemIds as $itemId) {
            $row = app::get('finance')->model('monthly_report_items')->db_dump(['id'=>$itemId], 'order_bn,gap');
            if(empty($row)) {
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = '单据缺少';
                continue;
            }
            list($rs, $rsData) = kernel::single('finance_monthly_report_items')->doAutoVerificate($itemId, $_GET['shop_id']);
        
            if($rs) {
                $retArr['isucc'] += 1;
            } else {
                $retArr['ifail'] += 1;
                $retArr['err_msg'][] = $row['order_bn'].':'.$rsData['msg'];
            }
        }

        $firstRow = app::get('finance')->model('monthly_report_items')->db_dump(['id'=>$itemIds[0]], 'monthly_id');
        if(!empty($firstRow['monthly_id'])) {
            finance_monthly_report::updateMonthlyAmount(array('monthly_id'=>$firstRow['monthly_id']));
        }

        echo json_encode($retArr),'ok.';exit;
    }

    public function base_list($id){
        $row = app::get('finance')->model('monthly_report_items')->db_dump(['id'=>$id], 'order_bn');
        $params = array(
            'actions'=>[],
            'title'=>'店铺收支明细',
            'use_buildin_recycle'=>false,
            'use_buildin_selectrow'=>false,
            'use_buildin_filter'=>false,
            'use_buildin_setcol'=>false,
            'base_filter' => ['order_bn'=>$row['order_bn']],
            'finder_aliasname' => 'finance_verification_base_list',
            'finder_cols'=>'shop_id,trade_no,order_bn,trade_time,money,trade_type,remarks,bill_category,member,financial_no,out_trade_no',
            'orderBy'=> 'id desc',
        );
        $this->finder('financebase_mdl_base', $params);
    }

    public function sale_list($id){
        $row = app::get('finance')->model('monthly_report_items')->db_dump(['id'=>$id], 'order_bn');
        $orderObj = app::get('ome')->model('orders');
        $list = $orderObj->getList('order_id', ['order_bn'=>$row['order_bn']]);
        $plateList = $orderObj->getList('order_id', ['platform_order_bn'=>$row['order_bn']]);
        $list = array_merge($list, $plateList);
        $params = array(
            'actions'=>[],
            'title'=>'销售单',
            'use_buildin_recycle'=>false,
            'use_buildin_selectrow'=>false,
            'use_buildin_filter'=>false,
            'use_buildin_setcol'=>false,
            'base_filter' => ['order_id'=>array_column($list, 'order_id')],
            'finder_aliasname' => 'finance_verification_sale_list',
            //'finder_cols'=>'shop_id,trade_no,order_bn,trade_time,money,trade_type,remarks,bill_category,member,financial_no,out_trade_no',
            'orderBy'=> 'sale_id desc',
        );
        $this->finder('sales_mdl_sales', $params);
    }

    /**
     * 新版导出模板方法，参考订单的实现方式
     */
    public function exportTemplateV2()
    {
        $fileName = "差异类型导入模板.xlsx";
        $title = app::get('finance')->model('monthly_report_items')->exportTemplateV2('finance_monthly_report_items_import');
        kernel::single('omecsv_phpoffice')->export($fileName, [0 => $title]);
    }

    /**
     * 导入差异类型页面
     */
    public function displayImportV2($type='', $extraParams=[])
    {
        // 如果是财务差异类型导入，添加monthly_id
        if ($type === 'finance_gap_type_import' && isset($_GET['monthly_id'])) {
            $extraParams[] = [
                'name' => 'queue_data[monthly_id]',
                'value' => $_GET['monthly_id']
            ];
        }
        
        // 调用父类方法，传递额外参数
        parent::displayImportV2($type, $extraParams);
    }
    
}
