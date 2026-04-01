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
 * 预约退款
 * 2018.9.27 by wangjianjun
 */
class erpapi_shop_response_process_bookingrefund{

    /**
     * ordermsg
     * @param mixed $params 参数
     * @return mixed 返回值
     */

    public function ordermsg($params){
        $order_bn = $params['tid'];
        $orderModel = app::get('ome')->model('orders');
        $orderInfo = $orderModel->dump(array('order_bn' => $order_bn, 'shop_id'=>$params['shop_id']), 'order_id,order_bn,shop_id,status,ship_status,order_bool_type,payed,org_id');
        if(!$orderInfo || !$orderInfo['order_id']){
            kernel::single('erpapi_router_request')->set('shop',$params['shop_id'])->order_get_order_detial($order_bn);
            $orderInfo = $orderModel->dump(array('order_bn' => $order_bn), 'order_id,shop_id,status,ship_status,order_bool_type');
            if(empty($orderInfo)){
                return array('rsp'=>'succ', 'msg'=>'订单暂停成功');
            }
        }
        
        //发起暂停订单
        $pause_status = false;
        if ($orderInfo["status"] == "active" && $orderInfo["ship_status"] == "0"){
            $parseRet = $orderModel->pauseOrder($orderInfo['order_id'], true);
            if($parseRet['rsp'] == 'succ'){
                $pause_status = true;
                $oOperation_log = app::get('ome')->model('operation_log');
                $log_text_str = "买家预约退款(msg_id:".$params["msg_id"].",seller_nick:".$params["seller_nick"].",user_nick:".$params["user_nick"].")";
                $oOperation_log->write_log('order_modify@ome',$orderInfo['order_id'],$log_text_str);
                $order_bool_type = ome_order_bool_type::__BOOKING_REFUND;
                if($orderInfo["order_bool_type"]){
                    $order_bool_type = $orderInfo["order_bool_type"] | ome_order_bool_type::__BOOKING_REFUND;
                }
                $orderModel->update(array('order_bool_type'=>$order_bool_type), array('order_id'=>$orderInfo['order_id']));
            } else {
                $orderInfo['pause_fail_msg'] = $parseRet['msg'];
            }
        }
        if($orderInfo["status"] == 'cancel') {
            $pause_status = true;
        } else {
            if ($params['refundStatus'] == '9') {
                list($rs, $data) = $this->_dealRefund($orderInfo);
                if($rs) {
                    $pause_status = true;
                } else {
                    $orderInfo['pause_fail_msg'] = $data['msg'];
                }
            }
        }
        //$params["callType"] == "synchronous"
        if($params["call_type"] == "synchronous"){ //同步
            $result = array("result"=>array("success"=>$pause_status));
            if($pause_status){
                return array('rsp'=>'succ', 'msg'=>'订单暂停成功','result'=>$result);
            }else{
                return array('rsp'=>'fail', 'msg'=>'订单暂停失败','result'=>$result);
            }
        }else{ //异步asynchronous
            $orderInfo['pause_status'] = $pause_status;
            $ret = $this->ordermsg_back($orderInfo, $params);
            return array('rsp'=>'succ', 'msg' => $ret);
        }
    }

    public function fxordermsg($params){
        // shopbee供销供应商订单信息同步
        $order_bn = $params['order_bn'];
        $orderModel = app::get('ome')->model('orders');
        $orderInfo = $orderModel->dump(array('order_bn' => $order_bn, 'shop_id'=>$params['shop_id']), 'order_id,order_bn,shop_id,status,ship_status,order_bool_type,payed,org_id');
        if(!$orderInfo || !$orderInfo['order_id']){
            return array('rsp'=>'fail', 'msg'=>'订单不存在');
        }
        
        // 暂时只处理sellerComments添加到订单备注
        if (!empty($params['sellerComments'])) {
            list($success, $message) = kernel::single('ome_order_marktext')->add($orderInfo['order_id'], $params['sellerComments'], '供销供应商');
            if (!$success) {
                return array('rsp' => 'fail', 'msg' => $message);
            }
        }
        
        return array('rsp' => 'succ', 'msg' => '订单信息同步成功');
    }

    protected function ordermsg_back($orderInfo, $params) {
        $orderExtend = app::get('ome')->model('order_extend')->db_dump($orderInfo['order_id'], 'extend_field');
        $sdf = [
            'request_params' => $params, 
            'order' => $orderInfo, 
            'order_extend'=>$orderExtend
        ];
        $ret = kernel::single('erpapi_router_request')->set('shop', $orderInfo['shop_id'])->bookingrefund_orderMsgUpdate($sdf);
        return $ret;
    }
    
    protected function _dealRefund($sdf) {
        $data = array(
            'refund_bn'     => $sdf['order_bn'],
            'shop_id'       => $sdf['shop_id'],
            'order_id'      => $sdf['order_id'],
            'currency'      => 'CNY',
            'money'         => $sdf['payed'],
            'cur_money'     => $sdf['payed'],
            'pay_type'      => '',
            'download_time' => time(),
            'status'        => 'succ',
            'memo'          => '平台已经完成退款且关闭了交易订单',
            'trade_no'      => $sdf['order_bn'],
            'modifiey'      => time(),
            'payment'       => 0,
            't_ready'       => time(),
            't_sent'        => time(),
            't_received'    => 0,
            'org_id'    => $sdf['org_id'],
            'refund_refer' => '0', //退款来源
        );
        //insert
        $rs = app::get('ome')->model('refunds')->insert($data);
        if(!$rs) {
            return [false, ['msg'=>'退款单创建失败']];
        }
        $sql ="update sdb_ome_orders set payed=0  where order_id=".$sdf['order_id'];
        kernel::database()->exec($sql);
        kernel::single('ome_order_func')->update_order_pay_status($sdf['order_id'], true, __CLASS__.'::'.__FUNCTION__);
        return [true];
    }

    /**
     * ordercancle
     * @param mixed $params 参数
     * @return mixed 返回值
     */
    public function ordercancle($params) {
        $rsp = array('rsp'=>'succ', 'msg'=>'处理成功');

        $order_bn = $params['order_bn'];
        $orderMdl = app::get('ome')->model('orders');
        $orders = $orderMdl->db_dump(array('order_bn'=>$order_bn),'order_id');

        if($orders){
            $rs = $orderMdl->pauseOrder($orders['order_id']);
            if($rs['rsp'] == 'succ'){
                $orderMdl->cancel($orders['order_id'],$params['reason'],'','async',false);
            }
        }
        
        return $rsp;
    }
    

}
