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

class console_event_receive_otherStockout extends console_event_response{

    /**
     *
     * 其它出库事件处理
     * @param array $data
     */
    public function outStorage($data){
       
        $stockObj = kernel::single('console_receipt_stock');
        $io = '0';
        if ($data['io_source'] == 'selfwms'){//自有仓储不作处理
            //return $this->send_succ();
        }
       
        $io_status = $data['io_status'];
        if (!$stockObj->checkValid($data['io_bn'],$io_status,$msg)){
            return $this->send_error($msg);
        }

        switch($io_status){
            case 'PARTIN':
            case 'FINISH':
                $result = $stockObj->do_save($data,$io,$msg);
            break;
            case 'FAILED':
            case 'CANCEL':
            case 'CLOSE':
                $result = $stockObj->cancel($data,$io);
                break;
            default:
                return $this->send_succ('无法识别的操作指令');
                break;
        }
        if ($result){
            return $this->send_succ('出库处理成功');
        }else{
            return $this->send_error($msg,'',$data);
        }
    }
    

}