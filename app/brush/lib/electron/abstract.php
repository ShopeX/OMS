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
 * @author ykm 2015-12-16
 * @describe 电子面单请求前响应后数据处理
 */
abstract class brush_electron_abstract {
    protected $preBn;
    protected $requestTime;
    protected $directRet;
    public $channel;
    public $delivery;
    public $needRequestId;
    public $deliveryBnKey;

    final public function init($params){
        $this->requestTime = time();
        $this->channel = $params['channel'];
        $this->delivery = array();
        $this->deliveryBnKey = array();
        foreach($params['delivery'] as $dly) {
            $this->delivery[] = $dly;
            $this->deliveryBnKey[$dly['delivery_bn']] = $dly;
        }
    }

    final protected function request($method, $sdf){
        $method = 'electron_' . $method;
        $ret = kernel::single('erpapi_router_request')->set('logistics', $this->channel['channel_id'])->$method($sdf);
        return $ret;
    }

    public function isExistLogino($delivery) {
        if($delivery['logi_no']) {
            $filter = array('channel_id' => $this->channel['channel_id'], 'waybill_number' => $delivery['logi_no']);
            $count = app::get('logisticsmanager')->model('waybill')->count($filter);
            return $count > 0 ? true : false;
        }
        return false;
    }

    private function _dealBufferWaybill($waybill, $delivery) {
        $ret = app::get('logisticsmanager')->model('waybill')->update(array('status'=>1), array('id'=>$waybill['id']));
        if($ret !== 1) {
            return false;
        }
        $logMsg = $this->_getWriteLogMsg("成功：". $waybill['waybill_number']);
        $extendFilter = array(
            'logi_id' => $delivery['logi_id'],
            'filter_sql' => ' (logi_no is null or logi_no = "") '
        );
        $ret = kernel::single('brush_logistics')->changeLogistics(array('logi_no' => $waybill['waybill_number']), $delivery['delivery_id'], $logMsg, $extendFilter);
        if(!$ret) {
            return false;
        }
        return true;
    }

    //获取缓存池里的电子面单
    public function getBufferWaybill() {
        $arrDelivery = array();
        foreach($this->delivery as $delivery) {
            if (!$this->isExistLogino($delivery)) {
                $arrDelivery[] = $delivery;
            }
        }
        $num = count($arrDelivery);
        $wFilter = array(
            'channel_id' => $this->channel['channel_id'],
            'status' => 0
        );
        $objWaybill = app::get('logisticsmanager')->model('waybill');
        $iWhile = 3;
        do {
            $arrWaybill = $objWaybill->getList('id, waybill_number', $wFilter, 0, $num, 'id asc');
            if(count($arrWaybill) < $num) {
                $this->bufferGetWaybill();
                sleep(1);
                $iWhile--;
            } else {
                $iWhile = 0;
            }
        } while ($iWhile);
        $wbKey = 0;
        $notGetWaybill = array();
        $getWaybill = array();
        $db = kernel::database();
        $db->beginTransaction();
        foreach($arrDelivery as $delivery) {
            if($arrWaybill[$wbKey]) {
                $db->exec('SAVEPOINT upBufferWaybill');
                $ret = $this->_dealBufferWaybill($arrWaybill[$wbKey], $delivery);
                if(!$ret) {
                    $notGetWaybill[$delivery['delivery_id']] = $delivery['delivery_bn'];
                    $db->exec('ROLLBACK TO SAVEPOINT upBufferWaybill');
                } else {
                    $delivery['logi_no'] = $arrWaybill[$wbKey]['waybill_number'];
                    $getWaybill[$delivery['delivery_id']] = $delivery;
                }
                $wbKey++;
            } else {
                $notGetWaybill[$delivery['delivery_id']] = $delivery['delivery_bn'];
            }
        }
        $db->commit();
        #应对rds对事务处理漏掉sql的情况, 如rds修复可还原到上一版本
        $gwDlyIds = array_keys($getWaybill);
        $logiNOData = app::get('brush')->model('delivery')->getList('delivery_id,logi_no', array('delivery_id'=>$gwDlyIds));
        foreach($logiNOData as $lndVal) {
            if(empty($lndVal['logi_no'])) {
                $notGetWaybill[$lndVal['delivery_id']] = $getWaybill[$lndVal['delivery_id']]['delivery_bn'];
                unset($getWaybill[$lndVal['delivery_id']]);
            }
        }
        return array($getWaybill, $notGetWaybill);
    }

    //缓存池方式获取电子面单
    public function bufferGetWaybill() {
        return $this->request('bufferRequest', null);
    }

    public function deliveryToSdf($delivery) {//各自实现
        $sdf = array();

        $deliveryIds = array();
        foreach($delivery as $dly) {
            $deliveryIds[] = $dly['delivery_id'];
        }

        // 御城河
        $order_bns = array();
        if ($deliveryIds) {
            $orders = $this->getDeliveryOrder($deliveryIds);
            foreach ($orders as $order) {
                foreach($order['order_info'] as $val) {
                    // if ($val['shop_type'] == 'taobao' && $val['createway'] == 'matrix') 
                    $order_bns[] = $val['order_bn'];
                }
            }
        }

        $sdf['order_bns'] = $order_bns;

        return $sdf;
    }

    private function _getWriteLogMsg($logMsg) {
        $bnFlag = $this->preBn ? "(请求日志标识：" . $this->preBn . $this->requestTime .")" : "";
        $logMsg = "获取电子面单运单号" . $logMsg . $bnFlag;
        return $logMsg;
    }

    private function _dealDirectResult($params) {
        $params['logi_no'] = trim($params['logi_no']);
        $objWaybill = app::get('logisticsmanager')->model('waybill');
        $arrWaybill = $objWaybill->dump(array('channel_id' => $this->channel['channel_id'], 'waybill_number' => $params['logi_no']),'id,status');
        if (!$arrWaybill) {
            $arrWaybill = array(
                'waybill_number' => $params['logi_no'],
                'channel_id' => $this->channel['channel_id'],
                'logistics_code' => $this->channel['logistics_code'],
                'status' => 1,
                'create_time' => time(),
            );
            $ret = $objWaybill->insert($arrWaybill);
            if (!$ret) {
                return false;
            }
        } elseif ($arrWaybill['status'] == '2') {
            $objWaybill->update(array('status'=>'1'),array('id'=>$arrWaybill['id']));
        } elseif ($arrWaybill['status'] == '1') {
            return false;
        }
        $logMsg = $this->_getWriteLogMsg("成功：". $params['logi_no']);
        $ret = kernel::single('brush_logistics')->changeLogistics(array('logi_no' => $params['logi_no']), $params['delivery_id'], $logMsg);
        if (!$ret) {
            return false;
        }
        if(!$params['noWayBillExtend']) {
            $waybillExtend = array(
                'waybill_id' => $arrWaybill['id'],
                'mailno_barcode' => $params['mailno_barcode'],
                'qrcode' => $params['qrcode'],
                'position' => $params['position'],
                'position_no' => $params['position_no'],
                'package_wdjc' => $params['package_wdjc'],
                'package_wd' => $params['package_wd'],
                'print_config' => $params['print_config'],
                'json_packet' => $params['json_packet'],
            );
            $waybillExtendModel = app::get('logisticsmanager')->model('waybill_extend');
            $filter = array('waybill_id' => $waybillExtend['waybill_id']);
            if (!$waybillExtendModel->dump($filter)) {
                $ret = $waybillExtendModel->insert($waybillExtend);
            } else {
                $ret = $waybillExtendModel->update($waybillExtend, $filter);
            }
            if (!$ret) {
                return false;
            }
        }
        return true;
    }

    public function directCallback($result) {
        $waybillCodeArr = array();
        if($result && is_array($result)) {
            $db = kernel::database();
            $db->beginTransaction();
            foreach ($result as $val) {
                $retData = array();
                $retData['delivery_id'] = $val['delivery_id'];
                $retData['delivery_bn'] = $val['delivery_bn'];
                if($val['succ']) {
                    $db->exec('SAVEPOINT brush_waybill');
                    $retData['logi_no'] = $val['logi_no'];
                    $ret = $this->_dealDirectResult($val);
                    if ($ret) {
                        $waybillCodeArr['succ'][] = $retData;
                    } else {
                        $db->exec('ROLLBACK TO SAVEPOINT brush_waybill');
                        $retData['msg'] = '保存失败';
                        $waybillCodeArr['fail'][] = $retData;
                    }
                } elseif($val['succ'] === false) {
                    $logMsg = $this->_getWriteLogMsg("失败:" . $val['msg']);
                    app::get('ome')->model('operation_log')->write_log('delivery_brush_getwaybill@brush', $val['delivery_id'], $logMsg);
                    $retData['msg'] = $val['msg'];
                    $waybillCodeArr['fail'][] = $retData;
                }
            }
            $db->commit();
        } elseif(!empty($this->needRequestId)) {
            $msg = $result ? $result : '请求没有返回结果';
            foreach($this->needRequestId as $val) {
                $waybillCodeArr['fail'][] = array(
                    'delivery_id' => $val,
                    'msg' => $msg
                );
            }
        }
        return $waybillCodeArr;
    }

    //直连请求获取电子面单
    public function directGetWaybill($delivery_id) {
        if(empty($this->delivery)) {
            return array(
                'rsp' => 'fail',
                'doSucc' => 0,
                'succ' => array(),
                'doFail' => count($delivery_id),
                'fail' => array('msg' => '没有发货单')
            );
        }
        $this->directRet = array();
        foreach($this->delivery as $delivery) {
            $hasDelivery[] = $delivery['delivery_id'];
            if(!$this->checkLogisticsChannel($delivery['logi_id'])) {
                $this->directRet['fail'][] = array(
                    'delivery_id' => $delivery['delivery_id'],
                    'delivery_bn' => $delivery['delivery_bn'],
                    'msg' => '物流公司已经切换，不能获取'
                );
                continue;
            }
            if (!empty($delivery['logi_no']) && $this->isExistLogino($delivery)) {
                $this->directRet['succ'][] = array(
                    'logi_no' => $delivery['logi_no'],
                    'delivery_id' => $delivery['delivery_id'],
                    'delivery_bn' => $delivery['delivery_bn']
                );
            } else {

                $delivery['shop'] = $this->getShop($delivery['shop_id']);
                $orderList = $this->getDeliveryOrder($delivery['delivery_id']);
                foreach ($orderList['order_info'] as $ov) {
                    $delivery['order_bns'][] = $ov['order_bn'];
                }

                $needRequest[] = $delivery;
                $this->needRequestId[] = $delivery['delivery_id'];
            }
        }
        $noDelivery = array_diff($delivery_id, $hasDelivery);
        if($noDelivery) {
            foreach($noDelivery as $val) {
                $this->directRet['fail'][] = array(
                    'delivery_id' => $val,
                    'msg' => '没有该发货单'
                );
            }
        }
        if($needRequest) {
            $sdf = $this->deliveryToSdf($needRequest);
            $back = $this->request('directRequest', $sdf);
            $backRet = $this->directCallback($back);
            if($backRet['succ']) {
                foreach($backRet['succ'] as $val) {
                    $this->directRet['succ'][] = $val;
                }
            }
            if($backRet['fail']) {
                foreach($backRet['fail'] as $val) {
                    $this->directRet['fail'][] = $val;
                }
            }
        }
        $this->directRet['rsp'] = count($this->directRet['fail']) ? 'fail' : 'succ';
        $this->directRet['doSucc'] = count($this->directRet['succ']);
        $this->directRet['doFail'] = count($this->directRet['fail']);
        return $this->directRet;
    }

    function getChannelExtend(){
        static $shop_address;
        if(!empty($shop_address)) {
            return $shop_address;
        }
        $extendObj = app::get('logisticsmanager')->model('channel_extend');
        $shop_address = $extendObj->dump(array('channel_id'=>$this->channel['channel_id']),'shop_name,province,city,area,address_detail,seller_id,default_sender,mobile,tel,zip,shop_name');
        return $shop_address;
    }

    //获取两条发货单明细
    public function getDeliveryItems($delivery_id)
    {
        static $deliveryItems = array(), $electronProductName = '';
        if(empty($deliveryItems)) {
            $electronProductName = trim(app::get('ome')->getConf('ome.electron.product.name'));
        }
        
        if(!$deliveryItems[$delivery_id]) {
            $deliveryItems[$delivery_id] = app::get('brush')->model('delivery_items')->getList('*', array('delivery_id' => $delivery_id),0,2);
            if($electronProductName) {
                foreach($deliveryItems[$delivery_id] as &$val) {
                    $val['product_name'] = $electronProductName;
                }
            }
        }
        
        return $deliveryItems[$delivery_id];
    }

    //获取两条订单明细
    public function getOrderItems($order_id) {
        static $orderItems = array(), $electronProductName = '';
        if(empty($deliveryItems)) {
            $electronProductName = trim(app::get('ome')->getConf('ome.electron.product.name'));
        }
        if (!$orderItems[$order_id]) {
            $orderItems[$order_id] = app::get('ome')->model('order_items')->getList('*', array('order_id' => $order_id), 0, 2);
            if($electronProductName) {
                foreach($orderItems[$order_id] as &$val) {
                    $val['name'] = $electronProductName;
                }
            }
        }
        return $orderItems[$order_id];
    }

    private function setDeliveryOrder($delivery_id) {
        $db = kernel::database();
        $where = is_array($delivery_id) ? 'd.delivery_id in (' . (implode(',', $delivery_id)) . ')' : 'd.delivery_id = "' . $delivery_id .'"';
        $field = 'o.order_id, o.order_bn, o.total_amount, o.shop_id, o.createway, o.shop_type, d.delivery_id';
        $sql = 'select '. $field . ' from sdb_brush_delivery_order as d left join sdb_ome_orders as o using(order_id) where ' . $where;
        $result = $db->select($sql);
        $data = array();
        foreach ($result as $k => $v) {
            $data[$v['delivery_id']]['order_id'][] = $v['order_id'];
            $data[$v['delivery_id']]['order_info'][$v['order_id']] = $v;
        }
        return $data;
    }

    /**
     * 获取发货订单
     * @param Int/Array $delivery_id 发货单ID
     * @return 单条/多条
     */
    public function getDeliveryOrder($delivery_id) {
        static $deliveryOrder = array();
        if(is_array($delivery_id)) {
            $retArr = array();
            foreach($delivery_id as $val) {
                if($deliveryOrder[$val]) {
                    $retArr[$val] = $deliveryOrder[$val];
                } else {
                    $needSet[] = $val;
                }
            }
            if(!empty($needSet)) {
                $data = $this->setDeliveryOrder($needSet);
                foreach($needSet as $value) {
                    $deliveryOrder[$value] = $data[$value];
                    $retArr[$value] = $deliveryOrder[$value];
                }
            }
            return $retArr;
        }
        if (!$deliveryOrder[$delivery_id]) {
            $data = $this->setDeliveryOrder($delivery_id);
            $deliveryOrder[$delivery_id] = $data[$delivery_id];
        }
        return $deliveryOrder[$delivery_id];
    }

    /**
     * 获取物流公司信息
     * @param Int $corp_id 物流公司ID
     */
    public function getDlyCorp($corp_id) {
        static $dlyCorp = array();
        if (!$dlyCorp[$corp_id]) {
            $dlyCorp[$corp_id] = app::get('ome')->model('dly_corp')->dump(array('corp_id' => $corp_id));
        }
        return $dlyCorp[$corp_id];
    }

    /**
     * 获得店铺信息
     * @param String $shop_id 店铺ID
     */
    public function getShop($shop_id) {
        static $shop = array();
        if (!$shop[$shop_id]) {
            $shop[$shop_id] = app::get('ome')->model('shop')->dump(array('shop_id' => $shop_id));
        }
        return $shop[$shop_id];
    }


    /**
     * 获取订单总额
     * @param Int $delivery_id 发货单ID
     * @return 总额
     */
    public function getOrderTotalAmount($delivery_id){
        static $dTotalAmount = array();
        if(!$dTotalAmount[$delivery_id]) {
            $orderList = $this->getDeliveryOrder($delivery_id);
            $totalAmount = 0;
            foreach ($orderList['order_info'] as $k => $v) {
                $totalAmount += $v['total_amount'];
            }
            $dTotalAmount[$delivery_id] = $totalAmount;
        }
        return $dTotalAmount[$delivery_id];
    }

    public function getGoods($bn) {
        static $goods = array();
        if(!$goods[$bn]) {
            $goods[$bn] = app::get('ome')->model('goods')->getList('*', array('bn' => $bn));
        }
        return $goods[$bn];
    }

    #验证物流公司是否是该渠道的
    protected function checkLogisticsChannel($logiId) {
        static $channelLogistics = array();
        if(empty($channelLogistics)) {
            $arrLogistics = app::get('ome')->model('dly_corp')->getList('corp_id', array('channel_id'=>$this->channel['channel_id']));
            foreach($arrLogistics as $val) {
                $channelLogistics[] = $val['corp_id'];
            }
        }
        if(in_array($logiId, $channelLogistics)) {
            return true;
        } else {
            return false;
        }
    }
}