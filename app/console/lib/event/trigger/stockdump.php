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
* 转储单触发事件
*
*/
class console_event_trigger_stockdump {

    /**
     *
     * 转储单通知创建发起方法
     * @param string $wms_id 仓库类型ID
     * @param array $data 调拔单通知数据信息
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function create($wms_id, &$data, $sync = false) {
        if ($wms_id) {
            // return kernel::single('middleware_wms_request', $wms_id)->stockdump_create($data, $sync);
            return kernel::single('erpapi_router_request')->set('wms',$wms_id)->stockdump_create($data);
        }
        
    }

    /**
    * 
    *转储单创建发起的响应接收方法
    * @param array $data
    */
    public function create_callback($res) {

    }

    /**
     *
     * 转储单取消创建发起方法
     * @param string $wms_id 仓库类型ID
     * @param array $data 转储单取消数据信息
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
     public function updateStatus($wms_id, &$data, $sync = false){
        if ($wms_id) {
            // return kernel::single('middleware_wms_request', $wms_id)->stockdump_cancel($data, $sync);
            return kernel::single('erpapi_router_request')->set('wms',$wms_id)->stockdump_cancel($data);
        }
        

     }

     public function updateStatus_callback($res){
        
     }
}



?>