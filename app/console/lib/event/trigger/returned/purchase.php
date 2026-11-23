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

class console_event_trigger_returned_purchase{

    private static $return_status = array (
        1 => '已新建',
        2 => '退货完成',
        3 => '出库拒绝',
        4 => '取消',
        
      );

    private static $check_status = array(
        1 => '未审',
        2 => '已审',
    );

   
    /**
     *
     * 采购退货单通知创建发起方法
     * @param string $wms_id 仓库类型ID
     * @param array $data 采购通知数据信息
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function create($wms_id, &$data, $sync = false){
        //kernel::single('middleware_wms_request', $wms_id)->stockout_create($data, $sync);
        kernel::single('erpapi_router_request')->set('wms',$wms_id)->stockout_create($data);
    }

    /**
     *
     * 采购退货单通知创建发起的响应接收方法
     * @param array $data
     */
    public function create_callback($res){

    }

    /**
     *
     * 采购退货单通知状态变更发起方法
     * @param string $wms_id 仓库类型ID
     * @param string $po_bn 采购通知单编号
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function cancel($data, $sync = false){
        
        //kernel::single('middleware_wms_request', $wms_id)->stockout_cancel($data, $sync);
        kernel::single('erpapi_router_request')->set('wms',$wms_id)->stockout_cancel($data);
    }

    /**
     *
     * 采购通知状态变更发起方法
     * @param array $data
     */
    public function cancel_callback($res){

    }

}