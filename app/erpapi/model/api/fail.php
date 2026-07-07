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
class erpapi_mdl_api_fail extends dbeav_model{

    public $objTypeProperty = array(
        'search_inpurchase'        => array(
            'text' => '查询采购单状态',
            'method' => 'console_event_trigger_purchase:searchPurchase',
            'filter' => array(
                'app'           => 'purchase',
                'model'         => 'po',
                'obj_bn'        => 'po_bn',
                'check_status'  => '2',
                'po_status'     => '1'
            )
        ),
        'search_delivery'        => array(
            'text' => '查询发货单状态',
            'method' => 'ome_delivery_notice:searchDelivery',
            'filter' => array(
                'app' => 'ome',
                'model' => 'delivery',
                'obj_bn' => 'delivery_bn',
                'status' => array('ready','progress'),
                'parent_id' => '0',
            )
        ),
        'search_outpurchase_return' => array(
            'text' => '查询采购退货单',
            'method' => 'console_event_trigger_purchasereturn:searchPurchaseReturn',
            'filter' => array(
                'app'               => 'purchase',
                'model'             => 'returned_purchase',
                'obj_bn'            => 'rp_bn',
                'check_status'      => '2',
                'return_status'     => array('1','4')
            )
        ),
        'search_reship'          => array(
            'text' => '退货单查询',
            'method' => 'wms_trigger_reship:reshipSearch',
            'filter' => array(
                'app' => 'ome',
                'model' => 'reship',
                'obj_bn' => 'reship_bn',
                'is_check' => array('1', '11')
            )
        ),
        'search_iso'          => array(
            'text'          => '查询直接出入库单',
            'method'        => 'console_event_trigger_iso:search',
            'filter' => array(
                'app'       => 'taoguaniostockorder',
                'model'     => 'iso',
                'obj_bn'    => 'iso_bn',
                'status'    => 'Y',
                'confirm'   => 'N'
            )
        ),
        'deliveryBack'    => array(
            'text' => '物流信息回传平台',
            'method' => 'ome_event_trigger_shop_delivery:delivery_confirm_send_fromsub',
            'filter' => array(
                'app' => 'ome',
                'model' => 'delivery',
                'obj_bn' => 'delivery_bn',
                'status' => array('ready','progress','succ'),
//                'parent_id' => '0',
            )
        ),
        'bookingrefund_back' => array(
            'text' => '订单退款回传平台',
            'method' => '',
            'filter' => array(),
        ),
        'upload_invoice' => array(
            'text'   => '电子发票上传',
            'method' => 'invoice_event_trigger_einvoice:upload',
            'filter' => array(
                'app'        => 'invoice',
                'model'      => 'order_electronic_items',
                'obj_bn'     => 'invoice_no',
            ),
        ),
    );

    public function getObjTypeText() {
        $arrText = array();
        foreach($this->objTypeProperty as $k => $val) {
            $arrText[$k] = $val['text'];
        }
        return $arrText;
    }

    public function modifier_obj_type($col)
    {
        return $this->objTypeProperty[$col] ? $this->objTypeProperty[$col]['text'] : $col;
    }

    public function modifier_err_msg($col){
        $col = strip_tags($col);
        return sprintf("<span alt='%s' title='%s'>%s</span>",$col, $col, $col);
    }

    /**
     * 发布失败日志
     *
     * 已弃用 Thu May  5 11:04:32 2022 chenping@shopex.cn
     *
     * @return void
     * @author 
     **/
    public function publish_api_fail($method, $callback_params, $result)
    {
        
        $obj_bn   = $callback_params['obj_bn'];
        $obj_type = $callback_params['obj_type'];
        $err_msg  = $result['err_msg'];
        $res      = $result['res'];
        $status   = $result['rsp'];

        if (!$obj_bn || !$obj_type || !$method) return true;

        $fail = $this->dump(array('obj_bn'=>$obj_bn,'obj_type'=>$obj_type));
        if ($status == 'fail') {
            $fail_params = array(
                'obj_bn'     => $obj_bn,
                'obj_type'   => $obj_type,
                'method'     => $method,
                'err_msg'    => $err_msg.'('.$res.')'.$result['msg'],
                'fail_times' => ((int)$fail['fail_times'] + 1),
                'status'     => 'fail',
                'last_modify'=> time(),
            
                'msg_id'     => $result['msg_id'],
                'params'     => $callback_params['params'],
            );
            if ($fail) $fail_params['id'] = $fail['id'];
            if(!$fail) $fail_params['create_time'] = time();
            $this->save($fail_params);
            $apifail_mq = app::get('ome')->getConf('ome.saas.apifail_mq');
            $retryapiErrcode = $this->getretryapiErrcode();
            // 判断是不是超时
            if ((in_array($res,$retryapiErrcode) || in_array($result['msg_code'],$retryapiErrcode)) && $fail_params['fail_times']<3 && $apifail_mq!='true') {

                $retrytime = app::get('ome')->getConf('ome.apifail.retry');
                $retrytime = $retrytime ? $retrytime * 60 : 600;
                $push_params = array(
                    'data' => array(
                        'log_id'     => $fail_params['id'],
                        'task_type'  => 'autoretryapi',
                        'exectime'   => (time() + $retrytime),
                        'obj_bn'     => $obj_bn,
                        'obj_type'   => $obj_type,
                        'method'     => $method,
                        'id'         => $fail_params['id'],
                    ),
                    'url' => kernel::openapi_url('openapi.autotask','service')
                );

                $flag = kernel::single('taskmgr_interface_connecter')->push($push_params);

                // if ($flag) {
                //     $this->update(array('status'=>'running'),array('id'=>$fail_params['id']));
                // }
            }
        } elseif ($status=='succ' && $fail) {
            $this->delete(array('id'=>$fail['id']));
        }

        return true;
    }

    public function getretryapiErrcode(){

        $errcode = kernel::single('erpapi_errcode')->errcode;

        $retryCode = array();
        foreach($errcode as $key=>$val){

            foreach($val as $vk=>$vv){
                if ( $vv['retry'] == '1' ){
                    $retryCode[] = $vk;
                }
            }

        }
        return $retryCode;
    }

    //异步请求重试 重新组装数据
    public function saveTriggerRequest($objBn, $objType, $api_method='', $errMsg='', $sub_obj_bn = '') {
        $sdf = array(
            'obj_bn' => $objBn,
            'obj_type' => $objType,
            'method'     => $api_method,
            'retry_params' => 'trigger_request',
            'status' => 'running',
            'last_modify' => time(),
            'sub_obj_bn'  => $sub_obj_bn,
        );
        if($errMsg) {
            $sdf['status'] = 'fail';
            $sdf['err_msg'] = $errMsg;
        }
        $data = $this->getList('id', array('obj_bn'=>$objBn, 'obj_type'=>$objType, 'sub_obj_bn' => $sub_obj_bn), 0, 1);
        if($data) {
            if($this->update($sdf, array('id'=>$data[0]['id']))) {
                return $data[0]['id'];
            }
        } else {
            $sdf['create_time'] = time();
            if($this->insert($sdf)) {
                return $sdf['id'];
            }
        }
        return false;
    }

    /**
     * 异步请求重试 不重新组装数据
     * @param  [type] $params [description]
     * @return [type]         [description]
     */
    public function saveRunning($params) {
        $sdf = array(
            'method' => $params['method'],
            'obj_bn' => $params['obj_bn'],
            'obj_type' => $params['obj_type'],
            'retry_params' => serialize(array(
                'channel' => $params['channel'],
                'channel_id' => $params['channel_id'],
                'method' => $params['method'],
                'args' => $params['args'],
                'next_obj_type' => $params['next_obj_type']
            )),
            'status' => 'running',
            'last_modify' => time(),
        );
        if($params['total_page']) {
            $page = array();
            for($i = 0; $i < $params['total_page']; $i++) {
                $page[$i+1] = '';
            }
            $sdf['page_content'] = serialize(array(
                'page'=>$page,
                'err_msg'=>''
            ));
        }
        $data = $this->getList('id', array('obj_bn'=>$params['obj_bn'], 'obj_type'=>$params['obj_type']), 0, 1);
        if($data) {
            if($this->update($sdf, array('id'=>$data[0]['id']))) {
                return $data[0]['id'];
            }
        } else {
            $sdf['create_time'] = time();
            if($this->insert($sdf)) {
                return $sdf['id'];
            }
        }
        return false;
    }

    # 同步请求重试 不重新组装数据
    public function saveSyncRequest($params) {
        if($params['rsp'] == 'succ') {
            $this->delete(array('obj_bn'=>$params['obj_bn'], 'obj_type'=>$params['obj_type']));
            return true;
        }
        $sdf = array(
            'obj_bn' => $params['obj_bn'],
            'obj_type' => $params['obj_type'],
            'retry_params' => serialize(array(
                'channel' => $params['channel'],
                'channel_id' => $params['channel_id'],
                'method' => $params['method'],
                'args' => $params['args'],
            )),
            'err_msg' => $params['err_msg'],
            'msg_id' => $params['msg_id'],
            'status' => 'fail',
            'last_modify' => time(),
        );
        $data = $this->getList('id,fail_times',
            array('obj_bn'=>$params['obj_bn'], 'obj_type'=>$params['obj_type']), 0, 1);
        if($data) {
            $sdf['fail_times'] = (int) $data[0]['fail_times'] + 1;
            if($this->update($sdf, array('id'=>$data[0]['id']))) {
                return $data[0]['id'];
            }
        } else {
            $sdf['create_time'] = time();
            if($this->insert($sdf)) {
                return $sdf['id'];
            }
        }
        return false;
    }

    public function dealCallback($status,$apiFailId, $msg, $msg_id) {
        if(strpos($apiFailId, '-')) {
            list($id, $page) = explode('-', $apiFailId);
            $data = $this->db_dump(array('id'=>$id), '*');
            $pageContent = unserialize($data['page_content']);
            $is_finish = true;
            $is_success = true;
            foreach($pageContent['page'] as $k => &$val) {
                if($k == $page) {
                    $val = $status;
                }
                if(empty($val)) {
                    $is_finish = false;
                } elseif($val != 'success') {
                    $is_success = false;
                }
            }
            if($status != 'success' && strpos($pageContent['err_msg'], $msg) == false) {
                $pageContent['err_msg'] .= $msg;
            }
            $this->update(array('page_content'=>serialize($pageContent)), array('id'=>$id));
            if($is_finish) {
                $status = $is_success ? 'success' : 'fail';
                $msg = $pageContent['err_msg'];
            } else {
                return null;
            }
        } else {
            $id = $apiFailId;
            $data = $this->db_dump(array('id'=>$id), '*');
        }
        if($status == 'success') {
            $this->delete(array('id'=>$id));

            $retryParams = @unserialize($data['retry_params']);
            if($retryParams['next_obj_type'] && $this->objTypeProperty[$retryParams['next_obj_type']]['method']) {
                $sdf = array(
                    'obj_bn'       => $data['obj_bn'],
                    'obj_type'     => $retryParams['next_obj_type'],
                    'retry_params' => 'trigger_request',
                    'status'       => 'running',
                    'last_modify'  => time(),
                );

                $this->db_save($sdf);
            }
        } else {
            $this->updateFail($id, $msg, $msg_id);
        }
    }

    public function updateFail($id, $errMsg, $msgId) {
        $data = $this->getList('fail_times', array('id'=>$id), 0, 1);
        if(empty($data)) {
            return false;
        }
        $upData = array(
            'err_msg' => $errMsg,
            'msg_id' => $msgId,
            'fail_times' => (int) $data[0]['fail_times'] + 1,
            'status' => 'fail',
            'last_modify' => time(),
        );
        return $this->update($upData, array('id'=>$id));
    }

    # 失败重试
    public function retry($log)
    {
        $property = $this->objTypeProperty[$log['obj_type']];

        if($property['filter'] 
            && $property['filter']['obj_bn'] 
            && $property['filter']['app'] 
            && $property['filter']['model']) 
        {
            $filter = $property['filter'];

            $objBn          = $filter['obj_bn'];
            $appName        = $filter['app'];
            $modelName      = $filter['model'];
            unset($filter['obj_bn'], $filter['app'], $filter['model']);

            $filter[$objBn] = $log['obj_bn'];
            $modelObject    = app::get($appName)->model($modelName);
            $primaryKey     = $modelObject->schema['idColumn'];
            $row            = $modelObject->dump($filter, $primaryKey);

            if(empty($row)) {
                $this->delete(array('id'=>$log['id']));
                return array('rsp'=>'fail');
            }

            if($log['retry_params'] == 'trigger_request') {
                if($property['method']) {
                    list($objectName, $method) = explode(':', $property['method']);
                    return kernel::single($objectName)->$method($row[$primaryKey]);
                } else {
                    $this->delete(array('id'=>$log['id']));
                    return array('rsp'=>'fail');
                }
            }
        }
        // 验证单据状态
        switch ($log['obj_type']) {
            case 'deliveryBack':
                $delivery = app::get('ome')->model('delivery')->dump(array('delivery_bn'=>$log['obj_bn']),'delivery_id,logi_no');
                $shipmentLogModel = app::get('ome')->model('shipment_log');
                if($shipmentLogModel->db_dump(array('deliveryCode'=>$delivery['logi_no'], 'status'=>'succ'), 'log_id')) {
                    $this->delete(array('id'=>$log['id']));
                    return array('rsp'=>'fail');
                }
                    
                break;
            case 'logistics_back':
                if (false !== strpos($log['err_msg'], '运单号已使用')) {
                    $this->delete(array('id'=>$log['id']));
                    return array('rsp'=>'fail');
                }
                break;
            case 'upload_invoice':
                if (false !== strpos($log['err_msg'], '订单无开票记录')) {
                    $this->delete(array('id'=>$log['id']));
                    return array('rsp'=>'fail');
                }
                break;
        }

        $retryParams = @unserialize($log['retry_params']);

        if(empty($retryParams['method']) || empty($retryParams['channel']) || empty($retryParams['channel_id'])) {
            $upData = array(
                'err_msg' => '参数不全，无法重试',
                'fail_times' => 100,
                'status' => 'fail',
                'last_modify' => time(),
            );
            $this->update($upData, array('id'=>$log['id']));
            return array('rsp'=>'fail');
        }

        $obj = kernel::single('erpapi_router_request')->set($retryParams['channel'], $retryParams['channel_id']);

        return call_user_func_array(array($obj, $retryParams['method']), $retryParams['args']);
    }
}
