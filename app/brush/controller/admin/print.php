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
 * @author ykm 2015-12-02
 * @describe 特殊订单打印
 */
class brush_ctl_admin_print extends desktop_controller
{
    function __construct($app)
    {
        if(in_array($_GET['act'], ['toPrintExpre'])) {
            $this->checkCSRF = false;
        }
        
        parent::__construct($app);
    }
    
    /**
     * toPrintShip
     * @return mixed 返回值
     */

    public function toPrintShip()
    {
        $this->toPrintExpre();//仅使用安装logisticsmanager后的打印方式
    }
    
    /**
     * 安装logisticsmanager后使用
     */
    public function toPrintExpre()
    {
        $objBrushPrint = kernel::single('brush_print');
        
        $filter = $_POST['isSelectedAll'] == '_ALL_' ? array_merge($_POST, $_GET) : $_POST;
        
        $objBrushPrint->checkDelivery($filter);
        if($objBrushPrint->msg['error_msg']) {
            $this->message($objBrushPrint->msg['error_msg']);
        }
        
        if(!isset($_GET['isdown'])) {
            $objBrushPrint->noRepeatPrint();
        }
        
        if($objBrushPrint->msg['error_msg']) {
            $this->message($objBrushPrint->msg['error_msg']);
        }
        
        $eleParam['logi_id'] = $objBrushPrint->logi_id;
        $eleParam['delivery'] = $objBrushPrint->noLogiDelivery;
        $objElectron = kernel::single('brush_electron', $eleParam);
        if ($isElectron = $objElectron->isElectron()) {
            //获取电子面单
            if($objBrushPrint->noLogiDelivery) {//对没有运单号的发货单操作
                $gwnRet = $objElectron->getWaybillNumber();
                if ($gwnRet) {
                    if ($gwnRet === true) {
                        list($getWaybill, $notGetWaybill) = $objElectron->getBufferWaybill();
                        $objBrushPrint->setDelivery($getWaybill);
                        $objBrushPrint->delDelivery($notGetWaybill, '获取电子面单失败');
                    } else if (is_numeric($gwnRet)) {//直连单次获取
                        $directParam = array(
                            'get' => $_GET,
                            'ids' => array_keys($objBrushPrint->delivery),
                            'channel' => $objElectron->channel,
                            'directNum' => $gwnRet
                        );
                        $this->getElectronLogiNo($directParam);
                    } else {
                        $msg = $gwnRet['msg'] ? $gwnRet['msg'] : "返回不明数据";
                        $this->message($msg);
                    }
                } else {
                    $msg = $objElectron->msg ? $objElectron->msg : '获取电子面单失败，无法打印';
                    $this->message($msg);
                }
            }
            
            //获取大头笔
            $objElectron->delivery = $objBrushPrint->delivery;//对所有的发货单进行操作
            $notGetWaybillExtend = $objElectron->getWaybillExtend();
            if($notGetWaybillExtend == false ) {
                if($objElectron->msg) {
                    $this->message($objElectron->msg);
                }
            } else {
                $objBrushPrint->delDelivery($notGetWaybillExtend, '获取电子面单大头笔失败');
            }
        }
        
        if(empty($objBrushPrint->delivery)) {
            $arrMsg = array_unique($objBrushPrint->showError['errInfo']);
            $msg = $arrMsg ? implode(',', $arrMsg) : '没有发货单';
            $this->message($msg);
        }
        
        $express_company_no = strtoupper($objElectron->corp['type']);
        $objExpress = logisticsmanager_print_express::instance($express_company_no, $this);
        if($objExpress->getExpressTpl($objElectron->corp)) {
            $objPrintData = kernel::single('logisticsmanager_print_data');
            $printData = $objPrintData->dealPrintData($objBrushPrint->delivery, $objElectron->corp, $objExpress->printField, 'brush');
            
            $objPageData = kernel::single('brush_pagedata');
            $objPageData->init($objBrushPrint, $objElectron, $objExpress);
            $this->pagedata = $objPageData->getPageData($printData);
            
            //御城河 START
            $tradeIds = $this->pagedata['o_bn'];
            $hchsafe = array(
                'operation' => '订单快递单打印',
                'tradeIds'  => $tradeIds,
            );
            kernel::single('base_hchsafe')->order_log($hchsafe);
            
            //御城河 END
            $params = array('order_bn'=>$this->pagedata['o_bn']);
            $objExpress->setParams($params)->getTmpl();
        } else {
            $msg = $objExpress->msg ? $objExpress->msg : '获取打印模板失败';
            $this->message($msg);
        }
    }

    //直连获取电子面单运单号
    /**
     * 获取ElectronLogiNo
     * @param mixed $directParam directParam
     * @return mixed 返回结果
     */
    public function getElectronLogiNo($directParam)
    {
        $urlParams = json_encode($directParam['get']);
        $postIds = json_encode($directParam['ids']);
        $request_uri = kernel::single('base_component_request')->get_request_uri();
        $this->pagedata['urlParams'] = $urlParams;
        $this->pagedata['postIds'] = $postIds;
        $this->pagedata['channel'] = $directParam['channel'];
        $this->pagedata['directNum'] = $directParam['directNum'];
        $this->pagedata['request_uri'] = base64_encode($request_uri);
        $this->singlepage('admin/delivery/getelectronlogino.html');
        exit();
    }

    //逐批获取电子面单(进度条)
    /**
     * asyncLoginoPage
     * @return mixed 返回值
     */
    public function asyncLoginoPage() {
        $channel_id = $_GET['channel_id'];
        $request_uri = base64_decode($_GET['request_uri']) . '&isdown=1';
        $this->pagedata['channel_id'] = $channel_id;
        $this->pagedata['request_uri'] = $request_uri;
        $this->pagedata['MaxProcessOrderNum'] = $_GET['directNum'] ? intval($_GET['directNum']) : 1;
        $ids = explode(',', urldecode($_GET['itemIds']));
        $this->pagedata['postIds'] = json_encode($ids);
        $count = count($ids);
        $this->pagedata['count'] = $count;
        $this->display('admin/delivery/asyncLoginoPage.html');
    }

    /**
     * 获取WaybillLogiNo
     * @return mixed 返回结果
     */
    public function getWaybillLogiNo() {
        $channel_id = $_POST['channel_id'];
        $delivery_id = explode(';',$_POST['id']);
        $cFilter = array(
            'channel_id' => $channel_id,
            'status'=>'true',
        );
        $data['channel'] = app::get("logisticsmanager")->model("channel")->dump($cFilter);
        $data['delivery'] = $this->app->model('delivery')->getList('*', array('delivery_id'=>$delivery_id));
        $objElectron = kernel::single('brush_electron', $data);
        $result  = $objElectron->getDirectWaybill($delivery_id);
        echo json_encode($result);
    }

    private function message($msg) {
        $this->pagedata['err'] = 'true';
        $this->pagedata['base_dir'] = kernel::base_url();
        $this->pagedata['time'] = date("Y-m-d H:i:s");
        $this->pagedata['msg'] = $msg;
        $this->singlepage('admin/print/message.html');
        exit();
    }

    /**
     * insertExpress
     * @return mixed 返回值
     */
    public function insertExpress() {
        if (empty($_POST['id'])) {
            exit("请录入快递单号");
        }
        $ids = $_POST['id'];
        $dlyObj = $this->app->model('delivery');
        $rows = $dlyObj->getList('delivery_id,delivery_bn,status,process,logi_no', array('delivery_id'=>array_keys($ids)));
        $arrDelivery = array();
        foreach($rows as $row) {
            $arrDelivery[$row['delivery_id']] = $row;
        }
        $errmsg = '';
        if ($ids)
            foreach ($ids as $k => $i) {
                $i = $i ? trim($i) : null;
                $delivery = $arrDelivery[$k];
                $bn = $delivery['delivery_bn'];
                $arr_s = array('succ', 'cancel', 'back', 'stop','return_back');
                if (in_array($delivery['status'], $arr_s) || $delivery['process'] == 'true') {
                    $errmsg .= "发货单" . $bn . "相关信息不能修改\n";
                    unset($ids[$k]);
                    continue;
                }
                if($delivery['logi_no'] == $i) {
                    unset($ids[$k]);
                    continue;
                }
                unset($delivery);
                if ($dlyObj->dump(array('logi_no'=>$i, 'delivery_id|noequal'=>$k))) {
                    exit("物流单号已存在，发货单为" . $bn);
                }

                if(empty($i) && $_GET['chklogi'] != 1){
                    exit("物流单号不能为空，发货单为" . $bn);
                }
            }
        $opObj = app::get('ome')->model('operation_log');
        if ($ids) {
            $db = kernel::database();
            $db->beginTransaction();
            foreach ($ids as $key => $item) {
                $item = $item ? trim($item) : null;
                $db->exec('SAVEPOINT saveLogiNo');
                $ret = kernel::single('brush_logistics')->changeLogistics(array('logi_no'=>$item), $key);
                if(!$ret) {
                    $db->exec('ROLLBACK TO SAVEPOINT saveLogiNo');
                    $errmsg .= "发货单" . $arrDelivery[$key]['delivery_bn'] . "保存失败\n";
                }
            }
            $db->commit(true);
        }
        app::get('ome')->setConf('print_logi_version_'.$_POST['print_logi_id'], intval($_POST['logi_version']));
        if($errmsg && !empty($errmsg)){
            $errmsg .= "\n请将以上报错的打印单据作废或重新操作，其它单据保存成功";
            exit($errmsg);
        }
        echo "SUCC";
    }

    /**
     * 设置PrintStatus
     * @return mixed 返回操作结果
     */
    public function setPrintStatus() {
        set_time_limit(0);
        $type   = $_POST['type'];
        $str_id = $_POST['str'];
        $dlyObj = $this->app->model('delivery');
        $deliveryIds = array_map('intval', array_filter(explode(',', $str_id)));
        $dlys = $dlyObj->getList('*', array('delivery_id' => $deliveryIds), 0, -1);
        $deliveryOrderId = array();
        $deliveryOrderBn = array();
        $dOrder = $this->app->model('delivery_order')->getList('*', array('delivery_id'=>$deliveryIds));
        foreach($dOrder as $val) {
            $deliveryOrderId[$val['delivery_id']][] = $val['order_id'];
        }
        //批量打印只能是同一物流公司
        $corp = app::get('ome')->model('dly_corp')->dump(array('corp_id' => $dlys[0]['logi_id']), 'type');
        if ($corp['type'] == 'DANGDANG') {//如果是当当物流订单，将订单号更新为物流单号
            $sql = 'select order_bn,delivery_id from sdb_brush_delivery_order left join sdb_ome_order using(order_id) where delivery_id in (' . implode(',', $deliveryIds) . ')';
            $orderBn = kernel::database()->select($sql);
            foreach($orderBn as $val) {
                $deliveryOrderBn[$val['delivery_id']] = $val['order_bn'];
            }
        }
        $arr_s = array('cancel', 'back', 'stop','return_back');
        $failDLY = array();
        $db = kernel::database();
        $this->begin();
        foreach ($dlys as $k => $delivery) {
            if (in_array($delivery['status'], $arr_s)) continue;
            $db->exec('SAVEPOINT updatePrintStatus');
            $ret = $this->_dealPrintStatus($delivery, $deliveryOrderId, $deliveryOrderBn);
            if(!$ret) {
                $db->exec('ROLLBACK TO SAVEPOINT updatePrintStatus');
                $failDLY[] = $delivery['delivery_bn'];
            }
        }
        $this->endonly();
        $msg = empty($failDLY) ? 'true' : implode(',', $failDLY) . '打印数据保存失败';
        echo $msg;
    }

    private function _dealPrintStatus($delivery, $deliveryOrderId, $deliveryOrderBn) {
        $dlyData = array();
        if ($delivery['status'] == 'ready') {
            $dlyData['status'] = 'progress';
        }
        $dlyData['expre_status'] = 'true';
        if($deliveryOrderBn[$delivery['delivery_id']]) {
            $dlyData['logi_no'] = $deliveryOrderBn[$delivery['delivery_id']];
        }
        $ret = $this->app->model('delivery')->update($dlyData, array('delivery_id'=>$delivery['delivery_id']));
        if(!$ret) {
            return false;
        }
        $orderUpData = array(
            'print_finish' => 'true',
            'print_status' => 1,
        );
        $ret = app::get('ome')->model('orders')->update($orderUpData, array('order_id'=>$deliveryOrderId[$delivery['delivery_id']]));
        if(!$ret) {
            return false;
        }
        $log_msg       = '快递单打印';
        $opObj = app::get('ome')->model('operation_log');
        $ret = $opObj->write_log('delivery_brush_expre@brush', $delivery['delivery_id'], $log_msg);
        if(!$ret) {
            return false;
        }
        return true;
    }

    /**
     * 设置PrintMode
     * @return mixed 返回操作结果
     */
    public function setPrintMode() {

    }
}