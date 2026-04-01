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
 * 订单处理
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_request_order extends erpapi_shop_request_abstract
{
    /**
     * 淘宝全链路
     * 
     * @return void
     * @author 
     * */

    public function message_produce($sdf,$queue=false){}
    
    /**
     * 获取店铺订单详情
     * 
     * @param String $order_bn 订单号
     * @return void
     * @author
     * */
    public function get_order_detial($order_bn)
    {
        $params['tid'] = $order_bn;
        $params = $this->__forma_params_get_order_detial($params);
        $title = "店铺(".$this->__channelObj->channel['name'].")获取前端店铺".$order_bn."的订单详情";

        $order_type = ($this->__channelObj->channel['business_type'] == 'zx') ? 'direct' : 'agent';

        $api_name = $order_type == 'direct' ? SHOP_TRADE_FULLINFO_RPC : SHOP_FENXIAO_TRADE_FULLINFO_RPC;

        $rsp = $this->__caller->call($api_name,$params,array(),$title,10,$order_bn);

        $result = array();
        $result['rsp']        = $rsp['rsp'];
        $result['err_msg']    = $rsp['err_msg'];
        $result['msg_id']     = $rsp['msg_id'];
        $result['res']        = $rsp['res'];
        $result['data']       = json_decode($rsp['data'],1);
        $result['order_type'] = $order_type;
        
        return $result;
    }
    
        /**
     * __forma_params_get_order_detial
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function __forma_params_get_order_detial($params)
    {
        return $params;
    }

    #获取订单状态
    /**
     * 获取OrderStatus
     * @param mixed $arrOrderBn arrOrderBn
     * @return mixed 返回结果
     */
    public function getOrderStatus($arrOrderBn)
    {
        $order_bn = implode(',', $arrOrderBn);
        $params = array('tids' => $order_bn);
        $title = "店铺(" . $this->__channelObj->channel['name'] . ")获取前端店铺" . $order_bn . "的订单详情";
        $rsp = $this->__caller->call(SHOP_GET_ORDER_STATUS, $params, array(), $title, 10, $order_bn);
        return $this->doGetOrderStatusRet($rsp);
    }
    
    #获取订单发票汇总信息
    public function getActuallyPay($order_bn)
    {
        $params = array('tid' => $order_bn);
        $title = "店铺(" . $this->__channelObj->channel['name'] . ")获取前端店铺" . $order_bn . "的客户实付信息";
        $rsp = $this->__caller->call(SHOP_GET_TRADE_INVOICE_SUMMARY, $params, array(), $title, 10, $order_bn);
        return $this->doGetActuallyPayRet($rsp);
    }
    
    protected function doGetOrderStatusRet($rsp) {
        $rsp['data'] = json_decode($rsp['data'], 1);
        return $rsp;
    }
    
    protected function doGetActuallyPayRet($rsp) {
        if(empty($rsp['data'])) {
            $rsp['data'] = array();
        } else {
            $rsp['data'] = json_decode($rsp['data'], 1);
            
            // consumer_invoice_amount
            if(isset($rsp['data']['consumer_invoice_amount'])){
                $rsp['data']['actually_pay'] = $rsp['data']['consumer_invoice_amount'] / 100;
            }
        }
        return $rsp;
    }
    
    #订单编辑
    public function updateIframe($order,$is_request=true,$ext=array()) {
        // 默认本地编辑
        $data = array('edit_type'=>'local');
        return array('rsp'=>'success','msg'=>'本地订单编辑','data'=>$data);
    }

    protected function getReceivedParams($sdf) {
        return [];
    }

    /**
     * received
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function received($sdf){
        $order = $sdf['order'];
        list($api_name, $params) = $this->getReceivedParams($sdf);
        if(empty($params)) {
            return $this->succ('没有该接口');
        }
        $callback = array(
            'class' => get_class($this),
            'method' => 'callback',
        );
        $response = $this->__caller->call($api_name,$params,$callback,'订单接收回传',10,$order['order_bn']);

        return $response;
    }

    /**
     * reject
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function reject($sdf){}
    
    /**
     * lackApply
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function lackApply($sdf){}

    #订单更新
    /**
     * 更新Order
     * @param mixed $order order
     * @return mixed 返回值
     */
    public function updateOrder($order){}

    /**
     * 更新OrderStatus
     * @param mixed $order order
     * @param mixed $status status
     * @param mixed $memo memo
     * @param mixed $mode mode
     * @return mixed 返回值
     */
    public function updateOrderStatus($order , $status='' , $memo='' , $mode='sync'){}

    /**
     * 更新OrderTax
     * @param mixed $order order
     * @return mixed 返回值
     */
    public function updateOrderTax($order){}

    /**
     * 更新OrderShipStatus
     * @param mixed $order order
     * @param mixed $queue queue
     * @return mixed 返回值
     */
    public function updateOrderShipStatus($order,$queue = false) {}

    /**
     * 更新OrderPayStatus
     * @param mixed $order order
     * @return mixed 返回值
     */
    public function updateOrderPayStatus($order){}

    /**
     * 更新OrderMemo
     * @param mixed $order order
     * @param mixed $memo memo
     * @return mixed 返回值
     */
    public function updateOrderMemo($order,$memo){}

    /**
     * 添加OrderMemo
     * @param mixed $order order
     * @param mixed $memo memo
     * @return mixed 返回值
     */
    public function addOrderMemo($order,$memo){}

    /**
     * 添加OrderCustomMark
     * @param mixed $order order
     * @param mixed $memo memo
     * @return mixed 返回值
     */
    public function addOrderCustomMark($order,$memo){}

    #$sdf=['order_bn'=>'','confirm'=>true]
    /**
     * confirmModifyAdress
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function confirmModifyAdress($sdf){}

    protected function __formatUpdateOrderShippingInfo($order) {
        return array();
    }

    /**
     * 更新OrderShippingInfo
     * @param mixed $order order
     * @return mixed 返回值
     */
    public function updateOrderShippingInfo($order)
    {
        $rs = array('rsp'=>'fail','msg'=>'','data'=>'');
        if (!$order) {
            $rs['msg'] = 'no order';
            return $rs;
        }
        $params = $this->__formatUpdateOrderShippingInfo($order);
        if(empty($params)) {
            $rs['msg'] = 'no params';
            return $rs;
        }
        $callback = array(
            'class' => get_class($this),
            'method' => 'callback',
        );
        $title = '店铺('.$this->__channelObj->channel['name'].')更新[交易收货人信息]:'.$params['receiver_name'].'(订单号:'.$order['order_bn'].')';
        $rs = $this->__caller->call(SHOP_UPDATE_TRADE_SHIPPING_ADDRESS_RPC,$params,$callback,$title,10,$order['order_bn']);
        return $rs;
    }

    /**
     * 更新OrderConsignerinfo
     * @param mixed $order order
     * @return mixed 返回值
     */
    public function updateOrderConsignerinfo($order){}

    /**
     * 更新OrderSellagentinfo
     * @param mixed $order order
     * @return mixed 返回值
     */
    public function updateOrderSellagentinfo($order){}

    /**
     * 更新OrderLimitTime
     * @param mixed $order order
     * @param mixed $order_limit_time order_limit_time
     * @return mixed 返回值
     */
    public function updateOrderLimitTime($order,$order_limit_time){}

    #获取店铺指定时间范围内的订单
    /**
     * 获取OrderList
     * @param mixed $start_time start_time
     * @param mixed $end_time end_time
     * @return mixed 返回结果
     */
    public function getOrderList($start_time,$end_time) {
        $rs = array('rsp'=>'fail','msg'=>'','data'=>array(),'is_update_time'=>'false');
        $orderModel = app::get('ome')->model('orders');
        $params = array(
            'start_time' => date("Y-m-d H:i:s",$start_time),
            'end_time'   => date("Y-m-d H:i:s",$end_time),
            'page_size'  => 100,
            'fields'     => 'tid,status,pay_status,ship_status,modified',
            'page_no'    => 1,
        );

        $channel = $this->__channelObj->channel;


        $trades = array();$lastmodify = null;
        do {
            $title = sprintf('获取店铺%s(%s-%s内)的订单%s',$channel['name'],$params['start_time'],$params['end_time'],$params['page_no']);

            $return_data = $this->__caller->call(SHOP_GET_TRADES_SOLD_RPC,$params,array(),$title,10,$channel['shop_id']);
            if ($return_data['data']) $return_data['data'] = @json_decode($return_data['data'], true);

            if ($return_data['rsp'] != 'succ') break;

            if (($params['page_no']-1)*$params['page_size']>intval($return_data['data']['total_results'])) break;

            $tids = array();
            foreach((array)$return_data['data']['trades'] as $t){
                $trades[$t['tid']] = $t;
                
                $tids[] = $t['tid'];

                $lastmodify = strtotime($t['modified']);
            }

            if ($tids) {
                $erporders = $orderModel->getList('outer_lastmodify,order_bn',array('order_bn'=>$tids,'shop_id'=>$channel['shop_id']));
                // 判断是否漏单
                foreach ($erporders as $order) {
                    if ($order['outer_lastmodify']>=strtotime($trades[$order['order_bn']]['modified'])) {
                        unset($trades[$order['order_bn']]);
                    }
                }
            }

            $params['page_no']++;
        } while (true);

        $return = array(
            'rsp'        => $return_data['rsp'] == 'succ' ? 'success' : 'fail',
            'msg'        => ($return_data['rsp'] == 'succ' && !$trades) ? '未发现漏单' : $return_data['msg'],
            'msg_id'     => $return_data['msg_id'],
            'data'       => $trades,
            'lastmodify' => $lastmodify,
        );

        return $return;
    }

    /**
     * cleanStockFreeze
     * @param mixed $order order
     * @return mixed 返回值
     */
    public function cleanStockFreeze($order){}

    /**
     * oid_sync
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function oid_sync($sdf){}

    /**
     * serial_sync
     * @param mixed $serialNumber serialNumber
     * @return mixed 返回值
     */
    public function serial_sync($serialNumber) {
        $params = [];
        $billId = [];
        $billNo = '';
        foreach ($serialNumber as $value) {
            $billId[$value['bill_id']] = $value['bill_id'];
            $billNo = $value['bill_no'];
            $params[$value['bill_no']][] = $value['serial_number'];
        }
        $params = ['serial_number_data'=>json_encode($params)];
        $title = '同步唯一码';
        $obj = app::get('ome')->model('product_serial_history');
        $snFilter = ['bill_id'=>$billId, 'bill_type'=>'3', 'sync|noequal'=>'succ'];
        $obj->update(['sync'=>'run'], $snFilter);
        $return_data = $this->__caller->call(SHOP_SERIALNUMBER_UPDATE,$params,array(),$title,10,$billNo);
        if($return_data['rsp'] == 'succ') {
            $obj->update(['sync'=>'succ'], $snFilter);
        } else {
            $obj->update(['sync'=>'fail'], $snFilter);
        }
        return $return_data;
    }
    
    /**
     * [翱象系统]查询黑白名单快递
     * 
     * @param array $params
     * @return array
     */
    public function selectOrderLogi($orderObjList)
    {
        $title = '查询黑白名单快递';
        
        $original_bn = $orderObjList[0]['order_code'];
        
        //params
        $requestParams = array(
            'delivery_decision' => json_encode($orderObjList),
        );
        
        //callback
        $callback = array();
        
        //request
        $result = $this->__caller->call(SHOP_AOXIANG_LOGISTICS_QUERY, $requestParams, $callback, $title, 10, $original_bn);
        
        return $result;
    }

    /**
     * 订单确认接口
     * 子类可以重写此方法实现具体的订单确认逻辑
     */
    public function confirm($order){}
    
    /**
     * 隐私号G组更新：报备外呼主叫号码组
     *
     * @param array $params 单个订单信息
     * @return array
     */
    public function bindSecretMobiles($params)
    {}
    
    /**
     * 隐私号G组查询：查询报备外呼主叫号码组
     *
     * @param array $params 单个订单信息
     * @return array
     */
    public function querySecretMobiles($params)
    {}
}