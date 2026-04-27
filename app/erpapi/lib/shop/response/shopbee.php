<?php
class erpapi_shop_response_shopbee extends erpapi_shop_response_abstract {

    /**
     * 订单标签变更
     * @param array $params
     * @return array|false
     */
    public function tagchange($params){
        // 原有的预约退款逻辑
        $this->__apilog['title'] = '订单标签变更';
        $this->__apilog['original_bn'] = $params['tid'];

        // tag_key加个白名单，目前只支持sku_logistics_extra_info
        $white_list = ['sku_logistics_extra_info', 'shop_priority_delivery'];
        if (!in_array($params['tag_key'], $white_list)) {
            $this->__apilog['result']['msg'] = '不支持的标签类型';
            return false;
        }

        $shop_id = $this->__channelObj->channel['shop_id'];
        $order_bn = $params['tid'];
        if (!$order_bn) {
            $this->__apilog['result']['msg'] = '订单号(tid)为空';
            return false;
        }
        $order = $this->getOrder('order_id,order_bn', $shop_id, $order_bn);
        if (!$order) {
            $this->__apilog['result']['msg'] = '订单不存在';
            return false;
        }

        $tagInfoRaw = $params['tag_info'] ? json_decode($params['tag_info'], true) : [];

        $sdf = [
            'order_id' => $order['order_id'],
            'order_bn' => $order['order_bn'],
            'shop_id' => $this->__channelObj->channel['shop_id'],
            'tag_key' => $params['tag_key'],
            'tag_info' => $tagInfoRaw ?? [],
        ];

        return $sdf;
    }
}
