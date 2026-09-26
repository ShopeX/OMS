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
 *
 * @author sunjing@shopex.cn
 * @version
 */
class erpapi_shop_response_plugins_order_confirmreceipt extends erpapi_shop_response_plugins_order_abstract
{
    /**
     * convert
     * @param erpapi_shop_response_abstract $platform platform
     * @return mixed 返回值
     */

    public function convert(erpapi_shop_response_abstract $platform)
    {
        $extend = array();

        if ($platform->_ordersdf['end_time']) {
            $extend['end_time'] = $platform->_ordersdf['end_time'];
        }

        return $extend;
    }

    /**
     *
     * @param Array
     * @return void
     * @author
     **/
    public function postUpdate($order_id, $extendinfo)
    {
        $orderObj = app::get('ome')->model('orders');

        if ($extendinfo['end_time']) {
            $orderObj->update(array('end_time' => $extendinfo['end_time']), array('order_id' => $order_id));
            
            // 否允许开蓝票
            $is_check_blue_invoice = true;
            $params = [];
            
            // 注册service，分析是否允许开蓝票
            foreach(kernel::servicelist('ome.service.invoice.analysis.prepose') as $object)
            {
                if(method_exists($object, 'check_blue_invoice')){
                    // check
                    $analysisResult = $object->check_blue_invoice($params);
                    if(isset($analysisResult['rsp']) && $analysisResult['rsp'] == 'fail'){
                        $is_check_blue_invoice = false;
                    }
                }
            }
            
            // 允许开蓝票
            if($is_check_blue_invoice){
                // 自动开票
                $einvoice = app::get('invoice')->model('order')->getList('*', array('order_id' => $order_id, 'is_status' => '0'), 0, 1, 'id DESC');
                if ($einvoice) {
                    $billing = array(
                        "id"       => $einvoice[0]['id'],
                        "order_id" => $order_id,
                    );
                    kernel::single('invoice_process')->billing($billing, 'sign');
                }
            }
            
            // 订单签收后触发服务
            foreach(kernel::servicelist('ome.service.order.sign.after') as $service) {
                if(method_exists($service,'after_sign')) {
                    $payload = [];
                    if (isset($extendinfo['end_time'])) {
                        $payload['end_time'] = $extendinfo['end_time'];
                    }
                    $service->after_sign($order_id, $payload);
                }
            }

            // 平台确认收货后，回写 OME 发货单签收并同步下游 WMS/门店
            kernel::single('ome_delivery_sign')->signOrderDeliveries(
                $order_id,
                $extendinfo['end_time'],
                '平台确认收货同步签收'
            );
        }
    }
}
