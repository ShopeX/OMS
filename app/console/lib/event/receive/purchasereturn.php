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

class console_event_receive_purchasereturn extends console_event_response{

    /**
     *
     * 采购退货入库事件处理
     * @param array $data
     */
    public function outStorage($data){
       
       if ($data['io_source'] == 'selfwms'){//自有仓储不作处理
            #return $this->send_succ();
       }
       $io_status = $data['io_status'];
       $purchasereturnObj = kernel::single('console_receipt_purchasereturn');
       
        #判断状态是否可操作
        $msg = '';
        $purchasereturn = $purchasereturnObj->checkValid($data['io_bn'],$io_status,$msg);
        if (!$purchasereturn){
            return $this->send_error($msg,'',$data);

        }
       //判断 平台
        
        switch($io_status){#根据状态执行相应操作
            case 'FAILED':
            case 'CLOSE':
            case 'CANCEL'://取消
                //参数转换
                
                $result = $purchasereturnObj->cancel($data);
                break;
            case 'PARTIN':
            case 'FINISH'://入库
                
                $result = $purchasereturnObj->update($data,$msg);
                break;
            default:
                return $this->send_error('未定义操作');
                break;

        }
        if ($result){
            return $this->send_succ('采购退货操作成功');
        }else{
            return $this->send_error($msg,'',$data);
        }
        
           
       
    }


}