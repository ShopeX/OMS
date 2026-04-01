<?php

class erpapi_shop_matrix_alibaba_request_logistics extends erpapi_shop_request_logistics
{
    /**
     * 官方提货（提货物流）- 阿里巴巴/菜鸟 sendGoods 结构
     * @param array $sdf 由 ome_event_trigger_shop_data_delivery_alibaba->get_sdf 得到
     * @return mixed
     */
    public function officialPickup($sdf = [])
    {
        if (empty($sdf['orderinfo']['order_bn']) || empty($sdf['delivery_items'])) {
            return $this->succ('', '', []);
        }

        $sendGoodEntries = [];
        foreach ($sdf['delivery_items'] as $item) {
            if (empty($item['oid'])) {
                continue;
            }
            $amount = !empty($item['nums']) ? (int)$item['nums'] : (isset($item['number']) ? (int)$item['number'] : 0);
            $sendGoodEntries[] = [
                'sourceEntryId' => $item['oid'],
                'amount'        => $amount,
                'weight'        => isset($item['weight']) ? (float)$item['weight'] : 0,
            ];
        }
        if (empty($sendGoodEntries)) {
            return $this->succ('', '', []);
        }

        $sendGoods = [
            [
                'sourceId'        => $sdf['orderinfo']['order_bn'],
                'sendGoodEntries' => $sendGoodEntries,
            ],
        ];

        $params = [
            'sendGoods' => is_string($sendGoods) ? $sendGoods : json_encode($sendGoods),
            'remarks'   => isset($sdf['memo']) ? $sdf['memo'] : '',
            'gmtSend'   => !empty($sdf['delivery_time']) ? date('Y-m-d H:i:s', $sdf['delivery_time']) : date('Y-m-d H:i:s', time()),
        ];

        // 运单号回写已迁至 wms_lib_logistics，在 officialPickup 返回 succ 后统一处理
        $title = '官方物流提货';
        $result = $this->__caller->call(SHOP_LOGISTICS_OFFICIAL_PICKUP, $params, [], $title, 10, $sdf['delivery_bn']);
        return $result;
    }
}
