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

class console_event_receive_reship extends console_event_response
{
    /**
     * 退换货单回传事件处理
     * 
     * @param array $data
     * @return array
     */
    public function updateStatus($data)
    {
        $status = $data['status'];
        $reshipObj = kernel::single('console_receipt_reship');
        $msg = '';
        
        //check
        $check = $reshipObj->checkValid($data['reship_bn'], $status,$msg);
        if (!$check){
            return $this->send_error($msg);
        }
        
        $result = false;
        switch($status){
            case 'PARTIN':
            case 'FINISH':
                $result = kernel::single('console_receipt_reship')->updateStatus($data,$msg);
                break;
            case 'DENY':
            case 'CLOSE':
                $result = kernel::single('console_receipt_reship')->cancel($data,$msg);
                break;
            case 'ACCEPT':
                //京东一件代发
                if($data['wms_type'] == 'yjdf'){
                    //接收京东服务单
                    $result = kernel::single('console_receipt_reship')->reship_accept($data, $msg);
                    if(!$result){
                        $msg = ($msg ? '接单失败：'.$msg : '接单失败');
                        
                        return $this->send_error($msg);
                    }
                    
                    //处理京东服务单MQ消息
                    if($data['afsResultType']){
                        $keplerLib = kernel::single('ome_reship_kepler');
                        $data['action'] = 'disposeMQ';
                        $result = $keplerLib->process($data);
                    }
                    
                    $log_msg = '接收京东服务单成功';
                    if($data['afsResultType']){
                        $log_msg .= '(afsResultType：'. $data['afsResultType'] .'，stepType：'. $data['stepType'] .')';
                    }
                    
                    return $this->send_succ($log_msg);
                }
                
                return $this->send_succ('接单成功');
                break;
            default:
                $msg = 'status：'.$status.' 不支持此业务状态';
            break;
        }
        
        if ($result){
            if(in_array($status, array('CLOSE'))){
                return $this->send_succ('取消退货单成功');
            }else{
                return $this->send_succ('退货操作成功');
            }
        }else{
            $msg = $msg!='' ? $msg :'更新失败';
            return $this->send_error($msg);
        }
    }
    
    /**
     * WMS京东云交易订单退款成功MQ消息
     *
     * @param array $params
     * @return array
     */
    public function service_refund($params)
    {
        $reshipObj = app::get('ome')->model('reship');
        $processObj = app::get('ome')->model('return_process');
        $keplerLib = kernel::single('ome_reship_kepler');
        
        $reship_bn = $params['reship_bn'];
        $service_bn = $params['service_bn'];
        if(empty($reship_bn)){
            return $this->send_error('京东退款MQ消息,没有有效的参数');
        }
        
        //退货单信息
        $reshipInfo = $reshipObj->dump(array('reship_bn'=>$reship_bn), '*');
        if(empty($reshipInfo)){
            return $this->send_error('京东退款MQ消息,没有找到退货单信息');
        }
        
        //服务单信息
        $processInfo = $processObj->dump(array('reship_id'=>$reshipInfo['reship_id'], 'service_bn'=>$service_bn), 'por_id');
        if(empty($processInfo)){
            return $this->send_error('京东退款MQ消息,服务单号：'. $service_bn .' 不存在');
        }
        
        //params
        //$refundId = trim($params['refundId']); //退款唯一标识
        $refundFee = $params['refund_fee']; //退款金额
        $refund_time = ($params['refund_time'] ? strtotime($params['refund_time']) : 0); //退款时间
        
        //update
        $updateSdf = array(
                //'wms_refund_id' => $refundId,
                'wms_refund_fee' => $refundFee,
                'wms_refund_time' => $refund_time,
        );
        $processObj->update($updateSdf, array('por_id'=>$processInfo['por_id']));
        
        //自动同意平台退货
        $data = array_merge($params, $reshipInfo);
        $data['action'] = 'disposeMQ';
        $data['afsResultType'] = 'service_refund';
        $result = $keplerLib->process($data);
        
        return $this->send_succ('保存京东云交易订单退款MQ消息成功');
    }
}