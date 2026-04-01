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
 * 千牛修改地址接口处理
 *
 * @category
 * @package
 * @author sunjing<sunjing@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_response_process_qianniu extends erpapi_shop_response_abstract
{
    /**
     * 添加ress_modify
     * @param mixed $convert_order convert_order
     * @return mixed 返回值
     */

    public function address_modify($convert_order)
    {
        $operLobObj = app::get('ome')->model('operation_log');
        
        $new_order          =   $convert_order['new_order'];
        $order_detail       =   $convert_order['order_detail'];
        $shop_id = $order_detail['shop_id'];
        $order_bn = $order_detail['order_bn'];
        //走编辑订单一样的流程
        $order_id = $new_order['order_id'];
        if(empty($order_id)) {
            return array('rsp'=>'succ','msg'=>'千牛/平台改地址成功');
        }
        
        //订单信息
        $orderModel = app::get('ome')->model('orders');
        $order = $orderModel->db_dump($order_id, '*');
        
        //暂停订单
        $rs = $orderModel->pauseOrder($order_id);
        if ($rs['rsp'] == 'fail'){
            $this->_confirmModifyAdress($shop_id, ['order_bn'=>$order_bn,'confirm'=>false]);
            $msg = '仓库已接单不支持改地址';
            
            // 如果是预售订单
            if ($order['order_type'] == 'presale'){
                $msg = '预售下沉订单需要进行仓库确认';
            }
            return array('rsp'=>'fail','msg'=>$msg,'msg_code'=>'200007');
        }
        $orderModel->rebackDeliveryByOrderId($order_id, true);
        $consignee = $new_order['consignee'];
        foreach ($consignee as $k => $v) {
            if($index = strpos($v, '>>')) {
                $consignee[$k] = substr($v, 0, $index);
            }
            if($k == 'area') {
                list(,$consignee[$k],) = explode(':', $v);
            }
        }
        $msgLog = "千牛/平台改地址为：".$consignee['name'].' '.$consignee['mobile'].' '.$consignee['area'].$consignee['addr'];
        $operLobObj->write_log('order_modify@ome',$order_id,$msgLog);
        $this->_confirmModifyAdress($shop_id, ['order_bn'=>$order_bn,'confirm'=>true]);
        
        //[翱象]地址变更后,重新获取建议的配送建议
        if(in_array($order['shop_type'], array('taobao', 'tmall'))){
            $orderTypeLib = kernel::single('ome_order_bool_type');
            $aoxiangLib = kernel::single('dchain_aoxiang');
            
            if($orderTypeLib->isAoxiang($order['order_bool_type'])) {
                //查询建议快递
                $error_msg = '';
                $axResult = $aoxiangLib->triggerOrderLogi($order, $error_msg);
                $log_msg = ($axResult ? '查询翱象建议快递成功' : '查询翱象建议快递失败：'. $error_msg);
                
                //log
                $operLobObj->write_log('order_modify@ome', $order['order_id'], $log_msg);
            }
        }
        
        // [大家电]收货人信息变更后,重新进行预约
        kernel::single('ome_order_reservation')->againReservation($order_id, 'address_change');
        
        return array('rsp'=>'succ','msg'=>'千牛/平台改地址成功');
    }

    /**
     * _confirmModifyAdress
     * @param mixed $shop_id ID
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function _confirmModifyAdress($shop_id, $data) {
        kernel::single('erpapi_router_request')->set('shop', $shop_id)->order_confirmModifyAdress($data);
    }

    /**
     * modifysku
     * @param mixed $sdf sdf
     * @return mixed 返回值
     */
    public function modifysku($sdf)
    {

        //记录师傅要更换sku的状态
        $orderExtendModel = app::get('ome')->model('order_extend');
        $orderExtendInfo = $orderExtendModel->dump(['order_id'=>$sdf['order_id']],'bool_extendstatus');
        $bool_extendstatus = 0;
        if($orderExtendInfo){
            $bool_extendstatus = $orderExtendInfo['bool_extendstatus'];
        }

        $data = array(
            'order_id' => $sdf['order_id'],
            'bool_extendstatus' => $bool_extendstatus | ome_order_bool_extendstatus::__UPDATESKU_ORDER,
        );
        $orderExtendModel->save($data);
        return array('rsp'=>'succ','msg'=>'成功');
    }
    
    /**
     * 同步平台地址至OMS
     * @param $sdf
     * @return array|string[]
     * @author db
     * @date 2023-05-31 10:39 上午
     */
    public function order_addr_modify($sdf)
    {
        $shop_id    = $sdf['shop_id'];
        $order_bn   = $sdf['order_bn'];
        $orderModel = app::get('ome')->model('orders');
        $filter     = array('order_bn' => $order_bn, 'shop_id' => $shop_id);
        
        $orderInfo = $orderModel->db_dump($filter, 'order_id,order_bn');
        if (!$orderInfo) {
            return array('rsp' => 'fail', 'msg' => '缺少订单数据');
        }
        $orderRsp = kernel::single('erpapi_router_request')->set('shop', $shop_id)->order_get_order_detial($order_bn);
        if ($orderRsp['rsp'] == 'succ') {
            $orderPull = $orderRsp['data']['trade'];
            kernel::single('ome_syncorder')->get_order_log($orderPull, $shop_id, $msg);
        } else {
            $this->__apilog['result']['msg'] = '单拉订单失败';
            return array();
        }
        $msgLog = "平台改地址成功";
        app::get('ome')->model('operation_log')->write_log('order_modify@ome', $orderInfo['order_id'], $msgLog);
        return array('rsp' => 'succ', 'msg' => '平台改地址成功');
    }
}
