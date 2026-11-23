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
 * 库内转储
 */
class console_event_receive_stockdump extends console_event_response{

    /**
     * ioStorage
     * @param mixed $data 数据
     * @return mixed 返回值
     */

    public function ioStorage($data){
        $stockdumpObj = kernel::single('console_receipt_stockdump');
        $io_status = $data['status'];
        if ($data['io_source'] == 'selfwms'){#自有仓储不作处理
            return $this->send_succ();
        }
        
        //验证转储单是否存在
        if(!$stockdumpObj->checkExist($data['stockdump_bn'])){
           return $this->send_error('转储单'.$data['stockdump_bn'].'不存在');
        }

        //验证转储单当前状态是否有效
        $msg = '';
        if(!$stockdumpObj->checkValid($data['stockdump_bn'],$io_status,$msg)){
           return $this->send_error($msg);
        }

        switch($io_status){
            case 'FINISH':
                $stockdump_bn = $data['stockdump_bn'];
                
                $result = kernel::single('console_receipt_stockdump')->do_save($stockdump_bn,$data);
            break;
            case 'FAILED':
            case 'CANCEL':
            case 'CLOSE':
                $result = kernel::single('console_receipt_stockdump')->cancel($data['stockdump_bn']);
                break;
            default:
                return $this->send_succ('未定义的转储单操作');
                break;
        }
        if ($result){
            return $this->send_succ('转储单操作成功');
        }else{
            return $this->send_error('更新失败', '', $data);
        }
    }
}
