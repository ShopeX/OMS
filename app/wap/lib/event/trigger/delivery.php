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


class wap_event_trigger_delivery{

    /**
     *
     * 移动端门店自提单确认可自提回传
     * @param string $wms_id 仓库类型ID
     * @param array $data 请求参数
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function confirm($channel_id, &$data, $sync = false){
        $data['status'] = 'confirm';
        return kernel::single('erpapi_router_response')->set_channel_id($channel_id)->set_api_name('store.delivery.status_update')->dispatch($data);
    }

    /**
     *
     * 移动端门店自提单拒绝并打回
     * @param string $wms_id 仓库类型ID
     * @param array $data 请求参数
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function reback($channel_id, &$data, $sync = false){
        $data['status'] = 'cancel';
        return kernel::single('erpapi_router_response')->set_channel_id($channel_id)->set_api_name('store.delivery.status_update')->dispatch($data);
    }

    /**
     *
     * 移动端门店自提单发货完成回传
     * @param string $wms_id 仓库类型ID
     * @param array $data 请求参数
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function consign($channel_id, &$data, $sync = false){
        $data['status'] = 'delivery';
        return kernel::single('erpapi_router_response')->set_channel_id($channel_id)->set_api_name('store.delivery.status_update')->dispatch($data);
    }

    /**
     *
     * 移动端门店订单签收收货
     * 
     * @param string $wms_id 仓库类型ID
     * @param array $data 请求参数
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function sign($channel_id, &$data, $sync = false){
        $data['status'] = 'sign';
        return kernel::single('erpapi_router_response')->set_channel_id($channel_id)->set_api_name('store.delivery.status_update')->dispatch($data);
    }

    /**
     *
     * WMS发货单内容更新发起通知OMS的方法
     * @param string $channel_id 仓库类型ID
     * @param array $data 请求参数
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function doUpdate($channel_id, &$data, $sync = false){
        $data['status'] = 'update';
        return kernel::single('erpapi_router_response')->set_channel_id($channel_id)->set_api_name('store.delivery.status_update')->dispatch($data);
    }

    /**
     *
     * WMS发货单打印完成发起通知OMS的方法
     * @param string $wms_id 仓库类型ID
     * @param array $data 请求参数
     * @param string $sync 是否同步请求，true为同步，false异步，默认异步
     */
    public function doPrint($channel_id, &$data, $sync = false){
        $data['status'] = 'print';
        return kernel::single('erpapi_router_response')->set_channel_id($channel_id)->set_api_name('store.delivery.status_update')->dispatch($data);
    }
}

?>
