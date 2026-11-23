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

class wms_event_trigger_logistics_electron {
    private $logIdentifier = '';

    

    //缓存池方式获取电子面单
    /**
     * bufferGetWaybill
     * @param mixed $channelId ID
     * @return mixed 返回值
     */
    public function bufferGetWaybill($channelId) {
        return kernel::single('erpapi_router_request')->set('logistics', $channelId)->electron_bufferRequest(null);
    }

    //获取缓存池里的电子面单 $allDlyOrBill 为发货单主表或补打单主表信息
    /**
     * 获取BufferWaybill
     * @param mixed $allDlyOrBill allDlyOrBill
     * @param mixed $channelId ID
     * @param mixed $bill bill
     * @return mixed 返回结果
     */
    public function getBufferWaybill($allDlyOrBill, $channelId, $bill = false) {
        $arrDlyOrBill = array();
        foreach($allDlyOrBill as $dlyOrBill) {
            if (!$this->isExistLogino($dlyOrBill['logi_no'], $channelId)) {
                $arrDlyOrBill[] = $dlyOrBill;
            }
        }
        $num = count($arrDlyOrBill);
        $wFilter = array(
            'channel_id' => $channelId,
            'status' => 0
        );
        $objWaybill = app::get('logisticsmanager')->model('waybill');
        $iWhile = 3;
        do {
            $arrWaybill = $objWaybill->getList('id, waybill_number', $wFilter, 0, $num, 'id asc');
            if(count($arrWaybill) < $num) {
                $this->bufferGetWaybill($channelId);
                sleep(1);
                $iWhile--;
            } else {
                $iWhile = 0;
            }
        } while ($iWhile);
        $wbKey = 0;
        $notGetWaybill = array();
        $getWaybill = array();
        $gwbKey = $bill ? 'b_id' : 'delivery_id';
        $db = kernel::database();
        foreach($arrDlyOrBill as $dlyOrBill) {
            if($arrWaybill[$wbKey]) {
                $db->beginTransaction();
                $ret = $this->_dealBufferWaybill($arrWaybill[$wbKey], $dlyOrBill, $bill);
                if(!$ret) {
                    $notGetWaybill[$dlyOrBill[$gwbKey]] = $arrWaybill[$wbKey]['waybill_number'];
                    $db->rollBack();
                } else {
                    $getWaybill[$dlyOrBill[$gwbKey]] = $arrWaybill[$wbKey]['waybill_number'];
                    $db->commit();
                }
                $wbKey++;
            } else {
                $notGetWaybill[$dlyOrBill[$gwbKey]] = false;
            }
        }
        return array($getWaybill, $notGetWaybill);
    }

    private function _dealBufferWaybill($waybill, $dlyOrBill, $bill) {
        $dlyBillObj = app::get('wms')->model('delivery_bill');
        $ret = app::get('logisticsmanager')->model('waybill')->update(array('status'=>1), array('id'=>$waybill['id']));
        if($ret !== 1) {
            return false;
        }
        if($bill) {
            //$ret = app::get('ome')->model('delivery_bill')->update(array('logi_no' => $waybill['waybill_number']), array('log_id' => $dlyOrBill['log_id']));
            $ret = $dlyBillObj->update(array('logi_no'=>$waybill['waybill_number']), array('b_id'=>$dlyOrBill['b_id'],'delivery_id'=>$dlyOrBill['delivery_id'],'type'=>2));
        } else {
            
            
            $dlyBillObj->db->exec("update sdb_wms_delivery_bill set logi_no='".$waybill['waybill_number']."' where delivery_id =".$dlyOrBill['delivery_id']." and type = 1 and (logi_no is null or logi_no ='')");
            $ret = $dlyBillObj->db->affect_row();
            if($ret > 0){
                //电子面单获取后顺便请求ome模块更新物流单号
                $wms_id = kernel::single('ome_branch')->getWmsIdById($dlyOrBill['branch_id']);
                $tmp_data = array(
                'delivery_bn' => $dlyOrBill['outer_delivery_bn'],
                'logi_no' => $waybill['waybill_number'],
                'action' => 'addLogiNo',
                );
                $res = kernel::single('wms_event_trigger_delivery')->doUpdate($wms_id, $tmp_data, true);
            }
           
        }
        if($ret !== 1) {
            return false;
        }
        $opLogObj = app::get('ome')->model('operation_log');
        $logMsg = "成功:". $waybill['waybill_number'];
        $logMsg = $this->_getWriteLogMsg($logMsg, $dlyOrBill['b_id']);
        $ret = $opLogObj->write_log('delivery_expre@wms', $dlyOrBill['delivery_id'], $logMsg);
        if(!$ret) {
            return false;
        }
        return true;
    }

    /**
     * isExistLogino
     * @param mixed $logiNo logiNo
     * @param mixed $channelId ID
     * @return mixed 返回值
     */
    public function isExistLogino($logiNo, $channelId) {
        if($logiNo) {
            $filter = array('channel_id' => $channelId, 'waybill_number' => $logiNo);
            $count = app::get('logisticsmanager')->model('waybill')->count($filter);
            return $count > 0 ? true : false;
        }
        return false;
    }

    //直连请求获取电子面单
    /**
     * directGetWaybill
     * @param mixed $deliveryId ID
     * @param mixed $channelId ID
     * @param mixed $billId ID
     * @return mixed 返回值
     */
    public function directGetWaybill($deliveryId, $channelId, $billId = '') {
        $arrDeliveryId = explode(';', $deliveryId);
        if($billId) {
            $arrBillId = explode(';', $billId);
            if(count($arrDeliveryId) > 1) {
                return array('dealResult'=>1, 'doFail' => count($arrBillId), 'doSucc' => 0);
            }
        }
        $directRet = array('succ'=>[], 'fail'=>[]);
        $channel = app::get('logisticsmanager')->model('channel')->dump(array('channel_id'=>$channelId));
        $param = kernel::single('wms_event_trigger_logistics_data_electron_router')
            ->setChannel($channel)
            ->directDealParam($arrDeliveryId, $arrBillId);

        if($param['succ']) {
            foreach($param['succ'] as $succ) {
                $directRet['succ'][] = $succ;
            }
        }
        
        if($param['fail']) {
            foreach($param['fail'] as $succ) {
                // check msg
                if(isset($succ['msg']) && $succ['msg']){
                    $succ['msg'] = $this->appendShopReauthHint($succ['msg']);
                }
                
                $directRet['fail'][] = $succ;
            }
        }
        
        if($param['sdf']) {
            $back = kernel::single('erpapi_router_request')->set('logistics', $channel['channel_id'])->electron_directRequest($param['sdf']);
            $this->logIdentifier = $param['sdf']['primary_bn'];
            $backRet = $this->directCallback($back, $param['need_request_id'], $channel);
            if ($backRet['succ']) {
                foreach ($backRet['succ'] as $val) {
                    $directRet['succ'][] = $val;
                }
            }
            if ($backRet['fail']) {
                foreach ($backRet['fail'] as $val) {
                    // check msg
                    if(isset($val['msg']) && $val['msg']){
                        $val['msg'] = $this->appendShopReauthHint($val['msg']);
                    }
                    
                    $directRet['fail'][] = $val;
                }
            }
        }
        
        $directRet['doFail'] = count($directRet['fail']);
        $directRet['doSucc'] = count($directRet['succ']);
        $directRet['dealResult'] = 1;
        return $directRet;
    }

    /**
     * directCallback
     * @param mixed $result result
     * @param mixed $needRequestId ID
     * @param mixed $channel channel
     * @return mixed 返回值
     */
    public function directCallback($result, $needRequestId, $channel) {
        $waybillCodeArr = array();
        if($result && is_array($result)) {
            $db = kernel::database();
            foreach ($result as $val) {
                $dlyBn = $billId = '';
                kernel::single('wms_event_trigger_logistics_data_electron_common')->checkChildRqOrdNo($val['delivery_bn'], $dlyBn, $billId);
                $retData = array();
                $retData['delivery_id'] = $val['delivery_id'];
                if($billId) {
                    $retData['b_id'] = $billId;
                    $val['b_id'] = $billId;
                    $retData['delivery_bn'] = $dlyBn;
                } else {
                    $retData['delivery_bn'] = $val['delivery_bn'];
                }
                if($val['succ']) {
                    $db->beginTransaction();
                    $retData['logi_no'] = $val['logi_no'];
                    $ret = $this->_dealDirectResult($val, $channel);

                    if ($ret) {
                        $db->commit();
                        $waybillCodeArr['succ'][] = $retData;
                    } else {
                        $db->rollBack();
                        $retData['msg'] = '保存失败：运单号`'.$val['logi_no'].'`可能已被占用';
                        $waybillCodeArr['fail'][] = $retData;
                    }
                } elseif($val['succ'] === false) {
//                    $logMsg = $this->_getWriteLogMsg("失败:" . $val['msg'], $billId);
//                    app::get('ome')->model('operation_log')->write_log('delivery_expre@wms', $val['delivery_id'], $logMsg);
                    $retData['msg'] = $val['msg'];
                    $waybillCodeArr['fail'][] = $retData;
                }
            }
        } elseif(!empty($needRequestId)) {
            $msg = $result ? $result : '请求没有返回结果';
            foreach ($needRequestId as $val) {
                $waybillCodeArr['fail'][] = array(
                    'request_id' => $val,
                    'msg' => $msg."(请求日志标识：" . $this->logIdentifier .")"
                );
            }
        }
        //记录失败请求运单号日志
        if (isset($waybillCodeArr['fail'])) {
            $filter['delivery_id'] = array_merge(array_column($waybillCodeArr['fail'],'request_id'),array_column($waybillCodeArr['fail'],'delivery_id'));
            $logMsg = current($waybillCodeArr['fail'])['msg'];
            app::get('ome')->model('operation_log')->batch_write_log('delivery_expre@wms', $filter, $logMsg,time());
        }
        return $waybillCodeArr;
    }

    private function _dealDirectResult($params, $channel) {
        $params['logi_no'] = trim($params['logi_no']);
        
        $billObj = app::get('wms')->model('delivery_bill');
        $deliveryObj = app::get('wms')->model('delivery');
        if($params['b_id']) {
            $ret = $billObj->update(array('logi_no' => $params['logi_no']), array('delivery_id' => $params['delivery_id'],'b_id'=>$params['b_id']));
        } else {
            $ret = $billObj->update(array('logi_no' => $params['logi_no']), array('delivery_id' => $params['delivery_id'],'type'=>'1'));
            if($ret){
                //信息变更更新到oms
                $delivery = $deliveryObj->dump(array('delivery_id'=>$params['delivery_id']),'branch_id,outer_delivery_bn');
                $wms_id = kernel::single('ome_branch')->getWmsIdById($delivery['branch_id']);
                $tmp_data = array(
                    'delivery_bn' =>$delivery['outer_delivery_bn'],
                    'logi_no' => $params['logi_no'],
                    'action' => 'addLogiNo',
                );
                kernel::single('wms_event_trigger_delivery')->doUpdate($wms_id, $tmp_data, true);
            
            }
        }
        if (!$ret) {
            return false;
        }

        $logMsg = $this->_getWriteLogMsg("成功：". $params['logi_no'], $params['b_id']);
        $ret = app::get('ome')->model('operation_log')->write_log('delivery_expre@wms', $params['delivery_id'], $logMsg);
        if (!$ret) {
            return false;
        }
         
        return true;
    }

    private function _getWriteLogMsg($logMsg, $billId) {
        $curr_op_name = kernel::single('desktop_user')->get_name();
        $curr_op_name = $curr_op_name ? $curr_op_name : kernel::single('desktop_user')->get_login_name();
        $bnFlag = $this->logIdentifier ? "(请求日志标识：" . $this->logIdentifier .")" : "";
        $child = $billId ? '子包裹' . $billId : '';
        $logMsg = $curr_op_name."分派," . $child . "获取运单号" . $logMsg . $bnFlag;
        return $logMsg;
    }

    /**
     * SessionKey 相关错误时追加重新授权提示
     */
    private function appendShopReauthHint($msg) {
        $keywords = array('非法的SessionKey', '过期的SessionKey', '请使用有效的SessionKey');
        
        foreach ($keywords as $keyword) {
            if (strpos($msg, $keyword) !== false) {
                return $msg . '：（请重新授权店铺）';
            }
        }
        
        return $msg;
    }

    //获取大头笔
    public function getWaybillExtend($arrDelivery, $channelId, $arrBill = array()) {
        if(count($arrDelivery) > 1 && count($arrBill) > 1) {
            return false;
        }
        $channel = app::get('logisticsmanager')->model('channel')->dump(array('channel_id'=>$channelId));
        $param = kernel::single('wms_event_trigger_logistics_data_electron_router')
            ->setChannel($channel)
            ->waybillExtendDealParam($arrDelivery, $arrBill);
        $notGetWaybillExtend = array();

        if($param['sdf']) {
            foreach($param['sdf'] as $val) {
                $ret = kernel::single('erpapi_router_request')->set('logistics', $channel['channel_id'])->electron_waybillExtend($val);
                
                if($ret['rsp'] == 'fail') {
                    $notGetWaybillExtend[$val['delivery']['delivery_id']] =$val['delivery']['delivery_bn'];
                }
            }
            if(!empty($arrBill) && empty($notGetWaybillExtend)) {
                $param = kernel::single('wms_event_trigger_logistics_data_electron_router')
                    ->setChannel($channel)
                    ->waybillExtendDealParam($arrDelivery);
            }
        }
        if($param['bill_extend_fail']) {
           
            $delivery = $arrDelivery[0];
            $notGetWaybillExtend = array($delivery['delivery_id'] => $delivery['delivery_bn']);
        }
        return $notGetWaybillExtend;
    }
}