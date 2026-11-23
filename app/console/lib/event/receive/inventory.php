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

class console_event_receive_inventory extends console_event_response{

    /**
     *
     * 盘点单事件处理
     * @param array $datainStorage
     */
    public function create($data){
       
        $inventoryObj = kernel::single('console_receipt_inventory');
        $result = $inventoryObj->do_inventory($data,$msg);
        $io_source = $data['io_source'];
        if($result){
            if ($io_source == 'selfwms' || $data['autoconfirm'] == 'Y'){
                $inventoryObj->finish_inventory($data['inventory_bn'],$data['branch_bn'],$data['inventory_type'],$data['items']);
                return $this->send_succ('盘点单操作成功');
            }else{
                return $this->send_succ('盘点单操作成功');
            }
        }else{
            return $this->send_error($msg);
        }
        
        
    }

    
}