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
 * 唯品会JIT出库事件处理
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 1.0 vopick.php 2017-03-10
 */
class console_event_receive_vopstockout extends console_event_response
{
    /**
     * 出库事件处理
     * @param array $data
     */
    public function outStorage($data)
    {
        $io        = '0';
        $io_status = $data['io_status'];//出库类型(默认为FINISH全部)
        
        $stockObj = kernel::single('console_receipt_vopstock');
        
        switch($io_status){
            case 'PARTIN':
            case 'FINISH':
                $result = $stockObj->do_save($data, $io, $msg);
                break;
            case 'FAILED':
            case 'CANCEL':
            case 'CLOSE':
            default:
                return $this->send_succ('无法识别的操作指令');
        }
        
        //取消出库单
        if(in_array($io_status, array('FAILED','CANCEL','CLOSE'))){
            if ($result){
                return $this->send_succ('出库出库单成功');
            }else{
                return $this->send_error('取消出库单失败：'. $msg, '',$data);
            }
        }
        
        //出库单处理
        if ($result)
        {
            //[全部出库]回写唯品会三个接口
            if($io_status == 'FINISH')
            {
                $stockoutObj    = app::get('purchase')->model('pick_stockout_bills');
                $iso            = $stockoutObj->dump(array('stockout_no'=>$data['io_bn']), 'stockout_id');
                $stockout_id    = $iso['stockout_id'];
                
                $syncLib    = kernel::single('purchase_purchase_sync');
                $error_msg  = '';
                
                //初始化信息
                $result    = $syncLib->_initStockoutIfo($stockout_id, $error_msg);
                if(!$result)
                {
                    return $this->send_succ('出库处理成功');
                }
                
                //editDelivery
                $result    = $syncLib->editDelivery($error_msg);
                if(!$result)
                {
                    return $this->send_succ('出库处理成功');
                }
                
                //importDeliveryDetail
                $result    = $syncLib->importDeliveryDetail($error_msg);
                if(!$result)
                {
                    return $this->send_succ('出库处理成功');
                }
                
                //confirmDelivery
                $result    = $syncLib->confirmDelivery($error_msg);
                if(!$result)
                {
                    return $this->send_succ('出库处理成功');
                }
                
                //更新回传状态
                $stockoutObj->update(array('rsp_code'=>0), array('stockout_id'=>$stockout_id));
                
                //增加回传成功日志
                $logObj    = app::get('ome')->model('operation_log');

                $logObj->write_log('update_stockout_bills@ome', $stockout_id, '回传唯品会出仓单成功');
            }
            
            return $this->send_succ('出库处理成功');
        }else{
            return $this->send_error($msg,'',$data);
        }
    }
}
