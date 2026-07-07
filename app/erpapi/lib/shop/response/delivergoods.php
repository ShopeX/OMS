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
 * 订单催发货(店小蜜)
 *
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 0.1
 */
class erpapi_shop_response_delivergoods extends erpapi_shop_response_abstract
{
    const URGENT_SERVICE_TAG = '加急发货';
    const URGENT_ORDER_FIELDS = 'order_id, order_bn, process_status, status, ship_status, order_bool_type, shop_id, shop_type, source, createway, sync, sync_fail_type, abnormal, pause, timing_confirm, pay_status, is_cod, order_type, logi_no,last_modified';

    /**
     * 接收参数
     */

    public $_sdf = array();
    
    /**
     * 催发货
     * 
     * @param array $params
     * @return array
     */
    public function urgent($params){
        $tid = $params['tid'];
        $serviceTags = $this->normalizeServiceTags($params['service_tags']);
        $isUrgentShip = in_array(self::URGENT_SERVICE_TAG, $serviceTags, true);

        $this->__apilog['title']          = sprintf('%s[%s]', $isUrgentShip ? '淘宝加急发货' : '催发货', $tid);
        $this->__apilog['original_bn']    = $tid;
        $this->__apilog['result']['data'] = array('tid' => $tid);

        if (!$tid) {
            $this->__apilog['result'] = $this->buildFailResult('PARAM_ERROR', '缺少订单号', false, $tid);
            return false;
        }

        $shop_id = $this->__channelObj->channel['shop_id'];
        $sellerId = trim((string)($params['seller_id'] ?: $params['sellerId']));

        //检查订单
        $order = $isUrgentShip
            ? $this->getLocalOrder(self::URGENT_ORDER_FIELDS, $shop_id, $tid)
            : $this->getOrder(self::URGENT_ORDER_FIELDS, $shop_id, $tid);

        if (!$order) {
            $this->__apilog['result'] = $this->buildFailResult('ORDER_NOT_FOUND', 'ERP不存在此单', false, $tid);
            return false;
        }

        if ($isUrgentShip && $sellerId && !$this->isMatchedSellerId($shop_id, $sellerId)) {
            $this->__apilog['result'] = $this->buildFailResult('INVALID_SELLER_ID', 'seller_id与店铺不匹配', false, $tid);
            return false;
        }

        if($order['status'] == 'dead'){
            $this->__apilog['result'] = $this->buildFailResult('ORDER_CANCELLED', '订单已作废', false, $tid);
            return false;
        }

        if ($order['ship_status'] == '1') {
            $this->__apilog['result'] = $this->buildFailResult('ORDER_ALREADY_SHIPPED', '订单已经发货', false, $tid);
            return false;
        }

        $order['service_tags'] = $serviceTags;
        $order['is_urgent_ship'] = $isUrgentShip ? 'true' : 'false';
        $order['seller_name'] = $params['seller_name'];
        $order['logistics_time'] = $params['logistics_time'];
        $order['notify_time'] = $params['notify_time'];
        $order['estimated_delivery_time'] = $params['estimated_delivery_time'];
        return $order;
    }

    /**
     * 统一规整矩阵透传的 service_tags。
     *
     * 当前联调约定优先传一维数组；如果上游仍传字符串，则先尝试按 JSON 解码成数组后再使用。
     *
     * @param mixed $serviceTags
     * @return array
     */
    protected function normalizeServiceTags($serviceTags)
    {
        if (is_string($serviceTags)) {
            $serviceTags = trim($serviceTags);
            if ($serviceTags === '') {
                return [];
            }

            $decodedServiceTags = json_decode($serviceTags, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedServiceTags)) {
                $serviceTags = $decodedServiceTags;
            } else {
                return [$serviceTags];
            }
        }

        if (!is_array($serviceTags)) {
            return [];
        }

        $normalized = [];
        foreach ($serviceTags as $serviceTag) {
            if (is_scalar($serviceTag)) {
                $serviceTag = trim((string)$serviceTag);
                if ($serviceTag !== '') {
                    $normalized[] = $serviceTag;
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * 查询本地或归档订单，供加急发货场景做订单校验。
     *
     * @param string $field
     * @param int $shopId
     * @param string $orderBn
     * @return array|false
     */
    protected function getLocalOrder($field, $shopId, $orderBn)
    {
        $orderModel = app::get('ome')->model('orders');
        $orders     = $orderModel->getList($field, array ('order_bn' => $orderBn, 'shop_id' => $shopId), 0, 1);
        if ($orders) {
            if ($orders[0]['order_type'] == 'brush') {
                return false;
            }
            return $orders[0];
        }

        $archiveFields = $this->normalizeArchiveFields($field);
        $archiveOrders = app::get('archive')->model('orders')->getList($archiveFields, array ('order_bn' => $orderBn, 'shop_id' => $shopId), 0, 1);
        if (!$archiveOrders) {
            return false;
        }

        $order = $archiveOrders[0];
        if ($order['order_type'] == 'brush') {
            return false;
        }

        $defaults = array (
            'order_bool_type' => 0,
            'sync'            => 'fail',
            'sync_fail_type'  => 'none',
            'abnormal'        => 'false',
            'pause'           => 'false',
            'timing_confirm'  => 0,
            'pay_status'      => (string)$order['pay_status'],
            'is_cod'          => (string)$order['is_cod'],
            'logi_no'         => '',
            'tran_type'       => 'archive',
        );

        return array_merge($defaults, $order);
    }

    /**
     * 过滤归档订单可查询字段，避免按正式订单字段直接查归档表。
     *
     * @param string $field
     * @return string
     */
    protected function normalizeArchiveFields($field)
    {
        $available = array (
            'order_id', 'order_bn', 'process_status', 'status', 'ship_status', 'shop_id', 'shop_type',
            'source', 'createway', 'pay_status', 'is_cod', 'order_type'
        );
        $fields    = preg_split('/\s*,\s*/', $field);
        $fields    = array_values(array_intersect($fields, $available));
        if (!in_array('order_id', $fields)) {
            $fields[] = 'order_id';
        }
        return implode(',', $fields);
    }

    /**
     * 校验平台传入 seller_id 是否匹配当前店铺绑定的卖家身份。
     *
     * @param int $shopId
     * @param string $sellerId
     * @return bool
     */
    protected function isMatchedSellerId($shopId, $sellerId)
    {
        $shop         = app::get('ome')->model('shop')->dump($shopId, 'addon');
        $addon        = (array)$shop['addon'];
        $candidateIds = array_filter(array (
            (string)$addon['tb_user_id'],
            (string)$addon['user_id'],
            (string)$addon['seller_id'],
        ));
        if (!$candidateIds) {
            return true;
        }
        return in_array((string)$sellerId, $candidateIds, true);
    }

    /**
     * 统一构造催发货/加急发货失败响应。
     *
     * @param string $errorCode
     * @param string $msg
     * @param bool $retry
     * @param string $tid
     * @return array
     */
    protected function buildFailResult($errorCode, $msg, $retry = false, $tid = '')
    {
        return [
            'rsp'        => 'fail',
            'msg'        => $msg,
            'error_code' => $errorCode,
            'retry'      => $retry ? 'true' : 'false',
            'data'       => ['tid' => $tid],
        ];
    }
    
    /**
     * 获取数据
     * 
     * @param array $params
     * @return array:
     */
    protected function _returnParams($params) {
        return array();
    }
    
    /**
     * 格式化参数
     * 
     * @param array $params
     * @return array:
     */
    protected function _formatParams($params) {
        $sdf = array('order_bn'=>$params['tid']);
        
        return $sdf;
    }

    public function promise($params)
    {
        $tid = $params['orderId'];

        $this->__apilog['title']          = sprintf('时效订单[%s]', $tid);
        $this->__apilog['original_bn']    = $tid;
        $this->__apilog['result']['data'] = array('tid' => $tid);

        if (!$tid) {
            $this->__apilog['result']['msg'] = '缺少订单号';
            return false;
        }

        $shop_id = $this->__channelObj->channel['shop_id'];

        //检查订单
        $order = $this->getOrder('order_id, order_bn, process_status, status, ship_status, order_bool_type, shop_id', $shop_id, $tid);

        if (!$order) {
            $this->__apilog['result']['msg'] = 'ERP不存在此单';
            return false;
        }
        $sdf = $this->_formatPromiseParams($params, $order);
        return $sdf;
    }

    protected function _formatPromiseParams($params, $order)
    {
        $sdf = [
            'pick_date' => $params['pickDate'],
            'delivered_time' => $params['deliveredTime'],
            'event_type' => $params['event_type'],
            'order' => $order
        ];
        return $sdf;
    }
}
