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
 * @author ykm 2015-11-19
 * @describe 特殊订单审核通过及发货
 */
class brush_ctl_admin_delivery extends desktop_controller
{
    function __construct($app)
    {
        if(in_array($_GET['act'], ['toPrintExpre'])) {
            $this->checkCSRF = false;
        }
        
        parent::__construct($app);
    }

    public function _views()
    {
        static $sub_menu;
        
        $brushDelivery = $this->app->model('delivery');
        
        if($sub_menu) {
            return $sub_menu;
        }
        
        $sub_menu = array(
            0 => array('label'=>app::get('base')->_('待发货'),'filter'=>array('status'=>array('ready','progress') ),'optional'=>false),
            1 => array('label'=>app::get('base')->_('已打印'),'filter'=>array('status'=>array('progress'), 'expre_status'=>'true'),'optional'=>false),
            2 => array('label'=>app::get('base')->_('已发货'),'filter'=>array('status'=>'succ'),'optional'=>false,'addon'=>'_FILTER_POINT_'),
            3 => array('label'=>app::get('base')->_('全部'),'filter'=>array('status|noequal'=>'cancel'),'optional'=>false,'addon'=>'_FILTER_POINT_'),
        );
        $dly = app::get('ome')->model('dly_corp')->getList('corp_id,name',array('disabled'=>'false'));
        foreach($dly as $val){
            $sub_menu[] = array('label'=>$val['name'],'filter'=>array('status'=>array('ready','progress'),'logi_id'=>$val['corp_id']),'optional'=>false);
        }
        
        foreach ($sub_menu as $k => $v)
        {
            if (!$v['addon']) $sub_menu[$k]['addon'] = $brushDelivery->count($v['filter']);
            $sub_menu[$k]['href'] = 'index.php?app=brush&ctl=' . $_GET['ctl'] . '&act=' . $_GET['act'] . '&view=' . $k;
        }
        
        return $sub_menu;
    }

    public function index()
    {
        $subMenu = $this->_views();
        
        $use_export = true;
        
        if(!isset($_GET['view']) || $_GET['view'] == '') {
            foreach($subMenu as $k => $v) {
                if($v['addon'] > 0) {
                    $_GET['view'] = $k;
                    break;
                }
            }
            
            is_null($_GET['view']) && $_GET['view'] = 0;
        }
        
        //export
        if($_GET['view'] == '' || $_GET['view'] == '0'){
            $use_export = false;
        }
        
        if($_GET['action'] && $_POST['isSelectedAll'] == '_ALL_') {
            $_POST = $_POST ? array_merge($subMenu[$_GET['view']]['filter'], $_POST) : $subMenu[$_GET['view']]['filter'];
        }
        
        $params = array(
            'title'=>'特殊订单列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'=>false,
            'use_buildin_recycle'=>false,
            'use_buildin_export' => $use_export,
            'use_buildin_import'=>false,
            'use_buildin_filter'=>true,
            'use_view_tab' => true,
        );
        
        $params['actions'] = $this->_getAction();
        
        $this->finder('brush_mdl_delivery',$params);
    }

    private function _getAction()
    {
        $subMenu = $this->_views();
        
        $act = 'toPrintExpre';
        $query = http_build_query($subMenu[$_GET['view']]['filter']);
        
        $action = array(
            'expre' => array(
                'label' => '打印快递单',
                'submit' => 'index.php?app=brush&ctl=admin_print&act=' . $act . '&' . $query,
                'onclick'=>"_hmt.push(['_trackEvent', '订单发货操作', '打印快递单', 'b-打印快递单']);",
                'target' => '_blank',
            ),
            'delivery' => array(
                'label' => '开始发货',
                'submit' => 'index.php?app=brush&ctl=admin_delivery&act=delivery',
                'target' => 'dialog::{title:\'开始发货\',width:650,height:260}',
                'confirm' =>' 确认要进行发货么？'
            ),
            'changeDly' => array(
                'label' => '批量更换物流',
                'submit' => 'index.php?app=brush&ctl=admin_logistics&act=toChangeLogistics&' . $query,
                'target' => 'dialog::{title:\'批量更换物流公司\',width:680,height:500}',
            )
        );
        
        switch($_GET['view'])
        {
            case 2 :
            case 3 :
                unset($action['delivery'], $action['changeDly']);
                break;
            case 0 :
            case 1 :
                $action['process'] = array(
                        'label' => '导出未发货',
                        'submit' => 'index.php?app=brush&ctl=admin_delivery&act=unDelivery&action=export',
                        'target' => 'dialog::{title:\'导出未发货\',width:400,height:200}',
                );
                
                $action['import'] = array(
                    'label' => '导入未发货',
                    'href' => 'index.php?app=brush&ctl=admin_delivery&act=unDelivery&action=import',
                    'target' => 'dialog::{title:\'导入未发货\',width:400,height:200}',
                );
                
                break;
            default : break;
        }
        
        return $action;
    }

    public function unDelivery()
    {
        $subMenu = $this->_views();
        
        if($_GET['action'] && $_POST['isSelectedAll'] == '_ALL_') {
            $_POST = $_POST ? array_merge($subMenu[$_GET['view']]['filter'], $_POST) : $subMenu[$_GET['view']]['filter'];
        }
        
        $params = array(
            'title'=>'特殊订单未发货导出'
        );
        
        $this->finder('brush_mdl_undelivery', $params);
    }
    
    public function delivery()
    {
        $arrDeliveryId = $this->_getSelectedId();
        
        $this->pagedata['deliveryCount'] = count($arrDeliveryId);
        $this->pagedata['deliveryGroup'] = json_encode($arrDeliveryId);
        
        $this->display("admin/delivery.html");
    }
    
    /**
     * 开始虚拟发货
     */
    public function ajaxDealDelivery()
    {
        $db = kernel::database();
        $brushDelivery = kernel::single('brush_delivery');
        
        $arrObjId = explode(';', $_POST['ajaxParams']);
        
        $deliveryData = $this->app->model('delivery')->getList('delivery_id,delivery_bn,expre_status,status,logi_id,logi_no', array('delivery_id'=>$arrObjId));
        
        $orderIds = $this->app->model('delivery_order')->getList('*', array('delivery_id'=>$arrObjId));
        
        $arrDeliveryOrder = array();
        foreach($orderIds as $val) {
            $arrDeliveryOrder[$val['delivery_id']] = $val['order_id'];
        }
        
        $fail = $succ = $noPrint =0;
        
        $this->begin();
        
        $errorList = array();
        foreach($deliveryData as $delivery)
        {
            if($delivery['expre_status'] == 'false') {
                $fail++;
                $noPrint++;
                
                $errorList[] = '发货单号：'. $delivery['delivery_bn'] .' 未打印';
                
                continue;
            }
            
            if($delivery['status'] == 'succ') {
                $succ++;
                continue;
            }
            
            $params = array('logi_id'=>$delivery['logi_id'], 'logi_no'=>$delivery['logi_no']);
            
            $db->exec('SAVEPOINT batchDelivery');
            
            $error_msg = '';
            $ret = $brushDelivery->finishDeliver($delivery['delivery_id'], $arrDeliveryOrder[$delivery['delivery_id']], $params, $error_msg);
            if(!$ret) {
                $fail++;
                
                if($error_msg){
                    $errorList[] = $error_msg;
                }
                
                $db->exec('ROLLBACK TO SAVEPOINT batchDelivery');
            } else {
                $succ++;
            }
        }
        
        $this->endonly(true);
        
        $retData['total'] = $fail + $succ;
        $retData['fail'] = $fail;
        $retData['succ'] = $succ;
        $retData['noPrint'] = $noPrint;
        
        if($errorList){
            $error_msg = implode(';', $errorList);
            $retData['error_msg'] = substr($error_msg, 0, 200);
        }
        
        echo json_encode($retData);
    }
    
    public function doDetail()
    {
        if (empty($_POST['dly'])) {
            $this->splash('error', null, '保存失败');
        }
        
        if (empty($_POST['dly']['logi_id'])) {
            $this->splash('error', null, '请选择物流公司');
        }
        
        $ObjDelivery = $this->app->model('delivery');
        $delivery = $ObjDelivery->dump($_POST['dly']['delivery_id'], 'delivery_id,status,weight,logi_id,logi_no,ship_area,net_weight,memo');
        if(in_array($delivery['status'], array('cancel', 'back', 'stop', 'return_back'))) {
            $this->splash('error', null, '发货单已撤销不能编辑');
        }
        
        $data['logi_id'] = $_POST['dly']['logi_id'];
        $data['logi_no'] = $_POST['dly']['logi_no'];
        if(isset($_POST['weight'])) {
            $data['weight'] = $_POST['weight'];
        }
        
        if($data['logi_id'] != $delivery['logi_id']) {//切换物流公司
            $changeLogistics = kernel::single('brush_logistics')->checkChangeLogistics($data, $delivery);
            if(!$changeLogistics['result']) {
                $this->splash('error', null, $changeLogistics['msg']);
            } else {
                $succMsg = '保存成功，快递公司切换，需重新打印';
                
                $corpRows = app::get('ome')->model('dly_corp')->getList('corp_id,name', array('corp_id' => array($data['logi_id'], $delivery['logi_id'])));
                
                foreach($corpRows as $cVal) {
                    if($cVal['corp_id'] == $delivery['logi_id']) {
                        $oldCorpName = $cVal['name'];
                    } elseif($cVal['corp_id'] == $data['logi_id']) {
                        $newCorpName = $cVal['name'];
                    }
                }
                
                $logMsg = '物流公司：' . $oldCorpName . ' => ' . $newCorpName;
            }
        } else {
            if($data['logi_no'] == $delivery['logi_no']) {
                unset($data['logi_no']);
            }
            
            if($data['weight'] == $delivery['weight']) {
                unset($data['weight']);
            } elseif(isset($data['weight'])) {
                $arrArea = explode(':', $delivery['ship_area']);
                $area_id = $arrArea[2];
                $data['delivery_cost_actual'] = app::get('ome')->model('delivery')->getDeliveryFreight($area_id,$data['logi_id'],$data['weight']);
            }
            
            unset($data['logi_id']);
        }
        
        if($_POST['dly']['memo'] != $delivery['memo']) {
            $data['memo'] = $_POST['dly']['memo'];
        }
        
        if(count($data)) {
            if($data['logi_no'] && $ObjDelivery->dump(array('delivery_id|noequal' => $delivery['delivery_id'], 'logi_no' => $data['logi_no']))) {
                $this->splash('error', null, '已有此物流单号');
            }
            
            $this->begin();
            
            $ret = kernel::single('brush_logistics')->changeLogistics($data, $delivery['delivery_id'], $logMsg);
            if(!$ret) {
                $this->end(false, '保存失败');
            }
            
            $succMsg = $succMsg ? $succMsg : '保存成功';
            
            $this->end(true, $succMsg);
        } else {
            $this->splash('success', null, '没有数据发生变化');
        }
    }

    /**
     * 批量审核订单并发货
     */
    public function ajaxDoAuto()
    {
        $brushDelivery = kernel::single('brush_delivery');
        $db = kernel::database();
        
        $arrObjId = explode(';', $_POST['ajaxParams']);
        $corp_id  = $_POST['need_delivery'] ? $_POST['corp_id'] : 0;
        
        //result
        $retData = array('total'=>0, 'succ'=>0, 'fail'=> 0, 'error_msg'=>'');
        
        //delivery
        $succ = 0;
        $data = $brushDelivery->orderToDelivery($arrObjId, $corp_id);
        if(!$data){
            $retData['fail'] = 1;
            $retData['error_msg'] = 'Error: 没有可操作的订单：[虚拟发货]仅限淘宝平台，请检查';
            echo json_encode($retData);
            exit;
        }
        
        $fail = count($arrObjId) - count($data);
        
        $this->begin();
        
        //data
        foreach($data as $val)
        {
            $db->exec('SAVEPOINT delivery_start');
            
            $order_id = $val['main']['order_id'];
            unset($val['main']['order_id']);
            
            $mainData = $val['main'];
            $items = $val['item'];
            $itemsDetail = $val['itemDetail'];
            $mainData['delivery_bn'] = $brushDelivery->generateBrushDeliveryBn();
            
            $ret = $this->app->model('delivery')->insert($mainData);
            if(!$ret) {
                $fail += 1;
                $db->exec('ROLLBACK TO SAVEPOINT delivery_start');
                continue;
            }
            
            foreach($items as &$item)
            {
                $item['delivery_id'] = $mainData['delivery_id'];
                
                $ret = $this->app->model('delivery_items')->insert($item);
                if(!$ret) {
                    $fail += 1;
                    $db->exec('ROLLBACK TO SAVEPOINT delivery_start');
                    continue 2;
                }
            }
            
            foreach($itemsDetail as &$detail)
            {
                $detail['delivery_id'] = $mainData['delivery_id'];
                $detail['delivery_item_id'] = $items[$detail['bn']]['item_id'];
                
                $ret = $this->app->model('delivery_items_detail')->insert($detail);
                if(!$ret) {
                    $fail += 1;
                    $db->exec('ROLLBACK TO SAVEPOINT delivery_start');
                    continue 2;
                }
            }
            
            $deliOrder = array('order_id'=>$order_id, 'delivery_id'=>$mainData['delivery_id']);
            $ret = $this->app->model('delivery_order')->insert($deliOrder);
            if(!$ret) {
                $fail += 1;
                $db->exec('ROLLBACK TO SAVEPOINT delivery_start');
                continue;
            }
            
            app::get('ome')->model('operation_log')->write_log('order_confirm@ome', $order_id, '订单确认(发货单号：'. $mainData['delivery_bn'] .')');
            if($corp_id) {
                //更新订单为[已确认]状态
                $orderUpdate = array('process_status'=>'confirmed');
                $ret = app::get('ome')->model('orders')->update($orderUpdate, array('order_id'=>$order_id, 'process_status|noequal'=>'confirmed'));
                $ret = is_bool($ret) ? false : true;
            } else {
                $ret = app::get('ome')->model('operation_log')->write_log('delivery_brush_expre@brush', $mainData['delivery_id'], '快递单打印(自动发货模拟)');
                if(!$ret) {
                    $fail += 1;
                    $db->exec('ROLLBACK TO SAVEPOINT delivery_start');
                    continue;
                }
                
                $brushDelivery->log_msg = '批量审单自动发货成功(' . $mainData['delivery_bn'] . ')';
                
                $ret = $brushDelivery->finishDeliver($mainData['delivery_id'], $order_id);
            }
            
            if(!$ret) {
                $fail += 1;
                $db->exec('ROLLBACK TO SAVEPOINT delivery_start');
                continue;
            }
            
            $succ += 1;
        }
        
        $this->endonly(true);
        
        $retData['total'] = $fail + $succ;
        $retData['fail'] = $fail;
        $retData['succ'] = $succ;
        
        echo json_encode($retData);
    }

    private function _getSelectedId()
    {
        $objModel = $this->app->model('delivery');
        
        if($_POST['isSelectedAll'] == '_ALL_') {
            unset($_POST['isSelectedAll']);
            
            $view = intval($_POST['view']);
            $subMenu = $this->_views();
            $baseFilter = $subMenu[$view]['filter'];
            $param = array_merge($baseFilter, $_POST);
            $objModel->defaultOrder = '';
            $objModel->filter_use_like = true;
            $selData = $objModel->getList($objModel->idColumn, $param, 0, -1);
            $arrObjId = array();
            foreach($selData as $val) {
                $arrObjId[] = $val[$objModel->idColumn];
            }
        } else {
            $arrObjId = $_POST[$objModel->idColumn];
        }
        
        if(empty($arrObjId)) {
            $this->splash('success', $this->url, '没有选择的发货单');
        }
        
        return $arrObjId;
    }

    /**
     * 加密字段显示明文
     * 
     * @param int $delivery_id
     * @return html
     */
    public function showSensitiveData($delivery_id)
    {
        $deliveryMdl = app::get('brush')->model('delivery');
        
        $delivery = $deliveryMdl->db_dump($delivery_id,'shop_id,shop_type,ship_name,ship_tel,ship_mobile,ship_addr,delivery_id,delivery_bn,member_id,ship_province,ship_city,ship_district,memo');
        
        if ($delivery['member_id']) {
            $member = app::get('ome')->model('members')->db_dump($delivery['member_id'],'uname');
        
            $delivery['uname'] = $member['uname'];
        }
        
        $order_ids = $deliveryMdl->getOrderIdByDeliveryId($delivery['delivery_id']);
        
        $order = app::get('ome')->model('orders')->db_dump(array ('order_id'=>$order_ids), '*');
        $delivery['order_bn'] = $order['order_bn'];
        
        $obj = kernel::single('ome_security_router', $delivery['shop_type']);
        
        if ($obj->allowDecrypt($order['shop_id'], $order['shop_type'], $order['status'])){
            // 处理加密
            $delivery['encrypt_body'] = $obj->get_encrypt_body($delivery, 'delivery');
        
            // 推送日志
            kernel::single('base_hchsafe')->order_log(array('operation'=>'查看发货单收货人信息','tradeIds'=>array($delivery['delivery_bn'])));
        }
        
        $this->splash('success',null,null,'redirect',$delivery);
        
    }
}